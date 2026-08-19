<?php

declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';

sm_requerir_sesion([], false);

/*
|--------------------------------------------------------------------------
| Contexto del sidebar
|--------------------------------------------------------------------------
*/

$smSidebarRol = strtoupper((string) ($_SESSION['tipo_usuario'] ?? ''));
$smSidebarVista = basename((string) parse_url(
    (string) ($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? ''),
    PHP_URL_PATH
));

$smSidebarNombreUsuario = trim((string) (
    $_SESSION['nombre_completo']
    ?? $_SESSION['nombre']
    ?? $_SESSION['usuario']
    ?? 'Usuario'
));

if ($smSidebarNombreUsuario === '') {
    $smSidebarNombreUsuario = 'Usuario';
}

/*
|--------------------------------------------------------------------------
| Utilidades
|--------------------------------------------------------------------------
*/

if (!function_exists('sm_sidebar_e')) {
    function sm_sidebar_e($valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sm_sidebar_activo')) {
    function sm_sidebar_activo(array $item, string $vista): bool
    {
        if (($item['url'] ?? '') === $vista) {
            return true;
        }

        return in_array($vista, (array) ($item['alias'] ?? []), true);
    }
}

if (!function_exists('sm_sidebar_grupo_activo')) {
    function sm_sidebar_grupo_activo(array $grupo, string $vista): bool
    {
        foreach ((array) ($grupo['items'] ?? []) as $item) {
            if (sm_sidebar_activo($item, $vista)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('sm_sidebar_iniciales')) {
    function sm_sidebar_iniciales(string $nombre): string
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            return 'US';
        }

        $partes = preg_split('/\s+/u', $nombre) ?: [];
        $partes = array_values(array_filter($partes, static function ($parte): bool {
            return trim((string) $parte) !== '';
        }));

        if ($partes === []) {
            return 'US';
        }

        $obtenerCaracter = static function (string $texto, int $posicion = 0): string {
            if (function_exists('mb_substr')) {
                return mb_substr($texto, $posicion, 1, 'UTF-8');
            }

            preg_match_all('/./us', $texto, $coincidencias);

            return (string) ($coincidencias[0][$posicion] ?? '');
        };

        $primera = $obtenerCaracter((string) $partes[0], 0);
        $ultima = count($partes) > 1
            ? $obtenerCaracter((string) $partes[count($partes) - 1], 0)
            : $obtenerCaracter((string) $partes[0], 1);

        $iniciales = function_exists('mb_strtoupper')
            ? mb_strtoupper($primera . $ultima, 'UTF-8')
            : strtoupper($primera . $ultima);

        return $iniciales !== '' ? $iniciales : 'US';
    }
}

if (!function_exists('sm_sidebar_minusculas')) {
    function sm_sidebar_minusculas(string $valor): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($valor, 'UTF-8')
            : strtolower($valor);
    }
}

if (!function_exists('sm_sidebar_icono')) {
    function sm_sidebar_icono(string $nombre): string
    {
        $iconos = [
            'brand' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.75 3.75h10.5a3 3 0 0 1 3 3v10.5a3 3 0 0 1-3 3H6.75a3 3 0 0 1-3-3V6.75a3 3 0 0 1 3-3Z"/><path d="M8 15.75V8.5l4 4 4-4v7.25"/></svg>',
            'home' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3.5 11 8.5-7 8.5 7"/><path d="M5.5 9.8v9.2h13V9.8M9.25 19v-5.5h5.5V19"/></svg>',
            'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="4.5" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="10.5" width="7" height="10" rx="1.5"/></svg>',
            'requests' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.5h10M7 9h10M7 13.5h6"/><path d="M5 2.75h14a2 2 0 0 1 2 2v14.5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4.75a2 2 0 0 1 2-2Z"/><path d="m15.2 17.2 1.35 1.35 2.75-3"/></svg>',
            'review' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.75"/><path d="m16 16 4 4M11 7.5V11l2.25 1.5"/></svg>',
            'schedule' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5.25" width="17" height="15" rx="2"/><path d="M7.5 3.25v4M16.5 3.25v4M3.5 9.25h17"/><path d="m8 15 2 2 5-5"/></svg>',
            'history' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.25 12a7.75 7.75 0 1 0 2.27-5.48L4.25 8.8"/><path d="M4.25 4.5v4.3h4.3M12 7.75V12l3 1.75"/></svg>',
            'plus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
            'corrective' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.7 5.1 4.2 4.2M13.5 6.3l4.2 4.2M4.25 19.75l5.8-1.45 9-9a2.55 2.55 0 0 0-3.6-3.6l-9 9-2.2 5.05Z"/></svg>',
            'improvement' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.25a7 7 0 0 0-4.1 12.67c.7.5 1.1 1.3 1.1 2.16v.17h6v-.17c0-.86.4-1.66 1.1-2.16A7 7 0 0 0 12 3.25Z"/><path d="M9.25 21h5.5M9 18.25h6M12 6.5v5.5M9.25 9.25h5.5"/></svg>',
            'urgent' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.35 4.5 3.5 17a2 2 0 0 0 1.75 3h13.5a2 2 0 0 0 1.75-3L13.65 4.5a1.88 1.88 0 0 0-3.3 0Z"/><path d="M12 8.25v5.25M12 17.25h.01"/></svg>',
            'operation' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.25 5.5 4.25 4.25M4.5 19.5l5.25-5.25M8.5 4.25a4.25 4.25 0 0 0 5.15 5.15l6.1 6.1a2.48 2.48 0 0 1-3.5 3.5l-6.1-6.1A4.25 4.25 0 0 0 4.25 8.5l2.7 1.35 2.9-2.9L8.5 4.25Z"/></svg>',
            'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M7.5 3v4M16.5 3v4M3.5 9.25h17"/><path d="M7.5 13h3M13.5 13h3M7.5 17h3M13.5 17h3"/></svg>',
            'routine' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.5 8.25A8 8 0 0 0 5.1 6.1L3.5 8"/><path d="M3.5 4.5V8h3.5M4.5 15.75A8 8 0 0 0 18.9 17.9l1.6-1.9"/><path d="M20.5 19.5V16H17"/></svg>',
            'tracking' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.25"/><circle cx="12" cy="12" r="1"/></svg>',
            'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.25V12l3.25 2"/></svg>',
            'compliance' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.25 20 7v5c0 4.75-3.05 7.4-8 8.75C7.05 19.4 4 16.75 4 12V7l8-3.75Z"/><path d="m8.5 12 2.25 2.25L15.75 9"/></svg>',
            'movements' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6.25h14M5 12h14M5 17.75h14"/><circle cx="8" cy="6.25" r="1.5"/><circle cx="15.5" cy="12" r="1.5"/><circle cx="10.5" cy="17.75" r="1.5"/></svg>',
            'people' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.25"/><path d="M3.5 19.5v-1.25A4.75 4.75 0 0 1 8.25 13.5h1.5a4.75 4.75 0 0 1 4.75 4.75v1.25"/><circle cx="17" cy="9" r="2.25"/><path d="M16 14h.75a3.75 3.75 0 0 1 3.75 3.75v1.75"/></svg>',
            'admin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.25"/><path d="M5.5 19.75v-1.5A5.25 5.25 0 0 1 10.75 13h2.5a5.25 5.25 0 0 1 5.25 5.25v1.5"/><path d="m16.75 5.25 1 1 2-2"/></svg>',
            'requester' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9.5" cy="8" r="3.25"/><path d="M3.5 19.75v-1.5A5.25 5.25 0 0 1 8.75 13h1.5a5.25 5.25 0 0 1 5.25 5.25v1.5"/><path d="M17 9h4M19 7v4"/></svg>',
            'technician' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="7.75" r="3"/><path d="M3.75 19.5v-1.25A4.75 4.75 0 0 1 8.5 13.5h1a4.75 4.75 0 0 1 4.75 4.75v1.25"/><path d="m15.25 10.5 5 5M16.25 17.25l1.5-1.5M13.75 12l1.5-1.5"/></svg>',
            'catalog' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.25A2.25 2.25 0 0 1 6.25 3H19v16.5H6.25A2.25 2.25 0 0 1 4 17.25v-12Z"/><path d="M4 17.25A2.25 2.25 0 0 1 6.25 15H19M8 7h7M8 10.5h5"/></svg>',
            'equipment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="13" rx="2"/><path d="M7 21h10M9 18v3M15 18v3M8 9h8M8 13h4"/></svg>',
            'resources' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.2 5.7a4.25 4.25 0 0 0-5.45 5.45L4 15.9a2.4 2.4 0 0 0 3.4 3.4l4.75-4.75A4.25 4.25 0 0 0 17.6 9.1l-2.65 2.65-2.7-2.7 1.95-3.35Z"/><circle cx="18.2" cy="17.7" r="2.4"/><path d="M18.2 13.9v1.4M18.2 20.1v1.4M14.4 17.7h1.4M20.6 17.7H22"/></svg>',
            'area' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20.5V8.25L12 3.5l8 4.75V20.5M8 20.5v-7h8v7"/><path d="M9 9h.01M12 9h.01M15 9h.01"/></svg>',
            'department' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20.5V6.5h10v14M14 10.5h6v10M7.5 10h3M7.5 14h3M7.5 18h3M17 14h.01M17 18h.01"/></svg>',
            'process' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M8.5 6H14a4 4 0 0 1 4 4v1M15.5 18H10a4 4 0 0 1-4-4v-1"/></svg>',
            'work' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="6.5" width="17" height="13" rx="2"/><path d="M8 6.5V4.75A1.75 1.75 0 0 1 9.75 3h4.5A1.75 1.75 0 0 1 16 4.75V6.5M3.5 11.5h17M9.5 11.5v2h5v-2"/></svg>',
            'assigned' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4h8M8 8h8M8 12h5"/><rect x="4" y="2.75" width="16" height="18.5" rx="2"/><path d="m14.5 17 1.5 1.5 3-3"/></svg>',
            'play' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="m10 8.75 5.25 3.25L10 15.25v-6.5Z"/></svg>',
            'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="m8.25 12 2.5 2.5 5-5"/></svg>',
            'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.75" cy="10.75" r="6.25"/><path d="m15.5 15.5 4 4"/></svg>',
            'chevron' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 10 4 4 4-4"/></svg>',
            'collapse' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 6.5-5 5.5 5 5.5"/></svg>',
            'close' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6.5 6.5 11 11M17.5 6.5l-11 11"/></svg>',
            'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5.5A2.5 2.5 0 0 0 3 6.5v11A2.5 2.5 0 0 0 5.5 20H10M14.5 8l4 4-4 4M8 12h10.5"/></svg>',
            'online' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5a7 7 0 0 1 14 0M8 12.5a4 4 0 0 1 8 0M12 16.5h.01"/></svg>',
        ];

        return $iconos[$nombre] ?? $iconos['dashboard'];
    }
}

/*
|--------------------------------------------------------------------------
| Menús por rol
|--------------------------------------------------------------------------
| Las rutas, aliases y permisos conservan exactamente la estructura original.
*/

$smSidebarMenus = [
    'ADMIN' => [
        [
            'clave' => 'inicio',
            'titulo' => 'Inicio',
            'icono' => 'home',
            'items' => [
                ['texto' => 'Dashboard', 'icono' => 'dashboard', 'url' => 'dashboard_admin.php'],
            ],
        ],
        [
            'clave' => 'solicitudes',
            'titulo' => 'Solicitudes',
            'icono' => 'requests',
            'items' => [
                ['texto' => 'Por revisar', 'icono' => 'review', 'url' => 'solicitudes_pendientes.php'],
                ['texto' => 'Programar y asignar', 'icono' => 'schedule', 'url' => 'solicitudes_programacion.php', 'alias' => ['solicitudes_aprobadas.php']],
                ['texto' => 'Todas las solicitudes', 'icono' => 'history', 'url' => 'solicitudes_historial.php'],
            ],
        ],
        [
            'clave' => 'registrar_solicitud',
            'titulo' => 'Registrar solicitud',
            'icono' => 'plus',
            'items' => [
                ['texto' => 'Correctivo programable', 'icono' => 'corrective', 'url' => 'solicitud_correctivo_programable.php'],
                ['texto' => 'Modificación o mejora', 'icono' => 'improvement', 'url' => 'solicitud_modificacion_mejora.php'],
                ['texto' => 'Correctivo urgente', 'icono' => 'urgent', 'url' => 'solicitud_correctivo_urgente.php', 'urgente' => true],
            ],
        ],
        [
            'clave' => 'operacion',
            'titulo' => 'Operación',
            'icono' => 'operation',
            'items' => [
                ['texto' => 'Agenda semanal', 'icono' => 'calendar', 'url' => 'agenda_semanal.php', 'alias' => ['agenda.php']],
                ['texto' => 'Rutinas', 'icono' => 'routine', 'url' => 'rutinas.php'],
                ['texto' => 'Calendario laboral', 'icono' => 'calendar', 'url' => 'calendario_laboral.php'],
            ],
        ],
        [
            'clave' => 'seguimiento',
            'titulo' => 'Seguimiento',
            'icono' => 'tracking',
            'items' => [
                ['texto' => 'Tiempos reales', 'icono' => 'clock', 'url' => 'tiempos_ejecucion.php'],
                ['texto' => 'Cumplimiento', 'icono' => 'compliance', 'url' => 'incumplimientos.php', 'alias' => ['mantenimientos_no_realizados.php']],
                ['texto' => 'Movimientos', 'icono' => 'movements', 'url' => 'movimientos_sistema.php'],
            ],
        ],
        [
            'clave' => 'personal',
            'titulo' => 'Personal',
            'icono' => 'people',
            'items' => [
                ['texto' => 'Administradores', 'icono' => 'admin', 'url' => 'administradores.php'],
                ['texto' => 'Solicitantes', 'icono' => 'requester', 'url' => 'solicitantes.php'],
                ['texto' => 'Técnicos', 'icono' => 'technician', 'url' => 'tecnicos.php'],
            ],
        ],
        [
            'clave' => 'catalogos',
            'titulo' => 'Catálogos',
            'icono' => 'catalog',
            'items' => [
                ['texto' => 'Herramientas y refacciones', 'icono' => 'resources', 'url' => 'recursos_mantenimiento.php'],
                ['texto' => 'Equipos', 'icono' => 'equipment', 'url' => 'equipos.php'],
                ['texto' => 'Áreas', 'icono' => 'area', 'url' => 'areas.php'],
                ['texto' => 'Departamentos', 'icono' => 'department', 'url' => 'departamentos.php'],
                ['texto' => 'Procesos', 'icono' => 'process', 'url' => 'procesos.php'],
            ],
        ],
    ],

    'SOLICITANTE' => [
        [
            'clave' => 'inicio',
            'titulo' => 'Inicio',
            'icono' => 'home',
            'items' => [
                ['texto' => 'Inicio', 'icono' => 'dashboard', 'url' => 'dashboard_solicitante.php'],
                ['texto' => 'Mis solicitudes', 'icono' => 'history', 'url' => 'bandeja_solicitante.php'],
            ],
        ],
        [
            'clave' => 'nueva',
            'titulo' => 'Nueva solicitud',
            'icono' => 'plus',
            'items' => [
                ['texto' => 'Correctivo programable', 'icono' => 'corrective', 'url' => 'solicitud_correctivo_programable.php'],
                ['texto' => 'Modificación o mejora', 'icono' => 'improvement', 'url' => 'solicitud_modificacion_mejora.php'],
                ['texto' => 'Correctivo urgente', 'icono' => 'urgent', 'url' => 'solicitud_correctivo_urgente.php', 'urgente' => true],
            ],
        ],
    ],

    'TECNICO' => [
        [
            'clave' => 'inicio',
            'titulo' => 'Inicio',
            'icono' => 'home',
            'items' => [
                ['texto' => 'Dashboard', 'icono' => 'dashboard', 'url' => 'dashboard_tecnico.php'],
            ],
        ],
        [
            'clave' => 'trabajo',
            'titulo' => 'Mi trabajo',
            'icono' => 'work',
            'items' => [
                ['texto' => 'Urgencias disponibles', 'icono' => 'urgent', 'url' => 'urgencias_disponibles.php', 'urgente' => true],
                ['texto' => 'Asignados', 'icono' => 'assigned', 'url' => 'mantenimientos_asignados.php', 'alias' => ['mantenimientos_pendientes.php', 'mantenimientos_no_terminados.php']],
                ['texto' => 'Actividad actual', 'icono' => 'play', 'url' => 'mantenimiento_activo.php'],
                ['texto' => 'Historial', 'icono' => 'check', 'url' => 'mantenimientos_finalizados.php'],
            ],
        ],
    ],
];

$smSidebarMenu = $smSidebarMenus[$smSidebarRol] ?? [];
$smSidebarNombreRol = [
    'ADMIN' => 'Administrador',
    'SOLICITANTE' => 'Solicitante',
    'TECNICO' => 'Técnico',
][$smSidebarRol] ?? 'Usuario';

$smSidebarInicio = [
    'ADMIN' => 'dashboard_admin.php',
    'SOLICITANTE' => 'dashboard_solicitante.php',
    'TECNICO' => 'dashboard_tecnico.php',
][$smSidebarRol] ?? '../login.php';

$smSidebarIniciales = sm_sidebar_iniciales($smSidebarNombreUsuario);
$smSidebarCantidadOpciones = 0;

foreach ($smSidebarMenu as $smSidebarGrupoConteo) {
    $smSidebarCantidadOpciones += count((array) ($smSidebarGrupoConteo['items'] ?? []));
}
?>

<script>
(function () {
    'use strict';

    /*
     * Evita que el menú se anime desde arriba antes de recuperar su posición.
     * La clase se elimina después de restaurar grupos, modo compacto y scroll.
     */
    document.documentElement.classList.add('sm-sidebar-restoring');
})();
</script>

<aside class="sm-sidebar" id="smSidebar" aria-label="Menú principal">
    <span class="sm-sidebar__ambient sm-sidebar__ambient--one" aria-hidden="true"></span>
    <span class="sm-sidebar__ambient sm-sidebar__ambient--two" aria-hidden="true"></span>

    <header class="sm-sidebar__head">
        <a
            class="sm-sidebar__brand"
            href="<?= sm_sidebar_e($smSidebarInicio) ?>"
            aria-label="Ir al inicio del sistema"
            data-tooltip="Inicio"
        >
            <span class="sm-sidebar__logo" aria-hidden="true">
                <?= sm_sidebar_icono('brand') ?>
            </span>
            <span class="sm-sidebar__brand-copy">
                <strong>Sistema de Mantenimiento</strong>
                <small>Los Chapeteados · Petfood</small>
            </span>
        </a>

        <button
            type="button"
            class="sm-sidebar__icon-button sm-sidebar__collapse"
            id="smSidebarCollapse"
            aria-label="Contraer menú"
            aria-pressed="false"
            title="Contraer menú"
            data-tooltip="Contraer menú"
        >
            <?= sm_sidebar_icono('collapse') ?>
        </button>

        <button
            type="button"
            class="sm-sidebar__icon-button sm-sidebar__mobile-close"
            id="smSidebarMobileClose"
            aria-label="Cerrar menú"
            title="Cerrar menú"
        >
            <?= sm_sidebar_icono('close') ?>
        </button>
    </header>

    <section class="sm-sidebar__profile" aria-label="Usuario conectado">
        <span class="sm-sidebar__avatar" aria-hidden="true">
            <?= sm_sidebar_e($smSidebarIniciales) ?>
            <span class="sm-sidebar__avatar-status"></span>
        </span>

        <span class="sm-sidebar__profile-copy">
            <small>Sesión activa</small>
            <strong title="<?= sm_sidebar_e($smSidebarNombreUsuario) ?>">
                <?= sm_sidebar_e($smSidebarNombreUsuario) ?>
            </strong>
            <span>
                <i aria-hidden="true"><?= sm_sidebar_icono('online') ?></i>
                <?= sm_sidebar_e($smSidebarNombreRol) ?>
            </span>
        </span>
    </section>

    <div class="sm-sidebar__search" id="smSidebarSearchWrap">
        <span class="sm-sidebar__search-icon" aria-hidden="true">
            <?= sm_sidebar_icono('search') ?>
        </span>
        <label class="sm-sidebar__sr-only" for="smSidebarSearch">Buscar una opción del menú</label>
        <input
            type="search"
            id="smSidebarSearch"
            placeholder="Buscar en el menú..."
            autocomplete="off"
            spellcheck="false"
            maxlength="80"
        >
        <kbd aria-hidden="true">/</kbd>
        <button
            type="button"
            class="sm-sidebar__search-clear"
            id="smSidebarSearchClear"
            aria-label="Limpiar búsqueda"
            title="Limpiar búsqueda"
            hidden
        >
            <?= sm_sidebar_icono('close') ?>
        </button>
    </div>

    <div class="sm-sidebar__nav-heading">
        <span>Navegación</span>
        <small><?= (int) $smSidebarCantidadOpciones ?> opciones</small>
    </div>

    <nav class="sm-sidebar__nav" id="smSidebarNav">
        <?php foreach ($smSidebarMenu as $grupo): ?>
            <?php
            $grupoActivo = sm_sidebar_grupo_activo($grupo, $smSidebarVista);
            $cantidadItems = count((array) ($grupo['items'] ?? []));
            ?>
            <section
                class="sm-sidebar__group <?= $grupoActivo ? 'is-open is-current' : '' ?>"
                data-sidebar-group
                data-group-key="<?= sm_sidebar_e((string) ($grupo['clave'] ?? 'grupo')) ?>"
            >
                <button
                    type="button"
                    class="sm-sidebar__group-button"
                    data-sidebar-group-button
                    data-tooltip="<?= sm_sidebar_e($grupo['titulo']) ?>"
                    aria-expanded="<?= $grupoActivo ? 'true' : 'false' ?>"
                >
                    <span class="sm-sidebar__group-icon" aria-hidden="true">
                        <?= sm_sidebar_icono((string) $grupo['icono']) ?>
                    </span>
                    <span class="sm-sidebar__group-title"><?= sm_sidebar_e($grupo['titulo']) ?></span>
                    <span class="sm-sidebar__group-count" aria-label="<?= (int) $cantidadItems ?> opciones">
                        <?= (int) $cantidadItems ?>
                    </span>
                    <span class="sm-sidebar__chevron" aria-hidden="true">
                        <?= sm_sidebar_icono('chevron') ?>
                    </span>
                </button>

                <div class="sm-sidebar__items">
                    <?php foreach ($grupo['items'] as $item): ?>
                        <?php $activo = sm_sidebar_activo($item, $smSidebarVista); ?>
                        <a
                            href="<?= sm_sidebar_e($item['url']) ?>"
                            class="sm-sidebar__item <?= $activo ? 'is-active' : '' ?> <?= !empty($item['urgente']) ? 'is-urgent' : '' ?>"
                            <?= $activo ? 'aria-current="page"' : '' ?>
                            title="<?= sm_sidebar_e($item['texto']) ?>"
                            data-tooltip="<?= sm_sidebar_e($item['texto']) ?>"
                            data-sidebar-item
                            data-search-text="<?= sm_sidebar_e(sm_sidebar_minusculas((string) $grupo['titulo'] . ' ' . (string) $item['texto'])) ?>"
                        >
                            <span class="sm-sidebar__item-icon" aria-hidden="true">
                                <?= sm_sidebar_icono((string) $item['icono']) ?>
                            </span>
                            <span class="sm-sidebar__item-text"><?= sm_sidebar_e($item['texto']) ?></span>

                            <?php if (!empty($item['urgente'])): ?>
                                <span class="sm-sidebar__urgent-label">Urgente</span>
                            <?php endif; ?>

                            <span class="sm-sidebar__active-mark" aria-hidden="true"></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="sm-sidebar__empty-search" id="smSidebarEmptySearch" hidden>
            <span aria-hidden="true"><?= sm_sidebar_icono('search') ?></span>
            <strong>Sin resultados</strong>
            <p>No encontramos una opción con ese nombre.</p>
        </div>
    </nav>

    <footer class="sm-sidebar__foot">
        <div class="sm-sidebar__system-state">
            <span class="sm-sidebar__system-icon" aria-hidden="true">
                <?= sm_sidebar_icono('online') ?>
            </span>
            <span class="sm-sidebar__system-copy">
                <strong>Sistema disponible</strong>
                <small>Conexión interna protegida</small>
            </span>
        </div>

        <a
            href="../funciones/logout.php"
            class="sm-sidebar__logout"
            data-tooltip="Cerrar sesión"
            title="Cerrar sesión"
        >
            <span class="sm-sidebar__item-icon" aria-hidden="true">
                <?= sm_sidebar_icono('logout') ?>
            </span>
            <span class="sm-sidebar__item-text">Cerrar sesión</span>
            <span class="sm-sidebar__logout-arrow" aria-hidden="true">→</span>
        </a>
    </footer>
</aside>

<div class="sm-sidebar__overlay" id="smSidebarOverlay" hidden></div>

<style>
:root {
    --sm-sidebar-width: 304px;
    --sm-sidebar-mini: 88px;
    --sm-topbar-height: 68px;

    --sm-sidebar-navy-1000: #07182b;
    --sm-sidebar-navy-950: #0a1f37;
    --sm-sidebar-navy-900: #0d2947;
    --sm-sidebar-navy-850: #113452;
    --sm-sidebar-cyan: #35d3c3;
    --sm-sidebar-cyan-bright: #60eadc;
    --sm-sidebar-cyan-soft: rgba(53, 211, 195, .13);
    --sm-sidebar-text: #f4f8fb;
    --sm-sidebar-text-soft: #d6e3ec;
    --sm-sidebar-muted: #8fa7b8;
    --sm-sidebar-line: rgba(255, 255, 255, .085);
    --sm-sidebar-hover: rgba(255, 255, 255, .065);
    --sm-sidebar-active: rgba(53, 211, 195, .14);
    --sm-sidebar-danger: #ff7f78;
    --sm-sidebar-danger-soft: rgba(255, 101, 94, .13);
    --sm-sidebar-shadow: 18px 0 52px rgba(4, 18, 32, .20);
    --sm-sidebar-ease: cubic-bezier(.22, 1, .36, 1);
}

*,
*::before,
*::after {
    box-sizing: border-box;
}

/*
 * Durante la restauración inicial se desactivan únicamente las transiciones
 * del sidebar. Así no aparece primero arriba y después salta a su posición.
 */
html.sm-sidebar-restoring body,
html.sm-sidebar-restoring .sm-sidebar,
html.sm-sidebar-restoring .sm-sidebar *,
html.sm-sidebar-restoring .sm-sidebar__overlay {
    transition: none !important;
    animation-duration: .01ms !important;
}

body {
    margin: 0;
    padding-left: var(--sm-sidebar-width);
    transition: padding-left .36s var(--sm-sidebar-ease);
}

.sm-sidebar button,
.sm-sidebar input,
.sm-sidebar a {
    font: inherit;
}

.sm-sidebar svg {
    width: 1em;
    height: 1em;
    display: block;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.sm-sidebar__sr-only {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}

.sm-sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 1000;
    width: var(--sm-sidebar-width);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    isolation: isolate;
    overflow: hidden;
    color: var(--sm-sidebar-text);
    background:
        radial-gradient(circle at 12% 2%, rgba(53, 211, 195, .13), transparent 26%),
        radial-gradient(circle at 100% 72%, rgba(51, 122, 193, .12), transparent 30%),
        linear-gradient(168deg, var(--sm-sidebar-navy-900) 0%, var(--sm-sidebar-navy-950) 46%, var(--sm-sidebar-navy-1000) 100%);
    border-right: 1px solid rgba(157, 201, 231, .11);
    box-shadow: var(--sm-sidebar-shadow);
    font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
    transition:
        width .36s var(--sm-sidebar-ease),
        transform .36s var(--sm-sidebar-ease),
        box-shadow .25s ease;
}

.sm-sidebar::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: -2;
    pointer-events: none;
    opacity: .18;
    background-image:
        linear-gradient(rgba(255, 255, 255, .025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, .025) 1px, transparent 1px);
    background-size: 34px 34px;
    mask-image: linear-gradient(to bottom, #000, transparent 82%);
}

.sm-sidebar::after {
    content: "";
    position: absolute;
    inset: auto 17px 0;
    z-index: -1;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(53, 211, 195, .35), transparent);
}

