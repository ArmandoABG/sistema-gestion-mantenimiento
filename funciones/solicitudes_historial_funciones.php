<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Todas las solicitudes - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| Expediente administrativo de consulta para todas las solicitudes.
|
| Incluye:
| - Búsqueda, filtros, resumen, paginación y exportación CSV.
| - Estado general, programación actual, resultado del cierre y cumplimiento.
| - Detalle de asignaciones, tiempos reales, pausas e incumplimientos.
| - Historial, evidencias y auditoría de ediciones.
| - Consulta protegida y cancelación administrativa excepcional de urgencias activas.
|
| Compatible con PHP 7.3 o superior.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';
require_once __DIR__ . '/recursos_mantenimiento_servicio.php';

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
$accion = strtoupper(shis_texto(
    $metodo === 'GET'
        ? ($_GET['accion'] ?? 'INICIAL')
        : ($_POST['accion'] ?? '')
));

try {
    if ($metodo === 'GET') {
        if ($accion === 'INICIAL' || $accion === 'LISTAR') {
            shis_cargar_listado($conexion);
        }

        if ($accion === 'DETALLE') {
            shis_cargar_detalle($conexion);
        }

        if ($accion === 'EXPORTAR' || $accion === 'EXPORTAR_EXCEL') {
            shis_exportar_excel($conexion);
        }

        if ($accion === 'EXPORTAR_PDF') {
            shis_exportar_pdf($conexion);
        }
    } else {
        sm_requerir_metodo('POST');
        sm_validar_csrf();

        if (in_array($accion, ['CANCELAR_MANTENIMIENTO', 'CANCELAR_URGENCIA'], true)) {
            shis_cancelar_mantenimiento($conexion);
        }
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[TODAS LAS SOLICITUDES][PDO] ' . $e->getMessage());

    sm_responder_json(
        false,
        'No fue posible consultar las solicitudes.',
        [],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[TODAS LAS SOLICITUDES] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error interno al consultar la información.',
        [],
        500
    );
}

/* =========================================================================
   CANCELACIÓN ADMINISTRATIVA DE MANTENIMIENTOS
   ========================================================================= */

function shis_cancelar_mantenimiento(PDO $conexion): void
{
    $adminId = shis_admin_id();
    $admin = shis_obtener_admin_activo($conexion, $adminId);
    $solicitudId = shis_id_entrada(
        $_POST['solicitud_id'] ?? null,
        'solicitud'
    );
    $motivo = shis_texto($_POST['motivo_cancelacion'] ?? '');
    $longitudMotivo = function_exists('mb_strlen')
        ? mb_strlen($motivo, 'UTF-8')
        : strlen($motivo);

    if ($longitudMotivo < 15 || $longitudMotivo > 500) {
        sm_responder_json(
            false,
            'Explica el motivo de cancelación con entre 15 y 500 caracteres.',
            ['campo' => 'motivo_cancelacion'],
            422
        );
    }

    $conexion->beginTransaction();

    $stmtSolicitud = $conexion->prepare(
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
    $stmtSolicitud->bindValue(':id', $solicitudId, PDO::PARAM_INT);
    $stmtSolicitud->execute();
    $solicitud = $stmtSolicitud->fetch(PDO::FETCH_ASSOC);

    if (!is_array($solicitud)) {
        shis_cancelar_transaccion($conexion, 'La solicitud no existe.', 404);
    }

    $tipoSolicitud = (string) ($solicitud['tipo_solicitud'] ?? '');
    $tiposPermitidos = [
        'CORRECTIVO_PROGRAMABLE',
        'MODIFICACION_MEJORA',
        'CORRECTIVO_URGENTE',
        'RUTINARIO',
    ];

    if (!in_array($tipoSolicitud, $tiposPermitidos, true)) {
        shis_cancelar_transaccion(
            $conexion,
            'Este tipo de mantenimiento no admite cancelación administrativa.',
            422
        );
    }

    $estadoAnterior = (string) $solicitud['estado'];
    $estadosCancelables = ['APROBADO','AGENDADO','EN_PROCESO','PAUSADO','ATRASADO'];
    if (!in_array($estadoAnterior, $estadosCancelables, true)) {
        shis_cancelar_transaccion(
            $conexion,
            'El mantenimiento ya no se encuentra en un estado que permita cancelarlo.',
            409
        );
    }

    $stmtCierre = $conexion->prepare(
        "SELECT id
         FROM cierres_mantenimiento
         WHERE solicitud_id = :solicitud_id
         LIMIT 1
         FOR UPDATE"
    );
    $stmtCierre->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtCierre->execute();
    if ($stmtCierre->fetch(PDO::FETCH_ASSOC)) {
        shis_cancelar_transaccion(
            $conexion,
            'El mantenimiento ya tiene un cierre técnico y no puede cancelarse.',
            409
        );
    }

    $stmtProgramacion = $conexion->prepare(
        "SELECT *
         FROM programaciones_mantenimiento
         WHERE solicitud_id = :solicitud_id
         ORDER BY es_actual DESC, id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmtProgramacion->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtProgramacion->execute();
    $programacion = $stmtProgramacion->fetch(PDO::FETCH_ASSOC);
    $programacionId = is_array($programacion) ? (int) ($programacion['id'] ?? 0) : 0;

    $stmtParticipaciones = $conexion->prepare(
        "SELECT *
         FROM solicitud_tecnicos
         WHERE solicitud_id = :solicitud_id
         ORDER BY id
         FOR UPDATE"
    );
    $stmtParticipaciones->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtParticipaciones->execute();
    $participaciones = $stmtParticipaciones->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmtEjecuciones = $conexion->prepare(
        "SELECT *
         FROM ejecuciones_mantenimiento
         WHERE solicitud_id = :solicitud_id
         ORDER BY id
         FOR UPDATE"
    );
    $stmtEjecuciones->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtEjecuciones->execute();
    $ejecuciones = $stmtEjecuciones->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($ejecuciones as $ejecucion) {
        if ((string) $ejecucion['estado'] === 'TERMINADA') {
            shis_cancelar_transaccion(
                $conexion,
                'Existe una ejecución terminada. Revisa el expediente antes de cancelar.',
                409
            );
        }
    }

    $participacionesCanceladas = array_values(array_filter(
        $participaciones,
        static function (array $participacion): bool {
            return (int) ($participacion['activo'] ?? 0) === 1
                && in_array(
                    (string) ($participacion['estado'] ?? ''),
                    ['ASIGNADO','ACEPTADO','EN_PROCESO','PAUSADO'],
                    true
                );
        }
    ));

    $ahora = date('Y-m-d H:i:s');
    $nombreTipo = shis_etiqueta_tipo($tipoSolicitud);
    $detalleEdicion = shis_limitar(
        $nombreTipo . ' cancelado administrativamente. Motivo: ' . $motivo,
        500
    );

    /* Cierra pausas abiertas de este mantenimiento y conserva sus tiempos. */
    $stmtPausas = $conexion->prepare(
        "SELECT pe.id, pe.ejecucion_id, pe.fecha_hora_inicio
         FROM pausas_ejecucion pe
         INNER JOIN ejecuciones_mantenimiento em ON em.id = pe.ejecucion_id
         WHERE em.solicitud_id = :solicitud_id
           AND pe.pausa_abierta_token = 1
         ORDER BY pe.id
         FOR UPDATE"
    );
    $stmtPausas->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtPausas->execute();
    $pausasAbiertas = $stmtPausas->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $segundosPausaPorEjecucion = [];

    $stmtCerrarPausa = $conexion->prepare(
        "UPDATE pausas_ejecucion
         SET fecha_hora_fin = :ahora,
             duracion_segundos = GREATEST(0, TIMESTAMPDIFF(SECOND, fecha_hora_inicio, :ahora_duracion)),
             pausa_abierta_token = NULL,
             observaciones = CONCAT_WS(' · ', NULLIF(observaciones, ''), :observaciones)
         WHERE id = :id
           AND pausa_abierta_token = 1"
    );

    foreach ($pausasAbiertas as $pausa) {
        $inicio = strtotime((string) $pausa['fecha_hora_inicio']);
        $segundos = $inicio === false ? 0 : max(0, strtotime($ahora) - $inicio);
        $ejecucionId = (int) $pausa['ejecucion_id'];
        $segundosPausaPorEjecucion[$ejecucionId] =
            ($segundosPausaPorEjecucion[$ejecucionId] ?? 0) + $segundos;

        $stmtCerrarPausa->bindValue(':ahora', $ahora, PDO::PARAM_STR);
        $stmtCerrarPausa->bindValue(':ahora_duracion', $ahora, PDO::PARAM_STR);
        $stmtCerrarPausa->bindValue(
            ':observaciones',
            'Pausa cerrada por cancelación administrativa del mantenimiento.',
            PDO::PARAM_STR
        );
        $stmtCerrarPausa->bindValue(':id', (int) $pausa['id'], PDO::PARAM_INT);
        $stmtCerrarPausa->execute();
    }

    $ejecucionesCanceladas = 0;
    $stmtCancelarEjecucion = $conexion->prepare(
        "UPDATE ejecuciones_mantenimiento
         SET total_segundos_activos = total_segundos_activos +
                CASE
                    WHEN estado = 'EN_PROCESO' THEN GREATEST(
                        0,
                        TIMESTAMPDIFF(
                            SECOND,
                            COALESCE(fecha_ultima_reanudacion, fecha_hora_inicio, :ahora_base),
                            :ahora_activos
                        )
                    )
                    ELSE 0
                END,
             total_segundos_pausa = total_segundos_pausa + :segundos_pausa,
             estado = 'CANCELADA',
             fecha_hora_fin = CASE WHEN fecha_hora_inicio IS NULL THEN NULL ELSE :ahora_fin END,
             fecha_hora_fin_original = CASE
                 WHEN fecha_hora_inicio IS NULL THEN fecha_hora_fin_original
                 ELSE COALESCE(fecha_hora_fin_original, :ahora_original)
             END,
             fecha_ultima_reanudacion = NULL,
             en_proceso_token = NULL,
             editado_por_admin_id = :admin_id,
             motivo_edicion_tiempos = :motivo,
             fecha_actualizacion = NOW()
         WHERE id = :id
           AND estado IN ('PENDIENTE','EN_PROCESO','PAUSADA')"
    );

    foreach ($ejecuciones as $ejecucion) {
        if (!in_array((string) $ejecucion['estado'], ['PENDIENTE','EN_PROCESO','PAUSADA'], true)) {
            continue;
        }

        $ejecucionId = (int) $ejecucion['id'];
        $stmtCancelarEjecucion->bindValue(':ahora_base', $ahora, PDO::PARAM_STR);
        $stmtCancelarEjecucion->bindValue(':ahora_activos', $ahora, PDO::PARAM_STR);
        $stmtCancelarEjecucion->bindValue(
            ':segundos_pausa',
            (int) ($segundosPausaPorEjecucion[$ejecucionId] ?? 0),
            PDO::PARAM_INT
        );
        $stmtCancelarEjecucion->bindValue(':ahora_fin', $ahora, PDO::PARAM_STR);
        $stmtCancelarEjecucion->bindValue(':ahora_original', $ahora, PDO::PARAM_STR);
        $stmtCancelarEjecucion->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmtCancelarEjecucion->bindValue(':motivo', $detalleEdicion, PDO::PARAM_STR);
        $stmtCancelarEjecucion->bindValue(':id', $ejecucionId, PDO::PARAM_INT);
        $stmtCancelarEjecucion->execute();
        $ejecucionesCanceladas += $stmtCancelarEjecucion->rowCount();
    }

    $stmtRetirar = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET estado = 'RETIRADO',
             fecha_retiro = COALESCE(fecha_retiro, :ahora_retiro),
             resultado_cumplimiento = 'NO_APLICA',
             fecha_resultado = :ahora_resultado,
             activo = 0,
             activo_token = NULL,
             fecha_actualizacion = NOW()
         WHERE solicitud_id = :solicitud_id
           AND activo = 1
           AND estado IN ('ASIGNADO','ACEPTADO','EN_PROCESO','PAUSADO')"
    );
    $stmtRetirar->bindValue(':ahora_retiro', $ahora, PDO::PARAM_STR);
    $stmtRetirar->bindValue(':ahora_resultado', $ahora, PDO::PARAM_STR);
    $stmtRetirar->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtRetirar->execute();
    $participacionesRetiradas = $stmtRetirar->rowCount();

    $programacionCancelada = false;
    if ($programacionId > 0) {
        $stmtCancelarProgramacion = $conexion->prepare(
            "UPDATE programaciones_mantenimiento
             SET estado = 'CANCELADA',
                 es_actual = 0,
                 vigente_token = NULL,
                 motivo_cancelacion = :motivo,
                 fecha_actualizacion = NOW()
             WHERE id = :id
               AND estado IN ('PROGRAMADA','VENCIDA')"
        );
        $stmtCancelarProgramacion->bindValue(':motivo', $motivo, PDO::PARAM_STR);
        $stmtCancelarProgramacion->bindValue(':id', $programacionId, PDO::PARAM_INT);
        $stmtCancelarProgramacion->execute();
        $programacionCancelada = $stmtCancelarProgramacion->rowCount() === 1;
    }

    /* Una actividad cancelada deja de generar incumplimientos exigibles. */
    $stmtIncumplimientos = $conexion->prepare(
        "UPDATE incumplimientos_mantenimiento
         SET estado = 'JUSTIFICADO',
             justificacion = :justificacion,
             justificado_por_admin_id = :admin_id,
             fecha_resolucion = NOW()
         WHERE solicitud_id = :solicitud_id
           AND estado = 'PENDIENTE'"
    );
    $stmtIncumplimientos->bindValue(
        ':justificacion',
        shis_limitar('Mantenimiento cancelado por administración. Motivo: ' . $motivo, 1000),
        PDO::PARAM_STR
    );
    $stmtIncumplimientos->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmtIncumplimientos->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtIncumplimientos->execute();
    $incumplimientosJustificados = $stmtIncumplimientos->rowCount();

    $stmtActualizarSolicitud = $conexion->prepare(
        "UPDATE solicitudes
         SET estado = 'CANCELADO',
             ultima_edicion_admin_id = :admin_id,
             motivo_ultima_edicion = :motivo,
             version_registro = version_registro + 1,
             fecha_actualizacion = NOW()
         WHERE id = :id
           AND activo = 1
           AND estado IN ('APROBADO','AGENDADO','EN_PROCESO','PAUSADO','ATRASADO')"
    );
    $stmtActualizarSolicitud->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmtActualizarSolicitud->bindValue(':motivo', $motivo, PDO::PARAM_STR);
    $stmtActualizarSolicitud->bindValue(':id', $solicitudId, PDO::PARAM_INT);
    $stmtActualizarSolicitud->execute();

    if ($stmtActualizarSolicitud->rowCount() !== 1) {
        shis_cancelar_transaccion(
            $conexion,
            'El mantenimiento cambió mientras realizabas la cancelación.',
            409
        );
    }

    if ($tipoSolicitud === 'RUTINARIO') {
        $stmtAlertaRutina = $conexion->prepare(
            "UPDATE rutina_alertas
             SET estado = 'CANCELADA',
                 atendida_por_admin_id = :admin_id,
                 motivo_omision = :motivo,
                 fecha_atencion = :fecha_atencion
             WHERE solicitud_id = :solicitud_id
               AND estado IN ('PENDIENTE_PROGRAMAR','PROGRAMADA')"
        );
        $stmtAlertaRutina->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmtAlertaRutina->bindValue(':motivo', $motivo, PDO::PARAM_STR);
        $stmtAlertaRutina->bindValue(':fecha_atencion', $ahora, PDO::PARAM_STR);
        $stmtAlertaRutina->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmtAlertaRutina->execute();
    }

    $tipoDescripcion = function_exists('mb_strtolower')
        ? mb_strtolower($nombreTipo, 'UTF-8')
        : strtolower($nombreTipo);

    $descripcion = 'El administrador ' . (string) $admin['nombre']
        . ' canceló ' . $tipoDescripcion
        . ' ' . (string) $solicitud['folio']
        . ($ejecucionesCanceladas > 0
            ? ' mientras se encontraba en ejecución.'
            : ' antes de que iniciara la ejecución.')
        . ' Motivo: ' . $motivo;

    $stmtHistorial = $conexion->prepare(
        "INSERT INTO historial_solicitudes (
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
         ) VALUES (
            :solicitud_id,
            NULL,
            :programacion_id,
            'CANCELADA',
            :estado_anterior,
            'CANCELADO',
            'ADMIN',
            :actor_id,
            :descripcion,
            :fecha_evento
         )"
    );
    $stmtHistorial->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    if ($programacionId > 0) {
        $stmtHistorial->bindValue(':programacion_id', $programacionId, PDO::PARAM_INT);
    } else {
        $stmtHistorial->bindValue(':programacion_id', null, PDO::PARAM_NULL);
    }
    $stmtHistorial->bindValue(':estado_anterior', $estadoAnterior, PDO::PARAM_STR);
    $stmtHistorial->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmtHistorial->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmtHistorial->bindValue(':fecha_evento', $ahora, PDO::PARAM_STR);
    $stmtHistorial->execute();

    /* Conserva una entrada individual para que cada técnico vea por qué se retiró su actividad. */
    $stmtHistorialTecnico = $conexion->prepare(
        "INSERT INTO historial_solicitudes (
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
         ) VALUES (
            :solicitud_id,
            :solicitud_tecnico_id,
            :programacion_id,
            'TECNICO_RETIRADO',
            :estado_anterior,
            'RETIRADO',
            'ADMIN',
            :actor_id,
            :descripcion,
            :fecha_evento
         )"
    );

    foreach ($participacionesCanceladas as $participacion) {
        $descripcionTecnico = 'La actividad del técnico fue retirada porque el mantenimiento '
            . (string) $solicitud['folio']
            . ' fue cancelado por administración. Motivo: ' . $motivo;
        $stmtHistorialTecnico->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmtHistorialTecnico->bindValue(
            ':solicitud_tecnico_id',
            (int) $participacion['id'],
            PDO::PARAM_INT
        );
        $programacionParticipacion = (int) ($participacion['programacion_id'] ?? 0);
        if ($programacionParticipacion > 0) {
            $stmtHistorialTecnico->bindValue(
                ':programacion_id',
                $programacionParticipacion,
                PDO::PARAM_INT
            );
        } elseif ($programacionId > 0) {
            $stmtHistorialTecnico->bindValue(':programacion_id', $programacionId, PDO::PARAM_INT);
        } else {
            $stmtHistorialTecnico->bindValue(':programacion_id', null, PDO::PARAM_NULL);
        }
        $stmtHistorialTecnico->bindValue(
            ':estado_anterior',
            (string) ($participacion['estado'] ?? 'ASIGNADO'),
            PDO::PARAM_STR
        );
        $stmtHistorialTecnico->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
        $stmtHistorialTecnico->bindValue(':descripcion', $descripcionTecnico, PDO::PARAM_STR);
        $stmtHistorialTecnico->bindValue(':fecha_evento', $ahora, PDO::PARAM_STR);
        $stmtHistorialTecnico->execute();
    }

    $stmtMovimiento = $conexion->prepare(
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
            'ADMIN',
            :usuario_id,
            'CANCELAR_MANTENIMIENTO_ACTIVO',
            'Todas las solicitudes',
            :descripcion,
            'solicitudes',
            :registro_id,
            :ip,
            :agente
         )"
    );
    $stmtMovimiento->bindValue(':usuario_id', $adminId, PDO::PARAM_INT);
    $stmtMovimiento->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmtMovimiento->bindValue(':registro_id', $solicitudId, PDO::PARAM_INT);
    $stmtMovimiento->bindValue(
        ':ip',
        shis_limitar((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 60),
        PDO::PARAM_STR
    );
    $stmtMovimiento->bindValue(
        ':agente',
        shis_limitar((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 255),
        PDO::PARAM_STR
    );
    $stmtMovimiento->execute();

    $stmtAuditoria = $conexion->prepare(
        "INSERT INTO auditoria_ediciones (
            tabla_afectada,
            registro_id,
            solicitud_id,
            actor_tipo,
            actor_id,
            accion,
            motivo,
            datos_anteriores,
            datos_nuevos,
            ip_address,
            user_agent,
            fecha_evento
         ) VALUES (
            'solicitudes',
            :registro_id,
            :solicitud_id,
            'ADMIN',
            :actor_id,
            'UPDATE',
            :motivo,
            :anteriores,
            :nuevos,
            :ip,
            :agente,
            :fecha_evento
         )"
    );
    $stmtAuditoria->bindValue(':registro_id', $solicitudId, PDO::PARAM_INT);
    $stmtAuditoria->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtAuditoria->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmtAuditoria->bindValue(':motivo', $motivo, PDO::PARAM_STR);
    $stmtAuditoria->bindValue(
        ':anteriores',
        json_encode(
            ['estado' => $estadoAnterior, 'tipo_solicitud' => $tipoSolicitud],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ),
        PDO::PARAM_STR
    );
    $stmtAuditoria->bindValue(
        ':nuevos',
        json_encode(
            [
                'estado' => 'CANCELADO',
                'ejecuciones_canceladas' => $ejecucionesCanceladas,
                'participaciones_retiradas' => $participacionesRetiradas,
                'programacion_cancelada' => $programacionCancelada,
                'incumplimientos_justificados' => $incumplimientosJustificados,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ),
        PDO::PARAM_STR
    );
    $stmtAuditoria->bindValue(
        ':ip',
        shis_limitar((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 45),
        PDO::PARAM_STR
    );
    $stmtAuditoria->bindValue(
        ':agente',
        shis_limitar((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 500),
        PDO::PARAM_STR
    );
    $stmtAuditoria->bindValue(':fecha_evento', $ahora, PDO::PARAM_STR);
    $stmtAuditoria->execute();

    /* Reemplaza avisos de trabajo por una notificación de cancelación. */
    $stmtLeidas = $conexion->prepare(
        "UPDATE notificaciones
         SET leida = 1,
             fecha_lectura = COALESCE(fecha_lectura, :ahora)
         WHERE solicitud_id = :solicitud_id
           AND tipo_usuario = 'TECNICO'
           AND leida = 0"
    );
    $stmtLeidas->bindValue(':ahora', $ahora, PDO::PARAM_STR);
    $stmtLeidas->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtLeidas->execute();

    $stmtNotificarTecnico = $conexion->prepare(
        "INSERT INTO notificaciones (
            tipo_usuario,
            usuario_id,
            solicitud_id,
            titulo,
            mensaje,
            tipo,
            leida,
            fecha_creacion
         ) VALUES (
            'TECNICO',
            :usuario_id,
            :solicitud_id,
            'Mantenimiento cancelado por administración',
            :mensaje,
            'DANGER',
            0,
            :fecha_creacion
         )"
    );
    $tecnicosNotificados = [];
    foreach ($participacionesCanceladas as $participacion) {
        $tecnicoId = (int) ($participacion['tecnico_id'] ?? 0);
        if ($tecnicoId < 1 || isset($tecnicosNotificados[$tecnicoId])) {
            continue;
        }
        $tecnicosNotificados[$tecnicoId] = true;
        $stmtNotificarTecnico->bindValue(':usuario_id', $tecnicoId, PDO::PARAM_INT);
        $stmtNotificarTecnico->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmtNotificarTecnico->bindValue(
            ':mensaje',
            $nombreTipo . ' ' . (string) $solicitud['folio']
                . ' fue cancelado por administración. '
                . ($ejecucionesCanceladas > 0 ? 'Toda actividad debe detenerse. ' : '')
                . 'Motivo: ' . $motivo,
            PDO::PARAM_STR
        );
        $stmtNotificarTecnico->bindValue(':fecha_creacion', $ahora, PDO::PARAM_STR);
        $stmtNotificarTecnico->execute();
    }

    shis_notificar_solicitante_cancelacion(
        $conexion,
        $solicitud,
        $solicitudId,
        $motivo,
        $ahora
    );

    $reanudarManualmente = [];
    if ($tipoSolicitud === 'CORRECTIVO_URGENTE') {
        $reanudarManualmente = shis_notificar_trabajos_reanudables(
            $conexion,
            $solicitudId,
            (string) $solicitud['folio'],
            $ahora
        );
    }

    $conexion->commit();

    sm_responder_json(
        true,
        'El mantenimiento se canceló y las actividades relacionadas fueron detenidas.',
        [
            'solicitud_id' => $solicitudId,
            'tipo_solicitud' => $tipoSolicitud,
            'estado' => 'CANCELADO',
            'ejecuciones_canceladas' => $ejecucionesCanceladas,
            'participaciones_retiradas' => $participacionesRetiradas,
            'incumplimientos_justificados' => $incumplimientosJustificados,
            'mantenimientos_para_reanudar' => $reanudarManualmente,
        ]
    );
}

function shis_notificar_solicitante_cancelacion(
    PDO $conexion,
    array $solicitud,
    int $solicitudId,
    string $motivo,
    string $ahora
): void {
    $tipoUsuario = null;
    $usuarioId = 0;

    if ((int) ($solicitud['solicitante_id'] ?? 0) > 0) {
        $tipoUsuario = 'SOLICITANTE';
        $usuarioId = (int) $solicitud['solicitante_id'];
    } elseif ((int) ($solicitud['administrador_solicitante_id'] ?? 0) > 0) {
        $tipoUsuario = 'ADMIN';
        $usuarioId = (int) $solicitud['administrador_solicitante_id'];
    }

    if ($tipoUsuario === null || $usuarioId < 1) {
        return;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO notificaciones (
            tipo_usuario,
            usuario_id,
            solicitud_id,
            titulo,
            mensaje,
            tipo,
            leida,
            fecha_creacion
         ) VALUES (
            :tipo_usuario,
            :usuario_id,
            :solicitud_id,
            'Mantenimiento cancelado',
            :mensaje,
            'WARNING',
            0,
            :fecha_creacion
         )"
    );
    $stmt->bindValue(':tipo_usuario', $tipoUsuario, PDO::PARAM_STR);
    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(
        ':mensaje',
        shis_etiqueta_tipo((string) ($solicitud['tipo_solicitud'] ?? '')) . ' ' . (string) $solicitud['folio']
            . ' fue cancelado por administración. Motivo: ' . $motivo,
        PDO::PARAM_STR
    );
    $stmt->bindValue(':fecha_creacion', $ahora, PDO::PARAM_STR);
    $stmt->execute();
}

function shis_notificar_trabajos_reanudables(
    PDO $conexion,
    int $urgenciaId,
    string $folioUrgencia,
    string $ahora
): array {
    $stmt = $conexion->prepare(
        "SELECT DISTINCT
            em.tecnico_id,
            em.solicitud_id,
            em.id AS ejecucion_id,
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
         INNER JOIN equipos e ON e.id = s.equipo_id
         WHERE pe.solicitud_urgente_id = :urgencia_id
           AND pe.motivo = 'URGENCIA'
           AND pe.pausa_abierta_token = 1
         ORDER BY em.id"
    );
    $stmt->bindValue(':urgencia_id', $urgenciaId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmtNotificar = $conexion->prepare(
        "INSERT INTO notificaciones (
            tipo_usuario,
            usuario_id,
            solicitud_id,
            ejecucion_id,
            titulo,
            mensaje,
            tipo,
            leida,
            fecha_creacion
         ) VALUES (
            'TECNICO',
            :usuario_id,
            :solicitud_id,
            :ejecucion_id,
            'Mantenimiento listo para reanudar',
            :mensaje,
            'WARNING',
            0,
            :fecha_creacion
         )"
    );

    foreach ($filas as $fila) {
        $stmtNotificar->bindValue(':usuario_id', (int) $fila['tecnico_id'], PDO::PARAM_INT);
        $stmtNotificar->bindValue(':solicitud_id', (int) $fila['solicitud_id'], PDO::PARAM_INT);
        $stmtNotificar->bindValue(':ejecucion_id', (int) $fila['ejecucion_id'], PDO::PARAM_INT);
        $stmtNotificar->bindValue(
            ':mensaje',
            'La urgencia ' . $folioUrgencia
                . ' fue cancelada. Tu mantenimiento ' . (string) $fila['folio']
                . ' permanece pausado y ya puede reanudarse manualmente.',
            PDO::PARAM_STR
        );
        $stmtNotificar->bindValue(':fecha_creacion', $ahora, PDO::PARAM_STR);
        $stmtNotificar->execute();
    }

    return array_map(
        static function (array $fila): array {
            return [
                'solicitud_id' => (int) $fila['solicitud_id'],
                'ejecucion_id' => (int) $fila['ejecucion_id'],
                'tecnico_id' => (int) $fila['tecnico_id'],
                'folio' => (string) $fila['folio'],
                'equipo' => (string) $fila['nombre_equipo'],
            ];
        },
        $filas
    );
}

function shis_cancelar_transaccion(
    PDO $conexion,
    string $mensaje,
    int $codigo
): void {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    sm_responder_json(false, $mensaje, [], $codigo);
}

/* =========================================================================
   LISTADO PRINCIPAL
   ========================================================================= */

function shis_cargar_listado(PDO $conexion): void
{
    $adminId = shis_admin_id();
    $perfil = shis_obtener_admin_activo($conexion, $adminId);
    $filtros = shis_leer_filtros();
    $consulta = shis_construir_condiciones($filtros);

    $total = shis_contar_resultados(
        $conexion,
        $consulta['where'],
        $consulta['parametros']
    );

    $porPagina = (int) $filtros['por_pagina'];
    $totalPaginas = max(1, (int) ceil($total / max(1, $porPagina)));
    $pagina = min((int) $filtros['pagina'], $totalPaginas);
    $offset = max(0, ($pagina - 1) * $porPagina);
    $filtros['pagina'] = $pagina;

    $resumen = shis_obtener_resumen(
        $conexion,
        $consulta['where'],
        $consulta['parametros']
    );

    $registros = shis_obtener_registros(
        $conexion,
        $consulta['where'],
        $consulta['parametros'],
        (string) $filtros['orden'],
        $porPagina,
        $offset
    );

    sm_responder_json(
        true,
        'Solicitudes cargadas correctamente.',
        [
            'perfil' => $perfil,
            'resumen' => $resumen,
            'registros' => $registros,
            'filtros' => $filtros,
            'catalogos' => shis_catalogos($conexion),
            'paginacion' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total_registros' => $total,
                'total_paginas' => $totalPaginas,
                'desde' => $total === 0 ? 0 : $offset + 1,
                'hasta' => min($total, $offset + count($registros)),
            ],
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function shis_base_desde(): string
{
    return "FROM solicitudes s
            INNER JOIN departamentos d
                    ON d.id = s.departamento_id
            INNER JOIN areas a
                    ON a.id = s.area_id
            INNER JOIN procesos p
                    ON p.id = s.proceso_id
            INNER JOIN equipos e
                    ON e.id = s.equipo_id
            LEFT JOIN tipos_falla tf
                   ON tf.id = s.tipo_falla_id
            LEFT JOIN causas_averia ca
                   ON ca.id = s.causa_averia_id
            LEFT JOIN solicitantes sol
                   ON sol.id = s.solicitante_id
            LEFT JOIN administradores adm_sol
                   ON adm_sol.id = s.administrador_solicitante_id
            LEFT JOIN programaciones_mantenimiento pm
                   ON pm.solicitud_id = s.id
                  AND pm.es_actual = 1
            LEFT JOIN cierres_mantenimiento cm
                   ON cm.solicitud_id = s.id";
}

function shis_contar_resultados(
    PDO $conexion,
    string $where,
    array $parametros
): int {
    $sql = 'SELECT COUNT(DISTINCT s.id) '
        . shis_base_desde()
        . ' ' . $where;

    $stmt = $conexion->prepare($sql);
    shis_vincular_parametros($stmt, $parametros);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function shis_obtener_resumen(
    PDO $conexion,
    string $where,
    array $parametros
): array {
    $sql = "SELECT
                COUNT(DISTINCT s.id) AS total,
                COUNT(DISTINCT CASE WHEN s.estado = 'PENDIENTE' THEN s.id END) AS pendientes,
                COUNT(DISTINCT CASE WHEN s.estado IN ('APROBADO','AGENDADO') THEN s.id END) AS planeadas,
                COUNT(DISTINCT CASE WHEN s.estado IN ('EN_PROCESO','PAUSADO') THEN s.id END) AS activas,
                COUNT(DISTINCT CASE WHEN s.estado = 'ATRASADO' THEN s.id END) AS atrasadas,
                COUNT(DISTINCT CASE WHEN s.estado = 'TERMINADO' THEN s.id END) AS terminadas,
                COUNT(DISTINCT CASE WHEN s.estado IN ('RECHAZADO','CANCELADO') THEN s.id END) AS cerradas_sin_ejecucion,
                COUNT(DISTINCT CASE WHEN cm.trabajo_quedo IN ('PARCIAL','PROVISIONAL') THEN s.id END) AS parciales,
                COUNT(DISTINCT CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM solicitud_tecnicos st_r
                        WHERE st_r.solicitud_id = s.id
                          AND st_r.resultado_cumplimiento = 'TARDE'
                    ) THEN s.id
                END) AS con_retraso,
                COUNT(DISTINCT CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM solicitud_tecnicos st_nr
                        WHERE st_nr.solicitud_id = s.id
                          AND st_nr.resultado_cumplimiento = 'NO_REALIZADO'
                    ) THEN s.id
                END) AS no_realizadas,
                COALESCE(SUM(
                    CASE
                        WHEN s.estado = 'TERMINADO' THEN (
                            SELECT COALESCE(SUM(em_r.total_segundos_activos), 0)
                            FROM ejecuciones_mantenimiento em_r
                            WHERE em_r.solicitud_id = s.id
                        )
                        ELSE 0
                    END
                ), 0) AS segundos_activos_terminados
            " . shis_base_desde() . "
            {$where}";

    $stmt = $conexion->prepare($sql);
    shis_vincular_parametros($stmt, $parametros);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($fila)) {
        $fila = [];
    }

    $total = (int) ($fila['total'] ?? 0);
    $terminadas = (int) ($fila['terminadas'] ?? 0);

    return [
        'total' => $total,
        'pendientes' => (int) ($fila['pendientes'] ?? 0),
        'planeadas' => (int) ($fila['planeadas'] ?? 0),
        'activas' => (int) ($fila['activas'] ?? 0),
        'atrasadas' => (int) ($fila['atrasadas'] ?? 0),
        'terminadas' => $terminadas,
        'cerradas_sin_ejecucion' => (int) ($fila['cerradas_sin_ejecucion'] ?? 0),
        'parciales' => (int) ($fila['parciales'] ?? 0),
        'con_retraso' => (int) ($fila['con_retraso'] ?? 0),
        'no_realizadas' => (int) ($fila['no_realizadas'] ?? 0),
        'segundos_activos_terminados' => (int) ($fila['segundos_activos_terminados'] ?? 0),
        'porcentaje_terminadas' => $total > 0
            ? round(($terminadas * 100) / $total, 1)
            : 0.0,
    ];
}

function shis_obtener_registros(
    PDO $conexion,
    string $where,
    array $parametros,
    string $orden,
    int $limite,
    int $offset
): array {
    $ordenSql = shis_orden_sql($orden);

    $sql = "SELECT
                s.id,
                s.folio,
                s.tipo_solicitud,
                s.estado,
                s.prioridad,
                s.fecha_solicitud,
                s.hora_solicitud,
                s.fecha_sugerida,
                s.descripcion_solicitud,
                s.trabajo_peligroso,
                s.nivel_riesgo,
                s.requiere_paro_equipo,
                s.activo,
                s.fecha_actualizacion,
                d.nombre AS departamento,
                a.nombre AS area,
                p.nombre AS proceso,
                e.codigo_equipo,
                e.nombre_equipo,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', sol.nombre, sol.apellido_paterno, sol.apellido_materno)), ''),
                    NULLIF(TRIM(CONCAT_WS(' ', adm_sol.nombre, adm_sol.apellido_paterno, adm_sol.apellido_materno)), ''),
                    'Sin solicitante'
                ) AS solicitante,
                pm.id AS programacion_id,
                pm.fecha_programada,
                pm.fecha_limite,
                pm.estado AS estado_programacion,
                cm.id AS cierre_id,
                cm.fecha_hora_cierre,
                cm.trabajo_quedo,
                cm.descripcion_trabajo_realizado,
                (
                    SELECT COUNT(*)
                    FROM solicitud_tecnicos st_total
                    WHERE st_total.solicitud_id = s.id
                      AND st_total.activo = 1
                      AND st_total.estado <> 'RETIRADO'
                ) AS total_tecnicos,
                (
                    SELECT COUNT(*)
                    FROM solicitud_tecnicos st_fin
                    WHERE st_fin.solicitud_id = s.id
                      AND st_fin.estado = 'TERMINADO'
                ) AS tecnicos_terminaron,
                (
                    SELECT COUNT(*)
                    FROM solicitud_tecnicos st_np
                    WHERE st_np.solicitud_id = s.id
                      AND st_np.estado = 'NO_PARTICIPO'
                ) AS tecnicos_no_participaron,
                (
                    SELECT COUNT(*)
                    FROM solicitud_tecnicos st_tarde
                    WHERE st_tarde.solicitud_id = s.id
                      AND st_tarde.resultado_cumplimiento = 'TARDE'
                ) AS tecnicos_tarde,
                (
                    SELECT COUNT(*)
                    FROM solicitud_tecnicos st_nr
                    WHERE st_nr.solicitud_id = s.id
                      AND st_nr.resultado_cumplimiento = 'NO_REALIZADO'
                ) AS tecnicos_no_realizado,
                (
                    SELECT COUNT(*)
                    FROM solicitud_tecnicos st_pend
                    WHERE st_pend.solicitud_id = s.id
                      AND st_pend.resultado_cumplimiento = 'PENDIENTE'
                      AND st_pend.estado NOT IN ('RETIRADO','NO_PARTICIPO')
                ) AS cumplimiento_pendiente,
                (
                    SELECT MIN(em_ini.fecha_hora_inicio)
                    FROM ejecuciones_mantenimiento em_ini
                    WHERE em_ini.solicitud_id = s.id
                ) AS primer_inicio,
                (
                    SELECT MAX(em_fin.fecha_hora_fin)
                    FROM ejecuciones_mantenimiento em_fin
                    WHERE em_fin.solicitud_id = s.id
                ) AS ultima_finalizacion,
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
                CASE
                    WHEN pm.fecha_limite IS NULL THEN 0
                    WHEN cm.fecha_hora_cierre IS NOT NULL
                         AND cm.fecha_hora_cierre > CONCAT(pm.fecha_limite, ' 23:59:59')
                    THEN TIMESTAMPDIFF(
                        SECOND,
                        CONCAT(pm.fecha_limite, ' 23:59:59'),
                        cm.fecha_hora_cierre
                    )
                    WHEN cm.fecha_hora_cierre IS NULL
                         AND NOW() > CONCAT(pm.fecha_limite, ' 23:59:59')
                         AND s.estado NOT IN ('RECHAZADO','CANCELADO','TERMINADO')
                    THEN TIMESTAMPDIFF(
                        SECOND,
                        CONCAT(pm.fecha_limite, ' 23:59:59'),
                        NOW()
                    )
                    ELSE 0
                END AS segundos_fuera_limite,
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM solicitud_tecnicos st_c1
                        WHERE st_c1.solicitud_id = s.id
                          AND st_c1.resultado_cumplimiento = 'NO_REALIZADO'
                    ) THEN 'NO_REALIZADO'
                    WHEN EXISTS (
                        SELECT 1 FROM solicitud_tecnicos st_c2
                        WHERE st_c2.solicitud_id = s.id
                          AND st_c2.resultado_cumplimiento = 'TARDE'
                    ) THEN 'TARDE'
                    WHEN EXISTS (
                        SELECT 1 FROM solicitud_tecnicos st_c3
                        WHERE st_c3.solicitud_id = s.id
                          AND st_c3.resultado_cumplimiento = 'PENDIENTE'
                          AND st_c3.estado NOT IN ('RETIRADO','NO_PARTICIPO')
                    ) THEN 'PENDIENTE'
                    WHEN EXISTS (
                        SELECT 1 FROM solicitud_tecnicos st_c4
                        WHERE st_c4.solicitud_id = s.id
                          AND st_c4.resultado_cumplimiento = 'A_TIEMPO'
                    ) THEN 'A_TIEMPO'
                    WHEN EXISTS (
                        SELECT 1 FROM solicitud_tecnicos st_c5
                        WHERE st_c5.solicitud_id = s.id
                    ) THEN 'NO_APLICA'
                    ELSE 'SIN_ASIGNACION'
                END AS cumplimiento_general
            " . shis_base_desde() . "
            {$where}
            ORDER BY {$ordenSql}
            LIMIT :limite OFFSET :offset";

    $stmt = $conexion->prepare($sql);
    shis_vincular_parametros($stmt, $parametros);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($filas as &$fila) {
        foreach (
            [
                'id', 'activo', 'programacion_id', 'cierre_id', 'total_tecnicos',
                'tecnicos_terminaron', 'tecnicos_no_participaron', 'tecnicos_tarde',
                'tecnicos_no_realizado', 'cumplimiento_pendiente',
                'total_segundos_activos', 'total_segundos_pausa', 'segundos_fuera_limite',
            ] as $campo
        ) {
            $fila[$campo] = (int) ($fila[$campo] ?? 0);
        }
    }
    unset($fila);

    return $filas;
}

