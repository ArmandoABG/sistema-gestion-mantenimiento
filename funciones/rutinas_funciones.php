<?php

/*
|--------------------------------------------------------------------------
| Módulo: Rutinas de mantenimiento
|--------------------------------------------------------------------------
| Compatible con PHP 7.3 o superior.
|
| La rutina es una plantilla recurrente:
| - Genera un aviso cuando llega su fecha.
| - No asigna técnicos ni programa automáticamente.
| - El administrador prepara una solicitud RUTINARIA cuando decide atenderla.
| - El siguiente ciclo comienza al terminar el mantenimiento anterior.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';
require_once __DIR__ . '/recursos_mantenimiento_servicio.php';

sm_requerir_sesion(array('ADMIN'), true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($conexion) || !($conexion instanceof PDO)) {
    rut_responder(
        false,
        'No fue posible conectar con la base de datos.',
        array(),
        503
    );
}

try {
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conexion->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log('[RUTINAS][CONFIGURACION PDO] ' . $e->getMessage());
}

$accion = '';

if (isset($_GET['accion'])) {
    $accion = rut_limpiar_texto($_GET['accion']);
} elseif (isset($_POST['accion'])) {
    $accion = rut_limpiar_texto($_POST['accion']);
}

try {
    rut_preparar_esquema($conexion);
    $GLOBALS['rut_automatizacion'] = rut_asegurar_automatizacion($conexion);

    switch ($accion) {
        case 'inicial':
            rut_requerir_metodo('GET');
            rut_endpoint_inicial($conexion);
            break;

        case 'listar':
            rut_requerir_metodo('GET');
            rut_endpoint_listar($conexion);
            break;

        case 'sincronizar':
            rut_requerir_metodo('POST');
            rut_validar_csrf();
            rut_endpoint_sincronizar($conexion);
            break;

        case 'guardar':
            rut_requerir_metodo('POST');
            rut_validar_csrf();
            rut_endpoint_guardar($conexion);
            break;

        case 'cambiar_estado':
            rut_requerir_metodo('POST');
            rut_validar_csrf();
            rut_endpoint_cambiar_estado($conexion);
            break;

        case 'preparar_solicitud':
            rut_requerir_metodo('POST');
            rut_validar_csrf();
            rut_endpoint_preparar_solicitud($conexion);
            break;

        case 'omitir_alerta':
            rut_requerir_metodo('POST');
            rut_validar_csrf();
            rut_endpoint_omitir_alerta($conexion);
            break;

        case 'reactivar_alerta':
            rut_requerir_metodo('POST');
            rut_validar_csrf();
            rut_endpoint_reactivar_alerta($conexion);
            break;

        default:
            rut_responder(
                false,
                'La acción solicitada no es válida.',
                array(),
                400
            );
    }
} catch (RuntimeException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    rut_responder(
        false,
        $e->getMessage(),
        array(),
        409
    );
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'RUT-' . date('Ymd-His') . '-' . random_int(100, 999);

    error_log(
        '[' . $referencia . '][PDO] '
        . $e->getMessage()
        . ' | SQLSTATE: ' . $e->getCode()
        . ' | Archivo: ' . $e->getFile()
        . ' | Línea: ' . $e->getLine()
    );

    $mensaje = 'Ocurrió un error interno al procesar las rutinas.';
    $codigo = 500;

    if (
        (string) $e->getCode() === '23000'
        && stripos($e->getMessage(), 'uk_rutina_nombre_equipo') !== false
    ) {
        $mensaje = 'Ya existe una rutina con ese nombre para el equipo seleccionado.';
        $codigo = 409;
    }

    rut_responder(
        false,
        $mensaje,
        array('referencia' => $referencia),
        $codigo
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'RUT-' . date('Ymd-His') . '-' . random_int(100, 999);

    error_log(
        '[' . $referencia . '][GENERAL] '
        . $e->getMessage()
        . ' | Archivo: ' . $e->getFile()
        . ' | Línea: ' . $e->getLine()
    );

    rut_responder(
        false,
        'No fue posible completar la operación.',
        array('referencia' => $referencia),
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function rut_endpoint_inicial(PDO $conexion)
{
    rut_validar_admin_activo($conexion, rut_admin_id());
    $resultado = rut_sincronizar_estado($conexion);

    rut_responder(
        true,
        'Rutinas cargadas correctamente.',
        array_merge(
            rut_paquete_datos($conexion),
            array(
                'resultado' => $resultado,
                'catalogos' => rut_obtener_catalogos($conexion),
                'listado' => rut_obtener_listado_solicitado($conexion)
            )
        )
    );
}

function rut_endpoint_listar(PDO $conexion)
{
    rut_validar_admin_activo($conexion, rut_admin_id());

    rut_responder(
        true,
        'Listado cargado correctamente.',
        array(
            'csrf_token' => rut_token_csrf(),
            'listado' => rut_obtener_listado_solicitado($conexion),
            'servidor' => array(
                'fecha' => date('Y-m-d'),
                'fecha_hora' => date('Y-m-d H:i:s')
            )
        )
    );
}

function rut_endpoint_sincronizar(PDO $conexion)
{
    rut_validar_admin_activo($conexion, rut_admin_id());
    $resultado = rut_sincronizar_estado($conexion);

    rut_responder(
        true,
        'Los recordatorios y las notificaciones fueron actualizados.',
        array_merge(
            rut_paquete_datos($conexion),
            array('resultado' => $resultado)
        )
    );
}

function rut_endpoint_guardar(PDO $conexion)
{
    $adminId = rut_admin_id();
    rut_validar_admin_activo($conexion, $adminId);

    $id = rut_entero_opcional_estricto(
        isset($_POST['id']) ? $_POST['id'] : null,
        'rutina'
    );

    $nombre = rut_validar_texto(
        isset($_POST['nombre']) ? $_POST['nombre'] : '',
        3,
        150,
        'nombre'
    );

    $tipoRutina = rut_validar_texto(
        isset($_POST['tipo_rutina']) ? $_POST['tipo_rutina'] : '',
        3,
        100,
        'tipo de actividad'
    );

    if (!in_array($tipoRutina, rut_tipos_rutina(), true)) {
        rut_responder(
            false,
            'Selecciona un tipo de actividad válido.',
            array('campo' => 'tipo_rutina'),
            422
        );
    }

    $descripcion = rut_validar_texto(
        isset($_POST['descripcion_actividad'])
            ? $_POST['descripcion_actividad']
            : '',
        10,
        3000,
        'descripción de la actividad'
    );

    $equipoId = rut_entero_positivo(
        isset($_POST['equipo_id']) ? $_POST['equipo_id'] : null,
        'equipo'
    );

    $prioridad = strtoupper(
        rut_limpiar_texto(
            isset($_POST['prioridad']) ? $_POST['prioridad'] : 'MEDIA'
        )
    );

    if (!in_array($prioridad, array('BAJA', 'MEDIA', 'ALTA'), true)) {
        rut_responder(
            false,
            'Selecciona una prioridad válida.',
            array('campo' => 'prioridad'),
            422
        );
    }

    $tipoFallaId = rut_entero_opcional_estricto(
        isset($_POST['tipo_falla_id']) ? $_POST['tipo_falla_id'] : null,
        'tipo de falla'
    );

    $causaAveriaId = rut_entero_opcional_estricto(
        isset($_POST['causa_averia_id']) ? $_POST['causa_averia_id'] : null,
        'causa relacionada'
    );

    $trabajoPeligroso = rut_booleano_estricto(
        isset($_POST['trabajo_peligroso'])
            ? $_POST['trabajo_peligroso']
            : 0,
        'trabajo peligroso'
    );

    $nivelRiesgo = strtoupper(
        rut_limpiar_texto(
            isset($_POST['nivel_riesgo'])
                ? $_POST['nivel_riesgo']
                : 'BAJO'
        )
    );

    if (!in_array($nivelRiesgo, array('BAJO', 'MEDIO', 'ALTO'), true)) {
        rut_responder(
            false,
            'Selecciona un nivel de riesgo válido.',
            array('campo' => 'nivel_riesgo'),
            422
        );
    }

    if ($trabajoPeligroso !== 1) {
        $nivelRiesgo = 'BAJO';
    }

    $detalleTrabajoPeligroso = rut_validar_detalle_trabajo_peligroso(
        isset($_POST['detalle_trabajo_peligroso'])
            ? $_POST['detalle_trabajo_peligroso']
            : '',
        $trabajoPeligroso
    );

    $herramientasIds = rut_normalizar_ids_recursos(
        isset($_POST['herramientas_ids']) ? $_POST['herramientas_ids'] : array(),
        'herramientas'
    );
    $refaccionesIds = rut_normalizar_ids_recursos(
        isset($_POST['refacciones_ids']) ? $_POST['refacciones_ids'] : array(),
        'refacciones'
    );

    $requiereParo = rut_booleano_estricto(
        isset($_POST['requiere_paro_equipo'])
            ? $_POST['requiere_paro_equipo']
            : 0,
        'paro del equipo'
    );

    $frecuenciaCada = rut_entero_positivo(
        isset($_POST['frecuencia_cada'])
            ? $_POST['frecuencia_cada']
            : null,
        'intervalo de días'
    );

    if ($frecuenciaCada > 3650) {
        rut_responder(
            false,
            'El intervalo no puede ser mayor a 3650 días.',
            array('campo' => 'frecuencia_cada'),
            422
        );
    }

    $fechaInicio = rut_limpiar_texto(
        isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : ''
    );

    if (!rut_fecha_valida($fechaInicio)) {
        rut_responder(
            false,
            'Selecciona una fecha válida para el próximo aviso.',
            array('campo' => 'fecha_inicio'),
            422
        );
    }

    $equipo = rut_obtener_equipo($conexion, $equipoId);

    if (!$equipo) {
        rut_responder(
            false,
            'El equipo seleccionado no existe, está inactivo o su ubicación está incompleta.',
            array('campo' => 'equipo_id'),
            422
        );
    }

    if (
        $tipoFallaId > 0
        && !rut_registro_activo($conexion, 'tipos_falla', $tipoFallaId)
    ) {
        rut_responder(
            false,
            'El tipo de falla seleccionado no está disponible.',
            array('campo' => 'tipo_falla_id'),
            422
        );
    }

    if (
        $causaAveriaId > 0
        && !rut_registro_activo($conexion, 'causas_averia', $causaAveriaId)
    ) {
        rut_responder(
            false,
            'La causa seleccionada no está disponible.',
            array('campo' => 'causa_averia_id'),
            422
        );
    }

    $equipo['tipo_falla'] = $tipoFallaId > 0
        ? rut_nombre_catalogo($conexion, 'tipos_falla', $tipoFallaId)
        : null;
    $equipo['causa_averia'] = $causaAveriaId > 0
        ? rut_nombre_catalogo($conexion, 'causas_averia', $causaAveriaId)
        : null;

    rut_validar_recursos_catalogo(
        $conexion,
        $herramientasIds,
        RSM_TIPO_HERRAMIENTA,
        $id
    );
    rut_validar_recursos_catalogo(
        $conexion,
        $refaccionesIds,
        RSM_TIPO_REFACCION,
        $id
    );

    $conexion->beginTransaction();

    if (
        rut_existe_rutina_duplicada(
            $conexion,
            $nombre,
            $equipoId,
            $id
        )
    ) {
        throw new RuntimeException(
            'Ya existe una rutina con ese nombre para el equipo seleccionado.'
        );
    }

    $advertencia = '';
    $snapshot = '';

    if ($id > 0) {
        $anterior = rut_bloquear_rutina($conexion, $id);

        if (!$anterior) {
            throw new RuntimeException('La rutina seleccionada ya no existe.');
        }

        $fechaAnterior = (string) $anterior['proxima_notificacion'];
        $cicloBloqueante = rut_obtener_ciclo_bloqueante($conexion, $id);

        /*
         * Cuando ya existe una solicitud o programación activa, el vencimiento
         * de ese ciclo no se mueve. Los cambios de frecuencia se aplicarán al
         * siguiente ciclo, calculado desde el cierre real.
         */
        if (
            $cicloBloqueante
            && (int) $cicloBloqueante['solicitud_id'] > 0
        ) {
            $fechaInicio = $fechaAnterior;
            $advertencia = 'El próximo aviso no cambió porque esta rutina ya tiene una solicitud en curso. La nueva frecuencia se aplicará después de terminarla.';
        }

        $stmt = $conexion->prepare(
            "UPDATE rutinas_mantenimiento
             SET nombre = :nombre,
                 descripcion_actividad = :descripcion,
                 tipo_rutina = :tipo_rutina,
                 departamento_id = :departamento_id,
                 area_id = :area_id,
                 proceso_id = :proceso_id,
                 equipo_id = :equipo_id,
                 prioridad = :prioridad,
                 tipo_falla_id = :tipo_falla_id,
                 causa_averia_id = :causa_averia_id,
                 trabajo_peligroso = :trabajo_peligroso,
                 detalle_trabajo_peligroso = :detalle_trabajo_peligroso,
                 nivel_riesgo = :nivel_riesgo,
                 requiere_paro_equipo = :requiere_paro_equipo,
                 frecuencia_unidad = 'DIA',
                 frecuencia_cada = :frecuencia_cada,
                 dia_semana = NULL,
                 dia_mes = NULL,
                 fecha_inicio = :fecha_inicio,
                 proxima_notificacion = :proxima_notificacion,
                 modificado_por_admin_id = :admin_id,
                 fecha_actualizacion = NOW()
             WHERE id = :id"
        );

        rut_vincular_datos_rutina(
            $stmt,
            $nombre,
            $descripcion,
            $tipoRutina,
            $equipo,
            $prioridad,
            $tipoFallaId,
            $causaAveriaId,
            $trabajoPeligroso,
            $detalleTrabajoPeligroso,
            $nivelRiesgo,
            $requiereParo,
            $frecuenciaCada,
            $fechaInicio,
            $adminId
        );

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        rut_reemplazar_recursos_rutina(
            $conexion,
            $id,
            array_merge($herramientasIds, $refaccionesIds),
            $adminId
        );
        $recursosSnapshot = rut_obtener_recursos_rutina($conexion, $id);

        $snapshot = rut_crear_snapshot_desde_valores(
            $nombre,
            $descripcion,
            $tipoRutina,
            $equipo,
            $prioridad,
            $tipoFallaId,
            $causaAveriaId,
            $trabajoPeligroso,
            $detalleTrabajoPeligroso,
            $nivelRiesgo,
            $requiereParo,
            $recursosSnapshot
        );

        if (!$cicloBloqueante || (int) $cicloBloqueante['solicitud_id'] <= 0) {
            rut_reconciliar_alertas_edicion(
                $conexion,
                $id,
                $fechaAnterior,
                $fechaInicio,
                $snapshot,
                $adminId
            );
        }

        rut_registrar_movimiento(
            $conexion,
            $adminId,
            'EDITAR_RUTINA',
            'Se actualizó la plantilla de rutina "' . $nombre . '" con ' . count($herramientasIds) . ' herramienta(s) y ' . count($refaccionesIds) . ' refacción(es).',
            'rutinas_mantenimiento',
            $id
        );

        $mensaje = 'La plantilla fue actualizada correctamente.';
    } else {
        $stmt = $conexion->prepare(
            "INSERT INTO rutinas_mantenimiento
            (
                nombre,
                descripcion_actividad,
                tipo_rutina,
                departamento_id,
                area_id,
                proceso_id,
                equipo_id,
                prioridad,
                tipo_falla_id,
                causa_averia_id,
                trabajo_peligroso,
                detalle_trabajo_peligroso,
                nivel_riesgo,
                requiere_paro_equipo,
                frecuencia_unidad,
                frecuencia_cada,
                dia_semana,
                dia_mes,
                fecha_inicio,
                ultima_notificacion,
                proxima_notificacion,
                creado_por_admin_id,
                modificado_por_admin_id,
                activo,
                fecha_registro,
                fecha_actualizacion
            )
            VALUES
            (
                :nombre,
                :descripcion,
                :tipo_rutina,
                :departamento_id,
                :area_id,
                :proceso_id,
                :equipo_id,
                :prioridad,
                :tipo_falla_id,
                :causa_averia_id,
                :trabajo_peligroso,
                :detalle_trabajo_peligroso,
                :nivel_riesgo,
                :requiere_paro_equipo,
                'DIA',
                :frecuencia_cada,
                NULL,
                NULL,
                :fecha_inicio,
                NULL,
                :proxima_notificacion,
                :admin_id,
                NULL,
                1,
                NOW(),
                NOW()
            )"
        );

        rut_vincular_datos_rutina(
            $stmt,
            $nombre,
            $descripcion,
            $tipoRutina,
            $equipo,
            $prioridad,
            $tipoFallaId,
            $causaAveriaId,
            $trabajoPeligroso,
            $detalleTrabajoPeligroso,
            $nivelRiesgo,
            $requiereParo,
            $frecuenciaCada,
            $fechaInicio,
            $adminId
        );

        $stmt->execute();
        $id = (int) $conexion->lastInsertId();

        rut_reemplazar_recursos_rutina(
            $conexion,
            $id,
            array_merge($herramientasIds, $refaccionesIds),
            $adminId
        );
        $recursosSnapshot = rut_obtener_recursos_rutina($conexion, $id);

        $snapshot = rut_crear_snapshot_desde_valores(
            $nombre,
            $descripcion,
            $tipoRutina,
            $equipo,
            $prioridad,
            $tipoFallaId,
            $causaAveriaId,
            $trabajoPeligroso,
            $detalleTrabajoPeligroso,
            $nivelRiesgo,
            $requiereParo,
            $recursosSnapshot
        );

        if ($fechaInicio <= date('Y-m-d')) {
            rut_crear_o_reactivar_alerta(
                $conexion,
                $id,
                $fechaInicio,
                $snapshot,
                true
            );
        }

        rut_registrar_movimiento(
            $conexion,
            $adminId,
            'CREAR_RUTINA',
            'Se creó la plantilla de rutina "' . $nombre
                . '" con intervalo de ' . $frecuenciaCada . ' día(s), ' . count($herramientasIds) . ' herramienta(s) y ' . count($refaccionesIds) . ' refacción(es).',
            'rutinas_mantenimiento',
            $id
        );

        $mensaje = 'La plantilla fue registrada correctamente.';
    }

    $conexion->commit();

    rut_sincronizar_estado($conexion);

    rut_responder(
        true,
        $mensaje,
        array_merge(
            array(
                'id' => $id,
                'advertencia' => $advertencia
            ),
            rut_paquete_datos($conexion)
        )
    );
}

