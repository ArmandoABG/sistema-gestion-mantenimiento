-- =====================================================================
-- SISTEMA DE MANTENIMIENTO
-- VERIFICACIÓN DE CANCELACIÓN GENERAL Y TRAZABILIDAD PARA TÉCNICOS
-- SOLO LECTURA: no crea, modifica ni elimina registros.
-- =====================================================================

USE `sistema_mantenimiento_1.1`;

SELECT
    CASE
        WHEN revision.asignaciones_activas = 0
         AND revision.ejecuciones_abiertas = 0
         AND revision.pausas_abiertas = 0
         AND revision.programaciones_vigentes = 0
         AND revision.incumplimientos_pendientes = 0
         AND revision.canceladas_con_cierre = 0
         AND revision.canceladas_sin_motivo = 0
         AND revision.canceladas_sin_evento = 0
         AND revision.rutinas_con_alerta_abierta = 0
        THEN 'TODO CORRECTO'
        ELSE 'REVISAR RESULTADOS'
    END AS estado_general,
    revision.asignaciones_activas,
    revision.ejecuciones_abiertas,
    revision.pausas_abiertas,
    revision.programaciones_vigentes,
    revision.incumplimientos_pendientes,
    revision.canceladas_con_cierre,
    revision.canceladas_sin_motivo,
    revision.canceladas_sin_evento,
    revision.rutinas_con_alerta_abierta
FROM (
    SELECT
        (
            SELECT COUNT(*)
            FROM solicitudes s
            INNER JOIN solicitud_tecnicos st ON st.solicitud_id = s.id
            WHERE s.estado = 'CANCELADO'
              AND st.activo = 1
        ) AS asignaciones_activas,
        (
            SELECT COUNT(*)
            FROM solicitudes s
            INNER JOIN ejecuciones_mantenimiento em ON em.solicitud_id = s.id
            WHERE s.estado = 'CANCELADO'
              AND em.estado IN ('PENDIENTE', 'EN_PROCESO', 'PAUSADA')
        ) AS ejecuciones_abiertas,
        (
            SELECT COUNT(*)
            FROM solicitudes s
            INNER JOIN ejecuciones_mantenimiento em ON em.solicitud_id = s.id
            INNER JOIN pausas_ejecucion pe ON pe.ejecucion_id = em.id
            WHERE s.estado = 'CANCELADO'
              AND pe.pausa_abierta_token = 1
        ) AS pausas_abiertas,
        (
            SELECT COUNT(*)
            FROM solicitudes s
            INNER JOIN programaciones_mantenimiento pm ON pm.solicitud_id = s.id
            WHERE s.estado = 'CANCELADO'
              AND (
                    pm.es_actual = 1
                    OR pm.vigente_token = 1
              )
        ) AS programaciones_vigentes,
        (
            SELECT COUNT(*)
            FROM solicitudes s
            INNER JOIN incumplimientos_mantenimiento im ON im.solicitud_id = s.id
            WHERE s.estado = 'CANCELADO'
              AND im.estado = 'PENDIENTE'
        ) AS incumplimientos_pendientes,
        (
            SELECT COUNT(*)
            FROM solicitudes s
            INNER JOIN cierres_mantenimiento cm ON cm.solicitud_id = s.id
            WHERE s.estado = 'CANCELADO'
        ) AS canceladas_con_cierre,
        (
            SELECT COUNT(*)
            FROM solicitudes s
            WHERE s.estado = 'CANCELADO'
              AND COALESCE(
                    NULLIF(TRIM(s.motivo_ultima_edicion), ''),
                    (
                        SELECT NULLIF(TRIM(pm2.motivo_cancelacion), '')
                        FROM programaciones_mantenimiento pm2
                        WHERE pm2.solicitud_id = s.id
                        ORDER BY pm2.id DESC
                        LIMIT 1
                    )
              ) IS NULL
        ) AS canceladas_sin_motivo,
        (
            SELECT COUNT(*)
            FROM solicitudes s
            WHERE s.estado = 'CANCELADO'
              AND NOT EXISTS (
                    SELECT 1
                    FROM historial_solicitudes hs
                    WHERE hs.solicitud_id = s.id
                      AND hs.evento = 'CANCELADA'
              )
        ) AS canceladas_sin_evento,
        (
            SELECT COUNT(*)
            FROM solicitudes s
            INNER JOIN rutina_alertas ra ON ra.solicitud_id = s.id
            WHERE s.estado = 'CANCELADO'
              AND s.tipo_solicitud = 'RUTINARIO'
              AND ra.estado IN ('PENDIENTE_PROGRAMAR', 'PROGRAMADA')
        ) AS rutinas_con_alerta_abierta
) AS revision;

