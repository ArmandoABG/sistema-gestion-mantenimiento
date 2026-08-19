<?php

declare(strict_types=1);

/*
 * El navegador consulta esta misma página con ?tej_api=1. La página incluye
 * internamente el archivo de funciones mediante una ruta absoluta del servidor.
 * Así se evita el error 404 que puede provocar una ruta relativa mal copiada.
 */
if (isset($_GET['tej_api']) || isset($_POST['tej_api'])) {
    $endpoint = __DIR__ . '/../funciones/tiempos_ejecucion_funciones.php';

    if (!is_file($endpoint)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode(
            [
                'success' => false,
                'mensaje' => 'No se encontró funciones/tiempos_ejecucion_funciones.php. Copia juntos los tres archivos del módulo en sus carpetas correspondientes.',
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
    <title>Tiempos reales | Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="../css/style_tiempos_ejecucion.css?v=20260730.1">
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<main class="tej-page">
    <header class="tej-heading">
        <div class="tej-heading__copy">
            <p class="tej-eyebrow">SEGUIMIENTO OPERATIVO</p>
            <h1>Tiempos reales</h1>
            <p>
                Consulta de forma clara cuánto tiempo trabajó cada técnico, cuánto tiempo estuvo en pausa
                y qué mantenimientos siguen activos, pausados o terminados.
            </p>
        </div>

        <div class="tej-heading__actions">
            <button type="button" class="tej-btn tej-btn--secondary" id="btnExportar">
                <span aria-hidden="true">⇩</span>
                Exportar CSV
            </button>
            <button type="button" class="tej-btn tej-btn--primary" id="btnActualizar">
                <span aria-hidden="true">↻</span>
                Actualizar
            </button>
        </div>
    </header>

    <section class="tej-rule" aria-label="Regla de cálculo">
        <span class="tej-rule__icon" aria-hidden="true">◴</span>
        <div>
            <strong>El tiempo activo descuenta automáticamente todas las pausas registradas.</strong>
            <p>
                Cada registro representa la participación de un técnico. Usa “Ver detalle” para consultar
                sus pausas y, únicamente cuando el mantenimiento esté cerrado, corregir un dato con auditoría.
            </p>
        </div>
    </section>

    <div class="tej-status" id="estadoPagina" role="status" aria-live="polite">
        Cargando tiempos de ejecución...
    </div>

    <section class="tej-kpis" aria-label="Resumen de tiempos">
        <article class="tej-kpi">
            <span>Participaciones</span>
            <strong id="kpiTotal">0</strong>
            <small>Técnicos con ejecución</small>
        </article>
        <article class="tej-kpi tej-kpi--active">
            <span>Trabajando ahora</span>
            <strong id="kpiActivos">0</strong>
            <small>Ejecuciones en proceso</small>
        </article>
        <article class="tej-kpi tej-kpi--pause">
            <span>En pausa</span>
            <strong id="kpiPausadas">0</strong>
            <small>Pendientes de reanudar</small>
        </article>
        <article class="tej-kpi tej-kpi--time">
            <span>Tiempo activo</span>
            <strong id="kpiTiempoActivo">00:00</strong>
            <small>Acumulado filtrado</small>
        </article>
        <article class="tej-kpi tej-kpi--edited">
            <span>Con corrección</span>
            <strong id="kpiCorregidas">0</strong>
            <small>Cambios auditados</small>
        </article>
        <article class="tej-kpi tej-kpi--warning">
            <span>Requieren revisión</span>
            <strong id="kpiAlertas">0</strong>
            <small>Datos que conviene verificar</small>
        </article>
    </section>

    <section class="tej-card tej-filter-card">
        <form class="tej-filters" id="formFiltros" autocomplete="off">
            <label class="tej-field tej-field--search" for="filtroBusqueda">
                <span>Buscar</span>
                <div class="tej-search">
                    <span aria-hidden="true">⌕</span>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        maxlength="120"
                        placeholder="Folio, técnico, equipo o ubicación"
                    >
                </div>
            </label>

            <label class="tej-field" for="filtroEstado">
                <span>Estado de ejecución</span>
                <select id="filtroEstado">
                    <option value="TODOS">Todos</option>
                    <option value="PENDIENTE">Pendiente</option>
                    <option value="EN_PROCESO">En proceso</option>
                    <option value="PAUSADA">Pausada</option>
                    <option value="TERMINADA">Terminada</option>
                    <option value="CANCELADA">Cancelada</option>
                </select>
            </label>

            <label class="tej-field" for="filtroTipo">
                <span>Tipo</span>
                <select id="filtroTipo">
                    <option value="TODOS">Todos</option>
                    <option value="CORRECTIVO_PROGRAMABLE">Correctivo programable</option>
                    <option value="MODIFICACION_MEJORA">Modificación o mejora</option>
                    <option value="CORRECTIVO_URGENTE">Correctivo urgente</option>
                    <option value="RUTINARIO">Rutinario</option>
                </select>
            </label>

            <label class="tej-field" for="filtroTecnico">
                <span>Técnico</span>
                <select id="filtroTecnico">
                    <option value="">Todos</option>
                </select>
            </label>

            <div class="tej-filter-actions">
                <button type="submit" class="tej-btn tej-btn--primary">Aplicar</button>
                <button type="button" class="tej-btn tej-btn--ghost" id="btnLimpiar">Limpiar</button>
            </div>

            <details class="tej-more-filters" id="filtrosAvanzados">
                <summary>Más filtros</summary>
                <div class="tej-more-filters__body">
                    <label class="tej-field" for="filtroDesde">
                        <span>Desde</span>
                        <input type="date" id="filtroDesde">
                    </label>
                    <label class="tej-field" for="filtroHasta">
                        <span>Hasta</span>
                        <input type="date" id="filtroHasta">
                    </label>
                    <label class="tej-field" for="filtroEdicion">
                        <span>Corrección</span>
                        <select id="filtroEdicion">
                            <option value="TODOS">Todas</option>
                            <option value="ORIGINAL">Sin corrección</option>
                            <option value="CORREGIDA">Corregidas</option>
                        </select>
                    </label>
                    <label class="tej-field" for="filtroRevision">
                        <span>Integridad</span>
                        <select id="filtroRevision">
                            <option value="TODOS">Todas</option>
                            <option value="CON_ALERTAS">Requieren revisión</option>
                            <option value="SIN_ALERTAS">Sin alertas</option>
                        </select>
                    </label>
                    <label class="tej-field" for="filtroOrden">
                        <span>Ordenar</span>
                        <select id="filtroOrden">
                            <option value="RECIENTES">Más recientes</option>
                            <option value="ANTIGUAS">Más antiguas</option>
                            <option value="MAYOR_ACTIVO">Mayor tiempo activo</option>
                            <option value="MAYOR_PAUSA">Mayor tiempo en pausa</option>
                            <option value="TECNICO">Por técnico</option>
                            <option value="FOLIO">Por folio</option>
                        </select>
                    </label>
                    <label class="tej-field" for="filtroPorPagina">
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

    <section class="tej-card tej-results-card">
        <header class="tej-results-head">
            <div>
                <p class="tej-eyebrow">PARTICIPACIÓN DE TÉCNICOS</p>
                <h2>Tiempos de mantenimiento</h2>
                <p id="textoResultados">Consultando información...</p>
            </div>
            <div class="tej-results-head__meta">
                <span class="tej-count" id="contadorResultados">0 registros</span>
                <span class="tej-efficiency" id="promedioActivo">0% activo</span>
            </div>
        </header>

        <div class="tej-table-wrap" id="contenedorTabla" hidden>
            <table class="tej-table">
                <thead>
                    <tr>
                        <th>Mantenimiento</th>
                        <th>Técnico</th>
                        <th>Estado</th>
                        <th>Inicio y fin</th>
                        <th>Tiempo registrado</th>
                        <th>Control</th>
                    </tr>
                </thead>
                <tbody id="cuerpoTabla"></tbody>
            </table>
        </div>

        <div class="tej-empty" id="estadoVacio" hidden>
            <span aria-hidden="true">◴</span>
            <h3>No hay ejecuciones con esos filtros</h3>
            <p>Amplía el rango de fechas o limpia los filtros para consultar más registros.</p>
            <button type="button" class="tej-btn tej-btn--secondary" id="btnLimpiarVacio">
                Mostrar el mes actual
            </button>
        </div>

        <footer class="tej-pagination-wrap" id="contenedorPaginacion" hidden>
            <p id="textoPaginacion">0 resultados</p>
            <nav class="tej-pagination" id="paginacion" aria-label="Paginación"></nav>
        </footer>
    </section>

    <div class="tej-tools-background" aria-hidden="true"></div>
</main>

<section class="tej-modal" id="modalDetalle" hidden aria-hidden="true">
    <div class="tej-modal__dialog tej-modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="detalleTitulo">
        <header class="tej-modal__header">
            <div>
                <p class="tej-eyebrow">EXPEDIENTE DE EJECUCIÓN</p>
                <h2 id="detalleTitulo">Detalle de tiempo</h2>
                <p id="detalleSubtitulo">Consultando información...</p>
            </div>
            <button type="button" class="tej-modal__close" data-cerrar-modal="modalDetalle" aria-label="Cerrar">×</button>
        </header>

        <div class="tej-modal__body" id="detalleContenido">
            <div class="tej-loading">
                <span></span>
                <p>Cargando detalle...</p>
            </div>
        </div>

        <footer class="tej-modal__footer">
            <a class="tej-btn tej-btn--secondary" id="enlaceExpediente" href="solicitudes_historial.php">
                Abrir solicitud completa
            </a>
            <button type="button" class="tej-btn tej-btn--warning" id="btnCorregir" hidden>
                Corregir tiempos
            </button>
            <button type="button" class="tej-btn tej-btn--ghost" data-cerrar-modal="modalDetalle">
                Cerrar
            </button>
        </footer>
    </div>
</section>

<section class="tej-modal" id="modalCorreccion" hidden aria-hidden="true">
    <div class="tej-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="correccionTitulo">
        <header class="tej-modal__header">
            <div>
                <p class="tej-eyebrow">CORRECCIÓN AUDITADA</p>
                <h2 id="correccionTitulo">Corregir tiempos</h2>
                <p id="correccionSubtitulo">Los valores originales no serán eliminados.</p>
            </div>
            <button type="button" class="tej-modal__close" data-cerrar-modal="modalCorreccion" aria-label="Cerrar">×</button>
        </header>

        <form id="formCorreccion" novalidate>
            <div class="tej-modal__body">
                <input type="hidden" name="accion" value="CORREGIR_TIEMPOS">
                <input type="hidden" name="ejecucion_id" id="correccionEjecucionId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <section class="tej-warning-box">
                    <span aria-hidden="true">!</span>
                    <div>
                        <strong>Esta acción modifica un registro histórico.</strong>
                        <p>
                            El sistema recalculará el tiempo activo y el cumplimiento por fecha,
                            guardará los valores anteriores y retirará el incumplimiento relacionado
                            únicamente cuando la corrección demuestre que el técnico terminó dentro del plazo.
                        </p>
                    </div>
                </section>

                <div class="tej-correction-context" id="correccionContexto"></div>

                <div class="tej-form-grid">
                    <label class="tej-field" for="correccionInicio">
                        <span>Inicio real *</span>
                        <input type="datetime-local" id="correccionInicio" name="fecha_hora_inicio" required>
                        <small id="limiteInicioTexto">Debe ser posterior a la solicitud.</small>
                    </label>
                    <label class="tej-field" for="correccionFin">
                        <span>Finalización real *</span>
                        <input type="datetime-local" id="correccionFin" name="fecha_hora_fin" required>
                        <small id="limiteFinTexto">No puede superar el cierre general.</small>
                    </label>
                    <label class="tej-field tej-field--full" for="correccionMotivo">
                        <span>Motivo de la corrección *</span>
                        <textarea
                            id="correccionMotivo"
                            name="motivo"
                            rows="4"
                            minlength="15"
                            maxlength="500"
                            placeholder="Explica qué dato era incorrecto y cómo se verificó el horario correcto."
                            required
                        ></textarea>
                        <small><span id="contadorMotivo">0</span>/500 caracteres · mínimo 15</small>
                    </label>
                </div>

                <section class="tej-preview">
                    <div>
                        <span>Tiempo transcurrido</span>
                        <strong id="previewTranscurrido">00:00:00</strong>
                    </div>
                    <div>
                        <span>Pausas conservadas</span>
                        <strong id="previewPausas">00:00:00</strong>
                    </div>
                    <div>
                        <span>Nuevo tiempo activo</span>
                        <strong id="previewActivo">00:00:00</strong>
                    </div>
                </section>

                <div class="tej-form-error" id="errorCorreccion" hidden></div>
            </div>

            <footer class="tej-modal__footer">
                <button type="button" class="tej-btn tej-btn--danger" id="btnRestaurar" hidden>
                    Restaurar valores originales
                </button>
                <span class="tej-footer-spacer"></span>
                <button type="button" class="tej-btn tej-btn--ghost" data-cerrar-modal="modalCorreccion">
                    Cancelar
                </button>
                <button type="submit" class="tej-btn tej-btn--primary" id="btnGuardarCorreccion">
                    Guardar corrección
                </button>
            </footer>
        </form>
    </div>
</section>

<div class="tej-toast" id="toast" hidden role="status" aria-live="polite"></div>

<?php require_once __DIR__ . '/../inc/alertas.php'; ?>

<script>
(function () {
    'use strict';

    var API_PATH = window.location.pathname;
    var CSRF_TOKEN = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var estado = {
        pagina: 1,
        catalogosCargados: false,
        filtrosServidor: null,
        detalle: null,
        pausasDetalle: [],
        cargando: false
    };
    var ui = {};
    var temporizadorBusqueda = null;

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        capturarElementos();
        registrarEventos();
        cargar(true);
    }

    function capturarElementos() {
        [
            'btnExportar', 'btnActualizar', 'estadoPagina',
            'kpiTotal', 'kpiActivos', 'kpiPausadas', 'kpiTiempoActivo',
            'kpiCorregidas', 'kpiAlertas', 'formFiltros', 'filtroBusqueda',
            'filtroEstado', 'filtroTipo', 'filtroTecnico', 'filtroDesde',
            'filtroHasta', 'filtroEdicion', 'filtroRevision', 'filtroOrden',
            'filtroPorPagina', 'btnLimpiar', 'btnLimpiarVacio', 'textoResultados',
            'contadorResultados', 'promedioActivo', 'contenedorTabla', 'cuerpoTabla',
            'estadoVacio', 'contenedorPaginacion', 'textoPaginacion', 'paginacion',
            'modalDetalle', 'detalleTitulo', 'detalleSubtitulo', 'detalleContenido',
            'enlaceExpediente', 'btnCorregir', 'modalCorreccion', 'formCorreccion',
            'correccionEjecucionId', 'correccionContexto', 'correccionInicio',
            'correccionFin', 'correccionMotivo', 'contadorMotivo',
            'limiteInicioTexto', 'limiteFinTexto', 'previewTranscurrido',
            'previewPausas', 'previewActivo', 'errorCorreccion', 'btnRestaurar',
            'btnGuardarCorreccion', 'toast'
        ].forEach(function (id) {
            ui[id] = document.getElementById(id);
        });
    }

    function registrarEventos() {
        ui.formFiltros.addEventListener('submit', function (evento) {
            evento.preventDefault();
            estado.pagina = 1;
            cargar(false);
        });

        ui.btnActualizar.addEventListener('click', function () {
            cargar(false, true);
        });

        ui.btnExportar.addEventListener('click', exportar);
        ui.btnLimpiar.addEventListener('click', limpiarFiltros);
        ui.btnLimpiarVacio.addEventListener('click', limpiarFiltros);

        ui.filtroBusqueda.addEventListener('input', function () {
            window.clearTimeout(temporizadorBusqueda);
            temporizadorBusqueda = window.setTimeout(function () {
                estado.pagina = 1;
                cargar(false);
            }, 500);
        });

        ui.filtroPorPagina.addEventListener('change', function () {
            estado.pagina = 1;
            cargar(false);
        });

        ui.cuerpoTabla.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-ver-ejecucion]');
            if (boton) {
                abrirDetalle(Number(boton.getAttribute('data-ver-ejecucion')));
            }
        });

        ui.paginacion.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-pagina]');
            if (!boton || boton.disabled) {
                return;
            }
            estado.pagina = Number(boton.getAttribute('data-pagina')) || 1;
            cargar(false);
            window.scrollTo({ top: ui.contenedorTabla.offsetTop - 120, behavior: 'smooth' });
        });

        document.querySelectorAll('[data-cerrar-modal]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                cerrarModal(document.getElementById(boton.getAttribute('data-cerrar-modal')));
            });
        });

        [ui.modalDetalle, ui.modalCorreccion].forEach(function (modal) {
            modal.addEventListener('click', function (evento) {
                if (evento.target === modal) {
                    cerrarModal(modal);
                }
            });
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Escape') {
                return;
            }
            if (!ui.modalCorreccion.hidden) {
                cerrarModal(ui.modalCorreccion);
            } else if (!ui.modalDetalle.hidden) {
                cerrarModal(ui.modalDetalle);
            }
        });

        ui.btnCorregir.addEventListener('click', abrirCorreccion);
        ui.formCorreccion.addEventListener('submit', guardarCorreccion);
        ui.btnRestaurar.addEventListener('click', restaurarOriginales);
        ui.correccionInicio.addEventListener('input', actualizarPreview);
        ui.correccionFin.addEventListener('input', actualizarPreview);
        ui.correccionMotivo.addEventListener('input', function () {
            ui.contadorMotivo.textContent = String(ui.correccionMotivo.value.length);
        });
    }

    async function cargar(inicial, confirmar) {
        if (estado.cargando) {
            return;
        }

        if (!validarFechas()) {
            return;
        }

        estado.cargando = true;
        mostrarEstado('Cargando tiempos de ejecución...', 'loading');
        bloquearBoton(ui.btnActualizar, true, 'Actualizando...');

        try {
            var parametros = obtenerParametros('LISTAR');
            var datos = await solicitar(crearUrlApi(parametros));

            if (datos.csrf_token) {
                CSRF_TOKEN = datos.csrf_token;
            }

            if (!estado.catalogosCargados || inicial) {
                llenarCatalogos(datos.catalogos || {});
                aplicarFiltrosServidor(datos.filtros || {});
                estado.catalogosCargados = true;
            }

            estado.filtrosServidor = datos.filtros || null;
            renderizarResumen(datos.resumen || {});
            renderizarRegistros(datos.registros || []);
            renderizarPaginacion(datos.paginacion || {});
            ocultarEstado();

            if (confirmar) {
                toast('Información actualizada.', 'success');
            }
        } catch (error) {
            mostrarEstado(error.message || 'No fue posible cargar la información.', 'error');
            ui.contenedorTabla.hidden = true;
            ui.estadoVacio.hidden = false;
        } finally {
            estado.cargando = false;
            bloquearBoton(ui.btnActualizar, false, '↻ Actualizar');
        }
    }

    function obtenerParametros(accion) {
        var parametros = new URLSearchParams();
        parametros.set('accion', accion || 'LISTAR');
        parametros.set('busqueda', ui.filtroBusqueda.value.trim());
        parametros.set('estado', ui.filtroEstado.value);
        parametros.set('tipo', ui.filtroTipo.value);
        parametros.set('tecnico_id', ui.filtroTecnico.value);
        parametros.set('desde', ui.filtroDesde.value);
        parametros.set('hasta', ui.filtroHasta.value);
        parametros.set('edicion', ui.filtroEdicion.value);
        parametros.set('revision', ui.filtroRevision.value);
        parametros.set('orden', ui.filtroOrden.value);
        parametros.set('por_pagina', ui.filtroPorPagina.value);
        parametros.set('pagina', String(estado.pagina));
        parametros.set('_', String(Date.now()));
        return parametros;
    }

    function aplicarFiltrosServidor(filtros) {
        ui.filtroBusqueda.value = filtros.busqueda || '';
        ui.filtroDesde.value = filtros.desde || '';
        ui.filtroHasta.value = filtros.hasta || '';
        ui.filtroEstado.value = filtros.estado || 'TODOS';
        ui.filtroTipo.value = filtros.tipo || 'TODOS';
        ui.filtroEdicion.value = filtros.edicion || 'TODOS';
        ui.filtroRevision.value = filtros.revision || 'TODOS';
        ui.filtroOrden.value = filtros.orden || 'RECIENTES';
        ui.filtroPorPagina.value = String(filtros.por_pagina || 24);
        ui.filtroTecnico.value = filtros.tecnico_id ? String(filtros.tecnico_id) : '';
    }

    function llenarCatalogos(catalogos) {
        var valorActual = ui.filtroTecnico.value;
        var opciones = ['<option value="">Todos</option>'];
        (catalogos.tecnicos || []).forEach(function (tecnico) {
            opciones.push(
                '<option value="' + Number(tecnico.id) + '">' +
                escapeHtml(tecnico.nombre || 'Técnico') +
                (Number(tecnico.activo) === 1 ? '' : ' · Inactivo') +
                '</option>'
            );
        });
        ui.filtroTecnico.innerHTML = opciones.join('');
        if (valorActual) {
            ui.filtroTecnico.value = valorActual;
        }
    }

    function renderizarResumen(resumen) {
        ui.kpiTotal.textContent = numero(resumen.total);
        ui.kpiActivos.textContent = numero(resumen.en_proceso);
        ui.kpiPausadas.textContent = numero(resumen.pausadas);
        ui.kpiTiempoActivo.textContent = duracionCompacta(resumen.segundos_activos);
        ui.kpiCorregidas.textContent = numero(resumen.corregidas);
        ui.kpiAlertas.textContent = numero(resumen.con_alertas);
        ui.promedioActivo.textContent = numeroDecimal(resumen.promedio_porcentaje_activo) + '% activo';
    }

    function renderizarRegistros(registros) {
        ui.cuerpoTabla.innerHTML = '';

        if (!registros.length) {
            ui.contenedorTabla.hidden = true;
            ui.estadoVacio.hidden = false;
            ui.textoResultados.textContent = 'No hay tiempos registrados que coincidan con los filtros.';
            ui.contadorResultados.textContent = '0 registros';
            return;
        }

        ui.estadoVacio.hidden = true;
        ui.contenedorTabla.hidden = false;
        ui.textoResultados.textContent = 'Cada registro corresponde al tiempo de un técnico dentro de un mantenimiento.';
        ui.contadorResultados.textContent = registros.length + (registros.length === 1 ? ' registro visible' : ' registros visibles');

        ui.cuerpoTabla.innerHTML = registros.map(function (item) {
            var alertas = Array.isArray(item.alertas_revision) ? item.alertas_revision : [];
            var control = '';

            if (alertas.length) {
                control += '<span class="tej-control tej-control--warning" title="' + escapeHtml(alertas.join(' · ')) + '">! Revisar</span>';
            } else {
                control += '<span class="tej-control tej-control--ok">✓ Íntegro</span>';
            }

            if (Number(item.fue_editada) === 1) {
                control += '<span class="tej-control tej-control--edited">✎ Corregido</span>';
            }

            return '<tr>' +
                '<td data-label="Mantenimiento">' +
                    '<div class="tej-request-cell">' +
                        '<strong>' + escapeHtml(item.folio || 'Sin folio') + '</strong>' +
                        '<span>' + escapeHtml(tipoTexto(item.tipo_solicitud)) + '</span>' +
                        '<small>' + escapeHtml((item.codigo_equipo || 'S/C') + ' · ' + (item.nombre_equipo || 'Sin equipo')) + '</small>' +
                    '</div>' +
                '</td>' +
                '<td data-label="Técnico">' +
                    '<div class="tej-tech-cell">' +
                        '<strong>' + escapeHtml(item.tecnico || 'Técnico') + '</strong>' +
                        '<span>' + escapeHtml(turnoTexto(item.turno)) + '</span>' +
                        '<small>' + escapeHtml(item.especialidad || 'Sin especialidad') + '</small>' +
                    '</div>' +
                '</td>' +
                '<td data-label="Estado">' +
                    badgeEstado(item.estado_ejecucion) +
                    '<small class="tej-cell-note">' + escapeHtml(cumplimientoTexto(item.resultado_cumplimiento)) + '</small>' +
                '</td>' +
                '<td data-label="Inicio y fin">' +
                    '<div class="tej-dates">' +
                        '<span><b>Inicio</b>' + escapeHtml(fechaHora(item.fecha_hora_inicio)) + '</span>' +
                        '<span><b>Fin</b>' + escapeHtml(fechaHora(item.fecha_hora_fin)) + '</span>' +
                    '</div>' +
                '</td>' +
                '<td data-label="Tiempo registrado">' +
                    '<div class="tej-time-cell">' +
                        '<strong>' + escapeHtml(duracion(item.segundos_activos_actuales)) + '</strong>' +
                        '<span>' + escapeHtml(duracion(item.segundos_pausa_actuales)) + ' en pausa</span>' +
                        porcentajeBarra(item.porcentaje_activo) +
                    '</div>' +
                '</td>' +
                '<td data-label="Control">' +
                    '<div class="tej-control-cell">' + control +
                        '<button type="button" class="tej-btn-table" data-ver-ejecucion="' + Number(item.id) + '">Ver detalle</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    function renderizarPaginacion(paginacion) {
        var total = Number(paginacion.total || 0);
        var pagina = Number(paginacion.pagina || 1);
        var totalPaginas = Number(paginacion.total_paginas || 1);

        if (total === 0) {
            ui.contenedorPaginacion.hidden = true;
            return;
        }

        ui.contenedorPaginacion.hidden = false;
        ui.textoPaginacion.textContent = 'Mostrando ' + numero(paginacion.desde) + '–' + numero(paginacion.hasta) + ' de ' + numero(total);

        var paginas = paginasVisibles(pagina, totalPaginas);
        var html = '<button type="button" data-pagina="' + Math.max(1, pagina - 1) + '" ' + (pagina <= 1 ? 'disabled' : '') + '>‹</button>';

        paginas.forEach(function (item) {
            if (item === '...') {
                html += '<span>…</span>';
            } else {
                html += '<button type="button" data-pagina="' + item + '" class="' + (item === pagina ? 'is-active' : '') + '">' + item + '</button>';
            }
        });

        html += '<button type="button" data-pagina="' + Math.min(totalPaginas, pagina + 1) + '" ' + (pagina >= totalPaginas ? 'disabled' : '') + '>›</button>';
        ui.paginacion.innerHTML = html;
    }

    function paginasVisibles(actual, total) {
        if (total <= 7) {
            return Array.from({ length: total }, function (_, i) { return i + 1; });
        }
        var paginas = [1];
        if (actual > 4) {
            paginas.push('...');
        }
        var inicio = Math.max(2, actual - 1);
        var fin = Math.min(total - 1, actual + 1);
        for (var i = inicio; i <= fin; i += 1) {
            paginas.push(i);
        }
        if (actual < total - 3) {
            paginas.push('...');
        }
        paginas.push(total);
        return paginas;
    }

    async function abrirDetalle(id) {
        estado.detalle = null;
        estado.pausasDetalle = [];
        ui.detalleTitulo.textContent = 'Detalle de tiempo';
        ui.detalleSubtitulo.textContent = 'Consultando información...';
        ui.detalleContenido.innerHTML = '<div class="tej-loading"><span></span><p>Cargando detalle...</p></div>';
        ui.btnCorregir.hidden = true;
        abrirModal(ui.modalDetalle);

        try {
            var detalleParametros = new URLSearchParams();
            detalleParametros.set('accion', 'DETALLE');
            detalleParametros.set('id', String(id));
            detalleParametros.set('_', String(Date.now()));
            var datos = await solicitar(crearUrlApi(detalleParametros));
            if (datos.csrf_token) {
                CSRF_TOKEN = datos.csrf_token;
            }
            estado.detalle = datos.ejecucion || null;
            estado.pausasDetalle = datos.pausas || [];
            renderizarDetalle(datos);
        } catch (error) {
            ui.detalleContenido.innerHTML = '<div class="tej-modal-error"><span>!</span><h3>No se pudo cargar el detalle</h3><p>' + escapeHtml(error.message || 'Ocurrió un error interno.') + '</p></div>';
        }
    }

    function renderizarDetalle(datos) {
        var item = datos.ejecucion || {};
        var pausas = datos.pausas || [];
        var auditoria = datos.auditoria || [];
        var historial = datos.historial || [];

        ui.detalleTitulo.textContent = item.folio || 'Detalle de ejecución';
        ui.detalleSubtitulo.textContent = (item.tecnico || 'Técnico') + ' · ' + (item.codigo_equipo || 'S/C') + ' · ' + (item.nombre_equipo || 'Sin equipo');
        ui.enlaceExpediente.href = 'solicitudes_historial.php?solicitud_id=' + Number(item.solicitud_id || 0);
        ui.btnCorregir.hidden = !item.puede_editar;

        var alertas = Array.isArray(item.alertas_revision) ? item.alertas_revision : [];
        var html = '';

        html += '<section class="tej-detail-hero">' +
            '<div><p>' + escapeHtml(tipoTexto(item.tipo_solicitud)) + '</p><h3>' + escapeHtml(item.tecnico || 'Técnico') + '</h3><span>' + escapeHtml((item.codigo_equipo || 'S/C') + ' · ' + (item.nombre_equipo || 'Sin equipo')) + '</span></div>' +
            '<div class="tej-detail-hero__badges">' + badgeEstado(item.estado_ejecucion) + badgePrioridad(item.prioridad) + '</div>' +
        '</section>';

        if (alertas.length) {
            html += '<section class="tej-alert-box"><strong>Este registro requiere revisión</strong><ul>' + alertas.map(function (alerta) { return '<li>' + escapeHtml(alerta) + '</li>'; }).join('') + '</ul></section>';
        }

        html += '<section class="tej-detail-metrics">' +
            metrica('Tiempo activo', duracion(item.segundos_activos_actuales), 'Trabajo real acumulado') +
            metrica('Tiempo en pausa', duracion(item.segundos_pausa_actuales), pausas.length + (pausas.length === 1 ? ' pausa registrada' : ' pausas registradas')) +
            metrica('Tiempo transcurrido', duracion(item.segundos_transcurridos), 'Entre inicio y finalización') +
            metrica('Proporción activa', item.porcentaje_activo === null ? '—' : numeroDecimal(item.porcentaje_activo) + '%', 'Activo respecto al transcurrido') +
        '</section>';

        html += detalleSeccion('Ejecución del técnico',
            dato('Estado', estadoTexto(item.estado_ejecucion)) +
            dato('Inicio real', fechaHora(item.fecha_hora_inicio)) +
            dato('Finalización real', fechaHora(item.fecha_hora_fin)) +
            dato('Participación', textoEstado(item.estado_participacion)) +
            dato('Resultado', cumplimientoTexto(item.resultado_cumplimiento)) +
            dato('Iniciada por', textoEstado(item.iniciada_por_tipo || 'Sin registro'))
        );

        html += detalleSeccion('Programación y cierre',
            dato('Fecha programada', fecha(item.fecha_programada)) +
            dato('Fecha límite', fecha(item.fecha_limite)) +
            dato('Cierre general', fechaHora(item.fecha_hora_cierre)) +
            dato('Trabajo quedó', trabajoTexto(item.trabajo_quedo)) +
            dato('Departamento', item.departamento || '—') +
            dato('Área / proceso', (item.area || '—') + ' · ' + (item.proceso || '—'))
        );

        html += '<section class="tej-detail-section"><header><div><p class="tej-eyebrow">PAUSAS</p><h3>Interrupciones registradas</h3></div><span class="tej-count">' + pausas.length + '</span></header>' + renderizarPausas(pausas) + '</section>';

        if (Number(item.fue_editada) === 1) {
            html += '<section class="tej-edited-box"><span>✎</span><div><strong>Tiempos corregidos administrativamente</strong><p>' + escapeHtml(item.motivo_edicion_tiempos || 'Sin motivo visible') + '</p><small>' + escapeHtml(item.admin_editor || 'Administrador') + ' · ' + escapeHtml(fechaHora(item.fecha_actualizacion)) + '</small></div></section>';
        }

        html += '<section class="tej-detail-section"><header><div><p class="tej-eyebrow">AUDITORÍA</p><h3>Correcciones de este registro</h3></div><span class="tej-count">' + auditoria.length + '</span></header>' + renderizarAuditoria(auditoria) + '</section>';
        html += '<section class="tej-detail-section"><header><div><p class="tej-eyebrow">TRAZABILIDAD</p><h3>Eventos relacionados</h3></div></header>' + renderizarHistorial(historial) + '</section>';

        ui.detalleContenido.innerHTML = html;
        ui.detalleContenido.scrollTop = 0;
    }

    function renderizarPausas(pausas) {
        if (!pausas.length) {
            return miniVacio('No existen pausas registradas para esta ejecución.');
        }

        return '<div class="tej-timeline">' + pausas.map(function (pausa) {
            return '<article class="tej-timeline__item ' + (Number(pausa.abierta) === 1 ? 'is-open' : '') + '">' +
                '<span class="tej-timeline__dot">Ⅱ</span>' +
                '<div><header><strong>' + escapeHtml(motivoPausaTexto(pausa.motivo)) + '</strong><span>' + escapeHtml(duracion(pausa.duracion_actual)) + '</span></header>' +
                '<p>' + escapeHtml(fechaHora(pausa.fecha_hora_inicio)) + ' → ' + escapeHtml(fechaHora(pausa.fecha_hora_fin)) + '</p>' +
                '<small>' + escapeHtml(pausa.creada_por || 'Sistema') + (pausa.folio_urgencia ? ' · Urgencia ' + escapeHtml(pausa.folio_urgencia) : '') + (pausa.observaciones ? ' · ' + escapeHtml(pausa.observaciones) : '') + '</small></div>' +
            '</article>';
        }).join('') + '</div>';
    }

    function renderizarAuditoria(auditoria) {
        if (!auditoria.length) {
            return miniVacio('Este registro no ha sido corregido administrativamente.');
        }

        return '<div class="tej-audit-list">' + auditoria.map(function (item) {
            var antes = item.datos_anteriores_obj || {};
            var despues = item.datos_nuevos_obj || {};
            return '<article class="tej-audit-item">' +
                '<header><div><strong>' + escapeHtml(item.actor || 'Administrador') + '</strong><span>' + escapeHtml(fechaHora(item.fecha_evento)) + '</span></div><span class="tej-control tej-control--edited">Corrección</span></header>' +
                '<p>' + escapeHtml(item.motivo || 'Sin motivo') + '</p>' +
                '<div class="tej-audit-compare">' +
                    '<div><span>Antes</span><strong>' + escapeHtml(fechaHora(antes.fecha_hora_inicio)) + ' → ' + escapeHtml(fechaHora(antes.fecha_hora_fin)) + '</strong><small>' + escapeHtml(duracion(antes.total_segundos_activos)) + ' activos</small></div>' +
                    '<div><span>Después</span><strong>' + escapeHtml(fechaHora(despues.fecha_hora_inicio)) + ' → ' + escapeHtml(fechaHora(despues.fecha_hora_fin)) + '</strong><small>' + escapeHtml(duracion(despues.total_segundos_activos)) + ' activos</small></div>' +
                '</div>' +
            '</article>';
        }).join('') + '</div>';
    }

    function renderizarHistorial(historial) {
        if (!historial.length) {
            return miniVacio('No hay eventos operativos disponibles.');
        }

        return '<div class="tej-history-list">' + historial.map(function (item) {
            return '<article><span class="tej-history-list__icon">' + escapeHtml(iconoEvento(item.evento)) + '</span><div><header><strong>' + escapeHtml(textoEstado(item.evento)) + '</strong><time>' + escapeHtml(fechaHora(item.fecha_evento)) + '</time></header><p>' + escapeHtml(item.descripcion || 'Sin descripción') + '</p><small>' + escapeHtml(item.actor || item.actor_tipo || 'Sistema') + '</small></div></article>';
        }).join('') + '</div>';
    }

    function abrirCorreccion() {
        var item = estado.detalle;
        if (!item || !item.puede_editar) {
            toast('Este registro no puede corregirse en su estado actual.', 'error');
            return;
        }

        ui.formCorreccion.reset();
        ui.correccionEjecucionId.value = String(item.id);
        ui.correccionInicio.value = aDatetimeLocal(item.fecha_hora_inicio);
        ui.correccionFin.value = aDatetimeLocal(item.fecha_hora_fin);
        ui.correccionInicio.min = aDatetimeLocal(item.limite_inicio);
        ui.correccionInicio.max = aDatetimeLocal(item.limite_fin);
        ui.correccionFin.min = aDatetimeLocal(item.limite_inicio);
        ui.correccionFin.max = aDatetimeLocal(item.limite_fin);
        ui.correccionMotivo.value = '';
        ui.contadorMotivo.textContent = '0';
        ui.errorCorreccion.hidden = true;
        ui.btnRestaurar.hidden = !item.puede_restaurar;
        ui.correccionContexto.innerHTML =
            '<div><span>Solicitud</span><strong>' + escapeHtml(item.folio || 'Sin folio') + '</strong></div>' +
            '<div><span>Técnico</span><strong>' + escapeHtml(item.tecnico || 'Técnico') + '</strong></div>' +
            '<div><span>Equipo</span><strong>' + escapeHtml((item.codigo_equipo || 'S/C') + ' · ' + (item.nombre_equipo || 'Sin equipo')) + '</strong></div>';
        ui.limiteInicioTexto.textContent = 'No anterior a ' + fechaHora(item.limite_inicio) + '.';
        ui.limiteFinTexto.textContent = 'No posterior al cierre ' + fechaHora(item.limite_fin) + '.';
        actualizarPreview();
        cerrarModal(ui.modalDetalle);
        abrirModal(ui.modalCorreccion);
        window.setTimeout(function () { ui.correccionInicio.focus(); }, 80);
    }

    function actualizarPreview() {
        var inicio = new Date(ui.correccionInicio.value);
        var fin = new Date(ui.correccionFin.value);
        var pausas = estado.pausasDetalle.reduce(function (total, pausa) {
            return total + Number(pausa.duracion_actual || 0);
        }, 0);
        var transcurrido = 0;

        if (!Number.isNaN(inicio.getTime()) && !Number.isNaN(fin.getTime()) && fin > inicio) {
            transcurrido = Math.floor((fin.getTime() - inicio.getTime()) / 1000);
        }

        ui.previewTranscurrido.textContent = duracion(transcurrido);
        ui.previewPausas.textContent = duracion(pausas);
        ui.previewActivo.textContent = transcurrido >= pausas ? duracion(transcurrido - pausas) : 'Rango inválido';
        ui.previewActivo.classList.toggle('is-invalid', transcurrido < pausas);
    }


    function validarCorreccionCliente() {
        var item = estado.detalle || {};
        var inicio = new Date(ui.correccionInicio.value);
        var fin = new Date(ui.correccionFin.value);

        if (Number.isNaN(inicio.getTime()) || Number.isNaN(fin.getTime())) {
            mostrarErrorCorreccion('Selecciona fechas y horas válidas.');
            return false;
        }

        if (fin <= inicio) {
            mostrarErrorCorreccion('La finalización debe ser posterior al inicio.');
            ui.correccionFin.focus();
            return false;
        }

        var minimo = item.limite_inicio ? new Date(aDatetimeLocal(item.limite_inicio)) : null;
        var maximo = item.limite_fin ? new Date(aDatetimeLocal(item.limite_fin)) : null;

        if (minimo && !Number.isNaN(minimo.getTime()) && inicio < minimo) {
            mostrarErrorCorreccion('El inicio no puede ser anterior al registro de la solicitud.');
            ui.correccionInicio.focus();
            return false;
        }

        if (maximo && !Number.isNaN(maximo.getTime()) && fin > maximo) {
            mostrarErrorCorreccion('La finalización no puede ser posterior al cierre general.');
            ui.correccionFin.focus();
            return false;
        }

        for (var i = 0; i < estado.pausasDetalle.length; i += 1) {
            var pausa = estado.pausasDetalle[i];
            if (Number(pausa.abierta) === 1 || !pausa.fecha_hora_fin) {
                mostrarErrorCorreccion('La ejecución conserva una pausa abierta y no puede corregirse todavía.');
                return false;
            }

            var pausaInicio = new Date(aDatetimeLocal(pausa.fecha_hora_inicio));
            var pausaFin = new Date(aDatetimeLocal(pausa.fecha_hora_fin));
            if (pausaInicio < inicio || pausaFin > fin) {
                mostrarErrorCorreccion('El nuevo rango debe contener todas las pausas registradas.');
                return false;
            }
        }

        var transcurrido = Math.floor((fin.getTime() - inicio.getTime()) / 1000);
        var pausas = estado.pausasDetalle.reduce(function (total, pausa) {
            return total + Number(pausa.duracion_actual || 0);
        }, 0);

        if (pausas > transcurrido) {
            mostrarErrorCorreccion('Las pausas superan el nuevo tiempo transcurrido. Amplía el rango.');
            return false;
        }

        return true;
    }

    async function guardarCorreccion(evento) {
        evento.preventDefault();
        ui.errorCorreccion.hidden = true;

        if (!ui.formCorreccion.checkValidity()) {
            ui.formCorreccion.reportValidity();
            return;
        }

        if (ui.correccionMotivo.value.trim().length < 15) {
            mostrarErrorCorreccion('Explica el motivo con al menos 15 caracteres.');
            ui.correccionMotivo.focus();
            return;
        }

        if (!validarCorreccionCliente()) {
            return;
        }

        bloquearBoton(ui.btnGuardarCorreccion, true, 'Guardando...');

        try {
            var formulario = new FormData(ui.formCorreccion);
            formulario.set('csrf_token', CSRF_TOKEN);
            formulario.set('tej_api', '1');
            var datos = await solicitar(crearUrlApi(), { method: 'POST', body: formulario });
            cerrarModal(ui.modalCorreccion);
            toast(datos.mensaje || 'Tiempos corregidos.', 'success');
            await cargar(false);
            abrirDetalle(Number(datos.ejecucion_id));
        } catch (error) {
            mostrarErrorCorreccion(error.message || 'No fue posible guardar la corrección.');
        } finally {
            bloquearBoton(ui.btnGuardarCorreccion, false, 'Guardar corrección');
        }
    }

    async function restaurarOriginales() {
        var item = estado.detalle;
        if (!item || !item.puede_restaurar) {
            return;
        }

        var motivo = ui.correccionMotivo.value.trim();
        if (motivo.length < 15) {
            mostrarErrorCorreccion('Escribe primero el motivo de la restauración con al menos 15 caracteres.');
            ui.correccionMotivo.focus();
            return;
        }

        var acepto = await confirmar(
            '¿Restaurar los tiempos originales?',
            'Se reemplazarán las fechas corregidas por los valores originales conservados por el sistema. La restauración también quedará registrada en auditoría.',
            'Restaurar originales',
            'warning'
        );

        if (!acepto) {
            return;
        }

        bloquearBoton(ui.btnRestaurar, true, 'Restaurando...');

        try {
            var formulario = new FormData();
            formulario.append('accion', 'RESTAURAR_TIEMPOS');
            formulario.append('ejecucion_id', String(item.id));
            formulario.append('motivo', motivo);
            formulario.append('csrf_token', CSRF_TOKEN);
            formulario.set('tej_api', '1');
            var datos = await solicitar(crearUrlApi(), { method: 'POST', body: formulario });
            cerrarModal(ui.modalCorreccion);
            toast(datos.mensaje || 'Valores originales restaurados.', 'success');
            await cargar(false);
            abrirDetalle(Number(datos.ejecucion_id));
        } catch (error) {
            mostrarErrorCorreccion(error.message || 'No fue posible restaurar los valores originales.');
        } finally {
            bloquearBoton(ui.btnRestaurar, false, 'Restaurar valores originales');
        }
    }

    function exportar() {
        if (!validarFechas()) {
            return;
        }
        var parametros = obtenerParametros('EXPORTAR');
        parametros.delete('pagina');
        window.location.href = crearUrlApi(parametros);
    }

    function limpiarFiltros() {
        ui.filtroBusqueda.value = '';
        ui.filtroEstado.value = 'TODOS';
        ui.filtroTipo.value = 'TODOS';
        ui.filtroTecnico.value = '';
        ui.filtroEdicion.value = 'TODOS';
        ui.filtroRevision.value = 'TODOS';
        ui.filtroOrden.value = 'RECIENTES';
        ui.filtroPorPagina.value = '24';
        ui.filtroDesde.value = primerDiaMes();
        ui.filtroHasta.value = hoy();
        estado.pagina = 1;
        cargar(false);
    }

    function validarFechas() {
        if (ui.filtroDesde.value && ui.filtroHasta.value && ui.filtroDesde.value > ui.filtroHasta.value) {
            toast('La fecha inicial no puede ser posterior a la fecha final.', 'error');
            ui.filtroDesde.focus();
            return false;
        }
        return true;
    }

    function abrirModal(modal) {
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('tej-modal-open');
    }

    function cerrarModal(modal) {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        if (ui.modalDetalle.hidden && ui.modalCorreccion.hidden) {
            document.body.classList.remove('tej-modal-open');
        }
    }

    function mostrarEstado(mensaje, tipo) {
        ui.estadoPagina.hidden = false;
        ui.estadoPagina.className = 'tej-status tej-status--' + (tipo || 'info');
        ui.estadoPagina.textContent = mensaje;
    }

    function ocultarEstado() {
        ui.estadoPagina.hidden = true;
    }

    function mostrarErrorCorreccion(mensaje) {
        ui.errorCorreccion.textContent = mensaje;
        ui.errorCorreccion.hidden = false;
    }

    function toast(mensaje, tipo) {
        ui.toast.textContent = mensaje;
        ui.toast.className = 'tej-toast tej-toast--' + (tipo || 'info');
        ui.toast.hidden = false;
        window.clearTimeout(ui.toast._timer);
        ui.toast._timer = window.setTimeout(function () {
            ui.toast.hidden = true;
        }, 4200);
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
            allowEscapeKey: true,
            heightAuto: false,
            buttonsStyling: false,
            customClass: {
                popup: 'tej-swal-popup',
                title: 'tej-swal-title',
                htmlContainer: 'tej-swal-text',
                actions: 'tej-swal-actions',
                confirmButton: 'tej-swal-button ' + (icono === 'warning'
                    ? 'tej-swal-button--danger'
                    : 'tej-swal-button--confirm'),
                cancelButton: 'tej-swal-button tej-swal-button--cancel'
            }
        });

        return resultado.isConfirmed;
    }


    function crearUrlApi(parametros) {
        var url = new URL(API_PATH, window.location.origin);
        url.searchParams.set('tej_api', '1');

        if (parametros instanceof URLSearchParams) {
            parametros.forEach(function (valor, clave) {
                url.searchParams.set(clave, valor);
            });
        }

        return url.toString();
    }

    async function solicitar(url, opciones) {
        var respuesta = await fetch(url, Object.assign({
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }, opciones || {}));

        var texto = await respuesta.text();
        var datos;
        try {
            datos = JSON.parse(texto);
        } catch (error) {
            if (respuesta.status === 404) {
                throw new Error('No se encontró el servicio de Tiempos reales. Reemplaza juntos los tres archivos y actualiza la página.');
            }

            throw new Error(
                'El servidor no pudo procesar Tiempos reales (HTTP ' + respuesta.status + '). ' +
                'Revisa el registro de PHP si el problema continúa.'
            );
        }

        if (datos.sesion_expirada && datos.redirect) {
            window.location.href = datos.redirect;
            throw new Error(datos.mensaje || 'La sesión expiró.');
        }

        if (!respuesta.ok || datos.success === false) {
            throw new Error(datos.mensaje || 'No fue posible completar la operación.');
        }

        return datos;
    }

    function bloquearBoton(boton, bloqueado, texto) {
        if (!boton) {
            return;
        }
        if (!boton.dataset.textoOriginal) {
            boton.dataset.textoOriginal = boton.textContent.trim();
        }
        boton.disabled = bloqueado;
        boton.textContent = bloqueado ? texto : boton.dataset.textoOriginal;
    }

    function detalleSeccion(titulo, contenido) {
        return '<section class="tej-detail-section"><header><div><h3>' + escapeHtml(titulo) + '</h3></div></header><div class="tej-detail-grid">' + contenido + '</div></section>';
    }

    function dato(etiqueta, valor) {
        return '<article class="tej-detail-data"><span>' + escapeHtml(etiqueta) + '</span><strong>' + escapeHtml(valor || '—') + '</strong></article>';
    }

    function metrica(etiqueta, valor, ayuda) {
        return '<article><span>' + escapeHtml(etiqueta) + '</span><strong>' + escapeHtml(valor) + '</strong><small>' + escapeHtml(ayuda) + '</small></article>';
    }

    function miniVacio(texto) {
        return '<div class="tej-mini-empty"><span>—</span><p>' + escapeHtml(texto) + '</p></div>';
    }

    function porcentajeBarra(valor) {
        var porcentaje = valor === null || valor === undefined ? 0 : Math.max(0, Math.min(100, Number(valor)));
        return '<div class="tej-progress" title="' + numeroDecimal(porcentaje) + '% de tiempo activo"><span style="width:' + porcentaje + '%"></span></div>';
    }

    function badgeEstado(estado) {
        var clase = String(estado || '').toLowerCase();
        return '<span class="tej-badge tej-badge--' + escapeHtml(clase) + '">' + escapeHtml(estadoTexto(estado)) + '</span>';
    }

    function badgePrioridad(prioridad) {
        var clase = String(prioridad || 'MEDIA').toLowerCase();
        return '<span class="tej-badge tej-badge--priority-' + escapeHtml(clase) + '">' + escapeHtml(textoEstado(prioridad || 'MEDIA')) + '</span>';
    }

    function estadoTexto(estado) {
        var mapa = {
            PENDIENTE: 'Pendiente', EN_PROCESO: 'En proceso', PAUSADA: 'Pausada',
            TERMINADA: 'Terminada', CANCELADA: 'Cancelada'
        };
        return mapa[estado] || textoEstado(estado || 'Sin estado');
    }

    function tipoTexto(tipo) {
        var mapa = {
            CORRECTIVO_PROGRAMABLE: 'Correctivo programable',
            MODIFICACION_MEJORA: 'Modificación o mejora',
            CORRECTIVO_URGENTE: 'Correctivo urgente',
            RUTINARIO: 'Rutinario'
        };
        return mapa[tipo] || textoEstado(tipo || 'Sin tipo');
    }

    function cumplimientoTexto(valor) {
        var mapa = {
            PENDIENTE: 'Cumplimiento pendiente', A_TIEMPO: 'A tiempo', TARDE: 'Terminado tarde',
            NO_REALIZADO: 'No realizado', NO_APLICA: 'No aplica'
        };
        return mapa[valor] || textoEstado(valor || 'Sin resultado');
    }

    function trabajoTexto(valor) {
        var mapa = { TERMINADO: 'Terminado', PARCIAL: 'Parcial', PROVISIONAL: 'Provisional' };
        return mapa[valor] || 'Sin cierre';
    }

    function turnoTexto(valor) {
        var mapa = { MATUTINO: 'Matutino', VESPERTINO: 'Vespertino', NOCTURNO: 'Nocturno' };
        return mapa[valor] || textoEstado(valor || 'Sin turno');
    }

    function motivoPausaTexto(valor) {
        var mapa = {
            URGENCIA: 'Atención de urgencia', MANUAL: 'Pausa manual', ADMINISTRATIVA: 'Pausa administrativa',
            FALTA_RECURSO: 'Falta de recurso', CAMBIO_PRIORIDAD: 'Cambio de prioridad', OTRO: 'Otra causa'
        };
        return mapa[valor] || textoEstado(valor || 'Pausa');
    }

    function textoEstado(valor) {
        return String(valor || '').toLowerCase().replace(/_/g, ' ').replace(/(^|\s)\S/g, function (letra) { return letra.toUpperCase(); });
    }

    function iconoEvento(evento) {
        var mapa = { INICIADA: '▶', PAUSADA: 'Ⅱ', REANUDADA: '↻', TERMINADA: '✓', EDITADA: '✎', OTRO: '•' };
        return mapa[evento] || '•';
    }

    function fecha(valor) {
        if (!valor) {
            return '—';
        }
        var partes = String(valor).slice(0, 10).split('-');
        if (partes.length !== 3) {
            return String(valor);
        }
        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    function fechaHora(valor) {
        if (!valor) {
            return '—';
        }
        var texto = String(valor).replace('T', ' ');
        return fecha(texto.slice(0, 10)) + (texto.length > 10 ? ' · ' + texto.slice(11, 16) : '');
    }

    function aDatetimeLocal(valor) {
        if (!valor) {
            return '';
        }
        return String(valor).replace(' ', 'T').slice(0, 16);
    }

    function duracion(valor) {
        var segundos = Math.max(0, Number(valor || 0));
        var horas = Math.floor(segundos / 3600);
        var minutos = Math.floor((segundos % 3600) / 60);
        var resto = Math.floor(segundos % 60);
        return rellenar(horas) + ':' + rellenar(minutos) + ':' + rellenar(resto);
    }

    function duracionCompacta(valor) {
        var segundos = Math.max(0, Number(valor || 0));
        var horas = Math.floor(segundos / 3600);
        var minutos = Math.floor((segundos % 3600) / 60);
        if (horas >= 100) {
            return numero(horas) + ' h';
        }
        return rellenar(horas) + ':' + rellenar(minutos);
    }

    function rellenar(valor) {
        return String(Math.floor(valor)).padStart(2, '0');
    }

    function numero(valor) {
        return new Intl.NumberFormat('es-MX').format(Number(valor || 0));
    }

    function numeroDecimal(valor) {
        return new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format(Number(valor || 0));
    }

    function primerDiaMes() {
        var fechaActual = new Date();
        return fechaActual.getFullYear() + '-' + rellenar(fechaActual.getMonth() + 1) + '-01';
    }

    function hoy() {
        var fechaActual = new Date();
        return fechaActual.getFullYear() + '-' + rellenar(fechaActual.getMonth() + 1) + '-' + rellenar(fechaActual.getDate());
    }

    function escapeHtml(valor) {
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