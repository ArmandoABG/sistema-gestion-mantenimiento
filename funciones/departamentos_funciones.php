<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Departamentos - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| Catálogo administrativo con creación, edición y baja lógica.
| No elimina registros. Protege relaciones activas y registra auditoría.
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
    error_log('[DEPARTAMENTOS][PDO CONFIG] ' . $e->getMessage());
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = strtoupper(dep_texto(
    $metodo === 'GET'
        ? ($_GET['accion'] ?? 'INICIAL')
        : ($_POST['accion'] ?? '')
));

try {
    dep_validar_admin_activo($conexion, dep_admin_id());

    if ($metodo === 'GET') {
        sm_requerir_metodo('GET');

        if ($accion === 'INICIAL' || $accion === 'LISTAR') {
            dep_endpoint_listar($conexion);
        }

        if ($accion === 'DETALLE') {
            dep_endpoint_detalle($conexion);
        }

        sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
    }

    sm_requerir_metodo('POST');
    sm_validar_csrf();

    if ($accion === 'GUARDAR') {
        dep_endpoint_guardar($conexion);
    }

    if ($accion === 'CAMBIAR_ESTADO') {
        dep_endpoint_cambiar_estado($conexion);
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'DEP-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][DEPARTAMENTOS][PDO] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    if ((string) $e->getCode() === '23000') {
        sm_responder_json(
            false,
            'Ya existe un departamento con ese nombre o el registro tiene datos relacionados que impiden la operación.',
            ['campo' => 'nombre', 'referencia' => $referencia],
            409
        );
    }

    sm_responder_json(
        false,
        'No fue posible procesar el catálogo de departamentos.',
        ['referencia' => $referencia],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $referencia = 'DEP-' . date('Ymd-His');
    error_log(
        '[' . $referencia . '][DEPARTAMENTOS] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar el departamento.',
        ['referencia' => $referencia],
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function dep_endpoint_listar(PDO $conexion): void
{
    $sql = "SELECT
                d.id,
                d.nombre,
                d.descripcion,
                d.activo,
                d.fecha_registro,
                DATE_FORMAT(d.fecha_registro, '%d/%m/%Y') AS fecha_registro_texto,
                COALESCE((
                    SELECT COUNT(*)
                    FROM areas a
                    WHERE a.departamento_id = d.id
                      AND a.activo = 1
                ), 0) AS areas_activas,
                COALESCE((
                    SELECT COUNT(*)
                    FROM solicitantes so
                    WHERE so.departamento_id = d.id
                      AND so.activo = 1
                ), 0) AS solicitantes_activos,
                COALESCE((
                    SELECT COUNT(*)
                    FROM tecnicos t
                    WHERE t.departamento_id = d.id
                      AND t.activo = 1
                ), 0) AS tecnicos_activos,
                COALESCE((
                    SELECT COUNT(*)
                    FROM equipos e
                    WHERE e.departamento_id = d.id
                      AND e.activo = 1
                ), 0) AS equipos_activos,
                COALESCE((
                    SELECT COUNT(*)
                    FROM solicitudes s
                    WHERE s.departamento_id = d.id
                      AND s.activo = 1
                      AND s.estado IN (
                          'PENDIENTE','APROBADO','AGENDADO','EN_PROCESO',
                          'PAUSADO','ATRASADO'
                      )
                ), 0) AS solicitudes_abiertas,
                COALESCE((
                    SELECT COUNT(*)
                    FROM rutinas_mantenimiento r
                    WHERE r.departamento_id = d.id
                      AND r.activo = 1
                ), 0) AS rutinas_activas
            FROM departamentos d
            ORDER BY d.activo DESC, d.nombre ASC, d.id ASC";

    $departamentos = $conexion->query($sql)->fetchAll();
    $activos = 0;
    $inactivos = 0;
    $enUso = 0;

    foreach ($departamentos as &$departamento) {
        $departamento['id'] = (int) $departamento['id'];
        $departamento['activo'] = (int) $departamento['activo'];
        $departamento['areas_activas'] = (int) $departamento['areas_activas'];
        $departamento['solicitantes_activos'] = (int) $departamento['solicitantes_activos'];
        $departamento['tecnicos_activos'] = (int) $departamento['tecnicos_activos'];
        $departamento['equipos_activos'] = (int) $departamento['equipos_activos'];
        $departamento['solicitudes_abiertas'] = (int) $departamento['solicitudes_abiertas'];
        $departamento['rutinas_activas'] = (int) $departamento['rutinas_activas'];
        $departamento['total_relaciones_activas'] =
            $departamento['areas_activas']
            + $departamento['solicitantes_activos']
            + $departamento['tecnicos_activos']
            + $departamento['equipos_activos']
            + $departamento['solicitudes_abiertas']
            + $departamento['rutinas_activas'];

        if ($departamento['activo'] === 1) {
            $activos++;
        } else {
            $inactivos++;
        }

        if ($departamento['total_relaciones_activas'] > 0) {
            $enUso++;
        }
    }
    unset($departamento);

    sm_responder_json(
        true,
        'Departamentos cargados correctamente.',
        [
            'departamentos' => $departamentos,
            'resumen' => [
                'total' => count($departamentos),
                'activos' => $activos,
                'inactivos' => $inactivos,
                'en_uso' => $enUso,
            ],
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function dep_endpoint_detalle(PDO $conexion): void
{
    $id = dep_entero_positivo($_GET['id'] ?? null, 'departamento');
    $departamento = dep_obtener_departamento($conexion, $id, false);

    if (!$departamento) {
        sm_responder_json(false, 'El departamento solicitado no existe.', [], 404);
    }

    sm_responder_json(
        true,
        'Departamento cargado correctamente.',
        ['departamento' => $departamento]
    );
}

function dep_endpoint_guardar(PDO $conexion): void
{
    $adminId = dep_admin_id();
    $idEntrada = dep_texto($_POST['departamento_id'] ?? '');
    $id = $idEntrada === '' ? 0 : dep_entero_positivo($idEntrada, 'departamento');
    $nombre = dep_validar_nombre($_POST['nombre'] ?? '');
    $descripcion = dep_validar_descripcion($_POST['descripcion'] ?? '');

    $conexion->beginTransaction();

    if ($id === 0) {
        if (dep_nombre_existe($conexion, $nombre, 0)) {
            dep_responder_cancelando(
                $conexion,
                'Ya existe un departamento con ese nombre.',
                409,
                ['campo' => 'nombre']
            );
        }

        $stmt = $conexion->prepare(
            "INSERT INTO departamentos
             (nombre, descripcion, activo, fecha_registro)
             VALUES (:nombre, :descripcion, 1, NOW())"
        );
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        dep_bind_nullable($stmt, ':descripcion', $descripcion);
        $stmt->execute();

        $id = (int) $conexion->lastInsertId();
        $nuevo = [
            'id' => $id,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'activo' => 1,
        ];

        dep_registrar_auditoria(
            $conexion,
            $adminId,
            'INSERT',
            $id,
            'Registro inicial del departamento.',
            null,
            $nuevo
        );
        dep_registrar_movimiento(
            $conexion,
            $adminId,
            'CREAR_DEPARTAMENTO',
            'Se registró el departamento "' . $nombre . '".',
            $id
        );

        $conexion->commit();
        sm_responder_json(
            true,
            'Departamento registrado correctamente.',
            ['id' => $id]
        );
    }

    $anterior = dep_obtener_departamento($conexion, $id, true);
    if (!$anterior) {
        dep_responder_cancelando(
            $conexion,
            'El departamento que intentas editar ya no existe.',
            404
        );
    }

    if (dep_nombre_existe($conexion, $nombre, $id)) {
        dep_responder_cancelando(
            $conexion,
            'Ya existe otro departamento con ese nombre.',
            409,
            ['campo' => 'nombre']
        );
    }

    $sinCambios = dep_iguales((string) $anterior['nombre'], $nombre)
        && dep_nullable_igual($anterior['descripcion'], $descripcion);

    if ($sinCambios) {
        $conexion->commit();
        sm_responder_json(
            true,
            'No se detectaron cambios en el departamento.',
            ['id' => $id, 'sin_cambios' => true]
        );
    }

    $stmt = $conexion->prepare(
        "UPDATE departamentos
         SET nombre = :nombre,
             descripcion = :descripcion
         WHERE id = :id"
    );
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    dep_bind_nullable($stmt, ':descripcion', $descripcion);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $nuevo = [
        'id' => $id,
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'activo' => (int) $anterior['activo'],
    ];

    dep_registrar_auditoria(
        $conexion,
        $adminId,
        'UPDATE',
        $id,
        'Actualización de datos del departamento.',
        dep_datos_auditoria($anterior),
        $nuevo
    );
    dep_registrar_movimiento(
        $conexion,
        $adminId,
        'EDITAR_DEPARTAMENTO',
        'Se actualizó el departamento "' . $nombre . '".',
        $id
    );

    $conexion->commit();
    sm_responder_json(
        true,
        'Departamento actualizado correctamente.',
        ['id' => $id]
    );
}

function dep_endpoint_cambiar_estado(PDO $conexion): void
{
    $adminId = dep_admin_id();
    $id = dep_entero_positivo($_POST['id'] ?? null, 'departamento');
    $activo = dep_validar_estado($_POST['activo'] ?? null);

    $conexion->beginTransaction();

    $departamento = dep_obtener_departamento($conexion, $id, true);
    if (!$departamento) {
        dep_responder_cancelando(
            $conexion,
            'El departamento solicitado ya no existe.',
            404
        );
    }

    $estadoAnterior = (int) $departamento['activo'];
    if ($estadoAnterior === $activo) {
        $conexion->commit();
        sm_responder_json(
            true,
            $activo === 1
                ? 'El departamento ya se encontraba activo.'
                : 'El departamento ya se encontraba inactivo.',
            ['sin_cambios' => true]
        );
    }

    if ($activo === 0) {
        $dependencias = dep_obtener_dependencias($conexion, $id);
        $total = array_sum($dependencias);

        if ($total > 0) {
            dep_responder_cancelando(
                $conexion,
                'No se puede desactivar el departamento porque todavía tiene registros activos relacionados: '
                    . dep_describir_dependencias($dependencias)
                    . '. Primero desactiva, finaliza o reasigna esos registros.',
                409,
                [
                    'dependencias' => $dependencias,
                    'total_dependencias' => $total,
                ]
            );
        }
    }

    $stmt = $conexion->prepare(
        "UPDATE departamentos
         SET activo = :activo
         WHERE id = :id"
    );
    $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $nuevo = dep_datos_auditoria($departamento);
    $nuevo['activo'] = $activo;
    $reactivar = $activo === 1;

    dep_registrar_auditoria(
        $conexion,
        $adminId,
        $reactivar ? 'REACTIVACION' : 'DELETE_LOGICO',
        $id,
        $reactivar
            ? 'Reactivación administrativa del departamento.'
            : 'Desactivación administrativa del departamento.',
        dep_datos_auditoria($departamento),
        $nuevo
    );
    dep_registrar_movimiento(
        $conexion,
        $adminId,
        $reactivar ? 'REACTIVAR_DEPARTAMENTO' : 'DESACTIVAR_DEPARTAMENTO',
        ($reactivar ? 'Se reactivó' : 'Se desactivó')
            . ' el departamento "' . (string) $departamento['nombre'] . '".',
        $id
    );

    $conexion->commit();
    sm_responder_json(
        true,
        $reactivar
            ? 'Departamento reactivado correctamente.'
            : 'Departamento desactivado correctamente.'
    );
}

/* =========================================================================
   CONSULTAS Y AUDITORÍA
   ========================================================================= */

function dep_obtener_departamento(PDO $conexion, int $id, bool $bloquear): ?array
{
    $sql = "SELECT id, nombre, descripcion, activo, fecha_registro
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

function dep_nombre_existe(PDO $conexion, string $nombre, int $excluirId): bool
{
    $sql = "SELECT COUNT(*)
            FROM departamentos
            WHERE nombre = :nombre";

    if ($excluirId > 0) {
        $sql .= ' AND id <> :id';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    if ($excluirId > 0) {
        $stmt->bindValue(':id', $excluirId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function dep_obtener_dependencias(PDO $conexion, int $id): array
{
    $sql = "SELECT
                (SELECT COUNT(*) FROM areas
                 WHERE departamento_id = :areas AND activo = 1) AS areas,
                (SELECT COUNT(*) FROM solicitantes
                 WHERE departamento_id = :solicitantes AND activo = 1) AS solicitantes,
                (SELECT COUNT(*) FROM tecnicos
                 WHERE departamento_id = :tecnicos AND activo = 1) AS tecnicos,
                (SELECT COUNT(*) FROM equipos
                 WHERE departamento_id = :equipos AND activo = 1) AS equipos,
                (SELECT COUNT(*) FROM solicitudes
                 WHERE departamento_id = :solicitudes
                   AND activo = 1
                   AND estado IN (
                       'PENDIENTE','APROBADO','AGENDADO','EN_PROCESO',
                       'PAUSADO','ATRASADO'
                   )) AS solicitudes,
                (SELECT COUNT(*) FROM rutinas_mantenimiento
                 WHERE departamento_id = :rutinas AND activo = 1) AS rutinas";

    $stmt = $conexion->prepare($sql);
    foreach (['areas', 'solicitantes', 'tecnicos', 'equipos', 'solicitudes', 'rutinas'] as $campo) {
        $stmt->bindValue(':' . $campo, $id, PDO::PARAM_INT);
    }
    $stmt->execute();

    $fila = $stmt->fetch() ?: [];
    $resultado = [];
    foreach (['areas', 'solicitantes', 'tecnicos', 'equipos', 'solicitudes', 'rutinas'] as $campo) {
        $resultado[$campo] = (int) ($fila[$campo] ?? 0);
    }

    return $resultado;
}

function dep_registrar_movimiento(
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
            'ADMIN', :usuario_id, :accion, 'Departamentos', :descripcion,
            'departamentos', :registro_id, :ip_address, :user_agent,
            NOW()
        )"
    );
    $stmt->bindValue(':usuario_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', dep_recortar($accion, 100), PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    dep_bind_nullable($stmt, ':ip_address', dep_ip());
    dep_bind_nullable($stmt, ':user_agent', dep_recortar_nullable(dep_user_agent(), 255));
    $stmt->execute();
}

function dep_registrar_auditoria(
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
            'departamentos', :registro_id, NULL, 'ADMIN',
            :actor_id, :accion, :motivo, :anteriores, :nuevos,
            :ip_address, :user_agent, NOW()
        )"
    );
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':motivo', dep_recortar($motivo, 500), PDO::PARAM_STR);
    dep_bind_nullable($stmt, ':anteriores', dep_json($anteriores));
    dep_bind_nullable($stmt, ':nuevos', dep_json($nuevos));
    dep_bind_nullable($stmt, ':ip_address', dep_ip());
    dep_bind_nullable($stmt, ':user_agent', dep_recortar_nullable(dep_user_agent(), 500));
    $stmt->execute();
}

function dep_validar_admin_activo(PDO $conexion, int $adminId): void
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
            ['sesion_expirada' => true, 'redirect' => '../login.php?sesion=expirada'],
            401
        );
    }
}

/* =========================================================================
   VALIDACIONES Y UTILIDADES
   ========================================================================= */

function dep_validar_nombre($valor): string
{
    $nombre = preg_replace('/\s+/u', ' ', dep_texto($valor)) ?? '';
    $nombre = trim($nombre);
    $longitud = dep_longitud($nombre);

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

function dep_validar_descripcion($valor): ?string
{
    $descripcion = dep_texto($valor);
    $descripcion = str_replace(["\r\n", "\r"], "\n", $descripcion);
    $descripcion = trim($descripcion);

    if ($descripcion === '') {
        return null;
    }

    if (dep_longitud($descripcion) > 500) {
        sm_responder_json(
            false,
            'La descripción no puede superar los 500 caracteres.',
            ['campo' => 'descripcion'],
            422
        );
    }

    return $descripcion;
}

function dep_validar_estado($valor): int
{
    $estado = dep_texto($valor);
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

function dep_entero_positivo($valor, string $campo): int
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

function dep_admin_id(): int
{
    $id = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || (int) $id < 1) {
        sm_responder_json(false, 'Tu sesión administrativa no es válida.', [], 401);
    }
    return (int) $id;
}

function dep_texto($valor): string
{
    return sm_limpiar_texto($valor);
}

function dep_describir_dependencias(array $dependencias): string
{
    $etiquetas = [
        'areas' => ['área activa', 'áreas activas'],
        'solicitantes' => ['solicitante activo', 'solicitantes activos'],
        'tecnicos' => ['técnico activo', 'técnicos activos'],
        'equipos' => ['equipo activo', 'equipos activos'],
        'solicitudes' => ['solicitud abierta', 'solicitudes abiertas'],
        'rutinas' => ['rutina activa', 'rutinas activas'],
    ];
    $partes = [];

    foreach ($dependencias as $clave => $cantidad) {
        $cantidad = (int) $cantidad;
        if ($cantidad > 0 && isset($etiquetas[$clave])) {
            $partes[] = $cantidad . ' ' . $etiquetas[$clave][$cantidad === 1 ? 0 : 1];
        }
    }

    return implode(', ', $partes);
}

function dep_datos_auditoria(array $departamento): array
{
    return [
        'id' => (int) ($departamento['id'] ?? 0),
        'nombre' => (string) ($departamento['nombre'] ?? ''),
        'descripcion' => $departamento['descripcion'] ?? null,
        'activo' => (int) ($departamento['activo'] ?? 0),
    ];
}

function dep_iguales(string $a, string $b): bool
{
    return $a === $b;
}

function dep_nullable_igual($a, $b): bool
{
    $a = $a === null || trim((string) $a) === '' ? null : (string) $a;
    $b = $b === null || trim((string) $b) === '' ? null : (string) $b;
    return $a === $b;
}

function dep_bind_nullable(PDOStatement $stmt, string $parametro, ?string $valor): void
{
    $stmt->bindValue(
        $parametro,
        $valor,
        $valor === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );
}

function dep_responder_cancelando(
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

function dep_json(?array $datos): ?string
{
    if ($datos === null) {
        return null;
    }
    $json = json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    return $json === false ? null : $json;
}

function dep_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? (int) mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function dep_recortar(string $texto, int $limite): string
{
    if (dep_longitud($texto) <= $limite) {
        return $texto;
    }
    return function_exists('mb_substr')
        ? (string) mb_substr($texto, 0, $limite, 'UTF-8')
        : substr($texto, 0, $limite);
} 

function dep_recortar_nullable(?string $texto, int $limite): ?string
{
    if ($texto === null || trim($texto) === '') {
        return null;
    }
    return dep_recortar($texto, $limite);
}

function dep_ip(): ?string
{
    $ip = dep_texto($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip === '' ? null : dep_recortar($ip, 45);
}

function dep_user_agent(): ?string
{
    $agente = dep_texto($_SERVER['HTTP_USER_AGENT'] ?? '');
    return $agente === '' ? null : $agente;
}