function shis_orden_sql(string $orden): string
{
    $opciones = [
        'RECIENTES' => 's.fecha_solicitud DESC, s.hora_solicitud DESC, s.id DESC',
        'ANTIGUAS' => 's.fecha_solicitud ASC, s.hora_solicitud ASC, s.id ASC',
        'ACTUALIZADAS' => 's.fecha_actualizacion DESC, s.id DESC',
        'FOLIO' => 's.folio ASC, s.id ASC',
        'PRIORIDAD' => "FIELD(s.prioridad, 'URGENTE','ALTA','MEDIA','BAJA'), s.fecha_solicitud DESC, s.id DESC",
        'VENCIMIENTO' => 'CASE WHEN pm.fecha_limite IS NULL THEN 1 ELSE 0 END, pm.fecha_limite ASC, s.id DESC',
        'MAYOR_TIEMPO' => 'total_segundos_activos DESC, s.fecha_solicitud DESC, s.id DESC',
    ];

    return $opciones[$orden] ?? $opciones['RECIENTES'];
}

/* =========================================================================
   EXPEDIENTE COMPLETO
   ========================================================================= */

function shis_cargar_detalle(PDO $conexion): void
{
    shis_obtener_admin_activo($conexion, shis_admin_id());
    $solicitudId = shis_id_entrada($_GET['solicitud_id'] ?? null, 'solicitud');
    $solicitud = shis_obtener_detalle_principal($conexion, $solicitudId);

    if ($solicitud === null) {
        sm_responder_json(false, 'La solicitud no existe.', [], 404);
    }

    $participantes = shis_obtener_participantes($conexion, $solicitudId);

    $mantenimientoIniciado = false;
    $participantesActivos = 0;
    foreach ($participantes as $participante) {
        if (!empty($participante['fecha_hora_inicio'])) {
            $mantenimientoIniciado = true;
        }
        if ((int) ($participante['activo'] ?? 0) === 1) {
            $participantesActivos++;
        }
    }

    $solicitud['mantenimiento_iniciado'] = $mantenimientoIniciado ? 1 : 0;
    $solicitud['urgencia_iniciada'] = $mantenimientoIniciado ? 1 : 0;
    $solicitud['participantes_activos'] = $participantesActivos;
    $solicitud['puede_cancelar_mantenimiento'] = (
        (int) ($solicitud['activo'] ?? 0) === 1
        && (int) ($solicitud['cierre_id'] ?? 0) === 0
        && in_array(
            (string) ($solicitud['tipo_solicitud'] ?? ''),
            ['CORRECTIVO_PROGRAMABLE','MODIFICACION_MEJORA','CORRECTIVO_URGENTE','RUTINARIO'],
            true
        )
        && in_array(
            (string) ($solicitud['estado'] ?? ''),
            ['APROBADO','AGENDADO','EN_PROCESO','PAUSADO','ATRASADO'],
            true
        )
    ) ? 1 : 0;
    /* Alias temporal para cualquier cliente anterior que todavía lo consulte. */
    $solicitud['puede_cancelar_urgencia'] = $solicitud['puede_cancelar_mantenimiento'];

    $programaciones = shis_obtener_programaciones($conexion, $solicitudId);
    $pausas = shis_obtener_pausas($conexion, $solicitudId);
    $incumplimientos = shis_obtener_incumplimientos($conexion, $solicitudId);
    $historial = shis_obtener_historial($conexion, $solicitudId);
    $evidencias = shis_obtener_evidencias($conexion, $solicitudId);
    $auditoria = shis_obtener_auditoria($conexion, $solicitudId);
    $recursosRecomendados = rsm_obtener_recursos_recomendados_solicitud(
        $conexion,
        $solicitudId,
        false
    );
    $recursosUtilizados = rsm_obtener_recursos_utilizados_cierre(
        $conexion,
        (int) ($solicitud['cierre_id'] ?? 0)
    );

    sm_responder_json(
        true,
        'Expediente cargado correctamente.',
        [
            'solicitud' => $solicitud,
            'participantes' => $participantes,
            'programaciones' => $programaciones,
            'pausas' => $pausas,
            'incumplimientos' => $incumplimientos,
            'historial' => $historial,
            'evidencias' => $evidencias,
            'auditoria' => $auditoria,
            'recursos_recomendados' => $recursosRecomendados,
            'recursos_utilizados' => $recursosUtilizados,
            'metricas' => shis_metricas_detalle(
                $solicitud,
                $participantes,
                $pausas,
                $incumplimientos
            ),
        ]
    );
}

