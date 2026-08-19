<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/seguridad.php';

sm_requerir_sesion(['TECNICO'], false);

$csrfToken = sm_token_csrf();

$nombreTecnico = trim(
    (string) (
        $_SESSION['nombre_completo']
        ?? $_SESSION['usuario']
        ?? 'Técnico'
    )
);

$cssMantenimientosAsignados = __DIR__ . '/../css/style_mantenimientos_asignados.css';
$versionCss = is_file($cssMantenimientosAsignados)
    ? (string) filemtime($cssMantenimientosAsignados)
    : (string) time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0b2b47">
    <meta
        name="description"
        content="Planeación y mantenimientos asignados al técnico"
    >

    <title>Mantenimientos asignados | Sistema de Mantenimiento</title>

    <link
        rel="stylesheet"
        href="../css/style_mantenimientos_asignados.css?v=<?= htmlspecialchars($versionCss, ENT_QUOTES, 'UTF-8') ?>"
    >
</head>

<body>

<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>


<svg class="masg-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="masg-icon-sparkles" viewBox="0 0 24 24">
        <path d="m12 3-1.2 3.8L7 8l3.8 1.2L12 13l1.2-3.8L17 8l-3.8-1.2L12 3Z"/>
        <path d="m5 15-.7 2.3L2 18l2.3.7L5 21l.7-2.3L8 18l-2.3-.7L5 15Z"/>
        <path d="m19 13-.7 2.3-2.3.7 2.3.7L19 19l.7-2.3 2.3-.7L19 13Z"/>
    </symbol>
    <symbol id="masg-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="masg-icon-calendar" viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path d="M16 3v4M8 3v4M3 10h18"/>
        <path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>
    </symbol>
    <symbol id="masg-icon-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </symbol>
    <symbol id="masg-icon-shield" viewBox="0 0 24 24">
        <path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="masg-icon-list" viewBox="0 0 24 24">
        <path d="M8 6h12M8 12h12M8 18h12"/>
        <path d="M4 6h.01M4 12h.01M4 18h.01"/>
    </symbol>
    <symbol id="masg-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="masg-icon-check" viewBox="0 0 24 24">
        <path d="m5 12 4 4L19 6"/>
    </symbol>
    <symbol id="masg-icon-arrow" viewBox="0 0 24 24">
        <path d="M5 12h14M14 7l5 5-5 5"/>
    </symbol>
    <symbol id="masg-icon-play" viewBox="0 0 24 24">
        <path d="m8 5 11 7-11 7V5Z"/>
    </symbol>
    <symbol id="masg-icon-warning" viewBox="0 0 24 24">
        <path d="M12 3 2.8 20h18.4L12 3Z"/>
        <path d="M12 9v5M12 17h.01"/>
    </symbol>
    <symbol id="masg-icon-users" viewBox="0 0 24 24">
        <circle cx="9" cy="8" r="3"/>
        <circle cx="17" cy="9" r="2.5"/>
        <path d="M3 20a6 6 0 0 1 12 0M14 20a5 5 0 0 1 7 0"/>
    </symbol>
    <symbol id="masg-icon-forward" viewBox="0 0 24 24">
        <path d="M5 12h14M14 7l5 5-5 5"/>
    </symbol>
</svg>

