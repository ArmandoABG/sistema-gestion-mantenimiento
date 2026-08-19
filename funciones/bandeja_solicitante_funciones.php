<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bandeja del solicitante
|--------------------------------------------------------------------------
| Consulta únicamente solicitudes pertenecientes al solicitante autenticado.
| Todas las listas utilizan filtros y paginación desde MySQL.
| Este endpoint es de solo lectura.
| Compatible con PHP 7.4 o superior.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_sesion(['SOLICITANTE'], true);

if (!isset($conexion) || !($conexion instanceof PDO)) {
    sm_responder_json(
        false,
        'No fue posible conectar con la base de datos.',
        [],
        503
    );
}

try {
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conexion->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (Throwable $e) {
    error_log('[BANDEJA SOLICITANTE][PDO CONFIG] ' . $e->getMessage());
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = strtoupper(bs_texto($_GET['accion'] ?? 'LISTAR'));

try {
    sm_requerir_metodo('GET');

    $solicitanteId = bs_solicitante_id();
    $perfil = bs_obtener_perfil($conexion, $solicitanteId);

    if (!$perfil || (int) ($perfil['activo'] ?? 0) !== 1) {
        sm_destruir_sesion();
        sm_responder_json(
            false,
            'Tu cuenta solicitante ya no está disponible.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?acceso=no_disponible',
            ],
            401
        );
    }

    switch ($accion) {
        case 'LISTAR':
            bs_listar($conexion, $solicitanteId, $perfil);
            break;

        case 'DETALLE':
            bs_detalle($conexion, $solicitanteId);
            break;

        default:
            sm_responder_json(
                false,
                'La acción solicitada no es válida.',
                [],
                400
            );
    }
} catch (PDOException $e) {
    $referencia = 'BS-' . date('Ymd-His');

    error_log(
        '[' . $referencia . '][BANDEJA SOLICITANTE][PDO] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'No fue posible consultar tus solicitudes en este momento.',
        ['referencia' => $referencia],
        500
    );
} catch (Throwable $e) {
    $referencia = 'BS-' . date('Ymd-His');

    error_log(
        '[' . $referencia . '][BANDEJA SOLICITANTE] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'Ocurrió un error interno al consultar tus solicitudes.',
        ['referencia' => $referencia],
        500
    );
}

/**
 * @param array<string,mixed> $perfil
 */
function bs_listar(PDO $conexion, int $solicitanteId, array $perfil): void
{
    $filtros = bs_obtener_filtros();
    $consulta = bs_construir_filtros($solicitanteId, $filtros);

    $stmtTotal = $conexion->prepare(
        'SELECT COUNT(*) '
        . $consulta['from']
        . ' WHERE ' . $consulta['where']
    );
    bs_enlazar($stmtTotal, $consulta['params']);
    $stmtTotal->execute();

    $total = (int) $stmtTotal->fetchColumn();
    $totalPaginas = max(1, (int) ceil($total / $filtros['por_pagina']));
    $pagina = min($filtros['pagina'], $totalPaginas);
    $offset = ($pagina - 1) * $filtros['por_pagina'];

    $sql = "SELECT
                s.id,
                s.folio,
                s.tipo_solicitud,
                s.estado,
                s.prioridad,
                s.descripcion_solicitud,
                s.fecha_solicitud,
                s.hora_solicitud,
                s.fecha_actualizacion,
                d.nombre AS departamento,
                a.nombre AS area,
                p.nombre AS proceso,
                e.codigo_equipo,
                e.nombre_equipo,
                DATE_FORMAT(s.fecha_solicitud, '%d/%m/%Y') AS fecha_solicitud_texto,
                TIME_FORMAT(s.hora_solicitud, '%H:%i') AS hora_solicitud_texto,
                DATE_FORMAT(s.fecha_actualizacion, '%d/%m/%Y %H:%i') AS actualizacion_texto,
                DATE_FORMAT(pm.fecha_programada, '%d/%m/%Y') AS fecha_programada_texto,
                DATE_FORMAT(pm.fecha_limite, '%d/%m/%Y') AS fecha_limite_texto,
                pm.estado AS programacion_estado,
                DATE_FORMAT(cm.fecha_hora_cierre, '%d/%m/%Y %H:%i') AS fecha_cierre_texto,
                cm.trabajo_quedo,
                (
                    SELECT COUNT(*)
                    FROM solicitud_tecnicos st
                    WHERE st.solicitud_id = s.id
                      AND st.activo = 1
                      AND st.estado <> 'RETIRADO'
                ) AS tecnicos_asignados,
                (
                    SELECT MAX(hs.fecha_evento)
                    FROM historial_solicitudes hs
                    WHERE hs.solicitud_id = s.id
                ) AS ultima_actividad
            " . $consulta['from'] . "
            LEFT JOIN programaciones_mantenimiento pm
              ON pm.id = (
                    SELECT pm2.id
                    FROM programaciones_mantenimiento pm2
                    WHERE pm2.solicitud_id = s.id
                    ORDER BY pm2.es_actual DESC, pm2.id DESC
                    LIMIT 1
              )
            LEFT JOIN cierres_mantenimiento cm
              ON cm.id = (
                    SELECT cm2.id
                    FROM cierres_mantenimiento cm2
                    WHERE cm2.solicitud_id = s.id
                    ORDER BY cm2.id DESC
                    LIMIT 1
              )
            WHERE " . $consulta['where'] . "
            ORDER BY s.fecha_solicitud DESC, s.hora_solicitud DESC, s.id DESC
            LIMIT :limite OFFSET :offset";

    $stmt = $conexion->prepare($sql);
    bs_enlazar($stmt, $consulta['params']);
    $stmt->bindValue(':limite', $filtros['por_pagina'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($solicitudes as $indice => $solicitud) {
        $solicitudes[$indice] = bs_normalizar_listado($solicitud);
    }

    $resumen = bs_obtener_resumen($conexion, $solicitanteId);

    sm_responder_json(
        true,
        'Solicitudes cargadas correctamente.',
        [
            'solicitante' => [
                'id' => (int) $perfil['id'],
                'nombre' => bs_nombre_completo($perfil),
                'usuario' => (string) ($perfil['usuario'] ?? ''),
                'departamento' => (string) ($perfil['departamento'] ?? 'Sin departamento'),
            ],
            'resumen' => $resumen,
            'solicitudes' => $solicitudes,
            'filtros' => [
                'buscar' => $filtros['buscar'],
                'estado' => $filtros['estado'],
                'tipo' => $filtros['tipo'],
                'por_pagina' => $filtros['por_pagina'],
            ],
            'paginacion' => [
                'pagina' => $pagina,
                'por_pagina' => $filtros['por_pagina'],
                'total' => $total,
                'total_paginas' => $totalPaginas,
                'desde' => $total === 0 ? 0 : $offset + 1,
                'hasta' => $total === 0
                    ? 0
                    : min($offset + $filtros['por_pagina'], $total),
            ],
            'actualizado_en' => date('d/m/Y H:i'),
        ]
    );
}

function bs_detalle(PDO $conexion, int $solicitanteId): void
{
    $solicitudId = bs_entero_positivo(
        $_GET['id'] ?? null,
        'La solicitud seleccionada no es válida.'
    );

    $sql = "SELECT
                s.id,
                s.folio,
                s.tipo_solicitud,
                s.estado,
                s.prioridad,
                s.descripcion_solicitud,
                s.fecha_solicitud,
                s.hora_solicitud,
                s.fecha_sugerida,
                s.descripcion_falla,
                s.causa_desconocida_descripcion,
                s.costo_vs_beneficio,
                s.impacto_operacion,
                s.objetivo_mejora,
                s.resultado_esperado,
                s.justificacion_mejora,
                s.observaciones_solicitante,
                s.trabajo_peligroso,
                s.nivel_riesgo,
                s.requiere_paro_equipo,
                s.observaciones_revision,
                s.motivo_rechazo,
                s.motivo_ultima_edicion,
                s.fecha_revision,
                s.fecha_registro,
                s.fecha_actualizacion,
                d.nombre AS departamento,
                a.nombre AS area,
                p.nombre AS proceso,
                e.codigo_equipo,
                e.nombre_equipo,
                e.descripcion AS descripcion_equipo,
                tf.nombre AS tipo_falla,
                ca.nombre AS causa_averia,
                DATE_FORMAT(s.fecha_solicitud, '%d/%m/%Y') AS fecha_solicitud_texto,
                TIME_FORMAT(s.hora_solicitud, '%H:%i') AS hora_solicitud_texto,
                DATE_FORMAT(s.fecha_sugerida, '%d/%m/%Y') AS fecha_sugerida_texto,
                DATE_FORMAT(s.fecha_revision, '%d/%m/%Y %H:%i') AS fecha_revision_texto,
                DATE_FORMAT(s.fecha_actualizacion, '%d/%m/%Y %H:%i') AS actualizacion_texto,
                pm.id AS programacion_id,
                pm.estado AS programacion_estado,
                pm.es_actual AS programacion_actual,
                pm.motivo_programacion,
                pm.motivo_reprogramacion,
                pm.motivo_cancelacion,
                DATE_FORMAT(pm.fecha_programada, '%d/%m/%Y') AS fecha_programada_texto,
                DATE_FORMAT(pm.fecha_limite, '%d/%m/%Y') AS fecha_limite_texto,
                cm.id AS cierre_id,
                cm.trabajo_quedo,
                cm.descripcion_trabajo_realizado,
                cm.que_falto,
                cm.realizo_limpieza_area,
                cm.area_ordenada_libre_componentes,
                cm.observaciones_cierre,
                DATE_FORMAT(cm.fecha_hora_cierre, '%d/%m/%Y %H:%i') AS fecha_cierre_texto,
                DATE_FORMAT(ex.fecha_hora_inicio, '%d/%m/%Y %H:%i') AS fecha_inicio_texto,
                DATE_FORMAT(ex.fecha_hora_fin, '%d/%m/%Y %H:%i') AS fecha_fin_texto,
                ex.total_segundos_activos,
                ex.total_segundos_pausa,
                ex.ejecuciones_abiertas
            FROM solicitudes s
            LEFT JOIN departamentos d ON d.id = s.departamento_id
            LEFT JOIN areas a ON a.id = s.area_id
            LEFT JOIN procesos p ON p.id = s.proceso_id
            LEFT JOIN equipos e ON e.id = s.equipo_id
            LEFT JOIN tipos_falla tf ON tf.id = s.tipo_falla_id
            LEFT JOIN causas_averia ca ON ca.id = s.causa_averia_id
            LEFT JOIN programaciones_mantenimiento pm
              ON pm.id = (
                    SELECT pm2.id
                    FROM programaciones_mantenimiento pm2
                    WHERE pm2.solicitud_id = s.id
                    ORDER BY pm2.es_actual DESC, pm2.id DESC
                    LIMIT 1
              )
            LEFT JOIN cierres_mantenimiento cm
              ON cm.id = (
                    SELECT cm2.id
                    FROM cierres_mantenimiento cm2
                    WHERE cm2.solicitud_id = s.id
                    ORDER BY cm2.id DESC
                    LIMIT 1
              )
            LEFT JOIN (
                SELECT
                    solicitud_id,
                    MIN(fecha_hora_inicio) AS fecha_hora_inicio,
                    MAX(fecha_hora_fin) AS fecha_hora_fin,
                    COALESCE(SUM(total_segundos_activos), 0) AS total_segundos_activos,
                    COALESCE(SUM(total_segundos_pausa), 0) AS total_segundos_pausa,
                    COALESCE(SUM(CASE
                        WHEN estado IN ('EN_PROCESO', 'PAUSADA') THEN 1
                        ELSE 0
                    END), 0) AS ejecuciones_abiertas
                FROM ejecuciones_mantenimiento
                WHERE solicitud_id = :ejecucion_solicitud_id
                GROUP BY solicitud_id
            ) ex ON ex.solicitud_id = s.id
            WHERE s.id = :solicitud_id
              AND s.solicitante_id = :solicitante_id
              AND s.activo = 1
              AND s.tipo_solicitud IN (
                    'CORRECTIVO_PROGRAMABLE',
                    'MODIFICACION_MEJORA',
                    'CORRECTIVO_URGENTE'
              )
            LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':ejecucion_solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitante_id', $solicitanteId, PDO::PARAM_INT);
    $stmt->execute();

    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        sm_responder_json(
            false,
            'La solicitud no existe o no pertenece a tu cuenta.',
            [],
            404
        );
    }

    $tecnicos = bs_obtener_tecnicos($conexion, $solicitudId);
    $historial = bs_obtener_historial($conexion, $solicitudId);

    sm_responder_json(
        true,
        'Detalle cargado correctamente.',
        [
            'solicitud' => bs_normalizar_detalle($solicitud),
            'tecnicos' => $tecnicos,
            'historial' => $historial,
            'etapas' => bs_construir_etapas(
                (string) $solicitud['estado'],
                (string) $solicitud['tipo_solicitud']
            ),
        ]
    );
}

/**
 * @return array<string,mixed>|false
 */
function bs_obtener_perfil(PDO $conexion, int $solicitanteId)
{
    $stmt = $conexion->prepare(
        "SELECT
            s.id,
            s.usuario,
            s.nombre,
            s.apellido_paterno,
            s.apellido_materno,
            s.activo,
            d.nombre AS departamento
         FROM solicitantes s
         LEFT JOIN departamentos d ON d.id = s.departamento_id
         WHERE s.id = :id
         LIMIT 1"
    );
    $stmt->bindValue(':id', $solicitanteId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * @return array<string,int>
 */
function bs_obtener_resumen(PDO $conexion, int $solicitanteId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END), 0) AS revision,
            COALESCE(SUM(CASE
                WHEN estado IN ('APROBADO', 'AGENDADO', 'EN_PROCESO', 'PAUSADO', 'ATRASADO')
                THEN 1 ELSE 0 END), 0) AS seguimiento,
            COALESCE(SUM(CASE WHEN estado = 'TERMINADO' THEN 1 ELSE 0 END), 0) AS terminadas,
            COALESCE(SUM(CASE
                WHEN estado IN ('RECHAZADO', 'CANCELADO')
                THEN 1 ELSE 0 END), 0) AS cerradas
         FROM solicitudes
         WHERE solicitante_id = :solicitante_id
           AND activo = 1
           AND tipo_solicitud IN (
                'CORRECTIVO_PROGRAMABLE',
                'MODIFICACION_MEJORA',
                'CORRECTIVO_URGENTE'
           )"
    );
    $stmt->bindValue(':solicitante_id', $solicitanteId, PDO::PARAM_INT);
    $stmt->execute();

    $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total' => (int) ($fila['total'] ?? 0),
        'revision' => (int) ($fila['revision'] ?? 0),
        'seguimiento' => (int) ($fila['seguimiento'] ?? 0),
        'terminadas' => (int) ($fila['terminadas'] ?? 0),
        'cerradas' => (int) ($fila['cerradas'] ?? 0),
    ];
}

