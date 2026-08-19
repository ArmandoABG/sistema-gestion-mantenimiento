<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Solicitud de correctivo urgente - Sistema de Mantenimiento 1.1
|--------------------------------------------------------------------------
| - Disponible para ADMIN y SOLICITANTE.
| - Se publica inmediatamente con estado AGENDADO.
| - Queda pendiente de revisión administrativa sin detener su atención.
| - Todos los técnicos activos reciben una notificación URGENTE.
| - El solicitante describe síntomas e impacto; el técnico captura tipo y
|   causa de falla al iniciar la atención.
| - Los técnicos aceptarán directamente; aquí no se asigna a ninguno.
| - El límite se toma de MAX_TECNICOS_URGENTE y nunca supera 10.
| - La fecha y hora se obtienen del servidor.
| - Compatible con PHP 7.4 o superior.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';
require_once __DIR__ . '/recursos_mantenimiento_servicio.php';

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
            scu_cargar_inicial($conexion);
            break;

        case 'buscar_equipo':
            sm_requerir_metodo('GET');
            scu_buscar_equipo($conexion);
            break;

        case 'crear':
            sm_requerir_metodo('POST');
            sm_validar_csrf();
            scu_crear_solicitud($conexion);
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

    error_log('[CORRECTIVO URGENTE][PDO] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la urgencia.',
        ['form_token' => scu_emitir_form_token()],
        500
    );
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('[CORRECTIVO URGENTE] ' . $e->getMessage());

    sm_responder_json(
        false,
        'Ocurrió un error interno al procesar la urgencia.',
        ['form_token' => scu_emitir_form_token()],
        500
    );
}

/* =========================================================================
   ACCIONES
   ========================================================================= */

function scu_cargar_inicial(PDO $conexion): void
{
    $contexto = scu_contexto_sesion($conexion);

    $solicitantes = [];

    if ($contexto['rol'] === 'ADMIN') {
        $solicitantes = scu_obtener_solicitantes_activos($conexion);
    }

    $limiteTecnicos = scu_obtener_limite_tecnicos($conexion);

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
            'configuracion' => [
                'max_tecnicos_urgente' => $limiteTecnicos,
            ],
            'resumen' => scu_obtener_resumen(
                $conexion,
                $contexto['rol'],
                $contexto['usuario_id']
            ),
            'recientes' => scu_obtener_recientes(
                $conexion,
                $contexto['rol'],
                $contexto['usuario_id']
            ),
            'form_token' => scu_emitir_form_token(),
            'fecha_servidor' => date('Y-m-d'),
            'fecha_hora_servidor' => date('d/m/Y H:i'),
        ]
    );
}

