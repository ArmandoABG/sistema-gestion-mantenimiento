<?php

declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/conexion.php';

$smTopbarDirecto = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);

sm_requerir_sesion([], $smTopbarDirecto);

if (!function_exists('sm_topbar_e')) {
    function sm_topbar_e($valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sm_topbar_iniciales')) {
    function sm_topbar_iniciales(string $nombre): string
    {
        $partes = preg_split('/\s+/u', trim($nombre)) ?: [];
        $partes = array_values(array_filter($partes));

        if ($partes === []) {
            return 'U';
        }

        $primera = function_exists('mb_substr')
            ? mb_substr($partes[0], 0, 1, 'UTF-8')
            : substr($partes[0], 0, 1);

        $segunda = count($partes) > 1
            ? (function_exists('mb_substr')
                ? mb_substr($partes[count($partes) - 1], 0, 1, 'UTF-8')
                : substr($partes[count($partes) - 1], 0, 1))
            : '';

        return strtoupper($primera . $segunda);
    }
}

if (!function_exists('sm_topbar_json')) {
    function sm_topbar_json(bool $ok, string $mensaje, array $datos = [], int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo json_encode([
            'ok' => $ok,
            'mensaje' => $mensaje,
            'datos' => $datos,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$smTopbarUsuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
$smTopbarRol = strtoupper((string) ($_SESSION['tipo_usuario'] ?? ''));

/* Endpoint interno para marcar notificaciones. */
if ($smTopbarDirecto && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    sm_validar_csrf();

    if (!($conexion instanceof PDO)) {
        sm_topbar_json(false, 'No fue posible conectar con la base de datos.', [], 500);
    }

    $accion = trim((string) ($_POST['accion'] ?? ''));

    try {
        if ($accion === 'marcar_leida') {
            $notificacionId = filter_input(INPUT_POST, 'notificacion_id', FILTER_VALIDATE_INT);

            if (!$notificacionId || $notificacionId < 1) {
                sm_topbar_json(false, 'La notificación no es válida.', [], 422);
            }

            $stmt = $conexion->prepare(
                "UPDATE notificaciones
                 SET leida = 1,
                     fecha_lectura = COALESCE(fecha_lectura, NOW())
                 WHERE id = :id
                   AND tipo_usuario = :tipo_usuario
                   AND usuario_id = :usuario_id"
            );
            $stmt->execute([
                ':id' => $notificacionId,
                ':tipo_usuario' => $smTopbarRol,
                ':usuario_id' => $smTopbarUsuarioId,
            ]);

            sm_topbar_json(true, 'Notificación marcada como leída.');
        }

        if ($accion === 'marcar_todas') {
            $stmt = $conexion->prepare(
                "UPDATE notificaciones
                 SET leida = 1,
                     fecha_lectura = COALESCE(fecha_lectura, NOW())
                 WHERE tipo_usuario = :tipo_usuario
                   AND usuario_id = :usuario_id
                   AND leida = 0"
            );
            $stmt->execute([
                ':tipo_usuario' => $smTopbarRol,
                ':usuario_id' => $smTopbarUsuarioId,
            ]);

            sm_topbar_json(true, 'Notificaciones marcadas como leídas.');
        }

        sm_topbar_json(false, 'Acción no reconocida.', [], 400);
    } catch (PDOException $e) {
        error_log('[TOPBAR NOTIFICACIONES] ' . $e->getMessage());
        sm_topbar_json(false, 'No fue posible actualizar las notificaciones.', [], 500);
    }
}

$smTopbarVista = basename((string) parse_url(
    (string) ($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? ''),
    PHP_URL_PATH
));

$smTopbarPaginas = [
    'dashboard_admin.php' => ['titulo' => 'Dashboard', 'seccion' => 'Inicio'],
    'solicitudes_pendientes.php' => ['titulo' => 'Solicitudes por revisar', 'seccion' => 'Solicitudes'],
    'solicitudes_programacion.php' => ['titulo' => 'Programar y asignar', 'seccion' => 'Solicitudes'],
    'solicitudes_aprobadas.php' => ['titulo' => 'Programar y asignar', 'seccion' => 'Solicitudes'],
    'solicitudes_historial.php' => ['titulo' => 'Todas las solicitudes', 'seccion' => 'Solicitudes'],
    'agenda_semanal.php' => ['titulo' => 'Agenda semanal', 'seccion' => 'Operación'],
    'agenda.php' => ['titulo' => 'Agenda semanal', 'seccion' => 'Operación'],
    'rutinas.php' => ['titulo' => 'Rutinas de mantenimiento', 'seccion' => 'Operación'],
    'calendario_laboral.php' => ['titulo' => 'Calendario laboral', 'seccion' => 'Operación'],
    'tiempos_ejecucion.php' => ['titulo' => 'Tiempos reales', 'seccion' => 'Seguimiento'],
    'incumplimientos.php' => ['titulo' => 'Cumplimiento', 'seccion' => 'Seguimiento'],
    'mantenimientos_no_realizados.php' => ['titulo' => 'Cumplimiento', 'seccion' => 'Seguimiento'],
    'movimientos_sistema.php' => ['titulo' => 'Movimientos del sistema', 'seccion' => 'Seguimiento'],
    'administradores.php' => ['titulo' => 'Administradores', 'seccion' => 'Personal'],
    'solicitantes.php' => ['titulo' => 'Solicitantes', 'seccion' => 'Personal'],
    'tecnicos.php' => ['titulo' => 'Técnicos', 'seccion' => 'Personal'],
    'recursos_mantenimiento.php' => ['titulo' => 'Herramientas y refacciones', 'seccion' => 'Catálogos'],
    'equipos.php' => ['titulo' => 'Equipos', 'seccion' => 'Catálogos'],
    'areas.php' => ['titulo' => 'Áreas', 'seccion' => 'Catálogos'],
    'departamentos.php' => ['titulo' => 'Departamentos', 'seccion' => 'Catálogos'],
    'procesos.php' => ['titulo' => 'Procesos', 'seccion' => 'Catálogos'],

    'dashboard_solicitante.php' => ['titulo' => 'Inicio', 'seccion' => 'Solicitante'],
    'bandeja_solicitante.php' => ['titulo' => 'Mis solicitudes', 'seccion' => 'Solicitante'],
    'solicitud_correctivo_programable.php' => ['titulo' => 'Correctivo programable', 'seccion' => 'Nueva solicitud'],
    'solicitud_modificacion_mejora.php' => ['titulo' => 'Modificación o mejora', 'seccion' => 'Nueva solicitud'],
    'solicitud_correctivo_urgente.php' => ['titulo' => 'Correctivo urgente', 'seccion' => 'Nueva solicitud'],

    'dashboard_tecnico.php' => ['titulo' => 'Dashboard', 'seccion' => 'Técnico'],
    'urgencias_disponibles.php' => ['titulo' => 'Urgencias disponibles', 'seccion' => 'Mi trabajo'],
    'mantenimientos_asignados.php' => ['titulo' => 'Mantenimientos asignados', 'seccion' => 'Mi trabajo'],
    'mantenimientos_pendientes.php' => ['titulo' => 'Mantenimientos asignados', 'seccion' => 'Mi trabajo'],
    'mantenimientos_no_terminados.php' => ['titulo' => 'Mantenimientos asignados', 'seccion' => 'Mi trabajo'],
    'mantenimiento_activo.php' => ['titulo' => 'Actividad actual', 'seccion' => 'Mi trabajo'],
    'mantenimientos_finalizados.php' => ['titulo' => 'Historial', 'seccion' => 'Mi trabajo'],
];

$smTopbarPagina = $smTopbarPaginas[$smTopbarVista] ?? [
    'titulo' => 'Sistema de Mantenimiento',
    'seccion' => 'Sistema 1.1',
];

$smTopbarNombre = trim((string) ($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'Usuario'));
$smTopbarUsuario = trim((string) ($_SESSION['usuario'] ?? ''));
$smTopbarExtra = '';
$smTopbarRolNombre = [
    'ADMIN' => 'Administrador',
    'SOLICITANTE' => 'Solicitante',
    'TECNICO' => 'Técnico',
][$smTopbarRol] ?? 'Usuario';

/* Refrescar datos del usuario y validar que siga activo. */
if ($conexion instanceof PDO && $smTopbarUsuarioId > 0) {
    try {
        if ($smTopbarRol === 'ADMIN') {
            $sqlPerfil = "SELECT id, usuario, nombre, apellido_paterno, apellido_materno, activo
                          FROM administradores WHERE id = :id LIMIT 1";
        } elseif ($smTopbarRol === 'SOLICITANTE') {
            $sqlPerfil = "SELECT s.id, s.usuario, s.nombre, s.apellido_paterno, s.apellido_materno,
                                 s.activo, d.nombre AS departamento
                          FROM solicitantes s
                          LEFT JOIN departamentos d ON d.id = s.departamento_id
                          WHERE s.id = :id LIMIT 1";
        } else {
            $sqlPerfil = "SELECT t.id, t.usuario, t.nombre, t.apellido_paterno, t.apellido_materno,
                                 t.activo, t.turno, t.especialidad, d.nombre AS departamento
                          FROM tecnicos t
                          LEFT JOIN departamentos d ON d.id = t.departamento_id
                          WHERE t.id = :id LIMIT 1";
        }

        $stmtPerfil = $conexion->prepare($sqlPerfil);
        $stmtPerfil->execute([':id' => $smTopbarUsuarioId]);
        $perfil = $stmtPerfil->fetch(PDO::FETCH_ASSOC);

        if (!is_array($perfil) || (int) ($perfil['activo'] ?? 0) !== 1) {
            sm_destruir_sesion();
            header('Location: ../login.php?acceso=desactivado');
            exit;
        }

        $nombrePartes = array_filter([
            trim((string) ($perfil['nombre'] ?? '')),
            trim((string) ($perfil['apellido_paterno'] ?? '')),
            trim((string) ($perfil['apellido_materno'] ?? '')),
        ]);

        if ($nombrePartes !== []) {
            $smTopbarNombre = implode(' ', $nombrePartes);
        }

        $smTopbarUsuario = trim((string) ($perfil['usuario'] ?? $smTopbarUsuario));

        if ($smTopbarRol === 'SOLICITANTE') {
            $smTopbarExtra = trim((string) ($perfil['departamento'] ?? ''));
        } elseif ($smTopbarRol === 'TECNICO') {
            $extraPartes = array_filter([
                trim((string) ($perfil['departamento'] ?? '')),
                trim((string) ($perfil['turno'] ?? '')),
            ]);
            $smTopbarExtra = implode(' · ', $extraPartes);
        }

        $_SESSION['nombre_completo'] = $smTopbarNombre;
        $_SESSION['usuario'] = $smTopbarUsuario;
    } catch (PDOException $e) {
        error_log('[TOPBAR PERFIL] ' . $e->getMessage());
    }
}

$smTopbarNotificaciones = [];
$smTopbarNoLeidas = 0;

if ($conexion instanceof PDO) {
    try {
        $stmtConteo = $conexion->prepare(
            "SELECT COUNT(*)
             FROM notificaciones
             WHERE tipo_usuario = :tipo_usuario
               AND usuario_id = :usuario_id
               AND leida = 0"
        );
        $stmtConteo->execute([
            ':tipo_usuario' => $smTopbarRol,
            ':usuario_id' => $smTopbarUsuarioId,
        ]);
        $smTopbarNoLeidas = (int) $stmtConteo->fetchColumn();

        $stmtNotificaciones = $conexion->prepare(
            "SELECT
                    n.id,
                    n.solicitud_id,
                    n.rutina_alerta_id,
                    n.ejecucion_id,
                    n.titulo,
                    n.mensaje,
                    n.tipo,
                    n.leida,
                    DATE_FORMAT(n.fecha_creacion, '%d/%m/%Y %H:%i') AS fecha,
                    s.tipo_solicitud AS solicitud_tipo,
                    s.estado AS solicitud_estado,
                    s.revisado_por_admin_id AS solicitud_revisada_por
             FROM notificaciones n
             LEFT JOIN solicitudes s
                ON s.id = n.solicitud_id
             WHERE n.tipo_usuario = :tipo_usuario
               AND n.usuario_id = :usuario_id
             ORDER BY n.leida ASC, n.fecha_creacion DESC, n.id DESC
             LIMIT 10"
        );
        $stmtNotificaciones->execute([
            ':tipo_usuario' => $smTopbarRol,
            ':usuario_id' => $smTopbarUsuarioId,
        ]);
        $smTopbarNotificaciones = $stmtNotificaciones->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('[TOPBAR LISTADO] ' . $e->getMessage());
    }
}

$smTopbarCsrf = sm_token_csrf();
$smTopbarIniciales = sm_topbar_iniciales($smTopbarNombre);

function sm_topbar_destino(array $notificacion, string $rol): string
{
    if ($rol === 'ADMIN') {
        if (!empty($notificacion['rutina_alerta_id'])) {
            return 'rutinas.php';
        }
        if (!empty($notificacion['solicitud_id'])) {
            $solicitudId = (int) $notificacion['solicitud_id'];
            $estado = (string) ($notificacion['solicitud_estado'] ?? '');
            $tipoSolicitud = (string) ($notificacion['solicitud_tipo'] ?? '');
            $sinRevisar = (int) ($notificacion['solicitud_revisada_por'] ?? 0) <= 0;

            $requiereRevision = $estado === 'PENDIENTE'
                || (
                    $tipoSolicitud === 'CORRECTIVO_URGENTE'
                    && $sinRevisar
                    && in_array(
                        $estado,
                        [
                            'AGENDADO',
                            'EN_PROCESO',
                            'PAUSADO',
                            'ATRASADO',
                        ],
                        true
                    )
                );

            if ($requiereRevision) {
                return 'solicitudes_pendientes.php?id=' . $solicitudId;
            }

            return 'solicitudes_historial.php?solicitud=' . $solicitudId;
        }
        return 'dashboard_admin.php';
    }

    if ($rol === 'TECNICO') {
        if (($notificacion['tipo'] ?? '') === 'URGENTE') {
            return 'urgencias_disponibles.php';
        }
        if (!empty($notificacion['ejecucion_id'])) {
            return 'mantenimiento_activo.php';
        }
        return 'mantenimientos_asignados.php';
    }

    if (!empty($notificacion['solicitud_id'])) {
        return 'bandeja_solicitante.php?solicitud=' . (int) $notificacion['solicitud_id'];
    }

    return 'dashboard_solicitante.php';
}

/*
|--------------------------------------------------------------------------
| Consulta AJAX de notificaciones
|--------------------------------------------------------------------------
| La topbar usa este endpoint de solo lectura para actualizar el contador y
| las diez notificaciones más recientes sin recargar la página completa.
*/
if (
    $smTopbarDirecto
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && trim((string) ($_GET['accion'] ?? '')) === 'listar_notificaciones'
) {
    $smTopbarRespuestaNotificaciones = [];

    foreach ($smTopbarNotificaciones as $smTopbarNotificacion) {
        $smTopbarRespuestaNotificaciones[] = [
            'id' => (int) ($smTopbarNotificacion['id'] ?? 0),
            'titulo' => (string) ($smTopbarNotificacion['titulo'] ?? ''),
            'mensaje' => (string) ($smTopbarNotificacion['mensaje'] ?? ''),
            'tipo' => strtolower((string) ($smTopbarNotificacion['tipo'] ?? 'info')),
            'leida' => (int) ($smTopbarNotificacion['leida'] ?? 0),
            'fecha' => (string) ($smTopbarNotificacion['fecha'] ?? ''),
            'destino' => sm_topbar_destino($smTopbarNotificacion, $smTopbarRol),
        ];
    }

    sm_topbar_json(
        true,
        'Notificaciones actualizadas.',
        [
            'no_leidas' => $smTopbarNoLeidas,
            'notificaciones' => $smTopbarRespuestaNotificaciones,
            'intervalo_ms' => 8000,
            'fecha_servidor' => date('Y-m-d H:i:s'),
        ]
    );
}
?>


<header class="sm-topbar" id="smTopbar">
    <div class="sm-topbar__ambient" aria-hidden="true"></div>

    <div class="sm-topbar__left">
        <button
            type="button"
            class="sm-topbar__menu"
            id="smTopbarMenu"
            aria-label="Abrir menú principal"
            title="Abrir menú principal"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 7h16M4 12h16M4 17h16"></path>
            </svg>
        </button>

        <div class="sm-topbar__page-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <rect x="3.5" y="3.5" width="7" height="7" rx="2"></rect>
                <rect x="13.5" y="3.5" width="7" height="7" rx="2"></rect>
                <rect x="3.5" y="13.5" width="7" height="7" rx="2"></rect>
                <rect x="13.5" y="13.5" width="7" height="7" rx="2"></rect>
            </svg>
        </div>

        <div class="sm-topbar__title">
            <div class="sm-topbar__breadcrumb">
                <span>Sistema de Mantenimiento</span>
                <svg viewBox="0 0 20 20" aria-hidden="true">
                    <path d="m8 5 5 5-5 5"></path>
                </svg>
                <strong><?= sm_topbar_e($smTopbarPagina['seccion']) ?></strong>
            </div>
            <h1><?= sm_topbar_e($smTopbarPagina['titulo']) ?></h1>
        </div>
    </div>

    <div class="sm-topbar__right">
        <div class="sm-topbar__system-state" title="Estado del sistema">
            <span class="sm-topbar__system-dot" aria-hidden="true"></span>
            <span class="sm-topbar__system-copy">
                <strong>Sistema activo</strong>
                <small>Sesión protegida</small>
            </span>
        </div>

        <div class="sm-topbar__datetime" aria-label="Fecha y hora actuales">
            <span class="sm-topbar__datetime-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M7 3v3M17 3v3M4 9h16"></path>
                    <rect x="3" y="5" width="18" height="16" rx="3"></rect>
                    <path d="M8 13h.01M12 13h.01M16 13h.01M8 17h.01M12 17h.01"></path>
                </svg>
            </span>
            <span class="sm-topbar__datetime-copy">
                <strong id="smTopbarDate">Hoy</strong>
                <small id="smTopbarTime">--:--</small>
            </span>
        </div>

        <div class="sm-topbar__divider" aria-hidden="true"></div>

        <div class="sm-topbar__dropdown" data-topbar-dropdown>
            <button
                type="button"
                class="sm-topbar__icon-button"
                data-topbar-toggle
                aria-label="Abrir notificaciones"
                aria-expanded="false"
                aria-haspopup="dialog"
                aria-controls="smTopbarNotifications"
                title="Notificaciones"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                    <path d="M10 21h4"></path>
                </svg>
                <b
                    class="sm-topbar__badge"
                    id="smNotificationBadge"
                    <?= $smTopbarNoLeidas > 0 ? '' : 'hidden' ?>
                >
                    <?= $smTopbarNoLeidas > 99 ? '99+' : $smTopbarNoLeidas ?>
                </b>
            </button>

            <div
                class="sm-topbar__panel sm-topbar__notifications"
                id="smTopbarNotifications"
                data-topbar-panel
                role="dialog"
                aria-label="Centro de notificaciones"
                hidden
            >
                <div class="sm-topbar__panel-accent" aria-hidden="true"></div>

                <div class="sm-topbar__panel-head">
                    <div class="sm-topbar__panel-title">
                        <span class="sm-topbar__panel-title-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                                <path d="M10 21h4"></path>
                            </svg>
                        </span>
                        <div>
                            <strong>Notificaciones</strong>
                            <small id="smNotificationSummary">
                                <?= (int) $smTopbarNoLeidas ?>
                                <?= (int) $smTopbarNoLeidas === 1 ? 'sin leer' : 'sin leer' ?>
                            </small>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="sm-topbar__text-button"
                        id="smMarkAll"
                        <?= $smTopbarNoLeidas > 0 ? '' : 'hidden' ?>
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="m4 12 4 4L20 4"></path>
                            <path d="m4 6 4 4"></path>
                        </svg>
                        <span>Marcar todas</span>
                    </button>
                </div>

                <div class="sm-topbar__notification-list" id="smTopbarNotificationList">
                    <?php if ($smTopbarNotificaciones === []): ?>
                        <div class="sm-topbar__empty">
                            <span class="sm-topbar__empty-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                                    <path d="M9 21h6"></path>
                                </svg>
                            </span>
                            <strong>Todo está al día</strong>
                            <span>No tienes notificaciones por el momento.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($smTopbarNotificaciones as $notificacion): ?>
                            <?php
                                $smTipoNotificacion = strtolower((string) ($notificacion['tipo'] ?? 'info'));
                                $smEsNoLeida = (int) ($notificacion['leida'] ?? 0) === 0;
                            ?>
                            <a
                                href="<?= sm_topbar_e(sm_topbar_destino($notificacion, $smTopbarRol)) ?>"
                                class="sm-topbar__notification <?= $smEsNoLeida ? 'is-unread' : '' ?>"
                                data-notification-id="<?= (int) $notificacion['id'] ?>"
                            >
                                <span class="sm-topbar__notification-icon sm-type-<?= sm_topbar_e($smTipoNotificacion) ?>" aria-hidden="true">
                                    <?php if (in_array($smTipoNotificacion, ['danger', 'urgente', 'error'], true)): ?>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M12 9v4"></path>
                                            <path d="M12 17h.01"></path>
                                            <path d="M10.3 4.6 2.7 18a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 4.6a2 2 0 0 0-3.4 0Z"></path>
                                        </svg>
                                    <?php elseif (in_array($smTipoNotificacion, ['success', 'exito', 'terminado'], true)): ?>
                                        <svg viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="9"></circle>
                                            <path d="m8 12 2.7 2.7L16.5 9"></path>
                                        </svg>
                                    <?php elseif (in_array($smTipoNotificacion, ['warning', 'advertencia'], true)): ?>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M12 9v4"></path>
                                            <path d="M12 17h.01"></path>
                                            <path d="M10.3 4.6 2.7 18a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 4.6a2 2 0 0 0-3.4 0Z"></path>
                                        </svg>
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="9"></circle>
                                            <path d="M12 11v5"></path>
                                            <path d="M12 8h.01"></path>
                                        </svg>
                                    <?php endif; ?>
                                </span>

                                <span class="sm-topbar__notification-copy">
                                    <span class="sm-topbar__notification-line">
                                        <strong><?= sm_topbar_e($notificacion['titulo']) ?></strong>
                                        <?php if ($smEsNoLeida): ?>
                                            <i aria-label="No leída" title="No leída"></i>
                                        <?php endif; ?>
                                    </span>
                                    <span class="sm-topbar__notification-message">
                                        <?= sm_topbar_e($notificacion['mensaje']) ?>
                                    </span>
                                    <small>
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="12" cy="12" r="9"></circle>
                                            <path d="M12 7v5l3 2"></path>
                                        </svg>
                                        <?= sm_topbar_e($notificacion['fecha']) ?>
                                    </small>
                                </span>

                                <span class="sm-topbar__notification-arrow" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <path d="m9 18 6-6-6-6"></path>
                                    </svg>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="sm-topbar__panel-foot">
                    <span>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z"></path>
                            <path d="M12 16v-4"></path>
                            <path d="M12 8h.01"></path>
                        </svg>
                        Se muestran las 10 notificaciones más recientes
                    </span>
                </div>
            </div>
        </div>

        <div class="sm-topbar__dropdown" data-topbar-dropdown>
            <button
                type="button"
                class="sm-topbar__profile"
                data-topbar-toggle
                aria-expanded="false"
                aria-haspopup="dialog"
                aria-controls="smTopbarProfilePanel"
            >
                <span class="sm-topbar__avatar">
                    <?= sm_topbar_e($smTopbarIniciales) ?>
                    <i aria-hidden="true"></i>
                </span>
                <span class="sm-topbar__profile-copy">
                    <strong><?= sm_topbar_e($smTopbarNombre) ?></strong>
                    <small><?= sm_topbar_e($smTopbarRolNombre) ?></small>
                </span>
                <span class="sm-topbar__arrow" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="m7 10 5 5 5-5"></path>
                    </svg>
                </span>
            </button>

            <div
                class="sm-topbar__panel sm-topbar__profile-panel"
                id="smTopbarProfilePanel"
                data-topbar-panel
                role="dialog"
                aria-label="Menú de usuario"
                hidden
            >
                <div class="sm-topbar__profile-cover">
                    <div class="sm-topbar__profile-cover-orb" aria-hidden="true"></div>
                    <span class="sm-topbar__avatar sm-topbar__avatar--large">
                        <?= sm_topbar_e($smTopbarIniciales) ?>
                        <i aria-hidden="true"></i>
                    </span>
                    <div class="sm-topbar__profile-summary">
                        <strong><?= sm_topbar_e($smTopbarNombre) ?></strong>
                        <span>@<?= sm_topbar_e($smTopbarUsuario) ?></span>
                    </div>
                </div>

                <div class="sm-topbar__profile-body">
                    <div class="sm-topbar__profile-detail">
                        <span class="sm-topbar__profile-detail-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M20 21a8 8 0 0 0-16 0"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <span>
                            <small>Perfil de acceso</small>
                            <strong><?= sm_topbar_e($smTopbarRolNombre) ?></strong>
                        </span>
                    </div>

                    <?php if ($smTopbarExtra !== ''): ?>
                        <div class="sm-topbar__profile-detail">
                            <span class="sm-topbar__profile-detail-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M3 21h18"></path>
                                    <path d="M6 21V7l6-4 6 4v14"></path>
                                    <path d="M9 10h.01M15 10h.01M9 14h.01M15 14h.01"></path>
                                </svg>
                            </span>
                            <span>
                                <small>Área asignada</small>
                                <strong><?= sm_topbar_e($smTopbarExtra) ?></strong>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="sm-topbar__profile-status">
                        <span aria-hidden="true"></span>
                        Sesión iniciada y cuenta activa
                    </div>

                    <a href="../funciones/logout.php" class="sm-topbar__logout">
                        <span aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M10 17l5-5-5-5"></path>
                                <path d="M15 12H3"></path>
                                <path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"></path>
                            </svg>
                        </span>
                        <strong>Cerrar sesión</strong>
                        <small>Salir de forma segura</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="sm-topbar__toast" id="smTopbarToast" role="status" aria-live="polite" hidden>
    <span class="sm-topbar__toast-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24">
            <path d="m5 12 4 4L19 6"></path>
        </svg>
    </span>
    <span id="smTopbarToastText">Acción completada.</span>
</div>

<style>
:root {
    --sm-topbar-height: 78px;
    --smt-blue-950: #103b52;
    --smt-blue-900: #15506a;
    --smt-blue-800: #1d6680;
    --smt-blue-700: #297f96;
    --smt-blue-600: #3d98aa;
    --smt-blue-200: #bfe3e7;
    --smt-blue-100: #d8eff0;
    --smt-blue-75: #e7f6f5;
    --smt-blue-50: #f3fbfa;
    --smt-surface: #ffffff;
    --smt-text: #153b4d;
    --smt-muted: #607d88;
    --smt-soft: #88a4ab;
    --smt-border: #c6e1e4;
    --smt-green: #16835f;
    --smt-green-soft: #e8f7f0;
    --smt-red: #c23b34;
    --smt-red-soft: #fff0ef;
    --smt-amber: #b2740f;
    --smt-amber-soft: #fff7df;
    --smt-shadow: 0 16px 42px rgba(24, 86, 102, .14);
    --smt-shadow-soft: 0 7px 20px rgba(24, 86, 102, .08);
}

.sm-topbar,
.sm-topbar *,
.sm-topbar__toast,
.sm-topbar__toast * {
    box-sizing: border-box;
}

.sm-topbar [hidden],
.sm-topbar__toast[hidden] {
    display: none !important;
}

.sm-topbar {
    position: sticky;
    top: 0;
    z-index: 900;
    width: 100%;
    min-height: var(--sm-topbar-height);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 22px;
    padding: 11px clamp(18px, 2.3vw, 34px);
    isolation: isolate;
    color: var(--smt-text);
   background:
    linear-gradient(
        112deg,
        rgba(126, 235, 230, 0.97),
        #10253b
    ),
    #d5f1e9;
    border-bottom: 1px solid rgba(143, 198, 205, .72);
    box-shadow: 0 5px 20px rgba(24, 88, 104, .09);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
}

.sm-topbar::after {
    content: "";
    position: absolute;
    right: 0;
    bottom: -1px;
    left: 0;
    height: 1px;
    z-index: -1;
    background: linear-gradient(90deg, transparent, rgba(42, 127, 150, .30), transparent);
}

.sm-topbar__ambient {
    position: absolute;
    top: -70px;
    right: 20%;
    width: 340px;
    height: 150px;
    z-index: -1;
    pointer-events: none;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(73, 170, 184, .18), transparent 68%);
    filter: blur(8px);
}

.sm-topbar button,
.sm-topbar a {
    -webkit-tap-highlight-color: transparent;
}

.sm-topbar button {
    font: inherit;
}

.sm-topbar svg,
.sm-topbar__toast svg {
    width: 1em;
    height: 1em;
    display: block;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.sm-topbar__left,
.sm-topbar__right {
    min-width: 0;
    display: flex;
    align-items: center;
}

.sm-topbar__left {
    flex: 1 1 auto;
    gap: 13px;
}

.sm-topbar__right {
    flex: 0 0 auto;
    gap: 10px;
}

.sm-topbar__menu,
.sm-topbar__page-icon,
.sm-topbar__icon-button,
.sm-topbar__datetime-icon,
.sm-topbar__panel-title-icon,
.sm-topbar__profile-detail-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.sm-topbar__menu {
    width: 46px;
    height: 46px;
    flex: 0 0 46px;
    display: none;
    padding: 0;
    border: 1px solid rgba(116, 183, 193, .55);
    border-radius: 14px;
    color: var(--smt-blue-800);
    background: rgba(255, 255, 255, .86);
    box-shadow: var(--smt-shadow-soft);
    cursor: pointer;
    transition: transform .2s ease, border-color .2s ease, background .2s ease, box-shadow .2s ease;
}

.sm-topbar__menu svg {
    width: 23px;
    height: 23px;
    stroke-width: 2.1;
}

.sm-topbar__menu:hover {
    transform: translateY(-1px);
    border-color: rgba(50, 141, 158, .62);
    background: #fff;
    box-shadow: 0 10px 25px rgba(24, 86, 102, .13);
}

.sm-topbar__menu:focus-visible,
.sm-topbar__icon-button:focus-visible,
.sm-topbar__profile:focus-visible,
.sm-topbar__text-button:focus-visible,
.sm-topbar__logout:focus-visible {
    outline: 3px solid rgba(52, 145, 164, .22);
    outline-offset: 3px;
}

.sm-topbar__page-icon {
    width: 46px;
    height: 46px;
    flex: 0 0 46px;
    border: 1px solid rgba(115, 181, 191, .34);
    border-radius: 15px;
    color: var(--smt-blue-700);
    background: linear-gradient(145deg, #fff, var(--smt-blue-100));
    box-shadow: inset 0 1px 0 rgba(255,255,255,.9), var(--smt-shadow-soft);
}

.sm-topbar__page-icon svg {
    width: 22px;
    height: 22px;
}

.sm-topbar__title {
    min-width: 0;
    line-height: 1;
}

.sm-topbar__breadcrumb {
    min-width: 0;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--smt-muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .015em;
    white-space: nowrap;
}

.sm-topbar__breadcrumb span,
.sm-topbar__breadcrumb strong {
    overflow: hidden;
    text-overflow: ellipsis;
}

.sm-topbar__breadcrumb strong {
    color: var(--smt-blue-700);
    font-weight: 850;
}

.sm-topbar__breadcrumb svg {
    width: 13px;
    height: 13px;
    flex: 0 0 13px;
    color: #86aab3;
}

.sm-topbar__title h1 {
    max-width: min(48vw, 720px);
    margin: 0;
    overflow: hidden;
    color: var(--smt-blue-950);
    font-size: clamp(20px, 2vw, 27px);
    font-weight: 850;
    line-height: 1.12;
    letter-spacing: -.025em;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sm-topbar__system-state,
.sm-topbar__datetime {
    min-height: 47px;
    display: flex;
    align-items: center;
    border: 1px solid rgba(123, 187, 196, .37);
    background: rgba(255, 255, 255, .63);
}

.sm-topbar__system-state {
    gap: 9px;
    padding: 7px 13px;
    border-radius: 14px;
}

.sm-topbar__system-dot {
    width: 9px;
    height: 9px;
    flex: 0 0 9px;
    border-radius: 50%;
    background: #20a777;
    box-shadow: 0 0 0 5px rgba(32, 167, 119, .11);
    animation: smTopbarPulse 2.8s ease-in-out infinite;
}

.sm-topbar__system-copy,
.sm-topbar__datetime-copy {
    min-width: 0;
    line-height: 1.15;
}

.sm-topbar__system-copy strong,
.sm-topbar__system-copy small,
.sm-topbar__datetime-copy strong,
.sm-topbar__datetime-copy small {
    display: block;
    white-space: nowrap;
}

.sm-topbar__system-copy strong,
.sm-topbar__datetime-copy strong {
    color: var(--smt-text);
    font-size: 12px;
    font-weight: 850;
}

.sm-topbar__system-copy small,
.sm-topbar__datetime-copy small {
    margin-top: 4px;
    color: var(--smt-muted);
    font-size: 10px;
    font-weight: 650;
}

.sm-topbar__datetime {
    gap: 9px;
    min-width: 168px;
    padding: 5px 12px 5px 7px;
    border-radius: 14px;
}

.sm-topbar__datetime-icon {
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    border-radius: 10px;
    color: var(--smt-blue-700);
    background: var(--smt-blue-100);
}

.sm-topbar__datetime-icon svg {
    width: 18px;
    height: 18px;
}

.sm-topbar__divider {
    width: 1px;
    height: 32px;
    margin: 0 2px;
    background: linear-gradient(transparent, rgba(84, 155, 165, .35), transparent);
}

.sm-topbar__dropdown {
    position: relative;
}

.sm-topbar__icon-button,
.sm-topbar__profile {
    border: 1px solid rgba(115, 181, 191, .45);
    color: var(--smt-blue-900);
    background: rgba(255, 255, 255, .88);
    box-shadow: 0 5px 14px rgba(24, 86, 102, .06);
    cursor: pointer;
    transition: transform .2s ease, background .2s ease, border-color .2s ease, box-shadow .2s ease;
}

.sm-topbar__icon-button:hover,
.sm-topbar__profile:hover,
.sm-topbar__icon-button[aria-expanded="true"],
.sm-topbar__profile[aria-expanded="true"] {
    transform: translateY(-1px);
    border-color: rgba(47, 137, 154, .60);
    background: #fff;
    box-shadow: 0 10px 25px rgba(24, 86, 102, .12);
}

.sm-topbar__icon-button {
    position: relative;
    width: 47px;
    height: 47px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border-radius: 14px;
}

.sm-topbar__icon-button svg {
    width: 21px;
    height: 21px;
    stroke-width: 1.95;
}

.sm-topbar__badge {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: 21px;
    height: 21px;
    padding: 0 5px;
    display: inline-grid;
    place-items: center;
    border: 2px solid #edf9f8;
    border-radius: 999px;
    color: #fff;
    background: linear-gradient(135deg, #e0524d, #ba2e2a);
    box-shadow: 0 4px 11px rgba(186, 46, 42, .28);
    font-size: 10px;
    font-weight: 900;
    line-height: 1;
}

.sm-topbar__profile {
    min-width: 212px;
    min-height: 49px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 10px 5px 6px;
    border-radius: 15px;
    text-align: left;
}

.sm-topbar__avatar {
    position: relative;
    width: 37px;
    height: 37px;
    flex: 0 0 37px;
    display: inline-grid;
    place-items: center;
    border: 2px solid rgba(255,255,255,.88);
    border-radius: 12px;
    color: #fff;
    background:
        linear-gradient(145deg, rgba(255,255,255,.11), transparent),
        linear-gradient(135deg, var(--smt-blue-800), var(--smt-blue-600));
    box-shadow: 0 7px 15px rgba(27, 102, 123, .22);
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .035em;
}

.sm-topbar__avatar i {
    position: absolute;
    right: -3px;
    bottom: -2px;
    width: 10px;
    height: 10px;
    border: 2px solid #fff;
    border-radius: 50%;
    background: #1fa877;
}

.sm-topbar__avatar--large {
    width: 58px;
    height: 58px;
    flex-basis: 58px;
    border-radius: 18px;
    font-size: 17px;
}

.sm-topbar__profile-copy {
    min-width: 0;
    flex: 1;
}

.sm-topbar__profile-copy strong,
.sm-topbar__profile-copy small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sm-topbar__profile-copy strong {
    max-width: 145px;
    color: var(--smt-blue-950);
    font-size: 12px;
    font-weight: 850;
    line-height: 1.2;
}

.sm-topbar__profile-copy small {
    margin-top: 4px;
    color: var(--smt-muted);
    font-size: 10px;
    font-weight: 700;
}

.sm-topbar__arrow {
    width: 20px;
    height: 20px;
    flex: 0 0 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #6f929b;
    transition: transform .2s ease;
}

.sm-topbar__arrow svg {
    width: 15px;
    height: 15px;
    stroke-width: 2;
}

.sm-topbar__profile[aria-expanded="true"] .sm-topbar__arrow {
    transform: rotate(180deg);
}

.sm-topbar__panel {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    width: min(410px, calc(100vw - 24px));
    overflow: hidden;
    border: 1px solid rgba(142, 198, 205, .62);
    border-radius: 20px;
    color: var(--smt-text);
    background: rgba(255, 255, 255, .985);
    box-shadow: var(--smt-shadow);
    transform-origin: top right;
    animation: smTopbarPanelIn .2s ease both;
}

.sm-topbar__panel-accent {
    height: 4px;
    background: linear-gradient(90deg, var(--smt-blue-800), #57a8b9, #79c7cc);
}

.sm-topbar__panel-head {
    min-height: 76px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 16px;
    border-bottom: 1px solid #e5f1f1;
    background:
        radial-gradient(circle at 90% 0%, rgba(91, 184, 194, .13), transparent 40%),
        linear-gradient(145deg, #fff, #f7fbfb);
}

.sm-topbar__panel-title {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 11px;
}

.sm-topbar__panel-title-icon {
    width: 39px;
    height: 39px;
    flex: 0 0 39px;
    border-radius: 12px;
    color: var(--smt-blue-700);
    background: var(--smt-blue-100);
}

.sm-topbar__panel-title-icon svg {
    width: 19px;
    height: 19px;
}

.sm-topbar__panel-title strong,
.sm-topbar__panel-title small {
    display: block;
}

.sm-topbar__panel-title strong {
    color: var(--smt-blue-950);
    font-size: 15px;
    font-weight: 900;
}

.sm-topbar__panel-title small {
    margin-top: 5px;
    color: var(--smt-muted);
    font-size: 11px;
    font-weight: 700;
}

.sm-topbar__text-button {
    min-height: 35px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 7px 10px;
    border: 1px solid #c7e4e6;
    border-radius: 10px;
    color: var(--smt-blue-700);
    background: var(--smt-blue-50);
    cursor: pointer;
    font-size: 10px;
    font-weight: 850;
    white-space: nowrap;
    transition: background .2s ease, border-color .2s ease, transform .2s ease;
}

.sm-topbar__text-button:hover {
    transform: translateY(-1px);
    border-color: #abd3d8;
    background: var(--smt-blue-100);
}

.sm-topbar__text-button:disabled {
    opacity: .62;
    cursor: wait;
    transform: none;
}

.sm-topbar__text-button svg {
    width: 15px;
    height: 15px;
    stroke-width: 2.1;
}

.sm-topbar__text-button.is-loading svg {
    animation: smTopbarSpin .75s linear infinite;
}

.sm-topbar__notification-list {
    max-height: min(510px, calc(100vh - 190px));
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: #b8d9dc transparent;
}

.sm-topbar__notification-list::-webkit-scrollbar {
    width: 7px;
}

.sm-topbar__notification-list::-webkit-scrollbar-thumb {
    border: 2px solid #fff;
    border-radius: 999px;
    background: #b8d9dc;
}

.sm-topbar__notification {
    position: relative;
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr) 20px;
    align-items: start;
    gap: 11px;
    padding: 14px 15px;
    border-bottom: 1px solid #eaf3f3;
    color: inherit;
    background: #fff;
    text-decoration: none;
    transition: background .18s ease, transform .18s ease, box-shadow .18s ease;
}

.sm-topbar__notification:last-child {
    border-bottom: 0;
}

.sm-topbar__notification:hover {
    z-index: 1;
    background: #f5fbfa;
    box-shadow: inset 3px 0 0 #7fbfc6;
}

.sm-topbar__notification.is-unread {
    background:
        linear-gradient(90deg, rgba(221, 243, 243, .83), rgba(247, 252, 251, .76));
}

.sm-topbar__notification.is-unread::before {
    content: "";
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 3px;
    background: linear-gradient(var(--smt-blue-600), #63b0bd);
}

.sm-topbar__notification.is-loading {
    pointer-events: none;
    opacity: .68;
}

.sm-topbar__notification-icon {
    width: 42px;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    color: var(--smt-blue-700);
    background: var(--smt-blue-100);
}

.sm-topbar__notification-icon svg {
    width: 20px;
    height: 20px;
}

.sm-topbar__notification-icon.sm-type-success,
.sm-topbar__notification-icon.sm-type-exito,
.sm-topbar__notification-icon.sm-type-terminado {
    color: var(--smt-green);
    background: var(--smt-green-soft);
}

.sm-topbar__notification-icon.sm-type-warning,
.sm-topbar__notification-icon.sm-type-advertencia {
    color: var(--smt-amber);
    background: var(--smt-amber-soft);
}

.sm-topbar__notification-icon.sm-type-danger,
.sm-topbar__notification-icon.sm-type-urgente,
.sm-topbar__notification-icon.sm-type-error {
    color: var(--smt-red);
    background: var(--smt-red-soft);
}

.sm-topbar__notification-copy {
    min-width: 0;
}

.sm-topbar__notification-line {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.sm-topbar__notification-line strong {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    color: var(--smt-blue-950);
    font-size: 12px;
    font-weight: 850;
    line-height: 1.35;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sm-topbar__notification-line i {
    width: 7px;
    height: 7px;
    flex: 0 0 7px;
    margin-top: 4px;
    border-radius: 50%;
    background: #3c95a7;
    box-shadow: 0 0 0 4px rgba(55, 151, 167, .11);
}

.sm-topbar__notification-message {
    margin-top: 5px;
    display: -webkit-box;
    overflow: hidden;
    color: var(--smt-muted);
    font-size: 11px;
    font-weight: 600;
    line-height: 1.48;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.sm-topbar__notification-copy small {
    margin-top: 8px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #819ca3;
    font-size: 9px;
    font-weight: 700;
}

.sm-topbar__notification-copy small svg {
    width: 12px;
    height: 12px;
}

.sm-topbar__notification-arrow {
    width: 20px;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #96afb4;
    transition: color .18s ease, transform .18s ease;
}

.sm-topbar__notification-arrow svg {
    width: 15px;
    height: 15px;
}

.sm-topbar__notification:hover .sm-topbar__notification-arrow {
    color: var(--smt-blue-700);
    transform: translateX(2px);
}

.sm-topbar__empty {
    min-height: 255px;
    padding: 38px 25px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--smt-muted);
    text-align: center;
}

.sm-topbar__empty-icon {
    width: 66px;
    height: 66px;
    margin-bottom: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #d1e8e9;
    border-radius: 21px;
    color: var(--smt-blue-700);
    background: linear-gradient(145deg, #fff, var(--smt-blue-100));
    box-shadow: var(--smt-shadow-soft);
}

.sm-topbar__empty-icon svg {
    width: 29px;
    height: 29px;
}

.sm-topbar__empty strong {
    color: var(--smt-blue-950);
    font-size: 15px;
    font-weight: 900;
}

.sm-topbar__empty > span:last-child {
    max-width: 250px;
    margin-top: 8px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.55;
}

.sm-topbar__panel-foot {
    padding: 10px 15px;
    border-top: 1px solid #eaf3f3;
    background: #f5fbfa;
}

.sm-topbar__panel-foot span {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    color: #78969d;
    font-size: 9px;
    font-weight: 700;
    text-align: center;
}

.sm-topbar__panel-foot svg {
    width: 13px;
    height: 13px;
}

.sm-topbar__profile-panel {
    width: min(330px, calc(100vw - 24px));
}

.sm-topbar__profile-cover {
    position: relative;
    min-height: 135px;
    padding: 20px;
    display: flex;
    align-items: flex-end;
    gap: 13px;
    isolation: isolate;
    overflow: hidden;
    color: #fff;
    background:
        linear-gradient(135deg, rgba(15, 72, 92, .96), rgba(39, 135, 148, .94)),
        var(--smt-blue-800);
}

.sm-topbar__profile-cover::before,
.sm-topbar__profile-cover::after {
    content: "";
    position: absolute;
    z-index: -1;
    border: 1px solid rgba(255,255,255,.13);
    border-radius: 50%;
}

.sm-topbar__profile-cover::before {
    width: 180px;
    height: 180px;
    top: -110px;
    right: -55px;
}

.sm-topbar__profile-cover::after {
    width: 110px;
    height: 110px;
    right: 45px;
    bottom: -70px;
}

.sm-topbar__profile-cover-orb {
    position: absolute;
    top: -20px;
    left: 30px;
    width: 160px;
    height: 90px;
    z-index: -1;
    background: radial-gradient(circle, rgba(117, 207, 211, .26), transparent 70%);
    filter: blur(8px);
}

.sm-topbar__profile-cover .sm-topbar__avatar {
    border-color: rgba(255,255,255,.38);
    background: rgba(255,255,255,.16);
    box-shadow: 0 10px 30px rgba(8, 40, 50, .22);
    backdrop-filter: blur(8px);
}

.sm-topbar__profile-cover .sm-topbar__avatar i {
    border-color: var(--smt-blue-800);
}

.sm-topbar__profile-summary {
    min-width: 0;
    padding-bottom: 4px;
}

.sm-topbar__profile-summary strong,
.sm-topbar__profile-summary span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sm-topbar__profile-summary strong {
    color: #fff;
    font-size: 14px;
    font-weight: 900;
    line-height: 1.25;
}

.sm-topbar__profile-summary span {
    margin-top: 5px;
    color: rgba(255,255,255,.7);
    font-size: 10px;
    font-weight: 700;
}

.sm-topbar__profile-body {
    padding: 13px;
}

.sm-topbar__profile-detail {
    min-height: 58px;
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 9px 10px;
    border-radius: 13px;
    transition: background .18s ease;
}

.sm-topbar__profile-detail:hover {
    background: var(--smt-blue-50);
}

.sm-topbar__profile-detail-icon {
    width: 37px;
    height: 37px;
    flex: 0 0 37px;
    border-radius: 11px;
    color: var(--smt-blue-700);
    background: var(--smt-blue-100);
}

.sm-topbar__profile-detail-icon svg {
    width: 18px;
    height: 18px;
}

.sm-topbar__profile-detail > span:last-child {
    min-width: 0;
}

.sm-topbar__profile-detail small,
.sm-topbar__profile-detail strong {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sm-topbar__profile-detail small {
    color: var(--smt-muted);
    font-size: 9px;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: .05em;
}

.sm-topbar__profile-detail strong {
    margin-top: 4px;
    color: var(--smt-blue-950);
    font-size: 11px;
    font-weight: 850;
}

.sm-topbar__profile-status {
    margin: 8px 3px 12px;
    padding: 9px 11px;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #cde8dc;
    border-radius: 11px;
    color: #287156;
    background: #f0faf6;
    font-size: 9px;
    font-weight: 750;
}

.sm-topbar__profile-status span {
    width: 7px;
    height: 7px;
    flex: 0 0 7px;
    border-radius: 50%;
    background: #1fa877;
    box-shadow: 0 0 0 4px rgba(31, 168, 119, .1);
}

.sm-topbar__logout {
    min-height: 55px;
    display: grid;
    grid-template-columns: 39px minmax(0, 1fr);
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border: 1px solid #f0cfcc;
    border-radius: 13px;
    color: var(--smt-red);
    background: var(--smt-red-soft);
    text-decoration: none;
    transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
}

.sm-topbar__logout:hover {
    transform: translateY(-1px);
    border-color: #e9b4b0;
    box-shadow: 0 8px 18px rgba(194, 59, 52, .1);
}

.sm-topbar__logout > span {
    grid-row: 1 / span 2;
    width: 39px;
    height: 39px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    color: #fff;
    background: linear-gradient(135deg, #db5751, #b62f2a);
}

.sm-topbar__logout svg {
    width: 18px;
    height: 18px;
}

.sm-topbar__logout strong,
.sm-topbar__logout small {
    min-width: 0;
    display: block;
}

.sm-topbar__logout strong {
    align-self: end;
    font-size: 11px;
    font-weight: 900;
}

.sm-topbar__logout small {
    align-self: start;
    margin-top: -1px;
    color: #b56e69;
    font-size: 9px;
    font-weight: 650;
}

.sm-topbar__toast {
    position: fixed;
    right: 22px;
    bottom: 22px;
    z-index: 25000;
    max-width: min(380px, calc(100vw - 30px));
    min-height: 52px;
    padding: 10px 14px 10px 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #bfe1d2;
    border-radius: 14px;
    color: #246b50;
    background: rgba(247, 255, 251, .98);
    box-shadow: 0 16px 38px rgba(20, 68, 50, .16);
    font-size: 11px;
    font-weight: 800;
    animation: smTopbarToastIn .24s ease both;
}

.sm-topbar__toast.is-error {
    border-color: #f0c0bc;
    color: #a93631;
    background: rgba(255, 248, 247, .98);
}

.sm-topbar__toast-icon {
    width: 32px;
    height: 32px;
    flex: 0 0 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    color: #fff;
    background: var(--smt-green);
}

.sm-topbar__toast.is-error .sm-topbar__toast-icon {
    background: var(--smt-red);
}

.sm-topbar__toast-icon svg {
    width: 17px;
    height: 17px;
    stroke-width: 2.2;
}

@keyframes smTopbarPanelIn {
    from {
        opacity: 0;
        transform: translateY(-7px) scale(.985);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes smTopbarToastIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes smTopbarPulse {
    0%, 100% {
        box-shadow: 0 0 0 4px rgba(32, 167, 119, .1);
    }
    50% {
        box-shadow: 0 0 0 7px rgba(32, 167, 119, .04);
    }
}

@keyframes smTopbarSpin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 1380px) {
    .sm-topbar__system-state {
        display: none;
    }
}

@media (max-width: 1160px) {
    .sm-topbar {
        gap: 14px;
        padding-inline: 18px;
    }

    .sm-topbar__datetime {
        min-width: auto;
        width: 47px;
        padding: 6px;
    }

    .sm-topbar__datetime-copy {
        display: none;
    }

    .sm-topbar__divider {
        display: none;
    }

    .sm-topbar__profile {
        min-width: 190px;
    }
}

@media (max-width: 900px) {
    :root {
        --sm-topbar-height: 74px;
    }

    .sm-topbar {
        padding: 10px 14px;
    }

    .sm-topbar__menu {
        display: inline-flex;
    }

    .sm-topbar__page-icon {
        display: none;
    }

    .sm-topbar__title h1 {
        max-width: 42vw;
        font-size: clamp(18px, 3.2vw, 23px);
    }

    .sm-topbar__profile {
        min-width: 180px;
    }

    .sm-topbar__panel {
        max-height: calc(100vh - 92px);
    }
}

@media (max-width: 760px) {
    .sm-topbar {
        gap: 10px;
    }

    .sm-topbar__left {
        gap: 10px;
    }

    .sm-topbar__breadcrumb span,
    .sm-topbar__breadcrumb svg {
        display: none;
    }

    .sm-topbar__title h1 {
        max-width: 38vw;
    }

    .sm-topbar__datetime {
        display: none;
    }

    .sm-topbar__profile {
        min-width: 49px;
        width: 49px;
        padding: 5px;
        justify-content: center;
    }

    .sm-topbar__profile-copy,
    .sm-topbar__arrow {
        display: none;
    }
}

@media (max-width: 560px) {
    :root {
        --sm-topbar-height: 68px;
    }

    .sm-topbar {
        min-height: var(--sm-topbar-height);
        padding: 8px 10px;
    }

    .sm-topbar__menu,
    .sm-topbar__icon-button {
        width: 44px;
        height: 44px;
        flex-basis: 44px;
        border-radius: 13px;
    }

    .sm-topbar__title h1 {
        max-width: calc(100vw - 178px);
        font-size: 16px;
        letter-spacing: -.015em;
    }

    .sm-topbar__breadcrumb {
        margin-bottom: 4px;
        font-size: 9px;
    }

    .sm-topbar__right {
        gap: 7px;
    }

    .sm-topbar__profile {
        width: 44px;
        min-width: 44px;
        min-height: 44px;
        border-radius: 13px;
    }

    .sm-topbar__avatar {
        width: 34px;
        height: 34px;
        flex-basis: 34px;
        border-radius: 10px;
    }

    .sm-topbar__panel {
        position: fixed;
        top: calc(var(--sm-topbar-height) + 7px);
        right: 8px;
        left: 8px;
        width: auto;
        max-height: calc(100vh - var(--sm-topbar-height) - 15px);
        transform-origin: top center;
    }

    .sm-topbar__notification-list {
        max-height: calc(100vh - 235px);
    }

    .sm-topbar__profile-panel {
        left: auto;
        width: min(330px, calc(100vw - 16px));
    }

    .sm-topbar__panel-head {
        min-height: 70px;
        padding: 12px;
    }

    .sm-topbar__notification {
        grid-template-columns: 38px minmax(0, 1fr) 17px;
        gap: 9px;
        padding: 12px;
    }

    .sm-topbar__notification-icon {
        width: 38px;
        height: 38px;
        border-radius: 11px;
    }

    .sm-topbar__toast {
        right: 10px;
        bottom: 10px;
        left: 10px;
        max-width: none;
    }
}

@media (max-width: 390px) {
    .sm-topbar__breadcrumb {
        display: none;
    }

    .sm-topbar__title h1 {
        max-width: calc(100vw - 171px);
    }

    .sm-topbar__text-button span {
        display: none;
    }

    .sm-topbar__text-button {
        width: 35px;
        padding: 0;
    }
}

@media (hover: none) and (pointer: coarse) {
    .sm-topbar__menu,
    .sm-topbar__icon-button,
    .sm-topbar__profile {
        min-height: 48px;
    }

    .sm-topbar__notification {
        min-height: 76px;
    }

    .sm-topbar__text-button {
        min-height: 40px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .sm-topbar *,
    .sm-topbar *::before,
    .sm-topbar *::after,
    .sm-topbar__toast {
        scroll-behavior: auto !important;
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .01ms !important;
    }
}

/*
|--------------------------------------------------------------------------
| Blindaje tipográfico y visual de la topbar
|--------------------------------------------------------------------------
| Estas reglas están deliberadamente limitadas a #smTopbar y
| #smTopbarToast. Evitan que estilos globales de interfaces antiguas
| (h1, strong, small, button, a, etc.) modifiquen la presentación del
| componente sin afectar el resto de la página.
*/
html body #smTopbar,
html body #smTopbar button,
html body #smTopbar a,
html body #smTopbar h1,
html body #smTopbar b,
html body #smTopbar strong,
html body #smTopbar small,
html body #smTopbar span,
html body #smTopbar div,
html body #smTopbar i,
html body #smTopbar time,
html body #smTopbarToast,
html body #smTopbarToast * {
    font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif !important;
    font-style: normal !important;
    font-variant: normal !important;
    text-shadow: none !important;
}

html body #smTopbar button,
html body #smTopbar a {
    font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif !important;
    -webkit-appearance: none !important;
    appearance: none !important;
}

html body #smTopbar a,
html body #smTopbar a:hover,
html body #smTopbar a:focus,
html body #smTopbar a:active,
html body #smTopbar a:visited {
    text-decoration: none !important;
}

html body #smTopbar .sm-topbar__breadcrumb {
    color: var(--smt-muted) !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    line-height: 1 !important;
    letter-spacing: .015em !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__breadcrumb strong {
    color: var(--smt-blue-700) !important;
    font-size: inherit !important;
    font-weight: 850 !important;
    line-height: inherit !important;
    letter-spacing: inherit !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__title h1 {
    margin: 0 !important;
    color: var(--smt-blue-950) !important;
    font-size: clamp(20px, 2vw, 27px) !important;
    font-weight: 850 !important;
    line-height: 1.12 !important;
    letter-spacing: -.025em !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__system-copy strong,
html body #smTopbar .sm-topbar__datetime-copy strong {
    color: var(--smt-text) !important;
    font-size: 12px !important;
    font-weight: 850 !important;
    line-height: 1.15 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__system-copy small,
html body #smTopbar .sm-topbar__datetime-copy small {
    color: var(--smt-muted) !important;
    font-size: 10px !important;
    font-weight: 650 !important;
    line-height: 1.15 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__badge {
    color: #fff !important;
    font-size: 10px !important;
    font-weight: 900 !important;
    line-height: 1 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__profile-copy strong {
    color: var(--smt-blue-950) !important;
    font-size: 12px !important;
    font-weight: 850 !important;
    line-height: 1.2 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__profile-copy small {
    color: var(--smt-muted) !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__panel-title strong {
    color: var(--smt-blue-950) !important;
    font-size: 15px !important;
    font-weight: 900 !important;
    line-height: 1.2 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__panel-title small {
    color: var(--smt-muted) !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    line-height: 1.25 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__text-button {
    color: var(--smt-blue-700) !important;
    font-size: 10px !important;
    font-weight: 850 !important;
    line-height: 1.1 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__notification-line strong {
    color: var(--smt-blue-950) !important;
    font-size: 12px !important;
    font-weight: 850 !important;
    line-height: 1.35 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__notification-message {
    color: var(--smt-muted) !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    line-height: 1.48 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__notification-copy small {
    color: #819ca3 !important;
    font-size: 9px !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__empty strong {
    color: var(--smt-blue-950) !important;
    font-size: 15px !important;
    font-weight: 900 !important;
    line-height: 1.25 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__empty > span:last-child {
    color: var(--smt-muted) !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    line-height: 1.55 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__panel-foot span {
    color: #78969d !important;
    font-size: 9px !important;
    font-weight: 700 !important;
    line-height: 1.3 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__profile-summary strong {
    color: #fff !important;
    font-size: 14px !important;
    font-weight: 900 !important;
    line-height: 1.25 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__profile-summary span {
    color: rgba(255, 255, 255, .7) !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    line-height: 1.25 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__profile-detail small {
    color: var(--smt-muted) !important;
    font-size: 9px !important;
    font-weight: 750 !important;
    line-height: 1.2 !important;
    letter-spacing: .05em !important;
    text-transform: uppercase !important;
}

html body #smTopbar .sm-topbar__profile-detail strong {
    color: var(--smt-blue-950) !important;
    font-size: 11px !important;
    font-weight: 850 !important;
    line-height: 1.25 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__profile-status {
    color: #287156 !important;
    font-size: 9px !important;
    font-weight: 750 !important;
    line-height: 1.25 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__logout strong {
    color: var(--smt-red) !important;
    font-size: 11px !important;
    font-weight: 900 !important;
    line-height: 1.2 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbar .sm-topbar__logout small {
    color: #b56e69 !important;
    font-size: 9px !important;
    font-weight: 650 !important;
    line-height: 1.2 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbarToast {
    color: #246b50 !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    line-height: 1.35 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}

html body #smTopbarToast.is-error {
    color: #a93631 !important;
}

/* Conserva la escala tipográfica responsive aun con estilos externos !important. */
@media (max-width: 900px) {
    html body #smTopbar .sm-topbar__title h1 {
        font-size: clamp(18px, 3.2vw, 23px) !important;
    }
}

@media (max-width: 560px) {
    html body #smTopbar .sm-topbar__title h1 {
        font-size: 16px !important;
        letter-spacing: -.015em !important;
    }

    html body #smTopbar .sm-topbar__breadcrumb {
        font-size: 9px !important;
    }
}



/* Aviso visual breve cuando llega una notificación nueva por actualización automática. */
.sm-topbar__icon-button.has-new-notification {
    animation: smTopbarBellArrival .68s cubic-bezier(.22, 1, .36, 1) both;
}

@keyframes smTopbarBellArrival {
    0%, 100% { transform: translateY(0) rotate(0); }
    22% { transform: translateY(-2px) rotate(-8deg); }
    48% { transform: translateY(-1px) rotate(7deg); }
    72% { transform: translateY(0) rotate(-3deg); }
}
</style>

<script>
(function () {
    'use strict';

    var csrf = <?= json_encode($smTopbarCsrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var endpoint = '../inc/topbar.php';
    var refreshInterval = 8000;
    var refreshTimer = null;
    var refreshController = null;
    var refreshInProgress = false;
    var notificationsMutating = false;
    var lastRefreshAt = 0;
    var toastTimer = null;
    var bellAnimationTimer = null;
    var knownNotificationIds = {};

    function byId(id) {
        return document.getElementById(id);
    }

    function updateDateTime() {
        var now = new Date();
        var dateNode = byId('smTopbarDate');
        var timeNode = byId('smTopbarTime');

        if (dateNode) {
            try {
                dateNode.textContent = new Intl.DateTimeFormat('es-MX', {
                    weekday: 'short',
                    day: '2-digit',
                    month: 'short'
                }).format(now).replace('.', '');
            } catch (error) {
                dateNode.textContent = now.toLocaleDateString('es-MX');
            }
        }

        if (timeNode) {
            try {
                timeNode.textContent = new Intl.DateTimeFormat('es-MX', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                }).format(now);
            } catch (error) {
                timeNode.textContent = now.toLocaleTimeString('es-MX', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
        }
    }

    function showToast(message, type) {
        var toast = byId('smTopbarToast');
        var textNode = byId('smTopbarToastText');

        if (!toast || !textNode) {
            return;
        }

        if (toastTimer) {
            window.clearTimeout(toastTimer);
        }

        textNode.textContent = message;
        toast.classList.toggle('is-error', type === 'error');
        toast.hidden = false;

        toastTimer = window.setTimeout(function () {
            toast.hidden = true;
        }, 3800);
    }

    function setDropdown(dropdown, open) {
        if (!dropdown) {
            return;
        }

        var button = dropdown.querySelector('[data-topbar-toggle]');
        var panel = dropdown.querySelector('[data-topbar-panel]');

        if (!button || !panel) {
            return;
        }

        panel.hidden = !open;
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function closeAll(except) {
        document.querySelectorAll('[data-topbar-dropdown]').forEach(function (dropdown) {
            if (dropdown !== except) {
                setDropdown(dropdown, false);
            }
        });
    }

    function notificationBellButton() {
        return document.querySelector('[aria-controls="smTopbarNotifications"]');
    }

    function animateNotificationBell() {
        var button = notificationBellButton();

        if (!button) {
            return;
        }

        if (bellAnimationTimer) {
            window.clearTimeout(bellAnimationTimer);
        }

        button.classList.remove('has-new-notification');
        void button.offsetWidth;
        button.classList.add('has-new-notification');

        bellAnimationTimer = window.setTimeout(function () {
            button.classList.remove('has-new-notification');
        }, 900);
    }

    document.querySelectorAll('[data-topbar-dropdown]').forEach(function (dropdown) {
        var button = dropdown.querySelector('[data-topbar-toggle]');
        var panel = dropdown.querySelector('[data-topbar-panel]');

        if (!button || !panel) {
            return;
        }

        button.addEventListener('click', function (event) {
            event.stopPropagation();

            var shouldOpen = panel.hidden;
            closeAll(dropdown);
            setDropdown(dropdown, shouldOpen);

            if (shouldOpen && panel.id === 'smTopbarNotifications') {
                refreshNotifications({silent: true, force: true});
            }

            if (shouldOpen) {
                window.setTimeout(function () {
                    var firstInteractive = panel.querySelector('button:not(:disabled), a[href]');
                    if (firstInteractive && window.matchMedia('(max-width: 560px)').matches) {
                        firstInteractive.focus({preventScroll: true});
                    }
                }, 30);
            }
        });

        panel.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });

    document.addEventListener('click', function () {
        closeAll(null);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAll(null);
        }
    });

    var menuButton = byId('smTopbarMenu');

    if (menuButton) {
        menuButton.addEventListener('click', function () {
            closeAll(null);

            if (typeof window.smAbrirMenu === 'function') {
                window.smAbrirMenu();
            }
        });
    }

    function postAction(data) {
        var controller = typeof AbortController !== 'undefined'
            ? new AbortController()
            : null;

        var timeout = window.setTimeout(function () {
            if (controller) {
                controller.abort();
            }
        }, 12000);

        data.append('csrf_token', csrf);

        return fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: data,
            credentials: 'same-origin',
            cache: 'no-store',
            signal: controller ? controller.signal : undefined
        }).then(function (response) {
            return response.text().then(function (raw) {
                var json;

                try {
                    json = raw ? JSON.parse(raw) : {};
                } catch (error) {
                    throw new Error('El servidor devolvió una respuesta inválida.');
                }

                if (!response.ok || !json.ok) {
                    throw new Error(json.mensaje || 'No fue posible completar la acción.');
                }

                return json;
            });
        }).catch(function (error) {
            if (error && error.name === 'AbortError') {
                throw new Error('La solicitud tardó demasiado. Inténtalo nuevamente.');
            }

            throw error;
        }).finally(function () {
            window.clearTimeout(timeout);
        });
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safeNotificationType(value) {
        var normalized = String(value || 'info')
            .toLocaleLowerCase('es-MX')
            .replace(/[^a-z0-9_-]/g, '');

        return normalized || 'info';
    }

    function notificationIcon(type) {
        if (['danger', 'urgente', 'error'].indexOf(type) !== -1) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true">' +
                '<path d="M12 9v4"></path><path d="M12 17h.01"></path>' +
                '<path d="M10.3 4.6 2.7 18a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 4.6a2 2 0 0 0-3.4 0Z"></path>' +
            '</svg>';
        }

        if (['success', 'exito', 'terminado'].indexOf(type) !== -1) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true">' +
                '<circle cx="12" cy="12" r="9"></circle>' +
                '<path d="m8 12 2.7 2.7L16.5 9"></path>' +
            '</svg>';
        }

        if (['warning', 'advertencia'].indexOf(type) !== -1) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true">' +
                '<path d="M12 9v4"></path><path d="M12 17h.01"></path>' +
                '<path d="M10.3 4.6 2.7 18a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 4.6a2 2 0 0 0-3.4 0Z"></path>' +
            '</svg>';
        }

        return '<svg viewBox="0 0 24 24" aria-hidden="true">' +
            '<circle cx="12" cy="12" r="9"></circle>' +
            '<path d="M12 11v5"></path><path d="M12 8h.01"></path>' +
        '</svg>';
    }

    function emptyNotificationsHtml() {
        return '<div class="sm-topbar__empty">' +
            '<span class="sm-topbar__empty-icon" aria-hidden="true">' +
                '<svg viewBox="0 0 24 24">' +
                    '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>' +
                    '<path d="M9 21h6"></path>' +
                '</svg>' +
            '</span>' +
            '<strong>Todo está al día</strong>' +
            '<span>No tienes notificaciones por el momento.</span>' +
        '</div>';
    }

    function notificationHtml(notification) {
        var id = Number(notification.id || 0);
        var unread = Number(notification.leida || 0) === 0;
        var type = safeNotificationType(notification.tipo);
        var destination = escapeHtml(notification.destino || '#');
        var unreadDot = unread ? '<i aria-label="No leída" title="No leída"></i>' : '';

        return '<a href="' + destination + '" ' +
            'class="sm-topbar__notification' + (unread ? ' is-unread' : '') + '" ' +
            'data-notification-id="' + id + '">' +
                '<span class="sm-topbar__notification-icon sm-type-' + type + '" aria-hidden="true">' +
                    notificationIcon(type) +
                '</span>' +
                '<span class="sm-topbar__notification-copy">' +
                    '<span class="sm-topbar__notification-line">' +
                        '<strong>' + escapeHtml(notification.titulo || '') + '</strong>' +
                        unreadDot +
                    '</span>' +
                    '<span class="sm-topbar__notification-message">' +
                        escapeHtml(notification.mensaje || '') +
                    '</span>' +
                    '<small>' +
                        '<svg viewBox="0 0 24 24" aria-hidden="true">' +
                            '<circle cx="12" cy="12" r="9"></circle>' +
                            '<path d="M12 7v5l3 2"></path>' +
                        '</svg>' +
                        escapeHtml(notification.fecha || '') +
                    '</small>' +
                '</span>' +
                '<span class="sm-topbar__notification-arrow" aria-hidden="true">' +
                    '<svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"></path></svg>' +
                '</span>' +
        '</a>';
    }

    function updateUnreadSummary(value) {
        var unread = Math.max(0, Number(value || 0));
        var summary = byId('smNotificationSummary');
        var badge = byId('smNotificationBadge');
        var markAll = byId('smMarkAll');

        if (summary) {
            summary.textContent = unread.toLocaleString('es-MX') + ' sin leer';
        }

        if (badge) {
            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.hidden = unread === 0;
        }

        if (markAll) {
            markAll.hidden = unread === 0;
            if (unread === 0) {
                markAll.disabled = false;
                markAll.classList.remove('is-loading');
            }
        }

        var bell = notificationBellButton();
        if (bell) {
            bell.setAttribute(
                'aria-label',
                unread > 0
                    ? 'Abrir notificaciones, ' + unread + ' sin leer'
                    : 'Abrir notificaciones'
            );
        }
    }

    function rememberCurrentNotificationIds() {
        document.querySelectorAll('[data-notification-id]').forEach(function (item) {
            var id = Number(item.getAttribute('data-notification-id'));
            if (id > 0) {
                knownNotificationIds[id] = true;
            }
        });
    }

    function renderNotifications(items, unreadCount, notifyArrival) {
        var list = byId('smTopbarNotificationList');
        var notifications = Array.isArray(items) ? items : [];
        var previousScroll = list ? list.scrollTop : 0;
        var hasNewUnread = false;

        notifications.forEach(function (notification) {
            var id = Number(notification.id || 0);
            if (
                notifyArrival
                && id > 0
                && Number(notification.leida || 0) === 0
                && !knownNotificationIds[id]
            ) {
                hasNewUnread = true;
            }
        });

        if (list) {
            list.innerHTML = notifications.length > 0
                ? notifications.map(notificationHtml).join('')
                : emptyNotificationsHtml();

            window.requestAnimationFrame(function () {
                list.scrollTop = Math.min(previousScroll, Math.max(0, list.scrollHeight - list.clientHeight));
            });
        }

        notifications.forEach(function (notification) {
            var id = Number(notification.id || 0);
            if (id > 0) {
                knownNotificationIds[id] = true;
            }
        });

        updateUnreadSummary(unreadCount);

        if (hasNewUnread) {
            animateNotificationBell();
        }
    }

    function parseJsonResponse(response) {
        return response.text().then(function (raw) {
            var json;

            try {
                json = raw ? JSON.parse(raw) : {};
            } catch (error) {
                throw new Error('El servidor devolvió una respuesta inválida.');
            }

            if (!response.ok || !json.ok) {
                throw new Error(json.mensaje || 'No fue posible consultar las notificaciones.');
            }

            return json;
        });
    }

    function scheduleNotificationRefresh(delay) {
        if (refreshTimer) {
            window.clearTimeout(refreshTimer);
        }

        refreshTimer = window.setTimeout(function () {
            refreshNotifications({silent: true});
        }, Math.max(1000, Number(delay || refreshInterval)));
    }

    function refreshNotifications(options) {
        var settings = options || {};
        var silent = settings.silent !== false;
        var force = settings.force === true;

        if (document.hidden && !force) {
            scheduleNotificationRefresh(refreshInterval);
            return Promise.resolve(null);
        }

        if (notificationsMutating && !force) {
            scheduleNotificationRefresh(1200);
            return Promise.resolve(null);
        }

        if (refreshInProgress) {
            return Promise.resolve(null);
        }

        refreshInProgress = true;

        if (refreshController && typeof refreshController.abort === 'function') {
            refreshController.abort();
        }

        refreshController = typeof AbortController !== 'undefined'
            ? new AbortController()
            : null;

        var timeout = window.setTimeout(function () {
            if (refreshController) {
                refreshController.abort();
            }
        }, 10000);

        return fetch(
            endpoint + '?accion=listar_notificaciones&t=' + Date.now(),
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: refreshController ? refreshController.signal : undefined
            }
        ).then(parseJsonResponse).then(function (response) {
            var data = response.datos || {};
            var serverInterval = Number(data.intervalo_ms || 0);

            if (serverInterval >= 3000 && serverInterval <= 60000) {
                refreshInterval = serverInterval;
            }

            renderNotifications(
                Array.isArray(data.notificaciones) ? data.notificaciones : [],
                Number(data.no_leidas || 0),
                lastRefreshAt > 0
            );
            lastRefreshAt = Date.now();
            return response;
        }).catch(function (error) {
            if (error && error.name === 'AbortError') {
                return null;
            }

            if (!silent && navigator.onLine !== false) {
                showToast(error.message || 'No fue posible actualizar las notificaciones.', 'error');
            }

            return null;
        }).finally(function () {
            window.clearTimeout(timeout);
            refreshInProgress = false;
            refreshController = null;
            scheduleNotificationRefresh(refreshInterval);
        });
    }

    var notificationList = byId('smTopbarNotificationList');

    if (notificationList) {
        notificationList.addEventListener('click', function (event) {
            var link = event.target.closest('[data-notification-id]');

            if (!link || !notificationList.contains(link)) {
                return;
            }

            if (!link.classList.contains('is-unread')) {
                return;
            }

            event.preventDefault();

            var destination = link.getAttribute('href');
            var data = new FormData();

            notificationsMutating = true;
            link.classList.add('is-loading');
            data.append('accion', 'marcar_leida');
            data.append('notificacion_id', link.getAttribute('data-notification-id'));

            postAction(data)
                .catch(function () {
                    /* La navegación continúa para no bloquear el acceso a la información. */
                })
                .finally(function () {
                    notificationsMutating = false;
                    window.location.href = destination;
                });
        });
    }

    var markAll = byId('smMarkAll');

    if (markAll) {
        markAll.addEventListener('click', function () {
            if (markAll.disabled || markAll.hidden) {
                return;
            }

            notificationsMutating = true;
            markAll.disabled = true;
            markAll.classList.add('is-loading');

            var data = new FormData();
            data.append('accion', 'marcar_todas');

            postAction(data).then(function (response) {
                document.querySelectorAll('[data-notification-id]').forEach(function (item) {
                    item.classList.remove('is-unread');
                });

                document.querySelectorAll('.sm-topbar__notification-line i').forEach(function (dot) {
                    dot.remove();
                });

                updateUnreadSummary(0);
                showToast(
                    response.mensaje || 'Todas las notificaciones fueron marcadas como leídas.',
                    'success'
                );
            }).catch(function (error) {
                showToast(error.message || 'No fue posible actualizar las notificaciones.', 'error');
                markAll.disabled = false;
                markAll.classList.remove('is-loading');
            }).finally(function () {
                notificationsMutating = false;
                refreshNotifications({silent: true, force: true});
            });
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            refreshNotifications({silent: true, force: true});
        }
    });

    window.addEventListener('focus', function () {
        if (Date.now() - lastRefreshAt > 2000) {
            refreshNotifications({silent: true, force: true});
        }
    });

    window.addEventListener('online', function () {
        refreshNotifications({silent: true, force: true});
    });

    window.addEventListener('resize', function () {
        closeAll(null);
    });

    window.addEventListener('pagehide', function () {
        if (refreshTimer) {
            window.clearTimeout(refreshTimer);
        }
        if (refreshController && typeof refreshController.abort === 'function') {
            refreshController.abort();
        }
    });

    rememberCurrentNotificationIds();
    updateDateTime();
    window.setInterval(updateDateTime, 30000);
    scheduleNotificationRefresh(1200);
})();
</script>