.sm-sidebar__ambient {
    position: absolute;
    z-index: -1;
    pointer-events: none;
    border-radius: 999px;
    filter: blur(1px);
}

.sm-sidebar__ambient--one {
    width: 170px;
    height: 170px;
    top: -100px;
    right: -70px;
    border: 1px solid rgba(96, 234, 220, .10);
    box-shadow:
        0 0 0 30px rgba(96, 234, 220, .025),
        0 0 0 64px rgba(96, 234, 220, .018);
}

.sm-sidebar__ambient--two {
    width: 110px;
    height: 110px;
    left: -67px;
    bottom: 18%;
    transform: rotate(35deg);
    border: 1px solid rgba(255, 255, 255, .065);
    border-radius: 26px;
}

.sm-sidebar__head {
    min-height: 88px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 16px 14px;
    border-bottom: 1px solid var(--sm-sidebar-line);
}

.sm-sidebar__brand {
    min-width: 0;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 12px;
    color: inherit;
    text-decoration: none;
    border-radius: 14px;
    outline: none;
}

.sm-sidebar__brand:focus-visible {
    box-shadow: 0 0 0 3px rgba(53, 211, 195, .20);
}

.sm-sidebar__logo {
    position: relative;
    flex: 0 0 48px;
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, .34);
    border-radius: 15px;
    color: #062523;
    background:
        linear-gradient(145deg, rgba(255, 255, 255, .34), transparent 45%),
        linear-gradient(135deg, var(--sm-sidebar-cyan-bright), var(--sm-sidebar-cyan));
    box-shadow:
        0 13px 25px rgba(0, 0, 0, .20),
        inset 0 1px 0 rgba(255, 255, 255, .50);
}