function scu_buscar_equipo(PDO $conexion): void
{
    $termino = scu_normalizar_busqueda_equipo(
        $_GET['termino'] ?? ($_GET['codigo'] ?? '')
    );

    if ($termino === '') {
        scu_error_campo(
            'Escribe el código o parte del nombre del equipo.',
            'codigo_equipo'
        );
    }

    $longitud = scu_longitud($termino);

    if ($longitud < 2 || $longitud > 100) {
        scu_error_campo(
            'La búsqueda debe contener entre 2 y 100 caracteres.',
            'codigo_equipo'
        );
    }

    $codigoExacto = scu_normalizar_codigo($termino);
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
           AND a.departamento_id = e.departamento_id

         LEFT JOIN procesos p
            ON p.id = e.proceso_id
           AND p.activo = 1
           AND p.area_id = e.area_id

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

function scu_crear_solicitud(PDO $conexion): void
{
    $contexto = scu_contexto_sesion($conexion);
    $origen = scu_resolver_origen_solicitud(
        $conexion,
        $contexto,
        $_POST['solicitante_opcion'] ?? ''
    );

    $formToken = sm_limpiar_texto($_POST['form_token'] ?? '');

    $equipoId = scu_entero_requerido(
        $_POST['equipo_id'] ?? null,
        'equipo_id',
        'Busca el equipo por código o nombre y selecciona una opción válida.'
    );

    $descripcion = scu_texto_requerido(
        $_POST['descripcion_solicitud'] ?? '',
        'descripcion_solicitud',
        'Describe claramente qué ocurrió y por qué requiere atención inmediata.',
        20,
        2500
    );

    $descripcionFalla = scu_texto_requerido(
        $_POST['descripcion_falla'] ?? '',
        'descripcion_falla',
        'Describe los síntomas, señales o condición actual del equipo.',
        10,
        1800
    );

    $impactoOperacion = scu_texto_requerido(
        $_POST['impacto_operacion'] ?? '',
        'impacto_operacion',
        'Describe cómo afecta la falla a la operación, producción, calidad o seguridad.',
        10,
        1800
    );

    $trabajoPeligroso = scu_booleano_requerido(
        $_POST['trabajo_peligroso'] ?? null,
        'trabajo_peligroso',
        'Indica si la atención implica trabajo peligroso.'
    );

    $detalleTrabajoPeligroso = scu_validar_detalle_trabajo_peligroso(
        $_POST['detalle_trabajo_peligroso'] ?? '',
        $trabajoPeligroso
    );

    $requiereParo = scu_booleano_requerido(
        $_POST['requiere_paro_equipo'] ?? null,
        'requiere_paro_equipo',
        'Indica si el equipo debe permanecer detenido.'
    );

    $nivelRiesgo = strtoupper(
        sm_limpiar_texto($_POST['nivel_riesgo'] ?? '')
    );

    if (!in_array($nivelRiesgo, ['BAJO', 'MEDIO', 'ALTO'], true)) {
        scu_error_campo(
            'Selecciona un nivel de riesgo válido.',
            'nivel_riesgo'
        );
    }

    $observaciones = scu_texto_opcional(
        $_POST['observaciones_solicitante'] ?? '',
        'observaciones_solicitante',
        1500
    );

    $equipo = scu_validar_equipo(
        $conexion,
        $equipoId
    );
    $codigoEquipoValidado = trim((string) ($equipo['codigo_equipo'] ?? ''));

    if ($codigoEquipoValidado === '') {
        $codigoEquipoValidado = 'Sin código';
    }

    $resultadoAnterior = scu_consumir_form_token($formToken);

    // Si el navegador reintentó exactamente el mismo formulario, devolvemos
    // el resultado original. No se inserta una segunda urgencia y tampoco se
    // muestra un error falso al usuario.
    if ($resultadoAnterior !== null) {
        $resultadoAnterior['form_token'] = scu_emitir_form_token();
        $resultadoAnterior['resumen'] = scu_obtener_resumen(
            $conexion,
            $contexto['rol'],
            $contexto['usuario_id']
        );
        $resultadoAnterior['recientes'] = scu_obtener_recientes(
            $conexion,
            $contexto['rol'],
            $contexto['usuario_id']
        );

        sm_responder_json(
            true,
            'La urgencia ya había sido publicada correctamente.',
            $resultadoAnterior,
            200
        );
    }

    $duplicado = scu_buscar_duplicado_reciente(
        $conexion,
        $origen,
        $equipoId,
        $descripcion
    );

    if ($duplicado !== null) {
        sm_responder_json(
            false,
            'Esta misma urgencia ya fue registrada recientemente con el folio ' . $duplicado . '.',
            [
                'folio_existente' => $duplicado,
                'form_token' => scu_emitir_form_token(),
            ],
            409
        );
    }

    $ultimoEnvio = (int) ($_SESSION['scu_ultimo_envio'] ?? 0);

    if ($ultimoEnvio > 0 && (time() - $ultimoEnvio) < 2) {
        sm_responder_json(
            false,
            'Espera un momento antes de registrar otra urgencia.',
            ['form_token' => scu_emitir_form_token()],
            429
        );
    }

    $ahora = new DateTimeImmutable(
        'now',
        new DateTimeZone(SM_ZONA_HORARIA)
    );
    $fechaSolicitud = $ahora->format('Y-m-d');
    $horaSolicitud = $ahora->format('H:i:s');
    $limiteTecnicos = scu_obtener_limite_tecnicos($conexion);

    $conexion->beginTransaction();

    $folio = scu_generar_folio(
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
            prioridad,
            descripcion_solicitud,
            tipo_falla_id,
            causa_averia_id,
            descripcion_falla,
            causa_desconocida_descripcion,
            impacto_operacion,
            observaciones_solicitante,
            trabajo_peligroso,
            detalle_trabajo_peligroso,
            nivel_riesgo,
            requiere_paro_equipo,
            cupo_tecnicos_urgente,
            activo
        )
        VALUES
        (
            :folio,
            'CORRECTIVO_URGENTE',
            'AGENDADO',
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
            'URGENTE',
            :descripcion_solicitud,
            NULL,
            NULL,
            :descripcion_falla,
            NULL,
            :impacto_operacion,
            :observaciones_solicitante,
            :trabajo_peligroso,
            :detalle_trabajo_peligroso,
            :nivel_riesgo,
            :requiere_paro_equipo,
            :cupo_tecnicos_urgente,
            1
        )"
    );

    $stmt->bindValue(':folio', $folio, PDO::PARAM_STR);
    scu_bind_nullable_int(
        $stmt,
        ':solicitante_id',
        $origen['solicitante_id']
    );
    scu_bind_nullable_int(
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
    $stmt->bindValue(':descripcion_solicitud', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':descripcion_falla', $descripcionFalla, PDO::PARAM_STR);
    $stmt->bindValue(':impacto_operacion', $impactoOperacion, PDO::PARAM_STR);
    scu_bind_nullable_string(
        $stmt,
        ':observaciones_solicitante',
        $observaciones
    );
    $stmt->bindValue(':trabajo_peligroso', $trabajoPeligroso, PDO::PARAM_INT);
    scu_bind_nullable_string(
        $stmt,
        ':detalle_trabajo_peligroso',
        $detalleTrabajoPeligroso
    );
    $stmt->bindValue(':nivel_riesgo', $nivelRiesgo, PDO::PARAM_STR);
    $stmt->bindValue(':requiere_paro_equipo', $requiereParo, PDO::PARAM_INT);
    $stmt->bindValue(':cupo_tecnicos_urgente', $limiteTecnicos, PDO::PARAM_INT);
    $stmt->execute();

    $solicitudId = (int) $conexion->lastInsertId();

    // La primera urgencia de un equipo puede quedar sin recomendaciones.
    // Cuando ya existe memoria de urgencias anteriores, se copia como una
    // fotografía propia para que cambios futuros no alteren esta solicitud.
    $adminMemoriaId = $contexto['rol'] === 'ADMIN'
        ? (int) $contexto['usuario_id']
        : null;

    $recursosCopiados = rsm_copiar_memoria_a_solicitud(
        $conexion,
        $solicitudId,
        $equipoId,
        'CORRECTIVO_URGENTE',
        $adminMemoriaId
    );

    scu_registrar_historial_creacion(
        $conexion,
        $solicitudId,
        $contexto,
        $origen,
        $folio,
        $codigoEquipoValidado,
        (string) $equipo['nombre_equipo']
    );

    scu_registrar_historial_publicacion(
        $conexion,
        $solicitudId,
        $contexto,
        $folio,
        $limiteTecnicos
    );

    scu_registrar_movimiento(
        $conexion,
        $contexto,
        $origen,
        $solicitudId,
        $folio,
        $codigoEquipoValidado
    );

    scu_notificar_registro(
        $conexion,
        $solicitudId,
        $contexto,
        $origen,
        $folio,
        $codigoEquipoValidado,
        (string) $equipo['nombre_equipo'],
        $descripcionFalla,
        $nivelRiesgo,
        $requiereParo,
        $limiteTecnicos
    );

    $conexion->commit();

    $_SESSION['scu_ultimo_envio'] = time();

    $resultadoCreacion = [
        'folio' => $folio,
        'solicitud_id' => $solicitudId,
        'estado' => 'AGENDADO',
        'registrada_para' => $origen['nombre_completo'],
        'limite_tecnicos' => $limiteTecnicos,
        'recursos_recomendados_copiados' => $recursosCopiados,
    ];

    scu_recordar_resultado_token($formToken, $resultadoCreacion);

    $resultadoCreacion['form_token'] = scu_emitir_form_token();
    $resultadoCreacion['resumen'] = scu_obtener_resumen(
        $conexion,
        $contexto['rol'],
        $contexto['usuario_id']
    );
    $resultadoCreacion['recientes'] = scu_obtener_recientes(
        $conexion,
        $contexto['rol'],
        $contexto['usuario_id']
    );

    sm_responder_json(
        true,
        'La urgencia fue publicada correctamente y los técnicos activos fueron notificados.',
        $resultadoCreacion,
        201
    );
}

/* =========================================================================
   USUARIOS Y ORIGEN
   ========================================================================= */

function scu_contexto_sesion(PDO $conexion): array
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

    $perfil = $rol === 'ADMIN'
        ? scu_obtener_administrador_activo($conexion, (int) $usuarioId)
        : scu_obtener_solicitante_activo($conexion, (int) $usuarioId);

    return [
        'rol' => $rol,
        'usuario_id' => (int) $usuarioId,
        'perfil' => $perfil,
    ];
}

