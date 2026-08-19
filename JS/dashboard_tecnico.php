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

$cssDashboardTecnico = __DIR__ . '/../css/style_dashboard_tecnico.css';
$versionCss = is_file($cssDashboardTecnico)
    ? (string) filemtime($cssDashboardTecnico)
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
        content="Panel operativo del técnico del Sistema de Mantenimiento"
    >
    <title>Dashboard del técnico | Sistema de Mantenimiento</title>
    <link
        rel="stylesheet"
        href="../css/style_dashboard_tecnico.css?v=<?= htmlspecialchars($versionCss, ENT_QUOTES, 'UTF-8') ?>"
    >
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="dtec-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="dtec-icon-sparkles" viewBox="0 0 24 24">
        <path d="m12 3-1.2 3.8L7 8l3.8 1.2L12 13l1.2-3.8L17 8l-3.8-1.2L12 3Z"/>
        <path d="m5 15-.7 2.3L2 18l2.3.7L5 21l.7-2.3L8 18l-2.3-.7L5 15Z"/>
        <path d="m19 13-.7 2.3-2.3.7 2.3.7L19 19l.7-2.3 2.3-.7L19 13Z"/>
    </symbol>
    <symbol id="dtec-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="dtec-icon-user" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="4"/>
        <path d="M4 21a8 8 0 0 1 16 0"/>
    </symbol>
    <symbol id="dtec-icon-activity" viewBox="0 0 24 24">
        <path d="M3 12h4l2.5-6 5 12 2.5-6H21"/>
    </symbol>
    <symbol id="dtec-icon-bolt" viewBox="0 0 24 24">
        <path d="m13 2-9 12h7l-1 8 9-12h-7l1-8Z"/>
    </symbol>
    <symbol id="dtec-icon-tools" viewBox="0 0 24 24">
        <path d="m14.7 6.3 3-3a4 4 0 0 1-5 5l-7.4 7.4a2 2 0 1 1-2.8-2.8l7.4-7.4a4 4 0 0 1 4.8-5.2"/>
        <path d="m15 14 6 6M17 12l2-2"/>
    </symbol>
    <symbol id="dtec-icon-pause" viewBox="0 0 24 24">
        <rect x="5" y="4" width="5" height="16" rx="1"/>
        <rect x="14" y="4" width="5" height="16" rx="1"/>
    </symbol>
    <symbol id="dtec-icon-check" viewBox="0 0 24 24">
        <path d="m5 12 4 4L19 6"/>
    </symbol>
    <symbol id="dtec-icon-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </symbol>
    <symbol id="dtec-icon-shield" viewBox="0 0 24 24">
        <path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="dtec-icon-radio" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="2"/>
        <path d="M8.5 8.5a5 5 0 0 0 0 7M15.5 8.5a5 5 0 0 1 0 7"/>
        <path d="M5.5 5.5a9 9 0 0 0 0 13M18.5 5.5a9 9 0 0 1 0 13"/>
    </symbol>
    <symbol id="dtec-icon-list" viewBox="0 0 24 24">
        <path d="M8 6h12M8 12h12M8 18h12"/>
        <path d="M4 6h.01M4 12h.01M4 18h.01"/>
    </symbol>
    <symbol id="dtec-icon-bell" viewBox="0 0 24 24">
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
        <path d="M10 21h4"/>
    </symbol>
    <symbol id="dtec-icon-arrow" viewBox="0 0 24 24">
        <path d="M5 12h14M14 7l5 5-5 5"/>
    </symbol>
</svg>

