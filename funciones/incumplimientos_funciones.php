<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cumplimiento de mantenimientos - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| Un incumplimiento pertenece a una asignación concreta de un técnico.
| Las urgencias no se evalúan contra calendario laboral.
| El módulo detecta vencimientos, concilia trabajos terminados tarde y
| permite justificar o declarar no realizada una participación sin borrar
| historial ni modificar tiempos de ejecución.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_sesion(['ADMIN'], true);

if (!isset($conexion) || !($conexion instanceof PDO)) {
    sm_responder_json(
        false,
        'No fue posible conectar con la base de datos.',
        [],
        503
    );
}

try {
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[CUMPLIMIENTO][PDO CONFIG] ' . $e->getMessage());
}

final class IncuHttpException extends RuntimeException
{
    /** @var int */
    public $status;

    /** @var array<string,mixed> */
    public $data;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(string $message, int $status = 409, array $data = [])
    {
        parent::__construct($message);
        $this->status = $status;
        $this->data = $data;
    }
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = strtoupper(
    incu_texto(
        $metodo === 'GET'
            ? ($_GET['accion'] ?? 'INICIAL')
            : ($_POST['accion'] ?? '')
    )
);

try {
    incu_validar_admin_activo($conexion, incu_admin_id());

    if ($metodo === 'GET') {
        if ($accion === 'INICIAL' || $accion === 'LISTAR') {
            incu_endpoint_listar($conexion);
        }

        if ($accion === 'DETALLE') {
            incu_endpoint_detalle($conexion);
        }

        if ($accion === 'EXPORTAR') {
            incu_endpoint_exportar($conexion);
        }

        sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
    }

    sm_requerir_metodo('POST');
    sm_validar_csrf();

    if ($accion === 'SINCRONIZAR') {
        incu_endpoint_sincronizar($conexion);
    }

    if ($accion === 'RESOLVER') {
        incu_endpoint_resolver($conexion);
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (IncuHttpException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    sm_responder_json(false, $e->getMessage(), $e->data, $e->status);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'CUM-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][CUMPLIMIENTO][PDO] '
        . $e->getMessage()
        . ' | ' . $e->getFile()
        . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'No fue posible procesar la información de cumplimiento.',
        ['referencia' => $referencia],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'CUM-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][CUMPLIMIENTO] '
        . $e->getMessage()
        . ' | ' . $e->getFile()
        . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'Ocurrió un error interno al consultar el cumplimiento.',
        ['referencia' => $referencia],
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function incu_endpoint_listar(PDO $conexion): void
{
    $sincronizacion = incu_sincronizar_estado($conexion, false);
    $filtros = incu_leer_filtros();
    $consulta = incu_construir_condiciones($filtros, true);
    $consultaResumen = incu_construir_condiciones($filtros, false);

    $total = incu_contar($conexion, $consulta['where'], $consulta['params']);
    $porPagina = (int) $filtros['por_pagina'];
    $totalPaginas = max(1, (int) ceil($total / max(1, $porPagina)));
    $pagina = min((int) $filtros['pagina'], $totalPaginas);
    $offset = max(0, ($pagina - 1) * $porPagina);
    $filtros['pagina'] = $pagina;

    $registros = incu_consultar_registros(
        $conexion,
        $consulta['where'],
        $consulta['params'],
        (string) $filtros['orden'],
        $porPagina,
        $offset
    );

    sm_responder_json(
        true,
        'Cumplimiento cargado correctamente.',
        [
            'csrf_token' => sm_token_csrf(),
            'resumen' => incu_resumen(
                $conexion,
                $consultaResumen['where'],
                $consultaResumen['params']
            ),
            'registros' => $registros,
            'catalogos' => [
                'tecnicos' => incu_catalogo_tecnicos($conexion),
            ],
            'paginacion' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $total,
                'total_paginas' => $totalPaginas,
                'desde' => $total === 0 ? 0 : $offset + 1,
                'hasta' => min($total, $offset + count($registros)),
            ],
            'filtros' => $filtros,
            'reglas' => [
                'hora_corte' => substr(incu_hora_corte($conexion), 0, 5),
                'urgentes_exentos' => true,
                'justificacion_minima' => 15,
                'justificacion_maxima' => 1000,
            ],
            'sincronizacion' => $sincronizacion,
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function incu_endpoint_sincronizar(PDO $conexion): void
{
    $resultado = incu_sincronizar_estado($conexion, true);

    sm_responder_json(
        true,
        'Los vencimientos y resultados fueron actualizados.',
        [
            'resultado' => $resultado,
            'csrf_token' => sm_token_csrf(),
        ]
    );
}

function incu_endpoint_detalle(PDO $conexion): void
{
    $id = incu_entero_positivo($_GET['id'] ?? null, 'incumplimiento');
    $registro = incu_consultar_registro($conexion, $id, false);

    if (!$registro) {
        throw new IncuHttpException(
            'El registro de cumplimiento no existe.',
            404
        );
    }

    sm_responder_json(
        true,
        'Detalle cargado correctamente.',
        [
            'registro' => $registro,
            'historial' => incu_historial_solicitud(
                $conexion,
                (int) $registro['solicitud_id']
            ),
            'programaciones' => incu_programaciones_solicitud(
                $conexion,
                (int) $registro['solicitud_id']
            ),
            'participantes' => incu_participantes_solicitud(
                $conexion,
                (int) $registro['solicitud_id']
            ),
        ]
    );
}

function incu_endpoint_resolver(PDO $conexion): void
{
    $adminId = incu_admin_id();
    $id = incu_entero_positivo($_POST['incumplimiento_id'] ?? null, 'incumplimiento');
    $resolucion = strtoupper(incu_texto($_POST['resolucion'] ?? ''));
    $motivo = incu_texto_validado(
        $_POST['motivo'] ?? '',
        15,
        1000,
        'motivo'
    );

    if (!in_array($resolucion, ['JUSTIFICAR', 'NO_REALIZADO'], true)) {
        throw new IncuHttpException(
            'Selecciona una resolución válida.',
            422,
            ['campo' => 'resolucion']
        );
    }

    $conexion->beginTransaction();
    $registro = incu_consultar_registro($conexion, $id, true);

    if (!$registro) {
        throw new IncuHttpException(
            'El registro ya no existe.',
            404
        );
    }

    if ((string) $registro['estado_incumplimiento'] !== 'PENDIENTE') {
        throw new IncuHttpException(
            'Este incumplimiento ya fue resuelto por otro administrador.',
            409,
            ['estado_actual' => $registro['estado_incumplimiento']]
        );
    }

    $anteriores = [
        'estado_incumplimiento' => $registro['estado_incumplimiento'],
        'justificacion' => $registro['justificacion'],
        'resultado_cumplimiento' => $registro['resultado_cumplimiento'],
        'estado_asignacion' => $registro['estado_asignacion'],
        'asignacion_activa' => $registro['asignacion_activa'],
    ];

    if ($resolucion === 'JUSTIFICAR') {
        incu_resolver_justificado($conexion, $registro, $adminId, $motivo);
        $estadoNuevo = 'JUSTIFICADO';
        $evento = 'JUSTIFICADA';
        $accionMovimiento = 'JUSTIFICAR_INCUMPLIMIENTO';
        $mensaje = 'El incumplimiento fue justificado y quedó registrado en el historial.';
    } else {
        incu_resolver_no_realizado($conexion, $registro, $adminId, $motivo);
        $estadoNuevo = 'NO_REALIZADO';
        $evento = 'NO_REALIZADA';
        $accionMovimiento = 'MARCAR_NO_REALIZADO';
        $mensaje = 'La participación quedó marcada como no realizada y fue retirada de las asignaciones activas.';
    }

    $descripcion = ($resolucion === 'JUSTIFICAR'
        ? 'Se justificó el incumplimiento'
        : 'Se declaró no realizada la participación')
        . ' del técnico ' . (string) $registro['tecnico']
        . ' en la solicitud ' . (string) $registro['folio']
        . '. Motivo: ' . $motivo;

    incu_historial(
        $conexion,
        (int) $registro['solicitud_id'],
        (int) $registro['solicitud_tecnico_id'],
        (int) $registro['programacion_id'],
        $evento,
        (string) $registro['estado_solicitud'],
        $resolucion === 'NO_REALIZADO'
            ? 'ATRASADO'
            : (string) $registro['estado_solicitud'],
        $adminId,
        $descripcion
    );

    incu_movimiento(
        $conexion,
        $adminId,
        $accionMovimiento,
        $descripcion,
        'incumplimientos_mantenimiento',
        $id
    );

    $nuevos = [
        'estado_incumplimiento' => $estadoNuevo,
        'justificacion' => $motivo,
        'justificado_por_admin_id' => $adminId,
        'fecha_resolucion' => date('Y-m-d H:i:s'),
    ];

    incu_auditoria(
        $conexion,
        $id,
        (int) $registro['solicitud_id'],
        $adminId,
        $motivo,
        $anteriores,
        $nuevos
    );

    incu_notificar_resolucion(
        $conexion,
        $registro,
        $resolucion,
        $motivo
    );

    $conexion->commit();

    sm_responder_json(
        true,
        $mensaje,
        [
            'incumplimiento_id' => $id,
            'estado' => $estadoNuevo,
            'csrf_token' => sm_token_csrf(),
        ]
    );
}

function incu_endpoint_exportar(PDO $conexion): void
{
    $filtros = incu_leer_filtros();
    $consulta = incu_construir_condiciones($filtros, true);
    $total = incu_contar($conexion, $consulta['where'], $consulta['params']);

    if ($total > 5000) {
        throw new IncuHttpException(
            'La exportación supera 5,000 registros. Reduce el rango de fechas o aplica más filtros.',
            422
        );
    }

    $registros = incu_consultar_registros(
        $conexion,
        $consulta['where'],
        $consulta['params'],
        (string) $filtros['orden'],
        max(1, $total),
        0
    );

    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header(
            'Content-Disposition: attachment; filename="cumplimiento_'
            . date('Ymd_His') . '.csv"'
        );
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo "\xEF\xBB\xBF";
    $salida = fopen('php://output', 'wb');

    if ($salida === false) {
        throw new RuntimeException('No fue posible generar el archivo CSV.');
    }

    fputcsv($salida, [
        'Folio',
        'Tipo',
        'Prioridad',
        'Equipo',
        'Ubicación',
        'Técnico',
        'Turno',
        'Fecha programada',
        'Fecha límite',
        'Minutos de atraso',
        'Estado del incumplimiento',
        'Estado de la solicitud',
        'Resultado del técnico',
        'Inicio real',
        'Fin real',
        'Resolución',
        'Motivo o justificación',
        'Resuelto por',
    ]);

    foreach ($registros as $fila) {
        fputcsv($salida, [
            incu_csv($fila['folio']),
            incu_csv(incu_tipo_texto((string) $fila['tipo_solicitud'])),
            incu_csv(incu_etiqueta_enum((string) $fila['prioridad'])),
            incu_csv(trim((string) $fila['codigo_equipo'] . ' - ' . (string) $fila['nombre_equipo'], ' -')),
            incu_csv((string) $fila['ubicacion']),
            incu_csv((string) $fila['tecnico']),
            incu_csv(incu_etiqueta_enum((string) $fila['turno'])),
            incu_csv((string) $fila['fecha_programada']),
            incu_csv((string) $fila['fecha_limite']),
            (int) $fila['minutos_atraso'],
            incu_csv(incu_estado_texto((string) $fila['estado_incumplimiento'])),
            incu_csv(incu_etiqueta_enum((string) $fila['estado_solicitud'])),
            incu_csv(incu_etiqueta_enum((string) $fila['resultado_cumplimiento'])),
            incu_csv((string) ($fila['fecha_hora_inicio'] ?? '')),
            incu_csv((string) ($fila['fecha_hora_fin'] ?? '')),
            incu_csv((string) ($fila['fecha_resolucion'] ?? '')),
            incu_csv((string) ($fila['justificacion'] ?? '')),
            incu_csv((string) ($fila['administrador_resolvio'] ?? '')),
        ]);
    }

    fclose($salida);
    exit;
}

/* =========================================================================
   SINCRONIZACIÓN
   ========================================================================= */

/**
 * @return array<string,mixed>
 */
function incu_sincronizar_estado(PDO $conexion, bool $manual): array
{
    $lock = incu_adquirir_lock($conexion, 'sm_cumplimiento_sync_v1', 4);

    if (!$lock) {
        if ($manual) {
            throw new IncuHttpException(
                'Otro proceso está actualizando el cumplimiento. Espera unos segundos e inténtalo nuevamente.',
                409
            );
        }

        return [
            'omitida' => true,
            'motivo' => 'Sincronización concurrente en curso.',
        ];
    }

    $corte = incu_hora_corte($conexion);
    $resultado = [
        'creados' => 0,
        'cumplidos_tarde' => 0,
        'no_realizados_conciliados' => 0,
        'programaciones_vencidas' => 0,
        'solicitudes_atrasadas' => 0,
        'omitida' => false,
    ];

    try {
        $conexion->beginTransaction();

        $stmtFaltantes = $conexion->prepare(
            "SELECT
                s.id AS solicitud_id,
                s.folio,
                s.estado AS estado_solicitud,
                pm.id AS programacion_id,
                pm.fecha_programada,
                pm.fecha_limite,
                st.id AS solicitud_tecnico_id,
                st.tecnico_id,
                TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico
             FROM programaciones_mantenimiento pm
             INNER JOIN solicitudes s ON s.id = pm.solicitud_id
             INNER JOIN solicitud_tecnicos st ON st.programacion_id = pm.id
             INNER JOIN tecnicos t ON t.id = st.tecnico_id
             LEFT JOIN incumplimientos_mantenimiento im
                    ON im.solicitud_tecnico_id = st.id
                   AND im.programacion_id = pm.id
             WHERE s.tipo_solicitud <> 'CORRECTIVO_URGENTE'
               AND TIMESTAMP(pm.fecha_limite, :hora_corte) < NOW()
               AND pm.estado IN ('PROGRAMADA','VENCIDA','CUMPLIDA')
               AND st.origen = 'ADMIN'
               AND st.resultado_cumplimiento IN ('PENDIENTE','TARDE','NO_REALIZADO')
               AND im.id IS NULL
             ORDER BY pm.fecha_limite, st.id
             FOR UPDATE"
        );
        $stmtFaltantes->bindValue(':hora_corte', $corte, PDO::PARAM_STR);
        $stmtFaltantes->execute();
        $faltantes = $stmtFaltantes->fetchAll();

        $stmtInsertar = $conexion->prepare(
            "INSERT IGNORE INTO incumplimientos_mantenimiento
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

        foreach ($faltantes as $fila) {
            $stmtInsertar->execute([
                ':solicitud_id' => (int) $fila['solicitud_id'],
                ':programacion_id' => (int) $fila['programacion_id'],
                ':solicitud_tecnico_id' => (int) $fila['solicitud_tecnico_id'],
                ':fecha_programada' => (string) $fila['fecha_programada'],
            ]);

            if ($stmtInsertar->rowCount() !== 1) {
                continue;
            }

            $resultado['creados']++;
            $estadoNuevo = in_array(
                (string) $fila['estado_solicitud'],
                ['EN_PROCESO', 'PAUSADO', 'TERMINADO'],
                true
            )
                ? (string) $fila['estado_solicitud']
                : 'ATRASADO';

            incu_historial(
                $conexion,
                (int) $fila['solicitud_id'],
                (int) $fila['solicitud_tecnico_id'],
                (int) $fila['programacion_id'],
                'INCUMPLIMIENTO_DETECTADO',
                (string) $fila['estado_solicitud'],
                $estadoNuevo,
                0,
                'La fecha límite venció sin que la participación del técnico '
                    . (string) $fila['tecnico']
                    . ' se registrara como cumplida.'
            );
        }

        /*
         * La conciliación se realiza registro por registro. Así cada cambio
         * automático deja una entrada única en el historial y no se pierde la
         * trazabilidad cuando un técnico termina después de la fecha límite o
         * cuando otro módulo ya marcó su participación como no realizada.
         */
        $stmtConciliar = $conexion->query(
            "SELECT
                im.id AS incumplimiento_id,
                im.solicitud_id,
                im.programacion_id,
                im.solicitud_tecnico_id,
                st.resultado_cumplimiento,
                st.fecha_resultado,
                s.estado AS estado_solicitud,
                s.folio,
                TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico
             FROM incumplimientos_mantenimiento im
             INNER JOIN solicitud_tecnicos st ON st.id = im.solicitud_tecnico_id
             INNER JOIN solicitudes s ON s.id = im.solicitud_id
             INNER JOIN tecnicos t ON t.id = st.tecnico_id
             WHERE im.estado = 'PENDIENTE'
               AND st.resultado_cumplimiento IN ('TARDE','NO_REALIZADO')
             ORDER BY im.id
             FOR UPDATE"
        );
        $porConciliar = $stmtConciliar->fetchAll();

        $stmtActualizarIncumplimiento = $conexion->prepare(
            "UPDATE incumplimientos_mantenimiento
             SET estado = :estado,
                 fecha_resolucion = COALESCE(fecha_resolucion, :fecha_resultado, NOW())
             WHERE id = :id
               AND estado = 'PENDIENTE'"
        );

        foreach ($porConciliar as $filaConciliar) {
            $resultadoTecnico = (string) $filaConciliar['resultado_cumplimiento'];
            $estadoIncumplimiento = $resultadoTecnico === 'TARDE'
                ? 'CUMPLIDO_TARDE'
                : 'NO_REALIZADO';

            $stmtActualizarIncumplimiento->bindValue(
                ':estado',
                $estadoIncumplimiento,
                PDO::PARAM_STR
            );
            if (!empty($filaConciliar['fecha_resultado'])) {
                $stmtActualizarIncumplimiento->bindValue(
                    ':fecha_resultado',
                    (string) $filaConciliar['fecha_resultado'],
                    PDO::PARAM_STR
                );
            } else {
                $stmtActualizarIncumplimiento->bindValue(
                    ':fecha_resultado',
                    null,
                    PDO::PARAM_NULL
                );
            }
            $stmtActualizarIncumplimiento->bindValue(
                ':id',
                (int) $filaConciliar['incumplimiento_id'],
                PDO::PARAM_INT
            );
            $stmtActualizarIncumplimiento->execute();

            if ($stmtActualizarIncumplimiento->rowCount() !== 1) {
                continue;
            }

            if ($estadoIncumplimiento === 'CUMPLIDO_TARDE') {
                $resultado['cumplidos_tarde']++;
                $evento = 'CUMPLIDA_TARDE';
                $descripcionConciliacion = 'La participación del técnico '
                    . (string) $filaConciliar['tecnico']
                    . ' en la solicitud ' . (string) $filaConciliar['folio']
                    . ' fue conciliada automáticamente como cumplida tarde.';
            } else {
                $resultado['no_realizados_conciliados']++;
                $evento = 'NO_REALIZADA';
                $descripcionConciliacion = 'La participación del técnico '
                    . (string) $filaConciliar['tecnico']
                    . ' en la solicitud ' . (string) $filaConciliar['folio']
                    . ' fue conciliada automáticamente como no realizada.';
            }

            incu_historial(
                $conexion,
                (int) $filaConciliar['solicitud_id'],
                (int) $filaConciliar['solicitud_tecnico_id'],
                (int) $filaConciliar['programacion_id'],
                $evento,
                (string) $filaConciliar['estado_solicitud'],
                (string) $filaConciliar['estado_solicitud'],
                0,
                $descripcionConciliacion
            );
        }

        $stmtVencidas = $conexion->prepare(
            "UPDATE programaciones_mantenimiento pm
             INNER JOIN solicitudes s ON s.id = pm.solicitud_id
             SET pm.estado = 'VENCIDA'
             WHERE pm.es_actual = 1
               AND pm.estado = 'PROGRAMADA'
               AND TIMESTAMP(pm.fecha_limite, :corte_programacion) < NOW()
               AND s.tipo_solicitud <> 'CORRECTIVO_URGENTE'
               AND s.estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')"
        );
        $stmtVencidas->bindValue(':corte_programacion', $corte, PDO::PARAM_STR);
        $stmtVencidas->execute();
        $resultado['programaciones_vencidas'] = $stmtVencidas->rowCount();

        $stmtAtrasadas = $conexion->prepare(
            "UPDATE solicitudes s
             INNER JOIN programaciones_mantenimiento pm
                     ON pm.solicitud_id = s.id
                    AND pm.es_actual = 1
             SET s.estado = 'ATRASADO',
                 s.fecha_actualizacion = NOW()
             WHERE s.estado IN ('APROBADO','AGENDADO')
               AND s.activo = 1
               AND s.tipo_solicitud <> 'CORRECTIVO_URGENTE'
               AND TIMESTAMP(pm.fecha_limite, :corte_solicitud) < NOW()
               AND NOT EXISTS (
                    SELECT 1
                    FROM ejecuciones_mantenimiento em
                    WHERE em.solicitud_id = s.id
                      AND em.fecha_hora_inicio IS NOT NULL
               )"
        );
        $stmtAtrasadas->bindValue(':corte_solicitud', $corte, PDO::PARAM_STR);
        $stmtAtrasadas->execute();
        $resultado['solicitudes_atrasadas'] = $stmtAtrasadas->rowCount();

        $conexion->commit();
    } finally {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        incu_liberar_lock($conexion, 'sm_cumplimiento_sync_v1');
    }

    return $resultado;
}

/* =========================================================================
   RESOLUCIONES
   ========================================================================= */

/**
 * @param array<string,mixed> $registro
 */
function incu_resolver_justificado(
    PDO $conexion,
    array $registro,
    int $adminId,
    string $motivo
): void {
    $stmt = $conexion->prepare(
        "UPDATE incumplimientos_mantenimiento
         SET estado = 'JUSTIFICADO',
             justificacion = :motivo,
             justificado_por_admin_id = :admin_id,
             fecha_resolucion = NOW()
         WHERE id = :id
           AND estado = 'PENDIENTE'"
    );
    $stmt->execute([
        ':motivo' => $motivo,
        ':admin_id' => $adminId,
        ':id' => (int) $registro['incumplimiento_id'],
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new IncuHttpException(
            'El incumplimiento cambió mientras intentabas justificarlo.',
            409
        );
    }

    $stmtAsignacion = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET fecha_resultado = CASE
                 WHEN resultado_cumplimiento = 'PENDIENTE' THEN NOW()
                 ELSE fecha_resultado
             END,
             resultado_cumplimiento = CASE
                 WHEN resultado_cumplimiento = 'PENDIENTE' THEN 'NO_APLICA'
                 ELSE resultado_cumplimiento
             END
         WHERE id = :id"
    );
    $stmtAsignacion->bindValue(
        ':id',
        (int) $registro['solicitud_tecnico_id'],
        PDO::PARAM_INT
    );
    $stmtAsignacion->execute();
}

/**
 * @param array<string,mixed> $registro
 */
function incu_resolver_no_realizado(
    PDO $conexion,
    array $registro,
    int $adminId,
    string $motivo
): void {
    if ((string) $registro['tipo_solicitud'] === 'CORRECTIVO_URGENTE') {
        throw new IncuHttpException(
            'Las urgencias no se evalúan desde este módulo.',
            409
        );
    }

    if (!in_array(
        (string) $registro['estado_solicitud'],
        ['APROBADO', 'AGENDADO', 'ATRASADO'],
        true
    )) {
        throw new IncuHttpException(
            'La solicitud ya está en ejecución o cerrada. En este caso sólo puede registrarse una justificación administrativa.',
            409
        );
    }

    if ((int) $registro['asignacion_activa'] !== 1) {
        throw new IncuHttpException(
            'La asignación ya no está activa. Registra una justificación en lugar de retirarla nuevamente.',
            409
        );
    }

    if (!in_array(
        (string) $registro['estado_asignacion'],
        ['ASIGNADO', 'ACEPTADO'],
        true
    )) {
        throw new IncuHttpException(
            'La participación ya cambió de estado y no puede marcarse como no realizada.',
            409
        );
    }

    if (!empty($registro['fecha_hora_inicio'])) {
        throw new IncuHttpException(
            'El técnico ya inició el mantenimiento. No puede marcarse como no realizado.',
            409
        );
    }

    $stmt = $conexion->prepare(
        "UPDATE incumplimientos_mantenimiento
         SET estado = 'NO_REALIZADO',
             justificacion = :motivo,
             justificado_por_admin_id = :admin_id,
             fecha_resolucion = NOW()
         WHERE id = :id
           AND estado = 'PENDIENTE'"
    );
    $stmt->execute([
        ':motivo' => $motivo,
        ':admin_id' => $adminId,
        ':id' => (int) $registro['incumplimiento_id'],
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new IncuHttpException(
            'El incumplimiento cambió mientras intentabas resolverlo.',
            409
        );
    }

    $stmtAsignacion = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET estado = 'NO_PARTICIPO',
             resultado_cumplimiento = 'NO_REALIZADO',
             fecha_resultado = NOW(),
             fecha_retiro = COALESCE(fecha_retiro, NOW()),
             activo = 0,
             activo_token = NULL,
             fecha_actualizacion = NOW()
         WHERE id = :id
           AND activo = 1
           AND estado IN ('ASIGNADO','ACEPTADO')"
    );
    $stmtAsignacion->bindValue(
        ':id',
        (int) $registro['solicitud_tecnico_id'],
        PDO::PARAM_INT
    );
    $stmtAsignacion->execute();

    if ($stmtAsignacion->rowCount() !== 1) {
        throw new IncuHttpException(
            'La asignación cambió mientras intentabas retirarla. Actualiza la pantalla.',
            409
        );
    }

    $stmtSolicitud = $conexion->prepare(
        "UPDATE solicitudes
         SET estado = 'ATRASADO',
             ultima_edicion_admin_id = :admin_id,
             motivo_ultima_edicion = :motivo,
             version_registro = version_registro + 1,
             fecha_actualizacion = NOW()
         WHERE id = :id
           AND activo = 1
           AND estado IN ('APROBADO','AGENDADO','ATRASADO')"
    );
    $stmtSolicitud->execute([
        ':admin_id' => $adminId,
        ':motivo' => incu_recortar(
            'Participación no realizada. ' . $motivo,
            500
        ),
        ':id' => (int) $registro['solicitud_id'],
    ]);

    $stmtNotificaciones = $conexion->prepare(
        "UPDATE notificaciones
         SET leida = 1,
             fecha_lectura = COALESCE(fecha_lectura, NOW())
         WHERE solicitud_id = :solicitud_id
           AND tipo_usuario = 'TECNICO'
           AND usuario_id = :tecnico_id
           AND leida = 0"
    );
    $stmtNotificaciones->execute([
        ':solicitud_id' => (int) $registro['solicitud_id'],
        ':tecnico_id' => (int) $registro['tecnico_id'],
    ]);
}

/* =========================================================================
   CONSULTAS
   ========================================================================= */

/**
 * @return array<string,mixed>|null
 */
function incu_consultar_registro(PDO $conexion, int $id, bool $bloquear): ?array
{
    $sql = incu_select_base()
        . " WHERE im.id = :id LIMIT 1"
        . ($bloquear ? ' FOR UPDATE' : '');

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':hora_corte_limite', incu_hora_corte($conexion), PDO::PARAM_STR);
    $stmt->bindValue(':hora_corte_atraso', incu_hora_corte($conexion), PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch();

    return $fila ? incu_normalizar_registro($fila) : null;
}

/**
 * @param string[] $where
 * @param array<string,mixed> $params
 */
function incu_contar(PDO $conexion, array $where, array $params): int
{
    $sql = "SELECT COUNT(*)
            FROM incumplimientos_mantenimiento im
            INNER JOIN solicitudes s ON s.id = im.solicitud_id
            INNER JOIN programaciones_mantenimiento pm ON pm.id = im.programacion_id
            INNER JOIN solicitud_tecnicos st ON st.id = im.solicitud_tecnico_id
            INNER JOIN tecnicos t ON t.id = st.tecnico_id
            INNER JOIN equipos e ON e.id = s.equipo_id
            INNER JOIN departamentos d ON d.id = s.departamento_id
            INNER JOIN areas a ON a.id = s.area_id
            INNER JOIN procesos p ON p.id = s.proceso_id
            WHERE " . implode(' AND ', $where);

    $stmt = $conexion->prepare($sql);
    incu_bind_params($stmt, $params);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

/**
 * @param string[] $where
 * @param array<string,mixed> $params
 * @return array<int,array<string,mixed>>
 */
function incu_consultar_registros(
    PDO $conexion,
    array $where,
    array $params,
    string $orden,
    int $limite,
    int $offset
): array {
    $sql = incu_select_base()
        . ' WHERE ' . implode(' AND ', $where)
        . ' ORDER BY ' . incu_orden_sql($orden)
        . ' LIMIT :limite OFFSET :offset';

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':hora_corte_limite', incu_hora_corte($conexion), PDO::PARAM_STR);
    $stmt->bindValue(':hora_corte_atraso', incu_hora_corte($conexion), PDO::PARAM_STR);
    incu_bind_params($stmt, $params);
    $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll();

    return array_map('incu_normalizar_registro', $filas ?: []);
}

function incu_select_base(): string
{
    return "SELECT
        im.id AS incumplimiento_id,
        im.solicitud_id,
        im.programacion_id,
        im.solicitud_tecnico_id,
        im.fecha_programada,
        im.fecha_detectado,
        im.estado AS estado_incumplimiento,
        im.justificacion,
        im.justificado_por_admin_id,
        im.fecha_resolucion,
        s.folio,
        s.tipo_solicitud,
        s.estado AS estado_solicitud,
        s.prioridad,
        s.descripcion_solicitud,
        s.fecha_solicitud,
        s.trabajo_peligroso,
        s.nivel_riesgo,
        s.requiere_paro_equipo,
        pm.fecha_limite,
        pm.estado AS estado_programacion,
        pm.es_actual AS programacion_actual,
        TIMESTAMP(pm.fecha_limite, :hora_corte_limite) AS fecha_hora_limite,
        st.tecnico_id,
        st.estado AS estado_asignacion,
        st.resultado_cumplimiento,
        st.fecha_resultado,
        st.activo AS asignacion_activa,
        st.origen AS origen_asignacion,
        t.usuario AS usuario_tecnico,
        TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
        t.turno,
        t.especialidad,
        e.codigo_equipo,
        e.nombre_equipo,
        d.nombre AS departamento,
        a.nombre AS area,
        p.nombre AS proceso,
        CONCAT_WS(' · ', d.nombre, a.nombre, p.nombre) AS ubicacion,
        em.id AS ejecucion_id,
        em.estado AS estado_ejecucion,
        em.fecha_hora_inicio,
        em.fecha_hora_fin,
        em.total_segundos_activos,
        em.total_segundos_pausa,
        cm.id AS cierre_id,
        cm.fecha_hora_cierre,
        cm.trabajo_quedo,
        cm.descripcion_trabajo_realizado,
        cm.que_falto,
        TRIM(CONCAT_WS(' ', ar.nombre, ar.apellido_paterno, ar.apellido_materno)) AS administrador_resolvio,
        GREATEST(
            0,
            TIMESTAMPDIFF(
                MINUTE,
                TIMESTAMP(pm.fecha_limite, :hora_corte_atraso),
                COALESCE(
                    st.fecha_resultado,
                    im.fecha_resolucion,
                    cm.fecha_hora_cierre,
                    em.fecha_hora_fin,
                    NOW()
                )
            )
        ) AS minutos_atraso
     FROM incumplimientos_mantenimiento im
     INNER JOIN solicitudes s ON s.id = im.solicitud_id
     INNER JOIN programaciones_mantenimiento pm ON pm.id = im.programacion_id
     INNER JOIN solicitud_tecnicos st ON st.id = im.solicitud_tecnico_id
     INNER JOIN tecnicos t ON t.id = st.tecnico_id
     INNER JOIN equipos e ON e.id = s.equipo_id
     INNER JOIN departamentos d ON d.id = s.departamento_id
     INNER JOIN areas a ON a.id = s.area_id
     INNER JOIN procesos p ON p.id = s.proceso_id
     LEFT JOIN ejecuciones_mantenimiento em ON em.solicitud_tecnico_id = st.id
     LEFT JOIN cierres_mantenimiento cm ON cm.solicitud_id = s.id
     LEFT JOIN administradores ar ON ar.id = im.justificado_por_admin_id";
}

/**
 * @param array<string,mixed> $fila
 * @return array<string,mixed>
 */
function incu_normalizar_registro(array $fila): array
{
    $enteros = [
        'incumplimiento_id',
        'solicitud_id',
        'programacion_id',
        'solicitud_tecnico_id',
        'tecnico_id',
        'programacion_actual',
        'asignacion_activa',
        'trabajo_peligroso',
        'requiere_paro_equipo',
        'total_segundos_activos',
        'total_segundos_pausa',
        'minutos_atraso',
    ];

    foreach ($enteros as $campo) {
        $fila[$campo] = (int) ($fila[$campo] ?? 0);
    }

    foreach (['ejecucion_id', 'cierre_id', 'justificado_por_admin_id'] as $campo) {
        $fila[$campo] = isset($fila[$campo]) && $fila[$campo] !== null
            ? (int) $fila[$campo]
            : null;
    }

    $fila['puede_justificar'] = (string) $fila['estado_incumplimiento'] === 'PENDIENTE';
    $fila['puede_no_realizado'] = incu_puede_no_realizado($fila);
    $fila['puede_reprogramar'] = in_array(
        (string) $fila['estado_solicitud'],
        ['APROBADO', 'AGENDADO', 'ATRASADO'],
        true
    ) && empty($fila['fecha_hora_inicio']);
    $fila['situacion'] = incu_situacion($fila);
    $fila['atraso_texto'] = incu_duracion_minutos((int) $fila['minutos_atraso']);
    $fila['tipo_texto'] = incu_tipo_texto((string) $fila['tipo_solicitud']);
    $fila['estado_texto'] = incu_estado_texto((string) $fila['estado_incumplimiento']);

    return $fila;
}

/**
 * @param array<string,mixed> $fila
 */
function incu_puede_no_realizado(array $fila): bool
{
    return (string) ($fila['estado_incumplimiento'] ?? '') === 'PENDIENTE'
        && (string) ($fila['tipo_solicitud'] ?? '') !== 'CORRECTIVO_URGENTE'
        && in_array(
            (string) ($fila['estado_solicitud'] ?? ''),
            ['APROBADO', 'AGENDADO', 'ATRASADO'],
            true
        )
        && (int) ($fila['asignacion_activa'] ?? 0) === 1
        && in_array(
            (string) ($fila['estado_asignacion'] ?? ''),
            ['ASIGNADO', 'ACEPTADO'],
            true
        )
        && empty($fila['fecha_hora_inicio']);
}

/**
 * @param array<string,mixed> $fila
 */
function incu_situacion(array $fila): string
{
    $estado = (string) ($fila['estado_incumplimiento'] ?? '');

    if ($estado !== 'PENDIENTE') {
        return $estado;
    }

    if (!empty($fila['fecha_hora_fin'])) {
        return 'FINALIZADO_POR_CONCILIAR';
    }

    if ((string) ($fila['estado_ejecucion'] ?? '') === 'PAUSADA') {
        return 'PAUSADO_TARDE';
    }

    if (!empty($fila['fecha_hora_inicio'])) {
        return 'EN_PROCESO_TARDE';
    }

    if ((int) ($fila['asignacion_activa'] ?? 0) !== 1) {
        return 'ASIGNACION_RETIRADA';
    }

    return 'VENCIDO_SIN_INICIAR';
}

/**
 * @param string[] $where
 * @param array<string,mixed> $params
 * @return array<string,mixed>
 */
function incu_resumen(PDO $conexion, array $where, array $params): array
{
    $sql = "SELECT
        COUNT(*) AS total,
        SUM(im.estado = 'PENDIENTE') AS pendientes,
        SUM(im.estado = 'CUMPLIDO_TARDE') AS cumplidos_tarde,
        SUM(im.estado = 'JUSTIFICADO') AS justificados,
        SUM(im.estado = 'NO_REALIZADO') AS no_realizados,
        COUNT(DISTINCT st.tecnico_id) AS tecnicos,
        COALESCE(ROUND(AVG(
            CASE
                WHEN im.estado = 'PENDIENTE' THEN GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        MINUTE,
                        TIMESTAMP(pm.fecha_limite, :corte_resumen),
                        NOW()
                    )
                )
                ELSE NULL
            END
        )), 0) AS promedio_minutos_pendientes
     FROM incumplimientos_mantenimiento im
     INNER JOIN solicitudes s ON s.id = im.solicitud_id
     INNER JOIN programaciones_mantenimiento pm ON pm.id = im.programacion_id
     INNER JOIN solicitud_tecnicos st ON st.id = im.solicitud_tecnico_id
     INNER JOIN tecnicos t ON t.id = st.tecnico_id
     INNER JOIN equipos e ON e.id = s.equipo_id
     INNER JOIN departamentos d ON d.id = s.departamento_id
     INNER JOIN areas a ON a.id = s.area_id
     INNER JOIN procesos p ON p.id = s.proceso_id
     WHERE " . implode(' AND ', $where);

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':corte_resumen', incu_hora_corte($conexion), PDO::PARAM_STR);
    incu_bind_params($stmt, $params);
    $stmt->execute();
    $fila = $stmt->fetch() ?: [];

    return [
        'total' => (int) ($fila['total'] ?? 0),
        'pendientes' => (int) ($fila['pendientes'] ?? 0),
        'cumplidos_tarde' => (int) ($fila['cumplidos_tarde'] ?? 0),
        'justificados' => (int) ($fila['justificados'] ?? 0),
        'no_realizados' => (int) ($fila['no_realizados'] ?? 0),
        'tecnicos' => (int) ($fila['tecnicos'] ?? 0),
        'promedio_minutos_pendientes' => (int) ($fila['promedio_minutos_pendientes'] ?? 0),
        'promedio_atraso_texto' => incu_duracion_minutos(
            (int) ($fila['promedio_minutos_pendientes'] ?? 0)
        ),
    ];
}

/**
 * @return array<int,array<string,mixed>>
 */
function incu_catalogo_tecnicos(PDO $conexion): array
{
    $stmt = $conexion->query(
        "SELECT DISTINCT
            t.id,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
            t.turno,
            t.activo
         FROM incumplimientos_mantenimiento im
         INNER JOIN solicitud_tecnicos st ON st.id = im.solicitud_tecnico_id
         INNER JOIN tecnicos t ON t.id = st.tecnico_id
         ORDER BY tecnico"
    );
    $filas = $stmt->fetchAll() ?: [];

    foreach ($filas as &$fila) {
        $fila['id'] = (int) $fila['id'];
        $fila['activo'] = (int) $fila['activo'];
    }
    unset($fila);

    return $filas;
}

/**
 * @return array<int,array<string,mixed>>
 */
function incu_historial_solicitud(PDO $conexion, int $solicitudId): array
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
            CASE hs.actor_tipo
                WHEN 'ADMIN' THEN TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno))
                WHEN 'TECNICO' THEN TRIM(CONCAT_WS(' ', te.nombre, te.apellido_paterno, te.apellido_materno))
                WHEN 'SOLICITANTE' THEN TRIM(CONCAT_WS(' ', so.nombre, so.apellido_paterno, so.apellido_materno))
                ELSE 'Sistema'
            END AS actor
         FROM historial_solicitudes hs
         LEFT JOIN administradores ad ON hs.actor_tipo = 'ADMIN' AND ad.id = hs.actor_id
         LEFT JOIN tecnicos te ON hs.actor_tipo = 'TECNICO' AND te.id = hs.actor_id
         LEFT JOIN solicitantes so ON hs.actor_tipo = 'SOLICITANTE' AND so.id = hs.actor_id
         WHERE hs.solicitud_id = :solicitud_id
         ORDER BY hs.fecha_evento DESC, hs.id DESC
         LIMIT 30"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}

/**
 * @return array<int,array<string,mixed>>
 */
function incu_programaciones_solicitud(PDO $conexion, int $solicitudId): array
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
            TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno)) AS administrador
         FROM programaciones_mantenimiento pm
         LEFT JOIN administradores ad ON ad.id = pm.programado_por_admin_id
         WHERE pm.solicitud_id = :solicitud_id
         ORDER BY pm.fecha_registro DESC, pm.id DESC"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll() ?: [];

    foreach ($filas as &$fila) {
        $fila['id'] = (int) $fila['id'];
        $fila['es_actual'] = (int) $fila['es_actual'];
    }
    unset($fila);

    return $filas;
}

