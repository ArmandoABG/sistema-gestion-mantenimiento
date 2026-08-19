<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Solicitud de correctivo programable - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| - Disponible para ADMIN y SOLICITANTE.
| - El estado inicial siempre es PENDIENTE.
| - Tipo de falla y causa de avería son opcionales.
| - La ubicación se obtiene del código real del equipo.
| - La fecha y hora se toman del servidor.
| - Compatible con PHP 7.4 o superior.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_sesion(['ADMIN', 'SOLICITANTE'], true);

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
        case 'inicial':
            sm_requerir_metodo('GET');
            scp_cargar_inicial($conexion);
            break;

        case 'buscar_equipo':
            sm_requerir_metodo('GET');
            scp_buscar_equipo($conexion);
            break;

        case 'crear':
            sm_requerir_metodo('POST');
            sm_validar_csrf();
            scp_crear_solicitud($conexion);
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

    error_log('[CORRECTIVO PROGRAMABLE][PDO] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la solicitud.',
        ['form_token' => scp_emitir_form_token()],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[CORRECTIVO PROGRAMABLE] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la solicitud.',
        ['form_token' => scp_emitir_form_token()],
        500
    );
}

/* =========================================================================
   ACCIONES
   ========================================================================= */

function scp_cargar_inicial(PDO $conexion): void
{
    $contexto = scp_contexto_sesion($conexion);

    $tiposFalla = scp_consultar_todos(
        $conexion,
        "SELECT id, nombre
         FROM tipos_falla
         WHERE activo = 1
         ORDER BY nombre"
    );

    $causasAveria = scp_consultar_todos(
        $conexion,
        "SELECT id, nombre
         FROM causas_averia
         WHERE activo = 1
         ORDER BY nombre"
    );

    $solicitantes = [];

    if ($contexto['rol'] === 'ADMIN') {
        $solicitantes = scp_obtener_solicitantes_activos($conexion);
    }

    sm_responder_json(
        true,
        'Información cargada correctamente.',
        [
            'rol' => $contexto['rol'],
            'usuario_sesion' => $contexto['perfil'],
            'solicitante' => $contexto['rol'] === 'SOLICITANTE'
                ? $contexto['perfil']
                : null,
            'solicitantes' => $solicitantes,
            'catalogos' => [
                'tipos_falla' => $tiposFalla,
                'causas_averia' => $causasAveria,
            ],
            'resumen' => scp_obtener_resumen(
                $conexion,
                $contexto['rol'],
                $contexto['usuario_id']
            ),
            'recientes' => scp_obtener_recientes(
                $conexion,
                $contexto['rol'],
                $contexto['usuario_id']
            ),
            'form_token' => scp_emitir_form_token(),
            'fecha_servidor' => date('Y-m-d'),
            'fecha_hora_servidor' => date('d/m/Y H:i'),
        ]
    );
}

function scp_buscar_equipo(PDO $conexion): void
{
    $termino = scp_normalizar_busqueda_equipo(
        $_GET['termino'] ?? ($_GET['codigo'] ?? '')
    );

    if ($termino === '') {
        scp_error_campo(
            'Escribe el código o parte del nombre del equipo.',
            'codigo_equipo'
        );
    }

    $longitud = scp_longitud($termino);

    if ($longitud < 2 || $longitud > 100) {
        scp_error_campo(
            'La búsqueda debe contener entre 2 y 100 caracteres.',
            'codigo_equipo'
        );
    }

    $codigoExacto = scp_normalizar_codigo($termino);
    $contiene = '%' . $termino . '%';
    $prefijo = $termino . '%';

    $stmt = $conexion->prepare(
        "SELECT
            e.id,
            e.codigo_equipo,
            e.nombre_equipo,
            e.descripcion,
            e.departamento_id,
            e.area_id,
            e.proceso_id,
            d.nombre AS departamento,
            a.nombre AS area,
            p.nombre AS proceso,

            CASE
                WHEN e.departamento_id IS NOT NULL
                 AND e.area_id IS NOT NULL
                 AND e.proceso_id IS NOT NULL
                 AND d.id IS NOT NULL
                 AND a.id IS NOT NULL
                 AND p.id IS NOT NULL
                    THEN 1
                ELSE 0
            END AS seleccionable

         FROM equipos e

         LEFT JOIN departamentos d
            ON d.id = e.departamento_id
           AND d.activo = 1

         LEFT JOIN areas a
            ON a.id = e.area_id
           AND a.activo = 1

         LEFT JOIN procesos p
            ON p.id = e.proceso_id
           AND p.activo = 1

         WHERE e.activo = 1
           AND (
                e.codigo_equipo LIKE :codigo_contiene
                OR e.nombre_equipo LIKE :nombre_contiene
           )

         ORDER BY
            CASE
                WHEN e.codigo_equipo = :codigo_exacto THEN 0
                WHEN e.nombre_equipo = :nombre_exacto THEN 1
                WHEN e.codigo_equipo LIKE :codigo_prefijo THEN 2
                WHEN e.nombre_equipo LIKE :nombre_prefijo THEN 3
                ELSE 4
            END,
            e.nombre_equipo ASC,
            e.codigo_equipo ASC

         LIMIT 20"
    );

    $stmt->execute([
        ':codigo_contiene' => $contiene,
        ':nombre_contiene' => $contiene,
        ':codigo_exacto' => $codigoExacto,
        ':nombre_exacto' => $termino,
        ':codigo_prefijo' => $prefijo,
        ':nombre_prefijo' => $prefijo,
    ]);

    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!$equipos) {
        sm_responder_json(
            false,
            'No se encontraron equipos activos con ese código o nombre.',
            ['campo' => 'codigo_equipo'],
            404
        );
    }

    foreach ($equipos as &$equipo) {
        $equipo['id'] = (int) ($equipo['id'] ?? 0);
        $equipo['departamento_id'] = isset($equipo['departamento_id'])
            ? (int) $equipo['departamento_id']
            : null;
        $equipo['area_id'] = isset($equipo['area_id'])
            ? (int) $equipo['area_id']
            : null;
        $equipo['proceso_id'] = isset($equipo['proceso_id'])
            ? (int) $equipo['proceso_id']
            : null;
        $equipo['seleccionable'] = (int) ($equipo['seleccionable'] ?? 0);
    }
    unset($equipo);

    $seleccionables = array_values(
        array_filter(
            $equipos,
            static function (array $equipo): bool {
                return (int) ($equipo['seleccionable'] ?? 0) === 1;
            }
        )
    );

    if (!$seleccionables) {
        sm_responder_json(
            false,
            'Los equipos encontrados tienen su ubicación incompleta. Solicita al administrador que corrija el catálogo de equipos.',
            [
                'campo' => 'codigo_equipo',
                'equipos' => $equipos,
            ],
            409
        );
    }

    sm_responder_json(
        true,
        count($equipos) === 1
            ? 'Equipo encontrado.'
            : 'Selecciona el equipo correcto.',
        [
            'equipos' => $equipos,
            'total' => count($equipos),
            'seleccion_automatica' => count($equipos) === 1
                && (int) ($equipos[0]['seleccionable'] ?? 0) === 1,
        ]
    );
}