<main class="dtec-page">
    <div class="dtec-ambient dtec-ambient--one" aria-hidden="true"></div>
    <div class="dtec-ambient dtec-ambient--two" aria-hidden="true"></div>

    <section class="dtec-heading dtec-hero" aria-labelledby="tituloDashboardTecnico">
        <div class="dtec-hero__pattern" aria-hidden="true"></div>

        <div class="dtec-hero__content">
            <div class="dtec-hero__copy">
                <p class="dtec-eyebrow">
                    <span class="dtec-eyebrow__icon" aria-hidden="true">
                        <svg><use href="#dtec-icon-sparkles"></use></svg>
                    </span>
                    Panel operativo del técnico
                </p>

                <h1 id="tituloDashboardTecnico">
                    Hola, <?= htmlspecialchars($nombreTecnico, ENT_QUOTES, 'UTF-8') ?>
                </h1>

                <p class="dtec-hero__description">
                    Revisa tus actividades, responde urgencias y continúa los trabajos
                    que requieren atención o reanudación manual.
                </p>

                <div class="dtec-hero__meta">
                    <span>
                        <span class="dtec-live-dot" aria-hidden="true"></span>
                        Sesión técnica activa
                    </span>
                    <span>
                        Actualización automática cada <strong>30 segundos</strong>
                    </span>
                </div>
            </div>

            <div class="dtec-hero__actions">
                <div class="dtec-hero__mini-card">
                    <span class="dtec-hero__mini-icon" aria-hidden="true">
                        <svg><use href="#dtec-icon-activity"></use></svg>
                    </span>
                    <div>
                        <small>Centro operativo</small>
                        <strong>Trabajo y respuesta en tiempo real</strong>
                    </div>
                </div>

                <button type="button" class="dtec-button dtec-button--hero" id="btnActualizar">
                    <svg aria-hidden="true"><use href="#dtec-icon-refresh"></use></svg>
                    <span>Actualizar información</span>
                </button>
            </div>
        </div>
    </section>

    <section class="dtec-guides" aria-label="Reglas principales del panel">
        <article>
            <span class="dtec-guide-icon" aria-hidden="true">
                <svg><use href="#dtec-icon-activity"></use></svg>
            </span>
            <div>
                <strong>Una ejecución activa</strong>
                <p>El panel concentra tu actividad actual y evita perder de vista el trabajo que estás realizando.</p>
            </div>
        </article>

        <article>
            <span class="dtec-guide-icon" aria-hidden="true">
                <svg><use href="#dtec-icon-bolt"></use></svg>
            </span>
            <div>
                <strong>Urgencias disponibles</strong>
                <p>Las urgencias abiertas se publican directamente para que puedas revisarlas y aceptarlas.</p>
            </div>
        </article>

        <article>
            <span class="dtec-guide-icon" aria-hidden="true">
                <svg><use href="#dtec-icon-refresh"></use></svg>
            </span>
            <div>
                <strong>Reanudación manual</strong>
                <p>Cuando una urgencia termina, tú decides cuándo continuar el mantenimiento que quedó pausado.</p>
            </div>
        </article>
    </section>

    <div class="dtec-status" id="estadoCarga" role="status" aria-live="polite">
        Cargando información...
    </div>

    <section class="dtec-profile" aria-label="Información del técnico">
        <div class="dtec-profile__avatar" aria-hidden="true">
            <svg><use href="#dtec-icon-user"></use></svg>
        </div>

        <div class="dtec-profile__copy">
            <span>Sesión activa</span>
            <strong id="perfilNombre">Técnico</strong>
            <small id="perfilDetalle">Cargando turno, especialidad y departamento...</small>
        </div>

        <div class="dtec-profile__state">
            <i aria-hidden="true"></i>
            Cuenta activa
        </div>
    </section>

    <section class="dtec-alert dtec-alert--danger" id="alertaUrgencias" hidden aria-live="polite">
        <div class="dtec-alert__icon" aria-hidden="true">
            <svg><use href="#dtec-icon-bolt"></use></svg>
        </div>

        <div>
            <strong id="alertaUrgenciasTitulo">Hay urgencias disponibles</strong>
            <p id="alertaUrgenciasTexto">Revisa la información antes de aceptar una urgencia.</p>
        </div>

        <a href="urgencias_disponibles.php">
            Ver urgencias
            <svg aria-hidden="true"><use href="#dtec-icon-arrow"></use></svg>
        </a>
    </section>

    <section class="dtec-alert dtec-alert--warning" id="alertaReanudar" hidden aria-live="polite">
        <div class="dtec-alert__icon" aria-hidden="true">
            <svg><use href="#dtec-icon-refresh"></use></svg>
        </div>

        <div>
            <strong>Mantenimiento esperando reanudación</strong>
            <p>
                La urgencia terminó o fue cancelada. El mantenimiento anterior continúa
                pausado hasta que tú decidas reanudarlo.
            </p>
        </div>

        <a href="mantenimiento_activo.php?filtro=pausados">
            Revisar pausados
            <svg aria-hidden="true"><use href="#dtec-icon-arrow"></use></svg>
        </a>
    </section>

    <section class="dtec-kpis" aria-label="Resumen de trabajo">
        <a class="dtec-kpi dtec-kpi--danger" href="urgencias_disponibles.php">
            <span class="dtec-kpi__icon" aria-hidden="true">
                <svg><use href="#dtec-icon-bolt"></use></svg>
            </span>
            <span>Urgencias disponibles</span>
            <strong id="kpiUrgencias">0</strong>
            <small>Puedes aceptarlas directamente</small>
        </a>

        <a class="dtec-kpi dtec-kpi--assigned" href="mantenimientos_asignados.php">
            <span class="dtec-kpi__icon" aria-hidden="true">
                <svg><use href="#dtec-icon-tools"></use></svg>
            </span>
            <span>Asignados por atender</span>
            <strong id="kpiAsignados">0</strong>
            <small>Programados o atrasados</small>
        </a>

        <a class="dtec-kpi dtec-kpi--success" href="mantenimiento_activo.php">
            <span class="dtec-kpi__icon" aria-hidden="true">
                <svg><use href="#dtec-icon-activity"></use></svg>
            </span>
            <span>En proceso</span>
            <strong id="kpiProceso">0</strong>
            <small>Actividad que estás realizando</small>
        </a>

        <a class="dtec-kpi dtec-kpi--warning" href="mantenimiento_activo.php?filtro=pausados">
            <span class="dtec-kpi__icon" aria-hidden="true">
                <svg><use href="#dtec-icon-pause"></use></svg>
            </span>
            <span>En pausa</span>
            <strong id="kpiPausados">0</strong>
            <small id="kpiReanudarTexto">0 listos para reanudar</small>
        </a>

        <a class="dtec-kpi dtec-kpi--completed" href="mantenimientos_finalizados.php">
            <span class="dtec-kpi__icon" aria-hidden="true">
                <svg><use href="#dtec-icon-check"></use></svg>
            </span>
            <span>Terminados esta semana</span>
            <strong id="kpiTerminados">0</strong>
            <small>Participaciones concluidas</small>
        </a>
    </section>

    <section class="dtec-card dtec-card--featured">
        <header class="dtec-card__head">
            <div class="dtec-section-heading">
                <span class="dtec-section-heading__icon" aria-hidden="true">
                    <svg><use href="#dtec-icon-activity"></use></svg>
                </span>
                <div>
                    <p class="dtec-eyebrow">Actividad actual</p>
                    <h2>Trabajo en proceso</h2>
                    <span>El sistema permite una sola ejecución activa por técnico.</span>
                </div>
            </div>

            <a class="dtec-card__link" href="mantenimiento_activo.php">
                Abrir actividad
                <svg aria-hidden="true"><use href="#dtec-icon-arrow"></use></svg>
            </a>
        </header>

        <div id="actividadActual" class="dtec-current"></div>
    </section>

    <section class="dtec-grid dtec-grid--main">
        <article class="dtec-card dtec-card--danger">
            <header class="dtec-card__head">
                <div class="dtec-section-heading">
                    <span class="dtec-section-heading__icon" aria-hidden="true">
                        <svg><use href="#dtec-icon-bolt"></use></svg>
                    </span>
                    <div>
                        <p class="dtec-eyebrow">Respuesta inmediata</p>
                        <h2>Urgencias disponibles</h2>
                        <p>Permanecen visibles mientras tengan lugares y sigan abiertas.</p>
                    </div>
                </div>

                <a class="dtec-card__link" href="urgencias_disponibles.php">
                    Ver todas
                    <svg aria-hidden="true"><use href="#dtec-icon-arrow"></use></svg>
                </a>
            </header>

            <div id="listaUrgencias" class="dtec-list"></div>
        </article>

        <article class="dtec-card">
            <header class="dtec-card__head">
                <div class="dtec-section-heading">
                    <span class="dtec-section-heading__icon" aria-hidden="true">
                        <svg><use href="#dtec-icon-tools"></use></svg>
                    </span>
                    <div>
                        <p class="dtec-eyebrow">Agenda operativa</p>
                        <h2>Mantenimientos asignados</h2>
                        <p>Próximas actividades asignadas por el administrador.</p>
                    </div>
                </div>

                <a class="dtec-card__link" href="mantenimientos_asignados.php">
                    Ver asignados
                    <svg aria-hidden="true"><use href="#dtec-icon-arrow"></use></svg>
                </a>
            </header>

            <div id="listaAsignados" class="dtec-list"></div>
        </article>
    </section>

    <section class="dtec-grid dtec-grid--secondary">
        <article class="dtec-card dtec-card--warning">
            <header class="dtec-card__head">
                <div class="dtec-section-heading">
                    <span class="dtec-section-heading__icon" aria-hidden="true">
                        <svg><use href="#dtec-icon-pause"></use></svg>
                    </span>
                    <div>
                        <p class="dtec-eyebrow">Continuidad del trabajo</p>
                        <h2>Trabajos pausados</h2>
                        <p>Una urgencia terminada no reanuda automáticamente el mantenimiento anterior.</p>
                    </div>
                </div>

                <a class="dtec-card__link" href="mantenimiento_activo.php?filtro=pausados">
                    Revisar pausados
                    <svg aria-hidden="true"><use href="#dtec-icon-arrow"></use></svg>
                </a>
            </header>

            <div id="listaPausados" class="dtec-list"></div>
        </article>

        <article class="dtec-card dtec-card--danger">
            <header class="dtec-card__head">
                <div class="dtec-section-heading">
                    <span class="dtec-section-heading__icon" aria-hidden="true">
                        <svg><use href="#dtec-icon-radio"></use></svg>
                    </span>
                    <div>
                        <p class="dtec-eyebrow">Participación urgente</p>
                        <h2>Mis urgencias</h2>
                        <p>Urgencias aceptadas o en las que ya estás participando.</p>
                    </div>
                </div>

                <a class="dtec-card__link" href="urgencias_disponibles.php?filtro=mias">
                    Abrir
                    <svg aria-hidden="true"><use href="#dtec-icon-arrow"></use></svg>
                </a>
            </header>

            <div id="listaMisUrgencias" class="dtec-list"></div>
        </article>
    </section>

    <section class="dtec-grid dtec-grid--bottom">
        <article class="dtec-card dtec-card--success">
            <header class="dtec-card__head">
                <div class="dtec-section-heading">
                    <span class="dtec-section-heading__icon" aria-hidden="true">
                        <svg><use href="#dtec-icon-check"></use></svg>
                    </span>
                    <div>
                        <p class="dtec-eyebrow">Trabajo concluido</p>
                        <h2>Finalizados recientemente</h2>
                        <p>Últimas participaciones concluidas.</p>
                    </div>
                </div>

                <a class="dtec-card__link" href="mantenimientos_finalizados.php">
                    Historial
                    <svg aria-hidden="true"><use href="#dtec-icon-arrow"></use></svg>
                </a>
            </header>

            <div id="listaFinalizados" class="dtec-list"></div>
        </article>

        <article class="dtec-card">
            <header class="dtec-card__head">
                <div class="dtec-section-heading">
                    <span class="dtec-section-heading__icon" aria-hidden="true">
                        <svg><use href="#dtec-icon-bell"></use></svg>
                    </span>
                    <div>
                        <p class="dtec-eyebrow">Centro de avisos</p>
                        <h2>Avisos recientes</h2>
                        <p>Notificaciones relacionadas con tu trabajo.</p>
                    </div>
                </div>

                <span class="dtec-unread" id="contadorNoLeidas">0 nuevas</span>
            </header>

            <div id="listaAvisos" class="dtec-list"></div>
        </article>
    </section>

    <footer class="dtec-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Panel operativo del técnico · Los Chapeteados División Petfood</span>
    </footer>

    <div class="dtec-tools-background" aria-hidden="true"></div>
