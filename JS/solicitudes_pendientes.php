<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/seguridad.php';

sm_requerir_sesion(['ADMIN'], false);

$cssSolicitudesPendientes = __DIR__ . '/../css/style_solicitudes_pendientes.css';
$versionCssSolicitudesPendientes = file_exists($cssSolicitudesPendientes)
    ? (string) filemtime($cssSolicitudesPendientes)
    : (string) time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <meta name="theme-color" content="#09233b">
    <meta name="description" content="Bandeja administrativa para revisar solicitudes de mantenimiento">
    <title>Solicitudes pendientes | Sistema de Mantenimiento</title>
    <link
        rel="stylesheet"
        href="../css/style_solicitudes_pendientes.css?v=<?= htmlspecialchars($versionCssSolicitudesPendientes, ENT_QUOTES, 'UTF-8') ?>"
    >
</head>
<body>

<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="spen-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="spen-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.2 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.8 15"/>
    </symbol>
    <symbol id="spen-icon-review" viewBox="0 0 24 24">
        <path d="M9 5h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9"/>
        <path d="M3 9h6V3M3 9l6-6M8 14h8M8 18h5"/>
    </symbol>
    <symbol id="spen-icon-inbox" viewBox="0 0 24 24">
        <path d="M4 4h16l2 9v7H2v-7l2-9Z"/>
        <path d="M2 13h5l2 3h6l2-3h5"/>
    </symbol>
    <symbol id="spen-icon-bolt" viewBox="0 0 24 24">
        <path d="m13 2-9 12h8l-1 8 9-12h-8l1-8Z"/>
    </symbol>
    <symbol id="spen-icon-alert" viewBox="0 0 24 24">
        <path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
        <path d="M12 9v4M12 17h.01"/>
    </symbol>
    <symbol id="spen-icon-calendar" viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path d="M16 3v4M8 3v4M3 10h18"/>
    </symbol>
    <symbol id="spen-icon-tools" viewBox="0 0 24 24">
        <path d="M14.7 6.3a4 4 0 0 0-5-5L7.4 3.6l3 3 2.3-2.3a4 4 0 0 0 2 2Z"/>
        <path d="m10.3 6.7-8.6 8.6a2.1 2.1 0 0 0 3 3l8.6-8.6"/>
    </symbol>
    <symbol id="spen-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="spen-icon-filter" viewBox="0 0 24 24">
        <path d="M4 5h16M7 12h10M10 19h4"/>
    </symbol>
    <symbol id="spen-icon-chevron" viewBox="0 0 24 24">
        <path d="m9 18 6-6-6-6"/>
    </symbol>
    <symbol id="spen-icon-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </symbol>
    <symbol id="spen-icon-list" viewBox="0 0 24 24">
        <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
    </symbol>
    <symbol id="spen-icon-close" viewBox="0 0 24 24">
        <path d="m6 6 12 12M18 6 6 18"/>
    </symbol>
</svg>

<main class="contenido-principal spen-page">
    <section class="spen-hero" aria-labelledby="tituloSolicitudesPendientes">
        <div class="spen-hero__pattern" aria-hidden="true"></div>
        <div class="spen-hero__content">
            <div class="spen-hero__copy">
                <span class="spen-kicker">
                    <span class="spen-kicker__icon"><svg><use href="#spen-icon-review"></use></svg></span>
                    Revisión administrativa
                </span>
                <h1 id="tituloSolicitudesPendientes">Solicitudes <span>pendientes</span></h1>
                <p>
                    Revisa, corrige y procesa las solicitudes que requieren atención administrativa.
                    Las urgencias activas únicamente se validan sin interrumpir su operación.
                </p>
                <div class="spen-hero__meta">
                    <span><i aria-hidden="true"></i> Bandeja operativa en tiempo real</span>
                    <span id="spenFechaHero"></span>
                </div>
            </div>

            <div class="spen-hero__actions">
                <div class="spen-hero__mini">
                    <span><svg><use href="#spen-icon-list"></use></svg></span>
                    <div>
                        <small>Módulo actual</small>
                        <strong>Bandeja de revisión</strong>
                    </div>
                </div>

                <button type="button" class="spen-btn spen-btn--primario" id="btnActualizarSolicitudes">
                    <span class="spen-btn__icon"><svg><use href="#spen-icon-refresh"></use></svg></span>
                    <span class="spen-btn__text">Actualizar datos</span>
                    <span class="spen-btn__loader" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </section>

    <section class="spen-resumen" aria-label="Resumen de solicitudes pendientes">
        <article class="spen-resumen__card spen-resumen__card--total">
            <span class="spen-resumen__icon"><svg><use href="#spen-icon-inbox"></use></svg></span>
            <div>
                <span>Total pendientes</span>
                <strong id="resumenTotal">0</strong>
                <small>Registros en la bandeja</small>
            </div>
        </article>

        <article class="spen-resumen__card spen-resumen__card--urgente spen-resumen--urgente">
            <span class="spen-resumen__icon"><svg><use href="#spen-icon-bolt"></use></svg></span>
            <div>
                <span>Urgentes por revisar</span>
                <strong id="resumenUrgentes">0</strong>
                <small>Requieren control administrativo</small>
            </div>
        </article>

        <article class="spen-resumen__card spen-resumen__card--alta">
            <span class="spen-resumen__icon"><svg><use href="#spen-icon-alert"></use></svg></span>
            <div>
                <span>Prioridad alta</span>
                <strong id="resumenAltas">0</strong>
                <small>Alta o urgente</small>
            </div>
        </article>

        <article class="spen-resumen__card spen-resumen__card--programable">
            <span class="spen-resumen__icon"><svg><use href="#spen-icon-calendar"></use></svg></span>
            <div>
                <span>Programables</span>
                <strong id="resumenProgramables">0</strong>
                <small>Correctivos por aprobar</small>
            </div>
        </article>

        <article class="spen-resumen__card spen-resumen__card--mejora">
            <span class="spen-resumen__icon"><svg><use href="#spen-icon-tools"></use></svg></span>
            <div>
                <span>Mejoras</span>
                <strong id="resumenMejoras">0</strong>
                <small>Modificaciones registradas</small>
            </div>
        </article>
    </section>

    <section class="spen-panel">
        <header class="spen-panel__cabecera">
            <div class="spen-panel__titulo">
                <span class="spen-panel__icon"><svg><use href="#spen-icon-review"></use></svg></span>
                <div>
                    <span class="spen-panel__kicker">Control de solicitudes</span>
                    <h2>Bandeja de revisión</h2>
                    <p>Abre un registro para consultar, corregir, aprobar o rechazar su información.</p>
                </div>
            </div>

            <div class="spen-panel__actualizacion">
                <span class="spen-panel__actualizacion-icon"><svg><use href="#spen-icon-clock"></use></svg></span>
                <div>
                    <small>Última actualización</small>
                    <strong id="fechaActualizacion">Sin actualizar</strong>
                </div>
            </div>
        </header>

        <div class="spen-filtros">
            <div class="spen-filtros__heading">
                <span><svg><use href="#spen-icon-filter"></use></svg></span>
                <div>
                    <strong>Filtros de búsqueda</strong>
                    <small>Los resultados se actualizan sin recargar la página.</small>
                </div>
            </div>

            <div class="spen-filtros__grid">
                <label class="spen-filtro spen-filtro--buscar">
                    <span>Buscar solicitud</span>
                    <span class="spen-input-wrap">
                        <svg aria-hidden="true"><use href="#spen-icon-search"></use></svg>
                        <input
                            type="search"
                            id="filtroBusqueda"
                            maxlength="120"
                            placeholder="Folio, solicitante, código o equipo"
                            autocomplete="off"
                        >
                    </span>
                </label>

                <label class="spen-filtro">
                    <span>Tipo</span>
                    <select id="filtroTipo">
                        <option value="">Todos los tipos</option>
                        <option value="CORRECTIVO_PROGRAMABLE">Correctivo programable</option>
                        <option value="MODIFICACION_MEJORA">Modificación o mejora</option>
                        <option value="CORRECTIVO_URGENTE">Correctivo urgente</option>
                    </select>
                </label>

                <label class="spen-filtro">
                    <span>Prioridad</span>
                    <select id="filtroPrioridad">
                        <option value="">Todas las prioridades</option>
                        <option value="URGENTE">Urgente</option>
                        <option value="ALTA">Alta</option>
                        <option value="MEDIA">Media</option>
                        <option value="BAJA">Baja</option>
                    </select>
                </label>

                <label class="spen-filtro">
                    <span>Tiempo de espera</span>
                    <select id="filtroEspera">
                        <option value="">Cualquier tiempo</option>
                        <option value="120">2 horas o más</option>
                        <option value="240">4 horas o más</option>
                        <option value="480">8 horas o más</option>
                    </select>
                </label>

                <button type="button" class="spen-btn spen-btn--secundario" id="btnLimpiarFiltros">
                    <span class="spen-btn__icon"><svg><use href="#spen-icon-close"></use></svg></span>
                    <span class="spen-btn__text">Limpiar</span>
                </button>
            </div>
        </div>

        <div class="spen-estado" id="estadoCargando">
            <span class="spen-spinner" aria-hidden="true"></span>
            <div>
                <strong>Cargando solicitudes...</strong>
                <p>Consultando la información del sistema.</p>
            </div>
        </div>

        <div class="spen-estado" id="estadoVacio" hidden>
            <span class="spen-estado__icon"><svg><use href="#spen-icon-inbox"></use></svg></span>
            <div>
                <strong>No hay solicitudes para revisar</strong>
                <p>No existen registros que coincidan con los filtros actuales.</p>
            </div>
        </div>

        <div class="spen-estado spen-estado--error" id="estadoError" hidden>
            <span class="spen-estado__icon"><svg><use href="#spen-icon-alert"></use></svg></span>
            <div>
                <strong>No fue posible cargar la bandeja</strong>
                <p id="textoErrorLista">Ocurrió un error inesperado.</p>
            </div>
        </div>

        <div class="spen-tabla-contenedor" id="contenedorTabla" hidden>
            <div class="spen-tabla-meta">
                <div>
                    <strong id="paginacionInfo">0 registros</strong>
                    <span>Mostrando únicamente la página actual para mantener una carga rápida.</span>
                </div>
                <label>
                    <span>Filas por página</span>
                    <select id="selectorPorPagina">
                        <option value="10">10</option>
                        <option value="15" selected>15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>

            <div class="spen-tabla-scroll">
                <table id="tablaSolicitudesPendientes" class="spen-tabla">
                    <colgroup>
                        <col class="spen-col spen-col--folio">
                        <col class="spen-col spen-col--tipo">
                        <col class="spen-col spen-col--prioridad">
                        <col class="spen-col spen-col--solicitante">
                        <col class="spen-col spen-col--equipo">
                        <col class="spen-col spen-col--fecha">
                        <col class="spen-col spen-col--revision">
                        <col class="spen-col spen-col--accion">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col" class="spen-th spen-th--folio">
                                <button type="button" class="spen-sort" data-sort="folio">
                                    <span>Folio</span><i aria-hidden="true"></i>
                                </button>
                            </th>
                            <th scope="col" class="spen-th spen-th--tipo">
                                <button type="button" class="spen-sort" data-sort="tipo_solicitud">
                                    <span>Tipo</span><i aria-hidden="true"></i>
                                </button>
                            </th>
                            <th scope="col" class="spen-th spen-th--prioridad">
                                <button type="button" class="spen-sort" data-sort="prioridad">
                                    <span>Prioridad</span><i aria-hidden="true"></i>
                                </button>
                            </th>
                            <th scope="col" class="spen-th spen-th--solicitante">
                                <button type="button" class="spen-sort" data-sort="solicitante">
                                    <span>Solicitante</span><i aria-hidden="true"></i>
                                </button>
                            </th>
                            <th scope="col" class="spen-th spen-th--equipo">
                                <button type="button" class="spen-sort" data-sort="equipo">
                                    <span>Equipo y ubicación</span><i aria-hidden="true"></i>
                                </button>
                            </th>
                            <th scope="col" class="spen-th spen-th--fecha">
                                <button type="button" class="spen-sort" data-sort="fecha">
                                    <span>Fecha y espera</span><i aria-hidden="true"></i>
                                </button>
                            </th>
                            <th scope="col" class="spen-th spen-th--revision">
                                <button type="button" class="spen-sort" data-sort="tipo_revision">
                                    <span>Estado de revisión</span><i aria-hidden="true"></i>
                                </button>
                            </th>
                            <th scope="col" class="spen-th spen-th--accion spen-tabla__accion">
                                <span class="spen-tabla__titulo-estatico">Acción</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoTablaSolicitudes"></tbody>
                </table>
            </div>

            <nav class="spen-paginacion" id="paginacionSolicitudes" aria-label="Paginación de solicitudes">
                <button type="button" class="spen-paginacion__nav" id="paginaAnterior">
                    <span aria-hidden="true">‹</span> Anterior
                </button>
                <div class="spen-paginacion__numeros" id="paginacionNumeros"></div>
                <button type="button" class="spen-paginacion__nav" id="paginaSiguiente">
                    Siguiente <span aria-hidden="true">›</span>
                </button>
            </nav>
        </div>
    </section>

    <footer class="spen-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Solicitudes pendientes · Los Chapeteados División Petfood</span>
    </footer>