/**
 * @return array<int,array<string,mixed>>
 */
function incu_participantes_solicitud(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            st.id,
            st.programacion_id,
            st.tecnico_id,
            st.estado,
            st.resultado_cumplimiento,
            st.fecha_resultado,
            st.activo,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
            t.turno,
            im.id AS incumplimiento_id,
            im.estado AS estado_incumplimiento,
            im.justificacion,
            em.estado AS estado_ejecucion,
            em.fecha_hora_inicio,
            em.fecha_hora_fin,
            em.total_segundos_activos,
            em.total_segundos_pausa
         FROM solicitud_tecnicos st
         INNER JOIN tecnicos t ON t.id = st.tecnico_id
         LEFT JOIN incumplimientos_mantenimiento im
                ON im.solicitud_tecnico_id = st.id
               AND im.programacion_id = st.programacion_id
         LEFT JOIN ejecuciones_mantenimiento em ON em.solicitud_tecnico_id = st.id
         WHERE st.solicitud_id = :solicitud_id
         ORDER BY st.fecha_asignacion DESC, st.id DESC"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll() ?: [];

    foreach ($filas as &$fila) {
        foreach (['id', 'programacion_id', 'tecnico_id', 'activo', 'total_segundos_activos', 'total_segundos_pausa'] as $campo) {
            $fila[$campo] = isset($fila[$campo]) && $fila[$campo] !== null
                ? (int) $fila[$campo]
                : null;
        }
        $fila['incumplimiento_id'] = isset($fila['incumplimiento_id'])
            ? (int) $fila['incumplimiento_id']
            : null;
    }
    unset($fila);

    return $filas;
}

