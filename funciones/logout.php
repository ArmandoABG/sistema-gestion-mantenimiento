<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cierre seguro de sesión
|--------------------------------------------------------------------------
|
| Ruta:
|   sistema_mantenimiento/funciones/logout.php
|
| Compatible con PHP 7.4 o superior.
| Registra el movimiento cuando sea posible y siempre cierra la sesión,
| aunque exista un problema temporal con la base de datos.
|
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

/*
|--------------------------------------------------------------------------
| Evitar que el navegador conserve esta respuesta
|--------------------------------------------------------------------------
*/

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

/*
|--------------------------------------------------------------------------
| Métodos permitidos
|--------------------------------------------------------------------------
|
| Se permite GET porque el topbar actual utiliza un enlace confirmado con
| SweetAlert2. También se admite POST para una futura implementación.
|
*/

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (!in_array($metodo, ['GET', 'POST'], true)) {
    if (!headers_sent()) {
        header('Allow: GET, POST');
    }

    http_response_code(405);
    exit;
}

/*
|--------------------------------------------------------------------------
| Rechazar solicitudes identificadas explícitamente como externas
|--------------------------------------------------------------------------
|
| El encabezado Sec-Fetch-Site puede no existir en navegadores antiguos.
| Solo se bloquea cuando el navegador indica expresamente "cross-site".
|
*/

$origenSolicitud = strtolower(
    trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''))
);

if ($origenSolicitud === 'cross-site') {
    http_response_code(403);
    exit;
}

/*
|--------------------------------------------------------------------------
| Validar CSRF cuando se utilice POST
|--------------------------------------------------------------------------
*/

if ($metodo === 'POST') {
    $tokenSesion = isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        ? $_SESSION['csrf_token']
        : '';

    $tokenRecibido = isset($_POST['csrf_token'])
        && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : '';

    if (
        $tokenSesion === ''
        || $tokenRecibido === ''
        || !hash_equals($tokenSesion, $tokenRecibido)
    ) {
        http_response_code(419);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Guardar los datos necesarios antes de destruir la sesión
|--------------------------------------------------------------------------
*/

$usuarioId = filter_var(
    $_SESSION['usuario_id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
        ],
    ]
);

$tipoUsuario = strtoupper(
    trim((string) ($_SESSION['tipo_usuario'] ?? ''))
);

$usuario = trim(
    (string) ($_SESSION['usuario'] ?? '')
);

$tablasPorRol = [
    'ADMIN' => 'administradores',
    'SOLICITANTE' => 'solicitantes',
    'TECNICO' => 'tecnicos',
];

$sesionValidaParaAuditoria = (
    $usuarioId !== false
    && isset($tablasPorRol[$tipoUsuario])
);

/*
|--------------------------------------------------------------------------
| Registrar el cierre de sesión
|--------------------------------------------------------------------------
|
| Si la base de datos no está disponible, el error se registra internamente,
| pero la sesión se cierra de todas formas.
|
*/

if (
    $sesionValidaParaAuditoria
    && isset($conexion)
    && $conexion instanceof PDO
) {
    try {
        $tablaUsuario = $tablasPorRol[$tipoUsuario];

        if ($usuario === '') {
            $usuario = 'usuario_' . (string) $usuarioId;
        }

        $usuario = substr($usuario, 0, 60);

        $descripcion = sprintf(
            'Cierre de sesión del usuario %s (%s).',
            $usuario,
            $tipoUsuario
        );

        $ip = trim(
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );

        $userAgent = trim(
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
        );

        $ip = $ip !== ''
            ? substr($ip, 0, 60)
            : null;

        $userAgent = $userAgent !== ''
            ? substr($userAgent, 0, 255)
            : null;

        $sql = '
            INSERT INTO movimientos_sistema
            (
                tipo_usuario,
                usuario_id,
                accion,
                modulo,
                descripcion,
                tabla_afectada,
                registro_id,
                ip_address,
                user_agent,
                fecha_movimiento
            )
            VALUES
            (
                :tipo_usuario,
                :usuario_id,
                :accion,
                :modulo,
                :descripcion,
                :tabla_afectada,
                :registro_id,
                :ip_address,
                :user_agent,
                NOW()
            )
        ';

        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            ':tipo_usuario' => $tipoUsuario,
            ':usuario_id' => (int) $usuarioId,
            ':accion' => 'LOGOUT',
            ':modulo' => 'Login',
            ':descripcion' => $descripcion,
            ':tabla_afectada' => $tablaUsuario,
            ':registro_id' => (int) $usuarioId,
            ':ip_address' => $ip,
            ':user_agent' => $userAgent,
        ]);
    } catch (Throwable $e) {
        error_log(
            '[LOGOUT] No fue posible registrar el cierre de sesión: '
            . $e->getMessage()
        );
    }
}

/*
|--------------------------------------------------------------------------
| Destruir completamente la sesión y su cookie
|--------------------------------------------------------------------------
*/

sm_destruir_sesion();

/*
|--------------------------------------------------------------------------
| Redirigir al inicio de sesión
|--------------------------------------------------------------------------
*/

if (!headers_sent()) {
    header(
        'Location: ../login.php?logout=1',
        true,
        303
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Respaldo en caso de que los encabezados ya hayan sido enviados
|--------------------------------------------------------------------------
*/

echo '<!DOCTYPE html>';
echo '<html lang="es">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<meta http-equiv="refresh" content="0;url=../login.php?logout=1">';
echo '<title>Sesión cerrada</title>';
echo '</head>';
echo '<body>';
echo '<p>La sesión se cerró correctamente. ';
echo '<a href="../login.php?logout=1">Volver al inicio de sesión</a>.</p>';
echo '</body>';
echo '</html>';
exit;