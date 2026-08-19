<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Catálogo de herramientas y refacciones - endpoints administrativos
|--------------------------------------------------------------------------
| Alta, edición, consulta, desactivación y reactivación con transacciones,
| CSRF, auditoría y conservación íntegra del historial.
| Compatible con PHP 7.4 o superior.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';
require_once __DIR__ . '/recursos_mantenimiento_servicio.php';

sm_requerir_sesion(['ADMIN'], true);

if (!isset($conexion) || !($conexion instanceof PDO)) {
    sm_responder_json(false, 'No fue posible conectar con la base de datos.', [], 503);
}

try {
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[RECURSOS MANTENIMIENTO][PDO CONFIG] ' . $e->getMessage());
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = rsm_mayusculas(rsm_texto(
    $metodo === 'GET'
        ? ($_GET['accion'] ?? 'INICIAL')
        : ($_POST['accion'] ?? '')
));

try {
    $adminId = rsm_admin_id();
    rsm_validar_admin_activo($conexion, $adminId);
    rsm_verificar_estructura($conexion);

    if ($metodo === 'GET') {
        sm_requerir_metodo('GET');

        if ($accion === 'INICIAL' || $accion === 'LISTAR') {
            rmc_endpoint_listar($conexion);
        }

        if ($accion === 'DETALLE') {
            rmc_endpoint_detalle($conexion);
        }

        if ($accion === 'BUSCAR_ACTIVOS') {
            rmc_endpoint_buscar_activos($conexion);
        }

        sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
    }

    sm_requerir_metodo('POST');
    sm_validar_csrf();

    if ($accion === 'GUARDAR') {
        rmc_endpoint_guardar($conexion, $adminId);
    }

    if ($accion === 'CAMBIAR_ESTADO') {
        rmc_endpoint_cambiar_estado($conexion, $adminId);
    }

    if ($accion === 'ATENDER_SUGERENCIA') {
        rmc_endpoint_atender_sugerencia($conexion, $adminId);
    }

    if ($accion === 'COMPLETAR_CODIGOS') {
        rmc_endpoint_completar_codigos($conexion, $adminId);
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'RMC-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][RECURSOS MANTENIMIENTO][PDO] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    if ((string) $e->getCode() === '23000') {
        sm_responder_json(
            false,
            'Ya existe una herramienta o refacción con ese nombre o código dentro del mismo tipo.',
            ['referencia' => $referencia],
            409
        );
    }

    sm_responder_json(
        false,
        'No fue posible procesar el catálogo de herramientas y refacciones.',
        ['referencia' => $referencia],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'RMC-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][RECURSOS MANTENIMIENTO] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar el recurso.',
        ['referencia' => $referencia],
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function rmc_endpoint_listar(PDO $conexion): void
{
    $recursos = rsm_listar_recursos($conexion);

    sm_responder_json(
        true,
        'Herramientas y refacciones cargadas correctamente.',
        [
            'recursos' => $recursos,
            'resumen' => rsm_resumen($recursos),
            'sugerencias' => rmc_listar_sugerencias($conexion),
            'resumen_sugerencias' => rmc_resumen_sugerencias($conexion),
            'codigos_siguientes' => [
                'HERRAMIENTA' => rsm_previsualizar_siguiente_codigo($conexion, RSM_TIPO_HERRAMIENTA),
                'REFACCION' => rsm_previsualizar_siguiente_codigo($conexion, RSM_TIPO_REFACCION),
            ],
            'recursos_sin_codigo' => rmc_contar_recursos_sin_codigo($conexion),
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function rmc_endpoint_detalle(PDO $conexion): void
{
    $id = rsm_entero_positivo($_GET['id'] ?? null, 'recurso');
    $recurso = rsm_obtener_recurso($conexion, $id, false);

    if (!$recurso) {
        sm_responder_json(false, 'El recurso solicitado no existe.', [], 404);
    }

    sm_responder_json(
        true,
        'Recurso cargado correctamente.',
        ['recurso' => $recurso]
    );
}

function rmc_endpoint_buscar_activos(PDO $conexion): void
{
    $tipoEntrada = rsm_texto($_GET['tipo_recurso'] ?? '');
    $tipo = $tipoEntrada === '' ? null : $tipoEntrada;
    $busqueda = rsm_texto($_GET['q'] ?? '');
    $limiteEntrada = $_GET['limite'] ?? 30;
    $limite = filter_var(
        $limiteEntrada,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 50]]
    );

    if ($limite === false) {
        $limite = 30;
    }

    $recursos = rsm_buscar_recursos_activos(
        $conexion,
        $tipo,
        $busqueda,
        (int) $limite
    );

    sm_responder_json(
        true,
        'Resultados cargados correctamente.',
        ['recursos' => $recursos]
    );
}

function rmc_endpoint_guardar(PDO $conexion, int $adminId): void
{
    $idEntrada = rsm_texto($_POST['recurso_id'] ?? '');
    $id = $idEntrada === '' ? 0 : rsm_entero_positivo($idEntrada, 'recurso');
    $tipo = rsm_validar_tipo($_POST['tipo_recurso'] ?? '');
    $nombre = rsm_validar_nombre($_POST['nombre'] ?? '');
    $descripcion = rsm_validar_descripcion($_POST['descripcion'] ?? '');

    $conexion->beginTransaction();

    if ($id === 0) {
        $codigo = rsm_generar_codigo_automatico($conexion, $tipo);
        rmc_validar_duplicados($conexion, $tipo, $nombre, $codigo, 0);

        $stmt = $conexion->prepare(
            "INSERT INTO recursos_mantenimiento
            (
                tipo_recurso, nombre, codigo, descripcion,
                creado_por_admin_id, modificado_por_admin_id,
                activo, fecha_registro, fecha_actualizacion
            )
            VALUES
            (
                :tipo_recurso, :nombre, :codigo, :descripcion,
                :admin_creador, :admin_modificador,
                1, NOW(), NOW()
            )"
        );
        $stmt->bindValue(':tipo_recurso', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        rsm_bind_nullable($stmt, ':codigo', $codigo);
        rsm_bind_nullable($stmt, ':descripcion', $descripcion);
        $stmt->bindValue(':admin_creador', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':admin_modificador', $adminId, PDO::PARAM_INT);
        $stmt->execute();

        $id = (int) $conexion->lastInsertId();
        $nuevo = rsm_obtener_recurso($conexion, $id, false);

        if (!$nuevo) {
            throw new RuntimeException('No fue posible recuperar el recurso recién creado.');
        }

        rsm_registrar_auditoria(
            $conexion,
            $adminId,
            'INSERT',
            $id,
            'Registro inicial de ' . rmc_tipo_en_minusculas($tipo) . '.',
            null,
            rsm_datos_auditoria($nuevo)
        );
        rsm_registrar_movimiento(
            $conexion,
            $adminId,
            'CREAR_RECURSO_MANTENIMIENTO',
            'Se registró la ' . rmc_tipo_en_minusculas($tipo) . ' "' . $nombre . '".',
            $id
        );

        $conexion->commit();

        sm_responder_json(
            true,
            ucfirst(rmc_tipo_en_minusculas($tipo)) . ' registrada correctamente.',
            ['id' => $id, 'recurso' => $nuevo]
        );
    }

    $anterior = rsm_obtener_recurso($conexion, $id, true);

    if (!$anterior) {
        rsm_responder_cancelando(
            $conexion,
            'El recurso que intentas editar ya no existe.',
            404
        );
    }

    $tipoCambio = (string) $anterior['tipo_recurso'] !== $tipo;

    if (
        $tipoCambio
        && (int) $anterior['total_usos'] > 0
    ) {
        rsm_responder_cancelando(
            $conexion,
            'No puedes cambiar el tipo porque este recurso ya está vinculado con recomendaciones, rutinas o mantenimientos. Puedes corregir su nombre y descripción; el código se administra automáticamente.',
            409,
            ['campo' => 'tipo_recurso', 'total_usos' => (int) $anterior['total_usos']]
        );
    }

    $codigo = trim((string) ($anterior['codigo'] ?? ''));
    if ($tipoCambio || $codigo === '') {
        $codigo = rsm_generar_codigo_automatico($conexion, $tipo);
    }

    rmc_validar_duplicados($conexion, $tipo, $nombre, $codigo, $id);

    $sinCambios =
        (string) $anterior['tipo_recurso'] === $tipo
        && (string) $anterior['nombre'] === $nombre
        && rmc_nullable_igual($anterior['codigo'] ?? null, $codigo)
        && rmc_nullable_igual($anterior['descripcion'] ?? null, $descripcion);

    if ($sinCambios) {
        $conexion->rollBack();
        sm_responder_json(
            true,
            'No había cambios pendientes por guardar.',
            ['sin_cambios' => true, 'id' => $id]
        );
    }

    $stmt = $conexion->prepare(
        "UPDATE recursos_mantenimiento
         SET tipo_recurso = :tipo_recurso,
             nombre = :nombre,
             codigo = :codigo,
             descripcion = :descripcion,
             modificado_por_admin_id = :admin_id,
             fecha_actualizacion = NOW()
         WHERE id = :id"
    );
    $stmt->bindValue(':tipo_recurso', $tipo, PDO::PARAM_STR);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    rsm_bind_nullable($stmt, ':codigo', $codigo);
    rsm_bind_nullable($stmt, ':descripcion', $descripcion);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $nuevo = rsm_obtener_recurso($conexion, $id, false);

    if (!$nuevo) {
        throw new RuntimeException('No fue posible recuperar el recurso actualizado.');
    }

    rsm_registrar_auditoria(
        $conexion,
        $adminId,
        'UPDATE',
        $id,
        'Actualización de los datos del recurso.',
        rsm_datos_auditoria($anterior),
        rsm_datos_auditoria($nuevo)
    );
    rsm_registrar_movimiento(
        $conexion,
        $adminId,
        'EDITAR_RECURSO_MANTENIMIENTO',
        'Se actualizó la ' . rmc_tipo_en_minusculas($tipo) . ' "' . $nombre . '".',
        $id
    );

    $conexion->commit();

    sm_responder_json(
        true,
        ucfirst(rmc_tipo_en_minusculas($tipo)) . ' actualizada correctamente.',
        ['id' => $id, 'recurso' => $nuevo]
    );
}

function rmc_endpoint_cambiar_estado(PDO $conexion, int $adminId): void
{
    $id = rsm_entero_positivo($_POST['id'] ?? null, 'recurso');
    $activo = rsm_validar_estado($_POST['activo'] ?? null);

    $conexion->beginTransaction();
    $anterior = rsm_obtener_recurso($conexion, $id, true);

    if (!$anterior) {
        rsm_responder_cancelando(
            $conexion,
            'El recurso que intentas actualizar ya no existe.',
            404
        );
    }

    if ((int) $anterior['activo'] === $activo) {
        $conexion->rollBack();
        sm_responder_json(
            true,
            $activo === 1
                ? 'El recurso ya estaba activo.'
                : 'El recurso ya estaba inactivo.',
            ['sin_cambios' => true]
        );
    }

    $stmt = $conexion->prepare(
        "UPDATE recursos_mantenimiento
         SET activo = :activo,
             modificado_por_admin_id = :admin_id,
             fecha_actualizacion = NOW()
         WHERE id = :id"
    );
    $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $nuevo = rsm_obtener_recurso($conexion, $id, false);

    if (!$nuevo) {
        throw new RuntimeException('No fue posible recuperar el estado actualizado del recurso.');
    }

    $accionAuditoria = $activo === 1 ? 'REACTIVACION' : 'DELETE_LOGICO';
    $accionMovimiento = $activo === 1
        ? 'REACTIVAR_RECURSO_MANTENIMIENTO'
        : 'DESACTIVAR_RECURSO_MANTENIMIENTO';
    $verbo = $activo === 1 ? 'reactivó' : 'desactivó';

    rsm_registrar_auditoria(
        $conexion,
        $adminId,
        $accionAuditoria,
        $id,
        $activo === 1
            ? 'Reactivación del recurso para nuevas selecciones.'
            : 'Desactivación lógica; se conservaron todas sus relaciones históricas.',
        rsm_datos_auditoria($anterior),
        rsm_datos_auditoria($nuevo)
    );
    rsm_registrar_movimiento(
        $conexion,
        $adminId,
        $accionMovimiento,
        'Se ' . $verbo . ' la ' . rmc_tipo_en_minusculas((string) $anterior['tipo_recurso'])
        . ' "' . (string) $anterior['nombre'] . '". Sus relaciones históricas se conservaron.',
        $id
    );

    $conexion->commit();

    sm_responder_json(
        true,
        $activo === 1
            ? 'Recurso reactivado correctamente.'
            : 'Recurso desactivado correctamente. Su historial permanece intacto.',
        [
            'id' => $id,
            'activo' => $activo,
            'total_usos' => (int) $nuevo['total_usos'],
        ]
    );
}

function rmc_contar_recursos_sin_codigo(PDO $conexion): int
{
    return (int) $conexion->query(
        "SELECT COUNT(*)
         FROM recursos_mantenimiento
         WHERE codigo IS NULL OR TRIM(codigo) = ''"
    )->fetchColumn();
}

function rmc_endpoint_completar_codigos(PDO $conexion, int $adminId): void
{
    $conexion->beginTransaction();
    $resultado = rsm_completar_codigos_faltantes($conexion, $adminId);

    if ((int) $resultado['actualizados'] > 0) {
        rsm_registrar_movimiento(
            $conexion,
            $adminId,
            'COMPLETAR_CODIGOS_RECURSOS',
            'Se asignaron códigos automáticos a ' . (int) $resultado['actualizados']
                . ' recurso(s): ' . (int) $resultado['herramientas']
                . ' herramienta(s) y ' . (int) $resultado['refacciones'] . ' refacción(es).',
            null
        );
    }

    $conexion->commit();

    sm_responder_json(
        true,
        (int) $resultado['actualizados'] > 0
            ? 'Los códigos faltantes se generaron correctamente.'
            : 'Todos los recursos ya tenían código.',
        ['resultado' => $resultado]
    );
}

/* =========================================================================
   VALIDACIONES ESPECÍFICAS DEL ENDPOINT
   ========================================================================= */

function rmc_validar_duplicados(
    PDO $conexion,
    string $tipo,
    string $nombre,
    ?string $codigo,
    int $excluirId
): void {
    if (rsm_nombre_existe($conexion, $tipo, $nombre, $excluirId)) {
        rsm_responder_cancelando(
            $conexion,
            'Ya existe ' . rmc_articulo_tipo($tipo) . ' con ese nombre.',
            409,
            ['campo' => 'nombre']
        );
    }

    if (rsm_codigo_existe($conexion, $tipo, $codigo, $excluirId)) {
        rsm_responder_cancelando(
            $conexion,
            'Ya existe ' . rmc_articulo_tipo($tipo) . ' con ese código.',
            409,
            ['campo' => 'codigo']
        );
    }
}

function rmc_tipo_en_minusculas(string $tipo): string
{
    return $tipo === RSM_TIPO_REFACCION ? 'refacción' : 'herramienta';
}

function rmc_articulo_tipo(string $tipo): string
{
    return $tipo === RSM_TIPO_REFACCION ? 'una refacción' : 'una herramienta';
}

function rmc_nullable_igual($a, $b): bool
{
    $a = $a === null || trim((string) $a) === '' ? null : (string) $a;
    $b = $b === null || trim((string) $b) === '' ? null : (string) $b;

    return $a === $b;
}

/* =========================================================================
   SUGERENCIAS ENVIADAS DESDE EL CIERRE TÉCNICO
   ========================================================================= */

function rmc_listar_sugerencias(PDO $conexion): array
{
    $stmt = $conexion->query(
        "SELECT
            sr.id,
            sr.tipo_recurso,
            sr.nombre_sugerido,
            sr.estado,
            sr.recurso_creado_id,
            sr.observaciones_admin,
            sr.fecha_registro,
            sr.fecha_atencion,
            cru.id AS cierre_recurso_utilizado_id,
            cm.id AS cierre_id,
            cm.solicitud_id,
            s.folio,
            s.tipo_solicitud,
            s.equipo_id,
            e.codigo_equipo,
            e.nombre_equipo,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico,
            TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno)) AS atendida_por,
            r.nombre AS recurso_oficial,
            CASE
                WHEN EXISTS (
                    SELECT 1 FROM recursos_mantenimiento rx
                    WHERE rx.tipo_recurso = sr.tipo_recurso
                      AND rx.nombre = sr.nombre_sugerido
                ) THEN 1 ELSE 0
            END AS coincide_catalogo
         FROM sugerencias_recursos sr
         INNER JOIN cierre_recursos_utilizados cru
                 ON cru.id = sr.cierre_recurso_utilizado_id
         INNER JOIN cierres_mantenimiento cm ON cm.id = cru.cierre_id
         INNER JOIN solicitudes s ON s.id = cm.solicitud_id
         INNER JOIN equipos e ON e.id = s.equipo_id
         INNER JOIN tecnicos t ON t.id = sr.tecnico_id
         LEFT JOIN administradores ad ON ad.id = sr.atendida_por_admin_id
         LEFT JOIN recursos_mantenimiento r ON r.id = sr.recurso_creado_id
         ORDER BY
            CASE sr.estado WHEN 'PENDIENTE' THEN 0 ELSE 1 END,
            sr.fecha_registro DESC,
            sr.id DESC
         LIMIT 500"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function rmc_resumen_sugerencias(PDO $conexion): array
{
    $stmt = $conexion->query(
        "SELECT
            COUNT(*) AS total,
            SUM(estado = 'PENDIENTE') AS pendientes,
            SUM(estado = 'APROBADA') AS aprobadas,
            SUM(estado = 'RECHAZADA') AS rechazadas,
            SUM(estado = 'ATENDIDA') AS atendidas
         FROM sugerencias_recursos"
    );
    $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total' => (int) ($fila['total'] ?? 0),
        'pendientes' => (int) ($fila['pendientes'] ?? 0),
        'aprobadas' => (int) ($fila['aprobadas'] ?? 0),
        'rechazadas' => (int) ($fila['rechazadas'] ?? 0),
        'atendidas' => (int) ($fila['atendidas'] ?? 0),
    ];
}

function rmc_endpoint_atender_sugerencia(PDO $conexion, int $adminId): void
{
    $sugerenciaId = rsm_entero_positivo($_POST['sugerencia_id'] ?? null, 'sugerencia');
    $decision = rsm_mayusculas(rsm_texto($_POST['decision'] ?? ''));
    $observaciones = rsm_texto($_POST['observaciones'] ?? '');

    if (!in_array($decision, ['APROBAR', 'APROBAR_Y_RECOMENDAR', 'RECHAZAR'], true)) {
        sm_responder_json(false, 'Selecciona una decisión válida para la sugerencia.', [], 422);
    }

    if (function_exists('mb_strlen')) {
        if (mb_strlen($observaciones, 'UTF-8') > 500) {
            sm_responder_json(false, 'Las observaciones no pueden superar 500 caracteres.', [], 422);
        }
    } elseif (strlen($observaciones) > 500) {
        sm_responder_json(false, 'Las observaciones no pueden superar 500 caracteres.', [], 422);
    }

    if ($decision === 'RECHAZAR' && strlen(trim($observaciones)) < 5) {
        sm_responder_json(false, 'Escribe una razón breve para rechazar la sugerencia.', [], 422);
    }

    $conexion->beginTransaction();

    $stmt = $conexion->prepare(
        "SELECT
            sr.*,
            cru.cierre_id,
            cru.recurso_id AS cierre_recurso_id,
            cru.nombre_no_catalogado,
            cm.solicitud_id,
            s.equipo_id,
            s.tipo_solicitud,
            s.folio,
            e.nombre_equipo,
            TRIM(CONCAT_WS(' ', t.nombre, t.apellido_paterno, t.apellido_materno)) AS tecnico
         FROM sugerencias_recursos sr
         INNER JOIN cierre_recursos_utilizados cru
                 ON cru.id = sr.cierre_recurso_utilizado_id
         INNER JOIN cierres_mantenimiento cm ON cm.id = cru.cierre_id
         INNER JOIN solicitudes s ON s.id = cm.solicitud_id
         INNER JOIN equipos e ON e.id = s.equipo_id
         INNER JOIN tecnicos t ON t.id = sr.tecnico_id
         WHERE sr.id = :sugerencia_id
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bindValue(':sugerencia_id', $sugerenciaId, PDO::PARAM_INT);
    $stmt->execute();
    $sugerencia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($sugerencia)) {
        rsm_responder_cancelando($conexion, 'La sugerencia ya no existe.', 404);
    }

    if ((string) ($sugerencia['estado'] ?? '') !== 'PENDIENTE') {
        rsm_responder_cancelando($conexion, 'La sugerencia ya fue atendida por otro administrador.', 409);
    }

    if ($decision === 'APROBAR_Y_RECOMENDAR' && (string) $sugerencia['tipo_solicitud'] === 'RUTINARIO') {
        rsm_responder_cancelando(
            $conexion,
            'En mantenimientos rutinarios la recomendación permanente se modifica desde la plantilla de la rutina. Puedes agregar el recurso al catálogo y después editar la plantilla.',
            422
        );
    }

    if ($decision === 'RECHAZAR') {
        $stmtRechazar = $conexion->prepare(
            "UPDATE sugerencias_recursos
             SET estado = 'RECHAZADA',
                 atendida_por_admin_id = :admin_id,
                 observaciones_admin = :observaciones,
                 fecha_atencion = NOW()
             WHERE id = :sugerencia_id"
        );
        $stmtRechazar->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmtRechazar->bindValue(':observaciones', trim($observaciones), PDO::PARAM_STR);
        $stmtRechazar->bindValue(':sugerencia_id', $sugerenciaId, PDO::PARAM_INT);
        $stmtRechazar->execute();

        rsm_registrar_movimiento(
            $conexion,
            $adminId,
            'RECHAZAR_SUGERENCIA_RECURSO',
            'Se rechazó la sugerencia "' . (string) $sugerencia['nombre_sugerido'] . '" del mantenimiento ' . (string) $sugerencia['folio'] . '.',
            null
        );
        $conexion->commit();
        sm_responder_json(true, 'Sugerencia rechazada correctamente.', ['sugerencia_id' => $sugerenciaId]);
    }

    $tipo = (string) $sugerencia['tipo_recurso'];
    $nombre = trim((string) $sugerencia['nombre_sugerido']);
    $recurso = rsm_buscar_recurso_por_nombre_tipo($conexion, $tipo, $nombre);
    $recursoId = 0;
    $creado = false;

    if (is_array($recurso)) {
        $recursoId = (int) $recurso['id'];
        $codigoExistente = trim((string) ($recurso['codigo'] ?? ''));
        $codigoAsignado = $codigoExistente !== ''
            ? $codigoExistente
            : rsm_generar_codigo_automatico($conexion, $tipo);

        if ((int) ($recurso['activo'] ?? 0) !== 1 || $codigoExistente === '') {
            $stmtReactivar = $conexion->prepare(
                "UPDATE recursos_mantenimiento
                 SET activo = 1,
                     codigo = :codigo,
                     modificado_por_admin_id = :admin_id,
                     fecha_actualizacion = NOW()
                 WHERE id = :recurso_id"
            );
            $stmtReactivar->bindValue(':codigo', $codigoAsignado, PDO::PARAM_STR);
            $stmtReactivar->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
            $stmtReactivar->bindValue(':recurso_id', $recursoId, PDO::PARAM_INT);
            $stmtReactivar->execute();
        }
    } else {
        $descripcion = 'Sugerida por ' . (string) $sugerencia['tecnico']
            . ' al finalizar ' . (string) $sugerencia['folio']
            . ' en ' . (string) $sugerencia['nombre_equipo'] . '.';

        $codigoNuevo = rsm_generar_codigo_automatico($conexion, $tipo);
        $stmtCrear = $conexion->prepare(
            "INSERT INTO recursos_mantenimiento (
                tipo_recurso, nombre, codigo, descripcion,
                creado_por_admin_id, modificado_por_admin_id,
                activo, fecha_registro, fecha_actualizacion
             ) VALUES (
                :tipo, :nombre, :codigo, :descripcion,
                :admin_creador, :admin_modificador,
                1, NOW(), NOW()
             )"
        );
        $stmtCrear->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmtCrear->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmtCrear->bindValue(':codigo', $codigoNuevo, PDO::PARAM_STR);
        $stmtCrear->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmtCrear->bindValue(':admin_creador', $adminId, PDO::PARAM_INT);
        $stmtCrear->bindValue(':admin_modificador', $adminId, PDO::PARAM_INT);
        $stmtCrear->execute();
        $recursoId = (int) $conexion->lastInsertId();
        $creado = true;
    }

    rmc_vincular_sugerencia_con_catalogo($conexion, $sugerencia, $recursoId);
    rmc_convertir_memoria_libre_a_catalogo($conexion, $sugerencia, $recursoId, $adminId);

    if ($decision === 'APROBAR_Y_RECOMENDAR') {
        rmc_agregar_recomendacion_desde_sugerencia($conexion, $sugerencia, $recursoId, $adminId);
    }

    $observacionFinal = trim($observaciones);
    if ($observacionFinal === '') {
        $observacionFinal = $decision === 'APROBAR_Y_RECOMENDAR'
            ? 'Aprobada y agregada a la recomendación del equipo.'
            : 'Aprobada e incorporada al catálogo oficial.';
    }

    $stmtAtender = $conexion->prepare(
        "UPDATE sugerencias_recursos
         SET estado = 'APROBADA',
             recurso_creado_id = :recurso_id,
             atendida_por_admin_id = :admin_id,
             observaciones_admin = :observaciones,
             fecha_atencion = NOW()
         WHERE id = :sugerencia_id"
    );
    $stmtAtender->bindValue(':recurso_id', $recursoId, PDO::PARAM_INT);
    $stmtAtender->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmtAtender->bindValue(':observaciones', $observacionFinal, PDO::PARAM_STR);
    $stmtAtender->bindValue(':sugerencia_id', $sugerenciaId, PDO::PARAM_INT);
    $stmtAtender->execute();

    $recursoActual = rsm_obtener_recurso($conexion, $recursoId, false);
    if ($creado && is_array($recursoActual)) {
        rsm_registrar_auditoria(
            $conexion,
            $adminId,
            'INSERT',
            $recursoId,
            'Alta desde sugerencia técnica del mantenimiento ' . (string) $sugerencia['folio'] . '.',
            null,
            rsm_datos_auditoria($recursoActual)
        );
    }

    rsm_registrar_movimiento(
        $conexion,
        $adminId,
        $decision === 'APROBAR_Y_RECOMENDAR'
            ? 'APROBAR_Y_RECOMENDAR_RECURSO'
            : 'APROBAR_SUGERENCIA_RECURSO',
        'Se aprobó la sugerencia "' . $nombre . '" del mantenimiento ' . (string) $sugerencia['folio'] . '.',
        $recursoId
    );

    $conexion->commit();
    sm_responder_json(true, $decision === 'APROBAR_Y_RECOMENDAR'
        ? 'Sugerencia aprobada y agregada a la recomendación del equipo.'
        : 'Sugerencia aprobada e incorporada al catálogo.', [
        'sugerencia_id' => $sugerenciaId,
        'recurso_id' => $recursoId,
        'recurso_creado' => $creado ? 1 : 0,
    ]);
}

function rmc_vincular_sugerencia_con_catalogo(PDO $conexion, array $sugerencia, int $recursoId): void
{
    $cierreId = (int) $sugerencia['cierre_id'];
    $cierreRecursoId = (int) $sugerencia['cierre_recurso_utilizado_id'];

    $stmtDuplicado = $conexion->prepare(
        "SELECT id
         FROM cierre_recursos_utilizados
         WHERE cierre_id = :cierre_id
           AND recurso_id = :recurso_id
           AND id <> :cierre_recurso_id
         LIMIT 1
         FOR UPDATE"
    );
    $stmtDuplicado->bindValue(':cierre_id', $cierreId, PDO::PARAM_INT);
    $stmtDuplicado->bindValue(':recurso_id', $recursoId, PDO::PARAM_INT);
    $stmtDuplicado->bindValue(':cierre_recurso_id', $cierreRecursoId, PDO::PARAM_INT);
    $stmtDuplicado->execute();
    $duplicadoId = (int) ($stmtDuplicado->fetchColumn() ?: 0);

    if ($duplicadoId > 0) {
        $stmtEliminar = $conexion->prepare('DELETE FROM cierre_recursos_utilizados WHERE id = :id');
        $stmtEliminar->bindValue(':id', $duplicadoId, PDO::PARAM_INT);
        $stmtEliminar->execute();
    }

    $stmtActualizar = $conexion->prepare(
        "UPDATE cierre_recursos_utilizados
         SET recurso_id = :recurso_id,
             nombre_no_catalogado = NULL,
             fecha_actualizacion = NOW()
         WHERE id = :cierre_recurso_id"
    );
    $stmtActualizar->bindValue(':recurso_id', $recursoId, PDO::PARAM_INT);
    $stmtActualizar->bindValue(':cierre_recurso_id', $cierreRecursoId, PDO::PARAM_INT);
    $stmtActualizar->execute();
}

function rmc_convertir_memoria_libre_a_catalogo(
    PDO $conexion,
    array $sugerencia,
    int $recursoId,
    int $adminId
): void {
    $equipoId = (int) $sugerencia['equipo_id'];
    $tipoSolicitud = (string) $sugerencia['tipo_solicitud'];
    $tipoRecurso = (string) $sugerencia['tipo_recurso'];
    $nombre = (string) $sugerencia['nombre_sugerido'];

    /* Los nombres libres solo alimentan automáticamente la memoria urgente. */
    if ($tipoSolicitud !== 'CORRECTIVO_URGENTE') {
        return;
    }

    $stmtExiste = $conexion->prepare(
        "SELECT COUNT(*)
         FROM recomendaciones_recursos
         WHERE equipo_id = :equipo_id
           AND tipo_solicitud = 'CORRECTIVO_URGENTE'
           AND tipo_recurso = :tipo_recurso
           AND nombre_no_catalogado = :nombre"
    );
    $stmtExiste->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
    $stmtExiste->bindValue(':tipo_recurso', $tipoRecurso, PDO::PARAM_STR);
    $stmtExiste->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmtExiste->execute();

    if ((int) $stmtExiste->fetchColumn() < 1) {
        return;
    }

    $stmtEliminarDuplicado = $conexion->prepare(
        "DELETE FROM recomendaciones_recursos
         WHERE equipo_id = :equipo_id
           AND tipo_solicitud = 'CORRECTIVO_URGENTE'
           AND recurso_id = :recurso_id"
    );
    $stmtEliminarDuplicado->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
    $stmtEliminarDuplicado->bindValue(':recurso_id', $recursoId, PDO::PARAM_INT);
    $stmtEliminarDuplicado->execute();

    $stmtActualizar = $conexion->prepare(
        "UPDATE recomendaciones_recursos
         SET recurso_id = :recurso_id,
             nombre_no_catalogado = NULL,
             actualizado_por_admin_id = :admin_id,
             fecha_actualizacion = NOW()
         WHERE equipo_id = :equipo_id
           AND tipo_solicitud = 'CORRECTIVO_URGENTE'
           AND tipo_recurso = :tipo_recurso
           AND nombre_no_catalogado = :nombre"
    );
    $stmtActualizar->bindValue(':recurso_id', $recursoId, PDO::PARAM_INT);
    $stmtActualizar->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmtActualizar->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
    $stmtActualizar->bindValue(':tipo_recurso', $tipoRecurso, PDO::PARAM_STR);
    $stmtActualizar->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmtActualizar->execute();
}

function rmc_agregar_recomendacion_desde_sugerencia(
    PDO $conexion,
    array $sugerencia,
    int $recursoId,
    int $adminId
): void {
    $stmt = $conexion->prepare(
        "INSERT INTO recomendaciones_recursos (
            equipo_id, tipo_solicitud, tipo_recurso, recurso_id,
            nombre_no_catalogado, origen_ultima_actualizacion,
            solicitud_origen_id, actualizado_por_admin_id,
            actualizado_por_tecnico_id, fecha_registro, fecha_actualizacion
         ) VALUES (
            :equipo_id, :tipo_solicitud, :tipo_recurso, :recurso_id,
            NULL, 'ADMIN', :solicitud_id, :admin_id,
            NULL, NOW(), NOW()
         )
         ON DUPLICATE KEY UPDATE
            origen_ultima_actualizacion = 'ADMIN',
            solicitud_origen_id = VALUES(solicitud_origen_id),
            actualizado_por_admin_id = VALUES(actualizado_por_admin_id),
            actualizado_por_tecnico_id = NULL,
            fecha_actualizacion = NOW()"
    );
    $stmt->bindValue(':equipo_id', (int) $sugerencia['equipo_id'], PDO::PARAM_INT);
    $stmt->bindValue(':tipo_solicitud', (string) $sugerencia['tipo_solicitud'], PDO::PARAM_STR);
    $stmt->bindValue(':tipo_recurso', (string) $sugerencia['tipo_recurso'], PDO::PARAM_STR);
    $stmt->bindValue(':recurso_id', $recursoId, PDO::PARAM_INT);
    $stmt->bindValue(':solicitud_id', (int) $sugerencia['solicitud_id'], PDO::PARAM_INT);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->execute();
}