/**
 * @return array<int,array<string,mixed>>
 */
function bs_obtener_tecnicos(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            st.id,
            st.estado,
            st.origen,
            st.resultado_cumplimiento,
            st.fecha_asignacion,
            st.fecha_aceptacion,
            st.activo,
            t.nombre,
            t.apellido_paterno,
            t.apellido_materno,
            t.especialidad,
            t.turno,
            DATE_FORMAT(st.fecha_asignacion, '%d/%m/%Y %H:%i') AS fecha_asignacion_texto,
            DATE_FORMAT(st.fecha_aceptacion, '%d/%m/%Y %H:%i') AS fecha_aceptacion_texto
         FROM solicitud_tecnicos st
         INNER JOIN tecnicos t ON t.id = st.tecnico_id
         WHERE st.solicitud_id = :solicitud_id
           AND st.estado <> 'RETIRADO'
         ORDER BY st.activo DESC, st.fecha_asignacion ASC, st.id ASC"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $resultado = [];

    foreach ($filas as $fila) {
        $resultado[] = [
            'id' => (int) $fila['id'],
            'nombre' => bs_nombre_completo($fila),
            'especialidad' => (string) ($fila['especialidad'] ?? ''),
            'turno' => (string) ($fila['turno'] ?? ''),
            'estado' => (string) ($fila['estado'] ?? ''),
            'origen' => (string) ($fila['origen'] ?? ''),
            'resultado_cumplimiento' => (string) ($fila['resultado_cumplimiento'] ?? ''),
            'activo' => (int) ($fila['activo'] ?? 0) === 1,
            'fecha_asignacion' => (string) ($fila['fecha_asignacion_texto'] ?? ''),
            'fecha_aceptacion' => (string) ($fila['fecha_aceptacion_texto'] ?? ''),
        ];
    }

    return $resultado;
}

