<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Técnicos - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| Gestión administrativa de cuentas técnicas.
| - Listado con búsqueda, filtros y paginación desde MySQL.
| - Alta, edición, baja lógica, reactivación y contraseña.
| - Departamento activo, turno y especialidad obligatorios.
| - Protección de técnicos con asignaciones o ejecuciones activas.
| - Usuario y correo únicos entre todos los tipos de cuenta.
| - Auditoría sin contraseñas ni hashes.
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
    error_log('[TECNICOS][PDO CONFIG] ' . $e->getMessage());
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = strtoupper(tec_texto(
    $metodo === 'GET'
        ? ($_GET['accion'] ?? 'LISTAR')
        : ($_POST['accion'] ?? '')
));

try {
    $adminId = tec_admin_id();
    tec_validar_admin_activo($conexion, $adminId);

    if ($metodo === 'GET') {
        sm_requerir_metodo('GET');

        if ($accion === 'INICIAL' || $accion === 'LISTAR') {
            tec_endpoint_listar($conexion);
        }

        if ($accion === 'DETALLE') {
            tec_endpoint_detalle($conexion);
        }

        sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
    }

    sm_requerir_metodo('POST');
    sm_validar_csrf();

    if ($accion === 'GUARDAR') {
        tec_endpoint_guardar($conexion, $adminId);
    }

    if ($accion === 'CAMBIAR_ESTADO') {
        tec_endpoint_cambiar_estado($conexion, $adminId);
    }

    if ($accion === 'CAMBIAR_PASSWORD') {
        tec_endpoint_cambiar_password($conexion, $adminId);
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'TEC-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][TECNICOS][PDO] '
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
        'No fue posible procesar la cuenta técnica.',
        ['referencia' => $referencia],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'TEC-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][TECNICOS] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la cuenta técnica.',
        ['referencia' => $referencia],
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function tec_endpoint_listar(PDO $conexion): void
{
    $filtros = tec_leer_filtros();
    $consulta = tec_construir_condiciones($filtros);

    $sqlTotal = "SELECT COUNT(*)
                 FROM tecnicos t
                 LEFT JOIN departamentos d ON d.id = t.departamento_id
                 WHERE " . $consulta['where'];
    $stmtTotal = $conexion->prepare($sqlTotal);
    tec_enlazar($stmtTotal, $consulta['parametros']);
    $stmtTotal->execute();
    $totalFiltrado = (int) $stmtTotal->fetchColumn();

    $porPagina = (int) $filtros['por_pagina'];
    $totalPaginas = max(1, (int) ceil($totalFiltrado / max(1, $porPagina)));
    $pagina = min((int) $filtros['pagina'], $totalPaginas);
    $offset = max(0, ($pagina - 1) * $porPagina);

    $sql = "SELECT
                t.id,
                t.usuario,
                t.nombre,
                t.apellido_paterno,
                t.apellido_materno,
                t.telefono,
                t.correo,
                t.departamento_id,
                t.turno,
                t.especialidad,
                t.activo,
                t.ultimo_acceso,
                t.fecha_registro,
                d.nombre AS departamento,
                COALESCE(d.activo, 0) AS departamento_activo,
                DATE_FORMAT(t.ultimo_acceso, '%d/%m/%Y %H:%i') AS ultimo_acceso_texto,
                DATE_FORMAT(t.fecha_registro, '%d/%m/%Y') AS fecha_registro_texto,
                COALESCE(carga.asignaciones_total, 0) AS asignaciones_total,
                COALESCE(carga.asignaciones_activas, 0) AS asignaciones_activas,
                COALESCE(carga.terminadas, 0) AS asignaciones_terminadas,
                COALESCE(ejec.ejecuciones_abiertas, 0) AS ejecuciones_abiertas
            FROM tecnicos t
            LEFT JOIN departamentos d ON d.id = t.departamento_id
            LEFT JOIN (
                SELECT
                    st.tecnico_id,
                    COUNT(*) AS asignaciones_total,
                    SUM(CASE
                        WHEN st.activo = 1
                         AND st.estado IN ('ASIGNADO', 'ACEPTADO', 'EN_PROCESO', 'PAUSADO')
                        THEN 1 ELSE 0 END
                    ) AS asignaciones_activas,
                    SUM(CASE WHEN st.estado = 'TERMINADO' THEN 1 ELSE 0 END) AS terminadas
                FROM solicitud_tecnicos st
                GROUP BY st.tecnico_id
            ) carga ON carga.tecnico_id = t.id
            LEFT JOIN (
                SELECT
                    em.tecnico_id,
                    SUM(CASE WHEN em.estado IN ('EN_PROCESO', 'PAUSADA') THEN 1 ELSE 0 END)
                        AS ejecuciones_abiertas
                FROM ejecuciones_mantenimiento em
                GROUP BY em.tecnico_id
            ) ejec ON ejec.tecnico_id = t.id
            WHERE " . $consulta['where'] . "
            ORDER BY
                t.activo DESC,
                COALESCE(carga.asignaciones_activas, 0) DESC,
                t.nombre ASC,
                t.apellido_paterno ASC,
                t.id ASC
            LIMIT :limite OFFSET :offset";

    $stmt = $conexion->prepare($sql);
    tec_enlazar($stmt, $consulta['parametros']);
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $registros = $stmt->fetchAll();

    foreach ($registros as &$registro) {
        tec_normalizar_registro($registro);
    }
    unset($registro);

    $resumen = tec_resumen_general($conexion);
    $departamentos = tec_departamentos($conexion);

    $inicio = $totalFiltrado === 0 ? 0 : $offset + 1;
    $fin = $totalFiltrado === 0 ? 0 : min($offset + $porPagina, $totalFiltrado);

    sm_responder_json(
        true,
        'Técnicos cargados correctamente.',
        [
            'tecnicos' => $registros,
            'departamentos' => $departamentos,
            'resumen' => $resumen,
            'paginacion' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total_registros' => $totalFiltrado,
                'total_paginas' => $totalPaginas,
                'inicio' => $inicio,
                'fin' => $fin,
            ],
            'filtros' => $filtros,
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function tec_endpoint_detalle(PDO $conexion): void
{
    $id = tec_entero_positivo($_GET['id'] ?? null, 'tecnico');

    $stmt = $conexion->prepare(
        "SELECT
            t.id,
            t.usuario,
            t.nombre,
            t.apellido_paterno,
            t.apellido_materno,
            t.telefono,
            t.correo,
            t.departamento_id,
            t.turno,
            t.especialidad,
            t.activo,
            t.ultimo_acceso,
            t.fecha_registro,
            d.nombre AS departamento,
            COALESCE(d.activo, 0) AS departamento_activo,
            DATE_FORMAT(t.ultimo_acceso, '%d/%m/%Y %H:%i') AS ultimo_acceso_texto,
            DATE_FORMAT(t.fecha_registro, '%d/%m/%Y') AS fecha_registro_texto,
            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos st
                WHERE st.tecnico_id = t.id
            ) AS asignaciones_total,
            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos st
                WHERE st.tecnico_id = t.id
                  AND st.activo = 1
                  AND st.estado IN ('ASIGNADO', 'ACEPTADO', 'EN_PROCESO', 'PAUSADO')
            ) AS asignaciones_activas,
            (
                SELECT COUNT(*)
                FROM ejecuciones_mantenimiento em
                WHERE em.tecnico_id = t.id
                  AND em.estado IN ('EN_PROCESO', 'PAUSADA')
            ) AS ejecuciones_abiertas,
            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos st
                WHERE st.tecnico_id = t.id
                  AND st.estado = 'TERMINADO'
            ) AS asignaciones_terminadas
         FROM tecnicos t
         LEFT JOIN departamentos d ON d.id = t.departamento_id
         WHERE t.id = :id
         LIMIT 1"
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $tecnico = $stmt->fetch();

    if (!$tecnico) {
        sm_responder_json(false, 'No se encontró el técnico seleccionado.', [], 404);
    }

    tec_normalizar_registro($tecnico);

    sm_responder_json(
        true,
        'Técnico encontrado.',
        ['tecnico' => $tecnico]
    );
}

function tec_endpoint_guardar(PDO $conexion, int $adminId): void
{
    $idTexto = tec_texto($_POST['tecnico_id'] ?? '');
    $id = $idTexto === '' ? 0 : tec_entero_positivo($idTexto, 'tecnico');

    $usuario = tec_validar_usuario($_POST['usuario'] ?? '');
    $nombre = tec_validar_nombre($_POST['nombre'] ?? '', 'nombre', 'El nombre', true);
    $apellidoPaterno = tec_validar_nombre(
        $_POST['apellido_paterno'] ?? '',
        'apellido_paterno',
        'El apellido paterno',
        false
    );
    $apellidoMaterno = tec_validar_nombre(
        $_POST['apellido_materno'] ?? '',
        'apellido_materno',
        'El apellido materno',
        false
    );
    $telefono = tec_validar_telefono($_POST['telefono'] ?? '');
    $correo = tec_validar_correo($_POST['correo'] ?? '');
    $departamentoId = tec_entero_positivo(
        $_POST['departamento_id'] ?? null,
        'departamento_id'
    );
    $turno = tec_validar_turno($_POST['turno'] ?? '');
    $especialidad = tec_validar_especialidad($_POST['especialidad'] ?? '');

    $password = (string) ($_POST['password'] ?? '');
    $confirmar = (string) ($_POST['confirmar_password'] ?? '');

    if ($id === 0) {
        tec_validar_password($password, $confirmar, 'password', 'confirmar_password');
    }

    $conexion->beginTransaction();

    $departamento = tec_bloquear_departamento($conexion, $departamentoId);
    if (!$departamento || (int) $departamento['activo'] !== 1) {
        tec_cancelar(
            $conexion,
            'Selecciona un departamento activo para el técnico.',
            409,
            ['campo' => 'departamento_id']
        );
    }

    $anterior = null;
    if ($id > 0) {
        $anterior = tec_bloquear_tecnico($conexion, $id, false);
        if (!$anterior) {
            tec_cancelar($conexion, 'El técnico ya no existe.', 404);
        }

        $cambioOperativo = (int) ($anterior['departamento_id'] ?? 0) !== $departamentoId
            || (string) ($anterior['turno'] ?? '') !== $turno;

        if ($cambioOperativo) {
            $trabajosActivos = tec_trabajos_activos_bloqueados($conexion, $id);
            if (count($trabajosActivos) > 0) {
                tec_cancelar(
                    $conexion,
                    'No se puede cambiar el departamento o turno mientras el técnico tenga mantenimientos activos. Finaliza o reasigna primero esos trabajos.',
                    409,
                    ['trabajos_activos' => count($trabajosActivos)]
                );
            }
        }
    }

    if (tec_usuario_en_uso($conexion, $usuario, $id)) {
        tec_cancelar(
            $conexion,
            'El usuario ya está registrado en otra cuenta del sistema.',
            409,
            ['campo' => 'usuario']
        );
    }

    if ($correo !== null && tec_correo_en_uso($conexion, $correo, $id)) {
        tec_cancelar(
            $conexion,
            'El correo electrónico ya está registrado en otra cuenta del sistema.',
            409,
            ['campo' => 'correo']
        );
    }

    if ($id === 0) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            tec_cancelar($conexion, 'No fue posible proteger la contraseña.', 500);
        }

        $stmt = $conexion->prepare(
            "INSERT INTO tecnicos
            (
                usuario, password_hash, nombre, apellido_paterno,
                apellido_materno, telefono, correo, departamento_id,
                turno, especialidad, activo, ultimo_acceso, fecha_registro
            )
            VALUES
            (
                :usuario, :password_hash, :nombre, :apellido_paterno,
                :apellido_materno, :telefono, :correo, :departamento_id,
                :turno, :especialidad, 1, NULL, NOW()
            )"
        );
        $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $hash, PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        tec_bind_nullable($stmt, ':apellido_paterno', $apellidoPaterno);
        tec_bind_nullable($stmt, ':apellido_materno', $apellidoMaterno);
        tec_bind_nullable($stmt, ':telefono', $telefono);
        tec_bind_nullable($stmt, ':correo', $correo);
        $stmt->bindValue(':departamento_id', $departamentoId, PDO::PARAM_INT);
        $stmt->bindValue(':turno', $turno, PDO::PARAM_STR);
        $stmt->bindValue(':especialidad', $especialidad, PDO::PARAM_STR);
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
            'departamento_id' => $departamentoId,
            'turno' => $turno,
            'especialidad' => $especialidad,
            'activo' => 1,
        ];

        tec_auditar(
            $conexion,
            $adminId,
            'INSERT',
            $id,
            'Alta de cuenta técnica.',
            null,
            $nuevo
        );
        tec_movimiento(
            $conexion,
            $adminId,
            'CREAR_TECNICO',
            'Se registró la cuenta técnica ' . $usuario . '.',
            $id
        );

        $conexion->commit();
        sm_responder_json(true, 'Técnico registrado correctamente.', ['id' => $id]);
    }

    $nuevo = [
        'id' => $id,
        'usuario' => $usuario,
        'nombre' => $nombre,
        'apellido_paterno' => $apellidoPaterno,
        'apellido_materno' => $apellidoMaterno,
        'telefono' => $telefono,
        'correo' => $correo,
        'departamento_id' => $departamentoId,
        'turno' => $turno,
        'especialidad' => $especialidad,
        'activo' => (int) $anterior['activo'],
    ];

    if (tec_datos_iguales(tec_datos_seguros($anterior), $nuevo)) {
        $conexion->commit();
        sm_responder_json(
            true,
            'No se detectaron cambios para guardar.',
            ['id' => $id, 'sin_cambios' => true]
        );
    }

    $stmt = $conexion->prepare(
        "UPDATE tecnicos
         SET usuario = :usuario,
             nombre = :nombre,
             apellido_paterno = :apellido_paterno,
             apellido_materno = :apellido_materno,
             telefono = :telefono,
             correo = :correo,
             departamento_id = :departamento_id,
             turno = :turno,
             especialidad = :especialidad
         WHERE id = :id"
    );
    $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    tec_bind_nullable($stmt, ':apellido_paterno', $apellidoPaterno);
    tec_bind_nullable($stmt, ':apellido_materno', $apellidoMaterno);
    tec_bind_nullable($stmt, ':telefono', $telefono);
    tec_bind_nullable($stmt, ':correo', $correo);
    $stmt->bindValue(':departamento_id', $departamentoId, PDO::PARAM_INT);
    $stmt->bindValue(':turno', $turno, PDO::PARAM_STR);
    $stmt->bindValue(':especialidad', $especialidad, PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    tec_auditar(
        $conexion,
        $adminId,
        'UPDATE',
        $id,
        'Actualización de datos personales y laborales de la cuenta técnica.',
        tec_datos_seguros($anterior),
        $nuevo
    );
    tec_movimiento(
        $conexion,
        $adminId,
        'EDITAR_TECNICO',
        'Se actualizó la cuenta técnica ' . $usuario . '.',
        $id
    );

    $conexion->commit();
    sm_responder_json(true, 'Técnico actualizado correctamente.', ['id' => $id]);
}

