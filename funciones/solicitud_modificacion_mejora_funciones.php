<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Solicitud de modificación o mejora - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| - Disponible para ADMIN y SOLICITANTE.
| - El estado inicial siempre es PENDIENTE.
| - Las categorías y los datos técnicos complementarios son opcionales.
| - El equipo puede buscarse por código o por nombre; la ubicación se obtiene de su catálogo.
| - La fecha y hora se toman del servidor.
| - Compatible con PHP 7.3 o superior.
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
            smm_cargar_inicial($conexion);
            break;

        case 'buscar_equipo':
            sm_requerir_metodo('GET');
            smm_buscar_equipo($conexion);
            break;

        case 'crear':
            sm_requerir_metodo('POST');
            sm_validar_csrf();
            smm_crear_solicitud($conexion);
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

    error_log('[MODIFICACION O MEJORA][PDO] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la solicitud.',
        ['form_token' => smm_emitir_form_token()],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[MODIFICACION O MEJORA] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la solicitud.',
        ['form_token' => smm_emitir_form_token()],
        500
    );
}

/* =========================================================================
   ACCIONES
   ========================================================================= */

function smm_cargar_inicial(PDO $conexion): void
{
    $contexto = smm_contexto_sesion($conexion);

    $causasMejora = smm_consultar_todos(
        $conexion,
        "SELECT id, nombre
         FROM causas_mejora
         WHERE activo = 1
         ORDER BY nombre"
    );

    $solicitantes = [];

    if ($contexto['rol'] === 'ADMIN') {
        $solicitantes = smm_obtener_solicitantes_activos($conexion);
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
                'causas_mejora' => $causasMejora,
            ],
            'resumen' => smm_obtener_resumen(
                $conexion,
                $contexto['rol'],
                $contexto['usuario_id']
            ),
            'recientes' => smm_obtener_recientes(
                $conexion,
                $contexto['rol'],
                $contexto['usuario_id']
            ),
            'form_token' => smm_emitir_form_token(),
            'fecha_servidor' => date('Y-m-d'),
            'fecha_hora_servidor' => date('d/m/Y H:i'),
        ]
    );
}