/**
 * @return array<int,array<string,string>>
 */
function bs_obtener_historial(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            evento,
            estado_anterior,
            estado_nuevo,
            descripcion,
            DATE_FORMAT(fecha_evento, '%d/%m/%Y %H:%i') AS fecha_evento_texto
         FROM historial_solicitudes
         WHERE solicitud_id = :solicitud_id
         ORDER BY fecha_evento DESC, id DESC
         LIMIT 14"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $resultado = [];

    foreach ($filas as $fila) {
        $resultado[] = [
            'evento' => (string) ($fila['evento'] ?? 'OTRO'),
            'estado_anterior' => (string) ($fila['estado_anterior'] ?? ''),
            'estado_nuevo' => (string) ($fila['estado_nuevo'] ?? ''),
            'descripcion' => (string) ($fila['descripcion'] ?? ''),
            'fecha' => (string) ($fila['fecha_evento_texto'] ?? ''),
        ];
    }

    return $resultado;
}

/**
 * @param array<string,mixed> $filtros
 * @return array{from:string,where:string,params:array<string,array{0:mixed,1:int}>}
 */
function bs_construir_filtros(int $solicitanteId, array $filtros): array
{
    $from = "FROM solicitudes s
             LEFT JOIN departamentos d ON d.id = s.departamento_id
             LEFT JOIN areas a ON a.id = s.area_id
             LEFT JOIN procesos p ON p.id = s.proceso_id
             LEFT JOIN equipos e ON e.id = s.equipo_id";

    $condiciones = [
        's.solicitante_id = :solicitante_id',
        's.activo = 1',
        "s.tipo_solicitud IN (
            'CORRECTIVO_PROGRAMABLE',
            'MODIFICACION_MEJORA',
            'CORRECTIVO_URGENTE'
        )",
    ];

    $params = [
        ':solicitante_id' => [$solicitanteId, PDO::PARAM_INT],
    ];

    if ($filtros['buscar'] !== '') {
        $buscar = '%' . $filtros['buscar'] . '%';
        $condiciones[] = "(
            s.folio LIKE :buscar_folio
            OR s.descripcion_solicitud LIKE :buscar_descripcion
            OR COALESCE(e.codigo_equipo, '') LIKE :buscar_codigo
            OR COALESCE(e.nombre_equipo, '') LIKE :buscar_equipo
            OR COALESCE(d.nombre, '') LIKE :buscar_departamento
            OR COALESCE(a.nombre, '') LIKE :buscar_area
            OR COALESCE(p.nombre, '') LIKE :buscar_proceso
        )";

        $params[':buscar_folio'] = [$buscar, PDO::PARAM_STR];
        $params[':buscar_descripcion'] = [$buscar, PDO::PARAM_STR];
        $params[':buscar_codigo'] = [$buscar, PDO::PARAM_STR];
        $params[':buscar_equipo'] = [$buscar, PDO::PARAM_STR];
        $params[':buscar_departamento'] = [$buscar, PDO::PARAM_STR];
        $params[':buscar_area'] = [$buscar, PDO::PARAM_STR];
        $params[':buscar_proceso'] = [$buscar, PDO::PARAM_STR];
    }

    if ($filtros['estado'] !== '') {
        $condiciones[] = 's.estado = :estado';
        $params[':estado'] = [$filtros['estado'], PDO::PARAM_STR];
    }

    if ($filtros['tipo'] !== '') {
        $condiciones[] = 's.tipo_solicitud = :tipo';
        $params[':tipo'] = [$filtros['tipo'], PDO::PARAM_STR];
    }

    return [
        'from' => $from,
        'where' => implode(' AND ', $condiciones),
        'params' => $params,
    ];
}

