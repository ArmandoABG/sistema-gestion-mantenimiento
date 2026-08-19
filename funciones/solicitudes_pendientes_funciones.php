<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Solicitudes pendientes - Sistema de Mantenimiento 1.1
| En esta bandeja se aprueban solicitudes normales, se rechazan registros
| improcedentes y las urgencias solo se marcan como revisadas.
| La cancelación administrativa de urgencias activas se realiza desde el expediente en Todas las solicitudes.
|--------------------------------------------------------------------------
| Compatible con PHP 7.4+.
| Estados de solicitud utilizados:
| PENDIENTE, APROBADO, AGENDADO, EN_PROCESO, PAUSADO,
| TERMINADO, CANCELADO, RECHAZADO y ATRASADO.
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
$accion = $metodo === 'GET'
    ? sm_limpiar_texto($_GET['accion'] ?? '')
    : sm_limpiar_texto($_POST['accion'] ?? '');

try {
    switch ($accion) {
        case 'listar':
            sm_requerir_metodo('GET');
            spen_listar($conexion);
            break;

        case 'catalogos':
            sm_requerir_metodo('GET');
            spen_catalogos($conexion);
            break;

        case 'obtener':
            sm_requerir_metodo('GET');
            spen_obtener($conexion);
            break;

        case 'guardar_edicion':
            sm_requerir_metodo('POST');
            sm_validar_csrf();
            spen_guardar_edicion($conexion);
            break;

        case 'procesar':
            sm_requerir_metodo('POST');
            sm_validar_csrf();
            spen_procesar($conexion);
            break;

        default:
            sm_responder_json(
                false,
                'La acción solicitada no es válida.',
                [],
                400
            );
    }
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[SOLICITUDES PENDIENTES][PDO] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la solicitud.',
        [],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[SOLICITUDES PENDIENTES] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la solicitud.',
        [],
        500
    );
}

/* =========================================================================
   ACCIONES
   ========================================================================= */

