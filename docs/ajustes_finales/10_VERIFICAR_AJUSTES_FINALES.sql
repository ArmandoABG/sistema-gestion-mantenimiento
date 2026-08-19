/*
======================================================================
SISTEMA DE MANTENIMIENTO 1.1
VERIFICACIÓN DE AJUSTES FINALES
======================================================================
SOLO LECTURA: este archivo no crea, modifica ni elimina información.
Ejecutarlo después de abrir al menos una vez el catálogo actualizado.
*/

USE `sistema_mantenimiento_1.1`;

/* -------------------------------------------------------------------
   1. RESUMEN EN UNA SOLA FILA PARA CAPTURA
   ------------------------------------------------------------------- */
SELECT
    CASE
        WHEN revision.tablas_requeridas = 4
         AND revision.columnas_requeridas = 5
         AND revision.recursos_sin_codigo = 0
         AND revision.codigos_duplicados = 0
         AND revision.codigos_formato_incorrecto = 0
        THEN 'TODO CORRECTO'
        ELSE 'REVISAR RESULTADOS'
    END AS estado_general,
    revision.tablas_requeridas,
    revision.columnas_requeridas,
    revision.recursos_registrados,
    revision.recursos_sin_codigo,
    revision.codigos_duplicados,
    revision.codigos_formato_incorrecto,
    revision.secuencias_automaticas,
    revision.urgencias_con_recomendacion_admin,
    revision.equipos_con_memoria_urgente_admin,
    revision.peligrosos_sin_nota
FROM (
    SELECT
        (
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = 'sistema_mantenimiento_1.1'
              AND TABLE_NAME IN (
                  'recursos_mantenimiento',
                  'solicitud_recursos_recomendados',
                  'recomendaciones_recursos',
                  'configuracion_sistema'
              )
        ) AS tablas_requeridas,
        (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = 'sistema_mantenimiento_1.1'
              AND (
                    (TABLE_NAME = 'recursos_mantenimiento'
                     AND COLUMN_NAME IN ('tipo_recurso', 'codigo'))
                 OR (TABLE_NAME = 'solicitudes'
                     AND COLUMN_NAME IN (
                         'trabajo_peligroso',
                         'nivel_riesgo',
                         'detalle_trabajo_peligroso'
                     ))
              )
        ) AS columnas_requeridas,
        (
            SELECT COUNT(*)
            FROM `sistema_mantenimiento_1.1`.`recursos_mantenimiento`
        ) AS recursos_registrados,
        (
            SELECT COUNT(*)
            FROM `sistema_mantenimiento_1.1`.`recursos_mantenimiento`
            WHERE codigo IS NULL OR TRIM(codigo) = ''
        ) AS recursos_sin_codigo,
        (
            SELECT COUNT(*)
            FROM (
                SELECT tipo_recurso, codigo
                FROM `sistema_mantenimiento_1.1`.`recursos_mantenimiento`
                WHERE codigo IS NOT NULL AND TRIM(codigo) <> ''
                GROUP BY tipo_recurso, codigo
                HAVING COUNT(*) > 1
            ) AS duplicados
        ) AS codigos_duplicados,
        (
            SELECT COUNT(*)
            FROM `sistema_mantenimiento_1.1`.`recursos_mantenimiento`
            WHERE codigo IS NOT NULL
              AND TRIM(codigo) <> ''
              AND (
                    (tipo_recurso = 'HERRAMIENTA'
                     AND codigo NOT REGEXP '^HER-[0-9]{3,}$')
                 OR (tipo_recurso = 'REFACCION'
                     AND codigo NOT REGEXP '^REF-[0-9]{3,}$')
              )
        ) AS codigos_formato_incorrecto,
        (
            SELECT COUNT(*)
            FROM `sistema_mantenimiento_1.1`.`configuracion_sistema`
            WHERE clave IN (
                'SECUENCIA_RECURSO_HERRAMIENTA',
                'SECUENCIA_RECURSO_REFACCION'
            )
        ) AS secuencias_automaticas,
        (
            SELECT COUNT(DISTINCT srr.solicitud_id)
            FROM `sistema_mantenimiento_1.1`.`solicitud_recursos_recomendados` AS srr
            INNER JOIN `sistema_mantenimiento_1.1`.`solicitudes` AS s
                ON s.id = srr.solicitud_id
            WHERE s.tipo_solicitud = 'CORRECTIVO_URGENTE'
              AND srr.origen = 'ADMIN'
        ) AS urgencias_con_recomendacion_admin,
        (
            SELECT COUNT(DISTINCT rr.equipo_id)
            FROM `sistema_mantenimiento_1.1`.`recomendaciones_recursos` AS rr
            WHERE rr.tipo_solicitud = 'CORRECTIVO_URGENTE'
              AND rr.origen_ultima_actualizacion = 'ADMIN'
        ) AS equipos_con_memoria_urgente_admin,
        (
            SELECT COUNT(*)
            FROM `sistema_mantenimiento_1.1`.`solicitudes`
            WHERE trabajo_peligroso = 1
              AND (detalle_trabajo_peligroso IS NULL
                   OR TRIM(detalle_trabajo_peligroso) = '')
        ) AS peligrosos_sin_nota
) AS revision;