function scp_crear_solicitud(PDO $conexion): void
{
    $contexto = scp_contexto_sesion($conexion);
    $origen = scp_resolver_origen_solicitud(
        $conexion,
        $contexto,
        $_POST['solicitante_opcion'] ?? ''
    );

    $formToken = sm_limpiar_texto($_POST['form_token'] ?? '');

    $equipoId = scp_entero_requerido(
        $_POST['equipo_id'] ?? null,
        'equipo_id',
        'Busca el equipo por código o nombre y selecciona una opción válida.'
    );

    $codigoEquipo = scp_normalizar_codigo($_POST['codigo_equipo'] ?? '');

    if ($codigoEquipo === '') {
        scp_error_campo('Busca y selecciona un equipo válido.', 'codigo_equipo');
    }

    $prioridad = strtoupper(sm_limpiar_texto($_POST['prioridad'] ?? 'MEDIA'));

    if (!in_array($prioridad, ['BAJA', 'MEDIA', 'ALTA'], true)) {
        scp_error_campo(
            'Selecciona una prioridad válida. Para una emergencia utiliza Correctivo urgente.',
            'prioridad'
        );
    }

    $fechaSugerida = scp_fecha_opcional($_POST['fecha_sugerida'] ?? '');

    $tipoFallaId = scp_entero_opcional(
        $_POST['tipo_falla_id'] ?? null,
        'tipo_falla_id'
    );

    $causaAveriaId = scp_entero_opcional(
        $_POST['causa_averia_id'] ?? null,
        'causa_averia_id'
    );

    $descripcion = scp_texto_requerido(
        $_POST['descripcion_solicitud'] ?? '',
        'descripcion_solicitud',
        'Describe claramente el mantenimiento o problema que necesitas reportar.',
        20,
        2500
    );

    $descripcionFalla = scp_texto_opcional(
        $_POST['descripcion_falla'] ?? '',
        'descripcion_falla',
        1500
    );

    $impactoOperacion = scp_texto_opcional(
        $_POST['impacto_operacion'] ?? '',
        'impacto_operacion',
        1500
    );

    $observaciones = scp_texto_opcional(
        $_POST['observaciones_solicitante'] ?? '',
        'observaciones_solicitante',
        1500
    );

    $trabajoPeligroso = scp_booleano($_POST['trabajo_peligroso'] ?? '0');
    $detalleTrabajoPeligroso = scp_texto_opcional(
        $_POST['detalle_trabajo_peligroso'] ?? '',
        'detalle_trabajo_peligroso',
        200
    );
    $requiereParo = scp_booleano($_POST['requiere_paro_equipo'] ?? '0');
    $nivelRiesgo = strtoupper(sm_limpiar_texto($_POST['nivel_riesgo'] ?? 'BAJO'));

    if (!in_array($nivelRiesgo, ['BAJO', 'MEDIO', 'ALTO'], true)) {
        $nivelRiesgo = 'BAJO';
    }

    if ($trabajoPeligroso === 0) {
        $nivelRiesgo = 'BAJO';
        $detalleTrabajoPeligroso = null;
    } else {
        if ($nivelRiesgo === 'BAJO') {
            $nivelRiesgo = 'MEDIO';
        }
        if ($detalleTrabajoPeligroso === null || mb_strlen($detalleTrabajoPeligroso, 'UTF-8') < 3) {
            sm_responder_json(false, 'Describe brevemente el peligro o la precaución necesaria.', ['campo' => 'detalle_trabajo_peligroso'], 422);
        }
    }

    $equipo = scp_validar_equipo(
        $conexion,
        $equipoId,
        $codigoEquipo
    );

    if ($tipoFallaId !== null) {
        scp_validar_catalogo_activo(
            $conexion,
            'tipos_falla',
            $tipoFallaId,
            'tipo_falla_id',
            'El tipo de falla seleccionado ya no está disponible.'
        );
    }

    if ($causaAveriaId !== null) {
        scp_validar_catalogo_activo(
            $conexion,
            'causas_averia',
            $causaAveriaId,
            'causa_averia_id',
            'La causa de avería seleccionada ya no está disponible.'
        );
    }

    scp_consumir_form_token($formToken);

    $duplicado = scp_buscar_duplicado_reciente(
        $conexion,
        $origen,
        $equipoId,
        $descripcion
    );

    if ($duplicado !== null) {
        sm_responder_json(
            false,
            'Esta misma solicitud ya fue registrada recientemente con el folio ' . $duplicado . '.',
            [
                'folio_existente' => $duplicado,
                'form_token' => scp_emitir_form_token(),
            ],
            409
        );
    }

    $ultimoEnvio = (int) ($_SESSION['scp_ultimo_envio'] ?? 0);

    if ($ultimoEnvio > 0 && (time() - $ultimoEnvio) < 2) {
        sm_responder_json(
            false,
            'Espera un momento antes de registrar otra solicitud.',
            ['form_token' => scp_emitir_form_token()],
            429
        );
    }

    $ahora = new DateTimeImmutable('now', new DateTimeZone(SM_ZONA_HORARIA));
    $fechaSolicitud = $ahora->format('Y-m-d');
    $horaSolicitud = $ahora->format('H:i:s');

    $conexion->beginTransaction();

    $folio = scp_generar_folio(
        $conexion,
        (int) $ahora->format('Y')
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
            impacto_operacion,
            observaciones_solicitante,
            trabajo_peligroso,
            detalle_trabajo_peligroso,
            nivel_riesgo,
            requiere_paro_equipo,
            activo
        )
        VALUES
        (
            :folio,
            'CORRECTIVO_PROGRAMABLE',
            'PENDIENTE',
            :solicitante_id,
            :administrador_solicitante_id,
            :creado_por_tipo,
            :creado_por_id,
            :departamento_id,
            :area_id,
            :proceso_id,
            :equipo_id,
            :fecha_solicitud,
            :hora_solicitud,
            :fecha_sugerida,
            :prioridad,
            :descripcion_solicitud,
            :tipo_falla_id,
            :causa_averia_id,
            :descripcion_falla,
            :impacto_operacion,
            :observaciones_solicitante,
            :trabajo_peligroso,
            :detalle_trabajo_peligroso,
            :nivel_riesgo,
            :requiere_paro_equipo,
            1
        )"
    );

    $stmt->bindValue(':folio', $folio, PDO::PARAM_STR);
    scp_bind_nullable_int($stmt, ':solicitante_id', $origen['solicitante_id']);
    scp_bind_nullable_int(
        $stmt,
        ':administrador_solicitante_id',
        $origen['administrador_solicitante_id']
    );
    $stmt->bindValue(':creado_por_tipo', $contexto['rol'], PDO::PARAM_STR);
    $stmt->bindValue(':creado_por_id', $contexto['usuario_id'], PDO::PARAM_INT);
    $stmt->bindValue(':departamento_id', (int) $equipo['departamento_id'], PDO::PARAM_INT);
    $stmt->bindValue(':area_id', (int) $equipo['area_id'], PDO::PARAM_INT);
    $stmt->bindValue(':proceso_id', (int) $equipo['proceso_id'], PDO::PARAM_INT);
    $stmt->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
    $stmt->bindValue(':fecha_solicitud', $fechaSolicitud, PDO::PARAM_STR);
    $stmt->bindValue(':hora_solicitud', $horaSolicitud, PDO::PARAM_STR);
    scp_bind_nullable_string($stmt, ':fecha_sugerida', $fechaSugerida);
    $stmt->bindValue(':prioridad', $prioridad, PDO::PARAM_STR);
    $stmt->bindValue(':descripcion_solicitud', $descripcion, PDO::PARAM_STR);
    scp_bind_nullable_int($stmt, ':tipo_falla_id', $tipoFallaId);
    scp_bind_nullable_int($stmt, ':causa_averia_id', $causaAveriaId);
    scp_bind_nullable_string($stmt, ':descripcion_falla', $descripcionFalla);
    scp_bind_nullable_string($stmt, ':impacto_operacion', $impactoOperacion);
    scp_bind_nullable_string($stmt, ':observaciones_solicitante', $observaciones);
    $stmt->bindValue(':trabajo_peligroso', $trabajoPeligroso, PDO::PARAM_INT);
    scp_bind_nullable_string($stmt, ':detalle_trabajo_peligroso', $detalleTrabajoPeligroso);
    $stmt->bindValue(':nivel_riesgo', $nivelRiesgo, PDO::PARAM_STR);
    $stmt->bindValue(':requiere_paro_equipo', $requiereParo, PDO::PARAM_INT);
    $stmt->execute();

    $solicitudId = (int) $conexion->lastInsertId();

    scp_registrar_historial(
        $conexion,
        $solicitudId,
        $contexto,
        $origen,
        $folio,
        (string) $equipo['codigo_equipo'],
        (string) $equipo['nombre_equipo']
    );

    scp_registrar_movimiento(
        $conexion,
        $contexto,
        $origen,
        $solicitudId,
        $folio,
        (string) $equipo['codigo_equipo']
    );

    scp_notificar_registro(
        $conexion,
        $solicitudId,
        $contexto,
        $origen,
        $folio,
        $prioridad,
        (string) $equipo['codigo_equipo'],
        (string) $equipo['nombre_equipo']
    );

    $conexion->commit();

    $_SESSION['scp_ultimo_envio'] = time();

    sm_responder_json(
        true,
        'La solicitud fue registrada correctamente.',
        [
            'folio' => $folio,
            'solicitud_id' => $solicitudId,
            'estado' => 'PENDIENTE',
            'registrada_para' => $origen['nombre_completo'],
            'form_token' => scp_emitir_form_token(),
            'resumen' => scp_obtener_resumen(
                $conexion,
                $contexto['rol'],
                $contexto['usuario_id']
            ),
            'recientes' => scp_obtener_recientes(
                $conexion,
                $contexto['rol'],
                $contexto['usuario_id']
            ),
        ],
        201
    );
}

