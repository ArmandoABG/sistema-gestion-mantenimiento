<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Actividad actual del técnico - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| - Exclusivo para usuarios TECNICO.
| - Permite una sola ejecución EN_PROCESO por técnico.
| - Permite pausar manualmente la participación actual.
| - Permite reanudar de forma manual una ejecución pausada.
| - Un mantenimiento pausado por una urgencia solo puede reanudarse cuando
|   esa urgencia ya terminó o fue cancelada.
| - Finalizar una solicitud cierra el trabajo para todos sus participantes.
| - Los mantenimientos anteriores pausados por una urgencia NO se reanudan
|   automáticamente: quedan disponibles para que cada técnico los reanude.
| - Todas las operaciones críticas usan transacciones y bloqueos FOR UPDATE.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';
require_once __DIR__ . '/recursos_mantenimiento_servicio.php';

sm_requerir_sesion(['TECNICO'], true);

if (!($conexion instanceof PDO)) {
    sm_responder_json(false, 'No fue posible conectar con la base de datos.', [], 503);
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = $metodo === 'GET'
    ? sm_limpiar_texto($_GET['accion'] ?? 'inicial')
    : sm_limpiar_texto($_POST['accion'] ?? '');

try {
    if ($metodo === 'GET') {
        if ($accion === 'inicial') {
            mact_cargar_inicial($conexion);
        }

        if ($accion === 'detalle') {
            mact_obtener_detalle($conexion);
        }

        if ($accion === 'buscar_recursos') {
            mact_buscar_recursos($conexion);
        }

        sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
    }

    sm_requerir_metodo('POST');
    sm_validar_csrf();

    if ($accion === 'pausar') {
        mact_pausar($conexion);
    }

    if ($accion === 'reanudar') {
        mact_reanudar($conexion);
    }

    if ($accion === 'finalizar') {
        mact_finalizar($conexion);
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[MANTENIMIENTO ACTIVO][PDO] ' . $e->getMessage());

    if ((string) $e->getCode() === '23000') {
        sm_responder_json(
            false,
            'La información cambió mientras realizabas la operación. Actualiza la pantalla e inténtalo nuevamente.',
            [],
            409
        );
    }

    sm_responder_json(false, 'Ocurrió un error interno al procesar la actividad.', [], 500);
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[MANTENIMIENTO ACTIVO] ' . $e->getMessage());
    sm_responder_json(false, 'Ocurrió un error interno al procesar la actividad.', [], 500);
}

/* =========================================================================
   CARGA DE LA INTERFAZ
   ========================================================================= */

function mact_cargar_inicial(PDO $conexion): void
{
    $tecnicoId = mact_tecnico_id();
    $perfil = mact_obtener_tecnico_activo($conexion, $tecnicoId);
    $ejecucionSolicitada = mact_id_opcional($_GET['ejecucion_id'] ?? null);

    $ejecuciones = mact_consultar_ejecuciones_abiertas($conexion, $tecnicoId);
    $actividadActual = null;
    $pausadas = [];

    foreach ($ejecuciones as $ejecucion) {
        if ((string) $ejecucion['estado_ejecucion'] === 'EN_PROCESO') {
            $actividadActual = $ejecucion;
        } elseif ((string) $ejecucion['estado_ejecucion'] === 'PAUSADA') {
            $pausadas[] = $ejecucion;
        }
    }

    $seleccionada = null;

    if ($ejecucionSolicitada !== null) {
        foreach ($ejecuciones as $ejecucion) {
            if ((int) $ejecucion['ejecucion_id'] === $ejecucionSolicitada) {
                $seleccionada = $ejecucion;
                break;
            }
        }
    }

    if ($seleccionada === null) {
        $seleccionada = $actividadActual;
    }

    if ($seleccionada === null && $pausadas !== []) {
        $seleccionada = $pausadas[0];
    }

    $cancelacionReciente = null;
    $ejecucionSeleccionadaId = is_array($seleccionada)
        ? (int) ($seleccionada['ejecucion_id'] ?? 0)
        : 0;
    if (
        $ejecucionSolicitada !== null
        && $ejecucionSeleccionadaId !== $ejecucionSolicitada
    ) {
        $cancelacionReciente = mact_obtener_cancelacion_ejecucion(
            $conexion,
            $tecnicoId,
            $ejecucionSolicitada
        );
    }

    $participantes = [];

    if (is_array($seleccionada)) {
        $participantes = mact_listar_participantes(
            $conexion,
            (int) $seleccionada['solicitud_id'],
            $tecnicoId
        );
    }

    $resumen = [
        'en_proceso' => $actividadActual === null ? 0 : 1,
        'pausadas' => count($pausadas),
        'listas_reanudar' => 0,
        'esperando_urgencia' => 0,
    ];

    foreach ($pausadas as $pausada) {
        if ((int) $pausada['puede_reanudar'] === 1) {
            $resumen['listas_reanudar']++;
        }

        if ((int) $pausada['espera_urgencia'] === 1) {
            $resumen['esperando_urgencia']++;
        }
    }

    sm_responder_json(
        true,
        'Actividad cargada correctamente.',
        [
            'perfil' => $perfil,
            'actividad_actual' => $actividadActual,
            'pausadas' => $pausadas,
            'seleccionada' => $seleccionada,
            'participantes' => $participantes,
            'cancelacion_reciente' => $cancelacionReciente,
            'resumen' => $resumen,
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function mact_obtener_detalle(PDO $conexion): void
{
    $tecnicoId = mact_tecnico_id();
    mact_obtener_tecnico_activo($conexion, $tecnicoId);

    $ejecucionId = mact_id_entrada($_GET['ejecucion_id'] ?? null, 'ejecucion_id');
    $ejecucion = mact_consultar_ejecucion_abierta($conexion, $tecnicoId, $ejecucionId);

    if (!$ejecucion) {
        sm_responder_json(false, 'La actividad no existe o ya no está abierta.', [], 404);
    }

    sm_responder_json(
        true,
        'Detalle cargado correctamente.',
        [
            'ejecucion' => $ejecucion,
            'participantes' => mact_listar_participantes(
                $conexion,
                (int) $ejecucion['solicitud_id'],
                $tecnicoId
            ),
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function mact_buscar_recursos(PDO $conexion): void
{
    $tecnicoId = mact_tecnico_id();
    mact_obtener_tecnico_activo($conexion, $tecnicoId);
    rsm_verificar_estructura($conexion);

    $tipo = strtoupper(mact_texto($_GET['tipo_recurso'] ?? ''));

    if (!in_array($tipo, rsm_tipos_validos(), true)) {
        sm_responder_json(
            false,
            'Selecciona si deseas buscar una herramienta o una refacción.',
            ['campo' => 'tipo_recurso'],
            422
        );
    }

    $busqueda = mact_texto($_GET['q'] ?? '');
    if (mact_longitud($busqueda) > 150) {
        $busqueda = mact_recortar($busqueda, 150);
    }

    sm_responder_json(
        true,
        'Recursos encontrados correctamente.',
        [
            'recursos' => rsm_buscar_recursos_activos(
                $conexion,
                $tipo,
                $busqueda,
                30
            ),
        ]
    );
}

/* =========================================================================
   DETECCIÓN DE ACTIVIDAD CANCELADA
   ========================================================================= */

function mact_obtener_cancelacion_ejecucion(
    PDO $conexion,
    int $tecnicoId,
    int $ejecucionId
): ?array {
    $stmt = $conexion->prepare(
        "SELECT
            em.id AS ejecucion_id,
            em.solicitud_id,
            em.fecha_hora_inicio,
            em.fecha_hora_fin,
            em.total_segundos_activos,
            em.total_segundos_pausa,
            s.folio,
            s.tipo_solicitud,
            s.descripcion_solicitud,
            e.codigo_equipo,
            e.nombre_equipo,
            a.nombre AS area,
            COALESCE(
                NULLIF(pm.motivo_cancelacion, ''),
                NULLIF(s.motivo_ultima_edicion, ''),
                'No se registró un motivo de cancelación.'
            ) AS motivo_cancelacion,
            COALESCE(hc.fecha_evento, s.fecha_actualizacion) AS fecha_cancelacion,
            COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ', adm.nombre, adm.apellido_paterno, adm.apellido_materno)), ''),
                'Administración'
            ) AS cancelado_por
         FROM ejecuciones_mantenimiento em
         INNER JOIN solicitudes s ON s.id = em.solicitud_id
         INNER JOIN equipos e ON e.id = s.equipo_id
         INNER JOIN areas a ON a.id = s.area_id
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
         WHERE em.id = :ejecucion_id
           AND em.tecnico_id = :tecnico_id
           AND em.estado = 'CANCELADA'
           AND s.estado = 'CANCELADO'
         LIMIT 1"
    );
    $stmt->bindValue(':ejecucion_id', $ejecucionId, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($fila) ? $fila : null;
}

function mact_consultar_ejecuciones_abiertas(PDO $conexion, int $tecnicoId): array
{
    $sql = mact_sql_ejecucion_base() . "
        WHERE em.tecnico_id = :tecnico_id
          AND em.estado IN ('EN_PROCESO','PAUSADA')
          AND st.activo = 1
          AND s.activo = 1
        ORDER BY
            FIELD(em.estado, 'EN_PROCESO','PAUSADA'),
            CASE
                WHEN em.estado = 'PAUSADA'
                 AND pe.motivo = 'URGENCIA'
                 AND su.estado IN ('TERMINADO','CANCELADO') THEN 0
                ELSE 1
            END,
            COALESCE(pe.fecha_hora_inicio, em.fecha_hora_inicio) ASC,
            em.id ASC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();

    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    rsm_adjuntar_recursos_recomendados(
        $conexion,
        $filas,
        'solicitud_equipo_id',
        true
    );

    $hayActiva = false;

    foreach ($filas as $fila) {
        if ((string) $fila['estado_ejecucion'] === 'EN_PROCESO') {
            $hayActiva = true;
            break;
        }
    }

    foreach ($filas as &$fila) {
        mact_agregar_permisos($fila, $hayActiva);
    }
    unset($fila);

    return $filas;
}

function mact_consultar_ejecucion_abierta(
    PDO $conexion,
    int $tecnicoId,
    int $ejecucionId
): ?array {
    $sql = mact_sql_ejecucion_base() . "
        WHERE em.id = :ejecucion_id
          AND em.tecnico_id = :tecnico_id
          AND em.estado IN ('EN_PROCESO','PAUSADA')
          AND st.activo = 1
          AND s.activo = 1
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':ejecucion_id', $ejecucionId, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($fila)) {
        return null;
    }

    $filasDetalle = [$fila];
    rsm_adjuntar_recursos_recomendados(
        $conexion,
        $filasDetalle,
        'solicitud_equipo_id',
        true
    );
    $fila = $filasDetalle[0];

    $stmtActiva = $conexion->prepare(
        "SELECT COUNT(*)
         FROM ejecuciones_mantenimiento
         WHERE tecnico_id = :tecnico_id
           AND estado = 'EN_PROCESO'"
    );
    $stmtActiva->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmtActiva->execute();
    $hayActiva = (int) $stmtActiva->fetchColumn() > 0;

    mact_agregar_permisos($fila, $hayActiva);

    return $fila;
}

function mact_sql_ejecucion_base(): string
{
    return "
        SELECT
            em.id AS ejecucion_id,
            em.solicitud_id,
            em.solicitud_tecnico_id,
            em.tecnico_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.fecha_hora_fin,
            em.fecha_ultima_reanudacion,
            em.fecha_hora_inicio_original,
            em.total_segundos_activos,
            em.total_segundos_pausa,
            em.total_segundos_activos + CASE
                WHEN em.estado = 'EN_PROCESO' THEN GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        SECOND,
                        COALESCE(em.fecha_ultima_reanudacion, em.fecha_hora_inicio, NOW()),
                        NOW()
                    )
                )
                ELSE 0
            END AS segundos_activos_actuales,
            em.total_segundos_pausa + CASE
                WHEN pe.id IS NOT NULL THEN GREATEST(
                    0,
                    TIMESTAMPDIFF(SECOND, pe.fecha_hora_inicio, NOW())
                )
                ELSE 0
            END AS segundos_pausa_actuales,
            st.origen,
            st.estado AS estado_participacion,
            st.fecha_asignacion,
            st.fecha_aceptacion,
            st.resultado_cumplimiento,
            st.alerta_riesgo_nocturno,
            st.riesgo_nocturno_confirmado,
            s.folio,
            s.tipo_solicitud,
            s.equipo_id AS solicitud_equipo_id,
            s.estado AS estado_solicitud,
            s.prioridad,
            s.fecha_solicitud,
            s.hora_solicitud,
            s.fecha_sugerida,
            s.descripcion_solicitud,
            s.descripcion_falla,
            s.causa_desconocida_descripcion,
            s.impacto_operacion,
            s.objetivo_mejora,
            s.resultado_esperado,
            s.justificacion_mejora,
            s.observaciones_solicitante,
            s.trabajo_peligroso,
            s.detalle_trabajo_peligroso,
            s.nivel_riesgo,
            s.requiere_paro_equipo,
            s.revisado_por_admin_id,
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
            pe.id AS pausa_id,
            pe.fecha_hora_inicio AS pausa_inicio,
            pe.motivo AS motivo_pausa,
            pe.solicitud_urgente_id,
            pe.observaciones AS observaciones_pausa,
            su.folio AS folio_urgencia_origen,
            su.estado AS estado_urgencia_origen,
            eu.nombre_equipo AS equipo_urgencia_origen,
            CASE
                WHEN s.solicitante_id IS NOT NULL THEN TRIM(CONCAT_WS(
                    ' ', sol.nombre, sol.apellido_paterno, sol.apellido_materno
                ))
                WHEN s.administrador_solicitante_id IS NOT NULL THEN TRIM(CONCAT_WS(
                    ' ', adm.nombre, adm.apellido_paterno, adm.apellido_materno
                ))
                ELSE 'Sistema'
            END AS solicitante,
            cm.id AS cierre_id,
            cm.fecha_hora_cierre,
            cm.trabajo_quedo
        FROM ejecuciones_mantenimiento em
        INNER JOIN solicitud_tecnicos st
                ON st.id = em.solicitud_tecnico_id
               AND st.solicitud_id = em.solicitud_id
               AND st.tecnico_id = em.tecnico_id
        INNER JOIN solicitudes s
                ON s.id = em.solicitud_id
        INNER JOIN equipos e
                ON e.id = s.equipo_id
        INNER JOIN departamentos d
                ON d.id = s.departamento_id
        INNER JOIN areas a
                ON a.id = s.area_id
               AND a.departamento_id = s.departamento_id
        INNER JOIN procesos p
                ON p.id = s.proceso_id
               AND p.area_id = s.area_id
        LEFT JOIN tipos_falla tf
               ON tf.id = s.tipo_falla_id
        LEFT JOIN causas_averia ca
               ON ca.id = s.causa_averia_id
        LEFT JOIN solicitantes sol
               ON sol.id = s.solicitante_id
        LEFT JOIN administradores adm
               ON adm.id = s.administrador_solicitante_id
        LEFT JOIN programaciones_mantenimiento pm
               ON pm.id = st.programacion_id
        LEFT JOIN pausas_ejecucion pe
               ON pe.ejecucion_id = em.id
              AND pe.pausa_abierta_token = 1
        LEFT JOIN solicitudes su
               ON su.id = pe.solicitud_urgente_id
        LEFT JOIN equipos eu
               ON eu.id = su.equipo_id
        LEFT JOIN cierres_mantenimiento cm
               ON cm.solicitud_id = s.id
    ";
}

function mact_agregar_permisos(array &$fila, bool $hayActividadActiva): void
{
    $estadoEjecucion = strtoupper((string) ($fila['estado_ejecucion'] ?? ''));
    $estadoSolicitud = strtoupper((string) ($fila['estado_solicitud'] ?? ''));
    $motivo = strtoupper((string) ($fila['motivo_pausa'] ?? ''));
    $estadoUrgencia = strtoupper((string) ($fila['estado_urgencia_origen'] ?? ''));

    $terminalSolicitud = in_array(
        $estadoSolicitud,
        ['TERMINADO','RECHAZADO','CANCELADO'],
        true
    ) || !empty($fila['cierre_id']);

    $puedePausar = $estadoEjecucion === 'EN_PROCESO' && !$terminalSolicitud;
    $puedeFinalizar = $estadoEjecucion === 'EN_PROCESO' && !$terminalSolicitud;
    $puedeReanudar = $estadoEjecucion === 'PAUSADA' && !$terminalSolicitud;
    $esperaUrgencia = false;
    $bloqueo = '';

    if ($puedeReanudar && empty($fila['pausa_id'])) {
        $puedeReanudar = false;
        $bloqueo = 'La ejecución no tiene una pausa abierta válida.';
    }

    if ($puedeReanudar && $hayActividadActiva) {
        $puedeReanudar = false;
        $bloqueo = 'Primero debes pausar o finalizar tu actividad actual.';
    }

    if ($puedeReanudar && in_array($motivo, ['ADMINISTRATIVA','CAMBIO_PRIORIDAD'], true)) {
        $puedeReanudar = false;
        $bloqueo = 'Esta pausa requiere autorización o ajuste administrativo.';
    }

    if ($puedeReanudar && $motivo === 'URGENCIA') {
        if (!in_array($estadoUrgencia, ['TERMINADO','CANCELADO'], true)) {
            $puedeReanudar = false;
            $esperaUrgencia = true;
            $bloqueo = 'La urgencia relacionada todavía continúa abierta.';
        }
    }

    $fila['puede_pausar'] = $puedePausar ? 1 : 0;
    $fila['puede_finalizar'] = $puedeFinalizar ? 1 : 0;
    $fila['puede_reanudar'] = $puedeReanudar ? 1 : 0;
    $fila['espera_urgencia'] = $esperaUrgencia ? 1 : 0;
    $fila['bloqueo_reanudacion'] = $bloqueo;
    $fila['es_urgente'] = (string) ($fila['tipo_solicitud'] ?? '') === 'CORRECTIVO_URGENTE' ? 1 : 0;
}

function mact_listar_participantes(
    PDO $conexion,
    int $solicitudId,
    int $tecnicoActualId
): array {
    $stmt = $conexion->prepare(
        "SELECT
            st.id AS solicitud_tecnico_id,
            st.tecnico_id,
            st.origen,
            st.estado AS estado_participacion,
            st.fecha_asignacion,
            st.fecha_aceptacion,
            st.resultado_cumplimiento,
            TRIM(CONCAT_WS(
                ' ', t.nombre, t.apellido_paterno, t.apellido_materno
            )) AS tecnico,
            t.turno,
            t.especialidad,
            em.id AS ejecucion_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.fecha_hora_fin,
            em.total_segundos_activos + CASE
                WHEN em.estado = 'EN_PROCESO' THEN GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        SECOND,
                        COALESCE(em.fecha_ultima_reanudacion, em.fecha_hora_inicio, NOW()),
                        NOW()
                    )
                )
                ELSE 0
            END AS segundos_activos_actuales,
            em.total_segundos_pausa,
            CASE WHEN st.tecnico_id = :tecnico_actual THEN 1 ELSE 0 END AS es_tecnico_actual
         FROM solicitud_tecnicos st
         INNER JOIN tecnicos t
                 ON t.id = st.tecnico_id
         LEFT JOIN ejecuciones_mantenimiento em
                ON em.solicitud_tecnico_id = st.id
         WHERE st.solicitud_id = :solicitud_id
           AND st.activo = 1
         ORDER BY
             FIELD(st.estado, 'EN_PROCESO','PAUSADO','ACEPTADO','ASIGNADO','TERMINADO','NO_PARTICIPO'),
             COALESCE(st.fecha_aceptacion, st.fecha_asignacion) ASC,
             st.id ASC"
    );
    $stmt->bindValue(':tecnico_actual', $tecnicoActualId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* =========================================================================
   PAUSAR
   ========================================================================= */

function mact_pausar(PDO $conexion): void
{
    $tecnicoId = mact_tecnico_id();
    $ejecucionId = mact_id_entrada($_POST['ejecucion_id'] ?? null, 'ejecucion_id');
    $motivoDetalle = mact_texto($_POST['motivo'] ?? '');

    mact_validar_texto(
        $motivoDetalle,
        'motivo',
        'Explica brevemente por qué vas a pausar el mantenimiento.',
        10,
        500,
        true
    );
    mact_obtener_tecnico_activo($conexion, $tecnicoId);
    $ubicacion = mact_localizar_ejecucion_propia($conexion, $ejecucionId, $tecnicoId);

    $conexion->beginTransaction();

    mact_bloquear_solicitud($conexion, (int) $ubicacion['solicitud_id']);
    $ejecucion = mact_bloquear_ejecucion_propia($conexion, $ejecucionId, $tecnicoId);
    mact_validar_ejecucion_abierta($conexion, $ejecucion);

    if (mact_cierre_existente($conexion, (int) $ejecucion['solicitud_id'], true)) {
        mact_cancelar($conexion, 'El mantenimiento ya tiene un cierre registrado.', 409);
    }

    if ((string) $ejecucion['estado_ejecucion'] !== 'EN_PROCESO') {
        mact_cancelar($conexion, 'La actividad ya no se encuentra en proceso.', 409);
    }

    if (mact_bloquear_pausa_abierta($conexion, $ejecucionId)) {
        mact_cancelar($conexion, 'La actividad ya tiene una pausa abierta.', 409);
    }

    $ahora = date('Y-m-d H:i:s');

    $stmt = $conexion->prepare(
        "UPDATE ejecuciones_mantenimiento
         SET total_segundos_activos = total_segundos_activos + GREATEST(
                0,
                TIMESTAMPDIFF(
                    SECOND,
                    COALESCE(fecha_ultima_reanudacion, fecha_hora_inicio, :ahora_base),
                    :ahora_calculo
                )
             ),
             estado = 'PAUSADA',
             fecha_ultima_reanudacion = NULL,
             en_proceso_token = NULL
         WHERE id = :ejecucion_id
           AND tecnico_id = :tecnico_id
           AND estado = 'EN_PROCESO'"
    );
    $stmt->bindValue(':ahora_base', $ahora, PDO::PARAM_STR);
    $stmt->bindValue(':ahora_calculo', $ahora, PDO::PARAM_STR);
    $stmt->bindValue(':ejecucion_id', $ejecucionId, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        mact_cancelar($conexion, 'La actividad cambió mientras intentabas pausarla.', 409);
    }

    $stmtParticipacion = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET estado = 'PAUSADO'
         WHERE id = :participacion_id
           AND activo = 1
           AND estado = 'EN_PROCESO'"
    );
    $stmtParticipacion->bindValue(
        ':participacion_id',
        (int) $ejecucion['solicitud_tecnico_id'],
        PDO::PARAM_INT
    );
    $stmtParticipacion->execute();

    if ($stmtParticipacion->rowCount() !== 1) {
        mact_cancelar($conexion, 'No fue posible pausar tu participación.', 409);
    }

    $stmtPausa = $conexion->prepare(
        "INSERT INTO pausas_ejecucion (
            ejecucion_id,
            fecha_hora_inicio,
            fecha_hora_fin,
            duracion_segundos,
            motivo,
            solicitud_urgente_id,
            observaciones,
            creada_por_tipo,
            creada_por_id,
            pausa_abierta_token
         ) VALUES (
            :ejecucion_id,
            :fecha_inicio,
            NULL,
            0,
            'MANUAL',
            NULL,
            :observaciones,
            'TECNICO',
            :tecnico_id,
            1
         )"
    );
    $stmtPausa->bindValue(':ejecucion_id', $ejecucionId, PDO::PARAM_INT);
    $stmtPausa->bindValue(':fecha_inicio', $ahora, PDO::PARAM_STR);
    $stmtPausa->bindValue(':observaciones', $motivoDetalle, PDO::PARAM_STR);
    $stmtPausa->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmtPausa->execute();
    $pausaId = (int) $conexion->lastInsertId();

    $estadoSolicitudAnterior = (string) $ejecucion['estado_solicitud'];
    $estadoSolicitudNuevo = $estadoSolicitudAnterior;

    $stmtActivas = $conexion->prepare(
        "SELECT COUNT(*)
         FROM ejecuciones_mantenimiento
         WHERE solicitud_id = :solicitud_id
           AND estado = 'EN_PROCESO'"
    );
    $stmtActivas->bindValue(
        ':solicitud_id',
        (int) $ejecucion['solicitud_id'],
        PDO::PARAM_INT
    );
    $stmtActivas->execute();

    if ((int) $stmtActivas->fetchColumn() === 0) {
        /*
         * Si esta era la última participación trabajando, la solicitud queda
         * pausada. Se aceptan también estados operativos abiertos para reparar
         * de forma segura una posible desincronización previa entre solicitud
         * y ejecución, sin tocar estados terminales.
         */
        $stmtSolicitud = $conexion->prepare(
            "UPDATE solicitudes
             SET estado = 'PAUSADO'
             WHERE id = :solicitud_id
               AND activo = 1
               AND estado IN ('AGENDADO','EN_PROCESO','PAUSADO','ATRASADO')"
        );
        $stmtSolicitud->bindValue(
            ':solicitud_id',
            (int) $ejecucion['solicitud_id'],
            PDO::PARAM_INT
        );
        $stmtSolicitud->execute();
        $estadoSolicitudNuevo = 'PAUSADO';
    }

    $nombreTecnico = mact_nombre_tecnico($conexion, $tecnicoId);

    mact_historial(
        $conexion,
        (int) $ejecucion['solicitud_id'],
        (int) $ejecucion['solicitud_tecnico_id'],
        mact_id_nullable($ejecucion['programacion_id'] ?? null),
        'PAUSADA',
        $estadoSolicitudAnterior,
        $estadoSolicitudNuevo,
        'TECNICO',
        $tecnicoId,
        'El técnico ' . $nombreTecnico . ' pausó su participación. Motivo: ' . $motivoDetalle
    );

    mact_movimiento(
        $conexion,
        $tecnicoId,
        'PAUSAR_MANTENIMIENTO',
        'Actividad actual',
        'Se pausó la ejecución del mantenimiento ' . (string) $ejecucion['folio']
            . '. Motivo: ' . $motivoDetalle,
        'pausas_ejecucion',
        $pausaId
    );

    mact_notificar_responsables(
        $conexion,
        $ejecucion,
        'Mantenimiento pausado',
        $nombreTecnico . ' pausó su participación en ' . (string) $ejecucion['folio']
            . '. Motivo: ' . $motivoDetalle,
        'WARNING',
        $ejecucionId
    );

    mact_notificar_participantes(
        $conexion,
        (int) $ejecucion['solicitud_id'],
        $tecnicoId,
        'Participación pausada',
        $nombreTecnico . ' pausó su participación en ' . (string) $ejecucion['folio'] . '.',
        'WARNING',
        $ejecucionId
    );

    $conexion->commit();

    sm_responder_json(
        true,
        'La actividad quedó pausada. Podrás reanudarla manualmente cuando estés listo.',
        [
            'ejecucion_id' => $ejecucionId,
            'solicitud_id' => (int) $ejecucion['solicitud_id'],
            'estado' => 'PAUSADA',
            'pausa_id' => $pausaId,
        ]
    );
}

/* =========================================================================
   REANUDAR MANUALMENTE
   ========================================================================= */

function mact_reanudar(PDO $conexion): void
{
    $tecnicoId = mact_tecnico_id();
    $ejecucionId = mact_id_entrada($_POST['ejecucion_id'] ?? null, 'ejecucion_id');
    mact_obtener_tecnico_activo($conexion, $tecnicoId);
    $ubicacion = mact_localizar_ejecucion_propia($conexion, $ejecucionId, $tecnicoId);

    $conexion->beginTransaction();

    mact_bloquear_solicitud($conexion, (int) $ubicacion['solicitud_id']);
    $ejecucion = mact_bloquear_ejecucion_propia($conexion, $ejecucionId, $tecnicoId);
    mact_validar_ejecucion_abierta($conexion, $ejecucion);

    if (mact_cierre_existente($conexion, (int) $ejecucion['solicitud_id'], true)) {
        mact_cancelar($conexion, 'El mantenimiento ya tiene un cierre registrado.', 409);
    }

    if ((string) $ejecucion['estado_ejecucion'] !== 'PAUSADA') {
        mact_cancelar($conexion, 'La actividad ya no se encuentra pausada.', 409);
    }

    $pausa = mact_bloquear_pausa_abierta($conexion, $ejecucionId);

    if (!$pausa) {
        mact_cancelar(
            $conexion,
            'No se encontró una pausa abierta válida para esta actividad.',
            409
        );
    }

    $motivo = strtoupper((string) $pausa['motivo']);

    if (in_array($motivo, ['ADMINISTRATIVA','CAMBIO_PRIORIDAD'], true)) {
        mact_cancelar(
            $conexion,
            'Esta pausa requiere revisión administrativa antes de reanudarse.',
            409
        );
    }

    if ($motivo === 'URGENCIA') {
        $urgenciaId = mact_id_nullable($pausa['solicitud_urgente_id'] ?? null);

        if ($urgenciaId === null) {
            mact_cancelar(
                $conexion,
                'La pausa no conserva una urgencia relacionada válida. Solicita revisión administrativa.',
                409
            );
        }

        $stmtUrgencia = $conexion->prepare(
            "SELECT id, folio, estado
             FROM solicitudes
             WHERE id = :id
               AND tipo_solicitud = 'CORRECTIVO_URGENTE'
             LIMIT 1"
        );
        $stmtUrgencia->bindValue(':id', $urgenciaId, PDO::PARAM_INT);
        $stmtUrgencia->execute();
        $urgencia = $stmtUrgencia->fetch(PDO::FETCH_ASSOC);

        if (!is_array($urgencia)) {
            mact_cancelar(
                $conexion,
                'No fue posible validar la urgencia que originó la pausa.',
                409
            );
        }

        if (!in_array((string) $urgencia['estado'], ['TERMINADO','CANCELADO'], true)) {
            mact_cancelar(
                $conexion,
                'La urgencia ' . (string) $urgencia['folio']
                    . ' todavía continúa abierta. No puedes reanudar este mantenimiento.',
                409
            );
        }
    }

    $stmtActivas = $conexion->prepare(
        "SELECT id, solicitud_id
         FROM ejecuciones_mantenimiento
         WHERE tecnico_id = :tecnico_id
           AND estado = 'EN_PROCESO'
         FOR UPDATE"
    );
    $stmtActivas->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmtActivas->execute();
    $activas = $stmtActivas->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($activas !== []) {
        mact_cancelar(
            $conexion,
            'Ya tienes otra actividad en proceso. Debes pausarla o finalizarla antes de reanudar esta.',
            409
        );
    }

    $ahora = date('Y-m-d H:i:s');
    $inicioPausa = (string) $pausa['fecha_hora_inicio'];
    $duracion = max(0, strtotime($ahora) - strtotime($inicioPausa));

    $stmtCerrarPausa = $conexion->prepare(
        "UPDATE pausas_ejecucion
         SET fecha_hora_fin = :fecha_fin,
             duracion_segundos = :duracion,
             pausa_abierta_token = NULL
         WHERE id = :pausa_id
           AND pausa_abierta_token = 1"
    );
    $stmtCerrarPausa->bindValue(':fecha_fin', $ahora, PDO::PARAM_STR);
    $stmtCerrarPausa->bindValue(':duracion', $duracion, PDO::PARAM_INT);
    $stmtCerrarPausa->bindValue(':pausa_id', (int) $pausa['id'], PDO::PARAM_INT);
    $stmtCerrarPausa->execute();

    if ($stmtCerrarPausa->rowCount() !== 1) {
        mact_cancelar($conexion, 'La pausa cambió mientras intentabas reanudarla.', 409);
    }

    $stmtEjecucion = $conexion->prepare(
        "UPDATE ejecuciones_mantenimiento
         SET estado = 'EN_PROCESO',
             total_segundos_pausa = total_segundos_pausa + :duracion,
             fecha_ultima_reanudacion = :fecha_reanudacion,
             en_proceso_token = 1
         WHERE id = :ejecucion_id
           AND tecnico_id = :tecnico_id
           AND estado = 'PAUSADA'"
    );
    $stmtEjecucion->bindValue(':duracion', $duracion, PDO::PARAM_INT);
    $stmtEjecucion->bindValue(':fecha_reanudacion', $ahora, PDO::PARAM_STR);
    $stmtEjecucion->bindValue(':ejecucion_id', $ejecucionId, PDO::PARAM_INT);
    $stmtEjecucion->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmtEjecucion->execute();

    if ($stmtEjecucion->rowCount() !== 1) {
        mact_cancelar($conexion, 'La ejecución cambió mientras intentabas reanudarla.', 409);
    }

    $stmtParticipacion = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET estado = 'EN_PROCESO'
         WHERE id = :participacion_id
           AND activo = 1
           AND estado = 'PAUSADO'"
    );
    $stmtParticipacion->bindValue(
        ':participacion_id',
        (int) $ejecucion['solicitud_tecnico_id'],
        PDO::PARAM_INT
    );
    $stmtParticipacion->execute();

    if ($stmtParticipacion->rowCount() !== 1) {
        mact_cancelar($conexion, 'No fue posible reanudar tu participación.', 409);
    }

    $estadoSolicitudAnterior = (string) $ejecucion['estado_solicitud'];

    $stmtSolicitud = $conexion->prepare(
        "UPDATE solicitudes
         SET estado = 'EN_PROCESO'
         WHERE id = :solicitud_id
           AND activo = 1
           AND estado IN ('PAUSADO','AGENDADO','ATRASADO','EN_PROCESO')"
    );
    $stmtSolicitud->bindValue(
        ':solicitud_id',
        (int) $ejecucion['solicitud_id'],
        PDO::PARAM_INT
    );
    $stmtSolicitud->execute();

    $nombreTecnico = mact_nombre_tecnico($conexion, $tecnicoId);

    mact_historial(
        $conexion,
        (int) $ejecucion['solicitud_id'],
        (int) $ejecucion['solicitud_tecnico_id'],
        mact_id_nullable($ejecucion['programacion_id'] ?? null),
        'REANUDADA',
        $estadoSolicitudAnterior,
        'EN_PROCESO',
        'TECNICO',
        $tecnicoId,
        'El técnico ' . $nombreTecnico . ' reanudó manualmente su participación.'
    );

    mact_movimiento(
        $conexion,
        $tecnicoId,
        'REANUDAR_MANTENIMIENTO',
        'Actividad actual',
        'Se reanudó manualmente el mantenimiento ' . (string) $ejecucion['folio'] . '.',
        'ejecuciones_mantenimiento',
        $ejecucionId
    );

    mact_notificar_responsables(
        $conexion,
        $ejecucion,
        'Mantenimiento reanudado',
        $nombreTecnico . ' reanudó el mantenimiento ' . (string) $ejecucion['folio'] . '.',
        'INFO',
        $ejecucionId
    );

    mact_notificar_participantes(
        $conexion,
        (int) $ejecucion['solicitud_id'],
        $tecnicoId,
        'Participación reanudada',
        $nombreTecnico . ' reanudó su participación en ' . (string) $ejecucion['folio'] . '.',
        'INFO',
        $ejecucionId
    );

    $conexion->commit();

    sm_responder_json(
        true,
        'El mantenimiento fue reanudado correctamente.',
        [
            'ejecucion_id' => $ejecucionId,
            'solicitud_id' => (int) $ejecucion['solicitud_id'],
            'estado' => 'EN_PROCESO',
        ]
    );
}

/* =========================================================================
   FINALIZAR SOLICITUD COMPLETA
   ========================================================================= */

function mact_finalizar(PDO $conexion): void
{
    $tecnicoId = mact_tecnico_id();
    $ejecucionId = mact_id_entrada($_POST['ejecucion_id'] ?? null, 'ejecucion_id');
    $trabajoQuedo = strtoupper(mact_texto($_POST['trabajo_quedo'] ?? ''));
    $descripcion = mact_texto($_POST['descripcion_trabajo_realizado'] ?? '');
    $queFalto = mact_texto($_POST['que_falto'] ?? '');
    $limpieza = mact_booleano_entrada($_POST['realizo_limpieza_area'] ?? null, 'realizo_limpieza_area');
    $orden = mact_booleano_entrada(
        $_POST['area_ordenada_libre_componentes'] ?? null,
        'area_ordenada_libre_componentes'
    );
    $observaciones = mact_texto($_POST['observaciones_cierre'] ?? '');
    $herramientasIds = mact_ids_recursos_entrada($_POST['herramientas_ids'] ?? []);
    $refaccionesIds = mact_ids_recursos_entrada($_POST['refacciones_ids'] ?? []);
    $herramientasOtras = mact_otros_recursos_entrada(
        $_POST['herramientas_otras'] ?? [],
        'herramienta'
    );
    $refaccionesOtras = mact_otros_recursos_entrada(
        $_POST['refacciones_otras'] ?? [],
        'refacción'
    );
    $sinHerramientas = mact_booleano_entrada(
        $_POST['sin_herramientas_utilizadas'] ?? null,
        'sin_herramientas_utilizadas'
    );
    $sinRefacciones = mact_booleano_entrada(
        $_POST['sin_refacciones_utilizadas'] ?? null,
        'sin_refacciones_utilizadas'
    );

    mact_validar_declaracion_recursos(
        $herramientasIds,
        $herramientasOtras,
        $sinHerramientas,
        'herramientas'
    );
    mact_validar_declaracion_recursos(
        $refaccionesIds,
        $refaccionesOtras,
        $sinRefacciones,
        'refacciones'
    );

    if (!in_array($trabajoQuedo, ['TERMINADO','PARCIAL','PROVISIONAL'], true)) {
        sm_responder_json(
            false,
            'Selecciona cómo quedó el trabajo.',
            ['campo' => 'trabajo_quedo'],
            422
        );
    }

    mact_validar_texto(
        $descripcion,
        'descripcion_trabajo_realizado',
        'Describe claramente el trabajo realizado.',
        20,
        4000,
        true
    );

    if ($trabajoQuedo !== 'TERMINADO') {
        mact_validar_texto(
            $queFalto,
            'que_falto',
            'Indica qué falta por realizar.',
            10,
            2500,
            true
        );
    } elseif ($queFalto !== '') {
        mact_validar_texto(
            $queFalto,
            'que_falto',
            'La explicación de pendientes es demasiado extensa.',
            0,
            2500,
            false
        );
    }

    if ($observaciones !== '') {
        mact_validar_texto(
            $observaciones,
            'observaciones_cierre',
            'Las observaciones son demasiado extensas.',
            0,
            2000,
            false
        );
    }

    mact_obtener_tecnico_activo($conexion, $tecnicoId);
    $ubicacion = mact_localizar_ejecucion_propia($conexion, $ejecucionId, $tecnicoId);
    $conexion->beginTransaction();

    $solicitudId = (int) $ubicacion['solicitud_id'];
    $solicitud = mact_bloquear_solicitud($conexion, $solicitudId);

    if (mact_cierre_existente($conexion, $solicitudId, true)) {
        mact_cancelar($conexion, 'El mantenimiento ya fue finalizado por otro usuario.', 409);
    }

    $participaciones = mact_bloquear_participaciones_solicitud($conexion, $solicitudId);
    $ejecuciones = mact_bloquear_ejecuciones_solicitud($conexion, $solicitudId);
    $ejecucion = mact_bloquear_ejecucion_propia($conexion, $ejecucionId, $tecnicoId);
    mact_validar_ejecucion_abierta($conexion, $ejecucion);

    if (
        (string) $ejecucion['estado_ejecucion'] !== 'EN_PROCESO'
        || (string) $ejecucion['estado_participacion'] !== 'EN_PROCESO'
    ) {
        mact_cancelar(
            $conexion,
            'Solo un técnico que esté trabajando actualmente puede finalizar el mantenimiento.',
            409
        );
    }

    if (in_array((string) $solicitud['estado'], ['TERMINADO','RECHAZADO','CANCELADO'], true)) {
        mact_cancelar($conexion, 'El mantenimiento ya no se encuentra disponible para finalizar.', 409);
    }

    $ejecucionFinalizadorValida = false;

    foreach ($ejecuciones as $filaEjecucion) {
        if (
            (int) $filaEjecucion['id'] === $ejecucionId
            && (string) $filaEjecucion['estado'] === 'EN_PROCESO'
        ) {
            $ejecucionFinalizadorValida = true;
            break;
        }
    }

    if (!$ejecucionFinalizadorValida) {
        mact_cancelar(
            $conexion,
            'La actividad cambió mientras intentabas finalizarla.',
            409
        );
    }

    $ahora = date('Y-m-d H:i:s');

    $stmtCierre = $conexion->prepare(
        "INSERT INTO cierres_mantenimiento (
            solicitud_id,
            cerrado_por_tecnico_id,
            cerrado_por_admin_id,
            fecha_hora_cierre,
            trabajo_quedo,
            descripcion_trabajo_realizado,
            que_falto,
            realizo_limpieza_area,
            area_ordenada_libre_componentes,
            observaciones_cierre,
            sin_herramientas_utilizadas,
            sin_refacciones_utilizadas,
            editado_por_admin_id,
            motivo_edicion
         ) VALUES (
            :solicitud_id,
            :tecnico_id,
            NULL,
            :fecha_cierre,
            :trabajo_quedo,
            :descripcion,
            :que_falto,
            :limpieza,
            :orden,
            :observaciones,
            :sin_herramientas,
            :sin_refacciones,
            NULL,
            NULL
         )"
    );
    $stmtCierre->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtCierre->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmtCierre->bindValue(':fecha_cierre', $ahora, PDO::PARAM_STR);
    $stmtCierre->bindValue(':trabajo_quedo', $trabajoQuedo, PDO::PARAM_STR);
    $stmtCierre->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    mact_bind_nullable_text($stmtCierre, ':que_falto', $queFalto === '' ? null : $queFalto);
    $stmtCierre->bindValue(':limpieza', $limpieza, PDO::PARAM_INT);
    $stmtCierre->bindValue(':orden', $orden, PDO::PARAM_INT);
    mact_bind_nullable_text(
        $stmtCierre,
        ':observaciones',
        $observaciones === '' ? null : $observaciones
    );
    $stmtCierre->bindValue(':sin_herramientas', $sinHerramientas, PDO::PARAM_INT);
    $stmtCierre->bindValue(':sin_refacciones', $sinRefacciones, PDO::PARAM_INT);
    $stmtCierre->execute();
    $cierreId = (int) $conexion->lastInsertId();

    $resultadoRecursos = mact_guardar_recursos_utilizados(
        $conexion,
        $cierreId,
        $solicitudId,
        $tecnicoId,
        $herramientasIds,
        $refaccionesIds,
        $herramientasOtras,
        $refaccionesOtras
    );

    $recursosAprendidos = 0;
    if ((string) $solicitud['tipo_solicitud'] === 'CORRECTIVO_URGENTE') {
        $recursosAprendidos = rsm_actualizar_memoria_urgente_desde_cierre(
            $conexion,
            $solicitudId,
            (int) $solicitud['equipo_id'],
            $tecnicoId,
            $resultadoRecursos['para_memoria']
        );
    }

    if ((int) $resultadoRecursos['sugerencias'] > 0) {
        mact_notificar_sugerencias_recursos(
            $conexion,
            $solicitudId,
            (string) $solicitud['folio'],
            (string) $solicitud['nombre_equipo'],
            $tecnicoId,
            (int) $resultadoRecursos['sugerencias']
        );
    }

    $ejecucionesTerminadas = 0;
    $ejecucionesCanceladas = 0;

    foreach ($ejecuciones as $filaEjecucion) {
        $estado = (string) $filaEjecucion['estado'];
        $id = (int) $filaEjecucion['id'];

        /*
         * Una ejecución PENDIENTE corresponde a alguien que nunca comenzó.
         * Al cerrar la solicitud debe quedar CANCELADA para que no sobreviva
         * una ejecución abierta asociada a una participación NO_PARTICIPO.
         */
        if ($estado === 'PENDIENTE') {
            $stmtCancelarPendiente = $conexion->prepare(
                "UPDATE ejecuciones_mantenimiento
                 SET estado = 'CANCELADA',
                     fecha_hora_fin = COALESCE(fecha_hora_fin, :fecha_fin),
                     fecha_hora_fin_original = COALESCE(fecha_hora_fin_original, :fecha_fin_original),
                     fecha_ultima_reanudacion = NULL,
                     en_proceso_token = NULL
                 WHERE id = :ejecucion_id
                   AND estado = 'PENDIENTE'"
            );
            $stmtCancelarPendiente->bindValue(':fecha_fin', $ahora, PDO::PARAM_STR);
            $stmtCancelarPendiente->bindValue(':fecha_fin_original', $ahora, PDO::PARAM_STR);
            $stmtCancelarPendiente->bindValue(':ejecucion_id', $id, PDO::PARAM_INT);
            $stmtCancelarPendiente->execute();
            $ejecucionesCanceladas += $stmtCancelarPendiente->rowCount();
            continue;
        }

        if (!in_array($estado, ['EN_PROCESO','PAUSADA'], true)) {
            continue;
        }

        $segundosPausaAdicional = 0;

        if ($estado === 'PAUSADA') {
            $pausa = mact_bloquear_pausa_abierta($conexion, $id);

            if ($pausa) {
                $segundosPausaAdicional = max(
                    0,
                    strtotime($ahora) - strtotime((string) $pausa['fecha_hora_inicio'])
                );

                $stmtCerrarPausa = $conexion->prepare(
                    "UPDATE pausas_ejecucion
                     SET fecha_hora_fin = :fecha_fin,
                         duracion_segundos = :duracion,
                         pausa_abierta_token = NULL
                     WHERE id = :pausa_id
                       AND pausa_abierta_token = 1"
                );
                $stmtCerrarPausa->bindValue(':fecha_fin', $ahora, PDO::PARAM_STR);
                $stmtCerrarPausa->bindValue(
                    ':duracion',
                    $segundosPausaAdicional,
                    PDO::PARAM_INT
                );
                $stmtCerrarPausa->bindValue(':pausa_id', (int) $pausa['id'], PDO::PARAM_INT);
                $stmtCerrarPausa->execute();
            }
        }

        if ($estado === 'EN_PROCESO') {
            $stmtTerminar = $conexion->prepare(
                "UPDATE ejecuciones_mantenimiento
                 SET total_segundos_activos = total_segundos_activos + GREATEST(
                        0,
                        TIMESTAMPDIFF(
                            SECOND,
                            COALESCE(fecha_ultima_reanudacion, fecha_hora_inicio, :ahora_base),
                            :ahora_calculo
                        )
                     ),
                     estado = 'TERMINADA',
                     fecha_hora_fin = :fecha_fin,
                     fecha_hora_fin_original = COALESCE(fecha_hora_fin_original, :fecha_fin_original),
                     fecha_ultima_reanudacion = NULL,
                     en_proceso_token = NULL
                 WHERE id = :ejecucion_id
                   AND estado = 'EN_PROCESO'"
            );
            $stmtTerminar->bindValue(':ahora_base', $ahora, PDO::PARAM_STR);
            $stmtTerminar->bindValue(':ahora_calculo', $ahora, PDO::PARAM_STR);
            $stmtTerminar->bindValue(':fecha_fin', $ahora, PDO::PARAM_STR);
            $stmtTerminar->bindValue(':fecha_fin_original', $ahora, PDO::PARAM_STR);
            $stmtTerminar->bindValue(':ejecucion_id', $id, PDO::PARAM_INT);
            $stmtTerminar->execute();
            $ejecucionesTerminadas += $stmtTerminar->rowCount();
        } else {
            $stmtTerminar = $conexion->prepare(
                "UPDATE ejecuciones_mantenimiento
                 SET total_segundos_pausa = total_segundos_pausa + :pausa_adicional,
                     estado = 'TERMINADA',
                     fecha_hora_fin = :fecha_fin,
                     fecha_hora_fin_original = COALESCE(fecha_hora_fin_original, :fecha_fin_original),
                     fecha_ultima_reanudacion = NULL,
                     en_proceso_token = NULL
                 WHERE id = :ejecucion_id
                   AND estado = 'PAUSADA'"
            );
            $stmtTerminar->bindValue(
                ':pausa_adicional',
                $segundosPausaAdicional,
                PDO::PARAM_INT
            );
            $stmtTerminar->bindValue(':fecha_fin', $ahora, PDO::PARAM_STR);
            $stmtTerminar->bindValue(':fecha_fin_original', $ahora, PDO::PARAM_STR);
            $stmtTerminar->bindValue(':ejecucion_id', $id, PDO::PARAM_INT);
            $stmtTerminar->execute();
            $ejecucionesTerminadas += $stmtTerminar->rowCount();
        }
    }

    if ($ejecucionesTerminadas < 1) {
        mact_cancelar(
            $conexion,
            'La actividad cambió mientras intentabas finalizarla. Actualiza la pantalla.',
            409
        );
    }

    $esUrgente = (string) $solicitud['tipo_solicitud'] === 'CORRECTIVO_URGENTE';
    $resultado = 'NO_APLICA';

    if (!$esUrgente) {
        $fechaLimite = mact_fecha_limite_solicitud($conexion, $solicitudId);
        if ($fechaLimite !== null) {
            $resultado = date('Y-m-d') <= $fechaLimite ? 'A_TIEMPO' : 'TARDE';
        }
    }

    $stmtParticiparon = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET estado = 'TERMINADO',
             resultado_cumplimiento = :resultado,
             fecha_resultado = :fecha_resultado
         WHERE solicitud_id = :solicitud_id
           AND activo = 1
           AND estado IN ('EN_PROCESO','PAUSADO')"
    );
    $stmtParticiparon->bindValue(':resultado', $resultado, PDO::PARAM_STR);
    $stmtParticiparon->bindValue(':fecha_resultado', $ahora, PDO::PARAM_STR);
    $stmtParticiparon->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtParticiparon->execute();

    $stmtNoParticiparon = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET estado = 'NO_PARTICIPO',
             resultado_cumplimiento = 'NO_APLICA',
             fecha_resultado = :fecha_resultado
         WHERE solicitud_id = :solicitud_id
           AND activo = 1
           AND estado IN ('ASIGNADO','ACEPTADO')"
    );
    $stmtNoParticiparon->bindValue(':fecha_resultado', $ahora, PDO::PARAM_STR);
    $stmtNoParticiparon->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtNoParticiparon->execute();

    $stmtSolicitud = $conexion->prepare(
        "UPDATE solicitudes
         SET estado = 'TERMINADO'
         WHERE id = :solicitud_id
           AND activo = 1
           AND estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')"
    );
    $stmtSolicitud->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtSolicitud->execute();

    if ($stmtSolicitud->rowCount() !== 1) {
        mact_cancelar(
            $conexion,
            'La solicitud cambió mientras intentabas finalizarla.',
            409
        );
    }

    if (!$esUrgente) {
        $stmtProgramacion = $conexion->prepare(
            "UPDATE programaciones_mantenimiento
             SET estado = 'CUMPLIDA'
             WHERE solicitud_id = :solicitud_id
               AND es_actual = 1
               AND estado IN ('PROGRAMADA','VENCIDA')"
        );
        $stmtProgramacion->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmtProgramacion->execute();
    }

    $nombreTecnico = mact_nombre_tecnico($conexion, $tecnicoId);
    $descripcionHistorial = $esUrgente
        ? 'El técnico ' . $nombreTecnico . ' cerró la urgencia para todos los participantes. '
            . 'Los mantenimientos anteriores pausados quedaron pendientes de reanudación manual.'
        : 'El técnico ' . $nombreTecnico . ' finalizó el mantenimiento para todo el equipo participante.';

    mact_historial(
        $conexion,
        $solicitudId,
        (int) $ejecucion['solicitud_tecnico_id'],
        mact_id_nullable($ejecucion['programacion_id'] ?? null),
        'TERMINADA',
        (string) $solicitud['estado'],
        'TERMINADO',
        'TECNICO',
        $tecnicoId,
        $descripcionHistorial . ' Resultado: ' . $trabajoQuedo
            . '. Recursos reales registrados: ' . (int) $resultadoRecursos['total']
            . '. Sugerencias nuevas: ' . (int) $resultadoRecursos['sugerencias']
            . '. ' . ($recursosAprendidos > 0
                ? 'La memoria urgente se actualizó con el cierre técnico.'
                : 'La recomendación administrativa, cuando existe, se conservó sin cambios.')
    );

    mact_movimiento(
        $conexion,
        $tecnicoId,
        $esUrgente ? 'FINALIZAR_URGENCIA' : 'FINALIZAR_MANTENIMIENTO',
        'Actividad actual',
        'Se finalizó ' . (string) $solicitud['folio'] . ' para todos los participantes. '
            . 'Resultado: ' . $trabajoQuedo . '.',
        'cierres_mantenimiento',
        $cierreId
    );

    $titulo = $esUrgente ? 'Urgencia finalizada' : 'Mantenimiento finalizado';
    $mensaje = $nombreTecnico . ' finalizó ' . (string) $solicitud['folio']
        . '. El trabajo quedó: ' . strtolower($trabajoQuedo) . '.';

    mact_notificar_responsables(
        $conexion,
        array_merge($ejecucion, $solicitud),
        $titulo,
        $mensaje,
        'SUCCESS',
        $ejecucionId
    );

    mact_notificar_participantes(
        $conexion,
        $solicitudId,
        0,
        $titulo,
        $mensaje,
        'SUCCESS',
        $ejecucionId
    );

    $reanudarManualmente = [];

    if ($esUrgente) {
        $reanudarManualmente = mact_notificar_mantenimientos_reanudables(
            $conexion,
            $solicitudId,
            (string) $solicitud['folio']
        );
    }

    $conexion->commit();

    sm_responder_json(
        true,
        $esUrgente
            ? 'La urgencia fue finalizada para todos los participantes.'
            : 'El mantenimiento fue finalizado correctamente.',
        [
            'solicitud_id' => $solicitudId,
            'cierre_id' => $cierreId,
            'estado' => 'TERMINADO',
            'trabajo_quedo' => $trabajoQuedo,
            'mantenimientos_por_reanudar' => $reanudarManualmente,
            'reanudacion_automatica' => false,
            'ejecuciones_terminadas' => $ejecucionesTerminadas,
            'ejecuciones_pendientes_canceladas' => $ejecucionesCanceladas,
            'recursos_utilizados' => (int) $resultadoRecursos['total'],
            'sugerencias_generadas' => (int) $resultadoRecursos['sugerencias'],
            'recursos_aprendidos_urgencia' => $recursosAprendidos,
            'redirect' => 'mantenimientos_finalizados.php?solicitud_id=' . $solicitudId,
        ]
    );
}

