<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tiempos reales - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| - Exclusivo para ADMIN.
| - Consulta ejecuciones activas, pausadas, terminadas y canceladas.
| - El tiempo activo descuenta todas las pausas registradas.
| - No utiliza horas estimadas: el modelo 1.1 no las contempla.
| - Una corrección sólo se permite cuando la solicitud y la ejecución están
|   terminadas, no existen pausas abiertas y se conserva el valor original.
| - Toda corrección genera auditoría, historial y movimiento del sistema.
| - Si la corrección demuestra cumplimiento dentro del plazo, se elimina el
|   incumplimiento automático relacionado y se conserva en auditoría.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_sesion(['ADMIN'], true);

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
}

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
    ? strtoupper(tej_texto($_GET['accion'] ?? 'INICIAL'))
    : strtoupper(tej_texto($_POST['accion'] ?? ''));

try {
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if ($metodo === 'GET') {
        if ($accion === 'INICIAL' || $accion === 'LISTAR') {
            tej_cargar_listado($conexion);
        }

        if ($accion === 'DETALLE') {
            tej_cargar_detalle($conexion);
        }

        if ($accion === 'EXPORTAR') {
            tej_exportar_csv($conexion);
        }

        sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
    }

    sm_requerir_metodo('POST');
    sm_validar_csrf();

    if ($accion === 'CORREGIR_TIEMPOS') {
        tej_corregir_tiempos($conexion, false);
    }

    if ($accion === 'RESTAURAR_TIEMPOS') {
        tej_corregir_tiempos($conexion, true);
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'TEJ-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][PDO] ' . $e->getMessage()
        . ' | Archivo: ' . $e->getFile()
        . ' | Línea: ' . $e->getLine()
    );

    if ((string) $e->getCode() === '23000') {
        sm_responder_json(
            false,
            'La información cambió mientras realizabas la operación. Actualiza la pantalla e inténtalo nuevamente.',
            ['referencia' => $referencia],
            409
        );
    }

    sm_responder_json(
        false,
        'No fue posible consultar los tiempos en este momento.',
        ['referencia' => $referencia],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'TEJ-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][GENERAL] ' . $e->getMessage()
        . ' | Archivo: ' . $e->getFile()
        . ' | Línea: ' . $e->getLine()
    );

    sm_responder_json(
        false,
        'No fue posible procesar la información de Tiempos reales.',
        ['referencia' => $referencia],
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function tej_cargar_listado(PDO $conexion): void
{
    $adminId = tej_admin_id();
    tej_validar_admin_activo($conexion, $adminId);

    $filtros = tej_leer_filtros();
    $consulta = tej_construir_condiciones($filtros);

    $total = tej_contar($conexion, $consulta['where'], $consulta['parametros']);
    $porPagina = (int) $filtros['por_pagina'];
    $totalPaginas = max(1, (int) ceil($total / max(1, $porPagina)));
    $pagina = min((int) $filtros['pagina'], $totalPaginas);
    $offset = max(0, ($pagina - 1) * $porPagina);
    $filtros['pagina'] = $pagina;

    $registros = tej_obtener_registros(
        $conexion,
        $consulta['where'],
        $consulta['parametros'],
        (string) $filtros['orden'],
        $porPagina,
        $offset
    );

    $resumen = tej_obtener_resumen(
        $conexion,
        $consulta['where'],
        $consulta['parametros']
    );

    sm_responder_json(
        true,
        'Tiempos cargados correctamente.',
        [
            'csrf_token' => sm_token_csrf(),
            'resumen' => $resumen,
            'registros' => $registros,
            'catalogos' => tej_catalogos($conexion),
            'filtros' => $filtros,
            'paginacion' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $total,
                'total_paginas' => $totalPaginas,
                'desde' => $total === 0 ? 0 : $offset + 1,
                'hasta' => min($total, $offset + count($registros)),
            ],
            'fecha_servidor' => date('Y-m-d H:i:s'),
            'reglas' => [
                'usa_horas_estimadas' => false,
                'correccion_solo_terminadas' => true,
                'motivo_minimo' => 15,
                'motivo_maximo' => 500,
            ],
        ]
    );
}

function tej_cargar_detalle(PDO $conexion): void
{
    $adminId = tej_admin_id();
    tej_validar_admin_activo($conexion, $adminId);

    $ejecucionId = tej_entero_positivo($_GET['id'] ?? null, 'ejecución');
    $detalle = tej_obtener_detalle($conexion, $ejecucionId, false);

    if (!$detalle) {
        sm_responder_json(
            false,
            'La ejecución no existe o ya no está disponible.',
            [],
            404
        );
    }

    $pausas = tej_obtener_pausas($conexion, $ejecucionId, false);
    $auditoria = tej_obtener_auditoria($conexion, $ejecucionId);
    $historial = tej_obtener_historial_relacionado(
        $conexion,
        (int) $detalle['solicitud_id']
    );

    $detalle = tej_preparar_detalle($detalle, $pausas);

    sm_responder_json(
        true,
        'Detalle cargado correctamente.',
        [
            'ejecucion' => $detalle,
            'pausas' => $pausas,
            'auditoria' => $auditoria,
            'historial' => $historial,
            'csrf_token' => sm_token_csrf(),
        ]
    );
}

function tej_exportar_csv(PDO $conexion): void
{
    $adminId = tej_admin_id();
    tej_validar_admin_activo($conexion, $adminId);

    $filtros = tej_leer_filtros(true);
    $consulta = tej_construir_condiciones($filtros);
    $limite = 5000;

    $registros = tej_obtener_registros(
        $conexion,
        $consulta['where'],
        $consulta['parametros'],
        (string) $filtros['orden'],
        $limite,
        0
    );

    $nombre = 'tiempos_reales_' . date('Ymd_His') . '.csv';

    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    $salida = fopen('php://output', 'wb');
    if ($salida === false) {
        throw new RuntimeException('No fue posible generar el archivo CSV.');
    }

    fwrite($salida, "\xEF\xBB\xBF");
    tej_escribir_csv($salida, [
        'Folio',
        'Tipo de solicitud',
        'Estado de ejecución',
        'Técnico',
        'Equipo',
        'Departamento',
        'Fecha programada',
        'Fecha límite',
        'Inicio real',
        'Fin real',
        'Tiempo activo',
        'Tiempo en pausa',
        'Tiempo transcurrido',
        'Porcentaje activo',
        'Pausas',
        'Cumplimiento',
        'Trabajo quedó',
        'Tiempos corregidos',
        'Revisión requerida',
    ]);

    foreach ($registros as $item) {
        tej_escribir_csv($salida, [
            $item['folio'],
            tej_tipo_texto((string) $item['tipo_solicitud']),
            tej_estado_texto((string) $item['estado_ejecucion']),
            $item['tecnico'],
            trim((string) $item['codigo_equipo'] . ' - ' . (string) $item['nombre_equipo'], ' -'),
            $item['departamento'],
            $item['fecha_programada'],
            $item['fecha_limite'],
            $item['fecha_hora_inicio'],
            $item['fecha_hora_fin'],
            tej_duracion_csv((int) $item['segundos_activos_actuales']),
            tej_duracion_csv((int) $item['segundos_pausa_actuales']),
            tej_duracion_csv((int) $item['segundos_transcurridos']),
            $item['porcentaje_activo'] === null
                ? ''
                : number_format((float) $item['porcentaje_activo'], 1, '.', '') . '%',
            (int) $item['total_pausas'],
            tej_cumplimiento_texto((string) $item['resultado_cumplimiento']),
            tej_trabajo_quedo_texto((string) $item['trabajo_quedo']),
            (int) $item['fue_editada'] === 1 ? 'Sí' : 'No',
            (int) $item['requiere_revision'] === 1
                ? implode('; ', (array) $item['alertas_revision'])
                : 'No',
        ]);
    }

    fclose($salida);
    exit;
}

function tej_corregir_tiempos(PDO $conexion, bool $restaurar): void
{
    $adminId = tej_admin_id();
    tej_validar_admin_activo($conexion, $adminId);

    $ejecucionId = tej_entero_positivo(
        $_POST['ejecucion_id'] ?? null,
        'ejecución'
    );
    $motivo = tej_texto_validado(
        $_POST['motivo'] ?? '',
        15,
        500,
        'motivo'
    );

    $inicioNuevo = null;
    $finNuevo = null;

    if (!$restaurar) {
        $inicioNuevo = tej_fecha_hora_entrada(
            $_POST['fecha_hora_inicio'] ?? null,
            'fecha_hora_inicio'
        );
        $finNuevo = tej_fecha_hora_entrada(
            $_POST['fecha_hora_fin'] ?? null,
            'fecha_hora_fin'
        );
    }

    $conexion->beginTransaction();

    $ejecucion = tej_obtener_detalle($conexion, $ejecucionId, true);
    if (!$ejecucion) {
        tej_abortar($conexion, 'La ejecución ya no existe.', 404);
    }

    if ((string) $ejecucion['estado_ejecucion'] !== 'TERMINADA') {
        tej_abortar(
            $conexion,
            'Sólo pueden corregirse ejecuciones que ya estén terminadas.',
            409
        );
    }

    if ((string) $ejecucion['estado_solicitud'] !== 'TERMINADO') {
        tej_abortar(
            $conexion,
            'Espera a que el mantenimiento completo sea cerrado antes de corregir los tiempos de un participante.',
            409
        );
    }

    if (empty($ejecucion['fecha_hora_cierre'])) {
        tej_abortar(
            $conexion,
            'La solicitud no tiene un cierre válido. Revisa primero el expediente del mantenimiento.',
            409
        );
    }

    $pausas = tej_obtener_pausas($conexion, $ejecucionId, true);

    foreach ($pausas as $pausa) {
        if (empty($pausa['fecha_hora_fin']) || (int) $pausa['abierta'] === 1) {
            tej_abortar(
                $conexion,
                'La ejecución conserva una pausa abierta. No se pueden corregir sus tiempos hasta resolver esa inconsistencia.',
                409
            );
        }
    }

    if ($restaurar) {
        if (
            empty($ejecucion['fecha_hora_inicio_original'])
            || empty($ejecucion['fecha_hora_fin_original'])
        ) {
            tej_abortar(
                $conexion,
                'Esta ejecución no tiene valores originales disponibles para restaurar.',
                409
            );
        }

        $inicioNuevo = (string) $ejecucion['fecha_hora_inicio_original'];
        $finNuevo = (string) $ejecucion['fecha_hora_fin_original'];
    }

    if ($inicioNuevo === null || $finNuevo === null) {
        tej_abortar($conexion, 'Las fechas proporcionadas no son válidas.', 422);
    }

    tej_validar_rango_correccion(
        $conexion,
        $ejecucion,
        $pausas,
        $inicioNuevo,
        $finNuevo
    );

    $calculo = tej_calcular_tiempos_rango($inicioNuevo, $finNuevo, $pausas);
    $resultadoNuevo = tej_resultado_cumplimiento_corregido(
        $ejecucion,
        $finNuevo
    );

    $incumplimientoAnterior = tej_obtener_incumplimiento_correccion(
        $conexion,
        (int) $ejecucion['solicitud_tecnico_id'],
        $ejecucion['programacion_id'] !== null
            ? (int) $ejecucion['programacion_id']
            : null
    );

    $requiereSincronizarIncumplimiento = tej_requiere_sincronizar_incumplimiento(
        $ejecucion,
        $resultadoNuevo,
        $incumplimientoAnterior
    );

    if (
        (string) ($ejecucion['fecha_hora_inicio'] ?? '') === $inicioNuevo
        && (string) ($ejecucion['fecha_hora_fin'] ?? '') === $finNuevo
        && (int) $ejecucion['total_segundos_activos'] === $calculo['segundos_activos']
        && (int) $ejecucion['total_segundos_pausa'] === $calculo['segundos_pausa']
        && (string) ($ejecucion['resultado_cumplimiento'] ?? '') === $resultadoNuevo
        && !$requiereSincronizarIncumplimiento
    ) {
        tej_abortar(
            $conexion,
            'Los valores capturados ya coinciden con el registro actual; no hay cambios que auditar.',
            409
        );
    }

    $anteriores = [
        'fecha_hora_inicio' => $ejecucion['fecha_hora_inicio'],
        'fecha_hora_fin' => $ejecucion['fecha_hora_fin'],
        'total_segundos_activos' => (int) $ejecucion['total_segundos_activos'],
        'total_segundos_pausa' => (int) $ejecucion['total_segundos_pausa'],
        'resultado_cumplimiento' => $ejecucion['resultado_cumplimiento'],
        'fecha_resultado' => $ejecucion['fecha_resultado'],
        'editado_por_admin_id' => $ejecucion['editado_por_admin_id'],
        'motivo_edicion_tiempos' => $ejecucion['motivo_edicion_tiempos'],
        'incumplimiento_relacionado' => $incumplimientoAnterior,
    ];

    $stmt = $conexion->prepare(
        "UPDATE ejecuciones_mantenimiento
         SET fecha_hora_inicio_original = COALESCE(
                fecha_hora_inicio_original,
                fecha_hora_inicio
             ),
             fecha_hora_fin_original = COALESCE(
                fecha_hora_fin_original,
                fecha_hora_fin
             ),
             fecha_hora_inicio = :inicio,
             fecha_hora_fin = :fin,
             fecha_ultima_reanudacion = NULL,
             total_segundos_pausa = :segundos_pausa,
             total_segundos_activos = :segundos_activos,
             editado_por_admin_id = :admin_id,
             motivo_edicion_tiempos = :motivo,
             fecha_actualizacion = NOW()
         WHERE id = :id
           AND estado = 'TERMINADA'"
    );
    $stmt->bindValue(':inicio', $inicioNuevo, PDO::PARAM_STR);
    $stmt->bindValue(':fin', $finNuevo, PDO::PARAM_STR);
    $stmt->bindValue(':segundos_pausa', $calculo['segundos_pausa'], PDO::PARAM_INT);
    $stmt->bindValue(':segundos_activos', $calculo['segundos_activos'], PDO::PARAM_INT);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':motivo', $motivo, PDO::PARAM_STR);
    $stmt->bindValue(':id', $ejecucionId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        tej_abortar(
            $conexion,
            'La ejecución cambió mientras realizabas la corrección. Actualiza la pantalla.',
            409
        );
    }

    /*
     * El cumplimiento pertenece a la participación del técnico. Si la fecha
     * corregida cruza la fecha límite, también debe actualizarse para que los
     * tableros y reportes no contradigan el tiempo real auditado.
     */
    $stmtResultado = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET resultado_cumplimiento = :resultado,
             fecha_resultado = :fecha_resultado,
             fecha_actualizacion = NOW()
         WHERE id = :id
           AND solicitud_id = :solicitud_id
           AND tecnico_id = :tecnico_id"
    );
    $stmtResultado->bindValue(':resultado', $resultadoNuevo, PDO::PARAM_STR);
    $stmtResultado->bindValue(':fecha_resultado', $finNuevo, PDO::PARAM_STR);
    $stmtResultado->bindValue(
        ':id',
        (int) $ejecucion['solicitud_tecnico_id'],
        PDO::PARAM_INT
    );
    $stmtResultado->bindValue(
        ':solicitud_id',
        (int) $ejecucion['solicitud_id'],
        PDO::PARAM_INT
    );
    $stmtResultado->bindValue(
        ':tecnico_id',
        (int) $ejecucion['tecnico_id'],
        PDO::PARAM_INT
    );
    $stmtResultado->execute();

    if ($stmtResultado->rowCount() > 1) {
        tej_abortar(
            $conexion,
            'La participación del técnico presenta una inconsistencia y no pudo actualizarse de forma segura.',
            409
        );
    }

    $gestionIncumplimiento = tej_sincronizar_incumplimiento_correccion(
        $conexion,
        $ejecucion,
        $resultadoNuevo,
        $finNuevo,
        $incumplimientoAnterior
    );

    $nuevos = [
        'fecha_hora_inicio' => $inicioNuevo,
        'fecha_hora_fin' => $finNuevo,
        'total_segundos_activos' => $calculo['segundos_activos'],
        'total_segundos_pausa' => $calculo['segundos_pausa'],
        'resultado_cumplimiento' => $resultadoNuevo,
        'fecha_resultado' => $finNuevo,
        'editado_por_admin_id' => $adminId,
        'motivo_edicion_tiempos' => $motivo,
        'restaurado_a_original' => $restaurar,
        'gestion_incumplimiento' => $gestionIncumplimiento,
    ];

    tej_auditoria(
        $conexion,
        $adminId,
        $ejecucionId,
        (int) $ejecucion['solicitud_id'],
        $motivo,
        $anteriores,
        $nuevos
    );

    $descripcion = ($restaurar
        ? 'Se restauraron los tiempos originales'
        : 'Se corrigieron los tiempos')
        . ' de la ejecución del técnico '
        . (string) $ejecucion['tecnico']
        . ' en la solicitud '
        . (string) $ejecucion['folio']
        . '. Inicio: ' . $inicioNuevo
        . '. Fin: ' . $finNuevo
        . '. Cumplimiento recalculado: '
        . tej_cumplimiento_texto($resultadoNuevo)
        . '. ' . tej_descripcion_gestion_incumplimiento($gestionIncumplimiento)
        . ' Motivo: ' . $motivo;

    tej_historial(
        $conexion,
        (int) $ejecucion['solicitud_id'],
        (int) $ejecucion['solicitud_tecnico_id'],
        $ejecucion['programacion_id'] !== null
            ? (int) $ejecucion['programacion_id']
            : null,
        $adminId,
        $descripcion
    );

    tej_movimiento(
        $conexion,
        $adminId,
        $restaurar ? 'RESTAURAR_TIEMPOS_EJECUCION' : 'CORREGIR_TIEMPOS_EJECUCION',
        $descripcion,
        $ejecucionId
    );

    $conexion->commit();

    $mensajeRespuesta = $restaurar
        ? 'Los tiempos originales fueron restaurados correctamente.'
        : 'Los tiempos fueron corregidos correctamente.';

    $accionIncumplimiento = (string) ($gestionIncumplimiento['accion'] ?? '');
    if ($accionIncumplimiento === 'ELIMINADO_POR_CUMPLIMIENTO') {
        $mensajeRespuesta .= ' El incumplimiento incorrecto fue retirado.';
    } elseif (in_array(
        $accionIncumplimiento,
        ['ACTUALIZADO_A_CUMPLIDO_TARDE', 'CREADO_CUMPLIDO_TARDE'],
        true
    )) {
        $mensajeRespuesta .= ' El cumplimiento quedó conciliado como tardío.';
    } elseif ($accionIncumplimiento === 'JUSTIFICACION_CONSERVADA') {
        $mensajeRespuesta .= ' La justificación existente se conservó.';
    }

    sm_responder_json(
        true,
        $mensajeRespuesta,
        [
            'ejecucion_id' => $ejecucionId,
            'fecha_hora_inicio' => $inicioNuevo,
            'fecha_hora_fin' => $finNuevo,
            'total_segundos_activos' => $calculo['segundos_activos'],
            'total_segundos_pausa' => $calculo['segundos_pausa'],
            'resultado_cumplimiento' => $resultadoNuevo,
            'gestion_incumplimiento' => $gestionIncumplimiento,
        ]
    );
}