function scp_obtener_solicitante_activo(
    PDO $conexion,
    int $solicitanteId,
    bool $esUsuarioSesion = true
): array {
    $stmt = $conexion->prepare(
        "SELECT
            s.id,
            s.usuario,
            s.nombre,
            s.apellido_paterno,
            s.apellido_materno,
            s.departamento_id,
            COALESCE(d.nombre, 'Sin departamento asignado') AS departamento
         FROM solicitantes s
         LEFT JOIN departamentos d
            ON d.id = s.departamento_id
         WHERE s.id = :id
           AND s.activo = 1
         LIMIT 1"
    );

    $stmt->execute([':id' => $solicitanteId]);
    $solicitante = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitante) {
        if ($esUsuarioSesion) {
            sm_responder_json(
                false,
                'Tu cuenta ya no está activa. Inicia sesión nuevamente.',
                [
                    'sesion_expirada' => true,
                    'redirect' => '../login.php?sesion=expirada',
                ],
                401
            );
        }

        scp_error_campo(
            'El solicitante seleccionado ya no está disponible.',
            'solicitante_opcion',
            409
        );
    }

    $solicitante['nombre_completo'] = scp_nombre_completo($solicitante);

    return $solicitante;
}

function scp_contexto_sesion(PDO $conexion): array
{
    $rol = strtoupper(sm_limpiar_texto($_SESSION['tipo_usuario'] ?? ''));
    $usuarioId = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($usuarioId === false || !in_array($rol, ['ADMIN', 'SOLICITANTE'], true)) {
        sm_responder_json(
            false,
            'La sesión del usuario no es válida.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?sesion=expirada',
            ],
            401
        );
    }

    if ($rol === 'ADMIN') {
        $perfil = scp_obtener_administrador_activo($conexion, (int) $usuarioId);
    } else {
        $perfil = scp_obtener_solicitante_activo($conexion, (int) $usuarioId);
    }

    return [
        'rol' => $rol,
        'usuario_id' => (int) $usuarioId,
        'perfil' => $perfil,
    ];
}

