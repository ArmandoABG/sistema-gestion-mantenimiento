<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/seguridad.php';
require_once __DIR__ . '/../inc/conexion.php';

sm_requerir_sesion(['ADMIN'], false);

$csrfToken = sm_token_csrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a2947">
    <title>Calendario laboral | Sistema de mantenimiento</title>
    <link rel="stylesheet" href="../css/style_calendario_laboral.css?v=20260730.4">
</head>
<body>

<?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

<main class="cal-layout">
    <?php require_once __DIR__ . '/../inc/topbar.php'; ?>

    <section class="cal-page">
        <header class="cal-header">
            <div class="cal-header__copy">
                <span class="cal-eyebrow">Planeación y disponibilidad</span>
                <h1>Calendario laboral</h1>
                <p>
                    Administra los días disponibles para programación, consulta la
                    carga diaria y reprograma mantenimientos sin salir del calendario.
                </p>
            </div>

            <div class="cal-header__actions">
                <button type="button" class="cal-btn cal-btn--soft" id="btnHoy">
                    <span aria-hidden="true">⌖</span> Ir a hoy
                </button>
                <button type="button" class="cal-btn cal-btn--primary" id="btnPrepararMes">
                    <span aria-hidden="true">✓</span> Verificar mes
                </button>
            </div>
        </header>

        <section class="cal-guidance" aria-label="Reglas del calendario">
            <article>
                <span class="cal-guidance__icon">1</span>
                <div>
                    <strong>Días programables</strong>
                    <p>Los mantenimientos normales sólo se agendan en días hábiles o hábiles extra.</p>
                </div>
            </article>
            <article>
                <span class="cal-guidance__icon">2</span>
                <div>
                    <strong>Reprogramación segura</strong>
                    <p>Un trabajo que ya inició conserva su fecha y no puede moverse desde esta pantalla.</p>
                </div>
            </article>
            <article>
                <span class="cal-guidance__icon">3</span>
                <div>
                    <strong>Urgencias exentas</strong>
                    <p>Los correctivos urgentes se atienden directamente y no dependen del calendario.</p>
                </div>
            </article>
        </section>

        <div
            class="cal-message"
            id="mensajePagina"
            role="status"
            aria-live="polite"
            hidden
        ></div>

        <section class="cal-kpis" aria-label="Resumen mensual">
            <article class="cal-kpi cal-kpi--success">
                <span>Días hábiles</span>
                <strong id="kpiHabiles">0</strong>
                <small>Disponibles de forma regular</small>
            </article>
            <article class="cal-kpi cal-kpi--danger">
                <span>Días inhábiles</span>
                <strong id="kpiInhabiles">0</strong>
                <small>Bloqueados para programación normal</small>
            </article>
            <article class="cal-kpi cal-kpi--extra">
                <span>Hábiles extra</span>
                <strong id="kpiExtras">0</strong>
                <small>Aperturas extraordinarias</small>
            </article>
            <article class="cal-kpi cal-kpi--info">
                <span>Mantenimientos del mes</span>
                <strong id="kpiProgramados">0</strong>
                <small id="textoDiasOcupados">0 días con actividad</small>
            </article>
        </section>

        <section class="cal-panel">
            <header class="cal-panel__header">
                <div class="cal-month-nav" aria-label="Navegación mensual">
                    <button
                        type="button"
                        class="cal-icon-btn"
                        id="btnMesAnterior"
                        aria-label="Mes anterior"
                        title="Mes anterior"
                    >‹</button>

                    <label class="cal-month-picker">
                        <span>Mes mostrado</span>
                        <input type="month" id="selectorMes" min="2020-01" max="2100-12">
                    </label>

                    <button
                        type="button"
                        class="cal-icon-btn"
                        id="btnMesSiguiente"
                        aria-label="Mes siguiente"
                        title="Mes siguiente"
                    >›</button>
                </div>

                <div class="cal-panel__title">
                    <span>Vista mensual</span>
                    <h2 id="tituloMes">Calendario</h2>
                </div>

                <div class="cal-legend" aria-label="Leyenda del calendario">
                    <span><i class="is-habil"></i> Hábil</span>
                    <span><i class="is-inhabil"></i> Inhábil</span>
                    <span><i class="is-extra"></i> Hábil extra</span>
                    <span><i class="is-programado"></i> Con trabajos</span>
                </div>
            </header>

            <div class="cal-loading" id="cargandoPagina">
                <span></span>
                <p>Cargando calendario laboral...</p>
            </div>

            <div class="cal-calendar" id="contenedorCalendario" hidden>
                <div class="cal-weekdays" aria-hidden="true">
                    <span>Lun</span>
                    <span>Mar</span>
                    <span>Mié</span>
                    <span>Jue</span>
                    <span>Vie</span>
                    <span>Sáb</span>
                    <span>Dom</span>
                </div>
                <div class="cal-grid" id="cuadriculaCalendario"></div>
            </div>

            <footer class="cal-panel__footer">
                <p>
                    Selecciona un día para configurar su disponibilidad, consultar
                    trabajos o moverlos a otra fecha hábil.
                </p>
                <small id="ultimaActualizacion">Sin actualizar</small>
            </footer>
        </section>

        <div class="cal-tools-background" aria-hidden="true"></div>
    </section>
</main>

