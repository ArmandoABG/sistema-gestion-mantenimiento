<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Calendario laboral - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| - Exclusivo para administradores activos.
| - Días base: lunes a viernes HÁBIL; sábado y domingo INHÁBIL.
| - HÁBIL_EXTRA permite abrir de forma excepcional un día no laborable.
| - No permite convertir en INHÁBIL una fecha pasada.
| - Permite consultar los mantenimientos de cada día.
| - Permite reprogramar trabajos uno por uno conservando sus técnicos.
| - Permite mover todos los trabajos programables al siguiente día hábil
|   mediante una operación atómica: todos se mueven o ninguno.
| - No permite cambiar la fecha de un mantenimiento que ya inició.
| - Los correctivos urgentes no quedan bloqueados ni se mueven desde aquí.
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
    error_log('[CALENDARIO LABORAL][CONFIGURACIÓN PDO] ' . $e->getMessage());
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = $metodo === 'GET'
    ? cal_texto($_GET['accion'] ?? 'inicial')
    : cal_texto($_POST['accion'] ?? '');

try {
    if ($metodo === 'GET') {
        if ($accion === 'inicial') {
            cal_endpoint_inicial($conexion);
        }

        if ($accion === 'dia') {
            cal_endpoint_dia($conexion);
        }

        if ($accion === 'fecha_destino') {
            cal_endpoint_fecha_destino($conexion);
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

    if ($accion === 'preparar_mes') {
        cal_endpoint_preparar_mes($conexion);
    }

    if ($accion === 'guardar_dia') {
        cal_endpoint_guardar_dia($conexion);
    }

    if ($accion === 'restaurar_dia') {
        cal_endpoint_restaurar_dia($conexion);
    }

    if ($accion === 'reprogramar_mantenimiento') {
        cal_endpoint_reprogramar_mantenimiento($conexion);
    }

    if ($accion === 'mover_todos_siguiente_habil') {
        cal_endpoint_mover_todos_siguiente_habil($conexion);
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

    $referencia = 'CAL-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][PDO] ' . $e->getMessage()
        . ' | Archivo: ' . $e->getFile()
        . ' | Línea: ' . $e->getLine()
    );

    if ((string) $e->getCode() === '23000') {
        sm_responder_json(
            false,
            'El calendario cambió mientras realizabas la operación. Actualiza la pantalla e inténtalo otra vez.',
            ['referencia' => $referencia],
            409
        );
    }

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar el calendario laboral.',
        ['referencia' => $referencia],
        500
    );
} catch (RuntimeException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    sm_responder_json(false, $e->getMessage(), [], 409);
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'CAL-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][GENERAL] ' . $e->getMessage()
        . ' | Archivo: ' . $e->getFile()
        . ' | Línea: ' . $e->getLine()
    );

    sm_responder_json(
        false,
        'No fue posible completar la operación.',
        ['referencia' => $referencia],
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function cal_endpoint_inicial(PDO $conexion): void
{
    $adminId = cal_admin_id();
    cal_validar_admin_activo($conexion, $adminId);

    $mes = cal_mes($_GET['mes'] ?? date('Y-m'));

    $conexion->beginTransaction();
    $insertados = cal_asegurar_mes($conexion, $mes, $adminId);

    if ($insertados > 0) {
        cal_movimiento(
            $conexion,
            $adminId,
            'CONFIGURAR_MES_LABORAL',
            'Se preparó el calendario laboral del mes ' . $mes
                . ' con ' . $insertados . ' día(s) nuevo(s).',
            null
        );
    }

    $conexion->commit();

    sm_responder_json(
        true,
        'Calendario laboral cargado correctamente.',
        cal_respuesta_mes($conexion, $mes, $insertados)
    );
}

function cal_endpoint_preparar_mes(PDO $conexion): void
{
    $adminId = cal_admin_id();
    cal_validar_admin_activo($conexion, $adminId);

    $mes = cal_mes($_POST['mes'] ?? '');

    $conexion->beginTransaction();
    $insertados = cal_asegurar_mes($conexion, $mes, $adminId);

    cal_movimiento(
        $conexion,
        $adminId,
        'CONFIGURAR_MES_LABORAL',
        $insertados > 0
            ? 'Se preparó el calendario laboral del mes ' . $mes
                . ' con ' . $insertados . ' día(s) nuevo(s).'
            : 'Se verificó el calendario laboral del mes ' . $mes
                . '; ya estaba completo.',
        null
    );

    $conexion->commit();

    sm_responder_json(
        true,
        $insertados > 0
            ? 'El mes quedó preparado correctamente.'
            : 'El mes ya estaba preparado.',
        cal_respuesta_mes($conexion, $mes, $insertados)
    );
}

function cal_endpoint_dia(PDO $conexion): void
{
    $adminId = cal_admin_id();
    cal_validar_admin_activo($conexion, $adminId);

    $fecha = cal_fecha($_GET['fecha'] ?? '', 'fecha');
    $mes = substr($fecha, 0, 7);

    $conexion->beginTransaction();
    cal_asegurar_mes($conexion, $mes, $adminId);
    $conexion->commit();

    $dia = cal_consultar_dia($conexion, $fecha);

    if (!$dia) {
        sm_responder_json(
            false,
            'No fue posible localizar el día solicitado.',
            [],
            404
        );
    }

    $mantenimientos = cal_consultar_mantenimientos_fecha($conexion, $fecha);

    sm_responder_json(
        true,
        'Información del día cargada.',
        [
            'dia' => $dia,
            'mantenimientos' => $mantenimientos,
            'resumen_mantenimientos' => cal_resumen_mantenimientos($mantenimientos),
            'siguiente_dia_habil' => cal_siguiente_dia_habil($conexion, $fecha),
            'fecha_servidor' => date('Y-m-d'),
        ]
    );
}

function cal_endpoint_fecha_destino(PDO $conexion): void
{
    $adminId = cal_admin_id();
    cal_validar_admin_activo($conexion, $adminId);

    $fecha = cal_fecha($_GET['fecha'] ?? '', 'fecha');

    sm_responder_json(
        true,
        'Fecha de destino consultada.',
        [
            'calendario' => cal_estado_fecha($conexion, $fecha),
            'fecha_servidor' => date('Y-m-d'),
        ]
    );
}

function cal_endpoint_reprogramar_mantenimiento(PDO $conexion): void
{
    $adminId = cal_admin_id();
    cal_validar_admin_activo($conexion, $adminId);

    $solicitudId = cal_entero_positivo(
        $_POST['solicitud_id'] ?? null,
        'solicitud_id'
    );
    $fechaOrigen = cal_fecha($_POST['fecha_origen'] ?? '', 'fecha_origen');
    $fechaDestino = cal_fecha($_POST['fecha_destino'] ?? '', 'fecha_destino');
    $motivo = cal_texto_limitado(
        $_POST['motivo_reprogramacion'] ?? '',
        10,
        500,
        'motivo_reprogramacion'
    );

    cal_validar_fecha_destino($conexion, $fechaOrigen, $fechaDestino);

    $conexion->beginTransaction();
    cal_bloquear_y_validar_destino(
        $conexion,
        $fechaOrigen,
        $fechaDestino,
        $adminId
    );

    $resultado = cal_reprogramar_solicitud(
        $conexion,
        $solicitudId,
        $fechaOrigen,
        $fechaDestino,
        $adminId,
        $motivo,
        'INDIVIDUAL'
    );

    $conexion->commit();

    $respuesta = cal_respuesta_despues_movimiento(
        $conexion,
        $fechaOrigen,
        $resultado
    );

    sm_responder_json(
        true,
        'El mantenimiento fue reprogramado correctamente.',
        $respuesta
    );
}

function cal_endpoint_mover_todos_siguiente_habil(PDO $conexion): void
{
    $adminId = cal_admin_id();
    cal_validar_admin_activo($conexion, $adminId);

    $fechaOrigen = cal_fecha($_POST['fecha_origen'] ?? '', 'fecha_origen');
    $motivo = cal_texto_limitado(
        $_POST['motivo_reprogramacion'] ?? '',
        10,
        500,
        'motivo_reprogramacion'
    );

    $siguienteHabil = cal_siguiente_dia_habil($conexion, $fechaOrigen);
    $fechaDestino = (string) $siguienteHabil['fecha'];
    cal_validar_fecha_destino($conexion, $fechaOrigen, $fechaDestino);

    $conexion->beginTransaction();

    cal_asegurar_mes($conexion, substr($fechaOrigen, 0, 7), $adminId);
    cal_bloquear_y_validar_destino(
        $conexion,
        $fechaOrigen,
        $fechaDestino,
        $adminId
    );

    $mantenimientos = cal_consultar_mantenimientos_bloqueantes(
        $conexion,
        $fechaOrigen,
        false
    );

    if ($mantenimientos === []) {
        cal_cancelar_operacion(
            $conexion,
            'No hay mantenimientos programables vigentes para mover en esta fecha.',
            409
        );
    }

    $bloqueados = array_values(array_filter(
        $mantenimientos,
        static function (array $item): bool {
            return !empty($item['iniciado']) || (int) ($item['total_tecnicos'] ?? 0) < 1;
        }
    ));

    if ($bloqueados !== []) {
        cal_cancelar_operacion(
            $conexion,
            'No se movió ningún mantenimiento porque uno o más trabajos ya iniciaron o no tienen una asignación válida. Revisa los elementos señalados y reprograma individualmente.',
            409,
            [
                'bloqueados' => $bloqueados,
                'requiere_revision' => true,
            ]
        );
    }

    $movidos = [];
    foreach ($mantenimientos as $mantenimiento) {
        $movidos[] = cal_reprogramar_solicitud(
            $conexion,
            (int) $mantenimiento['solicitud_id'],
            $fechaOrigen,
            $fechaDestino,
            $adminId,
            $motivo,
            'MASIVA'
        );
    }

    cal_movimiento(
        $conexion,
        $adminId,
        'MOVER_MANTENIMIENTOS_DIA_HABIL',
        'Se movieron ' . count($movidos) . ' mantenimiento(s) del día '
            . $fechaOrigen . ' al siguiente día hábil ' . $fechaDestino
            . '. Motivo: ' . $motivo,
        null
    );

    $conexion->commit();

    $resultado = [
        'fecha_origen' => $fechaOrigen,
        'fecha_destino' => $fechaDestino,
        'total_movidos' => count($movidos),
        'movidos' => $movidos,
    ];

    sm_responder_json(
        true,
        count($movidos) === 1
            ? 'El mantenimiento fue movido al siguiente día hábil.'
            : 'Los mantenimientos fueron movidos al siguiente día hábil.',
        cal_respuesta_despues_movimiento($conexion, $fechaOrigen, $resultado)
    );
}

function cal_endpoint_guardar_dia(PDO $conexion): void
{
    $adminId = cal_admin_id();
    cal_validar_admin_activo($conexion, $adminId);

    $fecha = cal_fecha($_POST['fecha'] ?? '', 'fecha');
    $tipoDia = strtoupper(cal_texto($_POST['tipo_dia'] ?? ''));
    $motivo = cal_texto_limitado($_POST['motivo'] ?? '', 0, 200, 'motivo');

    if (!in_array($tipoDia, ['HABIL', 'INHABIL', 'HABIL_EXTRA'], true)) {
        sm_responder_json(
            false,
            'Selecciona un tipo de día válido.',
            ['campo' => 'tipo_dia'],
            422
        );
    }

    if ($fecha < date('Y-m-d')) {
        sm_responder_json(
            false,
            'Los días anteriores a hoy son históricos y ya no pueden modificarse.',
            ['campo' => 'fecha'],
            422
        );
    }

    if (
        in_array($tipoDia, ['INHABIL', 'HABIL_EXTRA'], true)
        && cal_longitud($motivo) < 5
    ) {
        sm_responder_json(
            false,
            $tipoDia === 'INHABIL'
                ? 'Escribe el motivo por el que el día será inhábil.'
                : 'Escribe el motivo por el que se habilitará de forma extraordinaria.',
            ['campo' => 'motivo'],
            422
        );
    }

    $mes = substr($fecha, 0, 7);

    $conexion->beginTransaction();
    cal_asegurar_mes($conexion, $mes, $adminId);

    $anterior = cal_bloquear_dia($conexion, $fecha);
    if (!$anterior) {
        throw new RuntimeException('El día ya no se encuentra disponible.');
    }

    if ($tipoDia === 'INHABIL') {
        $bloqueados = cal_consultar_mantenimientos_bloqueantes(
            $conexion,
            $fecha,
            true
        );

        if ($bloqueados !== []) {
            $conexion->rollBack();
            $mantenimientos = cal_consultar_mantenimientos_fecha(
                $conexion,
                $fecha
            );

            sm_responder_json(
                false,
                'No puedes marcar el día como inhábil porque tiene mantenimientos normales vigentes. Reprográmalos primero o muévelos al siguiente día hábil. Los urgentes no se bloquean.',
                [
                    'campo' => 'tipo_dia',
                    'requiere_reprogramacion' => true,
                    'mantenimientos' => $mantenimientos,
                    'resumen_mantenimientos' => cal_resumen_mantenimientos($mantenimientos),
                    'siguiente_dia_habil' => cal_siguiente_dia_habil($conexion, $fecha),
                ],
                409
            );
        }
    }

    $esHabil = $tipoDia === 'INHABIL' ? 0 : 1;

    $stmt = $conexion->prepare(
        "UPDATE calendario_laboral
         SET es_habil = :es_habil,
             tipo_dia = :tipo_dia,
             motivo = :motivo,
             modificado_por_admin_id = :admin_id,
             fecha_actualizacion = NOW()
         WHERE fecha = :fecha"
    );
    $stmt->bindValue(':es_habil', $esHabil, PDO::PARAM_INT);
    $stmt->bindValue(':tipo_dia', $tipoDia, PDO::PARAM_STR);
    cal_bind_nullable($stmt, ':motivo', $motivo);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
    $stmt->execute();

    $descripcion = 'Se configuró el día ' . $fecha . ' como '
        . $tipoDia
        . ($motivo !== '' ? '. Motivo: ' . $motivo : '.');

    cal_movimiento(
        $conexion,
        $adminId,
        'ACTUALIZAR_DIA_LABORAL',
        $descripcion,
        (int) $anterior['id']
    );

    $conexion->commit();

    sm_responder_json(
        true,
        'El día fue actualizado correctamente.',
        cal_respuesta_mes($conexion, $mes, 0)
    );
}

function cal_endpoint_restaurar_dia(PDO $conexion): void
{
    $adminId = cal_admin_id();
    cal_validar_admin_activo($conexion, $adminId);

    $fecha = cal_fecha($_POST['fecha'] ?? '', 'fecha');

    if ($fecha < date('Y-m-d')) {
        sm_responder_json(
            false,
            'Los días anteriores a hoy son históricos y ya no pueden modificarse.',
            ['campo' => 'fecha'],
            422
        );
    }

    $fechaObjeto = new DateTimeImmutable($fecha);
    $finSemana = (int) $fechaObjeto->format('N') >= 6;
    $tipoDia = $finSemana ? 'INHABIL' : 'HABIL';
    $esHabil = $finSemana ? 0 : 1;
    $motivo = $finSemana ? 'Fin de semana' : '';
    $mes = substr($fecha, 0, 7);

    $conexion->beginTransaction();
    cal_asegurar_mes($conexion, $mes, $adminId);

    $anterior = cal_bloquear_dia($conexion, $fecha);
    if (!$anterior) {
        throw new RuntimeException('El día ya no se encuentra disponible.');
    }

    if ($tipoDia === 'INHABIL') {
        $bloqueados = cal_consultar_mantenimientos_bloqueantes(
            $conexion,
            $fecha,
            true
        );

        if ($bloqueados !== []) {
            $conexion->rollBack();
            $mantenimientos = cal_consultar_mantenimientos_fecha(
                $conexion,
                $fecha
            );

            sm_responder_json(
                false,
                'No se puede restaurar la regla base porque el día tiene mantenimientos normales vigentes. Reprográmalos primero o muévelos al siguiente día hábil.',
                [
                    'requiere_reprogramacion' => true,
                    'mantenimientos' => $mantenimientos,
                    'resumen_mantenimientos' => cal_resumen_mantenimientos($mantenimientos),
                    'siguiente_dia_habil' => cal_siguiente_dia_habil($conexion, $fecha),
                ],
                409
            );
        }
    }

    $stmt = $conexion->prepare(
        "UPDATE calendario_laboral
         SET es_habil = :es_habil,
             tipo_dia = :tipo_dia,
             motivo = :motivo,
             modificado_por_admin_id = :admin_id,
             fecha_actualizacion = NOW()
         WHERE fecha = :fecha"
    );
    $stmt->bindValue(':es_habil', $esHabil, PDO::PARAM_INT);
    $stmt->bindValue(':tipo_dia', $tipoDia, PDO::PARAM_STR);
    cal_bind_nullable($stmt, ':motivo', $motivo);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
    $stmt->execute();

    cal_movimiento(
        $conexion,
        $adminId,
        'RESTAURAR_DIA_LABORAL',
        'Se restauró la regla base del día ' . $fecha . ' como '
            . $tipoDia . '.',
        (int) $anterior['id']
    );

    $conexion->commit();

    sm_responder_json(
        true,
        'El día volvió a su configuración base.',
        cal_respuesta_mes($conexion, $mes, 0)
    );
}

/* =========================================================================
   PREPARACIÓN Y CONSULTAS DEL CALENDARIO
   ========================================================================= */

function cal_respuesta_mes(PDO $conexion, string $mes, int $insertados): array
{
    $dias = cal_consultar_mes($conexion, $mes);

    return [
        'csrf_token' => sm_token_csrf(),
        'mes' => $mes,
        'titulo_mes' => cal_nombre_mes($mes),
        'dias' => $dias,
        'resumen' => cal_resumen_mes($dias),
        'insertados' => $insertados,
        'reglas' => [
            'lunes_viernes' => 'HABIL',
            'sabado_domingo' => 'INHABIL',
            'urgentes_ignoran_calendario' => true,
            'bloquear_inhabil_con_programaciones' => true,
            'editar_fechas_pasadas' => false,
            'reprogramar_desde_calendario' => true,
            'mover_todos_siguiente_habil' => true,
            'movimiento_masivo_atomico' => true,
        ],
        'fecha_servidor' => date('Y-m-d'),
        'fecha_hora_servidor' => date('Y-m-d H:i:s'),
    ];
}

function cal_asegurar_mes(
    PDO $conexion,
    string $mes,
    int $adminId
): int {
    $inicio = new DateTimeImmutable($mes . '-01');
    $fin = $inicio->modify('last day of this month');

    $stmt = $conexion->prepare(
        "INSERT IGNORE INTO calendario_laboral
        (
            fecha,
            anio,
            mes,
            es_habil,
            tipo_dia,
            motivo,
            creado_por_admin_id,
            modificado_por_admin_id,
            fecha_registro,
            fecha_actualizacion
        )
        VALUES
        (
            :fecha,
            :anio,
            :mes,
            :es_habil,
            :tipo_dia,
            :motivo,
            :admin_id,
            NULL,
            NOW(),
            NOW()
        )"
    );

    $insertados = 0;
    $fecha = $inicio;

    while ($fecha <= $fin) {
        $numeroDia = (int) $fecha->format('N');
        $finSemana = $numeroDia >= 6;
        $tipoDia = $finSemana ? 'INHABIL' : 'HABIL';
        $motivo = $finSemana ? 'Fin de semana' : '';

        $stmt->bindValue(':fecha', $fecha->format('Y-m-d'), PDO::PARAM_STR);
        $stmt->bindValue(':anio', (int) $fecha->format('Y'), PDO::PARAM_INT);
        $stmt->bindValue(':mes', (int) $fecha->format('n'), PDO::PARAM_INT);
        $stmt->bindValue(':es_habil', $finSemana ? 0 : 1, PDO::PARAM_INT);
        $stmt->bindValue(':tipo_dia', $tipoDia, PDO::PARAM_STR);
        cal_bind_nullable($stmt, ':motivo', $motivo);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->execute();

        $insertados += $stmt->rowCount();
        $fecha = $fecha->modify('+1 day');
    }

    return $insertados;
}

function cal_consultar_mes(PDO $conexion, string $mes): array
{
    $inicio = $mes . '-01';
    $fin = (new DateTimeImmutable($inicio))
        ->modify('last day of this month')
        ->format('Y-m-d');

    $stmt = $conexion->prepare(
        "SELECT
            c.id,
            c.fecha,
            c.anio,
            c.mes,
            c.es_habil,
            c.tipo_dia,
            c.motivo,
            c.creado_por_admin_id,
            c.modificado_por_admin_id,
            c.fecha_registro,
            c.fecha_actualizacion,
            COALESCE(p.total_programados, 0) AS total_programados,
            COALESCE(p.total_no_urgentes, 0) AS total_no_urgentes,
            COALESCE(p.total_urgentes, 0) AS total_urgentes,
            COALESCE(p.total_iniciados, 0) AS total_iniciados
         FROM calendario_laboral c
         LEFT JOIN
         (
             SELECT
                pm.fecha_programada,
                COUNT(*) AS total_programados,
                SUM(s.tipo_solicitud <> 'CORRECTIVO_URGENTE') AS total_no_urgentes,
                SUM(s.tipo_solicitud = 'CORRECTIVO_URGENTE') AS total_urgentes,
                SUM(
                    EXISTS
                    (
                        SELECT 1
                        FROM ejecuciones_mantenimiento em
                        WHERE em.solicitud_id = s.id
                          AND (
                              em.fecha_hora_inicio IS NOT NULL
                              OR em.estado IN ('EN_PROCESO','PAUSADA','TERMINADA')
                          )
                    )
                ) AS total_iniciados
             FROM programaciones_mantenimiento pm
             INNER JOIN solicitudes s ON s.id = pm.solicitud_id
             WHERE pm.es_actual = 1
               AND pm.estado IN ('PROGRAMADA','VENCIDA')
               AND s.activo = 1
               AND s.estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')
               AND pm.fecha_programada BETWEEN :inicio_programacion AND :fin_programacion
             GROUP BY pm.fecha_programada
         ) p ON p.fecha_programada = c.fecha
         WHERE c.fecha BETWEEN :inicio AND :fin
         ORDER BY c.fecha"
    );
    $stmt->bindValue(':inicio_programacion', $inicio, PDO::PARAM_STR);
    $stmt->bindValue(':fin_programacion', $fin, PDO::PARAM_STR);
    $stmt->bindValue(':inicio', $inicio, PDO::PARAM_STR);
    $stmt->bindValue(':fin', $fin, PDO::PARAM_STR);
    $stmt->execute();

    $dias = [];
    foreach ($stmt->fetchAll() as $fila) {
        $fechaObjeto = new DateTimeImmutable((string) $fila['fecha']);
        $dias[] = [
            'id' => (int) $fila['id'],
            'fecha' => (string) $fila['fecha'],
            'numero' => (int) $fechaObjeto->format('j'),
            'dia_semana' => (int) $fechaObjeto->format('N'),
            'dia_semana_texto' => cal_nombre_dia($fechaObjeto),
            'es_habil' => (int) $fila['es_habil'] === 1,
            'tipo_dia' => (string) $fila['tipo_dia'],
            'motivo' => $fila['motivo'],
            'total_programados' => (int) $fila['total_programados'],
            'total_no_urgentes' => (int) $fila['total_no_urgentes'],
            'total_urgentes' => (int) $fila['total_urgentes'],
            'total_iniciados' => (int) $fila['total_iniciados'],
            'es_hoy' => (string) $fila['fecha'] === date('Y-m-d'),
            'es_pasado' => (string) $fila['fecha'] < date('Y-m-d'),
            'fecha_actualizacion' => $fila['fecha_actualizacion'],
        ];
    }

    return $dias;
}

function cal_consultar_dia(PDO $conexion, string $fecha): ?array
{
    $stmt = $conexion->prepare(
        "SELECT
            c.*,
            TRIM(CONCAT_WS(' ', ac.nombre, ac.apellido_paterno, ac.apellido_materno)) AS creado_por,
            TRIM(CONCAT_WS(' ', am.nombre, am.apellido_paterno, am.apellido_materno)) AS modificado_por
         FROM calendario_laboral c
         LEFT JOIN administradores ac ON ac.id = c.creado_por_admin_id
         LEFT JOIN administradores am ON am.id = c.modificado_por_admin_id
         WHERE c.fecha = :fecha
         LIMIT 1"
    );
    $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
    $stmt->execute();
    $fila = $stmt->fetch();

    if (!$fila) {
        return null;
    }

    $objeto = new DateTimeImmutable($fecha);

    return [
        'id' => (int) $fila['id'],
        'fecha' => $fecha,
        'fecha_texto' => cal_fecha_es($fecha),
        'numero' => (int) $objeto->format('j'),
        'dia_semana' => (int) $objeto->format('N'),
        'dia_semana_texto' => cal_nombre_dia($objeto),
        'es_habil' => (int) $fila['es_habil'] === 1,
        'tipo_dia' => (string) $fila['tipo_dia'],
        'motivo' => $fila['motivo'],
        'es_hoy' => $fecha === date('Y-m-d'),
        'es_pasado' => $fecha < date('Y-m-d'),
        'creado_por' => $fila['creado_por'],
        'modificado_por' => $fila['modificado_por'],
        'fecha_registro' => $fila['fecha_registro'],
        'fecha_actualizacion' => $fila['fecha_actualizacion'],
    ];
}

function cal_consultar_mantenimientos_fecha(
    PDO $conexion,
    string $fecha
): array {
    $stmt = $conexion->prepare(
        "SELECT
            s.id AS solicitud_id,
            s.folio,
            s.tipo_solicitud,
            s.estado,
            s.prioridad,
            pm.id AS programacion_id,
            pm.fecha_programada,
            e.codigo_equipo,
            e.nombre_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            COUNT(DISTINCT CASE WHEN st.activo = 1 THEN st.id END) AS total_tecnicos,
            GROUP_CONCAT(
                DISTINCT CASE
                    WHEN st.activo = 1 THEN TRIM(CONCAT_WS(
                        ' ',
                        t.nombre,
                        t.apellido_paterno,
                        t.apellido_materno
                    ))
                    ELSE NULL
                END
                ORDER BY t.nombre, t.apellido_paterno
                SEPARATOR ', '
            ) AS tecnicos,
            COUNT(DISTINCT CASE
                WHEN em.fecha_hora_inicio IS NOT NULL
                  OR em.estado IN ('EN_PROCESO','PAUSADA','TERMINADA')
                THEN em.id
                ELSE NULL
            END) AS total_iniciados
         FROM programaciones_mantenimiento pm
         INNER JOIN solicitudes s ON s.id = pm.solicitud_id
         INNER JOIN equipos e ON e.id = s.equipo_id
         INNER JOIN departamentos d ON d.id = s.departamento_id
         INNER JOIN areas a ON a.id = s.area_id
         LEFT JOIN solicitud_tecnicos st
                ON st.solicitud_id = s.id
               AND st.programacion_id = pm.id
               AND st.origen = 'ADMIN'
               AND st.activo = 1
         LEFT JOIN tecnicos t ON t.id = st.tecnico_id
         LEFT JOIN ejecuciones_mantenimiento em
                ON em.solicitud_tecnico_id = st.id
         WHERE pm.fecha_programada = :fecha
           AND pm.es_actual = 1
           AND pm.estado IN ('PROGRAMADA','VENCIDA')
           AND s.activo = 1
           AND s.estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')
         GROUP BY
            s.id,
            s.folio,
            s.tipo_solicitud,
            s.estado,
            s.prioridad,
            pm.id,
            pm.fecha_programada,
            e.codigo_equipo,
            e.nombre_equipo,
            d.nombre,
            a.nombre
         ORDER BY
            CASE s.prioridad
                WHEN 'URGENTE' THEN 0
                WHEN 'ALTA' THEN 1
                WHEN 'MEDIA' THEN 2
                ELSE 3
            END,
            s.folio"
    );
    $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
    $stmt->execute();

    $items = [];
    foreach ($stmt->fetchAll() as $fila) {
        $esUrgente = (string) $fila['tipo_solicitud'] === 'CORRECTIVO_URGENTE';
        $totalIniciados = (int) $fila['total_iniciados'];
        $totalTecnicos = (int) $fila['total_tecnicos'];
        $estadoSolicitud = (string) $fila['estado'];
        $estadoPermitido = in_array(
            $estadoSolicitud,
            ['APROBADO', 'AGENDADO', 'ATRASADO'],
            true
        );

        $puedeReprogramar = !$esUrgente
            && $totalIniciados === 0
            && $totalTecnicos > 0
            && $estadoPermitido;

        $motivoBloqueo = '';
        if ($esUrgente) {
            $motivoBloqueo = 'Los correctivos urgentes son independientes del calendario laboral.';
        } elseif ($totalIniciados > 0) {
            $motivoBloqueo = 'El mantenimiento ya fue iniciado y su fecha quedó bloqueada.';
        } elseif ($totalTecnicos < 1) {
            $motivoBloqueo = 'No tiene técnicos activos. Ábrelo en Programar y asignar para corregirlo.';
        } elseif (!$estadoPermitido) {
            $motivoBloqueo = 'El estado actual no permite reprogramarlo desde el calendario.';
        }

        $items[] = [
            'solicitud_id' => (int) $fila['solicitud_id'],
            'programacion_id' => (int) $fila['programacion_id'],
            'folio' => (string) $fila['folio'],
            'tipo_solicitud' => (string) $fila['tipo_solicitud'],
            'estado' => $estadoSolicitud,
            'prioridad' => (string) $fila['prioridad'],
            'fecha_programada' => (string) $fila['fecha_programada'],
            'codigo_equipo' => (string) $fila['codigo_equipo'],
            'nombre_equipo' => (string) $fila['nombre_equipo'],
            'departamento' => (string) $fila['departamento'],
            'area' => (string) $fila['area'],
            'total_tecnicos' => $totalTecnicos,
            'tecnicos' => (string) ($fila['tecnicos'] ?? ''),
            'total_iniciados' => $totalIniciados,
            'es_urgente' => $esUrgente,
            'bloquea_inhabil' => !$esUrgente,
            'puede_reprogramar' => $puedeReprogramar,
            'motivo_bloqueo' => $motivoBloqueo,
            'url_programacion' => 'solicitudes_programacion.php?solicitud_id='
                . (int) $fila['solicitud_id'],
        ];
    }

    return $items;
}

function cal_consultar_mantenimientos_bloqueantes(
    PDO $conexion,
    string $fecha,
    bool $bloquear
): array {
    $sql = "SELECT
                s.id AS solicitud_id,
                s.folio,
                s.tipo_solicitud,
                s.estado,
                s.prioridad,
                pm.id AS programacion_id,
                e.codigo_equipo,
                e.nombre_equipo,
                EXISTS
                (
                    SELECT 1
                    FROM ejecuciones_mantenimiento em
                    WHERE em.solicitud_id = s.id
                      AND (
                          em.fecha_hora_inicio IS NOT NULL
                          OR em.estado IN ('EN_PROCESO','PAUSADA','TERMINADA')
                      )
                ) AS iniciado,
                (
                    SELECT COUNT(*)
                    FROM solicitud_tecnicos st
                    WHERE st.solicitud_id = s.id
                      AND st.programacion_id = pm.id
                      AND st.origen = 'ADMIN'
                      AND st.activo = 1
                ) AS total_tecnicos
            FROM programaciones_mantenimiento pm
            INNER JOIN solicitudes s ON s.id = pm.solicitud_id
            INNER JOIN equipos e ON e.id = s.equipo_id
            WHERE pm.fecha_programada = :fecha
              AND pm.es_actual = 1
              AND pm.estado IN ('PROGRAMADA','VENCIDA')
              AND s.activo = 1
              AND s.estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')
              AND s.tipo_solicitud <> 'CORRECTIVO_URGENTE'
            ORDER BY s.id";

    if ($bloquear) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
    $stmt->execute();

    $items = [];
    foreach ($stmt->fetchAll() as $fila) {
        $items[] = [
            'solicitud_id' => (int) $fila['solicitud_id'],
            'programacion_id' => (int) $fila['programacion_id'],
            'folio' => (string) $fila['folio'],
            'tipo_solicitud' => (string) $fila['tipo_solicitud'],
            'estado' => (string) $fila['estado'],
            'prioridad' => (string) $fila['prioridad'],
            'codigo_equipo' => (string) $fila['codigo_equipo'],
            'nombre_equipo' => (string) $fila['nombre_equipo'],
            'iniciado' => (int) $fila['iniciado'] === 1,
            'total_tecnicos' => (int) $fila['total_tecnicos'],
            'url' => 'solicitudes_programacion.php?solicitud_id=' . (int) $fila['solicitud_id'],
        ];
    }

    return $items;
}

function cal_bloquear_dia(PDO $conexion, string $fecha): ?array
{
    $stmt = $conexion->prepare(
        "SELECT *
         FROM calendario_laboral
         WHERE fecha = :fecha
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
    $stmt->execute();
    $fila = $stmt->fetch();

    return $fila ?: null;
}

function cal_resumen_mes(array $dias): array
{
    $resumen = [
        'total' => count($dias),
        'habiles' => 0,
        'inhabiles' => 0,
        'habiles_extra' => 0,
        'con_programaciones' => 0,
        'mantenimientos_programados' => 0,
    ];

    foreach ($dias as $dia) {
        if ($dia['tipo_dia'] === 'INHABIL') {
            $resumen['inhabiles']++;
        } elseif ($dia['tipo_dia'] === 'HABIL_EXTRA') {
            $resumen['habiles_extra']++;
        } else {
            $resumen['habiles']++;
        }

        if ((int) $dia['total_programados'] > 0) {
            $resumen['con_programaciones']++;
            $resumen['mantenimientos_programados'] += (int) $dia['total_programados'];
        }
    }

    return $resumen;
}

/* =========================================================================
   REPROGRAMACIÓN DESDE EL CALENDARIO
   ========================================================================= */

function cal_respuesta_despues_movimiento(
    PDO $conexion,
    string $fechaOrigen,
    array $resultado
): array {
    $mes = substr($fechaOrigen, 0, 7);
    $respuesta = cal_respuesta_mes($conexion, $mes, 0);
    $mantenimientos = cal_consultar_mantenimientos_fecha($conexion, $fechaOrigen);

    $respuesta['dia'] = cal_consultar_dia($conexion, $fechaOrigen);
    $respuesta['mantenimientos'] = $mantenimientos;
    $respuesta['resumen_mantenimientos'] = cal_resumen_mantenimientos($mantenimientos);
    $respuesta['siguiente_dia_habil'] = cal_siguiente_dia_habil(
        $conexion,
        $fechaOrigen
    );
    $respuesta['resultado'] = $resultado;

    return $respuesta;
}

function cal_resumen_mantenimientos(array $items): array
{
    $resumen = [
        'total' => count($items),
        'urgentes' => 0,
        'programables' => 0,
        'reprogramables' => 0,
        'bloqueados' => 0,
        'iniciados' => 0,
    ];

    foreach ($items as $item) {
        if (!empty($item['es_urgente'])) {
            $resumen['urgentes']++;
            continue;
        }

        $resumen['programables']++;

        if (!empty($item['puede_reprogramar'])) {
            $resumen['reprogramables']++;
        } else {
            $resumen['bloqueados']++;
        }

        if ((int) ($item['total_iniciados'] ?? 0) > 0) {
            $resumen['iniciados']++;
        }
    }

    return $resumen;
}

function cal_estado_fecha(PDO $conexion, string $fecha): array
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
            'fecha_texto' => cal_fecha_es($fecha),
            'configurado' => false,
            'origen' => 'REGLA_BASE',
            'permitido' => !$finSemana,
            'es_habil' => !$finSemana,
            'tipo_dia' => $finSemana ? 'INHABIL' : 'HABIL',
            'motivo' => $finSemana ? 'Fin de semana' : null,
            'mensaje' => $finSemana
                ? 'Sábado o domingo bloqueado por la regla base. Puede abrirse como HÁBIL_EXTRA.'
                : 'Día hábil disponible por la regla base de lunes a viernes.',
        ];
    }

    $permitido = (int) $fila['es_habil'] === 1
        && (string) $fila['tipo_dia'] !== 'INHABIL';

    return [
        'fecha' => $fecha,
        'fecha_texto' => cal_fecha_es($fecha),
        'configurado' => true,
        'origen' => 'CALENDARIO',
        'permitido' => $permitido,
        'es_habil' => $permitido,
        'tipo_dia' => (string) $fila['tipo_dia'],
        'motivo' => $fila['motivo'],
        'mensaje' => $permitido
            ? ((string) $fila['tipo_dia'] === 'HABIL_EXTRA'
                ? 'Día habilitado de manera extraordinaria.'
                : 'Día hábil disponible para programación.')
            : 'La fecha está marcada como día inhábil'
                . (!empty($fila['motivo']) ? ': ' . (string) $fila['motivo'] : '.'),
    ];
}