/* =========================================================================
   FILTROS
   ========================================================================= */

/**
 * @return array<string,mixed>
 */
function incu_leer_filtros(): array
{
    $estado = strtoupper(incu_texto($_GET['estado'] ?? 'TODOS'));
    $tipo = strtoupper(incu_texto($_GET['tipo'] ?? 'TODOS'));
    $prioridad = strtoupper(incu_texto($_GET['prioridad'] ?? 'TODAS'));
    $orden = strtoupper(incu_texto($_GET['orden'] ?? 'RECIENTES'));
    $pagina = incu_entero_rango($_GET['pagina'] ?? 1, 1, 100000, 1);
    $porPagina = incu_entero_rango($_GET['por_pagina'] ?? 24, 1, 100, 24);

    if (!in_array($estado, ['TODOS', 'PENDIENTE', 'CUMPLIDO_TARDE', 'JUSTIFICADO', 'NO_REALIZADO'], true)) {
        $estado = 'TODOS';
    }

    if (!in_array($tipo, ['TODOS', 'CORRECTIVO_PROGRAMABLE', 'MODIFICACION_MEJORA', 'RUTINARIO'], true)) {
        $tipo = 'TODOS';
    }

    if (!in_array($prioridad, ['TODAS', 'BAJA', 'MEDIA', 'ALTA', 'URGENTE'], true)) {
        $prioridad = 'TODAS';
    }

    if (!in_array($orden, ['RECIENTES', 'MAYOR_ATRASO', 'ANTIGUOS', 'TECNICO', 'FOLIO'], true)) {
        $orden = 'RECIENTES';
    }

    if (!in_array($porPagina, [12, 24, 48], true)) {
        $porPagina = 24;
    }

    $desde = incu_fecha_opcional($_GET['desde'] ?? null, 'desde');
    $hasta = incu_fecha_opcional($_GET['hasta'] ?? null, 'hasta');

    if ($desde !== null && $hasta !== null && $desde > $hasta) {
        throw new IncuHttpException(
            'La fecha inicial no puede ser posterior a la fecha final.',
            422,
            ['campo' => 'desde']
        );
    }

    return [
        'q' => incu_recortar(incu_texto($_GET['q'] ?? ''), 120),
        'estado' => $estado,
        'tipo' => $tipo,
        'prioridad' => $prioridad,
        'tecnico_id' => incu_entero_opcional($_GET['tecnico_id'] ?? null),
        'desde' => $desde,
        'hasta' => $hasta,
        'orden' => $orden,
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
    ];
}

