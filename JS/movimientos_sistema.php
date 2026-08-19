<?php

declare(strict_types=1);

/*
 * El navegador consulta esta misma página mediante ?mov_api=1. La página
 * incluye el backend con una ruta absoluta del servidor para evitar errores
 * 404 por carpetas o rutas relativas distintas.
 */
if (isset($_GET['mov_api'])) {
    $endpoint = __DIR__ . '/../funciones/movimientos_sistema_funciones.php';

    if (!is_file($endpoint)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode(
            [
                'success' => false,
                'mensaje' => 'No se encontró funciones/movimientos_sistema_funciones.php. Copia juntos los tres archivos del módulo en sus carpetas correspondientes.',
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

$nombreAdmin = trim(
    (string) (
        $_SESSION['nombre_completo']
        ?? $_SESSION['usuario']
        ?? 'Administrador'
    )
);

$cssMovimientos = __DIR__ . '/../css/style_movimientos_sistema.css';
$versionCss = is_file($cssMovimientos)
    ? (string) filemtime($cssMovimientos)
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
        content="Bitácora administrativa de movimientos del Sistema de Mantenimiento"
    >
    <title>Movimientos del sistema | Sistema de Mantenimiento</title>
    <link
        rel="stylesheet"
        href="../css/style_movimientos_sistema.css?v=<?= htmlspecialchars($versionCss, ENT_QUOTES, 'UTF-8') ?>"
    >
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="mov-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="mov-icon-sparkles" viewBox="0 0 24 24">
        <path d="m12 3-1.2 3.8L7 8l3.8 1.2L12 13l1.2-3.8L17 8l-3.8-1.2L12 3Z"/>
        <path d="m5 15-.7 2.3L2 18l2.3.7L5 21l.7-2.3L8 18l-2.3-.7L5 15Z"/>
        <path d="m19 13-.7 2.3-2.3.7 2.3.7L19 19l.7-2.3 2.3-.7L19 13Z"/>
    </symbol>
    <symbol id="mov-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="mov-icon-shield" viewBox="0 0 24 24">
        <path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/>
        <path d="m8.5 12 2.2 2.2 4.8-5"/>
    </symbol>
    <symbol id="mov-icon-route" viewBox="0 0 24 24">
        <circle cx="6" cy="6" r="3"/>
        <circle cx="18" cy="18" r="3"/>
        <path d="M8.5 7.5c5 1 7 3 7 7M6 9v7a2 2 0 0 0 2 2h7"/>
    </symbol>
    <symbol id="mov-icon-lock" viewBox="0 0 24 24">
        <rect x="4" y="10" width="16" height="11" rx="2"/>
        <path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>
    </symbol>
    <symbol id="mov-icon-filter" viewBox="0 0 24 24">
        <path d="M4 5h16M7 12h10M10 19h4"/>
    </symbol>
    <symbol id="mov-icon-history" viewBox="0 0 24 24">
        <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>
        <path d="M3 3v5h5M12 7v5l3 2"/>
    </symbol>
    <symbol id="mov-icon-calendar" viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path d="M16 3v4M8 3v4M3 10h18"/>
    </symbol>
    <symbol id="mov-icon-users" viewBox="0 0 24 24">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
    </symbol>
    <symbol id="mov-icon-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </symbol>
    <symbol id="mov-icon-database" viewBox="0 0 24 24">
        <ellipse cx="12" cy="5" rx="8" ry="3"/>
        <path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>
    </symbol>
    <symbol id="mov-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="mov-icon-eye" viewBox="0 0 24 24">
        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/>
        <circle cx="12" cy="12" r="2.5"/>
    </symbol>
    <symbol id="mov-icon-check" viewBox="0 0 24 24">
        <path d="m5 12 4 4L19 6"/>
    </symbol>
    <symbol id="mov-icon-list" viewBox="0 0 24 24">
        <path d="M8 6h12M8 12h12M8 18h12"/>
        <path d="M4 6h.01M4 12h.01M4 18h.01"/>
    </symbol>
</svg>

<main class="mov-page">
    <div class="mov-ambient mov-ambient--one" aria-hidden="true"></div>
    <div class="mov-ambient mov-ambient--two" aria-hidden="true"></div>

    <section class="mov-heading mov-hero" aria-labelledby="tituloMovimientos">
        <div class="mov-hero__pattern" aria-hidden="true"></div>

        <div class="mov-hero__content">
            <div class="mov-hero__copy">
                <p class="mov-eyebrow">
                    <span class="mov-eyebrow__icon" aria-hidden="true">
                        <svg><use href="#mov-icon-sparkles"></use></svg>
                    </span>
                    Auditoría administrativa
                </p>

                <h1 id="tituloMovimientos">Movimientos del sistema</h1>

                <p class="mov-hero__description">
                    Consulta quién realizó cada acción, en qué módulo ocurrió y qué
                    registro fue afectado, sin alterar el historial del sistema.
                </p>

                <div class="mov-hero__meta">
                    <span>
                        <span class="mov-live-dot" aria-hidden="true"></span>
                        Bitácora protegida y de solo lectura
                    </span>
                    <span>
                        Administrador:
                        <strong><?= htmlspecialchars($nombreAdmin, ENT_QUOTES, 'UTF-8') ?></strong>
                    </span>
                </div>
            </div>

            <div class="mov-hero__actions">
                <div class="mov-hero__mini-card" aria-hidden="true">
                    <span class="mov-hero__mini-icon">
                        <svg><use href="#mov-icon-history"></use></svg>
                    </span>
                    <div>
                        <small>Centro de trazabilidad</small>
                        <strong>Historial inalterable</strong>
                    </div>
                </div>

                <button
                    type="button"
                    class="mov-btn mov-btn--hero mov-btn--secondary"
                    id="btnActualizar"
                >
                    <svg aria-hidden="true"><use href="#mov-icon-refresh"></use></svg>
                    <span>Actualizar</span>
                </button>
            </div>
        </div>
    </section>

    <section class="mov-guides" aria-label="Características de la bitácora">
        <article>
            <span class="mov-guide-icon" aria-hidden="true">
                <svg><use href="#mov-icon-lock"></use></svg>
            </span>
            <div>
                <strong>Historial protegido</strong>
                <p>Los movimientos no se editan ni se eliminan; la consulta conserva la evidencia original.</p>
            </div>
        </article>

        <article>
            <span class="mov-guide-icon" aria-hidden="true">
                <svg><use href="#mov-icon-route"></use></svg>
            </span>
            <div>
                <strong>Trazabilidad completa</strong>
                <p>Identifica al usuario, la fecha, el módulo, la acción y el registro relacionado.</p>
            </div>
        </article>

        <article>
            <span class="mov-guide-icon" aria-hidden="true">
                <svg><use href="#mov-icon-filter"></use></svg>
            </span>
            <div>
                <strong>Consulta precisa</strong>
                <p>Combina búsqueda, usuario, módulo y periodo sin cargar todos los registros de una sola vez.</p>
            </div>
        </article>
    </section>

    <div class="mov-status" id="estadoPagina" role="status" aria-live="polite">
        <span class="mov-status__indicator" aria-hidden="true"></span>
        <span>Cargando movimientos...</span>
    </div>

    <section class="mov-summary" aria-label="Resumen de movimientos">
        <article class="mov-summary__card mov-summary__card--total">
            <span class="mov-summary__icon" aria-hidden="true">
                <svg><use href="#mov-icon-list"></use></svg>
            </span>
            <span>Movimientos encontrados</span>
            <strong id="kpiTotal">0</strong>
            <small>Según los filtros actuales</small>
        </article>

        <article class="mov-summary__card mov-summary__card--today">
            <span class="mov-summary__icon" aria-hidden="true">
                <svg><use href="#mov-icon-calendar"></use></svg>
            </span>
            <span>Registrados hoy</span>
            <strong id="kpiHoy">0</strong>
            <small>Dentro de esta consulta</small>
        </article>

        <article class="mov-summary__card mov-summary__card--users">
            <span class="mov-summary__icon" aria-hidden="true">
                <svg><use href="#mov-icon-users"></use></svg>
            </span>
            <span>Usuarios involucrados</span>
            <strong id="kpiUsuarios">0</strong>
            <small>Personas distintas</small>
        </article>

        <article class="mov-summary__card mov-summary__card--last">
            <span class="mov-summary__icon" aria-hidden="true">
                <svg><use href="#mov-icon-clock"></use></svg>
            </span>
            <span>Último movimiento</span>
            <strong id="kpiUltimo">—</strong>
            <small>Actividad más reciente</small>
        </article>
    </section>

    <section class="mov-panel" aria-labelledby="tituloActividad">
        <header class="mov-panel__head">
            <div class="mov-section-heading">
                <span class="mov-section-heading__icon" aria-hidden="true">
                    <svg><use href="#mov-icon-database"></use></svg>
                </span>
                <div>
                    <p class="mov-eyebrow">Historial administrativo</p>
                    <h2 id="tituloActividad">Actividad registrada</h2>
                    <p id="textoResultados">Preparando resultados...</p>
                </div>
            </div>

            <div class="mov-panel__meta">
                <span class="mov-server-badge">
                    <span class="mov-live-dot" aria-hidden="true"></span>
                    Paginación del servidor
                </span>
                <span class="mov-updated" id="ultimaActualizacion">Sin actualizar</span>
            </div>
        </header>

        <form id="formFiltros" class="mov-filters" autocomplete="off">
            <label class="mov-field mov-field--search" for="filtroBusqueda">
                <span>Buscar</span>
                <div class="mov-search">
                    <svg aria-hidden="true"><use href="#mov-icon-search"></use></svg>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        maxlength="120"
                        placeholder="Persona, acción, módulo o registro"
                    >
                </div>
            </label>

            <label class="mov-field" for="filtroTipoUsuario">
                <span>Quién</span>
                <select id="filtroTipoUsuario">
                    <option value="TODOS">Todos los usuarios</option>
                    <option value="ADMIN">Administradores</option>
                    <option value="SOLICITANTE">Solicitantes</option>
                    <option value="TECNICO">Técnicos</option>
                </select>
            </label>

            <label class="mov-field" for="filtroModulo">
                <span>Desde dónde</span>
                <select id="filtroModulo">
                    <option value="">Todos los módulos</option>
                </select>
            </label>

            <label class="mov-field" for="filtroDesde">
                <span>Desde</span>
                <input type="date" id="filtroDesde">
            </label>

            <label class="mov-field" for="filtroHasta">
                <span>Hasta</span>
                <input type="date" id="filtroHasta">
            </label>

            <label class="mov-field mov-field--small" for="filtroPorPagina">
                <span>Mostrar</span>
                <select id="filtroPorPagina">
                    <option value="10">10 registros</option>
                    <option value="20" selected>20 registros</option>
                    <option value="40">40 registros</option>
                    <option value="80">80 registros</option>
                </select>
            </label>

            <div class="mov-filter-actions">
                <button type="submit" class="mov-btn mov-btn--primary">
                    <svg aria-hidden="true"><use href="#mov-icon-filter"></use></svg>
                    <span>Aplicar filtros</span>
                </button>
                <button type="button" class="mov-btn mov-btn--soft" id="btnLimpiar">
                    Limpiar
                </button>
            </div>
        </form>

        <div class="mov-loading" id="estadoCarga">
            <span class="mov-spinner" aria-hidden="true"></span>
            <strong>Cargando movimientos...</strong>
            <small>Consultando la bitácora administrativa.</small>
        </div>

        <div class="mov-empty" id="estadoVacio" hidden>
            <span class="mov-empty__icon" aria-hidden="true">
                <svg><use href="#mov-icon-check"></use></svg>
            </span>
            <h3>No se encontraron movimientos</h3>
            <p>Prueba con otro nombre, módulo o periodo.</p>
        </div>

        <div class="mov-table-wrap" id="contenedorTabla" hidden>
            <table class="mov-table">
                <thead>
                    <tr>
                        <th>Cuándo</th>
                        <th>Quién</th>
                        <th>Desde dónde</th>
                        <th>Qué hizo</th>
                        <th>Qué afectó</th>
                        <th aria-label="Detalle"></th>
                    </tr>
                </thead>
                <tbody id="tablaMovimientos"></tbody>
            </table>
        </div>

        <footer class="mov-pagination" id="paginacion" hidden>
            <span id="textoPaginacion">Sin resultados</span>
            <div class="mov-pagination__buttons" id="botonesPaginacion"></div>
        </footer>
    </section>

    <footer class="mov-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Bitácora administrativa · Los Chapeteados División Petfood</span>
    </footer>

    <div class="mov-tools-background" aria-hidden="true"></div>
</main>

<section class="mov-modal" id="modalDetalle" hidden aria-hidden="true">
    <div class="mov-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tituloDetalle">
        <header class="mov-modal__header">
            <div class="mov-modal__title">
                <span class="mov-modal__title-icon" aria-hidden="true">
                    <svg><use href="#mov-icon-eye"></use></svg>
                </span>
                <div>
                    <p class="mov-eyebrow">Detalle del movimiento</p>
                    <h2 id="tituloDetalle">Movimiento</h2>
                    <p id="subtituloDetalle">Información registrada en la bitácora.</p>
                </div>
            </div>

            <button
                type="button"
                class="mov-modal__close"
                id="btnCerrarDetalle"
                aria-label="Cerrar"
            >×</button>
        </header>

        <div class="mov-modal__body">
            <section class="mov-detail-main">
                <span class="mov-detail-main__icon" aria-hidden="true">
                    <svg><use href="#mov-icon-users"></use></svg>
                </span>
                <div>
                    <span class="mov-detail-role" id="detalleRol">Usuario</span>
                    <h3 id="detalleUsuario">—</h3>
                    <p id="detalleCuenta">—</p>
                </div>
            </section>

            <dl class="mov-detail-grid">
                <div>
                    <dt>Cuándo</dt>
                    <dd id="detalleFecha">—</dd>
                </div>
                <div>
                    <dt>Módulo</dt>
                    <dd id="detalleModulo">—</dd>
                </div>
                <div>
                    <dt>Acción</dt>
                    <dd id="detalleAccion">—</dd>
                </div>
                <div>
                    <dt>Registro afectado</dt>
                    <dd id="detalleAfectado">—</dd>
                </div>
                <div>
                    <dt>Dirección IP</dt>
                    <dd id="detalleIp">No registrada</dd>
                </div>
                <div>
                    <dt>Origen</dt>
                    <dd id="detalleNavegador">No registrado</dd>
                </div>
            </dl>

            <section class="mov-description">
                <header>
                    <span aria-hidden="true">
                        <svg><use href="#mov-icon-history"></use></svg>
                    </span>
                    <h3>Descripción</h3>
                </header>
                <p id="detalleDescripcion">Sin descripción.</p>
            </section>

            <details class="mov-technical">
                <summary>Datos internos</summary>
                <dl>
                    <div>
                        <dt>ID del movimiento</dt>
                        <dd id="detalleMovimientoId">—</dd>
                    </div>
                    <div>
                        <dt>ID del usuario</dt>
                        <dd id="detalleUsuarioId">—</dd>
                    </div>
                    <div>
                        <dt>Tabla</dt>
                        <dd id="detalleTabla">—</dd>
                    </div>
                    <div>
                        <dt>ID del registro</dt>
                        <dd id="detalleRegistroId">—</dd>
                    </div>
                </dl>
            </details>
        </div>

        <footer class="mov-modal__footer">
            <button type="button" class="mov-btn mov-btn--primary" id="btnCerrarDetallePie">
                Cerrar detalle
            </button>
        </footer>
    </div>
</section>

<div class="mov-toast" id="toast" hidden role="status" aria-live="polite"></div>

<script>
(function () {
    'use strict';

    var ENDPOINT = 'movimientos_sistema.php';
    var estado = {
        pagina: 1,
        cargando: false,
        catalogosCargados: false
    };

    var ui = {};

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        capturarElementos();
        registrarEventos();
        cargarMovimientos(true);
    }

    function capturarElementos() {
        [
            'btnActualizar', 'formFiltros', 'filtroBusqueda', 'filtroTipoUsuario',
            'filtroModulo', 'filtroDesde', 'filtroHasta', 'filtroPorPagina',
            'btnLimpiar', 'estadoPagina', 'kpiTotal', 'kpiHoy', 'kpiUsuarios',
            'kpiUltimo', 'textoResultados', 'ultimaActualizacion', 'estadoCarga',
            'estadoVacio', 'contenedorTabla', 'tablaMovimientos', 'paginacion',
            'textoPaginacion', 'botonesPaginacion', 'modalDetalle',
            'btnCerrarDetalle', 'btnCerrarDetallePie', 'tituloDetalle',
            'subtituloDetalle', 'detalleRol', 'detalleUsuario', 'detalleCuenta',
            'detalleFecha', 'detalleModulo', 'detalleAccion', 'detalleAfectado',
            'detalleIp', 'detalleNavegador', 'detalleDescripcion',
            'detalleMovimientoId', 'detalleUsuarioId', 'detalleTabla',
            'detalleRegistroId', 'toast'
        ].forEach(function (id) {
            ui[id] = document.getElementById(id);
        });
    }

    function registrarEventos() {
        ui.formFiltros.addEventListener('submit', function (evento) {
            evento.preventDefault();
            estado.pagina = 1;
            cargarMovimientos(false);
        });

        ui.btnActualizar.addEventListener('click', function () {
            cargarMovimientos(false, true);
        });

        ui.btnLimpiar.addEventListener('click', function () {
            ui.formFiltros.reset();
            ui.filtroTipoUsuario.value = 'TODOS';
            ui.filtroModulo.value = '';
            ui.filtroPorPagina.value = '20';
            estado.pagina = 1;
            cargarMovimientos(false);
        });

        ui.filtroPorPagina.addEventListener('change', function () {
            estado.pagina = 1;
            cargarMovimientos(false);
        });

        ui.tablaMovimientos.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-detalle-id]');
            if (!boton) {
                return;
            }
            cargarDetalle(Number(boton.getAttribute('data-detalle-id')));
        });

        ui.botonesPaginacion.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-pagina]');
            if (!boton || boton.disabled) {
                return;
            }

            estado.pagina = Number(boton.getAttribute('data-pagina')) || 1;
            cargarMovimientos(false);
            window.scrollTo({ top: Math.max(0, ui.contenedorTabla.offsetTop - 120), behavior: 'smooth' });
        });

        ui.btnCerrarDetalle.addEventListener('click', cerrarDetalle);
        ui.btnCerrarDetallePie.addEventListener('click', cerrarDetalle);

        ui.modalDetalle.addEventListener('click', function (evento) {
            if (evento.target === ui.modalDetalle) {
                cerrarDetalle();
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && !ui.modalDetalle.hidden) {
                cerrarDetalle();
            }
        });
    }

    async function cargarMovimientos(inicial, notificar) {
        if (estado.cargando) {
            return;
        }

        if (!validarFechas()) {
            return;
        }

        estado.cargando = true;
        mostrarCarga(true);
        cambiarBotonActualizar(true);

        try {
            var parametros = construirParametros(inicial ? 'INICIAL' : 'LISTAR');
            var datos = await solicitar(ENDPOINT + '?' + parametros.toString());

            if (!estado.catalogosCargados || inicial) {
                llenarCatalogos(datos.catalogos || {});
                estado.catalogosCargados = true;
            }

            renderizarResumen(datos.resumen || {});
            renderizarMovimientos(datos.movimientos || []);
            renderizarPaginacion(datos.paginacion || {});

            ui.ultimaActualizacion.textContent = datos.fecha_servidor
                ? 'Actualizado: ' + fechaHora(datos.fecha_servidor)
                : 'Actualizado';

            ocultarEstado();
            if (notificar) {
                toast('Movimientos actualizados.', 'success');
            }
        } catch (error) {
            mostrarError(error.message || 'No fue posible cargar los movimientos.');
        } finally {
            estado.cargando = false;
            mostrarCarga(false);
            cambiarBotonActualizar(false);
        }
    }

    function construirParametros(accion) {
        var parametros = new URLSearchParams();
        parametros.set('mov_api', '1');
        parametros.set('accion', accion);
        parametros.set('pagina', String(estado.pagina));
        parametros.set('por_pagina', ui.filtroPorPagina.value || '20');
        parametros.set('busqueda', ui.filtroBusqueda.value.trim());
        parametros.set('tipo_usuario', ui.filtroTipoUsuario.value || 'TODOS');
        parametros.set('modulo', ui.filtroModulo.value || '');
        parametros.set('fecha_desde', ui.filtroDesde.value || '');
        parametros.set('fecha_hasta', ui.filtroHasta.value || '');
        parametros.set('_', String(Date.now()));
        return parametros;
    }

    function validarFechas() {
        if (
            ui.filtroDesde.value &&
            ui.filtroHasta.value &&
            ui.filtroDesde.value > ui.filtroHasta.value
        ) {
            mostrarError('La fecha inicial no puede ser posterior a la fecha final.');
            ui.filtroDesde.focus();
            return false;
        }
        return true;
    }

    function llenarCatalogos(catalogos) {
        var actual = ui.filtroModulo.value;
        ui.filtroModulo.innerHTML = '<option value="">Todos los módulos</option>';

        (catalogos.modulos || []).forEach(function (modulo) {
            var opcion = document.createElement('option');
            opcion.value = modulo;
            opcion.textContent = modulo;
            ui.filtroModulo.appendChild(opcion);
        });

        if (actual && Array.from(ui.filtroModulo.options).some(function (opcion) {
            return opcion.value === actual;
        })) {
            ui.filtroModulo.value = actual;
        }
    }

    function renderizarResumen(resumen) {
        ui.kpiTotal.textContent = numero(resumen.total);
        ui.kpiHoy.textContent = numero(resumen.hoy);
        ui.kpiUsuarios.textContent = numero(resumen.usuarios);
        ui.kpiUltimo.textContent = resumen.ultimo_movimiento_texto || 'Sin movimientos';
    }

    function renderizarMovimientos(movimientos) {
        ui.tablaMovimientos.innerHTML = '';

        if (!Array.isArray(movimientos) || movimientos.length === 0) {
            ui.contenedorTabla.hidden = true;
            ui.estadoVacio.hidden = false;
            return;
        }

        ui.estadoVacio.hidden = true;
        ui.contenedorTabla.hidden = false;

        movimientos.forEach(function (movimiento) {
            var fila = document.createElement('tr');
            fila.innerHTML =
                '<td data-label="Cuándo">' +
                    '<div class="mov-when"><strong>' + escapeHtml(movimiento.fecha_corta || '—') + '</strong>' +
                    '<span>' + escapeHtml(movimiento.hora_texto || '') + '</span></div>' +
                '</td>' +
                '<td data-label="Quién">' +
                    '<div class="mov-user">' +
                        '<span class="mov-user__avatar mov-user__avatar--' + escapeAttr(String(movimiento.tipo_usuario || '').toLowerCase()) + '">' +
                            escapeHtml(inicial(movimiento.nombre_usuario)) +
                        '</span>' +
                        '<div><strong>' + escapeHtml(movimiento.nombre_usuario || 'Usuario') + '</strong>' +
                        '<span>' + escapeHtml(movimiento.tipo_usuario_texto || '') +
                            (movimiento.cuenta_usuario ? ' · @' + escapeHtml(movimiento.cuenta_usuario) : '') +
                        '</span></div>' +
                    '</div>' +
                '</td>' +
                '<td data-label="Desde dónde"><span class="mov-module">' + escapeHtml(movimiento.modulo || 'Sin módulo') + '</span></td>' +
                '<td data-label="Qué hizo">' +
                    '<div class="mov-action"><strong>' + escapeHtml(movimiento.accion_texto || movimiento.accion || 'Acción') + '</strong>' +
                    '<p>' + escapeHtml(movimiento.descripcion_corta || 'Sin descripción.') + '</p></div>' +
                '</td>' +
                '<td data-label="Qué afectó"><span class="mov-affected">' + escapeHtml(movimiento.afectado_texto || 'Sin registro específico') + '</span></td>' +
                '<td class="mov-table__action"><button type="button" class="mov-detail-btn" data-detalle-id="' + Number(movimiento.id) + '">Ver</button></td>';
            ui.tablaMovimientos.appendChild(fila);
        });
    }

    function renderizarPaginacion(paginacion) {
        var total = Number(paginacion.total || 0);
        var pagina = Number(paginacion.pagina || 1);
        var totalPaginas = Number(paginacion.total_paginas || 1);
        var desde = Number(paginacion.desde || 0);
        var hasta = Number(paginacion.hasta || 0);

        ui.textoResultados.textContent = total === 1
            ? '1 movimiento encontrado.'
            : numero(total) + ' movimientos encontrados.';

        ui.textoPaginacion.textContent = total > 0
            ? 'Mostrando ' + numero(desde) + '–' + numero(hasta) + ' de ' + numero(total)
            : 'Sin resultados';

        ui.botonesPaginacion.innerHTML = '';
        ui.paginacion.hidden = total === 0;

        if (total === 0) {
            return;
        }

        agregarBotonPagina('‹', pagina - 1, pagina <= 1, false);

        paginasVisibles(pagina, totalPaginas).forEach(function (valor) {
            if (valor === '...') {
                var separador = document.createElement('span');
                separador.className = 'mov-pagination__ellipsis';
                separador.textContent = '…';
                ui.botonesPaginacion.appendChild(separador);
                return;
            }
            agregarBotonPagina(String(valor), Number(valor), false, Number(valor) === pagina);
        });

        agregarBotonPagina('›', pagina + 1, pagina >= totalPaginas, false);
    }

    function paginasVisibles(actual, total) {
        if (total <= 7) {
            return Array.from({ length: total }, function (_, indice) { return indice + 1; });
        }

        if (actual <= 4) {
            return [1, 2, 3, 4, 5, '...', total];
        }

        if (actual >= total - 3) {
            return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
        }

        return [1, '...', actual - 1, actual, actual + 1, '...', total];
    }

    function agregarBotonPagina(texto, pagina, deshabilitado, activo) {
        var boton = document.createElement('button');
        boton.type = 'button';
        boton.textContent = texto;
        boton.disabled = deshabilitado;
        boton.className = activo ? 'is-active' : '';
        boton.setAttribute('data-pagina', String(pagina));
        ui.botonesPaginacion.appendChild(boton);
    }

    async function cargarDetalle(id) {
        if (!Number.isInteger(id) || id < 1) {
            toast('El movimiento seleccionado no es válido.', 'error');
            return;
        }

        try {
            var parametros = new URLSearchParams({
                mov_api: '1',
                accion: 'DETALLE',
                id: String(id),
                _: String(Date.now())
            });
            var datos = await solicitar(ENDPOINT + '?' + parametros.toString());
            mostrarDetalle(datos.movimiento || {});
        } catch (error) {
            toast(error.message || 'No fue posible cargar el detalle.', 'error');
        }
    }

    function mostrarDetalle(movimiento) {
        ui.tituloDetalle.textContent = movimiento.accion_texto || movimiento.accion || 'Movimiento';
        ui.subtituloDetalle.textContent = movimiento.modulo || 'Sistema';
        ui.detalleRol.textContent = movimiento.tipo_usuario_texto || 'Usuario';
        ui.detalleRol.className = 'mov-detail-role mov-detail-role--' + String(movimiento.tipo_usuario || '').toLowerCase();
        ui.detalleUsuario.textContent = movimiento.nombre_usuario || 'Usuario';
        ui.detalleCuenta.textContent = movimiento.cuenta_usuario
            ? '@' + movimiento.cuenta_usuario
            : 'Cuenta no disponible';
        ui.detalleFecha.textContent = movimiento.fecha_texto || '—';
        ui.detalleModulo.textContent = movimiento.modulo || 'Sin módulo';
        ui.detalleAccion.textContent = movimiento.accion_texto || movimiento.accion || '—';
        ui.detalleAfectado.textContent = movimiento.afectado_texto || 'Sin registro específico';
        ui.detalleIp.textContent = movimiento.ip_address || 'No registrada';
        ui.detalleNavegador.textContent = movimiento.navegador_texto || 'No registrado';
        ui.detalleDescripcion.textContent = movimiento.descripcion || 'Sin descripción.';
        ui.detalleMovimientoId.textContent = String(movimiento.id || '—');
        ui.detalleUsuarioId.textContent = String(movimiento.usuario_id || '—');
        ui.detalleTabla.textContent = movimiento.tabla_afectada || 'No registrada';
        ui.detalleRegistroId.textContent = movimiento.registro_id === null || movimiento.registro_id === undefined
            ? 'No registrado'
            : String(movimiento.registro_id);

        ui.modalDetalle.hidden = false;
        document.body.classList.add('mov-modal-open');
        window.setTimeout(function () { ui.btnCerrarDetalle.focus(); }, 20);
    }

    function cerrarDetalle() {
        ui.modalDetalle.hidden = true;
        document.body.classList.remove('mov-modal-open');
    }

    async function solicitar(url) {
        var respuesta;
        try {
            respuesta = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        } catch (error) {
            throw new Error('No fue posible comunicarse con el servidor.');
        }

        var texto = await respuesta.text();
        var datos;

        try {
            datos = JSON.parse(texto);
        } catch (error) {
            throw new Error('El servidor devolvió una respuesta no válida. Revisa el registro de PHP.');
        }

        if (datos.sesion_expirada && datos.redirect) {
            window.location.href = datos.redirect;
            throw new Error(datos.mensaje || 'La sesión expiró.');
        }

        if (!respuesta.ok || !datos.success) {
            var mensaje = datos.mensaje || 'No fue posible completar la consulta.';
            if (datos.referencia) {
                mensaje += ' Referencia: ' + datos.referencia;
            }
            throw new Error(mensaje);
        }

        return datos;
    }

    function mostrarCarga(mostrar) {
        ui.estadoCarga.hidden = !mostrar;
        if (mostrar) {
            ui.estadoVacio.hidden = true;
            ui.contenedorTabla.hidden = true;
            ui.paginacion.hidden = true;
        }
    }

    function mostrarError(mensaje) {
        ui.estadoPagina.hidden = false;
        ui.estadoPagina.className = 'mov-status mov-status--error';
        ui.estadoPagina.textContent = mensaje;
    }

    function ocultarEstado() {
        ui.estadoPagina.hidden = true;
        ui.estadoPagina.className = 'mov-status';
        ui.estadoPagina.textContent = '';
    }

    function cambiarBotonActualizar(cargando) {
        ui.btnActualizar.disabled = cargando;
        ui.btnActualizar.innerHTML = cargando
            ? '<span class="mov-mini-spinner" aria-hidden="true"></span> Actualizando...'
            : '<span aria-hidden="true">↻</span> Actualizar';
    }

    function toast(mensaje, tipo) {
        ui.toast.textContent = mensaje;
        ui.toast.className = 'mov-toast mov-toast--' + (tipo || 'info');
        ui.toast.hidden = false;
        window.clearTimeout(toast.temporizador);
        toast.temporizador = window.setTimeout(function () {
            ui.toast.hidden = true;
        }, 3500);
    }

    function numero(valor) {
        return new Intl.NumberFormat('es-MX').format(Number(valor || 0));
    }

    function inicial(nombre) {
        var limpio = String(nombre || 'U').trim();
        return limpio ? limpio.charAt(0).toUpperCase() : 'U';
    }

    function fechaHora(valor) {
        if (!valor) {
            return '—';
        }
        var fecha = new Date(String(valor).replace(' ', 'T'));
        if (Number.isNaN(fecha.getTime())) {
            return valor;
        }
        return new Intl.DateTimeFormat('es-MX', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hour12: false
        }).format(fecha);
    } 

    function escapeHtml(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeAttr(valor) {
        return String(valor || '').replace(/[^a-z0-9_-]/gi, '');
    }
})();
</script>
</body>
</html>