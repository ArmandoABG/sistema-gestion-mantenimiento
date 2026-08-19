<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Agenda semanal - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| - Exclusivo para ADMIN.
| - Consulta la semana operativa actual o cualquier semana seleccionada.
| - Muestra programaciones vigentes y urgencias publicadas sin programación.
| - No modifica fechas ni técnicos: esas operaciones se realizan en
|   "Programar y asignar" para mantener una sola fuente de verdad.
| - Sincroniza programaciones vencidas e incumplimientos pendientes antes de
|   mostrar la agenda, siguiendo las reglas actuales de la base de datos.
| - Compatible con PHP 7.4 o superior.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_sesion(['ADMIN'], true);

if (!($conexion instanceof PDO)) {
    sm_responder_json(
        false,
        'No fue posible conectar con la base de datos.',
        [],
        503
    );
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = sm_limpiar_texto($_GET['accion'] ?? 'inicial');

try {
    sm_requerir_metodo('GET');
    aseg_validar_admin_activo($conexion, aseg_admin_id());

    if ($accion === 'inicial') {
        aseg_endpoint_inicial($conexion);
    }

    if ($accion === 'detalle') {
        aseg_endpoint_detalle($conexion);
    }

    sm_responder_json(
        false,
        'La acción solicitada no es válida.',
        [],
        400
    );
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[AGENDA SEMANAL][PDO] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error al consultar la agenda semanal.',
        [],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[AGENDA SEMANAL] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error interno al preparar la agenda.',
        [],
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function aseg_endpoint_inicial(PDO $conexion): void
{
    aseg_sincronizar_atrasos($conexion);

    $semana = aseg_semana_desde_entrada($_GET['semana'] ?? null);
    $inicio = $semana['inicio'];
    $fin = $semana['fin'];

    $actividades = aseg_consultar_actividades($conexion, $inicio, $fin);
    aseg_adjuntar_tecnicos($conexion, $actividades);

    $dias = aseg_consultar_calendario($conexion, $inicio, $fin);
    $resumen = aseg_construir_resumen($actividades);
    $cargaTecnicos = aseg_construir_carga_tecnicos($conexion, $actividades);

    sm_responder_json(
        true,
        'Agenda semanal cargada correctamente.',
        [
            'semana' => [
                'inicio' => $inicio,
                'fin' => $fin,
                'dias' => $dias,
            ],
            'resumen' => $resumen,
            'actividades' => array_values($actividades),
            'carga_tecnicos' => $cargaTecnicos,
            'catalogos' => [
                'tecnicos' => aseg_catalogo_tecnicos($conexion),
                'tipos' => [
                    'CORRECTIVO_PROGRAMABLE',
                    'MODIFICACION_MEJORA',
                    'CORRECTIVO_URGENTE',
                    'RUTINARIO',
                ],
                'estados_vista' => [
                    'POR_INICIAR',
                    'EN_CURSO',
                    'ATRASADO',
                    'TERMINADO',
                ],
            ],
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function aseg_endpoint_detalle(PDO $conexion): void
{
    $solicitudId = aseg_entero_positivo($_GET['solicitud_id'] ?? null, 'solicitud_id');
    $solicitud = aseg_consultar_detalle($conexion, $solicitudId);

    if (!$solicitud) {
        sm_responder_json(
            false,
            'La solicitud no existe o ya no está disponible.',
            [],
            404
        );
    }

    $tecnicos = aseg_consultar_tecnicos_detalle($conexion, $solicitudId);

    sm_responder_json(
        true,
        'Detalle cargado correctamente.',
        [
            'solicitud' => $solicitud,
            'tecnicos' => $tecnicos,
            'acciones' => [
                'puede_programar' => !in_array(
                    (string) $solicitud['estado'],
                    ['TERMINADO', 'RECHAZADO', 'CANCELADO'],
                    true
                ) && (string) $solicitud['tipo_solicitud'] !== 'CORRECTIVO_URGENTE',
                'puede_ver_expediente' => true,
            ],
        ]
    );
}

/* =========================================================================
   CONSULTAS DE LA AGENDA
   ========================================================================= */

function aseg_consultar_actividades(
    PDO $conexion,
    string $inicio,
    string $fin
): array {
    $sql = "
        SELECT
            s.id,
            s.folio,
            s.tipo_solicitud,
            s.estado,
            s.prioridad,
            s.fecha_solicitud,
            s.hora_solicitud,
            s.descripcion_solicitud,
            s.trabajo_peligroso,
            s.nivel_riesgo,
            s.requiere_paro_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso,
            e.codigo_equipo,
            e.nombre_equipo,
            pm.id AS programacion_id,
            pm.fecha_programada,
            pm.fecha_limite,
            pm.estado AS estado_programacion,
            CASE
                WHEN pm.id IS NOT NULL THEN pm.fecha_programada
                ELSE s.fecha_solicitud
            END AS fecha_agenda,
            cm.fecha_hora_cierre,
            cm.trabajo_quedo,
            COALESCE(es.total_ejecuciones, 0) AS total_ejecuciones,
            COALESCE(es.total_iniciadas, 0) AS total_iniciadas,
            COALESCE(es.total_en_proceso, 0) AS total_en_proceso,
            COALESCE(es.total_pausadas, 0) AS total_pausadas,
            COALESCE(es.total_terminadas, 0) AS total_terminadas,
            COALESCE(es.total_segundos_activos, 0) AS total_segundos_activos,
            COALESCE(es.total_segundos_pausa, 0) AS total_segundos_pausa,
            COALESCE(rs.total_asignados, 0) AS total_asignados,
            COALESCE(rs.total_a_tiempo, 0) AS total_a_tiempo,
            COALESCE(rs.total_tarde, 0) AS total_tarde,
            COALESCE(rs.total_no_realizado, 0) AS total_no_realizado,
            CASE
                WHEN s.estado = 'TERMINADO' THEN 'TERMINADO'
                WHEN s.estado IN ('EN_PROCESO', 'PAUSADO') THEN 'EN_CURSO'
                WHEN s.estado = 'ATRASADO'
                     OR pm.estado = 'VENCIDA'
                     OR (
                        pm.fecha_limite IS NOT NULL
                        AND pm.fecha_limite < CURDATE()
                        AND s.estado NOT IN ('TERMINADO', 'RECHAZADO', 'CANCELADO')
                     )
                    THEN 'ATRASADO'
                ELSE 'POR_INICIAR'
            END AS grupo_estado,
            CASE
                WHEN s.estado NOT IN ('TERMINADO', 'RECHAZADO', 'CANCELADO')
                     AND pm.fecha_limite IS NOT NULL
                     AND pm.fecha_limite < CURDATE()
                    THEN DATEDIFF(CURDATE(), pm.fecha_limite)
                WHEN s.estado = 'TERMINADO'
                     AND cm.fecha_hora_cierre IS NOT NULL
                     AND pm.fecha_limite IS NOT NULL
                     AND DATE(cm.fecha_hora_cierre) > pm.fecha_limite
                    THEN DATEDIFF(DATE(cm.fecha_hora_cierre), pm.fecha_limite)
                ELSE 0
            END AS dias_retraso
        FROM solicitudes s
        INNER JOIN departamentos d ON d.id = s.departamento_id
        INNER JOIN areas a ON a.id = s.area_id
        INNER JOIN procesos p ON p.id = s.proceso_id
        INNER JOIN equipos e ON e.id = s.equipo_id
        LEFT JOIN programaciones_mantenimiento pm
               ON pm.solicitud_id = s.id
              AND pm.es_actual = 1
        LEFT JOIN cierres_mantenimiento cm
               ON cm.solicitud_id = s.id
        LEFT JOIN (
            SELECT
                em.solicitud_id,
                COUNT(DISTINCT em.id) AS total_ejecuciones,
                SUM(CASE WHEN em.fecha_hora_inicio IS NOT NULL THEN 1 ELSE 0 END) AS total_iniciadas,
                SUM(CASE WHEN em.estado = 'EN_PROCESO' THEN 1 ELSE 0 END) AS total_en_proceso,
                SUM(CASE WHEN em.estado = 'PAUSADA' THEN 1 ELSE 0 END) AS total_pausadas,
                SUM(CASE WHEN em.estado = 'TERMINADA' THEN 1 ELSE 0 END) AS total_terminadas,
                SUM(em.total_segundos_activos) AS total_segundos_activos,
                SUM(em.total_segundos_pausa) AS total_segundos_pausa
            FROM ejecuciones_mantenimiento em
            GROUP BY em.solicitud_id
        ) es ON es.solicitud_id = s.id
        LEFT JOIN (
            SELECT
                st.solicitud_id,
                COUNT(DISTINCT CASE WHEN st.activo = 1 THEN st.tecnico_id END) AS total_asignados,
                SUM(CASE WHEN st.activo = 1 AND st.resultado_cumplimiento = 'A_TIEMPO' THEN 1 ELSE 0 END) AS total_a_tiempo,
                SUM(CASE WHEN st.activo = 1 AND st.resultado_cumplimiento = 'TARDE' THEN 1 ELSE 0 END) AS total_tarde,
                SUM(CASE WHEN st.activo = 1 AND st.resultado_cumplimiento = 'NO_REALIZADO' THEN 1 ELSE 0 END) AS total_no_realizado
            FROM solicitud_tecnicos st
            GROUP BY st.solicitud_id
        ) rs ON rs.solicitud_id = s.id
        WHERE s.activo = 1
          AND s.estado NOT IN ('RECHAZADO', 'CANCELADO')
          AND (
                (
                    pm.id IS NOT NULL
                    AND pm.fecha_programada BETWEEN :inicio_programado AND :fin_programado
                    AND pm.estado IN ('PROGRAMADA', 'VENCIDA', 'CUMPLIDA')
                )
                OR
                (
                    s.tipo_solicitud = 'CORRECTIVO_URGENTE'
                    AND pm.id IS NULL
                    AND s.fecha_solicitud BETWEEN :inicio_urgente AND :fin_urgente
                )
          )
        ORDER BY
            fecha_agenda ASC,
            CASE
                WHEN s.estado IN ('EN_PROCESO', 'PAUSADO') THEN 1
                WHEN s.estado = 'ATRASADO' OR pm.estado = 'VENCIDA' THEN 2
                WHEN s.prioridad = 'URGENTE' THEN 3
                WHEN s.prioridad = 'ALTA' THEN 4
                WHEN s.prioridad = 'MEDIA' THEN 5
                ELSE 6
            END,
            s.id DESC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':inicio_programado', $inicio, PDO::PARAM_STR);
    $stmt->bindValue(':fin_programado', $fin, PDO::PARAM_STR);
    $stmt->bindValue(':inicio_urgente', $inicio, PDO::PARAM_STR);
    $stmt->bindValue(':fin_urgente', $fin, PDO::PARAM_STR);
    $stmt->execute();

    $actividades = [];

    foreach ($stmt->fetchAll() as $fila) {
        $id = (int) $fila['id'];
        $fila['id'] = $id;
        $fila['programacion_id'] = $fila['programacion_id'] !== null
            ? (int) $fila['programacion_id']
            : null;
        $fila['trabajo_peligroso'] = (int) $fila['trabajo_peligroso'];
        $fila['requiere_paro_equipo'] = (int) $fila['requiere_paro_equipo'];
        $fila['total_ejecuciones'] = (int) $fila['total_ejecuciones'];
        $fila['total_iniciadas'] = (int) $fila['total_iniciadas'];
        $fila['total_en_proceso'] = (int) $fila['total_en_proceso'];
        $fila['total_pausadas'] = (int) $fila['total_pausadas'];
        $fila['total_terminadas'] = (int) $fila['total_terminadas'];
        $fila['total_segundos_activos'] = (int) $fila['total_segundos_activos'];
        $fila['total_segundos_pausa'] = (int) $fila['total_segundos_pausa'];
        $fila['total_asignados'] = (int) $fila['total_asignados'];
        $fila['total_a_tiempo'] = (int) $fila['total_a_tiempo'];
        $fila['total_tarde'] = (int) $fila['total_tarde'];
        $fila['total_no_realizado'] = (int) $fila['total_no_realizado'];
        $fila['dias_retraso'] = max(0, (int) $fila['dias_retraso']);
        $fila['tecnicos'] = [];
        $actividades[$id] = $fila;
    }

    return $actividades;
}

function aseg_adjuntar_tecnicos(PDO $conexion, array &$actividades): void
{
    if ($actividades === []) {
        return;
    }

    $ids = array_map('intval', array_keys($actividades));
    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $sql = "
        SELECT
            st.solicitud_id,
            st.id AS solicitud_tecnico_id,
            st.tecnico_id,
            st.estado AS estado_asignacion,
            st.resultado_cumplimiento,
            st.alerta_riesgo_nocturno,
            st.riesgo_nocturno_confirmado,
            t.turno,
            t.especialidad,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.fecha_hora_fin,
            COALESCE(em.total_segundos_activos, 0) AS total_segundos_activos,
            COALESCE(em.total_segundos_pausa, 0) AS total_segundos_pausa
        FROM solicitud_tecnicos st
        INNER JOIN tecnicos t ON t.id = st.tecnico_id
        LEFT JOIN ejecuciones_mantenimiento em
               ON em.solicitud_tecnico_id = st.id
        WHERE st.solicitud_id IN ($marcadores)
          AND (
                st.activo = 1
                OR em.id IS NOT NULL
          )
        ORDER BY
            st.solicitud_id,
            CASE st.estado
                WHEN 'EN_PROCESO' THEN 1
                WHEN 'PAUSADO' THEN 2
                WHEN 'TERMINADO' THEN 3
                WHEN 'ACEPTADO' THEN 4
                WHEN 'ASIGNADO' THEN 5
                ELSE 6
            END,
            t.nombre,
            t.apellido_paterno
    ";

    $stmt = $conexion->prepare($sql);
    foreach ($ids as $indice => $id) {
        $stmt->bindValue($indice + 1, $id, PDO::PARAM_INT);
    }
    $stmt->execute();

    foreach ($stmt->fetchAll() as $fila) {
        $solicitudId = (int) $fila['solicitud_id'];
        if (!isset($actividades[$solicitudId])) {
            continue;
        }

        $actividades[$solicitudId]['tecnicos'][] = [
            'solicitud_tecnico_id' => (int) $fila['solicitud_tecnico_id'],
            'tecnico_id' => (int) $fila['tecnico_id'],
            'tecnico' => (string) $fila['tecnico'],
            'turno' => (string) $fila['turno'],
            'especialidad' => $fila['especialidad'],
            'estado_asignacion' => (string) $fila['estado_asignacion'],
            'estado_ejecucion' => $fila['estado_ejecucion'],
            'resultado_cumplimiento' => (string) $fila['resultado_cumplimiento'],
            'fecha_hora_inicio' => $fila['fecha_hora_inicio'],
            'fecha_hora_fin' => $fila['fecha_hora_fin'],
            'total_segundos_activos' => (int) $fila['total_segundos_activos'],
            'total_segundos_pausa' => (int) $fila['total_segundos_pausa'],
            'alerta_riesgo_nocturno' => (int) $fila['alerta_riesgo_nocturno'],
            'riesgo_nocturno_confirmado' => (int) $fila['riesgo_nocturno_confirmado'],
        ];
    }
}

function aseg_consultar_detalle(PDO $conexion, int $solicitudId): ?array
{
    $stmt = $conexion->prepare(
        "SELECT
            s.id,
            s.folio,
            s.tipo_solicitud,
            s.estado,
            s.prioridad,
            s.fecha_solicitud,
            s.hora_solicitud,
            s.fecha_sugerida,
            s.descripcion_solicitud,
            s.descripcion_falla,
            s.impacto_operacion,
            s.objetivo_mejora,
            s.resultado_esperado,
            s.trabajo_peligroso,
            s.nivel_riesgo,
            s.requiere_paro_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso,
            e.codigo_equipo,
            e.nombre_equipo,
            tf.nombre AS tipo_falla,
            ca.nombre AS causa_averia,
            COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ', sol.nombre, sol.apellido_paterno, sol.apellido_materno)), ''),
                NULLIF(TRIM(CONCAT_WS(' ', adm.nombre, adm.apellido_paterno, adm.apellido_materno)), ''),
                'Sin solicitante'
            ) AS solicitante,
            COALESCE(sol.telefono, adm.telefono) AS telefono_solicitante,
            COALESCE(sol.correo, adm.correo) AS correo_solicitante,
            pm.id AS programacion_id,
            pm.fecha_programada,
            pm.fecha_limite,
            pm.estado AS estado_programacion,
            pm.motivo_programacion,
            pm.motivo_reprogramacion,
            cm.fecha_hora_cierre,
            cm.trabajo_quedo,
            cm.descripcion_trabajo_realizado,
            cm.que_falto,
            cm.realizo_limpieza_area,
            cm.area_ordenada_libre_componentes,
            cm.observaciones_cierre,
            CASE
                WHEN s.estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')
                     AND pm.fecha_limite IS NOT NULL
                     AND pm.fecha_limite < CURDATE()
                    THEN DATEDIFF(CURDATE(), pm.fecha_limite)
                WHEN s.estado = 'TERMINADO'
                     AND cm.fecha_hora_cierre IS NOT NULL
                     AND pm.fecha_limite IS NOT NULL
                     AND DATE(cm.fecha_hora_cierre) > pm.fecha_limite
                    THEN DATEDIFF(DATE(cm.fecha_hora_cierre), pm.fecha_limite)
                ELSE 0
            END AS dias_retraso
         FROM solicitudes s
         INNER JOIN departamentos d ON d.id = s.departamento_id
         INNER JOIN areas a ON a.id = s.area_id
         INNER JOIN procesos p ON p.id = s.proceso_id
         INNER JOIN equipos e ON e.id = s.equipo_id
         LEFT JOIN tipos_falla tf ON tf.id = s.tipo_falla_id
         LEFT JOIN causas_averia ca ON ca.id = s.causa_averia_id
         LEFT JOIN solicitantes sol ON sol.id = s.solicitante_id
         LEFT JOIN administradores adm ON adm.id = s.administrador_solicitante_id
         LEFT JOIN programaciones_mantenimiento pm
                ON pm.solicitud_id = s.id
               AND pm.es_actual = 1
         LEFT JOIN cierres_mantenimiento cm ON cm.solicitud_id = s.id
         WHERE s.id = :id
           AND s.activo = 1
         LIMIT 1"
    );
    $stmt->bindValue(':id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch();

    if (!$fila) {
        return null;
    }

    $fila['id'] = (int) $fila['id'];
    $fila['programacion_id'] = $fila['programacion_id'] !== null
        ? (int) $fila['programacion_id']
        : null;
    $fila['trabajo_peligroso'] = (int) $fila['trabajo_peligroso'];
    $fila['requiere_paro_equipo'] = (int) $fila['requiere_paro_equipo'];
    $fila['realizo_limpieza_area'] = $fila['realizo_limpieza_area'] !== null
        ? (int) $fila['realizo_limpieza_area']
        : null;
    $fila['area_ordenada_libre_componentes'] = $fila['area_ordenada_libre_componentes'] !== null
        ? (int) $fila['area_ordenada_libre_componentes']
        : null;
    $fila['dias_retraso'] = max(0, (int) $fila['dias_retraso']);

    return $fila;
}

function aseg_consultar_tecnicos_detalle(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            st.id AS solicitud_tecnico_id,
            st.tecnico_id,
            st.origen,
            st.estado AS estado_asignacion,
            st.fecha_asignacion,
            st.fecha_aceptacion,
            st.resultado_cumplimiento,
            st.fecha_resultado,
            st.alerta_riesgo_nocturno,
            st.riesgo_nocturno_confirmado,
            st.observacion_riesgo_nocturno,
            st.activo,
            t.turno,
            t.especialidad,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
            em.id AS ejecucion_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.fecha_hora_fin,
            COALESCE(em.total_segundos_activos, 0) AS total_segundos_activos,
            COALESCE(em.total_segundos_pausa, 0) AS total_segundos_pausa
         FROM solicitud_tecnicos st
         INNER JOIN tecnicos t ON t.id = st.tecnico_id
         LEFT JOIN ejecuciones_mantenimiento em
                ON em.solicitud_tecnico_id = st.id
         WHERE st.solicitud_id = :solicitud_id
           AND (st.activo = 1 OR em.id IS NOT NULL)
         ORDER BY
            CASE st.estado
                WHEN 'EN_PROCESO' THEN 1
                WHEN 'PAUSADO' THEN 2
                WHEN 'TERMINADO' THEN 3
                WHEN 'ACEPTADO' THEN 4
                WHEN 'ASIGNADO' THEN 5
                ELSE 6
            END,
            t.nombre,
            t.apellido_paterno"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    $tecnicos = [];
    foreach ($stmt->fetchAll() as $fila) {
        $tecnicos[] = [
            'solicitud_tecnico_id' => (int) $fila['solicitud_tecnico_id'],
            'tecnico_id' => (int) $fila['tecnico_id'],
            'tecnico' => (string) $fila['tecnico'],
            'turno' => (string) $fila['turno'],
            'especialidad' => $fila['especialidad'],
            'origen' => (string) $fila['origen'],
            'estado_asignacion' => (string) $fila['estado_asignacion'],
            'fecha_asignacion' => $fila['fecha_asignacion'],
            'fecha_aceptacion' => $fila['fecha_aceptacion'],
            'resultado_cumplimiento' => (string) $fila['resultado_cumplimiento'],
            'fecha_resultado' => $fila['fecha_resultado'],
            'activo' => (int) $fila['activo'],
            'ejecucion_id' => $fila['ejecucion_id'] !== null
                ? (int) $fila['ejecucion_id']
                : null,
            'estado_ejecucion' => $fila['estado_ejecucion'],
            'fecha_hora_inicio' => $fila['fecha_hora_inicio'],
            'fecha_hora_fin' => $fila['fecha_hora_fin'],
            'total_segundos_activos' => (int) $fila['total_segundos_activos'],
            'total_segundos_pausa' => (int) $fila['total_segundos_pausa'],
            'alerta_riesgo_nocturno' => (int) $fila['alerta_riesgo_nocturno'],
            'riesgo_nocturno_confirmado' => (int) $fila['riesgo_nocturno_confirmado'],
            'observacion_riesgo_nocturno' => $fila['observacion_riesgo_nocturno'],
        ];
    }

    return $tecnicos;
}

/* =========================================================================
   RESUMEN, CARGA Y CALENDARIO
   ========================================================================= */

function aseg_construir_resumen(array $actividades): array
{
    $resumen = [
        'total' => 0,
        'por_iniciar' => 0,
        'en_curso' => 0,
        'atrasados' => 0,
        'terminados' => 0,
        'urgentes' => 0,
        'sin_tecnico' => 0,
        'trabajos_peligrosos' => 0,
        'terminados_tarde' => 0,
    ];

    foreach ($actividades as $actividad) {
        $resumen['total']++;

        $grupo = (string) $actividad['grupo_estado'];
        if ($grupo === 'POR_INICIAR') {
            $resumen['por_iniciar']++;
        } elseif ($grupo === 'EN_CURSO') {
            $resumen['en_curso']++;
        } elseif ($grupo === 'ATRASADO') {
            $resumen['atrasados']++;
        } elseif ($grupo === 'TERMINADO') {
            $resumen['terminados']++;
        }

        if ((string) $actividad['tipo_solicitud'] === 'CORRECTIVO_URGENTE') {
            $resumen['urgentes']++;
        }

        if ((int) $actividad['total_asignados'] === 0) {
            $resumen['sin_tecnico']++;
        }

        if ((int) $actividad['trabajo_peligroso'] === 1) {
            $resumen['trabajos_peligrosos']++;
        }

        if (
            $grupo === 'TERMINADO'
            && (
                (int) $actividad['total_tarde'] > 0
                || (int) $actividad['dias_retraso'] > 0
            )
        ) {
            $resumen['terminados_tarde']++;
        }
    }

    return $resumen;
}

function aseg_construir_carga_tecnicos(
    PDO $conexion,
    array $actividades
): array {
    $catalogo = aseg_catalogo_tecnicos($conexion);
    $carga = [];

    foreach ($catalogo as $tecnico) {
        $id = (int) $tecnico['id'];
        $carga[$id] = [
            'id' => $id,
            'tecnico' => (string) $tecnico['tecnico'],
            'turno' => (string) $tecnico['turno'],
            'especialidad' => $tecnico['especialidad'],
            'total' => 0,
            'por_iniciar' => 0,
            'en_curso' => 0,
            'terminados' => 0,
            'atrasados' => 0,
            'tarde' => 0,
            'segundos_activos' => 0,
        ];
    }

    foreach ($actividades as $actividad) {
        foreach ((array) $actividad['tecnicos'] as $tecnicoActividad) {
            $id = (int) $tecnicoActividad['tecnico_id'];
            if (!isset($carga[$id])) {
                $carga[$id] = [
                    'id' => $id,
                    'tecnico' => (string) $tecnicoActividad['tecnico'],
                    'turno' => (string) $tecnicoActividad['turno'],
                    'especialidad' => $tecnicoActividad['especialidad'],
                    'total' => 0,
                    'por_iniciar' => 0,
                    'en_curso' => 0,
                    'terminados' => 0,
                    'atrasados' => 0,
                    'tarde' => 0,
                    'segundos_activos' => 0,
                ];
            }

            $carga[$id]['total']++;
            $grupo = (string) $actividad['grupo_estado'];

            if ($grupo === 'POR_INICIAR') {
                $carga[$id]['por_iniciar']++;
            } elseif ($grupo === 'EN_CURSO') {
                $carga[$id]['en_curso']++;
            } elseif ($grupo === 'ATRASADO') {
                $carga[$id]['atrasados']++;
            } elseif ($grupo === 'TERMINADO') {
                $carga[$id]['terminados']++;
            }

            if ((string) $tecnicoActividad['resultado_cumplimiento'] === 'TARDE') {
                $carga[$id]['tarde']++;
            }

            $carga[$id]['segundos_activos'] += (int) $tecnicoActividad['total_segundos_activos'];
        }
    }

    $resultado = array_values($carga);
    usort($resultado, 'aseg_ordenar_carga_tecnicos');

    return $resultado;
}

function aseg_ordenar_carga_tecnicos(array $a, array $b): int
{
    if ((int) $a['total'] === (int) $b['total']) {
        return strcasecmp((string) $a['tecnico'], (string) $b['tecnico']);
    }

    return (int) $b['total'] <=> (int) $a['total'];
}

function aseg_catalogo_tecnicos(PDO $conexion): array
{
    $stmt = $conexion->query(
        "SELECT
            t.id,
            t.turno,
            t.especialidad,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico
         FROM tecnicos t
         WHERE t.activo = 1
         ORDER BY t.nombre, t.apellido_paterno, t.apellido_materno"
    );

    $tecnicos = [];
    foreach ($stmt->fetchAll() as $fila) {
        $tecnicos[] = [
            'id' => (int) $fila['id'],
            'tecnico' => (string) $fila['tecnico'],
            'turno' => (string) $fila['turno'],
            'especialidad' => $fila['especialidad'],
        ];
    }

    return $tecnicos;
}

function aseg_consultar_calendario(
    PDO $conexion,
    string $inicio,
    string $fin
): array {
    $stmt = $conexion->prepare(
        "SELECT fecha, es_habil, tipo_dia, motivo
         FROM calendario_laboral
         WHERE fecha BETWEEN :inicio AND :fin
         ORDER BY fecha"
    );
    $stmt->bindValue(':inicio', $inicio, PDO::PARAM_STR);
    $stmt->bindValue(':fin', $fin, PDO::PARAM_STR);
    $stmt->execute();

    $configurados = [];
    foreach ($stmt->fetchAll() as $fila) {
        $configurados[(string) $fila['fecha']] = $fila;
    }

    $dias = [];
    $fecha = new DateTimeImmutable($inicio);
    $limite = new DateTimeImmutable($fin);

    while ($fecha <= $limite) {
        $textoFecha = $fecha->format('Y-m-d');
        $fila = $configurados[$textoFecha] ?? null;

        if ($fila) {
            $esHabil = (int) $fila['es_habil'] === 1
                && (string) $fila['tipo_dia'] !== 'INHABIL';

            $dias[] = [
                'fecha' => $textoFecha,
                'numero_dia' => (int) $fecha->format('N'),
                'configurado' => true,
                'es_habil' => $esHabil,
                'tipo_dia' => (string) $fila['tipo_dia'],
                'motivo' => $fila['motivo'],
            ];
        } else {
            $finSemana = (int) $fecha->format('N') >= 6;
            $dias[] = [
                'fecha' => $textoFecha,
                'numero_dia' => (int) $fecha->format('N'),
                'configurado' => true,
                'origen' => 'REGLA_BASE',
                'es_habil' => !$finSemana,
                'tipo_dia' => $finSemana ? 'INHABIL' : 'HABIL',
                'motivo' => $finSemana ? 'Fin de semana (regla base).' : null,
            ];
        }

        $fecha = $fecha->modify('+1 day');
    }

    return $dias;
}

/* =========================================================================
   SINCRONIZACIÓN DE ATRASOS
   ========================================================================= */

function aseg_sincronizar_atrasos(PDO $conexion): void
{
    if ($conexion->inTransaction()) {
        return;
    }

    $conexion->beginTransaction();

    $stmt = $conexion->query(
        "SELECT
            s.id AS solicitud_id,
            s.folio,
            s.estado AS estado_solicitud,
            pm.id AS programacion_id,
            pm.fecha_programada,
            st.id AS solicitud_tecnico_id
         FROM programaciones_mantenimiento pm
         INNER JOIN solicitudes s ON s.id = pm.solicitud_id
         INNER JOIN solicitud_tecnicos st
                 ON st.programacion_id = pm.id
                AND st.activo = 1
         LEFT JOIN incumplimientos_mantenimiento im
                ON im.solicitud_tecnico_id = st.id
               AND im.programacion_id = pm.id
         WHERE pm.es_actual = 1
           AND pm.fecha_limite < CURDATE()
           AND pm.estado IN ('PROGRAMADA', 'VENCIDA')
           AND s.activo = 1
           AND s.estado NOT IN ('TERMINADO', 'RECHAZADO', 'CANCELADO')
           AND st.resultado_cumplimiento = 'PENDIENTE'
           AND im.id IS NULL
         FOR UPDATE"
    );

    $pendientes = $stmt->fetchAll();

    $insertarIncumplimiento = $conexion->prepare(
        "INSERT INTO incumplimientos_mantenimiento
        (
            solicitud_id,
            programacion_id,
            solicitud_tecnico_id,
            fecha_programada,
            fecha_detectado,
            estado
        )
        VALUES
        (
            :solicitud_id,
            :programacion_id,
            :solicitud_tecnico_id,
            :fecha_programada,
            NOW(),
            'PENDIENTE'
        )"
    );

    $insertarHistorial = $conexion->prepare(
        "INSERT INTO historial_solicitudes
        (
            solicitud_id,
            solicitud_tecnico_id,
            programacion_id,
            evento,
            estado_anterior,
            estado_nuevo,
            actor_tipo,
            actor_id,
            descripcion,
            fecha_evento
        )
        VALUES
        (
            :solicitud_id,
            :solicitud_tecnico_id,
            :programacion_id,
            'INCUMPLIMIENTO_DETECTADO',
            :estado_anterior,
            :estado_nuevo,
            'SISTEMA',
            NULL,
            :descripcion,
            NOW()
        )"
    );

    foreach ($pendientes as $fila) {
        $insertarIncumplimiento->execute([
            ':solicitud_id' => (int) $fila['solicitud_id'],
            ':programacion_id' => (int) $fila['programacion_id'],
            ':solicitud_tecnico_id' => (int) $fila['solicitud_tecnico_id'],
            ':fecha_programada' => (string) $fila['fecha_programada'],
        ]);

        $estadoAnterior = (string) $fila['estado_solicitud'];
        $estadoNuevo = in_array($estadoAnterior, ['EN_PROCESO', 'PAUSADO'], true)
            ? $estadoAnterior
            : 'ATRASADO';

        $insertarHistorial->execute([
            ':solicitud_id' => (int) $fila['solicitud_id'],
            ':solicitud_tecnico_id' => (int) $fila['solicitud_tecnico_id'],
            ':programacion_id' => (int) $fila['programacion_id'],
            ':estado_anterior' => $estadoAnterior,
            ':estado_nuevo' => $estadoNuevo,
            ':descripcion' => 'La actividad superó la fecha límite sin registrarse como terminada.',
        ]);
    }

    $conexion->exec(
        "UPDATE programaciones_mantenimiento pm
         INNER JOIN solicitudes s ON s.id = pm.solicitud_id
         SET pm.estado = 'VENCIDA'
         WHERE pm.es_actual = 1
           AND pm.fecha_limite < CURDATE()
           AND pm.estado = 'PROGRAMADA'
           AND s.activo = 1
           AND s.estado NOT IN ('TERMINADO', 'RECHAZADO', 'CANCELADO')"
    );

    $conexion->exec(
        "UPDATE solicitudes s
         INNER JOIN programaciones_mantenimiento pm
                 ON pm.solicitud_id = s.id
                AND pm.es_actual = 1
         SET s.estado = 'ATRASADO'
         WHERE pm.fecha_limite < CURDATE()
           AND s.estado IN ('APROBADO', 'AGENDADO')
           AND s.activo = 1
           AND NOT EXISTS (
               SELECT 1
               FROM ejecuciones_mantenimiento em
               WHERE em.solicitud_id = s.id
                 AND em.fecha_hora_inicio IS NOT NULL
           )"
    );

    $conexion->commit();
}

/* =========================================================================
   VALIDACIONES Y FECHAS
   ========================================================================= */

function aseg_admin_id(): int
{
    $id = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        sm_responder_json(false, 'La sesión del administrador no es válida.', [], 401);
    }

    return (int) $id;
}

function aseg_validar_admin_activo(PDO $conexion, int $adminId): void
{
    $stmt = $conexion->prepare(
        "SELECT id
         FROM administradores 
         WHERE id = :id
           AND activo = 1
         LIMIT 1"
    );
    $stmt->bindValue(':id', $adminId, PDO::PARAM_INT);
    $stmt->execute();

    if (!$stmt->fetchColumn()) {
        sm_responder_json(
            false,
            'Tu cuenta de administrador ya no se encuentra activa.',
            ['sesion_expirada' => true, 'redirect' => '../login.php'],
            403
        );
    }
}

function aseg_entero_positivo($valor, string $campo): int
{
    $id = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        sm_responder_json(
            false,
            'El identificador solicitado no es válido.',
            ['campo' => $campo],
            422
        );
    }

    return (int) $id;
}

function aseg_semana_desde_entrada($valor): array
{
    $texto = sm_limpiar_texto($valor);

    try {
        $fecha = $texto !== ''
            ? new DateTimeImmutable($texto)
            : new DateTimeImmutable('today');
    } catch (Throwable $e) {
        sm_responder_json(
            false,
            'La semana seleccionada no es válida.',
            ['campo' => 'semana'],
            422
        );
    }

    $numeroDia = (int) $fecha->format('N');
    $inicio = $fecha->modify('-' . ($numeroDia - 1) . ' days');
    $fin = $inicio->modify('+6 days');

    return [
        'inicio' => $inicio->format('Y-m-d'),
        'fin' => $fin->format('Y-m-d'),
    ];
}