function tec_endpoint_cambiar_estado(PDO $conexion, int $adminId): void
{
    $id = tec_entero_positivo($_POST['tecnico_id'] ?? null, 'tecnico');
    $activo = tec_validar_estado($_POST['activo'] ?? null);

    $conexion->beginTransaction();
    $anterior = tec_bloquear_tecnico($conexion, $id, false);

    if (!$anterior) {
        tec_cancelar($conexion, 'El técnico ya no existe.', 404);
    }

    if ((int) $anterior['activo'] === $activo) {
        $conexion->commit();
        sm_responder_json(
            true,
            'La cuenta ya tenía el estado solicitado.',
            ['sin_cambios' => true]
        );
    }

    if ($activo === 0) {
        $trabajosActivos = tec_trabajos_activos_bloqueados($conexion, $id);
        if (count($trabajosActivos) > 0) {
            $folios = array_slice(array_values(array_unique(array_column($trabajosActivos, 'folio'))), 0, 5);
            tec_cancelar(
                $conexion,
                'No se puede desactivar al técnico porque todavía tiene '
                    . count($trabajosActivos)
                    . (count($trabajosActivos) === 1 ? ' mantenimiento activo.' : ' mantenimientos activos.')
                    . ' Finaliza o reasigna esos trabajos antes de continuar.',
                409,
                ['trabajos_activos' => count($trabajosActivos), 'folios' => $folios]
            );
        }
    } else {
        $departamentoId = (int) ($anterior['departamento_id'] ?? 0);
        $departamento = $departamentoId > 0
            ? tec_bloquear_departamento($conexion, $departamentoId)
            : false;

        if (!$departamento || (int) $departamento['activo'] !== 1) {
            tec_cancelar(
                $conexion,
                'No se puede reactivar la cuenta porque su departamento está inactivo o ya no existe. Edita primero la cuenta.',
                409
            );
        }

        tec_validar_turno($anterior['turno'] ?? '');
        tec_validar_especialidad($anterior['especialidad'] ?? '');

        if (tec_usuario_en_uso($conexion, (string) $anterior['usuario'], $id)) {
            tec_cancelar(
                $conexion,
                'No se puede reactivar porque el usuario ahora pertenece a otra cuenta.',
                409
            );
        }

        $correo = tec_nullable($anterior['correo'] ?? null);
        if ($correo !== null && tec_correo_en_uso($conexion, $correo, $id)) {
            tec_cancelar(
                $conexion,
                'No se puede reactivar porque el correo ahora pertenece a otra cuenta.',
                409
            );
        }
    }

    $stmt = $conexion->prepare('UPDATE tecnicos SET activo = :activo WHERE id = :id');
    $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $nuevo = tec_datos_seguros($anterior);
    $nuevo['activo'] = $activo;
    $reactivar = $activo === 1;

    tec_auditar(
        $conexion,
        $adminId,
        $reactivar ? 'REACTIVACION' : 'DELETE_LOGICO',
        $id,
        $reactivar ? 'Reactivación de cuenta técnica.' : 'Desactivación de cuenta técnica.',
        tec_datos_seguros($anterior),
        $nuevo
    );
    tec_movimiento(
        $conexion,
        $adminId,
        $reactivar ? 'REACTIVAR_TECNICO' : 'DESACTIVAR_TECNICO',
        ($reactivar ? 'Se reactivó' : 'Se desactivó')
            . ' la cuenta técnica '
            . (string) $anterior['usuario']
            . '.',
        $id
    );

    $conexion->commit();
    sm_responder_json(
        true,
        $reactivar
            ? 'Técnico reactivado correctamente.'
            : 'Técnico desactivado correctamente.'
    );
}