function spen_listar(PDO $conexion): void
{
    $sql = "
        SELECT
            s.id,
            s.folio,
            s.tipo_solicitud,
            s.estado,
            s.prioridad,
            s.fecha_solicitud,
            s.hora_solicitud,
            s.fecha_registro,
            s.revisado_por_admin_id,
            s.descripcion_solicitud,
            s.creado_por_tipo,

            DATE_FORMAT(s.fecha_solicitud, '%d/%m/%Y') AS fecha_solicitud_formato,
            TIME_FORMAT(s.hora_solicitud, '%H:%i') AS hora_solicitud_formato,
            DATE_FORMAT(s.fecha_registro, '%d/%m/%Y %H:%i') AS fecha_registro_formato,

            GREATEST(
                TIMESTAMPDIFF(
                    MINUTE,
                    TIMESTAMP(s.fecha_solicitud, s.hora_solicitud),
                    NOW()
                ),
                0
            ) AS minutos_espera,

            COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ', sol.nombre, sol.apellido_paterno, sol.apellido_materno)), ''),
                NULLIF(TRIM(CONCAT_WS(' ', adm_sol.nombre, adm_sol.apellido_paterno, adm_sol.apellido_materno)), ''),
                'Sin solicitante'
            ) AS solicitante,

            COALESCE(d.nombre, 'Sin departamento') AS departamento,
            COALESCE(a.nombre, 'Sin área') AS area,
            COALESCE(p.nombre, 'Sin proceso') AS proceso,
            COALESCE(e.codigo_equipo, 'Sin código') AS codigo_equipo,
            COALESCE(e.nombre_equipo, 'Sin equipo') AS nombre_equipo,

            CASE
                WHEN s.tipo_solicitud = 'CORRECTIVO_URGENTE'
                     AND s.revisado_por_admin_id IS NULL
                    THEN 'URGENTE_SIN_REVISAR'
                ELSE 'PENDIENTE'
            END AS tipo_revision

        FROM solicitudes s
        LEFT JOIN solicitantes sol
            ON sol.id = s.solicitante_id
        LEFT JOIN administradores adm_sol
            ON adm_sol.id = s.administrador_solicitante_id
        LEFT JOIN departamentos d
            ON d.id = s.departamento_id
        LEFT JOIN areas a
            ON a.id = s.area_id
        LEFT JOIN procesos p
            ON p.id = s.proceso_id
        LEFT JOIN equipos e
            ON e.id = s.equipo_id

        WHERE s.activo = 1
          AND (
                s.estado = 'PENDIENTE'
                OR (
                    s.tipo_solicitud = 'CORRECTIVO_URGENTE'
                    AND s.estado IN (
                        'AGENDADO',
                        'EN_PROCESO',
                        'PAUSADO',
                        'ATRASADO'
                    )
                    AND s.revisado_por_admin_id IS NULL
                )
          )

        ORDER BY
            CASE
                WHEN s.tipo_solicitud = 'CORRECTIVO_URGENTE' THEN 1
                WHEN s.prioridad = 'URGENTE' THEN 2
                WHEN s.prioridad = 'ALTA' THEN 3
                WHEN s.prioridad = 'MEDIA' THEN 4
                ELSE 5
            END,
            s.fecha_solicitud,
            s.hora_solicitud,
            s.id
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $resumen = [
        'total' => 0,
        'urgentes' => 0,
        'programables' => 0,
        'mejoras' => 0,
        'prioridad_alta' => 0,
        'espera_mayor_dos_horas' => 0,
    ];

    foreach ($solicitudes as $solicitud) {
        $resumen['total']++;

        if ((string) $solicitud['tipo_solicitud'] === 'CORRECTIVO_URGENTE') {
            $resumen['urgentes']++;
        } elseif ((string) $solicitud['tipo_solicitud'] === 'CORRECTIVO_PROGRAMABLE') {
            $resumen['programables']++;
        } elseif ((string) $solicitud['tipo_solicitud'] === 'MODIFICACION_MEJORA') {
            $resumen['mejoras']++;
        }

        if (in_array((string) $solicitud['prioridad'], ['ALTA', 'URGENTE'], true)) {
            $resumen['prioridad_alta']++;
        }

        if ((int) $solicitud['minutos_espera'] >= 120) {
            $resumen['espera_mayor_dos_horas']++;
        }
    }

    sm_responder_json(
        true,
        'Solicitudes cargadas correctamente.',
        [
            'solicitudes' => $solicitudes,
            'resumen' => $resumen,
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function spen_catalogos(PDO $conexion): void
{
    $catalogos = [
        'departamentos' => spen_consultar_catalogo(
            $conexion,
            "SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre"
        ),
        'areas' => spen_consultar_catalogo(
            $conexion,
            "SELECT id, departamento_id, nombre FROM areas WHERE activo = 1 ORDER BY nombre"
        ),
        'procesos' => spen_consultar_catalogo(
            $conexion,
            "SELECT id, area_id, nombre FROM procesos WHERE activo = 1 ORDER BY nombre"
        ),
        'equipos' => spen_consultar_catalogo(
            $conexion,
            "SELECT
                id,
                departamento_id,
                area_id,
                proceso_id,
                codigo_equipo,
                nombre_equipo
             FROM equipos
             WHERE activo = 1
             ORDER BY codigo_equipo, nombre_equipo"
        ),
        'tipos_falla' => spen_consultar_catalogo(
            $conexion,
            "SELECT id, nombre FROM tipos_falla WHERE activo = 1 ORDER BY nombre"
        ),
        'causas_averia' => spen_consultar_catalogo(
            $conexion,
            "SELECT id, nombre FROM causas_averia WHERE activo = 1 ORDER BY nombre"
        ),
        'causas_mejora' => spen_consultar_catalogo(
            $conexion,
            "SELECT id, nombre FROM causas_mejora WHERE activo = 1 ORDER BY nombre"
        ),
    ];

    sm_responder_json(
        true,
        'Catálogos cargados correctamente.',
        ['catalogos' => $catalogos]
    );
}

function spen_obtener(PDO $conexion): void
{
    $id = spen_entero_positivo(
        $_GET['id'] ?? null,
        'Selecciona una solicitud válida.'
    );

    $solicitud = spen_consultar_solicitud_detalle($conexion, $id);

    if (!$solicitud) {
        sm_responder_json(
            false,
            'La solicitud no existe, está inactiva o ya no requiere revisión.',
            [],
            404
        );
    }

    sm_responder_json(
        true,
        'Solicitud cargada correctamente.',
        ['solicitud' => $solicitud]
    );
}

function spen_guardar_edicion(PDO $conexion): void
{
    $adminId = spen_admin_sesion();
    $id = spen_entero_positivo(
        $_POST['id'] ?? null,
        'Selecciona una solicitud válida.'
    );

    $datos = spen_recibir_formulario();
    spen_validar_formulario($conexion, $datos);

    $motivoEdicion = spen_normalizar_texto($_POST['motivo_edicion'] ?? '');
    spen_validar_texto(
        $motivoEdicion,
        'motivo_edicion',
        'Escribe por qué se corrigió la solicitud.',
        10,
        500,
        true
    );

    $conexion->beginTransaction();

    $anterior = spen_bloquear_solicitud($conexion, $id);

    if (!$anterior) {
        spen_cancelar(
            $conexion,
            'La solicitud no existe o fue modificada por otro usuario.',
            409
        );
    }

    spen_validar_editable($conexion, $anterior);

    if ((string) $anterior['tipo_solicitud'] !== (string) $datos['tipo_solicitud']) {
        spen_cancelar(
            $conexion,
            'El tipo de solicitud no puede modificarse desde esta interfaz.',
            409
        );
    }

    $recursosAnteriores = spen_bloquear_recursos_recomendados($conexion, $id);
    $recursosSeleccionados = $recursosAnteriores;
    $recursosCambiaron = false;

    if ((string) $anterior['tipo_solicitud'] === 'CORRECTIVO_URGENTE') {
        $recursosSeleccionados = spen_validar_recursos_urgencia(
            $conexion,
            $datos['herramientas_ids'],
            $datos['refacciones_ids'],
            $datos['herramientas_libres'],
            $datos['refacciones_libres'],
            $recursosAnteriores
        );
        $recursosCambiaron = spen_firma_recursos($recursosAnteriores)
            !== spen_firma_recursos($recursosSeleccionados);

        if ($recursosCambiaron && spen_solicitud_iniciada($conexion, $id)) {
            spen_cancelar(
                $conexion,
                'Las herramientas y refacciones ya no pueden cambiarse porque el mantenimiento comenzó.',
                409,
                ['campo' => 'recursos_urgencia']
            );
        }
    }

    $causasAnteriores = spen_consultar_ids_causas_mejora($conexion, $id);
    $versionActual = (int) ($anterior['version_registro'] ?? 1);

    $sql = "
        UPDATE solicitudes
        SET
            departamento_id = :departamento_id,
            area_id = :area_id,
            proceso_id = :proceso_id,
            equipo_id = :equipo_id,
            fecha_sugerida = :fecha_sugerida,
            prioridad = :prioridad,
            descripcion_solicitud = :descripcion_solicitud,
            tipo_falla_id = :tipo_falla_id,
            causa_averia_id = :causa_averia_id,
            descripcion_falla = :descripcion_falla,
            causa_desconocida_descripcion = :causa_desconocida_descripcion,
            costo_vs_beneficio = :costo_vs_beneficio,
            impacto_operacion = :impacto_operacion,
            objetivo_mejora = :objetivo_mejora,
            resultado_esperado = :resultado_esperado,
            justificacion_mejora = :justificacion_mejora,
            observaciones_solicitante = :observaciones_solicitante,
            trabajo_peligroso = :trabajo_peligroso,
            detalle_trabajo_peligroso = :detalle_trabajo_peligroso,
            nivel_riesgo = :nivel_riesgo,
            requiere_paro_equipo = :requiere_paro_equipo,
            cupo_tecnicos_urgente = :cupo_tecnicos_urgente,
            ultima_edicion_admin_id = :admin_id,
            motivo_ultima_edicion = :motivo_edicion,
            version_registro = version_registro + 1
        WHERE id = :id
          AND version_registro = :version_actual
          AND activo = 1
    ";

    $stmt = $conexion->prepare($sql);
    spen_bind_formulario($stmt, $datos);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':motivo_edicion', $motivoEdicion, PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':version_actual', $versionActual, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        spen_cancelar(
            $conexion,
            'La solicitud cambió mientras la editabas. Actualiza la lista y vuelve a intentarlo.',
            409
        );
    }

    spen_guardar_causas_mejora(
        $conexion,
        $id,
        (string) $anterior['tipo_solicitud'],
        $datos['causas_mejora_ids']
    );

    if ((string) $anterior['tipo_solicitud'] === 'CORRECTIVO_URGENTE' && $recursosCambiaron) {
        spen_reemplazar_recursos_urgencia($conexion, $id, $recursosSeleccionados, $adminId);
        spen_reemplazar_memoria_urgencia_admin(
            $conexion,
            (int) $datos['equipo_id'],
            $id,
            $recursosSeleccionados,
            $adminId
        );
        spen_notificar_recursos_urgencia($conexion, $id, (string) $anterior['folio']);
    }

    $nuevo = spen_consultar_fila_solicitud($conexion, $id);
    $datosAnteriores = $anterior;
    $datosAnteriores['causas_mejora_ids'] = $causasAnteriores;
    $datosAnteriores['recursos_recomendados'] = spen_resumen_auditoria_recursos($recursosAnteriores);
    $datosNuevos = $nuevo;
    $datosNuevos['causas_mejora_ids'] = $datos['causas_mejora_ids'];
    $datosNuevos['recursos_recomendados'] = spen_resumen_auditoria_recursos(
        (string) $anterior['tipo_solicitud'] === 'CORRECTIVO_URGENTE'
            ? $recursosSeleccionados
            : $recursosAnteriores
    );

    spen_registrar_auditoria(
        $conexion,
        $id,
        $adminId,
        'UPDATE',
        $motivoEdicion,
        $datosAnteriores,
        $datosNuevos
    );

    spen_registrar_historial(
        $conexion,
        $id,
        'EDITADA',
        (string) $anterior['estado'],
        (string) $anterior['estado'],
        'El administrador corrigió la información de la solicitud. Motivo: ' . $motivoEdicion,
        $adminId
    );

    spen_registrar_movimiento(
        $conexion,
        'EDITAR_SOLICITUD',
        'Se corrigió la solicitud ' . (string) $anterior['folio'] . '. Motivo: ' . $motivoEdicion,
        $id
    );

    $conexion->commit();

    sm_responder_json(
        true,
        'Los cambios se guardaron correctamente.',
        [
            'id' => $id,
            'version_registro' => $versionActual + 1,
            'recursos_actualizados' => $recursosCambiaron,
        ]
    );
}

function spen_procesar(PDO $conexion): void
{
    $adminId = spen_admin_sesion();
    $id = spen_entero_positivo(
        $_POST['id'] ?? null,
        'Selecciona una solicitud válida.'
    );

    $tipoAccion = strtoupper(
        spen_normalizar_texto($_POST['tipo_accion'] ?? '')
    );

    if (!in_array($tipoAccion, ['APROBAR', 'REVISAR_URGENCIA', 'RECHAZAR'], true)) {
        sm_responder_json(
            false,
            'La acción seleccionada no es válida.',
            ['campo' => 'tipo_accion'],
            422
        );
    }

    $motivo = spen_normalizar_texto($_POST['motivo'] ?? '');

    if ($tipoAccion === 'RECHAZAR') {
        spen_validar_texto(
            $motivo,
            'motivo',
            'Escribe un motivo claro.',
            10,
            800,
            true
        );
    } elseif ($motivo !== '') {
        spen_validar_texto(
            $motivo,
            'motivo',
            'La observación no es válida.',
            0,
            800,
            false
        );
    }

    $conexion->beginTransaction();

    $solicitud = spen_bloquear_solicitud($conexion, $id);

    if (!$solicitud) {
        spen_cancelar(
            $conexion,
            'La solicitud no existe o fue procesada por otro usuario.',
            409
        );
    }

    spen_validar_editable($conexion, $solicitud);

    $tipoSolicitud = (string) $solicitud['tipo_solicitud'];
    $estadoAnterior = (string) $solicitud['estado'];

    if ($tipoAccion === 'APROBAR') {
        if ($tipoSolicitud === 'CORRECTIVO_URGENTE') {
            spen_cancelar(
                $conexion,
                'Las urgencias no se aprueban desde esta bandeja. Utiliza Marcar como revisada.',
                409
            );
        }

        $datosValidacion = spen_datos_desde_fila($solicitud);
        $datosValidacion['causas_mejora_ids'] = spen_consultar_ids_causas_mejora($conexion, $id);
        spen_validar_formulario($conexion, $datosValidacion);

        $nuevoEstado = 'APROBADO';

        $sql = "
            UPDATE solicitudes
            SET
                estado = 'APROBADO',
                revisado_por_admin_id = :admin_id,
                fecha_revision = NOW(),
                observaciones_revision = :observaciones,
                motivo_rechazo = NULL
            WHERE id = :id
              AND activo = 1
        ";

        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        spen_bind_nullable_text($stmt, ':observaciones', $motivo);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $evento = 'APROBADA';
        $accionMovimiento = 'APROBAR_SOLICITUD';
        $descripcion = 'Se aprobó la solicitud ' . (string) $solicitud['folio'] . '.';
        $tituloNotificacion = 'Solicitud aprobada';
        $mensajeNotificacion = 'Tu solicitud ' . (string) $solicitud['folio'] . ' fue aprobada y está lista para programarse.';
        $tipoNotificacion = 'SUCCESS';
        $mensajeRespuesta = 'Solicitud aprobada correctamente.';
    } elseif ($tipoAccion === 'REVISAR_URGENCIA') {
        if ($tipoSolicitud !== 'CORRECTIVO_URGENTE') {
            spen_cancelar(
                $conexion,
                'La acción Marcar como revisada solo corresponde a correctivos urgentes.',
                409
            );
        }

        $datosValidacion = spen_datos_desde_fila($solicitud);
        $datosValidacion['causas_mejora_ids'] = [];
        spen_validar_formulario($conexion, $datosValidacion);

        /*
         * La urgencia se publicó desde su registro. La revisión administrativa
         * no habilita su atención y nunca debe cambiar el estado operativo.
         */
        $estadosOperativosUrgencia = [
            'AGENDADO',
            'EN_PROCESO',
            'PAUSADO',
            'ATRASADO',
        ];

        if (!in_array($estadoAnterior, $estadosOperativosUrgencia, true)) {
            spen_cancelar(
                $conexion,
                'La urgencia ya no se encuentra en un estado operativo revisable.',
                409
            );
        }

        $nuevoEstado = $estadoAnterior;

        $sql = "
            UPDATE solicitudes
            SET
                revisado_por_admin_id = :admin_id,
                fecha_revision = NOW(),
                observaciones_revision = :observaciones,
                motivo_rechazo = NULL
            WHERE id = :id
              AND activo = 1
              AND revisado_por_admin_id IS NULL
        ";

        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        spen_bind_nullable_text($stmt, ':observaciones', $motivo);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() !== 1) {
            spen_cancelar(
                $conexion,
                'La urgencia ya fue revisada por otro administrador.',
                409
            );
        }

        $evento = 'OTRO';
        $accionMovimiento = 'MARCAR_URGENCIA_REVISADA';
        $descripcion = 'El administrador marcó como revisada la urgencia ' . (string) $solicitud['folio'] . ' sin modificar su estado operativo ' . $estadoAnterior . '.';
        $tituloNotificacion = 'Urgencia revisada';
        $mensajeNotificacion = 'La información de tu urgencia ' . (string) $solicitud['folio'] . ' fue revisada por el administrador. Su atención técnica continúa sin cambios.';
        $tipoNotificacion = 'SUCCESS';
        $mensajeRespuesta = 'La urgencia se marcó como revisada correctamente.';
    } elseif ($tipoAccion === 'RECHAZAR') {
        if (
            $tipoSolicitud === 'CORRECTIVO_URGENTE'
            && spen_urgencia_tiene_participacion($conexion, $id)
        ) {
            spen_cancelar(
                $conexion,
                'La urgencia ya tiene técnicos participantes y no puede rechazarse desde Solicitudes pendientes. Su control operativo deberá realizarse desde el módulo de urgencias activas.',
                409
            );
        }

        $nuevoEstado = 'RECHAZADO';

        $sql = "
            UPDATE solicitudes
            SET
                estado = 'RECHAZADO',
                revisado_por_admin_id = :admin_id,
                fecha_revision = NOW(),
                observaciones_revision = NULL,
                motivo_rechazo = :motivo
            WHERE id = :id
              AND activo = 1
        ";

        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':motivo', $motivo, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $evento = 'RECHAZADA';
        $accionMovimiento = 'RECHAZAR_SOLICITUD';
        $descripcion = 'Se rechazó la solicitud ' . (string) $solicitud['folio'] . '. Motivo: ' . $motivo;
        $tituloNotificacion = 'Solicitud rechazada';
        $mensajeNotificacion = 'Tu solicitud ' . (string) $solicitud['folio'] . ' fue rechazada. Motivo: ' . $motivo;
        $tipoNotificacion = 'DANGER';
        $mensajeRespuesta = 'Solicitud rechazada correctamente.';
    }

    spen_registrar_historial(
        $conexion,
        $id,
        $evento,
        $estadoAnterior,
        $nuevoEstado,
        $descripcion,
        $adminId
    );

    spen_registrar_movimiento(
        $conexion,
        $accionMovimiento,
        $descripcion,
        $id
    );

    spen_notificar_solicitante(
        $conexion,
        $solicitud,
        $id,
        $tituloNotificacion,
        $mensajeNotificacion,
        $tipoNotificacion,
        $adminId
    );

    spen_marcar_notificaciones_admin_leidas(
        $conexion,
        $id,
        $adminId
    );

    $conexion->commit();

    sm_responder_json(
        true,
        $mensajeRespuesta,
        [
            'id' => $id,
            'folio' => (string) $solicitud['folio'],
            'estado' => $nuevoEstado,
        ]
    );
}