/**
 * @param array<string,mixed> $filtros
 * @return array{where:array<int,string>,params:array<string,mixed>}
 */
function incu_construir_condiciones(array $filtros, bool $incluirEstado): array
{
    $where = ["s.tipo_solicitud <> 'CORRECTIVO_URGENTE'"];
    $params = [];

    if ($incluirEstado && (string) $filtros['estado'] !== 'TODOS') {
        $where[] = 'im.estado = :f_estado';
        $params[':f_estado'] = (string) $filtros['estado'];
    }

    if ((string) $filtros['tipo'] !== 'TODOS') {
        $where[] = 's.tipo_solicitud = :f_tipo';
        $params[':f_tipo'] = (string) $filtros['tipo'];
    }

    if ((string) $filtros['prioridad'] !== 'TODAS') {
        $where[] = 's.prioridad = :f_prioridad';
        $params[':f_prioridad'] = (string) $filtros['prioridad'];
    }

    if ((int) $filtros['tecnico_id'] > 0) {
        $where[] = 'st.tecnico_id = :f_tecnico';
        $params[':f_tecnico'] = (int) $filtros['tecnico_id'];
    }

    if ($filtros['desde'] !== null) {
        $where[] = 'im.fecha_programada >= :f_desde';
        $params[':f_desde'] = (string) $filtros['desde'];
    }

    if ($filtros['hasta'] !== null) {
        $where[] = 'im.fecha_programada <= :f_hasta';
        $params[':f_hasta'] = (string) $filtros['hasta'];
    }

    $busqueda = (string) $filtros['q'];
    if ($busqueda !== '') {
        $where[] = "(
            s.folio LIKE :f_q1
            OR e.codigo_equipo LIKE :f_q2
            OR e.nombre_equipo LIKE :f_q3
            OR d.nombre LIKE :f_q4
            OR a.nombre LIKE :f_q5
            OR p.nombre LIKE :f_q6
            OR t.nombre LIKE :f_q7
            OR t.apellido_paterno LIKE :f_q8
            OR t.apellido_materno LIKE :f_q9
            OR t.usuario LIKE :f_q10
        )";
        for ($i = 1; $i <= 10; $i++) {
            $params[':f_q' . $i] = '%' . $busqueda . '%';
        }
    }

    return ['where' => $where, 'params' => $params];
}

