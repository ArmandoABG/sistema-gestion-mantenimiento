<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Procesos - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| Catálogo dependiente de Áreas.
| Permite alta, edición, desactivación y reactivación con auditoría.
| No elimina registros y protege relaciones históricas y operativas.
| Compatible con PHP 7.4 o superior.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_sesion(['ADMIN'], true);

if (!isset($conexion) || !($conexion instanceof PDO)) {
    sm_responder_json(false, 'No fue posible conectar con la base de datos.', [], 503);
}

try {
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[PROCESOS][PDO CONFIG] ' . $e->getMessage());
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = strtoupper(proceso_texto(
    $metodo === 'GET'
        ? ($_GET['accion'] ?? 'INICIAL')
        : ($_POST['accion'] ?? '')
));

try {
    proceso_validar_admin_activo($conexion, proceso_admin_id());

    if ($metodo === 'GET') {
        sm_requerir_metodo('GET');

        if ($accion === 'INICIAL' || $accion === 'LISTAR') {
            proceso_endpoint_listar($conexion);
        }

        if ($accion === 'DETALLE') {
            proceso_endpoint_detalle($conexion);
        }

        sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
    }

    sm_requerir_metodo('POST');
    sm_validar_csrf();

    if ($accion === 'GUARDAR') {
        proceso_endpoint_guardar($conexion);
    }

    if ($accion === 'CAMBIAR_ESTADO') {
        proceso_endpoint_cambiar_estado($conexion);
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'PROC-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][PROCESOS][PDO] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    if ((string) $e->getCode() === '23000') {
        sm_responder_json(
            false,
            'Ya existe un proceso con ese nombre dentro del área seleccionada o hay datos relacionados que impiden la operación.',
            ['campo' => 'nombre', 'referencia' => $referencia],
            409
        );
    }

    sm_responder_json(
        false,
        'No fue posible procesar el catálogo de procesos.',
        ['referencia' => $referencia],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'PROC-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][PROCESOS] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar el proceso.',
        ['referencia' => $referencia],
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function proceso_endpoint_listar(PDO $conexion): void
{
    $sql = "SELECT
                p.id,
                p.area_id,
                p.nombre,
                p.descripcion,
                p.activo,
                p.fecha_registro,
                DATE_FORMAT(p.fecha_registro, '%d/%m/%Y') AS fecha_registro_texto,
                COALESCE(a.nombre, 'Área no disponible') AS area,
                COALESCE(a.activo, 0) AS area_activa,
                COALESCE(a.departamento_id, 0) AS departamento_id,
                COALESCE(d.nombre, 'Departamento no disponible') AS departamento,
                COALESCE(d.activo, 0) AS departamento_activo,
                COALESCE((
                    SELECT COUNT(*)
                    FROM equipos e
                    WHERE e.proceso_id = p.id
                      AND e.activo = 1
                ), 0) AS equipos_activos,
                COALESCE((
                    SELECT COUNT(*)
                    FROM solicitudes s
                    WHERE s.proceso_id = p.id
                      AND s.activo = 1
                      AND s.estado IN (
                          'PENDIENTE','APROBADO','AGENDADO','EN_PROCESO',
                          'PAUSADO','ATRASADO'
                      )
                ), 0) AS solicitudes_abiertas,
                COALESCE((
                    SELECT COUNT(*)
                    FROM rutinas_mantenimiento r
                    WHERE r.proceso_id = p.id
                      AND r.activo = 1
                ), 0) AS rutinas_activas
            FROM procesos p
            LEFT JOIN areas a ON a.id = p.area_id
            LEFT JOIN departamentos d ON d.id = a.departamento_id
            ORDER BY p.activo DESC, departamento ASC, area ASC, p.nombre ASC, p.id ASC";

    $procesos = $conexion->query($sql)->fetchAll();
    $activos = 0;
    $inactivos = 0;
    $enUso = 0;

    foreach ($procesos as &$proceso) {
        proceso_convertir_fila($proceso);
        $proceso['total_relaciones_activas'] =
            $proceso['equipos_activos']
            + $proceso['solicitudes_abiertas']
            + $proceso['rutinas_activas'];

        if ($proceso['activo'] === 1) {
            $activos++;
        } else {
            $inactivos++;
        }

        if ($proceso['total_relaciones_activas'] > 0) {
            $enUso++;
        }
    }
    unset($proceso);

    sm_responder_json(
        true,
        'Procesos cargados correctamente.',
        [
            'procesos' => $procesos,
            'areas' => proceso_listar_areas($conexion),
            'resumen' => [
                'total' => count($procesos),
                'activos' => $activos,
                'inactivos' => $inactivos,
                'en_uso' => $enUso,
            ],
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function proceso_endpoint_detalle(PDO $conexion): void
{
    $id = proceso_entero_positivo($_GET['id'] ?? null, 'proceso');
    $proceso = proceso_obtener_proceso($conexion, $id, false);

    if (!$proceso) {
        sm_responder_json(false, 'El proceso solicitado no existe.', [], 404);
    }

    $relaciones = proceso_obtener_relaciones_totales($conexion, $id);
    $proceso['relaciones_totales'] = $relaciones;
    $proceso['total_relaciones_historicas'] = array_sum($relaciones);
    $proceso['puede_cambiar_area'] =
        $proceso['total_relaciones_historicas'] === 0 ? 1 : 0;

    sm_responder_json(
        true,
        'Proceso cargado correctamente.',
        ['proceso' => $proceso]
    );
}

function proceso_endpoint_guardar(PDO $conexion): void
{
    $adminId = proceso_admin_id();
    $idEntrada = proceso_texto($_POST['proceso_id'] ?? '');
    $id = $idEntrada === '' ? 0 : proceso_entero_positivo($idEntrada, 'proceso');
    $areaId = proceso_entero_positivo($_POST['area_id'] ?? null, 'área');
    $nombre = proceso_validar_nombre($_POST['nombre'] ?? '');
    $descripcion = proceso_validar_descripcion($_POST['descripcion'] ?? '');

    $conexion->beginTransaction();

    if ($id === 0) {
        $area = proceso_obtener_area($conexion, $areaId, true);
        if (!$area || (int) $area['activo'] !== 1 || (int) $area['departamento_activo'] !== 1) {
            proceso_responder_cancelando(
                $conexion,
                'El área seleccionada no existe, está inactiva o pertenece a un departamento inactivo.',
                422,
                ['campo' => 'area_id']
            );
        }

        if (proceso_nombre_existe($conexion, $nombre, $areaId, 0)) {
            proceso_responder_cancelando(
                $conexion,
                'Ya existe un proceso con ese nombre dentro del área seleccionada.',
                409,
                ['campo' => 'nombre']
            );
        }

        $stmt = $conexion->prepare(
            "INSERT INTO procesos
             (area_id, nombre, descripcion, activo, fecha_registro)
             VALUES (:area_id, :nombre, :descripcion, 1, NOW())"
        );
        $stmt->bindValue(':area_id', $areaId, PDO::PARAM_INT);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        proceso_bind_nullable($stmt, ':descripcion', $descripcion);
        $stmt->execute();

        $id = (int) $conexion->lastInsertId();
        $nuevo = [
            'id' => $id,
            'area_id' => $areaId,
            'area' => (string) $area['nombre'],
            'departamento_id' => (int) $area['departamento_id'],
            'departamento' => (string) $area['departamento'],
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'activo' => 1,
        ];

        proceso_registrar_auditoria(
            $conexion,
            $adminId,
            'INSERT',
            $id,
            'Registro inicial del proceso.',
            null,
            $nuevo
        );
        proceso_registrar_movimiento(
            $conexion,
            $adminId,
            'CREAR_PROCESO',
            'Se registró el proceso "' . $nombre . '" en el área "'
                . (string) $area['nombre'] . '" del departamento "'
                . (string) $area['departamento'] . '".',
            $id
        );

        $conexion->commit();
        sm_responder_json(true, 'Proceso registrado correctamente.', ['id' => $id]);
    }

    $anterior = proceso_obtener_proceso($conexion, $id, true);
    if (!$anterior) {
        proceso_responder_cancelando(
            $conexion,
            'El proceso que intentas editar ya no existe.',
            404
        );
    }

    $areaAnteriorId = (int) $anterior['area_id'];
    $cambiaArea = $areaAnteriorId !== $areaId;
    $areaDestino = proceso_obtener_area($conexion, $areaId, true);

    if (!$areaDestino) {
        proceso_responder_cancelando(
            $conexion,
            'El área seleccionada ya no existe.',
            422,
            ['campo' => 'area_id']
        );
    }

    if ($cambiaArea) {
        if ((int) $areaDestino['activo'] !== 1 || (int) $areaDestino['departamento_activo'] !== 1) {
            proceso_responder_cancelando(
                $conexion,
                'La nueva área está inactiva o pertenece a un departamento inactivo.',
                422,
                ['campo' => 'area_id']
            );
        }

        $relaciones = proceso_obtener_relaciones_totales($conexion, $id);
        $totalRelaciones = array_sum($relaciones);
        if ($totalRelaciones > 0) {
            proceso_responder_cancelando(
                $conexion,
                'No se puede cambiar el área porque el proceso ya tiene registros relacionados: '
                    . proceso_describir_dependencias($relaciones)
                    . '. Conservar la ubicación evita inconsistencias históricas.',
                409,
                [
                    'campo' => 'area_id',
                    'dependencias' => $relaciones,
                    'total_dependencias' => $totalRelaciones,
                ]
            );
        }
    } elseif (
        (int) $anterior['activo'] === 1
        && ((int) $areaDestino['activo'] !== 1 || (int) $areaDestino['departamento_activo'] !== 1)
    ) {
        proceso_responder_cancelando(
            $conexion,
            'Un proceso activo no puede permanecer dentro de un área o departamento inactivo.',
            409,
            ['campo' => 'area_id']
        );
    }

    if (proceso_nombre_existe($conexion, $nombre, $areaId, $id)) {
        proceso_responder_cancelando(
            $conexion,
            'Ya existe otro proceso con ese nombre dentro del área seleccionada.',
            409,
            ['campo' => 'nombre']
        );
    }

    $sinCambios = $areaAnteriorId === $areaId
        && (string) $anterior['nombre'] === $nombre
        && proceso_nullable_igual($anterior['descripcion'], $descripcion);

    if ($sinCambios) {
        $conexion->commit();
        sm_responder_json(
            true,
            'No se detectaron cambios en el proceso.',
            ['id' => $id, 'sin_cambios' => true]
        );
    }

    $stmt = $conexion->prepare(
        "UPDATE procesos
         SET area_id = :area_id,
             nombre = :nombre,
             descripcion = :descripcion
         WHERE id = :id"
    );
    $stmt->bindValue(':area_id', $areaId, PDO::PARAM_INT);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    proceso_bind_nullable($stmt, ':descripcion', $descripcion);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $nuevo = proceso_datos_auditoria($anterior);
    $nuevo['area_id'] = $areaId;
    $nuevo['area'] = (string) $areaDestino['nombre'];
    $nuevo['departamento_id'] = (int) $areaDestino['departamento_id'];
    $nuevo['departamento'] = (string) $areaDestino['departamento'];
    $nuevo['nombre'] = $nombre;
    $nuevo['descripcion'] = $descripcion;

    proceso_registrar_auditoria(
        $conexion,
        $adminId,
        'UPDATE',
        $id,
        $cambiaArea
            ? 'Actualización del proceso y cambio de área.'
            : 'Actualización de datos del proceso.',
        proceso_datos_auditoria($anterior),
        $nuevo
    );

    $descripcionMovimiento = $cambiaArea
        ? 'Se movió el proceso "' . $nombre . '" del área "'
            . (string) $anterior['area'] . '" a "'
            . (string) $areaDestino['nombre'] . '".'
        : 'Se actualizó el proceso "' . $nombre . '".';

    proceso_registrar_movimiento(
        $conexion,
        $adminId,
        'EDITAR_PROCESO',
        $descripcionMovimiento,
        $id
    );

    $conexion->commit();
    sm_responder_json(true, 'Proceso actualizado correctamente.', ['id' => $id]);
}

function proceso_endpoint_cambiar_estado(PDO $conexion): void
{
    $adminId = proceso_admin_id();
    $id = proceso_entero_positivo($_POST['id'] ?? null, 'proceso');
    $activo = proceso_validar_estado($_POST['activo'] ?? null);

    $conexion->beginTransaction();

    $proceso = proceso_obtener_proceso($conexion, $id, true);
    if (!$proceso) {
        proceso_responder_cancelando(
            $conexion,
            'El proceso solicitado ya no existe.',
            404
        );
    }

    $estadoAnterior = (int) $proceso['activo'];
    if ($estadoAnterior === $activo) {
        $conexion->commit();
        sm_responder_json(
            true,
            $activo === 1
                ? 'El proceso ya se encontraba activo.'
                : 'El proceso ya se encontraba inactivo.',
            ['sin_cambios' => true]
        );
    }

    if ($activo === 0) {
        $dependencias = proceso_obtener_dependencias_activas($conexion, $id);
        $total = array_sum($dependencias);

        if ($total > 0) {
            proceso_responder_cancelando(
                $conexion,
                'No se puede desactivar el proceso porque todavía tiene registros activos relacionados: '
                    . proceso_describir_dependencias($dependencias)
                    . '. Primero desactiva, finaliza o reasigna esos registros.',
                409,
                [
                    'dependencias' => $dependencias,
                    'total_dependencias' => $total,
                ]
            );
        }
    } else {
        $area = proceso_obtener_area(
            $conexion,
            (int) $proceso['area_id'],
            true
        );

        if (
            !$area
            || (int) $area['activo'] !== 1
            || (int) $area['departamento_activo'] !== 1
        ) {
            proceso_responder_cancelando(
                $conexion,
                'No se puede reactivar el proceso porque su área o departamento está inactivo. Reactiva primero los catálogos superiores.',
                409
            );
        }

        if (proceso_nombre_existe(
            $conexion,
            (string) $proceso['nombre'],
            (int) $proceso['area_id'],
            $id
        )) {
            proceso_responder_cancelando(
                $conexion,
                'No se puede reactivar el proceso porque ya existe otro proceso con el mismo nombre dentro del área.',
                409
            );
        }
    }

    $stmt = $conexion->prepare(
        "UPDATE procesos
         SET activo = :activo
         WHERE id = :id AND activo = :estado_anterior"
    );
    $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':estado_anterior', $estadoAnterior, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        proceso_responder_cancelando(
            $conexion,
            'El estado del proceso cambió mientras realizabas la operación. Actualiza la lista.',
            409
        );
    }

    $nuevo = proceso_datos_auditoria($proceso);
    $nuevo['activo'] = $activo;
    $reactivar = $activo === 1;

    proceso_registrar_auditoria(
        $conexion,
        $adminId,
        $reactivar ? 'REACTIVACION' : 'DELETE_LOGICO',
        $id,
        $reactivar
            ? 'Reactivación administrativa del proceso.'
            : 'Desactivación administrativa del proceso.',
        proceso_datos_auditoria($proceso),
        $nuevo
    );
    proceso_registrar_movimiento(
        $conexion,
        $adminId,
        $reactivar ? 'REACTIVAR_PROCESO' : 'DESACTIVAR_PROCESO',
        ($reactivar ? 'Se reactivó' : 'Se desactivó')
            . ' el proceso "' . (string) $proceso['nombre'] . '" del área "'
            . (string) $proceso['area'] . '".',
        $id
    );

    $conexion->commit();
    sm_responder_json(
        true,
        $reactivar
            ? 'Proceso reactivado correctamente.'
            : 'Proceso desactivado correctamente.'
    );
}

/* =========================================================================
   CONSULTAS Y RELACIONES
   ========================================================================= */

function proceso_listar_areas(PDO $conexion): array
{
    $stmt = $conexion->query(
        "SELECT
            a.id,
            a.departamento_id,
            a.nombre,
            a.activo,
            COALESCE(d.nombre, 'Departamento no disponible') AS departamento,
            COALESCE(d.activo, 0) AS departamento_activo
         FROM areas a
         LEFT JOIN departamentos d ON d.id = a.departamento_id
         ORDER BY departamento ASC, a.activo DESC, a.nombre ASC, a.id ASC"
    );
    $areas = $stmt->fetchAll();

    foreach ($areas as &$area) {
        foreach (['id', 'departamento_id', 'activo', 'departamento_activo'] as $campo) {
            $area[$campo] = (int) ($area[$campo] ?? 0);
        }
    }
    unset($area);

    return $areas;
}

function proceso_obtener_proceso(PDO $conexion, int $id, bool $bloquear): ?array
{
    $sql = "SELECT
                p.id,
                p.area_id,
                p.nombre,
                p.descripcion,
                p.activo,
                p.fecha_registro,
                COALESCE(a.nombre, 'Área no disponible') AS area,
                COALESCE(a.activo, 0) AS area_activa,
                COALESCE(a.departamento_id, 0) AS departamento_id,
                COALESCE(d.nombre, 'Departamento no disponible') AS departamento,
                COALESCE(d.activo, 0) AS departamento_activo
            FROM procesos p
            LEFT JOIN areas a ON a.id = p.area_id
            LEFT JOIN departamentos d ON d.id = a.departamento_id
            WHERE p.id = :id
            LIMIT 1";

    if ($bloquear) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch();

    if (!$fila) {
        return null;
    }

    proceso_convertir_fila($fila);
    return $fila;
}

function proceso_obtener_area(PDO $conexion, int $id, bool $bloquear): ?array
{
    $sql = "SELECT
                a.id,
                a.departamento_id,
                a.nombre,
                a.activo,
                COALESCE(d.nombre, 'Departamento no disponible') AS departamento,
                COALESCE(d.activo, 0) AS departamento_activo
            FROM areas a
            LEFT JOIN departamentos d ON d.id = a.departamento_id
            WHERE a.id = :id
            LIMIT 1";

    if ($bloquear) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch();

    if (!$fila) {
        return null;
    }

    foreach (['id', 'departamento_id', 'activo', 'departamento_activo'] as $campo) {
        $fila[$campo] = (int) ($fila[$campo] ?? 0);
    }

    return $fila;
}

function proceso_nombre_existe(
    PDO $conexion,
    string $nombre,
    int $areaId,
    int $idActual
): bool {
    $sql = "SELECT COUNT(*)
            FROM procesos
            WHERE nombre = :nombre AND area_id = :area_id";

    if ($idActual > 0) {
        $sql .= ' AND id <> :id';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':area_id', $areaId, PDO::PARAM_INT);
    if ($idActual > 0) {
        $stmt->bindValue(':id', $idActual, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function proceso_obtener_dependencias_activas(PDO $conexion, int $id): array
{
    $sql = "SELECT
                (SELECT COUNT(*) FROM equipos
                 WHERE proceso_id = :equipos AND activo = 1) AS equipos,
                (SELECT COUNT(*) FROM solicitudes
                 WHERE proceso_id = :solicitudes
                   AND activo = 1
                   AND estado IN (
                       'PENDIENTE','APROBADO','AGENDADO','EN_PROCESO',
                       'PAUSADO','ATRASADO'
                   )) AS solicitudes,
                (SELECT COUNT(*) FROM rutinas_mantenimiento
                 WHERE proceso_id = :rutinas AND activo = 1) AS rutinas";

    $stmt = $conexion->prepare($sql);
    foreach (['equipos', 'solicitudes', 'rutinas'] as $campo) {
        $stmt->bindValue(':' . $campo, $id, PDO::PARAM_INT);
    }
    $stmt->execute();

    return proceso_convertir_dependencias($stmt->fetch() ?: []);
}

function proceso_obtener_relaciones_totales(PDO $conexion, int $id): array
{
    $sql = "SELECT
                (SELECT COUNT(*) FROM equipos
                 WHERE proceso_id = :equipos) AS equipos,
                (SELECT COUNT(*) FROM solicitudes
                 WHERE proceso_id = :solicitudes) AS solicitudes,
                (SELECT COUNT(*) FROM rutinas_mantenimiento
                 WHERE proceso_id = :rutinas) AS rutinas";

    $stmt = $conexion->prepare($sql);
    foreach (['equipos', 'solicitudes', 'rutinas'] as $campo) {
        $stmt->bindValue(':' . $campo, $id, PDO::PARAM_INT);
    }
    $stmt->execute();

    return proceso_convertir_dependencias($stmt->fetch() ?: []);
}

function proceso_convertir_dependencias(array $fila): array
{
    return [
        'equipos' => (int) ($fila['equipos'] ?? 0),
        'solicitudes' => (int) ($fila['solicitudes'] ?? 0),
        'rutinas' => (int) ($fila['rutinas'] ?? 0),
    ];
}

function proceso_convertir_fila(array &$proceso): void
{
    foreach (
        [
            'id', 'area_id', 'activo', 'area_activa', 'departamento_id',
            'departamento_activo', 'equipos_activos',
            'solicitudes_abiertas', 'rutinas_activas'
        ] as $campo
    ) {
        $proceso[$campo] = (int) ($proceso[$campo] ?? 0);
    }
}

/* =========================================================================
   MOVIMIENTOS Y AUDITORÍA
   ========================================================================= */

function proceso_registrar_movimiento(
    PDO $conexion,
    int $adminId,
    string $accion,
    string $descripcion,
    int $registroId
): void {
    $stmt = $conexion->prepare(
        "INSERT INTO movimientos_sistema
        (
            tipo_usuario, usuario_id, accion, modulo, descripcion,
            tabla_afectada, registro_id, ip_address, user_agent,
            fecha_movimiento
        )
        VALUES
        (
            'ADMIN', :usuario_id, :accion, 'Procesos', :descripcion,
            'procesos', :registro_id, :ip_address, :user_agent,
            NOW()
        )"
    );
    $stmt->bindValue(':usuario_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', proceso_recortar($accion, 100), PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    proceso_bind_nullable($stmt, ':ip_address', proceso_ip());
    proceso_bind_nullable(
        $stmt,
        ':user_agent',
        proceso_recortar_nullable(proceso_user_agent(), 255)
    );
    $stmt->execute();
}

function proceso_registrar_auditoria(
    PDO $conexion,
    int $adminId,
    string $accion,
    int $registroId,
    string $motivo,
    ?array $anteriores,
    ?array $nuevos
): void {
    $stmt = $conexion->prepare(
        "INSERT INTO auditoria_ediciones
        (
            tabla_afectada, registro_id, solicitud_id, actor_tipo,
            actor_id, accion, motivo, datos_anteriores, datos_nuevos,
            ip_address, user_agent, fecha_evento
        )
        VALUES
        (
            'procesos', :registro_id, NULL, 'ADMIN',
            :actor_id, :accion, :motivo, :anteriores, :nuevos,
            :ip_address, :user_agent, NOW()
        )"
    );
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':motivo', proceso_recortar($motivo, 500), PDO::PARAM_STR);
    proceso_bind_nullable($stmt, ':anteriores', proceso_json($anteriores));
    proceso_bind_nullable($stmt, ':nuevos', proceso_json($nuevos));
    proceso_bind_nullable($stmt, ':ip_address', proceso_ip());
    proceso_bind_nullable(
        $stmt,
        ':user_agent',
        proceso_recortar_nullable(proceso_user_agent(), 500)
    );
    $stmt->execute();
}

/* =========================================================================
   VALIDACIONES Y UTILIDADES
   ========================================================================= */

function proceso_validar_admin_activo(PDO $conexion, int $adminId): void
{
    $stmt = $conexion->prepare(
        "SELECT COUNT(*)
         FROM administradores
         WHERE id = :id AND activo = 1"
    );
    $stmt->bindValue(':id', $adminId, PDO::PARAM_INT);
    $stmt->execute();

    if ((int) $stmt->fetchColumn() !== 1) {
        sm_responder_json(
            false,
            'Tu cuenta administrativa ya no está activa.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?sesion=expirada',
            ],
            401
        );
    }
}

function proceso_validar_nombre($valor): string
{
    $nombre = preg_replace('/\s+/u', ' ', proceso_texto($valor)) ?? '';
    $nombre = trim($nombre);
    $longitud = proceso_longitud($nombre);

    if ($longitud < 2 || $longitud > 100) {
        sm_responder_json(
            false,
            'El nombre debe contener entre 2 y 100 caracteres.',
            ['campo' => 'nombre'],
            422
        );
    }

    if (
        !preg_match('/^[\p{L}\p{M}\p{N} .,&()\/\-\'’]+$/u', $nombre)
        || !preg_match('/[\p{L}\p{N}]/u', $nombre)
    ) {
        sm_responder_json(
            false,
            'El nombre contiene caracteres no permitidos.',
            ['campo' => 'nombre'],
            422
        );
    }

    return $nombre;
}

function proceso_validar_descripcion($valor): ?string
{
    $descripcion = proceso_texto($valor);
    $descripcion = str_replace(["\r\n", "\r"], "\n", $descripcion);
    $descripcion = trim($descripcion);

    if ($descripcion === '') {
        return null;
    }

    if (proceso_longitud($descripcion) > 500) {
        sm_responder_json(
            false,
            'La descripción no puede superar los 500 caracteres.',
            ['campo' => 'descripcion'],
            422
        );
    }

    return $descripcion;
}

function proceso_validar_estado($valor): int
{
    $estado = proceso_texto($valor);
    if ($estado !== '0' && $estado !== '1') {
        sm_responder_json(
            false,
            'El estado solicitado no es válido.',
            ['campo' => 'activo'],
            422
        );
    }
    return (int) $estado;
}

function proceso_entero_positivo($valor, string $campo): int
{
    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($entero === false) {
        sm_responder_json(
            false,
            'El identificador de ' . $campo . ' no es válido.',
            ['campo' => $campo],
            422
        );
    }

    return (int) $entero;
}

function proceso_admin_id(): int
{
    $id = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || (int) $id < 1) {
        sm_responder_json(false, 'Tu sesión administrativa no es válida.', [], 401);
    }
    return (int) $id;
}

function proceso_texto($valor): string
{
    return sm_limpiar_texto($valor);
}

function proceso_describir_dependencias(array $dependencias): string
{
    $etiquetas = [
        'equipos' => ['equipo', 'equipos'],
        'solicitudes' => ['solicitud', 'solicitudes'],
        'rutinas' => ['rutina', 'rutinas'],
    ];
    $partes = [];

    foreach ($dependencias as $clave => $cantidad) {
        $cantidad = (int) $cantidad;
        if ($cantidad > 0 && isset($etiquetas[$clave])) {
            $partes[] = $cantidad . ' '
                . $etiquetas[$clave][$cantidad === 1 ? 0 : 1];
        }
    }

    return implode(', ', $partes);
}

function proceso_datos_auditoria(array $proceso): array
{
    return [
        'id' => (int) ($proceso['id'] ?? 0),
        'area_id' => (int) ($proceso['area_id'] ?? 0),
        'area' => (string) ($proceso['area'] ?? ''),
        'departamento_id' => (int) ($proceso['departamento_id'] ?? 0),
        'departamento' => (string) ($proceso['departamento'] ?? ''),
        'nombre' => (string) ($proceso['nombre'] ?? ''),
        'descripcion' => $proceso['descripcion'] ?? null,
        'activo' => (int) ($proceso['activo'] ?? 0),
    ];
}

function proceso_nullable_igual($a, $b): bool
{
    $a = $a === null || trim((string) $a) === '' ? null : (string) $a;
    $b = $b === null || trim((string) $b) === '' ? null : (string) $b;
    return $a === $b;
}

function proceso_bind_nullable(
    PDOStatement $stmt,
    string $parametro,
    ?string $valor
): void {
    $stmt->bindValue(
        $parametro,
        $valor,
        $valor === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );
}

function proceso_responder_cancelando(
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

function proceso_json(?array $datos): ?string
{
    if ($datos === null) {
        return null;
    }
    $json = json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );
    return $json === false ? null : $json;
}

function proceso_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? (int) mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function proceso_recortar(string $texto, int $limite): string
{
    if (proceso_longitud($texto) <= $limite) {
        return $texto; 
    }
    return function_exists('mb_substr')
        ? (string) mb_substr($texto, 0, $limite, 'UTF-8')
        : substr($texto, 0, $limite);
}

function proceso_recortar_nullable(?string $texto, int $limite): ?string
{
    if ($texto === null || trim($texto) === '') {
        return null;
    }
    return proceso_recortar($texto, $limite);
}

function proceso_ip(): ?string
{
    $ip = proceso_texto($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip === '' ? null : proceso_recortar($ip, 45);
}

function proceso_user_agent(): ?string
{
    $agente = proceso_texto($_SERVER['HTTP_USER_AGENT'] ?? '');
    return $agente === '' ? null : $agente;
}