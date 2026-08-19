<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/seguridad.php';
sm_requerir_sesion(['ADMIN'], false);

$nombreAdmin = trim((string) ($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'Administrador'));
$cssHistorial = __DIR__ . '/../css/style_solicitudes_historial.css';
$versionCss = file_exists($cssHistorial) ? (string) filemtime($cssHistorial) : (string) time();

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
    <meta name="theme-color" content="#0b2944">
    <meta name="description" content="Historial administrativo y expedientes del Sistema de Mantenimiento">
    <title>Todas las solicitudes | Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="../css/style_solicitudes_historial.css?v=<?= htmlspecialchars($versionCss, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="shis-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="shis-icon-history" viewBox="0 0 24 24">
        <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>
        <path d="M3 3v5h5M12 7v5l3 2"/>
    </symbol>
    <symbol id="shis-icon-download" viewBox="0 0 24 24">
        <path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>
    </symbol>
    <symbol id="shis-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="shis-icon-file-sheet" viewBox="0 0 24 24">
        <path d="M6 2h8l4 4v16H6zM14 2v5h5"/>
        <path d="M9 12h6M9 16h6M12 9v10"/>
    </symbol>
    <symbol id="shis-icon-file-pdf" viewBox="0 0 24 24">
        <path d="M6 2h8l4 4v16H6zM14 2v5h5"/>
        <path d="M8.5 16v-5h2a1.5 1.5 0 0 1 0 3h-2M13 16v-5h1.2a2 2 0 0 1 0 4H13M17 11h2.5M17 13.5h2"/>
    </symbol>
    <symbol id="shis-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="shis-icon-clipboard" viewBox="0 0 24 24">
        <rect x="5" y="4" width="14" height="17" rx="2"/>
        <path d="M9 4V2h6v2M9 9h6M9 13h6M9 17h4"/>
    </symbol>
    <symbol id="shis-icon-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </symbol>
    <symbol id="shis-icon-play" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m10 8 6 4-6 4V8Z"/>
    </symbol>
    <symbol id="shis-icon-warning" viewBox="0 0 24 24">
        <path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
        <path d="M12 9v4M12 17h.01"/>
    </symbol>
    <symbol id="shis-icon-check" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8 12 2.6 2.6L16.5 9"/>
    </symbol>
    <symbol id="shis-icon-eye" viewBox="0 0 24 24">
        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
        <circle cx="12" cy="12" r="2.5"/>
    </symbol>
    <symbol id="shis-icon-chevron" viewBox="0 0 24 24">
        <path d="m9 18 6-6-6-6"/>
    </symbol>
    <symbol id="shis-icon-print" viewBox="0 0 24 24">
        <path d="M7 9V3h10v6M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/>
        <path d="M7 14h10v7H7z"/>
    </symbol>
</svg>

<main class="shis-page">
    <header class="shis-heading">
        <div class="shis-heading__pattern" aria-hidden="true"></div>

        <div class="shis-heading__content">
            <div class="shis-heading__copy">
                <p class="shis-eyebrow">
                    <span class="shis-eyebrow__icon"><svg><use href="#shis-icon-history"></use></svg></span>
                    Consulta administrativa
                </p>
                <h1>Todas las solicitudes</h1>
                <p>
                    Consulta el estado actual de cada mantenimiento y abre su expediente para revisar
                    programación, técnicos, tiempos, evidencias, cumplimiento y resultado final.
                </p>

                <div class="shis-heading__meta">
                    <span><i class="shis-live-dot" aria-hidden="true"></i> Historial protegido y de solo lectura</span>
                    <span>Administrador: <strong><?= htmlspecialchars($nombreAdmin, ENT_QUOTES, 'UTF-8') ?></strong></span>
                </div>
            </div>

            <div class="shis-heading__actions" aria-label="Acciones del historial">
                <div class="shis-export" id="contenedorExportar">
                    <button
                        type="button"
                        class="shis-btn shis-btn--secondary shis-btn--export"
                        id="btnExportar"
                        aria-haspopup="menu"
                        aria-controls="menuExportar"
                        aria-expanded="false"
                    >
                        <svg><use href="#shis-icon-download"></use></svg>
                        <span>Exportar</span>
                        <i aria-hidden="true">⌄</i>
                    </button>
                </div>

                <button type="button" class="shis-btn shis-btn--primary" id="btnActualizar">
                    <svg class="shis-btn__normal-icon"><use href="#shis-icon-refresh"></use></svg>
                    <span>Actualizar</span>
                    <i class="shis-btn__loader" aria-hidden="true"></i>
                </button>
            </div>

            <div class="shis-heading__mini-card" aria-hidden="true">
                <span><svg><use href="#shis-icon-clipboard"></use></svg></span>
                <div>
                    <small>Expedientes</small>
                    <strong>Consulta completa y protegida</strong>
                </div>
            </div>
        </div>
    </header>

    <div class="shis-status" id="estadoPagina" role="status" aria-live="polite">
        Cargando solicitudes...
    </div>

    <section class="shis-kpis" aria-label="Resumen de solicitudes">
        <article class="shis-kpi shis-kpi--total">
            <span class="shis-kpi__icon"><svg><use href="#shis-icon-clipboard"></use></svg></span>
            <span class="shis-kpi__body">
                <span>Total</span>
                <strong id="kpiTotal">0</strong>
                <small>Según los filtros</small>
            </span>
        </article>
        <article class="shis-kpi shis-kpi--pending">
            <span class="shis-kpi__icon"><svg><use href="#shis-icon-clock"></use></svg></span>
            <span class="shis-kpi__body">
                <span>Pendientes</span>
                <strong id="kpiPendientes">0</strong>
                <small>Esperan revisión</small>
            </span>
        </article>
        <article class="shis-kpi shis-kpi--active">
            <span class="shis-kpi__icon"><svg><use href="#shis-icon-play"></use></svg></span>
            <span class="shis-kpi__body">
                <span>En ejecución</span>
                <strong id="kpiActivas">0</strong>
                <small>En proceso o pausadas</small>
            </span>
        </article>
        <article class="shis-kpi shis-kpi--late">
            <span class="shis-kpi__icon"><svg><use href="#shis-icon-warning"></use></svg></span>
            <span class="shis-kpi__body">
                <span>Atrasadas</span>
                <strong id="kpiAtrasadas">0</strong>
                <small>Fuera de fecha</small>
            </span>
        </article>
        <article class="shis-kpi shis-kpi--done">
            <span class="shis-kpi__icon"><svg><use href="#shis-icon-check"></use></svg></span>
            <span class="shis-kpi__body">
                <span>Terminadas</span>
                <strong id="kpiTerminadas">0</strong>
                <small id="kpiPorcentaje">0% del total</small>
            </span>
        </article>
    </section>

    <section class="shis-card shis-filters-card">
        <form id="formFiltros" class="shis-filters" autocomplete="off">
            <label class="shis-field shis-field--search" for="filtroBusqueda">
                <span>Buscar</span>
                <div class="shis-search-box">
                    <span aria-hidden="true"><svg><use href="#shis-icon-search"></use></svg></span>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        maxlength="120"
                        placeholder="Folio, equipo, solicitante o descripción"
                    >
                </div>
            </label>

            <label class="shis-field" for="filtroEstado">
                <span>Estado</span>
                <select id="filtroEstado">
                    <option value="TODOS">Todos</option>
                    <option value="PENDIENTE">Pendiente</option>
                    <option value="APROBADO">Aprobado</option>
                    <option value="AGENDADO">Agendado</option>
                    <option value="EN_PROCESO">En proceso</option>
                    <option value="PAUSADO">Pausado</option>
                    <option value="ATRASADO">Atrasado</option>
                    <option value="TERMINADO">Terminado</option>
                    <option value="RECHAZADO">Rechazado</option>
                    <option value="CANCELADO">Cancelado</option>
                </select>
            </label>

            <label class="shis-field" for="filtroTipo">
                <span>Tipo</span>
                <select id="filtroTipo">
                    <option value="TODOS">Todos</option>
                    <option value="CORRECTIVO_PROGRAMABLE">Correctivo programable</option>
                    <option value="CORRECTIVO_URGENTE">Correctivo urgente</option>
                    <option value="MODIFICACION_MEJORA">Modificación o mejora</option>
                    <option value="RUTINARIO">Rutinario</option>
                </select>
            </label>

            <label class="shis-field" for="filtroTecnico">
                <span>Técnico</span>
                <select id="filtroTecnico">
                    <option value="">Todos</option>
                </select>
            </label>

            <div class="shis-filter-actions">
                <button type="submit" class="shis-btn shis-btn--primary">Aplicar</button>
                <button type="button" class="shis-btn shis-btn--ghost" id="btnLimpiar">Limpiar</button>
            </div>

            <details class="shis-more-filters">
                <summary>Filtrar por fecha</summary>
                <div class="shis-more-filters__body">
                    <label class="shis-field" for="filtroDesde">
                        <span>Desde</span>
                        <input type="date" id="filtroDesde">
                    </label>
                    <label class="shis-field" for="filtroHasta">
                        <span>Hasta</span>
                        <input type="date" id="filtroHasta">
                    </label>
                    <label class="shis-field" for="filtroOrden">
                        <span>Orden</span>
                        <select id="filtroOrden">
                            <option value="RECIENTES">Más recientes</option>
                            <option value="ANTIGUAS">Más antiguas</option>
                            <option value="ACTUALIZADAS">Actualizadas recientemente</option>
                            <option value="FOLIO">Por folio</option>
                        </select>
                    </label>
                </div>
            </details>
        </form>
    </section>

    <section class="shis-card shis-results-card">
        <header class="shis-results-head">
            <div>
                <p class="shis-eyebrow">Resultados</p>
                <h2>Solicitudes registradas</h2>
                <p id="textoResultados">Consultando información...</p>
            </div>

            <div class="shis-results-head__tools">
                <label class="shis-page-size">
                    <span>Mostrar</span>
                    <select id="selectorPorPagina" aria-label="Registros por página">
                        <option value="15">15</option>
                        <option value="30">30</option>
                        <option value="60">60</option>
                    </select>
                    <span>por página</span>
                </label>
                <span class="shis-count" id="contadorResultados">0 registros</span>
            </div>
        </header>

        <div
            class="shis-list"
            id="listaSolicitudes"
            aria-live="polite"
            aria-label="Listado desplazable de solicitudes registradas"
            tabindex="0"
        ></div>

        <div class="shis-empty" id="estadoVacio" hidden>
            <span aria-hidden="true">⌕</span>
            <h3>No hay solicitudes con esos filtros</h3>
            <p>Prueba otra búsqueda o limpia los filtros.</p>
            <button type="button" class="shis-btn shis-btn--secondary" id="btnLimpiarVacio">
                Mostrar todas
            </button>
        </div>

        <footer class="shis-pagination-wrap" id="contenedorPaginacion" hidden>
            <p id="textoPaginacion">Mostrando 0 resultados</p>
            <nav class="shis-pagination" id="paginacion" aria-label="Paginación"></nav>
        </footer>
    </section>

    <footer class="shis-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Historial administrativo · Los Chapeteados División Petfood</span>
    </footer>

    <div class="shis-tools-background" aria-hidden="true"></div>
