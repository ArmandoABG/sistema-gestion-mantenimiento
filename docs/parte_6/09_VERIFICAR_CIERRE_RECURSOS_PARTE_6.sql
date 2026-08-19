-- =====================================================================
-- SISTEMA DE MANTENIMIENTO 1.1
-- PARTE 6 · VERIFICACION DE CIERRE, HISTORIAL Y APRENDIZAJE URGENTE
-- SOLO LECTURA: NO CREA, MODIFICA NI ELIMINA INFORMACION
-- =====================================================================

USE `sistema_mantenimiento_1.1`;

-- 1. Resumen rápido de estructura e invariantes principales.
SELECT
    CASE
        WHEN revision.tablas_requeridas = 6
         AND revision.columnas_cierre = 2
         AND revision.columnas_sugerencias = 4
         AND revision.recursos_inconsistentes = 0
         AND revision.cierres_contradiccion = 0
         AND revision.aprendizaje_fuera_de_urgencias = 0
        THEN 'TODO CORRECTO'
        ELSE 'REVISAR RESULTADOS'
    END AS estado_general,
    revision.tablas_requeridas,
    revision.columnas_cierre,
    revision.columnas_sugerencias,
    revision.recursos_inconsistentes,
    revision.cierres_contradiccion,
    revision.aprendizaje_fuera_de_urgencias
FROM (
    SELECT
        (
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = 'sistema_mantenimiento_1.1'
              AND TABLE_NAME IN (
                  'recursos_mantenimiento',
                  'recomendaciones_recursos',
                  'solicitud_recursos_recomendados',
                  'rutina_recursos',
                  'cierre_recursos_utilizados',
                  'sugerencias_recursos'
              )
        ) AS tablas_requeridas,
        (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = 'sistema_mantenimiento_1.1'
              AND TABLE_NAME = 'cierres_mantenimiento'
              AND COLUMN_NAME IN (
                  'sin_herramientas_utilizadas',
                  'sin_refacciones_utilizadas'
              )
        ) AS columnas_cierre,
        (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = 'sistema_mantenimiento_1.1'
              AND TABLE_NAME = 'sugerencias_recursos'
              AND COLUMN_NAME IN (
                  'estado',
                  'recurso_creado_id',
                  'atendida_por_admin_id',
                  'fecha_atencion'
              )
        ) AS columnas_sugerencias,
        (
            SELECT COUNT(*)
            FROM `sistema_mantenimiento_1.1`.`cierre_recursos_utilizados` cru
            WHERE
                (cru.recurso_id IS NULL AND NULLIF(TRIM(cru.nombre_no_catalogado), '') IS NULL)
                OR
                (cru.recurso_id IS NOT NULL AND NULLIF(TRIM(cru.nombre_no_catalogado), '') IS NOT NULL)
        ) AS recursos_inconsistentes,
        (
            SELECT COUNT(*)
            FROM `sistema_mantenimiento_1.1`.`cierres_mantenimiento` cm
            WHERE
                (
                    cm.sin_herramientas_utilizadas = 1
                    AND EXISTS (
                        SELECT 1
                        FROM `sistema_mantenimiento_1.1`.`cierre_recursos_utilizados` ch
                        WHERE ch.cierre_id = cm.id
                          AND ch.tipo_recurso = 'HERRAMIENTA'
                    )
                )
                OR
                (
                    cm.sin_refacciones_utilizadas = 1
                    AND EXISTS (
                        SELECT 1
                        FROM `sistema_mantenimiento_1.1`.`cierre_recursos_utilizados` cr
                        WHERE cr.cierre_id = cm.id
                          AND cr.tipo_recurso = 'REFACCION'
                    )
                )
        ) AS cierres_contradiccion,
        (
            SELECT COUNT(*)
            FROM `sistema_mantenimiento_1.1`.`recomendaciones_recursos` rr
            WHERE rr.origen_ultima_actualizacion = 'CIERRE_TECNICO'
              AND rr.tipo_solicitud <> 'CORRECTIVO_URGENTE'
        ) AS aprendizaje_fuera_de_urgencias
) AS revision;

-- 2. Ultimos cierres y lo que realmente se registró.
SELECT
    s.id AS solicitud_id,
    s.folio,
    s.tipo_solicitud,
    e.codigo_equipo,
    e.nombre_equipo,
    cm.id AS cierre_id,
    cm.fecha_hora_cierre,
    cm.sin_herramientas_utilizadas,
    cm.sin_refacciones_utilizadas,
    SUM(cru.tipo_recurso = 'HERRAMIENTA') AS herramientas_registradas,
    SUM(cru.tipo_recurso = 'REFACCION') AS refacciones_registradas,
    GROUP_CONCAT(
        DISTINCT CASE
            WHEN cru.tipo_recurso = 'HERRAMIENTA'
            THEN COALESCE(r.nombre, cru.nombre_no_catalogado)
        END
        ORDER BY COALESCE(r.nombre, cru.nombre_no_catalogado)
        SEPARATOR ' | '
    ) AS herramientas_utilizadas,
    GROUP_CONCAT(
        DISTINCT CASE
            WHEN cru.tipo_recurso = 'REFACCION'
            THEN COALESCE(r.nombre, cru.nombre_no_catalogado)
        END
        ORDER BY COALESCE(r.nombre, cru.nombre_no_catalogado)
        SEPARATOR ' | '
    ) AS refacciones_utilizadas
