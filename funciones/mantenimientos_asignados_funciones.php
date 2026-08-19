<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mantenimientos asignados - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| - Exclusivo para usuarios TECNICO.
| - Muestra asignaciones administrativas vigentes.
| - No muestra urgencias de aceptación directa.
| - Los trabajos se programan únicamente por día.
| - Los atrasados siguen disponibles mientras el administrador no retire
|   la asignación.
| - Varios técnicos pueden iniciar su propia participación en una misma
|   solicitud.
| - Un técnico solamente puede tener una ejecución EN_PROCESO.
| - Las operaciones de inicio usan transacciones y bloqueos FOR UPDATE.
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

$metodo = strtoupper(
    (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')
);

$accion = $metodo === 'GET'
    ? sm_limpiar_texto($_GET['accion'] ?? 'inicial')
    : sm_limpiar_texto($_POST['accion'] ?? '');

try {
    if ($metodo === 'GET') {
        if ($accion === 'inicial') {
            masg_cargar_inicial($conexion);
        }

        if ($accion === 'detalle') {
            masg_cargar_detalle($conexion);
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

    if ($accion === 'sincronizar') {
        masg_sincronizar($conexion);
    }

    if ($accion === 'iniciar') {
        masg_iniciar($conexion);
    }

    sm_responder_json(
        false,
        'La acción solicitada no es válida.',
        [],
        400
    );
} catch (RuntimeException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    sm_responder_json(
        false,
        $e->getMessage(),
        [],
        409
    );
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log(
        '[MANTENIMIENTOS ASIGNADOS][PDO] '
        . $e->getMessage()
    );

    if ((string) $e->getCode() === '23000') {
        sm_responder_json(
            false,
            'La información cambió mientras realizabas la operación. Actualiza la pantalla e inténtalo nuevamente.',
            [],
            409
        );
    }

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar el mantenimiento.',
        [],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log(
        '[MANTENIMIENTOS ASIGNADOS] '
        . $e->getMessage()
    );

    sm_responder_json(
        false,
        'No fue posible completar la operación.',
        [],
        500
    );
}

/* =========================================================================
   CARGA
   ========================================================================= */

function masg_cargar_inicial(PDO $conexion): void
{
    $tecnicoId = masg_tecnico_id();

    $perfil = masg_obtener_tecnico(
        $conexion,
        $tecnicoId
    );

    if (!$perfil) {
        sm_responder_json(
            false,
            'Tu cuenta de técnico no está disponible.',
            [],
            403
        );
    }

    $bloqueos = masg_obtener_bloqueos(
        $conexion,
        $tecnicoId
    );

    $mantenimientos = masg_consultar_asignaciones(
        $conexion,
        $tecnicoId,
        $perfil,
        $bloqueos
    );

    sm_responder_json(
        true,
        'Asignaciones actualizadas correctamente.',
        [
            'perfil' => $perfil,
            'bloqueos' => $bloqueos,
            'mantenimientos' => $mantenimientos,
            'cancelaciones_recientes' => masg_consultar_cancelaciones_recientes($conexion, $tecnicoId),
            'resumen' => masg_generar_resumen(
                $mantenimientos
            ),
            'fecha_servidor' => date('Y-m-d H:i:s'),
            'fecha_servidor_texto' => date(
                'd/m/Y, h:i a'
            ),
        ]
    );
}

function masg_cargar_detalle(PDO $conexion): void
{
    $tecnicoId = masg_tecnico_id();

    $asignacionId = masg_id_positivo(
        $_GET['asignacion_id'] ?? null,
        'asignacion_id'
    );

    $perfil = masg_obtener_tecnico(
        $conexion,
        $tecnicoId
    );

    if (!$perfil) {
        sm_responder_json(
            false,
            'Tu cuenta de técnico no está disponible.',
            [],
            403
        );
    }

    $bloqueos = masg_obtener_bloqueos(
        $conexion,
        $tecnicoId
    );

    $registros = masg_consultar_asignaciones(
        $conexion,
        $tecnicoId,
        $perfil,
        $bloqueos,
        $asignacionId
    );

    if ($registros === []) {
        sm_responder_json(
            false,
            'La asignación no existe, fue retirada o ya no está abierta.',
            [],
            404
        );
    }

    $mantenimiento = $registros[0];

    sm_responder_json(
        true,
        'Detalle cargado correctamente.',
        [
            'mantenimiento' => $mantenimiento,
            'participantes' => masg_consultar_participantes(
                $conexion,
                (int) $mantenimiento['solicitud_id']
            ),
            'bloqueos' => $bloqueos,
        ]
    );
}

/* =========================================================================
   CONSULTA PRINCIPAL
   ========================================================================= */

function masg_consultar_asignaciones(
    PDO $conexion,
    int $tecnicoId,
    array $perfil,
    array $bloqueos,
    ?int $asignacionId = null
): array {
    $sql = "
        SELECT
            st.id AS asignacion_id,
            st.solicitud_id,
            st.programacion_id,
            st.tecnico_id,
            st.origen,
            st.estado AS estado_asignacion,
            st.fecha_asignacion,
            st.alerta_riesgo_nocturno,
            st.riesgo_nocturno_confirmado,
            st.observacion_riesgo_nocturno,
            st.resultado_cumplimiento,
            st.fecha_resultado,

            s.folio,
            s.tipo_solicitud,
            s.estado AS estado_solicitud,
            s.departamento_id AS solicitud_departamento_id,
            s.area_id AS solicitud_area_id,
            s.proceso_id AS solicitud_proceso_id,
            s.equipo_id AS solicitud_equipo_id,
            s.prioridad,
            s.fecha_solicitud,
            s.hora_solicitud,
            s.descripcion_solicitud,
            s.descripcion_falla,
            s.causa_desconocida_descripcion,
            s.costo_vs_beneficio,
            s.impacto_operacion,
            s.objetivo_mejora,
            s.resultado_esperado,
            s.justificacion_mejora,
            s.observaciones_solicitante,
            s.trabajo_peligroso,
            s.detalle_trabajo_peligroso,
            s.nivel_riesgo,
            s.requiere_paro_equipo,

            pm.fecha_programada,
            pm.fecha_limite,
            pm.estado AS estado_programacion,
            pm.es_actual,
            pm.motivo_programacion,
            pm.motivo_reprogramacion,

            e.codigo_equipo,
            e.nombre_equipo,
            e.activo AS equipo_activo,

            d.nombre AS departamento,
            d.activo AS departamento_activo,

            a.nombre AS area,
            a.activo AS area_activa,
            a.departamento_id AS area_departamento_id,

            p.nombre AS proceso,
            p.activo AS proceso_activo,
            p.area_id AS proceso_area_id,

            tf.nombre AS tipo_falla,
            ca.nombre AS causa_averia,

            COALESCE(
                NULLIF(
                    TRIM(
                        CONCAT_WS(
                            ' ',
                            sol.nombre,
                            sol.apellido_paterno,
                            sol.apellido_materno
                        )
                    ),
                    ''
                ),
                NULLIF(
                    TRIM(
                        CONCAT_WS(
                            ' ',
                            adm.nombre,
                            adm.apellido_paterno,
                            adm.apellido_materno
                        )
                    ),
                    ''
                ),
                'Sin solicitante'
            ) AS solicitante,

            COALESCE(
                sol.telefono,
                adm.telefono,
                ''
            ) AS solicitante_telefono,

            COALESCE(
                sol.correo,
                adm.correo,
                ''
            ) AS solicitante_correo,

            em.id AS ejecucion_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.fecha_hora_fin,
            em.total_segundos_activos,
            em.total_segundos_pausa,

            im.id AS incumplimiento_id,
            im.estado AS estado_incumplimiento,
            im.fecha_detectado,

            cm.id AS cierre_id,

            cal.tipo_dia AS tipo_dia_calendario,
            cal.es_habil AS es_laboral_calendario,
            cal.motivo AS observacion_calendario,

            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos st_total
                WHERE st_total.solicitud_id = s.id
                  AND st_total.origen = 'ADMIN'
                  AND st_total.activo = 1
                  AND st_total.estado IN (
                      'ASIGNADO',
                      'ACEPTADO',
                      'EN_PROCESO',
                      'PAUSADO',
                      'TERMINADO'
                  )
            ) AS total_tecnicos,

            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos st_inicio
                INNER JOIN ejecuciones_mantenimiento em_inicio
                        ON em_inicio.solicitud_tecnico_id = st_inicio.id
                WHERE st_inicio.solicitud_id = s.id
                  AND st_inicio.origen = 'ADMIN'
                  AND st_inicio.activo = 1
                  AND em_inicio.fecha_hora_inicio IS NOT NULL
            ) AS tecnicos_iniciaron,

            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos st_trabajando
                INNER JOIN ejecuciones_mantenimiento em_trabajando
                        ON em_trabajando.solicitud_tecnico_id = st_trabajando.id
                WHERE st_trabajando.solicitud_id = s.id
                  AND st_trabajando.origen = 'ADMIN'
                  AND st_trabajando.activo = 1
                  AND em_trabajando.estado IN (
                      'EN_PROCESO',
                      'PAUSADA'
                  )
            ) AS tecnicos_con_ejecucion_abierta

        FROM solicitud_tecnicos st

        INNER JOIN solicitudes s
                ON s.id = st.solicitud_id

        LEFT JOIN programaciones_mantenimiento pm
               ON pm.id = st.programacion_id

        INNER JOIN equipos e
                ON e.id = s.equipo_id

        INNER JOIN departamentos d
                ON d.id = s.departamento_id

        INNER JOIN areas a
                ON a.id = s.area_id

        INNER JOIN procesos p
                ON p.id = s.proceso_id

        LEFT JOIN tipos_falla tf
               ON tf.id = s.tipo_falla_id

        LEFT JOIN causas_averia ca
               ON ca.id = s.causa_averia_id

        LEFT JOIN solicitantes sol
               ON sol.id = s.solicitante_id

        LEFT JOIN administradores adm
               ON adm.id = s.administrador_solicitante_id

        LEFT JOIN ejecuciones_mantenimiento em
               ON em.solicitud_tecnico_id = st.id

        LEFT JOIN incumplimientos_mantenimiento im
               ON im.solicitud_tecnico_id = st.id
              AND im.programacion_id = st.programacion_id

        LEFT JOIN cierres_mantenimiento cm
               ON cm.solicitud_id = s.id

        LEFT JOIN calendario_laboral cal
               ON cal.fecha = pm.fecha_programada

        WHERE st.tecnico_id = :tecnico_id
          AND st.origen = 'ADMIN'
          AND st.activo = 1
          AND st.activo_token = 1
          AND st.estado IN (
              'ASIGNADO',
              'ACEPTADO',
              'EN_PROCESO',
              'PAUSADO'
          )
          AND s.activo = 1
          AND s.tipo_solicitud <> 'CORRECTIVO_URGENTE'
          AND s.estado IN (
              'AGENDADO',
              'ATRASADO',
              'EN_PROCESO',
              'PAUSADO'
          )
    ";

    if ($asignacionId !== null) {
        $sql .= "
          AND st.id = :asignacion_id
        ";
    }

    $sql .= "
        ORDER BY
            CASE
                WHEN em.estado = 'EN_PROCESO' THEN 0
                WHEN em.estado = 'PAUSADA' THEN 1
                WHEN pm.fecha_limite < CURDATE() THEN 2
                WHEN pm.fecha_programada = CURDATE() THEN 3
                WHEN s.estado IN ('EN_PROCESO','PAUSADO') THEN 4
                ELSE 5
            END,
            pm.fecha_programada ASC,
            FIELD(
                s.prioridad,
                'URGENTE',
                'ALTA',
                'MEDIA',
                'BAJA'
            ),
            s.id ASC
        LIMIT 250
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->bindValue(
        ':tecnico_id',
        $tecnicoId,
        PDO::PARAM_INT
    );

    if ($asignacionId !== null) {
        $stmt->bindValue(
            ':asignacion_id',
            $asignacionId,
            PDO::PARAM_INT
        );
    }

    $stmt->execute();

    $registros = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    ) ?: [];

    rsm_adjuntar_recursos_recomendados(
        $conexion,
        $registros,
        'solicitud_equipo_id',
        false
    );

    foreach ($registros as &$registro) {
        masg_enriquecer_registro(
            $registro,
            $perfil,
            $bloqueos
        );
    }

    unset($registro);

    return $registros;
}

