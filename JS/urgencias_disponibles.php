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

$cssUrgencias = __DIR__ . '/../css/style_urgencias_disponibles.css';
$versionCss = is_file($cssUrgencias)
    ? (string) filemtime($cssUrgencias)
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
        content="Centro de respuesta de urgencias para técnicos del Sistema de Mantenimiento"
    >
    <title>Urgencias disponibles | Sistema de Mantenimiento</title>
    <link
        rel="stylesheet"
        href="../css/style_urgencias_disponibles.css?v=<?= htmlspecialchars($versionCss, ENT_QUOTES, 'UTF-8') ?>"
    >
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="urg-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="urg-icon-siren" viewBox="0 0 24 24">
        <path d="M7 16V9a5 5 0 0 1 10 0v7"/>
        <path d="M5 16h14v4H5zM12 2V0M4.9 4.9 3.5 3.5M19.1 4.9l1.4-1.4M2 11H0M24 11h-2"/>
    </symbol>
    <symbol id="urg-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="urg-icon-bolt" viewBox="0 0 24 24">
        <path d="m13 2-9 12h7l-1 8 9-12h-7l1-8Z"/>
    </symbol>
    <symbol id="urg-icon-shield" viewBox="0 0 24 24">
        <path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="urg-icon-user-check" viewBox="0 0 24 24">
        <circle cx="9" cy="8" r="4"/>
        <path d="M2 21a7 7 0 0 1 14 0M16 11l2 2 4-4"/>
    </symbol>
    <symbol id="urg-icon-activity" viewBox="0 0 24 24">
        <path d="M3 12h4l2.5-6 5 12 2.5-6H21"/>
    </symbol>
    <symbol id="urg-icon-alert" viewBox="0 0 24 24">
        <path d="M12 3 2 21h20L12 3Z"/>
        <path d="M12 9v5M12 18h.01"/>
    </symbol>
    <symbol id="urg-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="urg-icon-filter" viewBox="0 0 24 24">
        <path d="M4 6h16M7 12h10M10 18h4"/>
    </symbol>
    <symbol id="urg-icon-users" viewBox="0 0 24 24">
        <circle cx="9" cy="8" r="3"/>
        <circle cx="17" cy="9" r="2.5"/>
        <path d="M3 20a6 6 0 0 1 12 0M14 19a5 5 0 0 1 8 0"/>
    </symbol>
    <symbol id="urg-icon-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </symbol>
    <symbol id="urg-icon-arrow" viewBox="0 0 24 24">
        <path d="M5 12h14M14 7l5 5-5 5"/>
    </symbol>
    <symbol id="urg-icon-check" viewBox="0 0 24 24">
        <path d="m5 12 4 4L19 6"/>
    </symbol>
    <symbol id="urg-icon-eye" viewBox="0 0 24 24">
        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/>
        <circle cx="12" cy="12" r="3"/>
    </symbol>
    <symbol id="urg-icon-location" viewBox="0 0 24 24">
        <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/>
        <circle cx="12" cy="10" r="2.5"/>
    </symbol>
</svg>

