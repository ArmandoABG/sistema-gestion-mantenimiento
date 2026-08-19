<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Servicio central de herramientas y refacciones
|--------------------------------------------------------------------------
| Este archivo concentra las consultas y validaciones que serán reutilizadas
| por catálogo, rutinas, programación, cierres e historiales.
| Compatible con PHP 7.4 o superior.
|--------------------------------------------------------------------------
*/

if (
    isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__
) {
    http_response_code(404);
    exit;
}

const RSM_TIPO_HERRAMIENTA = 'HERRAMIENTA';
const RSM_TIPO_REFACCION = 'REFACCION';

/**
 * @return string[]
 */
function rsm_tipos_validos(): array
{
    return [RSM_TIPO_HERRAMIENTA, RSM_TIPO_REFACCION];
}

/** Prefijo oficial para los códigos automáticos. */
function rsm_prefijo_codigo(string $tipo): string
{
    return $tipo === RSM_TIPO_REFACCION ? 'REF' : 'HER';
}

/** Clave interna de la secuencia independiente por tipo. */
function rsm_clave_secuencia_codigo(string $tipo): string
{
    return $tipo === RSM_TIPO_REFACCION
        ? 'SECUENCIA_RECURSO_REFACCION'
        : 'SECUENCIA_RECURSO_HERRAMIENTA';
}

/**
 * Reserva el siguiente código dentro de una transacción. La fila de
 * configuración se bloquea para impedir duplicados por concurrencia.
 */
function rsm_generar_codigo_automatico(PDO $conexion, string $tipo): string
{
    $tipo = rsm_validar_tipo($tipo);
    $clave = rsm_clave_secuencia_codigo($tipo);
    $prefijo = rsm_prefijo_codigo($tipo);

    $stmtCrear = $conexion->prepare(
        "INSERT IGNORE INTO configuracion_sistema
        (clave, valor, descripcion, tipo_valor, editable, fecha_actualizacion)
        VALUES (:clave, '0', :descripcion, 'ENTERO', 0, NOW())"
    );
    $stmtCrear->bindValue(':clave', $clave, PDO::PARAM_STR);
    $stmtCrear->bindValue(
        ':descripcion',
        'Consecutivo automático del catálogo de ' .
            ($tipo === RSM_TIPO_REFACCION ? 'refacciones.' : 'herramientas.'),
        PDO::PARAM_STR
    );
    $stmtCrear->execute();

    $stmtBloquear = $conexion->prepare(
        "SELECT valor
         FROM configuracion_sistema
         WHERE clave = :clave
         LIMIT 1
         FOR UPDATE"
    );
    $stmtBloquear->bindValue(':clave', $clave, PDO::PARAM_STR);
    $stmtBloquear->execute();
    $actual = (int) ($stmtBloquear->fetchColumn() ?: 0);

    $siguiente = $actual + 1;

    do {
        $codigo = $prefijo . '-' . str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT);
        $stmtExiste = $conexion->prepare(
            "SELECT COUNT(*)
             FROM recursos_mantenimiento
             WHERE tipo_recurso = :tipo
               AND codigo = :codigo"
        );
        $stmtExiste->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmtExiste->bindValue(':codigo', $codigo, PDO::PARAM_STR);
        $stmtExiste->execute();

        if ((int) $stmtExiste->fetchColumn() === 0) {
            break;
        }
        $siguiente++;
    } while (true);

    $stmtActualizar = $conexion->prepare(
        "UPDATE configuracion_sistema
         SET valor = :valor,
             fecha_actualizacion = NOW()
         WHERE clave = :clave"
    );
    $stmtActualizar->bindValue(':valor', (string) $siguiente, PDO::PARAM_STR);
    $stmtActualizar->bindValue(':clave', $clave, PDO::PARAM_STR);
    $stmtActualizar->execute();

    return $codigo;
}

/** Vista previa; el consecutivo definitivo se reserva al guardar. */
function rsm_previsualizar_siguiente_codigo(PDO $conexion, string $tipo): string
{
    $tipo = rsm_validar_tipo($tipo);
    $clave = rsm_clave_secuencia_codigo($tipo);
    $stmt = $conexion->prepare(
        "SELECT CAST(valor AS UNSIGNED)
         FROM configuracion_sistema
         WHERE clave = :clave
         LIMIT 1"
    );
    $stmt->bindValue(':clave', $clave, PDO::PARAM_STR);
    $stmt->execute();
    $contador = (int) ($stmt->fetchColumn() ?: 0);
    $siguiente = $contador + 1;
    $prefijo = rsm_prefijo_codigo($tipo);

    do {
        $codigo = $prefijo . '-' . str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT);
        if (!rsm_codigo_existe($conexion, $tipo, $codigo, 0)) {
            return $codigo;
        }
        $siguiente++;
    } while (true);
}

/**
 * Completa recursos antiguos sin código. Debe ejecutarse dentro de una
 * transacción.
 *
 * @return array{actualizados:int,herramientas:int,refacciones:int}
 */
function rsm_completar_codigos_faltantes(PDO $conexion, int $adminId): array
{
    $stmt = $conexion->query(
        "SELECT id, tipo_recurso
         FROM recursos_mantenimiento
         WHERE codigo IS NULL OR TRIM(codigo) = ''
         ORDER BY tipo_recurso, id
         FOR UPDATE"
    );
    $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $resultado = ['actualizados' => 0, 'herramientas' => 0, 'refacciones' => 0];

    if ($pendientes === []) {
        return $resultado;
    }

    $stmtActualizar = $conexion->prepare(
        "UPDATE recursos_mantenimiento
         SET codigo = :codigo,
             modificado_por_admin_id = :admin_id,
             fecha_actualizacion = NOW()
         WHERE id = :id
           AND (codigo IS NULL OR TRIM(codigo) = '')"
    );

    foreach ($pendientes as $recurso) {
        $tipo = (string) $recurso['tipo_recurso'];
        $codigo = rsm_generar_codigo_automatico($conexion, $tipo);
        $stmtActualizar->bindValue(':codigo', $codigo, PDO::PARAM_STR);
        $stmtActualizar->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmtActualizar->bindValue(':id', (int) $recurso['id'], PDO::PARAM_INT);
        $stmtActualizar->execute();

        if ($stmtActualizar->rowCount() === 1) {
            $resultado['actualizados']++;
            $tipo === RSM_TIPO_REFACCION
                ? $resultado['refacciones']++
                : $resultado['herramientas']++;
        }
    }

    return $resultado;
}