/* =========================================================================
   LISTADO Y RESUMEN
   ========================================================================= */

function tej_leer_filtros(bool $exportar = false): array
{
    $hoy = date('Y-m-d');
    $primerDia = date('Y-m-01');

    $busqueda = tej_texto($_GET['busqueda'] ?? '');
    if (tej_longitud($busqueda) > 120) {
        $busqueda = tej_recortar($busqueda, 120);
    }

    $estado = strtoupper(tej_texto($_GET['estado'] ?? 'TODOS'));
    $estados = ['TODOS','PENDIENTE','EN_PROCESO','PAUSADA','TERMINADA','CANCELADA'];
    if (!in_array($estado, $estados, true)) {
        $estado = 'TODOS';
    }

    $tipo = strtoupper(tej_texto($_GET['tipo'] ?? 'TODOS'));
    $tipos = [
        'TODOS',
        'CORRECTIVO_PROGRAMABLE',
        'MODIFICACION_MEJORA',
        'CORRECTIVO_URGENTE',
        'RUTINARIO',
    ];
    if (!in_array($tipo, $tipos, true)) {
        $tipo = 'TODOS';
    }

    $edicion = strtoupper(tej_texto($_GET['edicion'] ?? 'TODOS'));
    if (!in_array($edicion, ['TODOS','ORIGINAL','CORREGIDA'], true)) {
        $edicion = 'TODOS';
    }

    $revision = strtoupper(tej_texto($_GET['revision'] ?? 'TODOS'));
    if (!in_array($revision, ['TODOS','CON_ALERTAS','SIN_ALERTAS'], true)) {
        $revision = 'TODOS';
    }

    $tecnicoId = filter_var($_GET['tecnico_id'] ?? null, FILTER_VALIDATE_INT);
    $tecnicoId = $tecnicoId !== false && (int) $tecnicoId > 0
        ? (int) $tecnicoId
        : 0;

    $desdeEntrada = tej_texto($_GET['desde'] ?? $primerDia);
    $hastaEntrada = tej_texto($_GET['hasta'] ?? $hoy);
    $desde = tej_fecha_valida($desdeEntrada) ? $desdeEntrada : $primerDia;
    $hasta = tej_fecha_valida($hastaEntrada) ? $hastaEntrada : $hoy;

    if ($desde > $hasta) {
        $temporal = $desde;
        $desde = $hasta;
        $hasta = $temporal;
    }

    $orden = strtoupper(tej_texto($_GET['orden'] ?? 'RECIENTES'));
    if (!in_array(
        $orden,
        ['RECIENTES','ANTIGUAS','MAYOR_ACTIVO','MAYOR_PAUSA','TECNICO','FOLIO'],
        true
    )) {
        $orden = 'RECIENTES';
    }

    $pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
    $pagina = $pagina !== false && (int) $pagina > 0 ? (int) $pagina : 1;

    $porPagina = filter_var($_GET['por_pagina'] ?? 24, FILTER_VALIDATE_INT);
    $permitidos = [12, 24, 48];
    $porPagina = in_array((int) $porPagina, $permitidos, true)
        ? (int) $porPagina
        : 24;

    if ($exportar) {
        $pagina = 1;
        $porPagina = 5000;
    }

    return [
        'busqueda' => $busqueda,
        'estado' => $estado,
        'tipo' => $tipo,
        'edicion' => $edicion,
        'revision' => $revision,
        'tecnico_id' => $tecnicoId,
        'desde' => $desde,
        'hasta' => $hasta,
        'orden' => $orden,
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
    ];
}

