<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_sesion(['TECNICO'], true);
sm_requerir_metodo('GET');

if (!($conexion instanceof PDO)) {
    sm_responder_json(false, 'No fue posible conectar con la base de datos.', [], 503);
}

$accion = sm_limpiar_texto($_GET['accion'] ?? 'inicial');

if ($accion !== 'inicial') {
    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
}

try {
    $tecnicoId = dtec_tecnico_id();
    $perfil = dtec_obtener_perfil($conexion, $tecnicoId);

    if (!$perfil) {
        sm_responder_json(
            false,
            'El técnico no existe o su cuenta está desactivada.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?acceso=desactivado',
            ],
            403
        );
    }

    $resumen = dtec_obtener_resumen($conexion, $tecnicoId);

    sm_responder_json(true, 'Dashboard actualizado.', [
        'perfil' => $perfil,
        'resumen' => $resumen,
        'actividades_actuales' => dtec_obtener_actividades_actuales($conexion, $tecnicoId),
        'urgencias_disponibles' => dtec_obtener_urgencias_disponibles($conexion, $tecnicoId),
        'asignados' => dtec_obtener_asignados($conexion, $tecnicoId),
        'pausados' => dtec_obtener_pausados($conexion, $tecnicoId),
        'mis_urgencias' => dtec_obtener_mis_urgencias($conexion, $tecnicoId),
        'finalizados_recientes' => dtec_obtener_finalizados($conexion, $tecnicoId),
        'avisos' => dtec_obtener_avisos($conexion, $tecnicoId),
        'avisos_no_leidos' => dtec_contar_avisos_no_leidos($conexion, $tecnicoId),
        'hora_servidor' => date('d/m/Y H:i:s'),
    ]);
} catch (PDOException $e) {
    error_log('[DASHBOARD TECNICO][PDO] ' . $e->getMessage());
    sm_responder_json(false, 'No fue posible cargar la información del dashboard.', [], 500);
} catch (Throwable $e) {
    error_log('[DASHBOARD TECNICO] ' . $e->getMessage());
    sm_responder_json(false, 'Ocurrió un error interno al cargar el dashboard.', [], 500);
}

function dtec_tecnico_id(): int
{
    $id = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        sm_responder_json(
            false,
            'No fue posible identificar al técnico de la sesión.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?sesion=expirada',
            ],
            401
        );
    }

    return (int) $id;
}

function dtec_obtener_perfil(PDO $conexion, int $tecnicoId): ?array
{
    $stmt = $conexion->prepare(
        "SELECT
            t.id,
            t.usuario,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS nombre_completo,
            t.telefono,
            t.correo,
            t.turno,
            t.especialidad,
            t.departamento_id,
            COALESCE(d.nombre, 'Departamento no asignado') AS departamento,
            t.ultimo_acceso
         FROM tecnicos t
         LEFT JOIN departamentos d ON d.id = t.departamento_id
         WHERE t.id = :tecnico_id
           AND t.activo = 1
         LIMIT 1"
    );
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($fila) ? $fila : null;
}