function scp_obtener_administrador_activo(PDO $conexion, int $administradorId): array
{
    $stmt = $conexion->prepare(
        "SELECT
            id,
            usuario,
            nombre,
            apellido_paterno,
            apellido_materno
         FROM administradores
         WHERE id = :id
           AND activo = 1
         LIMIT 1"
    );

    $stmt->execute([':id' => $administradorId]);
    $administrador = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$administrador) {
        sm_responder_json(
            false,
            'La cuenta del administrador ya no está activa.',
            [
                'sesion_expirada' => true,
                'redirect' => '../login.php?sesion=expirada',
            ],
            401
        );
    }

    $administrador['nombre_completo'] = scp_nombre_completo($administrador);
    $administrador['departamento'] = 'Administración';

    return $administrador;
}

function scp_obtener_solicitantes_activos(PDO $conexion): array
{
    $stmt = $conexion->prepare(
        "SELECT
            s.id,
            s.usuario,
            s.nombre,
            s.apellido_paterno,
            s.apellido_materno,
            s.departamento_id,
            COALESCE(d.nombre, 'Sin departamento asignado') AS departamento
         FROM solicitantes s
         LEFT JOIN departamentos d
            ON d.id = s.departamento_id
         WHERE s.activo = 1
         ORDER BY
            s.nombre,
            s.apellido_paterno,
            s.apellido_materno,
            s.id"
    );

    $stmt->execute();
    $solicitantes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($solicitantes as &$solicitante) {
        $solicitante['nombre_completo'] = scp_nombre_completo($solicitante);
    }
    unset($solicitante);

    return $solicitantes;
}