function tej_construir_condiciones(array $filtros): array
{
    $where = [
        's.activo = 1',
        'DATE(COALESCE(em.fecha_hora_inicio, em.fecha_registro)) BETWEEN :desde AND :hasta',
    ];
    $parametros = [
        ':desde' => $filtros['desde'],
        ':hasta' => $filtros['hasta'],
    ];

    if ((string) $filtros['estado'] !== 'TODOS') {
        $where[] = 'em.estado = :estado';
        $parametros[':estado'] = $filtros['estado'];
    }

    if ((string) $filtros['tipo'] !== 'TODOS') {
        $where[] = 's.tipo_solicitud = :tipo';
        $parametros[':tipo'] = $filtros['tipo'];
    }

    if ((int) $filtros['tecnico_id'] > 0) {
        $where[] = 'em.tecnico_id = :tecnico_id';
        $parametros[':tecnico_id'] = (int) $filtros['tecnico_id'];
    }

    if ((string) $filtros['edicion'] === 'ORIGINAL') {
        $where[] = 'em.editado_por_admin_id IS NULL';
    } elseif ((string) $filtros['edicion'] === 'CORREGIDA') {
        $where[] = 'em.editado_por_admin_id IS NOT NULL';
    }

    if ((string) $filtros['revision'] === 'CON_ALERTAS') {
        $where[] = tej_sql_alertas() . ' = 1';
    } elseif ((string) $filtros['revision'] === 'SIN_ALERTAS') {
        $where[] = tej_sql_alertas() . ' = 0';
    }

    if ((string) $filtros['busqueda'] !== '') {
        /*
         * La conexión usa prepares nativos. Cada aparición necesita un nombre
         * distinto; reutilizar :busqueda produciría HY093 en MySQL/PDO.
         */
        $where[] = "(
            s.folio LIKE :busqueda_folio
            OR e.codigo_equipo LIKE :busqueda_codigo
            OR e.nombre_equipo LIKE :busqueda_equipo
            OR d.nombre LIKE :busqueda_departamento
            OR a.nombre LIKE :busqueda_area
            OR p.nombre LIKE :busqueda_proceso
            OR TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) LIKE :busqueda_tecnico
        )";

        $termino = '%' . $filtros['busqueda'] . '%';
        $parametros[':busqueda_folio'] = $termino;
        $parametros[':busqueda_codigo'] = $termino;
        $parametros[':busqueda_equipo'] = $termino;
        $parametros[':busqueda_departamento'] = $termino;
        $parametros[':busqueda_area'] = $termino;
        $parametros[':busqueda_proceso'] = $termino;
        $parametros[':busqueda_tecnico'] = $termino;
    }

    return [
        'where' => implode(' AND ', $where),
        'parametros' => $parametros,
    ];
}

function tej_sql_activos(): string
{
    return "(
        em.total_segundos_activos
        + CASE
            WHEN em.estado = 'EN_PROCESO' THEN GREATEST(
                0,
                TIMESTAMPDIFF(
                    SECOND,
                    COALESCE(em.fecha_ultima_reanudacion, em.fecha_hora_inicio, NOW()),
                    NOW()
                )
            )
            ELSE 0
          END
    )";
}

function tej_sql_pausa(): string
{
    return "(
        em.total_segundos_pausa
        + CASE
            WHEN em.estado = 'PAUSADA' THEN COALESCE((
                SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, pe.fecha_hora_inicio, NOW()))
                FROM pausas_ejecucion pe
                WHERE pe.ejecucion_id = em.id
                  AND pe.fecha_hora_fin IS NULL
                ORDER BY pe.id DESC
                LIMIT 1
            ), 0)
            ELSE 0
          END
    )";
}

function tej_sql_transcurridos(): string
{
    return "CASE
        WHEN em.fecha_hora_inicio IS NULL THEN 0
        WHEN em.fecha_hora_fin IS NOT NULL THEN GREATEST(
            0,
            TIMESTAMPDIFF(SECOND, em.fecha_hora_inicio, em.fecha_hora_fin)
        )
        ELSE GREATEST(
            0,
            TIMESTAMPDIFF(SECOND, em.fecha_hora_inicio, NOW())
        )
    END";
}

