<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Historial técnico de mantenimientos
|--------------------------------------------------------------------------
| - MIS: trabajos en los que participó el técnico autenticado.
| - TODOS: una sola fila por mantenimiento finalizado, disponible como
|   referencia técnica para el resto del personal.
| - Separa recursos recomendados de recursos realmente utilizados.
| - Consulta y exportación únicamente; no modifica el historial.
| Compatible con PHP 7.4 o superior.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';
require_once __DIR__ . '/recursos_mantenimiento_servicio.php';

sm_requerir_sesion(['TECNICO'], true);
sm_requerir_metodo('GET');

if (!isset($conexion) || !($conexion instanceof PDO)) {
    sm_responder_json(false, 'No fue posible conectar con la base de datos.', [], 503);
}

try {
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[MANTENIMIENTOS FINALIZADOS][PDO CONFIG] ' . $e->getMessage());
}

$accion = strtoupper(sm_limpiar_texto($_GET['accion'] ?? 'INICIAL'));

try {
    if ($accion === 'INICIAL' || $accion === 'LISTAR') {
        mfin_cargar_listado($conexion);
    }

    if ($accion === 'DETALLE') {
        mfin_cargar_detalle($conexion);
    }

    if ($accion === 'EXPORTAR') {
        mfin_exportar_csv($conexion);
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (PDOException $e) {
    error_log('[MANTENIMIENTOS FINALIZADOS][PDO] ' . $e->getMessage());
    sm_responder_json(false, 'No fue posible consultar el historial de mantenimientos.', [], 500);
} catch (Throwable $e) {
    error_log('[MANTENIMIENTOS FINALIZADOS] ' . $e->getMessage());
    sm_responder_json(false, 'Ocurrió un error interno al consultar el historial.', [], 500);
}

/* =========================================================================
   LISTADO
   ========================================================================= */

function mfin_cargar_listado(PDO $conexion): void
{
    $tecnicoId = mfin_tecnico_id();
    $perfil = mfin_obtener_tecnico_activo($conexion, $tecnicoId);
    $filtros = mfin_leer_filtros();
    $consulta = mfin_construir_condiciones($tecnicoId, $filtros);

    $total = mfin_contar_resultados($conexion, $consulta['where'], $consulta['parametros']);
    $porPagina = (int) $filtros['por_pagina'];
    $totalPaginas = max(1, (int) ceil($total / max(1, $porPagina)));
    $pagina = min((int) $filtros['pagina'], $totalPaginas);
    $offset = max(0, ($pagina - 1) * $porPagina);
    $filtros['pagina'] = $pagina;

    $resumen = mfin_obtener_resumen($conexion, $consulta['where'], $consulta['parametros']);
    $registros = mfin_obtener_registros(
        $conexion,
        $consulta['where'],
        $consulta['parametros'],
        (string) $filtros['orden'],
        $porPagina,
        $offset
    );

    rsm_adjuntar_recursos_recomendados($conexion, $registros, 'equipo_id', false);
    rsm_adjuntar_resumen_recursos_utilizados($conexion, $registros);

    sm_responder_json(true, 'Historial actualizado correctamente.', [
        'perfil' => $perfil,
        'alcance' => $filtros['alcance'],
        'resumen' => $resumen,
        'registros' => $registros,
        'cancelaciones' => mfin_obtener_cancelaciones_tecnico($conexion, $tecnicoId),
        'filtros' => $filtros,
        'paginacion' => [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_registros' => $total,
            'total_paginas' => $totalPaginas,
            'desde' => $total === 0 ? 0 : $offset + 1,
            'hasta' => min($total, $offset + count($registros)),
        ],
        'catalogos' => mfin_catalogos_filtros(),
        'fecha_servidor' => date('Y-m-d H:i:s'),
    ]);
}

function mfin_desde_base(): string
{
    return "FROM solicitudes s
            INNER JOIN equipos e ON e.id = s.equipo_id
            INNER JOIN departamentos d ON d.id = s.departamento_id
            INNER JOIN areas a ON a.id = s.area_id
            INNER JOIN procesos p ON p.id = s.proceso_id
            INNER JOIN cierres_mantenimiento cm ON cm.solicitud_id = s.id
            LEFT JOIN programaciones_mantenimiento pm
                   ON pm.solicitud_id = s.id AND pm.es_actual = 1
            LEFT JOIN solicitud_tecnicos st_actual
                   ON st_actual.solicitud_id = s.id
                  AND st_actual.tecnico_id = :mfin_tecnico_select
                  AND st_actual.activo = 1
            LEFT JOIN (
                SELECT
                    solicitud_id,
                    MIN(id) AS ejecucion_id,
                    MIN(fecha_hora_inicio) AS fecha_hora_inicio,
                    MAX(fecha_hora_fin) AS fecha_hora_fin,
                    COALESCE(SUM(total_segundos_activos), 0) AS total_segundos_activos,
                    COALESCE(SUM(total_segundos_pausa), 0) AS total_segundos_pausa
                FROM ejecuciones_mantenimiento
                WHERE estado = 'TERMINADA'
                GROUP BY solicitud_id
            ) ex ON ex.solicitud_id = s.id
            LEFT JOIN tecnicos tc ON tc.id = cm.cerrado_por_tecnico_id
            LEFT JOIN administradores ac ON ac.id = cm.cerrado_por_admin_id";
}

function mfin_cumplimiento_sql(): string
{
    return "COALESCE(
                st_actual.resultado_cumplimiento,
                (
                    SELECT CASE
                        WHEN SUM(stc.resultado_cumplimiento = 'TARDE') > 0 THEN 'TARDE'
                        WHEN SUM(stc.resultado_cumplimiento = 'A_TIEMPO') > 0 THEN 'A_TIEMPO'
                        WHEN SUM(stc.resultado_cumplimiento = 'NO_REALIZADO') > 0 THEN 'NO_REALIZADO'
                        ELSE 'NO_APLICA'
                    END
                    FROM solicitud_tecnicos stc
                    WHERE stc.solicitud_id = s.id
                ),
                'NO_APLICA'
            )";
}

function mfin_contar_resultados(PDO $conexion, string $where, array $parametros): int
{
    $sql = 'SELECT COUNT(*) ' . mfin_desde_base() . ' ' . $where;
    $stmt = $conexion->prepare($sql);
    mfin_vincular_parametros($stmt, $parametros);
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function mfin_obtener_resumen(PDO $conexion, string $where, array $parametros): array
{
    $cumplimiento = mfin_cumplimiento_sql();
    $sql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN DATE(cm.fecha_hora_cierre)
                    BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND CURDATE()
                    THEN 1 ELSE 0 END) AS este_mes,
                SUM(CASE WHEN cm.trabajo_quedo = 'TERMINADO' THEN 1 ELSE 0 END) AS terminados,
                SUM(CASE WHEN cm.trabajo_quedo IN ('PARCIAL','PROVISIONAL') THEN 1 ELSE 0 END) AS con_pendientes,
                SUM(CASE WHEN {$cumplimiento} = 'A_TIEMPO' THEN 1 ELSE 0 END) AS a_tiempo,
                SUM(CASE WHEN {$cumplimiento} = 'TARDE' THEN 1 ELSE 0 END) AS tarde,
                COALESCE(SUM(ex.total_segundos_activos), 0) AS segundos_activos,
                COALESCE(SUM(ex.total_segundos_pausa), 0) AS segundos_pausa,
                COALESCE(AVG(ex.total_segundos_activos), 0) AS promedio_segundos_activos
            " . mfin_desde_base() . " {$where}";

    $stmt = $conexion->prepare($sql);
    mfin_vincular_parametros($stmt, $parametros);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total' => (int) ($fila['total'] ?? 0),
        'este_mes' => (int) ($fila['este_mes'] ?? 0),
        'terminados' => (int) ($fila['terminados'] ?? 0),
        'con_pendientes' => (int) ($fila['con_pendientes'] ?? 0),
        'a_tiempo' => (int) ($fila['a_tiempo'] ?? 0),
        'tarde' => (int) ($fila['tarde'] ?? 0),
        'segundos_activos' => (int) ($fila['segundos_activos'] ?? 0),
        'segundos_pausa' => (int) ($fila['segundos_pausa'] ?? 0),
        'promedio_segundos_activos' => (int) round((float) ($fila['promedio_segundos_activos'] ?? 0)),
    ];
}

