<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/seguridad.php';
sm_requerir_sesion(['ADMIN'], false);

$solicitudInicial = filter_input(INPUT_GET, 'solicitud_id', FILTER_VALIDATE_INT);
$solicitudInicial = $solicitudInicial && $solicitudInicial > 0
    ? (int) $solicitudInicial
    : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda semanal | Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="../css/style_agenda_semanal.css?v=20260730.3">
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<main class="contenido-principal aseg-page">
    <header class="aseg-heading aseg-hero">
        <div class="aseg-heading__copy aseg-hero__copy">
            <div class="aseg-hero__eyebrow">
                <span class="aseg-hero__eyebrow-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M7 3v3M17 3v3M4.5 9h15M6.5 5h11a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <path d="m8.5 14 2.1 2.1 4.9-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span>OPERACIÓN SEMANAL</span>
            </div>
            <h1>Agenda semanal</h1>
            <p>
                Consulta qué debe realizarse cada día, quién está asignado y cuáles trabajos requieren atención inmediata.
            </p>
            <div class="aseg-hero__meta" aria-label="Características de la agenda">
                <span><i aria-hidden="true"></i> Programaciones vigentes</span>
                <span><i aria-hidden="true"></i> Urgencias publicadas</span>
                <span><i aria-hidden="true"></i> Seguimiento operativo</span>
            </div>
        </div>

        <div class="aseg-hero__aside">
            <div class="aseg-hero__status">
                <span class="aseg-hero__status-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/>
                    </svg>
                </span>
                <span>
                    <small>VISTA OPERATIVA</small>
                    <strong>Planificación semanal</strong>
                </span>
                <i class="aseg-hero__status-pulse" aria-hidden="true"></i>
            </div>

            <div class="aseg-heading__actions">
                <button type="button" class="aseg-btn aseg-btn--soft aseg-btn--hero" id="btnActualizar">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M20 11a8 8 0 1 0-2.34 5.66" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <path d="M20 5v6h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Actualizar
                </button>
                <a class="aseg-btn aseg-btn--primary aseg-btn--hero" href="solicitudes_programacion.php">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                    </svg>
                    Programar y asignar
                </a>
            </div>
        </div>
    </header>

    <section class="aseg-week" aria-labelledby="tituloSemana">
        <div class="aseg-week__head">
            <div class="aseg-week__title">
                <span class="aseg-week__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M7 3v3M17 3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
                <div>
                    <span class="aseg-eyebrow">SEMANA VISIBLE</span>
                    <h2 id="tituloSemana">Cargando semana...</h2>
                    <p id="subtituloSemana">Preparando agenda operativa.</p>
                </div>
            </div>
            <div class="aseg-week__navigation" aria-label="Cambiar semana">
                <button type="button" class="aseg-icon-btn" id="btnSemanaAnterior" aria-label="Semana anterior">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m14.5 6-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <button type="button" class="aseg-btn aseg-btn--soft" id="btnSemanaActual">Esta semana</button>
                <button type="button" class="aseg-icon-btn" id="btnSemanaSiguiente" aria-label="Semana siguiente">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m9.5 6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
        </div>
        <div class="aseg-days" id="diasSemana"></div>
        <div class="aseg-week__legend" aria-label="Guía visual de la semana">
            <span><i class="aseg-legend-dot aseg-legend-dot--today" aria-hidden="true"></i> Día actual</span>
            <span><i class="aseg-legend-dot aseg-legend-dot--selected" aria-hidden="true"></i> Día seleccionado</span>
            <span><i class="aseg-legend-dot aseg-legend-dot--disabled" aria-hidden="true"></i> Día inhábil</span>
            <span><i class="aseg-legend-count" aria-hidden="true">3</i> Actividades registradas</span>
        </div>
    </section>

    <div class="aseg-status" id="estadoPagina" role="status" aria-live="polite">
        Cargando la agenda semanal...
    </div>

    <section class="aseg-kpis" aria-label="Resumen de la semana">
        <article class="aseg-kpi aseg-kpi--total">
            <span class="aseg-kpi__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/><path d="M7 9h10M7 13h10M7 17h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </span>
            <div>
                <span>Actividades</span>
                <strong id="kpiTotal">0</strong>
                <small>En la semana visible</small>
            </div>
        </article>
        <article class="aseg-kpi aseg-kpi--pending">
            <span class="aseg-kpi__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5V12l3 1.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </span>
            <div>
                <span>Por iniciar</span>
                <strong id="kpiPorIniciar">0</strong>
                <small>Programadas o publicadas</small>
            </div>
        </article>
        <article class="aseg-kpi aseg-kpi--active">
            <span class="aseg-kpi__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M8 5.5v13l10-6.5L8 5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
            </span>
            <div>
                <span>En curso</span>
                <strong id="kpiEnCurso">0</strong>
                <small>Trabajando o en pausa</small>
            </div>
        </article>
        <article class="aseg-kpi aseg-kpi--late">
            <span class="aseg-kpi__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M12 4 3.8 19h16.4L12 4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v4.5M12 16.7v.1" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
            </span>
            <div>
                <span>Atrasadas</span>
                <strong id="kpiAtrasados">0</strong>
                <small>Requieren seguimiento</small>
            </div>
        </article>
        <article class="aseg-kpi aseg-kpi--done">
            <span class="aseg-kpi__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="m8.2 12.2 2.4 2.4 5.2-5.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <div>
                <span>Terminadas</span>
                <strong id="kpiTerminados">0</strong>
                <small>Con cierre registrado</small>
            </div>
        </article>
    </section>

    <section class="aseg-toolbar" aria-label="Filtros de agenda">
        <header class="aseg-toolbar__head">
            <div class="aseg-toolbar__title">
                <span class="aseg-toolbar__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
                <div>
                    <span class="aseg-eyebrow">CONTROL DE AGENDA</span>
                    <h2>Filtrar actividades</h2>
                    <p>Ubica rápidamente un folio, equipo, técnico o situación operativa.</p>
                </div>
            </div>
            <div class="aseg-toolbar__exports" aria-label="Exportaciones">
                <button type="button" class="aseg-btn aseg-btn--excel" id="btnExportar">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.5h7l4 4V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7"/><path d="M14 3.5V8h4M9 12l5 5M14 12l-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    Excel
                </button>
                <button type="button" class="aseg-btn aseg-btn--pdf" id="btnExportarPdf">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.5h7l4 4V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7"/><path d="M14 3.5V8h4M8.5 16.5h7M8.5 13.5h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    PDF
                </button>
            </div>
        </header>

        <div class="aseg-toolbar__main">
            <label class="aseg-search">
                <span class="aseg-field-label">Buscar</span>
                <span class="aseg-search__box">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" stroke="currentColor" stroke-width="1.8"/><path d="m15.5 15.5 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        placeholder="Folio, equipo, ubicación o técnico"
                        autocomplete="off"
                    >
                </span>
            </label>

            <label class="aseg-field">
                <span class="aseg-field-label">Técnico</span>
                <select id="filtroTecnico">
                    <option value="">Todos los técnicos</option>
                </select>
            </label>

            <label class="aseg-field">
                <span class="aseg-field-label">Situación</span>
                <select id="filtroEstado">
                    <option value="">Todas</option>
                    <option value="POR_INICIAR">Por iniciar</option>
                    <option value="EN_CURSO">En curso</option>
                    <option value="ATRASADO">Atrasadas</option>
                    <option value="TERMINADO">Terminadas</option>
                </select>
            </label>

            <label class="aseg-field aseg-field--compact">
                <span class="aseg-field-label">Tipo</span>
                <select id="filtroTipo">
                    <option value="">Todos</option>
                    <option value="CORRECTIVO_PROGRAMABLE">Correctivo programable</option>
                    <option value="MODIFICACION_MEJORA">Modificación o mejora</option>
                    <option value="CORRECTIVO_URGENTE">Correctivo urgente</option>
                    <option value="RUTINARIO">Rutinario</option>
                </select>
            </label>
        </div>

        <div class="aseg-toolbar__secondary">
            <div class="aseg-segmented" role="group" aria-label="Forma de agrupación">
                <button type="button" class="is-active" data-vista="DIA">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 4h14v16H5V4Z" stroke="currentColor" stroke-width="1.7"/><path d="M8 2.8v3M16 2.8v3M5 8h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    Por día
                </button>
                <button type="button" data-vista="TECNICO">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 19c.7-3.2 2.5-5 5.5-5s4.8 1.8 5.5 5M16 9h4M18 7v4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    Por técnico
                </button>
            </div>
            <button type="button" class="aseg-btn aseg-btn--ghost" id="btnLimpiarFiltros">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h14M8 12h8M10.5 17h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                Limpiar filtros
            </button>
        </div>
    </section>

    <section class="aseg-notices" id="avisosSemana" hidden aria-label="Avisos importantes"></section>

    <section class="aseg-content" aria-live="polite">
        <header class="aseg-content__head">
            <div class="aseg-content__title">
                <span class="aseg-content__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M4 6.5h16M4 12h16M4 17.5h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <div>
                    <p class="aseg-eyebrow" id="etiquetaVista">AGENDA POR DÍA</p>
                    <h2 id="tituloResultados">Actividades de la semana</h2>
                    <p class="aseg-content__subtitle">Selecciona una tarjeta para consultar programación, técnicos, tiempos y seguimiento.</p>
                </div>
            </div>
            <span class="aseg-result-count" id="contadorResultados">0 actividades</span>
        </header>

        <div class="aseg-board-shell">
            <div class="aseg-board" id="tableroAgenda"></div>

            <div class="aseg-empty" id="estadoVacio" hidden>
                <span aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="m8.5 12.2 2.2 2.2 4.8-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <h3>No hay actividades con estos filtros</h3>
                <p>Prueba otro día, técnico o situación.</p>
                <button type="button" class="aseg-btn aseg-btn--soft" id="btnVacioLimpiar">Mostrar toda la semana</button>
            </div>
        </div>
    </section>

    <div class="aseg-tools-background" aria-hidden="true">
        <img src="../imagenes/herramienta_abajo.png" alt="">
    </div>