</main>

<div
    class="spen-modal"
    id="modalSolicitud"
    role="dialog"
    aria-modal="true"
    aria-labelledby="tituloModalSolicitud"
    hidden
>
    <div class="spen-modal__dialogo">
        <header class="spen-modal__cabecera">
            <div>
                <span id="etiquetaModalSolicitud">Solicitud</span>
                <h2 id="tituloModalSolicitud">Revisar solicitud</h2>
                <p id="subtituloModalSolicitud">Verifica y corrige la información.</p>
            </div>

            <button
                type="button"
                class="spen-modal__cerrar"
                id="btnCerrarModal"
                aria-label="Cerrar"
            >
                ×
            </button>
        </header>

        <div class="spen-modal__cuerpo">
            <div class="spen-cargando-detalle" id="cargandoDetalle">
                <span class="spen-spinner" aria-hidden="true"></span>
                Cargando detalle...
            </div>

            <form id="formSolicitud" hidden novalidate>
                <input type="hidden" id="solicitud_id" name="id">
                <input type="hidden" id="tipo_solicitud" name="tipo_solicitud">

                <aside class="spen-aviso-revision-urgente" id="avisoRevisionUrgente" hidden>
                    <span class="spen-aviso-revision-urgente__icono" aria-hidden="true">!</span>
                    <div>
                        <strong>Esta urgencia ya está publicada.</strong>
                        <p>
                            Los técnicos pueden verla, aceptarla o estar atendiéndola.
                            Marcarla como revisada solamente registra el control administrativo;
                            no autoriza, detiene, reinicia ni finaliza la urgencia.
                        </p>
                    </div>
                </aside>

                <section class="spen-seccion spen-seccion--resumen">
                    <header>
                        <h3>Información de control</h3>
                        <p>Estos datos no cambian el contenido técnico de la solicitud.</p>
                    </header>

                    <div class="spen-datos-control">
                        <div>
                            <span>Folio</span>
                            <strong id="datoFolio">—</strong>
                        </div>
                        <div>
                            <span>Tipo</span>
                            <strong id="datoTipo">—</strong>
                        </div>
                        <div>
                            <span>Solicitante</span>
                            <strong id="datoSolicitante">—</strong>
                        </div>
                        <div>
                            <span>Registrada</span>
                            <strong id="datoFechaRegistro">—</strong>
                        </div>
                    </div>
                </section>

                <section class="spen-seccion">
                    <header>
                        <h3>Ubicación y equipo</h3>
                        <p>Los cuatro datos deben corresponder entre sí.</p>
                    </header>

                    <div class="spen-grid">
                        <label class="spen-campo">
                            <span>Departamento *</span>
                            <select id="departamento_id" name="departamento_id" required>
                                <option value="">Selecciona</option>
                            </select>
                            <small class="spen-error-campo"></small>
                        </label>

                        <label class="spen-campo">
                            <span>Área *</span>
                            <select id="area_id" name="area_id" required disabled>
                                <option value="">Selecciona</option>
                            </select>
                            <small class="spen-error-campo"></small>
                        </label>

                        <label class="spen-campo">
                            <span>Proceso *</span>
                            <select id="proceso_id" name="proceso_id" required disabled>
                                <option value="">Selecciona</option>
                            </select>
                            <small class="spen-error-campo"></small>
                        </label>

                        <label class="spen-campo">
                            <span>Equipo *</span>
                            <select id="equipo_id" name="equipo_id" required disabled>
                                <option value="">Selecciona</option>
                            </select>
                            <small class="spen-error-campo"></small>
                        </label>
                    </div>
                </section>

                <section class="spen-seccion">
                    <header>
                        <h3>Solicitud</h3>
                        <p>Información principal que recibirá el técnico.</p>
                    </header>

                    <div class="spen-grid">
                        <label class="spen-campo">
                            <span>Prioridad *</span>
                            <select id="prioridad" name="prioridad" required>
                                <option value="BAJA">Baja</option>
                                <option value="MEDIA">Media</option>
                                <option value="ALTA">Alta</option>
                                <option value="URGENTE">Urgente</option>
                            </select>
                            <small class="spen-error-campo"></small>
                        </label>

                        <label class="spen-campo">
                            <span>Fecha sugerida</span>
                            <input type="date" id="fecha_sugerida" name="fecha_sugerida">
                            <small class="spen-error-campo"></small>
                        </label>

                        <label class="spen-campo spen-campo--ancho">
                            <span>Descripción de la solicitud *</span>
                            <textarea
                                id="descripcion_solicitud"
                                name="descripcion_solicitud"
                                rows="5"
                                minlength="10"
                                maxlength="3000"
                                required
                            ></textarea>
                            <small class="spen-ayuda">Describe qué sucede y qué necesita realizarse.</small>
                            <small class="spen-error-campo"></small>
                        </label>
                    </div>
                </section>

                <section class="spen-seccion spen-seccion--seguridad" id="seccionSeguridad">
                    <header>
                        <h3>Seguridad del mantenimiento</h3>
                        <p>El solicitante puede advertir el riesgo y el administrador puede corregirlo antes de continuar.</p>
                    </header>

                    <div class="spen-grid">
                        <label class="spen-campo">
                            <span>Nivel de riesgo *</span>
                            <select id="nivel_riesgo" name="nivel_riesgo">
                                <option value="BAJO">Bajo</option>
                                <option value="MEDIO">Medio</option>
                                <option value="ALTO">Alto</option>
                            </select>
                            <small class="spen-error-campo"></small>
                        </label>

                        <div class="spen-campo spen-campo--checks">
                            <span>Condiciones de atención</span>
                            <label>
                                <input type="checkbox" id="trabajo_peligroso" name="trabajo_peligroso" value="1">
                                Trabajo peligroso
                            </label>
                            <label>
                                <input type="checkbox" id="requiere_paro_equipo" name="requiere_paro_equipo" value="1">
                                Requiere paro del equipo
                            </label>
                        </div>

                        <label class="spen-campo spen-campo--ancho" id="campoDetallePeligro" hidden>
                            <span>Motivo o precaución del trabajo peligroso *</span>
                            <textarea
                                id="detalle_trabajo_peligroso"
                                name="detalle_trabajo_peligroso"
                                rows="2"
                                minlength="3"
                                maxlength="200"
                                placeholder="Ej. Trabajo en altura, carga pesada o riesgo eléctrico."
                            ></textarea>
                            <small class="spen-ayuda">Nota breve que verán el administrador y los técnicos.</small>
                            <small class="spen-error-campo"></small>
                        </label>
                    </div>
                </section>

                <section class="spen-seccion spen-seccion--urgencia" id="seccionUrgencia" hidden>
                    <header>
                        <h3>Información obligatoria de la urgencia</h3>
                        <p>La urgencia ya está publicada; puedes completar sus datos y recomendar recursos mientras no haya comenzado.</p>
                    </header>

                    <div class="spen-grid">
                        <label class="spen-campo spen-campo--ancho">
                            <span>Impacto en la operación *</span>
                            <textarea id="impacto_operacion" name="impacto_operacion" rows="3" maxlength="2000"></textarea>
                            <small class="spen-ayuda">Explica qué está detenido, afectado o en riesgo.</small>
                            <small class="spen-error-campo"></small>
                        </label>
                    </div>

                    <div class="spen-urgent-resources" id="panelRecursosUrgencia">
                        <div class="spen-urgent-resources__head">
                            <div>
                                <span class="spen-resource-source">RECOMENDACIÓN ADMINISTRATIVA</span>
                                <h4>Herramientas y refacciones sugeridas</h4>
                                <p>
                                    Puedes dejar ambas listas vacías. Si agregas o retiras recursos, esta selección
                                    se guardará para esta urgencia y para futuras urgencias del mismo equipo.
                                </p>
                            </div>
                            <span class="spen-resource-count" id="contadorRecursosUrgencia">0 seleccionados</span>
                        </div>

                        <div class="spen-resource-lock" id="avisoRecursosBloqueados" hidden>
                            El mantenimiento ya comenzó. Las recomendaciones permanecen visibles, pero ya no pueden modificarse.
                        </div>

                        <div class="spen-resource-grid">
                            <div class="spen-resource-picker" data-picker="HERRAMIENTA">
                                <label for="buscarHerramientaUrgencia">Buscar herramientas</label>
                                <div class="spen-resource-search">
                                    <input type="search" id="buscarHerramientaUrgencia" placeholder="Nombre, código o descripción" autocomplete="off">
                                    <div class="spen-resource-results" id="resultadosHerramientasUrgencia" hidden></div>
                                </div>
                                <div class="spen-resource-selected" id="seleccionHerramientasUrgencia"></div>
                            </div>

                            <div class="spen-resource-picker" data-picker="REFACCION">
                                <label for="buscarRefaccionUrgencia">Buscar refacciones</label>
                                <div class="spen-resource-search">
                                    <input type="search" id="buscarRefaccionUrgencia" placeholder="Nombre, código o descripción" autocomplete="off">
                                    <div class="spen-resource-results" id="resultadosRefaccionesUrgencia" hidden></div>
                                </div>
                                <div class="spen-resource-selected" id="seleccionRefaccionesUrgencia"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="spen-seccion" id="seccionDiagnostico">
                    <header>
                        <h3>Diagnóstico</h3>
                        <p id="textoDiagnostico">
                            En un correctivo programable puede dejarse incompleto si el solicitante no conoce la causa.
                        </p>
                    </header>

                    <div class="spen-grid">
                        <label class="spen-campo">
                            <span>Tipo de falla <b class="spen-obligatorio-urgente" hidden>*</b></span>
                            <select id="tipo_falla_id" name="tipo_falla_id">
                                <option value="">No especificado</option>
                            </select>
                            <small class="spen-error-campo"></small>
                        </label>

                        <label class="spen-campo">
                            <span>Causa de avería <b class="spen-obligatorio-urgente" hidden>*</b></span>
                            <select id="causa_averia_id" name="causa_averia_id">
                                <option value="">No especificada</option>
                            </select>
                            <small class="spen-error-campo"></small>
                        </label>

                        <label class="spen-campo spen-campo--ancho">
                            <span>Descripción adicional de la falla</span>
                            <textarea
                                id="descripcion_falla"
                                name="descripcion_falla"
                                rows="3"
                                maxlength="2000"
                            ></textarea>
                            <small class="spen-error-campo"></small>
                        </label>

                        <label class="spen-campo spen-campo--ancho">
                            <span>Explicación cuando la causa no es clara</span>
                            <textarea
                                id="causa_desconocida_descripcion"
                                name="causa_desconocida_descripcion"
                                rows="3"
                                maxlength="1500"
                            ></textarea>
                            <small class="spen-error-campo"></small>
                        </label>
                    </div>
                </section>

                <section class="spen-seccion" id="seccionMejora" hidden>
                    <header>
                        <h3>Modificación o mejora</h3>
                        <p>Solo el objetivo o el resultado esperado es indispensable.</p>
                    </header>

                    <div class="spen-grid">
                        <label class="spen-campo spen-campo--ancho">
                            <span>Objetivo de la mejora</span>
                            <textarea
                                id="objetivo_mejora"
                                name="objetivo_mejora"
                                rows="3"
                                maxlength="2000"
                            ></textarea>
                            <small class="spen-error-campo"></small>
                        </label>

                        <label class="spen-campo spen-campo--ancho">
                            <span>Resultado esperado</span>
                            <textarea
                                id="resultado_esperado"
                                name="resultado_esperado"
                                rows="3"
                                maxlength="2000"
                            ></textarea>
                            <small class="spen-error-campo"></small>
                        </label>

                        <label class="spen-campo spen-campo--ancho">
                            <span>Justificación</span>
                            <textarea
                                id="justificacion_mejora"
                                name="justificacion_mejora"
                                rows="3"
                                maxlength="2500"
                            ></textarea>
                            <small class="spen-error-campo"></small>
                        </label>

                        <label class="spen-campo spen-campo--ancho">
                            <span>Costo frente al beneficio</span>
                            <textarea
                                id="costo_vs_beneficio"
                                name="costo_vs_beneficio"
                                rows="3"
                                maxlength="2500"
                            ></textarea>
                            <small class="spen-error-campo"></small>
                        </label>

                        <fieldset class="spen-campo spen-campo--ancho spen-causas">
                            <legend>Causas relacionadas</legend>
                            <div id="contenedorCausasMejora">
                                <span class="spen-ayuda">No hay causas disponibles.</span>
                            </div>
                            <small class="spen-error-campo"></small>
                        </fieldset>
                    </div>
                </section>

                <section class="spen-seccion" id="seccionObservaciones">
                    <header>
                        <h3>Observaciones</h3>
                        <p>Información adicional proporcionada por el solicitante.</p>
                    </header>

                    <div class="spen-grid">
                        <label class="spen-campo spen-campo--ancho">
                            <span>Observaciones del solicitante</span>
                            <textarea
                                id="observaciones_solicitante"
                                name="observaciones_solicitante"
                                rows="3"
                                maxlength="2000"
                            ></textarea>
                            <small class="spen-error-campo"></small>
                        </label>
                    </div>
                </section>

                <section class="spen-seccion spen-seccion--motivo">
                    <header>
                        <h3>Motivo de la corrección</h3>
                        <p>Solo es obligatorio al guardar cambios. Se conservará en auditoría.</p>
                    </header>

                    <label class="spen-campo">
                        <span>Motivo de edición</span>
                        <textarea
                            id="motivo_edicion"
                            name="motivo_edicion"
                            rows="2"
                            minlength="10"
                            maxlength="500"
                            placeholder="Ejemplo: Se corrigió el equipo y se completó la descripción."
                        ></textarea>
                        <small class="spen-error-campo"></small>
                    </label>
                </section>
            </form>
        </div>

        <footer class="spen-modal__acciones" id="accionesModal" hidden>
            <div class="spen-modal__acciones-secundarias">
                <button
                    type="button"
                    class="spen-btn spen-btn--secundario"
                    id="btnCerrarSinCambios"
                >
                    Cerrar
                </button>

                <button
                    type="button"
                    class="spen-btn spen-btn--guardar"
                    id="btnGuardarCorrecciones"
                >
                    Guardar correcciones
                </button>
            </div>

            <div class="spen-modal__acciones-principales">
                <button
                    type="button"
                    class="spen-btn spen-btn--rechazar"
                    id="btnRechazarSolicitud"
                >
                    Rechazar
                </button>

                <button
                    type="button"
                    class="spen-btn spen-btn--aprobar"
                    id="btnAprobarSolicitud"
                >
                    Aprobar
                </button>
            </div>
        </footer>
    </div>