/* =========================================================================
   CONSULTAS
   ========================================================================= */

function spen_consultar_catalogo(PDO $conexion, string $sql): array
{
    $stmt = $conexion->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function spen_consultar_solicitud_detalle(PDO $conexion, int $id)
{
    $sql = "
        SELECT
            s.*,

            DATE_FORMAT(s.fecha_solicitud, '%d/%m/%Y') AS fecha_solicitud_formato,
            TIME_FORMAT(s.hora_solicitud, '%H:%i') AS hora_solicitud_formato,
            DATE_FORMAT(s.fecha_registro, '%d/%m/%Y %H:%i') AS fecha_registro_formato,

            GREATEST(
                TIMESTAMPDIFF(
                    MINUTE,
                    TIMESTAMP(s.fecha_solicitud, s.hora_solicitud),
                    NOW()
                ),
                0
            ) AS minutos_espera,

            COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ', sol.nombre, sol.apellido_paterno, sol.apellido_materno)), ''),
                NULLIF(TRIM(CONCAT_WS(' ', adm_sol.nombre, adm_sol.apellido_paterno, adm_sol.apellido_materno)), ''),
                'Sin solicitante'
            ) AS solicitante,

            COALESCE(sol.telefono, adm_sol.telefono) AS solicitante_telefono,
            COALESCE(sol.correo, adm_sol.correo) AS solicitante_correo,

            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso,

            e.codigo_equipo,
            e.nombre_equipo,
            e.descripcion AS equipo_descripcion,

            tf.nombre AS tipo_falla,
            ca.nombre AS causa_averia

        FROM solicitudes s
        LEFT JOIN solicitantes sol
            ON sol.id = s.solicitante_id
        LEFT JOIN administradores adm_sol
            ON adm_sol.id = s.administrador_solicitante_id
        LEFT JOIN departamentos d
            ON d.id = s.departamento_id
        LEFT JOIN areas a
            ON a.id = s.area_id
        LEFT JOIN procesos p
            ON p.id = s.proceso_id
        LEFT JOIN equipos e
            ON e.id = s.equipo_id
        LEFT JOIN tipos_falla tf
            ON tf.id = s.tipo_falla_id
        LEFT JOIN causas_averia ca
            ON ca.id = s.causa_averia_id

        WHERE s.id = :id
          AND s.activo = 1
          AND (
                s.estado = 'PENDIENTE'
                OR (
                    s.tipo_solicitud = 'CORRECTIVO_URGENTE'
                    AND s.estado IN (
                        'AGENDADO',
                        'EN_PROCESO',
                        'PAUSADO',
                        'ATRASADO'
                    )
                    AND s.revisado_por_admin_id IS NULL
                )
          )
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fila) {
        return false;
    }

    $fila['causas_mejora_ids'] = spen_consultar_ids_causas_mejora($conexion, $id);
    $fila['recursos_recomendados'] = spen_consultar_recursos_recomendados($conexion, $id);
    $fila['recursos_editables'] = !spen_solicitud_iniciada($conexion, $id);

    return $fila;
}