function masg_enriquecer_registro(
    array &$registro,
    array $perfil,
    array $bloqueos
): void {
    $hoy = date('Y-m-d');

    $fechaProgramada = is_string(
        $registro['fecha_programada'] ?? null
    )
        ? $registro['fecha_programada']
        : '';

    $fechaLimite = is_string(
        $registro['fecha_limite'] ?? null
    )
        ? $registro['fecha_limite']
        : '';

    $estadoEjecucion = strtoupper(
        (string) (
            $registro['estado_ejecucion']
            ?? ''
        )
    );

    $estadoAsignacion = strtoupper(
        (string) (
            $registro['estado_asignacion']
            ?? ''
        )
    );

    $estadoSolicitud = strtoupper(
        (string) (
            $registro['estado_solicitud']
            ?? ''
        )
    );

    $estadoProgramacion = strtoupper(
        (string) (
            $registro['estado_programacion']
            ?? ''
        )
    );

    $diasParaInicio = 0;
    $diasAtraso = 0;

    if ($fechaProgramada !== '') {
        $diasParaInicio = masg_diferencia_dias(
            $hoy,
            $fechaProgramada
        );
    }

    $inicioRegistrado =
        trim(
            (string) (
                $registro['fecha_hora_inicio']
                ?? ''
            )
        ) !== '';

    if (
        $fechaLimite !== ''
        && $fechaLimite < $hoy
        && !$inicioRegistrado
    ) {
        $diasAtraso = masg_diferencia_dias(
            $fechaLimite,
            $hoy
        );
    }

    $registro['dias_para_inicio'] =
        $diasParaInicio;

    $registro['dias_atraso'] =
        $diasAtraso;

    $registro['fecha_programada_texto'] =
        masg_formatear_fecha(
            $fechaProgramada
        );

    $registro['fecha_limite_texto'] =
        masg_formatear_fecha(
            $fechaLimite
        );

    $registro['fecha_solicitud_texto'] =
        masg_formatear_fecha(
            (string) (
                $registro['fecha_solicitud']
                ?? ''
            )
        );

    $registro['hora_solicitud_texto'] =
        masg_formatear_hora(
            (string) (
                $registro['hora_solicitud']
                ?? ''
            )
        );

    $registro['fecha_asignacion_texto'] =
        masg_formatear_fecha_hora(
            (string) (
                $registro['fecha_asignacion']
                ?? ''
            )
        );

    $registro['solicitante_contacto'] =
        masg_contacto_solicitante(
            (string) (
                $registro['solicitante_telefono']
                ?? ''
            ),
            (string) (
                $registro['solicitante_correo']
                ?? ''
            )
        );

    $registro['equipo_trabajando'] =
        in_array(
            $estadoSolicitud,
            ['EN_PROCESO', 'PAUSADO'],
            true
        )
        && (int) (
            $registro['tecnicos_iniciaron']
            ?? 0
        ) > 0
            ? 1
            : 0;

    $registro['alerta_nocturna_peligrosa'] =
        (
            (int) (
                $registro['alerta_riesgo_nocturno']
                ?? 0
            ) === 1
            || (
                strtoupper(
                    (string) (
                        $perfil['turno']
                        ?? ''
                    )
                ) === 'NOCTURNO'
                && (int) (
                    $registro['trabajo_peligroso']
                    ?? 0
                ) === 1
            )
        )
            ? 1
            : 0;

    $registro['confirmacion_nocturna_pendiente'] =
        (
            (int) (
                $registro['alerta_riesgo_nocturno']
                ?? 0
            ) === 1
            && (int) (
                $registro['riesgo_nocturno_confirmado']
                ?? 0
            ) !== 1
        )
            ? 1
            : 0;

    $registro['equipo_para_unirse'] =
        (
            (int) $registro['equipo_trabajando'] === 1
            && empty($registro['ejecucion_id'])
        )
            ? 1
            : 0;

    $registro['puede_iniciar'] = 0;
    $registro['accion_principal'] = 'BLOQUEADO';
    $registro['texto_accion'] = 'No disponible';
    $registro['motivo_bloqueo'] = '';

    if (
        in_array(
            $estadoEjecucion,
            ['EN_PROCESO', 'PAUSADA'],
            true
        )
    ) {
        $registro['accion_principal'] = 'ABRIR';

        $registro['texto_accion'] =
            $estadoEjecucion === 'PAUSADA'
                ? 'Revisar actividad pausada'
                : 'Abrir actividad';

        $registro['categoria'] =
            $estadoEjecucion === 'PAUSADA'
                ? 'PAUSADA'
                : 'ACTIVA';

        $registro['orden_visual'] =
            $estadoEjecucion === 'EN_PROCESO'
                ? 0
                : 1;

        $registro['fecha_relativa'] =
            $estadoEjecucion === 'PAUSADA'
                ? 'Actividad pausada'
                : 'En proceso';

        return;
    }

    if (
        $fechaProgramada === ''
        || empty($registro['programacion_id'])
        || (int) (
            $registro['es_actual']
            ?? 0
        ) !== 1
    ) {
        $registro['texto_accion'] =
            'Sin programación vigente';

        $registro['motivo_bloqueo'] =
            'Solicita al administrador que revise la programación.';

        $registro['categoria'] =
            'OTRA';

        $registro['orden_visual'] = 90;
        $registro['fecha_relativa'] =
            'Sin programación';

        return;
    }

    if (
        !in_array(
            $estadoProgramacion,
            ['PROGRAMADA', 'VENCIDA'],
            true
        )
    ) {
        $registro['texto_accion'] =
            'Programación no disponible';

        $registro['motivo_bloqueo'] =
            'La programación fue reemplazada, cancelada o cerrada.';

        $registro['categoria'] = 'OTRA';
        $registro['orden_visual'] = 91;
        $registro['fecha_relativa'] =
            masg_fecha_relativa(
                $fechaProgramada,
                $fechaLimite
            );

        return;
    }

    if ($fechaProgramada > $hoy) {
        $registro['accion_principal'] = 'ESPERAR';

        $registro['texto_accion'] =
            'Disponible el '
            . masg_formatear_fecha(
                $fechaProgramada
            );

        $registro['motivo_bloqueo'] =
            'Todavía no llega el día programado.';

        $registro['categoria'] = 'PROXIMA';
        $registro['orden_visual'] = 50;
        $registro['fecha_relativa'] =
            masg_fecha_relativa(
                $fechaProgramada,
                $fechaLimite
            );

        return;
    }

    if (
        !in_array(
            $estadoAsignacion,
            ['ASIGNADO', 'ACEPTADO'],
            true
        )
    ) {
        $registro['texto_accion'] =
            'Asignación no disponible';

        $registro['motivo_bloqueo'] =
            'El estado de tu asignación cambió.';

        $registro['categoria'] = 'OTRA';
        $registro['orden_visual'] = 92;
        $registro['fecha_relativa'] =
            masg_fecha_relativa(
                $fechaProgramada,
                $fechaLimite
            );

        return;
    }

    if (
        !in_array(
            $estadoSolicitud,
            [
                'AGENDADO',
                'ATRASADO',
                'EN_PROCESO',
                'PAUSADO',
            ],
            true
        )
    ) {
        $registro['texto_accion'] =
            'Mantenimiento no disponible';

        $registro['motivo_bloqueo'] =
            'La solicitud ya no se encuentra abierta.';

        $registro['categoria'] = 'OTRA';
        $registro['orden_visual'] = 93;
        $registro['fecha_relativa'] =
            masg_fecha_relativa(
                $fechaProgramada,
                $fechaLimite
            );

        return;
    }

    if (!empty($registro['cierre_id'])) {
        $registro['texto_accion'] =
            'Mantenimiento cerrado';

        $registro['motivo_bloqueo'] =
            'Ya existe un cierre para esta solicitud.';

        $registro['categoria'] = 'OTRA';
        $registro['orden_visual'] = 94;
        $registro['fecha_relativa'] =
            'Cerrado';

        return;
    }

    if (!masg_ubicacion_activa($registro)) {
        $registro['texto_accion'] =
            'Ubicación inactiva';

        $registro['motivo_bloqueo'] =
            'El equipo o su ubicación fueron desactivados. Solicita revisión al administrador.';

        $registro['categoria'] = 'OTRA';
        $registro['orden_visual'] = 95;
        $registro['fecha_relativa'] =
            masg_fecha_relativa(
                $fechaProgramada,
                $fechaLimite
            );

        return;
    }

    if (
        (int) (
            $registro['confirmacion_nocturna_pendiente']
            ?? 0
        ) === 1
    ) {
        $registro['texto_accion'] =
            'Pendiente de validación de seguridad';

        $registro['motivo_bloqueo'] =
            'El administrador debe confirmar las condiciones del trabajo peligroso en turno nocturno.';

        $registro['categoria'] =
            $diasAtraso > 0
                ? 'ATRASADA'
                : (
                    $fechaProgramada === $hoy
                        ? 'HOY'
                        : 'OTRA'
                );

        $registro['orden_visual'] =
            $diasAtraso > 0 ? 20 : 30;

        $registro['fecha_relativa'] =
            masg_fecha_relativa(
                $fechaProgramada,
                $fechaLimite
            );

        return;
    }

    $actividadActiva =
        $bloqueos['actividad_activa']
        ?? null;

    if (
        is_array($actividadActiva)
        && (int) (
            $actividadActiva['asignacion_id']
            ?? 0
        ) !== (int) $registro['asignacion_id']
    ) {
        $registro['texto_accion'] =
            'Ya tienes una actividad activa';

        $registro['motivo_bloqueo'] =
            'Pausa o finaliza tu actividad actual antes de iniciar otra.';

        $registro['categoria'] =
            $diasAtraso > 0
                ? 'ATRASADA'
                : (
                    $fechaProgramada === $hoy
                        ? 'HOY'
                        : 'OTRA'
                );

        $registro['orden_visual'] =
            $diasAtraso > 0 ? 20 : 30;

        $registro['fecha_relativa'] =
            masg_fecha_relativa(
                $fechaProgramada,
                $fechaLimite
            );

        return;
    }

    $urgenciaAceptada =
        $bloqueos['urgencia_aceptada']
        ?? null;

    if (is_array($urgenciaAceptada)) {
        $registro['texto_accion'] =
            'Tienes una urgencia aceptada';

        $registro['motivo_bloqueo'] =
            'Inicia la urgencia o libera tu lugar antes de comenzar este mantenimiento.';

        $registro['categoria'] =
            $diasAtraso > 0
                ? 'ATRASADA'
                : (
                    $fechaProgramada === $hoy
                        ? 'HOY'
                        : 'OTRA'
                );

        $registro['orden_visual'] =
            $diasAtraso > 0 ? 20 : 30;

        $registro['fecha_relativa'] =
            masg_fecha_relativa(
                $fechaProgramada,
                $fechaLimite
            );

        return;
    }

    $registro['puede_iniciar'] = 1;
    $registro['accion_principal'] = 'INICIAR';

    if (
        (int) $registro['equipo_trabajando'] === 1
    ) {
        $registro['texto_accion'] =
            'Unirme al mantenimiento';

        $registro['categoria'] = 'EQUIPO';
        $registro['orden_visual'] = 12;
    } elseif ($diasAtraso > 0) {
        $registro['texto_accion'] =
            'Iniciar mantenimiento atrasado';

        $registro['categoria'] = 'ATRASADA';
        $registro['orden_visual'] = 10;
    } else {
        $registro['texto_accion'] =
            'Iniciar mantenimiento';

        $registro['categoria'] =
            $fechaProgramada === $hoy
                ? 'HOY'
                : 'DISPONIBLE';

        $registro['orden_visual'] =
            $fechaProgramada === $hoy
                ? 15
                : 18;
    }

    $registro['fecha_relativa'] =
        masg_fecha_relativa(
            $fechaProgramada,
            $fechaLimite
        );
}