function incu_orden_sql(string $orden): string
{
    switch ($orden) {
        case 'MAYOR_ATRASO':
            return 'minutos_atraso DESC, im.fecha_detectado DESC, im.id DESC';
        case 'ANTIGUOS':
            return 'im.fecha_detectado ASC, im.id ASC';
        case 'TECNICO':
            return 'tecnico ASC, im.fecha_detectado DESC';
        case 'FOLIO':
            return 's.folio ASC, tecnico ASC';
        case 'RECIENTES':
        default:
            return "CASE im.estado WHEN 'PENDIENTE' THEN 0 ELSE 1 END,
                    im.fecha_detectado DESC,
                    im.id DESC";
    }
}

/* =========================================================================
   REGISTROS DE AUDITORÍA Y NOTIFICACIONES
   ========================================================================= */

function incu_historial(
    PDO $conexion,
    int $solicitudId,
    int $asignacionId,
    int $programacionId,
    string $evento,
    string $estadoAnterior,
    string $estadoNuevo,
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
            :asignacion_id,
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
    $stmt->bindValue(':asignacion_id', $asignacionId, PDO::PARAM_INT);
    $stmt->bindValue(':programacion_id', $programacionId, PDO::PARAM_INT);
    $stmt->bindValue(':evento', $evento, PDO::PARAM_STR);
    $stmt->bindValue(':estado_anterior', $estadoAnterior, PDO::PARAM_STR);
    $stmt->bindValue(':estado_nuevo', $estadoNuevo, PDO::PARAM_STR);
    $stmt->bindValue(':actor_tipo', $adminId > 0 ? 'ADMIN' : 'SISTEMA', PDO::PARAM_STR);
    if ($adminId > 0) {
        $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':actor_id', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->execute();
}

function incu_movimiento(
    PDO $conexion,
    int $adminId,
    string $accion,
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
            'Cumplimiento',
            :descripcion,
            :tabla,
            :registro_id,
            :ip,
            :agente,
            NOW()
        )"
    );
    $stmt->execute([
        ':usuario_id' => $adminId,
        ':accion' => $accion,
        ':descripcion' => $descripcion,
        ':tabla' => $tabla,
        ':registro_id' => $registroId,
        ':ip' => incu_recortar((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 60),
        ':agente' => incu_recortar((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 255),
    ]);
}

/**
 * @param array<string,mixed> $anteriores
 * @param array<string,mixed> $nuevos
 */
function incu_auditoria(
    PDO $conexion,
    int $registroId,
    int $solicitudId,
    int $adminId,
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
            'incumplimientos_mantenimiento',
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
            NOW()
        )"
    );
    $stmt->execute([
        ':registro_id' => $registroId,
        ':solicitud_id' => $solicitudId,
        ':actor_id' => $adminId,
        ':motivo' => incu_recortar($motivo, 500),
        ':anteriores' => json_encode($anteriores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':nuevos' => json_encode($nuevos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':ip' => incu_recortar((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 45),
        ':agente' => incu_recortar((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 500),
    ]);
}

/**
 * @param array<string,mixed> $registro
 */
function incu_notificar_resolucion(
    PDO $conexion,
    array $registro,
    string $resolucion,
    string $motivo
): void {
    $titulo = $resolucion === 'JUSTIFICAR'
        ? 'Incumplimiento justificado'
        : 'Participación marcada como no realizada';
    $mensaje = $resolucion === 'JUSTIFICAR'
        ? 'El incumplimiento de ' . (string) $registro['folio']
            . ' fue justificado administrativamente. Motivo: ' . $motivo
        : 'Tu participación en ' . (string) $registro['folio']
            . ' fue marcada como no realizada. El mantenimiento queda pendiente de reprogramación. Motivo: '
            . $motivo;

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
            'TECNICO',
            :tecnico_id,
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
    $stmt->bindValue(':tecnico_id', (int) $registro['tecnico_id'], PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id', (int) $registro['solicitud_id'], PDO::PARAM_INT);
    if (!empty($registro['ejecucion_id'])) {
        $stmt->bindValue(':ejecucion_id', (int) $registro['ejecucion_id'], PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':ejecucion_id', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
    $stmt->bindValue(':mensaje', incu_recortar($mensaje, 1000), PDO::PARAM_STR);
    $stmt->bindValue(':tipo', $resolucion === 'JUSTIFICAR' ? 'INFO' : 'WARNING', PDO::PARAM_STR);
    $stmt->execute();
}

/* =========================================================================
   SEGURIDAD Y UTILIDADES
   ========================================================================= */

function incu_admin_id(): int
{
    $id = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        throw new IncuHttpException('La sesión administrativa no es válida.', 401);
    }

    return (int) $id;
}

function incu_validar_admin_activo(PDO $conexion, int $adminId): void
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
        throw new IncuHttpException(
            'Tu cuenta administrativa ya no está activa.',
            403,
            ['sesion_expirada' => true, 'redirect' => '../login.php?acceso=denegado']
        );
    }
}

function incu_hora_corte(PDO $conexion): string
{
    static $hora = null;

    if (is_string($hora)) {
        return $hora;
    }

    $stmt = $conexion->prepare(
        "SELECT valor
         FROM configuracion_sistema
         WHERE clave = 'HORA_CORTE_CUMPLIMIENTO'
         LIMIT 1"
    );
    $stmt->execute();
    $valor = trim((string) ($stmt->fetchColumn() ?: '23:59'));

    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $valor)) {
        $valor = '23:59';
    }

    $hora = $valor . ':00';
    return $hora;
}

function incu_adquirir_lock(PDO $conexion, string $nombre, int $segundos): bool
{
    try {
        $stmt = $conexion->prepare('SELECT GET_LOCK(:nombre, :segundos)');
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':segundos', $segundos, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn() === 1;
    } catch (Throwable $e) {
        error_log('[CUMPLIMIENTO][GET_LOCK] ' . $e->getMessage());
        return true;
    }
}

function incu_liberar_lock(PDO $conexion, string $nombre): void
{
    try {
        $stmt = $conexion->prepare('SELECT RELEASE_LOCK(:nombre)');
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('[CUMPLIMIENTO][RELEASE_LOCK] ' . $e->getMessage());
    }
}

function incu_texto($valor): string
{
    return sm_limpiar_texto($valor);
}

function incu_texto_validado(
    $valor,
    int $minimo,
    int $maximo,
    string $campo
): string {
    $texto = incu_texto($valor);
    $longitud = function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);

    if ($longitud < $minimo || $longitud > $maximo) {
        throw new IncuHttpException(
            'El ' . $campo . ' debe contener entre ' . $minimo . ' y ' . $maximo . ' caracteres.',
            422,
            ['campo' => $campo]
        );
    }

    return $texto;
}