function rsm_verificar_estructura(PDO $conexion): void
{
    $stmt = $conexion->prepare(
        "SELECT COUNT(*)
         FROM configuracion_sistema
         WHERE clave = 'version_recursos_mantenimiento'
           AND CAST(valor AS UNSIGNED) >= 1"
    );
    $stmt->execute();

    if ((int) $stmt->fetchColumn() !== 1) {
        sm_responder_json(
            false,
            'La estructura de herramientas y refacciones todavía no está instalada o no está completa.',
            ['requiere_migracion' => true],
            503
        );
    }
}

function rsm_validar_admin_activo(PDO $conexion, int $adminId): void
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
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?sesion=expirada',
            ],
            401
        );
    }
}

function rsm_admin_id(): int
{
    $id = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || (int) $id < 1) {
        sm_responder_json(false, 'Tu sesión administrativa no es válida.', [], 401);
    }

    return (int) $id;
}

function rsm_entero_positivo($valor, string $campo): int
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

function rsm_texto($valor): string
{
    return sm_limpiar_texto($valor);
}

function rsm_normalizar_espacios(string $texto): string
{
    $texto = preg_replace('/[\t ]+/u', ' ', $texto) ?? $texto;
    return trim($texto);
}

function rsm_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? (int) mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function rsm_recortar(string $texto, int $limite): string
{
    if (rsm_longitud($texto) <= $limite) {
        return $texto;
    }

    return function_exists('mb_substr')
        ? (string) mb_substr($texto, 0, $limite, 'UTF-8')
        : substr($texto, 0, $limite);
}

function rsm_mayusculas(string $texto): string
{
    return function_exists('mb_strtoupper')
        ? mb_strtoupper($texto, 'UTF-8')
        : strtoupper($texto);
}

function rsm_validar_tipo($valor): string
{
    $tipo = rsm_mayusculas(rsm_texto($valor));

    if (!in_array($tipo, rsm_tipos_validos(), true)) {
        sm_responder_json(
            false,
            'Selecciona si el recurso es una herramienta o una refacción.',
            ['campo' => 'tipo_recurso'],
            422
        );
    }

    return $tipo;
}

