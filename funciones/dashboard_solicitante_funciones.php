<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dashboard del solicitante - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| Endpoint de solo lectura para la pantalla inicial del solicitante.
| - Perfil y validación de cuenta activa.
| - Resumen sencillo de sus propias solicitudes.
| - Últimas solicitudes con su programación actual.
| - No modifica solicitudes ni información histórica.
| Compatible con PHP 7.4 o superior.
| Consulta corregida para equipos.nombre_equipo.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_sesion(['SOLICITANTE'], true);

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
} catch (Throwable $e) {
    error_log('[DASHBOARD SOLICITANTE][PDO CONFIG] ' . $e->getMessage());
}

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accion = strtoupper(ds_texto($_GET['accion'] ?? 'INICIAL'));

try {
    sm_requerir_metodo('GET');

    if (!in_array($accion, ['INICIAL', 'OBTENER_DASHBOARD'], true)) {
        sm_responder_json(
            false,
            'La acción solicitada no es válida.',
            [],
            400
        );
    }

    $solicitanteId = ds_solicitante_id();
    $perfil = ds_obtener_perfil($conexion, $solicitanteId);

    if (!$perfil) {
        sm_destruir_sesion();
        sm_responder_json(
            false,
            'La cuenta solicitante ya no está disponible.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?acceso=no_disponible',
            ],
            401
        );
    }

    if ((int) $perfil['activo'] !== 1) {
        sm_destruir_sesion();
        sm_responder_json(
            false,
            'Tu cuenta fue desactivada. Comunícate con un administrador.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?acceso=desactivado',
            ],
            401
        );
    }

    $perfil = ds_normalizar_perfil($perfil);
    $resumen = ds_obtener_resumen($conexion, $solicitanteId);
    $recientes = ds_obtener_recientes($conexion, $solicitanteId, 4);

    sm_responder_json(
        true,
        'Inicio del solicitante cargado correctamente.',
        [
            'solicitante' => $perfil,
            'resumen' => $resumen,
            'solicitudes_recientes' => $recientes,
            'actualizado_en' => date('d/m/Y H:i'),
        ]
    );
} catch (PDOException $e) {
    $referencia = 'DS-' . date('Ymd-His');

    error_log(
        '[' . $referencia . '][DASHBOARD SOLICITANTE][PDO] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'No fue posible cargar tu información en este momento.',
        ['referencia' => $referencia],
        500
    );
} catch (Throwable $e) {
    $referencia = 'DS-' . date('Ymd-His');

    error_log(
        '[' . $referencia . '][DASHBOARD SOLICITANTE] '
        . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'Ocurrió un error interno al cargar tu inicio.',
        ['referencia' => $referencia],
        500
    );
}

function ds_solicitante_id(): int
{
    $id = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        sm_destruir_sesion();
        sm_responder_json(
            false,
            'Tu sesión no contiene una cuenta solicitante válida.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?sesion=expirada',
            ],
            401
        );
    }

    return (int) $id;
}

/**
 * @return array<string,mixed>|false
 */
