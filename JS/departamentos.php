<?php

declare(strict_types=1);

/*
 * La interfaz consulta esta misma página mediante ?dep_api=1. De esta forma
 * el navegador no depende de rutas relativas hacia la carpeta funciones.
 */
if (isset($_GET['dep_api'])) {
    $endpoint = __DIR__ . '/../funciones/departamentos_funciones.php';

    if (!is_file($endpoint)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode(
            [
                'success' => false,
                'mensaje' => 'No se encontró funciones/departamentos_funciones.php. Copia juntos los tres archivos del módulo en sus carpetas correspondientes.',
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

$cssPath = __DIR__ . '/../css/style_departamentos.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '3.0';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b2944">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Gestión segura del catálogo de departamentos del Sistema de Mantenimiento.">
    <title>Departamentos | Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="../css/style_departamentos.css?v=<?= rawurlencode($cssVersion) ?>">
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="dep-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="dep-icon-building" viewBox="0 0 24 24">
        <path d="M4 21V6l8-3 8 3v15M8 9h.01M12 9h.01M16 9h.01M8 13h.01M12 13h.01M16 13h.01M9 21v-4h6v4"/>
    </symbol>
    <symbol id="dep-icon-plus" viewBox="0 0 24 24">
        <path d="M12 5v14M5 12h14"/>
    </symbol>
    <symbol id="dep-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="dep-icon-shield" viewBox="0 0 24 24">
        <path d="M12 2 20 5v6c0 5-3.4 9-8 11-4.6-2-8-6-8-11V5l8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="dep-icon-list" viewBox="0 0 24 24">
        <path d="M8 6h13M8 12h13M8 18h13"/>
        <path d="M3 6h.01M3 12h.01M3 18h.01"/>
    </symbol>
    <symbol id="dep-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="dep-icon-filter" viewBox="0 0 24 24">
        <path d="M3 5h18M6 12h12M10 19h4"/>
    </symbol>
    <symbol id="dep-icon-check" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8 12 2.6 2.6L16.5 9"/>
    </symbol>
    <symbol id="dep-icon-inactive" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8.5 8.5 7 7M15.5 8.5l-7 7"/>
    </symbol>
    <symbol id="dep-icon-link" viewBox="0 0 24 24">
        <path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.2 1.2"/>
        <path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.2-1.2"/>
    </symbol>
    <symbol id="dep-icon-hierarchy" viewBox="0 0 24 24">
        <rect x="9" y="3" width="6" height="5" rx="1"/>
        <rect x="3" y="16" width="6" height="5" rx="1"/>
        <rect x="15" y="16" width="6" height="5" rx="1"/>
        <path d="M12 8v4M6 16v-4h12v4"/>
    </symbol>
    <symbol id="dep-icon-warning" viewBox="0 0 24 24">
        <path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
        <path d="M12 9v4M12 17h.01"/>
    </symbol>
</svg>

<main class="dep-page">
    <header class="dep-heading" aria-labelledby="tituloDepartamentos">
        <div class="dep-heading__pattern" aria-hidden="true"></div>

        <div class="dep-heading__content">
            <div class="dep-heading__copy">
                <p class="dep-eyebrow">
                    <span class="dep-eyebrow__icon"><svg><use href="#dep-icon-building"></use></svg></span>
                    Catálogo organizacional
                </p>
                <h1 id="tituloDepartamentos">Departamentos</h1>
                <p>
                    Organiza las divisiones principales de la empresa y conserva una estructura
                    confiable para áreas, personal, equipos, solicitudes y rutinas de mantenimiento.
                </p>

                <div class="dep-heading__meta">
                    <span><i class="dep-live-dot" aria-hidden="true"></i> Catálogo protegido y trazable</span>
                    <span>Administrador: <strong><?= htmlspecialchars($nombreAdmin, ENT_QUOTES, 'UTF-8') ?></strong></span>
                </div>
            </div>

            <div class="dep-heading__actions" aria-label="Acciones del catálogo de departamentos">
                <button type="button" class="dep-btn dep-btn--secondary" id="btnActualizar">
                    <svg><use href="#dep-icon-refresh"></use></svg>
                    <span>Actualizar</span>
                </button>
                <button type="button" class="dep-btn dep-btn--primary" id="btnNuevo">
                    <svg><use href="#dep-icon-plus"></use></svg>
                    <span>Nuevo departamento</span>
                </button>
            </div>

            <div class="dep-heading__mini-card" aria-hidden="true">
                <span><svg><use href="#dep-icon-hierarchy"></use></svg></span>
                <div>
                    <small>Estructura principal</small>
                    <strong>Departamentos y relaciones vinculadas</strong>
                </div>
            </div>
        </div>
    </header>

    <section class="dep-security-note" aria-label="Reglas del catálogo">
        <span class="dep-security-note__icon"><svg><use href="#dep-icon-shield"></use></svg></span>
        <div>
            <strong>Historial y relaciones protegidos</strong>
            <p>
                Los departamentos se desactivan, no se eliminan. Cuando conservan áreas, personal,
                equipos, solicitudes o rutinas activas, el sistema impide su desactivación.
            </p>
        </div>
        <span class="dep-security-note__badge">Auditoría activa</span>
    </section>

    <div class="dep-status" id="estadoPagina" role="status" aria-live="polite">
        <span class="dep-spinner dep-spinner--small" aria-hidden="true"></span>
        <span>Cargando departamentos...</span>
    </div>

    <section class="dep-kpis" aria-label="Resumen de departamentos">
        <article class="dep-kpi dep-kpi--total">
            <span class="dep-kpi__icon"><svg><use href="#dep-icon-building"></use></svg></span>
            <span class="dep-kpi__body">
                <span>Total</span>
                <strong id="kpiTotal">0</strong>
                <small>Departamentos registrados</small>
            </span>
        </article>
        <article class="dep-kpi dep-kpi--active">
            <span class="dep-kpi__icon"><svg><use href="#dep-icon-check"></use></svg></span>
            <span class="dep-kpi__body">
                <span>Activos</span>
                <strong id="kpiActivos">0</strong>
                <small>Disponibles en formularios</small>
            </span>
        </article>
        <article class="dep-kpi dep-kpi--inactive">
            <span class="dep-kpi__icon"><svg><use href="#dep-icon-inactive"></use></svg></span>
            <span class="dep-kpi__body">
                <span>Inactivos</span>
                <strong id="kpiInactivos">0</strong>
                <small>Conservados por historial</small>
            </span>
        </article>
        <article class="dep-kpi dep-kpi--use">
            <span class="dep-kpi__icon"><svg><use href="#dep-icon-link"></use></svg></span>
            <span class="dep-kpi__body">
                <span>En uso</span>
                <strong id="kpiEnUso">0</strong>
                <small>Con relaciones activas</small>
            </span>
        </article>
    </section>

    <section class="dep-card dep-filters-card" aria-labelledby="tituloFiltrosDepartamentos">
        <header class="dep-section-head">
            <div>
                <p class="dep-eyebrow">Búsqueda y filtros</p>
                <h2 id="tituloFiltrosDepartamentos">Encuentra un departamento</h2>
                <p>Busca por nombre o descripción y limita el catálogo según su estado operativo.</p>
            </div>
            <span class="dep-section-head__chip"><svg><use href="#dep-icon-filter"></use></svg> Consulta local</span>
        </header>

        <div class="dep-filters">
            <label class="dep-field dep-field--search" for="filtroBusqueda">
                <span>Buscar</span>
                <div class="dep-search">
                    <span aria-hidden="true"><svg><use href="#dep-icon-search"></use></svg></span>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        maxlength="100"
                        placeholder="Nombre o descripción"
                        autocomplete="off"
                    >
                </div>
            </label>

            <label class="dep-field" for="filtroEstado">
                <span>Estado</span>
                <select id="filtroEstado">
                    <option value="TODOS">Todos</option>
                    <option value="ACTIVO">Activos</option>
                    <option value="INACTIVO">Inactivos</option>
                    <option value="EN_USO">En uso</option>
                    <option value="SIN_USO">Sin relaciones activas</option>
                </select>
            </label>

            <label class="dep-field dep-field--small" for="filtroCantidad">
                <span>Mostrar</span>
                <select id="filtroCantidad">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>

            <div class="dep-filter-actions">
                <button type="button" class="dep-btn dep-btn--ghost" id="btnLimpiar">Limpiar</button>
            </div>
        </div>
    </section>

    <section class="dep-card dep-results dep-results-card" aria-labelledby="tituloListadoDepartamentos">
        <header class="dep-results__head">
            <div>
                <p class="dep-eyebrow">Resultados</p>
                <h2 id="tituloListadoDepartamentos">Departamentos registrados</h2>
                <p id="textoResultados">Preparando resultados...</p>
            </div>

            <div class="dep-results__tools">
                <span class="dep-updated" id="ultimaActualizacion">Sin actualizar</span>
                <span class="dep-results__badge"><svg><use href="#dep-icon-list"></use></svg> Listado protegido</span>
            </div>
        </header>

        <div class="dep-loading" id="estadoCarga">
            <span class="dep-spinner" aria-hidden="true"></span>
            <strong>Cargando departamentos...</strong>
        </div>

        <div class="dep-empty" id="estadoVacio" hidden>
            <span aria-hidden="true"><svg><use href="#dep-icon-search"></use></svg></span>
            <h3>No hay coincidencias</h3>
            <p>Prueba con otro nombre o cambia el filtro de estado.</p>
        </div>

        <div class="dep-table-wrap" id="contenedorTabla" hidden tabindex="0" aria-label="Listado desplazable de departamentos">
            <table class="dep-table">
                <thead>
                    <tr>
                        <th>Departamento</th>
                        <th>Descripción</th>
                        <th>Uso actual</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaDepartamentos"></tbody>
            </table>
        </div>

        <footer class="dep-pagination" id="paginacion" hidden>
            <span id="textoPaginacion">Sin resultados</span>
            <div class="dep-pagination__buttons">
                <button type="button" id="btnAnterior">Anterior</button>
                <span id="paginaActual">Página 1</span>
                <button type="button" id="btnSiguiente">Siguiente</button>
            </div>
        </footer>
    </section>

    <footer class="dep-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Catálogo de departamentos protegido · Los Chapeteados División Petfood</span>
    </footer>

    <div class="dep-tools-background" aria-hidden="true"></div>
</main>

<section class="dep-modal" id="modalDepartamento" hidden>
    <div class="dep-modal__dialog dep-modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="tituloModal">
        <header class="dep-modal__header">
            <div>
                <p class="dep-eyebrow" id="etiquetaModal">NUEVO REGISTRO</p>
                <h2 id="tituloModal">Nuevo departamento</h2>
                <p id="subtituloModal">Agrega una división organizacional al sistema.</p>
            </div>
            <button type="button" class="dep-modal__close" id="btnCerrarModal" aria-label="Cerrar">×</button>
        </header>

        <form id="formDepartamento" novalidate>
            <input type="hidden" id="departamentoId" name="departamento_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="dep-modal__body">
                <section class="dep-form-section">
                    <header>
                        <span>01</span>
                        <div>
                            <h3>Identidad del departamento</h3>
                            <p>Utiliza un nombre claro que represente correctamente esta división de la empresa.</p>
                        </div>
                    </header>

                    <div class="dep-form-grid dep-form-grid--one">
                        <label class="dep-form-field" for="nombre">
                            <span>Nombre del departamento *</span>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                minlength="2"
                                maxlength="100"
                                placeholder="Ej. Producción"
                                autocomplete="organization"
                                required
                            >
                            <small>
                                Usa un nombre diferente a los ya registrados.
                                <b id="contadorNombre">0/100</b>
                            </small>
                            <em class="dep-error" id="errorNombre"></em>
                        </label>

                        <label class="dep-form-field" for="descripcion">
                            <span>Descripción</span>
                            <textarea
                                id="descripcion"
                                name="descripcion"
                                rows="5"
                                maxlength="500"
                                placeholder="Describe brevemente las funciones del departamento."
                            ></textarea>
                            <small>
                                Campo opcional.
                                <b id="contadorDescripcion">0/500</b>
                            </small>
                            <em class="dep-error" id="errorDescripcion"></em>
                        </label>
                    </div>
                </section>
            </div>

            <footer class="dep-modal__footer">
                <button type="button" class="dep-btn dep-btn--ghost" id="btnCancelar">Cancelar</button>
                <button type="submit" class="dep-btn dep-btn--primary" id="btnGuardar">Guardar departamento</button>
            </footer>
        </form>
    </div>
</section>

<section class="dep-modal dep-confirmation-modal" id="modalConfirmacion" hidden>
    <div class="dep-modal__dialog dep-modal__dialog--small" role="alertdialog" aria-modal="true" aria-labelledby="tituloConfirmacion" aria-describedby="textoConfirmacion">
        <header class="dep-modal__header">
            <div>
                <p class="dep-eyebrow">CONFIRMACIÓN SEGURA</p>
                <h2 id="tituloConfirmacion">Confirmar operación</h2>
                <p>La acción quedará registrada en la auditoría del sistema.</p>
            </div>
        </header>

        <div class="dep-confirmation">
            <span class="dep-confirmation__icon"><svg><use href="#dep-icon-warning"></use></svg></span>
            <p id="textoConfirmacion">Revisa la operación antes de continuar.</p>
        </div>

        <footer class="dep-modal__footer dep-modal__footer--alone">
            <button type="button" class="dep-btn dep-btn--ghost" id="btnCancelarConfirmacion">Cancelar</button>
            <button type="button" class="dep-btn dep-btn--primary" id="btnAceptarConfirmacion">Confirmar</button>
        </footer>
    </div>
</section>

<div class="dep-toast" id="toast" role="status" aria-live="polite" aria-atomic="true" hidden></div>

<?php include __DIR__ . '/../inc/alertas.php'; ?>
<script>
(function () {
    'use strict';

    var ENDPOINT = window.location.pathname + '?dep_api=1';
    var estado = {
        departamentos: [],
        filtrados: [],
        pagina: 1,
        cantidad: 10,
        cargando: false,
        guardando: false,
        resolverConfirmacion: null
    };
    var ui = {};

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        capturarElementos();
        registrarEventos();
        actualizarContadores();
        cargarDepartamentos(false);
    }

    function capturarElementos() {
        [
            'btnNuevo', 'btnActualizar', 'btnLimpiar', 'estadoPagina',
            'kpiTotal', 'kpiActivos', 'kpiInactivos', 'kpiEnUso',
            'filtroBusqueda', 'filtroEstado', 'filtroCantidad',
            'textoResultados', 'ultimaActualizacion', 'estadoCarga',
            'estadoVacio', 'contenedorTabla', 'tablaDepartamentos',
            'paginacion', 'textoPaginacion', 'btnAnterior', 'btnSiguiente',
            'paginaActual', 'modalDepartamento', 'btnCerrarModal',
            'btnCancelar', 'formDepartamento', 'departamentoId', 'nombre',
            'descripcion', 'contadorNombre', 'contadorDescripcion',
            'errorNombre', 'errorDescripcion', 'etiquetaModal', 'tituloModal',
            'subtituloModal', 'btnGuardar', 'modalConfirmacion',
            'tituloConfirmacion', 'textoConfirmacion',
            'btnCancelarConfirmacion', 'btnAceptarConfirmacion', 'toast'
        ].forEach(function (id) {
            ui[id] = document.getElementById(id);
        });
    }

    function registrarEventos() {
        ui.btnNuevo.addEventListener('click', abrirNuevo);
        ui.btnActualizar.addEventListener('click', function () {
            cargarDepartamentos(true);
        });
        ui.btnLimpiar.addEventListener('click', limpiarFiltros);
        ui.filtroBusqueda.addEventListener('input', aplicarFiltros);
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
            var paginas = totalPaginas();
            if (estado.pagina < paginas) {
                estado.pagina++;
                renderizar();
            }
        });
        ui.btnCerrarModal.addEventListener('click', cerrarModal);
        ui.btnCancelar.addEventListener('click', cerrarModal);
        ui.modalDepartamento.addEventListener('click', function (evento) {
            if (evento.target === ui.modalDepartamento) {
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
            if (!ui.modalDepartamento.hidden) {
                cerrarModal();
            }
        });
        ui.formDepartamento.addEventListener('submit', guardarDepartamento);
        ui.nombre.addEventListener('input', function () {
            ui.errorNombre.textContent = '';
            actualizarContadores();
        });
        ui.descripcion.addEventListener('input', function () {
            ui.errorDescripcion.textContent = '';
            actualizarContadores();
        });
        ui.tablaDepartamentos.addEventListener('click', manejarAccionTabla);
    }

    async function cargarDepartamentos(mostrarConfirmacion) {
        if (estado.cargando) {
            return;
        }

        estado.cargando = true;
        bloquearBoton(ui.btnActualizar, true, 'Actualizando...');
        mostrarCarga(true);
        mostrarEstado('Consultando departamentos...', 'info');

        try {
            var respuesta = await solicitar(ENDPOINT + '&accion=INICIAL&t=' + Date.now());
            estado.departamentos = Array.isArray(respuesta.departamentos)
                ? respuesta.departamentos
                : [];
            pintarResumen(respuesta.resumen || {});
            aplicarFiltros();
            ui.ultimaActualizacion.textContent = 'Actualizado ' + horaActual();
            mostrarEstado('Información actualizada correctamente.', 'success');

            if (mostrarConfirmacion) {
                toast('Lista actualizada.', 'success');
            }
        } catch (error) {
            mostrarEstado(error.message || 'No fue posible cargar los departamentos.', 'error');
            estado.departamentos = [];
            aplicarFiltros();
        } finally {
            estado.cargando = false;
            bloquearBoton(ui.btnActualizar, false);
            mostrarCarga(false);
        }
    }

    function aplicarFiltros() {
        var busqueda = normalizar(ui.filtroBusqueda.value);
        var filtroEstado = ui.filtroEstado.value;

        estado.filtrados = estado.departamentos.filter(function (departamento) {
            var coincideTexto = busqueda === '' || normalizar(
                String(departamento.nombre || '') + ' ' +
                String(departamento.descripcion || '')
            ).indexOf(busqueda) !== -1;

            var activo = Number(departamento.activo) === 1;
            var enUso = Number(departamento.total_relaciones_activas) > 0;
            var coincideEstado = filtroEstado === 'TODOS'
                || (filtroEstado === 'ACTIVO' && activo)
                || (filtroEstado === 'INACTIVO' && !activo)
                || (filtroEstado === 'EN_USO' && enUso)
                || (filtroEstado === 'SIN_USO' && !enUso);

            return coincideTexto && coincideEstado;
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

        var inicio = estado.cantidad > 0
            ? (estado.pagina - 1) * estado.cantidad
            : 0;
        var fin = estado.cantidad > 0
            ? Math.min(inicio + estado.cantidad, total)
            : total;
        var visibles = estado.filtrados.slice(inicio, fin);

        ui.tablaDepartamentos.innerHTML = visibles.map(crearFila).join('');
        ui.textoResultados.textContent = total === 1
            ? '1 departamento coincide con los filtros.'
            : total + ' departamentos coinciden con los filtros.';

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

    function crearFila(departamento) {
        var id = Number(departamento.id);
        var activo = Number(departamento.activo) === 1;
        var totalUso = Number(departamento.total_relaciones_activas) || 0;
        var puedeDesactivar = activo && totalUso === 0;
        var accionEstado = activo ? 'desactivar' : 'reactivar';
        var textoEstado = activo ? 'Desactivar' : 'Reactivar';
        var claseEstado = activo ? 'dep-action--danger' : 'dep-action--success';
        var deshabilitado = activo && !puedeDesactivar;

        return '<tr>' +
            '<td>' +
                '<div class="dep-identity">' +
                    '<span>' + escapeHtml(inicial(departamento.nombre)) + '</span>' +
                    '<div><strong>' + escapeHtml(departamento.nombre || '') + '</strong>' +
                    '<small>Registrado ' + escapeHtml(departamento.fecha_registro_texto || '—') + '</small></div>' +
                '</div>' +
            '</td>' +
            '<td><p class="dep-description">' + escapeHtml(departamento.descripcion || 'Sin descripción') + '</p></td>' +
            '<td>' + crearUso(departamento) + '</td>' +
            '<td><span class="dep-badge ' + (activo ? 'dep-badge--active' : 'dep-badge--inactive') + '">' +
                (activo ? 'Activo' : 'Inactivo') + '</span></td>' +
            '<td><div class="dep-actions">' +
                '<button type="button" class="dep-action" data-action="editar" data-id="' + id + '">Editar</button>' +
                '<button type="button" class="dep-action ' + claseEstado + '" data-action="' + accionEstado + '" data-id="' + id + '"' +
                    (deshabilitado ? ' disabled title="Tiene registros activos relacionados"' : '') + '>' + textoEstado + '</button>' +
            '</div></td>' +
        '</tr>';
    }

    function crearUso(departamento) {
        var usos = [
            ['Áreas', departamento.areas_activas],
            ['Solicitantes', departamento.solicitantes_activos],
            ['Técnicos', departamento.tecnicos_activos],
            ['Equipos', departamento.equipos_activos],
            ['Solicitudes', departamento.solicitudes_abiertas],
            ['Rutinas', departamento.rutinas_activas]
        ].filter(function (item) {
            return Number(item[1]) > 0;
        });

        if (usos.length === 0) {
            return '<span class="dep-no-use">Sin relaciones activas</span>';
        }

        return '<div class="dep-use-list">' + usos.map(function (item) {
            return '<span>' + escapeHtml(item[0]) + ' <b>' + Number(item[1]) + '</b></span>';
        }).join('') + '</div>';
    }

    async function manejarAccionTabla(evento) {
        var boton = evento.target.closest('[data-action]');
        if (!boton || boton.disabled) {
            return;
        }

        var id = Number(boton.getAttribute('data-id'));
        var accion = boton.getAttribute('data-action');

        if (!Number.isInteger(id) || id <= 0) {
            toast('No se pudo identificar el departamento.', 'error');
            return;
        }

        if (accion === 'editar') {
            abrirEdicion(id);
            return;
        }

        if (accion === 'desactivar' || accion === 'reactivar') {
            await cambiarEstado(id, accion === 'reactivar', boton);
        }
    }

    function abrirNuevo() {
        limpiarFormulario();
        ui.etiquetaModal.textContent = 'NUEVO REGISTRO';
        ui.tituloModal.textContent = 'Nuevo departamento';
        ui.subtituloModal.textContent = 'Agrega una división organizacional al sistema.';
        ui.btnGuardar.textContent = 'Guardar departamento';
        abrirModal();
    }

    function abrirEdicion(id) {
        var departamento = estado.departamentos.find(function (item) {
            return Number(item.id) === id;
        });

        if (!departamento) {
            toast('El departamento ya no está disponible.', 'error');
            return;
        }

        limpiarFormulario();
        ui.departamentoId.value = String(id);
        ui.nombre.value = departamento.nombre || '';
        ui.descripcion.value = departamento.descripcion || '';
        ui.etiquetaModal.textContent = 'EDICIÓN';
        ui.tituloModal.textContent = 'Editar departamento';
        ui.subtituloModal.textContent = 'Actualiza el nombre o la descripción del registro.';
        ui.btnGuardar.textContent = 'Actualizar departamento';
        actualizarContadores();
        abrirModal();
    }

    function abrirModal() {
        ui.modalDepartamento.hidden = false;
        document.body.classList.add('dep-modal-open');
        window.setTimeout(function () {
            ui.nombre.focus();
        }, 40);
    }

    function cerrarModal() {
        if (estado.guardando) {
            return;
        }
        ui.modalDepartamento.hidden = true;
        document.body.classList.remove('dep-modal-open');
        limpiarFormulario();
    }

    function limpiarFormulario() {
        ui.formDepartamento.reset();
        ui.departamentoId.value = '';
        ui.errorNombre.textContent = '';
        ui.errorDescripcion.textContent = '';
        actualizarContadores();
    }

    async function guardarDepartamento(evento) {
        evento.preventDefault();

        if (estado.guardando) {
            return;
        }

        var nombre = limpiarEspacios(ui.nombre.value);
        ui.nombre.value = nombre;
        ui.descripcion.value = ui.descripcion.value.trim();

        if (!validarFormulario()) {
            return;
        }

        estado.guardando = true;
        bloquearBoton(ui.btnGuardar, true, 'Guardando...');

        try {
            var formulario = new FormData(ui.formDepartamento);
            formulario.set('accion', 'GUARDAR');
            var respuesta = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            cerrarModalForzado();
            await cargarDepartamentos(false);
            toast(respuesta.mensaje || 'Departamento guardado correctamente.', 'success');
        } catch (error) {
            marcarErrorServidor(error);
            toast(error.message || 'No fue posible guardar el departamento.', 'error');
        } finally {
            estado.guardando = false;
            bloquearBoton(ui.btnGuardar, false);
        }
    }

    async function cambiarEstado(id, reactivar, boton) {
        var departamento = estado.departamentos.find(function (item) {
            return Number(item.id) === id;
        });
        if (!departamento) {
            toast('El registro ya no está disponible.', 'error');
            return;
        }

        var confirmado = await confirmar(
            reactivar ? '¿Reactivar departamento?' : '¿Desactivar departamento?',
            reactivar
                ? 'Volverá a estar disponible en los formularios del sistema.'
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
            formulario.set('csrf_token', <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);

            var respuesta = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });
            await cargarDepartamentos(false);
            toast(respuesta.mensaje || 'Estado actualizado.', 'success');
        } catch (error) {
            toast(error.message || 'No fue posible cambiar el estado.', 'error');
        } finally {
            bloquearBoton(boton, false);
        }
    }

    function validarFormulario() {
        var valido = true;
        var nombre = ui.nombre.value.trim();
        var descripcion = ui.descripcion.value.trim();

        ui.errorNombre.textContent = '';
        ui.errorDescripcion.textContent = '';

        if (nombre.length < 2 || nombre.length > 100) {
            ui.errorNombre.textContent = 'Escribe un nombre de 2 a 100 caracteres.';
            valido = false;
        }

        if (descripcion.length > 500) {
            ui.errorDescripcion.textContent = 'La descripción no puede superar 500 caracteres.';
            valido = false;
        }

        if (!valido) {
            (ui.errorNombre.textContent ? ui.nombre : ui.descripcion).focus();
        }

        return valido;
    }

    function marcarErrorServidor(error) {
        var campo = error && error.datos ? error.datos.campo : '';
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
        ui.filtroEstado.value = 'TODOS';
        ui.filtroCantidad.value = '10';
        estado.cantidad = 10;
        aplicarFiltros();
        ui.filtroBusqueda.focus();
    }

    function pintarResumen(resumen) {
        ui.kpiTotal.textContent = Number(resumen.total) || 0;
        ui.kpiActivos.textContent = Number(resumen.activos) || 0;
        ui.kpiInactivos.textContent = Number(resumen.inactivos) || 0;
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
        ui.estadoPagina.className = 'dep-status';
        if (tipo === 'error') {
            ui.estadoPagina.classList.add('dep-status--error');
        } else if (tipo === 'success') {
            ui.estadoPagina.classList.add('dep-status--success');
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
        return Math.max(1, Math.ceil(estado.filtrados.length / estado.cantidad));
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
            throw crearError('El servidor devolvió una respuesta no válida. Revisa el registro de PHP.', {}, respuesta.status);
        }

        if (!respuesta.ok || !datos.success) {
            if (datos.sesion_expirada && datos.redirect) {
                window.location.href = datos.redirect;
            }
            throw crearError(datos.mensaje || 'No fue posible completar la operación.', datos, respuesta.status);
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
                ? 'dep-btn dep-btn--danger'
                : 'dep-btn dep-btn--primary';
            ui.modalConfirmacion.classList.toggle('dep-modal--danger', Boolean(peligro));
            ui.modalConfirmacion.hidden = false;
            document.body.classList.add('dep-modal-open');

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
        ui.modalConfirmacion.classList.remove('dep-modal--danger');

        if (ui.modalDepartamento.hidden) {
            document.body.classList.remove('dep-modal-open');
        }

        var resolver = estado.resolverConfirmacion;
        estado.resolverConfirmacion = null;

        if (typeof resolver === 'function') {
            resolver(Boolean(resultado));
        }
    }

    function toast(mensaje, tipo) {
        ui.toast.textContent = mensaje;
        ui.toast.className = 'dep-toast dep-toast--' + (tipo || 'info');
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

    function cerrarModalForzado() {
        ui.modalDepartamento.hidden = true;
        document.body.classList.remove('dep-modal-open');
        limpiarFormulario();
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
        return limpio === '' ? 'D' : limpio.charAt(0).toUpperCase();
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