<?php

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_sesion(array('ADMIN'), false);

$csrfToken = sm_token_csrf();
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

    <title>Rutinas | Sistema de mantenimiento</title>

    <link
        rel="stylesheet"
        href="../css/style_rutinas.css?v=20260805.4"
    >
</head>
<body>

<?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

<main class="rut-layout">
    <?php require_once __DIR__ . '/../inc/topbar.php'; ?>

    <section class="rut-page">
        <header class="rut-header">
            <div>
                <span class="rut-eyebrow">Mantenimiento recurrente</span>
                <h1>Rutinas</h1>
                <p>
                    Guarda actividades repetitivas como plantillas y atiende
                    sus avisos cuando llegue el momento de realizarlas.
                </p>
            </div>

            <div class="rut-header__actions">
                <button
                    type="button"
                    class="rut-btn rut-btn--soft"
                    id="btnActualizar"
                >
                    ↻ Actualizar avisos
                </button>

                <button
                    type="button"
                    class="rut-btn rut-btn--primary"
                    id="btnNuevaRutina"
                >
                    + Nueva rutina
                </button>
            </div>
        </header>

        <section class="rut-flow">
            <span class="rut-flow__icon">↻</span>

            <div>
                <strong>La rutina es una plantilla, no una asignación.</strong>
                <p>
                    Al vencer el intervalo sólo se genera un aviso. Ninguna
                    solicitud aparecerá en Programar y asignar hasta que un
                    administrador decida prepararla. Después podrá elegir una
                    fecha y técnicos distintos en cada periodo.
                </p>
                <small class="rut-flow__status" id="estadoAutomatizacion">
                    Comprobando automatización del servidor...
                </small>
            </div>
        </section>

        <div
            class="rut-message"
            id="mensajePagina"
            role="status"
            aria-live="polite"
            hidden
        ></div>

        <section class="rut-kpis">
            <article class="rut-kpi">
                <span>Plantillas activas</span>
                <strong id="kpiActivas">0</strong>
                <small>Generan recordatorios</small>
            </article>

            <article class="rut-kpi">
                <span>Inactivas</span>
                <strong id="kpiInactivas">0</strong>
                <small>No generan avisos nuevos</small>
            </article>

            <article class="rut-kpi rut-kpi--danger">
                <span>Requieren atención</span>
                <strong id="kpiPendientes">0</strong>
                <small id="textoVencidas">0 vencidas</small>
            </article>

            <article class="rut-kpi rut-kpi--info">
                <span>Listas para programar</span>
                <strong id="kpiProgramar">0</strong>
                <small>Solicitud ya preparada</small>
            </article>
        </section>

        <nav class="rut-tabs" aria-label="Secciones de rutinas">
            <button
                type="button"
                class="rut-tab is-active"
                data-vista="ALERTAS"
            >
                Avisos pendientes
                <span id="contadorAlertas">0</span>
            </button>

            <button
                type="button"
                class="rut-tab"
                data-vista="PLANTILLAS"
            >
                Plantillas
                <span id="contadorRutinas">0</span>
            </button>
        </nav>

        <section class="rut-panel">
            <div class="rut-toolbar">
                <label class="rut-search">
                    <span>Buscar</span>
                    <input
                        type="search"
                        id="buscar"
                        maxlength="100"
                        placeholder="Rutina, equipo, código o ubicación"
                    >
                </label>

                <label>
                    <span>Estado</span>
                    <select id="filtroEstado">
                        <option value="">Todos</option>
                    </select>
                </label>

                <label>
                    <span>Equipo</span>
                    <select id="filtroEquipo">
                        <option value="">Todos los equipos</option>
                    </select>
                </label>

                <label class="rut-quantity">
                    <span>Mostrar</span>
                    <select id="filtroCantidad">
                        <option value="10" selected>10 por página</option>
                        <option value="25">25 por página</option>
                        <option value="50">50 por página</option>
                        <option value="100">100 por página</option>
                    </select>
                </label>

                <button
                    type="button"
                    class="rut-link"
                    id="btnLimpiarFiltros"
                >
                    Limpiar filtros
                </button>
            </div>

            <div class="rut-loading" id="cargandoPagina">
                <span></span>
                <p>Cargando plantillas y recordatorios...</p>
            </div>

            <div class="rut-empty" id="estadoVacio" hidden>
                <span>✓</span>
                <h3>Sin resultados</h3>
                <p id="textoVacio">
                    No hay elementos que coincidan con los filtros.
                </p>
            </div>

            <div
                class="rut-list"
                id="listaAlertas"
                hidden
            ></div>

            <div
                class="rut-list rut-list--templates"
                id="listaRutinas"
                hidden
            ></div>

            <footer class="rut-pagination" id="paginacion" hidden>
                <div class="rut-pagination__summary">
                    <strong id="textoPaginacion">Sin resultados</strong>
                    <span id="detallePaginacion">Página 1 de 1</span>
                </div>

                <div class="rut-pagination__controls" aria-label="Paginación">
                    <button type="button" id="btnPrimera" aria-label="Primera página">«</button>
                    <button type="button" id="btnAnterior">Anterior</button>
                    <div class="rut-pagination__pages" id="paginasPaginacion"></div>
                    <button type="button" id="btnSiguiente">Siguiente</button>
                    <button type="button" id="btnUltima" aria-label="Última página">»</button>
                </div>
            </footer>
        </section>

        <div class="rut-tools-background" aria-hidden="true"></div>
    </section>
</main>