function masg_ubicacion_activa(array $registro): bool
{
    return
        (int) (
            $registro['equipo_activo']
            ?? 0
        ) === 1
        && (int) (
            $registro['departamento_activo']
            ?? 0
        ) === 1
        && (int) (
            $registro['area_activa']
            ?? 0
        ) === 1
        && (int) (
            $registro['proceso_activo']
            ?? 0
        ) === 1
        && (int) (
            $registro['area_departamento_id']
            ?? 0
        ) === (int) (
            $registro['solicitud_departamento_id']
            ?? 0
        )
        && (int) (
            $registro['proceso_area_id']
            ?? 0
        ) === (int) (
            $registro['solicitud_area_id']
            ?? 0
        );
}

/* =========================================================================
   PARTICIPANTES
   ========================================================================= */

function masg_consultar_participantes(
    PDO $conexion,
    int $solicitudId
): array {
    $stmt = $conexion->prepare(
        "
        SELECT
            st.id AS asignacion_id,
            st.estado AS estado_asignacion,
            st.fecha_asignacion,
            t.id AS tecnico_id,
            TRIM(
                CONCAT_WS(
                    ' ',
                    t.nombre,
                    t.apellido_paterno,
                    t.apellido_materno
                )
            ) AS tecnico,
            t.especialidad,
            t.turno,
            em.id AS ejecucion_id,
            em.estado AS estado_ejecucion,
            DATE_FORMAT(
                em.fecha_hora_inicio,
                '%d/%m/%Y %H:%i'
            ) AS fecha_inicio_texto
        FROM solicitud_tecnicos st
        INNER JOIN tecnicos t
                ON t.id = st.tecnico_id
        LEFT JOIN ejecuciones_mantenimiento em
               ON em.solicitud_tecnico_id = st.id
        WHERE st.solicitud_id = :solicitud_id
          AND st.origen = 'ADMIN'
          AND st.activo = 1
          AND st.activo_token = 1
          AND st.estado IN (
              'ASIGNADO',
              'ACEPTADO',
              'EN_PROCESO',
              'PAUSADO',
              'TERMINADO'
          )
        ORDER BY
            CASE
                WHEN em.estado = 'EN_PROCESO' THEN 0
                WHEN em.estado = 'PAUSADA' THEN 1
                WHEN st.estado = 'ASIGNADO' THEN 2
                ELSE 3
            END,
            tecnico ASC,
            st.id ASC
        "
    );

    $stmt->bindValue(
        ':solicitud_id',
        $solicitudId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    ) ?: [];
}

/* =========================================================================
   BLOQUEOS GLOBALES
   ========================================================================= */