/* =========================================================================
   RECURSOS UTILIZADOS EN EL CIERRE
   ========================================================================= */

function mact_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? (int) mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function mact_recortar(string $texto, int $limite): string
{
    if (mact_longitud($texto) <= $limite) {
        return $texto;
    }

    return function_exists('mb_substr')
        ? (string) mb_substr($texto, 0, $limite, 'UTF-8')
        : substr($texto, 0, $limite);
}

function mact_normalizar_espacios(string $texto): string
{
    $texto = preg_replace('/[\t ]+/u', ' ', $texto) ?? $texto;
    return trim($texto);
}

/**
 * @return int[]
 */
function mact_ids_recursos_entrada($valor): array
{
    if ($valor === null || $valor === '') {
        return [];
    }

    $valores = is_array($valor) ? $valor : [$valor];

    if (count($valores) > 50) {
        sm_responder_json(
            false,
            'No puedes registrar más de 50 recursos del mismo tipo en un cierre.',
            ['campo' => 'recursos_utilizados'],
            422
        );
    }

    $ids = [];

    foreach ($valores as $item) {
        $id = filter_var(
            $item,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            sm_responder_json(
                false,
                'Una herramienta o refacción seleccionada no es válida.',
                ['campo' => 'recursos_utilizados'],
                422
            );
        }

        $ids[(int) $id] = (int) $id;
    }

    return array_values($ids);
}