function rsm_validar_nombre($valor): string
{
    $nombre = rsm_normalizar_espacios(rsm_texto($valor));
    $longitud = rsm_longitud($nombre);

    if ($longitud < 2 || $longitud > 150) {
        sm_responder_json(
            false,
            'El nombre debe contener entre 2 y 150 caracteres.',
            ['campo' => 'nombre'],
            422
        );
    }

    if (
        !preg_match('/[\p{L}\p{N}]/u', $nombre)
        || preg_match('/[<>\r\n]/u', $nombre)
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

function rsm_validar_codigo($valor): ?string
{
    $codigo = rsm_normalizar_espacios(rsm_texto($valor));

    if ($codigo === '') {
        return null;
    }

    if (rsm_longitud($codigo) > 60) {
        sm_responder_json(
            false,
            'El código no puede superar los 60 caracteres.',
            ['campo' => 'codigo'],
            422
        );
    }

    if (
        !preg_match('/[\p{L}\p{N}]/u', $codigo)
        || !preg_match('/^[\p{L}\p{N} ._\-\/#]+$/u', $codigo)
    ) {
        sm_responder_json(
            false,
            'El código solo puede contener letras, números, espacios, puntos, guiones, diagonales, numeral y guion bajo.',
            ['campo' => 'codigo'],
            422
        );
    }

    return rsm_mayusculas($codigo);
}

function rsm_validar_descripcion($valor): ?string
{
    $descripcion = rsm_texto($valor);
    $descripcion = str_replace(["\r\n", "\r"], "\n", $descripcion);
    $descripcion = trim($descripcion);

    if ($descripcion === '') {
        return null;
    }

    if (rsm_longitud($descripcion) > 500) {
        sm_responder_json(
            false,
            'La descripción no puede superar los 500 caracteres.',
            ['campo' => 'descripcion'],
            422
        );
    }

    return $descripcion;
}

function rsm_validar_estado($valor): int
{
    $estado = rsm_texto($valor);

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

/**
 * @return array<int, array<string, mixed>>
 */
function rsm_listar_recursos(PDO $conexion): array
{
    $sql = "SELECT
                r.id,
                r.tipo_recurso,
                r.nombre,
                r.codigo,
                r.descripcion,
                r.activo,
                r.creado_por_admin_id,
                r.modificado_por_admin_id,
                r.fecha_registro,
                r.fecha_actualizacion,
                DATE_FORMAT(r.fecha_registro, '%d/%m/%Y') AS fecha_registro_texto,
                DATE_FORMAT(r.fecha_actualizacion, '%d/%m/%Y %H:%i') AS fecha_actualizacion_texto,
                TRIM(CONCAT_WS(' ', ac.nombre, ac.apellido_paterno, ac.apellido_materno)) AS creado_por,
                TRIM(CONCAT_WS(' ', am.nombre, am.apellido_paterno, am.apellido_materno)) AS modificado_por,
                (SELECT COUNT(*) FROM recomendaciones_recursos rr
                 WHERE rr.recurso_id = r.id) AS usos_recomendaciones,
                (SELECT COUNT(*) FROM solicitud_recursos_recomendados srr
                 WHERE srr.recurso_id = r.id) AS usos_solicitudes,
                (SELECT COUNT(*) FROM rutina_recursos rur
                 WHERE rur.recurso_id = r.id) AS usos_rutinas,
                (SELECT COUNT(*) FROM cierre_recursos_utilizados cru
                 WHERE cru.recurso_id = r.id) AS usos_cierres,
                (SELECT COUNT(*) FROM sugerencias_recursos sr
                 WHERE sr.recurso_creado_id = r.id) AS usos_sugerencias
            FROM recursos_mantenimiento r
            LEFT JOIN administradores ac ON ac.id = r.creado_por_admin_id
            LEFT JOIN administradores am ON am.id = r.modificado_por_admin_id
            ORDER BY r.activo DESC, r.tipo_recurso ASC, r.nombre ASC, r.id ASC";

    $recursos = $conexion->query($sql)->fetchAll();

    foreach ($recursos as &$recurso) {
        $recurso = rsm_convertir_recurso($recurso);
    }
    unset($recurso);

    return $recursos;
}

/**
 * Buscador ligero para los módulos que posteriormente seleccionarán recursos.
 *
 * @return array<int, array<string, mixed>>
 */
function rsm_buscar_recursos_activos(
    PDO $conexion,
    ?string $tipo,
    string $busqueda,
    int $limite = 30
): array {
    $limite = max(1, min(50, $limite));
    $parametros = [];
    $condiciones = ['r.activo = 1'];

    if ($tipo !== null && $tipo !== '') {
        $tipo = rsm_validar_tipo($tipo);
        $condiciones[] = 'r.tipo_recurso = :tipo';
        $parametros[':tipo'] = $tipo;
    }

    $busqueda = rsm_normalizar_espacios(rsm_texto($busqueda));

    if ($busqueda !== '') {
        /*
         * Con preparaciones nativas de PDO MySQL no debe reutilizarse el
         * mismo marcador con nombre más de una vez dentro de la consulta.
         * Se usan tres marcadores independientes para que el buscador
         * funcione por nombre, código y descripción sin producir HY093.
         */
        $condiciones[] = "(
            r.nombre LIKE :busqueda_nombre
            OR COALESCE(r.codigo, '') LIKE :busqueda_codigo
            OR COALESCE(r.descripcion, '') LIKE :busqueda_descripcion
        )";

        $patronBusqueda = '%' . $busqueda . '%';
        $parametros[':busqueda_nombre'] = $patronBusqueda;
        $parametros[':busqueda_codigo'] = $patronBusqueda;
        $parametros[':busqueda_descripcion'] = $patronBusqueda;
    }

    $sql = "SELECT
                r.id,
                r.tipo_recurso,
                r.nombre,
                r.codigo,
                r.descripcion,
                r.activo
            FROM recursos_mantenimiento r
            WHERE " . implode(' AND ', $condiciones) . "
            ORDER BY r.nombre ASC, r.id ASC
            LIMIT :limite";

    $stmt = $conexion->prepare($sql);

    foreach ($parametros as $parametro => $valor) {
        $stmt->bindValue($parametro, $valor, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();

    $recursos = $stmt->fetchAll();

    foreach ($recursos as &$recurso) {
        $recurso['id'] = (int) $recurso['id'];
        $recurso['activo'] = (int) $recurso['activo'];
    }
    unset($recurso);

    return $recursos;
}

function rsm_obtener_recurso(PDO $conexion, int $id, bool $forUpdate = false): ?array
{
    $sql = "SELECT
                r.id,
                r.tipo_recurso,
                r.nombre,
                r.codigo,
                r.descripcion,
                r.activo,
                r.creado_por_admin_id,
                r.modificado_por_admin_id,
                r.fecha_registro,
                r.fecha_actualizacion,
                DATE_FORMAT(r.fecha_registro, '%d/%m/%Y') AS fecha_registro_texto,
                DATE_FORMAT(r.fecha_actualizacion, '%d/%m/%Y %H:%i') AS fecha_actualizacion_texto,
                TRIM(CONCAT_WS(' ', ac.nombre, ac.apellido_paterno, ac.apellido_materno)) AS creado_por,
                TRIM(CONCAT_WS(' ', am.nombre, am.apellido_paterno, am.apellido_materno)) AS modificado_por,
                (SELECT COUNT(*) FROM recomendaciones_recursos rr
                 WHERE rr.recurso_id = r.id) AS usos_recomendaciones,
                (SELECT COUNT(*) FROM solicitud_recursos_recomendados srr
                 WHERE srr.recurso_id = r.id) AS usos_solicitudes,
                (SELECT COUNT(*) FROM rutina_recursos rur
                 WHERE rur.recurso_id = r.id) AS usos_rutinas,
                (SELECT COUNT(*) FROM cierre_recursos_utilizados cru
                 WHERE cru.recurso_id = r.id) AS usos_cierres,
                (SELECT COUNT(*) FROM sugerencias_recursos sr
                 WHERE sr.recurso_creado_id = r.id) AS usos_sugerencias
            FROM recursos_mantenimiento r
            LEFT JOIN administradores ac ON ac.id = r.creado_por_admin_id
            LEFT JOIN administradores am ON am.id = r.modificado_por_admin_id
            WHERE r.id = :id
            LIMIT 1";

    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $recurso = $stmt->fetch();

    return is_array($recurso) ? rsm_convertir_recurso($recurso) : null;
}

function rsm_convertir_recurso(array $recurso): array
{
    $camposEnteros = [
        'id',
        'activo',
        'creado_por_admin_id',
        'modificado_por_admin_id',
        'usos_recomendaciones',
        'usos_solicitudes',
        'usos_rutinas',
        'usos_cierres',
        'usos_sugerencias',
    ];

    foreach ($camposEnteros as $campo) {
        if (array_key_exists($campo, $recurso)) {
            $recurso[$campo] = $recurso[$campo] === null
                ? null
                : (int) $recurso[$campo];
        }
    }

    $recurso['total_usos'] =
        (int) ($recurso['usos_recomendaciones'] ?? 0)
        + (int) ($recurso['usos_solicitudes'] ?? 0)
        + (int) ($recurso['usos_rutinas'] ?? 0)
        + (int) ($recurso['usos_cierres'] ?? 0)
        + (int) ($recurso['usos_sugerencias'] ?? 0);

    return $recurso;
}

function rsm_nombre_existe(
    PDO $conexion,
    string $tipo,
    string $nombre,
    int $excluirId = 0
): bool {
    $sql = "SELECT COUNT(*)
            FROM recursos_mantenimiento
            WHERE tipo_recurso = :tipo
              AND nombre = :nombre";

    if ($excluirId > 0) {
        $sql .= ' AND id <> :id';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);

    if ($excluirId > 0) {
        $stmt->bindValue(':id', $excluirId, PDO::PARAM_INT);
    }

    $stmt->execute();
    return (int) $stmt->fetchColumn() > 0;
}

function rsm_codigo_existe(
    PDO $conexion,
    string $tipo,
    ?string $codigo,
    int $excluirId = 0
): bool {
    if ($codigo === null) {
        return false;
    }

    $sql = "SELECT COUNT(*)
            FROM recursos_mantenimiento
            WHERE tipo_recurso = :tipo
              AND codigo = :codigo";

    if ($excluirId > 0) {
        $sql .= ' AND id <> :id';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
    $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);

    if ($excluirId > 0) {
        $stmt->bindValue(':id', $excluirId, PDO::PARAM_INT);
    }

    $stmt->execute();
    return (int) $stmt->fetchColumn() > 0;
}

function rsm_resumen(array $recursos): array
{
    $resumen = [
        'total' => 0,
        'herramientas' => 0,
        'refacciones' => 0,
        'activos' => 0,
        'inactivos' => 0,
        'en_uso' => 0,
    ];

    foreach ($recursos as $recurso) {
        $resumen['total']++;

        if (($recurso['tipo_recurso'] ?? '') === RSM_TIPO_HERRAMIENTA) {
            $resumen['herramientas']++;
        } elseif (($recurso['tipo_recurso'] ?? '') === RSM_TIPO_REFACCION) {
            $resumen['refacciones']++;
        }

        if ((int) ($recurso['activo'] ?? 0) === 1) {
            $resumen['activos']++;
        } else {
            $resumen['inactivos']++;
        }

        if ((int) ($recurso['total_usos'] ?? 0) > 0) {
            $resumen['en_uso']++;
        }
    }

    return $resumen;
}

function rsm_datos_auditoria(array $recurso): array
{
    return [
        'id' => (int) ($recurso['id'] ?? 0),
        'tipo_recurso' => (string) ($recurso['tipo_recurso'] ?? ''),
        'nombre' => (string) ($recurso['nombre'] ?? ''),
        'codigo' => $recurso['codigo'] ?? null,
        'descripcion' => $recurso['descripcion'] ?? null,
        'activo' => (int) ($recurso['activo'] ?? 0),
    ];
}

function rsm_registrar_movimiento(
    PDO $conexion,
    int $adminId,
    string $accion,
    string $descripcion,
    ?int $registroId
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
            'ADMIN', :usuario_id, :accion, 'Herramientas y refacciones', :descripcion,
            'recursos_mantenimiento', :registro_id, :ip_address, :user_agent,
            NOW()
        )"
    );
    $stmt->bindValue(':usuario_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', rsm_recortar($accion, 100), PDO::PARAM_STR);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(
        ':registro_id',
        $registroId,
        $registroId === null ? PDO::PARAM_NULL : PDO::PARAM_INT
    );
    rsm_bind_nullable($stmt, ':ip_address', rsm_ip());
    rsm_bind_nullable($stmt, ':user_agent', rsm_recortar_nullable(rsm_user_agent(), 255));
    $stmt->execute();
}

function rsm_registrar_auditoria(
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
            'recursos_mantenimiento', :registro_id, NULL, 'ADMIN',
            :actor_id, :accion, :motivo, :anteriores, :nuevos,
            :ip_address, :user_agent, NOW()
        )"
    );
    $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
    $stmt->bindValue(':actor_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
    $stmt->bindValue(':motivo', rsm_recortar($motivo, 500), PDO::PARAM_STR);
    rsm_bind_nullable($stmt, ':anteriores', rsm_json($anteriores));
    rsm_bind_nullable($stmt, ':nuevos', rsm_json($nuevos));
    rsm_bind_nullable($stmt, ':ip_address', rsm_ip());
    rsm_bind_nullable($stmt, ':user_agent', rsm_recortar_nullable(rsm_user_agent(), 500));
    $stmt->execute();
}

function rsm_bind_nullable(PDOStatement $stmt, string $parametro, ?string $valor): void
{
    $stmt->bindValue(
        $parametro,
        $valor,
        $valor === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );
}

function rsm_json(?array $datos): ?string
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

function rsm_ip(): ?string
{
    $ip = rsm_texto($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip === '' ? null : rsm_recortar($ip, 45);
}

function rsm_user_agent(): ?string
{
    $agente = rsm_texto($_SERVER['HTTP_USER_AGENT'] ?? '');
    return $agente === '' ? null : $agente;
}

function rsm_recortar_nullable(?string $texto, int $limite): ?string
{
    if ($texto === null || trim($texto) === '') {
        return null;
    }

    return rsm_recortar($texto, $limite);
}

function rsm_responder_cancelando(
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

/* =========================================================================
   RECURSOS RECOMENDADOS PARA SOLICITUDES Y VISTAS DEL TÉCNICO
   ========================================================================= */

/**
 * Estructura vacía estable para que todas las interfaces reciban las mismas
 * claves, incluso cuando una urgencia todavía no tiene antecedentes.
 *
 * @return array<string, mixed>
 */
function rsm_recursos_recomendados_vacios(): array
{
    return [
        'herramientas' => [],
        'refacciones' => [],
        'total_herramientas' => 0,
        'total_refacciones' => 0,
        'total' => 0,
        'tiene_recomendaciones' => 0,
        'fuente' => 'NINGUNA',
        'mensaje_vacio' => 'No existen recomendaciones registradas para este mantenimiento.',
    ];
}

/**
 * @param array<string, mixed> $fila
 * @return array<string, mixed>|null
 */
function rsm_normalizar_recurso_recomendado(array $fila): ?array
{
    $nombre = trim((string) ($fila['nombre'] ?? ''));

    if ($nombre === '') {
        return null;
    }

    $tipo = strtoupper(trim((string) ($fila['tipo_recurso'] ?? '')));

    if (!in_array($tipo, rsm_tipos_validos(), true)) {
        return null;
    }

    $recursoId = isset($fila['recurso_id']) && $fila['recurso_id'] !== null
        ? (int) $fila['recurso_id']
        : 0;

    return [
        'id' => $recursoId,
        'tipo_recurso' => $tipo,
        'nombre' => $nombre,
        'codigo' => isset($fila['codigo']) && trim((string) $fila['codigo']) !== ''
            ? trim((string) $fila['codigo'])
            : null,
        'descripcion' => isset($fila['descripcion']) && trim((string) $fila['descripcion']) !== ''
            ? trim((string) $fila['descripcion'])
            : null,
        'activo' => (int) ($fila['activo'] ?? 1),
        'origen' => strtoupper(trim((string) ($fila['origen'] ?? 'ADMIN'))),
        'es_catalogado' => $recursoId > 0 ? 1 : 0,
    ];
}

/**
 * Agrega un recurso a una estructura agrupada evitando duplicados.
 *
 * @param array<string, mixed> $grupo
 * @param array<string, mixed> $recurso
 */
function rsm_agregar_recurso_recomendado(array &$grupo, array $recurso): void
{
    $tipo = (string) ($recurso['tipo_recurso'] ?? '');
    $destino = $tipo === RSM_TIPO_HERRAMIENTA
        ? 'herramientas'
        : 'refacciones';

    $clave = (int) ($recurso['id'] ?? 0) > 0
        ? 'ID:' . (int) $recurso['id']
        : 'NOMBRE:' . rsm_mayusculas(trim((string) ($recurso['nombre'] ?? '')));

    if (!isset($grupo['_claves'])) {
        $grupo['_claves'] = [];
    }

    if (isset($grupo['_claves'][$destino][$clave])) {
        return;
    }

    $grupo['_claves'][$destino][$clave] = true;
    $grupo[$destino][] = $recurso;
}

/**
 * @param array<string, mixed> $grupo
 * @return array<string, mixed>
 */
function rsm_finalizar_grupo_recomendado(array $grupo): array
{
    unset($grupo['_claves']);

    $grupo['total_herramientas'] = count($grupo['herramientas'] ?? []);
    $grupo['total_refacciones'] = count($grupo['refacciones'] ?? []);
    $grupo['total'] = $grupo['total_herramientas'] + $grupo['total_refacciones'];
    $grupo['tiene_recomendaciones'] = $grupo['total'] > 0 ? 1 : 0;

    if ($grupo['total'] > 0) {
        $grupo['mensaje_vacio'] = '';
    }

    return $grupo;
}

/**
 * Carga en una sola operación las fotografías de recursos de varias
 * solicitudes. Para urgencias antiguas sin fotografía puede usar como
 * respaldo la memoria vigente del equipo, sin modificar la base de datos.
 *
 * Cada elemento de $solicitudes debe contener solicitud_id, equipo_id y
 * tipo_solicitud.
 *
 * @param array<int, array<string, mixed>> $solicitudes
 * @return array<int, array<string, mixed>> mapa por solicitud_id
 */
function rsm_mapear_recursos_recomendados_solicitudes(
    PDO $conexion,
    array $solicitudes,
    bool $usarMemoriaUrgente = false
): array {
    $metadatos = [];

    foreach ($solicitudes as $solicitud) {
        $solicitudId = (int) ($solicitud['solicitud_id'] ?? 0);

        if ($solicitudId < 1) {
            continue;
        }

        $metadatos[$solicitudId] = [
            'solicitud_id' => $solicitudId,
            'equipo_id' => (int) ($solicitud['equipo_id'] ?? 0),
            'tipo_solicitud' => strtoupper(trim((string) ($solicitud['tipo_solicitud'] ?? ''))),
        ];
    }

    if ($metadatos === []) {
        return [];
    }

    $mapa = [];
    $parametros = [];
    $marcadores = [];
    $indice = 0;

    foreach (array_keys($metadatos) as $solicitudId) {
        $parametro = ':solicitud_' . $indice++;
        $marcadores[] = $parametro;
        $parametros[$parametro] = $solicitudId;
        $mapa[$solicitudId] = rsm_recursos_recomendados_vacios();
    }

    $sql = "SELECT
                srr.solicitud_id,
                srr.tipo_recurso,
                srr.recurso_id,
                COALESCE(r.nombre, srr.nombre_no_catalogado) AS nombre,
                r.codigo,
                r.descripcion,
                COALESCE(r.activo, 1) AS activo,
                srr.origen
            FROM solicitud_recursos_recomendados srr
            LEFT JOIN recursos_mantenimiento r
                   ON r.id = srr.recurso_id
            WHERE srr.solicitud_id IN (" . implode(', ', $marcadores) . ")
            ORDER BY srr.solicitud_id, srr.tipo_recurso, nombre, srr.id";

    $stmt = $conexion->prepare($sql);

    foreach ($parametros as $parametro => $valor) {
        $stmt->bindValue($parametro, $valor, PDO::PARAM_INT);
    }

    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($filas as $fila) {
        $solicitudId = (int) ($fila['solicitud_id'] ?? 0);
        $recurso = rsm_normalizar_recurso_recomendado($fila);

        if ($solicitudId < 1 || $recurso === null || !isset($mapa[$solicitudId])) {
            continue;
        }

        $mapa[$solicitudId]['fuente'] = 'SOLICITUD';
        rsm_agregar_recurso_recomendado($mapa[$solicitudId], $recurso);
    }

    if ($usarMemoriaUrgente) {
        $equiposPendientes = [];

        foreach ($metadatos as $solicitudId => $meta) {
            $totalTemporal = count($mapa[$solicitudId]['herramientas'] ?? [])
                + count($mapa[$solicitudId]['refacciones'] ?? []);

            if (
                $totalTemporal === 0
                && (string) $meta['tipo_solicitud'] === 'CORRECTIVO_URGENTE'
                && (int) $meta['equipo_id'] > 0
            ) {
                $equiposPendientes[(int) $meta['equipo_id']] = true;
            }
        }

        if ($equiposPendientes !== []) {
            $marcadoresEquipo = [];
            $parametrosEquipo = [];
            $indiceEquipo = 0;

            foreach (array_keys($equiposPendientes) as $equipoId) {
                $parametro = ':equipo_' . $indiceEquipo++;
                $marcadoresEquipo[] = $parametro;
                $parametrosEquipo[$parametro] = $equipoId;
            }

            $sqlMemoria = "SELECT
                    rr.equipo_id,
                    rr.tipo_recurso,
                    rr.recurso_id,
                    COALESCE(r.nombre, rr.nombre_no_catalogado) AS nombre,
                    r.codigo,
                    r.descripcion,
                    COALESCE(r.activo, 1) AS activo,
                    CASE
                        WHEN rr.origen_ultima_actualizacion = 'CIERRE_TECNICO'
                            THEN 'CIERRE_URGENTE'
                        ELSE 'MEMORIA'
                    END AS origen
                FROM recomendaciones_recursos rr
                LEFT JOIN recursos_mantenimiento r
                       ON r.id = rr.recurso_id
                WHERE rr.tipo_solicitud = 'CORRECTIVO_URGENTE'
                  AND rr.equipo_id IN (" . implode(', ', $marcadoresEquipo) . ")
                ORDER BY rr.equipo_id, rr.tipo_recurso, nombre, rr.id";

            $stmtMemoria = $conexion->prepare($sqlMemoria);

            foreach ($parametrosEquipo as $parametro => $valor) {
                $stmtMemoria->bindValue($parametro, $valor, PDO::PARAM_INT);
            }

            $stmtMemoria->execute();
            $memoriaPorEquipo = [];

            foreach ($stmtMemoria->fetchAll(PDO::FETCH_ASSOC) ?: [] as $fila) {
                $equipoId = (int) ($fila['equipo_id'] ?? 0);
                $recurso = rsm_normalizar_recurso_recomendado($fila);

                if ($equipoId < 1 || $recurso === null) {
                    continue;
                }

                if (!isset($memoriaPorEquipo[$equipoId])) {
                    $memoriaPorEquipo[$equipoId] = rsm_recursos_recomendados_vacios();
                    $memoriaPorEquipo[$equipoId]['fuente'] = 'MEMORIA_URGENTE';
                }

                rsm_agregar_recurso_recomendado($memoriaPorEquipo[$equipoId], $recurso);
            }

            foreach ($metadatos as $solicitudId => $meta) {
                $totalTemporal = count($mapa[$solicitudId]['herramientas'] ?? [])
                    + count($mapa[$solicitudId]['refacciones'] ?? []);
                $equipoId = (int) $meta['equipo_id'];

                if (
                    $totalTemporal === 0
                    && (string) $meta['tipo_solicitud'] === 'CORRECTIVO_URGENTE'
                    && isset($memoriaPorEquipo[$equipoId])
                ) {
                    $mapa[$solicitudId] = $memoriaPorEquipo[$equipoId];
                }
            }
        }
    }

    foreach ($mapa as $solicitudId => $grupo) {
        $mapa[$solicitudId] = rsm_finalizar_grupo_recomendado($grupo);
    }

    return $mapa;
}

/**
 * Adjunta las claves de recursos recomendados directamente a cada registro.
 *
 * @param array<int, array<string, mixed>> $registros
 */
function rsm_adjuntar_recursos_recomendados(
    PDO $conexion,
    array &$registros,
    string $campoEquipo = 'solicitud_equipo_id',
    bool $usarMemoriaUrgente = false
): void {
    if ($registros === []) {
        return;
    }

    $solicitudes = [];

    foreach ($registros as $registro) {
        $solicitudes[] = [
            'solicitud_id' => (int) ($registro['solicitud_id'] ?? 0),
            'equipo_id' => (int) ($registro[$campoEquipo] ?? 0),
            'tipo_solicitud' => (string) ($registro['tipo_solicitud'] ?? ''),
        ];
    }

    $mapa = rsm_mapear_recursos_recomendados_solicitudes(
        $conexion,
        $solicitudes,
        $usarMemoriaUrgente
    );

    foreach ($registros as &$registro) {
        $solicitudId = (int) ($registro['solicitud_id'] ?? 0);
        $recursos = $mapa[$solicitudId] ?? rsm_recursos_recomendados_vacios();

        $registro['recursos_recomendados'] = $recursos;
        $registro['herramientas_recomendadas'] = $recursos['herramientas'];
        $registro['refacciones_recomendadas'] = $recursos['refacciones'];
        $registro['total_herramientas_recomendadas'] = $recursos['total_herramientas'];
        $registro['total_refacciones_recomendadas'] = $recursos['total_refacciones'];
        $registro['tiene_recursos_recomendados'] = $recursos['tiene_recomendaciones'];
        $registro['fuente_recursos_recomendados'] = $recursos['fuente'];
        $registro['mensaje_recursos_recomendados'] = $recursos['mensaje_vacio'];
    }
    unset($registro);
}

/**
 * Copia la memoria vigente de equipo + tipo a la fotografía de una solicitud.
 * Si no hay memoria, no inserta nada y la solicitud sigue siendo válida.
 */
function rsm_copiar_memoria_a_solicitud(
    PDO $conexion,
    int $solicitudId,
    int $equipoId,
    string $tipoSolicitud,
    ?int $adminId = null
): int {
    if ($solicitudId < 1 || $equipoId < 1 || trim($tipoSolicitud) === '') {
        return 0;
    }

    $stmt = $conexion->prepare(
        "INSERT IGNORE INTO solicitud_recursos_recomendados (
            solicitud_id,
            tipo_recurso,
            recurso_id,
            nombre_no_catalogado,
            origen,
            agregado_por_admin_id,
            fecha_registro,
            fecha_actualizacion
         )
         SELECT
            :solicitud_id,
            rr.tipo_recurso,
            rr.recurso_id,
            rr.nombre_no_catalogado,
            CASE
                WHEN rr.origen_ultima_actualizacion = 'CIERRE_TECNICO'
                    THEN 'CIERRE_URGENTE'
                ELSE 'MEMORIA'
            END,
            :admin_id,
            NOW(),
            NOW()
         FROM recomendaciones_recursos rr
         WHERE rr.equipo_id = :equipo_id
           AND rr.tipo_solicitud = :tipo_solicitud"
    );

    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
    $stmt->bindValue(':tipo_solicitud', strtoupper(trim($tipoSolicitud)), PDO::PARAM_STR);

    if ($adminId === null) {
        $stmt->bindValue(':admin_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    }

    $stmt->execute();

    return $stmt->rowCount();
}

/* =========================================================================
   RECURSOS REALMENTE UTILIZADOS, HISTORIAL Y APRENDIZAJE DE URGENCIAS
   ========================================================================= */

/**
 * Recupera recursos del catálogo por identificador sin alterar su estado.
 *
 * @param int[] $ids
 * @return array<int, array<string, mixed>> mapa por id
 */
function rsm_recursos_catalogo_por_ids(PDO $conexion, array $ids): array
{
    $limpios = [];

    foreach ($ids as $id) {
        $entero = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero !== false) {
            $limpios[(int) $entero] = (int) $entero;
        }
    }

    if ($limpios === []) {
        return [];
    }

    $marcadores = [];
    foreach (array_values($limpios) as $indice => $id) {
        $marcadores[] = ':rsm_catalogo_' . $indice;
    }

    $stmt = $conexion->prepare(
        "SELECT id, tipo_recurso, nombre, codigo, descripcion, activo
         FROM recursos_mantenimiento
         WHERE id IN (" . implode(', ', $marcadores) . ")
         ORDER BY tipo_recurso, nombre, id"
    );

    foreach (array_values($limpios) as $indice => $id) {
        $stmt->bindValue(':rsm_catalogo_' . $indice, $id, PDO::PARAM_INT);
    }

    $stmt->execute();
    $mapa = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $fila) {
        $fila['id'] = (int) $fila['id'];
        $fila['activo'] = (int) $fila['activo'];
        $mapa[(int) $fila['id']] = $fila;
    }

    return $mapa;
}

/**
 * Busca por nombre dentro del mismo tipo. La intercalación utf8mb4_unicode_ci
 * evita duplicados por mayúsculas o acentos mínimos.
 */
function rsm_buscar_recurso_por_nombre_tipo(
    PDO $conexion,
    string $tipo,
    string $nombre
): ?array {
    $stmt = $conexion->prepare(
        "SELECT id, tipo_recurso, nombre, codigo, descripcion, activo
         FROM recursos_mantenimiento
         WHERE tipo_recurso = :tipo
           AND nombre = :nombre
         ORDER BY activo DESC, id ASC
         LIMIT 1"
    );
    $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($fila)) {
        return null;
    }

    $fila['id'] = (int) $fila['id'];
    $fila['activo'] = (int) $fila['activo'];

    return $fila;
}

/**
 * @return array<string, mixed>
 */
function rsm_recursos_utilizados_vacios(): array
{
    return [
        'herramientas' => [],
        'refacciones' => [],
        'total_herramientas' => 0,
        'total_refacciones' => 0,
        'total' => 0,
        'sin_herramientas_utilizadas' => 0,
        'sin_refacciones_utilizadas' => 0,
        'tiene_registro' => 0,
        'mensaje_herramientas' => 'No se registraron herramientas utilizadas.',
        'mensaje_refacciones' => 'No se registraron refacciones utilizadas.',
    ];
}

/**
 * Devuelve la fotografía recomendada de una sola solicitud.
 *
 * @return array<string, mixed>
 */
function rsm_obtener_recursos_recomendados_solicitud(
    PDO $conexion,
    int $solicitudId,
    bool $usarMemoriaUrgente = false
): array {
    if ($solicitudId < 1) {
        return rsm_recursos_recomendados_vacios();
    }

    $stmt = $conexion->prepare(
        "SELECT id AS solicitud_id, equipo_id, tipo_solicitud
         FROM solicitudes
         WHERE id = :solicitud_id
         LIMIT 1"
    );
    $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmt->execute();
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($solicitud)) {
        return rsm_recursos_recomendados_vacios();
    }

    $mapa = rsm_mapear_recursos_recomendados_solicitudes(
        $conexion,
        [[
            'solicitud_id' => (int) $solicitud['solicitud_id'],
            'equipo_id' => (int) $solicitud['equipo_id'],
            'tipo_solicitud' => (string) $solicitud['tipo_solicitud'],
        ]],
        $usarMemoriaUrgente
    );

    return $mapa[$solicitudId] ?? rsm_recursos_recomendados_vacios();
}