function tec_endpoint_cambiar_password(PDO $conexion, int $adminId): void
{
    $id = tec_entero_positivo($_POST['tecnico_id'] ?? null, 'tecnico');
    $passwordActor = (string) ($_POST['password_actual_actor'] ?? '');
    $nuevaPassword = (string) ($_POST['nueva_password'] ?? '');
    $confirmacion = (string) ($_POST['confirmar_nueva_password'] ?? '');

    if ($passwordActor === '') {
        sm_responder_json(
            false,
            'Confirma tu contraseña administrativa actual.',
            ['campo' => 'password_actual_actor'],
            422
        );
    }

    tec_validar_password(
        $nuevaPassword,
        $confirmacion,
        'nueva_password',
        'confirmar_nueva_password'
    );

    $conexion->beginTransaction();

    $stmtActor = $conexion->prepare(
        'SELECT id, password_hash, activo
         FROM administradores
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $stmtActor->bindValue(':id', $adminId, PDO::PARAM_INT);
    $stmtActor->execute();
    $actor = $stmtActor->fetch();

    if (!$actor || (int) $actor['activo'] !== 1) {
        tec_cancelar($conexion, 'Tu cuenta administrativa ya no está activa.', 403);
    }

    if (!password_verify($passwordActor, (string) $actor['password_hash'])) {
        tec_cancelar(
            $conexion,
            'Tu contraseña administrativa actual no es correcta.',
            422,
            ['campo' => 'password_actual_actor']
        );
    }

    $tecnico = tec_bloquear_tecnico($conexion, $id, true);
    if (!$tecnico) {
        tec_cancelar($conexion, 'El técnico ya no existe.', 404);
    }

    if (password_verify($nuevaPassword, (string) $tecnico['password_hash'])) {
        tec_cancelar(
            $conexion,
            'La nueva contraseña debe ser diferente de la contraseña actual de la cuenta.',
            422,
            ['campo' => 'nueva_password']
        );
    }

    $hash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        tec_cancelar($conexion, 'No fue posible proteger la nueva contraseña.', 500);
    }

    $stmt = $conexion->prepare(
        'UPDATE tecnicos SET password_hash = :password_hash WHERE id = :id'
    );
    $stmt->bindValue(':password_hash', $hash, PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    tec_auditar(
        $conexion,
        $adminId,
        'UPDATE',
        $id,
        'Restablecimiento administrativo de contraseña.',
        ['credencial_actualizada' => false],
        ['credencial_actualizada' => true]
    );
    tec_movimiento(
        $conexion,
        $adminId,
        'CAMBIAR_PASSWORD_TECNICO',
        'Se restableció la contraseña del técnico '
            . (string) $tecnico['usuario']
            . '.',
        $id
    );

    $conexion->commit();
    sm_responder_json(true, 'Contraseña actualizada correctamente.');
}

/* =========================================================================
   CONSULTAS Y REGLAS
   ========================================================================= */

function tec_leer_filtros(): array
{
    $busqueda = tec_texto($_GET['q'] ?? '');
    if (tec_longitud($busqueda) > 120) {
        $busqueda = tec_recortar($busqueda, 120);
    }

    $estado = strtoupper(tec_texto($_GET['estado'] ?? 'TODOS'));
    $estados = ['TODOS', 'ACTIVO', 'INACTIVO', 'SIN_ACCESO'];
    if (!in_array($estado, $estados, true)) {
        $estado = 'TODOS';
    }

    $turno = strtoupper(tec_texto($_GET['turno'] ?? 'TODOS'));
    $turnos = ['TODOS', 'MATUTINO', 'VESPERTINO', 'NOCTURNO'];
    if (!in_array($turno, $turnos, true)) {
        $turno = 'TODOS';
    }

    $carga = strtoupper(tec_texto($_GET['carga'] ?? 'TODOS'));
    $cargas = ['TODOS', 'CON_TRABAJO', 'SIN_TRABAJO', 'EN_EJECUCION'];
    if (!in_array($carga, $cargas, true)) {
        $carga = 'TODOS';
    }

    $departamentoTexto = tec_texto($_GET['departamento_id'] ?? '');
    $departamentoId = null;
    if ($departamentoTexto !== '') {
        $departamentoId = tec_entero_positivo($departamentoTexto, 'departamento_id');
    }

    $pagina = filter_var(
        $_GET['pagina'] ?? 1,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $pagina = $pagina === false ? 1 : (int) $pagina;

    $porPagina = filter_var($_GET['por_pagina'] ?? 10, FILTER_VALIDATE_INT);
    $permitidos = [10, 20, 40, 80];
    $porPagina = in_array((int) $porPagina, $permitidos, true)
        ? (int) $porPagina
        : 10;

    return [
        'q' => $busqueda,
        'estado' => $estado,
        'turno' => $turno,
        'carga' => $carga,
        'departamento_id' => $departamentoId,
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
    ];
}

function tec_construir_condiciones(array $filtros): array
{
    $where = ['1 = 1'];
    $parametros = [];

    if ($filtros['q'] !== '') {
        $where[] = "(
            t.usuario LIKE :busqueda_1
            OR t.nombre LIKE :busqueda_2
            OR t.apellido_paterno LIKE :busqueda_3
            OR t.apellido_materno LIKE :busqueda_4
            OR t.telefono LIKE :busqueda_5
            OR t.correo LIKE :busqueda_6
            OR d.nombre LIKE :busqueda_7
            OR t.especialidad LIKE :busqueda_8
            OR CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno) LIKE :busqueda_9
        )";
        $valorBusqueda = '%' . $filtros['q'] . '%';
        for ($indice = 1; $indice <= 9; $indice++) {
            $parametros[':busqueda_' . $indice] = $valorBusqueda;
        }
    }

    if ($filtros['estado'] === 'ACTIVO') {
        $where[] = 't.activo = 1';
    } elseif ($filtros['estado'] === 'INACTIVO') {
        $where[] = 't.activo = 0';
    } elseif ($filtros['estado'] === 'SIN_ACCESO') {
        $where[] = 't.ultimo_acceso IS NULL';
    }

    if ($filtros['departamento_id'] !== null) {
        $where[] = 't.departamento_id = :departamento_id';
        $parametros[':departamento_id'] = (int) $filtros['departamento_id'];
    }

    if ($filtros['turno'] !== 'TODOS') {
        $where[] = 't.turno = :turno';
        $parametros[':turno'] = $filtros['turno'];
    }

    if ($filtros['carga'] === 'CON_TRABAJO') {
        $where[] = "EXISTS (
            SELECT 1
            FROM solicitud_tecnicos stf
            WHERE stf.tecnico_id = t.id
              AND stf.activo = 1
              AND stf.estado IN ('ASIGNADO', 'ACEPTADO', 'EN_PROCESO', 'PAUSADO')
        )";
    } elseif ($filtros['carga'] === 'SIN_TRABAJO') {
        $where[] = "NOT EXISTS (
            SELECT 1
            FROM solicitud_tecnicos stf
            WHERE stf.tecnico_id = t.id
              AND stf.activo = 1
              AND stf.estado IN ('ASIGNADO', 'ACEPTADO', 'EN_PROCESO', 'PAUSADO')
        )";
    } elseif ($filtros['carga'] === 'EN_EJECUCION') {
        $where[] = "EXISTS (
            SELECT 1
            FROM ejecuciones_mantenimiento emf
            WHERE emf.tecnico_id = t.id
              AND emf.estado IN ('EN_PROCESO', 'PAUSADA')
        )";
    }

    return [
        'where' => implode(' AND ', $where),
        'parametros' => $parametros,
    ];
}