function shis_obtener_detalle_principal(PDO $conexion, int $solicitudId): ?array
{
    $sql = "SELECT
                s.*,
                d.nombre AS departamento,
                d.activo AS departamento_activo,
                a.nombre AS area,
                a.activo AS area_activa,
                p.nombre AS proceso,
                p.activo AS proceso_activo,
                e.codigo_equipo,
                e.nombre_equipo,
                e.descripcion AS descripcion_equipo,
                e.activo AS equipo_activo,
                tf.nombre AS tipo_falla,
                ca.nombre AS causa_averia,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', sol.nombre, sol.apellido_paterno, sol.apellido_materno)), ''),
                    NULLIF(TRIM(CONCAT_WS(' ', adm_sol.nombre, adm_sol.apellido_paterno, adm_sol.apellido_materno)), ''),
                    'Sin solicitante'
                ) AS solicitante,
                COALESCE(sol.telefono, adm_sol.telefono) AS telefono_solicitante,
                COALESCE(sol.correo, adm_sol.correo) AS correo_solicitante,
                CASE
                    WHEN s.solicitante_id IS NOT NULL THEN 'SOLICITANTE'
                    WHEN s.administrador_solicitante_id IS NOT NULL THEN 'ADMIN'
                    ELSE s.creado_por_tipo
                END AS solicitante_tipo,
                CASE
                    WHEN s.creado_por_tipo = 'ADMIN' THEN
                        TRIM(CONCAT_WS(' ', adm_crea.nombre, adm_crea.apellido_paterno, adm_crea.apellido_materno))
                    WHEN s.creado_por_tipo = 'SOLICITANTE' THEN
                        TRIM(CONCAT_WS(' ', sol_crea.nombre, sol_crea.apellido_paterno, sol_crea.apellido_materno))
                    ELSE 'Sistema'
                END AS creado_por,
                TRIM(CONCAT_WS(' ', adm_rev.nombre, adm_rev.apellido_paterno, adm_rev.apellido_materno)) AS revisado_por,
                TRIM(CONCAT_WS(' ', adm_edit.nombre, adm_edit.apellido_paterno, adm_edit.apellido_materno)) AS ultima_edicion_por,
                pm.id AS programacion_id,
                pm.fecha_programada,
                pm.fecha_limite,
                pm.estado AS estado_programacion,
                pm.motivo_programacion,
                pm.motivo_reprogramacion,
                pm.motivo_cancelacion AS motivo_cancelacion_programacion,
                TRIM(CONCAT_WS(' ', adm_prog.nombre, adm_prog.apellido_paterno, adm_prog.apellido_materno)) AS programado_por,
                cm.id AS cierre_id,
                cm.fecha_hora_cierre,
                cm.trabajo_quedo,
                cm.descripcion_trabajo_realizado,
                cm.que_falto,
                cm.realizo_limpieza_area,
                cm.area_ordenada_libre_componentes,
                cm.observaciones_cierre,
                cm.motivo_edicion AS motivo_edicion_cierre,
                CASE
                    WHEN cm.cerrado_por_tecnico_id IS NOT NULL THEN
                        TRIM(CONCAT_WS(' ', te_cierre.nombre, te_cierre.apellido_paterno, te_cierre.apellido_materno))
                    WHEN cm.cerrado_por_admin_id IS NOT NULL THEN
                        TRIM(CONCAT_WS(' ', ad_cierre.nombre, ad_cierre.apellido_paterno, ad_cierre.apellido_materno))
                    ELSE NULL
                END AS cerrado_por,
                CASE
                    WHEN cm.cerrado_por_tecnico_id IS NOT NULL THEN 'TECNICO'
                    WHEN cm.cerrado_por_admin_id IS NOT NULL THEN 'ADMIN'
                    ELSE NULL
                END AS cerrado_por_tipo,
                TRIM(CONCAT_WS(' ', ad_cierre_edit.nombre, ad_cierre_edit.apellido_paterno, ad_cierre_edit.apellido_materno)) AS cierre_editado_por,
                (
                    SELECT GROUP_CONCAT(
                        DISTINCT CONCAT(
                            cme.nombre,
                            CASE
                                WHEN scm.observaciones IS NOT NULL AND TRIM(scm.observaciones) <> ''
                                THEN CONCAT(' (', scm.observaciones, ')')
                                ELSE ''
                            END
                        )
                        ORDER BY cme.nombre
                        SEPARATOR ' · '
                    )
                    FROM solicitud_causas_mejora scm
                    INNER JOIN causas_mejora cme
                            ON cme.id = scm.causa_mejora_id
                    WHERE scm.solicitud_id = s.id
                ) AS causas_mejora,
                CASE
                    WHEN pm.fecha_limite IS NULL THEN 0
                    WHEN cm.fecha_hora_cierre IS NOT NULL
                         AND cm.fecha_hora_cierre > CONCAT(pm.fecha_limite, ' 23:59:59')
                    THEN TIMESTAMPDIFF(SECOND, CONCAT(pm.fecha_limite, ' 23:59:59'), cm.fecha_hora_cierre)
                    WHEN cm.fecha_hora_cierre IS NULL
                         AND s.estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')
                         AND NOW() > CONCAT(pm.fecha_limite, ' 23:59:59')
                    THEN TIMESTAMPDIFF(SECOND, CONCAT(pm.fecha_limite, ' 23:59:59'), NOW())
                    ELSE 0
                END AS segundos_fuera_limite
            FROM solicitudes s
            INNER JOIN departamentos d ON d.id = s.departamento_id
            INNER JOIN areas a ON a.id = s.area_id
            INNER JOIN procesos p ON p.id = s.proceso_id
            INNER JOIN equipos e ON e.id = s.equipo_id
            LEFT JOIN tipos_falla tf ON tf.id = s.tipo_falla_id
            LEFT JOIN causas_averia ca ON ca.id = s.causa_averia_id
            LEFT JOIN solicitantes sol ON sol.id = s.solicitante_id
            LEFT JOIN administradores adm_sol ON adm_sol.id = s.administrador_solicitante_id
            LEFT JOIN administradores adm_crea
                   ON s.creado_por_tipo = 'ADMIN'
                  AND adm_crea.id = s.creado_por_id
            LEFT JOIN solicitantes sol_crea
                   ON s.creado_por_tipo = 'SOLICITANTE'
                  AND sol_crea.id = s.creado_por_id
            LEFT JOIN administradores adm_rev ON adm_rev.id = s.revisado_por_admin_id
            LEFT JOIN administradores adm_edit ON adm_edit.id = s.ultima_edicion_admin_id
            LEFT JOIN programaciones_mantenimiento pm
                   ON pm.solicitud_id = s.id
                  AND pm.es_actual = 1
            LEFT JOIN administradores adm_prog ON adm_prog.id = pm.programado_por_admin_id
            LEFT JOIN cierres_mantenimiento cm ON cm.solicitud_id = s.id
            LEFT JOIN tecnicos te_cierre ON te_cierre.id = cm.cerrado_por_tecnico_id
            LEFT JOIN administradores ad_cierre ON ad_cierre.id = cm.cerrado_por_admin_id
            LEFT JOIN administradores ad_cierre_edit ON ad_cierre_edit.id = cm.editado_por_admin_id
            WHERE s.id = :solicitud_id
            LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($fila)) {
        return null;
    }

    foreach (
        [
            'id', 'solicitante_id', 'administrador_solicitante_id', 'creado_por_id',
            'departamento_id', 'area_id', 'proceso_id', 'equipo_id', 'tipo_falla_id',
            'causa_averia_id', 'trabajo_peligroso', 'requiere_paro_equipo',
            'cupo_tecnicos_urgente', 'revisado_por_admin_id', 'ultima_edicion_admin_id',
            'version_registro', 'activo', 'programacion_id', 'cierre_id',
            'realizo_limpieza_area', 'area_ordenada_libre_componentes',
            'segundos_fuera_limite', 'departamento_activo', 'area_activa',
            'proceso_activo', 'equipo_activo',
        ] as $campo
    ) {
        $fila[$campo] = (int) ($fila[$campo] ?? 0);
    }

    return $fila;
}