/**
 * Devuelve los recursos realmente usados en un cierre.
 *
 * @return array<string, mixed>
 */
function rsm_obtener_recursos_utilizados_cierre(PDO $conexion, int $cierreId): array
{
    $grupo = rsm_recursos_utilizados_vacios();

    if ($cierreId < 1) {
        return $grupo;
    }

    $stmtCierre = $conexion->prepare(
        "SELECT sin_herramientas_utilizadas, sin_refacciones_utilizadas
         FROM cierres_mantenimiento
         WHERE id = :cierre_id
         LIMIT 1"
    );
    $stmtCierre->bindValue(':cierre_id', $cierreId, PDO::PARAM_INT);
    $stmtCierre->execute();
    $cierre = $stmtCierre->fetch(PDO::FETCH_ASSOC);

    if (!is_array($cierre)) {
        return $grupo;
    }

    $grupo['sin_herramientas_utilizadas'] = (int) ($cierre['sin_herramientas_utilizadas'] ?? 0);
    $grupo['sin_refacciones_utilizadas'] = (int) ($cierre['sin_refacciones_utilizadas'] ?? 0);

    $stmt = $conexion->prepare(
        "SELECT
            cru.id,
            cru.tipo_recurso,
            cru.recurso_id,
            COALESCE(r.nombre, cru.nombre_no_catalogado) AS nombre,
            r.codigo,
            r.descripcion,
            COALESCE(r.activo, 1) AS activo,
            CASE WHEN cru.recurso_id IS NULL THEN 1 ELSE 0 END AS es_otro,
            sr.id AS sugerencia_id,
            sr.estado AS estado_sugerencia
         FROM cierre_recursos_utilizados cru
         LEFT JOIN recursos_mantenimiento r ON r.id = cru.recurso_id
         LEFT JOIN sugerencias_recursos sr
                ON sr.cierre_recurso_utilizado_id = cru.id
         WHERE cru.cierre_id = :cierre_id
         ORDER BY cru.tipo_recurso, nombre, cru.id"
    );
    $stmt->bindValue(':cierre_id', $cierreId, PDO::PARAM_INT);
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $fila) {
        $tipo = (string) ($fila['tipo_recurso'] ?? '');
        $destino = $tipo === RSM_TIPO_HERRAMIENTA ? 'herramientas' : 'refacciones';
        $nombre = trim((string) ($fila['nombre'] ?? ''));

        if ($nombre === '' || !in_array($tipo, rsm_tipos_validos(), true)) {
            continue;
        }

        $grupo[$destino][] = [
            'id' => (int) ($fila['id'] ?? 0),
            'recurso_id' => $fila['recurso_id'] === null ? 0 : (int) $fila['recurso_id'],
            'tipo_recurso' => $tipo,
            'nombre' => $nombre,
            'codigo' => isset($fila['codigo']) && trim((string) $fila['codigo']) !== ''
                ? trim((string) $fila['codigo'])
                : null,
            'descripcion' => isset($fila['descripcion']) && trim((string) $fila['descripcion']) !== ''
                ? trim((string) $fila['descripcion'])
                : null,
            'activo' => (int) ($fila['activo'] ?? 1),
            'es_otro' => (int) ($fila['es_otro'] ?? 0),
            'sugerencia_id' => $fila['sugerencia_id'] === null ? 0 : (int) $fila['sugerencia_id'],
            'estado_sugerencia' => $fila['estado_sugerencia'] ?? null,
        ];
    }

    $grupo['total_herramientas'] = count($grupo['herramientas']);
    $grupo['total_refacciones'] = count($grupo['refacciones']);
    $grupo['total'] = $grupo['total_herramientas'] + $grupo['total_refacciones'];
    $grupo['tiene_registro'] = (
        $grupo['total'] > 0
        || $grupo['sin_herramientas_utilizadas'] === 1
        || $grupo['sin_refacciones_utilizadas'] === 1
    ) ? 1 : 0;

    if ($grupo['sin_herramientas_utilizadas'] === 1) {
        $grupo['mensaje_herramientas'] = 'El técnico confirmó que no utilizó herramientas.';
    } elseif ($grupo['total_herramientas'] > 0) {
        $grupo['mensaje_herramientas'] = '';
    }

    if ($grupo['sin_refacciones_utilizadas'] === 1) {
        $grupo['mensaje_refacciones'] = 'El técnico confirmó que no utilizó refacciones.';
    } elseif ($grupo['total_refacciones'] > 0) {
        $grupo['mensaje_refacciones'] = '';
    }

    return $grupo;
}

