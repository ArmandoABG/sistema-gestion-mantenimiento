<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/seguridad.php';
sm_requerir_sesion(['TECNICO'], false);

$nombreTecnico = trim((string) (
    $_SESSION['nombre_completo']
    ?? $_SESSION['usuario']
    ?? 'Técnico'
));

$cssMantenimientosFinalizados = __DIR__ . '/../css/style_mantenimientos_finalizados.css';
$versionCss = is_file($cssMantenimientosFinalizados)
    ? (string) filemtime($cssMantenimientosFinalizados)
    : (string) time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0b2b47">
    <meta
        name="description"
        content="Historial, tiempos, cierres y trazabilidad de mantenimientos del técnico"
    >
    <title>Historial de mantenimientos | Sistema de Mantenimiento</title>
    <link
        rel="preload"
        as="image"
        href="../imagenes/herramienta_abajo.png"
    >
    <link
        rel="stylesheet"
        href="../css/style_mantenimientos_finalizados.css?v=<?= htmlspecialchars($versionCss, ENT_QUOTES, 'UTF-8') ?>"
    >
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>


<svg class="mfin-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="mfin-icon-sparkles" viewBox="0 0 24 24">
        <path d="m12 3-1.2 3.8L7 8l3.8 1.2L12 13l1.2-3.8L17 8l-3.8-1.2L12 3Z"/>
        <path d="m5 15-.7 2.3L2 18l2.3.7L5 21l.7-2.3L8 18l-2.3-.7L5 15Z"/>
        <path d="m19 13-.7 2.3-2.3.7 2.3.7L19 19l.7-2.3 2.3-.7L19 13Z"/>
    </symbol>
    <symbol id="mfin-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="mfin-icon-download" viewBox="0 0 24 24">
        <path d="M12 3v12M7 10l5 5 5-5"/>
        <path d="M4 20h16"/>
    </symbol>
    <symbol id="mfin-icon-history" viewBox="0 0 24 24">
        <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>
        <path d="M3 3v5h5M12 7v5l3 2"/>
    </symbol>
    <symbol id="mfin-icon-shield" viewBox="0 0 24 24">
        <path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="mfin-icon-filter" viewBox="0 0 24 24">
        <path d="M4 5h16M7 12h10M10 19h4"/>
    </symbol>
    <symbol id="mfin-icon-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </symbol>
    <symbol id="mfin-icon-check" viewBox="0 0 24 24">
        <path d="m5 12 4 4L19 6"/>
    </symbol>
    <symbol id="mfin-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="mfin-icon-print" viewBox="0 0 24 24">
        <path d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
        <path d="M7 14h10v7H7z"/>
    </symbol>
</svg>


<main class="mfin-page">
    <div class="mfin-ambient mfin-ambient--one" aria-hidden="true"></div>
    <div class="mfin-ambient mfin-ambient--two" aria-hidden="true"></div>

    <section class="mfin-heading mfin-hero" aria-labelledby="tituloHistorialMantenimientos">
        <div class="mfin-hero__content">
            <div class="mfin-hero__copy">
                <p class="mfin-eyebrow">
                    <span class="mfin-eyebrow__icon" aria-hidden="true">
                        <svg><use href="#mfin-icon-sparkles"></use></svg>
                    </span>
                    Mi trabajo realizado
                </p>

                <h1 id="tituloHistorialMantenimientos">Historial de mantenimientos</h1>

                <p class="mfin-hero__description">
                    Consulta tus trabajos terminados y las actividades que administración canceló.
                    Compara recursos, resultados, tiempos y motivos sin perder la trazabilidad.
                </p>

                <div class="mfin-hero__meta">
                    <span>
                        <i class="mfin-live-dot" aria-hidden="true"></i>
                        Consulta segura de solo lectura
                    </span>
                    <span>
                        Técnico:
                        <strong><?= htmlspecialchars($nombreTecnico, ENT_QUOTES, 'UTF-8') ?></strong>
                    </span>
                </div>
            </div>

            <div class="mfin-hero__actions">
                <div class="mfin-hero__mini-card">
                    <span class="mfin-hero__mini-icon" aria-hidden="true">
                        <svg><use href="#mfin-icon-history"></use></svg>
                    </span>
                    <span>
                        <small>Centro de historial</small>
                        <strong>Cierres, tiempos y trazabilidad</strong>
                    </span>
                </div>

                <div class="mfin-heading__actions">
                    <button type="button" class="mfin-btn mfin-btn--hero-secondary" id="btnExportar">
                        <svg aria-hidden="true"><use href="#mfin-icon-download"></use></svg>
                        <span>Exportar CSV</span>
                    </button>

                    <button type="button" class="mfin-btn mfin-btn--hero-primary" id="btnActualizar">
                        <svg aria-hidden="true"><use href="#mfin-icon-refresh"></use></svg>
                        <span>Actualizar información</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="mfin-guides" aria-label="Características del historial">
        <article>
            <span aria-hidden="true">
                <svg><use href="#mfin-icon-shield"></use></svg>
            </span>
            <div>
                <strong>Historial protegido</strong>
                <p>Los registros se muestran como quedaron guardados y no pueden modificarse desde esta pantalla.</p>
            </div>
        </article>

        <article>
            <span aria-hidden="true">
                <svg><use href="#mfin-icon-filter"></use></svg>
            </span>
            <div>
                <strong>Consulta precisa</strong>
                <p>Combina búsqueda, fechas, resultado y cumplimiento sin cargar todo el historial de golpe.</p>
            </div>
        </article>

        <article>
            <span aria-hidden="true">
                <svg><use href="#mfin-icon-clock"></use></svg>
            </span>
            <div>
                <strong>Tiempos reales</strong>
                <p>Revisa tiempo activo, pausas, participantes, evidencias y eventos conservados por mantenimiento.</p>
            </div>
        </article>
    </section>

    <section class="mfin-note" aria-label="Información del historial">
        <span class="mfin-note__icon" aria-hidden="true">
            <svg><use href="#mfin-icon-check"></use></svg>
        </span>
        <div>
            <strong>Historial técnico compartido de solo lectura</strong>
            <p>
                Las recomendaciones del administrador y los recursos realmente utilizados se muestran por separado.
                Los registros no pueden modificarse desde esta pantalla.
            </p>
        </div>
    </section>

    <div class="mfin-status" id="estadoCarga" role="status" aria-live="polite">
        Cargando historial...
    </div>

    <section class="mfin-kpis" aria-label="Resumen del historial">
        <article class="mfin-kpi mfin-kpi--total" data-symbol="◎">
            <span>Trabajos encontrados</span>
            <strong id="kpiTotal">0</strong>
            <small>Según los filtros actuales</small>
        </article>
        <article class="mfin-kpi mfin-kpi--month" data-symbol="▦">
            <span>Finalizados este mes</span>
            <strong id="kpiMes">0</strong>
            <small>Dentro del resultado filtrado</small>
        </article>
        <article class="mfin-kpi mfin-kpi--time" data-symbol="◷">
            <span>Tiempo activo</span>
            <strong id="kpiTiempo">0 h</strong>
            <small id="kpiPromedio">Promedio: 0 min</small>
        </article>
        <article class="mfin-kpi mfin-kpi--success" data-symbol="✓">
            <span>Terminados a tiempo</span>
            <strong id="kpiATiempo">0</strong>
            <small id="kpiCumplimiento">0% del historial filtrado</small>
        </article>
        <article class="mfin-kpi mfin-kpi--warning" data-symbol="!">
            <span>Parciales o provisionales</span>
            <strong id="kpiPendientes">0</strong>
            <small>Con información pendiente registrada</small>
        </article>
    </section>

    <section class="mfin-card mfin-filter-card">
        <header class="mfin-card__head">
            <div>
                <p class="mfin-eyebrow">
                    <span class="mfin-section-icon" aria-hidden="true">
                        <svg><use href="#mfin-icon-filter"></use></svg>
                    </span>
                    Búsqueda y filtros
                </p>
                <h2>Encuentra un mantenimiento</h2>
                <p>Busca por folio, equipo, trabajo realizado, herramienta o refacción recomendada o utilizada.</p>
            </div>
            <span class="mfin-filter-count" id="contadorFiltros">Sin filtros</span>
        </header>

        <form class="mfin-filters" id="formFiltros" autocomplete="off">
            <label class="mfin-field mfin-field--scope" for="filtroAlcance">
                <span>Historial a consultar</span>
                <select id="filtroAlcance" name="alcance">
                    <option value="MIS">Mis mantenimientos</option>
                    <option value="TODOS">Todos los mantenimientos</option>
                </select>
                <small>“Todos” muestra un registro por mantenimiento, aunque hayan participado varios técnicos.</small>
            </label>

            <label class="mfin-field mfin-field--search" for="filtroBusqueda">
                <span>Buscar</span>
                <div class="mfin-search-control">
                    <span aria-hidden="true">
                        <svg><use href="#mfin-icon-search"></use></svg>
                    </span>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        name="busqueda"
                        maxlength="120"
                        placeholder="Ej. MCP-2026-00018, mezcladora, producción..."
                    >
                </div>
            </label>

            <label class="mfin-field" for="filtroTipo">
                <span>Tipo</span>
                <select id="filtroTipo" name="tipo">
                    <option value="TODOS">Todos los tipos</option>
                    <option value="CORRECTIVO_PROGRAMABLE">Correctivo programable</option>
                    <option value="MODIFICACION_MEJORA">Modificación o mejora</option>
                    <option value="CORRECTIVO_URGENTE">Correctivo urgente</option>
                    <option value="RUTINARIO">Mantenimiento rutinario</option>
                </select>
            </label>

            <label class="mfin-field" for="filtroResultado">
                <span>Cómo quedó</span>
                <select id="filtroResultado" name="resultado">
                    <option value="TODOS">Todos los resultados</option>
                    <option value="TERMINADO">Trabajo terminado</option>
                    <option value="PARCIAL">Trabajo parcial</option>
                    <option value="PROVISIONAL">Solución provisional</option>
                </select>
            </label>

            <label class="mfin-field" for="filtroCumplimiento">
                <span>Cumplimiento</span>
                <select id="filtroCumplimiento" name="cumplimiento">
                    <option value="TODOS">Todo cumplimiento</option>
                    <option value="A_TIEMPO">A tiempo</option>
                    <option value="TARDE">Terminado tarde</option>
                    <option value="NO_APLICA">No aplica</option>
                </select>
            </label>

            <label class="mfin-field" for="filtroDesde">
                <span>Finalizado desde</span>
                <input type="date" id="filtroDesde" name="fecha_desde">
            </label>

            <label class="mfin-field" for="filtroHasta">
                <span>Finalizado hasta</span>
                <input type="date" id="filtroHasta" name="fecha_hasta">
            </label>

            <label class="mfin-field" for="filtroOrden">
                <span>Ordenar por</span>
                <select id="filtroOrden" name="orden">
                    <option value="RECIENTES">Más recientes</option>
                    <option value="ANTIGUOS">Más antiguos</option>
                    <option value="MAYOR_TIEMPO">Mayor tiempo activo</option>
                    <option value="MENOR_TIEMPO">Menor tiempo activo</option>
                    <option value="FOLIO">Folio</option>
                </select>
            </label>

            <label class="mfin-field" for="filtroPorPagina">
                <span>Mostrar</span>
                <select id="filtroPorPagina" name="por_pagina">
                    <option value="12">12 por página</option>
                    <option value="24">24 por página</option>
                    <option value="48">48 por página</option>
                </select>
            </label>

            <div class="mfin-filter-actions">
                <button type="button" class="mfin-btn mfin-btn--ghost" id="btnLimpiar">
                    Limpiar filtros
                </button>
                <button type="submit" class="mfin-btn mfin-btn--primary" id="btnAplicar">
                    Aplicar filtros
                </button>
            </div>
        </form>
    </section>

    <section class="mfin-card mfin-results-card">
        <header class="mfin-card__head mfin-results-head">
            <div>
                <p class="mfin-eyebrow">
                    <span class="mfin-section-icon" aria-hidden="true">
                        <svg><use href="#mfin-icon-history"></use></svg>
                    </span>
                    Resultados
                </p>
                <h2>Trabajos finalizados</h2>
                <p id="textoResultados">Consultando información...</p>
            </div>
            <span class="mfin-results-count" id="contadorResultados">0 registros</span>
        </header>

        <div class="mfin-list" id="listaMantenimientos" aria-live="polite"></div>

        <div class="mfin-empty" id="estadoVacio" hidden>
            <span aria-hidden="true">⌕</span>
            <h3>No encontramos mantenimientos</h3>
            <p>
                Prueba con otro folio, amplía las fechas o elimina algunos filtros.
            </p>
            <button type="button" class="mfin-btn mfin-btn--secondary" id="btnLimpiarVacio">
                Mostrar todo el historial
            </button>
        </div>

        <footer class="mfin-pagination-wrap" id="contenedorPaginacion" hidden>
            <p id="textoPaginacion">Mostrando 0 resultados</p>
            <nav class="mfin-pagination" id="paginacion" aria-label="Paginación del historial"></nav>
        </footer>
    </section>

    <section class="mfin-card mfin-cancelled-card" id="cancelaciones">
        <header class="mfin-card__head mfin-cancelled-head">
            <div>
                <p class="mfin-eyebrow">
                    <span class="mfin-section-icon mfin-section-icon--danger" aria-hidden="true">!</span>
                    Cancelaciones administrativas
                </p>
                <h2>Actividades que te fueron canceladas</h2>
                <p>Conserva el motivo, quién tomó la decisión y el tiempo real acumulado antes de detenerse.</p>
            </div>
            <span class="mfin-results-count mfin-results-count--danger" id="contadorCancelaciones">0 cancelaciones</span>
        </header>

        <div class="mfin-cancelled-list" id="listaCancelaciones" aria-live="polite"></div>

        <div class="mfin-cancelled-empty" id="cancelacionesVacias" hidden>
            <span aria-hidden="true">✓</span>
            <div>
                <strong>No tienes cancelaciones administrativas registradas</strong>
                <p>Cuando administración cancele una actividad asignada, aquí aparecerá el motivo.</p>
            </div>
        </div>
    </section>

    <footer class="mfin-footer">
        <span>Historial técnico · Consulta personal o compartida de solo lectura</span>
        <span>Los registros conservan tiempos, cierres y trazabilidad</span>
    </footer>

    <div class="mfin-tools-background" aria-hidden="true"></div>