function cal_siguiente_dia_habil(PDO $conexion, string $fechaOrigen): array
{
    $origen = new DateTimeImmutable($fechaOrigen);
    $candidata = $origen->modify('+1 day');
    $hoy = new DateTimeImmutable('today');

    if ($candidata < $hoy) {
        $candidata = $hoy;
    }

    for ($i = 0; $i < 730; $i++) {
        $fecha = $candidata->modify('+' . $i . ' day')->format('Y-m-d');
        $estado = cal_estado_fecha($conexion, $fecha);

        if (!empty($estado['permitido'])) {
            return $estado;
        }
    }

    throw new RuntimeException(
        'No se encontró un día hábil disponible dentro de los próximos dos años.'
    );
}

function cal_validar_fecha_destino(
    PDO $conexion,
    string $fechaOrigen,
    string $fechaDestino
): void {
    if ($fechaDestino < date('Y-m-d')) {
        if ($conexion->inTransaction()) {
            cal_cancelar_operacion(
                $conexion,
                'La nueva fecha no puede ser anterior al día de hoy.',
                422,
                ['campo' => 'fecha_destino']
            );
        }

        sm_responder_json(
            false,
            'La nueva fecha no puede ser anterior al día de hoy.',
            ['campo' => 'fecha_destino'],
            422
        );
    }

    if ($fechaOrigen === $fechaDestino) {
        if ($conexion->inTransaction()) {
            cal_cancelar_operacion(
                $conexion,
                'Selecciona una fecha diferente a la programación actual.',
                422,
                ['campo' => 'fecha_destino']
            );
        }

        sm_responder_json(
            false,
            'Selecciona una fecha diferente a la programación actual.',
            ['campo' => 'fecha_destino'],
            422
        );
    }

    $estado = cal_estado_fecha($conexion, $fechaDestino);
    if (empty($estado['permitido'])) {
        $extra = [
            'campo' => 'fecha_destino',
            'calendario' => $estado,
        ];

        if ($conexion->inTransaction()) {
            cal_cancelar_operacion(
                $conexion,
                (string) $estado['mensaje'],
                422,
                $extra
            );
        }

        sm_responder_json(
            false,
            (string) $estado['mensaje'],
            $extra,
            422
        );
    }
}