</main>

<div class="aseg-modal" id="modalDetalle" role="dialog" aria-modal="true" aria-labelledby="detalleTitulo" hidden>
    <div class="aseg-modal__dialog">
        <header class="aseg-modal__head">
            <div>
                <p class="aseg-eyebrow">DETALLE OPERATIVO</p>
                <h2 id="detalleTitulo">Actividad</h2>
                <p id="detalleSubtitulo">Información necesaria para dar seguimiento.</p>
            </div>
            <button type="button" class="aseg-modal__close" id="btnCerrarModal" aria-label="Cerrar">×</button>
        </header>

        <div class="aseg-modal__loading" id="detalleCargando">
            <span class="aseg-spinner" aria-hidden="true"></span>
            <p>Cargando información...</p>
        </div>

        <div class="aseg-modal__error" id="detalleError" hidden>
            <strong>No fue posible abrir el detalle</strong>
            <p id="detalleErrorMensaje">Inténtalo nuevamente.</p>
        </div>

        <div class="aseg-modal__body" id="detalleContenido" hidden></div>

        <footer class="aseg-modal__foot">
            <a class="aseg-btn aseg-btn--ghost" id="btnVerExpediente" href="solicitudes_historial.php" hidden>
                Ver expediente
            </a>
            <a class="aseg-btn aseg-btn--soft" id="btnEditarProgramacion" href="solicitudes_programacion.php" hidden>
                Programar o reasignar
            </a>
            <button type="button" class="aseg-btn aseg-btn--primary" id="btnCerrarModalPie">Cerrar</button>
        </footer>
    </div>
</div>

<div class="aseg-toast" id="toast" role="status" aria-live="polite" hidden></div>