<main class="urg-page">
    <div class="urg-ambient urg-ambient--one" aria-hidden="true"></div>
    <div class="urg-ambient urg-ambient--two" aria-hidden="true"></div>

    <section class="urg-heading urg-hero" aria-labelledby="tituloUrgencias">
        <div class="urg-hero__pattern" aria-hidden="true"></div>

        <div class="urg-hero__content">
            <div class="urg-hero__copy">
                <p class="urg-eyebrow">
                    <span class="urg-eyebrow__icon" aria-hidden="true">
                        <svg><use href="#urg-icon-siren"></use></svg>
                    </span>
                    Atención inmediata
                </p>

                <h1 id="tituloUrgencias">Urgencias disponibles</h1>

                <p class="urg-hero__description">
                    Revisa la información, reserva tu lugar y comienza una participación
                    urgente cuando estés preparado para responder.
                </p>

                <div class="urg-hero__meta">
                    <span>
                        <span class="urg-live-dot" aria-hidden="true"></span>
                        Canal urgente activo
                    </span>
                    <span>
                        Técnico conectado: <strong><?= htmlspecialchars($nombreTecnico, ENT_QUOTES, 'UTF-8') ?></strong>
                    </span>
                    <span>Actualización automática cada <strong>20 segundos</strong></span>
                </div>
            </div>

            <div class="urg-hero__actions">
                <div class="urg-hero__mini-card">
                    <span class="urg-hero__mini-icon" aria-hidden="true">
                        <svg><use href="#urg-icon-bolt"></use></svg>
                    </span>
                    <div>
                        <small>Centro de respuesta</small>
                        <strong>Aceptación e inicio en tiempo real</strong>
                    </div>
                </div>

                <button type="button" class="urg-btn urg-btn--hero" id="btnActualizar">
                    <svg aria-hidden="true"><use href="#urg-icon-refresh"></use></svg>
                    <span>Actualizar información</span>
                </button>
            </div>
        </div>
    </section>

    <section class="urg-guides" aria-label="Reglas principales de urgencias">
        <article>
            <span class="urg-guide-icon urg-guide-icon--danger" aria-hidden="true">
                <svg><use href="#urg-icon-siren"></use></svg>
            </span>
            <div>
                <strong>Publicación inmediata</strong>
                <p>Las urgencias aparecen para los técnicos desde su registro, sin esperar validación administrativa.</p>
            </div>
        </article>

        <article>
            <span class="urg-guide-icon" aria-hidden="true">
                <svg><use href="#urg-icon-user-check"></use></svg>
            </span>
            <div>
                <strong>Un compromiso urgente</strong>
                <p>Solo puedes mantener una urgencia aceptada o activa a la vez para evitar participaciones cruzadas.</p>
            </div>
        </article>

        <article>
            <span class="urg-guide-icon urg-guide-icon--warning" aria-hidden="true">
                <svg><use href="#urg-icon-refresh"></use></svg>
            </span>
            <div>
                <strong>Reanudación manual</strong>
                <p>El mantenimiento pausado por una urgencia continuará detenido hasta que tú decidas reanudarlo.</p>
            </div>
        </article>
    </section>

    <section class="urg-rule" aria-label="Regla de publicación de urgencias">
        <span class="urg-rule__icon" aria-hidden="true">
            <svg><use href="#urg-icon-alert"></use></svg>
        </span>
        <div>
            <strong>Las urgencias se publican inmediatamente</strong>
            <p>No necesitan validación administrativa para que los técnicos puedan verlas, aceptarlas o iniciarlas.</p>
        </div>
        <span class="urg-rule__tag">Respuesta directa</span>
    </section>

    <section class="urg-notice urg-notice--warning" id="avisoCompromiso" hidden aria-live="polite">
        <span class="urg-notice__icon" aria-hidden="true">
            <svg><use href="#urg-icon-user-check"></use></svg>
        </span>
        <div>
            <strong id="avisoCompromisoTitulo">Ya tienes una urgencia aceptada</strong>
            <p id="avisoCompromisoTexto"></p>
        </div>
        <button type="button" class="urg-link-button" id="btnAbrirCompromiso">
            Abrir urgencia
            <svg aria-hidden="true"><use href="#urg-icon-arrow"></use></svg>
        </button>
    </section>

    <section class="urg-notice urg-notice--info" id="avisoActividad" hidden aria-live="polite">
        <span class="urg-notice__icon" aria-hidden="true">
            <svg><use href="#urg-icon-activity"></use></svg>
        </span>
        <div>
            <strong id="avisoActividadTitulo">Tienes un mantenimiento en proceso</strong>
            <p id="avisoActividadTexto"></p>
        </div>
    </section>

    <section class="urg-kpis" aria-label="Resumen de urgencias">
        <article class="urg-kpi urg-kpi--danger">
            <span class="urg-kpi__icon" aria-hidden="true">
                <svg><use href="#urg-icon-siren"></use></svg>
            </span>
            <span>Disponibles para aceptar</span>
            <strong id="kpiDisponibles">0</strong>
            <small>Con lugares libres</small>
        </article>

        <article class="urg-kpi urg-kpi--warning">
            <span class="urg-kpi__icon" aria-hidden="true">
                <svg><use href="#urg-icon-clock"></use></svg>
            </span>
            <span>Mis urgencias aceptadas</span>
            <strong id="kpiAceptadas">0</strong>
            <small>Pendientes de iniciar</small>
        </article>

        <article class="urg-kpi urg-kpi--success">
            <span class="urg-kpi__icon" aria-hidden="true">
                <svg><use href="#urg-icon-activity"></use></svg>
            </span>
            <span>Mis participaciones activas</span>
            <strong id="kpiActivas">0</strong>
            <small>En proceso o pausadas</small>
        </article>

        <article class="urg-kpi urg-kpi--risk">
            <span class="urg-kpi__icon" aria-hidden="true">
                <svg><use href="#urg-icon-shield"></use></svg>
            </span>
            <span>Riesgo alto</span>
            <strong id="kpiRiesgo">0</strong>
            <small>Requieren atención especial</small>
        </article>
    </section>

    <section class="urg-card urg-card--toolbar">
        <header class="urg-toolbar__head">
            <div class="urg-section-heading">
                <span class="urg-section-heading__icon" aria-hidden="true">
                    <svg><use href="#urg-icon-filter"></use></svg>
                </span>
                <div>
                    <p class="urg-eyebrow">Consulta operativa</p>
                    <h2>Filtrar urgencias</h2>
                    <p>Selecciona la vista y busca por folio, equipo, ubicación o descripción.</p>
                </div>
            </div>
            <span class="urg-toolbar__badge">Actualización en tiempo real</span>
        </header>

        <div class="urg-toolbar__body">
            <div class="urg-filters" role="group" aria-label="Filtrar urgencias">
                <button type="button" class="urg-filter is-active" data-filter="disponibles">Disponibles</button>
                <button type="button" class="urg-filter" data-filter="mias">Mis urgencias</button>
                <button type="button" class="urg-filter" data-filter="proceso">En proceso</button>
                <button type="button" class="urg-filter" data-filter="todas">Todas</button>
            </div>

            <label class="urg-search" for="buscarUrgencia">
                <span>Buscar</span>
                <div class="urg-search__control">
                    <svg aria-hidden="true"><use href="#urg-icon-search"></use></svg>
                    <input
                        type="search"
                        id="buscarUrgencia"
                        placeholder="Folio, equipo, área o descripción"
                        autocomplete="off"
                        maxlength="100"
                    >
                </div>
            </label>
        </div>
    </section>

    <div class="urg-status" id="estadoCarga" role="status" aria-live="polite">
        Cargando urgencias...
    </div>

    <section class="urg-results-panel">
        <header class="urg-results-head">
            <div class="urg-section-heading">
                <span class="urg-section-heading__icon urg-section-heading__icon--danger" aria-hidden="true">
                    <svg><use href="#urg-icon-bolt"></use></svg>
                </span>
                <div>
                    <p class="urg-eyebrow">Centro de respuesta</p>
                    <h2 id="tituloResultados">Urgencias disponibles</h2>
                    <p id="subtituloResultados">Solicitudes abiertas que todavía tienen lugares.</p>
                </div>
            </div>
            <span class="urg-results-count" id="contadorResultados">0 registros</span>
        </header>

        <div class="urg-results-body">
            <section class="urg-grid" id="listaUrgencias" aria-live="polite"></section>

            <section class="urg-empty" id="estadoVacio" hidden>
                <span aria-hidden="true"><svg><use href="#urg-icon-check"></use></svg></span>
                <h2>No hay urgencias en esta vista</h2>
                <p id="textoVacio">No existen urgencias disponibles con los filtros seleccionados.</p>
            </section>
        </div>

        <footer class="urg-footer-note">
            <span class="urg-footer-note__icon" aria-hidden="true">
                <svg><use href="#urg-icon-refresh"></use></svg>
            </span>
            <p>
                <strong>Reanudación manual:</strong>
                cuando una urgencia pause tu mantenimiento anterior, ese mantenimiento permanecerá pausado al cerrar la urgencia.
                Tú decidirás cuándo reanudarlo desde Actividad actual.
            </p>
        </footer>
    </section>

    <footer class="urg-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Centro de urgencias del técnico · Los Chapeteados División Petfood</span>
    </footer>

    <div class="urg-tools-background" aria-hidden="true"></div>
</main>