</main>

<div class="shis-export-layer" id="capaExportar" hidden aria-hidden="true">
    <div class="shis-export__menu" id="menuExportar" role="menu" aria-label="Opciones de exportación" hidden>
        <div class="shis-export__head">
            <span class="shis-export__head-icon"><svg><use href="#shis-icon-download"></use></svg></span>
            <div>
                <strong>Exportar resultados</strong>
                <small>Se respetarán los filtros aplicados</small>
            </div>
        </div>

        <button type="button" id="btnExportarExcel" role="menuitem">
            <span class="shis-export__icon shis-export__icon--excel">
                <svg><use href="#shis-icon-file-sheet"></use></svg>
            </span>
            <span>
                <strong>Excel con formato</strong>
                <small>Todos los resultados filtrados</small>
            </span>
            <svg class="shis-export__arrow"><use href="#shis-icon-chevron"></use></svg>
        </button>

        <button type="button" id="btnExportarPdf" role="menuitem">
            <span class="shis-export__icon shis-export__icon--pdf">
                <svg><use href="#shis-icon-file-pdf"></use></svg>
            </span>
            <span>
                <strong>Documento PDF</strong>
                <small>Reporte listo para guardar o imprimir</small>
            </span>
            <svg class="shis-export__arrow"><use href="#shis-icon-chevron"></use></svg>
        </button>
    </div>
</div>

<div class="shis-modal" id="modalExpediente" hidden aria-hidden="true">
    <div class="shis-modal__backdrop" data-cerrar-modal></div>

    <section class="shis-modal__panel" role="dialog" aria-modal="true" aria-labelledby="expedienteTitulo">
        <header class="shis-modal__head">
            <div>
                <p class="shis-eyebrow">EXPEDIENTE DE SOLICITUD</p>
                <h2 id="expedienteTitulo">Cargando...</h2>
                <p id="expedienteSubtitulo">Consultando información.</p>
            </div>
            <button type="button" class="shis-icon-btn" data-cerrar-modal aria-label="Cerrar expediente">×</button>
        </header>

        <div class="shis-modal__loading" id="expedienteCargando" hidden>
            <span class="shis-spinner" aria-hidden="true"></span>
            <strong>Cargando expediente...</strong>
            <small>Esto sólo debe tomar unos segundos.</small>
        </div>

        <div class="shis-modal__error" id="expedienteError" hidden>
            <span aria-hidden="true">!</span>
            <h3>No fue posible cargar el expediente</h3>
            <p id="expedienteErrorTexto">Actualiza la página e inténtalo nuevamente.</p>
        </div>

        <div class="shis-modal__body" id="expedienteContenido" hidden></div>

        <footer class="shis-modal__foot">
            <button type="button" class="shis-btn shis-btn--danger" id="btnCancelarMantenimiento" hidden disabled>
                <svg><use href="#shis-icon-warning"></use></svg>
                <span id="textoCancelarMantenimiento">Cancelar mantenimiento</span>
            </button>
            <button type="button" class="shis-btn shis-btn--secondary" id="btnImprimir" disabled>
                <svg><use href="#shis-icon-print"></use></svg>
                Imprimir expediente
            </button>
            <button type="button" class="shis-btn shis-btn--primary" data-cerrar-modal>Cerrar</button>
        </footer>
    </section>
</div>

<?php include __DIR__ . '/../inc/alertas.php'; ?>

