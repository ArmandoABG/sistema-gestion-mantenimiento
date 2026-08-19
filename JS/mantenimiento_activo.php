<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/seguridad.php';
sm_requerir_sesion(['TECNICO'], false);

$nombreTecnico = trim(
    (string) (
        $_SESSION['nombre_completo']
        ?? $_SESSION['usuario']
        ?? 'Técnico'
    )
);

$cssMantenimientoActivo = __DIR__ . '/../css/style_mantenimiento_activo.css';
$versionCss = is_file($cssMantenimientoActivo)
    ? (string) filemtime($cssMantenimientoActivo)
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
        content="Centro de control de la actividad actual del técnico"
    >
    <title>Actividad actual | Sistema de Mantenimiento</title>
    <link
        rel="stylesheet"
        href="../css/style_mantenimiento_activo.css?v=<?= htmlspecialchars($versionCss, ENT_QUOTES, 'UTF-8') ?>"
    >
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="mact-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="mact-icon-activity" viewBox="0 0 24 24">
        <path d="M3 12h4l2.5-6 5 12 2.5-6H21"/>
    </symbol>
    <symbol id="mact-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="mact-icon-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </symbol>
    <symbol id="mact-icon-pause" viewBox="0 0 24 24">
        <rect x="5" y="4" width="5" height="16" rx="1"/>
        <rect x="14" y="4" width="5" height="16" rx="1"/>
    </symbol>
    <symbol id="mact-icon-play" viewBox="0 0 24 24">
        <path d="m8 5 11 7-11 7V5Z"/>
    </symbol>
    <symbol id="mact-icon-check" viewBox="0 0 24 24">
        <path d="m5 12 4 4L19 6"/>
    </symbol>
    <symbol id="mact-icon-tools" viewBox="0 0 24 24">
        <path d="m14.7 6.3 3-3a4 4 0 0 1-5 5l-7.4 7.4a2 2 0 1 1-2.8-2.8l7.4-7.4a4 4 0 0 1 4.8-5.2"/>
        <path d="m15 14 6 6M17 12l2-2"/>
    </symbol>
    <symbol id="mact-icon-shield" viewBox="0 0 24 24">
        <path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="mact-icon-users" viewBox="0 0 24 24">
        <circle cx="9" cy="8" r="3"/>
        <circle cx="17" cy="9" r="2.5"/>
        <path d="M3 20a6 6 0 0 1 12 0M14 19a5 5 0 0 1 8 0"/>
    </symbol>
    <symbol id="mact-icon-alert" viewBox="0 0 24 24">
        <path d="M12 3 2 21h20L12 3Z"/>
        <path d="M12 9v5M12 18h.01"/>
    </symbol>
    <symbol id="mact-icon-clipboard" viewBox="0 0 24 24">
        <path d="M9 5h6M9 3h6v4H9z"/>
        <path d="M7 5H5v16h14V5h-2M8 12h8M8 16h6"/>
    </symbol>
    <symbol id="mact-icon-sparkles" viewBox="0 0 24 24">
        <path d="m12 3-1.2 3.8L7 8l3.8 1.2L12 13l1.2-3.8L17 8l-3.8-1.2L12 3Z"/>
        <path d="m5 15-.7 2.3L2 18l2.3.7L5 21l.7-2.3L8 18l-2.3-.7L5 15Z"/>
    </symbol>
    <symbol id="mact-icon-arrow" viewBox="0 0 24 24">
        <path d="M5 12h14M14 7l5 5-5 5"/>
    </symbol>
</svg>