function ds_obtener_perfil(PDO $conexion, int $solicitanteId)
{
    $stmt = $conexion->prepare(
        "SELECT
            s.id,
            s.usuario,
            s.nombre,
            s.apellido_paterno,
            s.apellido_materno,
            s.correo,
            s.telefono,
            s.departamento_id,
            s.activo,
            s.ultimo_acceso,
            s.fecha_registro,
            d.nombre AS departamento,
            COALESCE(d.activo, 0) AS departamento_activo,
            DATE_FORMAT(s.ultimo_acceso, '%d/%m/%Y %H:%i') AS ultimo_acceso_texto
         FROM solicitantes s
         LEFT JOIN departamentos d
            ON d.id = s.departamento_id
         WHERE s.id = :id
         LIMIT 1"
    );
    $stmt->bindValue(':id', $solicitanteId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * @param array<string,mixed> $perfil
 * @return array<string,mixed>
 */
function ds_normalizar_perfil(array $perfil): array
{
    $nombreCompleto = ds_nombre_completo($perfil);
    $nombreCorto = trim((string) ($perfil['nombre'] ?? ''));

    if ($nombreCorto === '') {
        $nombreCorto = $nombreCompleto !== '' ? $nombreCompleto : 'Solicitante';
    }

    $departamentoId = $perfil['departamento_id'] === null
        ? null
        : (int) $perfil['departamento_id'];
    $departamentoActivo = (int) ($perfil['departamento_activo'] ?? 0) === 1;
    $departamento = trim((string) ($perfil['departamento'] ?? ''));

    if ($departamento === '') {
        $departamento = 'Sin departamento asignado';
    }

    return [
        'id' => (int) $perfil['id'],
        'usuario' => (string) ($perfil['usuario'] ?? ''),
        'nombre' => $nombreCorto,
        'nombre_completo' => $nombreCompleto !== '' ? $nombreCompleto : $nombreCorto,
        'correo' => (string) ($perfil['correo'] ?? ''),
        'telefono' => (string) ($perfil['telefono'] ?? ''),
        'departamento_id' => $departamentoId,
        'departamento' => $departamento,
        'departamento_activo' => $departamentoActivo,
        'puede_crear_solicitudes' => $departamentoId !== null && $departamentoActivo,
        'ultimo_acceso' => $perfil['ultimo_acceso'] === null
            ? 'Primer ingreso'
            : (string) ($perfil['ultimo_acceso_texto'] ?? 'Sin dato'),
    ];
}

/**
 * @return array<string,int>
 */
function ds_obtener_resumen(PDO $conexion, int $solicitanteId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END), 0) AS en_revision,
            COALESCE(SUM(CASE
                WHEN estado IN ('APROBADO', 'AGENDADO', 'EN_PROCESO', 'PAUSADO', 'ATRASADO')
                THEN 1 ELSE 0 END), 0) AS en_seguimiento,
            COALESCE(SUM(CASE WHEN estado IN ('EN_PROCESO', 'PAUSADO') THEN 1 ELSE 0 END), 0) AS en_atencion,
            COALESCE(SUM(CASE WHEN estado = 'ATRASADO' THEN 1 ELSE 0 END), 0) AS atrasadas,
            COALESCE(SUM(CASE WHEN estado = 'TERMINADO' THEN 1 ELSE 0 END), 0) AS terminadas,
            COALESCE(SUM(CASE WHEN estado IN ('RECHAZADO', 'CANCELADO') THEN 1 ELSE 0 END), 0) AS cerradas_sin_ejecucion,
            COALESCE(SUM(CASE
                WHEN tipo_solicitud = 'CORRECTIVO_URGENTE'
                 AND estado IN ('PENDIENTE', 'APROBADO', 'AGENDADO', 'EN_PROCESO', 'PAUSADO', 'ATRASADO')
                THEN 1 ELSE 0 END), 0) AS urgentes_abiertas
         FROM solicitudes
         WHERE solicitante_id = :solicitante_id
           AND activo = 1
           AND tipo_solicitud IN (
               'CORRECTIVO_PROGRAMABLE',
               'MODIFICACION_MEJORA',
               'CORRECTIVO_URGENTE'
           )"
    );
    $stmt->bindValue(':solicitante_id', $solicitanteId, PDO::PARAM_INT);
    $stmt->execute();

    $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total' => (int) ($fila['total'] ?? 0),
        'en_revision' => (int) ($fila['en_revision'] ?? 0),
        'en_seguimiento' => (int) ($fila['en_seguimiento'] ?? 0),
        'en_atencion' => (int) ($fila['en_atencion'] ?? 0),
        'atrasadas' => (int) ($fila['atrasadas'] ?? 0),
        'terminadas' => (int) ($fila['terminadas'] ?? 0),
        'cerradas_sin_ejecucion' => (int) ($fila['cerradas_sin_ejecucion'] ?? 0),
        'urgentes_abiertas' => (int) ($fila['urgentes_abiertas'] ?? 0),
    ];
}

/**
 * @return array<int,array<string,mixed>>
 */
