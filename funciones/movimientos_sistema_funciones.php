<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Movimientos del sistema - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| Bitácora administrativa de sólo lectura.
| Muestra quién realizó una acción, desde qué módulo, cuándo ocurrió y qué
| tabla o registro fue afectado. No modifica ni elimina movimientos.
| Compatible con PHP 7.4 o superior.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_sesion(['ADMIN'], true);
sm_requerir_metodo('GET');

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
    $conexion->exec("SET time_zone = '-06:00'");
} catch (Throwable $e) {
    error_log('[MOVIMIENTOS][CONFIGURACION PDO] ' . $e->getMessage());
}

$accion = strtoupper(mov_texto($_GET['accion'] ?? 'INICIAL'));

try {
    mov_validar_admin_activo($conexion, mov_admin_id());

    if ($accion === 'INICIAL' || $accion === 'LISTAR') {
        mov_endpoint_listar($conexion);
    }

    if ($accion === 'DETALLE') {
        mov_endpoint_detalle($conexion);
    }

    sm_responder_json(false, 'La acción solicitada no es válida.', [], 400);
} catch (PDOException $e) {
    $referencia = 'MOV-' . date('Ymd-His');

    error_log(
        '[' . $referencia . '][MOVIMIENTOS][PDO] '
        . $e->getMessage()
        . ' | ' . $e->getFile()
        . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'No fue posible consultar los movimientos del sistema.',
        ['referencia' => $referencia],
        500
    );
} catch (Throwable $e) {
    $referencia = 'MOV-' . date('Ymd-His');

    error_log(
        '[' . $referencia . '][MOVIMIENTOS] '
        . $e->getMessage()
        . ' | ' . $e->getFile()
        . ':' . $e->getLine()
    );

    sm_responder_json(
        false,
        'Ocurrió un error interno al consultar la bitácora.',
        ['referencia' => $referencia],
        500
    );
}

/* =========================================================================
   ENDPOINTS
   ========================================================================= */

function mov_endpoint_listar(PDO $conexion): void
{
    $filtros = mov_leer_filtros();
    $consulta = mov_construir_condiciones($filtros);

    $total = mov_contar(
        $conexion,
        $consulta['where'],
        $consulta['parametros']
    );

    $porPagina = (int) $filtros['por_pagina'];
    $totalPaginas = max(1, (int) ceil($total / max(1, $porPagina)));
    $pagina = min((int) $filtros['pagina'], $totalPaginas);
    $offset = max(0, ($pagina - 1) * $porPagina);
    $filtros['pagina'] = $pagina;

    $movimientos = mov_consultar_movimientos(
        $conexion,
        $consulta['where'],
        $consulta['parametros'],
        $porPagina,
        $offset
    );

    sm_responder_json(
        true,
        'Movimientos cargados correctamente.',
        [
            'movimientos' => $movimientos,
            'resumen' => mov_consultar_resumen(
                $conexion,
                $consulta['where'],
                $consulta['parametros']
            ),
            'catalogos' => mov_consultar_catalogos($conexion),
            'filtros' => $filtros,
            'paginacion' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $total,
                'total_paginas' => $totalPaginas,
                'desde' => $total > 0 ? $offset + 1 : 0,
                'hasta' => $total > 0 ? min($offset + $porPagina, $total) : 0,
            ],
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}

function mov_endpoint_detalle(PDO $conexion): void
{
    $id = mov_entero_positivo($_GET['id'] ?? null, 'movimiento');
    $movimiento = mov_consultar_detalle($conexion, $id);

    if (!$movimiento) {
        sm_responder_json(
            false,
            'El movimiento solicitado ya no existe.',
            [],
            404
        );
    }

    sm_responder_json(
        true,
        'Detalle cargado correctamente.',
        ['movimiento' => $movimiento]
    );
}

/* =========================================================================
   CONSULTAS
   ========================================================================= */

function mov_consultar_movimientos(
    PDO $conexion,
    string $where,
    array $parametros,
    int $limite,
    int $offset
): array {
    $sql = mov_select_base()
        . ' ' . $where
        . " ORDER BY m.fecha_movimiento DESC, m.id DESC
            LIMIT " . (int) $limite . " OFFSET " . (int) $offset;

    $stmt = $conexion->prepare($sql);
    mov_enlazar_parametros($stmt, $parametros);
    $stmt->execute();

    $filas = $stmt->fetchAll();

    foreach ($filas as &$fila) {
        $fila = mov_formatear_fila($fila, false);
    }
    unset($fila);

    return $filas;
}

function mov_consultar_detalle(PDO $conexion, int $id): ?array
{
    $sql = mov_select_base()
        . " WHERE m.id = :movimiento_id
            LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':movimiento_id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $fila = $stmt->fetch();

    if (!$fila) {
        return null;
    }

    return mov_formatear_fila($fila, true);
}

function mov_select_base(): string
{
    return "SELECT
                m.id,
                m.tipo_usuario,
                m.usuario_id,
                m.accion,
                m.modulo,
                m.descripcion,
                m.tabla_afectada,
                m.registro_id,
                m.ip_address,
                m.user_agent,
                m.fecha_movimiento,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno)), ''),
                    NULLIF(TRIM(CONCAT_WS(' ', so.nombre, so.apellido_paterno, so.apellido_materno)), ''),
                    NULLIF(TRIM(CONCAT_WS(' ', te.nombre, te.apellido_paterno, te.apellido_materno)), ''),
                    CONCAT('Usuario #', m.usuario_id)
                ) AS nombre_usuario,
                COALESCE(ad.usuario, so.usuario, te.usuario, '') AS cuenta_usuario
            FROM movimientos_sistema m
            LEFT JOIN administradores ad
                   ON m.tipo_usuario = 'ADMIN'
                  AND ad.id = m.usuario_id
            LEFT JOIN solicitantes so
                   ON m.tipo_usuario = 'SOLICITANTE'
                  AND so.id = m.usuario_id
            LEFT JOIN tecnicos te
                   ON m.tipo_usuario = 'TECNICO'
                  AND te.id = m.usuario_id";
}