/**
 * Adjunta un resumen de recursos utilizados a registros de listado.
 *
 * @param array<int, array<string, mixed>> $registros
 */
function rsm_adjuntar_resumen_recursos_utilizados(PDO $conexion, array &$registros): void
{
    $ids = [];

    foreach ($registros as $registro) {
        $solicitudId = (int) ($registro['solicitud_id'] ?? 0);
        if ($solicitudId > 0) {
            $ids[$solicitudId] = $solicitudId;
        }
    }

    if ($ids === []) {
        return;
    }

    $marcadores = [];
    foreach (array_values($ids) as $indice => $id) {
        $marcadores[] = ':rsm_solicitud_cierre_' . $indice;
    }

    $stmt = $conexion->prepare(
        "SELECT
            cm.solicitud_id,
            cm.sin_herramientas_utilizadas,
            cm.sin_refacciones_utilizadas,
            cru.tipo_recurso,
            COALESCE(r.nombre, cru.nombre_no_catalogado) AS nombre
         FROM cierres_mantenimiento cm
         LEFT JOIN cierre_recursos_utilizados cru ON cru.cierre_id = cm.id
         LEFT JOIN recursos_mantenimiento r ON r.id = cru.recurso_id
         WHERE cm.solicitud_id IN (" . implode(', ', $marcadores) . ")
         ORDER BY cm.solicitud_id, cru.tipo_recurso, nombre, cru.id"
    );

    foreach (array_values($ids) as $indice => $id) {
        $stmt->bindValue(':rsm_solicitud_cierre_' . $indice, $id, PDO::PARAM_INT);
    }

    $stmt->execute();
    $mapa = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $fila) {
        $solicitudId = (int) ($fila['solicitud_id'] ?? 0);
        if ($solicitudId < 1) {
            continue;
        }

        if (!isset($mapa[$solicitudId])) {
            $mapa[$solicitudId] = [
                'herramientas' => [],
                'refacciones' => [],
                'sin_herramientas_utilizadas' => (int) ($fila['sin_herramientas_utilizadas'] ?? 0),
                'sin_refacciones_utilizadas' => (int) ($fila['sin_refacciones_utilizadas'] ?? 0),
            ];
        }

        $nombre = trim((string) ($fila['nombre'] ?? ''));
        $tipo = (string) ($fila['tipo_recurso'] ?? '');

        if ($nombre === '') {
            continue;
        }

        if ($tipo === RSM_TIPO_HERRAMIENTA) {
            $mapa[$solicitudId]['herramientas'][] = $nombre;
        } elseif ($tipo === RSM_TIPO_REFACCION) {
            $mapa[$solicitudId]['refacciones'][] = $nombre;
        }
    }

    foreach ($registros as &$registro) {
        $solicitudId = (int) ($registro['solicitud_id'] ?? 0);
        $resumen = $mapa[$solicitudId] ?? [
            'herramientas' => [],
            'refacciones' => [],
            'sin_herramientas_utilizadas' => 0,
            'sin_refacciones_utilizadas' => 0,
        ];

        $registro['herramientas_utilizadas_nombres'] = $resumen['herramientas'];
        $registro['refacciones_utilizadas_nombres'] = $resumen['refacciones'];
        $registro['total_herramientas_utilizadas'] = count($resumen['herramientas']);
        $registro['total_refacciones_utilizadas'] = count($resumen['refacciones']);
        $registro['sin_herramientas_utilizadas'] = (int) $resumen['sin_herramientas_utilizadas'];
        $registro['sin_refacciones_utilizadas'] = (int) $resumen['sin_refacciones_utilizadas'];
    }
    unset($registro);
}

