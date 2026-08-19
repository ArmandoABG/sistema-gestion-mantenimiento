<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Solicitantes - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| Gestión administrativa de cuentas solicitantes.
| - Listado con búsqueda, filtros y paginación desde MySQL.
| - Alta, edición, baja lógica y reactivación.
| - Restablecimiento de contraseña con autorización del administrador actor.
| - Usuario y correo únicos entre administradores, solicitantes y técnicos.
| - Departamento activo obligatorio para altas, cambios y reactivaciones.
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
    error_log('[SOLICITANTES][PDO CONFIG] ' . $e->getMessage());
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = strtoupper(sol_texto(
    $metodo === 'GET'
        ? ($_GET['accion'] ?? 'LISTAR')
        : ($_POST['accion'] ?? '')
));

try {
    $adminId = sol_admin_id();
    sol_validar_admin_activo($conexion, $adminId);

    if ($metodo === 'GET') {
        sm_requerir_metodo('GET');

        if ($accion === 'INICIAL' || $accion === 'LISTAR') {
            sol_endpoint_listar($conexion);
        }

        if ($accion === 'DETALLE') {
            sol_endpoint_detalle($conexion);
        }

        sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
    }

    sm_requerir_metodo('POST');
    sm_validar_csrf();

    if ($accion === 'GUARDAR') {
        sol_endpoint_guardar($conexion, $adminId);
    }

    if ($accion === 'CAMBIAR_ESTADO') {
        sol_endpoint_cambiar_estado($conexion, $adminId);
    }

    if ($accion === 'CAMBIAR_PASSWORD') {
        sol_endpoint_cambiar_password($conexion, $adminId);
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'SOL-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][SOLICITANTES][PDO] '
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
        'No fue posible procesar la cuenta solicitante.',
        ['referencia' => $referencia],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'SOL-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][SOLICITANTES] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la cuenta solicitante.',
        ['referencia' => $referencia],
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function sol_endpoint_listar(PDO $conexion): void
{
    $filtros = sol_leer_filtros();
    $consulta = sol_construir_condiciones($filtros);

    $sqlTotal = "SELECT COUNT(*)
                 FROM solicitantes s
                 LEFT JOIN departamentos d ON d.id = s.departamento_id
                 WHERE " . $consulta['where'];
    $stmtTotal = $conexion->prepare($sqlTotal);
    sol_enlazar($stmtTotal, $consulta['parametros']);
    $stmtTotal->execute();
    $totalFiltrado = (int) $stmtTotal->fetchColumn();

    $porPagina = (int) $filtros['por_pagina'];
    $totalPaginas = max(1, (int) ceil($totalFiltrado / max(1, $porPagina)));
    $pagina = min((int) $filtros['pagina'], $totalPaginas);
    $offset = max(0, ($pagina - 1) * $porPagina);

    $sql = "SELECT
                s.id,
                s.usuario,
                s.nombre,
                s.apellido_paterno,
                s.apellido_materno,
                s.telefono,
                s.correo,
                s.departamento_id,
                s.activo,
                s.ultimo_acceso,
                s.fecha_registro,
                d.nombre AS departamento,
                COALESCE(d.activo, 0) AS departamento_activo,
                DATE_FORMAT(s.ultimo_acceso, '%d/%m/%Y %H:%i') AS ultimo_acceso_texto,
                DATE_FORMAT(s.fecha_registro, '%d/%m/%Y') AS fecha_registro_texto,
                (
                    SELECT COUNT(*)
                    FROM solicitudes sx
                    WHERE sx.solicitante_id = s.id
                ) AS solicitudes_total,
                (
                    SELECT COUNT(*)
                    FROM solicitudes sx
                    WHERE sx.solicitante_id = s.id
                      AND sx.activo = 1
                      AND sx.estado IN (
                          'PENDIENTE', 'APROBADO', 'AGENDADO',
                          'EN_PROCESO', 'PAUSADO', 'ATRASADO'
                      )
                ) AS solicitudes_abiertas
            FROM solicitantes s
            LEFT JOIN departamentos d ON d.id = s.departamento_id
            WHERE " . $consulta['where'] . "
            ORDER BY s.activo DESC, s.nombre ASC, s.apellido_paterno ASC, s.id ASC
            LIMIT :limite OFFSET :offset";

    $stmt = $conexion->prepare($sql);
    sol_enlazar($stmt, $consulta['parametros']);
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $registros = $stmt->fetchAll();

    foreach ($registros as &$registro) {
        $registro['id'] = (int) $registro['id'];
        $registro['departamento_id'] = $registro['departamento_id'] === null
            ? null
            : (int) $registro['departamento_id'];
        $registro['activo'] = (int) $registro['activo'];
        $registro['departamento_activo'] = (int) $registro['departamento_activo'];
        $registro['solicitudes_total'] = (int) $registro['solicitudes_total'];
        $registro['solicitudes_abiertas'] = (int) $registro['solicitudes_abiertas'];
        $registro['nombre_completo'] = sol_nombre_completo($registro);
        $registro['departamento'] = $registro['departamento'] ?: 'Sin departamento';
        $registro['ultimo_acceso_texto'] = $registro['ultimo_acceso'] === null
            ? 'Nunca ha ingresado'
            : (string) $registro['ultimo_acceso_texto'];
        $registro['puede_reactivar'] = $registro['activo'] === 0
            && $registro['departamento_activo'] === 1;
    }
    unset($registro);

    $resumen = sol_resumen_general($conexion);
    $departamentos = sol_departamentos($conexion);

    $inicio = $totalFiltrado === 0 ? 0 : $offset + 1;
    $fin = $totalFiltrado === 0 ? 0 : min($offset + $porPagina, $totalFiltrado);

    sm_responder_json(
        true,
        'Solicitantes cargados correctamente.',
        [
            'solicitantes' => $registros,
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

function sol_endpoint_detalle(PDO $conexion): void
{
    $id = sol_entero_positivo($_GET['id'] ?? null, 'solicitante');

    $stmt = $conexion->prepare(
        "SELECT
            s.id,
            s.usuario,
            s.nombre,
            s.apellido_paterno,
            s.apellido_materno,
            s.telefono,
            s.correo,
            s.departamento_id,
            s.activo,
            s.ultimo_acceso,
            s.fecha_registro,
            d.nombre AS departamento,
            COALESCE(d.activo, 0) AS departamento_activo,
            (
                SELECT COUNT(*) FROM solicitudes sx
                WHERE sx.solicitante_id = s.id
            ) AS solicitudes_total,
            (
                SELECT COUNT(*) FROM solicitudes sx
                WHERE sx.solicitante_id = s.id
                  AND sx.activo = 1
                  AND sx.estado IN (
                      'PENDIENTE', 'APROBADO', 'AGENDADO',
                      'EN_PROCESO', 'PAUSADO', 'ATRASADO'
                  )
            ) AS solicitudes_abiertas
         FROM solicitantes s
         LEFT JOIN departamentos d ON d.id = s.departamento_id
         WHERE s.id = :id
         LIMIT 1"
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $solicitante = $stmt->fetch();

    if (!$solicitante) {
        sm_responder_json(false, 'No se encontró el solicitante seleccionado.', [], 404);
    }

    $solicitante['id'] = (int) $solicitante['id'];
    $solicitante['departamento_id'] = $solicitante['departamento_id'] === null
        ? null
        : (int) $solicitante['departamento_id'];
    $solicitante['activo'] = (int) $solicitante['activo'];
    $solicitante['departamento_activo'] = (int) $solicitante['departamento_activo'];
    $solicitante['solicitudes_total'] = (int) $solicitante['solicitudes_total'];
    $solicitante['solicitudes_abiertas'] = (int) $solicitante['solicitudes_abiertas'];
    $solicitante['nombre_completo'] = sol_nombre_completo($solicitante);

    sm_responder_json(
        true,
        'Solicitante encontrado.',
        ['solicitante' => $solicitante]
    );
}

function sol_endpoint_guardar(PDO $conexion, int $adminId): void
{
    $idTexto = sol_texto($_POST['solicitante_id'] ?? '');
    $id = $idTexto === '' ? 0 : sol_entero_positivo($idTexto, 'solicitante');

    $usuario = sol_validar_usuario($_POST['usuario'] ?? '');
    $nombre = sol_validar_nombre($_POST['nombre'] ?? '', 'nombre', 'El nombre', true);
    $apellidoPaterno = sol_validar_nombre(
        $_POST['apellido_paterno'] ?? '',
        'apellido_paterno',
        'El apellido paterno',
        false
    );
    $apellidoMaterno = sol_validar_nombre(
        $_POST['apellido_materno'] ?? '',
        'apellido_materno',
        'El apellido materno',
        false
    );
    $telefono = sol_validar_telefono($_POST['telefono'] ?? '');
    $correo = sol_validar_correo($_POST['correo'] ?? '');
    $departamentoId = sol_entero_positivo(
        $_POST['departamento_id'] ?? null,
        'departamento_id'
    );

    $password = (string) ($_POST['password'] ?? '');
    $confirmar = (string) ($_POST['confirmar_password'] ?? '');

    if ($id === 0) {
        sol_validar_password($password, $confirmar, 'password', 'confirmar_password');
    }

    $conexion->beginTransaction();

    $departamento = sol_bloquear_departamento($conexion, $departamentoId);
    if (!$departamento || (int) $departamento['activo'] !== 1) {
        sol_cancelar(
            $conexion,
            'Selecciona un departamento activo para el solicitante.',
            409,
            ['campo' => 'departamento_id']
        );
    }

    $anterior = null;
    if ($id > 0) {
        $anterior = sol_bloquear_solicitante($conexion, $id, false);
        if (!$anterior) {
            sol_cancelar($conexion, 'El solicitante ya no existe.', 404);
        }
    }

    if (sol_usuario_en_uso($conexion, $usuario, $id)) {
        sol_cancelar(
            $conexion,
            'El usuario ya está registrado en otra cuenta del sistema.',
            409,
            ['campo' => 'usuario']
        );
    }

    if ($correo !== null && sol_correo_en_uso($conexion, $correo, $id)) {
        sol_cancelar(
            $conexion,
            'El correo electrónico ya está registrado en otra cuenta del sistema.',
            409,
            ['campo' => 'correo']
        );
    }

    if ($id === 0) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            sol_cancelar($conexion, 'No fue posible proteger la contraseña.', 500);
        }

        $stmt = $conexion->prepare(
            "INSERT INTO solicitantes
            (
                usuario, password_hash, nombre, apellido_paterno,
                apellido_materno, telefono, correo, departamento_id,
                activo, ultimo_acceso, fecha_registro
            )
            VALUES
            (
                :usuario, :password_hash, :nombre, :apellido_paterno,
                :apellido_materno, :telefono, :correo, :departamento_id,
                1, NULL, NOW()
            )"
        );
        $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $hash, PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        sol_bind_nullable($stmt, ':apellido_paterno', $apellidoPaterno);
        sol_bind_nullable($stmt, ':apellido_materno', $apellidoMaterno);
        sol_bind_nullable($stmt, ':telefono', $telefono);
        sol_bind_nullable($stmt, ':correo', $correo);
        $stmt->bindValue(':departamento_id', $departamentoId, PDO::PARAM_INT);
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
            'activo' => 1,
        ];

        sol_auditar(
            $conexion,
            $adminId,
            'INSERT',
            $id,
            'Alta de cuenta solicitante.',
            null,
            $nuevo
        );
        sol_movimiento(
            $conexion,
            $adminId,
            'CREAR_SOLICITANTE',
            'Se registró la cuenta solicitante ' . $usuario . '.',
            $id
        );

        $conexion->commit();
        sm_responder_json(true, 'Solicitante registrado correctamente.', ['id' => $id]);
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
        'activo' => (int) $anterior['activo'],
    ];

    if (sol_datos_iguales(sol_datos_seguros($anterior), $nuevo)) {
        $conexion->commit();
        sm_responder_json(true, 'No había cambios por guardar.', ['sin_cambios' => true]);
    }

    $stmt = $conexion->prepare(
        "UPDATE solicitantes
         SET usuario = :usuario,
             nombre = :nombre,
             apellido_paterno = :apellido_paterno,
             apellido_materno = :apellido_materno,
             telefono = :telefono,
             correo = :correo,
             departamento_id = :departamento_id
         WHERE id = :id"
    );
    $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    sol_bind_nullable($stmt, ':apellido_paterno', $apellidoPaterno);
    sol_bind_nullable($stmt, ':apellido_materno', $apellidoMaterno);
    sol_bind_nullable($stmt, ':telefono', $telefono);
    sol_bind_nullable($stmt, ':correo', $correo);
    $stmt->bindValue(':departamento_id', $departamentoId, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    sol_auditar(
        $conexion,
        $adminId,
        'UPDATE',
        $id,
        'Actualización de datos de la cuenta solicitante.',
        sol_datos_seguros($anterior),
        $nuevo
    );
    sol_movimiento(
        $conexion,
        $adminId,
        'EDITAR_SOLICITANTE',
        'Se actualizó la cuenta solicitante ' . $usuario . '.',
        $id
    );

    $conexion->commit();
    sm_responder_json(true, 'Solicitante actualizado correctamente.', ['id' => $id]);
}

function sol_endpoint_cambiar_estado(PDO $conexion, int $adminId): void
{
    $id = sol_entero_positivo($_POST['solicitante_id'] ?? null, 'solicitante');
    $activo = sol_validar_estado($_POST['activo'] ?? null);

    $conexion->beginTransaction();
    $anterior = sol_bloquear_solicitante($conexion, $id, false);

    if (!$anterior) {
        sol_cancelar($conexion, 'El solicitante ya no existe.', 404);
    }

    if ((int) $anterior['activo'] === $activo) {
        $conexion->commit();
        sm_responder_json(true, 'La cuenta ya tenía el estado solicitado.', ['sin_cambios' => true]);
    }

    if ($activo === 1) {
        $departamentoId = (int) ($anterior['departamento_id'] ?? 0);
        $departamento = $departamentoId > 0
            ? sol_bloquear_departamento($conexion, $departamentoId)
            : false;

        if (!$departamento || (int) $departamento['activo'] !== 1) {
            sol_cancelar(
                $conexion,
                'No se puede reactivar la cuenta porque su departamento está inactivo o ya no existe. Edita primero la cuenta y asígnala a un departamento activo.',
                409
            );
        }

        if (sol_usuario_en_uso($conexion, (string) $anterior['usuario'], $id)) {
            sol_cancelar(
                $conexion,
                'No se puede reactivar porque el usuario ahora pertenece a otra cuenta.',
                409
            );
        }

        $correo = sol_nullable($anterior['correo'] ?? null);
        if ($correo !== null && sol_correo_en_uso($conexion, $correo, $id)) {
            sol_cancelar(
                $conexion,
                'No se puede reactivar porque el correo ahora pertenece a otra cuenta.',
                409
            );
        }
    }

    $stmt = $conexion->prepare(
        'UPDATE solicitantes SET activo = :activo WHERE id = :id'
    );
    $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $nuevo = sol_datos_seguros($anterior);
    $nuevo['activo'] = $activo;
    $reactivar = $activo === 1;

    sol_auditar(
        $conexion,
        $adminId,
        $reactivar ? 'REACTIVACION' : 'DELETE_LOGICO',
        $id,
        $reactivar ? 'Reactivación de cuenta solicitante.' : 'Desactivación de cuenta solicitante.',
        sol_datos_seguros($anterior),
        $nuevo
    );
    sol_movimiento(
        $conexion,
        $adminId,
        $reactivar ? 'REACTIVAR_SOLICITANTE' : 'DESACTIVAR_SOLICITANTE',
        ($reactivar ? 'Se reactivó' : 'Se desactivó')
            . ' la cuenta solicitante '
            . (string) $anterior['usuario']
            . '.',
        $id
    );

    $conexion->commit();
    sm_responder_json(
        true,
        $reactivar
            ? 'Solicitante reactivado correctamente.'
            : 'Solicitante desactivado correctamente.'
    );
}

function sol_endpoint_cambiar_password(PDO $conexion, int $adminId): void
{
    $id = sol_entero_positivo($_POST['solicitante_id'] ?? null, 'solicitante');
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

    sol_validar_password(
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
        sol_cancelar($conexion, 'Tu cuenta administrativa ya no está activa.', 403);
    }

    if (!password_verify($passwordActor, (string) $actor['password_hash'])) {
        sol_cancelar(
            $conexion,
            'Tu contraseña administrativa actual no es correcta.',
            422,
            ['campo' => 'password_actual_actor']
        );
    }

    $solicitante = sol_bloquear_solicitante($conexion, $id, true);
    if (!$solicitante) {
        sol_cancelar($conexion, 'El solicitante ya no existe.', 404);
    }

    if (password_verify($nuevaPassword, (string) $solicitante['password_hash'])) {
        sol_cancelar(
            $conexion,
            'La nueva contraseña debe ser diferente de la contraseña actual de la cuenta.',
            422,
            ['campo' => 'nueva_password']
        );
    }

    $hash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        sol_cancelar($conexion, 'No fue posible proteger la nueva contraseña.', 500);
    }

    $stmt = $conexion->prepare(
        'UPDATE solicitantes SET password_hash = :password_hash WHERE id = :id'
    );
    $stmt->bindValue(':password_hash', $hash, PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    sol_auditar(
        $conexion,
        $adminId,
        'UPDATE',
        $id,
        'Restablecimiento administrativo de contraseña.',
        ['credencial_actualizada' => false],
        ['credencial_actualizada' => true]
    );
    sol_movimiento(
        $conexion,
        $adminId,
        'CAMBIAR_PASSWORD_SOLICITANTE',
        'Se restableció la contraseña del solicitante '
            . (string) $solicitante['usuario']
            . '.',
        $id
    );

    $conexion->commit();
    sm_responder_json(true, 'Contraseña actualizada correctamente.');
}

/* =========================================================================
   CONSULTAS Y VALIDACIONES
   ========================================================================= */

function sol_leer_filtros(): array
{
    $busqueda = sol_texto($_GET['q'] ?? '');
    if (sol_longitud($busqueda) > 120) {
        $busqueda = sol_recortar($busqueda, 120);
    }

    $estado = strtoupper(sol_texto($_GET['estado'] ?? 'TODOS'));
    $estados = ['TODOS', 'ACTIVO', 'INACTIVO', 'SIN_ACCESO'];
    if (!in_array($estado, $estados, true)) {
        $estado = 'TODOS';
    }

    $departamentoTexto = sol_texto($_GET['departamento_id'] ?? '');
    $departamentoId = null;
    if ($departamentoTexto !== '') {
        $departamentoId = sol_entero_positivo($departamentoTexto, 'departamento_id');
    }

    $pagina = filter_var(
        $_GET['pagina'] ?? 1,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $pagina = $pagina === false ? 1 : (int) $pagina;

    $porPagina = filter_var(
        $_GET['por_pagina'] ?? 10,
        FILTER_VALIDATE_INT
    );
    $permitidos = [10, 20, 40, 80];
    $porPagina = in_array((int) $porPagina, $permitidos, true)
        ? (int) $porPagina
        : 10;

    return [
        'q' => $busqueda,
        'estado' => $estado,
        'departamento_id' => $departamentoId,
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
    ];
}

function sol_construir_condiciones(array $filtros): array
{
    $where = ['1 = 1'];
    $parametros = [];

    if ($filtros['q'] !== '') {
        $where[] = "(
            s.usuario LIKE :busqueda_1
            OR s.nombre LIKE :busqueda_2
            OR s.apellido_paterno LIKE :busqueda_3
            OR s.apellido_materno LIKE :busqueda_4
            OR s.telefono LIKE :busqueda_5
            OR s.correo LIKE :busqueda_6
            OR d.nombre LIKE :busqueda_7
            OR CONCAT_WS(' ', s.nombre, s.apellido_paterno, s.apellido_materno) LIKE :busqueda_8
        )";
        $valorBusqueda = '%' . $filtros['q'] . '%';
        for ($indice = 1; $indice <= 8; $indice++) {
            $parametros[':busqueda_' . $indice] = $valorBusqueda;
        }
    }

    if ($filtros['estado'] === 'ACTIVO') {
        $where[] = 's.activo = 1';
    } elseif ($filtros['estado'] === 'INACTIVO') {
        $where[] = 's.activo = 0';
    } elseif ($filtros['estado'] === 'SIN_ACCESO') {
        $where[] = 's.ultimo_acceso IS NULL';
    }

    if ($filtros['departamento_id'] !== null) {
        $where[] = 's.departamento_id = :departamento_id';
        $parametros[':departamento_id'] = (int) $filtros['departamento_id'];
    }

    return [
        'where' => implode(' AND ', $where),
        'parametros' => $parametros,
    ];
}

function sol_resumen_general(PDO $conexion): array
{
    $stmt = $conexion->query(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) AS activos,
            SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) AS inactivos,
            SUM(CASE WHEN ultimo_acceso IS NULL THEN 1 ELSE 0 END) AS sin_acceso
         FROM solicitantes"
    );
    $resumen = $stmt->fetch() ?: [];

    return [
        'total' => (int) ($resumen['total'] ?? 0),
        'activos' => (int) ($resumen['activos'] ?? 0),
        'inactivos' => (int) ($resumen['inactivos'] ?? 0),
        'sin_acceso' => (int) ($resumen['sin_acceso'] ?? 0),
    ];
}

function sol_departamentos(PDO $conexion): array
{
    $stmt = $conexion->query(
        "SELECT
            d.id,
            d.nombre,
            d.activo,
            (
                SELECT COUNT(*)
                FROM solicitantes s
                WHERE s.departamento_id = d.id
            ) AS solicitantes_total
         FROM departamentos d
         ORDER BY d.activo DESC, d.nombre ASC, d.id ASC"
    );
    $departamentos = $stmt->fetchAll();

    foreach ($departamentos as &$departamento) {
        $departamento['id'] = (int) $departamento['id'];
        $departamento['activo'] = (int) $departamento['activo'];
        $departamento['solicitantes_total'] = (int) $departamento['solicitantes_total'];
    }
    unset($departamento);

    return $departamentos;
}

function sol_bloquear_solicitante(PDO $conexion, int $id, bool $conPassword)
{
    $campos = 'id, usuario, nombre, apellido_paterno, apellido_materno, telefono, correo, departamento_id, activo';
    if ($conPassword) {
        $campos .= ', password_hash';
    }

    $stmt = $conexion->prepare(
        'SELECT ' . $campos . '
         FROM solicitantes
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch();
}

function sol_bloquear_departamento(PDO $conexion, int $id)
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

function sol_usuario_en_uso(PDO $conexion, string $usuario, int $solicitanteExcluir): bool
{
    $consultas = [
        ['tabla' => 'administradores', 'excluir' => 0],
        ['tabla' => 'solicitantes', 'excluir' => $solicitanteExcluir],
        ['tabla' => 'tecnicos', 'excluir' => 0],
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

function sol_correo_en_uso(PDO $conexion, string $correo, int $solicitanteExcluir): bool
{
    $consultas = [
        ['tabla' => 'administradores', 'excluir' => 0],
        ['tabla' => 'solicitantes', 'excluir' => $solicitanteExcluir],
        ['tabla' => 'tecnicos', 'excluir' => 0],
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

function sol_validar_admin_activo(PDO $conexion, int $adminId): void
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

function sol_auditar(
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
            'solicitantes', :registro_id, NULL, 'ADMIN',
            :actor_id, :accion, :motivo, :anteriores, :nuevos,
            :ip, :user_agent, NOW()
        )"
    );
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':motivo', sol_recortar($motivo, 500), PDO::PARAM_STR);
    sol_bind_nullable($stmt, ':anteriores', sol_json($anteriores));
    sol_bind_nullable($stmt, ':nuevos', sol_json($nuevos));
    sol_bind_nullable($stmt, ':ip', sol_ip());
    sol_bind_nullable($stmt, ':user_agent', sol_user_agent());
    $stmt->execute();
}

function sol_movimiento(
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
            'ADMIN', :usuario_id, :accion, 'Solicitantes', :descripcion,
            'solicitantes', :registro_id, :ip, :user_agent, NOW()
        )"
    );
    $stmt->bindValue(':usuario_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', sol_recortar($accion, 100), PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    sol_bind_nullable($stmt, ':ip', sol_recortar_nullable(sol_ip(), 60));
    sol_bind_nullable($stmt, ':user_agent', sol_recortar_nullable(sol_user_agent(), 255));
    $stmt->execute();
}

/* =========================================================================
   VALIDADORES Y UTILIDADES
   ========================================================================= */

function sol_validar_usuario($valor): string
{
    $usuario = strtolower(sol_texto($valor));
    $longitud = sol_longitud($usuario);

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

function sol_validar_nombre(
    $valor,
    string $campo,
    string $etiqueta,
    bool $obligatorio
): ?string {
    $texto = preg_replace('/\s+/u', ' ', sol_texto($valor));
    $texto = trim($texto === null ? '' : $texto);

    if ($texto === '') {
        if ($obligatorio) {
            sm_responder_json(false, $etiqueta . ' es obligatorio.', ['campo' => $campo], 422);
        }
        return null;
    }

    $minimo = $obligatorio ? 2 : 1;
    $longitud = sol_longitud($texto);
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

function sol_validar_telefono($valor): ?string
{
    $telefono = preg_replace('/\D+/', '', sol_texto($valor));
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

function sol_validar_correo($valor): ?string
{
    $correo = strtolower(sol_texto($valor));
    if ($correo === '') {
        return null;
    }

    if (sol_longitud($correo) > 150 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        sm_responder_json(
            false,
            'El correo electrónico no es válido.',
            ['campo' => 'correo'],
            422
        );
    }

    return $correo;
}

function sol_validar_password(
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

function sol_validar_estado($valor): int
{
    $estado = sol_texto($valor);
    if ($estado !== '0' && $estado !== '1') {
        sm_responder_json(false, 'El estado solicitado no es válido.', [], 422);
    }
    return (int) $estado;
}

function sol_entero_positivo($valor, string $campo): int
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

function sol_admin_id(): int
{
    $id = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || (int) $id <= 0) {
        sm_responder_json(false, 'La sesión administrativa no es válida.', [], 401);
    }
    return (int) $id;
}

function sol_datos_seguros(array $solicitante): array
{
    return [
        'id' => (int) ($solicitante['id'] ?? 0),
        'usuario' => (string) ($solicitante['usuario'] ?? ''),
        'nombre' => (string) ($solicitante['nombre'] ?? ''),
        'apellido_paterno' => sol_nullable($solicitante['apellido_paterno'] ?? null),
        'apellido_materno' => sol_nullable($solicitante['apellido_materno'] ?? null),
        'telefono' => sol_nullable($solicitante['telefono'] ?? null),
        'correo' => sol_nullable($solicitante['correo'] ?? null),
        'departamento_id' => isset($solicitante['departamento_id'])
            ? (int) $solicitante['departamento_id']
            : null,
        'activo' => (int) ($solicitante['activo'] ?? 0),
    ];
}

function sol_datos_iguales(array $a, array $b): bool
{
    return sol_json($a) === sol_json($b);
}

function sol_nombre_completo(array $datos): string
{
    $partes = [
        $datos['nombre'] ?? '',
        $datos['apellido_paterno'] ?? '',
        $datos['apellido_materno'] ?? '',
    ];
    $partes = array_values(array_filter(array_map('trim', $partes), static function ($valor) {
        return $valor !== '';
    }));

    return implode(' ', $partes);
}

function sol_enlazar(PDOStatement $stmt, array $parametros): void
{
    foreach ($parametros as $clave => $valor) {
        $stmt->bindValue(
            $clave,
            $valor,
            is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }
}

function sol_cancelar(PDO $conexion, string $mensaje, int $codigo, array $extra = []): void
{
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    sm_responder_json(false, $mensaje, $extra, $codigo);
}

function sol_bind_nullable(PDOStatement $stmt, string $parametro, ?string $valor): void
{
    $stmt->bindValue(
        $parametro,
        $valor,
        $valor === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );
}

function sol_texto($valor): string
{
    return sm_limpiar_texto($valor);
}

function sol_nullable($valor): ?string
{
    if ($valor === null) {
        return null;
    }
    $texto = trim((string) $valor);
    return $texto === '' ? null : $texto;
}

function sol_json(?array $datos): ?string
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

function sol_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? (int) mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}
 
function sol_recortar(string $texto, int $limite): string
{
    return function_exists('mb_substr')
        ? (string) mb_substr($texto, 0, $limite, 'UTF-8')
        : substr($texto, 0, $limite);
}

function sol_recortar_nullable(?string $texto, int $limite): ?string
{
    return $texto === null ? null : sol_recortar($texto, $limite);
}

function sol_ip(): ?string
{
    return function_exists('sm_ip_cliente') ? sm_ip_cliente() : sol_nullable($_SERVER['REMOTE_ADDR'] ?? null);
}

function sol_user_agent(): ?string
{
    return function_exists('sm_user_agent') ? sm_user_agent() : sol_nullable($_SERVER['HTTP_USER_AGENT'] ?? null);
}