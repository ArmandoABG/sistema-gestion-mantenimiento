<?php

declare(strict_types=1);

/*
 * La interfaz consulta esta misma página mediante ?rsc_api=1. Así se conserva
 * el patrón de rutas del sistema y no se expone directamente la carpeta
 * funciones desde el navegador.
 */
if (isset($_GET['rsc_api'])) {
    $endpoint = __DIR__ . '/../funciones/recursos_mantenimiento_funciones.php';

    if (!is_file($endpoint)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode(
            [
                'success' => false,
                'mensaje' => 'No se encontró funciones/recursos_mantenimiento_funciones.php. Copia todos los archivos de la Parte 2 en sus carpetas correspondientes.',
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

$cssPath = __DIR__ . '/../css/style_recursos_mantenimiento.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '1.0';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b2944">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Catálogo seguro de herramientas y refacciones del Sistema de Mantenimiento.">
    <title>Herramientas y refacciones | Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="../css/style_recursos_mantenimiento.css?v=<?= rawurlencode($cssVersion) ?>">
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="rsc-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="rsc-icon-box" viewBox="0 0 24 24">
        <path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z"/>
        <path d="m4.5 7.5 7.5 4.25 7.5-4.25M12 11.75V21"/>
    </symbol>
    <symbol id="rsc-icon-tool" viewBox="0 0 24 24">
        <path d="M14.2 6.1a4.5 4.5 0 0 0-5.7 5.7L3.7 16.6a2.4 2.4 0 0 0 3.4 3.4l4.8-4.8a4.5 4.5 0 0 0 5.7-5.7l-2.8 2.8-3.1-3.1 2.5-3.1Z"/>
    </symbol>
    <symbol id="rsc-icon-part" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="3.2"/>
        <path d="M19.2 13.5a7.8 7.8 0 0 0 0-3l2-1.2-2-3.5-2.1 1.1a8 8 0 0 0-2.6-1.5L14.3 3h-4l-.3 2.4a8 8 0 0 0-2.6 1.5L5.3 5.8l-2 3.5 2 1.2a7.8 7.8 0 0 0 0 3l-2 1.2 2 3.5 2.1-1.1a8 8 0 0 0 2.6 1.5l.3 2.4h4l.3-2.4a8 8 0 0 0 2.6-1.5l2.1 1.1 2-3.5-2.1-1.2Z"/>
    </symbol>
    <symbol id="rsc-icon-plus" viewBox="0 0 24 24">
        <path d="M12 5v14M5 12h14"/>
    </symbol>
    <symbol id="rsc-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="rsc-icon-shield" viewBox="0 0 24 24">
        <path d="M12 2 20 5v6c0 5-3.4 9-8 11-4.6-2-8-6-8-11V5l8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="rsc-icon-list" viewBox="0 0 24 24">
        <path d="M8 6h13M8 12h13M8 18h13"/>
        <path d="M3 6h.01M3 12h.01M3 18h.01"/>
    </symbol>
    <symbol id="rsc-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="rsc-icon-filter" viewBox="0 0 24 24">
        <path d="M3 5h18M6 12h12M10 19h4"/>
    </symbol>
    <symbol id="rsc-icon-check" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8 12 2.6 2.6L16.5 9"/>
    </symbol>
    <symbol id="rsc-icon-inactive" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8.5 8.5 7 7M15.5 8.5l-7 7"/>
    </symbol>
    <symbol id="rsc-icon-link" viewBox="0 0 24 24">
        <path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.2 1.2"/>
        <path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.2-1.2"/>
    </symbol>
    <symbol id="rsc-icon-warning" viewBox="0 0 24 24">
        <path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
        <path d="M12 9v4M12 17h.01"/>
    </symbol>
    <symbol id="rsc-icon-info" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 10.5v6M12 7.5h.01"/>
    </symbol>
</svg>

<main class="rsc-page">
    <header class="rsc-heading" aria-labelledby="tituloRecursos">
        <div class="rsc-heading__pattern" aria-hidden="true"></div>

        <div class="rsc-heading__content">
            <div class="rsc-heading__copy">
                <p class="rsc-eyebrow">
                    <span class="rsc-eyebrow__icon"><svg><use href="#rsc-icon-box"></use></svg></span>
                    Catálogo técnico central
                </p>
                <h1 id="tituloRecursos">Herramientas y refacciones</h1>
                <p>
                    Registra los recursos que después podrán recomendarse por equipo, tipo de
                    mantenimiento, rutina y cierre técnico, sin perder relaciones históricas.
                </p>

                <div class="rsc-heading__meta">
                    <span><i class="rsc-live-dot" aria-hidden="true"></i> Catálogo listo para automatización</span>
                    <span>Administrador: <strong><?= htmlspecialchars($nombreAdmin, ENT_QUOTES, 'UTF-8') ?></strong></span>
                </div>
            </div>

            <div class="rsc-heading__actions" aria-label="Acciones del catálogo">
                <button type="button" class="rsc-btn rsc-btn--secondary" id="btnActualizar">
                    <svg><use href="#rsc-icon-refresh"></use></svg>
                    <span>Actualizar</span>
                </button>
                <button type="button" class="rsc-btn rsc-btn--primary" id="btnNuevo">
                    <svg><use href="#rsc-icon-plus"></use></svg>
                    <span>Nuevo recurso</span>
                </button>
            </div>

            <div class="rsc-heading__mini-card">
                <span><svg><use href="#rsc-icon-link"></use></svg></span>
                <div>
                    <small>Relaciones conservadas</small>
                    <strong id="resumenEnUso">0 recursos vinculados</strong>
                </div>
            </div>
        </div>
    </header>

    <section class="rsc-security-note" aria-label="Reglas del catálogo">
        <span class="rsc-security-note__icon"><svg><use href="#rsc-icon-shield"></use></svg></span>
        <div>
            <strong>Sin eliminaciones y con auditoría completa</strong>
            <p>
                Los recursos se desactivan para nuevas selecciones, pero sus recomendaciones,
                rutinas y cierres anteriores permanecen intactos. Cada cambio queda registrado.
            </p>
        </div>
        <span class="rsc-security-note__badge">Historial protegido</span>
    </section>

    <div class="rsc-status" id="estadoPagina" role="status" aria-live="polite">
        <span class="rsc-spinner rsc-spinner--small" aria-hidden="true"></span>
        <span>Cargando herramientas y refacciones...</span>
    </div>

    <section class="rsc-card rsc-suggestions-card" aria-labelledby="tituloSugerenciasRecursos">
        <header class="rsc-suggestions-card__head">
            <div>
                <p class="rsc-eyebrow">
                    <span class="rsc-eyebrow__icon"><svg><use href="#rsc-icon-warning"></use></svg></span>
                    Propuestas desde mantenimientos terminados
                </p>
                <h2 id="tituloSugerenciasRecursos">Sugerencias de técnicos</h2>
                <p>
                    Cuando un técnico utiliza “Otra herramienta” u “Otra refacción”, aquí puedes
                    incorporarla al catálogo, recomendarla para ese equipo o rechazarla.
                </p>
            </div>
            <span class="rsc-suggestions-count" id="contadorSugerencias">0 pendientes</span>
        </header>

        <div class="rsc-suggestions-list" id="listaSugerencias"></div>
        <div class="rsc-suggestions-empty" id="sugerenciasVacias" hidden>
            <span aria-hidden="true">✓</span>
            <div>
                <strong>No hay sugerencias pendientes</strong>
                <p>Las nuevas propuestas aparecerán después de que un técnico finalice un mantenimiento.</p>
            </div>
        </div>
    </section>

    <section class="rsc-type-tabs" aria-label="Filtrar por tipo de recurso">
        <button type="button" class="rsc-type-tab is-active" data-tipo="TODOS" aria-pressed="true">
            <svg><use href="#rsc-icon-box"></use></svg>
            Todos los recursos
        </button>
        <button type="button" class="rsc-type-tab" data-tipo="HERRAMIENTA" aria-pressed="false">
            <svg><use href="#rsc-icon-tool"></use></svg>
            Herramientas
        </button>
        <button type="button" class="rsc-type-tab" data-tipo="REFACCION" aria-pressed="false">
            <svg><use href="#rsc-icon-part"></use></svg>
            Refacciones
        </button>
    </section>

    <section class="rsc-kpis" aria-label="Resumen del catálogo">
        <article class="rsc-kpi rsc-kpi--total">
            <span class="rsc-kpi__icon"><svg><use href="#rsc-icon-box"></use></svg></span>
            <span class="rsc-kpi__body">
                <span>Total</span>
                <strong id="kpiTotal">0</strong>
                <small>Recursos registrados</small>
            </span>
        </article>
        <article class="rsc-kpi rsc-kpi--tools">
            <span class="rsc-kpi__icon"><svg><use href="#rsc-icon-tool"></use></svg></span>
            <span class="rsc-kpi__body">
                <span>Herramientas</span>
                <strong id="kpiHerramientas">0</strong>
                <small>Equipo de trabajo</small>
            </span>
        </article>
        <article class="rsc-kpi rsc-kpi--parts">
            <span class="rsc-kpi__icon"><svg><use href="#rsc-icon-part"></use></svg></span>
            <span class="rsc-kpi__body">
                <span>Refacciones</span>
                <strong id="kpiRefacciones">0</strong>
                <small>Piezas y reemplazos</small>
            </span>
        </article>
        <article class="rsc-kpi rsc-kpi--active">
            <span class="rsc-kpi__icon"><svg><use href="#rsc-icon-check"></use></svg></span>
            <span class="rsc-kpi__body">
                <span>Activos</span>
                <strong id="kpiActivos">0</strong>
                <small>Disponibles para seleccionar</small>
            </span>
        </article>
        <article class="rsc-kpi rsc-kpi--inactive">
            <span class="rsc-kpi__icon"><svg><use href="#rsc-icon-inactive"></use></svg></span>
            <span class="rsc-kpi__body">
                <span>Inactivos</span>
                <strong id="kpiInactivos">0</strong>
                <small>Conservados por historial</small>
            </span>
        </article>
    </section>

    <section class="rsc-card rsc-filters-card" aria-labelledby="tituloFiltrosRecursos">
        <header class="rsc-section-head">
            <div>
                <p class="rsc-eyebrow">Búsqueda y filtros</p>
                <h2 id="tituloFiltrosRecursos">Encuentra un recurso</h2>
                <p>Busca por nombre, código o descripción y limita el resultado por estado o uso.</p>
            </div>
            <span class="rsc-section-head__chip"><svg><use href="#rsc-icon-filter"></use></svg> Consulta local</span>
        </header>

        <div class="rsc-filter-grid">
            <label class="rsc-field rsc-field--search" for="filtroBusqueda">
                <span>Buscar</span>
                <div class="rsc-search">
                    <span aria-hidden="true"><svg><use href="#rsc-icon-search"></use></svg></span>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        maxlength="150"
                        placeholder="Nombre, código o descripción"
                        autocomplete="off"
                        spellcheck="false"
                    >
                </div>
            </label>

            <label class="rsc-field" for="filtroEstado">
                <span>Estado</span>
                <select id="filtroEstado">
                    <option value="TODOS">Todos</option>
                    <option value="ACTIVOS">Activos</option>
                    <option value="INACTIVOS">Inactivos</option>
                </select>
            </label>

            <label class="rsc-field" for="filtroUso">
                <span>Relaciones</span>
                <select id="filtroUso">
                    <option value="TODOS">Todos</option>
                    <option value="EN_USO">En uso</option>
                    <option value="SIN_USO">Sin uso todavía</option>
                </select>
            </label>

            <label class="rsc-field" for="filtroCantidad">
                <span>Mostrar</span>
                <select id="filtroCantidad">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="0">Todos</option>
                </select>
            </label>

            <div class="rsc-filter-actions">
                <button type="button" class="rsc-btn rsc-btn--ghost" id="btnLimpiar">Limpiar</button>
            </div>
        </div>
    </section>

    <section class="rsc-card rsc-results rsc-results-card" aria-labelledby="tituloListadoRecursos">
        <header class="rsc-results__head">
            <div>
                <p class="rsc-eyebrow">Resultados</p>
                <h2 id="tituloListadoRecursos">Recursos registrados</h2>
                <p id="textoResultados">Preparando resultados...</p>
            </div>

            <div class="rsc-results__tools">
                <span class="rsc-updated" id="ultimaActualizacion">Sin actualizar</span>
                <span class="rsc-results__badge"><svg><use href="#rsc-icon-list"></use></svg> Listado protegido</span>
            </div>
        </header>

        <div class="rsc-loading" id="estadoCarga">
            <span class="rsc-spinner" aria-hidden="true"></span>
            <strong>Cargando catálogo...</strong>
        </div>

        <div class="rsc-empty" id="estadoVacio" hidden>
            <span aria-hidden="true"><svg><use href="#rsc-icon-search"></use></svg></span>
            <h3>No hay coincidencias</h3>
            <p>Prueba con otro texto o cambia los filtros aplicados.</p>
        </div>

        <div class="rsc-table-wrap" id="contenedorTabla" hidden tabindex="0" aria-label="Listado desplazable de herramientas y refacciones">
            <table class="rsc-table">
                <thead>
                    <tr>
                        <th>Recurso</th>
                        <th>Tipo</th>
                        <th>Código y descripción</th>
                        <th>Uso registrado</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaRecursos"></tbody>
            </table>
        </div>

        <footer class="rsc-pagination" id="paginacion" hidden>
            <span id="textoPaginacion">Sin resultados</span>
            <div class="rsc-pagination__buttons">
                <button type="button" id="btnAnterior">Anterior</button>
                <span id="paginaActual">Página 1</span>
                <button type="button" id="btnSiguiente">Siguiente</button>
            </div>
        </footer>
    </section>

    <footer class="rsc-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Catálogo técnico central · Los Chapeteados División Petfood</span>
    </footer>

    <div class="rsc-tools-background" aria-hidden="true"></div>
</main>

<section class="rsc-modal" id="modalRecurso" hidden>
    <div class="rsc-modal__dialog rsc-modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="tituloModal">
        <header class="rsc-modal__header">
            <div>
                <p class="rsc-eyebrow" id="etiquetaModal">NUEVO REGISTRO</p>
                <h2 id="tituloModal">Nuevo recurso</h2>
                <p id="subtituloModal">Agrega una herramienta o refacción al catálogo central.</p>
            </div>
            <button type="button" class="rsc-modal__close" id="btnCerrarModal" aria-label="Cerrar">×</button>
        </header>

        <form id="formRecurso" novalidate>
            <input type="hidden" id="recursoId" name="recurso_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="rsc-modal__body">
                <div class="rsc-usage-warning" id="avisoRecursoEnUso" hidden>
                    <svg><use href="#rsc-icon-warning"></use></svg>
                    <div>
                        <strong>Este recurso ya tiene relaciones</strong>
                        <span id="textoAvisoRecursoEnUso">Puedes corregir sus datos, pero no cambiar de herramienta a refacción o viceversa.</span>
                    </div>
                </div>

                <section class="rsc-form-section">
                    <header>
                        <span>01</span>
                        <div>
                            <h3>Identidad del recurso</h3>
                            <p>Usa nombres claros y evita registrar el mismo elemento con variaciones mínimas.</p>
                        </div>
                    </header>

                    <div class="rsc-form-grid rsc-form-grid--two">
                        <label class="rsc-form-field" for="tipoRecurso">
                            <span>Tipo de recurso *</span>
                            <select id="tipoRecurso" name="tipo_recurso" required>
                                <option value="">Selecciona una opción</option>
                                <option value="HERRAMIENTA">Herramienta</option>
                                <option value="REFACCION">Refacción</option>
                            </select>
                            <small>Define en qué buscador aparecerá.</small>
                            <em class="rsc-error" id="errorTipoRecurso"></em>
                        </label>

                        <label class="rsc-form-field" for="codigo">
                            <span>Código interno</span>
                            <input
                                type="text"
                                id="codigo"
                                name="codigo"
                                maxlength="60"
                                placeholder="Se asignará al seleccionar el tipo"
                                autocomplete="off"
                                spellcheck="false"
                                readonly
                                class="rsc-generated-code-input"
                            >
                            <small>
                                Se genera automáticamente: <strong>HER-001</strong> para herramientas y
                                <strong>REF-001</strong> para refacciones.
                                <b id="contadorCodigo">Automático</b>
                            </small>
                            <em class="rsc-error" id="errorCodigo"></em>
                        </label>

                        <label class="rsc-form-field rsc-form-field--full" for="nombre">
                            <span>Nombre *</span>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                minlength="2"
                                maxlength="150"
                                placeholder="Ej. Multímetro digital o rodamiento 6204"
                                autocomplete="off"
                                required
                            >
                            <small>
                                Debe ser identificable para administradores y técnicos.
                                <b id="contadorNombre">0/150</b>
                            </small>
                            <em class="rsc-error" id="errorNombre"></em>
                        </label>

                        <label class="rsc-form-field rsc-form-field--full" for="descripcion">
                            <span>Descripción o especificación</span>
                            <textarea
                                id="descripcion"
                                name="descripcion"
                                rows="5"
                                maxlength="500"
                                placeholder="Medidas, capacidad, presentación, compatibilidad o cualquier dato útil para distinguirlo."
                            ></textarea>
                            <small>
                                Campo opcional.
                                <b id="contadorDescripcion">0/500</b>
                            </small>
                            <em class="rsc-error" id="errorDescripcion"></em>
                        </label>
                    </div>

                    <div class="rsc-form-hint">
                        <svg><use href="#rsc-icon-info"></use></svg>
                        <span>
                            Si un técnico utiliza algo que todavía no existe, lo escribirá como “Otro” y
                            llegará posteriormente como sugerencia para aprobación administrativa.
                        </span>
                    </div>
                </section>
            </div>

            <footer class="rsc-modal__footer">
                <button type="button" class="rsc-btn rsc-btn--ghost" id="btnCancelar">Cancelar</button>
                <button type="submit" class="rsc-btn rsc-btn--primary" id="btnGuardar">Guardar recurso</button>
            </footer>
        </form>
    </div>
</section>

<section class="rsc-modal rsc-confirmation-modal" id="modalConfirmacion" hidden>
    <div class="rsc-modal__dialog rsc-modal__dialog--small" role="alertdialog" aria-modal="true" aria-labelledby="tituloConfirmacion" aria-describedby="textoConfirmacion">
        <header class="rsc-modal__header">
            <div>
                <p class="rsc-eyebrow">CONFIRMACIÓN SEGURA</p>
                <h2 id="tituloConfirmacion">Confirmar operación</h2>
                <p>La acción quedará registrada en la auditoría del sistema.</p>
            </div>
        </header>

        <div class="rsc-confirmation">
            <span class="rsc-confirmation__icon"><svg><use href="#rsc-icon-warning"></use></svg></span>
            <p id="textoConfirmacion">Revisa la operación antes de continuar.</p>
        </div>

        <footer class="rsc-modal__footer rsc-modal__footer--alone">
            <button type="button" class="rsc-btn rsc-btn--ghost" id="btnCancelarConfirmacion">Cancelar</button>
            <button type="button" class="rsc-btn rsc-btn--primary" id="btnAceptarConfirmacion">Confirmar</button>
        </footer>
    </div>
</section>

<div class="rsc-toast" id="toast" role="status" aria-live="polite" aria-atomic="true" hidden></div>

<?php include __DIR__ . '/../inc/alertas.php'; ?>
<script>
(function () {
    'use strict';

    var ENDPOINT = window.location.pathname + '?rsc_api=1';
    var CSRF_TOKEN = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var estado = {
        recursos: [],
        filtrados: [],
        sugerencias: [],
        resumenSugerencias: {},
        codigosSiguientes: {HERRAMIENTA: 'HER-001', REFACCION: 'REF-001'},
        completandoCodigos: false,
        tipo: 'TODOS',
        pagina: 1,
        cantidad: 10,
        cargando: false,
        guardando: false,
        recursoEditado: null,
        resolverConfirmacion: null
    };
    var ui = {};

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        capturarElementos();
        registrarEventos();
        actualizarContadores();
        cargarRecursos(false);
    }

    function capturarElementos() {
        [
            'btnNuevo', 'btnActualizar', 'btnLimpiar', 'estadoPagina',
            'contadorSugerencias', 'listaSugerencias', 'sugerenciasVacias',
            'kpiTotal', 'kpiHerramientas', 'kpiRefacciones', 'kpiActivos',
            'kpiInactivos', 'resumenEnUso', 'filtroBusqueda', 'filtroEstado',
            'filtroUso', 'filtroCantidad', 'textoResultados', 'ultimaActualizacion',
            'estadoCarga', 'estadoVacio', 'contenedorTabla', 'tablaRecursos',
            'paginacion', 'textoPaginacion', 'btnAnterior', 'btnSiguiente',
            'paginaActual', 'modalRecurso', 'btnCerrarModal', 'btnCancelar',
            'formRecurso', 'recursoId', 'tipoRecurso', 'codigo', 'nombre',
            'descripcion', 'contadorCodigo', 'contadorNombre', 'contadorDescripcion',
            'errorTipoRecurso', 'errorCodigo', 'errorNombre', 'errorDescripcion',
            'etiquetaModal', 'tituloModal', 'subtituloModal', 'btnGuardar',
            'avisoRecursoEnUso', 'textoAvisoRecursoEnUso', 'modalConfirmacion',
            'tituloConfirmacion', 'textoConfirmacion', 'btnCancelarConfirmacion',
            'btnAceptarConfirmacion', 'toast'
        ].forEach(function (id) {
            ui[id] = document.getElementById(id);
        });

        ui.tabsTipo = Array.prototype.slice.call(document.querySelectorAll('[data-tipo]'));
    }

    function registrarEventos() {
        ui.btnNuevo.addEventListener('click', abrirNuevo);
        ui.btnActualizar.addEventListener('click', function () {
            cargarRecursos(true);
        });
        ui.btnLimpiar.addEventListener('click', limpiarFiltros);

        ui.tabsTipo.forEach(function (tab) {
            tab.addEventListener('click', function () {
                cambiarTipo(tab.getAttribute('data-tipo') || 'TODOS');
            });
        });

        ui.filtroBusqueda.addEventListener('input', aplicarFiltros);
        ui.filtroEstado.addEventListener('change', aplicarFiltros);
        ui.filtroUso.addEventListener('change', aplicarFiltros);
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

        ui.tablaRecursos.addEventListener('click', manejarAccionTabla);
        ui.listaSugerencias.addEventListener('click', manejarSugerencia);
        ui.formRecurso.addEventListener('submit', guardarRecurso);
        ui.btnCerrarModal.addEventListener('click', cerrarModal);
        ui.btnCancelar.addEventListener('click', cerrarModal);
        ui.modalRecurso.addEventListener('click', function (evento) {
            if (evento.target === ui.modalRecurso) {
                cerrarModal();
            }
        });

        [ui.nombre, ui.descripcion].forEach(function (campo) {
            campo.addEventListener('input', actualizarContadores);
        });
        ui.tipoRecurso.addEventListener('change', function () {
            validarCambioTipoEnUso();
            actualizarVistaCodigoAutomatico();
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
            if (!ui.modalRecurso.hidden) {
                cerrarModal();
            }
        });
    }

    async function cargarRecursos(notificar) {
        if (estado.cargando) {
            return;
        }

        estado.cargando = true;
        bloquearBoton(ui.btnActualizar, true, 'Actualizando...');
        mostrarCarga(true);
        mostrarEstado('Consultando el catálogo seguro...', 'info');

        try {
            var respuesta = await solicitar(ENDPOINT + '&accion=LISTAR', {method: 'GET'});
            estado.recursos = Array.isArray(respuesta.recursos) ? respuesta.recursos : [];
            estado.sugerencias = Array.isArray(respuesta.sugerencias) ? respuesta.sugerencias : [];
            estado.resumenSugerencias = respuesta.resumen_sugerencias || {};
            estado.codigosSiguientes = respuesta.codigos_siguientes || estado.codigosSiguientes;

            if (Number(respuesta.recursos_sin_codigo || 0) > 0 && !estado.completandoCodigos) {
                estado.completandoCodigos = true;
                try {
                    await completarCodigosExistentes();
                    estado.cargando = false;
                    bloquearBoton(ui.btnActualizar, false);
                    mostrarCarga(false);
                    await cargarRecursos(false);
                    return;
                } catch (errorCodigos) {
                    /*
                     * La asignación automática de códigos es una mejora del
                     * catálogo, pero no debe dejar toda la pantalla vacía si
                     * esa operación secundaria falla. Se conserva el listado
                     * ya cargado y se muestra una advertencia recuperable.
                     */
                    mostrarEstado(
                        errorCodigos.message || 'El catálogo se cargó, pero no fue posible completar algunos códigos.',
                        'warning'
                    );
                    toast(
                        errorCodigos.message || 'El catálogo se cargó con códigos pendientes.',
                        'warning'
                    );
                } finally {
                    estado.completandoCodigos = false;
                }
            }

            pintarResumen(respuesta.resumen || {});
            pintarSugerencias();
            aplicarFiltros();
            ui.ultimaActualizacion.textContent = 'Actualizado ' + formatearHora(new Date());
            mostrarEstado('Catálogo actualizado correctamente.', 'success');
            if (notificar) {
                toast('Catálogo actualizado.', 'success');
            }
        } catch (error) {
            estado.recursos = [];
            estado.sugerencias = [];
            pintarSugerencias();
            aplicarFiltros();
            mostrarEstado(error.message || 'No fue posible cargar el catálogo.', 'error');
            toast(error.message || 'No fue posible cargar el catálogo.', 'error');
        } finally {
            estado.cargando = false;
            bloquearBoton(ui.btnActualizar, false);
            mostrarCarga(false);
            pintarTabla();
        }
    }

    async function completarCodigosExistentes() {
        var formulario = new FormData();
        formulario.set('accion', 'COMPLETAR_CODIGOS');
        formulario.set('csrf_token', CSRF_TOKEN);
        await solicitar(ENDPOINT, {method: 'POST', body: formulario});
    }

    function actualizarVistaCodigoAutomatico() {
        if (estado.recursoEditado && ui.tipoRecurso.value === estado.recursoEditado.tipo_recurso) {
            ui.codigo.value = estado.recursoEditado.codigo || '';
            return;
        }
        ui.codigo.value = estado.codigosSiguientes[ui.tipoRecurso.value] || '';
    }

    function pintarSugerencias() {
        var pendientes = estado.sugerencias.filter(function (sugerencia) {
            return sugerencia.estado === 'PENDIENTE';
        });
        var cantidad = Number(estado.resumenSugerencias.pendientes || pendientes.length);
        ui.contadorSugerencias.textContent = cantidad + (cantidad === 1 ? ' pendiente' : ' pendientes');
        ui.sugerenciasVacias.hidden = pendientes.length !== 0;

        if (pendientes.length === 0) {
            ui.listaSugerencias.innerHTML = '';
            return;
        }

        ui.listaSugerencias.innerHTML = pendientes.map(function (sugerencia) {
            var herramienta = sugerencia.tipo_recurso === 'HERRAMIENTA';
            var coincide = Number(sugerencia.coincide_catalogo) === 1;
            return '<article class="rsc-suggestion" data-sugerencia-id="' + Number(sugerencia.id) + '">' +
                '<header>' +
                    '<span class="rsc-suggestion__icon">' + (herramienta ? '🔧' : '⚙️') + '</span>' +
                    '<div>' +
                        '<span class="rsc-suggestion__type">' + escapeHtml(herramienta ? 'HERRAMIENTA' : 'REFACCIÓN') + '</span>' +
                        '<h3>' + escapeHtml(sugerencia.nombre_sugerido || 'Sin nombre') + '</h3>' +
                        '<p>' + escapeHtml((sugerencia.folio || '') + ' · ' + (sugerencia.codigo_equipo || '') + ' · ' + (sugerencia.nombre_equipo || '')) + '</p>' +
                    '</div>' +
                    (coincide ? '<span class="rsc-suggestion__match">Ya existe un nombre igual</span>' : '') +
                '</header>' +
                '<div class="rsc-suggestion__meta">' +
                    '<span>Propuesta por <strong>' + escapeHtml(sugerencia.tecnico || 'Técnico') + '</strong></span>' +
                    '<span>' + escapeHtml(formatearFechaHora(sugerencia.fecha_registro)) + '</span>' +
                '</div>' +
                '<label class="rsc-suggestion__note">' +
                    '<span>Observación administrativa (opcional al aprobar)</span>' +
                    '<textarea data-sugerencia-observacion maxlength="500" rows="2" placeholder="Ej. Validada con almacén; usar esta descripción oficial."></textarea>' +
                '</label>' +
                '<footer>' +
                    '<button type="button" class="rsc-suggestion-action rsc-suggestion-action--reject" data-decision="RECHAZAR">Rechazar</button>' +
                    '<button type="button" class="rsc-suggestion-action rsc-suggestion-action--catalog" data-decision="APROBAR">Agregar al catálogo</button>' +
                    (sugerencia.tipo_solicitud === 'RUTINARIO' ? '' : '<button type="button" class="rsc-suggestion-action rsc-suggestion-action--recommend" data-decision="APROBAR_Y_RECOMENDAR">Agregar y recomendar</button>') +
                '</footer>' +
            '</article>';
        }).join('');
    }

    async function manejarSugerencia(evento) {
        var boton = evento.target.closest('[data-decision]');
        if (!boton || estado.guardando) {
            return;
        }

        var tarjeta = boton.closest('[data-sugerencia-id]');
        if (!tarjeta) {
            return;
        }

        var sugerenciaId = Number(tarjeta.getAttribute('data-sugerencia-id'));
        var decision = boton.getAttribute('data-decision') || '';
        var campo = tarjeta.querySelector('[data-sugerencia-observacion]');
        var observaciones = campo ? campo.value.trim() : '';

        if (decision === 'RECHAZAR' && observaciones.length < 5) {
            toast('Escribe una razón breve antes de rechazar.', 'error');
            if (campo) campo.focus();
            return;
        }

        var titulo = decision === 'RECHAZAR'
            ? '¿Rechazar esta sugerencia?'
            : (decision === 'APROBAR_Y_RECOMENDAR'
                ? '¿Agregarla al catálogo y recomendarla?'
                : '¿Agregarla al catálogo?');
        var mensaje = decision === 'APROBAR_Y_RECOMENDAR'
            ? 'Además de convertirla en recurso oficial, se agregará a la recomendación vigente de ese equipo y tipo de mantenimiento.'
            : (decision === 'RECHAZAR'
                ? 'La propuesta quedará conservada como rechazada en el historial administrativo.'
                : 'La propuesta se convertirá en recurso oficial y el historial del cierre quedará vinculado al catálogo.');
        var aceptado = await confirmar(titulo, mensaje, decision === 'RECHAZAR' ? 'Rechazar' : 'Confirmar', decision === 'RECHAZAR');
        if (!aceptado) return;

        var datos = new FormData();
        datos.append('accion', 'ATENDER_SUGERENCIA');
        datos.append('csrf_token', CSRF_TOKEN);
        datos.append('sugerencia_id', String(sugerenciaId));
        datos.append('decision', decision);
        datos.append('observaciones', observaciones);

        estado.guardando = true;
        bloquearBoton(boton, true, 'Procesando...');
        try {
            var respuesta = await solicitar(ENDPOINT, {method: 'POST', body: datos});
            toast(respuesta.mensaje || 'Sugerencia atendida.', 'success');
            await cargarRecursos(false);
        } catch (error) {
            toast(error.message || 'No fue posible atender la sugerencia.', 'error');
        } finally {
            estado.guardando = false;
            bloquearBoton(boton, false);
        }
    }

    function cambiarTipo(tipo) {
        estado.tipo = ['TODOS', 'HERRAMIENTA', 'REFACCION'].indexOf(tipo) >= 0
            ? tipo
            : 'TODOS';
        estado.pagina = 1;

        ui.tabsTipo.forEach(function (tab) {
            var activo = tab.getAttribute('data-tipo') === estado.tipo;
            tab.classList.toggle('is-active', activo);
            tab.setAttribute('aria-pressed', activo ? 'true' : 'false');
        });

        aplicarFiltros();
    }

    function aplicarFiltros() {
        var busqueda = normalizarBusqueda(ui.filtroBusqueda.value);
        var filtroEstado = ui.filtroEstado.value;
        var filtroUso = ui.filtroUso.value;

        estado.filtrados = estado.recursos.filter(function (recurso) {
            if (estado.tipo !== 'TODOS' && recurso.tipo_recurso !== estado.tipo) {
                return false;
            }

            var activo = Number(recurso.activo) === 1;
            if (filtroEstado === 'ACTIVOS' && !activo) {
                return false;
            }
            if (filtroEstado === 'INACTIVOS' && activo) {
                return false;
            }

            var enUso = Number(recurso.total_usos) > 0;
            if (filtroUso === 'EN_USO' && !enUso) {
                return false;
            }
            if (filtroUso === 'SIN_USO' && enUso) {
                return false;
            }

            if (busqueda === '') {
                return true;
            }

            var contenido = normalizarBusqueda([
                recurso.nombre || '',
                recurso.codigo || '',
                recurso.descripcion || '',
                recurso.tipo_recurso || ''
            ].join(' '));

            return contenido.indexOf(busqueda) !== -1;
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

        var inicio = estado.cantidad === 0 ? 0 : (estado.pagina - 1) * estado.cantidad;
        var fin = estado.cantidad === 0 ? total : Math.min(inicio + estado.cantidad, total);
        var visibles = estado.cantidad === 0
            ? estado.filtrados
            : estado.filtrados.slice(inicio, fin);

        ui.tablaRecursos.innerHTML = visibles.map(crearFila).join('');
        ui.estadoVacio.hidden = total !== 0 || estado.cargando;
        ui.contenedorTabla.hidden = total === 0 || estado.cargando;
        ui.paginacion.hidden = total === 0 || estado.cargando;
        ui.textoResultados.textContent = total === 1
            ? '1 recurso coincide con los filtros.'
            : total + ' recursos coinciden con los filtros.';

        ui.textoPaginacion.textContent = total === 0
            ? 'Sin resultados'
            : 'Mostrando ' + (inicio + 1) + ' a ' + fin + ' de ' + total;
        ui.paginaActual.textContent = 'Página ' + estado.pagina + ' de ' + paginas;
        ui.btnAnterior.disabled = estado.pagina <= 1;
        ui.btnSiguiente.disabled = estado.pagina >= paginas;
    }

    function crearFila(recurso) {
        var id = Number(recurso.id);
        var activo = Number(recurso.activo) === 1;
        var herramienta = recurso.tipo_recurso === 'HERRAMIENTA';
        var accionEstado = activo ? 'desactivar' : 'reactivar';
        var textoEstado = activo ? 'Desactivar' : 'Reactivar';
        var claseEstado = activo ? 'rsc-action--danger' : 'rsc-action--success';
        var descripcion = recurso.descripcion || 'Sin descripción adicional';
        var codigo = recurso.codigo
            ? '<span class="rsc-code">' + escapeHtml(recurso.codigo) + '</span>'
            : '<span class="rsc-code rsc-code--empty">Sin código</span>';

        return '<tr>' +
            '<td><div class="rsc-resource-title">' +
                '<span class="rsc-resource-title__icon"><svg><use href="#' +
                    (herramienta ? 'rsc-icon-tool' : 'rsc-icon-part') + '"></use></svg></span>' +
                '<div><strong>' + escapeHtml(recurso.nombre || '') + '</strong>' +
                    '<small>Actualizado ' + escapeHtml(recurso.fecha_actualizacion_texto || '—') + '</small></div>' +
            '</div></td>' +
            '<td><span class="rsc-kind ' + (herramienta ? 'rsc-kind--tool' : 'rsc-kind--part') + '">' +
                '<svg><use href="#' + (herramienta ? 'rsc-icon-tool' : 'rsc-icon-part') + '"></use></svg>' +
                (herramienta ? 'Herramienta' : 'Refacción') + '</span></td>' +
            '<td>' + codigo + '<p class="rsc-description">' + escapeHtml(descripcion) + '</p></td>' +
            '<td>' + crearUso(recurso) + '</td>' +
            '<td><span class="rsc-badge ' + (activo ? 'rsc-badge--active' : 'rsc-badge--inactive') + '">' +
                (activo ? 'Activo' : 'Inactivo') + '</span></td>' +
            '<td><div class="rsc-actions">' +
                '<button type="button" class="rsc-action" data-action="editar" data-id="' + id + '">Editar</button>' +
                '<button type="button" class="rsc-action ' + claseEstado + '" data-action="' + accionEstado + '" data-id="' + id + '">' + textoEstado + '</button>' +
            '</div></td>' +
        '</tr>';
    }

    function crearUso(recurso) {
        var usos = [
            ['Recomendaciones', recurso.usos_recomendaciones],
            ['Solicitudes', recurso.usos_solicitudes],
            ['Rutinas', recurso.usos_rutinas],
            ['Cierres', recurso.usos_cierres],
            ['Sugerencias', recurso.usos_sugerencias]
        ].filter(function (item) {
            return Number(item[1]) > 0;
        });

        if (usos.length === 0) {
            return '<span class="rsc-no-use">Todavía sin relaciones</span>';
        }

        return '<div class="rsc-use-list">' + usos.map(function (item) {
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
            toast('No se pudo identificar el recurso.', 'error');
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
        estado.recursoEditado = null;
        ui.etiquetaModal.textContent = 'NUEVO REGISTRO';
        ui.tituloModal.textContent = 'Nueva herramienta o refacción';
        ui.subtituloModal.textContent = 'Agrega un recurso al catálogo técnico central.';
        ui.btnGuardar.textContent = 'Guardar recurso';
        actualizarVistaCodigoAutomatico();
        abrirModal();
    }

    function abrirEdicion(id) {
        var recurso = estado.recursos.find(function (item) {
            return Number(item.id) === id;
        });

        if (!recurso) {
            toast('El recurso ya no está disponible.', 'error');
            return;
        }

        limpiarFormulario();
        estado.recursoEditado = recurso;
        ui.recursoId.value = String(id);
        ui.tipoRecurso.value = recurso.tipo_recurso || '';
        ui.tipoRecurso.setAttribute('data-original', recurso.tipo_recurso || '');
        ui.codigo.value = recurso.codigo || '';
        ui.nombre.value = recurso.nombre || '';
        ui.descripcion.value = recurso.descripcion || '';
        ui.etiquetaModal.textContent = 'EDICIÓN';
        ui.tituloModal.textContent = 'Editar recurso';
        ui.subtituloModal.textContent = 'Corrige los datos sin eliminar sus relaciones existentes.';
        ui.btnGuardar.textContent = 'Actualizar recurso';

        var usos = Number(recurso.total_usos) || 0;
        ui.avisoRecursoEnUso.hidden = usos === 0;
        ui.textoAvisoRecursoEnUso.textContent = usos === 1
            ? 'Tiene 1 relación registrada. Puedes corregir sus datos, pero no cambiar su tipo.'
            : 'Tiene ' + usos + ' relaciones registradas. Puedes corregir sus datos, pero no cambiar su tipo.';

        actualizarContadores();
        abrirModal();
    }

    function abrirModal() {
        ui.modalRecurso.hidden = false;
        document.body.classList.add('rsc-modal-open');
        window.setTimeout(function () {
            (ui.tipoRecurso.value ? ui.nombre : ui.tipoRecurso).focus();
        }, 40);
    }

    function cerrarModal() {
        if (estado.guardando) {
            return;
        }
        cerrarModalForzado();
    }

    function cerrarModalForzado() {
        ui.modalRecurso.hidden = true;
        if (ui.modalConfirmacion.hidden) {
            document.body.classList.remove('rsc-modal-open');
        }
        limpiarFormulario();
    }

    function limpiarFormulario() {
        ui.formRecurso.reset();
        ui.recursoId.value = '';
        ui.tipoRecurso.removeAttribute('data-original');
        ui.avisoRecursoEnUso.hidden = true;
        ui.errorTipoRecurso.textContent = '';
        ui.errorCodigo.textContent = '';
        ui.errorNombre.textContent = '';
        ui.errorDescripcion.textContent = '';
        estado.recursoEditado = null;
        actualizarVistaCodigoAutomatico();
        actualizarContadores();
    }

    async function guardarRecurso(evento) {
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
            var formulario = new FormData(ui.formRecurso);
            formulario.set('accion', 'GUARDAR');
            var respuesta = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            cerrarModalForzado();
            await cargarRecursos(false);
            toast(respuesta.mensaje || 'Recurso guardado correctamente.', 'success');
        } catch (error) {
            marcarErrorServidor(error);
            toast(error.message || 'No fue posible guardar el recurso.', 'error');
        } finally {
            estado.guardando = false;
            bloquearBoton(ui.btnGuardar, false);
        }
    }

    async function cambiarEstado(id, reactivar, boton) {
        var recurso = estado.recursos.find(function (item) {
            return Number(item.id) === id;
        });

        if (!recurso) {
            toast('El recurso ya no está disponible.', 'error');
            return;
        }

        var usos = Number(recurso.total_usos) || 0;
        var textoDesactivar = usos > 0
            ? 'Dejará de aparecer en nuevas selecciones, pero sus ' + usos + ' relaciones y todo el historial se conservarán.'
            : 'Dejará de aparecer en nuevas selecciones. Podrás reactivarlo posteriormente.';

        var confirmado = await confirmar(
            reactivar ? '¿Reactivar recurso?' : '¿Desactivar recurso?',
            reactivar
                ? 'Volverá a estar disponible en los buscadores de herramientas o refacciones.'
                : textoDesactivar,
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

            await cargarRecursos(false);
            toast(respuesta.mensaje || 'Estado actualizado.', 'success');
        } catch (error) {
            toast(error.message || 'No fue posible cambiar el estado.', 'error');
        } finally {
            bloquearBoton(boton, false);
        }
    }

    function validarFormulario() {
        var valido = true;
        var tipo = ui.tipoRecurso.value;
        var codigo = ui.codigo.value.trim();
        var nombre = ui.nombre.value.trim();
        var descripcion = ui.descripcion.value.trim();

        limpiarErrores();

        if (['HERRAMIENTA', 'REFACCION'].indexOf(tipo) === -1) {
            ui.errorTipoRecurso.textContent = 'Selecciona herramienta o refacción.';
            valido = false;
        }

        if (nombre.length < 2 || nombre.length > 150) {
            ui.errorNombre.textContent = 'Escribe un nombre de 2 a 150 caracteres.';
            valido = false;
        }

        if (/[<>\r\n]/.test(nombre)) {
            ui.errorNombre.textContent = 'El nombre contiene caracteres no permitidos.';
            valido = false;
        }

        if (descripcion.length > 500) {
            ui.errorDescripcion.textContent = 'La descripción no puede superar 500 caracteres.';
            valido = false;
        }

        if (!validarCambioTipoEnUso()) {
            valido = false;
        }

        if (!valido) {
            primerCampoConError().focus();
        }

        return valido;
    }

    function validarCambioTipoEnUso() {
        if (!estado.recursoEditado || Number(estado.recursoEditado.total_usos) === 0) {
            return true;
        }

        if (ui.tipoRecurso.value !== estado.recursoEditado.tipo_recurso) {
            ui.errorTipoRecurso.textContent = 'No se puede cambiar el tipo de un recurso que ya tiene relaciones.';
            return false;
        }

        ui.errorTipoRecurso.textContent = '';
        return true;
    }

    function limpiarErrores() {
        ui.errorTipoRecurso.textContent = '';
        ui.errorCodigo.textContent = '';
        ui.errorNombre.textContent = '';
        ui.errorDescripcion.textContent = '';
    }

    function primerCampoConError() {
        if (ui.errorTipoRecurso.textContent) {
            return ui.tipoRecurso;
        }
        if (ui.errorCodigo.textContent) {
            return ui.codigo;
        }
        if (ui.errorNombre.textContent) {
            return ui.nombre;
        }
        return ui.descripcion;
    }

    function marcarErrorServidor(error) {
        var campo = error && error.datos ? error.datos.campo : '';
        var destino = {
            tipo_recurso: [ui.errorTipoRecurso, ui.tipoRecurso],
            codigo: [ui.errorCodigo, ui.codigo],
            nombre: [ui.errorNombre, ui.nombre],
            descripcion: [ui.errorDescripcion, ui.descripcion]
        }[campo];

        if (destino) {
            destino[0].textContent = error.message || 'Revisa este campo.';
            destino[1].focus();
        }
    }

    function limpiarFiltros() {
        ui.filtroBusqueda.value = '';
        ui.filtroEstado.value = 'TODOS';
        ui.filtroUso.value = 'TODOS';
        ui.filtroCantidad.value = '10';
        estado.cantidad = 10;
        cambiarTipo('TODOS');
        ui.filtroBusqueda.focus();
    }

    function pintarResumen(resumen) {
        ui.kpiTotal.textContent = Number(resumen.total) || 0;
        ui.kpiHerramientas.textContent = Number(resumen.herramientas) || 0;
        ui.kpiRefacciones.textContent = Number(resumen.refacciones) || 0;
        ui.kpiActivos.textContent = Number(resumen.activos) || 0;
        ui.kpiInactivos.textContent = Number(resumen.inactivos) || 0;

        var enUso = Number(resumen.en_uso) || 0;
        ui.resumenEnUso.textContent = enUso === 1
            ? '1 recurso vinculado'
            : enUso + ' recursos vinculados';
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
        ui.estadoPagina.className = 'rsc-status';
        if (tipo === 'error') {
            ui.estadoPagina.classList.add('rsc-status--error');
        } else if (tipo === 'success') {
            ui.estadoPagina.classList.add('rsc-status--success');
        }
    }

    function actualizarContadores() {
        ui.contadorCodigo.textContent = estado.recursoEditado
            ? 'Código asignado'
            : (ui.codigo.value ? 'Siguiente consecutivo' : 'Automático');
        ui.contadorNombre.textContent = ui.nombre.value.length + '/150';
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
                ? 'rsc-btn rsc-btn--danger'
                : 'rsc-btn rsc-btn--primary';
            ui.modalConfirmacion.classList.toggle('rsc-modal--danger', Boolean(peligro));
            ui.modalConfirmacion.hidden = false;
            document.body.classList.add('rsc-modal-open');

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
        ui.modalConfirmacion.classList.remove('rsc-modal--danger');

        if (ui.modalRecurso.hidden) {
            document.body.classList.remove('rsc-modal-open');
        }

        var resolver = estado.resolverConfirmacion;
        estado.resolverConfirmacion = null;

        if (typeof resolver === 'function') {
            resolver(Boolean(resultado));
        }
    }

    function toast(mensaje, tipo) {
        ui.toast.textContent = mensaje;
        ui.toast.className = 'rsc-toast rsc-toast--' + (tipo || 'info');
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
            if (!boton.hasAttribute('data-texto-original')) {
                boton.setAttribute('data-texto-original', boton.textContent.trim());
            }
            boton.disabled = true;
            if (texto) {
                boton.textContent = texto;
            }
            return;
        }

        boton.disabled = false;
        var original = boton.getAttribute('data-texto-original');
        if (original !== null) {
            boton.textContent = original;
            boton.removeAttribute('data-texto-original');
        }
    }

    function limpiarEspacios(texto) {
        return String(texto || '').replace(/[\t ]+/g, ' ').trim();
    }

    function normalizarBusqueda(texto) {
        var valor = String(texto || '').toLocaleLowerCase('es-MX');
        if (typeof valor.normalize === 'function') {
            valor = valor.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return valor.trim();
    }

    function formatearFechaHora(valor) {
        if (!valor) return 'Fecha no disponible';
        var fecha = new Date(String(valor).replace(' ', 'T'));
        if (Number.isNaN(fecha.getTime())) return String(valor);
        try {
            return fecha.toLocaleString('es-MX', {dateStyle: 'medium', timeStyle: 'short'});
        } catch (e) {
            return String(valor);
        }
    }

    function formatearHora(fecha) {
        try {
            return fecha.toLocaleTimeString('es-MX', {
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return 'ahora';
        }
    }

    function escapeHtml(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
</script>
</body>
</html>