<div class="urg-modal" id="modalUrgencia" hidden aria-hidden="true">
    <div class="urg-modal__backdrop" data-close-modal></div>
    <section class="urg-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modalTitulo">
        <header class="urg-modal__head">
            <div class="urg-modal__heading">
                <span class="urg-modal__heading-icon" aria-hidden="true">
                    <svg><use href="#urg-icon-siren"></use></svg>
                </span>
                <div>
                    <p class="urg-eyebrow">Detalle de urgencia</p>
                    <h2 id="modalTitulo">Cargando...</h2>
                    <p id="modalSubtitulo"></p>
                </div>
            </div>
            <button type="button" class="urg-modal__close" data-close-modal aria-label="Cerrar">×</button>
        </header>

        <div class="urg-modal__body">
            <div class="urg-modal-loading" id="modalCargando">
                <span class="urg-spinner" aria-hidden="true"></span>
                <p>Cargando información...</p>
            </div>

            <div id="modalContenido" hidden>
                <section class="urg-detail-hero" id="detalleHero"></section>

                <section class="urg-risk-notice" id="avisoRiesgoUrgente" hidden>
                    <span aria-hidden="true">!</span>
                    <div>
                        <strong>Trabajo peligroso</strong>
                        <p id="textoRiesgoUrgente"></p>
                    </div>
                </section>

                <section class="urg-detail-grid" aria-label="Datos generales">
                    <article>
                        <span>Equipo</span>
                        <strong id="detalleEquipo">—</strong>
                        <small id="detalleCodigo">—</small>
                    </article>
                    <article>
                        <span>Ubicación</span>
                        <strong id="detalleUbicacion">—</strong>
                        <small id="detalleProceso">—</small>
                    </article>
                    <article>
                        <span>Diagnóstico técnico</span>
                        <strong id="detalleTipoFalla">Pendiente de captura</strong>
                        <small id="detalleCausa">Se registrará al iniciar</small>
                    </article>
                    <article>
                        <span>Solicitante</span>
                        <strong id="detalleSolicitante">—</strong>
                        <small id="detalleFecha">—</small>
                    </article>
                </section>

                <section class="urg-detail-section">
                    <h3>Descripción de la urgencia</h3>
                    <p id="detalleDescripcion"></p>
                </section>

                <section class="urg-detail-columns">
                    <article class="urg-detail-section">
                        <h3>Condición o síntomas</h3>
                        <p id="detalleFalla"></p>
                    </article>
                    <article class="urg-detail-section">
                        <h3>Impacto en la operación</h3>
                        <p id="detalleImpacto"></p>
                    </article>
                </section>

                <section class="urg-detail-section" id="seccionExplicacionCausa" hidden>
                    <h3>Explicación técnica provisional</h3>
                    <p id="detalleCausaDesconocida"></p>
                </section>

                <section class="urg-detail-section" id="seccionObservaciones" hidden>
                    <h3>Observaciones del solicitante</h3>
                    <p id="detalleObservaciones"></p>
                </section>

                <section class="urg-resources">
                    <header>
                        <div>
                            <h3>Herramientas y refacciones recomendadas</h3>
                            <p>Estas recomendaciones pueden provenir del administrador o de urgencias anteriores del mismo equipo.</p>
                        </div>
                        <span id="contadorRecursosUrgencia">0 recursos</span>
                    </header>
                    <div class="urg-resources__content" id="detalleRecursosUrgencia"></div>
                </section>

                <section class="urg-participants">
                    <header>
                        <div>
                            <h3>Participantes</h3>
                            <p id="detalleCapacidad">0 de 0 lugares ocupados</p>
                        </div>
                        <span id="detalleLugares">0 lugares libres</span>
                    </header>
                    <div class="urg-progress" aria-hidden="true">
                        <i id="barraCapacidad"></i>
                    </div>
                    <div class="urg-participant-list" id="listaParticipantes"></div>
                </section>

                <section class="urg-start-warning" id="avisoInicioModal" hidden>
                    <strong>Tu actividad actual será pausada</strong>
                    <p id="textoInicioModal"></p>
                </section>
            </div>
        </div>

        <footer class="urg-modal__actions" id="modalAcciones" hidden>
            <button type="button" class="urg-btn urg-btn--ghost" data-close-modal>Cerrar</button>
            <button type="button" class="urg-btn urg-btn--danger-outline" id="btnRetirar" hidden>Liberar mi lugar</button>
            <button type="button" class="urg-btn urg-btn--danger" id="btnAceptar" hidden>Aceptar urgencia</button>
            <button type="button" class="urg-btn urg-btn--primary" id="btnIniciar" hidden>Iniciar urgencia</button>
            <a class="urg-btn urg-btn--primary" id="btnAbrirActividad" href="mantenimiento_activo.php" hidden>Abrir actividad</a>
        </footer>
    </section>
</div>

<?php include __DIR__ . '/../inc/alertas.php'; ?>