/**
 * Determina si el administrador dejó una recomendación vigente que debe
 * prevalecer sobre el aprendizaje automático del técnico.
 */
function rsm_urgencia_tiene_recomendacion_administrativa(
    PDO $conexion,
    int $solicitudId,
    int $equipoId
): bool {
    $stmtSolicitud = $conexion->prepare(
        "SELECT COUNT(*)
         FROM solicitud_recursos_recomendados
         WHERE solicitud_id = :solicitud_id
           AND origen = 'ADMIN'"
    );
    $stmtSolicitud->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
    $stmtSolicitud->execute();

    if ((int) $stmtSolicitud->fetchColumn() > 0) {
        return true;
    }

    $stmtMemoria = $conexion->prepare(
        "SELECT COUNT(*)
         FROM recomendaciones_recursos
         WHERE equipo_id = :equipo_id
           AND tipo_solicitud = 'CORRECTIVO_URGENTE'
           AND origen_ultima_actualizacion = 'ADMIN'"
    );
    $stmtMemoria->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
    $stmtMemoria->execute();

    return (int) $stmtMemoria->fetchColumn() > 0;
}

/**
 * Sustituye la memoria automática urgente por lo realmente utilizado.
 * Solo debe llamarse cuando no existe una recomendación administrativa que
 * tenga prioridad. Los registros libres permanecen como recomendación
 * temporal hasta que administración atienda la sugerencia.
 *
 * @param array<int, array<string, mixed>> $recursosUtilizados
 */