function scu_obtener_administrador_activo(PDO $conexion, int $administradorId): array
{
    $stmt = $conexion->prepare(
        "SELECT id, usuario, nombre, apellido_paterno, apellido_materno
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

    $administrador['nombre_completo'] = scu_nombre_completo($administrador);
    $administrador['departamento'] = 'Administración';

    return $administrador;
}

function scu_obtener_solicitante_activo(
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

        scu_error_campo(
            'El solicitante seleccionado ya no está disponible.',
            'solicitante_opcion',
            409
        );
    }

    $solicitante['nombre_completo'] = scu_nombre_completo($solicitante);

    return $solicitante;
}

function scu_obtener_solicitantes_activos(PDO $conexion): array
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
         ORDER BY s.nombre, s.apellido_paterno, s.apellido_materno, s.id"
    );
    $stmt->execute();
    $solicitantes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($solicitantes as &$solicitante) {
        $solicitante['id'] = (int) ($solicitante['id'] ?? 0);
        $solicitante['nombre_completo'] = scu_nombre_completo($solicitante);
    }
    unset($solicitante);

    return $solicitantes;
}

function scu_resolver_origen_solicitud(
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
        scu_error_campo(
            'Selecciona a nombre de quién se registrará la urgencia.',
            'solicitante_opcion'
        );
    }

    $solicitanteId = (int) $coincidencia[1];
    $solicitante = scu_obtener_solicitante_activo(
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

function scu_nombre_completo(array $persona): string
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

/* =========================================================================
   EQUIPOS, CATÁLOGOS Y CONSULTAS
   ========================================================================= */

function scu_validar_equipo(PDO $conexion, int $equipoId): array
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
           AND e.activo = 1
         LIMIT 1"
    );
    $stmt->execute([
        ':id' => $equipoId,
    ]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipo) {
        scu_error_campo(
            'El equipo seleccionado ya no es válido. Vuelve a buscarlo por código o nombre.',
            'codigo_equipo',
            409
        );
    }

    return $equipo;
}

