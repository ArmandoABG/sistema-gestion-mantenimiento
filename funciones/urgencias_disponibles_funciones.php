<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Urgencias disponibles - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| - Exclusivo para usuarios TECNICO.
| - Las urgencias se muestran desde su publicación; no dependen de revisión.
| - Un técnico puede aceptar una urgencia directamente.
| - El técnico puede retirar su aceptación mientras no la haya iniciado.
| - El primer técnico que inicia captura el tipo de falla y la causa.
| - Al iniciar una urgencia se pausa su mantenimiento normal activo.
| - El mantenimiento pausado NO se reanuda automáticamente al terminar la
|   urgencia; se reanudará manualmente desde el módulo de actividad actual.
| - Las operaciones críticas se ejecutan dentro de transacciones y bloquean
|   la solicitud para impedir sobrecupo, dobles inicios o estados cruzados.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';
require_once __DIR__ . '/recursos_mantenimiento_servicio.php';

sm_requerir_sesion(['TECNICO'], true);

if (!($conexion instanceof PDO)) {
    sm_responder_json(
        false,
        'No fue posible conectar con la base de datos.',
        [],
        503
    );
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = $metodo === 'GET'
    ? sm_limpiar_texto($_GET['accion'] ?? 'inicial')
    : sm_limpiar_texto($_POST['accion'] ?? '');

try {
    if ($metodo === 'GET') {
        if ($accion === 'inicial') {
            urg_cargar_inicial($conexion);
        }

        if ($accion === 'detalle') {
            urg_obtener_detalle($conexion);
        }

        sm_responder_json(
            false,
            'La acción solicitada no es válida.',
            [],
            400
        );
    }

    sm_requerir_metodo('POST');
    sm_validar_csrf();

    if ($accion === 'aceptar') {
        urg_aceptar($conexion);
    }

    if ($accion === 'retirar') {
        urg_retirar_aceptacion($conexion);
    }

    if ($accion === 'iniciar') {
        urg_iniciar($conexion);
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

    error_log('[URGENCIAS DISPONIBLES][PDO] ' . $e->getMessage());

    $codigo = (string) $e->getCode();

    if ($codigo === '23000') {
        sm_responder_json(
            false,
            'La información cambió mientras realizabas la operación. Actualiza la lista e inténtalo nuevamente.',
            [],
            409
        );
    }

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la urgencia.',
        [],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[URGENCIAS DISPONIBLES] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la urgencia.',
        [],
        500
    );
}

/* =========================================================================
   CARGA Y CONSULTAS
   ========================================================================= */

function urg_cargar_inicial(PDO $conexion): void
{
    $tecnicoId = urg_tecnico_id();
    $perfil = urg_obtener_tecnico_activo($conexion, $tecnicoId);
    $configuracion = urg_configuracion($conexion);
    $compromiso = urg_obtener_compromiso_urgente($conexion, $tecnicoId);
    $actividadActual = urg_obtener_actividad_actual($conexion, $tecnicoId);
    $urgencias = urg_listar_urgencias(
        $conexion,
        $tecnicoId,
        (int) $configuracion['limite_tecnicos'],
        $compromiso
    );

    sm_responder_json(
        true,
        'Urgencias cargadas correctamente.',
        [
            'perfil' => $perfil,
            'configuracion' => $configuracion,
            'catalogos' => urg_catalogos_diagnostico($conexion),
            'compromiso_urgente' => $compromiso,
            'actividad_actual' => $actividadActual,
            'resumen' => urg_resumen_desde_lista($urgencias),
            'urgencias' => $urgencias,
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function urg_obtener_detalle(PDO $conexion): void
{
    $tecnicoId = urg_tecnico_id();
    urg_obtener_tecnico_activo($conexion, $tecnicoId);

    $solicitudId = urg_id_entrada($_GET['id'] ?? null, 'id');
    $configuracion = urg_configuracion($conexion);
    $compromiso = urg_obtener_compromiso_urgente($conexion, $tecnicoId);
    $actividadActual = urg_obtener_actividad_actual($conexion, $tecnicoId);

    $detalle = urg_consultar_detalle(
        $conexion,
        $solicitudId,
        $tecnicoId,
        (int) $configuracion['limite_tecnicos'],
        $compromiso
    );

    if (!$detalle) {
        sm_responder_json(
            false,
            'La urgencia no existe o ya no está disponible.',
            [],
            404
        );
    }

    $participantes = urg_listar_participantes($conexion, $solicitudId);

    sm_responder_json(
        true,
        'Detalle de urgencia cargado.',
        [
            'urgencia' => $detalle,
            'participantes' => $participantes,
            'actividad_actual' => $actividadActual,
            'compromiso_urgente' => $compromiso,
            'configuracion' => $configuracion,
            'catalogos' => urg_catalogos_diagnostico($conexion),
        ]
    );
}

function urg_listar_urgencias(
    PDO $conexion,
    int $tecnicoId,
    int $limiteGlobal,
    ?array $compromiso
): array {
    $sql = "
        SELECT
            s.id AS solicitud_id,
            s.tipo_solicitud,
            s.equipo_id AS solicitud_equipo_id,
            s.folio,
            s.estado,
            s.fecha_solicitud,
            s.hora_solicitud,
            s.fecha_registro,
            s.descripcion_solicitud,
            s.descripcion_falla,
            s.impacto_operacion,
            s.trabajo_peligroso,
            s.detalle_trabajo_peligroso,
            s.nivel_riesgo,
            s.requiere_paro_equipo,
            LEAST(
                10,
                GREATEST(1, s.cupo_tecnicos_urgente),
                :limite_global_lista
            ) AS limite_tecnicos,
            e.codigo_equipo,
            e.nombre_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso,
            tf.nombre AS tipo_falla,
            ca.nombre AS causa_averia,
            COALESCE(conteo.total_participantes, 0) AS tecnicos_aceptaron,
            GREATEST(
                0,
                LEAST(
                    10,
                    GREATEST(1, s.cupo_tecnicos_urgente),
                    :limite_global_lugares
                ) - COALESCE(conteo.total_participantes, 0)
            ) AS lugares_disponibles,
            propia.id AS solicitud_tecnico_id,
            propia.estado AS estado_participacion,
            propia.fecha_aceptacion,
            propia.fecha_retiro,
            propia.riesgo_urgente_confirmado_tecnico,
            propia.fecha_confirmacion_riesgo_urgente,
            propia.detalle_riesgo_urgente_confirmado,
            em.id AS ejecucion_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.total_segundos_activos,
            em.total_segundos_pausa,
            CASE WHEN s.revisado_por_admin_id IS NULL THEN 0 ELSE 1 END AS revisada_admin
        FROM solicitudes s
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
        LEFT JOIN (
            SELECT
                stc.solicitud_id,
                COUNT(*) AS total_participantes
            FROM solicitud_tecnicos stc
            WHERE stc.origen = 'ACEPTACION_URGENTE'
              AND stc.activo = 1
              AND stc.estado IN ('ACEPTADO','EN_PROCESO','PAUSADO')
            GROUP BY stc.solicitud_id
        ) conteo
               ON conteo.solicitud_id = s.id
        LEFT JOIN solicitud_tecnicos propia
               ON propia.solicitud_id = s.id
              AND propia.tecnico_id = :tecnico_id_lista
              AND propia.origen = 'ACEPTACION_URGENTE'
              AND propia.activo = 1
              AND propia.estado IN ('ACEPTADO','EN_PROCESO','PAUSADO')
        LEFT JOIN ejecuciones_mantenimiento em
               ON em.solicitud_tecnico_id = propia.id
        WHERE s.tipo_solicitud = 'CORRECTIVO_URGENTE'
          AND s.activo = 1
          AND s.estado IN ('AGENDADO','EN_PROCESO','PAUSADO','ATRASADO')
          AND (
              propia.id IS NOT NULL
              OR COALESCE(conteo.total_participantes, 0) < LEAST(
                    10,
                    GREATEST(1, s.cupo_tecnicos_urgente),
                    :limite_global_filtro
              )
          )
        ORDER BY
            CASE WHEN propia.id IS NOT NULL THEN 0 ELSE 1 END,
            CASE WHEN s.estado = 'EN_PROCESO' THEN 0 ELSE 1 END,
            FIELD(s.nivel_riesgo, 'ALTO','MEDIO','BAJO'),
            s.fecha_solicitud ASC,
            s.hora_solicitud ASC,
            s.id ASC
        LIMIT 200
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':limite_global_lista', $limiteGlobal, PDO::PARAM_INT);
    $stmt->bindValue(':limite_global_lugares', $limiteGlobal, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_id_lista', $tecnicoId, PDO::PARAM_INT);
    $stmt->bindValue(':limite_global_filtro', $limiteGlobal, PDO::PARAM_INT);
    $stmt->execute();

    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    rsm_adjuntar_recursos_recomendados(
        $conexion,
        $filas,
        'solicitud_equipo_id',
        true
    );

    foreach ($filas as &$fila) {
        urg_agregar_permisos_fila($fila, $tecnicoId, $compromiso);
    }
    unset($fila);

    return $filas;
}

function urg_consultar_detalle(
    PDO $conexion,
    int $solicitudId,
    int $tecnicoId,
    int $limiteGlobal,
    ?array $compromiso
): ?array {
    $sql = "
        SELECT
            s.id AS solicitud_id,
            s.tipo_solicitud,
            s.equipo_id AS solicitud_equipo_id,
            s.folio,
            s.estado,
            s.prioridad,
            s.fecha_solicitud,
            s.hora_solicitud,
            s.fecha_registro,
            s.descripcion_solicitud,
            s.tipo_falla_id,
            s.causa_averia_id,
            s.descripcion_falla,
            s.causa_desconocida_descripcion,
            s.impacto_operacion,
            s.observaciones_solicitante,
            s.trabajo_peligroso,
            s.detalle_trabajo_peligroso,
            s.nivel_riesgo,
            s.requiere_paro_equipo,
            s.revisado_por_admin_id,
            s.fecha_revision,
            LEAST(
                10,
                GREATEST(1, s.cupo_tecnicos_urgente),
                :limite_global_detalle
            ) AS limite_tecnicos,
            e.codigo_equipo,
            e.nombre_equipo,
            e.descripcion AS descripcion_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso,
            tf.nombre AS tipo_falla,
            ca.nombre AS causa_averia,
            CASE
                WHEN s.solicitante_id IS NOT NULL THEN TRIM(CONCAT_WS(
                    ' ', sol.nombre, sol.apellido_paterno, sol.apellido_materno
                ))
                WHEN s.administrador_solicitante_id IS NOT NULL THEN TRIM(CONCAT_WS(
                    ' ', adm.nombre, adm.apellido_paterno, adm.apellido_materno
                ))
                ELSE 'Sistema'
            END AS solicitante,
            COALESCE(conteo.total_participantes, 0) AS tecnicos_aceptaron,
            GREATEST(
                0,
                LEAST(
                    10,
                    GREATEST(1, s.cupo_tecnicos_urgente),
                    :limite_global_detalle_lugares
                ) - COALESCE(conteo.total_participantes, 0)
            ) AS lugares_disponibles,
            propia.id AS solicitud_tecnico_id,
            propia.estado AS estado_participacion,
            propia.fecha_aceptacion,
            propia.fecha_retiro,
            propia.riesgo_urgente_confirmado_tecnico,
            propia.fecha_confirmacion_riesgo_urgente,
            propia.detalle_riesgo_urgente_confirmado,
            em.id AS ejecucion_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.total_segundos_activos,
            em.total_segundos_pausa
        FROM solicitudes s
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
        LEFT JOIN (
            SELECT
                stc.solicitud_id,
                COUNT(*) AS total_participantes
            FROM solicitud_tecnicos stc
            WHERE stc.origen = 'ACEPTACION_URGENTE'
              AND stc.activo = 1
              AND stc.estado IN ('ACEPTADO','EN_PROCESO','PAUSADO')
            GROUP BY stc.solicitud_id
        ) conteo
               ON conteo.solicitud_id = s.id
        LEFT JOIN solicitud_tecnicos propia
               ON propia.solicitud_id = s.id
              AND propia.tecnico_id = :tecnico_id_detalle
              AND propia.origen = 'ACEPTACION_URGENTE'
              AND propia.activo = 1
              AND propia.estado IN ('ACEPTADO','EN_PROCESO','PAUSADO')
        LEFT JOIN ejecuciones_mantenimiento em
               ON em.solicitud_tecnico_id = propia.id
        WHERE s.id = :solicitud_id_detalle
          AND s.tipo_solicitud = 'CORRECTIVO_URGENTE'
          AND s.activo = 1
          AND s.estado IN ('AGENDADO','EN_PROCESO','PAUSADO','ATRASADO')
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':limite_global_detalle', $limiteGlobal, PDO::PARAM_INT);
    $stmt->bindValue(':limite_global_detalle_lugares', $limiteGlobal, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_id_detalle', $tecnicoId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id_detalle', $solicitudId, PDO::PARAM_INT);
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

    urg_agregar_permisos_fila($fila, $tecnicoId, $compromiso);

    return $fila;
}

function urg_listar_participantes(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            st.id AS solicitud_tecnico_id,
            st.tecnico_id,
            st.estado AS estado_participacion,
            st.fecha_aceptacion,
            st.fecha_retiro,
            TRIM(CONCAT_WS(
                ' ', t.nombre, t.apellido_paterno, t.apellido_materno
            )) AS tecnico,
            t.turno,
            t.especialidad,
            em.id AS ejecucion_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.fecha_hora_fin,
            em.total_segundos_activos,
            em.total_segundos_pausa
         FROM solicitud_tecnicos st
         INNER JOIN tecnicos t
                 ON t.id = st.tecnico_id
         LEFT JOIN ejecuciones_mantenimiento em
                ON em.solicitud_tecnico_id = st.id
         WHERE st.solicitud_id = :solicitud_id
           AND st.origen = 'ACEPTACION_URGENTE'
           AND st.activo = 1
           AND st.estado IN ('ACEPTADO','EN_PROCESO','PAUSADO')
         ORDER BY
             FIELD(st.estado, 'EN_PROCESO','PAUSADO','ACEPTADO'),
             st.fecha_aceptacion ASC,
             st.id ASC"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function urg_agregar_permisos_fila(
    array &$fila,
    int $tecnicoId,
    ?array $compromiso
): void {
    $participacionId = (int) ($fila['solicitud_tecnico_id'] ?? 0);
    $estadoParticipacion = strtoupper((string) ($fila['estado_participacion'] ?? ''));
    $estadoEjecucion = strtoupper((string) ($fila['estado_ejecucion'] ?? ''));
    $lugares = max(0, (int) ($fila['lugares_disponibles'] ?? 0));
    $solicitudId = (int) ($fila['solicitud_id'] ?? 0);

    $tieneOtraUrgencia = $compromiso
        && (int) ($compromiso['solicitud_id'] ?? 0) !== $solicitudId;

    $fila['es_mia'] = $participacionId > 0 ? 1 : 0;
    $fila['bloqueada_por_otra_urgencia'] = $tieneOtraUrgencia ? 1 : 0;
    $fila['puede_aceptar'] = (
        $participacionId === 0
        && $lugares > 0
        && !$tieneOtraUrgencia
    ) ? 1 : 0;

    $fila['puede_retirar'] = (
        $participacionId > 0
        && $estadoParticipacion === 'ACEPTADO'
        && $estadoEjecucion === ''
    ) ? 1 : 0;

    $fila['puede_iniciar'] = (
        $participacionId > 0
        && $estadoParticipacion === 'ACEPTADO'
        && $estadoEjecucion === ''
        && !$tieneOtraUrgencia
    ) ? 1 : 0;

    $fila['puede_abrir_actividad'] = in_array(
        $estadoParticipacion,
        ['EN_PROCESO','PAUSADO'],
        true
    ) ? 1 : 0;

    if ($tieneOtraUrgencia) {
        $fila['motivo_bloqueo'] = 'Ya tienes la urgencia '
            . (string) ($compromiso['folio'] ?? '')
            . ' aceptada o activa.';
    } elseif ($participacionId === 0 && $lugares < 1) {
        $fila['motivo_bloqueo'] = 'La urgencia ya alcanzó el límite de técnicos.';
    } else {
        $fila['motivo_bloqueo'] = '';
    }

    $esPeligrosa = (int) ($fila['trabajo_peligroso'] ?? 0) === 1;
    $riesgoConfirmado = (int) ($fila['riesgo_urgente_confirmado_tecnico'] ?? 0) === 1;

    $fila['requiere_confirmacion_riesgo'] = (
        $esPeligrosa
        && !$riesgoConfirmado
    ) ? 1 : 0;
    $fila['riesgo_confirmado_por_tecnico'] = $riesgoConfirmado ? 1 : 0;

    $fila['tecnico_actual_id'] = $tecnicoId;
}

function urg_resumen_desde_lista(array $urgencias): array
{
    $resumen = [
        'disponibles' => 0,
        'mias_aceptadas' => 0,
        'mias_en_proceso' => 0,
        'riesgo_alto' => 0,
        'total_abiertas' => count($urgencias),
    ];

    foreach ($urgencias as $urgencia) {
        $esMia = (int) ($urgencia['es_mia'] ?? 0) === 1;
        $estadoParticipacion = strtoupper((string) ($urgencia['estado_participacion'] ?? ''));

        if (!$esMia && (int) ($urgencia['lugares_disponibles'] ?? 0) > 0) {
            $resumen['disponibles']++;
        }

        if ($esMia && $estadoParticipacion === 'ACEPTADO') {
            $resumen['mias_aceptadas']++;
        }

        if ($esMia && in_array($estadoParticipacion, ['EN_PROCESO','PAUSADO'], true)) {
            $resumen['mias_en_proceso']++;
        }

        if (strtoupper((string) ($urgencia['nivel_riesgo'] ?? '')) === 'ALTO') {
            $resumen['riesgo_alto']++;
        }
    }

    return $resumen;
}

/* =========================================================================
   ACEPTAR, RETIRAR E INICIAR
   ========================================================================= */

function urg_aceptar(PDO $conexion): void
{
    $tecnicoId = urg_tecnico_id();
    $solicitudId = urg_id_entrada($_POST['solicitud_id'] ?? null, 'solicitud_id');
    $confirmacionRiesgo = urg_confirmacion_riesgo_recibida(
        $_POST['confirmacion_riesgo'] ?? '0'
    );

    urg_obtener_tecnico_activo($conexion, $tecnicoId);

    $conexion->beginTransaction();

    $solicitud = urg_bloquear_solicitud($conexion, $solicitudId);
    urg_validar_solicitud_operativa($conexion, $solicitud);

    $esPeligrosa = (int) ($solicitud['trabajo_peligroso'] ?? 0) === 1;
    $detalleRiesgo = urg_detalle_riesgo_mostrado($solicitud);

    if ($esPeligrosa && $confirmacionRiesgo !== 1) {
        urg_cancelar_transaccion(
            $conexion,
            'Esta urgencia está marcada como trabajo peligroso. Confirma que estás enterado antes de unirte.',
            422
        );
    }

    $existente = urg_bloquear_participacion_activa(
        $conexion,
        $solicitudId,
        $tecnicoId
    );

    if ($existente) {
        $confirmacionRegistrada = (int) (
            $existente['riesgo_urgente_confirmado_tecnico'] ?? 0
        ) === 1;

        if ($esPeligrosa && !$confirmacionRegistrada) {
            $stmtConfirmacion = $conexion->prepare(
                "UPDATE solicitud_tecnicos
                 SET riesgo_urgente_confirmado_tecnico = 1,
                     fecha_confirmacion_riesgo_urgente = NOW(),
                     detalle_riesgo_urgente_confirmado = :detalle
                 WHERE id = :id
                   AND activo = 1"
            );
            $stmtConfirmacion->bindValue(':detalle', $detalleRiesgo, PDO::PARAM_STR);
            $stmtConfirmacion->bindValue(':id', (int) $existente['id'], PDO::PARAM_INT);
            $stmtConfirmacion->execute();

            if ($stmtConfirmacion->rowCount() !== 1) {
                urg_cancelar_transaccion(
                    $conexion,
                    'No fue posible registrar tu confirmación de seguridad. Actualiza la pantalla e inténtalo nuevamente.',
                    409
                );
            }

            $nombreTecnico = urg_nombre_tecnico($conexion, $tecnicoId);

            urg_historial(
                $conexion,
                $solicitudId,
                (int) $existente['id'],
                'OTRO',
                (string) $solicitud['estado'],
                (string) $solicitud['estado'],
                'TECNICO',
                $tecnicoId,
                'El técnico ' . $nombreTecnico
                    . ' confirmó que está enterado del trabajo peligroso: '
                    . $detalleRiesgo
            );

            urg_movimiento(
                $conexion,
                $tecnicoId,
                'CONFIRMAR_RIESGO_URGENCIA',
                'Urgencias disponibles',
                'El técnico confirmó que está enterado del riesgo de la urgencia '
                    . (string) $solicitud['folio'] . '.',
                'solicitud_tecnicos',
                (int) $existente['id']
            );
        }

        $conexion->commit();

        sm_responder_json(
            true,
            $esPeligrosa && !$confirmacionRegistrada
                ? 'Confirmaste que estás enterado del trabajo peligroso.'
                : 'Ya habías aceptado esta urgencia.',
            [
                'solicitud_id' => $solicitudId,
                'solicitud_tecnico_id' => (int) $existente['id'],
                'estado_participacion' => (string) $existente['estado'],
                'riesgo_confirmado' => $esPeligrosa ? 1 : 0,
                'ya_existia' => true,
            ]
        );
    }

    $otroCompromiso = urg_bloquear_otro_compromiso(
        $conexion,
        $tecnicoId,
        $solicitudId
    );

    if ($otroCompromiso) {
        urg_cancelar_transaccion(
            $conexion,
            'Ya tienes la urgencia '
                . (string) $otroCompromiso['folio']
                . ' aceptada o activa. Retírate de ella o termínala antes de aceptar otra.',
            409
        );
    }

    $limiteGlobal = urg_limite_global($conexion);
    $limiteSolicitud = min(
        10,
        $limiteGlobal,
        max(1, (int) ($solicitud['cupo_tecnicos_urgente'] ?? 10))
    );

    $ocupados = urg_contar_participantes_activos($conexion, $solicitudId);

    if ($ocupados >= $limiteSolicitud) {
        urg_cancelar_transaccion(
            $conexion,
            'La urgencia alcanzó el límite de técnicos disponibles.',
            409
        );
    }

    $stmt = $conexion->prepare(
        "INSERT INTO solicitud_tecnicos (
            solicitud_id,
            programacion_id,
            tecnico_id,
            origen,
            estado,
            asignado_por_admin_id,
            fecha_asignacion,
            fecha_aceptacion,
            fecha_retiro,
            riesgo_urgente_confirmado_tecnico,
            fecha_confirmacion_riesgo_urgente,
            detalle_riesgo_urgente_confirmado,
            resultado_cumplimiento,
            activo,
            activo_token
         ) VALUES (
            :solicitud_id,
            NULL,
            :tecnico_id,
            'ACEPTACION_URGENTE',
            'ACEPTADO',
            NULL,
            NOW(),
            NOW(),
            NULL,
            :riesgo_confirmado,
            :fecha_confirmacion,
            :detalle_confirmado,
            'NO_APLICA',
            1,
            1
         )"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->bindValue(':riesgo_confirmado', $esPeligrosa ? 1 : 0, PDO::PARAM_INT);

    if ($esPeligrosa) {
        $stmt->bindValue(':fecha_confirmacion', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':detalle_confirmado', $detalleRiesgo, PDO::PARAM_STR);
    } else {
        $stmt->bindValue(':fecha_confirmacion', null, PDO::PARAM_NULL);
        $stmt->bindValue(':detalle_confirmado', null, PDO::PARAM_NULL);
    }

    $stmt->execute();

    $participacionId = (int) $conexion->lastInsertId();
    $nombreTecnico = urg_nombre_tecnico($conexion, $tecnicoId);
    $textoAceptacion = 'El técnico ' . $nombreTecnico
        . ' aceptó directamente la urgencia.';

    if ($esPeligrosa) {
        $textoAceptacion .= ' Confirmó que está enterado del trabajo peligroso: '
            . $detalleRiesgo;
    }

    urg_historial(
        $conexion,
        $solicitudId,
        $participacionId,
        'URGENTE_ACEPTADA',
        (string) $solicitud['estado'],
        (string) $solicitud['estado'],
        'TECNICO',
        $tecnicoId,
        $textoAceptacion
    );

    urg_movimiento(
        $conexion,
        $tecnicoId,
        'ACEPTAR_URGENCIA',
        'Urgencias disponibles',
        'El técnico aceptó la urgencia ' . (string) $solicitud['folio']
            . ($esPeligrosa ? ' y confirmó que está enterado del riesgo.' : '.'),
        'solicitud_tecnicos',
        $participacionId
    );

    urg_notificar_responsables(
        $conexion,
        $solicitud,
        $solicitudId,
        'Técnico incorporado a urgencia',
        $nombreTecnico . ' aceptó la urgencia ' . (string) $solicitud['folio'] . '.',
        'URGENTE'
    );

    urg_marcar_notificaciones_tecnico_leidas($conexion, $tecnicoId, $solicitudId);

    $conexion->commit();

    sm_responder_json(
        true,
        $esPeligrosa
            ? 'Aceptaste la urgencia y confirmaste que estás enterado del trabajo peligroso.'
            : 'Aceptaste la urgencia correctamente.',
        [
            'solicitud_id' => $solicitudId,
            'solicitud_tecnico_id' => $participacionId,
            'estado_participacion' => 'ACEPTADO',
            'riesgo_confirmado' => $esPeligrosa ? 1 : 0,
            'lugares_restantes' => max(0, $limiteSolicitud - ($ocupados + 1)),
        ]
    );
}

function urg_retirar_aceptacion(PDO $conexion): void
{
    $tecnicoId = urg_tecnico_id();
    $solicitudId = urg_id_entrada($_POST['solicitud_id'] ?? null, 'solicitud_id');
    urg_obtener_tecnico_activo($conexion, $tecnicoId);

    $conexion->beginTransaction();

    $solicitud = urg_bloquear_solicitud($conexion, $solicitudId);
    urg_validar_solicitud_operativa($conexion, $solicitud);

    $participacion = urg_bloquear_participacion_activa(
        $conexion,
        $solicitudId,
        $tecnicoId
    );

    if (!$participacion) {
        urg_cancelar_transaccion(
            $conexion,
            'No tienes una aceptación activa en esta urgencia.',
            409
        );
    }

    if (strtoupper((string) $participacion['estado']) !== 'ACEPTADO') {
        urg_cancelar_transaccion(
            $conexion,
            'La urgencia ya fue iniciada. Ya no puedes liberar el lugar desde esta pantalla.',
            409
        );
    }

    $stmtEjecucion = $conexion->prepare(
        "SELECT id, estado
         FROM ejecuciones_mantenimiento
         WHERE solicitud_tecnico_id = :participacion_id
         LIMIT 1
         FOR UPDATE"
    );
    $stmtEjecucion->bindValue(
        ':participacion_id',
        (int) $participacion['id'],
        PDO::PARAM_INT
    );
    $stmtEjecucion->execute();
    $ejecucion = $stmtEjecucion->fetch(PDO::FETCH_ASSOC);

    if (is_array($ejecucion)) {
        urg_cancelar_transaccion(
            $conexion,
            'La participación ya tiene una ejecución registrada y no puede retirarse como una simple aceptación.',
            409
        );
    }

    $stmt = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET estado = 'RETIRADO',
             fecha_retiro = NOW(),
             activo = 0,
             activo_token = NULL
         WHERE id = :id
           AND activo = 1
           AND estado = 'ACEPTADO'"
    );
    $stmt->bindValue(':id', (int) $participacion['id'], PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        urg_cancelar_transaccion(
            $conexion,
            'La aceptación cambió mientras realizabas la operación.',
            409
        );
    }

    $nombreTecnico = urg_nombre_tecnico($conexion, $tecnicoId);

    urg_historial(
        $conexion,
        $solicitudId,
        (int) $participacion['id'],
        'TECNICO_RETIRADO',
        (string) $solicitud['estado'],
        (string) $solicitud['estado'],
        'TECNICO',
        $tecnicoId,
        'El técnico ' . $nombreTecnico . ' retiró su aceptación antes de iniciar la urgencia.'
    );

    urg_movimiento(
        $conexion,
        $tecnicoId,
        'RETIRAR_ACEPTACION_URGENCIA',
        'Urgencias disponibles',
        'El técnico liberó su lugar en la urgencia ' . (string) $solicitud['folio'] . '.',
        'solicitud_tecnicos',
        (int) $participacion['id']
    );

    urg_notificar_responsables(
        $conexion,
        $solicitud,
        $solicitudId,
        'Técnico retirado de urgencia',
        $nombreTecnico . ' liberó su lugar en la urgencia ' . (string) $solicitud['folio'] . ' antes de iniciarla.',
        'WARNING'
    );

    $conexion->commit();

    sm_responder_json(
        true,
        'Liberaste tu lugar en la urgencia.',
        [
            'solicitud_id' => $solicitudId,
            'estado_participacion' => 'RETIRADO',
        ]
    );
}

function urg_iniciar(PDO $conexion): void
{
    $tecnicoId = urg_tecnico_id();
    $solicitudId = urg_id_entrada($_POST['solicitud_id'] ?? null, 'solicitud_id');
    urg_obtener_tecnico_activo($conexion, $tecnicoId);

    $conexion->beginTransaction();

    $solicitud = urg_bloquear_solicitud($conexion, $solicitudId);
    urg_validar_solicitud_operativa($conexion, $solicitud);

    $participacion = urg_bloquear_participacion_activa(
        $conexion,
        $solicitudId,
        $tecnicoId
    );

    if (!$participacion) {
        urg_cancelar_transaccion(
            $conexion,
            'Primero debes aceptar esta urgencia.',
            409
        );
    }

    $estadoParticipacion = strtoupper((string) $participacion['estado']);

    if ($estadoParticipacion === 'EN_PROCESO') {
        $ejecucionActual = urg_ejecucion_por_participacion(
            $conexion,
            (int) $participacion['id'],
            true
        );

        $conexion->commit();

        sm_responder_json(
            true,
            'La urgencia ya está iniciada para tu usuario.',
            [
                'solicitud_id' => $solicitudId,
                'ejecucion_id' => (int) ($ejecucionActual['id'] ?? 0),
                'ya_iniciada' => true,
                'redirect' => 'mantenimiento_activo.php?ejecucion_id=' . (int) ($ejecucionActual['id'] ?? 0),
            ]
        );
    }

    if ($estadoParticipacion === 'PAUSADO') {
        urg_cancelar_transaccion(
            $conexion,
            'Tu participación está pausada. Debes reanudarla desde Actividad actual.',
            409
        );
    }

    if ($estadoParticipacion !== 'ACEPTADO') {
        urg_cancelar_transaccion(
            $conexion,
            'Tu participación no se encuentra disponible para iniciar.',
            409
        );
    }

    if (
        (int) ($solicitud['trabajo_peligroso'] ?? 0) === 1
        && (int) ($participacion['riesgo_urgente_confirmado_tecnico'] ?? 0) !== 1
    ) {
        urg_cancelar_transaccion(
            $conexion,
            'Antes de iniciar debes confirmar que estás enterado del trabajo peligroso.',
            409
        );
    }

    $cierre = urg_cierre_existente($conexion, $solicitudId, true);

    if ($cierre) {
        urg_cancelar_transaccion(
            $conexion,
            'La urgencia ya fue cerrada por otro usuario.',
            409
        );
    }

    $ejecucionUrgente = urg_ejecucion_por_participacion(
        $conexion,
        (int) $participacion['id'],
        true
    );

    if ($ejecucionUrgente) {
        $estadoEjecucion = strtoupper((string) $ejecucionUrgente['estado']);

        if ($estadoEjecucion === 'EN_PROCESO') {
            $conexion->commit();

            sm_responder_json(
                true,
                'La urgencia ya estaba iniciada.',
                [
                    'solicitud_id' => $solicitudId,
                    'ejecucion_id' => (int) $ejecucionUrgente['id'],
                    'ya_iniciada' => true,
                    'redirect' => 'mantenimiento_activo.php?ejecucion_id=' . (int) $ejecucionUrgente['id'],
                ]
            );
        }

        if ($estadoEjecucion !== 'PENDIENTE') {
            urg_cancelar_transaccion(
                $conexion,
                'La ejecución existente no puede iniciarse desde esta pantalla.',
                409
            );
        }
    }

    $activas = urg_bloquear_ejecuciones_activas_tecnico($conexion, $tecnicoId);

    if (count($activas) > 1) {
        urg_cancelar_transaccion(
            $conexion,
            'Se detectaron varias actividades activas en tu cuenta. Un administrador debe revisar la información antes de continuar.',
            409
        );
    }

    $mantenimientoPausado = null;

    if (count($activas) === 1) {
        $actual = $activas[0];

        if ((int) $actual['solicitud_id'] === $solicitudId) {
            $conexion->commit();

            sm_responder_json(
                true,
                'La urgencia ya se encuentra en proceso.',
                [
                    'solicitud_id' => $solicitudId,
                    'ejecucion_id' => (int) $actual['ejecucion_id'],
                    'ya_iniciada' => true,
                    'redirect' => 'mantenimiento_activo.php?ejecucion_id=' . (int) $actual['ejecucion_id'],
                ]
            );
        }

        if (strtoupper((string) $actual['tipo_solicitud']) === 'CORRECTIVO_URGENTE') {
            urg_cancelar_transaccion(
                $conexion,
                'Ya estás atendiendo la urgencia '
                    . (string) $actual['folio']
                    . '. Debes terminarla o pausarla antes de iniciar otra.',
                409
            );
        }

        if (!urg_pausa_automatica_habilitada($conexion)) {
            urg_cancelar_transaccion(
                $conexion,
                'Tienes un mantenimiento en proceso. La pausa automática por urgencia no está habilitada.',
                409
            );
        }

        $mantenimientoPausado = urg_pausar_mantenimiento_anterior(
            $conexion,
            $actual,
            $solicitud,
            $tecnicoId
        );
    }

    $diagnostico = urg_completar_diagnostico_inicial(
        $conexion,
        $solicitud,
        $solicitudId,
        (int) $participacion['id'],
        $tecnicoId
    );

    $ahora = date('Y-m-d H:i:s');

    if ($ejecucionUrgente) {
        $stmt = $conexion->prepare(
            "UPDATE ejecuciones_mantenimiento
             SET estado = 'EN_PROCESO',
                 fecha_hora_inicio = COALESCE(fecha_hora_inicio, :ahora_inicio),
                 fecha_ultima_reanudacion = :ahora_reanudacion,
                 fecha_hora_inicio_original = COALESCE(fecha_hora_inicio_original, :ahora_original),
                 fecha_hora_fin = NULL,
                 en_proceso_token = 1,
                 iniciada_por_tipo = 'TECNICO',
                 iniciada_por_id = :tecnico_id
             WHERE id = :ejecucion_id
               AND estado = 'PENDIENTE'"
        );
        $stmt->bindValue(':ahora_inicio', $ahora, PDO::PARAM_STR);
        $stmt->bindValue(':ahora_reanudacion', $ahora, PDO::PARAM_STR);
        $stmt->bindValue(':ahora_original', $ahora, PDO::PARAM_STR);
        $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
        $stmt->bindValue(':ejecucion_id', (int) $ejecucionUrgente['id'], PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() !== 1) {
            urg_cancelar_transaccion(
                $conexion,
                'La ejecución cambió mientras intentabas iniciarla.',
                409
            );
        }

        $ejecucionId = (int) $ejecucionUrgente['id'];
    } else {
        $stmt = $conexion->prepare(
            "INSERT INTO ejecuciones_mantenimiento (
                solicitud_id,
                solicitud_tecnico_id,
                tecnico_id,
                estado,
                fecha_hora_inicio,
                fecha_hora_fin,
                fecha_ultima_reanudacion,
                fecha_hora_inicio_original,
                fecha_hora_fin_original,
                total_segundos_pausa,
                total_segundos_activos,
                en_proceso_token,
                iniciada_por_tipo,
                iniciada_por_id
             ) VALUES (
                :solicitud_id,
                :participacion_id,
                :tecnico_id,
                'EN_PROCESO',
                :fecha_inicio,
                NULL,
                :fecha_reanudacion,
                :fecha_original,
                NULL,
                0,
                0,
                1,
                'TECNICO',
                :iniciada_por_id
             )"
        );
        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(':participacion_id', (int) $participacion['id'], PDO::PARAM_INT);
        $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_inicio', $ahora, PDO::PARAM_STR);
        $stmt->bindValue(':fecha_reanudacion', $ahora, PDO::PARAM_STR);
        $stmt->bindValue(':fecha_original', $ahora, PDO::PARAM_STR);
        $stmt->bindValue(':iniciada_por_id', $tecnicoId, PDO::PARAM_INT);
        $stmt->execute();

        $ejecucionId = (int) $conexion->lastInsertId();
    }

    $stmtParticipacion = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET estado = 'EN_PROCESO'
         WHERE id = :id
           AND activo = 1
           AND estado = 'ACEPTADO'"
    );
    $stmtParticipacion->bindValue(':id', (int) $participacion['id'], PDO::PARAM_INT);
    $stmtParticipacion->execute();

    if ($stmtParticipacion->rowCount() !== 1) {
        urg_cancelar_transaccion(
            $conexion,
            'Tu participación cambió mientras intentabas iniciar.',
            409
        );
    }

    $estadoAnterior = (string) $solicitud['estado'];

    if ($estadoAnterior !== 'EN_PROCESO') {
        $stmtSolicitud = $conexion->prepare(
            "UPDATE solicitudes
             SET estado = 'EN_PROCESO'
             WHERE id = :id
               AND activo = 1
               AND estado IN ('AGENDADO','PAUSADO','ATRASADO')"
        );
        $stmtSolicitud->bindValue(':id', $solicitudId, PDO::PARAM_INT);
        $stmtSolicitud->execute();

        if ($stmtSolicitud->rowCount() !== 1) {
            urg_cancelar_transaccion(
                $conexion,
                'La urgencia cambió de estado mientras intentabas iniciarla.',
                409
            );
        }
    }

    $nombreTecnico = urg_nombre_tecnico($conexion, $tecnicoId);

    urg_historial(
        $conexion,
        $solicitudId,
        (int) $participacion['id'],
        'INICIADA',
        $estadoAnterior,
        'EN_PROCESO',
        'TECNICO',
        $tecnicoId,
        'El técnico ' . $nombreTecnico . ' inició su participación en la urgencia.'
    );

    urg_movimiento(
        $conexion,
        $tecnicoId,
        'INICIAR_URGENCIA',
        'Urgencias disponibles',
        'El técnico inició la urgencia ' . (string) $solicitud['folio'] . '.',
        'ejecuciones_mantenimiento',
        $ejecucionId
    );

    urg_notificar_responsables(
        $conexion,
        $solicitud,
        $solicitudId,
        'Urgencia en atención',
        $nombreTecnico . ' inició la atención de la urgencia ' . (string) $solicitud['folio'] . '.',
        'URGENTE',
        $ejecucionId
    );

    urg_notificar_otros_participantes(
        $conexion,
        $solicitudId,
        $tecnicoId,
        $ejecucionId,
        'Participante inició la urgencia',
        $nombreTecnico . ' comenzó a trabajar en la urgencia ' . (string) $solicitud['folio'] . '.'
    );

    urg_marcar_notificaciones_tecnico_leidas($conexion, $tecnicoId, $solicitudId);

    $conexion->commit();

    sm_responder_json(
        true,
        'La urgencia fue iniciada correctamente.',
        [
            'solicitud_id' => $solicitudId,
            'ejecucion_id' => $ejecucionId,
            'estado' => 'EN_PROCESO',
            'diagnostico_capturado' => !empty($diagnostico['capturado']),
            'diagnostico' => $diagnostico,
            'mantenimiento_pausado' => $mantenimientoPausado,
            'reanudar_manual' => $mantenimientoPausado !== null,
            'redirect' => 'mantenimiento_activo.php?ejecucion_id=' . $ejecucionId,
        ]
    );
}