<main class="mact-page">
    <div class="mact-ambient mact-ambient--one" aria-hidden="true"></div>
    <div class="mact-ambient mact-ambient--two" aria-hidden="true"></div>

    <section class="mact-heading mact-heading--hero" aria-labelledby="tituloActividadActual">
        <div class="mact-heading__pattern" aria-hidden="true"></div>

        <div class="mact-heading__content">
            <div class="mact-heading__copy">
                <p class="mact-eyebrow mact-eyebrow--hero">
                    <span class="mact-eyebrow__icon" aria-hidden="true">
                        <svg><use href="#mact-icon-activity"></use></svg>
                    </span>
                    Ejecución del técnico
                </p>

                <h1 id="tituloActividadActual">Actividad actual</h1>

                <p class="mact-heading__description">
                    Controla el tiempo real, administra pausas y registra el cierre
                    completo de tu mantenimiento sin perder la trazabilidad.
                </p>

                <div class="mact-heading__meta">
                    <span>
                        <span class="mact-live-dot" aria-hidden="true"></span>
                        Centro de ejecución activo
                    </span>
                    <span>
                        Técnico conectado:
                        <strong><?= htmlspecialchars($nombreTecnico, ENT_QUOTES, 'UTF-8') ?></strong>
                    </span>
                    <span>Actualización automática cada <strong>20 segundos</strong></span>
                </div>
            </div>

            <div class="mact-heading__actions">
                <div class="mact-heading__mini-card">
                    <span class="mact-heading__mini-icon" aria-hidden="true">
                        <svg><use href="#mact-icon-clock"></use></svg>
                    </span>
                    <div>
                        <small>Control operativo</small>
                        <strong>Tiempo, pausas y cierre en una sola vista</strong>
                    </div>
                </div>

                <button type="button" class="mact-btn mact-btn--hero" id="btnActualizar">
                    <svg aria-hidden="true"><use href="#mact-icon-refresh"></use></svg>
                    <span>Actualizar información</span>
                </button>
            </div>
        </div>
    </section>

    <section class="mact-guides" aria-label="Reglas principales de ejecución">
        <article>
            <span class="mact-guide-icon" aria-hidden="true">
                <svg><use href="#mact-icon-activity"></use></svg>
            </span>
            <div>
                <strong>Una actividad en proceso</strong>
                <p>El sistema mantiene una sola ejecución activa por técnico para evitar tiempos cruzados.</p>
            </div>
        </article>

        <article>
            <span class="mact-guide-icon mact-guide-icon--warning" aria-hidden="true">
                <svg><use href="#mact-icon-pause"></use></svg>
            </span>
            <div>
                <strong>Pausas con tiempo conservado</strong>
                <p>Al pausar, el tiempo activo se detiene y la ejecución queda disponible para continuarla después.</p>
            </div>
        </article>

        <article>
            <span class="mact-guide-icon mact-guide-icon--success" aria-hidden="true">
                <svg><use href="#mact-icon-check"></use></svg>
            </span>
            <div>
                <strong>Cierre con evidencia</strong>
                <p>El resultado, trabajo realizado, limpieza y observaciones quedan registrados antes de finalizar.</p>
            </div>
        </article>
    </section>

    <section class="mact-rule" aria-label="Regla de reanudación manual">
        <span class="mact-rule__icon" aria-hidden="true">
            <svg><use href="#mact-icon-refresh"></use></svg>
        </span>
        <div>
            <strong>Una urgencia no reanuda automáticamente tu mantenimiento anterior</strong>
            <p>
                Cuando la urgencia termine o sea cancelada, el trabajo anterior seguirá pausado
                hasta que presiones <b>Reanudar mantenimiento</b>.
            </p>
        </div>
        <span class="mact-rule__tag">Reanudación manual</span>
    </section>

    <div class="mact-status" id="estadoCarga" role="status" aria-live="polite">
        Cargando actividad...
    </div>

    <section class="mact-alert mact-alert--cancelled" id="avisoCancelacionAdministrativa" hidden>
        <span aria-hidden="true"><svg><use href="#mact-icon-alert"></use></svg></span>
        <div>
            <strong>Esta actividad fue cancelada por administración</strong>
            <p id="textoCancelacionAdministrativa">La ejecución fue detenida y ya no puede continuarse.</p>
            <small id="metaCancelacionAdministrativa"></small>
        </div>
        <a href="mantenimientos_finalizados.php#cancelaciones" class="mact-btn mact-btn--cancel-history">Ver historial</a>
    </section>

    <section class="mact-kpis" aria-label="Resumen de actividades">
        <article class="mact-kpi mact-kpi--active">
            <span class="mact-kpi__icon" aria-hidden="true">
                <svg><use href="#mact-icon-activity"></use></svg>
            </span>
            <span>En proceso</span>
            <strong id="kpiProceso">0</strong>
            <small>Máximo una actividad</small>
        </article>

        <article class="mact-kpi mact-kpi--paused">
            <span class="mact-kpi__icon" aria-hidden="true">
                <svg><use href="#mact-icon-pause"></use></svg>
            </span>
            <span>Pausadas</span>
            <strong id="kpiPausadas">0</strong>
            <small>Conservan su tiempo</small>
        </article>

        <article class="mact-kpi mact-kpi--ready">
            <span class="mact-kpi__icon" aria-hidden="true">
                <svg><use href="#mact-icon-play"></use></svg>
            </span>
            <span>Listas para reanudar</span>
            <strong id="kpiReanudar">0</strong>
            <small>Requieren acción manual</small>
        </article>

        <article class="mact-kpi mact-kpi--waiting">
            <span class="mact-kpi__icon" aria-hidden="true">
                <svg><use href="#mact-icon-clock"></use></svg>
            </span>
            <span>Esperando urgencia</span>
            <strong id="kpiEspera">0</strong>
            <small>Aún no se pueden reanudar</small>
        </article>
    </section>

    <section class="mact-card mact-current-card">
        <header class="mact-card__head">
            <div>
                <p class="mact-eyebrow" id="detalleEyebrow">Actividad seleccionada</p>
                <h2 id="detalleTitulo">Cargando...</h2>
                <p id="detalleSubtitulo">Espera mientras consultamos tu actividad.</p>
            </div>
            <div class="mact-head-badges" id="detalleBadges"></div>
        </header>

        <div class="mact-empty" id="actividadVacia" hidden>
            <span aria-hidden="true">
                <svg><use href="#mact-icon-check"></use></svg>
            </span>
            <h2>No tienes actividades abiertas</h2>
            <p>
                Revisa tus mantenimientos asignados o consulta las urgencias disponibles
                para comenzar una actividad.
            </p>
            <div class="mact-empty__actions">
                <a href="mantenimientos_asignados.php" class="mact-btn mact-btn--primary">
                    Ver asignados
                </a>
                <a href="urgencias_disponibles.php" class="mact-btn mact-btn--danger">
                    Ver urgencias
                </a>
            </div>
        </div>

        <div id="actividadContenido" hidden>
            <section class="mact-hero" id="detalleHero">
                <div class="mact-hero__identity">
                    <span class="mact-folio" id="detalleFolio">—</span>
                    <h3 id="detalleEquipo">—</h3>
                    <p id="detalleUbicacion">—</p>
                </div>
                <div class="mact-live-state" id="estadoEnVivo">
                    <i aria-hidden="true"></i>
                    <span id="textoEstadoEnVivo">En proceso</span>
                </div>
            </section>

            <section class="mact-timers" aria-label="Tiempos de ejecución">
                <article class="mact-timer mact-timer--main">
                    <span class="mact-timer__icon" aria-hidden="true">
                        <svg><use href="#mact-icon-activity"></use></svg>
                    </span>
                    <span>Tiempo activo</span>
                    <strong id="tiempoActivo">00:00:00</strong>
                    <small>Trabajo real acumulado</small>
                </article>
                <article class="mact-timer mact-timer--pause">
                    <span class="mact-timer__icon" aria-hidden="true">
                        <svg><use href="#mact-icon-pause"></use></svg>
                    </span>
                    <span>Tiempo pausado</span>
                    <strong id="tiempoPausa">00:00:00</strong>
                    <small>Pausas acumuladas</small>
                </article>
                <article class="mact-timer mact-timer--start">
                    <span class="mact-timer__icon" aria-hidden="true">
                        <svg><use href="#mact-icon-clock"></use></svg>
                    </span>
                    <span>Inicio</span>
                    <strong class="mact-timer__date" id="fechaInicio">—</strong>
                    <small id="textoInicio">Hora del servidor</small>
                </article>
            </section>

            <section class="mact-alert mact-alert--warning" id="avisoPausa" hidden>
                <span aria-hidden="true">
                    <svg><use href="#mact-icon-pause"></use></svg>
                </span>
                <div>
                    <strong id="avisoPausaTitulo">Actividad pausada</strong>
                    <p id="avisoPausaTexto"></p>
                </div>
            </section>

            <section class="mact-alert mact-alert--danger" id="avisoUrgente" hidden>
                <span aria-hidden="true">
                    <svg><use href="#mact-icon-alert"></use></svg>
                </span>
                <div>
                    <strong>Finalizar cerrará la urgencia para todos</strong>
                    <p>
                        Los participantes activos terminarán su ejecución y quienes solo aceptaron
                        quedarán como no participantes. Sus mantenimientos anteriores permanecerán
                        pausados para reanudación manual.
                    </p>
                </div>
            </section>

            <section class="mact-detail-grid" aria-label="Información general">
                <article>
                    <span class="mact-detail-grid__icon" aria-hidden="true">
                        <svg><use href="#mact-icon-tools"></use></svg>
                    </span>
                    <span>Tipo de mantenimiento</span>
                    <strong id="detalleTipo">—</strong>
                    <small id="detallePrioridad">—</small>
                </article>
                <article>
                    <span class="mact-detail-grid__icon" aria-hidden="true">
                        <svg><use href="#mact-icon-users"></use></svg>
                    </span>
                    <span>Solicitante</span>
                    <strong id="detalleSolicitante">—</strong>
                    <small id="detalleSolicitudFecha">—</small>
                </article>
                <article>
                    <span class="mact-detail-grid__icon" aria-hidden="true">
                        <svg><use href="#mact-icon-clock"></use></svg>
                    </span>
                    <span>Programación</span>
                    <strong id="detalleProgramacion">—</strong>
                    <small id="detalleLimite">—</small>
                </article>
                <article>
                    <span class="mact-detail-grid__icon" aria-hidden="true">
                        <svg><use href="#mact-icon-shield"></use></svg>
                    </span>
                    <span>Riesgo</span>
                    <strong id="detalleRiesgo">—</strong>
                    <small id="detalleSeguridad">—</small>
                </article>
            </section>

            <section class="mact-description-grid">
                <article class="mact-copy-card">
                    <span class="mact-copy-card__icon" aria-hidden="true">
                        <svg><use href="#mact-icon-clipboard"></use></svg>
                    </span>
                    <h3>Trabajo solicitado</h3>
                    <p id="detalleDescripcion">—</p>
                </article>
                <article class="mact-copy-card" id="bloqueFalla">
                    <span class="mact-copy-card__icon" aria-hidden="true">
                        <svg><use href="#mact-icon-tools"></use></svg>
                    </span>
                    <h3>Falla o condición reportada</h3>
                    <p id="detalleFalla">—</p>
                </article>
                <article class="mact-copy-card" id="bloqueImpacto">
                    <span class="mact-copy-card__icon" aria-hidden="true">
                        <svg><use href="#mact-icon-alert"></use></svg>
                    </span>
                    <h3>Impacto en la operación</h3>
                    <p id="detalleImpacto">—</p>
                </article>
                <article class="mact-copy-card" id="bloqueObjetivo">
                    <span class="mact-copy-card__icon" aria-hidden="true">
                        <svg><use href="#mact-icon-sparkles"></use></svg>
                    </span>
                    <h3>Objetivo y resultado esperado</h3>
                    <p id="detalleObjetivo">—</p>
                </article>
            </section>

            <section class="mact-resources">
                <header>
                    <div>
                        <h3>Recursos recomendados para el mantenimiento</h3>
                        <p>Consulta lo que fue preparado antes de realizar el trabajo.</p>
                    </div>
                    <span id="contadorRecursos">0 recursos</span>
                </header>
                <div class="mact-resources__content" id="detalleRecursos"></div>
            </section>

            <section class="mact-participants">
                <header>
                    <div>
                        <h3>Equipo participante</h3>
                        <p>El cierre realizado por un participante concluye la solicitud para todo el equipo.</p>
                    </div>
                    <span id="contadorParticipantes">0 participantes</span>
                </header>
                <div class="mact-participant-list" id="listaParticipantes"></div>
            </section>

            <footer class="mact-actions" id="accionesActividad">
                <div class="mact-actions__copy">
                    <strong>Acciones disponibles</strong>
                    <span>El sistema valida el estado antes de ejecutar cada operación.</span>
                </div>
                <div class="mact-actions__buttons">
                    <button type="button" class="mact-btn mact-btn--secondary" id="btnPausar">
                        Pausar actividad
                    </button>
                    <button type="button" class="mact-btn mact-btn--primary" id="btnReanudarPrincipal" hidden>
                        Reanudar mantenimiento
                    </button>
                    <button type="button" class="mact-btn mact-btn--success" id="btnFinalizar">
                        Finalizar mantenimiento
                    </button>
                </div>
            </footer>
        </div>
    </section>

    <section class="mact-card mact-paused-card">
        <header class="mact-card__head">
            <div>
                <p class="mact-eyebrow">Reanudación manual</p>
                <h2>Trabajos pausados</h2>
                <p>Selecciona una actividad para revisar por qué está pausada y cuándo puede reanudarse.</p>
            </div>
            <span class="mact-count" id="contadorPausadas">0 actividades</span>
        </header>

        <div class="mact-paused-list" id="listaPausadas"></div>

        <div class="mact-empty mact-empty--compact" id="pausadasVacias" hidden>
            <span aria-hidden="true">
                <svg><use href="#mact-icon-check"></use></svg>
            </span>
            <h3>No tienes trabajos pausados</h3>
            <p>Las actividades que pauses aparecerán aquí conservando todo su tiempo registrado.</p>
        </div>
    </section>

    <footer class="mact-footer">
        <span>Centro operativo del técnico</span>
        <span>Tiempo y acciones sincronizados con el servidor</span>
    </footer>

    <div class="mact-tools-background" aria-hidden="true"></div>
</main>

<div class="mact-modal" id="modalPausa" hidden aria-hidden="true">
    <div class="mact-modal__backdrop" data-close-modal="pausa"></div>
    <section
        class="mact-modal__dialog mact-modal__dialog--small"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloModalPausa"
    >
        <header class="mact-modal__head">
            <div class="mact-modal__title-group">
                <span class="mact-modal__title-icon" aria-hidden="true">
                    <svg><use href="#mact-icon-pause"></use></svg>
                </span>
                <div>
                    <p class="mact-eyebrow">Pausa manual</p>
                    <h2 id="tituloModalPausa">Pausar actividad</h2>
                    <p>El tiempo activo se detendrá y la actividad quedará disponible para reanudarla manualmente.</p>
                </div>
            </div>
            <button type="button" class="mact-modal__close" data-close-modal="pausa" aria-label="Cerrar">×</button>
        </header>

        <form id="formPausa" class="mact-modal__body" novalidate>
            <input type="hidden" name="ejecucion_id" id="pausaEjecucionId">

            <section class="mact-form-intro">
                <span aria-hidden="true"><svg><use href="#mact-icon-clock"></use></svg></span>
                <div>
                    <strong>El tiempo queda protegido</strong>
                    <p>La pausa conservará los segundos activos acumulados hasta este momento.</p>
                </div>
            </section>

            <label class="mact-field" for="motivoPausa">
                <span>Motivo de la pausa <b>*</b></span>
                <textarea
                    id="motivoPausa"
                    name="motivo"
                    minlength="10"
                    maxlength="500"
                    rows="4"
                    placeholder="Ejemplo: necesito esperar una refacción o verificar una condición de seguridad."
                    required
                ></textarea>
                <small><span id="contadorMotivo">0</span>/500 caracteres</small>
            </label>

            <div class="mact-modal__actions">
                <button type="button" class="mact-btn mact-btn--secondary" data-close-modal="pausa">Cancelar</button>
                <button type="submit" class="mact-btn mact-btn--primary" id="btnConfirmarPausa">Confirmar pausa</button>
            </div>
        </form>
    </section>