<script>
(function () {
    'use strict';

    var ENDPOINT = '../funciones/agenda_semanal_funciones.php';
    var SOLICITUD_INICIAL = <?= (int) $solicitudInicial ?>;

    var estado = {
        semanaInicio: '',
        semanaFin: '',
        dias: [],
        actividades: [],
        cargaTecnicos: [],
        resumen: {},
        vista: 'DIA',
        diaSeleccionado: '',
        filtros: {
            busqueda: '',
            tecnico: '',
            estado: '',
            tipo: ''
        },
        cargando: false,
        solicitudAbierta: 0
    };

    var elementos = {};

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        capturarElementos();
        registrarEventos();
        cargarAgenda('');
    }

    function capturarElementos() {
        var ids = [
            'btnActualizar', 'btnSemanaAnterior', 'btnSemanaActual', 'btnSemanaSiguiente',
            'tituloSemana', 'subtituloSemana', 'diasSemana', 'estadoPagina',
            'kpiTotal', 'kpiPorIniciar', 'kpiEnCurso', 'kpiAtrasados', 'kpiTerminados',
            'filtroBusqueda', 'filtroTecnico', 'filtroEstado', 'filtroTipo',
            'btnLimpiarFiltros', 'btnExportar', 'btnExportarPdf', 'avisosSemana', 'etiquetaVista',
            'tituloResultados', 'contadorResultados', 'tableroAgenda', 'estadoVacio',
            'btnVacioLimpiar', 'modalDetalle', 'detalleTitulo', 'detalleSubtitulo',
            'btnCerrarModal', 'btnCerrarModalPie', 'detalleCargando', 'detalleError',
            'detalleErrorMensaje', 'detalleContenido', 'btnVerExpediente',
            'btnEditarProgramacion', 'toast'
        ];

        ids.forEach(function (id) {
            elementos[id] = document.getElementById(id);
        });

        elementos.botonesVista = Array.prototype.slice.call(
            document.querySelectorAll('[data-vista]')
        );
    }

    function registrarEventos() {
        elementos.btnActualizar.addEventListener('click', function () {
            cargarAgenda(estado.semanaInicio);
        });

        elementos.btnSemanaAnterior.addEventListener('click', function () {
            cargarAgenda(moverDias(estado.semanaInicio, -7));
        });

        elementos.btnSemanaSiguiente.addEventListener('click', function () {
            cargarAgenda(moverDias(estado.semanaInicio, 7));
        });

        elementos.btnSemanaActual.addEventListener('click', function () {
            cargarAgenda('');
        });

        elementos.filtroBusqueda.addEventListener('input', function () {
            estado.filtros.busqueda = normalizarTexto(this.value);
            renderizarAgenda();
        });

        elementos.filtroTecnico.addEventListener('change', function () {
            estado.filtros.tecnico = this.value;
            renderizarAgenda();
        });

        elementos.filtroEstado.addEventListener('change', function () {
            estado.filtros.estado = this.value;
            renderizarAgenda();
        });

        elementos.filtroTipo.addEventListener('change', function () {
            estado.filtros.tipo = this.value;
            renderizarAgenda();
        });

        elementos.botonesVista.forEach(function (boton) {
            boton.addEventListener('click', function () {
                estado.vista = this.getAttribute('data-vista') || 'DIA';
                elementos.botonesVista.forEach(function (item) {
                    item.classList.toggle('is-active', item === boton);
                });
                renderizarAgenda();
            });
        });

        elementos.btnLimpiarFiltros.addEventListener('click', limpiarFiltros);
        elementos.btnVacioLimpiar.addEventListener('click', limpiarFiltros);
        elementos.btnExportar.addEventListener('click', exportarExcel);
        elementos.btnExportarPdf.addEventListener('click', exportarPDF);

        elementos.diasSemana.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-fecha]');
            if (!boton) {
                return;
            }

            var fecha = boton.getAttribute('data-fecha') || '';
            estado.diaSeleccionado = estado.diaSeleccionado === fecha ? '' : fecha;
            renderizarDias();
            renderizarAgenda();
        });

        elementos.tableroAgenda.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-detalle]');
            if (!boton) {
                return;
            }

            abrirDetalle(numero(boton.getAttribute('data-detalle')));
        });

        elementos.btnCerrarModal.addEventListener('click', cerrarModal);
        elementos.btnCerrarModalPie.addEventListener('click', cerrarModal);

        elementos.modalDetalle.addEventListener('click', function (evento) {
            if (evento.target === elementos.modalDetalle) {
                cerrarModal();
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && !elementos.modalDetalle.hidden) {
                cerrarModal();
            }
        });
    }

    async function cargarAgenda(semana) {
        if (estado.cargando) {
            return;
        }

        estado.cargando = true;
        establecerEstadoPagina('Cargando la agenda semanal...', 'loading');
        alternarControlesSemana(true);

        try {
            var params = new URLSearchParams();
            params.set('accion', 'inicial');
            if (semana) {
                params.set('semana', semana);
            }

            var respuesta = await solicitarJSON(ENDPOINT + '?' + params.toString());

            estado.semanaInicio = respuesta.semana.inicio;
            estado.semanaFin = respuesta.semana.fin;
            estado.dias = Array.isArray(respuesta.semana.dias) ? respuesta.semana.dias : [];
            estado.actividades = Array.isArray(respuesta.actividades) ? respuesta.actividades : [];
            estado.cargaTecnicos = Array.isArray(respuesta.carga_tecnicos) ? respuesta.carga_tecnicos : [];
            estado.resumen = respuesta.resumen || {};
            estado.diaSeleccionado = '';

            renderizarTodo();
            establecerEstadoPagina(
                estado.actividades.length === 0
                    ? 'La semana no tiene actividades registradas.'
                    : 'Agenda actualizada. Los atrasos y estados están sincronizados.',
                'success'
            );

            if (SOLICITUD_INICIAL > 0 && estado.solicitudAbierta === 0) {
                estado.solicitudAbierta = SOLICITUD_INICIAL;
                abrirDetalle(SOLICITUD_INICIAL);
            }
        } catch (error) {
            establecerEstadoPagina(error.message || 'No fue posible cargar la agenda.', 'error');
            mostrarToast(error.message || 'No fue posible cargar la agenda.', 'error');
        } finally {
            estado.cargando = false;
            alternarControlesSemana(false);
        }
    }

    function renderizarTodo() {
        renderizarEncabezadoSemana();
        renderizarDias();
        renderizarKPIs();
        renderizarCatalogoTecnicos();
        renderizarAvisos();
        renderizarAgenda();
    }

    function renderizarEncabezadoSemana() {
        elementos.tituloSemana.textContent = tituloRangoSemana(estado.semanaInicio, estado.semanaFin);

        var hoy = fechaISO(new Date());
        var esActual = hoy >= estado.semanaInicio && hoy <= estado.semanaFin;
        elementos.subtituloSemana.textContent = esActual
            ? 'Semana actual · el día de hoy se encuentra resaltado.'
            : 'Consulta operativa del ' + fechaCorta(estado.semanaInicio) + ' al ' + fechaCorta(estado.semanaFin) + '.';
    }

    function renderizarDias() {
        var conteos = {};
        estado.actividades.forEach(function (actividad) {
            var fecha = actividad.fecha_agenda || '';
            conteos[fecha] = (conteos[fecha] || 0) + 1;
        });

        var hoy = fechaISO(new Date());
        var html = estado.dias.map(function (dia) {
            var fecha = dia.fecha;
            var cantidad = conteos[fecha] || 0;
            var clases = ['aseg-day'];

            if (fecha === hoy) {
                clases.push('is-today');
            }
            if (estado.diaSeleccionado === fecha) {
                clases.push('is-selected');
            }
            if (!dia.es_habil) {
                clases.push('is-disabled-day');
            }

            var estadoDia = dia.configurado
                ? (dia.es_habil ? textoTipoDia(dia.tipo_dia) : 'Inhábil')
                : 'Sin configurar';

            return '' +
                '<button type="button" class="' + clases.join(' ') + '" data-fecha="' + escapar(fecha) + '">' +
                    '<span class="aseg-day__name">' + escapar(nombreDia(fecha)) + '</span>' +
                    '<strong>' + escapar(numeroDiaMes(fecha)) + '</strong>' +
                    '<small>' + escapar(nombreMesCorto(fecha)) + '</small>' +
                    '<b>' + cantidad + '</b>' +
                    '<em title="' + escapar(dia.motivo || estadoDia) + '">' + escapar(estadoDia) + '</em>' +
                '</button>';
        }).join('');

        elementos.diasSemana.innerHTML = html;
    }

    function renderizarKPIs() {
        elementos.kpiTotal.textContent = numero(estado.resumen.total);
        elementos.kpiPorIniciar.textContent = numero(estado.resumen.por_iniciar);
        elementos.kpiEnCurso.textContent = numero(estado.resumen.en_curso);
        elementos.kpiAtrasados.textContent = numero(estado.resumen.atrasados);
        elementos.kpiTerminados.textContent = numero(estado.resumen.terminados);
    }

    function renderizarCatalogoTecnicos() {
        var valorActual = elementos.filtroTecnico.value;
        var opciones = ['<option value="">Todos los técnicos</option>'];

        estado.cargaTecnicos.forEach(function (tecnico) {
            opciones.push(
                '<option value="' + numero(tecnico.id) + '">' +
                    escapar(tecnico.tecnico) + ' · ' + numero(tecnico.total) +
                '</option>'
            );
        });

        opciones.push('<option value="SIN_TECNICO">Sin técnico asignado</option>');
        elementos.filtroTecnico.innerHTML = opciones.join('');
        elementos.filtroTecnico.value = valorActual;
    }

    function renderizarAvisos() {
        var avisos = [];

        if (numero(estado.resumen.atrasados) > 0) {
            avisos.push(
                '<article class="aseg-notice aseg-notice--danger">' +
                    '<span aria-hidden="true">!</span>' +
                    '<div><strong>' + numero(estado.resumen.atrasados) + ' actividad(es) atrasada(s)</strong>' +
                    '<p>Revisa su programación o da seguimiento al personal asignado.</p></div>' +
                '</article>'
            );
        }

        if (numero(estado.resumen.sin_tecnico) > 0) {
            avisos.push(
                '<article class="aseg-notice aseg-notice--warning">' +
                    '<span aria-hidden="true">○</span>' +
                    '<div><strong>' + numero(estado.resumen.sin_tecnico) + ' actividad(es) sin técnico</strong>' +
                    '<p>Las urgencias pueden estar esperando aceptación; las demás deben asignarse.</p></div>' +
                '</article>'
            );
        }

        if (numero(estado.resumen.trabajos_peligrosos) > 0) {
            avisos.push(
                '<article class="aseg-notice">' +
                    '<span aria-hidden="true">△</span>' +
                    '<div><strong>' + numero(estado.resumen.trabajos_peligrosos) + ' trabajo(s) con riesgo</strong>' +
                    '<p>Verifica las condiciones de seguridad antes de iniciar.</p></div>' +
                '</article>'
            );
        }

        elementos.avisosSemana.hidden = avisos.length === 0;
        elementos.avisosSemana.innerHTML = avisos.join('');
    }

    function renderizarAgenda() {
        var actividades = filtrarActividades();
        elementos.contadorResultados.textContent = actividades.length === 1
            ? '1 actividad'
            : actividades.length + ' actividades';

        elementos.estadoVacio.hidden = actividades.length !== 0;
        elementos.tableroAgenda.hidden = actividades.length === 0;

        if (actividades.length === 0) {
            elementos.tableroAgenda.innerHTML = '';
            return;
        }

        if (estado.vista === 'TECNICO') {
            elementos.etiquetaVista.textContent = 'AGENDA POR TÉCNICO';
            elementos.tituloResultados.textContent = 'Carga del equipo de mantenimiento';
            elementos.tableroAgenda.innerHTML = renderizarPorTecnico(actividades);
        } else {
            elementos.etiquetaVista.textContent = 'AGENDA POR DÍA';
            elementos.tituloResultados.textContent = estado.diaSeleccionado
                ? 'Actividades del ' + fechaLarga(estado.diaSeleccionado)
                : 'Actividades de la semana';
            elementos.tableroAgenda.innerHTML = renderizarPorDia(actividades);
        }
    }

    function filtrarActividades() {
        var busqueda = estado.filtros.busqueda;
        var tecnico = estado.filtros.tecnico;
        var grupo = estado.filtros.estado;
        var tipo = estado.filtros.tipo;

        return estado.actividades.filter(function (actividad) {
            if (estado.diaSeleccionado && actividad.fecha_agenda !== estado.diaSeleccionado) {
                return false;
            }

            if (grupo && actividad.grupo_estado !== grupo) {
                return false;
            }

            if (tipo && actividad.tipo_solicitud !== tipo) {
                return false;
            }

            if (tecnico === 'SIN_TECNICO' && Array.isArray(actividad.tecnicos) && actividad.tecnicos.length > 0) {
                return false;
            }

            if (tecnico && tecnico !== 'SIN_TECNICO') {
                var asignado = (actividad.tecnicos || []).some(function (item) {
                    return String(item.tecnico_id) === tecnico;
                });
                if (!asignado) {
                    return false;
                }
            }

            if (busqueda) {
                var tecnicos = (actividad.tecnicos || []).map(function (item) {
                    return item.tecnico;
                }).join(' ');

                var texto = normalizarTexto([
                    actividad.folio,
                    actividad.nombre_equipo,
                    actividad.codigo_equipo,
                    actividad.departamento,
                    actividad.area,
                    actividad.proceso,
                    actividad.descripcion_solicitud,
                    tecnicos
                ].join(' '));

                if (texto.indexOf(busqueda) === -1) {
                    return false;
                }
            }

            return true;
        });
    }

    function renderizarPorDia(actividades) {
        var grupos = {};
        actividades.forEach(function (actividad) {
            var fecha = actividad.fecha_agenda || 'SIN_FECHA';
            if (!grupos[fecha]) {
                grupos[fecha] = [];
            }
            grupos[fecha].push(actividad);
        });

        var fechas = Object.keys(grupos).sort();

        return fechas.map(function (fecha) {
            var items = grupos[fecha];
            var dia = buscarDia(fecha);
            var resumen = contarPorGrupo(items);

            return '' +
                '<section class="aseg-group">' +
                    '<header class="aseg-group__head">' +
                        '<div class="aseg-group__date">' +
                            '<span>' + escapar(nombreDia(fecha)) + '</span>' +
                            '<strong>' + escapar(numeroDiaMes(fecha)) + '</strong>' +
                            '<small>' + escapar(nombreMesCorto(fecha)) + '</small>' +
                        '</div>' +
                        '<div class="aseg-group__title">' +
                            '<h3>' + escapar(fechaLarga(fecha)) + '</h3>' +
                            '<p>' + escapar(descripcionDia(dia)) + '</p>' +
                        '</div>' +
                        '<div class="aseg-group__summary">' +
                            resumenMini(resumen) +
                        '</div>' +
                    '</header>' +
                    '<div class="aseg-cards">' + items.map(renderizarTarjeta).join('') + '</div>' +
                '</section>';
        }).join('');
    }

    function renderizarPorTecnico(actividades) {
        var grupos = {};

        actividades.forEach(function (actividad) {
            var tecnicos = Array.isArray(actividad.tecnicos) ? actividad.tecnicos.slice() : [];
            var tecnicoFiltrado = estado.filtros.tecnico;

            if (tecnicoFiltrado && tecnicoFiltrado !== 'SIN_TECNICO') {
                tecnicos = tecnicos.filter(function (tecnico) {
                    return String(tecnico.tecnico_id) === tecnicoFiltrado;
                });
            }

            if (tecnicos.length === 0) {
                if (!grupos.SIN_TECNICO) {
                    grupos.SIN_TECNICO = {
                        id: 'SIN_TECNICO',
                        nombre: 'Sin técnico asignado',
                        turno: '',
                        actividades: []
                    };
                }
                grupos.SIN_TECNICO.actividades.push(actividad);
                return;
            }

            tecnicos.forEach(function (tecnico) {
                var clave = String(tecnico.tecnico_id);
                if (!grupos[clave]) {
                    grupos[clave] = {
                        id: clave,
                        nombre: tecnico.tecnico,
                        turno: tecnico.turno,
                        actividades: []
                    };
                }
                grupos[clave].actividades.push(actividad);
            });
        });

        var lista = Object.keys(grupos).map(function (clave) {
            return grupos[clave];
        });

        lista.sort(function (a, b) {
            if (a.id === 'SIN_TECNICO') {
                return -1;
            }
            if (b.id === 'SIN_TECNICO') {
                return 1;
            }
            if (b.actividades.length !== a.actividades.length) {
                return b.actividades.length - a.actividades.length;
            }
            return a.nombre.localeCompare(b.nombre, 'es');
        });

        return lista.map(function (grupo) {
            var resumen = contarPorGrupo(grupo.actividades);
            var iniciales = obtenerIniciales(grupo.nombre);

            return '' +
                '<section class="aseg-group aseg-group--technician">' +
                    '<header class="aseg-group__head">' +
                        '<div class="aseg-tech-avatar">' + escapar(iniciales) + '</div>' +
                        '<div class="aseg-group__title">' +
                            '<h3>' + escapar(grupo.nombre) + '</h3>' +
                            '<p>' + (grupo.turno ? 'Turno ' + escapar(textoTurno(grupo.turno)) : 'Pendiente de asignación') + '</p>' +
                        '</div>' +
                        '<div class="aseg-group__summary">' + resumenMini(resumen) + '</div>' +
                    '</header>' +
                    '<div class="aseg-cards">' + grupo.actividades.map(renderizarTarjeta).join('') + '</div>' +
                '</section>';
        }).join('');
    }

    function renderizarTarjeta(actividad) {
        var tecnicos = Array.isArray(actividad.tecnicos) ? actividad.tecnicos : [];
        var nombres = tecnicos.slice(0, 3).map(function (item) {
            return item.tecnico;
        });
        var extraTecnicos = tecnicos.length > 3 ? tecnicos.length - 3 : 0;
        var etiquetaEstado = textoGrupoEstado(actividad.grupo_estado, actividad.estado);
        var claseEstado = claseGrupoEstado(actividad.grupo_estado);
        var badges = [];

        badges.push('<span class="aseg-badge ' + claseEstado + '">' + escapar(etiquetaEstado) + '</span>');
        badges.push('<span class="aseg-badge aseg-badge--type">' + escapar(textoTipo(actividad.tipo_solicitud)) + '</span>');

        if (actividad.prioridad === 'URGENTE' || actividad.tipo_solicitud === 'CORRECTIVO_URGENTE') {
            badges.push('<span class="aseg-badge aseg-badge--urgent">Urgente</span>');
        } else if (actividad.prioridad === 'ALTA') {
            badges.push('<span class="aseg-badge aseg-badge--high">Prioridad alta</span>');
        }

        if (numero(actividad.trabajo_peligroso) === 1) {
            badges.push('<span class="aseg-badge aseg-badge--risk">Riesgo ' + escapar((actividad.nivel_riesgo || '').toLowerCase()) + '</span>');
        }

        var fechaInfo = actividad.tipo_solicitud === 'CORRECTIVO_URGENTE' && !actividad.programacion_id
            ? 'Publicada ' + horaCorta(actividad.hora_solicitud)
            : 'Programada para ' + fechaCorta(actividad.fecha_agenda);

        var cumplimiento = '';
        if (actividad.grupo_estado === 'ATRASADO') {
            cumplimiento = '<span class="aseg-card__warning">' +
                numero(actividad.dias_retraso) + ' día(s) de atraso' +
            '</span>';
        } else if (actividad.grupo_estado === 'TERMINADO') {
            if (numero(actividad.total_tarde) > 0 || numero(actividad.dias_retraso) > 0) {
                cumplimiento = '<span class="aseg-card__warning">Terminada con retraso</span>';
            } else {
                cumplimiento = '<span class="aseg-card__success">Terminada a tiempo</span>';
            }
        }

        var equipo = actividad.codigo_equipo
            ? actividad.codigo_equipo + ' · ' + actividad.nombre_equipo
            : actividad.nombre_equipo;

        var textoTecnicos = nombres.length
            ? nombres.join(', ') + (extraTecnicos ? ' +' + extraTecnicos : '')
            : 'Sin técnico asignado';

        return '' +
            '<article class="aseg-card ' + claseTarjeta(actividad) + '">' +
                '<button type="button" class="aseg-card__button" data-detalle="' + numero(actividad.id) + '">' +
                    '<div class="aseg-card__badges">' + badges.join('') + '</div>' +
                    '<div class="aseg-card__top">' +
                        '<div>' +
                            '<span class="aseg-card__folio">' + escapar(actividad.folio) + '</span>' +
                            '<h4>' + escapar(equipo || 'Equipo sin nombre') + '</h4>' +
                        '</div>' +
                        '<span class="aseg-card__arrow" aria-hidden="true">›</span>' +
                    '</div>' +
                    '<p class="aseg-card__location">' +
                        escapar([actividad.departamento, actividad.area, actividad.proceso].filter(Boolean).join(' · ')) +
                    '</p>' +
                    '<p class="aseg-card__description">' + escapar(actividad.descripcion_solicitud || 'Sin descripción') + '</p>' +
                    '<div class="aseg-card__team">' +
                        '<span aria-hidden="true">♙</span>' +
                        '<span>' + escapar(textoTecnicos) + '</span>' +
                    '</div>' +
                    '<footer class="aseg-card__foot">' +
                        '<span>' + escapar(fechaInfo) + '</span>' +
                        (cumplimiento || '<span>' + textoProgreso(actividad) + '</span>') +
                    '</footer>' +
                '</button>' +
            '</article>';
    }

    async function abrirDetalle(solicitudId) {
        if (!solicitudId) {
            return;
        }

        estado.solicitudAbierta = solicitudId;
        elementos.modalDetalle.hidden = false;
        document.body.classList.add('aseg-modal-open');
        mostrarEstadoDetalle('loading');
        elementos.detalleTitulo.textContent = 'Cargando actividad...';
        elementos.detalleSubtitulo.textContent = 'Información necesaria para dar seguimiento.';
        elementos.btnVerExpediente.hidden = true;
        elementos.btnEditarProgramacion.hidden = true;

        try {
            var params = new URLSearchParams();
            params.set('accion', 'detalle');
            params.set('solicitud_id', String(solicitudId));

            var respuesta = await solicitarJSON(ENDPOINT + '?' + params.toString());
            renderizarDetalle(respuesta.solicitud, respuesta.tecnicos || [], respuesta.acciones || {});
            mostrarEstadoDetalle('content');
        } catch (error) {
            elementos.detalleErrorMensaje.textContent = error.message || 'No fue posible cargar la información.';
            mostrarEstadoDetalle('error');
        }
    }

    function renderizarDetalle(solicitud, tecnicos, acciones) {
        elementos.detalleTitulo.textContent = solicitud.folio || 'Actividad';
        elementos.detalleSubtitulo.textContent = (solicitud.nombre_equipo || 'Equipo') + ' · ' + textoEstado(solicitud.estado);

        var programacion = solicitud.fecha_programada
            ? fechaLarga(solicitud.fecha_programada)
            : (solicitud.tipo_solicitud === 'CORRECTIVO_URGENTE'
                ? 'Urgencia publicada el ' + fechaLarga(solicitud.fecha_solicitud)
                : 'Sin fecha programada');

        var tecnicoHtml = tecnicos.length
            ? tecnicos.map(renderizarTecnicoDetalle).join('')
            : '<div class="aseg-detail-empty">Todavía no hay técnicos asignados o que hayan aceptado.</div>';

        var resultadoHtml = '';
        if (solicitud.trabajo_quedo) {
            resultadoHtml = '' +
                '<section class="aseg-detail-section">' +
                    '<header><h3>Resultado del mantenimiento</h3></header>' +
                    '<div class="aseg-result-box">' +
                        '<div><span>El trabajo quedó</span><strong>' + escapar(textoTrabajoQuedo(solicitud.trabajo_quedo)) + '</strong></div>' +
                        '<p>' + escapar(solicitud.descripcion_trabajo_realizado || 'Sin descripción del trabajo realizado.') + '</p>' +
                        (solicitud.que_falto
                            ? '<div class="aseg-result-pending"><strong>Quedó pendiente:</strong> ' + escapar(solicitud.que_falto) + '</div>'
                            : '') +
                    '</div>' +
                '</section>';
        }

        var detalleAdicional = construirDetalleAdicional(solicitud);

        elementos.detalleContenido.innerHTML = '' +
            '<section class="aseg-detail-hero">' +
                '<div class="aseg-detail-hero__badges">' +
                    '<span class="aseg-badge ' + claseEstadoSolicitud(solicitud.estado) + '">' + escapar(textoEstado(solicitud.estado)) + '</span>' +
                    '<span class="aseg-badge aseg-badge--type">' + escapar(textoTipo(solicitud.tipo_solicitud)) + '</span>' +
                    (solicitud.prioridad === 'URGENTE' || solicitud.prioridad === 'ALTA'
                        ? '<span class="aseg-badge aseg-badge--urgent">' + escapar(textoPrioridad(solicitud.prioridad)) + '</span>'
                        : '') +
                '</div>' +
                '<h3>' + escapar(solicitud.nombre_equipo || 'Equipo sin nombre') + '</h3>' +
                '<p>' + escapar((solicitud.codigo_equipo ? solicitud.codigo_equipo + ' · ' : '') + [solicitud.departamento, solicitud.area, solicitud.proceso].filter(Boolean).join(' · ')) + '</p>' +
            '</section>' +

            '<section class="aseg-detail-grid">' +
                datoDetalle('Fecha', programacion) +
                datoDetalle('Fecha límite', solicitud.fecha_limite ? fechaCorta(solicitud.fecha_limite) : 'No aplica') +
                datoDetalle('Solicitante', solicitud.solicitante || 'Sin solicitante') +
                datoDetalle('Riesgo', numero(solicitud.trabajo_peligroso) === 1 ? 'Sí · ' + textoCapital(solicitud.nivel_riesgo) : 'No marcado') +
            '</section>' +

            (numero(solicitud.dias_retraso) > 0
                ? '<div class="aseg-detail-alert"><strong>Atención:</strong> registra ' + numero(solicitud.dias_retraso) + ' día(s) de retraso.</div>'
                : '') +

            '<section class="aseg-detail-section">' +
                '<header><h3>Trabajo solicitado</h3></header>' +
                '<div class="aseg-detail-text">' + escapar(solicitud.descripcion_solicitud || 'Sin descripción.') + '</div>' +
            '</section>' +

            '<section class="aseg-detail-section">' +
                '<header><h3>Equipo de trabajo</h3><span>' + tecnicos.length + ' técnico(s)</span></header>' +
                '<div class="aseg-detail-team">' + tecnicoHtml + '</div>' +
            '</section>' +

            resultadoHtml +
            detalleAdicional;

        elementos.btnVerExpediente.href = 'solicitudes_historial.php?solicitud_id=' + numero(solicitud.id);
        elementos.btnVerExpediente.hidden = !acciones.puede_ver_expediente;

        elementos.btnEditarProgramacion.href = 'solicitudes_programacion.php';
        elementos.btnEditarProgramacion.hidden = !acciones.puede_programar;
    }

    function construirDetalleAdicional(solicitud) {
        var items = [];

        if (solicitud.descripcion_falla) {
            items.push(datoExpandible('Falla reportada', solicitud.descripcion_falla));
        }
        if (solicitud.tipo_falla) {
            items.push(datoExpandible('Tipo de falla', solicitud.tipo_falla));
        }
        if (solicitud.causa_averia) {
            items.push(datoExpandible('Causa de la avería', solicitud.causa_averia));
        }
        if (solicitud.impacto_operacion) {
            items.push(datoExpandible('Impacto en la operación', solicitud.impacto_operacion));
        }
        if (solicitud.objetivo_mejora) {
            items.push(datoExpandible('Objetivo de la mejora', solicitud.objetivo_mejora));
        }
        if (solicitud.resultado_esperado) {
            items.push(datoExpandible('Resultado esperado', solicitud.resultado_esperado));
        }
        if (solicitud.motivo_reprogramacion) {
            items.push(datoExpandible('Motivo de reprogramación', solicitud.motivo_reprogramacion));
        } else if (solicitud.motivo_programacion) {
            items.push(datoExpandible('Motivo de programación', solicitud.motivo_programacion));
        }

        if (items.length === 0) {
            return '';
        }

        return '' +
            '<details class="aseg-detail-more">' +
                '<summary>Ver información adicional</summary>' +
                '<div>' + items.join('') + '</div>' +
            '</details>';
    }

    function renderizarTecnicoDetalle(tecnico) {
        var estadoTecnico = tecnico.estado_ejecucion || tecnico.estado_asignacion;
        var cumplimiento = textoCumplimiento(tecnico.resultado_cumplimiento);
        var tiempos = [];

        if (numero(tecnico.total_segundos_activos) > 0) {
            tiempos.push('Activo: ' + duracion(tecnico.total_segundos_activos));
        }
        if (numero(tecnico.total_segundos_pausa) > 0) {
            tiempos.push('Pausa: ' + duracion(tecnico.total_segundos_pausa));
        }

        return '' +
            '<article class="aseg-tech-row">' +
                '<div class="aseg-tech-avatar aseg-tech-avatar--small">' + escapar(obtenerIniciales(tecnico.tecnico)) + '</div>' +
                '<div class="aseg-tech-row__copy">' +
                    '<strong>' + escapar(tecnico.tecnico) + '</strong>' +
                    '<span>' + escapar(textoTurno(tecnico.turno)) + (tecnico.especialidad ? ' · ' + escapar(tecnico.especialidad) : '') + '</span>' +
                    (tiempos.length ? '<small>' + escapar(tiempos.join(' · ')) + '</small>' : '') +
                '</div>' +
                '<div class="aseg-tech-row__status">' +
                    '<b>' + escapar(textoEstadoTecnico(estadoTecnico)) + '</b>' +
                    '<small>' + escapar(cumplimiento) + '</small>' +
                '</div>' +
            '</article>';
    }

    function cerrarModal() {
        elementos.modalDetalle.hidden = true;
        document.body.classList.remove('aseg-modal-open');
        estado.solicitudAbierta = 0;
    }

    function mostrarEstadoDetalle(tipo) {
        elementos.detalleCargando.hidden = tipo !== 'loading';
        elementos.detalleError.hidden = tipo !== 'error';
        elementos.detalleContenido.hidden = tipo !== 'content';
    }

    function limpiarFiltros() {
        estado.diaSeleccionado = '';
        estado.filtros = {
            busqueda: '',
            tecnico: '',
            estado: '',
            tipo: ''
        };

        elementos.filtroBusqueda.value = '';
        elementos.filtroTecnico.value = '';
        elementos.filtroEstado.value = '';
        elementos.filtroTipo.value = '';

        renderizarDias();
        renderizarAgenda();
    }


    function datosExportacion() {
        var actividades = filtrarActividades();

        if (actividades.length === 0) {
            mostrarToast('No hay actividades para exportar.', 'warning');
            return null;
        }

        return {
            actividades: actividades,
            tituloSemana: tituloRangoSemana(estado.semanaInicio, estado.semanaFin),
            filtros: descripcionFiltrosExportacion(),
            generado: new Intl.DateTimeFormat('es-MX', {
                dateStyle: 'long',
                timeStyle: 'short'
            }).format(new Date())
        };
    }

    function descripcionFiltrosExportacion() {
        var partes = [];

        if (estado.diaSeleccionado) {
            partes.push('Día: ' + fechaLarga(estado.diaSeleccionado));
        }
        if (estado.filtros.busqueda) {
            partes.push('Búsqueda: ' + elementos.filtroBusqueda.value.trim());
        }
        if (estado.filtros.tecnico) {
            partes.push('Técnico: ' + textoOpcionSeleccionada(elementos.filtroTecnico));
        }
        if (estado.filtros.estado) {
            partes.push('Situación: ' + textoOpcionSeleccionada(elementos.filtroEstado));
        }
        if (estado.filtros.tipo) {
            partes.push('Tipo: ' + textoOpcionSeleccionada(elementos.filtroTipo));
        }

        return partes.length ? partes.join(' · ') : 'Sin filtros adicionales';
    }

    function textoOpcionSeleccionada(select) {
        if (!select || select.selectedIndex < 0) {
            return '';
        }

        return select.options[select.selectedIndex].textContent.trim();
    }

    function filasExportacion(actividades) {
        return actividades.map(function (actividad) {
            return [
                fechaCorta(actividad.fecha_agenda || actividad.fecha_solicitud),
                actividad.folio || '',
                textoTipo(actividad.tipo_solicitud),
                textoGrupoEstado(actividad.grupo_estado, actividad.estado),
                textoPrioridad(actividad.prioridad),
                (actividad.codigo_equipo ? actividad.codigo_equipo + ' · ' : '') + (actividad.nombre_equipo || ''),
                [actividad.departamento, actividad.area, actividad.proceso].filter(Boolean).join(' / '),
                (actividad.tecnicos || []).map(function (item) {
                    return item.tecnico;
                }).join(', ') || 'Sin técnico asignado',
                actividad.descripcion_solicitud || '',
                numero(actividad.dias_retraso)
            ];
        });
    }

    function exportarExcel() {
        var datos = datosExportacion();
        if (!datos) {
            return;
        }

        var encabezados = [
            'Fecha', 'Folio', 'Tipo', 'Situación', 'Prioridad',
            'Equipo', 'Ubicación', 'Técnicos', 'Descripción', 'Días de retraso'
        ];
        var filas = filasExportacion(datos.actividades);

        var filasHtml = filas.map(function (fila, indice) {
            return '<tr class="' + (indice % 2 ? 'alt' : '') + '">' +
                fila.map(function (valor, columna) {
                    var clase = columna === 8 ? ' class="descripcion"' : '';
                    return '<td' + clase + '>' + escaparExcel(valor) + '</td>';
                }).join('') +
            '</tr>';
        }).join('');

        var documento = '\uFEFF' +
            '<html xmlns:o="urn:schemas-microsoft-com:office:office" ' +
            'xmlns:x="urn:schemas-microsoft-com:office:excel" ' +
            'xmlns="http://www.w3.org/TR/REC-html40">' +
            '<head><meta charset="UTF-8">' +
            '<style>' +
            'body{font-family:Calibri,Arial,sans-serif;color:#142f4c;}' +
            'table{border-collapse:collapse;width:100%;}' +
            '.title{background:#0b3455;color:#fff;font-size:20px;font-weight:700;text-align:left;padding:14px;}' +
            '.meta{background:#eaf4fb;color:#35566f;font-size:11px;padding:7px 10px;}' +
            'th{background:#126a9a;color:#fff;font-size:11px;font-weight:700;border:1px solid #0c547d;padding:8px;white-space:nowrap;}' +
            'td{font-size:10px;border:1px solid #c8dae6;padding:7px;vertical-align:top;}' +
            'tr.alt td{background:#f3f8fb;}' +
            'td.descripcion{min-width:280px;white-space:normal;}' +
            '</style></head><body>' +
            '<table>' +
            '<tr><th class="title" colspan="10">Agenda semanal · Sistema de Mantenimiento</th></tr>' +
            '<tr><td class="meta" colspan="10"><b>Semana:</b> ' + escaparExcel(datos.tituloSemana) + '</td></tr>' +
            '<tr><td class="meta" colspan="10"><b>Filtros:</b> ' + escaparExcel(datos.filtros) + '</td></tr>' +
            '<tr><td class="meta" colspan="10"><b>Generado:</b> ' + escaparExcel(datos.generado) + '</td></tr>' +
            '<tr>' + encabezados.map(function (titulo) {
                return '<th>' + escaparExcel(titulo) + '</th>';
            }).join('') + '</tr>' +
            filasHtml +
            '</table></body></html>';

        descargarBlob(
            documento,
            'application/vnd.ms-excel;charset=utf-8;',
            'agenda_' + estado.semanaInicio + '_a_' + estado.semanaFin + '.xls'
        );

        mostrarToast('Archivo de Excel generado correctamente.', 'success');
    }

    function exportarPDF() {
        var datos = datosExportacion();
        if (!datos) {
            return;
        }

        var encabezados = [
            'Fecha', 'Folio', 'Tipo', 'Situación', 'Prioridad',
            'Equipo', 'Ubicación', 'Técnicos', 'Descripción', 'Atraso'
        ];
        var filas = filasExportacion(datos.actividades);

        var ventana = window.open('', '_blank', 'width=1400,height=900');
        if (!ventana) {
            mostrarToast('El navegador bloqueó la ventana del reporte. Permite ventanas emergentes e inténtalo nuevamente.', 'warning');
            return;
        }

        var filasHtml = filas.map(function (fila) {
            return '<tr>' + fila.map(function (valor, indice) {
                return '<td class="c' + indice + '">' + escapar(valor) + '</td>';
            }).join('') + '</tr>';
        }).join('');

        ventana.document.open();
        ventana.document.write(
            '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">' +
            '<title>Agenda semanal</title>' +
            '<style>' +
            '@page{size:A4 landscape;margin:10mm;}' +
            '*{box-sizing:border-box;}' +
            'body{margin:0;font-family:Arial,sans-serif;color:#17324a;background:#fff;}' +
            '.head{display:flex;justify-content:space-between;gap:24px;border-bottom:3px solid #126a9a;padding:0 0 12px;margin-bottom:12px;}' +
            '.head h1{margin:0;font-size:22px;color:#0b3455;}' +
            '.head p{margin:5px 0 0;color:#567086;font-size:10px;}' +
            '.brand{text-align:right;font-size:10px;color:#567086;}' +
            '.brand strong{display:block;color:#0b3455;font-size:12px;}' +
            '.filters{margin:0 0 12px;padding:8px 10px;border:1px solid #c8dae6;background:#f3f8fb;font-size:9px;}' +
            'table{width:100%;border-collapse:collapse;table-layout:fixed;}' +
            'thead{display:table-header-group;}' +
            'th{background:#126a9a;color:#fff;border:1px solid #0c547d;padding:6px 4px;font-size:8px;text-align:left;}' +
            'td{border:1px solid #cfdee8;padding:5px 4px;font-size:7.5px;vertical-align:top;overflow-wrap:anywhere;}' +
            'tbody tr:nth-child(even) td{background:#f6f9fb;}' +
            '.c0{width:6%;}.c1{width:7%;}.c2{width:9%;}.c3{width:8%;}.c4{width:6%;}.c5{width:12%;}.c6{width:14%;}.c7{width:14%;}.c8{width:20%;}.c9{width:4%;text-align:center;}' +
            '.foot{margin-top:8px;color:#6d8191;font-size:8px;text-align:right;}' +
            '@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}' +
            '</style></head><body>' +
            '<header class="head"><div><h1>Agenda semanal</h1><p>' + escapar(datos.tituloSemana) + '</p></div>' +
            '<div class="brand"><strong>Sistema de Mantenimiento</strong>Reporte operativo semanal<br>' + escapar(datos.generado) + '</div></header>' +
            '<div class="filters"><b>Filtros:</b> ' + escapar(datos.filtros) + ' · <b>Actividades:</b> ' + datos.actividades.length + '</div>' +
            '<table><thead><tr>' + encabezados.map(function (titulo) {
                return '<th>' + escapar(titulo) + '</th>';
            }).join('') + '</tr></thead><tbody>' + filasHtml + '</tbody></table>' +
            '<div class="foot">Generado desde la Agenda semanal del Sistema de Mantenimiento.</div>' +
            '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},250);});<\/script>' +
            '</body></html>'
        );
        ventana.document.close();
        ventana.focus();
    }

    function escaparExcel(valor) {
        var textoSeguro = String(valor === null || valor === undefined ? '' : valor);

        if (/^[=+\-@]/.test(textoSeguro)) {
            textoSeguro = "'" + textoSeguro;
        }

        return textoSeguro.replace(/[&<>'"]/g, function (caracter) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#039;',
                '"': '&quot;'
            }[caracter];
        });
    }

    function descargarBlob(contenido, tipo, nombre) {
        var blob = new Blob([contenido], { type: tipo });
        var url = URL.createObjectURL(blob);
        var enlace = document.createElement('a');

        enlace.href = url;
        enlace.download = nombre;
        document.body.appendChild(enlace);
        enlace.click();
        enlace.remove();

        window.setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 1000);
    }

    async function solicitarJSON(url) {
        var respuesta = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            cache: 'no-store'
        });

        var datos;
        try {
            datos = await respuesta.json();
        } catch (error) {
            throw new Error('El servidor devolvió una respuesta que no se pudo interpretar.');
        }

        if (datos.sesion_expirada && datos.redirect) {
            window.location.href = datos.redirect;
            throw new Error(datos.mensaje || 'La sesión expiró.');
        }

        if (!respuesta.ok || !datos.success) {
            throw new Error(datos.mensaje || 'No fue posible completar la consulta.');
        }

        return datos;
    }

    function establecerEstadoPagina(mensaje, tipo) {
        elementos.estadoPagina.textContent = mensaje;
        elementos.estadoPagina.className = 'aseg-status';
        if (tipo) {
            elementos.estadoPagina.classList.add('is-' + tipo);
        }
    }

    function alternarControlesSemana(bloqueado) {
        elementos.btnActualizar.disabled = bloqueado;
        elementos.btnSemanaAnterior.disabled = bloqueado;
        elementos.btnSemanaActual.disabled = bloqueado;
        elementos.btnSemanaSiguiente.disabled = bloqueado;
    }

    function mostrarToast(mensaje, tipo) {
        elementos.toast.textContent = mensaje;
        elementos.toast.className = 'aseg-toast is-' + (tipo || 'info');
        elementos.toast.hidden = false;

        window.clearTimeout(mostrarToast.temporizador);
        mostrarToast.temporizador = window.setTimeout(function () {
            elementos.toast.hidden = true;
        }, 4200);
    }

    function buscarDia(fecha) {
        for (var i = 0; i < estado.dias.length; i++) {
            if (estado.dias[i].fecha === fecha) {
                return estado.dias[i];
            }
        }
        return null;
    }

    function descripcionDia(dia) {
        if (!dia) {
            return 'Día sin información de calendario.';
        }
        if (!dia.configurado) {
            return 'Día no configurado; el sistema permite programación.';
        }
        if (!dia.es_habil) {
            return dia.motivo ? 'Día inhábil: ' + dia.motivo : 'Día marcado como inhábil.';
        }
        if (dia.tipo_dia === 'HABIL_EXTRA') {
            return dia.motivo ? 'Habilitado de forma extraordinaria: ' + dia.motivo : 'Día hábil extraordinario.';
        }
        return dia.motivo || 'Día hábil de operación.';
    }

    function contarPorGrupo(items) {
        var total = { total: items.length, por_iniciar: 0, en_curso: 0, atrasado: 0, terminado: 0 };
        items.forEach(function (item) {
            if (item.grupo_estado === 'POR_INICIAR') total.por_iniciar++;
            if (item.grupo_estado === 'EN_CURSO') total.en_curso++;
            if (item.grupo_estado === 'ATRASADO') total.atrasado++;
            if (item.grupo_estado === 'TERMINADO') total.terminado++;
        });
        return total;
    }

    function resumenMini(resumen) {
        var partes = ['<span><b>' + numero(resumen.total) + '</b> total</span>'];
        if (resumen.en_curso) partes.push('<span class="is-active"><b>' + resumen.en_curso + '</b> en curso</span>');
        if (resumen.atrasado) partes.push('<span class="is-late"><b>' + resumen.atrasado + '</b> atrasada(s)</span>');
        if (resumen.terminado) partes.push('<span class="is-done"><b>' + resumen.terminado + '</b> terminada(s)</span>');
        return partes.join('');
    }

    function textoProgreso(actividad) {
        if (numero(actividad.total_asignados) === 0) {
            return 'Esperando técnico';
        }
        if (numero(actividad.total_iniciadas) === 0) {
            return numero(actividad.total_asignados) + ' técnico(s) asignado(s)';
        }
        if (numero(actividad.total_terminadas) > 0) {
            return numero(actividad.total_terminadas) + ' técnico(s) terminaron';
        }
        return numero(actividad.total_iniciadas) + ' técnico(s) iniciaron';
    }

    function claseTarjeta(actividad) {
        var clases = [];
        if (actividad.grupo_estado === 'ATRASADO') clases.push('is-late');
        if (actividad.grupo_estado === 'EN_CURSO') clases.push('is-active');
        if (actividad.grupo_estado === 'TERMINADO') clases.push('is-done');
        if (actividad.tipo_solicitud === 'CORRECTIVO_URGENTE') clases.push('is-urgent');
        return clases.join(' ');
    }

    function datoDetalle(etiqueta, valor) {
        return '<div class="aseg-detail-data"><span>' + escapar(etiqueta) + '</span><strong>' + escapar(valor || 'No disponible') + '</strong></div>';
    }

    function datoExpandible(etiqueta, valor) {
        return '<article><strong>' + escapar(etiqueta) + '</strong><p>' + escapar(valor) + '</p></article>';
    }

    function valorCSV(valor) {
        var texto = String(valor === null || valor === undefined ? '' : valor);
        return '"' + texto.replace(/"/g, '""') + '"';
    }

    function textoGrupoEstado(grupo, estadoSolicitud) {
        if (grupo === 'EN_CURSO' && estadoSolicitud === 'PAUSADO') return 'En pausa';
        if (grupo === 'EN_CURSO') return 'En proceso';
        if (grupo === 'ATRASADO') return 'Atrasado';
        if (grupo === 'TERMINADO') return 'Terminado';
        return 'Por iniciar';
    }

    function claseGrupoEstado(grupo) {
        if (grupo === 'EN_CURSO') return 'aseg-badge--active';
        if (grupo === 'ATRASADO') return 'aseg-badge--late';
        if (grupo === 'TERMINADO') return 'aseg-badge--done';
        return 'aseg-badge--pending';
    }

    function claseEstadoSolicitud(estadoSolicitud) {
        if (estadoSolicitud === 'TERMINADO') return 'aseg-badge--done';
        if (estadoSolicitud === 'ATRASADO') return 'aseg-badge--late';
        if (estadoSolicitud === 'EN_PROCESO' || estadoSolicitud === 'PAUSADO') return 'aseg-badge--active';
        return 'aseg-badge--pending';
    }

    function textoTipo(tipo) {
        var mapa = {
            CORRECTIVO_PROGRAMABLE: 'Correctivo programable',
            MODIFICACION_MEJORA: 'Modificación o mejora',
            CORRECTIVO_URGENTE: 'Correctivo urgente',
            RUTINARIO: 'Rutinario'
        };
        return mapa[tipo] || textoCapital(tipo);
    }

    function textoEstado(valor) {
        var mapa = {
            PENDIENTE: 'Pendiente', APROBADO: 'Aprobado', AGENDADO: 'Agendado',
            EN_PROCESO: 'En proceso', PAUSADO: 'En pausa', ATRASADO: 'Atrasado',
            TERMINADO: 'Terminado', RECHAZADO: 'Rechazado', CANCELADO: 'Cancelado'
        };
        return mapa[valor] || textoCapital(valor);
    }

    function textoEstadoTecnico(valor) {
        var mapa = {
            ASIGNADO: 'Asignado', ACEPTADO: 'Aceptado', EN_PROCESO: 'En proceso',
            PAUSADO: 'En pausa', PAUSADA: 'En pausa', TERMINADO: 'Terminado',
            TERMINADA: 'Terminado', PENDIENTE: 'Pendiente', CANCELADA: 'Cancelada',
            NO_PARTICIPO: 'No participó', RETIRADO: 'Retirado'
        };
        return mapa[valor] || textoCapital(valor);
    }

    function textoCumplimiento(valor) {
        var mapa = {
            PENDIENTE: 'Cumplimiento pendiente',
            A_TIEMPO: 'Cumplió a tiempo',
            TARDE: 'Terminó tarde',
            NO_REALIZADO: 'No realizado',
            NO_APLICA: 'No aplica'
        };
        return mapa[valor] || 'Sin resultado';
    }

    function textoPrioridad(valor) {
        var mapa = { BAJA: 'Prioridad baja', MEDIA: 'Prioridad media', ALTA: 'Prioridad alta', URGENTE: 'Urgente' };
        return mapa[valor] || textoCapital(valor);
    }

    function textoTrabajoQuedo(valor) {
        var mapa = { TERMINADO: 'Terminado', PARCIAL: 'Parcial', PROVISIONAL: 'Provisional' };
        return mapa[valor] || textoCapital(valor);
    }

    function textoTurno(valor) {
        var mapa = { MATUTINO: 'matutino', VESPERTINO: 'vespertino', NOCTURNO: 'nocturno' };
        return mapa[valor] || textoCapital(valor);
    }

    function textoTipoDia(valor) {
        if (valor === 'HABIL_EXTRA') return 'Hábil extra';
        if (valor === 'INHABIL') return 'Inhábil';
        return 'Hábil';
    }

    function duracion(segundos) {
        var total = Math.max(0, numero(segundos));
        var horas = Math.floor(total / 3600);
        var minutos = Math.floor((total % 3600) / 60);
        if (horas > 0) return horas + ' h ' + minutos + ' min';
        if (minutos > 0) return minutos + ' min';
        return total + ' s';
    }

    function tituloRangoSemana(inicio, fin) {
        if (!inicio || !fin) return 'Semana';
        var inicioFecha = crearFecha(inicio);
        var finFecha = crearFecha(fin);
        var mismoMes = inicioFecha.getMonth() === finFecha.getMonth() && inicioFecha.getFullYear() === finFecha.getFullYear();
        if (mismoMes) {
            return inicioFecha.getDate() + ' al ' + finFecha.getDate() + ' de ' + nombreMes(inicio) + ' de ' + finFecha.getFullYear();
        }
        return fechaCorta(inicio) + ' al ' + fechaCorta(fin);
    }

    function nombreDia(fecha) {
        return crearFecha(fecha).toLocaleDateString('es-MX', { weekday: 'short' }).replace('.', '');
    }

    function nombreMes(fecha) {
        return crearFecha(fecha).toLocaleDateString('es-MX', { month: 'long' });
    }

    function nombreMesCorto(fecha) {
        return crearFecha(fecha).toLocaleDateString('es-MX', { month: 'short' }).replace('.', '');
    }

    function numeroDiaMes(fecha) {
        return String(crearFecha(fecha).getDate());
    }

    function fechaCorta(fecha) {
        if (!fecha) return '';
        return crearFecha(fecha).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function fechaLarga(fecha) {
        if (!fecha) return '';
        return crearFecha(fecha).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }

    function horaCorta(hora) {
        if (!hora) return '';
        return String(hora).slice(0, 5);
    }

    function moverDias(fecha, dias) {
        var valor = fecha ? crearFecha(fecha) : new Date();
        valor.setDate(valor.getDate() + dias);
        return fechaISO(valor);
    }

    function crearFecha(valor) {
        var partes = String(valor || '').slice(0, 10).split('-');
        if (partes.length !== 3) return new Date(valor);
        return new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]), 12, 0, 0);
    }

    function fechaISO(fecha) {
        var anio = fecha.getFullYear();
        var mes = String(fecha.getMonth() + 1).padStart(2, '0');
        var dia = String(fecha.getDate()).padStart(2, '0');
        return anio + '-' + mes + '-' + dia;
    }

    function obtenerIniciales(nombre) {
        var partes = String(nombre || '').trim().split(/\s+/).filter(Boolean);
        if (partes.length === 0) return '?';
        return (partes[0].charAt(0) + (partes.length > 1 ? partes[partes.length - 1].charAt(0) : '')).toUpperCase();
    }

    function textoCapital(valor) {
        var texto = String(valor || '').replace(/_/g, ' ').toLowerCase();
        return texto ? texto.charAt(0).toUpperCase() + texto.slice(1) : '';
    }

    function normalizarTexto(valor) {
        return String(valor || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function numero(valor) {
        var resultado = Number(valor);
        return Number.isFinite(resultado) ? resultado : 0;
    }

    function escapar(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
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