function mfin_obtener_registros(
    PDO $conexion,
    string $where,
    array $parametros,
    string $orden,
    int $limite,
    int $offset
): array {
    $cumplimiento = mfin_cumplimiento_sql();
    $ordenSql = mfin_orden_sql($orden);

    $sql = "SELECT
                s.id AS solicitud_id,
                s.equipo_id,
                ex.ejecucion_id,
                ex.fecha_hora_inicio,
                ex.fecha_hora_fin,
                ex.total_segundos_activos,
                ex.total_segundos_pausa,
                {$cumplimiento} AS resultado_cumplimiento,
                s.folio,
                s.tipo_solicitud,
                s.estado AS estado_solicitud,
                s.prioridad,
                s.descripcion_solicitud,
                s.nivel_riesgo,
                s.trabajo_peligroso,
                s.detalle_trabajo_peligroso,
                s.requiere_paro_equipo,
                e.codigo_equipo,
                e.nombre_equipo,
                d.nombre AS departamento,
                a.nombre AS area,
                p.nombre AS proceso,
                pm.id AS programacion_id,
                pm.fecha_programada,
                pm.fecha_limite,
                pm.estado AS estado_programacion,
                cm.id AS cierre_id,
                cm.fecha_hora_cierre,
                cm.trabajo_quedo,
                cm.descripcion_trabajo_realizado,
                cm.que_falto,
                cm.realizo_limpieza_area,
                cm.area_ordenada_libre_componentes,
                cm.sin_herramientas_utilizadas,
                cm.sin_refacciones_utilizadas,
                cm.fecha_hora_cierre AS fecha_finalizacion,
                CASE
                    WHEN cm.cerrado_por_tecnico_id IS NOT NULL THEN
                        TRIM(CONCAT_WS(' ', tc.nombre, tc.apellido_paterno, tc.apellido_materno))
                    WHEN cm.cerrado_por_admin_id IS NOT NULL THEN
                        TRIM(CONCAT_WS(' ', ac.nombre, ac.apellido_paterno, ac.apellido_materno))
                    ELSE 'Cierre no identificado'
                END AS cerrado_por,
                CASE
                    WHEN cm.cerrado_por_tecnico_id IS NOT NULL THEN 'TECNICO'
                    WHEN cm.cerrado_por_admin_id IS NOT NULL THEN 'ADMIN'
                    ELSE 'SISTEMA'
                END AS cerrado_por_tipo,
                (
                    SELECT COUNT(DISTINCT stp.tecnico_id)
                    FROM solicitud_tecnicos stp
                    WHERE stp.solicitud_id = s.id
                      AND stp.estado = 'TERMINADO'
                ) AS participantes_terminaron
            " . mfin_desde_base() . "
            {$where}
            ORDER BY {$ordenSql}
            LIMIT :mfin_limite OFFSET :mfin_offset";

    $stmt = $conexion->prepare($sql);
    mfin_vincular_parametros($stmt, $parametros);
    $stmt->bindValue(':mfin_limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':mfin_offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mfin_orden_sql(string $orden): string
{
    $mapa = [
        'RECIENTES' => 'cm.fecha_hora_cierre DESC, s.id DESC',
        'ANTIGUOS' => 'cm.fecha_hora_cierre ASC, s.id ASC',
        'MAYOR_TIEMPO' => 'ex.total_segundos_activos DESC, cm.fecha_hora_cierre DESC',
        'MENOR_TIEMPO' => 'ex.total_segundos_activos ASC, cm.fecha_hora_cierre DESC',
        'FOLIO' => 's.folio ASC, s.id DESC',
    ];
    return $mapa[$orden] ?? $mapa['RECIENTES'];
}

/* =========================================================================
   DETALLE
   ========================================================================= */

function mfin_cargar_detalle(PDO $conexion): void
{
    $tecnicoId = mfin_tecnico_id();
    mfin_obtener_tecnico_activo($conexion, $tecnicoId);
    $solicitudId = mfin_id_entrada($_GET['solicitud_id'] ?? null, 'solicitud_id');
    $alcance = mfin_validar_alcance($_GET['alcance'] ?? 'MIS');

    $detalle = mfin_obtener_detalle_principal($conexion, $tecnicoId, $solicitudId, $alcance);

    if (!$detalle) {
        sm_responder_json(false, 'El mantenimiento no existe en el historial disponible.', [], 404);
    }

    $cierreId = (int) ($detalle['cierre_id'] ?? 0);
    $detalle['recursos_recomendados'] = rsm_obtener_recursos_recomendados_solicitud($conexion, $solicitudId, false);
    $detalle['recursos_utilizados'] = rsm_obtener_recursos_utilizados_cierre($conexion, $cierreId);

    sm_responder_json(true, 'Detalle cargado correctamente.', [
        'detalle' => $detalle,
        'recursos_recomendados' => $detalle['recursos_recomendados'],
        'recursos_utilizados' => $detalle['recursos_utilizados'],
        'participantes' => mfin_obtener_participantes($conexion, $solicitudId, $tecnicoId),
        'pausas' => mfin_obtener_pausas($conexion, $solicitudId, $tecnicoId, $alcance),
        'historial' => mfin_obtener_historial($conexion, $solicitudId),
        'evidencias' => mfin_obtener_evidencias($conexion, $solicitudId),
        'alcance' => $alcance,
        'fecha_servidor' => date('Y-m-d H:i:s'),
    ]);
}

function mfin_obtener_detalle_principal(
    PDO $conexion,
    int $tecnicoId,
    int $solicitudId,
    string $alcance
): ?array {
    $permiso = $alcance === 'MIS'
        ? "AND EXISTS (
                SELECT 1
                FROM ejecuciones_mantenimiento permiso_em
                WHERE permiso_em.solicitud_id = s.id
                  AND permiso_em.tecnico_id = :detalle_tecnico_permiso
                  AND permiso_em.estado = 'TERMINADA'
            )"
        : '';

    $cumplimiento = mfin_cumplimiento_sql();

    $sql = "SELECT
                COALESCE(em_actual.id, ex.ejecucion_id) AS ejecucion_id,
                s.id AS solicitud_id,
                st_actual.id AS solicitud_tecnico_id,
                COALESCE(em_actual.estado, 'TERMINADA') AS estado_ejecucion,
                ex.fecha_hora_inicio,
                ex.fecha_hora_fin,
                em_actual.fecha_hora_inicio_original,
                em_actual.fecha_hora_fin_original,
                ex.total_segundos_activos,
                ex.total_segundos_pausa,
                em_actual.iniciada_por_tipo,
                st_actual.origen,
                st_actual.estado AS estado_participacion,
                st_actual.fecha_asignacion,
                st_actual.fecha_aceptacion,
                {$cumplimiento} AS resultado_cumplimiento,
                st_actual.fecha_resultado,
                st_actual.alerta_riesgo_nocturno,
                st_actual.riesgo_nocturno_confirmado,
                st_actual.observacion_riesgo_nocturno,
                s.folio,
                s.tipo_solicitud,
                s.estado AS estado_solicitud,
                s.prioridad,
                s.fecha_solicitud,
                s.hora_solicitud,
                s.fecha_sugerida,
                s.descripcion_solicitud,
                s.descripcion_falla,
                s.causa_desconocida_descripcion,
                s.costo_vs_beneficio,
                s.impacto_operacion,
                s.objetivo_mejora,
                s.resultado_esperado,
                s.justificacion_mejora,
                s.observaciones_solicitante,
                s.trabajo_peligroso,
                s.detalle_trabajo_peligroso,
                s.nivel_riesgo,
                s.requiere_paro_equipo,
                s.equipo_id,
                e.codigo_equipo,
                e.nombre_equipo,
                e.descripcion AS descripcion_equipo,
                d.nombre AS departamento,
                a.nombre AS area,
                p.nombre AS proceso,
                tf.nombre AS tipo_falla,
                ca.nombre AS causa_averia,
                pm.id AS programacion_id,
                pm.fecha_programada,
                pm.fecha_limite,
                pm.estado AS estado_programacion,
                pm.motivo_programacion,
                pm.motivo_reprogramacion,
                cm.id AS cierre_id,
                cm.fecha_hora_cierre,
                cm.trabajo_quedo,
                cm.descripcion_trabajo_realizado,
                cm.que_falto,
                cm.realizo_limpieza_area,
                cm.area_ordenada_libre_componentes,
                cm.observaciones_cierre,
                cm.sin_herramientas_utilizadas,
                cm.sin_refacciones_utilizadas,
                cm.editado_por_admin_id,
                cm.motivo_edicion,
                cm.fecha_actualizacion AS fecha_actualizacion_cierre,
                CASE
                    WHEN s.solicitante_id IS NOT NULL THEN
                        TRIM(CONCAT_WS(' ', so.nombre, so.apellido_paterno, so.apellido_materno))
                    WHEN s.administrador_solicitante_id IS NOT NULL THEN
                        TRIM(CONCAT_WS(' ', asa.nombre, asa.apellido_paterno, asa.apellido_materno))
                    ELSE 'Generada por el sistema'
                END AS solicitante,
                CASE WHEN s.solicitante_id IS NOT NULL THEN so.telefono ELSE NULL END AS telefono_solicitante,
                CASE WHEN s.solicitante_id IS NOT NULL THEN so.correo ELSE NULL END AS correo_solicitante,
                CASE
                    WHEN cm.cerrado_por_tecnico_id IS NOT NULL THEN
                        TRIM(CONCAT_WS(' ', tc.nombre, tc.apellido_paterno, tc.apellido_materno))
                    WHEN cm.cerrado_por_admin_id IS NOT NULL THEN
                        TRIM(CONCAT_WS(' ', ac.nombre, ac.apellido_paterno, ac.apellido_materno))
                    ELSE 'Cierre no identificado'
                END AS cerrado_por,
                CASE
                    WHEN cm.cerrado_por_tecnico_id IS NOT NULL THEN 'TECNICO'
                    WHEN cm.cerrado_por_admin_id IS NOT NULL THEN 'ADMIN'
                    ELSE 'SISTEMA'
                END AS cerrado_por_tipo,
                CASE WHEN cm.editado_por_admin_id IS NOT NULL THEN
                    TRIM(CONCAT_WS(' ', ae.nombre, ae.apellido_paterno, ae.apellido_materno))
                    ELSE NULL END AS cierre_editado_por
            " . mfin_desde_base() . "
            LEFT JOIN ejecuciones_mantenimiento em_actual
                   ON em_actual.solicitud_id = s.id
                  AND em_actual.tecnico_id = :detalle_tecnico_ejecucion
                  AND em_actual.estado = 'TERMINADA'
            LEFT JOIN tipos_falla tf ON tf.id = s.tipo_falla_id
            LEFT JOIN causas_averia ca ON ca.id = s.causa_averia_id
            LEFT JOIN solicitantes so ON so.id = s.solicitante_id
            LEFT JOIN administradores asa ON asa.id = s.administrador_solicitante_id
            LEFT JOIN administradores ae ON ae.id = cm.editado_por_admin_id
            WHERE s.id = :detalle_solicitud_id
              {$permiso}
            LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':mfin_tecnico_select', $tecnicoId, PDO::PARAM_INT);
    $stmt->bindValue(':detalle_tecnico_ejecucion', $tecnicoId, PDO::PARAM_INT);
    $stmt->bindValue(':detalle_solicitud_id', $solicitudId, PDO::PARAM_INT);
    if ($alcance === 'MIS') {
        $stmt->bindValue(':detalle_tecnico_permiso', $tecnicoId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($fila) ? $fila : null;
}

function mfin_obtener_participantes(PDO $conexion, int $solicitudId, int $tecnicoActualId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            st.id AS solicitud_tecnico_id,
            st.tecnico_id,
            st.origen,
            st.estado AS estado_participacion,
            st.fecha_asignacion,
            st.fecha_aceptacion,
            st.fecha_retiro,
            st.resultado_cumplimiento,
            st.fecha_resultado,
            st.alerta_riesgo_nocturno,
            st.riesgo_nocturno_confirmado,
            st.observacion_riesgo_nocturno,
            st.activo,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
            t.turno,
            t.especialidad,
            d.nombre AS departamento_tecnico,
            em.id AS ejecucion_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.fecha_hora_fin,
            em.total_segundos_activos,
            em.total_segundos_pausa,
            CASE WHEN st.tecnico_id = :participante_tecnico_actual THEN 1 ELSE 0 END AS es_actual
         FROM solicitud_tecnicos st
         INNER JOIN tecnicos t ON t.id = st.tecnico_id
         LEFT JOIN departamentos d ON d.id = t.departamento_id
         LEFT JOIN ejecuciones_mantenimiento em ON em.solicitud_tecnico_id = st.id
         WHERE st.solicitud_id = :participante_solicitud_id
         ORDER BY
            CASE WHEN st.tecnico_id = :participante_tecnico_orden THEN 0 ELSE 1 END,
            FIELD(st.estado, 'TERMINADO','EN_PROCESO','PAUSADO','ACEPTADO','ASIGNADO','NO_PARTICIPO','RETIRADO'),
            t.nombre, t.apellido_paterno, t.id"
    );
    $stmt->bindValue(':participante_tecnico_actual', $tecnicoActualId, PDO::PARAM_INT);
    $stmt->bindValue(':participante_solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':participante_tecnico_orden', $tecnicoActualId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mfin_obtener_pausas(PDO $conexion, int $solicitudId, int $tecnicoId, string $alcance): array
{
    $filtroTecnico = $alcance === 'MIS' ? 'AND em.tecnico_id = :pausa_tecnico_id' : '';
    $stmt = $conexion->prepare(
        "SELECT
            pe.id,
            pe.ejecucion_id,
            pe.motivo,
            pe.fecha_hora_inicio,
            pe.fecha_hora_fin,
            pe.duracion_segundos,
            pe.observaciones,
            pe.creada_por_tipo,
            pe.creada_por_id,
            su.folio AS folio_urgencia,
            eu.nombre_equipo AS equipo_urgencia,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico
         FROM pausas_ejecucion pe
         INNER JOIN ejecuciones_mantenimiento em ON em.id = pe.ejecucion_id
         INNER JOIN tecnicos t ON t.id = em.tecnico_id
         LEFT JOIN solicitudes su ON su.id = pe.solicitud_urgente_id
         LEFT JOIN equipos eu ON eu.id = su.equipo_id
         WHERE em.solicitud_id = :pausa_solicitud_id
           {$filtroTecnico}
         ORDER BY pe.fecha_hora_inicio ASC, pe.id ASC"
    );
    $stmt->bindValue(':pausa_solicitud_id', $solicitudId, PDO::PARAM_INT);
    if ($alcance === 'MIS') {
        $stmt->bindValue(':pausa_tecnico_id', $tecnicoId, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mfin_obtener_historial(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            hs.id, hs.evento, hs.estado_anterior, hs.estado_nuevo,
            hs.actor_tipo, hs.actor_id, hs.descripcion, hs.fecha_evento,
            CASE
                WHEN hs.actor_tipo = 'ADMIN' THEN TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno))
                WHEN hs.actor_tipo = 'TECNICO' THEN TRIM(CONCAT_WS(' ', te.nombre, te.apellido_paterno, te.apellido_materno))
                WHEN hs.actor_tipo = 'SOLICITANTE' THEN TRIM(CONCAT_WS(' ', so.nombre, so.apellido_paterno, so.apellido_materno))
                ELSE 'Sistema'
            END AS actor
         FROM historial_solicitudes hs
         LEFT JOIN administradores ad ON hs.actor_tipo = 'ADMIN' AND ad.id = hs.actor_id
         LEFT JOIN tecnicos te ON hs.actor_tipo = 'TECNICO' AND te.id = hs.actor_id
         LEFT JOIN solicitantes so ON hs.actor_tipo = 'SOLICITANTE' AND so.id = hs.actor_id
         WHERE hs.solicitud_id = :historial_solicitud_id
         ORDER BY hs.fecha_evento ASC, hs.id ASC
         LIMIT 200"
    );
    $stmt->bindValue(':historial_solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mfin_obtener_evidencias(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            ev.id, ev.ejecucion_id, ev.cierre_id, ev.tipo_evidencia,
            ev.nombre_original, ev.ruta_archivo, ev.mime_type, ev.tamano_bytes,
            ev.descripcion, ev.subido_por_tipo, ev.subido_por_id, ev.fecha_registro,
            CASE
                WHEN ev.subido_por_tipo = 'ADMIN' THEN TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno))
                WHEN ev.subido_por_tipo = 'TECNICO' THEN TRIM(CONCAT_WS(' ', te.nombre, te.apellido_paterno, te.apellido_materno))
                WHEN ev.subido_por_tipo = 'SOLICITANTE' THEN TRIM(CONCAT_WS(' ', so.nombre, so.apellido_paterno, so.apellido_materno))
                ELSE 'Usuario'
            END AS subido_por
         FROM evidencias_mantenimiento ev
         LEFT JOIN administradores ad ON ev.subido_por_tipo = 'ADMIN' AND ad.id = ev.subido_por_id
         LEFT JOIN tecnicos te ON ev.subido_por_tipo = 'TECNICO' AND te.id = ev.subido_por_id
         LEFT JOIN solicitantes so ON ev.subido_por_tipo = 'SOLICITANTE' AND so.id = ev.subido_por_id
         WHERE ev.solicitud_id = :evidencia_solicitud_id
           AND ev.activo = 1
         ORDER BY ev.fecha_registro ASC, ev.id ASC"
    );
    $stmt->bindValue(':evidencia_solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $evidencias = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($evidencias as &$evidencia) {
        $evidencia['ruta_publica'] = mfin_ruta_publica_evidencia((string) ($evidencia['ruta_archivo'] ?? ''));
    }
    unset($evidencia);
    return $evidencias;
}

function mfin_ruta_publica_evidencia(string $ruta): ?string
{
    $ruta = trim(str_replace('\\', '/', $ruta));
    if ($ruta === '' || strpos($ruta, '..') !== false || preg_match('#^[a-z][a-z0-9+.-]*://#i', $ruta) || strpos($ruta, "\0") !== false) {
        return null;
    }
    return '../' . ltrim($ruta, '/');
}

/* =========================================================================
   EXPORTACIÓN CSV
   ========================================================================= */

function mfin_exportar_csv(PDO $conexion): void
{
    $tecnicoId = mfin_tecnico_id();
    $perfil = mfin_obtener_tecnico_activo($conexion, $tecnicoId);
    $filtros = mfin_leer_filtros();
    $consulta = mfin_construir_condiciones($tecnicoId, $filtros);
    $registros = mfin_obtener_registros(
        $conexion,
        $consulta['where'],
        $consulta['parametros'],
        (string) $filtros['orden'],
        20000,
        0
    );

    rsm_adjuntar_recursos_recomendados($conexion, $registros, 'equipo_id', false);
    rsm_adjuntar_resumen_recursos_utilizados($conexion, $registros);

    $nombreArchivo = 'historial_' . strtolower((string) $filtros['alcance']) . '_' . date('Y-m-d_H-i-s') . '.csv';
    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    $salida = fopen('php://output', 'wb');
    if ($salida === false) {
        throw new RuntimeException('No fue posible preparar el archivo de exportación.');
    }
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, [
        'Alcance', 'Consultado por', 'Folio', 'Tipo', 'Prioridad', 'Código de equipo', 'Equipo',
        'Departamento', 'Área', 'Proceso', 'Inicio real', 'Fin real', 'Tiempo activo', 'Tiempo pausado',
        'Cumplimiento', 'Fecha de cierre', 'Resultado', 'Trabajo realizado', 'Qué faltó',
        'Herramientas recomendadas', 'Refacciones recomendadas',
        'Herramientas realmente utilizadas', 'Refacciones realmente utilizadas',
        'Limpieza realizada', 'Área ordenada', 'Cerrado por'
    ]);

    foreach ($registros as $fila) {
        $herrRec = mfin_nombres_recursos($fila['herramientas_recomendadas'] ?? []);
        $refRec = mfin_nombres_recursos($fila['refacciones_recomendadas'] ?? []);
        $herrUso = mfin_resumen_utilizado_csv($fila, 'HERRAMIENTA');
        $refUso = mfin_resumen_utilizado_csv($fila, 'REFACCION');

        fputcsv($salida, [
            (string) $filtros['alcance'],
            (string) ($perfil['nombre_completo'] ?? ''),
            (string) ($fila['folio'] ?? ''),
            mfin_etiqueta_tipo((string) ($fila['tipo_solicitud'] ?? '')),
            (string) ($fila['prioridad'] ?? ''),
            (string) ($fila['codigo_equipo'] ?? ''),
            (string) ($fila['nombre_equipo'] ?? ''),
            (string) ($fila['departamento'] ?? ''),
            (string) ($fila['area'] ?? ''),
            (string) ($fila['proceso'] ?? ''),
            mfin_fecha_hora_csv((string) ($fila['fecha_hora_inicio'] ?? '')),
            mfin_fecha_hora_csv((string) ($fila['fecha_hora_fin'] ?? '')),
            mfin_duracion_csv((int) ($fila['total_segundos_activos'] ?? 0)),
            mfin_duracion_csv((int) ($fila['total_segundos_pausa'] ?? 0)),
            (string) ($fila['resultado_cumplimiento'] ?? ''),
            mfin_fecha_hora_csv((string) ($fila['fecha_hora_cierre'] ?? '')),
            (string) ($fila['trabajo_quedo'] ?? ''),
            (string) ($fila['descripcion_trabajo_realizado'] ?? ''),
            (string) ($fila['que_falto'] ?? ''),
            $herrRec,
            $refRec,
            $herrUso,
            $refUso,
            (int) ($fila['realizo_limpieza_area'] ?? 0) === 1 ? 'Sí' : 'No',
            (int) ($fila['area_ordenada_libre_componentes'] ?? 0) === 1 ? 'Sí' : 'No',
            (string) ($fila['cerrado_por'] ?? ''),
        ]);
    }

    fclose($salida);
    exit;
}