function incu_recortar(string $texto, int $maximo): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, $maximo, 'UTF-8');
    }

    return substr($texto, 0, $maximo);
}

function incu_entero_positivo($valor, string $campo): int
{
    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($entero === false) {
        throw new IncuHttpException(
            'El campo ' . $campo . ' no es válido.',
            422,
            ['campo' => $campo]
        );
    }

    return (int) $entero;
}

function incu_entero_opcional($valor): int
{
    if ($valor === null || $valor === '') {
        return 0;
    }

    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    return $entero === false ? 0 : (int) $entero;
}

function incu_entero_rango($valor, int $minimo, int $maximo, int $default): int
{
    $entero = filter_var($valor, FILTER_VALIDATE_INT);

    if ($entero === false || (int) $entero < $minimo || (int) $entero > $maximo) {
        return $default;
    }

    return (int) $entero;
}

function incu_fecha_opcional($valor, string $campo): ?string
{
    $fecha = incu_texto($valor);

    if ($fecha === '') {
        return null;
    }

    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    $errores = DateTimeImmutable::getLastErrors();
    $valida = $objeto instanceof DateTimeImmutable
        && ($errores === false || (
            (int) ($errores['warning_count'] ?? 0) === 0
            && (int) ($errores['error_count'] ?? 0) === 0
        ))
        && $objeto->format('Y-m-d') === $fecha;

    if (!$valida) {
        throw new IncuHttpException(
            'La fecha del campo ' . $campo . ' no es válida.',
            422,
            ['campo' => $campo]
        );
    }

    return $fecha;
}