function rut_endpoint_cambiar_estado(PDO $conexion)
{
    $adminId = rut_admin_id();
    rut_validar_admin_activo($conexion, $adminId);

    $id = rut_entero_positivo(
        isset($_POST['id']) ? $_POST['id'] : null,
        'rutina'
    );

    $activo = rut_booleano_estricto(
        isset($_POST['activo']) ? $_POST['activo'] : null,
        'estado'
    );

    $conexion->beginTransaction();

    $rutina = rut_bloquear_rutina($conexion, $id);

    if (!$rutina) {
        throw new RuntimeException('La rutina seleccionada ya no existe.');
    }

    if ((int) $rutina['activo'] === $activo) {
        $conexion->commit();

        rut_responder(
            true,
            $activo === 1
                ? 'La rutina ya estaba activa.'
                : 'La rutina ya estaba inactiva.',
            rut_paquete_datos($conexion)
        );
    }

    $stmt = $conexion->prepare(
        "UPDATE rutinas_mantenimiento
         SET activo = :activo,
             modificado_por_admin_id = :admin_id,
             fecha_actualizacion = NOW()
         WHERE id = :id"
    );
    $stmt->execute(
        array(
            ':activo' => $activo,
            ':admin_id' => $adminId,
            ':id' => $id
        )
    );

    if ($activo !== 1) {
        $stmtAlertas = $conexion->prepare(
            "SELECT id
             FROM rutina_alertas
             WHERE rutina_id = :rutina_id
               AND estado = 'PENDIENTE_PROGRAMAR'
               AND solicitud_id IS NULL
             FOR UPDATE"
        );
        $stmtAlertas->execute(array(':rutina_id' => $id));
        $idsAlertas = $stmtAlertas->fetchAll(PDO::FETCH_COLUMN);

        $stmtCancelar = $conexion->prepare(
            "UPDATE rutina_alertas
             SET estado = 'CANCELADA',
                 motivo_omision = 'La rutina fue desactivada.',
                 atendida_por_admin_id = :admin_id,
                 fecha_atencion = NOW()
             WHERE rutina_id = :rutina_id
               AND estado = 'PENDIENTE_PROGRAMAR'
               AND solicitud_id IS NULL"
        );
        $stmtCancelar->execute(
            array(
                ':admin_id' => $adminId,
                ':rutina_id' => $id
            )
        );

        foreach ($idsAlertas as $alertaId) {
            rut_marcar_notificaciones_leidas($conexion, (int) $alertaId);
        }
    } elseif ((string) $rutina['proxima_notificacion'] <= date('Y-m-d')) {
        $rutinaActualizada = rut_obtener_rutina_completa($conexion, $id);

        if ($rutinaActualizada) {
            rut_crear_o_reactivar_alerta(
                $conexion,
                $id,
                (string) $rutinaActualizada['proxima_notificacion'],
                rut_crear_snapshot_desde_fila(
                    $rutinaActualizada,
                    rut_obtener_recursos_rutina($conexion, $id)
                ),
                false
            );
        }
    }

    rut_registrar_movimiento(
        $conexion,
        $adminId,
        $activo === 1 ? 'ACTIVAR_RUTINA' : 'DESACTIVAR_RUTINA',
        ($activo === 1 ? 'Se activó' : 'Se desactivó')
            . ' la plantilla "' . $rutina['nombre'] . '".',
        'rutinas_mantenimiento',
        $id
    );

    $conexion->commit();

    rut_sincronizar_estado($conexion);

    rut_responder(
        true,
        $activo === 1
            ? 'La rutina fue activada.'
            : 'La rutina fue desactivada. Las solicitudes ya creadas no fueron alteradas.',
        rut_paquete_datos($conexion)
    );
}

function rut_endpoint_preparar_solicitud(PDO $conexion)
{
    $adminId = rut_admin_id();
    rut_validar_admin_activo($conexion, $adminId);

    $alertaId = rut_entero_positivo(
        isset($_POST['alerta_id']) ? $_POST['alerta_id'] : null,
        'recordatorio'
    );

    $conexion->beginTransaction();

    $alerta = rut_bloquear_alerta($conexion, $alertaId);

    if (!$alerta) {
        throw new RuntimeException('El recordatorio seleccionado ya no existe.');
    }

    if ((int) $alerta['rutina_activa'] !== 1) {
        throw new RuntimeException(
            'La plantilla está inactiva. Actívala antes de continuar.'
        );
    }

    if ((string) $alerta['estado'] !== 'PENDIENTE_PROGRAMAR') {
        throw new RuntimeException('Este recordatorio ya fue atendido.');
    }

    if ((int) $alerta['solicitud_id'] > 0) {
        $solicitudId = (int) $alerta['solicitud_id'];
        $conexion->commit();

        rut_responder(
            true,
            'La solicitud ya estaba preparada.',
            array(
                'solicitud_id' => $solicitudId,
                'redirect' => 'solicitudes_programacion.php?solicitud_id='
                    . $solicitudId
            )
        );
    }

    if ((string) $alerta['fecha_notificacion'] > date('Y-m-d')) {
        throw new RuntimeException(
            'Este recordatorio todavía no llega a su fecha de atención.'
        );
    }

    $folio = rut_generar_folio($conexion);

    $descripcion = trim(
        (string) $alerta['nombre']
        . ': '
        . (string) $alerta['descripcion_actividad']
    );

    $stmt = $conexion->prepare(
        "INSERT INTO solicitudes
        (
            folio,
            tipo_solicitud,
            estado,
            solicitante_id,
            administrador_solicitante_id,
            creado_por_tipo,
            creado_por_id,
            departamento_id,
            area_id,
            proceso_id,
            equipo_id,
            fecha_solicitud,
            hora_solicitud,
            fecha_sugerida,
            prioridad,
            descripcion_solicitud,
            tipo_falla_id,
            causa_averia_id,
            descripcion_falla,
            observaciones_solicitante,
            trabajo_peligroso,
            detalle_trabajo_peligroso,
            nivel_riesgo,
            requiere_paro_equipo,
            revisado_por_admin_id,
            fecha_revision,
            observaciones_revision,
            activo,
            fecha_registro,
            fecha_actualizacion
        )
        VALUES
        (
            :folio,
            'RUTINARIO',
            'APROBADO',
            NULL,
            :administrador_solicitante_id,
            'ADMIN',
            :creado_por_id,
            :departamento_id,
            :area_id,
            :proceso_id,
            :equipo_id,
            CURDATE(),
            CURTIME(),
            :fecha_sugerida,
            :prioridad,
            :descripcion_solicitud,
            :tipo_falla_id,
            :causa_averia_id,
            :descripcion_falla,
            :observaciones_solicitante,
            :trabajo_peligroso,
            :detalle_trabajo_peligroso,
            :nivel_riesgo,
            :requiere_paro_equipo,
            :revisado_por_admin_id,
            NOW(),
            :observaciones_revision,
            1,
            NOW(),
            NOW()
        )"
    );

    $stmt->bindValue(':folio', $folio, PDO::PARAM_STR);
    $stmt->bindValue(':administrador_solicitante_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':creado_por_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':departamento_id', (int) $alerta['departamento_id'], PDO::PARAM_INT);
    $stmt->bindValue(':area_id', (int) $alerta['area_id'], PDO::PARAM_INT);
    $stmt->bindValue(':proceso_id', (int) $alerta['proceso_id'], PDO::PARAM_INT);
    $stmt->bindValue(':equipo_id', (int) $alerta['equipo_id'], PDO::PARAM_INT);
    $stmt->bindValue(
        ':fecha_sugerida',
        (string) $alerta['fecha_notificacion'],
        PDO::PARAM_STR
    );
    $stmt->bindValue(':prioridad', (string) $alerta['prioridad'], PDO::PARAM_STR);
    $stmt->bindValue(':descripcion_solicitud', $descripcion, PDO::PARAM_STR);

    rut_bind_entero_nullable(
        $stmt,
        ':tipo_falla_id',
        (int) $alerta['tipo_falla_id']
    );
    rut_bind_entero_nullable(
        $stmt,
        ':causa_averia_id',
        (int) $alerta['causa_averia_id']
    );

    $stmt->bindValue(
        ':descripcion_falla',
        (string) $alerta['tipo_rutina'],
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':observaciones_solicitante',
        'Solicitud originada desde el recordatorio de la rutina #'
            . (int) $alerta['rutina_id']
            . '. El administrador debe elegir la fecha real y los técnicos.',
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':trabajo_peligroso',
        (int) $alerta['trabajo_peligroso'],
        PDO::PARAM_INT
    );
    rut_bind_texto_nullable(
        $stmt,
        ':detalle_trabajo_peligroso',
        isset($alerta['detalle_trabajo_peligroso'])
            ? $alerta['detalle_trabajo_peligroso']
            : null
    );
    $stmt->bindValue(
        ':nivel_riesgo',
        (string) $alerta['nivel_riesgo'],
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':requiere_paro_equipo',
        (int) $alerta['requiere_paro_equipo'],
        PDO::PARAM_INT
    );
    $stmt->bindValue(':revisado_por_admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(
        ':observaciones_revision',
        'Solicitud rutinaria aprobada al ser preparada por un administrador. '
            . 'Queda pendiente programar fecha y técnicos.',
        PDO::PARAM_STR
    );
    $stmt->execute();

    $solicitudId = (int) $conexion->lastInsertId();

    rut_copiar_recursos_alerta_a_solicitud(
        $conexion,
        $alerta,
        $solicitudId,
        $adminId
    );

    $stmtAlerta = $conexion->prepare(
        "UPDATE rutina_alertas
         SET solicitud_id = :solicitud_id,
             atendida_por_admin_id = :admin_id,
             fecha_atencion = NOW(),
             motivo_omision = NULL
         WHERE id = :id
           AND estado = 'PENDIENTE_PROGRAMAR'
           AND solicitud_id IS NULL"
    );
    $stmtAlerta->execute(
        array(
            ':solicitud_id' => $solicitudId,
            ':admin_id' => $adminId,
            ':id' => $alertaId
        )
    );

    if ($stmtAlerta->rowCount() !== 1) {
        throw new RuntimeException(
            'El recordatorio cambió mientras se preparaba. Actualiza la página.'
        );
    }

    rut_registrar_historial(
        $conexion,
        $solicitudId,
        'CREADA',
        null,
        'APROBADO',
        $adminId,
        'Se creó y aprobó la solicitud desde la plantilla "'
            . $alerta['nombre']
            . '". Queda pendiente elegir fecha y técnicos.'
    );

    rut_registrar_movimiento(
        $conexion,
        $adminId,
        'PREPARAR_SOLICITUD_RUTINA',
        'Se preparó la solicitud ' . $folio
            . ' desde la plantilla "' . $alerta['nombre'] . '".',
        'solicitudes',
        $solicitudId
    );

    rut_marcar_notificaciones_leidas($conexion, $alertaId);

    $conexion->commit();

    /*
     * Genera inmediatamente la notificación de "pendiente de asignar" y
     * evita que permanezca visible la notificación anterior de "preparar".
     */
    rut_sincronizar_estado($conexion);

    rut_responder(
        true,
        'La solicitud quedó lista para programar y asignar.',
        array(
            'solicitud_id' => $solicitudId,
            'folio' => $folio,
            'redirect' => 'solicitudes_programacion.php?solicitud_id='
                . $solicitudId
        )
    );
}

function rut_endpoint_omitir_alerta(PDO $conexion)
{
    $adminId = rut_admin_id();
    rut_validar_admin_activo($conexion, $adminId);

    $alertaId = rut_entero_positivo(
        isset($_POST['alerta_id']) ? $_POST['alerta_id'] : null,
        'recordatorio'
    );

    $motivo = rut_validar_texto(
        isset($_POST['motivo']) ? $_POST['motivo'] : '',
        10,
        500,
        'motivo'
    );

    $conexion->beginTransaction();

    $alerta = rut_bloquear_alerta($conexion, $alertaId);

    if (!$alerta) {
        throw new RuntimeException('El recordatorio ya no existe.');
    }

    if ((string) $alerta['estado'] !== 'PENDIENTE_PROGRAMAR') {
        throw new RuntimeException('El recordatorio ya fue atendido.');
    }

    if ((int) $alerta['solicitud_id'] > 0) {
        throw new RuntimeException(
            'No puedes omitirlo porque ya existe una solicitud preparada.'
        );
    }

    if ((int) $alerta['rutina_activa'] !== 1) {
        throw new RuntimeException(
            'La plantilla está inactiva y el recordatorio ya no puede omitirse.'
        );
    }

    $stmt = $conexion->prepare(
        "UPDATE rutina_alertas
         SET estado = 'OMITIDA',
             motivo_omision = :motivo,
             atendida_por_admin_id = :admin_id,
             fecha_atencion = NOW()
         WHERE id = :id
           AND estado = 'PENDIENTE_PROGRAMAR'
           AND solicitud_id IS NULL"
    );
    $stmt->execute(
        array(
            ':motivo' => $motivo,
            ':admin_id' => $adminId,
            ':id' => $alertaId
        )
    );

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'El recordatorio cambió mientras se omitía. Actualiza la página.'
        );
    }

    /*
     * El siguiente ciclo se cuenta desde hoy para no producir de inmediato
     * una cadena de periodos atrasados.
     */
    $siguiente = (new DateTimeImmutable('today'))
        ->modify(
            '+' . max(1, (int) $alerta['frecuencia_cada']) . ' days'
        )
        ->format('Y-m-d');

    $stmtRutina = $conexion->prepare(
        "UPDATE rutinas_mantenimiento
         SET ultima_notificacion = :fecha_anterior,
             proxima_notificacion = :siguiente,
             modificado_por_admin_id = :admin_id,
             fecha_actualizacion = NOW()
         WHERE id = :rutina_id"
    );
    $stmtRutina->execute(
        array(
            ':fecha_anterior' => (string) $alerta['fecha_notificacion'],
            ':siguiente' => $siguiente,
            ':admin_id' => $adminId,
            ':rutina_id' => (int) $alerta['rutina_id']
        )
    );

    rut_marcar_notificaciones_leidas($conexion, $alertaId);

    rut_registrar_movimiento(
        $conexion,
        $adminId,
        'OMITIR_RECORDATORIO_RUTINA',
        'Se omitió el periodo de la plantilla "'
            . $alerta['nombre']
            . '". Motivo: ' . $motivo,
        'rutina_alertas',
        $alertaId
    );

    $conexion->commit();

    rut_sincronizar_estado($conexion);

    rut_responder(
        true,
        'El periodo fue omitido. La plantilla continuará en su siguiente ciclo.',
        rut_paquete_datos($conexion)
    );
}

function rut_endpoint_reactivar_alerta(PDO $conexion)
{
    $adminId = rut_admin_id();
    rut_validar_admin_activo($conexion, $adminId);

    $alertaId = rut_entero_positivo(
        isset($_POST['alerta_id']) ? $_POST['alerta_id'] : null,
        'recordatorio'
    );

    $conexion->beginTransaction();

    $alerta = rut_bloquear_alerta($conexion, $alertaId);

    if (!$alerta) {
        throw new RuntimeException('El recordatorio ya no existe.');
    }

    if ((int) $alerta['rutina_activa'] !== 1) {
        throw new RuntimeException('Primero debes activar la plantilla.');
    }

    if (
        !in_array(
            (string) $alerta['estado'],
            array('OMITIDA', 'CANCELADA'),
            true
        )
    ) {
        throw new RuntimeException(
            'Este recordatorio no necesita reactivarse.'
        );
    }

    if (
        rut_existe_otro_ciclo_bloqueante(
            $conexion,
            (int) $alerta['rutina_id'],
            $alertaId
        )
    ) {
        throw new RuntimeException(
            'No puede reactivarse porque la rutina ya tiene otro periodo pendiente o una solicitud en curso.'
        );
    }

    if (
        rut_existe_periodo_posterior_atendido(
            $conexion,
            (int) $alerta['rutina_id'],
            $alertaId,
            (string) $alerta['fecha_notificacion']
        )
    ) {
        throw new RuntimeException(
            'No puede reactivarse un periodo antiguo porque la rutina ya avanzó a un ciclo posterior.'
        );
    }

    $solicitudAnteriorId = (int) $alerta['solicitud_id'];
    $folioSolicitudAnterior = '';
    $estadoSolicitudAnterior = '';

    /*
     * Si el periodo ya había generado una solicitud y esa solicitud fue
     * cancelada o rechazada, la solicitud anterior debe conservarse intacta
     * en el historial. Reactivar el recordatorio libera solamente el periodo
     * para que el administrador prepare una solicitud nueva.
     */
    if ($solicitudAnteriorId > 0) {
        $stmtSolicitud = $conexion->prepare(
            "SELECT id, folio, tipo_solicitud, estado, activo
             FROM solicitudes
             WHERE id = :id
             LIMIT 1
             FOR UPDATE"
        );
        $stmtSolicitud->bindValue(
            ':id',
            $solicitudAnteriorId,
            PDO::PARAM_INT
        );
        $stmtSolicitud->execute();
        $solicitudAnterior = $stmtSolicitud->fetch();

        if (!$solicitudAnterior) {
            throw new RuntimeException(
                'La solicitud relacionada ya no existe. Revisa la integridad del recordatorio.'
            );
        }

        if ((string) $solicitudAnterior['tipo_solicitud'] !== 'RUTINARIO') {
            throw new RuntimeException(
                'La solicitud relacionada no corresponde a un mantenimiento rutinario.'
            );
        }

        $estadoSolicitudAnterior = (string) $solicitudAnterior['estado'];
        $folioSolicitudAnterior = (string) $solicitudAnterior['folio'];

        if (
            !in_array(
                $estadoSolicitudAnterior,
                array('CANCELADO', 'RECHAZADO'),
                true
            )
        ) {
            throw new RuntimeException(
                'El recordatorio tiene una solicitud vigente. Ábrela desde Programar y asignar en lugar de reactivar el periodo.'
            );
        }

        $stmtProgramacionVigente = $conexion->prepare(
            "SELECT COUNT(*)
             FROM programaciones_mantenimiento
             WHERE solicitud_id = :solicitud_id
               AND es_actual = 1"
        );
        $stmtProgramacionVigente->bindValue(
            ':solicitud_id',
            $solicitudAnteriorId,
            PDO::PARAM_INT
        );
        $stmtProgramacionVigente->execute();

        if ((int) $stmtProgramacionVigente->fetchColumn() > 0) {
            throw new RuntimeException(
                'La solicitud cancelada todavía conserva una programación vigente. Actualiza la pantalla y revisa la programación.'
            );
        }

        $stmtEjecucionIniciada = $conexion->prepare(
            "SELECT COUNT(*)
             FROM ejecuciones_mantenimiento
             WHERE solicitud_id = :solicitud_id
               AND
               (
                   fecha_hora_inicio IS NOT NULL
                   OR estado IN ('EN_PROCESO','PAUSADA','TERMINADA')
               )"
        );
        $stmtEjecucionIniciada->bindValue(
            ':solicitud_id',
            $solicitudAnteriorId,
            PDO::PARAM_INT
        );
        $stmtEjecucionIniciada->execute();

        if ((int) $stmtEjecucionIniciada->fetchColumn() > 0) {
            throw new RuntimeException(
                'No puede reactivarse este periodo porque la solicitud relacionada llegó a iniciarse.'
            );
        }

        $stmt = $conexion->prepare(
            "UPDATE rutina_alertas
             SET estado = 'PENDIENTE_PROGRAMAR',
                 solicitud_id = NULL,
                 programacion_id = NULL,
                 motivo_omision = NULL,
                 atendida_por_admin_id = NULL,
                 fecha_atencion = NULL
             WHERE id = :id
               AND estado IN ('OMITIDA','CANCELADA')
               AND solicitud_id = :solicitud_id"
        );
        $stmt->bindValue(':id', $alertaId, PDO::PARAM_INT);
        $stmt->bindValue(
            ':solicitud_id',
            $solicitudAnteriorId,
            PDO::PARAM_INT
        );
        $stmt->execute();

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'El recordatorio cambió mientras se reactivaba. Actualiza la página.'
            );
        }

        rut_registrar_historial(
            $conexion,
            $solicitudAnteriorId,
            'OTRO',
            $estadoSolicitudAnterior,
            $estadoSolicitudAnterior,
            $adminId,
            'El recordatorio rutinario fue reactivado. La solicitud '
                . $folioSolicitudAnterior
                . ' permanece '
                . strtolower($estadoSolicitudAnterior)
                . ' en el historial y el mismo periodo quedó disponible para preparar una solicitud nueva.'
        );
    } else {
        $stmt = $conexion->prepare(
            "UPDATE rutina_alertas
             SET estado = 'PENDIENTE_PROGRAMAR',
                 motivo_omision = NULL,
                 atendida_por_admin_id = NULL,
                 fecha_atencion = NULL
             WHERE id = :id
               AND estado IN ('OMITIDA','CANCELADA')
               AND solicitud_id IS NULL"
        );
        $stmt->execute(array(':id' => $alertaId));

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'El recordatorio cambió mientras se reactivaba. Actualiza la página.'
            );
        }
    }

    /*
     * Si el periodo había sido omitido o cancelado, se restaura como el
     * próximo ciclo pendiente. La siguiente fecha volverá a calcularse
     * únicamente cuando el mantenimiento reactivado termine realmente.
     */
    $ultimaAnterior = rut_ultima_fecha_atendida_anterior(
        $conexion,
        (int) $alerta['rutina_id'],
        $alertaId,
        (string) $alerta['fecha_notificacion']
    );

    $stmtRutina = $conexion->prepare(
        "UPDATE rutinas_mantenimiento
         SET ultima_notificacion = :ultima,
             proxima_notificacion = :fecha,
             modificado_por_admin_id = :admin_id,
             fecha_actualizacion = NOW()
         WHERE id = :rutina_id"
    );

    rut_bind_texto_nullable($stmtRutina, ':ultima', $ultimaAnterior);
    $stmtRutina->bindValue(
        ':fecha',
        (string) $alerta['fecha_notificacion'],
        PDO::PARAM_STR
    );
    $stmtRutina->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmtRutina->bindValue(
        ':rutina_id',
        (int) $alerta['rutina_id'],
        PDO::PARAM_INT
    );
    $stmtRutina->execute();

    rut_marcar_notificaciones_leidas($conexion, $alertaId);

    $descripcionMovimiento = 'Se reactivó el periodo de la plantilla "'
        . $alerta['nombre'] . '".';

    if ($solicitudAnteriorId > 0) {
        $descripcionMovimiento .= ' La solicitud '
            . $folioSolicitudAnterior
            . ' se conservó '
            . strtolower($estadoSolicitudAnterior)
            . ' y el periodo quedó listo para generar una solicitud nueva.';
    }

    rut_registrar_movimiento(
        $conexion,
        $adminId,
        'REACTIVAR_RECORDATORIO_RUTINA',
        $descripcionMovimiento,
        'rutina_alertas',
        $alertaId
    );

    $conexion->commit();

    rut_sincronizar_estado($conexion);

    rut_responder(
        true,
        $solicitudAnteriorId > 0
            ? 'El periodo fue reactivado. La solicitud cancelada se conservó en el historial; ahora puedes preparar una solicitud nueva.'
            : 'El recordatorio fue reactivado.',
        rut_paquete_datos($conexion)
    );
}