function cal_bloquear_y_validar_destino(
    PDO $conexion,
    string $fechaOrigen,
    string $fechaDestino,
    int $adminId
): void {
    cal_asegurar_mes($conexion, substr($fechaDestino, 0, 7), $adminId);
    $dia = cal_bloquear_dia($conexion, $fechaDestino);

    if (!$dia) {
        cal_cancelar_operacion(
            $conexion,
            'No fue posible preparar la fecha de destino.',
            409
        );
    }

    $permitido = (int) $dia['es_habil'] === 1
        && (string) $dia['tipo_dia'] !== 'INHABIL';

    if (!$permitido) {
        cal_cancelar_operacion(
            $conexion,
            'La fecha de destino fue marcada como inhábil por otro administrador. Actualiza el calendario.',
            409,
            [
                'campo' => 'fecha_destino',
                'calendario' => cal_estado_fecha($conexion, $fechaDestino),
            ]
        );
    }

    if ($fechaDestino < date('Y-m-d') || $fechaDestino === $fechaOrigen) {
        cal_cancelar_operacion(
            $conexion,
            'La fecha de destino ya no es válida. Actualiza la información e inténtalo nuevamente.',
            409,
            ['campo' => 'fecha_destino']
        );
    }
}

function cal_reprogramar_solicitud(
    PDO $conexion,
    int $solicitudId,
    string $fechaOrigen,
    string $fechaDestino,
    int $adminId,
    string $motivo,
    string $modo
): array {
    $solicitud = cal_prog_bloquear_solicitud($conexion, $solicitudId);

    if (!$solicitud) {
        cal_cancelar_operacion(
            $conexion,
            'La solicitud ya no existe o fue desactivada.',
            404
        );
    }

    if ((string) $solicitud['tipo_solicitud'] === 'CORRECTIVO_URGENTE') {
        cal_cancelar_operacion(
            $conexion,
            'Los correctivos urgentes no se reprograman desde el calendario laboral.',
            409
        );
    }

    $estadoAnterior = (string) $solicitud['estado'];
    if (!in_array($estadoAnterior, ['APROBADO', 'AGENDADO', 'ATRASADO'], true)) {
        cal_cancelar_operacion(
            $conexion,
            'La solicitud ' . (string) $solicitud['folio']
                . ' ya no puede reprogramarse porque cambió de estado.',
            409
        );
    }

    $programacion = cal_prog_bloquear_programacion($conexion, $solicitudId);
    if (!$programacion) {
        cal_cancelar_operacion(
            $conexion,
            'La solicitud ' . (string) $solicitud['folio']
                . ' ya no tiene una programación vigente.',
            409
        );
    }

    if ((string) $programacion['fecha_programada'] !== $fechaOrigen) {
        cal_cancelar_operacion(
            $conexion,
            'La fecha de ' . (string) $solicitud['folio']
                . ' cambió mientras realizabas la operación. Actualiza el calendario.',
            409
        );
    }

    $asignaciones = cal_prog_bloquear_asignaciones($conexion, $solicitudId);
    if ($asignaciones === []) {
        cal_cancelar_operacion(
            $conexion,
            'La solicitud ' . (string) $solicitud['folio']
                . ' no tiene técnicos activos. Corrígela desde Programar y asignar.',
            409
        );
    }

    $ejecuciones = cal_prog_bloquear_ejecuciones($conexion, $solicitudId);
    foreach ($ejecuciones as $ejecucion) {
        if (
            !empty($ejecucion['fecha_hora_inicio'])
            || in_array(
                (string) $ejecucion['estado'],
                ['EN_PROCESO', 'PAUSADA', 'TERMINADA'],
                true
            )
        ) {
            cal_cancelar_operacion(
                $conexion,
                'El mantenimiento ' . (string) $solicitud['folio']
                    . ' ya fue iniciado y no puede cambiar de fecha.',
                409
            );
        }
    }

    foreach ($asignaciones as $asignacion) {
        if (!in_array((string) $asignacion['estado'], ['ASIGNADO', 'ACEPTADO'], true)) {
            cal_cancelar_operacion(
                $conexion,
                'Una asignación de ' . (string) $solicitud['folio']
                    . ' cambió de estado. Actualiza el calendario.',
                409
            );
        }
    }

    $tecnicosIds = array_values(array_unique(array_map(
        static function (array $asignacion): int {
            return (int) $asignacion['tecnico_id'];
        },
        $asignaciones
    )));

    $tecnicos = cal_prog_bloquear_tecnicos($conexion, $tecnicosIds);
    if (count($tecnicos) !== count($tecnicosIds)) {
        cal_cancelar_operacion(
            $conexion,
            'Uno o más técnicos asignados a ' . (string) $solicitud['folio']
                . ' ya no están activos. Reprograma el trabajo individualmente desde Programar y asignar.',
            409
        );
    }

    cal_validar_fecha_destino($conexion, $fechaOrigen, $fechaDestino);

    $programacionAnteriorId = (int) $programacion['id'];
    cal_prog_marcar_notificaciones_leidas($conexion, $solicitudId);
    cal_prog_cerrar_programacion(
        $conexion,
        $programacionAnteriorId,
        $motivo
    );

    foreach ($asignaciones as $asignacion) {
        cal_prog_retirar_asignacion(
            $conexion,
            $asignacion,
            $adminId,
            $motivo
        );
    }

    $programacionNuevaId = cal_prog_insertar_programacion(
        $conexion,
        $solicitudId,
        $fechaDestino,
        $adminId,
        $motivo
    );

    $nuevasAsignaciones = [];
    foreach ($asignaciones as $asignacion) {
        $asignacionNuevaId = cal_prog_insertar_asignacion_copia(
            $conexion,
            $asignacion,
            $programacionNuevaId,
            $adminId
        );

        $tecnico = cal_prog_tecnico_por_id(
            $tecnicos,
            (int) $asignacion['tecnico_id']
        );
        $nombreTecnico = cal_prog_nombre_tecnico($tecnico);

        cal_prog_historial(
            $conexion,
            $solicitudId,
            $asignacionNuevaId,
            $programacionNuevaId,
            'ASIGNADA',
            'RETIRADO',
            'ASIGNADO',
            $adminId,
            'Se reasignó el mantenimiento al técnico ' . $nombreTecnico
                . ' para el día ' . $fechaDestino
                . ' después de una reprogramación desde Calendario laboral.'
        );

        cal_prog_notificar(
            $conexion,
            'TECNICO',
            (int) $asignacion['tecnico_id'],
            $solicitudId,
            'Mantenimiento reprogramado',
            'El mantenimiento ' . (string) $solicitud['folio']
                . ' cambió del ' . cal_fecha_es($fechaOrigen)
                . ' al ' . cal_fecha_es($fechaDestino)
                . '. Equipo: ' . (string) $solicitud['nombre_equipo']
                . '. Motivo: ' . $motivo,
            'WARNING'
        );

        $nuevasAsignaciones[] = [
            'asignacion_id' => $asignacionNuevaId,
            'tecnico_id' => (int) $asignacion['tecnico_id'],
            'tecnico' => $nombreTecnico,
        ];
    }

    $stmtSolicitud = $conexion->prepare(
        "UPDATE solicitudes
         SET estado = 'AGENDADO',
             ultima_edicion_admin_id = :admin_id,
             motivo_ultima_edicion = :motivo,
             version_registro = version_registro + 1,
             fecha_actualizacion = NOW()
         WHERE id = :id
           AND activo = 1
           AND estado IN ('APROBADO','AGENDADO','ATRASADO')"
    );
    $stmtSolicitud->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmtSolicitud->bindValue(':motivo', $motivo, PDO::PARAM_STR);
    $stmtSolicitud->bindValue(':id', $solicitudId, PDO::PARAM_INT);
    $stmtSolicitud->execute();

    if ($stmtSolicitud->rowCount() !== 1) {
        cal_cancelar_operacion(
            $conexion,
            'La solicitud cambió mientras se actualizaba. No se realizó la reprogramación.',
            409
        );
    }

    cal_prog_resolver_incumplimientos(
        $conexion,
        $solicitudId,
        $programacionAnteriorId,
        $adminId,
        $motivo
    );
    cal_prog_actualizar_rutina(
        $conexion,
        $solicitudId,
        $programacionNuevaId,
        $adminId,
        $motivo
    );

    $descripcion = 'Se reprogramó ' . (string) $solicitud['folio']
        . ' del ' . $fechaOrigen . ' al ' . $fechaDestino
        . ' desde Calendario laboral'
        . ($modo === 'MASIVA' ? ' mediante movimiento masivo' : '')
        . '. Se conservaron ' . count($asignaciones) . ' técnico(s). Motivo: '
        . $motivo;

    cal_prog_historial(
        $conexion,
        $solicitudId,
        null,
        $programacionNuevaId,
        'REPROGRAMADA',
        $estadoAnterior,
        'AGENDADO',
        $adminId,
        $descripcion
    );

    cal_prog_movimiento(
        $conexion,
        $adminId,
        $modo === 'MASIVA'
            ? 'REPROGRAMAR_MANTENIMIENTO_MASIVO'
            : 'REPROGRAMAR_MANTENIMIENTO_CALENDARIO',
        $descripcion,
        $programacionNuevaId
    );

    cal_prog_notificar_solicitante(
        $conexion,
        $solicitud,
        $adminId,
        $solicitudId,
        'Mantenimiento reprogramado',
        'La solicitud ' . (string) $solicitud['folio']
            . ' cambió del ' . cal_fecha_es($fechaOrigen)
            . ' al ' . cal_fecha_es($fechaDestino)
            . '. Motivo: ' . $motivo,
        'INFO'
    );

    return [
        'solicitud_id' => $solicitudId,
        'folio' => (string) $solicitud['folio'],
        'programacion_anterior_id' => $programacionAnteriorId,
        'programacion_nueva_id' => $programacionNuevaId,
        'fecha_origen' => $fechaOrigen,
        'fecha_destino' => $fechaDestino,
        'tecnicos_conservados' => count($nuevasAsignaciones),
        'asignaciones' => $nuevasAsignaciones,
    ];
}