function shis_obtener_participantes(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            st.id AS solicitud_tecnico_id,
            st.programacion_id,
            st.tecnico_id,
            st.origen,
            st.estado AS estado_participacion,
            st.fecha_asignacion,
            st.fecha_aceptacion,
            st.fecha_retiro,
            st.alerta_riesgo_nocturno,
            st.riesgo_nocturno_confirmado,
            st.observacion_riesgo_nocturno,
            st.resultado_cumplimiento,
            st.fecha_resultado,
            st.activo,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
            t.turno,
            t.especialidad,
            t.telefono,
            t.correo,
            t.activo AS tecnico_activo,
            dt.nombre AS departamento_tecnico,
            pm.fecha_programada,
            pm.fecha_limite,
            pm.estado AS estado_programacion,
            em.id AS ejecucion_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.fecha_hora_fin,
            em.fecha_ultima_reanudacion,
            em.total_segundos_activos,
            em.total_segundos_pausa,
            em.iniciada_por_tipo,
            em.motivo_edicion_tiempos,
            TRIM(CONCAT_WS(' ', ae.nombre, ae.apellido_paterno, ae.apellido_materno)) AS tiempos_editados_por,
            im.id AS incumplimiento_id,
            im.estado AS estado_incumplimiento,
            im.fecha_detectado,
            im.justificacion,
            im.fecha_resolucion,
            CASE
                WHEN st.resultado_cumplimiento = 'TARDE'
                     AND pm.fecha_limite IS NOT NULL
                     AND COALESCE(em.fecha_hora_fin, cm.fecha_hora_cierre) > CONCAT(pm.fecha_limite, ' 23:59:59')
                THEN TIMESTAMPDIFF(
                    SECOND,
                    CONCAT(pm.fecha_limite, ' 23:59:59'),
                    COALESCE(em.fecha_hora_fin, cm.fecha_hora_cierre)
                )
                ELSE 0
            END AS segundos_retraso
         FROM solicitud_tecnicos st
         INNER JOIN tecnicos t ON t.id = st.tecnico_id
         LEFT JOIN departamentos dt ON dt.id = t.departamento_id
         LEFT JOIN programaciones_mantenimiento pm ON pm.id = st.programacion_id
         LEFT JOIN ejecuciones_mantenimiento em ON em.solicitud_tecnico_id = st.id
         LEFT JOIN administradores ae ON ae.id = em.editado_por_admin_id
         LEFT JOIN incumplimientos_mantenimiento im
                ON im.solicitud_tecnico_id = st.id
               AND im.programacion_id = st.programacion_id
         LEFT JOIN cierres_mantenimiento cm ON cm.solicitud_id = st.solicitud_id
         WHERE st.solicitud_id = :solicitud_id
         ORDER BY
            FIELD(st.estado, 'EN_PROCESO','PAUSADO','TERMINADO','ACEPTADO','ASIGNADO','NO_PARTICIPO','RETIRADO'),
            t.nombre,
            t.apellido_paterno,
            st.id"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($filas as &$fila) {
        foreach (
            [
                'solicitud_tecnico_id', 'programacion_id', 'tecnico_id', 'activo',
                'tecnico_activo', 'alerta_riesgo_nocturno', 'riesgo_nocturno_confirmado',
                'ejecucion_id', 'total_segundos_activos', 'total_segundos_pausa',
                'incumplimiento_id', 'segundos_retraso',
            ] as $campo
        ) {
            $fila[$campo] = (int) ($fila[$campo] ?? 0);
        }
    }
    unset($fila);

    return $filas;
}

