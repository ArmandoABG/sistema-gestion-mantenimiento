<?php

declare(strict_types=1);

/* REVISIÓN 3: compatible con PHP 7.4 o superior. */

const SM_ZONA_HORARIA = 'America/Mexico_City';
const SM_TIEMPO_INACTIVIDAD = 7200;      // 2 horas
const SM_REGENERAR_SESION_CADA = 1800;   // 30 minutos

/*
|--------------------------------------------------------------------------
| Compatibilidad con el login actual
|--------------------------------------------------------------------------
|
| Se conserva PHPSESSID para que el login existente y los módulos que ya
| utilizan session_start() compartan exactamente la misma sesión.
|
*/
const SM_NOMBRE_SESION = 'PHPSESSID';

date_default_timezone_set(SM_ZONA_HORARIA);

sm_iniciar_sesion_segura();
sm_cabeceras_seguridad();

function sm_iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (!headers_sent()) {
        session_name(SM_NOMBRE_SESION);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => sm_es_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_start();
}

function sm_cabeceras_seguridad(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function sm_es_https(): bool
{
    return (
        (
            !empty($_SERVER['HTTPS'])
            && strtolower((string) $_SERVER['HTTPS']) !== 'off'
        )
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
    );
}

function sm_es_ajax(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function sm_sesion_autenticada(): bool
{
    return isset($_SESSION['usuario_id'], $_SESSION['tipo_usuario'])
        && filter_var($_SESSION['usuario_id'], FILTER_VALIDATE_INT) !== false
        && (int) $_SESSION['usuario_id'] > 0
        && in_array(
            (string) $_SESSION['tipo_usuario'],
            ['ADMIN', 'SOLICITANTE', 'TECNICO'],
            true
        );
}

/**
 * @param string[] $rolesPermitidos
 */
function sm_requerir_sesion(
    array $rolesPermitidos = [],
    ?bool $json = null
): void {
    if ($json === null) {
        $json = sm_es_ajax();
    }

    if (!sm_sesion_autenticada()) {
        sm_sesion_invalida(
            $json,
            'Debes iniciar sesión para continuar.'
        );
    }

    $ultimaActividad = (int) ($_SESSION['ultima_actividad'] ?? time());

    if ((time() - $ultimaActividad) > SM_TIEMPO_INACTIVIDAD) {
        sm_destruir_sesion();

        sm_sesion_invalida(
            $json,
            'Tu sesión expiró por inactividad.'
        );
    }

    $_SESSION['ultima_actividad'] = time();

    $ultimaRegeneracion = (int) ($_SESSION['sesion_regenerada_en'] ?? 0);

    if (
        $ultimaRegeneracion === 0
        || (time() - $ultimaRegeneracion) >= SM_REGENERAR_SESION_CADA
    ) {
        session_regenerate_id(true);
        $_SESSION['sesion_regenerada_en'] = time();
    }

    if ($rolesPermitidos !== []) {
        $tipoUsuario = (string) ($_SESSION['tipo_usuario'] ?? '');

        if (!in_array($tipoUsuario, $rolesPermitidos, true)) {
            if ($json) {
                sm_responder_json(
                    false,
                    'No tienes permiso para realizar esta acción.',
                    [],
                    403
                );
            }

            header('Location: ../login.php?acceso=denegado');
            exit;
        }
    }
}

function sm_sesion_invalida(bool $json, string $mensaje): void
{
    if ($json) {
        sm_responder_json(
            false,
            $mensaje,
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?sesion=expirada',
            ],
            401
        );
    }

    header('Location: ../login.php?sesion=expirada');
    exit;
}

function sm_token_csrf(string $clave = 'csrf_token'): string
{
    if (
        empty($_SESSION[$clave])
        || !is_string($_SESSION[$clave])
        || strlen($_SESSION[$clave]) < 64
    ) {
        $_SESSION[$clave] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$clave];
}

function sm_validar_csrf(string $clave = 'csrf_token'): void
{
    $tokenSesion = $_SESSION[$clave] ?? '';
    $tokenRecibido = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';

    if (
        !is_string($tokenSesion)
        || !is_string($tokenRecibido)
        || $tokenSesion === ''
        || $tokenRecibido === ''
        || !hash_equals($tokenSesion, $tokenRecibido)
    ) {
        sm_responder_json(
            false,
            'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.',
            ['csrf_invalido' => true],
            419
        );
    }
}

function sm_requerir_metodo(string $metodo): void
{
    $metodo = strtoupper(trim($metodo));
    $metodoActual = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($metodoActual !== $metodo) {
        if (!headers_sent()) {
            header('Allow: ' . $metodo);
        }

        sm_responder_json(
            false,
            'Método de solicitud no permitido.',
            [],
            405
        );
    }
}

function sm_responder_json(
    bool $success,
    string $mensaje,
    array $extra = [],
    int $codigoHttp = 200
): void {
    if (!headers_sent()) {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    $respuesta = array_merge(
        [
            'success' => $success,
            'mensaje' => $mensaje,
        ],
        $extra
    );

    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function sm_limpiar_texto($valor): string
{
    if (!is_scalar($valor)) {
        return '';
    }

    $texto = trim((string) $valor);

    return preg_replace(
        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
        '',
        $texto
    ) ?? '';
}

function sm_telefono_mexico_opcional($valor): ?string
{
    $telefono = sm_limpiar_texto($valor);

    if ($telefono === '') {
        return null;
    }

    if (!preg_match('/^\d{10}$/', $telefono)) {
        sm_responder_json(
            false,
            'El teléfono debe contener exactamente 10 dígitos.',
            ['campo' => 'telefono'],
            422
        );
    }

    return $telefono;
}

function sm_correo_opcional($valor): ?string
{
    $correo = sm_limpiar_texto($valor);

    if ($correo === '') {
        return null;
    }

    if (
        mb_strlen($correo, 'UTF-8') > 150
        || !filter_var($correo, FILTER_VALIDATE_EMAIL)
    ) {
        sm_responder_json(
            false,
            'El correo electrónico no es válido.',
            ['campo' => 'correo'],
            422
        );
    }

    return mb_strtolower($correo, 'UTF-8');
}

function sm_entero_positivo($valor, string $campo): int
{
    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );

    if ($entero === false) {
        sm_responder_json(
            false,
            "El campo {$campo} no es válido.",
            ['campo' => $campo],
            422
        );
    }

    return (int) $entero;
}

function sm_ip_cliente(): ?string
{
    $ip = sm_limpiar_texto($_SERVER['REMOTE_ADDR'] ?? '');

    return $ip === '' ? null : substr($ip, 0, 60);
}

function sm_user_agent(): ?string
{
    $agente = sm_limpiar_texto($_SERVER['HTTP_USER_AGENT'] ?? '');

    return $agente === ''
        ? null
        : mb_substr($agente, 0, 255, 'UTF-8');
}

function sm_destruir_sesion(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $parametros['path'] ?: '/',
                'domain' => $parametros['domain'] ?? '',
                'secure' => (bool) ($parametros['secure'] ?? false),
                'httponly' => (bool) ($parametros['httponly'] ?? true),
                'samesite' => $parametros['samesite'] ?? 'Lax',
            ]
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}