.sm-sidebar__logo::after {
    content: "";
    position: absolute;
    width: 58px;
    height: 18px;
    top: -9px;
    left: -16px;
    transform: rotate(-28deg);
    background: rgba(255, 255, 255, .24);
}

.sm-sidebar__logo svg {
    position: relative;
    z-index: 1;
    width: 27px;
    height: 27px;
    stroke-width: 2.1;
}

.sm-sidebar__brand-copy {
    min-width: 0;
    line-height: 1.15;
}

.sm-sidebar__brand-copy strong {
    display: block;
    overflow: hidden;
    color: #fff;
    font-size: 15px;
    font-weight: 850;
    letter-spacing: -.02em;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sm-sidebar__brand-copy small {
    display: block;
    margin-top: 5px;
    overflow: hidden;
    color: var(--sm-sidebar-muted);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .045em;
    text-overflow: ellipsis;
    text-transform: uppercase;
    white-space: nowrap;
}

.sm-sidebar__icon-button {
    flex: 0 0 34px;
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, .09);
    border-radius: 11px;
    color: var(--sm-sidebar-text-soft);
    background: rgba(255, 255, 255, .055);
    cursor: pointer;
    outline: none;
    transition:
        color .2s ease,
        background .2s ease,
        border-color .2s ease,
        transform .22s var(--sm-sidebar-ease),
        box-shadow .2s ease;
}