/**
 * @return array{buscar:string,estado:string,tipo:string,pagina:int,por_pagina:int}
 */
function bs_obtener_filtros(): array
{
    $buscar = bs_texto($_GET['buscar'] ?? '');

    if (bs_longitud($buscar) > 120) {
        sm_responder_json(
            false,
            'La búsqueda no puede superar los 120 caracteres.',
            ['campo' => 'buscar'],
            422
        );
    }

    $estadosPermitidos = [
        '',
        'PENDIENTE',
        'APROBADO',
        'AGENDADO',
        'EN_PROCESO',
        'PAUSADO',
        'ATRASADO',
        'TERMINADO',
        'RECHAZADO',
        'CANCELADO',
    ];
    $estado = strtoupper(bs_texto($_GET['estado'] ?? ''));

    if (!in_array($estado, $estadosPermitidos, true)) {
        $estado = '';
    }

    $tiposPermitidos = [
        '',
        'CORRECTIVO_PROGRAMABLE',
        'MODIFICACION_MEJORA',
        'CORRECTIVO_URGENTE',
    ];
    $tipo = strtoupper(bs_texto($_GET['tipo'] ?? ''));

    if (!in_array($tipo, $tiposPermitidos, true)) {
        $tipo = '';
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
    $porPagina = in_array((int) $porPagina, [10, 20, 40], true)
        ? (int) $porPagina
        : 10;

    return [
        'buscar' => $buscar,
        'estado' => $estado,
        'tipo' => $tipo,
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
    ];
}

/**
 * @param array<string,mixed> $fila
 * @return array<string,mixed>
 */
function bs_normalizar_listado(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'folio' => (string) ($fila['folio'] ?? ''),
        'tipo_solicitud' => (string) ($fila['tipo_solicitud'] ?? ''),
        'estado' => (string) ($fila['estado'] ?? ''),
        'prioridad' => (string) ($fila['prioridad'] ?? ''),
        'descripcion' => (string) ($fila['descripcion_solicitud'] ?? ''),
        'departamento' => (string) ($fila['departamento'] ?? ''),
        'area' => (string) ($fila['area'] ?? ''),
        'proceso' => (string) ($fila['proceso'] ?? ''),
        'codigo_equipo' => (string) ($fila['codigo_equipo'] ?? ''),
        'equipo' => (string) ($fila['nombre_equipo'] ?? ''),
        'fecha_solicitud' => (string) ($fila['fecha_solicitud_texto'] ?? ''),
        'hora_solicitud' => (string) ($fila['hora_solicitud_texto'] ?? ''),
        'actualizacion' => (string) ($fila['actualizacion_texto'] ?? ''),
        'fecha_programada' => (string) ($fila['fecha_programada_texto'] ?? ''),
        'fecha_limite' => (string) ($fila['fecha_limite_texto'] ?? ''),
        'programacion_estado' => (string) ($fila['programacion_estado'] ?? ''),
        'fecha_cierre' => (string) ($fila['fecha_cierre_texto'] ?? ''),
        'trabajo_quedo' => (string) ($fila['trabajo_quedo'] ?? ''),
        'tecnicos_asignados' => (int) ($fila['tecnicos_asignados'] ?? 0),
    ];
}