function cal_prog_bloquear_solicitud(PDO $conexion, int $solicitudId): ?array
{
    $stmt = $conexion->prepare(
        "SELECT s.*, e.codigo_equipo, e.nombre_equipo
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

function cal_prog_bloquear_programacion(PDO $conexion, int $solicitudId): ?array
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

function cal_prog_bloquear_asignaciones(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT *
         FROM solicitud_tecnicos
         WHERE solicitud_id = :solicitud_id
           AND origen = 'ADMIN'
           AND activo = 1
         ORDER BY id
         FOR UPDATE"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function cal_prog_bloquear_ejecuciones(PDO $conexion, int $solicitudId): array
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

function cal_prog_bloquear_tecnicos(PDO $conexion, array $ids): array
{
    if ($ids === []) {
        return [];
    }

    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conexion->prepare(
        "SELECT id, nombre, apellido_paterno, apellido_materno, turno
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

function cal_prog_cerrar_programacion(
    PDO $conexion,
    int $programacionId,
    string $motivo 
): void {
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
        cal_cancelar_operacion(
            $conexion,
            'La programación cambió mientras realizabas la operación. Actualiza el calendario.',
            409
        );
    }
}

function cal_prog_insertar_programacion(
    PDO $conexion,
    int $solicitudId,
    string $fechaDestino,
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
    $stmt->bindValue(':fecha_programada', $fechaDestino, PDO::PARAM_STR);
    $stmt->bindValue(':fecha_limite', $fechaDestino, PDO::PARAM_STR);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':motivo', $motivo, PDO::PARAM_STR);
    $stmt->execute();

    return (int) $conexion->lastInsertId();
}

function cal_prog_retirar_asignacion(
    PDO $conexion,
    array $asignacion,
    int $adminId,
    string $motivo
): void {
    $asignacionId = (int) $asignacion['id'];

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
        cal_cancelar_operacion(
            $conexion,
            'Una asignación fue iniciada o modificada por otro usuario. No se realizó la reprogramación.',
            409
        );
    }

    cal_prog_historial(
        $conexion,
        (int) $asignacion['solicitud_id'],
        $asignacionId,
        $asignacion['programacion_id'] !== null
            ? (int) $asignacion['programacion_id']
            : null,
        'TECNICO_RETIRADO',
        (string) $asignacion['estado'],
        'RETIRADO',
        $adminId,
        'Se cerró la asignación anterior debido a una reprogramación desde Calendario laboral. Motivo: '
            . $motivo
    );
}

function cal_prog_insertar_asignacion_copia(
    PDO $conexion,
    array $anterior,
    int $programacionNuevaId,
    int $adminId
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
            alerta_riesgo_nocturno,
            riesgo_nocturno_confirmado,
            confirmado_por_admin_id,
            observacion_riesgo_nocturno,
            resultado_cumplimiento,
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
            :alerta,
            :confirmado,
            :confirmado_por,
            :observacion,
            'PENDIENTE',
            1,
            1,
            NOW()
        )"
    );
    $stmt->bindValue(':solicitud_id', (int) $anterior['solicitud_id'], PDO::PARAM_INT);
    $stmt->bindValue(':programacion_id', $programacionNuevaId, PDO::PARAM_INT);
    $stmt->bindValue(':tecnico_id', (int) $anterior['tecnico_id'], PDO::PARAM_INT);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(
        ':alerta',
        (int) ($anterior['alerta_riesgo_nocturno'] ?? 0),
        PDO::PARAM_INT
    );
    $stmt->bindValue(
        ':confirmado',
        (int) ($anterior['riesgo_nocturno_confirmado'] ?? 0),
        PDO::PARAM_INT
    );
    cal_bind_int_nullable(
        $stmt,
        ':confirmado_por',
        !empty($anterior['confirmado_por_admin_id'])
            ? (int) $anterior['confirmado_por_admin_id']
            : null
    );
    cal_bind_nullable(
        $stmt,
        ':observacion',
        (string) ($anterior['observacion_riesgo_nocturno'] ?? '')
    );
    $stmt->execute();

    return (int) $conexion->lastInsertId();
}