function scu_obtener_catalogo_activo(
    PDO $conexion,
    string $tabla,
    int $id,
    string $campo,
    string $mensaje
): array {
    if (!in_array($tabla, ['tipos_falla', 'causas_averia'], true)) {
        throw new InvalidArgumentException('Catálogo no permitido.');
    }

    $stmt = $conexion->prepare(
        "SELECT id, nombre
         FROM `{$tabla}`
         WHERE id = :id
           AND activo = 1
         LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fila) {
        scu_error_campo($mensaje, $campo, 409);
    }

    return $fila;
}

function scu_causa_requiere_explicacion(string $nombre): bool
{
    return preg_match(
        '/pendiente|desconoc|por determinar|no identific/iu',
        $nombre
    ) === 1;
}

function scu_obtener_limite_tecnicos(PDO $conexion): int
{
    $stmt = $conexion->prepare(
        "SELECT valor
         FROM configuracion_sistema
         WHERE clave = 'MAX_TECNICOS_URGENTE'
         LIMIT 1"
    );
    $stmt->execute();
    $valor = $stmt->fetchColumn();
    $limite = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 10]]
    );

    return $limite === false ? 10 : (int) $limite;
}

function scu_consultar_todos(PDO $conexion, string $sql): array
{
    $stmt = $conexion->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function scu_obtener_resumen(
    PDO $conexion,
    string $rol,
    int $usuarioId
): array {
    $condicion = $rol === 'ADMIN'
        ? "creado_por_tipo = 'ADMIN' AND creado_por_id = :usuario_id"
        : 'solicitante_id = :usuario_id';

    $stmt = $conexion->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE
                WHEN revisado_por_admin_id IS NULL
                 AND estado IN ('AGENDADO', 'EN_PROCESO', 'PAUSADO', 'ATRASADO')
                THEN 1 ELSE 0 END
            ) AS sin_revisar,
            SUM(CASE WHEN estado IN ('AGENDADO', 'ATRASADO') THEN 1 ELSE 0 END) AS publicadas,
            SUM(CASE WHEN estado IN ('EN_PROCESO', 'PAUSADO') THEN 1 ELSE 0 END) AS en_atencion,
            SUM(CASE WHEN estado IN ('TERMINADO', 'RECHAZADO', 'CANCELADO') THEN 1 ELSE 0 END) AS cerradas
         FROM solicitudes
         WHERE {$condicion}
           AND tipo_solicitud = 'CORRECTIVO_URGENTE'
           AND activo = 1"
    );
    $stmt->execute([':usuario_id' => $usuarioId]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total' => (int) ($fila['total'] ?? 0),
        'sin_revisar' => (int) ($fila['sin_revisar'] ?? 0),
        'publicadas' => (int) ($fila['publicadas'] ?? 0),
        'en_atencion' => (int) ($fila['en_atencion'] ?? 0),
        'cerradas' => (int) ($fila['cerradas'] ?? 0),
    ];
}