/* =========================================================================
   SINCRONIZACIÓN
   ========================================================================= */

function rut_sincronizar_estado(PDO $conexion)
{
    $resultado = array(
        'alertas_creadas' => 0,
        'alertas_reactivadas' => 0,
        'notificaciones_creadas' => 0,
        'programaciones_sincronizadas' => 0,
        'rutinas_recalculadas' => 0,
        'solicitudes_reabiertas' => 0,
        'sincronizacion_omitida' => false
    );

    if ($conexion->inTransaction()) {
        $resultado['sincronizacion_omitida'] = true;
        return $resultado;
    }

    $lockAdquirido = rut_adquirir_lock($conexion, 'sm_rutinas_sincronizar_v6', 2);

    if (!$lockAdquirido) {
        $resultado['sincronizacion_omitida'] = true;
        return $resultado;
    }

    try {
        $conexion->beginTransaction();

        /*
         * Una alerta se considera PROGRAMADA en cuanto existe una programación
         * vigente para la solicitud que nació de ese recordatorio.
         */
        $stmtProgramadas = $conexion->prepare(
            "UPDATE rutina_alertas ra
             INNER JOIN programaciones_mantenimiento pm
                     ON pm.solicitud_id = ra.solicitud_id
                    AND pm.es_actual = 1
                    AND pm.estado IN
                        ('PROGRAMADA','CUMPLIDA','VENCIDA','REPROGRAMADA')
             INNER JOIN solicitudes s
                     ON s.id = ra.solicitud_id
                    AND s.estado NOT IN ('RECHAZADO','CANCELADO')
             SET ra.estado = 'PROGRAMADA',
                 ra.programacion_id = pm.id,
                 ra.fecha_atencion = COALESCE(ra.fecha_atencion, NOW())
             WHERE ra.estado = 'PENDIENTE_PROGRAMAR'
               AND ra.solicitud_id IS NOT NULL"
        );
        $stmtProgramadas->execute();
        $resultado['programaciones_sincronizadas'] =
            $stmtProgramadas->rowCount();

        /*
         * Si una solicitud rutinaria se rechaza o cancela, el mismo periodo
         * vuelve a quedar pendiente; no se genera un ciclo adicional.
         */
        $stmtReabrir = $conexion->prepare(
            "SELECT ra.id
             FROM rutina_alertas ra
             INNER JOIN solicitudes s ON s.id = ra.solicitud_id
             INNER JOIN rutinas_mantenimiento r ON r.id = ra.rutina_id
             WHERE s.estado IN ('RECHAZADO','CANCELADO')
               AND ra.estado IN ('PENDIENTE_PROGRAMAR','PROGRAMADA')
             FOR UPDATE"
        );
        $stmtReabrir->execute();
        $alertasReabrir = $stmtReabrir->fetchAll(PDO::FETCH_COLUMN);

        $stmtActualizarReabiertas = $conexion->prepare(
            "UPDATE rutina_alertas ra
             INNER JOIN solicitudes s ON s.id = ra.solicitud_id
             INNER JOIN rutinas_mantenimiento r ON r.id = ra.rutina_id
             SET ra.estado = CASE
                    WHEN r.activo = 1 THEN 'PENDIENTE_PROGRAMAR'
                    ELSE 'CANCELADA'
                 END,
                 ra.solicitud_id = NULL,
                 ra.programacion_id = NULL,
                 ra.atendida_por_admin_id = NULL,
                 ra.fecha_atencion = NULL,
                 ra.motivo_omision = CONCAT(
                    'La solicitud ',
                    s.folio,
                    ' fue ',
                    LOWER(s.estado),
                    '. El mismo periodo volvió a quedar disponible.'
                 )
             WHERE s.estado IN ('RECHAZADO','CANCELADO')
               AND ra.estado IN ('PENDIENTE_PROGRAMAR','PROGRAMADA')"
        );
        $stmtActualizarReabiertas->execute();
        $resultado['solicitudes_reabiertas'] =
            $stmtActualizarReabiertas->rowCount();

        foreach ($alertasReabrir as $alertaReabiertaId) {
            rut_marcar_notificaciones_leidas(
                $conexion,
                (int) $alertaReabiertaId
            );
        }

        /*
         * El siguiente vencimiento se calcula desde el cierre real. Se procesa
         * cada alerta una sola vez mediante ultima_notificacion.
         */
        $stmtTerminadas = $conexion->query(
            "SELECT
                ra.id AS alerta_id,
                ra.rutina_id,
                ra.fecha_notificacion,
                r.frecuencia_cada,
                cm.fecha_hora_cierre
             FROM rutina_alertas ra
             INNER JOIN rutinas_mantenimiento r
                     ON r.id = ra.rutina_id
             INNER JOIN solicitudes s
                     ON s.id = ra.solicitud_id
             INNER JOIN cierres_mantenimiento cm
                     ON cm.solicitud_id = s.id
             WHERE ra.estado = 'PROGRAMADA'
               AND s.estado = 'TERMINADO'
               AND (
                    r.ultima_notificacion IS NULL
                    OR r.ultima_notificacion < ra.fecha_notificacion
               )
             ORDER BY ra.fecha_notificacion, ra.id"
        );

        $stmtActualizarRutina = $conexion->prepare(
            "UPDATE rutinas_mantenimiento
             SET ultima_notificacion = :ultima,
                 proxima_notificacion = :proxima,
                 fecha_actualizacion = NOW()
             WHERE id = :id
               AND (
                    ultima_notificacion IS NULL
                    OR ultima_notificacion < :ultima_comparar
               )"
        );

        foreach ($stmtTerminadas->fetchAll() as $terminada) {
            $fechaCierre = new DateTimeImmutable(
                (string) $terminada['fecha_hora_cierre']
            );

            $proxima = $fechaCierre
                ->setTime(0, 0, 0)
                ->modify(
                    '+' . max(1, (int) $terminada['frecuencia_cada'])
                    . ' days'
                )
                ->format('Y-m-d');

            $stmtActualizarRutina->execute(
                array(
                    ':ultima' => (string) $terminada['fecha_notificacion'],
                    ':proxima' => $proxima,
                    ':id' => (int) $terminada['rutina_id'],
                    ':ultima_comparar' =>
                        (string) $terminada['fecha_notificacion']
                )
            );

            $resultado['rutinas_recalculadas'] +=
                $stmtActualizarRutina->rowCount();
        }

        /*
         * Sólo puede existir un ciclo pendiente o en ejecución por rutina.
         * El índice único rutina/periodo y el UPSERT evitan duplicados incluso
         * si coincide esta sincronización con el evento de MySQL.
         */
        $rutinasVencidas = $conexion->query(
            "SELECT
                r.id,
                r.nombre,
                r.descripcion_actividad,
                r.tipo_rutina,
                r.departamento_id,
                r.area_id,
                r.proceso_id,
                r.equipo_id,
                r.prioridad,
                r.tipo_falla_id,
                r.causa_averia_id,
                r.trabajo_peligroso,
                r.detalle_trabajo_peligroso,
                r.nivel_riesgo,
                r.requiere_paro_equipo,
                r.proxima_notificacion,
                e.codigo_equipo,
                e.nombre_equipo,
                d.nombre AS departamento,
                a.nombre AS area,
                p.nombre AS proceso,
                tf.nombre AS tipo_falla,
                ca.nombre AS causa_averia
             FROM rutinas_mantenimiento r
             INNER JOIN equipos e ON e.id = r.equipo_id
             INNER JOIN departamentos d ON d.id = r.departamento_id
             INNER JOIN areas a ON a.id = r.area_id
             INNER JOIN procesos p ON p.id = r.proceso_id
             LEFT JOIN tipos_falla tf ON tf.id = r.tipo_falla_id
             LEFT JOIN causas_averia ca ON ca.id = r.causa_averia_id
             WHERE r.activo = 1
               AND r.proxima_notificacion <= CURDATE()
               AND NOT EXISTS
               (
                   SELECT 1
                   FROM rutina_alertas ra
                   LEFT JOIN solicitudes s ON s.id = ra.solicitud_id
                   WHERE ra.rutina_id = r.id
                     AND
                     (
                         ra.estado = 'PENDIENTE_PROGRAMAR'
                         OR
                         (
                             ra.estado = 'PROGRAMADA'
                             AND ra.solicitud_id IS NOT NULL
                             AND s.estado NOT IN
                                 ('TERMINADO','RECHAZADO','CANCELADO')
                         )
                     )
               )
             ORDER BY r.proxima_notificacion, r.id"
        )->fetchAll();

        foreach ($rutinasVencidas as $rutina) {
            $estadoCreacion = rut_crear_o_reactivar_alerta(
                $conexion,
                (int) $rutina['id'],
                (string) $rutina['proxima_notificacion'],
                rut_crear_snapshot_desde_fila(
                    $rutina,
                    rut_obtener_recursos_rutina(
                        $conexion,
                        (int) $rutina['id']
                    )
                ),
                false
            );

            if ($estadoCreacion === 'CREADA') {
                $resultado['alertas_creadas']++;
            } elseif ($estadoCreacion === 'REACTIVADA') {
                $resultado['alertas_reactivadas']++;
            }
        }

        /*
         * Cierra notificaciones que ya no representan el estado actual.
         */
        $conexion->exec(
            "UPDATE notificaciones n
             INNER JOIN rutina_alertas ra
                     ON ra.id = n.rutina_alerta_id
             INNER JOIN rutinas_mantenimiento r
                     ON r.id = ra.rutina_id
             SET n.leida = 1,
                 n.fecha_lectura = COALESCE(n.fecha_lectura, NOW())
             WHERE n.leida = 0
               AND
               (
                   r.activo = 0
                   OR ra.estado IN ('PROGRAMADA','OMITIDA','CANCELADA')
                   OR
                   (
                       ra.estado = 'PENDIENTE_PROGRAMAR'
                       AND ra.solicitud_id IS NOT NULL
                       AND n.titulo = 'Rutina pendiente de preparar'
                   )
                   OR
                   (
                       ra.estado = 'PENDIENTE_PROGRAMAR'
                       AND ra.solicitud_id IS NULL
                       AND n.titulo = 'Rutina pendiente de asignar'
                   )
               )"
        );

        $pendientes = $conexion->query(
            "SELECT
                ra.id AS alerta_id,
                ra.solicitud_id,
                ra.fecha_notificacion,
                r.nombre,
                e.codigo_equipo,
                e.nombre_equipo,
                ad.id AS admin_id
             FROM rutina_alertas ra
             INNER JOIN rutinas_mantenimiento r
                     ON r.id = ra.rutina_id
                    AND r.activo = 1
             INNER JOIN equipos e ON e.id = r.equipo_id
             INNER JOIN administradores ad ON ad.activo = 1
             WHERE ra.estado = 'PENDIENTE_PROGRAMAR'
               AND ra.fecha_notificacion <= CURDATE()
             ORDER BY ra.fecha_notificacion, ra.id, ad.id"
        )->fetchAll();

        foreach ($pendientes as $pendiente) {
            $tieneSolicitud = (int) $pendiente['solicitud_id'] > 0;
            $etapa = $tieneSolicitud ? 'ASIGNAR' : 'PREPARAR';
            $titulo = $tieneSolicitud
                ? 'Rutina pendiente de asignar'
                : 'Rutina pendiente de preparar';

            $mensaje = $tieneSolicitud
                ? 'La solicitud rutinaria de "'
                    . $pendiente['nombre']
                    . '" sigue sin programarse. Elige la fecha y los técnicos responsables.'
                : 'La rutina "'
                    . $pendiente['nombre']
                    . '" del equipo '
                    . $pendiente['codigo_equipo']
                    . ' - '
                    . $pendiente['nombre_equipo']
                    . ' ya debe realizarse. Prepara la solicitud para elegir fecha y técnicos.';

            $tipo = (string) $pendiente['fecha_notificacion'] < date('Y-m-d')
                ? 'DANGER'
                : 'WARNING';

            $clave = 'RUT:'
                . (int) $pendiente['alerta_id']
                . ':'
                . (int) $pendiente['admin_id']
                . ':'
                . $etapa
                . ':'
                . date('Ymd');

            /*
             * Mantiene como máximo una notificación no leída por alerta,
             * administrador y etapa; las de días anteriores quedan en historial.
             */
            $stmtCerrarAnteriores = $conexion->prepare(
                "UPDATE notificaciones
                 SET leida = 1,
                     fecha_lectura = COALESCE(fecha_lectura, NOW())
                 WHERE tipo_usuario = 'ADMIN'
                   AND usuario_id = :admin_id
                   AND rutina_alerta_id = :alerta_id
                   AND leida = 0
                   AND (
                        clave_dedupe IS NULL
                        OR clave_dedupe <> :clave
                   )"
            );
            $stmtCerrarAnteriores->execute(
                array(
                    ':admin_id' => (int) $pendiente['admin_id'],
                    ':alerta_id' => (int) $pendiente['alerta_id'],
                    ':clave' => $clave
                )
            );

            $stmtNotificacion = $conexion->prepare(
                "INSERT IGNORE INTO notificaciones
                (
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
                    fecha_creacion
                )
                VALUES
                (
                    'ADMIN',
                    :admin_id,
                    :solicitud_id,
                    :alerta_id,
                    NULL,
                    :clave,
                    :titulo,
                    :mensaje,
                    :tipo,
                    0,
                    NOW()
                )"
            );

            $stmtNotificacion->bindValue(
                ':admin_id',
                (int) $pendiente['admin_id'],
                PDO::PARAM_INT
            );
            rut_bind_entero_nullable(
                $stmtNotificacion,
                ':solicitud_id',
                (int) $pendiente['solicitud_id']
            );
            $stmtNotificacion->bindValue(
                ':alerta_id',
                (int) $pendiente['alerta_id'],
                PDO::PARAM_INT
            );
            $stmtNotificacion->bindValue(':clave', $clave, PDO::PARAM_STR);
            $stmtNotificacion->bindValue(':titulo', $titulo, PDO::PARAM_STR);
            $stmtNotificacion->bindValue(
                ':mensaje',
                rut_recortar($mensaje, 1000),
                PDO::PARAM_STR
            );
            $stmtNotificacion->bindValue(':tipo', $tipo, PDO::PARAM_STR);
            $stmtNotificacion->execute();

            $resultado['notificaciones_creadas'] +=
                $stmtNotificacion->rowCount();
        }

        $conexion->commit();
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        throw $e;
    } finally {
        rut_liberar_lock($conexion, 'sm_rutinas_sincronizar_v6');
    }

    return $resultado;
}

/* =========================================================================
   CONSULTAS
   ========================================================================= */