function dtec_obtener_resumen(PDO $conexion, int $tecnicoId): array
{
    return [
        'urgencias_disponibles' => dtec_contar(
            $conexion,
            "SELECT COUNT(*)
             FROM vw_urgentes_disponibles v
             WHERE NOT EXISTS (
                 SELECT 1
                 FROM solicitud_tecnicos st
                 WHERE st.solicitud_id = v.solicitud_id
                   AND st.tecnico_id = :tecnico_id
                   AND st.activo = 1
             )",
            [':tecnico_id' => $tecnicoId]
        ),
        'asignados_pendientes' => dtec_contar(
            $conexion,
            "SELECT COUNT(DISTINCT st.id)
             FROM solicitud_tecnicos st
             INNER JOIN solicitudes s ON s.id = st.solicitud_id
             LEFT JOIN programaciones_mantenimiento pm
                    ON pm.id = st.programacion_id
                   AND pm.es_actual = 1
             WHERE st.tecnico_id = :tecnico_id
               AND st.origen = 'ADMIN'
               AND st.activo = 1
               AND st.estado IN ('ASIGNADO','ACEPTADO')
               AND s.activo = 1
               AND s.estado IN ('AGENDADO','ATRASADO')",
            [':tecnico_id' => $tecnicoId]
        ),
        'en_proceso' => dtec_contar(
            $conexion,
            "SELECT COUNT(*)
             FROM ejecuciones_mantenimiento
             WHERE tecnico_id = :tecnico_id
               AND estado = 'EN_PROCESO'",
            [':tecnico_id' => $tecnicoId]
        ),
        'pausados' => dtec_contar(
            $conexion,
            "SELECT COUNT(*)
             FROM ejecuciones_mantenimiento
             WHERE tecnico_id = :tecnico_id
               AND estado = 'PAUSADA'",
            [':tecnico_id' => $tecnicoId]
        ),
        'listos_reanudar' => dtec_contar(
            $conexion,
            "SELECT COUNT(DISTINCT em.id)
             FROM ejecuciones_mantenimiento em
             INNER JOIN pausas_ejecucion pe
                     ON pe.id = (
                         SELECT MAX(pe2.id)
                         FROM pausas_ejecucion pe2
                         WHERE pe2.ejecucion_id = em.id
                           AND pe2.fecha_hora_fin IS NULL
                           AND pe2.pausa_abierta_token = 1
                     )
             LEFT JOIN solicitudes su ON su.id = pe.solicitud_urgente_id
             WHERE em.tecnico_id = :tecnico_id
               AND em.estado = 'PAUSADA'
               AND (
                    pe.motivo = 'MANUAL'
                    OR (
                        pe.motivo = 'URGENCIA'
                        AND su.estado IN ('TERMINADO','CANCELADO')
                    )
               )",
            [':tecnico_id' => $tecnicoId]
        ),
        'terminados_semana' => dtec_contar(
            $conexion,
            "SELECT COUNT(DISTINCT em.id)
             FROM ejecuciones_mantenimiento em
             WHERE em.tecnico_id = :tecnico_id
               AND em.estado = 'TERMINADA'
               AND em.fecha_hora_fin IS NOT NULL
               AND YEARWEEK(em.fecha_hora_fin, 1) = YEARWEEK(CURDATE(), 1)",
            [':tecnico_id' => $tecnicoId]
        ),
    ];
}

function dtec_obtener_actividades_actuales(PDO $conexion, int $tecnicoId): array
{
    return dtec_lista(
        $conexion,
        "SELECT
            em.id AS ejecucion_id,
            em.solicitud_id,
            em.solicitud_tecnico_id,
            em.fecha_hora_inicio,
            em.fecha_ultima_reanudacion,
            em.total_segundos_activos,
            em.total_segundos_pausa,
            s.folio,
            s.tipo_solicitud,
            s.estado AS estado_solicitud,
            s.prioridad,
            s.descripcion_solicitud,
            e.codigo_equipo,
            e.nombre_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso,
            CASE
                WHEN em.fecha_hora_inicio IS NULL THEN em.total_segundos_activos
                ELSE em.total_segundos_activos + GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        SECOND,
                        COALESCE(em.fecha_ultima_reanudacion, em.fecha_hora_inicio),
                        NOW()
                    )
                )
            END AS segundos_activos_estimados,
            CASE
                WHEN s.tipo_solicitud = 'CORRECTIVO_URGENTE' THEN (
                    SELECT COUNT(*)
                    FROM solicitud_tecnicos stp
                    WHERE stp.solicitud_id = s.id
                      AND stp.origen = 'ACEPTACION_URGENTE'
                      AND stp.activo = 1
                      AND stp.estado IN ('ACEPTADO','EN_PROCESO','PAUSADO')
                )
                ELSE 0
            END AS participantes_urgencia
         FROM ejecuciones_mantenimiento em
         INNER JOIN solicitudes s ON s.id = em.solicitud_id
         INNER JOIN solicitud_tecnicos st ON st.id = em.solicitud_tecnico_id
         INNER JOIN equipos e ON e.id = s.equipo_id
         INNER JOIN departamentos d ON d.id = s.departamento_id
         INNER JOIN areas a ON a.id = s.area_id
         INNER JOIN procesos p ON p.id = s.proceso_id
         WHERE em.tecnico_id = :tecnico_id
           AND em.estado = 'EN_PROCESO'
           AND st.activo = 1
           AND s.activo = 1
         ORDER BY
             CASE WHEN s.tipo_solicitud = 'CORRECTIVO_URGENTE' THEN 0 ELSE 1 END,
             em.fecha_hora_inicio ASC,
             em.id ASC",
        [':tecnico_id' => $tecnicoId]
    );
}