function shis_obtener_programaciones(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            pm.id,
            pm.fecha_programada,
            pm.fecha_limite,
            pm.estado,
            pm.es_actual,
            pm.motivo_programacion,
            pm.motivo_reprogramacion,
            pm.motivo_cancelacion,
            pm.fecha_registro,
            pm.fecha_actualizacion,
            TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno)) AS programado_por,
            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos st
                WHERE st.programacion_id = pm.id
            ) AS total_asignaciones,
            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos st
                WHERE st.programacion_id = pm.id
                  AND st.resultado_cumplimiento = 'A_TIEMPO'
            ) AS a_tiempo,
            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos st
                WHERE st.programacion_id = pm.id
                  AND st.resultado_cumplimiento = 'TARDE'
            ) AS tarde,
            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos st
                WHERE st.programacion_id = pm.id
                  AND st.resultado_cumplimiento = 'NO_REALIZADO'
            ) AS no_realizado
         FROM programaciones_mantenimiento pm
         INNER JOIN administradores ad ON ad.id = pm.programado_por_admin_id
         WHERE pm.solicitud_id = :solicitud_id
         ORDER BY pm.es_actual DESC, pm.id DESC"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($filas as &$fila) {
        foreach (['id', 'es_actual', 'total_asignaciones', 'a_tiempo', 'tarde', 'no_realizado'] as $campo) {
            $fila[$campo] = (int) ($fila[$campo] ?? 0);
        }
    }
    unset($fila);

    return $filas;
}

function shis_obtener_pausas(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            pe.id,
            pe.ejecucion_id,
            pe.fecha_hora_inicio,
            pe.fecha_hora_fin,
            pe.duracion_segundos,
            pe.motivo,
            pe.solicitud_urgente_id,
            pe.observaciones,
            pe.creada_por_tipo,
            pe.creada_por_id,
            em.tecnico_id,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
            su.folio AS folio_urgencia,
            eu.nombre_equipo AS equipo_urgencia
         FROM pausas_ejecucion pe
         INNER JOIN ejecuciones_mantenimiento em ON em.id = pe.ejecucion_id
         INNER JOIN tecnicos t ON t.id = em.tecnico_id
         LEFT JOIN solicitudes su ON su.id = pe.solicitud_urgente_id
         LEFT JOIN equipos eu ON eu.id = su.equipo_id
         WHERE em.solicitud_id = :solicitud_id
         ORDER BY pe.fecha_hora_inicio ASC, pe.id ASC"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($filas as &$fila) {
        foreach (['id', 'ejecucion_id', 'duracion_segundos', 'solicitud_urgente_id', 'tecnico_id'] as $campo) {
            $fila[$campo] = (int) ($fila[$campo] ?? 0);
        }
    }
    unset($fila);

    return $filas;
}

function shis_obtener_incumplimientos(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            im.id,
            im.programacion_id,
            im.solicitud_tecnico_id,
            im.fecha_programada,
            im.fecha_detectado,
            im.estado,
            im.justificacion,
            im.fecha_resolucion,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
            TRIM(CONCAT_WS(' ', aj.nombre, aj.apellido_paterno, aj.apellido_materno)) AS justificado_por,
            st.resultado_cumplimiento,
            st.fecha_resultado
         FROM incumplimientos_mantenimiento im
         INNER JOIN solicitud_tecnicos st ON st.id = im.solicitud_tecnico_id
         INNER JOIN tecnicos t ON t.id = st.tecnico_id
         LEFT JOIN administradores aj ON aj.id = im.justificado_por_admin_id
         WHERE im.solicitud_id = :solicitud_id
         ORDER BY im.fecha_detectado DESC, im.id DESC"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($filas as &$fila) {
        foreach (['id', 'programacion_id', 'solicitud_tecnico_id'] as $campo) {
            $fila[$campo] = (int) ($fila[$campo] ?? 0);
        }
    }
    unset($fila);

    return $filas;
}

function shis_obtener_historial(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            hs.id,
            hs.solicitud_tecnico_id,
            hs.programacion_id,
            hs.evento,
            hs.estado_anterior,
            hs.estado_nuevo,
            hs.actor_tipo,
            hs.actor_id,
            hs.descripcion,
            hs.fecha_evento,
            CASE
                WHEN hs.actor_tipo = 'ADMIN' THEN
                    TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno))
                WHEN hs.actor_tipo = 'TECNICO' THEN
                    TRIM(CONCAT_WS(' ', te.nombre, te.apellido_paterno, te.apellido_materno))
                WHEN hs.actor_tipo = 'SOLICITANTE' THEN
                    TRIM(CONCAT_WS(' ', so.nombre, so.apellido_paterno, so.apellido_materno))
                ELSE 'Sistema'
            END AS actor
         FROM historial_solicitudes hs
         LEFT JOIN administradores ad
                ON hs.actor_tipo = 'ADMIN'
               AND ad.id = hs.actor_id
         LEFT JOIN tecnicos te
                ON hs.actor_tipo = 'TECNICO'
               AND te.id = hs.actor_id
         LEFT JOIN solicitantes so
                ON hs.actor_tipo = 'SOLICITANTE'
               AND so.id = hs.actor_id
         WHERE hs.solicitud_id = :solicitud_id
         ORDER BY hs.fecha_evento ASC, hs.id ASC
         LIMIT 500"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function shis_obtener_evidencias(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            ev.id,
            ev.ejecucion_id,
            ev.cierre_id,
            ev.tipo_evidencia,
            ev.nombre_original,
            ev.ruta_archivo,
            ev.mime_type,
            ev.tamano_bytes,
            ev.descripcion,
            ev.subido_por_tipo,
            ev.subido_por_id,
            ev.fecha_registro,
            CASE
                WHEN ev.subido_por_tipo = 'ADMIN' THEN
                    TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno))
                WHEN ev.subido_por_tipo = 'TECNICO' THEN
                    TRIM(CONCAT_WS(' ', te.nombre, te.apellido_paterno, te.apellido_materno))
                WHEN ev.subido_por_tipo = 'SOLICITANTE' THEN
                    TRIM(CONCAT_WS(' ', so.nombre, so.apellido_paterno, so.apellido_materno))
                ELSE 'Usuario'
            END AS subido_por
         FROM evidencias_mantenimiento ev
         LEFT JOIN administradores ad
                ON ev.subido_por_tipo = 'ADMIN'
               AND ad.id = ev.subido_por_id
         LEFT JOIN tecnicos te
                ON ev.subido_por_tipo = 'TECNICO'
               AND te.id = ev.subido_por_id
         LEFT JOIN solicitantes so
                ON ev.subido_por_tipo = 'SOLICITANTE'
               AND so.id = ev.subido_por_id
         WHERE ev.solicitud_id = :solicitud_id
           AND ev.activo = 1
         ORDER BY ev.fecha_registro ASC, ev.id ASC"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($filas as &$fila) {
        $fila['id'] = (int) ($fila['id'] ?? 0);
        $fila['ejecucion_id'] = (int) ($fila['ejecucion_id'] ?? 0);
        $fila['cierre_id'] = (int) ($fila['cierre_id'] ?? 0);
        $fila['tamano_bytes'] = (int) ($fila['tamano_bytes'] ?? 0);
        $fila['ruta_publica'] = shis_ruta_publica_evidencia(
            (string) ($fila['ruta_archivo'] ?? '')
        );
    }
    unset($fila);

    return $filas;
}