<script>
(function () {
    'use strict';

    var UI = window.SistemaUI;
    var ENDPOINT = '../funciones/solicitudes_historial_funciones.php';
    var SOLICITUD_INICIAL = <?php echo json_encode($solicitudInicial, JSON_UNESCAPED_UNICODE); ?>;

    function porId(id) {
        return document.getElementById(id);
    }

    var dom = {
        estadoPagina: porId('estadoPagina'),
        form: porId('formFiltros'),
        busqueda: porId('filtroBusqueda'),
        estado: porId('filtroEstado'),
        tipo: porId('filtroTipo'),
        tecnico: porId('filtroTecnico'),
        desde: porId('filtroDesde'),
        hasta: porId('filtroHasta'),
        orden: porId('filtroOrden'),
        btnActualizar: porId('btnActualizar'),
        btnExportar: porId('btnExportar'),
        capaExportar: porId('capaExportar'),
        menuExportar: porId('menuExportar'),
        btnExportarExcel: porId('btnExportarExcel'),
        btnExportarPdf: porId('btnExportarPdf'),
        porPagina: porId('selectorPorPagina'),
        btnLimpiar: porId('btnLimpiar'),
        btnLimpiarVacio: porId('btnLimpiarVacio'),
        lista: porId('listaSolicitudes'),
        vacio: porId('estadoVacio'),
        textoResultados: porId('textoResultados'),
        contador: porId('contadorResultados'),
        paginacionWrap: porId('contenedorPaginacion'),
        textoPaginacion: porId('textoPaginacion'),
        paginacion: porId('paginacion'),
        kpiTotal: porId('kpiTotal'),
        kpiPendientes: porId('kpiPendientes'),
        kpiActivas: porId('kpiActivas'),
        kpiAtrasadas: porId('kpiAtrasadas'),
        kpiTerminadas: porId('kpiTerminadas'),
        kpiPorcentaje: porId('kpiPorcentaje'),
        modal: porId('modalExpediente'),
        modalCargando: porId('expedienteCargando'),
        modalError: porId('expedienteError'),
        modalErrorTexto: porId('expedienteErrorTexto'),
        modalContenido: porId('expedienteContenido'),
        titulo: porId('expedienteTitulo'),
        subtitulo: porId('expedienteSubtitulo'),
        btnCancelarMantenimiento: porId('btnCancelarMantenimiento'),
        textoCancelarMantenimiento: porId('textoCancelarMantenimiento'),
        btnImprimir: porId('btnImprimir')
    };

    var estadoPagina = {
        cargando: false,
        detalleCargando: false,
        pagina: 1,
        catalogosCargados: false,
        autoAbierto: false,
        ultimoFoco: null,
        detalle: null,
        cancelandoMantenimiento: false
    };

    var etiquetas = {
        estado: {
            PENDIENTE: 'Pendiente',
            APROBADO: 'Aprobado',
            AGENDADO: 'Agendado',
            EN_PROCESO: 'En proceso',
            PAUSADO: 'Pausado',
            ATRASADO: 'Atrasado',
            TERMINADO: 'Terminado',
            RECHAZADO: 'Rechazado',
            CANCELADO: 'Cancelado'
        },
        tipo: {
            CORRECTIVO_PROGRAMABLE: 'Correctivo programable',
            CORRECTIVO_URGENTE: 'Correctivo urgente',
            MODIFICACION_MEJORA: 'Modificación o mejora',
            RUTINARIO: 'Rutinario'
        },
        prioridad: {
            BAJA: 'Baja',
            MEDIA: 'Media',
            ALTA: 'Alta',
            URGENTE: 'Urgente'
        },
        riesgo: {
            BAJO: 'Bajo',
            MEDIO: 'Medio',
            ALTO: 'Alto'
        },
        cumplimiento: {
            A_TIEMPO: 'A tiempo',
            TARDE: 'Terminó tarde',
            NO_REALIZADO: 'No realizado',
            PENDIENTE: 'Pendiente',
            NO_APLICA: 'No aplica',
            SIN_ASIGNACION: 'Sin asignación'
        },
        participacion: {
            ASIGNADO: 'Asignado',
            ACEPTADO: 'Aceptado',
            EN_PROCESO: 'En proceso',
            PAUSADO: 'Pausado',
            TERMINADO: 'Terminó',
            NO_PARTICIPO: 'No participó',
            RETIRADO: 'Retirado'
        },
        programacion: {
            PROGRAMADA: 'Programada',
            CUMPLIDA: 'Cumplida',
            VENCIDA: 'Vencida',
            REPROGRAMADA: 'Reprogramada',
            CANCELADA: 'Cancelada'
        },
        cierre: {
            TERMINADO: 'Terminado',
            PARCIAL: 'Parcial',
            PROVISIONAL: 'Provisional'
        },
        evento: {
            CREADA: 'Solicitud creada',
            EDITADA: 'Solicitud editada',
            APROBADA: 'Solicitud aprobada',
            RECHAZADA: 'Solicitud rechazada',
            PROGRAMADA: 'Mantenimiento programado',
            REPROGRAMADA: 'Mantenimiento reprogramado',
            ASIGNADA: 'Técnicos asignados',
            TECNICO_RETIRADO: 'Técnico retirado',
            URGENTE_PUBLICADA: 'Urgencia publicada',
            URGENTE_ACEPTADA: 'Urgencia aceptada',
            INICIADA: 'Mantenimiento iniciado',
            PAUSADA: 'Mantenimiento pausado',
            REANUDADA: 'Mantenimiento reanudado',
            TERMINADA: 'Mantenimiento terminado',
            INCUMPLIMIENTO_DETECTADO: 'Incumplimiento detectado',
            CUMPLIDA_TARDE: 'Cumplido tarde',
            NO_REALIZADA: 'No realizado',
            JUSTIFICADA: 'Incumplimiento justificado',
            CANCELADA: 'Solicitud cancelada',
            OTRO: 'Actualización'
        }
    };

    if (!UI) {
        dom.estadoPagina.textContent = 'No fue posible cargar las herramientas de la interfaz.';
        dom.estadoPagina.className = 'shis-status shis-status--error';
        return;
    }

    function texto(valor, defecto) {
        if (valor === null || valor === undefined) {
            return defecto || '';
        }
        var limpio = String(valor).trim();
        return limpio !== '' ? limpio : (defecto || '');
    }

    function numero(valor) {
        var n = parseInt(valor, 10);
        return isNaN(n) ? 0 : n;
    }

    function escapar(valor) {
        return texto(valor).replace(/[&<>'"]/g, function (caracter) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#039;',
                '"': '&quot;'
            }[caracter];
        });
    }

    function clase(valor) {
        return texto(valor, 'sin-dato').toLowerCase().replace(/[^a-z0-9]+/g, '-');
    }

    function etiqueta(grupo, valor, defecto) {
        var mapa = etiquetas[grupo] || {};
        return mapa[valor] || defecto || texto(valor, 'Sin registro');
    }

    function fecha(valor) {
        var limpio = texto(valor);
        if (!limpio) {
            return 'Sin fecha';
        }
        var parte = limpio.substring(0, 10).split('-');
        if (parte.length !== 3) {
            return limpio;
        }
        return parte[2] + '/' + parte[1] + '/' + parte[0];
    }

    function fechaHora(valor) {
        var limpio = texto(valor);
        if (!limpio) {
            return 'Sin registro';
        }
        var partes = limpio.replace('T', ' ').split(' ');
        return fecha(partes[0]) + (partes[1] ? ' · ' + partes[1].substring(0, 5) : '');
    }

    function duracion(segundos) {
        var total = Math.max(0, numero(segundos));
        if (total < 60) {
            return total === 0 ? 'Sin tiempo' : 'Menos de 1 min';
        }
        var dias = Math.floor(total / 86400);
        var horas = Math.floor((total % 86400) / 3600);
        var minutos = Math.floor((total % 3600) / 60);
        var partes = [];
        if (dias) {
            partes.push(dias + ' d');
        }
        if (horas) {
            partes.push(horas + ' h');
        }
        if (minutos || partes.length === 0) {
            partes.push(minutos + ' min');
        }
        return partes.slice(0, 2).join(' ');
    }

    function parametros(accion) {
        var params = new URLSearchParams();
        params.set('accion', accion || 'LISTAR');
        params.set('busqueda', dom.busqueda.value.trim());
        params.set('estado', dom.estado.value || 'TODOS');
        params.set('tipo', dom.tipo.value || 'TODOS');
        params.set('tecnico_id', dom.tecnico.value || '0');
        params.set('fecha_desde', dom.desde.value || '');
        params.set('fecha_hasta', dom.hasta.value || '');
        params.set('orden', dom.orden.value || 'RECIENTES');
        params.set('vigencia', 'ACTIVAS');
        params.set('pagina', String(estadoPagina.pagina));
        params.set('por_pagina', dom.porPagina ? dom.porPagina.value : '15');
        return params;
    }

    function establecerEstado(mensaje, tipo) {
        dom.estadoPagina.textContent = mensaje;
        dom.estadoPagina.className = 'shis-status' + (tipo ? ' shis-status--' + tipo : '');
        dom.estadoPagina.hidden = tipo === 'ok';
    }

    async function cargarListado() {
        if (estadoPagina.cargando) {
            return;
        }

        estadoPagina.cargando = true;
        establecerEstado('Cargando solicitudes...', 'loading');
        dom.btnActualizar.disabled = true;
        dom.btnActualizar.classList.add('is-loading');

        try {
            var respuesta = await UI.peticionJson(ENDPOINT + '?' + parametros('LISTAR').toString());
            pintarCatalogos(respuesta.catalogos || {});
            pintarResumen(respuesta.resumen || {});
            pintarListado(respuesta.registros || [], respuesta.paginacion || {});
            establecerEstado('Información actualizada.', 'ok');

            if (SOLICITUD_INICIAL > 0 && !estadoPagina.autoAbierto) {
                estadoPagina.autoAbierto = true;
                abrirExpediente(SOLICITUD_INICIAL);
            }
        } catch (error) {
            console.error(error);
            establecerEstado(error.message || 'No fue posible cargar las solicitudes.', 'error');
            dom.lista.innerHTML = '';
            dom.vacio.hidden = false;
            dom.textoResultados.textContent = 'No se pudo consultar la información.';
        } finally {
            estadoPagina.cargando = false;
            dom.btnActualizar.disabled = false;
            dom.btnActualizar.classList.remove('is-loading');
        }
    }

    function pintarCatalogos(catalogos) {
        if (estadoPagina.catalogosCargados) {
            return;
        }

        var tecnicos = catalogos.tecnicos || [];
        var fragmento = document.createDocumentFragment();

        tecnicos.forEach(function (tecnico) {
            var opcion = document.createElement('option');
            opcion.value = String(numero(tecnico.id));
            opcion.textContent = texto(tecnico.nombre, 'Técnico') + (numero(tecnico.activo) === 1 ? '' : ' (inactivo)');
            fragmento.appendChild(opcion);
        });

        dom.tecnico.appendChild(fragmento);
        estadoPagina.catalogosCargados = true;
    }

    function pintarResumen(resumen) {
        dom.kpiTotal.textContent = numero(resumen.total);
        dom.kpiPendientes.textContent = numero(resumen.pendientes);
        dom.kpiActivas.textContent = numero(resumen.activas);
        dom.kpiAtrasadas.textContent = numero(resumen.atrasadas);
        dom.kpiTerminadas.textContent = numero(resumen.terminadas);
        dom.kpiPorcentaje.textContent = texto(resumen.porcentaje_terminadas, '0') + '% del total';
    }

    function pintarListado(registros, paginacion) {
        dom.lista.innerHTML = '';
        dom.vacio.hidden = registros.length > 0;

        if (!registros.length) {
            dom.contador.textContent = '0 registros';
            dom.textoResultados.textContent = 'No encontramos resultados con los filtros seleccionados.';
            dom.paginacionWrap.hidden = true;
            return;
        }

        var fragmento = document.createDocumentFragment();
        registros.forEach(function (registro) {
            var contenedor = document.createElement('div');
            contenedor.innerHTML = tarjetaSolicitud(registro).trim();
            fragmento.appendChild(contenedor.firstElementChild);
        });
        dom.lista.appendChild(fragmento);
        dom.lista.scrollTop = 0;

        var total = numero(paginacion.total_registros);
        dom.contador.textContent = total + (total === 1 ? ' registro' : ' registros');
        dom.textoResultados.textContent = 'Mostrando ' + numero(paginacion.desde) + ' a ' + numero(paginacion.hasta) + ' de ' + total + '.';
        pintarPaginacion(paginacion);
    }

    function tarjetaSolicitud(r) {
        var cumplimiento = etiqueta('cumplimiento', r.cumplimiento_general, 'Sin resultado');
        var fechaPlaneada = r.fecha_programada
            ? '<span><b>Programada:</b> ' + escapar(fecha(r.fecha_programada)) + '</span>'
            : '<span><b>Programación:</b> Sin fecha</span>';
        var limite = r.fecha_limite
            ? '<span><b>Límite:</b> ' + escapar(fecha(r.fecha_limite)) + '</span>'
            : '';
        var atraso = numero(r.segundos_fuera_limite) > 0
            ? '<span class="shis-late-note">Fuera de fecha por ' + escapar(duracion(r.segundos_fuera_limite)) + '</span>'
            : '';

        return '' +
            '<article class="shis-request shis-request--' + clase(r.estado) + '">' +
                '<div class="shis-request__main">' +
                    '<div class="shis-badges">' +
                        '<span class="shis-badge shis-badge--estado-' + clase(r.estado) + '">' + escapar(etiqueta('estado', r.estado)) + '</span>' +
                        '<span class="shis-badge shis-badge--soft">' + escapar(etiqueta('tipo', r.tipo_solicitud)) + '</span>' +
                        '<span class="shis-badge shis-badge--prioridad-' + clase(r.prioridad) + '">' + escapar(etiqueta('prioridad', r.prioridad)) + '</span>' +
                    '</div>' +
                    '<h3>' + escapar(texto(r.nombre_equipo, 'Equipo sin nombre')) + '</h3>' +
                    '<p class="shis-request__identity">' +
                        escapar(texto(r.folio, 'Sin folio')) + ' · ' +
                        escapar(texto(r.codigo_equipo, 'Sin código')) + ' · ' +
                        escapar([r.departamento, r.area, r.proceso].filter(Boolean).join(' / ')) +
                    '</p>' +
                    '<p class="shis-request__description">' + escapar(texto(r.descripcion_solicitud, 'Sin descripción')) + '</p>' +
                    '<div class="shis-request__meta">' +
                        '<span><b>Solicitada:</b> ' + escapar(fecha(r.fecha_solicitud)) + '</span>' +
                        fechaPlaneada +
                        limite +
                        '<span><b>Técnicos:</b> ' + numero(r.total_tecnicos) + '</span>' +
                    '</div>' +
                '</div>' +
                '<aside class="shis-request__side">' +
                    '<div class="shis-result shis-result--' + clase(r.cumplimiento_general) + '">' +
                        '<small>Cumplimiento</small>' +
                        '<strong>' + escapar(cumplimiento) + '</strong>' +
                        atraso +
                    '</div>' +
                    '<button type="button" class="shis-btn shis-btn--primary shis-btn--small" data-expediente="' + numero(r.id) + '">' +
                        '<svg><use href="#shis-icon-eye"></use></svg>' +
                        'Ver expediente' +
                    '</button>' +
                '</aside>' +
            '</article>';
    }

    function pintarPaginacion(paginacion) {
        var actual = Math.max(1, numero(paginacion.pagina));
        var total = Math.max(1, numero(paginacion.total_paginas));
        dom.paginacion.innerHTML = '';
        dom.paginacionWrap.hidden = total <= 1;

        if (total <= 1) {
            return;
        }

        dom.textoPaginacion.textContent = 'Página ' + actual + ' de ' + total;
        dom.paginacion.appendChild(crearBotonPagina('‹', actual - 1, actual === 1, 'Página anterior'));

        paginasVisibles(actual, total).forEach(function (item) {
            if (item === '…') {
                var puntos = document.createElement('span');
                puntos.className = 'shis-pagination__ellipsis';
                puntos.textContent = '…';
                dom.paginacion.appendChild(puntos);
                return;
            }
            dom.paginacion.appendChild(crearBotonPagina(String(item), item, false, 'Página ' + item, item === actual));
        });

        dom.paginacion.appendChild(crearBotonPagina('›', actual + 1, actual === total, 'Página siguiente'));
    }

    function paginasVisibles(actual, total) {
        if (total <= 7) {
            return Array.from({ length: total }, function (_, indice) { return indice + 1; });
        }
        var resultado = [1];
        if (actual > 4) {
            resultado.push('…');
        }
        var inicio = Math.max(2, actual - 1);
        var fin = Math.min(total - 1, actual + 1);
        var i;
        for (i = inicio; i <= fin; i += 1) {
            resultado.push(i);
        }
        if (actual < total - 3) {
            resultado.push('…');
        }
        resultado.push(total);
        return resultado;
    }

    function crearBotonPagina(textoBoton, pagina, deshabilitado, aria, activo) {
        var boton = document.createElement('button');
        boton.type = 'button';
        boton.textContent = textoBoton;
        boton.disabled = deshabilitado;
        boton.setAttribute('aria-label', aria);
        boton.className = 'shis-pagination__button' + (activo ? ' is-active' : '');
        boton.addEventListener('click', function () {
            estadoPagina.pagina = pagina;
            cargarListado();
            dom.lista.scrollTo({ top: 0, behavior: 'smooth' });
        });
        return boton;
    }

    function mostrarEstadoModal(modo) {
        var cargando = modo === 'cargando';
        var contenido = modo === 'contenido';
        var error = modo === 'error';

        dom.modalCargando.hidden = !cargando;
        dom.modalContenido.hidden = !contenido;
        dom.modalError.hidden = !error;

        dom.modalCargando.style.display = cargando ? 'grid' : 'none';
        dom.modalContenido.style.display = contenido ? 'block' : 'none';
        dom.modalError.style.display = error ? 'grid' : 'none';
    }

    async function abrirExpediente(solicitudId) {
        if (estadoPagina.detalleCargando || !solicitudId) {
            return;
        }

        estadoPagina.detalleCargando = true;
        estadoPagina.ultimoFoco = document.activeElement;
        estadoPagina.detalle = null;
        dom.modal.hidden = false;
        dom.modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('shis-modal-open');
        dom.titulo.textContent = 'Cargando expediente...';
        dom.subtitulo.textContent = 'Consultando la información necesaria.';
        dom.btnImprimir.disabled = true;
        dom.btnCancelarMantenimiento.hidden = true;
        dom.btnCancelarMantenimiento.disabled = true;
        mostrarEstadoModal('cargando');

        try {
            var consulta = new URLSearchParams();
            consulta.set('accion', 'DETALLE');
            consulta.set('solicitud_id', String(solicitudId));
            var respuesta = await UI.peticionJson(ENDPOINT + '?' + consulta.toString());
            estadoPagina.detalle = respuesta;
            pintarExpediente(respuesta);
            configurarAccionesExpediente(respuesta.solicitud || {});
            mostrarEstadoModal('contenido');
            dom.btnImprimir.disabled = false;
        } catch (error) {
            console.error(error);
            dom.titulo.textContent = 'Expediente no disponible';
            dom.subtitulo.textContent = 'No se pudo consultar esta solicitud.';
            dom.modalErrorTexto.textContent = error.message || 'Actualiza la página e inténtalo nuevamente.';
            dom.btnCancelarMantenimiento.hidden = true;
            dom.btnCancelarMantenimiento.disabled = true;
            mostrarEstadoModal('error');
        } finally {
            estadoPagina.detalleCargando = false;
        }
    }

    function cerrarModal() {
        dom.modal.hidden = true;
        dom.modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('shis-modal-open');
        mostrarEstadoModal('ninguno');
        estadoPagina.detalle = null;
        dom.btnCancelarMantenimiento.hidden = true;
        dom.btnCancelarMantenimiento.disabled = true;
        if (estadoPagina.ultimoFoco && typeof estadoPagina.ultimoFoco.focus === 'function') {
            estadoPagina.ultimoFoco.focus();
        }
    }

    function pintarExpediente(datos) {
        var s = datos.solicitud || {};
        dom.titulo.textContent = texto(s.folio, 'Solicitud');
        dom.subtitulo.textContent = [s.nombre_equipo, s.codigo_equipo, etiqueta('estado', s.estado)].filter(Boolean).join(' · ');
        dom.modalContenido.innerHTML = plantillaExpediente(datos);
        dom.modalContenido.scrollTop = 0;
    }

    function plantillaExpediente(datos) {
        var s = datos.solicitud || {};
        var m = datos.metricas || {};
        var participantes = datos.participantes || [];
        var programaciones = datos.programaciones || [];
        var historial = datos.historial || [];
        var evidencias = datos.evidencias || [];
        var auditoria = datos.auditoria || [];
        var recursosRecomendados = datos.recursos_recomendados || {};
        var recursosUtilizados = datos.recursos_utilizados || {};

        return '' +
            plantillaCabeceraDetalle(s) +
            plantillaInformacionClave(s) +
            plantillaSolicitud(s) +
            plantillaRecursosMantenimiento(s, recursosRecomendados, recursosUtilizados) +
            plantillaDesempeno(s, m, participantes) +
            plantillaCierre(s) +
            plantillaInformacionAdicional(s, programaciones, historial, evidencias, auditoria);
    }

    function plantillaCabeceraDetalle(s) {
        return '' +
            '<section class="shis-detail-hero">' +
                '<div>' +
                    '<div class="shis-badges">' +
                        '<span class="shis-badge shis-badge--estado-' + clase(s.estado) + '">' + escapar(etiqueta('estado', s.estado)) + '</span>' +
                        '<span class="shis-badge shis-badge--soft">' + escapar(etiqueta('tipo', s.tipo_solicitud)) + '</span>' +
                        '<span class="shis-badge shis-badge--prioridad-' + clase(s.prioridad) + '">' + escapar(etiqueta('prioridad', s.prioridad)) + '</span>' +
                    '</div>' +
                    '<h3>' + escapar(texto(s.nombre_equipo, 'Equipo sin nombre')) + '</h3>' +
                    '<p>' + escapar(texto(s.codigo_equipo, 'Sin código')) + ' · ' + escapar([s.departamento, s.area, s.proceso].filter(Boolean).join(' / ')) + '</p>' +
                '</div>' +
                '<div class="shis-detail-hero__date">' +
                    '<span>Registrada</span>' +
                    '<strong>' + escapar(fecha(s.fecha_solicitud)) + '</strong>' +
                    '<small>' + escapar(texto(s.hora_solicitud).substring(0, 5)) + '</small>' +
                '</div>' +
            '</section>';
    }

    function plantillaInformacionClave(s) {
        return '' +
            '<section class="shis-facts" aria-label="Datos principales">' +
                datoClave('Solicitante', texto(s.solicitante, 'Sin registro')) +
                datoClave('Programada', s.fecha_programada ? fecha(s.fecha_programada) : 'Sin programación') +
                datoClave('Fecha límite', s.fecha_limite ? fecha(s.fecha_limite) : 'Sin fecha límite') +
                datoClave('Riesgo', etiqueta('riesgo', s.nivel_riesgo, 'Bajo')) +
            '</section>';
    }

    function datoClave(titulo, valor) {
        return '<article><span>' + escapar(titulo) + '</span><strong>' + escapar(valor) + '</strong></article>';
    }

    function plantillaSolicitud(s) {
        var diagnostico = [];
        if (texto(s.tipo_falla)) {
            diagnostico.push(datoTexto('Tipo de falla', s.tipo_falla));
        }
        if (texto(s.causa_averia) || texto(s.causa_desconocida_descripcion)) {
            diagnostico.push(datoTexto('Causa', texto(s.causa_averia, s.causa_desconocida_descripcion)));
        }
        if (texto(s.descripcion_falla)) {
            diagnostico.push(datoTexto('Detalle de la falla', s.descripcion_falla));
        }

        return '' +
            '<section class="shis-detail-section">' +
                '<header><div><p class="shis-eyebrow">TRABAJO SOLICITADO</p><h3>¿Qué se necesitaba realizar?</h3></div></header>' +
                '<div class="shis-text-block">' + escapar(texto(s.descripcion_solicitud, 'Sin descripción registrada.')) + '</div>' +
                (diagnostico.length ? '<div class="shis-data-grid">' + diagnostico.join('') + '</div>' : '') +
            '</section>';
    }

    function datoTexto(titulo, valor) {
        return '<article><span>' + escapar(titulo) + '</span><p>' + escapar(texto(valor, 'Sin registro')) + '</p></article>';
    }

    function plantillaRecursosMantenimiento(s, recomendados, utilizados) {
        var tieneCierre = numero(s.cierre_id) > 0;
        var textoActual = tieneCierre
            ? 'Registro declarado por el técnico que finalizó el mantenimiento.'
            : 'Se llenará cuando el técnico finalice el mantenimiento.';

        return '' +
            '<section class="shis-detail-section shis-detail-section--resources">' +
                '<header>' +
                    '<div>' +
                        '<p class="shis-eyebrow">RECURSOS DEL MANTENIMIENTO</p>' +
                        '<h3>Recomendación y uso real</h3>' +
                    '</div>' +
                '</header>' +
                '<p class="shis-resource-intro">La recomendación administrativa y lo realmente utilizado se conservan por separado para no alterar el historial.</p>' +
                '<div class="shis-resource-compare">' +
                    '<article class="shis-resource-panel shis-resource-panel--recommended">' +
                        '<header><span aria-hidden="true">◎</span><div><strong>Recomendado antes de iniciar</strong><small>Preparado por administración, plantilla o memoria vigente.</small></div></header>' +
                        plantillaListaRecursos(recomendados, 'recomendado', true) +
                    '</article>' +
                    '<article class="shis-resource-panel shis-resource-panel--actual">' +
                        '<header><span aria-hidden="true">✓</span><div><strong>Realmente utilizado</strong><small>' + escapar(textoActual) + '</small></div></header>' +
                        (tieneCierre
                            ? plantillaListaRecursos(utilizados, 'actual', true)
                            : '<p class="shis-resource-pending">Pendiente de cierre técnico.</p>') +
                    '</article>' +
                '</div>' +
            '</section>';
    }

    function plantillaListaRecursos(grupo, modo, mostrarDescripcion) {
        grupo = grupo || {};
        var herramientas = Array.isArray(grupo.herramientas) ? grupo.herramientas : [];
        var refacciones = Array.isArray(grupo.refacciones) ? grupo.refacciones : [];
        var sinHerramientas = numero(grupo.sin_herramientas_utilizadas) === 1;
        var sinRefacciones = numero(grupo.sin_refacciones_utilizadas) === 1;

        return '' +
            plantillaTipoRecursos(
                'Herramientas',
                '🔧',
                herramientas,
                modo,
                sinHerramientas,
                mostrarDescripcion
            ) +
            plantillaTipoRecursos(
                'Refacciones',
                '⚙️',
                refacciones,
                modo,
                sinRefacciones,
                mostrarDescripcion
            );
    }

    function plantillaTipoRecursos(titulo, icono, recursos, modo, sinUso, mostrarDescripcion) {
        var contenido = '';

        if (recursos.length > 0) {
            contenido = '<ul class="shis-resource-list">' + recursos.map(function (recurso) {
                var otro = numero(recurso.es_otro) === 1
                    ? '<em class="shis-resource-tag">Otro</em>'
                    : '';
                var codigo = texto(recurso.codigo, '')
                    ? '<small class="shis-resource-code">' + escapar(recurso.codigo) + '</small>'
                    : '';
                var descripcion = mostrarDescripcion && texto(recurso.descripcion, '')
                    ? '<p>' + escapar(recurso.descripcion) + '</p>'
                    : '';
                var sugerencia = texto(recurso.estado_sugerencia, '')
                    ? '<small class="shis-resource-suggestion">Sugerencia ' + escapar(recurso.estado_sugerencia.toLowerCase()) + '</small>'
                    : '';

                return '' +
                    '<li>' +
                        '<span class="shis-resource-item-icon" aria-hidden="true">' + icono + '</span>' +
                        '<div>' +
                            '<strong>' + escapar(texto(recurso.nombre, 'Recurso sin nombre')) + otro + '</strong>' +
                            codigo + descripcion + sugerencia +
                        '</div>' +
                    '</li>';
            }).join('') + '</ul>';
        } else if (modo === 'actual' && sinUso) {
            contenido = '<p class="shis-resource-empty shis-resource-empty--confirmed">El técnico confirmó que no utilizó ' + escapar(titulo.toLowerCase()) + '.</p>';
        } else {
            contenido = '<p class="shis-resource-empty">' +
                (modo === 'actual'
                    ? 'No se registraron ' + escapar(titulo.toLowerCase()) + ' realmente utilizadas.'
                    : 'No hubo ' + escapar(titulo.toLowerCase()) + ' recomendadas.') +
            '</p>';
        }

        return '' +
            '<section class="shis-resource-group">' +
                '<h4><span aria-hidden="true">' + icono + '</span>' + escapar(titulo) + '</h4>' +
                contenido +
            '</section>';
    }

    function plantillaDesempeno(s, m, participantes) {
        var tieneDatos = participantes.length > 0 || numero(m.total_segundos_activos) > 0;
        if (!tieneDatos) {
            return '' +
                '<section class="shis-detail-section">' +
                    '<header><div><p class="shis-eyebrow">EJECUCIÓN</p><h3>Técnicos y tiempos</h3></div></header>' +
                    '<div class="shis-simple-empty">Esta solicitud todavía no tiene ejecución registrada.</div>' +
                '</section>';
        }

        var cumplimientoGeneral = 'Pendiente';
        if (numero(m.no_realizado) > 0) {
            cumplimientoGeneral = 'No realizado';
        } else if (numero(m.tarde) > 0) {
            cumplimientoGeneral = 'Con retraso';
        } else if (numero(m.a_tiempo) > 0 && numero(m.pendiente) === 0) {
            cumplimientoGeneral = 'A tiempo';
        }

        var filas = participantes.map(function (p) {
            var retraso = numero(p.segundos_retraso) > 0
                ? '<small class="shis-table-late">+' + escapar(duracion(p.segundos_retraso)) + '</small>'
                : '';
            return '' +
                '<tr>' +
                    '<td><strong>' + escapar(texto(p.tecnico, 'Técnico')) + '</strong><small>' + escapar(texto(p.especialidad, texto(p.turno, ''))) + '</small></td>' +
                    '<td>' + escapar(etiqueta('participacion', p.estado_participacion)) + '</td>' +
                    '<td>' + escapar(duracion(p.total_segundos_activos)) + '<small>' + escapar(numero(p.total_segundos_pausa) > 0 ? duracion(p.total_segundos_pausa) + ' en pausa' : 'Sin pausas') + '</small></td>' +
                    '<td><span class="shis-mini-status shis-mini-status--' + clase(p.resultado_cumplimiento) + '">' + escapar(etiqueta('cumplimiento', p.resultado_cumplimiento)) + '</span>' + retraso + '</td>' +
                '</tr>';
        }).join('');

        return '' +
            '<section class="shis-detail-section">' +
                '<header><div><p class="shis-eyebrow">EJECUCIÓN</p><h3>Técnicos y tiempos</h3></div></header>' +
                '<div class="shis-metrics">' +
                    metrica('Técnicos', numero(m.total_asignaciones), numero(m.participaron) + ' con ejecución') +
                    metrica('Tiempo activo', duracion(m.total_segundos_activos), 'Suma del equipo') +
                    metrica('Tiempo en pausa', duracion(m.total_segundos_pausa), numero(m.total_pausas) + ' pausas') +
                    metrica('Cumplimiento', cumplimientoGeneral, numero(m.segundos_fuera_limite) > 0 ? duracion(m.segundos_fuera_limite) + ' fuera de fecha' : 'Sin retraso general') +
                '</div>' +
                '<div class="shis-table-wrap">' +
                    '<table class="shis-table">' +
                        '<thead><tr><th>Técnico</th><th>Participación</th><th>Tiempo</th><th>Resultado</th></tr></thead>' +
                        '<tbody>' + filas + '</tbody>' +
                    '</table>' +
                '</div>' +
            '</section>';
    }

    function metrica(titulo, valor, ayuda) {
        return '<article><span>' + escapar(titulo) + '</span><strong>' + escapar(valor) + '</strong><small>' + escapar(ayuda) + '</small></article>';
    }

    function plantillaCierre(s) {
        if (!numero(s.cierre_id)) {
            if (s.estado === 'RECHAZADO') {
                return seccionMensaje('RESULTADO', 'Solicitud rechazada', texto(s.motivo_rechazo, 'No se registró un motivo.'));
            }
            if (s.estado === 'CANCELADO') {
                return seccionMensaje('RESULTADO', 'Solicitud cancelada', texto(s.motivo_cancelacion_programacion, 'No se registró un motivo.'));
            }
            return '';
        }

        var pendientes = texto(s.que_falto)
            ? '<div class="shis-callout shis-callout--warning"><strong>Trabajo pendiente</strong><p>' + escapar(s.que_falto) + '</p></div>'
            : '';
        var observaciones = texto(s.observaciones_cierre)
            ? datoTexto('Observaciones del cierre', s.observaciones_cierre)
            : '';

        return '' +
            '<section class="shis-detail-section shis-detail-section--done">' +
                '<header>' +
                    '<div><p class="shis-eyebrow">CIERRE</p><h3>Resultado del mantenimiento</h3></div>' +
                    '<span class="shis-badge shis-badge--estado-' + clase(s.trabajo_quedo) + '">' + escapar(etiqueta('cierre', s.trabajo_quedo)) + '</span>' +
                '</header>' +
                '<div class="shis-text-block"><strong>Trabajo realizado</strong><p>' + escapar(texto(s.descripcion_trabajo_realizado, 'Sin descripción registrada.')) + '</p></div>' +
                pendientes +
                '<div class="shis-data-grid">' +
                    datoTexto('Cerrado por', texto(s.cerrado_por, 'Sin registro')) +
                    datoTexto('Fecha de cierre', fechaHora(s.fecha_hora_cierre)) +
                    datoTexto('Limpieza del área', numero(s.realizo_limpieza_area) === 1 ? 'Realizada' : 'No registrada') +
                    datoTexto('Área ordenada', numero(s.area_ordenada_libre_componentes) === 1 ? 'Sí' : 'No registrada') +
                    observaciones +
                '</div>' +
            '</section>';
    }

    function seccionMensaje(eyebrow, titulo, mensaje) {
        return '' +
            '<section class="shis-detail-section">' +
                '<header><div><p class="shis-eyebrow">' + escapar(eyebrow) + '</p><h3>' + escapar(titulo) + '</h3></div></header>' +
                '<div class="shis-callout"><p>' + escapar(mensaje) + '</p></div>' +
            '</section>';
    }

    function plantillaInformacionAdicional(s, programaciones, historial, evidencias, auditoria) {
        var extras = datosAdicionales(s);
        var bloques = [];

        if (extras.length) {
            bloques.push('' +
                '<details class="shis-disclosure">' +
                    '<summary>Información adicional de la solicitud <span>' + extras.length + '</span></summary>' +
                    '<div class="shis-data-grid">' + extras.join('') + '</div>' +
                '</details>');
        }

        if (programaciones.length) {
            bloques.push('' +
                '<details class="shis-disclosure">' +
                    '<summary>Historial de programación <span>' + programaciones.length + '</span></summary>' +
                    '<div class="shis-compact-list">' + programaciones.map(programacionItem).join('') + '</div>' +
                '</details>');
        }

        if (historial.length) {
            var eventos = historial.slice(Math.max(0, historial.length - 30)).reverse();
            bloques.push('' +
                '<details class="shis-disclosure">' +
                    '<summary>Trazabilidad <span>' + historial.length + '</span></summary>' +
                    '<div class="shis-timeline">' + eventos.map(historialItem).join('') + '</div>' +
                '</details>');
        }

        if (evidencias.length) {
            bloques.push('' +
                '<details class="shis-disclosure">' +
                    '<summary>Evidencias y documentos <span>' + evidencias.length + '</span></summary>' +
                    '<div class="shis-files">' + evidencias.map(evidenciaItem).join('') + '</div>' +
                '</details>');
        }

        if (auditoria.length) {
            bloques.push('' +
                '<details class="shis-disclosure">' +
                    '<summary>Correcciones administrativas <span>' + auditoria.length + '</span></summary>' +
                    '<div class="shis-compact-list">' + auditoria.slice(0, 20).map(auditoriaItem).join('') + '</div>' +
                '</details>');
        }

        if (!bloques.length) {
            return '';
        }

        return '' +
            '<section class="shis-detail-section shis-detail-section--additional">' +
                '<header><div><p class="shis-eyebrow">MÁS INFORMACIÓN</p><h3>Consulta sólo cuando sea necesario</h3></div></header>' +
                '<div class="shis-disclosures">' + bloques.join('') + '</div>' +
            '</section>';
    }

    function datosAdicionales(s) {
        var datos = [];
        var agregar = function (titulo, valor) {
            if (texto(valor)) {
                datos.push(datoTexto(titulo, valor));
            }
        };

        agregar('Impacto en la operación', s.impacto_operacion);
        agregar('Objetivo de mejora', s.objetivo_mejora);
        agregar('Resultado esperado', s.resultado_esperado);
        agregar('Justificación', s.justificacion_mejora);
        agregar('Costo contra beneficio', s.costo_vs_beneficio);
        agregar('Observaciones del solicitante', s.observaciones_solicitante);
        agregar('Observaciones de revisión', s.observaciones_revision);
        agregar('Causas de mejora', s.causas_mejora);
        agregar('Requiere paro del equipo', numero(s.requiere_paro_equipo) === 1 ? 'Sí' : 'No');
        agregar('Trabajo peligroso', numero(s.trabajo_peligroso) === 1 ? 'Sí' : 'No');
        return datos;
    }

    function programacionItem(p) {
        var motivo = texto(p.motivo_reprogramacion, texto(p.motivo_programacion, texto(p.motivo_cancelacion)));
        return '' +
            '<article>' +
                '<div><strong>' + escapar(fecha(p.fecha_programada)) + '</strong><small>Límite: ' + escapar(fecha(p.fecha_limite)) + '</small></div>' +
                '<div><span class="shis-mini-status shis-mini-status--' + clase(p.estado) + '">' + escapar(etiqueta('programacion', p.estado)) + '</span><small>' + escapar(texto(p.programado_por, 'Sin registro')) + '</small></div>' +
                (motivo ? '<p>' + escapar(motivo) + '</p>' : '') +
            '</article>';
    }

    function historialItem(h) {
        var evento = texto(h.evento, 'OTRO');
        var estadoCambio = '';

        if (texto(h.estado_anterior) || texto(h.estado_nuevo)) {
            estadoCambio = '<span class="shis-timeline__state">' +
                escapar(etiqueta('estado', h.estado_anterior, texto(h.estado_anterior, '—'))) +
                '<i aria-hidden="true">→</i>' +
                escapar(etiqueta('estado', h.estado_nuevo, texto(h.estado_nuevo, '—'))) +
            '</span>';
        }

        return '' +
            '<article class="shis-timeline__item shis-timeline__item--' + clase(evento) + '">' +
                '<span class="shis-timeline__dot" aria-hidden="true"></span>' +
                '<div class="shis-timeline__card">' +
                    '<header class="shis-timeline__head">' +
                        '<span class="shis-timeline__event">' + escapar(etiqueta('evento', evento, evento)) + '</span>' +
                        '<time datetime="' + escapar(texto(h.fecha_evento)) + '">' + escapar(fechaHora(h.fecha_evento)) + '</time>' +
                    '</header>' +
                    '<p>' + escapar(texto(h.descripcion, 'Sin descripción')) + '</p>' +
                    '<footer>' +
                        '<span>Responsable: <strong>' + escapar(texto(h.actor, 'Sistema')) + '</strong></span>' +
                        estadoCambio +
                    '</footer>' +
                '</div>' +
            '</article>';
    }

    function evidenciaItem(e) {
        var enlace = texto(e.ruta_publica);
        var contenido = '<strong>' + escapar(texto(e.nombre_original, 'Archivo')) + '</strong>' +
            '<small>' + escapar(texto(e.tipo_evidencia, 'Evidencia')) + ' · ' + escapar(fechaHora(e.fecha_registro)) + '</small>';
        if (enlace) {
            contenido = '<a href="' + escapar(enlace) + '" target="_blank" rel="noopener">' + contenido + '</a>';
        }
        return '<article><span aria-hidden="true">▧</span><div>' + contenido + '</div></article>';
    }

    function auditoriaItem(a) {
        return '' +
            '<article>' +
                '<div><strong>' + escapar(texto(a.accion, 'Corrección')) + '</strong><small>' + escapar(texto(a.tabla_afectada, 'Registro')) + '</small></div>' +
                '<div><small>' + escapar(fechaHora(a.fecha_evento)) + '</small><small>' + escapar(texto(a.actor, 'Sistema')) + '</small></div>' +
                (texto(a.motivo) ? '<p>' + escapar(a.motivo) + '</p>' : '') +
            '</article>';
    }

    function configurarAccionesExpediente(solicitud) {
        var puedeCancelar = numero(solicitud.puede_cancelar_mantenimiento) === 1;
        dom.btnCancelarMantenimiento.hidden = !puedeCancelar;
        dom.btnCancelarMantenimiento.disabled = !puedeCancelar || estadoPagina.cancelandoMantenimiento;

        if (puedeCancelar) {
            dom.textoCancelarMantenimiento.textContent = numero(solicitud.mantenimiento_iniciado) === 1
                ? 'Detener y cancelar mantenimiento'
                : 'Cancelar mantenimiento';
        }
    }

    async function cancelarMantenimientoActual() {
        var respuestaDetalle = estadoPagina.detalle || {};
        var solicitud = respuestaDetalle.solicitud || {};

        if (
            estadoPagina.cancelandoMantenimiento
            || numero(solicitud.puede_cancelar_mantenimiento) !== 1
        ) {
            return;
        }

        var iniciada = numero(solicitud.mantenimiento_iniciado) === 1;
        var participantes = numero(solicitud.participantes_activos);
        var tipo = etiqueta('tipo', solicitud.tipo_solicitud, texto(solicitud.tipo_solicitud, 'Mantenimiento'));
        var resultado = await Swal.fire({
            icon: 'warning',
            title: iniciada ? '¿Detener y cancelar el mantenimiento?' : '¿Cancelar el mantenimiento?',
            html:
                '<div class="shis-cancel-warning">' +
                    '<p><strong>' + escapar(tipo) + '</strong> <strong>' + escapar(texto(solicitud.folio)) + '</strong> quedará cancelado.</p>' +
                    (iniciada
                        ? '<p><strong>Las ejecuciones se detendrán inmediatamente</strong>, se cerrarán las pausas abiertas y ningún técnico podrá continuar ni finalizar esta actividad.</p>'
                        : '<p>Las asignaciones o aceptaciones activas de los técnicos serán retiradas.</p>') +
                    (participantes > 0
                        ? '<p>Participantes activos o asignados: <strong>' + participantes + '</strong>.</p>'
                        : '') +
                    '<p>La solicitud conservará sus tiempos y trazabilidad, pero no se creará un cierre técnico ni se registrará como terminada, parcial o provisional.</p>' +
                    (solicitud.tipo_solicitud === 'RUTINARIO'
                        ? (iniciada
                            ? '<p>El periodo de la rutina quedará cancelado y conservará el tiempo trabajado. No podrá reutilizarse ese mismo periodo; las siguientes fechas de la plantilla continuarán normalmente.</p>'
                            : '<p>El periodo de la rutina quedará cancelado y podrá reactivarse posteriormente desde el módulo de rutinas, sin borrar este registro.</p>')
                        : '') +
                '</div>',
            input: 'textarea',
            inputLabel: 'Motivo obligatorio de cancelación',
            inputPlaceholder: 'Ej. Se verificó que el trabajo ya no es necesario o la solicitud fue creada por error.',
            inputAttributes: {
                minlength: '15',
                maxlength: '500',
                rows: '5'
            },
            inputValidator: function (valor) {
                var motivo = texto(valor);
                if (motivo.length < 15) {
                    return 'Escribe un motivo de al menos 15 caracteres.';
                }
                if (motivo.length > 500) {
                    return 'El motivo no puede superar 500 caracteres.';
                }
                return undefined;
            },
            showCancelButton: true,
            confirmButtonText: iniciada ? 'Sí, detener y cancelar' : 'Sí, cancelar mantenimiento',
            cancelButtonText: 'Volver',
            confirmButtonColor: '#b4232d',
            reverseButtons: true,
            focusCancel: true,
            allowOutsideClick: false,
            heightAuto: false
        });

        if (!resultado.isConfirmed) {
            return;
        }

        var datos = new FormData();
        datos.set('accion', 'CANCELAR_MANTENIMIENTO');
        datos.set('solicitud_id', String(solicitud.id));
        datos.set('motivo_cancelacion', texto(resultado.value));

        estadoPagina.cancelandoMantenimiento = true;
        dom.btnCancelarMantenimiento.disabled = true;
        var textoOriginal = dom.textoCancelarMantenimiento.textContent;
        dom.textoCancelarMantenimiento.textContent = 'Cancelando...';

        try {
            var respuesta = await UI.peticionJson(ENDPOINT, {
                method: 'POST',
                body: datos
            });

            await UI.exito(
                'Mantenimiento cancelado',
                respuesta.mensaje || 'El mantenimiento y sus actividades fueron cancelados.'
            );

            cerrarModal();
            await cargarListado();
        } catch (error) {
            console.error(error);
            await UI.error(
                'No fue posible cancelar el mantenimiento',
                error.message || 'Actualiza el expediente e inténtalo nuevamente.'
            );

            if (estadoPagina.detalle && estadoPagina.detalle.solicitud) {
                configurarAccionesExpediente(estadoPagina.detalle.solicitud);
            }
        } finally {
            estadoPagina.cancelandoMantenimiento = false;
            dom.textoCancelarMantenimiento.textContent = textoOriginal;
            if (!dom.btnCancelarMantenimiento.hidden) {
                dom.btnCancelarMantenimiento.disabled = false;
            }
        }
    }

    function limpiarFiltros() {
        dom.form.reset();
        dom.estado.value = 'TODOS';
        dom.tipo.value = 'TODOS';
        dom.orden.value = 'RECIENTES';
        estadoPagina.pagina = 1;
        cargarListado();
    }

    function posicionarMenuExportar() {
        if (!dom.menuExportar || dom.menuExportar.hidden) {
            return;
        }

        var margen = 12;
        var separacion = 10;
        var boton = dom.btnExportar.getBoundingClientRect();
        var ancho = dom.menuExportar.offsetWidth;
        var alto = dom.menuExportar.offsetHeight;
        var izquierda = boton.right - ancho;
        var arriba = boton.bottom + separacion;

        izquierda = Math.max(margen, Math.min(izquierda, window.innerWidth - ancho - margen));

        if (arriba + alto > window.innerHeight - margen && boton.top - alto - separacion >= margen) {
            arriba = boton.top - alto - separacion;
            dom.menuExportar.classList.add('is-above');
        } else {
            dom.menuExportar.classList.remove('is-above');
        }

        arriba = Math.max(margen, Math.min(arriba, window.innerHeight - alto - margen));
        dom.menuExportar.style.left = Math.round(izquierda) + 'px';
        dom.menuExportar.style.top = Math.round(arriba) + 'px';
    }

    function cerrarMenuExportar(devolverFoco) {
        if (!dom.menuExportar) {
            return;
        }

        dom.menuExportar.hidden = true;
        if (dom.capaExportar) {
            dom.capaExportar.hidden = true;
            dom.capaExportar.setAttribute('aria-hidden', 'true');
        }
        dom.btnExportar.setAttribute('aria-expanded', 'false');
        dom.btnExportar.classList.remove('is-open');

        if (devolverFoco) {
            dom.btnExportar.focus({ preventScroll: true });
        }
    }

    function alternarMenuExportar(evento) {
        evento.stopPropagation();
        var abrir = dom.menuExportar.hidden;

        if (!abrir) {
            cerrarMenuExportar(true);
            return;
        }

        if (dom.capaExportar) {
            dom.capaExportar.hidden = false;
            dom.capaExportar.setAttribute('aria-hidden', 'false');
        }
        dom.menuExportar.hidden = false;
        dom.btnExportar.setAttribute('aria-expanded', 'true');
        dom.btnExportar.classList.add('is-open');

        window.requestAnimationFrame(function () {
            posicionarMenuExportar();
            dom.btnExportarExcel.focus({ preventScroll: true });
        });
    }

    function exportar(formato) {
        var accion = formato === 'PDF' ? 'EXPORTAR_PDF' : 'EXPORTAR_EXCEL';
        var params = parametros(accion);
        params.delete('pagina');
        params.delete('por_pagina');
        cerrarMenuExportar();

        if (formato === 'PDF') {
            window.open(ENDPOINT + '?' + params.toString(), '_blank', 'noopener');
            return;
        }

        window.location.assign(ENDPOINT + '?' + params.toString());
    }

    dom.form.addEventListener('submit', function (evento) {
        evento.preventDefault();
        estadoPagina.pagina = 1;
        cargarListado();
    });

    dom.btnActualizar.addEventListener('click', cargarListado);
    dom.btnExportar.addEventListener('click', alternarMenuExportar);
    dom.btnExportarExcel.addEventListener('click', function () { exportar('EXCEL'); });
    dom.btnExportarPdf.addEventListener('click', function () { exportar('PDF'); });
    dom.btnLimpiar.addEventListener('click', limpiarFiltros);
    dom.btnLimpiarVacio.addEventListener('click', limpiarFiltros);

    if (dom.porPagina) {
        dom.porPagina.addEventListener('change', function () {
            estadoPagina.pagina = 1;
            cargarListado();
        });
    }

    document.addEventListener('click', function (evento) {
        if (
            !evento.target.closest('#contenedorExportar')
            && !evento.target.closest('#menuExportar')
        ) {
            cerrarMenuExportar(false);
        }
    });

    window.addEventListener('resize', posicionarMenuExportar);
    window.addEventListener('scroll', posicionarMenuExportar, { passive: true });

    dom.lista.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-expediente]');
        if (boton) {
            abrirExpediente(numero(boton.getAttribute('data-expediente')));
        }
    });

    document.querySelectorAll('[data-cerrar-modal]').forEach(function (elemento) {
        elemento.addEventListener('click', cerrarModal);
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key !== 'Escape') {
            return;
        }

        if (!dom.menuExportar.hidden) {
            cerrarMenuExportar(true);
            return;
        }

        if (!dom.modal.hidden) {
            cerrarModal();
        }
    });

    dom.btnCancelarMantenimiento.addEventListener('click', cancelarMantenimientoActual);

    dom.btnImprimir.addEventListener('click', function () {
        document.body.classList.add('shis-printing');
        window.print();
    });

    window.addEventListener('afterprint', function () {
        document.body.classList.remove('shis-printing');
    });

    cargarListado();
}());
</script>
</body>
</html> 