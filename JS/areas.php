<?php

declare(strict_types=1);

/*
 * La interfaz consulta esta misma página mediante ?area_api=1. Así el
 * navegador no depende de una ruta relativa directa hacia /funciones.
 */
if (isset($_GET['area_api'])) {
    $endpoint = __DIR__ . '/../funciones/areas_funciones.php';

    if (!is_file($endpoint)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode(
            [
                'success' => false,
                'mensaje' => 'No se encontró funciones/areas_funciones.php. Copia juntos los tres archivos del módulo en sus carpetas correspondientes.',
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
$nombreAdmin = trim((string) (
    $_SESSION['nombre_completo']
    ?? $_SESSION['nombre']
    ?? $_SESSION['usuario']
    ?? 'Administrador'
));

if ($nombreAdmin === '') {
    $nombreAdmin = 'Administrador';
}

$cssPath = __DIR__ . '/../css/style_areas.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '4.0';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b2944">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Gestión segura del catálogo de áreas del Sistema de Mantenimiento.">
    <title>Áreas | Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="../css/style_areas.css?v=<?= rawurlencode($cssVersion) ?>">
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="area-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="area-icon-layout" viewBox="0 0 24 24">
        <rect x="3" y="3" width="18" height="18" rx="2"/>
        <path d="M3 9h18M9 9v12"/>
    </symbol>
    <symbol id="area-icon-plus" viewBox="0 0 24 24">
        <path d="M12 5v14M5 12h14"/>
    </symbol>
    <symbol id="area-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="area-icon-shield" viewBox="0 0 24 24">
        <path d="M12 2 20 5v6c0 5-3.4 9-8 11-4.6-2-8-6-8-11V5l8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="area-icon-list" viewBox="0 0 24 24">
        <path d="M8 6h13M8 12h13M8 18h13"/>
        <path d="M3 6h.01M3 12h.01M3 18h.01"/>
    </symbol>
    <symbol id="area-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="area-icon-filter" viewBox="0 0 24 24">
        <path d="M3 5h18M6 12h12M10 19h4"/>
    </symbol>
    <symbol id="area-icon-check" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8 12 2.6 2.6L16.5 9"/>
    </symbol>
    <symbol id="area-icon-inactive" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8.5 8.5 7 7M15.5 8.5l-7 7"/>
    </symbol>
    <symbol id="area-icon-link" viewBox="0 0 24 24">
        <path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.2 1.2"/>
        <path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.2-1.2"/>
    </symbol>
    <symbol id="area-icon-building" viewBox="0 0 24 24">
        <path d="M4 21V6l8-3 8 3v15M8 9h.01M12 9h.01M16 9h.01M8 13h.01M12 13h.01M16 13h.01M9 21v-4h6v4"/>
    </symbol>
    <symbol id="area-icon-lock" viewBox="0 0 24 24">
        <rect x="4" y="10" width="16" height="11" rx="2"/>
        <path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>
    </symbol>
    <symbol id="area-icon-warning" viewBox="0 0 24 24">
        <path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
        <path d="M12 9v4M12 17h.01"/>
    </symbol>
</svg>

<main class="area-page">
    <header class="area-heading" aria-labelledby="tituloAreas">
        <div class="area-heading__pattern" aria-hidden="true"></div>

        <div class="area-heading__content">
            <div class="area-heading__copy">
                <p class="area-eyebrow">
                    <span class="area-eyebrow__icon"><svg><use href="#area-icon-layout"></use></svg></span>
                    Catálogo operativo
                </p>
                <h1 id="tituloAreas">Áreas</h1>
                <p>
                    Organiza las zonas de trabajo de cada departamento y conserva una ubicación
                    consistente para procesos, equipos, solicitudes y rutinas de mantenimiento.
                </p>

                <div class="area-heading__meta">
                    <span><i class="area-live-dot" aria-hidden="true"></i> Catálogo protegido y trazable</span>
                    <span>Administrador: <strong><?= htmlspecialchars($nombreAdmin, ENT_QUOTES, 'UTF-8') ?></strong></span>
                </div>
            </div>

            <div class="area-heading__actions" aria-label="Acciones del catálogo de áreas">
                <button type="button" class="area-btn area-btn--secondary" id="btnActualizar">
                    <svg><use href="#area-icon-refresh"></use></svg>
                    <span>Actualizar</span>
                </button>
                <button type="button" class="area-btn area-btn--primary" id="btnNueva">
                    <svg><use href="#area-icon-plus"></use></svg>
                    <span>Nueva área</span>
                </button>
            </div>

            <div class="area-heading__mini-card" aria-hidden="true">
                <span><svg><use href="#area-icon-building"></use></svg></span>
                <div>
                    <small>Estructura operativa</small>
                    <strong>Departamento y áreas vinculadas</strong>
                </div>
            </div>
        </div>
    </header>

    <section class="area-security-note" aria-label="Reglas del catálogo">
        <span class="area-security-note__icon"><svg><use href="#area-icon-shield"></use></svg></span>
        <div>
            <strong>Ubicación e historial protegidos</strong>
            <p>
                Las áreas se desactivan, no se eliminan. Cuando existe información relacionada,
                el departamento queda bloqueado para preservar correctamente la trazabilidad histórica.
            </p>
        </div>
        <span class="area-security-note__badge">Auditoría activa</span>
    </section>

    <div class="area-status" id="estadoPagina" role="status" aria-live="polite">
        <span class="area-spinner area-spinner--small" aria-hidden="true"></span>
        <span>Cargando áreas...</span>
    </div>

    <section class="area-kpis" aria-label="Resumen de áreas">
        <article class="area-kpi area-kpi--total">
            <span class="area-kpi__icon"><svg><use href="#area-icon-layout"></use></svg></span>
            <span class="area-kpi__body">
                <span>Total</span>
                <strong id="kpiTotal">0</strong>
                <small>Áreas registradas</small>
            </span>
        </article>
        <article class="area-kpi area-kpi--active">
            <span class="area-kpi__icon"><svg><use href="#area-icon-check"></use></svg></span>
            <span class="area-kpi__body">
                <span>Activas</span>
                <strong id="kpiActivas">0</strong>
                <small>Disponibles en formularios</small>
            </span>
        </article>
        <article class="area-kpi area-kpi--inactive">
            <span class="area-kpi__icon"><svg><use href="#area-icon-inactive"></use></svg></span>
            <span class="area-kpi__body">
                <span>Inactivas</span>
                <strong id="kpiInactivas">0</strong>
                <small>Conservadas por historial</small>
            </span>
        </article>
        <article class="area-kpi area-kpi--use">
            <span class="area-kpi__icon"><svg><use href="#area-icon-link"></use></svg></span>
            <span class="area-kpi__body">
                <span>En uso</span>
                <strong id="kpiEnUso">0</strong>
                <small>Con relaciones activas</small>
            </span>
        </article>
    </section>

    <section class="area-card area-filters-card" aria-labelledby="tituloFiltrosAreas">
        <header class="area-section-head">
            <div>
                <p class="area-eyebrow">Búsqueda y filtros</p>
                <h2 id="tituloFiltrosAreas">Encuentra un área</h2>
                <p>Busca por nombre, departamento o descripción y limita el catálogo por estado.</p>
            </div>
            <span class="area-section-head__chip"><svg><use href="#area-icon-filter"></use></svg> Consulta local</span>
        </header>

        <div class="area-filters">
            <label class="area-field area-field--search" for="filtroBusqueda">
                <span>Buscar</span>
                <div class="area-search">
                    <span aria-hidden="true"><svg><use href="#area-icon-search"></use></svg></span>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        maxlength="100"
                        placeholder="Área, departamento o descripción"
                        autocomplete="off"
                    >
                </div>
            </label>

            <label class="area-field" for="filtroDepartamento">
                <span>Departamento</span>
                <select id="filtroDepartamento">
                    <option value="TODOS">Todos los departamentos</option>
                </select>
            </label>

            <label class="area-field" for="filtroEstado">
                <span>Estado</span>
                <select id="filtroEstado">
                    <option value="TODOS">Todos</option>
                    <option value="ACTIVO">Activas</option>
                    <option value="INACTIVO">Inactivas</option>
                    <option value="EN_USO">En uso</option>
                    <option value="SIN_USO">Sin relaciones activas</option>
                </select>
            </label>

            <label class="area-field area-field--small" for="filtroCantidad">
                <span>Mostrar</span>
                <select id="filtroCantidad">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>

            <div class="area-filter-actions">
                <button type="button" class="area-btn area-btn--ghost" id="btnLimpiar">Limpiar</button>
            </div>
        </div>
    </section>

    <section class="area-card area-results area-results-card" aria-labelledby="tituloListadoAreas">
        <header class="area-results__head">
            <div>
                <p class="area-eyebrow">Resultados</p>
                <h2 id="tituloListadoAreas">Áreas registradas</h2>
                <p id="textoResultados">Preparando resultados...</p>
            </div>

            <div class="area-results__tools">
                <span class="area-updated" id="ultimaActualizacion">Sin actualizar</span>
                <span class="area-results__badge"><svg><use href="#area-icon-list"></use></svg> Listado protegido</span>
            </div>
        </header>

        <div class="area-loading" id="estadoCarga">
            <span class="area-spinner" aria-hidden="true"></span>
            <strong>Cargando áreas...</strong>
        </div>

        <div class="area-empty" id="estadoVacio" hidden>
            <span aria-hidden="true"><svg><use href="#area-icon-search"></use></svg></span>
            <h3>No hay coincidencias</h3>
            <p>Prueba con otro nombre o cambia los filtros.</p>
        </div>

        <div class="area-table-wrap" id="contenedorTabla" hidden tabindex="0" aria-label="Listado desplazable de áreas">
            <table class="area-table">
                <thead>
                    <tr>
                        <th>Área</th>
                        <th>Departamento</th>
                        <th>Descripción</th>
                        <th>Uso actual</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaAreas"></tbody>
            </table>
        </div>

        <footer class="area-pagination" id="paginacion" hidden>
            <span id="textoPaginacion">Sin resultados</span>
            <div class="area-pagination__buttons">
                <button type="button" id="btnAnterior">Anterior</button>
                <span id="paginaActual">Página 1</span>
                <button type="button" id="btnSiguiente">Siguiente</button>
            </div>
        </footer>
    </section>

    <footer class="area-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Catálogo de áreas protegido · Los Chapeteados División Petfood</span>
    </footer>

    <div class="area-tools-background" aria-hidden="true"></div>
</main>

<section class="area-modal" id="modalArea" hidden>
    <div class="area-modal__dialog area-modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="tituloModal">
        <header class="area-modal__header">
            <div>
                <p class="area-eyebrow" id="etiquetaModal">NUEVO REGISTRO</p>
                <h2 id="tituloModal">Nueva área</h2>
                <p id="subtituloModal">Agrega una zona de trabajo dentro de un departamento.</p>
            </div>
            <button type="button" class="area-modal__close" id="btnCerrarModal" aria-label="Cerrar">×</button>
        </header>

        <form id="formArea" novalidate>
            <input type="hidden" id="areaId" name="area_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="area-modal__body">
                <section class="area-form-section">
                    <header>
                        <span>01</span>
                        <div>
                            <h3>Ubicación departamental</h3>
                            <p>Selecciona el departamento al que pertenece esta zona de trabajo.</p>
                        </div>
                    </header>

                    <div class="area-form-grid area-form-grid--one">
                        <label class="area-form-field" for="departamentoId">
                            <span>Departamento *</span>
                            <select id="departamentoId" name="departamento_id" required>
                                <option value="">Selecciona un departamento</option>
                            </select>
                            <small>Solamente se pueden usar departamentos activos.</small>
                            <em class="area-error" id="errorDepartamento"></em>
                        </label>
                    </div>

                    <div class="area-form-alert" id="avisoDepartamentoBloqueado" hidden>
                        <span><svg><use href="#area-icon-lock"></use></svg></span>
                        <div>
                            <strong>La ubicación está protegida</strong>
                            <p>
                                Esta área ya tiene información relacionada. Puedes editar su nombre
                                y descripción, pero no cambiarla de departamento.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="area-form-section">
                    <header>
                        <span>02</span>
                        <div>
                            <h3>Identidad del área</h3>
                            <p>Utiliza un nombre claro y una descripción que facilite su identificación.</p>
                        </div>
                    </header>

                    <div class="area-form-grid area-form-grid--one">
                        <label class="area-form-field" for="nombre">
                            <span>Nombre del área *</span>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                minlength="2"
                                maxlength="100"
                                placeholder="Ej. Línea de empaque"
                                autocomplete="organization-title"
                                required
                            >
                            <small>
                                Debe ser diferente dentro del departamento seleccionado.
                                <b id="contadorNombre">0/100</b>
                            </small>
                            <em class="area-error" id="errorNombre"></em>
                        </label>

                        <label class="area-form-field" for="descripcion">
                            <span>Descripción</span>
                            <textarea
                                id="descripcion"
                                name="descripcion"
                                rows="5"
                                maxlength="500"
                                placeholder="Describe brevemente la función o ubicación del área."
                            ></textarea>
                            <small>
                                Campo opcional.
                                <b id="contadorDescripcion">0/500</b>
                            </small>
                            <em class="area-error" id="errorDescripcion"></em>
                        </label>
                    </div>
                </section>
            </div>

            <footer class="area-modal__footer">
                <button type="button" class="area-btn area-btn--ghost" id="btnCancelar">Cancelar</button>
                <button type="submit" class="area-btn area-btn--primary" id="btnGuardar">Guardar área</button>
            </footer>
        </form>
    </div>
</section>

<section class="area-modal area-confirmation-modal" id="modalConfirmacion" hidden>
    <div class="area-modal__dialog area-modal__dialog--small" role="alertdialog" aria-modal="true" aria-labelledby="tituloConfirmacion" aria-describedby="textoConfirmacion">
        <header class="area-modal__header">
            <div>
                <p class="area-eyebrow">CONFIRMACIÓN SEGURA</p>
                <h2 id="tituloConfirmacion">Confirmar operación</h2>
                <p>La acción quedará registrada en la auditoría del sistema.</p>
            </div>
        </header>

        <div class="area-confirmation">
            <span class="area-confirmation__icon"><svg><use href="#area-icon-warning"></use></svg></span>
            <p id="textoConfirmacion">Revisa la operación antes de continuar.</p>
        </div>

        <footer class="area-modal__footer area-modal__footer--alone">
            <button type="button" class="area-btn area-btn--ghost" id="btnCancelarConfirmacion">Cancelar</button>
            <button type="button" class="area-btn area-btn--primary" id="btnAceptarConfirmacion">Confirmar</button>
        </footer>
    </div>
</section>

<div class="area-toast" id="toast" role="status" aria-live="polite" aria-atomic="true" hidden></div>

<?php include __DIR__ . '/../inc/alertas.php'; ?>
<script>
(function () {
    'use strict';

    var ENDPOINT = window.location.pathname + '?area_api=1';
    var CSRF_TOKEN = <?= json_encode(
        $csrfToken,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;
    var estado = {
        areas: [],
        departamentos: [],
        filtradas: [],
        pagina: 1,
        cantidad: 10,
        cargando: false,
        guardando: false,
        departamentoBloqueado: false,
        resolverConfirmacion: null
    };
    var ui = {};

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        capturarElementos();
        registrarEventos();
        actualizarContadores();
        cargarAreas(false);
    }

    function capturarElementos() {
        [
            'btnNueva', 'btnActualizar', 'btnLimpiar', 'estadoPagina',
            'kpiTotal', 'kpiActivas', 'kpiInactivas', 'kpiEnUso',
            'filtroBusqueda', 'filtroDepartamento', 'filtroEstado',
            'filtroCantidad', 'textoResultados', 'ultimaActualizacion',
            'estadoCarga', 'estadoVacio', 'contenedorTabla', 'tablaAreas',
            'paginacion', 'textoPaginacion', 'btnAnterior', 'btnSiguiente',
            'paginaActual', 'modalArea', 'btnCerrarModal', 'btnCancelar',
            'formArea', 'areaId', 'departamentoId', 'nombre', 'descripcion',
            'contadorNombre', 'contadorDescripcion', 'errorDepartamento',
            'errorNombre', 'errorDescripcion', 'avisoDepartamentoBloqueado',
            'etiquetaModal', 'tituloModal', 'subtituloModal', 'btnGuardar',
            'modalConfirmacion', 'tituloConfirmacion', 'textoConfirmacion',
            'btnCancelarConfirmacion', 'btnAceptarConfirmacion', 'toast'
        ].forEach(function (id) {
            ui[id] = document.getElementById(id);
        });
    }

    function registrarEventos() {
        ui.btnNueva.addEventListener('click', abrirNueva);
        ui.btnActualizar.addEventListener('click', function () {
            cargarAreas(true);
        });
        ui.btnLimpiar.addEventListener('click', limpiarFiltros);
        ui.filtroBusqueda.addEventListener('input', aplicarFiltros);
        ui.filtroDepartamento.addEventListener('change', aplicarFiltros);
        ui.filtroEstado.addEventListener('change', aplicarFiltros);
        ui.filtroCantidad.addEventListener('change', function () {
            estado.cantidad = Number(ui.filtroCantidad.value) || 0;
            estado.pagina = 1;
            renderizar();
        });
        ui.btnAnterior.addEventListener('click', function () {
            if (estado.pagina > 1) {
                estado.pagina--;
                renderizar();
            }
        });
        ui.btnSiguiente.addEventListener('click', function () {
            if (estado.pagina < totalPaginas()) {
                estado.pagina++;
                renderizar();
            }
        });
        ui.btnCerrarModal.addEventListener('click', cerrarModal);
        ui.btnCancelar.addEventListener('click', cerrarModal);
        ui.modalArea.addEventListener('click', function (evento) {
            if (evento.target === ui.modalArea) {
                cerrarModal();
            }
        });
        ui.btnCancelarConfirmacion.addEventListener('click', function () {
            cerrarConfirmacion(false);
        });
        ui.btnAceptarConfirmacion.addEventListener('click', function () {
            cerrarConfirmacion(true);
        });
        ui.modalConfirmacion.addEventListener('click', function (evento) {
            if (evento.target === ui.modalConfirmacion) {
                cerrarConfirmacion(false);
            }
        });
        document.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Escape') {
                return;
            }
            if (!ui.modalConfirmacion.hidden) {
                cerrarConfirmacion(false);
                return;
            }
            if (!ui.modalArea.hidden) {
                cerrarModal();
            }
        });
        ui.formArea.addEventListener('submit', guardarArea);
        ui.departamentoId.addEventListener('change', function () {
            ui.errorDepartamento.textContent = '';
        });
        ui.nombre.addEventListener('input', function () {
            ui.errorNombre.textContent = '';
            actualizarContadores();
        });
        ui.descripcion.addEventListener('input', function () {
            ui.errorDescripcion.textContent = '';
            actualizarContadores();
        });
        ui.tablaAreas.addEventListener('click', manejarAccionTabla);
    }

    async function cargarAreas(mostrarConfirmacion) {
        if (estado.cargando) {
            return;
        }

        estado.cargando = true;
        bloquearBoton(ui.btnActualizar, true, 'Actualizando...');
        mostrarCarga(true);
        mostrarEstado('Consultando áreas...', 'info');

        try {
            var respuesta = await solicitar(
                ENDPOINT + '&accion=INICIAL&t=' + Date.now()
            );
            estado.areas = Array.isArray(respuesta.areas) ? respuesta.areas : [];
            estado.departamentos = Array.isArray(respuesta.departamentos)
                ? respuesta.departamentos
                : [];
            llenarFiltroDepartamentos();
            pintarResumen(respuesta.resumen || {});
            aplicarFiltros();
            ui.ultimaActualizacion.textContent = 'Actualizado ' + horaActual();
            mostrarEstado('Información actualizada correctamente.', 'success');

            if (mostrarConfirmacion) {
                toast('Lista actualizada.', 'success');
            }
        } catch (error) {
            mostrarEstado(error.message || 'No fue posible cargar las áreas.', 'error');
            estado.areas = [];
            estado.departamentos = [];
            llenarFiltroDepartamentos();
            aplicarFiltros();
        } finally {
            estado.cargando = false;
            bloquearBoton(ui.btnActualizar, false);
            mostrarCarga(false);
        }
    }

    function llenarFiltroDepartamentos() {
        var valorActual = ui.filtroDepartamento.value || 'TODOS';
        var opciones = ['<option value="TODOS">Todos los departamentos</option>'];

        estado.departamentos.forEach(function (departamento) {
            opciones.push(
                '<option value="' + Number(departamento.id) + '">' +
                    escapeHtml(departamento.nombre || '') +
                    (Number(departamento.activo) === 1 ? '' : ' · Inactivo') +
                '</option>'
            );
        });

        ui.filtroDepartamento.innerHTML = opciones.join('');
        ui.filtroDepartamento.value = existeOpcion(
            ui.filtroDepartamento,
            valorActual
        ) ? valorActual : 'TODOS';
    }

    function aplicarFiltros() {
        var busqueda = normalizar(ui.filtroBusqueda.value);
        var filtroDepartamento = ui.filtroDepartamento.value;
        var filtroEstado = ui.filtroEstado.value;

        estado.filtradas = estado.areas.filter(function (area) {
            var coincideTexto = busqueda === '' || normalizar(
                String(area.nombre || '') + ' ' +
                String(area.departamento || '') + ' ' +
                String(area.descripcion || '')
            ).indexOf(busqueda) !== -1;

            var coincideDepartamento = filtroDepartamento === 'TODOS'
                || Number(area.departamento_id) === Number(filtroDepartamento);
            var activa = Number(area.activo) === 1;
            var enUso = Number(area.total_relaciones_activas) > 0;
            var coincideEstado = filtroEstado === 'TODOS'
                || (filtroEstado === 'ACTIVO' && activa)
                || (filtroEstado === 'INACTIVO' && !activa)
                || (filtroEstado === 'EN_USO' && enUso)
                || (filtroEstado === 'SIN_USO' && !enUso);

            return coincideTexto && coincideDepartamento && coincideEstado;
        });

        estado.pagina = 1;
        renderizar();
    }

    function renderizar() {
        var total = estado.filtradas.length;
        var paginas = totalPaginas();

        if (estado.pagina > paginas) {
            estado.pagina = paginas;
        }

        var inicio = estado.cantidad > 0
            ? (estado.pagina - 1) * estado.cantidad
            : 0;
        var fin = estado.cantidad > 0
            ? Math.min(inicio + estado.cantidad, total)
            : total;
        var visibles = estado.filtradas.slice(inicio, fin);

        ui.tablaAreas.innerHTML = visibles.map(crearFila).join('');
        ui.textoResultados.textContent = total === 1
            ? '1 área coincide con los filtros.'
            : total + ' áreas coinciden con los filtros.';

        ui.estadoVacio.hidden = total !== 0;
        ui.contenedorTabla.hidden = total === 0;
        ui.paginacion.hidden = total === 0 || estado.cantidad === 0 || paginas <= 1;
        ui.textoPaginacion.textContent = total === 0
            ? 'Sin resultados'
            : 'Mostrando ' + (inicio + 1) + ' a ' + fin + ' de ' + total;
        ui.paginaActual.textContent = 'Página ' + estado.pagina + ' de ' + paginas;
        ui.btnAnterior.disabled = estado.pagina <= 1;
        ui.btnSiguiente.disabled = estado.pagina >= paginas;
    }

    function crearFila(area) {
        var id = Number(area.id);
        var activa = Number(area.activo) === 1;
        var departamentoActivo = Number(area.departamento_activo) === 1;
        var totalUso = Number(area.total_relaciones_activas) || 0;
        var puedeDesactivar = activa && totalUso === 0;
        var reactivar = !activa;
        var estadoDeshabilitado = (activa && !puedeDesactivar)
            || (reactivar && !departamentoActivo);
        var tituloEstado = activa && !puedeDesactivar
            ? 'Primero atiende las relaciones activas.'
            : (reactivar && !departamentoActivo
                ? 'Reactiva primero el departamento.'
                : '');

        return '<tr>' +
            '<td>' +
                '<div class="area-identity">' +
                    '<span>' + escapeHtml(inicial(area.nombre)) + '</span>' +
                    '<div><strong>' + escapeHtml(area.nombre || '') + '</strong>' +
                    '<small>ID ' + id + ' · ' +
                        escapeHtml(area.fecha_registro_texto || 'Sin fecha') +
                    '</small></div>' +
                '</div>' +
            '</td>' +
            '<td>' +
                '<span class="area-parent ' +
                    (departamentoActivo ? '' : 'area-parent--inactive') + '">' +
                    escapeHtml(area.departamento || 'Sin departamento') +
                '</span>' +
            '</td>' +
            '<td><p class="area-description">' +
                escapeHtml(area.descripcion || 'Sin descripción') +
            '</p></td>' +
            '<td>' + crearUso(area) + '</td>' +
            '<td><span class="area-badge ' +
                (activa ? 'area-badge--active' : 'area-badge--inactive') + '">' +
                (activa ? 'Activa' : 'Inactiva') +
            '</span></td>' +
            '<td><div class="area-actions">' +
                '<button type="button" class="area-action" data-action="editar" data-id="' + id + '">Editar</button>' +
                '<button type="button" class="area-action ' +
                    (activa ? 'area-action--danger' : 'area-action--success') + '" ' +
                    'data-action="estado" data-id="' + id + '" data-reactivar="' +
                    (reactivar ? '1' : '0') + '" ' +
                    (estadoDeshabilitado ? 'disabled ' : '') +
                    'title="' + escapeHtml(tituloEstado) + '">' +
                    (activa ? 'Desactivar' : 'Reactivar') +
                '</button>' +
            '</div></td>' +
        '</tr>';
    }

    function crearUso(area) {
        var elementos = [
            ['Procesos', Number(area.procesos_activos) || 0],
            ['Equipos', Number(area.equipos_activos) || 0],
            ['Solicitudes', Number(area.solicitudes_abiertas) || 0],
            ['Rutinas', Number(area.rutinas_activas) || 0]
        ].filter(function (item) {
            return item[1] > 0;
        });

        if (elementos.length === 0) {
            return '<span class="area-no-use">Sin relaciones activas</span>';
        }

        return '<div class="area-usage">' + elementos.map(function (item) {
            return '<span>' + escapeHtml(item[0]) + ' <b>' + item[1] + '</b></span>';
        }).join('') + '</div>';
    }

    async function manejarAccionTabla(evento) {
        var boton = evento.target.closest('[data-action]');
        if (!boton || boton.disabled) {
            return;
        }

        var id = Number(boton.dataset.id);
        if (!Number.isInteger(id) || id < 1) {
            toast('No se pudo identificar el área.', 'error');
            return;
        }

        if (boton.dataset.action === 'editar') {
            await abrirEdicion(id, boton);
        }

        if (boton.dataset.action === 'estado') {
            await cambiarEstado(
                id,
                boton.dataset.reactivar === '1',
                boton
            );
        }
    }

    function abrirNueva() {
        var activos = estado.departamentos.filter(function (departamento) {
            return Number(departamento.activo) === 1;
        });

        if (activos.length === 0) {
            toast('Primero registra o reactiva un departamento.', 'error');
            return;
        }

        limpiarFormulario();
        estado.departamentoBloqueado = false;
        llenarDepartamentosModal(0, false);
        ui.etiquetaModal.textContent = 'NUEVO REGISTRO';
        ui.tituloModal.textContent = 'Nueva área';
        ui.subtituloModal.textContent = 'Agrega una zona de trabajo dentro de un departamento.';
        ui.btnGuardar.textContent = 'Guardar área';
        abrirModal();
    }

    async function abrirEdicion(id, boton) {
        bloquearBoton(boton, true, 'Cargando...');

        try {
            var respuesta = await solicitar(
                ENDPOINT + '&accion=DETALLE&id=' + encodeURIComponent(id)
            );
            var area = respuesta.area || {};

            limpiarFormulario();
            ui.areaId.value = area.id || '';
            estado.departamentoBloqueado =
                Number(area.puede_cambiar_departamento) !== 1;
            llenarDepartamentosModal(
                Number(area.departamento_id),
                estado.departamentoBloqueado
            );
            ui.departamentoId.disabled = estado.departamentoBloqueado;
            ui.avisoDepartamentoBloqueado.hidden = !estado.departamentoBloqueado;
            ui.nombre.value = area.nombre || '';
            ui.descripcion.value = area.descripcion || '';
            ui.etiquetaModal.textContent = 'EDICIÓN';
            ui.tituloModal.textContent = 'Editar área';
            ui.subtituloModal.textContent = 'Actualiza la información sin perder su historial.';
            ui.btnGuardar.textContent = 'Actualizar área';
            actualizarContadores();
            abrirModal();
        } catch (error) {
            toast(error.message || 'No fue posible abrir el área.', 'error');
        } finally {
            bloquearBoton(boton, false);
        }
    }

    function llenarDepartamentosModal(seleccionado, bloquear) {
        var opciones = ['<option value="">Selecciona un departamento</option>'];

        estado.departamentos.forEach(function (departamento) {
            var id = Number(departamento.id);
            var activo = Number(departamento.activo) === 1;
            var esSeleccionado = id === Number(seleccionado);
            var deshabilitado = !activo && !esSeleccionado;

            opciones.push(
                '<option value="' + id + '" ' +
                    (esSeleccionado ? 'selected ' : '') +
                    (deshabilitado ? 'disabled ' : '') + '>' +
                    escapeHtml(departamento.nombre || '') +
                    (activo ? '' : ' · Inactivo') +
                '</option>'
            );
        });

        ui.departamentoId.innerHTML = opciones.join('');
        ui.departamentoId.value = seleccionado ? String(seleccionado) : '';
        ui.departamentoId.disabled = Boolean(bloquear);
    }

    function abrirModal() {
        ui.modalArea.hidden = false;
        document.body.classList.add('area-modal-open');
        window.setTimeout(function () {
            if (!ui.departamentoId.disabled && ui.departamentoId.value === '') {
                ui.departamentoId.focus();
            } else {
                ui.nombre.focus();
            }
        }, 40);
    }

    function cerrarModal() {
        if (estado.guardando) {
            return;
        }
        cerrarModalForzado();
    }

    function cerrarModalForzado() {
        ui.modalArea.hidden = true;
        document.body.classList.remove('area-modal-open');
        limpiarFormulario();
    }

    function limpiarFormulario() {
        ui.formArea.reset();
        ui.areaId.value = '';
        ui.departamentoId.disabled = false;
        ui.avisoDepartamentoBloqueado.hidden = true;
        estado.departamentoBloqueado = false;
        ui.errorDepartamento.textContent = '';
        ui.errorNombre.textContent = '';
        ui.errorDescripcion.textContent = '';
        actualizarContadores();
    }

    async function guardarArea(evento) {
        evento.preventDefault();

        if (estado.guardando) {
            return;
        }

        ui.nombre.value = limpiarEspacios(ui.nombre.value);
        ui.descripcion.value = ui.descripcion.value.trim();

        if (!validarFormulario()) {
            return;
        }

        estado.guardando = true;
        bloquearBoton(ui.btnGuardar, true, 'Guardando...');

        try {
            var formulario = new FormData(ui.formArea);
            formulario.set('accion', 'GUARDAR');
            formulario.set('csrf_token', CSRF_TOKEN);

            if (estado.departamentoBloqueado) {
                formulario.set('departamento_id', ui.departamentoId.value);
            }

            var respuesta = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            cerrarModalForzado();
            await cargarAreas(false);
            toast(respuesta.mensaje || 'Área guardada correctamente.', 'success');
        } catch (error) {
            marcarErrorServidor(error);
            toast(error.message || 'No fue posible guardar el área.', 'error');
        } finally {
            estado.guardando = false;
            bloquearBoton(ui.btnGuardar, false);
        }
    }

    async function cambiarEstado(id, reactivar, boton) {
        var area = estado.areas.find(function (item) {
            return Number(item.id) === id;
        });
        if (!area) {
            toast('El registro ya no está disponible.', 'error');
            return;
        }

        var confirmado = await confirmar(
            reactivar ? '¿Reactivar área?' : '¿Desactivar área?',
            reactivar
                ? 'Volverá a estar disponible para procesos, equipos y solicitudes.'
                : 'Dejará de estar disponible para nuevos registros. Su historial se conservará.',
            reactivar ? 'Sí, reactivar' : 'Sí, desactivar',
            !reactivar
        );

        if (!confirmado) {
            return;
        }

        bloquearBoton(boton, true, 'Procesando...');

        try {
            var formulario = new FormData();
            formulario.set('accion', 'CAMBIAR_ESTADO');
            formulario.set('id', String(id));
            formulario.set('activo', reactivar ? '1' : '0');
            formulario.set('csrf_token', CSRF_TOKEN);

            var respuesta = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });
            await cargarAreas(false);
            toast(respuesta.mensaje || 'Estado actualizado.', 'success');
        } catch (error) {
            toast(error.message || 'No fue posible cambiar el estado.', 'error');
        } finally {
            bloquearBoton(boton, false);
        }
    }

    function validarFormulario() {
        var valido = true;
        var departamento = ui.departamentoId.value;
        var nombre = ui.nombre.value.trim();
        var descripcion = ui.descripcion.value.trim();

        ui.errorDepartamento.textContent = '';
        ui.errorNombre.textContent = '';
        ui.errorDescripcion.textContent = '';

        if (departamento === '') {
            ui.errorDepartamento.textContent = 'Selecciona un departamento.';
            valido = false;
        }

        if (nombre.length < 2 || nombre.length > 100) {
            ui.errorNombre.textContent = 'Escribe un nombre de 2 a 100 caracteres.';
            valido = false;
        }

        if (descripcion.length > 500) {
            ui.errorDescripcion.textContent = 'La descripción no puede superar 500 caracteres.';
            valido = false;
        }

        if (!valido) {
            if (ui.errorDepartamento.textContent && !ui.departamentoId.disabled) {
                ui.departamentoId.focus();
            } else if (ui.errorNombre.textContent) {
                ui.nombre.focus();
            } else {
                ui.descripcion.focus();
            }
        }

        return valido;
    }

    function marcarErrorServidor(error) {
        var campo = error && error.datos ? error.datos.campo : '';
        if (campo === 'departamento_id') {
            ui.errorDepartamento.textContent = error.message || 'Revisa el departamento.';
            if (!ui.departamentoId.disabled) {
                ui.departamentoId.focus();
            }
        }
        if (campo === 'nombre') {
            ui.errorNombre.textContent = error.message || 'Revisa el nombre.';
            ui.nombre.focus();
        }
        if (campo === 'descripcion') {
            ui.errorDescripcion.textContent = error.message || 'Revisa la descripción.';
            ui.descripcion.focus();
        }
    }

    function limpiarFiltros() {
        ui.filtroBusqueda.value = '';
        ui.filtroDepartamento.value = 'TODOS';
        ui.filtroEstado.value = 'TODOS';
        ui.filtroCantidad.value = '10';
        estado.cantidad = 10;
        aplicarFiltros();
        ui.filtroBusqueda.focus();
    }

    function pintarResumen(resumen) {
        ui.kpiTotal.textContent = Number(resumen.total) || 0;
        ui.kpiActivas.textContent = Number(resumen.activas) || 0;
        ui.kpiInactivas.textContent = Number(resumen.inactivas) || 0;
        ui.kpiEnUso.textContent = Number(resumen.en_uso) || 0;
    }

    function mostrarCarga(mostrar) {
        ui.estadoCarga.hidden = !mostrar;
        if (mostrar) {
            ui.estadoVacio.hidden = true;
            ui.contenedorTabla.hidden = true;
            ui.paginacion.hidden = true;
        }
    }

    function mostrarEstado(mensaje, tipo) {
        ui.estadoPagina.textContent = mensaje;
        ui.estadoPagina.className = 'area-status';
        if (tipo === 'error') {
            ui.estadoPagina.classList.add('area-status--error');
        } else if (tipo === 'success') {
            ui.estadoPagina.classList.add('area-status--success');
        }
    }

    function actualizarContadores() {
        ui.contadorNombre.textContent = ui.nombre.value.length + '/100';
        ui.contadorDescripcion.textContent = ui.descripcion.value.length + '/500';
    }

    function totalPaginas() {
        if (estado.cantidad === 0) {
            return 1;
        }
        return Math.max(1, Math.ceil(estado.filtradas.length / estado.cantidad));
    }

    async function solicitar(url, opciones) {
        var config = opciones || {};
        config.headers = Object.assign(
            {'X-Requested-With': 'XMLHttpRequest'},
            config.headers || {}
        );

        var respuesta = await fetch(url, config);
        var texto = await respuesta.text();
        var datos;

        try {
            datos = JSON.parse(texto);
        } catch (e) {
            throw crearError(
                'El servidor devolvió una respuesta no válida. Revisa el registro de PHP.',
                {},
                respuesta.status
            );
        }

        if (!respuesta.ok || !datos.success) {
            if (datos.sesion_expirada && datos.redirect) {
                window.location.href = datos.redirect;
            }
            throw crearError(
                datos.mensaje || 'No fue posible completar la operación.',
                datos,
                respuesta.status
            );
        }

        return datos;
    }

    function crearError(mensaje, datos, estadoHttp) {
        var error = new Error(mensaje);
        error.datos = datos || {};
        error.estadoHttp = estadoHttp || 0;
        return error;
    }

    function confirmar(titulo, texto, textoConfirmar, peligro) {
        return new Promise(function (resolver) {
            if (typeof estado.resolverConfirmacion === 'function') {
                estado.resolverConfirmacion(false);
            }

            estado.resolverConfirmacion = resolver;
            ui.tituloConfirmacion.textContent = titulo || 'Confirmar operación';
            ui.textoConfirmacion.textContent = texto || 'Revisa la operación antes de continuar.';
            ui.btnAceptarConfirmacion.textContent = textoConfirmar || 'Confirmar';
            ui.btnAceptarConfirmacion.className = peligro
                ? 'area-btn area-btn--danger'
                : 'area-btn area-btn--primary';
            ui.modalConfirmacion.classList.toggle('area-modal--danger', Boolean(peligro));
            ui.modalConfirmacion.hidden = false;
            document.body.classList.add('area-modal-open');

            window.setTimeout(function () {
                ui.btnAceptarConfirmacion.focus();
            }, 40);
        });
    }

    function cerrarConfirmacion(resultado) {
        if (ui.modalConfirmacion.hidden) {
            return;
        }

        ui.modalConfirmacion.hidden = true;
        ui.modalConfirmacion.classList.remove('area-modal--danger');

        if (ui.modalArea.hidden) {
            document.body.classList.remove('area-modal-open');
        }

        var resolver = estado.resolverConfirmacion;
        estado.resolverConfirmacion = null;

        if (typeof resolver === 'function') {
            resolver(Boolean(resultado));
        }
    }

    function toast(mensaje, tipo) {
        ui.toast.textContent = mensaje;
        ui.toast.className = 'area-toast area-toast--' + (tipo || 'info');
        ui.toast.hidden = false;
        window.clearTimeout(toast.temporizador);
        toast.temporizador = window.setTimeout(function () {
            ui.toast.hidden = true;
        }, 3800);
    }

    function bloquearBoton(boton, bloquear, texto) {
        if (!boton) {
            return;
        }
        if (bloquear) {
            if (!boton.dataset.textoOriginal) {
                boton.dataset.textoOriginal = boton.innerHTML;
            }
            boton.disabled = true;
            if (texto) {
                boton.textContent = texto;
            }
        } else {
            boton.disabled = false;
            if (boton.dataset.textoOriginal) {
                boton.innerHTML = boton.dataset.textoOriginal;
                delete boton.dataset.textoOriginal;
            }
        }
    }

    function normalizar(valor) {
        return String(valor || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function limpiarEspacios(valor) {
        return String(valor || '').replace(/\s+/g, ' ').trim();
    }

    function inicial(nombre) {
        var limpio = String(nombre || '').trim();
        return limpio === '' ? 'A' : limpio.charAt(0).toUpperCase();
    }

    function existeOpcion(select, valor) {
        return Array.prototype.some.call(select.options, function (opcion) {
            return opcion.value === String(valor);
        });
    }
 
    function horaActual() {
        return new Intl.DateTimeFormat('es-MX', {
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date());
    }

    function escapeHtml(valor) {
        return String(valor == null ? '' : valor)
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