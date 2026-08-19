<?php

declare(strict_types=1);

/*
 * La interfaz consulta esta misma página mediante ?ds_api=1 para evitar
 * rutas relativas frágiles hacia la carpeta funciones.
 */
if (isset($_GET['ds_api'])) {
    $endpoint = __DIR__ . '/../funciones/dashboard_solicitante_funciones.php';

    if (!is_file($endpoint)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode(
            [
                'success' => false,
                'mensaje' => 'No se encontró funciones/dashboard_solicitante_funciones.php. Copia juntos los tres archivos del módulo.',
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    require $endpoint;
    exit;
}

require_once __DIR__ . '/../inc/seguridad.php';
sm_requerir_sesion(['SOLICITANTE'], false);

if (!function_exists('ds_vista_e')) {
    function ds_vista_e($valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}

$nombreSesion = trim((string) ($_SESSION['nombre'] ?? $_SESSION['nombre_completo'] ?? 'Solicitante'));

if ($nombreSesion === '') {
    $nombreSesion = 'Solicitante';
}

$cssDashboardSolicitante = __DIR__ . '/../css/style_dashboard_solicitante.css';
$versionCss = is_file($cssDashboardSolicitante)
    ? (string) filemtime($cssDashboardSolicitante)
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
        content="Panel de inicio del solicitante del Sistema de Mantenimiento"
    >
    <title>Inicio del solicitante | Sistema de Mantenimiento</title>
    <link
        rel="preload"
        href="../imagenes/herramienta_abajo.png"
        as="image"
    >
    <link
        rel="stylesheet"
        href="../css/style_dashboard_solicitante.css?v=<?= ds_vista_e($versionCss) ?>"
    >
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="ds-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="ds-icon-sparkles" viewBox="0 0 24 24">
        <path d="m12 3-1.2 3.8L7 8l3.8 1.2L12 13l1.2-3.8L17 8l-3.8-1.2L12 3Z"/>
        <path d="m5 15-.7 2.3L2 18l2.3.7L5 21l.7-2.3L8 18l-2.3-.7L5 15Z"/>
        <path d="m19 13-.7 2.3-2.3.7 2.3.7L19 19l.7-2.3 2.3-.7L19 13Z"/>
    </symbol>
    <symbol id="ds-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="ds-icon-user" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="4"/>
        <path d="M4 21a8 8 0 0 1 16 0"/>
    </symbol>
    <symbol id="ds-icon-building" viewBox="0 0 24 24">
        <path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/>
        <path d="M16 9h2a2 2 0 0 1 2 2v10M8 7h4M8 11h4M8 15h4M9 21v-3h2v3M2 21h20"/>
    </symbol>
    <symbol id="ds-icon-clipboard" viewBox="0 0 24 24">
        <rect x="5" y="4" width="14" height="17" rx="2"/>
        <path d="M9 4.5V3h6v1.5M8 9h8M8 13h8M8 17h5"/>
    </symbol>
    <symbol id="ds-icon-wrench" viewBox="0 0 24 24">
        <path d="m14.7 6.3 3-3a4 4 0 0 1-5 5l-7.4 7.4a2 2 0 1 1-2.8-2.8l7.4-7.4a4 4 0 0 1 4.8-5.2"/>
        <path d="m15 14 6 6M17 12l2-2"/>
    </symbol>
    <symbol id="ds-icon-improve" viewBox="0 0 24 24">
        <path d="M4 19 15 8l1 1 4-4"/>
        <path d="M14 5h6v6M5 13v6h6"/>
    </symbol>
    <symbol id="ds-icon-bolt" viewBox="0 0 24 24">
        <path d="m13 2-9 12h7l-1 8 9-12h-7l1-8Z"/>
    </symbol>
    <symbol id="ds-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="ds-icon-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </symbol>
    <symbol id="ds-icon-progress" viewBox="0 0 24 24">
        <path d="M4 12h4l2-5 4 10 2-5h4"/>
    </symbol>
    <symbol id="ds-icon-check" viewBox="0 0 24 24">
        <path d="m5 12 4 4L19 6"/>
    </symbol>
    <symbol id="ds-icon-shield" viewBox="0 0 24 24">
        <path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="ds-icon-arrow" viewBox="0 0 24 24">
        <path d="M5 12h14M14 7l5 5-5 5"/>
    </symbol>
    <symbol id="ds-icon-info" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 11v5M12 8h.01"/>
    </symbol>
    <symbol id="ds-icon-plus" viewBox="0 0 24 24">
        <path d="M12 5v14M5 12h14"/>
    </symbol>
</svg>

<main class="ds-page">
    <div class="ds-ambient ds-ambient--one" aria-hidden="true"></div>
    <div class="ds-ambient ds-ambient--two" aria-hidden="true"></div>

    <section
        class="ds-welcome ds-hero"
        aria-labelledby="tituloInicioSolicitante"
    >
        <div class="ds-hero__content">
            <div class="ds-welcome__copy ds-hero__copy">
                <p class="ds-eyebrow">
                    <span class="ds-eyebrow__icon" aria-hidden="true">
                        <svg><use href="#ds-icon-sparkles"></use></svg>
                    </span>
                    Portal del solicitante
                </p>

                <h1 id="tituloInicioSolicitante">
                    Hola, <span id="nombreSolicitante"><?= ds_vista_e($nombreSesion) ?></span>
                </h1>

                <p class="ds-hero__description">
                    Reporta una necesidad de mantenimiento y consulta el avance de
                    las solicitudes que ya enviaste desde un solo lugar.
                </p>

                <div class="ds-hero__meta">
                    <span>
                        <span class="ds-live-dot" aria-hidden="true"></span>
                        Cuenta solicitante activa
                    </span>
                    <span>
                        Departamento:
                        <strong id="departamentoSolicitante">
                            Consultando departamento...
                        </strong>
                    </span>
                </div>
            </div>

            <div class="ds-hero__actions">
                <div class="ds-hero__mini-card">
                    <span class="ds-hero__mini-icon" aria-hidden="true">
                        <svg><use href="#ds-icon-clipboard"></use></svg>
                    </span>
                    <div>
                        <small>Centro de solicitudes</small>
                        <strong>Registro y seguimiento en un solo panel</strong>
                    </div>
                </div>

                <button
                    type="button"
                    class="ds-refresh ds-refresh--hero"
                    id="btnActualizar"
                    aria-label="Actualizar información"
                >
                    <svg aria-hidden="true"><use href="#ds-icon-refresh"></use></svg>
                    <span>Actualizar</span>
                </button>
            </div>
        </div>
    </section>

    <section class="ds-guides" aria-label="Guía rápida del solicitante">
        <article>
            <span aria-hidden="true">
                <svg><use href="#ds-icon-wrench"></use></svg>
            </span>
            <div>
                <strong>Elige el reporte correcto</strong>
                <p>
                    Selecciona correctivo programable, mejora o urgencia según
                    el impacto real de la situación.
                </p>
            </div>
        </article>

        <article>
            <span aria-hidden="true">
                <svg><use href="#ds-icon-search"></use></svg>
            </span>
            <div>
                <strong>Consulta el seguimiento</strong>
                <p>
                    Revisa estados, programación, observaciones y resultados
                    desde tu bandeja personal.
                </p>
            </div>
        </article>

        <article>
            <span aria-hidden="true">
                <svg><use href="#ds-icon-shield"></use></svg>
            </span>
            <div>
                <strong>Información protegida</strong>
                <p>
                    Solo puedes consultar y registrar solicitudes relacionadas
                    con tu propia cuenta.
                </p>
            </div>
        </article>
    </section>

    <div
        class="ds-message"
        id="mensajePagina"
        role="alert"
        aria-live="assertive"
        hidden
    ></div>

    <section
        class="ds-department-warning"
        id="avisoDepartamento"
        role="alert"
        hidden
    >
        <span class="ds-department-warning__icon" aria-hidden="true">
            <svg><use href="#ds-icon-info"></use></svg>
        </span>
        <div>
            <strong>No puedes crear solicitudes por el momento</strong>
            <p>
                Tu cuenta no tiene un departamento activo asignado. Comunícate
                con un administrador para corregirlo; todavía puedes consultar
                tus solicitudes anteriores.
            </p>
        </div>
    </section>

    <section
        class="ds-loading"
        id="estadoCarga"
        role="status"
        aria-live="polite"
    >
        <span class="ds-spinner" aria-hidden="true"></span>
        <div>
            <strong>Preparando tu panel...</strong>
            <span>Estamos consultando tus solicitudes y tu departamento.</span>
        </div>
    </section>

    <div id="contenidoDashboard" hidden>
        <section class="ds-actions" aria-label="Acciones principales">
            <article class="ds-new-request">
                <header class="ds-section-heading">
                    <div class="ds-section-heading__title">
                        <span class="ds-section-icon" aria-hidden="true">
                            <svg><use href="#ds-icon-plus"></use></svg>
                        </span>
                        <div>
                            <p class="ds-eyebrow">NUEVA SOLICITUD</p>
                            <h2>¿Qué necesitas reportar?</h2>
                            <p>
                                Selecciona la opción que mejor describa la situación.
                            </p>
                        </div>
                    </div>
                </header>

                <div class="ds-request-options" id="opcionesSolicitud">
                    <a
                        href="solicitud_correctivo_programable.php"
                        class="ds-request-option ds-request-option--programable"
                        data-request-link
                    >
                        <span class="ds-request-option__icon" aria-hidden="true">
                            <svg><use href="#ds-icon-wrench"></use></svg>
                        </span>
                        <span class="ds-request-option__copy">
                            <strong>Correctivo programable</strong>
                            <small>
                                Existe una falla, pero puede atenderse con planeación.
                            </small>
                        </span>
                        <span class="ds-request-option__arrow" aria-hidden="true">
                            <svg><use href="#ds-icon-arrow"></use></svg>
                        </span>
                    </a>

                    <a
                        href="solicitud_modificacion_mejora.php"
                        class="ds-request-option ds-request-option--mejora"
                        data-request-link
                    >
                        <span class="ds-request-option__icon" aria-hidden="true">
                            <svg><use href="#ds-icon-improve"></use></svg>
                        </span>
                        <span class="ds-request-option__copy">
                            <strong>Modificación o mejora</strong>
                            <small>
                                Propón un cambio para mejorar seguridad, calidad o productividad.
                            </small>
                        </span>
                        <span class="ds-request-option__arrow" aria-hidden="true">
                            <svg><use href="#ds-icon-arrow"></use></svg>
                        </span>
                    </a>

                    <a
                        href="solicitud_correctivo_urgente.php"
                        class="ds-request-option ds-request-option--urgente"
                        data-request-link
                    >
                        <span class="ds-request-option__icon" aria-hidden="true">
                            <svg><use href="#ds-icon-bolt"></use></svg>
                        </span>
                        <span class="ds-request-option__copy">
                            <strong>Correctivo urgente</strong>
                            <small>
                                La falla detiene, pone en riesgo o afecta gravemente la operación.
                            </small>
                        </span>
                        <span class="ds-request-option__arrow" aria-hidden="true">
                            <svg><use href="#ds-icon-arrow"></use></svg>
                        </span>
                    </a>
                </div>

                <details class="ds-help">
                    <summary>
                        <span>
                            <svg aria-hidden="true"><use href="#ds-icon-info"></use></svg>
                            ¿No sabes cuál elegir?
                        </span>
                    </summary>
                    <div class="ds-help__content">
                        <p>
                            <strong>Programable:</strong>
                            la falla existe, pero el trabajo puede organizarse.
                        </p>
                        <p>
                            <strong>Mejora:</strong>
                            no necesariamente hay una falla; buscas cambiar u optimizar algo.
                        </p>
                        <p>
                            <strong>Urgente:</strong>
                            existe paro, riesgo o afectación inmediata importante.
                        </p>
                    </div>
                </details>
            </article>

            <a
                href="bandeja_solicitante.php"
                class="ds-follow-card"
                id="accesoMisSolicitudes"
            >
                <span class="ds-follow-card__icon" aria-hidden="true">
                    <svg><use href="#ds-icon-clipboard"></use></svg>
                </span>

                <span class="ds-follow-card__copy">
                    <span class="ds-eyebrow">SEGUIMIENTO</span>
                    <strong>Mis solicitudes</strong>
                    <small>
                        Consulta estados, fechas, observaciones y resultados.
                    </small>
                </span>

                <span class="ds-follow-card__count">
                    <b id="contadorSeguimiento">0</b>
                    <small>en seguimiento</small>
                </span>

                <span class="ds-follow-card__action">
                    Abrir bandeja
                    <svg aria-hidden="true"><use href="#ds-icon-arrow"></use></svg>
                </span>
            </a>
        </section>

        <section class="ds-summary" aria-label="Resumen de solicitudes">
            <article class="ds-summary-card ds-summary-card--review">
                <span class="ds-summary-card__icon" aria-hidden="true">
                    <svg><use href="#ds-icon-clock"></use></svg>
                </span>
                <div>
                    <span>En revisión</span>
                    <strong id="kpiRevision">0</strong>
                    <small>Esperando validación</small>
                </div>
            </article>

            <article class="ds-summary-card ds-summary-card--progress">
                <span class="ds-summary-card__icon" aria-hidden="true">
                    <svg><use href="#ds-icon-progress"></use></svg>
                </span>
                <div>
                    <span>En seguimiento</span>
                    <strong id="kpiSeguimiento">0</strong>
                    <small>Aprobadas, agendadas o activas</small>
                </div>
            </article>

            <article class="ds-summary-card ds-summary-card--done">
                <span class="ds-summary-card__icon" aria-hidden="true">
                    <svg><use href="#ds-icon-check"></use></svg>
                </span>
                <div>
                    <span>Terminadas</span>
                    <strong id="kpiTerminadas">0</strong>
                    <small>Con resultado registrado</small>
                </div>
            </article>
        </section>

        <section
            class="ds-recent"
            aria-labelledby="tituloSolicitudesRecientes"
        >
            <header class="ds-section-heading ds-section-heading--between">
                <div class="ds-section-heading__title">
                    <span class="ds-section-icon" aria-hidden="true">
                        <svg><use href="#ds-icon-clock"></use></svg>
                    </span>
                    <div>
                        <p class="ds-eyebrow">ACTIVIDAD RECIENTE</p>
                        <h2 id="tituloSolicitudesRecientes">
                            Últimas solicitudes
                        </h2>
                        <p>
                            Una vista rápida de tus reportes más recientes.
                        </p>
                    </div>
                </div>

                <a href="bandeja_solicitante.php" class="ds-text-link">
                    Ver todas
                    <svg aria-hidden="true"><use href="#ds-icon-arrow"></use></svg>
                </a>
            </header>

            <div class="ds-empty" id="estadoVacio" hidden>
                <span class="ds-empty__icon" aria-hidden="true">
                    <svg><use href="#ds-icon-plus"></use></svg>
                </span>
                <h3>Todavía no tienes solicitudes</h3>
                <p>
                    Cuando registres la primera, podrás consultar aquí su avance.
                </p>
                <a
                    href="solicitud_correctivo_programable.php"
                    data-request-link
                >
                    Crear mi primera solicitud
                </a>
            </div>

            <div
                class="ds-recent-list"
                id="listaSolicitudes"
                aria-live="polite"
            ></div>
        </section>

        <footer class="ds-footer">
            <p class="ds-updated" id="ultimaActualizacion">
                Sin actualizar
            </p>
            <span>
                La información mostrada corresponde únicamente a tus solicitudes.
            </span>
        </footer>
    </div>

    <div class="ds-tools-background" aria-hidden="true"></div>
</main>

<script>
(function () {
    'use strict';

    var ENDPOINT = 'dashboard_solicitante.php?ds_api=1';
    var state = {
        loading: false,
        loaded: false,
        canCreate: true
    };

    var elements = {};

    document.addEventListener('DOMContentLoaded', function () {
        elements = {
            refresh: document.getElementById('btnActualizar'),
            message: document.getElementById('mensajePagina'),
            loading: document.getElementById('estadoCarga'),
            content: document.getElementById('contenidoDashboard'),
            warning: document.getElementById('avisoDepartamento'),
            name: document.getElementById('nombreSolicitante'),
            department: document.getElementById('departamentoSolicitante'),
            review: document.getElementById('kpiRevision'),
            tracking: document.getElementById('kpiSeguimiento'),
            done: document.getElementById('kpiTerminadas'),
            trackingCount: document.getElementById('contadorSeguimiento'),
            list: document.getElementById('listaSolicitudes'),
            empty: document.getElementById('estadoVacio'),
            updated: document.getElementById('ultimaActualizacion')
        };

        elements.refresh.addEventListener('click', loadDashboard);
        document.addEventListener('click', guardRequestLinks);

        loadDashboard();
    });

    async function loadDashboard() {
        if (state.loading) {
            return;
        }

        state.loading = true;
        showState('loading');
        setButtonLoading(true);
        hideMessage();

        try {
            var params = new URLSearchParams({ accion: 'INICIAL' });
            var data = await requestJson(ENDPOINT + '&' + params.toString());

            paintProfile(data.solicitante || {});
            paintSummary(data.resumen || {});
            paintRequests(Array.isArray(data.solicitudes_recientes) ? data.solicitudes_recientes : []);

            elements.updated.textContent = data.actualizado_en
                ? 'Actualizado ' + data.actualizado_en
                : 'Información actualizada';

            state.loaded = true;
            showState('content');
        } catch (error) {
            console.error(error);
            showState('error');
            showMessage(
                error.message || 'No fue posible cargar tu información. Inténtalo nuevamente.',
                'error'
            );
        } finally {
            state.loading = false;
            setButtonLoading(false);
        }
    }

    async function requestJson(url, options) {
        var response = await fetch(url, Object.assign({
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            cache: 'no-store'
        }, options || {}));

        var text = await response.text();
        var data;

        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Respuesta recibida:', text);
            throw new Error('El servidor devolvió una respuesta no válida.');
        }

        if (data && data.sesion_expirada && data.redirect) {
            window.location.href = data.redirect;
            throw new Error('Tu sesión terminó.');
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

    function paintProfile(profile) {
        var fullName = text(profile.nombre_completo) || text(profile.nombre) || 'Solicitante';
        var shortName = text(profile.nombre) || firstName(fullName);
        var department = text(profile.departamento) || 'Sin departamento asignado';

        elements.name.textContent = shortName;
        elements.department.textContent = department;

        state.canCreate = profile.puede_crear_solicitudes === true;
        elements.warning.hidden = state.canCreate;

        document.querySelectorAll('[data-request-link]').forEach(function (link) {
            link.classList.toggle('is-disabled', !state.canCreate);
            link.setAttribute('aria-disabled', state.canCreate ? 'false' : 'true');
            link.tabIndex = state.canCreate ? 0 : -1;
        });
    }

    function paintSummary(summary) {
        var review = number(summary.en_revision);
        var tracking = number(summary.en_seguimiento);
        var done = number(summary.terminadas);

        elements.review.textContent = String(review);
        elements.tracking.textContent = String(tracking);
        elements.done.textContent = String(done);
        elements.trackingCount.textContent = String(tracking);
    }

    function paintRequests(requests) {
        elements.list.innerHTML = '';
        elements.empty.hidden = requests.length !== 0;
        elements.list.hidden = requests.length === 0;

        if (requests.length === 0) {
            return;
        }

        var fragment = document.createDocumentFragment();

        requests.forEach(function (request) {
            fragment.appendChild(createRequestCard(request));
        });

        elements.list.appendChild(fragment);
    }

    function createRequestCard(request) {
        var article = document.createElement('article');
        var status = text(request.estado);
        var type = text(request.tipo_solicitud);
        var requestId = number(request.id);
        var equipment = [text(request.codigo_equipo), text(request.equipo)]
            .filter(Boolean)
            .join(' · ') || 'Equipo no disponible';
        var date = [text(request.fecha_solicitud_texto), text(request.hora_solicitud_texto)]
            .filter(Boolean)
            .join(' · ');
        var scheduleText = requestScheduleText(request);

        article.className = 'ds-request-card ' + statusClass(status);
        article.innerHTML =
            '<div class="ds-request-card__top">' +
                '<div class="ds-request-card__identity">' +
                    '<span class="ds-request-card__folio">' + escapeHtml(text(request.folio) || 'Sin folio') + '</span>' +
                    '<span class="ds-badge ' + statusClass(status) + '">' + escapeHtml(statusLabel(status)) + '</span>' +
                '</div>' +
                '<span class="ds-request-card__date">' + escapeHtml(date || 'Sin fecha') + '</span>' +
            '</div>' +
            '<h3>' + escapeHtml(typeLabel(type)) + '</h3>' +
            '<p class="ds-request-card__equipment">' + escapeHtml(equipment) + '</p>' +
            '<p class="ds-request-card__description">' + escapeHtml(text(request.descripcion_solicitud) || 'Sin descripción') + '</p>' +
            '<div class="ds-request-card__foot">' +
                '<span>' + escapeHtml(scheduleText) + '</span>' +
                '<a href="bandeja_solicitante.php' + (requestId > 0 ? '?solicitud=' + requestId : '') + '">' +
                    'Ver seguimiento <b aria-hidden="true">›</b>' +
                '</a>' +
            '</div>';

        return article;
    }

    function requestScheduleText(request) {
        var status = text(request.estado);
        var programmed = text(request.fecha_programada_texto);
        var closed = text(request.fecha_cierre_texto);

        if (status === 'TERMINADO' && closed) {
            return 'Finalizada: ' + closed;
        }

        if (programmed) {
            return 'Programada: ' + programmed;
        }

        if (status === 'PENDIENTE') {
            return 'Esperando revisión';
        }

        if (status === 'RECHAZADO') {
            return 'Revisa el motivo en tu bandeja';
        }

        if (status === 'CANCELADO') {
            return 'Solicitud cancelada';
        }

        return 'Consulta el detalle';
    }

    function guardRequestLinks(event) {
        var link = event.target.closest('[data-request-link]');

        if (!link || state.canCreate) {
            return;
        }

        event.preventDefault();
        showMessage(
            'Tu departamento no está activo o no está asignado. Comunícate con un administrador antes de crear una solicitud.',
            'warning'
        );
        elements.warning.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function showState(mode) {
        if (mode === 'loading') {
            elements.loading.hidden = state.loaded;
            elements.content.hidden = !state.loaded;
            return;
        }

        elements.loading.hidden = true;
        elements.content.hidden = mode === 'error' && !state.loaded;
    }

    function setButtonLoading(loading) {
        elements.refresh.disabled = loading;
        elements.refresh.classList.toggle('is-loading', loading);
        elements.refresh.querySelector('span:last-child').textContent = loading
            ? 'Actualizando...'
            : 'Actualizar';
    }

    function showMessage(message, type) {
        elements.message.textContent = message;
        elements.message.className = 'ds-message ds-message--' + (type || 'info');
        elements.message.hidden = false;
    }

    function hideMessage() {
        elements.message.hidden = true;
        elements.message.textContent = '';
    }

    function firstName(fullName) {
        return text(fullName).split(/\s+/)[0] || 'Solicitante';
    }

    function statusClass(status) {
        var classes = {
            PENDIENTE: 'is-pending',
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

    function typeLabel(type) {
        var labels = {
            CORRECTIVO_PROGRAMABLE: 'Correctivo programable',
            MODIFICACION_MEJORA: 'Modificación o mejora',
            CORRECTIVO_URGENTE: 'Correctivo urgente'
        };

        return labels[type] || type || 'Solicitud';
    }

    function number(value) {
        var parsed = Number.parseInt(value, 10);
        return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
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