function mov_contar(PDO $conexion, string $where, array $parametros): int
{
    $sql = "SELECT COUNT(*)
            FROM movimientos_sistema m
            LEFT JOIN administradores ad
                   ON m.tipo_usuario = 'ADMIN'
                  AND ad.id = m.usuario_id
            LEFT JOIN solicitantes so
                   ON m.tipo_usuario = 'SOLICITANTE'
                  AND so.id = m.usuario_id
            LEFT JOIN tecnicos te
                   ON m.tipo_usuario = 'TECNICO'
                  AND te.id = m.usuario_id
            " . $where;

    $stmt = $conexion->prepare($sql);
    mov_enlazar_parametros($stmt, $parametros);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function mov_consultar_resumen(
    PDO $conexion,
    string $where,
    array $parametros
): array {
    $sql = "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(DATE(m.fecha_movimiento) = CURDATE()), 0) AS hoy,
                COUNT(DISTINCT CONCAT(m.tipo_usuario, ':', m.usuario_id)) AS usuarios,
                MAX(m.fecha_movimiento) AS ultimo_movimiento
            FROM movimientos_sistema m
            LEFT JOIN administradores ad
                   ON m.tipo_usuario = 'ADMIN'
                  AND ad.id = m.usuario_id
            LEFT JOIN solicitantes so
                   ON m.tipo_usuario = 'SOLICITANTE'
                  AND so.id = m.usuario_id
            LEFT JOIN tecnicos te
                   ON m.tipo_usuario = 'TECNICO'
                  AND te.id = m.usuario_id
            " . $where;

    $stmt = $conexion->prepare($sql);
    mov_enlazar_parametros($stmt, $parametros);
    $stmt->execute();

    $fila = $stmt->fetch() ?: [];
    $ultimo = (string) ($fila['ultimo_movimiento'] ?? '');

    return [
        'total' => (int) ($fila['total'] ?? 0),
        'hoy' => (int) ($fila['hoy'] ?? 0),
        'usuarios' => (int) ($fila['usuarios'] ?? 0),
        'ultimo_movimiento' => $ultimo,
        'ultimo_movimiento_texto' => $ultimo !== ''
            ? mov_fecha_hora_es($ultimo)
            : 'Sin movimientos',
    ];
}

function mov_consultar_catalogos(PDO $conexion): array
{
    $modulos = $conexion->query(
        "SELECT DISTINCT TRIM(modulo) AS valor
         FROM movimientos_sistema
         WHERE modulo IS NOT NULL
           AND TRIM(modulo) <> ''
         ORDER BY valor
         LIMIT 150"
    )->fetchAll(PDO::FETCH_COLUMN);

    return [
        'modulos' => array_values(array_map('strval', $modulos)),
        'tipos_usuario' => [
            ['valor' => 'ADMIN', 'texto' => 'Administrador'],
            ['valor' => 'SOLICITANTE', 'texto' => 'Solicitante'],
            ['valor' => 'TECNICO', 'texto' => 'Técnico'],
        ],
    ];
}

/* =========================================================================
   FILTROS
   ========================================================================= */

function mov_leer_filtros(): array
{
    $busqueda = mov_texto($_GET['busqueda'] ?? '');
    if (mov_longitud($busqueda) > 120) {
        $busqueda = mov_recortar($busqueda, 120);
    }

    $tipoUsuario = strtoupper(mov_texto($_GET['tipo_usuario'] ?? 'TODOS'));
    if (!in_array($tipoUsuario, ['TODOS', 'ADMIN', 'SOLICITANTE', 'TECNICO'], true)) {
        $tipoUsuario = 'TODOS';
    }

    $modulo = mov_texto($_GET['modulo'] ?? '');
    if (mov_longitud($modulo) > 100) {
        $modulo = mov_recortar($modulo, 100);
    }

    $fechaDesde = mov_fecha_opcional($_GET['fecha_desde'] ?? '');
    $fechaHasta = mov_fecha_opcional($_GET['fecha_hasta'] ?? '');

    if ($fechaDesde !== '' && $fechaHasta !== '' && $fechaDesde > $fechaHasta) {
        sm_responder_json(
            false,
            'La fecha inicial no puede ser posterior a la fecha final.',
            ['campo' => 'fecha_desde'],
            422
        );
    }

    $pagina = mov_entero_rango($_GET['pagina'] ?? 1, 1, 100000, 1);
    $porPagina = mov_entero_rango($_GET['por_pagina'] ?? 20, 10, 80, 20);

    if (!in_array($porPagina, [10, 20, 40, 80], true)) {
        $porPagina = 20;
    }

    return [
        'busqueda' => $busqueda,
        'tipo_usuario' => $tipoUsuario,
        'modulo' => $modulo,
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta,
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
    ];
}

function mov_construir_condiciones(array $filtros): array
{
    $condiciones = ['1 = 1'];
    $parametros = [];

    if ((string) $filtros['tipo_usuario'] !== 'TODOS') {
        $condiciones[] = 'm.tipo_usuario = :tipo_usuario';
        $parametros[':tipo_usuario'] = (string) $filtros['tipo_usuario'];
    }

    if ((string) $filtros['modulo'] !== '') {
        $condiciones[] = 'm.modulo = :modulo';
        $parametros[':modulo'] = (string) $filtros['modulo'];
    }

    if ((string) $filtros['fecha_desde'] !== '') {
        $condiciones[] = 'm.fecha_movimiento >= :fecha_desde';
        $parametros[':fecha_desde'] = (string) $filtros['fecha_desde'] . ' 00:00:00';
    }

    if ((string) $filtros['fecha_hasta'] !== '') {
        $condiciones[] = 'm.fecha_movimiento < DATE_ADD(:fecha_hasta, INTERVAL 1 DAY)';
        $parametros[':fecha_hasta'] = (string) $filtros['fecha_hasta'] . ' 00:00:00';
    }

    if ((string) $filtros['busqueda'] !== '') {
        $condiciones[] = "(
            m.accion LIKE :busqueda_accion
            OR m.modulo LIKE :busqueda_modulo
            OR m.descripcion LIKE :busqueda_descripcion
            OR m.tabla_afectada LIKE :busqueda_tabla
            OR CAST(m.registro_id AS CHAR) LIKE :busqueda_registro
            OR ad.usuario LIKE :busqueda_admin_usuario
            OR so.usuario LIKE :busqueda_solicitante_usuario
            OR te.usuario LIKE :busqueda_tecnico_usuario
            OR CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno) LIKE :busqueda_admin_nombre
            OR CONCAT_WS(' ', so.nombre, so.apellido_paterno, so.apellido_materno) LIKE :busqueda_solicitante_nombre
            OR CONCAT_WS(' ', te.nombre, te.apellido_paterno, te.apellido_materno) LIKE :busqueda_tecnico_nombre
        )";

        $patron = '%' . (string) $filtros['busqueda'] . '%';
        $parametros[':busqueda_accion'] = $patron;
        $parametros[':busqueda_modulo'] = $patron;
        $parametros[':busqueda_descripcion'] = $patron;
        $parametros[':busqueda_tabla'] = $patron;
        $parametros[':busqueda_registro'] = $patron;
        $parametros[':busqueda_admin_usuario'] = $patron;
        $parametros[':busqueda_solicitante_usuario'] = $patron;
        $parametros[':busqueda_tecnico_usuario'] = $patron;
        $parametros[':busqueda_admin_nombre'] = $patron;
        $parametros[':busqueda_solicitante_nombre'] = $patron;
        $parametros[':busqueda_tecnico_nombre'] = $patron;
    }

    return [
        'where' => 'WHERE ' . implode(' AND ', $condiciones),
        'parametros' => $parametros,
    ];
}

