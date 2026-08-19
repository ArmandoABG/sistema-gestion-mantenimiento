<?php

declare(strict_types=1);

/* Interfaz visual profesional v2: mayor legibilidad y gráficas comparativas. */

require_once __DIR__ . '/../inc/seguridad.php';
sm_requerir_sesion(['ADMIN'], false);

$nombreAdmin = trim((string) ($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'Administrador'));
$csrf = sm_token_csrf();
$cssDashboard = __DIR__ . '/../css/style_dashboard_admin.css';
$versionCss = file_exists($cssDashboard) ? (string) filemtime($cssDashboard) : (string) time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="theme-color" content="#09233b">
    <meta name="description" content="Panel operativo del administrador del Sistema de Mantenimiento">
    <title>Dashboard del administrador | Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="../css/style_dashboard_admin.css?v=<?= htmlspecialchars($versionCss, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="da-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="da-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="da-icon-inbox" viewBox="0 0 24 24">
        <path d="M4 4h16l2 9v7H2v-7l2-9Z"/>
        <path d="M2 13h5l2 3h6l2-3h5"/>
    </symbol>
    <symbol id="da-icon-calendar" viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path d="M16 3v4M8 3v4M3 10h18"/>
    </symbol>
    <symbol id="da-icon-bolt" viewBox="0 0 24 24">
        <path d="m13 2-9 12h8l-1 8 9-12h-8l1-8Z"/>
    </symbol>
    <symbol id="da-icon-today" viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path d="M16 3v4M8 3v4M3 10h18M8 14h3v3H8z"/>
    </symbol>
    <symbol id="da-icon-play" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m10 8 6 4-6 4V8Z"/>
    </symbol>
    <symbol id="da-icon-pause" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M9.5 8.5v7M14.5 8.5v7"/>
    </symbol>
    <symbol id="da-icon-clock-alert" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2M19 5l2-2"/>
    </symbol>
    <symbol id="da-icon-repeat" viewBox="0 0 24 24">
        <path d="M17 2l4 4-4 4"/>
        <path d="M3 11V9a3 3 0 0 1 3-3h15M7 22l-4-4 4-4"/>
        <path d="M21 13v2a3 3 0 0 1-3 3H3"/>
    </symbol>
    <symbol id="da-icon-clipboard" viewBox="0 0 24 24">
        <rect x="5" y="4" width="14" height="17" rx="2"/>
        <path d="M9 4V2h6v2M9 9h6M9 13h6M9 17h4"/>
    </symbol>
    <symbol id="da-icon-users" viewBox="0 0 24 24">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
    </symbol>
    <symbol id="da-icon-chart" viewBox="0 0 24 24">
        <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>
    </symbol>
    <symbol id="da-icon-tools" viewBox="0 0 24 24">
        <path d="M14.7 6.3a4 4 0 0 0-5-5L7.4 3.6l3 3 2.3-2.3a4 4 0 0 0 2 2Z"/>
        <path d="m10.3 6.7-8.6 8.6a2.1 2.1 0 0 0 3 3l8.6-8.6"/>
        <path d="m14 14 6 6M17 11l-2 2 6 6 2-2-6-6Z"/>
    </symbol>
    <symbol id="da-icon-check" viewBox="0 0 24 24">
        <path d="m5 12 4 4L19 6"/>
    </symbol>
    <symbol id="da-icon-warning" viewBox="0 0 24 24">
        <path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
        <path d="M12 9v4M12 17h.01"/>
    </symbol>
    <symbol id="da-icon-arrow" viewBox="0 0 24 24">
        <path d="M5 12h14M13 6l6 6-6 6"/>
    </symbol>
    <symbol id="da-icon-activity" viewBox="0 0 24 24">
        <path d="M3 12h4l2-7 4 14 2-7h6"/>
    </symbol>
    <symbol id="da-icon-sparkles" viewBox="0 0 24 24">
        <path d="m12 3-1.2 3.8L7 8l3.8 1.2L12 13l1.2-3.8L17 8l-3.8-1.2L12 3ZM5 15l-.7 2.3L2 18l2.3.7L5 21l.7-2.3L8 18l-2.3-.7L5 15ZM19 13l-.7 2.3-2.3.7 2.3.7L19 19l.7-2.3 2.3-.7-2.3-.7L19 13Z"/>
    </symbol>
    <symbol id="da-icon-history" viewBox="0 0 24 24">
        <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>
        <path d="M3 3v5h5M12 7v5l3 2"/>
    </symbol>
    <symbol id="da-icon-chevron" viewBox="0 0 24 24">
        <path d="m9 18 6-6-6-6"/>
    </symbol>