function shis_obtener_auditoria(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            ae.id,
            ae.tabla_afectada,
            ae.registro_id,
            ae.actor_tipo,
            ae.actor_id,
            ae.accion,
            ae.motivo,
            ae.datos_anteriores,
            ae.datos_nuevos,
            ae.ip_address,
            ae.fecha_evento,
            CASE
                WHEN ae.actor_tipo = 'ADMIN' THEN
                    TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno))
                WHEN ae.actor_tipo = 'TECNICO' THEN
                    TRIM(CONCAT_WS(' ', te.nombre, te.apellido_paterno, te.apellido_materno))
                WHEN ae.actor_tipo = 'SOLICITANTE' THEN
                    TRIM(CONCAT_WS(' ', so.nombre, so.apellido_paterno, so.apellido_materno))
                ELSE 'Sistema'
            END AS actor
         FROM auditoria_ediciones ae
         LEFT JOIN administradores ad
                ON ae.actor_tipo = 'ADMIN'
               AND ad.id = ae.actor_id
         LEFT JOIN tecnicos te
                ON ae.actor_tipo = 'TECNICO'
               AND te.id = ae.actor_id
         LEFT JOIN solicitantes so
                ON ae.actor_tipo = 'SOLICITANTE'
               AND so.id = ae.actor_id
         WHERE ae.solicitud_id = :solicitud_id
            OR (ae.tabla_afectada = 'solicitudes' AND ae.registro_id = :solicitud_registro)
         ORDER BY ae.fecha_evento DESC, ae.id DESC
         LIMIT 200"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_registro', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($filas as &$fila) {
        $fila['id'] = (int) ($fila['id'] ?? 0);
        $fila['registro_id'] = (int) ($fila['registro_id'] ?? 0);
        $fila['cambios'] = shis_resumir_cambios_json(
            (string) ($fila['datos_anteriores'] ?? ''),
            (string) ($fila['datos_nuevos'] ?? '')
        );
        unset($fila['datos_anteriores'], $fila['datos_nuevos']);
    }
    unset($fila);

    return $filas;
}

function shis_metricas_detalle(
    array $solicitud,
    array $participantes,
    array $pausas,
    array $incumplimientos
): array {
    $segundosActivos = 0;
    $segundosPausa = 0;
    $aTiempo = 0;
    $tarde = 0;
    $noRealizado = 0;
    $pendiente = 0;
    $participaron = 0;

    foreach ($participantes as $participante) {
        $segundosActivos += (int) ($participante['total_segundos_activos'] ?? 0);
        $segundosPausa += (int) ($participante['total_segundos_pausa'] ?? 0);

        if ((int) ($participante['ejecucion_id'] ?? 0) > 0) {
            $participaron++;
        }

        $resultado = (string) ($participante['resultado_cumplimiento'] ?? '');
        if ($resultado === 'A_TIEMPO') {
            $aTiempo++;
        } elseif ($resultado === 'TARDE') {
            $tarde++;
        } elseif ($resultado === 'NO_REALIZADO') {
            $noRealizado++;
        } elseif ($resultado === 'PENDIENTE') {
            $pendiente++;
        }
    }

    $pausasAbiertas = 0;
    foreach ($pausas as $pausa) {
        if (empty($pausa['fecha_hora_fin'])) {
            $pausasAbiertas++;
        }
    }

    return [
        'total_asignaciones' => count($participantes),
        'participaron' => $participaron,
        'a_tiempo' => $aTiempo,
        'tarde' => $tarde,
        'no_realizado' => $noRealizado,
        'pendiente' => $pendiente,
        'total_segundos_activos' => $segundosActivos,
        'total_segundos_pausa' => $segundosPausa,
        'total_pausas' => count($pausas),
        'pausas_abiertas' => $pausasAbiertas,
        'total_incumplimientos' => count($incumplimientos),
        'segundos_fuera_limite' => (int) ($solicitud['segundos_fuera_limite'] ?? 0),
    ];
}

function shis_resumir_cambios_json(string $anterior, string $nuevo): array
{
    $antes = json_decode($anterior, true);
    $despues = json_decode($nuevo, true);

    if (!is_array($antes) || !is_array($despues)) {
        return [];
    }

    $cambios = [];
    $claves = array_unique(array_merge(array_keys($antes), array_keys($despues)));

    foreach ($claves as $clave) {
        $valorAntes = $antes[$clave] ?? null;
        $valorDespues = $despues[$clave] ?? null;

        if ($valorAntes === $valorDespues) {
            continue;
        }

        $cambios[] = [
            'campo' => (string) $clave,
            'antes' => shis_valor_a_texto($valorAntes),
            'despues' => shis_valor_a_texto($valorDespues),
        ];

        if (count($cambios) >= 30) {
            break;
        }
    }

    return $cambios;
}

function shis_valor_a_texto($valor): string
{
    if ($valor === null) {
        return 'Sin valor';
    }

    if (is_bool($valor)) {
        return $valor ? 'Sí' : 'No';
    }

    if (is_scalar($valor)) {
        return shis_limitar((string) $valor, 250);
    }

    $json = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return shis_limitar(is_string($json) ? $json : 'Dato complejo', 250);
}

function shis_ruta_publica_evidencia(string $ruta): ?string
{
    $ruta = trim(str_replace('\\', '/', $ruta));

    if (
        $ruta === ''
        || strpos($ruta, '..') !== false
        || preg_match('#^[a-z][a-z0-9+.-]*://#i', $ruta)
        || strpos($ruta, "\0") !== false
    ) {
        return null;
    }

    return '../' . ltrim($ruta, '/');
}

/* =========================================================================
   EXPORTACIÓN CSV
   ========================================================================= */

function shis_preparar_exportacion(PDO $conexion): array
{
    shis_obtener_admin_activo($conexion, shis_admin_id());

    $filtros = shis_leer_filtros();
    $consulta = shis_construir_condiciones($filtros);
    $ordenSql = shis_orden_sql((string) $filtros['orden']);

    $conexion->exec('SET SESSION group_concat_max_len = 100000');

    $sql = "SELECT
                s.folio,
                s.tipo_solicitud,
                s.estado,
                s.prioridad,
                s.fecha_solicitud,
                s.hora_solicitud,
                d.nombre AS departamento,
                a.nombre AS area,
                p.nombre AS proceso,
                e.codigo_equipo,
                e.nombre_equipo,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', sol.nombre, sol.apellido_paterno, sol.apellido_materno)), ''),
                    NULLIF(TRIM(CONCAT_WS(' ', adm_sol.nombre, adm_sol.apellido_paterno, adm_sol.apellido_materno)), ''),
                    'Sin solicitante'
                ) AS solicitante,
                s.descripcion_solicitud,
                pm.fecha_programada,
                pm.fecha_limite,
                pm.estado AS estado_programacion,
                cm.fecha_hora_cierre,
                cm.trabajo_quedo,
                cm.descripcion_trabajo_realizado,
                cm.que_falto,
                (
                    SELECT GROUP_CONCAT(
                        DISTINCT TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno))
                        ORDER BY t.nombre, t.apellido_paterno
                        SEPARATOR ' | '
                    )
                    FROM solicitud_tecnicos st
                    INNER JOIN tecnicos t ON t.id = st.tecnico_id
                    WHERE st.solicitud_id = s.id
                ) AS tecnicos,
                (
                    SELECT GROUP_CONCAT(
                        DISTINCT CONCAT(
                            TRIM(CONCAT_WS(' ', t2.nombre, t2.apellido_paterno, t2.apellido_materno)),
                            ': ', st2.resultado_cumplimiento
                        )
                        ORDER BY t2.nombre, t2.apellido_paterno
                        SEPARATOR ' | '
                    )
                    FROM solicitud_tecnicos st2
                    INNER JOIN tecnicos t2 ON t2.id = st2.tecnico_id
                    WHERE st2.solicitud_id = s.id
                ) AS cumplimiento_tecnicos,
                (
                    SELECT COALESCE(SUM(em.total_segundos_activos), 0)
                    FROM ejecuciones_mantenimiento em
                    WHERE em.solicitud_id = s.id
                ) AS segundos_activos,
                (
                    SELECT COALESCE(SUM(em2.total_segundos_pausa), 0)
                    FROM ejecuciones_mantenimiento em2
                    WHERE em2.solicitud_id = s.id
                ) AS segundos_pausa
            " . shis_base_desde() . "
            {$consulta['where']}
            ORDER BY {$ordenSql}";

    $stmt = $conexion->prepare($sql);
    shis_vincular_parametros($stmt, $consulta['parametros']);
    $stmt->execute();

    return [
        'consulta' => $stmt,
        'filtros' => $filtros,
    ];
}

function shis_columnas_exportacion(): array
{
    return [
        'Folio',
        'Tipo',
        'Estado',
        'Prioridad',
        'Fecha solicitud',
        'Hora solicitud',
        'Departamento',
        'Área',
        'Proceso',
        'Código equipo',
        'Equipo',
        'Solicitante',
        'Trabajo solicitado',
        'Fecha programada',
        'Fecha límite',
        'Estado programación',
        'Fecha cierre',
        'Cómo quedó',
        'Trabajo realizado',
        'Qué faltó',
        'Técnicos',
        'Cumplimiento por técnico',
        'Tiempo activo',
        'Tiempo en pausa',
    ];
}

function shis_valores_exportacion(array $fila): array
{
    return [
        (string) ($fila['folio'] ?? ''),
        shis_etiqueta_tipo((string) ($fila['tipo_solicitud'] ?? '')),
        shis_etiqueta_estado((string) ($fila['estado'] ?? '')),
        shis_etiqueta_prioridad((string) ($fila['prioridad'] ?? '')),
        shis_fecha_csv((string) ($fila['fecha_solicitud'] ?? '')),
        shis_hora_csv((string) ($fila['hora_solicitud'] ?? '')),
        (string) ($fila['departamento'] ?? ''),
        (string) ($fila['area'] ?? ''),
        (string) ($fila['proceso'] ?? ''),
        (string) ($fila['codigo_equipo'] ?? ''),
        (string) ($fila['nombre_equipo'] ?? ''),
        (string) ($fila['solicitante'] ?? ''),
        (string) ($fila['descripcion_solicitud'] ?? ''),
        shis_fecha_csv((string) ($fila['fecha_programada'] ?? '')),
        shis_fecha_csv((string) ($fila['fecha_limite'] ?? '')),
        (string) ($fila['estado_programacion'] ?? ''),
        shis_fecha_hora_csv((string) ($fila['fecha_hora_cierre'] ?? '')),
        (string) ($fila['trabajo_quedo'] ?? ''),
        (string) ($fila['descripcion_trabajo_realizado'] ?? ''),
        (string) ($fila['que_falto'] ?? ''),
        (string) ($fila['tecnicos'] ?? ''),
        (string) ($fila['cumplimiento_tecnicos'] ?? ''),
        shis_duracion_csv((int) ($fila['segundos_activos'] ?? 0)),
        shis_duracion_csv((int) ($fila['segundos_pausa'] ?? 0)),
    ];
}