function tec_resumen_general(PDO $conexion): array
{
    $stmt = $conexion->query(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) AS activos,
            SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) AS inactivos,
            SUM(CASE WHEN ultimo_acceso IS NULL THEN 1 ELSE 0 END) AS sin_acceso
         FROM tecnicos"
    );
    $resumen = $stmt->fetch() ?: [];

    $stmtCarga = $conexion->query(
        "SELECT COUNT(DISTINCT st.tecnico_id)
         FROM solicitud_tecnicos st
         INNER JOIN tecnicos t ON t.id = st.tecnico_id
         WHERE t.activo = 1
           AND st.activo = 1
           AND st.estado IN ('ASIGNADO', 'ACEPTADO', 'EN_PROCESO', 'PAUSADO')"
    );

    return [
        'total' => (int) ($resumen['total'] ?? 0),
        'activos' => (int) ($resumen['activos'] ?? 0),
        'inactivos' => (int) ($resumen['inactivos'] ?? 0),
        'sin_acceso' => (int) ($resumen['sin_acceso'] ?? 0),
        'con_trabajo' => (int) $stmtCarga->fetchColumn(),
    ];
}

function tec_departamentos(PDO $conexion): array
{
    $stmt = $conexion->query(
        "SELECT
            d.id,
            d.nombre,
            d.activo,
            (
                SELECT COUNT(*)
                FROM tecnicos t
                WHERE t.departamento_id = d.id
            ) AS tecnicos_total
         FROM departamentos d
         ORDER BY d.activo DESC, d.nombre ASC, d.id ASC"
    );
    $departamentos = $stmt->fetchAll();

    foreach ($departamentos as &$departamento) {
        $departamento['id'] = (int) $departamento['id'];
        $departamento['activo'] = (int) $departamento['activo'];
        $departamento['tecnicos_total'] = (int) $departamento['tecnicos_total'];
    }
    unset($departamento);

    return $departamentos;
}

