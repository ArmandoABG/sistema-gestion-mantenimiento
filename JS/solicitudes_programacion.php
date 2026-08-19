<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/seguridad.php';
sm_requerir_sesion(['ADMIN'], false);

$nombreAdmin = trim((string) ($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'Administrador'));
$cssProgramacion = __DIR__ . '/../css/style_solicitudes_programacion.css';
$versionCss = file_exists($cssProgramacion)
    ? (string) filemtime($cssProgramacion)
    : (string) time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0b2b47">
    <meta name="description" content="Programación y asignación administrativa de mantenimientos">
    <title>Programar y asignar | Sistema de Mantenimiento</title>
    <link
        rel="stylesheet"
        href="../css/style_solicitudes_programacion.css?v=<?= htmlspecialchars($versionCss, ENT_QUOTES, 'UTF-8') ?>"
    >
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="sprog-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="sprog-icon-sparkles" viewBox="0 0 24 24">
        <path d="m12 3-1.2 3.8L7 8l3.8 1.2L12 13l1.2-3.8L17 8l-3.8-1.2L12 3Z"/>
        <path d="m5 15-.7 2.3L2 18l2.3.7L5 21l.7-2.3L8 18l-2.3-.7L5 15Z"/>
        <path d="m19 13-.7 2.3-2.3.7 2.3.7L19 19l.7-2.3 2.3-.7-2.3-.7L19 13Z"/>
    </symbol>
    <symbol id="sprog-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="sprog-icon-calendar" viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path d="M16 3v4M8 3v4M3 10h18"/>
    </symbol>
    <symbol id="sprog-icon-plan" viewBox="0 0 24 24">
        <path d="M4 20V9M10 20V4M16 20v-7M22 20H2"/>
        <path d="m18 6 2 2 3-4"/>
    </symbol>
    <symbol id="sprog-icon-balance" viewBox="0 0 24 24">
        <path d="M12 3v18M5 6h14M5 6l-3 6h6L5 6ZM19 6l-3 6h6l-3-6ZM8 21h8"/>
    </symbol>
    <symbol id="sprog-icon-shield" viewBox="0 0 24 24">
        <path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="sprog-icon-inbox" viewBox="0 0 24 24">
        <path d="M4 4h16l2 9v7H2v-7l2-9Z"/>
        <path d="M2 13h5l2 3h6l2-3h5"/>
    </symbol>
    <symbol id="sprog-icon-week" viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path d="M16 3v4M8 3v4M3 10h18M7 14h3M14 14h3M7 18h3M14 18h3"/>
    </symbol>
    <symbol id="sprog-icon-warning" viewBox="0 0 24 24">
        <path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
        <path d="M12 9v4M12 17h.01"/>
    </symbol>
    <symbol id="sprog-icon-activity" viewBox="0 0 24 24">
        <path d="M3 12h4l2-7 4 14 2-7h6"/>
    </symbol>
    <symbol id="sprog-icon-filter" viewBox="0 0 24 24">
        <path d="M4 5h16M7 12h10M10 19h4"/>
    </symbol>
    <symbol id="sprog-icon-arrow-left" viewBox="0 0 24 24">
        <path d="m15 18-6-6 6-6"/>
    </symbol>
    <symbol id="sprog-icon-arrow-right" viewBox="0 0 24 24">
        <path d="m9 18 6-6-6-6"/>
    </symbol>
    <symbol id="sprog-icon-check" viewBox="0 0 24 24">
        <path d="m5 12 4 4L19 6"/>
    </symbol>
    <symbol id="sprog-icon-users" viewBox="0 0 24 24">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
    </symbol>
</svg>

<main class="sprog-page">
    <div class="sprog-ambient sprog-ambient--one" aria-hidden="true"></div>
    <div class="sprog-ambient sprog-ambient--two" aria-hidden="true"></div>

    <section class="sprog-heading sprog-hero" aria-labelledby="tituloProgramacion">
        <div class="sprog-hero__pattern" aria-hidden="true"></div>

        <div class="sprog-hero__content">
            <div class="sprog-hero__copy">
                <p class="sprog-eyebrow">
                    <span class="sprog-eyebrow__icon" aria-hidden="true">
                        <svg><use href="#sprog-icon-sparkles"></use></svg>
                    </span>
                    Planificación administrativa
                </p>

                <h1 id="tituloProgramacion">Programar y asignar</h1>

                <p class="sprog-hero__description">
                    Organiza la semana, compara la carga real de cada técnico y ajusta las
                    asignaciones antes de que comiencen los mantenimientos.
                </p>

                <div class="sprog-hero__meta">
                    <span>
                        <span class="sprog-live-dot" aria-hidden="true"></span>
                        Planeación operativa actualizada
                    </span>
                    <span>
                        Administrador:
                        <strong><?= htmlspecialchars($nombreAdmin, ENT_QUOTES, 'UTF-8') ?></strong>
                    </span>
                </div>
            </div>

            <div class="sprog-hero__actions">
                <div class="sprog-hero__mini-card" aria-hidden="true">
                    <span class="sprog-hero__mini-icon">
                        <svg><use href="#sprog-icon-calendar"></use></svg>
                    </span>
                    <div>
                        <small>Centro de planeación</small>
                        <strong>Agenda y personal</strong>
                    </div>
                </div>

                <button
                    type="button"
                    class="sprog-btn sprog-btn--hero sprog-btn--secondary"
                    id="btnActualizar"
                >
                    <svg aria-hidden="true"><use href="#sprog-icon-refresh"></use></svg>
                    <span>Actualizar información</span>
                </button>
            </div>
        </div>
    </section>

    <section class="sprog-guides" aria-label="Reglas de programación">
        <article>
            <span class="sprog-guide-icon" aria-hidden="true">
                <svg><use href="#sprog-icon-plan"></use></svg>
            </span>
            <div>
                <strong>Planifica con anticipación</strong>
                <p>La vista inicia en la próxima semana para dejar preparada la carga antes de que comience.</p>
            </div>
        </article>

        <article>
            <span class="sprog-guide-icon" aria-hidden="true">
                <svg><use href="#sprog-icon-balance"></use></svg>
            </span>
            <div>
                <strong>Distribución visible</strong>
                <p>Compara mantenimientos activos, carga diaria, carga semanal y trabajos atrasados por técnico.</p>
            </div>
        </article>

        <article>
            <span class="sprog-guide-icon" aria-hidden="true">
                <svg><use href="#sprog-icon-shield"></use></svg>
            </span>
            <div>
                <strong>Reasignación controlada</strong>
                <p>Cambia fecha o personal antes de iniciar. Los técnicos que ya comenzaron quedan protegidos.</p>
            </div>
        </article>
    </section>

    <section class="sprog-week-card" aria-labelledby="tituloSemana">
        <header class="sprog-week-card__head">
            <div class="sprog-section-heading">
                <span class="sprog-section-heading__icon" aria-hidden="true">
                    <svg><use href="#sprog-icon-week"></use></svg>
                </span>
                <div>
                    <p class="sprog-eyebrow">Semana de trabajo</p>
                    <h2 id="tituloSemana">Cargando semana...</h2>
                    <p>Selecciona únicamente el día; no se solicita una hora exacta ni una duración estimada.</p>
                </div>
            </div>

            <div class="sprog-week-actions">
                <button
                    type="button"
                    class="sprog-icon-btn"
                    id="btnSemanaAnterior"
                    aria-label="Semana anterior"
                    title="Semana anterior"
                >
                    <svg aria-hidden="true"><use href="#sprog-icon-arrow-left"></use></svg>
                </button>

                <button type="button" class="sprog-btn sprog-btn--soft" id="btnEstaSemana">
                    Esta semana
                </button>

                <button type="button" class="sprog-btn sprog-btn--soft" id="btnProximaSemana">
                    Próxima semana
                </button>

                <button
                    type="button"
                    class="sprog-icon-btn"
                    id="btnSemanaSiguiente"
                    aria-label="Semana siguiente"
                    title="Semana siguiente"
                >
                    <svg aria-hidden="true"><use href="#sprog-icon-arrow-right"></use></svg>
                </button>
            </div>
        </header>

        <div class="sprog-week-days" id="diasSemana" aria-label="Días de la semana"></div>
    </section>

    <div class="sprog-status is-loading" id="estadoPagina" role="status" aria-live="polite">
        <span class="sprog-status__indicator" aria-hidden="true"></span>
        <span>Cargando solicitudes y carga de técnicos...</span>
    </div>

    <section class="sprog-kpis" aria-label="Resumen de programación">
        <article class="sprog-kpi sprog-kpi--pending">
            <span class="sprog-kpi__icon" aria-hidden="true">
                <svg><use href="#sprog-icon-inbox"></use></svg>
            </span>
            <div>
                <span>Por programar</span>
                <strong id="kpiPorProgramar">0</strong>
                <small>Aprobadas sin fecha</small>
            </div>
        </article>

        <article class="sprog-kpi sprog-kpi--week">
            <span class="sprog-kpi__icon" aria-hidden="true">
                <svg><use href="#sprog-icon-week"></use></svg>
            </span>
            <div>
                <span>En esta semana</span>
                <strong id="kpiSemana">0</strong>
                <small>Programaciones actuales</small>
            </div>
        </article>

        <article class="sprog-kpi sprog-kpi--late">
            <span class="sprog-kpi__icon" aria-hidden="true">
                <svg><use href="#sprog-icon-warning"></use></svg>
            </span>
            <div>
                <span>Atrasados sin iniciar</span>
                <strong id="kpiAtrasados">0</strong>
                <small>Disponibles para mover o reasignar</small>
            </div>
        </article>

        <article class="sprog-kpi sprog-kpi--active">
            <span class="sprog-kpi__icon" aria-hidden="true">
                <svg><use href="#sprog-icon-activity"></use></svg>
            </span>
            <div>
                <span>En ejecución</span>
                <strong id="kpiEjecucion">0</strong>
                <small>Con cambios restringidos</small>
            </div>
        </article>
    </section>

    <section class="sprog-panel" aria-labelledby="tituloSolicitudesProgramacion">
        <header class="sprog-panel__head">
            <div class="sprog-section-heading">
                <span class="sprog-section-heading__icon sprog-section-heading__icon--soft" aria-hidden="true">
                    <svg><use href="#sprog-icon-filter"></use></svg>
                </span>
                <div>
                    <p class="sprog-eyebrow">Mantenimientos</p>
                    <h2 id="tituloSolicitudesProgramacion">Solicitudes listas para organizar</h2>
                    <p>Las urgencias no aparecen aquí porque los técnicos las aceptan directamente.</p>
                </div>
            </div>

            <span class="sprog-count" id="contadorResultados">0 resultados</span>
        </header>

        <div class="sprog-tabs" role="tablist" aria-label="Estado de programación">
            <button type="button" class="is-active" data-tab="TODOS" role="tab" aria-selected="true">
                Todos <b id="tabTodos">0</b>
            </button>
            <button type="button" data-tab="POR_PROGRAMAR" role="tab" aria-selected="false">
                Por programar <b id="tabPendientes">0</b>
            </button>
            <button type="button" data-tab="PROGRAMADO" role="tab" aria-selected="false">
                Programados <b id="tabProgramados">0</b>
            </button>
            <button type="button" data-tab="ATRASADO_SIN_INICIAR" role="tab" aria-selected="false">
                Atrasados <b id="tabAtrasados">0</b>
            </button>
            <button type="button" data-tab="EN_EJECUCION" role="tab" aria-selected="false">
                En ejecución <b id="tabEjecucion">0</b>
            </button>
        </div>

        <div class="sprog-filters">
            <label class="sprog-search">
                <span>Buscar mantenimiento</span>
                <input
                    type="search"
                    id="filtroBusqueda"
                    maxlength="120"
                    placeholder="Folio, equipo, área o técnico"
                    autocomplete="off"
                >
            </label>

            <label>
                <span>Tipo</span>
                <select id="filtroTipo">
                    <option value="">Todos</option>
                    <option value="CORRECTIVO_PROGRAMABLE">Correctivo programable</option>
                    <option value="MODIFICACION_MEJORA">Modificación o mejora</option>
                    <option value="RUTINARIO">Rutinario</option>
                </select>
            </label>

            <label>
                <span>Prioridad</span>
                <select id="filtroPrioridad">
                    <option value="">Todas</option>
                    <option value="ALTA">Alta</option>
                    <option value="MEDIA">Media</option>
                    <option value="BAJA">Baja</option>
                </select>
            </label>

            <label>
                <span>Semana</span>
                <select id="filtroSemana">
                    <option value="">Cualquier fecha</option>
                    <option value="SI">Solo semana visible</option>
                    <option value="NO">Fuera de la semana</option>
                </select>
            </label>
        </div>

        <div class="sprog-request-list" id="listaSolicitudes"></div>

        <div class="sprog-empty" id="vacioSolicitudes" hidden>
            <span class="sprog-empty__icon" aria-hidden="true">
                <svg><use href="#sprog-icon-check"></use></svg>
            </span>
            <h3>No hay mantenimientos con estos filtros</h3>
            <p>Cambia la pestaña, la semana o los filtros para consultar otros registros.</p>
        </div>
    </section>

    <footer class="sprog-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Programación administrativa · Los Chapeteados División Petfood</span>
    </footer>

    <div class="sprog-tools-background" aria-hidden="true"></div>