</div>

<?php include __DIR__ . '/../inc/alertas.php'; ?>

<script>
(function () {
    'use strict';

    var ENDPOINT = '../funciones/solicitudes_pendientes_funciones.php';
    var ENDPOINT_RECURSOS = 'recursos_mantenimiento.php?rsc_api=1';

    var estado = {
        solicitudes: [],
        catalogos: {
            departamentos: [],
            areas: [],
            procesos: [],
            equipos: [],
            tipos_falla: [],
            causas_averia: [],
            causas_mejora: []
        },
        solicitudActual: null,
        solicitudesFiltradas: [],
        paginaActual: 1,
        porPagina: 15,
        ordenCampo: '',
        ordenDireccion: 'asc',
        temporizadorBusqueda: null,
        cargandoCatalogos: false,
        catalogosCargados: false,
        formularioSucio: false,
        guardando: false,
        ultimoFoco: null,
        solicitudInicialProcesada: false,
        recursosUrgencia: [],
        recursosEditables: true,
        temporizadorRecursos: {HERRAMIENTA: null, REFACCION: null}
    };

    var elementos = {};

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        capturarElementos();
        registrarEventos();
        pintarFechaHero();
        Promise.all([
            cargarCatalogos(),
            cargarSolicitudes(false)
        ]).then(function () {
            return abrirSolicitudIndicadaEnUrl();
        }).catch(function () {
            /* Cada función ya muestra su propio error. */
        });
    }

    async function abrirSolicitudIndicadaEnUrl() {
        if (estado.solicitudInicialProcesada) {
            return;
        }

        estado.solicitudInicialProcesada = true;

        var parametros = new URLSearchParams(window.location.search);
        var valor = parametros.get('id') || parametros.get('solicitud');

        if (!valor) {
            return;
        }

        var id = Number(valor);

        if (!Number.isInteger(id) || id <= 0) {
            await SistemaUI.advertencia(
                'Enlace no válido',
                'No fue posible identificar la solicitud indicada en la dirección.'
            );
            return;
        }

        await abrirSolicitud(id);
    }

    function capturarElementos() {
        [
            'btnActualizarSolicitudes',
            'resumenTotal',
            'resumenUrgentes',
            'resumenAltas',
            'resumenProgramables',
            'resumenMejoras',
            'fechaActualizacion',
            'filtroBusqueda',
            'filtroTipo',
            'filtroPrioridad',
            'filtroEspera',
            'btnLimpiarFiltros',
            'estadoCargando',
            'estadoVacio',
            'estadoError',
            'textoErrorLista',
            'contenedorTabla',
            'cuerpoTablaSolicitudes',
            'paginacionSolicitudes',
            'paginacionInfo',
            'paginacionNumeros',
            'paginaAnterior',
            'paginaSiguiente',
            'selectorPorPagina',
            'spenFechaHero',
            'modalSolicitud',
            'btnCerrarModal',
            'btnCerrarSinCambios',
            'cargandoDetalle',
            'formSolicitud',
            'accionesModal',
            'btnGuardarCorrecciones',
            'btnRechazarSolicitud',
            'btnAprobarSolicitud',
            'etiquetaModalSolicitud',
            'tituloModalSolicitud',
            'subtituloModalSolicitud',
            'avisoRevisionUrgente',
            'datoFolio',
            'datoTipo',
            'datoSolicitante',
            'datoFechaRegistro',
            'solicitud_id',
            'tipo_solicitud',
            'departamento_id',
            'area_id',
            'proceso_id',
            'equipo_id',
            'prioridad',
            'fecha_sugerida',
            'nivel_riesgo',
            'trabajo_peligroso',
            'requiere_paro_equipo',
            'campoDetallePeligro',
            'detalle_trabajo_peligroso',
            'panelRecursosUrgencia',
            'contadorRecursosUrgencia',
            'avisoRecursosBloqueados',
            'buscarHerramientaUrgencia',
            'resultadosHerramientasUrgencia',
            'seleccionHerramientasUrgencia',
            'buscarRefaccionUrgencia',
            'resultadosRefaccionesUrgencia',
            'seleccionRefaccionesUrgencia',
            'descripcion_solicitud',
            'seccionUrgencia',
            'seccionDiagnostico',
            'textoDiagnostico',
            'tipo_falla_id',
            'causa_averia_id',
            'descripcion_falla',
            'causa_desconocida_descripcion',
            'seccionMejora',
            'objetivo_mejora',
            'resultado_esperado',
            'justificacion_mejora',
            'costo_vs_beneficio',
            'contenedorCausasMejora',
            'seccionObservaciones',
            'impacto_operacion',
            'observaciones_solicitante',
            'motivo_edicion'
        ].forEach(function (id) {
            elementos[id] = document.getElementById(id);
        });
    }

    function registrarEventos() {
        elementos.btnActualizarSolicitudes.addEventListener('click', function () {
            cargarSolicitudes(true);
        });

        elementos.filtroBusqueda.addEventListener('input', function () {
            window.clearTimeout(estado.temporizadorBusqueda);
            estado.temporizadorBusqueda = window.setTimeout(aplicarFiltros, 180);
        });
        elementos.filtroTipo.addEventListener('change', aplicarFiltros);
        elementos.filtroPrioridad.addEventListener('change', aplicarFiltros);
        elementos.filtroEspera.addEventListener('change', aplicarFiltros);
        elementos.btnLimpiarFiltros.addEventListener('click', limpiarFiltros);

        elementos.selectorPorPagina.addEventListener('change', function () {
            estado.porPagina = Math.max(1, Number(elementos.selectorPorPagina.value || 15));
            estado.paginaActual = 1;
            pintarPaginaActual();
        });

        elementos.paginaAnterior.addEventListener('click', function () {
            cambiarPagina(estado.paginaActual - 1);
        });

        elementos.paginaSiguiente.addEventListener('click', function () {
            cambiarPagina(estado.paginaActual + 1);
        });

        elementos.paginacionNumeros.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-pagina]');

            if (boton) {
                cambiarPagina(Number(boton.dataset.pagina));
            }
        });

        document.querySelector('#tablaSolicitudesPendientes thead').addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-sort]');

            if (!boton) {
                return;
            }

            cambiarOrden(boton.dataset.sort);
        });

        elementos.cuerpoTablaSolicitudes.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-accion="revisar"]');

            if (!boton) {
                return;
            }

            abrirSolicitud(Number(boton.dataset.id));
        });

        elementos.departamento_id.addEventListener('change', function () {
            cargarAreas('');
            cargarProcesos('');
            cargarEquipos('');
            marcarSucio();
        });

        elementos.area_id.addEventListener('change', function () {
            cargarProcesos('');
            cargarEquipos('');
            marcarSucio();
        });

        elementos.proceso_id.addEventListener('change', function () {
            cargarEquipos('');
            marcarSucio();
        });

        elementos.trabajo_peligroso.addEventListener('change', function () {
            actualizarDetallePeligro();
            marcarSucio();
        });

        registrarBuscadorRecurso('HERRAMIENTA', elementos.buscarHerramientaUrgencia, elementos.resultadosHerramientasUrgencia);
        registrarBuscadorRecurso('REFACCION', elementos.buscarRefaccionUrgencia, elementos.resultadosRefaccionesUrgencia);
        elementos.seleccionHerramientasUrgencia.addEventListener('click', retirarRecursoUrgencia);
        elementos.seleccionRefaccionesUrgencia.addEventListener('click', retirarRecursoUrgencia);

        document.addEventListener('click', function (evento) {
            if (!evento.target.closest('.spen-resource-search')) {
                elementos.resultadosHerramientasUrgencia.hidden = true;
                elementos.resultadosRefaccionesUrgencia.hidden = true;
            }
        });

        elementos.formSolicitud.addEventListener('input', marcarSucio);
        elementos.formSolicitud.addEventListener('change', marcarSucio);

        elementos.btnGuardarCorrecciones.addEventListener('click', guardarCorrecciones);
        elementos.btnAprobarSolicitud.addEventListener('click', aprobarSolicitud);
        elementos.btnRechazarSolicitud.addEventListener('click', rechazarSolicitud);

        elementos.btnCerrarModal.addEventListener('click', solicitarCerrar);
        elementos.btnCerrarSinCambios.addEventListener('click', solicitarCerrar);

        elementos.modalSolicitud.addEventListener('click', function (evento) {
            if (evento.target === elementos.modalSolicitud) {
                solicitarCerrar();
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && !elementos.modalSolicitud.hidden) {
                solicitarCerrar();
            }
        });
    }

    async function cargarCatalogos() {
        if (estado.cargandoCatalogos || estado.catalogosCargados) {
            return;
        }

        estado.cargandoCatalogos = true;

        try {
            var respuesta = await SistemaUI.peticionJson(
                ENDPOINT + '?accion=catalogos'
            );

            estado.catalogos = respuesta.catalogos || estado.catalogos;
            pintarCatalogosFijos();
            estado.catalogosCargados = true;
        } catch (error) {
            await SistemaUI.error(
                'No se pudieron cargar los catálogos',
                error.message || 'Actualiza la página e inténtalo nuevamente.'
            );
            throw error;
        } finally {
            estado.cargandoCatalogos = false;
        }
    }

    function pintarCatalogosFijos() {
        llenarSelect(
            elementos.departamento_id,
            estado.catalogos.departamentos,
            'id',
            function (item) { return item.nombre; },
            'Selecciona'
        );

        llenarSelect(
            elementos.tipo_falla_id,
            estado.catalogos.tipos_falla,
            'id',
            function (item) { return item.nombre; },
            'No especificado'
        );

        llenarSelect(
            elementos.causa_averia_id,
            estado.catalogos.causas_averia,
            'id',
            function (item) { return item.nombre; },
            'No especificada'
        );

        pintarCausasMejora();
    }

    function pintarCausasMejora() {
        var lista = Array.isArray(estado.catalogos.causas_mejora)
            ? estado.catalogos.causas_mejora
            : [];

        if (!lista.length) {
            elementos.contenedorCausasMejora.innerHTML =
                '<span class="spen-ayuda">No hay causas disponibles.</span>';
            return;
        }

        elementos.contenedorCausasMejora.innerHTML = lista.map(function (item) {
            return (
                '<label class="spen-check-chip">' +
                    '<input type="checkbox" name="causas_mejora_ids[]" value="' +
                        escaparAtributo(item.id) + '">' +
                    '<span>' + escapar(item.nombre) + '</span>' +
                '</label>'
            );
        }).join('');
    }

    async function cargarSolicitudes(mostrarMensaje) {
        mostrarEstadoLista('cargando');
        SistemaUI.estadoBoton(elementos.btnActualizarSolicitudes, true, 'Actualizando...');

        try {
            var respuesta = await SistemaUI.peticionJson(
                ENDPOINT + '?accion=listar'
            );

            estado.solicitudes = Array.isArray(respuesta.solicitudes)
                ? respuesta.solicitudes
                : [];

            pintarResumen(respuesta.resumen || {});
            pintarFecha(respuesta.fecha_servidor || '');
            aplicarFiltros();

            if (mostrarMensaje) {
                await SistemaUI.exito(
                    'Lista actualizada',
                    'La bandeja se actualizó correctamente.'
                );
            }
        } catch (error) {
            estado.solicitudes = [];
            estado.solicitudesFiltradas = [];
            elementos.cuerpoTablaSolicitudes.innerHTML = '';
            elementos.textoErrorLista.textContent =
                error.message || 'Ocurrió un error inesperado.';
            mostrarEstadoLista('error');

            await SistemaUI.error(
                'No se pudo cargar el módulo',
                error.message || 'Ocurrió un error al consultar las solicitudes.'
            );
        } finally {
            SistemaUI.estadoBoton(elementos.btnActualizarSolicitudes, false);
        }
    }

    function pintarResumen(resumen) {
        elementos.resumenTotal.textContent = numero(resumen.total);
        elementos.resumenUrgentes.textContent = numero(resumen.urgentes);
        elementos.resumenAltas.textContent = numero(resumen.prioridad_alta);
        elementos.resumenProgramables.textContent = numero(resumen.programables);
        elementos.resumenMejoras.textContent = numero(resumen.mejoras);
    }

    function pintarFecha(valor) {
        if (!valor) {
            elementos.fechaActualizacion.textContent = 'Ahora';
            return;
        }

        var partes = valor.split(' ');
        var fecha = (partes[0] || '').split('-');
        var hora = (partes[1] || '').substring(0, 5);

        elementos.fechaActualizacion.textContent = fecha.length === 3
            ? fecha[2] + '/' + fecha[1] + '/' + fecha[0] + ' ' + hora
            : valor;
    }

    function pintarFechaHero() {
        if (!elementos.spenFechaHero) {
            return;
        }

        try {
            elementos.spenFechaHero.textContent = new Intl.DateTimeFormat('es-MX', {
                weekday: 'long',
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            }).format(new Date());
        } catch (error) {
            elementos.spenFechaHero.textContent = '';
        }
    }

    function aplicarFiltros() {
        var busqueda = normalizarBusqueda(elementos.filtroBusqueda.value);
        var tipo = elementos.filtroTipo.value;
        var prioridad = elementos.filtroPrioridad.value;
        var espera = Number(elementos.filtroEspera.value || 0);

        estado.solicitudesFiltradas = estado.solicitudes.filter(function (item) {
            if (tipo && item.tipo_solicitud !== tipo) {
                return false;
            }

            if (prioridad && item.prioridad !== prioridad) {
                return false;
            }

            if (espera > 0 && Number(item.minutos_espera || 0) < espera) {
                return false;
            }

            if (busqueda) {
                var texto = [
                    item.folio,
                    item.solicitante,
                    item.departamento,
                    item.area,
                    item.proceso,
                    item.codigo_equipo,
                    item.nombre_equipo,
                    item.descripcion_solicitud
                ].join(' ');

                if (normalizarBusqueda(texto).indexOf(busqueda) === -1) {
                    return false;
                }
            }

            return true;
        });

        estado.paginaActual = 1;
        pintarPaginaActual();
    }

    function limpiarFiltros() {
        elementos.filtroBusqueda.value = '';
        elementos.filtroTipo.value = '';
        elementos.filtroPrioridad.value = '';
        elementos.filtroEspera.value = '';
        estado.ordenCampo = '';
        estado.ordenDireccion = 'asc';
        actualizarIndicadoresOrden();
        aplicarFiltros();
        elementos.filtroBusqueda.focus();
    }

    function cambiarOrden(campo) {
        if (!campo) {
            return;
        }

        if (estado.ordenCampo === campo) {
            estado.ordenDireccion = estado.ordenDireccion === 'asc' ? 'desc' : 'asc';
        } else {
            estado.ordenCampo = campo;
            estado.ordenDireccion = 'asc';
        }

        estado.paginaActual = 1;
        actualizarIndicadoresOrden();
        pintarPaginaActual();
    }

    function actualizarIndicadoresOrden() {
        document.querySelectorAll('.spen-sort').forEach(function (boton) {
            var activo = boton.dataset.sort === estado.ordenCampo;
            boton.classList.toggle('is-active', activo);
            boton.setAttribute(
                'aria-sort',
                activo
                    ? (estado.ordenDireccion === 'asc' ? 'ascending' : 'descending')
                    : 'none'
            );
        });
    }

    function ordenarLista(lista) {
        if (!estado.ordenCampo) {
            return lista.slice();
        }

        var direccion = estado.ordenDireccion === 'desc' ? -1 : 1;
        var prioridadOrden = { URGENTE: 4, ALTA: 3, MEDIA: 2, BAJA: 1 };

        return lista.slice().sort(function (a, b) {
            var valorA;
            var valorB;

            switch (estado.ordenCampo) {
                case 'prioridad':
                    valorA = prioridadOrden[a.prioridad] || 0;
                    valorB = prioridadOrden[b.prioridad] || 0;
                    break;
                case 'equipo':
                    valorA = (a.codigo_equipo || '') + ' ' + (a.nombre_equipo || '');
                    valorB = (b.codigo_equipo || '') + ' ' + (b.nombre_equipo || '');
                    break;
                case 'fecha':
                    valorA = String(a.fecha_solicitud || '') + ' ' + String(a.hora_solicitud || '');
                    valorB = String(b.fecha_solicitud || '') + ' ' + String(b.hora_solicitud || '');
                    break;
                default:
                    valorA = a[estado.ordenCampo] || '';
                    valorB = b[estado.ordenCampo] || '';
                    break;
            }

            if (typeof valorA === 'number' && typeof valorB === 'number') {
                return (valorA - valorB) * direccion;
            }

            return String(valorA).localeCompare(String(valorB), 'es', {
                sensitivity: 'base',
                numeric: true
            }) * direccion;
        });
    }

    function pintarPaginaActual() {
        elementos.cuerpoTablaSolicitudes.innerHTML = '';

        if (!estado.solicitudesFiltradas.length) {
            elementos.paginacionSolicitudes.hidden = true;
            mostrarEstadoLista('vacio');
            return;
        }

        var listaOrdenada = ordenarLista(estado.solicitudesFiltradas);
        var total = listaOrdenada.length;
        var totalPaginas = Math.max(1, Math.ceil(total / estado.porPagina));

        if (estado.paginaActual > totalPaginas) {
            estado.paginaActual = totalPaginas;
        }

        var inicio = (estado.paginaActual - 1) * estado.porPagina;
        var fin = Math.min(inicio + estado.porPagina, total);
        var pagina = listaOrdenada.slice(inicio, fin);
        var fragmento = document.createDocumentFragment();

        pagina.forEach(function (item) {
            fragmento.appendChild(crearFilaSolicitud(item));
        });

        elementos.cuerpoTablaSolicitudes.appendChild(fragmento);
        elementos.paginacionInfo.textContent =
            'Mostrando ' + (inicio + 1).toLocaleString('es-MX') +
            ' a ' + fin.toLocaleString('es-MX') +
            ' de ' + total.toLocaleString('es-MX') + ' registros';

        pintarPaginacion(totalPaginas);
        mostrarEstadoLista('tabla');
    }

    function crearFilaSolicitud(item) {
        var fila = document.createElement('tr');

        if (item.tipo_solicitud === 'CORRECTIVO_URGENTE') {
            fila.classList.add('spen-fila-urgente');
        }

        var revision = item.tipo_revision === 'URGENTE_SIN_REVISAR'
            ? '<span class="spen-badge spen-badge--revision">Activa · por revisar</span>'
            : '<span class="spen-badge spen-badge--pendiente">Pendiente</span>';

        var ubicacion = [item.departamento, item.area]
            .filter(function (valor) { return String(valor || '').trim() !== ''; })
            .join(' · ');

        fila.innerHTML =
            '<td data-label="Folio" class="spen-td spen-td--folio">' +
                '<strong class="spen-folio">' + escapar(item.folio || 'Sin folio') + '</strong>' +
                '<small class="spen-registro">Registrada: ' + escapar(item.fecha_registro_formato || 'Sin fecha') + '</small>' +
            '</td>' +
            '<td data-label="Tipo" class="spen-td spen-td--tipo">' + badgeTipo(item.tipo_solicitud) + '</td>' +
            '<td data-label="Prioridad" class="spen-td spen-td--prioridad">' + badgePrioridad(item.prioridad) + '</td>' +
            '<td data-label="Solicitante" class="spen-td spen-td--solicitante">' +
                '<strong>' + escapar(item.solicitante || 'Sin solicitante') + '</strong>' +
            '</td>' +
            '<td data-label="Equipo y ubicación" class="spen-td spen-td--equipo">' +
                '<strong class="spen-equipo-codigo">' + escapar(item.codigo_equipo || 'Sin código') + '</strong>' +
                '<small class="spen-equipo-nombre">' + escapar(item.nombre_equipo || 'Sin equipo') + '</small>' +
                '<small class="spen-ubicacion">' + escapar(ubicacion || 'Sin ubicación') + '</small>' +
                '<small class="spen-proceso">Proceso: ' + escapar(item.proceso || 'Sin proceso') + '</small>' +
            '</td>' +
            '<td data-label="Fecha y espera" class="spen-td spen-td--fecha">' +
                '<strong>' + escapar(item.fecha_solicitud_formato || 'Sin fecha') + '</strong>' +
                '<small>' + escapar(item.hora_solicitud_formato || '--:--') + ' h</small>' +
                '<small class="spen-tiempo-espera">' + escapar(textoEspera(item.minutos_espera)) + ' de espera</small>' +
            '</td>' +
            '<td data-label="Estado de revisión" class="spen-td spen-td--revision">' + revision + '</td>' +
            '<td data-label="Acción" class="spen-td spen-td--accion spen-tabla__accion">' +
                '<button type="button" class="spen-btn-tabla" data-accion="revisar" data-id="' +
                    numero(item.id) + '">' +
                    '<span>Revisar</span><svg aria-hidden="true"><use href="#spen-icon-chevron"></use></svg>' +
                '</button>' +
            '</td>';

        return fila;
    }

    function pintarPaginacion(totalPaginas) {
        elementos.paginaAnterior.disabled = estado.paginaActual <= 1;
        elementos.paginaSiguiente.disabled = estado.paginaActual >= totalPaginas;
        elementos.paginacionSolicitudes.hidden = totalPaginas <= 1;

        var paginas = obtenerPaginasVisibles(totalPaginas, estado.paginaActual);
        elementos.paginacionNumeros.innerHTML = paginas.map(function (pagina) {
            if (pagina === '…') {
                return '<span class="spen-paginacion__ellipsis" aria-hidden="true">…</span>';
            }

            var activa = Number(pagina) === estado.paginaActual;

            return '<button type="button" class="spen-paginacion__numero' +
                (activa ? ' is-active' : '') +
                '" data-pagina="' + pagina + '"' +
                (activa ? ' aria-current="page"' : '') +
                '>' + pagina + '</button>';
        }).join('');
    }

    function obtenerPaginasVisibles(total, actual) {
        if (total <= 7) {
            return Array.from({ length: total }, function (_, indice) {
                return indice + 1;
            });
        }

        var paginas = [1];
        var inicio = Math.max(2, actual - 1);
        var fin = Math.min(total - 1, actual + 1);

        if (inicio > 2) {
            paginas.push('…');
        }

        for (var pagina = inicio; pagina <= fin; pagina++) {
            paginas.push(pagina);
        }

        if (fin < total - 1) {
            paginas.push('…');
        }

        paginas.push(total);
        return paginas;
    }

    function cambiarPagina(pagina) {
        var totalPaginas = Math.max(
            1,
            Math.ceil(estado.solicitudesFiltradas.length / estado.porPagina)
        );

        var nuevaPagina = Math.min(totalPaginas, Math.max(1, Number(pagina || 1)));

        if (nuevaPagina === estado.paginaActual) {
            return;
        }

        estado.paginaActual = nuevaPagina;
        pintarPaginaActual();

        var panel = elementos.contenedorTabla.closest('.spen-panel');

        if (panel && panel.getBoundingClientRect().top < 0) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function mostrarEstadoLista(tipo) {
        elementos.estadoCargando.hidden = tipo !== 'cargando';
        elementos.estadoVacio.hidden = tipo !== 'vacio';
        elementos.estadoError.hidden = tipo !== 'error';
        elementos.contenedorTabla.hidden = tipo !== 'tabla';
    }

    async function abrirSolicitud(id) {
        if (!Number.isInteger(id) || id <= 0) {
            await SistemaUI.error(
                'Solicitud no válida',
                'No fue posible identificar el registro.'
            );
            return;
        }

        if (!estado.catalogosCargados) {
            await cargarCatalogos();
        }

        estado.ultimoFoco = document.activeElement;
        estado.solicitudActual = null;
        estado.formularioSucio = false;

        abrirModal();
        mostrarCargaDetalle(true);

        try {
            var respuesta = await SistemaUI.peticionJson(
                ENDPOINT + '?accion=obtener&id=' + encodeURIComponent(id)
            );

            estado.solicitudActual = respuesta.solicitud || null;

            if (!estado.solicitudActual) {
                throw new Error('No se recibió la información de la solicitud.');
            }

            llenarFormulario(estado.solicitudActual);
            mostrarCargaDetalle(false);
        } catch (error) {
            cerrarModal(true);

            await SistemaUI.error(
                'No se pudo abrir la solicitud',
                error.message || 'Actualiza la lista e inténtalo nuevamente.'
            );
        }
    }

    function abrirModal() {
        elementos.modalSolicitud.hidden = false;
        document.body.classList.add('spen-bloquear-scroll');
    }

    function mostrarCargaDetalle(cargando) {
        elementos.cargandoDetalle.hidden = !cargando;
        elementos.formSolicitud.hidden = cargando;
        elementos.accionesModal.hidden = cargando;
    }

    function llenarFormulario(solicitud) {
        elementos.formSolicitud.reset();
        limpiarErrores();

        elementos.solicitud_id.value = solicitud.id || '';
        elementos.tipo_solicitud.value = solicitud.tipo_solicitud || '';

        elementos.datoFolio.textContent = solicitud.folio || 'Sin folio';
        elementos.datoTipo.textContent = textoTipo(solicitud.tipo_solicitud);
        elementos.datoSolicitante.textContent = solicitud.solicitante || 'Sin solicitante';
        elementos.datoFechaRegistro.textContent = solicitud.fecha_registro_formato || 'Sin fecha';

        elementos.etiquetaModalSolicitud.textContent =
            solicitud.tipo_solicitud === 'CORRECTIVO_URGENTE'
                ? 'Urgencia activa pendiente de revisión'
                : 'Solicitud pendiente';

        elementos.tituloModalSolicitud.textContent =
            solicitud.folio || 'Revisar solicitud';

        elementos.subtituloModalSolicitud.textContent =
            solicitud.tipo_solicitud === 'CORRECTIVO_URGENTE'
                ? 'La urgencia ya está publicada. Revisa sus datos sin interrumpir la atención técnica.'
                : 'Se muestran únicamente los campos que corresponden a este tipo de solicitud.';

        elementos.avisoRevisionUrgente.hidden =
            solicitud.tipo_solicitud !== 'CORRECTIVO_URGENTE';

        elementos.departamento_id.value = String(solicitud.departamento_id || '');
        cargarAreas(String(solicitud.area_id || ''));
        cargarProcesos(String(solicitud.proceso_id || ''));
        cargarEquipos(String(solicitud.equipo_id || ''));

        elementos.prioridad.value = solicitud.prioridad || 'MEDIA';
        elementos.fecha_sugerida.value = solicitud.fecha_sugerida || '';
        elementos.nivel_riesgo.value = solicitud.nivel_riesgo || 'BAJO';
        elementos.trabajo_peligroso.checked = Number(solicitud.trabajo_peligroso) === 1;
        elementos.requiere_paro_equipo.checked = Number(solicitud.requiere_paro_equipo) === 1;
        elementos.detalle_trabajo_peligroso.value = solicitud.detalle_trabajo_peligroso || '';
        actualizarDetallePeligro();
        elementos.descripcion_solicitud.value = solicitud.descripcion_solicitud || '';
        elementos.tipo_falla_id.value = solicitud.tipo_falla_id || '';
        elementos.causa_averia_id.value = solicitud.causa_averia_id || '';
        elementos.descripcion_falla.value = solicitud.descripcion_falla || '';
        elementos.causa_desconocida_descripcion.value =
            solicitud.causa_desconocida_descripcion || '';
        elementos.objetivo_mejora.value = solicitud.objetivo_mejora || '';
        elementos.resultado_esperado.value = solicitud.resultado_esperado || '';
        elementos.justificacion_mejora.value = solicitud.justificacion_mejora || '';
        elementos.costo_vs_beneficio.value = solicitud.costo_vs_beneficio || '';
        elementos.impacto_operacion.value = solicitud.impacto_operacion || '';
        elementos.observaciones_solicitante.value =
            solicitud.observaciones_solicitante || '';
        elementos.motivo_edicion.value = '';

        marcarCausasMejora(solicitud.causas_mejora_ids || []);
        estado.recursosUrgencia = Array.isArray(solicitud.recursos_recomendados)
            ? solicitud.recursos_recomendados.map(normalizarRecursoUrgencia)
            : [];
        estado.recursosEditables = solicitud.recursos_editables !== false;
        pintarRecursosUrgencia();
        configurarFormularioPorTipo(solicitud.tipo_solicitud);

        elementos.btnAprobarSolicitud.textContent =
            solicitud.tipo_solicitud === 'CORRECTIVO_URGENTE'
                ? 'Marcar como revisada'
                : 'Aprobar solicitud';
        elementos.btnGuardarCorrecciones.textContent =
            solicitud.tipo_solicitud === 'CORRECTIVO_URGENTE'
                ? 'Guardar datos y recomendaciones'
                : 'Guardar correcciones';

        estado.formularioSucio = false;
    }

    function cargarAreas(valorSeleccionado) {
        var departamentoId = Number(elementos.departamento_id.value || 0);
        var lista = estado.catalogos.areas.filter(function (item) {
            return Number(item.departamento_id) === departamentoId;
        });

        llenarSelect(
            elementos.area_id,
            lista,
            'id',
            function (item) { return item.nombre; },
            'Selecciona'
        );

        elementos.area_id.disabled = departamentoId <= 0;
        elementos.area_id.value = valorSeleccionado || '';
    }

    function cargarProcesos(valorSeleccionado) {
        var areaId = Number(elementos.area_id.value || 0);
        var lista = estado.catalogos.procesos.filter(function (item) {
            return Number(item.area_id) === areaId;
        });

        llenarSelect(
            elementos.proceso_id,
            lista,
            'id',
            function (item) { return item.nombre; },
            'Selecciona'
        );

        elementos.proceso_id.disabled = areaId <= 0;
        elementos.proceso_id.value = valorSeleccionado || '';
    }

    function cargarEquipos(valorSeleccionado) {
        var departamentoId = Number(elementos.departamento_id.value || 0);
        var areaId = Number(elementos.area_id.value || 0);
        var procesoId = Number(elementos.proceso_id.value || 0);

        var lista = estado.catalogos.equipos.filter(function (item) {
            return (
                Number(item.departamento_id) === departamentoId &&
                Number(item.area_id) === areaId &&
                Number(item.proceso_id) === procesoId
            );
        });

        llenarSelect(
            elementos.equipo_id,
            lista,
            'id',
            function (item) {
                return (item.codigo_equipo || 'Sin código') +
                    ' — ' +
                    (item.nombre_equipo || 'Sin nombre');
            },
            'Selecciona'
        );

        elementos.equipo_id.disabled = procesoId <= 0;
        elementos.equipo_id.value = valorSeleccionado || '';
    }

    function llenarSelect(select, lista, campoValor, texto, placeholder) {
        select.innerHTML =
            '<option value="">' + escapar(placeholder) + '</option>' +
            (Array.isArray(lista) ? lista : []).map(function (item) {
                return '<option value="' + escaparAtributo(item[campoValor]) + '">' +
                    escapar(texto(item)) +
                '</option>';
            }).join('');
    }

    function marcarCausasMejora(ids) {
        var seleccionadas = (Array.isArray(ids) ? ids : []).map(Number);

        elementos.contenedorCausasMejora
            .querySelectorAll('input[type="checkbox"]')
            .forEach(function (checkbox) {
                checkbox.checked = seleccionadas.indexOf(Number(checkbox.value)) !== -1;
            });
    }

    function actualizarDetallePeligro() {
        var peligroso = elementos.trabajo_peligroso.checked;
        elementos.campoDetallePeligro.hidden = !peligroso;
        elementos.detalle_trabajo_peligroso.required = peligroso;
        if (!peligroso) {
            elementos.detalle_trabajo_peligroso.value = '';
            elementos.nivel_riesgo.value = 'BAJO';
        }
    }

    function normalizarRecursoUrgencia(recurso) {
        return {
            tipo_recurso: recurso.tipo_recurso || '',
            recurso_id: Number(recurso.recurso_id || recurso.id || 0) || null,
            nombre_no_catalogado: recurso.nombre_no_catalogado || null,
            nombre: recurso.nombre || recurso.nombre_no_catalogado || 'Recurso sin nombre',
            codigo: recurso.codigo || '',
            descripcion: recurso.descripcion || '',
            activo: Number(recurso.activo == null ? 1 : recurso.activo),
            origen: recurso.origen || 'ADMIN'
        };
    }

    function registrarBuscadorRecurso(tipo, input, resultados) {
        input.addEventListener('focus', function () {
            buscarRecursosUrgencia(tipo, input.value, resultados);
        });
        input.addEventListener('input', function () {
            window.clearTimeout(estado.temporizadorRecursos[tipo]);
            estado.temporizadorRecursos[tipo] = window.setTimeout(function () {
                buscarRecursosUrgencia(tipo, input.value, resultados);
            }, 180);
        });
        resultados.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-recurso-id]');
            if (!boton || !estado.recursosEditables) return;
            var recurso = JSON.parse(decodeURIComponent(boton.dataset.recurso || ''));
            agregarRecursoUrgencia(tipo, recurso);
            input.value = '';
            resultados.hidden = true;
            input.focus();
        });
    }

    async function buscarRecursosUrgencia(tipo, texto, contenedor) {
        if (!estado.recursosEditables || elementos.tipo_solicitud.value !== 'CORRECTIVO_URGENTE') {
            contenedor.hidden = true;
            return;
        }
        try {
            var url = ENDPOINT_RECURSOS + '&accion=BUSCAR_ACTIVOS&tipo_recurso=' + encodeURIComponent(tipo)
                + '&q=' + encodeURIComponent(texto || '') + '&limite=20';
            var respuesta = await SistemaUI.peticionJson(url);
            var seleccionados = estado.recursosUrgencia.reduce(function (mapa, item) {
                if (item.recurso_id) mapa[item.recurso_id] = true;
                return mapa;
            }, {});
            var recursos = (respuesta.recursos || []).filter(function (item) {
                return !seleccionados[Number(item.id)];
            });
            contenedor.innerHTML = recursos.length ? recursos.map(function (item) {
                var serializado = encodeURIComponent(JSON.stringify(item));
                return '<button type="button" data-recurso-id="' + Number(item.id) + '" data-recurso="' + serializado + '">' +
                    '<strong>' + escapar(item.nombre || '') + '</strong>' +
                    '<span>' + escapar((item.codigo || 'Sin código') + (item.descripcion ? ' · ' + item.descripcion : '')) + '</span>' +
                '</button>';
            }).join('') : '<div class="spen-resource-empty">No hay coincidencias activas.</div>';
            contenedor.hidden = false;
        } catch (error) {
            contenedor.innerHTML = '<div class="spen-resource-empty">No fue posible buscar recursos.</div>';
            contenedor.hidden = false;
        }
    }

    function agregarRecursoUrgencia(tipo, recurso) {
        var id = Number(recurso.id || recurso.recurso_id || 0);
        if (!id || estado.recursosUrgencia.some(function (item) { return Number(item.recurso_id) === id; })) {
            return;
        }
        estado.recursosUrgencia.push(normalizarRecursoUrgencia({
            tipo_recurso: tipo,
            recurso_id: id,
            nombre: recurso.nombre,
            codigo: recurso.codigo,
            descripcion: recurso.descripcion,
            activo: 1,
            origen: 'ADMIN'
        }));
        pintarRecursosUrgencia();
        marcarSucio();
    }

    function retirarRecursoUrgencia(evento) {
        var boton = evento.target.closest('[data-quitar-recurso]');
        if (!boton || !estado.recursosEditables) return;
        var clave = boton.dataset.quitarRecurso;
        estado.recursosUrgencia = estado.recursosUrgencia.filter(function (item) {
            return claveRecursoUrgencia(item) !== clave;
        });
        pintarRecursosUrgencia();
        marcarSucio();
    }

    function claveRecursoUrgencia(item) {
        return item.recurso_id
            ? 'ID-' + Number(item.recurso_id)
            : 'TXT-' + item.tipo_recurso + '-' + String(item.nombre_no_catalogado || '').toLowerCase();
    }

    function pintarRecursosUrgencia() {
        var herramientas = estado.recursosUrgencia.filter(function (item) { return item.tipo_recurso === 'HERRAMIENTA'; });
        var refacciones = estado.recursosUrgencia.filter(function (item) { return item.tipo_recurso === 'REFACCION'; });
        pintarSeleccionRecursos(elementos.seleccionHerramientasUrgencia, herramientas);
        pintarSeleccionRecursos(elementos.seleccionRefaccionesUrgencia, refacciones);
        var total = estado.recursosUrgencia.length;
        elementos.contadorRecursosUrgencia.textContent = total + (total === 1 ? ' seleccionado' : ' seleccionados');
    }

    function pintarSeleccionRecursos(contenedor, lista) {
        if (!lista.length) {
            contenedor.innerHTML = '<div class="spen-resource-none">Sin recomendaciones seleccionadas.</div>';
            return;
        }
        contenedor.innerHTML = lista.map(function (item) {
            var libre = !item.recurso_id;
            var inactivo = Number(item.activo) !== 1;
            return '<article class="spen-resource-chip' + (libre ? ' is-free' : '') + (inactivo ? ' is-inactive' : '') + '">' +
                '<div><strong>' + escapar(item.nombre) + '</strong>' +
                '<span>' + escapar(libre ? 'Pendiente de catálogo' : (item.codigo || 'Sin código')) + '</span></div>' +
                (estado.recursosEditables ? '<button type="button" data-quitar-recurso="' + escaparAtributo(claveRecursoUrgencia(item)) + '" aria-label="Quitar">×</button>' : '') +
            '</article>';
        }).join('');
    }

    function agregarRecursosAlFormulario(datos) {
        datos.delete('herramientas_ids[]');
        datos.delete('refacciones_ids[]');
        datos.delete('herramientas_libres[]');
        datos.delete('refacciones_libres[]');
        estado.recursosUrgencia.forEach(function (item) {
            if (item.recurso_id) {
                datos.append(item.tipo_recurso === 'HERRAMIENTA' ? 'herramientas_ids[]' : 'refacciones_ids[]', String(item.recurso_id));
            } else if (item.nombre_no_catalogado) {
                datos.append(item.tipo_recurso === 'HERRAMIENTA' ? 'herramientas_libres[]' : 'refacciones_libres[]', item.nombre_no_catalogado);
            }
        });
    }

    function configurarFormularioPorTipo(tipo) {
        var esProgramable = tipo === 'CORRECTIVO_PROGRAMABLE';
        var esMejora = tipo === 'MODIFICACION_MEJORA';
        var esUrgente = tipo === 'CORRECTIVO_URGENTE';

        /*
         * Cada tipo muestra únicamente la información que le corresponde:
         * - Programable: datos generales y diagnóstico opcional.
         * - Mejora: datos generales y bloque de modificación/mejora.
         * - Urgente: datos generales, riesgo, impacto y diagnóstico opcional.
         */
        elementos.seccionUrgencia.hidden = !esUrgente;
        elementos.seccionMejora.hidden = !esMejora;
        elementos.seccionDiagnostico.hidden = esMejora;

        elementos.prioridad.disabled = esUrgente;

        if (esUrgente) {
            elementos.prioridad.value = 'URGENTE';
        } else if (elementos.prioridad.value === 'URGENTE') {
            elementos.prioridad.value = 'ALTA';
        }

        elementos.nivel_riesgo.required = true;
        elementos.tipo_falla_id.required = false;
        elementos.causa_averia_id.required = false;
        elementos.impacto_operacion.required = esUrgente;

        document.querySelectorAll('.spen-obligatorio-urgente').forEach(function (elemento) {
            elemento.hidden = true;
        });

        var permitirRecursos = esUrgente && estado.recursosEditables;
        elementos.buscarHerramientaUrgencia.disabled = !permitirRecursos;
        elementos.buscarRefaccionUrgencia.disabled = !permitirRecursos;
        elementos.avisoRecursosBloqueados.hidden = !esUrgente || estado.recursosEditables;
        pintarRecursosUrgencia();
        actualizarDetallePeligro();

        if (esUrgente) {
            elementos.textoDiagnostico.textContent =
                'Opcional al revisar. Puedes guardar solamente las recomendaciones; si faltan estos datos, el primer técnico que inicie la urgencia capturará el diagnóstico.';
        } else if (esProgramable) {
            elementos.textoDiagnostico.textContent =
                'El tipo de falla y la causa son opcionales; déjalos en blanco cuando no se conozcan.';
        }
    }

    function marcarSucio() {
        if (!estado.solicitudActual) {
            return;
        }

        estado.formularioSucio = true;
    }

    async function guardarCorrecciones() {
        if (estado.guardando || !estado.solicitudActual) {
            return;
        }

        limpiarErrores();

        if (!validarFormulario()) {
            await SistemaUI.advertencia(
                'Revisa la información',
                'Completa o corrige los campos señalados.'
            );
            return;
        }

        var motivo = normalizarTexto(elementos.motivo_edicion.value);

        if (motivo.length < 10) {
            marcarCampoInvalido(
                elementos.motivo_edicion,
                'Escribe al menos 10 caracteres.'
            );
            elementos.motivo_edicion.focus();

            await SistemaUI.advertencia(
                'Falta el motivo de edición',
                'Escribe por qué se modificó la solicitud.'
            );
            return;
        }

        estado.guardando = true;
        SistemaUI.estadoBoton(
            elementos.btnGuardarCorrecciones,
            true,
            'Guardando...'
        );
        bloquearAcciones(true);

        try {
            habilitarCamposParaEnvio();
            var datos = new FormData(elementos.formSolicitud);
            datos.set('accion', 'guardar_edicion');
            agregarRecursosAlFormulario(datos);

            await SistemaUI.peticionJson(ENDPOINT, {
                method: 'POST',
                body: datos
            });

            estado.formularioSucio = false;
            elementos.motivo_edicion.value = '';

            await SistemaUI.exito(
                'Correcciones guardadas',
                'La solicitud y su auditoría se actualizaron correctamente.'
            );

            var id = Number(estado.solicitudActual.id);
            await recargarSolicitudAbierta(id);
            await cargarSolicitudes(false);
        } catch (error) {
            marcarErrorServidor(error);

            await SistemaUI.error(
                'No se pudieron guardar los cambios',
                error.message || 'Revisa la información e inténtalo nuevamente.'
            );
        } finally {
            estado.guardando = false;
            SistemaUI.estadoBoton(elementos.btnGuardarCorrecciones, false);
            bloquearAcciones(false);
            configurarFormularioPorTipo(
                estado.solicitudActual
                    ? estado.solicitudActual.tipo_solicitud
                    : ''
            );
        }
    }

    async function recargarSolicitudAbierta(id) {
        var respuesta = await SistemaUI.peticionJson(
            ENDPOINT + '?accion=obtener&id=' + encodeURIComponent(id)
        );

        estado.solicitudActual = respuesta.solicitud || null;

        if (estado.solicitudActual) {
            llenarFormulario(estado.solicitudActual);
        }
    }

    async function aprobarSolicitud() {
        if (!estado.solicitudActual) {
            return;
        }

        limpiarErrores();
        if (!validarFormulario()) {
            await SistemaUI.advertencia(
                'Completa la revisión',
                'Corrige los campos señalados y guarda los cambios antes de continuar.'
            );
            return;
        }

        if (estado.formularioSucio) {
            await SistemaUI.advertencia(
                'Hay cambios sin guardar',
                'Guarda las correcciones antes de aprobar o validar la solicitud.'
            );
            return;
        }

        var esUrgente =
            estado.solicitudActual.tipo_solicitud === 'CORRECTIVO_URGENTE';

        var confirmado = await SistemaUI.confirmar({
            titulo: esUrgente
                ? '¿Marcar esta urgencia como revisada?'
                : '¿Aprobar esta solicitud?',
            texto: esUrgente
                ? 'Solo se registrará la revisión administrativa. La urgencia continuará en su estado operativo actual.'
                : 'Pasará al módulo de programación y asignación.',
            textoConfirmar: esUrgente ? 'Sí, marcar revisada' : 'Sí, aprobar',
            icono: 'question',
            peligro: false
        });

        if (!confirmado) {
            return;
        }

        procesarSolicitud(esUrgente ? 'REVISAR_URGENCIA' : 'APROBAR', '');
    }

    async function rechazarSolicitud() {
        if (!estado.solicitudActual) {
            return;
        }

        if (estado.formularioSucio) {
            await SistemaUI.advertencia(
                'Hay cambios sin guardar',
                'Guarda o descarta los cambios antes de rechazar.'
            );
            return;
        }

        var resultado = await solicitarMotivo(
            'Rechazar solicitud',
            'Explica claramente por qué no procede.',
            'Rechazar',
            '#b42318'
        );

        if (resultado === null) {
            return;
        }

        procesarSolicitud('RECHAZAR', resultado);
    }


    async function solicitarMotivo(titulo, texto, confirmar, color) {
        var resultado = await Swal.fire({
            icon: 'warning',
            title: titulo,
            text: texto,
            input: 'textarea',
            inputLabel: 'Motivo',
            inputPlaceholder: 'Escribe una explicación clara...',
            inputAttributes: {
                maxlength: '800',
                rows: '5'
            },
            showCancelButton: true,
            confirmButtonText: confirmar,
            cancelButtonText: 'Volver',
            reverseButtons: true,
            focusCancel: true,
            confirmButtonColor: color,
            allowOutsideClick: false,
            heightAuto: false,
            preConfirm: function (valor) {
                var motivo = normalizarTexto(valor || '');

                if (motivo.length < 10) {
                    Swal.showValidationMessage('Escribe al menos 10 caracteres.');
                    return false;
                }

                if (!/[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/.test(motivo)) {
                    Swal.showValidationMessage('Escribe una explicación válida.');
                    return false;
                }

                return motivo;
            }
        });

        return resultado.isConfirmed
            ? resultado.value
            : null;
    }

    async function procesarSolicitud(tipoAccion, motivo) {
        if (estado.guardando || !estado.solicitudActual) {
            return;
        }

        estado.guardando = true;
        bloquearAcciones(true);

        var boton = (tipoAccion === 'APROBAR' || tipoAccion === 'REVISAR_URGENCIA')
            ? elementos.btnAprobarSolicitud
            : elementos.btnRechazarSolicitud;

        SistemaUI.estadoBoton(boton, true, 'Procesando...');

        try {
            var datos = new FormData();
            datos.set('accion', 'procesar');
            datos.set('id', String(estado.solicitudActual.id));
            datos.set('tipo_accion', tipoAccion);
            datos.set('motivo', motivo || '');

            var respuesta = await SistemaUI.peticionJson(ENDPOINT, {
                method: 'POST',
                body: datos
            });

            cerrarModal(true);
            await SistemaUI.exito(
                'Operación realizada',
                respuesta.mensaje || 'La solicitud se actualizó correctamente.'
            );
            await cargarSolicitudes(false);
        } catch (error) {
            await SistemaUI.error(
                'No se pudo procesar la solicitud',
                error.message || 'Actualiza la información e inténtalo nuevamente.'
            );
        } finally {
            estado.guardando = false;
            SistemaUI.estadoBoton(boton, false);
            bloquearAcciones(false);
        }
    }

    function validarFormulario() {
        habilitarCamposParaEnvio();
        var valido = elementos.formSolicitud.checkValidity();

        if (!valido) {
            var primero = elementos.formSolicitud.querySelector(':invalid');

            if (primero) {
                primero.focus();
            }
        }

        var tipo = elementos.tipo_solicitud.value;

        if (tipo === 'MODIFICACION_MEJORA') {
            var objetivo = normalizarTexto(elementos.objetivo_mejora.value);
            var resultado = normalizarTexto(elementos.resultado_esperado.value);

            if (objetivo.length < 5 && resultado.length < 5) {
                marcarCampoInvalido(
                    elementos.objetivo_mejora,
                    'Escribe el objetivo o el resultado esperado.'
                );
                valido = false;
            }
        }

        if (elementos.trabajo_peligroso.checked) {
            var detallePeligro = normalizarTexto(elementos.detalle_trabajo_peligroso.value);
            if (detallePeligro.length < 3 || detallePeligro.length > 200) {
                marcarCampoInvalido(
                    elementos.detalle_trabajo_peligroso,
                    'Describe brevemente el peligro (3 a 200 caracteres).'
                );
                valido = false;
            }
        }

        if (
            tipo === 'CORRECTIVO_URGENTE' &&
            normalizarTexto(elementos.impacto_operacion.value).length < 5
        ) {
            marcarCampoInvalido(
                elementos.impacto_operacion,
                'Describe el impacto en la operación.'
            );
            valido = false;
        }

        return valido;
    }

    function habilitarCamposParaEnvio() {
        elementos.prioridad.disabled = false;
        elementos.area_id.disabled = false;
        elementos.proceso_id.disabled = false;
        elementos.equipo_id.disabled = false;
    }

    function bloquearAcciones(bloquear) {
        [
            elementos.btnAprobarSolicitud,
            elementos.btnRechazarSolicitud,
            elementos.btnGuardarCorrecciones,
            elementos.btnCerrarModal,
            elementos.btnCerrarSinCambios
        ].forEach(function (boton) {
            boton.disabled = bloquear;
        });
    }

    function marcarErrorServidor(error) {
        var campo = error && error.datos
            ? error.datos.campo
            : '';

        if (!campo) {
            return;
        }

        if (campo === 'recursos_urgencia') {
            elementos.panelRecursosUrgencia.scrollIntoView({behavior: 'smooth', block: 'center'});
            return;
        }

        var elemento = elementos[campo] || document.getElementById(campo);

        if (elemento) {
            marcarCampoInvalido(elemento, error.message || 'Revisa este campo.');
            elemento.focus();
        }
    }

    function marcarCampoInvalido(campo, mensaje) {
        campo.classList.add('spen-invalido');

        var contenedor = campo.closest('.spen-campo');

        if (!contenedor) {
            return;
        }

        var error = contenedor.querySelector('.spen-error-campo');

        if (error) {
            error.textContent = mensaje;
        }
    }

    function limpiarErrores() {
        elementos.formSolicitud
            .querySelectorAll('.spen-invalido')
            .forEach(function (campo) {
                campo.classList.remove('spen-invalido');
            });

        elementos.formSolicitud
            .querySelectorAll('.spen-error-campo')
            .forEach(function (error) {
                error.textContent = '';
            });
    }

    async function solicitarCerrar() {
        if (estado.guardando) {
            return;
        }

        if (estado.formularioSucio) {
            var confirmado = await SistemaUI.confirmar({
                titulo: '¿Cerrar sin guardar?',
                texto: 'Los cambios realizados se perderán.',
                textoConfirmar: 'Sí, cerrar',
                icono: 'warning',
                peligro: true
            });

            if (!confirmado) {
                return;
            }
        }

        cerrarModal(true);
    }

    function cerrarModal(forzar) {
        if (!forzar && estado.formularioSucio) {
            return;
        }

        elementos.modalSolicitud.hidden = true;
        document.body.classList.remove('spen-bloquear-scroll');
        elementos.formSolicitud.reset();
        limpiarErrores();

        estado.solicitudActual = null;
        estado.formularioSucio = false;

        if (estado.ultimoFoco && typeof estado.ultimoFoco.focus === 'function') {
            estado.ultimoFoco.focus();
        }
    }

    function badgeTipo(tipo) {
        var clase = 'spen-badge--normal';

        if (tipo === 'CORRECTIVO_URGENTE') {
            clase = 'spen-badge--urgente';
        } else if (tipo === 'MODIFICACION_MEJORA') {
            clase = 'spen-badge--mejora';
        }

        return '<span class="spen-badge ' + clase + '">' +
            escapar(textoTipo(tipo)) +
        '</span>';
    }

    function badgePrioridad(prioridad) {
        var clase = 'spen-badge--baja';

        if (prioridad === 'URGENTE') {
            clase = 'spen-badge--urgente';
        } else if (prioridad === 'ALTA') {
            clase = 'spen-badge--alta';
        } else if (prioridad === 'MEDIA') {
            clase = 'spen-badge--media';
        }

        return '<span class="spen-badge ' + clase + '">' +
            escapar(textoPrioridad(prioridad)) +
        '</span>';
    }

    function textoTipo(tipo) {
        var textos = {
            CORRECTIVO_PROGRAMABLE: 'Correctivo programable',
            MODIFICACION_MEJORA: 'Modificación o mejora',
            CORRECTIVO_URGENTE: 'Correctivo urgente'
        };

        return textos[tipo] || 'Solicitud';
    }

    function textoPrioridad(prioridad) {
        var textos = {
            URGENTE: 'Urgente',
            ALTA: 'Alta',
            MEDIA: 'Media',
            BAJA: 'Baja'
        };

        return textos[prioridad] || 'Sin prioridad';
    }

    function textoEspera(minutos) {
        var total = Math.max(0, Number(minutos || 0));
        var dias = Math.floor(total / 1440);
        var horas = Math.floor((total % 1440) / 60);
        var minutosRestantes = total % 60;

        if (dias > 0) {
            return dias + ' d ' + horas + ' h';
        }

        if (horas > 0) {
            return horas + ' h ' + minutosRestantes + ' min';
        }

        return minutosRestantes + ' min';
    }

    function normalizarTexto(valor) {
        return String(valor || '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function normalizarBusqueda(valor) {
        return normalizarTexto(valor)
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
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

    function escaparAtributo(valor) {
        return escapar(valor);
    }
})();
</script>

</body>
</html>