/**
 * @param array<string,mixed> $fila
 * @return array<string,mixed>
 */
function bs_normalizar_detalle(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'folio' => (string) ($fila['folio'] ?? ''),
        'tipo_solicitud' => (string) ($fila['tipo_solicitud'] ?? ''),
        'estado' => (string) ($fila['estado'] ?? ''),
        'prioridad' => (string) ($fila['prioridad'] ?? ''),
        'descripcion_solicitud' => (string) ($fila['descripcion_solicitud'] ?? ''),
        'fecha_solicitud' => (string) ($fila['fecha_solicitud_texto'] ?? ''),
        'hora_solicitud' => (string) ($fila['hora_solicitud_texto'] ?? ''),
        'fecha_sugerida' => (string) ($fila['fecha_sugerida_texto'] ?? ''),
        'actualizacion' => (string) ($fila['actualizacion_texto'] ?? ''),
        'departamento' => (string) ($fila['departamento'] ?? ''),
        'area' => (string) ($fila['area'] ?? ''),
        'proceso' => (string) ($fila['proceso'] ?? ''),
        'codigo_equipo' => (string) ($fila['codigo_equipo'] ?? ''),
        'equipo' => (string) ($fila['nombre_equipo'] ?? ''),
        'descripcion_equipo' => (string) ($fila['descripcion_equipo'] ?? ''),
        'tipo_falla' => (string) ($fila['tipo_falla'] ?? ''),
        'causa_averia' => (string) ($fila['causa_averia'] ?? ''),
        'descripcion_falla' => (string) ($fila['descripcion_falla'] ?? ''),
        'causa_desconocida_descripcion' => (string) ($fila['causa_desconocida_descripcion'] ?? ''),
        'costo_vs_beneficio' => (string) ($fila['costo_vs_beneficio'] ?? ''),
        'impacto_operacion' => (string) ($fila['impacto_operacion'] ?? ''),
        'objetivo_mejora' => (string) ($fila['objetivo_mejora'] ?? ''),
        'resultado_esperado' => (string) ($fila['resultado_esperado'] ?? ''),
        'justificacion_mejora' => (string) ($fila['justificacion_mejora'] ?? ''),
        'observaciones_solicitante' => (string) ($fila['observaciones_solicitante'] ?? ''),
        'trabajo_peligroso' => (int) ($fila['trabajo_peligroso'] ?? 0) === 1,
        'nivel_riesgo' => (string) ($fila['nivel_riesgo'] ?? ''),
        'requiere_paro_equipo' => (int) ($fila['requiere_paro_equipo'] ?? 0) === 1,
        'observaciones_revision' => (string) ($fila['observaciones_revision'] ?? ''),
        'motivo_rechazo' => (string) ($fila['motivo_rechazo'] ?? ''),
        'motivo_ultima_edicion' => (string) ($fila['motivo_ultima_edicion'] ?? ''),
        'fecha_revision' => (string) ($fila['fecha_revision_texto'] ?? ''),
        'programacion' => [
            'id' => $fila['programacion_id'] === null ? null : (int) $fila['programacion_id'],
            'estado' => (string) ($fila['programacion_estado'] ?? ''),
            'es_actual' => (int) ($fila['programacion_actual'] ?? 0) === 1,
            'fecha_programada' => (string) ($fila['fecha_programada_texto'] ?? ''),
            'fecha_limite' => (string) ($fila['fecha_limite_texto'] ?? ''),
            'motivo_programacion' => (string) ($fila['motivo_programacion'] ?? ''),
            'motivo_reprogramacion' => (string) ($fila['motivo_reprogramacion'] ?? ''),
            'motivo_cancelacion' => (string) ($fila['motivo_cancelacion'] ?? ''),
        ],
        'ejecucion' => [
            'fecha_inicio' => (string) ($fila['fecha_inicio_texto'] ?? ''),
            'fecha_fin' => (string) ($fila['fecha_fin_texto'] ?? ''),
            'segundos_activos' => (int) ($fila['total_segundos_activos'] ?? 0),
            'segundos_pausa' => (int) ($fila['total_segundos_pausa'] ?? 0),
            'abiertas' => (int) ($fila['ejecuciones_abiertas'] ?? 0),
        ],
        'cierre' => [
            'id' => $fila['cierre_id'] === null ? null : (int) $fila['cierre_id'],
            'fecha' => (string) ($fila['fecha_cierre_texto'] ?? ''),
            'trabajo_quedo' => (string) ($fila['trabajo_quedo'] ?? ''),
            'descripcion_trabajo_realizado' => (string) ($fila['descripcion_trabajo_realizado'] ?? ''),
            'que_falto' => (string) ($fila['que_falto'] ?? ''),
            'realizo_limpieza_area' => (int) ($fila['realizo_limpieza_area'] ?? 0) === 1,
            'area_ordenada_libre_componentes' => (int) ($fila['area_ordenada_libre_componentes'] ?? 0) === 1,
            'observaciones' => (string) ($fila['observaciones_cierre'] ?? ''),
        ],
    ];
}