function mfin_nombres_recursos(array $recursos): string
{
    $nombres = [];
    foreach ($recursos as $recurso) {
        $nombre = trim((string) ($recurso['nombre'] ?? ''));
        if ($nombre !== '') {
            $nombres[] = $nombre;
        }
    }
    return implode(' | ', array_values(array_unique($nombres)));
}

function mfin_resumen_utilizado_csv(array $fila, string $tipo): string
{
    if ($tipo === 'HERRAMIENTA') {
        if ((int) ($fila['sin_herramientas_utilizadas'] ?? 0) === 1) {
            return 'No se utilizaron herramientas';
        }
        return implode(' | ', (array) ($fila['herramientas_utilizadas_nombres'] ?? []));
    }

    if ((int) ($fila['sin_refacciones_utilizadas'] ?? 0) === 1) {
        return 'No se utilizaron refacciones';
    }
    return implode(' | ', (array) ($fila['refacciones_utilizadas_nombres'] ?? []));
}

/* =========================================================================
   FILTROS
   ========================================================================= */

function mfin_leer_filtros(): array
{
    $pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $porPagina = filter_var($_GET['por_pagina'] ?? 12, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 48]]);
    $tipo = strtoupper(mfin_texto($_GET['tipo'] ?? 'TODOS'));
    $resultado = strtoupper(mfin_texto($_GET['resultado'] ?? 'TODOS'));
    $cumplimiento = strtoupper(mfin_texto($_GET['cumplimiento'] ?? 'TODOS'));
    $orden = strtoupper(mfin_texto($_GET['orden'] ?? 'RECIENTES'));
    $alcance = mfin_validar_alcance($_GET['alcance'] ?? 'MIS');
    $fechaDesde = mfin_texto($_GET['fecha_desde'] ?? '');
    $fechaHasta = mfin_texto($_GET['fecha_hasta'] ?? '');

    if (!in_array($tipo, ['TODOS','CORRECTIVO_PROGRAMABLE','MODIFICACION_MEJORA','CORRECTIVO_URGENTE','RUTINARIO'], true)) {
        $tipo = 'TODOS';
    }
    if (!in_array($resultado, ['TODOS','TERMINADO','PARCIAL','PROVISIONAL'], true)) {
        $resultado = 'TODOS';
    }
    if (!in_array($cumplimiento, ['TODOS','A_TIEMPO','TARDE','NO_APLICA'], true)) {
        $cumplimiento = 'TODOS';
    }
    if (!in_array($orden, ['RECIENTES','ANTIGUOS','MAYOR_TIEMPO','MENOR_TIEMPO','FOLIO'], true)) {
        $orden = 'RECIENTES';
    }
    if ($fechaDesde !== '' && !mfin_fecha_valida($fechaDesde)) {
        $fechaDesde = '';
    }
    if ($fechaHasta !== '' && !mfin_fecha_valida($fechaHasta)) {
        $fechaHasta = '';
    }
    if ($fechaDesde !== '' && $fechaHasta !== '' && $fechaDesde > $fechaHasta) {
        sm_responder_json(false, 'La fecha inicial no puede ser posterior a la fecha final.', [], 422);
    }

    return [
        'alcance' => $alcance,
        'busqueda' => mfin_limitar(mfin_texto($_GET['busqueda'] ?? ''), 120),
        'tipo' => $tipo,
        'resultado' => $resultado,
        'cumplimiento' => $cumplimiento,
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta,
        'orden' => $orden,
        'pagina' => $pagina === false ? 1 : (int) $pagina,
        'por_pagina' => $porPagina === false ? 12 : (int) $porPagina,
    ];
}