/**
 * @return string[]
 */
function mact_otros_recursos_entrada($valor, string $etiqueta): array
{
    if ($valor === null || $valor === '') {
        return [];
    }

    $valores = is_array($valor) ? $valor : [$valor];

    if (count($valores) > 10) {
        sm_responder_json(
            false,
            'Puedes registrar como máximo 10 elementos no catalogados por tipo.',
            ['campo' => 'otros_recursos'],
            422
        );
    }

    $resultado = [];

    foreach ($valores as $item) {
        if (!is_scalar($item)) {
            continue;
        }

        $nombre = mact_normalizar_espacios(mact_texto($item));

        if ($nombre === '') {
            continue;
        }

        $longitud = mact_longitud($nombre);
        if ($longitud < 2 || $longitud > 150) {
            sm_responder_json(
                false,
                'El nombre de otra ' . $etiqueta . ' debe contener entre 2 y 150 caracteres.',
                ['campo' => 'otros_recursos'],
                422
            );
        }

        if (!preg_match('/[\p{L}\p{N}]/u', $nombre) || preg_match('/[<>\r\n]/u', $nombre)) {
            sm_responder_json(
                false,
                'El nombre de otra ' . $etiqueta . ' contiene caracteres no permitidos.',
                ['campo' => 'otros_recursos'],
                422
            );
        }

        $clave = rsm_mayusculas($nombre);
        $resultado[$clave] = $nombre;
    }

    return array_values($resultado);
}