-- Últimas cancelaciones y estado técnico resultante.
SELECT
    s.id AS solicitud_id,
    s.folio,
    s.tipo_solicitud,
    s.estado,
    e.codigo_equipo,
    e.nombre_equipo,
    COALESCE(
        NULLIF(TRIM(pm.motivo_cancelacion), ''),
        NULLIF(TRIM(s.motivo_ultima_edicion), ''),
        'SIN MOTIVO'
    ) AS motivo_cancelacion,
    hs.fecha_evento AS fecha_cancelacion,
    COUNT(DISTINCT st.tecnico_id) AS tecnicos_relacionados,
    SUM(CASE WHEN st.activo = 1 THEN 1 ELSE 0 END) AS asignaciones_activas,
    SUM(CASE WHEN em.estado IN ('PENDIENTE','EN_PROCESO','PAUSADA') THEN 1 ELSE 0 END) AS ejecuciones_abiertas,
    SUM(CASE WHEN em.estado = 'CANCELADA' THEN 1 ELSE 0 END) AS ejecuciones_canceladas
FROM solicitudes s
INNER JOIN equipos e ON e.id = s.equipo_id
LEFT JOIN programaciones_mantenimiento pm
       ON pm.id = (
            SELECT MAX(pm3.id)
            FROM programaciones_mantenimiento pm3
            WHERE pm3.solicitud_id = s.id
       )
LEFT JOIN historial_solicitudes hs
       ON hs.id = (
            SELECT MAX(hs2.id)
            FROM historial_solicitudes hs2
            WHERE hs2.solicitud_id = s.id
              AND hs2.evento = 'CANCELADA'
       )
LEFT JOIN solicitud_tecnicos st ON st.solicitud_id = s.id
LEFT JOIN ejecuciones_mantenimiento em ON em.solicitud_tecnico_id = st.id
WHERE s.estado = 'CANCELADO'
GROUP BY
    s.id, s.folio, s.tipo_solicitud, s.estado,
    e.codigo_equipo, e.nombre_equipo,
    pm.motivo_cancelacion, s.motivo_ultima_edicion,
    hs.fecha_evento
ORDER BY COALESCE(hs.fecha_evento, s.fecha_actualizacion) DESC
LIMIT 25;

-- Notificaciones recientes entregadas a técnicos.
SELECT
    n.id,
    n.usuario_id AS tecnico_id,
    n.solicitud_id,
    s.folio,
    n.titulo,
    n.mensaje,
    n.leida,
    n.fecha_creacion
FROM notificaciones n
INNER JOIN solicitudes s ON s.id = n.solicitud_id
WHERE n.tipo_usuario = 'TECNICO'
  AND n.titulo IN (
      'Mantenimiento cancelado',
      'Mantenimiento cancelado por administración'
  )
ORDER BY n.id DESC
LIMIT 25;

-- Trazabilidad individual de técnicos retirados por cancelación.
SELECT
    hs.id,
    hs.solicitud_id,
    s.folio,
    hs.solicitud_tecnico_id,
    st.tecnico_id,
    TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
    hs.estado_anterior,
    hs.estado_nuevo,
    hs.descripcion,
    hs.fecha_evento
FROM historial_solicitudes hs
INNER JOIN solicitudes s ON s.id = hs.solicitud_id
INNER JOIN solicitud_tecnicos st ON st.id = hs.solicitud_tecnico_id
INNER JOIN tecnicos t ON t.id = st.tecnico_id
WHERE hs.evento = 'TECNICO_RETIRADO'
  AND LOWER(hs.descripcion) LIKE '%cancel%'
ORDER BY hs.id DESC
LIMIT 25;