</main>

<div class="sprog-modal" id="modalProgramacion" role="dialog" aria-modal="true" aria-labelledby="tituloModal" hidden>
    <div class="sprog-modal__dialog">
        <header class="sprog-modal__head">
            <div>
                <p class="sprog-eyebrow" id="etiquetaModal">PROGRAMACIÓN</p>
                <h2 id="tituloModal">Programar mantenimiento</h2>
                <p id="subtituloModal">Selecciona el día y distribuye la carga entre los técnicos.</p>
            </div>
            <button type="button" class="sprog-modal__close" id="btnCerrarModal" aria-label="Cerrar">×</button>
        </header>

        <form id="formProgramacion" class="sprog-modal__body" novalidate>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="solicitud_id" id="solicitudId">

            <section class="sprog-summary" id="resumenSolicitudModal"></section>

            <section class="sprog-form-section">
                <header>
                    <div>
                        <span class="sprog-step">01</span>
                        <div>
                            <h3>Día programado</h3>
                            <p>La fecha programada también será la fecha límite para medir cumplimiento.</p>
                        </div>
                    </div>
                </header>

                <div class="sprog-date-layout">
                    <label class="sprog-field">
                        <span>Fecha del mantenimiento *</span>
                        <input type="date" name="fecha_programada" id="fechaProgramada" required>
                        <small id="ayudaFecha">Selecciona un día hábil.</small>
                    </label>
                    <div class="sprog-calendar-state" id="estadoCalendario">
                        Consultando calendario...
                    </div>
                </div>

                <div class="sprog-lock-note" id="avisoFechaBloqueada" hidden>
                    <span aria-hidden="true">🔒</span>
                    <p>
                        La fecha está bloqueada porque un técnico ya inició. Aún puedes agregar personal o retirar únicamente a quienes no comenzaron.
                    </p>
                </div>
            </section>

            <section class="sprog-form-section">
                <header class="sprog-technician-header">
                    <div>
                        <span class="sprog-step">02</span>
                        <div>
                            <h3>Seleccionar técnicos</h3>
                            <p>Máximo 5. La carga es informativa y no bloquea nuevas asignaciones.</p>
                        </div>
                    </div>
                    <strong id="contadorSeleccionados">0 de 5 seleccionados</strong>
                </header>

                <div class="sprog-tech-filters">
                    <label class="sprog-search">
                        <span>Buscar técnico</span>
                        <input type="search" id="buscarTecnico" placeholder="Nombre o especialidad">
                    </label>
                    <label>
                        <span>Turno</span>
                        <select id="filtroTurnoTecnico">
                            <option value="">Todos</option>
                            <option value="MATUTINO">Matutino</option>
                            <option value="VESPERTINO">Vespertino</option>
                            <option value="NOCTURNO">Nocturno</option>
                        </select>
                    </label>
                    <label>
                        <span>Especialidad</span>
                        <select id="filtroEspecialidadTecnico">
                            <option value="">Todas</option>
                        </select>
                    </label>
                    <label>
                        <span>Departamento</span>
                        <select id="filtroDepartamentoTecnico">
                            <option value="">Todos</option>
                        </select>
                    </label>
                    <label>
                        <span>Ordenar</span>
                        <select id="ordenTecnicos">
                            <option value="CARGA_ACTIVA">Menor carga activa</option>
                            <option value="CARGA_SEMANA">Menor carga semanal</option>
                            <option value="CARGA_DIA">Menor carga del día</option>
                            <option value="ATRASADOS">Menos atrasados</option>
                            <option value="NOMBRE">Nombre</option>
                        </select>
                    </label>
                </div>

                <div class="sprog-tech-legend">
                    <span><i class="is-low"></i> 0–3 activos: carga baja</span>
                    <span><i class="is-medium"></i> 4–6 activos: carga media</span>
                    <span><i class="is-high"></i> 7 o más: carga alta</span>
                </div>

                <div class="sprog-tech-list" id="listaTecnicos"></div>

                <div class="sprog-empty sprog-empty--compact" id="vacioTecnicos" hidden>
                    <h3>No hay técnicos con esos filtros</h3>
                    <p>Quita algún filtro para ampliar los resultados.</p>
                </div>
            </section>

            <section class="sprog-form-section sprog-form-section--resources" id="seccionRecursos">
                <header class="sprog-resource-heading">
                    <div>
                        <span class="sprog-step">03</span>
                        <div>
                            <h3>Herramientas y refacciones recomendadas</h3>
                            <p id="descripcionFuenteRecursos">
                                Selecciona lo que el técnico debería llevar antes de iniciar.
                            </p>
                        </div>
                    </div>
                    <span class="sprog-resource-source" id="fuenteRecursos">Sin recomendaciones</span>
                </header>

                <div class="sprog-resource-lock" id="avisoRecursosBloqueados" hidden>
                    <span aria-hidden="true">🔒</span>
                    <p>
                        La lista ya no puede cambiarse porque al menos un técnico inició el mantenimiento.
                    </p>
                </div>

                <div class="sprog-resource-grid">
                    <div class="sprog-resource-picker" data-resource-picker="HERRAMIENTA">
                        <div class="sprog-resource-picker__head">
                            <span class="sprog-resource-icon" aria-hidden="true">🔧</span>
                            <div>
                                <strong>Herramientas</strong>
                                <small id="contadorHerramientas">0 seleccionadas</small>
                            </div>
                        </div>

                        <label class="sprog-resource-search">
                            <span class="sr-only">Buscar herramienta</span>
                            <input
                                type="search"
                                id="buscarHerramienta"
                                autocomplete="off"
                                placeholder="Buscar por nombre, código o descripción..."
                            >
                        </label>

                        <div
                            class="sprog-resource-results"
                            id="resultadosHerramientas"
                            role="listbox"
                            hidden
                        ></div>

                        <div
                            class="sprog-resource-selected"
                            id="herramientasSeleccionadas"
                            aria-live="polite"
                        ></div>
                    </div>

                    <div class="sprog-resource-picker" data-resource-picker="REFACCION">
                        <div class="sprog-resource-picker__head">
                            <span class="sprog-resource-icon" aria-hidden="true">⚙️</span>
                            <div>
                                <strong>Refacciones</strong>
                                <small id="contadorRefacciones">0 seleccionadas</small>
                            </div>
                        </div>

                        <label class="sprog-resource-search">
                            <span class="sr-only">Buscar refacción</span>
                            <input
                                type="search"
                                id="buscarRefaccion"
                                autocomplete="off"
                                placeholder="Buscar por nombre, código o descripción..."
                            >
                        </label>

                        <div
                            class="sprog-resource-results"
                            id="resultadosRefacciones"
                            role="listbox"
                            hidden
                        ></div>

                        <div
                            class="sprog-resource-selected"
                            id="refaccionesSeleccionadas"
                            aria-live="polite"
                        ></div>
                    </div>
                </div>

                <div class="sprog-resource-note" id="notaRecursos">
                    En mantenimientos normales, esta selección se recordará por equipo y tipo.
                    En rutinas, solo cambiará esta ejecución y la plantilla permanecerá intacta.
                </div>
            </section>

            <section class="sprog-night-warning" id="avisoRiesgoNocturno" hidden>
                <span class="sprog-night-warning__icon" aria-hidden="true">!</span>
                <div>
                    <strong>Trabajo peligroso con personal de turno nocturno</strong>
                    <p>
                        El turno solo genera esta alerta. Revisa las condiciones reales de seguridad antes de guardar.
                    </p>
                    <p class="sprog-night-warning__detail" id="detallePeligroNocturno">
                        Trabajo peligroso sin una nota específica.
                    </p>
                    <label class="sprog-check-confirm">
                        <input type="checkbox" name="confirmar_riesgo_nocturno" id="confirmarRiesgoNocturno" value="1">
                        <span>Confirmo que revisé el turno real y las condiciones del trabajo peligroso.</span>
                    </label>
                    <label class="sprog-field">
                        <span>Observación de seguridad <em>opcional</em></span>
                        <textarea name="observacion_riesgo_nocturno" id="observacionRiesgoNocturno" maxlength="500" rows="3" placeholder="Equipo, iluminación, acompañamiento, permisos o indicaciones relevantes"></textarea>
                    </label>
                </div>
            </section>

            <section class="sprog-form-section sprog-form-section--reason">
                <header>
                    <div>
                        <span class="sprog-step">04</span>
                        <div>
                            <h3>Motivo y confirmación</h3>
                            <p id="textoMotivo">En una programación nueva el motivo es opcional.</p>
                        </div>
                    </div>
                </header>
                <label class="sprog-field">
                    <span>Motivo de programación o cambio</span>
                    <textarea name="motivo" id="motivoProgramacion" maxlength="500" rows="3" placeholder="Ejemplo: distribuir la carga de la próxima semana"></textarea>
                    <small id="contadorMotivo">0/500 caracteres</small>
                </label>
            </section>
        </form>

        <footer class="sprog-modal__foot">
            <div>
                <strong id="resumenGuardar">Selecciona una solicitud, fecha y técnicos.</strong>
                <small>Los técnicos retirados conservarán su historial.</small>
            </div>
            <div class="sprog-modal__buttons">
                <button
                    type="button"
                    class="sprog-btn sprog-btn--danger"
                    id="btnCancelarMantenimiento"
                    hidden
                >
                    Cancelar mantenimiento
                </button>
                <button type="button" class="sprog-btn sprog-btn--secondary" id="btnCancelarModal">Cerrar</button>
                <button type="submit" form="formProgramacion" class="sprog-btn sprog-btn--primary" id="btnGuardar">
                    Guardar programación
                </button>
            </div>
        </footer>
    </div>