function scp_resolver_origen_solicitud(
    PDO $conexion,
    array $contexto,
    $opcionRecibida
): array {
    if ($contexto['rol'] === 'SOLICITANTE') {
        return [
            'solicitante_id' => $contexto['usuario_id'],
            'administrador_solicitante_id' => null,
            'nombre_completo' => $contexto['perfil']['nombre_completo'],
            'departamento' => $contexto['perfil']['departamento'] ?? 'Sin departamento asignado',
            'tipo' => 'SOLICITANTE',
        ];
    }

    $opcion = strtoupper(sm_limpiar_texto($opcionRecibida));

    if ($opcion === 'ADMIN') {
        return [
            'solicitante_id' => null,
            'administrador_solicitante_id' => $contexto['usuario_id'],
            'nombre_completo' => $contexto['perfil']['nombre_completo'],
            'departamento' => 'Registro directo del administrador',
            'tipo' => 'ADMIN',
        ];
    }

    if (!preg_match('/^SOLICITANTE:(\d+)$/', $opcion, $coincidencia)) {
        scp_error_campo(
            'Selecciona a nombre de quién se registrará la solicitud.',
            'solicitante_opcion'
        );
    }

    $solicitanteId = (int) $coincidencia[1];
    $solicitante = scp_obtener_solicitante_activo(
        $conexion,
        $solicitanteId,
        false
    );

    return [
        'solicitante_id' => $solicitanteId,
        'administrador_solicitante_id' => null,
        'nombre_completo' => $solicitante['nombre_completo'],
        'departamento' => $solicitante['departamento'],
        'tipo' => 'SOLICITANTE',
    ];
}

function scp_nombre_completo(array $persona): string
{
    return trim(
        implode(
            ' ',
            array_filter(
                [
                    (string) ($persona['nombre'] ?? ''),
                    (string) ($persona['apellido_paterno'] ?? ''),
                    (string) ($persona['apellido_materno'] ?? ''),
                ],
                static function ($valor): bool {
                    return trim((string) $valor) !== '';
                }
            )
        )
    );
}

function scp_validar_equipo(PDO $conexion, int $equipoId, string $codigo): array
{
    $stmt = $conexion->prepare(
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
           AND d.activo = 1
         INNER JOIN areas a
            ON a.id = e.area_id
           AND a.activo = 1
           AND a.departamento_id = e.departamento_id
         INNER JOIN procesos p
            ON p.id = e.proceso_id
           AND p.activo = 1
           AND p.area_id = e.area_id
         WHERE e.id = :id
           AND e.codigo_equipo = :codigo
           AND e.activo = 1
         LIMIT 1"
    );

    $stmt->execute([
        ':id' => $equipoId,
        ':codigo' => $codigo,
    ]);

    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipo) {
        scp_error_campo(
            'El equipo seleccionado ya no es válido. Vuelve a buscarlo por código o nombre.',
            'codigo_equipo',
            409
        );
    }

    return $equipo;
}

function scp_obtener_resumen(
    PDO $conexion,
    string $rol,
    int $usuarioId
): array {
    if ($rol === 'ADMIN') {
        $condicion = "creado_por_tipo = 'ADMIN' AND creado_por_id = :usuario_id";
    } else {
        $condicion = "solicitante_id = :usuario_id";
    }

    $stmt = $conexion->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN estado IN ('APROBADO', 'AGENDADO') THEN 1 ELSE 0 END) AS autorizadas,
            SUM(CASE WHEN estado IN ('EN_PROCESO', 'PAUSADO', 'ATRASADO') THEN 1 ELSE 0 END) AS seguimiento,
            SUM(CASE WHEN estado = 'TERMINADO' THEN 1 ELSE 0 END) AS terminadas
         FROM solicitudes
         WHERE {$condicion}
           AND tipo_solicitud = 'CORRECTIVO_PROGRAMABLE'
           AND activo = 1"
    );

    $stmt->execute([':usuario_id' => $usuarioId]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total' => (int) ($fila['total'] ?? 0),
        'pendientes' => (int) ($fila['pendientes'] ?? 0),
        'autorizadas' => (int) ($fila['autorizadas'] ?? 0),
        'seguimiento' => (int) ($fila['seguimiento'] ?? 0),
        'terminadas' => (int) ($fila['terminadas'] ?? 0),
    ];
}