</div>

<div class="mact-modal" id="modalFinalizar" hidden aria-hidden="true">
    <div class="mact-modal__backdrop" data-close-modal="finalizar"></div>
    <section class="mact-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tituloModalFinalizar">
        <header class="mact-modal__head">
            <div class="mact-modal__title-group">
                <span class="mact-modal__title-icon mact-modal__title-icon--success" aria-hidden="true">
                    <svg><use href="#mact-icon-check"></use></svg>
                </span>
                <div>
                    <p class="mact-eyebrow">Cierre del mantenimiento</p>
                    <h2 id="tituloModalFinalizar">Finalizar mantenimiento</h2>
                    <p id="textoModalFinalizar">Registra el resultado real antes de cerrar la solicitud.</p>
                </div>
            </div>
            <button type="button" class="mact-modal__close" data-close-modal="finalizar" aria-label="Cerrar">×</button>
        </header>

        <form id="formFinalizar" class="mact-modal__body" novalidate>
            <input type="hidden" name="ejecucion_id" id="finalizarEjecucionId">

            <fieldset class="mact-fieldset">
                <legend>¿Cómo quedó el trabajo? <b>*</b></legend>
                <div class="mact-choice-grid mact-choice-grid--three">
                    <label class="mact-choice">
                        <input type="radio" name="trabajo_quedo" value="TERMINADO" required>
                        <span><strong>Terminado</strong><small>Quedó completamente resuelto.</small></span>
                    </label>
                    <label class="mact-choice">
                        <input type="radio" name="trabajo_quedo" value="PARCIAL" required>
                        <span><strong>Parcial</strong><small>Se avanzó, pero falta trabajo.</small></span>
                    </label>
                    <label class="mact-choice">
                        <input type="radio" name="trabajo_quedo" value="PROVISIONAL" required>
                        <span><strong>Provisional</strong><small>Funciona temporalmente.</small></span>
                    </label>
                </div>
            </fieldset>

            <label class="mact-field" for="descripcionTrabajo">
                <span>Trabajo realizado <b>*</b></span>
                <textarea
                    id="descripcionTrabajo"
                    name="descripcion_trabajo_realizado"
                    minlength="20"
                    maxlength="4000"
                    rows="6"
                    placeholder="Describe lo que revisaste, reparaste, sustituiste o ajustaste."
                    required
                ></textarea>
                <small><span id="contadorDescripcion">0</span>/4000 caracteres</small>
            </label>

            <label class="mact-field" for="queFalto" id="campoQueFalto" hidden>
                <span>¿Qué falta por realizar? <b>*</b></span>
                <textarea
                    id="queFalto"
                    name="que_falto"
                    minlength="10"
                    maxlength="2500"
                    rows="4"
                    placeholder="Indica las piezas, actividades o pruebas que aún están pendientes."
                ></textarea>
                <small><span id="contadorFalto">0</span>/2500 caracteres</small>
            </label>

            <section class="mact-close-resources" aria-labelledby="tituloRecursosUtilizados">
                <header class="mact-close-resources__head">
                    <div>
                        <span class="mact-close-resources__step">RECURSOS REALES</span>
                        <h3 id="tituloRecursosUtilizados">¿Qué utilizaste realmente?</h3>
                        <p>
                            Registra lo que sí se llevó y utilizó. Esta información quedará en el
                            historial; en urgencias sin recomendación administrativa también podrá
                            alimentar la próxima recomendación del equipo.
                        </p>
                    </div>
                </header>

                <div class="mact-close-resource-grid">
                    <article class="mact-close-resource-picker" data-cierre-picker="HERRAMIENTA">
                        <header>
                            <div>
                                <span aria-hidden="true">🔧</span>
                                <div>
                                    <h4>Herramientas utilizadas</h4>
                                    <p>Busca y selecciona todas las que realmente usaste.</p>
                                </div>
                            </div>
                            <span id="contadorHerramientasCierre">0 seleccionadas</span>
                        </header>

                        <label class="mact-close-resource-search" for="buscarHerramientasCierre">
                            <span>Buscar herramienta</span>
                            <input
                                type="search"
                                id="buscarHerramientasCierre"
                                maxlength="150"
                                autocomplete="off"
                                placeholder="Nombre, código o descripción"
                            >
                        </label>
                        <div class="mact-close-resource-results" id="resultadosHerramientasCierre" hidden></div>
                        <div class="mact-close-resource-selected" id="seleccionHerramientasCierre"></div>

                        <div class="mact-close-resource-other">
                            <button type="button" class="mact-btn mact-btn--secondary mact-btn--small" id="btnOtraHerramienta">
                                Otra herramienta no registrada
                            </button>
                            <div id="otrasHerramientasCierre"></div>
                        </div>

                        <label class="mact-close-resource-none">
                            <input type="hidden" name="sin_herramientas_utilizadas" value="0">
                            <input type="checkbox" id="sinHerramientasCierre" name="sin_herramientas_utilizadas" value="1">
                            <span>
                                <strong>No se utilizaron herramientas</strong>
                                <small>Márcalo solo cuando realmente no se necesitó ninguna.</small>
                            </span>
                        </label>
                    </article>

                    <article class="mact-close-resource-picker" data-cierre-picker="REFACCION">
                        <header>
                            <div>
                                <span aria-hidden="true">⚙️</span>
                                <div>
                                    <h4>Refacciones utilizadas</h4>
                                    <p>Registra piezas, componentes o consumibles sustituidos.</p>
                                </div>
                            </div>
                            <span id="contadorRefaccionesCierre">0 seleccionadas</span>
                        </header>

                        <label class="mact-close-resource-search" for="buscarRefaccionesCierre">
                            <span>Buscar refacción</span>
                            <input
                                type="search"
                                id="buscarRefaccionesCierre"
                                maxlength="150"
                                autocomplete="off"
                                placeholder="Nombre, código o descripción"
                            >
                        </label>
                        <div class="mact-close-resource-results" id="resultadosRefaccionesCierre" hidden></div>
                        <div class="mact-close-resource-selected" id="seleccionRefaccionesCierre"></div>

                        <div class="mact-close-resource-other">
                            <button type="button" class="mact-btn mact-btn--secondary mact-btn--small" id="btnOtraRefaccion">
                                Otra refacción no registrada
                            </button>
                            <div id="otrasRefaccionesCierre"></div>
                        </div>

                        <label class="mact-close-resource-none">
                            <input type="hidden" name="sin_refacciones_utilizadas" value="0">
                            <input type="checkbox" id="sinRefaccionesCierre" name="sin_refacciones_utilizadas" value="1">
                            <span>
                                <strong>No se utilizaron refacciones</strong>
                                <small>Úsalo cuando no se sustituyó ninguna pieza o componente.</small>
                            </span>
                        </label>
                    </article>
                </div>

                <div class="mact-close-resource-note">
                    <strong>¿No aparece en el buscador?</strong>
                    <span>
                        Usa “Otra”. Se guardará en el historial y llegará al administrador como
                        sugerencia de alta; no se agregará automáticamente al catálogo oficial.
                    </span>
                </div>
            </section>

            <div class="mact-form-grid">
                <fieldset class="mact-fieldset">
                    <legend>¿Se realizó limpieza del área? <b>*</b></legend>
                    <div class="mact-choice-grid">
                        <label class="mact-choice mact-choice--compact">
                            <input type="radio" name="realizo_limpieza_area" value="1" required>
                            <span><strong>Sí</strong></span>
                        </label>
                        <label class="mact-choice mact-choice--compact">
                            <input type="radio" name="realizo_limpieza_area" value="0" required>
                            <span><strong>No</strong></span>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="mact-fieldset">
                    <legend>¿El área quedó ordenada y libre de componentes? <b>*</b></legend>
                    <div class="mact-choice-grid">
                        <label class="mact-choice mact-choice--compact">
                            <input type="radio" name="area_ordenada_libre_componentes" value="1" required>
                            <span><strong>Sí</strong></span>
                        </label>
                        <label class="mact-choice mact-choice--compact">
                            <input type="radio" name="area_ordenada_libre_componentes" value="0" required>
                            <span><strong>No</strong></span>
                        </label>
                    </div>
                </fieldset>
            </div>

            <label class="mact-field" for="observacionesCierre">
                <span>Observaciones del cierre</span>
                <textarea
                    id="observacionesCierre"
                    name="observaciones_cierre"
                    maxlength="2000"
                    rows="4"
                    placeholder="Agrega recomendaciones, pruebas realizadas o información que deba conocer el administrador."
                ></textarea>
                <small><span id="contadorObservaciones">0</span>/2000 caracteres</small>
            </label>

            <section class="mact-close-warning" id="advertenciaCierreUrgente" hidden>
                <span aria-hidden="true"><svg><use href="#mact-icon-alert"></use></svg></span>
                <div>
                    <strong>Esta acción cerrará la urgencia para todos los participantes.</strong>
                    <p>
                        Ningún mantenimiento anterior se reanudará automáticamente. Cada técnico
                        deberá reanudarlo manualmente cuando esté listo.
                    </p>
                </div>
            </section>

            <div class="mact-modal__actions">
                <button type="button" class="mact-btn mact-btn--secondary" data-close-modal="finalizar">Cancelar</button>
                <button type="submit" class="mact-btn mact-btn--success" id="btnConfirmarFinalizar">Finalizar mantenimiento</button>
            </div>
        </form>
    </section>