function rut_obtener_resumen(PDO $conexion)
{
    $rutinas = $conexion->query(
        "SELECT
            COALESCE(SUM(activo = 1), 0) AS activas,
            COALESCE(SUM(activo = 0), 0) AS inactivas
         FROM rutinas_mantenimiento"
    )->fetch();

    $alertas = $conexion->query(
        "SELECT
            COALESCE(
                SUM(
                    estado = 'PENDIENTE_PROGRAMAR'
                    AND fecha_notificacion <= CURDATE()
                ),
                0
            ) AS pendientes,
            COALESCE(
                SUM(
                    estado = 'PENDIENTE_PROGRAMAR'
                    AND fecha_notificacion < CURDATE()
                ),
                0
            ) AS vencidas,
            COALESCE(
                SUM(
                    estado = 'PENDIENTE_PROGRAMAR'
                    AND solicitud_id IS NOT NULL
                ),
                0
            ) AS por_programar
         FROM rutina_alertas"
    )->fetch();

    return array(
        'activas' => (int) $rutinas['activas'],
        'inactivas' => (int) $rutinas['inactivas'],
        'pendientes' => (int) $alertas['pendientes'],
        'vencidas' => (int) $alertas['vencidas'],
        'por_programar' => (int) $alertas['por_programar']
    );
}

function rut_obtener_listado_solicitado(PDO $conexion)
{
    $filtros = rut_leer_filtros_listado();

    if ($filtros['vista'] === 'PLANTILLAS') {
        return rut_listar_rutinas_paginadas($conexion, $filtros);
    }

    return rut_listar_alertas_paginadas($conexion, $filtros);
}

function rut_leer_filtros_listado()
{
    $vista = strtoupper(
        rut_limpiar_texto(
            isset($_GET['vista']) ? $_GET['vista'] : 'ALERTAS'
        )
    );

    if (!in_array($vista, array('ALERTAS', 'PLANTILLAS'), true)) {
        $vista = 'ALERTAS';
    }

    $pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
    $pagina = max(1, $pagina);

    $cantidad = isset($_GET['cantidad']) ? (int) $_GET['cantidad'] : 10;
    $cantidadesPermitidas = array(10, 25, 50, 100);

    if (!in_array($cantidad, $cantidadesPermitidas, true)) {
        $cantidad = 10;
    }

    $busqueda = rut_limpiar_texto(
        isset($_GET['busqueda']) ? $_GET['busqueda'] : ''
    );

    if (function_exists('mb_substr')) {
        $busqueda = mb_substr($busqueda, 0, 100, 'UTF-8');
    } else {
        $busqueda = substr($busqueda, 0, 100);
    }

    $estado = strtoupper(
        rut_limpiar_texto(
            isset($_GET['estado']) ? $_GET['estado'] : ''
        )
    );

    $estadosPermitidos = $vista === 'ALERTAS'
        ? array(
            '',
            'VENCIDA',
            'HOY',
            'PROXIMA',
            'LISTA_PROGRAMAR',
            'PROGRAMADA',
            'OMITIDA',
            'CANCELADA'
        )
        : array('', 'ACTIVA', 'INACTIVA', 'VENCIDA', 'PROXIMA');

    if (!in_array($estado, $estadosPermitidos, true)) {
        $estado = '';
    }

    $equipoId = isset($_GET['equipo_id'])
        ? (int) $_GET['equipo_id']
        : 0;

    $equipoId = max(0, $equipoId);

    return array(
        'vista' => $vista,
        'pagina' => $pagina,
        'cantidad' => $cantidad,
        'busqueda' => $busqueda,
        'estado' => $estado,
        'equipo_id' => $equipoId
    );
}

function rut_listar_rutinas_paginadas(PDO $conexion, array $filtros)
{
    $desde = " FROM rutinas_mantenimiento r
        INNER JOIN equipos e ON e.id = r.equipo_id
        INNER JOIN departamentos d ON d.id = r.departamento_id
        INNER JOIN areas a ON a.id = r.area_id
        INNER JOIN procesos p ON p.id = r.proceso_id
        LEFT JOIN tipos_falla tf ON tf.id = r.tipo_falla_id
        LEFT JOIN causas_averia ca ON ca.id = r.causa_averia_id";

    $condiciones = array('1 = 1');
    $parametros = array();

    if ((int) $filtros['equipo_id'] > 0) {
        $condiciones[] = 'r.equipo_id = :equipo_id';
        $parametros[':equipo_id'] = (int) $filtros['equipo_id'];
    }

    switch ((string) $filtros['estado']) {
        case 'ACTIVA':
            $condiciones[] = 'r.activo = 1';
            break;

        case 'INACTIVA':
            $condiciones[] = 'r.activo = 0';
            break;

        case 'VENCIDA':
            $condiciones[] = 'r.activo = 1';
            $condiciones[] = 'r.proxima_notificacion <= CURDATE()';
            break;

        case 'PROXIMA':
            $condiciones[] = 'r.activo = 1';
            $condiciones[] = 'r.proxima_notificacion > CURDATE()';
            break;
    }

    if ((string) $filtros['busqueda'] !== '') {
        $columnas = array(
            'r.nombre',
            'r.tipo_rutina',
            'r.descripcion_actividad',
            'e.codigo_equipo',
            'e.nombre_equipo',
            'd.nombre',
            'a.nombre',
            'p.nombre'
        );

        rut_agregar_busqueda_sql(
            $condiciones,
            $parametros,
            $columnas,
            (string) $filtros['busqueda'],
            'rut'
        );
    }

    $where = ' WHERE ' . implode(' AND ', $condiciones);

    $stmtTotal = $conexion->prepare('SELECT COUNT(*)' . $desde . $where);
    rut_vincular_parametros($stmtTotal, $parametros);
    $stmtTotal->execute();
    $total = (int) $stmtTotal->fetchColumn();

    $paginacion = rut_calcular_paginacion(
        $total,
        (int) $filtros['pagina'],
        (int) $filtros['cantidad']
    );

    $sql = "SELECT
                r.id,
                r.nombre,
                r.descripcion_actividad,
                r.tipo_rutina,
                r.departamento_id,
                r.area_id,
                r.proceso_id,
                r.equipo_id,
                r.prioridad,
                r.tipo_falla_id,
                r.causa_averia_id,
                r.trabajo_peligroso,
                r.detalle_trabajo_peligroso,
                r.nivel_riesgo,
                r.requiere_paro_equipo,
                r.frecuencia_cada,
                r.fecha_inicio,
                r.ultima_notificacion,
                r.proxima_notificacion,
                r.activo,
                r.fecha_registro,
                r.fecha_actualizacion,
                e.codigo_equipo,
                e.nombre_equipo,
                d.nombre AS departamento,
                a.nombre AS area,
                p.nombre AS proceso,
                tf.nombre AS tipo_falla,
                ca.nombre AS causa_averia,
                (
                    SELECT COUNT(*)
                    FROM rutina_alertas ra
                    WHERE ra.rutina_id = r.id
                      AND ra.estado = 'PROGRAMADA'
                ) AS total_programadas,
                (
                    SELECT COUNT(*)
                    FROM rutina_alertas ra
                    WHERE ra.rutina_id = r.id
                      AND ra.estado = 'OMITIDA'
                ) AS total_omitidas,
                (
                    SELECT COUNT(*)
                    FROM rutina_alertas ra
                    WHERE ra.rutina_id = r.id
                      AND ra.estado = 'PENDIENTE_PROGRAMAR'
                ) AS total_pendientes,
                (
                    SELECT ra2.solicitud_id
                    FROM rutina_alertas ra2
                    LEFT JOIN solicitudes s2 ON s2.id = ra2.solicitud_id
                    WHERE ra2.rutina_id = r.id
                      AND (
                          ra2.estado = 'PENDIENTE_PROGRAMAR'
                          OR (
                              ra2.estado = 'PROGRAMADA'
                              AND s2.estado NOT IN
                                  ('TERMINADO','RECHAZADO','CANCELADO')
                          )
                      )
                    ORDER BY ra2.fecha_notificacion DESC, ra2.id DESC
                    LIMIT 1
                ) AS solicitud_en_curso_id,
                (
                    SELECT s3.folio
                    FROM rutina_alertas ra3
                    INNER JOIN solicitudes s3 ON s3.id = ra3.solicitud_id
                    WHERE ra3.rutina_id = r.id
                      AND s3.estado NOT IN
                          ('TERMINADO','RECHAZADO','CANCELADO')
                    ORDER BY ra3.fecha_notificacion DESC, ra3.id DESC
                    LIMIT 1
                ) AS folio_en_curso"
        . $desde
        . $where
        . " ORDER BY
                r.activo DESC,
                CASE
                    WHEN r.proxima_notificacion <= CURDATE() THEN 0
                    ELSE 1
                END,
                r.proxima_notificacion,
                r.nombre,
                r.id
            LIMIT :limite OFFSET :desplazamiento";

    $stmt = $conexion->prepare($sql);
    rut_vincular_parametros($stmt, $parametros);
    $stmt->bindValue(':limite', $paginacion['cantidad'], PDO::PARAM_INT);
    $stmt->bindValue(
        ':desplazamiento',
        $paginacion['desplazamiento'],
        PDO::PARAM_INT
    );
    $stmt->execute();

    $rutinas = $stmt->fetchAll();
    $idsRutinas = array_map(
        static function ($fila) {
            return (int) $fila['id'];
        },
        $rutinas
    );
    $recursosPorRutina = rut_obtener_recursos_rutinas(
        $conexion,
        $idsRutinas
    );

    foreach ($rutinas as &$rutina) {
        $recursos = isset($recursosPorRutina[(int) $rutina['id']])
            ? $recursosPorRutina[(int) $rutina['id']]
            : array();
        $rutina['recursos'] = $recursos;
        $rutina['herramientas'] = rut_filtrar_recursos_tipo(
            $recursos,
            RSM_TIPO_HERRAMIENTA
        );
        $rutina['refacciones'] = rut_filtrar_recursos_tipo(
            $recursos,
            RSM_TIPO_REFACCION
        );
        $rutina['total_herramientas'] = count($rutina['herramientas']);
        $rutina['total_refacciones'] = count($rutina['refacciones']);
        $dias = max(1, (int) $rutina['frecuencia_cada']);

        $rutina['frecuencia_texto'] = $dias === 1
            ? 'Cada día'
            : 'Cada ' . $dias . ' días';

        $rutina['proxima_texto'] = rut_fecha_relativa(
            (string) $rutina['proxima_notificacion']
        );

        $rutina['descripcion_corta'] = rut_recortar(
            (string) $rutina['descripcion_actividad'],
            170
        );

        $rutina['ciclo_en_curso'] =
            (int) $rutina['solicitud_en_curso_id'] > 0
            || (int) $rutina['total_pendientes'] > 0;
    }
    unset($rutina);

    return array(
        'vista' => 'PLANTILLAS',
        'items' => $rutinas,
        'paginacion' => rut_datos_paginacion_respuesta($paginacion)
    );
}

function rut_listar_alertas_paginadas(PDO $conexion, array $filtros)
{
    $desde = " FROM rutina_alertas ra
        INNER JOIN rutinas_mantenimiento r ON r.id = ra.rutina_id
        INNER JOIN equipos er ON er.id = r.equipo_id
        INNER JOIN departamentos dr ON dr.id = r.departamento_id
        INNER JOIN areas ar ON ar.id = r.area_id
        INNER JOIN procesos pr ON pr.id = r.proceso_id
        LEFT JOIN tipos_falla tfr ON tfr.id = r.tipo_falla_id
        LEFT JOIN causas_averia car ON car.id = r.causa_averia_id
        LEFT JOIN solicitudes s ON s.id = ra.solicitud_id
        LEFT JOIN equipos es ON es.id = s.equipo_id
        LEFT JOIN departamentos ds ON ds.id = s.departamento_id
        LEFT JOIN areas ats ON ats.id = s.area_id
        LEFT JOIN procesos ps ON ps.id = s.proceso_id
        LEFT JOIN tipos_falla tfs ON tfs.id = s.tipo_falla_id
        LEFT JOIN causas_averia cas ON cas.id = s.causa_averia_id
        LEFT JOIN programaciones_mantenimiento pm
               ON pm.id = ra.programacion_id";

    $condiciones = array('1 = 1');
    $parametros = array();

    if ((int) $filtros['equipo_id'] > 0) {
        $condiciones[] =
            'COALESCE(s.equipo_id, r.equipo_id) = :equipo_id';
        $parametros[':equipo_id'] = (int) $filtros['equipo_id'];
    }

    switch ((string) $filtros['estado']) {
        case 'VENCIDA':
            $condiciones[] = "ra.estado = 'PENDIENTE_PROGRAMAR'";
            $condiciones[] = 'ra.solicitud_id IS NULL';
            $condiciones[] = 'ra.fecha_notificacion < CURDATE()';
            break;

        case 'HOY':
            $condiciones[] = "ra.estado = 'PENDIENTE_PROGRAMAR'";
            $condiciones[] = 'ra.solicitud_id IS NULL';
            $condiciones[] = 'ra.fecha_notificacion = CURDATE()';
            break;

        case 'PROXIMA':
            $condiciones[] = "ra.estado = 'PENDIENTE_PROGRAMAR'";
            $condiciones[] = 'ra.solicitud_id IS NULL';
            $condiciones[] = 'ra.fecha_notificacion > CURDATE()';
            break;

        case 'LISTA_PROGRAMAR':
            $condiciones[] = "ra.estado = 'PENDIENTE_PROGRAMAR'";
            $condiciones[] = 'ra.solicitud_id IS NOT NULL';
            break;

        case 'PROGRAMADA':
            $condiciones[] = "ra.estado = 'PROGRAMADA'";
            break;

        case 'OMITIDA':
            $condiciones[] = "ra.estado = 'OMITIDA'";
            break;

        case 'CANCELADA':
            $condiciones[] = "ra.estado = 'CANCELADA'";
            break;
    }

    if ((string) $filtros['busqueda'] !== '') {
        $columnas = array(
            'r.nombre',
            'r.tipo_rutina',
            'r.descripcion_actividad',
            'er.codigo_equipo',
            'er.nombre_equipo',
            'dr.nombre',
            'ar.nombre',
            'pr.nombre',
            'COALESCE(s.folio, \'\')',
            'COALESCE(s.descripcion_solicitud, \'\')',
            'COALESCE(es.codigo_equipo, \'\')',
            'COALESCE(es.nombre_equipo, \'\')',
            'COALESCE(ds.nombre, \'\')',
            'COALESCE(ats.nombre, \'\')',
            'COALESCE(ps.nombre, \'\')',
            'COALESCE(ra.datos_snapshot, \'\')'
        );

        rut_agregar_busqueda_sql(
            $condiciones,
            $parametros,
            $columnas,
            (string) $filtros['busqueda'],
            'ale'
        );
    }

    $where = ' WHERE ' . implode(' AND ', $condiciones);

    $stmtTotal = $conexion->prepare('SELECT COUNT(*)' . $desde . $where);
    rut_vincular_parametros($stmtTotal, $parametros);
    $stmtTotal->execute();
    $total = (int) $stmtTotal->fetchColumn();

    $paginacion = rut_calcular_paginacion(
        $total,
        (int) $filtros['pagina'],
        (int) $filtros['cantidad']
    );

    $sql = "SELECT
                ra.id,
                ra.rutina_id,
                ra.fecha_notificacion,
                ra.periodo_clave,
                ra.datos_snapshot,
                ra.estado,
                ra.solicitud_id,
                ra.programacion_id,
                ra.atendida_por_admin_id,
                ra.motivo_omision,
                ra.fecha_atencion,
                ra.fecha_registro,

                r.nombre AS actual_nombre,
                r.descripcion_actividad AS actual_descripcion_actividad,
                r.tipo_rutina AS actual_tipo_rutina,
                r.prioridad AS actual_prioridad,
                r.trabajo_peligroso AS actual_trabajo_peligroso,
                r.detalle_trabajo_peligroso AS actual_detalle_trabajo_peligroso,
                r.nivel_riesgo AS actual_nivel_riesgo,
                r.requiere_paro_equipo AS actual_requiere_paro_equipo,
                r.frecuencia_cada,
                r.activo AS rutina_activa,
                r.departamento_id AS actual_departamento_id,
                r.area_id AS actual_area_id,
                r.proceso_id AS actual_proceso_id,
                r.equipo_id AS actual_equipo_id,
                r.tipo_falla_id AS actual_tipo_falla_id,
                r.causa_averia_id AS actual_causa_averia_id,

                er.codigo_equipo AS actual_codigo_equipo,
                er.nombre_equipo AS actual_nombre_equipo,
                dr.nombre AS actual_departamento,
                ar.nombre AS actual_area,
                pr.nombre AS actual_proceso,
                tfr.nombre AS actual_tipo_falla,
                car.nombre AS actual_causa_averia,

                s.folio,
                s.estado AS estado_solicitud,
                s.descripcion_solicitud,
                s.prioridad AS solicitud_prioridad,
                s.trabajo_peligroso AS solicitud_trabajo_peligroso,
                s.detalle_trabajo_peligroso AS solicitud_detalle_trabajo_peligroso,
                s.nivel_riesgo AS solicitud_nivel_riesgo,
                s.requiere_paro_equipo AS solicitud_requiere_paro,
                s.departamento_id AS solicitud_departamento_id,
                s.area_id AS solicitud_area_id,
                s.proceso_id AS solicitud_proceso_id,
                s.equipo_id AS solicitud_equipo_id,
                s.tipo_falla_id AS solicitud_tipo_falla_id,
                s.causa_averia_id AS solicitud_causa_averia_id,

                es.codigo_equipo AS solicitud_codigo_equipo,
                es.nombre_equipo AS solicitud_nombre_equipo,
                ds.nombre AS solicitud_departamento,
                ats.nombre AS solicitud_area,
                ps.nombre AS solicitud_proceso,
                tfs.nombre AS solicitud_tipo_falla,
                cas.nombre AS solicitud_causa_averia,

                pm.fecha_programada,
                pm.fecha_limite,
                pm.estado AS estado_programacion"
        . $desde
        . $where
        . " ORDER BY
                CASE ra.estado
                    WHEN 'PENDIENTE_PROGRAMAR' THEN 0
                    WHEN 'PROGRAMADA' THEN 1
                    WHEN 'OMITIDA' THEN 2
                    ELSE 3
                END,
                ra.fecha_notificacion DESC,
                ra.id DESC
            LIMIT :limite OFFSET :desplazamiento";

    $stmt = $conexion->prepare($sql);
    rut_vincular_parametros($stmt, $parametros);
    $stmt->bindValue(':limite', $paginacion['cantidad'], PDO::PARAM_INT);
    $stmt->bindValue(
        ':desplazamiento',
        $paginacion['desplazamiento'],
        PDO::PARAM_INT
    );
    $stmt->execute();

    $alertas = $stmt->fetchAll();
    $idsRutinasAlertas = array();
    $idsSolicitudesAlertas = array();

    foreach ($alertas as $filaAlerta) {
        $idsRutinasAlertas[] = (int) $filaAlerta['rutina_id'];
        if ((int) $filaAlerta['solicitud_id'] > 0) {
            $idsSolicitudesAlertas[] = (int) $filaAlerta['solicitud_id'];
        }
    }

    $recursosPorRutinaAlertas = rut_obtener_recursos_rutinas(
        $conexion,
        array_values(array_unique($idsRutinasAlertas))
    );
    $recursosPorSolicitudAlertas = rut_obtener_recursos_solicitudes(
        $conexion,
        array_values(array_unique($idsSolicitudesAlertas))
    );

    foreach ($alertas as &$alerta) {
        $alerta = rut_normalizar_alerta_listado($alerta);

        if (
            (int) $alerta['solicitud_id'] > 0
            && isset(
                $recursosPorSolicitudAlertas[(int) $alerta['solicitud_id']]
            )
        ) {
            $alerta['recursos'] =
                $recursosPorSolicitudAlertas[(int) $alerta['solicitud_id']];
        } elseif (!array_key_exists('recursos', $alerta)) {
            $alerta['recursos'] = isset(
                $recursosPorRutinaAlertas[(int) $alerta['rutina_id']]
            )
                ? $recursosPorRutinaAlertas[(int) $alerta['rutina_id']]
                : array();
        }

        $alerta['herramientas'] = rut_filtrar_recursos_tipo(
            is_array($alerta['recursos']) ? $alerta['recursos'] : array(),
            RSM_TIPO_HERRAMIENTA
        );
        $alerta['refacciones'] = rut_filtrar_recursos_tipo(
            is_array($alerta['recursos']) ? $alerta['recursos'] : array(),
            RSM_TIPO_REFACCION
        );
        $alerta['total_herramientas'] = count($alerta['herramientas']);
        $alerta['total_refacciones'] = count($alerta['refacciones']);
        $alerta['situacion'] = rut_situacion_alerta($alerta);
        $alerta['fecha_texto'] = rut_fecha_es(
            (string) $alerta['fecha_notificacion']
        );
        $alerta['fecha_relativa'] = rut_fecha_relativa(
            (string) $alerta['fecha_notificacion']
        );
        $alerta['descripcion_corta'] = rut_recortar(
            (string) $alerta['descripcion_actividad'],
            180
        );
    }
    unset($alerta);

    return array(
        'vista' => 'ALERTAS',
        'items' => $alertas,
        'paginacion' => rut_datos_paginacion_respuesta($paginacion)
    );
}