function smm_buscar_equipo(PDO $conexion): void
{
    $termino = smm_normalizar_busqueda_equipo(
        $_GET['termino'] ?? ($_GET['codigo'] ?? '')
    );

    if ($termino === '') {
        smm_error_campo(
            'Escribe el código o parte del nombre del equipo.',
            'codigo_equipo'
        );
    }

    $longitud = smm_longitud($termino);

    if ($longitud < 2 || $longitud > 100) {
        smm_error_campo(
            'La búsqueda debe contener entre 2 y 100 caracteres.',
            'codigo_equipo'
        );
    }

    $codigoExacto = smm_normalizar_codigo($termino);
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

function smm_crear_solicitud(PDO $conexion): void
{
    $contexto = smm_contexto_sesion($conexion);
    $origen = smm_resolver_origen_solicitud(
        $conexion,
        $contexto,
        $_POST['solicitante_opcion'] ?? ''
    );

    $formToken = sm_limpiar_texto($_POST['form_token'] ?? '');

    $equipoId = smm_entero_requerido(
        $_POST['equipo_id'] ?? null,
        'equipo_id',
        'Busca el equipo por código o nombre y selecciona una opción válida.'
    );

    $codigoEquipo = smm_normalizar_codigo($_POST['codigo_equipo'] ?? '');

    if ($codigoEquipo === '') {
        smm_error_campo(
            'Busca y selecciona un equipo válido.',
            'codigo_equipo'
        );
    }

    $prioridad = strtoupper(sm_limpiar_texto($_POST['prioridad'] ?? 'MEDIA'));

    if (!in_array($prioridad, ['BAJA', 'MEDIA', 'ALTA'], true)) {
        smm_error_campo(
            'Selecciona una prioridad válida. Para una emergencia utiliza Correctivo urgente.',
            'prioridad'
        );
    }

    $fechaSugerida = smm_fecha_opcional($_POST['fecha_sugerida'] ?? '');

    $descripcion = smm_texto_requerido(
        $_POST['descripcion_solicitud'] ?? '',
        'descripcion_solicitud',
        'Describe claramente la modificación o mejora que necesitas.',
        20,
        2500
    );

    $objetivoMejora = smm_texto_opcional(
        $_POST['objetivo_mejora'] ?? '',
        'objetivo_mejora',
        1500
    );

    $resultadoEsperado = smm_texto_opcional(
        $_POST['resultado_esperado'] ?? '',
        'resultado_esperado',
        1500
    );

    $justificacionMejora = smm_texto_opcional(
        $_POST['justificacion_mejora'] ?? '',
        'justificacion_mejora',
        1800
    );

    $costoVsBeneficio = smm_texto_opcional(
        $_POST['costo_vs_beneficio'] ?? '',
        'costo_vs_beneficio',
        1800
    );

    $impactoOperacion = smm_texto_opcional(
        $_POST['impacto_operacion'] ?? '',
        'impacto_operacion',
        1500
    );

    $observaciones = smm_texto_opcional(
        $_POST['observaciones_solicitante'] ?? '',
        'observaciones_solicitante',
        1500
    );

    $trabajoPeligroso = smm_booleano($_POST['trabajo_peligroso'] ?? '0');
    $requiereParo = smm_booleano($_POST['requiere_paro_equipo'] ?? '0');
    $nivelRiesgo = strtoupper(sm_limpiar_texto($_POST['nivel_riesgo'] ?? 'BAJO'));
    $detalleTrabajoPeligroso = smm_texto_opcional(
        $_POST['detalle_trabajo_peligroso'] ?? '',
        'detalle_trabajo_peligroso',
        200
    );

    if (!in_array($nivelRiesgo, ['BAJO', 'MEDIO', 'ALTO'], true)) {
        $nivelRiesgo = 'BAJO';
    }
    if ($trabajoPeligroso === 0) {
        $nivelRiesgo = 'BAJO';
        $detalleTrabajoPeligroso = null;
    } else {
        if ($nivelRiesgo === 'BAJO') $nivelRiesgo = 'MEDIO';
        if ($detalleTrabajoPeligroso === null || mb_strlen($detalleTrabajoPeligroso, 'UTF-8') < 3) {
            sm_responder_json(false, 'Describe brevemente el peligro o la precaución necesaria.', ['campo' => 'detalle_trabajo_peligroso'], 422);
        }
    }

    $causasMejora = smm_ids_opcionales(
        $_POST['causas_mejora'] ?? [],
        'causas_mejora',
        10
    );

    $equipo = smm_validar_equipo(
        $conexion,
        $equipoId,
        $codigoEquipo
    );

    smm_validar_causas_mejora(
        $conexion,
        $causasMejora
    );

    smm_consumir_form_token($formToken);

    $duplicado = smm_buscar_duplicado_reciente(
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
                'form_token' => smm_emitir_form_token(),
            ],
            409
        );
    }

    $ultimoEnvio = (int) ($_SESSION['smm_ultimo_envio'] ?? 0);

    if ($ultimoEnvio > 0 && (time() - $ultimoEnvio) < 2) {
        sm_responder_json(
            false,
            'Espera un momento antes de registrar otra solicitud.',
            ['form_token' => smm_emitir_form_token()],
            429
        );
    }

    $ahora = new DateTimeImmutable(
        'now',
        new DateTimeZone(SM_ZONA_HORARIA)
    );
    $fechaSolicitud = $ahora->format('Y-m-d');
    $horaSolicitud = $ahora->format('H:i:s');

    $conexion->beginTransaction();

    $folio = smm_generar_folio(
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
            costo_vs_beneficio,
            impacto_operacion,
            objetivo_mejora,
            resultado_esperado,
            justificacion_mejora,
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
            'MODIFICACION_MEJORA',
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
            :costo_vs_beneficio,
            :impacto_operacion,
            :objetivo_mejora,
            :resultado_esperado,
            :justificacion_mejora,
            :observaciones_solicitante,
            :trabajo_peligroso,
            :detalle_trabajo_peligroso,
            :nivel_riesgo,
            :requiere_paro_equipo,
            1
        )"
    );

    $stmt->bindValue(':folio', $folio, PDO::PARAM_STR);
    smm_bind_nullable_int(
        $stmt,
        ':solicitante_id',
        $origen['solicitante_id']
    );
    smm_bind_nullable_int(
        $stmt,
        ':administrador_solicitante_id',
        $origen['administrador_solicitante_id']
    );
    $stmt->bindValue(
        ':creado_por_tipo',
        $contexto['rol'],
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':creado_por_id',
        $contexto['usuario_id'],
        PDO::PARAM_INT
    );
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
    $stmt->bindValue(':equipo_id', $equipoId, PDO::PARAM_INT);
    $stmt->bindValue(
        ':fecha_solicitud',
        $fechaSolicitud,
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':hora_solicitud',
        $horaSolicitud,
        PDO::PARAM_STR
    );
    smm_bind_nullable_string(
        $stmt,
        ':fecha_sugerida',
        $fechaSugerida
    );
    $stmt->bindValue(':prioridad', $prioridad, PDO::PARAM_STR);
    $stmt->bindValue(
        ':descripcion_solicitud',
        $descripcion,
        PDO::PARAM_STR
    );
    smm_bind_nullable_string(
        $stmt,
        ':costo_vs_beneficio',
        $costoVsBeneficio
    );
    smm_bind_nullable_string(
        $stmt,
        ':impacto_operacion',
        $impactoOperacion
    );
    smm_bind_nullable_string(
        $stmt,
        ':objetivo_mejora',
        $objetivoMejora
    );
    smm_bind_nullable_string(
        $stmt,
        ':resultado_esperado',
        $resultadoEsperado
    );
    smm_bind_nullable_string(
        $stmt,
        ':justificacion_mejora',
        $justificacionMejora
    );
    smm_bind_nullable_string(
        $stmt,
        ':observaciones_solicitante',
        $observaciones
    );
    $stmt->bindValue(':trabajo_peligroso', $trabajoPeligroso, PDO::PARAM_INT);
    smm_bind_nullable_string($stmt, ':detalle_trabajo_peligroso', $detalleTrabajoPeligroso);
    $stmt->bindValue(':nivel_riesgo', $nivelRiesgo, PDO::PARAM_STR);
    $stmt->bindValue(':requiere_paro_equipo', $requiereParo, PDO::PARAM_INT);
    $stmt->execute();

    $solicitudId = (int) $conexion->lastInsertId();

    smm_guardar_causas_mejora(
        $conexion,
        $solicitudId,
        $causasMejora
    );

    smm_registrar_historial(
        $conexion,
        $solicitudId,
        $contexto,
        $origen,
        $folio,
        (string) $equipo['codigo_equipo'],
        (string) $equipo['nombre_equipo']
    );

    smm_registrar_movimiento(
        $conexion,
        $contexto,
        $origen,
        $solicitudId,
        $folio,
        (string) $equipo['codigo_equipo']
    );

    smm_notificar_registro(
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

    $_SESSION['smm_ultimo_envio'] = time();

    sm_responder_json(
        true,
        'La solicitud de modificación o mejora fue registrada correctamente.',
        [
            'folio' => $folio,
            'solicitud_id' => $solicitudId,
            'estado' => 'PENDIENTE',
            'registrada_para' => $origen['nombre_completo'],
            'form_token' => smm_emitir_form_token(),
            'resumen' => smm_obtener_resumen(
                $conexion,
                $contexto['rol'],
                $contexto['usuario_id']
            ),
            'recientes' => smm_obtener_recientes(
                $conexion,
                $contexto['rol'],
                $contexto['usuario_id']
            ),
        ],
        201
    );
}

