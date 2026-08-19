<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_metodo('POST');
sm_validar_csrf('csrf_login');

if (!($conexion instanceof PDO)) {
    sm_responder_json(
        false,
        'No fue posible conectar con el sistema. Inténtalo nuevamente.',
        [],
        500
    );
}

$estadoIntentos = login_estado_intentos();

if ($estadoIntentos['bloqueado']) {
    sm_responder_json(
        false,
        'Se realizaron varios intentos incorrectos. Espera un momento antes de volver a intentarlo.',
        [
            'espera_segundos' => $estadoIntentos['espera_segundos'],
        ],
        429
    );
}

$usuario = sm_limpiar_texto($_POST['usuario'] ?? '');
$password = isset($_POST['password']) && is_string($_POST['password'])
    ? $_POST['password']
    : '';

if ($usuario === '') {
    sm_responder_json(
        false,
        'Ingresa tu usuario.',
        ['campo' => 'usuario'],
        422
    );
}

if (strlen($usuario) > 60 || !preg_match('/^[A-Za-z0-9._-]+$/', $usuario)) {
    sm_responder_json(
        false,
        'El nombre de usuario no es válido.',
        ['campo' => 'usuario'],
        422
    );
}

if ($password === '') {
    sm_responder_json(
        false,
        'Ingresa tu contraseña.',
        ['campo' => 'password'],
        422
    );
}

if (strlen($password) > 255) {
    sm_responder_json(
        false,
        'La contraseña no es válida.',
        ['campo' => 'password'],
        422
    );
}