<main class="masg-page">
    <div class="masg-ambient masg-ambient--one" aria-hidden="true"></div>
    <div class="masg-ambient masg-ambient--two" aria-hidden="true"></div>

    <section class="masg-heading masg-hero" aria-labelledby="tituloMantenimientosAsignados">
        <div class="masg-hero__content">
            <div class="masg-hero__copy">
                <p class="masg-eyebrow">
                    <span class="masg-eyebrow__icon" aria-hidden="true">
                        <svg><use href="#masg-icon-sparkles"></use></svg>
                    </span>
                    Planeación del técnico
                </p>

                <h1 id="tituloMantenimientosAsignados">Mis mantenimientos asignados</h1>

                <p class="masg-hero__description">
                    Consulta tu programación, identifica atrasos y comienza los trabajos
                    que ya están disponibles para tu participación.
                </p>

                <div class="masg-hero__meta">
                    <span>
                        <span class="masg-live-dot" aria-hidden="true"></span>
                        Sesión de <?= htmlspecialchars($nombreTecnico, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span>
                        Actualización automática cada <strong>30 segundos</strong>
                    </span>
                </div>
            </div>

            <div class="masg-hero__actions">
                <div class="masg-hero__mini-card">
                    <span class="masg-hero__mini-icon" aria-hidden="true">
                        <svg><use href="#masg-icon-calendar"></use></svg>
                    </span>
                    <div>
                        <small>Centro de planeación</small>
                        <strong>Programación diaria y carga de trabajo</strong>
                    </div>
                </div>

                <button
                    type="button"
                    class="masg-btn masg-btn--hero"
                    id="btnActualizar"
                >
                    <svg aria-hidden="true"><use href="#masg-icon-refresh"></use></svg>
                    <span>Actualizar información</span>
                </button>
            </div>
        </div>
    </section>

    <section class="masg-guides" aria-label="Reglas de los mantenimientos asignados">
        <article>
            <span class="masg-guide-icon" aria-hidden="true">
                <svg><use href="#masg-icon-calendar"></use></svg>
            </span>
            <div>
                <strong>Programación por día</strong>
                <p>Los trabajos se habilitan por fecha programada, sin depender de una hora específica.</p>
            </div>
        </article>

        <article>
            <span class="masg-guide-icon" aria-hidden="true">
                <svg><use href="#masg-icon-shield"></use></svg>
            </span>
            <div>
                <strong>Una ejecución activa</strong>
                <p>Debes pausar o finalizar tu actividad actual antes de iniciar otro mantenimiento.</p>
            </div>
        </article>

        <article>
            <span class="masg-guide-icon" aria-hidden="true">
                <svg><use href="#masg-icon-users"></use></svg>
            </span>
            <div>
                <strong>Participación por técnico</strong>
                <p>Cada integrante registra su propio inicio y tiempo, aunque el equipo ya esté trabajando.</p>
            </div>
        </article>
    </section>

    <section
        class="masg-alert masg-alert--danger"
        id="alertaActividadActiva"
        hidden
    >
        <span class="masg-alert__icon" aria-hidden="true"><svg><use href="#masg-icon-play"></use></svg></span>

        <div>
            <strong>Ya tienes una actividad en proceso</strong>

            <p id="textoActividadActiva">
                Finaliza o pausa correctamente tu actividad antes de iniciar otra.
            </p>
        </div>

        <a
            href="mantenimiento_activo.php"
            class="masg-btn masg-btn--danger-soft"
            id="enlaceActividadActiva"
        >
            <span>Abrir actividad</span>
            <svg aria-hidden="true"><use href="#masg-icon-arrow"></use></svg>
        </a>
    </section>

    <section
        class="masg-alert masg-alert--urgent"
        id="alertaUrgenciaAceptada"
        hidden
    >
        <span class="masg-alert__icon" aria-hidden="true"><svg><use href="#masg-icon-warning"></use></svg></span>

        <div>
            <strong>Tienes una urgencia aceptada pendiente de atención</strong>

            <p id="textoUrgenciaAceptada">
                Inicia la urgencia o libera tu lugar antes de comenzar un
                mantenimiento normal.
            </p>
        </div>

        <a
            href="urgencias_disponibles.php"
            class="masg-btn masg-btn--urgent"
        >
            <span>Revisar urgencia</span>
            <svg aria-hidden="true"><use href="#masg-icon-arrow"></use></svg>
        </a>
    </section>

    <section
        class="masg-alert masg-alert--cancelled"
        id="alertaCancelacionAdministrativa"
        hidden
    >
        <span class="masg-alert__icon" aria-hidden="true"><svg><use href="#masg-icon-warning"></use></svg></span>
        <div>
            <strong id="tituloCancelacionAdministrativa">Una actividad fue cancelada por administración</strong>
            <p id="textoCancelacionAdministrativa">Consulta el motivo y la trazabilidad conservada.</p>
        </div>
        <a href="mantenimientos_finalizados.php#cancelaciones" class="masg-btn masg-btn--cancelled">
            <span>Ver historial de cancelaciones</span>
            <svg aria-hidden="true"><use href="#masg-icon-arrow"></use></svg>
        </a>
    </section>

    <div
        class="masg-status"
        id="estadoCarga"
        role="status"
        aria-live="polite"
    >
        Cargando mantenimientos asignados...
    </div>

    <section class="masg-kpis" aria-label="Resumen de mantenimientos asignados">
        <article class="masg-kpi masg-kpi--available">
            <span class="masg-kpi__icon" aria-hidden="true">
                <svg><use href="#masg-icon-check"></use></svg>
            </span>
            <span>Disponibles</span>
            <strong id="kpiDisponibles">0</strong>
            <small>Pueden iniciarse ahora</small>
        </article>

        <article class="masg-kpi masg-kpi--today">
            <span class="masg-kpi__icon" aria-hidden="true">
                <svg><use href="#masg-icon-calendar"></use></svg>
            </span>
            <span>Programados hoy</span>
            <strong id="kpiHoy">0</strong>
            <small>Fecha programada actual</small>
        </article>

        <article class="masg-kpi masg-kpi--future">
            <span class="masg-kpi__icon" aria-hidden="true">
                <svg><use href="#masg-icon-clock"></use></svg>
            </span>
            <span>Próximos</span>
            <strong id="kpiProximos">0</strong>
            <small>Aún no se pueden iniciar</small>
        </article>

        <article class="masg-kpi masg-kpi--late">
            <span class="masg-kpi__icon" aria-hidden="true">
                <svg><use href="#masg-icon-warning"></use></svg>
            </span>
            <span>Atrasados</span>
            <strong id="kpiAtrasados">0</strong>
            <small>Siguen disponibles</small>
        </article>

        <article class="masg-kpi masg-kpi--team">
            <span class="masg-kpi__icon" aria-hidden="true">
                <svg><use href="#masg-icon-users"></use></svg>
            </span>
            <span>Equipo trabajando</span>
            <strong id="kpiEquipo">0</strong>
            <small>Puedes unirte cuando corresponda</small>
        </article>
    </section>

    <section class="masg-panel">

        <header class="masg-panel__head">
            <div class="masg-panel__title">
                <span class="masg-panel__icon" aria-hidden="true">
                    <svg><use href="#masg-icon-list"></use></svg>
                </span>
                <div>
                    <p class="masg-eyebrow">Trabajos abiertos</p>
                    <h2>Programación y asignaciones</h2>
                    <p>Se muestran únicamente las asignaciones que siguen vigentes para tu cuenta.</p>
                </div>
            </div>

            <div class="masg-panel__meta">
                <span class="masg-server-pill">
                    <i aria-hidden="true"></i>
                    Datos del servidor
                </span>
                <span class="masg-updated" id="ultimaActualizacion">Sin actualizar</span>
            </div>
        </header>

        <div class="masg-filters">

            <label class="masg-field masg-field--search">
                <span>Buscar</span>

                <span class="masg-search-control">
                    <svg aria-hidden="true"><use href="#masg-icon-search"></use></svg>
                    <input
                        type="search"
                        id="busqueda"
                        maxlength="120"
                        autocomplete="off"
                        placeholder="Folio, equipo, ubicación o descripción"
                    >
                </span>
            </label>

            <label class="masg-field">
                <span>Tipo</span>

                <select id="filtroTipo">
                    <option value="">Todos los tipos</option>
                    <option value="CORRECTIVO_PROGRAMABLE">
                        Correctivo programable
                    </option>
                    <option value="MODIFICACION_MEJORA">
                        Modificación o mejora
                    </option>
                    <option value="RUTINARIO">
                        Rutinario
                    </option>
                </select>
            </label>

            <label class="masg-field">
                <span>Prioridad</span>

                <select id="filtroPrioridad">
                    <option value="">Todas las prioridades</option>
                    <option value="URGENTE">Urgente</option>
                    <option value="ALTA">Alta</option>
                    <option value="MEDIA">Media</option>
                    <option value="BAJA">Baja</option>
                </select>
            </label>

            <label class="masg-field">
                <span>Orden</span>

                <select id="filtroOrden">
                    <option value="PRIORIDAD">
                        Recomendado
                    </option>
                    <option value="FECHA_ASC">
                        Fecha más cercana
                    </option>
                    <option value="FECHA_DESC">
                        Fecha más lejana
                    </option>
                    <option value="ATRASO">
                        Mayor atraso
                    </option>
                </select>
            </label>

        </div>

        <div
            class="masg-tabs"
            role="tablist"
            aria-label="Filtros rápidos"
        >
            <button
                type="button"
                class="masg-tab is-active"
                data-filter="TODOS"
            >
                Todos
            </button>

            <button
                type="button"
                class="masg-tab"
                data-filter="DISPONIBLES"
            >
                Disponibles
            </button>

            <button
                type="button"
                class="masg-tab"
                data-filter="HOY"
            >
                Hoy
            </button>

            <button
                type="button"
                class="masg-tab"
                data-filter="ATRASADOS"
            >
                Atrasados
            </button>

            <button
                type="button"
                class="masg-tab"
                data-filter="PROXIMOS"
            >
                Próximos
            </button>

            <button
                type="button"
                class="masg-tab"
                data-filter="EQUIPO"
            >
                Equipo trabajando
            </button>

            <button
                type="button"
                class="masg-tab"
                data-filter="ABIERTOS"
            >
                Mis actividades abiertas
            </button>
        </div>

        <div
            class="masg-empty"
            id="estadoVacio"
            hidden
        >
            <span aria-hidden="true"><svg><use href="#masg-icon-check"></use></svg></span>

            <h3>No hay mantenimientos con estos filtros</h3>

            <p>
                Cambia los filtros o actualiza la información.
            </p>
        </div>

        <div
            class="masg-list"
            id="listaMantenimientos"
            hidden
        ></div>

    </section>

    <footer class="masg-footer">
        <span>Planeación operativa del técnico</span>
        <span>Asignaciones y disponibilidad sincronizadas con el servidor</span>
    </footer>

    <div class="masg-tools-background" aria-hidden="true"></div>
</main>

<!-- ================================================================
     DETALLE
     ================================================================ -->

<div
    class="masg-modal"
    id="modalDetalle"
    role="dialog"
    aria-modal="true"
    aria-labelledby="detalleTitulo"
    aria-hidden="true"
>
    <div class="masg-modal__dialog masg-modal__dialog--wide">

        <header class="masg-modal__head">
            <div>
                <p class="masg-eyebrow">DETALLE COMPLETO</p>

                <h2 id="detalleTitulo">
                    Detalle del mantenimiento
                </h2>

                <p id="detalleSubtitulo">
                    Información de la solicitud, programación y participantes.
                </p>
            </div>

            <button
                type="button"
                class="masg-modal__close"
                data-close="modalDetalle"
                aria-label="Cerrar"
            >
                ×
            </button>
        </header>

        <div
            class="masg-modal__body"
            id="detalleContenido"
        >
            <div class="masg-modal-loading">
                Cargando información...
            </div>
        </div>

        <footer class="masg-modal__actions">
            <button
                type="button"
                class="masg-btn masg-btn--secondary"
                data-close="modalDetalle"
            >
                Cerrar
            </button>

            <button
                type="button"
                class="masg-btn masg-btn--primary"
                id="btnDetallePrincipal"
                hidden
            >
                Iniciar mantenimiento
            </button>
        </footer>

    </div>
</div>

<!-- ================================================================
     CONFIRMACIÓN DE INICIO
     ================================================================ -->

<div
    class="masg-modal"
    id="modalIniciar"
    role="dialog"
    aria-modal="true"
    aria-labelledby="iniciarTitulo"
    aria-hidden="true"
>
    <div class="masg-modal__dialog">

        <header class="masg-modal__head">
            <div>
                <p class="masg-eyebrow">REGISTRO DE INICIO</p>

                <h2 id="iniciarTitulo">
                    Iniciar mantenimiento
                </h2>

                <p>
                    La fecha y la hora se tomarán automáticamente del servidor.
                </p>
            </div>

            <button
                type="button"
                class="masg-modal__close"
                data-close="modalIniciar"
                aria-label="Cerrar"
            >
                ×
            </button>
        </header>

        <form
            id="formIniciar"
            novalidate
        >
            <div class="masg-modal__body">

                <input
                    type="hidden"
                    id="asignacionIniciar"
                    value=""
                >

                <div
                    class="masg-start-summary"
                    id="resumenInicio"
                ></div>

                <section
                    class="masg-warning masg-warning--danger"
                    id="avisoNocturnoPeligroso"
                    hidden
                >
                    <span aria-hidden="true">!</span>

                    <div>
                        <strong>
                            Trabajo peligroso durante turno nocturno
                        </strong>

                        <p>
                            Verifica iluminación, acompañamiento, permisos,
                            bloqueo del equipo y condiciones seguras antes
                            de comenzar.
                        </p>
                    </div>
                </section>

                <section
                    class="masg-warning masg-warning--info"
                    id="avisoUnirseEquipo"
                    hidden
                >
                    <span aria-hidden="true">+</span>

                    <div>
                        <strong>
                            Otros técnicos ya están trabajando
                        </strong>

                        <p>
                            Al confirmar se iniciará únicamente tu participación
                            y se registrará tu propio tiempo de ejecución.
                        </p>
                    </div>
                </section>

                <div class="masg-start-checklist">
                    <p>Antes de iniciar confirma que:</p>

                    <ul>
                        <li>Revisaste el equipo y la ubicación.</li>
                        <li>Conoces el trabajo solicitado.</li>
                        <li>Tienes herramientas y protección necesarias.</li>
                        <li>No tienes otra ejecución activa.</li>
                    </ul>
                </div>

            </div>

            <footer class="masg-modal__actions">
                <button
                    type="button"
                    class="masg-btn masg-btn--secondary"
                    data-close="modalIniciar"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="masg-btn masg-btn--primary"
                    id="btnConfirmarInicio"
                >
                    Confirmar inicio
                </button>
            </footer>

        </form>

    </div>
</div>

<?php include __DIR__ . '/../inc/alertas.php'; ?>

<script>
(function () {
    'use strict';

    const UI = window.SistemaUI;

    if (!UI) {
        const estadoCarga = document.getElementById('estadoCarga');

        if (estadoCarga) {
            estadoCarga.textContent =
                'No fue posible cargar las herramientas de la interfaz.';
            estadoCarga.className =
                'masg-status masg-status--error';
        }

        console.error(
            'No se cargó window.SistemaUI. Revisa inc/alertas.php.'
        );

        return;
    }

    const API =
        '../funciones/mantenimientos_asignados_funciones.php';

    const CSRF_TOKEN =
        <?= json_encode(
            $csrfToken,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) ?>;

    const $ = function (id) {
        return document.getElementById(id);
    };

    const state = {
        items: [],
        cancelaciones: [],
        resumen: {},
        perfil: null,
        bloqueos: {},
        filter: 'TODOS',
        loading: false,
        processing: false,
        selected: null,
        detail: null,
        requestedRequestId: null,
        requestedAssignmentId: null,
        lastSyncAt: 0,
        timer: null
    };

    document.addEventListener(
        'DOMContentLoaded',
        initialize
    );

    function initialize() {
        bindEvents();

        const params = new URLSearchParams(
            window.location.search
        );

        const requestedRequest =
            Number(params.get('solicitud_id') || 0);

        const requestedAssignment =
            Number(params.get('asignacion_id') || 0);

        state.requestedRequestId =
            Number.isInteger(requestedRequest)
            && requestedRequest > 0
                ? requestedRequest
                : null;

        state.requestedAssignmentId =
            Number.isInteger(requestedAssignment)
            && requestedAssignment > 0
                ? requestedAssignment
                : null;

        loadModule(null, false);

        state.timer = window.setInterval(
            function () {
                if (
                    !state.processing
                    && !document.querySelector(
                        '.masg-modal.is-open'
                    )
                    && document.visibilityState === 'visible'
                ) {
                    loadModule(null, true);
                }
            },
            30000
        );

        window.addEventListener(
            'pagehide',
            function () {
                if (state.timer) {
                    window.clearInterval(state.timer);
                    state.timer = null;
                }
            },
            { once: true }
        );
    }

    function bindEvents() {
        $('btnActualizar').addEventListener(
            'click',
            function () {
                loadModule(this, false);
            }
        );

        $('busqueda').addEventListener(
            'input',
            renderList
        );

        $('filtroTipo').addEventListener(
            'change',
            renderList
        );

        $('filtroPrioridad').addEventListener(
            'change',
            renderList
        );

        $('filtroOrden').addEventListener(
            'change',
            renderList
        );

        document.querySelectorAll(
            '.masg-tab'
        ).forEach(function (button) {
            button.addEventListener(
                'click',
                function () {
                    document.querySelectorAll(
                        '.masg-tab'
                    ).forEach(function (other) {
                        other.classList.remove(
                            'is-active'
                        );
                    });

                    button.classList.add('is-active');

                    state.filter =
                        button.dataset.filter || 'TODOS';

                    renderList();
                }
            );
        });

        $('listaMantenimientos').addEventListener(
            'click',
            handleListAction
        );

        $('formIniciar').addEventListener(
            'submit',
            submitStart
        );

        $('btnDetallePrincipal').addEventListener(
            'click',
            function () {
                if (!state.detail) {
                    return;
                }

                const item =
                    state.detail.mantenimiento;

                closeModal('modalDetalle');

                if (
                    item.accion_principal === 'ABRIR'
                    && item.ejecucion_id
                ) {
                    openExecution(item.ejecucion_id);
                    return;
                }

                openStart(item);
            }
        );

        document.querySelectorAll(
            '[data-close]'
        ).forEach(function (button) {
            button.addEventListener(
                'click',
                function () {
                    closeModal(
                        button.dataset.close
                    );
                }
            );
        });

        document.querySelectorAll(
            '.masg-modal'
        ).forEach(function (modal) {
            modal.addEventListener(
                'click',
                function (event) {
                    if (event.target === modal) {
                        closeModal(modal.id);
                    }
                }
            );
        });

        document.addEventListener(
            'keydown',
            function (event) {
                if (event.key !== 'Escape') {
                    return;
                }

                const open =
                    document.querySelector(
                        '.masg-modal.is-open'
                    );

                if (open) {
                    closeModal(open.id);
                }
            }
        );
    }

    async function loadModule(
        button,
        silent
    ) {
        if (state.loading) {
            return;
        }

        state.loading = true;

        if (!silent) {
            showStatus(
                'Sincronizando fechas y cargando asignaciones...',
                'loading'
            );
        }

        UI.estadoBoton(
            button || null,
            true,
            'Actualizando...'
        );

        try {
            let synchronizationWarning = '';

            const now = Date.now();
            const shouldSynchronize =
                !silent
                || state.lastSyncAt === 0
                || (now - state.lastSyncAt) >= 300000;

            if (shouldSynchronize) {
                try {
                    await synchronizeLateAssignments();
                    state.lastSyncAt = now;
                } catch (syncError) {
                    if (errorHandled(syncError)) {
                        throw syncError;
                    }

                    synchronizationWarning =
                        syncError.message
                        || 'No fue posible actualizar los atrasos.';
                    console.warn(
                        'La lista se cargará sin sincronización:',
                        syncError
                    );
                }
            }

            const response =
                await UI.peticionJson(
                    API + '?accion=inicial'
                );

            state.items = Array.isArray(
                response.mantenimientos
            )
                ? response.mantenimientos
                : [];

            state.cancelaciones = Array.isArray(response.cancelaciones_recientes)
                ? response.cancelaciones_recientes
                : [];

            state.resumen =
                response.resumen || {};

            state.perfil =
                response.perfil || null;

            state.bloqueos =
                response.bloqueos || {};

            renderSummary();
            renderBlockers();
            renderList();

            $('ultimaActualizacion').textContent =
                response.fecha_servidor_texto
                    ? 'Actualizado: '
                        + response.fecha_servidor_texto
                    : 'Actualizado ahora';

            if (!silent) {
                showStatus(
                    synchronizationWarning !== ''
                        ? 'Asignaciones cargadas. '
                            + synchronizationWarning
                        : (
                            response.mensaje
                            || 'Información actualizada.'
                        ),
                    synchronizationWarning !== ''
                        ? 'warning'
                        : 'success'
                );
            }

            const requestedAssignment =
                state.requestedAssignmentId;

            const requestedRequest =
                state.requestedRequestId;

            state.requestedAssignmentId = null;
            state.requestedRequestId = null;

            if (
                requestedAssignment !== null
                || requestedRequest !== null
            ) {
                const item = state.items.find(
                    function (current) {
                        if (requestedAssignment !== null) {
                            return Number(
                                current.asignacion_id
                            ) === requestedAssignment;
                        }

                        return Number(
                            current.solicitud_id
                        ) === requestedRequest;
                    }
                );

                if (item) {
                    openDetail(item.asignacion_id);
                }
            }
        } catch (error) {
            console.error(error);

            if (!silent || state.items.length === 0) {
                state.items = [];
                state.resumen = {};
                state.bloqueos = {};

                renderSummary();
                renderBlockers();
                renderList();

                showStatus(
                    error.message
                        || 'No fue posible cargar las asignaciones.',
                    'error'
                );
            }

            if (
                !silent
                && !errorHandled(error)
            ) {
                await UI.error(
                    'No se pudieron cargar los mantenimientos',
                    error.message
                        || 'Actualiza la página e inténtalo nuevamente.'
                );
            }
        } finally {
            state.loading = false;

            UI.estadoBoton(
                button || null,
                false
            );
        }
    }

    async function synchronizeLateAssignments() {
        const formData = new FormData();

        formData.set(
            'accion',
            'sincronizar'
        );

        formData.set(
            'csrf_token',
            CSRF_TOKEN
        );

        await UI.peticionJson(
            API,
            {
                method: 'POST',
                body: formData
            }
        );
    }

    function renderSummary() {
        const summary =
            state.resumen || {};

        setText(
            'kpiDisponibles',
            summary.disponibles || 0
        );

        setText(
            'kpiHoy',
            summary.hoy || 0
        );

        setText(
            'kpiProximos',
            summary.proximos || 0
        );

        setText(
            'kpiAtrasados',
            summary.atrasados || 0
        );

        setText(
            'kpiEquipo',
            summary.equipo_trabajando || 0
        );
    }

    function renderBlockers() {
        const active =
            state.bloqueos.actividad_activa || null;

        const urgent =
            state.bloqueos.urgencia_aceptada || null;

        const activeAlert =
            $('alertaActividadActiva');

        const urgentAlert =
            $('alertaUrgenciaAceptada');

        const cancelledAlert =
            $('alertaCancelacionAdministrativa');

        const latestCancellation = state.cancelaciones.length > 0
            ? state.cancelaciones[0]
            : null;

        activeAlert.hidden = !active;
        urgentAlert.hidden = !urgent;
        cancelledAlert.hidden = !latestCancellation;

        if (active) {
            $('textoActividadActiva').textContent =
                'Actualmente trabajas en '
                + (active.folio || 'otro mantenimiento')
                + '.';

            $('enlaceActividadActiva').href =
                'mantenimiento_activo.php?ejecucion_id='
                + encodeURIComponent(
                    active.ejecucion_id
                );
        }

        if (urgent) {
            $('textoUrgenciaAceptada').textContent =
                'Aceptaste la urgencia '
                + (urgent.folio || '')
                + '. Atiéndela o libera tu lugar.';
        }


        if (latestCancellation) {
            const count = state.cancelaciones.length;
            $('tituloCancelacionAdministrativa').textContent = count > 1
                ? count + ' actividades fueron canceladas por administración'
                : 'Una actividad fue cancelada por administración';
            $('textoCancelacionAdministrativa').textContent =
                (latestCancellation.folio || 'El mantenimiento')
                + ' · '
                + (latestCancellation.nombre_equipo || 'Equipo sin nombre')
                + '. Motivo: '
                + (latestCancellation.motivo_cancelacion || 'Sin motivo registrado.');
        }
    }

    function renderList() {
        const container =
            $('listaMantenimientos');

        const empty =
            $('estadoVacio');

        const search =
            normalizeText(
                $('busqueda').value
            );

        const type =
            $('filtroTipo').value;

        const priority =
            $('filtroPrioridad').value;

        const order =
            $('filtroOrden').value;

        let items = state.items.filter(
            function (item) {
                if (
                    type !== ''
                    && item.tipo_solicitud !== type
                ) {
                    return false;
                }

                if (
                    priority !== ''
                    && item.prioridad !== priority
                ) {
                    return false;
                }

                if (
                    !matchesQuickFilter(item)
                ) {
                    return false;
                }

                if (search === '') {
                    return true;
                }

                const searchable =
                    normalizeText([
                        item.folio,
                        item.tipo_solicitud,
                        item.prioridad,
                        item.estado_solicitud,
                        item.estado_asignacion,
                        item.estado_programacion,
                        item.tipo_falla,
                        item.causa_averia,
                        item.solicitante,
                        item.codigo_equipo,
                        item.nombre_equipo,
                        item.departamento,
                        item.area,
                        item.proceso,
                        item.descripcion_solicitud
                    ].join(' '));

                return searchable.includes(search);
            }
        );

        items = sortItems(
            items,
            order
        );

        container.innerHTML = '';

        if (items.length === 0) {
            container.hidden = true;
            empty.hidden = false;
            return;
        }

        empty.hidden = true;
        container.hidden = false;

        items.forEach(function (item) {
            container.insertAdjacentHTML(
                'beforeend',
                renderCard(item)
            );
        });
    }

    function matchesQuickFilter(item) {
        switch (state.filter) {
            case 'DISPONIBLES':
                return Number(
                    item.puede_iniciar
                ) === 1;

            case 'HOY':
                return item.categoria === 'HOY';

            case 'ATRASADOS':
                return item.categoria === 'ATRASADA';

            case 'PROXIMOS':
                return item.categoria === 'PROXIMA';

            case 'EQUIPO':
                return Number(
                    item.equipo_para_unirse
                ) === 1;

            case 'ABIERTOS':
                return [
                    'EN_PROCESO',
                    'PAUSADA'
                ].includes(
                    item.estado_ejecucion
                );

            default:
                return true;
        }
    }

    function sortItems(items, order) {
        const copy =
            items.slice();

        copy.sort(function (a, b) {
            if (order === 'FECHA_ASC') {
                return compareDates(
                    a.fecha_programada,
                    b.fecha_programada
                );
            }

            if (order === 'FECHA_DESC') {
                return compareDates(
                    b.fecha_programada,
                    a.fecha_programada
                );
            }

            if (order === 'ATRASO') {
                return Number(
                    b.dias_atraso || 0
                ) - Number(
                    a.dias_atraso || 0
                );
            }

            return Number(
                a.orden_visual || 999
            ) - Number(
                b.orden_visual || 999
            );
        });

        return copy;
    }

    function compareDates(a, b) {
        return String(a || '9999-12-31')
            .localeCompare(
                String(b || '9999-12-31')
            );
    }

    function renderCard(item) {
        const action =
            renderMainAction(item);

        const teamText =
            Number(item.total_tecnicos || 0)
            + (
                Number(item.total_tecnicos || 0) === 1
                    ? ' técnico'
                    : ' técnicos'
            );

        const startedText =
            Number(item.tecnicos_iniciaron || 0)
            + ' iniciaron';

        return `
            <article
                class="masg-card masg-card--${escapeAttribute(
                    String(item.categoria || 'OTRA').toLowerCase()
                )}"
                data-assignment-id="${Number(item.asignacion_id)}"
            >
                <header class="masg-card__head">
                    <div class="masg-card__identity">
                        <div class="masg-card__badges">
                            ${badgeType(item.tipo_solicitud)}
                            ${badgePriority(item.prioridad)}
                            ${badgeState(item.estado_solicitud)}
                        </div>

                        <h3>${escapeHtml(item.folio || 'Sin folio')}</h3>

                        <p>
                            ${escapeHtml(item.codigo_equipo || 'Sin código')}
                            ·
                            ${escapeHtml(item.nombre_equipo || 'Equipo sin nombre')}
                        </p>
                    </div>

                    <div class="masg-date-state">
                        <strong>${escapeHtml(item.fecha_relativa || 'Sin fecha')}</strong>
                        <span>${escapeHtml(item.fecha_programada_texto || 'Sin programación')}</span>
                    </div>
                </header>

                <div class="masg-card__body">
                    <div class="masg-location">
                        <span aria-hidden="true">⌖</span>

                        <p>
                            <strong>${escapeHtml(item.departamento || 'Sin departamento')}</strong>
                            <small>
                                ${escapeHtml(item.area || 'Sin área')}
                                ·
                                ${escapeHtml(item.proceso || 'Sin proceso')}
                            </small>
                        </p>
                    </div>

                    <p class="masg-description">
                        ${escapeHtml(
                            item.descripcion_solicitud
                            || 'Sin descripción registrada.'
                        )}
                    </p>

                    ${renderResourceSummary(item)}

                    <div class="masg-card__meta">
                        <span>
                            <b>Mi estado:</b>
                            ${escapeHtml(
                                labelAssignmentState(
                                    item.estado_asignacion
                                )
                            )}
                        </span>

                        <span>
                            <b>Programación:</b>
                            ${escapeHtml(
                                labelProgramState(
                                    item.estado_programacion
                                )
                            )}
                        </span>

                        <span>
                            <b>Equipo:</b>
                            ${escapeHtml(teamText)}
                            ·
                            ${escapeHtml(startedText)}
                        </span>
                    </div>

                    ${renderWarnings(item)}
                </div>

                <footer class="masg-card__actions">
                    <button
                        type="button"
                        class="masg-btn masg-btn--secondary"
                        data-action="detail"
                        data-id="${Number(item.asignacion_id)}"
                    >
                        Ver detalle
                    </button>

                    ${action}
                </footer>
            </article>
        `;
    }

    function resourceItems(item, key) {
        return Array.isArray(item && item[key]) ? item[key] : [];
    }

    function renderResourceSummary(item) {
        const herramientas = resourceItems(item, 'herramientas_recomendadas');
        const refacciones = resourceItems(item, 'refacciones_recomendadas');
        const total = herramientas.length + refacciones.length;

        if (total < 1) {
            return `
                <div class="masg-resource-summary masg-resource-summary--empty">
                    <span aria-hidden="true">i</span>
                    <p>
                        <strong>Sin recomendaciones registradas</strong>
                        <small>Verifica lo necesario antes de trasladarte al equipo.</small>
                    </p>
                </div>
            `;
        }

        return `
            <div class="masg-resource-summary">
                <span class="masg-resource-summary__title">Lleva preparado</span>
                <span><b>${herramientas.length}</b> ${herramientas.length === 1 ? 'herramienta' : 'herramientas'}</span>
                <span><b>${refacciones.length}</b> ${refacciones.length === 1 ? 'refacción' : 'refacciones'}</span>
            </div>
        `;
    }

    function renderResourceList(title, items, emptyText) {
        const list = Array.isArray(items) ? items : [];

        if (list.length < 1) {
            return `
                <article class="masg-resource-panel masg-resource-panel--empty">
                    <header><h4>${escapeHtml(title)}</h4><span>0</span></header>
                    <p>${escapeHtml(emptyText)}</p>
                </article>
            `;
        }

        return `
            <article class="masg-resource-panel">
                <header><h4>${escapeHtml(title)}</h4><span>${list.length}</span></header>
                <ul>
                    ${list.map((resource) => `
                        <li>
                            <span aria-hidden="true">✓</span>
                            <div>
                                <strong>${escapeHtml(resource.nombre || 'Recurso')}</strong>
                                ${resource.codigo ? `<small>Código: ${escapeHtml(resource.codigo)}</small>` : ''}
                                ${resource.descripcion ? `<p>${escapeHtml(resource.descripcion)}</p>` : ''}
                                ${Number(resource.activo) !== 1 ? '<em>Recurso desactivado; se conserva por historial.</em>' : ''}
                            </div>
                        </li>
                    `).join('')}
                </ul>
            </article>
        `;
    }

    function renderRecommendedResources(item) {
        const herramientas = resourceItems(item, 'herramientas_recomendadas');
        const refacciones = resourceItems(item, 'refacciones_recomendadas');
        const total = herramientas.length + refacciones.length;

        return `
            <section class="masg-resources-detail">
                <header>
                    <div>
                        <h3>Herramientas y refacciones recomendadas</h3>
                        <p>Prepara estos recursos antes de acudir al mantenimiento.</p>
                    </div>
                    <span>${total} ${total === 1 ? 'recurso' : 'recursos'}</span>
                </header>
                ${total < 1 ? `
                    <div class="masg-resources-detail__notice">
                        No existen recomendaciones registradas. Revisa el trabajo y lleva los recursos que consideres necesarios.
                    </div>
                ` : ''}
                <div class="masg-resources-detail__grid">
                    ${renderResourceList('Herramientas', herramientas, 'No se recomendaron herramientas.')}
                    ${renderResourceList('Refacciones', refacciones, 'No se recomendaron refacciones.')}
                </div>
            </section>
        `;
    }

    function renderStartResources(item) {
        const herramientas = resourceItems(item, 'herramientas_recomendadas');
        const refacciones = resourceItems(item, 'refacciones_recomendadas');
        const nombresHerramientas = herramientas.map((resource) => resource.nombre).filter(Boolean);
        const nombresRefacciones = refacciones.map((resource) => resource.nombre).filter(Boolean);

        if (nombresHerramientas.length + nombresRefacciones.length < 1) {
            return 'Sin recomendaciones registradas; verifica qué necesitas antes de comenzar.';
        }

        const partes = [];

        if (nombresHerramientas.length > 0) {
            partes.push('Herramientas: ' + nombresHerramientas.join(', '));
        }

        if (nombresRefacciones.length > 0) {
            partes.push('Refacciones: ' + nombresRefacciones.join(', '));
        }

        return partes.join(' · ');
    }

    function renderWarnings(item) {
        let html = '';

        if (
            Number(item.confirmacion_nocturna_pendiente) === 1
        ) {
            html += `
                <div class="masg-inline-alert masg-inline-alert--danger">
                    <b>Inicio bloqueado.</b>
                    Falta la confirmación administrativa de seguridad nocturna.
                </div>
            `;
        } else if (
            Number(item.alerta_nocturna_peligrosa) === 1
        ) {
            html += `
                <div class="masg-inline-alert masg-inline-alert--danger">
                    <b>Trabajo peligroso en turno nocturno.</b>
                    Revisa las condiciones de seguridad confirmadas.
                </div>
            `;
        }

        if (
            Number(item.es_laboral_calendario) === 0
        ) {
            html += `
                <div class="masg-inline-alert masg-inline-alert--muted">
                    <b>Fecha no hábil.</b>
                    ${escapeHtml(
                        item.observacion_calendario
                        || 'La programación está marcada como día inhábil.'
                    )}
                </div>
            `;
        }

        if (
            Number(item.requiere_paro_equipo || 0) === 1
        ) {
            html += `
                <div class="masg-inline-alert">
                    Requiere paro del equipo antes de intervenir.
                </div>
            `;
        }

        if (
            Number(item.equipo_trabajando || 0) === 1
            && !item.ejecucion_id
        ) {
            html += `
                <div class="masg-inline-alert masg-inline-alert--info">
                    Otros técnicos ya iniciaron este mantenimiento.
                </div>
            `;
        }

        if (
            item.motivo_bloqueo
            && Number(item.puede_iniciar) !== 1
            && item.accion_principal !== 'ABRIR'
        ) {
            html += `
                <div class="masg-inline-alert masg-inline-alert--muted">
                    ${escapeHtml(item.motivo_bloqueo)}
                </div>
            `;
        }

        return html;
    }

    function renderMainAction(item) {
        if (
            item.accion_principal === 'ABRIR'
            && item.ejecucion_id
        ) {
            return `
                <button
                    type="button"
                    class="masg-btn masg-btn--primary"
                    data-action="open"
                    data-execution="${Number(item.ejecucion_id)}"
                >
                    ${
                        item.estado_ejecucion === 'PAUSADA'
                            ? 'Revisar actividad pausada'
                            : 'Abrir actividad'
                    }
                </button>
            `;
        }

        if (Number(item.puede_iniciar) === 1) {
            return `
                <button
                    type="button"
                    class="masg-btn masg-btn--primary"
                    data-action="start"
                    data-id="${Number(item.asignacion_id)}"
                >
                    ${escapeHtml(
                        item.texto_accion
                        || 'Iniciar mantenimiento'
                    )}
                </button>
            `;
        }

        return `
            <button
                type="button"
                class="masg-btn masg-btn--disabled"
                disabled
            >
                ${escapeHtml(
                    item.texto_accion
                    || 'No disponible'
                )}
            </button>
        `;
    }

    async function handleListAction(event) {
        const button =
            event.target.closest(
                '[data-action]'
            );

        if (!button) {
            return;
        }

        const action =
            button.dataset.action;

        if (action === 'open') {
            openExecution(
                Number(
                    button.dataset.execution || 0
                )
            );

            return;
        }

        const id =
            Number(
                button.dataset.id || 0
            );

        const item =
            state.items.find(
                function (current) {
                    return Number(
                        current.asignacion_id
                    ) === id;
                }
            );

        if (!item) {
            await UI.advertencia(
                'Información actualizada',
                'El mantenimiento ya no se encuentra en la lista.'
            );

            await loadModule(null, true);
            return;
        }

        if (action === 'detail') {
            await openDetail(id);
            return;
        }

        if (action === 'start') {
            openStart(item);
        }
    }

    async function openDetail(id) {
        if (state.processing) {
            return;
        }

        state.processing = true;

        $('detalleContenido').innerHTML =
            '<div class="masg-modal-loading">Cargando información...</div>';

        $('btnDetallePrincipal').hidden = true;

        openModal('modalDetalle');

        try {
            const response =
                await UI.peticionJson(
                    API
                    + '?accion=detalle&asignacion_id='
                    + encodeURIComponent(id)
                );

            state.detail = response;

            renderDetail(
                response.mantenimiento,
                response.participantes || []
            );
        } catch (error) {
            console.error(error);

            $('detalleContenido').innerHTML = `
                <div class="masg-modal-error">
                    ${escapeHtml(
                        error.message
                        || 'No fue posible cargar el detalle.'
                    )}
                </div>
            `;
        } finally {
            state.processing = false;
        }
    }

    function renderDetail(item, participants) {
        $('detalleTitulo').textContent =
            item.folio || 'Detalle del mantenimiento';

        $('detalleSubtitulo').textContent =
            (item.nombre_equipo || 'Equipo')
            + ' · '
            + (item.fecha_programada_texto || 'Sin programación');

        const teamHtml =
            participants.length > 0
                ? participants.map(
                    renderParticipant
                ).join('')
                : `
                    <div class="masg-detail-empty">
                        No hay participantes activos.
                    </div>
                `;

        $('detalleContenido').innerHTML = `
            <section class="masg-detail-hero">
                <div>
                    <div class="masg-card__badges">
                        ${badgeType(item.tipo_solicitud)}
                        ${badgePriority(item.prioridad)}
                        ${badgeState(item.estado_solicitud)}
                    </div>

                    <h3>
                        ${escapeHtml(item.nombre_equipo || 'Equipo')}
                    </h3>

                    <p>
                        ${escapeHtml(item.codigo_equipo || 'Sin código')}
                        ·
                        ${escapeHtml(item.departamento || 'Sin departamento')}
                        /
                        ${escapeHtml(item.area || 'Sin área')}
                        /
                        ${escapeHtml(item.proceso || 'Sin proceso')}
                    </p>
                </div>

                <div class="masg-detail-date">
                    <strong>
                        ${escapeHtml(item.fecha_relativa || 'Sin fecha')}
                    </strong>

                    <span>
                        ${escapeHtml(item.fecha_programada_texto || 'Sin programación')}
                    </span>
                </div>
            </section>

            <section class="masg-detail-grid">
                ${detailCell(
                    'Solicitante',
                    item.solicitante || 'Sin solicitante',
                    item.solicitante_contacto || 'Sin datos de contacto'
                )}

                ${detailCell(
                    'Solicitud registrada',
                    item.fecha_solicitud_texto || 'Sin fecha',
                    item.hora_solicitud_texto || ''
                )}

                ${detailCell(
                    'Programación',
                    item.fecha_programada_texto || 'Sin programación',
                    'Fecha límite: '
                        + (
                            item.fecha_limite_texto
                            || 'Sin fecha límite'
                        )
                )}

                ${detailCell(
                    'Mi asignación',
                    labelAssignmentState(
                        item.estado_asignacion
                    ),
                    item.fecha_asignacion_texto
                        ? 'Asignado: '
                            + item.fecha_asignacion_texto
                        : 'Sin fecha de asignación'
                )}

                ${detailCell(
                    'Riesgo',
                    labelRisk(item.nivel_riesgo),
                    Number(item.trabajo_peligroso) === 1
                        ? (item.detalle_trabajo_peligroso || 'Trabajo peligroso; revisa las condiciones de seguridad.')
                        : 'Trabajo no marcado como peligroso'
                )}

                ${detailCell(
                    'Paro del equipo',
                    Number(item.requiere_paro_equipo) === 1
                        ? 'Sí requerido'
                        : 'No requerido',
                    Number(item.alerta_nocturna_peligrosa) === 1
                        ? 'Advertencia por turno nocturno'
                        : 'Sin advertencia nocturna'
                )}

                ${detailCell(
                    'Calendario laboral',
                    labelCalendarDay(
                        item.tipo_dia_calendario,
                        item.es_laboral_calendario
                    ),
                    item.observacion_calendario
                        || 'Sin observaciones'
                )}

                ${detailCell(
                    'Seguridad nocturna',
                    Number(item.alerta_riesgo_nocturno) === 1
                        ? (
                            Number(item.riesgo_nocturno_confirmado) === 1
                                ? 'Confirmada por administración'
                                : 'Pendiente de confirmación'
                        )
                        : 'No aplica',
                    item.observacion_riesgo_nocturno
                        || 'Sin observaciones'
                )}
            </section>

            ${detailText(
                'Trabajo solicitado',
                item.descripcion_solicitud
            )}

            ${detailText(
                'Falla o condición reportada',
                combineText([
                    item.tipo_falla
                        ? 'Tipo: ' + item.tipo_falla
                        : '',
                    item.causa_averia
                        ? 'Causa: ' + item.causa_averia
                        : '',
                    item.descripcion_falla || '',
                    item.causa_desconocida_descripcion || ''
                ])
            )}

            ${detailText(
                'Impacto en la operación',
                item.impacto_operacion
            )}

            ${detailText(
                'Objetivo y resultado esperado',
                combineText([
                    item.objetivo_mejora || '',
                    item.resultado_esperado || '',
                    item.justificacion_mejora || ''
                ])
            )}

            ${detailText(
                'Costo contra beneficio',
                item.costo_vs_beneficio
            )}

            ${detailText(
                'Observaciones del solicitante',
                item.observaciones_solicitante
            )}

            ${renderRecommendedResources(item)}

            <section class="masg-team">
                <header>
                    <div>
                        <h3>Equipo asignado</h3>

                        <p>
                            Cada técnico registra su propio tiempo.
                        </p>
                    </div>

                    <span>
                        ${participants.length}
                        ${
                            participants.length === 1
                                ? 'participante'
                                : 'participantes'
                        }
                    </span>
                </header>

                <div class="masg-team__list">
                    ${teamHtml}
                </div>
            </section>
        `;

        configureDetailAction(item);
    }

    function renderParticipant(item) {
        return `
            <article class="masg-participant">
                <span class="masg-participant__avatar">
                    ${escapeHtml(
                        initials(item.tecnico)
                    )}
                </span>

                <div>
                    <strong>
                        ${escapeHtml(item.tecnico || 'Técnico')}
                    </strong>

                    <small>
                        ${escapeHtml(item.especialidad || 'Sin especialidad')}
                        ·
                        ${escapeHtml(labelShift(item.turno))}
                    </small>
                </div>

                <div class="masg-participant__state">
                    <b>
                        ${escapeHtml(
                            labelAssignmentState(
                                item.estado_asignacion
                            )
                        )}
                    </b>

                    <small>
                        ${escapeHtml(
                            item.fecha_inicio_texto
                            || 'Sin iniciar'
                        )}
                    </small>
                </div>
            </article>
        `;
    }

    function configureDetailAction(item) {
        const button =
            $('btnDetallePrincipal');

        button.hidden = false;
        button.disabled = false;
        button.dataset.assignment =
            String(item.asignacion_id || '');

        if (
            item.accion_principal === 'ABRIR'
            && item.ejecucion_id
        ) {
            button.textContent =
                item.estado_ejecucion === 'PAUSADA'
                    ? 'Revisar actividad pausada'
                    : 'Abrir actividad';

            return;
        }

        if (Number(item.puede_iniciar) === 1) {
            button.textContent =
                item.texto_accion
                || 'Iniciar mantenimiento';

            return;
        }

        button.textContent =
            item.texto_accion
            || 'No disponible';

        button.disabled = true;
    }

    function openStart(item) {
        if (
            !item
            || Number(item.puede_iniciar) !== 1
        ) {
            UI.advertencia(
                'No disponible para iniciar',
                item && item.motivo_bloqueo
                    ? item.motivo_bloqueo
                    : 'Actualiza la información e inténtalo nuevamente.'
            );

            return;
        }

        state.selected = item;

        $('formIniciar').reset();

        $('asignacionIniciar').value =
            String(item.asignacion_id);

        $('iniciarTitulo').textContent =
            item.texto_accion
            || 'Iniciar mantenimiento';

        $('resumenInicio').innerHTML = `
            <div>
                <span>Folio</span>
                <strong>${escapeHtml(item.folio || 'Sin folio')}</strong>
            </div>

            <div>
                <span>Equipo</span>
                <strong>
                    ${escapeHtml(item.codigo_equipo || 'Sin código')}
                    ·
                    ${escapeHtml(item.nombre_equipo || 'Sin nombre')}
                </strong>
            </div>

            <div>
                <span>Programación</span>
                <strong>
                    ${escapeHtml(item.fecha_programada_texto || 'Sin fecha')}
                </strong>
            </div>

            <div>
                <span>Ubicación</span>
                <strong>
                    ${escapeHtml(item.area || 'Sin área')}
                    ·
                    ${escapeHtml(item.proceso || 'Sin proceso')}
                </strong>
            </div>

            <div class="masg-start-summary__wide">
                <span>Recursos recomendados</span>
                <strong>${escapeHtml(renderStartResources(item))}</strong>
            </div>
        `;

        $('avisoNocturnoPeligroso').hidden =
            Number(
                item.alerta_nocturna_peligrosa || 0
            ) !== 1;

        $('avisoUnirseEquipo').hidden =
            Number(
                item.equipo_trabajando || 0
            ) !== 1;

        $('btnConfirmarInicio').textContent =
            Number(item.equipo_para_unirse || 0) === 1
                ? 'Unirme al mantenimiento'
                : 'Confirmar inicio';

        openModal('modalIniciar');
    }

    async function submitStart(event) {
        event.preventDefault();

        if (
            state.processing
            || !state.selected
        ) {
            return;
        }

        const assignmentId =
            Number(
                $('asignacionIniciar').value || 0
            );

        if (
            assignmentId <= 0
            || assignmentId !== Number(
                state.selected.asignacion_id
            )
        ) {
            await UI.error(
                'Asignación no válida',
                'Cierra la ventana, actualiza la lista e inténtalo nuevamente.'
            );

            return;
        }

        state.processing = true;

        const button =
            $('btnConfirmarInicio');

        UI.estadoBoton(
            button,
            true,
            Number(
                state.selected.equipo_para_unirse || 0
            ) === 1
                ? 'Registrando participación...'
                : 'Iniciando...'
        );

        try {
            /*
             * Se crea FormData manualmente. No se utiliza event.currentTarget
             * después de una operación asíncrona, evitando el error:
             * “parameter 1 is not of type HTMLFormElement”.
             */
            const formData = new FormData();

            formData.set(
                'accion',
                'iniciar'
            );

            formData.set(
                'csrf_token',
                CSRF_TOKEN
            );

            formData.set(
                'asignacion_id',
                String(assignmentId)
            );

            const response =
                await UI.peticionJson(
                    API,
                    {
                        method: 'POST',
                        body: formData
                    }
                );

            closeModal('modalIniciar');

            await UI.exito(
                'Mantenimiento iniciado',
                response.mensaje
                || 'Tu tiempo de ejecución comenzó correctamente.'
            );

            const executionId =
                Number(
                    response.ejecucion_id || 0
                );

            if (executionId > 0) {
                openExecution(executionId);
                return;
            }

            window.location.assign(
                'mantenimiento_activo.php'
            );
        } catch (error) {
            console.error(error);

            if (!errorHandled(error)) {
                await UI.error(
                    'No se pudo iniciar',
                    error.message
                    || 'Actualiza la información e inténtalo nuevamente.'
                );
            }

            await loadModule(null, true);
        } finally {
            state.processing = false;

            UI.estadoBoton(
                button,
                false
            );
        }
    }

    function openExecution(executionId) {
        if (!executionId || executionId <= 0) {
            window.location.assign(
                'mantenimiento_activo.php'
            );

            return;
        }

        window.location.assign(
            'mantenimiento_activo.php?ejecucion_id='
            + encodeURIComponent(executionId)
        );
    }

    function openModal(id) {
        const modal = $(id);

        if (!modal) {
            return;
        }

        modal.classList.add('is-open');
        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'masg-modal-open'
        );
    }

    function closeModal(id) {
        const modal = $(id);

        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        if (
            !document.querySelector(
                '.masg-modal.is-open'
            )
        ) {
            document.body.classList.remove(
                'masg-modal-open'
            );
        }

        if (id === 'modalIniciar') {
            state.selected = null;
        }

        if (id === 'modalDetalle') {
            state.detail = null;
        }
    }

    function showStatus(message, type) {
        const element =
            $('estadoCarga');

        element.textContent =
            message || '';

        element.className =
            'masg-status';

        if (type) {
            element.classList.add(
                'masg-status--' + type
            );
        }
    }

    function detailCell(label, value, small) {
        return `
            <article>
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value || 'Sin registro')}</strong>
                <small>${escapeHtml(small || '')}</small>
            </article>
        `;
    }

    function detailText(title, text) {
        const value =
            String(text || '').trim();

        if (value === '') {
            return '';
        }

        return `
            <section class="masg-detail-text">
                <h3>${escapeHtml(title)}</h3>
                <p>${escapeHtml(value)}</p>
            </section>
        `;
    }

    function combineText(values) {
        return values
            .map(function (value) {
                return String(value || '').trim();
            })
            .filter(Boolean)
            .join('\n\n');
    }

    function badgeType(value) {
        return `
            <span class="masg-badge masg-badge--type">
                ${escapeHtml(labelType(value))}
            </span>
        `;
    }

    function badgePriority(value) {
        return `
            <span class="masg-badge masg-badge--priority-${escapeAttribute(
                String(value || 'MEDIA').toLowerCase()
            )}">
                ${escapeHtml(labelPriority(value))}
            </span>
        `;
    }

    function badgeState(value) {
        return `
            <span class="masg-badge masg-badge--state-${escapeAttribute(
                String(value || '').toLowerCase()
            )}">
                ${escapeHtml(labelRequestState(value))}
            </span>
        `;
    }

    function labelType(value) {
        return {
            CORRECTIVO_PROGRAMABLE:
                'Correctivo programable',
            MODIFICACION_MEJORA:
                'Modificación o mejora',
            CORRECTIVO_URGENTE:
                'Correctivo urgente',
            RUTINARIO:
                'Rutinario'
        }[value] || 'Mantenimiento';
    }

    function labelPriority(value) {
        return {
            URGENTE: 'Urgente',
            ALTA: 'Alta',
            MEDIA: 'Media',
            BAJA: 'Baja'
        }[value] || 'Media';
    }

    function labelRequestState(value) {
        return {
            APROBADO: 'Aprobado',
            AGENDADO: 'Agendado',
            ATRASADO: 'Atrasado',
            EN_PROCESO: 'En proceso',
            PAUSADO: 'Pausado',
            TERMINADO: 'Terminado',
            CANCELADO: 'Cancelado'
        }[value] || String(value || 'Sin estado');
    }

    function labelAssignmentState(value) {
        return {
            ASIGNADO: 'Asignado',
            ACEPTADO: 'Aceptado',
            EN_PROCESO: 'En proceso',
            PAUSADO: 'Pausado',
            TERMINADO: 'Terminado',
            NO_PARTICIPO: 'No participó',
            RETIRADO: 'Retirado'
        }[value] || String(value || 'Sin estado');
    }

    function labelProgramState(value) {
        return {
            PROGRAMADA: 'Programada',
            VENCIDA: 'Vencida',
            CUMPLIDA: 'Cumplida',
            REPROGRAMADA: 'Reprogramada',
            CANCELADA: 'Cancelada'
        }[value] || 'Sin programación';
    }

    function labelCalendarDay(type, enabled) {
        if (String(type || '') === 'HABIL_EXTRA') {
            return 'Día hábil extraordinario';
        }

        if (
            String(type || '') === 'INHABIL'
            || Number(enabled) === 0
        ) {
            return 'Día inhábil';
        }

        if (String(type || '') === 'HABIL') {
            return 'Día hábil';
        }

        return 'Sin registro de calendario';
    }

    function labelRisk(value) {
        return {
            ALTO: 'Riesgo alto',
            MEDIO: 'Riesgo medio',
            BAJO: 'Riesgo bajo'
        }[value] || 'Sin clasificación';
    }

    function labelShift(value) {
        return {
            MATUTINO: 'Matutino',
            VESPERTINO: 'Vespertino',
            NOCTURNO: 'Nocturno'
        }[value] || 'Sin turno';
    }

    function initials(name) {
        const parts =
            String(name || 'T')
                .trim()
                .split(/\s+/)
                .filter(Boolean);

        return parts
            .slice(0, 2)
            .map(function (part) {
                return part.charAt(0).toUpperCase();
            })
            .join('');
    }

    function normalizeText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(
                /[\u0300-\u036f]/g,
                ''
            )
            .toLowerCase()
            .trim();
    }

    function setText(id, value) {
        const element = $(id);

        if (element) {
            element.textContent =
                String(value);
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeAttribute(value) {
        return escapeHtml(value)
            .replace(/`/g, '&#096;');
    }

    function errorHandled(error) {
        if (!error || !error.datos) {
            return false;
        }

        if (
            error.datos.sesion_expirada 
            && error.datos.redirect
        ) {
            window.location.assign(
                error.datos.redirect
            );

            return true;
        }

        if (error.datos.csrf_invalido) {
            window.location.reload();
            return true;
        }

        return false;
    }
})();
</script>

</body>
</html>