function smm_obtener_solicitante_activo(
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

        smm_error_campo(
            'El solicitante seleccionado ya no está disponible.',
            'solicitante_opcion',
            409
        );
    }

    $solicitante['nombre_completo'] = smm_nombre_completo($solicitante);

    return $solicitante;
}

function smm_contexto_sesion(PDO $conexion): array
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
        $perfil = smm_obtener_administrador_activo($conexion, (int) $usuarioId);
    } else {
        $perfil = smm_obtener_solicitante_activo($conexion, (int) $usuarioId);
    }

    return [
        'rol' => $rol,
        'usuario_id' => (int) $usuarioId,
        'perfil' => $perfil,
    ];
}

function smm_obtener_administrador_activo(PDO $conexion, int $administradorId): array
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

    $administrador['nombre_completo'] = smm_nombre_completo($administrador);
    $administrador['departamento'] = 'Administración';

    return $administrador;
}

function smm_obtener_solicitantes_activos(PDO $conexion): array
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
        $solicitante['nombre_completo'] = smm_nombre_completo($solicitante);
    }
    unset($solicitante);

    return $solicitantes;
}

function smm_resolver_origen_solicitud(
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
        smm_error_campo(
            'Selecciona a nombre de quién se registrará la solicitud.',
            'solicitante_opcion'
        );
    }

    $solicitanteId = (int) $coincidencia[1];
    $solicitante = smm_obtener_solicitante_activo(
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

function smm_nombre_completo(array $persona): string
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

function smm_validar_equipo(PDO $conexion, int $equipoId, string $codigo): array
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
        smm_error_campo(
            'El equipo seleccionado ya no es válido. Vuelve a buscarlo por código o nombre.',
            'codigo_equipo',
            409
        );
    }

    return $equipo;
}

