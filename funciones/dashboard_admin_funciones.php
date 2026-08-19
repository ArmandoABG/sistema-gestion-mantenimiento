<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dashboard del administrador - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| Estados válidos de solicitudes:
| PENDIENTE, APROBADO, AGENDADO, EN_PROCESO, PAUSADO,
| ATRASADO, TERMINADO, RECHAZADO y CANCELADO.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_sesion(['ADMIN'], true);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function da_responder(
    bool $ok,
    string $mensaje,
    array $datos = [],
    int $codigoHttp = 200
): void {
    http_response_code($codigoHttp);

    echo json_encode(
        [
            'ok' => $ok,
            'mensaje' => $mensaje,
            'datos' => $datos,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function da_contar(PDO $conexion, string $sql, array $parametros = []): int
{
    $stmt = $conexion->prepare($sql);
    $stmt->execute($parametros);

    return (int) $stmt->fetchColumn();
}

function da_consultar(PDO $conexion, string $sql, array $parametros = []): array
{
    $stmt = $conexion->prepare($sql);
    $stmt->execute($parametros);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    da_responder(false, 'Método no permitido.', [], 405);
}

if (!($conexion instanceof PDO)) {
    da_responder(
        false,
        'No fue posible conectar con la base de datos.',
        [],
        503
    );
}

try {
    /*
    |--------------------------------------------------------------------------
    | Indicadores principales
    |--------------------------------------------------------------------------
    */

    $kpis = [
        /*
         * Una solicitud requiere revisión cuando:
         * - Es normal y está PENDIENTE.
         * - Es urgente, sigue activa y todavía no fue revisada por
         *   el administrador, aunque un técnico ya la haya iniciado o pausado.
         */
        'pendientes_revision' => da_contar(
            $conexion,
            "SELECT COUNT(*)
             FROM solicitudes
             WHERE activo = 1
               AND (
                    estado = 'PENDIENTE'
                    OR (
                        tipo_solicitud = 'CORRECTIVO_URGENTE'
                        AND estado IN (
                            'AGENDADO',
                            'EN_PROCESO',
                            'PAUSADO',
                            'ATRASADO'
                        )
                        AND revisado_por_admin_id IS NULL
                    )
               )"
        ),

        'por_programar' => da_contar(
            $conexion,
            "SELECT COUNT(*)
             FROM solicitudes
             WHERE activo = 1
               AND estado = 'APROBADO'
               AND tipo_solicitud <> 'CORRECTIVO_URGENTE'"
        ),

        'urgentes_abiertas' => da_contar(
            $conexion,
            "SELECT COUNT(*)
             FROM solicitudes
             WHERE activo = 1
               AND tipo_solicitud = 'CORRECTIVO_URGENTE'
               AND estado NOT IN (
                    'TERMINADO',
                    'RECHAZADO',
                    'CANCELADO'
               )"
        ),

        'actividades_hoy' => da_contar(
            $conexion,
            "SELECT COUNT(DISTINCT pm.solicitud_id)
             FROM programaciones_mantenimiento pm
             INNER JOIN solicitudes s
                ON s.id = pm.solicitud_id
             WHERE pm.es_actual = 1
               AND pm.fecha_programada = CURDATE()
               AND pm.estado IN ('PROGRAMADA', 'VENCIDA', 'CUMPLIDA')
               AND s.activo = 1
               AND s.estado NOT IN (
                    'TERMINADO',
                    'RECHAZADO',
                    'CANCELADO'
               )"
        ),

        'en_proceso' => da_contar(
            $conexion,
            "SELECT COUNT(*)
             FROM solicitudes
             WHERE activo = 1
               AND estado = 'EN_PROCESO'"
        ),

        'pausados' => da_contar(
            $conexion,
            "SELECT COUNT(*)
             FROM solicitudes
             WHERE activo = 1
               AND estado = 'PAUSADO'"
        ),

        'atrasados' => da_contar(
            $conexion,
            "SELECT COUNT(DISTINCT solicitud_id)
             FROM vw_mantenimientos_atrasados"
        ),

        'rutinas_pendientes' => da_contar(
            $conexion,
            "SELECT COUNT(*)
             FROM rutina_alertas
             WHERE estado = 'PENDIENTE_PROGRAMAR'
               AND fecha_notificacion <= CURDATE()"
        ),

        'terminados_hoy' => da_contar(
            $conexion,
            "SELECT COUNT(*)
             FROM cierres_mantenimiento
             WHERE DATE(fecha_hora_cierre) = CURDATE()"
        ),

        'terminados_mes' => da_contar(
            $conexion,
            "SELECT COUNT(*)
             FROM cierres_mantenimiento
             WHERE YEAR(fecha_hora_cierre) = YEAR(CURDATE())
               AND MONTH(fecha_hora_cierre) = MONTH(CURDATE())"
        ),

        /*
         * NO_REALIZADO pertenece a incumplimientos_mantenimiento,
         * no a solicitudes.estado.
         */
        'no_realizados_mes' => da_contar(
            $conexion,
            "SELECT COUNT(*)
             FROM incumplimientos_mantenimiento
             WHERE estado = 'NO_REALIZADO'
               AND YEAR(fecha_detectado) = YEAR(CURDATE())
               AND MONTH(fecha_detectado) = MONTH(CURDATE())"
        ),
    ];

    /*
    |--------------------------------------------------------------------------
    | Atención requerida
    |--------------------------------------------------------------------------
    */

    $prioridades = da_consultar(
        $conexion,
        "SELECT *
         FROM (
            SELECT
                s.id AS solicitud_id,
                s.folio,
                s.tipo_solicitud,
                s.estado,
                s.prioridad,
                s.fecha_solicitud AS fecha_referencia,
                NULL AS dias_atraso,
                e.codigo_equipo,
                e.nombre_equipo,
                COALESCE(a.nombre, '') AS area,

                CASE
                    WHEN s.tipo_solicitud = 'CORRECTIVO_URGENTE'
                        THEN 'URGENTE'
                    ELSE 'REVISION'
                END AS clase,

                CASE
                    WHEN s.tipo_solicitud = 'CORRECTIVO_URGENTE'
                         AND s.revisado_por_admin_id IS NULL
                        THEN 1
                    ELSE 0
                END AS requiere_revision

            FROM solicitudes s

            INNER JOIN equipos e
                ON e.id = s.equipo_id

            LEFT JOIN areas a
                ON a.id = s.area_id

            WHERE s.activo = 1
              AND (
                    s.estado = 'PENDIENTE'
                    OR (
                        s.tipo_solicitud = 'CORRECTIVO_URGENTE'
                        AND s.estado NOT IN (
                            'TERMINADO',
                            'RECHAZADO',
                            'CANCELADO'
                        )
                    )
              )

            UNION ALL

            SELECT
                ma.solicitud_id,
                ma.folio,
                ma.tipo_solicitud,
                ma.estado,
                'ALTA' AS prioridad,
                ma.fecha_limite AS fecha_referencia,
                MAX(ma.dias_atraso) AS dias_atraso,
                ma.codigo_equipo,
                ma.nombre_equipo,
                '' AS area,
                'ATRASADO' AS clase,
                0 AS requiere_revision

            FROM vw_mantenimientos_atrasados ma

            WHERE ma.tipo_solicitud <> 'CORRECTIVO_URGENTE'

            GROUP BY
                ma.solicitud_id,
                ma.folio,
                ma.tipo_solicitud,
                ma.estado,
                ma.fecha_limite,
                ma.codigo_equipo,
                ma.nombre_equipo
         ) pendientes

         ORDER BY
            FIELD(clase, 'URGENTE', 'ATRASADO', 'REVISION'),
            FIELD(prioridad, 'URGENTE', 'ALTA', 'MEDIA', 'BAJA'),
            fecha_referencia ASC,
            solicitud_id ASC

         LIMIT 10"
    );

    /*
    |--------------------------------------------------------------------------
    | Rutinas pendientes
    |--------------------------------------------------------------------------
    */

    $rutinas = da_consultar(
        $conexion,
        "SELECT
            alerta_id,
            rutina_id,
            nombre,
            tipo_rutina,
            prioridad,
            fecha_notificacion,
            codigo_equipo,
            nombre_equipo,
            area,
            nivel_riesgo,
            trabajo_peligroso
         FROM vw_rutinas_pendientes_programar
         WHERE fecha_notificacion <= CURDATE()
         ORDER BY
            fecha_notificacion ASC,
            FIELD(prioridad, 'ALTA', 'MEDIA', 'BAJA'),
            alerta_id ASC
         LIMIT 6"
    );

    /*
    |--------------------------------------------------------------------------
    | Actividades de hoy
    |--------------------------------------------------------------------------
    */

    $agendaHoy = da_consultar(
        $conexion,
        "SELECT
            v.solicitud_id,
            v.folio,
            v.tipo_solicitud,
            v.estado,
            v.prioridad,
            v.fecha_programada,
            v.fecha_limite,
            v.codigo_equipo,
            v.nombre_equipo,

            GROUP_CONCAT(
                DISTINCT NULLIF(v.tecnico, '')
                ORDER BY v.tecnico
                SEPARATOR ', '
            ) AS tecnicos,

            COUNT(DISTINCT v.tecnico_id) AS cantidad_tecnicos

         FROM vw_programacion_semanal v

         WHERE v.fecha_programada = CURDATE()

         GROUP BY
            v.solicitud_id,
            v.folio,
            v.tipo_solicitud,
            v.estado,
            v.prioridad,
            v.fecha_programada,
            v.fecha_limite,
            v.codigo_equipo,
            v.nombre_equipo

         ORDER BY
            FIELD(
                v.estado,
                'EN_PROCESO',
                'PAUSADO',
                'ATRASADO',
                'AGENDADO',
                'APROBADO',
                'PENDIENTE',
                'TERMINADO',
                'RECHAZADO',
                'CANCELADO'
            ),
            FIELD(v.prioridad, 'URGENTE', 'ALTA', 'MEDIA', 'BAJA'),
            v.folio ASC

         LIMIT 12"
    );

    /*
    |--------------------------------------------------------------------------
    | Carga de técnicos
    |--------------------------------------------------------------------------
    */

    $tecnicos = da_consultar(
        $conexion,
        "SELECT
            t.id,

            TRIM(
                CONCAT_WS(
                    ' ',
                    t.nombre,
                    t.apellido_paterno,
                    t.apellido_materno
                )
            ) AS tecnico,

            t.turno,

            COUNT(
                DISTINCT CASE
                    WHEN pm.fecha_programada = CURDATE()
                         AND st.activo = 1
                        THEN st.id
                    ELSE NULL
                END
            ) AS asignadas_hoy,

            SUM(
                CASE
                    WHEN st.estado = 'EN_PROCESO'
                         AND st.activo = 1
                        THEN 1
                    ELSE 0
                END
            ) AS en_proceso,

            SUM(
                CASE
                    WHEN st.estado = 'PAUSADO'
                         AND st.activo = 1
                        THEN 1
                    ELSE 0
                END
            ) AS pausadas,

            SUM(
                CASE
                    WHEN st.resultado_cumplimiento = 'TARDE'
                         AND YEAR(st.fecha_resultado) = YEAR(CURDATE())
                         AND MONTH(st.fecha_resultado) = MONTH(CURDATE())
                        THEN 1
                    ELSE 0
                END
            ) AS tarde_mes,

            SUM(
                CASE
                    WHEN st.resultado_cumplimiento = 'A_TIEMPO'
                         AND YEAR(st.fecha_resultado) = YEAR(CURDATE())
                         AND MONTH(st.fecha_resultado) = MONTH(CURDATE())
                        THEN 1
                    ELSE 0
                END
            ) AS a_tiempo_mes

         FROM tecnicos t

         LEFT JOIN solicitud_tecnicos st
            ON st.tecnico_id = t.id

         LEFT JOIN programaciones_mantenimiento pm
            ON pm.id = st.programacion_id
           AND pm.es_actual = 1

         WHERE t.activo = 1

         GROUP BY
            t.id,
            t.nombre,
            t.apellido_paterno,
            t.apellido_materno,
            t.turno

         ORDER BY
            asignadas_hoy DESC,
            tecnico ASC

         LIMIT 12"
    );

    /*
    |--------------------------------------------------------------------------
    | Cierres recientes
    |--------------------------------------------------------------------------
    */

    $cierresRecientes = da_consultar(
        $conexion,
        "SELECT
            s.id AS solicitud_id,
            s.folio,
            s.tipo_solicitud,
            s.prioridad,
            cm.fecha_hora_cierre,
            cm.trabajo_quedo,
            e.codigo_equipo,
            e.nombre_equipo,

            COALESCE(
                NULLIF(
                    TRIM(
                        CONCAT_WS(
                            ' ',
                            t.nombre,
                            t.apellido_paterno,
                            t.apellido_materno
                        )
                    ),
                    ''
                ),
                NULLIF(
                    TRIM(
                        CONCAT_WS(
                            ' ',
                            a.nombre,
                            a.apellido_paterno,
                            a.apellido_materno
                        )
                    ),
                    ''
                ),
                'Sin responsable'
            ) AS cerrado_por

         FROM cierres_mantenimiento cm

         INNER JOIN solicitudes s
            ON s.id = cm.solicitud_id

         INNER JOIN equipos e
            ON e.id = s.equipo_id

         LEFT JOIN tecnicos t
            ON t.id = cm.cerrado_por_tecnico_id

         LEFT JOIN administradores a
            ON a.id = cm.cerrado_por_admin_id

         ORDER BY
            cm.fecha_hora_cierre DESC,
            cm.id DESC

         LIMIT 8"
    );

    /*
    |--------------------------------------------------------------------------
    | Distribuciones
    |--------------------------------------------------------------------------
    */

    $estados = da_consultar(
        $conexion,
        "SELECT
            estado,
            COUNT(*) AS total
         FROM solicitudes
         WHERE activo = 1
         GROUP BY estado
         ORDER BY
            FIELD(
                estado,
                'PENDIENTE',
                'APROBADO',
                'AGENDADO',
                'EN_PROCESO',
                'PAUSADO',
                'ATRASADO',
                'TERMINADO',
                'RECHAZADO',
                'CANCELADO'
            )"
    );

    $tipos = da_consultar(
        $conexion,
        "SELECT
            tipo_solicitud AS tipo,
            COUNT(*) AS total
         FROM solicitudes
         WHERE activo = 1
         GROUP BY tipo_solicitud
         ORDER BY total DESC, tipo_solicitud ASC"
    );

    da_responder(
        true,
        'Dashboard actualizado correctamente.',
        [
            'kpis' => $kpis,
            'prioridades' => $prioridades,
            'rutinas' => $rutinas,
            'agenda_hoy' => $agendaHoy,
            'tecnicos' => $tecnicos,
            'cierres_recientes' => $cierresRecientes,
            'estados' => $estados,
            'tipos' => $tipos,
            'actualizado_en' => date('Y-m-d H:i:s'),
        ] 
    );
} catch (PDOException $e) {
    error_log('[DASHBOARD ADMIN][PDO] ' . $e->getMessage());

    da_responder(
        false,
        'No fue posible cargar la información del dashboard.',
        [],
        500
    );
} catch (Throwable $e) {
    error_log('[DASHBOARD ADMIN] ' . $e->getMessage());

    da_responder(
        false,
        'No fue posible cargar la información del dashboard.',
        [],
        500
    );
}