function scu_obtener_recientes(
    PDO $conexion,
    string $rol,
    int $usuarioId
): array {
    $condicion = $rol === 'ADMIN'
        ? "s.creado_por_tipo = 'ADMIN' AND s.creado_por_id = :usuario_id"
        : 's.solicitante_id = :usuario_id';

    $stmt = $conexion->prepare(
        "SELECT
            s.id,
            s.folio,
            s.estado,
            s.prioridad,
            s.descripcion_solicitud,
            s.trabajo_peligroso,
            s.detalle_trabajo_peligroso,
            s.nivel_riesgo,
            s.requiere_paro_equipo,
            s.cupo_tecnicos_urgente,
            s.revisado_por_admin_id,
            DATE_FORMAT(s.fecha_registro, '%d/%m/%Y %H:%i') AS fecha_registro_formato,
            e.codigo_equipo,
            e.nombre_equipo,
            COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ', so.nombre, so.apellido_paterno, so.apellido_materno)), ''),
                NULLIF(TRIM(CONCAT_WS(' ', ad.nombre, ad.apellido_paterno, ad.apellido_materno)), ''),
                'Sin solicitante'
            ) AS nombre_solicitante,
            (
                SELECT COUNT(*)
                FROM solicitud_tecnicos st
                WHERE st.solicitud_id = s.id
                  AND st.origen = 'ACEPTACION_URGENTE'
                  AND st.activo = 1
            ) AS tecnicos_aceptaron
         FROM solicitudes s
         INNER JOIN equipos e
            ON e.id = s.equipo_id
         LEFT JOIN solicitantes so
            ON so.id = s.solicitante_id
         LEFT JOIN administradores ad
            ON ad.id = s.administrador_solicitante_id
         WHERE {$condicion}
           AND s.tipo_solicitud = 'CORRECTIVO_URGENTE'
           AND s.activo = 1
         ORDER BY s.fecha_registro DESC, s.id DESC
         LIMIT 6"
    );
    $stmt->execute([':usuario_id' => $usuarioId]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($filas as &$fila) {
        $fila['cupo_tecnicos_urgente'] = (int) ($fila['cupo_tecnicos_urgente'] ?? 10);
        $fila['tecnicos_aceptaron'] = (int) ($fila['tecnicos_aceptaron'] ?? 0);
        $fila['revisada'] = (int) ($fila['revisado_por_admin_id'] ?? 0) > 0;
    }
    unset($fila);

    return $filas;
}