function tej_sql_alertas(): string
{
    return "CASE
        WHEN em.estado IN ('EN_PROCESO','PAUSADA') AND em.fecha_hora_inicio IS NULL THEN 1
        WHEN em.estado = 'TERMINADA' AND (em.fecha_hora_inicio IS NULL OR em.fecha_hora_fin IS NULL) THEN 1
        WHEN em.fecha_hora_inicio IS NOT NULL
             AND em.fecha_hora_fin IS NOT NULL
             AND em.fecha_hora_fin < em.fecha_hora_inicio THEN 1
        WHEN " . tej_sql_pausa() . " > " . tej_sql_transcurridos() . " THEN 1
        WHEN em.estado = 'TERMINADA'
             AND em.fecha_hora_inicio IS NOT NULL
             AND em.fecha_hora_fin IS NOT NULL
             AND ABS(
                (" . tej_sql_activos() . " + " . tej_sql_pausa() . ")
                - " . tej_sql_transcurridos() . "
             ) > 5 THEN 1
        WHEN em.estado = 'TERMINADA' AND EXISTS (
            SELECT 1
            FROM pausas_ejecucion pex
            WHERE pex.ejecucion_id = em.id
              AND pex.fecha_hora_fin IS NULL
        ) THEN 1
        ELSE 0
    END";
}

function tej_base_select(): string
{
    return "
        FROM ejecuciones_mantenimiento em
        INNER JOIN solicitudes s ON s.id = em.solicitud_id
        INNER JOIN solicitud_tecnicos st ON st.id = em.solicitud_tecnico_id
        INNER JOIN tecnicos t ON t.id = em.tecnico_id
        INNER JOIN equipos e ON e.id = s.equipo_id
        INNER JOIN departamentos d ON d.id = s.departamento_id
        INNER JOIN areas a ON a.id = s.area_id
        INNER JOIN procesos p ON p.id = s.proceso_id
        LEFT JOIN programaciones_mantenimiento pm ON pm.id = st.programacion_id
        LEFT JOIN cierres_mantenimiento cm ON cm.solicitud_id = s.id
        LEFT JOIN administradores ae ON ae.id = em.editado_por_admin_id
    ";
}

