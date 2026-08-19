<?php

declare(strict_types=1);

/*
 * La interfaz consulta esta misma página mediante ?proceso_api=1 para evitar
 * depender de rutas relativas directas hacia la carpeta /funciones.
 */
if (isset($_GET['proceso_api'])) {
    $endpoint = __DIR__ . '/../funciones/procesos_funciones.php';

    if (!is_file($endpoint)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode(
            [
                'success' => false,
                'mensaje' => 'No se encontró funciones/procesos_funciones.php. Copia juntos los tres archivos del módulo en sus carpetas correspondientes.',
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

$cssPath = __DIR__ . '/../css/style_procesos.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '4.0';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b2944">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Gestión segura del catálogo de procesos del Sistema de Mantenimiento.">
    <title>Procesos | Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="../css/style_procesos.css?v=<?= rawurlencode($cssVersion) ?>">
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="proceso-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="proceso-icon-flow" viewBox="0 0 24 24">
        <rect x="3" y="3" width="6" height="6" rx="1.5"/>
        <rect x="15" y="15" width="6" height="6" rx="1.5"/>
        <path d="M9 6h4a5 5 0 0 1 5 5v4M15 18h-4a5 5 0 0 1-5-5V9"/>
    </symbol>
    <symbol id="proceso-icon-plus" viewBox="0 0 24 24">
        <path d="M12 5v14M5 12h14"/>
    </symbol>
    <symbol id="proceso-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="proceso-icon-shield" viewBox="0 0 24 24">
        <path d="M12 2 20 5v6c0 5-3.4 9-8 11-4.6-2-8-6-8-11V5l8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="proceso-icon-list" viewBox="0 0 24 24">
        <path d="M8 6h13M8 12h13M8 18h13"/>
        <path d="M3 6h.01M3 12h.01M3 18h.01"/>
    </symbol>
    <symbol id="proceso-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="proceso-icon-filter" viewBox="0 0 24 24">
        <path d="M3 5h18M6 12h12M10 19h4"/>
    </symbol>
    <symbol id="proceso-icon-check" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8 12 2.6 2.6L16.5 9"/>
    </symbol>
    <symbol id="proceso-icon-inactive" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8.5 8.5 7 7M15.5 8.5l-7 7"/>
    </symbol>
    <symbol id="proceso-icon-link" viewBox="0 0 24 24">
        <path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.2 1.2"/>
        <path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.2-1.2"/>
    </symbol>
    <symbol id="proceso-icon-route" viewBox="0 0 24 24">
        <circle cx="5" cy="6" r="2"/>
        <circle cx="19" cy="18" r="2"/>
        <path d="M7 6h5a4 4 0 0 1 4 4v0a4 4 0 0 1-4 4H8a3 3 0 0 0-3 3v1M17 18h-5"/>
    </symbol>
    <symbol id="proceso-icon-lock" viewBox="0 0 24 24">
        <rect x="4" y="10" width="16" height="11" rx="2"/>
        <path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>
    </symbol>
    <symbol id="proceso-icon-warning" viewBox="0 0 24 24">
        <path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
        <path d="M12 9v4M12 17h.01"/>
    </symbol>
</svg>

<main class="proceso-page">
    <header class="proceso-heading" aria-labelledby="tituloProcesos">
        <div class="proceso-heading__pattern" aria-hidden="true"></div>

        <div class="proceso-heading__content">
            <div class="proceso-heading__copy">
                <p class="proceso-eyebrow">
                    <span class="proceso-eyebrow__icon"><svg><use href="#proceso-icon-flow"></use></svg></span>
                    Catálogo operativo
                </p>
                <h1 id="tituloProcesos">Procesos</h1>
                <p>
                    Organiza las actividades que se realizan dentro de cada área y conserva
                    una ubicación consistente para equipos, solicitudes y rutinas.
                </p>

                <div class="proceso-heading__meta">
                    <span><i class="proceso-live-dot" aria-hidden="true"></i> Catálogo protegido y trazable</span>
                    <span>Administrador: <strong><?= htmlspecialchars($nombreAdmin, ENT_QUOTES, 'UTF-8') ?></strong></span>
                </div>
            </div>

            <div class="proceso-heading__actions" aria-label="Acciones del catálogo de procesos">
                <button type="button" class="proceso-btn proceso-btn--secondary" id="btnActualizar">
                    <svg><use href="#proceso-icon-refresh"></use></svg>
                    <span>Actualizar</span>
                </button>
                <button type="button" class="proceso-btn proceso-btn--primary" id="btnNuevo">
                    <svg><use href="#proceso-icon-plus"></use></svg>
                    <span>Nuevo proceso</span>
                </button>
            </div>

            <div class="proceso-heading__mini-card" aria-hidden="true">
                <span><svg><use href="#proceso-icon-route"></use></svg></span>
                <div>
                    <small>Ruta operativa</small>
                    <strong>Departamento, área y proceso vinculados</strong>
                </div>
            </div>
        </div>
    </header>

    <section class="proceso-security-note" aria-label="Reglas del catálogo">
        <span class="proceso-security-note__icon"><svg><use href="#proceso-icon-shield"></use></svg></span>
        <div>
            <strong>Ubicación e historial protegidos</strong>
            <p>
                Los procesos se desactivan, no se eliminan. Cuando existe información relacionada,
                el área queda bloqueada para preservar correctamente la trazabilidad histórica.
            </p>
        </div>
        <span class="proceso-security-note__badge">Auditoría activa</span>
    </section>

    <div class="proceso-status" id="estadoPagina" role="status" aria-live="polite">
        <span class="proceso-spinner proceso-spinner--small" aria-hidden="true"></span>
        <span>Cargando procesos...</span>
    </div>

    <section class="proceso-kpis" aria-label="Resumen de procesos">
        <article class="proceso-kpi proceso-kpi--total">
            <span class="proceso-kpi__icon"><svg><use href="#proceso-icon-flow"></use></svg></span>
            <span class="proceso-kpi__body">
                <span>Total</span>
                <strong id="kpiTotal">0</strong>
                <small>Procesos registrados</small>
            </span>
        </article>
        <article class="proceso-kpi proceso-kpi--active">
            <span class="proceso-kpi__icon"><svg><use href="#proceso-icon-check"></use></svg></span>
            <span class="proceso-kpi__body">
                <span>Activos</span>
                <strong id="kpiActivos">0</strong>
                <small>Disponibles en formularios</small>
            </span>
        </article>
        <article class="proceso-kpi proceso-kpi--inactive">
            <span class="proceso-kpi__icon"><svg><use href="#proceso-icon-inactive"></use></svg></span>
            <span class="proceso-kpi__body">
                <span>Inactivos</span>
                <strong id="kpiInactivos">0</strong>
                <small>Conservados por historial</small>
            </span>
        </article>
        <article class="proceso-kpi proceso-kpi--use">
            <span class="proceso-kpi__icon"><svg><use href="#proceso-icon-link"></use></svg></span>
            <span class="proceso-kpi__body">
                <span>En uso</span>
                <strong id="kpiEnUso">0</strong>
                <small>Con relaciones activas</small>
            </span>
        </article>
    </section>

    <section class="proceso-card proceso-filters-card" aria-labelledby="tituloFiltrosProcesos">
        <header class="proceso-section-head">
            <div>
                <p class="proceso-eyebrow">Búsqueda y filtros</p>
                <h2 id="tituloFiltrosProcesos">Encuentra un proceso</h2>
                <p>Busca por proceso, área, departamento o descripción y limita los resultados.</p>
            </div>
            <span class="proceso-section-head__chip"><svg><use href="#proceso-icon-filter"></use></svg> Máximo 100 por página</span>
        </header>

        <div class="proceso-filters">
            <label class="proceso-field proceso-field--search" for="filtroBusqueda">
                <span>Buscar</span>
                <div class="proceso-search">
                    <span aria-hidden="true"><svg><use href="#proceso-icon-search"></use></svg></span>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        maxlength="100"
                        placeholder="Proceso, área, departamento o descripción"
                        autocomplete="off"
                    >
                </div>
            </label>

            <label class="proceso-field" for="filtroDepartamento">
                <span>Departamento</span>
                <select id="filtroDepartamento">
                    <option value="TODOS">Todos los departamentos</option>
                </select>
            </label>

            <label class="proceso-field" for="filtroArea">
                <span>Área</span>
                <select id="filtroArea">
                    <option value="TODAS">Todas las áreas</option>
                </select>
            </label>

            <label class="proceso-field" for="filtroEstado">
                <span>Estado</span>
                <select id="filtroEstado">
                    <option value="TODOS">Todos</option>
                    <option value="ACTIVO">Activos</option>
                    <option value="INACTIVO">Inactivos</option>
                    <option value="EN_USO">En uso</option>
                    <option value="SIN_USO">Sin relaciones activas</option>
                </select>
            </label>

            <label class="proceso-field proceso-field--small" for="filtroCantidad">
                <span>Mostrar</span>
                <select id="filtroCantidad">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>

            <div class="proceso-filter-actions">
                <button type="button" class="proceso-btn proceso-btn--ghost" id="btnLimpiar">Limpiar</button>
            </div>
        </div>
    </section>

    <section class="proceso-card proceso-results proceso-results-card" aria-labelledby="tituloListadoProcesos">
        <header class="proceso-results__head">
            <div>
                <p class="proceso-eyebrow">Resultados</p>
                <h2 id="tituloListadoProcesos">Procesos registrados</h2>
                <p id="textoResultados">Preparando resultados...</p>
            </div>

            <div class="proceso-results__tools">
                <span class="proceso-updated" id="ultimaActualizacion">Sin actualizar</span>
                <span class="proceso-results__badge"><svg><use href="#proceso-icon-list"></use></svg> Listado protegido</span>
            </div>
        </header>

        <div class="proceso-loading" id="estadoCarga">
            <span class="proceso-spinner" aria-hidden="true"></span>
            <strong>Cargando procesos...</strong>
        </div>

        <div class="proceso-empty" id="estadoVacio" hidden>
            <span aria-hidden="true"><svg><use href="#proceso-icon-search"></use></svg></span>
            <h3>No hay coincidencias</h3>
            <p>Prueba con otro nombre o cambia los filtros.</p>
        </div>

        <div class="proceso-table-wrap" id="contenedorTabla" hidden tabindex="0" aria-label="Listado desplazable de procesos">
            <table class="proceso-table">
                <thead>
                    <tr>
                        <th>Proceso</th>
                        <th>Ubicación</th>
                        <th>Descripción</th>
                        <th>Uso actual</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaProcesos"></tbody>
            </table>
        </div>

        <footer class="proceso-pagination" id="paginacion" hidden>
            <span id="textoPaginacion">Sin resultados</span>
            <div class="proceso-pagination__buttons">
                <button type="button" id="btnAnterior">Anterior</button>
                <span id="paginaActual">Página 1</span>
                <button type="button" id="btnSiguiente">Siguiente</button>
            </div>
        </footer>
    </section>

    <footer class="proceso-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Catálogo de procesos protegido · Los Chapeteados División Petfood</span>
    </footer>

    <div class="proceso-tools-background" aria-hidden="true"></div>
</main>

<section class="proceso-modal" id="modalProceso" hidden>
    <div class="proceso-modal__dialog proceso-modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="tituloModal">
        <header class="proceso-modal__header">
            <div>
                <p class="proceso-eyebrow" id="etiquetaModal">NUEVO REGISTRO</p>
                <h2 id="tituloModal">Nuevo proceso</h2>
                <p id="subtituloModal">Agrega una actividad dentro de un área.</p>
            </div>
            <button type="button" class="proceso-modal__close" id="btnCerrarModal" aria-label="Cerrar">×</button>
        </header>

        <form id="formProceso" novalidate>
            <input type="hidden" id="procesoId" name="proceso_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="proceso-modal__body">
                <section class="proceso-form-section">
                    <header>
                        <span>01</span>
                        <div>
                            <h3>Ubicación operativa</h3>
                            <p>Selecciona el área y conserva la ruta organizacional del proceso.</p>
                        </div>
                    </header>

                    <div class="proceso-form-grid proceso-form-grid--one">
                        <label class="proceso-form-field" for="areaId">
                            <span>Área *</span>
                            <select id="areaId" name="area_id" required>
                                <option value="">Selecciona un área</option>
                            </select>
                            <small id="ayudaArea">Solo se pueden usar áreas activas de departamentos activos.</small>
                            <em class="proceso-error" id="errorArea"></em>
                        </label>
                    </div>

                    <div class="proceso-form-alert" id="avisoAreaBloqueada" hidden>
                        <span><svg><use href="#proceso-icon-lock"></use></svg></span>
                        <div>
                            <strong>La ubicación está protegida</strong>
                            <p>
                                Este proceso ya tiene información relacionada. Puedes editar su
                                nombre y descripción, pero no cambiarlo de área.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="proceso-form-section">
                    <header>
                        <span>02</span>
                        <div>
                            <h3>Identidad del proceso</h3>
                            <p>Utiliza un nombre claro y una descripción que facilite su identificación.</p>
                        </div>
                    </header>

                    <div class="proceso-form-grid proceso-form-grid--one">
                        <label class="proceso-form-field" for="nombre">
                            <span>Nombre del proceso *</span>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                minlength="2"
                                maxlength="100"
                                placeholder="Ej. Mezclado, horneado o empaque"
                                autocomplete="organization-title"
                                required
                            >
                            <small>
                                Debe ser diferente dentro del área seleccionada.
                                <b id="contadorNombre">0/100</b>
                            </small>
                            <em class="proceso-error" id="errorNombre"></em>
                        </label>

                        <label class="proceso-form-field" for="descripcion">
                            <span>Descripción</span>
                            <textarea
                                id="descripcion"
                                name="descripcion"
                                rows="5"
                                maxlength="500"
                                placeholder="Describe brevemente en qué consiste este proceso."
                            ></textarea>
                            <small>
                                Campo opcional.
                                <b id="contadorDescripcion">0/500</b>
                            </small>
                            <em class="proceso-error" id="errorDescripcion"></em>
                        </label>
                    </div>
                </section>
            </div>

            <footer class="proceso-modal__footer">
                <button type="button" class="proceso-btn proceso-btn--ghost" id="btnCancelar">Cancelar</button>
                <button type="submit" class="proceso-btn proceso-btn--primary" id="btnGuardar">Guardar proceso</button>
            </footer>
        </form>
    </div>
</section>

<section class="proceso-modal proceso-confirmation-modal" id="modalConfirmacion" hidden>
    <div class="proceso-modal__dialog proceso-modal__dialog--small" role="alertdialog" aria-modal="true" aria-labelledby="tituloConfirmacion" aria-describedby="textoConfirmacion">
        <header class="proceso-modal__header">
            <div>
                <p class="proceso-eyebrow">CONFIRMACIÓN SEGURA</p>
                <h2 id="tituloConfirmacion">Confirmar operación</h2>
                <p>La acción quedará registrada en la auditoría del sistema.</p>
            </div>
        </header>

        <div class="proceso-confirmation">
            <span class="proceso-confirmation__icon"><svg><use href="#proceso-icon-warning"></use></svg></span>
            <p id="textoConfirmacion">Revisa la operación antes de continuar.</p>
        </div>

        <footer class="proceso-modal__footer proceso-modal__footer--alone">
            <button type="button" class="proceso-btn proceso-btn--ghost" id="btnCancelarConfirmacion">Cancelar</button>
            <button type="button" class="proceso-btn proceso-btn--primary" id="btnAceptarConfirmacion">Confirmar</button>
        </footer>
    </div>
</section>

<div class="proceso-toast" id="toast" role="status" aria-live="polite" aria-atomic="true" hidden></div>

<?php include __DIR__ . '/../inc/alertas.php'; ?>
<script>
(function () {
    'use strict';

    var ENDPOINT = window.location.pathname + '?proceso_api=1';
    var CSRF_TOKEN = <?= json_encode(
        $csrfToken,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;
    var estado = {
        procesos: [],
        areas: [],
        filtrados: [],
        pagina: 1,
        cantidad: 10,
        cargando: false,
        guardando: false,
        areaBloqueada: false,
        areaActualId: 0,
        resolverConfirmacion: null
    };
    var ui = {};

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        capturarElementos();
        registrarEventos();
        actualizarContadores();
        cargarProcesos(false);
    }

    function capturarElementos() {
        [
            'btnNuevo', 'btnActualizar', 'btnLimpiar', 'estadoPagina',
            'kpiTotal', 'kpiActivos', 'kpiInactivos', 'kpiEnUso',
            'filtroBusqueda', 'filtroDepartamento', 'filtroArea',
            'filtroEstado', 'filtroCantidad', 'textoResultados',
            'ultimaActualizacion', 'estadoCarga', 'estadoVacio',
            'contenedorTabla', 'tablaProcesos', 'paginacion',
            'textoPaginacion', 'btnAnterior', 'btnSiguiente',
            'paginaActual', 'modalProceso', 'btnCerrarModal', 'btnCancelar',
            'formProceso', 'procesoId', 'areaId', 'nombre', 'descripcion',
            'contadorNombre', 'contadorDescripcion', 'errorArea',
            'errorNombre', 'errorDescripcion', 'avisoAreaBloqueada',
            'ayudaArea', 'etiquetaModal', 'tituloModal', 'subtituloModal',
            'btnGuardar', 'modalConfirmacion', 'tituloConfirmacion',
            'textoConfirmacion', 'btnCancelarConfirmacion',
            'btnAceptarConfirmacion', 'toast'
        ].forEach(function (id) {
            ui[id] = document.getElementById(id);
        });
    }

    function registrarEventos() {
        ui.btnNuevo.addEventListener('click', abrirNuevo);
        ui.btnActualizar.addEventListener('click', function () {
            cargarProcesos(true);
        });
        ui.btnLimpiar.addEventListener('click', limpiarFiltros);
        ui.filtroBusqueda.addEventListener('input', aplicarFiltros);
        ui.filtroDepartamento.addEventListener('change', function () {
            llenarFiltroAreas();
            aplicarFiltros();
        });
        ui.filtroArea.addEventListener('change', aplicarFiltros);
        ui.filtroEstado.addEventListener('change', aplicarFiltros);
        ui.filtroCantidad.addEventListener('change', function () {
            estado.cantidad = cantidadPermitida(ui.filtroCantidad.value);
            ui.filtroCantidad.value = String(estado.cantidad);
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
        ui.btnCerrarModal.addEventListener('click', function () {
            cerrarModal(false);
        });
        ui.btnCancelar.addEventListener('click', function () {
            cerrarModal(false);
        });
        ui.modalProceso.addEventListener('click', function (evento) {
            if (evento.target === ui.modalProceso) {
                cerrarModal(false);
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
            if (!ui.modalProceso.hidden) {
                cerrarModal(false);
            }
        });
        ui.formProceso.addEventListener('submit', guardarProceso);
        ui.areaId.addEventListener('change', function () {
            ui.errorArea.textContent = '';
        });
        ui.nombre.addEventListener('input', function () {
            ui.errorNombre.textContent = '';
            actualizarContadores();
        });
        ui.descripcion.addEventListener('input', function () {
            ui.errorDescripcion.textContent = '';
            actualizarContadores();
        });
        ui.tablaProcesos.addEventListener('click', manejarAccionTabla);
    }

    async function cargarProcesos(mostrarConfirmacion) {
        if (estado.cargando) {
            return;
        }

        estado.cargando = true;
        bloquearBoton(ui.btnActualizar, true, 'Actualizando...');
        mostrarCarga(true);
        mostrarEstado('Consultando procesos...', 'info');

        try {
            var respuesta = await solicitar(
                ENDPOINT + '&accion=INICIAL&t=' + Date.now()
            );
            estado.procesos = Array.isArray(respuesta.procesos)
                ? respuesta.procesos
                : [];
            estado.areas = Array.isArray(respuesta.areas) ? respuesta.areas : [];
            llenarFiltroDepartamentos();
            llenarFiltroAreas();
            pintarResumen(respuesta.resumen || {});
            aplicarFiltros();
            ui.ultimaActualizacion.textContent = 'Actualizado ' + horaActual();
            mostrarEstado('Información actualizada correctamente.', 'success');

            if (mostrarConfirmacion) {
                toast('Lista actualizada.', 'success');
            }
        } catch (error) {
            mostrarEstado(error.message || 'No fue posible cargar los procesos.', 'error');
            estado.procesos = [];
            estado.areas = [];
            llenarFiltroDepartamentos();
            llenarFiltroAreas();
            aplicarFiltros();
        } finally {
            estado.cargando = false;
            bloquearBoton(ui.btnActualizar, false);
            mostrarCarga(false);
        }
    }

    function departamentosCatalogo() {
        var vistos = {};
        var departamentos = [];

        estado.areas.forEach(function (area) {
            var id = Number(area.departamento_id);
            if (id > 0 && !vistos[id]) {
                vistos[id] = true;
                departamentos.push({
                    id: id,
                    nombre: area.departamento || 'Departamento no disponible',
                    activo: Number(area.departamento_activo) === 1 ? 1 : 0
                });
            }
        });

        departamentos.sort(function (a, b) {
            return String(a.nombre).localeCompare(String(b.nombre), 'es');
        });
        return departamentos;
    }

    function llenarFiltroDepartamentos() {
        var valorActual = ui.filtroDepartamento.value || 'TODOS';
        var opciones = ['<option value="TODOS">Todos los departamentos</option>'];

        departamentosCatalogo().forEach(function (departamento) {
            opciones.push(
                '<option value="' + departamento.id + '">' +
                    escapeHtml(departamento.nombre) +
                    (departamento.activo === 1 ? '' : ' · Inactivo') +
                '</option>'
            );
        });

        ui.filtroDepartamento.innerHTML = opciones.join('');
        ui.filtroDepartamento.value = existeOpcion(
            ui.filtroDepartamento,
            valorActual
        ) ? valorActual : 'TODOS';
    }

    function llenarFiltroAreas() {
        var valorActual = ui.filtroArea.value || 'TODAS';
        var departamento = ui.filtroDepartamento.value;
        var opciones = ['<option value="TODAS">Todas las áreas</option>'];

        estado.areas.forEach(function (area) {
            if (
                departamento !== 'TODOS'
                && Number(area.departamento_id) !== Number(departamento)
            ) {
                return;
            }
            opciones.push(
                '<option value="' + Number(area.id) + '">' +
                    escapeHtml(area.nombre || '') +
                    (Number(area.activo) === 1 ? '' : ' · Inactiva') +
                '</option>'
            );
        });

        ui.filtroArea.innerHTML = opciones.join('');
        ui.filtroArea.value = existeOpcion(ui.filtroArea, valorActual)
            ? valorActual
            : 'TODAS';
    }

    function aplicarFiltros() {
        var busqueda = normalizar(ui.filtroBusqueda.value);
        var filtroDepartamento = ui.filtroDepartamento.value;
        var filtroArea = ui.filtroArea.value;
        var filtroEstado = ui.filtroEstado.value;

        estado.filtrados = estado.procesos.filter(function (proceso) {
            var coincideTexto = busqueda === '' || normalizar(
                String(proceso.nombre || '') + ' ' +
                String(proceso.area || '') + ' ' +
                String(proceso.departamento || '') + ' ' +
                String(proceso.descripcion || '')
            ).indexOf(busqueda) !== -1;

            var coincideDepartamento = filtroDepartamento === 'TODOS'
                || Number(proceso.departamento_id) === Number(filtroDepartamento);
            var coincideArea = filtroArea === 'TODAS'
                || Number(proceso.area_id) === Number(filtroArea);
            var activo = Number(proceso.activo) === 1;
            var enUso = Number(proceso.total_relaciones_activas) > 0;
            var coincideEstado = filtroEstado === 'TODOS'
                || (filtroEstado === 'ACTIVO' && activo)
                || (filtroEstado === 'INACTIVO' && !activo)
                || (filtroEstado === 'EN_USO' && enUso)
                || (filtroEstado === 'SIN_USO' && !enUso);

            return coincideTexto && coincideDepartamento && coincideArea && coincideEstado;
        });

        estado.pagina = 1;
        renderizar();
    }

    function renderizar() {
        var total = estado.filtrados.length;
        var paginas = totalPaginas();

        if (estado.pagina > paginas) {
            estado.pagina = paginas;
        }

        var inicio = (estado.pagina - 1) * estado.cantidad;
        var fin = Math.min(inicio + estado.cantidad, total);
        var visibles = estado.filtrados.slice(inicio, fin);

        ui.tablaProcesos.innerHTML = visibles.map(crearFila).join('');
        ui.textoResultados.textContent = total === 1
            ? '1 proceso coincide con los filtros.'
            : total + ' procesos coinciden con los filtros.';

        ui.estadoVacio.hidden = total !== 0;
        ui.contenedorTabla.hidden = total === 0;
        ui.paginacion.hidden = total === 0 || paginas <= 1;
        ui.textoPaginacion.textContent = total === 0
            ? 'Sin resultados'
            : 'Mostrando ' + (inicio + 1) + ' a ' + fin + ' de ' + total;
        ui.paginaActual.textContent = 'Página ' + estado.pagina + ' de ' + paginas;
        ui.btnAnterior.disabled = estado.pagina <= 1;
        ui.btnSiguiente.disabled = estado.pagina >= paginas;
    }

    function crearFila(proceso) {
        var id = Number(proceso.id);
        var activo = Number(proceso.activo) === 1;
        var relaciones = Number(proceso.total_relaciones_activas) || 0;
        var padreDisponible = Number(proceso.area_activa) === 1
            && Number(proceso.departamento_activo) === 1;

        return '<tr>' +
            '<td><div class="proceso-identity">' +
                '<span aria-hidden="true">' + escapeHtml(inicial(proceso.nombre)) + '</span>' +
                '<div><strong>' + escapeHtml(proceso.nombre || '') + '</strong>' +
                '<small>ID ' + id + ' · Registrado ' + escapeHtml(proceso.fecha_registro_texto || '—') + '</small></div>' +
            '</div></td>' +
            '<td><div class="proceso-location">' +
                '<strong>' + escapeHtml(proceso.area || 'Área no disponible') + '</strong>' +
                '<small>' + escapeHtml(proceso.departamento || 'Departamento no disponible') + '</small>' +
                (padreDisponible ? '' : '<span>Ubicación inactiva</span>') +
            '</div></td>' +
            '<td><div class="proceso-description" title="' +
                escapeHtml(proceso.descripcion || 'Sin descripción') + '">' +
                escapeHtml(proceso.descripcion || 'Sin descripción') +
            '</div></td>' +
            '<td>' + crearUso(proceso) + '</td>' +
            '<td><span class="proceso-badge ' +
                (activo ? 'proceso-badge--active' : 'proceso-badge--inactive') + '">' +
                (activo ? 'Activo' : 'Inactivo') +
            '</span></td>' +
            '<td><div class="proceso-actions">' +
                '<button type="button" class="proceso-action" data-action="editar" data-id="' + id + '">Editar</button>' +
                '<button type="button" class="proceso-action ' +
                    (activo ? 'proceso-action--danger' : 'proceso-action--success') + '" ' +
                    'data-action="estado" data-id="' + id + '" data-activo="' +
                    (activo ? '0' : '1') + '" ' +
                    (activo && relaciones > 0 ? 'disabled title="Tiene relaciones activas"' : '') + '>' +
                    (activo ? 'Desactivar' : 'Reactivar') +
                '</button>' +
            '</div></td>' +
        '</tr>';
    }

    function crearUso(proceso) {
        var usos = [
            ['Equipos', Number(proceso.equipos_activos) || 0],
            ['Solicitudes', Number(proceso.solicitudes_abiertas) || 0],
            ['Rutinas', Number(proceso.rutinas_activas) || 0]
        ].filter(function (item) {
            return item[1] > 0;
        });

        if (usos.length === 0) {
            return '<span class="proceso-no-use">Sin relaciones activas</span>';
        }

        return '<div class="proceso-use-list">' + usos.map(function (item) {
            return '<span>' + escapeHtml(item[0]) + ' <b>' + item[1] + '</b></span>';
        }).join('') + '</div>';
    }

    function pintarResumen(resumen) {
        ui.kpiTotal.textContent = numero(resumen.total);
        ui.kpiActivos.textContent = numero(resumen.activos);
        ui.kpiInactivos.textContent = numero(resumen.inactivos);
        ui.kpiEnUso.textContent = numero(resumen.en_uso);
    }

    function limpiarFiltros() {
        ui.filtroBusqueda.value = '';
        ui.filtroDepartamento.value = 'TODOS';
        llenarFiltroAreas();
        ui.filtroArea.value = 'TODAS';
        ui.filtroEstado.value = 'TODOS';
        ui.filtroCantidad.value = '10';
        estado.cantidad = 10;
        aplicarFiltros();
    }

    function abrirNuevo() {
        var disponibles = estado.areas.filter(function (area) {
            return Number(area.activo) === 1
                && Number(area.departamento_activo) === 1;
        });

        if (disponibles.length === 0) {
            toast('Primero registra o reactiva un área y su departamento.', 'error');
            return;
        }

        limpiarFormulario();
        llenarSelectAreas(0);
        ui.etiquetaModal.textContent = 'NUEVO REGISTRO';
        ui.tituloModal.textContent = 'Nuevo proceso';
        ui.subtituloModal.textContent = 'Agrega una actividad dentro de un área.';
        ui.btnGuardar.textContent = 'Guardar proceso';
        abrirModal();
    }

    async function abrirEditar(id, boton) {
        bloquearBoton(boton, true, 'Cargando...');

        try {
            var respuesta = await solicitar(
                ENDPOINT + '&accion=DETALLE&id=' + encodeURIComponent(id)
            );
            var proceso = respuesta.proceso || {};
            limpiarFormulario();
            estado.areaActualId = Number(proceso.area_id) || 0;
            estado.areaBloqueada = Number(proceso.puede_cambiar_area) !== 1;
            llenarSelectAreas(estado.areaActualId);

            ui.procesoId.value = Number(proceso.id) || '';
            ui.areaId.value = String(estado.areaActualId);
            ui.areaId.disabled = estado.areaBloqueada;
            ui.nombre.value = proceso.nombre || '';
            ui.descripcion.value = proceso.descripcion || '';
            ui.avisoAreaBloqueada.hidden = !estado.areaBloqueada;
            ui.ayudaArea.textContent = estado.areaBloqueada
                ? 'El área se conserva para proteger la ubicación histórica.'
                : 'Puedes cambiar el área mientras el proceso no tenga relaciones.';
            ui.etiquetaModal.textContent = 'EDITAR REGISTRO';
            ui.tituloModal.textContent = 'Editar proceso';
            ui.subtituloModal.textContent = 'Actualiza la información permitida.';
            ui.btnGuardar.textContent = 'Actualizar proceso';
            actualizarContadores();
            abrirModal();
        } catch (error) {
            toast(error.message || 'No fue posible abrir el proceso.', 'error');
        } finally {
            bloquearBoton(boton, false);
        }
    }

    function llenarSelectAreas(seleccionActual) {
        var grupos = {};
        estado.areas.forEach(function (area) {
            var departamento = area.departamento || 'Departamento no disponible';
            if (!grupos[departamento]) {
                grupos[departamento] = [];
            }
            grupos[departamento].push(area);
        });

        var html = ['<option value="">Selecciona un área</option>'];
        Object.keys(grupos).sort(function (a, b) {
            return a.localeCompare(b, 'es');
        }).forEach(function (departamento) {
            html.push('<optgroup label="' + escapeHtml(departamento) + '">');
            grupos[departamento].forEach(function (area) {
                var id = Number(area.id);
                var disponible = Number(area.activo) === 1
                    && Number(area.departamento_activo) === 1;
                var esActual = id === Number(seleccionActual);
                html.push(
                    '<option value="' + id + '" ' +
                    (!disponible && !esActual ? 'disabled ' : '') +
                    (esActual ? 'selected ' : '') + '>' +
                    escapeHtml(area.nombre || '') +
                    (disponible ? '' : ' · Inactiva') +
                    '</option>'
                );
            });
            html.push('</optgroup>');
        });

        ui.areaId.innerHTML = html.join('');
    }

    async function guardarProceso(evento) {
        evento.preventDefault();
        if (estado.guardando) {
            return;
        }

        limpiarErrores();
        var valido = validarFormulario();
        if (!valido) {
            return;
        }

        estado.guardando = true;
        bloquearBoton(ui.btnGuardar, true, 'Guardando...');

        try {
            var datos = new FormData(ui.formProceso);
            datos.set('accion', 'GUARDAR');
            datos.set('csrf_token', CSRF_TOKEN);
            if (ui.areaId.disabled) {
                datos.set('area_id', String(estado.areaActualId));
            }

            var respuesta = await solicitar(ENDPOINT, {
                method: 'POST',
                body: datos
            });
            cerrarModal(true);
            await cargarProcesos(false);
            toast(respuesta.mensaje || 'Proceso guardado correctamente.', 'success');
        } catch (error) {
            marcarCampo(error.datos && error.datos.campo);
            toast(error.message || 'No fue posible guardar el proceso.', 'error');
        } finally {
            estado.guardando = false;
            bloquearBoton(ui.btnGuardar, false);
        }
    }

    async function cambiarEstado(id, nuevoEstado, boton) {
        var proceso = estado.procesos.find(function (item) {
            return Number(item.id) === Number(id);
        });
        if (!proceso) {
            toast('El registro ya no está disponible.', 'error');
            return;
        }

        var reactivar = Number(nuevoEstado) === 1;
        var confirmado = await confirmar(
            reactivar ? '¿Reactivar proceso?' : '¿Desactivar proceso?',
            reactivar
                ? 'El proceso volverá a estar disponible en formularios.'
                : 'El proceso dejará de estar disponible para nuevos registros.',
            reactivar ? 'Sí, reactivar' : 'Sí, desactivar',
            !reactivar
        );

        if (!confirmado) {
            return;
        }

        bloquearBoton(boton, true, 'Procesando...');
        try {
            var datos = new FormData();
            datos.set('accion', 'CAMBIAR_ESTADO');
            datos.set('id', String(id));
            datos.set('activo', String(nuevoEstado));
            datos.set('csrf_token', CSRF_TOKEN);

            var respuesta = await solicitar(ENDPOINT, {
                method: 'POST',
                body: datos
            });
            await cargarProcesos(false);
            toast(respuesta.mensaje || 'Estado actualizado.', 'success');
        } catch (error) {
            toast(error.message || 'No fue posible cambiar el estado.', 'error');
        } finally {
            bloquearBoton(boton, false);
        }
    }

    function manejarAccionTabla(evento) {
        var boton = evento.target.closest('[data-action]');
        if (!boton || boton.disabled) {
            return;
        }

        var id = Number(boton.dataset.id);
        if (!Number.isInteger(id) || id <= 0) {
            toast('No se pudo identificar el proceso.', 'error');
            return;
        }

        if (boton.dataset.action === 'editar') {
            abrirEditar(id, boton);
        } else if (boton.dataset.action === 'estado') {
            cambiarEstado(id, Number(boton.dataset.activo), boton);
        }
    }

    function validarFormulario() {
        var valido = true;
        var area = Number(ui.areaId.disabled ? estado.areaActualId : ui.areaId.value);
        var nombre = String(ui.nombre.value || '').trim().replace(/\s+/g, ' ');
        var descripcion = String(ui.descripcion.value || '').trim();

        if (!Number.isInteger(area) || area <= 0) {
            ui.errorArea.textContent = 'Selecciona un área válida.';
            valido = false;
        }
        if (nombre.length < 2 || nombre.length > 100) {
            ui.errorNombre.textContent = 'Escribe entre 2 y 100 caracteres.';
            valido = false;
        }
        if (descripcion.length > 500) {
            ui.errorDescripcion.textContent = 'La descripción supera 500 caracteres.';
            valido = false;
        }
        return valido;
    }

    function marcarCampo(campo) {
        if (campo === 'area_id') {
            ui.errorArea.textContent = 'Revisa el área seleccionada.';
            ui.areaId.focus();
        } else if (campo === 'nombre') {
            ui.errorNombre.textContent = 'Revisa el nombre del proceso.';
            ui.nombre.focus();
        } else if (campo === 'descripcion') {
            ui.errorDescripcion.textContent = 'Revisa la descripción.';
            ui.descripcion.focus();
        }
    }

    function limpiarFormulario() {
        ui.formProceso.reset();
        ui.procesoId.value = '';
        ui.areaId.disabled = false;
        ui.areaId.innerHTML = '<option value="">Selecciona un área</option>';
        ui.avisoAreaBloqueada.hidden = true;
        ui.ayudaArea.textContent = 'Solo se pueden usar áreas activas de departamentos activos.';
        estado.areaBloqueada = false;
        estado.areaActualId = 0;
        limpiarErrores();
        actualizarContadores();
    }

    function limpiarErrores() {
        ui.errorArea.textContent = '';
        ui.errorNombre.textContent = '';
        ui.errorDescripcion.textContent = '';
    }

    function abrirModal() {
        ui.modalProceso.hidden = false;
        document.body.classList.add('proceso-modal-open');
        window.setTimeout(function () {
            (ui.areaId.disabled ? ui.nombre : ui.areaId).focus();
        }, 50);
    }

    function cerrarModal(forzar) {
        if (estado.guardando && forzar !== true) {
            return;
        }
        ui.modalProceso.hidden = true;
        if (ui.modalConfirmacion.hidden) {
            document.body.classList.remove('proceso-modal-open');
        }
        limpiarFormulario();
    }

    function actualizarContadores() {
        ui.contadorNombre.textContent = String(ui.nombre.value || '').length + '/100';
        ui.contadorDescripcion.textContent = String(ui.descripcion.value || '').length + '/500';
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
        ui.estadoPagina.className = 'proceso-status proceso-status--' + (tipo || 'info');
    }

    function totalPaginas() {
        return Math.max(1, Math.ceil(estado.filtrados.length / estado.cantidad));
    }

    async function solicitar(url, opciones) {
        var respuesta;
        try {
            respuesta = await fetch(url, Object.assign({
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }, opciones || {}));
        } catch (error) {
            throw crearError('No se pudo conectar con el servidor.', {}, 0);
        }

        var texto = await respuesta.text();
        var datos;
        try {
            datos = JSON.parse(texto);
        } catch (error) {
            throw crearError(
                respuesta.status === 404
                    ? 'No se encontró el servicio de Procesos.'
                    : 'El servidor devolvió una respuesta no válida.',
                { respuesta: texto.substring(0, 300) },
                respuesta.status
            );
        }

        if (!respuesta.ok || datos.success === false) {
            if (datos.sesion_expirada || (datos.datos && datos.datos.sesion_expirada)) {
                window.location.href = datos.redirect
                    || (datos.datos && datos.datos.redirect)
                    || '../login.php?sesion=expirada';
            }
            throw crearError(
                datos.mensaje || 'No fue posible completar la operación.',
                datos.datos || datos,
                respuesta.status
            );
        }

        return datos.datos && typeof datos.datos === 'object'
            ? Object.assign({ mensaje: datos.mensaje }, datos.datos)
            : datos;
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
            ui.textoConfirmacion.textContent = texto
                || 'Revisa la operación antes de continuar.';
            ui.btnAceptarConfirmacion.textContent = textoConfirmar || 'Confirmar';
            ui.btnAceptarConfirmacion.className = peligro
                ? 'proceso-btn proceso-btn--danger'
                : 'proceso-btn proceso-btn--primary';
            ui.modalConfirmacion.classList.toggle(
                'proceso-modal--danger',
                Boolean(peligro)
            );
            ui.modalConfirmacion.hidden = false;
            document.body.classList.add('proceso-modal-open');

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
        ui.modalConfirmacion.classList.remove('proceso-modal--danger');

        if (ui.modalProceso.hidden) {
            document.body.classList.remove('proceso-modal-open');
        }

        var resolver = estado.resolverConfirmacion;
        estado.resolverConfirmacion = null;

        if (typeof resolver === 'function') {
            resolver(Boolean(resultado));
        }
    }

    function cantidadPermitida(valor) {
        var cantidad = Number(valor);
        return [10, 25, 50, 100].indexOf(cantidad) !== -1 ? cantidad : 10;
    }

    function toast(mensaje, tipo) {
        ui.toast.textContent = mensaje;
        ui.toast.className = 'proceso-toast proceso-toast--' + (tipo || 'info');
        ui.toast.hidden = false;
        window.clearTimeout(toast.temporizador);
        toast.temporizador = window.setTimeout(function () {
            ui.toast.hidden = true;
        }, 4200);
    }

    function bloquearBoton(boton, bloquear, texto) {
        if (!boton) {
            return;
        }
        if (bloquear) {
            boton.dataset.textoOriginal = boton.textContent;
            boton.disabled = true;
            if (texto) {
                boton.textContent = texto;
            }
        } else {
            boton.disabled = false;
            if (boton.dataset.textoOriginal) {
                boton.textContent = boton.dataset.textoOriginal;
                delete boton.dataset.textoOriginal;
            }
        }
    }

    function normalizar(valor) {
        return String(valor || '')
            .toLocaleLowerCase('es')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function inicial(nombre) {
        var limpio = String(nombre || '').trim();
        return limpio === '' ? 'P' : limpio.charAt(0).toUpperCase();
    }

    function numero(valor) {  
        var resultado = Number(valor);
        return Number.isFinite(resultado) && resultado >= 0 ? resultado : 0;
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