function cal_prog_resolver_incumplimientos(
    PDO $conexion,
    int $solicitudId,
    int $programacionAnteriorId,
    int $adminId,
    string $motivo
): void {
    $stmt = $conexion->prepare(
        "UPDATE incumplimientos_mantenimiento
         SET estado = 'JUSTIFICADO',
             justificacion = :justificacion,
             justificado_por_admin_id = :admin_id,
             fecha_resolucion = NOW()
         WHERE solicitud_id = :solicitud_id
           AND programacion_id = :programacion_id
           AND estado = 'PENDIENTE'"
    );
    $stmt->bindValue(
        ':justificacion',
        cal_recortar(
            'Reprogramación administrativa desde Calendario laboral antes de iniciar. Motivo: '
                . $motivo,
            1000
        ),
        PDO::PARAM_STR
    );
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':programacion_id', $programacionAnteriorId, PDO::PARAM_INT);
    $stmt->execute();
}

function cal_prog_actualizar_rutina(
    PDO $conexion,
    int $solicitudId,
    int $programacionNuevaId,
    int $adminId,
    string $motivo
): void {
    $stmt = $conexion->prepare(
        "UPDATE rutina_alertas
         SET estado = 'PROGRAMADA',
             programacion_id = :programacion_id,
             atendida_por_admin_id = :admin_id,
             motivo_omision = NULL,
             fecha_atencion = NOW()
         WHERE solicitud_id = :solicitud_id
           AND estado IN ('PENDIENTE_PROGRAMAR','PROGRAMADA')"
    );
    $stmt->bindValue(':programacion_id', $programacionNuevaId, PDO::PARAM_INT);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
}