function mfin_validar_alcance($valor): string
{
    $alcance = strtoupper(mfin_texto($valor));
    return $alcance === 'TODOS' ? 'TODOS' : 'MIS';
}

function mfin_construir_condiciones(int $tecnicoId, array $filtros): array
{
    $condiciones = ['cm.id IS NOT NULL'];
    $parametros = [':mfin_tecnico_select' => $tecnicoId];

    if ((string) $filtros['alcance'] === 'MIS') {
        $condiciones[] = "EXISTS (
            SELECT 1 FROM ejecuciones_mantenimiento em_mis
            WHERE em_mis.solicitud_id = s.id
              AND em_mis.tecnico_id = :mfin_tecnico_scope
              AND em_mis.estado = 'TERMINADA'
        )";
        $parametros[':mfin_tecnico_scope'] = $tecnicoId;
    }

    if ((string) $filtros['tipo'] !== 'TODOS') {
        $condiciones[] = 's.tipo_solicitud = :mfin_tipo';
        $parametros[':mfin_tipo'] = (string) $filtros['tipo'];
    }

    if ((string) $filtros['resultado'] !== 'TODOS') {
        $condiciones[] = 'cm.trabajo_quedo = :mfin_resultado';
        $parametros[':mfin_resultado'] = (string) $filtros['resultado'];
    }

    if ((string) $filtros['cumplimiento'] !== 'TODOS') {
        $condiciones[] = mfin_cumplimiento_sql() . ' = :mfin_cumplimiento';
        $parametros[':mfin_cumplimiento'] = (string) $filtros['cumplimiento'];
    }

    if ((string) $filtros['fecha_desde'] !== '') {
        $condiciones[] = 'DATE(cm.fecha_hora_cierre) >= :mfin_fecha_desde';
        $parametros[':mfin_fecha_desde'] = (string) $filtros['fecha_desde'];
    }

    if ((string) $filtros['fecha_hasta'] !== '') {
        $condiciones[] = 'DATE(cm.fecha_hora_cierre) <= :mfin_fecha_hasta';
        $parametros[':mfin_fecha_hasta'] = (string) $filtros['fecha_hasta'];
    }

    $busqueda = trim((string) $filtros['busqueda']);
    if ($busqueda !== '') {
        $valor = '%' . $busqueda . '%';
        $campos = [
            's.folio LIKE :mfin_q_folio',
            'e.codigo_equipo LIKE :mfin_q_codigo',
            'e.nombre_equipo LIKE :mfin_q_equipo',
            'd.nombre LIKE :mfin_q_departamento',
            'a.nombre LIKE :mfin_q_area',
            'p.nombre LIKE :mfin_q_proceso',
            's.descripcion_solicitud LIKE :mfin_q_solicitud',
            'cm.descripcion_trabajo_realizado LIKE :mfin_q_trabajo',
            "EXISTS (
                SELECT 1
                FROM cierre_recursos_utilizados cru_q
                LEFT JOIN recursos_mantenimiento r_q ON r_q.id = cru_q.recurso_id
                WHERE cru_q.cierre_id = cm.id
                  AND COALESCE(r_q.nombre, cru_q.nombre_no_catalogado) LIKE :mfin_q_usado
            )",
            "EXISTS (
                SELECT 1
                FROM solicitud_recursos_recomendados srr_q
                LEFT JOIN recursos_mantenimiento rr_q ON rr_q.id = srr_q.recurso_id
                WHERE srr_q.solicitud_id = s.id
                  AND COALESCE(rr_q.nombre, srr_q.nombre_no_catalogado) LIKE :mfin_q_recomendado
            )",
        ];
        $condiciones[] = '(' . implode(' OR ', $campos) . ')';
        foreach (['folio','codigo','equipo','departamento','area','proceso','solicitud','trabajo','usado','recomendado'] as $clave) {
            $parametros[':mfin_q_' . $clave] = $valor;
        }
    }

    return [
        'where' => 'WHERE ' . implode(' AND ', $condiciones),
        'parametros' => $parametros,
    ];
}