<script>
(() => {
    'use strict';

    const ENDPOINT = '../funciones/urgencias_disponibles_funciones.php';
    const REFRESCO_MS = 20000;
    const $ = (id) => document.getElementById(id);

    const estado = {
        cargando: false,
        procesando: false,
        urgencias: [],
        resumen: {},
        configuracion: {},
        tiposFalla: [],
        causasAveria: [],
        compromiso: null,
        actividadActual: null,
        detalle: null,
        participantes: [],
        filtro: 'disponibles',
        busqueda: '',
        temporizador: null,
        ultimoFoco: null,
        solicitudInicial: 0
    };

    const textosFiltro = {
        disponibles: {
            titulo: 'Urgencias disponibles',
            subtitulo: 'Solicitudes abiertas con lugares libres que todavía no has aceptado.',
            vacio: 'No hay urgencias disponibles para aceptar en este momento.'
        },
        mias: {
            titulo: 'Mis urgencias',
            subtitulo: 'Urgencias que aceptaste o en las que ya estás participando.',
            vacio: 'No tienes una urgencia aceptada o activa.'
        },
        proceso: {
            titulo: 'Urgencias en proceso',
            subtitulo: 'Urgencias que ya comenzaron y todavía permiten participación.',
            vacio: 'No hay urgencias en proceso dentro de esta vista.'
        },
        todas: {
            titulo: 'Todas las urgencias abiertas',
            subtitulo: 'Urgencias disponibles y participaciones propias.',
            vacio: 'No hay urgencias abiertas.'
        }
    };

    const etiquetas = {
        AGENDADO: 'Disponible',
        EN_PROCESO: 'En proceso',
        PAUSADO: 'Pausada',
        ATRASADO: 'Atrasada',
        ACEPTADO: 'Aceptada',
        RETIRADO: 'Retirado',
        BAJO: 'Riesgo bajo',
        MEDIO: 'Riesgo medio',
        ALTO: 'Riesgo alto',
        MATUTINO: 'Matutino',
        VESPERTINO: 'Vespertino',
        NOCTURNO: 'Nocturno'
    };

    function escapar(valor) {
        return String(valor == null ? '' : valor).replace(/[&<>'"]/g, (caracter) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#039;',
            '"': '&quot;'
        }[caracter]));
    }

    function texto(valor, alternativo = 'No especificado') {
        const limpio = String(valor == null ? '' : valor).trim();
        return limpio || alternativo;
    }

    function numero(valor) {
        const resultado = Number(valor || 0);
        return Number.isFinite(resultado) ? resultado : 0;
    }

    function recursosDe(registro, clave) {
        return Array.isArray(registro && registro[clave])
            ? registro[clave]
            : [];
    }

    function detalleRiesgoUrgente(registro) {
        return texto(
            registro && registro.detalle_trabajo_peligroso,
            'Trabajo peligroso con nivel de riesgo '
                + texto(registro && registro.nivel_riesgo, 'registrado').toLowerCase()
                + '. Verifica protección, condiciones del área y seguridad antes de participar.'
        );
    }

    function resumenRecursosUrgencia(registro) {
        const herramientas = recursosDe(registro, 'herramientas_recomendadas');
        const refacciones = recursosDe(registro, 'refacciones_recomendadas');
        const total = herramientas.length + refacciones.length;

        if (total < 1) {
            return `
                <div class="urg-resource-summary urg-resource-summary--empty">
                    <strong>Sin recomendaciones previas</strong>
                    <span>Puede ser la primera urgencia de este equipo.</span>
                </div>
            `;
        }

        return `
            <div class="urg-resource-summary">
                <strong>Recursos sugeridos</strong>
                <span>${herramientas.length} ${herramientas.length === 1 ? 'herramienta' : 'herramientas'}</span>
                <span>${refacciones.length} ${refacciones.length === 1 ? 'refacción' : 'refacciones'}</span>
            </div>
        `;
    }

    function pintarRecursosUrgencia(registro) {
        const herramientas = recursosDe(registro, 'herramientas_recomendadas');
        const refacciones = recursosDe(registro, 'refacciones_recomendadas');
        const total = herramientas.length + refacciones.length;
        const contenedor = $('detalleRecursosUrgencia');

        $('contadorRecursosUrgencia').textContent = total === 1
            ? '1 recurso'
            : total + ' recursos';

        if (total < 1) {
            contenedor.innerHTML = `
                <div class="urg-resources-empty">
                    <strong>No existen recomendaciones registradas</strong>
                    <p>La urgencia puede atenderse. Revisa el trabajo y prepara los recursos que consideres necesarios.</p>
                </div>
            `;
            return;
        }

        const panel = (titulo, elementos, vacio) => `
            <article class="urg-resource-panel${elementos.length ? '' : ' is-empty'}">
                <header><h4>${escapar(titulo)}</h4><span>${elementos.length}</span></header>
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

    async function confirmarConocimientoRiesgo(registro, alAceptarLugar) {
        const detalle = detalleRiesgoUrgente(registro);
        const confirmado = await SistemaUI.confirmar({
            titulo: 'Urgencia con trabajo peligroso',
            texto: detalle + ' '
                + (alAceptarLugar
                    ? 'Al continuar reservarás un lugar y quedará registrada tu confirmación.'
                    : 'Debes confirmar que estás enterado antes de iniciar.'),
            textoConfirmar: alAceptarLugar
                ? 'Estoy enterado y deseo unirme'
                : 'Estoy enterado y continuar',
            icono: 'warning',
            peligro: true
        });

        return confirmado === true;
    }

    function etiqueta(valor) {
        const clave = String(valor || '').toUpperCase();
        return etiquetas[clave] || clave.replaceAll('_', ' ').toLowerCase().replace(/^./, (c) => c.toUpperCase());
    }

    function fecha(valor, incluirHora = false) {
        if (!valor) return 'Sin fecha';
        const original = String(valor);
        const normalizado = original.length === 10
            ? original + 'T12:00:00'
            : original.replace(' ', 'T');
        const objeto = new Date(normalizado);
        if (Number.isNaN(objeto.getTime())) return original;
        return new Intl.DateTimeFormat('es-MX', incluirHora
            ? { dateStyle: 'medium', timeStyle: 'short' }
            : { dateStyle: 'medium' }
        ).format(objeto);
    }

    function fechaSolicitud(registro) {
        const fechaBase = texto(registro.fecha_solicitud, '');
        const horaBase = texto(registro.hora_solicitud, '');
        if (!fechaBase) return 'Sin fecha';
        return fecha(fechaBase + (horaBase ? ' ' + horaBase : ''), true);
    }

    function claseRiesgo(riesgo) {
        return 'urg-badge--risk-' + String(riesgo || 'BAJO').toLowerCase();
    }

    function claseEstado(estadoSolicitud) {
        const estadoValor = String(estadoSolicitud || '').toLowerCase();
        return 'urg-badge--state-' + estadoValor;
    }

    function establecerEstado(mensaje, tipo = '') {
        const elemento = $('estadoCarga');
        elemento.textContent = mensaje;
        elemento.className = 'urg-status' + (tipo ? ' urg-status--' + tipo : '');
    }

    function bloquearPagina(bloquear) {
        estado.procesando = bloquear;
        document.querySelectorAll('[data-urg-action]').forEach((boton) => {
            boton.disabled = bloquear;
        });
    }

    function leerParametros() {
        const parametros = new URLSearchParams(window.location.search);
        const filtro = parametros.get('filtro');
        const solicitud = Number(parametros.get('solicitud_id') || 0);

        if (['disponibles', 'mias', 'proceso', 'todas'].includes(filtro)) {
            estado.filtro = filtro;
        }

        if (Number.isInteger(solicitud) && solicitud > 0) {
            estado.solicitudInicial = solicitud;
        }
    }

    async function cargarInicial(silencioso = false) {
        if (estado.cargando || (estado.procesando && !silencioso)) return;
        estado.cargando = true;

        if (!silencioso) {
            establecerEstado('Cargando urgencias...');
            SistemaUI.estadoBoton($('btnActualizar'), true, 'Actualizando...');
        }

        try {
            const datos = await SistemaUI.peticionJson(ENDPOINT + '?accion=inicial');
            estado.urgencias = Array.isArray(datos.urgencias) ? datos.urgencias : [];
            estado.resumen = datos.resumen || {};
            estado.configuracion = datos.configuracion || {};
            estado.tiposFalla = datos.catalogos && Array.isArray(datos.catalogos.tipos_falla)
                ? datos.catalogos.tipos_falla
                : [];
            estado.causasAveria = datos.catalogos && Array.isArray(datos.catalogos.causas_averia)
                ? datos.catalogos.causas_averia
                : [];
            estado.compromiso = datos.compromiso_urgente || null;
            estado.actividadActual = datos.actividad_actual || null;

            pintarResumen();
            pintarAvisos();
            activarFiltroVisual();
            pintarLista();

            establecerEstado(
                'Información actualizada ' + fecha(datos.fecha_servidor, true) + '.',
                'ok'
            );

            if (estado.solicitudInicial > 0) {
                const id = estado.solicitudInicial;
                estado.solicitudInicial = 0;
                await abrirDetalle(id);
            }
        } catch (error) {
            establecerEstado(
                error.message || 'No fue posible cargar las urgencias.',
                'error'
            );

            if (!silencioso) {
                await SistemaUI.error(
                    'No se cargaron las urgencias',
                    error.message || 'Actualiza la página e inténtalo nuevamente.'
                );
            }
        } finally {
            estado.cargando = false;
            SistemaUI.estadoBoton($('btnActualizar'), false);
        }
    }

    function pintarResumen() {
        $('kpiDisponibles').textContent = numero(estado.resumen.disponibles).toLocaleString('es-MX');
        $('kpiAceptadas').textContent = numero(estado.resumen.mias_aceptadas).toLocaleString('es-MX');
        $('kpiActivas').textContent = numero(estado.resumen.mias_en_proceso).toLocaleString('es-MX');
        $('kpiRiesgo').textContent = numero(estado.resumen.riesgo_alto).toLocaleString('es-MX');
    }

    function pintarAvisos() {
        const compromiso = estado.compromiso;
        const avisoCompromiso = $('avisoCompromiso');

        if (compromiso) {
            avisoCompromiso.hidden = false;
            $('avisoCompromisoTitulo').textContent = compromiso.estado_participacion === 'ACEPTADO'
                ? 'Ya aceptaste la urgencia ' + texto(compromiso.folio)
                : 'Estás participando en la urgencia ' + texto(compromiso.folio);
            $('avisoCompromisoTexto').textContent =
                texto(compromiso.nombre_equipo) + '. Solo puedes mantener un compromiso urgente activo a la vez.';
            $('btnAbrirCompromiso').dataset.solicitudId = numero(compromiso.solicitud_id);
        } else {
            avisoCompromiso.hidden = true;
            delete $('btnAbrirCompromiso').dataset.solicitudId;
        }

        const actividad = estado.actividadActual;
        const avisoActividad = $('avisoActividad');

        if (actividad && actividad.tipo_solicitud !== 'CORRECTIVO_URGENTE') {
            avisoActividad.hidden = false;
            $('avisoActividadTitulo').textContent = 'Mantenimiento normal en proceso: ' + texto(actividad.folio);
            $('avisoActividadTexto').textContent =
                'Si inicias una urgencia, ' + texto(actividad.nombre_equipo)
                + ' se pausará automáticamente. Al terminar la urgencia no se reanudará solo; deberás presionar Reanudar mantenimiento.';
        } else {
            avisoActividad.hidden = true;
        }
    }

    function activarFiltroVisual() {
        document.querySelectorAll('.urg-filter').forEach((boton) => {
            boton.classList.toggle('is-active', boton.dataset.filter === estado.filtro);
        });

        const textos = textosFiltro[estado.filtro] || textosFiltro.disponibles;
        $('tituloResultados').textContent = textos.titulo;
        $('subtituloResultados').textContent = textos.subtitulo;
        $('textoVacio').textContent = textos.vacio;
    }

    function coincideBusqueda(registro) {
        if (!estado.busqueda) return true;
        const bolsa = [
            registro.folio,
            registro.codigo_equipo,
            registro.nombre_equipo,
            registro.departamento,
            registro.area,
            registro.proceso,
            registro.descripcion_solicitud,
            registro.descripcion_falla,
            registro.tipo_falla,
            registro.causa_averia,
            recursosDe(registro, 'herramientas_recomendadas').map((recurso) => recurso.nombre).join(' '),
            recursosDe(registro, 'refacciones_recomendadas').map((recurso) => recurso.nombre).join(' ')
        ].join(' ').toLowerCase();
        return bolsa.includes(estado.busqueda);
    }

    function coincideFiltro(registro) {
        const esMia = numero(registro.es_mia) === 1;

        if (estado.filtro === 'mias') return esMia;
        if (estado.filtro === 'proceso') return registro.estado === 'EN_PROCESO';
        if (estado.filtro === 'todas') return true;

        return !esMia && numero(registro.lugares_disponibles) > 0;
    }

    function obtenerFiltradas() {
        return estado.urgencias.filter((registro) =>
            coincideFiltro(registro) && coincideBusqueda(registro)
        );
    }

    function pintarLista() {
        const registros = obtenerFiltradas();
        const contenedor = $('listaUrgencias');
        contenedor.innerHTML = '';
        $('contadorResultados').textContent = registros.length === 1
            ? '1 registro'
            : registros.length.toLocaleString('es-MX') + ' registros';
        $('estadoVacio').hidden = registros.length > 0;
        contenedor.hidden = registros.length === 0;

        registros.forEach((registro) => {
            contenedor.insertAdjacentHTML('beforeend', tarjetaUrgencia(registro));
        });
    }

    function tarjetaUrgencia(registro) {
        const participantes = numero(registro.tecnicos_aceptaron);
        const limite = Math.max(1, numero(registro.limite_tecnicos));
        const porcentaje = Math.min(100, Math.round((participantes / limite) * 100));
        const esMia = numero(registro.es_mia) === 1;
        const peligrosa = numero(registro.trabajo_peligroso) === 1;
        const requiereParo = numero(registro.requiere_paro_equipo) === 1;

        let estadoPropio = '';
        if (esMia) {
            estadoPropio = '<span class="urg-own">Mi participación: '
                + escapar(etiqueta(registro.estado_participacion)) + '</span>';
        }

        let acciones = '';

        if (numero(registro.puede_aceptar) === 1) {
            acciones = '<button type="button" class="urg-btn urg-btn--danger" data-urg-action="aceptar" data-id="'
                + numero(registro.solicitud_id) + '">Aceptar urgencia</button>';
        } else if (numero(registro.puede_iniciar) === 1) {
            acciones = '<button type="button" class="urg-btn urg-btn--primary" data-urg-action="iniciar" data-id="'
                + numero(registro.solicitud_id) + '">Iniciar urgencia</button>';
        } else if (numero(registro.puede_abrir_actividad) === 1) {
            acciones = '<a class="urg-btn urg-btn--primary" href="mantenimiento_activo.php?ejecucion_id='
                + numero(registro.ejecucion_id) + '">Abrir actividad</a>';
        } else {
            acciones = '<span class="urg-blocked">' + escapar(texto(registro.motivo_bloqueo, 'Revisa el detalle')) + '</span>';
        }

        const flags = [
            peligrosa ? '<span>Trabajo peligroso</span>' : '',
            requiereParo ? '<span>Requiere paro</span>' : '',
            registro.estado === 'EN_PROCESO' ? '<span>Ya comenzó</span>' : ''
        ].filter(Boolean).join('');

        return `
            <article class="urg-item ${esMia ? 'urg-item--mine' : ''}" data-solicitud-id="${numero(registro.solicitud_id)}">
                <header class="urg-item__head">
                    <div>
                        <span class="urg-folio">${escapar(texto(registro.folio))}</span>
                        <h3>${escapar(texto(registro.nombre_equipo))}</h3>
                        <p>${escapar(texto(registro.codigo_equipo, 'Sin código'))}</p>
                    </div>
                    <div class="urg-badges">
                        <span class="urg-badge ${claseEstado(registro.estado)}">${escapar(etiqueta(registro.estado))}</span>
                        <span class="urg-badge ${claseRiesgo(registro.nivel_riesgo)}">${escapar(etiqueta(registro.nivel_riesgo))}</span>
                    </div>
                </header>

                <div class="urg-location">
                    <span>${escapar(texto(registro.departamento))}</span>
                    <i aria-hidden="true">›</i>
                    <span>${escapar(texto(registro.area))}</span>
                    <i aria-hidden="true">›</i>
                    <span>${escapar(texto(registro.proceso))}</span>
                </div>

                <p class="urg-description">${escapar(texto(registro.descripcion_solicitud))}</p>

                ${resumenRecursosUrgencia(registro)}

                <div class="urg-flags">${flags || '<span>Información revisada</span>'}</div>

                <div class="urg-capacity">
                    <div>
                        <span>Participantes</span>
                        <strong>${participantes} de ${limite}</strong>
                    </div>
                    <div class="urg-progress"><i style="width:${porcentaje}%"></i></div>
                    <small>${numero(registro.lugares_disponibles)} lugares libres</small>
                </div>

                ${estadoPropio}

                <footer class="urg-item__foot">
                    <span>Publicada ${escapar(fechaSolicitud(registro))}</span>
                    <div>
                        <button type="button" class="urg-btn urg-btn--ghost" data-urg-action="detalle" data-id="${numero(registro.solicitud_id)}">Ver detalle</button>
                        ${acciones}
                    </div>
                </footer>
            </article>
        `;
    }

    async function abrirDetalle(solicitudId) {
        if (!Number.isInteger(Number(solicitudId)) || Number(solicitudId) < 1) return;

        estado.ultimoFoco = document.activeElement;
        estado.detalle = null;
        estado.participantes = [];
        abrirModal();
        mostrarCargaModal(true);

        try {
            const datos = await SistemaUI.peticionJson(
                ENDPOINT + '?accion=detalle&id=' + encodeURIComponent(solicitudId)
            );
            estado.detalle = datos.urgencia || null;
            estado.participantes = Array.isArray(datos.participantes) ? datos.participantes : [];
            estado.actividadActual = datos.actividad_actual || estado.actividadActual;
            estado.compromiso = datos.compromiso_urgente || estado.compromiso;
            estado.configuracion = datos.configuracion || estado.configuracion;
            estado.tiposFalla = datos.catalogos && Array.isArray(datos.catalogos.tipos_falla)
                ? datos.catalogos.tipos_falla
                : estado.tiposFalla;
            estado.causasAveria = datos.catalogos && Array.isArray(datos.catalogos.causas_averia)
                ? datos.catalogos.causas_averia
                : estado.causasAveria;

            if (!estado.detalle) throw new Error('No se recibió la información de la urgencia.');

            pintarDetalle();
            mostrarCargaModal(false);
        } catch (error) {
            cerrarModal(true);
            await SistemaUI.error(
                'No se pudo abrir la urgencia',
                error.message || 'Actualiza la lista e inténtalo nuevamente.'
            );
            await cargarInicial(true);
        }
    }

    function abrirModal() {
        $('modalUrgencia').hidden = false;
        $('modalUrgencia').setAttribute('aria-hidden', 'false');
        document.body.classList.add('urg-lock');
        window.setTimeout(() => document.querySelector('.urg-modal__close').focus(), 30);
    }

    function cerrarModal(forzar = false) {
        if (estado.procesando && !forzar) return;
        $('modalUrgencia').hidden = true;
        $('modalUrgencia').setAttribute('aria-hidden', 'true');
        document.body.classList.remove('urg-lock');
        estado.detalle = null;
        estado.participantes = [];
        if (estado.ultimoFoco && typeof estado.ultimoFoco.focus === 'function') {
            estado.ultimoFoco.focus();
        }
    }

    function mostrarCargaModal(cargando) {
        $('modalCargando').hidden = !cargando;
        $('modalContenido').hidden = cargando;
        $('modalAcciones').hidden = cargando;
    }

    function pintarDetalle() {
        const d = estado.detalle;
        const participantes = numero(d.tecnicos_aceptaron);
        const limite = Math.max(1, numero(d.limite_tecnicos));
        const porcentaje = Math.min(100, Math.round((participantes / limite) * 100));

        $('modalTitulo').textContent = texto(d.folio);
        $('modalSubtitulo').textContent = texto(d.nombre_equipo) + ' · ' + etiqueta(d.estado);

        $('detalleHero').innerHTML = `
            <div>
                <span class="urg-badge ${claseEstado(d.estado)}">${escapar(etiqueta(d.estado))}</span>
                <span class="urg-badge ${claseRiesgo(d.nivel_riesgo)}">${escapar(etiqueta(d.nivel_riesgo))}</span>
                ${numero(d.trabajo_peligroso) === 1 ? '<span class="urg-badge urg-badge--alert">Trabajo peligroso</span>' : ''}
                ${numero(d.requiere_paro_equipo) === 1 ? '<span class="urg-badge urg-badge--alert">Requiere detener equipo</span>' : ''}
            </div>
            <strong>${escapar(texto(d.estado_participacion, 'Aún no participas'))}</strong>
        `;

        $('detalleEquipo').textContent = texto(d.nombre_equipo);
        $('detalleCodigo').textContent = texto(d.codigo_equipo, 'Sin código de equipo');
        $('detalleUbicacion').textContent = texto(d.departamento) + ' · ' + texto(d.area);
        $('detalleProceso').textContent = 'Proceso: ' + texto(d.proceso);
        const diagnosticoCompleto = numero(d.tipo_falla_id) > 0
            && numero(d.causa_averia_id) > 0;

        $('detalleTipoFalla').textContent = diagnosticoCompleto
            ? texto(d.tipo_falla)
            : 'Pendiente de captura';

        $('detalleCausa').textContent = diagnosticoCompleto
            ? 'Causa: ' + texto(d.causa_averia)
            : 'El primer técnico la registrará al iniciar';
        $('detalleSolicitante').textContent = texto(d.solicitante);
        $('detalleFecha').textContent = 'Registrada ' + fechaSolicitud(d);
        $('detalleDescripcion').textContent = texto(d.descripcion_solicitud);
        $('detalleFalla').textContent = texto(d.descripcion_falla);
        $('detalleImpacto').textContent = texto(d.impacto_operacion);

        const causaDesconocida = texto(d.causa_desconocida_descripcion, '');
        $('seccionExplicacionCausa').hidden = !causaDesconocida;
        $('detalleCausaDesconocida').textContent = causaDesconocida;

        const observaciones = texto(d.observaciones_solicitante, '');
        $('seccionObservaciones').hidden = !observaciones;
        $('detalleObservaciones').textContent = observaciones;

        const peligrosa = numero(d.trabajo_peligroso) === 1;
        $('avisoRiesgoUrgente').hidden = !peligrosa;
        $('textoRiesgoUrgente').textContent = peligrosa ? detalleRiesgoUrgente(d) : '';
        pintarRecursosUrgencia(d);

        $('detalleCapacidad').textContent = participantes + ' de ' + limite + ' lugares ocupados';
        $('detalleLugares').textContent = numero(d.lugares_disponibles) === 1
            ? '1 lugar libre'
            : numero(d.lugares_disponibles) + ' lugares libres';
        $('barraCapacidad').style.width = porcentaje + '%';

        pintarParticipantes();
        pintarAvisoInicioModal();
        pintarAccionesDetalle();
    }

    function pintarParticipantes() {
        const contenedor = $('listaParticipantes');

        if (estado.participantes.length === 0) {
            contenedor.innerHTML = '<div class="urg-participant-empty">Todavía ningún técnico ha aceptado esta urgencia.</div>';
            return;
        }

        contenedor.innerHTML = estado.participantes.map((participante) => `
            <article class="urg-participant">
                <span class="urg-participant__avatar" aria-hidden="true">${escapar(texto(participante.tecnico, 'T').charAt(0).toUpperCase())}</span>
                <div>
                    <strong>${escapar(texto(participante.tecnico))}</strong>
                    <small>${escapar(etiqueta(participante.turno))}${participante.especialidad ? ' · ' + escapar(participante.especialidad) : ''}</small>
                </div>
                <span class="urg-badge ${claseEstado(participante.estado_participacion)}">${escapar(etiqueta(participante.estado_participacion))}</span>
            </article>
        `).join('');
    }

    function pintarAvisoInicioModal() {
        const aviso = $('avisoInicioModal');
        const actividad = estado.actividadActual;
        const puedeIniciar = estado.detalle && numero(estado.detalle.puede_iniciar) === 1;

        if (puedeIniciar && actividad && actividad.tipo_solicitud !== 'CORRECTIVO_URGENTE') {
            aviso.hidden = false;
            $('textoInicioModal').textContent =
                'El mantenimiento ' + texto(actividad.folio) + ' de ' + texto(actividad.nombre_equipo)
                + ' se pausará al iniciar esta urgencia. Después no se reanudará automáticamente; deberás hacerlo manualmente cuando estés listo.';
        } else {
            aviso.hidden = true;
            $('textoInicioModal').textContent = '';
        }
    }

    function pintarAccionesDetalle() {
        const d = estado.detalle;
        $('btnAceptar').hidden = numero(d.puede_aceptar) !== 1;
        $('btnIniciar').hidden = numero(d.puede_iniciar) !== 1;
        $('btnRetirar').hidden = numero(d.puede_retirar) !== 1;
        $('btnAbrirActividad').hidden = numero(d.puede_abrir_actividad) !== 1;
        $('btnAbrirActividad').href = 'mantenimiento_activo.php?ejecucion_id=' + numero(d.ejecucion_id);

        ['btnAceptar', 'btnIniciar', 'btnRetirar'].forEach((id) => {
            $(id).dataset.solicitudId = numero(d.solicitud_id);
        });
    }

    async function aceptarUrgencia(solicitudId) {
        if (estado.procesando) return;

        const registroLista = buscarRegistro(solicitudId);
        const registro = estado.detalle
            && numero(estado.detalle.solicitud_id) === numero(solicitudId)
            ? estado.detalle
            : registroLista;
        const peligrosa = numero(registro && registro.trabajo_peligroso) === 1;

        let confirmado = false;

        if (peligrosa) {
            confirmado = await confirmarConocimientoRiesgo(registro, true);
        } else {
            confirmado = await SistemaUI.confirmar({
                titulo: '¿Aceptar esta urgencia?',
                texto: 'Reservarás un lugar en ' + texto(registro ? registro.folio : '')
                    + '. Después podrás iniciarla cuando estés preparado.',
                textoConfirmar: 'Sí, aceptar urgencia',
                icono: 'warning',
                peligro: true
            });
        }

        if (!confirmado) return;

        await ejecutarAccion(
            'aceptar',
            solicitudId,
            peligrosa ? 'Confirmando y aceptando...' : 'Aceptando...',
            async (datos) => {
                await SistemaUI.exito(
                    peligrosa ? 'Riesgo confirmado' : 'Urgencia aceptada',
                    datos.mensaje || 'Tu lugar quedó registrado.'
                );
            },
            { confirmacion_riesgo: peligrosa ? 1 : 0 }
        );
    }

    async function retirarUrgencia(solicitudId) {
        if (estado.procesando) return;
        const confirmado = await SistemaUI.confirmar({
            titulo: '¿Liberar tu lugar?',
            texto: 'Podrás liberar el lugar porque todavía no has iniciado la urgencia. La acción quedará registrada.',
            textoConfirmar: 'Sí, liberar mi lugar',
            icono: 'warning',
            peligro: true
        });

        if (!confirmado) return;

        await ejecutarAccion('retirar', solicitudId, 'Liberando...', async (datos) => {
            await SistemaUI.exito('Lugar liberado', datos.mensaje || 'Tu aceptación fue retirada.');
        });
    }

    async function iniciarUrgencia(solicitudId) {
        if (estado.procesando) return;

        let detalleActual = estado.detalle;

        if (!detalleActual || numero(detalleActual.solicitud_id) !== numero(solicitudId)) {
            try {
                const datosDetalle = await SistemaUI.peticionJson(
                    ENDPOINT + '?accion=detalle&id=' + encodeURIComponent(solicitudId)
                );

                detalleActual = datosDetalle.urgencia || null;
                estado.actividadActual = datosDetalle.actividad_actual || estado.actividadActual;
                estado.tiposFalla = datosDetalle.catalogos && Array.isArray(datosDetalle.catalogos.tipos_falla)
                    ? datosDetalle.catalogos.tipos_falla
                    : estado.tiposFalla;
                estado.causasAveria = datosDetalle.catalogos && Array.isArray(datosDetalle.catalogos.causas_averia)
                    ? datosDetalle.catalogos.causas_averia
                    : estado.causasAveria;
            } catch (error) {
                await SistemaUI.error(
                    'No se pudo validar la urgencia',
                    error.message || 'Actualiza la lista.'
                );
                return;
            }
        }

        if (!detalleActual) {
            await SistemaUI.error(
                'No se pudo iniciar',
                'No se recibió la información de la urgencia.'
            );
            return;
        }

        if (
            numero(detalleActual.trabajo_peligroso) === 1
            && numero(detalleActual.riesgo_confirmado_por_tecnico) !== 1
        ) {
            const confirmadoRiesgo = await confirmarConocimientoRiesgo(detalleActual, false);

            if (!confirmadoRiesgo) {
                return;
            }

            try {
                const formularioRiesgo = new FormData();
                formularioRiesgo.set('accion', 'aceptar');
                formularioRiesgo.set('solicitud_id', String(solicitudId));
                formularioRiesgo.set('confirmacion_riesgo', '1');

                await SistemaUI.peticionJson(ENDPOINT, {
                    method: 'POST',
                    body: formularioRiesgo
                });

                detalleActual.riesgo_confirmado_por_tecnico = 1;
                detalleActual.riesgo_urgente_confirmado_tecnico = 1;
                detalleActual.requiere_confirmacion_riesgo = 0;
            } catch (error) {
                await SistemaUI.error(
                    'No se registró la confirmación',
                    error.message || 'Actualiza la urgencia e inténtalo nuevamente.'
                );
                return;
            }
        }

        let mensaje = 'Se registrará la hora de inicio y la urgencia pasará a En proceso.';

        if (
            estado.actividadActual
            && estado.actividadActual.tipo_solicitud !== 'CORRECTIVO_URGENTE'
        ) {
            mensaje = 'El mantenimiento ' + texto(estado.actividadActual.folio)
                + ' se pausará automáticamente. Al terminar la urgencia seguirá pausado hasta que presiones Reanudar mantenimiento.';
        }

        let datosDiagnostico = {};
        const diagnosticoPendiente = numero(detalleActual.tipo_falla_id) < 1
            || numero(detalleActual.causa_averia_id) < 1;

        if (diagnosticoPendiente) {
            const diagnostico = await solicitarDiagnosticoInicial(mensaje);

            if (!diagnostico) {
                return;
            }

            datosDiagnostico = diagnostico;
        } else {
            const confirmado = await SistemaUI.confirmar({
                titulo: '¿Iniciar la urgencia ahora?',
                texto: mensaje,
                textoConfirmar: 'Sí, iniciar urgencia',
                icono: 'warning',
                peligro: true
            });

            if (!confirmado) {
                return;
            }
        }

        await ejecutarAccion(
            'iniciar',
            solicitudId,
            'Iniciando...',
            async (datos) => {
                cerrarModal(true);

                let textoExito = datos.diagnostico_capturado
                    ? 'El diagnóstico fue registrado y la urgencia quedó en proceso.'
                    : 'La urgencia quedó en proceso.';

                if (datos.mantenimiento_pausado) {
                    textoExito += ' El mantenimiento '
                        + texto(datos.mantenimiento_pausado.folio)
                        + ' quedó pausado y deberá reanudarse manualmente.';
                }

                await Swal.fire({
                    icon: 'success',
                    title: 'Urgencia iniciada',
                    text: textoExito,
                    confirmButtonText: 'Aceptar',
                    allowOutsideClick: false,
                    heightAuto: false
                });
            },
            datosDiagnostico
        );
    }

    async function solicitarDiagnosticoInicial(mensajeInicio) {
        if (!estado.tiposFalla.length || !estado.causasAveria.length) {
            await SistemaUI.error(
                'No se puede iniciar la urgencia',
                'No hay tipos de falla o causas de avería activas. Solicita al administrador que revise los catálogos.'
            );
            return null;
        }

        const opcionesTipo = estado.tiposFalla.map((item) => (
            '<option value="' + numero(item.id) + '">'
            + escapar(texto(item.nombre, 'Tipo de falla'))
            + '</option>'
        )).join('');

        const opcionesCausa = estado.causasAveria.map((item) => (
            '<option value="' + numero(item.id) + '">'
            + escapar(texto(item.nombre, 'Causa de avería'))
            + '</option>'
        )).join('');

        const resultado = await Swal.fire({
            icon: 'warning',
            title: 'Diagnóstico inicial',
            html: `
                <div class="urg-diagnosis-form">
                    <p class="urg-diagnosis-form__intro">
                        Antes de iniciar, registra la clasificación técnica de la falla.
                        ${escapar(mensajeInicio)}
                    </p>

                    <label for="swalTipoFalla">Tipo de falla *</label>
                    <select id="swalTipoFalla" class="swal2-select">
                        <option value="">Selecciona el tipo de falla</option>
                        ${opcionesTipo}
                    </select>

                    <label for="swalCausaAveria">Causa de la avería *</label>
                    <select id="swalCausaAveria" class="swal2-select">
                        <option value="">Selecciona la causa de avería</option>
                        ${opcionesCausa}
                    </select>

                    <div id="swalGrupoExplicacion" hidden>
                        <label for="swalExplicacionCausa">
                            Explicación provisional *
                        </label>
                        <textarea
                            id="swalExplicacionCausa"
                            class="swal2-textarea"
                            maxlength="1500"
                            placeholder="Explica qué se conoce hasta ahora o por qué la causa sigue pendiente."
                        ></textarea>
                        <small>
                            Obligatoria cuando la causa está pendiente, no identificada o por determinar.
                        </small>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Registrar e iniciar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusConfirm: false,
            allowOutsideClick: false,
            heightAuto: false,
            didOpen: () => {
                const causa = document.getElementById('swalCausaAveria');
                causa.addEventListener('change', actualizarExplicacionDiagnostico);
                actualizarExplicacionDiagnostico();
            },
            preConfirm: () => {
                const tipoFallaId = numero(
                    document.getElementById('swalTipoFalla').value
                );
                const causaAveriaId = numero(
                    document.getElementById('swalCausaAveria').value
                );
                const explicacion = document.getElementById(
                    'swalExplicacionCausa'
                ).value.trim();
                const causa = estado.causasAveria.find(
                    (item) => numero(item.id) === causaAveriaId
                );
                const requiereExplicacion = causaRequiereExplicacion(causa);

                if (tipoFallaId < 1) {
                    Swal.showValidationMessage('Selecciona el tipo de falla.');
                    return false;
                }

                if (causaAveriaId < 1) {
                    Swal.showValidationMessage('Selecciona la causa de la avería.');
                    return false;
                }

                if (requiereExplicacion && explicacion.length < 10) {
                    Swal.showValidationMessage(
                        'Explica la causa provisional con al menos 10 caracteres.'
                    );
                    return false;
                }

                return {
                    tipo_falla_id: String(tipoFallaId),
                    causa_averia_id: String(causaAveriaId),
                    causa_desconocida_descripcion: requiereExplicacion
                        ? explicacion
                        : ''
                };
            }
        });

        return resultado.isConfirmed ? resultado.value : null;
    }

    function actualizarExplicacionDiagnostico() {
        const causaSelect = document.getElementById('swalCausaAveria');
        const grupo = document.getElementById('swalGrupoExplicacion');
        const campo = document.getElementById('swalExplicacionCausa');

        if (!causaSelect || !grupo || !campo) {
            return;
        }

        const causaId = numero(causaSelect.value);
        const causa = estado.causasAveria.find(
            (item) => numero(item.id) === causaId
        );
        const requerida = causaRequiereExplicacion(causa);

        grupo.hidden = !requerida;
        campo.required = requerida;

        if (!requerida) {
            campo.value = '';
        }
    }

    function causaRequiereExplicacion(causa) {
        if (!causa) {
            return false;
        }

        if (causa.requiere_explicacion === true || numero(causa.requiere_explicacion) === 1) {
            return true;
        }

        const nombre = texto(causa.nombre, '').toLowerCase();

        return /pendiente|desconoc|por determinar|no identific/.test(nombre);
    }

    async function ejecutarAccion(
        accion,
        solicitudId,
        textoCarga,
        alExito,
        datosExtra = {}
    ) {
        estado.procesando = true;
        bloquearPagina(true);

        const botones = Array.from(
            document.querySelectorAll(
                '[data-urg-action="' + accion + '"][data-id="' + solicitudId + '"]'
            )
        );

        const botonModal = accion === 'aceptar'
            ? $('btnAceptar')
            : accion === 'retirar'
                ? $('btnRetirar')
                : $('btnIniciar');

        botones.forEach((boton) => {
            SistemaUI.estadoBoton(boton, true, textoCarga);
        });

        if (botonModal && !botonModal.hidden) {
            SistemaUI.estadoBoton(botonModal, true, textoCarga);
        }

        try {
            const formulario = new FormData();
            formulario.set('accion', accion);
            formulario.set('solicitud_id', String(solicitudId));

            Object.keys(datosExtra || {}).forEach((clave) => {
                const valor = datosExtra[clave];

                if (valor !== undefined && valor !== null) {
                    formulario.set(clave, String(valor));
                }
            });

            const datos = await SistemaUI.peticionJson(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            if (typeof alExito === 'function') {
                await alExito(datos);
            }

            const modalAbierto = !$('modalUrgencia').hidden;
            const idModal = modalAbierto && estado.detalle
                ? numero(estado.detalle.solicitud_id)
                : 0;

            await cargarInicial(true);

            if (modalAbierto && idModal > 0 && accion !== 'iniciar') {
                await abrirDetalle(idModal);
            }
        } catch (error) {
            await SistemaUI.error(
                'No se completó la operación',
                error.message || 'La información pudo haber cambiado. Actualiza la lista.'
            );
            await cargarInicial(true);
        } finally {
            estado.procesando = false;
            bloquearPagina(false);

            botones.forEach((boton) => {
                SistemaUI.estadoBoton(boton, false);
            });

            if (botonModal) {
                SistemaUI.estadoBoton(botonModal, false);
            }
        }
    }

    function buscarRegistro(solicitudId) {
        return estado.urgencias.find((registro) => numero(registro.solicitud_id) === numero(solicitudId)) || null;
    }

    function iniciarEventos() {
        $('btnActualizar').addEventListener('click', () => cargarInicial(false));

        $('buscarUrgencia').addEventListener('input', (evento) => {
            estado.busqueda = evento.target.value.trim().toLowerCase();
            pintarLista();
        });

        document.querySelectorAll('.urg-filter').forEach((boton) => {
            boton.addEventListener('click', () => {
                estado.filtro = boton.dataset.filter || 'disponibles';
                activarFiltroVisual();
                pintarLista();
            });
        });

        $('listaUrgencias').addEventListener('click', (evento) => {
            const control = evento.target.closest('[data-urg-action]');
            if (!control) return;
            const accion = control.dataset.urgAction;
            const id = numero(control.dataset.id);
            if (accion === 'detalle') abrirDetalle(id);
            if (accion === 'aceptar') aceptarUrgencia(id);
            if (accion === 'iniciar') iniciarUrgencia(id);
        });

        $('btnAbrirCompromiso').addEventListener('click', () => {
            const id = numero($('btnAbrirCompromiso').dataset.solicitudId);
            if (id > 0) abrirDetalle(id);
        });

        document.querySelectorAll('[data-close-modal]').forEach((control) => {
            control.addEventListener('click', () => cerrarModal());
        });

        $('btnAceptar').addEventListener('click', () => aceptarUrgencia(numero($('btnAceptar').dataset.solicitudId)));
        $('btnRetirar').addEventListener('click', () => retirarUrgencia(numero($('btnRetirar').dataset.solicitudId)));
        $('btnIniciar').addEventListener('click', () => iniciarUrgencia(numero($('btnIniciar').dataset.solicitudId)));

        document.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape' && !$('modalUrgencia').hidden) cerrarModal();
        });

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !estado.procesando) cargarInicial(true);
        });
    }

    function programarActualizacion() {
        if (estado.temporizador) window.clearInterval(estado.temporizador);
        estado.temporizador = window.setInterval(() => {
            if (!document.hidden && $('modalUrgencia').hidden && !estado.procesando) {
                cargarInicial(true);
            }
        }, REFRESCO_MS);
    }

    leerParametros(); 
    iniciarEventos();
    activarFiltroVisual();
    cargarInicial(false);
    programarActualizacion();
})();
</script>
</body>
</html>