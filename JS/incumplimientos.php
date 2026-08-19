<?php

declare(strict_types=1);

/*
 * Las peticiones del navegador regresan a esta misma página con ?inc_api=1.
 * La página carga internamente el backend mediante una ruta absoluta, evitando
 * errores 404 por rutas relativas o nombres de carpeta diferentes.
 */
if (isset($_GET['inc_api']) || isset($_POST['inc_api'])) {
    $endpoint = __DIR__ . '/../funciones/incumplimientos_funciones.php';

    if (!is_file($endpoint)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode(
            [
                'success' => false,
                'mensaje' => 'No se encontró funciones/incumplimientos_funciones.php. Copia juntos los tres archivos del módulo en sus carpetas correspondientes.',
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    require $endpoint;
    exit;
}

require_once __DIR__ . '/../inc/seguridad.php';
sm_requerir_sesion(['ADMIN'], false);

$csrfToken = sm_token_csrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0a2944">
    <meta name="description" content="Seguimiento administrativo de cumplimiento e incumplimientos de mantenimiento.">
    <title>Cumplimiento | Sistema de Mantenimiento</title>
    <link rel="preload" as="image" href="../imagenes/herramienta_abajo.png">
    <link rel="stylesheet" href="../css/style_incumplimientos.css?v=20260730.2">
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<main class="inc-page">
    <header class="inc-heading">
        <div class="inc-heading__copy">
            <p class="inc-eyebrow">SEGUIMIENTO ADMINISTRATIVO</p>
            <h1>Cumplimiento</h1>
            <p>
                Revisa qué participaciones vencieron, cuáles se completaron tarde y cuáles necesitan
                una resolución administrativa antes de continuar con la reprogramación.
            </p>
            <div class="inc-heading__meta" aria-label="Características del módulo">
                <span><i aria-hidden="true"></i> Seguimiento por técnico</span>
                <span><i aria-hidden="true"></i> Resoluciones con auditoría</span>
            </div>
        </div>

        <div class="inc-heading__actions">
            <button type="button" class="inc-btn inc-btn--secondary" id="btnExportar">
                <span aria-hidden="true">⇩</span>
                Exportar CSV
            </button>
            <button type="button" class="inc-btn inc-btn--primary" id="btnSincronizar">
                <span aria-hidden="true">↻</span>
                Actualizar vencimientos
            </button>
        </div>
    </header>

    <section class="inc-rule" aria-label="Reglas del módulo">
        <span class="inc-rule__icon" aria-hidden="true">!</span>
        <div>
            <strong>Cada registro corresponde a la responsabilidad de un técnico en una fecha programada.</strong>
            <p>
                Los correctivos urgentes están exentos. Justificar conserva la actividad pendiente;
                marcar “No realizado” retira solamente a ese técnico y deja el mantenimiento disponible para reprogramarse.
            </p>
        </div>
        <span class="inc-rule__cutoff" id="textoHoraCorte">Corte: 23:59</span>
    </section>

    <div class="inc-status" id="estadoPagina" role="status" aria-live="polite">
        Cargando cumplimiento...
    </div>

    <section class="inc-kpis" aria-label="Resumen de cumplimiento">
        <article class="inc-kpi" data-symbol="◎">
            <span>Registros</span>
            <strong id="kpiTotal">0</strong>
            <small>Con filtros generales</small>
        </article>
        <article class="inc-kpi inc-kpi--pending" data-symbol="!">
            <span>Pendientes</span>
            <strong id="kpiPendientes">0</strong>
            <small>Requieren atención</small>
        </article>
        <article class="inc-kpi inc-kpi--late" data-symbol="↗">
            <span>Cumplidos tarde</span>
            <strong id="kpiTarde">0</strong>
            <small>Finalizados después</small>
        </article>
        <article class="inc-kpi inc-kpi--justified" data-symbol="✓">
            <span>Justificados</span>
            <strong id="kpiJustificados">0</strong>
            <small>Con motivo administrativo</small>
        </article>
        <article class="inc-kpi inc-kpi--failed" data-symbol="×">
            <span>No realizados</span>
            <strong id="kpiNoRealizados">0</strong>
            <small>Participaciones retiradas</small>
        </article>
        <article class="inc-kpi inc-kpi--time" data-symbol="◴">
            <span>Atraso pendiente</span>
            <strong id="kpiPromedio">0 min</strong>
            <small>Promedio sin resolver</small>
        </article>
    </section>

    <section class="inc-card inc-filter-card">
        <header class="inc-filter-card__head">
            <span class="inc-filter-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M4 5h16M7 12h10M10 19h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="8" cy="5" r="2" fill="currentColor"/>
                    <circle cx="15" cy="12" r="2" fill="currentColor"/>
                    <circle cx="12" cy="19" r="2" fill="currentColor"/>
                </svg>
            </span>
            <div>
                <span class="inc-filter-card__eyebrow">CONSULTA OPERATIVA</span>
                <h2>Filtra el seguimiento</h2>
                <p>Combina búsqueda, situación, técnico y periodo sin cargar todos los registros de golpe.</p>
            </div>
            <span class="inc-filter-card__badge">Filtros del servidor</span>
        </header>
        <form class="inc-filters" id="formFiltros" autocomplete="off">
            <label class="inc-field inc-field--search" for="filtroBusqueda">
                <span>Buscar</span>
                <div class="inc-search">
                    <span aria-hidden="true">⌕</span>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        maxlength="120"
                        placeholder="Folio, técnico, equipo o ubicación"
                    >
                </div>
            </label>

            <label class="inc-field" for="filtroEstado">
                <span>Situación</span>
                <select id="filtroEstado">
                    <option value="TODOS">Todas</option>
                    <option value="PENDIENTE">Pendientes</option>
                    <option value="CUMPLIDO_TARDE">Cumplidos tarde</option>
                    <option value="JUSTIFICADO">Justificados</option>
                    <option value="NO_REALIZADO">No realizados</option>
                </select>
            </label>

            <label class="inc-field" for="filtroTecnico">
                <span>Técnico</span>
                <select id="filtroTecnico">
                    <option value="">Todos los técnicos</option>
                </select>
            </label>

            <label class="inc-field" for="filtroTipo">
                <span>Tipo</span>
                <select id="filtroTipo">
                    <option value="TODOS">Todos</option>
                    <option value="CORRECTIVO_PROGRAMABLE">Correctivo programable</option>
                    <option value="MODIFICACION_MEJORA">Modificación o mejora</option>
                    <option value="RUTINARIO">Rutinario</option>
                </select>
            </label>

            <div class="inc-filter-actions">
                <button type="submit" class="inc-btn inc-btn--primary">Aplicar</button>
                <button type="button" class="inc-btn inc-btn--ghost" id="btnLimpiar">Limpiar</button>
            </div>

            <details class="inc-more-filters" id="filtrosAvanzados">
                <summary>Más filtros</summary>
                <div class="inc-more-filters__body">
                    <label class="inc-field" for="filtroPrioridad">
                        <span>Prioridad</span>
                        <select id="filtroPrioridad">
                            <option value="TODAS">Todas</option>
                            <option value="ALTA">Alta</option>
                            <option value="MEDIA">Media</option>
                            <option value="BAJA">Baja</option>
                        </select>
                    </label>
                    <label class="inc-field" for="filtroDesde">
                        <span>Programado desde</span>
                        <input type="date" id="filtroDesde">
                    </label>
                    <label class="inc-field" for="filtroHasta">
                        <span>Programado hasta</span>
                        <input type="date" id="filtroHasta">
                    </label>
                    <label class="inc-field" for="filtroOrden">
                        <span>Ordenar</span>
                        <select id="filtroOrden">
                            <option value="RECIENTES">Pendientes y recientes</option>
                            <option value="MAYOR_ATRASO">Mayor atraso</option>
                            <option value="ANTIGUOS">Más antiguos</option>
                            <option value="TECNICO">Por técnico</option>
                            <option value="FOLIO">Por folio</option>
                        </select>
                    </label>
                    <label class="inc-field" for="filtroPorPagina">
                        <span>Mostrar</span>
                        <select id="filtroPorPagina">
                            <option value="12">12 por página</option>
                            <option value="24" selected>24 por página</option>
                            <option value="48">48 por página</option>
                        </select>
                    </label>
                </div>
            </details>
        </form>
    </section>

    <section class="inc-card inc-results-card">
        <header class="inc-results-head">
            <div>
                <p class="inc-eyebrow">RESPONSABILIDAD POR TÉCNICO</p>
                <h2>Seguimiento de vencimientos</h2>
                <p id="textoResultados">Consultando información...</p>
            </div>
            <div class="inc-results-head__meta">
                <span class="inc-server-badge">Paginación del servidor</span>
                <span class="inc-count" id="contadorResultados">0 registros</span>
            </div>
        </header>

        <div class="inc-table-wrap" id="contenedorTabla" hidden>
            <table class="inc-table">
                <thead>
                    <tr>
                        <th>Mantenimiento</th>
                        <th>Técnico responsable</th>
                        <th>Programación</th>
                        <th>Situación</th>
                        <th>Estado actual</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="cuerpoTabla"></tbody>
            </table>
        </div>

        <div class="inc-empty" id="estadoVacio" hidden>
            <span aria-hidden="true">✓</span>
            <h3>No hay registros con esos filtros</h3>
            <p>Prueba con otro periodo o limpia los filtros para consultar el historial completo.</p>
            <button type="button" class="inc-btn inc-btn--secondary" id="btnLimpiarVacio">
                Mostrar todos
            </button>
        </div>

        <footer class="inc-pagination-wrap" id="contenedorPaginacion" hidden>
            <p id="textoPaginacion">0 resultados</p>
            <nav class="inc-pagination" id="paginacion" aria-label="Paginación"></nav>
        </footer>
    </section>

    <div class="inc-tools-background" aria-hidden="true">
        <img src="../imagenes/herramienta_abajo.png" alt="" decoding="async">
    </div>
</main>

<section class="inc-modal" id="modalDetalle" hidden aria-hidden="true">
    <div class="inc-modal__dialog inc-modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="detalleTitulo">
        <header class="inc-modal__header">
            <div>
                <p class="inc-eyebrow">EXPEDIENTE DE CUMPLIMIENTO</p>
                <h2 id="detalleTitulo">Detalle del incumplimiento</h2>
                <p id="detalleSubtitulo">Consultando información...</p>
            </div>
            <button type="button" class="inc-modal__close" data-cerrar-modal="modalDetalle" aria-label="Cerrar">×</button>
        </header>

        <div class="inc-modal__body" id="detalleContenido">
            <div class="inc-loading"><span></span><p>Cargando detalle...</p></div>
        </div>

        <footer class="inc-modal__footer" id="detalleAcciones">
            <button type="button" class="inc-btn inc-btn--ghost" data-cerrar-modal="modalDetalle">Cerrar</button>
        </footer>
    </div>
</section>

<section class="inc-modal" id="modalResolver" hidden aria-hidden="true">
    <div class="inc-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="resolverTitulo">
        <header class="inc-modal__header">
            <div>
                <p class="inc-eyebrow">RESOLUCIÓN ADMINISTRATIVA</p>
                <h2 id="resolverTitulo">Resolver incumplimiento</h2>
                <p id="resolverSubtitulo">Describe claramente la decisión.</p>
            </div>
            <button type="button" class="inc-modal__close" data-cerrar-modal="modalResolver" aria-label="Cerrar">×</button>
        </header>

        <form id="formResolver" novalidate>
            <div class="inc-modal__body">
                <input type="hidden" id="resolverId" name="incumplimiento_id">
                <input type="hidden" id="resolverTipo" name="resolucion">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="inc-decision" id="resolverDecision">
                    <span class="inc-decision__icon" aria-hidden="true">?</span>
                    <div>
                        <strong>Selecciona una resolución desde el detalle.</strong>
                        <p>La operación quedará registrada en historial y auditoría.</p>
                    </div>
                </div>

                <label class="inc-field inc-field--full" for="resolverMotivo">
                    <span id="resolverMotivoEtiqueta">Motivo *</span>
                    <textarea
                        id="resolverMotivo"
                        name="motivo"
                        rows="6"
                        minlength="15"
                        maxlength="1000"
                        placeholder="Explica la causa y la decisión administrativa..."
                        required
                    ></textarea>
                    <small><span id="contadorMotivo">0</span>/1000 caracteres · mínimo 15</small>
                </label>
            </div>

            <footer class="inc-modal__footer">
                <button type="button" class="inc-btn inc-btn--ghost" data-cerrar-modal="modalResolver">Cancelar</button>
                <button type="submit" class="inc-btn inc-btn--primary" id="btnConfirmarResolucion">Guardar resolución</button>
            </footer>
        </form>
    </div>
</section>

<div class="inc-toast" id="toast" hidden role="status" aria-live="polite"></div>

<script>
(function () {
    'use strict';

    var ENDPOINT = window.location.pathname + '?inc_api=1';
    var CSRF_TOKEN = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var estado = {
        pagina: 1,
        cargando: false,
        registros: [],
        detalle: null,
        catalogosCargados: false
    };
    var ui = {};
    var temporizadorToast = null;

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        capturarElementos();
        registrarEventos();
        cargarListado(true);
    }

    function capturarElementos() {
        [
            'btnExportar', 'btnSincronizar', 'textoHoraCorte', 'estadoPagina',
            'kpiTotal', 'kpiPendientes', 'kpiTarde', 'kpiJustificados',
            'kpiNoRealizados', 'kpiPromedio', 'formFiltros', 'filtroBusqueda',
            'filtroEstado', 'filtroTecnico', 'filtroTipo', 'filtroPrioridad',
            'filtroDesde', 'filtroHasta', 'filtroOrden', 'filtroPorPagina',
            'btnLimpiar', 'textoResultados', 'contadorResultados', 'contenedorTabla',
            'cuerpoTabla', 'estadoVacio', 'btnLimpiarVacio', 'contenedorPaginacion',
            'textoPaginacion', 'paginacion', 'modalDetalle', 'detalleTitulo',
            'detalleSubtitulo', 'detalleContenido', 'detalleAcciones', 'modalResolver',
            'resolverTitulo', 'resolverSubtitulo', 'formResolver', 'resolverId',
            'resolverTipo', 'resolverDecision', 'resolverMotivoEtiqueta',
            'resolverMotivo', 'contadorMotivo', 'btnConfirmarResolucion', 'toast'
        ].forEach(function (id) {
            ui[id] = document.getElementById(id);
        });
    }

    function registrarEventos() {
        ui.formFiltros.addEventListener('submit', function (evento) {
            evento.preventDefault();
            estado.pagina = 1;
            cargarListado(false);
        });

        ui.btnLimpiar.addEventListener('click', limpiarFiltros);
        ui.btnLimpiarVacio.addEventListener('click', limpiarFiltros);
        ui.btnSincronizar.addEventListener('click', sincronizar);
        ui.btnExportar.addEventListener('click', exportarCsv);

        ui.filtroEstado.addEventListener('change', aplicarRapido);
        ui.filtroTecnico.addEventListener('change', aplicarRapido);
        ui.filtroTipo.addEventListener('change', aplicarRapido);
        ui.filtroPrioridad.addEventListener('change', aplicarRapido);
        ui.filtroOrden.addEventListener('change', aplicarRapido);
        ui.filtroPorPagina.addEventListener('change', aplicarRapido);

        ui.resolverMotivo.addEventListener('input', function () {
            ui.contadorMotivo.textContent = String(ui.resolverMotivo.value.length);
        });
        ui.formResolver.addEventListener('submit', guardarResolucion);

        document.addEventListener('click', manejarClick);
        document.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Escape') {
                return;
            }
            if (!ui.modalResolver.hidden) {
                cerrarModal(ui.modalResolver);
            } else if (!ui.modalDetalle.hidden) {
                cerrarModal(ui.modalDetalle);
            }
        });
    }

    function aplicarRapido() {
        estado.pagina = 1;
        cargarListado(false);
    }

    function limpiarFiltros() {
        ui.formFiltros.reset();
        ui.filtroEstado.value = 'TODOS';
        ui.filtroTipo.value = 'TODOS';
        ui.filtroPrioridad.value = 'TODAS';
        ui.filtroOrden.value = 'RECIENTES';
        ui.filtroPorPagina.value = '24';
        estado.pagina = 1;
        cargarListado(false);
    }

    async function cargarListado(inicial) {
        if (estado.cargando) {
            return;
        }

        estado.cargando = true;
        bloquearBoton(ui.btnSincronizar, true, 'Actualizando...');
        mostrarEstado('Cargando información...', 'loading');

        try {
            var url = ENDPOINT + '&accion=' + (inicial ? 'INICIAL' : 'LISTAR')
                + '&' + parametrosFiltros().toString();
            var datos = await solicitar(url);

            if (datos.csrf_token) {
                CSRF_TOKEN = datos.csrf_token;
            }

            estado.registros = Array.isArray(datos.registros) ? datos.registros : [];
            estado.pagina = Number(datos.paginacion && datos.paginacion.pagina) || 1;
            aplicarResumen(datos.resumen || {});
            llenarTecnicos(datos.catalogos && datos.catalogos.tecnicos);
            renderizarTabla(estado.registros);
            renderizarPaginacion(datos.paginacion || {});

            var horaCorte = datos.reglas && datos.reglas.hora_corte
                ? datos.reglas.hora_corte
                : '23:59';
            ui.textoHoraCorte.textContent = 'Corte diario: ' + horaCorte;

            var total = Number(datos.paginacion && datos.paginacion.total) || 0;
            ui.contadorResultados.textContent = total + (total === 1 ? ' registro' : ' registros');
            ui.textoResultados.textContent = total === 0
                ? 'No se encontraron participaciones.'
                : 'Mostrando ' + datos.paginacion.desde + '–' + datos.paginacion.hasta
                    + ' de ' + total + '.';

            mostrarEstado('Información actualizada ' + fechaHoraCorta(datos.fecha_servidor) + '.', 'success', true);
        } catch (error) {
            estado.registros = [];
            renderizarTabla([]);
            renderizarPaginacion({ total: 0 });
            mostrarEstado(error.message, 'error');
        } finally {
            estado.cargando = false;
            bloquearBoton(ui.btnSincronizar, false, '↻ Actualizar vencimientos');
        }
    }

    async function sincronizar() {
        bloquearBoton(ui.btnSincronizar, true, 'Sincronizando...');

        try {
            var formulario = new FormData();
            formulario.append('inc_api', '1');
            formulario.append('accion', 'SINCRONIZAR');
            formulario.append('csrf_token', CSRF_TOKEN);
            var datos = await solicitar(ENDPOINT, { method: 'POST', body: formulario });

            if (datos.csrf_token) {
                CSRF_TOKEN = datos.csrf_token;
            }

            var r = datos.resultado || {};
            toast(
                'Actualización lista: ' + (Number(r.creados) || 0) + ' nuevos, '
                + (Number(r.cumplidos_tarde) || 0) + ' conciliados.',
                'success'
            );
            await cargarListado(false);
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            bloquearBoton(ui.btnSincronizar, false, '↻ Actualizar vencimientos');
        }
    }

    function parametrosFiltros() {
        var p = new URLSearchParams();
        p.set('q', ui.filtroBusqueda.value.trim());
        p.set('estado', ui.filtroEstado.value);
        p.set('tecnico_id', ui.filtroTecnico.value);
        p.set('tipo', ui.filtroTipo.value);
        p.set('prioridad', ui.filtroPrioridad.value);
        p.set('desde', ui.filtroDesde.value);
        p.set('hasta', ui.filtroHasta.value);
        p.set('orden', ui.filtroOrden.value);
        p.set('por_pagina', ui.filtroPorPagina.value);
        p.set('pagina', String(estado.pagina));
        p.set('_t', String(Date.now()));
        return p;
    }

    function aplicarResumen(resumen) {
        ui.kpiTotal.textContent = numero(resumen.total);
        ui.kpiPendientes.textContent = numero(resumen.pendientes);
        ui.kpiTarde.textContent = numero(resumen.cumplidos_tarde);
        ui.kpiJustificados.textContent = numero(resumen.justificados);
        ui.kpiNoRealizados.textContent = numero(resumen.no_realizados);
        ui.kpiPromedio.textContent = texto(resumen.promedio_atraso_texto, '0 min');
    }

    function llenarTecnicos(tecnicos) {
        if (!Array.isArray(tecnicos)) {
            return;
        }

        var actual = ui.filtroTecnico.value;
        var opciones = '<option value="">Todos los técnicos</option>';
        tecnicos.forEach(function (item) {
            opciones += '<option value="' + escapeHtml(item.id) + '">'
                + escapeHtml(item.tecnico)
                + (Number(item.activo) === 1 ? '' : ' · Inactivo')
                + '</option>';
        });
        ui.filtroTecnico.innerHTML = opciones;
        ui.filtroTecnico.value = actual;
        estado.catalogosCargados = true;
    }

    function renderizarTabla(registros) {
        if (!Array.isArray(registros) || registros.length === 0) {
            ui.cuerpoTabla.innerHTML = '';
            ui.contenedorTabla.hidden = true;
            ui.estadoVacio.hidden = false;
            return;
        }

        ui.estadoVacio.hidden = true;
        ui.contenedorTabla.hidden = false;
        ui.cuerpoTabla.innerHTML = registros.map(filaHtml).join('');
    }

    function filaHtml(item) {
        var estadoInc = String(item.estado_incumplimiento || 'PENDIENTE');
        var situacion = etiquetaSituacion(item.situacion);
        var accionProgramar = item.puede_reprogramar
            ? '<a class="inc-btn inc-btn--mini inc-btn--secondary" href="solicitudes_programacion.php?solicitud_id='
                + encodeURIComponent(item.solicitud_id) + '">Reprogramar</a>'
            : '';

        return '<tr>'
            + '<td data-label="Mantenimiento">'
                + '<div class="inc-main-cell">'
                    + '<strong>' + escapeHtml(item.folio) + '</strong>'
                    + badge(tipoTexto(item.tipo_solicitud), 'neutral')
                    + '<span>' + escapeHtml(item.codigo_equipo || 'Sin código') + ' · '
                        + escapeHtml(item.nombre_equipo || 'Sin equipo') + '</span>'
                    + '<small>' + escapeHtml(item.ubicacion || 'Sin ubicación') + '</small>'
                + '</div>'
            + '</td>'
            + '<td data-label="Técnico">'
                + '<div class="inc-person">'
                    + '<span class="inc-avatar">' + escapeHtml(iniciales(item.tecnico)) + '</span>'
                    + '<div><strong>' + escapeHtml(item.tecnico) + '</strong>'
                    + '<small>' + escapeHtml(enumTexto(item.turno))
                        + (item.especialidad ? ' · ' + escapeHtml(item.especialidad) : '') + '</small></div>'
                + '</div>'
            + '</td>'
            + '<td data-label="Programación">'
                + '<div class="inc-date-cell">'
                    + '<strong>' + escapeHtml(fecha(item.fecha_programada)) + '</strong>'
                    + '<span>Límite: ' + escapeHtml(fecha(item.fecha_limite)) + '</span>'
                    + '<small>' + escapeHtml(item.atraso_texto || '0 min') + ' de atraso</small>'
                + '</div>'
            + '</td>'
            + '<td data-label="Situación">'
                + badge(item.estado_texto || enumTexto(estadoInc), claseEstado(estadoInc))
                + '<p class="inc-situation">' + escapeHtml(situacion) + '</p>'
            + '</td>'
            + '<td data-label="Estado actual">'
                + '<div class="inc-current">'
                    + '<strong>' + escapeHtml(enumTexto(item.estado_solicitud)) + '</strong>'
                    + '<span>Técnico: ' + escapeHtml(enumTexto(item.estado_asignacion)) + '</span>'
                    + '<small>Resultado: ' + escapeHtml(enumTexto(item.resultado_cumplimiento)) + '</small>'
                + '</div>'
            + '</td>'
            + '<td data-label="Acciones">'
                + '<div class="inc-actions">'
                    + '<button type="button" class="inc-btn inc-btn--mini inc-btn--primary" data-accion="detalle" data-id="'
                        + escapeHtml(item.incumplimiento_id) + '">Ver detalle</button>'
                    + accionProgramar
                + '</div>'
            + '</td>'
        + '</tr>';
    }

    function renderizarPaginacion(p) {
        var total = Number(p.total) || 0;
        var paginas = Number(p.total_paginas) || 1;
        var pagina = Number(p.pagina) || 1;

        if (total === 0 || paginas <= 1) {
            ui.contenedorPaginacion.hidden = true;
            ui.paginacion.innerHTML = '';
            return;
        }

        ui.contenedorPaginacion.hidden = false;
        ui.textoPaginacion.textContent = 'Página ' + pagina + ' de ' + paginas;
        var desde = Math.max(1, pagina - 2);
        var hasta = Math.min(paginas, pagina + 2);
        var html = botonPagina('Anterior', pagina - 1, pagina <= 1);

        if (desde > 1) {
            html += botonPagina('1', 1, false, pagina === 1);
            if (desde > 2) {
                html += '<span>…</span>';
            }
        }

        for (var i = desde; i <= hasta; i += 1) {
            html += botonPagina(String(i), i, false, i === pagina);
        }

        if (hasta < paginas) {
            if (hasta < paginas - 1) {
                html += '<span>…</span>';
            }
            html += botonPagina(String(paginas), paginas, false, pagina === paginas);
        }

        html += botonPagina('Siguiente', pagina + 1, pagina >= paginas);
        ui.paginacion.innerHTML = html;
    }

    function botonPagina(textoBoton, pagina, deshabilitado, activo) {
        return '<button type="button" data-pagina="' + pagina + '"'
            + (deshabilitado ? ' disabled' : '')
            + (activo ? ' class="is-active" aria-current="page"' : '')
            + '>' + escapeHtml(textoBoton) + '</button>';
    }

    async function abrirDetalle(id) {
        abrirModal(ui.modalDetalle);
        ui.detalleTitulo.textContent = 'Detalle del incumplimiento';
        ui.detalleSubtitulo.textContent = 'Consultando información...';
        ui.detalleContenido.innerHTML = '<div class="inc-loading"><span></span><p>Cargando detalle...</p></div>';
        ui.detalleAcciones.innerHTML = '<button type="button" class="inc-btn inc-btn--ghost" data-cerrar-modal="modalDetalle">Cerrar</button>';

        try {
            var datos = await solicitar(ENDPOINT + '&accion=DETALLE&id=' + encodeURIComponent(id) + '&_t=' + Date.now());
            estado.detalle = datos.registro;
            renderizarDetalle(datos);
        } catch (error) {
            ui.detalleContenido.innerHTML = '<div class="inc-inline-error">' + escapeHtml(error.message) + '</div>';
            ui.detalleSubtitulo.textContent = 'No fue posible cargar el expediente.';
        }
    }

    function renderizarDetalle(datos) {
        var r = datos.registro || {};
        ui.detalleTitulo.textContent = texto(r.folio, 'Detalle de cumplimiento');
        ui.detalleSubtitulo.textContent = texto(r.tecnico, 'Técnico') + ' · ' + texto(r.estado_texto, enumTexto(r.estado_incumplimiento));

        var resolucion = r.justificacion
            ? '<section class="inc-detail-section"><h3>Resolución administrativa</h3>'
                + '<div class="inc-resolution-box ' + claseEstado(r.estado_incumplimiento) + '">'
                    + '<strong>' + escapeHtml(r.estado_texto) + '</strong>'
                    + '<p>' + escapeHtml(r.justificacion) + '</p>'
                    + '<small>' + escapeHtml(texto(r.administrador_resolvio, 'Sistema'))
                        + (r.fecha_resolucion ? ' · ' + escapeHtml(fechaHora(r.fecha_resolucion)) : '') + '</small>'
                + '</div></section>'
            : '';

        ui.detalleContenido.innerHTML = ''
            + '<section class="inc-detail-metrics">'
                + metric('Situación', r.estado_texto || enumTexto(r.estado_incumplimiento), r.situacion ? etiquetaSituacion(r.situacion) : '—')
                + metric('Fecha límite', fechaHora(r.fecha_hora_limite), 'Programado: ' + fecha(r.fecha_programada))
                + metric('Atraso', r.atraso_texto || '0 min', 'Corte diario aplicado')
                + metric('Resultado técnico', enumTexto(r.resultado_cumplimiento), 'Asignación: ' + enumTexto(r.estado_asignacion))
            + '</section>'
            + '<div class="inc-detail-grid">'
                + '<section class="inc-detail-section"><h3>Mantenimiento</h3>'
                    + dato('Folio', r.folio)
                    + dato('Tipo', tipoTexto(r.tipo_solicitud))
                    + dato('Estado', enumTexto(r.estado_solicitud))
                    + dato('Prioridad', enumTexto(r.prioridad))
                    + dato('Equipo', (r.codigo_equipo || 'Sin código') + ' · ' + (r.nombre_equipo || 'Sin equipo'))
                    + dato('Ubicación', r.ubicacion)
                    + '<div class="inc-description"><span>Descripción</span><p>' + escapeHtml(texto(r.descripcion_solicitud, 'Sin descripción')) + '</p></div>'
                + '</section>'
                + '<section class="inc-detail-section"><h3>Técnico y ejecución</h3>'
                    + dato('Técnico', r.tecnico)
                    + dato('Turno', enumTexto(r.turno))
                    + dato('Especialidad', texto(r.especialidad, 'Sin especialidad'))
                    + dato('Estado de ejecución', enumTexto(texto(r.estado_ejecucion, 'SIN_EJECUCION')))
                    + dato('Inicio real', fechaHora(r.fecha_hora_inicio))
                    + dato('Fin real', fechaHora(r.fecha_hora_fin))
                    + dato('Tiempo activo', duracionSegundos(r.total_segundos_activos))
                    + dato('Tiempo en pausa', duracionSegundos(r.total_segundos_pausa))
                + '</section>'
            + '</div>'
            + resolucion
            + '<section class="inc-detail-section"><h3>Participantes de la solicitud</h3>'
                + participantesHtml(datos.participantes || [])
            + '</section>'
            + '<section class="inc-detail-section"><h3>Programaciones</h3>'
                + programacionesHtml(datos.programaciones || [])
            + '</section>'
            + '<section class="inc-detail-section"><h3>Historial reciente</h3>'
                + historialHtml(datos.historial || [])
            + '</section>';

        var botones = '<button type="button" class="inc-btn inc-btn--ghost" data-cerrar-modal="modalDetalle">Cerrar</button>';
        if (r.puede_reprogramar) {
            botones += '<a class="inc-btn inc-btn--secondary" href="solicitudes_programacion.php?solicitud_id=' + encodeURIComponent(r.solicitud_id) + '">Abrir programación</a>';
        }
        if (r.puede_justificar) {
            botones += '<button type="button" class="inc-btn inc-btn--secondary" data-accion="resolver" data-tipo="JUSTIFICAR" data-id="' + escapeHtml(r.incumplimiento_id) + '">Justificar</button>';
        }
        if (r.puede_no_realizado) {
            botones += '<button type="button" class="inc-btn inc-btn--danger" data-accion="resolver" data-tipo="NO_REALIZADO" data-id="' + escapeHtml(r.incumplimiento_id) + '">Marcar no realizado</button>';
        }
        ui.detalleAcciones.innerHTML = botones;
    }

    function participantesHtml(lista) {
        if (!Array.isArray(lista) || lista.length === 0) {
            return '<div class="inc-mini-empty">No hay participantes registrados.</div>';
        }

        return '<div class="inc-participants">' + lista.map(function (p) {
            return '<article><div><strong>' + escapeHtml(p.tecnico) + '</strong><small>'
                + escapeHtml(enumTexto(p.turno)) + '</small></div><div>'
                + badge(enumTexto(p.estado), 'neutral')
                + badge(enumTexto(p.resultado_cumplimiento), claseResultado(p.resultado_cumplimiento))
                + (p.estado_incumplimiento ? badge(enumTexto(p.estado_incumplimiento), claseEstado(p.estado_incumplimiento)) : '')
                + '</div></article>';
        }).join('') + '</div>';
    }

    function programacionesHtml(lista) {
        if (!Array.isArray(lista) || lista.length === 0) {
            return '<div class="inc-mini-empty">No existen programaciones.</div>';
        }

        return '<div class="inc-program-list">' + lista.map(function (p) {
            return '<article class="' + (Number(p.es_actual) === 1 ? 'is-current' : '') + '">'
                + '<div><strong>' + escapeHtml(fecha(p.fecha_programada)) + '</strong><span>Límite ' + escapeHtml(fecha(p.fecha_limite)) + '</span></div>'
                + '<div>' + badge(enumTexto(p.estado), Number(p.es_actual) === 1 ? 'primary' : 'neutral')
                + '<small>' + escapeHtml(texto(p.motivo_reprogramacion || p.motivo_programacion || p.motivo_cancelacion, 'Sin observación')) + '</small></div>'
            + '</article>';
        }).join('') + '</div>';
    }

    function historialHtml(lista) {
        if (!Array.isArray(lista) || lista.length === 0) {
            return '<div class="inc-mini-empty">No hay eventos registrados.</div>';
        }

        return '<ol class="inc-timeline">' + lista.map(function (h) {
            return '<li><span></span><div><strong>' + escapeHtml(enumTexto(h.evento)) + '</strong>'
                + '<p>' + escapeHtml(h.descripcion) + '</p><small>'
                + escapeHtml(fechaHora(h.fecha_evento)) + ' · ' + escapeHtml(texto(h.actor, h.actor_tipo))
                + '</small></div></li>';
        }).join('') + '</ol>';
    }

    function abrirResolucion(id, tipoResolucion) {
        var r = estado.detalle;
        if (!r || Number(r.incumplimiento_id) !== Number(id)) {
            r = estado.registros.find(function (item) {
                return Number(item.incumplimiento_id) === Number(id);
            }) || null;
        }

        ui.formResolver.reset();
        ui.resolverId.value = String(id);
        ui.resolverTipo.value = tipoResolucion;
        ui.contadorMotivo.textContent = '0';

        if (tipoResolucion === 'JUSTIFICAR') {
            ui.resolverTitulo.textContent = 'Justificar incumplimiento';
            ui.resolverSubtitulo.textContent = 'La actividad podrá continuar o reprogramarse.';
            ui.resolverMotivoEtiqueta.textContent = 'Justificación administrativa *';
            ui.resolverMotivo.placeholder = 'Explica la causa válida del incumplimiento y las medidas tomadas...';
            ui.resolverDecision.className = 'inc-decision inc-decision--info';
            ui.resolverDecision.innerHTML = '<span class="inc-decision__icon">i</span><div><strong>Se conserva el mantenimiento pendiente.</strong><p>La justificación no finaliza el trabajo ni cambia su programación.</p></div>';
            ui.btnConfirmarResolucion.className = 'inc-btn inc-btn--primary';
            ui.btnConfirmarResolucion.textContent = 'Guardar justificación';
        } else {
            ui.resolverTitulo.textContent = 'Marcar participación no realizada';
            ui.resolverSubtitulo.textContent = r ? r.tecnico + ' · ' + r.folio : 'Confirma la decisión administrativa.';
            ui.resolverMotivoEtiqueta.textContent = 'Motivo de no realización *';
            ui.resolverMotivo.placeholder = 'Explica por qué el técnico no realizó la actividad y qué deberá hacerse después...';
            ui.resolverDecision.className = 'inc-decision inc-decision--danger';
            ui.resolverDecision.innerHTML = '<span class="inc-decision__icon">!</span><div><strong>El técnico será retirado de esta asignación.</strong><p>El mantenimiento seguirá atrasado y podrá asignarse nuevamente desde Programar y asignar.</p></div>';
            ui.btnConfirmarResolucion.className = 'inc-btn inc-btn--danger';
            ui.btnConfirmarResolucion.textContent = 'Confirmar no realizado';
        }

        abrirModal(ui.modalResolver);
        window.setTimeout(function () { ui.resolverMotivo.focus(); }, 80);
    }

    async function guardarResolucion(evento) {
        evento.preventDefault();

        if (!ui.formResolver.checkValidity()) {
            ui.formResolver.reportValidity();
            return;
        }

        if (ui.resolverMotivo.value.trim().length < 15) {
            toast('El motivo debe contener al menos 15 caracteres.', 'error');
            ui.resolverMotivo.focus();
            return;
        }

        bloquearBoton(ui.btnConfirmarResolucion, true, 'Guardando...');

        try {
            var form = new FormData(ui.formResolver);
            form.set('inc_api', '1');
            form.set('accion', 'RESOLVER');
            form.set('csrf_token', CSRF_TOKEN);
            var datos = await solicitar(ENDPOINT, { method: 'POST', body: form });

            if (datos.csrf_token) {
                CSRF_TOKEN = datos.csrf_token;
            }

            cerrarModal(ui.modalResolver);
            cerrarModal(ui.modalDetalle);
            toast(datos.mensaje, 'success');
            await cargarListado(false);
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            bloquearBoton(ui.btnConfirmarResolucion, false, 'Guardar resolución');
        }
    }

    function exportarCsv() {
        var p = parametrosFiltros();
        p.set('accion', 'EXPORTAR');
        p.set('inc_api', '1');
        p.delete('_t');
        window.location.href = window.location.pathname + '?' + p.toString();
    }

    function manejarClick(evento) {
        var cerrar = evento.target.closest('[data-cerrar-modal]');
        if (cerrar) {
            cerrarModal(document.getElementById(cerrar.getAttribute('data-cerrar-modal')));
            return;
        }

        var pagina = evento.target.closest('[data-pagina]');
        if (pagina && !pagina.disabled) {
            estado.pagina = Number(pagina.getAttribute('data-pagina')) || 1;
            cargarListado(false);
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        var accion = evento.target.closest('[data-accion]');
        if (!accion) {
            return;
        }

        var nombre = accion.getAttribute('data-accion');
        var id = Number(accion.getAttribute('data-id'));
        if (nombre === 'detalle') {
            abrirDetalle(id);
        } else if (nombre === 'resolver') {
            abrirResolucion(id, accion.getAttribute('data-tipo'));
        }
    }

    function abrirModal(modal) {
        if (!modal) { return; }
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('inc-modal-open');
    }

    function cerrarModal(modal) {
        if (!modal) { return; }
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        if (ui.modalDetalle.hidden && ui.modalResolver.hidden) {
            document.body.classList.remove('inc-modal-open');
        }
    }

    async function solicitar(url, opciones) {
        var config = opciones || {};
        config.headers = Object.assign({}, config.headers || {}, {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        });
        config.credentials = 'same-origin';
        config.cache = 'no-store';

        var respuesta = await fetch(url, config);
        var textoRespuesta = await respuesta.text();
        var datos;

        try {
            datos = JSON.parse(textoRespuesta);
        } catch (error) {
            throw new Error(
                'El servidor devolvió una respuesta no válida (HTTP ' + respuesta.status
                + '). Revisa el registro de PHP.'
            );
        }

        if (datos.sesion_expirada && datos.redirect) {
            window.location.href = datos.redirect;
            throw new Error(datos.mensaje || 'Tu sesión expiró.');
        }

        if (!respuesta.ok || datos.success !== true) {
            var mensaje = datos.mensaje || 'No fue posible completar la operación.';
            if (datos.referencia) {
                mensaje += ' Referencia: ' + datos.referencia + '.';
            }
            throw new Error(mensaje);
        }

        return datos;
    }

    function mostrarEstado(mensaje, tipo, ocultar) {
        ui.estadoPagina.textContent = mensaje;
        ui.estadoPagina.className = 'inc-status inc-status--' + (tipo || 'info');
        ui.estadoPagina.hidden = false;
        if (ocultar) {
            window.setTimeout(function () { ui.estadoPagina.hidden = true; }, 3000);
        }
    }

    function toast(mensaje, tipo) {
        window.clearTimeout(temporizadorToast);
        ui.toast.textContent = mensaje;
        ui.toast.className = 'inc-toast inc-toast--' + (tipo || 'info');
        ui.toast.hidden = false;
        temporizadorToast = window.setTimeout(function () { ui.toast.hidden = true; }, 4200);
    }

    function bloquearBoton(boton, bloqueado, textoBoton) {
        if (!boton) { return; }
        boton.disabled = bloqueado;
        if (!boton.dataset.textoOriginal) {
            boton.dataset.textoOriginal = boton.innerHTML;
        }
        boton.innerHTML = bloqueado ? escapeHtml(textoBoton) : textoBoton;
    }

    function metric(titulo, valor, ayuda) {
        return '<article><span>' + escapeHtml(titulo) + '</span><strong>' + escapeHtml(texto(valor, '—'))
            + '</strong><small>' + escapeHtml(texto(ayuda, '—')) + '</small></article>';
    }

    function dato(titulo, valor) {
        return '<div class="inc-detail-row"><span>' + escapeHtml(titulo) + '</span><strong>'
            + escapeHtml(texto(valor, '—')) + '</strong></div>';
    }

    function badge(etiqueta, clase) {
        return '<span class="inc-badge inc-badge--' + escapeHtml(clase || 'neutral') + '">'
            + escapeHtml(texto(etiqueta, '—')) + '</span>';
    }

    function claseEstado(estadoInc) {
        var mapa = {
            PENDIENTE: 'warning',
            CUMPLIDO_TARDE: 'late',
            JUSTIFICADO: 'info',
            NO_REALIZADO: 'danger'
        };
        return mapa[String(estadoInc || '')] || 'neutral';
    }

    function claseResultado(resultado) {
        var mapa = {
            A_TIEMPO: 'success',
            TARDE: 'late',
            NO_REALIZADO: 'danger',
            NO_APLICA: 'neutral',
            PENDIENTE: 'warning'
        };
        return mapa[String(resultado || '')] || 'neutral';
    }

    function etiquetaSituacion(valor) {
        var mapa = {
            VENCIDO_SIN_INICIAR: 'Venció y todavía no se ha iniciado',
            EN_PROCESO_TARDE: 'Se está trabajando después del límite',
            PAUSADO_TARDE: 'Está pausado después del límite',
            FINALIZADO_POR_CONCILIAR: 'Finalizado; pendiente de conciliación',
            ASIGNACION_RETIRADA: 'La asignación ya fue retirada',
            PENDIENTE: 'Pendiente de resolver',
            CUMPLIDO_TARDE: 'Se completó después de la fecha límite',
            JUSTIFICADO: 'La demora cuenta con justificación',
            NO_REALIZADO: 'La participación fue declarada no realizada'
        };
        return mapa[String(valor || '')] || enumTexto(valor);
    }

    function tipoTexto(tipo) {
        var mapa = {
            CORRECTIVO_PROGRAMABLE: 'Correctivo programable',
            MODIFICACION_MEJORA: 'Modificación o mejora',
            CORRECTIVO_URGENTE: 'Correctivo urgente',
            RUTINARIO: 'Rutinario'
        };
        return mapa[String(tipo || '')] || enumTexto(tipo);
    }

    function enumTexto(valor) {
        var str = String(valor || '').replaceAll('_', ' ').toLowerCase();
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : '—';
    }

    function fecha(valor) {
        if (!valor) { return '—'; }
        var partes = String(valor).slice(0, 10).split('-');
        return partes.length === 3 ? partes[2] + '/' + partes[1] + '/' + partes[0] : String(valor);
    }

    function fechaHora(valor) {
        if (!valor) { return '—'; }
        var str = String(valor).replace('T', ' ');
        return fecha(str.slice(0, 10)) + (str.length >= 16 ? ' ' + str.slice(11, 16) : '');
    }

    function fechaHoraCorta(valor) {
        return valor ? fechaHora(valor) : 'ahora';
    }

    function duracionSegundos(valor) {
        var segundos = Math.max(0, Number(valor) || 0);
        var horas = Math.floor(segundos / 3600);
        var minutos = Math.floor((segundos % 3600) / 60);
        var seg = Math.floor(segundos % 60);
        return String(horas).padStart(2, '0') + ':' + String(minutos).padStart(2, '0') + ':' + String(seg).padStart(2, '0');
    }

    function iniciales(nombre) {
        var partes = String(nombre || 'T').trim().split(/\s+/).filter(Boolean);
        return (partes[0] ? partes[0].charAt(0) : 'T') + (partes[1] ? partes[1].charAt(0) : '');
    }

    function numero(valor) {
        return new Intl.NumberFormat('es-MX').format(Number(valor) || 0);
    }

    function texto(valor, alternativa) {
        if (valor === null || valor === undefined || String(valor).trim() === '') {
            return alternativa || '—';
        }
        return String(valor);
    }

    function escapeHtml(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
}());
</script>
</body> 
</html>