</main>

<div class="mfin-modal" id="modalDetalle" hidden aria-hidden="true">
    <div class="mfin-modal__backdrop" data-cerrar-modal></div>

    <section
        class="mfin-modal__panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="detalleTitulo"
    >
        <header class="mfin-modal__head">
            <div>
                <p class="mfin-eyebrow" id="detalleEyebrow">DETALLE DEL MANTENIMIENTO</p>
                <h2 id="detalleTitulo">Cargando...</h2>
                <p id="detalleSubtitulo">Espera mientras consultamos el registro.</p>
            </div>
            <button type="button" class="mfin-icon-btn" data-cerrar-modal aria-label="Cerrar detalle">×</button>
        </header>

        <div class="mfin-modal__loading" id="detalleCargando">
            <span class="mfin-spinner" aria-hidden="true"></span>
            <p>Cargando detalle completo...</p>
        </div>

        <div class="mfin-modal__content" id="detalleContenido" hidden>
            <section class="mfin-detail-hero">
                <div>
                    <div class="mfin-detail-badges" id="detalleBadges"></div>
                    <span class="mfin-detail-folio" id="detalleFolio">—</span>
                    <h3 id="detalleEquipo">—</h3>
                    <p id="detalleUbicacion">—</p>
                </div>
                <div class="mfin-result-seal" id="detalleSelloResultado">
                    <span>Resultado</span>
                    <strong id="detalleResultado">—</strong>
                    <small id="detalleFechaCierre">—</small>
                </div>
            </section>

            <nav class="mfin-tabs" aria-label="Secciones del detalle">
                <button type="button" class="mfin-tab is-active" data-tab="resumen">Resumen</button>
                <button type="button" class="mfin-tab" data-tab="equipo">Equipo y tiempos</button>
                <button type="button" class="mfin-tab" data-tab="trazabilidad">Trazabilidad</button>
            </nav>

            <div class="mfin-tab-panel is-active" data-panel="resumen">
                <section class="mfin-detail-metrics" aria-label="Tiempos del técnico">
                    <article>
                        <span>Tiempo activo</span>
                        <strong id="detalleTiempoActivo">00:00:00</strong>
                        <small>Trabajo real registrado</small>
                    </article>
                    <article>
                        <span>Tiempo pausado</span>
                        <strong id="detalleTiempoPausa">00:00:00</strong>
                        <small>Pausas acumuladas</small>
                    </article>
                    <article>
                        <span>Inicio real</span>
                        <strong class="mfin-metric-date" id="detalleInicio">—</strong>
                        <small id="detalleInicioTexto">Registro de ejecución</small>
                    </article>
                    <article>
                        <span>Fin real</span>
                        <strong class="mfin-metric-date" id="detalleFin">—</strong>
                        <small id="detalleCumplimiento">—</small>
                    </article>
                </section>

                <section class="mfin-info-grid" aria-label="Información del mantenimiento">
                    <article>
                        <span>Tipo y prioridad</span>
                        <strong id="detalleTipo">—</strong>
                        <small id="detallePrioridad">—</small>
                    </article>
                    <article>
                        <span>Solicitante</span>
                        <strong id="detalleSolicitante">—</strong>
                        <small id="detalleContacto">—</small>
                    </article>
                    <article>
                        <span>Programación</span>
                        <strong id="detalleProgramacion">—</strong>
                        <small id="detalleLimite">—</small>
                    </article>
                    <article>
                        <span>Seguridad</span>
                        <strong id="detalleRiesgo">—</strong>
                        <small id="detalleParo">—</small>
                    </article>
                </section>

                <section class="mfin-result-banner" id="detalleResultadoBanner">
                    <div>
                        <span aria-hidden="true" id="detalleResultadoIcono">✓</span>
                    </div>
                    <section>
                        <strong id="detalleResultadoTitulo">Resultado del cierre</strong>
                        <p id="detalleResultadoTexto">—</p>
                    </section>
                </section>

                <section class="mfin-copy-grid">
                    <article class="mfin-copy-card">
                        <h4>Trabajo solicitado</h4>
                        <p id="detalleSolicitud">—</p>
                    </article>
                    <article class="mfin-copy-card">
                        <h4>Trabajo realizado</h4>
                        <p id="detalleTrabajoRealizado">—</p>
                    </article>
                    <article class="mfin-copy-card" id="bloqueQueFalto">
                        <h4>Qué faltó por realizar</h4>
                        <p id="detalleQueFalto">—</p>
                    </article>
                    <article class="mfin-copy-card" id="bloqueCondicion">
                        <h4>Falla o condición reportada</h4>
                        <p id="detalleCondicion">—</p>
                    </article>
                    <article class="mfin-copy-card" id="bloqueImpacto">
                        <h4>Impacto en la operación</h4>
                        <p id="detalleImpacto">—</p>
                    </article>
                    <article class="mfin-copy-card" id="bloqueObjetivo">
                        <h4>Objetivo y resultado esperado</h4>
                        <p id="detalleObjetivo">—</p>
                    </article>
                </section>

                <section class="mfin-history-resources" aria-labelledby="tituloRecursosHistorial">
                    <header>
                        <div>
                            <h4 id="tituloRecursosHistorial">Herramientas y refacciones</h4>
                            <p>Compara lo que se recomendó llevar con lo que realmente se utilizó al finalizar.</p>
                        </div>
                    </header>
                    <div class="mfin-history-resources__grid">
                        <article class="mfin-history-resource-group">
                            <span class="mfin-history-resource-group__label">RECOMENDACIÓN</span>
                            <h5>Indicadas antes del mantenimiento</h5>
                            <div id="detalleRecursosRecomendados"></div>
                        </article>
                        <article class="mfin-history-resource-group mfin-history-resource-group--actual">
                            <span class="mfin-history-resource-group__label">USO REAL</span>
                            <h5>Registradas por quien finalizó</h5>
                            <div id="detalleRecursosUtilizados"></div>
                        </article>
                    </div>
                    <p class="mfin-history-resources__help">
                        Los recursos reales de mantenimientos normales y rutinarios son informativos.
                        Solo las urgencias sin recomendación administrativa pueden aprender automáticamente del cierre.
                    </p>
                </section>

                <section class="mfin-close-review">
                    <header>
                        <div>
                            <h4>Revisión del cierre</h4>
                            <p id="detalleCerradoPor">—</p>
                        </div>
                        <span id="detalleCierreEditado" hidden>Cierre editado</span>
                    </header>
                    <div class="mfin-check-grid">
                        <article id="detalleLimpiezaCard">
                            <span aria-hidden="true" id="detalleLimpiezaIcono">—</span>
                            <div>
                                <strong>Limpieza del área</strong>
                                <small id="detalleLimpieza">—</small>
                            </div>
                        </article>
                        <article id="detalleOrdenCard">
                            <span aria-hidden="true" id="detalleOrdenIcono">—</span>
                            <div>
                                <strong>Área ordenada</strong>
                                <small id="detalleOrden">—</small>
                            </div>
                        </article>
                    </div>
                    <div class="mfin-close-observations" id="bloqueObservacionesCierre">
                        <strong>Observaciones del cierre</strong>
                        <p id="detalleObservacionesCierre">—</p>
                    </div>
                    <div class="mfin-close-observations" id="bloqueEdicionCierre" hidden>
                        <strong>Motivo de edición administrativa</strong>
                        <p id="detalleMotivoEdicion">—</p>
                    </div>
                </section>
            </div>

            <div class="mfin-tab-panel" data-panel="equipo">
                <section class="mfin-detail-section">
                    <header class="mfin-section-head">
                        <div>
                            <h3>Participantes del mantenimiento</h3>
                            <p>Estado final y tiempo registrado de cada técnico asignado.</p>
                        </div>
                        <span id="contadorParticipantes">0 participantes</span>
                    </header>
                    <div class="mfin-participant-list" id="listaParticipantes"></div>
                </section>

                <section class="mfin-detail-section">
                    <header class="mfin-section-head">
                        <div>
                            <h3>Pausas registradas</h3>
                            <p>En “Mis mantenimientos” se muestran tus pausas; en “Todos” se muestran las de los participantes.</p>
                        </div>
                        <span id="contadorPausas">0 pausas</span>
                    </header>
                    <div class="mfin-pause-list" id="listaPausas"></div>
                    <div class="mfin-mini-empty" id="pausasVacias" hidden>
                        Este mantenimiento no tuvo pausas registradas.
                    </div>
                </section>
            </div>

            <div class="mfin-tab-panel" data-panel="trazabilidad">
                <section class="mfin-detail-section">
                    <header class="mfin-section-head">
                        <div>
                            <h3>Trazabilidad de la solicitud</h3>
                            <p>Eventos conservados desde el registro hasta el cierre.</p>
                        </div>
                        <span id="contadorEventos">0 eventos</span>
                    </header>
                    <div class="mfin-timeline" id="listaHistorial"></div>
                </section>

                <section class="mfin-detail-section">
                    <header class="mfin-section-head">
                        <div>
                            <h3>Evidencias asociadas</h3>
                            <p>Archivos activos vinculados con la solicitud, ejecución o cierre.</p>
                        </div>
                        <span id="contadorEvidencias">0 archivos</span>
                    </header>
                    <div class="mfin-evidence-list" id="listaEvidencias"></div>
                    <div class="mfin-mini-empty" id="evidenciasVacias" hidden>
                        No hay evidencias activas vinculadas con este mantenimiento.
                    </div>
                </section>
            </div>
        </div>

        <footer class="mfin-modal__foot">
            <button type="button" class="mfin-btn mfin-btn--secondary" id="btnImprimirDetalle" disabled>
                <svg aria-hidden="true"><use href="#mfin-icon-print"></use></svg>
                <span>Imprimir detalle</span>
            </button>
            <button type="button" class="mfin-btn mfin-btn--primary" data-cerrar-modal>
                Cerrar
            </button>
        </footer>
    </section>