function mfin_catalogos_filtros(): array
{
    return [
        'alcances' => ['MIS' => 'Mis mantenimientos', 'TODOS' => 'Todos los mantenimientos'],
        'tipos' => [
            'CORRECTIVO_PROGRAMABLE' => 'Correctivo programable',
            'MODIFICACION_MEJORA' => 'Modificación o mejora',
            'CORRECTIVO_URGENTE' => 'Correctivo urgente',
            'RUTINARIO' => 'Mantenimiento rutinario',
        ],
    ];
}

/* =========================================================================
   CANCELACIONES ADMINISTRATIVAS DEL TÉCNICO
   ========================================================================= */

function mfin_obtener_cancelaciones_tecnico(PDO $conexion, int $tecnicoId): array
{
    $sql = "SELECT
                s.id AS solicitud_id,
                s.folio,
                s.tipo_solicitud,
                s.prioridad,
                s.descripcion_solicitud,
                s.trabajo_peligroso,
                s.detalle_trabajo_peligroso,
                s.nivel_riesgo,
                s.fecha_actualizacion,
                e.codigo_equipo,
                e.nombre_equipo,
                d.nombre AS departamento,
                a.nombre AS area,
                p.nombre AS proceso,
                pm.fecha_programada,
                COALESCE(
                    NULLIF(pm.motivo_cancelacion, ''),
                    NULLIF(s.motivo_ultima_edicion, ''),
                    'No se registró un motivo de cancelación.'
                ) AS motivo_cancelacion,
                COALESCE(hc.fecha_evento, s.fecha_actualizacion) AS fecha_cancelacion,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', adm.nombre, adm.apellido_paterno, adm.apellido_materno)), ''),
                    'Administración'
                ) AS cancelado_por,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM ejecuciones_mantenimiento em_i
                        WHERE em_i.solicitud_id = s.id
                          AND em_i.fecha_hora_inicio IS NOT NULL
                    ) THEN 1 ELSE 0
                END AS fue_iniciado,
                (
                    SELECT COALESCE(SUM(em_t.total_segundos_activos), 0)
                    FROM ejecuciones_mantenimiento em_t
                    WHERE em_t.solicitud_id = s.id
                ) AS total_segundos_activos,
                (
                    SELECT COALESCE(SUM(em_p.total_segundos_pausa), 0)
                    FROM ejecuciones_mantenimiento em_p
                    WHERE em_p.solicitud_id = s.id
                ) AS total_segundos_pausa,
                (
                    SELECT COUNT(DISTINCT st_c.tecnico_id)
                    FROM solicitud_tecnicos st_c
                    WHERE st_c.solicitud_id = s.id
                ) AS participantes_asignados
            FROM solicitudes s
            INNER JOIN equipos e ON e.id = s.equipo_id
            INNER JOIN departamentos d ON d.id = s.departamento_id
            INNER JOIN areas a ON a.id = s.area_id
            INNER JOIN procesos p ON p.id = s.proceso_id
            LEFT JOIN programaciones_mantenimiento pm
                   ON pm.id = (
                        SELECT MAX(pm2.id)
                        FROM programaciones_mantenimiento pm2
                        WHERE pm2.solicitud_id = s.id
                   )
            LEFT JOIN historial_solicitudes hc
                   ON hc.id = (
                        SELECT MAX(h2.id)
                        FROM historial_solicitudes h2
                        WHERE h2.solicitud_id = s.id
                          AND h2.evento = 'CANCELADA'
                   )
            LEFT JOIN administradores adm
                   ON hc.actor_tipo = 'ADMIN'
                  AND adm.id = hc.actor_id
            WHERE s.estado = 'CANCELADO'
              AND (
                    EXISTS (
                        SELECT 1
                        FROM historial_solicitudes ht_permiso
                        INNER JOIN solicitud_tecnicos st_permiso
                                ON st_permiso.id = ht_permiso.solicitud_tecnico_id
                        WHERE ht_permiso.solicitud_id = s.id
                          AND st_permiso.tecnico_id = :tecnico_historial
                          AND ht_permiso.evento = 'TECNICO_RETIRADO'
                          AND LOWER(ht_permiso.descripcion) LIKE '%cancel%'
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM notificaciones n_permiso
                        WHERE n_permiso.solicitud_id = s.id
                          AND n_permiso.tipo_usuario = 'TECNICO'
                          AND n_permiso.usuario_id = :tecnico_notificacion
                          AND n_permiso.titulo IN (
                              'Mantenimiento cancelado',
                              'Mantenimiento cancelado por administración'
                          )
                    )
              )
            ORDER BY COALESCE(hc.fecha_evento, s.fecha_actualizacion) DESC, s.id DESC
            LIMIT 100";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':tecnico_historial', $tecnicoId, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_notificacion', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* =========================================================================
   IDENTIDAD Y UTILIDADES
   ========================================================================= */