function mact_validar_declaracion_recursos(
    array $ids,
    array $otros,
    int $sinRecursos,
    string $etiqueta
): void {
    $tieneRecursos = $ids !== [] || $otros !== [];

    if ($sinRecursos === 1 && $tieneRecursos) {
        sm_responder_json(
            false,
            'No puedes marcar que no utilizaste ' . $etiqueta . ' y registrar elementos al mismo tiempo.',
            ['campo' => 'sin_' . $etiqueta . '_utilizadas'],
            422
        );
    }

    if ($sinRecursos !== 1 && !$tieneRecursos) {
        sm_responder_json(
            false,
            'Registra las ' . $etiqueta . ' realmente utilizadas o confirma que no se utilizó ninguna.',
            ['campo' => $etiqueta . '_utilizadas'],
            422
        );
    }
}

/**
 * @param int[] $herramientasIds
 * @param int[] $refaccionesIds
 * @param string[] $herramientasOtras
 * @param string[] $refaccionesOtras
 * @return array<string, mixed>
 */
function mact_guardar_recursos_utilizados(
    PDO $conexion,
    int $cierreId,
    int $solicitudId,
    int $tecnicoId,
    array $herramientasIds,
    array $refaccionesIds,
    array $herramientasOtras,
    array $refaccionesOtras
): array {
    $tiposPorId = [];

    foreach ($herramientasIds as $id) {
        $tiposPorId[(int) $id] = RSM_TIPO_HERRAMIENTA;
    }

    foreach ($refaccionesIds as $id) {
        if (isset($tiposPorId[(int) $id])) {
            mact_cancelar(
                $conexion,
                'Un recurso no puede registrarse como herramienta y refacción al mismo tiempo.',
                422
            );
        }
        $tiposPorId[(int) $id] = RSM_TIPO_REFACCION;
    }

    $catalogo = rsm_recursos_catalogo_por_ids($conexion, array_keys($tiposPorId));

    if (count($catalogo) !== count($tiposPorId)) {
        mact_cancelar(
            $conexion,
            'Una herramienta o refacción seleccionada ya no existe en el catálogo.',
            422
        );
    }

    $recursos = [];

    foreach ($tiposPorId as $id => $tipoEsperado) {
        $recurso = $catalogo[$id] ?? null;

        if (!is_array($recurso) || (string) $recurso['tipo_recurso'] !== $tipoEsperado) {
            mact_cancelar(
                $conexion,
                'Una selección no corresponde al tipo de recurso esperado.',
                422
            );
        }

        if ((int) $recurso['activo'] !== 1) {
            mact_cancelar(
                $conexion,
                'El recurso "' . (string) $recurso['nombre'] . '" fue desactivado. Actualiza el buscador y selecciona otro.',
                409
            );
        }

        $recursos['ID:' . $id] = [
            'tipo_recurso' => $tipoEsperado,
            'recurso_id' => $id,
            'nombre_no_catalogado' => null,
            'nombre' => (string) $recurso['nombre'],
        ];
    }

    $otrosPorTipo = [
        RSM_TIPO_HERRAMIENTA => $herramientasOtras,
        RSM_TIPO_REFACCION => $refaccionesOtras,
    ];

    foreach ($otrosPorTipo as $tipo => $nombres) {
        foreach ($nombres as $nombre) {
            $existente = rsm_buscar_recurso_por_nombre_tipo($conexion, $tipo, $nombre);

            if (is_array($existente) && (int) ($existente['activo'] ?? 0) === 1) {
                $id = (int) $existente['id'];
                $recursos['ID:' . $id] = [
                    'tipo_recurso' => $tipo,
                    'recurso_id' => $id,
                    'nombre_no_catalogado' => null,
                    'nombre' => (string) $existente['nombre'],
                ];
                continue;
            }

            $clave = 'LIBRE:' . $tipo . ':' . rsm_mayusculas($nombre);
            $recursos[$clave] = [
                'tipo_recurso' => $tipo,
                'recurso_id' => null,
                'nombre_no_catalogado' => $nombre,
                'nombre' => $nombre,
            ];
        }
    }

    if ($recursos === []) {
        return [
            'total' => 0,
            'sugerencias' => 0,
            'para_memoria' => [],
        ];
    }

    $stmtRecurso = $conexion->prepare(
        "INSERT INTO cierre_recursos_utilizados (
            cierre_id,
            tipo_recurso,
            recurso_id,
            nombre_no_catalogado,
            registrado_por_tecnico_id,
            registrado_por_admin_id,
            editado_por_admin_id,
            fecha_registro,
            fecha_actualizacion
         ) VALUES (
            :cierre_id,
            :tipo_recurso,
            :recurso_id,
            :nombre_no_catalogado,
            :tecnico_id,
            NULL,
            NULL,
            NOW(),
            NOW()
         )"
    );

    $stmtSugerencia = $conexion->prepare(
        "INSERT INTO sugerencias_recursos (
            cierre_recurso_utilizado_id,
            tipo_recurso,
            nombre_sugerido,
            tecnico_id,
            estado,
            recurso_creado_id,
            atendida_por_admin_id,
            observaciones_admin,
            fecha_registro,
            fecha_atencion
         ) VALUES (
            :cierre_recurso_id,
            :tipo_recurso,
            :nombre_sugerido,
            :tecnico_id,
            'PENDIENTE',
            NULL,
            NULL,
            NULL,
            NOW(),
            NULL
         )"
    );

    $sugerencias = 0;
    $paraMemoria = [];

    foreach ($recursos as $recurso) {
        $stmtRecurso->bindValue(':cierre_id', $cierreId, PDO::PARAM_INT);
        $stmtRecurso->bindValue(':tipo_recurso', (string) $recurso['tipo_recurso'], PDO::PARAM_STR);

        if ($recurso['recurso_id'] === null) {
            $stmtRecurso->bindValue(':recurso_id', null, PDO::PARAM_NULL);
            $stmtRecurso->bindValue(
                ':nombre_no_catalogado',
                (string) $recurso['nombre_no_catalogado'],
                PDO::PARAM_STR
            );
        } else {
            $stmtRecurso->bindValue(':recurso_id', (int) $recurso['recurso_id'], PDO::PARAM_INT);
            $stmtRecurso->bindValue(':nombre_no_catalogado', null, PDO::PARAM_NULL);
        }

        $stmtRecurso->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
        $stmtRecurso->execute();
        $cierreRecursoId = (int) $conexion->lastInsertId();

        if ($recurso['recurso_id'] === null) {
            $stmtSugerencia->bindValue(':cierre_recurso_id', $cierreRecursoId, PDO::PARAM_INT);
            $stmtSugerencia->bindValue(':tipo_recurso', (string) $recurso['tipo_recurso'], PDO::PARAM_STR);
            $stmtSugerencia->bindValue(':nombre_sugerido', (string) $recurso['nombre_no_catalogado'], PDO::PARAM_STR);
            $stmtSugerencia->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
            $stmtSugerencia->execute();
            $sugerencias++;
        }

        $paraMemoria[] = [
            'tipo_recurso' => (string) $recurso['tipo_recurso'],
            'recurso_id' => $recurso['recurso_id'] === null ? 0 : (int) $recurso['recurso_id'],
            'nombre_no_catalogado' => $recurso['nombre_no_catalogado'],
        ];
    }

    return [
        'total' => count($recursos),
        'sugerencias' => $sugerencias,
        'para_memoria' => $paraMemoria,
    ];
}