function urg_pausar_mantenimiento_anterior(
    PDO $conexion,
    array $actual,
    array $urgencia,
    int $tecnicoId
): array {
    $ejecucionId = (int) $actual['ejecucion_id'];
    $solicitudAnteriorId = (int) $actual['solicitud_id'];
    $participacionAnteriorId = (int) $actual['solicitud_tecnico_id'];

    $stmtPausa = $conexion->prepare(
        "SELECT id
         FROM pausas_ejecucion
         WHERE ejecucion_id = :ejecucion_id
           AND pausa_abierta_token = 1
         LIMIT 1
         FOR UPDATE"
    );
    $stmtPausa->bindValue(':ejecucion_id', $ejecucionId, PDO::PARAM_INT);
    $stmtPausa->execute();

    if ($stmtPausa->fetch(PDO::FETCH_ASSOC)) {
        urg_cancelar_transaccion(
            $conexion,
            'La actividad actual ya tiene una pausa abierta. Revisa su estado antes de iniciar la urgencia.',
            409
        );
    }

    $stmt = $conexion->prepare(
        "UPDATE ejecuciones_mantenimiento
         SET total_segundos_activos = total_segundos_activos + GREATEST(
                0,
                TIMESTAMPDIFF(
                    SECOND,
                    COALESCE(fecha_ultima_reanudacion, fecha_hora_inicio, NOW()),
                    NOW()
                )
             ),
             estado = 'PAUSADA',
             fecha_ultima_reanudacion = NULL,
             en_proceso_token = NULL
         WHERE id = :ejecucion_id
           AND tecnico_id = :tecnico_id
           AND estado = 'EN_PROCESO'"
    );
    $stmt->bindValue(':ejecucion_id', $ejecucionId, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        urg_cancelar_transaccion(
            $conexion,
            'La actividad anterior cambió mientras intentabas pausarla.',
            409
        );
    }

    $stmtInsertPausa = $conexion->prepare(
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
            NOW(),
            NULL,
            0,
            'URGENCIA',
            :solicitud_urgente_id,
            :observaciones,
            'SISTEMA',
            :creada_por_id,
            1
         )"
    );
    $stmtInsertPausa->bindValue(':ejecucion_id', $ejecucionId, PDO::PARAM_INT);
    $stmtInsertPausa->bindValue(
        ':solicitud_urgente_id',
        (int) $urgencia['id'],
        PDO::PARAM_INT
    );
    $stmtInsertPausa->bindValue(
        ':observaciones',
        'Pausa automática para atender la urgencia ' . (string) $urgencia['folio']
            . '. La reanudación será manual.',
        PDO::PARAM_STR
    );
    $stmtInsertPausa->bindValue(':creada_por_id', $tecnicoId, PDO::PARAM_INT);
    $stmtInsertPausa->execute();

    $pausaId = (int) $conexion->lastInsertId();

    $stmtParticipacion = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET estado = 'PAUSADO'
         WHERE id = :id
           AND activo = 1
           AND estado = 'EN_PROCESO'"
    );
    $stmtParticipacion->bindValue(':id', $participacionAnteriorId, PDO::PARAM_INT);
    $stmtParticipacion->execute();

    if ($stmtParticipacion->rowCount() !== 1) {
        urg_cancelar_transaccion(
            $conexion,
            'No fue posible pausar la participación del mantenimiento anterior.',
            409
        );
    }

    $stmtOtras = $conexion->prepare(
        "SELECT COUNT(*)
         FROM ejecuciones_mantenimiento
         WHERE solicitud_id = :solicitud_id
           AND estado = 'EN_PROCESO'"
    );
    $stmtOtras->bindValue(':solicitud_id', $solicitudAnteriorId, PDO::PARAM_INT);
    $stmtOtras->execute();
    $otrasActivas = (int) $stmtOtras->fetchColumn();

    $estadoAnteriorSolicitud = (string) $actual['estado_solicitud'];
    $estadoNuevoSolicitud = $estadoAnteriorSolicitud;

    if ($otrasActivas === 0 && $estadoAnteriorSolicitud === 'EN_PROCESO') {
        $stmtSolicitud = $conexion->prepare(
            "UPDATE solicitudes
             SET estado = 'PAUSADO'
             WHERE id = :id
               AND estado = 'EN_PROCESO'"
        );
        $stmtSolicitud->bindValue(':id', $solicitudAnteriorId, PDO::PARAM_INT);
        $stmtSolicitud->execute();

        if ($stmtSolicitud->rowCount() === 1) {
            $estadoNuevoSolicitud = 'PAUSADO';
        }
    }

    urg_historial(
        $conexion,
        $solicitudAnteriorId,
        $participacionAnteriorId,
        'PAUSADA',
        $estadoAnteriorSolicitud,
        $estadoNuevoSolicitud,
        'SISTEMA',
        $tecnicoId,
        'La actividad se pausó automáticamente para atender la urgencia '
            . (string) $urgencia['folio']
            . '. Deberá reanudarse manualmente cuando el técnico esté listo.'
    );

    urg_movimiento(
        $conexion,
        $tecnicoId,
        'PAUSAR_POR_URGENCIA',
        'Ejecución de mantenimiento',
        'Se pausó el mantenimiento ' . (string) $actual['folio']
            . ' para atender la urgencia ' . (string) $urgencia['folio']
            . '. La reanudación quedó pendiente de acción manual.',
        'pausas_ejecucion',
        $pausaId
    );

    urg_notificar_responsables_por_id(
        $conexion,
        $solicitudAnteriorId,
        'Mantenimiento pausado por urgencia',
        'El mantenimiento ' . (string) $actual['folio']
            . ' fue pausado temporalmente para atender la urgencia '
            . (string) $urgencia['folio']
            . '. El técnico deberá reanudarlo manualmente.',
        'WARNING',
        $ejecucionId
    );

    return [
        'solicitud_id' => $solicitudAnteriorId,
        'ejecucion_id' => $ejecucionId,
        'pausa_id' => $pausaId,
        'folio' => (string) $actual['folio'],
        'equipo' => (string) $actual['nombre_equipo'],
        'estado' => 'PAUSADA',
        'requiere_reanudacion_manual' => true,
    ];
}