function rut_agregar_busqueda_sql(
    array &$condiciones,
    array &$parametros,
    array $columnas,
    $busqueda,
    $prefijo
) {
    $fragmentos = array();
    $valor = '%' . $busqueda . '%';

    foreach ($columnas as $indice => $columna) {
        $clave = ':' . $prefijo . '_busqueda_' . $indice;
        $fragmentos[] = $columna . ' LIKE ' . $clave;
        $parametros[$clave] = $valor;
    }

    if ($fragmentos !== array()) {
        $condiciones[] = '(' . implode(' OR ', $fragmentos) . ')';
    }
}

function rut_vincular_parametros(PDOStatement $stmt, array $parametros)
{
    foreach ($parametros as $clave => $valor) {
        $tipo = is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($clave, $valor, $tipo);
    }
}

function rut_calcular_paginacion($total, $paginaSolicitada, $cantidad)
{
    $total = max(0, (int) $total);
    $cantidad = in_array((int) $cantidad, array(10, 25, 50, 100), true)
        ? (int) $cantidad
        : 10;

    $totalPaginas = $total > 0
        ? (int) ceil($total / $cantidad)
        : 1;

    $pagina = min(max(1, (int) $paginaSolicitada), $totalPaginas);
    $desplazamiento = ($pagina - 1) * $cantidad;
    $desde = $total > 0 ? $desplazamiento + 1 : 0;
    $hasta = $total > 0
        ? min($desplazamiento + $cantidad, $total)
        : 0;

    return array(
        'pagina' => $pagina,
        'cantidad' => $cantidad,
        'total' => $total,
        'total_paginas' => $totalPaginas,
        'desplazamiento' => $desplazamiento,
        'desde' => $desde,
        'hasta' => $hasta
    );
}

function rut_datos_paginacion_respuesta(array $paginacion)
{
    return array(
        'pagina' => (int) $paginacion['pagina'],
        'cantidad' => (int) $paginacion['cantidad'],
        'total' => (int) $paginacion['total'],
        'total_paginas' => (int) $paginacion['total_paginas'],
        'desde' => (int) $paginacion['desde'],
        'hasta' => (int) $paginacion['hasta']
    );
}

function rut_obtener_catalogos(PDO $conexion)
{
    return array(
        'equipos' => $conexion->query(
            "SELECT
                e.id,
                e.codigo_equipo,
                e.nombre_equipo,
                e.departamento_id,
                e.area_id,
                e.proceso_id,
                d.nombre AS departamento,
                a.nombre AS area,
                p.nombre AS proceso
             FROM equipos e
             INNER JOIN departamentos d
                     ON d.id = e.departamento_id
             INNER JOIN areas a ON a.id = e.area_id
             INNER JOIN procesos p ON p.id = e.proceso_id
             WHERE e.activo = 1
               AND d.activo = 1
               AND a.activo = 1
               AND p.activo = 1
             ORDER BY e.nombre_equipo, e.codigo_equipo"
        )->fetchAll(),

        'tipos_falla' => $conexion->query(
            "SELECT id, nombre
             FROM tipos_falla
             WHERE activo = 1
             ORDER BY nombre"
        )->fetchAll(),

        'causas_averia' => $conexion->query(
            "SELECT id, nombre
             FROM causas_averia
             WHERE activo = 1
             ORDER BY nombre"
        )->fetchAll(),

        'tipos_rutina' => rut_tipos_rutina()
    );
}

/* =========================================================================
   ESQUEMA Y AUTOMATIZACIÓN
   ========================================================================= */

function rut_preparar_esquema(PDO $conexion)
{
    $tablasNecesarias = array(
        'administradores',
        'areas',
        'causas_averia',
        'cierres_mantenimiento',
        'configuracion_sistema',
        'departamentos',
        'equipos',
        'historial_solicitudes',
        'movimientos_sistema',
        'notificaciones',
        'procesos',
        'programaciones_mantenimiento',
        'rutinas_mantenimiento',
        'rutina_alertas',
        'rutina_recursos',
        'recursos_mantenimiento',
        'secuencias_folios',
        'solicitudes',
        'solicitud_recursos_recomendados',
        'tipos_falla'
    );

    foreach ($tablasNecesarias as $tabla) {
        if (!rut_tabla_existe($conexion, $tabla)) {
            throw new RuntimeException(
                'La base de datos no contiene la tabla requerida "'
                . $tabla
                . '". Importa la base de datos completa antes de usar Rutinas.'
            );
        }
    }

    $versionObjetivo = '7';

    $stmtVersion = $conexion->prepare(
        "SELECT valor
         FROM configuracion_sistema
         WHERE clave = 'rutinas_esquema_version'
         LIMIT 1"
    );
    $stmtVersion->execute();
    $versionActual = (string) $stmtVersion->fetchColumn();

    if ($versionActual === $versionObjetivo) {
        return;
    }

    if (!rut_adquirir_lock($conexion, 'sm_rutinas_esquema_v7', 10)) {
        throw new RuntimeException(
            'Otro proceso está actualizando el módulo de Rutinas. Intenta de nuevo en unos segundos.'
        );
    }

    try {
        $stmtVersion->execute();
        $versionActual = (string) $stmtVersion->fetchColumn();

        if ($versionActual === $versionObjetivo) {
            return;
        }

        if (
            !rut_columna_existe(
                $conexion,
                'rutinas_mantenimiento',
                'requiere_paro_equipo'
            )
        ) {
            $conexion->exec(
                "ALTER TABLE rutinas_mantenimiento
                 ADD COLUMN requiere_paro_equipo
                     TINYINT(1) NOT NULL DEFAULT 0
                 AFTER nivel_riesgo"
            );
        }

        if (
            !rut_columna_existe(
                $conexion,
                'rutina_alertas',
                'datos_snapshot'
            )
        ) {
            $conexion->exec(
                "ALTER TABLE rutina_alertas
                 ADD COLUMN datos_snapshot LONGTEXT NULL
                 AFTER periodo_clave"
            );
        }

        if (
            !rut_indice_existe(
                $conexion,
                'rutinas_mantenimiento',
                'idx_rutinas_listado'
            )
        ) {
            $conexion->exec(
                "ALTER TABLE rutinas_mantenimiento
                 ADD KEY idx_rutinas_listado
                     (activo, proxima_notificacion, equipo_id, id)"
            );
        }

        if (
            !rut_indice_existe(
                $conexion,
                'rutina_alertas',
                'idx_rutina_alertas_listado'
            )
        ) {
            $conexion->exec(
                "ALTER TABLE rutina_alertas
                 ADD KEY idx_rutina_alertas_listado
                     (estado, fecha_notificacion, solicitud_id, rutina_id, id)"
            );
        }

        if (
            !rut_columna_existe(
                $conexion,
                'notificaciones',
                'clave_dedupe'
            )
        ) {
            $conexion->exec(
                "ALTER TABLE notificaciones
                 ADD COLUMN clave_dedupe VARCHAR(191) NULL
                 AFTER ejecucion_id"
            );
        }

        if (
            !rut_indice_existe(
                $conexion,
                'notificaciones',
                'uk_notificacion_clave_dedupe'
            )
        ) {
            $conexion->exec(
                "ALTER TABLE notificaciones
                 ADD UNIQUE KEY uk_notificacion_clave_dedupe
                     (clave_dedupe)"
            );
        }

        /*
         * Limpieza de notificaciones antiguas: después de instalar la clave
         * deduplicadora se recrea únicamente el aviso vigente de cada etapa.
         */
        $conexion->exec(
            "UPDATE notificaciones
             SET leida = 1,
                 fecha_lectura = COALESCE(fecha_lectura, NOW())
             WHERE rutina_alerta_id IS NOT NULL
               AND leida = 0
               AND clave_dedupe IS NULL"
        );

        /*
         * Guarda una fotografía de la plantilla para que editarla después no
         * cambie el historial de periodos anteriores.
         */
        $faltantes = $conexion->query(
            "SELECT
                ra.id AS alerta_id,
                r.id AS rutina_id,
                r.nombre,
                r.descripcion_actividad,
                r.tipo_rutina,
                r.departamento_id,
                r.area_id,
                r.proceso_id,
                r.equipo_id,
                r.prioridad,
                r.tipo_falla_id,
                r.causa_averia_id,
                r.trabajo_peligroso,
                r.detalle_trabajo_peligroso,
                r.nivel_riesgo,
                r.requiere_paro_equipo,
                e.codigo_equipo,
                e.nombre_equipo,
                d.nombre AS departamento,
                a.nombre AS area,
                p.nombre AS proceso,
                tf.nombre AS tipo_falla,
                ca.nombre AS causa_averia
             FROM rutina_alertas ra
             INNER JOIN rutinas_mantenimiento r ON r.id = ra.rutina_id
             INNER JOIN equipos e ON e.id = r.equipo_id
             INNER JOIN departamentos d ON d.id = r.departamento_id
             INNER JOIN areas a ON a.id = r.area_id
             INNER JOIN procesos p ON p.id = r.proceso_id
             LEFT JOIN tipos_falla tf ON tf.id = r.tipo_falla_id
             LEFT JOIN causas_averia ca ON ca.id = r.causa_averia_id
             WHERE ra.datos_snapshot IS NULL
                OR ra.datos_snapshot = ''"
        )->fetchAll();

        $stmtSnapshot = $conexion->prepare(
            "UPDATE rutina_alertas
             SET datos_snapshot = :snapshot
             WHERE id = :id"
        );

        foreach ($faltantes as $fila) {
            $stmtSnapshot->execute(
                array(
                    ':snapshot' => rut_crear_snapshot_desde_fila(
                        $fila,
                        rut_obtener_recursos_rutina(
                            $conexion,
                            (int) $fila['rutina_id']
                        )
                    ),
                    ':id' => (int) $fila['alerta_id']
                )
            );
        }

        $stmtGuardarVersion = $conexion->prepare(
            "INSERT INTO configuracion_sistema
                (clave, valor, descripcion, tipo_valor, editable, fecha_actualizacion)
             VALUES
                (
                    'rutinas_esquema_version',
                    :version,
                    'Versión interna del esquema del módulo de Rutinas.',
                    'ENTERO',
                    0,
                    NOW()
                )
             ON DUPLICATE KEY UPDATE
                valor = VALUES(valor),
                descripcion = VALUES(descripcion),
                tipo_valor = VALUES(tipo_valor),
                editable = VALUES(editable),
                fecha_actualizacion = NOW()"
        );
        $stmtGuardarVersion->execute(array(':version' => $versionObjetivo));
    } finally {
        rut_liberar_lock($conexion, 'sm_rutinas_esquema_v7');
    }
}

function rut_asegurar_automatizacion(PDO $conexion)
{
    $estado = rut_estado_automatizacion($conexion);

    if (!empty($estado['activa'])) {
        return $estado;
    }

    $ultimoIntento = isset($_SESSION['rutinas_evento_intento_v7'])
        ? (int) $_SESSION['rutinas_evento_intento_v7']
        : 0;

    if ($ultimoIntento > 0 && (time() - $ultimoIntento) < 3600) {
        return $estado;
    }

    $_SESSION['rutinas_evento_intento_v7'] = time();

    try {
        $conexion->exec("SET GLOBAL event_scheduler = ON");
    } catch (Throwable $e) {
        error_log(
            '[RUTINAS][EVENT SCHEDULER] No fue posible habilitarlo: '
            . $e->getMessage()
        );
    }

    /*
     * Elimina automatizaciones antiguas del primer módulo para evitar que
     * vuelvan a crear avisos sin clave deduplicadora.
     */
    foreach (array(
        'ev_rutinas_crear_alerta_v2',
        'ev_rutinas_notificar_admin_v2',
        'ev_rutinas_sincronizar_v5',
        'ev_rutinas_sincronizar_v6'
    ) as $eventoAnterior) {
        try {
            $conexion->exec('DROP EVENT IF EXISTS ' . $eventoAnterior);
        } catch (Throwable $e) {
            error_log(
                '[RUTINAS][LIMPIEZA EVENTO] No fue posible eliminar '
                . $eventoAnterior . ': ' . $e->getMessage()
            );
        }
    }

    $sqlEvento = <<<'SQL'
CREATE EVENT IF NOT EXISTS ev_rutinas_sincronizar_v7
ON SCHEDULE EVERY 1 MINUTE
STARTS CURRENT_TIMESTAMP + INTERVAL 1 MINUTE
ON COMPLETION PRESERVE
ENABLE
DO
BEGIN
    UPDATE rutina_alertas ra
    INNER JOIN programaciones_mantenimiento pm
            ON pm.solicitud_id = ra.solicitud_id
           AND pm.es_actual = 1
           AND pm.estado IN ('PROGRAMADA','CUMPLIDA','VENCIDA','REPROGRAMADA')
    INNER JOIN solicitudes s
            ON s.id = ra.solicitud_id
           AND s.estado NOT IN ('RECHAZADO','CANCELADO')
    SET ra.estado = 'PROGRAMADA',
        ra.programacion_id = pm.id,
        ra.fecha_atencion = COALESCE(ra.fecha_atencion, NOW())
    WHERE ra.estado = 'PENDIENTE_PROGRAMAR'
      AND ra.solicitud_id IS NOT NULL;

    UPDATE rutina_alertas ra
    INNER JOIN solicitudes s ON s.id = ra.solicitud_id
    INNER JOIN rutinas_mantenimiento r ON r.id = ra.rutina_id
    SET ra.estado = CASE
            WHEN r.activo = 1 THEN 'PENDIENTE_PROGRAMAR'
            ELSE 'CANCELADA'
        END,
        ra.solicitud_id = NULL,
        ra.programacion_id = NULL,
        ra.atendida_por_admin_id = NULL,
        ra.fecha_atencion = NULL,
        ra.motivo_omision = CONCAT(
            'La solicitud ',
            s.folio,
            ' fue ',
            LOWER(s.estado),
            '. El mismo periodo volvió a quedar disponible.'
        )
    WHERE s.estado IN ('RECHAZADO','CANCELADO')
      AND ra.estado IN ('PENDIENTE_PROGRAMAR','PROGRAMADA');

    UPDATE rutinas_mantenimiento r
    INNER JOIN
    (
        SELECT
            ra.rutina_id,
            MAX(ra.fecha_notificacion) AS fecha_notificacion,
            MAX(cm.fecha_hora_cierre) AS fecha_cierre
        FROM rutina_alertas ra
        INNER JOIN solicitudes s ON s.id = ra.solicitud_id
        INNER JOIN cierres_mantenimiento cm ON cm.solicitud_id = s.id
        WHERE ra.estado = 'PROGRAMADA'
          AND s.estado = 'TERMINADO'
        GROUP BY ra.rutina_id
    ) terminadas ON terminadas.rutina_id = r.id
    SET r.ultima_notificacion = terminadas.fecha_notificacion,
        r.proxima_notificacion = DATE_ADD(
            DATE(terminadas.fecha_cierre),
            INTERVAL r.frecuencia_cada DAY
        ),
        r.fecha_actualizacion = NOW()
    WHERE r.ultima_notificacion IS NULL
       OR r.ultima_notificacion < terminadas.fecha_notificacion;

    INSERT IGNORE INTO rutina_alertas
    (
        rutina_id,
        fecha_notificacion,
        periodo_clave,
        datos_snapshot,
        estado,
        fecha_registro
    )
    SELECT
        r.id,
        r.proxima_notificacion,
        DATE_FORMAT(r.proxima_notificacion, '%Y%m%d'),
        JSON_OBJECT(
            'nombre', r.nombre,
            'descripcion_actividad', r.descripcion_actividad,
            'tipo_rutina', r.tipo_rutina,
            'departamento_id', r.departamento_id,
            'area_id', r.area_id,
            'proceso_id', r.proceso_id,
            'equipo_id', r.equipo_id,
            'prioridad', r.prioridad,
            'tipo_falla_id', r.tipo_falla_id,
            'causa_averia_id', r.causa_averia_id,
            'trabajo_peligroso', r.trabajo_peligroso,
            'detalle_trabajo_peligroso', r.detalle_trabajo_peligroso,
            'nivel_riesgo', r.nivel_riesgo,
            'requiere_paro_equipo', r.requiere_paro_equipo,
            'codigo_equipo', e.codigo_equipo,
            'nombre_equipo', e.nombre_equipo,
            'departamento', d.nombre,
            'area', a.nombre,
            'proceso', p.nombre,
            'tipo_falla', tf.nombre,
            'causa_averia', ca.nombre,
            'recursos', COALESCE(
                (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id', rm.id,
                            'tipo_recurso', rm.tipo_recurso,
                            'nombre', rm.nombre,
                            'codigo', rm.codigo,
                            'descripcion', rm.descripcion,
                            'activo', rm.activo
                        )
                    )
                    FROM rutina_recursos rr
                    INNER JOIN recursos_mantenimiento rm
                            ON rm.id = rr.recurso_id
                    WHERE rr.rutina_id = r.id
                ),
                JSON_ARRAY()
            )
        ),
        'PENDIENTE_PROGRAMAR',
        NOW()
    FROM rutinas_mantenimiento r
    INNER JOIN equipos e ON e.id = r.equipo_id
    INNER JOIN departamentos d ON d.id = r.departamento_id
    INNER JOIN areas a ON a.id = r.area_id
    INNER JOIN procesos p ON p.id = r.proceso_id
    LEFT JOIN tipos_falla tf ON tf.id = r.tipo_falla_id
    LEFT JOIN causas_averia ca ON ca.id = r.causa_averia_id
    WHERE r.activo = 1
      AND r.proxima_notificacion <= CURDATE()
      AND NOT EXISTS
      (
          SELECT 1
          FROM rutina_alertas ra
          LEFT JOIN solicitudes s ON s.id = ra.solicitud_id
          WHERE ra.rutina_id = r.id
            AND
            (
                ra.estado = 'PENDIENTE_PROGRAMAR'
                OR
                (
                    ra.estado = 'PROGRAMADA'
                    AND ra.solicitud_id IS NOT NULL
                    AND s.estado NOT IN ('TERMINADO','RECHAZADO','CANCELADO')
                )
            )
      );

    UPDATE rutina_alertas ra
    INNER JOIN rutinas_mantenimiento r ON r.id = ra.rutina_id
    SET ra.estado = 'PENDIENTE_PROGRAMAR',
        ra.motivo_omision = NULL,
        ra.atendida_por_admin_id = NULL,
        ra.fecha_atencion = NULL
    WHERE ra.estado = 'CANCELADA'
      AND ra.solicitud_id IS NULL
      AND r.activo = 1
      AND ra.fecha_notificacion = r.proxima_notificacion
      AND r.proxima_notificacion <= CURDATE();

    UPDATE notificaciones n
    INNER JOIN rutina_alertas ra ON ra.id = n.rutina_alerta_id
    INNER JOIN rutinas_mantenimiento r ON r.id = ra.rutina_id
    SET n.leida = 1,
        n.fecha_lectura = COALESCE(n.fecha_lectura, NOW())
    WHERE n.leida = 0
      AND
      (
          r.activo = 0
          OR ra.estado IN ('PROGRAMADA','OMITIDA','CANCELADA')
          OR (
              ra.solicitud_id IS NOT NULL
              AND n.titulo = 'Rutina pendiente de preparar'
          )
          OR (
              ra.solicitud_id IS NULL
              AND n.titulo = 'Rutina pendiente de asignar'
          )
          OR (
              n.clave_dedupe IS NOT NULL
              AND n.clave_dedupe NOT LIKE CONCAT(
                  'RUT:',
                  ra.id,
                  ':',
                  n.usuario_id,
                  ':',
                  CASE
                      WHEN ra.solicitud_id IS NULL THEN 'PREPARAR'
                      ELSE 'ASIGNAR'
                  END,
                  ':',
                  DATE_FORMAT(CURDATE(), '%Y%m%d')
              )
          )
      );

    INSERT IGNORE INTO notificaciones
    (
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
        fecha_creacion
    )
    SELECT
        'ADMIN',
        ad.id,
        ra.solicitud_id,
        ra.id,
        NULL,
        CONCAT(
            'RUT:',
            ra.id,
            ':',
            ad.id,
            ':',
            CASE
                WHEN ra.solicitud_id IS NULL THEN 'PREPARAR'
                ELSE 'ASIGNAR'
            END,
            ':',
            DATE_FORMAT(CURDATE(), '%Y%m%d')
        ),
        CASE
            WHEN ra.solicitud_id IS NULL
                THEN 'Rutina pendiente de preparar'
            ELSE 'Rutina pendiente de asignar'
        END,
        CASE
            WHEN ra.solicitud_id IS NULL THEN CONCAT(
                'La rutina "',
                r.nombre,
                '" del equipo ',
                e.codigo_equipo,
                ' - ',
                e.nombre_equipo,
                ' ya debe realizarse. Prepara la solicitud para elegir fecha y técnicos.'
            )
            ELSE CONCAT(
                'La solicitud rutinaria de "',
                r.nombre,
                '" sigue sin programarse. Elige la fecha y los técnicos responsables.'
            )
        END,
        CASE
            WHEN ra.fecha_notificacion < CURDATE() THEN 'DANGER'
            ELSE 'WARNING'
        END,
        0,
        NOW()
    FROM rutina_alertas ra
    INNER JOIN rutinas_mantenimiento r
            ON r.id = ra.rutina_id
           AND r.activo = 1
    INNER JOIN equipos e ON e.id = r.equipo_id
    INNER JOIN administradores ad ON ad.activo = 1
    WHERE ra.estado = 'PENDIENTE_PROGRAMAR'
      AND ra.fecha_notificacion <= CURDATE();