function mact_notificar_sugerencias_recursos(
    PDO $conexion,
    int $solicitudId,
    string $folio,
    string $equipo,
    int $tecnicoId,
    int $cantidad
): void {
    if ($cantidad < 1) {
        return;
    }

    $nombreTecnico = mact_nombre_tecnico($conexion, $tecnicoId);
    $stmtAdmins = $conexion->query(
        "SELECT id
         FROM administradores
         WHERE activo = 1
         ORDER BY id"
    );
    $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $stmt = $conexion->prepare(
        "INSERT IGNORE INTO notificaciones (
            tipo_usuario,
            usuario_id,
            solicitud_id,
            rutina_alerta_id,
            ejecucion_id,
            clave_dedupe,
            titulo,
            mensaje,
            tipo,
            leida,
            fecha_lectura,
            fecha_creacion
         ) VALUES (
            'ADMIN',
            :admin_id,
            :solicitud_id,
            NULL,
            NULL,
            :clave_dedupe,
            'Nuevos recursos sugeridos',
            :mensaje,
            'WARNING',
            0,
            NULL,
            NOW()
         )"
    );

    $mensaje = $nombreTecnico . ' registró ' . $cantidad
        . ($cantidad === 1 ? ' recurso no catalogado' : ' recursos no catalogados')
        . ' al cerrar ' . $folio . ' del equipo ' . $equipo
        . '. Revisa las sugerencias en Herramientas y refacciones.';

    foreach ($admins as $adminId) {
        $id = (int) $adminId;
        if ($id < 1) {
            continue;
        }

        $stmt->bindValue(':admin_id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(
            ':clave_dedupe',
            'sugerencias-recursos-' . $solicitudId . '-admin-' . $id,
            PDO::PARAM_STR
        );
        $stmt->bindValue(':mensaje', mact_recortar($mensaje, 1000), PDO::PARAM_STR);
        $stmt->execute();
    }
}

