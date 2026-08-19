<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Programar y asignar mantenimientos - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| - Exclusivo para ADMIN.
| - Programa por día; no utiliza hora ni duración estimada.
| - Permite planificar semanas futuras y consultar la carga real de cada
|   técnico antes de seleccionarlo.
| - Filtra técnicos por turno, especialidad, departamento y carga.
| - Permite reprogramar y cambiar técnicos mientras no se retire a un
|   técnico que ya inició. La fecha queda bloqueada si alguien ya comenzó.
| - Un trabajo peligroso con técnico NOCTURNO genera advertencia y exige
|   confirmación administrativa; el turno no impide la asignación.
| - Los mantenimientos vencidos siguen disponibles y pueden reprogramarse.
| - El calendario aplica una regla base sin preparar el mes: lunes a viernes
|   son HÁBILES; sábado y domingo son INHÁBILES. Las excepciones se registran
|   como HÁBIL_EXTRA o INHÁBIL desde Calendario laboral.
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

rsm_verificar_estructura($conexion);

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = $metodo === 'GET'
    ? sm_limpiar_texto($_GET['accion'] ?? 'inicial')
    : sm_limpiar_texto($_POST['accion'] ?? '');

try {
    if ($metodo === 'GET') {
        if ($accion === 'inicial') {
            sprog_cargar_inicial($conexion);
        }

        if ($accion === 'detalle') {
            sprog_cargar_detalle($conexion);
        }

        if ($accion === 'tecnicos') {
            sprog_cargar_tecnicos($conexion);
        }

        if ($accion === 'calendario') {
            sprog_consultar_calendario_endpoint($conexion);
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

    if ($accion === 'guardar') {
        sprog_guardar_programacion($conexion);
    }

    if ($accion === 'cancelar_mantenimiento') {
        sprog_cancelar_mantenimiento($conexion);
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

    error_log('[PROGRAMAR Y ASIGNAR][PDO] ' . $e->getMessage());

    if ((string) $e->getCode() === '23000') {
        sm_responder_json(
            false,
            'La programación cambió mientras realizabas la operación. Actualiza la pantalla e inténtalo nuevamente.',
            [],
            409
        );
    }

    sm_responder_json(
        false,
        'Ocurrió un error interno al guardar la programación.',
        [],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[PROGRAMAR Y ASIGNAR] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la información.',
        [],
        500
    );
}

/* =========================================================================
   ENDPOINTS DE CONSULTA
   ========================================================================= */

function sprog_cargar_inicial(PDO $conexion): void
{
    sprog_validar_admin_activo($conexion, sprog_admin_id());
    sprog_actualizar_atrasos($conexion);

    $semana = sprog_semana_desde_entrada($_GET['semana'] ?? null, true);
    $inicio = $semana['inicio'];
    $fin = $semana['fin'];

    sm_responder_json(
        true,
        'Programaciones cargadas correctamente.',
        [
            'resumen' => sprog_consultar_resumen($conexion, $inicio, $fin),
            'solicitudes' => sprog_consultar_solicitudes($conexion, $inicio, $fin),
            'semana' => [
                'inicio' => $inicio,
                'fin' => $fin,
                'dias' => sprog_consultar_semana_calendario($conexion, $inicio, $fin),
            ],
            'catalogos' => sprog_consultar_catalogos_tecnicos($conexion),
            'reglas' => [
                'maximo_tecnicos' => 5,
                'usa_hora' => false,
                'usa_horas_estimadas' => false,
                'mes_preparado_requerido' => false,
                'reprogramacion_permitida_antes_inicio' => true,
            ],
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function sprog_cargar_detalle(PDO $conexion): void
{
    sprog_validar_admin_activo($conexion, sprog_admin_id());

    $solicitudId = sprog_id_entrada($_GET['id'] ?? null, 'id');
    $detalle = sprog_consultar_detalle_solicitud($conexion, $solicitudId);

    if (!$detalle) {
        sm_responder_json(
            false,
            'La solicitud no existe o ya no se encuentra disponible para programación.',
            [],
            404
        );
    }

    $asignaciones = sprog_consultar_asignaciones_actuales($conexion, $solicitudId);
    $recursosDetalle = sprog_preparar_recursos_detalle($conexion, $detalle);
    $fecha = (string) ($detalle['fecha_programada'] ?? '');

    if ($fecha === '' || !sprog_fecha_valida($fecha)) {
        $fecha = sprog_siguiente_dia_programable($conexion, date('Y-m-d'));
    }

    $semana = sprog_semana_desde_entrada($fecha, false);

    sm_responder_json(
        true,
        'Detalle cargado correctamente.',
        [
            'solicitud' => $detalle,
            'asignaciones' => $asignaciones,
            'recursos_recomendados' => $recursosDetalle['recursos'],
            'recursos_contexto' => $recursosDetalle['contexto'],
            'fecha_sugerida_programacion' => $fecha,
            'calendario' => sprog_estado_calendario($conexion, $fecha),
            'semana' => [
                'inicio' => $semana['inicio'],
                'fin' => $semana['fin'],
            ],
            'tecnicos' => sprog_consultar_tecnicos(
                $conexion,
                $solicitudId,
                $fecha,
                $semana['inicio'],
                $semana['fin']
            ),
            'puede_cambiar_fecha' => (int) $detalle['total_iniciados'] === 0,
            'puede_editar_recursos' => (int) $detalle['total_iniciados'] === 0,
            'puede_editar_equipo' => !in_array(
                (string) $detalle['estado'],
                ['TERMINADO', 'RECHAZADO', 'CANCELADO'],
                true
            ),
        ]
    );
}

function sprog_cargar_tecnicos(PDO $conexion): void
{
    sprog_validar_admin_activo($conexion, sprog_admin_id());

    $solicitudId = sprog_id_entrada($_GET['solicitud_id'] ?? null, 'solicitud_id');
    $fecha = sprog_fecha_entrada($_GET['fecha'] ?? null, 'fecha');
    $semana = sprog_semana_desde_entrada($fecha, false);

    $detalle = sprog_consultar_detalle_solicitud($conexion, $solicitudId);
    if (!$detalle) {
        sm_responder_json(false, 'La solicitud ya no está disponible.', [], 404);
    }

    sm_responder_json(
        true,
        'Carga de técnicos actualizada.',
        [
            'tecnicos' => sprog_consultar_tecnicos(
                $conexion,
                $solicitudId,
                $fecha,
                $semana['inicio'],
                $semana['fin']
            ),
            'calendario' => sprog_estado_calendario($conexion, $fecha),
            'semana' => [
                'inicio' => $semana['inicio'],
                'fin' => $semana['fin'],
            ],
        ]
    );
}

function sprog_consultar_calendario_endpoint(PDO $conexion): void
{
    sprog_validar_admin_activo($conexion, sprog_admin_id());
    $fecha = sprog_fecha_entrada($_GET['fecha'] ?? null, 'fecha');

    sm_responder_json(
        true,
        'Día consultado correctamente.',
        ['calendario' => sprog_estado_calendario($conexion, $fecha)]
    );
}

/* =========================================================================
   GUARDAR PROGRAMACIÓN Y ASIGNACIÓN
   ========================================================================= */

function sprog_guardar_programacion(PDO $conexion): void
{
    $adminId = sprog_admin_id();
    sprog_validar_admin_activo($conexion, $adminId);

    $solicitudId = sprog_id_entrada($_POST['solicitud_id'] ?? null, 'solicitud_id');
    $fechaProgramada = sprog_fecha_entrada($_POST['fecha_programada'] ?? null, 'fecha_programada');
    $tecnicosIds = sprog_normalizar_ids($_POST['tecnicos'] ?? []);
    $motivo = sprog_texto($_POST['motivo'] ?? '', 0, 500, 'motivo');
    $confirmacionNocturna = (string) ($_POST['confirmar_riesgo_nocturno'] ?? '') === '1';
    $observacionNocturna = sprog_texto(
        $_POST['observacion_riesgo_nocturno'] ?? '',
        0,
        500,
        'observacion_riesgo_nocturno'
    );
    $herramientasIds = sprog_normalizar_ids($_POST['herramientas_ids'] ?? []);
    $refaccionesIds = sprog_normalizar_ids($_POST['refacciones_ids'] ?? []);

    if (count($herramientasIds) > 80 || count($refaccionesIds) > 80) {
        sm_responder_json(
            false,
            'Puedes seleccionar como máximo 80 herramientas y 80 refacciones por mantenimiento.',
            ['campo' => 'recursos_recomendados'],
            422
        );
    }

    if (count($tecnicosIds) < 1 || count($tecnicosIds) > 5) {
        sm_responder_json(
            false,
            'Selecciona entre 1 y 5 técnicos.',
            ['campo' => 'tecnicos'],
            422
        );
    }

    if ($fechaProgramada < date('Y-m-d')) {
        sm_responder_json(
            false,
            'No puedes programar un mantenimiento en una fecha pasada.',
            ['campo' => 'fecha_programada'],
            422
        );
    }

    $estadoCalendario = sprog_estado_calendario($conexion, $fechaProgramada);
    if (!$estadoCalendario['permitido']) {
        sm_responder_json(
            false,
            (string) $estadoCalendario['mensaje'],
            [
                'campo' => 'fecha_programada',
                'calendario' => $estadoCalendario,
            ],
            422
        );
    }

    $conexion->beginTransaction();

    $solicitud = sprog_bloquear_solicitud($conexion, $solicitudId);
    if (!$solicitud) {
        sprog_cancelar($conexion, 'La solicitud no existe o cambió de estado.', 404);
    }

    if ((string) $solicitud['tipo_solicitud'] === 'CORRECTIVO_URGENTE') {
        sprog_cancelar(
            $conexion,
            'Las urgencias se publican directamente para los técnicos y no se asignan desde esta interfaz.',
            409
        );
    }

    if (in_array(
        (string) $solicitud['estado'],
        ['PENDIENTE', 'TERMINADO', 'RECHAZADO', 'CANCELADO'],
        true
    )) {
        sprog_cancelar(
            $conexion,
            'La solicitud ya no puede programarse desde esta interfaz.',
            409
        );
    }

    $recursosBase = sprog_preparar_recursos_detalle($conexion, $solicitud);
    $recursosActuales = sprog_bloquear_recursos_solicitud($conexion, $solicitudId);
    $recursosSeleccionados = sprog_validar_recursos_programacion(
        $conexion,
        $solicitud,
        $herramientasIds,
        $refaccionesIds,
        $recursosBase['recursos']
    );

    $programacionActual = sprog_bloquear_programacion_actual($conexion, $solicitudId);
    $asignacionesActuales = sprog_bloquear_asignaciones_actuales($conexion, $solicitudId);
    $ejecuciones = sprog_bloquear_ejecuciones_solicitud($conexion, $solicitudId);
    $tecnicos = sprog_bloquear_tecnicos($conexion, $tecnicosIds);

    if (count($tecnicos) !== count($tecnicosIds)) {
        sprog_cancelar(
            $conexion,
            'Uno o más técnicos ya no están activos. Actualiza la lista y vuelve a seleccionarlos.',
            409
        );
    }

    $asignacionesPorId = [];
    $idsAnteriores = [];
    foreach ($asignacionesActuales as $asignacion) {
        $tecnicoId = (int) $asignacion['tecnico_id'];
        $asignacionesPorId[$tecnicoId] = $asignacion;
        $idsAnteriores[] = $tecnicoId;
    }

    $iniciadosPorTecnico = [];
    foreach ($ejecuciones as $ejecucion) {
        if (
            !empty($ejecucion['fecha_hora_inicio'])
            || in_array(
                (string) $ejecucion['estado'],
                ['EN_PROCESO', 'PAUSADA', 'TERMINADA'],
                true
            )
        ) {
            $iniciadosPorTecnico[(int) $ejecucion['tecnico_id']] = true;
        }
    }

    $idsIniciados = array_map('intval', array_keys($iniciadosPorTecnico));

    $idsRecursosActuales = sprog_ids_recursos($recursosActuales);
    $idsRecursosNuevos = array_values(array_unique(array_merge($herramientasIds, $refaccionesIds)));
    sort($idsRecursosActuales);
    sort($idsRecursosNuevos);
    $cambioRecursos = $idsRecursosActuales !== $idsRecursosNuevos;

    /*
     * Si todavía no existe una fotografía en la solicitud, la selección que
     * el administrador vio puede venir de la memoria o de la plantilla.
     * Guardarla por primera vez sí cuenta como cambio, incluso si los ids
     * coinciden con esa recomendación precargada.
     */
    if ($recursosActuales === [] && $idsRecursosNuevos !== []) {
        $cambioRecursos = true;
    }

    if ($cambioRecursos && $idsIniciados !== []) {
        sprog_cancelar(
            $conexion,
            'Las herramientas y refacciones recomendadas ya no pueden cambiarse porque al menos un técnico inició el mantenimiento.',
            409,
            ['campo' => 'recursos_recomendados']
        );
    }

    foreach ($idsIniciados as $tecnicoIniciadoId) {
        if (!in_array($tecnicoIniciadoId, $tecnicosIds, true)) {
            sprog_cancelar(
                $conexion,
                'No puedes retirar a un técnico que ya inició este mantenimiento.',
                409,
                ['tecnico_id' => $tecnicoIniciadoId]
            );
        }
    }

    $fechaAnterior = $programacionActual
        ? (string) $programacionActual['fecha_programada']
        : '';
    $cambioFecha = $programacionActual === null || $fechaAnterior !== $fechaProgramada;

    if ($programacionActual && $cambioFecha && $idsIniciados !== []) {
        sprog_cancelar(
            $conexion,
            'La fecha ya no puede cambiarse porque al menos un técnico comenzó el mantenimiento. Puedes conservar a quienes iniciaron y ajustar únicamente técnicos que todavía no hayan comenzado.',
            409
        );
    }

    sort($idsAnteriores);
    $idsNuevosOrdenados = $tecnicosIds;
    sort($idsNuevosOrdenados);
    $cambioEquipo = $idsAnteriores !== $idsNuevosOrdenados;
    $esCambioEstructural = $programacionActual !== null
        && ($cambioFecha || $cambioEquipo || $cambioRecursos);

    if ($esCambioEstructural && sprog_longitud($motivo) < 10) {
        sprog_cancelar(
            $conexion,
            'Escribe un motivo de al menos 10 caracteres para modificar la fecha, los técnicos o los recursos recomendados.',
            422,
            ['campo' => 'motivo']
        );
    }

    $tecnicosNocturnos = [];
    foreach ($tecnicos as $tecnico) {
        if ((string) $tecnico['turno'] === 'NOCTURNO') {
            $tecnicosNocturnos[] = $tecnico;
        }
    }

    $riesgoNocturno = (int) $solicitud['trabajo_peligroso'] === 1
        && $tecnicosNocturnos !== [];
    $detallePeligroConfirmado = sprog_detalle_peligro_confirmado($solicitud);

    if ($riesgoNocturno && !$confirmacionNocturna) {
        sprog_cancelar(
            $conexion,
            'El trabajo está marcado como peligroso y seleccionaste personal nocturno. Confirma que revisaste las condiciones antes de guardar.',
            422,
            [
                'campo' => 'confirmar_riesgo_nocturno',
                'requiere_confirmacion_nocturna' => true,
            ]
        );
    }

    $cambioConfiguracionRiesgo = false;
    if ($programacionActual !== null && !$cambioFecha) {
        foreach ($asignacionesActuales as $asignacion) {
            $tecnicoIdAsignado = (int) $asignacion['tecnico_id'];
            if (!in_array($tecnicoIdAsignado, $tecnicosIds, true)) {
                continue;
            }

            $tecnicoAsignado = sprog_tecnico_por_id($tecnicos, $tecnicoIdAsignado);
            if (!$tecnicoAsignado) {
                continue;
            }

            $alertaEsperada = (int) $solicitud['trabajo_peligroso'] === 1
                && (string) $tecnicoAsignado['turno'] === 'NOCTURNO';
            $confirmacionEsperada = $alertaEsperada && $confirmacionNocturna;
            $observacionEsperada = $alertaEsperada ? trim($observacionNocturna) : '';
            $observacionActual = trim((string) ($asignacion['observacion_riesgo_nocturno'] ?? ''));
            $detalleConfirmadoActual = trim((string) ($asignacion['detalle_riesgo_nocturno_confirmado'] ?? ''));
            $detalleConfirmadoEsperado = $confirmacionEsperada ? $detallePeligroConfirmado : '';

            if (
                (int) $asignacion['alerta_riesgo_nocturno'] !== ($alertaEsperada ? 1 : 0)
                || (int) $asignacion['riesgo_nocturno_confirmado'] !== ($confirmacionEsperada ? 1 : 0)
                || $observacionActual !== $observacionEsperada
                || $detalleConfirmadoActual !== $detalleConfirmadoEsperado
            ) {
                $cambioConfiguracionRiesgo = true;
                break;
            }
        }
    }

    if (
        !$cambioFecha
        && !$cambioEquipo
        && !$cambioRecursos
        && !$cambioConfiguracionRiesgo
        && $programacionActual !== null
    ) {
        $conexion->commit();
        sm_responder_json(
            true,
            'La programación ya contiene esos datos; no fue necesario realizar cambios.',
            ['sin_cambios' => true]
        );
    }

    $programacionId = 0;
    $eventoProgramacion = 'PROGRAMADA';
    $accionMovimiento = 'PROGRAMAR_MANTENIMIENTO';

    if ($programacionActual === null) {
        /*
         * Protección frente a datos heredados inconsistentes: si existen
         * asignaciones ADMIN activas sin una programación vigente, se retiran
         * antes de crear la nueva programación. Nunca se recicla una asignación
         * huérfana con una fecha/programación diferente.
         */
        if ($asignacionesActuales !== []) {
            foreach ($asignacionesActuales as $asignacion) {
                if (isset($iniciadosPorTecnico[(int) $asignacion['tecnico_id']])) {
                    sprog_cancelar(
                        $conexion,
                        'Existe una ejecución iniciada sin programación vigente. Revisa el historial antes de volver a programar.',
                        409
                    );
                }

                sprog_retirar_asignacion(
                    $conexion,
                    $asignacion,
                    $adminId,
                    'Se normalizó una asignación activa sin programación vigente antes de crear la nueva programación.',
                    true
                );
            }

            $asignacionesActuales = [];
            $asignacionesPorId = [];
            $idsAnteriores = [];
        }

        $programacionId = sprog_insertar_programacion(
            $conexion,
            $solicitudId,
            $fechaProgramada,
            $adminId,
            $motivo
        );
    } elseif ($cambioFecha) {
        sprog_cerrar_programacion_anterior(
            $conexion,
            (int) $programacionActual['id'],
            $motivo
        );

        foreach ($asignacionesActuales as $asignacion) {
            sprog_retirar_asignacion(
                $conexion,
                $asignacion,
                $adminId,
                'La programación cambió de fecha. ' . $motivo,
                true
            );
        }

        $programacionId = sprog_insertar_programacion(
            $conexion,
            $solicitudId,
            $fechaProgramada,
            $adminId,
            $motivo
        );
        $asignacionesActuales = [];
        $asignacionesPorId = [];
        $idsAnteriores = [];
        $eventoProgramacion = 'REPROGRAMADA';
        $accionMovimiento = 'REPROGRAMAR_MANTENIMIENTO';
    } else {
        $programacionId = (int) $programacionActual['id'];

        if ($cambioEquipo) {
            $eventoProgramacion = 'ASIGNADA';
            $accionMovimiento = 'CAMBIAR_TECNICOS_MANTENIMIENTO';
        } elseif ($cambioRecursos) {
            $eventoProgramacion = 'OTRO';
            $accionMovimiento = 'ACTUALIZAR_RECURSOS_RECOMENDADOS';
        } else {
            $eventoProgramacion = 'OTRO';
            $accionMovimiento = 'ACTUALIZAR_SEGURIDAD_PROGRAMACION';
        }
    }

    $idsRetirados = array_values(array_diff($idsAnteriores, $tecnicosIds));
    $idsAgregados = array_values(array_diff($tecnicosIds, $idsAnteriores));
    $idsConservados = array_values(array_intersect($tecnicosIds, $idsAnteriores));

    foreach ($idsRetirados as $tecnicoId) {
        if (isset($iniciadosPorTecnico[$tecnicoId])) {
            sprog_cancelar(
                $conexion,
                'No puedes retirar a un técnico que ya inició.',
                409
            );
        }

        if (isset($asignacionesPorId[$tecnicoId])) {
            sprog_retirar_asignacion(
                $conexion,
                $asignacionesPorId[$tecnicoId],
                $adminId,
                $motivo,
                false
            );
        }
    }

    foreach ($idsConservados as $tecnicoId) {
        if (!isset($asignacionesPorId[$tecnicoId])) {
            continue;
        }

        $tecnico = sprog_tecnico_por_id($tecnicos, $tecnicoId);
        $esNocturnoPeligroso = $tecnico
            && (string) $tecnico['turno'] === 'NOCTURNO'
            && (int) $solicitud['trabajo_peligroso'] === 1;

        sprog_actualizar_alerta_asignacion(
            $conexion,
            (int) $asignacionesPorId[$tecnicoId]['id'],
            $esNocturnoPeligroso,
            $confirmacionNocturna,
            $adminId,
            $observacionNocturna,
            $detallePeligroConfirmado
        );
    }

    $asignacionesNuevas = [];
    foreach ($idsAgregados as $tecnicoId) {
        $tecnico = sprog_tecnico_por_id($tecnicos, $tecnicoId);
        if (!$tecnico) {
            sprog_cancelar($conexion, 'No fue posible validar a uno de los técnicos.', 409);
        }

        $esNocturnoPeligroso = (string) $tecnico['turno'] === 'NOCTURNO'
            && (int) $solicitud['trabajo_peligroso'] === 1;

        $asignacionId = sprog_insertar_asignacion(
            $conexion,
            $solicitudId,
            $programacionId,
            $tecnicoId,
            $adminId,
            $esNocturnoPeligroso,
            $confirmacionNocturna,
            $observacionNocturna,
            $detallePeligroConfirmado
        );

        $asignacionesNuevas[] = [
            'id' => $asignacionId,
            'tecnico_id' => $tecnicoId,
            'tecnico' => sprog_nombre_tecnico($tecnico),
        ];

        sprog_historial(
            $conexion,
            $solicitudId,
            $asignacionId,
            $programacionId,
            'ASIGNADA',
            (string) $solicitud['estado'],
            'AGENDADO',
            'ADMIN',
            $adminId,
            'Se asignó el mantenimiento al técnico ' . sprog_nombre_tecnico($tecnico)
                . ' para el día ' . $fechaProgramada . '.'
        );

        sprog_notificar(
            $conexion,
            'TECNICO',
            $tecnicoId,
            $solicitudId,
            $programacionActual === null ? 'Nuevo mantenimiento asignado' : 'Asignación de mantenimiento actualizada',
            'Se te asignó ' . (string) $solicitud['folio'] . ' para el día '
                . sprog_fecha_es($fechaProgramada) . '. Equipo: '
                . (string) $solicitud['nombre_equipo'] . '.',
            $esNocturnoPeligroso ? 'WARNING' : 'INFO'
        );
    }

    if ($programacionActual === null || $cambioRecursos || $recursosActuales === []) {
        sprog_reemplazar_recursos_solicitud(
            $conexion,
            $solicitud,
            $recursosSeleccionados,
            $adminId
        );

        if ((string) $solicitud['tipo_solicitud'] !== 'RUTINARIO') {
            sprog_reemplazar_memoria_recomendaciones(
                $conexion,
                $solicitud,
                $recursosSeleccionados,
                $adminId
            );
        }
    }

    if ($cambioRecursos) {
        foreach ($idsConservados as $tecnicoId) {
            sprog_notificar(
                $conexion,
                'TECNICO',
                (int) $tecnicoId,
                $solicitudId,
                'Recursos recomendados actualizados',
                'El administrador actualizó lo recomendado para ' . (string) $solicitud['folio']
                    . ': ' . count($herramientasIds) . ' herramienta(s) y '
                    . count($refaccionesIds) . ' refacción(es). Revisa el detalle antes de iniciar.',
                'INFO'
            );
        }
    }

    $estadoAnterior = (string) $solicitud['estado'];
    $estadoNuevo = $idsIniciados === [] ? 'AGENDADO' : $estadoAnterior;

    if ($idsIniciados === []) {
        $stmtEstado = $conexion->prepare(
            "UPDATE solicitudes
             SET estado = 'AGENDADO'
             WHERE id = :id
               AND activo = 1
               AND estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')"
        );
        $stmtEstado->bindValue(':id', $solicitudId, PDO::PARAM_INT);
        $stmtEstado->execute();
    }

    $descripcion = $programacionActual === null
        ? 'Se programó la solicitud ' . (string) $solicitud['folio']
            . ' para el día ' . $fechaProgramada . ' con '
            . count($tecnicosIds) . ' técnico(s), '
            . count($herramientasIds) . ' herramienta(s) y '
            . count($refaccionesIds) . ' refacción(es) recomendada(s).'
        : 'Se actualizó la programación de ' . (string) $solicitud['folio']
            . '. Fecha: ' . $fechaProgramada . '. Técnicos activos: '
            . count($tecnicosIds) . '. Recursos: '
            . count($herramientasIds) . ' herramienta(s) y '
            . count($refaccionesIds) . ' refacción(es). Motivo: ' . $motivo;

    sprog_historial(
        $conexion,
        $solicitudId,
        null,
        $programacionId,
        $eventoProgramacion,
        $estadoAnterior,
        $estadoNuevo,
        'ADMIN',
        $adminId,
        $descripcion
    );

    if ($cambioRecursos) {
        sprog_historial(
            $conexion,
            $solicitudId,
            null,
            $programacionId,
            'OTRO',
            $estadoAnterior,
            $estadoNuevo,
            'ADMIN',
            $adminId,
            'Se actualizaron los recursos recomendados: '
                . count($herramientasIds) . ' herramienta(s) y '
                . count($refaccionesIds) . ' refacción(es).'
                . ((string) $solicitud['tipo_solicitud'] === 'RUTINARIO'
                    ? ' La plantilla de rutina no fue modificada.'
                    : ' La memoria del equipo y tipo de mantenimiento fue actualizada.')
        );
    }

    sprog_movimiento(
        $conexion,
        $adminId,
        $accionMovimiento,
        'Programar y asignar',
        $descripcion,
        'programaciones_mantenimiento',
        $programacionId
    );

    sprog_notificar_solicitante(
        $conexion,
        $solicitud,
        $adminId,
        $solicitudId,
        $programacionActual === null ? 'Mantenimiento programado' : 'Programación actualizada',
        'La solicitud ' . (string) $solicitud['folio'] . ' quedó programada para el día '
            . sprog_fecha_es($fechaProgramada) . '.',
        'INFO'
    );

    $conexion->commit();

    sm_responder_json(
        true,
        $programacionActual === null
            ? 'El mantenimiento fue programado y asignado correctamente.'
            : 'La programación fue actualizada correctamente.',
        [
            'solicitud_id' => $solicitudId,
            'programacion_id' => $programacionId,
            'fecha_programada' => $fechaProgramada,
            'tecnicos_asignados' => count($tecnicosIds),
            'tecnicos_agregados' => count($idsAgregados),
            'tecnicos_retirados' => count($idsRetirados),
            'herramientas_recomendadas' => count($herramientasIds),
            'refacciones_recomendadas' => count($refaccionesIds),
            'memoria_actualizada' => (string) $solicitud['tipo_solicitud'] !== 'RUTINARIO',
            'requiere_alerta_nocturna' => $riesgoNocturno,
        ]
    );
}

/* =========================================================================
   CANCELAR MANTENIMIENTO ANTES DE INICIAR
   ========================================================================= */

function sprog_cancelar_mantenimiento(PDO $conexion): void
{
    $adminId = sprog_admin_id();
    sprog_validar_admin_activo($conexion, $adminId);

    $solicitudId = sprog_id_entrada(
        $_POST['solicitud_id'] ?? null,
        'solicitud_id'
    );
    $motivo = sprog_texto(
        $_POST['motivo_cancelacion'] ?? '',
        10,
        500,
        'motivo_cancelacion'
    );

    $conexion->beginTransaction();

    $solicitud = sprog_bloquear_solicitud($conexion, $solicitudId);
    if (!$solicitud) {
        sprog_cancelar(
            $conexion,
            'La solicitud no existe o ya no está disponible.',
            404
        );
    }

    $estadoAnterior = (string) $solicitud['estado'];

    if ((string) $solicitud['tipo_solicitud'] === 'CORRECTIVO_URGENTE') {
        sprog_cancelar(
            $conexion,
            'Las urgencias no se cancelan desde esta interfaz.',
            409
        );
    }

    if ($estadoAnterior === 'CANCELADO') {
        sprog_cancelar(
            $conexion,
            'El mantenimiento ya se encuentra cancelado.',
            409
        );
    }

    if (!in_array($estadoAnterior, ['APROBADO', 'AGENDADO', 'ATRASADO'], true)) {
        sprog_cancelar(
            $conexion,
            'Solo se pueden cancelar mantenimientos aprobados, agendados o atrasados que todavía no hayan iniciado.',
            409
        );
    }

    $programacionActual = sprog_bloquear_programacion_actual(
        $conexion,
        $solicitudId
    );
    $asignaciones = sprog_bloquear_asignaciones_actuales(
        $conexion,
        $solicitudId
    );
    $ejecuciones = sprog_bloquear_ejecuciones_solicitud(
        $conexion,
        $solicitudId
    );

    foreach ($asignaciones as $asignacion) {
        if (in_array(
            (string) $asignacion['estado'],
            ['EN_PROCESO', 'PAUSADO', 'TERMINADO'],
            true
        )) {
            sprog_cancelar(
                $conexion,
                'No se puede cancelar porque al menos un técnico ya comenzó o terminó su participación.',
                409
            );
        }
    }

    foreach ($ejecuciones as $ejecucion) {
        if (
            !empty($ejecucion['fecha_hora_inicio'])
            || in_array(
                (string) $ejecucion['estado'],
                ['EN_PROCESO', 'PAUSADA', 'TERMINADA'],
                true
            )
        ) {
            sprog_cancelar(
                $conexion,
                'No se puede cancelar porque el mantenimiento ya fue iniciado.',
                409
            );
        }
    }

    /*
     * Se marcan como leídas las notificaciones de asignación anteriores para
     * que el técnico no conserve una alerta vieja que parezca vigente. La
     * notificación de cancelación se inserta después de este cierre.
     */
    $stmtNotificaciones = $conexion->prepare(
        "UPDATE notificaciones
         SET leida = 1,
             fecha_lectura = COALESCE(fecha_lectura, NOW())
         WHERE solicitud_id = :solicitud_id
           AND tipo_usuario = 'TECNICO'
           AND leida = 0"
    );
    $stmtNotificaciones->bindValue(
        ':solicitud_id',
        $solicitudId,
        PDO::PARAM_INT
    );
    $stmtNotificaciones->execute();

    foreach ($asignaciones as $asignacion) {
        sprog_retirar_asignacion(
            $conexion,
            $asignacion,
            $adminId,
            $motivo,
            false,
            true
        );
    }

    $programacionId = $programacionActual !== null
        ? (int) $programacionActual['id']
        : null;

    if ($programacionActual !== null) {
        sprog_cerrar_programacion_cancelada(
            $conexion,
            (int) $programacionActual['id'],
            $motivo
        );
    }

    /*
     * Una solicitud atrasada puede tener incumplimientos pendientes. Al
     * cancelarla antes de iniciar dejan de ser tareas exigibles, por eso se
     * resuelven administrativamente como justificados.
     */
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
        sprog_recortar(
            'Mantenimiento cancelado antes de iniciar. Motivo: ' . $motivo,
            1000
        ),
        PDO::PARAM_STR
    );
    $stmtIncumplimientos->bindValue(
        ':admin_id',
        $adminId,
        PDO::PARAM_INT
    );
    $stmtIncumplimientos->bindValue(
        ':solicitud_id',
        $solicitudId,
        PDO::PARAM_INT
    );
    $stmtIncumplimientos->execute();

    $stmtSolicitud = $conexion->prepare(
        "UPDATE solicitudes
         SET estado = 'CANCELADO',
             ultima_edicion_admin_id = :admin_id,
             motivo_ultima_edicion = :motivo,
             version_registro = version_registro + 1,
             fecha_actualizacion = NOW()
         WHERE id = :id
           AND activo = 1
           AND estado IN ('APROBADO', 'AGENDADO', 'ATRASADO')"
    );
    $stmtSolicitud->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmtSolicitud->bindValue(':motivo', $motivo, PDO::PARAM_STR);
    $stmtSolicitud->bindValue(':id', $solicitudId, PDO::PARAM_INT);
    $stmtSolicitud->execute();

    if ($stmtSolicitud->rowCount() !== 1) {
        sprog_cancelar(
            $conexion,
            'La solicitud cambió mientras realizabas la cancelación. Actualiza la pantalla.',
            409
        );
    }

    /*
     * Si el mantenimiento provino de una rutina, el periodo correspondiente
     * también queda cerrado como cancelado y no vuelve a aparecer como una
     * alerta pendiente de programación.
     */
    $stmtRutina = $conexion->prepare(
        "UPDATE rutina_alertas
         SET estado = 'CANCELADA',
             atendida_por_admin_id = :admin_id,
             motivo_omision = :motivo,
             fecha_atencion = NOW()
         WHERE solicitud_id = :solicitud_id
           AND estado IN ('PENDIENTE_PROGRAMAR', 'PROGRAMADA')"
    );
    $stmtRutina->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmtRutina->bindValue(':motivo', $motivo, PDO::PARAM_STR);
    $stmtRutina->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtRutina->execute();

    $descripcion = 'Se canceló el mantenimiento '
        . (string) $solicitud['folio']
        . ' antes de iniciar. Motivo: '
        . $motivo;

    sprog_historial(
        $conexion,
        $solicitudId,
        null,
        $programacionId,
        'CANCELADA',
        $estadoAnterior,
        'CANCELADO',
        'ADMIN',
        $adminId,
        $descripcion
    );

    sprog_movimiento(
        $conexion,
        $adminId,
        'CANCELAR_MANTENIMIENTO',
        'Programar y asignar',
        $descripcion,
        'solicitudes',
        $solicitudId
    );

    sprog_notificar_solicitante(
        $conexion,
        $solicitud,
        $adminId,
        $solicitudId,
        'Mantenimiento cancelado',
        'La solicitud ' . (string) $solicitud['folio']
            . ' fue cancelada antes de iniciar. Motivo: ' . $motivo,
        'WARNING'
    );

    $conexion->commit();

    sm_responder_json(
        true,
        'El mantenimiento fue cancelado correctamente y se retiró de las asignaciones de los técnicos.',
        [
            'solicitud_id' => $solicitudId,
            'programacion_id' => $programacionId,
            'tecnicos_retirados' => count($asignaciones),
        ]
    );
}

/* =========================================================================
   CONSULTAS PRINCIPALES
   ========================================================================= */

function sprog_consultar_solicitudes(PDO $conexion, string $inicioSemana, string $finSemana): array
{
    $sql = "
        SELECT
            s.id,
            s.folio,
            s.tipo_solicitud,
            s.estado,
            s.prioridad,
            s.fecha_solicitud,
            s.fecha_sugerida,
            s.descripcion_solicitud,
            s.trabajo_peligroso,
            s.nivel_riesgo,
            s.requiere_paro_equipo,
            e.codigo_equipo,
            e.nombre_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso,
            pm.id AS programacion_id,
            pm.fecha_programada,
            pm.fecha_limite,
            pm.estado AS estado_programacion,
            pm.fecha_actualizacion AS programacion_actualizada,
            COALESCE((
                SELECT COUNT(*)
                FROM solicitud_tecnicos stc
                WHERE stc.solicitud_id = s.id
                  AND stc.origen = 'ADMIN'
                  AND stc.activo = 1
            ), 0) AS total_tecnicos,
            COALESCE((
                SELECT COUNT(DISTINCT emc.tecnico_id)
                FROM ejecuciones_mantenimiento emc
                INNER JOIN solicitud_tecnicos ste
                        ON ste.id = emc.solicitud_tecnico_id
                WHERE emc.solicitud_id = s.id
                  AND ste.activo = 1
                  AND emc.fecha_hora_inicio IS NOT NULL
            ), 0) AS total_iniciados,
            COALESCE((
                SELECT GROUP_CONCAT(
                    DISTINCT TRIM(CONCAT_WS(' ', tc.nombre, tc.apellido_paterno, tc.apellido_materno))
                    ORDER BY tc.nombre, tc.apellido_paterno
                    SEPARATOR ', '
                )
                FROM solicitud_tecnicos stn
                INNER JOIN tecnicos tc ON tc.id = stn.tecnico_id
                WHERE stn.solicitud_id = s.id
                  AND stn.origen = 'ADMIN'
                  AND stn.activo = 1
            ), '') AS tecnicos,
            CASE
                WHEN pm.id IS NOT NULL AND pm.fecha_limite < CURDATE()
                THEN DATEDIFF(CURDATE(), pm.fecha_limite)
                ELSE 0
            END AS dias_atraso,
            CASE
                WHEN pm.fecha_programada BETWEEN :semana_inicio AND :semana_fin
                THEN 1 ELSE 0
            END AS en_semana,
            CASE
                WHEN pm.id IS NULL THEN 'POR_PROGRAMAR'
                WHEN pm.fecha_limite < CURDATE()
                     AND COALESCE((
                        SELECT COUNT(*)
                        FROM ejecuciones_mantenimiento emx
                        WHERE emx.solicitud_id = s.id
                          AND emx.fecha_hora_inicio IS NOT NULL
                     ), 0) = 0
                THEN 'ATRASADO_SIN_INICIAR'
                WHEN s.estado IN ('EN_PROCESO','PAUSADO') THEN 'EN_EJECUCION'
                ELSE 'PROGRAMADO'
            END AS grupo
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
        LEFT JOIN programaciones_mantenimiento pm
               ON pm.solicitud_id = s.id
              AND pm.es_actual = 1
        WHERE s.activo = 1
          AND s.tipo_solicitud <> 'CORRECTIVO_URGENTE'
          AND s.estado IN ('APROBADO','AGENDADO','ATRASADO','EN_PROCESO','PAUSADO')
          AND (s.estado = 'APROBADO' OR pm.id IS NOT NULL)
        ORDER BY
            CASE
                WHEN pm.id IS NOT NULL AND pm.fecha_limite < CURDATE() THEN 0
                WHEN pm.id IS NULL THEN 1
                WHEN pm.fecha_programada BETWEEN :semana_inicio_orden AND :semana_fin_orden THEN 2
                ELSE 3
            END,
            CASE s.prioridad
                WHEN 'ALTA' THEN 1
                WHEN 'MEDIA' THEN 2
                WHEN 'BAJA' THEN 3
                ELSE 4
            END,
            COALESCE(pm.fecha_programada, s.fecha_sugerida, s.fecha_solicitud),
            s.id DESC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':semana_inicio', $inicioSemana, PDO::PARAM_STR);
    $stmt->bindValue(':semana_fin', $finSemana, PDO::PARAM_STR);
    $stmt->bindValue(':semana_inicio_orden', $inicioSemana, PDO::PARAM_STR);
    $stmt->bindValue(':semana_fin_orden', $finSemana, PDO::PARAM_STR);
    $stmt->execute();

    $filas = $stmt->fetchAll();
    foreach ($filas as &$fila) {
        $fila['id'] = (int) $fila['id'];
        $fila['programacion_id'] = $fila['programacion_id'] !== null
            ? (int) $fila['programacion_id']
            : null;
        $fila['total_tecnicos'] = (int) $fila['total_tecnicos'];
        $fila['total_iniciados'] = (int) $fila['total_iniciados'];
        $fila['dias_atraso'] = max(0, (int) $fila['dias_atraso']);
        $fila['en_semana'] = (int) $fila['en_semana'];
        $fila['trabajo_peligroso'] = (int) $fila['trabajo_peligroso'];
        $fila['requiere_paro_equipo'] = (int) $fila['requiere_paro_equipo'];
        $fila['puede_cambiar_fecha'] = (int) $fila['total_iniciados'] === 0;
        $fila['puede_cancelar'] = (int) $fila['total_iniciados'] === 0
            && in_array(
                (string) $fila['estado'],
                ['APROBADO', 'AGENDADO', 'ATRASADO'],
                true
            );
    }
    unset($fila);

    return $filas;
}

function sprog_consultar_detalle_solicitud(PDO $conexion, int $solicitudId): ?array
{
    $stmt = $conexion->prepare(
        "SELECT
            s.*,
            e.codigo_equipo,
            e.nombre_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso,
            tf.nombre AS tipo_falla,
            ca.nombre AS causa_averia,
            COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ', sol.nombre, sol.apellido_paterno, sol.apellido_materno)), ''),
                NULLIF(TRIM(CONCAT_WS(' ', adm.nombre, adm.apellido_paterno, adm.apellido_materno)), ''),
                'Sin solicitante'
            ) AS solicitante,
            pm.id AS programacion_id,
            pm.fecha_programada,
            pm.fecha_limite,
            pm.estado AS estado_programacion,
            pm.motivo_programacion,
            pm.motivo_reprogramacion,
            COALESCE((
                SELECT COUNT(*)
                FROM solicitud_tecnicos st1
                WHERE st1.solicitud_id = s.id
                  AND st1.origen = 'ADMIN'
                  AND st1.activo = 1
            ), 0) AS total_tecnicos,
            COALESCE((
                SELECT COUNT(DISTINCT em1.tecnico_id)
                FROM ejecuciones_mantenimiento em1
                INNER JOIN solicitud_tecnicos st2
                        ON st2.id = em1.solicitud_tecnico_id
                WHERE em1.solicitud_id = s.id
                  AND st2.activo = 1
                  AND em1.fecha_hora_inicio IS NOT NULL
            ), 0) AS total_iniciados,
            CASE
                WHEN pm.id IS NOT NULL AND pm.fecha_limite < CURDATE()
                THEN DATEDIFF(CURDATE(), pm.fecha_limite)
                ELSE 0
            END AS dias_atraso
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
         LEFT JOIN tipos_falla tf ON tf.id = s.tipo_falla_id
         LEFT JOIN causas_averia ca ON ca.id = s.causa_averia_id
         LEFT JOIN solicitantes sol ON sol.id = s.solicitante_id
         LEFT JOIN administradores adm ON adm.id = s.administrador_solicitante_id
         LEFT JOIN programaciones_mantenimiento pm
                ON pm.solicitud_id = s.id
               AND pm.es_actual = 1
         WHERE s.id = :id
           AND s.activo = 1
           AND s.tipo_solicitud <> 'CORRECTIVO_URGENTE'
           AND s.estado IN ('APROBADO','AGENDADO','ATRASADO','EN_PROCESO','PAUSADO')
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
    $fila['total_tecnicos'] = (int) $fila['total_tecnicos'];
    $fila['total_iniciados'] = (int) $fila['total_iniciados'];
    $fila['dias_atraso'] = max(0, (int) $fila['dias_atraso']);
    $fila['trabajo_peligroso'] = (int) $fila['trabajo_peligroso'];
    $fila['requiere_paro_equipo'] = (int) $fila['requiere_paro_equipo'];

    return $fila;
}

function sprog_consultar_asignaciones_actuales(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            st.id AS solicitud_tecnico_id,
            st.tecnico_id,
            st.programacion_id,
            st.estado,
            st.fecha_asignacion,
            st.alerta_riesgo_nocturno,
            st.riesgo_nocturno_confirmado,
            st.fecha_confirmacion_riesgo_nocturno,
            st.detalle_riesgo_nocturno_confirmado,
            st.observacion_riesgo_nocturno,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
            t.turno,
            t.especialidad,
            d.nombre AS departamento,
            em.id AS ejecucion_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            CASE
                WHEN em.fecha_hora_inicio IS NOT NULL
                     OR em.estado IN ('EN_PROCESO','PAUSADA','TERMINADA')
                THEN 1 ELSE 0
            END AS iniciado
         FROM solicitud_tecnicos st
         INNER JOIN tecnicos t ON t.id = st.tecnico_id
         LEFT JOIN departamentos d ON d.id = t.departamento_id
         LEFT JOIN ejecuciones_mantenimiento em
                ON em.solicitud_tecnico_id = st.id
         WHERE st.solicitud_id = :solicitud_id
           AND st.origen = 'ADMIN'
           AND st.activo = 1
         ORDER BY iniciado DESC, tecnico"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll();

    foreach ($filas as &$fila) {
        $fila['solicitud_tecnico_id'] = (int) $fila['solicitud_tecnico_id'];
        $fila['tecnico_id'] = (int) $fila['tecnico_id'];
        $fila['programacion_id'] = $fila['programacion_id'] !== null
            ? (int) $fila['programacion_id']
            : null;
        $fila['ejecucion_id'] = $fila['ejecucion_id'] !== null
            ? (int) $fila['ejecucion_id']
            : null;
        $fila['iniciado'] = (int) $fila['iniciado'];
        $fila['alerta_riesgo_nocturno'] = (int) $fila['alerta_riesgo_nocturno'];
        $fila['riesgo_nocturno_confirmado'] = (int) $fila['riesgo_nocturno_confirmado'];
    }
    unset($fila);

    return $filas;
}

function sprog_consultar_tecnicos(
    PDO $conexion,
    int $solicitudId,
    string $fecha,
    string $inicioSemana,
    string $finSemana
): array {
    $sql = "
        SELECT
            t.id,
            t.usuario,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
            t.turno,
            COALESCE(NULLIF(TRIM(t.especialidad), ''), 'Sin especialidad') AS especialidad,
            t.departamento_id,
            COALESCE(d.nombre, 'Sin departamento') AS departamento,
            COALESCE((
                SELECT COUNT(*)
                FROM solicitud_tecnicos stc
                INNER JOIN solicitudes sc ON sc.id = stc.solicitud_id
                WHERE stc.tecnico_id = t.id
                  AND stc.activo = 1
                  AND stc.estado IN ('ASIGNADO','ACEPTADO','EN_PROCESO','PAUSADO')
                  AND sc.activo = 1
                  AND sc.estado IN ('AGENDADO','ATRASADO','EN_PROCESO','PAUSADO')
            ), 0) AS carga_activa,
            COALESCE((
                SELECT COUNT(*)
                FROM solicitud_tecnicos stw
                INNER JOIN programaciones_mantenimiento pmw ON pmw.id = stw.programacion_id
                INNER JOIN solicitudes sw ON sw.id = stw.solicitud_id
                WHERE stw.tecnico_id = t.id
                  AND stw.activo = 1
                  AND pmw.es_actual = 1
                  AND pmw.fecha_programada BETWEEN :inicio_semana AND :fin_semana
                  AND sw.estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')
            ), 0) AS carga_semana,
            COALESCE((
                SELECT COUNT(*)
                FROM solicitud_tecnicos std
                INNER JOIN programaciones_mantenimiento pmd ON pmd.id = std.programacion_id
                INNER JOIN solicitudes sd ON sd.id = std.solicitud_id
                WHERE std.tecnico_id = t.id
                  AND std.activo = 1
                  AND pmd.es_actual = 1
                  AND pmd.fecha_programada = :fecha_dia
                  AND sd.estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')
            ), 0) AS carga_dia,
            COALESCE((
                SELECT COUNT(*)
                FROM solicitud_tecnicos sta
                INNER JOIN programaciones_mantenimiento pma ON pma.id = sta.programacion_id
                INNER JOIN solicitudes sa ON sa.id = sta.solicitud_id
                WHERE sta.tecnico_id = t.id
                  AND sta.activo = 1
                  AND pma.es_actual = 1
                  AND pma.fecha_limite < CURDATE()
                  AND sa.estado IN ('AGENDADO','ATRASADO')
                  AND NOT EXISTS (
                      SELECT 1
                      FROM ejecuciones_mantenimiento ema
                      WHERE ema.solicitud_tecnico_id = sta.id
                        AND ema.fecha_hora_inicio IS NOT NULL
                  )
            ), 0) AS atrasados_sin_iniciar,
            COALESCE((
                SELECT COUNT(*)
                FROM ejecuciones_mantenimiento emp
                WHERE emp.tecnico_id = t.id
                  AND emp.estado = 'EN_PROCESO'
            ), 0) AS en_proceso,
            stactual.id AS solicitud_tecnico_id_actual,
            stactual.estado AS estado_asignacion_actual,
            CASE WHEN stactual.id IS NULL THEN 0 ELSE 1 END AS seleccionado,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM ejecuciones_mantenimiento emactual
                    WHERE emactual.solicitud_tecnico_id = stactual.id
                      AND (
                          emactual.fecha_hora_inicio IS NOT NULL
                          OR emactual.estado IN ('EN_PROCESO','PAUSADA','TERMINADA')
                      )
                ) THEN 1 ELSE 0
            END AS iniciado_en_solicitud
        FROM tecnicos t
        LEFT JOIN departamentos d ON d.id = t.departamento_id
        LEFT JOIN solicitud_tecnicos stactual
               ON stactual.solicitud_id = :solicitud_actual
              AND stactual.tecnico_id = t.id
              AND stactual.origen = 'ADMIN'
              AND stactual.activo = 1
        WHERE t.activo = 1
        ORDER BY
            carga_activa ASC,
            carga_semana ASC,
            atrasados_sin_iniciar ASC,
            tecnico ASC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':inicio_semana', $inicioSemana, PDO::PARAM_STR);
    $stmt->bindValue(':fin_semana', $finSemana, PDO::PARAM_STR);
    $stmt->bindValue(':fecha_dia', $fecha, PDO::PARAM_STR);
    $stmt->bindValue(':solicitud_actual', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll();

    foreach ($filas as &$fila) {
        $fila['id'] = (int) $fila['id'];
        $fila['departamento_id'] = $fila['departamento_id'] !== null
            ? (int) $fila['departamento_id']
            : null;
        $fila['carga_activa'] = (int) $fila['carga_activa'];
        $fila['carga_semana'] = (int) $fila['carga_semana'];
        $fila['carga_dia'] = (int) $fila['carga_dia'];
        $fila['atrasados_sin_iniciar'] = (int) $fila['atrasados_sin_iniciar'];
        $fila['en_proceso'] = (int) $fila['en_proceso'];
        $fila['solicitud_tecnico_id_actual'] = $fila['solicitud_tecnico_id_actual'] !== null
            ? (int) $fila['solicitud_tecnico_id_actual']
            : null;
        $fila['seleccionado'] = (int) $fila['seleccionado'];
        $fila['iniciado_en_solicitud'] = (int) $fila['iniciado_en_solicitud'];
        $fila['carga_nivel'] = sprog_nivel_carga((int) $fila['carga_activa']);
    }
    unset($fila);

    return $filas;
}

function sprog_consultar_resumen(PDO $conexion, string $inicioSemana, string $finSemana): array
{
    $stmt = $conexion->prepare(
        "SELECT
            SUM(CASE WHEN s.estado = 'APROBADO' AND pm.id IS NULL THEN 1 ELSE 0 END) AS por_programar,
            SUM(CASE
                WHEN pm.id IS NOT NULL
                 AND pm.fecha_programada >= CURDATE()
                 AND s.estado NOT IN ('EN_PROCESO','PAUSADO')
                THEN 1 ELSE 0 END
            ) AS programados,
            SUM(CASE
                WHEN pm.id IS NOT NULL
                 AND pm.fecha_limite < CURDATE()
                 AND s.estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')
                 AND NOT EXISTS (
                    SELECT 1
                    FROM ejecuciones_mantenimiento emr
                    WHERE emr.solicitud_id = s.id
                      AND emr.fecha_hora_inicio IS NOT NULL
                 )
                THEN 1 ELSE 0 END
            ) AS atrasados_sin_iniciar,
            SUM(CASE WHEN s.estado IN ('EN_PROCESO','PAUSADO') THEN 1 ELSE 0 END) AS en_ejecucion,
            SUM(CASE
                WHEN pm.fecha_programada BETWEEN :inicio_semana AND :fin_semana
                THEN 1 ELSE 0 END
            ) AS en_semana,
            SUM(CASE WHEN s.trabajo_peligroso = 1 THEN 1 ELSE 0 END) AS peligrosos
         FROM solicitudes s
         LEFT JOIN programaciones_mantenimiento pm
                ON pm.solicitud_id = s.id
               AND pm.es_actual = 1
         WHERE s.activo = 1
           AND s.tipo_solicitud <> 'CORRECTIVO_URGENTE'
           AND s.estado IN ('APROBADO','AGENDADO','ATRASADO','EN_PROCESO','PAUSADO')"
    );
    $stmt->bindValue(':inicio_semana', $inicioSemana, PDO::PARAM_STR);
    $stmt->bindValue(':fin_semana', $finSemana, PDO::PARAM_STR);
    $stmt->execute();
    $fila = $stmt->fetch() ?: [];

    return [
        'por_programar' => (int) ($fila['por_programar'] ?? 0),
        'programados' => (int) ($fila['programados'] ?? 0),
        'atrasados_sin_iniciar' => (int) ($fila['atrasados_sin_iniciar'] ?? 0),
        'en_ejecucion' => (int) ($fila['en_ejecucion'] ?? 0),
        'en_semana' => (int) ($fila['en_semana'] ?? 0),
        'peligrosos' => (int) ($fila['peligrosos'] ?? 0),
    ];
}

function sprog_consultar_catalogos_tecnicos(PDO $conexion): array
{
    $especialidades = $conexion->query(
        "SELECT DISTINCT TRIM(especialidad) AS especialidad
         FROM tecnicos
         WHERE activo = 1
           AND especialidad IS NOT NULL
           AND TRIM(especialidad) <> ''
         ORDER BY especialidad"
    )->fetchAll(PDO::FETCH_COLUMN);

    $departamentos = $conexion->query(
        "SELECT DISTINCT d.id, d.nombre
         FROM tecnicos t
         INNER JOIN departamentos d ON d.id = t.departamento_id
         WHERE t.activo = 1
           AND d.activo = 1
         ORDER BY d.nombre"
    )->fetchAll();

    return [
        'turnos' => ['MATUTINO', 'VESPERTINO', 'NOCTURNO'],
        'especialidades' => array_values(array_filter(array_map('strval', $especialidades))),
        'departamentos' => $departamentos,
    ];
}

/* =========================================================================
   CALENDARIO Y SEMANAS
   ========================================================================= */

function sprog_estado_calendario(PDO $conexion, string $fecha): array
{
    $stmt = $conexion->prepare(
        "SELECT fecha, es_habil, tipo_dia, motivo
         FROM calendario_laboral
         WHERE fecha = :fecha
         LIMIT 1"
    );
    $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
    $stmt->execute();
    $fila = $stmt->fetch();

    if (!$fila) {
        $objeto = new DateTimeImmutable($fecha);
        $finSemana = (int) $objeto->format('N') >= 6;

        return [
            'fecha' => $fecha,
            'configurado' => true,
            'origen' => 'REGLA_BASE',
            'permitido' => !$finSemana,
            'es_habil' => !$finSemana,
            'tipo_dia' => $finSemana ? 'INHABIL' : 'HABIL',
            'motivo' => $finSemana ? 'Fin de semana (regla base).' : null,
            'mensaje' => $finSemana
                ? 'La fecha corresponde a sábado o domingo y está bloqueada por la regla base. Sólo puede abrirse como HÁBIL_EXTRA desde Calendario laboral.'
                : 'Día hábil disponible por la regla base de lunes a viernes.',
        ];
    }

    $permitido = (int) $fila['es_habil'] === 1
        && (string) $fila['tipo_dia'] !== 'INHABIL';

    return [
        'fecha' => $fecha,
        'configurado' => true,
        'permitido' => $permitido,
        'es_habil' => $permitido,
        'tipo_dia' => (string) $fila['tipo_dia'],
        'motivo' => $fila['motivo'],
        'mensaje' => $permitido
            ? ((string) $fila['tipo_dia'] === 'HABIL_EXTRA'
                ? 'Día habilitado de manera extraordinaria.'
                : 'Día hábil disponible para programación.')
            : 'La fecha está marcada como día inhábil'
                . (!empty($fila['motivo']) ? ': ' . (string) $fila['motivo'] : '.') ,
    ];
}

function sprog_consultar_semana_calendario(PDO $conexion, string $inicio, string $fin): array
{
    $stmt = $conexion->prepare(
        "SELECT fecha, es_habil, tipo_dia, motivo
         FROM calendario_laboral
         WHERE fecha BETWEEN :inicio AND :fin
         ORDER BY fecha"
    );
    $stmt->bindValue(':inicio', $inicio, PDO::PARAM_STR);
    $stmt->bindValue(':fin', $fin, PDO::PARAM_STR);
    $stmt->execute();

    $porFecha = [];
    foreach ($stmt->fetchAll() as $fila) {
        $porFecha[(string) $fila['fecha']] = $fila;
    }

    $dias = [];
    $actual = new DateTimeImmutable($inicio);
    $limite = new DateTimeImmutable($fin);

    while ($actual <= $limite) {
        $fecha = $actual->format('Y-m-d');
        if (isset($porFecha[$fecha])) {
            $fila = $porFecha[$fecha];
            $permitido = (int) $fila['es_habil'] === 1
                && (string) $fila['tipo_dia'] !== 'INHABIL';
            $dias[] = [
                'fecha' => $fecha,
                'configurado' => true,
                'permitido' => $permitido,
                'tipo_dia' => (string) $fila['tipo_dia'],
                'motivo' => $fila['motivo'],
            ];
        } else {
            $finSemana = (int) $actual->format('N') >= 6;
            $dias[] = [
                'fecha' => $fecha,
                'configurado' => true,
                'origen' => 'REGLA_BASE',
                'permitido' => !$finSemana,
                'tipo_dia' => $finSemana ? 'INHABIL' : 'HABIL',
                'motivo' => $finSemana ? 'Fin de semana (regla base).' : null,
            ];
        }
        $actual = $actual->modify('+1 day');
    }

    return $dias;
}

function sprog_siguiente_dia_programable(PDO $conexion, string $fechaBase): string
{
    $fecha = new DateTimeImmutable($fechaBase);

    for ($i = 0; $i < 60; $i++) {
        $candidata = $fecha->modify('+' . $i . ' day')->format('Y-m-d');
        $estado = sprog_estado_calendario($conexion, $candidata);
        if ($estado['permitido']) {
            return $candidata;
        }
    }

    return $fechaBase;
}

function sprog_semana_desde_entrada($valor, bool $proximaPorDefecto): array
{
    $texto = sm_limpiar_texto($valor);

    if ($texto !== '' && sprog_fecha_valida($texto)) {
        $fecha = new DateTimeImmutable($texto);
    } else {
        $fecha = new DateTimeImmutable('today');
        if ($proximaPorDefecto) {
            $fecha = $fecha->modify('next monday');
        }
    }

    $numeroDia = (int) $fecha->format('N');
    $inicio = $fecha->modify('-' . ($numeroDia - 1) . ' days');
    $fin = $inicio->modify('+6 days');

    return [
        'inicio' => $inicio->format('Y-m-d'),
        'fin' => $fin->format('Y-m-d'),
    ];
}

/* =========================================================================
   DETECCIÓN DE ATRASOS
   ========================================================================= */

function sprog_actualizar_atrasos(PDO $conexion): void
{
    if ($conexion->inTransaction()) {
        return;
    }

    $conexion->beginTransaction();

    $stmtPendientes = $conexion->query(
        "SELECT
            s.id AS solicitud_id,
            s.folio,
            s.estado AS estado_solicitud,
            pm.id AS programacion_id,
            pm.fecha_programada,
            st.id AS solicitud_tecnico_id,
            st.tecnico_id
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
           AND pm.estado IN ('PROGRAMADA','VENCIDA')
           AND s.activo = 1
           AND s.estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')
           AND st.resultado_cumplimiento = 'PENDIENTE'
           AND im.id IS NULL
         FOR UPDATE"
    );
    $pendientes = $stmtPendientes->fetchAll();

    $stmtInsertar = $conexion->prepare(
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

    foreach ($pendientes as $fila) {
        $stmtInsertar->execute([
            ':solicitud_id' => (int) $fila['solicitud_id'],
            ':programacion_id' => (int) $fila['programacion_id'],
            ':solicitud_tecnico_id' => (int) $fila['solicitud_tecnico_id'],
            ':fecha_programada' => (string) $fila['fecha_programada'],
        ]);

        $estadoNuevoHistorial = in_array(
            (string) $fila['estado_solicitud'],
            ['EN_PROCESO', 'PAUSADO'],
            true
        )
            ? (string) $fila['estado_solicitud']
            : 'ATRASADO';

        sprog_historial(
            $conexion,
            (int) $fila['solicitud_id'],
            (int) $fila['solicitud_tecnico_id'],
            (int) $fila['programacion_id'],
            'INCUMPLIMIENTO_DETECTADO',
            (string) $fila['estado_solicitud'],
            $estadoNuevoHistorial,
            'SISTEMA',
            null,
            'La actividad superó la fecha programada sin registrarse como terminada.'
        );
    }

    $conexion->exec(
        "UPDATE programaciones_mantenimiento pm
         INNER JOIN solicitudes s ON s.id = pm.solicitud_id
         SET pm.estado = 'VENCIDA'
         WHERE pm.es_actual = 1
           AND pm.fecha_limite < CURDATE()
           AND pm.estado = 'PROGRAMADA'
           AND s.activo = 1
           AND s.estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')"
    );

    $conexion->exec(
        "UPDATE solicitudes s
         INNER JOIN programaciones_mantenimiento pm
                 ON pm.solicitud_id = s.id
                AND pm.es_actual = 1
         SET s.estado = 'ATRASADO'
         WHERE pm.fecha_limite < CURDATE()
           AND s.estado IN ('APROBADO','AGENDADO')
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
   HERRAMIENTAS Y REFACCIONES RECOMENDADAS
   ========================================================================= */

/**
 * Devuelve la lista que debe mostrarse al abrir la programación.
 *
 * Prioridad:
 * 1. Fotografía ya guardada para la solicitud.
 * 2. Plantilla de rutina (compatibilidad con solicitudes rutinarias antiguas).
 * 3. Memoria por equipo + tipo de mantenimiento.
 *
 * @return array{recursos: array<int, array<string, mixed>>, contexto: array<string, mixed>}
 */
function sprog_preparar_recursos_detalle(PDO $conexion, array $solicitud): array
{
    $solicitudId = (int) ($solicitud['id'] ?? 0);
    $tipoSolicitud = (string) ($solicitud['tipo_solicitud'] ?? '');
    $equipoId = (int) ($solicitud['equipo_id'] ?? 0);

    $recursos = sprog_obtener_recursos_solicitud($conexion, $solicitudId);

    if ($recursos !== []) {
        return [
            'recursos' => $recursos,
            'contexto' => [
                'fuente' => 'SOLICITUD',
                'titulo' => 'Recomendación guardada para este mantenimiento',
                'descripcion' => $tipoSolicitud === 'RUTINARIO'
                    ? 'Esta lista pertenece únicamente a esta ejecución. Editarla no modifica la plantilla de la rutina.'
                    : 'Esta lista es la fotografía actual del mantenimiento. Al guardarla también se actualizará la recomendación futura para este equipo y tipo.',
                'actualiza_memoria' => $tipoSolicitud !== 'RUTINARIO',
                'es_rutinario' => $tipoSolicitud === 'RUTINARIO',
                'fotografia_guardada' => true,
            ],
        ];
    }

    if ($tipoSolicitud === 'RUTINARIO') {
        $recursos = sprog_obtener_recursos_rutina_solicitud($conexion, $solicitudId);

        return [
            'recursos' => $recursos,
            'contexto' => [
                'fuente' => $recursos !== [] ? 'RUTINA' : 'VACIO',
                'titulo' => $recursos !== []
                    ? 'Recursos definidos en la plantilla de rutina'
                    : 'La rutina no tiene recursos definidos',
                'descripcion' => $recursos !== []
                    ? 'Puedes ajustar esta ejecución sin cambiar la plantilla. La siguiente ejecución volverá a cargar lo definido en Rutinas.'
                    : 'Puedes agregar herramientas o refacciones para esta ejecución; la plantilla permanecerá sin cambios.',
                'actualiza_memoria' => false,
                'es_rutinario' => true,
                'fotografia_guardada' => false,
            ],
        ];
    }

    $recursos = sprog_obtener_memoria_recursos(
        $conexion,
        $equipoId,
        $tipoSolicitud
    );

    return [
        'recursos' => $recursos,
        'contexto' => [
            'fuente' => $recursos !== [] ? 'MEMORIA' : 'VACIO',
            'titulo' => $recursos !== []
                ? 'Recomendación automática del equipo y mantenimiento'
                : 'Todavía no existe una recomendación',
            'descripcion' => $recursos !== []
                ? 'Se precargó la última selección guardada para este equipo y tipo de mantenimiento. Puedes agregar o retirar elementos.'
                : 'Selecciona lo que el técnico debería llevar. La selección se recordará para el siguiente mantenimiento igual de este equipo.',
            'actualiza_memoria' => true,
            'es_rutinario' => false,
            'fotografia_guardada' => false,
        ],
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function sprog_obtener_recursos_solicitud(PDO $conexion, int $solicitudId): array
{
    if ($solicitudId < 1) {
        return [];
    }

    $stmt = $conexion->prepare(
        "SELECT
            COALESCE(r.id, 0) AS id,
            srr.tipo_recurso,
            COALESCE(r.nombre, srr.nombre_no_catalogado) AS nombre,
            r.codigo,
            r.descripcion,
            COALESCE(r.activo, 1) AS activo,
            srr.origen
         FROM solicitud_recursos_recomendados srr
         LEFT JOIN recursos_mantenimiento r ON r.id = srr.recurso_id
         WHERE srr.solicitud_id = :solicitud_id
         ORDER BY srr.tipo_recurso, nombre, srr.id"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return sprog_convertir_recursos($stmt->fetchAll());
}

/**
 * @return array<int, array<string, mixed>>
 */
function sprog_obtener_recursos_rutina_solicitud(
    PDO $conexion,
    int $solicitudId
): array {
    if ($solicitudId < 1) {
        return [];
    }

    $stmt = $conexion->prepare(
        "SELECT
            r.id,
            r.tipo_recurso,
            r.nombre,
            r.codigo,
            r.descripcion,
            r.activo,
            'RUTINA' AS origen
         FROM rutina_recursos rr
         INNER JOIN recursos_mantenimiento r ON r.id = rr.recurso_id
         WHERE rr.rutina_id = (
             SELECT ra.rutina_id
             FROM rutina_alertas ra
             WHERE ra.solicitud_id = :solicitud_id
             ORDER BY ra.id DESC
             LIMIT 1
         )
         ORDER BY r.tipo_recurso, r.nombre, r.id"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return sprog_convertir_recursos($stmt->fetchAll());
}

/**
 * @return array<int, array<string, mixed>>
 */
function sprog_obtener_memoria_recursos(
    PDO $conexion,
    int $equipoId,
    string $tipoSolicitud
): array {
    if ($equipoId < 1 || $tipoSolicitud === '') {
        return [];
    }

    $stmt = $conexion->prepare(
        "SELECT
            COALESCE(r.id, 0) AS id,
            rr.tipo_recurso,
            COALESCE(r.nombre, rr.nombre_no_catalogado) AS nombre,
            r.codigo,
            r.descripcion,
            COALESCE(r.activo, 1) AS activo,
            'MEMORIA' AS origen
         FROM recomendaciones_recursos rr
         LEFT JOIN recursos_mantenimiento r ON r.id = rr.recurso_id
         WHERE rr.equipo_id = :equipo_id
           AND rr.tipo_solicitud = :tipo_solicitud
         ORDER BY rr.tipo_recurso, nombre, rr.id"
    );
    $stmt->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
    $stmt->bindValue(':tipo_solicitud', $tipoSolicitud, PDO::PARAM_STR);
    $stmt->execute();

    return sprog_convertir_recursos($stmt->fetchAll());
}

/**
 * @param array<int, array<string, mixed>> $filas
 * @return array<int, array<string, mixed>>
 */
function sprog_convertir_recursos(array $filas): array
{
    $resultado = [];

    foreach ($filas as $fila) {
        $nombre = trim((string) ($fila['nombre'] ?? ''));
        if ($nombre === '') {
            continue;
        }

        $resultado[] = [
            'id' => (int) ($fila['id'] ?? 0),
            'tipo_recurso' => (string) ($fila['tipo_recurso'] ?? ''),
            'nombre' => $nombre,
            'codigo' => $fila['codigo'] ?? null,
            'descripcion' => $fila['descripcion'] ?? null,
            'activo' => (int) ($fila['activo'] ?? 1),
            'origen' => (string) ($fila['origen'] ?? 'ADMIN'),
        ];
    }

    return $resultado;
}

/**
 * Bloquea la fotografía actual para evitar que dos administradores la
 * reemplacen al mismo tiempo.
 *
 * @return array<int, array<string, mixed>>
 */
function sprog_bloquear_recursos_solicitud(
    PDO $conexion,
    int $solicitudId
): array {
    $stmt = $conexion->prepare(
        "SELECT
            srr.id,
            srr.solicitud_id,
            srr.tipo_recurso,
            srr.recurso_id,
            srr.nombre_no_catalogado,
            srr.origen
         FROM solicitud_recursos_recomendados srr
         WHERE srr.solicitud_id = :solicitud_id
         ORDER BY srr.id
         FOR UPDATE"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * @param array<int, array<string, mixed>> $recursos
 * @return int[]
 */
function sprog_ids_recursos(array $recursos): array
{
    $ids = [];

    foreach ($recursos as $recurso) {
        $id = (int) ($recurso['recurso_id'] ?? $recurso['id'] ?? 0);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    $ids = array_values($ids);
    sort($ids);

    return $ids;
}

/**
 * Valida que todos los ids existan, correspondan al tipo enviado y puedan
 * utilizarse. Un recurso inactivo únicamente puede conservarse cuando ya
 * venía precargado en la solicitud, memoria o plantilla.
 *
 * @param int[] $herramientasIds
 * @param int[] $refaccionesIds
 * @param array<int, array<string, mixed>> $recursosBase
 * @return array<int, array<string, mixed>>
 */
function sprog_validar_recursos_programacion(
    PDO $conexion,
    array $solicitud,
    array $herramientasIds,
    array $refaccionesIds,
    array $recursosBase
): array {
    $seleccionPorId = [];

    foreach ($herramientasIds as $id) {
        $seleccionPorId[(int) $id] = 'HERRAMIENTA';
    }
    foreach ($refaccionesIds as $id) {
        if (isset($seleccionPorId[(int) $id])) {
            sprog_cancelar(
                $conexion,
                'Un recurso no puede seleccionarse al mismo tiempo como herramienta y refacción.',
                422,
                ['recurso_id' => (int) $id]
            );
        }
        $seleccionPorId[(int) $id] = 'REFACCION';
    }

    if ($seleccionPorId === []) {
        return [];
    }

    $permitidosInactivos = [];
    foreach ($recursosBase as $recurso) {
        $id = (int) ($recurso['id'] ?? 0);
        if ($id > 0) {
            $permitidosInactivos[$id] = true;
        }
    }

    $marcadores = [];
    foreach (array_keys($seleccionPorId) as $indice => $id) {
        $marcadores[] = ':recurso_' . $indice;
    }

    $stmt = $conexion->prepare(
        "SELECT id, tipo_recurso, nombre, codigo, descripcion, activo
         FROM recursos_mantenimiento
         WHERE id IN (" . implode(',', $marcadores) . ")
         ORDER BY tipo_recurso, nombre, id"
    );

    foreach (array_keys($seleccionPorId) as $indice => $id) {
        $stmt->bindValue(':recurso_' . $indice, (int) $id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $filas = $stmt->fetchAll();

    if (count($filas) !== count($seleccionPorId)) {
        sprog_cancelar(
            $conexion,
            'Una herramienta o refacción seleccionada ya no existe en el catálogo.',
            422,
            ['campo' => 'recursos_recomendados']
        );
    }

    foreach ($filas as &$fila) {
        $id = (int) $fila['id'];
        $tipoEsperado = $seleccionPorId[$id];

        if ((string) $fila['tipo_recurso'] !== $tipoEsperado) {
            sprog_cancelar(
                $conexion,
                'Una selección no corresponde al tipo de recurso esperado.',
                422,
                ['recurso_id' => $id]
            );
        }

        if ((int) $fila['activo'] !== 1 && !isset($permitidosInactivos[$id])) {
            sprog_cancelar(
                $conexion,
                'El recurso "' . (string) $fila['nombre'] . '" está desactivado y no puede agregarse.',
                422,
                ['recurso_id' => $id]
            );
        }

        $fila['id'] = $id;
        $fila['activo'] = (int) $fila['activo'];
    }
    unset($fila);

    return $filas;
}

/**
 * @param array<int, array<string, mixed>> $recursosSeleccionados
 */
function sprog_reemplazar_recursos_solicitud(
    PDO $conexion,
    array $solicitud,
    array $recursosSeleccionados,
    int $adminId
): void {
    $solicitudId = (int) $solicitud['id'];

    $idsOrigenRutina = [];
    if ((string) $solicitud['tipo_solicitud'] === 'RUTINARIO') {
        $stmtOrigen = $conexion->prepare(
            "SELECT recurso_id
             FROM solicitud_recursos_recomendados
             WHERE solicitud_id = :solicitud_id
               AND origen = 'RUTINA'
               AND recurso_id IS NOT NULL"
        );
        $stmtOrigen->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmtOrigen->execute();

        foreach ($stmtOrigen->fetchAll(PDO::FETCH_COLUMN) as $recursoId) {
            $idsOrigenRutina[(int) $recursoId] = true;
        }
    }

    $stmtEliminar = $conexion->prepare(
        "DELETE FROM solicitud_recursos_recomendados
         WHERE solicitud_id = :solicitud_id"
    );
    $stmtEliminar->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtEliminar->execute();

    if ($recursosSeleccionados === []) {
        return;
    }

    $idsPlantilla = $idsOrigenRutina;
    if ((string) $solicitud['tipo_solicitud'] === 'RUTINARIO') {
        foreach (sprog_obtener_recursos_rutina_solicitud($conexion, $solicitudId) as $recurso) {
            $id = (int) ($recurso['id'] ?? 0);
            if ($id > 0) {
                $idsPlantilla[$id] = true;
            }
        }
    }

    $stmtInsertar = $conexion->prepare(
        "INSERT INTO solicitud_recursos_recomendados
        (
            solicitud_id,
            tipo_recurso,
            recurso_id,
            nombre_no_catalogado,
            origen,
            agregado_por_admin_id,
            fecha_registro,
            fecha_actualizacion
        )
        VALUES
        (
            :solicitud_id,
            :tipo_recurso,
            :recurso_id,
            NULL,
            :origen,
            :admin_id,
            NOW(),
            NOW()
        )"
    );

    foreach ($recursosSeleccionados as $recurso) {
        $recursoId = (int) $recurso['id'];
        $origen = (string) $solicitud['tipo_solicitud'] === 'RUTINARIO'
            && isset($idsPlantilla[$recursoId])
            ? 'RUTINA'
            : 'ADMIN';

        $stmtInsertar->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmtInsertar->bindValue(':tipo_recurso', (string) $recurso['tipo_recurso'], PDO::PARAM_STR);
        $stmtInsertar->bindValue(':recurso_id', $recursoId, PDO::PARAM_INT);
        $stmtInsertar->bindValue(':origen', $origen, PDO::PARAM_STR);
        $stmtInsertar->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmtInsertar->execute();
    }
}

/**
 * Los mantenimientos normales recuerdan exactamente la última selección del
 * administrador para el mismo equipo y tipo. Las rutinas no llaman a esta
 * función porque su fuente permanente es la plantilla.
 *
 * @param array<int, array<string, mixed>> $recursosSeleccionados
 */
function sprog_reemplazar_memoria_recomendaciones(
    PDO $conexion,
    array $solicitud,
    array $recursosSeleccionados,
    int $adminId
): void {
    $equipoId = (int) $solicitud['equipo_id'];
    $tipoSolicitud = (string) $solicitud['tipo_solicitud'];
    $solicitudId = (int) $solicitud['id'];

    $stmtEliminar = $conexion->prepare(
        "DELETE FROM recomendaciones_recursos
         WHERE equipo_id = :equipo_id
           AND tipo_solicitud = :tipo_solicitud"
    );
    $stmtEliminar->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
    $stmtEliminar->bindValue(':tipo_solicitud', $tipoSolicitud, PDO::PARAM_STR);
    $stmtEliminar->execute();

    if ($recursosSeleccionados === []) {
        return;
    }

    $stmtInsertar = $conexion->prepare(
        "INSERT INTO recomendaciones_recursos
        (
            equipo_id,
            tipo_solicitud,
            tipo_recurso,
            recurso_id,
            nombre_no_catalogado,
            origen_ultima_actualizacion,
            solicitud_origen_id,
            actualizado_por_admin_id,
            actualizado_por_tecnico_id,
            fecha_registro,
            fecha_actualizacion
        )
        VALUES
        (
            :equipo_id,
            :tipo_solicitud,
            :tipo_recurso,
            :recurso_id,
            NULL,
            'ADMIN',
            :solicitud_id,
            :admin_id,
            NULL,
            NOW(),
            NOW()
        )"
    );

    foreach ($recursosSeleccionados as $recurso) {
        $stmtInsertar->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
        $stmtInsertar->bindValue(':tipo_solicitud', $tipoSolicitud, PDO::PARAM_STR);
        $stmtInsertar->bindValue(':tipo_recurso', (string) $recurso['tipo_recurso'], PDO::PARAM_STR);
        $stmtInsertar->bindValue(':recurso_id', (int) $recurso['id'], PDO::PARAM_INT);
        $stmtInsertar->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmtInsertar->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmtInsertar->execute();
    }
}

function sprog_detalle_peligro_confirmado(array $solicitud): string
{
    $detalle = trim((string) ($solicitud['detalle_trabajo_peligroso'] ?? ''));

    if ($detalle !== '') {
        return sprog_recortar($detalle, 200);
    }

    $nivel = trim((string) ($solicitud['nivel_riesgo'] ?? ''));
    return sprog_recortar(
        $nivel !== ''
            ? 'Trabajo peligroso. Nivel de riesgo: ' . $nivel . '.'
            : 'Trabajo marcado como peligroso sin una nota específica.',
        200
    );
}

/* =========================================================================
   OPERACIONES DE BASE DE DATOS
   ========================================================================= */

function sprog_bloquear_solicitud(PDO $conexion, int $solicitudId): ?array
{
    $stmt = $conexion->prepare(
        "SELECT
            s.*,
            e.codigo_equipo,
            e.nombre_equipo
         FROM solicitudes s
         INNER JOIN equipos e ON e.id = s.equipo_id
         WHERE s.id = :id
           AND s.activo = 1
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bindValue(':id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch();

    return $fila ?: null;
}

function sprog_bloquear_programacion_actual(PDO $conexion, int $solicitudId): ?array
{
    $stmt = $conexion->prepare(
        "SELECT *
         FROM programaciones_mantenimiento
         WHERE solicitud_id = :solicitud_id
           AND es_actual = 1
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch();

    return $fila ?: null;
}

function sprog_bloquear_asignaciones_actuales(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT st.*
         FROM solicitud_tecnicos st
         WHERE st.solicitud_id = :solicitud_id
           AND st.origen = 'ADMIN'
           AND st.activo = 1
         ORDER BY st.id
         FOR UPDATE"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sprog_bloquear_ejecuciones_solicitud(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT id, solicitud_tecnico_id, tecnico_id, estado, fecha_hora_inicio
         FROM ejecuciones_mantenimiento
         WHERE solicitud_id = :solicitud_id
         ORDER BY id
         FOR UPDATE"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sprog_bloquear_tecnicos(PDO $conexion, array $ids): array
{
    if ($ids === []) {
        return [];
    }

    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conexion->prepare(
        "SELECT id, nombre, apellido_paterno, apellido_materno, turno, especialidad, departamento_id
         FROM tecnicos
         WHERE activo = 1
           AND id IN ($marcas)
         ORDER BY id
         FOR UPDATE"
    );

    foreach ($ids as $indice => $id) {
        $stmt->bindValue($indice + 1, $id, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sprog_insertar_programacion(
    PDO $conexion,
    int $solicitudId,
    string $fecha,
    int $adminId,
    string $motivo
): int {
    $stmt = $conexion->prepare(
        "INSERT INTO programaciones_mantenimiento
        (
            solicitud_id,
            fecha_programada,
            fecha_limite,
            estado,
            es_actual,
            vigente_token,
            programado_por_admin_id,
            motivo_programacion,
            fecha_registro,
            fecha_actualizacion
        )
        VALUES
        (
            :solicitud_id,
            :fecha_programada,
            :fecha_limite,
            'PROGRAMADA',
            1,
            1,
            :admin_id,
            :motivo,
            NOW(),
            NOW()
        )"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':fecha_programada', $fecha, PDO::PARAM_STR);
    $stmt->bindValue(':fecha_limite', $fecha, PDO::PARAM_STR);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    sprog_bind_texto_nullable($stmt, ':motivo', $motivo);
    $stmt->execute();

    return (int) $conexion->lastInsertId();
}

function sprog_cerrar_programacion_anterior(PDO $conexion, int $programacionId, string $motivo): void
{
    $stmt = $conexion->prepare(
        "UPDATE programaciones_mantenimiento
         SET estado = 'REPROGRAMADA',
             es_actual = 0,
             vigente_token = NULL,
             motivo_reprogramacion = :motivo,
             fecha_actualizacion = NOW()
         WHERE id = :id
           AND es_actual = 1"
    );
    $stmt->bindValue(':motivo', $motivo, PDO::PARAM_STR);
    $stmt->bindValue(':id', $programacionId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        sprog_cancelar(
            $conexion,
            'La programación fue modificada por otro usuario. Actualiza la página.',
            409
        );
    }
}

function sprog_cerrar_programacion_cancelada(
    PDO $conexion,
    int $programacionId,
    string $motivo
): void {
    $stmt = $conexion->prepare(
        "UPDATE programaciones_mantenimiento
         SET estado = 'CANCELADA',
             es_actual = 0,
             vigente_token = NULL,
             motivo_cancelacion = :motivo,
             fecha_actualizacion = NOW()
         WHERE id = :id
           AND es_actual = 1"
    );
    $stmt->bindValue(':motivo', $motivo, PDO::PARAM_STR);
    $stmt->bindValue(':id', $programacionId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        sprog_cancelar(
            $conexion,
            'La programación fue modificada por otro usuario. Actualiza la pantalla.',
            409
        );
    }
}

function sprog_insertar_asignacion(
    PDO $conexion,
    int $solicitudId,
    int $programacionId,
    int $tecnicoId,
    int $adminId,
    bool $alertaNocturna,
    bool $confirmada,
    string $observacion,
    string $detallePeligro
): int {
    $stmt = $conexion->prepare(
        "INSERT INTO solicitud_tecnicos
        (
            solicitud_id,
            programacion_id,
            tecnico_id,
            origen,
            estado,
            asignado_por_admin_id,
            fecha_asignacion,
            fecha_aceptacion,
            fecha_retiro,
            alerta_riesgo_nocturno,
            riesgo_nocturno_confirmado,
            confirmado_por_admin_id,
            fecha_confirmacion_riesgo_nocturno,
            detalle_riesgo_nocturno_confirmado,
            observacion_riesgo_nocturno,
            resultado_cumplimiento,
            fecha_resultado,
            activo,
            activo_token,
            fecha_actualizacion
        )
        VALUES
        (
            :solicitud_id,
            :programacion_id,
            :tecnico_id,
            'ADMIN',
            'ASIGNADO',
            :admin_id,
            NOW(),
            NULL,
            NULL,
            :alerta,
            :confirmada,
            :confirmado_por,
            :fecha_confirmacion,
            :detalle_confirmado,
            :observacion,
            'PENDIENTE',
            NULL,
            1,
            1,
            NOW()
        )"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':programacion_id', $programacionId, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':alerta', $alertaNocturna ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':confirmada', $alertaNocturna && $confirmada ? 1 : 0, PDO::PARAM_INT);

    if ($alertaNocturna && $confirmada) {
        $stmt->bindValue(':confirmado_por', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_confirmacion', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':detalle_confirmado', $detallePeligro, PDO::PARAM_STR);
    } else {
        $stmt->bindValue(':confirmado_por', null, PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_confirmacion', null, PDO::PARAM_NULL);
        $stmt->bindValue(':detalle_confirmado', null, PDO::PARAM_NULL);
    }

    sprog_bind_texto_nullable(
        $stmt,
        ':observacion',
        $alertaNocturna ? $observacion : ''
    );
    $stmt->execute();

    return (int) $conexion->lastInsertId();
}

function sprog_retirar_asignacion(
    PDO $conexion,
    array $asignacion,
    int $adminId,
    string $motivo,
    bool $porReprogramacion,
    bool $porCancelacion = false
): void {
    $asignacionId = (int) $asignacion['id'];
    $solicitudId = (int) $asignacion['solicitud_id'];
    $tecnicoId = (int) $asignacion['tecnico_id'];

    $stmtEjecucion = $conexion->prepare(
        "UPDATE ejecuciones_mantenimiento
         SET estado = 'CANCELADA',
             fecha_hora_fin = COALESCE(fecha_hora_fin, NOW()),
             fecha_hora_fin_original = COALESCE(fecha_hora_fin_original, NOW()),
             en_proceso_token = NULL,
             fecha_actualizacion = NOW()
         WHERE solicitud_tecnico_id = :asignacion_id
           AND estado = 'PENDIENTE'
           AND fecha_hora_inicio IS NULL"
    );
    $stmtEjecucion->bindValue(':asignacion_id', $asignacionId, PDO::PARAM_INT);
    $stmtEjecucion->execute();

    $stmt = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET estado = 'RETIRADO',
             fecha_retiro = NOW(),
             resultado_cumplimiento = CASE
                 WHEN resultado_cumplimiento = 'PENDIENTE' THEN 'NO_APLICA'
                 ELSE resultado_cumplimiento
             END,
             fecha_resultado = COALESCE(fecha_resultado, NOW()),
             activo = 0,
             activo_token = NULL,
             fecha_actualizacion = NOW()
         WHERE id = :id
           AND activo = 1
           AND estado IN ('ASIGNADO','ACEPTADO')"
    );
    $stmt->bindValue(':id', $asignacionId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        sprog_cancelar(
            $conexion,
            'No fue posible retirar una asignación porque ya fue iniciada o modificada.',
            409
        );
    }

    if ($porCancelacion) {
        $descripcion = 'La asignación se cerró porque el mantenimiento fue cancelado antes de iniciar. Motivo: '
            . $motivo;
    } elseif ($porReprogramacion) {
        $descripcion = 'Se cerró la asignación anterior debido a una reprogramación. Motivo: '
            . $motivo;
    } else {
        $descripcion = 'El administrador retiró al técnico antes de que iniciara. Motivo: '
            . $motivo;
    }

    sprog_historial(
        $conexion,
        $solicitudId,
        $asignacionId,
        isset($asignacion['programacion_id']) && $asignacion['programacion_id'] !== null
            ? (int) $asignacion['programacion_id']
            : null,
        'TECNICO_RETIRADO',
        null,
        'RETIRADO',
        'ADMIN',
        $adminId,
        $descripcion
    );

    sprog_notificar(
        $conexion,
        'TECNICO',
        $tecnicoId,
        $solicitudId,
        $porCancelacion
            ? 'Mantenimiento cancelado'
            : ($porReprogramacion ? 'Mantenimiento reprogramado' : 'Asignación retirada'),
        $porCancelacion
            ? 'El mantenimiento fue cancelado y se retiró de tu lista antes de que lo iniciaras. Motivo: ' . $motivo
            : ($porReprogramacion
                ? 'La programación anterior fue reemplazada. Revisa tu lista de mantenimientos para consultar la nueva asignación.'
                : 'El mantenimiento fue retirado de tu lista antes de que lo iniciaras. Motivo: ' . $motivo),
        $porCancelacion ? 'DANGER' : 'WARNING'
    );
}

function sprog_actualizar_alerta_asignacion(
    PDO $conexion,
    int $asignacionId,
    bool $alerta,
    bool $confirmada,
    int $adminId,
    string $observacion,
    string $detallePeligro
): void {
    $stmt = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET alerta_riesgo_nocturno = :alerta,
             riesgo_nocturno_confirmado = :confirmada,
             confirmado_por_admin_id = :confirmado_por,
             fecha_confirmacion_riesgo_nocturno = :fecha_confirmacion,
             detalle_riesgo_nocturno_confirmado = :detalle_confirmado,
             observacion_riesgo_nocturno = :observacion,
             fecha_actualizacion = NOW()
         WHERE id = :id
           AND activo = 1"
    );
    $stmt->bindValue(':alerta', $alerta ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':confirmada', $alerta && $confirmada ? 1 : 0, PDO::PARAM_INT);

    if ($alerta && $confirmada) {
        $stmt->bindValue(':confirmado_por', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_confirmacion', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':detalle_confirmado', $detallePeligro, PDO::PARAM_STR);
    } else {
        $stmt->bindValue(':confirmado_por', null, PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_confirmacion', null, PDO::PARAM_NULL);
        $stmt->bindValue(':detalle_confirmado', null, PDO::PARAM_NULL);
    }

    sprog_bind_texto_nullable($stmt, ':observacion', $alerta ? $observacion : '');
    $stmt->bindValue(':id', $asignacionId, PDO::PARAM_INT);
    $stmt->execute();
}

/* =========================================================================
   HISTORIAL, MOVIMIENTOS Y NOTIFICACIONES
   ========================================================================= */

function sprog_historial(
    PDO $conexion,
    int $solicitudId,
    ?int $solicitudTecnicoId,
    ?int $programacionId,
    string $evento,
    ?string $estadoAnterior,
    ?string $estadoNuevo,
    string $actorTipo,
    ?int $actorId,
    string $descripcion
): void {
    $stmt = $conexion->prepare(
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
    sprog_bind_int_nullable($stmt, ':solicitud_tecnico_id', $solicitudTecnicoId);
    sprog_bind_int_nullable($stmt, ':programacion_id', $programacionId);
    $stmt->bindValue(':evento', $evento, PDO::PARAM_STR);
    sprog_bind_texto_nullable($stmt, ':estado_anterior', $estadoAnterior ?? '');
    sprog_bind_texto_nullable($stmt, ':estado_nuevo', $estadoNuevo ?? '');
    $stmt->bindValue(':actor_tipo', $actorTipo, PDO::PARAM_STR);
    sprog_bind_int_nullable($stmt, ':actor_id', $actorId);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->execute();
}

function sprog_movimiento(
    PDO $conexion,
    int $adminId,
    string $accion,
    string $modulo,
    string $descripcion,
    string $tabla,
    int $registroId
): void {
    $stmt = $conexion->prepare(
        "INSERT INTO movimientos_sistema
        (
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
        )
        VALUES
        (
            'ADMIN',
            :usuario_id,
            :accion,
            :modulo,
            :descripcion,
            :tabla,
            :registro_id,
            :ip,
            :agente,
            NOW()
        )"
    );
    $stmt->bindValue(':usuario_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':modulo', $modulo, PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':tabla', $tabla, PDO::PARAM_STR);
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    $stmt->bindValue(':ip', substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 60), PDO::PARAM_STR);
    $stmt->bindValue(':agente', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), PDO::PARAM_STR);
    $stmt->execute();
}

function sprog_notificar(
    PDO $conexion,
    string $tipoUsuario,
    int $usuarioId,
    int $solicitudId,
    string $titulo,
    string $mensaje,
    string $tipo
): void {
    if ($usuarioId < 1) {
        return;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO notificaciones
        (
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
        VALUES
        (
            :tipo_usuario,
            :usuario_id,
            :solicitud_id,
            NULL,
            NULL,
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
    $stmt->bindValue(':titulo', sprog_recortar($titulo, 180), PDO::PARAM_STR);
    $stmt->bindValue(':mensaje', sprog_recortar($mensaje, 1000), PDO::PARAM_STR);
    $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
    $stmt->execute();
}

function sprog_notificar_solicitante(
    PDO $conexion,
    array $solicitud,
    int $adminActual,
    int $solicitudId,
    string $titulo,
    string $mensaje,
    string $tipo
): void {
    if (!empty($solicitud['solicitante_id'])) {
        sprog_notificar(
            $conexion,
            'SOLICITANTE',
            (int) $solicitud['solicitante_id'],
            $solicitudId,
            $titulo,
            $mensaje,
            $tipo
        );
        return;
    }

    if (
        !empty($solicitud['administrador_solicitante_id'])
        && (int) $solicitud['administrador_solicitante_id'] !== $adminActual
    ) {
        sprog_notificar(
            $conexion,
            'ADMIN',
            (int) $solicitud['administrador_solicitante_id'],
            $solicitudId,
            $titulo,
            $mensaje,
            $tipo
        );
    }
}

/* =========================================================================
   VALIDACIONES Y UTILIDADES
   ========================================================================= */

function sprog_validar_admin_activo(PDO $conexion, int $adminId): void
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
            'Tu cuenta de administrador ya no está activa.',
            ['sesion_expirada' => true, 'redirect' => '../login.php?sesion=expirada'],
            401
        );
    }
}

function sprog_admin_id(): int
{
    $id = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        sm_responder_json(false, 'La sesión no contiene un administrador válido.', [], 401);
    }

    return (int) $id;
}

function sprog_id_entrada($valor, string $campo): int
{
    $id = filter_var($valor, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        sm_responder_json(
            false,
            'El identificador recibido no es válido.',
            ['campo' => $campo],
            422
        );
    }

    return (int) $id;
}

function sprog_fecha_entrada($valor, string $campo): string
{
    $fecha = sm_limpiar_texto($valor);
    if (!sprog_fecha_valida($fecha)) {
        sm_responder_json(
            false,
            'Selecciona una fecha válida.',
            ['campo' => $campo],
            422
        );
    }

    return $fecha;
}

function sprog_fecha_valida(string $fecha): bool
{
    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $objeto instanceof DateTimeImmutable
        && $objeto->format('Y-m-d') === $fecha;
}

function sprog_normalizar_ids($valor): array
{
    if (!is_array($valor)) {
        $valor = [$valor];
    }

    $ids = [];
    foreach ($valor as $item) {
        $id = filter_var($item, FILTER_VALIDATE_INT);
        if ($id !== false && $id > 0) {
            $ids[] = (int) $id;
        }
    }

    $ids = array_values(array_unique($ids));
    sort($ids);

    return $ids;
}

function sprog_texto($valor, int $minimo, int $maximo, string $campo): string
{
    $texto = sm_limpiar_texto($valor);
    $longitud = sprog_longitud($texto);

    if ($longitud < $minimo || $longitud > $maximo) {
        sm_responder_json(
            false,
            $minimo > 0
                ? 'El campo debe contener entre ' . $minimo . ' y ' . $maximo . ' caracteres.'
                : 'El campo no puede superar ' . $maximo . ' caracteres.',
            ['campo' => $campo],
            422
        );
    }

    return $texto;
}

function sprog_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function sprog_recortar(string $texto, int $maximo): string
{
    if (sprog_longitud($texto) <= $maximo) {
        return $texto;
    }

    return function_exists('mb_substr')
        ? mb_substr($texto, 0, $maximo, 'UTF-8')
        : substr($texto, 0, $maximo);
}

function sprog_nombre_tecnico(array $tecnico): string
{
    $nombre = trim(implode(' ', array_filter([
        (string) ($tecnico['nombre'] ?? ''),
        (string) ($tecnico['apellido_paterno'] ?? ''),
        (string) ($tecnico['apellido_materno'] ?? ''),
    ])));

    return $nombre !== '' ? $nombre : 'Técnico';
}

function sprog_tecnico_por_id(array $tecnicos, int $id): ?array
{
    foreach ($tecnicos as $tecnico) {
        if ((int) $tecnico['id'] === $id) {
            return $tecnico;
        }
    }

    return null;
}

function sprog_nivel_carga(int $carga): string
{
    if ($carga <= 3) {
        return 'BAJA';
    }

    if ($carga <= 6) {
        return 'MEDIA';
    }

    return 'ALTA';
}

function sprog_fecha_es(string $fecha): string
{
    if (!sprog_fecha_valida($fecha)) {
        return $fecha;
    }

    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    $objeto = new DateTimeImmutable($fecha);
    return (int) $objeto->format('j') . ' de '
        . $meses[(int) $objeto->format('n')] . ' de '
        . $objeto->format('Y');
}

function sprog_bind_texto_nullable(PDOStatement $stmt, string $parametro, string $texto): void
{
    if ($texto === '') {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, $texto, PDO::PARAM_STR);
}

function sprog_bind_int_nullable(PDOStatement $stmt, string $parametro, ?int $valor): void
{
    if ($valor === null) {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, $valor, PDO::PARAM_INT);
}

function sprog_cancelar(
    PDO $conexion, 
    string $mensaje,
    int $codigo,
    array $extra = []
): void {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    sm_responder_json(false, $mensaje, $extra, $codigo);
}