/* =========================================================================
   PRESENTACIÓN
   ========================================================================= */

function mov_formatear_fila(array $fila, bool $detalle): array
{
    $fila['id'] = (int) $fila['id'];
    $fila['usuario_id'] = (int) $fila['usuario_id'];
    $fila['registro_id'] = $fila['registro_id'] !== null
        ? (int) $fila['registro_id']
        : null;

    $fila['tipo_usuario_texto'] = mov_tipo_usuario_texto(
        (string) $fila['tipo_usuario']
    );
    $fila['accion_texto'] = mov_accion_texto((string) $fila['accion']);
    $fila['tabla_texto'] = mov_nombre_legible((string) ($fila['tabla_afectada'] ?? ''));
    $fila['fecha_texto'] = mov_fecha_hora_es((string) $fila['fecha_movimiento']);
    $fila['fecha_corta'] = mov_fecha_corta_es((string) $fila['fecha_movimiento']);
    $fila['hora_texto'] = mov_hora_es((string) $fila['fecha_movimiento']);
    $fila['afectado_texto'] = mov_afectado_texto($fila);
    $fila['descripcion'] = trim((string) ($fila['descripcion'] ?? ''));
    $fila['descripcion_corta'] = mov_recortar(
        $fila['descripcion'] !== '' ? $fila['descripcion'] : $fila['accion_texto'],
        180
    );

    if (!$detalle) {
        unset($fila['user_agent']);
    } else {
        $fila['navegador_texto'] = mov_navegador_texto(
            (string) ($fila['user_agent'] ?? '')
        );
    }

    return $fila;
}