function scu_buscar_duplicado_reciente(
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
           AND tipo_solicitud = 'CORRECTIVO_URGENTE'
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

/* =========================================================================
   REGISTRO TRANSACCIONAL
   ========================================================================= */

function scu_generar_folio(PDO $conexion, int $anio): string
{
    $tipo = 'CORRECTIVO_URGENTE';
    $prefijo = sprintf('MCU-%04d-', $anio);

    $stmt = $conexion->prepare(
        "SELECT COALESCE(
            MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)),
            0
         )
         FROM solicitudes
         WHERE tipo_solicitud = 'CORRECTIVO_URGENTE'
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
        throw new RuntimeException('No fue posible generar el folio urgente.');
    }

    return sprintf('MCU-%04d-%05d', $anio, $numero);
}

function scu_registrar_historial_creacion(
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
            'AGENDADO',
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
            '%s registró la urgencia %s a nombre de %s para el equipo %s - %s.',
            $contexto['perfil']['nombre_completo'],
            $folio,
            $origen['nombre_completo'],
            $codigoEquipo,
            $nombreEquipo
        ),
    ]);
}

function scu_registrar_historial_publicacion(
    PDO $conexion,
    int $solicitudId,
    array $contexto,
    string $folio,
    int $limiteTecnicos
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
            'URGENTE_PUBLICADA',
            'AGENDADO',
            'AGENDADO',
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
            'La urgencia %s se publicó para aceptación directa de hasta %d técnicos activos.',
            $folio,
            $limiteTecnicos
        ),
    ]);
}

function scu_registrar_movimiento(
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
            'CREAR_SOLICITUD_URGENTE',
            'Solicitud correctivo urgente',
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
            'Se registró y publicó la urgencia %s a nombre de %s para el equipo %s.',
            $folio,
            $origen['nombre_completo'],
            $codigoEquipo
        ),
        ':registro_id' => $solicitudId,
        ':ip_address' => scu_ip_cliente(),
        ':user_agent' => scu_user_agent(),
    ]);
}