</div>

<?php include __DIR__ . '/../inc/alertas.php'; ?>

<script>
(function () {
    'use strict';

    const UI = window.SistemaUI;
    const endpoint = '../funciones/solicitudes_programacion_funciones.php';
    const endpointRecursos = '../funciones/recursos_mantenimiento_funciones.php';

    if (!UI) {
        document.getElementById('estadoPagina').textContent =
            'No se cargaron las herramientas de la interfaz. Recarga la página con Ctrl + F5.';
        return;
    }

    const estado = {
        semanaInicio: '',
        semanaFin: '',
        diasSemana: [],
        fechaPreferida: '',
        solicitudes: [],
        catalogos: { especialidades: [], departamentos: [] },
        tab: 'TODOS',
        solicitud: null,
        asignaciones: [],
        tecnicos: [],
        seleccionados: new Set(),
        obligatorios: new Set(),
        fechaOriginal: '',
        tecnicosOriginales: [],
        recursosSeleccionados: {
            HERRAMIENTA: [],
            REFACCION: []
        },
        recursosOriginales: [],
        recursosContexto: {},
        puedeEditarRecursos: true,
        temporizadoresRecursos: {
            HERRAMIENTA: 0,
            REFACCION: 0
        },
        secuenciaBusquedaRecursos: {
            HERRAMIENTA: 0,
            REFACCION: 0
        },
        cancelandoId: 0,
        cargando: false
    };

    const dom = {
        btnActualizar: document.getElementById('btnActualizar'),
        btnSemanaAnterior: document.getElementById('btnSemanaAnterior'),
        btnSemanaSiguiente: document.getElementById('btnSemanaSiguiente'),
        btnEstaSemana: document.getElementById('btnEstaSemana'),
        btnProximaSemana: document.getElementById('btnProximaSemana'),
        tituloSemana: document.getElementById('tituloSemana'),
        diasSemana: document.getElementById('diasSemana'),
        estadoPagina: document.getElementById('estadoPagina'),
        listaSolicitudes: document.getElementById('listaSolicitudes'),
        vacioSolicitudes: document.getElementById('vacioSolicitudes'),
        contadorResultados: document.getElementById('contadorResultados'),
        filtroBusqueda: document.getElementById('filtroBusqueda'),
        filtroTipo: document.getElementById('filtroTipo'),
        filtroPrioridad: document.getElementById('filtroPrioridad'),
        filtroSemana: document.getElementById('filtroSemana'),
        modal: document.getElementById('modalProgramacion'),
        btnCerrarModal: document.getElementById('btnCerrarModal'),
        btnCancelarModal: document.getElementById('btnCancelarModal'),
        btnCancelarMantenimiento: document.getElementById('btnCancelarMantenimiento'),
        form: document.getElementById('formProgramacion'),
        solicitudId: document.getElementById('solicitudId'),
        etiquetaModal: document.getElementById('etiquetaModal'),
        tituloModal: document.getElementById('tituloModal'),
        subtituloModal: document.getElementById('subtituloModal'),
        resumenSolicitudModal: document.getElementById('resumenSolicitudModal'),
        fechaProgramada: document.getElementById('fechaProgramada'),
        ayudaFecha: document.getElementById('ayudaFecha'),
        estadoCalendario: document.getElementById('estadoCalendario'),
        avisoFechaBloqueada: document.getElementById('avisoFechaBloqueada'),
        listaTecnicos: document.getElementById('listaTecnicos'),
        vacioTecnicos: document.getElementById('vacioTecnicos'),
        contadorSeleccionados: document.getElementById('contadorSeleccionados'),
        buscarTecnico: document.getElementById('buscarTecnico'),
        filtroTurnoTecnico: document.getElementById('filtroTurnoTecnico'),
        filtroEspecialidadTecnico: document.getElementById('filtroEspecialidadTecnico'),
        filtroDepartamentoTecnico: document.getElementById('filtroDepartamentoTecnico'),
        ordenTecnicos: document.getElementById('ordenTecnicos'),
        fuenteRecursos: document.getElementById('fuenteRecursos'),
        descripcionFuenteRecursos: document.getElementById('descripcionFuenteRecursos'),
        notaRecursos: document.getElementById('notaRecursos'),
        avisoRecursosBloqueados: document.getElementById('avisoRecursosBloqueados'),
        buscarHerramienta: document.getElementById('buscarHerramienta'),
        buscarRefaccion: document.getElementById('buscarRefaccion'),
        resultadosHerramientas: document.getElementById('resultadosHerramientas'),
        resultadosRefacciones: document.getElementById('resultadosRefacciones'),
        herramientasSeleccionadas: document.getElementById('herramientasSeleccionadas'),
        refaccionesSeleccionadas: document.getElementById('refaccionesSeleccionadas'),
        contadorHerramientas: document.getElementById('contadorHerramientas'),
        contadorRefacciones: document.getElementById('contadorRefacciones'),
        avisoRiesgoNocturno: document.getElementById('avisoRiesgoNocturno'),
        detallePeligroNocturno: document.getElementById('detallePeligroNocturno'),
        confirmarRiesgoNocturno: document.getElementById('confirmarRiesgoNocturno'),
        observacionRiesgoNocturno: document.getElementById('observacionRiesgoNocturno'),
        motivoProgramacion: document.getElementById('motivoProgramacion'),
        textoMotivo: document.getElementById('textoMotivo'),
        contadorMotivo: document.getElementById('contadorMotivo'),
        resumenGuardar: document.getElementById('resumenGuardar'),
        btnGuardar: document.getElementById('btnGuardar')
    };

    async function cargarInicial(semana) {
        if (estado.cargando) {
            return;
        }

        estado.cargando = true;
        UI.estadoBoton(dom.btnActualizar, true, 'Actualizando...');
        mostrarEstado('Cargando solicitudes y distribución semanal...', 'loading');

        try {
            const parametros = new URLSearchParams({ accion: 'inicial' });
            if (semana) {
                parametros.set('semana', semana);
            }

            const respuesta = await UI.peticionJson(endpoint + '?' + parametros.toString());
            estado.semanaInicio = respuesta.semana.inicio;
            estado.semanaFin = respuesta.semana.fin;
            estado.diasSemana = respuesta.semana.dias || [];
            estado.solicitudes = respuesta.solicitudes || [];
            estado.catalogos = respuesta.catalogos || { especialidades: [], departamentos: [] };

            if (!estado.fechaPreferida || !fechaEnRango(estado.fechaPreferida, estado.semanaInicio, estado.semanaFin)) {
                const primerDiaPermitido = estado.diasSemana.find(function (dia) {
                    return dia.permitido && dia.fecha >= fechaHoy();
                });
                estado.fechaPreferida = primerDiaPermitido
                    ? primerDiaPermitido.fecha
                    : estado.semanaInicio;
            }

            pintarResumen(respuesta.resumen || {});
            pintarSemana();
            llenarCatalogosTecnicos();
            pintarSolicitudes();
            mostrarEstado(
                'Información actualizada ' + formatearFechaHora(respuesta.fecha_servidor) + '.',
                'success'
            );
        } catch (error) {
            console.error(error);
            mostrarEstado(error.message || 'No fue posible cargar las programaciones.', 'error');
        } finally {
            estado.cargando = false;
            UI.estadoBoton(dom.btnActualizar, false);
        }
    }

    function pintarResumen(resumen) {
        texto('kpiPorProgramar', resumen.por_programar || 0);
        texto('kpiSemana', resumen.en_semana || 0);
        texto('kpiAtrasados', resumen.atrasados_sin_iniciar || 0);
        texto('kpiEjecucion', resumen.en_ejecucion || 0);
    }

    function pintarSemana() {
        dom.tituloSemana.textContent =
            'Del ' + formatearFechaCorta(estado.semanaInicio) +
            ' al ' + formatearFechaCorta(estado.semanaFin);

        const nombres = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        dom.diasSemana.innerHTML = estado.diasSemana.map(function (dia, indice) {
            const fecha = new Date(dia.fecha + 'T12:00:00');
            const seleccionada = dia.fecha === estado.fechaPreferida;
            const total = estado.solicitudes.filter(function (solicitud) {
                return solicitud.fecha_programada === dia.fecha;
            }).length;
            const claseEstado = dia.permitido
                ? (dia.tipo_dia === 'HABIL_EXTRA' ? 'is-extra' : 'is-available')
                : 'is-disabled';

            return '' +
                '<button type="button" class="sprog-day ' + claseEstado +
                (seleccionada ? ' is-selected' : '') + '" data-fecha="' + escapeHtml(dia.fecha) + '">' +
                    '<span>' + nombres[indice] + '</span>' +
                    '<strong>' + fecha.getDate() + '</strong>' +
                    '<small>' + (total === 1 ? '1 mantenimiento' : total + ' mantenimientos') + '</small>' +
                    '<em>' + etiquetaDia(dia) + '</em>' +
                '</button>';
        }).join('');

        dom.diasSemana.querySelectorAll('[data-fecha]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                const fecha = boton.dataset.fecha || '';
                estado.fechaPreferida = fecha;
                pintarSemana();
            });
        });
    }

    function pintarSolicitudes() {
        const busqueda = normalizar(dom.filtroBusqueda.value);
        const tipo = dom.filtroTipo.value;
        const prioridad = dom.filtroPrioridad.value;
        const semana = dom.filtroSemana.value;

        const conteos = {
            TODOS: estado.solicitudes.length,
            POR_PROGRAMAR: 0,
            PROGRAMADO: 0,
            ATRASADO_SIN_INICIAR: 0,
            EN_EJECUCION: 0
        };

        estado.solicitudes.forEach(function (solicitud) {
            if (conteos[solicitud.grupo] !== undefined) {
                conteos[solicitud.grupo] += 1;
            }
        });

        texto('tabTodos', conteos.TODOS);
        texto('tabPendientes', conteos.POR_PROGRAMAR);
        texto('tabProgramados', conteos.PROGRAMADO);
        texto('tabAtrasados', conteos.ATRASADO_SIN_INICIAR);
        texto('tabEjecucion', conteos.EN_EJECUCION);

        const filtradas = estado.solicitudes.filter(function (solicitud) {
            if (estado.tab !== 'TODOS' && solicitud.grupo !== estado.tab) {
                return false;
            }
            if (tipo && solicitud.tipo_solicitud !== tipo) {
                return false;
            }
            if (prioridad && solicitud.prioridad !== prioridad) {
                return false;
            }
            if (semana === 'SI' && Number(solicitud.en_semana) !== 1) {
                return false;
            }
            if (semana === 'NO' && Number(solicitud.en_semana) === 1) {
                return false;
            }

            if (busqueda) {
                const contenido = normalizar([
                    solicitud.folio,
                    solicitud.codigo_equipo,
                    solicitud.nombre_equipo,
                    solicitud.departamento,
                    solicitud.area,
                    solicitud.proceso,
                    solicitud.tecnicos,
                    solicitud.descripcion_solicitud
                ].join(' '));
                if (contenido.indexOf(busqueda) === -1) {
                    return false;
                }
            }

            return true;
        });

        dom.contadorResultados.textContent =
            filtradas.length === 1 ? '1 resultado' : filtradas.length + ' resultados';
        dom.vacioSolicitudes.hidden = filtradas.length !== 0;
        dom.listaSolicitudes.hidden = filtradas.length === 0;

        dom.listaSolicitudes.innerHTML = filtradas.map(tarjetaSolicitud).join('');
        dom.listaSolicitudes.querySelectorAll('[data-programar]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                abrirProgramacion(Number(boton.dataset.programar));
            });
        });

        dom.listaSolicitudes.querySelectorAll('[data-cancelar]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                const solicitudId = Number(boton.dataset.cancelar);
                const solicitud = estado.solicitudes.find(function (item) {
                    return Number(item.id) === solicitudId;
                });

                if (solicitud) {
                    cancelarMantenimiento(solicitud, boton);
                }
            });
        });
    }

    function tarjetaSolicitud(solicitud) {
        const atrasada = solicitud.grupo === 'ATRASADO_SIN_INICIAR';
        const enEjecucion = solicitud.grupo === 'EN_EJECUCION';
        const sinProgramar = solicitud.grupo === 'POR_PROGRAMAR';
        let textoBoton = 'Ver y ajustar';

        if (sinProgramar) {
            textoBoton = 'Programar y asignar';
        } else if (atrasada) {
            textoBoton = 'Reprogramar o reasignar';
        } else if (enEjecucion) {
            textoBoton = 'Revisar equipo';
        }

        const fecha = solicitud.fecha_programada
            ? formatearFecha(solicitud.fecha_programada)
            : 'Sin fecha programada';
        const tecnicoTexto = solicitud.total_tecnicos > 0
            ? escapeHtml(solicitud.tecnicos || solicitud.total_tecnicos + ' técnico(s)')
            : 'Sin técnicos asignados';

        return '' +
            '<article class="sprog-request ' + claseGrupo(solicitud.grupo) + '">' +
                '<div class="sprog-request__main">' +
                    '<div class="sprog-request__top">' +
                        '<span class="sprog-folio">' + escapeHtml(solicitud.folio) + '</span>' +
                        badge(legibleTipo(solicitud.tipo_solicitud), 'type') +
                        badge(legiblePrioridad(solicitud.prioridad), 'priority-' + String(solicitud.prioridad).toLowerCase()) +
                        badge(legibleGrupo(solicitud.grupo), 'group-' + String(solicitud.grupo).toLowerCase()) +
                    '</div>' +
                    '<h3>' + escapeHtml(solicitud.nombre_equipo || 'Equipo sin nombre') + '</h3>' +
                    '<p class="sprog-location">' +
                        escapeHtml([solicitud.departamento, solicitud.area, solicitud.proceso].filter(Boolean).join(' · ')) +
                    '</p>' +
                    '<p class="sprog-description">' + escapeHtml(solicitud.descripcion_solicitud || '') + '</p>' +
                    '<div class="sprog-request__flags">' +
                        (Number(solicitud.trabajo_peligroso) === 1 ? '<span class="is-danger">Trabajo peligroso</span>' : '') +
                        (Number(solicitud.requiere_paro_equipo) === 1 ? '<span>Requiere paro</span>' : '') +
                        (atrasada ? '<span class="is-late">' + Number(solicitud.dias_atraso) + ' día(s) de atraso</span>' : '') +
                        (Number(solicitud.total_iniciados) > 0 ? '<span class="is-started">' + Number(solicitud.total_iniciados) + ' técnico(s) iniciaron</span>' : '') +
                    '</div>' +
                '</div>' +
                '<aside class="sprog-request__schedule">' +
                    '<span>Programación</span>' +
                    '<strong>' + escapeHtml(fecha) + '</strong>' +
                    '<small>' + tecnicoTexto + '</small>' +
                    '<div class="sprog-request__buttons">' +
                        '<button type="button" class="sprog-btn sprog-btn--primary" data-programar="' + Number(solicitud.id) + '">' +
                            escapeHtml(textoBoton) +
                        '</button>' +
                        (Number(solicitud.puede_cancelar) === 1
                            ? '<button type="button" class="sprog-btn sprog-btn--danger sprog-btn--compact" data-cancelar="' +
                                Number(solicitud.id) + '">Cancelar mantenimiento</button>'
                            : '') +
                    '</div>' +
                '</aside>' +
            '</article>';
    }

    async function abrirProgramacion(solicitudId) {
        limpiarModal();
        dom.modal.hidden = false;
        dom.modal.setAttribute('aria-busy', 'true');
        dom.tituloModal.textContent = 'Cargando mantenimiento...';
        dom.resumenSolicitudModal.innerHTML = '<div class="sprog-loading">Consultando programación y carga del personal...</div>';

        try {
            const respuesta = await UI.peticionJson(
                endpoint + '?' + new URLSearchParams({ accion: 'detalle', id: String(solicitudId) }).toString()
            );

            estado.solicitud = respuesta.solicitud;
            estado.asignaciones = respuesta.asignaciones || [];
            estado.tecnicos = respuesta.tecnicos || [];
            estado.seleccionados = new Set(
                estado.asignaciones.map(function (item) { return Number(item.tecnico_id); })
            );
            estado.obligatorios = new Set(
                estado.asignaciones
                    .filter(function (item) { return Number(item.iniciado) === 1; })
                    .map(function (item) { return Number(item.tecnico_id); })
            );
            estado.tecnicosOriginales = Array.from(estado.seleccionados).sort(function (a, b) { return a - b; });
            estado.fechaOriginal = respuesta.solicitud.fecha_programada || '';
            estado.recursosContexto = respuesta.recursos_contexto || {};
            estado.puedeEditarRecursos = Boolean(respuesta.puede_editar_recursos);
            establecerRecursosSeleccionados(respuesta.recursos_recomendados || []);
            estado.recursosOriginales = estado.recursosContexto.fotografia_guardada
                ? idsRecursosSeleccionados()
                : [];
            configurarEdicionRecursos();
            pintarContextoRecursos();
            pintarRecursosSeleccionados('HERRAMIENTA');
            pintarRecursosSeleccionados('REFACCION');

            dom.solicitudId.value = String(estado.solicitud.id);
            dom.btnCancelarMantenimiento.hidden = !puedeCancelarSolicitud(estado.solicitud);
            const fechaSugerida = estado.fechaOriginal ||
                (estado.fechaPreferida && estado.fechaPreferida >= fechaHoy()
                    ? estado.fechaPreferida
                    : respuesta.fecha_sugerida_programacion);
            dom.fechaProgramada.value = fechaSugerida;
            dom.fechaProgramada.min = fechaHoy();
            dom.fechaProgramada.disabled = !respuesta.puede_cambiar_fecha;
            dom.avisoFechaBloqueada.hidden = respuesta.puede_cambiar_fecha;

            const alertasNocturnasExistentes = estado.asignaciones.filter(function (item) {
                return Number(item.alerta_riesgo_nocturno) === 1;
            });
            dom.confirmarRiesgoNocturno.checked = alertasNocturnasExistentes.length > 0 &&
                alertasNocturnasExistentes.every(function (item) {
                    return Number(item.riesgo_nocturno_confirmado) === 1;
                });
            const asignacionConObservacion = alertasNocturnasExistentes.find(function (item) {
                return String(item.observacion_riesgo_nocturno || '').trim() !== '';
            });
            dom.observacionRiesgoNocturno.value = asignacionConObservacion
                ? String(asignacionConObservacion.observacion_riesgo_nocturno || '')
                : '';
            dom.detallePeligroNocturno.textContent = detallePeligroSolicitud();

            dom.etiquetaModal.textContent = estado.fechaOriginal
                ? (Number(estado.solicitud.dias_atraso) > 0 ? 'REPROGRAMACIÓN ATRASADA' : 'PROGRAMACIÓN EXISTENTE')
                : 'NUEVA PROGRAMACIÓN';
            dom.tituloModal.textContent = estado.fechaOriginal
                ? 'Ajustar programación y técnicos'
                : 'Programar y asignar mantenimiento';
            dom.subtituloModal.textContent = estado.fechaOriginal
                ? 'Puedes moverlo mientras nadie haya iniciado; los técnicos iniciados quedan protegidos.'
                : 'Selecciona un día y distribuye el trabajo usando la carga real como referencia.';

            dom.textoMotivo.textContent = estado.fechaOriginal
                ? 'Al cambiar la fecha, el equipo técnico o los recursos recomendados, el motivo será obligatorio y quedará en auditoría.'
                : 'En una programación nueva el motivo es opcional, pero ayuda a explicar la planeación.';

            pintarResumenModal();
            pintarEstadoCalendario(respuesta.calendario);
            llenarFiltrosDesdeTecnicos();
            pintarTecnicos();
            actualizarRiesgoNocturno();
            actualizarResumenGuardar();

            if (!estado.fechaOriginal && fechaSugerida !== respuesta.fecha_sugerida_programacion) {
                await recargarTecnicosPorFecha();
            }

            dom.modal.removeAttribute('aria-busy');
        } catch (error) {
            console.error(error);
            dom.modal.removeAttribute('aria-busy');
            cerrarModal();
            UI.error('No fue posible abrir la programación', error.message || 'Actualiza la página e inténtalo nuevamente.');
        }
    }

    function pintarResumenModal() {
        const s = estado.solicitud;
        if (!s) {
            return;
        }

        dom.resumenSolicitudModal.innerHTML = '' +
            '<div class="sprog-summary__identity">' +
                '<span class="sprog-folio">' + escapeHtml(s.folio) + '</span>' +
                '<div>' +
                    '<h3>' + escapeHtml(s.nombre_equipo || 'Equipo sin nombre') + '</h3>' +
                    '<p>' + escapeHtml([s.codigo_equipo || 'Sin código', s.departamento, s.area, s.proceso].filter(Boolean).join(' · ')) + '</p>' +
                '</div>' +
            '</div>' +
            '<div class="sprog-summary__badges">' +
                badge(legibleTipo(s.tipo_solicitud), 'type') +
                badge(legiblePrioridad(s.prioridad), 'priority-' + String(s.prioridad).toLowerCase()) +
                badge(legibleEstado(s.estado), 'status') +
            '</div>' +
            '<div class="sprog-summary__copy">' +
                '<article><span>Solicitante</span><strong>' + escapeHtml(s.solicitante || 'Sin solicitante') + '</strong></article>' +
                '<article><span>Riesgo</span><strong>' + escapeHtml(legibleRiesgo(s.nivel_riesgo)) + '</strong></article>' +
                '<article><span>Trabajo solicitado</span><p>' + escapeHtml(s.descripcion_solicitud || '') + '</p></article>' +
            '</div>';
    }

    async function recargarTecnicosPorFecha() {
        if (!estado.solicitud || !dom.fechaProgramada.value) {
            return;
        }

        dom.listaTecnicos.classList.add('is-loading');
        dom.estadoCalendario.textContent = 'Consultando carga y calendario...';

        try {
            const parametros = new URLSearchParams({
                accion: 'tecnicos',
                solicitud_id: String(estado.solicitud.id),
                fecha: dom.fechaProgramada.value
            });
            const respuesta = await UI.peticionJson(endpoint + '?' + parametros.toString());
            estado.tecnicos = respuesta.tecnicos || [];
            pintarEstadoCalendario(respuesta.calendario);
            llenarFiltrosDesdeTecnicos();
            pintarTecnicos();
            actualizarRiesgoNocturno();
            actualizarResumenGuardar();
        } catch (error) {
            console.error(error);
            UI.error('No fue posible consultar la fecha', error.message || 'Selecciona otra fecha.');
        } finally {
            dom.listaTecnicos.classList.remove('is-loading');
        }
    }

    function pintarEstadoCalendario(calendario) {
        if (!calendario) {
            dom.estadoCalendario.className = 'sprog-calendar-state is-warning';
            dom.estadoCalendario.textContent = 'No fue posible consultar el calendario.';
            return;
        }

        if (!calendario.permitido) {
            dom.estadoCalendario.className = 'sprog-calendar-state is-blocked';
            dom.estadoCalendario.innerHTML = '<strong>Día inhábil</strong><span>' + escapeHtml(calendario.mensaje || '') + '</span>';
            dom.ayudaFecha.textContent = 'Selecciona una fecha permitida.';
            return;
        }

        if (!calendario.configurado) {
            dom.estadoCalendario.className = 'sprog-calendar-state is-warning';
            dom.estadoCalendario.innerHTML = '<strong>Día sin registro</strong><span>' + escapeHtml(calendario.mensaje || '') + '</span>';
            dom.ayudaFecha.textContent = 'Se permite programar sin preparar previamente el mes.';
            return;
        }

        dom.estadoCalendario.className = calendario.tipo_dia === 'HABIL_EXTRA'
            ? 'sprog-calendar-state is-extra'
            : 'sprog-calendar-state is-available';
        dom.estadoCalendario.innerHTML = '<strong>' +
            (calendario.tipo_dia === 'HABIL_EXTRA' ? 'Día hábil extra' : 'Día hábil') +
            '</strong><span>' + escapeHtml(calendario.mensaje || '') + '</span>';
        dom.ayudaFecha.textContent = 'La actividad deberá completarse este mismo día para contar como a tiempo.';
    }

    function pintarTecnicos() {
        const busqueda = normalizar(dom.buscarTecnico.value);
        const turno = dom.filtroTurnoTecnico.value;
        const especialidad = dom.filtroEspecialidadTecnico.value;
        const departamento = dom.filtroDepartamentoTecnico.value;
        const orden = dom.ordenTecnicos.value;

        let tecnicos = estado.tecnicos.filter(function (tecnico) {
            if (turno && tecnico.turno !== turno) {
                return false;
            }
            if (especialidad && tecnico.especialidad !== especialidad) {
                return false;
            }
            if (departamento && String(tecnico.departamento_id || '') !== departamento) {
                return false;
            }
            if (busqueda) {
                const contenido = normalizar([tecnico.tecnico, tecnico.especialidad, tecnico.departamento].join(' '));
                if (contenido.indexOf(busqueda) === -1) {
                    return false;
                }
            }
            return true;
        });

        tecnicos = tecnicos.slice().sort(function (a, b) {
            if (orden === 'CARGA_SEMANA') {
                return compararNumero(a.carga_semana, b.carga_semana) || compararNumero(a.carga_activa, b.carga_activa) || compararNombre(a, b);
            }
            if (orden === 'CARGA_DIA') {
                return compararNumero(a.carga_dia, b.carga_dia) || compararNumero(a.carga_activa, b.carga_activa) || compararNombre(a, b);
            }
            if (orden === 'ATRASADOS') {
                return compararNumero(a.atrasados_sin_iniciar, b.atrasados_sin_iniciar) || compararNumero(a.carga_activa, b.carga_activa) || compararNombre(a, b);
            }
            if (orden === 'NOMBRE') {
                return compararNombre(a, b);
            }
            return compararNumero(a.carga_activa, b.carga_activa) || compararNumero(a.carga_semana, b.carga_semana) || compararNombre(a, b);
        });

        dom.vacioTecnicos.hidden = tecnicos.length !== 0;
        dom.listaTecnicos.hidden = tecnicos.length === 0;
        dom.listaTecnicos.innerHTML = tecnicos.map(tarjetaTecnico).join('');

        dom.listaTecnicos.querySelectorAll('[data-tecnico]').forEach(function (input) {
            input.addEventListener('change', function () {
                cambiarSeleccionTecnico(Number(input.dataset.tecnico), input.checked, input);
            });
        });

        dom.contadorSeleccionados.textContent =
            estado.seleccionados.size + ' de 5 seleccionados';
    }

    function tarjetaTecnico(tecnico) {
        const id = Number(tecnico.id);
        const seleccionado = estado.seleccionados.has(id);
        const obligatorio = estado.obligatorios.has(id);
        const nivel = String(tecnico.carga_nivel || 'BAJA').toLowerCase();
        const actual = Number(tecnico.seleccionado) === 1;

        return '' +
            '<label class="sprog-tech ' + (seleccionado ? 'is-selected ' : '') +
                (obligatorio ? 'is-locked ' : '') + 'is-load-' + nivel + '">' +
                '<input type="checkbox" data-tecnico="' + id + '" ' +
                    (seleccionado ? 'checked ' : '') + (obligatorio ? 'disabled ' : '') + '>' +
                '<span class="sprog-tech__check" aria-hidden="true">✓</span>' +
                '<span class="sprog-tech__avatar">' + iniciales(tecnico.tecnico) + '</span>' +
                '<span class="sprog-tech__identity">' +
                    '<strong>' + escapeHtml(tecnico.tecnico) + '</strong>' +
                    '<small>' + escapeHtml(legibleTurno(tecnico.turno)) + ' · ' + escapeHtml(tecnico.especialidad) + '</small>' +
                    '<em>' + escapeHtml(tecnico.departamento) + '</em>' +
                '</span>' +
                '<span class="sprog-tech__load">' +
                    '<b>' + Number(tecnico.carga_activa) + '</b>' +
                    '<small>activos</small>' +
                '</span>' +
                '<span class="sprog-tech__metrics">' +
                    '<span><b>' + Number(tecnico.carga_dia) + '</b> ese día</span>' +
                    '<span><b>' + Number(tecnico.carga_semana) + '</b> esa semana</span>' +
                    '<span class="' + (Number(tecnico.atrasados_sin_iniciar) > 0 ? 'is-warning' : '') + '"><b>' + Number(tecnico.atrasados_sin_iniciar) + '</b> atrasados</span>' +
                '</span>' +
                '<span class="sprog-tech__status">' +
                    (obligatorio
                        ? '<b>Ya inició · no se puede retirar</b>'
                        : (actual ? '<b>Asignado actualmente</b>' : '<b>Disponible para seleccionar</b>')) +
                    (Number(tecnico.en_proceso) > 0 ? '<small>Tiene una actividad en proceso ahora</small>' : '<small>Sin ejecución activa ahora</small>') +
                '</span>' +
            '</label>';
    }

    function cambiarSeleccionTecnico(id, seleccionado, input) {
        if (seleccionado) {
            if (estado.seleccionados.size >= 5 && !estado.seleccionados.has(id)) {
                input.checked = false;
                UI.advertencia('Máximo 5 técnicos', 'Retira a otro técnico antes de agregar uno nuevo.');
                return;
            }
            estado.seleccionados.add(id);
        } else {
            if (estado.obligatorios.has(id)) {
                input.checked = true;
                UI.advertencia('Técnico protegido', 'No puedes retirar a un técnico que ya inició el mantenimiento.');
                return;
            }
            estado.seleccionados.delete(id);
        }

        // Cualquier cambio de integrantes exige volver a revisar la advertencia
        // cuando el trabajo es peligroso y participa personal nocturno.
        dom.confirmarRiesgoNocturno.checked = false;
        dom.observacionRiesgoNocturno.value = '';

        pintarTecnicos();
        actualizarRiesgoNocturno();
        actualizarResumenGuardar();
    }

    function actualizarRiesgoNocturno() {
        if (!estado.solicitud) {
            dom.avisoRiesgoNocturno.hidden = true;
            return;
        }

        const hayNocturno = estado.tecnicos.some(function (tecnico) {
            return estado.seleccionados.has(Number(tecnico.id)) && tecnico.turno === 'NOCTURNO';
        });
        const mostrar = Number(estado.solicitud.trabajo_peligroso) === 1 && hayNocturno;
        dom.avisoRiesgoNocturno.hidden = !mostrar;
        dom.detallePeligroNocturno.textContent = detallePeligroSolicitud();
        dom.confirmarRiesgoNocturno.required = mostrar;

        if (!mostrar) {
            dom.confirmarRiesgoNocturno.checked = false;
            dom.observacionRiesgoNocturno.value = '';
        }
    }

    function actualizarResumenGuardar() {
        if (!estado.solicitud) {
            return;
        }

        const fecha = dom.fechaProgramada.value
            ? formatearFecha(dom.fechaProgramada.value)
            : 'sin fecha';
        const totalHerramientas = estado.recursosSeleccionados.HERRAMIENTA.length;
        const totalRefacciones = estado.recursosSeleccionados.REFACCION.length;
        dom.resumenGuardar.textContent =
            escapeSinHtml(estado.solicitud.folio) + ' · ' + fecha + ' · ' +
            estado.seleccionados.size + (estado.seleccionados.size === 1 ? ' técnico' : ' técnicos') +
            ' · ' + totalHerramientas + ' herramienta(s) · ' + totalRefacciones + ' refacción(es)';

        const seleccionActual = Array.from(estado.seleccionados).sort(function (a, b) { return a - b; });
        const cambio = estado.fechaOriginal !== dom.fechaProgramada.value ||
            JSON.stringify(estado.tecnicosOriginales) !== JSON.stringify(seleccionActual) ||
            recursosCambiaron();
        dom.motivoProgramacion.required = Boolean(estado.fechaOriginal && cambio);
        dom.motivoProgramacion.minLength = dom.motivoProgramacion.required ? 10 : 0;
    }


    function registrarBuscadorRecurso(tipo, input, contenedor) {
        input.addEventListener('focus', function () {
            if (!estado.puedeEditarRecursos) {
                return;
            }
            buscarRecursos(tipo, input.value, contenedor);
        });

        input.addEventListener('input', function () {
            if (!estado.puedeEditarRecursos) {
                return;
            }

            window.clearTimeout(estado.temporizadoresRecursos[tipo]);
            estado.temporizadoresRecursos[tipo] = window.setTimeout(function () {
                buscarRecursos(tipo, input.value, contenedor);
            }, 220);
        });

        input.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                contenedor.hidden = true;
            }
        });

        contenedor.addEventListener('click', function (evento) {
            const boton = evento.target.closest('[data-recurso-id]');
            if (!boton || !estado.puedeEditarRecursos) {
                return;
            }

            agregarRecursoSeleccionado(tipo, {
                id: Number(boton.dataset.recursoId),
                tipo_recurso: tipo,
                nombre: boton.dataset.recursoNombre || '',
                codigo: boton.dataset.recursoCodigo || '',
                descripcion: boton.dataset.recursoDescripcion || '',
                activo: 1,
                origen: 'ADMIN'
            });

            input.value = '';
            contenedor.hidden = true;
            input.focus();
        });
    }

    async function buscarRecursos(tipo, termino, contenedor) {
        if (!estado.puedeEditarRecursos) {
            return;
        }

        const secuencia = Number(estado.secuenciaBusquedaRecursos[tipo] || 0) + 1;
        estado.secuenciaBusquedaRecursos[tipo] = secuencia;

        contenedor.hidden = false;
        contenedor.innerHTML =
            '<div class="sprog-resource-results__status">Buscando...</div>';

        try {
            const parametros = new URLSearchParams({
                accion: 'BUSCAR_ACTIVOS',
                tipo_recurso: tipo,
                q: String(termino || '').trim(),
                limite: '30'
            });

            const respuesta = await UI.peticionJson(
                endpointRecursos + '?' + parametros.toString()
            );

            if (secuencia !== estado.secuenciaBusquedaRecursos[tipo]) {
                return;
            }

            pintarResultadosRecursos(
                tipo,
                Array.isArray(respuesta.recursos) ? respuesta.recursos : [],
                contenedor
            );
        } catch (error) {
            if (secuencia !== estado.secuenciaBusquedaRecursos[tipo]) {
                return;
            }

            console.error(error);
            contenedor.innerHTML =
                '<div class="sprog-resource-results__status sprog-resource-results__status--error">' +
                escapeHtml(error.message || 'No fue posible buscar en el catálogo.') +
                '</div>';
        }
    }

    function pintarResultadosRecursos(tipo, recursos, contenedor) {
        const seleccionados = new Set(
            estado.recursosSeleccionados[tipo].map(function (recurso) {
                return Number(recurso.id);
            })
        );

        const disponibles = recursos.filter(function (recurso) {
            return Number(recurso.id) > 0 && !seleccionados.has(Number(recurso.id));
        });

        if (!disponibles.length) {
            contenedor.innerHTML =
                '<div class="sprog-resource-results__status">' +
                (recursos.length
                    ? 'Todos los resultados ya están seleccionados.'
                    : 'No se encontraron resultados.') +
                '</div>';
            return;
        }

        contenedor.innerHTML = disponibles.map(function (recurso) {
            return '' +
                '<button type="button" class="sprog-resource-option"' +
                    ' data-recurso-id="' + Number(recurso.id) + '"' +
                    ' data-recurso-nombre="' + escapeHtmlAtributo(recurso.nombre || '') + '"' +
                    ' data-recurso-codigo="' + escapeHtmlAtributo(recurso.codigo || '') + '"' +
                    ' data-recurso-descripcion="' + escapeHtmlAtributo(recurso.descripcion || '') + '">' +
                    '<span>' +
                        '<strong>' + escapeHtml(recurso.nombre || '') + '</strong>' +
                        '<small>' + escapeHtml(recurso.codigo || 'Sin código') + '</small>' +
                    '</span>' +
                    '<em>Agregar</em>' +
                '</button>';
        }).join('');
    }

    function agregarRecursoSeleccionado(tipo, recurso) {
        if (!estado.puedeEditarRecursos || Number(recurso.id) < 1) {
            return;
        }

        const existe = estado.recursosSeleccionados[tipo].some(function (item) {
            return Number(item.id) === Number(recurso.id);
        });

        if (!existe) {
            estado.recursosSeleccionados[tipo].push(normalizarRecurso(recurso));
            estado.recursosSeleccionados[tipo].sort(function (a, b) {
                return String(a.nombre || '').localeCompare(String(b.nombre || ''), 'es');
            });
        }

        pintarRecursosSeleccionados(tipo);
        actualizarResumenGuardar();
    }

    function establecerRecursosSeleccionados(recursos) {
        estado.recursosSeleccionados.HERRAMIENTA = [];
        estado.recursosSeleccionados.REFACCION = [];

        (Array.isArray(recursos) ? recursos : []).forEach(function (recurso) {
            const tipo = String(recurso.tipo_recurso || '');
            const id = Number(recurso.id || 0);

            if ((tipo !== 'HERRAMIENTA' && tipo !== 'REFACCION') || id < 1) {
                return;
            }

            if (!estado.recursosSeleccionados[tipo].some(function (item) {
                return Number(item.id) === id;
            })) {
                estado.recursosSeleccionados[tipo].push(normalizarRecurso(recurso));
            }
        });

        ['HERRAMIENTA', 'REFACCION'].forEach(function (tipo) {
            estado.recursosSeleccionados[tipo].sort(function (a, b) {
                return String(a.nombre || '').localeCompare(String(b.nombre || ''), 'es');
            });
        });
    }

    function normalizarRecurso(recurso) {
        return {
            id: Number(recurso.id || 0),
            tipo_recurso: String(recurso.tipo_recurso || ''),
            nombre: String(recurso.nombre || ''),
            codigo: String(recurso.codigo || ''),
            descripcion: String(recurso.descripcion || ''),
            activo: Number(recurso.activo === undefined ? 1 : recurso.activo),
            origen: String(recurso.origen || 'ADMIN')
        };
    }

    function pintarRecursosSeleccionados(tipo) {
        const esHerramienta = tipo === 'HERRAMIENTA';
        const contenedor = esHerramienta
            ? dom.herramientasSeleccionadas
            : dom.refaccionesSeleccionadas;
        const contador = esHerramienta
            ? dom.contadorHerramientas
            : dom.contadorRefacciones;
        const recursos = estado.recursosSeleccionados[tipo];

        contador.textContent = recursos.length === 1
            ? '1 seleccionada'
            : recursos.length + ' seleccionadas';

        if (!recursos.length) {
            contenedor.innerHTML =
                '<div class="sprog-resource-empty">No hay ' +
                (esHerramienta ? 'herramientas' : 'refacciones') +
                ' seleccionadas.</div>';
            return;
        }

        contenedor.innerHTML = recursos.map(function (recurso) {
            const inactivo = Number(recurso.activo) !== 1;

            return '' +
                '<article class="sprog-resource-chip ' + (inactivo ? 'is-inactive' : '') + '">' +
                    '<span>' +
                        '<strong>' + escapeHtml(recurso.nombre) + '</strong>' +
                        '<small>' +
                            escapeHtml(recurso.codigo || 'Sin código') +
                            (inactivo ? ' · Desactivado' : '') +
                        '</small>' +
                    '</span>' +
                    (estado.puedeEditarRecursos
                        ? '<button type="button" data-retirar-recurso="' + Number(recurso.id) +
                            '" data-tipo-recurso="' + tipo + '" aria-label="Retirar ' +
                            escapeHtmlAtributo(recurso.nombre) + '">×</button>'
                        : '') +
                '</article>';
        }).join('');

        contenedor.querySelectorAll('[data-retirar-recurso]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                retirarRecurso(
                    boton.dataset.tipoRecurso || '',
                    Number(boton.dataset.retirarRecurso)
                );
            });
        });
    }

    function retirarRecurso(tipo, id) {
        if (!estado.puedeEditarRecursos ||
            (tipo !== 'HERRAMIENTA' && tipo !== 'REFACCION')) {
            return;
        }

        estado.recursosSeleccionados[tipo] =
            estado.recursosSeleccionados[tipo].filter(function (recurso) {
                return Number(recurso.id) !== Number(id);
            });

        pintarRecursosSeleccionados(tipo);
        actualizarResumenGuardar();
    }

    function idsRecursosSeleccionados() {
        return estado.recursosSeleccionados.HERRAMIENTA
            .concat(estado.recursosSeleccionados.REFACCION)
            .map(function (recurso) { return Number(recurso.id); })
            .filter(function (id) { return id > 0; })
            .sort(function (a, b) { return a - b; });
    }

    function recursosCambiaron() {
        return JSON.stringify(estado.recursosOriginales) !==
            JSON.stringify(idsRecursosSeleccionados());
    }

    function configurarEdicionRecursos() {
        dom.buscarHerramienta.disabled = !estado.puedeEditarRecursos;
        dom.buscarRefaccion.disabled = !estado.puedeEditarRecursos;
        dom.avisoRecursosBloqueados.hidden = estado.puedeEditarRecursos;

        if (!estado.puedeEditarRecursos) {
            dom.resultadosHerramientas.hidden = true;
            dom.resultadosRefacciones.hidden = true;
        }
    }

    function pintarContextoRecursos() {
        const contexto = estado.recursosContexto || {};
        dom.fuenteRecursos.textContent = contexto.titulo || 'Sin recomendaciones';
        dom.descripcionFuenteRecursos.textContent =
            contexto.descripcion || 'Selecciona los recursos recomendados para este mantenimiento.';

        dom.notaRecursos.textContent = contexto.es_rutinario
            ? 'Los cambios solo afectarán esta ejecución. La plantilla de la rutina no será modificada.'
            : 'Al guardar, esta selección quedará como recomendación para el mismo equipo y tipo de mantenimiento.';
    }

    function detallePeligroSolicitud() {
        if (!estado.solicitud) {
            return 'Trabajo peligroso sin una nota específica.';
        }

        const detalle = String(estado.solicitud.detalle_trabajo_peligroso || '').trim();
        if (detalle) {
            return 'Peligro informado: ' + detalle;
        }

        return 'Trabajo peligroso. Nivel de riesgo: ' +
            legibleRiesgo(estado.solicitud.nivel_riesgo || 'BAJO') + '.';
    }

    function escapeHtmlAtributo(valor) {
        return escapeHtml(valor).replace(/`/g, '&#096;');
    }

    function puedeCancelarSolicitud(solicitud) {
        if (!solicitud) {
            return false;
        }

        return Number(solicitud.total_iniciados || 0) === 0 &&
            ['APROBADO', 'AGENDADO', 'ATRASADO'].indexOf(String(solicitud.estado || '')) !== -1;
    }

    async function cancelarMantenimiento(solicitud, botonOrigen) {
        if (!puedeCancelarSolicitud(solicitud)) {
            UI.advertencia(
                'No se puede cancelar',
                'El mantenimiento ya inició o su estado ya no permite cancelarlo desde esta interfaz.'
            );
            return;
        }

        if (estado.cancelandoId > 0) {
            return;
        }

        const resultado = await Swal.fire({
            icon: 'warning',
            title: '¿Cancelar el mantenimiento?',
            html:
                '<p class="sprog-cancel-copy">Se cancelará <strong>' +
                escapeHtml(solicitud.folio || '') +
                '</strong>, se retirará de los técnicos y ya no aparecerá como trabajo pendiente.</p>',
            input: 'textarea',
            inputLabel: 'Motivo de cancelación',
            inputPlaceholder: 'Explica claramente por qué se cancela este mantenimiento.',
            inputAttributes: {
                maxlength: '500',
                minlength: '10',
                rows: '4'
            },
            inputValidator: function (valor) {
                const motivo = String(valor || '').trim();
                if (motivo.length < 10) {
                    return 'Escribe un motivo de al menos 10 caracteres.';
                }
                if (motivo.length > 500) {
                    return 'El motivo no puede superar 500 caracteres.';
                }
                return undefined;
            },
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar mantenimiento',
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

        const motivo = String(resultado.value || '').trim();
        const datos = new FormData();
        datos.set('accion', 'cancelar_mantenimiento');
        datos.set('solicitud_id', String(solicitud.id));
        datos.set('motivo_cancelacion', motivo);

        estado.cancelandoId = Number(solicitud.id);
        if (botonOrigen) {
            UI.estadoBoton(botonOrigen, true, 'Cancelando...');
        }
        if (!dom.btnCancelarMantenimiento.hidden &&
            estado.solicitud &&
            Number(estado.solicitud.id) === Number(solicitud.id)) {
            UI.estadoBoton(dom.btnCancelarMantenimiento, true, 'Cancelando...');
        }

        try {
            const respuesta = await UI.peticionJson(endpoint, {
                method: 'POST',
                body: datos
            });

            await UI.exito(
                'Mantenimiento cancelado',
                respuesta.mensaje || 'El mantenimiento se canceló correctamente.'
            );

            if (estado.solicitud &&
                Number(estado.solicitud.id) === Number(solicitud.id)) {
                cerrarModal();
            }

            await cargarInicial(estado.semanaInicio);
        } catch (error) {
            console.error(error);
            UI.error(
                'No fue posible cancelar',
                error.message || 'Actualiza la información e inténtalo nuevamente.'
            );
        } finally {
            estado.cancelandoId = 0;
            if (botonOrigen && document.body.contains(botonOrigen)) {
                UI.estadoBoton(botonOrigen, false);
            }
            UI.estadoBoton(dom.btnCancelarMantenimiento, false);
        }
    }

    async function guardarProgramacion(evento) {
        evento.preventDefault();
        const formulario = dom.form;

        if (!estado.solicitud) {
            return;
        }

        if (!formulario.checkValidity()) {
            UI.validarFormulario(formulario);
            return;
        }

        if (estado.seleccionados.size < 1 || estado.seleccionados.size > 5) {
            UI.advertencia('Selecciona técnicos', 'Debes elegir entre 1 y 5 técnicos.');
            return;
        }

        const seleccionActual = Array.from(estado.seleccionados).sort(function (a, b) { return a - b; });
        const cambioFecha = estado.fechaOriginal !== dom.fechaProgramada.value;
        const cambioEquipo = JSON.stringify(estado.tecnicosOriginales) !== JSON.stringify(seleccionActual);
        const cambioRecursos = recursosCambiaron();

        if (estado.fechaOriginal && (cambioFecha || cambioEquipo || cambioRecursos) && dom.motivoProgramacion.value.trim().length < 10) {
            dom.motivoProgramacion.focus();
            UI.advertencia('Motivo requerido', 'Escribe al menos 10 caracteres para explicar el cambio.');
            return;
        }

        const confirmar = await UI.confirmar({
            titulo: estado.fechaOriginal ? '¿Guardar cambios de programación?' : '¿Programar este mantenimiento?',
            texto: 'Fecha: ' + formatearFecha(dom.fechaProgramada.value) +
                '. Técnicos: ' + estado.seleccionados.size +
                '. Herramientas: ' + estado.recursosSeleccionados.HERRAMIENTA.length +
                '. Refacciones: ' + estado.recursosSeleccionados.REFACCION.length + '.',
            textoConfirmar: estado.fechaOriginal ? 'Guardar cambios' : 'Programar y asignar',
            icono: 'question'
        });

        if (!confirmar) {
            return;
        }

        const datos = new FormData(formulario);
        // El campo fecha puede estar deshabilitado cuando alguien ya inició.
        // FormData omite los controles deshabilitados, por eso se agrega de forma explícita.
        datos.set('fecha_programada', dom.fechaProgramada.value);
        datos.delete('tecnicos[]');
        seleccionActual.forEach(function (id) {
            datos.append('tecnicos[]', String(id));
        });

        datos.delete('herramientas_ids[]');
        datos.delete('refacciones_ids[]');
        estado.recursosSeleccionados.HERRAMIENTA.forEach(function (recurso) {
            datos.append('herramientas_ids[]', String(recurso.id));
        });
        estado.recursosSeleccionados.REFACCION.forEach(function (recurso) {
            datos.append('refacciones_ids[]', String(recurso.id));
        });

        UI.estadoBoton(dom.btnGuardar, true, 'Guardando...');

        try {
            const respuesta = await UI.peticionJson(endpoint, {
                method: 'POST',
                body: datos
            });
            await UI.exito('Programación guardada', respuesta.mensaje || 'Los cambios se guardaron correctamente.');
            cerrarModal();
            await cargarInicial(estado.semanaInicio);
        } catch (error) {
            console.error(error);
            UI.error('No fue posible guardar', error.message || 'Revisa la información e inténtalo nuevamente.');
        } finally {
            UI.estadoBoton(dom.btnGuardar, false);
        }
    }

    function llenarCatalogosTecnicos() {
        llenarSelect(
            dom.filtroEspecialidadTecnico,
            estado.catalogos.especialidades || [],
            'Todas'
        );

        const departamentos = (estado.catalogos.departamentos || []).map(function (item) {
            return { valor: String(item.id), texto: item.nombre };
        });
        llenarSelectObjetos(dom.filtroDepartamentoTecnico, departamentos, 'Todos');
    }

    function llenarFiltrosDesdeTecnicos() {
        const especialidades = Array.from(new Set(
            estado.tecnicos.map(function (item) { return item.especialidad; }).filter(Boolean)
        )).sort(function (a, b) { return a.localeCompare(b, 'es'); });
        const valorEspecialidad = dom.filtroEspecialidadTecnico.value;
        llenarSelect(dom.filtroEspecialidadTecnico, especialidades, 'Todas');
        if (especialidades.indexOf(valorEspecialidad) !== -1) {
            dom.filtroEspecialidadTecnico.value = valorEspecialidad;
        }

        const mapa = new Map();
        estado.tecnicos.forEach(function (item) {
            if (item.departamento_id) {
                mapa.set(String(item.departamento_id), item.departamento);
            }
        });
        const departamentos = Array.from(mapa.entries())
            .map(function (par) { return { valor: par[0], texto: par[1] }; })
            .sort(function (a, b) { return a.texto.localeCompare(b.texto, 'es'); });
        const valorDepartamento = dom.filtroDepartamentoTecnico.value;
        llenarSelectObjetos(dom.filtroDepartamentoTecnico, departamentos, 'Todos');
        if (mapa.has(valorDepartamento)) {
            dom.filtroDepartamentoTecnico.value = valorDepartamento;
        }
    }

    function limpiarModal() {
        estado.solicitud = null;
        estado.asignaciones = [];
        estado.tecnicos = [];
        estado.seleccionados = new Set();
        estado.obligatorios = new Set();
        estado.fechaOriginal = '';
        estado.tecnicosOriginales = [];
        estado.recursosSeleccionados = {
            HERRAMIENTA: [],
            REFACCION: []
        };
        estado.recursosOriginales = [];
        estado.recursosContexto = {};
        estado.puedeEditarRecursos = true;
        estado.secuenciaBusquedaRecursos.HERRAMIENTA += 1;
        estado.secuenciaBusquedaRecursos.REFACCION += 1;
        dom.form.reset();
        dom.btnCancelarMantenimiento.hidden = true;
        UI.estadoBoton(dom.btnCancelarMantenimiento, false);
        dom.fechaProgramada.disabled = false;
        dom.avisoFechaBloqueada.hidden = true;
        dom.avisoRiesgoNocturno.hidden = true;
        dom.avisoRecursosBloqueados.hidden = true;
        dom.buscarHerramienta.disabled = false;
        dom.buscarRefaccion.disabled = false;
        dom.resultadosHerramientas.hidden = true;
        dom.resultadosRefacciones.hidden = true;
        dom.resultadosHerramientas.innerHTML = '';
        dom.resultadosRefacciones.innerHTML = '';
        dom.herramientasSeleccionadas.innerHTML =
            '<div class="sprog-resource-empty">No hay herramientas seleccionadas.</div>';
        dom.refaccionesSeleccionadas.innerHTML =
            '<div class="sprog-resource-empty">No hay refacciones seleccionadas.</div>';
        dom.contadorHerramientas.textContent = '0 seleccionadas';
        dom.contadorRefacciones.textContent = '0 seleccionadas';
        dom.fuenteRecursos.textContent = 'Sin recomendaciones';
        dom.descripcionFuenteRecursos.textContent =
            'Selecciona lo que el técnico debería llevar antes de iniciar.';
        dom.listaTecnicos.innerHTML = '';
        dom.contadorSeleccionados.textContent = '0 de 5 seleccionados';
        dom.contadorMotivo.textContent = '0/500 caracteres';
        dom.resumenGuardar.textContent = 'Selecciona una solicitud, fecha y técnicos.';
    }

    function cerrarModal() {
        dom.modal.hidden = true;
        dom.modal.removeAttribute('aria-busy');
        limpiarModal();
    }

    function moverSemana(dias) {
        if (!estado.semanaInicio) {
            return;
        }
        const fecha = new Date(estado.semanaInicio + 'T12:00:00');
        fecha.setDate(fecha.getDate() + dias);
        cargarInicial(fechaISO(fecha));
    }

    function irSemanaActual(siguiente) {
        const hoy = new Date();
        const numero = hoy.getDay() === 0 ? 7 : hoy.getDay();
        hoy.setDate(hoy.getDate() - (numero - 1) + (siguiente ? 7 : 0));
        cargarInicial(fechaISO(hoy));
    }

    function mostrarEstado(mensaje, tipo) {
        dom.estadoPagina.className = 'sprog-status is-' + tipo;
        dom.estadoPagina.textContent = mensaje;
    }

    function llenarSelect(select, valores, textoVacio) {
        const valorActual = select.value;
        select.innerHTML = '<option value="">' + escapeHtml(textoVacio) + '</option>' +
            valores.map(function (valor) {
                return '<option value="' + escapeHtml(valor) + '">' + escapeHtml(valor) + '</option>';
            }).join('');
        if (valores.indexOf(valorActual) !== -1) {
            select.value = valorActual;
        }
    }

    function llenarSelectObjetos(select, valores, textoVacio) {
        const valorActual = select.value;
        select.innerHTML = '<option value="">' + escapeHtml(textoVacio) + '</option>' +
            valores.map(function (item) {
                return '<option value="' + escapeHtml(item.valor) + '">' + escapeHtml(item.texto) + '</option>';
            }).join('');
        if (valores.some(function (item) { return item.valor === valorActual; })) {
            select.value = valorActual;
        }
    }

    function badge(textoBadge, clase) {
        return '<span class="sprog-badge is-' + escapeHtml(clase) + '">' + escapeHtml(textoBadge) + '</span>';
    }

    function claseGrupo(grupo) {
        if (grupo === 'ATRASADO_SIN_INICIAR') return 'is-late';
        if (grupo === 'EN_EJECUCION') return 'is-active';
        if (grupo === 'POR_PROGRAMAR') return 'is-pending';
        return 'is-scheduled';
    }

    function legibleGrupo(grupo) {
        return {
            POR_PROGRAMAR: 'Por programar',
            PROGRAMADO: 'Programado',
            ATRASADO_SIN_INICIAR: 'Atrasado sin iniciar',
            EN_EJECUCION: 'En ejecución'
        }[grupo] || grupo;
    }

    function legibleTipo(tipo) {
        return {
            CORRECTIVO_PROGRAMABLE: 'Correctivo programable',
            MODIFICACION_MEJORA: 'Modificación o mejora',
            RUTINARIO: 'Rutinario'
        }[tipo] || tipo;
    }

    function legiblePrioridad(prioridad) {
        return { ALTA: 'Alta', MEDIA: 'Media', BAJA: 'Baja', URGENTE: 'Urgente' }[prioridad] || prioridad;
    }

    function legibleEstado(valor) {
        return String(valor || '').replace(/_/g, ' ').toLowerCase().replace(/^./, function (letra) { return letra.toUpperCase(); });
    }

    function legibleTurno(valor) {
        return { MATUTINO: 'Matutino', VESPERTINO: 'Vespertino', NOCTURNO: 'Nocturno' }[valor] || valor;
    }

    function legibleRiesgo(valor) {
        return { BAJO: 'Bajo', MEDIO: 'Medio', ALTO: 'Alto' }[valor] || valor || 'Sin definir';
    }

    function etiquetaDia(dia) {
        if (!dia.permitido) return 'Inhábil';
        if (dia.tipo_dia === 'HABIL_EXTRA') return 'Hábil extra';
        if (!dia.configurado) return 'Sin registro';
        return 'Hábil';
    }

    function formatearFecha(fecha) {
        if (!fecha) return 'Sin fecha';
        return new Intl.DateTimeFormat('es-MX', {
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
        }).format(new Date(fecha + 'T12:00:00'));
    }

    function formatearFechaCorta(fecha) {
        return new Intl.DateTimeFormat('es-MX', {
            day: 'numeric', month: 'short', year: 'numeric'
        }).format(new Date(fecha + 'T12:00:00'));
    }

    function formatearFechaHora(valor) {
        if (!valor) return '';
        return new Intl.DateTimeFormat('es-MX', {
            day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit'
        }).format(new Date(String(valor).replace(' ', 'T')));
    }

    function fechaHoy() {
        return fechaISO(new Date());
    }

    function fechaISO(fecha) {
        const anio = fecha.getFullYear();
        const mes = String(fecha.getMonth() + 1).padStart(2, '0');
        const dia = String(fecha.getDate()).padStart(2, '0');
        return anio + '-' + mes + '-' + dia;
    }

    function fechaEnRango(fecha, inicio, fin) {
        return Boolean(fecha && inicio && fin && fecha >= inicio && fecha <= fin);
    }

    function compararNumero(a, b) {
        return Number(a || 0) - Number(b || 0);
    }

    function compararNombre(a, b) {
        return String(a.tecnico || '').localeCompare(String(b.tecnico || ''), 'es');
    }

    function iniciales(nombre) {
        const partes = String(nombre || 'T').trim().split(/\s+/).filter(Boolean);
        return escapeHtml(((partes[0] || 'T').charAt(0) + (partes[1] || '').charAt(0)).toUpperCase());
    }

    function normalizar(valor) {
        return String(valor || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    function escapeHtml(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeSinHtml(valor) {
        return String(valor === null || valor === undefined ? '' : valor);
    }

    function texto(id, valor) {
        const elemento = document.getElementById(id);
        if (elemento) elemento.textContent = String(valor);
    }

    document.querySelectorAll('[data-tab]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            estado.tab = boton.dataset.tab || 'TODOS';
            document.querySelectorAll('[data-tab]').forEach(function (otro) {
                const activo = otro === boton;
                otro.classList.toggle('is-active', activo);
                otro.setAttribute('aria-selected', activo ? 'true' : 'false');
            });
            pintarSolicitudes();
        });
    });

    [dom.filtroBusqueda, dom.filtroTipo, dom.filtroPrioridad, dom.filtroSemana]
        .forEach(function (control) {
            control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', pintarSolicitudes);
        });

    [dom.buscarTecnico, dom.filtroTurnoTecnico, dom.filtroEspecialidadTecnico,
        dom.filtroDepartamentoTecnico, dom.ordenTecnicos]
        .forEach(function (control) {
            control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', pintarTecnicos);
        });

    registrarBuscadorRecurso(
        'HERRAMIENTA',
        dom.buscarHerramienta,
        dom.resultadosHerramientas
    );
    registrarBuscadorRecurso(
        'REFACCION',
        dom.buscarRefaccion,
        dom.resultadosRefacciones
    );

    document.addEventListener('click', function (evento) {
        if (!evento.target.closest('[data-resource-picker]')) {
            dom.resultadosHerramientas.hidden = true;
            dom.resultadosRefacciones.hidden = true;
        }
    });

    dom.fechaProgramada.addEventListener('change', function () {
        actualizarResumenGuardar();
        recargarTecnicosPorFecha();
    });
    dom.confirmarRiesgoNocturno.addEventListener('change', actualizarResumenGuardar);
    dom.motivoProgramacion.addEventListener('input', function () {
        dom.contadorMotivo.textContent = dom.motivoProgramacion.value.length + '/500 caracteres';
        actualizarResumenGuardar();
    });

    dom.form.addEventListener('submit', guardarProgramacion);
    dom.btnCerrarModal.addEventListener('click', cerrarModal);
    dom.btnCancelarModal.addEventListener('click', cerrarModal);
    dom.btnCancelarMantenimiento.addEventListener('click', function () {
        if (estado.solicitud) {
            cancelarMantenimiento(estado.solicitud, dom.btnCancelarMantenimiento);
        }
    });
    dom.modal.addEventListener('click', function (evento) {
        if (evento.target === dom.modal) cerrarModal();
    });
    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape' && !dom.modal.hidden) cerrarModal();
    });

    dom.btnActualizar.addEventListener('click', function () { cargarInicial(estado.semanaInicio); });
    dom.btnSemanaAnterior.addEventListener('click', function () { moverSemana(-7); });
    dom.btnSemanaSiguiente.addEventListener('click', function () { moverSemana(7); });
    dom.btnEstaSemana.addEventListener('click', function () { irSemanaActual(false); });
    dom.btnProximaSemana.addEventListener('click', function () { irSemanaActual(true); });

    cargarInicial('');
})();
</script>
</body>
</html>