function rsm_actualizar_memoria_urgente_desde_cierre(
    PDO $conexion,
    int $solicitudId,
    int $equipoId,
    int $tecnicoId,
    array $recursosUtilizados
): int {
    if ($solicitudId < 1 || $equipoId < 1 || $tecnicoId < 1) {
        return 0;
    }

    if (rsm_urgencia_tiene_recomendacion_administrativa($conexion, $solicitudId, $equipoId)) {
        return 0;
    }

    $stmtEliminar = $conexion->prepare(
        "DELETE FROM recomendaciones_recursos
         WHERE equipo_id = :equipo_id
           AND tipo_solicitud = 'CORRECTIVO_URGENTE'"
    );
    $stmtEliminar->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
    $stmtEliminar->execute();

    if ($recursosUtilizados === []) {
        return 0;
    }

    $stmtInsertar = $conexion->prepare(
        "INSERT INTO recomendaciones_recursos (
            equipo_id,
            tipo_solicitud,
            tipo_recurso,
            recurso_id,
            nombre_no_catalogado,
            origen_ultima_actualizacion,
            solicitud_origen_id,
            actualizado_por_admin_id,
            actualizado_por_tecnico_id,
            fecha_registro,
            fecha_actualizacion
         ) VALUES (
            :equipo_id,
            'CORRECTIVO_URGENTE',
            :tipo_recurso,
            :recurso_id,
            :nombre_no_catalogado,
            'CIERRE_TECNICO',
            :solicitud_id,
            NULL,
            :tecnico_id,
            NOW(),
            NOW()
         )"
    );

    $insertados = 0;

    foreach ($recursosUtilizados as $recurso) {
        $tipo = (string) ($recurso['tipo_recurso'] ?? '');
        $recursoId = (int) ($recurso['recurso_id'] ?? 0);
        $nombreLibre = trim((string) ($recurso['nombre_no_catalogado'] ?? ''));

        if (!in_array($tipo, rsm_tipos_validos(), true)) {
            continue;
        }

        if (($recursoId > 0 && $nombreLibre !== '') || ($recursoId < 1 && $nombreLibre === '')) {
            continue;
        }

        $stmtInsertar->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
        $stmtInsertar->bindValue(':tipo_recurso', $tipo, PDO::PARAM_STR);

        if ($recursoId > 0) {
            $stmtInsertar->bindValue(':recurso_id', $recursoId, PDO::PARAM_INT);
            $stmtInsertar->bindValue(':nombre_no_catalogado', null, PDO::PARAM_NULL);
        } else {
            $stmtInsertar->bindValue(':recurso_id', null, PDO::PARAM_NULL);
            $stmtInsertar->bindValue(':nombre_no_catalogado', $nombreLibre, PDO::PARAM_STR);
        }

        $stmtInsertar->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmtInsertar->bindValue(':tecnico_id', $tecnicoId, PDO::PARAM_INT);
        $stmtInsertar->execute();
        $insertados++;
    }

    return $insertados;
}
