<?php

declare(strict_types=1);

/*
 * La interfaz consulta esta misma página mediante ?equipo_api=1 para evitar
 * depender de rutas relativas directas hacia la carpeta /funciones.
 */
if (isset($_GET['equipo_api'])) {
    $endpoint = __DIR__ . '/../funciones/equipos_funciones.php';

    if (!is_file($endpoint)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode(
            [
                'success' => false,
                'mensaje' => 'No se encontró funciones/equipos_funciones.php. Copia juntos los tres archivos del módulo en sus carpetas correspondientes.',
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

$cssPath = __DIR__ . '/../css/style_equipos.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '4.0';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b2944">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Gestión segura del catálogo de equipos del Sistema de Mantenimiento.">
    <title>Equipos | Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="../css/style_equipos.css?v=<?= rawurlencode($cssVersion) ?>">
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="equipo-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="equipo-icon-machine" viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="15" rx="2"/>
        <path d="M7 5V2h10v3M7 10h10M8 15h.01M12 15h4M7 20v2M17 20v2"/>
    </symbol>
    <symbol id="equipo-icon-plus" viewBox="0 0 24 24">
        <path d="M12 5v14M5 12h14"/>
    </symbol>
    <symbol id="equipo-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="equipo-icon-shield" viewBox="0 0 24 24">
        <path d="M12 2 20 5v6c0 5-3.4 9-8 11-4.6-2-8-6-8-11V5l8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="equipo-icon-list" viewBox="0 0 24 24">
        <path d="M8 6h13M8 12h13M8 18h13"/>
        <path d="M3 6h.01M3 12h.01M3 18h.01"/>
    </symbol>
    <symbol id="equipo-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="equipo-icon-filter" viewBox="0 0 24 24">
        <path d="M3 5h18M6 12h12M10 19h4"/>
    </symbol>
    <symbol id="equipo-icon-check" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8 12 2.6 2.6L16.5 9"/>
    </symbol>
    <symbol id="equipo-icon-inactive" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8.5 8.5 7 7M15.5 8.5l-7 7"/>
    </symbol>
    <symbol id="equipo-icon-link" viewBox="0 0 24 24">
        <path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.2 1.2"/>
        <path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.2-1.2"/>
    </symbol>
    <symbol id="equipo-icon-map" viewBox="0 0 24 24">
        <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/>
        <circle cx="12" cy="10" r="2.5"/>
    </symbol>
    <symbol id="equipo-icon-route" viewBox="0 0 24 24">
        <circle cx="6" cy="18" r="2"/>
        <circle cx="18" cy="6" r="2"/>
        <path d="M8 18h3a3 3 0 0 0 3-3v-6a3 3 0 0 1 3-3h-1"/>
    </symbol>
    <symbol id="equipo-icon-lock" viewBox="0 0 24 24">
        <rect x="4" y="10" width="16" height="11" rx="2"/>
        <path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>
    </symbol>
    <symbol id="equipo-icon-edit" viewBox="0 0 24 24">
        <path d="M12 20h9"/>
        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>
    </symbol>
</svg>

<main class="equipo-page">
    <header class="equipo-heading" aria-labelledby="tituloEquipos">
        <div class="equipo-heading__pattern" aria-hidden="true"></div>

        <div class="equipo-heading__content">
            <div class="equipo-heading__copy">
                <p class="equipo-eyebrow">
                    <span class="equipo-eyebrow__icon"><svg><use href="#equipo-icon-machine"></use></svg></span>
                    Catálogo operativo
                </p>
                <h1 id="tituloEquipos">Equipos</h1>
                <p>
                    Registra máquinas y activos de mantenimiento, conserva su identidad y ubícalos
                    dentro de la ruta completa de departamento, área y proceso.
                </p>

                <div class="equipo-heading__meta">
                    <span><i class="equipo-live-dot" aria-hidden="true"></i> Catálogo protegido y trazable</span>
                    <span>Administrador: <strong><?= htmlspecialchars($nombreAdmin, ENT_QUOTES, 'UTF-8') ?></strong></span>
                </div>
            </div>

            <div class="equipo-heading__actions" aria-label="Acciones del catálogo de equipos">
                <button type="button" class="equipo-btn equipo-btn--secondary" id="btnActualizar">
                    <svg><use href="#equipo-icon-refresh"></use></svg>
                    <span>Actualizar</span>
                </button>
                <button type="button" class="equipo-btn equipo-btn--primary" id="btnNuevo">
                    <svg><use href="#equipo-icon-plus"></use></svg>
                    <span>Nuevo equipo</span>
                </button>
            </div>

            <div class="equipo-heading__mini-card" aria-hidden="true">
                <span><svg><use href="#equipo-icon-route"></use></svg></span>
                <div>
                    <small>Ruta operativa</small>
                    <strong>Departamento, área y proceso vinculados</strong>
                </div>
            </div>
        </div>
    </header>

    <section class="equipo-security-note" aria-label="Reglas del catálogo">
        <span class="equipo-security-note__icon"><svg><use href="#equipo-icon-shield"></use></svg></span>
        <div>
            <strong>Identidad e historial protegidos</strong>
            <p>
                Los equipos se desactivan, no se eliminan. Cuando existe historial, el código y la ubicación
                quedan bloqueados para conservar la trazabilidad; el nombre y la descripción pueden corregirse.
            </p>
        </div>
        <span class="equipo-security-note__badge">Auditoría activa</span>
    </section>

    <div class="equipo-status" id="estadoPagina" role="status" aria-live="polite">
        <span class="equipo-spinner equipo-spinner--small" aria-hidden="true"></span>
        <span>Cargando equipos...</span>
    </div>

    <section class="equipo-kpis" aria-label="Resumen de equipos">
        <article class="equipo-kpi equipo-kpi--total">
            <span class="equipo-kpi__icon"><svg><use href="#equipo-icon-machine"></use></svg></span>
            <span class="equipo-kpi__body">
                <span>Total</span>
                <strong id="kpiTotal">0</strong>
                <small>Equipos registrados</small>
            </span>
        </article>
        <article class="equipo-kpi equipo-kpi--active">
            <span class="equipo-kpi__icon"><svg><use href="#equipo-icon-check"></use></svg></span>
            <span class="equipo-kpi__body">
                <span>Activos</span>
                <strong id="kpiActivos">0</strong>
                <small>Disponibles para solicitudes</small>
            </span>
        </article>
        <article class="equipo-kpi equipo-kpi--inactive">
            <span class="equipo-kpi__icon"><svg><use href="#equipo-icon-inactive"></use></svg></span>
            <span class="equipo-kpi__body">
                <span>Inactivos</span>
                <strong id="kpiInactivos">0</strong>
                <small>Conservados por historial</small>
            </span>
        </article>
        <article class="equipo-kpi equipo-kpi--use">
            <span class="equipo-kpi__icon"><svg><use href="#equipo-icon-link"></use></svg></span>
            <span class="equipo-kpi__body">
                <span>En uso</span>
                <strong id="kpiEnUso">0</strong>
                <small>Con relaciones activas</small>
            </span>
        </article>
    </section>

    <section class="equipo-card equipo-filters-card" aria-labelledby="tituloFiltrosEquipos">
        <header class="equipo-section-head">
            <div>
                <p class="equipo-eyebrow">Búsqueda y filtros</p>
                <h2 id="tituloFiltrosEquipos">Encuentra un equipo</h2>
                <p>Busca por código, nombre o ubicación y limita el catálogo por departamento, área o estado.</p>
            </div>
            <span class="equipo-section-head__chip"><svg><use href="#equipo-icon-filter"></use></svg> Consulta local</span>
        </header>

        <div class="equipo-filters">
            <label class="equipo-field equipo-field--search" for="filtroBusqueda">
                <span>Buscar</span>
                <div class="equipo-search">
                    <span aria-hidden="true"><svg><use href="#equipo-icon-search"></use></svg></span>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        maxlength="120"
                        placeholder="Código, equipo, proceso o ubicación"
                        autocomplete="off"
                    >
                </div>
            </label>

            <label class="equipo-field" for="filtroDepartamento">
                <span>Departamento</span>
                <select id="filtroDepartamento">
                    <option value="TODOS">Todos los departamentos</option>
                </select>
            </label>

            <label class="equipo-field" for="filtroArea">
                <span>Área</span>
                <select id="filtroArea">
                    <option value="TODAS">Todas las áreas</option>
                </select>
            </label>

            <label class="equipo-field" for="filtroEstado">
                <span>Estado</span>
                <select id="filtroEstado">
                    <option value="TODOS">Todos</option>
                    <option value="ACTIVO">Activos</option>
                    <option value="INACTIVO">Inactivos</option>
                    <option value="EN_USO">En uso</option>
                    <option value="SIN_USO">Sin relaciones activas</option>
                    <option value="UBICACION_NO_DISPONIBLE">Ubicación no disponible</option>
                </select>
            </label>

            <label class="equipo-field equipo-field--small" for="filtroCantidad">
                <span>Mostrar</span>
                <select id="filtroCantidad">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>

            <div class="equipo-filter-actions">
                <button type="button" class="equipo-btn equipo-btn--ghost" id="btnLimpiar">Limpiar</button>
            </div>
        </div>
    </section>

    <section class="equipo-card equipo-results equipo-results-card" aria-labelledby="tituloListadoEquipos">
        <header class="equipo-results__head">
            <div>
                <p class="equipo-eyebrow">Resultados</p>
                <h2 id="tituloListadoEquipos">Equipos registrados</h2>
                <p id="textoResultados">Preparando resultados...</p>
            </div>

            <div class="equipo-results__tools">
                <span class="equipo-updated" id="ultimaActualizacion">Sin actualizar</span>
                <span class="equipo-results__badge"><svg><use href="#equipo-icon-list"></use></svg> Catálogo protegido</span>
            </div>
        </header>

        <div class="equipo-loading" id="estadoCarga">
            <span class="equipo-spinner" aria-hidden="true"></span>
            <strong>Cargando equipos...</strong>
        </div>

        <div class="equipo-empty" id="estadoVacio" hidden>
            <span aria-hidden="true"><svg><use href="#equipo-icon-search"></use></svg></span>
            <h3>No hay coincidencias</h3>
            <p>Prueba con otro código, nombre o cambia los filtros.</p>
        </div>

        <div class="equipo-table-wrap" id="contenedorTabla" hidden tabindex="0" aria-label="Listado desplazable de equipos">
            <table class="equipo-table">
                <thead>
                    <tr>
                        <th>Equipo</th>
                        <th>Ubicación</th>
                        <th>Descripción</th>
                        <th>Uso actual</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaEquipos"></tbody>
            </table>
        </div>

        <footer class="equipo-pagination" id="paginacion" hidden>
            <span id="textoPaginacion">Sin resultados</span>
            <div class="equipo-pagination__buttons">
                <button type="button" id="btnAnterior">Anterior</button>
                <span id="paginaActual">Página 1</span>
                <button type="button" id="btnSiguiente">Siguiente</button>
            </div>
        </footer>
    </section>

    <footer class="equipo-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Catálogo operativo protegido · Los Chapeteados División Petfood</span>
    </footer>

    <div class="equipo-tools-background" aria-hidden="true"></div>
</main>

<section class="equipo-modal" id="modalEquipo" hidden>
    <div class="equipo-modal__dialog equipo-modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="tituloModal">
        <header class="equipo-modal__header">
            <div>
                <p class="equipo-eyebrow" id="etiquetaModal">NUEVO REGISTRO</p>
                <h2 id="tituloModal">Nuevo equipo</h2>
                <p id="subtituloModal">Completa la identidad y ubicación del equipo.</p>
            </div>
            <button type="button" class="equipo-modal__close" id="btnCerrarModal" aria-label="Cerrar">×</button>
        </header>

        <form id="formEquipo" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="equipo_id" id="equipoId">

            <div class="equipo-modal__body">
                <section class="equipo-form-section">
                    <header>
                        <span>01</span>
                        <div>
                            <h3>Identidad del equipo</h3>
                            <p>Usa un código y un nombre reconocibles para todo el personal.</p>
                        </div>
                    </header>

                    <div class="equipo-form-grid equipo-form-grid--two">
                        <label class="equipo-form-field" for="codigoEquipo">
                            <span>Código del equipo</span>
                            <input
                                type="text"
                                id="codigoEquipo"
                                name="codigo_equipo"
                                minlength="3"
                                maxlength="50"
                                placeholder="Déjalo vacío para generar uno"
                                autocomplete="off"
                                spellcheck="false"
                            >
                            <small id="ayudaCodigo">Opcional al crear. Ejemplo: EQ-001.</small>
                            <em class="equipo-field-error" id="errorCodigo"></em>
                        </label>

                        <label class="equipo-form-field" for="nombreEquipo">
                            <span>Nombre del equipo <b>*</b></span>
                            <input
                                type="text"
                                id="nombreEquipo"
                                name="nombre_equipo"
                                minlength="2"
                                maxlength="150"
                                placeholder="Ej. Mezcladora principal"
                                autocomplete="off"
                                required
                            >
                            <small><span id="contadorNombre">0</span>/150 caracteres</small>
                            <em class="equipo-field-error" id="errorNombre"></em>
                        </label>
                    </div>

                    <div class="equipo-lock-note" id="avisoIdentidad" hidden>
                        <span aria-hidden="true"><svg><use href="#equipo-icon-lock"></use></svg></span>
                        <p>
                            Este equipo ya tiene historial. El código y la ubicación no pueden cambiarse;
                            únicamente puedes corregir el nombre y la descripción.
                        </p>
                    </div>
                </section>

                <section class="equipo-form-section">
                    <header>
                        <span>02</span>
                        <div>
                            <h3>Ubicación operativa</h3>
                            <p>Selecciona la ruta completa donde trabaja el equipo.</p>
                        </div>
                    </header>

                    <div class="equipo-form-grid equipo-form-grid--three">
                        <label class="equipo-form-field" for="departamentoId">
                            <span>Departamento <b>*</b></span>
                            <select id="departamentoId" name="departamento_id" required>
                                <option value="">Selecciona un departamento</option>
                            </select>
                            <em class="equipo-field-error" id="errorDepartamento"></em>
                        </label>

                        <label class="equipo-form-field" for="areaId">
                            <span>Área <b>*</b></span>
                            <select id="areaId" name="area_id" required disabled>
                                <option value="">Selecciona primero el departamento</option>
                            </select>
                            <em class="equipo-field-error" id="errorArea"></em>
                        </label>

                        <label class="equipo-form-field" for="procesoId">
                            <span>Proceso <b>*</b></span>
                            <select id="procesoId" name="proceso_id" required disabled>
                                <option value="">Selecciona primero el área</option>
                            </select>
                            <em class="equipo-field-error" id="errorProceso"></em>
                        </label>
                    </div>

                    <div class="equipo-route-preview" id="vistaRuta">
                        <span aria-hidden="true"><svg><use href="#equipo-icon-map"></use></svg></span>
                        <p>Selecciona departamento, área y proceso para completar la ubicación.</p>
                    </div>
                </section>

                <section class="equipo-form-section">
                    <header>
                        <span>03</span>
                        <div>
                            <h3>Descripción</h3>
                            <p>Agrega información breve que ayude a distinguir el equipo.</p>
                        </div>
                    </header>

                    <div class="equipo-form-section__body">
                        <label class="equipo-form-field" for="descripcion">
                            <span>Descripción opcional</span>
                            <textarea
                                id="descripcion"
                                name="descripcion"
                                maxlength="800"
                                rows="4"
                                placeholder="Ej. Equipo principal de mezclado, ubicado junto a la línea 2."
                            ></textarea>
                            <small><span id="contadorDescripcion">0</span>/800 caracteres</small>
                            <em class="equipo-field-error" id="errorDescripcion"></em>
                        </label>
                    </div>
                </section>
            </div>

            <footer class="equipo-modal__footer">
                <button type="button" class="equipo-btn equipo-btn--ghost" id="btnCancelarModal">Cancelar</button>
                <button type="submit" class="equipo-btn equipo-btn--primary" id="btnGuardar">Guardar equipo</button>
            </footer>
        </form>
    </div>
</section>

<div class="equipo-toast" id="toast" role="status" aria-live="polite" hidden></div>

<?php include __DIR__ . '/../inc/alertas.php'; ?>

<script>
(function () {
    'use strict'; 

    var API = window.location.pathname + '?equipo_api=1';
    var estado = {
        equipos: [],
        filtrados: [],
        catalogos: { departamentos: [], areas: [], procesos: [] },
        pagina: 1,
        cantidad: 10,
        cargando: false,
        guardando: false,
        editandoId: 0,
        identidadProtegida: false
    };

    var ui = {
        btnNuevo: document.getElementById('btnNuevo'),
        btnActualizar: document.getElementById('btnActualizar'),
        btnLimpiar: document.getElementById('btnLimpiar'),
        filtroBusqueda: document.getElementById('filtroBusqueda'),
        filtroDepartamento: document.getElementById('filtroDepartamento'),
        filtroArea: document.getElementById('filtroArea'),
        filtroEstado: document.getElementById('filtroEstado'),
        filtroCantidad: document.getElementById('filtroCantidad'),
        estadoPagina: document.getElementById('estadoPagina'),
        kpiTotal: document.getElementById('kpiTotal'),
        kpiActivos: document.getElementById('kpiActivos'),
        kpiInactivos: document.getElementById('kpiInactivos'),
        kpiEnUso: document.getElementById('kpiEnUso'),
        textoResultados: document.getElementById('textoResultados'),
        ultimaActualizacion: document.getElementById('ultimaActualizacion'),
        estadoCarga: document.getElementById('estadoCarga'),
        estadoVacio: document.getElementById('estadoVacio'),
        contenedorTabla: document.getElementById('contenedorTabla'),
        tablaEquipos: document.getElementById('tablaEquipos'),
        paginacion: document.getElementById('paginacion'),
        textoPaginacion: document.getElementById('textoPaginacion'),
        paginaActual: document.getElementById('paginaActual'),
        btnAnterior: document.getElementById('btnAnterior'),
        btnSiguiente: document.getElementById('btnSiguiente'),
        modalEquipo: document.getElementById('modalEquipo'),
        etiquetaModal: document.getElementById('etiquetaModal'),
        tituloModal: document.getElementById('tituloModal'),
        subtituloModal: document.getElementById('subtituloModal'),
        btnCerrarModal: document.getElementById('btnCerrarModal'),
        btnCancelarModal: document.getElementById('btnCancelarModal'),
        formEquipo: document.getElementById('formEquipo'),
        equipoId: document.getElementById('equipoId'),
        codigoEquipo: document.getElementById('codigoEquipo'),
        ayudaCodigo: document.getElementById('ayudaCodigo'),
        nombreEquipo: document.getElementById('nombreEquipo'),
        departamentoId: document.getElementById('departamentoId'),
        areaId: document.getElementById('areaId'),
        procesoId: document.getElementById('procesoId'),
        vistaRuta: document.getElementById('vistaRuta'),
        descripcion: document.getElementById('descripcion'),
        contadorNombre: document.getElementById('contadorNombre'),
        contadorDescripcion: document.getElementById('contadorDescripcion'),
        avisoIdentidad: document.getElementById('avisoIdentidad'),
        btnGuardar: document.getElementById('btnGuardar'),
        errorCodigo: document.getElementById('errorCodigo'),
        errorNombre: document.getElementById('errorNombre'),
        errorDepartamento: document.getElementById('errorDepartamento'),
        errorArea: document.getElementById('errorArea'),
        errorProceso: document.getElementById('errorProceso'),
        errorDescripcion: document.getElementById('errorDescripcion'),
        toast: document.getElementById('toast')
    };

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        registrarEventos();
        actualizarContadores();
        cargarEquipos();
    }

    function registrarEventos() {
        ui.btnNuevo.addEventListener('click', abrirNuevo);
        ui.btnActualizar.addEventListener('click', cargarEquipos);
        ui.btnLimpiar.addEventListener('click', limpiarFiltros);
        ui.filtroBusqueda.addEventListener('input', aplicarFiltros);
        ui.filtroDepartamento.addEventListener('change', function () {
            actualizarFiltroAreas();
            aplicarFiltros();
        });
        ui.filtroArea.addEventListener('change', aplicarFiltros);
        ui.filtroEstado.addEventListener('change', aplicarFiltros);
        ui.filtroCantidad.addEventListener('change', function () {
            estado.cantidad = Number(ui.filtroCantidad.value) || 0;
            estado.pagina = 1;
            pintarTabla();
        });

        ui.btnAnterior.addEventListener('click', function () {
            if (estado.pagina > 1) {
                estado.pagina--;
                pintarTabla();
            }
        });
        ui.btnSiguiente.addEventListener('click', function () {
            if (estado.pagina < totalPaginas()) {
                estado.pagina++;
                pintarTabla();
            }
        });

        ui.tablaEquipos.addEventListener('click', manejarAccionTabla);
        ui.formEquipo.addEventListener('submit', guardarEquipo);
        ui.btnCerrarModal.addEventListener('click', function () { cerrarModal(false); });
        ui.btnCancelarModal.addEventListener('click', function () { cerrarModal(false); });
        ui.modalEquipo.addEventListener('mousedown', function (evento) {
            if (evento.target === ui.modalEquipo) {
                cerrarModal(false);
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && !ui.modalEquipo.hidden) {
                cerrarModal(false);
            }
        });

        ui.codigoEquipo.addEventListener('input', function () {
            ui.codigoEquipo.value = ui.codigoEquipo.value
                .toUpperCase()
                .replace(/[^A-Z0-9._-]/g, '')
                .slice(0, 50);
            limpiarError(ui.codigoEquipo, ui.errorCodigo);
        });
        ui.nombreEquipo.addEventListener('input', function () {
            limpiarError(ui.nombreEquipo, ui.errorNombre);
            actualizarContadores();
        });
        ui.descripcion.addEventListener('input', function () {
            limpiarError(ui.descripcion, ui.errorDescripcion);
            actualizarContadores();
        });
        ui.departamentoId.addEventListener('change', function () {
            limpiarError(ui.departamentoId, ui.errorDepartamento);
            llenarAreasFormulario(Number(ui.departamentoId.value) || 0, 0);
            llenarProcesosFormulario(0, 0);
            actualizarVistaRuta();
        });
        ui.areaId.addEventListener('change', function () {
            limpiarError(ui.areaId, ui.errorArea);
            llenarProcesosFormulario(Number(ui.areaId.value) || 0, 0);
            actualizarVistaRuta();
        });
        ui.procesoId.addEventListener('change', function () {
            limpiarError(ui.procesoId, ui.errorProceso);
            actualizarVistaRuta();
        });
    }

    async function cargarEquipos() {
        if (estado.cargando) {
            return;
        }

        estado.cargando = true;
        bloquearBoton(ui.btnActualizar, true, 'Actualizando...');
        mostrarCarga(true);
        mostrarEstado('Consultando el catálogo de equipos...', 'info');

        try {
            var datos = await solicitar(API + '&accion=INICIAL');
            estado.equipos = Array.isArray(datos.equipos) ? datos.equipos : [];
            estado.catalogos = datos.catalogos || { departamentos: [], areas: [], procesos: [] };
            pintarResumen(datos.resumen || {});
            llenarFiltros();
            aplicarFiltros();
            ui.ultimaActualizacion.textContent = 'Actualizado ' + horaActual();
            mostrarEstado('Catálogo actualizado correctamente.', 'success');
            validarDisponibilidadAlta();
        } catch (error) {
            console.error(error);
            estado.equipos = [];
            estado.filtrados = [];
            pintarTabla();
            mostrarEstado(error.message || 'No fue posible cargar los equipos.', 'error');
            toast(error.message || 'No fue posible cargar los equipos.', 'error');
        } finally {
            estado.cargando = false;
            bloquearBoton(ui.btnActualizar, false);
            mostrarCarga(false);
        }
    }

    function pintarResumen(resumen) {
        ui.kpiTotal.textContent = numero(resumen.total);
        ui.kpiActivos.textContent = numero(resumen.activos);
        ui.kpiInactivos.textContent = numero(resumen.inactivos);
        ui.kpiEnUso.textContent = numero(resumen.en_uso);
    }

    function llenarFiltros() {
        var departamentoActual = ui.filtroDepartamento.value;
        ui.filtroDepartamento.innerHTML = '<option value="TODOS">Todos los departamentos</option>';

        (estado.catalogos.departamentos || []).forEach(function (departamento) {
            var opcion = document.createElement('option');
            opcion.value = String(departamento.id);
            opcion.textContent = departamento.nombre + (Number(departamento.activo) === 1 ? '' : ' · Inactivo');
            ui.filtroDepartamento.appendChild(opcion);
        });

        if (opcionExiste(ui.filtroDepartamento, departamentoActual)) {
            ui.filtroDepartamento.value = departamentoActual;
        }
        actualizarFiltroAreas();
    }

    function actualizarFiltroAreas() {
        var valorAnterior = ui.filtroArea.value;
        var departamento = ui.filtroDepartamento.value;
        ui.filtroArea.innerHTML = '<option value="TODAS">Todas las áreas</option>';

        (estado.catalogos.areas || []).filter(function (area) {
            return departamento === 'TODOS'
                || Number(area.departamento_id) === Number(departamento);
        }).forEach(function (area) {
            var opcion = document.createElement('option');
            opcion.value = String(area.id);
            opcion.textContent = area.nombre + (Number(area.activo) === 1 ? '' : ' · Inactiva');
            ui.filtroArea.appendChild(opcion);
        });

        ui.filtroArea.value = opcionExiste(ui.filtroArea, valorAnterior)
            ? valorAnterior : 'TODAS';
    }

    function aplicarFiltros() {
        var busqueda = normalizarBusqueda(ui.filtroBusqueda.value);
        var departamento = ui.filtroDepartamento.value;
        var area = ui.filtroArea.value;
        var filtroEstado = ui.filtroEstado.value;

        estado.filtrados = estado.equipos.filter(function (equipo) {
            var texto = normalizarBusqueda([
                equipo.codigo_equipo,
                equipo.nombre_equipo,
                equipo.descripcion,
                equipo.departamento,
                equipo.area,
                equipo.proceso
            ].join(' '));

            if (busqueda && texto.indexOf(busqueda) === -1) {
                return false;
            }
            if (departamento !== 'TODOS' && Number(equipo.departamento_id) !== Number(departamento)) {
                return false;
            }
            if (area !== 'TODAS' && Number(equipo.area_id) !== Number(area)) {
                return false;
            }

            if (filtroEstado === 'ACTIVO' && Number(equipo.activo) !== 1) {
                return false;
            }
            if (filtroEstado === 'INACTIVO' && Number(equipo.activo) !== 0) {
                return false;
            }
            if (filtroEstado === 'EN_USO' && Number(equipo.total_relaciones_activas) < 1) {
                return false;
            }
            if (filtroEstado === 'SIN_USO' && Number(equipo.total_relaciones_activas) > 0) {
                return false;
            }
            if (
                filtroEstado === 'UBICACION_NO_DISPONIBLE'
                && Number(equipo.ubicacion_disponible) === 1
            ) {
                return false;
            }

            return true;
        });

        estado.pagina = 1;
        pintarTabla();
    }

    function pintarTabla() {
        var total = estado.filtrados.length;
        var paginas = totalPaginas();
        if (estado.pagina > paginas) {
            estado.pagina = paginas;
        }

        var inicio = estado.cantidad === 0
            ? 0 : (estado.pagina - 1) * estado.cantidad;
        var fin = estado.cantidad === 0
            ? total : Math.min(total, inicio + estado.cantidad);
        var filas = estado.cantidad === 0
            ? estado.filtrados : estado.filtrados.slice(inicio, fin);

        ui.tablaEquipos.innerHTML = filas.map(filaEquipo).join('');
        ui.estadoVacio.hidden = total !== 0;
        ui.contenedorTabla.hidden = total === 0;
        ui.paginacion.hidden = total === 0 || estado.cantidad === 0 || paginas <= 1;
        ui.textoResultados.textContent = total === 1
            ? '1 equipo coincide con los filtros.'
            : numero(total) + ' equipos coinciden con los filtros.';

        ui.textoPaginacion.textContent = total === 0
            ? 'Sin resultados'
            : 'Mostrando ' + numero(inicio + 1) + ' a ' + numero(fin)
                + ' de ' + numero(total);
        ui.paginaActual.textContent = 'Página ' + estado.pagina + ' de ' + paginas;
        ui.btnAnterior.disabled = estado.pagina <= 1;
        ui.btnSiguiente.disabled = estado.pagina >= paginas;
    }

    function filaEquipo(equipo) {
        var activo = Number(equipo.activo) === 1;
        var ubicacionDisponible = Number(equipo.ubicacion_disponible) === 1;
        var solicitudes = Number(equipo.solicitudes_abiertas) || 0;
        var rutinas = Number(equipo.rutinas_activas) || 0;
        var historial = Number(equipo.total_relaciones_historicas) || 0;
        var totalActivo = solicitudes + rutinas;
        var puedeDesactivar = Number(equipo.puede_desactivar) === 1;

        var uso = [];
        if (solicitudes > 0) {
            uso.push('<span class="equipo-chip equipo-chip--request">Solicitudes ' + solicitudes + '</span>');
        }
        if (rutinas > 0) {
            uso.push('<span class="equipo-chip equipo-chip--routine">Rutinas ' + rutinas + '</span>');
        }
        if (uso.length === 0) {
            uso.push('<span class="equipo-chip equipo-chip--empty">Sin relaciones activas</span>');
        }
        if (historial > 0) {
            uso.push('<small class="equipo-history">Historial protegido · ' + historial + '</small>');
        }

        var estadoUbicacion = ubicacionDisponible
            ? ''
            : '<span class="equipo-location-warning">Ubicación no disponible</span>';

        var botonEstado;
        if (activo && !puedeDesactivar) {
            botonEstado = '<button type="button" class="equipo-action equipo-action--danger" disabled '
                + 'title="Tiene ' + totalActivo + ' relaciones activas">Desactivar</button>';
        } else {
            botonEstado = '<button type="button" class="equipo-action '
                + (activo ? 'equipo-action--danger' : 'equipo-action--success') + '" '
                + 'data-action="estado" data-id="' + Number(equipo.id) + '" '
                + 'data-activo="' + (activo ? '0' : '1') + '">'
                + (activo ? 'Desactivar' : 'Reactivar') + '</button>';
        }

        return '<tr>'
            + '<td><div class="equipo-identity">'
            + '<span class="equipo-identity__icon">' + escapeHtml(iniciales(equipo.nombre_equipo)) + '</span>'
            + '<div><strong>' + escapeHtml(equipo.nombre_equipo || 'Sin nombre') + '</strong>'
            + '<code>' + escapeHtml(equipo.codigo_equipo || 'Sin código') + '</code></div>'
            + '</div></td>'
            + '<td><div class="equipo-location">'
            + '<strong>' + escapeHtml(equipo.proceso || 'Proceso no disponible') + '</strong>'
            + '<span>' + escapeHtml(equipo.area || 'Área no disponible') + '</span>'
            + '<small>' + escapeHtml(equipo.departamento || 'Departamento no disponible') + '</small>'
            + estadoUbicacion + '</div></td>'
            + '<td><p class="equipo-description" title="' + escapeHtml(equipo.descripcion || 'Sin descripción') + '">'
            + escapeHtml(equipo.descripcion || 'Sin descripción') + '</p></td>'
            + '<td><div class="equipo-usage">' + uso.join('') + '</div></td>'
            + '<td><span class="equipo-badge ' + (activo ? 'equipo-badge--active' : 'equipo-badge--inactive') + '">'
            + (activo ? 'Activo' : 'Inactivo') + '</span>'
            + '<small class="equipo-date">Desde ' + escapeHtml(equipo.fecha_registro_texto || '—') + '</small></td>'
            + '<td><div class="equipo-actions">'
            + '<button type="button" class="equipo-action" data-action="editar" data-id="' + Number(equipo.id) + '">Editar</button>'
            + botonEstado
            + '</div></td>'
            + '</tr>';
    }

    async function manejarAccionTabla(evento) {
        var boton = evento.target.closest('[data-action]');
        if (!boton || boton.disabled) {
            return;
        }

        var id = Number(boton.dataset.id);
        if (!Number.isInteger(id) || id < 1) {
            toast('No se pudo identificar el equipo.', 'error');
            return;
        }

        if (boton.dataset.action === 'editar') {
            await abrirEditar(id, boton);
        } else if (boton.dataset.action === 'estado') {
            await cambiarEstado(id, Number(boton.dataset.activo), boton);
        }
    }

    function abrirNuevo() {
        var disponibles = (estado.catalogos.procesos || []).some(function (proceso) {
            return Number(proceso.activo) === 1
                && Number(proceso.area_activa) === 1
                && Number(proceso.departamento_activo) === 1;
        });

        if (!disponibles) {
            toast('Primero registra y activa un departamento, un área y un proceso.', 'error');
            return;
        }

        limpiarFormulario();
        estado.editandoId = 0;
        estado.identidadProtegida = false;
        ui.etiquetaModal.textContent = 'NUEVO REGISTRO';
        ui.tituloModal.textContent = 'Nuevo equipo';
        ui.subtituloModal.textContent = 'Completa la identidad y ubicación del equipo.';
        ui.btnGuardar.textContent = 'Guardar equipo';
        ui.ayudaCodigo.textContent = 'Opcional. Si lo dejas vacío, el servidor generará un código único.';
        ui.avisoIdentidad.hidden = true;
        ui.codigoEquipo.disabled = false;
        llenarDepartamentosFormulario(0);
        abrirModal();
    }

    async function abrirEditar(id, boton) {
        bloquearBoton(boton, true, 'Cargando...');

        try {
            var datos = await solicitar(API + '&accion=DETALLE&id=' + encodeURIComponent(id));
            var equipo = datos.equipo || {};

            limpiarFormulario();
            estado.editandoId = id;
            estado.identidadProtegida = Number(equipo.puede_cambiar_identidad) !== 1;
            ui.equipoId.value = String(id);
            ui.codigoEquipo.value = equipo.codigo_equipo || '';
            ui.nombreEquipo.value = equipo.nombre_equipo || '';
            ui.descripcion.value = equipo.descripcion || '';
            ui.etiquetaModal.textContent = 'EDITAR REGISTRO';
            ui.tituloModal.textContent = 'Editar equipo';
            ui.subtituloModal.textContent = estado.identidadProtegida
                ? 'Puedes corregir el nombre y la descripción. La identidad operativa está protegida.'
                : 'Actualiza la información y ubicación del equipo.';
            ui.btnGuardar.textContent = 'Actualizar equipo';
            ui.avisoIdentidad.hidden = !estado.identidadProtegida;
            ui.codigoEquipo.disabled = estado.identidadProtegida;
            ui.ayudaCodigo.textContent = estado.identidadProtegida
                ? 'Código protegido porque el equipo ya tiene historial.'
                : 'El código debe ser único en todo el sistema.';

            llenarDepartamentosFormulario(Number(equipo.departamento_id) || 0);
            ui.departamentoId.value = String(equipo.departamento_id || '');
            llenarAreasFormulario(
                Number(equipo.departamento_id) || 0,
                Number(equipo.area_id) || 0
            );
            ui.areaId.value = String(equipo.area_id || '');
            llenarProcesosFormulario(
                Number(equipo.area_id) || 0,
                Number(equipo.proceso_id) || 0
            );
            ui.procesoId.value = String(equipo.proceso_id || '');

            ui.departamentoId.disabled = estado.identidadProtegida;
            ui.areaId.disabled = estado.identidadProtegida;
            ui.procesoId.disabled = estado.identidadProtegida;
            actualizarContadores();
            actualizarVistaRuta();
            abrirModal();
        } catch (error) {
            console.error(error);
            toast(error.message || 'No fue posible abrir el equipo.', 'error');
        } finally {
            bloquearBoton(boton, false);
        }
    }

    async function guardarEquipo(evento) {
        evento.preventDefault();
        if (estado.guardando) {
            return;
        }

        limpiarErrores();
        if (!validarFormulario()) {
            toast('Revisa los campos marcados.', 'error');
            return;
        }

        estado.guardando = true;
        bloquearBoton(ui.btnGuardar, true, 'Guardando...');

        try {
            var formulario = new FormData(ui.formEquipo);
            formulario.set('accion', 'GUARDAR');
            formulario.set('equipo_id', ui.equipoId.value || '');
            formulario.set('codigo_equipo', ui.codigoEquipo.value || '');
            formulario.set('nombre_equipo', normalizarEspacios(ui.nombreEquipo.value));
            formulario.set('descripcion', ui.descripcion.value.trim());
            formulario.set('departamento_id', ui.departamentoId.value || '');
            formulario.set('area_id', ui.areaId.value || '');
            formulario.set('proceso_id', ui.procesoId.value || '');

            var datos = await solicitar(API, { method: 'POST', body: formulario });
            cerrarModal(true);
            await cargarEquipos();

            var mensaje = datos.mensaje || 'Equipo guardado correctamente.';
            if (datos.codigo_equipo) {
                mensaje += ' Código: ' + datos.codigo_equipo + '.';
            }
            toast(mensaje, 'success');
        } catch (error) {
            console.error(error);
            marcarErrorServidor(error.datos && error.datos.campo, error.message);
            toast(error.message || 'No fue posible guardar el equipo.', 'error');
        } finally {
            estado.guardando = false;
            bloquearBoton(ui.btnGuardar, false);
        }
    }

    async function cambiarEstado(id, nuevoEstado, boton) {
        var equipo = estado.equipos.find(function (item) {
            return Number(item.id) === id;
        });
        if (!equipo) {
            toast('El equipo ya no se encuentra en la lista.', 'error');
            return;
        }

        var reactivar = nuevoEstado === 1;
        var confirmado = await confirmar(
            reactivar ? '¿Reactivar equipo?' : '¿Desactivar equipo?',
            reactivar
                ? 'Volverá a estar disponible para nuevas solicitudes y rutinas.'
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
            formulario.set('activo', String(nuevoEstado));
            formulario.set('csrf_token', ui.formEquipo.elements.csrf_token.value);

            var datos = await solicitar(API, { method: 'POST', body: formulario });
            await cargarEquipos();
            toast(datos.mensaje || 'Estado actualizado correctamente.', 'success');
        } catch (error) {
            console.error(error);
            toast(error.message || 'No fue posible cambiar el estado.', 'error');
        } finally {
            bloquearBoton(boton, false);
        }
    }

    function llenarDepartamentosFormulario(incluirId) {
        ui.departamentoId.innerHTML = '<option value="">Selecciona un departamento</option>';
        (estado.catalogos.departamentos || []).filter(function (departamento) {
            return Number(departamento.activo) === 1 || Number(departamento.id) === Number(incluirId);
        }).forEach(function (departamento) {
            var opcion = document.createElement('option');
            opcion.value = String(departamento.id);
            opcion.textContent = departamento.nombre
                + (Number(departamento.activo) === 1 ? '' : ' · Inactivo');
            ui.departamentoId.appendChild(opcion);
        });
        ui.departamentoId.disabled = false;
        llenarAreasFormulario(0, 0);
        llenarProcesosFormulario(0, 0);
    }

    function llenarAreasFormulario(departamentoId, incluirId) {
        ui.areaId.innerHTML = '<option value="">'
            + (departamentoId > 0 ? 'Selecciona un área' : 'Selecciona primero el departamento')
            + '</option>';

        if (departamentoId < 1) {
            ui.areaId.disabled = true;
            return;
        }

        var areas = (estado.catalogos.areas || []).filter(function (area) {
            return Number(area.departamento_id) === departamentoId
                && (
                    (Number(area.activo) === 1 && Number(area.departamento_activo) === 1)
                    || Number(area.id) === Number(incluirId)
                );
        });

        areas.forEach(function (area) {
            var opcion = document.createElement('option');
            opcion.value = String(area.id);
            opcion.textContent = area.nombre
                + (Number(area.activo) === 1 && Number(area.departamento_activo) === 1
                    ? '' : ' · No disponible');
            ui.areaId.appendChild(opcion);
        });
        ui.areaId.disabled = false;
    }

    function llenarProcesosFormulario(areaId, incluirId) {
        ui.procesoId.innerHTML = '<option value="">'
            + (areaId > 0 ? 'Selecciona un proceso' : 'Selecciona primero el área')
            + '</option>';

        if (areaId < 1) {
            ui.procesoId.disabled = true;
            return;
        }

        var procesos = (estado.catalogos.procesos || []).filter(function (proceso) {
            return Number(proceso.area_id) === areaId
                && (
                    (
                        Number(proceso.activo) === 1
                        && Number(proceso.area_activa) === 1
                        && Number(proceso.departamento_activo) === 1
                    )
                    || Number(proceso.id) === Number(incluirId)
                );
        });

        procesos.forEach(function (proceso) {
            var opcion = document.createElement('option');
            opcion.value = String(proceso.id);
            opcion.textContent = proceso.nombre
                + (
                    Number(proceso.activo) === 1
                    && Number(proceso.area_activa) === 1
                    && Number(proceso.departamento_activo) === 1
                    ? '' : ' · No disponible'
                );
            ui.procesoId.appendChild(opcion);
        });
        ui.procesoId.disabled = false;
    }

    function actualizarVistaRuta() {
        var departamento = textoOpcion(ui.departamentoId);
        var area = textoOpcion(ui.areaId);
        var proceso = textoOpcion(ui.procesoId);

        if (!ui.departamentoId.value || !ui.areaId.value || !ui.procesoId.value) {
            ui.vistaRuta.classList.remove('is-complete');
            ui.vistaRuta.querySelector('p').textContent =
                'Selecciona departamento, área y proceso para completar la ubicación.';
            return;
        }

        ui.vistaRuta.classList.add('is-complete');
        ui.vistaRuta.querySelector('p').textContent =
            departamento + ' → ' + area + ' → ' + proceso;
    }

    function validarFormulario() {
        var valido = true;
        var codigo = ui.codigoEquipo.value.trim().toUpperCase();
        var nombre = normalizarEspacios(ui.nombreEquipo.value);

        if (codigo !== '' && !/^[A-Z0-9._-]{3,50}$/.test(codigo)) {
            marcarError(ui.codigoEquipo, ui.errorCodigo, 'Usa de 3 a 50 letras, números, puntos, guiones o guion bajo.');
            valido = false;
        }
        if (nombre.length < 2 || nombre.length > 150) {
            marcarError(ui.nombreEquipo, ui.errorNombre, 'Escribe un nombre de 2 a 150 caracteres.');
            valido = false;
        }
        if (!ui.departamentoId.value) {
            marcarError(ui.departamentoId, ui.errorDepartamento, 'Selecciona el departamento.');
            valido = false;
        }
        if (!ui.areaId.value) {
            marcarError(ui.areaId, ui.errorArea, 'Selecciona el área.');
            valido = false;
        }
        if (!ui.procesoId.value) {
            marcarError(ui.procesoId, ui.errorProceso, 'Selecciona el proceso.');
            valido = false;
        }
        if (ui.descripcion.value.trim().length > 800) {
            marcarError(ui.descripcion, ui.errorDescripcion, 'La descripción no puede superar 800 caracteres.');
            valido = false;
        }

        if (!valido) {
            var primerError = ui.formEquipo.querySelector('.is-invalid');
            if (primerError) {
                primerError.focus();
            }
        }
        return valido;
    }

    function limpiarFormulario() {
        ui.formEquipo.reset();
        ui.equipoId.value = '';
        estado.editandoId = 0;
        estado.identidadProtegida = false;
        ui.codigoEquipo.disabled = false;
        ui.departamentoId.disabled = false;
        ui.areaId.disabled = true;
        ui.procesoId.disabled = true;
        ui.avisoIdentidad.hidden = true;
        llenarDepartamentosFormulario(0);
        limpiarErrores();
        actualizarContadores();
        actualizarVistaRuta();
    }

    function limpiarFiltros() {
        ui.filtroBusqueda.value = '';
        ui.filtroDepartamento.value = 'TODOS';
        actualizarFiltroAreas();
        ui.filtroArea.value = 'TODAS';
        ui.filtroEstado.value = 'TODOS';
        ui.filtroCantidad.value = '10';
        estado.cantidad = 10;
        estado.pagina = 1;
        aplicarFiltros();
    }

    function validarDisponibilidadAlta() {
        var disponible = (estado.catalogos.procesos || []).some(function (proceso) {
            return Number(proceso.activo) === 1
                && Number(proceso.area_activa) === 1
                && Number(proceso.departamento_activo) === 1;
        });
        ui.btnNuevo.disabled = !disponible;
        ui.btnNuevo.title = disponible
            ? ''
            : 'Primero registra y activa departamento, área y proceso.';
    }

    function abrirModal() {
        ui.modalEquipo.hidden = false;
        document.body.classList.add('equipo-modal-open');
        window.setTimeout(function () {
            (ui.codigoEquipo.disabled ? ui.nombreEquipo : ui.codigoEquipo).focus();
        }, 50);
    }

    function cerrarModal(forzar) {
        if (estado.guardando && forzar !== true) {
            return;
        }
        ui.modalEquipo.hidden = true;
        document.body.classList.remove('equipo-modal-open');
        limpiarFormulario();
    }

    function actualizarContadores() {
        ui.contadorNombre.textContent = String(ui.nombreEquipo.value || '').length;
        ui.contadorDescripcion.textContent = String(ui.descripcion.value || '').length;
    }

    function limpiarErrores() {
        [
            [ui.codigoEquipo, ui.errorCodigo],
            [ui.nombreEquipo, ui.errorNombre],
            [ui.departamentoId, ui.errorDepartamento],
            [ui.areaId, ui.errorArea],
            [ui.procesoId, ui.errorProceso],
            [ui.descripcion, ui.errorDescripcion]
        ].forEach(function (par) {
            limpiarError(par[0], par[1]);
        });
    }

    function marcarErrorServidor(campo, mensaje) {
        var mapa = {
            codigo_equipo: [ui.codigoEquipo, ui.errorCodigo],
            nombre_equipo: [ui.nombreEquipo, ui.errorNombre],
            departamento_id: [ui.departamentoId, ui.errorDepartamento],
            area_id: [ui.areaId, ui.errorArea],
            proceso_id: [ui.procesoId, ui.errorProceso],
            descripcion: [ui.descripcion, ui.errorDescripcion]
        };
        if (campo && mapa[campo]) {
            marcarError(mapa[campo][0], mapa[campo][1], mensaje || 'Revisa este campo.');
        }
    }

    function marcarError(campo, contenedor, mensaje) {
        campo.classList.add('is-invalid');
        contenedor.textContent = mensaje;
    }

    function limpiarError(campo, contenedor) {
        campo.classList.remove('is-invalid');
        contenedor.textContent = '';
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
        ui.estadoPagina.className = 'equipo-status equipo-status--' + (tipo || 'info');
    }

    function totalPaginas() {
        if (estado.cantidad === 0) {
            return 1;
        }
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
                    ? 'No se encontró el servicio de Equipos.'
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
        if (window.SistemaUI && typeof window.SistemaUI.confirmar === 'function') {
            return window.SistemaUI.confirmar({
                titulo: titulo,
                texto: texto,
                textoConfirmar: textoConfirmar,
                peligro: Boolean(peligro)
            });
        }
        return Promise.resolve(window.confirm(titulo + '\n\n' + texto));
    }

    function toast(mensaje, tipo) {
        ui.toast.textContent = mensaje;
        ui.toast.className = 'equipo-toast equipo-toast--' + (tipo || 'info');
        ui.toast.hidden = false;
        window.clearTimeout(toast.temporizador);
        toast.temporizador = window.setTimeout(function () {
            ui.toast.hidden = true;
        }, 4400);
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

    function opcionExiste(select, valor) {
        return Array.prototype.some.call(select.options, function (opcion) {
            return opcion.value === valor;
        });
    }

    function textoOpcion(select) {
        var opcion = select.options[select.selectedIndex];
        return opcion ? opcion.textContent.replace(/ · (Inactivo|Inactiva|No disponible)$/i, '') : '';
    }

    function normalizarBusqueda(texto) {
        return String(texto || '')
            .toLocaleLowerCase('es')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function normalizarEspacios(texto) {
        return String(texto || '').replace(/\s+/g, ' ').trim();
    }

    function numero(valor) {
        return new Intl.NumberFormat('es-MX').format(Number(valor) || 0);
    }

    function horaActual() {
        return new Intl.DateTimeFormat('es-MX', {
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date());
    }

    function iniciales(nombre) {
        var partes = normalizarEspacios(nombre).split(' ').filter(Boolean);
        return partes.slice(0, 2).map(function (parte) {
            return parte.charAt(0).toUpperCase();
        }).join('') || 'EQ';
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