<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Áreas - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| Catálogo administrativo dependiente de Departamentos.
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
    error_log('[AREAS][PDO CONFIG] ' . $e->getMessage());
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = strtoupper(area_texto(
    $metodo === 'GET'
        ? ($_GET['accion'] ?? 'INICIAL')
        : ($_POST['accion'] ?? '')
));

try {
    area_validar_admin_activo($conexion, area_admin_id());

    if ($metodo === 'GET') {
        sm_requerir_metodo('GET');

        if ($accion === 'INICIAL' || $accion === 'LISTAR') {
            area_endpoint_listar($conexion);
        }

        if ($accion === 'DETALLE') {
            area_endpoint_detalle($conexion);
        }

        sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
    }

    sm_requerir_metodo('POST');
    sm_validar_csrf();

    if ($accion === 'GUARDAR') {
        area_endpoint_guardar($conexion);
    }

    if ($accion === 'CAMBIAR_ESTADO') {
        area_endpoint_cambiar_estado($conexion);
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'AREA-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][AREAS][PDO] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    if ((string) $e->getCode() === '23000') {
        sm_responder_json(
            false,
            'Ya existe un área con ese nombre dentro del departamento seleccionado o hay datos relacionados que impiden la operación.',
            ['campo' => 'nombre', 'referencia' => $referencia],
            409
        );
    }

    sm_responder_json(
        false,
        'No fue posible procesar el catálogo de áreas.',
        ['referencia' => $referencia],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'AREA-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][AREAS] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar el área.',
        ['referencia' => $referencia],
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function area_endpoint_listar(PDO $conexion): void
{
    $sql = "SELECT
                a.id,
                a.departamento_id,
                a.nombre,
                a.descripcion,
                a.activo,
                a.fecha_registro,
                DATE_FORMAT(a.fecha_registro, '%d/%m/%Y') AS fecha_registro_texto,
                COALESCE(d.nombre, 'Departamento no disponible') AS departamento,
                COALESCE(d.activo, 0) AS departamento_activo,
                COALESCE((
                    SELECT COUNT(*)
                    FROM procesos p
                    WHERE p.area_id = a.id
                      AND p.activo = 1
                ), 0) AS procesos_activos,
                COALESCE((
                    SELECT COUNT(*)
                    FROM equipos e
                    WHERE e.area_id = a.id
                      AND e.activo = 1
                ), 0) AS equipos_activos,
                COALESCE((
                    SELECT COUNT(*)
                    FROM solicitudes s
                    WHERE s.area_id = a.id
                      AND s.activo = 1
                      AND s.estado IN (
                          'PENDIENTE','APROBADO','AGENDADO','EN_PROCESO',
                          'PAUSADO','ATRASADO'
                      )
                ), 0) AS solicitudes_abiertas,
                COALESCE((
                    SELECT COUNT(*)
                    FROM rutinas_mantenimiento r
                    WHERE r.area_id = a.id
                      AND r.activo = 1
                ), 0) AS rutinas_activas
            FROM areas a
            LEFT JOIN departamentos d ON d.id = a.departamento_id
            ORDER BY a.activo DESC, departamento ASC, a.nombre ASC, a.id ASC";

    $areas = $conexion->query($sql)->fetchAll();
    $activas = 0;
    $inactivas = 0;
    $enUso = 0;

    foreach ($areas as &$area) {
        area_convertir_fila($area);
        $area['total_relaciones_activas'] =
            $area['procesos_activos']
            + $area['equipos_activos']
            + $area['solicitudes_abiertas']
            + $area['rutinas_activas'];

        if ($area['activo'] === 1) {
            $activas++;
        } else {
            $inactivas++;
        }

        if ($area['total_relaciones_activas'] > 0) {
            $enUso++;
        }
    }
    unset($area);

    sm_responder_json(
        true,
        'Áreas cargadas correctamente.',
        [
            'areas' => $areas,
            'departamentos' => area_listar_departamentos($conexion),
            'resumen' => [
                'total' => count($areas),
                'activas' => $activas,
                'inactivas' => $inactivas,
                'en_uso' => $enUso,
            ],
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function area_endpoint_detalle(PDO $conexion): void
{
    $id = area_entero_positivo($_GET['id'] ?? null, 'área');
    $area = area_obtener_area($conexion, $id, false);

    if (!$area) {
        sm_responder_json(false, 'El área solicitada no existe.', [], 404);
    }

    $relaciones = area_obtener_relaciones_totales($conexion, $id);
    $area['relaciones_totales'] = $relaciones;
    $area['total_relaciones_historicas'] = array_sum($relaciones);
    $area['puede_cambiar_departamento'] =
        $area['total_relaciones_historicas'] === 0 ? 1 : 0;

    sm_responder_json(
        true,
        'Área cargada correctamente.',
        ['area' => $area]
    );
}

function area_endpoint_guardar(PDO $conexion): void
{
    $adminId = area_admin_id();
    $idEntrada = area_texto($_POST['area_id'] ?? '');
    $id = $idEntrada === '' ? 0 : area_entero_positivo($idEntrada, 'área');
    $departamentoId = area_entero_positivo(
        $_POST['departamento_id'] ?? null,
        'departamento'
    );
    $nombre = area_validar_nombre($_POST['nombre'] ?? '');
    $descripcion = area_validar_descripcion($_POST['descripcion'] ?? '');

    $conexion->beginTransaction();

    if ($id === 0) {
        $departamento = area_obtener_departamento($conexion, $departamentoId, true);
        if (!$departamento || (int) $departamento['activo'] !== 1) {
            area_responder_cancelando(
                $conexion,
                'El departamento seleccionado no existe o está inactivo.',
                422,
                ['campo' => 'departamento_id']
            );
        }

        if (area_nombre_existe($conexion, $nombre, $departamentoId, 0)) {
            area_responder_cancelando(
                $conexion,
                'Ya existe un área con ese nombre dentro del departamento seleccionado.',
                409,
                ['campo' => 'nombre']
            );
        }

        $stmt = $conexion->prepare(
            "INSERT INTO areas
             (departamento_id, nombre, descripcion, activo, fecha_registro)
             VALUES (:departamento_id, :nombre, :descripcion, 1, NOW())"
        );
        $stmt->bindValue(':departamento_id', $departamentoId, PDO::PARAM_INT);
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        area_bind_nullable($stmt, ':descripcion', $descripcion);
        $stmt->execute();

        $id = (int) $conexion->lastInsertId();
        $nuevo = [
            'id' => $id,
            'departamento_id' => $departamentoId,
            'departamento' => (string) $departamento['nombre'],
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'activo' => 1,
        ];

        area_registrar_auditoria(
            $conexion,
            $adminId,
            'INSERT',
            $id,
            'Registro inicial del área.',
            null,
            $nuevo
        );
        area_registrar_movimiento(
            $conexion,
            $adminId,
            'CREAR_AREA',
            'Se registró el área "' . $nombre . '" en el departamento "'
                . (string) $departamento['nombre'] . '".',
            $id
        );

        $conexion->commit();
        sm_responder_json(true, 'Área registrada correctamente.', ['id' => $id]);
    }

    $anterior = area_obtener_area($conexion, $id, true);
    if (!$anterior) {
        area_responder_cancelando(
            $conexion,
            'El área que intentas editar ya no existe.',
            404
        );
    }

    $departamentoAnteriorId = (int) $anterior['departamento_id'];
    $cambiaDepartamento = $departamentoAnteriorId !== $departamentoId;
    $departamentoDestino = area_obtener_departamento($conexion, $departamentoId, true);

    if (!$departamentoDestino) {
        area_responder_cancelando(
            $conexion,
            'El departamento seleccionado ya no existe.',
            422,
            ['campo' => 'departamento_id']
        );
    }

    if ($cambiaDepartamento) {
        if ((int) $departamentoDestino['activo'] !== 1) {
            area_responder_cancelando(
                $conexion,
                'El nuevo departamento está inactivo.',
                422,
                ['campo' => 'departamento_id']
            );
        }

        $relaciones = area_obtener_relaciones_totales($conexion, $id);
        $totalRelaciones = array_sum($relaciones);
        if ($totalRelaciones > 0) {
            area_responder_cancelando(
                $conexion,
                'No se puede cambiar el departamento porque el área ya tiene registros relacionados: '
                    . area_describir_dependencias($relaciones)
                    . '. Conservar la ubicación evita inconsistencias históricas.',
                409,
                [
                    'campo' => 'departamento_id',
                    'dependencias' => $relaciones,
                    'total_dependencias' => $totalRelaciones,
                ]
            );
        }
    } elseif (
        (int) $anterior['activo'] === 1
        && (int) $departamentoDestino['activo'] !== 1
    ) {
        area_responder_cancelando(
            $conexion,
            'Un área activa no puede permanecer dentro de un departamento inactivo.',
            409,
            ['campo' => 'departamento_id']
        );
    }

    if (area_nombre_existe($conexion, $nombre, $departamentoId, $id)) {
        area_responder_cancelando(
            $conexion,
            'Ya existe otra área con ese nombre dentro del departamento seleccionado.',
            409,
            ['campo' => 'nombre']
        );
    }

    $sinCambios = $departamentoAnteriorId === $departamentoId
        && (string) $anterior['nombre'] === $nombre
        && area_nullable_igual($anterior['descripcion'], $descripcion);

    if ($sinCambios) {
        $conexion->commit();
        sm_responder_json(
            true,
            'No se detectaron cambios en el área.',
            ['id' => $id, 'sin_cambios' => true]
        );
    }

    $stmt = $conexion->prepare(
        "UPDATE areas
         SET departamento_id = :departamento_id,
             nombre = :nombre,
             descripcion = :descripcion
         WHERE id = :id"
    );
    $stmt->bindValue(':departamento_id', $departamentoId, PDO::PARAM_INT);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    area_bind_nullable($stmt, ':descripcion', $descripcion);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $nuevo = area_datos_auditoria($anterior);
    $nuevo['departamento_id'] = $departamentoId;
    $nuevo['departamento'] = (string) $departamentoDestino['nombre'];
    $nuevo['nombre'] = $nombre;
    $nuevo['descripcion'] = $descripcion;

    area_registrar_auditoria(
        $conexion,
        $adminId,
        'UPDATE',
        $id,
        $cambiaDepartamento
            ? 'Actualización del área y cambio de departamento.'
            : 'Actualización de datos del área.',
        area_datos_auditoria($anterior),
        $nuevo
    );

    $descripcionMovimiento = $cambiaDepartamento
        ? 'Se movió el área "' . $nombre . '" del departamento "'
            . (string) $anterior['departamento'] . '" a "'
            . (string) $departamentoDestino['nombre'] . '".'
        : 'Se actualizó el área "' . $nombre . '".';

    area_registrar_movimiento(
        $conexion,
        $adminId,
        'EDITAR_AREA',
        $descripcionMovimiento,
        $id
    );

    $conexion->commit();
    sm_responder_json(true, 'Área actualizada correctamente.', ['id' => $id]);
}

function area_endpoint_cambiar_estado(PDO $conexion): void
{
    $adminId = area_admin_id();
    $id = area_entero_positivo($_POST['id'] ?? null, 'área');
    $activo = area_validar_estado($_POST['activo'] ?? null);

    $conexion->beginTransaction();

    $area = area_obtener_area($conexion, $id, true);
    if (!$area) {
        area_responder_cancelando(
            $conexion,
            'El área solicitada ya no existe.',
            404
        );
    }

    $estadoAnterior = (int) $area['activo'];
    if ($estadoAnterior === $activo) {
        $conexion->commit();
        sm_responder_json(
            true,
            $activo === 1
                ? 'El área ya se encontraba activa.'
                : 'El área ya se encontraba inactiva.',
            ['sin_cambios' => true]
        );
    }

    if ($activo === 0) {
        $dependencias = area_obtener_dependencias_activas($conexion, $id);
        $total = array_sum($dependencias);

        if ($total > 0) {
            area_responder_cancelando(
                $conexion,
                'No se puede desactivar el área porque todavía tiene registros activos relacionados: '
                    . area_describir_dependencias($dependencias)
                    . '. Primero desactiva, finaliza o reasigna esos registros.',
                409,
                [
                    'dependencias' => $dependencias,
                    'total_dependencias' => $total,
                ]
            );
        }
    } else {
        $departamento = area_obtener_departamento(
            $conexion,
            (int) $area['departamento_id'],
            true
        );

        if (!$departamento || (int) $departamento['activo'] !== 1) {
            area_responder_cancelando(
                $conexion,
                'No se puede reactivar el área porque su departamento está inactivo. Reactiva primero el departamento.',
                409
            );
        }
    }

    $stmt = $conexion->prepare(
        "UPDATE areas SET activo = :activo WHERE id = :id"
    );
    $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $nuevo = area_datos_auditoria($area);
    $nuevo['activo'] = $activo;
    $reactivar = $activo === 1;

    area_registrar_auditoria(
        $conexion,
        $adminId,
        $reactivar ? 'REACTIVACION' : 'DELETE_LOGICO',
        $id,
        $reactivar
            ? 'Reactivación administrativa del área.'
            : 'Desactivación administrativa del área.',
        area_datos_auditoria($area),
        $nuevo
    );
    area_registrar_movimiento(
        $conexion,
        $adminId,
        $reactivar ? 'REACTIVAR_AREA' : 'DESACTIVAR_AREA',
        ($reactivar ? 'Se reactivó' : 'Se desactivó')
            . ' el área "' . (string) $area['nombre'] . '" del departamento "'
            . (string) $area['departamento'] . '".',
        $id
    );

    $conexion->commit();
    sm_responder_json(
        true,
        $reactivar
            ? 'Área reactivada correctamente.'
            : 'Área desactivada correctamente.'
    );
}

/* =========================================================================
   CONSULTAS Y RELACIONES
   ========================================================================= */

function area_listar_departamentos(PDO $conexion): array
{
    $stmt = $conexion->query(
        "SELECT id, nombre, activo
         FROM departamentos
         ORDER BY activo DESC, nombre ASC, id ASC"
    );
    $departamentos = $stmt->fetchAll();

    foreach ($departamentos as &$departamento) {
        $departamento['id'] = (int) $departamento['id'];
        $departamento['activo'] = (int) $departamento['activo'];
    }
    unset($departamento);

    return $departamentos;
}

function area_obtener_area(PDO $conexion, int $id, bool $bloquear): ?array
{
    $sql = "SELECT
                a.id,
                a.departamento_id,
                a.nombre,
                a.descripcion,
                a.activo,
                a.fecha_registro,
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

    $fila['id'] = (int) $fila['id'];
    $fila['departamento_id'] = (int) $fila['departamento_id'];
    $fila['activo'] = (int) $fila['activo'];
    $fila['departamento_activo'] = (int) $fila['departamento_activo'];
    return $fila;
}

function area_obtener_departamento(PDO $conexion, int $id, bool $bloquear): ?array
{
    $sql = "SELECT id, nombre, activo
            FROM departamentos
            WHERE id = :id
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

    $fila['id'] = (int) $fila['id'];
    $fila['activo'] = (int) $fila['activo'];
    return $fila;
}

function area_nombre_existe(
    PDO $conexion,
    string $nombre,
    int $departamentoId,
    int $excluirId
): bool {
    $sql = "SELECT COUNT(*)
            FROM areas
            WHERE departamento_id = :departamento_id
              AND nombre = :nombre";

    if ($excluirId > 0) {
        $sql .= ' AND id <> :id';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':departamento_id', $departamentoId, PDO::PARAM_INT);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    if ($excluirId > 0) {
        $stmt->bindValue(':id', $excluirId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function area_obtener_dependencias_activas(PDO $conexion, int $id): array
{
    $sql = "SELECT
                (SELECT COUNT(*) FROM procesos
                 WHERE area_id = :procesos AND activo = 1) AS procesos,
                (SELECT COUNT(*) FROM equipos
                 WHERE area_id = :equipos AND activo = 1) AS equipos,
                (SELECT COUNT(*) FROM solicitudes
                 WHERE area_id = :solicitudes
                   AND activo = 1
                   AND estado IN (
                       'PENDIENTE','APROBADO','AGENDADO','EN_PROCESO',
                       'PAUSADO','ATRASADO'
                   )) AS solicitudes,
                (SELECT COUNT(*) FROM rutinas_mantenimiento
                 WHERE area_id = :rutinas AND activo = 1) AS rutinas";

    $stmt = $conexion->prepare($sql);
    foreach (['procesos', 'equipos', 'solicitudes', 'rutinas'] as $campo) {
        $stmt->bindValue(':' . $campo, $id, PDO::PARAM_INT);
    }
    $stmt->execute();

    return area_convertir_dependencias($stmt->fetch() ?: []);
}

function area_obtener_relaciones_totales(PDO $conexion, int $id): array
{
    $sql = "SELECT
                (SELECT COUNT(*) FROM procesos
                 WHERE area_id = :procesos) AS procesos,
                (SELECT COUNT(*) FROM equipos
                 WHERE area_id = :equipos) AS equipos,
                (SELECT COUNT(*) FROM solicitudes
                 WHERE area_id = :solicitudes) AS solicitudes,
                (SELECT COUNT(*) FROM rutinas_mantenimiento
                 WHERE area_id = :rutinas) AS rutinas";

    $stmt = $conexion->prepare($sql);
    foreach (['procesos', 'equipos', 'solicitudes', 'rutinas'] as $campo) {
        $stmt->bindValue(':' . $campo, $id, PDO::PARAM_INT);
    }
    $stmt->execute();

    return area_convertir_dependencias($stmt->fetch() ?: []);
}

function area_convertir_dependencias(array $fila): array
{
    return [
        'procesos' => (int) ($fila['procesos'] ?? 0),
        'equipos' => (int) ($fila['equipos'] ?? 0),
        'solicitudes' => (int) ($fila['solicitudes'] ?? 0),
        'rutinas' => (int) ($fila['rutinas'] ?? 0),
    ];
}

function area_convertir_fila(array &$area): void
{
    foreach (
        [
            'id', 'departamento_id', 'activo', 'departamento_activo',
            'procesos_activos', 'equipos_activos', 'solicitudes_abiertas',
            'rutinas_activas'
        ] as $campo
    ) {
        $area[$campo] = (int) ($area[$campo] ?? 0);
    }
}

/* =========================================================================
   MOVIMIENTOS Y AUDITORÍA
   ========================================================================= */

function area_registrar_movimiento(
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
            'ADMIN', :usuario_id, :accion, 'Áreas', :descripcion,
            'areas', :registro_id, :ip_address, :user_agent,
            NOW()
        )"
    );
    $stmt->bindValue(':usuario_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', area_recortar($accion, 100), PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    area_bind_nullable($stmt, ':ip_address', area_ip());
    area_bind_nullable(
        $stmt,
        ':user_agent',
        area_recortar_nullable(area_user_agent(), 255)
    );
    $stmt->execute();
}

function area_registrar_auditoria(
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
            'areas', :registro_id, NULL, 'ADMIN',
            :actor_id, :accion, :motivo, :anteriores, :nuevos,
            :ip_address, :user_agent, NOW()
        )"
    );
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':motivo', area_recortar($motivo, 500), PDO::PARAM_STR);
    area_bind_nullable($stmt, ':anteriores', area_json($anteriores));
    area_bind_nullable($stmt, ':nuevos', area_json($nuevos));
    area_bind_nullable($stmt, ':ip_address', area_ip());
    area_bind_nullable(
        $stmt,
        ':user_agent',
        area_recortar_nullable(area_user_agent(), 500)
    );
    $stmt->execute();
}

/* =========================================================================
   VALIDACIONES Y UTILIDADES
   ========================================================================= */

function area_validar_admin_activo(PDO $conexion, int $adminId): void
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

function area_validar_nombre($valor): string
{
    $nombre = preg_replace('/\s+/u', ' ', area_texto($valor)) ?? '';
    $nombre = trim($nombre);
    $longitud = area_longitud($nombre);

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

function area_validar_descripcion($valor): ?string
{
    $descripcion = area_texto($valor);
    $descripcion = str_replace(["\r\n", "\r"], "\n", $descripcion);
    $descripcion = trim($descripcion);

    if ($descripcion === '') {
        return null;
    }

    if (area_longitud($descripcion) > 500) {
        sm_responder_json(
            false,
            'La descripción no puede superar los 500 caracteres.',
            ['campo' => 'descripcion'],
            422
        );
    }

    return $descripcion;
}

function area_validar_estado($valor): int
{
    $estado = area_texto($valor);
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

function area_entero_positivo($valor, string $campo): int
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

function area_admin_id(): int
{
    $id = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || (int) $id < 1) {
        sm_responder_json(false, 'Tu sesión administrativa no es válida.', [], 401);
    }
    return (int) $id;
}

function area_texto($valor): string
{
    return sm_limpiar_texto($valor);
}

function area_describir_dependencias(array $dependencias): string
{
    $etiquetas = [
        'procesos' => ['proceso', 'procesos'],
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

function area_datos_auditoria(array $area): array
{
    return [
        'id' => (int) ($area['id'] ?? 0),
        'departamento_id' => (int) ($area['departamento_id'] ?? 0),
        'departamento' => (string) ($area['departamento'] ?? ''),
        'nombre' => (string) ($area['nombre'] ?? ''),
        'descripcion' => $area['descripcion'] ?? null,
        'activo' => (int) ($area['activo'] ?? 0),
    ];
}

function area_nullable_igual($a, $b): bool
{
    $a = $a === null || trim((string) $a) === '' ? null : (string) $a;
    $b = $b === null || trim((string) $b) === '' ? null : (string) $b;
    return $a === $b;
}

function area_bind_nullable(
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

function area_responder_cancelando(
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

function area_json(?array $datos): ?string
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

function area_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? (int) mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function area_recortar(string $texto, int $limite): string
{
    if (area_longitud($texto) <= $limite) {
        return $texto;
    }
    return function_exists('mb_substr')
        ? (string) mb_substr($texto, 0, $limite, 'UTF-8')
        : substr($texto, 0, $limite);
} 

function area_recortar_nullable(?string $texto, int $limite): ?string
{
    if ($texto === null || trim($texto) === '') {
        return null;
    }
    return area_recortar($texto, $limite);
}

function area_ip(): ?string
{
    $ip = area_texto($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip === '' ? null : area_recortar($ip, 45);
}

function area_user_agent(): ?string
{
    $agente = area_texto($_SERVER['HTTP_USER_AGENT'] ?? '');
    return $agente === '' ? null : $agente;
}