function tej_contar(PDO $conexion, string $where, array $parametros): int
{
    $sql = 'SELECT COUNT(*) ' . tej_base_select() . ' WHERE ' . $where;
    $stmt = $conexion->prepare($sql);
    tej_enlazar($stmt, $parametros);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function tej_obtener_registros(
    PDO $conexion,
    string $where,
    array $parametros,
    string $orden,
    int $limite,
    int $offset
): array {
    $ordenes = [
        'RECIENTES' => 'COALESCE(em.fecha_hora_inicio, em.fecha_registro) DESC, em.id DESC',
        'ANTIGUAS' => 'COALESCE(em.fecha_hora_inicio, em.fecha_registro) ASC, em.id ASC',
        'MAYOR_ACTIVO' => 'segundos_activos_actuales DESC, em.id DESC',
        'MAYOR_PAUSA' => 'segundos_pausa_actuales DESC, em.id DESC',
        'TECNICO' => 'tecnico ASC, COALESCE(em.fecha_hora_inicio, em.fecha_registro) DESC',
        'FOLIO' => 's.folio ASC, tecnico ASC',
    ];
    $orderBy = $ordenes[$orden] ?? $ordenes['RECIENTES'];

    $sql = "SELECT
            em.id,
            em.solicitud_id,
            em.solicitud_tecnico_id,
            em.tecnico_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.fecha_hora_fin,
            em.fecha_ultima_reanudacion,
            em.fecha_hora_inicio_original,
            em.fecha_hora_fin_original,
            em.total_segundos_activos,
            em.total_segundos_pausa,
            em.editado_por_admin_id,
            em.motivo_edicion_tiempos,
            em.fecha_registro,
            em.fecha_actualizacion,
            " . tej_sql_activos() . " AS segundos_activos_actuales,
            " . tej_sql_pausa() . " AS segundos_pausa_actuales,
            " . tej_sql_transcurridos() . " AS segundos_transcurridos,
            " . tej_sql_alertas() . " AS requiere_revision,
            COALESCE((
                SELECT COUNT(*)
                FROM pausas_ejecucion pec
                WHERE pec.ejecucion_id = em.id
            ), 0) AS total_pausas,
            COALESCE((
                SELECT COUNT(*)
                FROM pausas_ejecucion pea
                WHERE pea.ejecucion_id = em.id
                  AND pea.fecha_hora_fin IS NULL
            ), 0) AS pausas_abiertas,
            s.folio,
            s.tipo_solicitud,
            s.estado AS estado_solicitud,
            s.prioridad,
            s.fecha_solicitud,
            s.hora_solicitud,
            e.codigo_equipo,
            e.nombre_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
            t.turno,
            t.especialidad,
            t.activo AS tecnico_activo,
            st.origen,
            st.estado AS estado_participacion,
            st.resultado_cumplimiento,
            pm.id AS programacion_id,
            pm.fecha_programada,
            pm.fecha_limite,
            pm.estado AS estado_programacion,
            cm.id AS cierre_id,
            cm.fecha_hora_cierre,
            cm.trabajo_quedo,
            TRIM(CONCAT_WS(' ', ae.nombre, ae.apellido_paterno, ae.apellido_materno)) AS admin_editor
        " . tej_base_select() . "
        WHERE " . $where . "
        ORDER BY " . $orderBy . "
        LIMIT :limite OFFSET :offset";

    $stmt = $conexion->prepare($sql);
    tej_enlazar($stmt, $parametros);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $filas = $stmt->fetchAll();
    foreach ($filas as &$fila) {
        $fila = tej_preparar_registro($fila);
    }
    unset($fila);

    return $filas;
}

function tej_obtener_resumen(
    PDO $conexion,
    string $where,
    array $parametros
): array {
    $sql = "SELECT
            COUNT(*) AS total,
            SUM(em.estado = 'EN_PROCESO') AS en_proceso,
            SUM(em.estado = 'PAUSADA') AS pausadas,
            SUM(em.estado = 'TERMINADA') AS terminadas,
            SUM(em.estado = 'CANCELADA') AS canceladas,
            SUM(em.editado_por_admin_id IS NOT NULL) AS corregidas,
            SUM(" . tej_sql_alertas() . ") AS con_alertas,
            COALESCE(SUM(" . tej_sql_activos() . "), 0) AS segundos_activos,
            COALESCE(SUM(" . tej_sql_pausa() . "), 0) AS segundos_pausa,
            COALESCE(AVG(CASE
                WHEN " . tej_sql_transcurridos() . " > 0
                THEN (" . tej_sql_activos() . " / " . tej_sql_transcurridos() . ") * 100
                ELSE NULL
            END), 0) AS promedio_porcentaje_activo
        " . tej_base_select() . "
        WHERE " . $where;

    $stmt = $conexion->prepare($sql);
    tej_enlazar($stmt, $parametros);
    $stmt->execute();
    $fila = $stmt->fetch() ?: [];

    return [
        'total' => (int) ($fila['total'] ?? 0),
        'en_proceso' => (int) ($fila['en_proceso'] ?? 0),
        'pausadas' => (int) ($fila['pausadas'] ?? 0),
        'terminadas' => (int) ($fila['terminadas'] ?? 0),
        'canceladas' => (int) ($fila['canceladas'] ?? 0),
        'corregidas' => (int) ($fila['corregidas'] ?? 0),
        'con_alertas' => (int) ($fila['con_alertas'] ?? 0),
        'segundos_activos' => (int) ($fila['segundos_activos'] ?? 0),
        'segundos_pausa' => (int) ($fila['segundos_pausa'] ?? 0),
        'promedio_porcentaje_activo' => round(
            (float) ($fila['promedio_porcentaje_activo'] ?? 0),
            1
        ),
    ];
}

function tej_preparar_registro(array $fila): array
{
    $enteros = [
        'id','solicitud_id','solicitud_tecnico_id','tecnico_id',
        'total_segundos_activos','total_segundos_pausa',
        'segundos_activos_actuales','segundos_pausa_actuales',
        'segundos_transcurridos','requiere_revision','total_pausas',
        'pausas_abiertas','tecnico_activo',
    ];

    foreach ($enteros as $campo) {
        $fila[$campo] = (int) ($fila[$campo] ?? 0);
    }

    $fila['programacion_id'] = $fila['programacion_id'] !== null
        ? (int) $fila['programacion_id']
        : null;
    $fila['cierre_id'] = $fila['cierre_id'] !== null
        ? (int) $fila['cierre_id']
        : null;
    $fila['editado_por_admin_id'] = $fila['editado_por_admin_id'] !== null
        ? (int) $fila['editado_por_admin_id']
        : null;
    $fila['fue_editada'] = $fila['editado_por_admin_id'] !== null ? 1 : 0;

    $transcurridos = max(0, (int) $fila['segundos_transcurridos']);
    $activos = max(0, (int) $fila['segundos_activos_actuales']);
    $fila['porcentaje_activo'] = $transcurridos > 0
        ? round(min(100, ($activos / $transcurridos) * 100), 1)
        : null;

    $fila['alertas_revision'] = tej_detectar_alertas($fila);
    $fila['requiere_revision'] = $fila['alertas_revision'] !== [] ? 1 : 0;
    $fila['puede_editar'] = tej_puede_editar($fila);
    $fila['puede_restaurar'] = tej_puede_restaurar($fila);

    return $fila;
}

function tej_detectar_alertas(array $fila): array
{
    $alertas = [];
    $estado = (string) ($fila['estado_ejecucion'] ?? '');
    $inicio = (string) ($fila['fecha_hora_inicio'] ?? '');
    $fin = (string) ($fila['fecha_hora_fin'] ?? '');
    $transcurridos = (int) ($fila['segundos_transcurridos'] ?? 0);
    $pausa = (int) ($fila['segundos_pausa_actuales'] ?? 0);

    if (in_array($estado, ['EN_PROCESO','PAUSADA'], true) && $inicio === '') {
        $alertas[] = 'Ejecución activa sin fecha de inicio';
    }

    if ($estado === 'TERMINADA' && ($inicio === '' || $fin === '')) {
        $alertas[] = 'Ejecución terminada con fechas incompletas';
    }

    if ($inicio !== '' && $fin !== '' && strtotime($fin) < strtotime($inicio)) {
        $alertas[] = 'La finalización es anterior al inicio';
    }

    if ($pausa > $transcurridos) {
        $alertas[] = 'Las pausas superan el tiempo transcurrido';
    }

    if (
        $estado === 'TERMINADA'
        && $inicio !== ''
        && $fin !== ''
        && abs(
            ((int) ($fila['segundos_activos_actuales'] ?? 0) + $pausa)
            - $transcurridos
        ) > 5
    ) {
        $alertas[] = 'La suma de tiempo activo y pausas no coincide con el rango';
    }

    if ($estado === 'TERMINADA' && (int) ($fila['pausas_abiertas'] ?? 0) > 0) {
        $alertas[] = 'Conserva una pausa abierta';
    }

    if (
        $estado === 'TERMINADA'
        && (int) ($fila['segundos_activos_actuales'] ?? 0) === 0
        && $inicio !== ''
        && $fin !== ''
        && strtotime($fin) > strtotime($inicio)
    ) {
        $alertas[] = 'No tiene tiempo activo registrado';
    }

    return $alertas;
}

function tej_puede_editar(array $fila): bool
{
    /*
     * También se permite reparar una ejecución terminada con una de sus fechas
     * vacía. Precisamente esos registros requieren esta herramienta. El rango
     * nuevo seguirá validándose contra la solicitud, el cierre y las pausas.
     */
    return (string) ($fila['estado_ejecucion'] ?? '') === 'TERMINADA'
        && (string) ($fila['estado_solicitud'] ?? '') === 'TERMINADO'
        && !empty($fila['fecha_hora_cierre'])
        && (int) ($fila['pausas_abiertas'] ?? 0) === 0;
}

function tej_puede_restaurar(array $fila): bool
{
    if (!tej_puede_editar($fila)) {
        return false;
    }

    $inicioOriginal = (string) ($fila['fecha_hora_inicio_original'] ?? '');
    $finOriginal = (string) ($fila['fecha_hora_fin_original'] ?? '');

    if ($inicioOriginal === '' || $finOriginal === '') {
        return false;
    }

    return $inicioOriginal !== (string) ($fila['fecha_hora_inicio'] ?? '')
        || $finOriginal !== (string) ($fila['fecha_hora_fin'] ?? '');
}

/* =========================================================================
   DETALLE
   ========================================================================= */

function tej_obtener_detalle(PDO $conexion, int $ejecucionId, bool $bloquear): ?array
{
    $sql = "SELECT
            em.id,
            em.solicitud_id,
            em.solicitud_tecnico_id,
            em.tecnico_id,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.fecha_hora_fin,
            em.fecha_ultima_reanudacion,
            em.fecha_hora_inicio_original,
            em.fecha_hora_fin_original,
            em.total_segundos_activos,
            em.total_segundos_pausa,
            em.iniciada_por_tipo,
            em.iniciada_por_id,
            em.editado_por_admin_id,
            em.motivo_edicion_tiempos,
            em.fecha_registro,
            em.fecha_actualizacion,
            " . tej_sql_activos() . " AS segundos_activos_actuales,
            " . tej_sql_pausa() . " AS segundos_pausa_actuales,
            " . tej_sql_transcurridos() . " AS segundos_transcurridos,
            " . tej_sql_alertas() . " AS requiere_revision,
            COALESCE((
                SELECT COUNT(*) FROM pausas_ejecucion pec
                WHERE pec.ejecucion_id = em.id
            ), 0) AS total_pausas,
            COALESCE((
                SELECT COUNT(*) FROM pausas_ejecucion pea
                WHERE pea.ejecucion_id = em.id
                  AND pea.fecha_hora_fin IS NULL
            ), 0) AS pausas_abiertas,
            s.folio,
            s.tipo_solicitud,
            s.estado AS estado_solicitud,
            s.prioridad,
            s.fecha_solicitud,
            s.hora_solicitud,
            s.descripcion_solicitud,
            s.trabajo_peligroso,
            s.nivel_riesgo,
            s.requiere_paro_equipo,
            e.codigo_equipo,
            e.nombre_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
            t.turno,
            t.especialidad,
            t.activo AS tecnico_activo,
            st.origen,
            st.estado AS estado_participacion,
            st.resultado_cumplimiento,
            st.fecha_resultado,
            pm.id AS programacion_id,
            pm.fecha_programada,
            pm.fecha_limite,
            pm.estado AS estado_programacion,
            cm.id AS cierre_id,
            cm.fecha_hora_cierre,
            cm.trabajo_quedo,
            cm.descripcion_trabajo_realizado,
            cm.que_falto,
            cm.observaciones_cierre,
            TRIM(CONCAT_WS(' ', ae.nombre, ae.apellido_paterno, ae.apellido_materno)) AS admin_editor
        " . tej_base_select() . "
        WHERE em.id = :id
        LIMIT 1" . ($bloquear ? ' FOR UPDATE' : '');

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':id', $ejecucionId, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch();

    if (!$fila) {
        return null;
    }

    return tej_preparar_registro($fila);
}

function tej_obtener_pausas(PDO $conexion, int $ejecucionId, bool $bloquear): array
{
    $sql = "SELECT
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
            pe.pausa_abierta_token,
            pe.fecha_registro,
            su.folio AS folio_urgencia,
            CASE
                WHEN pe.creada_por_tipo = 'TECNICO' THEN TRIM(CONCAT_WS(' ', tc.nombre, tc.apellido_paterno, tc.apellido_materno))
                WHEN pe.creada_por_tipo = 'ADMIN' THEN TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno))
                ELSE 'Sistema'
            END AS creada_por
        FROM pausas_ejecucion pe
        LEFT JOIN solicitudes su ON su.id = pe.solicitud_urgente_id
        LEFT JOIN tecnicos tc
               ON pe.creada_por_tipo = 'TECNICO'
              AND tc.id = pe.creada_por_id
        LEFT JOIN administradores ad
               ON pe.creada_por_tipo = 'ADMIN'
              AND ad.id = pe.creada_por_id
        WHERE pe.ejecucion_id = :ejecucion_id
        ORDER BY pe.fecha_hora_inicio, pe.id" . ($bloquear ? ' FOR UPDATE' : '');

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':ejecucion_id', $ejecucionId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll();

    foreach ($filas as &$fila) {
        $fila['id'] = (int) $fila['id'];
        $fila['ejecucion_id'] = (int) $fila['ejecucion_id'];
        $fila['duracion_segundos'] = (int) $fila['duracion_segundos'];
        $fila['solicitud_urgente_id'] = $fila['solicitud_urgente_id'] !== null
            ? (int) $fila['solicitud_urgente_id']
            : null;
        $fila['abierta'] = empty($fila['fecha_hora_fin']) ? 1 : 0;
        $fila['duracion_actual'] = $fila['abierta'] === 1
            ? max(0, time() - strtotime((string) $fila['fecha_hora_inicio']))
            : max(
                0,
                (int) $fila['duracion_segundos'] > 0
                    ? (int) $fila['duracion_segundos']
                    : strtotime((string) $fila['fecha_hora_fin'])
                        - strtotime((string) $fila['fecha_hora_inicio'])
            );
    }
    unset($fila);

    return $filas;
}

function tej_obtener_auditoria(PDO $conexion, int $ejecucionId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            au.id,
            au.actor_tipo,
            au.actor_id,
            au.accion,
            au.motivo,
            au.datos_anteriores,
            au.datos_nuevos,
            au.fecha_evento,
            TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno)) AS actor
         FROM auditoria_ediciones au
         LEFT JOIN administradores ad
                ON au.actor_tipo = 'ADMIN'
               AND ad.id = au.actor_id
         WHERE au.tabla_afectada = 'ejecuciones_mantenimiento'
           AND au.registro_id = :id
         ORDER BY au.fecha_evento DESC, au.id DESC
         LIMIT 100"
    );
    $stmt->bindValue(':id', $ejecucionId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll();

    foreach ($filas as &$fila) {
        $fila['id'] = (int) $fila['id'];
        $fila['actor_id'] = $fila['actor_id'] !== null ? (int) $fila['actor_id'] : null;
        $fila['datos_anteriores_obj'] = tej_json_decodificar($fila['datos_anteriores']);
        $fila['datos_nuevos_obj'] = tej_json_decodificar($fila['datos_nuevos']);
        unset($fila['datos_anteriores'], $fila['datos_nuevos']);
    }
    unset($fila);

    return $filas;
}