/* =========================================================================
   BLOQUEOS, VALIDACIONES Y PERFIL
   ========================================================================= */

function urg_tecnico_id(): int
{
    $id = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        sm_responder_json(
            false,
            'La sesión del técnico no es válida.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?sesion=expirada',
            ],
            401
        );
    }

    return (int) $id;
}

function urg_obtener_tecnico_activo(PDO $conexion, int $tecnicoId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            t.id,
            t.usuario,
            t.nombre,
            t.apellido_paterno,
            t.apellido_materno,
            t.turno,
            t.especialidad,
            t.activo,
            d.nombre AS departamento
         FROM tecnicos t
         LEFT JOIN departamentos d
                ON d.id = t.departamento_id
         WHERE t.id = :id
         LIMIT 1"
    );
    $stmt->bindValue(':id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    $tecnico = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($tecnico) || (int) ($tecnico['activo'] ?? 0) !== 1) {
        sm_destruir_sesion();

        sm_responder_json(
            false,
            'La cuenta del técnico está desactivada.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?acceso=desactivado',
            ],
            401
        );
    }

    $tecnico['nombre_completo'] = trim(implode(' ', array_filter([
        trim((string) ($tecnico['nombre'] ?? '')),
        trim((string) ($tecnico['apellido_paterno'] ?? '')),
        trim((string) ($tecnico['apellido_materno'] ?? '')),
    ])));

    return $tecnico;
}