</svg>

<main class="da-page">
    <div class="da-ambient da-ambient--one" aria-hidden="true"></div>
    <div class="da-ambient da-ambient--two" aria-hidden="true"></div>
    <div class="da-tools-background" aria-hidden="true"></div>

    <section class="da-hero" aria-labelledby="tituloDashboard">
        <div class="da-hero__pattern" aria-hidden="true"></div>
        <div class="da-hero__content">
            <div class="da-hero__copy">
                <div class="da-eyebrow">
                    <span class="da-eyebrow__icon"><svg><use href="#da-icon-sparkles"></use></svg></span>
                    Centro de control operativo
                </div>
                <h1 id="tituloDashboard">Hola, <span><?= htmlspecialchars($nombreAdmin, ENT_QUOTES, 'UTF-8') ?></span></h1>
                <p>Visualiza prioridades, programación, carga de trabajo y resultados de mantenimiento desde un solo lugar.</p>

                <div class="da-hero__meta">
                    <span><span class="da-live-dot" aria-hidden="true"></span> Información operativa en tiempo real</span>
                    <span id="fechaActual"></span>
                </div>
            </div>

            <div class="da-hero__actions">
                <div class="da-hero__mini-card" aria-hidden="true">
                    <span class="da-hero__mini-icon"><svg><use href="#da-icon-activity"></use></svg></span>
                    <div>
                        <small>Estado general</small>
                        <strong>Panel operativo</strong>
                    </div>
                </div>

                <button type="button" class="da-button" id="btnActualizar">
                    <span class="da-button__icon"><svg><use href="#da-icon-refresh"></use></svg></span>
                    <span class="da-button__text">Actualizar datos</span>
                    <span class="da-button__loader" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </section>

    <div class="da-status" id="estadoCarga" role="status" aria-live="polite">
        <span class="da-status__icon"><span class="da-status__pulse"></span></span>
        <span class="da-status__text">Cargando información del dashboard...</span>
        <span class="da-status__time" id="estadoHora"></span>
    </div>

    <section class="da-section" aria-labelledby="tituloIndicadores">
        <div class="da-section-title">
            <div>
                <span class="da-section-title__kicker">Visión inmediata</span>
                <h2 id="tituloIndicadores">Indicadores principales</h2>
            </div>
            <p>Selecciona cualquier indicador para abrir su módulo relacionado.</p>
        </div>

        <div class="da-kpis" aria-label="Indicadores principales">
            <a href="solicitudes_pendientes.php" class="da-kpi da-kpi--warning" data-kpi="pendientes">
                <span class="da-kpi__glow" aria-hidden="true"></span>
                <span class="da-kpi__icon"><svg><use href="#da-icon-inbox"></use></svg></span>
                <span class="da-kpi__content">
                    <span class="da-kpi__label">Por revisar</span>
                    <strong id="kpiPendientes">0</strong>
                    <small>Solicitudes nuevas</small>
                </span>
                <span class="da-kpi__arrow"><svg><use href="#da-icon-arrow"></use></svg></span>
            </a>

            <a href="solicitudes_programacion.php" class="da-kpi da-kpi--primary" data-kpi="programar">
                <span class="da-kpi__glow" aria-hidden="true"></span>
                <span class="da-kpi__icon"><svg><use href="#da-icon-calendar"></use></svg></span>
                <span class="da-kpi__content">
                    <span class="da-kpi__label">Por programar</span>
                    <strong id="kpiProgramar">0</strong>
                    <small>Aprobadas sin fecha</small>
                </span>
                <span class="da-kpi__arrow"><svg><use href="#da-icon-arrow"></use></svg></span>
            </a>

            <a href="solicitudes_historial.php?tipo=urgente" class="da-kpi da-kpi--danger" data-kpi="urgentes">
                <span class="da-kpi__glow" aria-hidden="true"></span>
                <span class="da-kpi__icon"><svg><use href="#da-icon-bolt"></use></svg></span>
                <span class="da-kpi__content">
                    <span class="da-kpi__label">Urgencias abiertas</span>
                    <strong id="kpiUrgentes">0</strong>
                    <small>Aceptación directa</small>
                </span>
                <span class="da-kpi__arrow"><svg><use href="#da-icon-arrow"></use></svg></span>
            </a>

            <a href="agenda_semanal.php?fecha=hoy" class="da-kpi da-kpi--info" data-kpi="hoy">
                <span class="da-kpi__glow" aria-hidden="true"></span>
                <span class="da-kpi__icon"><svg><use href="#da-icon-today"></use></svg></span>
                <span class="da-kpi__content">
                    <span class="da-kpi__label">Actividades de hoy</span>
                    <strong id="kpiHoy">0</strong>
                    <small>Sin límite por técnico</small>
                </span>
                <span class="da-kpi__arrow"><svg><use href="#da-icon-arrow"></use></svg></span>
            </a>

            <a href="solicitudes_historial.php?estado=en_proceso" class="da-kpi da-kpi--success" data-kpi="proceso">
                <span class="da-kpi__glow" aria-hidden="true"></span>
                <span class="da-kpi__icon"><svg><use href="#da-icon-play"></use></svg></span>
                <span class="da-kpi__content">
                    <span class="da-kpi__label">En proceso</span>
                    <strong id="kpiProceso">0</strong>
                    <small>Actividades iniciadas</small>
                </span>
                <span class="da-kpi__arrow"><svg><use href="#da-icon-arrow"></use></svg></span>
            </a>

            <a href="solicitudes_historial.php?estado=pausado" class="da-kpi da-kpi--neutral" data-kpi="pausados">
                <span class="da-kpi__glow" aria-hidden="true"></span>
                <span class="da-kpi__icon"><svg><use href="#da-icon-pause"></use></svg></span>
                <span class="da-kpi__content">
                    <span class="da-kpi__label">En pausa</span>
                    <strong id="kpiPausados">0</strong>
                    <small>Incluye pausa por urgencia</small>
                </span>
                <span class="da-kpi__arrow"><svg><use href="#da-icon-arrow"></use></svg></span>
            </a>

            <a href="solicitudes_historial.php?estado=atrasado" class="da-kpi da-kpi--danger" data-kpi="atrasados">
                <span class="da-kpi__glow" aria-hidden="true"></span>
                <span class="da-kpi__icon"><svg><use href="#da-icon-clock-alert"></use></svg></span>
                <span class="da-kpi__content">
                    <span class="da-kpi__label">Atrasados</span>
                    <strong id="kpiAtrasados">0</strong>
                    <small>Siguen disponibles</small>
                </span>
                <span class="da-kpi__arrow"><svg><use href="#da-icon-arrow"></use></svg></span>
            </a>

            <a href="rutinas.php?estado=pendiente" class="da-kpi da-kpi--warning" data-kpi="rutinas">
                <span class="da-kpi__glow" aria-hidden="true"></span>
                <span class="da-kpi__icon"><svg><use href="#da-icon-repeat"></use></svg></span>
                <span class="da-kpi__content">
                    <span class="da-kpi__label">Rutinas por programar</span>
                    <strong id="kpiRutinas">0</strong>
                    <small>Notificaciones pendientes</small>
                </span>
                <span class="da-kpi__arrow"><svg><use href="#da-icon-arrow"></use></svg></span>
            </a>
        </div>
    </section>

    <section class="da-section" aria-labelledby="tituloAcciones">
        <div class="da-section-title da-section-title--compact">
            <div>
                <span class="da-section-title__kicker">Navegación directa</span>
                <h2 id="tituloAcciones">Accesos rápidos</h2>
            </div>
        </div>

        <div class="da-actions" aria-label="Accesos rápidos">
            <a href="solicitudes_pendientes.php" class="da-action-card">
                <span class="da-action-card__number">01</span>
                <span class="da-action-card__icon"><svg><use href="#da-icon-clipboard"></use></svg></span>
                <span class="da-action-card__body">
                    <strong>Revisar solicitudes</strong>
                    <span>Aprobar, rechazar o corregir datos</span>
                </span>
                <span class="da-action-card__arrow"><svg><use href="#da-icon-chevron"></use></svg></span>
            </a>

            <a href="solicitudes_programacion.php" class="da-action-card">
                <span class="da-action-card__number">02</span>
                <span class="da-action-card__icon"><svg><use href="#da-icon-calendar"></use></svg></span>
                <span class="da-action-card__body">
                    <strong>Programar y asignar</strong>
                    <span>Seleccionar fecha y técnicos</span>
                </span>
                <span class="da-action-card__arrow"><svg><use href="#da-icon-chevron"></use></svg></span>
            </a>

            <a href="agenda_semanal.php" class="da-action-card">
                <span class="da-action-card__number">03</span>
                <span class="da-action-card__icon"><svg><use href="#da-icon-today"></use></svg></span>
                <span class="da-action-card__body">
                    <strong>Ver semana</strong>
                    <span>Consultar actividades programadas</span>
                </span>
                <span class="da-action-card__arrow"><svg><use href="#da-icon-chevron"></use></svg></span>
            </a>

            <a href="rutinas.php" class="da-action-card">
                <span class="da-action-card__number">04</span>
                <span class="da-action-card__icon"><svg><use href="#da-icon-repeat"></use></svg></span>
                <span class="da-action-card__body">
                    <strong>Atender rutinas</strong>
                    <span>Programar actividades recurrentes</span>
                </span>
                <span class="da-action-card__arrow"><svg><use href="#da-icon-chevron"></use></svg></span>
            </a>
        </div>
    </section>

    <section class="da-grid da-grid--main">
        <article class="da-card da-card--priority">
            <header class="da-card__head">
                <div class="da-card__title-wrap">
                    <span class="da-card__icon da-card__icon--danger"><svg><use href="#da-icon-warning"></use></svg></span>
                    <div>
                        <span class="da-card__kicker">Prioridad operativa</span>
                        <h2>Atención requerida</h2>
                        <p>Urgencias, atrasos y solicitudes nuevas.</p>
                    </div>
                </div>
                <a href="solicitudes_historial.php">Ver todas <svg><use href="#da-icon-arrow"></use></svg></a>
            </header>
            <div id="listaPrioridades" class="da-list" aria-live="polite"></div>
        </article>

        <article class="da-card da-card--agenda">
            <header class="da-card__head">
                <div class="da-card__title-wrap">
                    <span class="da-card__icon da-card__icon--info"><svg><use href="#da-icon-today"></use></svg></span>
                    <div>
                        <span class="da-card__kicker">Operación diaria</span>
                        <h2>Actividades de hoy</h2>
                        <p>Programadas, activas o pausadas.</p>
                    </div>
                </div>
                <a href="agenda_semanal.php?fecha=hoy">Abrir agenda <svg><use href="#da-icon-arrow"></use></svg></a>
            </header>
            <div id="listaAgenda" class="da-list" aria-live="polite"></div>
        </article>
    </section>

    <section class="da-grid da-grid--secondary">
        <article class="da-card">
            <header class="da-card__head">
                <div class="da-card__title-wrap">
                    <span class="da-card__icon da-card__icon--warning"><svg><use href="#da-icon-repeat"></use></svg></span>
                    <div>
                        <span class="da-card__kicker">Mantenimiento recurrente</span>
                        <h2>Rutinas pendientes</h2>
                        <p>Actividades fijas que deben programarse.</p>
                    </div>
                </div>
                <a href="rutinas.php">Gestionar <svg><use href="#da-icon-arrow"></use></svg></a>
            </header>
            <div id="listaRutinas" class="da-list" aria-live="polite"></div>
        </article>

        <article class="da-card da-summary-card">
            <header class="da-card__head">
                <div class="da-card__title-wrap">
                    <span class="da-card__icon da-card__icon--success"><svg><use href="#da-icon-chart"></use></svg></span>
                    <div>
                        <span class="da-card__kicker">Cumplimiento</span>
                        <h2>Resultado del mes</h2>
                        <p>Seguimiento general de desempeño.</p>
                    </div>
                </div>
                <a href="incumplimientos.php">Ver detalle <svg><use href="#da-icon-arrow"></use></svg></a>
            </header>
            <div class="da-month">
                <div class="da-month__item da-month__item--today">
                    <span class="da-month__icon"><svg><use href="#da-icon-check"></use></svg></span>
                    <span class="da-month__data"><strong id="kpiTerminadosHoy">0</strong><span>Terminados hoy</span></span>
                </div>
                <div class="da-month__item da-month__item--month">
                    <span class="da-month__icon"><svg><use href="#da-icon-calendar"></use></svg></span>
                    <span class="da-month__data"><strong id="kpiTerminadosMes">0</strong><span>Terminados este mes</span></span>
                </div>
                <div class="da-month__item da-month__item--missed">
                    <span class="da-month__icon"><svg><use href="#da-icon-warning"></use></svg></span>
                    <span class="da-month__data"><strong id="kpiNoRealizados">0</strong><span>No realizados este mes</span></span>
                </div>
            </div>
        </article>
    </section>

    <section class="da-card da-card--table">
        <header class="da-card__head">
            <div class="da-card__title-wrap">
                <span class="da-card__icon da-card__icon--primary"><svg><use href="#da-icon-users"></use></svg></span>
                <div>
                    <span class="da-card__kicker">Capacidad operativa</span>
                    <h2>Carga de técnicos</h2>
                    <p>Resumen de asignaciones, avance y cumplimiento del equipo técnico.</p>
                </div>
            </div>
            <a href="tecnicos.php">Administrar técnicos <svg><use href="#da-icon-arrow"></use></svg></a>
        </header>
        <div class="da-table-wrap">
            <table class="da-table">
                <thead>
                    <tr>
                        <th>Técnico</th>
                        <th>Turno</th>
                        <th>Asignadas hoy</th>
                        <th>En proceso</th>
                        <th>En pausa</th>
                        <th>A tiempo mes</th>
                        <th>Tarde mes</th>
                    </tr>
                </thead>
                <tbody id="tablaTecnicos"></tbody>
            </table>
        </div>
    </section>

    <section class="da-grid da-grid--bottom">
        <article class="da-card da-chart-card">
            <header class="da-card__head">
                <div class="da-card__title-wrap">
                    <span class="da-card__icon da-card__icon--primary"><svg><use href="#da-icon-chart"></use></svg></span>
                    <div>
                        <span class="da-card__kicker">Panorama general</span>
                        <h2>Distribución actual</h2>
                        <p>Solicitudes activas por estado.</p>
                    </div>
                </div>
            </header>
            <div id="graficaEstados" class="da-bars" aria-live="polite"></div>
        </article>

        <article class="da-card da-chart-card">
            <header class="da-card__head">
                <div class="da-card__title-wrap">
                    <span class="da-card__icon da-card__icon--info"><svg><use href="#da-icon-tools"></use></svg></span>
                    <div>
                        <span class="da-card__kicker">Clasificación</span>
                        <h2>Tipos de mantenimiento</h2>
                        <p>Solicitudes registradas por modalidad.</p>
                    </div>
                </div>
            </header>
            <div id="graficaTipos" class="da-bars" aria-live="polite"></div>
        </article>

        <article class="da-card">
            <header class="da-card__head">
                <div class="da-card__title-wrap">
                    <span class="da-card__icon da-card__icon--success"><svg><use href="#da-icon-history"></use></svg></span>
                    <div>
                        <span class="da-card__kicker">Actividad reciente</span>
                        <h2>Cierres recientes</h2>
                        <p>Últimos trabajos registrados como finalizados.</p>
                    </div>
                </div>
                <a href="solicitudes_historial.php?estado=terminado">Historial <svg><use href="#da-icon-arrow"></use></svg></a>
            </header>
            <div id="listaCierres" class="da-list" aria-live="polite"></div>
        </article>
    </section>

    <footer class="da-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Panel del administrador · Los Chapeteados División Petfood</span>
    </footer>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(() => {
    'use strict';

    const endpoint = '../funciones/dashboard_admin_funciones.php';
    const $ = (id) => document.getElementById(id);
    const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const labels = {
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
        RECHAZADO: 'Rechazado',
        CANCELADO: 'Cancelado',
        TERMINADO_PARCIAL: 'Terminado parcialmente',
        PARCIAL: 'Parcial',
        PROVISIONAL: 'Provisional',
        MATUTINO: 'Matutino',
        VESPERTINO: 'Vespertino',
        NOCTURNO: 'Nocturno',
        MIXTO: 'Mixto',
        URGENTE: 'Urgente',
        ALTA: 'Alta',
        MEDIA: 'Media',
        BAJA: 'Baja'
    };

    const label = (value) => labels[value] || String(value || '')
        .replaceAll('_', ' ')
        .toLowerCase()
        .replace(/^./, c => c.toUpperCase());

    const fecha = (value, conHora = false) => {
        if (!value) return 'Sin fecha';

        const raw = String(value);
        const normalizada = raw.length === 10
            ? raw + 'T12:00:00'
            : raw.replace(' ', 'T');
        const d = new Date(normalizada);

        if (Number.isNaN(d.getTime())) return esc(value);

        return new Intl.DateTimeFormat(
            'es-MX',
            conHora
                ? { dateStyle: 'medium', timeStyle: 'short' }
                : { dateStyle: 'medium' }
        ).format(d);
    };

    const icon = (name) => `<svg aria-hidden="true"><use href="#da-icon-${name}"></use></svg>`;

    const empty = (text, iconName = 'check') => `
        <div class="da-empty">
            <span class="da-empty__icon">${icon(iconName)}</span>
            <strong>Todo en orden</strong>
            <span>${esc(text)}</span>
        </div>`;

    function iniciales(nombre) {
        const partes = String(nombre || 'Técnico')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2);

        return partes.map(parte => parte.charAt(0).toUpperCase()).join('') || 'T';
    }

    function claseBadge(valor) {
        const normalized = String(valor || '').toUpperCase();
        const mapa = {
            URGENTE: 'danger',
            ATRASADO: 'danger',
            RECHAZADO: 'danger',
            CANCELADO: 'danger',
            REVISION: 'warning',
            RUTINA: 'warning',
            PENDIENTE: 'warning',
            PAUSADO: 'neutral',
            AGENDADO: 'info',
            APROBADO: 'info',
            EN_PROCESO: 'success',
            TERMINADO: 'success',
            TERMINADO_PARCIAL: 'success',
            PARCIAL: 'warning',
            PROVISIONAL: 'info'
        };

        return mapa[normalized] || 'default';
    }

    function animarNumero(elemento, destino) {
        if (!elemento) return;

        const final = Number(destino || 0);
        const inicio = Number(String(elemento.textContent).replace(/[^0-9.-]/g, '')) || 0;

        if (reduceMotion || inicio === final) {
            elemento.textContent = final.toLocaleString('es-MX');
            return;
        }

        const duracion = 650;
        const comienzo = performance.now();

        const actualizar = (ahora) => {
            const progreso = Math.min(1, (ahora - comienzo) / duracion);
            const suavizado = 1 - Math.pow(1 - progreso, 3);
            const actual = Math.round(inicio + (final - inicio) * suavizado);
            elemento.textContent = actual.toLocaleString('es-MX');

            if (progreso < 1) requestAnimationFrame(actualizar);
        };

        requestAnimationFrame(actualizar);
    }

    function setKpis(k) {
        const map = {
            kpiPendientes: 'pendientes_revision',
            kpiProgramar: 'por_programar',
            kpiUrgentes: 'urgentes_abiertas',
            kpiHoy: 'actividades_hoy',
            kpiProceso: 'en_proceso',
            kpiPausados: 'pausados',
            kpiAtrasados: 'atrasados',
            kpiRutinas: 'rutinas_pendientes',
            kpiTerminadosHoy: 'terminados_hoy',
            kpiTerminadosMes: 'terminados_mes',
            kpiNoRealizados: 'no_realizados_mes'
        };

        Object.entries(map).forEach(([id, key]) => {
            animarNumero($(id), Number(k[key] || 0));
        });
    }

    function renderPrioridades(rows) {
        $('listaPrioridades').innerHTML = rows.length ? rows.map((r, index) => {
            const clase = String(r.clase || 'REVISION').toUpperCase();
            const destino = Number(r.requiere_revision || 0) === 1 || clase === 'REVISION'
                ? 'solicitudes_pendientes.php'
                : 'solicitudes_historial.php';
            const textoClase = clase === 'REVISION' ? 'Revisar' : label(clase);
            const referencia = r.dias_atraso
                ? `${esc(r.dias_atraso)} días de atraso`
                : fecha(r.fecha_referencia);

            return `
                <a class="da-item" style="--item-delay:${index * 45}ms" href="${destino}?id=${Number(r.solicitud_id)}">
                    <span class="da-item__marker da-item__marker--${claseBadge(clase)}"></span>
                    <span class="da-badge da-badge--${claseBadge(clase)}">${esc(textoClase)}</span>
                    <div class="da-item__content">
                        <strong>${esc(r.folio)} <span class="da-item__separator">·</span> ${esc(r.codigo_equipo)}</strong>
                        <span>${esc(r.nombre_equipo)}${r.area ? ' · ' + esc(r.area) : ''}</span>
                    </div>
                    <small><span>${referencia}</span>${icon('chevron')}</small>
                </a>`;
        }).join('') : empty('No hay asuntos urgentes o pendientes.', 'check');
    }

    function renderAgenda(rows) {
        $('listaAgenda').innerHTML = rows.length ? rows.map((r, index) => `
            <a class="da-item" style="--item-delay:${index * 45}ms" href="solicitudes_historial.php?id=${Number(r.solicitud_id)}">
                <span class="da-item__marker da-item__marker--${claseBadge(r.estado)}"></span>
                <span class="da-badge da-badge--${claseBadge(r.estado)}">${esc(label(r.estado))}</span>
                <div class="da-item__content">
                    <strong>${esc(r.folio)} <span class="da-item__separator">·</span> ${esc(r.codigo_equipo)}</strong>
                    <span>${esc(r.nombre_equipo)}</span>
                    <span class="da-item__secondary">${esc(r.tecnicos || 'Sin técnico asignado')}</span>
                </div>
                <small><span>${esc(label(r.prioridad))}</span>${icon('chevron')}</small>
            </a>`).join('') : empty('No hay actividades programadas para hoy.', 'calendar');
    }

    function renderRutinas(rows) {
        $('listaRutinas').innerHTML = rows.length ? rows.map((r, index) => `
            <a class="da-item" style="--item-delay:${index * 45}ms" href="rutinas.php?alerta_id=${Number(r.alerta_id)}">
                <span class="da-item__marker da-item__marker--warning"></span>
                <span class="da-badge da-badge--warning">Rutina</span>
                <div class="da-item__content">
                    <strong>${esc(r.nombre)}</strong>
                    <span>${esc(r.codigo_equipo)} · ${esc(r.nombre_equipo)}</span>
                </div>
                <small><span>${fecha(r.fecha_notificacion)}</span>${icon('chevron')}</small>
            </a>`).join('') : empty('No hay rutinas pendientes de programación.', 'repeat');
    }

    function renderTecnicos(rows) {
        $('tablaTecnicos').innerHTML = rows.length ? rows.map((r, index) => {
            const proceso = Number(r.en_proceso || 0);
            const pausadas = Number(r.pausadas || 0);
            const tarde = Number(r.tarde_mes || 0);

            return `<tr style="--row-delay:${index * 35}ms">
                <td>
                    <div class="da-technician">
                        <span class="da-technician__avatar">${esc(iniciales(r.tecnico))}</span>
                        <span><strong>${esc(r.tecnico)}</strong><small>Personal técnico</small></span>
                    </div>
                </td>
                <td><span class="da-table-pill da-table-pill--shift">${esc(label(r.turno))}</span></td>
                <td><span class="da-table-number">${Number(r.asignadas_hoy || 0)}</span></td>
                <td><span class="da-table-pill ${proceso > 0 ? 'da-table-pill--success' : ''}">${proceso}</span></td>
                <td><span class="da-table-pill ${pausadas > 0 ? 'da-table-pill--warning' : ''}">${pausadas}</span></td>
                <td><span class="da-table-pill da-table-pill--success">${Number(r.a_tiempo_mes || 0)}</span></td>
                <td><span class="da-table-pill ${tarde > 0 ? 'da-table-pill--danger' : ''}">${tarde}</span></td>
            </tr>`;
        }).join('') : `
            <tr>
                <td colspan="7">
                    <div class="da-table-empty">${icon('users')}<span>No hay técnicos activos.</span></div>
                </td>
            </tr>`;
    }

    function renderBars(id, rows, key, nameKey) {
        const total = rows.reduce((sum, row) => sum + Number(row[key] || 0), 0);
        const ordenadas = [...rows].sort((a, b) => Number(b[key] || 0) - Number(a[key] || 0));

        if (!ordenadas.length) {
            $(id).innerHTML = empty('Sin información disponible.', 'chart');
            return;
        }

        const filas = ordenadas.map((r, index) => {
            const cantidad = Number(r[key] || 0);
            const participacionExacta = total > 0 ? cantidad * 100 / total : 0;
            const participacion = Math.round(participacionExacta);
            const ancho = cantidad > 0 ? Math.max(4, participacionExacta) : 0;

            return `
                <div class="da-bar-row" style="--bar-delay:${index * 65}ms">
                    <span class="da-bar-row__rank" aria-hidden="true">${String(index + 1).padStart(2, '0')}</span>
                    <div class="da-bar-row__body">
                        <div class="da-bar-row__top">
                            <span>${esc(label(r[nameKey]))}</span>
                            <strong>${cantidad.toLocaleString('es-MX')} <small>${participacion}%</small></strong>
                        </div>
                        <div class="da-bar-row__track" role="img" aria-label="${esc(label(r[nameKey]))}: ${participacion}% del total">
                            <i style="--bar-width:${ancho}%"></i>
                        </div>
                    </div>
                </div>`;
        }).join('');

        $(id).innerHTML = `
            <div class="da-chart-summary">
                <div>
                    <span>Total registrado</span>
                    <strong>${total.toLocaleString('es-MX')}</strong>
                </div>
                <span class="da-chart-summary__caption">Distribución porcentual</span>
            </div>
            <div class="da-chart-list">${filas}</div>`;
    }

    function renderCierres(rows) {
        $('listaCierres').innerHTML = rows.length ? rows.map((r, index) => `
            <a class="da-item da-item--closure" style="--item-delay:${index * 45}ms" href="solicitudes_historial.php?id=${Number(r.solicitud_id)}">
                <span class="da-item__marker da-item__marker--success"></span>
                <span class="da-badge da-badge--success">${esc(label(r.trabajo_quedo))}</span>
                <div class="da-item__content">
                    <strong>${esc(r.folio)} <span class="da-item__separator">·</span> ${esc(r.codigo_equipo)}</strong>
                    <span>${esc(r.nombre_equipo)} · ${esc(r.cerrado_por)}</span>
                </div>
                <small><span>${fecha(r.fecha_hora_cierre, true)}</span>${icon('chevron')}</small>
            </a>`).join('') : empty('Todavía no hay cierres registrados.', 'history');
    }

    function mostrarSkeletons() {
        const skeletonItem = () => `
            <div class="da-skeleton-item">
                <span></span><div><i></i><i></i></div><b></b>
            </div>`;

        ['listaPrioridades', 'listaAgenda', 'listaRutinas', 'listaCierres'].forEach(id => {
            const contenedor = $(id);
            if (contenedor && !contenedor.children.length) {
                contenedor.innerHTML = skeletonItem() + skeletonItem() + skeletonItem();
            }
        });

        if (!$('tablaTecnicos').children.length) {
            $('tablaTecnicos').innerHTML = Array.from({length: 4}, () => `
                <tr class="da-skeleton-row">
                    <td><span></span></td><td><span></span></td><td><span></span></td>
                    <td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td>
                </tr>`).join('');
        }

        ['graficaEstados', 'graficaTipos'].forEach(id => {
            const contenedor = $(id);
            if (contenedor && !contenedor.children.length) {
                contenedor.innerHTML = Array.from({length: 4}, () => `
                    <div class="da-skeleton-bar"><span></span><i></i></div>`).join('');
            }
        });
    }

    function estadoCarga(texto, tipo = 'loading', hora = '') {
        const estado = $('estadoCarga');
        const textoEstado = estado.querySelector('.da-status__text');
        const horaEstado = $('estadoHora');

        estado.className = `da-status da-status--${tipo}`;
        textoEstado.textContent = texto;
        horaEstado.textContent = hora;
    }

    function cambiarCarga(cargando) {
        const boton = $('btnActualizar');
        boton.disabled = cargando;
        boton.classList.toggle('is-loading', cargando);
        boton.setAttribute('aria-busy', cargando ? 'true' : 'false');
    }

    function mostrarToast(titulo, icono = 'success') {
        if (typeof Swal === 'undefined') return;

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icono,
            title: titulo,
            showConfirmButton: false,
            timer: 2400,
            timerProgressBar: true,
            customClass: {
                popup: 'da-toast'
            }
        });
    }

    async function cargar(manual = false) {
        cambiarCarga(true);
        estadoCarga('Actualizando información operativa...', 'loading');

        if (!manual) mostrarSkeletons();

        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 20000);

        try {
            const response = await fetch(endpoint, {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                cache: 'no-store',
                credentials: 'same-origin',
                signal: controller.signal
            });

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error('El servidor devolvió una respuesta no válida.');
            }

            const json = await response.json();
            if (!response.ok || !json.ok) {
                throw new Error(json.mensaje || 'No fue posible cargar el dashboard.');
            }

            const d = json.datos || {};
            setKpis(d.kpis || {});
            renderPrioridades(d.prioridades || []);
            renderAgenda(d.agenda_hoy || []);
            renderRutinas(d.rutinas || []);
            renderTecnicos(d.tecnicos || []);
            renderBars('graficaEstados', d.estados || [], 'total', 'estado');
            renderBars('graficaTipos', d.tipos || [], 'total', 'tipo');
            renderCierres(d.cierres_recientes || []);

            const actualizado = fecha(d.actualizado_en, true);
            estadoCarga('Dashboard actualizado correctamente', 'ok', actualizado);

            document.querySelectorAll('.da-card, .da-kpi, .da-action-card').forEach(card => {
                card.classList.add('is-ready');
            });

            if (manual) mostrarToast('Información actualizada', 'success');
        } catch (error) {
            console.error(error);

            const mensaje = error && error.name === 'AbortError'
                ? 'La consulta tardó demasiado. Verifica la conexión e inténtalo nuevamente.'
                : (error.message || 'Ocurrió un error al cargar la información.');

            estadoCarga(mensaje, 'error');

            if (manual && typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo actualizar',
                    text: mensaje,
                    confirmButtonText: 'Entendido',
                    customClass: {
                        popup: 'da-alert',
                        confirmButton: 'da-alert__confirm'
                    },
                    buttonsStyling: false,
                    heightAuto: false
                });
            }
        } finally {
            window.clearTimeout(timeout);
            cambiarCarga(false);
        }
    }

    function pintarFechaActual() {
        const ahora = new Date();
        const texto = new Intl.DateTimeFormat('es-MX', {
            weekday: 'long',
            day: 'numeric',
            month: 'long', 
            year: 'numeric'
        }).format(ahora);

        $('fechaActual').textContent = texto.charAt(0).toUpperCase() + texto.slice(1);
    }

    $('btnActualizar').addEventListener('click', () => cargar(true));
    pintarFechaActual();
    cargar(false);
})();
</script>
</body>
</html>