/* =========================================================================
   BLOQUEOS Y VALIDACIONES
   ========================================================================= */

function mact_localizar_ejecucion_propia(
    PDO $conexion,
    int $ejecucionId,
    int $tecnicoId
): array {
    $stmt = $conexion->prepare(
        "SELECT id, solicitud_id
         FROM ejecuciones_mantenimiento
         WHERE id = :ejecucion_id
           AND tecnico_id = :tecnico_id
         LIMIT 1"
    );
    $stmt->bindValue(':ejecucion_id', $ejecucionId, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($fila)) {
        sm_responder_json(false, 'La actividad no existe o no pertenece a tu usuario.', [], 404);
    }

    return $fila;
}

function mact_bloquear_ejecucion_propia(
    PDO $conexion,
    int $ejecucionId,
    int $tecnicoId
): array {
    $stmt = $conexion->prepare(
        "SELECT
            em.id AS ejecucion_id,
            em.solicitud_id,
            em.solicitud_tecnico_id,
            em.tecnico_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.fecha_ultima_reanudacion,
            em.total_segundos_activos,
            em.total_segundos_pausa,
            st.estado AS estado_participacion,
            st.programacion_id,
            st.origen,
            s.folio,
            s.tipo_solicitud,
            s.estado AS estado_solicitud,
            s.solicitante_id,
            s.administrador_solicitante_id,
            s.activo,
            e.nombre_equipo
         FROM ejecuciones_mantenimiento em
         INNER JOIN solicitud_tecnicos st
                 ON st.id = em.solicitud_tecnico_id
                AND st.solicitud_id = em.solicitud_id
                AND st.tecnico_id = em.tecnico_id
         INNER JOIN solicitudes s
                 ON s.id = em.solicitud_id
         INNER JOIN equipos e
                 ON e.id = s.equipo_id
         WHERE em.id = :ejecucion_id
           AND em.tecnico_id = :tecnico_id
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bindValue(':ejecucion_id', $ejecucionId, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($fila)) {
        mact_cancelar($conexion, 'La actividad no existe o no pertenece a tu usuario.', 404);
    }

    return $fila;
}

function mact_bloquear_solicitud(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            s.*,
            e.nombre_equipo,
            e.codigo_equipo
         FROM solicitudes s
         INNER JOIN equipos e ON e.id = s.equipo_id
         WHERE s.id = :id
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bindValue(':id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($fila)) {
        mact_cancelar($conexion, 'La solicitud ya no existe.', 404);
    }

    return $fila;
}

function mact_bloquear_pausa_abierta(PDO $conexion, int $ejecucionId): ?array
{
    $stmt = $conexion->prepare(
        "SELECT *
         FROM pausas_ejecucion
         WHERE ejecucion_id = :ejecucion_id
           AND pausa_abierta_token = 1
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bindValue(':ejecucion_id', $ejecucionId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($fila) ? $fila : null;
}

function mact_bloquear_participaciones_solicitud(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT *
         FROM solicitud_tecnicos
         WHERE solicitud_id = :solicitud_id
           AND activo = 1
         ORDER BY id
         FOR UPDATE"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mact_bloquear_ejecuciones_solicitud(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT *
         FROM ejecuciones_mantenimiento
         WHERE solicitud_id = :solicitud_id
         ORDER BY id
         FOR UPDATE"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mact_cierre_existente(
    PDO $conexion,
    int $solicitudId,
    bool $bloquear = false
): ?array {
    $sql = "SELECT id, fecha_hora_cierre, trabajo_quedo
            FROM cierres_mantenimiento
            WHERE solicitud_id = :solicitud_id
            LIMIT 1";

    if ($bloquear) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($fila) ? $fila : null;
}

function mact_validar_ejecucion_abierta(PDO $conexion, array $ejecucion): void
{
    if ((int) ($ejecucion['activo'] ?? 0) !== 1) {
        mact_cancelar($conexion, 'La solicitud se encuentra inactiva.', 409);
    }

    if (in_array(
        (string) $ejecucion['estado_solicitud'],
        ['TERMINADO','RECHAZADO','CANCELADO'],
        true
    )) {
        mact_cancelar($conexion, 'La solicitud ya no se encuentra abierta.', 409);
    }
}

function mact_fecha_limite_solicitud(PDO $conexion, int $solicitudId): ?string
{
    $stmt = $conexion->prepare(
        "SELECT fecha_limite
         FROM programaciones_mantenimiento
         WHERE solicitud_id = :solicitud_id
           AND es_actual = 1
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $valor = $stmt->fetchColumn();

    return is_string($valor) && $valor !== '' ? $valor : null;
}

/* =========================================================================
   NOTIFICACIONES, HISTORIAL Y AUDITORÍA
   ========================================================================= */

function mact_notificar_mantenimientos_reanudables(
    PDO $conexion,
    int $urgenciaId,
    string $folioUrgencia
): array {
    $stmt = $conexion->prepare(
        "SELECT
            pe.id AS pausa_id,
            em.id AS ejecucion_id,
            em.tecnico_id,
            s.id AS solicitud_id,
            s.folio,
            e.nombre_equipo
         FROM pausas_ejecucion pe
         INNER JOIN ejecuciones_mantenimiento em
                 ON em.id = pe.ejecucion_id
                AND em.estado = 'PAUSADA'
         INNER JOIN solicitudes s
                 ON s.id = em.solicitud_id
                AND s.activo = 1
                AND s.estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')
         INNER JOIN equipos e
                 ON e.id = s.equipo_id
         WHERE pe.solicitud_urgente_id = :urgencia_id
           AND pe.motivo = 'URGENCIA'
           AND pe.pausa_abierta_token = 1
         ORDER BY pe.id"
    );
    $stmt->bindValue(':urgencia_id', $urgenciaId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($filas as $fila) {
        mact_insertar_notificacion(
            $conexion,
            'TECNICO',
            (int) $fila['tecnico_id'],
            (int) $fila['solicitud_id'],
            (int) $fila['ejecucion_id'],
            'Mantenimiento listo para reanudar',
            'La urgencia ' . $folioUrgencia . ' terminó. El mantenimiento '
                . (string) $fila['folio'] . ' permanece pausado. Reanúdalo manualmente cuando estés listo.',
            'WARNING'
        );
    }

    return array_map(
        static function (array $fila): array {
            return [
                'ejecucion_id' => (int) $fila['ejecucion_id'],
                'solicitud_id' => (int) $fila['solicitud_id'],
                'tecnico_id' => (int) $fila['tecnico_id'],
                'folio' => (string) $fila['folio'],
                'equipo' => (string) $fila['nombre_equipo'],
                'requiere_reanudacion_manual' => true,
            ];
        },
        $filas
    );
}

function mact_historial(
    PDO $conexion,
    int $solicitudId,
    ?int $participacionId,
    ?int $programacionId,
    string $evento,
    ?string $estadoAnterior,
    ?string $estadoNuevo,
    string $actorTipo,
    ?int $actorId,
    string $descripcion
): void {
    $stmt = $conexion->prepare(
        "INSERT INTO historial_solicitudes (
            solicitud_id,
            solicitud_tecnico_id,
            programacion_id,
            evento,
            estado_anterior,
            estado_nuevo,
            actor_tipo,
            actor_id,
            descripcion
         ) VALUES (
            :solicitud_id,
            :participacion_id,
            :programacion_id,
            :evento,
            :estado_anterior,
            :estado_nuevo,
            :actor_tipo,
            :actor_id,
            :descripcion
         )"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    mact_bind_nullable_int($stmt, ':participacion_id', $participacionId);
    mact_bind_nullable_int($stmt, ':programacion_id', $programacionId);
    $stmt->bindValue(':evento', $evento, PDO::PARAM_STR);
    mact_bind_nullable_text($stmt, ':estado_anterior', $estadoAnterior);
    mact_bind_nullable_text($stmt, ':estado_nuevo', $estadoNuevo);
    $stmt->bindValue(':actor_tipo', $actorTipo, PDO::PARAM_STR);
    mact_bind_nullable_int($stmt, ':actor_id', $actorId);
    $stmt->bindValue(':descripcion', mact_limitar($descripcion, 5000), PDO::PARAM_STR);
    $stmt->execute();
}

function mact_movimiento(
    PDO $conexion,
    int $tecnicoId,
    string $accion,
    string $modulo,
    string $descripcion,
    string $tabla,
    int $registroId
): void {
    $stmt = $conexion->prepare(
        "INSERT INTO movimientos_sistema (
            tipo_usuario,
            usuario_id,
            accion,
            modulo,
            descripcion,
            tabla_afectada,
            registro_id,
            ip_address,
            user_agent
         ) VALUES (
            'TECNICO',
            :usuario_id,
            :accion,
            :modulo,
            :descripcion,
            :tabla,
            :registro_id,
            :ip,
            :agente
         )"
    );
    $stmt->bindValue(':usuario_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', mact_limitar($accion, 100), PDO::PARAM_STR);
    $stmt->bindValue(':modulo', mact_limitar($modulo, 100), PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', mact_limitar($descripcion, 5000), PDO::PARAM_STR);
    $stmt->bindValue(':tabla', mact_limitar($tabla, 100), PDO::PARAM_STR);
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    $stmt->bindValue(
        ':ip',
        mact_limitar((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 60),
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':agente',
        mact_limitar((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 255),
        PDO::PARAM_STR
    );
    $stmt->execute();
}

function mact_notificar_responsables(
    PDO $conexion,
    array $solicitud,
    string $titulo,
    string $mensaje,
    string $tipo,
    ?int $ejecucionId = null
): void {
    $solicitudId = (int) ($solicitud['solicitud_id'] ?? $solicitud['id'] ?? 0);

    $stmtAdmins = $conexion->query(
        "SELECT id
         FROM administradores
         WHERE activo = 1
         ORDER BY id"
    );
    $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN) ?: [];

    foreach ($admins as $adminId) {
        mact_insertar_notificacion(
            $conexion,
            'ADMIN',
            (int) $adminId,
            $solicitudId,
            $ejecucionId,
            $titulo,
            $mensaje,
            $tipo
        );
    }

    $solicitanteId = mact_id_nullable($solicitud['solicitante_id'] ?? null);
    $adminSolicitanteId = mact_id_nullable($solicitud['administrador_solicitante_id'] ?? null);

    if ($solicitanteId !== null) {
        mact_insertar_notificacion(
            $conexion,
            'SOLICITANTE',
            $solicitanteId,
            $solicitudId,
            $ejecucionId,
            $titulo,
            $mensaje,
            $tipo
        );
    } elseif (
        $adminSolicitanteId !== null
        && !in_array($adminSolicitanteId, array_map('intval', $admins), true)
    ) {
        mact_insertar_notificacion(
            $conexion,
            'ADMIN',
            $adminSolicitanteId,
            $solicitudId,
            $ejecucionId,
            $titulo,
            $mensaje,
            $tipo
        );
    }
}

function mact_notificar_participantes(
    PDO $conexion,
    int $solicitudId,
    int $exceptoTecnicoId,
    string $titulo,
    string $mensaje,
    string $tipo,
    ?int $ejecucionId = null
): void {
    $stmt = $conexion->prepare(
        "SELECT DISTINCT tecnico_id
         FROM solicitud_tecnicos
         WHERE solicitud_id = :solicitud_id
           AND activo = 1
           AND tecnico_id <> :excepto"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':excepto', $exceptoTecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    $tecnicos = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    foreach ($tecnicos as $tecnicoId) {
        mact_insertar_notificacion(
            $conexion,
            'TECNICO',
            (int) $tecnicoId,
            $solicitudId,
            $ejecucionId,
            $titulo,
            $mensaje,
            $tipo
        );
    }
}

function mact_insertar_notificacion(
    PDO $conexion,
    string $tipoUsuario,
    int $usuarioId,
    ?int $solicitudId,
    ?int $ejecucionId,
    string $titulo,
    string $mensaje,
    string $tipo
): void {
    $stmt = $conexion->prepare(
        "INSERT INTO notificaciones (
            tipo_usuario,
            usuario_id,
            solicitud_id,
            rutina_alerta_id,
            ejecucion_id,
            titulo,
            mensaje,
            tipo,
            leida
         ) VALUES (
            :tipo_usuario,
            :usuario_id,
            :solicitud_id,
            NULL,
            :ejecucion_id,
            :titulo,
            :mensaje,
            :tipo,
            0
         )"
    );
    $stmt->bindValue(':tipo_usuario', $tipoUsuario, PDO::PARAM_STR);
    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    mact_bind_nullable_int($stmt, ':solicitud_id', $solicitudId);
    mact_bind_nullable_int($stmt, ':ejecucion_id', $ejecucionId);
    $stmt->bindValue(':titulo', mact_limitar($titulo, 180), PDO::PARAM_STR);
    $stmt->bindValue(':mensaje', mact_limitar($mensaje, 1000), PDO::PARAM_STR);
    $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
    $stmt->execute();
}

/* =========================================================================
   PERFIL Y UTILIDADES
   ========================================================================= */

function mact_tecnico_id(): int
{
    $id = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false || strtoupper((string) ($_SESSION['tipo_usuario'] ?? '')) !== 'TECNICO') {
        sm_responder_json(
            false,
            'La sesión del técnico no es válida.',
            ['sesion_expirada' => true, 'redirect' => '../login.php?sesion=expirada'],
            401
        );
    }

    return (int) $id;
}

function mact_obtener_tecnico_activo(PDO $conexion, int $tecnicoId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            t.id,
            t.usuario,
            TRIM(CONCAT_WS(
                ' ', t.nombre, t.apellido_paterno, t.apellido_materno
            )) AS nombre_completo,
            t.turno,
            t.especialidad,
            d.nombre AS departamento
         FROM tecnicos t
         LEFT JOIN departamentos d ON d.id = t.departamento_id
         WHERE t.id = :id
           AND t.activo = 1
         LIMIT 1"
    );
    $stmt->bindValue(':id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($fila)) {
        sm_responder_json(
            false,
            'Tu cuenta de técnico está inactiva o ya no existe.',
            ['sesion_expirada' => true, 'redirect' => '../login.php?cuenta=inactiva'],
            403
        );
    }

    return $fila;
}

function mact_nombre_tecnico(PDO $conexion, int $tecnicoId): string
{
    $stmt = $conexion->prepare(
        "SELECT TRIM(CONCAT_WS(
            ' ', nombre, apellido_paterno, apellido_materno
         ))
         FROM tecnicos
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->bindValue(':id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    $nombre = $stmt->fetchColumn();

    return is_string($nombre) && trim($nombre) !== '' ? trim($nombre) : 'Técnico';
}

function mact_id_entrada($valor, string $campo): int
{
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($id === false) {
        sm_responder_json(
            false,
            'El identificador enviado no es válido.',
            ['campo' => $campo],
            422
        );
    }

    return (int) $id;
}

function mact_id_opcional($valor): ?int
{
    if ($valor === null || $valor === '') {
        return null;
    }

    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : (int) $id;
}

function mact_id_nullable($valor): ?int
{
    if ($valor === null || $valor === '') {
        return null;
    }

    $id = (int) $valor;
    return $id > 0 ? $id : null;
}

function mact_booleano_entrada($valor, string $campo): int
{
    if ($valor === 1 || $valor === '1') {
        return 1;
    }

    if ($valor === 0 || $valor === '0') {
        return 0;
    }

    sm_responder_json(
        false,
        'Selecciona una opción válida.',
        ['campo' => $campo],
        422
    );
}

function mact_texto($valor): string
{
    if (!is_scalar($valor)) {
        return '';
    }

    $texto = trim((string) $valor);
    return preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/u', '', $texto) ?? '';
}

function mact_validar_texto(
    string $valor,
    string $campo,
    string $mensaje,
    int $minimo,
    int $maximo,
    bool $obligatorio
): void {
    $longitud = function_exists('mb_strlen')
        ? mb_strlen($valor, 'UTF-8')
        : strlen($valor);

    if (($obligatorio && $longitud < $minimo) || $longitud > $maximo) {
        sm_responder_json(false, $mensaje, ['campo' => $campo], 422);
    }
}

function mact_cancelar(PDO $conexion, string $mensaje, int $codigo = 409): void
{
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    sm_responder_json(false, $mensaje, [], $codigo);
}

function mact_bind_nullable_int(PDOStatement $stmt, string $parametro, ?int $valor): void
{
    if ($valor === null) {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue($parametro, $valor, PDO::PARAM_INT);
    } 
}

function mact_bind_nullable_text(PDOStatement $stmt, string $parametro, ?string $valor): void
{
    if ($valor === null || $valor === '') {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue($parametro, $valor, PDO::PARAM_STR);
    }
}

function mact_limitar(string $texto, int $maximo): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, $maximo, 'UTF-8');
    }

    return substr($texto, 0, $maximo);
}