function mov_tipo_usuario_texto(string $tipo): string
{
    $mapa = [
        'ADMIN' => 'Administrador',
        'SOLICITANTE' => 'Solicitante',
        'TECNICO' => 'Técnico',
    ];

    return $mapa[$tipo] ?? mov_nombre_legible($tipo);
}

function mov_accion_texto(string $accion): string
{
    $mapa = [
        'LOGIN' => 'Inició sesión',
        'LOGOUT' => 'Cerró sesión',
        'CREAR_SOLICITUD' => 'Registró una solicitud',
        'APROBAR_SOLICITUD' => 'Aprobó una solicitud',
        'RECHAZAR_SOLICITUD' => 'Rechazó una solicitud',
        'PROGRAMAR_MANTENIMIENTO' => 'Programó un mantenimiento',
        'REPROGRAMAR_MANTENIMIENTO' => 'Reprogramó un mantenimiento',
        'CANCELAR_MANTENIMIENTO' => 'Canceló un mantenimiento',
        'ASIGNAR_TECNICOS' => 'Asignó técnicos',
        'INICIAR_MANTENIMIENTO' => 'Inició un mantenimiento',
        'PAUSAR_MANTENIMIENTO' => 'Pausó un mantenimiento',
        'REANUDAR_MANTENIMIENTO' => 'Reanudó un mantenimiento',
        'FINALIZAR_MANTENIMIENTO' => 'Finalizó un mantenimiento',
        'EDITAR_TIEMPOS_EJECUCION' => 'Corrigió tiempos de ejecución',
        'JUSTIFICAR_INCUMPLIMIENTO' => 'Justificó un incumplimiento',
        'MARCAR_NO_REALIZADO' => 'Marcó una participación no realizada',
        'CREAR_RUTINA' => 'Creó una rutina',
        'EDITAR_RUTINA' => 'Editó una rutina',
        'ACTIVAR_RUTINA' => 'Activó una rutina',
        'DESACTIVAR_RUTINA' => 'Desactivó una rutina',
        'CONFIGURAR_DIA_LABORAL' => 'Configuró un día laboral',
    ];

    return $mapa[$accion] ?? mov_nombre_legible($accion);
}

function mov_afectado_texto(array $fila): string
{
    $tabla = trim((string) ($fila['tabla_texto'] ?? ''));
    $id = $fila['registro_id'] ?? null;

    if ($tabla === '' && $id === null) {
        return 'Sin registro específico';
    }

    if ($tabla === '') {
        return 'Registro #' . (int) $id;
    }

    if ($id === null) {
        return $tabla;
    }

    return $tabla . ' · #' . (int) $id;
}

function mov_nombre_legible(string $valor): string
{
    $valor = trim(str_replace(['_', '-'], ' ', $valor));

    if ($valor === '') {
        return '';
    }

    return mov_mayuscula_inicial(mb_strtolower($valor, 'UTF-8'));
}