function cal_prog_marcar_notificaciones_leidas(
    PDO $conexion,
    int $solicitudId
): void {
    $stmt = $conexion->prepare(
        "UPDATE notificaciones
         SET leida = 1,
             fecha_lectura = COALESCE(fecha_lectura, NOW())
         WHERE solicitud_id = :solicitud_id
           AND tipo_usuario = 'TECNICO'
           AND leida = 0"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
}

function cal_prog_historial(
    PDO $conexion,
    int $solicitudId,
    ?int $solicitudTecnicoId,
    ?int $programacionId,
    string $evento,
    ?string $estadoAnterior,
    ?string $estadoNuevo,
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
            :evento,
            :estado_anterior,
            :estado_nuevo,
            'ADMIN',
            :actor_id,
            :descripcion,
            NOW()
        )"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    cal_bind_int_nullable($stmt, ':solicitud_tecnico_id', $solicitudTecnicoId);
    cal_bind_int_nullable($stmt, ':programacion_id', $programacionId);
    $stmt->bindValue(':evento', $evento, PDO::PARAM_STR);
    cal_bind_nullable($stmt, ':estado_anterior', $estadoAnterior ?? '');
    cal_bind_nullable($stmt, ':estado_nuevo', $estadoNuevo ?? '');
    $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->execute();
}