/**
 * @return array<int,array{clave:string,etiqueta:string,estado:string}>
 */
function bs_construir_etapas(string $estado, string $tipo): array
{
    $esUrgente = $tipo === 'CORRECTIVO_URGENTE';
    $etapas = $esUrgente
        ? [
            ['clave' => 'ENVIADA', 'etiqueta' => 'Enviada', 'estado' => 'pendiente'],
            ['clave' => 'PUBLICADA', 'etiqueta' => 'Publicada', 'estado' => 'pendiente'],
            ['clave' => 'ACEPTADA', 'etiqueta' => 'Aceptada', 'estado' => 'pendiente'],
            ['clave' => 'ATENCION', 'etiqueta' => 'En atención', 'estado' => 'pendiente'],
            ['clave' => 'FINALIZADA', 'etiqueta' => 'Finalizada', 'estado' => 'pendiente'],
        ]
        : [
            ['clave' => 'ENVIADA', 'etiqueta' => 'Enviada', 'estado' => 'pendiente'],
            ['clave' => 'REVISADA', 'etiqueta' => 'En revisión', 'estado' => 'pendiente'],
            ['clave' => 'PROGRAMADA', 'etiqueta' => 'Programada', 'estado' => 'pendiente'],
            ['clave' => 'ATENCION', 'etiqueta' => 'En atención', 'estado' => 'pendiente'],
            ['clave' => 'FINALIZADA', 'etiqueta' => 'Finalizada', 'estado' => 'pendiente'],
        ];

    $etapas[0]['estado'] = 'completa';

    if ($estado === 'PENDIENTE') {
        $etapas[1]['estado'] = 'actual';
        return $etapas;
    }

    if ($estado === 'APROBADO') {
        if ($esUrgente) {
            $etapas[1]['estado'] = 'completa';
            $etapas[2]['estado'] = 'actual';
        } else {
            $etapas[1]['estado'] = 'actual';
            $etapas[1]['etiqueta'] = 'Aprobada';
        }
        return $etapas;
    }

    if ($estado === 'AGENDADO') {
        $etapas[1]['estado'] = 'completa';
        $etapas[2]['estado'] = 'actual';
        return $etapas;
    }

    if (in_array($estado, ['EN_PROCESO', 'PAUSADO'], true)) {
        $etapas[1]['estado'] = 'completa';
        $etapas[2]['estado'] = 'completa';
        $etapas[3]['estado'] = $estado === 'PAUSADO' ? 'alerta' : 'actual';
        $etapas[3]['etiqueta'] = $estado === 'PAUSADO' ? 'Pausada' : 'En atención';
        return $etapas;
    }

    if ($estado === 'ATRASADO') {
        $etapas[1]['estado'] = 'completa';
        $etapas[2]['estado'] = 'alerta';
        $etapas[2]['etiqueta'] = 'Atrasada';
        return $etapas;
    }

    if ($estado === 'TERMINADO') {
        foreach ($etapas as $indice => $etapa) {
            $etapas[$indice]['estado'] = 'completa';
        }
        return $etapas;
    }

    if (in_array($estado, ['RECHAZADO', 'CANCELADO'], true)) {
        $etapas[1]['estado'] = 'cerrada';
        $etapas[1]['etiqueta'] = $estado === 'RECHAZADO' ? 'Rechazada' : 'Cancelada';
        return $etapas;
    }

    $etapas[1]['estado'] = 'actual';
    return $etapas;
}