</div>

<?php include __DIR__ . '/../inc/alertas.php'; ?>

<script>
(() => {
    'use strict';

    const UI = window.SistemaUI;
    const ENDPOINT = '../funciones/mantenimientos_finalizados_funciones.php';
    const $ = (id) => document.getElementById(id);

    if (!UI) {
        const estadoCarga = $('estadoCarga');
        if (estadoCarga) {
            estadoCarga.textContent = 'No fue posible cargar las herramientas de la interfaz. Actualiza la página.';
            estadoCarga.className = 'mfin-status mfin-status--error';
        }
        console.error('No se cargó window.SistemaUI.');
        return;
    }

    const estado = {
        cargando: false,
        detalleCargando: false,
        pagina: 1,
        registros: [],
        cancelaciones: [],
        resumen: {},
        paginacion: {},
        detalle: null,
        participantes: [],
        pausas: [],
        historial: [],
        evidencias: [],
        recursosRecomendados: {},
        recursosUtilizados: {},
        alcance: 'MIS',
        busquedaTemporizador: null,
        solicitudInicial: 0,
        ultimoFoco: null
    };

    const etiquetasTipo = {
        CORRECTIVO_PROGRAMABLE: 'Correctivo programable',
        MODIFICACION_MEJORA: 'Modificación o mejora',
        CORRECTIVO_URGENTE: 'Correctivo urgente',
        RUTINARIO: 'Mantenimiento rutinario'
    };

    const etiquetasResultado = {
        TERMINADO: 'Terminado',
        PARCIAL: 'Parcial',
        PROVISIONAL: 'Provisional',
        SIN_REGISTRO: 'Sin cierre'
    };

    const etiquetasCumplimiento = {
        A_TIEMPO: 'A tiempo',
        TARDE: 'Terminado tarde',
        NO_REALIZADO: 'No realizado',
        NO_APLICA: 'No aplica',
        PENDIENTE: 'Pendiente'
    };

    const etiquetasEstadoParticipacion = {
        ASIGNADO: 'Asignado',
        ACEPTADO: 'Aceptado',
        EN_PROCESO: 'En proceso',
        PAUSADO: 'Pausado',
        TERMINADO: 'Participó y terminó',
        NO_PARTICIPO: 'No participó',
        RETIRADO: 'Retirado'
    };

    const etiquetasPausa = {
        URGENCIA: 'Pausa por urgencia',
        MANUAL: 'Pausa manual',
        ADMINISTRATIVA: 'Pausa administrativa',
        FALTA_RECURSO: 'Falta de recurso',
        CAMBIO_PRIORIDAD: 'Cambio de prioridad',
        OTRO: 'Otra pausa'
    };

    const etiquetasEvento = {
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
        TERMINADA: 'Mantenimiento finalizado',
        INCUMPLIMIENTO_DETECTADO: 'Incumplimiento detectado',
        CUMPLIDA_TARDE: 'Cumplido tarde',
        NO_REALIZADA: 'No realizado',
        JUSTIFICADA: 'Incumplimiento justificado',
        CANCELADA: 'Solicitud cancelada',
        OTRO: 'Movimiento registrado'
    };

    function texto(valor, respaldo = '—') {
        const limpio = String(valor ?? '').trim();
        return limpio || respaldo;
    }

    function numero(valor) {
        const convertido = Number(valor);
        return Number.isFinite(convertido) ? convertido : 0;
    }

    function escapar(valor) {
        return String(valor ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function fecha(valor, conHora = false) {
        if (!valor) return '—';
        const original = String(valor);
        const normalizado = /^\d{4}-\d{2}-\d{2}$/.test(original)
            ? original + 'T00:00:00'
            : original.replace(' ', 'T');
        const objeto = new Date(normalizado);
        if (Number.isNaN(objeto.getTime())) return texto(valor);

        return new Intl.DateTimeFormat(
            'es-MX',
            conHora
                ? { dateStyle: 'medium', timeStyle: 'short' }
                : { dateStyle: 'medium' }
        ).format(objeto);
    }

    function fechaCorta(valor) {
        if (!valor) return '—';
        const original = String(valor);
        const objeto = new Date(
            /^\d{4}-\d{2}-\d{2}$/.test(original)
                ? original + 'T00:00:00'
                : original.replace(' ', 'T')
        );
        if (Number.isNaN(objeto.getTime())) return texto(valor);
        return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }).format(objeto);
    }

    function duracion(segundos, compacta = false) {
        const total = Math.max(0, Math.floor(numero(segundos)));
        const horas = Math.floor(total / 3600);
        const minutos = Math.floor((total % 3600) / 60);
        const resto = total % 60;

        if (!compacta) {
            return [horas, minutos, resto]
                .map((valor) => String(valor).padStart(2, '0'))
                .join(':');
        }

        if (horas > 0) return horas + ' h ' + minutos + ' min';
        if (minutos > 0) return minutos + ' min';
        return resto + ' s';
    }

    function tamano(bytes) {
        const total = Math.max(0, numero(bytes));
        if (total < 1024) return total + ' B';
        if (total < 1024 * 1024) return (total / 1024).toFixed(1) + ' KB';
        return (total / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function tipoMantenimiento(valor) {
        return etiquetasTipo[valor] || texto(valor);
    }

    function resultadoTrabajo(valor) {
        return etiquetasResultado[valor] || texto(valor, 'Sin cierre');
    }

    function cumplimiento(valor) {
        return etiquetasCumplimiento[valor] || texto(valor);
    }

    function plural(cantidad, singular, pluralTexto) {
        return cantidad === 1 ? singular : pluralTexto;
    }

    function establecerEstado(mensaje, tipo = '') {
        const elemento = $('estadoCarga');
        elemento.textContent = mensaje;
        elemento.className = 'mfin-status' + (tipo ? ' mfin-status--' + tipo : '');
    }

    function leerUrl() {
        const parametros = new URLSearchParams(window.location.search);
        const campos = {
            alcance: 'filtroAlcance',
            busqueda: 'filtroBusqueda',
            tipo: 'filtroTipo',
            resultado: 'filtroResultado',
            cumplimiento: 'filtroCumplimiento',
            fecha_desde: 'filtroDesde',
            fecha_hasta: 'filtroHasta',
            orden: 'filtroOrden',
            por_pagina: 'filtroPorPagina'
        };

        Object.entries(campos).forEach(([parametro, id]) => {
            if (parametros.has(parametro) && $(id)) {
                $(id).value = parametros.get(parametro) || '';
            }
        });

        const pagina = Number(parametros.get('pagina') || 1);
        estado.pagina = Number.isInteger(pagina) && pagina > 0 ? pagina : 1;

        const solicitudId = Number(parametros.get('solicitud_id') || 0);
        estado.solicitudInicial = Number.isInteger(solicitudId) && solicitudId > 0
            ? solicitudId
            : 0;
    }

    function parametrosFiltros(incluirPagina = true) {
        const parametros = new URLSearchParams();
        const valores = {
            alcance: $('filtroAlcance').value,
            busqueda: $('filtroBusqueda').value.trim(),
            tipo: $('filtroTipo').value,
            resultado: $('filtroResultado').value,
            cumplimiento: $('filtroCumplimiento').value,
            fecha_desde: $('filtroDesde').value,
            fecha_hasta: $('filtroHasta').value,
            orden: $('filtroOrden').value,
            por_pagina: $('filtroPorPagina').value
        };

        Object.entries(valores).forEach(([clave, valor]) => {
            const omitir = (
                valor === ''
                || (clave !== 'alcance' && valor === 'TODOS')
                || (clave === 'alcance' && valor === 'MIS')
                || (clave === 'orden' && valor === 'RECIENTES')
                || (clave === 'por_pagina' && valor === '12')
            );
            if (!omitir) parametros.set(clave, valor);
        });

        if (incluirPagina && estado.pagina > 1) {
            parametros.set('pagina', String(estado.pagina));
        }

        return parametros;
    }

    function sincronizarUrl() {
        const parametros = parametrosFiltros(true);
        const url = new URL(window.location.href);
        url.search = parametros.toString();
        window.history.replaceState({}, '', url);
    }

    function validarFechas() {
        const desde = $('filtroDesde').value;
        const hasta = $('filtroHasta').value;

        if (desde && hasta && desde > hasta) {
            UI.error(
                'Rango de fechas inválido',
                'La fecha inicial no puede ser posterior a la fecha final.'
            );
            $('filtroDesde').focus();
            return false;
        }

        return true;
    }

    async function cargarHistorial(silencioso = false) {
        if (estado.cargando) return;
        if (!validarFechas()) return;

        estado.cargando = true;

        if (!silencioso) {
            establecerEstado('Consultando historial...');
            UI.estadoBoton($('btnActualizar'), true, 'Actualizando...');
            UI.estadoBoton($('btnAplicar'), true, 'Consultando...');
        }

        const parametros = parametrosFiltros(true);
        parametros.set('accion', 'INICIAL');

        try {
            const datos = await UI.peticionJson(ENDPOINT + '?' + parametros.toString());
            estado.registros = Array.isArray(datos.registros) ? datos.registros : [];
            estado.cancelaciones = Array.isArray(datos.cancelaciones) ? datos.cancelaciones : [];
            estado.resumen = datos.resumen || {};
            estado.paginacion = datos.paginacion || {};
            estado.alcance = texto(datos.alcance || $('filtroAlcance').value, 'MIS');
            estado.pagina = numero(estado.paginacion.pagina) || 1;

            pintarResumen();
            pintarRegistros();
            pintarCancelaciones();
            pintarPaginacion();
            pintarContadorFiltros();
            sincronizarUrl();

            establecerEstado(
                'Historial actualizado ' + fecha(datos.fecha_servidor, true) + '.',
                'ok'
            );

            if (window.location.hash === '#cancelaciones') {
                window.setTimeout(() => $('cancelaciones').scrollIntoView({ behavior: 'smooth', block: 'start' }), 120);
            }

            if (estado.solicitudInicial > 0) {
                const solicitudId = estado.solicitudInicial;
                estado.solicitudInicial = 0;
                await abrirDetalle(solicitudId, false);
            }
        } catch (error) {
            establecerEstado(error.message || 'No fue posible cargar el historial.', 'error');
            if (!silencioso) {
                await UI.error(
                    'No se cargó el historial',
                    error.message || 'Actualiza la página e inténtalo nuevamente.'
                );
            }
        } finally {
            estado.cargando = false;
            UI.estadoBoton($('btnActualizar'), false);
            UI.estadoBoton($('btnAplicar'), false);
        }
    }

    function pintarResumen() {
        const resumen = estado.resumen;
        const total = numero(resumen.total);
        const aTiempo = numero(resumen.a_tiempo);
        const porcentaje = total > 0 ? Math.round((aTiempo / total) * 100) : 0;

        $('kpiTotal').textContent = total.toLocaleString('es-MX');
        $('kpiMes').textContent = numero(resumen.este_mes).toLocaleString('es-MX');
        $('kpiTiempo').textContent = duracion(resumen.segundos_activos, true);
        $('kpiPromedio').textContent = 'Promedio: ' + duracion(resumen.promedio_segundos_activos, true);
        $('kpiATiempo').textContent = aTiempo.toLocaleString('es-MX');
        $('kpiCumplimiento').textContent = porcentaje + '% del historial filtrado';
        $('kpiPendientes').textContent = numero(resumen.con_pendientes).toLocaleString('es-MX');
    }

    function claseResultado(valor) {
        if (valor === 'TERMINADO') return 'success';
        if (valor === 'PARCIAL') return 'warning';
        if (valor === 'PROVISIONAL') return 'info';
        return 'muted';
    }

    function claseCumplimiento(valor) {
        if (valor === 'A_TIEMPO') return 'success';
        if (valor === 'TARDE') return 'danger';
        return 'muted';
    }

    function nombresRecursos(recursos) {
        return (Array.isArray(recursos) ? recursos : [])
            .map((recurso) => texto(recurso && recurso.nombre, ''))
            .filter(Boolean);
    }

    function resumenRecursoTarjeta(registro, tipo) {
        const esHerramienta = tipo === 'HERRAMIENTA';
        const usados = esHerramienta
            ? (Array.isArray(registro.herramientas_utilizadas_nombres) ? registro.herramientas_utilizadas_nombres : [])
            : (Array.isArray(registro.refacciones_utilizadas_nombres) ? registro.refacciones_utilizadas_nombres : []);
        const sinUso = numero(esHerramienta
            ? registro.sin_herramientas_utilizadas
            : registro.sin_refacciones_utilizadas) === 1;
        const recomendados = nombresRecursos(esHerramienta
            ? registro.herramientas_recomendadas
            : registro.refacciones_recomendadas);
        const etiqueta = esHerramienta ? 'Herramientas' : 'Refacciones';

        if (usados.length > 0) {
            const visibles = usados.slice(0, 3);
            const extra = usados.length - visibles.length;
            return `<div class="mfin-record-resource mfin-record-resource--actual">
                <span>${escapar(etiqueta + ' usadas')}</span>
                <strong>${escapar(visibles.join(', '))}${extra > 0 ? escapar(' +' + extra) : ''}</strong>
            </div>`;
        }

        if (sinUso) {
            return `<div class="mfin-record-resource mfin-record-resource--none">
                <span>${escapar(etiqueta + ' usadas')}</span>
                <strong>No se utilizaron</strong>
            </div>`;
        }

        if (recomendados.length > 0) {
            const visibles = recomendados.slice(0, 2);
            const extra = recomendados.length - visibles.length;
            return `<div class="mfin-record-resource mfin-record-resource--recommended">
                <span>${escapar(etiqueta + ' recomendadas')}</span>
                <strong>${escapar(visibles.join(', '))}${extra > 0 ? escapar(' +' + extra) : ''}</strong>
            </div>`;
        }

        return `<div class="mfin-record-resource mfin-record-resource--empty">
            <span>${escapar(etiqueta)}</span>
            <strong>Sin registro</strong>
        </div>`;
    }

    function pintarCancelaciones() {
        const lista = $('listaCancelaciones');
        const vacio = $('cancelacionesVacias');
        const registros = estado.cancelaciones;
        const cantidad = registros.length;

        $('contadorCancelaciones').textContent = cantidad.toLocaleString('es-MX') + ' ' + plural(cantidad, 'cancelación', 'cancelaciones');

        if (cantidad === 0) {
            lista.innerHTML = '';
            vacio.hidden = false;
            return;
        }

        vacio.hidden = true;
        lista.innerHTML = registros.map((registro) => {
            const iniciada = numero(registro.fue_iniciado) === 1;
            const motivo = texto(registro.motivo_cancelacion, 'No se registró un motivo de cancelación.');
            const tiempo = numero(registro.total_segundos_activos);
            return `
                <article class="mfin-cancelled-item">
                    <div class="mfin-cancelled-item__status" aria-hidden="true">×</div>
                    <div class="mfin-cancelled-item__body">
                        <header>
                            <div>
                                <span class="mfin-record__folio">${escapar(texto(registro.folio))}</span>
                                <h3>${escapar(texto(registro.nombre_equipo))}</h3>
                                <p>${escapar(texto(registro.codigo_equipo, 'Sin código'))} · ${escapar(texto(registro.area))}</p>
                            </div>
                            <span class="mfin-badge mfin-badge--danger">Cancelado</span>
                        </header>
                        <div class="mfin-cancelled-item__badges">
                            <span>${escapar(tipoMantenimiento(registro.tipo_solicitud))}</span>
                            <span>${iniciada ? 'Detenido durante la ejecución' : 'Cancelado antes de iniciar'}</span>
                            ${numero(registro.trabajo_peligroso) === 1 ? '<span class="is-danger">Trabajo peligroso</span>' : ''}
                        </div>
                        <div class="mfin-cancelled-reason">
                            <strong>Motivo informado por administración</strong>
                            <p>${escapar(motivo)}</p>
                        </div>
                        <dl>
                            <div><dt>Cancelado</dt><dd>${escapar(fecha(registro.fecha_cancelacion, true))}</dd></div>
                            <div><dt>Responsable</dt><dd>${escapar(texto(registro.cancelado_por, 'Administración'))}</dd></div>
                            <div><dt>Tiempo acumulado</dt><dd>${escapar(duracion(tiempo, true))}</dd></div>
                            <div><dt>Programado</dt><dd>${escapar(fechaCorta(registro.fecha_programada))}</dd></div>
                        </dl>
                        <footer>
                            <span>El registro permanece en el historial y no cuenta como trabajo terminado.</span>
                            <button type="button" class="mfin-btn mfin-btn--record mfin-btn--cancel-detail" data-ver-cancelacion="${numero(registro.solicitud_id)}">Ver motivo completo</button>
                        </footer>
                    </div>
                </article>`;
        }).join('');
    }

    async function mostrarCancelacion(solicitudId) {
        const registro = estado.cancelaciones.find((item) => numero(item.solicitud_id) === numero(solicitudId));
        if (!registro) return;

        const iniciada = numero(registro.fue_iniciado) === 1;
        await Swal.fire({
            icon: 'warning',
            title: 'Mantenimiento cancelado',
            html: `
                <div class="mfin-cancel-dialog">
                    <p class="mfin-cancel-dialog__folio">${escapar(texto(registro.folio))}</p>
                    <h3>${escapar(texto(registro.nombre_equipo))}</h3>
                    <p>${escapar(tipoMantenimiento(registro.tipo_solicitud))} · ${escapar(texto(registro.area))}</p>
                    <div class="mfin-cancel-dialog__reason">
                        <strong>Motivo de administración</strong>
                        <p>${escapar(texto(registro.motivo_cancelacion, 'No se registró un motivo.'))}</p>
                    </div>
                    <dl>
                        <div><dt>Cancelado</dt><dd>${escapar(fecha(registro.fecha_cancelacion, true))}</dd></div>
                        <div><dt>Responsable</dt><dd>${escapar(texto(registro.cancelado_por, 'Administración'))}</dd></div>
                        <div><dt>Situación</dt><dd>${iniciada ? 'La ejecución fue detenida' : 'No alcanzó a iniciar'}</dd></div>
                        <div><dt>Tiempo activo conservado</dt><dd>${escapar(duracion(registro.total_segundos_activos, true))}</dd></div>
                    </dl>
                    <p class="mfin-cancel-dialog__note">Esta cancelación no se registra como terminada, parcial ni provisional. Sus tiempos y movimientos permanecen protegidos.</p>
                </div>`,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#0f6f86',
            heightAuto: false
        });
    }

    function pintarRegistros() {
        const lista = $('listaMantenimientos');
        const vacio = $('estadoVacio');
        const registros = estado.registros;
        const total = numero(estado.paginacion.total_registros);

        $('contadorResultados').textContent = total.toLocaleString('es-MX') + ' ' + plural(total, 'registro', 'registros');

        if (registros.length === 0) {
            lista.innerHTML = '';
            vacio.hidden = false;
            $('textoResultados').textContent = 'No hay resultados con los filtros seleccionados.';
            return;
        }

        vacio.hidden = true;
        $('textoResultados').textContent = $('filtroAlcance').value === 'TODOS'
            ? 'Mostrando un registro por mantenimiento terminado para consulta técnica compartida.'
            : 'Mostrando únicamente los mantenimientos en los que participaste.';

        lista.innerHTML = registros.map((registro) => {
            const resultado = texto(registro.trabajo_quedo, 'SIN_REGISTRO');
            const resultadoClase = claseResultado(resultado);
            const cumplimientoClase = claseCumplimiento(registro.resultado_cumplimiento);
            const codigo = texto(registro.codigo_equipo, 'Sin código');
            const participantes = numero(registro.participantes_terminaron);
            const pendiente = texto(registro.que_falto, '');

            return `
                <article class="mfin-record mfin-record--${escapar(resultadoClase)}">
                    <header class="mfin-record__head">
                        <div>
                            <span class="mfin-record__folio">${escapar(texto(registro.folio))}</span>
                            <h3>${escapar(texto(registro.nombre_equipo))}</h3>
                            <p>${escapar(codigo)} · ${escapar(texto(registro.area))}</p>
                        </div>
                        <span class="mfin-badge mfin-badge--${escapar(resultadoClase)}">
                            ${escapar(resultadoTrabajo(resultado))}
                        </span>
                    </header>

                    <div class="mfin-record__badges">
                        <span class="mfin-badge mfin-badge--type">${escapar(tipoMantenimiento(registro.tipo_solicitud))}</span>
                        <span class="mfin-badge mfin-badge--priority-${escapar(String(registro.prioridad || '').toLowerCase())}">
                            ${escapar(texto(registro.prioridad))}
                        </span>
                        <span class="mfin-badge mfin-badge--${escapar(cumplimientoClase)}">
                            ${escapar(cumplimiento(registro.resultado_cumplimiento))}
                        </span>
                    </div>

                    <p class="mfin-record__description">
                        ${escapar(texto(registro.descripcion_trabajo_realizado, registro.descripcion_solicitud || 'Sin descripción de cierre.'))}
                    </p>

                    ${pendiente ? `
                        <div class="mfin-record__pending">
                            <strong>Pendiente registrado:</strong>
                            <span>${escapar(pendiente)}</span>
                        </div>
                    ` : ''}

                    <div class="mfin-record-resources">
                        ${resumenRecursoTarjeta(registro, 'HERRAMIENTA')}
                        ${resumenRecursoTarjeta(registro, 'REFACCION')}
                    </div>

                    <dl class="mfin-record__metrics">
                        <div>
                            <dt>Finalizado</dt>
                            <dd>${escapar(fechaCorta(registro.fecha_finalizacion))}</dd>
                        </div>
                        <div>
                            <dt>Tiempo activo</dt>
                            <dd>${escapar(duracion(registro.total_segundos_activos, true))}</dd>
                        </div>
                        <div>
                            <dt>Participantes</dt>
                            <dd>${participantes.toLocaleString('es-MX')}</dd>
                        </div>
                    </dl>

                    <footer class="mfin-record__foot">
                        <span>Cerrado por ${escapar(texto(registro.cerrado_por))}</span>
                        <button
                            type="button"
                            class="mfin-btn mfin-btn--record"
                            data-ver-detalle="${numero(registro.solicitud_id)}"
                        >
                            Ver detalle completo
                        </button>
                    </footer>
                </article>
            `;
        }).join('');
    }

    function paginasVisibles(actual, total) {
        const paginas = [];
        const inicio = Math.max(1, actual - 2);
        const fin = Math.min(total, actual + 2);

        for (let pagina = inicio; pagina <= fin; pagina += 1) {
            paginas.push(pagina);
        }

        return paginas;
    }

    function pintarPaginacion() {
        const contenedor = $('contenedorPaginacion');
        const nav = $('paginacion');
        const pagina = numero(estado.paginacion.pagina) || 1;
        const totalPaginas = numero(estado.paginacion.total_paginas) || 1;
        const total = numero(estado.paginacion.total_registros);
        const desde = numero(estado.paginacion.desde);
        const hasta = numero(estado.paginacion.hasta);

        if (total === 0) {
            contenedor.hidden = true;
            nav.innerHTML = '';
            return;
        }

        contenedor.hidden = false;
        $('textoPaginacion').textContent = `Mostrando ${desde.toLocaleString('es-MX')}–${hasta.toLocaleString('es-MX')} de ${total.toLocaleString('es-MX')}`;

        if (totalPaginas <= 1) {
            nav.innerHTML = '';
            return;
        }

        const botones = [];
        botones.push(`<button type="button" data-pagina="1" ${pagina <= 1 ? 'disabled' : ''} aria-label="Primera página">«</button>`);
        botones.push(`<button type="button" data-pagina="${pagina - 1}" ${pagina <= 1 ? 'disabled' : ''} aria-label="Página anterior">‹</button>`);

        paginasVisibles(pagina, totalPaginas).forEach((numeroPagina) => {
            botones.push(`
                <button
                    type="button"
                    data-pagina="${numeroPagina}"
                    class="${numeroPagina === pagina ? 'is-active' : ''}"
                    ${numeroPagina === pagina ? 'aria-current="page"' : ''}
                >${numeroPagina}</button>
            `);
        });

        botones.push(`<button type="button" data-pagina="${pagina + 1}" ${pagina >= totalPaginas ? 'disabled' : ''} aria-label="Página siguiente">›</button>`);
        botones.push(`<button type="button" data-pagina="${totalPaginas}" ${pagina >= totalPaginas ? 'disabled' : ''} aria-label="Última página">»</button>`);
        nav.innerHTML = botones.join('');
    }

    function contarFiltros() {
        let total = 0;
        if ($('filtroAlcance').value !== 'MIS') total += 1;
        if ($('filtroBusqueda').value.trim()) total += 1;
        if ($('filtroTipo').value !== 'TODOS') total += 1;
        if ($('filtroResultado').value !== 'TODOS') total += 1;
        if ($('filtroCumplimiento').value !== 'TODOS') total += 1;
        if ($('filtroDesde').value) total += 1;
        if ($('filtroHasta').value) total += 1;
        if ($('filtroOrden').value !== 'RECIENTES') total += 1;
        return total;
    }

    function pintarContadorFiltros() {
        const total = contarFiltros();
        $('contadorFiltros').textContent = total === 0
            ? 'Sin filtros'
            : total + ' ' + plural(total, 'filtro activo', 'filtros activos');
    }

    function limpiarFiltros(cargar = true) {
        $('formFiltros').reset();
        estado.pagina = 1;
        pintarContadorFiltros();
        if (cargar) cargarHistorial();
    }

    async function abrirDetalle(solicitudId, actualizarUrl = true) {
        if (estado.detalleCargando || solicitudId <= 0) return;

        estado.detalleCargando = true;
        estado.ultimoFoco = document.activeElement;
        abrirModalBase();
        prepararDetalleCargando();

        try {
            const datos = await UI.peticionJson(
                ENDPOINT + '?accion=DETALLE&solicitud_id=' + encodeURIComponent(solicitudId) + '&alcance=' + encodeURIComponent($('filtroAlcance').value)
            );

            estado.detalle = datos.detalle || null;
            estado.participantes = Array.isArray(datos.participantes) ? datos.participantes : [];
            estado.pausas = Array.isArray(datos.pausas) ? datos.pausas : [];
            estado.historial = Array.isArray(datos.historial) ? datos.historial : [];
            estado.evidencias = Array.isArray(datos.evidencias) ? datos.evidencias : [];
            estado.recursosRecomendados = datos.recursos_recomendados || (estado.detalle ? estado.detalle.recursos_recomendados : {}) || {};
            estado.recursosUtilizados = datos.recursos_utilizados || (estado.detalle ? estado.detalle.recursos_utilizados : {}) || {};

            pintarDetalle();
            $('detalleCargando').hidden = true;
            $('detalleContenido').hidden = false;
            $('btnImprimirDetalle').disabled = false;

            if (actualizarUrl) {
                const url = new URL(window.location.href);
                url.searchParams.set('solicitud_id', String(solicitudId));
                window.history.replaceState({}, '', url);
            }
        } catch (error) {
            cerrarModal();
            await UI.error(
                'No se abrió el mantenimiento',
                error.message || 'El registro cambió o ya no está disponible.'
            );
        } finally {
            estado.detalleCargando = false;
        }
    }

    function abrirModalBase() {
        const modal = $('modalDetalle');
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('mfin-modal-open');
        window.setTimeout(() => {
            const cerrar = modal.querySelector('[data-cerrar-modal]');
            if (cerrar) cerrar.focus();
        }, 30);
    }

    function prepararDetalleCargando() {
        $('detalleTitulo').textContent = 'Cargando...';
        $('detalleSubtitulo').textContent = 'Espera mientras consultamos el registro.';
        $('detalleCargando').hidden = false;
        $('detalleContenido').hidden = true;
        $('btnImprimirDetalle').disabled = true;
        activarTab('resumen');
    }

    function cerrarModal() {
        const modal = $('modalDetalle');
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('mfin-modal-open');
        estado.detalle = null;
        estado.recursosRecomendados = {};
        estado.recursosUtilizados = {};

        const url = new URL(window.location.href);
        url.searchParams.delete('solicitud_id');
        window.history.replaceState({}, '', url);

        if (estado.ultimoFoco && typeof estado.ultimoFoco.focus === 'function') {
            estado.ultimoFoco.focus();
        }
    }

    function pintarDetalle() {
        const d = estado.detalle;
        if (!d) return;

        const resultado = texto(d.trabajo_quedo, 'SIN_REGISTRO');
        const resultadoClase = claseResultado(resultado);
        const contacto = [texto(d.telefono_solicitante, ''), texto(d.correo_solicitante, '')]
            .filter(Boolean)
            .join(' · ');

        $('detalleEyebrow').textContent = tipoMantenimiento(d.tipo_solicitud).toUpperCase();
        $('detalleTitulo').textContent = texto(d.folio);
        $('detalleSubtitulo').textContent = 'Registro finalizado de ' + texto(d.nombre_equipo) + '.';
        $('detalleFolio').textContent = texto(d.folio);
        $('detalleEquipo').textContent = texto(d.nombre_equipo);
        $('detalleUbicacion').textContent = [d.departamento, d.area, d.proceso].map((valor) => texto(valor, '')).filter(Boolean).join(' · ') || 'Ubicación no disponible';

        $('detalleBadges').innerHTML = `
            <span class="mfin-badge mfin-badge--type">${escapar(tipoMantenimiento(d.tipo_solicitud))}</span>
            <span class="mfin-badge mfin-badge--priority-${escapar(String(d.prioridad || '').toLowerCase())}">${escapar(texto(d.prioridad))}</span>
            <span class="mfin-badge mfin-badge--${escapar(claseCumplimiento(d.resultado_cumplimiento))}">${escapar(cumplimiento(d.resultado_cumplimiento))}</span>
        `;

        $('detalleSelloResultado').className = 'mfin-result-seal mfin-result-seal--' + resultadoClase;
        $('detalleResultado').textContent = resultadoTrabajo(resultado);
        $('detalleFechaCierre').textContent = fecha(d.fecha_hora_cierre || d.fecha_hora_fin, true);

        $('detalleTiempoActivo').textContent = duracion(d.total_segundos_activos);
        $('detalleTiempoPausa').textContent = duracion(d.total_segundos_pausa);
        $('detalleInicio').textContent = fecha(d.fecha_hora_inicio, true);
        $('detalleFin').textContent = fecha(d.fecha_hora_fin, true);
        $('detalleCumplimiento').textContent = cumplimiento(d.resultado_cumplimiento);

        $('detalleTipo').textContent = tipoMantenimiento(d.tipo_solicitud);
        $('detallePrioridad').textContent = 'Prioridad ' + texto(d.prioridad).toLowerCase();
        $('detalleSolicitante').textContent = texto(d.solicitante);
        $('detalleContacto').textContent = contacto || 'Sin datos de contacto';
        $('detalleProgramacion').textContent = d.fecha_programada
            ? fecha(d.fecha_programada)
            : 'Sin programación';
        $('detalleLimite').textContent = d.fecha_limite
            ? 'Límite: ' + fecha(d.fecha_limite)
            : 'Sin fecha límite';
        $('detalleRiesgo').textContent = numero(d.trabajo_peligroso) === 1
            ? 'Trabajo peligroso · riesgo ' + texto(d.nivel_riesgo).toLowerCase()
            : 'Riesgo ' + texto(d.nivel_riesgo).toLowerCase();
        const detallePeligro = texto(d.detalle_trabajo_peligroso, '');
        const textoParo = numero(d.requiere_paro_equipo) === 1
            ? 'Requirió paro de equipo'
            : 'No requirió paro de equipo';
        $('detalleParo').textContent = detallePeligro ? detallePeligro + ' · ' + textoParo : textoParo;

        pintarBannerResultado(resultado, d);
        $('detalleSolicitud').textContent = texto(d.descripcion_solicitud);
        $('detalleTrabajoRealizado').textContent = texto(d.descripcion_trabajo_realizado, 'No existe una descripción de cierre registrada.');

        pintarBloqueTexto('bloqueQueFalto', 'detalleQueFalto', d.que_falto);

        const condicion = [d.tipo_falla, d.causa_averia, d.descripcion_falla, d.causa_desconocida_descripcion]
            .map((valor) => texto(valor, ''))
            .filter(Boolean)
            .join(' · ');
        pintarBloqueTexto('bloqueCondicion', 'detalleCondicion', condicion);
        pintarBloqueTexto('bloqueImpacto', 'detalleImpacto', d.impacto_operacion);

        const objetivo = [d.objetivo_mejora, d.resultado_esperado, d.justificacion_mejora]
            .map((valor) => texto(valor, ''))
            .filter(Boolean)
            .join('\n\n');
        pintarBloqueTexto('bloqueObjetivo', 'detalleObjetivo', objetivo);

        $('detalleCerradoPor').textContent = 'Cerrado por ' + texto(d.cerrado_por) + ' el ' + fecha(d.fecha_hora_cierre || d.fecha_hora_fin, true) + '.';
        pintarVerificacion('Limpieza', numero(d.realizo_limpieza_area) === 1);
        pintarVerificacion('Orden', numero(d.area_ordenada_libre_componentes) === 1);
        pintarBloqueTexto('bloqueObservacionesCierre', 'detalleObservacionesCierre', d.observaciones_cierre, false);

        const editado = numero(d.editado_por_admin_id) > 0;
        $('detalleCierreEditado').hidden = !editado;
        $('bloqueEdicionCierre').hidden = !editado;
        if (editado) {
            $('detalleCierreEditado').textContent = 'Editado por ' + texto(d.cierre_editado_por, 'administración');
            $('detalleMotivoEdicion').textContent = texto(d.motivo_edicion, 'No se registró el motivo de edición.');
        }

        pintarRecursosHistorial();
        pintarParticipantes();
        pintarPausas();
        pintarHistorial();
        pintarEvidencias();
    }

    function pintarListaRecursosHistorial(grupo, modo) {
        const herramientas = Array.isArray(grupo && grupo.herramientas) ? grupo.herramientas : [];
        const refacciones = Array.isArray(grupo && grupo.refacciones) ? grupo.refacciones : [];
        const sinHerramientas = numero(grupo && grupo.sin_herramientas_utilizadas) === 1;
        const sinRefacciones = numero(grupo && grupo.sin_refacciones_utilizadas) === 1;

        const bloque = (titulo, icono, recursos, sinUso, mensaje) => {
            let contenido = '';
            if (recursos.length > 0) {
                contenido = `<ul>${recursos.map((recurso) => `
                    <li>
                        <span aria-hidden="true">${icono}</span>
                        <div>
                            <strong>${escapar(texto(recurso.nombre))}${numero(recurso.es_otro) === 1 ? ' <em>Otro</em>' : ''}</strong>
                            ${recurso.codigo ? `<small>${escapar(texto(recurso.codigo))}</small>` : ''}
                            ${recurso.descripcion ? `<p>${escapar(texto(recurso.descripcion))}</p>` : ''}
                            ${recurso.estado_sugerencia ? `<small>Sugerencia: ${escapar(texto(recurso.estado_sugerencia).toLowerCase())}</small>` : ''}
                        </div>
                    </li>
                `).join('')}</ul>`;
            } else if (modo === 'actual' && sinUso) {
                contenido = `<p class="mfin-history-resource-empty is-confirmed">El técnico confirmó que no utilizó ${escapar(titulo.toLowerCase())}.</p>`;
            } else {
                contenido = `<p class="mfin-history-resource-empty">${escapar(mensaje)}</p>`;
            }

            return `<section class="mfin-history-resource-list">
                <h6><span aria-hidden="true">${icono}</span>${escapar(titulo)}</h6>
                ${contenido}
            </section>`;
        };

        return bloque(
            'Herramientas', '🔧', herramientas, sinHerramientas,
            modo === 'actual' ? 'No se registraron herramientas realmente utilizadas.' : 'No hubo herramientas recomendadas.'
        ) + bloque(
            'Refacciones', '⚙️', refacciones, sinRefacciones,
            modo === 'actual' ? 'No se registraron refacciones realmente utilizadas.' : 'No hubo refacciones recomendadas.'
        );
    }

    function pintarRecursosHistorial() {
        $('detalleRecursosRecomendados').innerHTML = pintarListaRecursosHistorial(
            estado.recursosRecomendados || {},
            'recomendado'
        );
        $('detalleRecursosUtilizados').innerHTML = pintarListaRecursosHistorial(
            estado.recursosUtilizados || {},
            'actual'
        );
    }

    function pintarBannerResultado(resultado, detalle) {
        const banner = $('detalleResultadoBanner');
        const titulo = $('detalleResultadoTitulo');
        const textoResultado = $('detalleResultadoTexto');
        const icono = $('detalleResultadoIcono');
        banner.className = 'mfin-result-banner mfin-result-banner--' + claseResultado(resultado);

        if (resultado === 'TERMINADO') {
            icono.textContent = '✓';
            titulo.textContent = 'El trabajo quedó terminado';
            textoResultado.textContent = 'El cierre no reportó actividades pendientes.';
        } else if (resultado === 'PARCIAL') {
            icono.textContent = '◐';
            titulo.textContent = 'El trabajo quedó parcial';
            textoResultado.textContent = texto(detalle.que_falto, 'El cierre fue parcial, pero no se registró qué faltó.');
        } else if (resultado === 'PROVISIONAL') {
            icono.textContent = '◇';
            titulo.textContent = 'Se aplicó una solución provisional';
            textoResultado.textContent = texto(detalle.que_falto, 'Se registró una solución provisional sin descripción de pendientes.');
        } else {
            icono.textContent = '!';
            titulo.textContent = 'No existe cierre detallado';
            textoResultado.textContent = 'La ejecución aparece terminada, pero no se encontró el registro de cierre asociado.';
        }
    }

    function pintarBloqueTexto(bloqueId, textoId, valor, ocultarSiVacio = true) {
        const bloque = $(bloqueId);
        const contenido = texto(valor, '');
        bloque.hidden = ocultarSiVacio && !contenido;
        $(textoId).textContent = contenido || 'Sin información registrada.';
    }

    function pintarVerificacion(tipo, correcto) {
        const esLimpieza = tipo === 'Limpieza';
        const card = $(esLimpieza ? 'detalleLimpiezaCard' : 'detalleOrdenCard');
        const icono = $(esLimpieza ? 'detalleLimpiezaIcono' : 'detalleOrdenIcono');
        const textoElemento = $(esLimpieza ? 'detalleLimpieza' : 'detalleOrden');
        card.className = correcto ? 'is-ok' : 'is-warning';
        icono.textContent = correcto ? '✓' : '!';
        textoElemento.textContent = correcto
            ? (esLimpieza ? 'Se confirmó la limpieza.' : 'Se confirmó el orden del área.')
            : (esLimpieza ? 'No se confirmó la limpieza.' : 'No se confirmó el orden del área.');
    }

    function pintarParticipantes() {
        const lista = $('listaParticipantes');
        const participantes = estado.participantes;
        $('contadorParticipantes').textContent = participantes.length + ' ' + plural(participantes.length, 'participante', 'participantes');

        if (participantes.length === 0) {
            lista.innerHTML = '<div class="mfin-mini-empty">No se encontraron participantes vinculados.</div>';
            return;
        }

        lista.innerHTML = participantes.map((p) => {
            const esActual = numero(p.es_actual) === 1;
            const estadoTexto = etiquetasEstadoParticipacion[p.estado_participacion] || texto(p.estado_participacion);
            const clase = p.estado_participacion === 'TERMINADO' ? 'success' : (p.estado_participacion === 'NO_PARTICIPO' ? 'muted' : 'warning');

            return `
                <article class="mfin-participant ${esActual ? 'is-current' : ''}">
                    <div class="mfin-participant__avatar" aria-hidden="true">${escapar(iniciales(p.tecnico))}</div>
                    <div class="mfin-participant__body">
                        <div class="mfin-participant__title">
                            <strong>${escapar(texto(p.tecnico))}${esActual ? ' (Tú)' : ''}</strong>
                            <span class="mfin-badge mfin-badge--${escapar(clase)}">${escapar(estadoTexto)}</span>
                        </div>
                        <p>${escapar(texto(p.especialidad, 'Sin especialidad'))} · ${escapar(texto(p.turno, 'Sin turno'))}</p>
                        <dl>
                            <div><dt>Inicio</dt><dd>${escapar(fecha(p.fecha_hora_inicio, true))}</dd></div>
                            <div><dt>Fin</dt><dd>${escapar(fecha(p.fecha_hora_fin, true))}</dd></div>
                            <div><dt>Activo</dt><dd>${escapar(duracion(p.total_segundos_activos, true))}</dd></div>
                            <div><dt>Pausa</dt><dd>${escapar(duracion(p.total_segundos_pausa, true))}</dd></div>
                            <div><dt>Cumplimiento</dt><dd>${escapar(cumplimiento(p.resultado_cumplimiento))}</dd></div>
                        </dl>
                    </div>
                </article>
            `;
        }).join('');
    }

    function iniciales(nombre) {
        const partes = texto(nombre, 'T').split(/\s+/).filter(Boolean).slice(0, 2);
        return partes.map((parte) => parte.charAt(0).toUpperCase()).join('') || 'T';
    }

    function pintarPausas() {
        const lista = $('listaPausas');
        const pausas = estado.pausas;
        $('contadorPausas').textContent = pausas.length + ' ' + plural(pausas.length, 'pausa', 'pausas');
        $('pausasVacias').hidden = pausas.length !== 0;

        if (pausas.length === 0) {
            lista.innerHTML = '';
            return;
        }

        lista.innerHTML = pausas.map((pausa, indice) => {
            const urgencia = pausa.folio_urgencia
                ? ` · Relacionada con ${texto(pausa.folio_urgencia)}`
                : '';
            return `
                <article class="mfin-pause-item">
                    <span class="mfin-pause-item__number">${indice + 1}</span>
                    <div>
                        <div class="mfin-pause-item__head">
                            <strong>${escapar(etiquetasPausa[pausa.motivo] || texto(pausa.motivo))}${pausa.tecnico && $('filtroAlcance').value === 'TODOS' ? escapar(' · ' + pausa.tecnico) : ''}</strong>
                            <span>${escapar(duracion(pausa.duracion_segundos, true))}</span>
                        </div>
                        <p>${escapar(fecha(pausa.fecha_hora_inicio, true))} → ${escapar(fecha(pausa.fecha_hora_fin, true))}${escapar(urgencia)}</p>
                        ${texto(pausa.observaciones, '') ? `<small>${escapar(pausa.observaciones)}</small>` : ''}
                    </div>
                </article>
            `;
        }).join('');
    }

    function pintarHistorial() {
        const lista = $('listaHistorial');
        const historial = estado.historial;
        $('contadorEventos').textContent = historial.length + ' ' + plural(historial.length, 'evento', 'eventos');

        if (historial.length === 0) {
            lista.innerHTML = '<div class="mfin-mini-empty">No se encontraron eventos de trazabilidad.</div>';
            return;
        }

        lista.innerHTML = historial.map((evento) => `
            <article class="mfin-timeline-item mfin-timeline-item--${escapar(String(evento.evento || '').toLowerCase())}">
                <span class="mfin-timeline-item__dot" aria-hidden="true"></span>
                <div>
                    <header>
                        <strong>${escapar(etiquetasEvento[evento.evento] || texto(evento.evento))}</strong>
                        <time>${escapar(fecha(evento.fecha_evento, true))}</time>
                    </header>
                    <p>${escapar(texto(evento.descripcion))}</p>
                    <small>Por ${escapar(texto(evento.actor, evento.actor_tipo || 'Sistema'))}</small>
                </div>
            </article>
        `).join('');
    }

    function pintarEvidencias() {
        const lista = $('listaEvidencias');
        const evidencias = estado.evidencias;
        $('contadorEvidencias').textContent = evidencias.length + ' ' + plural(evidencias.length, 'archivo', 'archivos');
        $('evidenciasVacias').hidden = evidencias.length !== 0;

        if (evidencias.length === 0) {
            lista.innerHTML = '';
            return;
        }

        lista.innerHTML = evidencias.map((evidencia) => {
            const enlace = evidencia.ruta_publica
                ? `<a href="${escapar(evidencia.ruta_publica)}" target="_blank" rel="noopener">Abrir archivo</a>`
                : '<span class="mfin-evidence-unavailable">Ruta no disponible</span>';
            return `
                <article class="mfin-evidence">
                    <span class="mfin-evidence__icon" aria-hidden="true">${escapar(iconoEvidencia(evidencia.mime_type))}</span>
                    <div>
                        <strong>${escapar(texto(evidencia.nombre_original))}</strong>
                        <p>${escapar(texto(evidencia.tipo_evidencia))} · ${escapar(tamano(evidencia.tamano_bytes))} · ${escapar(fecha(evidencia.fecha_registro, true))}</p>
                        ${texto(evidencia.descripcion, '') ? `<small>${escapar(evidencia.descripcion)}</small>` : ''}
                        <footer><span>Subido por ${escapar(texto(evidencia.subido_por))}</span>${enlace}</footer>
                    </div>
                </article>
            `;
        }).join('');
    }

    function iconoEvidencia(mime) {
        const tipo = String(mime || '').toLowerCase();
        if (tipo.startsWith('image/')) return '▧';
        if (tipo.includes('pdf')) return 'PDF';
        return '□';
    }

    function activarTab(nombre) {
        document.querySelectorAll('.mfin-tab').forEach((boton) => {
            boton.classList.toggle('is-active', boton.dataset.tab === nombre);
        });
        document.querySelectorAll('.mfin-tab-panel').forEach((panel) => {
            panel.classList.toggle('is-active', panel.dataset.panel === nombre);
        });
    }

    function exportar() {
        if (!validarFechas()) return;
        const parametros = parametrosFiltros(false);
        parametros.set('accion', 'EXPORTAR');
        window.location.href = ENDPOINT + '?' + parametros.toString();
    }

    function imprimirDetalle() {
        if (!estado.detalle) return;
        document.body.classList.add('mfin-printing-detail');
        window.print();
        window.setTimeout(() => document.body.classList.remove('mfin-printing-detail'), 200);
    }

    $('formFiltros').addEventListener('submit', (evento) => {
        evento.preventDefault();
        estado.pagina = 1;
        cargarHistorial();
    });

    $('filtroBusqueda').addEventListener('input', () => {
        pintarContadorFiltros();
        window.clearTimeout(estado.busquedaTemporizador);
        estado.busquedaTemporizador = window.setTimeout(() => {
            estado.pagina = 1;
            cargarHistorial(true);
        }, 550);
    });

    ['filtroAlcance', 'filtroTipo', 'filtroResultado', 'filtroCumplimiento', 'filtroDesde', 'filtroHasta', 'filtroOrden', 'filtroPorPagina']
        .forEach((id) => {
            $(id).addEventListener('change', () => {
                pintarContadorFiltros();
                estado.pagina = 1;
                cargarHistorial(true);
            });
        });

    $('btnActualizar').addEventListener('click', () => cargarHistorial());
    $('btnAplicar').addEventListener('click', () => {});
    $('btnLimpiar').addEventListener('click', () => limpiarFiltros());
    $('btnLimpiarVacio').addEventListener('click', () => limpiarFiltros());
    $('btnExportar').addEventListener('click', exportar);
    $('btnImprimirDetalle').addEventListener('click', imprimirDetalle);

    $('listaMantenimientos').addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-ver-detalle]');
        if (!boton) return;
        abrirDetalle(numero(boton.dataset.verDetalle));
    });

    $('listaCancelaciones').addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-ver-cancelacion]');
        if (!boton) return;
        mostrarCancelacion(numero(boton.dataset.verCancelacion));
    });

    $('paginacion').addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-pagina]');
        if (!boton || boton.disabled) return;
        const pagina = numero(boton.dataset.pagina);
        if (pagina < 1 || pagina === estado.pagina) return;
        estado.pagina = pagina;
        cargarHistorial().then(() => {
            document.querySelector('.mfin-results-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    $('modalDetalle').addEventListener('click', (evento) => {
        if (evento.target.closest('[data-cerrar-modal]')) {
            cerrarModal(); 
        }
    });

    document.querySelector('.mfin-tabs').addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-tab]');
        if (boton) activarTab(boton.dataset.tab);
    });

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && !$('modalDetalle').hidden) {
            cerrarModal();
        }
    });

    window.addEventListener('popstate', () => {
        if (!$('modalDetalle').hidden) cerrarModal();
    });

    leerUrl();
    pintarContadorFiltros();
    cargarHistorial();
})();
</script>
</body>
</html>