<!-- Modal de rutina -->
<section class="rut-modal" id="modalRutina" hidden>
    <div
        class="rut-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloModalRutina"
    >
        <header class="rut-modal__header">
            <div>
                <span>Plantilla de mantenimiento</span>
                <h2 id="tituloModalRutina">Nueva rutina</h2>
            </div>

            <button
                type="button"
                class="rut-modal__close"
                data-cerrar-modal="modalRutina"
                aria-label="Cerrar"
            >
                ×
            </button>
        </header>

        <form id="formRutina" novalidate>
            <div class="rut-modal__body">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="rutinaId">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                >

                <section class="rut-form-section">
                    <h3>Información de la actividad</h3>

                    <div class="rut-form-grid rut-form-grid--two">
                        <label class="rut-field">
                            <span>Nombre de la rutina *</span>
                            <input
                                type="text"
                                name="nombre"
                                id="rutinaNombre"
                                minlength="3"
                                maxlength="150"
                                placeholder="Ej. Limpieza de banda transportadora"
                                required
                            >
                        </label>

                        <label class="rut-field">
                            <span>Tipo de actividad *</span>
                            <select
                                name="tipo_rutina"
                                id="rutinaTipo"
                                required
                            ></select>
                        </label>

                        <label class="rut-field rut-field--full">
                            <span>Equipo *</span>
                            <select
                                name="equipo_id"
                                id="rutinaEquipo"
                                required
                            >
                                <option value="">Selecciona un equipo</option>
                            </select>
                        </label>

                        <div
                            class="rut-equipment"
                            id="resumenEquipo"
                            hidden
                        ></div>

                        <label class="rut-field rut-field--full">
                            <span>Trabajo que debe realizarse *</span>
                            <textarea
                                name="descripcion_actividad"
                                id="rutinaDescripcion"
                                minlength="10"
                                maxlength="3000"
                                rows="5"
                                placeholder="Describe con precisión los pasos, verificaciones y resultado esperado."
                                required
                            ></textarea>
                            <small>
                                Esta descripción se copiará a la solicitud
                                cuando el administrador la prepare.
                            </small>
                        </label>
                    </div>
                </section>

                <section class="rut-form-section">
                    <h3>Clasificación y seguridad</h3>

                    <div class="rut-form-grid rut-form-grid--three">
                        <label class="rut-field">
                            <span>Prioridad *</span>
                            <select
                                name="prioridad"
                                id="rutinaPrioridad"
                                required
                            >
                                <option value="BAJA">Baja</option>
                                <option value="MEDIA" selected>Media</option>
                                <option value="ALTA">Alta</option>
                            </select>
                        </label>

                        <label class="rut-field">
                            <span>Tipo de falla</span>
                            <select
                                name="tipo_falla_id"
                                id="rutinaTipoFalla"
                            >
                                <option value="">No aplica</option>
                            </select>
                        </label>

                        <label class="rut-field">
                            <span>Causa relacionada</span>
                            <select
                                name="causa_averia_id"
                                id="rutinaCausa"
                            >
                                <option value="">No aplica</option>
                            </select>
                        </label>
                    </div>

                    <div class="rut-check-grid">
                        <label class="rut-check">
                            <input
                                type="checkbox"
                                name="requiere_paro_equipo"
                                id="rutinaParo"
                                value="1"
                            >
                            <span>
                                <strong>Requiere paro del equipo</strong>
                                <small>
                                    Se copiará a la solicitud rutinaria.
                                </small>
                            </span>
                        </label>

                        <label class="rut-check">
                            <input
                                type="checkbox"
                                name="trabajo_peligroso"
                                id="rutinaPeligrosa"
                                value="1"
                            >
                            <span>
                                <strong>Trabajo peligroso</strong>
                                <small>
                                    Activa la selección del nivel de riesgo.
                                </small>
                            </span>
                        </label>
                    </div>

                    <div class="rut-danger-fields" id="camposTrabajoPeligroso" hidden>
                        <label class="rut-field rut-risk-field">
                            <span>Nivel de riesgo *</span>
                            <select
                                name="nivel_riesgo"
                                id="rutinaRiesgo"
                            >
                                <option value="BAJO">Bajo</option>
                                <option value="MEDIO">Medio</option>
                                <option value="ALTO">Alto</option>
                            </select>
                        </label>

                        <label class="rut-field rut-field--full">
                            <span>Nota breve del peligro *</span>
                            <input
                                type="text"
                                name="detalle_trabajo_peligroso"
                                id="rutinaDetallePeligro"
                                minlength="3"
                                maxlength="200"
                                placeholder="Ej. Trabajo en altura, carga pesada o riesgo eléctrico."
                            >
                            <small>
                                Esta nota se mostrará al administrador y a los técnicos.
                            </small>
                        </label>
                    </div>
                </section>

                <section class="rut-form-section rut-form-section--resources">
                    <div class="rut-section-heading">
                        <div>
                            <h3>Herramientas y refacciones de la plantilla</h3>
                            <p>
                                Se seleccionarán automáticamente cada vez que esta rutina se prepare.
                                Cambiarlas aquí sí modifica las siguientes ejecuciones de la plantilla.
                            </p>
                        </div>
                        <span class="rut-template-lock">Vinculación permanente</span>
                    </div>

                    <div class="rut-resource-grid">
                        <div class="rut-resource-picker" data-resource-picker="HERRAMIENTA">
                            <div class="rut-resource-picker__head">
                                <span class="rut-resource-icon" aria-hidden="true">🔧</span>
                                <div>
                                    <strong>Herramientas</strong>
                                    <small id="contadorHerramientas">0 seleccionadas</small>
                                </div>
                            </div>

                            <label class="rut-resource-search">
                                <span class="sr-only">Buscar herramienta</span>
                                <input
                                    type="search"
                                    id="buscarHerramienta"
                                    autocomplete="off"
                                    placeholder="Buscar por nombre, código o descripción..."
                                >
                            </label>

                            <div
                                class="rut-resource-results"
                                id="resultadosHerramientas"
                                role="listbox"
                                hidden
                            ></div>

                            <div
                                class="rut-resource-selected"
                                id="herramientasSeleccionadas"
                                aria-live="polite"
                            ></div>
                        </div>

                        <div class="rut-resource-picker" data-resource-picker="REFACCION">
                            <div class="rut-resource-picker__head">
                                <span class="rut-resource-icon" aria-hidden="true">⚙️</span>
                                <div>
                                    <strong>Refacciones</strong>
                                    <small id="contadorRefacciones">0 seleccionadas</small>
                                </div>
                            </div>

                            <label class="rut-resource-search">
                                <span class="sr-only">Buscar refacción</span>
                                <input
                                    type="search"
                                    id="buscarRefaccion"
                                    autocomplete="off"
                                    placeholder="Buscar por nombre, código o descripción..."
                                >
                            </label>

                            <div
                                class="rut-resource-results"
                                id="resultadosRefacciones"
                                role="listbox"
                                hidden
                            ></div>

                            <div
                                class="rut-resource-selected"
                                id="refaccionesSeleccionadas"
                                aria-live="polite"
                            ></div>
                        </div>
                    </div>

                    <div class="rut-resource-note">
                        <strong>Importante:</strong>
                        al programar una ejecución podrás agregar o retirar recursos únicamente para ese mantenimiento;
                        esa modificación no cambiará esta plantilla.
                    </div>
                </section>

                <section class="rut-form-section">
                    <h3>Cuándo debe avisar</h3>

                    <div class="rut-schedule">
                        <label class="rut-field">
                            <span>Avisar cada cuántos días *</span>
                            <div class="rut-number-input">
                                <input
                                    type="number"
                                    name="frecuencia_cada"
                                    id="rutinaFrecuencia"
                                    min="1"
                                    max="3650"
                                    step="1"
                                    value="7"
                                    required
                                >
                                <b>días</b>
                            </div>
                        </label>

                        <label class="rut-field">
                            <span id="etiquetaRutinaFecha">Primer aviso *</span>
                            <input
                                type="date"
                                name="fecha_inicio"
                                id="rutinaFecha"
                                required
                            >
                            <small id="ayudaRutinaFecha">
                                Si eliges hoy o una fecha pasada, el aviso aparecerá inmediatamente.
                            </small>
                        </label>

                        <div class="rut-schedule__preview">
                            <span>Funcionamiento</span>
                            <strong id="vistaFrecuencia">
                                Avisará cada 7 días
                            </strong>
                            <p>
                                La fecha real del trabajo podrá cambiar.
                                El siguiente intervalo comenzará cuando el
                                mantenimiento anterior se termine.
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <footer class="rut-modal__footer">
                <button
                    type="button"
                    class="rut-btn rut-btn--soft"
                    data-cerrar-modal="modalRutina"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="rut-btn rut-btn--primary"
                    id="btnGuardarRutina"
                >
                    Guardar plantilla
                </button>
            </footer>
        </form>
    </div>
</section>

<div class="rut-toast" id="toast" role="status" aria-live="polite" hidden></div>

<?php require_once __DIR__ . '/../inc/alertas.php'; ?>