function dtec_obtener_urgencias_disponibles(PDO $conexion, int $tecnicoId): array
{
    return dtec_lista(
        $conexion,
        "SELECT
            v.solicitud_id,
            v.folio,
            v.fecha_solicitud,
            v.hora_solicitud,
            v.descripcion_solicitud,
            v.impacto_operacion,
            v.trabajo_peligroso,
            v.nivel_riesgo,
            v.codigo_equipo,
            v.nombre_equipo,
            v.departamento,
            v.area,
            v.proceso,
            v.tecnicos_aceptaron,
            v.limite_tecnicos,
            v.lugares_disponibles,
            s.estado,
            s.requiere_paro_equipo
         FROM vw_urgentes_disponibles v
         INNER JOIN solicitudes s ON s.id = v.solicitud_id
         WHERE NOT EXISTS (
             SELECT 1
             FROM solicitud_tecnicos st
             WHERE st.solicitud_id = v.solicitud_id
               AND st.tecnico_id = :tecnico_id
               AND st.activo = 1
         )
         ORDER BY
             CASE WHEN s.estado = 'EN_PROCESO' THEN 0 ELSE 1 END,
             FIELD(v.nivel_riesgo, 'ALTO','MEDIO','BAJO'),
             v.fecha_solicitud ASC,
             v.hora_solicitud ASC,
             v.solicitud_id ASC
         LIMIT 6",
        [':tecnico_id' => $tecnicoId]
    );
}

function dtec_obtener_asignados(PDO $conexion, int $tecnicoId): array
{
    return dtec_lista(
        $conexion,
        "SELECT
            st.id AS solicitud_tecnico_id,
            s.id AS solicitud_id,
            s.folio,
            s.tipo_solicitud,
            s.estado,
            s.prioridad,
            s.descripcion_solicitud,
            pm.fecha_programada,
            pm.fecha_limite,
            pm.estado AS estado_programacion,
            e.codigo_equipo,
            e.nombre_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso
         FROM solicitud_tecnicos st
         INNER JOIN solicitudes s ON s.id = st.solicitud_id
         LEFT JOIN programaciones_mantenimiento pm
                ON pm.id = st.programacion_id
               AND pm.es_actual = 1
         INNER JOIN equipos e ON e.id = s.equipo_id
         INNER JOIN departamentos d ON d.id = s.departamento_id
         INNER JOIN areas a ON a.id = s.area_id
         INNER JOIN procesos p ON p.id = s.proceso_id
         WHERE st.tecnico_id = :tecnico_id
           AND st.origen = 'ADMIN'
           AND st.activo = 1
           AND st.estado IN ('ASIGNADO','ACEPTADO')
           AND s.activo = 1
           AND s.estado IN ('AGENDADO','ATRASADO')
         ORDER BY
             CASE WHEN s.estado = 'ATRASADO' THEN 0 ELSE 1 END,
             CASE WHEN pm.fecha_programada IS NULL THEN 1 ELSE 0 END,
             pm.fecha_programada ASC,
             FIELD(s.prioridad, 'URGENTE','ALTA','MEDIA','BAJA'),
             s.id ASC
         LIMIT 8",
        [':tecnico_id' => $tecnicoId]
    );
}

function dtec_obtener_pausados(PDO $conexion, int $tecnicoId): array
{
    return dtec_lista(
        $conexion,
        "SELECT
            em.id AS ejecucion_id,
            em.solicitud_id,
            em.solicitud_tecnico_id,
            em.fecha_hora_inicio,
            em.total_segundos_pausa,
            s.folio,
            s.tipo_solicitud,
            s.estado AS estado_solicitud,
            e.codigo_equipo,
            e.nombre_equipo,
            a.nombre AS area,
            pe.id AS pausa_id,
            pe.motivo AS motivo_pausa,
            pe.fecha_hora_inicio AS pausa_iniciada_en,
            pe.solicitud_urgente_id,
            TIMESTAMPDIFF(SECOND, pe.fecha_hora_inicio, NOW()) AS segundos_pausa_actual,
            su.folio AS folio_urgencia,
            su.estado AS estado_urgencia,
            CASE
                WHEN pe.motivo = 'MANUAL' THEN 1
                WHEN pe.motivo = 'URGENCIA'
                     AND su.estado IN ('TERMINADO','CANCELADO') THEN 1
                ELSE 0
            END AS puede_reanudar
         FROM ejecuciones_mantenimiento em
         INNER JOIN solicitudes s ON s.id = em.solicitud_id
         INNER JOIN equipos e ON e.id = s.equipo_id
         INNER JOIN areas a ON a.id = s.area_id
         LEFT JOIN pausas_ejecucion pe
                ON pe.id = (
                    SELECT MAX(pe2.id)
                    FROM pausas_ejecucion pe2
                    WHERE pe2.ejecucion_id = em.id
                      AND pe2.fecha_hora_fin IS NULL
                      AND pe2.pausa_abierta_token = 1
                )
         LEFT JOIN solicitudes su ON su.id = pe.solicitud_urgente_id
         WHERE em.tecnico_id = :tecnico_id
           AND em.estado = 'PAUSADA'
           AND s.activo = 1
         ORDER BY
             CASE
                 WHEN pe.motivo = 'MANUAL' THEN 0
                 WHEN pe.motivo = 'URGENCIA' AND su.estado IN ('TERMINADO','CANCELADO') THEN 0
                 ELSE 1
             END,
             pe.fecha_hora_inicio ASC,
             em.id ASC
         LIMIT 8",
        [':tecnico_id' => $tecnicoId]
    );
}