function tec_normalizar_registro(array &$registro): void
{
    // Esta función recibe datos tanto del listado como del detalle. Todos los
    // accesos son tolerantes a NULL y a registros antiguos incompletos para
    // impedir que una advertencia PHP contamine la respuesta JSON.
    $registro['id'] = (int) ($registro['id'] ?? 0);

    $departamentoId = $registro['departamento_id'] ?? null;
    $registro['departamento_id'] = ($departamentoId === null || $departamentoId === '')
        ? null
        : (int) $departamentoId;

    $registro['usuario'] = (string) ($registro['usuario'] ?? '');
    $registro['nombre'] = (string) ($registro['nombre'] ?? '');
    $registro['apellido_paterno'] = tec_nullable($registro['apellido_paterno'] ?? null);
    $registro['apellido_materno'] = tec_nullable($registro['apellido_materno'] ?? null);
    $registro['telefono'] = tec_nullable($registro['telefono'] ?? null);
    $registro['correo'] = tec_nullable($registro['correo'] ?? null);
    $registro['turno'] = strtoupper((string) ($registro['turno'] ?? ''));
    $registro['especialidad'] = tec_nullable($registro['especialidad'] ?? null);

    $registro['activo'] = (int) ($registro['activo'] ?? 0);
    $registro['departamento_activo'] = (int) ($registro['departamento_activo'] ?? 0);
    $registro['asignaciones_total'] = (int) ($registro['asignaciones_total'] ?? 0);
    $registro['asignaciones_activas'] = (int) ($registro['asignaciones_activas'] ?? 0);
    $registro['asignaciones_terminadas'] = (int) ($registro['asignaciones_terminadas'] ?? 0);
    $registro['ejecuciones_abiertas'] = (int) ($registro['ejecuciones_abiertas'] ?? 0);

    $registro['nombre_completo'] = tec_nombre_completo($registro);
    $registro['departamento'] = trim((string) ($registro['departamento'] ?? '')) !== ''
        ? (string) $registro['departamento']
        : 'Sin departamento';

    $ultimoAcceso = tec_nullable($registro['ultimo_acceso'] ?? null);
    $registro['ultimo_acceso'] = $ultimoAcceso;
    $ultimoAccesoTexto = tec_nullable($registro['ultimo_acceso_texto'] ?? null);
    $registro['ultimo_acceso_texto'] = $ultimoAcceso === null
        ? 'Nunca ha ingresado'
        : ($ultimoAccesoTexto ?? tec_formatear_fecha($ultimoAcceso, true));

    $fechaRegistro = tec_nullable($registro['fecha_registro'] ?? null);
    $registro['fecha_registro'] = $fechaRegistro;
    $fechaRegistroTexto = tec_nullable($registro['fecha_registro_texto'] ?? null);
    $registro['fecha_registro_texto'] = $fechaRegistroTexto
        ?? ($fechaRegistro === null ? 'Sin fecha' : tec_formatear_fecha($fechaRegistro, false));

    $registro['puede_reactivar'] = $registro['activo'] === 0
        && $registro['departamento_activo'] === 1
        && in_array($registro['turno'], ['MATUTINO', 'VESPERTINO', 'NOCTURNO'], true)
        && trim((string) ($registro['especialidad'] ?? '')) !== '';
}