function tej_obtener_historial_relacionado(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            hs.id,
            hs.evento,
            hs.estado_anterior,
            hs.estado_nuevo,
            hs.actor_tipo,
            hs.actor_id,
            hs.descripcion,
            hs.fecha_evento,
            CASE
                WHEN hs.actor_tipo = 'ADMIN' THEN TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno))
                WHEN hs.actor_tipo = 'TECNICO' THEN TRIM(CONCAT_WS(' ', te.nombre, te.apellido_paterno, te.apellido_materno))
                WHEN hs.actor_tipo = 'SOLICITANTE' THEN TRIM(CONCAT_WS(' ', so.nombre, so.apellido_paterno, so.apellido_materno))
                ELSE 'Sistema'
            END AS actor
         FROM historial_solicitudes hs
         LEFT JOIN administradores ad
                ON hs.actor_tipo = 'ADMIN' AND ad.id = hs.actor_id
         LEFT JOIN tecnicos te
                ON hs.actor_tipo = 'TECNICO' AND te.id = hs.actor_id
         LEFT JOIN solicitantes so
                ON hs.actor_tipo = 'SOLICITANTE' AND so.id = hs.actor_id
         WHERE hs.solicitud_id = :solicitud_id
           AND hs.evento IN ('INICIADA','PAUSADA','REANUDADA','TERMINADA','EDITADA','OTRO')
         ORDER BY hs.fecha_evento DESC, hs.id DESC
         LIMIT 40"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll();

    foreach ($filas as &$fila) {
        $fila['id'] = (int) $fila['id'];
        $fila['actor_id'] = $fila['actor_id'] !== null ? (int) $fila['actor_id'] : null;
    }
    unset($fila);

    return $filas;
}

function tej_preparar_detalle(array $detalle, array $pausas): array
{
    $detalle['pausas_total_calculado'] = 0;
    foreach ($pausas as $pausa) {
        $detalle['pausas_total_calculado'] += (int) $pausa['duracion_actual'];
    }

    $detalle['limite_inicio'] = date(
        'Y-m-d H:i:s',
        strtotime((string) $detalle['fecha_solicitud'] . ' ' . (string) $detalle['hora_solicitud'])
    );
    $detalle['limite_fin'] = (string) $detalle['fecha_hora_cierre'];

    return $detalle;
}

/* =========================================================================
   VALIDACIÓN Y CÁLCULO DE CORRECCIONES
   ========================================================================= */

function tej_validar_rango_correccion(
    PDO $conexion,
    array $ejecucion,
    array $pausas,
    string $inicio,
    string $fin
): void {
    $inicioTs = strtotime($inicio);
    $finTs = strtotime($fin);

    if ($inicioTs === false || $finTs === false || $finTs <= $inicioTs) {
        tej_abortar(
            $conexion,
            'La fecha de finalización debe ser posterior a la fecha de inicio.',
            422,
            ['campo' => 'fecha_hora_fin']
        );
    }

    if ($finTs > time()) {
        tej_abortar(
            $conexion,
            'La fecha de finalización no puede estar en el futuro.',
            422,
            ['campo' => 'fecha_hora_fin']
        );
    }

    $solicitudTs = strtotime(
        (string) $ejecucion['fecha_solicitud']
        . ' '
        . (string) $ejecucion['hora_solicitud']
    );

    if ($solicitudTs !== false && $inicioTs < $solicitudTs) {
        tej_abortar(
            $conexion,
            'El inicio no puede ser anterior al registro de la solicitud.',
            422,
            ['campo' => 'fecha_hora_inicio']
        );
    }

    $cierreTs = strtotime((string) $ejecucion['fecha_hora_cierre']);
    if ($cierreTs !== false && $finTs > $cierreTs) {
        tej_abortar(
            $conexion,
            'La finalización del técnico no puede ser posterior al cierre general del mantenimiento.',
            422,
            ['campo' => 'fecha_hora_fin']
        );
    }

    $finPausaAnterior = null;
    foreach ($pausas as $pausa) {
        $pausaInicio = strtotime((string) $pausa['fecha_hora_inicio']);
        $pausaFin = strtotime((string) $pausa['fecha_hora_fin']);

        if ($pausaInicio === false || $pausaFin === false || $pausaFin < $pausaInicio) {
            tej_abortar(
                $conexion,
                'Existe una pausa con fechas inconsistentes. Corrige primero ese registro desde la base de datos con respaldo.',
                409
            );
        }

        if ($pausaInicio < $inicioTs || $pausaFin > $finTs) {
            tej_abortar(
                $conexion,
                'El nuevo rango debe contener todas las pausas registradas. Revisa las fechas de inicio y fin.',
                422
            );
        }

        if ($finPausaAnterior !== null && $pausaInicio < $finPausaAnterior) {
            tej_abortar(
                $conexion,
                'La ejecución contiene pausas superpuestas. No es seguro recalcularla automáticamente.',
                409
            );
        }

        $finPausaAnterior = $pausaFin;
    }
}

function tej_calcular_tiempos_rango(
    string $inicio,
    string $fin,
    array $pausas
): array {
    $segundosTranscurridos = max(0, strtotime($fin) - strtotime($inicio));
    $segundosPausa = 0;

    foreach ($pausas as $pausa) {
        $inicioPausa = strtotime((string) $pausa['fecha_hora_inicio']);
        $finPausa = strtotime((string) $pausa['fecha_hora_fin']);
        $segundosPausa += max(0, $finPausa - $inicioPausa);
    }

    if ($segundosPausa > $segundosTranscurridos) {
        throw new RuntimeException(
            'Las pausas superan el tiempo total de la ejecución.'
        );
    }

    return [
        'segundos_transcurridos' => $segundosTranscurridos,
        'segundos_pausa' => $segundosPausa,
        'segundos_activos' => max(0, $segundosTranscurridos - $segundosPausa),
    ];
}

function tej_resultado_cumplimiento_corregido(
    array $ejecucion,
    string $fin
): string {
    if ((string) ($ejecucion['tipo_solicitud'] ?? '') === 'CORRECTIVO_URGENTE') {
        return 'NO_APLICA';
    }

    $fechaLimite = (string) ($ejecucion['fecha_limite'] ?? '');
    if (!tej_fecha_valida($fechaLimite)) {
        $actual = (string) ($ejecucion['resultado_cumplimiento'] ?? 'PENDIENTE');
        return in_array(
            $actual,
            ['PENDIENTE','A_TIEMPO','TARDE','NO_REALIZADO','NO_APLICA'],
            true
        ) ? $actual : 'PENDIENTE';
    }

    return substr($fin, 0, 10) <= $fechaLimite ? 'A_TIEMPO' : 'TARDE';
}

/**
 * Obtiene y bloquea el incumplimiento asociado a la participación que se
 * está corrigiendo. Las urgencias no tienen programación ni incumplimiento.
 *
 * @return array<string,mixed>|null
 */
function tej_obtener_incumplimiento_correccion(
    PDO $conexion,
    int $solicitudTecnicoId,
    ?int $programacionId
): ?array {
    if ($programacionId === null) {
        return null;
    }

    $stmt = $conexion->prepare(
        "SELECT
            im.id,
            im.solicitud_id,
            im.programacion_id,
            im.solicitud_tecnico_id,
            im.fecha_programada,
            im.fecha_detectado,
            im.estado,
            im.justificacion,
            im.justificado_por_admin_id,
            im.fecha_resolucion
         FROM incumplimientos_mantenimiento im
         WHERE im.solicitud_tecnico_id = :solicitud_tecnico_id
           AND im.programacion_id = :programacion_id
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bindValue(
        ':solicitud_tecnico_id',
        $solicitudTecnicoId,
        PDO::PARAM_INT
    );
    $stmt->bindValue(':programacion_id', $programacionId, PDO::PARAM_INT);
    $stmt->execute();

    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fila) {
        return null;
    }

    $fila['id'] = (int) $fila['id'];
    $fila['solicitud_id'] = (int) $fila['solicitud_id'];
    $fila['programacion_id'] = (int) $fila['programacion_id'];
    $fila['solicitud_tecnico_id'] = (int) $fila['solicitud_tecnico_id'];
    $fila['justificado_por_admin_id'] =
        $fila['justificado_por_admin_id'] !== null
            ? (int) $fila['justificado_por_admin_id']
            : null;

    return $fila;
}

/**
 * Determina si existe una contradicción entre el cumplimiento recalculado y
 * el registro vigente de incumplimientos.
 */