function mfin_tecnico_id(): int
{
    $id = $_SESSION['tecnico_id'] ?? $_SESSION['id_usuario'] ?? $_SESSION['usuario_id'] ?? 0;
    $entero = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($entero === false) {
        sm_responder_json(false, 'No fue posible identificar al técnico autenticado.', [], 401);
    }
    return (int) $entero;
}

function mfin_obtener_tecnico_activo(PDO $conexion, int $tecnicoId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            t.id, t.usuario, t.turno, t.especialidad, t.activo,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS nombre_completo,
            d.nombre AS departamento
         FROM tecnicos t
         LEFT JOIN departamentos d ON d.id = t.departamento_id
         WHERE t.id = :tecnico_id
         LIMIT 1"
    );
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    $perfil = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($perfil) || (int) ($perfil['activo'] ?? 0) !== 1) {
        sm_responder_json(false, 'La cuenta del técnico no está disponible.', [], 403);
    }
    return $perfil;
}

function mfin_vincular_parametros(PDOStatement $stmt, array $parametros): void
{
    foreach ($parametros as $clave => $valor) {
        $stmt->bindValue($clave, is_int($valor) ? $valor : (string) $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

function mfin_id_entrada($valor, string $campo): int
{
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        sm_responder_json(false, 'El identificador de ' . $campo . ' no es válido.', [], 422);
    }
    return (int) $id;
}

function mfin_texto($valor): string
{
    if (is_array($valor) || is_object($valor)) {
        return '';
    }
    return trim((string) $valor);
}

function mfin_limitar(string $texto, int $maximo): string
{
    return function_exists('mb_substr') ? mb_substr($texto, 0, $maximo, 'UTF-8') : substr($texto, 0, $maximo);
}

function mfin_fecha_valida(string $fecha): bool
{
    $objeto = DateTime::createFromFormat('!Y-m-d', $fecha);
    return $objeto instanceof DateTime && $objeto->format('Y-m-d') === $fecha;
}

function mfin_etiqueta_tipo(string $tipo): string
{
    $mapa = [
        'CORRECTIVO_PROGRAMABLE' => 'Correctivo programable',
        'MODIFICACION_MEJORA' => 'Modificación o mejora',
        'CORRECTIVO_URGENTE' => 'Correctivo urgente',
        'RUTINARIO' => 'Mantenimiento rutinario',
    ];
    return $mapa[$tipo] ?? $tipo;
}

function mfin_fecha_csv(string $fecha): string
{
    if ($fecha === '') {
        return '';
    }
    $objeto = DateTime::createFromFormat('Y-m-d', substr($fecha, 0, 10));
    return $objeto instanceof DateTime ? $objeto->format('d/m/Y') : $fecha;
}

function mfin_fecha_hora_csv(string $fechaHora): string
{
    if ($fechaHora === '') {
        return '';
    }
    try {
        return (new DateTime($fechaHora))->format('d/m/Y H:i:s');
    } catch (Throwable $e) {
        return $fechaHora;
    }
}

function mfin_duracion_csv(int $segundos): string
{
    $segundos = max(0, $segundos);
    return sprintf('%02d:%02d:%02d', intdiv($segundos, 3600), intdiv($segundos % 3600, 60), $segundos % 60);
}