function scp_obtener_recientes(
    PDO $conexion,
    string $rol,
    int $usuarioId
): array {
    if ($rol === 'ADMIN') {
        $condicion = "s.creado_por_tipo = 'ADMIN' AND s.creado_por_id = :usuario_id";
    } else {
        $condicion = "s.solicitante_id = :usuario_id";
    }

    $stmt = $conexion->prepare(
        "SELECT
            s.id,
            s.folio,
            s.estado,
            s.prioridad,
            s.descripcion_solicitud,
            s.fecha_registro,
            DATE_FORMAT(s.fecha_registro, '%d/%m/%Y %H:%i') AS fecha_registro_formato,
            e.codigo_equipo,
            e.nombre_equipo,
            COALESCE(
                NULLIF(
                    TRIM(CONCAT_WS(' ', so.nombre, so.apellido_paterno, so.apellido_materno)),
                    ''
                ),
                NULLIF(
                    TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno)),
                    ''
                ),
                'Sin solicitante'
            ) AS nombre_solicitante
         FROM solicitudes s
         INNER JOIN equipos e
            ON e.id = s.equipo_id
         LEFT JOIN solicitantes so
            ON so.id = s.solicitante_id
         LEFT JOIN administradores ad
            ON ad.id = s.administrador_solicitante_id
         WHERE {$condicion}
           AND s.tipo_solicitud = 'CORRECTIVO_PROGRAMABLE'
           AND s.activo = 1
         ORDER BY s.fecha_registro DESC, s.id DESC
         LIMIT 6"
    );

    $stmt->execute([':usuario_id' => $usuarioId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function scp_buscar_duplicado_reciente(
    PDO $conexion,
    array $origen,
    int $equipoId,
    string $descripcion
): ?string {
    if ($origen['solicitante_id'] !== null) {
        $condicion = 'solicitante_id = :persona_id';
        $personaId = (int) $origen['solicitante_id'];
    } else {
        $condicion = 'administrador_solicitante_id = :persona_id';
        $personaId = (int) $origen['administrador_solicitante_id'];
    }

    $stmt = $conexion->prepare(
        "SELECT folio
         FROM solicitudes
         WHERE {$condicion}
           AND equipo_id = :equipo_id
           AND tipo_solicitud = 'CORRECTIVO_PROGRAMABLE'
           AND descripcion_solicitud = :descripcion
           AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
           AND activo = 1
         ORDER BY id DESC
         LIMIT 1"
    );

    $stmt->execute([
        ':persona_id' => $personaId,
        ':equipo_id' => $equipoId,
        ':descripcion' => $descripcion,
    ]);

    $folio = $stmt->fetchColumn();

    return $folio === false ? null : (string) $folio;
}

function scp_consultar_todos(PDO $conexion, string $sql): array
{
    $stmt = $conexion->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* =========================================================================
   REGISTRO TRANSACCIONAL
   ========================================================================= */

function scp_generar_folio(PDO $conexion, int $anio): string
{
    $tipo = 'CORRECTIVO_PROGRAMABLE';
    $prefijo = sprintf('MCP-%04d-', $anio);

    /*
     * Se consulta el folio mayor ya existente para que la secuencia también
     * funcione correctamente si en el futuro se importan solicitudes.
     */
    $stmt = $conexion->prepare(
        "SELECT COALESCE(
            MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)),
            0
         )
         FROM solicitudes
         WHERE tipo_solicitud = 'CORRECTIVO_PROGRAMABLE'
           AND folio LIKE :patron"
    );

    $stmt->execute([':patron' => $prefijo . '%']);
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

    $stmt->execute([
        ':tipo' => $tipo,
        ':anio' => $anio,
        ':numero_inicial' => $minimoSiguiente,
        ':numero_minimo' => $minimoSiguiente,
    ]);

    $stmt = $conexion->prepare(
        "SELECT ultimo_numero
         FROM secuencias_folios
         WHERE tipo_solicitud = :tipo
           AND anio = :anio
         FOR UPDATE"
    );

    $stmt->execute([
        ':tipo' => $tipo,
        ':anio' => $anio,
    ]);

    $numero = (int) $stmt->fetchColumn();

    if ($numero <= 0) {
        throw new RuntimeException('No fue posible generar el folio.');
    }

    return sprintf('MCP-%04d-%05d', $anio, $numero);
}

function scp_registrar_historial(
    PDO $conexion,
    int $solicitudId,
    array $contexto,
    array $origen,
    string $folio,
    string $codigoEquipo,
    string $nombreEquipo
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
            descripcion
        )
        VALUES
        (
            :solicitud_id,
            NULL,
            NULL,
            'CREADA',
            NULL,
            'PENDIENTE',
            :actor_tipo,
            :actor_id,
            :descripcion
        )"
    );

    $stmt->execute([
        ':solicitud_id' => $solicitudId,
        ':actor_tipo' => $contexto['rol'],
        ':actor_id' => $contexto['usuario_id'],
        ':descripcion' => sprintf(
            '%s registró la solicitud programable %s a nombre de %s para el equipo %s - %s.',
            $contexto['perfil']['nombre_completo'],
            $folio,
            $origen['nombre_completo'],
            $codigoEquipo,
            $nombreEquipo
        ),
    ]);
}