function urg_confirmacion_riesgo_recibida($valor): int
{
    $normalizado = strtolower(trim((string) $valor));

    return in_array($normalizado, ['1', 'true', 'si', 'sí', 'on'], true)
        ? 1
        : 0;
}

/**
 * Devuelve exactamente el texto de seguridad que se mostrará y conservará
 * como evidencia de la confirmación del técnico.
 */
function urg_detalle_riesgo_mostrado(array $solicitud): string
{
    $detalle = trim((string) ($solicitud['detalle_trabajo_peligroso'] ?? ''));

    if ($detalle !== '') {
        return urg_limitar($detalle, 200);
    }

    $nivel = strtoupper(trim((string) ($solicitud['nivel_riesgo'] ?? 'BAJO')));

    return urg_limitar(
        'Trabajo peligroso registrado con nivel de riesgo ' . $nivel
            . '. Verifica condiciones, protección y seguridad antes de participar.',
        200
    );
}

function urg_id_entrada($valor, string $campo): int
{
    $id = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        sm_responder_json(
            false,
            'El registro seleccionado no es válido.',
            ['campo' => $campo],
            422
        );
    }

    return (int) $id;
}

function urg_bloquear_solicitud(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            s.*,
            e.codigo_equipo,
            e.nombre_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso
         FROM solicitudes s
         INNER JOIN equipos e ON e.id = s.equipo_id
         INNER JOIN departamentos d ON d.id = s.departamento_id
         INNER JOIN areas a ON a.id = s.area_id
         INNER JOIN procesos p ON p.id = s.proceso_id
         WHERE s.id = :id
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bindValue(':id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($solicitud)) {
        urg_cancelar_transaccion(
            $conexion,
            'La urgencia no existe.',
            404
        );
    }

    return $solicitud;
}