function smm_obtener_resumen(
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
           AND tipo_solicitud = 'MODIFICACION_MEJORA'
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

function smm_obtener_recientes(
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
            ) AS nombre_solicitante,
            (
                SELECT GROUP_CONCAT(
                    cm.nombre
                    ORDER BY cm.nombre
                    SEPARATOR ', '
                )
                FROM solicitud_causas_mejora scm
                INNER JOIN causas_mejora cm
                    ON cm.id = scm.causa_mejora_id
                WHERE scm.solicitud_id = s.id
            ) AS causas_mejora
         FROM solicitudes s
         INNER JOIN equipos e
            ON e.id = s.equipo_id
         LEFT JOIN solicitantes so
            ON so.id = s.solicitante_id
         LEFT JOIN administradores ad
            ON ad.id = s.administrador_solicitante_id
         WHERE {$condicion}
           AND s.tipo_solicitud = 'MODIFICACION_MEJORA'
           AND s.activo = 1
         ORDER BY s.fecha_registro DESC, s.id DESC
         LIMIT 6"
    );

    $stmt->execute([':usuario_id' => $usuarioId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function smm_buscar_duplicado_reciente(
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
           AND tipo_solicitud = 'MODIFICACION_MEJORA'
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

function smm_consultar_todos(PDO $conexion, string $sql): array
{
    $stmt = $conexion->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* =========================================================================
   REGISTRO TRANSACCIONAL
   ========================================================================= */

function smm_generar_folio(PDO $conexion, int $anio): string
{
    $tipo = 'MODIFICACION_MEJORA';
    $prefijo = sprintf('MEJ-%04d-', $anio);

    $stmt = $conexion->prepare(
        "SELECT COALESCE(
            MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)),
            0
         )
         FROM solicitudes
         WHERE tipo_solicitud = 'MODIFICACION_MEJORA'
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

    return sprintf('MEJ-%04d-%05d', $anio, $numero);
}

function smm_registrar_historial(
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
            '%s registró la solicitud de modificación o mejora %s a nombre de %s para el equipo %s - %s.',
            $contexto['perfil']['nombre_completo'],
            $folio,
            $origen['nombre_completo'],
            $codigoEquipo,
            $nombreEquipo
        ),
    ]);
}

function smm_registrar_movimiento(
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
            'CREAR_SOLICITUD_MEJORA',
            'Modificación o mejora',
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
        ':ip_address' => smm_ip_cliente(),
        ':user_agent' => smm_user_agent(),
    ]);
}

function smm_notificar_registro(
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
            'Nueva solicitud de modificación o mejora',
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
                'Solicitud de mejora registrada por administración',
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

function smm_normalizar_busqueda_equipo($valor): string
{
    $termino = sm_limpiar_texto($valor);
    $termino = preg_replace('/\s+/u', ' ', $termino) ?? '';

    return trim($termino);
}

function smm_normalizar_codigo($valor): string
{
    $codigo = sm_limpiar_texto($valor);
    $codigo = preg_replace('/\s+/u', '', $codigo) ?? '';

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($codigo, 'UTF-8')
        : strtoupper($codigo);
}

function smm_entero_requerido($valor, string $campo, string $mensaje): int
{
    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($entero === false) {
        smm_error_campo($mensaje, $campo);
    }

    return (int) $entero;
}

function smm_entero_opcional($valor, string $campo): ?int
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
        smm_error_campo('Selecciona una opción válida.', $campo);
    }

    return (int) $entero;
}

function smm_texto_requerido(
    $valor,
    string $campo,
    string $mensaje,
    int $minimo,
    int $maximo
): string {
    $texto = sm_limpiar_texto($valor);
    $longitud = smm_longitud($texto);

    if ($texto === '' || $longitud < $minimo) {
        smm_error_campo(
            $mensaje . ' Usa al menos ' . $minimo . ' caracteres.',
            $campo
        );
    }

    if ($longitud > $maximo) {
        smm_error_campo(
            'El texto no puede superar ' . $maximo . ' caracteres.',
            $campo
        );
    }

    return $texto;
}

function smm_texto_opcional($valor, string $campo, int $maximo): ?string
{
    $texto = sm_limpiar_texto($valor);

    if ($texto === '') {
        return null;
    }

    if (smm_longitud($texto) > $maximo) {
        smm_error_campo(
            'El texto no puede superar ' . $maximo . ' caracteres.',
            $campo
        );
    }

    return $texto;
}