function masg_obtener_bloqueos(
    PDO $conexion,
    int $tecnicoId
): array {
    $stmtActividad = $conexion->prepare(
        "
        SELECT
            em.id AS ejecucion_id,
            em.solicitud_tecnico_id AS asignacion_id,
            em.solicitud_id,
            s.folio,
            e.codigo_equipo,
            e.nombre_equipo
        FROM ejecuciones_mantenimiento em
        INNER JOIN solicitudes s
                ON s.id = em.solicitud_id
        INNER JOIN equipos e
                ON e.id = s.equipo_id
        WHERE em.tecnico_id = :tecnico_id
          AND em.estado = 'EN_PROCESO'
          AND s.activo = 1
        ORDER BY em.fecha_hora_inicio ASC
        LIMIT 1
        "
    );

    $stmtActividad->bindValue(
        ':tecnico_id',
        $tecnicoId,
        PDO::PARAM_INT
    );

    $stmtActividad->execute();

    $actividad = $stmtActividad->fetch(
        PDO::FETCH_ASSOC
    );

    $stmtUrgencia = $conexion->prepare(
        "
        SELECT
            st.id AS asignacion_id,
            s.id AS solicitud_id,
            s.folio,
            e.codigo_equipo,
            e.nombre_equipo
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
          AND st.estado = 'ACEPTADO'
          AND em.id IS NULL
          AND s.activo = 1
          AND s.estado IN (
              'AGENDADO',
              'EN_PROCESO',
              'PAUSADO',
              'ATRASADO'
          )
        ORDER BY st.fecha_aceptacion ASC
        LIMIT 1
        "
    );

    $stmtUrgencia->bindValue(
        ':tecnico_id',
        $tecnicoId,
        PDO::PARAM_INT
    );

    $stmtUrgencia->execute();

    $urgencia = $stmtUrgencia->fetch(
        PDO::FETCH_ASSOC
    );

    return [
        'actividad_activa' => is_array($actividad)
            ? $actividad
            : null,

        'urgencia_aceptada' => is_array($urgencia)
            ? $urgencia
            : null,
    ];
}

/* =========================================================================
   CANCELACIONES ADMINISTRATIVAS RECIENTES
   ========================================================================= */

function masg_consultar_cancelaciones_recientes(PDO $conexion, int $tecnicoId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            s.id AS solicitud_id,
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
            ) AS cancelado_por,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM ejecuciones_mantenimiento em_i
                    WHERE em_i.solicitud_id = s.id
                      AND em_i.fecha_hora_inicio IS NOT NULL
                ) THEN 1 ELSE 0
            END AS fue_iniciado
         FROM solicitudes s
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
         WHERE s.estado = 'CANCELADO'
           AND COALESCE(hc.fecha_evento, s.fecha_actualizacion) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           AND (
                EXISTS (
                    SELECT 1
                    FROM historial_solicitudes ht_permiso
                    INNER JOIN solicitud_tecnicos st_permiso
                            ON st_permiso.id = ht_permiso.solicitud_tecnico_id
                    WHERE ht_permiso.solicitud_id = s.id
                      AND st_permiso.tecnico_id = :tecnico_historial
                      AND ht_permiso.evento = 'TECNICO_RETIRADO'
                      AND LOWER(ht_permiso.descripcion) LIKE '%cancel%'
                )
                OR EXISTS (
                    SELECT 1
                    FROM notificaciones n_permiso
                    WHERE n_permiso.solicitud_id = s.id
                      AND n_permiso.tipo_usuario = 'TECNICO'
                      AND n_permiso.usuario_id = :tecnico_notificacion
                      AND n_permiso.titulo IN (
                          'Mantenimiento cancelado',
                          'Mantenimiento cancelado por administración'
                      )
                )
           )
         ORDER BY COALESCE(hc.fecha_evento, s.fecha_actualizacion) DESC, s.id DESC
         LIMIT 5"
    );
    $stmt->bindValue(':tecnico_historial', $tecnicoId, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_notificacion', $tecnicoId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* =========================================================================
   SINCRONIZACIÓN DE ATRASOS
   ========================================================================= */

function masg_sincronizar(PDO $conexion): void
{
    $tecnicoId = masg_tecnico_id();

    if (!masg_obtener_tecnico(
        $conexion,
        $tecnicoId
    )) {
        sm_responder_json(
            false,
            'Tu cuenta de técnico no está disponible.',
            [],
            403
        );
    }

    $resultado = masg_sincronizar_atrasos(
        $conexion
    );

    sm_responder_json(
        true,
        'Fechas sincronizadas correctamente.',
        $resultado
    );
}

function masg_sincronizar_atrasos(
    PDO $conexion
): array {
    $conexion->beginTransaction();

    try {
        /*
         * Se usa la misma lógica de cumplimiento del módulo administrativo:
         * - La programación vence aunque el mantenimiento ya haya comenzado.
         * - La solicitud sólo cambia a ATRASADO cuando nadie la inició.
         * - El incumplimiento se registra por asignación que siga pendiente.
         */
        $stmtProgramaciones = $conexion->prepare(
            "
            SELECT
                pm.id AS programacion_id,
                pm.solicitud_id,
                pm.fecha_programada,
                pm.fecha_limite,
                pm.estado AS estado_programacion,
                s.folio,
                s.estado AS estado_solicitud,
                EXISTS (
                    SELECT 1
                    FROM ejecuciones_mantenimiento em_inicio
                    WHERE em_inicio.solicitud_id = s.id
                      AND em_inicio.fecha_hora_inicio IS NOT NULL
                ) AS tiene_inicio
            FROM programaciones_mantenimiento pm
            INNER JOIN solicitudes s
                    ON s.id = pm.solicitud_id
            WHERE pm.es_actual = 1
              AND pm.estado IN (
                  'PROGRAMADA',
                  'VENCIDA'
              )
              AND pm.fecha_limite < CURDATE()
              AND s.activo = 1
              AND s.tipo_solicitud <> 'CORRECTIVO_URGENTE'
              AND s.estado NOT IN (
                  'TERMINADO',
                  'RECHAZADO',
                  'CANCELADO'
              )
            ORDER BY
                pm.fecha_limite ASC,
                pm.id ASC
            FOR UPDATE
            "
        );

        $stmtProgramaciones->execute();

        $programaciones = $stmtProgramaciones->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

        $programacionesVencidas = 0;
        $solicitudesAtrasadas = 0;
        $incumplimientosNuevos = 0;

        foreach ($programaciones as $programacion) {
            $programacionId = (int) (
                $programacion['programacion_id']
                ?? 0
            );

            $solicitudId = (int) (
                $programacion['solicitud_id']
                ?? 0
            );

            if (
                $programacionId <= 0
                || $solicitudId <= 0
            ) {
                continue;
            }

            if (
                (string) (
                    $programacion['estado_programacion']
                    ?? ''
                ) === 'PROGRAMADA'
            ) {
                $stmtVencer = $conexion->prepare(
                    "
                    UPDATE programaciones_mantenimiento
                    SET estado = 'VENCIDA'
                    WHERE id = :programacion_id
                      AND es_actual = 1
                      AND estado = 'PROGRAMADA'
                    "
                );

                $stmtVencer->bindValue(
                    ':programacion_id',
                    $programacionId,
                    PDO::PARAM_INT
                );

                $stmtVencer->execute();

                $programacionesVencidas +=
                    $stmtVencer->rowCount();
            }

            $estadoSolicitudAnterior = (string) (
                $programacion['estado_solicitud']
                ?? ''
            );

            $estadoSolicitudActual =
                $estadoSolicitudAnterior;

            if (
                in_array(
                    $estadoSolicitudAnterior,
                    ['APROBADO', 'AGENDADO'],
                    true
                )
                && (int) (
                    $programacion['tiene_inicio']
                    ?? 0
                ) === 0
            ) {
                $stmtSolicitud = $conexion->prepare(
                    "
                    UPDATE solicitudes
                    SET estado = 'ATRASADO'
                    WHERE id = :solicitud_id
                      AND activo = 1
                      AND estado IN (
                          'APROBADO',
                          'AGENDADO'
                      )
                    "
                );

                $stmtSolicitud->bindValue(
                    ':solicitud_id',
                    $solicitudId,
                    PDO::PARAM_INT
                );

                $stmtSolicitud->execute();

                if ($stmtSolicitud->rowCount() === 1) {
                    $solicitudesAtrasadas++;
                    $estadoSolicitudActual = 'ATRASADO';
                }
            }

            $stmtAsignaciones = $conexion->prepare(
                "
                SELECT
                    st.id AS asignacion_id,
                    st.tecnico_id,
                    st.estado AS estado_asignacion
                FROM solicitud_tecnicos st
                LEFT JOIN incumplimientos_mantenimiento im
                       ON im.solicitud_tecnico_id = st.id
                      AND im.programacion_id = st.programacion_id
                WHERE st.programacion_id = :programacion_id
                  AND st.origen = 'ADMIN'
                  AND st.activo = 1
                  AND st.activo_token = 1
                  AND st.resultado_cumplimiento = 'PENDIENTE'
                  AND st.estado IN (
                      'ASIGNADO',
                      'ACEPTADO',
                      'EN_PROCESO',
                      'PAUSADO'
                  )
                  AND im.id IS NULL
                ORDER BY st.id ASC
                FOR UPDATE
                "
            );

            $stmtAsignaciones->bindValue(
                ':programacion_id',
                $programacionId,
                PDO::PARAM_INT
            );

            $stmtAsignaciones->execute();

            $asignaciones = $stmtAsignaciones->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

            foreach ($asignaciones as $asignacion) {
                $asignacionId = (int) (
                    $asignacion['asignacion_id']
                    ?? 0
                );

                $tecnicoAsignadoId = (int) (
                    $asignacion['tecnico_id']
                    ?? 0
                );

                if (
                    $asignacionId <= 0
                    || $tecnicoAsignadoId <= 0
                ) {
                    continue;
                }

                $stmtIncumplimiento = $conexion->prepare(
                    "
                    INSERT IGNORE INTO incumplimientos_mantenimiento
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
                        :asignacion_id,
                        :fecha_programada,
                        NOW(),
                        'PENDIENTE'
                    )
                    "
                );

                $stmtIncumplimiento->bindValue(
                    ':solicitud_id',
                    $solicitudId,
                    PDO::PARAM_INT
                );

                $stmtIncumplimiento->bindValue(
                    ':programacion_id',
                    $programacionId,
                    PDO::PARAM_INT
                );

                $stmtIncumplimiento->bindValue(
                    ':asignacion_id',
                    $asignacionId,
                    PDO::PARAM_INT
                );

                $stmtIncumplimiento->bindValue(
                    ':fecha_programada',
                    (string) $programacion['fecha_programada'],
                    PDO::PARAM_STR
                );

                $stmtIncumplimiento->execute();

                if (
                    $stmtIncumplimiento->rowCount() !== 1
                ) {
                    continue;
                }

                $incumplimientosNuevos++;

                masg_registrar_historial(
                    $conexion,
                    $solicitudId,
                    $asignacionId,
                    $programacionId,
                    'INCUMPLIMIENTO_DETECTADO',
                    $estadoSolicitudAnterior,
                    $estadoSolicitudActual,
                    'SISTEMA',
                    null,
                    'La participación asignada superó la fecha límite '
                    . 'sin que el mantenimiento se registrara como terminado.'
                );

                masg_insertar_notificacion(
                    $conexion,
                    'TECNICO',
                    $tecnicoAsignadoId,
                    $solicitudId,
                    null,
                    'Mantenimiento fuera de fecha',
                    'El mantenimiento '
                    . (string) $programacion['folio']
                    . ' superó su fecha límite y continúa pendiente de conclusión.',
                    'WARNING'
                );
            }
        }

        $conexion->commit();

        return [
            'programaciones_vencidas' =>
                $programacionesVencidas,

            'solicitudes_atrasadas' =>
                $solicitudesAtrasadas,

            'incumplimientos_nuevos' =>
                $incumplimientosNuevos,
        ];
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        throw $e;
    }
}