function ds_obtener_recientes(
    PDO $conexion,
    int $solicitanteId,
    int $limite
): array {
    $limite = max(1, min(8, $limite));

    $sql = "SELECT
                s.id,
                s.folio,
                s.tipo_solicitud,
                s.estado,
                s.prioridad,
                s.descripcion_solicitud,
                s.fecha_solicitud,
                s.hora_solicitud,
                d.nombre AS departamento,
                a.nombre AS area,
                p.nombre AS proceso,
                e.codigo_equipo,
                e.nombre_equipo AS equipo,
                DATE_FORMAT(s.fecha_solicitud, '%d/%m/%Y') AS fecha_solicitud_texto,
                TIME_FORMAT(s.hora_solicitud, '%H:%i') AS hora_solicitud_texto,
                (
                    SELECT DATE_FORMAT(pm.fecha_programada, '%d/%m/%Y')
                    FROM programaciones_mantenimiento pm
                    WHERE pm.solicitud_id = s.id
                      AND pm.es_actual = 1
                      AND pm.estado IN ('PROGRAMADA', 'VENCIDA')
                    ORDER BY pm.id DESC
                    LIMIT 1
                ) AS fecha_programada_texto,
                (
                    SELECT DATE_FORMAT(cm.fecha_hora_cierre, '%d/%m/%Y %H:%i')
                    FROM cierres_mantenimiento cm
                    WHERE cm.solicitud_id = s.id
                    ORDER BY cm.id DESC
                    LIMIT 1
                ) AS fecha_cierre_texto
            FROM solicitudes s
            LEFT JOIN departamentos d ON d.id = s.departamento_id
            LEFT JOIN areas a ON a.id = s.area_id
            LEFT JOIN procesos p ON p.id = s.proceso_id
            LEFT JOIN equipos e ON e.id = s.equipo_id
            WHERE s.solicitante_id = :solicitante_id
              AND s.activo = 1
              AND s.tipo_solicitud IN (
                  'CORRECTIVO_PROGRAMABLE',
                  'MODIFICACION_MEJORA',
                  'CORRECTIVO_URGENTE'
              )
            ORDER BY s.fecha_solicitud DESC, s.hora_solicitud DESC, s.id DESC
            LIMIT :limite";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':solicitante_id', $solicitanteId, PDO::PARAM_INT);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();

    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($registros as &$registro) {
        $registro['id'] = (int) $registro['id'];
        $registro['folio'] = (string) ($registro['folio'] ?? '');
        $registro['tipo_solicitud'] = (string) ($registro['tipo_solicitud'] ?? '');
        $registro['estado'] = (string) ($registro['estado'] ?? '');
        $registro['prioridad'] = (string) ($registro['prioridad'] ?? '');
        $registro['descripcion_solicitud'] = ds_recortar_texto(
            (string) ($registro['descripcion_solicitud'] ?? ''),
            170
        );
        $registro['departamento'] = (string) ($registro['departamento'] ?? '');
        $registro['area'] = (string) ($registro['area'] ?? '');
        $registro['proceso'] = (string) ($registro['proceso'] ?? '');
        $registro['codigo_equipo'] = (string) ($registro['codigo_equipo'] ?? '');
        $registro['equipo'] = (string) ($registro['equipo'] ?? 'Equipo no disponible');
        $registro['fecha_solicitud_texto'] = (string) ($registro['fecha_solicitud_texto'] ?? '');
        $registro['hora_solicitud_texto'] = (string) ($registro['hora_solicitud_texto'] ?? '');
        $registro['fecha_programada_texto'] = (string) ($registro['fecha_programada_texto'] ?? '');
        $registro['fecha_cierre_texto'] = (string) ($registro['fecha_cierre_texto'] ?? '');
    }
    unset($registro);

    return $registros;
}

/**
 * @param array<string,mixed> $registro
 */
function ds_nombre_completo(array $registro): string
{
    $partes = [
        trim((string) ($registro['nombre'] ?? '')),
        trim((string) ($registro['apellido_paterno'] ?? '')),
        trim((string) ($registro['apellido_materno'] ?? '')),
    ];

    return trim(implode(' ', array_values(array_filter(
        $partes,
        static function (string $parte): bool {
            return $parte !== '';
        }
    ))));
}

function ds_recortar_texto(string $texto, int $limite): string
{
    $texto = preg_replace('/\s+/u', ' ', trim($texto)) ?? trim($texto);

    if ($texto === '') {
        return 'Sin descripción';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($texto, 'UTF-8') <= $limite) {
            return $texto;
        }

        return rtrim(mb_substr($texto, 0, $limite - 1, 'UTF-8')) . '…';
    }

    if (strlen($texto) <= $limite) {
        return $texto;
    }

    return rtrim(substr($texto, 0, $limite - 1)) . '…';
} 

function ds_texto($valor): string
{
    return sm_limpiar_texto($valor);
}