function spen_bloquear_solicitud(PDO $conexion, int $id)
{
    $sql = "
        SELECT *
        FROM solicitudes
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    return $fila ?: false;
}

function spen_consultar_fila_solicitud(PDO $conexion, int $id): array
{
    $stmt = $conexion->prepare(
        "SELECT * FROM solicitudes WHERE id = :id LIMIT 1"
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function spen_consultar_ids_causas_mejora(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT causa_mejora_id
         FROM solicitud_causas_mejora
         WHERE solicitud_id = :solicitud_id
         ORDER BY causa_mejora_id"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    $ids = [];

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
        $ids[] = (int) $id;
    }

    return $ids;
}

/* =========================================================================
   VALIDACIÓN Y FORMULARIO
   ========================================================================= */

function spen_recibir_formulario(): array
{
    $tipo = strtoupper(
        spen_normalizar_texto($_POST['tipo_solicitud'] ?? '')
    );

    $causasMejora = $_POST['causas_mejora_ids'] ?? [];

    if (!is_array($causasMejora)) {
        $causasMejora = [];
    }

    $causasLimpias = [];

    foreach ($causasMejora as $causaId) {
        $id = filter_var(
            $causaId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id !== false) {
            $causasLimpias[] = (int) $id;
        }
    }

    $causasLimpias = array_values(array_unique($causasLimpias));

    return [
        'tipo_solicitud' => $tipo,
        'departamento_id' => spen_entero_nullable($_POST['departamento_id'] ?? null),
        'area_id' => spen_entero_nullable($_POST['area_id'] ?? null),
        'proceso_id' => spen_entero_nullable($_POST['proceso_id'] ?? null),
        'equipo_id' => spen_entero_nullable($_POST['equipo_id'] ?? null),
        'fecha_sugerida' => spen_fecha_nullable($_POST['fecha_sugerida'] ?? ''),
        'prioridad' => strtoupper(spen_normalizar_texto($_POST['prioridad'] ?? '')),
        'descripcion_solicitud' => spen_normalizar_texto($_POST['descripcion_solicitud'] ?? ''),
        'tipo_falla_id' => spen_entero_nullable($_POST['tipo_falla_id'] ?? null),
        'causa_averia_id' => spen_entero_nullable($_POST['causa_averia_id'] ?? null),
        'descripcion_falla' => spen_normalizar_texto($_POST['descripcion_falla'] ?? ''),
        'causa_desconocida_descripcion' => spen_normalizar_texto($_POST['causa_desconocida_descripcion'] ?? ''),
        'costo_vs_beneficio' => spen_normalizar_texto($_POST['costo_vs_beneficio'] ?? ''),
        'impacto_operacion' => spen_normalizar_texto($_POST['impacto_operacion'] ?? ''),
        'objetivo_mejora' => spen_normalizar_texto($_POST['objetivo_mejora'] ?? ''),
        'resultado_esperado' => spen_normalizar_texto($_POST['resultado_esperado'] ?? ''),
        'justificacion_mejora' => spen_normalizar_texto($_POST['justificacion_mejora'] ?? ''),
        'observaciones_solicitante' => spen_normalizar_texto($_POST['observaciones_solicitante'] ?? ''),
        'trabajo_peligroso' => spen_booleano($_POST['trabajo_peligroso'] ?? '0'),
        'detalle_trabajo_peligroso' => spen_normalizar_texto($_POST['detalle_trabajo_peligroso'] ?? ''),
        'nivel_riesgo' => strtoupper(spen_normalizar_texto($_POST['nivel_riesgo'] ?? '')),
        'requiere_paro_equipo' => spen_booleano($_POST['requiere_paro_equipo'] ?? '0'),
        'cupo_tecnicos_urgente' => $tipo === 'CORRECTIVO_URGENTE' ? 10 : 10,
        'causas_mejora_ids' => $causasLimpias,
        'herramientas_ids' => spen_lista_ids_post('herramientas_ids'),
        'refacciones_ids' => spen_lista_ids_post('refacciones_ids'),
        'herramientas_libres' => spen_lista_textos_post('herramientas_libres'),
        'refacciones_libres' => spen_lista_textos_post('refacciones_libres'),
    ];
}

function spen_datos_desde_fila(array $fila): array
{
    return [
        'tipo_solicitud' => (string) ($fila['tipo_solicitud'] ?? ''),
        'departamento_id' => (int) ($fila['departamento_id'] ?? 0),
        'area_id' => (int) ($fila['area_id'] ?? 0),
        'proceso_id' => (int) ($fila['proceso_id'] ?? 0),
        'equipo_id' => (int) ($fila['equipo_id'] ?? 0),
        'fecha_sugerida' => $fila['fecha_sugerida'] ?? null,
        'prioridad' => (string) ($fila['prioridad'] ?? ''),
        'descripcion_solicitud' => (string) ($fila['descripcion_solicitud'] ?? ''),
        'tipo_falla_id' => isset($fila['tipo_falla_id']) ? (int) $fila['tipo_falla_id'] : null,
        'causa_averia_id' => isset($fila['causa_averia_id']) ? (int) $fila['causa_averia_id'] : null,
        'descripcion_falla' => (string) ($fila['descripcion_falla'] ?? ''),
        'causa_desconocida_descripcion' => (string) ($fila['causa_desconocida_descripcion'] ?? ''),
        'costo_vs_beneficio' => (string) ($fila['costo_vs_beneficio'] ?? ''),
        'impacto_operacion' => (string) ($fila['impacto_operacion'] ?? ''),
        'objetivo_mejora' => (string) ($fila['objetivo_mejora'] ?? ''),
        'resultado_esperado' => (string) ($fila['resultado_esperado'] ?? ''),
        'justificacion_mejora' => (string) ($fila['justificacion_mejora'] ?? ''),
        'observaciones_solicitante' => (string) ($fila['observaciones_solicitante'] ?? ''),
        'trabajo_peligroso' => (int) ($fila['trabajo_peligroso'] ?? 0),
        'detalle_trabajo_peligroso' => (string) ($fila['detalle_trabajo_peligroso'] ?? ''),
        'nivel_riesgo' => (string) ($fila['nivel_riesgo'] ?? ''),
        'requiere_paro_equipo' => (int) ($fila['requiere_paro_equipo'] ?? 0),
        'cupo_tecnicos_urgente' => (int) ($fila['cupo_tecnicos_urgente'] ?? 10),
        'causas_mejora_ids' => [],
    ];
}

function spen_validar_formulario(PDO $conexion, array &$datos): void
{
    $tiposPermitidos = [
        'CORRECTIVO_PROGRAMABLE',
        'MODIFICACION_MEJORA',
        'CORRECTIVO_URGENTE',
    ];

    if (!in_array($datos['tipo_solicitud'], $tiposPermitidos, true)) {
        spen_error_campo(
            'tipo_solicitud',
            'El tipo de solicitud no es válido.'
        );
    }

    foreach (['departamento_id', 'area_id', 'proceso_id', 'equipo_id'] as $campo) {
        if ((int) $datos[$campo] <= 0) {
            spen_error_campo(
                $campo,
                'Selecciona una ubicación y un equipo válidos.'
            );
        }
    }

    $prioridades = ['BAJA', 'MEDIA', 'ALTA', 'URGENTE'];

    if (!in_array($datos['prioridad'], $prioridades, true)) {
        spen_error_campo(
            'prioridad',
            'Selecciona una prioridad válida.'
        );
    }

    if ($datos['tipo_solicitud'] === 'CORRECTIVO_URGENTE') {
        $datos['prioridad'] = 'URGENTE';
        $datos['cupo_tecnicos_urgente'] = 10;
    } elseif ($datos['prioridad'] === 'URGENTE') {
        spen_error_campo(
            'prioridad',
            'La prioridad urgente solo corresponde al correctivo urgente.'
        );
    }

    spen_validar_texto(
        $datos['descripcion_solicitud'],
        'descripcion_solicitud',
        'Escribe una descripción clara de al menos 10 caracteres.',
        10,
        3000,
        true
    );

    spen_validar_texto_opcional($datos['descripcion_falla'], 'descripcion_falla', 2000);
    spen_validar_texto_opcional($datos['causa_desconocida_descripcion'], 'causa_desconocida_descripcion', 1500);
    spen_validar_texto_opcional($datos['costo_vs_beneficio'], 'costo_vs_beneficio', 2500);
    spen_validar_texto_opcional($datos['impacto_operacion'], 'impacto_operacion', 2000);
    spen_validar_texto_opcional($datos['objetivo_mejora'], 'objetivo_mejora', 2000);
    spen_validar_texto_opcional($datos['resultado_esperado'], 'resultado_esperado', 2000);
    spen_validar_texto_opcional($datos['justificacion_mejora'], 'justificacion_mejora', 2500);
    spen_validar_texto_opcional($datos['observaciones_solicitante'], 'observaciones_solicitante', 2000);

    if (!in_array($datos['nivel_riesgo'], ['BAJO', 'MEDIO', 'ALTO'], true)) {
        spen_error_campo(
            'nivel_riesgo',
            'Selecciona un nivel de riesgo válido.'
        );
    }

    if ((int) $datos['trabajo_peligroso'] === 1) {
        spen_validar_texto(
            $datos['detalle_trabajo_peligroso'],
            'detalle_trabajo_peligroso',
            'Describe brevemente el peligro o la precaución necesaria.',
            3,
            200,
            true
        );
    } else {
        $datos['detalle_trabajo_peligroso'] = '';
        $datos['nivel_riesgo'] = 'BAJO';
    }

    if ($datos['tipo_solicitud'] === 'CORRECTIVO_URGENTE') {
        /*
         * El tipo de falla y la causa son deliberadamente opcionales durante
         * la revisión administrativa. El administrador puede limitarse a
         * agregar herramientas o refacciones. Si el diagnóstico continúa
         * incompleto, el primer técnico que inicie la urgencia lo capturará.
         */
        spen_validar_texto(
            $datos['impacto_operacion'],
            'impacto_operacion',
            'Describe cómo afecta la urgencia a la operación.',
            5,
            2000,
            true
        );
    }

    if ($datos['tipo_solicitud'] === 'MODIFICACION_MEJORA') {
        if (
            spen_longitud($datos['objetivo_mejora']) < 5
            && spen_longitud($datos['resultado_esperado']) < 5
        ) {
            spen_error_campo(
                'objetivo_mejora',
                'Escribe el objetivo de la mejora o el resultado esperado.'
            );
        }
    }

    spen_validar_jerarquia($conexion, $datos);
    spen_validar_catalogo_opcional(
        $conexion,
        'tipos_falla',
        $datos['tipo_falla_id'],
        'tipo_falla_id',
        'El tipo de falla seleccionado no está disponible.'
    );
    spen_validar_catalogo_opcional(
        $conexion,
        'causas_averia',
        $datos['causa_averia_id'],
        'causa_averia_id',
        'La causa de avería seleccionada no está disponible.'
    );

    if ($datos['tipo_solicitud'] === 'MODIFICACION_MEJORA') {
        spen_validar_causas_mejora($conexion, $datos['causas_mejora_ids']);
    } else {
        $datos['causas_mejora_ids'] = [];
    }
}

function spen_validar_jerarquia(PDO $conexion, array $datos): void
{
    $sql = "
        SELECT
            e.id,
            e.activo AS equipo_activo,
            e.departamento_id AS equipo_departamento_id,
            e.area_id AS equipo_area_id,
            e.proceso_id AS equipo_proceso_id,

            d.activo AS departamento_activo,

            a.activo AS area_activa,
            a.departamento_id AS area_departamento_id,

            p.activo AS proceso_activo,
            p.area_id AS proceso_area_id

        FROM equipos e
        INNER JOIN departamentos d
            ON d.id = :departamento_id
        INNER JOIN areas a
            ON a.id = :area_id
        INNER JOIN procesos p
            ON p.id = :proceso_id
        WHERE e.id = :equipo_id
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':departamento_id', $datos['departamento_id'], PDO::PARAM_INT);
    $stmt->bindValue(':area_id', $datos['area_id'], PDO::PARAM_INT);
    $stmt->bindValue(':proceso_id', $datos['proceso_id'], PDO::PARAM_INT);
    $stmt->bindValue(':equipo_id', $datos['equipo_id'], PDO::PARAM_INT);
    $stmt->execute();

    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fila) {
        spen_error_campo(
            'equipo_id',
            'No fue posible validar la ubicación y el equipo.'
        );
    }

    if ((int) $fila['departamento_activo'] !== 1) {
        spen_error_campo(
            'departamento_id',
            'El departamento seleccionado está desactivado.'
        );
    }

    if (
        (int) $fila['area_activa'] !== 1
        || (int) $fila['area_departamento_id'] !== (int) $datos['departamento_id']
    ) {
        spen_error_campo(
            'area_id',
            'El área no está activa o no pertenece al departamento.'
        );
    }

    if (
        (int) $fila['proceso_activo'] !== 1
        || (int) $fila['proceso_area_id'] !== (int) $datos['area_id']
    ) {
        spen_error_campo(
            'proceso_id',
            'El proceso no está activo o no pertenece al área.'
        );
    }

    if (
        (int) $fila['equipo_activo'] !== 1
        || (int) $fila['equipo_departamento_id'] !== (int) $datos['departamento_id']
        || (int) $fila['equipo_area_id'] !== (int) $datos['area_id']
        || (int) $fila['equipo_proceso_id'] !== (int) $datos['proceso_id']
    ) {
        spen_error_campo(
            'equipo_id',
            'El equipo no está activo o no pertenece a la ubicación seleccionada.'
        );
    }
}