function scu_notificar_registro(
    PDO $conexion,
    int $solicitudId,
    array $contexto,
    array $origen,
    string $folio,
    string $codigoEquipo,
    string $nombreEquipo,
    string $descripcionFalla,
    string $nivelRiesgo,
    int $requiereParo,
    int $limiteTecnicos
): void {
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
            'Nueva urgencia publicada',
            :mensaje,
            'URGENTE',
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
            'La urgencia %s, registrada para %s, ya está disponible para técnicos y requiere revisión administrativa. Equipo: %s - %s. Riesgo: %s.',
            $folio,
            $origen['nombre_completo'],
            $codigoEquipo,
            $nombreEquipo,
            $nivelRiesgo
        ),
    ];

    if ($contexto['rol'] === 'ADMIN') {
        $parametros[':admin_actual'] = $contexto['usuario_id'];
    }

    $stmt->execute($parametros);

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
        SELECT
            'TECNICO',
            t.id,
            :solicitud_id,
            'Nueva urgencia disponible',
            :mensaje,
            'URGENTE',
            0
        FROM tecnicos t
        WHERE t.activo = 1"
    );
    $stmt->execute([
        ':solicitud_id' => $solicitudId,
        ':mensaje' => sprintf(
            '%s requiere atención inmediata. Equipo: %s - %s. Síntomas reportados: %s. Riesgo: %s. Paro requerido: %s. Pueden aceptar hasta %d técnicos.',
            $folio,
            $codigoEquipo,
            $nombreEquipo,
            scu_resumir_texto($descripcionFalla, 220),
            $nivelRiesgo,
            $requiereParo === 1 ? 'Sí' : 'No',
            $limiteTecnicos
        ),
    ]);

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
                'Urgencia registrada por administración',
                :mensaje,
                'URGENTE',
                0
            )"
        );
        $stmt->execute([
            ':usuario_id' => $origen['solicitante_id'],
            ':solicitud_id' => $solicitudId,
            ':mensaje' => sprintf(
                'El administrador %s registró y publicó la urgencia %s a tu nombre para el equipo %s - %s.',
                $contexto['perfil']['nombre_completo'],
                $folio,
                $codigoEquipo,
                $nombreEquipo
            ),
        ]);
    }
}

/* =========================================================================
   VALIDACIÓN Y UTILIDADES
   ========================================================================= */

function scu_normalizar_busqueda_equipo($valor): string
{
    $termino = sm_limpiar_texto($valor);
    $termino = preg_replace('/\s+/u', ' ', $termino) ?? '';

    return trim($termino);
}

function scu_normalizar_codigo($valor): string
{
    $codigo = sm_limpiar_texto($valor);
    $codigo = preg_replace('/\s+/u', '', $codigo) ?? '';

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($codigo, 'UTF-8')
        : strtoupper($codigo);
}

function scu_entero_requerido($valor, string $campo, string $mensaje): int
{
    $entero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($entero === false) {
        scu_error_campo($mensaje, $campo);
    }

    return (int) $entero;
}

function scu_texto_requerido(
    $valor,
    string $campo,
    string $mensaje,
    int $minimo,
    int $maximo
): string {
    $texto = sm_limpiar_texto($valor);
    $longitud = scu_longitud($texto);

    if ($texto === '' || $longitud < $minimo) {
        scu_error_campo(
            $mensaje . ' Usa al menos ' . $minimo . ' caracteres.',
            $campo
        );
    }

    if ($longitud > $maximo) {
        scu_error_campo(
            'El texto no puede superar ' . $maximo . ' caracteres.',
            $campo
        );
    }

    return $texto;
}

function scu_texto_opcional($valor, string $campo, int $maximo): ?string
{
    $texto = sm_limpiar_texto($valor);

    if ($texto === '') {
        return null;
    }

    if (scu_longitud($texto) > $maximo) {
        scu_error_campo(
            'El texto no puede superar ' . $maximo . ' caracteres.',
            $campo
        );
    }

    return $texto;
}

function scu_validar_detalle_trabajo_peligroso(
    $valor,
    int $trabajoPeligroso
): ?string {
    $detalle = sm_limpiar_texto((string) $valor);

    if ($trabajoPeligroso !== 1) {
        return null;
    }

    $longitud = scu_longitud($detalle);

    if ($longitud < 3 || $longitud > 200) {
        scu_error_campo(
            'Describe brevemente el peligro o la precaución necesaria (3 a 200 caracteres).',
            'detalle_trabajo_peligroso'
        );
    }

    return $detalle;
}

function scu_booleano_requerido(
    $valor,
    string $campo,
    string $mensaje
): int {
    $texto = strtolower(sm_limpiar_texto($valor));

    if (!in_array($texto, ['0', '1'], true)) {
        scu_error_campo($mensaje, $campo);
    }

    return $texto === '1' ? 1 : 0;
}

function scu_emitir_form_token(): string
{
    $ahora = time();
    $tokens = isset($_SESSION['scu_form_tokens']) && is_array($_SESSION['scu_form_tokens'])
        ? $_SESSION['scu_form_tokens']
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
    $_SESSION['scu_form_tokens'] = $tokens;

    return $token;
}