function smm_fecha_opcional($valor): ?string
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
        smm_error_campo('La fecha sugerida no es válida.', 'fecha_sugerida');
    }

    $hoy = new DateTimeImmutable('today', new DateTimeZone(SM_ZONA_HORARIA));
    $limite = $hoy->modify('+2 years');

    if ($objetoFecha < $hoy) {
        smm_error_campo(
            'La fecha sugerida no puede ser anterior a hoy.',
            'fecha_sugerida'
        );
    }

    if ($objetoFecha > $limite) {
        smm_error_campo(
            'La fecha sugerida no puede ser mayor a dos años.',
            'fecha_sugerida'
        );
    }

    return $fecha;
}

function smm_booleano($valor): int
{
    return in_array(
        strtolower(sm_limpiar_texto($valor)),
        ['1', 'true', 'on', 'si', 'sí'],
        true
    ) ? 1 : 0;
}

function smm_ids_opcionales(
    $valor,
    string $campo,
    int $maximo
): array {
    if ($valor === null || $valor === '') {
        return [];
    }

    $lista = is_array($valor) ? $valor : [$valor];

    if (count($lista) > $maximo) {
        smm_error_campo(
            'Selecciona como máximo ' . $maximo . ' opciones.',
            $campo
        );
    }

    $ids = [];

    foreach ($lista as $item) {
        $entero = filter_var(
            $item,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($entero === false) {
            smm_error_campo(
                'Una de las categorías seleccionadas no es válida.',
                $campo
            );
        }

        $ids[(int) $entero] = (int) $entero;
    }

    return array_values($ids);
}

function smm_validar_causas_mejora(
    PDO $conexion,
    array $causasMejora
): void {
    if (!$causasMejora) {
        return;
    }

    $marcadores = implode(
        ',',
        array_fill(0, count($causasMejora), '?')
    );

    $stmt = $conexion->prepare(
        "SELECT id
         FROM causas_mejora
         WHERE activo = 1
           AND id IN ({$marcadores})"
    );

    foreach ($causasMejora as $indice => $causaId) {
        $stmt->bindValue(
            $indice + 1,
            (int) $causaId,
            PDO::PARAM_INT
        );
    }

    $stmt->execute();
    $encontradas = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    if (count($encontradas) !== count($causasMejora)) {
        smm_error_campo(
            'Una de las categorías de mejora seleccionadas ya no está disponible.',
            'causas_mejora',
            409
        );
    }
}

function smm_guardar_causas_mejora(
    PDO $conexion,
    int $solicitudId,
    array $causasMejora
): void {
    if (!$causasMejora) {
        return;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO solicitud_causas_mejora
        (
            solicitud_id,
            causa_mejora_id,
            observaciones
        )
        VALUES
        (
            :solicitud_id,
            :causa_mejora_id,
            NULL
        )"
    );

    foreach ($causasMejora as $causaId) {
        $stmt->execute([
            ':solicitud_id' => $solicitudId,
            ':causa_mejora_id' => (int) $causaId,
        ]);
    }
}

function smm_emitir_form_token(): string
{
    $ahora = time();
    $tokens = isset($_SESSION['smm_form_tokens']) && is_array($_SESSION['smm_form_tokens'])
        ? $_SESSION['smm_form_tokens']
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
    $_SESSION['smm_form_tokens'] = $tokens;

    return $token;
}

function smm_consumir_form_token(string $token): void
{
    $tokens = isset($_SESSION['smm_form_tokens']) && is_array($_SESSION['smm_form_tokens'])
        ? $_SESSION['smm_form_tokens']
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
                'form_token' => smm_emitir_form_token(),
            ],
            409
        );
    }

    unset($tokens[$token]);
    $_SESSION['smm_form_tokens'] = $tokens;
}

function smm_bind_nullable_int(PDOStatement $stmt, string $parametro, ?int $valor): void
{
    if ($valor === null) {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, $valor, PDO::PARAM_INT);
}

function smm_bind_nullable_string(PDOStatement $stmt, string $parametro, ?string $valor): void
{
    if ($valor === null) {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, $valor, PDO::PARAM_STR);
}

function smm_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function smm_ip_cliente(): string
{
    return substr(
        sm_limpiar_texto($_SERVER['REMOTE_ADDR'] ?? ''),
        0,
        60
    );
}

function smm_user_agent(): string
{ 
    return substr(
        sm_limpiar_texto($_SERVER['HTTP_USER_AGENT'] ?? ''),
        0,
        255
    );
}

function smm_error_campo(
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