</main>

<script>
(() => {
    'use strict';

    const endpoint = '../funciones/dashboard_tecnico_funciones.php';
    const $ = (id) => document.getElementById(id);
    let cargando = false;
    let temporizador = null;

    const etiquetas = {
        CORRECTIVO_PROGRAMABLE: 'Correctivo programable',
        MODIFICACION_MEJORA: 'Modificación o mejora',
        CORRECTIVO_URGENTE: 'Correctivo urgente',
        RUTINARIO: 'Rutinario',
        PENDIENTE: 'Pendiente',
        APROBADO: 'Aprobado',
        AGENDADO: 'Agendado',
        EN_PROCESO: 'En proceso',
        PAUSADO: 'Pausado',
        ATRASADO: 'Atrasado',
        TERMINADO: 'Terminado',
        CANCELADO: 'Cancelado',
        ASIGNADO: 'Asignado',
        ACEPTADO: 'Aceptado',
        PAUSADA: 'Pausada',
        TERMINADA: 'Terminada',
        BAJO: 'Bajo',
        MEDIO: 'Medio',
        ALTO: 'Alto',
        URGENTE: 'Urgente',
        BAJA: 'Baja',
        MEDIA: 'Media',
        ALTA: 'Alta',
        MANUAL: 'Pausa manual',
        URGENCIA: 'Pausa por urgencia',
        ADMINISTRATIVA: 'Pausa administrativa',
        FALTA_RECURSO: 'Falta de recurso',
        CAMBIO_PRIORIDAD: 'Cambio de prioridad',
        OTRO: 'Otra causa'
    };

    function escapar(valor) {
        return String(valor ?? '').replace(/[&<>'"]/g, (caracter) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
        }[caracter]));
    }

    function etiqueta(valor) {
        const clave = String(valor || '').toUpperCase();
        return etiquetas[clave] || clave.replaceAll('_', ' ').toLowerCase().replace(/^./, (c) => c.toUpperCase());
    }

    function numero(valor) {
        const n = Number(valor || 0);
        return Number.isFinite(n) ? n : 0;
    }

    function fecha(valor, incluirHora = false) {
        if (!valor) return 'Sin fecha';
        const texto = String(valor);
        const normalizado = texto.length === 10 ? texto + 'T12:00:00' : texto.replace(' ', 'T');
        const objeto = new Date(normalizado);
        if (Number.isNaN(objeto.getTime())) return escapar(texto);
        const opciones = incluirHora
            ? { dateStyle: 'medium', timeStyle: 'short' }
            : { dateStyle: 'medium' };
        return new Intl.DateTimeFormat('es-MX', opciones).format(objeto);
    }

    function duracion(segundos) {
        let total = Math.max(0, Math.floor(numero(segundos)));
        const horas = Math.floor(total / 3600);
        total %= 3600;
        const minutos = Math.floor(total / 60);
        if (horas > 0) return horas + ' h ' + minutos + ' min';
        return minutos + ' min';
    }

    function vacio(texto) {
        return '<div class="dtec-empty">' + escapar(texto) + '</div>';
    }

    function estadoCarga(texto, tipo = '') {
        const elemento = $('estadoCarga');
        elemento.textContent = texto;
        elemento.className = 'dtec-status' + (tipo ? ' dtec-status--' + tipo : '');
    }

    function mostrarPerfil(perfil) {
        $('perfilNombre').textContent = perfil.nombre_completo || 'Técnico';
        const partes = [perfil.departamento, perfil.turno ? 'Turno ' + etiqueta(perfil.turno) : '', perfil.especialidad]
            .filter(Boolean);
        $('perfilDetalle').textContent = partes.join(' · ') || 'Sin datos adicionales';
    }

    function mostrarResumen(resumen) {
        $('kpiUrgencias').textContent = numero(resumen.urgencias_disponibles).toLocaleString('es-MX');
        $('kpiAsignados').textContent = numero(resumen.asignados_pendientes).toLocaleString('es-MX');
        $('kpiProceso').textContent = numero(resumen.en_proceso).toLocaleString('es-MX');
        $('kpiPausados').textContent = numero(resumen.pausados).toLocaleString('es-MX');
        $('kpiTerminados').textContent = numero(resumen.terminados_semana).toLocaleString('es-MX');
        $('kpiReanudarTexto').textContent = numero(resumen.listos_reanudar).toLocaleString('es-MX') + ' listos para reanudar';

        const urgencias = numero(resumen.urgencias_disponibles);
        $('alertaUrgencias').hidden = urgencias < 1;
        $('alertaUrgenciasTitulo').textContent = urgencias === 1
            ? 'Hay 1 urgencia disponible'
            : 'Hay ' + urgencias + ' urgencias disponibles';
        $('alertaUrgenciasTexto').textContent = 'Las urgencias se publican de inmediato y no requieren validación administrativa para que puedas verlas.';

        $('alertaReanudar').hidden = numero(resumen.listos_reanudar) < 1;
    }

    function mostrarActividad(rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            $('actividadActual').innerHTML = vacio('No tienes una ejecución activa en este momento.');
            return;
        }

        $('actividadActual').innerHTML = rows.map((r) => `
            <article class="dtec-current__item ${r.tipo_solicitud === 'CORRECTIVO_URGENTE' ? 'is-urgent' : ''}">
                <div class="dtec-current__main">
                    <div class="dtec-row-title">
                        <span class="dtec-badge dtec-badge--${r.tipo_solicitud === 'CORRECTIVO_URGENTE' ? 'danger' : 'success'}">${escapar(etiqueta(r.tipo_solicitud))}</span>
                        <strong>${escapar(r.folio || 'Sin folio')}</strong>
                    </div>
                    <h3>${escapar(r.codigo_equipo || 'Sin código')} · ${escapar(r.nombre_equipo || 'Equipo')}</h3>
                    <p>${escapar(r.descripcion_solicitud || 'Sin descripción')}</p>
                    <div class="dtec-meta">
                        <span>${escapar(r.area || 'Sin área')}</span>
                        <span>Inicio: ${fecha(r.fecha_hora_inicio, true)}</span>
                        ${numero(r.participantes_urgencia) > 0 ? `<span>${numero(r.participantes_urgencia)} participantes</span>` : ''}
                    </div>
                </div>
                <div class="dtec-current__time">
                    <span>Tiempo activo aproximado</span>
                    <strong>${duracion(r.segundos_activos_estimados)}</strong>
                    <a class="dtec-action" href="mantenimiento_activo.php?ejecucion_id=${numero(r.ejecucion_id)}">Continuar</a>
                </div>
            </article>
        `).join('');
    }

    function mostrarUrgencias(rows) {
        $('listaUrgencias').innerHTML = Array.isArray(rows) && rows.length
            ? rows.map((r) => `
                <a class="dtec-item" href="urgencias_disponibles.php?solicitud_id=${numero(r.solicitud_id)}">
                    <span class="dtec-badge dtec-badge--danger">${escapar(etiqueta(r.nivel_riesgo))}</span>
                    <div>
                        <strong>${escapar(r.folio)} · ${escapar(r.codigo_equipo || 'Sin código')}</strong>
                        <span>${escapar(r.nombre_equipo)} · ${escapar(r.area || 'Sin área')}</span>
                        <span>${numero(r.tecnicos_aceptaron)} de ${numero(r.limite_tecnicos)} técnicos</span>
                    </div>
                    <small>${escapar(r.estado === 'EN_PROCESO' ? 'Ya iniciada' : 'Disponible')}</small>
                </a>
            `).join('')
            : vacio('No hay urgencias disponibles para aceptar.');
    }

    function mostrarAsignados(rows) {
        $('listaAsignados').innerHTML = Array.isArray(rows) && rows.length
            ? rows.map((r) => `
                <a class="dtec-item" href="mantenimientos_asignados.php?solicitud_id=${numero(r.solicitud_id)}">
                    <span class="dtec-badge ${r.estado === 'ATRASADO' ? 'dtec-badge--danger' : ''}">${escapar(etiqueta(r.estado))}</span>
                    <div>
                        <strong>${escapar(r.folio)} · ${escapar(r.codigo_equipo || 'Sin código')}</strong>
                        <span>${escapar(r.nombre_equipo)} · ${escapar(r.area || 'Sin área')}</span>
                        <span>${escapar(etiqueta(r.tipo_solicitud))}</span>
                    </div>
                    <small>${r.fecha_programada ? fecha(r.fecha_programada) : 'Sin fecha'}</small>
                </a>
            `).join('')
            : vacio('No tienes mantenimientos asignados pendientes.');
    }

    function mostrarPausados(rows) {
        $('listaPausados').innerHTML = Array.isArray(rows) && rows.length
            ? rows.map((r) => {
                const listo = numero(r.puede_reanudar) === 1;
                const textoPausa = r.motivo_pausa === 'URGENCIA'
                    ? (listo ? 'La urgencia terminó. Reanudación manual disponible.' : 'Pausado mientras continúa la urgencia.')
                    : etiqueta(r.motivo_pausa);
                return `
                    <a class="dtec-item" href="mantenimiento_activo.php?ejecucion_id=${numero(r.ejecucion_id)}">
                        <span class="dtec-badge ${listo ? 'dtec-badge--warning' : ''}">${listo ? 'Reanudar' : 'Pausado'}</span>
                        <div>
                            <strong>${escapar(r.folio)} · ${escapar(r.codigo_equipo || 'Sin código')}</strong>
                            <span>${escapar(r.nombre_equipo)}</span>
                            <span>${escapar(textoPausa)}</span>
                        </div>
                        <small>${duracion(r.segundos_pausa_actual)}</small>
                    </a>
                `;
            }).join('')
            : vacio('No tienes mantenimientos pausados.');
    }

    function mostrarMisUrgencias(rows) {
        $('listaMisUrgencias').innerHTML = Array.isArray(rows) && rows.length
            ? rows.map((r) => `
                <a class="dtec-item" href="${r.estado_participacion === 'EN_PROCESO' ? 'mantenimiento_activo.php' : 'urgencias_disponibles.php'}?solicitud_id=${numero(r.solicitud_id)}">
                    <span class="dtec-badge dtec-badge--danger">${escapar(etiqueta(r.estado_participacion))}</span>
                    <div>
                        <strong>${escapar(r.folio)} · ${escapar(r.codigo_equipo || 'Sin código')}</strong>
                        <span>${escapar(r.nombre_equipo)} · ${escapar(r.area || 'Sin área')}</span>
                        <span>${numero(r.participantes)} participantes</span>
                    </div>
                    <small>${fecha(r.fecha_aceptacion, true)}</small>
                </a>
            `).join('')
            : vacio('No tienes urgencias aceptadas o activas.');
    }

    function mostrarFinalizados(rows) {
        $('listaFinalizados').innerHTML = Array.isArray(rows) && rows.length
            ? rows.map((r) => `
                <a class="dtec-item" href="mantenimientos_finalizados.php?solicitud_id=${numero(r.solicitud_id)}">
                    <span class="dtec-badge dtec-badge--success">${escapar(r.trabajo_quedo ? etiqueta(r.trabajo_quedo) : 'Terminado')}</span>
                    <div>
                        <strong>${escapar(r.folio)} · ${escapar(r.codigo_equipo || 'Sin código')}</strong>
                        <span>${escapar(r.nombre_equipo)} · ${escapar(etiqueta(r.tipo_solicitud))}</span>
                        <span>Tiempo activo: ${duracion(r.total_segundos_activos)}</span>
                    </div>
                    <small>${fecha(r.fecha_hora_fin, true)}</small>
                </a>
            `).join('')
            : vacio('Todavía no hay participaciones finalizadas.');
    }

    function mostrarAvisos(rows, noLeidas) {
        $('contadorNoLeidas').textContent = numero(noLeidas).toLocaleString('es-MX') + ' nuevas';
        $('listaAvisos').innerHTML = Array.isArray(rows) && rows.length
            ? rows.map((r) => `
                <div class="dtec-item dtec-item--notice ${numero(r.leida) === 0 ? 'is-unread' : ''}">
                    <span class="dtec-badge ${r.tipo === 'URGENTE' || r.tipo === 'DANGER' ? 'dtec-badge--danger' : ''}">${escapar(etiqueta(r.tipo))}</span>
                    <div>
                        <strong>${escapar(r.titulo)}</strong>
                        <span class="dtec-wrap">${escapar(r.mensaje)}</span>
                    </div>
                    <small>${fecha(r.fecha_creacion, true)}</small>
                </div>
            `).join('')
            : vacio('No tienes avisos recientes.');
    }

    async function cargarDatos(mostrarMensaje = true) {
        if (cargando) return;
        cargando = true;
        $('btnActualizar').disabled = true;
        if (mostrarMensaje) estadoCarga('Actualizando información...');

        try {
            const respuesta = await fetch(endpoint + '?accion=inicial&_=' + Date.now(), {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            let datos;
            try {
                datos = await respuesta.json();
            } catch (error) {
                throw new Error('La respuesta del servidor no es válida.');
            }

            if (!respuesta.ok || !datos.success) {
                if (datos.sesion_expirada && datos.redirect) {
                    window.location.href = datos.redirect;
                    return;
                }
                throw new Error(datos.mensaje || 'No fue posible cargar el dashboard.');
            }

            mostrarPerfil(datos.perfil || {});
            mostrarResumen(datos.resumen || {});
            mostrarActividad(datos.actividades_actuales || []);
            mostrarUrgencias(datos.urgencias_disponibles || []);
            mostrarAsignados(datos.asignados || []);
            mostrarPausados(datos.pausados || []);
            mostrarMisUrgencias(datos.mis_urgencias || []);
            mostrarFinalizados(datos.finalizados_recientes || []);
            mostrarAvisos(datos.avisos || [], datos.avisos_no_leidos || 0);

            const advertencia = numero(datos.resumen && datos.resumen.en_proceso) > 1
                ? ' Se detectó más de una ejecución activa; el administrador debe revisar la consistencia.'
                : '';
            estadoCarga('Información actualizada: ' + (datos.hora_servidor || 'ahora') + '.' + advertencia, advertencia ? 'warning' : 'ok');
        } catch (error) {
            estadoCarga(error.message || 'No fue posible cargar la información.', 'error');
        } finally {
            cargando = false;
            $('btnActualizar').disabled = false;
        }
    }
 
    $('btnActualizar').addEventListener('click', () => cargarDatos(true));
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) cargarDatos(false);
    });

    cargarDatos(true);
    temporizador = window.setInterval(() => cargarDatos(false), 30000);
    window.addEventListener('beforeunload', () => {
        if (temporizador) window.clearInterval(temporizador);
    });
})();
</script>
</body>
</html>