<!-- Detalle y configuración del día -->
<section class="cal-modal" id="modalDia" hidden>
    <div
        class="cal-modal__dialog cal-modal__dialog--day"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloModalDia"
    >
        <header class="cal-modal__header">
            <div>
                <span>Detalle del calendario</span>
                <h2 id="tituloModalDia">Configurar día</h2>
                <p id="subtituloModalDia"></p>
            </div>
            <button
                type="button"
                class="cal-modal__close"
                data-cerrar-modal="modalDia"
                aria-label="Cerrar"
            >×</button>
        </header>

        <form id="formDia" novalidate>
            <input type="hidden" name="accion" value="guardar_dia">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
            >
            <input type="hidden" name="fecha" id="diaFecha">

            <div class="cal-modal__body cal-day-workspace">
                <section class="cal-day-settings">
                    <div class="cal-section-title">
                        <div>
                            <span class="cal-section-kicker">Disponibilidad</span>
                            <h3>Clasificación del día</h3>
                            <p>Define si esta fecha acepta nuevas programaciones.</p>
                        </div>
                        <span class="cal-current-status" id="estadoActualDia"></span>
                    </div>

                    <div class="cal-type-grid" id="opcionesTipoDia">
                        <label class="cal-type-option cal-type-option--habil">
                            <input type="radio" name="tipo_dia" value="HABIL" required>
                            <span class="cal-type-option__icon">✓</span>
                            <span>
                                <strong>Hábil</strong>
                                <small>Disponible normalmente.</small>
                            </span>
                        </label>

                        <label class="cal-type-option cal-type-option--inhabil">
                            <input type="radio" name="tipo_dia" value="INHABIL" required>
                            <span class="cal-type-option__icon">×</span>
                            <span>
                                <strong>Inhábil</strong>
                                <small>Bloquea trabajos normales.</small>
                            </span>
                        </label>

                        <label class="cal-type-option cal-type-option--extra">
                            <input type="radio" name="tipo_dia" value="HABIL_EXTRA" required>
                            <span class="cal-type-option__icon">+</span>
                            <span>
                                <strong>Hábil extra</strong>
                                <small>Apertura excepcional.</small>
                            </span>
                        </label>
                    </div>

                    <label class="cal-field cal-field--full">
                        <span id="etiquetaMotivo">Motivo u observación</span>
                        <textarea
                            name="motivo"
                            id="diaMotivo"
                            maxlength="200"
                            rows="4"
                            placeholder="Ej. Día festivo, cierre de planta o jornada extraordinaria"
                        ></textarea>
                        <small>
                            Obligatorio para días inhábiles y hábiles extra.
                            <b id="contadorMotivo">0/200</b>
                        </small>
                    </label>

                    <div class="cal-day-warning" id="advertenciaDia" hidden></div>

                    <section class="cal-audit" id="auditoriaDia">
                        <span>Última actualización</span>
                        <strong id="textoAuditoria">Sin cambios registrados</strong>
                    </section>
                </section>

                <section class="cal-day-maintenance">
                    <div class="cal-section-title cal-section-title--maintenance">
                        <div>
                            <span class="cal-section-kicker">Carga de trabajo</span>
                            <h3>Mantenimientos programados</h3>
                            <p>Revisa, abre o cambia la fecha de cada trabajo.</p>
                        </div>
                        <span class="cal-count" id="contadorMantenimientos">0</span>
                    </div>

                    <div class="cal-maintenance-summary" id="resumenMantenimientosDia">
                        <span><b id="totalProgramablesDia">0</b> programables</span>
                        <span><b id="totalUrgentesDia">0</b> urgentes</span>
                        <span><b id="totalBloqueadosDia">0</b> bloqueados</span>
                    </div>

                    <div class="cal-bulk-move" id="barraMoverTodos" hidden>
                        <div class="cal-bulk-move__copy">
                            <span>Siguiente día hábil</span>
                            <strong id="textoSiguienteHabil">Sin fecha disponible</strong>
                            <small>
                                Se conservan los técnicos. Si un trabajo ya inició,
                                no se moverá ninguno.
                            </small>
                        </div>
                        <button
                            type="button"
                            class="cal-btn cal-btn--secondary"
                            id="btnMoverTodos"
                        >
                            Mover todos al siguiente hábil
                        </button>
                    </div>

                    <div class="cal-maintenance-list" id="listaMantenimientos"></div>

                    <div class="cal-empty-list" id="sinMantenimientos" hidden>
                        <span>✓</span>
                        <h4>Sin mantenimientos vigentes</h4>
                        <p>No hay trabajos programados en esta fecha.</p>
                    </div>
                </section>
            </div>

            <footer class="cal-modal__footer cal-modal__footer--day">
                <button
                    type="button"
                    class="cal-btn cal-btn--ghost"
                    id="btnRestaurarDia"
                >
                    Restaurar regla base
                </button>

                <div>
                    <button
                        type="button"
                        class="cal-btn cal-btn--soft"
                        data-cerrar-modal="modalDia"
                    >Cerrar</button>
                    <button
                        type="submit"
                        class="cal-btn cal-btn--primary"
                        id="btnGuardarDia"
                    >Guardar día</button>
                </div>
            </footer>
        </form>
    </div>
</section>

<!-- Reprogramación individual -->
<section class="cal-modal" id="modalReprogramar" hidden>
    <div
        class="cal-modal__dialog cal-modal__dialog--medium"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloReprogramar"
    >
        <header class="cal-modal__header">
            <div>
                <span>Reprogramación individual</span>
                <h2 id="tituloReprogramar">Cambiar fecha</h2>
                <p>Se conservarán los técnicos asignados mientras ninguno haya iniciado.</p>
            </div>
            <button
                type="button"
                class="cal-modal__close"
                data-cerrar-modal="modalReprogramar"
                aria-label="Cerrar"
            >×</button>
        </header>

        <form id="formReprogramar" novalidate>
            <div class="cal-modal__body">
                <input type="hidden" name="accion" value="reprogramar_mantenimiento">
                <input type="hidden" name="solicitud_id" id="reprogramarSolicitudId">
                <input type="hidden" name="fecha_origen" id="reprogramarFechaOrigen">

                <div class="cal-reprogram-card" id="resumenReprogramacion"></div>

                <div class="cal-form-grid cal-form-grid--date">
                    <label class="cal-field">
                        <span>Nueva fecha *</span>
                        <input
                            type="date"
                            name="fecha_destino"
                            id="reprogramarFechaDestino"
                            required
                        >
                    </label>
                    <button
                        type="button"
                        class="cal-btn cal-btn--soft cal-btn--field"
                        id="btnUsarSiguienteHabil"
                    >Usar siguiente hábil</button>
                </div>

                <div class="cal-destination-status" id="estadoFechaDestino">
                    Selecciona una fecha para validar el calendario laboral.
                </div>

                <label class="cal-field cal-field--full">
                    <span>Motivo de reprogramación *</span>
                    <textarea
                        name="motivo_reprogramacion"
                        id="motivoReprogramacion"
                        minlength="10"
                        maxlength="500"
                        rows="4"
                        placeholder="Explica por qué debe cambiarse la fecha"
                        required
                    ></textarea>
                    <small>Mínimo 10 caracteres. <b id="contadorReprogramacion">0/500</b></small>
                </label>
            </div>

            <footer class="cal-modal__footer">
                <button
                    type="button"
                    class="cal-btn cal-btn--soft"
                    data-cerrar-modal="modalReprogramar"
                >Cancelar</button>
                <button
                    type="submit"
                    class="cal-btn cal-btn--primary"
                    id="btnConfirmarReprogramacion"
                    disabled
                >Reprogramar mantenimiento</button>
            </footer>
        </form>
    </div>
</section>