</div>

<?php include __DIR__ . '/../inc/alertas.php'; ?>

<script>
(() => {
    'use strict';

    const UI = window.SistemaUI;

    if (!UI) {
        const estadoCarga = document.getElementById('estadoCarga');

        if (estadoCarga) {
            estadoCarga.textContent = 'No fue posible cargar las herramientas de la interfaz. Actualiza la página.';
            estadoCarga.className = 'mact-status mact-status--error';
        }

        console.error(
            'No se cargó window.SistemaUI. Verifica que inc/alertas.php exista y se incluya antes del script del módulo.'
        );
        return;
    }

    const ENDPOINT = '../funciones/mantenimiento_activo_funciones.php';
    const $ = (id) => document.getElementById(id);

    const estado = {
        cargando: false,
        procesando: false,
        actividadActual: null,
        cancelacion: null,
        pausadas: [],
        seleccionada: null,
        participantes: [],
        resumen: {},
        servidorBase: null,
        clienteBase: 0,
        temporizador: null,
        refresco: null,
        modalAbierto: '',
        ejecucionInicial: 0,
        recursosCierre: {
            HERRAMIENTA: [],
            REFACCION: []
        },
        otrosCierre: {
            HERRAMIENTA: [],
            REFACCION: []
        },
        temporizadoresBusquedaCierre: {
            HERRAMIENTA: null,
            REFACCION: null
        }
    };

    const etiquetasTipo = {
        CORRECTIVO_PROGRAMABLE: 'Correctivo programable',
        MODIFICACION_MEJORA: 'Modificación o mejora',
        CORRECTIVO_URGENTE: 'Correctivo urgente',
        RUTINARIO: 'Mantenimiento rutinario'
    };

    const etiquetasEstado = {
        EN_PROCESO: 'En proceso',
        PAUSADA: 'Pausada',
        PAUSADO: 'Pausado',
        AGENDADO: 'Agendado',
        ATRASADO: 'Atrasado',
        TERMINADO: 'Terminado',
        CANCELADO: 'Cancelado',
        ASIGNADO: 'Asignado',
        ACEPTADO: 'Aceptado',
        NO_PARTICIPO: 'No participó'
    };

    const etiquetasPausa = {
        URGENCIA: 'Pausa por urgencia',
        MANUAL: 'Pausa manual',
        ADMINISTRATIVA: 'Pausa administrativa',
        FALTA_RECURSO: 'Falta de recurso',
        CAMBIO_PRIORIDAD: 'Cambio de prioridad',
        OTRO: 'Otra pausa'
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
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function recursosDe(registro, clave) {
        return Array.isArray(registro && registro[clave])
            ? registro[clave]
            : [];
    }

    function pintarRecursosRecomendados(registro) {
        const herramientas = recursosDe(registro, 'herramientas_recomendadas');
        const refacciones = recursosDe(registro, 'refacciones_recomendadas');
        const total = herramientas.length + refacciones.length;
        const contenedor = $('detalleRecursos');

        $('contadorRecursos').textContent = total === 1
            ? '1 recurso'
            : total + ' recursos';

        if (total < 1) {
            contenedor.innerHTML = `
                <div class="mact-resources-empty">
                    <strong>No existen recomendaciones registradas</strong>
                    <p>Verifica el trabajo y confirma qué herramientas o refacciones necesitas antes de continuar.</p>
                </div>
            `;
            return;
        }

        const panel = (titulo, elementos, vacio) => `
            <article class="mact-resource-panel${elementos.length ? '' : ' is-empty'}">
                <header>
                    <h4>${escapar(titulo)}</h4>
                    <span>${elementos.length}</span>
                </header>
                ${elementos.length ? `
                    <ul>
                        ${elementos.map((recurso) => `
                            <li>
                                <span aria-hidden="true">✓</span>
                                <div>
                                    <strong>${escapar(texto(recurso.nombre, 'Recurso'))}</strong>
                                    ${recurso.codigo ? `<small>Código: ${escapar(recurso.codigo)}</small>` : ''}
                                    ${recurso.descripcion ? `<p>${escapar(recurso.descripcion)}</p>` : ''}
                                    ${numero(recurso.activo) !== 1 ? '<em>Desactivado; se conserva por historial.</em>' : ''}
                                </div>
                            </li>
                        `).join('')}
                    </ul>
                ` : `<p>${escapar(vacio)}</p>`}
            </article>
        `;

        contenedor.innerHTML = [
            panel('Herramientas', herramientas, 'No se recomendaron herramientas.'),
            panel('Refacciones', refacciones, 'No se recomendaron refacciones.')
        ].join('');
    }

    function configuracionCierre(tipo) {
        const herramienta = tipo === 'HERRAMIENTA';
        return {
            tipo,
            buscador: $(herramienta ? 'buscarHerramientasCierre' : 'buscarRefaccionesCierre'),
            resultados: $(herramienta ? 'resultadosHerramientasCierre' : 'resultadosRefaccionesCierre'),
            seleccion: $(herramienta ? 'seleccionHerramientasCierre' : 'seleccionRefaccionesCierre'),
            contador: $(herramienta ? 'contadorHerramientasCierre' : 'contadorRefaccionesCierre'),
            otros: $(herramienta ? 'otrasHerramientasCierre' : 'otrasRefaccionesCierre'),
            botonOtro: $(herramienta ? 'btnOtraHerramienta' : 'btnOtraRefaccion'),
            ninguno: $(herramienta ? 'sinHerramientasCierre' : 'sinRefaccionesCierre'),
            singular: herramienta ? 'herramienta' : 'refacción',
            plural: herramienta ? 'herramientas' : 'refacciones'
        };
    }

    function reiniciarRecursosCierre() {
        ['HERRAMIENTA', 'REFACCION'].forEach((tipo) => {
            estado.recursosCierre[tipo] = [];
            estado.otrosCierre[tipo] = [];
            const config = configuracionCierre(tipo);
            config.buscador.value = '';
            config.buscador.disabled = false;
            config.botonOtro.disabled = false;
            config.ninguno.checked = false;
            config.resultados.innerHTML = '';
            config.resultados.hidden = true;
            pintarSeleccionRecursosCierre(tipo);
            pintarOtrosCierre(tipo);
        });
    }

    function recursoCierreSeleccionado(tipo, id) {
        return estado.recursosCierre[tipo].some((recurso) => numero(recurso.id) === numero(id));
    }

    async function buscarRecursosCierre(tipo) {
        const config = configuracionCierre(tipo);
        if (config.ninguno.checked || estado.procesando) return;

        config.resultados.hidden = false;
        config.resultados.innerHTML = '<div class="mact-close-resource-status">Buscando...</div>';

        try {
            const parametros = new URLSearchParams({
                accion: 'buscar_recursos',
                tipo_recurso: tipo,
                q: config.buscador.value.trim()
            });
            const datos = await UI.peticionJson(ENDPOINT + '?' + parametros.toString());
            pintarResultadosRecursosCierre(tipo, Array.isArray(datos.recursos) ? datos.recursos : []);
        } catch (error) {
            config.resultados.innerHTML = `
                <div class="mact-close-resource-status is-error">
                    ${escapar(error.message || 'No fue posible buscar recursos.')}
                </div>
            `;
        }
    }

    function pintarResultadosRecursosCierre(tipo, recursos) {
        const config = configuracionCierre(tipo);
        const disponibles = recursos.filter((recurso) => !recursoCierreSeleccionado(tipo, recurso.id));

        if (!disponibles.length) {
            config.resultados.innerHTML = `
                <div class="mact-close-resource-status">
                    No hay ${escapar(config.plural)} disponibles con esa búsqueda.
                </div>
            `;
            return;
        }

        config.resultados.innerHTML = disponibles.map((recurso) => `
            <button
                type="button"
                class="mact-close-resource-option"
                data-cierre-agregar="${escapar(tipo)}"
                data-recurso-id="${numero(recurso.id)}"
                data-recurso-nombre="${escapar(recurso.nombre || '')}"
                data-recurso-codigo="${escapar(recurso.codigo || '')}"
                data-recurso-descripcion="${escapar(recurso.descripcion || '')}"
            >
                <span>
                    <strong>${escapar(texto(recurso.nombre, 'Recurso'))}</strong>
                    ${recurso.codigo ? `<small>${escapar(recurso.codigo)}</small>` : ''}
                </span>
                ${recurso.descripcion ? `<p>${escapar(recurso.descripcion)}</p>` : ''}
            </button>
        `).join('');
    }

    function agregarRecursoCierre(tipo, recurso) {
        if (recursoCierreSeleccionado(tipo, recurso.id)) return;
        estado.recursosCierre[tipo].push(recurso);
        estado.recursosCierre[tipo].sort((a, b) => String(a.nombre || '').localeCompare(String(b.nombre || ''), 'es'));
        pintarSeleccionRecursosCierre(tipo);
        const config = configuracionCierre(tipo);
        config.resultados.hidden = true;
        config.buscador.value = '';
    }

    function quitarRecursoCierre(tipo, id) {
        estado.recursosCierre[tipo] = estado.recursosCierre[tipo].filter(
            (recurso) => numero(recurso.id) !== numero(id)
        );
        pintarSeleccionRecursosCierre(tipo);
    }

    function pintarSeleccionRecursosCierre(tipo) {
        const config = configuracionCierre(tipo);
        const recursos = estado.recursosCierre[tipo];
        config.contador.textContent = recursos.length === 1
            ? '1 seleccionada'
            : recursos.length + ' seleccionadas';

        if (!recursos.length) {
            config.seleccion.innerHTML = `
                <div class="mact-close-resource-empty">
                    Aún no seleccionas ${escapar(config.plural)}.
                </div>
            `;
            return;
        }

        config.seleccion.innerHTML = recursos.map((recurso) => `
            <div class="mact-close-resource-chip">
                <span>
                    <strong>${escapar(texto(recurso.nombre, 'Recurso'))}</strong>
                    ${recurso.codigo ? `<small>${escapar(recurso.codigo)}</small>` : ''}
                </span>
                <button
                    type="button"
                    data-cierre-quitar="${escapar(tipo)}"
                    data-recurso-id="${numero(recurso.id)}"
                    aria-label="Quitar ${escapar(recurso.nombre || 'recurso')}"
                >×</button>
            </div>
        `).join('');
    }

    function agregarOtroCierre(tipo) {
        const config = configuracionCierre(tipo);
        if (config.ninguno.checked || estado.otrosCierre[tipo].length >= 10) return;
        estado.otrosCierre[tipo].push({ clave: Date.now() + Math.random(), nombre: '' });
        pintarOtrosCierre(tipo);
        window.setTimeout(() => {
            const entradas = config.otros.querySelectorAll('input[data-otro-clave]');
            const ultima = entradas[entradas.length - 1];
            if (ultima) ultima.focus();
        }, 10);
    }

    function pintarOtrosCierre(tipo) {
        const config = configuracionCierre(tipo);
        const otros = estado.otrosCierre[tipo];

        config.otros.innerHTML = otros.map((otro, indice) => `
            <label class="mact-close-resource-other-row">
                <span>Otra ${escapar(config.singular)} ${indice + 1}</span>
                <div>
                    <input
                        type="text"
                        maxlength="150"
                        data-otro-tipo="${escapar(tipo)}"
                        data-otro-clave="${escapar(otro.clave)}"
                        value="${escapar(otro.nombre || '')}"
                        placeholder="Escribe el nombre exacto"
                    >
                    <button
                        type="button"
                        data-quitar-otro-tipo="${escapar(tipo)}"
                        data-quitar-otro-clave="${escapar(otro.clave)}"
                        aria-label="Quitar otro recurso"
                    >×</button>
                </div>
            </label>
        `).join('');
    }

    function cambiarSinRecursos(tipo, marcado) {
        const config = configuracionCierre(tipo);
        config.buscador.disabled = marcado;
        config.botonOtro.disabled = marcado;
        config.resultados.hidden = true;

        if (marcado) {
            estado.recursosCierre[tipo] = [];
            estado.otrosCierre[tipo] = [];
            config.buscador.value = '';
            pintarSeleccionRecursosCierre(tipo);
            pintarOtrosCierre(tipo);
        }
    }

    function nombresOtrosCierre(tipo) {
        return estado.otrosCierre[tipo]
            .map((otro) => String(otro.nombre || '').trim())
            .filter(Boolean);
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

    function duracion(segundos) {
        const total = Math.max(0, Math.floor(numero(segundos)));
        const horas = Math.floor(total / 3600);
        const minutos = Math.floor((total % 3600) / 60);
        const resto = total % 60;
        return [horas, minutos, resto].map((valor) => String(valor).padStart(2, '0')).join(':');
    }

    function etiquetaTipo(tipo) {
        return etiquetasTipo[tipo] || texto(tipo);
    }

    function etiquetaEstado(valor) {
        return etiquetasEstado[valor] || texto(valor);
    }

    function establecerEstado(mensaje, tipo = '') {
        const elemento = $('estadoCarga');
        elemento.textContent = mensaje;
        elemento.className = 'mact-status' + (tipo ? ' mact-status--' + tipo : '');
    }

    function leerParametros() {
        const parametros = new URLSearchParams(window.location.search);
        const ejecucionId = Number(parametros.get('ejecucion_id') || 0);
        if (Number.isInteger(ejecucionId) && ejecucionId > 0) {
            estado.ejecucionInicial = ejecucionId;
        }
    }

    async function cargarInicial(silencioso = false, ejecucionPreferida = 0, forzar = false) {
        if (estado.cargando || (estado.procesando && !forzar)) return;
        estado.cargando = true;

        const preferida = ejecucionPreferida || estado.ejecucionInicial;
        const consulta = preferida > 0
            ? '?accion=inicial&ejecucion_id=' + encodeURIComponent(preferida)
            : '?accion=inicial';

        if (!silencioso) {
            establecerEstado('Cargando actividad...');
            UI.estadoBoton($('btnActualizar'), true, 'Actualizando...');
        }

        try {
            const datos = await UI.peticionJson(ENDPOINT + consulta);
            estado.actividadActual = datos.actividad_actual || null;
            if (datos.cancelacion_reciente) {
                estado.cancelacion = datos.cancelacion_reciente;
            }
            estado.pausadas = Array.isArray(datos.pausadas) ? datos.pausadas : [];
            estado.seleccionada = datos.seleccionada || null;
            estado.participantes = Array.isArray(datos.participantes) ? datos.participantes : [];
            estado.resumen = datos.resumen || {};
            estado.servidorBase = datos.fecha_servidor
                ? new Date(String(datos.fecha_servidor).replace(' ', 'T'))
                : new Date();
            estado.clienteBase = Date.now();
            estado.ejecucionInicial = 0;

            pintarResumen();
            pintarCancelacionAdministrativa();
            pintarActividad();
            pintarPausadas();
            actualizarTemporizadores();

            establecerEstado(
                'Información actualizada ' + fecha(datos.fecha_servidor, true) + '.',
                'ok'
            );
        } catch (error) {
            establecerEstado(error.message || 'No fue posible cargar la actividad.', 'error');
            if (!silencioso) {
                await UI.error(
                    'No se cargó la actividad',
                    error.message || 'Actualiza la página e inténtalo nuevamente.'
                );
            }
        } finally {
            estado.cargando = false;
            UI.estadoBoton($('btnActualizar'), false);
        }
    }

    async function cargarDetalle(ejecucionId) {
        if (estado.procesando || estado.cargando) return;
        estado.cargando = true;
        establecerEstado('Cargando detalle...');

        try {
            const datos = await UI.peticionJson(
                ENDPOINT + '?accion=detalle&ejecucion_id=' + encodeURIComponent(ejecucionId)
            );
            estado.seleccionada = datos.ejecucion || null;
            estado.participantes = Array.isArray(datos.participantes) ? datos.participantes : [];
            estado.servidorBase = datos.fecha_servidor
                ? new Date(String(datos.fecha_servidor).replace(' ', 'T'))
                : new Date();
            estado.clienteBase = Date.now();
            pintarActividad();
            pintarPausadas();
            actualizarTemporizadores();
            establecerEstado('Detalle actualizado.', 'ok');

            const url = new URL(window.location.href);
            url.searchParams.set('ejecucion_id', String(ejecucionId));
            window.history.replaceState({}, '', url);
        } catch (error) {
            await UI.error(
                'No se abrió la actividad',
                error.message || 'La actividad cambió o ya fue cerrada.'
            );
            await cargarInicial(true);
        } finally {
            estado.cargando = false;
        }
    }

    function pintarResumen() {
        $('kpiProceso').textContent = numero(estado.resumen.en_proceso).toLocaleString('es-MX');
        $('kpiPausadas').textContent = numero(estado.resumen.pausadas).toLocaleString('es-MX');
        $('kpiReanudar').textContent = numero(estado.resumen.listas_reanudar).toLocaleString('es-MX');
        $('kpiEspera').textContent = numero(estado.resumen.esperando_urgencia).toLocaleString('es-MX');
    }

    function pintarCancelacionAdministrativa() {
        const aviso = $('avisoCancelacionAdministrativa');
        const registro = estado.cancelacion;
        aviso.hidden = !registro;

        if (!registro) return;

        $('textoCancelacionAdministrativa').textContent =
            texto(registro.folio, 'El mantenimiento')
            + ' · '
            + texto(registro.nombre_equipo, 'Equipo sin nombre')
            + '. Motivo: '
            + texto(registro.motivo_cancelacion, 'No se registró un motivo.');

        $('metaCancelacionAdministrativa').textContent =
            'Cancelado por '
            + texto(registro.cancelado_por, 'Administración')
            + ' · '
            + fecha(registro.fecha_cancelacion, true)
            + ' · Tiempo activo conservado: '
            + duracion(registro.total_segundos_activos);
    }

    function pintarActividad() {
        const registro = estado.seleccionada;
        const vacio = $('actividadVacia');
        const contenido = $('actividadContenido');

        if (!registro) {
            vacio.hidden = false;
            contenido.hidden = true;
            $('detalleTitulo').textContent = estado.cancelacion
                ? 'Actividad cancelada por administración'
                : 'Sin actividad seleccionada';
            $('detalleSubtitulo').textContent = estado.cancelacion
                ? 'La ejecución se cerró como cancelada. Consulta el aviso y el historial para conocer el motivo.'
                : 'No hay ejecuciones abiertas en tu cuenta.';
            $('detalleBadges').innerHTML = '';
            return;
        }

        vacio.hidden = true;
        contenido.hidden = false;

        const esUrgente = numero(registro.es_urgente) === 1;
        const pausada = registro.estado_ejecucion === 'PAUSADA';
        const puedePausar = numero(registro.puede_pausar) === 1;
        const puedeFinalizar = numero(registro.puede_finalizar) === 1;
        const puedeReanudar = numero(registro.puede_reanudar) === 1;

        $('detalleEyebrow').textContent = pausada ? 'ACTIVIDAD PAUSADA' : 'ACTIVIDAD EN PROCESO';
        $('detalleTitulo').textContent = texto(registro.folio) + ' · ' + texto(registro.nombre_equipo);
        $('detalleSubtitulo').textContent = esUrgente
            ? 'Urgencia compartida con los técnicos que aceptaron participar.'
            : 'Mantenimiento asignado por el administrador.';

        $('detalleBadges').innerHTML = [
            '<span class="mact-badge mact-badge--' + (esUrgente ? 'urgent' : 'type') + '">' + escapar(etiquetaTipo(registro.tipo_solicitud)) + '</span>',
            '<span class="mact-badge mact-badge--state-' + escapar(String(registro.estado_ejecucion).toLowerCase()) + '">' + escapar(etiquetaEstado(registro.estado_ejecucion)) + '</span>',
            '<span class="mact-badge mact-badge--risk-' + escapar(String(registro.nivel_riesgo || 'BAJO').toLowerCase()) + '">Riesgo ' + escapar(texto(registro.nivel_riesgo, 'BAJO').toLowerCase()) + '</span>'
        ].join('');

        $('detalleHero').classList.toggle('mact-hero--urgent', esUrgente);
        $('detalleFolio').textContent = texto(registro.folio);
        $('detalleEquipo').textContent = texto(registro.nombre_equipo);
        $('detalleUbicacion').textContent = [
            texto(registro.codigo_equipo, 'Sin código'),
            texto(registro.departamento),
            texto(registro.area),
            texto(registro.proceso)
        ].join(' · ');

        $('estadoEnVivo').className = 'mact-live-state ' + (pausada ? 'is-paused' : 'is-active');
        $('textoEstadoEnVivo').textContent = pausada ? 'Tiempo en pausa' : 'Tiempo corriendo';
        $('fechaInicio').textContent = fecha(registro.fecha_hora_inicio, true);
        $('textoInicio').textContent = registro.fecha_ultima_reanudacion
            ? 'Última reanudación: ' + fecha(registro.fecha_ultima_reanudacion, true)
            : 'Hora de inicio registrada';

        $('detalleTipo').textContent = etiquetaTipo(registro.tipo_solicitud);
        $('detallePrioridad').textContent = 'Prioridad ' + texto(registro.prioridad).toLowerCase();
        $('detalleSolicitante').textContent = texto(registro.solicitante);
        $('detalleSolicitudFecha').textContent = 'Solicitado el ' + fecha(registro.fecha_solicitud);

        if (registro.fecha_programada) {
            $('detalleProgramacion').textContent = fecha(registro.fecha_programada);
            $('detalleLimite').textContent = 'Fecha límite: ' + fecha(registro.fecha_limite);
        } else {
            $('detalleProgramacion').textContent = esUrgente ? 'Atención inmediata' : 'Sin programación';
            $('detalleLimite').textContent = esUrgente ? 'No utiliza fecha límite' : 'Consulta con el administrador';
        }

        $('detalleRiesgo').textContent = texto(registro.nivel_riesgo, 'BAJO');
        const seguridad = [];
        if (numero(registro.trabajo_peligroso) === 1) {
            seguridad.push(
                texto(
                    registro.detalle_trabajo_peligroso,
                    'Trabajo peligroso; verifica las condiciones de seguridad'
                )
            );
        }
        if (numero(registro.requiere_paro_equipo) === 1) seguridad.push('Requiere paro de equipo');
        if (numero(registro.alerta_riesgo_nocturno) === 1) {
            seguridad.push(
                numero(registro.riesgo_nocturno_confirmado) === 1
                    ? 'Riesgo nocturno confirmado'
                    : 'Alerta de riesgo nocturno pendiente'
            );
        }
        $('detalleSeguridad').textContent = seguridad.length ? seguridad.join(' · ') : 'Sin condiciones especiales registradas';

        const descripcionSolicitud = [
            texto(registro.descripcion_solicitud, ''),
            texto(registro.observaciones_solicitante, '')
                ? 'Observaciones del solicitante: ' + texto(registro.observaciones_solicitante, '')
                : ''
        ].filter(Boolean).join('\n\n');
        $('detalleDescripcion').textContent = descripcionSolicitud || 'Sin descripción registrada.';

        const falla = [
            texto(registro.tipo_falla, '') ? 'Tipo de falla: ' + texto(registro.tipo_falla, '') : '',
            texto(registro.causa_averia, '') ? 'Causa registrada: ' + texto(registro.causa_averia, '') : '',
            texto(registro.descripcion_falla, ''),
            texto(registro.causa_desconocida_descripcion, '')
        ].filter(Boolean).join('\n\n');
        $('detalleFalla').textContent = falla || 'No se registró una descripción adicional de falla.';
        $('detalleImpacto').textContent = texto(registro.impacto_operacion, 'No se registró impacto operativo.');

        const objetivo = [
            registro.objetivo_mejora,
            registro.resultado_esperado,
            registro.justificacion_mejora
        ]
            .map((valor) => texto(valor, ''))
            .filter(Boolean)
            .join('\n\n');
        $('detalleObjetivo').textContent = objetivo || 'No aplica para este tipo de mantenimiento.';
        $('bloqueObjetivo').hidden = !objetivo;
        $('bloqueFalla').hidden = !texto(registro.descripcion_falla, '');
        $('bloqueImpacto').hidden = !texto(registro.impacto_operacion, '');

        pintarAvisoPausa(registro);
        $('avisoUrgente').hidden = !esUrgente || pausada;
        pintarRecursosRecomendados(registro);
        pintarParticipantes();

        $('btnPausar').hidden = !puedePausar;
        $('btnPausar').dataset.ejecucionId = String(registro.ejecucion_id);
        $('btnReanudarPrincipal').hidden = !pausada;
        $('btnReanudarPrincipal').disabled = !puedeReanudar;
        $('btnReanudarPrincipal').dataset.ejecucionId = String(registro.ejecucion_id);
        $('btnReanudarPrincipal').title = puedeReanudar
            ? 'Reanudar mantenimiento'
            : texto(registro.bloqueo_reanudacion, 'No disponible');
        $('btnFinalizar').hidden = !puedeFinalizar;
        $('btnFinalizar').dataset.ejecucionId = String(registro.ejecucion_id);
        $('btnFinalizar').textContent = esUrgente
            ? 'Finalizar urgencia para todos'
            : 'Finalizar mantenimiento';
    }

    function pintarAvisoPausa(registro) {
        const aviso = $('avisoPausa');

        if (registro.estado_ejecucion !== 'PAUSADA') {
            aviso.hidden = true;
            return;
        }

        aviso.hidden = false;
        const motivo = texto(registro.motivo_pausa, 'OTRO');
        $('avisoPausaTitulo').textContent = etiquetasPausa[motivo] || etiquetaEstado(motivo);

        if (motivo === 'URGENCIA') {
            const estadoUrgencia = texto(registro.estado_urgencia_origen, 'SIN ESTADO');
            const base = 'La actividad fue pausada para atender la urgencia '
                + texto(registro.folio_urgencia_origen) + ' en '
                + texto(registro.equipo_urgencia_origen) + '. ';

            $('avisoPausaTexto').textContent = numero(registro.puede_reanudar) === 1
                ? base + 'La urgencia ya terminó o fue cancelada. Reanuda cuando realmente estés listo.'
                : base + 'Estado actual: ' + etiquetaEstado(estadoUrgencia) + '. Debes esperar a que se cierre.';
        } else {
            $('avisoPausaTexto').textContent = texto(
                registro.observaciones_pausa,
                'La actividad permanecerá pausada hasta que se reanude manualmente.'
            ) + (registro.bloqueo_reanudacion ? ' ' + registro.bloqueo_reanudacion : '');
        }
    }

    function pintarParticipantes() {
        const lista = $('listaParticipantes');
        $('contadorParticipantes').textContent = estado.participantes.length === 1
            ? '1 participante'
            : estado.participantes.length + ' participantes';

        if (!estado.participantes.length) {
            lista.innerHTML = '<div class="mact-inline-empty">No se encontraron participantes activos.</div>';
            return;
        }

        lista.innerHTML = estado.participantes.map((participante) => {
            const actual = numero(participante.es_tecnico_actual) === 1;
            const detalle = [
                texto(participante.turno, '').toLowerCase(),
                texto(participante.especialidad, '')
            ].filter(Boolean).join(' · ');
            const tiempo = participante.ejecucion_id
                ? duracion(participante.segundos_activos_actuales)
                : 'Sin iniciar';

            return `
                <article class="mact-participant${actual ? ' is-current' : ''}">
                    <span class="mact-participant__avatar" aria-hidden="true">${escapar(texto(participante.tecnico, 'T').charAt(0).toUpperCase())}</span>
                    <div>
                        <strong>${escapar(texto(participante.tecnico))}${actual ? ' · Tú' : ''}</strong>
                        <small>${escapar(detalle || 'Técnico')}</small>
                    </div>
                    <div class="mact-participant__state">
                        <span>${escapar(etiquetaEstado(participante.estado_participacion))}</span>
                        <small>${escapar(tiempo)}</small>
                    </div>
                </article>
            `;
        }).join('');
    }

    function pintarPausadas() {
        const lista = $('listaPausadas');
        const vacio = $('pausadasVacias');
        $('contadorPausadas').textContent = estado.pausadas.length === 1
            ? '1 actividad'
            : estado.pausadas.length + ' actividades';

        if (!estado.pausadas.length) {
            lista.innerHTML = '';
            vacio.hidden = false;
            return;
        }

        vacio.hidden = true;
        lista.innerHTML = estado.pausadas.map((registro) => {
            const seleccionada = estado.seleccionada
                && numero(estado.seleccionada.ejecucion_id) === numero(registro.ejecucion_id);
            const puedeReanudar = numero(registro.puede_reanudar) === 1;
            const espera = numero(registro.espera_urgencia) === 1;
            let estadoTexto = 'Pausado';
            let clase = 'blocked';

            if (puedeReanudar) {
                estadoTexto = 'Listo para reanudar';
                clase = 'ready';
            } else if (espera) {
                estadoTexto = 'Esperando cierre de urgencia';
                clase = 'waiting';
            }

            return `
                <article class="mact-paused-item${seleccionada ? ' is-selected' : ''}" data-ejecucion-id="${numero(registro.ejecucion_id)}">
                    <button type="button" class="mact-paused-item__main" data-action="seleccionar" data-ejecucion-id="${numero(registro.ejecucion_id)}">
                        <span class="mact-paused-item__icon" aria-hidden="true">‖</span>
                        <span class="mact-paused-item__copy">
                            <small>${escapar(texto(registro.folio))} · ${escapar(etiquetaTipo(registro.tipo_solicitud))}</small>
                            <strong>${escapar(texto(registro.nombre_equipo))}</strong>
                            <em>${escapar(etiquetasPausa[registro.motivo_pausa] || 'Actividad pausada')}</em>
                        </span>
                        <span class="mact-paused-item__time">
                            <small>En pausa</small>
                            <strong data-pause-clock="${numero(registro.ejecucion_id)}">${escapar(duracion(registro.segundos_pausa_actuales))}</strong>
                        </span>
                    </button>
                    <footer>
                        <span class="mact-readiness mact-readiness--${clase}">${escapar(estadoTexto)}</span>
                        ${puedeReanudar
                            ? `<button type="button" class="mact-mini-btn" data-action="reanudar" data-ejecucion-id="${numero(registro.ejecucion_id)}">Reanudar</button>`
                            : `<small>${escapar(texto(registro.bloqueo_reanudacion, 'No disponible por ahora.'))}</small>`}
                    </footer>
                </article>
            `;
        }).join('');
    }

    function actualizarTemporizadores() {
        const registro = estado.seleccionada;
        if (!registro) return;

        const transcurrido = Math.max(0, Math.floor((Date.now() - estado.clienteBase) / 1000));
        const activos = numero(registro.segundos_activos_actuales)
            + (registro.estado_ejecucion === 'EN_PROCESO' ? transcurrido : 0);
        const pausas = numero(registro.segundos_pausa_actuales)
            + (registro.estado_ejecucion === 'PAUSADA' ? transcurrido : 0);

        $('tiempoActivo').textContent = duracion(activos);
        $('tiempoPausa').textContent = duracion(pausas);

        document.querySelectorAll('[data-pause-clock]').forEach((elemento) => {
            const id = numero(elemento.dataset.pauseClock);
            const pausada = estado.pausadas.find((fila) => numero(fila.ejecucion_id) === id);
            if (!pausada) return;
            elemento.textContent = duracion(numero(pausada.segundos_pausa_actuales) + transcurrido);
        });
    }

    function abrirModal(nombre) {
        const modal = nombre === 'pausa' ? $('modalPausa') : $('modalFinalizar');
        estado.modalAbierto = nombre;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('mact-modal-open');
        const enfoque = modal.querySelector('textarea, input:not([type="hidden"]), button');
        window.setTimeout(() => enfoque && enfoque.focus(), 40);
    }

    function cerrarModal(nombre) {
        const modal = nombre === 'pausa' ? $('modalPausa') : $('modalFinalizar');
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        estado.modalAbierto = '';
        document.body.classList.remove('mact-modal-open');
    }

    function abrirPausa() {
        const registro = estado.seleccionada;
        if (!registro || numero(registro.puede_pausar) !== 1) return;
        $('formPausa').reset();
        $('pausaEjecucionId').value = String(registro.ejecucion_id);
        $('contadorMotivo').textContent = '0';
        abrirModal('pausa');
    }

    function abrirFinalizar() {
        const registro = estado.seleccionada;
        if (!registro || numero(registro.puede_finalizar) !== 1) return;
        const urgente = numero(registro.es_urgente) === 1;

        $('formFinalizar').reset();
        $('finalizarEjecucionId').value = String(registro.ejecucion_id);
        $('campoQueFalto').hidden = true;
        $('queFalto').required = false;
        $('contadorDescripcion').textContent = '0';
        $('contadorFalto').textContent = '0';
        $('contadorObservaciones').textContent = '0';
        reiniciarRecursosCierre();
        $('advertenciaCierreUrgente').hidden = !urgente;
        $('tituloModalFinalizar').textContent = urgente
            ? 'Finalizar urgencia para todos'
            : 'Finalizar mantenimiento';
        $('textoModalFinalizar').textContent = urgente
            ? 'Tu cierre terminará las participaciones activas de esta urgencia.'
            : 'Tu cierre terminará la solicitud para todo el equipo participante.';
        $('btnConfirmarFinalizar').textContent = urgente
            ? 'Finalizar urgencia'
            : 'Finalizar mantenimiento';
        abrirModal('finalizar');
    }

    async function confirmarReanudacion(ejecucionId) {
        const registro = estado.pausadas.find((fila) => numero(fila.ejecucion_id) === numero(ejecucionId));
        if (!registro || numero(registro.puede_reanudar) !== 1) {
            await UI.advertencia(
                'No se puede reanudar',
                registro ? texto(registro.bloqueo_reanudacion) : 'La actividad ya no está disponible.'
            );
            return;
        }

        const confirmado = await UI.confirmar({
            titulo: '¿Reanudar mantenimiento?',
            texto: 'El tiempo activo volverá a correr para ' + texto(registro.folio)
                + '. Verifica que ya estés listo para continuar.',
            textoConfirmar: 'Sí, reanudar',
            textoCancelar: 'Todavía no',
            icono: 'question'
        });

        if (!confirmado) return;

        const datos = new FormData();
        datos.set('accion', 'reanudar');
        datos.set('ejecucion_id', String(ejecucionId));
        await ejecutarAccion(datos, 'Reanudando...', 'Mantenimiento reanudado');
    }

    async function ejecutarAccion(formData, textoCarga, tituloExito) {
        if (estado.procesando) return;
        estado.procesando = true;
        bloquearAcciones(true);

        try {
            const datos = await UI.peticionJson(ENDPOINT, {
                method: 'POST',
                body: formData
            });

            if (estado.modalAbierto) cerrarModal(estado.modalAbierto);
            await UI.exito(tituloExito, datos.mensaje || 'La operación se completó correctamente.');

            const preferida = numero(datos.ejecucion_id);
            await cargarInicial(true, preferida, true);
        } catch (error) {
            await UI.error(
                'No se completó la operación',
                error.message || 'Actualiza la pantalla e inténtalo nuevamente.'
            );
            await cargarInicial(true, 0, true);
        } finally {
            estado.procesando = false;
            bloquearAcciones(false);
            pintarActividad();
            pintarPausadas();
        }
    }

    function bloquearAcciones(bloquear) {
        document.querySelectorAll('button, textarea, input').forEach((elemento) => {
            if (elemento.closest('.sm-topbar') || elemento.closest('.sm-sidebar')) return;
            elemento.disabled = bloquear;
        });
        UI.estadoBoton($('btnConfirmarPausa'), bloquear, 'Guardando...');
        UI.estadoBoton($('btnConfirmarFinalizar'), bloquear, 'Finalizando...');
    }

    function validarFormularioFinal(formulario) {
        if (!(formulario instanceof HTMLFormElement)) {
            return {
                valido: false,
                mensaje: 'No fue posible leer el formulario de cierre. Cierra la ventana y vuelve a intentarlo.'
            };
        }

        const trabajo = formulario.querySelector('input[name="trabajo_quedo"]:checked');
        const limpieza = formulario.querySelector('input[name="realizo_limpieza_area"]:checked');
        const orden = formulario.querySelector('input[name="area_ordenada_libre_componentes"]:checked');
        const descripcion = $('descripcionTrabajo').value.trim();
        const queFalto = $('queFalto').value.trim();
        const observaciones = $('observacionesCierre').value.trim();

        if (!trabajo) return { valido: false, mensaje: 'Selecciona cómo quedó el trabajo.' };
        if (descripcion.length < 20) return { valido: false, mensaje: 'Describe el trabajo realizado con al menos 20 caracteres.' };
        if (descripcion.length > 4000) return { valido: false, mensaje: 'La descripción del trabajo supera 4000 caracteres.' };
        if (trabajo.value !== 'TERMINADO' && queFalto.length < 10) {
            return { valido: false, mensaje: 'Indica qué falta por realizar.' };
        }
        if (queFalto.length > 2500) return { valido: false, mensaje: 'La explicación de pendientes supera 2500 caracteres.' };
        if (!limpieza) return { valido: false, mensaje: 'Indica si se realizó limpieza del área.' };
        if (!orden) return { valido: false, mensaje: 'Indica si el área quedó ordenada.' };
        if (observaciones.length > 2000) return { valido: false, mensaje: 'Las observaciones superan 2000 caracteres.' };

        for (const tipo of ['HERRAMIENTA', 'REFACCION']) {
            const config = configuracionCierre(tipo);
            const seleccionados = estado.recursosCierre[tipo];
            const otros = nombresOtrosCierre(tipo);
            const sinRecursos = config.ninguno.checked;

            if (sinRecursos && (seleccionados.length > 0 || otros.length > 0)) {
                return {
                    valido: false,
                    mensaje: 'No puedes marcar que no utilizaste ' + config.plural + ' y registrar elementos al mismo tiempo.'
                };
            }

            if (!sinRecursos && seleccionados.length === 0 && otros.length === 0) {
                return {
                    valido: false,
                    mensaje: 'Registra las ' + config.plural + ' realmente utilizadas o confirma que no se utilizó ninguna.'
                };
            }

            if (otros.some((nombre) => nombre.length < 2 || nombre.length > 150)) {
                return {
                    valido: false,
                    mensaje: 'Cada elemento registrado como “Otro” debe contener entre 2 y 150 caracteres.'
                };
            }
        }

        return { valido: true };
    }

    function obtenerFormularioEvento(evento, formularioEsperado) {
        const formulario = evento.target;

        if (
            !(formulario instanceof HTMLFormElement)
            || formulario !== formularioEsperado
        ) {
            return null;
        }

        return formulario;
    }

    $('btnActualizar').addEventListener('click', () => cargarInicial(false));
    $('btnPausar').addEventListener('click', abrirPausa);
    $('btnFinalizar').addEventListener('click', abrirFinalizar);
    $('btnReanudarPrincipal').addEventListener('click', () => {
        const id = numero($('btnReanudarPrincipal').dataset.ejecucionId);
        if (id > 0) confirmarReanudacion(id);
    });

    $('listaPausadas').addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-action]');
        if (!boton) return;
        const id = numero(boton.dataset.ejecucionId);
        if (boton.dataset.action === 'seleccionar') cargarDetalle(id);
        if (boton.dataset.action === 'reanudar') confirmarReanudacion(id);
    });

    document.querySelectorAll('[data-close-modal]').forEach((boton) => {
        boton.addEventListener('click', () => cerrarModal(boton.dataset.closeModal));
    });

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && estado.modalAbierto && !estado.procesando) {
            cerrarModal(estado.modalAbierto);
        }
    });

    const formularioPausa = $('formPausa');
    const formularioFinalizar = $('formFinalizar');

    formularioPausa.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        const formulario = obtenerFormularioEvento(evento, formularioPausa);
        if (!formulario) {
            await UI.error(
                'No se pudo leer el formulario',
                'Cierra la ventana de pausa, vuelve a abrirla e inténtalo nuevamente.'
            );
            return;
        }

        const ejecucionId = numero($('pausaEjecucionId').value);
        const motivo = $('motivoPausa').value.trim();

        if (ejecucionId <= 0) {
            await UI.advertencia(
                'Actividad no disponible',
                'Actualiza la pantalla y vuelve a seleccionar la actividad que deseas pausar.'
            );
            return;
        }

        if (motivo.length < 10) {
            await UI.advertencia('Falta el motivo', 'Escribe un motivo claro de al menos 10 caracteres.');
            $('motivoPausa').focus();
            return;
        }

        if (motivo.length > 500) {
            await UI.advertencia('Motivo demasiado largo', 'El motivo puede contener como máximo 500 caracteres.');
            $('motivoPausa').focus();
            return;
        }

        const confirmado = await UI.confirmar({
            titulo: '¿Pausar esta actividad?',
            texto: 'El tiempo activo se detendrá. La reanudación será manual.',
            textoConfirmar: 'Sí, pausar',
            textoCancelar: 'Cancelar',
            icono: 'warning'
        });
        if (!confirmado) return;

        /*
         * No se usa evento.currentTarget después del await anterior.
         * Los navegadores limpian currentTarget cuando termina la parte síncrona
         * del evento y eso provocaba que FormData recibiera null.
         */
        const datos = new FormData(formulario);
        datos.set('accion', 'pausar');
        datos.set('ejecucion_id', String(ejecucionId));
        datos.set('motivo', motivo);

        await ejecutarAccion(datos, 'Pausando...', 'Actividad pausada');
    });

    formularioFinalizar.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        const formulario = obtenerFormularioEvento(evento, formularioFinalizar);
        if (!formulario) {
            await UI.error(
                'No se pudo leer el formulario',
                'Cierra la ventana de cierre, vuelve a abrirla e inténtalo nuevamente.'
            );
            return;
        }

        const ejecucionId = numero($('finalizarEjecucionId').value);
        if (ejecucionId <= 0) {
            await UI.advertencia(
                'Actividad no disponible',
                'Actualiza la pantalla y vuelve a seleccionar la actividad que deseas finalizar.'
            );
            return;
        }

        const validacion = validarFormularioFinal(formulario);
        if (!validacion.valido) {
            await UI.advertencia('Revisa el cierre', validacion.mensaje);
            return;
        }

        const urgente = estado.seleccionada && numero(estado.seleccionada.es_urgente) === 1;
        const confirmado = await UI.confirmar({
            titulo: urgente ? '¿Finalizar la urgencia para todos?' : '¿Finalizar el mantenimiento?',
            texto: urgente
                ? 'Se cerrarán las participaciones de todos los técnicos. Los trabajos anteriores seguirán pausados hasta que cada técnico los reanude manualmente.'
                : 'La solicitud quedará terminada para todo el equipo participante. Esta acción no debe usarse si el trabajo todavía continúa.',
            textoConfirmar: urgente ? 'Sí, finalizar urgencia' : 'Sí, finalizar',
            textoCancelar: 'Revisar datos',
            icono: 'warning'
        });
        if (!confirmado) return;

        const datos = new FormData(formulario);
        datos.set('accion', 'finalizar');
        datos.set('ejecucion_id', String(ejecucionId));
        datos.set('sin_herramientas_utilizadas', $('sinHerramientasCierre').checked ? '1' : '0');
        datos.set('sin_refacciones_utilizadas', $('sinRefaccionesCierre').checked ? '1' : '0');

        estado.recursosCierre.HERRAMIENTA.forEach((recurso) => {
            datos.append('herramientas_ids[]', String(numero(recurso.id)));
        });
        estado.recursosCierre.REFACCION.forEach((recurso) => {
            datos.append('refacciones_ids[]', String(numero(recurso.id)));
        });
        nombresOtrosCierre('HERRAMIENTA').forEach((nombre) => {
            datos.append('herramientas_otras[]', nombre);
        });
        nombresOtrosCierre('REFACCION').forEach((nombre) => {
            datos.append('refacciones_otras[]', nombre);
        });

        await ejecutarAccion(
            datos,
            'Finalizando...',
            urgente ? 'Urgencia finalizada' : 'Mantenimiento finalizado'
        );
    });

    ['HERRAMIENTA', 'REFACCION'].forEach((tipo) => {
        const config = configuracionCierre(tipo);

        config.buscador.addEventListener('focus', () => buscarRecursosCierre(tipo));
        config.buscador.addEventListener('input', () => {
            window.clearTimeout(estado.temporizadoresBusquedaCierre[tipo]);
            estado.temporizadoresBusquedaCierre[tipo] = window.setTimeout(
                () => buscarRecursosCierre(tipo),
                260
            );
        });

        config.botonOtro.addEventListener('click', () => agregarOtroCierre(tipo));
        config.ninguno.addEventListener('change', () => cambiarSinRecursos(tipo, config.ninguno.checked));

        config.resultados.addEventListener('click', (evento) => {
            const boton = evento.target.closest('[data-cierre-agregar]');
            if (!boton) return;
            agregarRecursoCierre(tipo, {
                id: numero(boton.dataset.recursoId),
                tipo_recurso: tipo,
                nombre: boton.dataset.recursoNombre || '',
                codigo: boton.dataset.recursoCodigo || '',
                descripcion: boton.dataset.recursoDescripcion || ''
            });
        });

        config.seleccion.addEventListener('click', (evento) => {
            const boton = evento.target.closest('[data-cierre-quitar]');
            if (!boton) return;
            quitarRecursoCierre(tipo, numero(boton.dataset.recursoId));
        });

        config.otros.addEventListener('input', (evento) => {
            const entrada = evento.target.closest('input[data-otro-clave]');
            if (!entrada) return;
            const clave = String(entrada.dataset.otroClave || '');
            const registro = estado.otrosCierre[tipo].find((otro) => String(otro.clave) === clave);
            if (registro) registro.nombre = entrada.value;
        });

        config.otros.addEventListener('click', (evento) => {
            const boton = evento.target.closest('[data-quitar-otro-clave]');
            if (!boton) return;
            const clave = String(boton.dataset.quitarOtroClave || '');
            estado.otrosCierre[tipo] = estado.otrosCierre[tipo].filter(
                (otro) => String(otro.clave) !== clave
            );
            pintarOtrosCierre(tipo);
        });
    });

    document.addEventListener('click', (evento) => {
        if (!evento.target.closest('[data-cierre-picker]')) {
            ['HERRAMIENTA', 'REFACCION'].forEach((tipo) => {
                configuracionCierre(tipo).resultados.hidden = true;
            });
        }
    });

    document.querySelectorAll('input[name="trabajo_quedo"]').forEach((input) => {
        input.addEventListener('change', () => {
            const necesita = input.checked && input.value !== 'TERMINADO';
            $('campoQueFalto').hidden = !necesita;
            $('queFalto').required = necesita;
            if (!necesita) $('queFalto').value = '';
        });
    });

    const contadores = [
        ['motivoPausa', 'contadorMotivo'],
        ['descripcionTrabajo', 'contadorDescripcion'],
        ['queFalto', 'contadorFalto'],
        ['observacionesCierre', 'contadorObservaciones']
    ];

    contadores.forEach(([campo, contador]) => {
        $(campo).addEventListener('input', () => {
            $(contador).textContent = String($(campo).value.length);
        });
    });

    leerParametros();
    cargarInicial(false);
    estado.temporizador = window.setInterval(actualizarTemporizadores, 1000);
    estado.refresco = window.setInterval(() => {
        if (!estado.modalAbierto && !document.hidden && !estado.procesando) {
            const seleccion = estado.seleccionada
                ? numero(estado.seleccionada.ejecucion_id)
                : (estado.cancelacion ? numero(estado.cancelacion.ejecucion_id) : 0);
            cargarInicial(true, seleccion);
        }
    }, 20000);
})();
</script>
</body>
</html>