try {
    $configuraciones = login_configuraciones_usuario();
    $candidatos = [];

    foreach ($configuraciones as $configuracion) {
        $candidato = login_buscar_usuario(
            $conexion,
            $configuracion,
            $usuario
        );

        if ($candidato !== false) {
            $candidato['_configuracion'] = $configuracion;
            $candidatos[] = $candidato;
        }
    }

    $usuarioAutenticado = null;
    $coincidenciaInactiva = false;

    if ($candidatos === []) {
        password_verify($password, login_hash_ficticio());
    } else {
        foreach ($candidatos as $candidato) {
            $hash = isset($candidato['password_hash'])
                ? (string) $candidato['password_hash']
                : login_hash_ficticio();

            if (!password_verify($password, $hash)) {
                continue;
            }

            if ((int) ($candidato['activo'] ?? 0) !== 1) {
                $coincidenciaInactiva = true;
                continue;
            }

            if (
                isset($candidato['departamento_activo'])
                && $candidato['departamento_id'] !== null
                && (int) $candidato['departamento_activo'] !== 1
            ) {
                $coincidenciaInactiva = true;
                continue;
            }

            $usuarioAutenticado = $candidato;
            break;
        }
    }

    if ($usuarioAutenticado === null) {
        if ($coincidenciaInactiva) {
            login_registrar_fallo();

            sm_responder_json(
                false,
                'La cuenta no está disponible. Contacta al administrador del sistema.',
                [],
                403
            );
        }

        $resultadoFallo = login_registrar_fallo();

        if ($resultadoFallo['bloqueado']) {
            sm_responder_json(
                false,
                'Se realizaron varios intentos incorrectos. Espera un momento antes de volver a intentarlo.',
                [
                    'espera_segundos' => $resultadoFallo['espera_segundos'],
                ],
                429
            );
        }

        sm_responder_json(
            false,
            'Usuario o contraseña incorrectos.',
            [
                'intentos_restantes' => $resultadoFallo['intentos_restantes'],
            ],
            401
        );
    }

    $configuracion = $usuarioAutenticado['_configuracion'];
    $usuarioId = (int) $usuarioAutenticado['id'];
    $tablaUsuario = (string) $configuracion['tabla'];
    $tipoUsuario = (string) $configuracion['tipo_usuario'];
    $redirect = (string) $configuracion['redirect'];

    $nombreCompleto = login_nombre_completo($usuarioAutenticado);

    $conexion->beginTransaction();

    login_actualizar_ultimo_acceso(
        $conexion,
        $tablaUsuario,
        $usuarioId
    );

    if (
        isset($usuarioAutenticado['password_hash'])
        && password_needs_rehash(
            (string) $usuarioAutenticado['password_hash'],
            PASSWORD_DEFAULT
        )
    ) {
        login_actualizar_hash(
            $conexion,
            $tablaUsuario,
            $usuarioId,
            password_hash($password, PASSWORD_DEFAULT)
        );
    }

    $conexion->commit();

    session_regenerate_id(true);

    $_SESSION['usuario_id'] = $usuarioId;
    $_SESSION['usuario'] = (string) $usuarioAutenticado['usuario'];
    $_SESSION['nombre'] = (string) ($usuarioAutenticado['nombre'] ?? '');
    $_SESSION['apellido_paterno'] = (string) ($usuarioAutenticado['apellido_paterno'] ?? '');
    $_SESSION['apellido_materno'] = (string) ($usuarioAutenticado['apellido_materno'] ?? '');
    $_SESSION['nombre_completo'] = $nombreCompleto;
    $_SESSION['telefono'] = (string) ($usuarioAutenticado['telefono'] ?? '');
    $_SESSION['correo'] = (string) ($usuarioAutenticado['correo'] ?? '');
    $_SESSION['tipo_usuario'] = $tipoUsuario;
    $_SESSION['rol_codigo'] = $tipoUsuario;
    $_SESSION['rol_nombre'] = (string) $configuracion['rol_nombre'];
    $_SESSION['tabla_usuario'] = $tablaUsuario;
    $_SESSION['ultima_actividad'] = time();
    $_SESSION['sesion_regenerada_en'] = time();

    if (isset($usuarioAutenticado['departamento_id'])) {
        $_SESSION['departamento_id'] = $usuarioAutenticado['departamento_id'] !== null
            ? (int) $usuarioAutenticado['departamento_id']
            : null;
    }

    if (isset($usuarioAutenticado['turno'])) {
        $_SESSION['turno'] = (string) $usuarioAutenticado['turno'];
    }

    if (isset($usuarioAutenticado['especialidad'])) {
        $_SESSION['especialidad'] = (string) $usuarioAutenticado['especialidad'];
    }

    login_limpiar_intentos();
    unset($_SESSION['csrf_login']);

    login_registrar_movimiento(
        $conexion,
        $usuarioId,
        $tipoUsuario,
        $tablaUsuario,
        (string) $usuarioAutenticado['usuario'],
        $nombreCompleto
    );

    sm_responder_json(
        true,
        'Inicio de sesión exitoso. Preparando tu panel...',
        [
            'redirect' => $redirect,
        ]
    );

} catch (PDOException $e) {
    if ($conexion instanceof PDO && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[LOGIN PDO] ' . $e->getMessage());

    sm_responder_json(
        false,
        'No fue posible conectar con el sistema. Inténtalo nuevamente.',
        [],
        500
    );

} catch (Throwable $e) {
    if ($conexion instanceof PDO && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[LOGIN GENERAL] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error interno al iniciar sesión.',
        [],
        500
    );
}

function login_configuraciones_usuario(): array
{
    return [
        [
            'tabla' => 'administradores',
            'tipo_usuario' => 'ADMIN',
            'rol_nombre' => 'Administrador',
            'redirect' => 'JS/dashboard_admin.php',
            'usa_departamento' => false,
        ],
        [
            'tabla' => 'solicitantes',
            'tipo_usuario' => 'SOLICITANTE',
            'rol_nombre' => 'Solicitante',
            'redirect' => 'JS/dashboard_solicitante.php',
            'usa_departamento' => true,
        ],
        [
            'tabla' => 'tecnicos',
            'tipo_usuario' => 'TECNICO',
            'rol_nombre' => 'Técnico',
            'redirect' => 'JS/dashboard_tecnico.php',
            'usa_departamento' => true,
        ],
    ];
}

function login_buscar_usuario(PDO $conexion, array $configuracion, string $usuario)
{
    $tabla = (string) $configuracion['tabla'];
    $usaDepartamento = !empty($configuracion['usa_departamento']);

    $camposExtra = '';
    $joinDepartamento = '';

    if ($usaDepartamento) {
        $camposExtra = ', u.departamento_id, d.activo AS departamento_activo';
        $joinDepartamento = ' LEFT JOIN departamentos d ON d.id = u.departamento_id ';
    }

    if ($tabla === 'tecnicos') {
        $camposExtra .= ', u.turno, u.especialidad';
    }

    $sql = "
        SELECT
            u.id,
            u.usuario,
            u.password_hash,
            u.nombre,
            u.apellido_paterno,
            u.apellido_materno,
            u.telefono,
            u.correo,
            u.activo,
            u.ultimo_acceso
            {$camposExtra}
        FROM {$tabla} u
        {$joinDepartamento}
        WHERE u.usuario = :usuario
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function login_actualizar_ultimo_acceso(
    PDO $conexion,
    string $tabla,
    int $usuarioId
): void {
    $tablasPermitidas = ['administradores', 'solicitantes', 'tecnicos'];

    if (!in_array($tabla, $tablasPermitidas, true)) {
        throw new RuntimeException('Tabla de usuario no permitida.');
    }

    $stmt = $conexion->prepare(
        "UPDATE {$tabla} SET ultimo_acceso = NOW() WHERE id = :id AND activo = 1"
    );
    $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('La cuenta cambió de estado durante el acceso.');
    }
}

function login_actualizar_hash(
    PDO $conexion,
    string $tabla,
    int $usuarioId,
    string $nuevoHash
): void {
    $tablasPermitidas = ['administradores', 'solicitantes', 'tecnicos'];

    if (!in_array($tabla, $tablasPermitidas, true)) {
        return;
    }

    $stmt = $conexion->prepare(
        "UPDATE {$tabla} SET password_hash = :password_hash WHERE id = :id"
    );
    $stmt->bindValue(':password_hash', $nuevoHash, PDO::PARAM_STR);
    $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
    $stmt->execute();
}

function login_nombre_completo(array $datos): string
{
    $partes = [
        trim((string) ($datos['nombre'] ?? '')),
        trim((string) ($datos['apellido_paterno'] ?? '')),
        trim((string) ($datos['apellido_materno'] ?? '')),
    ];

    $partes = array_filter($partes, function ($valor) {
        return $valor !== '';
    });

    $nombre = trim(implode(' ', $partes));

    return $nombre !== '' ? $nombre : (string) ($datos['usuario'] ?? 'Usuario');
}

function login_hash_ficticio(): string
{
    return '$2y$10$SkM72wA0HD72nPCvNdfQleQsGtoZk4lD0Aly2nEiPKvzceBQ12nza';
}

function login_estado_intentos(): array
{
    $ahora = time();
    $datos = isset($_SESSION['login_intentos']) && is_array($_SESSION['login_intentos'])
        ? $_SESSION['login_intentos']
        : [];

    $bloqueadoHasta = isset($datos['bloqueado_hasta'])
        ? (int) $datos['bloqueado_hasta']
        : 0;

    if ($bloqueadoHasta > $ahora) {
        return [
            'bloqueado' => true,
            'espera_segundos' => $bloqueadoHasta - $ahora,
        ];
    }

    if ($bloqueadoHasta > 0 && $bloqueadoHasta <= $ahora) {
        login_limpiar_intentos();
    }

    return [
        'bloqueado' => false,
        'espera_segundos' => 0,
    ];
}

function login_registrar_fallo(): array
{
    $ahora = time();
    $maxIntentos = 5;
    $ventana = 900;
    $bloqueo = 300;

    $datos = isset($_SESSION['login_intentos']) && is_array($_SESSION['login_intentos'])
        ? $_SESSION['login_intentos']
        : [];

    $primerIntento = isset($datos['primer_intento'])
        ? (int) $datos['primer_intento']
        : $ahora;

    $intentos = isset($datos['intentos'])
        ? (int) $datos['intentos']
        : 0;

    if (($ahora - $primerIntento) > $ventana) {
        $primerIntento = $ahora;
        $intentos = 0;
    }

    $intentos++;

    $nuevoEstado = [
        'primer_intento' => $primerIntento,
        'intentos' => $intentos,
        'bloqueado_hasta' => 0,
    ];

    if ($intentos >= $maxIntentos) {
        $nuevoEstado['bloqueado_hasta'] = $ahora + $bloqueo;
    }

    $_SESSION['login_intentos'] = $nuevoEstado;

    return [
        'bloqueado' => $nuevoEstado['bloqueado_hasta'] > $ahora,
        'espera_segundos' => max(0, $nuevoEstado['bloqueado_hasta'] - $ahora),
        'intentos_restantes' => max(0, $maxIntentos - $intentos),
    ];
}

function login_limpiar_intentos(): void
{
    unset($_SESSION['login_intentos']);
}

function login_registrar_movimiento(
    PDO $conexion,
    int $usuarioId,
    string $tipoUsuario,
    string $tablaUsuario,
    string $usuario,
    string $nombreCompleto
): void {
    try {
        $descripcion = sprintf(
            'Inicio de sesión de %s, usuario %s (%s).',
            $nombreCompleto,
            $usuario,
            $tipoUsuario
        );

        $sql = "
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
                'LOGIN',
                'Login',
                :descripcion,
                :tabla_afectada,
                :registro_id,
                :ip_address,
                :user_agent,
                NOW()
            )
        ";

        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':tipo_usuario', $tipoUsuario, PDO::PARAM_STR);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt->bindValue(':tabla_afectada', $tablaUsuario, PDO::PARAM_STR);
        $stmt->bindValue(':registro_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':ip_address', sm_ip_cliente(), PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', login_user_agent(), PDO::PARAM_STR);
        $stmt->execute();

    } catch (Throwable $e) {
        error_log('[MOVIMIENTO LOGIN] ' . $e->getMessage());
    }
}

function login_user_agent()
{
    $userAgent = sm_limpiar_texto($_SERVER['HTTP_USER_AGENT'] ?? '');

    if ($userAgent === '') {
        return null;
    }

    return substr($userAgent, 0, 255);
}