END
SQL;

    try {
        $conexion->exec($sqlEvento);
    } catch (Throwable $e) {
        error_log(
            '[RUTINAS][EVENTO V7] No fue posible crear el evento: '
            . $e->getMessage()
        );
    }

    return rut_estado_automatizacion($conexion);
}

/* =========================================================================
   HERRAMIENTAS, REFACCIONES Y SEGURIDAD DE LA PLANTILLA
   ========================================================================= */

function rut_validar_detalle_trabajo_peligroso($valor, $trabajoPeligroso)
{
    $detalle = rut_limpiar_texto($valor);

    if ((int) $trabajoPeligroso !== 1) {
        return null;
    }

    $longitud = function_exists('mb_strlen')
        ? mb_strlen($detalle, 'UTF-8')
        : strlen($detalle);

    if ($longitud < 3 || $longitud > 200) {
        rut_responder(
            false,
            'Describe brevemente el peligro con entre 3 y 200 caracteres.',
            array('campo' => 'detalle_trabajo_peligroso'),
            422
        );
    }

    return $detalle;
}

function rut_normalizar_ids_recursos($entrada, $campo)
{
    if ($entrada === null || $entrada === '') {
        return array();
    }

    if (!is_array($entrada)) {
        $entrada = array($entrada);
    }

    if (count($entrada) > 100) {
        rut_responder(
            false,
            'No puedes seleccionar más de 100 ' . $campo . ' por plantilla.',
            array('campo' => $campo),
            422
        );
    }

    $ids = array();

    foreach ($entrada as $valor) {
        $texto = trim((string) $valor);

        if ($texto === '' || !ctype_digit($texto) || (int) $texto < 1) {
            rut_responder(
                false,
                'La selección de ' . $campo . ' contiene un elemento inválido.',
                array('campo' => $campo),
                422
            );
        }

        $ids[(int) $texto] = (int) $texto;
    }

    return array_values($ids);
}

function rut_validar_recursos_catalogo(
    PDO $conexion,
    array $ids,
    $tipoEsperado,
    $rutinaId
) {
    if ($ids === array()) {
        return;
    }

    $marcadores = array();
    $parametros = array();

    foreach ($ids as $indice => $id) {
        $clave = ':recurso_' . $indice;
        $marcadores[] = $clave;
        $parametros[$clave] = (int) $id;
    }

    $sql = "SELECT
                r.id,
                r.tipo_recurso,
                r.nombre,
                r.activo,
                EXISTS(
                    SELECT 1
                    FROM rutina_recursos rr
                    WHERE rr.rutina_id = :rutina_id
                      AND rr.recurso_id = r.id
                ) AS ya_vinculado
            FROM recursos_mantenimiento r
            WHERE r.id IN (" . implode(',', $marcadores) . ")";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':rutina_id', max(0, (int) $rutinaId), PDO::PARAM_INT);

    foreach ($parametros as $clave => $valor) {
        $stmt->bindValue($clave, $valor, PDO::PARAM_INT);
    }

    $stmt->execute();
    $filas = $stmt->fetchAll();

    if (count($filas) !== count($ids)) {
        rut_responder(
            false,
            'Una herramienta o refacción seleccionada ya no existe.',
            array('campo' => strtolower((string) $tipoEsperado)),
            422
        );
    }

    foreach ($filas as $fila) {
        if ((string) $fila['tipo_recurso'] !== (string) $tipoEsperado) {
            rut_responder(
                false,
                'Una selección no corresponde al tipo de recurso esperado.',
                array('recurso_id' => (int) $fila['id']),
                422
            );
        }

        if ((int) $fila['activo'] !== 1 && (int) $fila['ya_vinculado'] !== 1) {
            rut_responder(
                false,
                'El recurso "' . $fila['nombre'] . '" está desactivado y no puede agregarse a una plantilla nueva.',
                array('recurso_id' => (int) $fila['id']),
                422
            );
        }
    }
}

function rut_reemplazar_recursos_rutina(
    PDO $conexion,
    $rutinaId,
    array $ids,
    $adminId
) {
    $stmtEliminar = $conexion->prepare(
        "DELETE FROM rutina_recursos WHERE rutina_id = :rutina_id"
    );
    $stmtEliminar->execute(array(':rutina_id' => (int) $rutinaId));

    if ($ids === array()) {
        return;
    }

    $stmtInsertar = $conexion->prepare(
        "INSERT INTO rutina_recursos
            (rutina_id, recurso_id, agregado_por_admin_id, fecha_registro)
         VALUES
            (:rutina_id, :recurso_id, :admin_id, NOW())"
    );

    foreach (array_values(array_unique(array_map('intval', $ids))) as $recursoId) {
        $stmtInsertar->execute(
            array(
                ':rutina_id' => (int) $rutinaId,
                ':recurso_id' => (int) $recursoId,
                ':admin_id' => (int) $adminId
            )
        );
    }
}

function rut_obtener_recursos_rutina(PDO $conexion, $rutinaId)
{
    $mapa = rut_obtener_recursos_rutinas($conexion, array((int) $rutinaId));

    return isset($mapa[(int) $rutinaId])
        ? $mapa[(int) $rutinaId]
        : array();
}

function rut_obtener_recursos_rutinas(PDO $conexion, array $rutinaIds)
{
    $rutinaIds = array_values(
        array_unique(
            array_filter(
                array_map('intval', $rutinaIds),
                static function ($id) {
                    return $id > 0;
                }
            )
        )
    );

    if ($rutinaIds === array()) {
        return array();
    }

    $marcadores = array();
    $parametros = array();

    foreach ($rutinaIds as $indice => $id) {
        $clave = ':rutina_' . $indice;
        $marcadores[] = $clave;
        $parametros[$clave] = $id;
    }

    $stmt = $conexion->prepare(
        "SELECT
            rr.rutina_id,
            r.id,
            r.tipo_recurso,
            r.nombre,
            r.codigo,
            r.descripcion,
            r.activo
         FROM rutina_recursos rr
         INNER JOIN recursos_mantenimiento r ON r.id = rr.recurso_id
         WHERE rr.rutina_id IN (" . implode(',', $marcadores) . ")
         ORDER BY rr.rutina_id, r.tipo_recurso, r.nombre, r.id"
    );

    foreach ($parametros as $clave => $valor) {
        $stmt->bindValue($clave, $valor, PDO::PARAM_INT);
    }

    $stmt->execute();
    $mapa = array();

    foreach ($stmt->fetchAll() as $fila) {
        $rutinaId = (int) $fila['rutina_id'];
        unset($fila['rutina_id']);
        $fila['id'] = (int) $fila['id'];
        $fila['activo'] = (int) $fila['activo'];
        $mapa[$rutinaId][] = $fila;
    }

    return $mapa;
}

function rut_obtener_recursos_solicitudes(PDO $conexion, array $solicitudIds)
{
    $solicitudIds = array_values(
        array_unique(
            array_filter(
                array_map('intval', $solicitudIds),
                static function ($id) {
                    return $id > 0;
                }
            )
        )
    );

    if ($solicitudIds === array()) {
        return array();
    }

    $marcadores = array();
    $parametros = array();

    foreach ($solicitudIds as $indice => $id) {
        $clave = ':solicitud_' . $indice;
        $marcadores[] = $clave;
        $parametros[$clave] = $id;
    }

    $stmt = $conexion->prepare(
        "SELECT
            srr.solicitud_id,
            r.id,
            srr.tipo_recurso,
            COALESCE(r.nombre, srr.nombre_no_catalogado) AS nombre,
            r.codigo,
            r.descripcion,
            COALESCE(r.activo, 1) AS activo,
            srr.origen
         FROM solicitud_recursos_recomendados srr
         LEFT JOIN recursos_mantenimiento r ON r.id = srr.recurso_id
         WHERE srr.solicitud_id IN (" . implode(',', $marcadores) . ")
         ORDER BY srr.solicitud_id, srr.tipo_recurso, nombre, srr.id"
    );

    foreach ($parametros as $clave => $valor) {
        $stmt->bindValue($clave, $valor, PDO::PARAM_INT);
    }

    $stmt->execute();
    $mapa = array();

    foreach ($stmt->fetchAll() as $fila) {
        $solicitudId = (int) $fila['solicitud_id'];
        unset($fila['solicitud_id']);
        $fila['id'] = isset($fila['id']) ? (int) $fila['id'] : 0;
        $fila['activo'] = (int) $fila['activo'];
        $mapa[$solicitudId][] = $fila;
    }

    return $mapa;
}

function rut_filtrar_recursos_tipo(array $recursos, $tipo)
{
    return array_values(
        array_filter(
            $recursos,
            static function ($recurso) use ($tipo) {
                return isset($recurso['tipo_recurso'])
                    && (string) $recurso['tipo_recurso'] === (string) $tipo;
            }
        )
    );
}

function rut_copiar_recursos_alerta_a_solicitud(
    PDO $conexion,
    array $alerta,
    $solicitudId,
    $adminId
) {
    $recursos = array();

    if (array_key_exists('recursos', $alerta) && is_array($alerta['recursos'])) {
        $recursos = $alerta['recursos'];
    } else {
        $recursos = rut_obtener_recursos_rutina(
            $conexion,
            (int) $alerta['rutina_id']
        );
    }

    $ids = array();

    foreach ($recursos as $recurso) {
        $id = isset($recurso['id']) ? (int) $recurso['id'] : 0;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    if ($ids === array()) {
        return;
    }

    $marcadores = array();
    $parametros = array();

    foreach (array_values($ids) as $indice => $id) {
        $clave = ':id_' . $indice;
        $marcadores[] = $clave;
        $parametros[$clave] = $id;
    }

    $stmtRecursos = $conexion->prepare(
        "SELECT id, tipo_recurso
         FROM recursos_mantenimiento
         WHERE id IN (" . implode(',', $marcadores) . ")"
    );

    foreach ($parametros as $clave => $valor) {
        $stmtRecursos->bindValue($clave, $valor, PDO::PARAM_INT);
    }

    $stmtRecursos->execute();
    $recursosValidos = $stmtRecursos->fetchAll();

    if (count($recursosValidos) !== count($ids)) {
        throw new RuntimeException(
            'La plantilla contiene un recurso que ya no existe en el catálogo.'
        );
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
            'RUTINA',
            :admin_id,
            NOW(),
            NOW()
        )"
    );

    foreach ($recursosValidos as $recurso) {
        $stmtInsertar->execute(
            array(
                ':solicitud_id' => (int) $solicitudId,
                ':tipo_recurso' => (string) $recurso['tipo_recurso'],
                ':recurso_id' => (int) $recurso['id'],
                ':admin_id' => (int) $adminId
            )
        );
    }
}

/* =========================================================================
   FUNCIONES AUXILIARES DE BASE DE DATOS
   ========================================================================= */

function rut_bloquear_rutina(PDO $conexion, $id)
{
    $stmt = $conexion->prepare(
        "SELECT *
         FROM rutinas_mantenimiento
         WHERE id = :id
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute(array(':id' => $id));

    return $stmt->fetch();
}

function rut_bloquear_alerta(PDO $conexion, $id)
{
    $stmt = $conexion->prepare(
        "SELECT
            ra.*,
            r.nombre,
            r.descripcion_actividad,
            r.tipo_rutina,
            r.departamento_id,
            r.area_id,
            r.proceso_id,
            r.equipo_id,
            r.prioridad,
            r.tipo_falla_id,
            r.causa_averia_id,
            r.trabajo_peligroso,
            r.detalle_trabajo_peligroso,
            r.nivel_riesgo,
            r.requiere_paro_equipo,
            r.frecuencia_cada,
            r.ultima_notificacion,
            r.proxima_notificacion,
            r.activo AS rutina_activa,
            e.codigo_equipo,
            e.nombre_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso,
            tf.nombre AS tipo_falla,
            ca.nombre AS causa_averia
         FROM rutina_alertas ra
         INNER JOIN rutinas_mantenimiento r
                 ON r.id = ra.rutina_id
         INNER JOIN equipos e ON e.id = r.equipo_id
         INNER JOIN departamentos d ON d.id = r.departamento_id
         INNER JOIN areas a ON a.id = r.area_id
         INNER JOIN procesos p ON p.id = r.proceso_id
         LEFT JOIN tipos_falla tf ON tf.id = r.tipo_falla_id
         LEFT JOIN causas_averia ca ON ca.id = r.causa_averia_id
         WHERE ra.id = :id
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute(array(':id' => $id));

    $alerta = $stmt->fetch();

    if (!$alerta) {
        return false;
    }

    return rut_aplicar_snapshot_a_alerta($alerta);
}

function rut_obtener_equipo(PDO $conexion, $id)
{
    $stmt = $conexion->prepare(
        "SELECT
            e.id,
            e.departamento_id,
            e.area_id,
            e.proceso_id,
            e.codigo_equipo,
            e.nombre_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso
         FROM equipos e
         INNER JOIN departamentos d
                 ON d.id = e.departamento_id
                AND d.activo = 1
         INNER JOIN areas a
                 ON a.id = e.area_id
                AND a.activo = 1
         INNER JOIN procesos p
                 ON p.id = e.proceso_id
                AND p.activo = 1
         WHERE e.id = :id
           AND e.activo = 1
         LIMIT 1"
    );
    $stmt->execute(array(':id' => $id));

    return $stmt->fetch();
}

function rut_registro_activo(PDO $conexion, $tabla, $id)
{
    $permitidas = array('tipos_falla', 'causas_averia');

    if (!in_array($tabla, $permitidas, true)) {
        return false;
    }

    $stmt = $conexion->prepare(
        "SELECT COUNT(*)
         FROM " . $tabla . "
         WHERE id = :id
           AND activo = 1"
    );
    $stmt->execute(array(':id' => $id));

    return (int) $stmt->fetchColumn() > 0;
}

function rut_nombre_catalogo(PDO $conexion, $tabla, $id)
{
    $permitidas = array('tipos_falla', 'causas_averia');

    if (!in_array($tabla, $permitidas, true) || (int) $id <= 0) {
        return null;
    }

    $stmt = $conexion->prepare(
        "SELECT nombre
         FROM " . $tabla . "
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->execute(array(':id' => $id));

    $nombre = $stmt->fetchColumn();

    return $nombre === false ? null : (string) $nombre;
}

function rut_generar_folio(PDO $conexion)
{
    $anio = (int) date('Y');
    $tipo = 'RUTINARIO';
    $prefijo = sprintf('RUT-%04d-', $anio);

    /*
     * Respeta folios importados y utiliza la tabla de secuencias con bloqueo
     * transaccional. Así dos administradores no obtienen el mismo folio.
     */
    $stmt = $conexion->prepare(
        "SELECT COALESCE(
            MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)),
            0
         )
         FROM solicitudes
         WHERE tipo_solicitud = 'RUTINARIO'
           AND folio LIKE :patron"
    );
    $stmt->execute(array(':patron' => $prefijo . '%'));

    $maximoExistente = (int) $stmt->fetchColumn();
    $minimoSiguiente = max(1, $maximoExistente + 1);

    $stmt = $conexion->prepare(
        "INSERT INTO secuencias_folios
            (tipo_solicitud, anio, ultimo_numero)
         VALUES
            (:tipo, :anio, :numero_inicial)
         ON DUPLICATE KEY UPDATE
            ultimo_numero = GREATEST(
                ultimo_numero + 1,
                :numero_minimo
            )"
    );
    $stmt->execute(
        array(
            ':tipo' => $tipo,
            ':anio' => $anio,
            ':numero_inicial' => $minimoSiguiente,
            ':numero_minimo' => $minimoSiguiente
        )
    );

    $stmt = $conexion->prepare(
        "SELECT ultimo_numero
         FROM secuencias_folios
         WHERE tipo_solicitud = :tipo
           AND anio = :anio
         FOR UPDATE"
    );
    $stmt->execute(
        array(
            ':tipo' => $tipo,
            ':anio' => $anio
        )
    );

    $numero = (int) $stmt->fetchColumn();

    if ($numero <= 0) {
        throw new RuntimeException('No fue posible generar el folio rutinario.');
    }

    return sprintf('RUT-%04d-%05d', $anio, $numero);
}