<script>
(function () {
    'use strict';

    var ENDPOINT = '../funciones/rutinas_funciones.php';
    var ENDPOINT_RECURSOS = '../funciones/recursos_mantenimiento_funciones.php';
    var CSRF_TOKEN = <?= json_encode(
        $csrfToken,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    var estado = {
        vista: 'ALERTAS',
        rutinas: [],
        alertas: [],
        fechaServidor: '',
        automatizacion: null,
        controladorListado: null,
        secuenciaListado: 0,
        temporizadorBusqueda: null,
        temporizadoresRecursos: {
            HERRAMIENTA: null,
            REFACCION: null
        },
        controladoresRecursos: {
            HERRAMIENTA: null,
            REFACCION: null
        },
        recursosSeleccionados: {
            HERRAMIENTA: [],
            REFACCION: []
        },
        paginacion: {
            ALERTAS: {
                pagina: 1,
                cantidad: 10,
                total: 0,
                total_paginas: 1,
                desde: 0,
                hasta: 0
            },
            PLANTILLAS: {
                pagina: 1,
                cantidad: 10,
                total: 0,
                total_paginas: 1,
                desde: 0,
                hasta: 0
            }
        },
        catalogos: {
            equipos: [],
            tipos_falla: [],
            causas_averia: [],
            tipos_rutina: []
        }
    };

    var ui = {};

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        capturarElementos();
        registrarEventos();
        establecerFechaInicial();
        cargarInicial();

        window.setInterval(function () {
            sincronizar(false);
        }, 300000);
    }

    function capturarElementos() {
        [
            'btnActualizar',
            'btnNuevaRutina',
            'mensajePagina',
            'kpiActivas',
            'kpiInactivas',
            'kpiPendientes',
            'kpiProgramar',
            'textoVencidas',
            'contadorAlertas',
            'contadorRutinas',
            'buscar',
            'filtroEstado',
            'filtroEquipo',
            'filtroCantidad',
            'btnLimpiarFiltros',
            'cargandoPagina',
            'estadoVacio',
            'textoVacio',
            'listaAlertas',
            'listaRutinas',
            'paginacion',
            'textoPaginacion',
            'detallePaginacion',
            'btnPrimera',
            'btnAnterior',
            'paginasPaginacion',
            'btnSiguiente',
            'btnUltima',
            'modalRutina',
            'tituloModalRutina',
            'formRutina',
            'rutinaId',
            'rutinaNombre',
            'rutinaTipo',
            'rutinaEquipo',
            'resumenEquipo',
            'rutinaDescripcion',
            'rutinaPrioridad',
            'rutinaTipoFalla',
            'rutinaCausa',
            'rutinaParo',
            'rutinaPeligrosa',
            'camposTrabajoPeligroso',
            'rutinaRiesgo',
            'rutinaDetallePeligro',
            'buscarHerramienta',
            'resultadosHerramientas',
            'herramientasSeleccionadas',
            'contadorHerramientas',
            'buscarRefaccion',
            'resultadosRefacciones',
            'refaccionesSeleccionadas',
            'contadorRefacciones',
            'rutinaFrecuencia',
            'rutinaFecha',
            'etiquetaRutinaFecha',
            'ayudaRutinaFecha',
            'estadoAutomatizacion',
            'vistaFrecuencia',
            'btnGuardarRutina',
            'toast'
        ].forEach(function (id) {
            ui[id] = document.getElementById(id);
        });
    }

    function registrarEventos() {
        ui.btnActualizar.addEventListener('click', function () {
            sincronizar(true);
        });

        ui.btnNuevaRutina.addEventListener('click', abrirNuevaRutina);
        ui.formRutina.addEventListener('submit', guardarRutina);

        ui.buscar.addEventListener('input', programarBusqueda);
        ui.filtroEstado.addEventListener('change', reiniciarPaginaYCargar);
        ui.filtroEquipo.addEventListener('change', reiniciarPaginaYCargar);
        ui.filtroCantidad.addEventListener('change', function () {
            var cantidad = cantidadPermitida(ui.filtroCantidad.value);
            estado.paginacion[estado.vista].cantidad = cantidad;
            ui.filtroCantidad.value = String(cantidad);
            reiniciarPaginaYCargar();
        });

        ui.btnLimpiarFiltros.addEventListener('click', function () {
            window.clearTimeout(estado.temporizadorBusqueda);
            ui.buscar.value = '';
            ui.filtroEstado.value = '';
            ui.filtroEquipo.value = '';
            estado.paginacion[estado.vista].pagina = 1;
            cargarListado(true);
            ui.buscar.focus();
        });

        document.querySelectorAll('.rut-tab').forEach(function (boton) {
            boton.addEventListener('click', function () {
                cambiarVista(boton.getAttribute('data-vista'));
            });
        });

        ui.btnPrimera.addEventListener('click', function () {
            irPagina(1);
        });

        ui.btnAnterior.addEventListener('click', function () {
            irPagina(estado.paginacion[estado.vista].pagina - 1);
        });

        ui.btnSiguiente.addEventListener('click', function () {
            irPagina(estado.paginacion[estado.vista].pagina + 1);
        });

        ui.btnUltima.addEventListener('click', function () {
            irPagina(estado.paginacion[estado.vista].total_paginas);
        });

        ui.paginasPaginacion.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-pagina]');
            if (!boton) {
                return;
            }

            irPagina(Number(boton.getAttribute('data-pagina')));
        });

        document.querySelectorAll('[data-cerrar-modal]').forEach(
            function (boton) {
                boton.addEventListener('click', function () {
                    cerrarModal(
                        document.getElementById(
                            boton.getAttribute('data-cerrar-modal')
                        )
                    );
                });
            }
        );

        ui.modalRutina.addEventListener('click', function (evento) {
            if (evento.target === ui.modalRutina) {
                cerrarModal(ui.modalRutina);
            }
        });

        ui.rutinaEquipo.addEventListener('change', mostrarResumenEquipo);

        ui.rutinaPeligrosa.addEventListener('change', function () {
            actualizarRiesgo();
        });

        registrarBuscadorRecurso(
            'HERRAMIENTA',
            ui.buscarHerramienta,
            ui.resultadosHerramientas
        );
        registrarBuscadorRecurso(
            'REFACCION',
            ui.buscarRefaccion,
            ui.resultadosRefacciones
        );

        document.addEventListener('click', function (evento) {
            if (!evento.target.closest('.rut-resource-picker')) {
                ocultarResultadosRecursos();
            }
        });

        ui.rutinaFrecuencia.addEventListener(
            'input',
            actualizarVistaFrecuencia
        );

        document.addEventListener('click', manejarAccionTarjeta);

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && !ui.modalRutina.hidden) {
                cerrarModal(ui.modalRutina);
            }
        });
    }

    async function cargarInicial() {
        mostrarCargando(true);
        ocultarMensaje();

        try {
            var datos = await solicitar(construirUrlListado('inicial'));
            aplicarDatos(datos);
            llenarCatalogos();
            aplicarListado(datos.listado);
        } catch (error) {
            mostrarMensaje(
                error.message,
                'error',
                false
            );
            mostrarVacio(
                'No se pudieron cargar las rutinas. Revisa el registro de PHP o Apache.'
            );
        } finally {
            mostrarCargando(false);
        }
    }

    async function cargarListado(mostrarErrorVisible) {
        estado.secuenciaListado += 1;
        var secuencia = estado.secuenciaListado;

        if (
            estado.controladorListado
            && typeof estado.controladorListado.abort === 'function'
        ) {
            estado.controladorListado.abort();
        }

        estado.controladorListado = typeof AbortController === 'function'
            ? new AbortController()
            : null;

        mostrarCargando(true);

        try {
            var opciones = {};

            if (estado.controladorListado) {
                opciones.signal = estado.controladorListado.signal;
            }

            var datos = await solicitar(
                construirUrlListado('listar'),
                opciones
            );

            if (secuencia !== estado.secuenciaListado) {
                return;
            }

            aplicarDatos(datos);
            aplicarListado(datos.listado);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return;
            }

            if (secuencia !== estado.secuenciaListado) {
                return;
            }

            mostrarVacio(
                'No fue posible cargar esta página de resultados.'
            );

            if (mostrarErrorVisible !== false) {
                toast(error.message, 'error');
            }
        } finally {
            if (secuencia === estado.secuenciaListado) {
                mostrarCargando(false);
                estado.controladorListado = null;
            }
        }
    }

    async function sincronizar(mostrarConfirmacion) {
        bloquearBoton(
            ui.btnActualizar,
            true,
            'Actualizando...'
        );

        try {
            var formulario = new FormData();
            formulario.append('accion', 'sincronizar');
            formulario.append('csrf_token', CSRF_TOKEN);

            var datos = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            aplicarDatos(datos);
            await cargarListado(false);

            if (mostrarConfirmacion) {
                toast(datos.mensaje, 'success');
            }
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            bloquearBoton(
                ui.btnActualizar,
                false,
                '↻ Actualizar avisos'
            );
        }
    }

    function aplicarDatos(datos) {
        if (datos.csrf_token) {
            CSRF_TOKEN = datos.csrf_token;
        }

        if (datos.catalogos) {
            estado.catalogos = datos.catalogos;
        }

        if (datos.servidor && datos.servidor.fecha) {
            estado.fechaServidor = String(datos.servidor.fecha);
        }

        if (datos.automatizacion) {
            estado.automatizacion = datos.automatizacion;
            pintarAutomatizacion(datos.automatizacion);
        }

        if (datos.resumen) {
            pintarResumen(datos.resumen);
        }
    }

    function aplicarListado(listado) {
        if (!listado || !Array.isArray(listado.items)) {
            throw new Error('El servidor no devolvió un listado válido.');
        }

        var vista = listado.vista === 'PLANTILLAS'
            ? 'PLANTILLAS'
            : 'ALERTAS';

        if (vista === 'PLANTILLAS') {
            estado.rutinas = listado.items;
        } else {
            estado.alertas = listado.items;
        }

        estado.paginacion[vista] = Object.assign(
            {
                pagina: 1,
                cantidad: 10,
                total: 0,
                total_paginas: 1,
                desde: 0,
                hasta: 0
            },
            listado.paginacion || {}
        );

        if (vista === estado.vista) {
            ui.filtroCantidad.value = String(
                cantidadPermitida(estado.paginacion[vista].cantidad)
            );
            renderizar();
        }
    }

    function construirUrlListado(accion) {
        var pagina = estado.paginacion[estado.vista];
        var parametros = new URLSearchParams();

        parametros.set('accion', accion);
        parametros.set('vista', estado.vista);
        parametros.set('pagina', String(Math.max(1, Number(pagina.pagina) || 1)));
        parametros.set(
            'cantidad',
            String(cantidadPermitida(pagina.cantidad))
        );
        parametros.set('busqueda', String(ui.buscar.value || '').trim());
        parametros.set('estado', String(ui.filtroEstado.value || ''));
        parametros.set('equipo_id', String(ui.filtroEquipo.value || ''));
        parametros.set('t', String(Date.now()));

        return ENDPOINT + '?' + parametros.toString();
    }

    function programarBusqueda() {
        window.clearTimeout(estado.temporizadorBusqueda);
        estado.paginacion[estado.vista].pagina = 1;

        estado.temporizadorBusqueda = window.setTimeout(function () {
            cargarListado(true);
        }, 350);
    }

    function reiniciarPaginaYCargar() {
        estado.paginacion[estado.vista].pagina = 1;
        cargarListado(true);
    }

    function cantidadPermitida(valor) {
        var cantidad = Number(valor);

        return [10, 25, 50, 100].indexOf(cantidad) !== -1
            ? cantidad
            : 10;
    }

    function pintarAutomatizacion(automatizacion) {
        if (!ui.estadoAutomatizacion) {
            return;
        }

        var activa = automatizacion && automatizacion.activa === true;

        ui.estadoAutomatizacion.classList.toggle('is-active', activa);
        ui.estadoAutomatizacion.classList.toggle('is-fallback', !activa);
        ui.estadoAutomatizacion.textContent = automatizacion
            && automatizacion.mensaje
                ? automatizacion.mensaje
                : 'La página actualizará los avisos cada cinco minutos.';
    }

    function pintarResumen(resumen) {
        var activas = Number(resumen.activas || 0);
        var inactivas = Number(resumen.inactivas || 0);

        ui.kpiActivas.textContent = numero(activas);
        ui.kpiInactivas.textContent = numero(inactivas);
        ui.kpiPendientes.textContent = numero(resumen.pendientes);
        ui.kpiProgramar.textContent = numero(resumen.por_programar);

        ui.textoVencidas.textContent =
            numero(resumen.vencidas)
            + (
                Number(resumen.vencidas) === 1
                    ? ' vencida'
                    : ' vencidas'
            );

        ui.contadorAlertas.textContent = numero(resumen.pendientes);
        ui.contadorRutinas.textContent = numero(activas + inactivas);
    }

    function llenarCatalogos() {
        llenarSelect(
            ui.rutinaEquipo,
            estado.catalogos.equipos,
            'Selecciona un equipo',
            function (item) {
                return item.codigo_equipo
                    + ' · '
                    + item.nombre_equipo;
            }
        );

        llenarSelect(
            ui.rutinaTipoFalla,
            estado.catalogos.tipos_falla,
            'No aplica',
            function (item) {
                return item.nombre;
            }
        );

        llenarSelect(
            ui.rutinaCausa,
            estado.catalogos.causas_averia,
            'No aplica',
            function (item) {
                return item.nombre;
            }
        );

        ui.rutinaTipo.innerHTML = '';

        (estado.catalogos.tipos_rutina || []).forEach(function (tipo) {
            var opcion = document.createElement('option');
            opcion.value = tipo;
            opcion.textContent = tipo;
            ui.rutinaTipo.appendChild(opcion);
        });

        ui.filtroEquipo.innerHTML =
            '<option value="">Todos los equipos</option>';

        (estado.catalogos.equipos || []).forEach(function (equipo) {
            var opcion = document.createElement('option');
            opcion.value = String(equipo.id);
            opcion.textContent =
                equipo.codigo_equipo + ' · ' + equipo.nombre_equipo;
            ui.filtroEquipo.appendChild(opcion);
        });

        actualizarOpcionesEstado();
    }

    function llenarSelect(select, lista, textoVacio, obtenerTexto) {
        select.innerHTML = '';

        var vacio = document.createElement('option');
        vacio.value = '';
        vacio.textContent = textoVacio;
        select.appendChild(vacio);

        (lista || []).forEach(function (item) {
            var opcion = document.createElement('option');
            opcion.value = String(item.id);
            opcion.textContent = obtenerTexto(item);
            select.appendChild(opcion);
        });
    }

    function actualizarOpcionesEstado() {
        var opciones;

        if (estado.vista === 'ALERTAS') {
            opciones = [
                ['', 'Todos'],
                ['VENCIDA', 'Vencidas'],
                ['HOY', 'Para hoy'],
                ['LISTA_PROGRAMAR', 'Solicitud sin programar'],
                ['PROGRAMADA', 'Programadas'],
                ['OMITIDA', 'Omitidas'],
                ['CANCELADA', 'Canceladas']
            ];
        } else {
            opciones = [
                ['', 'Todas'],
                ['ACTIVA', 'Activas'],
                ['INACTIVA', 'Inactivas'],
                ['VENCIDA', 'Con aviso vencido'],
                ['PROXIMA', 'Próximas']
            ];
        }

        ui.filtroEstado.innerHTML = '';

        opciones.forEach(function (opcion) {
            var elemento = document.createElement('option');
            elemento.value = opcion[0];
            elemento.textContent = opcion[1];
            ui.filtroEstado.appendChild(elemento);
        });
    }

    function cambiarVista(vista) {
        vista = vista === 'PLANTILLAS' ? 'PLANTILLAS' : 'ALERTAS';

        if (estado.vista === vista) {
            return;
        }

        estado.vista = vista;

        document.querySelectorAll('.rut-tab').forEach(function (boton) {
            boton.classList.toggle(
                'is-active',
                boton.getAttribute('data-vista') === vista
            );
        });

        actualizarOpcionesEstado();
        ui.filtroCantidad.value = String(
            cantidadPermitida(estado.paginacion[vista].cantidad)
        );
        cargarListado(true);
    }

    function renderizar() {
        if (estado.vista === 'ALERTAS') {
            pintarAlertas(estado.alertas);
        } else {
            pintarRutinas(estado.rutinas);
        }

        pintarPaginacion();
    }

    function pintarPaginacion() {
        var datos = estado.paginacion[estado.vista];
        var total = Number(datos.total || 0);
        var pagina = Math.max(1, Number(datos.pagina || 1));
        var totalPaginas = Math.max(1, Number(datos.total_paginas || 1));

        ui.paginacion.hidden = total === 0;
        ui.textoPaginacion.textContent = total === 0
            ? 'Sin resultados'
            : 'Mostrando '
                + Number(datos.desde || 0)
                + '–'
                + Number(datos.hasta || 0)
                + ' de '
                + total
                + ' registros';

        ui.detallePaginacion.textContent =
            'Página ' + pagina + ' de ' + totalPaginas;

        ui.btnPrimera.disabled = pagina <= 1;
        ui.btnAnterior.disabled = pagina <= 1;
        ui.btnSiguiente.disabled = pagina >= totalPaginas;
        ui.btnUltima.disabled = pagina >= totalPaginas;

        ui.paginasPaginacion.innerHTML = '';

        paginasVisibles(pagina, totalPaginas).forEach(function (numeroPagina) {
            if (numeroPagina === '...') {
                var separador = document.createElement('span');
                separador.className = 'rut-pagination__ellipsis';
                separador.textContent = '…';
                ui.paginasPaginacion.appendChild(separador);
                return;
            }

            var boton = document.createElement('button');
            boton.type = 'button';
            boton.textContent = String(numeroPagina);
            boton.setAttribute('data-pagina', String(numeroPagina));
            boton.setAttribute(
                'aria-label',
                'Ir a la página ' + numeroPagina
            );
            boton.classList.toggle('is-active', numeroPagina === pagina);

            if (numeroPagina === pagina) {
                boton.setAttribute('aria-current', 'page');
            }

            ui.paginasPaginacion.appendChild(boton);
        });
    }

    function paginasVisibles(actual, total) {
        if (total <= 7) {
            var todas = [];
            for (var i = 1; i <= total; i++) {
                todas.push(i);
            }
            return todas;
        }

        if (actual <= 4) {
            return [1, 2, 3, 4, 5, '...', total];
        }

        if (actual >= total - 3) {
            return [
                1,
                '...',
                total - 4,
                total - 3,
                total - 2,
                total - 1,
                total
            ];
        }

        return [
            1,
            '...',
            actual - 1,
            actual,
            actual + 1,
            '...',
            total
        ];
    }

    function irPagina(pagina) {
        var datos = estado.paginacion[estado.vista];
        var totalPaginas = Math.max(1, Number(datos.total_paginas || 1));
        var destino = Math.min(
            Math.max(1, Number(pagina) || 1),
            totalPaginas
        );

        if (destino === Number(datos.pagina || 1)) {
            return;
        }

        estado.paginacion[estado.vista].pagina = destino;
        cargarListado(true);

        var panel = document.querySelector('.rut-panel');
        if (panel) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function pintarAlertas(lista) {
        ui.listaRutinas.hidden = true;
        ui.listaAlertas.hidden = false;
        ui.listaAlertas.innerHTML = '';

        if (!lista.length) {
            ui.listaAlertas.hidden = true;
            mostrarVacio(
                'No hay recordatorios que coincidan con los filtros.'
            );
            return;
        }

        ocultarVacio();

        lista.forEach(function (item) {
            var tarjeta = document.createElement('article');
            tarjeta.className =
                'rut-card rut-card--alert rut-card--'
                + claseSituacion(item.situacion);

            tarjeta.innerHTML = crearAlertaHTML(item);
            ui.listaAlertas.appendChild(tarjeta);
        });
    }

    function crearAlertaHTML(item) {
        var acciones = '';

        if (item.situacion === 'LISTA_PROGRAMAR') {
            acciones += botonAccion(
                'Abrir programación',
                'abrir-programacion',
                item.id,
                'primary'
            );
        } else if (
            item.situacion === 'VENCIDA'
            || item.situacion === 'HOY'
            || item.situacion === 'PROXIMA'
        ) {
            acciones += botonAccion(
                'Preparar para asignar',
                'preparar',
                item.id,
                'primary'
            );

            acciones += botonAccion(
                'Omitir periodo',
                'omitir',
                item.id,
                'soft'
            );
        } else if (
            item.situacion === 'OMITIDA'
            || item.situacion === 'CANCELADA'
        ) {
            acciones += botonAccion(
                'Reactivar periodo',
                'reactivar',
                item.id,
                'soft'
            );
        }

        var solicitud = item.folio
            ? '<span>Solicitud</span><strong>'
                + escapar(item.folio)
                + '</strong>'
            : '<span>Solicitud</span><strong>Aún no creada</strong>';

        var programacion = item.fecha_programada
            ? '<span>Programada</span><strong>'
                + escapar(formatearFecha(item.fecha_programada))
                + '</strong>'
            : '';

        return ''
            + '<div class="rut-card__date">'
            + '  <span>' + escapar(nombreMes(item.fecha_notificacion)) + '</span>'
            + '  <strong>' + escapar(diaFecha(item.fecha_notificacion)) + '</strong>'
            + '  <small>' + escapar(item.fecha_relativa) + '</small>'
            + '</div>'
            + '<div class="rut-card__content">'
            + '  <div class="rut-card__badges">'
            + badge(textoSituacion(item.situacion), claseSituacion(item.situacion))
            + badge(textoPrioridad(item.prioridad), clasePrioridad(item.prioridad))
            + (Number(item.trabajo_peligroso) === 1
                ? badge('Trabajo peligroso', 'danger')
                : '')
            + '  </div>'
            + '  <h3>' + escapar(item.nombre) + '</h3>'
            + '  <p class="rut-card__equipment">'
            + escapar(item.codigo_equipo + ' · ' + item.nombre_equipo)
            + '  </p>'
            + '  <p class="rut-card__location">'
            + escapar(
                [item.departamento, item.area, item.proceso]
                    .filter(Boolean)
                    .join(' / ')
            )
            + '  </p>'
            + '  <p class="rut-card__description">'
            + escapar(item.descripcion_corta)
            + '  </p>'
            + crearResumenPeligro(item)
            + crearResumenRecursos(item)
            + '  <div class="rut-card__meta">'
            + '    <div>' + solicitud + '</div>'
            + (
                programacion
                    ? '<div>' + programacion + '</div>'
                    : ''
            )
            + '  </div>'
            + '</div>'
            + '<div class="rut-card__actions">'
            + acciones
            + '</div>';
    }

    function pintarRutinas(lista) {
        ui.listaAlertas.hidden = true;
        ui.listaRutinas.hidden = false;
        ui.listaRutinas.innerHTML = '';

        if (!lista.length) {
            ui.listaRutinas.hidden = true;
            mostrarVacio(
                'No hay plantillas que coincidan con los filtros.'
            );
            return;
        }

        ocultarVacio();

        lista.forEach(function (item) {
            var tarjeta = document.createElement('article');
            tarjeta.className = 'rut-card rut-card--template';

            tarjeta.innerHTML = crearRutinaHTML(item);
            ui.listaRutinas.appendChild(tarjeta);
        });
    }

    function crearRutinaHTML(item) {
        var activa = Number(item.activo) === 1;
        var vencida =
            activa
            && String(item.proxima_notificacion) <= fechaHoy();

        return ''
            + '<div class="rut-template__head">'
            + '  <div class="rut-card__badges">'
            + badge(activa ? 'Activa' : 'Inactiva', activa ? 'success' : 'neutral')
            + badge(textoPrioridad(item.prioridad), clasePrioridad(item.prioridad))
            + (vencida ? badge('Requiere atención', 'danger') : '')
            + (Number(item.trabajo_peligroso) === 1
                ? badge('Trabajo peligroso', 'danger')
                : '')
            + (item.ciclo_en_curso
                ? badge(
                    item.folio_en_curso
                        ? 'En curso · ' + item.folio_en_curso
                        : 'Periodo pendiente',
                    'info'
                )
                : '')
            + '  </div>'
            + '  <h3>' + escapar(item.nombre) + '</h3>'
            + '  <p>' + escapar(item.tipo_rutina) + '</p>'
            + '</div>'
            + '<div class="rut-template__body">'
            + '  <strong>'
            + escapar(item.codigo_equipo + ' · ' + item.nombre_equipo)
            + '  </strong>'
            + '  <small>'
            + escapar(
                [item.departamento, item.area, item.proceso]
                    .filter(Boolean)
                    .join(' / ')
            )
            + '  </small>'
            + '  <p>' + escapar(item.descripcion_corta) + '</p>'
            + crearResumenPeligro(item)
            + crearResumenRecursos(item)
            + '</div>'
            + '<div class="rut-template__schedule">'
            + '  <div>'
            + '    <span>Frecuencia</span>'
            + '    <strong>' + escapar(item.frecuencia_texto) + '</strong>'
            + '  </div>'
            + '  <div>'
            + '    <span>Próximo aviso</span>'
            + '    <strong>'
            + escapar(formatearFecha(item.proxima_notificacion))
            + '    </strong>'
            + '    <small>' + escapar(item.proxima_texto) + '</small>'
            + '  </div>'
            + '</div>'
            + '<div class="rut-card__actions">'
            + botonAccion('Editar', 'editar', item.id, 'soft')
            + botonAccion(
                activa ? 'Desactivar' : 'Activar',
                'estado',
                item.id,
                activa ? 'danger' : 'success'
            )
            + '</div>';
    }

    function manejarAccionTarjeta(evento) {
        var boton = evento.target.closest('[data-accion]');

        if (!boton) {
            return;
        }

        var accion = boton.getAttribute('data-accion');
        var id = Number(boton.getAttribute('data-id'));

        if (accion === 'editar') {
            abrirEdicion(id);
        } else if (accion === 'estado') {
            cambiarEstado(id);
        } else if (accion === 'preparar') {
            prepararSolicitud(id); 
        } else if (accion === 'abrir-programacion') {
            abrirProgramacion(id);
        } else if (accion === 'omitir') {
            omitirAlerta(id);
        } else if (accion === 'reactivar') {
            reactivarAlerta(id);
        }
    }

    function abrirNuevaRutina() {
        ui.formRutina.reset();
        ui.rutinaId.value = '';
        ui.tituloModalRutina.textContent = 'Nueva rutina';
        ui.etiquetaRutinaFecha.textContent = 'Primer aviso *';
        ui.ayudaRutinaFecha.textContent =
            'Si eliges hoy o una fecha pasada, el aviso aparecerá inmediatamente.';
        ui.rutinaPrioridad.value = 'MEDIA';
        ui.rutinaFrecuencia.value = '7';
        ui.rutinaRiesgo.value = 'BAJO';
        ui.rutinaDetallePeligro.value = '';
        establecerRecursosSeleccionados([]);
        limpiarBuscadoresRecursos();
        establecerFechaInicial();
        actualizarRiesgo();
        actualizarVistaFrecuencia();
        mostrarResumenEquipo();
        abrirModal(ui.modalRutina);
        ui.rutinaNombre.focus();
    }

    function abrirEdicion(id) {
        var item = buscarPorId(estado.rutinas, id);

        if (!item) {
            toast('No se encontró la plantilla.', 'error');
            return;
        }

        ui.formRutina.reset();
        ui.rutinaId.value = item.id;
        ui.rutinaNombre.value = item.nombre || '';
        ui.rutinaTipo.value = item.tipo_rutina || '';
        ui.rutinaEquipo.value = item.equipo_id || '';
        ui.rutinaDescripcion.value = item.descripcion_actividad || '';
        ui.rutinaPrioridad.value = item.prioridad || 'MEDIA';
        ui.rutinaTipoFalla.value = item.tipo_falla_id || '';
        ui.rutinaCausa.value = item.causa_averia_id || '';
        ui.rutinaParo.checked =
            Number(item.requiere_paro_equipo) === 1;
        ui.rutinaPeligrosa.checked =
            Number(item.trabajo_peligroso) === 1;
        ui.rutinaRiesgo.value = item.nivel_riesgo || 'BAJO';
        ui.rutinaDetallePeligro.value =
            item.detalle_trabajo_peligroso || '';
        establecerRecursosSeleccionados(item.recursos || []);
        limpiarBuscadoresRecursos();
        ui.rutinaFrecuencia.value = item.frecuencia_cada || 1;
        ui.rutinaFecha.value = item.proxima_notificacion || fechaHoy();

        ui.tituloModalRutina.textContent = 'Editar rutina';
        ui.etiquetaRutinaFecha.textContent = 'Próximo aviso *';
        ui.ayudaRutinaFecha.textContent = item.ciclo_en_curso
            ? 'Existe un periodo pendiente o una solicitud en curso. La fecha de ese ciclo no se moverá; la frecuencia nueva se aplicará después de terminarlo.'
            : 'Cambiar esta fecha sustituirá el recordatorio pendiente que todavía no tenga solicitud.';

        actualizarRiesgo();
        actualizarVistaFrecuencia();
        mostrarResumenEquipo();
        abrirModal(ui.modalRutina);
    }

    async function guardarRutina(evento) {
        evento.preventDefault();

        if (!ui.formRutina.checkValidity()) {
            ui.formRutina.reportValidity();
            return;
        }

        bloquearBoton(
            ui.btnGuardarRutina,
            true,
            'Guardando...'
        );

        try {
            var formulario = new FormData(ui.formRutina);
            formulario.set('csrf_token', CSRF_TOKEN);

            if (!ui.rutinaParo.checked) {
                formulario.set('requiere_paro_equipo', '0');
            }

            if (!ui.rutinaPeligrosa.checked) {
                formulario.set('trabajo_peligroso', '0');
                formulario.set('nivel_riesgo', 'BAJO');
                formulario.set('detalle_trabajo_peligroso', '');
            }

            estado.recursosSeleccionados.HERRAMIENTA.forEach(
                function (recurso) {
                    formulario.append('herramientas_ids[]', String(recurso.id));
                }
            );
            estado.recursosSeleccionados.REFACCION.forEach(
                function (recurso) {
                    formulario.append('refacciones_ids[]', String(recurso.id));
                }
            );

            var datos = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            aplicarDatos(datos);
            cerrarModal(ui.modalRutina);
            await cargarListado(false);
            toast(datos.mensaje, 'success');

            if (datos.advertencia) {
                window.setTimeout(function () {
                    toast(datos.advertencia, 'warning');
                }, 650);
            }
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            bloquearBoton(
                ui.btnGuardarRutina,
                false,
                'Guardar plantilla'
            );
        }
    }

    async function cambiarEstado(id) {
        var item = buscarPorId(estado.rutinas, id);

        if (!item) {
            return;
        }

        var activar = Number(item.activo) !== 1;

        var acepto = await confirmar(
            activar ? '¿Activar esta plantilla?' : '¿Desactivar esta plantilla?',
            activar
                ? 'Volverá a generar avisos cuando corresponda.'
                : 'No se eliminarán solicitudes ni trabajos ya programados.',
            activar ? 'Activar' : 'Desactivar',
            activar ? 'question' : 'warning'
        );

        if (!acepto) {
            return;
        }

        var formulario = new FormData();
        formulario.append('accion', 'cambiar_estado');
        formulario.append('id', String(id));
        formulario.append('activo', activar ? '1' : '0');
        formulario.append('csrf_token', CSRF_TOKEN);

        try {
            var datos = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            aplicarDatos(datos);
            await cargarListado(false);
            toast(datos.mensaje, 'success');
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    async function prepararSolicitud(id) {
        var item = buscarPorId(estado.alertas, id);

        if (!item) {
            return;
        }

        var acepto = await confirmar(
            '¿Preparar esta solicitud rutinaria?',
            'Se copiará la información de la plantilla. Todavía no se asignará ningún técnico ni fecha.',
            'Preparar y continuar',
            'question'
        );

        if (!acepto) {
            return;
        }

        var formulario = new FormData();
        formulario.append('accion', 'preparar_solicitud');
        formulario.append('alerta_id', String(id));
        formulario.append('csrf_token', CSRF_TOKEN);

        try {
            var datos = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            toast(datos.mensaje, 'success');

            window.setTimeout(function () {
                window.location.href = datos.redirect;
            }, 450);
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    function abrirProgramacion(id) {
        var item = buscarPorId(estado.alertas, id);

        if (!item || !item.solicitud_id) {
            toast('No se encontró la solicitud preparada.', 'error');
            return;
        }

        window.location.href =
            'solicitudes_programacion.php?solicitud_id='
            + encodeURIComponent(item.solicitud_id);
    }

    async function omitirAlerta(id) {
        var motivo = await pedirMotivo(
            'Omitir este periodo',
            'Explica por qué no se realizará esta vez.'
        );

        if (!motivo) {
            return;
        }

        var formulario = new FormData();
        formulario.append('accion', 'omitir_alerta');
        formulario.append('alerta_id', String(id));
        formulario.append('motivo', motivo);
        formulario.append('csrf_token', CSRF_TOKEN);

        try {
            var datos = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            aplicarDatos(datos);
            await cargarListado(false);
            toast(datos.mensaje, 'success');
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    async function reactivarAlerta(id) {
        var item = buscarPorId(estado.alertas, id);
        var tieneSolicitudAnterior = item
            && Number(item.solicitud_id || 0) > 0;
        var folioAnterior = item && item.folio
            ? String(item.folio)
            : 'la solicitud anterior';
        var mensaje = tieneSolicitudAnterior
            ? 'La solicitud ' + folioAnterior
                + ' permanecerá cancelada en el historial. El mismo periodo volverá a quedar disponible para preparar una solicitud nueva.'
            : 'El periodo volverá a aparecer como pendiente para preparar y asignar.';

        var acepto = await confirmar(
            '¿Reactivar este periodo?',
            mensaje,
            'Reactivar periodo',
            'question'
        );

        if (!acepto) {
            return;
        }

        var formulario = new FormData();
        formulario.append('accion', 'reactivar_alerta');
        formulario.append('alerta_id', String(id));
        formulario.append('csrf_token', CSRF_TOKEN);

        try {
            var datos = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            aplicarDatos(datos);
            await cargarListado(false);
            toast(datos.mensaje, 'success');
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    function mostrarResumenEquipo() {
        var id = Number(ui.rutinaEquipo.value);
        var equipo = buscarPorId(estado.catalogos.equipos || [], id);

        if (!equipo) {
            ui.resumenEquipo.hidden = true;
            ui.resumenEquipo.innerHTML = '';
            return;
        }

        ui.resumenEquipo.innerHTML =
            '<strong>'
            + escapar(equipo.codigo_equipo + ' · ' + equipo.nombre_equipo)
            + '</strong>'
            + '<span>'
            + escapar(
                [equipo.departamento, equipo.area, equipo.proceso]
                    .filter(Boolean)
                    .join(' / ')
            )
            + '</span>';

        ui.resumenEquipo.hidden = false;
    }

    function actualizarRiesgo() {
        var peligroso = ui.rutinaPeligrosa.checked;
        ui.camposTrabajoPeligroso.hidden = !peligroso;
        ui.rutinaRiesgo.required = peligroso;
        ui.rutinaDetallePeligro.required = peligroso;

        if (!peligroso) {
            ui.rutinaRiesgo.value = 'BAJO';
            ui.rutinaDetallePeligro.value = '';
        }
    }

    function registrarBuscadorRecurso(tipo, input, contenedor) {
        input.addEventListener('focus', function () {
            buscarRecursos(tipo, input.value, contenedor);
        });

        input.addEventListener('input', function () {
            window.clearTimeout(estado.temporizadoresRecursos[tipo]);
            estado.temporizadoresRecursos[tipo] = window.setTimeout(
                function () {
                    buscarRecursos(tipo, input.value, contenedor);
                },
                220
            );
        });

        input.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                contenedor.hidden = true;
            }
        });

        contenedor.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-recurso-id]');

            if (!boton) {
                return;
            }

            var recurso = {
                id: Number(boton.getAttribute('data-recurso-id')),
                tipo_recurso: tipo,
                nombre: boton.getAttribute('data-recurso-nombre') || '',
                codigo: boton.getAttribute('data-recurso-codigo') || '',
                descripcion: boton.getAttribute('data-recurso-descripcion') || '',
                activo: 1
            };

            agregarRecursoSeleccionado(tipo, recurso);
            input.value = '';
            contenedor.hidden = true;
            input.focus();
        });
    }

    async function buscarRecursos(tipo, termino, contenedor) {
        if (
            estado.controladoresRecursos[tipo]
            && typeof estado.controladoresRecursos[tipo].abort === 'function'
        ) {
            estado.controladoresRecursos[tipo].abort();
        }

        var controlador = typeof AbortController !== 'undefined'
            ? new AbortController()
            : null;
        estado.controladoresRecursos[tipo] = controlador;

        contenedor.hidden = false;
        contenedor.innerHTML = '<div class="rut-resource-results__status">Buscando...</div>';

        try {
            var parametros = new URLSearchParams();
            parametros.set('accion', 'BUSCAR_ACTIVOS');
            parametros.set('tipo_recurso', tipo);
            parametros.set('q', String(termino || '').trim());
            parametros.set('limite', '30');

            var opciones = {};
            if (controlador) {
                opciones.signal = controlador.signal;
            }

            var datos = await solicitar(
                ENDPOINT_RECURSOS + '?' + parametros.toString(),
                opciones
            );

            pintarResultadosRecursos(
                tipo,
                Array.isArray(datos.recursos) ? datos.recursos : [],
                contenedor
            );
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return;
            }

            contenedor.innerHTML =
                '<div class="rut-resource-results__status rut-resource-results__status--error">'
                + escapar(error.message)
                + '</div>';
        }
    }

    function pintarResultadosRecursos(tipo, recursos, contenedor) {
        var seleccionados = estado.recursosSeleccionados[tipo].reduce(
            function (mapa, recurso) {
                mapa[Number(recurso.id)] = true;
                return mapa;
            },
            {}
        );

        recursos = recursos.filter(function (recurso) {
            return !seleccionados[Number(recurso.id)];
        });

        if (!recursos.length) {
            contenedor.innerHTML =
                '<div class="rut-resource-results__status">'
                + 'No hay resultados disponibles.'
                + '</div>';
            return;
        }

        contenedor.innerHTML = recursos.map(function (recurso) {
            var codigo = recurso.codigo
                ? '<small>' + escapar(recurso.codigo) + '</small>'
                : '<small>Sin código</small>';
            var descripcion = recurso.descripcion
                ? '<p>' + escapar(recurso.descripcion) + '</p>'
                : '';

            return ''
                + '<button type="button" class="rut-resource-option"'
                + ' data-recurso-id="' + Number(recurso.id) + '"'
                + ' data-recurso-nombre="' + escaparAtributo(recurso.nombre || '') + '"'
                + ' data-recurso-codigo="' + escaparAtributo(recurso.codigo || '') + '"'
                + ' data-recurso-descripcion="' + escaparAtributo(recurso.descripcion || '') + '">'
                + '  <span><strong>' + escapar(recurso.nombre) + '</strong>' + codigo + '</span>'
                + descripcion
                + '</button>';
        }).join('');
    }

    function agregarRecursoSeleccionado(tipo, recurso) {
        var existe = estado.recursosSeleccionados[tipo].some(
            function (actual) {
                return Number(actual.id) === Number(recurso.id);
            }
        );

        if (!existe) {
            estado.recursosSeleccionados[tipo].push(recurso);
            estado.recursosSeleccionados[tipo].sort(function (a, b) {
                return String(a.nombre).localeCompare(String(b.nombre), 'es');
            });
            pintarRecursosSeleccionados();
        }
    }

    function establecerRecursosSeleccionados(recursos) {
        estado.recursosSeleccionados.HERRAMIENTA = [];
        estado.recursosSeleccionados.REFACCION = [];

        (Array.isArray(recursos) ? recursos : []).forEach(function (recurso) {
            var tipo = String(recurso.tipo_recurso || '').toUpperCase();

            if (
                tipo === 'HERRAMIENTA'
                || tipo === 'REFACCION'
            ) {
                estado.recursosSeleccionados[tipo].push({
                    id: Number(recurso.id),
                    tipo_recurso: tipo,
                    nombre: recurso.nombre || '',
                    codigo: recurso.codigo || '',
                    descripcion: recurso.descripcion || '',
                    activo: Number(recurso.activo) === 1 ? 1 : 0
                });
            }
        });

        pintarRecursosSeleccionados();
    }

    function pintarRecursosSeleccionados() {
        pintarSeleccionTipo(
            'HERRAMIENTA',
            ui.herramientasSeleccionadas,
            ui.contadorHerramientas
        );
        pintarSeleccionTipo(
            'REFACCION',
            ui.refaccionesSeleccionadas,
            ui.contadorRefacciones
        );
    }

    function pintarSeleccionTipo(tipo, contenedor, contador) {
        var recursos = estado.recursosSeleccionados[tipo];
        contador.textContent = recursos.length === 1
            ? '1 seleccionada'
            : recursos.length + ' seleccionadas';

        if (!recursos.length) {
            contenedor.innerHTML =
                '<div class="rut-resource-empty">'
                + 'Todavía no has seleccionado '
                + (tipo === 'HERRAMIENTA' ? 'herramientas.' : 'refacciones.')
                + '</div>';
            return;
        }

        contenedor.innerHTML = recursos.map(function (recurso) {
            var inactivo = Number(recurso.activo) !== 1;

            return ''
                + '<div class="rut-resource-chip' + (inactivo ? ' is-inactive' : '') + '">'
                + '  <span>'
                + '    <strong>' + escapar(recurso.nombre) + '</strong>'
                + (recurso.codigo ? '<small>' + escapar(recurso.codigo) + '</small>' : '')
                + (inactivo ? '<em>Desactivado</em>' : '')
                + '  </span>'
                + '  <button type="button" aria-label="Quitar ' + escaparAtributo(recurso.nombre) + '"'
                + '    data-quitar-recurso="' + Number(recurso.id) + '"'
                + '    data-tipo-recurso="' + tipo + '">×</button>'
                + '</div>';
        }).join('');

        contenedor.querySelectorAll('[data-quitar-recurso]').forEach(
            function (boton) {
                boton.addEventListener('click', function () {
                    quitarRecursoSeleccionado(
                        boton.getAttribute('data-tipo-recurso'),
                        Number(boton.getAttribute('data-quitar-recurso'))
                    );
                });
            }
        );
    }

    function quitarRecursoSeleccionado(tipo, id) {
        estado.recursosSeleccionados[tipo] =
            estado.recursosSeleccionados[tipo].filter(function (recurso) {
                return Number(recurso.id) !== Number(id);
            });
        pintarRecursosSeleccionados();
    }

    function limpiarBuscadoresRecursos() {
        ui.buscarHerramienta.value = '';
        ui.buscarRefaccion.value = '';
        ocultarResultadosRecursos();
    }

    function ocultarResultadosRecursos() {
        ui.resultadosHerramientas.hidden = true;
        ui.resultadosRefacciones.hidden = true;
    }

    function crearResumenRecursos(item) {
        var herramientas = Number(item.total_herramientas || 0);
        var refacciones = Number(item.total_refacciones || 0);

        return ''
            + '<div class="rut-resource-summary">'
            + '  <span><b>🔧</b> ' + herramientas + ' herramienta'
            + (herramientas === 1 ? '' : 's') + '</span>'
            + '  <span><b>⚙️</b> ' + refacciones + ' refacción'
            + (refacciones === 1 ? '' : 'es') + '</span>'
            + '</div>';
    }

    function crearResumenPeligro(item) {
        if (Number(item.trabajo_peligroso) !== 1) {
            return '';
        }

        var detalle = String(item.detalle_trabajo_peligroso || '').trim();

        return ''
            + '<div class="rut-danger-summary">'
            + '  <strong>Trabajo peligroso · ' + escapar(item.nivel_riesgo || 'BAJO') + '</strong>'
            + (detalle ? '<span>' + escapar(detalle) + '</span>' : '')
            + '</div>';
    }

    function actualizarVistaFrecuencia() {
        var dias = Math.max(
            1,
            Number(ui.rutinaFrecuencia.value || 1)
        );

        ui.vistaFrecuencia.textContent =
            dias === 1
                ? 'Avisará cada día'
                : 'Avisará cada ' + dias + ' días';
    }

    function establecerFechaInicial() {
        if (!ui.rutinaFecha.value) {
            ui.rutinaFecha.value = fechaHoy();
        }
    }

    async function solicitar(url, opciones) {
        opciones = opciones || {};
        opciones.credentials = 'same-origin';

        opciones.headers = opciones.headers || {};
        opciones.headers['X-Requested-With'] = 'XMLHttpRequest';
        opciones.headers.Accept = 'application/json';

        if (opciones.method === 'POST') {
            opciones.headers['X-CSRF-Token'] = CSRF_TOKEN;
        }

        var respuesta = await fetch(url, opciones);
        var texto = await respuesta.text();
        var datos;

        try {
            datos = JSON.parse(texto);
        } catch (error) {
            throw new Error(
                'El servidor devolvió una respuesta inválida. '
                + 'Revisa el archivo error.log de PHP o Apache.'
            );
        }

        if (datos.sesion_expirada && datos.redirect) {
            window.location.href = datos.redirect;
            throw new Error(datos.mensaje);
        }

        if (!respuesta.ok || datos.success === false) {
            var mensaje = datos.mensaje || 'No se pudo completar la operación.';

            if (datos.referencia) {
                mensaje += ' Referencia: ' + datos.referencia;
            }

            throw new Error(mensaje);
        }

        return datos;
    }

    async function confirmar(titulo, texto, textoConfirmar, icono) {
        if (!window.Swal || typeof window.Swal.fire !== 'function') {
            toast(
                'No fue posible abrir la confirmación visual. Recarga la página e inténtalo nuevamente.',
                'error'
            );
            return false;
        }

        var resultado = await window.Swal.fire({
            icon: icono || 'question',
            title: titulo,
            text: texto,
            showCancelButton: true,
            confirmButtonText: textoConfirmar || 'Continuar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true,
            allowOutsideClick: false,
            heightAuto: false,
            buttonsStyling: false,
            customClass: {
                popup: 'rut-swal-popup',
                title: 'rut-swal-title',
                htmlContainer: 'rut-swal-text',
                actions: 'rut-swal-actions',
                confirmButton: 'rut-swal-button ' + (icono === 'warning'
                    ? 'rut-swal-button--danger'
                    : 'rut-swal-button--confirm'),
                cancelButton: 'rut-swal-button rut-swal-button--cancel'
            }
        });

        return resultado.isConfirmed;
    }

    async function pedirMotivo(titulo, texto) {
        if (!window.Swal || typeof window.Swal.fire !== 'function') {
            toast(
                'No fue posible abrir el formulario visual. Recarga la página e inténtalo nuevamente.',
                'error'
            );
            return '';
        }

        var resultado = await window.Swal.fire({
            icon: 'warning',
            title: titulo,
            text: texto,
            input: 'textarea',
            inputLabel: 'Motivo',
            inputPlaceholder: 'Escribe el motivo con claridad...',
            inputAttributes: {
                maxlength: '500',
                minlength: '10',
                rows: '5'
            },
            showCancelButton: true,
            confirmButtonText: 'Guardar motivo',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true,
            allowOutsideClick: false,
            heightAuto: false,
            buttonsStyling: false,
            customClass: {
                popup: 'rut-swal-popup',
                title: 'rut-swal-title',
                htmlContainer: 'rut-swal-text',
                input: 'rut-swal-input',
                actions: 'rut-swal-actions',
                confirmButton: 'rut-swal-button rut-swal-button--danger',
                cancelButton: 'rut-swal-button rut-swal-button--cancel'
            },
            preConfirm: function (valor) {
                var motivo = String(valor || '').trim();

                if (motivo.length < 10) {
                    window.Swal.showValidationMessage(
                        'Escribe un motivo de al menos 10 caracteres.'
                    );
                    return false;
                }

                if (motivo.length > 500) {
                    window.Swal.showValidationMessage(
                        'El motivo no puede superar 500 caracteres.'
                    );
                    return false;
                }

                if (!/[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/.test(motivo)) {
                    window.Swal.showValidationMessage(
                        'Escribe una explicación válida.'
                    );
                    return false;
                }

                return motivo;
            }
        });

        return resultado.isConfirmed
            ? String(resultado.value || '').trim()
            : '';
    }

    function mostrarCargando(mostrar) {
        ui.cargandoPagina.hidden = !mostrar;

        if (mostrar) {
            ui.listaAlertas.hidden = true;
            ui.listaRutinas.hidden = true;
            ui.estadoVacio.hidden = true;
            ui.paginacion.hidden = true;
        }
    }

    function mostrarVacio(texto) {
        ui.textoVacio.textContent = texto;
        ui.estadoVacio.hidden = false;
    }

    function ocultarVacio() {
        ui.estadoVacio.hidden = true;
    }

    function mostrarMensaje(texto, tipo, ocultar) {
        ui.mensajePagina.textContent = texto;
        ui.mensajePagina.className =
            'rut-message rut-message--' + (tipo || 'info');
        ui.mensajePagina.hidden = false;

        if (ocultar) {
            window.setTimeout(ocultarMensaje, 3500);
        }
    }

    function ocultarMensaje() {
        ui.mensajePagina.hidden = true;
    }

    var temporizadorToast = null;

    function toast(texto, tipo) {
        window.clearTimeout(temporizadorToast);

        ui.toast.textContent = texto;
        ui.toast.className =
            'rut-toast rut-toast--' + (tipo || 'info');
        ui.toast.hidden = false;

        temporizadorToast = window.setTimeout(function () {
            ui.toast.hidden = true;
        }, 5000);
    }

    function abrirModal(modal) {
        modal.hidden = false;
        document.body.classList.add('rut-modal-open');
    }

    function cerrarModal(modal) {
        modal.hidden = true;
        document.body.classList.remove('rut-modal-open');
    }

    function bloquearBoton(boton, bloqueado, texto) {
        boton.disabled = bloqueado;
        boton.textContent = texto;
    }

    function botonAccion(texto, accion, id, estilo) {
        return '<button type="button"'
            + ' class="rut-btn rut-btn--small rut-btn--'
            + escaparAtributo(estilo)
            + '" data-accion="'
            + escaparAtributo(accion)
            + '" data-id="'
            + Number(id)
            + '">'
            + escapar(texto)
            + '</button>';
    }

    function badge(texto, tipo) {
        return '<span class="rut-badge rut-badge--'
            + escaparAtributo(tipo)
            + '">'
            + escapar(texto)
            + '</span>';
    }

    function buscarPorId(lista, id) {
        id = Number(id);

        for (var i = 0; i < lista.length; i++) {
            if (Number(lista[i].id) === id) {
                return lista[i];
            }
        }

        return null;
    }

    function textoSituacion(valor) {
        var mapa = {
            VENCIDA: 'Vencida',
            HOY: 'Para hoy',
            PROXIMA: 'Próxima',
            LISTA_PROGRAMAR: 'Solicitud sin programar',
            PROGRAMADA: 'Programada',
            OMITIDA: 'Omitida',
            CANCELADA: 'Cancelada'
        };

        return mapa[valor] || 'Pendiente';
    }

    function claseSituacion(valor) {
        var mapa = {
            VENCIDA: 'danger',
            HOY: 'warning',
            PROXIMA: 'info',
            LISTA_PROGRAMAR: 'primary',
            PROGRAMADA: 'success',
            OMITIDA: 'neutral',
            CANCELADA: 'neutral'
        };

        return mapa[valor] || 'neutral';
    }

    function textoPrioridad(valor) {
        var mapa = {
            BAJA: 'Prioridad baja',
            MEDIA: 'Prioridad media',
            ALTA: 'Prioridad alta'
        };

        return mapa[valor] || 'Prioridad media';
    }

    function clasePrioridad(valor) {
        if (valor === 'ALTA') {
            return 'danger';
        }

        if (valor === 'BAJA') {
            return 'neutral';
        }

        return 'warning';
    }

    function normalizar(valor) {
        return String(valor || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function escapar(valor) {
        return String(
            valor === null || valor === undefined ? '' : valor
        )
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escaparAtributo(valor) {
        return escapar(valor).replace(/`/g, '&#096;');
    }

    function numero(valor) {
        var resultado = Number(valor || 0);
        return Number.isFinite(resultado) ? String(resultado) : '0';
    }

    function fechaHoy() {
        if (/^\d{4}-\d{2}-\d{2}$/.test(estado.fechaServidor || '')) {
            return estado.fechaServidor;
        }

        var fecha = new Date();
        var mes = String(fecha.getMonth() + 1).padStart(2, '0');
        var dia = String(fecha.getDate()).padStart(2, '0');

        return fecha.getFullYear() + '-' + mes + '-' + dia;
    }

    function formatearFecha(fecha) {
        if (!fecha || !/^\d{4}-\d{2}-\d{2}/.test(fecha)) {
            return 'Sin fecha';
        }

        var partes = fecha.substring(0, 10).split('-');

        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    function diaFecha(fecha) {
        return fecha && fecha.length >= 10
            ? fecha.substring(8, 10)
            : '--';
    }

    function nombreMes(fecha) {
        var meses = [
            'ENE', 'FEB', 'MAR', 'ABR',
            'MAY', 'JUN', 'JUL', 'AGO',
            'SEP', 'OCT', 'NOV', 'DIC'
        ];

        var indice = fecha && fecha.length >= 7
            ? Number(fecha.substring(5, 7)) - 1
            : -1;

        return meses[indice] || '';
    }
})();
</script>
</body>
</html> 