/* -------------------------------------------------------------------
   2. CATÁLOGO Y CÓDIGOS
   ------------------------------------------------------------------- */
SELECT
    id,
    tipo_recurso,
    codigo,
    nombre,
    activo,
    fecha_actualizacion
FROM `sistema_mantenimiento_1.1`.`recursos_mantenimiento`
ORDER BY
    tipo_recurso,
    CAST(SUBSTRING_INDEX(codigo, '-', -1) AS UNSIGNED),
    codigo,
    nombre;

/* -------------------------------------------------------------------
   3. ÚLTIMAS URGENCIAS Y SU FOTOGRAFÍA DE RECOMENDACIONES
   ------------------------------------------------------------------- */
SELECT
    s.id AS solicitud_id,
    s.folio,
    e.codigo AS codigo_equipo,
    e.nombre AS equipo,
    s.estado,
    COUNT(srr.id) AS recursos_recomendados,
    GROUP_CONCAT(
        CONCAT(
            srr.tipo_recurso,
            ': ',
            COALESCE(rm.codigo, 'SIN CÓDIGO'),
            ' - ',
            COALESCE(rm.nombre, srr.nombre_no_catalogado)
        )
        ORDER BY srr.tipo_recurso, COALESCE(rm.nombre, srr.nombre_no_catalogado)
        SEPARATOR ' | '
    ) AS detalle_recursos,
    GROUP_CONCAT(DISTINCT srr.origen ORDER BY srr.origen SEPARATOR ', ') AS origenes
FROM `sistema_mantenimiento_1.1`.`solicitudes` AS s
INNER JOIN `sistema_mantenimiento_1.1`.`equipos` AS e
    ON e.id = s.equipo_id
LEFT JOIN `sistema_mantenimiento_1.1`.`solicitud_recursos_recomendados` AS srr
    ON srr.solicitud_id = s.id
LEFT JOIN `sistema_mantenimiento_1.1`.`recursos_mantenimiento` AS rm
    ON rm.id = srr.recurso_id
WHERE s.tipo_solicitud = 'CORRECTIVO_URGENTE'
GROUP BY s.id, s.folio, e.codigo, e.nombre, s.estado
ORDER BY s.id DESC
LIMIT 20;

/* -------------------------------------------------------------------
   4. MEMORIA URGENTE SEPARADA POR EQUIPO
   ------------------------------------------------------------------- */
SELECT
    e.id AS equipo_id,
    e.codigo AS codigo_equipo,
    e.nombre AS equipo,
    rr.origen_ultima_actualizacion,
    COUNT(rr.id) AS recursos_en_memoria,
    GROUP_CONCAT(
        CONCAT(
            rr.tipo_recurso,
            ': ',
            COALESCE(rm.codigo, 'SIN CÓDIGO'),
            ' - ',
            COALESCE(rm.nombre, rr.nombre_no_catalogado)
        )
        ORDER BY rr.tipo_recurso, COALESCE(rm.nombre, rr.nombre_no_catalogado)
        SEPARATOR ' | '
    ) AS detalle_memoria,
    MAX(rr.fecha_actualizacion) AS ultima_actualizacion
FROM `sistema_mantenimiento_1.1`.`recomendaciones_recursos` AS rr
INNER JOIN `sistema_mantenimiento_1.1`.`equipos` AS e
    ON e.id = rr.equipo_id
LEFT JOIN `sistema_mantenimiento_1.1`.`recursos_mantenimiento` AS rm
    ON rm.id = rr.recurso_id
WHERE rr.tipo_solicitud = 'CORRECTIVO_URGENTE'
GROUP BY
    e.id,
    e.codigo,
    e.nombre,
    rr.origen_ultima_actualizacion
ORDER BY e.nombre, rr.origen_ultima_actualizacion;

/* -------------------------------------------------------------------
   5. REGISTROS PELIGROSOS ANTIGUOS QUE TODAVÍA NO TIENEN NOTA
   Esta consulta es informativa. Pueden ser solicitudes creadas antes de que
   existiera detalle_trabajo_peligroso.
   ------------------------------------------------------------------- */
SELECT
    id,
    folio,
    tipo_solicitud,
    estado,
    nivel_riesgo,
    fecha_registro
FROM `sistema_mantenimiento_1.1`.`solicitudes`
WHERE trabajo_peligroso = 1
  AND (detalle_trabajo_peligroso IS NULL
       OR TRIM(detalle_trabajo_peligroso) = '')
ORDER BY id DESC;