.sm-sidebar__icon-button svg {
    width: 19px;
    height: 19px;
}

.sm-sidebar__icon-button:hover {
    color: #fff;
    border-color: rgba(96, 234, 220, .22);
    background: rgba(53, 211, 195, .12);
    transform: translateY(-1px);
}

.sm-sidebar__icon-button:focus-visible {
    box-shadow: 0 0 0 3px rgba(53, 211, 195, .18);
}

.sm-sidebar__mobile-close {
    display: none;
}

.sm-sidebar__profile {
    min-height: 80px;
    margin: 14px 14px 11px;
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, .085);
    border-radius: 17px;
    background:
        linear-gradient(135deg, rgba(255, 255, 255, .075), rgba(255, 255, 255, .025));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .035);
}

.sm-sidebar__profile::after {
    content: "";
    position: absolute;
    width: 82px;
    height: 82px;
    top: -52px;
    right: -32px;
    border-radius: 50%;
    border: 1px solid rgba(53, 211, 195, .14);
    box-shadow: 0 0 0 18px rgba(53, 211, 195, .025);
}

.sm-sidebar__avatar {
    position: relative;
    z-index: 1;
    flex: 0 0 46px;
    width: 46px;
    height: 46px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(96, 234, 220, .22);
    border-radius: 14px;
    color: var(--sm-sidebar-cyan-bright);
    background: rgba(53, 211, 195, .10);
    font-size: 13px;
    font-weight: 900;
    letter-spacing: .04em;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .06);
}

.sm-sidebar__avatar-status {
    position: absolute;
    right: -2px;
    bottom: -2px;
    width: 12px;
    height: 12px;
    border: 3px solid var(--sm-sidebar-navy-900);
    border-radius: 50%;
    background: #45dfa0;
    box-shadow: 0 0 0 2px rgba(69, 223, 160, .10);
}

.sm-sidebar__profile-copy {
    min-width: 0;
    flex: 1;
    position: relative;
    z-index: 1;
}

.sm-sidebar__profile-copy > small {
    display: block;
    margin-bottom: 3px;
    color: var(--sm-sidebar-cyan);
    font-size: 9px;
    font-weight: 850;
    letter-spacing: .11em;
    text-transform: uppercase;
}

.sm-sidebar__profile-copy > strong {
    display: block;
    overflow: hidden;
    color: #fff;
    font-size: 13.5px;
    font-weight: 800;
    line-height: 1.3;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sm-sidebar__profile-copy > span {
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--sm-sidebar-muted);
    font-size: 10.5px;
    font-weight: 650;
}

.sm-sidebar__profile-copy i {
    width: 13px;
    height: 13px;
    display: inline-grid;
    place-items: center;
    color: #45dfa0;
    font-style: normal;
}

.sm-sidebar__profile-copy i svg {
    width: 13px;
    height: 13px;
    stroke-width: 2;
}

.sm-sidebar__search {
    min-height: 44px;
    margin: 0 14px 13px;
    display: flex;
    align-items: center;
    gap: 8px;
    position: relative;
    border: 1px solid rgba(255, 255, 255, .09);
    border-radius: 13px;
    background: rgba(2, 14, 27, .24);
    transition:
        border-color .2s ease,
        background .2s ease,
        box-shadow .2s ease,
        transform .2s ease;
}

.sm-sidebar__search:focus-within {
    border-color: rgba(96, 234, 220, .42);
    background: rgba(4, 22, 38, .50);
    box-shadow: 0 0 0 4px rgba(53, 211, 195, .08);
    transform: translateY(-1px);
}

.sm-sidebar__search-icon {
    flex: 0 0 18px;
    margin-left: 12px;
    color: var(--sm-sidebar-muted);
}

.sm-sidebar__search-icon svg {
    width: 18px;
    height: 18px;
}

.sm-sidebar__search input {
    width: 100%;
    min-width: 0;
    height: 42px;
    padding: 0;
    border: 0;
    outline: 0;
    color: #fff;
    background: transparent;
    font-size: 12.5px;
    font-weight: 650;
}

.sm-sidebar__search input::placeholder {
    color: #7892a5;
    opacity: 1;
}

.sm-sidebar__search kbd {
    min-width: 23px;
    height: 23px;
    margin-right: 9px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, .10);
    border-radius: 6px;
    color: #7892a5;
    background: rgba(255, 255, 255, .045);
    font-family: inherit;
    font-size: 10px;
    font-weight: 800;
}

.sm-sidebar__search-clear {
    flex: 0 0 27px;
    width: 27px;
    height: 27px;
    margin-right: 7px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 8px;
    color: var(--sm-sidebar-muted);
    background: rgba(255, 255, 255, .055);
    cursor: pointer;
}

.sm-sidebar__search-clear:hover,
.sm-sidebar__search-clear:focus-visible {
    color: #fff;
    background: rgba(255, 255, 255, .10);
    outline: none;
}

.sm-sidebar__search-clear svg {
    width: 15px;
    height: 15px;
}

.sm-sidebar__nav-heading {
    min-height: 26px;
    padding: 0 19px 7px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    color: #6f899d;
}

.sm-sidebar__nav-heading > span {
    font-size: 9.5px;
    font-weight: 900;
    letter-spacing: .14em;
    text-transform: uppercase;
}

.sm-sidebar__nav-heading > small {
    font-size: 9.5px;
    font-weight: 750;
}

.sm-sidebar__nav {
    flex: 1;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    padding: 0 10px 17px;
    overscroll-behavior: contain;
    scrollbar-gutter: stable;
    scrollbar-color: rgba(143, 167, 184, .34) transparent;
    scrollbar-width: thin;
}

.sm-sidebar__nav::-webkit-scrollbar {
    width: 6px;
}

.sm-sidebar__nav::-webkit-scrollbar-track {
    background: transparent;
}

.sm-sidebar__nav::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: rgba(143, 167, 184, .25);
}

.sm-sidebar__nav::-webkit-scrollbar-thumb:hover {
    background: rgba(143, 167, 184, .42);
}

.sm-sidebar__group {
    margin-bottom: 5px;
}

.sm-sidebar__group[hidden],
.sm-sidebar__item[hidden] {
    display: none !important;
}

.sm-sidebar__group-button {
    width: 100%;
    min-height: 46px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 8px;
    position: relative;
    border: 0;
    border-radius: 13px;
    color: var(--sm-sidebar-muted);
    background: transparent;
    cursor: pointer;
    outline: none;
    text-align: left;
    transition:
        color .2s ease,
        background .2s ease,
        transform .2s var(--sm-sidebar-ease),
        box-shadow .2s ease;
}

.sm-sidebar__group-button:hover {
    color: #eaf5fb;
    background: rgba(255, 255, 255, .045);
}

.sm-sidebar__group-button:focus-visible {
    color: #fff;
    box-shadow: inset 0 0 0 1px rgba(96, 234, 220, .30), 0 0 0 3px rgba(53, 211, 195, .08);
}

.sm-sidebar__group.is-current > .sm-sidebar__group-button {
    color: #d7f8f3;
}

.sm-sidebar__group-icon {
    flex: 0 0 32px;
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border: 1px solid transparent;
    border-radius: 10px;
    color: #89a2b4;
    background: rgba(255, 255, 255, .032);
    transition:
        color .2s ease,
        border-color .2s ease,
        background .2s ease,
        transform .2s var(--sm-sidebar-ease);
}

.sm-sidebar__group-icon svg {
    width: 17px;
    height: 17px;
}

.sm-sidebar__group-button:hover .sm-sidebar__group-icon,
.sm-sidebar__group.is-open > .sm-sidebar__group-button .sm-sidebar__group-icon {
    color: var(--sm-sidebar-cyan-bright);
    border-color: rgba(53, 211, 195, .10);
    background: rgba(53, 211, 195, .08);
}