function tej_requiere_sincronizar_incumplimiento(
    array $ejecucion,
    string $resultadoNuevo,
    ?array $incumplimiento
): bool {
    if (
        (string) ($ejecucion['tipo_solicitud'] ?? '') === 'CORRECTIVO_URGENTE'
        || ($ejecucion['programacion_id'] ?? null) === null
    ) {
        return false;
    }

    if ($resultadoNuevo === 'A_TIEMPO') {
        return $incumplimiento !== null;
    }

    if ($resultadoNuevo !== 'TARDE') {
        return false;
    }

    if ($incumplimiento === null) {
        return true;
    }

    $estado = (string) ($incumplimiento['estado'] ?? '');

    /*
     * Una llegada tarde puede estar justificada. Esa resolución se conserva;
     * no representa una contradicción con resultado_cumplimiento = TARDE.
     */
    if ($estado === 'JUSTIFICADO') {
        return false;
    }

    return $estado !== 'CUMPLIDO_TARDE';
}

/**
 * Mantiene sincronizados solicitud_tecnicos e incumplimientos_mantenimiento.
 *
 * - A_TIEMPO: elimina el incumplimiento incorrecto.
 * - TARDE: crea o concilia como CUMPLIDO_TARDE.
 * - JUSTIFICADO + TARDE: conserva la justificación existente.
 *
 * Los datos anteriores se incluyen después dentro de auditoria_ediciones.
 *
 * @return array<string,mixed>
 */
function tej_sincronizar_incumplimiento_correccion(
    PDO $conexion,
    array $ejecucion,
    string $resultadoNuevo,
    string $finNuevo,
    ?array $incumplimientoAnterior
): array {
    $sinCambio = [
        'accion' => 'NO_APLICA',
        'incumplimiento_id' => null,
        'estado_anterior' => $incumplimientoAnterior['estado'] ?? null,
        'estado_nuevo' => $incumplimientoAnterior['estado'] ?? null,
    ];

    if (
        (string) ($ejecucion['tipo_solicitud'] ?? '') === 'CORRECTIVO_URGENTE'
        || ($ejecucion['programacion_id'] ?? null) === null
    ) {
        return $sinCambio;
    }

    if ($resultadoNuevo === 'A_TIEMPO') {
        if ($incumplimientoAnterior === null) {
            return [
                'accion' => 'SIN_REGISTRO',
                'incumplimiento_id' => null,
                'estado_anterior' => null,
                'estado_nuevo' => null,
            ];
        }

        $stmt = $conexion->prepare(
            "DELETE FROM incumplimientos_mantenimiento
             WHERE id = :id
               AND solicitud_tecnico_id = :solicitud_tecnico_id
               AND programacion_id = :programacion_id"
        );
        $stmt->bindValue(
            ':id',
            (int) $incumplimientoAnterior['id'],
            PDO::PARAM_INT
        );
        $stmt->bindValue(
            ':solicitud_tecnico_id',
            (int) $ejecucion['solicitud_tecnico_id'],
            PDO::PARAM_INT
        );
        $stmt->bindValue(
            ':programacion_id',
            (int) $ejecucion['programacion_id'],
            PDO::PARAM_INT
        );
        $stmt->execute();

        if ($stmt->rowCount() !== 1) {
            tej_abortar(
                $conexion,
                'El incumplimiento relacionado cambió durante la corrección. Actualiza la pantalla e inténtalo nuevamente.',
                409
            );
        }

        return [
            'accion' => 'ELIMINADO_POR_CUMPLIMIENTO',
            'incumplimiento_id' => (int) $incumplimientoAnterior['id'],
            'estado_anterior' => (string) $incumplimientoAnterior['estado'],
            'estado_nuevo' => null,
            'justificacion_conservada_en_auditoria' =>
                (string) ($incumplimientoAnterior['justificacion'] ?? ''),
        ];
    }

    if ($resultadoNuevo !== 'TARDE') {
        return $sinCambio;
    }

    if (
        $incumplimientoAnterior !== null
        && (string) $incumplimientoAnterior['estado'] === 'JUSTIFICADO'
    ) {
        return [
            'accion' => 'JUSTIFICACION_CONSERVADA',
            'incumplimiento_id' => (int) $incumplimientoAnterior['id'],
            'estado_anterior' => 'JUSTIFICADO',
            'estado_nuevo' => 'JUSTIFICADO',
            'justificacion' =>
                (string) ($incumplimientoAnterior['justificacion'] ?? ''),
        ];
    }

    if ($incumplimientoAnterior !== null) {
        $stmt = $conexion->prepare(
            "UPDATE incumplimientos_mantenimiento
             SET estado = 'CUMPLIDO_TARDE',
                 justificacion = NULL,
                 justificado_por_admin_id = NULL,
                 fecha_resolucion = :fecha_resolucion
             WHERE id = :id
               AND solicitud_tecnico_id = :solicitud_tecnico_id
               AND programacion_id = :programacion_id"
        );
        $stmt->bindValue(':fecha_resolucion', $finNuevo, PDO::PARAM_STR);
        $stmt->bindValue(
            ':id',
            (int) $incumplimientoAnterior['id'],
            PDO::PARAM_INT
        );
        $stmt->bindValue(
            ':solicitud_tecnico_id',
            (int) $ejecucion['solicitud_tecnico_id'],
            PDO::PARAM_INT
        );
        $stmt->bindValue(
            ':programacion_id',
            (int) $ejecucion['programacion_id'],
            PDO::PARAM_INT
        );
        $stmt->execute();

        return [
            'accion' => 'ACTUALIZADO_A_CUMPLIDO_TARDE',
            'incumplimiento_id' => (int) $incumplimientoAnterior['id'],
            'estado_anterior' => (string) $incumplimientoAnterior['estado'],
            'estado_nuevo' => 'CUMPLIDO_TARDE',
        ];
    }

    $fechaProgramada = (string) ($ejecucion['fecha_programada'] ?? '');
    if (!tej_fecha_valida($fechaProgramada)) {
        $fechaProgramada = (string) ($ejecucion['fecha_limite'] ?? '');
    }

    if (!tej_fecha_valida($fechaProgramada)) {
        tej_abortar(
            $conexion,
            'No fue posible conciliar el incumplimiento porque la programación no contiene una fecha válida.',
            409
        );
    }

    $stmt = $conexion->prepare(
        "INSERT INTO incumplimientos_mantenimiento
        (
            solicitud_id,
            programacion_id,
            solicitud_tecnico_id,
            fecha_programada,
            fecha_detectado,
            estado,
            fecha_resolucion
        )
        VALUES
        (
            :solicitud_id,
            :programacion_id,
            :solicitud_tecnico_id,
            :fecha_programada,
            NOW(),
            'CUMPLIDO_TARDE',
            :fecha_resolucion
        )"
    );
    $stmt->bindValue(
        ':solicitud_id',
        (int) $ejecucion['solicitud_id'],
        PDO::PARAM_INT
    );
    $stmt->bindValue(
        ':programacion_id',
        (int) $ejecucion['programacion_id'],
        PDO::PARAM_INT
    );
    $stmt->bindValue(
        ':solicitud_tecnico_id',
        (int) $ejecucion['solicitud_tecnico_id'],
        PDO::PARAM_INT
    );
    $stmt->bindValue(':fecha_programada', $fechaProgramada, PDO::PARAM_STR);
    $stmt->bindValue(':fecha_resolucion', $finNuevo, PDO::PARAM_STR);
    $stmt->execute();

    return [
        'accion' => 'CREADO_CUMPLIDO_TARDE',
        'incumplimiento_id' => (int) $conexion->lastInsertId(),
        'estado_anterior' => null,
        'estado_nuevo' => 'CUMPLIDO_TARDE',
    ];
}

/**
 * Devuelve una explicación breve para historial y movimientos.
 */
function tej_descripcion_gestion_incumplimiento(array $gestion): string
{
    $accion = (string) ($gestion['accion'] ?? 'NO_APLICA');

    $mapa = [
        'ELIMINADO_POR_CUMPLIMIENTO' =>
            'Se retiró el incumplimiento relacionado porque la fecha corregida demuestra cumplimiento dentro del plazo.',
        'ACTUALIZADO_A_CUMPLIDO_TARDE' =>
            'El incumplimiento relacionado fue conciliado como cumplido tarde.',
        'CREADO_CUMPLIDO_TARDE' =>
            'Se creó el incumplimiento correspondiente como cumplido tarde.',
        'JUSTIFICACION_CONSERVADA' =>
            'La participación continúa fuera de plazo y se conservó la justificación administrativa existente.',
        'SIN_REGISTRO' =>
            'No existía un incumplimiento que retirar.',
        'NO_APLICA' =>
            'No aplica gestión de incumplimiento para esta ejecución.',
    ];

    return $mapa[$accion] ?? 'El cumplimiento relacionado fue sincronizado.';
}


/* =========================================================================
   AUDITORÍA
   ========================================================================= */

