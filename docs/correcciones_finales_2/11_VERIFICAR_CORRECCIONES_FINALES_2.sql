-- ============================================================
-- SISTEMA DE MANTENIMIENTO 1.1
-- VERIFICACIÓN DE CORRECCIONES FINALES 2
-- SOLO LECTURA
-- ============================================================

USE `sistema_mantenimiento_1.1`;

SELECT
    CASE
        WHEN revision.asignaciones_activas_incorrectas = 0
         AND revision.ejecuciones_abiertas_incorrectas = 0
         AND revision.pausas_propias_abiertas_incorrectas = 0
         AND revision.canceladas_con_cierre = 0
        THEN 'TODO CORRECTO'
        ELSE 'REVISAR INCONSISTENCIAS'
    END AS estado_general,
    revision.urgencias_canceladas,
    revision.asignaciones_activas_incorrectas,
    revision.ejecuciones_abiertas_incorrectas,
    revision.pausas_propias_abiertas_incorrectas,
    revision.canceladas_con_cierre
FROM (
    SELECT
        (
            SELECT COUNT(*)
            FROM solicitudes s
            WHERE s.tipo_solicitud = 'CORRECTIVO_URGENTE'
              AND s.estado = 'CANCELADO'
        ) AS urgencias_canceladas,
        (
            SELECT COUNT(*)
            FROM solicitud_tecnicos st
            INNER JOIN solicitudes s ON s.id = st.solicitud_id
            WHERE s.tipo_solicitud = 'CORRECTIVO_URGENTE'
              AND s.estado = 'CANCELADO'
              AND (
                    st.activo = 1
                    OR st.estado IN ('ASIGNADO','ACEPTADO','EN_PROCESO','PAUSADO')
              )
        ) AS asignaciones_activas_incorrectas,
        (
            SELECT COUNT(*)
            FROM ejecuciones_mantenimiento em
            INNER JOIN solicitudes s ON s.id = em.solicitud_id
            WHERE s.tipo_solicitud = 'CORRECTIVO_URGENTE'
              AND s.estado = 'CANCELADO'
              AND em.estado IN ('PENDIENTE','EN_PROCESO','PAUSADA')
        ) AS ejecuciones_abiertas_incorrectas,
        (
            SELECT COUNT(*)
            FROM pausas_ejecucion pe
            INNER JOIN ejecuciones_mantenimiento em ON em.id = pe.ejecucion_id
            INNER JOIN solicitudes s ON s.id = em.solicitud_id
            WHERE s.tipo_solicitud = 'CORRECTIVO_URGENTE'
              AND s.estado = 'CANCELADO'
              AND pe.pausa_abierta_token = 1
        ) AS pausas_propias_abiertas_incorrectas,
        (
            SELECT COUNT(*)
            FROM solicitudes s
            INNER JOIN cierres_mantenimiento cm ON cm.solicitud_id = s.id
            WHERE s.tipo_solicitud = 'CORRECTIVO_URGENTE'
              AND s.estado = 'CANCELADO'
        ) AS canceladas_con_cierre
) AS revision;

-- Detalle de cualquier inconsistencia de asignaciones.
SELECT
    s.folio,
    s.estado AS estado_solicitud,
    st.id AS asignacion_id,
    st.tecnico_id,
    st.estado AS estado_asignacion,
    st.activo
FROM solicitudes s
INNER JOIN solicitud_tecnicos st ON st.solicitud_id = s.id
WHERE s.tipo_solicitud = 'CORRECTIVO_URGENTE'
  AND s.estado = 'CANCELADO'
  AND (
        st.activo = 1
        OR st.estado IN ('ASIGNADO','ACEPTADO','EN_PROCESO','PAUSADO')
  )
ORDER BY s.id DESC, st.id;

-- Detalle de ejecuciones que no hayan quedado canceladas/cerradas.
SELECT
    s.folio,
    s.estado AS estado_solicitud,
    em.id AS ejecucion_id,
    em.tecnico_id,
    em.estado AS estado_ejecucion,
    em.fecha_hora_inicio,
    em.fecha_hora_fin
FROM solicitudes s
INNER JOIN ejecuciones_mantenimiento em ON em.solicitud_id = s.id
WHERE s.tipo_solicitud = 'CORRECTIVO_URGENTE'
  AND s.estado = 'CANCELADO'
  AND em.estado IN ('PENDIENTE','EN_PROCESO','PAUSADA')
ORDER BY s.id DESC, em.id;

-- Configuración relacionada con la antigua lógica de límites.
SELECT clave, valor, descripcion
FROM configuracion_sistema
WHERE clave IN ('USA_HORAS_ESTIMADAS', 'PERMITIR_EJECUTAR_ATRASADOS')
ORDER BY clave;