function rut_registrar_historial(
    PDO $conexion,
    $solicitudId,
    $evento,
    $estadoAnterior,
    $estadoNuevo,
    $adminId,
    $descripcion
) {
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
            NULL,
            NULL,
            :evento,
            :estado_anterior,
            :estado_nuevo,
            'ADMIN',
            :actor_id,
            :descripcion,
            NOW()
        )"
    );

    rut_bind_texto_nullable(
        $stmt,
        ':estado_anterior',
        $estadoAnterior
    );

    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':evento', $evento, PDO::PARAM_STR);
    $stmt->bindValue(':estado_nuevo', $estadoNuevo, PDO::PARAM_STR);
    $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->execute();
}

function rut_registrar_movimiento(
    PDO $conexion,
    $adminId,
    $accion,
    $descripcion,
    $tabla,
    $registroId
) {
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
            'Rutinas',
            :descripcion,
            :tabla,
            :registro_id,
            :ip,
            :user_agent,
            NOW()
        )"
    );

    $stmt->execute(
        array(
            ':usuario_id' => $adminId,
            ':accion' => $accion,
            ':descripcion' => $descripcion,
            ':tabla' => $tabla,
            ':registro_id' => $registroId,
            ':ip' => substr(
                (string) (
                    isset($_SERVER['REMOTE_ADDR'])
                        ? $_SERVER['REMOTE_ADDR']
                        : ''
                ),
                0,
                60
            ),
            ':user_agent' => substr(
                (string) (
                    isset($_SERVER['HTTP_USER_AGENT'])
                        ? $_SERVER['HTTP_USER_AGENT']
                        : ''
                ),
                0,
                255
            )
        )
    );
}

function rut_marcar_notificaciones_leidas(PDO $conexion, $alertaId)
{
    $stmt = $conexion->prepare(
        "UPDATE notificaciones
         SET leida = 1,
             fecha_lectura = COALESCE(fecha_lectura, NOW())
         WHERE rutina_alerta_id = :alerta_id
           AND leida = 0"
    );
    $stmt->execute(array(':alerta_id' => $alertaId));
}

function rut_vincular_datos_rutina(
    PDOStatement $stmt,
    $nombre,
    $descripcion,
    $tipoRutina,
    array $equipo,
    $prioridad,
    $tipoFallaId,
    $causaAveriaId,
    $trabajoPeligroso,
    $detalleTrabajoPeligroso,
    $nivelRiesgo,
    $requiereParo,
    $frecuenciaCada,
    $fechaInicio,
    $adminId
) {
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':tipo_rutina', $tipoRutina, PDO::PARAM_STR);
    $stmt->bindValue(
        ':departamento_id',
        (int) $equipo['departamento_id'],
        PDO::PARAM_INT
    );
    $stmt->bindValue(
        ':area_id',
        (int) $equipo['area_id'],
        PDO::PARAM_INT
    );
    $stmt->bindValue(
        ':proceso_id',
        (int) $equipo['proceso_id'],
        PDO::PARAM_INT
    );
    $stmt->bindValue(
        ':equipo_id',
        (int) $equipo['id'],
        PDO::PARAM_INT
    );
    $stmt->bindValue(':prioridad', $prioridad, PDO::PARAM_STR);

    rut_bind_entero_nullable(
        $stmt,
        ':tipo_falla_id',
        $tipoFallaId
    );

    rut_bind_entero_nullable(
        $stmt,
        ':causa_averia_id',
        $causaAveriaId
    );

    $stmt->bindValue(
        ':trabajo_peligroso',
        $trabajoPeligroso,
        PDO::PARAM_INT
    );
    rut_bind_texto_nullable(
        $stmt,
        ':detalle_trabajo_peligroso',
        $detalleTrabajoPeligroso
    );
    $stmt->bindValue(
        ':nivel_riesgo',
        $nivelRiesgo,
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':requiere_paro_equipo',
        $requiereParo,
        PDO::PARAM_INT
    );
    $stmt->bindValue(
        ':frecuencia_cada',
        $frecuenciaCada,
        PDO::PARAM_INT
    );
    $stmt->bindValue(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
    $stmt->bindValue(
        ':proxima_notificacion',
        $fechaInicio,
        PDO::PARAM_STR
    );
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
}


/* =========================================================================
   REGLAS DE NEGOCIO, SNAPSHOTS Y CONCURRENCIA
   ========================================================================= */

function rut_paquete_datos(PDO $conexion)
{
    return array(
        'csrf_token' => rut_token_csrf(),
        'resumen' => rut_obtener_resumen($conexion),
        'automatizacion' => rut_estado_automatizacion($conexion),
        'servidor' => array(
            'fecha' => date('Y-m-d'),
            'fecha_hora' => date('Y-m-d H:i:s')
        )
    );
}

function rut_tipos_rutina()
{
    return array(
        'Inspección',
        'Limpieza',
        'Lubricación',
        'Ajuste',
        'Calibración',
        'Prueba de seguridad',
        'Cambio preventivo',
        'Revisión general',
        'Otro'
    );
}

function rut_validar_admin_activo(PDO $conexion, $adminId)
{
    $stmt = $conexion->prepare(
        "SELECT COUNT(*)
         FROM administradores
         WHERE id = :id
           AND activo = 1"
    );
    $stmt->execute(array(':id' => $adminId));

    if ((int) $stmt->fetchColumn() !== 1) {
        throw new RuntimeException(
            'La cuenta del administrador ya no está activa.'
        );
    }
}

function rut_entero_opcional_estricto($valor, $campo)
{
    if ($valor === null || $valor === '') {
        return 0;
    }

    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        array('options' => array('min_range' => 1))
    );

    if ($entero === false) {
        rut_responder(
            false,
            'El campo ' . $campo . ' no es válido.',
            array('campo' => $campo),
            422
        );
    }

    return (int) $entero;
}

function rut_booleano_estricto($valor, $campo)
{
    $texto = strtolower(trim((string) $valor));

    if (in_array($texto, array('1', 'true', 'on', 'si', 'sí'), true)) {
        return 1;
    }

    if (in_array($texto, array('0', 'false', 'off', 'no', ''), true)) {
        return 0;
    }

    rut_responder(
        false,
        'El campo ' . $campo . ' no es válido.',
        array('campo' => $campo),
        422
    );
}