function tej_auditoria(
    PDO $conexion,
    int $adminId,
    int $ejecucionId,
    int $solicitudId,
    string $motivo,
    array $anteriores,
    array $nuevos
): void {
    $stmt = $conexion->prepare(
        "INSERT INTO auditoria_ediciones
        (
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
        )
        VALUES
        (
            'ejecuciones_mantenimiento',
            :registro_id,
            :solicitud_id,
            'ADMIN',
            :actor_id,
            'CORRECCION_TIEMPOS',
            :motivo,
            :anteriores,
            :nuevos,
            :ip,
            :user_agent,
            NOW()
        )"
    );
    $stmt->bindValue(':registro_id', $ejecucionId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':motivo', $motivo, PDO::PARAM_STR);
    $stmt->bindValue(':anteriores', tej_json_codificar($anteriores), PDO::PARAM_STR);
    $stmt->bindValue(':nuevos', tej_json_codificar($nuevos), PDO::PARAM_STR);
    tej_bind_nullable($stmt, ':ip', tej_ip());
    tej_bind_nullable($stmt, ':user_agent', tej_recortar((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 500));
    $stmt->execute();
}

function tej_historial(
    PDO $conexion,
    int $solicitudId,
    int $solicitudTecnicoId,
    ?int $programacionId,
    int $adminId,
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
            'EDITADA',
            'TERMINADO',
            'TERMINADO',
            'ADMIN',
            :actor_id,
            :descripcion,
            NOW()
        )"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_tecnico_id', $solicitudTecnicoId, PDO::PARAM_INT);
    if ($programacionId === null) {
        $stmt->bindValue(':programacion_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':programacion_id', $programacionId, PDO::PARAM_INT);
    }
    $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->execute();
}

function tej_movimiento(
    PDO $conexion,
    int $adminId,
    string $accion,
    string $descripcion,
    int $ejecucionId
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
            'Tiempos reales',
            :descripcion,
            'ejecuciones_mantenimiento',
            :registro_id,
            :ip,
            :user_agent,
            NOW()
        )"
    );
    $stmt->bindValue(':usuario_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':registro_id', $ejecucionId, PDO::PARAM_INT);
    tej_bind_nullable($stmt, ':ip', tej_ip());
    tej_bind_nullable($stmt, ':user_agent', tej_recortar((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 255));
    $stmt->execute();
}

/* =========================================================================
   CATÁLOGOS Y UTILIDADES
   ========================================================================= */

function tej_catalogos(PDO $conexion): array
{
    $tecnicos = $conexion->query(
        "SELECT DISTINCT
            t.id,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS nombre,
            t.activo
         FROM tecnicos t
         INNER JOIN ejecuciones_mantenimiento em ON em.tecnico_id = t.id
         ORDER BY t.activo DESC, nombre"
    )->fetchAll();

    foreach ($tecnicos as &$tecnico) {
        $tecnico['id'] = (int) $tecnico['id'];
        $tecnico['activo'] = (int) $tecnico['activo'];
    }
    unset($tecnico);

    return ['tecnicos' => $tecnicos];
}

function tej_validar_admin_activo(PDO $conexion, int $adminId): void
{
    $stmt = $conexion->prepare(
        "SELECT COUNT(*)
         FROM administradores
         WHERE id = :id
           AND activo = 1"
    );
    $stmt->bindValue(':id', $adminId, PDO::PARAM_INT);
    $stmt->execute();

    if ((int) $stmt->fetchColumn() !== 1) {
        sm_responder_json(
            false,
            'Tu cuenta administrativa ya no está activa.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?sesion=inactiva',
            ],
            401
        );
    }
}

function tej_admin_id(): int
{
    return tej_entero_positivo($_SESSION['usuario_id'] ?? null, 'administrador');
}

function tej_entero_positivo($valor, string $campo): int
{
    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($entero === false) {
        sm_responder_json(
            false,
            'El campo ' . $campo . ' no es válido.',
            ['campo' => $campo],
            422
        );
    }

    return (int) $entero;
}

function tej_fecha_hora_entrada($valor, string $campo): string
{
    $texto = tej_texto($valor);
    $formatos = ['!Y-m-d\TH:i', '!Y-m-d\TH:i:s', '!Y-m-d H:i:s'];

    foreach ($formatos as $formato) {
        $fecha = DateTimeImmutable::createFromFormat($formato, $texto);
        $errores = DateTimeImmutable::getLastErrors();

        if (
            $fecha instanceof DateTimeImmutable
            && ($errores === false || (
                (int) ($errores['warning_count'] ?? 0) === 0
                && (int) ($errores['error_count'] ?? 0) === 0
            ))
        ) {
            return $fecha->format('Y-m-d H:i:s');
        }
    }

    sm_responder_json(
        false,
        'Selecciona una fecha y hora válidas.',
        ['campo' => $campo],
        422
    );
}

function tej_fecha_valida(string $fecha): bool
{
    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    $errores = DateTimeImmutable::getLastErrors();

    return $objeto instanceof DateTimeImmutable
        && ($errores === false || (
            (int) ($errores['warning_count'] ?? 0) === 0
            && (int) ($errores['error_count'] ?? 0) === 0
        ))
        && $objeto->format('Y-m-d') === $fecha;
}

function tej_texto($valor): string
{
    return sm_limpiar_texto($valor);
}

function tej_texto_validado(
    $valor,
    int $minimo,
    int $maximo,
    string $campo
): string {
    $texto = tej_texto($valor);
    $longitud = tej_longitud($texto);

    if ($longitud < $minimo || $longitud > $maximo) {
        sm_responder_json(
            false,
            'El campo ' . $campo . ' debe contener entre '
                . $minimo . ' y ' . $maximo . ' caracteres.',
            ['campo' => $campo],
            422
        );
    }

    return $texto;
}

function tej_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function tej_recortar(string $texto, int $limite): string
{
    if (tej_longitud($texto) <= $limite) {
        return $texto;
    }

    return function_exists('mb_substr')
        ? mb_substr($texto, 0, $limite, 'UTF-8')
        : substr($texto, 0, $limite);
}

function tej_enlazar(PDOStatement $stmt, array $parametros): void
{
    foreach ($parametros as $clave => $valor) {
        $stmt->bindValue(
            $clave,
            $valor,
            is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }
}

function tej_bind_nullable(PDOStatement $stmt, string $campo, ?string $valor): void
{
    if ($valor === null || $valor === '') {
        $stmt->bindValue($campo, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($campo, $valor, PDO::PARAM_STR);
}

function tej_abortar(
    PDO $conexion,
    string $mensaje,
    int $codigo = 409,
    array $extra = []
): void {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    sm_responder_json(false, $mensaje, $extra, $codigo);
}

function tej_ip(): ?string
{
    $ip = tej_texto($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip === '' ? null : tej_recortar($ip, 45);
}

function tej_json_codificar(array $datos): string
{
    $json = json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    return $json === false ? '{}' : $json;
}

function tej_json_decodificar($json): array
{
    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $datos = json_decode($json, true);
    return is_array($datos) ? $datos : [];
}

function tej_tipo_texto(string $tipo): string
{
    $mapa = [
        'CORRECTIVO_PROGRAMABLE' => 'Correctivo programable',
        'MODIFICACION_MEJORA' => 'Modificación o mejora',
        'CORRECTIVO_URGENTE' => 'Correctivo urgente',
        'RUTINARIO' => 'Rutinario',
    ];

    return $mapa[$tipo] ?? $tipo;
}

function tej_estado_texto(string $estado): string
{
    $mapa = [
        'PENDIENTE' => 'Pendiente',
        'EN_PROCESO' => 'En proceso',
        'PAUSADA' => 'Pausada',
        'TERMINADA' => 'Terminada',
        'CANCELADA' => 'Cancelada',
    ];

    return $mapa[$estado] ?? $estado;
}

function tej_cumplimiento_texto(string $estado): string
{
    $mapa = [
        'PENDIENTE' => 'Pendiente',
        'A_TIEMPO' => 'A tiempo',
        'TARDE' => 'Tarde',
        'NO_REALIZADO' => 'No realizado',
        'NO_APLICA' => 'No aplica',
    ];

    return $mapa[$estado] ?? $estado;
}

function tej_trabajo_quedo_texto(string $estado): string
{
    $mapa = [
        'TERMINADO' => 'Terminado',
        'PARCIAL' => 'Parcial',
        'PROVISIONAL' => 'Provisional',
    ];

    return $mapa[$estado] ?? $estado;
}

function tej_escribir_csv($salida, array $columnas): void
{
    $seguras = array_map('tej_csv_seguro', $columnas);

    if (fputcsv($salida, $seguras, ',', '"', '\\') === false) {
        throw new RuntimeException('No fue posible escribir el archivo CSV.');
    }
}

function tej_csv_seguro($valor): string
{
    $texto = (string) ($valor ?? '');
    $sinEspacios = ltrim($texto);

    /* Evita que Excel interprete contenido proveniente de la BD como fórmula. */
    if ($sinEspacios !== '' && preg_match('/^[=+\-@]/u', $sinEspacios) === 1) {
        return "'" . $texto;
    }

    return $texto;
}

function tej_duracion_csv(int $segundos): string
{
    $segundos = max(0, $segundos);
    $horas = intdiv($segundos, 3600);
    $minutos = intdiv($segundos % 3600, 60);
    $resto = $segundos % 60;

    return sprintf('%02d:%02d:%02d', $horas, $minutos, $resto);
} 