function mov_navegador_texto(string $userAgent): string
{
    $userAgent = trim($userAgent);

    if ($userAgent === '') {
        return 'No registrado';
    }

    $navegador = 'Navegador';
    if (stripos($userAgent, 'Edg/') !== false) {
        $navegador = 'Microsoft Edge';
    } elseif (stripos($userAgent, 'OPR/') !== false || stripos($userAgent, 'Opera') !== false) {
        $navegador = 'Opera';
    } elseif (stripos($userAgent, 'Chrome/') !== false) {
        $navegador = 'Google Chrome';
    } elseif (stripos($userAgent, 'Firefox/') !== false) {
        $navegador = 'Mozilla Firefox';
    } elseif (stripos($userAgent, 'Safari/') !== false) {
        $navegador = 'Safari';
    }

    $dispositivo = 'computadora';
    if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent)) {
        $dispositivo = 'dispositivo móvil';
    }

    return $navegador . ' en ' . $dispositivo;
}

/* =========================================================================
   VALIDACIONES Y UTILIDADES
   ========================================================================= */

function mov_admin_id(): int
{
    $id = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || (int) $id < 1) {
        sm_responder_json(false, 'La sesión administrativa no es válida.', [], 401);
    }

    return (int) $id;
}

function mov_validar_admin_activo(PDO $conexion, int $adminId): void
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
            ['sesion_expirada' => true, 'redirect' => '../login.php?acceso=denegado'],
            403
        );
    }
}

function mov_texto($valor): string
{
    if (!is_scalar($valor)) {
        return '';
    }

    $texto = trim((string) $valor);
    $limpio = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto);

    return $limpio === null ? '' : $limpio;
}

function mov_entero_positivo($valor, string $campo): int
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

function mov_entero_rango($valor, int $minimo, int $maximo, int $predeterminado): int
{
    $entero = filter_var($valor, FILTER_VALIDATE_INT);

    if ($entero === false) {
        return $predeterminado;
    }

    $entero = (int) $entero;

    return $entero >= $minimo && $entero <= $maximo
        ? $entero
        : $predeterminado;
}

function mov_fecha_opcional($valor): string
{
    $fecha = mov_texto($valor);

    if ($fecha === '') {
        return '';
    }

    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    $errores = DateTimeImmutable::getLastErrors();

    $valida = $objeto instanceof DateTimeImmutable
        && $objeto->format('Y-m-d') === $fecha
        && ($errores === false || (
            (int) $errores['warning_count'] === 0
            && (int) $errores['error_count'] === 0
        ));

    if (!$valida) {
        sm_responder_json(
            false,
            'Selecciona una fecha válida.',
            ['campo' => 'fecha'],
            422
        );
    }

    return $fecha;
}

function mov_enlazar_parametros(PDOStatement $stmt, array $parametros): void
{
    foreach ($parametros as $clave => $valor) {
        $stmt->bindValue((string) $clave, $valor, PDO::PARAM_STR);
    }
}

function mov_longitud(string $texto): int
{
    return mb_strlen($texto, 'UTF-8');
}

function mov_recortar(string $texto, int $maximo): string
{
    if (mov_longitud($texto) <= $maximo) {
        return $texto;
    }

    return rtrim(mb_substr($texto, 0, max(1, $maximo - 1), 'UTF-8')) . '…';
}

function mov_mayuscula_inicial(string $texto): string
{
    if ($texto === '') {
        return '';
    }

    return mb_strtoupper(mb_substr($texto, 0, 1, 'UTF-8'), 'UTF-8')
        . mb_substr($texto, 1, null, 'UTF-8');
}

function mov_fecha_hora_es(string $fecha): string
{
    try {
        $objeto = new DateTimeImmutable($fecha);
    } catch (Throwable $e) {
        return $fecha !== '' ? $fecha : 'Sin fecha';
    }

    $meses = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];

    return (int) $objeto->format('j')
        . ' ' . $meses[(int) $objeto->format('n')]
        . ' ' . $objeto->format('Y')
        . ', ' . $objeto->format('H:i');
} 

function mov_fecha_corta_es(string $fecha): string
{
    try {
        return (new DateTimeImmutable($fecha))->format('d/m/Y');
    } catch (Throwable $e) {
        return $fecha;
    }
}

function mov_hora_es(string $fecha): string
{
    try {
        return (new DateTimeImmutable($fecha))->format('H:i:s');
    } catch (Throwable $e) {
        return '';
    }
}