function rut_existe_rutina_duplicada(
    PDO $conexion,
    $nombre,
    $equipoId,
    $excluirId
) {
    $stmt = $conexion->prepare(
        "SELECT id
         FROM rutinas_mantenimiento
         WHERE nombre = :nombre
           AND equipo_id = :equipo_id
           AND id <> :excluir_id
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute(
        array(
            ':nombre' => $nombre,
            ':equipo_id' => $equipoId,
            ':excluir_id' => $excluirId
        )
    );

    return (bool) $stmt->fetchColumn();
}

function rut_obtener_ciclo_bloqueante(PDO $conexion, $rutinaId)
{
    $stmt = $conexion->prepare(
        "SELECT
            ra.id,
            ra.solicitud_id,
            ra.programacion_id,
            ra.estado,
            s.estado AS estado_solicitud
         FROM rutina_alertas ra
         LEFT JOIN solicitudes s ON s.id = ra.solicitud_id
         WHERE ra.rutina_id = :rutina_id
           AND
           (
               ra.estado = 'PENDIENTE_PROGRAMAR'
               OR
               (
                   ra.estado = 'PROGRAMADA'
                   AND ra.solicitud_id IS NOT NULL
                   AND s.estado NOT IN
                       ('TERMINADO','RECHAZADO','CANCELADO')
               )
           )
         ORDER BY ra.fecha_notificacion DESC, ra.id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute(array(':rutina_id' => $rutinaId));

    return $stmt->fetch();
}

function rut_existe_otro_ciclo_bloqueante(
    PDO $conexion,
    $rutinaId,
    $alertaExcluirId
) {
    $stmt = $conexion->prepare(
        "SELECT COUNT(*)
         FROM rutina_alertas ra
         LEFT JOIN solicitudes s ON s.id = ra.solicitud_id
         WHERE ra.rutina_id = :rutina_id
           AND ra.id <> :alerta_id
           AND
           (
               ra.estado = 'PENDIENTE_PROGRAMAR'
               OR
               (
                   ra.estado = 'PROGRAMADA'
                   AND ra.solicitud_id IS NOT NULL
                   AND s.estado NOT IN
                       ('TERMINADO','RECHAZADO','CANCELADO')
               )
           )"
    );
    $stmt->execute(
        array(
            ':rutina_id' => $rutinaId,
            ':alerta_id' => $alertaExcluirId
        )
    );

    return (int) $stmt->fetchColumn() > 0;
}

function rut_existe_periodo_posterior_atendido(
    PDO $conexion,
    $rutinaId,
    $alertaExcluirId,
    $fecha
) {
    $stmt = $conexion->prepare(
        "SELECT COUNT(*)
         FROM rutina_alertas ra
         LEFT JOIN solicitudes s ON s.id = ra.solicitud_id
         WHERE ra.rutina_id = :rutina_id
           AND ra.id <> :alerta_id
           AND ra.fecha_notificacion > :fecha
           AND
           (
               ra.estado = 'OMITIDA'
               OR (
                   ra.estado = 'PROGRAMADA'
                   AND s.estado NOT IN ('RECHAZADO','CANCELADO')
               )
               OR ra.solicitud_id IS NOT NULL
           )"
    );
    $stmt->execute(
        array(
            ':rutina_id' => $rutinaId,
            ':alerta_id' => $alertaExcluirId,
            ':fecha' => $fecha
        )
    );

    return (int) $stmt->fetchColumn() > 0;
}

function rut_ultima_fecha_atendida_anterior(
    PDO $conexion,
    $rutinaId,
    $alertaExcluirId,
    $fecha
) {
    $stmt = $conexion->prepare(
        "SELECT MAX(ra.fecha_notificacion)
         FROM rutina_alertas ra
         LEFT JOIN solicitudes s ON s.id = ra.solicitud_id
         WHERE ra.rutina_id = :rutina_id
           AND ra.id <> :alerta_id
           AND ra.fecha_notificacion < :fecha
           AND
           (
               ra.estado = 'OMITIDA'
               OR (
                   ra.estado = 'PROGRAMADA'
                   AND s.estado = 'TERMINADO'
               )
           )"
    );
    $stmt->execute(
        array(
            ':rutina_id' => $rutinaId,
            ':alerta_id' => $alertaExcluirId,
            ':fecha' => $fecha
        )
    );

    $valor = $stmt->fetchColumn();

    return $valor === false || $valor === null || $valor === ''
        ? null
        : (string) $valor;
}

function rut_obtener_rutina_completa(PDO $conexion, $id)
{
    $stmt = $conexion->prepare(
        "SELECT
            r.*,
            e.codigo_equipo,
            e.nombre_equipo,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso,
            tf.nombre AS tipo_falla,
            ca.nombre AS causa_averia
         FROM rutinas_mantenimiento r
         INNER JOIN equipos e ON e.id = r.equipo_id
         INNER JOIN departamentos d ON d.id = r.departamento_id
         INNER JOIN areas a ON a.id = r.area_id
         INNER JOIN procesos p ON p.id = r.proceso_id
         LEFT JOIN tipos_falla tf ON tf.id = r.tipo_falla_id
         LEFT JOIN causas_averia ca ON ca.id = r.causa_averia_id
         WHERE r.id = :id
         LIMIT 1"
    );
    $stmt->execute(array(':id' => $id));

    return $stmt->fetch();
}

function rut_crear_snapshot_desde_valores(
    $nombre,
    $descripcion,
    $tipoRutina,
    array $equipo,
    $prioridad,
    $tipoFallaId,
    $causaAveriaId,
    $trabajoPeligroso,
    $detalleTrabajoPeligroso,
    $nivelRiesgo,
    $requiereParo,
    array $recursos = array()
) {
    return rut_codificar_snapshot(
        array(
            'nombre' => $nombre,
            'descripcion_actividad' => $descripcion,
            'tipo_rutina' => $tipoRutina,
            'departamento_id' => (int) $equipo['departamento_id'],
            'area_id' => (int) $equipo['area_id'],
            'proceso_id' => (int) $equipo['proceso_id'],
            'equipo_id' => (int) $equipo['id'],
            'prioridad' => $prioridad,
            'tipo_falla_id' => $tipoFallaId > 0 ? $tipoFallaId : null,
            'causa_averia_id' => $causaAveriaId > 0 ? $causaAveriaId : null,
            'trabajo_peligroso' => (int) $trabajoPeligroso,
            'detalle_trabajo_peligroso' => $detalleTrabajoPeligroso,
            'nivel_riesgo' => $nivelRiesgo,
            'requiere_paro_equipo' => (int) $requiereParo,
            'codigo_equipo' => isset($equipo['codigo_equipo'])
                ? (string) $equipo['codigo_equipo']
                : '',
            'nombre_equipo' => isset($equipo['nombre_equipo'])
                ? (string) $equipo['nombre_equipo']
                : '',
            'departamento' => isset($equipo['departamento'])
                ? (string) $equipo['departamento']
                : '',
            'area' => isset($equipo['area'])
                ? (string) $equipo['area']
                : '',
            'proceso' => isset($equipo['proceso'])
                ? (string) $equipo['proceso']
                : '',
            'tipo_falla' => isset($equipo['tipo_falla'])
                ? $equipo['tipo_falla']
                : null,
            'causa_averia' => isset($equipo['causa_averia'])
                ? $equipo['causa_averia']
                : null,
            'recursos' => array_values($recursos)
        )
    );
}

function rut_crear_snapshot_desde_fila(
    array $fila,
    array $recursos = array()
)
{
    return rut_codificar_snapshot(
        array(
            'nombre' => isset($fila['nombre']) ? (string) $fila['nombre'] : '',
            'descripcion_actividad' => isset($fila['descripcion_actividad'])
                ? (string) $fila['descripcion_actividad']
                : '',
            'tipo_rutina' => isset($fila['tipo_rutina'])
                ? (string) $fila['tipo_rutina']
                : '',
            'departamento_id' => isset($fila['departamento_id'])
                ? (int) $fila['departamento_id']
                : 0,
            'area_id' => isset($fila['area_id'])
                ? (int) $fila['area_id']
                : 0,
            'proceso_id' => isset($fila['proceso_id'])
                ? (int) $fila['proceso_id']
                : 0,
            'equipo_id' => isset($fila['equipo_id'])
                ? (int) $fila['equipo_id']
                : 0,
            'prioridad' => isset($fila['prioridad'])
                ? (string) $fila['prioridad']
                : 'MEDIA',
            'tipo_falla_id' => !empty($fila['tipo_falla_id'])
                ? (int) $fila['tipo_falla_id']
                : null,
            'causa_averia_id' => !empty($fila['causa_averia_id'])
                ? (int) $fila['causa_averia_id']
                : null,
            'trabajo_peligroso' => isset($fila['trabajo_peligroso'])
                ? (int) $fila['trabajo_peligroso']
                : 0,
            'detalle_trabajo_peligroso' =>
                isset($fila['detalle_trabajo_peligroso'])
                    && trim((string) $fila['detalle_trabajo_peligroso']) !== ''
                    ? (string) $fila['detalle_trabajo_peligroso']
                    : null,
            'nivel_riesgo' => isset($fila['nivel_riesgo'])
                ? (string) $fila['nivel_riesgo']
                : 'BAJO',
            'requiere_paro_equipo' =>
                isset($fila['requiere_paro_equipo'])
                    ? (int) $fila['requiere_paro_equipo']
                    : 0,
            'codigo_equipo' => isset($fila['codigo_equipo'])
                ? (string) $fila['codigo_equipo']
                : '',
            'nombre_equipo' => isset($fila['nombre_equipo'])
                ? (string) $fila['nombre_equipo']
                : '',
            'departamento' => isset($fila['departamento'])
                ? (string) $fila['departamento']
                : '',
            'area' => isset($fila['area']) ? (string) $fila['area'] : '',
            'proceso' => isset($fila['proceso'])
                ? (string) $fila['proceso']
                : '',
            'tipo_falla' => isset($fila['tipo_falla'])
                ? $fila['tipo_falla']
                : null,
            'causa_averia' => isset($fila['causa_averia'])
                ? $fila['causa_averia']
                : null,
            'recursos' => array_values($recursos)
        )
    );
}

function rut_codificar_snapshot(array $datos)
{
    $json = json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if (!is_string($json) || $json === '') {
        throw new RuntimeException(
            'No fue posible guardar la información histórica de la rutina.'
        );
    }

    return $json;
}

function rut_decodificar_snapshot($json)
{
    if (!is_string($json) || trim($json) === '') {
        return array();
    }

    $datos = json_decode($json, true);

    return is_array($datos) ? $datos : array();
}

function rut_aplicar_snapshot_a_alerta(array $alerta)
{
    $snapshot = rut_decodificar_snapshot(
        isset($alerta['datos_snapshot'])
            ? $alerta['datos_snapshot']
            : ''
    );

    $campos = array(
        'nombre',
        'descripcion_actividad',
        'tipo_rutina',
        'departamento_id',
        'area_id',
        'proceso_id',
        'equipo_id',
        'prioridad',
        'tipo_falla_id',
        'causa_averia_id',
        'trabajo_peligroso',
        'detalle_trabajo_peligroso',
        'nivel_riesgo',
        'requiere_paro_equipo',
        'codigo_equipo',
        'nombre_equipo',
        'departamento',
        'area',
        'proceso',
        'tipo_falla',
        'causa_averia',
        'recursos'
    );

    foreach ($campos as $campo) {
        if (array_key_exists($campo, $snapshot)) {
            $alerta[$campo] = $snapshot[$campo];
        }
    }

    return $alerta;
}

function rut_normalizar_alerta_listado(array $fila)
{
    $alerta = $fila;

    $alerta['nombre'] = (string) $fila['actual_nombre'];
    $alerta['descripcion_actividad'] =
        (string) $fila['actual_descripcion_actividad'];
    $alerta['tipo_rutina'] = (string) $fila['actual_tipo_rutina'];
    $alerta['prioridad'] = (string) $fila['actual_prioridad'];
    $alerta['trabajo_peligroso'] =
        (int) $fila['actual_trabajo_peligroso'];
    $alerta['detalle_trabajo_peligroso'] =
        $fila['actual_detalle_trabajo_peligroso'];
    $alerta['nivel_riesgo'] = (string) $fila['actual_nivel_riesgo'];
    $alerta['requiere_paro_equipo'] =
        (int) $fila['actual_requiere_paro_equipo'];
    $alerta['departamento_id'] = (int) $fila['actual_departamento_id'];
    $alerta['area_id'] = (int) $fila['actual_area_id'];
    $alerta['proceso_id'] = (int) $fila['actual_proceso_id'];
    $alerta['equipo_id'] = (int) $fila['actual_equipo_id'];
    $alerta['tipo_falla_id'] = !empty($fila['actual_tipo_falla_id'])
        ? (int) $fila['actual_tipo_falla_id']
        : null;
    $alerta['causa_averia_id'] = !empty($fila['actual_causa_averia_id'])
        ? (int) $fila['actual_causa_averia_id']
        : null;
    $alerta['codigo_equipo'] = (string) $fila['actual_codigo_equipo'];
    $alerta['nombre_equipo'] = (string) $fila['actual_nombre_equipo'];
    $alerta['departamento'] = (string) $fila['actual_departamento'];
    $alerta['area'] = (string) $fila['actual_area'];
    $alerta['proceso'] = (string) $fila['actual_proceso'];
    $alerta['tipo_falla'] = $fila['actual_tipo_falla'];
    $alerta['causa_averia'] = $fila['actual_causa_averia'];

    $alerta = rut_aplicar_snapshot_a_alerta($alerta);

    if ((int) $fila['solicitud_id'] > 0) {
        $alerta['prioridad'] = (string) $fila['solicitud_prioridad'];
        $alerta['trabajo_peligroso'] =
            (int) $fila['solicitud_trabajo_peligroso'];
        $alerta['detalle_trabajo_peligroso'] =
            $fila['solicitud_detalle_trabajo_peligroso'];
        $alerta['nivel_riesgo'] =
            (string) $fila['solicitud_nivel_riesgo'];
        $alerta['requiere_paro_equipo'] =
            (int) $fila['solicitud_requiere_paro'];
        $alerta['departamento_id'] =
            (int) $fila['solicitud_departamento_id'];
        $alerta['area_id'] = (int) $fila['solicitud_area_id'];
        $alerta['proceso_id'] = (int) $fila['solicitud_proceso_id'];
        $alerta['equipo_id'] = (int) $fila['solicitud_equipo_id'];
        $alerta['tipo_falla_id'] =
            !empty($fila['solicitud_tipo_falla_id'])
                ? (int) $fila['solicitud_tipo_falla_id']
                : null;
        $alerta['causa_averia_id'] =
            !empty($fila['solicitud_causa_averia_id'])
                ? (int) $fila['solicitud_causa_averia_id']
                : null;
        $alerta['codigo_equipo'] =
            (string) $fila['solicitud_codigo_equipo'];
        $alerta['nombre_equipo'] =
            (string) $fila['solicitud_nombre_equipo'];
        $alerta['departamento'] =
            (string) $fila['solicitud_departamento'];
        $alerta['area'] = (string) $fila['solicitud_area'];
        $alerta['proceso'] = (string) $fila['solicitud_proceso'];
        $alerta['tipo_falla'] = $fila['solicitud_tipo_falla'];
        $alerta['causa_averia'] = $fila['solicitud_causa_averia'];

        if (trim((string) $fila['descripcion_solicitud']) !== '') {
            $alerta['descripcion_actividad'] =
                (string) $fila['descripcion_solicitud'];
        }
    }

    return $alerta;
}

function rut_reconciliar_alertas_edicion(
    PDO $conexion,
    $rutinaId,
    $fechaAnterior,
    $fechaNueva,
    $snapshot,
    $adminId
) {
    $stmt = $conexion->prepare(
        "SELECT id, fecha_notificacion
         FROM rutina_alertas
         WHERE rutina_id = :rutina_id
           AND estado = 'PENDIENTE_PROGRAMAR'
           AND solicitud_id IS NULL
         FOR UPDATE"
    );
    $stmt->execute(array(':rutina_id' => $rutinaId));
    $pendientes = $stmt->fetchAll();

    $conservada = false;

    foreach ($pendientes as $pendiente) {
        $alertaId = (int) $pendiente['id'];

        if ((string) $pendiente['fecha_notificacion'] === $fechaNueva) {
            $stmtActualizar = $conexion->prepare(
                "UPDATE rutina_alertas
                 SET datos_snapshot = :snapshot,
                     motivo_omision = NULL,
                     atendida_por_admin_id = NULL,
                     fecha_atencion = NULL
                 WHERE id = :id"
            );
            $stmtActualizar->execute(
                array(
                    ':snapshot' => $snapshot,
                    ':id' => $alertaId
                )
            );
            $conservada = true;
            continue;
        }

        $stmtCancelar = $conexion->prepare(
            "UPDATE rutina_alertas
             SET estado = 'CANCELADA',
                 motivo_omision =
                    'La fecha del próximo aviso cambió al editar la plantilla.',
                 atendida_por_admin_id = :admin_id,
                 fecha_atencion = NOW()
             WHERE id = :id"
        );
        $stmtCancelar->execute(
            array(
                ':admin_id' => $adminId,
                ':id' => $alertaId
            )
        );
        rut_marcar_notificaciones_leidas($conexion, $alertaId);
    }

    if (!$conservada && $fechaNueva <= date('Y-m-d')) {
        rut_crear_o_reactivar_alerta(
            $conexion,
            $rutinaId,
            $fechaNueva,
            $snapshot,
            true
        );
    }
}

function rut_crear_o_reactivar_alerta(
    PDO $conexion,
    $rutinaId,
    $fecha,
    $snapshot,
    $reactivarOmitida
) {
    $periodo = str_replace('-', '', $fecha);

    $stmt = $conexion->prepare(
        "SELECT id, estado, solicitud_id, programacion_id
         FROM rutina_alertas
         WHERE rutina_id = :rutina_id
           AND periodo_clave = :periodo
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute(
        array(
            ':rutina_id' => $rutinaId,
            ':periodo' => $periodo
        )
    );
    $existente = $stmt->fetch();

    if (!$existente) {
        $stmtInsertar = $conexion->prepare(
            "INSERT INTO rutina_alertas
            (
                rutina_id,
                fecha_notificacion,
                periodo_clave,
                datos_snapshot,
                estado,
                solicitud_id,
                programacion_id,
                atendida_por_admin_id,
                motivo_omision,
                fecha_atencion,
                fecha_registro
            )
            VALUES
            (
                :rutina_id,
                :fecha,
                :periodo,
                :snapshot,
                'PENDIENTE_PROGRAMAR',
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NOW()
            )"
        );
        $stmtInsertar->execute(
            array(
                ':rutina_id' => $rutinaId,
                ':fecha' => $fecha,
                ':periodo' => $periodo,
                ':snapshot' => $snapshot
            )
        );

        return 'CREADA';
    }

    $puedeReactivar =
        (int) $existente['solicitud_id'] <= 0
        && (int) $existente['programacion_id'] <= 0
        && (
            (string) $existente['estado'] === 'CANCELADA'
            || (
                $reactivarOmitida
                && (string) $existente['estado'] === 'OMITIDA'
            )
        );

    if ($puedeReactivar) {
        $stmtReactivar = $conexion->prepare(
            "UPDATE rutina_alertas
             SET fecha_notificacion = :fecha,
                 datos_snapshot = :snapshot,
                 estado = 'PENDIENTE_PROGRAMAR',
                 solicitud_id = NULL,
                 programacion_id = NULL,
                 atendida_por_admin_id = NULL,
                 motivo_omision = NULL,
                 fecha_atencion = NULL
             WHERE id = :id"
        );
        $stmtReactivar->execute(
            array(
                ':fecha' => $fecha,
                ':snapshot' => $snapshot,
                ':id' => (int) $existente['id']
            )
        );

        rut_marcar_notificaciones_leidas(
            $conexion,
            (int) $existente['id']
        );

        return 'REACTIVADA';
    }

    if (
        (string) $existente['estado'] === 'PENDIENTE_PROGRAMAR'
        && (int) $existente['solicitud_id'] <= 0
    ) {
        $stmtActualizar = $conexion->prepare(
            "UPDATE rutina_alertas
             SET datos_snapshot = :snapshot
             WHERE id = :id"
        );
        $stmtActualizar->execute(
            array(
                ':snapshot' => $snapshot,
                ':id' => (int) $existente['id']
            )
        );
    }

    return 'EXISTENTE';
}

function rut_estado_automatizacion(PDO $conexion)
{
    $scheduler = 'DESCONOCIDO';
    $eventoExiste = false;

    try {
        $scheduler = strtoupper(
            (string) $conexion->query(
                "SELECT @@event_scheduler"
            )->fetchColumn()
        );

        $stmt = $conexion->prepare(
            "SELECT COUNT(*)
             FROM information_schema.EVENTS
             WHERE EVENT_SCHEMA = DATABASE()
               AND EVENT_NAME = 'ev_rutinas_sincronizar_v7'
               AND STATUS = 'ENABLED'"
        );
        $stmt->execute();
        $eventoExiste = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log(
            '[RUTINAS][ESTADO AUTOMATIZACION] ' . $e->getMessage()
        );
    }

    $activa = $scheduler === 'ON' && $eventoExiste;

    return array(
        'activa' => $activa,
        'event_scheduler' => $scheduler,
        'evento_instalado' => $eventoExiste,
        'intervalo_minutos' => 1,
        'mensaje' => $activa
            ? 'Automatización del servidor activa cada minuto.'
            : 'La página seguirá actualizando los avisos mientras esté abierta, pero el evento automático de MySQL no está activo.'
    );
}

function rut_adquirir_lock(PDO $conexion, $nombre, $segundos)
{
    try {
        $stmt = $conexion->prepare(
            "SELECT GET_LOCK(:nombre, :segundos)"
        );
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':segundos', (int) $segundos, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() === 1;
    } catch (Throwable $e) {
        error_log('[RUTINAS][GET_LOCK] ' . $e->getMessage());

        /*
         * MySQL/MariaDB soportan GET_LOCK. Si el proveedor lo bloquea, se
         * continúa protegido por índices únicos y bloqueos FOR UPDATE.
         */
        return true;
    }
}

function rut_liberar_lock(PDO $conexion, $nombre)
{
    try {
        $stmt = $conexion->prepare("SELECT RELEASE_LOCK(:nombre)");
        $stmt->execute(array(':nombre' => $nombre));
    } catch (Throwable $e) {
        error_log('[RUTINAS][RELEASE_LOCK] ' . $e->getMessage());
    }
}

function rut_indice_existe(PDO $conexion, $tabla, $indice)
{
    $stmt = $conexion->prepare(
        "SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :tabla
           AND INDEX_NAME = :indice"
    );
    $stmt->execute(
        array(
            ':tabla' => $tabla,
            ':indice' => $indice
        )
    );

    return (int) $stmt->fetchColumn() > 0;
}

/* =========================================================================
   SEGURIDAD, VALIDACIÓN Y FORMATO
   ========================================================================= */

function rut_token_csrf()
{
    if (
        empty($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
        || strlen($_SESSION['csrf_token']) < 64
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function rut_validar_csrf()
{
    $esperado = rut_token_csrf();

    $recibido = '';

    if (isset($_POST['csrf_token'])) {
        $recibido = (string) $_POST['csrf_token'];
    } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $recibido = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    if (
        $recibido === ''
        || !hash_equals($esperado, $recibido)
    ) {
        rut_responder(
            false,
            'La sesión de seguridad venció. Actualiza la página.',
            array('csrf_invalido' => true),
            419
        );
    }
}

function rut_requerir_metodo($metodo)
{
    $actual = strtoupper(
        isset($_SERVER['REQUEST_METHOD'])
            ? (string) $_SERVER['REQUEST_METHOD']
            : 'GET'
    );

    if ($actual !== strtoupper($metodo)) {
        rut_responder(
            false,
            'Método de solicitud no permitido.',
            array(),
            405
        );
    }
}

function rut_admin_id()
{
    $id = isset($_SESSION['usuario_id'])
        ? (int) $_SESSION['usuario_id']
        : 0;

    if ($id <= 0) {
        rut_responder(
            false,
            'No se encontró al administrador en sesión.',
            array(),
            401
        );
    }

    return $id;
}

function rut_limpiar_texto($valor)
{
    if (!is_scalar($valor)) {
        return '';
    }

    $texto = trim((string) $valor);

    $limpio = preg_replace(
        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
        '',
        $texto
    );

    return $limpio === null ? '' : $limpio;
}

function rut_validar_texto($valor, $minimo, $maximo, $campo)
{
    $texto = rut_limpiar_texto($valor);
    $longitud = function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);

    if ($longitud < $minimo || $longitud > $maximo) {
        rut_responder(
            false,
            'El campo ' . $campo
                . ' debe contener entre '
                . $minimo
                . ' y '
                . $maximo
                . ' caracteres.',
            array('campo' => $campo),
            422
        );
    }

    return $texto;
}

function rut_entero_positivo($valor, $campo)
{
    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        array(
            'options' => array(
                'min_range' => 1
            )
        )
    );

    if ($entero === false) {
        rut_responder(
            false,
            'El campo ' . $campo . ' no es válido.',
            array('campo' => $campo),
            422
        );
    }

    return (int) $entero;
}

function rut_entero_opcional($valor)
{
    if ($valor === null || $valor === '') {
        return 0;
    }

    $entero = filter_var($valor, FILTER_VALIDATE_INT);

    if ($entero === false || (int) $entero < 1) {
        return 0;
    }

    return (int) $entero;
}

function rut_booleano($valor)
{
    return in_array(
        strtolower((string) $valor),
        array('1', 'true', 'on', 'si', 'sí'),
        true
    ) ? 1 : 0;
}

function rut_fecha_valida($fecha)
{
    $objeto = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        (string) $fecha
    );

    return $objeto instanceof DateTimeImmutable
        && $objeto->format('Y-m-d') === $fecha;
}

function rut_situacion_alerta(array $alerta)
{
    $estado = (string) $alerta['estado'];

    if ($estado === 'PROGRAMADA') {
        return 'PROGRAMADA';
    }

    if ($estado === 'OMITIDA') {
        return 'OMITIDA';
    }

    if ($estado === 'CANCELADA') {
        return 'CANCELADA';
    }

    if ((int) $alerta['solicitud_id'] > 0) {
        return 'LISTA_PROGRAMAR';
    }

    if ((string) $alerta['fecha_notificacion'] < date('Y-m-d')) {
        return 'VENCIDA';
    }

    if ((string) $alerta['fecha_notificacion'] === date('Y-m-d')) {
        return 'HOY';
    }

    return 'PROXIMA';
}

function rut_fecha_relativa($fecha)
{
    if (!rut_fecha_valida($fecha)) {
        return '';
    }

    $hoy = new DateTimeImmutable('today');
    $objetivo = new DateTimeImmutable($fecha);

    $dias = (int) $hoy->diff($objetivo)->format('%r%a');

    if ($dias === 0) {
        return 'Hoy';
    }

    if ($dias === 1) {
        return 'Mañana';
    }

    if ($dias === -1) {
        return 'Ayer';
    }

    if ($dias > 1) {
        return 'En ' . $dias . ' días';
    }

    return 'Hace ' . abs($dias) . ' días';
}

function rut_fecha_es($fecha)
{
    if (!rut_fecha_valida($fecha)) {
        return $fecha;
    }

    return (new DateTimeImmutable($fecha))->format('d/m/Y');
}

function rut_recortar($texto, $maximo)
{
    $texto = (string) $texto;
    $maximo = max(1, (int) $maximo);

    $longitud = function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);

    if ($longitud <= $maximo) {
        return $texto;
    }

    $corte = function_exists('mb_substr')
        ? mb_substr($texto, 0, $maximo - 1, 'UTF-8')
        : substr($texto, 0, $maximo - 1);

    return rtrim($corte) . '…';
}

function rut_bind_entero_nullable(
    PDOStatement $stmt,
    $parametro,
    $valor
) {
    if ((int) $valor > 0) {
        $stmt->bindValue(
            $parametro,
            (int) $valor,
            PDO::PARAM_INT
        );
    } else {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
    }
}

function rut_bind_texto_nullable(
    PDOStatement $stmt,
    $parametro,
    $valor
) {
    if ($valor === null || trim((string) $valor) === '') {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(
            $parametro,
            (string) $valor,
            PDO::PARAM_STR
        );
    }
}

function rut_tabla_existe(PDO $conexion, $tabla)
{
    $stmt = $conexion->prepare(
        "SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :tabla"
    );
    $stmt->execute(array(':tabla' => $tabla));

    return (int) $stmt->fetchColumn() > 0;
}

function rut_columna_existe(PDO $conexion, $tabla, $columna)
{
    $stmt = $conexion->prepare(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :tabla
           AND COLUMN_NAME = :columna"
    );
    $stmt->execute(
        array(
            ':tabla' => $tabla,
            ':columna' => $columna
        )
    );

    return (int) $stmt->fetchColumn() > 0;
}

function rut_responder(
    $success,
    $mensaje,
    array $extra = array(),
    $codigo = 200
) {
    if (!headers_sent()) {
        http_response_code((int) $codigo);
        header('Content-Type: application/json; charset=utf-8');
        header(
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
        ); 
    }

    echo json_encode(
        array_merge(
            array(
                'success' => (bool) $success,
                'mensaje' => (string) $mensaje
            ),
            $extra
        ),
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit; 
}