.sm-sidebar__group-button:hover .sm-sidebar__group-icon {
    transform: translateY(-1px);
}

.sm-sidebar__group-title {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    font-size: 11px;
    font-weight: 850;
    letter-spacing: .075em;
    text-overflow: ellipsis;
    text-transform: uppercase;
    white-space: nowrap;
}

.sm-sidebar__group-count {
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    display: inline-grid;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, .07);
    border-radius: 999px;
    color: #7892a5;
    background: rgba(255, 255, 255, .035);
    font-size: 9px;
    font-weight: 850;
}

.sm-sidebar__chevron {
    flex: 0 0 18px;
    width: 18px;
    height: 18px;
    display: grid;
    place-items: center;
    color: #688398;
    transition: transform .28s var(--sm-sidebar-ease), color .2s ease;
}

.sm-sidebar__chevron svg {
    width: 16px;
    height: 16px;
    stroke-width: 2.1;
}

.sm-sidebar__group.is-open > .sm-sidebar__group-button .sm-sidebar__chevron {
    color: var(--sm-sidebar-cyan);
    transform: rotate(180deg);
}

.sm-sidebar__items {
    max-height: 0;
    padding-left: 14px;
    position: relative;
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-4px);
    transition:
        max-height .34s var(--sm-sidebar-ease),
        opacity .22s ease,
        transform .28s var(--sm-sidebar-ease),
        visibility .22s ease;
}

