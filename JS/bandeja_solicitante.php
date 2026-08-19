<?php

declare(strict_types=1);

/*
 * La página funciona también como punto de entrada al endpoint interno.
 * Esto evita rutas relativas frágiles hacia la carpeta funciones.
 */
if (isset($_GET['bs_api'])) {
    $endpoint = __DIR__ . '/../funciones/bandeja_solicitante_funciones.php';

    if (!is_file($endpoint)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode(
            [
                'success' => false,
                'mensaje' => 'No se encontró funciones/bandeja_solicitante_funciones.php. Copia juntos los tres archivos del módulo.',
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    require $endpoint;
    exit;
}

require_once __DIR__ . '/../inc/seguridad.php';
sm_requerir_sesion(['SOLICITANTE'], false);

if (!function_exists('bs_vista_e')) {
    function bs_vista_e($valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}

$nombreSesion = trim((string) (
    $_SESSION['nombre']
    ?? $_SESSION['nombre_completo']
    ?? $_SESSION['usuario']
    ?? 'Solicitante'
));

if ($nombreSesion === '') {
    $nombreSesion = 'Solicitante';
}

$cssBandejaSolicitante = __DIR__ . '/../css/style_bandeja_solicitante.css';
$versionCss = is_file($cssBandejaSolicitante)
    ? (string) filemtime($cssBandejaSolicitante)
    : (string) time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, viewport-fit=cover"
    >
    <meta name="theme-color" content="#0b2b47">
    <meta name="robots" content="noindex, nofollow">
    <meta
        name="description"
        content="Bandeja personal de seguimiento de solicitudes de mantenimiento"
    >
    <title>Mis solicitudes | Sistema de Mantenimiento</title>
    <link
        rel="preload"
        href="../imagenes/herramienta_abajo.png"
        as="image"
    >
    <link
        rel="stylesheet"
        href="../css/style_bandeja_solicitante.css?v=<?= bs_vista_e($versionCss) ?>"
    >
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="bs-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="bs-icon-sparkles" viewBox="0 0 24 24">
        <path d="m12 3-1.2 3.8L7 8l3.8 1.2L12 13l1.2-3.8L17 8l-3.8-1.2L12 3Z"/>
        <path d="m5 15-.7 2.3L2 18l2.3.7L5 21l.7-2.3L8 18l-2.3-.7L5 15Z"/>
        <path d="m19 13-.7 2.3-2.3.7 2.3.7L19 19l.7-2.3 2.3-.7L19 13Z"/>
    </symbol>
    <symbol id="bs-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="bs-icon-plus" viewBox="0 0 24 24">
        <path d="M12 5v14M5 12h14"/>
    </symbol>
    <symbol id="bs-icon-inbox" viewBox="0 0 24 24">
        <path d="M4 5h16v14H4z"/>
        <path d="M4 14h4l2 3h4l2-3h4M8 9h8"/>
    </symbol>
    <symbol id="bs-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="bs-icon-filter" viewBox="0 0 24 24">
        <path d="M4 5h16M7 12h10M10 19h4"/>
    </symbol>
    <symbol id="bs-icon-list" viewBox="0 0 24 24">
        <path d="M9 6h11M9 12h11M9 18h11"/>
        <circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>
    </symbol>
    <symbol id="bs-icon-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </symbol>
    <symbol id="bs-icon-progress" viewBox="0 0 24 24">
        <path d="M4 12h4l2-5 4 10 2-5h4"/>
    </symbol>
    <symbol id="bs-icon-check" viewBox="0 0 24 24">
        <path d="m5 12 4 4L19 6"/>
    </symbol>
    <symbol id="bs-icon-shield" viewBox="0 0 24 24">
        <path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="bs-icon-calendar" viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path d="M8 3v4M16 3v4M3 10h18"/>
    </symbol>
    <symbol id="bs-icon-eye" viewBox="0 0 24 24">
        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
        <circle cx="12" cy="12" r="2.5"/>
    </symbol>
    <symbol id="bs-icon-history" viewBox="0 0 24 24">
        <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>
        <path d="M3 3v5h5M12 7v5l3 2"/>
    </symbol>
    <symbol id="bs-icon-user" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="4"/>
        <path d="M4 21a8 8 0 0 1 16 0"/>
    </symbol>
    <symbol id="bs-icon-database" viewBox="0 0 24 24">
        <ellipse cx="12" cy="5" rx="8" ry="3"/>
        <path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>
    </symbol>
    <symbol id="bs-icon-close" viewBox="0 0 24 24">
        <path d="m6 6 12 12M18 6 6 18"/>
    </symbol>
    <symbol id="bs-icon-arrow" viewBox="0 0 24 24">
        <path d="M5 12h14M14 7l5 5-5 5"/>
    </symbol>
</svg>

<main class="bs-page">
    <div class="bs-ambient bs-ambient--one" aria-hidden="true"></div>
    <div class="bs-ambient bs-ambient--two" aria-hidden="true"></div>

    <section class="bs-hero" aria-labelledby="tituloBandejaSolicitante">
        <div class="bs-hero__content">
            <div class="bs-hero__copy">
                <p class="bs-eyebrow bs-eyebrow--hero">
                    <span class="bs-eyebrow__icon" aria-hidden="true">
                        <svg><use href="#bs-icon-sparkles"></use></svg>
                    </span>
                    Seguimiento personal
                </p>

                <h1 id="tituloBandejaSolicitante">Mis solicitudes</h1>

                <p class="bs-hero__description">
                    Consulta el estado, la programación, el personal asignado y el
                    resultado de cada reporte que has enviado.
                </p>

                <div class="bs-hero__meta">
                    <span>
                        <span class="bs-live-dot" aria-hidden="true"></span>
                        Bandeja protegida
                    </span>
                    <span>
                        Solicitante:
                        <strong><?= bs_vista_e($nombreSesion) ?></strong>
                    </span>
                </div>
            </div>

            <div class="bs-hero__aside">
                <div class="bs-hero__mini-card">
                    <span class="bs-hero__mini-icon" aria-hidden="true">
                        <svg><use href="#bs-icon-inbox"></use></svg>
                    </span>
                    <div>
                        <small>Bandeja personal</small>
                        <strong>Seguimiento completo de tus reportes</strong>
                    </div>
                </div>

                <div class="bs-hero__actions">
                    <a
                        href="dashboard_solicitante.php"
                        class="bs-button bs-button--hero-secondary"
                    >
                        <svg aria-hidden="true"><use href="#bs-icon-plus"></use></svg>
                        Nueva solicitud
                    </a>

                    <button
                        type="button"
                        class="bs-button bs-button--hero-primary"
                        id="btnActualizar"
                    >
                        <svg aria-hidden="true"><use href="#bs-icon-refresh"></use></svg>
                        Actualizar
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="bs-guides" aria-label="Guía para consultar solicitudes">
        <article>
            <span aria-hidden="true">
                <svg><use href="#bs-icon-search"></use></svg>
            </span>
            <div>
                <strong>Localiza cualquier reporte</strong>
                <p>
                    Busca por folio, equipo o descripción y combina los filtros
                    de estado y tipo.
                </p>
            </div>
        </article>

        <article>
            <span aria-hidden="true">
                <svg><use href="#bs-icon-progress"></use></svg>
            </span>
            <div>
                <strong>Comprende el avance</strong>
                <p>
                    Revisa la etapa actual, programación, técnicos asignados y
                    movimientos recientes.
                </p>
            </div>
        </article>

        <article>
            <span aria-hidden="true">
                <svg><use href="#bs-icon-shield"></use></svg>
            </span>
            <div>
                <strong>Información privada</strong>
                <p>
                    La bandeja muestra únicamente solicitudes registradas desde
                    tu propia cuenta.
                </p>
            </div>
        </article>
    </section>

    <div
        class="bs-message"
        id="mensajePagina"
        role="alert"
        aria-live="assertive"
        hidden
    ></div>

    <section class="bs-summary" aria-label="Resumen de tus solicitudes">
        <article class="bs-summary-card bs-summary-card--total">
            <span class="bs-summary-card__icon" aria-hidden="true">
                <svg><use href="#bs-icon-list"></use></svg>
            </span>
            <div>
                <span>Total registrado</span>
                <strong id="resumenTotal">0</strong>
                <small>Todas tus solicitudes activas e históricas</small>
            </div>
        </article>

        <article class="bs-summary-card bs-summary-card--review">
            <span class="bs-summary-card__icon" aria-hidden="true">
                <svg><use href="#bs-icon-clock"></use></svg>
            </span>
            <div>
                <span>En revisión</span>
                <strong id="resumenRevision">0</strong>
                <small>Pendientes de validación administrativa</small>
            </div>
        </article>

        <article class="bs-summary-card bs-summary-card--progress">
            <span class="bs-summary-card__icon" aria-hidden="true">
                <svg><use href="#bs-icon-progress"></use></svg>
            </span>
            <div>
                <span>En seguimiento</span>
                <strong id="resumenSeguimiento">0</strong>
                <small>Aprobadas, programadas o en atención</small>
            </div>
        </article>

        <article class="bs-summary-card bs-summary-card--done">
            <span class="bs-summary-card__icon" aria-hidden="true">
                <svg><use href="#bs-icon-check"></use></svg>
            </span>
            <div>
                <span>Terminadas</span>
                <strong id="resumenTerminadas">0</strong>
                <small>Con resultado de mantenimiento registrado</small>
            </div>
        </article>
    </section>

    <section
        class="bs-panel bs-filter-panel"
        aria-labelledby="tituloFiltrosSolicitante"
    >
        <header class="bs-panel-heading">
            <div class="bs-panel-heading__identity">
                <span class="bs-panel-heading__icon" aria-hidden="true">
                    <svg><use href="#bs-icon-filter"></use></svg>
                </span>
                <div>
                    <p class="bs-eyebrow">Búsqueda y filtros</p>
                    <h2 id="tituloFiltrosSolicitante">Encuentra una solicitud</h2>
                    <p>
                        Combina los criterios para consultar exactamente el reporte
                        que necesitas.
                    </p>
                </div>
            </div>

            <button type="button" class="bs-link-button" id="btnLimpiar">
                Limpiar filtros
            </button>
        </header>

        <form id="formFiltros" class="bs-filters" novalidate>
            <label class="bs-field bs-field--search">
                <span>Buscar</span>
                <div class="bs-search-box">
                    <svg aria-hidden="true"><use href="#bs-icon-search"></use></svg>
                    <input
                        type="search"
                        id="buscar"
                        maxlength="120"
                        autocomplete="off"
                        placeholder="Folio, equipo o descripción"
                    >
                </div>
            </label>

            <label class="bs-field">
                <span>Estado</span>
                <select id="estado">
                    <option value="">Todos los estados</option>
                    <option value="PENDIENTE">En revisión</option>
                    <option value="APROBADO">Aprobada</option>
                    <option value="AGENDADO">Agendada</option>
                    <option value="EN_PROCESO">En proceso</option>
                    <option value="PAUSADO">Pausada</option>
                    <option value="ATRASADO">Atrasada</option>
                    <option value="TERMINADO">Terminada</option>
                    <option value="RECHAZADO">Rechazada</option>
                    <option value="CANCELADO">Cancelada</option>
                </select>
            </label>

            <label class="bs-field">
                <span>Tipo</span>
                <select id="tipo">
                    <option value="">Todos los tipos</option>
                    <option value="CORRECTIVO_PROGRAMABLE">Correctivo programable</option>
                    <option value="MODIFICACION_MEJORA">Modificación o mejora</option>
                    <option value="CORRECTIVO_URGENTE">Correctivo urgente</option>
                </select>
            </label>

            <label class="bs-field bs-field--amount">
                <span>Mostrar</span>
                <select id="porPagina">
                    <option value="10">10 por página</option>
                    <option value="20">20 por página</option>
                    <option value="40">40 por página</option>
                </select>
            </label>

            <button
                type="submit"
                class="bs-button bs-button--primary bs-filter-submit"
                id="btnAplicar"
            >
                <svg aria-hidden="true"><use href="#bs-icon-search"></use></svg>
                Aplicar filtros
            </button>
        </form>
    </section>

    <section
        class="bs-panel bs-list-panel"
        aria-labelledby="tituloListadoSolicitudes"
    >
        <header class="bs-list-header">
            <div class="bs-panel-heading__identity">
                <span class="bs-panel-heading__icon bs-panel-heading__icon--list" aria-hidden="true">
                    <svg><use href="#bs-icon-inbox"></use></svg>
                </span>
                <div>
                    <p class="bs-eyebrow">Historial personal</p>
                    <h2 id="tituloListadoSolicitudes">Solicitudes registradas</h2>
                    <p id="textoResultados">Consultando información...</p>
                </div>
            </div>

            <div class="bs-list-header__meta">
                <span class="bs-server-chip">
                    <svg aria-hidden="true"><use href="#bs-icon-database"></use></svg>
                    Paginación del servidor
                </span>
                <span class="bs-updated" id="ultimaActualizacion">
                    Sin actualizar
                </span>
            </div>
        </header>

        <div class="bs-loading" id="estadoCarga" role="status" aria-live="polite">
            <span class="bs-spinner" aria-hidden="true"></span>
            <div>
                <strong>Cargando solicitudes...</strong>
                <span>Estamos preparando tu historial personal.</span>
            </div>
        </div>

        <div class="bs-empty" id="estadoVacio" hidden>
            <span class="bs-empty__icon" aria-hidden="true">
                <svg><use href="#bs-icon-search"></use></svg>
            </span>
            <h3>No encontramos solicitudes</h3>
            <p id="textoVacio">
                Prueba con otros filtros o registra una nueva solicitud.
            </p>
            <a href="dashboard_solicitante.php">
                <svg aria-hidden="true"><use href="#bs-icon-plus"></use></svg>
                Ir a nueva solicitud
            </a>
        </div>

        <div class="bs-table-wrap" id="contenedorTabla" hidden>
            <table class="bs-table">
                <thead>
                    <tr>
                        <th>Solicitud</th>
                        <th>Equipo y ubicación</th>
                        <th>Enviada</th>
                        <th>Seguimiento</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="tablaSolicitudes"></tbody>
            </table>
        </div>

        <footer class="bs-pagination-footer" id="piePaginacion" hidden>
            <p id="textoPaginacion">0 resultados</p>
            <nav
                class="bs-pagination"
                id="paginacion"
                aria-label="Paginación de solicitudes"
            ></nav>
        </footer>
    </section>

    <footer class="bs-footer">
        <span>
            La información mostrada corresponde únicamente a solicitudes de tu cuenta.
        </span>
        <span>Consulta de solo lectura</span>
    </footer>

    <div class="bs-tools-background" aria-hidden="true"></div>
</main>

<div class="bs-modal" id="modalDetalle" aria-hidden="true" hidden>
    <div class="bs-modal__backdrop" data-close-modal></div>

    <section
        class="bs-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloDetalleSolicitud"
    >
        <header class="bs-modal__header">
            <div class="bs-modal__header-copy">
                <p class="bs-eyebrow">Detalle de solicitud</p>
                <h2 id="tituloDetalleSolicitud">Consultando...</h2>
                <p id="subtituloDetalleSolicitud">Espera un momento.</p>
            </div>

            <button
                type="button"
                class="bs-modal__close"
                id="btnCerrarModal"
                aria-label="Cerrar detalle"
            >
                <svg aria-hidden="true"><use href="#bs-icon-close"></use></svg>
            </button>
        </header>

        <div class="bs-modal__body">
            <div class="bs-detail-loading" id="detalleCarga" role="status">
                <span class="bs-spinner" aria-hidden="true"></span>
                <strong>Cargando seguimiento...</strong>
                <small>Estamos consultando la información completa.</small>
            </div>

            <div class="bs-detail-error" id="detalleError" hidden>
                <span aria-hidden="true">!</span>
                <strong>No se pudo abrir la solicitud</strong>
                <p id="detalleErrorTexto">
                    Actualiza la página e inténtalo nuevamente.
                </p>
            </div>

            <div id="detalleContenido" hidden>
                <section
                    class="bs-current-status"
                    id="estadoActualDetalle"
                ></section>

                <section class="bs-stage-wrap">
                    <header>
                        <div>
                            <span>Ruta de seguimiento</span>
                            <strong>Etapas de la solicitud</strong>
                        </div>
                    </header>
                    <ol
                        class="bs-steps"
                        id="etapasDetalle"
                        aria-label="Etapas de la solicitud"
                    ></ol>
                </section>

                <section class="bs-detail-section">
                    <header class="bs-detail-section__head">
                        <span aria-hidden="true">
                            <svg><use href="#bs-icon-list"></use></svg>
                        </span>
                        <div>
                            <h3>Datos del reporte</h3>
                            <p>Información enviada al registrar la solicitud.</p>
                        </div>
                    </header>
                    <div class="bs-info-grid" id="datosReporte"></div>
                    <div class="bs-text-block" id="descripcionReporte"></div>
                    <div id="datosAdicionales"></div>
                </section>

                <section class="bs-detail-section" id="seccionProgramacion">
                    <header class="bs-detail-section__head">
                        <span aria-hidden="true">
                            <svg><use href="#bs-icon-calendar"></use></svg>
                        </span>
                        <div>
                            <h3>Programación y atención</h3>
                            <p>Fechas, tiempos y personal técnico relacionado.</p>
                        </div>
                    </header>
                    <div class="bs-info-grid" id="datosProgramacion"></div>
                    <div id="notasProgramacion"></div>
                    <div class="bs-technicians" id="listaTecnicos"></div>
                </section>

                <section class="bs-detail-section" id="seccionResultado">
                    <header class="bs-detail-section__head">
                        <span aria-hidden="true">
                            <svg><use href="#bs-icon-check"></use></svg>
                        </span>
                        <div>
                            <h3>Resultado del mantenimiento</h3>
                            <p>Información registrada al cerrar el trabajo.</p>
                        </div>
                    </header>
                    <div id="datosResultado"></div>
                </section>

                <section class="bs-detail-section">
                    <header class="bs-detail-section__head">
                        <span aria-hidden="true">
                            <svg><use href="#bs-icon-history"></use></svg>
                        </span>
                        <div>
                            <h3>Historial de seguimiento</h3>
                            <p>Se muestran los movimientos más recientes.</p>
                        </div>
                    </header>
                    <div class="bs-timeline" id="historialSolicitud"></div>
                </section>
            </div>
        </div>

        <footer class="bs-modal__footer">
            <span>
                <svg aria-hidden="true"><use href="#bs-icon-shield"></use></svg>
                Información de consulta
            </span>
            <button
                type="button"
                class="bs-button bs-button--secondary"
                data-close-modal
            >
                Cerrar detalle
            </button>
        </footer>
    </section>
</div>

<script>
(function () {
    'use strict';

    var ENDPOINT = 'bandeja_solicitante.php?bs_api=1';
    var state = {
        page: 1,
        totalPages: 1,
        loading: false,
        detailLoading: false,
        listController: null,
        detailController: null,
        lastTrigger: null
    };
    var el = {};

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        cacheElements();
        bindEvents();
        loadList(1);
    }

    function cacheElements() {
        el.form = document.getElementById('formFiltros');
        el.search = document.getElementById('buscar');
        el.status = document.getElementById('estado');
        el.type = document.getElementById('tipo');
        el.perPage = document.getElementById('porPagina');
        el.refresh = document.getElementById('btnActualizar');
        el.clear = document.getElementById('btnLimpiar');
        el.apply = document.getElementById('btnAplicar');
        el.message = document.getElementById('mensajePagina');
        el.loading = document.getElementById('estadoCarga');
        el.empty = document.getElementById('estadoVacio');
        el.emptyText = document.getElementById('textoVacio');
        el.tableWrap = document.getElementById('contenedorTabla');
        el.tbody = document.getElementById('tablaSolicitudes');
        el.resultsText = document.getElementById('textoResultados');
        el.updated = document.getElementById('ultimaActualizacion');
        el.paginationFooter = document.getElementById('piePaginacion');
        el.paginationText = document.getElementById('textoPaginacion');
        el.pagination = document.getElementById('paginacion');
        el.summaryTotal = document.getElementById('resumenTotal');
        el.summaryReview = document.getElementById('resumenRevision');
        el.summaryProgress = document.getElementById('resumenSeguimiento');
        el.summaryDone = document.getElementById('resumenTerminadas');
        el.modal = document.getElementById('modalDetalle');
        el.modalClose = document.getElementById('btnCerrarModal');
        el.detailTitle = document.getElementById('tituloDetalleSolicitud');
        el.detailSubtitle = document.getElementById('subtituloDetalleSolicitud');
        el.detailLoading = document.getElementById('detalleCarga');
        el.detailError = document.getElementById('detalleError');
        el.detailErrorText = document.getElementById('detalleErrorTexto');
        el.detailContent = document.getElementById('detalleContenido');
        el.currentStatus = document.getElementById('estadoActualDetalle');
        el.steps = document.getElementById('etapasDetalle');
        el.reportData = document.getElementById('datosReporte');
        el.reportDescription = document.getElementById('descripcionReporte');
        el.additionalData = document.getElementById('datosAdicionales');
        el.scheduleSection = document.getElementById('seccionProgramacion');
        el.scheduleData = document.getElementById('datosProgramacion');
        el.scheduleNotes = document.getElementById('notasProgramacion');
        el.technicians = document.getElementById('listaTecnicos');
        el.resultSection = document.getElementById('seccionResultado');
        el.resultData = document.getElementById('datosResultado');
        el.history = document.getElementById('historialSolicitud');
    }

    function bindEvents() {
        el.form.addEventListener('submit', function (event) {
            event.preventDefault();
            loadList(1);
        });

        el.refresh.addEventListener('click', function () {
            loadList(state.page);
        });

        el.clear.addEventListener('click', function () {
            el.form.reset();
            el.perPage.value = '10';
            hideMessage();
            loadList(1);
        });

        el.pagination.addEventListener('click', function (event) {
            var button = event.target.closest('[data-page]');
            if (!button || button.disabled) {
                return;
            }

            var page = Number.parseInt(button.dataset.page, 10);
            if (Number.isInteger(page) && page > 0 && page <= state.totalPages) {
                loadList(page);
                scrollToList();
            }
        });

        el.tbody.addEventListener('click', function (event) {
            var button = event.target.closest('[data-detail-id]');
            if (!button || button.disabled) {
                return;
            }

            var id = Number.parseInt(button.dataset.detailId, 10);
            if (!Number.isInteger(id) || id <= 0) {
                return;
            }

            openDetail(id, button);
        });

        el.modalClose.addEventListener('click', closeDetail);
        el.modal.addEventListener('click', function (event) {
            if (event.target.closest('[data-close-modal]')) {
                closeDetail();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !el.modal.hidden) {
                closeDetail();
            }
        });
    }

    async function loadList(page) {
        if (state.listController) {
            state.listController.abort();
        }

        state.listController = new AbortController();
        state.loading = true;
        state.page = page;
        setListState('loading');
        setListButtons(true);
        hideMessage();

        var params = new URLSearchParams({
            accion: 'LISTAR',
            buscar: text(el.search.value),
            estado: text(el.status.value),
            tipo: text(el.type.value),
            pagina: String(page),
            por_pagina: String(el.perPage.value || '10')
        });

        try {
            var data = await requestJson(ENDPOINT + '&' + params.toString(), {
                signal: state.listController.signal
            });

            state.page = number(data.paginacion && data.paginacion.pagina, 1);
            state.totalPages = number(data.paginacion && data.paginacion.total_paginas, 1);

            renderSummary(data.resumen || {});
            renderList(data.solicitudes || [], data.paginacion || {});
            el.updated.textContent = data.actualizado_en
                ? 'Actualizado ' + data.actualizado_en
                : 'Actualizado ahora';
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error(error);
            setListState('error');
            showMessage(error.message || 'No se pudieron cargar tus solicitudes.', 'error');
        } finally {
            state.loading = false;
            setListButtons(false);
        }
    }

    function renderSummary(summary) {
        el.summaryTotal.textContent = number(summary.total, 0);
        el.summaryReview.textContent = number(summary.revision, 0);
        el.summaryProgress.textContent = number(summary.seguimiento, 0);
        el.summaryDone.textContent = number(summary.terminadas, 0);
    }

    function renderList(items, pagination) {
        el.tbody.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            var hasFilters = text(el.search.value) !== ''
                || text(el.status.value) !== ''
                || text(el.type.value) !== '';

            el.emptyText.textContent = hasFilters
                ? 'No hay solicitudes que coincidan con los filtros seleccionados.'
                : 'Cuando registres una solicitud, aparecerá aquí para que consultes su avance.';
            el.resultsText.textContent = '0 solicitudes encontradas';
            renderPagination(pagination);
            setListState('empty');
            return;
        }

        items.forEach(function (item) {
            var row = document.createElement('tr');
            var description = truncate(item.descripcion, 104);
            var equipment = joinNonEmpty([
                item.codigo_equipo ? item.codigo_equipo + ' · ' + item.equipo : item.equipo,
                joinNonEmpty([item.departamento, item.area, item.proceso], ' / ')
            ], '<br>');
            var sent = joinNonEmpty([item.fecha_solicitud, item.hora_solicitud], ' · ');
            var follow = followUpText(item);

            row.innerHTML =
                '<td data-label="Solicitud">' +
                    '<div class="bs-request-cell">' +
                        '<div class="bs-request-cell__top">' +
                            '<strong>' + escapeHtml(item.folio || 'Sin folio') + '</strong>' +
                            '<span class="bs-priority ' + priorityClass(item.prioridad) + '">' +
                                escapeHtml(priorityLabel(item.prioridad)) +
                            '</span>' +
                        '</div>' +
                        '<span>' + escapeHtml(typeLabel(item.tipo_solicitud)) + '</span>' +
                        '<small title="' + escapeHtml(item.descripcion || '') + '">' +
                            escapeHtml(description || 'Sin descripción') +
                        '</small>' +
                    '</div>' +
                '</td>' +
                '<td data-label="Equipo y ubicación">' +
                    '<div class="bs-location">' + sanitizeBreaks(equipment || 'Sin ubicación') + '</div>' +
                '</td>' +
                '<td data-label="Enviada">' +
                    '<div class="bs-date-cell"><strong>' + escapeHtml(sent || 'Sin fecha') + '</strong>' +
                    '<small>Actualizada ' + escapeHtml(item.actualizacion || 'sin dato') + '</small></div>' +
                '</td>' +
                '<td data-label="Seguimiento">' +
                    '<div class="bs-follow-cell">' +
                        '<span class="bs-status ' + statusClass(item.estado) + '">' +
                            escapeHtml(statusLabel(item.estado)) +
                        '</span>' +
                        '<small>' + escapeHtml(follow) + '</small>' +
                    '</div>' +
                '</td>' +
                '<td data-label="Acción">' +
                    '<button type="button" class="bs-detail-button" data-detail-id="' +
                        escapeHtml(item.id) + '">Ver detalle <span aria-hidden="true">›</span></button>' +
                '</td>';

            el.tbody.appendChild(row);
        });

        var total = number(pagination.total, items.length);
        el.resultsText.textContent = total === 1
            ? '1 solicitud encontrada'
            : total + ' solicitudes encontradas';

        renderPagination(pagination);
        setListState('table');
    }

    function renderPagination(pagination) {
        var total = number(pagination.total, 0);
        var page = number(pagination.pagina, 1);
        var totalPages = number(pagination.total_paginas, 1);
        var from = number(pagination.desde, 0);
        var to = number(pagination.hasta, 0);

        el.pagination.innerHTML = '';
        el.paginationText.textContent = total === 0
            ? '0 resultados'
            : 'Mostrando ' + from + ' a ' + to + ' de ' + total;

        if (total === 0) {
            el.paginationFooter.hidden = true;
            return;
        }

        el.paginationFooter.hidden = false;

        appendPageButton('Anterior', page - 1, page <= 1, false);

        pageRange(page, totalPages).forEach(function (value) {
            if (value === '...') {
                var separator = document.createElement('span');
                separator.className = 'bs-pagination__dots';
                separator.textContent = '…';
                el.pagination.appendChild(separator);
                return;
            }

            appendPageButton(String(value), value, false, value === page);
        });

        appendPageButton('Siguiente', page + 1, page >= totalPages, false);
    }

    function appendPageButton(label, page, disabled, active) {
        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.dataset.page = String(page);
        button.disabled = disabled;
        button.className = active ? 'is-active' : '';
        if (active) {
            button.setAttribute('aria-current', 'page');
        }
        el.pagination.appendChild(button);
    }

    function pageRange(current, total) {
        if (total <= 5) {
            return Array.from({length: total}, function (_, index) {
                return index + 1;
            });
        }

        var values = [1];
        var start = Math.max(2, current - 1);
        var end = Math.min(total - 1, current + 1);

        if (start > 2) {
            values.push('...');
        }

        for (var page = start; page <= end; page += 1) {
            values.push(page);
        }

        if (end < total - 1) {
            values.push('...');
        }

        values.push(total);
        return values;
    }

    async function openDetail(id, trigger) {
        if (state.detailController) {
            state.detailController.abort();
        }

        state.detailController = new AbortController();
        state.detailLoading = true;
        state.lastTrigger = trigger || null;

        el.modal.hidden = false;
        el.modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('bs-modal-open');
        setDetailState('loading');
        el.detailTitle.textContent = 'Consultando...';
        el.detailSubtitle.textContent = 'Cargando información de la solicitud.';
        el.modalClose.focus();

        try {
            var params = new URLSearchParams({
                accion: 'DETALLE',
                id: String(id)
            });
            var data = await requestJson(ENDPOINT + '&' + params.toString(), {
                signal: state.detailController.signal
            });

            renderDetail(data);
            setDetailState('content');
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error(error);
            el.detailErrorText.textContent = error.message || 'Actualiza la página e inténtalo nuevamente.';
            setDetailState('error');
        } finally {
            state.detailLoading = false;
        }
    }

    function closeDetail() {
        if (el.modal.hidden) {
            return;
        }

        if (state.detailController) {
            state.detailController.abort();
            state.detailController = null;
        }

        el.modal.hidden = true;
        el.modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('bs-modal-open');

        if (state.lastTrigger && document.contains(state.lastTrigger)) {
            state.lastTrigger.focus();
        }
    }

    function renderDetail(data) {
        var request = data.solicitud || {};
        var technicians = Array.isArray(data.tecnicos) ? data.tecnicos : [];
        var history = Array.isArray(data.historial) ? data.historial : [];
        var steps = Array.isArray(data.etapas) ? data.etapas : [];

        el.detailTitle.textContent = request.folio || 'Solicitud';
        el.detailSubtitle.textContent = typeLabel(request.tipo_solicitud) + ' · ' + statusLabel(request.estado);
        el.currentStatus.innerHTML = currentStatusHtml(request);
        el.steps.innerHTML = steps.map(stepHtml).join('');

        el.reportData.innerHTML = [
            infoItem('Tipo', typeLabel(request.tipo_solicitud)),
            infoItem('Prioridad', priorityLabel(request.prioridad)),
            infoItem('Enviada', joinNonEmpty([request.fecha_solicitud, request.hora_solicitud], ' · ')),
            infoItem('Equipo', joinNonEmpty([request.codigo_equipo, request.equipo], ' · ')),
            infoItem('Departamento', request.departamento),
            infoItem('Ubicación', joinNonEmpty([request.area, request.proceso], ' / '))
        ].join('');

        el.reportDescription.innerHTML =
            '<span>Descripción</span><p>' + escapeHtml(request.descripcion_solicitud || 'Sin descripción') + '</p>';
        el.additionalData.innerHTML = additionalDataHtml(request);

        var schedule = request.programacion || {};
        var execution = request.ejecucion || {};
        var scheduleItems = [];
        var scheduleNotes = [];

        if (schedule.id) {
            scheduleItems.push(infoItem('Programación', programStatusLabel(schedule.estado)));
        }
        if (schedule.fecha_programada) {
            scheduleItems.push(infoItem('Fecha programada', schedule.fecha_programada));
        }
        if (schedule.fecha_limite) {
            scheduleItems.push(infoItem('Fecha límite', schedule.fecha_limite));
        }
        if (execution.fecha_inicio) {
            scheduleItems.push(infoItem('Inicio real', execution.fecha_inicio));
        }
        if (request.estado === 'TERMINADO' && execution.fecha_fin) {
            scheduleItems.push(infoItem('Fin real', execution.fecha_fin));
        }
        if (number(execution.segundos_activos, 0) > 0) {
            scheduleItems.push(infoItem('Tiempo técnico acumulado', duration(execution.segundos_activos)));
        }
        if (number(execution.segundos_pausa, 0) > 0) {
            scheduleItems.push(infoItem('Pausas acumuladas', duration(execution.segundos_pausa)));
        }

        if (schedule.motivo_reprogramacion) {
            scheduleNotes.push(textSection('Motivo de reprogramación', schedule.motivo_reprogramacion));
        }
        if (schedule.motivo_programacion) {
            scheduleNotes.push(textSection('Nota de programación', schedule.motivo_programacion));
        }

        if (scheduleItems.length === 0 && technicians.length === 0 && scheduleNotes.length === 0) {
            el.scheduleSection.hidden = true;
        } else {
            el.scheduleSection.hidden = false;
            el.scheduleData.innerHTML = scheduleItems.join('');
            el.scheduleNotes.innerHTML = scheduleNotes.join('');
            el.technicians.innerHTML = techniciansHtml(technicians);
        }

        var closure = request.cierre || {};
        if (!closure.id) {
            el.resultSection.hidden = true;
        } else {
            el.resultSection.hidden = false;
            el.resultData.innerHTML = resultHtml(closure);
        }

        el.history.innerHTML = historyHtml(history);
    }

    function currentStatusHtml(request) {
        return '<div class="bs-current-status__icon ' + statusClass(request.estado) + '" aria-hidden="true">' +
                    escapeHtml(statusSymbol(request.estado)) +
                '</div>' +
                '<div><span>Estado actual</span><strong>' + escapeHtml(statusLabel(request.estado)) + '</strong>' +
                '<p>' + escapeHtml(statusExplanation(request.estado, request)) + '</p></div>';
    }

    function stepHtml(step) {
        return '<li class="' + escapeHtml(step.estado || 'pendiente') + '">' +
            '<span aria-hidden="true"></span><small>' + escapeHtml(step.etiqueta || '') + '</small></li>';
    }

    function additionalDataHtml(request) {
        var blocks = [];

        if (request.tipo_falla || request.descripcion_falla) {
            blocks.push(textSection('Falla reportada', joinNonEmpty([
                request.tipo_falla,
                request.descripcion_falla
            ], ' — ')));
        }
        if (request.causa_averia || request.causa_desconocida_descripcion) {
            blocks.push(textSection('Causa indicada', joinNonEmpty([
                request.causa_averia,
                request.causa_desconocida_descripcion
            ], ' — ')));
        }
        if (request.objetivo_mejora) {
            blocks.push(textSection('Objetivo de la mejora', request.objetivo_mejora));
        }
        if (request.resultado_esperado) {
            blocks.push(textSection('Resultado esperado', request.resultado_esperado));
        }
        if (request.justificacion_mejora) {
            blocks.push(textSection('Justificación', request.justificacion_mejora));
        }
        if (request.costo_vs_beneficio) {
            blocks.push(textSection('Costo y beneficio', request.costo_vs_beneficio));
        }
        if (request.impacto_operacion) {
            blocks.push(textSection('Impacto en la operación', request.impacto_operacion));
        }
        if (request.observaciones_solicitante) {
            blocks.push(textSection('Observaciones enviadas', request.observaciones_solicitante));
        }
        if (request.observaciones_revision) {
            blocks.push(textSection('Observaciones de revisión', request.observaciones_revision));
        }
        if (request.motivo_rechazo) {
            blocks.push(textSection('Motivo del rechazo', request.motivo_rechazo, 'warning'));
        }
        const motivoCancelacion = request.programacion && request.programacion.motivo_cancelacion
            ? request.programacion.motivo_cancelacion
            : (request.estado === 'CANCELADO' ? request.motivo_ultima_edicion : '');
        if (motivoCancelacion) {
            blocks.push(textSection('Motivo de cancelación', motivoCancelacion, 'warning'));
        }

        var flags = [];
        if (request.trabajo_peligroso) {
            flags.push('Trabajo marcado con riesgo ' + (request.nivel_riesgo || '')); 
        }
        if (request.requiere_paro_equipo) {
            flags.push('Requiere paro del equipo');
        }
        if (request.fecha_sugerida) {
            flags.push('Fecha sugerida: ' + request.fecha_sugerida);
        }

        if (flags.length > 0) {
            blocks.push('<div class="bs-tags">' + flags.map(function (flag) {
                return '<span>' + escapeHtml(flag) + '</span>';
            }).join('') + '</div>');
        }

        return blocks.join('');
    }

    function techniciansHtml(technicians) {
        if (!technicians.length) {
            return '<div class="bs-inline-empty">Todavía no hay técnicos asignados.</div>';
        }

        return '<div class="bs-technicians__heading">Personal técnico</div>' + technicians.map(function (technician) {
            return '<article class="bs-technician">' +
                '<span class="bs-technician__avatar" aria-hidden="true">' +
                    escapeHtml(initials(technician.nombre)) +
                '</span>' +
                '<div><strong>' + escapeHtml(technician.nombre || 'Técnico') + '</strong>' +
                '<small>' + escapeHtml(joinNonEmpty([
                    technician.especialidad,
                    technician.turno ? 'Turno ' + technician.turno.toLowerCase() : ''
                ], ' · ')) + '</small></div>' +
                '<span class="bs-assignment-state">' + escapeHtml(assignmentLabel(technician.estado)) + '</span>' +
            '</article>';
        }).join('');
    }

    function resultHtml(closure) {
        var html = '<div class="bs-result-head">' +
            '<span class="bs-result-badge">' + escapeHtml(workResultLabel(closure.trabajo_quedo)) + '</span>' +
            '<span>Cerrado ' + escapeHtml(closure.fecha || '') + '</span>' +
        '</div>';

        html += textSection('Trabajo realizado', closure.descripcion_trabajo_realizado || 'Sin descripción de cierre.');

        if (closure.que_falto) {
            html += textSection('Qué quedó pendiente', closure.que_falto, 'warning');
        }
        if (closure.observaciones) {
            html += textSection('Observaciones finales', closure.observaciones);
        }

        html += '<div class="bs-checks">' +
            checkItem('Limpieza realizada', closure.realizo_limpieza_area) +
            checkItem('Área ordenada', closure.area_ordenada_libre_componentes) +
        '</div>';

        return html;
    }

    function historyHtml(history) {
        if (!history.length) {
            return '<div class="bs-inline-empty">No hay movimientos registrados todavía.</div>';
        }

        return history.map(function (item) {
            return '<article class="bs-timeline-item">' +
                '<span class="bs-timeline-item__dot" aria-hidden="true"></span>' +
                '<div><div class="bs-timeline-item__top"><strong>' +
                    escapeHtml(eventLabel(item.evento)) +
                '</strong><time>' + escapeHtml(item.fecha || '') + '</time></div>' +
                '<p>' + escapeHtml(item.descripcion || statusTransition(item)) + '</p></div>' +
            '</article>';
        }).join('');
    }

    function infoItem(label, value) {
        return '<div class="bs-info-item"><span>' + escapeHtml(label) + '</span><strong>' +
            escapeHtml(value || 'Sin dato') + '</strong></div>';
    }

    function textSection(label, value, variant) {
        return '<div class="bs-text-block' + (variant ? ' bs-text-block--' + variant : '') + '">' +
            '<span>' + escapeHtml(label) + '</span><p>' + escapeHtml(value || 'Sin dato') + '</p></div>';
    }

    function checkItem(label, checked) {
        return '<span class="' + (checked ? 'is-ok' : 'is-pending') + '">' +
            '<b aria-hidden="true">' + (checked ? '✓' : '—') + '</b>' + escapeHtml(label) + '</span>';
    }

    function setListState(mode) {
        el.loading.hidden = mode !== 'loading';
        el.empty.hidden = mode !== 'empty';
        el.tableWrap.hidden = mode !== 'table';

        if (mode === 'error') {
            el.loading.hidden = true;
            el.empty.hidden = true;
            el.tableWrap.hidden = true;
            el.paginationFooter.hidden = true;
            el.resultsText.textContent = 'No se pudo cargar la información';
        }
    }

    function setDetailState(mode) {
        el.detailLoading.hidden = mode !== 'loading';
        el.detailError.hidden = mode !== 'error';
        el.detailContent.hidden = mode !== 'content';
    }

    function setListButtons(disabled) {
        el.refresh.disabled = disabled;
        el.apply.disabled = disabled;
        el.clear.disabled = disabled;
    }

    async function requestJson(url, options) {
        var response = await fetch(url, Object.assign({
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }, options || {}));

        var raw = await response.text();
        var data;

        try {
            data = JSON.parse(raw);
        } catch (parseError) {
            console.error('Respuesta no JSON:', raw);
            throw new Error('El servidor devolvió una respuesta no válida. Revisa el registro de PHP.');
        }

        if (data && data.sesion_expirada && data.redirect) {
            window.location.href = data.redirect;
            throw new Error(data.mensaje || 'Tu sesión expiró.');
        }

        if (!response.ok || !data.success) {
            var message = data && data.mensaje
                ? data.mensaje
                : 'No fue posible completar la consulta.';

            if (data && data.referencia) {
                message += ' Referencia: ' + data.referencia + '.';
            }

            throw new Error(message);
        }

        return data;
    }

    function showMessage(message, type) {
        el.message.textContent = message;
        el.message.className = 'bs-message bs-message--' + (type || 'info');
        el.message.hidden = false;
    }

    function hideMessage() {
        el.message.hidden = true;
        el.message.textContent = '';
    }

    function scrollToList() {
        var top = document.querySelector('.bs-list-panel');
        if (top) {
            top.scrollIntoView({behavior: 'smooth', block: 'start'});
        }
    }

    function followUpText(item) {
        switch (item.estado) {
            case 'PENDIENTE':
                return 'Espera revisión administrativa';
            case 'APROBADO':
                return 'Aprobada; falta programar';
            case 'AGENDADO':
                return item.fecha_programada
                    ? 'Programada para ' + item.fecha_programada
                    : 'Ya fue programada';
            case 'EN_PROCESO':
                return 'El personal técnico está trabajando';
            case 'PAUSADO':
                return 'La atención se encuentra pausada';
            case 'ATRASADO':
                return item.fecha_limite
                    ? 'Superó la fecha límite ' + item.fecha_limite
                    : 'Requiere seguimiento';
            case 'TERMINADO':
                return item.fecha_cierre
                    ? 'Finalizada ' + item.fecha_cierre
                    : 'Mantenimiento finalizado';
            case 'RECHAZADO':
                return 'La revisión fue cerrada sin aprobación';
            case 'CANCELADO':
                return 'La solicitud fue cancelada';
            default:
                return 'Consulta el detalle';
        }
    }

    function statusExplanation(status, request) {
        var schedule = request.programacion || {};
        var explanations = {
            PENDIENTE: 'Tu solicitud fue recibida y está esperando revisión administrativa.',
            APROBADO: 'La solicitud fue aprobada y está pendiente de programación.',
            AGENDADO: schedule.fecha_programada
                ? 'El mantenimiento está programado para el ' + schedule.fecha_programada + '.'
                : 'El mantenimiento ya cuenta con programación.',
            EN_PROCESO: 'El personal técnico ya inició la atención.',
            PAUSADO: 'La ejecución fue pausada temporalmente y podrá reanudarse.',
            ATRASADO: 'La fecha límite terminó y el mantenimiento requiere seguimiento o reprogramación.',
            TERMINADO: 'El mantenimiento fue finalizado y cuenta con un cierre registrado.',
            RECHAZADO: 'La solicitud no fue aprobada. Consulta el motivo en los datos del reporte.',
            CANCELADO: 'La solicitud fue cancelada antes de finalizar su ejecución.'
        };

        return explanations[status] || 'Consulta el historial para conocer el avance.';
    }

    function statusLabel(status) {
        var labels = {
            PENDIENTE: 'En revisión',
            APROBADO: 'Aprobada',
            AGENDADO: 'Agendada',
            EN_PROCESO: 'En proceso',
            PAUSADO: 'Pausada',
            ATRASADO: 'Atrasada',
            TERMINADO: 'Terminada',
            RECHAZADO: 'Rechazada',
            CANCELADO: 'Cancelada'
        };
        return labels[status] || status || 'Sin estado';
    }

    function statusClass(status) {
        var classes = {
            PENDIENTE: 'is-review',
            APROBADO: 'is-approved',
            AGENDADO: 'is-scheduled',
            EN_PROCESO: 'is-progress',
            PAUSADO: 'is-paused',
            ATRASADO: 'is-late',
            TERMINADO: 'is-done',
            RECHAZADO: 'is-rejected',
            CANCELADO: 'is-cancelled'
        };
        return classes[status] || 'is-neutral';
    }

    function statusSymbol(status) {
        var symbols = {
            PENDIENTE: '○',
            APROBADO: '✓',
            AGENDADO: '□',
            EN_PROCESO: '▶',
            PAUSADO: 'Ⅱ',
            ATRASADO: '!',
            TERMINADO: '✓',
            RECHAZADO: '×',
            CANCELADO: '—'
        };
        return symbols[status] || '•';
    }

    function typeLabel(type) {
        var labels = {
            CORRECTIVO_PROGRAMABLE: 'Correctivo programable',
            MODIFICACION_MEJORA: 'Modificación o mejora',
            CORRECTIVO_URGENTE: 'Correctivo urgente'
        };
        return labels[type] || type || 'Solicitud';
    }

    function priorityLabel(priority) {
        var labels = {
            BAJA: 'Baja',
            MEDIA: 'Media',
            ALTA: 'Alta',
            URGENTE: 'Urgente'
        };
        return labels[priority] || priority || 'Media';
    }

    function priorityClass(priority) {
        return priority === 'URGENTE' || priority === 'ALTA'
            ? 'is-high'
            : (priority === 'BAJA' ? 'is-low' : 'is-medium');
    }

    function assignmentLabel(status) {
        var labels = {
            ASIGNADO: 'Asignado',
            ACEPTADO: 'Aceptado',
            EN_PROCESO: 'Trabajando',
            PAUSADO: 'Pausado',
            TERMINADO: 'Finalizado',
            NO_PARTICIPO: 'No participó'
        };
        return labels[status] || status || 'Asignado';
    }

    function programStatusLabel(status) {
        var labels = {
            PROGRAMADA: 'Vigente',
            CUMPLIDA: 'Cumplida',
            VENCIDA: 'Vencida',
            REPROGRAMADA: 'Reprogramada',
            CANCELADA: 'Cancelada'
        };
        return labels[status] || status || 'Sin estado';
    }

    function workResultLabel(result) {
        var labels = {
            TERMINADO: 'Trabajo terminado',
            PARCIAL: 'Resultado parcial',
            PROVISIONAL: 'Solución provisional'
        };
        return labels[result] || result || 'Cierre registrado';
    }

    function eventLabel(event) {
        var labels = {
            CREADA: 'Solicitud enviada',
            EDITADA: 'Información actualizada',
            APROBADA: 'Solicitud aprobada',
            RECHAZADA: 'Solicitud rechazada',
            PROGRAMADA: 'Mantenimiento programado',
            REPROGRAMADA: 'Fecha reprogramada',
            ASIGNADA: 'Personal asignado',
            TECNICO_RETIRADO: 'Técnico retirado',
            URGENTE_PUBLICADA: 'Urgencia publicada',
            URGENTE_ACEPTADA: 'Urgencia aceptada',
            INICIADA: 'Mantenimiento iniciado',
            PAUSADA: 'Mantenimiento pausado',
            REANUDADA: 'Mantenimiento reanudado',
            TERMINADA: 'Mantenimiento terminado',
            INCUMPLIMIENTO_DETECTADO: 'Atraso detectado',
            CUMPLIDA_TARDE: 'Finalizada con atraso',
            NO_REALIZADA: 'Participación no realizada',
            JUSTIFICADA: 'Atraso justificado',
            CANCELADA: 'Solicitud cancelada',
            OTRO: 'Actualización'
        };
        return labels[event] || event || 'Actualización';
    }

    function statusTransition(item) {
        if (item.estado_anterior || item.estado_nuevo) {
            return 'Estado: ' + statusLabel(item.estado_anterior) + ' → ' + statusLabel(item.estado_nuevo);
        }
        return 'Se registró una actualización en la solicitud.';
    }

    function duration(seconds) {
        var value = number(seconds, 0);
        var hours = Math.floor(value / 3600);
        var minutes = Math.floor((value % 3600) / 60);

        if (hours > 0) {
            return hours + ' h ' + minutes + ' min';
        }
        return Math.max(1, minutes) + ' min';
    }

    function initials(name) {
        var parts = text(name).split(/\s+/).filter(Boolean);
        return ((parts[0] || '').charAt(0) + (parts[1] || '').charAt(0)).toUpperCase() || 'T';
    }

    function truncate(value, max) {
        var clean = text(value);
        return clean.length > max ? clean.slice(0, max - 1) + '…' : clean;
    }

    function joinNonEmpty(values, separator) {
        var clean = values.filter(function (value) {
            return text(value) !== '';
        }).map(function (value) {
            return text(value);
        });
        return clean.join(separator || ' · ');
    }

    function sanitizeBreaks(value) {
        return escapeHtml(value).replace(/&lt;br&gt;/g, '<br>');
    }

    function number(value, fallback) {
        var parsed = Number.parseInt(value, 10);
        return Number.isFinite(parsed) && parsed >= 0 ? parsed : fallback;
    }

    function text(value) {
        return value === null || value === undefined ? '' : String(value).trim();
    } 

    function escapeHtml(value) {
        return text(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}());
</script>
</body>
</html>