/**
 * @param array<string,mixed> $params
 */
function incu_bind_params(PDOStatement $stmt, array $params): void
{
    foreach ($params as $clave => $valor) {
        $stmt->bindValue(
            $clave,
            $valor,
            is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }
}

function incu_duracion_minutos(int $minutos): string
{
    $minutos = max(0, $minutos);
    $dias = intdiv($minutos, 1440);
    $resto = $minutos % 1440;
    $horas = intdiv($resto, 60);
    $mins = $resto % 60;
    $partes = [];

    if ($dias > 0) {
        $partes[] = $dias . ' día' . ($dias === 1 ? '' : 's');
    }
    if ($horas > 0) {
        $partes[] = $horas . ' h';
    }
    if ($mins > 0 || $partes === []) {
        $partes[] = $mins . ' min';
    }

    return implode(' ', array_slice($partes, 0, 2));
}

function incu_tipo_texto(string $tipo): string
{
    $mapa = [
        'CORRECTIVO_PROGRAMABLE' => 'Correctivo programable',
        'MODIFICACION_MEJORA' => 'Modificación o mejora',
        'CORRECTIVO_URGENTE' => 'Correctivo urgente',
        'RUTINARIO' => 'Rutinario',
    ];

    return $mapa[$tipo] ?? incu_etiqueta_enum($tipo);
}

function incu_estado_texto(string $estado): string
{
    $mapa = [
        'PENDIENTE' => 'Pendiente de resolver',
        'CUMPLIDO_TARDE' => 'Cumplido tarde',
        'JUSTIFICADO' => 'Justificado',
        'NO_REALIZADO' => 'No realizado',
    ];
 
    return $mapa[$estado] ?? incu_etiqueta_enum($estado);
}
 
function incu_etiqueta_enum(string $valor): string
{
    $texto = strtolower(str_replace('_', ' ', $valor));
    return $texto === '' ? '' : ucfirst($texto);
}

function incu_csv($valor): string
{
    $texto = (string) $valor;

    if ($texto !== '' && preg_match('/^[=+\-@]/', $texto)) {
        return "'" . $texto;
    }

    return $texto;
}