function dtec_obtener_mis_urgencias(PDO $conexion, int $tecnicoId): array
{
    return dtec_lista(
        $conexion,
        "SELECT
            st.id AS solicitud_tecnico_id,
            st.solicitud_id,
            st.estado AS estado_participacion,
            st.fecha_aceptacion,
            s.folio,
            s.estado AS estado_solicitud,
            s.nivel_riesgo,
            e.codigo_equipo,
            e.nombre_equipo,
            a.nombre AS area,
            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos stp
                WHERE stp.solicitud_id = s.id
                  AND stp.origen = 'ACEPTACION_URGENTE'
                  AND stp.activo = 1
                  AND stp.estado IN ('ACEPTADO','EN_PROCESO','PAUSADO')
            ) AS participantes
         FROM solicitud_tecnicos st
         INNER JOIN solicitudes s ON s.id = st.solicitud_id
         INNER JOIN equipos e ON e.id = s.equipo_id
         INNER JOIN areas a ON a.id = s.area_id
         WHERE st.tecnico_id = :tecnico_id
           AND st.origen = 'ACEPTACION_URGENTE'
           AND st.activo = 1
           AND st.estado IN ('ACEPTADO','EN_PROCESO','PAUSADO')
           AND s.activo = 1
           AND s.estado IN ('AGENDADO','EN_PROCESO','PAUSADO','ATRASADO')
         ORDER BY
             FIELD(st.estado, 'EN_PROCESO','PAUSADO','ACEPTADO'),
             st.fecha_aceptacion ASC,
             st.id ASC
         LIMIT 8",
        [':tecnico_id' => $tecnicoId]
    );
}

function dtec_obtener_finalizados(PDO $conexion, int $tecnicoId): array
{
    return dtec_lista(
        $conexion,
        "SELECT
            em.id AS ejecucion_id,
            em.solicitud_id,
            em.fecha_hora_fin,
            em.total_segundos_activos,
            em.total_segundos_pausa,
            s.folio,
            s.tipo_solicitud,
            s.prioridad,
            e.codigo_equipo,
            e.nombre_equipo,
            a.nombre AS area,
            cm.trabajo_quedo,
            cm.fecha_hora_cierre
         FROM ejecuciones_mantenimiento em
         INNER JOIN solicitudes s ON s.id = em.solicitud_id
         INNER JOIN equipos e ON e.id = s.equipo_id
         INNER JOIN areas a ON a.id = s.area_id
         LEFT JOIN cierres_mantenimiento cm ON cm.solicitud_id = s.id
         WHERE em.tecnico_id = :tecnico_id
           AND em.estado = 'TERMINADA'
         ORDER BY em.fecha_hora_fin DESC, em.id DESC
         LIMIT 6",
        [':tecnico_id' => $tecnicoId]
    );
}

function dtec_obtener_avisos(PDO $conexion, int $tecnicoId): array
{
    return dtec_lista(
        $conexion,
        "SELECT
            n.id,
            n.solicitud_id,
            n.ejecucion_id,
            n.titulo,
            n.mensaje,
            n.tipo,
            n.leida,
            n.fecha_creacion
         FROM notificaciones n
         WHERE n.tipo_usuario = 'TECNICO'
           AND n.usuario_id = :tecnico_id
         ORDER BY n.leida ASC, n.fecha_creacion DESC, n.id DESC
         LIMIT 8",
        [':tecnico_id' => $tecnicoId]
    );
}

function dtec_contar_avisos_no_leidos(PDO $conexion, int $tecnicoId): int
{
    return dtec_contar(
        $conexion,
        "SELECT COUNT(*)
         FROM notificaciones
         WHERE tipo_usuario = 'TECNICO'
           AND usuario_id = :tecnico_id
           AND leida = 0",
        [':tecnico_id' => $tecnicoId]
    );
}

function dtec_contar(PDO $conexion, string $sql, array $parametros = []): int
{
    $stmt = $conexion->prepare($sql);
    foreach ($parametros as $clave => $valor) {
        $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
 
    return (int) $stmt->fetchColumn();
}

function dtec_lista(PDO $conexion, string $sql, array $parametros = []): array
{
    $stmt = $conexion->prepare($sql);
    foreach ($parametros as $clave => $valor) {
        $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($filas) ? $filas : [];
}