<!-- Movimiento masivo -->
<section class="cal-modal" id="modalMoverTodos" hidden>
    <div
        class="cal-modal__dialog cal-modal__dialog--medium"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloMoverTodos"
    >
        <header class="cal-modal__header">
            <div>
                <span>Movimiento masivo</span>
                <h2 id="tituloMoverTodos">Mover todos los mantenimientos</h2>
                <p>La operación se realizará completa o se cancelará sin mover ninguno.</p>
            </div>
            <button
                type="button"
                class="cal-modal__close"
                data-cerrar-modal="modalMoverTodos"
                aria-label="Cerrar"
            >×</button>
        </header>

        <form id="formMoverTodos" novalidate>
            <div class="cal-modal__body">
                <input type="hidden" name="accion" value="mover_todos_siguiente_habil">
                <input type="hidden" name="fecha_origen" id="moverTodosFechaOrigen">

                <div class="cal-move-route">
                    <div>
                        <span>Fecha actual</span>
                        <strong id="moverDesdeTexto">—</strong>
                    </div>
                    <i aria-hidden="true">→</i>
                    <div>
                        <span>Siguiente día hábil</span>
                        <strong id="moverHaciaTexto">—</strong>
                    </div>
                </div>

                <div class="cal-operation-note">
                    <strong id="moverTotalTexto">0 mantenimientos programables</strong>
                    <p>
                        Se conservarán los técnicos y se actualizarán historial,
                        notificaciones, programación, rutinas e incumplimientos pendientes.
                        Los correctivos urgentes permanecerán en su fecha.
                    </p>
                </div>

                <label class="cal-field cal-field--full">
                    <span>Motivo del movimiento *</span>
                    <textarea
                        name="motivo_reprogramacion"
                        id="motivoMoverTodos"
                        minlength="10"
                        maxlength="500"
                        rows="4"
                        placeholder="Ej. Cierre de planta, día festivo o indisponibilidad general"
                        required
                    ></textarea>
                    <small>Mínimo 10 caracteres. <b id="contadorMoverTodos">0/500</b></small>
                </label>
            </div>

            <footer class="cal-modal__footer">
                <button
                    type="button"
                    class="cal-btn cal-btn--soft"
                    data-cerrar-modal="modalMoverTodos"
                >Cancelar</button>
                <button
                    type="submit"
                    class="cal-btn cal-btn--secondary"
                    id="btnConfirmarMoverTodos"
                >Mover todos</button>
            </footer>
        </form>
    </div>
</section>

<!-- Confirmación general -->
<section class="cal-modal cal-modal--confirm" id="modalConfirmar" hidden>
    <div
        class="cal-modal__dialog cal-modal__dialog--small"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="tituloConfirmar"
    >
        <header class="cal-modal__header">
            <div>
                <span>Confirmación</span>
                <h2 id="tituloConfirmar">¿Continuar?</h2>
            </div>
            <button
                type="button"
                class="cal-modal__close"
                data-cerrar-modal="modalConfirmar"
                aria-label="Cerrar"
            >×</button>
        </header>

        <div class="cal-confirm-body">
            <span class="cal-confirm-icon">!</span>
            <p id="textoConfirmar"></p>
        </div>

        <footer class="cal-modal__footer">
            <button
                type="button"
                class="cal-btn cal-btn--soft"
                data-cerrar-modal="modalConfirmar"
            >Cancelar</button>
            <button
                type="button"
                class="cal-btn cal-btn--danger"
                id="btnAceptarConfirmacion"
            >Confirmar</button>
        </footer>
    </div>
</section>

<div
    class="cal-toast"
    id="toast"
    role="status"
    aria-live="polite"
    hidden
></div>