function scp_registrar_movimiento(
    PDO $conexion,
    array $contexto,
    array $origen,
    int $solicitudId,
    string $folio,
    string $codigoEquipo
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
            user_agent
        )
        VALUES
        (
            :tipo_usuario,
            :usuario_id,
            'CREAR_SOLICITUD_PROGRAMABLE',
            'Correctivo programable',
            :descripcion,
            'solicitudes',
            :registro_id,
            :ip_address,
            :user_agent
        )"
    );

    $stmt->execute([
        ':tipo_usuario' => $contexto['rol'],
        ':usuario_id' => $contexto['usuario_id'],
        ':descripcion' => sprintf(
            'Se registró la solicitud %s a nombre de %s para el equipo %s.',
            $folio,
            $origen['nombre_completo'],
            $codigoEquipo
        ),
        ':registro_id' => $solicitudId,
        ':ip_address' => scp_ip_cliente(),
        ':user_agent' => scp_user_agent(),
    ]);
}

function scp_notificar_registro(
    PDO $conexion,
    int $solicitudId,
    array $contexto,
    array $origen,
    string $folio,
    string $prioridad,
    string $codigoEquipo,
    string $nombreEquipo
): void {
    $tipoNotificacion = $prioridad === 'ALTA' ? 'WARNING' : 'INFO';

    $sqlAdministradores =
        "INSERT INTO notificaciones
        (
            tipo_usuario,
            usuario_id,
            solicitud_id,
            titulo,
            mensaje,
            tipo,
            leida
        )
        SELECT
            'ADMIN',
            a.id,
            :solicitud_id,
            'Nueva solicitud programable',
            :mensaje,
            :tipo,
            0
        FROM administradores a
        WHERE a.activo = 1";

    if ($contexto['rol'] === 'ADMIN') {
        $sqlAdministradores .= ' AND a.id <> :admin_actual';
    }

    $stmt = $conexion->prepare($sqlAdministradores);
    $parametros = [
        ':solicitud_id' => $solicitudId,
        ':mensaje' => sprintf(
            'La solicitud %s, registrada para %s, requiere revisión. Equipo: %s - %s. Prioridad: %s.',
            $folio,
            $origen['nombre_completo'],
            $codigoEquipo,
            $nombreEquipo,
            $prioridad
        ),
        ':tipo' => $tipoNotificacion,
    ];

    if ($contexto['rol'] === 'ADMIN') {
        $parametros[':admin_actual'] = $contexto['usuario_id'];
    }

    $stmt->execute($parametros);

    if (
        $contexto['rol'] === 'ADMIN'
        && $origen['solicitante_id'] !== null
    ) {
        $stmt = $conexion->prepare(
            "INSERT INTO notificaciones
            (
                tipo_usuario,
                usuario_id,
                solicitud_id,
                titulo,
                mensaje,
                tipo,
                leida
            )
            VALUES
            (
                'SOLICITANTE',
                :usuario_id,
                :solicitud_id,
                'Solicitud registrada por administración',
                :mensaje,
                'INFO',
                0
            )"
        );

        $stmt->execute([
            ':usuario_id' => $origen['solicitante_id'],
            ':solicitud_id' => $solicitudId,
            ':mensaje' => sprintf(
                'El administrador %s registró la solicitud %s a tu nombre para el equipo %s - %s.',
                $contexto['perfil']['nombre_completo'],
                $folio,
                $codigoEquipo,
                $nombreEquipo
            ),
        ]);
    }
}

function scp_normalizar_busqueda_equipo($valor): string
{
    $termino = sm_limpiar_texto($valor);
    $termino = preg_replace('/\s+/u', ' ', $termino) ?? '';

    return trim($termino);
}

function scp_normalizar_codigo($valor): string
{
    $codigo = sm_limpiar_texto($valor);
    $codigo = preg_replace('/\s+/u', '', $codigo) ?? '';

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($codigo, 'UTF-8')
        : strtoupper($codigo);
}

function scp_entero_requerido($valor, string $campo, string $mensaje): int
{
    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($entero === false) {
        scp_error_campo($mensaje, $campo);
    }

    return (int) $entero;
}

function scp_entero_opcional($valor, string $campo): ?int
{
    if ($valor === null || sm_limpiar_texto($valor) === '') {
        return null;
    }

    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($entero === false) {
        scp_error_campo('Selecciona una opción válida.', $campo);
    }

    return (int) $entero;
}

function scp_texto_requerido(
    $valor,
    string $campo,
    string $mensaje,
    int $minimo,
    int $maximo
): string {
    $texto = sm_limpiar_texto($valor);
    $longitud = scp_longitud($texto);

    if ($texto === '' || $longitud < $minimo) {
        scp_error_campo(
            $mensaje . ' Usa al menos ' . $minimo . ' caracteres.',
            $campo
        );
    }

    if ($longitud > $maximo) {
        scp_error_campo(
            'El texto no puede superar ' . $maximo . ' caracteres.',
            $campo
        );
    }

    return $texto;
}