/* =========================================================================
   INICIAR O UNIRSE
   ========================================================================= */

function masg_iniciar(PDO $conexion): void
{
    $tecnicoId = masg_tecnico_id();

    $asignacionId = masg_id_positivo(
        $_POST['asignacion_id'] ?? null,
        'asignacion_id'
    );

    $conexion->beginTransaction();

    try {
        $perfil = masg_obtener_tecnico(
            $conexion,
            $tecnicoId,
            true
        );

        if (!$perfil) {
            throw new RuntimeException(
                'Tu cuenta de técnico está inactiva.'
            );
        }

        $asignacion = masg_bloquear_asignacion(
            $conexion,
            $asignacionId,
            $tecnicoId
        );

        if (!$asignacion) {
            throw new RuntimeException(
                'La asignación ya no está disponible para tu cuenta.'
            );
        }

        if (
            (string) $asignacion['origen']
            !== 'ADMIN'
        ) {
            throw new RuntimeException(
                'Esta actividad utiliza un flujo de atención diferente.'
            );
        }

        if (
            (int) $asignacion['asignacion_activa']
            !== 1
            || (int) $asignacion['activo_token']
            !== 1
        ) {
            throw new RuntimeException(
                'El administrador retiró esta asignación.'
            );
        }

        if (
            !in_array(
                (string) $asignacion['estado_asignacion'],
                ['ASIGNADO', 'ACEPTADO'],
                true
            )
        ) {
            throw new RuntimeException(
                'Esta participación ya fue iniciada, pausada, terminada o retirada.'
            );
        }

        if (
            (string) $asignacion['tipo_solicitud']
            === 'CORRECTIVO_URGENTE'
        ) {
            throw new RuntimeException(
                'Las urgencias deben iniciarse desde Urgencias disponibles.'
            );
        }

        if (
            !in_array(
                (string) $asignacion['estado_solicitud'],
                [
                    'AGENDADO',
                    'ATRASADO',
                    'EN_PROCESO',
                    'PAUSADO',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'El mantenimiento ya no se encuentra abierto.'
            );
        }

        if (
            empty($asignacion['programacion_id'])
            || (int) $asignacion['programacion_actual'] !== 1
        ) {
            throw new RuntimeException(
                'El mantenimiento no tiene una programación vigente.'
            );
        }

        if (
            !in_array(
                (string) $asignacion['estado_programacion'],
                ['PROGRAMADA', 'VENCIDA'],
                true
            )
        ) {
            throw new RuntimeException(
                'La programación fue cancelada, reemplazada o cerrada.'
            );
        }

        if (
            (string) $asignacion['fecha_programada']
            > date('Y-m-d')
        ) {
            throw new RuntimeException(
                'Todavía no llega el día programado para este mantenimiento.'
            );
        }

        if (!masg_validar_ubicacion_bloqueada(
            $asignacion
        )) {
            throw new RuntimeException(
                'El equipo o su ubicación están inactivos o ya no coinciden. Solicita revisión al administrador.'
            );
        }

        if (
            (int) (
                $asignacion['alerta_riesgo_nocturno']
                ?? 0
            ) === 1
            && (int) (
                $asignacion['riesgo_nocturno_confirmado']
                ?? 0
            ) !== 1
        ) {
            throw new RuntimeException(
                'El trabajo peligroso en turno nocturno todavía no cuenta con la confirmación administrativa de seguridad.'
            );
        }

        if (!empty($asignacion['cierre_id'])) {
            throw new RuntimeException(
                'El mantenimiento ya tiene un cierre registrado.'
            );
        }

        $actividadActiva = masg_bloquear_actividad_activa(
            $conexion,
            $tecnicoId,
            $asignacionId
        );

        if ($actividadActiva) {
            throw new RuntimeException(
                'Ya tienes el mantenimiento '
                . $actividadActiva['folio']
                . ' en proceso. Páusalo o finalízalo antes de iniciar otro.'
            );
        }

        $urgenciaAceptada = masg_bloquear_urgencia_aceptada(
            $conexion,
            $tecnicoId
        );

        if ($urgenciaAceptada) {
            throw new RuntimeException(
                'Tienes la urgencia '
                . $urgenciaAceptada['folio']
                . ' aceptada. Iníciala o libera tu lugar antes de continuar.'
            );
        }

        $ejecucion = masg_bloquear_ejecucion_asignacion(
            $conexion,
            $asignacionId
        );

        if ($ejecucion) {
            if (
                in_array(
                    (string) $ejecucion['estado'],
                    ['EN_PROCESO', 'PAUSADA'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Tu ejecución ya fue iniciada. Ábrela desde Actividad actual.'
                );
            }

            if (
                in_array(
                    (string) $ejecucion['estado'],
                    ['TERMINADA', 'CANCELADA'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'La ejecución ya fue cerrada o cancelada.'
                );
            }
        }

        $estadoAnterior =
            (string) $asignacion['estado_solicitud'];

        $fechaLimite =
            (string) $asignacion['fecha_limite'];

        $esInicioTarde =
            $fechaLimite !== ''
            && $fechaLimite < date('Y-m-d');

        if ($esInicioTarde) {
            $stmtVencida = $conexion->prepare(
                "
                UPDATE programaciones_mantenimiento
                SET estado = 'VENCIDA'
                WHERE id = :programacion_id
                  AND es_actual = 1
                  AND estado = 'PROGRAMADA'
                "
            );

            $stmtVencida->bindValue(
                ':programacion_id',
                (int) $asignacion['programacion_id'],
                PDO::PARAM_INT
            );

            $stmtVencida->execute();

            if ($estadoAnterior === 'AGENDADO') {
                $stmtAtrasada = $conexion->prepare(
                    "
                    UPDATE solicitudes
                    SET estado = 'ATRASADO'
                    WHERE id = :solicitud_id
                      AND estado = 'AGENDADO'
                      AND activo = 1
                    "
                );

                $stmtAtrasada->bindValue(
                    ':solicitud_id',
                    (int) $asignacion['solicitud_id'],
                    PDO::PARAM_INT
                );

                $stmtAtrasada->execute();

                if (
                    $stmtAtrasada->rowCount() === 1
                ) {
                    masg_registrar_historial(
                        $conexion,
                        (int) $asignacion['solicitud_id'],
                        $asignacionId,
                        (int) $asignacion['programacion_id'],
                        'INCUMPLIMIENTO_DETECTADO',
                        'AGENDADO',
                        'ATRASADO',
                        'SISTEMA',
                        null,
                        'El mantenimiento venció antes de que el técnico registrara su inicio.'
                    );

                    $estadoAnterior = 'ATRASADO';
                }
            }

            $stmtIncumplimiento = $conexion->prepare(
                "
                INSERT IGNORE INTO incumplimientos_mantenimiento
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
                    :asignacion_id,
                    :fecha_programada,
                    NOW(),
                    'PENDIENTE'
                )
                "
            );

            $stmtIncumplimiento->bindValue(
                ':solicitud_id',
                (int) $asignacion['solicitud_id'],
                PDO::PARAM_INT
            );

            $stmtIncumplimiento->bindValue(
                ':programacion_id',
                (int) $asignacion['programacion_id'],
                PDO::PARAM_INT
            );

            $stmtIncumplimiento->bindValue(
                ':asignacion_id',
                $asignacionId,
                PDO::PARAM_INT
            );

            $stmtIncumplimiento->bindValue(
                ':fecha_programada',
                (string) $asignacion['fecha_programada'],
                PDO::PARAM_STR
            );

            $stmtIncumplimiento->execute();
        }

        if ($ejecucion) {
            $stmtEjecucion = $conexion->prepare(
                "
                UPDATE ejecuciones_mantenimiento
                SET
                    estado = 'EN_PROCESO',
                    fecha_hora_inicio = NOW(),
                    fecha_ultima_reanudacion = NOW(),
                    fecha_hora_inicio_original = COALESCE(
                        fecha_hora_inicio_original,
                        NOW()
                    ),
                    fecha_hora_fin = NULL,
                    total_segundos_activos = 0,
                    total_segundos_pausa = 0,
                    en_proceso_token = 1,
                    iniciada_por_tipo = 'TECNICO',
                    iniciada_por_id = :tecnico_id
                WHERE id = :ejecucion_id
                  AND solicitud_tecnico_id = :asignacion_id
                  AND tecnico_id = :tecnico_id_revision
                  AND estado = 'PENDIENTE'
                "
            );

            $stmtEjecucion->bindValue(
                ':tecnico_id',
                $tecnicoId,
                PDO::PARAM_INT
            );

            $stmtEjecucion->bindValue(
                ':ejecucion_id',
                (int) $ejecucion['id'],
                PDO::PARAM_INT
            );

            $stmtEjecucion->bindValue(
                ':asignacion_id',
                $asignacionId,
                PDO::PARAM_INT
            );

            $stmtEjecucion->bindValue(
                ':tecnico_id_revision',
                $tecnicoId,
                PDO::PARAM_INT
            );

            $stmtEjecucion->execute();

            if (
                $stmtEjecucion->rowCount() !== 1
            ) {
                throw new RuntimeException(
                    'La ejecución cambió mientras intentabas iniciarla.'
                );
            }

            $ejecucionId =
                (int) $ejecucion['id'];
        } else {
            $stmtEjecucion = $conexion->prepare(
                "
                INSERT INTO ejecuciones_mantenimiento
                (
                    solicitud_id,
                    solicitud_tecnico_id,
                    tecnico_id,
                    estado,
                    fecha_hora_inicio,
                    fecha_ultima_reanudacion,
                    fecha_hora_inicio_original,
                    total_segundos_pausa,
                    total_segundos_activos,
                    en_proceso_token,
                    iniciada_por_tipo,
                    iniciada_por_id
                )
                VALUES
                (
                    :solicitud_id,
                    :asignacion_id,
                    :tecnico_id,
                    'EN_PROCESO',
                    NOW(),
                    NOW(),
                    NOW(),
                    0,
                    0,
                    1,
                    'TECNICO',
                    :iniciada_por_id
                )
                "
            );

            $stmtEjecucion->bindValue(
                ':solicitud_id',
                (int) $asignacion['solicitud_id'],
                PDO::PARAM_INT
            );

            $stmtEjecucion->bindValue(
                ':asignacion_id',
                $asignacionId,
                PDO::PARAM_INT
            );

            $stmtEjecucion->bindValue(
                ':tecnico_id',
                $tecnicoId,
                PDO::PARAM_INT
            );

            $stmtEjecucion->bindValue(
                ':iniciada_por_id',
                $tecnicoId,
                PDO::PARAM_INT
            );

            $stmtEjecucion->execute();

            $ejecucionId = (int) $conexion->lastInsertId();

            if ($ejecucionId <= 0) {
                throw new RuntimeException(
                    'No fue posible crear la ejecución del mantenimiento.'
                );
            }
        }

        $stmtAsignacion = $conexion->prepare(
            "
            UPDATE solicitud_tecnicos
            SET estado = 'EN_PROCESO'
            WHERE id = :asignacion_id
              AND tecnico_id = :tecnico_id
              AND activo = 1
              AND activo_token = 1
              AND estado IN (
                  'ASIGNADO',
                  'ACEPTADO'
              )
            "
        );

        $stmtAsignacion->bindValue(
            ':asignacion_id',
            $asignacionId,
            PDO::PARAM_INT
        );

        $stmtAsignacion->bindValue(
            ':tecnico_id',
            $tecnicoId,
            PDO::PARAM_INT
        );

        $stmtAsignacion->execute();

        if (
            $stmtAsignacion->rowCount() !== 1
        ) {
            throw new RuntimeException(
                'La asignación cambió antes de registrar el inicio.'
            );
        }

        $stmtSolicitud = $conexion->prepare(
            "
            UPDATE solicitudes
            SET estado = 'EN_PROCESO'
            WHERE id = :solicitud_id
              AND activo = 1
              AND estado IN (
                  'AGENDADO',
                  'ATRASADO',
                  'EN_PROCESO',
                  'PAUSADO'
              )
            "
        );

        $stmtSolicitud->bindValue(
            ':solicitud_id',
            (int) $asignacion['solicitud_id'],
            PDO::PARAM_INT
        );

        $stmtSolicitud->execute();

        masg_registrar_historial(
            $conexion,
            (int) $asignacion['solicitud_id'],
            $asignacionId,
            (int) $asignacion['programacion_id'],
            'INICIADA',
            $estadoAnterior,
            'EN_PROCESO',
            'TECNICO',
            $tecnicoId,
            (
                (int) $asignacion['tecnicos_iniciaron'] > 0
                    ? 'El técnico se unió al mantenimiento que ya estaba siendo atendido.'
                    : 'El técnico inició el mantenimiento asignado.'
            )
        );

        $nombreTecnico = trim(
            (string) $perfil['nombre_completo']
        );

        $accionMovimiento =
            (int) $asignacion['tecnicos_iniciaron'] > 0
                ? 'UNIRSE_MANTENIMIENTO'
                : 'INICIAR_MANTENIMIENTO';

        masg_registrar_movimiento(
            $conexion,
            $tecnicoId,
            $accionMovimiento,
            (
                (int) $asignacion['tecnicos_iniciaron'] > 0
                    ? 'El técnico '
                        . $nombreTecnico
                        . ' se unió al mantenimiento '
                        . $asignacion['folio']
                        . '.'
                    : 'El técnico '
                        . $nombreTecnico
                        . ' inició el mantenimiento '
                        . $asignacion['folio']
                        . '.'
            ),
            'ejecuciones_mantenimiento',
            $ejecucionId
        );

        masg_notificar_inicio(
            $conexion,
            $asignacion,
            $tecnicoId,
            $nombreTecnico,
            $ejecucionId
        );

        $conexion->commit();

        sm_responder_json(
            true,
            (
                (int) $asignacion['tecnicos_iniciaron'] > 0
                    ? 'Te uniste correctamente al mantenimiento.'
                    : 'Mantenimiento iniciado correctamente.'
            ),
            [
                'solicitud_id' =>
                    (int) $asignacion['solicitud_id'],

                'asignacion_id' =>
                    $asignacionId,

                'ejecucion_id' =>
                    $ejecucionId,

                'estado' =>
                    'EN_PROCESO',
            ]
        );
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        throw $e;
    }
}

function masg_bloquear_asignacion(
    PDO $conexion,
    int $asignacionId,
    int $tecnicoId
): ?array {
    $stmt = $conexion->prepare(
        "
        SELECT
            st.id AS asignacion_id,
            st.solicitud_id,
            st.programacion_id,
            st.tecnico_id,
            st.origen,
            st.estado AS estado_asignacion,
            st.activo AS asignacion_activa,
            st.activo_token,
            st.alerta_riesgo_nocturno,
            st.riesgo_nocturno_confirmado,
            st.observacion_riesgo_nocturno,

            s.folio,
            s.tipo_solicitud,
            s.estado AS estado_solicitud,
            s.solicitante_id,
            s.administrador_solicitante_id,
            s.departamento_id,
            s.area_id,
            s.proceso_id,
            s.equipo_id,
            s.trabajo_peligroso,
            s.detalle_trabajo_peligroso,
            s.nivel_riesgo,

            pm.fecha_programada,
            pm.fecha_limite,
            pm.estado AS estado_programacion,
            pm.es_actual AS programacion_actual,

            e.activo AS equipo_activo,
            d.activo AS departamento_activo,
            a.activo AS area_activa,
            a.departamento_id AS area_departamento_id,
            p.activo AS proceso_activo,
            p.area_id AS proceso_area_id,

            cm.id AS cierre_id,

            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos st_inicio
                INNER JOIN ejecuciones_mantenimiento em_inicio
                        ON em_inicio.solicitud_tecnico_id = st_inicio.id
                WHERE st_inicio.solicitud_id = st.solicitud_id
                  AND st_inicio.origen = 'ADMIN'
                  AND st_inicio.activo = 1
                  AND em_inicio.fecha_hora_inicio IS NOT NULL
            ) AS tecnicos_iniciaron

        FROM solicitud_tecnicos st

        INNER JOIN solicitudes s
                ON s.id = st.solicitud_id

        LEFT JOIN programaciones_mantenimiento pm
               ON pm.id = st.programacion_id

        INNER JOIN equipos e
                ON e.id = s.equipo_id

        INNER JOIN departamentos d
                ON d.id = s.departamento_id

        INNER JOIN areas a
                ON a.id = s.area_id

        INNER JOIN procesos p
                ON p.id = s.proceso_id

        LEFT JOIN cierres_mantenimiento cm
               ON cm.solicitud_id = s.id

        WHERE st.id = :asignacion_id
          AND st.tecnico_id = :tecnico_id
        LIMIT 1
        FOR UPDATE
        "
    );

    $stmt->bindValue(
        ':asignacion_id',
        $asignacionId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':tecnico_id',
        $tecnicoId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $registro = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return is_array($registro)
        ? $registro
        : null;
}

function masg_validar_ubicacion_bloqueada(
    array $asignacion
): bool {
    return
        (int) $asignacion['equipo_activo'] === 1
        && (int) $asignacion['departamento_activo'] === 1
        && (int) $asignacion['area_activa'] === 1
        && (int) $asignacion['proceso_activo'] === 1
        && (int) $asignacion['area_departamento_id']
            === (int) $asignacion['departamento_id']
        && (int) $asignacion['proceso_area_id']
            === (int) $asignacion['area_id'];
}

function masg_bloquear_actividad_activa(
    PDO $conexion,
    int $tecnicoId,
    int $asignacionActualId
): ?array {
    $stmt = $conexion->prepare(
        "
        SELECT
            em.id AS ejecucion_id,
            em.solicitud_tecnico_id AS asignacion_id,
            s.folio
        FROM ejecuciones_mantenimiento em
        INNER JOIN solicitudes s
                ON s.id = em.solicitud_id
        WHERE em.tecnico_id = :tecnico_id
          AND em.estado = 'EN_PROCESO'
          AND em.solicitud_tecnico_id <> :asignacion_id
        ORDER BY em.fecha_hora_inicio ASC
        LIMIT 1
        FOR UPDATE
        "
    );

    $stmt->bindValue(
        ':tecnico_id',
        $tecnicoId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':asignacion_id',
        $asignacionActualId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $registro = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return is_array($registro)
        ? $registro
        : null;
}

function masg_bloquear_urgencia_aceptada(
    PDO $conexion,
    int $tecnicoId
): ?array {
    $stmt = $conexion->prepare(
        "
        SELECT
            st.id AS asignacion_id,
            s.id AS solicitud_id,
            s.folio
        FROM solicitud_tecnicos st
        INNER JOIN solicitudes s
                ON s.id = st.solicitud_id
        LEFT JOIN ejecuciones_mantenimiento em
               ON em.solicitud_tecnico_id = st.id
        WHERE st.tecnico_id = :tecnico_id
          AND st.origen = 'ACEPTACION_URGENTE'
          AND st.activo = 1
          AND st.estado = 'ACEPTADO'
          AND em.id IS NULL
          AND s.activo = 1
          AND s.estado IN (
              'AGENDADO',
              'EN_PROCESO',
              'PAUSADO',
              'ATRASADO'
          )
        ORDER BY st.fecha_aceptacion ASC
        LIMIT 1
        FOR UPDATE
        "
    );

    $stmt->bindValue(
        ':tecnico_id',
        $tecnicoId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $registro = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return is_array($registro)
        ? $registro
        : null;
}

function masg_bloquear_ejecucion_asignacion(
    PDO $conexion,
    int $asignacionId
): ?array {
    $stmt = $conexion->prepare(
        "
        SELECT *
        FROM ejecuciones_mantenimiento
        WHERE solicitud_tecnico_id = :asignacion_id
        LIMIT 1
        FOR UPDATE
        "
    );

    $stmt->bindValue(
        ':asignacion_id',
        $asignacionId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $registro = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return is_array($registro)
        ? $registro
        : null;
}

/* =========================================================================
   NOTIFICACIONES, HISTORIAL Y AUDITORÍA
   ========================================================================= */

function masg_notificar_inicio(
    PDO $conexion,
    array $asignacion,
    int $tecnicoId,
    string $nombreTecnico,
    int $ejecucionId
): void {
    $solicitudId =
        (int) $asignacion['solicitud_id'];

    $folio =
        (string) $asignacion['folio'];

    $seUnio =
        (int) $asignacion['tecnicos_iniciaron'] > 0;

    $titulo = $seUnio
        ? 'Técnico agregado al mantenimiento'
        : 'Mantenimiento iniciado';

    $mensaje = $seUnio
        ? $nombreTecnico
            . ' se unió al mantenimiento '
            . $folio
            . '.'
        : $nombreTecnico
            . ' inició el mantenimiento '
            . $folio
            . '.';

    $stmtAdmins = $conexion->prepare(
        "
        SELECT id
        FROM administradores
        WHERE activo = 1
        ORDER BY id
        "
    );

    $stmtAdmins->execute();

    foreach (
        $stmtAdmins->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: []
        as $adminId
    ) {
        masg_insertar_notificacion(
            $conexion,
            'ADMIN',
            (int) $adminId,
            $solicitudId,
            $ejecucionId,
            $titulo,
            $mensaje,
            'INFO'
        );
    }

    if (
        !empty($asignacion['solicitante_id'])
    ) {
        masg_insertar_notificacion(
            $conexion,
            'SOLICITANTE',
            (int) $asignacion['solicitante_id'],
            $solicitudId,
            $ejecucionId,
            $titulo,
            $mensaje,
            'INFO'
        );
    }

    $stmtTecnicos = $conexion->prepare(
        "
        SELECT DISTINCT tecnico_id
        FROM solicitud_tecnicos
        WHERE solicitud_id = :solicitud_id
          AND origen = 'ADMIN'
          AND activo = 1
          AND tecnico_id <> :tecnico_id
          AND estado IN (
              'ASIGNADO',
              'ACEPTADO',
              'EN_PROCESO',
              'PAUSADO'
          )
        "
    );

    $stmtTecnicos->bindValue(
        ':solicitud_id',
        $solicitudId,
        PDO::PARAM_INT
    );

    $stmtTecnicos->bindValue(
        ':tecnico_id',
        $tecnicoId,
        PDO::PARAM_INT
    );

    $stmtTecnicos->execute();

    foreach (
        $stmtTecnicos->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: []
        as $otroTecnicoId
    ) {
        masg_insertar_notificacion(
            $conexion,
            'TECNICO',
            (int) $otroTecnicoId,
            $solicitudId,
            $ejecucionId,
            $titulo,
            $mensaje,
            'INFO'
        );
    }
}

function masg_insertar_notificacion(
    PDO $conexion,
    string $tipoUsuario,
    int $usuarioId,
    ?int $solicitudId,
    ?int $ejecucionId,
    string $titulo,
    string $mensaje,
    string $tipo
): void {
    if ($usuarioId <= 0) {
        return;
    }

    $stmt = $conexion->prepare(
        "
        INSERT INTO notificaciones
        (
            tipo_usuario,
            usuario_id,
            solicitud_id,
            ejecucion_id,
            titulo,
            mensaje,
            tipo,
            leida
        )
        VALUES
        (
            :tipo_usuario,
            :usuario_id,
            :solicitud_id,
            :ejecucion_id,
            :titulo,
            :mensaje,
            :tipo,
            0
        )
        "
    );

    $stmt->bindValue(
        ':tipo_usuario',
        $tipoUsuario,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':usuario_id',
        $usuarioId,
        PDO::PARAM_INT
    );

    if ($solicitudId === null) {
        $stmt->bindValue(
            ':solicitud_id',
            null,
            PDO::PARAM_NULL
        );
    } else {
        $stmt->bindValue(
            ':solicitud_id',
            $solicitudId,
            PDO::PARAM_INT
        );
    }

    if ($ejecucionId === null) {
        $stmt->bindValue(
            ':ejecucion_id',
            null,
            PDO::PARAM_NULL
        );
    } else {
        $stmt->bindValue(
            ':ejecucion_id',
            $ejecucionId,
            PDO::PARAM_INT
        );
    }

    $stmt->bindValue(
        ':titulo',
        mb_substr($titulo, 0, 180),
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':mensaje',
        mb_substr($mensaje, 0, 1000),
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':tipo',
        $tipo,
        PDO::PARAM_STR
    );

    $stmt->execute();
}

function masg_registrar_historial(
    PDO $conexion,
    int $solicitudId,
    ?int $asignacionId,
    ?int $programacionId,
    string $evento,
    ?string $estadoAnterior,
    ?string $estadoNuevo,
    string $actorTipo,
    ?int $actorId,
    string $descripcion
): void {
    $stmt = $conexion->prepare(
        "
        INSERT INTO historial_solicitudes
        (
            solicitud_id,
            solicitud_tecnico_id,
            programacion_id,
            evento,
            estado_anterior,
            estado_nuevo,
            actor_tipo,
            actor_id,
            descripcion
        )
        VALUES
        (
            :solicitud_id,
            :asignacion_id,
            :programacion_id,
            :evento,
            :estado_anterior,
            :estado_nuevo,
            :actor_tipo,
            :actor_id,
            :descripcion
        )
        "
    );

    $stmt->bindValue(
        ':solicitud_id',
        $solicitudId,
        PDO::PARAM_INT
    );

    masg_bind_nullable_int(
        $stmt,
        ':asignacion_id',
        $asignacionId
    );

    masg_bind_nullable_int(
        $stmt,
        ':programacion_id',
        $programacionId
    );

    $stmt->bindValue(
        ':evento',
        $evento,
        PDO::PARAM_STR
    );

    masg_bind_nullable_string(
        $stmt,
        ':estado_anterior',
        $estadoAnterior
    );

    masg_bind_nullable_string(
        $stmt,
        ':estado_nuevo',
        $estadoNuevo
    );

    $stmt->bindValue(
        ':actor_tipo',
        $actorTipo,
        PDO::PARAM_STR
    );

    masg_bind_nullable_int(
        $stmt,
        ':actor_id',
        $actorId
    );

    $stmt->bindValue(
        ':descripcion',
        $descripcion,
        PDO::PARAM_STR
    );

    $stmt->execute();
}

function masg_registrar_movimiento(
    PDO $conexion,
    int $tecnicoId,
    string $accion,
    string $descripcion,
    string $tabla,
    int $registroId
): void {
    $stmt = $conexion->prepare(
        "
        INSERT INTO movimientos_sistema
        (
            tipo_usuario,
            usuario_id,
            accion,
            modulo,
            descripcion,
            tabla_afectada,
            registro_id,
            ip_address,
            user_agent
        )
        VALUES
        (
            'TECNICO',
            :usuario_id,
            :accion,
            'Mantenimientos asignados',
            :descripcion,
            :tabla_afectada,
            :registro_id,
            :ip_address,
            :user_agent
        )
        "
    );

    $stmt->bindValue(
        ':usuario_id',
        $tecnicoId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':accion',
        $accion,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':descripcion',
        $descripcion,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':tabla_afectada',
        $tabla,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':registro_id',
        $registroId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':ip_address',
        masg_ip(),
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':user_agent',
        masg_user_agent(),
        PDO::PARAM_STR
    );

    $stmt->execute();
}

/* =========================================================================
   PERFIL Y RESUMEN
   ========================================================================= */

function masg_obtener_tecnico(
    PDO $conexion,
    int $tecnicoId,
    bool $bloquear = false
): ?array {
    $sql = "
        SELECT
            t.id,
            t.usuario,
            t.nombre,
            t.apellido_paterno,
            t.apellido_materno,
            TRIM(
                CONCAT_WS(
                    ' ',
                    t.nombre,
                    t.apellido_paterno,
                    t.apellido_materno
                )
            ) AS nombre_completo,
            t.turno,
            t.especialidad,
            t.departamento_id,
            d.nombre AS departamento,
            t.activo
        FROM tecnicos t
        LEFT JOIN departamentos d
               ON d.id = t.departamento_id
        WHERE t.id = :tecnico_id
          AND t.activo = 1
        LIMIT 1
    ";

    if ($bloquear) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $conexion->prepare($sql);

    $stmt->bindValue(
        ':tecnico_id',
        $tecnicoId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $registro = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return is_array($registro)
        ? $registro
        : null;
}

function masg_generar_resumen(
    array $registros
): array {
    $resumen = [
        'total' => count($registros),
        'disponibles' => 0,
        'hoy' => 0,
        'proximos' => 0,
        'atrasados' => 0,
        'equipo_trabajando' => 0,
        'actividades_abiertas' => 0,
    ];

    foreach ($registros as $registro) {
        if (
            (int) (
                $registro['puede_iniciar']
                ?? 0
            ) === 1
        ) {
            $resumen['disponibles']++;
        }

        if (
            (string) (
                $registro['categoria']
                ?? ''
            ) === 'HOY'
        ) {
            $resumen['hoy']++;
        }

        if (
            (string) (
                $registro['categoria']
                ?? ''
            ) === 'PROXIMA'
        ) {
            $resumen['proximos']++;
        }

        if (
            (string) (
                $registro['categoria']
                ?? ''
            ) === 'ATRASADA'
        ) {
            $resumen['atrasados']++;
        }

        if (
            (int) (
                $registro['equipo_para_unirse']
                ?? 0
            ) === 1
        ) {
            $resumen['equipo_trabajando']++;
        }

        if (
            in_array(
                (string) (
                    $registro['estado_ejecucion']
                    ?? ''
                ),
                ['EN_PROCESO', 'PAUSADA'],
                true
            )
        ) {
            $resumen['actividades_abiertas']++;
        }
    }

    return $resumen;
}

/* =========================================================================
   UTILIDADES
   ========================================================================= */

function masg_tecnico_id(): int
{
    $tecnicoId = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        $tecnicoId === false
        || (int) $tecnicoId <= 0
    ) {
        sm_responder_json(
            false,
            'La sesión del técnico no es válida.',
            [
                'sesion_expirada' => true,
                'redirect' =>
                    '../login.php?sesion=expirada',
            ],
            401
        );
    }

    return (int) $tecnicoId;
}

function masg_id_positivo(
    $valor,
    string $campo
): int {
    $id = filter_var(
        $valor,
        FILTER_VALIDATE_INT
    );

    if (
        $id === false
        || (int) $id <= 0
    ) {
        sm_responder_json(
            false,
            'El mantenimiento seleccionado no es válido.',
            ['campo' => $campo],
            422
        );
    }

    return (int) $id;
}

function masg_diferencia_dias(
    string $desde,
    string $hasta
): int {
    try {
        $fechaDesde = new DateTimeImmutable(
            $desde
        );

        $fechaHasta = new DateTimeImmutable(
            $hasta
        );

        return (int) $fechaDesde
            ->diff($fechaHasta)
            ->format('%r%a');
        } catch (Throwable $e) {
        return 0;
    }
}

function masg_fecha_relativa(
    string $fechaProgramada,
    string $fechaLimite
): string {
    if ($fechaProgramada === '') {
        return 'Sin programación';
    }

    $hoy = date('Y-m-d');

    if (
        $fechaLimite !== ''
        && $fechaLimite < $hoy
    ) {
        $dias = max(
            1,
            masg_diferencia_dias(
                $fechaLimite,
                $hoy
            )
        );

        return $dias === 1
            ? '1 día de atraso'
            : $dias . ' días de atraso';
    }

    if ($fechaProgramada === $hoy) {
        return 'Programado para hoy';
    }

    if ($fechaProgramada > $hoy) {
        $dias = max(
            1,
            masg_diferencia_dias(
                $hoy,
                $fechaProgramada
            )
        );

        return $dias === 1
            ? 'Programado para mañana'
            : 'Programado en '
                . $dias
                . ' días';
    }

    return 'Disponible para iniciar';
}

function masg_formatear_fecha(
    string $fecha
): string {
    if ($fecha === '') {
        return '';
    }

    try {
        return (
            new DateTimeImmutable($fecha)
        )->format('d/m/Y');
    } catch (Throwable $e) {
        return $fecha;
    }
}

function masg_formatear_hora(
    string $hora
): string {
    if ($hora === '') {
        return '';
    }

    try {
        return (
            new DateTimeImmutable($hora)
        )->format('H:i');
    } catch (Throwable $e) {
        return $hora;
    }
}

function masg_formatear_fecha_hora(
    string $fechaHora
): string {
    if ($fechaHora === '') {
        return '';
    }

    try {
        return (
            new DateTimeImmutable($fechaHora)
        )->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return $fechaHora;
    }
}

function masg_contacto_solicitante(
    string $telefono,
    string $correo
): string {
    $partes = []; 

    $telefono = trim($telefono);
    $correo = trim($correo);

    if ($telefono !== '') {
        $partes[] = $telefono;
    }

    if ($correo !== '') {
        $partes[] = $correo;
    }

    return $partes !== []
        ? implode(' · ', $partes)
        : 'Sin datos de contacto';
}

function masg_bind_nullable_int(
    PDOStatement $stmt,
    string $parametro,
    ?int $valor
): void {
    if ($valor === null) {
        $stmt->bindValue(
            $parametro,
            null,
            PDO::PARAM_NULL
        );

        return;
    }

    $stmt->bindValue(
        $parametro,
        $valor,
        PDO::PARAM_INT
    );
}

function masg_bind_nullable_string(
    PDOStatement $stmt,
    string $parametro,
    ?string $valor
): void {
    if (
        $valor === null
        || trim($valor) === ''
    ) {
        $stmt->bindValue(
            $parametro,
            null,
            PDO::PARAM_NULL
        );

        return;
    }

    $stmt->bindValue(
        $parametro,
        $valor,
        PDO::PARAM_STR
    );
}

function masg_ip(): string
{
    return mb_substr(
        (string) (
            $_SERVER['REMOTE_ADDR']
            ?? ''
        ),
        0,
        60
    );
}

function masg_user_agent(): string
{
    return mb_substr(
        (string) (
            $_SERVER['HTTP_USER_AGENT']
            ?? ''
        ),
        0,
        255
    );
}