<script>
(function () {
    'use strict';

    var ENDPOINT = '../funciones/calendario_laboral_funciones.php';
    var CSRF_TOKEN = <?= json_encode(
        $csrfToken,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    var estado = {
        mes: mesActual(),
        dias: [],
        resumen: {},
        diaSeleccionado: null,
        mantenimientos: [],
        resumenMantenimientos: {},
        siguienteDiaHabil: null,
        mantenimientoSeleccionado: null,
        fechaDestinoValida: false,
        validacionFechaSecuencia: 0,
        confirmacion: null,
        cargando: false
    };

    var ui = {};

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        capturarElementos();
        registrarEventos();
        ui.selectorMes.value = estado.mes;
        ui.reprogramarFechaDestino.min = fechaHoy();
        cargarMes(estado.mes);
    }

    function capturarElementos() {
        [
            'btnHoy',
            'btnPrepararMes',
            'mensajePagina',
            'kpiHabiles',
            'kpiInhabiles',
            'kpiExtras',
            'kpiProgramados',
            'textoDiasOcupados',
            'btnMesAnterior',
            'selectorMes',
            'btnMesSiguiente',
            'tituloMes',
            'cargandoPagina',
            'contenedorCalendario',
            'cuadriculaCalendario',
            'ultimaActualizacion',
            'modalDia',
            'tituloModalDia',
            'subtituloModalDia',
            'formDia',
            'diaFecha',
            'estadoActualDia',
            'opcionesTipoDia',
            'diaMotivo',
            'etiquetaMotivo',
            'contadorMotivo',
            'advertenciaDia',
            'textoAuditoria',
            'contadorMantenimientos',
            'resumenMantenimientosDia',
            'totalProgramablesDia',
            'totalUrgentesDia',
            'totalBloqueadosDia',
            'barraMoverTodos',
            'textoSiguienteHabil',
            'btnMoverTodos',
            'listaMantenimientos',
            'sinMantenimientos',
            'btnRestaurarDia',
            'btnGuardarDia',
            'modalReprogramar',
            'formReprogramar',
            'tituloReprogramar',
            'reprogramarSolicitudId',
            'reprogramarFechaOrigen',
            'resumenReprogramacion',
            'reprogramarFechaDestino',
            'btnUsarSiguienteHabil',
            'estadoFechaDestino',
            'motivoReprogramacion',
            'contadorReprogramacion',
            'btnConfirmarReprogramacion',
            'modalMoverTodos',
            'formMoverTodos',
            'moverTodosFechaOrigen',
            'moverDesdeTexto',
            'moverHaciaTexto',
            'moverTotalTexto',
            'motivoMoverTodos',
            'contadorMoverTodos',
            'btnConfirmarMoverTodos',
            'modalConfirmar',
            'tituloConfirmar',
            'textoConfirmar',
            'btnAceptarConfirmacion',
            'toast'
        ].forEach(function (id) {
            ui[id] = document.getElementById(id);
        });
    }

    function registrarEventos() {
        ui.btnHoy.addEventListener('click', function () {
            estado.mes = mesActual();
            ui.selectorMes.value = estado.mes;
            cargarMes(estado.mes);
        });

        ui.btnPrepararMes.addEventListener('click', prepararMes);
        ui.btnMesAnterior.addEventListener('click', function () {
            cambiarMes(-1);
        });
        ui.btnMesSiguiente.addEventListener('click', function () {
            cambiarMes(1);
        });

        ui.selectorMes.addEventListener('change', function () {
            if (!/^\d{4}-\d{2}$/.test(ui.selectorMes.value)) {
                ui.selectorMes.value = estado.mes;
                return;
            }

            estado.mes = ui.selectorMes.value;
            cargarMes(estado.mes);
        });

        ui.cuadriculaCalendario.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-fecha]');
            if (!boton || boton.disabled) return;
            abrirDia(boton.getAttribute('data-fecha'));
        });

        ui.formDia.addEventListener('submit', guardarDia);
        ui.opcionesTipoDia.addEventListener('change', actualizarFormularioTipo);
        ui.diaMotivo.addEventListener('input', function () {
            ui.contadorMotivo.textContent = ui.diaMotivo.value.length + '/200';
        });

        ui.listaMantenimientos.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-reprogramar]');
            if (!boton || boton.disabled) return;

            var solicitudId = Number(boton.getAttribute('data-reprogramar'));
            var item = estado.mantenimientos.find(function (mantenimiento) {
                return Number(mantenimiento.solicitud_id) === solicitudId;
            });

            if (item) abrirReprogramar(item);
        });

        ui.btnMoverTodos.addEventListener('click', abrirMoverTodos);

        ui.btnRestaurarDia.addEventListener('click', function () {
            if (!estado.diaSeleccionado || estado.diaSeleccionado.es_pasado) {
                return;
            }

            confirmar(
                'Restaurar regla base',
                'El día volverá a ser hábil de lunes a viernes o inhábil durante sábado y domingo. Las configuraciones especiales se perderán.',
                restaurarDia
            );
        });

        ui.reprogramarFechaDestino.addEventListener('change', validarFechaDestino);
        ui.btnUsarSiguienteHabil.addEventListener('click', function () {
            if (!estado.siguienteDiaHabil || !estado.siguienteDiaHabil.fecha) {
                toast('No se encontró una fecha hábil sugerida.', 'error');
                return;
            }

            ui.reprogramarFechaDestino.value = estado.siguienteDiaHabil.fecha;
            validarFechaDestino();
        });

        ui.motivoReprogramacion.addEventListener('input', function () {
            ui.contadorReprogramacion.textContent =
                ui.motivoReprogramacion.value.length + '/500';
        });
        ui.formReprogramar.addEventListener('submit', guardarReprogramacion);

        ui.motivoMoverTodos.addEventListener('input', function () {
            ui.contadorMoverTodos.textContent =
                ui.motivoMoverTodos.value.length + '/500';
        });
        ui.formMoverTodos.addEventListener('submit', guardarMoverTodos);

        ui.btnAceptarConfirmacion.addEventListener('click', function () {
            var accion = estado.confirmacion;
            cerrarModal(ui.modalConfirmar);
            estado.confirmacion = null;
            if (typeof accion === 'function') accion();
        });

        document.querySelectorAll('[data-cerrar-modal]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                cerrarModal(
                    document.getElementById(
                        boton.getAttribute('data-cerrar-modal')
                    )
                );
            });
        });

        [
            ui.modalDia,
            ui.modalReprogramar,
            ui.modalMoverTodos,
            ui.modalConfirmar
        ].forEach(function (modal) {
            modal.addEventListener('click', function (evento) {
                if (evento.target === modal) cerrarModal(modal);
            });
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Escape') return;

            if (!ui.modalConfirmar.hidden) {
                cerrarModal(ui.modalConfirmar);
            } else if (!ui.modalMoverTodos.hidden) {
                cerrarModal(ui.modalMoverTodos);
            } else if (!ui.modalReprogramar.hidden) {
                cerrarModal(ui.modalReprogramar);
            } else if (!ui.modalDia.hidden) {
                cerrarModal(ui.modalDia);
            }
        });
    }

    async function cargarMes(mes) {
        if (estado.cargando) return;

        estado.cargando = true;
        mostrarCargando(true);
        ocultarMensaje();
        bloquearNavegacion(true);

        try {
            var datos = await solicitar(
                ENDPOINT + '?accion=inicial&mes=' + encodeURIComponent(mes)
                    + '&t=' + Date.now()
            );
            aplicarMes(datos);
            renderizarCalendario();
        } catch (error) {
            mostrarMensaje(error.message, 'error');
        } finally {
            estado.cargando = false;
            mostrarCargando(false);
            bloquearNavegacion(false);
        }
    }

    async function prepararMes() {
        bloquearBoton(ui.btnPrepararMes, true, 'Verificando...');

        try {
            var formulario = new FormData();
            formulario.append('accion', 'preparar_mes');
            formulario.append('mes', estado.mes);
            formulario.append('csrf_token', CSRF_TOKEN);

            var datos = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            aplicarMes(datos);
            renderizarCalendario();
            toast(datos.mensaje, 'success');
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            bloquearBoton(ui.btnPrepararMes, false, 'Verificar mes');
        }
    }

    function aplicarMes(datos) {
        if (datos.csrf_token) CSRF_TOKEN = datos.csrf_token;

        estado.mes = datos.mes || estado.mes;
        estado.dias = Array.isArray(datos.dias) ? datos.dias : estado.dias;
        estado.resumen = datos.resumen || estado.resumen || {};

        ui.selectorMes.value = estado.mes;
        ui.tituloMes.textContent = datos.titulo_mes || tituloMes(estado.mes);
        ui.kpiHabiles.textContent = numero(estado.resumen.habiles);
        ui.kpiInhabiles.textContent = numero(estado.resumen.inhabiles);
        ui.kpiExtras.textContent = numero(estado.resumen.habiles_extra);
        ui.kpiProgramados.textContent = numero(
            estado.resumen.mantenimientos_programados
        );
        ui.textoDiasOcupados.textContent = numero(
            estado.resumen.con_programaciones
        ) + ' días con actividad';
        ui.ultimaActualizacion.textContent = 'Actualizado: '
            + fechaHoraCorta(datos.fecha_hora_servidor || new Date());
    }

    function renderizarCalendario() {
        var partes = estado.mes.split('-');
        var anio = Number(partes[0]);
        var mes = Number(partes[1]);
        var primerDia = new Date(anio, mes - 1, 1);
        var offset = (primerDia.getDay() + 6) % 7;
        var diasMes = new Date(anio, mes, 0).getDate();
        var diasMesAnterior = new Date(anio, mes - 1, 0).getDate();
        var mapa = {};

        estado.dias.forEach(function (dia) {
            mapa[dia.fecha] = dia;
        });

        var totalCeldas = Math.max(
            Math.ceil((offset + diasMes) / 7) * 7,
            35
        );
        var html = '';

        for (var indice = 0; indice < totalCeldas; indice++) {
            var numeroDia = indice - offset + 1;

            if (numeroDia < 1) {
                html += celdaFuera(diasMesAnterior + numeroDia);
                continue;
            }

            if (numeroDia > diasMes) {
                html += celdaFuera(numeroDia - diasMes);
                continue;
            }

            var fecha = estado.mes + '-' + String(numeroDia).padStart(2, '0');
            html += mapa[fecha]
                ? celdaDia(mapa[fecha])
                : '<div class="cal-day cal-day--missing"><b>'
                    + numeroDia + '</b><small>Sin información</small></div>';
        }

        ui.cuadriculaCalendario.innerHTML = html;
        ui.contenedorCalendario.hidden = false;
    }

    function celdaDia(dia) {
        var clase = 'cal-day cal-day--' + claseTipo(dia.tipo_dia);
        if (dia.es_hoy) clase += ' is-today';
        if (dia.es_pasado) clase += ' is-past';
        if (Number(dia.total_programados) > 0) clase += ' has-maintenance';
        if (Number(dia.total_iniciados) > 0) clase += ' has-started';

        var motivo = dia.motivo
            ? '<p title="' + escaparAtributo(dia.motivo) + '">'
                + escapar(dia.motivo) + '</p>'
            : '<p class="is-muted">' + textoBaseDia(dia) + '</p>';

        var actividad = Number(dia.total_programados) > 0
            ? '<div class="cal-day__activity">'
                + '<strong>' + numero(dia.total_programados) + '</strong>'
                + '<span>' + (Number(dia.total_programados) === 1
                    ? 'mantenimiento'
                    : 'mantenimientos') + '</span>'
                + (Number(dia.total_urgentes) > 0
                    ? '<em>' + numero(dia.total_urgentes) + ' urgente(s)</em>'
                    : '')
                + '</div>'
            : '<div class="cal-day__activity is-empty">Sin actividad</div>';

        return '<button type="button" class="' + clase + '" data-fecha="'
            + escaparAtributo(dia.fecha) + '" aria-label="'
            + escaparAtributo(
                dia.dia_semana_texto + ' ' + dia.numero + '. '
                + textoTipo(dia.tipo_dia) + '. '
                + Number(dia.total_programados) + ' mantenimientos.'
            ) + '">'
            + '<span class="cal-day__top">'
            + '<b>' + dia.numero + '</b>'
            + '<em>' + textoTipoCorto(dia.tipo_dia) + '</em>'
            + '</span>'
            + motivo
            + actividad
            + '</button>';
    }

    function celdaFuera(numeroDia) {
        return '<div class="cal-day cal-day--outside"><span>'
            + numeroDia + '</span></div>';
    }

    async function abrirDia(fecha) {
        limpiarModalDia();
        abrirModal(ui.modalDia);
        ui.tituloModalDia.textContent = 'Cargando día...';
        ui.subtituloModalDia.textContent = '';

        try {
            var datos = await solicitar(
                ENDPOINT + '?accion=dia&fecha=' + encodeURIComponent(fecha)
                    + '&t=' + Date.now()
            );
            aplicarDia(datos);
        } catch (error) {
            cerrarModal(ui.modalDia);
            toast(error.message, 'error');
        }
    }

    function limpiarModalDia() {
        ui.formDia.reset();
        ui.listaMantenimientos.innerHTML = '';
        ui.sinMantenimientos.hidden = true;
        ui.advertenciaDia.hidden = true;
        ui.barraMoverTodos.hidden = true;
        ui.btnGuardarDia.disabled = true;
        ui.btnRestaurarDia.disabled = true;
    }

    function aplicarDia(datos) {
        estado.diaSeleccionado = datos.dia || estado.diaSeleccionado;
        estado.mantenimientos = Array.isArray(datos.mantenimientos)
            ? datos.mantenimientos
            : [];
        estado.resumenMantenimientos = datos.resumen_mantenimientos || {};
        estado.siguienteDiaHabil = datos.siguiente_dia_habil || null;
        llenarModalDia();
    }

    function llenarModalDia() {
        var dia = estado.diaSeleccionado;
        if (!dia) return;

        ui.diaFecha.value = dia.fecha;
        ui.tituloModalDia.textContent = dia.fecha_texto;
        ui.subtituloModalDia.textContent = dia.es_pasado
            ? 'Fecha histórica: la clasificación es sólo de consulta, pero los trabajos no iniciados todavía pueden reprogramarse.'
            : 'Configura la disponibilidad o administra los mantenimientos del día.';
        ui.estadoActualDia.textContent = textoTipo(dia.tipo_dia);
        ui.estadoActualDia.className = 'cal-current-status is-'
            + claseTipo(dia.tipo_dia);

        var radio = ui.formDia.querySelector(
            'input[name="tipo_dia"][value="' + dia.tipo_dia + '"]'
        );
        if (radio) radio.checked = true;

        ui.diaMotivo.value = dia.motivo || '';
        ui.contadorMotivo.textContent = ui.diaMotivo.value.length + '/200';

        var soloLectura = Boolean(dia.es_pasado);
        ui.formDia.querySelectorAll('input[name="tipo_dia"]').forEach(
            function (input) {
                input.disabled = soloLectura;
            }
        );
        ui.diaMotivo.disabled = soloLectura;
        ui.btnGuardarDia.disabled = soloLectura;
        ui.btnRestaurarDia.disabled = soloLectura;

        renderizarMantenimientos();
        actualizarFormularioTipo();

        var auditor = dia.modificado_por || dia.creado_por || 'Administrador';
        ui.textoAuditoria.textContent = auditor + ' · '
            + fechaHoraCorta(dia.fecha_actualizacion || dia.fecha_registro);
    }

    function renderizarMantenimientos() {
        var items = estado.mantenimientos;
        var resumen = estado.resumenMantenimientos || {};

        ui.contadorMantenimientos.textContent = numero(items.length);
        ui.totalProgramablesDia.textContent = numero(resumen.programables);
        ui.totalUrgentesDia.textContent = numero(resumen.urgentes);
        ui.totalBloqueadosDia.textContent = numero(resumen.bloqueados);

        actualizarBarraMovimiento();

        if (!items.length) {
            ui.listaMantenimientos.innerHTML = '';
            ui.sinMantenimientos.hidden = false;
            return;
        }

        ui.sinMantenimientos.hidden = true;
        ui.listaMantenimientos.innerHTML = items.map(function (item) {
            var puede = Boolean(item.puede_reprogramar);
            var accionPrincipal = '';

            if (item.es_urgente) {
                accionPrincipal = '<span class="cal-action-state is-urgent">Urgente exento</span>';
            } else if (puede) {
                accionPrincipal = '<button type="button" class="cal-btn cal-btn--small '
                    + 'cal-btn--secondary" data-reprogramar="'
                    + escaparAtributo(item.solicitud_id) + '">Reprogramar</button>';
            } else {
                accionPrincipal = '<button type="button" class="cal-btn cal-btn--small '
                    + 'cal-btn--soft" disabled title="'
                    + escaparAtributo(item.motivo_bloqueo || 'No disponible')
                    + '">No se puede mover</button>';
            }

            return '<article class="cal-maintenance '
                + (item.es_urgente ? 'is-urgent ' : '')
                + (!puede && !item.es_urgente ? 'is-locked' : '') + '">'
                + '<div class="cal-maintenance__main">'
                + '<div class="cal-maintenance__badges">'
                + '<span class="cal-badge cal-badge--'
                + clasePrioridad(item.prioridad) + '">'
                + escapar(item.prioridad) + '</span>'
                + '<span class="cal-badge cal-badge--neutral">'
                + escapar(textoTipoSolicitud(item.tipo_solicitud)) + '</span>'
                + (Number(item.total_iniciados) > 0
                    ? '<span class="cal-badge cal-badge--danger">Iniciado</span>'
                    : '')
                + '</div>'
                + '<h4>' + escapar(item.folio) + '</h4>'
                + '<strong>' + escapar(item.codigo_equipo) + ' · '
                + escapar(item.nombre_equipo) + '</strong>'
                + '<p>' + escapar(item.departamento) + ' / '
                + escapar(item.area) + '</p>'
                + '<small><b>Técnicos:</b> '
                + escapar(item.tecnicos || 'Sin técnicos activos') + '</small>'
                + (!puede && item.motivo_bloqueo
                    ? '<div class="cal-maintenance__reason">'
                        + escapar(item.motivo_bloqueo) + '</div>'
                    : '')
                + '</div>'
                + '<div class="cal-maintenance__actions">'
                + accionPrincipal
                + '<a class="cal-link-button" href="'
                + escaparAtributo(item.url_programacion) + '">Abrir programación</a>'
                + '</div>'
                + '</article>';
        }).join('');
    }

    function actualizarBarraMovimiento() {
        var resumen = estado.resumenMantenimientos || {};
        var programables = Number(resumen.programables || 0);
        var bloqueados = Number(resumen.bloqueados || 0);
        var siguiente = estado.siguienteDiaHabil;

        ui.barraMoverTodos.hidden = programables < 1;
        ui.btnMoverTodos.disabled = programables < 1 || bloqueados > 0 || !siguiente;

        if (siguiente && siguiente.fecha) {
            ui.textoSiguienteHabil.textContent = siguiente.fecha_texto;
        } else {
            ui.textoSiguienteHabil.textContent = 'Sin fecha disponible';
        }

        if (bloqueados > 0) {
            ui.btnMoverTodos.title = 'Hay trabajos iniciados o con asignaciones que requieren revisión.';
        } else {
            ui.btnMoverTodos.removeAttribute('title');
        }
    }

    function actualizarFormularioTipo() {
        var seleccionado = ui.formDia.querySelector(
            'input[name="tipo_dia"]:checked'
        );
        var tipo = seleccionado ? seleccionado.value : '';
        var resumen = estado.resumenMantenimientos || {};
        var programables = Number(resumen.programables || 0);
        var bloqueados = Number(resumen.bloqueados || 0);
        var soloLectura = Boolean(
            estado.diaSeleccionado && estado.diaSeleccionado.es_pasado
        );

        ui.diaMotivo.required = tipo === 'INHABIL' || tipo === 'HABIL_EXTRA';
        ui.etiquetaMotivo.textContent = tipo === 'INHABIL'
            ? 'Motivo del día inhábil *'
            : (tipo === 'HABIL_EXTRA'
                ? 'Motivo de la apertura extraordinaria *'
                : 'Motivo u observación');

        var bloquearGuardado = false;

        if (tipo === 'INHABIL' && programables > 0 && !soloLectura) {
            bloquearGuardado = true;
            ui.advertenciaDia.hidden = false;
            ui.advertenciaDia.className = 'cal-day-warning is-danger';

            if (bloqueados > 0) {
                ui.advertenciaDia.innerHTML = '<strong>No puede marcarse como inhábil.</strong>'
                    + '<p>Hay ' + bloqueados
                    + ' trabajo(s) iniciado(s) o con una asignación que requiere revisión. '
                    + 'Esos mantenimientos no pueden moverse automáticamente.</p>';
            } else {
                ui.advertenciaDia.innerHTML = '<strong>Primero mueve los mantenimientos.</strong>'
                    + '<p>Puedes reprogramarlos uno por uno o usar “Mover todos al siguiente hábil”. '
                    + 'Después podrás guardar el día como inhábil.</p>';
            }
        } else {
            ui.advertenciaDia.hidden = true;
            ui.advertenciaDia.innerHTML = '';
        }

        ui.btnGuardarDia.disabled = soloLectura || bloquearGuardado;
    }

    async function guardarDia(evento) {
        evento.preventDefault();

        if (!ui.formDia.checkValidity()) {
            ui.formDia.reportValidity();
            return;
        }

        var seleccionado = ui.formDia.querySelector(
            'input[name="tipo_dia"]:checked'
        );

        if (!seleccionado) {
            toast('Selecciona la clasificación del día.', 'error');
            return;
        }

        if (
            (seleccionado.value === 'INHABIL'
                || seleccionado.value === 'HABIL_EXTRA')
            && ui.diaMotivo.value.trim().length < 5
        ) {
            toast('Escribe un motivo de al menos 5 caracteres.', 'error');
            ui.diaMotivo.focus();
            return;
        }

        bloquearBoton(ui.btnGuardarDia, true, 'Guardando...');

        try {
            var formulario = new FormData(ui.formDia);
            formulario.set('accion', 'guardar_dia');
            formulario.set('csrf_token', CSRF_TOKEN);

            var datos = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            aplicarMes(datos);
            renderizarCalendario();
            cerrarModal(ui.modalDia);
            toast(datos.mensaje, 'success');
        } catch (error) {
            if (error.datos && Array.isArray(error.datos.mantenimientos)) {
                estado.mantenimientos = error.datos.mantenimientos;
                estado.resumenMantenimientos = error.datos.resumen_mantenimientos
                    || resumenDesdeItems(estado.mantenimientos);
                estado.siguienteDiaHabil = error.datos.siguiente_dia_habil
                    || estado.siguienteDiaHabil;
                renderizarMantenimientos();
                actualizarFormularioTipo();
            }
            toast(error.message, 'error');
        } finally {
            bloquearBoton(ui.btnGuardarDia, false, 'Guardar día');
            actualizarFormularioTipo();
        }
    }

    async function restaurarDia() {
        if (!estado.diaSeleccionado) return;

        bloquearBoton(ui.btnRestaurarDia, true, 'Restaurando...');

        try {
            var formulario = new FormData();
            formulario.append('accion', 'restaurar_dia');
            formulario.append('fecha', estado.diaSeleccionado.fecha);
            formulario.append('csrf_token', CSRF_TOKEN);

            var datos = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            aplicarMes(datos);
            renderizarCalendario();
            cerrarModal(ui.modalDia);
            toast(datos.mensaje, 'success');
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            bloquearBoton(ui.btnRestaurarDia, false, 'Restaurar regla base');
        }
    }

    function abrirReprogramar(item) {
        estado.mantenimientoSeleccionado = item;
        estado.fechaDestinoValida = false;
        ui.formReprogramar.reset();
        ui.reprogramarSolicitudId.value = item.solicitud_id;
        ui.reprogramarFechaOrigen.value = item.fecha_programada;
        ui.reprogramarFechaDestino.min = fechaHoy();
        ui.btnConfirmarReprogramacion.disabled = true;
        ui.contadorReprogramacion.textContent = '0/500';
        ui.tituloReprogramar.textContent = 'Reprogramar ' + item.folio;
        ui.resumenReprogramacion.innerHTML = '<span class="cal-badge cal-badge--'
            + clasePrioridad(item.prioridad) + '">' + escapar(item.prioridad)
            + '</span><h3>' + escapar(item.codigo_equipo) + ' · '
            + escapar(item.nombre_equipo) + '</h3><p>'
            + escapar(item.departamento) + ' / ' + escapar(item.area)
            + '</p><small><b>Fecha actual:</b> '
            + escapar(fechaLarga(item.fecha_programada))
            + '<br><b>Técnicos que se conservarán:</b> '
            + escapar(item.tecnicos || 'Sin información') + '</small>';

        if (estado.siguienteDiaHabil && estado.siguienteDiaHabil.fecha) {
            ui.reprogramarFechaDestino.value = estado.siguienteDiaHabil.fecha;
        }

        abrirModal(ui.modalReprogramar);
        validarFechaDestino();
    }

    async function validarFechaDestino() {
        var fecha = ui.reprogramarFechaDestino.value;
        var origen = ui.reprogramarFechaOrigen.value;
        var secuencia = ++estado.validacionFechaSecuencia;

        estado.fechaDestinoValida = false;
        ui.btnConfirmarReprogramacion.disabled = true;

        if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha)) {
            mostrarEstadoDestino('Selecciona una fecha válida.', 'neutral');
            return;
        }

        if (fecha < fechaHoy()) {
            mostrarEstadoDestino('La fecha no puede ser anterior a hoy.', 'error');
            return;
        }

        if (fecha === origen) {
            mostrarEstadoDestino('Selecciona una fecha diferente a la actual.', 'error');
            return;
        }

        mostrarEstadoDestino('Validando disponibilidad...', 'loading');

        try {
            var datos = await solicitar(
                ENDPOINT + '?accion=fecha_destino&fecha='
                    + encodeURIComponent(fecha) + '&t=' + Date.now()
            );

            if (secuencia !== estado.validacionFechaSecuencia) return;

            var calendario = datos.calendario || {};
            estado.fechaDestinoValida = Boolean(calendario.permitido);
            mostrarEstadoDestino(
                calendario.mensaje || 'Fecha consultada.',
                calendario.permitido ? 'success' : 'error'
            );
            ui.btnConfirmarReprogramacion.disabled = !estado.fechaDestinoValida;
        } catch (error) {
            if (secuencia !== estado.validacionFechaSecuencia) return;
            mostrarEstadoDestino(error.message, 'error');
        }
    }

    function mostrarEstadoDestino(texto, tipo) {
        ui.estadoFechaDestino.textContent = texto;
        ui.estadoFechaDestino.className = 'cal-destination-status is-'
            + (tipo || 'neutral');
    }

    async function guardarReprogramacion(evento) {
        evento.preventDefault();

        if (!ui.formReprogramar.checkValidity()) {
            ui.formReprogramar.reportValidity();
            return;
        }

        if (!estado.fechaDestinoValida) {
            toast('Selecciona una fecha hábil válida.', 'error');
            return;
        }

        if (ui.motivoReprogramacion.value.trim().length < 10) {
            toast('Escribe un motivo de al menos 10 caracteres.', 'error');
            ui.motivoReprogramacion.focus();
            return;
        }

        bloquearBoton(
            ui.btnConfirmarReprogramacion,
            true,
            'Reprogramando...'
        );

        try {
            var formulario = new FormData(ui.formReprogramar);
            formulario.set('accion', 'reprogramar_mantenimiento');
            formulario.set('csrf_token', CSRF_TOKEN);

            var datos = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            aplicarMes(datos);
            renderizarCalendario();
            aplicarDia(datos);
            cerrarModal(ui.modalReprogramar);
            toast(datos.mensaje, 'success');
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            bloquearBoton(
                ui.btnConfirmarReprogramacion,
                false,
                'Reprogramar mantenimiento'
            );
            ui.btnConfirmarReprogramacion.disabled = !estado.fechaDestinoValida;
        }
    }

    function abrirMoverTodos() {
        var resumen = estado.resumenMantenimientos || {};
        var siguiente = estado.siguienteDiaHabil;

        if (!estado.diaSeleccionado || !siguiente || !siguiente.fecha) {
            toast('No se encontró el siguiente día hábil.', 'error');
            return;
        }

        if (Number(resumen.bloqueados || 0) > 0) {
            toast(
                'Hay trabajos iniciados o con asignaciones que deben revisarse individualmente.',
                'error'
            );
            return;
        }

        ui.formMoverTodos.reset();
        ui.moverTodosFechaOrigen.value = estado.diaSeleccionado.fecha;
        ui.moverDesdeTexto.textContent = estado.diaSeleccionado.fecha_texto;
        ui.moverHaciaTexto.textContent = siguiente.fecha_texto;
        ui.moverTotalTexto.textContent = numero(resumen.programables)
            + (Number(resumen.programables) === 1
                ? ' mantenimiento programable'
                : ' mantenimientos programables');
        ui.contadorMoverTodos.textContent = '0/500';
        abrirModal(ui.modalMoverTodos);
        ui.motivoMoverTodos.focus();
    }

    async function guardarMoverTodos(evento) {
        evento.preventDefault();

        if (!ui.formMoverTodos.checkValidity()) {
            ui.formMoverTodos.reportValidity();
            return;
        }

        if (ui.motivoMoverTodos.value.trim().length < 10) {
            toast('Escribe un motivo de al menos 10 caracteres.', 'error');
            ui.motivoMoverTodos.focus();
            return;
        }

        bloquearBoton(ui.btnConfirmarMoverTodos, true, 'Moviendo...');

        try {
            var formulario = new FormData(ui.formMoverTodos);
            formulario.set('accion', 'mover_todos_siguiente_habil');
            formulario.set('csrf_token', CSRF_TOKEN);

            var datos = await solicitar(ENDPOINT, {
                method: 'POST',
                body: formulario
            });

            aplicarMes(datos);
            renderizarCalendario();
            aplicarDia(datos);
            cerrarModal(ui.modalMoverTodos);
            toast(datos.mensaje, 'success');
        } catch (error) {
            if (error.datos && Array.isArray(error.datos.bloqueados)) {
                toast(
                    error.message + ' Bloqueados: '
                        + error.datos.bloqueados.map(function (item) {
                            return item.folio;
                        }).join(', '),
                    'error'
                );
            } else {
                toast(error.message, 'error');
            }
        } finally {
            bloquearBoton(ui.btnConfirmarMoverTodos, false, 'Mover todos');
        }
    }

    function resumenDesdeItems(items) {
        var resumen = {
            total: items.length,
            urgentes: 0,
            programables: 0,
            reprogramables: 0,
            bloqueados: 0,
            iniciados: 0
        };

        items.forEach(function (item) {
            if (item.es_urgente) {
                resumen.urgentes++;
                return;
            }
            resumen.programables++;
            if (item.puede_reprogramar) resumen.reprogramables++;
            else resumen.bloqueados++;
            if (Number(item.total_iniciados) > 0) resumen.iniciados++;
        });

        return resumen;
    }

    function cambiarMes(delta) {
        var partes = estado.mes.split('-');
        var fecha = new Date(
            Number(partes[0]),
            Number(partes[1]) - 1 + delta,
            1
        );
        var nuevoMes = fecha.getFullYear() + '-'
            + String(fecha.getMonth() + 1).padStart(2, '0');

        if (nuevoMes < '2020-01' || nuevoMes > '2100-12') {
            toast('El calendario permite consultar años entre 2020 y 2100.', 'info');
            return;
        }

        estado.mes = nuevoMes;
        ui.selectorMes.value = estado.mes;
        cargarMes(estado.mes);
    }

    async function solicitar(url, opciones) {
        var configuracion = opciones || {};
        configuracion.headers = configuracion.headers || {};
        configuracion.headers['X-Requested-With'] = 'XMLHttpRequest';
        configuracion.credentials = 'same-origin';

        var respuesta;
        try {
            respuesta = await fetch(url, configuracion);
        } catch (error) {
            throw new Error('No fue posible comunicarse con el servidor.');
        }

        var texto = await respuesta.text();
        var datos;

        try {
            datos = JSON.parse(texto);
        } catch (error) {
            throw new Error(
                'El servidor devolvió una respuesta inválida. Revisa el registro de PHP.'
            );
        }

        if (datos.csrf_token) CSRF_TOKEN = datos.csrf_token;

        if (!respuesta.ok || !datos.success) {
            if (datos.sesion_expirada && datos.redirect) {
                window.location.href = datos.redirect;
                throw new Error('La sesión expiró.');
            }

            var fallo = new Error(
                datos.mensaje || 'La operación no pudo completarse.'
            );
            fallo.datos = datos;
            throw fallo;
        }

        return datos;
    }

    function mostrarCargando(mostrar) {
        ui.cargandoPagina.hidden = !mostrar;
        if (mostrar) ui.contenedorCalendario.hidden = true;
    }

    function bloquearNavegacion(bloqueado) {
        ui.btnMesAnterior.disabled = bloqueado;
        ui.btnMesSiguiente.disabled = bloqueado;
        ui.selectorMes.disabled = bloqueado;
        ui.btnHoy.disabled = bloqueado;
    }

    function bloquearBoton(boton, bloqueado, texto) {
        if (!boton) return;

        if (!boton.dataset.textoOriginal) {
            boton.dataset.textoOriginal = boton.textContent.trim();
        }

        boton.disabled = bloqueado;
        boton.textContent = bloqueado
            ? texto
            : boton.dataset.textoOriginal;
    }

    function mostrarMensaje(mensaje, tipo) {
        ui.mensajePagina.textContent = mensaje;
        ui.mensajePagina.className = 'cal-message cal-message--'
            + (tipo || 'info');
        ui.mensajePagina.hidden = false;
    }

    function ocultarMensaje() {
        ui.mensajePagina.hidden = true;
        ui.mensajePagina.textContent = '';
    }

    function toast(mensaje, tipo) {
        ui.toast.textContent = mensaje;
        ui.toast.className = 'cal-toast is-' + (tipo || 'info');
        ui.toast.hidden = false;

        window.clearTimeout(toast.temporizador);
        toast.temporizador = window.setTimeout(function () {
            ui.toast.hidden = true;
        }, 5200);
    }

    function confirmar(titulo, texto, accion) {
        estado.confirmacion = accion;
        ui.tituloConfirmar.textContent = titulo;
        ui.textoConfirmar.textContent = texto;
        abrirModal(ui.modalConfirmar);
        ui.btnAceptarConfirmacion.focus();
    }

    function abrirModal(modal) {
        modal.hidden = false;
        document.body.classList.add('cal-modal-open');
    }

    function cerrarModal(modal) {
        if (!modal) return;
        modal.hidden = true;
        actualizarBloqueoBody();
    }

    function actualizarBloqueoBody() {
        var algunoAbierto = [
            ui.modalDia,
            ui.modalReprogramar,
            ui.modalMoverTodos,
            ui.modalConfirmar
        ].some(function (modal) {
            return modal && !modal.hidden;
        });

        document.body.classList.toggle('cal-modal-open', algunoAbierto);
    }

    function textoTipo(tipo) {
        return {
            HABIL: 'Día hábil',
            INHABIL: 'Día inhábil',
            HABIL_EXTRA: 'Hábil extra'
        }[tipo] || 'Sin clasificación';
    }

    function textoTipoCorto(tipo) {
        return {
            HABIL: 'Hábil',
            INHABIL: 'Inhábil',
            HABIL_EXTRA: 'Extra'
        }[tipo] || 'Día';
    }

    function claseTipo(tipo) {
        return {
            HABIL: 'habil',
            INHABIL: 'inhabil',
            HABIL_EXTRA: 'extra'
        }[tipo] || 'neutral';
    }

    function textoBaseDia(dia) {
        if (dia.tipo_dia === 'INHABIL') return 'No disponible';
        if (dia.tipo_dia === 'HABIL_EXTRA') return 'Apertura extraordinaria';
        return 'Disponible para programar';
    }

    function textoTipoSolicitud(tipo) {
        return {
            CORRECTIVO_PROGRAMABLE: 'Correctivo programable',
            MODIFICACION_MEJORA: 'Modificación o mejora',
            CORRECTIVO_URGENTE: 'Correctivo urgente',
            RUTINARIO: 'Rutinario'
        }[tipo] || tipo;
    }

    function clasePrioridad(prioridad) {
        return {
            URGENTE: 'urgente',
            ALTA: 'alta',
            MEDIA: 'media',
            BAJA: 'baja'
        }[prioridad] || 'neutral';
    }

    function mesActual() {
        var hoy = new Date();
        return hoy.getFullYear() + '-'
            + String(hoy.getMonth() + 1).padStart(2, '0');
    }

    function fechaHoy() {
        var hoy = new Date();
        return hoy.getFullYear() + '-'
            + String(hoy.getMonth() + 1).padStart(2, '0') + '-'
            + String(hoy.getDate()).padStart(2, '0');
    }

    function tituloMes(mes) {
        var partes = mes.split('-');
        var fecha = new Date(Number(partes[0]), Number(partes[1]) - 1, 1);
        var texto = fecha.toLocaleDateString('es-MX', {
            month: 'long',
            year: 'numeric'
        });
        return texto.charAt(0).toUpperCase() + texto.slice(1);
    }

    function fechaLarga(valor) {
        if (!valor) return 'Sin fecha';
        var fecha = new Date(String(valor) + 'T12:00:00');
        if (Number.isNaN(fecha.getTime())) return String(valor);

        var texto = fecha.toLocaleDateString('es-MX', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
        return texto.charAt(0).toUpperCase() + texto.slice(1);
    }

    function fechaHoraCorta(valor) {
        if (!valor) return 'Sin información';

        var fecha = valor instanceof Date
            ? valor
            : new Date(String(valor).replace(' ', 'T'));

        if (Number.isNaN(fecha.getTime())) return String(valor);

        return fecha.toLocaleString('es-MX', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function numero(valor) {
        var numeroValor = Number(valor || 0);
        return Number.isFinite(numeroValor)
            ? numeroValor.toLocaleString('es-MX')
            : '0';
    }

    function escapar(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;') 
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escaparAtributo(valor) {
        return escapar(valor).replace(/`/g, '&#096;');
    }
}());
</script>

</body>
</html> 