function shis_html_exportacion($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function shis_resumen_filtros_exportacion(array $filtros): string
{
    $partes = [];

    if (($filtros['estado'] ?? 'TODOS') !== 'TODOS') {
        $partes[] = 'Estado: ' . shis_etiqueta_estado((string) $filtros['estado']);
    }

    if (($filtros['tipo'] ?? 'TODOS') !== 'TODOS') {
        $partes[] = 'Tipo: ' . shis_etiqueta_tipo((string) $filtros['tipo']);
    }

    if (($filtros['busqueda'] ?? '') !== '') {
        $partes[] = 'Búsqueda: ' . (string) $filtros['busqueda'];
    }

    if ((int) ($filtros['tecnico_id'] ?? 0) > 0) {
        $partes[] = 'Técnico seleccionado';
    }

    if (($filtros['fecha_desde'] ?? '') !== '') {
        $partes[] = 'Desde: ' . shis_fecha_csv((string) $filtros['fecha_desde']);
    }

    if (($filtros['fecha_hasta'] ?? '') !== '') {
        $partes[] = 'Hasta: ' . shis_fecha_csv((string) $filtros['fecha_hasta']);
    }

    return $partes === [] ? 'Sin filtros adicionales' : implode(' · ', $partes);
}

function shis_exportar_excel(PDO $conexion): void
{
    $exportacion = shis_preparar_exportacion($conexion);
    /** @var PDOStatement $stmt */
    $stmt = $exportacion['consulta'];
    $filtros = $exportacion['filtros'];

    $nombre = 'historial_solicitudes_' . date('Y-m-d_H-i') . '.xls';

    if (!headers_sent()) {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    echo "\xEF\xBB\xBF";
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<style>';
    echo 'body{font-family:Calibri,Arial,sans-serif;color:#17324b;background:#fff;}';
    echo 'table{border-collapse:collapse;width:100%;table-layout:fixed;}';
    echo 'td,th{border:1px solid #cbd9e5;padding:7px 8px;vertical-align:top;white-space:normal;word-wrap:break-word;}';
    echo '.report-title{background:#0b2944;color:#fff;font-size:20px;font-weight:700;text-align:left;padding:16px;}';
    echo '.report-meta{background:#eaf4fb;color:#37566f;font-size:11px;font-weight:600;padding:9px;}';
    echo '.header{background:#15517d;color:#fff;font-size:11px;font-weight:700;text-align:left;}';
    echo '.row-even td{background:#f4f8fb;}';
    echo '.text{mso-number-format:"\\@";}';
    echo '.status{font-weight:700;}';
    echo '.small{font-size:10px;color:#5d7185;}';
    echo '</style></head><body><table>';
    echo '<tr><th class="report-title" colspan="24">Sistema de Mantenimiento · Historial de solicitudes</th></tr>';
    echo '<tr><td class="report-meta" colspan="24">Generado: '
        . shis_html_exportacion(date('d/m/Y H:i'))
        . ' · ' . shis_html_exportacion(shis_resumen_filtros_exportacion($filtros))
        . '</td></tr>';
    echo '<tr>';

    foreach (shis_columnas_exportacion() as $columna) {
        echo '<th class="header">' . shis_html_exportacion($columna) . '</th>';
    }

    echo '</tr>';

    $indice = 0;
    while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $indice++;
        $claseFila = $indice % 2 === 0 ? ' class="row-even"' : '';
        echo '<tr' . $claseFila . '>';

        foreach (shis_valores_exportacion($fila) as $posicion => $valor) {
            $clases = 'text';
            if (in_array($posicion, [2, 3, 15, 17], true)) {
                $clases .= ' status';
            }
            echo '<td class="' . $clases . '">' . shis_html_exportacion($valor) . '</td>';
        }

        echo '</tr>';
    }

    if ($indice === 0) {
        echo '<tr><td colspan="24" style="padding:20px;text-align:center;color:#6b7f91;">'
            . 'No existen registros que coincidan con los filtros.'
            . '</td></tr>';
    }

    echo '</table></body></html>';
    exit;
}

function shis_exportar_pdf(PDO $conexion): void
{
    $exportacion = shis_preparar_exportacion($conexion);
    /** @var PDOStatement $stmt */
    $stmt = $exportacion['consulta'];
    $filtros = $exportacion['filtros'];

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
    }

    $titulo = 'Historial de solicitudes';
    $filtrosTexto = shis_resumen_filtros_exportacion($filtros);

    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . shis_html_exportacion($titulo) . '</title>';
    echo '<style>
        @page{size:A4 landscape;margin:9mm;}
        *{box-sizing:border-box;}
        body{margin:0;font-family:"Segoe UI",Arial,sans-serif;color:#17324b;background:#eef4f8;}
        .toolbar{position:sticky;top:0;z-index:10;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 18px;background:#0b2944;color:#fff;box-shadow:0 8px 24px rgba(5,25,43,.22);}
        .toolbar strong{font-size:14px;}
        .toolbar span{font-size:11px;color:rgba(255,255,255,.7);}
        .toolbar button{min-height:40px;padding:0 16px;border:1px solid rgba(255,255,255,.22);border-radius:10px;background:#fff;color:#0b2944;font-weight:800;cursor:pointer;}
        .report{width:min(1500px,calc(100% - 30px));margin:18px auto;padding:22px;background:#fff;box-shadow:0 18px 50px rgba(8,35,58,.12);}
        .head{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;padding-bottom:16px;border-bottom:3px solid #17b8c4;}
        .head small{display:block;color:#1d6b9e;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;}
        h1{margin:4px 0 6px;color:#0b2944;font-size:25px;}
        .head p,.meta{margin:0;color:#60758a;font-size:11px;line-height:1.5;}
        .brand{padding:10px 13px;border-radius:12px;background:#e9f5fb;color:#15517d;text-align:right;font-size:10px;font-weight:700;}
        .meta{margin:12px 0 14px;padding:10px 12px;border:1px solid #d9e5ee;border-radius:9px;background:#f6f9fb;}
        table{width:100%;border-collapse:collapse;table-layout:fixed;}
        thead{display:table-header-group;}
        tr{page-break-inside:avoid;}
        th{padding:8px 7px;border:1px solid #15517d;background:#15517d;color:#fff;font-size:8.5px;text-align:left;vertical-align:top;}
        td{padding:7px;border:1px solid #d4e0e9;color:#334f66;font-size:8px;line-height:1.35;vertical-align:top;word-break:break-word;}
        tbody tr:nth-child(even) td{background:#f4f8fb;}
        td strong{color:#0b2944;}
        .state{font-weight:800;}
        .empty{padding:28px;text-align:center;color:#718599;}
        .footer{margin-top:12px;display:flex;justify-content:space-between;color:#718599;font-size:8px;}
        @media print{
            body{background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
            .toolbar{display:none!important;}
            .report{width:100%;margin:0;padding:0;box-shadow:none;}
            .head{padding-top:0;}
        }
    </style></head><body>';

    echo '<div class="toolbar"><div><strong>Reporte preparado</strong><br><span>'
        . 'Selecciona “Guardar como PDF” en el cuadro de impresión.'
        . '</span></div><button type="button" onclick="window.print()">Imprimir / Guardar PDF</button></div>';

    echo '<main class="report"><header class="head"><div><small>Consulta administrativa</small>';
    echo '<h1>' . shis_html_exportacion($titulo) . '</h1>';
    echo '<p>Expediente resumido de los resultados que coinciden con los filtros seleccionados.</p></div>';
    echo '<div class="brand">Sistema de Mantenimiento<br>Los Chapeteados División Petfood</div></header>';
    echo '<div class="meta"><strong>Generado:</strong> ' . shis_html_exportacion(date('d/m/Y H:i'))
        . ' &nbsp;·&nbsp; <strong>Filtros:</strong> ' . shis_html_exportacion($filtrosTexto) . '</div>';

    echo '<table><colgroup>';
    echo '<col style="width:8%"><col style="width:12%"><col style="width:8%"><col style="width:18%">';
    echo '<col style="width:14%"><col style="width:10%"><col style="width:12%"><col style="width:10%"><col style="width:8%">';
    echo '</colgroup><thead><tr>';
    foreach (['Folio','Tipo / prioridad','Estado','Equipo y ubicación','Solicitante','Solicitud','Programación','Técnicos','Cumplimiento'] as $columna) {
        echo '<th>' . shis_html_exportacion($columna) . '</th>';
    }
    echo '</tr></thead><tbody>';

    $indice = 0;
    while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $indice++;
        $ubicacion = implode(' / ', array_filter([
            (string) ($fila['departamento'] ?? ''),
            (string) ($fila['area'] ?? ''),
            (string) ($fila['proceso'] ?? ''),
        ]));

        $programacion = 'Sin programación';
        if (!empty($fila['fecha_programada'])) {
            $programacion = 'Programada: ' . shis_fecha_csv((string) $fila['fecha_programada']);
            if (!empty($fila['fecha_limite'])) {
                $programacion .= ' · Límite: ' . shis_fecha_csv((string) $fila['fecha_limite']);
            }
        }

        $cumplimiento = (string) ($fila['cumplimiento_tecnicos'] ?? '');
        if ($cumplimiento === '') {
            $cumplimiento = (string) ($fila['trabajo_quedo'] ?? 'Sin resultado');
        }

        echo '<tr>';
        echo '<td><strong>' . shis_html_exportacion($fila['folio'] ?? '') . '</strong><br>'
            . shis_html_exportacion(shis_fecha_csv((string) ($fila['fecha_solicitud'] ?? ''))) . '</td>';
        echo '<td>' . shis_html_exportacion(shis_etiqueta_tipo((string) ($fila['tipo_solicitud'] ?? '')))
            . '<br><strong>' . shis_html_exportacion(shis_etiqueta_prioridad((string) ($fila['prioridad'] ?? ''))) . '</strong></td>';
        echo '<td class="state">' . shis_html_exportacion(shis_etiqueta_estado((string) ($fila['estado'] ?? ''))) . '</td>';
        echo '<td><strong>' . shis_html_exportacion($fila['codigo_equipo'] ?? '') . ' · '
            . shis_html_exportacion($fila['nombre_equipo'] ?? '') . '</strong><br>'
            . shis_html_exportacion($ubicacion) . '</td>';
        echo '<td>' . shis_html_exportacion($fila['solicitante'] ?? '') . '</td>';
        echo '<td>' . shis_html_exportacion($fila['descripcion_solicitud'] ?? '') . '</td>';
        echo '<td>' . shis_html_exportacion($programacion) . '</td>';
        echo '<td>' . shis_html_exportacion($fila['tecnicos'] ?? 'Sin técnicos') . '</td>';
        echo '<td>' . shis_html_exportacion($cumplimiento) . '</td>';
        echo '</tr>';
    }

    if ($indice === 0) {
        echo '<tr><td class="empty" colspan="9">No existen registros que coincidan con los filtros.</td></tr>';
    }

    echo '</tbody></table>';
    echo '<footer class="footer"><span>Reporte administrativo de solo lectura</span><span>'
        . shis_html_exportacion(date('d/m/Y H:i')) . '</span></footer></main>';
    echo '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},450);});</script>';
    echo '</body></html>';
    exit;
}

/* Alias de compatibilidad para enlaces anteriores. */
function shis_exportar_csv(PDO $conexion): void
{
    shis_exportar_excel($conexion);
}

/* =========================================================================
   FILTROS Y CATÁLOGOS
   ========================================================================= */

function shis_leer_filtros(): array
{
    $estados = [
        'TODOS', 'PENDIENTE', 'APROBADO', 'AGENDADO', 'EN_PROCESO', 'PAUSADO',
        'ATRASADO', 'TERMINADO', 'RECHAZADO', 'CANCELADO',
    ];
    $tipos = [
        'TODOS', 'CORRECTIVO_PROGRAMABLE', 'MODIFICACION_MEJORA',
        'CORRECTIVO_URGENTE', 'RUTINARIO',
    ];
    $prioridades = ['TODAS', 'BAJA', 'MEDIA', 'ALTA', 'URGENTE'];
    $resultados = ['TODOS', 'TERMINADO', 'PARCIAL', 'PROVISIONAL', 'SIN_CIERRE'];
    $cumplimientos = [
        'TODOS', 'A_TIEMPO', 'TARDE', 'NO_REALIZADO', 'PENDIENTE',
        'NO_APLICA', 'SIN_ASIGNACION',
    ];
    $programaciones = ['TODAS', 'PROGRAMADA', 'CUMPLIDA', 'VENCIDA', 'REPROGRAMADA', 'CANCELADA', 'SIN_PROGRAMACION'];
    $vigencias = ['ACTIVAS', 'INACTIVAS', 'TODAS'];
    $ordenes = ['RECIENTES', 'ANTIGUAS', 'ACTUALIZADAS', 'FOLIO', 'PRIORIDAD', 'VENCIMIENTO', 'MAYOR_TIEMPO'];

    $estado = strtoupper(shis_texto($_GET['estado'] ?? 'TODOS'));
    $tipo = strtoupper(shis_texto($_GET['tipo'] ?? 'TODOS'));
    $prioridad = strtoupper(shis_texto($_GET['prioridad'] ?? 'TODAS'));
    $resultado = strtoupper(shis_texto($_GET['resultado'] ?? 'TODOS'));
    $cumplimiento = strtoupper(shis_texto($_GET['cumplimiento'] ?? 'TODOS'));
    $programacion = strtoupper(shis_texto($_GET['programacion'] ?? 'TODAS'));
    $vigencia = strtoupper(shis_texto($_GET['vigencia'] ?? 'ACTIVAS'));
    $orden = strtoupper(shis_texto($_GET['orden'] ?? 'RECIENTES'));

    $fechaDesde = shis_texto($_GET['fecha_desde'] ?? '');
    $fechaHasta = shis_texto($_GET['fecha_hasta'] ?? '');

    if ($fechaDesde !== '' && !shis_fecha_valida($fechaDesde)) {
        $fechaDesde = '';
    }
    if ($fechaHasta !== '' && !shis_fecha_valida($fechaHasta)) {
        $fechaHasta = '';
    }
    if ($fechaDesde !== '' && $fechaHasta !== '' && $fechaDesde > $fechaHasta) {
        $temporal = $fechaDesde;
        $fechaDesde = $fechaHasta;
        $fechaHasta = $temporal;
    }

    $pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
    $porPagina = filter_var($_GET['por_pagina'] ?? 15, FILTER_VALIDATE_INT);
    $departamentoId = filter_var($_GET['departamento_id'] ?? 0, FILTER_VALIDATE_INT);
    $tecnicoId = filter_var($_GET['tecnico_id'] ?? 0, FILTER_VALIDATE_INT);

    if (!in_array((int) $porPagina, [15, 30, 60], true)) {
        $porPagina = 15;
    }

    return [
        'busqueda' => shis_limitar(shis_texto($_GET['busqueda'] ?? ''), 120),
        'estado' => in_array($estado, $estados, true) ? $estado : 'TODOS',
        'tipo' => in_array($tipo, $tipos, true) ? $tipo : 'TODOS',
        'prioridad' => in_array($prioridad, $prioridades, true) ? $prioridad : 'TODAS',
        'resultado' => in_array($resultado, $resultados, true) ? $resultado : 'TODOS',
        'cumplimiento' => in_array($cumplimiento, $cumplimientos, true) ? $cumplimiento : 'TODOS',
        'programacion' => in_array($programacion, $programaciones, true) ? $programacion : 'TODAS',
        'vigencia' => in_array($vigencia, $vigencias, true) ? $vigencia : 'ACTIVAS',
        'departamento_id' => $departamentoId && $departamentoId > 0 ? (int) $departamentoId : 0,
        'tecnico_id' => $tecnicoId && $tecnicoId > 0 ? (int) $tecnicoId : 0,
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta,
        'orden' => in_array($orden, $ordenes, true) ? $orden : 'RECIENTES',
        'pagina' => $pagina && $pagina > 0 ? (int) $pagina : 1,
        'por_pagina' => (int) $porPagina,
    ];
}

function shis_construir_condiciones(array $filtros): array
{
    $condiciones = [];
    $parametros = [];

    if ($filtros['vigencia'] === 'ACTIVAS') {
        $condiciones[] = 's.activo = 1';
    } elseif ($filtros['vigencia'] === 'INACTIVAS') {
        $condiciones[] = 's.activo = 0';
    }

    if ($filtros['busqueda'] !== '') {
        $condiciones[] = "CONCAT_WS(
                ' ',
                s.folio,
                s.descripcion_solicitud,
                s.descripcion_falla,
                s.impacto_operacion,
                s.objetivo_mejora,
                s.resultado_esperado,
                e.codigo_equipo,
                e.nombre_equipo,
                d.nombre,
                a.nombre,
                p.nombre,
                tf.nombre,
                ca.nombre,
                sol.nombre,
                sol.apellido_paterno,
                sol.apellido_materno,
                adm_sol.nombre,
                adm_sol.apellido_paterno,
                adm_sol.apellido_materno,
                cm.descripcion_trabajo_realizado,
                cm.que_falto
            ) LIKE :busqueda";
        $parametros[':busqueda'] = ['%' . $filtros['busqueda'] . '%', PDO::PARAM_STR];
    }

    if ($filtros['estado'] !== 'TODOS') {
        $condiciones[] = 's.estado = :estado';
        $parametros[':estado'] = [$filtros['estado'], PDO::PARAM_STR];
    }

    if ($filtros['tipo'] !== 'TODOS') {
        $condiciones[] = 's.tipo_solicitud = :tipo';
        $parametros[':tipo'] = [$filtros['tipo'], PDO::PARAM_STR];
    }

    if ($filtros['prioridad'] !== 'TODAS') {
        $condiciones[] = 's.prioridad = :prioridad';
        $parametros[':prioridad'] = [$filtros['prioridad'], PDO::PARAM_STR];
    }

    if ((int) $filtros['departamento_id'] > 0) {
        $condiciones[] = 's.departamento_id = :departamento_id';
        $parametros[':departamento_id'] = [(int) $filtros['departamento_id'], PDO::PARAM_INT];
    }

    if ((int) $filtros['tecnico_id'] > 0) {
        $condiciones[] = "EXISTS (
            SELECT 1
            FROM solicitud_tecnicos st_filtro_tecnico
            WHERE st_filtro_tecnico.solicitud_id = s.id
              AND st_filtro_tecnico.tecnico_id = :tecnico_id
        )";
        $parametros[':tecnico_id'] = [(int) $filtros['tecnico_id'], PDO::PARAM_INT];
    }

    if ($filtros['resultado'] === 'SIN_CIERRE') {
        $condiciones[] = 'cm.id IS NULL';
    } elseif ($filtros['resultado'] !== 'TODOS') {
        $condiciones[] = 'cm.trabajo_quedo = :resultado';
        $parametros[':resultado'] = [$filtros['resultado'], PDO::PARAM_STR];
    }

    if ($filtros['programacion'] === 'SIN_PROGRAMACION') {
        $condiciones[] = 'pm.id IS NULL';
    } elseif ($filtros['programacion'] !== 'TODAS') {
        $condiciones[] = 'pm.estado = :estado_programacion';
        $parametros[':estado_programacion'] = [$filtros['programacion'], PDO::PARAM_STR];
    }

    if ($filtros['cumplimiento'] === 'SIN_ASIGNACION') {
        $condiciones[] = 'NOT EXISTS (SELECT 1 FROM solicitud_tecnicos st_ca WHERE st_ca.solicitud_id = s.id)';
    } elseif ($filtros['cumplimiento'] === 'TARDE') {
        $condiciones[] = "EXISTS (
            SELECT 1 FROM solicitud_tecnicos st_ct
            WHERE st_ct.solicitud_id = s.id
              AND st_ct.resultado_cumplimiento = 'TARDE'
        )";
    } elseif ($filtros['cumplimiento'] === 'NO_REALIZADO') {
        $condiciones[] = "EXISTS (
            SELECT 1 FROM solicitud_tecnicos st_cnr
            WHERE st_cnr.solicitud_id = s.id
              AND st_cnr.resultado_cumplimiento = 'NO_REALIZADO'
        )";
    } elseif ($filtros['cumplimiento'] === 'PENDIENTE') {
        $condiciones[] = "EXISTS (
            SELECT 1 FROM solicitud_tecnicos st_cp
            WHERE st_cp.solicitud_id = s.id
              AND st_cp.resultado_cumplimiento = 'PENDIENTE'
              AND st_cp.estado NOT IN ('RETIRADO','NO_PARTICIPO')
        )";
    } elseif ($filtros['cumplimiento'] === 'A_TIEMPO') {
        $condiciones[] = "EXISTS (
            SELECT 1 FROM solicitud_tecnicos st_cat
            WHERE st_cat.solicitud_id = s.id
              AND st_cat.resultado_cumplimiento = 'A_TIEMPO'
        )";
        $condiciones[] = "NOT EXISTS (
            SELECT 1 FROM solicitud_tecnicos st_cmal
            WHERE st_cmal.solicitud_id = s.id
              AND st_cmal.resultado_cumplimiento IN ('TARDE','NO_REALIZADO')
        )";
    } elseif ($filtros['cumplimiento'] === 'NO_APLICA') {
        $condiciones[] = "EXISTS (
            SELECT 1 FROM solicitud_tecnicos st_cna
            WHERE st_cna.solicitud_id = s.id
              AND st_cna.resultado_cumplimiento = 'NO_APLICA'
        )";
        $condiciones[] = "NOT EXISTS (
            SELECT 1 FROM solicitud_tecnicos st_cotro
            WHERE st_cotro.solicitud_id = s.id
              AND st_cotro.resultado_cumplimiento <> 'NO_APLICA'
        )";
    }

    if ($filtros['fecha_desde'] !== '') {
        $condiciones[] = 's.fecha_solicitud >= :fecha_desde';
        $parametros[':fecha_desde'] = [$filtros['fecha_desde'], PDO::PARAM_STR];
    }

    if ($filtros['fecha_hasta'] !== '') {
        $condiciones[] = 's.fecha_solicitud <= :fecha_hasta';
        $parametros[':fecha_hasta'] = [$filtros['fecha_hasta'], PDO::PARAM_STR];
    }

    return [
        'where' => $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones),
        'parametros' => $parametros,
    ];
}

function shis_catalogos(PDO $conexion): array
{
    $departamentos = $conexion->query(
        "SELECT id, nombre, activo
         FROM departamentos
         ORDER BY activo DESC, nombre ASC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $tecnicos = $conexion->query(
        "SELECT
            t.id,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS nombre,
            t.turno,
            t.especialidad,
            t.activo
         FROM tecnicos t
         ORDER BY t.activo DESC, t.nombre ASC, t.apellido_paterno ASC, t.id ASC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($departamentos as &$departamento) {
        $departamento['id'] = (int) ($departamento['id'] ?? 0);
        $departamento['activo'] = (int) ($departamento['activo'] ?? 0);
    }
    unset($departamento);

    foreach ($tecnicos as &$tecnico) {
        $tecnico['id'] = (int) ($tecnico['id'] ?? 0);
        $tecnico['activo'] = (int) ($tecnico['activo'] ?? 0);
    }
    unset($tecnico);

    return [
        'departamentos' => $departamentos,
        'tecnicos' => $tecnicos,
    ];
}

/* =========================================================================
   SEGURIDAD Y UTILIDADES
   ========================================================================= */

function shis_admin_id(): int
{
    $id = filter_var($_SESSION['usuario_id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        sm_responder_json(false, 'La sesión administrativa no es válida.', [], 401);
    }

    return (int) $id;
}

function shis_obtener_admin_activo(PDO $conexion, int $adminId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            id,
            usuario,
            TRIM(CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno)) AS nombre,
            activo
         FROM administradores
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->bindValue(':id', $adminId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($fila) || (int) ($fila['activo'] ?? 0) !== 1) {
        sm_responder_json(false, 'Tu cuenta administrativa está inactiva.', [], 403);
    }

    return [
        'id' => (int) $fila['id'],
        'usuario' => (string) $fila['usuario'],
        'nombre' => (string) $fila['nombre'],
    ];
}

function shis_vincular_parametros(PDOStatement $stmt, array $parametros): void
{
    foreach ($parametros as $nombre => $configuracion) {
        $valor = $configuracion[0] ?? null;
        $tipo = $configuracion[1] ?? PDO::PARAM_STR;
        $stmt->bindValue((string) $nombre, $valor, (int) $tipo);
    }
}

function shis_id_entrada($valor, string $campo): int
{
    $id = filter_var($valor, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        sm_responder_json(false, 'El identificador de ' . $campo . ' no es válido.', [], 422);
    }

    return (int) $id;
}

function shis_texto($valor): string
{
    if (!is_scalar($valor)) {
        return '';
    }

    $texto = trim((string) $valor);

    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto) ?? '';
}

function shis_limitar(string $texto, int $maximo): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, $maximo, 'UTF-8');
    }

    return substr($texto, 0, $maximo);
}

function shis_fecha_valida(string $fecha): bool
{
    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    $errores = DateTimeImmutable::getLastErrors();

    if ($objeto === false) {
        return false;
    }

    if (is_array($errores) && ($errores['warning_count'] > 0 || $errores['error_count'] > 0)) {
        return false;
    }

    return $objeto->format('Y-m-d') === $fecha;
}

function shis_etiqueta_tipo(string $tipo): string
{
    $mapa = [
        'CORRECTIVO_PROGRAMABLE' => 'Correctivo programable',
        'MODIFICACION_MEJORA' => 'Modificación o mejora',
        'CORRECTIVO_URGENTE' => 'Correctivo urgente',
        'RUTINARIO' => 'Mantenimiento rutinario',
    ];

    return $mapa[$tipo] ?? $tipo;
}

function shis_etiqueta_estado(string $estado): string
{
    $mapa = [
        'PENDIENTE' => 'Pendiente de revisión',
        'APROBADO' => 'Aprobada',
        'AGENDADO' => 'Agendada',
        'EN_PROCESO' => 'En proceso',
        'PAUSADO' => 'Pausada',
        'ATRASADO' => 'Atrasada',
        'TERMINADO' => 'Terminada',
        'RECHAZADO' => 'Rechazada',
        'CANCELADO' => 'Cancelada',
    ];

    return $mapa[$estado] ?? $estado;
}

function shis_etiqueta_prioridad(string $prioridad): string
{
    $mapa = [
        'BAJA' => 'Baja',
        'MEDIA' => 'Media',
        'ALTA' => 'Alta',
        'URGENTE' => 'Urgente',
    ];

    return $mapa[$prioridad] ?? $prioridad;
}

function shis_fecha_csv(string $fecha): string
{
    if ($fecha === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($fecha))->format('d/m/Y');
    } catch (Throwable $e) {
        return $fecha;
    }
}

function shis_hora_csv(string $hora): string
{
    if ($hora === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($hora))->format('H:i');
    } catch (Throwable $e) {
        return $hora;
    }
}

function shis_fecha_hora_csv(string $fechaHora): string
{
    if ($fechaHora === '') {
        return '';
    } 
 
    try {
        return (new DateTimeImmutable($fechaHora))->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return $fechaHora;
    }
}

function shis_duracion_csv(int $segundos): string
{
    $segundos = max(0, $segundos);
    $horas = intdiv($segundos, 3600);
    $minutos = intdiv($segundos % 3600, 60);
    $restantes = $segundos % 60;

    return sprintf('%02d:%02d:%02d', $horas, $minutos, $restantes);
}