function tec_formatear_fecha(string $valor, bool $incluirHora): string
{
    try {
        $fecha = new DateTimeImmutable($valor);
        return $fecha->format($incluirHora ? 'd/m/Y H:i' : 'd/m/Y');
    } catch (Throwable $e) {
        return $valor;
    }
}

function tec_bloquear_tecnico(PDO $conexion, int $id, bool $conPassword)
{
    $campos = 'id, usuario, nombre, apellido_paterno, apellido_materno, telefono, correo, departamento_id, turno, especialidad, activo';
    if ($conPassword) {
        $campos .= ', password_hash';
    }

    $stmt = $conexion->prepare(
        'SELECT ' . $campos . '
         FROM tecnicos
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch();
}

function tec_bloquear_departamento(PDO $conexion, int $id)
{
    $stmt = $conexion->prepare(
        'SELECT id, nombre, activo
         FROM departamentos
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch();
}

function tec_trabajos_activos_bloqueados(PDO $conexion, int $tecnicoId): array
{
    $stmtAsignaciones = $conexion->prepare(
        "SELECT
            st.id AS asignacion_id,
            st.estado AS estado_asignacion,
            s.id AS solicitud_id,
            s.folio,
            s.estado AS estado_solicitud
         FROM solicitud_tecnicos st
         INNER JOIN solicitudes s ON s.id = st.solicitud_id
         WHERE st.tecnico_id = :tecnico_id
           AND st.activo = 1
           AND st.estado IN ('ASIGNADO', 'ACEPTADO', 'EN_PROCESO', 'PAUSADO')
           AND s.activo = 1
           AND s.estado IN ('APROBADO', 'AGENDADO', 'EN_PROCESO', 'PAUSADO', 'ATRASADO')
         FOR UPDATE"
    );
    $stmtAsignaciones->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmtAsignaciones->execute();
    $trabajos = $stmtAsignaciones->fetchAll();

    $idsAsignaciones = [];
    foreach ($trabajos as $trabajo) {
        $idsAsignaciones[(int) $trabajo['asignacion_id']] = true;
    }

    $stmtEjecuciones = $conexion->prepare(
        "SELECT
            st.id AS asignacion_id,
            st.estado AS estado_asignacion,
            s.id AS solicitud_id,
            s.folio,
            s.estado AS estado_solicitud
         FROM ejecuciones_mantenimiento em
         INNER JOIN solicitud_tecnicos st ON st.id = em.solicitud_tecnico_id
         INNER JOIN solicitudes s ON s.id = em.solicitud_id
         WHERE em.tecnico_id = :tecnico_id
           AND em.estado IN ('EN_PROCESO', 'PAUSADA')
         FOR UPDATE"
    );
    $stmtEjecuciones->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmtEjecuciones->execute();

    foreach ($stmtEjecuciones->fetchAll() as $trabajo) {
        $asignacionId = (int) $trabajo['asignacion_id'];
        if (!isset($idsAsignaciones[$asignacionId])) {
            $trabajos[] = $trabajo;
            $idsAsignaciones[$asignacionId] = true;
        }
    }

    return $trabajos;
}

function tec_usuario_en_uso(PDO $conexion, string $usuario, int $tecnicoExcluir): bool
{
    $consultas = [
        ['tabla' => 'administradores', 'excluir' => 0],
        ['tabla' => 'solicitantes', 'excluir' => 0],
        ['tabla' => 'tecnicos', 'excluir' => $tecnicoExcluir],
    ];

    foreach ($consultas as $consulta) {
        $sql = 'SELECT COUNT(*) FROM ' . $consulta['tabla'] . ' WHERE usuario = :usuario';
        if ((int) $consulta['excluir'] > 0) {
            $sql .= ' AND id <> :id';
        }

        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
        if ((int) $consulta['excluir'] > 0) {
            $stmt->bindValue(':id', (int) $consulta['excluir'], PDO::PARAM_INT);
        }
        $stmt->execute();

        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
    }

    return false;
}

function tec_correo_en_uso(PDO $conexion, string $correo, int $tecnicoExcluir): bool
{
    $consultas = [
        ['tabla' => 'administradores', 'excluir' => 0],
        ['tabla' => 'solicitantes', 'excluir' => 0],
        ['tabla' => 'tecnicos', 'excluir' => $tecnicoExcluir],
    ];

    foreach ($consultas as $consulta) {
        $sql = 'SELECT COUNT(*) FROM ' . $consulta['tabla'] . ' WHERE correo = :correo';
        if ((int) $consulta['excluir'] > 0) {
            $sql .= ' AND id <> :id';
        }

        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);
        if ((int) $consulta['excluir'] > 0) {
            $stmt->bindValue(':id', (int) $consulta['excluir'], PDO::PARAM_INT);
        }
        $stmt->execute();

        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
    }

    return false;
}

function tec_validar_admin_activo(PDO $conexion, int $adminId): void
{
    $stmt = $conexion->prepare(
        'SELECT activo FROM administradores WHERE id = :id LIMIT 1'
    );
    $stmt->bindValue(':id', $adminId, PDO::PARAM_INT);
    $stmt->execute();

    if ((int) $stmt->fetchColumn() !== 1) {
        sm_responder_json(
            false,
            'Tu cuenta administrativa ya no está activa.',
            ['sesion_expirada' => true, 'redirect' => '../login.php?sesion=expirada'],
            403
        );
    }
}

/* =========================================================================
   AUDITORÍA Y MOVIMIENTOS
   ========================================================================= */

function tec_auditar(
    PDO $conexion,
    int $adminId,
    string $accion,
    int $registroId,
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
            'tecnicos', :registro_id, NULL, 'ADMIN',
            :actor_id, :accion, :motivo, :anteriores, :nuevos,
            :ip, :user_agent, NOW()
        )"
    );
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':motivo', tec_recortar($motivo, 500), PDO::PARAM_STR);
    tec_bind_nullable($stmt, ':anteriores', tec_json($anteriores));
    tec_bind_nullable($stmt, ':nuevos', tec_json($nuevos));
    tec_bind_nullable($stmt, ':ip', tec_ip());
    tec_bind_nullable($stmt, ':user_agent', tec_user_agent());
    $stmt->execute();
}