function scu_consumir_form_token(string $token): ?array
{
    $ahora = time();
    $resultados = isset($_SESSION['scu_form_token_resultados'])
        && is_array($_SESSION['scu_form_token_resultados'])
        ? $_SESSION['scu_form_token_resultados']
        : [];

    foreach ($resultados as $tokenUsado => $registro) {
        $creadoEn = is_array($registro)
            ? (int) ($registro['creado_en'] ?? 0)
            : 0;

        if (!is_string($tokenUsado) || $creadoEn <= 0 || ($ahora - $creadoEn) > 1800) {
            unset($resultados[$tokenUsado]);
        }
    }

    $_SESSION['scu_form_token_resultados'] = $resultados;

    if (
        $token !== ''
        && isset($resultados[$token])
        && is_array($resultados[$token])
        && isset($resultados[$token]['datos'])
        && is_array($resultados[$token]['datos'])
    ) {
        return $resultados[$token]['datos'];
    }

    $tokens = isset($_SESSION['scu_form_tokens']) && is_array($_SESSION['scu_form_tokens'])
        ? $_SESSION['scu_form_tokens']
        : [];

    if (
        $token === ''
        || !isset($tokens[$token])
        || ($ahora - (int) $tokens[$token]) > 1800
    ) {
        sm_responder_json(
            false,
            'Este formulario ya fue enviado o venció. Actualiza la información e inténtalo nuevamente.',
            [
                'formulario_vencido' => true,
                'form_token' => scu_emitir_form_token(),
            ],
            409
        );
    }

    unset($tokens[$token]);
    $_SESSION['scu_form_tokens'] = $tokens;

    return null;
}

/**
 * @param array<string,mixed> $datos
 */
function scu_recordar_resultado_token(string $token, array $datos): void
{
    if ($token === '') {
        return;
    }

    $resultados = isset($_SESSION['scu_form_token_resultados'])
        && is_array($_SESSION['scu_form_token_resultados'])
        ? $_SESSION['scu_form_token_resultados']
        : [];

    $resultados[$token] = [
        'creado_en' => time(),
        'datos' => $datos,
    ];

    if (count($resultados) > 10) {
        uasort(
            $resultados,
            static function ($a, $b): int {
                $tiempoA = is_array($a) ? (int) ($a['creado_en'] ?? 0) : 0;
                $tiempoB = is_array($b) ? (int) ($b['creado_en'] ?? 0) : 0;

                return $tiempoA <=> $tiempoB;
            }
        );
        $resultados = array_slice($resultados, -10, null, true);
    }

    $_SESSION['scu_form_token_resultados'] = $resultados;
}

function scu_bind_nullable_int(PDOStatement $stmt, string $parametro, ?int $valor): void
{
    if ($valor === null) {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, $valor, PDO::PARAM_INT);
}

function scu_bind_nullable_string(PDOStatement $stmt, string $parametro, ?string $valor): void
{
    if ($valor === null) {
        $stmt->bindValue($parametro, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($parametro, $valor, PDO::PARAM_STR);
}

function scu_longitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function scu_resumir_texto(string $texto, int $limite): string
{
    $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
    $limite = max(20, $limite);

    if (scu_longitud($texto) <= $limite) {
        return $texto;
    }

    $recorte = function_exists('mb_substr')
        ? mb_substr($texto, 0, $limite - 1, 'UTF-8')
        : substr($texto, 0, $limite - 1);

    return rtrim($recorte) . '…';
}

function scu_ip_cliente(): string
{
    return substr(
        sm_limpiar_texto($_SERVER['REMOTE_ADDR'] ?? ''),
        0,
        60
    ); 
}

function scu_user_agent(): string
{
    return substr(
        sm_limpiar_texto($_SERVER['HTTP_USER_AGENT'] ?? ''),
        0,
        255
    );
}

function scu_error_campo(
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