/**
 * @param array<string,array{0:mixed,1:int}> $params
 */
function bs_enlazar(PDOStatement $stmt, array $params): void
{
    foreach ($params as $nombre => $configuracion) {
        $stmt->bindValue($nombre, $configuracion[0], $configuracion[1]);
    }
}

function bs_solicitante_id(): int
{
    $id = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        sm_destruir_sesion();
        sm_responder_json(
            false,
            'Tu sesión no contiene una cuenta solicitante válida.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?sesion=expirada',
            ],
            401
        );
    }

    return (int) $id;
}

function bs_entero_positivo($valor, string $mensaje): int
{
    $id = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        sm_responder_json(false, $mensaje, [], 422);
    }

    return (int) $id;
}

function bs_texto($valor): string
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

function bs_longitud(string $texto): int
{
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($texto, 'UTF-8');
    }

    return strlen($texto);
}
 
/**
 * @param array<string,mixed> $fila
 */
function bs_nombre_completo(array $fila): string
{
    $partes = [
        trim((string) ($fila['nombre'] ?? '')),
        trim((string) ($fila['apellido_paterno'] ?? '')),
        trim((string) ($fila['apellido_materno'] ?? '')),
    ];

    $partes = array_values(array_filter($partes, static function ($parte): bool {
        return $parte !== '';
    }));

    return $partes === [] ? 'Solicitante' : implode(' ', $partes);
}