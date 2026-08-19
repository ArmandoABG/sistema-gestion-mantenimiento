<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Administradores - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| Gestión segura de cuentas administrativas.
| - Creación y edición de perfil.
| - Baja lógica y reactivación.
| - Restablecimiento de contraseña con verificación del administrador actor.
| - Usuario y correo únicos entre los tres tipos de usuario.
| - Protección de la cuenta actual y del último administrador activo.
| - Registro en movimientos_sistema y auditoria_ediciones sin exponer secretos.
| Compatible con PHP 7.4 o superior.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_sesion(['ADMIN'], true);

if (!isset($conexion) || !($conexion instanceof PDO)) {
    sm_responder_json(false, 'No fue posible conectar con la base de datos.', [], 503);
}

try {
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[ADMINISTRADORES][PDO CONFIG] ' . $e->getMessage());
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = strtoupper(adm_texto(
    $metodo === 'GET'
        ? ($_GET['accion'] ?? 'LISTAR')
        : ($_POST['accion'] ?? '')
));

try {
    $adminActualId = adm_admin_id();
    adm_validar_admin_activo($conexion, $adminActualId);

    if ($metodo === 'GET') {
        sm_requerir_metodo('GET');

        if ($accion === 'INICIAL' || $accion === 'LISTAR') {
            adm_endpoint_listar($conexion, $adminActualId);
        }

        if ($accion === 'DETALLE') {
            adm_endpoint_detalle($conexion, $adminActualId);
        }

        sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
    }

    sm_requerir_metodo('POST');
    sm_validar_csrf();

    if ($accion === 'GUARDAR') {
        adm_endpoint_guardar($conexion, $adminActualId);
    }

    if ($accion === 'CAMBIAR_ESTADO') {
        adm_endpoint_cambiar_estado($conexion, $adminActualId);
    }

    if ($accion === 'CAMBIAR_PASSWORD') {
        adm_endpoint_cambiar_password($conexion, $adminActualId);
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'ADM-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][ADMINISTRADORES][PDO] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    if ((string) $e->getCode() === '23000') {
        sm_responder_json(
            false,
            'El usuario o el correo ya se encuentran registrados.',
            ['referencia' => $referencia],
            409
        );
    }

    sm_responder_json(
        false,
        'No fue posible procesar la cuenta administrativa.',
        ['referencia' => $referencia],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'ADM-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][ADMINISTRADORES] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la cuenta administrativa.',
        ['referencia' => $referencia],
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function adm_endpoint_listar(PDO $conexion, int $adminActualId): void
{
    $sql = "SELECT
                a.id,
                a.usuario,
                a.nombre,
                a.apellido_paterno,
                a.apellido_materno,
                a.telefono,
                a.correo,
                a.activo,
                a.ultimo_acceso,
                a.fecha_registro,
                DATE_FORMAT(a.ultimo_acceso, '%d/%m/%Y %H:%i') AS ultimo_acceso_texto,
                DATE_FORMAT(a.fecha_registro, '%d/%m/%Y') AS fecha_registro_texto
            FROM administradores a
            ORDER BY a.activo DESC, a.nombre ASC, a.apellido_paterno ASC, a.id ASC";

    $administradores = $conexion->query($sql)->fetchAll();
    $activos = 0;
    $inactivos = 0;
    $sinAcceso = 0;

    foreach ($administradores as &$administrador) {
        $administrador['id'] = (int) $administrador['id'];
        $administrador['activo'] = (int) $administrador['activo'];
        $administrador['es_actual'] = (int) $administrador['id'] === $adminActualId;
        $administrador['nombre_completo'] = adm_nombre_completo($administrador);
        $administrador['ultimo_acceso_texto'] = $administrador['ultimo_acceso'] === null
            ? 'Nunca ha ingresado'
            : (string) $administrador['ultimo_acceso_texto'];
        $administrador['puede_desactivar'] = $administrador['activo'] === 1
            && !$administrador['es_actual'];

        if ($administrador['activo'] === 1) {
            $activos++;
        } else {
            $inactivos++;
        }

        if ($administrador['ultimo_acceso'] === null) {
            $sinAcceso++;
        }
    }
    unset($administrador);

    sm_responder_json(
        true,
        'Administradores cargados correctamente.',
        [
            'administradores' => $administradores,
            'resumen' => [
                'total' => count($administradores),
                'activos' => $activos,
                'inactivos' => $inactivos,
                'sin_acceso' => $sinAcceso,
            ],
            'admin_actual_id' => $adminActualId,
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function adm_endpoint_detalle(PDO $conexion, int $adminActualId): void
{
    $id = adm_entero_positivo($_GET['id'] ?? null, 'administrador');

    $stmt = $conexion->prepare(
        "SELECT
            id, usuario, nombre, apellido_paterno, apellido_materno,
            telefono, correo, activo, ultimo_acceso, fecha_registro
         FROM administradores
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $administrador = $stmt->fetch();

    if (!$administrador) {
        sm_responder_json(false, 'No se encontró el administrador seleccionado.', [], 404);
    }

    $administrador['id'] = (int) $administrador['id'];
    $administrador['activo'] = (int) $administrador['activo'];
    $administrador['es_actual'] = (int) $administrador['id'] === $adminActualId;
    $administrador['nombre_completo'] = adm_nombre_completo($administrador);

    sm_responder_json(
        true,
        'Administrador encontrado.',
        ['administrador' => $administrador]
    );
}

function adm_endpoint_guardar(PDO $conexion, int $adminActualId): void
{
    $idTexto = adm_texto($_POST['administrador_id'] ?? '');
    $id = $idTexto === '' ? 0 : adm_entero_positivo($idTexto, 'administrador');

    $usuario = adm_validar_usuario($_POST['usuario'] ?? '');
    $nombre = adm_validar_nombre($_POST['nombre'] ?? '', 'nombre', 'El nombre', true);
    $apellidoPaterno = adm_validar_nombre(
        $_POST['apellido_paterno'] ?? '',
        'apellido_paterno',
        'El apellido paterno',
        false
    );
    $apellidoMaterno = adm_validar_nombre(
        $_POST['apellido_materno'] ?? '',
        'apellido_materno',
        'El apellido materno',
        false
    );
    $telefono = adm_validar_telefono($_POST['telefono'] ?? '');
    $correo = adm_validar_correo($_POST['correo'] ?? '');

    $password = (string) ($_POST['password'] ?? '');
    $confirmarPassword = (string) ($_POST['confirmar_password'] ?? '');

    if ($id === 0) {
        adm_validar_password($password, $confirmarPassword, 'password', 'confirmar_password');
    }

    $conexion->beginTransaction();

    $anterior = null;
    if ($id > 0) {
        $anterior = adm_bloquear_administrador($conexion, $id, true);
        if (!$anterior) {
            adm_cancelar($conexion, 'El administrador ya no existe.', 404);
        }
    }

    if (adm_usuario_en_uso($conexion, $usuario, $id)) {
        adm_cancelar(
            $conexion,
            'El nombre de usuario ya está registrado por otra cuenta del sistema.',
            409,
            ['campo' => 'usuario']
        );
    }

    if ($correo !== null && adm_correo_en_uso($conexion, $correo, $id)) {
        adm_cancelar(
            $conexion,
            'El correo electrónico ya está registrado por otra cuenta del sistema.',
            409,
            ['campo' => 'correo']
        );
    }

    if ($id === 0) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('No fue posible proteger la contraseña.');
        }

        $stmt = $conexion->prepare(
            "INSERT INTO administradores
            (
                usuario, password_hash, nombre, apellido_paterno,
                apellido_materno, telefono, correo, activo, fecha_registro
            )
            VALUES
            (
                :usuario, :password_hash, :nombre, :apellido_paterno,
                :apellido_materno, :telefono, :correo, 1, NOW()
            )"
        );
        $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $hash, PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        adm_bind_nullable($stmt, ':apellido_paterno', $apellidoPaterno);
        adm_bind_nullable($stmt, ':apellido_materno', $apellidoMaterno);
        adm_bind_nullable($stmt, ':telefono', $telefono);
        adm_bind_nullable($stmt, ':correo', $correo);
        $stmt->execute();

        $id = (int) $conexion->lastInsertId();
        $nuevo = [
            'id' => $id,
            'usuario' => $usuario,
            'nombre' => $nombre,
            'apellido_paterno' => $apellidoPaterno,
            'apellido_materno' => $apellidoMaterno,
            'telefono' => $telefono,
            'correo' => $correo,
            'activo' => 1,
        ];

        adm_auditar(
            $conexion,
            $adminActualId,
            $id,
            'INSERT',
            'Alta de una cuenta administrativa.',
            null,
            $nuevo
        );
        adm_movimiento(
            $conexion,
            $adminActualId,
            'CREAR_ADMINISTRADOR',
            'Se registró la cuenta administrativa ' . $usuario . '.',
            $id
        );

        $conexion->commit();

        sm_responder_json(
            true,
            'Administrador registrado correctamente.',
            ['administrador_id' => $id],
            201
        );
    }

    $nuevo = [
        'id' => $id,
        'usuario' => $usuario,
        'nombre' => $nombre,
        'apellido_paterno' => $apellidoPaterno,
        'apellido_materno' => $apellidoMaterno,
        'telefono' => $telefono,
        'correo' => $correo,
        'activo' => (int) $anterior['activo'],
    ];
    $anteriorSeguro = adm_datos_seguros($anterior);

    if (adm_datos_iguales($anteriorSeguro, $nuevo)) {
        $conexion->commit();
        sm_responder_json(
            true,
            'No se detectaron cambios en la cuenta.',
            ['sin_cambios' => true, 'administrador_id' => $id]
        );
    }

    $stmt = $conexion->prepare(
        "UPDATE administradores
         SET usuario = :usuario,
             nombre = :nombre,
             apellido_paterno = :apellido_paterno,
             apellido_materno = :apellido_materno,
             telefono = :telefono,
             correo = :correo
         WHERE id = :id"
    );
    $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    adm_bind_nullable($stmt, ':apellido_paterno', $apellidoPaterno);
    adm_bind_nullable($stmt, ':apellido_materno', $apellidoMaterno);
    adm_bind_nullable($stmt, ':telefono', $telefono);
    adm_bind_nullable($stmt, ':correo', $correo);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $actualizoSesion = $id === $adminActualId;
    if ($actualizoSesion) {
        adm_actualizar_sesion($usuario, $nombre, $apellidoPaterno, $apellidoMaterno, $telefono, $correo);
    }

    adm_auditar(
        $conexion,
        $adminActualId,
        $id,
        'UPDATE',
        'Actualización del perfil administrativo.',
        $anteriorSeguro,
        $nuevo
    );
    adm_movimiento(
        $conexion,
        $adminActualId,
        'EDITAR_ADMINISTRADOR',
        'Se actualizó la cuenta administrativa ' . (string) $anterior['usuario']
            . ($usuario !== (string) $anterior['usuario'] ? '; nuevo usuario: ' . $usuario : '') . '.',
        $id
    );

    $conexion->commit();

    sm_responder_json(
        true,
        'Administrador actualizado correctamente.',
        [
            'administrador_id' => $id,
            'actualizo_sesion' => $actualizoSesion,
        ]
    );
}

function adm_endpoint_cambiar_estado(PDO $conexion, int $adminActualId): void
{
    $id = adm_entero_positivo($_POST['administrador_id'] ?? null, 'administrador');
    $activo = adm_validar_estado($_POST['activo'] ?? null);

    $conexion->beginTransaction();
    $administrador = adm_bloquear_administrador($conexion, $id, true);

    if (!$administrador) {
        adm_cancelar($conexion, 'No se encontró el administrador seleccionado.', 404);
    }

    $estadoActual = (int) $administrador['activo'];
    if ($estadoActual === $activo) {
        $conexion->commit();
        sm_responder_json(
            true,
            $activo === 1
                ? 'La cuenta ya se encuentra activa.'
                : 'La cuenta ya se encuentra inactiva.',
            ['sin_cambios' => true]
        );
    }

    if ($activo === 0 && $id === $adminActualId) {
        adm_cancelar(
            $conexion,
            'No puedes desactivar la cuenta con la que tienes iniciada la sesión.',
            409
        );
    }

    if ($activo === 0) {
        $stmt = $conexion->query(
            "SELECT id
             FROM administradores
             WHERE activo = 1
             ORDER BY id
             FOR UPDATE"
        );
        $activos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($activos) <= 1) {
            adm_cancelar(
                $conexion,
                'No puedes desactivar al último administrador activo del sistema.',
                409
            );
        }
    } else {
        $usuario = (string) $administrador['usuario'];
        $correo = adm_nullable($administrador['correo'] ?? null);

        if (adm_usuario_en_uso($conexion, $usuario, $id)) {
            adm_cancelar(
                $conexion,
                'La cuenta no puede reactivarse porque su usuario ya está ocupado por otra persona.',
                409
            );
        }
        if ($correo !== null && adm_correo_en_uso($conexion, $correo, $id)) {
            adm_cancelar(
                $conexion,
                'La cuenta no puede reactivarse porque su correo ya está ocupado por otra persona.',
                409
            );
        }
    }

    $stmt = $conexion->prepare(
        'UPDATE administradores SET activo = :activo WHERE id = :id'
    );
    $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $anterior = adm_datos_seguros($administrador);
    $nuevo = $anterior;
    $nuevo['activo'] = $activo;
    $reactivar = $activo === 1;

    adm_auditar(
        $conexion,
        $adminActualId,
        $id,
        $reactivar ? 'REACTIVACION' : 'DELETE_LOGICO',
        $reactivar
            ? 'Reactivación de una cuenta administrativa.'
            : 'Desactivación lógica de una cuenta administrativa.',
        $anterior,
        $nuevo
    );
    adm_movimiento(
        $conexion,
        $adminActualId,
        $reactivar ? 'REACTIVAR_ADMINISTRADOR' : 'DESACTIVAR_ADMINISTRADOR',
        ($reactivar ? 'Se reactivó' : 'Se desactivó')
            . ' la cuenta administrativa ' . (string) $administrador['usuario'] . '.',
        $id
    );

    $conexion->commit();

    sm_responder_json(
        true,
        $reactivar
            ? 'Administrador reactivado correctamente.'
            : 'Administrador desactivado correctamente.'
    );
}

function adm_endpoint_cambiar_password(PDO $conexion, int $adminActualId): void
{
    $id = adm_entero_positivo($_POST['administrador_id'] ?? null, 'administrador');
    $passwordActor = (string) ($_POST['password_actual_actor'] ?? '');
    $nuevaPassword = (string) ($_POST['nueva_password'] ?? '');
    $confirmacion = (string) ($_POST['confirmar_nueva_password'] ?? '');

    if ($passwordActor === '') {
        sm_responder_json(
            false,
            'Escribe tu contraseña administrativa para autorizar el cambio.',
            ['campo' => 'password_actual_actor'],
            422
        );
    }
    adm_validar_password(
        $nuevaPassword,
        $confirmacion,
        'nueva_password',
        'confirmar_nueva_password'
    );

    $conexion->beginTransaction();

    $actor = adm_bloquear_administrador($conexion, $adminActualId, true);
    if (!$actor || (int) $actor['activo'] !== 1) {
        adm_cancelar($conexion, 'Tu cuenta administrativa ya no está activa.', 401);
    }

    if (!password_verify($passwordActor, (string) $actor['password_hash'])) {
        adm_cancelar(
            $conexion,
            'Tu contraseña administrativa no es correcta.',
            422,
            ['campo' => 'password_actual_actor']
        );
    }

    $destino = $id === $adminActualId
        ? $actor
        : adm_bloquear_administrador($conexion, $id, true);

    if (!$destino) {
        adm_cancelar($conexion, 'No se encontró el administrador seleccionado.', 404);
    }

    if (password_verify($nuevaPassword, (string) $destino['password_hash'])) {
        adm_cancelar(
            $conexion,
            'La nueva contraseña debe ser diferente de la contraseña actual de esa cuenta.',
            422,
            ['campo' => 'nueva_password']
        );
    }

    $hash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('No fue posible proteger la nueva contraseña.');
    }

    $stmt = $conexion->prepare(
        'UPDATE administradores SET password_hash = :password_hash WHERE id = :id'
    );
    $stmt->bindValue(':password_hash', $hash, PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    /* La auditoría nunca guarda contraseñas ni hashes. */
    adm_auditar(
        $conexion,
        $adminActualId,
        $id,
        'UPDATE',
        $id === $adminActualId
            ? 'El administrador actualizó su propia contraseña.'
            : 'Un administrador restableció la contraseña de otra cuenta.',
        ['credencial' => 'PROTEGIDA', 'actualizada' => false],
        ['credencial' => 'PROTEGIDA', 'actualizada' => true]
    );
    adm_movimiento(
        $conexion,
        $adminActualId,
        'CAMBIAR_PASSWORD_ADMINISTRADOR',
        'Se actualizó la contraseña de la cuenta administrativa '
            . (string) $destino['usuario'] . '.',
        $id
    );

    $conexion->commit();

    if ($id === $adminActualId && function_exists('session_regenerate_id')) {
        @session_regenerate_id(true);
    }

    sm_responder_json(
        true,
        $id === $adminActualId
            ? 'Tu contraseña fue actualizada correctamente.'
            : 'La contraseña del administrador fue restablecida correctamente.',
        ['es_cuenta_actual' => $id === $adminActualId]
    );
}

/* =========================================================================
   CONSULTAS Y SEGURIDAD
   ========================================================================= */

function adm_bloquear_administrador(PDO $conexion, int $id, bool $conPassword)
{
    $campos = $conPassword
        ? 'id, usuario, password_hash, nombre, apellido_paterno, apellido_materno, telefono, correo, activo, ultimo_acceso, fecha_registro'
        : 'id, usuario, nombre, apellido_paterno, apellido_materno, telefono, correo, activo, ultimo_acceso, fecha_registro';

    $stmt = $conexion->prepare(
        'SELECT ' . $campos . '
         FROM administradores
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch();
}

function adm_usuario_en_uso(PDO $conexion, string $usuario, int $adminExcluir): bool
{
    $consultas = [
        ['administradores', $adminExcluir],
        ['solicitantes', 0],
        ['tecnicos', 0],
    ];

    foreach ($consultas as $consulta) {
        $tabla = $consulta[0];
        $excluir = (int) $consulta[1];
        $sql = "SELECT COUNT(*) FROM {$tabla} WHERE LOWER(usuario) = LOWER(:usuario)";
        if ($tabla === 'administradores' && $excluir > 0) {
            $sql .= ' AND id <> :id';
        }
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
        if ($tabla === 'administradores' && $excluir > 0) {
            $stmt->bindValue(':id', $excluir, PDO::PARAM_INT);
        }
        $stmt->execute();
        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
    }

    return false;
}

function adm_correo_en_uso(PDO $conexion, string $correo, int $adminExcluir): bool
{
    $consultas = [
        ['administradores', $adminExcluir],
        ['solicitantes', 0],
        ['tecnicos', 0],
    ];

    foreach ($consultas as $consulta) {
        $tabla = $consulta[0];
        $excluir = (int) $consulta[1];
        $sql = "SELECT COUNT(*) FROM {$tabla} WHERE correo IS NOT NULL AND LOWER(correo) = LOWER(:correo)";
        if ($tabla === 'administradores' && $excluir > 0) {
            $sql .= ' AND id <> :id';
        }
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);
        if ($tabla === 'administradores' && $excluir > 0) {
            $stmt->bindValue(':id', $excluir, PDO::PARAM_INT);
        }
        $stmt->execute();
        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
    }

    return false;
}

function adm_validar_admin_activo(PDO $conexion, int $adminId): void
{
    $stmt = $conexion->prepare(
        'SELECT COUNT(*) FROM administradores WHERE id = :id AND activo = 1'
    );
    $stmt->bindValue(':id', $adminId, PDO::PARAM_INT);
    $stmt->execute();

    if ((int) $stmt->fetchColumn() !== 1) {
        sm_responder_json(
            false,
            'Tu cuenta administrativa ya no está activa.',
            ['sesion_expirada' => true, 'redirect' => '../login.php?sesion=expirada'],
            401
        );
    }
}

/* =========================================================================
   AUDITORÍA Y MOVIMIENTOS
   ========================================================================= */

function adm_auditar(
    PDO $conexion,
    int $actorId,
    int $registroId,
    string $accion,
    string $motivo,
    ?array $anteriores,
    ?array $nuevos
): void {
    $stmt = $conexion->prepare(
        "INSERT INTO auditoria_ediciones
        (
            tabla_afectada, registro_id, solicitud_id, actor_tipo,
            actor_id, accion, motivo, datos_anteriores, datos_nuevos,
            ip_address, user_agent, fecha_evento
        )
        VALUES
        (
            'administradores', :registro_id, NULL, 'ADMIN',
            :actor_id, :accion, :motivo, :anteriores, :nuevos,
            :ip_address, :user_agent, NOW()
        )"
    );
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    $stmt->bindValue(':actor_id', $actorId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':motivo', adm_recortar($motivo, 500), PDO::PARAM_STR);
    adm_bind_nullable($stmt, ':anteriores', adm_json($anteriores));
    adm_bind_nullable($stmt, ':nuevos', adm_json($nuevos));
    adm_bind_nullable($stmt, ':ip_address', adm_ip());
    adm_bind_nullable($stmt, ':user_agent', adm_recortar_nullable(adm_user_agent(), 500));
    $stmt->execute();
}

function adm_movimiento(
    PDO $conexion,
    int $actorId,
    string $accion,
    string $descripcion,
    int $registroId
): void {
    $stmt = $conexion->prepare(
        "INSERT INTO movimientos_sistema
        (
            tipo_usuario, usuario_id, accion, modulo, descripcion,
            tabla_afectada, registro_id, ip_address, user_agent, fecha_movimiento
        )
        VALUES
        (
            'ADMIN', :usuario_id, :accion, 'Administradores', :descripcion,
            'administradores', :registro_id, :ip_address, :user_agent, NOW()
        )"
    );
    $stmt->bindValue(':usuario_id', $actorId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', adm_recortar($accion, 100), PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    adm_bind_nullable($stmt, ':ip_address', adm_recortar_nullable(adm_ip(), 60));
    adm_bind_nullable($stmt, ':user_agent', adm_recortar_nullable(adm_user_agent(), 255));
    $stmt->execute();
}

/* =========================================================================
   VALIDACIONES Y UTILIDADES
   ========================================================================= */

function adm_validar_usuario($valor): string
{
    $usuario = strtolower(adm_texto($valor));
    $longitud = adm_longitud($usuario);

    if ($longitud < 3 || $longitud > 60) {
        sm_responder_json(
            false,
            'El usuario debe contener entre 3 y 60 caracteres.',
            ['campo' => 'usuario'],
            422
        );
    }

    if (!preg_match('/^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$/', $usuario)) {
        sm_responder_json(
            false,
            'Usa letras minúsculas, números, punto, guion o guion bajo; no comiences ni termines con signo.',
            ['campo' => 'usuario'],
            422
        );
    }

    if (preg_match('/[._-]{2,}/', $usuario)) {
        sm_responder_json(
            false,
            'El usuario no puede contener signos consecutivos.',
            ['campo' => 'usuario'],
            422
        );
    }

    return $usuario;
}

function adm_validar_nombre(
    $valor,
    string $campo,
    string $etiqueta,
    bool $obligatorio
): ?string {
    $texto = preg_replace('/\s+/u', ' ', adm_texto($valor)) ?? '';
    $texto = trim($texto);

    if ($texto === '') {
        if ($obligatorio) {
            sm_responder_json(false, $etiqueta . ' es obligatorio.', ['campo' => $campo], 422);
        }
        return null;
    }

    $longitud = adm_longitud($texto);
    $minimo = $obligatorio ? 2 : 1;
    if ($longitud < $minimo || $longitud > 100) {
        sm_responder_json(
            false,
            $etiqueta . ' debe contener entre ' . $minimo . ' y 100 caracteres.',
            ['campo' => $campo],
            422
        );
    }

    if (
        !preg_match('/^[\p{L}\p{M} .\'’\-]+$/u', $texto)
        || !preg_match('/\p{L}/u', $texto)
    ) {
        sm_responder_json(
            false,
            $etiqueta . ' contiene caracteres no permitidos.',
            ['campo' => $campo],
            422
        );
    }

    return $texto;
}

function adm_validar_telefono($valor): ?string
{
    $telefono = adm_texto($valor);
    if ($telefono === '') {
        return null;
    }

    $soloDigitos = preg_replace('/\D+/', '', $telefono) ?? '';
    if (strlen($soloDigitos) !== 10) {
        sm_responder_json(
            false,
            'El teléfono debe contener exactamente 10 dígitos.',
            ['campo' => 'telefono'],
            422
        );
    }

    return $soloDigitos;
}

function adm_validar_correo($valor): ?string
{
    $correo = strtolower(adm_texto($valor));
    if ($correo === '') {
        return null;
    }

    if (adm_longitud($correo) > 150 || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
        sm_responder_json(
            false,
            'Escribe un correo electrónico válido de hasta 150 caracteres.',
            ['campo' => 'correo'],
            422
        );
    }

    return $correo;
}

function adm_validar_password(
    string $password,
    string $confirmacion,
    string $campoPassword,
    string $campoConfirmacion
): void {
    $bytes = strlen($password);

    if ($bytes < 10 || $bytes > 72) {
        sm_responder_json(
            false,
            'La contraseña debe contener entre 10 y 72 caracteres.',
            ['campo' => $campoPassword],
            422
        );
    }
    if (!preg_match('/[a-z]/', $password)) {
        sm_responder_json(false, 'Incluye al menos una letra minúscula.', ['campo' => $campoPassword], 422);
    }
    if (!preg_match('/[A-Z]/', $password)) {
        sm_responder_json(false, 'Incluye al menos una letra mayúscula.', ['campo' => $campoPassword], 422);
    }
    if (!preg_match('/\d/', $password)) {
        sm_responder_json(false, 'Incluye al menos un número.', ['campo' => $campoPassword], 422);
    }
    if ($password !== $confirmacion) {
        sm_responder_json(
            false,
            'Las contraseñas no coinciden.',
            ['campo' => $campoConfirmacion],
            422
        );
    }
}

function adm_validar_estado($valor): int
{
    $estado = adm_texto($valor);
    if ($estado !== '0' && $estado !== '1') {
        sm_responder_json(false, 'El estado solicitado no es válido.', ['campo' => 'activo'], 422);
    }
    return (int) $estado;
}

function adm_entero_positivo($valor, string $campo): int
{
    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($entero === false) {
        sm_responder_json(
            false,
            'El identificador de ' . $campo . ' no es válido.',
            ['campo' => $campo],
            422
        );
    }

    return (int) $entero;
}

function adm_admin_id(): int
{
    $id = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || (int) $id < 1) {
        sm_responder_json(false, 'Tu sesión administrativa no es válida.', [], 401);
    }
    return (int) $id;
}

function adm_actualizar_sesion(
    string $usuario,
    string $nombre,
    ?string $apellidoPaterno,
    ?string $apellidoMaterno,
    ?string $telefono,
    ?string $correo
): void {
    $_SESSION['usuario'] = $usuario;
    $_SESSION['nombre'] = $nombre;
    $_SESSION['apellido_paterno'] = $apellidoPaterno;
    $_SESSION['apellido_materno'] = $apellidoMaterno;
    $_SESSION['telefono'] = $telefono;
    $_SESSION['correo'] = $correo;
    $_SESSION['nombre_completo'] = trim(implode(' ', array_filter([
        $nombre,
        $apellidoPaterno,
        $apellidoMaterno,
    ], static function ($valor): bool {
        return $valor !== null && $valor !== '';
    })));
}

function adm_nombre_completo(array $datos): string
{
    $partes = [
        trim((string) ($datos['nombre'] ?? '')),
        trim((string) ($datos['apellido_paterno'] ?? '')),
        trim((string) ($datos['apellido_materno'] ?? '')),
    ];
    $partes = array_values(array_filter($partes, static function (string $valor): bool {
        return $valor !== '';
    }));
    return $partes === [] ? 'Sin nombre' : implode(' ', $partes);
}

function adm_datos_seguros(array $administrador): array
{
    return [
        'id' => (int) ($administrador['id'] ?? 0),
        'usuario' => (string) ($administrador['usuario'] ?? ''),
        'nombre' => (string) ($administrador['nombre'] ?? ''),
        'apellido_paterno' => adm_nullable($administrador['apellido_paterno'] ?? null),
        'apellido_materno' => adm_nullable($administrador['apellido_materno'] ?? null),
        'telefono' => adm_nullable($administrador['telefono'] ?? null),
        'correo' => adm_nullable($administrador['correo'] ?? null),
        'activo' => (int) ($administrador['activo'] ?? 0),
    ];
}

function adm_datos_iguales(array $a, array $b): bool
{
    return adm_json($a) === adm_json($b);
}

function adm_nullable($valor): ?string
{
    if ($valor === null) {
        return null;
    }
    $texto = trim((string) $valor);
    return $texto === '' ? null : $texto;
}

function adm_cancelar(PDO $conexion, string $mensaje, int $codigo, array $extra = []): void
{
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    sm_responder_json(false, $mensaje, $extra, $codigo);
}

function adm_bind_nullable(PDOStatement $stmt, string $parametro, ?string $valor): void
{
    $stmt->bindValue(
        $parametro,
        $valor,
        $valor === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );
}

function adm_texto($valor): string
{
    return sm_limpiar_texto($valor);
}

function adm_json(?array $datos): ?string
{
    if ($datos === null) {
        return null;
    }
    $json = json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    return $json === false ? null : $json;
}

function adm_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? (int) mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function adm_recortar(string $texto, int $limite): string
{
    if (adm_longitud($texto) <= $limite) {
        return $texto;
    }
    return function_exists('mb_substr')
        ? (string) mb_substr($texto, 0, $limite, 'UTF-8')
        : substr($texto, 0, $limite);
}

function adm_recortar_nullable(?string $texto, int $limite): ?string
{
    if ($texto === null || trim($texto) === '') {
        return null;
    } 
    return adm_recortar($texto, $limite);
}

function adm_ip(): ?string
{
    $ip = adm_texto($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip === '' ? null : adm_recortar($ip, 45);
}

function adm_user_agent(): ?string
{
    $agente = adm_texto($_SERVER['HTTP_USER_AGENT'] ?? '');
    return $agente === '' ? null : $agente;
}