function tec_movimiento(
    PDO $conexion,
    int $adminId,
    string $accion,
    string $descripcion,
    int $registroId
): void {
    $stmt = $conexion->prepare(
        "INSERT INTO movimientos_sistema
        (
            tipo_usuario, usuario_id, accion, modulo, descripcion,
            tabla_afectada, registro_id, ip_address, user_agent,
            fecha_movimiento
        )
        VALUES
        (
            'ADMIN', :usuario_id, :accion, 'Técnicos', :descripcion,
            'tecnicos', :registro_id, :ip, :user_agent, NOW()
        )"
    );
    $stmt->bindValue(':usuario_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', tec_recortar($accion, 100), PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    tec_bind_nullable($stmt, ':ip', tec_recortar_nullable(tec_ip(), 60));
    tec_bind_nullable($stmt, ':user_agent', tec_recortar_nullable(tec_user_agent(), 255));
    $stmt->execute();
}

/* =========================================================================
   VALIDADORES Y UTILIDADES
   ========================================================================= */

function tec_validar_usuario($valor): string
{
    $usuario = strtolower(tec_texto($valor));
    $longitud = tec_longitud($usuario);

    if ($usuario === '') {
        sm_responder_json(false, 'El usuario es obligatorio.', ['campo' => 'usuario'], 422);
    }

    if ($longitud < 3 || $longitud > 60) {
        sm_responder_json(
            false,
            'El usuario debe tener entre 3 y 60 caracteres.',
            ['campo' => 'usuario'],
            422
        );
    }

    if (
        !preg_match('/^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$/', $usuario)
        || preg_match('/[._-]{2,}/', $usuario)
    ) {
        sm_responder_json(
            false,
            'Usa letras minúsculas, números, punto, guion o guion bajo, sin signos consecutivos ni al inicio o final.',
            ['campo' => 'usuario'],
            422
        );
    }

    return $usuario;
}

function tec_validar_nombre(
    $valor,
    string $campo,
    string $etiqueta,
    bool $obligatorio
): ?string {
    $texto = preg_replace('/\s+/u', ' ', tec_texto($valor));
    $texto = trim($texto === null ? '' : $texto);

    if ($texto === '') {
        if ($obligatorio) {
            sm_responder_json(false, $etiqueta . ' es obligatorio.', ['campo' => $campo], 422);
        }
        return null;
    }

    $minimo = $obligatorio ? 2 : 1;
    $longitud = tec_longitud($texto);
    if ($longitud < $minimo || $longitud > 100) {
        sm_responder_json(
            false,
            $etiqueta . ' debe tener entre ' . $minimo . ' y 100 caracteres.',
            ['campo' => $campo],
            422
        );
    }

    if (
        !preg_match('/^[\p{L}\p{M} .\'’-]+$/u', $texto)
        || !preg_match('/\p{L}/u', $texto)
    ) {
        sm_responder_json(
            false,
            $etiqueta . ' sólo puede contener letras, espacios, punto, apóstrofo o guion.',
            ['campo' => $campo],
            422
        );
    }

    return $texto;
}

function tec_validar_telefono($valor): ?string
{
    $telefono = preg_replace('/\D+/', '', tec_texto($valor));
    $telefono = $telefono === null ? '' : $telefono;

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

function tec_validar_correo($valor): ?string
{
    $correo = strtolower(tec_texto($valor));
    if ($correo === '') {
        return null;
    }

    if (tec_longitud($correo) > 150 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        sm_responder_json(
            false,
            'El correo electrónico no es válido.',
            ['campo' => 'correo'],
            422
        );
    }

    return $correo;
}

function tec_validar_turno($valor): string
{
    $turno = strtoupper(tec_texto($valor));
    if (!in_array($turno, ['MATUTINO', 'VESPERTINO', 'NOCTURNO'], true)) {
        sm_responder_json(
            false,
            'Selecciona un turno válido.',
            ['campo' => 'turno'],
            422
        );
    }
    return $turno;
}

function tec_validar_especialidad($valor): string
{
    $especialidad = preg_replace('/\s+/u', ' ', tec_texto($valor));
    $especialidad = trim($especialidad === null ? '' : $especialidad);
    $longitud = tec_longitud($especialidad);

    if ($longitud < 2 || $longitud > 150) {
        sm_responder_json(
            false,
            'La especialidad debe tener entre 2 y 150 caracteres.',
            ['campo' => 'especialidad'],
            422
        );
    }

    if (!preg_match('/[\p{L}\p{N}]/u', $especialidad)) {
        sm_responder_json(
            false,
            'Escribe una especialidad válida.',
            ['campo' => 'especialidad'],
            422
        );
    }

    return $especialidad;
}

function tec_validar_password(
    string $password,
    string $confirmacion,
    string $campoPassword,
    string $campoConfirmacion
): void {
    $longitud = strlen($password);

    if ($longitud < 10 || $longitud > 72) {
        sm_responder_json(
            false,
            'La contraseña debe tener entre 10 y 72 caracteres.',
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
        sm_responder_json(false, 'Las contraseñas no coinciden.', ['campo' => $campoConfirmacion], 422);
    }
}

function tec_validar_estado($valor): int
{
    $estado = tec_texto($valor);
    if ($estado !== '0' && $estado !== '1') {
        sm_responder_json(false, 'El estado solicitado no es válido.', [], 422);
    }
    return (int) $estado;
}

function tec_entero_positivo($valor, string $campo): int
{
    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($entero === false) {
        sm_responder_json(
            false,
            'El identificador recibido no es válido.',
            ['campo' => $campo],
            422
        );
    }

    return (int) $entero;
}

function tec_admin_id(): int
{
    $id = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || (int) $id <= 0) {
        sm_responder_json(false, 'La sesión administrativa no es válida.', [], 401);
    }
    return (int) $id;
}

function tec_datos_seguros(array $tecnico): array
{
    return [
        'id' => (int) ($tecnico['id'] ?? 0),
        'usuario' => (string) ($tecnico['usuario'] ?? ''),
        'nombre' => (string) ($tecnico['nombre'] ?? ''),
        'apellido_paterno' => tec_nullable($tecnico['apellido_paterno'] ?? null),
        'apellido_materno' => tec_nullable($tecnico['apellido_materno'] ?? null),
        'telefono' => tec_nullable($tecnico['telefono'] ?? null),
        'correo' => tec_nullable($tecnico['correo'] ?? null),
        'departamento_id' => isset($tecnico['departamento_id'])
            ? (int) $tecnico['departamento_id']
            : null,
        'turno' => (string) ($tecnico['turno'] ?? ''),
        'especialidad' => tec_nullable($tecnico['especialidad'] ?? null),
        'activo' => (int) ($tecnico['activo'] ?? 0),
    ];
}

function tec_datos_iguales(array $a, array $b): bool
{
    return tec_json($a) === tec_json($b);
}

function tec_nombre_completo(array $datos): string
{
    $partes = [
        (string) ($datos['nombre'] ?? ''),
        (string) ($datos['apellido_paterno'] ?? ''),
        (string) ($datos['apellido_materno'] ?? ''),
    ];

    $partes = array_values(array_filter(array_map(
        static function (string $valor): string {
            return trim($valor);
        },
        $partes
    ), static function (string $valor): bool {
        return $valor !== '';
    }));

    return implode(' ', $partes);
}

function tec_enlazar(PDOStatement $stmt, array $parametros): void
{
    foreach ($parametros as $clave => $valor) {
        $stmt->bindValue(
            $clave,
            $valor,
            is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }
}

function tec_cancelar(PDO $conexion, string $mensaje, int $codigo, array $extra = []): void
{
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    sm_responder_json(false, $mensaje, $extra, $codigo);
}

function tec_bind_nullable(PDOStatement $stmt, string $parametro, ?string $valor): void
{
    $stmt->bindValue(
        $parametro,
        $valor,
        $valor === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );
}

function tec_texto($valor): string
{
    return sm_limpiar_texto($valor);
}

function tec_nullable($valor): ?string
{
    if ($valor === null) {
        return null;
    }
    $texto = trim((string) $valor);
    return $texto === '' ? null : $texto;
}

function tec_json(?array $datos): ?string
{
    if ($datos === null) {
        return null;
    }

    $json = json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    return is_string($json) ? $json : null;
}

function tec_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? (int) mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function tec_recortar(string $texto, int $limite): string
{
    return function_exists('mb_substr')
        ? (string) mb_substr($texto, 0, $limite, 'UTF-8')
        : substr($texto, 0, $limite);
}
 
function tec_recortar_nullable(?string $texto, int $limite): ?string
{
    return $texto === null ? null : tec_recortar($texto, $limite);
}

function tec_ip(): ?string
{
    return function_exists('sm_ip_cliente')
        ? sm_ip_cliente()
        : tec_nullable($_SERVER['REMOTE_ADDR'] ?? null);
}

function tec_user_agent(): ?string
{
    return function_exists('sm_user_agent')
        ? sm_user_agent()
        : tec_nullable($_SERVER['HTTP_USER_AGENT'] ?? null);
}