function spen_validar_catalogo_opcional(
    PDO $conexion,
    string $tabla,
    $id,
    string $campo,
    string $mensaje
): void {
    if ($id === null || (int) $id <= 0) {
        return;
    }

    $tablasPermitidas = ['tipos_falla', 'causas_averia'];

    if (!in_array($tabla, $tablasPermitidas, true)) {
        spen_error_campo($campo, $mensaje);
    }

    $sql = "SELECT COUNT(*) FROM {$tabla} WHERE id = :id AND activo = 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
    $stmt->execute();

    if ((int) $stmt->fetchColumn() !== 1) {
        spen_error_campo($campo, $mensaje);
    }
}

function spen_validar_causas_mejora(PDO $conexion, array $ids): void
{
    if ($ids === []) {
        return;
    }

    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conexion->prepare(
        "SELECT COUNT(*)
         FROM causas_mejora
         WHERE activo = 1
           AND id IN ({$marcadores})"
    );
    $stmt->execute($ids);

    if ((int) $stmt->fetchColumn() !== count($ids)) {
        spen_error_campo(
            'causas_mejora_ids',
            'Una de las causas de mejora seleccionadas ya no está disponible.'
        );
    }
}

function spen_validar_editable(PDO $conexion, array $solicitud): void
{
    if ((int) ($solicitud['activo'] ?? 0) !== 1) {
        spen_cancelar(
            $conexion,
            'La solicitud está inactiva.',
            409
        );
    }

    $tipo = (string) ($solicitud['tipo_solicitud'] ?? '');
    $estado = (string) ($solicitud['estado'] ?? '');
    $revisada = (int) ($solicitud['revisado_por_admin_id'] ?? 0) > 0;

    $esPendienteNormal = $estado === 'PENDIENTE';
    $esUrgenteSinRevisar = (
        $tipo === 'CORRECTIVO_URGENTE'
        && in_array(
            $estado,
            [
                'AGENDADO',
                'EN_PROCESO',
                'PAUSADO',
                'ATRASADO',
            ],
            true
        )
        && !$revisada
    );

    if (!$esPendienteNormal && !$esUrgenteSinRevisar) {
        spen_cancelar(
            $conexion,
            'La solicitud ya no se encuentra en la bandeja de revisión.',
            409
        );
    }
}

function spen_lista_ids_post(string $campo): array
{
    $valores = $_POST[$campo] ?? [];
    if (!is_array($valores)) {
        $valores = [$valores];
    }

    $ids = [];
    foreach ($valores as $valor) {
        $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id !== false) {
            $ids[] = (int) $id;
        }
    }

    return array_values(array_unique($ids));
}

function spen_lista_textos_post(string $campo): array
{
    $valores = $_POST[$campo] ?? [];
    if (!is_array($valores)) {
        $valores = [$valores];
    }

    $salida = [];
    foreach ($valores as $valor) {
        $texto = spen_normalizar_texto($valor);
        if ($texto !== '' && mb_strlen($texto, 'UTF-8') <= 150) {
            $salida[] = $texto;
        }
    }

    return array_values(array_unique($salida));
}