FROM `sistema_mantenimiento_1.1`.`cierres_mantenimiento` cm
INNER JOIN `sistema_mantenimiento_1.1`.`solicitudes` s ON s.id = cm.solicitud_id
INNER JOIN `sistema_mantenimiento_1.1`.`equipos` e ON e.id = s.equipo_id
LEFT JOIN `sistema_mantenimiento_1.1`.`cierre_recursos_utilizados` cru ON cru.cierre_id = cm.id
LEFT JOIN `sistema_mantenimiento_1.1`.`recursos_mantenimiento` r ON r.id = cru.recurso_id
GROUP BY
    s.id, s.folio, s.tipo_solicitud,
    e.codigo_equipo, e.nombre_equipo,
    cm.id, cm.fecha_hora_cierre,
    cm.sin_herramientas_utilizadas,
    cm.sin_refacciones_utilizadas
ORDER BY cm.fecha_hora_cierre DESC, cm.id DESC
LIMIT 20;

-- 3. Sugerencias enviadas por técnicos y su estado administrativo.
SELECT
    sr.id AS sugerencia_id,
    sr.estado,
    sr.tipo_recurso,
    sr.nombre_sugerido,
    s.folio,
    s.tipo_solicitud,
    e.codigo_equipo,
    e.nombre_equipo,
    TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
    r.nombre AS recurso_oficial,
    TRIM(CONCAT_WS(' ', a.nombre, a.apellido_paterno, a.apellido_materno)) AS atendida_por,
    sr.observaciones_admin,
    sr.fecha_registro,
    sr.fecha_atencion
FROM `sistema_mantenimiento_1.1`.`sugerencias_recursos` sr
INNER JOIN `sistema_mantenimiento_1.1`.`cierre_recursos_utilizados` cru
        ON cru.id = sr.cierre_recurso_utilizado_id
INNER JOIN `sistema_mantenimiento_1.1`.`cierres_mantenimiento` cm ON cm.id = cru.cierre_id
INNER JOIN `sistema_mantenimiento_1.1`.`solicitudes` s ON s.id = cm.solicitud_id
INNER JOIN `sistema_mantenimiento_1.1`.`equipos` e ON e.id = s.equipo_id
INNER JOIN `sistema_mantenimiento_1.1`.`tecnicos` t ON t.id = sr.tecnico_id
LEFT JOIN `sistema_mantenimiento_1.1`.`recursos_mantenimiento` r ON r.id = sr.recurso_creado_id
LEFT JOIN `sistema_mantenimiento_1.1`.`administradores` a ON a.id = sr.atendida_por_admin_id
ORDER BY
    CASE sr.estado WHEN 'PENDIENTE' THEN 0 ELSE 1 END,
    sr.fecha_registro DESC,
    sr.id DESC
LIMIT 50;

-- 4. Memoria vigente de urgencias. CIERRE_TECNICO indica aprendizaje
--    automático; ADMIN indica una recomendación que prevalece.
SELECT
    rr.equipo_id,
    e.codigo_equipo,
    e.nombre_equipo,
    rr.tipo_recurso,
    COALESCE(r.nombre, rr.nombre_no_catalogado) AS recurso_recomendado,
    rr.origen_ultima_actualizacion,
    so.folio AS solicitud_origen,
    TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico_origen,
    TRIM(CONCAT_WS(' ', a.nombre, a.apellido_paterno, a.apellido_materno)) AS admin_origen,
    rr.fecha_actualizacion
FROM `sistema_mantenimiento_1.1`.`recomendaciones_recursos` rr
INNER JOIN `sistema_mantenimiento_1.1`.`equipos` e ON e.id = rr.equipo_id
LEFT JOIN `sistema_mantenimiento_1.1`.`recursos_mantenimiento` r ON r.id = rr.recurso_id
LEFT JOIN `sistema_mantenimiento_1.1`.`solicitudes` so ON so.id = rr.solicitud_origen_id
LEFT JOIN `sistema_mantenimiento_1.1`.`tecnicos` t ON t.id = rr.actualizado_por_tecnico_id
LEFT JOIN `sistema_mantenimiento_1.1`.`administradores` a ON a.id = rr.actualizado_por_admin_id
WHERE rr.tipo_solicitud = 'CORRECTIVO_URGENTE'
ORDER BY e.nombre_equipo, rr.tipo_recurso, recurso_recomendado;

-- 5. Confirmación resumida de que los otros tipos no fueron aprendidos
--    automáticamente desde cierres técnicos.
SELECT
    rr.tipo_solicitud,
    rr.origen_ultima_actualizacion,
    COUNT(*) AS recursos_en_memoria
FROM `sistema_mantenimiento_1.1`.`recomendaciones_recursos` rr
GROUP BY rr.tipo_solicitud, rr.origen_ultima_actualizacion
ORDER BY rr.tipo_solicitud, rr.origen_ultima_actualizacion;