function scp_texto_opcional($valor, string $campo, int $maximo): ?string
{
    $texto = sm_limpiar_texto($valor);

    if ($texto === '') {
        return null;
    }

    if (scp_longitud($texto) > $maximo) {
        scp_error_campo(
            'El texto no puede superar ' . $maximo . ' caracteres.',
            $campo
        );
    }

    return $texto;
}

function scp_fecha_opcional($valor): ?string
{
    $fecha = sm_limpiar_texto($valor);

    if ($fecha === '') {
        return null;
    }

    $objetoFecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    $errores = DateTimeImmutable::getLastErrors();

    if (
        !$objetoFecha
        || ($errores !== false && ($errores['warning_count'] > 0 || $errores['error_count'] > 0))
        || $objetoFecha->format('Y-m-d') !== $fecha
    ) {
        scp_error_campo('La fecha sugerida no es válida.', 'fecha_sugerida');
    }

    $hoy = new DateTimeImmutable('today', new DateTimeZone(SM_ZONA_HORARIA));
    $limite = $hoy->modify('+2 years');

    if ($objetoFecha < $hoy) {
        scp_error_campo(
            'La fecha sugerida no puede ser anterior a hoy.',
            'fecha_sugerida'
        );
    }

    if ($objetoFecha > $limite) {
        scp_error_campo(
            'La fecha sugerida no puede ser mayor a dos años.',
            'fecha_sugerida'
        );
    }

    return $fecha;
}

function scp_booleano($valor): int
{
    return in_array(
        strtolower(sm_limpiar_texto($valor)),
        ['1', 'true', 'on', 'si', 'sí'],
        true
    ) ? 1 : 0;
}

function scp_validar_catalogo_activo(
    PDO $conexion,
    string $tabla,
    int $id,
    string $campo,
    string $mensaje
): void {
    $tablasPermitidas = ['tipos_falla', 'causas_averia'];

    if (!in_array($tabla, $tablasPermitidas, true)) {
        throw new InvalidArgumentException('Catálogo no permitido.');
    }

    $stmt = $conexion->prepare(
        "SELECT id
         FROM `{$tabla}`
         WHERE id = :id
           AND activo = 1
         LIMIT 1"
    );

    $stmt->execute([':id' => $id]);

    if ($stmt->fetchColumn() === false) {
        scp_error_campo($mensaje, $campo, 409);
    }
}

function scp_emitir_form_token(): string
{
    $ahora = time();
    $tokens = isset($_SESSION['scp_form_tokens']) && is_array($_SESSION['scp_form_tokens'])
        ? $_SESSION['scp_form_tokens']
        : [];

    foreach ($tokens as $token => $creadoEn) {
        if (!is_string($token) || ($ahora - (int) $creadoEn) > 1800) {
            unset($tokens[$token]);
        }
    }

    if (count($tokens) >= 5) {
        asort($tokens);
        $tokens = array_slice($tokens, -4, null, true);
    }

    $token = bin2hex(random_bytes(32));
    $tokens[$token] = $ahora;
    $_SESSION['scp_form_tokens'] = $tokens;

    return $token;
}

function scp_consumir_form_token(string $token): void
{
    $tokens = isset($_SESSION['scp_form_tokens']) && is_array($_SESSION['scp_form_tokens'])
        ? $_SESSION['scp_form_tokens']
        : [];

    if (
        $token === ''
        || !isset($tokens[$token])
        || (time() - (int) $tokens[$token]) > 1800
    ) {
        sm_responder_json(
            false,
            'Este formulario ya fue enviado o venció. Actualiza la información e inténtalo nuevamente.',
            [
                'formulario_vencido' => true,
                'form_token' => scp_emitir_form_token(),
            ],
            409
        );
    }

    unset($tokens[$token]);
    $_SESSION['scp_form_tokens'] = $tokens;
}

function scp_bind_nullable_int(PDOStatement $stmt, string $parametro, ?int $valor): void
{
    if ($valor === null) {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, $valor, PDO::PARAM_INT);
}

function scp_bind_nullable_string(PDOStatement $stmt, string $parametro, ?string $valor): void
{
    if ($valor === null) {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, $valor, PDO::PARAM_STR);
}

function scp_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function scp_ip_cliente(): string
{
    return substr(
        sm_limpiar_texto($_SERVER['REMOTE_ADDR'] ?? ''),
        0,
        60
    );
}

function scp_user_agent(): string
{
    return substr(
        sm_limpiar_texto($_SERVER['HTTP_USER_AGENT'] ?? ''),
        0,
        255
    );
}

function scp_error_campo(
    string $mensaje, 
    string $campo,
    int $codigoHttp = 422
): void {
    sm_responder_json(
        false,
        $mensaje,
        ['campo' => $campo],
        $codigoHttp
    );
}