function spen_consultar_recursos_recomendados(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            srr.id,
            srr.tipo_recurso,
            srr.recurso_id,
            srr.nombre_no_catalogado,
            srr.origen,
            rm.nombre,
            rm.codigo,
            rm.descripcion,
            rm.activo
         FROM solicitud_recursos_recomendados srr
         LEFT JOIN recursos_mantenimiento rm ON rm.id = srr.recurso_id
         WHERE srr.solicitud_id = :solicitud_id
         ORDER BY srr.tipo_recurso, COALESCE(rm.nombre, srr.nombre_no_catalogado), srr.id"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function spen_bloquear_recursos_recomendados(PDO $conexion, int $solicitudId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            srr.id,
            srr.tipo_recurso,
            srr.recurso_id,
            srr.nombre_no_catalogado,
            srr.origen,
            rm.nombre,
            rm.codigo,
            rm.descripcion,
            rm.activo
         FROM solicitud_recursos_recomendados srr
         LEFT JOIN recursos_mantenimiento rm ON rm.id = srr.recurso_id
         WHERE srr.solicitud_id = :solicitud_id
         ORDER BY srr.id"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function spen_solicitud_iniciada(PDO $conexion, int $solicitudId): bool
{
    $stmt = $conexion->prepare(
        "SELECT COUNT(*)
         FROM ejecuciones_mantenimiento
         WHERE solicitud_id = :solicitud_id
           AND fecha_hora_inicio IS NOT NULL"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function spen_validar_recursos_urgencia(
    PDO $conexion,
    array $herramientasIds,
    array $refaccionesIds,
    array $herramientasLibres,
    array $refaccionesLibres,
    array $actuales
): array {
    $actualesIds = [];
    $libresPermitidos = ['HERRAMIENTA' => [], 'REFACCION' => []];
    foreach ($actuales as $actual) {
        $recursoId = (int) ($actual['recurso_id'] ?? 0);
        if ($recursoId > 0) {
            $actualesIds[$recursoId] = true;
            continue;
        }
        $tipo = (string) ($actual['tipo_recurso'] ?? '');
        $nombre = spen_normalizar_texto($actual['nombre_no_catalogado'] ?? '');
        if (isset($libresPermitidos[$tipo]) && $nombre !== '') {
            $libresPermitidos[$tipo][mb_strtolower($nombre, 'UTF-8')] = $nombre;
        }
    }

    $pedidos = [];
    foreach ($herramientasIds as $id) {
        $pedidos[(int) $id] = 'HERRAMIENTA';
    }
    foreach ($refaccionesIds as $id) {
        $pedidos[(int) $id] = 'REFACCION';
    }

    $catalogo = [];
    if ($pedidos !== []) {
        $marcas = implode(',', array_fill(0, count($pedidos), '?'));
        $stmt = $conexion->prepare(
            "SELECT id, tipo_recurso, nombre, codigo, descripcion, activo
             FROM recursos_mantenimiento
             WHERE id IN ($marcas)"
        );
        $stmt->execute(array_keys($pedidos));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $recurso) {
            $catalogo[(int) $recurso['id']] = $recurso;
        }
    }

    $salida = [];
    foreach ($pedidos as $id => $tipoEsperado) {
        if (!isset($catalogo[$id])) {
            spen_cancelar($conexion, 'Una herramienta o refacción seleccionada ya no existe.', 422, ['campo' => 'recursos_urgencia']);
        }
        $recurso = $catalogo[$id];
        if ((string) $recurso['tipo_recurso'] !== $tipoEsperado) {
            spen_cancelar($conexion, 'Una selección no corresponde al tipo de recurso indicado.', 422, ['campo' => 'recursos_urgencia']);
        }
        if ((int) $recurso['activo'] !== 1 && !isset($actualesIds[$id])) {
            spen_cancelar($conexion, 'Un recurso seleccionado fue desactivado. Actualiza la búsqueda.', 409, ['campo' => 'recursos_urgencia']);
        }
        $salida[] = [
            'tipo_recurso' => $tipoEsperado,
            'recurso_id' => $id,
            'nombre_no_catalogado' => null,
            'nombre' => (string) $recurso['nombre'],
            'codigo' => $recurso['codigo'],
            'descripcion' => $recurso['descripcion'],
            'activo' => (int) $recurso['activo'],
        ];
    }

    foreach ([
        'HERRAMIENTA' => $herramientasLibres,
        'REFACCION' => $refaccionesLibres,
    ] as $tipo => $nombres) {
        foreach ($nombres as $nombre) {
            $clave = mb_strtolower(spen_normalizar_texto($nombre), 'UTF-8');
            if (!isset($libresPermitidos[$tipo][$clave])) {
                spen_cancelar($conexion, 'Los textos no catalogados solo pueden conservarse o retirarse; no pueden agregarse desde este formulario.', 422, ['campo' => 'recursos_urgencia']);
            }
            $salida[] = [
                'tipo_recurso' => $tipo,
                'recurso_id' => null,
                'nombre_no_catalogado' => $libresPermitidos[$tipo][$clave],
                'nombre' => $libresPermitidos[$tipo][$clave],
                'codigo' => null,
                'descripcion' => null,
                'activo' => 1,
            ];
        }
    }

    usort($salida, static function (array $a, array $b): int {
        return strcmp(
            (string) $a['tipo_recurso'] . '|' . (string) ($a['nombre'] ?? ''),
            (string) $b['tipo_recurso'] . '|' . (string) ($b['nombre'] ?? '')
        );
    });

    return $salida;
}

function spen_firma_recursos(array $recursos): string
{
    $partes = [];
    foreach ($recursos as $recurso) {
        $id = (int) ($recurso['recurso_id'] ?? 0);
        $nombre = mb_strtolower(spen_normalizar_texto($recurso['nombre_no_catalogado'] ?? ''), 'UTF-8');
        $partes[] = (string) ($recurso['tipo_recurso'] ?? '') . '|' . ($id > 0 ? 'ID:' . $id : 'TXT:' . $nombre);
    }
    sort($partes, SORT_STRING);
    return implode(';', $partes);
}

function spen_resumen_auditoria_recursos(array $recursos): array
{
    $salida = [];
    foreach ($recursos as $recurso) {
        $salida[] = [
            'tipo' => (string) ($recurso['tipo_recurso'] ?? ''),
            'recurso_id' => (int) ($recurso['recurso_id'] ?? 0) ?: null,
            'nombre' => (string) ($recurso['nombre'] ?? $recurso['nombre_no_catalogado'] ?? ''),
            'origen' => (string) ($recurso['origen'] ?? 'ADMIN'),
        ];
    }
    return $salida;
}

function spen_reemplazar_recursos_urgencia(PDO $conexion, int $solicitudId, array $recursos, int $adminId): void
{
    $stmtEliminar = $conexion->prepare(
        "DELETE FROM solicitud_recursos_recomendados WHERE solicitud_id = :solicitud_id"
    );
    $stmtEliminar->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtEliminar->execute();

    if ($recursos === []) {
        return;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO solicitud_recursos_recomendados
        (solicitud_id, tipo_recurso, recurso_id, nombre_no_catalogado, origen,
         agregado_por_admin_id, fecha_registro, fecha_actualizacion)
        VALUES
        (:solicitud_id, :tipo_recurso, :recurso_id, :nombre_no_catalogado, 'ADMIN',
         :admin_id, NOW(), NOW())"
    );

    foreach ($recursos as $recurso) {
        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(':tipo_recurso', (string) $recurso['tipo_recurso'], PDO::PARAM_STR);
        $recursoId = (int) ($recurso['recurso_id'] ?? 0);
        $recursoId > 0
            ? $stmt->bindValue(':recurso_id', $recursoId, PDO::PARAM_INT)
            : $stmt->bindValue(':recurso_id', null, PDO::PARAM_NULL);
        $nombreLibre = $recursoId > 0 ? null : (string) $recurso['nombre_no_catalogado'];
        $nombreLibre === null
            ? $stmt->bindValue(':nombre_no_catalogado', null, PDO::PARAM_NULL)
            : $stmt->bindValue(':nombre_no_catalogado', $nombreLibre, PDO::PARAM_STR);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->execute();
    }
}

function spen_reemplazar_memoria_urgencia_admin(PDO $conexion, int $equipoId, int $solicitudId, array $recursos, int $adminId): void
{
    $stmtEliminar = $conexion->prepare(
        "DELETE FROM recomendaciones_recursos
         WHERE equipo_id = :equipo_id
           AND tipo_solicitud = 'CORRECTIVO_URGENTE'"
    );
    $stmtEliminar->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
    $stmtEliminar->execute();

    if ($recursos === []) {
        return;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO recomendaciones_recursos
        (equipo_id, tipo_solicitud, tipo_recurso, recurso_id, nombre_no_catalogado,
         origen_ultima_actualizacion, solicitud_origen_id, actualizado_por_admin_id,
         actualizado_por_tecnico_id, fecha_registro, fecha_actualizacion)
        VALUES
        (:equipo_id, 'CORRECTIVO_URGENTE', :tipo_recurso, :recurso_id, :nombre_no_catalogado,
         'ADMIN', :solicitud_id, :admin_id, NULL, NOW(), NOW())"
    );

    foreach ($recursos as $recurso) {
        $stmt->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
        $stmt->bindValue(':tipo_recurso', (string) $recurso['tipo_recurso'], PDO::PARAM_STR);
        $recursoId = (int) ($recurso['recurso_id'] ?? 0);
        $recursoId > 0
            ? $stmt->bindValue(':recurso_id', $recursoId, PDO::PARAM_INT)
            : $stmt->bindValue(':recurso_id', null, PDO::PARAM_NULL);
        $nombreLibre = $recursoId > 0 ? null : (string) $recurso['nombre_no_catalogado'];
        $nombreLibre === null
            ? $stmt->bindValue(':nombre_no_catalogado', null, PDO::PARAM_NULL)
            : $stmt->bindValue(':nombre_no_catalogado', $nombreLibre, PDO::PARAM_STR);
        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->execute();
    }
}

function spen_notificar_recursos_urgencia(PDO $conexion, int $solicitudId, string $folio): void
{
    $stmtTecnicos = $conexion->prepare(
        "SELECT DISTINCT tecnico_id
         FROM solicitud_tecnicos
         WHERE solicitud_id = :solicitud_id
           AND activo = 1
           AND estado IN ('ASIGNADO','ACEPTADO')"
    );
    $stmtTecnicos->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtTecnicos->execute();
    $tecnicos = $stmtTecnicos->fetchAll(PDO::FETCH_COLUMN) ?: [];

    if ($tecnicos === []) {
        return;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO notificaciones
        (tipo_usuario, usuario_id, solicitud_id, titulo, mensaje, tipo, leida, fecha_creacion)
        VALUES
        ('TECNICO', :tecnico_id, :solicitud_id, 'Recomendaciones actualizadas',
         :mensaje, 'INFO', 0, NOW())"
    );
    foreach ($tecnicos as $tecnicoId) {
        $stmt->bindValue(':tecnico_id', (int) $tecnicoId, PDO::PARAM_INT);
        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(':mensaje', 'El administrador actualizó las herramientas o refacciones recomendadas para la urgencia ' . $folio . '.', PDO::PARAM_STR);
        $stmt->execute();
    }
}

/* =========================================================================
   GUARDADO RELACIONADO
   ========================================================================= */

function spen_bind_formulario(PDOStatement $stmt, array $datos): void
{
    $stmt->bindValue(':departamento_id', $datos['departamento_id'], PDO::PARAM_INT);
    $stmt->bindValue(':area_id', $datos['area_id'], PDO::PARAM_INT);
    $stmt->bindValue(':proceso_id', $datos['proceso_id'], PDO::PARAM_INT);
    $stmt->bindValue(':equipo_id', $datos['equipo_id'], PDO::PARAM_INT);

    spen_bind_nullable_text($stmt, ':fecha_sugerida', $datos['fecha_sugerida']);
    $stmt->bindValue(':prioridad', $datos['prioridad'], PDO::PARAM_STR);
    $stmt->bindValue(':descripcion_solicitud', $datos['descripcion_solicitud'], PDO::PARAM_STR);

    spen_bind_nullable_int($stmt, ':tipo_falla_id', $datos['tipo_falla_id']);
    spen_bind_nullable_int($stmt, ':causa_averia_id', $datos['causa_averia_id']);

    spen_bind_nullable_text($stmt, ':descripcion_falla', $datos['descripcion_falla']);
    spen_bind_nullable_text($stmt, ':causa_desconocida_descripcion', $datos['causa_desconocida_descripcion']);
    spen_bind_nullable_text($stmt, ':costo_vs_beneficio', $datos['costo_vs_beneficio']);
    spen_bind_nullable_text($stmt, ':impacto_operacion', $datos['impacto_operacion']);
    spen_bind_nullable_text($stmt, ':objetivo_mejora', $datos['objetivo_mejora']);
    spen_bind_nullable_text($stmt, ':resultado_esperado', $datos['resultado_esperado']);
    spen_bind_nullable_text($stmt, ':justificacion_mejora', $datos['justificacion_mejora']);
    spen_bind_nullable_text($stmt, ':observaciones_solicitante', $datos['observaciones_solicitante']);

    $stmt->bindValue(':trabajo_peligroso', $datos['trabajo_peligroso'], PDO::PARAM_INT);
    spen_bind_nullable_text($stmt, ':detalle_trabajo_peligroso', $datos['detalle_trabajo_peligroso']);
    $stmt->bindValue(':nivel_riesgo', $datos['nivel_riesgo'], PDO::PARAM_STR);
    $stmt->bindValue(':requiere_paro_equipo', $datos['requiere_paro_equipo'], PDO::PARAM_INT);
    $stmt->bindValue(':cupo_tecnicos_urgente', $datos['cupo_tecnicos_urgente'], PDO::PARAM_INT);
}

function spen_guardar_causas_mejora(
    PDO $conexion,
    int $solicitudId,
    string $tipoSolicitud,
    array $ids
): void {
    $stmt = $conexion->prepare(
        "DELETE FROM solicitud_causas_mejora WHERE solicitud_id = :solicitud_id"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    if ($tipoSolicitud !== 'MODIFICACION_MEJORA' || $ids === []) {
        return;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO solicitud_causas_mejora
        (
            solicitud_id,
            causa_mejora_id,
            fecha_registro
        )
        VALUES
        (
            :solicitud_id,
            :causa_mejora_id,
            NOW()
        )"
    );

    foreach ($ids as $causaId) {
        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(':causa_mejora_id', $causaId, PDO::PARAM_INT);
        $stmt->execute();
    }
}

function spen_urgencia_tiene_participacion(PDO $conexion, int $solicitudId): bool
{
    /*
     * Una urgencia que alguna vez fue aceptada o inició una ejecución ya no se
     * rechaza desde esta bandeja. Debe cancelarse desde Urgencias activas para
     * conservar participantes, tiempos, pausas y auditoría.
     */
    $stmt = $conexion->prepare(
        "SELECT
            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos st
                WHERE st.solicitud_id = :solicitud_id_tecnicos
                  AND st.origen = 'ACEPTACION_URGENTE'
            )
            +
            (
                SELECT COUNT(*)
                FROM ejecuciones_mantenimiento em
                WHERE em.solicitud_id = :solicitud_id_ejecuciones
            ) AS total_participacion"
    );
    $stmt->bindValue(':solicitud_id_tecnicos', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id_ejecuciones', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function spen_cancelar_participaciones_activas(PDO $conexion, int $solicitudId): void
{
    $stmt = $conexion->prepare(
        "UPDATE pausas_ejecucion pe
         INNER JOIN ejecuciones_mantenimiento em
            ON em.id = pe.ejecucion_id
         SET
            pe.fecha_hora_fin = COALESCE(pe.fecha_hora_fin, NOW()),
            pe.pausa_abierta_token = NULL,
            pe.duracion_segundos = CASE
                WHEN pe.fecha_hora_fin IS NULL
                    THEN GREATEST(TIMESTAMPDIFF(SECOND, pe.fecha_hora_inicio, NOW()), 0)
                ELSE pe.duracion_segundos
            END
         WHERE em.solicitud_id = :solicitud_id
           AND pe.fecha_hora_fin IS NULL"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $conexion->prepare(
        "UPDATE ejecuciones_mantenimiento
         SET
            estado = 'CANCELADA',
            fecha_hora_fin = COALESCE(fecha_hora_fin, NOW()),
            en_proceso_token = NULL
         WHERE solicitud_id = :solicitud_id
           AND estado IN ('PENDIENTE', 'EN_PROCESO', 'PAUSADA')"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $conexion->prepare(
        "UPDATE solicitud_tecnicos
         SET
            estado = 'RETIRADO',
            activo = 0,
            activo_token = NULL,
            fecha_retiro = COALESCE(fecha_retiro, NOW())
         WHERE solicitud_id = :solicitud_id
           AND activo = 1
           AND estado NOT IN ('TERMINADO', 'RETIRADO')"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
}

/* =========================================================================
   AUDITORÍA, HISTORIAL Y NOTIFICACIONES
   ========================================================================= */

function spen_registrar_auditoria(
    PDO $conexion,
    int $solicitudId,
    int $adminId,
    string $accion,
    string $motivo,
    array $anteriores,
    array $nuevos
): void {
    $sql = "
        INSERT INTO auditoria_ediciones
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
            'solicitudes',
            :registro_id,
            :solicitud_id,
            'ADMIN',
            :actor_id,
            :accion,
            :motivo,
            :datos_anteriores,
            :datos_nuevos,
            :ip_address,
            :user_agent,
            NOW()
        )
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':registro_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':motivo', $motivo, PDO::PARAM_STR);
    $stmt->bindValue(
        ':datos_anteriores',
        json_encode($anteriores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':datos_nuevos',
        json_encode($nuevos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        PDO::PARAM_STR
    );
    spen_bind_nullable_text($stmt, ':ip_address', sm_ip_cliente());
    spen_bind_nullable_text($stmt, ':user_agent', sm_user_agent());
    $stmt->execute();
}

function spen_registrar_historial(
    PDO $conexion,
    int $solicitudId,
    string $evento,
    ?string $estadoAnterior,
    ?string $estadoNuevo,
    string $descripcion,
    int $adminId
): void {
    $sql = "
        INSERT INTO historial_solicitudes
        (
            solicitud_id,
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
            :evento,
            :estado_anterior,
            :estado_nuevo,
            'ADMIN',
            :actor_id,
            :descripcion,
            NOW()
        )
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':evento', $evento, PDO::PARAM_STR);
    spen_bind_nullable_text($stmt, ':estado_anterior', $estadoAnterior);
    spen_bind_nullable_text($stmt, ':estado_nuevo', $estadoNuevo);
    $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->execute();
}

function spen_registrar_movimiento(
    PDO $conexion,
    string $accion,
    string $descripcion,
    int $registroId
): void {
    $sql = "
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
            user_agent,
            fecha_movimiento
        )
        VALUES
        (
            'ADMIN',
            :usuario_id,
            :accion,
            'Solicitudes pendientes',
            :descripcion,
            'solicitudes',
            :registro_id,
            :ip_address,
            :user_agent,
            NOW()
        )
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':usuario_id', spen_admin_sesion(), PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    spen_bind_nullable_text($stmt, ':ip_address', sm_ip_cliente());
    spen_bind_nullable_text($stmt, ':user_agent', sm_user_agent());
    $stmt->execute();
}

function spen_notificar_solicitante(
    PDO $conexion,
    array $solicitud,
    int $solicitudId,
    string $titulo,
    string $mensaje,
    string $tipo,
    int $adminActualId
): void {
    $tipoUsuario = '';
    $usuarioId = 0;

    if ((int) ($solicitud['solicitante_id'] ?? 0) > 0) {
        $tipoUsuario = 'SOLICITANTE';
        $usuarioId = (int) $solicitud['solicitante_id'];
    } elseif ((int) ($solicitud['administrador_solicitante_id'] ?? 0) > 0) {
        $tipoUsuario = 'ADMIN';
        $usuarioId = (int) $solicitud['administrador_solicitante_id'];
    } elseif ((string) ($solicitud['creado_por_tipo'] ?? '') === 'SOLICITANTE') {
        $tipoUsuario = 'SOLICITANTE';
        $usuarioId = (int) ($solicitud['creado_por_id'] ?? 0);
    } elseif ((string) ($solicitud['creado_por_tipo'] ?? '') === 'ADMIN') {
        $tipoUsuario = 'ADMIN';
        $usuarioId = (int) ($solicitud['creado_por_id'] ?? 0);
    }

    if ($usuarioId <= 0 || $tipoUsuario === '') {
        return;
    }

    if ($tipoUsuario === 'ADMIN' && $usuarioId === $adminActualId) {
        return;
    }

    $sql = "
        INSERT INTO notificaciones
        (
            tipo_usuario,
            usuario_id,
            solicitud_id,
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
            :titulo,
            :mensaje,
            :tipo,
            0,
            NOW()
        )
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':tipo_usuario', $tipoUsuario, PDO::PARAM_STR);
    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
    $stmt->bindValue(':mensaje', $mensaje, PDO::PARAM_STR);
    $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
    $stmt->execute();
}

function spen_marcar_notificaciones_admin_leidas(
    PDO $conexion,
    int $solicitudId,
    int $adminId
): void {
    $stmt = $conexion->prepare(
        "UPDATE notificaciones
         SET
            leida = 1,
            fecha_lectura = COALESCE(fecha_lectura, NOW())
         WHERE solicitud_id = :solicitud_id
           AND tipo_usuario = 'ADMIN'
           AND usuario_id = :admin_id
           AND leida = 0"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->execute();
}

/* =========================================================================
   AYUDANTES
   ========================================================================= */

function spen_admin_sesion(): int
{
    $id = (int) ($_SESSION['usuario_id'] ?? 0);

    if ($id <= 0) {
        sm_responder_json(
            false,
            'No se encontró el administrador de la sesión.',
            [],
            401
        );
    }

    return $id;
}

function spen_entero_positivo($valor, string $mensaje): int
{
    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($entero === false) {
        sm_responder_json(
            false,
            $mensaje,
            [],
            422
        );
    }

    return (int) $entero;
}

function spen_entero_nullable($valor): ?int
{
    if ($valor === null || $valor === '') {
        return null;
    }

    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    return $entero === false ? null : (int) $entero;
}

function spen_booleano($valor): int
{
    return in_array((string) $valor, ['1', 'true', 'on', 'SI'], true)
        ? 1
        : 0;
}

function spen_fecha_nullable($valor): ?string
{
    $fecha = trim((string) $valor);

    if ($fecha === '') {
        return null;
    }

    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    $errores = DateTimeImmutable::getLastErrors();

    if (
        !$objeto
        || (
            is_array($errores)
            && (
                (int) ($errores['warning_count'] ?? 0) > 0
                || (int) ($errores['error_count'] ?? 0) > 0
            )
        )
        || $objeto->format('Y-m-d') !== $fecha
    ) {
        spen_error_campo(
            'fecha_sugerida',
            'La fecha sugerida no es válida.'
        );
    }

    return $fecha;
}

function spen_normalizar_texto($valor): string
{
    $texto = sm_limpiar_texto($valor);
    $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
    $texto = preg_replace('/\R{3,}/u', "\n\n", $texto) ?? $texto;

    return trim($texto);
}

function spen_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? (int) mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function spen_validar_texto(
    string $texto,
    string $campo,
    string $mensaje,
    int $minimo,
    int $maximo,
    bool $requiereLetras
): void {
    $longitud = spen_longitud($texto);

    if ($longitud < $minimo || $longitud > $maximo) {
        spen_error_campo($campo, $mensaje);
    }

    if ($requiereLetras && !preg_match('/\p{L}/u', $texto)) {
        spen_error_campo($campo, $mensaje);
    }
}

function spen_validar_texto_opcional(
    string $texto,
    string $campo,
    int $maximo
): void {
    if ($texto === '') {
        return;
    }

    if (spen_longitud($texto) > $maximo) {
        spen_error_campo(
            $campo,
            'El contenido supera la longitud permitida.'
        );
    }
}

function spen_error_campo(string $campo, string $mensaje): void
{
    sm_responder_json(
        false,
        $mensaje,
        ['campo' => $campo],
        422
    );
}

function spen_bind_nullable_int(
    PDOStatement $stmt,
    string $parametro,
    $valor
): void {
    if ($valor === null || (int) $valor <= 0) {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, (int) $valor, PDO::PARAM_INT);
}

function spen_bind_nullable_text(
    PDOStatement $stmt,
    string $parametro,
    $valor
): void {
    if ($valor === null || trim((string) $valor) === '') {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, (string) $valor, PDO::PARAM_STR);
}

function spen_cancelar(
    PDO $conexion,
    string $mensaje,
    int $codigo,
    array $datos = []
): void {
    if ($conexion->inTransaction()) {
        $conexion->rollBack(); 
    }

    sm_responder_json(
        false,
        $mensaje,
        $datos,
        $codigo
    );
}