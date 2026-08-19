<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Equipos - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| Catálogo final de la estructura Departamento -> Área -> Proceso -> Equipo.
| Permite alta, edición, desactivación y reactivación con auditoría.
| No elimina registros y protege código/ubicación cuando existe historial.
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
    error_log('[EQUIPOS][PDO CONFIG] ' . $e->getMessage());
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = strtoupper(equipo_texto(
    $metodo === 'GET'
        ? ($_GET['accion'] ?? 'INICIAL')
        : ($_POST['accion'] ?? '')
));

try {
    equipo_validar_admin_activo($conexion, equipo_admin_id());

    if ($metodo === 'GET') {
        sm_requerir_metodo('GET');

        if ($accion === 'INICIAL' || $accion === 'LISTAR') {
            equipo_endpoint_listar($conexion);
        }

        if ($accion === 'DETALLE') {
            equipo_endpoint_detalle($conexion);
        }

        sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
    }

    sm_requerir_metodo('POST');
    sm_validar_csrf();

    if ($accion === 'GUARDAR') {
        equipo_endpoint_guardar($conexion);
    }

    if ($accion === 'CAMBIAR_ESTADO') {
        equipo_endpoint_cambiar_estado($conexion);
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'EQUIPO-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][EQUIPOS][PDO] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    if ((string) $e->getCode() === '23000') {
        sm_responder_json(
            false,
            'El código ya está registrado o existe otro equipo con datos que generan un conflicto.',
            ['referencia' => $referencia],
            409
        );
    }

    sm_responder_json(
        false,
        'No fue posible procesar el catálogo de equipos.',
        ['referencia' => $referencia],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'EQUIPO-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][EQUIPOS] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar el equipo.',
        ['referencia' => $referencia],
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function equipo_endpoint_listar(PDO $conexion): void
{
    $sql = "SELECT
                e.id,
                e.codigo_equipo,
                e.nombre_equipo,
                e.descripcion,
                e.departamento_id,
                e.area_id,
                e.proceso_id,
                e.activo,
                e.fecha_registro,
                DATE_FORMAT(e.fecha_registro, '%d/%m/%Y') AS fecha_registro_texto,
                COALESCE(d.nombre, 'Departamento no disponible') AS departamento,
                COALESCE(d.activo, 0) AS departamento_activo,
                COALESCE(a.nombre, 'Área no disponible') AS area,
                COALESCE(a.activo, 0) AS area_activa,
                COALESCE(p.nombre, 'Proceso no disponible') AS proceso,
                COALESCE(p.activo, 0) AS proceso_activo,
                COALESCE(wh.solicitudes_abiertas, 0) AS solicitudes_abiertas,
                COALESCE(rh.rutinas_activas, 0) AS rutinas_activas,
                COALESCE(wt.solicitudes_totales, 0) AS solicitudes_totales,
                COALESCE(rt.rutinas_totales, 0) AS rutinas_totales
            FROM equipos e
            LEFT JOIN departamentos d ON d.id = e.departamento_id
            LEFT JOIN areas a ON a.id = e.area_id
            LEFT JOIN procesos p ON p.id = e.proceso_id
            LEFT JOIN (
                SELECT equipo_id, COUNT(*) AS solicitudes_abiertas
                FROM solicitudes
                WHERE activo = 1
                  AND estado IN (
                      'PENDIENTE','APROBADO','AGENDADO','EN_PROCESO',
                      'PAUSADO','ATRASADO'
                  )
                GROUP BY equipo_id
            ) wh ON wh.equipo_id = e.id
            LEFT JOIN (
                SELECT equipo_id, COUNT(*) AS rutinas_activas
                FROM rutinas_mantenimiento
                WHERE activo = 1
                GROUP BY equipo_id
            ) rh ON rh.equipo_id = e.id
            LEFT JOIN (
                SELECT equipo_id, COUNT(*) AS solicitudes_totales
                FROM solicitudes
                GROUP BY equipo_id
            ) wt ON wt.equipo_id = e.id
            LEFT JOIN (
                SELECT equipo_id, COUNT(*) AS rutinas_totales
                FROM rutinas_mantenimiento
                GROUP BY equipo_id
            ) rt ON rt.equipo_id = e.id
            ORDER BY e.activo DESC, departamento ASC, area ASC, proceso ASC,
                     e.nombre_equipo ASC, e.id ASC";

    $equipos = $conexion->query($sql)->fetchAll();
    $activos = 0;
    $inactivos = 0;
    $enUso = 0;
    $ubicacionNoDisponible = 0;

    foreach ($equipos as &$equipo) {
        equipo_convertir_fila($equipo);

        $equipo['total_relaciones_activas'] =
            $equipo['solicitudes_abiertas'] + $equipo['rutinas_activas'];
        $equipo['total_relaciones_historicas'] =
            $equipo['solicitudes_totales'] + $equipo['rutinas_totales'];
        $equipo['ubicacion_disponible'] =
            $equipo['departamento_id'] > 0
            && $equipo['area_id'] > 0
            && $equipo['proceso_id'] > 0
            && $equipo['departamento_activo'] === 1
            && $equipo['area_activa'] === 1
            && $equipo['proceso_activo'] === 1
            ? 1 : 0;
        $equipo['puede_desactivar'] =
            $equipo['total_relaciones_activas'] === 0 ? 1 : 0;
        $equipo['identidad_protegida'] =
            $equipo['total_relaciones_historicas'] > 0 ? 1 : 0;

        if ($equipo['activo'] === 1) {
            $activos++;
        } else {
            $inactivos++;
        }

        if ($equipo['total_relaciones_activas'] > 0) {
            $enUso++;
        }

        if ($equipo['ubicacion_disponible'] !== 1) {
            $ubicacionNoDisponible++;
        }
    }
    unset($equipo);

    sm_responder_json(
        true,
        'Equipos cargados correctamente.',
        [
            'equipos' => $equipos,
            'catalogos' => equipo_listar_catalogos($conexion),
            'resumen' => [
                'total' => count($equipos),
                'activos' => $activos,
                'inactivos' => $inactivos,
                'en_uso' => $enUso,
                'ubicacion_no_disponible' => $ubicacionNoDisponible,
            ],
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function equipo_endpoint_detalle(PDO $conexion): void
{
    $id = equipo_entero_positivo($_GET['id'] ?? null, 'equipo');
    $equipo = equipo_obtener_equipo($conexion, $id, false);

    if (!$equipo) {
        sm_responder_json(false, 'El equipo solicitado no existe.', [], 404);
    }

    $relaciones = equipo_obtener_relaciones_totales($conexion, $id);
    $equipo['relaciones_totales'] = $relaciones;
    $equipo['total_relaciones_historicas'] = array_sum($relaciones);
    $equipo['puede_cambiar_identidad'] =
        $equipo['total_relaciones_historicas'] === 0 ? 1 : 0;
    $equipo['ubicacion_disponible'] = equipo_ubicacion_activa(
        $conexion,
        (int) $equipo['departamento_id'],
        (int) $equipo['area_id'],
        (int) $equipo['proceso_id']
    ) ? 1 : 0;

    sm_responder_json(
        true,
        'Equipo cargado correctamente.',
        ['equipo' => $equipo]
    );
}

function equipo_endpoint_guardar(PDO $conexion): void
{
    $adminId = equipo_admin_id();
    $idEntrada = equipo_texto($_POST['equipo_id'] ?? '');
    $id = $idEntrada === '' ? 0 : equipo_entero_positivo($idEntrada, 'equipo');

    $codigoEntrada = equipo_texto($_POST['codigo_equipo'] ?? '');
    $codigo = equipo_validar_codigo($codigoEntrada, true);
    $nombre = equipo_validar_nombre($_POST['nombre_equipo'] ?? '');
    $descripcion = equipo_validar_descripcion($_POST['descripcion'] ?? '');
    $departamentoId = equipo_entero_positivo(
        $_POST['departamento_id'] ?? null,
        'departamento'
    );
    $areaId = equipo_entero_positivo($_POST['area_id'] ?? null, 'área');
    $procesoId = equipo_entero_positivo($_POST['proceso_id'] ?? null, 'proceso');

    $conexion->beginTransaction();

    if ($id === 0) {
        $ubicacion = equipo_obtener_ubicacion(
            $conexion,
            $departamentoId,
            $areaId,
            $procesoId,
            true
        );

        if (!$ubicacion || !equipo_ubicacion_fila_activa($ubicacion)) {
            equipo_responder_cancelando(
                $conexion,
                'La ubicación seleccionada no existe o contiene un departamento, área o proceso inactivo.',
                422,
                ['campo' => 'departamento_id']
            );
        }

        if ($codigo === '') {
            $codigo = equipo_generar_codigo_unico($conexion);
        }

        if (equipo_codigo_existe($conexion, $codigo, 0)) {
            equipo_responder_cancelando(
                $conexion,
                'El código del equipo ya está registrado.',
                409,
                ['campo' => 'codigo_equipo']
            );
        }

        if (equipo_nombre_existe($conexion, $nombre, $areaId, 0)) {
            equipo_responder_cancelando(
                $conexion,
                'Ya existe un equipo con ese nombre dentro del área seleccionada.',
                409,
                ['campo' => 'nombre_equipo']
            );
        }

        $stmt = $conexion->prepare(
            "INSERT INTO equipos
             (
                codigo_equipo, nombre_equipo, descripcion,
                departamento_id, area_id, proceso_id, activo, fecha_registro
             )
             VALUES
             (
                :codigo_equipo, :nombre_equipo, :descripcion,
                :departamento_id, :area_id, :proceso_id, 1, NOW()
             )"
        );
        $stmt->bindValue(':codigo_equipo', $codigo, PDO::PARAM_STR);
        $stmt->bindValue(':nombre_equipo', $nombre, PDO::PARAM_STR);
        equipo_bind_nullable($stmt, ':descripcion', $descripcion);
        $stmt->bindValue(':departamento_id', $departamentoId, PDO::PARAM_INT);
        $stmt->bindValue(':area_id', $areaId, PDO::PARAM_INT);
        $stmt->bindValue(':proceso_id', $procesoId, PDO::PARAM_INT);
        $stmt->execute();

        $id = (int) $conexion->lastInsertId();
        $nuevo = [
            'id' => $id,
            'codigo_equipo' => $codigo,
            'nombre_equipo' => $nombre,
            'descripcion' => $descripcion,
            'departamento_id' => $departamentoId,
            'departamento' => (string) $ubicacion['departamento'],
            'area_id' => $areaId,
            'area' => (string) $ubicacion['area'],
            'proceso_id' => $procesoId,
            'proceso' => (string) $ubicacion['proceso'],
            'activo' => 1,
        ];

        equipo_registrar_auditoria(
            $conexion,
            $adminId,
            'INSERT',
            $id,
            'Registro inicial del equipo.',
            null,
            $nuevo
        );
        equipo_registrar_movimiento(
            $conexion,
            $adminId,
            'CREAR_EQUIPO',
            'Se registró el equipo "' . $codigo . ' - ' . $nombre
                . '" en el proceso "' . (string) $ubicacion['proceso']
                . '" del área "' . (string) $ubicacion['area'] . '".',
            $id
        );

        $conexion->commit();
        sm_responder_json(
            true,
            'Equipo registrado correctamente.',
            ['id' => $id, 'codigo_equipo' => $codigo]
        );
    }

    $anterior = equipo_obtener_equipo($conexion, $id, true);
    if (!$anterior) {
        equipo_responder_cancelando(
            $conexion,
            'El equipo que intentas editar ya no existe.',
            404
        );
    }

    $ubicacionDestino = equipo_obtener_ubicacion(
        $conexion,
        $departamentoId,
        $areaId,
        $procesoId,
        true
    );
    if (!$ubicacionDestino) {
        equipo_responder_cancelando(
            $conexion,
            'La ubicación seleccionada ya no existe.',
            422,
            ['campo' => 'departamento_id']
        );
    }

    $codigoAnterior = strtoupper(trim((string) ($anterior['codigo_equipo'] ?? '')));
    if ($codigo === '') {
        $codigo = $codigoAnterior !== ''
            ? $codigoAnterior
            : equipo_generar_codigo_unico($conexion);
    }

    $cambiaCodigo = $codigoAnterior !== $codigo;
    $cambiaUbicacion =
        (int) $anterior['departamento_id'] !== $departamentoId
        || (int) $anterior['area_id'] !== $areaId
        || (int) $anterior['proceso_id'] !== $procesoId;

    $relacionesHistoricas = equipo_obtener_relaciones_totales($conexion, $id);
    $totalHistorico = array_sum($relacionesHistoricas);

    if (($cambiaCodigo || $cambiaUbicacion) && $totalHistorico > 0) {
        $campo = $cambiaCodigo ? 'codigo_equipo' : 'departamento_id';
        equipo_responder_cancelando(
            $conexion,
            'No se puede cambiar '
                . ($cambiaCodigo && $cambiaUbicacion
                    ? 'el código ni la ubicación'
                    : ($cambiaCodigo ? 'el código' : 'la ubicación'))
                . ' porque el equipo ya tiene registros históricos relacionados: '
                . equipo_describir_dependencias($relacionesHistoricas)
                . '. Puedes corregir el nombre y la descripción.',
            409,
            [
                'campo' => $campo,
                'dependencias' => $relacionesHistoricas,
                'total_dependencias' => $totalHistorico,
            ]
        );
    }

    if ($cambiaUbicacion && !equipo_ubicacion_fila_activa($ubicacionDestino)) {
        equipo_responder_cancelando(
            $conexion,
            'La nueva ubicación contiene un departamento, área o proceso inactivo.',
            422,
            ['campo' => 'departamento_id']
        );
    }

    if (
        !$cambiaUbicacion
        && (int) $anterior['activo'] === 1
        && !equipo_ubicacion_fila_activa($ubicacionDestino)
    ) {
        equipo_responder_cancelando(
            $conexion,
            'Un equipo activo debe pertenecer a un departamento, área y proceso activos.',
            409,
            ['campo' => 'departamento_id']
        );
    }

    if (equipo_codigo_existe($conexion, $codigo, $id)) {
        equipo_responder_cancelando(
            $conexion,
            'El código del equipo ya está registrado.',
            409,
            ['campo' => 'codigo_equipo']
        );
    }

    if (equipo_nombre_existe($conexion, $nombre, $areaId, $id)) {
        equipo_responder_cancelando(
            $conexion,
            'Ya existe otro equipo con ese nombre dentro del área seleccionada.',
            409,
            ['campo' => 'nombre_equipo']
        );
    }

    $sinCambios = $codigoAnterior === $codigo
        && (string) $anterior['nombre_equipo'] === $nombre
        && equipo_nullable_igual($anterior['descripcion'], $descripcion)
        && (int) $anterior['departamento_id'] === $departamentoId
        && (int) $anterior['area_id'] === $areaId
        && (int) $anterior['proceso_id'] === $procesoId;

    if ($sinCambios) {
        $conexion->commit();
        sm_responder_json(
            true,
            'No se detectaron cambios en el equipo.',
            ['id' => $id, 'sin_cambios' => true]
        );
    }

    $stmt = $conexion->prepare(
        "UPDATE equipos
         SET codigo_equipo = :codigo_equipo,
             nombre_equipo = :nombre_equipo,
             descripcion = :descripcion,
             departamento_id = :departamento_id,
             area_id = :area_id,
             proceso_id = :proceso_id
         WHERE id = :id"
    );
    $stmt->bindValue(':codigo_equipo', $codigo, PDO::PARAM_STR);
    $stmt->bindValue(':nombre_equipo', $nombre, PDO::PARAM_STR);
    equipo_bind_nullable($stmt, ':descripcion', $descripcion);
    $stmt->bindValue(':departamento_id', $departamentoId, PDO::PARAM_INT);
    $stmt->bindValue(':area_id', $areaId, PDO::PARAM_INT);
    $stmt->bindValue(':proceso_id', $procesoId, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $nuevo = equipo_datos_auditoria($anterior);
    $nuevo['codigo_equipo'] = $codigo;
    $nuevo['nombre_equipo'] = $nombre;
    $nuevo['descripcion'] = $descripcion;
    $nuevo['departamento_id'] = $departamentoId;
    $nuevo['departamento'] = (string) $ubicacionDestino['departamento'];
    $nuevo['area_id'] = $areaId;
    $nuevo['area'] = (string) $ubicacionDestino['area'];
    $nuevo['proceso_id'] = $procesoId;
    $nuevo['proceso'] = (string) $ubicacionDestino['proceso'];

    equipo_registrar_auditoria(
        $conexion,
        $adminId,
        'UPDATE',
        $id,
        ($cambiaCodigo || $cambiaUbicacion)
            ? 'Actualización del equipo con cambio de identidad o ubicación.'
            : 'Actualización de datos descriptivos del equipo.',
        equipo_datos_auditoria($anterior),
        $nuevo
    );

    $cambios = [];
    if ($cambiaCodigo) {
        $cambios[] = 'código';
    }
    if ($cambiaUbicacion) {
        $cambios[] = 'ubicación';
    }
    if ((string) $anterior['nombre_equipo'] !== $nombre) {
        $cambios[] = 'nombre';
    }
    if (!equipo_nullable_igual($anterior['descripcion'], $descripcion)) {
        $cambios[] = 'descripción';
    }

    equipo_registrar_movimiento(
        $conexion,
        $adminId,
        'EDITAR_EQUIPO',
        'Se actualizó el equipo "' . $codigo . ' - ' . $nombre . '". Cambios: '
            . implode(', ', $cambios) . '.',
        $id
    );

    $conexion->commit();
    sm_responder_json(
        true,
        'Equipo actualizado correctamente.',
        ['id' => $id, 'codigo_equipo' => $codigo]
    );
}

function equipo_endpoint_cambiar_estado(PDO $conexion): void
{
    $adminId = equipo_admin_id();
    $id = equipo_entero_positivo($_POST['id'] ?? null, 'equipo');
    $activo = equipo_validar_estado($_POST['activo'] ?? null);

    $conexion->beginTransaction();

    $equipo = equipo_obtener_equipo($conexion, $id, true);
    if (!$equipo) {
        equipo_responder_cancelando(
            $conexion,
            'El equipo solicitado ya no existe.',
            404
        );
    }

    $estadoAnterior = (int) $equipo['activo'];
    if ($estadoAnterior === $activo) {
        $conexion->commit();
        sm_responder_json(
            true,
            $activo === 1
                ? 'El equipo ya se encontraba activo.'
                : 'El equipo ya se encontraba inactivo.',
            ['sin_cambios' => true]
        );
    }

    $codigoReactivacion = null;

    if ($activo === 0) {
        $dependencias = equipo_obtener_dependencias_activas($conexion, $id);
        $total = array_sum($dependencias);

        if ($total > 0) {
            equipo_responder_cancelando(
                $conexion,
                'No se puede desactivar el equipo porque todavía tiene registros activos relacionados: '
                    . equipo_describir_dependencias($dependencias)
                    . '. Primero finaliza, cancela o desactiva esas relaciones.',
                409,
                [
                    'dependencias' => $dependencias,
                    'total_dependencias' => $total,
                ]
            );
        }
    } else {
        if (!equipo_ubicacion_activa(
            $conexion,
            (int) $equipo['departamento_id'],
            (int) $equipo['area_id'],
            (int) $equipo['proceso_id']
        )) {
            equipo_responder_cancelando(
                $conexion,
                'No se puede reactivar el equipo porque su departamento, área o proceso está inactivo. Reactiva primero los catálogos superiores.',
                409
            );
        }

        $codigoActual = strtoupper(trim((string) $equipo['codigo_equipo']));
        if ($codigoActual === '') {
            $codigoReactivacion = equipo_generar_codigo_unico($conexion);
            $codigoActual = $codigoReactivacion;
        }

        if (equipo_codigo_existe(
            $conexion,
            $codigoActual,
            $id
        )) {
            equipo_responder_cancelando(
                $conexion,
                'No se puede reactivar el equipo porque otro registro utiliza el mismo código.',
                409
            );
        }

        if (equipo_nombre_existe(
            $conexion,
            (string) $equipo['nombre_equipo'],
            (int) $equipo['area_id'],
            $id
        )) {
            equipo_responder_cancelando(
                $conexion,
                'No se puede reactivar el equipo porque ya existe otro equipo con el mismo nombre dentro del área.',
                409
            );
        }
    }

    $stmt = $conexion->prepare(
        "UPDATE equipos
         SET activo = :activo,
             codigo_equipo = COALESCE(:codigo_reactivacion, codigo_equipo)
         WHERE id = :id AND activo = :estado_anterior"
    );
    $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);
    equipo_bind_nullable($stmt, ':codigo_reactivacion', $codigoReactivacion);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':estado_anterior', $estadoAnterior, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        equipo_responder_cancelando(
            $conexion,
            'El estado del equipo cambió mientras realizabas la operación. Actualiza la lista.',
            409
        );
    }

    $nuevo = equipo_datos_auditoria($equipo);
    $nuevo['activo'] = $activo;
    if ($codigoReactivacion !== null) {
        $nuevo['codigo_equipo'] = $codigoReactivacion;
        $equipo['codigo_equipo'] = $codigoReactivacion;
    }
    $reactivar = $activo === 1;

    equipo_registrar_auditoria(
        $conexion,
        $adminId,
        $reactivar ? 'REACTIVACION' : 'DELETE_LOGICO',
        $id,
        $reactivar
            ? 'Reactivación administrativa del equipo.'
            : 'Desactivación administrativa del equipo.',
        equipo_datos_auditoria($equipo),
        $nuevo
    );
    equipo_registrar_movimiento(
        $conexion,
        $adminId,
        $reactivar ? 'REACTIVAR_EQUIPO' : 'DESACTIVAR_EQUIPO',
        ($reactivar ? 'Se reactivó' : 'Se desactivó')
            . ' el equipo "' . (string) $equipo['codigo_equipo']
            . ' - ' . (string) $equipo['nombre_equipo'] . '".',
        $id
    );

    $conexion->commit();
    sm_responder_json(
        true,
        $reactivar
            ? 'Equipo reactivado correctamente.'
            : 'Equipo desactivado correctamente.'
    );
}

/* =========================================================================
   CONSULTAS Y RELACIONES
   ========================================================================= */

function equipo_listar_catalogos(PDO $conexion): array
{
    $departamentos = $conexion->query(
        "SELECT id, nombre, activo
         FROM departamentos
         ORDER BY activo DESC, nombre ASC, id ASC"
    )->fetchAll();

    $areas = $conexion->query(
        "SELECT
            a.id, a.departamento_id, a.nombre, a.activo,
            COALESCE(d.activo, 0) AS departamento_activo
         FROM areas a
         LEFT JOIN departamentos d ON d.id = a.departamento_id
         ORDER BY a.activo DESC, a.nombre ASC, a.id ASC"
    )->fetchAll();

    $procesos = $conexion->query(
        "SELECT
            p.id, p.area_id, p.nombre, p.activo,
            COALESCE(a.departamento_id, 0) AS departamento_id,
            COALESCE(a.activo, 0) AS area_activa,
            COALESCE(d.activo, 0) AS departamento_activo
         FROM procesos p
         LEFT JOIN areas a ON a.id = p.area_id
         LEFT JOIN departamentos d ON d.id = a.departamento_id
         ORDER BY p.activo DESC, p.nombre ASC, p.id ASC"
    )->fetchAll();

    foreach ($departamentos as &$departamento) {
        $departamento['id'] = (int) ($departamento['id'] ?? 0);
        $departamento['activo'] = (int) ($departamento['activo'] ?? 0);
    }
    unset($departamento);

    foreach ($areas as &$area) {
        foreach (['id', 'departamento_id', 'activo', 'departamento_activo'] as $campo) {
            $area[$campo] = (int) ($area[$campo] ?? 0);
        }
    }
    unset($area);

    foreach ($procesos as &$proceso) {
        foreach (
            ['id', 'area_id', 'departamento_id', 'activo', 'area_activa', 'departamento_activo']
            as $campo
        ) {
            $proceso[$campo] = (int) ($proceso[$campo] ?? 0);
        }
    }
    unset($proceso);

    return [
        'departamentos' => $departamentos,
        'areas' => $areas,
        'procesos' => $procesos,
    ];
}

function equipo_obtener_equipo(PDO $conexion, int $id, bool $bloquear): ?array
{
    if ($bloquear) {
        $lock = $conexion->prepare(
            'SELECT id FROM equipos WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $lock->bindValue(':id', $id, PDO::PARAM_INT);
        $lock->execute();

        if (!$lock->fetchColumn()) {
            return null;
        }
    }

    $stmt = $conexion->prepare(
        "SELECT
            e.id,
            e.codigo_equipo,
            e.nombre_equipo,
            e.descripcion,
            e.departamento_id,
            e.area_id,
            e.proceso_id,
            e.activo,
            e.fecha_registro,
            COALESCE(d.nombre, 'Departamento no disponible') AS departamento,
            COALESCE(d.activo, 0) AS departamento_activo,
            COALESCE(a.nombre, 'Área no disponible') AS area,
            COALESCE(a.activo, 0) AS area_activa,
            COALESCE(p.nombre, 'Proceso no disponible') AS proceso,
            COALESCE(p.activo, 0) AS proceso_activo
         FROM equipos e
         LEFT JOIN departamentos d ON d.id = e.departamento_id
         LEFT JOIN areas a ON a.id = e.area_id
         LEFT JOIN procesos p ON p.id = e.proceso_id
         WHERE e.id = :id
         LIMIT 1"
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch();

    if (!$fila) {
        return null;
    }

    equipo_convertir_fila($fila);
    return $fila;
}

function equipo_obtener_ubicacion(
    PDO $conexion,
    int $departamentoId,
    int $areaId,
    int $procesoId,
    bool $bloquear
): ?array {
    if ($bloquear) {
        $lock = $conexion->prepare(
            'SELECT id FROM procesos WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $lock->bindValue(':id', $procesoId, PDO::PARAM_INT);
        $lock->execute();
    }

    $stmt = $conexion->prepare(
        "SELECT
            d.id AS departamento_id,
            d.nombre AS departamento,
            d.activo AS departamento_activo,
            a.id AS area_id,
            a.nombre AS area,
            a.activo AS area_activa,
            p.id AS proceso_id,
            p.nombre AS proceso,
            p.activo AS proceso_activo
         FROM departamentos d
         INNER JOIN areas a ON a.departamento_id = d.id
         INNER JOIN procesos p ON p.area_id = a.id
         WHERE d.id = :departamento_id
           AND a.id = :area_id
           AND p.id = :proceso_id
         LIMIT 1"
    );
    $stmt->bindValue(':departamento_id', $departamentoId, PDO::PARAM_INT);
    $stmt->bindValue(':area_id', $areaId, PDO::PARAM_INT);
    $stmt->bindValue(':proceso_id', $procesoId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch();

    if (!$fila) {
        return null;
    }

    foreach (
        [
            'departamento_id', 'departamento_activo', 'area_id',
            'area_activa', 'proceso_id', 'proceso_activo'
        ] as $campo
    ) {
        $fila[$campo] = (int) ($fila[$campo] ?? 0);
    }

    return $fila;
}

function equipo_ubicacion_fila_activa(array $ubicacion): bool
{
    return (int) ($ubicacion['departamento_activo'] ?? 0) === 1
        && (int) ($ubicacion['area_activa'] ?? 0) === 1
        && (int) ($ubicacion['proceso_activo'] ?? 0) === 1;
}

function equipo_ubicacion_activa(
    PDO $conexion,
    int $departamentoId,
    int $areaId,
    int $procesoId
): bool {
    $ubicacion = equipo_obtener_ubicacion(
        $conexion,
        $departamentoId,
        $areaId,
        $procesoId,
        false
    );

    return $ubicacion !== null && equipo_ubicacion_fila_activa($ubicacion);
}

function equipo_codigo_existe(PDO $conexion, string $codigo, int $exceptoId): bool
{
    $sql = 'SELECT COUNT(*) FROM equipos WHERE codigo_equipo = :codigo';
    if ($exceptoId > 0) {
        $sql .= ' AND id <> :id';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
    if ($exceptoId > 0) {
        $stmt->bindValue(':id', $exceptoId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function equipo_nombre_existe(
    PDO $conexion,
    string $nombre,
    int $areaId,
    int $exceptoId
): bool {
    $sql = "SELECT COUNT(*)
            FROM equipos
            WHERE nombre_equipo = :nombre
              AND area_id = :area_id";
    if ($exceptoId > 0) {
        $sql .= ' AND id <> :id';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':area_id', $areaId, PDO::PARAM_INT);
    if ($exceptoId > 0) {
        $stmt->bindValue(':id', $exceptoId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function equipo_generar_codigo_unico(PDO $conexion): string
{
    for ($intento = 0; $intento < 25; $intento++) {
        try {
            $aleatorio = strtoupper(bin2hex(random_bytes(3)));
        } catch (Throwable $e) {
            $aleatorio = strtoupper(substr(sha1(uniqid('', true)), 0, 6));
        }

        $codigo = 'EQ-' . date('Ymd') . '-' . $aleatorio;
        if (!equipo_codigo_existe($conexion, $codigo, 0)) {
            return $codigo;
        }
    }

    throw new RuntimeException('No fue posible generar un código único para el equipo.');
}

function equipo_obtener_dependencias_activas(PDO $conexion, int $id): array
{
    $stmt = $conexion->prepare(
        "SELECT
            COALESCE((
                SELECT COUNT(*)
                FROM solicitudes s
                WHERE s.equipo_id = :id_solicitudes
                  AND s.activo = 1
                  AND s.estado IN (
                      'PENDIENTE','APROBADO','AGENDADO','EN_PROCESO',
                      'PAUSADO','ATRASADO'
                  )
            ), 0) AS solicitudes_abiertas,
            COALESCE((
                SELECT COUNT(*)
                FROM rutinas_mantenimiento r
                WHERE r.equipo_id = :id_rutinas
                  AND r.activo = 1
            ), 0) AS rutinas_activas"
    );
    $stmt->bindValue(':id_solicitudes', $id, PDO::PARAM_INT);
    $stmt->bindValue(':id_rutinas', $id, PDO::PARAM_INT);
    $stmt->execute();

    return equipo_convertir_dependencias_activas($stmt->fetch() ?: []);
}

function equipo_obtener_relaciones_totales(PDO $conexion, int $id): array
{
    $stmt = $conexion->prepare(
        "SELECT
            COALESCE((
                SELECT COUNT(*) FROM solicitudes WHERE equipo_id = :id_solicitudes
            ), 0) AS solicitudes,
            COALESCE((
                SELECT COUNT(*) FROM rutinas_mantenimiento WHERE equipo_id = :id_rutinas
            ), 0) AS rutinas"
    );
    $stmt->bindValue(':id_solicitudes', $id, PDO::PARAM_INT);
    $stmt->bindValue(':id_rutinas', $id, PDO::PARAM_INT);
    $stmt->execute();

    return equipo_convertir_dependencias_totales($stmt->fetch() ?: []);
}

function equipo_convertir_dependencias_activas(array $fila): array
{
    return [
        'solicitudes_abiertas' => (int) ($fila['solicitudes_abiertas'] ?? 0),
        'rutinas_activas' => (int) ($fila['rutinas_activas'] ?? 0),
    ];
}

function equipo_convertir_dependencias_totales(array $fila): array
{
    return [
        'solicitudes' => (int) ($fila['solicitudes'] ?? 0),
        'rutinas' => (int) ($fila['rutinas'] ?? 0),
    ];
}

function equipo_convertir_fila(array &$equipo): void
{
    foreach (
        [
            'id', 'departamento_id', 'area_id', 'proceso_id', 'activo',
            'departamento_activo', 'area_activa', 'proceso_activo',
            'solicitudes_abiertas', 'rutinas_activas',
            'solicitudes_totales', 'rutinas_totales'
        ] as $campo
    ) {
        if (array_key_exists($campo, $equipo)) {
            $equipo[$campo] = (int) ($equipo[$campo] ?? 0);
        }
    }

    $equipo['codigo_equipo'] = (string) ($equipo['codigo_equipo'] ?? '');
    $equipo['nombre_equipo'] = (string) ($equipo['nombre_equipo'] ?? '');
}

/* =========================================================================
   MOVIMIENTOS Y AUDITORÍA
   ========================================================================= */

function equipo_registrar_movimiento(
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
            'ADMIN', :usuario_id, :accion, 'Equipos', :descripcion,
            'equipos', :registro_id, :ip_address, :user_agent,
            NOW()
        )"
    );
    $stmt->bindValue(':usuario_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', equipo_recortar($accion, 100), PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    equipo_bind_nullable($stmt, ':ip_address', equipo_ip());
    equipo_bind_nullable(
        $stmt,
        ':user_agent',
        equipo_recortar_nullable(equipo_user_agent(), 255)
    );
    $stmt->execute();
}

function equipo_registrar_auditoria(
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
            'equipos', :registro_id, NULL, 'ADMIN',
            :actor_id, :accion, :motivo, :anteriores, :nuevos,
            :ip_address, :user_agent, NOW()
        )"
    );
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':motivo', equipo_recortar($motivo, 500), PDO::PARAM_STR);
    equipo_bind_nullable($stmt, ':anteriores', equipo_json($anteriores));
    equipo_bind_nullable($stmt, ':nuevos', equipo_json($nuevos));
    equipo_bind_nullable($stmt, ':ip_address', equipo_ip());
    equipo_bind_nullable(
        $stmt,
        ':user_agent',
        equipo_recortar_nullable(equipo_user_agent(), 500)
    );
    $stmt->execute();
}

/* =========================================================================
   VALIDACIONES Y UTILIDADES
   ========================================================================= */

function equipo_validar_admin_activo(PDO $conexion, int $adminId): void
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
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?sesion=expirada',
            ],
            401
        );
    }
}

function equipo_validar_codigo($valor, bool $permitirVacio): string
{
    $codigo = strtoupper(trim(equipo_texto($valor)));
    $codigo = preg_replace('/\s+/u', '', $codigo) ?? $codigo;

    if ($codigo === '' && $permitirVacio) {
        return '';
    }

    $longitud = equipo_longitud($codigo);
    if ($longitud < 3 || $longitud > 50) {
        sm_responder_json(
            false,
            'El código debe contener entre 3 y 50 caracteres.',
            ['campo' => 'codigo_equipo'],
            422
        );
    }

    if (!preg_match('/^[A-Z0-9._-]+$/', $codigo)) {
        sm_responder_json(
            false,
            'El código sólo puede contener letras, números, punto, guion y guion bajo.',
            ['campo' => 'codigo_equipo'],
            422
        );
    }

    return $codigo;
}

function equipo_validar_nombre($valor): string
{
    $nombre = preg_replace('/\s+/u', ' ', equipo_texto($valor)) ?? '';
    $nombre = trim($nombre);
    $longitud = equipo_longitud($nombre);

    if ($longitud < 2 || $longitud > 150) {
        sm_responder_json(
            false,
            'El nombre debe contener entre 2 y 150 caracteres.',
            ['campo' => 'nombre_equipo'],
            422
        );
    }

    if (
        !preg_match('/^[\p{L}\p{M}\p{N} .,&()\/\-\'’_:]+$/u', $nombre)
        || !preg_match('/[\p{L}\p{N}]/u', $nombre)
    ) {
        sm_responder_json(
            false,
            'El nombre contiene caracteres no permitidos.',
            ['campo' => 'nombre_equipo'],
            422
        );
    }

    return $nombre;
}

function equipo_validar_descripcion($valor): ?string
{
    $descripcion = equipo_texto($valor);
    $descripcion = str_replace(["\r\n", "\r"], "\n", $descripcion);
    $descripcion = trim($descripcion);

    if ($descripcion === '') {
        return null;
    }

    if (equipo_longitud($descripcion) > 800) {
        sm_responder_json(
            false,
            'La descripción no puede superar los 800 caracteres.',
            ['campo' => 'descripcion'],
            422
        );
    }

    return $descripcion;
}

function equipo_validar_estado($valor): int
{
    $estado = equipo_texto($valor);
    if ($estado !== '0' && $estado !== '1') {
        sm_responder_json(
            false,
            'El estado solicitado no es válido.',
            ['campo' => 'activo'],
            422
        );
    }
    return (int) $estado;
}

function equipo_entero_positivo($valor, string $campo): int
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
            ['campo' => str_replace('á', 'a', $campo) . '_id'],
            422
        );
    }

    return (int) $entero;
}

function equipo_admin_id(): int
{
    $id = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || (int) $id < 1) {
        sm_responder_json(false, 'Tu sesión administrativa no es válida.', [], 401);
    }
    return (int) $id;
}

function equipo_texto($valor): string
{
    return sm_limpiar_texto($valor);
}

function equipo_describir_dependencias(array $dependencias): string
{
    $etiquetas = [
        'solicitudes_abiertas' => ['solicitud abierta', 'solicitudes abiertas'],
        'rutinas_activas' => ['rutina activa', 'rutinas activas'],
        'solicitudes' => ['solicitud', 'solicitudes'],
        'rutinas' => ['rutina', 'rutinas'],
    ];
    $partes = [];

    foreach ($dependencias as $clave => $cantidad) {
        $cantidad = (int) $cantidad;
        if ($cantidad > 0 && isset($etiquetas[$clave])) {
            $partes[] = $cantidad . ' '
                . $etiquetas[$clave][$cantidad === 1 ? 0 : 1];
        }
    }

    return $partes === [] ? 'sin relaciones' : implode(', ', $partes);
}

function equipo_datos_auditoria(array $equipo): array
{
    return [
        'id' => (int) ($equipo['id'] ?? 0),
        'codigo_equipo' => (string) ($equipo['codigo_equipo'] ?? ''),
        'nombre_equipo' => (string) ($equipo['nombre_equipo'] ?? ''),
        'descripcion' => $equipo['descripcion'] ?? null,
        'departamento_id' => (int) ($equipo['departamento_id'] ?? 0),
        'departamento' => (string) ($equipo['departamento'] ?? ''),
        'area_id' => (int) ($equipo['area_id'] ?? 0),
        'area' => (string) ($equipo['area'] ?? ''),
        'proceso_id' => (int) ($equipo['proceso_id'] ?? 0),
        'proceso' => (string) ($equipo['proceso'] ?? ''),
        'activo' => (int) ($equipo['activo'] ?? 0),
    ];
}

function equipo_nullable_igual($a, $b): bool
{
    $a = $a === null || trim((string) $a) === '' ? null : (string) $a;
    $b = $b === null || trim((string) $b) === '' ? null : (string) $b;
    return $a === $b;
}

function equipo_bind_nullable(
    PDOStatement $stmt,
    string $parametro,
    ?string $valor
): void {
    $stmt->bindValue(
        $parametro,
        $valor,
        $valor === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );
}

function equipo_responder_cancelando(
    PDO $conexion,
    string $mensaje,
    int $codigo = 422,
    array $datos = []
): void {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    sm_responder_json(false, $mensaje, $datos, $codigo);
}

function equipo_json(?array $datos): ?string
{
    if ($datos === null) {
        return null;
    }

    $json = json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    return $json === false ? '{}' : $json;
}

function equipo_ip(): ?string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return $ip === '' ? null : equipo_recortar($ip, 45);
}

function equipo_user_agent(): ?string
{
    $agente = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return $agente === '' ? null : $agente;
}

function equipo_recortar(string $texto, int $maximo): string
{
    if (equipo_longitud($texto) <= $maximo) {
        return $texto;
    }
 
    if (function_exists('mb_substr')) {
        return (string) mb_substr($texto, 0, $maximo, 'UTF-8');
    }
    return substr($texto, 0, $maximo);
}

function equipo_recortar_nullable(?string $texto, int $maximo): ?string
{
    return $texto === null ? null : equipo_recortar($texto, $maximo);
}

function equipo_longitud(string $texto): int
{
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($texto, 'UTF-8');
    }
    return strlen($texto);
}