function urg_validar_solicitud_operativa(PDO $conexion, array $solicitud): void
{
    if ((int) ($solicitud['activo'] ?? 0) !== 1) {
        urg_cancelar_transaccion($conexion, 'La urgencia está inactiva.', 409);
    }

    if (strtoupper((string) ($solicitud['tipo_solicitud'] ?? '')) !== 'CORRECTIVO_URGENTE') {
        urg_cancelar_transaccion($conexion, 'La solicitud seleccionada no es urgente.', 422);
    }

    if (!in_array(
        strtoupper((string) ($solicitud['estado'] ?? '')),
        ['AGENDADO','EN_PROCESO','PAUSADO','ATRASADO'],
        true
    )) {
        urg_cancelar_transaccion(
            $conexion,
            'La urgencia ya no se encuentra disponible para participación.',
            409
        );
    }
}

function urg_bloquear_participacion_activa(
    PDO $conexion,
    int $solicitudId,
    int $tecnicoId
): ?array {
    $stmt = $conexion->prepare(
        "SELECT *
         FROM solicitud_tecnicos
         WHERE solicitud_id = :solicitud_id
           AND tecnico_id = :tecnico_id
           AND origen = 'ACEPTACION_URGENTE'
           AND activo = 1
           AND estado IN ('ACEPTADO','EN_PROCESO','PAUSADO')
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($fila) ? $fila : null;
}

function urg_bloquear_otro_compromiso(
    PDO $conexion,
    int $tecnicoId,
    int $solicitudExcluir
): ?array {
    $stmt = $conexion->prepare(
        "SELECT
            st.id,
            st.solicitud_id,
            st.estado,
            s.folio
         FROM solicitud_tecnicos st
         INNER JOIN solicitudes s
                 ON s.id = st.solicitud_id
         WHERE st.tecnico_id = :tecnico_id
           AND st.origen = 'ACEPTACION_URGENTE'
           AND st.activo = 1
           AND st.estado IN ('ACEPTADO','EN_PROCESO','PAUSADO')
           AND s.activo = 1
           AND s.estado IN ('AGENDADO','EN_PROCESO','PAUSADO','ATRASADO')
           AND s.id <> :solicitud_excluir
         ORDER BY st.id ASC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_excluir', $solicitudExcluir, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($fila) ? $fila : null;
}

function urg_obtener_compromiso_urgente(PDO $conexion, int $tecnicoId): ?array
{
    $stmt = $conexion->prepare(
        "SELECT
            st.id AS solicitud_tecnico_id,
            st.solicitud_id,
            st.estado AS estado_participacion,
            st.fecha_aceptacion,
            s.folio,
            s.estado AS estado_solicitud,
            e.codigo_equipo,
            e.nombre_equipo,
            em.id AS ejecucion_id,
            em.estado AS estado_ejecucion
         FROM solicitud_tecnicos st
         INNER JOIN solicitudes s
                 ON s.id = st.solicitud_id
         INNER JOIN equipos e
                 ON e.id = s.equipo_id
         LEFT JOIN ejecuciones_mantenimiento em
                ON em.solicitud_tecnico_id = st.id
         WHERE st.tecnico_id = :tecnico_id
           AND st.origen = 'ACEPTACION_URGENTE'
           AND st.activo = 1
           AND st.estado IN ('ACEPTADO','EN_PROCESO','PAUSADO')
           AND s.activo = 1
           AND s.estado IN ('AGENDADO','EN_PROCESO','PAUSADO','ATRASADO')
         ORDER BY
             FIELD(st.estado, 'EN_PROCESO','PAUSADO','ACEPTADO'),
             st.id ASC
         LIMIT 1"
    );
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($fila) ? $fila : null;
}

function urg_obtener_actividad_actual(PDO $conexion, int $tecnicoId): ?array
{
    $stmt = $conexion->prepare(
        "SELECT
            em.id AS ejecucion_id,
            em.solicitud_id,
            em.solicitud_tecnico_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            s.folio,
            s.tipo_solicitud,
            s.estado AS estado_solicitud,
            e.codigo_equipo,
            e.nombre_equipo
         FROM ejecuciones_mantenimiento em
         INNER JOIN solicitudes s
                 ON s.id = em.solicitud_id
         INNER JOIN equipos e
                 ON e.id = s.equipo_id
         WHERE em.tecnico_id = :tecnico_id
           AND em.estado = 'EN_PROCESO'
         ORDER BY em.id ASC
         LIMIT 1"
    );
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($fila) ? $fila : null;
}

function urg_bloquear_ejecuciones_activas_tecnico(PDO $conexion, int $tecnicoId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            em.id AS ejecucion_id,
            em.solicitud_id,
            em.solicitud_tecnico_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.fecha_ultima_reanudacion,
            em.total_segundos_activos,
            s.folio,
            s.tipo_solicitud,
            s.estado AS estado_solicitud,
            e.codigo_equipo,
            e.nombre_equipo
         FROM ejecuciones_mantenimiento em
         INNER JOIN solicitudes s
                 ON s.id = em.solicitud_id
         INNER JOIN equipos e
                 ON e.id = s.equipo_id
         WHERE em.tecnico_id = :tecnico_id
           AND em.estado = 'EN_PROCESO'
         ORDER BY em.id ASC
         FOR UPDATE"
    );
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function urg_ejecucion_por_participacion(
    PDO $conexion,
    int $participacionId,
    bool $bloquear
): ?array {
    $sql = "SELECT *
            FROM ejecuciones_mantenimiento
            WHERE solicitud_tecnico_id = :participacion_id
            LIMIT 1";

    if ($bloquear) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':participacion_id', $participacionId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($fila) ? $fila : null;
}

function urg_cierre_existente(PDO $conexion, int $solicitudId, bool $bloquear): ?array
{
    $sql = "SELECT id, fecha_hora_cierre
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

function urg_contar_participantes_activos(PDO $conexion, int $solicitudId): int
{
    $stmt = $conexion->prepare(
        "SELECT COUNT(*)
         FROM solicitud_tecnicos
         WHERE solicitud_id = :solicitud_id
           AND origen = 'ACEPTACION_URGENTE'
           AND activo = 1
           AND estado IN ('ACEPTADO','EN_PROCESO','PAUSADO')"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function urg_cancelar_transaccion(
    PDO $conexion,
    string $mensaje,
    int $codigo = 422
): void {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    sm_responder_json(false, $mensaje, [], $codigo);
}


/* =========================================================================
   DIAGNÓSTICO TÉCNICO DE LA URGENCIA
   ========================================================================= */

function urg_catalogos_diagnostico(PDO $conexion): array
{
    $tipos = $conexion->query(
        "SELECT id, nombre
         FROM tipos_falla
         WHERE activo = 1
         ORDER BY nombre"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $causas = $conexion->query(
        "SELECT id, nombre
         FROM causas_averia
         WHERE activo = 1
         ORDER BY nombre"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($tipos as &$tipo) {
        $tipo['id'] = (int) ($tipo['id'] ?? 0);
        $tipo['nombre'] = trim((string) ($tipo['nombre'] ?? ''));
    }
    unset($tipo);

    foreach ($causas as &$causa) {
        $causa['id'] = (int) ($causa['id'] ?? 0);
        $causa['nombre'] = trim((string) ($causa['nombre'] ?? ''));
        $causa['requiere_explicacion'] = urg_causa_requiere_explicacion(
            $causa['nombre']
        );
    }
    unset($causa);

    return [
        'tipos_falla' => $tipos,
        'causas_averia' => $causas,
    ];
}

function urg_completar_diagnostico_inicial(
    PDO $conexion,
    array &$solicitud,
    int $solicitudId,
    int $participacionId,
    int $tecnicoId
): array {
    $tipoActualId = (int) ($solicitud['tipo_falla_id'] ?? 0);
    $causaActualId = (int) ($solicitud['causa_averia_id'] ?? 0);

    if ($tipoActualId > 0 && $causaActualId > 0) {
        return [
            'capturado' => false,
            'tipo_falla_id' => $tipoActualId,
            'causa_averia_id' => $causaActualId,
            'tipo_falla' => urg_nombre_catalogo(
                $conexion,
                'tipos_falla',
                $tipoActualId
            ),
            'causa_averia' => urg_nombre_catalogo(
                $conexion,
                'causas_averia',
                $causaActualId
            ),
            'causa_desconocida_descripcion' => trim(
                (string) ($solicitud['causa_desconocida_descripcion'] ?? '')
            ),
        ];
    }

    $tipoFallaId = urg_id_post_transaccion(
        $conexion,
        $_POST['tipo_falla_id'] ?? null,
        'tipo_falla_id',
        'Selecciona el tipo de falla antes de iniciar la urgencia.'
    );

    $causaAveriaId = urg_id_post_transaccion(
        $conexion,
        $_POST['causa_averia_id'] ?? null,
        'causa_averia_id',
        'Selecciona la causa de la avería antes de iniciar la urgencia.'
    );

    $tipoFalla = urg_catalogo_activo_transaccion(
        $conexion,
        'tipos_falla',
        $tipoFallaId,
        'El tipo de falla seleccionado ya no está disponible.'
    );

    $causaAveria = urg_catalogo_activo_transaccion(
        $conexion,
        'causas_averia',
        $causaAveriaId,
        'La causa de avería seleccionada ya no está disponible.'
    );

    $explicacion = trim(
        (string) ($_POST['causa_desconocida_descripcion'] ?? '')
    );

    if (urg_longitud($explicacion) > 1500) {
        urg_cancelar_transaccion(
            $conexion,
            'La explicación provisional no puede superar 1500 caracteres.',
            422
        );
    }

    if (
        urg_causa_requiere_explicacion((string) $causaAveria['nombre'])
        && urg_longitud($explicacion) < 10
    ) {
        urg_cancelar_transaccion(
            $conexion,
            'Explica la causa provisional con al menos 10 caracteres.',
            422
        );
    }

    if (!urg_causa_requiere_explicacion((string) $causaAveria['nombre'])) {
        $explicacion = '';
    }

    $stmt = $conexion->prepare(
        "UPDATE solicitudes
         SET tipo_falla_id = :tipo_falla_id,
             causa_averia_id = :causa_averia_id,
             causa_desconocida_descripcion = :explicacion,
             version_registro = version_registro + 1
         WHERE id = :solicitud_id
           AND activo = 1
           AND tipo_solicitud = 'CORRECTIVO_URGENTE'
           AND (
                tipo_falla_id IS NULL
                OR causa_averia_id IS NULL
           )"
    );
    $stmt->bindValue(':tipo_falla_id', $tipoFallaId, PDO::PARAM_INT);
    $stmt->bindValue(':causa_averia_id', $causaAveriaId, PDO::PARAM_INT);
    urg_bind_nullable_text($stmt, ':explicacion', $explicacion);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        urg_cancelar_transaccion(
            $conexion,
            'El diagnóstico de la urgencia cambió mientras intentabas iniciar. Actualiza la información.',
            409
        );
    }

    $solicitud['tipo_falla_id'] = $tipoFallaId;
    $solicitud['causa_averia_id'] = $causaAveriaId;
    $solicitud['causa_desconocida_descripcion'] = $explicacion !== ''
        ? $explicacion
        : null;

    return [
        'capturado' => true,
        'tipo_falla_id' => $tipoFallaId,
        'causa_averia_id' => $causaAveriaId,
        'tipo_falla' => (string) $tipoFalla['nombre'],
        'causa_averia' => (string) $causaAveria['nombre'],
        'causa_desconocida_descripcion' => $explicacion,
        'participacion_id' => $participacionId,
        'tecnico_id' => $tecnicoId,
    ];
}

function urg_id_post_transaccion(
    PDO $conexion,
    $valor,
    string $campo,
    string $mensaje
): int {
    $id = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        urg_cancelar_transaccion(
            $conexion,
            $mensaje,
            422
        );
    }

    return (int) $id;
}

function urg_catalogo_activo_transaccion(
    PDO $conexion,
    string $tabla,
    int $id,
    string $mensaje
): array {
    if (!in_array($tabla, ['tipos_falla', 'causas_averia'], true)) {
        urg_cancelar_transaccion(
            $conexion,
            'El catálogo solicitado no es válido.',
            500
        );
    }

    $stmt = $conexion->prepare(
        "SELECT id, nombre
         FROM {$tabla}
         WHERE id = :id
           AND activo = 1
         LIMIT 1"
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($fila)) {
        urg_cancelar_transaccion(
            $conexion,
            $mensaje,
            422
        );
    }

    return $fila;
}

function urg_nombre_catalogo(
    PDO $conexion,
    string $tabla,
    int $id
): string {
    if (!in_array($tabla, ['tipos_falla', 'causas_averia'], true)) {
        return 'Sin dato';
    }

    $stmt = $conexion->prepare(
        "SELECT nombre
         FROM {$tabla}
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $nombre = trim((string) $stmt->fetchColumn());

    return $nombre !== '' ? $nombre : 'Sin dato';
}

function urg_causa_requiere_explicacion(string $nombre): bool
{
    $normalizado = function_exists('mb_strtolower')
        ? mb_strtolower(trim($nombre), 'UTF-8')
        : strtolower(trim($nombre));

    return preg_match(
        '/pendiente|desconoc|por determinar|no identific/u',
        $normalizado
    ) === 1;
}

function urg_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

/* =========================================================================
   CONFIGURACIÓN
   ========================================================================= */

function urg_configuracion(PDO $conexion): array
{
    return [
        'limite_tecnicos' => urg_limite_global($conexion),
        'pausa_automatica' => urg_pausa_automatica_habilitada($conexion),
        'reanudar_automaticamente' => false,
        'mensaje_reanudacion' => 'Los mantenimientos pausados por una urgencia deben reanudarse manualmente.',
    ];
}

function urg_limite_global(PDO $conexion): int
{
    $stmt = $conexion->prepare(
        "SELECT valor
         FROM configuracion_sistema
         WHERE clave = 'MAX_TECNICOS_URGENTE'
         LIMIT 1"
    );
    $stmt->execute();
    $valor = (int) $stmt->fetchColumn();

    if ($valor < 1) {
        $valor = 10;
    }

    return min(10, $valor);
}

function urg_pausa_automatica_habilitada(PDO $conexion): bool
{
    $stmt = $conexion->prepare(
        "SELECT valor
         FROM configuracion_sistema
         WHERE clave = 'PAUSAR_AL_INICIAR_URGENTE'
         LIMIT 1"
    );
    $stmt->execute();
    $valor = strtolower(trim((string) $stmt->fetchColumn()));

    return in_array($valor, ['1','true','si','sí','yes'], true);
}

/* =========================================================================
   HISTORIAL, MOVIMIENTOS Y NOTIFICACIONES
   ========================================================================= */

function urg_historial(
    PDO $conexion,
    int $solicitudId,
    ?int $participacionId,
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
            descripcion,
            fecha_evento
         ) VALUES (
            :solicitud_id,
            :participacion_id,
            NULL,
            :evento,
            :estado_anterior,
            :estado_nuevo,
            :actor_tipo,
            :actor_id,
            :descripcion,
            NOW()
         )"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    urg_bind_nullable_int($stmt, ':participacion_id', $participacionId);
    $stmt->bindValue(':evento', $evento, PDO::PARAM_STR);
    urg_bind_nullable_text($stmt, ':estado_anterior', $estadoAnterior);
    urg_bind_nullable_text($stmt, ':estado_nuevo', $estadoNuevo);
    $stmt->bindValue(':actor_tipo', $actorTipo, PDO::PARAM_STR);
    urg_bind_nullable_int($stmt, ':actor_id', $actorId);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->execute();
}

function urg_movimiento(
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
            user_agent,
            fecha_movimiento
         ) VALUES (
            'TECNICO',
            :usuario_id,
            :accion,
            :modulo,
            :descripcion,
            :tabla_afectada,
            :registro_id,
            :ip_address,
            :user_agent,
            NOW()
         )"
    );
    $stmt->bindValue(':usuario_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':modulo', $modulo, PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':tabla_afectada', $tabla, PDO::PARAM_STR);
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    urg_bind_nullable_text(
        $stmt,
        ':ip_address',
        urg_limitar((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 60)
    );
    urg_bind_nullable_text(
        $stmt,
        ':user_agent',
        urg_limitar((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 255)
    );
    $stmt->execute();
}

function urg_notificar_responsables(
    PDO $conexion,
    array $solicitud,
    int $solicitudId,
    string $titulo,
    string $mensaje,
    string $tipo,
    ?int $ejecucionId = null
): void {
    urg_notificar_administradores(
        $conexion,
        $solicitudId,
        $titulo,
        $mensaje,
        $tipo,
        $ejecucionId
    );

    $solicitanteId = (int) ($solicitud['solicitante_id'] ?? 0);

    if ($solicitanteId > 0) {
        urg_insertar_notificacion(
            $conexion,
            'SOLICITANTE',
            $solicitanteId,
            $solicitudId,
            $ejecucionId,
            $titulo,
            $mensaje,
            $tipo
        );
    }
}

function urg_notificar_responsables_por_id(
    PDO $conexion,
    int $solicitudId,
    string $titulo,
    string $mensaje,
    string $tipo,
    ?int $ejecucionId = null
): void {
    $stmt = $conexion->prepare(
        "SELECT solicitante_id, administrador_solicitante_id
         FROM solicitudes
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->bindValue(':id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($solicitud)) {
        return;
    }

    urg_notificar_responsables(
        $conexion,
        $solicitud,
        $solicitudId,
        $titulo,
        $mensaje,
        $tipo,
        $ejecucionId
    );
}

function urg_notificar_administradores(
    PDO $conexion,
    int $solicitudId,
    string $titulo,
    string $mensaje,
    string $tipo,
    ?int $ejecucionId
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
            leida,
            fecha_creacion
         )
         SELECT
            'ADMIN',
            a.id,
            :solicitud_id,
            NULL,
            :ejecucion_id,
            :titulo,
            :mensaje,
            :tipo,
            0,
            NOW()
         FROM administradores a
         WHERE a.activo = 1"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    urg_bind_nullable_int($stmt, ':ejecucion_id', $ejecucionId);
    $stmt->bindValue(':titulo', urg_limitar($titulo, 180), PDO::PARAM_STR);
    $stmt->bindValue(':mensaje', urg_limitar($mensaje, 1000), PDO::PARAM_STR);
    $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
    $stmt->execute();
}

function urg_notificar_otros_participantes(
    PDO $conexion,
    int $solicitudId,
    int $tecnicoExcluir,
    ?int $ejecucionId,
    string $titulo,
    string $mensaje
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
            leida,
            fecha_creacion
         )
         SELECT
            'TECNICO',
            st.tecnico_id,
            :solicitud_id,
            NULL,
            :ejecucion_id,
            :titulo,
            :mensaje,
            'URGENTE',
            0,
            NOW()
         FROM solicitud_tecnicos st
         INNER JOIN tecnicos t
                 ON t.id = st.tecnico_id
                AND t.activo = 1
         WHERE st.solicitud_id = :solicitud_id_filtro
           AND st.origen = 'ACEPTACION_URGENTE'
           AND st.activo = 1
           AND st.estado IN ('ACEPTADO','EN_PROCESO','PAUSADO')
           AND st.tecnico_id <> :tecnico_excluir"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    urg_bind_nullable_int($stmt, ':ejecucion_id', $ejecucionId);
    $stmt->bindValue(':titulo', urg_limitar($titulo, 180), PDO::PARAM_STR);
    $stmt->bindValue(':mensaje', urg_limitar($mensaje, 1000), PDO::PARAM_STR);
    $stmt->bindValue(':solicitud_id_filtro', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_excluir', $tecnicoExcluir, PDO::PARAM_INT);
    $stmt->execute();
}

function urg_insertar_notificacion(
    PDO $conexion,
    string $tipoUsuario,
    int $usuarioId,
    int $solicitudId,
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
            leida,
            fecha_creacion
         ) VALUES (
            :tipo_usuario,
            :usuario_id,
            :solicitud_id,
            NULL,
            :ejecucion_id,
            :titulo,
            :mensaje,
            :tipo,
            0,
            NOW()
         )"
    );
    $stmt->bindValue(':tipo_usuario', $tipoUsuario, PDO::PARAM_STR);
    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    urg_bind_nullable_int($stmt, ':ejecucion_id', $ejecucionId);
    $stmt->bindValue(':titulo', urg_limitar($titulo, 180), PDO::PARAM_STR);
    $stmt->bindValue(':mensaje', urg_limitar($mensaje, 1000), PDO::PARAM_STR);
    $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
    $stmt->execute();
}

function urg_marcar_notificaciones_tecnico_leidas(
    PDO $conexion,
    int $tecnicoId,
    int $solicitudId
): void {
    $stmt = $conexion->prepare(
        "UPDATE notificaciones
         SET leida = 1,
             fecha_lectura = COALESCE(fecha_lectura, NOW())
         WHERE tipo_usuario = 'TECNICO'
           AND usuario_id = :tecnico_id
           AND solicitud_id = :solicitud_id
           AND leida = 0"
    );
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
}

function urg_nombre_tecnico(PDO $conexion, int $tecnicoId): string
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
    $nombre = trim((string) $stmt->fetchColumn());

    return $nombre !== '' ? $nombre : 'Técnico';
}

function urg_bind_nullable_int(PDOStatement $stmt, string $parametro, ?int $valor): void
{
    if ($valor === null || $valor < 1) {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, $valor, PDO::PARAM_INT);
}

function urg_bind_nullable_text(PDOStatement $stmt, string $parametro, ?string $valor): void
{
    $texto = trim((string) $valor);

    if ($texto === '') {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, $texto, PDO::PARAM_STR);
}

function urg_limitar(string $texto, int $maximo): string
{
    $texto = trim($texto); 

    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, $maximo, 'UTF-8');
    }

    return substr($texto, 0, $maximo);
}