.sm-sidebar__group.is-open .sm-sidebar__items {
    max-height: 360px;
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.sm-sidebar__items::after {
    content: "";
    position: absolute;
    top: 3px;
    bottom: 7px;
    left: 24px;
    width: 1px;
    border-radius: 999px;
    background: linear-gradient(to bottom, rgba(53, 211, 195, .14), rgba(255, 255, 255, .04));
}

.sm-sidebar__item,
.sm-sidebar__logout {
    min-height: 46px;
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    border-radius: 13px;
    color: var(--sm-sidebar-text-soft);
    text-decoration: none;
    outline: none;
    -webkit-tap-highlight-color: transparent;
    transition:
        color .2s ease,
        background .2s ease,
        border-color .2s ease,
        transform .22s var(--sm-sidebar-ease),
        box-shadow .2s ease;
}

.sm-sidebar__item {
    margin: 3px 0;
    padding: 7px 9px 7px 10px;
    border: 1px solid transparent;
}

.sm-sidebar__item:hover {
    color: #fff;
    background: var(--sm-sidebar-hover);
    transform: translateX(2px);
}

.sm-sidebar__item:focus-visible,
.sm-sidebar__logout:focus-visible {
    color: #fff;
    box-shadow: 0 0 0 3px rgba(53, 211, 195, .12);
}

.sm-sidebar__item-icon {
    flex: 0 0 31px;
    width: 31px;
    height: 31px;
    display: grid;
    place-items: center;
    position: relative;
    z-index: 1;
    border: 1px solid rgba(255, 255, 255, .055);
    border-radius: 10px;
    color: #91aabc;
    background: rgba(255, 255, 255, .035);
    transition:
        color .2s ease,
        border-color .2s ease,
        background .2s ease,
        transform .2s var(--sm-sidebar-ease),
        box-shadow .2s ease;
}

.sm-sidebar__item-icon svg {
    width: 17px;
    height: 17px;
}

.sm-sidebar__item:hover .sm-sidebar__item-icon {
    color: #dffbf7;
    border-color: rgba(53, 211, 195, .11);
    background: rgba(53, 211, 195, .08);
}

.sm-sidebar__item-text {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    font-size: 13px;
    font-weight: 720;
    letter-spacing: -.006em;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sm-sidebar__item.is-active {
    color: #fff;
    border-color: rgba(96, 234, 220, .16);
    background:
        linear-gradient(90deg, rgba(53, 211, 195, .18), rgba(53, 211, 195, .075));
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, .035),
        0 9px 20px rgba(0, 0, 0, .09);
}

.sm-sidebar__item.is-active::before {
    content: "";
    position: absolute;
    top: 10px;
    bottom: 10px;
    left: -15px;
    width: 3px;
    border-radius: 999px;
    background: linear-gradient(to bottom, var(--sm-sidebar-cyan-bright), var(--sm-sidebar-cyan));
    box-shadow: 0 0 16px rgba(53, 211, 195, .45);
}

.sm-sidebar__item.is-active .sm-sidebar__item-icon {
    color: #062724;
    border-color: rgba(255, 255, 255, .20);
    background: linear-gradient(135deg, var(--sm-sidebar-cyan-bright), var(--sm-sidebar-cyan));
    box-shadow: 0 8px 16px rgba(2, 18, 29, .22);
}

.sm-sidebar__active-mark {
    flex: 0 0 6px;
    width: 6px;
    height: 6px;
    display: block;
    border-radius: 50%;
    background: transparent;
}

.sm-sidebar__item.is-active .sm-sidebar__active-mark {
    background: var(--sm-sidebar-cyan-bright);
    box-shadow: 0 0 0 4px rgba(53, 211, 195, .10);
}

.sm-sidebar__item.is-urgent:not(.is-active) .sm-sidebar__item-icon {
    color: var(--sm-sidebar-danger);
    border-color: rgba(255, 127, 120, .12);
    background: var(--sm-sidebar-danger-soft);
}

.sm-sidebar__item.is-urgent.is-active .sm-sidebar__item-icon {
    color: #48100e;
    background: linear-gradient(135deg, #ffaaa5, #ff7f78);
}

.sm-sidebar__urgent-label {
    min-width: 46px;
    height: 20px;
    padding: 0 6px;
    display: inline-grid;
    place-items: center;
    border: 1px solid rgba(255, 127, 120, .14);
    border-radius: 999px;
    color: #ffaaa5;
    background: var(--sm-sidebar-danger-soft);
    font-size: 8px;
    font-weight: 900;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.sm-sidebar__empty-search {
    min-height: 170px;
    margin: 11px 5px 0;
    padding: 22px 14px;
    display: grid;
    place-items: center;
    align-content: center;
    border: 1px dashed rgba(255, 255, 255, .09);
    border-radius: 16px;
    color: var(--sm-sidebar-muted);
    text-align: center;
}

.sm-sidebar__empty-search > span {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    margin-bottom: 10px;
    border-radius: 13px;
    color: var(--sm-sidebar-cyan);
    background: rgba(53, 211, 195, .09);
}

.sm-sidebar__empty-search svg {
    width: 21px;
    height: 21px;
}

.sm-sidebar__empty-search strong {
    color: #e9f2f7;
    font-size: 13px;
    font-weight: 850;
}

.sm-sidebar__empty-search p {
    margin: 5px 0 0;
    font-size: 11px;
    line-height: 1.45;
}

.sm-sidebar__foot {
    padding: 11px 12px 13px;
    border-top: 1px solid var(--sm-sidebar-line);
    background: linear-gradient(to top, rgba(4, 16, 29, .34), transparent);
}

.sm-sidebar__system-state {
    min-height: 47px;
    margin-bottom: 8px;
    padding: 8px 9px;
    display: flex;
    align-items: center;
    gap: 9px;
    border: 1px solid rgba(255, 255, 255, .06);
    border-radius: 12px;
    background: rgba(255, 255, 255, .027);
}

.sm-sidebar__system-icon {
    flex: 0 0 29px;
    width: 29px;
    height: 29px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    color: #45dfa0;
    background: rgba(69, 223, 160, .09);
}

.sm-sidebar__system-icon svg {
    width: 16px;
    height: 16px;
}

.sm-sidebar__system-copy {
    min-width: 0;
}

.sm-sidebar__system-copy strong,
.sm-sidebar__system-copy small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sm-sidebar__system-copy strong {
    color: #dcebe5;
    font-size: 10.5px;
    font-weight: 800;
}

.sm-sidebar__system-copy small {
    margin-top: 3px;
    color: #6f899d;
    font-size: 9px;
    font-weight: 650;
}

.sm-sidebar__logout {
    min-height: 47px;
    padding: 7px 10px;
    border: 1px solid rgba(255, 127, 120, .10);
    color: #ffc2bf;
    background: rgba(255, 101, 94, .055);
}

.sm-sidebar__logout:hover {
    color: #fff;
    border-color: rgba(255, 127, 120, .18);
    background: rgba(255, 101, 94, .12);
    transform: translateY(-1px);
}

.sm-sidebar__logout .sm-sidebar__item-icon {
    color: #ffaaa5;
    border-color: rgba(255, 127, 120, .12);
    background: rgba(255, 101, 94, .09);
}

.sm-sidebar__logout-arrow {
    color: #ffaaa5;
    font-size: 15px;
    font-weight: 900;
    transition: transform .2s var(--sm-sidebar-ease);
}

.sm-sidebar__logout:hover .sm-sidebar__logout-arrow {
    transform: translateX(2px);
}

.sm-sidebar__overlay {
    display: none;
}

.sm-sidebar__overlay[hidden] {
    display: none !important;
}

/*
|--------------------------------------------------------------------------
| Modo compacto de escritorio
|--------------------------------------------------------------------------
*/

body.sm-sidebar-collapsed {
    padding-left: var(--sm-sidebar-mini);
}

body.sm-sidebar-collapsed .sm-sidebar {
    width: var(--sm-sidebar-mini);
}

body.sm-sidebar-collapsed .sm-sidebar__head {
    padding-inline: 14px;
    justify-content: center;
}

body.sm-sidebar-collapsed .sm-sidebar__brand {
    flex: 0 0 48px;
}

body.sm-sidebar-collapsed .sm-sidebar__brand-copy,
body.sm-sidebar-collapsed .sm-sidebar__profile-copy,
body.sm-sidebar-collapsed .sm-sidebar__search,
body.sm-sidebar-collapsed .sm-sidebar__nav-heading,
body.sm-sidebar-collapsed .sm-sidebar__group-title,
body.sm-sidebar-collapsed .sm-sidebar__group-count,
body.sm-sidebar-collapsed .sm-sidebar__chevron,
body.sm-sidebar-collapsed .sm-sidebar__item-text,
body.sm-sidebar-collapsed .sm-sidebar__urgent-label,
body.sm-sidebar-collapsed .sm-sidebar__active-mark,
body.sm-sidebar-collapsed .sm-sidebar__system-copy,
body.sm-sidebar-collapsed .sm-sidebar__logout-arrow {
    display: none;
}

body.sm-sidebar-collapsed .sm-sidebar__collapse {
    position: absolute;
    top: 70px;
    right: 7px;
    z-index: 4;
    width: 26px;
    height: 26px;
    border-radius: 9px;
    color: var(--sm-sidebar-cyan-bright);
    background: var(--sm-sidebar-navy-850);
    box-shadow: 0 8px 18px rgba(0, 0, 0, .18);
}

body.sm-sidebar-collapsed .sm-sidebar__collapse svg {
    width: 16px;
    height: 16px;
    transform: rotate(180deg);
}

body.sm-sidebar-collapsed .sm-sidebar__profile {
    min-height: 64px;
    margin: 14px 10px 10px;
    padding: 9px;
    justify-content: center;
}

body.sm-sidebar-collapsed .sm-sidebar__avatar {
    flex-basis: 43px;
    width: 43px;
    height: 43px;
}

body.sm-sidebar-collapsed .sm-sidebar__nav {
    padding-inline: 9px;
}

body.sm-sidebar-collapsed .sm-sidebar__group {
    margin-bottom: 7px;
}

body.sm-sidebar-collapsed .sm-sidebar__group-button {
    justify-content: center;
    padding-inline: 7px;
}

body.sm-sidebar-collapsed .sm-sidebar__group-icon {
    flex-basis: 38px;
    width: 38px;
    height: 38px;
    border-radius: 12px;
}

body.sm-sidebar-collapsed .sm-sidebar__items {
    max-height: 520px;
    padding-left: 0;
    opacity: 1;
    visibility: visible;
    transform: none;
}

body.sm-sidebar-collapsed .sm-sidebar__items::after {
    display: none;
}

body.sm-sidebar-collapsed .sm-sidebar__item,
body.sm-sidebar-collapsed .sm-sidebar__logout {
    justify-content: center;
    padding-inline: 7px;
}

body.sm-sidebar-collapsed .sm-sidebar__item-icon {
    flex-basis: 38px;
    width: 38px;
    height: 38px;
    border-radius: 12px;
}

body.sm-sidebar-collapsed .sm-sidebar__item.is-active::before {
    top: 12px;
    bottom: 12px;
    left: -8px;
}

body.sm-sidebar-collapsed .sm-sidebar__system-state {
    min-height: 44px;
    padding: 7px;
    justify-content: center;
}

body.sm-sidebar-collapsed .sm-sidebar__system-icon {
    flex-basis: 30px;
}

/* Tooltips del modo compacto */
@media (min-width: 901px) {
    body.sm-sidebar-collapsed .sm-sidebar [data-tooltip] {
        position: relative;
    }

    body.sm-sidebar-collapsed .sm-sidebar [data-tooltip]::after {
        content: attr(data-tooltip);
        position: absolute;
        top: 50%;
        left: calc(100% + 13px);
        z-index: 30;
        min-width: max-content;
        max-width: 230px;
        padding: 8px 10px;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        transform: translate(6px, -50%);
        border: 1px solid rgba(255, 255, 255, .10);
        border-radius: 9px;
        color: #f5f9fc;
        background: #0b2139;
        box-shadow: 0 12px 30px rgba(0, 0, 0, .24);
        font-size: 11px;
        font-weight: 750;
        line-height: 1.3;
        white-space: nowrap;
        transition:
            opacity .16s ease,
            transform .2s var(--sm-sidebar-ease),
            visibility .16s ease;
    }

    body.sm-sidebar-collapsed .sm-sidebar [data-tooltip]::before {
        content: "";
        position: absolute;
        top: 50%;
        left: calc(100% + 8px);
        z-index: 31;
        width: 8px;
        height: 8px;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        transform: translate(6px, -50%) rotate(45deg);
        border-left: 1px solid rgba(255, 255, 255, .10);
        border-bottom: 1px solid rgba(255, 255, 255, .10);
        background: #0b2139;
        transition:
            opacity .16s ease,
            transform .2s var(--sm-sidebar-ease),
            visibility .16s ease;
    }

    body.sm-sidebar-collapsed .sm-sidebar [data-tooltip]:hover::after,
    body.sm-sidebar-collapsed .sm-sidebar [data-tooltip]:hover::before,
    body.sm-sidebar-collapsed .sm-sidebar [data-tooltip]:focus-visible::after,
    body.sm-sidebar-collapsed .sm-sidebar [data-tooltip]:focus-visible::before {
        opacity: 1;
        visibility: visible;
        transform: translate(0, -50%);
    }

    body.sm-sidebar-collapsed .sm-sidebar [data-tooltip]:hover::before,
    body.sm-sidebar-collapsed .sm-sidebar [data-tooltip]:focus-visible::before {
        transform: translate(0, -50%) rotate(45deg);
    }

    body.sm-sidebar-collapsed .sm-sidebar__brand::after,
    body.sm-sidebar-collapsed .sm-sidebar__brand::before,
    body.sm-sidebar-collapsed .sm-sidebar__collapse::after,
    body.sm-sidebar-collapsed .sm-sidebar__collapse::before {
        display: none;
    }
}

/*
|--------------------------------------------------------------------------
| Tablets horizontales y pantallas medianas
|--------------------------------------------------------------------------
*/

@media (min-width: 901px) and (max-width: 1180px) {
    :root {
        --sm-sidebar-width: 278px;
        --sm-sidebar-mini: 84px;
    }

    .sm-sidebar__head {
        padding-inline: 13px;
    }

    .sm-sidebar__profile {
        margin-inline: 11px;
    }

    .sm-sidebar__search {
        margin-inline: 11px;
    }

    .sm-sidebar__nav {
        padding-inline: 8px;
    }

    body.sm-sidebar-collapsed .sm-sidebar__collapse {
        right: 6px;
    }
}

/*
|--------------------------------------------------------------------------
| Tablet y móvil
|--------------------------------------------------------------------------
*/

@media (max-width: 900px) {
    body,
    body.sm-sidebar-collapsed {
        padding-left: 0;
    }

    .sm-sidebar,
    body.sm-sidebar-collapsed .sm-sidebar {
        width: min(90vw, 330px);
        transform: translateX(-105%);
        box-shadow: 24px 0 70px rgba(3, 14, 26, .34);
    }

    body.sm-sidebar-mobile-open .sm-sidebar,
    body.sm-sidebar-mobile-open.sm-sidebar-collapsed .sm-sidebar {
        transform: translateX(0);
    }

    .sm-sidebar__collapse {
        display: none;
    }

    .sm-sidebar__group-button,
    .sm-sidebar__item,
    .sm-sidebar__logout {
        min-height: 48px;
    }

    .sm-sidebar__group-button,
    .sm-sidebar__item {
        touch-action: manipulation;
    }

    .sm-sidebar__mobile-close {
        display: grid;
    }

    body.sm-sidebar-collapsed .sm-sidebar__head {
        padding: 15px 16px 14px;
        justify-content: initial;
    }

    body.sm-sidebar-collapsed .sm-sidebar__brand {
        flex: 1;
    }

    body.sm-sidebar-collapsed .sm-sidebar__brand-copy,
    body.sm-sidebar-collapsed .sm-sidebar__profile-copy,
    body.sm-sidebar-collapsed .sm-sidebar__search,
    body.sm-sidebar-collapsed .sm-sidebar__nav-heading,
    body.sm-sidebar-collapsed .sm-sidebar__group-title,
    body.sm-sidebar-collapsed .sm-sidebar__group-count,
    body.sm-sidebar-collapsed .sm-sidebar__chevron,
    body.sm-sidebar-collapsed .sm-sidebar__item-text,
    body.sm-sidebar-collapsed .sm-sidebar__urgent-label,
    body.sm-sidebar-collapsed .sm-sidebar__active-mark,
    body.sm-sidebar-collapsed .sm-sidebar__system-copy,
    body.sm-sidebar-collapsed .sm-sidebar__logout-arrow {
        display: initial;
    }

    body.sm-sidebar-collapsed .sm-sidebar__profile {
        min-height: 80px;
        margin: 14px 14px 11px;
        padding: 12px;
        justify-content: initial;
    }

    body.sm-sidebar-collapsed .sm-sidebar__avatar {
        flex-basis: 46px;
        width: 46px;
        height: 46px;
    }

    body.sm-sidebar-collapsed .sm-sidebar__nav {
        padding: 0 10px 17px;
    }

    body.sm-sidebar-collapsed .sm-sidebar__group-button {
        justify-content: initial;
        padding: 6px 8px;
    }

    body.sm-sidebar-collapsed .sm-sidebar__group-icon {
        flex-basis: 32px;
        width: 32px;
        height: 32px;
    }

    body.sm-sidebar-collapsed .sm-sidebar__items {
        max-height: 0;
        padding-left: 14px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-4px);
    }

    body.sm-sidebar-collapsed .sm-sidebar__group.is-open .sm-sidebar__items {
        max-height: 360px;
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    body.sm-sidebar-collapsed .sm-sidebar__items::after {
        display: block;
    }

    body.sm-sidebar-collapsed .sm-sidebar__item,
    body.sm-sidebar-collapsed .sm-sidebar__logout {
        justify-content: initial;
        padding-inline: 10px;
    }

    body.sm-sidebar-collapsed .sm-sidebar__item-icon {
        flex-basis: 31px;
        width: 31px;
        height: 31px;
    }

    body.sm-sidebar-collapsed .sm-sidebar__system-state {
        min-height: 47px;
        padding: 8px 9px;
        justify-content: initial;
    }

    .sm-sidebar__overlay {
        position: fixed;
        inset: 0;
        z-index: 999;
        display: block;
        opacity: 0;
        background: rgba(3, 13, 24, .66);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        transition: opacity .25s ease;
    }

    body.sm-sidebar-mobile-open .sm-sidebar__overlay {
        opacity: 1;
    }

    body.sm-sidebar-mobile-open {
        overflow: hidden;
        touch-action: none;
    }
}

@media (max-width: 420px) {
    .sm-sidebar,
    body.sm-sidebar-collapsed .sm-sidebar {
        width: min(94vw, 322px);
    }

    .sm-sidebar__head {
        min-height: 82px;
        padding-inline: 13px;
    }

    .sm-sidebar__logo {
        flex-basis: 44px;
        width: 44px;
        height: 44px;
    }

    .sm-sidebar__brand-copy strong {
        font-size: 14px;
    }

    .sm-sidebar__profile {
        margin-inline: 11px;
    }

    .sm-sidebar__search {
        margin-inline: 11px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .sm-sidebar *,
    .sm-sidebar *::before,
    .sm-sidebar *::after,
    .sm-sidebar__overlay,
    body {
        scroll-behavior: auto !important;
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .01ms !important;
    }
}
</style>

<script>
(function () {
    'use strict';

    var html = document.documentElement;
    var body = document.body;
    var sidebar = document.getElementById('smSidebar');
    var collapseButton = document.getElementById('smSidebarCollapse');
    var mobileCloseButton = document.getElementById('smSidebarMobileClose');
    var overlay = document.getElementById('smSidebarOverlay');
    var searchInput = document.getElementById('smSidebarSearch');
    var searchClear = document.getElementById('smSidebarSearchClear');
    var emptySearch = document.getElementById('smSidebarEmptySearch');
    var nav = document.getElementById('smSidebarNav');
    var groups = Array.prototype.slice.call(
        document.querySelectorAll('[data-sidebar-group]')
    );
    var groupButtons = Array.prototype.slice.call(
        document.querySelectorAll('[data-sidebar-group-button]')
    );
    var menuItems = Array.prototype.slice.call(
        document.querySelectorAll('[data-sidebar-item]')
    );
    var activeItem = document.querySelector('.sm-sidebar__item.is-active');

    var currentView = <?= json_encode(
        $smSidebarVista,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;
    var roleKey = '<?= sm_sidebar_e(strtolower($smSidebarRol)) ?>';

    var storageKey = 'sm_sidebar_collapsed_1_1';
    var openGroupsKey = 'sm_sidebar_open_groups_' + roleKey + '_1_2';
    var scrollKey = 'sm_sidebar_scroll_' + roleKey + '_1_3';
    var navigationAnchorKey = 'sm_sidebar_navigation_anchor_' + roleKey + '_1_0';

    var lastFocusedElement = null;
    var searchWasActive = false;
    var scrollSaveFrame = null;
    var restoreTimers = [];
    var restoreFinished = false;

    function isDesktop() {
        return window.innerWidth > 900;
    }

    function normalizeText(value) {
        return String(value || '')
            .toLocaleLowerCase('es-MX')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function safeStorageGet(storage, key) {
        try {
            return storage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function safeStorageSet(storage, key, value) {
        try {
            storage.setItem(key, value);
            return true;
        } catch (error) {
            return false;
        }
    }

    function safeStorageRemove(storage, key) {
        try {
            storage.removeItem(key);
        } catch (error) {
            /* El menú continúa funcionando sin almacenamiento. */
        }
    }

    function maxNavScroll() {
        if (!nav) {
            return 0;
        }

        return Math.max(0, nav.scrollHeight - nav.clientHeight);
    }

    function clampNavScroll(value) {
        var numericValue = Number(value);

        if (!Number.isFinite(numericValue)) {
            return 0;
        }

        return Math.min(Math.max(0, numericValue), maxNavScroll());
    }

    function saveNavScroll() {
        if (!nav) {
            return;
        }

        safeStorageSet(
            sessionStorage,
            scrollKey,
            String(Math.round(nav.scrollTop))
        );
    }

    function scheduleNavScrollSave() {
        if (scrollSaveFrame !== null) {
            return;
        }

        scrollSaveFrame = window.requestAnimationFrame(function () {
            scrollSaveFrame = null;
            saveNavScroll();
        });
    }

    function readSavedNavScroll() {
        var value = safeStorageGet(sessionStorage, scrollKey);

        if (value === null || value === '') {
            return null;
        }

        var parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function fileNameFromHref(href) {
        try {
            var url = new URL(String(href || ''), window.location.href);
            var parts = url.pathname.split('/').filter(Boolean);
            return parts.length > 0 ? parts[parts.length - 1] : '';
        } catch (error) {
            return '';
        }
    }

    function saveNavigationAnchor(item) {
        if (!nav || !item) {
            saveNavScroll();
            return;
        }

        var navRect = nav.getBoundingClientRect();
        var itemRect = item.getBoundingClientRect();
        var group = item.closest('[data-sidebar-group]');
        var anchor = {
            view: fileNameFromHref(item.getAttribute('href')),
            groupKey: group
                ? (group.getAttribute('data-group-key') || '')
                : '',
            scrollTop: Math.round(nav.scrollTop),
            viewportOffset: Math.round(itemRect.top - navRect.top),
            createdAt: Date.now()
        };

        safeStorageSet(
            sessionStorage,
            navigationAnchorKey,
            JSON.stringify(anchor)
        );
        saveNavScroll();
    }

    function readNavigationAnchor() {
        var raw = safeStorageGet(sessionStorage, navigationAnchorKey);

        if (!raw) {
            return null;
        }

        try {
            var anchor = JSON.parse(raw);
            var age = Date.now() - Number(anchor.createdAt || 0);
            var validAge = age >= 0 && age <= 120000;
            var validView = String(anchor.view || '') === String(currentView || '');
            var validOffset = Number.isFinite(Number(anchor.viewportOffset));
            var validScroll = Number.isFinite(Number(anchor.scrollTop));

            if (!validAge || !validView || !validOffset || !validScroll) {
                safeStorageRemove(sessionStorage, navigationAnchorKey);
                return null;
            }

            return anchor;
        } catch (error) {
            safeStorageRemove(sessionStorage, navigationAnchorKey);
            return null;
        }
    }

    function revealActiveItemInsideNav() {
        if (!nav || !activeItem) {
            return;
        }

        var navRect = nav.getBoundingClientRect();
        var itemRect = activeItem.getBoundingClientRect();
        var safeSpace = 14;
        var topLimit = navRect.top + safeSpace;
        var bottomLimit = navRect.bottom - safeSpace;

        if (itemRect.top < topLimit) {
            nav.scrollTop = clampNavScroll(
                nav.scrollTop - (topLimit - itemRect.top)
            );
        } else if (itemRect.bottom > bottomLimit) {
            nav.scrollTop = clampNavScroll(
                nav.scrollTop + (itemRect.bottom - bottomLimit)
            );
        }
    }

    function applyRestoredNavPosition(savedScroll, navigationAnchor) {
        if (!nav) {
            return;
        }

        if (savedScroll !== null) {
            nav.scrollTop = clampNavScroll(savedScroll);
        }

        if (navigationAnchor && activeItem) {
            var navRect = nav.getBoundingClientRect();
            var itemRect = activeItem.getBoundingClientRect();
            var currentOffset = itemRect.top - navRect.top;
            var desiredOffset = Number(navigationAnchor.viewportOffset);
            var correction = currentOffset - desiredOffset;

            nav.scrollTop = clampNavScroll(nav.scrollTop + correction);
            return;
        }

        if (savedScroll === null) {
            revealActiveItemInsideNav();
        }
    }

    function clearRestoreTimers() {
        restoreTimers.forEach(function (timer) {
            window.clearTimeout(timer);
        });
        restoreTimers = [];
    }

    function finishInitialRestore() {
        if (restoreFinished) {
            return;
        }

        restoreFinished = true;
        html.classList.remove('sm-sidebar-restoring');
        safeStorageRemove(sessionStorage, navigationAnchorKey);
        saveNavScroll();
    }

    function restoreNavScroll() {
        if (!nav) {
            finishInitialRestore();
            return;
        }

        clearRestoreTimers();

        var savedScroll = readSavedNavScroll();
        var navigationAnchor = readNavigationAnchor();

        /*
         * Se aplica varias veces porque fuentes, topbar y grupos desplegables
         * pueden terminar de medir unos milisegundos después del primer render.
         */
        var apply = function () {
            applyRestoredNavPosition(savedScroll, navigationAnchor);
        };

        apply();

        window.requestAnimationFrame(function () {
            apply();
            window.requestAnimationFrame(apply);
        });

        [70, 180, 360].forEach(function (delay) {
            restoreTimers.push(window.setTimeout(apply, delay));
        });

        restoreTimers.push(window.setTimeout(function () {
            apply();
            finishInitialRestore();
        }, 430));
    }

    function elementOffsetInsideNav(element) {
        if (!nav || !element) {
            return null;
        }

        var navRect = nav.getBoundingClientRect();
        var elementRect = element.getBoundingClientRect();

        return elementRect.top - navRect.top;
    }

    function preserveNavScroll(action, referenceElement) {
        if (!nav) {
            action();
            return;
        }

        var previousScroll = nav.scrollTop;
        var reference = referenceElement || activeItem;
        var previousOffset = elementOffsetInsideNav(reference);

        action();

        var restore = function () {
            if (!nav) {
                return;
            }

            if (reference && previousOffset !== null && reference.offsetParent !== null) {
                var currentOffset = elementOffsetInsideNav(reference);

                if (currentOffset !== null) {
                    nav.scrollTop = clampNavScroll(
                        nav.scrollTop + (currentOffset - previousOffset)
                    );
                    return;
                }
            }

            nav.scrollTop = clampNavScroll(previousScroll);
        };

        window.requestAnimationFrame(restore);
        window.setTimeout(restore, 360);
    }

    function updateCollapseButton() {
        if (!collapseButton) {
            return;
        }

        var collapsed = body.classList.contains('sm-sidebar-collapsed');
        var label = collapsed ? 'Expandir menú' : 'Contraer menú';

        collapseButton.setAttribute('aria-label', label);
        collapseButton.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
        collapseButton.setAttribute('title', label);
        collapseButton.setAttribute('data-tooltip', label);
    }

    function saveCollapsedState() {
        safeStorageSet(
            localStorage,
            storageKey,
            body.classList.contains('sm-sidebar-collapsed') ? '1' : '0'
        );
    }

    function getOpenGroupKeys() {
        return groups
            .filter(function (group) {
                return group.classList.contains('is-open');
            })
            .map(function (group) {
                return group.getAttribute('data-group-key') || '';
            })
            .filter(Boolean);
    }

    function saveOpenGroups() {
        if (searchWasActive) {
            return;
        }

        safeStorageSet(
            localStorage,
            openGroupsKey,
            JSON.stringify(getOpenGroupKeys())
        );
    }

    function setGroupOpen(group, open, persist) {
        if (!group) {
            return;
        }

        var button = group.querySelector('[data-sidebar-group-button]');
        group.classList.toggle('is-open', open);

        if (button) {
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        if (persist !== false) {
            saveOpenGroups();
        }
    }

    function restoreOpenGroups() {
        var storedKeys = [];
        var raw = safeStorageGet(localStorage, openGroupsKey);

        try {
            var parsed = raw ? JSON.parse(raw) : [];
            storedKeys = Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            storedKeys = [];
        }

        groups.forEach(function (group) {
            var key = group.getAttribute('data-group-key') || '';
            var containsCurrentPage = group.classList.contains('is-current');
            var shouldOpen = containsCurrentPage
                || storedKeys.indexOf(key) !== -1;

            setGroupOpen(group, shouldOpen, false);
        });
    }

    function clearSearch(restoreFocus) {
        if (!searchInput) {
            return;
        }

        searchInput.value = '';
        searchWasActive = false;

        menuItems.forEach(function (item) {
            item.hidden = false;
        });

        groups.forEach(function (group) {
            group.hidden = false;
        });

        if (emptySearch) {
            emptySearch.hidden = true;
        }

        if (searchClear) {
            searchClear.hidden = true;
        }

        var previousScroll = nav ? nav.scrollTop : 0;
        restoreOpenGroups();

        if (nav) {
            window.requestAnimationFrame(function () {
                nav.scrollTop = clampNavScroll(previousScroll);
            });
        }

        if (restoreFocus) {
            searchInput.focus({ preventScroll: true });
        }
    }

    function filterMenu() {
        if (!searchInput) {
            return;
        }

        var query = normalizeText(searchInput.value);
        var hasQuery = query.length > 0;
        var visibleItems = 0;

        searchWasActive = hasQuery;

        if (searchClear) {
            searchClear.hidden = !hasQuery;
        }

        groups.forEach(function (group) {
            var items = Array.prototype.slice.call(
                group.querySelectorAll('[data-sidebar-item]')
            );
            var groupVisibleItems = 0;

            items.forEach(function (item) {
                var searchable = normalizeText(
                    item.getAttribute('data-search-text') || item.textContent
                );
                var matches = !hasQuery
                    || searchable.indexOf(query) !== -1;

                item.hidden = !matches;

                if (matches) {
                    groupVisibleItems += 1;
                    visibleItems += 1;
                }
            });

            group.hidden = hasQuery && groupVisibleItems === 0;

            if (hasQuery && groupVisibleItems > 0) {
                setGroupOpen(group, true, false);
            }
        });

        if (!hasQuery) {
            restoreOpenGroups();
        }

        if (emptySearch) {
            emptySearch.hidden = visibleItems > 0 || !hasQuery;
        }
    }

    function focusBestSidebarControl() {
        window.setTimeout(function () {
            if (
                searchInput
                && !body.classList.contains('sm-sidebar-collapsed')
            ) {
                searchInput.focus({ preventScroll: true });
                return;
            }

            if (activeItem) {
                activeItem.focus({ preventScroll: true });
            }
        }, 180);
    }

    function openMobileMenu() {
        if (isDesktop()) {
            return;
        }

        lastFocusedElement = document.activeElement;
        body.classList.add('sm-sidebar-mobile-open');

        if (overlay) {
            overlay.hidden = false;
            window.requestAnimationFrame(function () {
                overlay.style.opacity = '1';
            });
        }

        focusBestSidebarControl();
    }

    function closeMobileMenu(restoreFocus) {
        body.classList.remove('sm-sidebar-mobile-open');

        if (overlay) {
            overlay.style.opacity = '';
            window.setTimeout(function () {
                if (!body.classList.contains('sm-sidebar-mobile-open')) {
                    overlay.hidden = true;
                }
            }, 260);
        }

        if (
            restoreFocus !== false
            && lastFocusedElement
            && typeof lastFocusedElement.focus === 'function'
        ) {
            window.setTimeout(function () {
                lastFocusedElement.focus();
            }, 80);
        }
    }

    function keepFocusInsideSidebar(event) {
        if (
            !body.classList.contains('sm-sidebar-mobile-open')
            || event.key !== 'Tab'
            || !sidebar
        ) {
            return;
        }

        var focusable = Array.prototype.slice.call(sidebar.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), '
            + '[tabindex]:not([tabindex="-1"])'
        )).filter(function (element) {
            return !element.hidden && element.offsetParent !== null;
        });

        if (focusable.length === 0) {
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    /*
     * Restaura el modo compacto antes de medir el scroll. La clase colocada
     * en <html> evita cualquier salto visual durante este proceso.
     */
    if (
        isDesktop()
        && safeStorageGet(localStorage, storageKey) === '1'
    ) {
        body.classList.add('sm-sidebar-collapsed');
    }

    restoreOpenGroups();
    updateCollapseButton();
    restoreNavScroll();

    if (nav) {
        nav.addEventListener(
            'scroll',
            scheduleNavScrollSave,
            { passive: true }
        );
    }

    groupButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            preserveNavScroll(function () {
                if (
                    body.classList.contains('sm-sidebar-collapsed')
                    && isDesktop()
                ) {
                    body.classList.remove('sm-sidebar-collapsed');
                    saveCollapsedState();
                    updateCollapseButton();
                }

                var group = button.closest('[data-sidebar-group]');
                var open = !group.classList.contains('is-open');
                setGroupOpen(group, open, true);
            }, button);
        });
    });

    if (collapseButton) {
        collapseButton.addEventListener('click', function () {
            preserveNavScroll(function () {
                body.classList.toggle('sm-sidebar-collapsed');
                clearSearch(false);
                saveCollapsedState();
                updateCollapseButton();
            }, activeItem);
        });
    }

    if (mobileCloseButton) {
        mobileCloseButton.addEventListener('click', function () {
            closeMobileMenu(true);
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            closeMobileMenu(true);
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterMenu);
        searchInput.addEventListener('search', filterMenu);
    }

    if (searchClear) {
        searchClear.addEventListener('click', function () {
            clearSearch(true);
        });
    }

    menuItems.forEach(function (item) {
        /*
         * pointerdown guarda la posición antes de que el navegador comience
         * la navegación. click cubre teclado y navegadores sin Pointer Events.
         */
        item.addEventListener('pointerdown', function () {
            saveNavigationAnchor(item);
        }, { passive: true });

        item.addEventListener('click', function () {
            saveNavigationAnchor(item);

            if (!isDesktop()) {
                closeMobileMenu(false);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        var target = event.target;
        var isTyping = target && (
            target.tagName === 'INPUT'
            || target.tagName === 'TEXTAREA'
            || target.tagName === 'SELECT'
            || target.isContentEditable
        );

        if (event.key === 'Escape') {
            if (body.classList.contains('sm-sidebar-mobile-open')) {
                event.preventDefault();
                closeMobileMenu(true);
                return;
            }

            if (searchInput && searchInput.value !== '') {
                event.preventDefault();
                clearSearch(true);
            }
        }

        if (
            event.key === '/'
            && !isTyping
            && searchInput
            && (
                !body.classList.contains('sm-sidebar-collapsed')
                || !isDesktop()
            )
        ) {
            event.preventDefault();
            searchInput.focus({ preventScroll: true });
        }

        keepFocusInsideSidebar(event);
    });

    window.addEventListener('resize', function () {
        if (isDesktop()) {
            closeMobileMenu(false);
            updateCollapseButton();
        }

        window.requestAnimationFrame(function () {
            if (nav) {
                nav.scrollTop = clampNavScroll(nav.scrollTop);
            }
        });
    });

    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) {
            return;
        }

        html.classList.add('sm-sidebar-restoring');
        restoreFinished = false;
        restoreOpenGroups();
        updateCollapseButton();
        restoreNavScroll();
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            saveNavScroll();
        }
    });

    window.addEventListener('pagehide', saveNavScroll);
    window.addEventListener('beforeunload', saveNavScroll);

    /*
     * Protección final: aunque una fuente o CSS externo tarde demasiado,
     * la interfaz nunca queda permanentemente en modo de restauración.
     */
    window.setTimeout(finishInitialRestore, 900);

    window.smAbrirMenu = openMobileMenu;
    window.smCerrarMenu = function () {
        closeMobileMenu(true);
    };
    window.smAlternarMenu = function () {
        if (body.classList.contains('sm-sidebar-mobile-open')) {
            closeMobileMenu(true);
        } else {
            openMobileMenu();
        }
    };
})();
</script>