function cal_prog_movimiento(
    PDO $conexion,
    int $adminId,
    string $accion,
    string $descripcion,
    int $programacionId
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
            'Calendario laboral',
            :descripcion,
            'programaciones_mantenimiento',
            :registro_id,
            :ip,
            :user_agent,
            NOW()
        )"
    );
    $stmt->bindValue(':usuario_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':registro_id', $programacionId, PDO::PARAM_INT);
    $stmt->bindValue(
        ':ip',
        substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 60),
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':user_agent',
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        PDO::PARAM_STR
    );
    $stmt->execute();
}

function cal_prog_notificar(
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
    $stmt->bindValue(':titulo', cal_recortar($titulo, 180), PDO::PARAM_STR);
    $stmt->bindValue(':mensaje', cal_recortar($mensaje, 1000), PDO::PARAM_STR);
    $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
    $stmt->execute();
}

function cal_prog_notificar_solicitante(
    PDO $conexion,
    array $solicitud,
    int $adminActual,
    int $solicitudId,
    string $titulo,
    string $mensaje,
    string $tipo
): void {
    if (!empty($solicitud['solicitante_id'])) {
        cal_prog_notificar(
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
        cal_prog_notificar(
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

function cal_prog_tecnico_por_id(array $tecnicos, int $tecnicoId): ?array
{
    foreach ($tecnicos as $tecnico) {
        if ((int) $tecnico['id'] === $tecnicoId) {
            return $tecnico;
        }
    }

    return null;
}

function cal_prog_nombre_tecnico(?array $tecnico): string
{
    if (!$tecnico) {
        return 'Técnico';
    }

    $nombre = trim(implode(' ', array_filter([
        (string) ($tecnico['nombre'] ?? ''),
        (string) ($tecnico['apellido_paterno'] ?? ''),
        (string) ($tecnico['apellido_materno'] ?? ''),
    ])));

    return $nombre !== '' ? $nombre : 'Técnico';
}

function cal_cancelar_operacion(
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

/* =========================================================================
   AUDITORÍA Y SEGURIDAD
   ========================================================================= */

function cal_validar_admin_activo(PDO $conexion, int $adminId): void
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
            'Tu cuenta de administrador ya no está activa.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?sesion=expirada',
            ],
            401
        );
    }
}

function cal_movimiento(
    PDO $conexion,
    int $adminId,
    string $accion,
    string $descripcion,
    ?int $registroId
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
            'Calendario laboral',
            :descripcion,
            'calendario_laboral',
            :registro_id,
            :ip,
            :user_agent,
            NOW()
        )"
    );
    $stmt->bindValue(':usuario_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);

    if ($registroId !== null && $registroId > 0) {
        $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':registro_id', null, PDO::PARAM_NULL);
    }

    $stmt->bindValue(
        ':ip',
        substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 60),
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':user_agent',
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        PDO::PARAM_STR
    );
    $stmt->execute();
}

function cal_admin_id(): int
{
    $id = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        sm_responder_json(
            false,
            'La sesión del administrador no es válida.',
            ['sesion_expirada' => true],
            401
        );
    }

    return (int) $id;
}

/* =========================================================================
   VALIDACIONES Y FORMATO
   ========================================================================= */

function cal_mes($valor): string
{
    $mes = cal_texto($valor);
    $objeto = DateTimeImmutable::createFromFormat('!Y-m', $mes);
    $errores = DateTimeImmutable::getLastErrors();

    $valido = $objeto instanceof DateTimeImmutable
        && $objeto->format('Y-m') === $mes
        && ($errores === false || (
            (int) ($errores['warning_count'] ?? 0) === 0
            && (int) ($errores['error_count'] ?? 0) === 0
        ));

    if (!$valido) {
        sm_responder_json(
            false,
            'Selecciona un mes válido.',
            ['campo' => 'mes'],
            422
        );
    }

    $anio = (int) $objeto->format('Y');
    if ($anio < 2020 || $anio > 2100) {
        sm_responder_json(
            false,
            'El año debe estar entre 2020 y 2100.',
            ['campo' => 'mes'],
            422
        );
    }

    return $mes;
}

function cal_fecha($valor, string $campo): string
{
    $fecha = cal_texto($valor);
    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    $errores = DateTimeImmutable::getLastErrors();

    $valido = $objeto instanceof DateTimeImmutable
        && $objeto->format('Y-m-d') === $fecha
        && ($errores === false || (
            (int) ($errores['warning_count'] ?? 0) === 0
            && (int) ($errores['error_count'] ?? 0) === 0
        ));

    if (!$valido) {
        sm_responder_json(
            false,
            'La fecha seleccionada no es válida.',
            ['campo' => $campo],
            422
        );
    }

    return $fecha;
}

function cal_entero_positivo($valor, string $campo): int
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

function cal_bind_int_nullable(
    PDOStatement $stmt,
    string $parametro,
    ?int $valor
): void {
    if ($valor === null || $valor < 1) {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, $valor, PDO::PARAM_INT);
}

function cal_recortar(string $texto, int $maximo): string
{
    if (cal_longitud($texto) <= $maximo) {
        return $texto;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, $maximo, 'UTF-8');
    }

    return substr($texto, 0, $maximo);
}

function cal_texto($valor): string
{
    return sm_limpiar_texto($valor);
}

function cal_texto_limitado(
    $valor,
    int $minimo,
    int $maximo,
    string $campo
): string {
    $texto = cal_texto($valor);
    $longitud = cal_longitud($texto);

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

function cal_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function cal_bind_nullable(
    PDOStatement $stmt,
    string $parametro,
    string $valor
): void {
    if ($valor === '') {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, $valor, PDO::PARAM_STR);
}

function cal_nombre_mes(string $mes): string
{
    $nombres = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    $objeto = new DateTimeImmutable($mes . '-01');

    return ($nombres[(int) $objeto->format('n')] ?? 'Mes')
        . ' ' . $objeto->format('Y');
}

function cal_nombre_dia(DateTimeImmutable $fecha): string
{
    $nombres = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    return $nombres[(int) $fecha->format('N')] ?? '';
}

function cal_fecha_es(string $fecha): string
{
    $objeto = new DateTimeImmutable($fecha);
    $meses = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    return cal_nombre_dia($objeto) . ' '
        . $objeto->format('j') . ' de '
        . ($meses[(int) $objeto->format('n')] ?? '') . ' de '
        . $objeto->format('Y');
}