<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/seguridad.php';

sm_requerir_sesion(['ADMIN', 'SOLICITANTE'], false);

$hoyServidor = date('Y-m-d');
$rolSesion = strtoupper((string) ($_SESSION['tipo_usuario'] ?? ''));
$esAdministrador = $rolSesion === 'ADMIN';
$numeroEquipo = $esAdministrador ? '2' : '1';
$numeroSolicitud = $esAdministrador ? '3' : '2';
$numeroSeguridad = $esAdministrador ? '4' : '3';

$cssSolicitud = __DIR__ . '/../css/style_solicitud_correctivo_programable.css';
$versionCss = file_exists($cssSolicitud)
    ? (string) filemtime($cssSolicitud)
    : (string) time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#09233b">
    <meta
        name="description"
        content="Registro profesional de solicitudes de mantenimiento correctivo programable"
    >

    <title>Correctivo programable | Sistema de Mantenimiento</title>

    <link
        rel="stylesheet"
        href="../css/style_solicitud_correctivo_programable.css?v=<?= htmlspecialchars($versionCss, ENT_QUOTES, 'UTF-8') ?>"
    >
</head>
<body>

<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="scp-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="scp-icon-sparkles" viewBox="0 0 24 24">
        <path d="m12 3-1.2 3.8L7 8l3.8 1.2L12 13l1.2-3.8L17 8l-3.8-1.2L12 3Z"/>
        <path d="m5 15-.7 2.3L2 18l2.3.7L5 21l.7-2.3L8 18l-2.3-.7L5 15Z"/>
        <path d="m19 13-.7 2.3-2.3.7 2.3.7L19 19l.7-2.3 2.3-.7-2.3-.7L19 13Z"/>
    </symbol>
    <symbol id="scp-icon-reset" viewBox="0 0 24 24">
        <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>
        <path d="M3 3v5h5"/>
    </symbol>
    <symbol id="scp-icon-clipboard" viewBox="0 0 24 24">
        <rect x="5" y="4" width="14" height="17" rx="2"/>
        <path d="M9 4V2h6v2M9 9h6M9 13h6M9 17h4"/>
    </symbol>
    <symbol id="scp-icon-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </symbol>
    <symbol id="scp-icon-calendar" viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path d="M16 3v4M8 3v4M3 10h18"/>
    </symbol>
    <symbol id="scp-icon-activity" viewBox="0 0 24 24">
        <path d="M3 12h4l2-7 4 14 2-7h6"/>
    </symbol>
    <symbol id="scp-icon-check" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8 12 2.6 2.6L16.5 9"/>
    </symbol>
    <symbol id="scp-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="scp-icon-shield" viewBox="0 0 24 24">
        <path d="M12 3 5 6v5c0 4.5 2.8 8.1 7 10 4.2-1.9 7-5.5 7-10V6l-7-3Z"/>
        <path d="m9 12 2 2 4-4"/>
    </symbol>
    <symbol id="scp-icon-user" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="4"/>
        <path d="M4 21a8 8 0 0 1 16 0"/>
    </symbol>
    <symbol id="scp-icon-tools" viewBox="0 0 24 24">
        <path d="M14.7 6.3a4 4 0 0 0-5-5L7.4 3.6l3 3 2.3-2.3a4 4 0 0 0 2 2Z"/>
        <path d="m10.3 6.7-8.6 8.6a2.1 2.1 0 0 0 3 3l8.6-8.6"/>
        <path d="m14 14 6 6"/>
    </symbol>
    <symbol id="scp-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
</svg>

<main class="contenido-principal scp-page">
    <div class="scp-ambient scp-ambient--one" aria-hidden="true"></div>
    <div class="scp-ambient scp-ambient--two" aria-hidden="true"></div>
    <section class="scp-encabezado" aria-labelledby="tituloCorrectivoProgramable">
        <div class="scp-encabezado__pattern" aria-hidden="true"></div>

        <div class="scp-encabezado__contenido">
            <div class="scp-encabezado__copy">
                <span class="scp-kicker">
                    <span class="scp-kicker__icono" aria-hidden="true">
                        <svg><use href="#scp-icon-sparkles"></use></svg>
                    </span>
                    Nueva solicitud
                </span>

                <h1 id="tituloCorrectivoProgramable">Correctivo programable</h1>

                <p>
                    <?= $esAdministrador
                        ? 'Registra una solicitud directa o a nombre de un solicitante activo. Después podrá revisarse, corregirse y programarse.'
                        : 'Reporta un problema que puede ser revisado y programado por mantenimiento. Si el equipo está detenido o existe peligro inmediato, utiliza Correctivo urgente.'
                    ?>
                </p>

                <div class="scp-encabezado__meta">
                    <span>
                        <i class="scp-live-dot" aria-hidden="true"></i>
                        Flujo de registro protegido
                    </span>
                    <span><?= $esAdministrador ? 'Captura administrativa' : 'Portal del solicitante' ?></span>
                </div>
            </div>

            <div class="scp-encabezado__acciones">
                <div class="scp-hero-mini-card" aria-hidden="true">
                    <span class="scp-hero-mini-card__icono">
                        <svg><use href="#scp-icon-shield"></use></svg>
                    </span>
                    <div>
                        <small>Estado inicial</small>
                        <strong>Pendiente de revisión</strong>
                    </div>
                </div>

                <button
                    type="button"
                    class="scp-btn scp-btn--secundario scp-btn--hero"
                    id="btnNuevaSolicitud"
                >
                    <span class="scp-btn__icono" aria-hidden="true">
                        <svg><use href="#scp-icon-reset"></use></svg>
                    </span>
                    <span>Limpiar formulario</span>
                </button>
            </div>
        </div>
    </section>

    <section class="scp-resumen" aria-label="Resumen de solicitudes programables">
        <article class="scp-resumen__card scp-resumen__card--total">
            <span class="scp-resumen__icono" aria-hidden="true">
                <svg><use href="#scp-icon-clipboard"></use></svg>
            </span>
            <div>
                <span>Total registradas</span>
                <strong id="resumenTotal">0</strong>
                <small>Histórico disponible</small>
            </div>
        </article>

        <article class="scp-resumen__card scp-resumen--pendiente">
            <span class="scp-resumen__icono" aria-hidden="true">
                <svg><use href="#scp-icon-clock"></use></svg>
            </span>
            <div>
                <span>Pendientes de revisión</span>
                <strong id="resumenPendientes">0</strong>
                <small>Esperan validación</small>
            </div>
        </article>

        <article class="scp-resumen__card scp-resumen__card--authorized">
            <span class="scp-resumen__icono" aria-hidden="true">
                <svg><use href="#scp-icon-calendar"></use></svg>
            </span>
            <div>
                <span>Aprobadas o agendadas</span>
                <strong id="resumenAutorizadas">0</strong>
                <small>Listas para atención</small>
            </div>
        </article>

        <article class="scp-resumen__card scp-resumen__card--tracking">
            <span class="scp-resumen__icono" aria-hidden="true">
                <svg><use href="#scp-icon-activity"></use></svg>
            </span>
            <div>
                <span>En seguimiento</span>
                <strong id="resumenSeguimiento">0</strong>
                <small>Trabajo en curso</small>
            </div>
        </article>

        <article class="scp-resumen__card scp-resumen--terminada">
            <span class="scp-resumen__icono" aria-hidden="true">
                <svg><use href="#scp-icon-check"></use></svg>
            </span>
            <div>
                <span>Terminadas</span>
                <strong id="resumenTerminadas">0</strong>
                <small>Cierres registrados</small>
            </div>
        </article>
    </section>

    <div
        class="scp-mensaje"
        id="mensajePagina"
        role="status"
        aria-live="polite"
        hidden
    ></div>

    <section class="scp-layout">
        <article class="scp-panel scp-panel--formulario">
            <header class="scp-panel__encabezado">
                <div>
                    <span class="scp-panel__etiqueta">Formulario</span>
                    <h2>Información del mantenimiento</h2>
                    <p>Los campos marcados con * son obligatorios. <?= $esAdministrador ? 'La solicitud quedará registrada con el administrador como capturista.' : 'El administrador podrá completar o corregir la información.' ?></p>
                </div>

                <div class="scp-fecha-servidor">
                    <span>Fecha de registro</span>
                    <strong id="fechaHoraServidor">Cargando...</strong>
                </div>
            </header>

            <form id="formSolicitud" novalidate>
                <input type="hidden" name="accion" value="crear">
                <input type="hidden" id="form_token" name="form_token" value="">
                <input type="hidden" id="equipo_id" name="equipo_id" value="">

                <?php if ($esAdministrador): ?>
                    <section class="scp-seccion scp-seccion--origen" aria-labelledby="tituloOrigenSolicitud">
                        <div class="scp-seccion__titulo">
                            <span aria-hidden="true">1</span>
                            <div>
                                <h3 id="tituloOrigenSolicitud">¿A nombre de quién se registra?</h3>
                                <p>Puede ser una solicitud directa del administrador o una captura realizada para un solicitante activo.</p>
                            </div>
                        </div>

                        <div class="scp-grid scp-grid--origen">
                            <label class="scp-campo scp-campo--completo" for="solicitante_opcion">
                                <span>Solicitante *</span>
                                <select
                                    id="solicitante_opcion"
                                    name="solicitante_opcion"
                                    required
                                    disabled
                                >
                                    <option value="">Cargando solicitantes...</option>
                                </select>
                                <small class="scp-ayuda">
                                    Selecciona “Registro directo del administrador” cuando la solicitud sea propia de administración.
                                </small>
                                <small class="scp-error-campo" data-error-for="solicitante_opcion"></small>
                            </label>
                        </div>

                        <div class="scp-identidad" id="identidadSeleccionada" hidden>
                            <span class="scp-identidad__icono" aria-hidden="true">S</span>
                            <div>
                                <small>Solicitud registrada a nombre de</small>
                                <strong id="identidadNombre">—</strong>
                                <span id="identidadDepartamento">—</span>
                            </div>
                        </div>
                    </section>
                <?php else: ?>
                    <input type="hidden" id="solicitante_opcion" name="solicitante_opcion" value="SESION">
                <?php endif; ?>

                <section class="scp-seccion" aria-labelledby="tituloEquipo">
                    <div class="scp-seccion__titulo">
                        <span aria-hidden="true"><?= $numeroEquipo ?></span>
                        <div>
                            <h3 id="tituloEquipo">Identifica el equipo</h3>
                            <p>Busca por el código colocado en el equipo o por una parte de su nombre.</p>
                        </div>
                    </div>

                    <div class="scp-busqueda-equipo">
                        <label class="scp-campo" for="codigo_equipo">
                            <span>Código o nombre del equipo *</span>
                            <div class="scp-input-accion">
                                <input
                                    type="text"
                                    id="codigo_equipo"
                                    name="codigo_equipo"
                                    minlength="2"
                                    maxlength="50"
                                    autocomplete="off"
                                    spellcheck="false"
                                    placeholder="Ejemplo: EQ-015 o Compresor"
                                    required
                                >
                                <button
                                    type="button"
                                    class="scp-btn scp-btn--buscar"
                                    id="btnBuscarEquipo"
                                >
                                    <span class="scp-btn__icono" aria-hidden="true">
                                        <svg><use href="#scp-icon-search"></use></svg>
                                    </span>
                                    <span>Buscar</span>
                                </button>
                            </div>
                            <small class="scp-ayuda">Escribe al menos 2 caracteres y selecciona el equipo correcto.</small>
                            <small class="scp-error-campo" data-error-for="codigo_equipo"></small>
                        </label>
                    </div>

                    <div
                        class="scp-resultados-equipo"
                        id="resultadosEquipo"
                        aria-live="polite"
                        hidden
                    >
                        <div class="scp-resultados-equipo__encabezado">
                            <div>
                                <strong>Resultados encontrados</strong>
                                <span id="textoResultadosEquipo">Selecciona un equipo</span>
                            </div>
                            <button
                                type="button"
                                class="scp-resultados-equipo__cerrar"
                                id="btnCerrarResultadosEquipo"
                                aria-label="Ocultar resultados"
                            >
                                ×
                            </button>
                        </div>
                        <div
                            class="scp-resultados-equipo__lista"
                            id="listaResultadosEquipo"
                        ></div>
                    </div>

                    <div
                        class="scp-equipo"
                        id="equipoSeleccionado"
                        aria-live="polite"
                        hidden
                    >
                        <div class="scp-equipo__marca" aria-hidden="true">E</div>
                        <div class="scp-equipo__contenido">
                            <div class="scp-equipo__encabezado">
                                <div>
                                    <span id="equipoCodigo">—</span>
                                    <strong id="equipoNombre">—</strong>
                                </div>
                                <span class="scp-badge scp-badge--valido">Equipo validado</span>
                            </div>

                            <dl class="scp-equipo__ubicacion">
                                <div><dt>Departamento</dt><dd id="equipoDepartamento">—</dd></div>
                                <div><dt>Área</dt><dd id="equipoArea">—</dd></div>
                                <div><dt>Proceso</dt><dd id="equipoProceso">—</dd></div>
                            </dl>

                            <p id="equipoDescripcion" hidden></p>
                        </div>
                    </div>
                </section>

                <section class="scp-seccion" aria-labelledby="tituloSolicitud">
                    <div class="scp-seccion__titulo">
                        <span aria-hidden="true"><?= $numeroSolicitud ?></span>
                        <div>
                            <h3 id="tituloSolicitud">Explica qué necesitas</h3>
                            <p>Describe el problema con palabras sencillas. El administrador podrá completar o corregir la información.</p>
                        </div>
                    </div>

                    <div class="scp-grid">
                        <label class="scp-campo">
                            <span>Prioridad *</span>
                            <select id="prioridad" name="prioridad" required>
                                <option value="BAJA">Baja</option>
                                <option value="MEDIA" selected>Media</option>
                                <option value="ALTA">Alta</option>
                            </select>
                            <small class="scp-ayuda">No uses este formulario para emergencias.</small>
                            <small class="scp-error-campo" data-error-for="prioridad"></small>
                        </label>

                        <label class="scp-campo">
                            <span>Fecha sugerida <em>opcional</em></span>
                            <input
                                type="date"
                                id="fecha_sugerida"
                                name="fecha_sugerida"
                                min="<?= htmlspecialchars($hoyServidor, ENT_QUOTES, 'UTF-8') ?>"
                            >
                            <small class="scp-ayuda">El administrador decidirá la fecha final.</small>
                            <small class="scp-error-campo" data-error-for="fecha_sugerida"></small>
                        </label>

                        <label class="scp-campo scp-campo--completo">
                            <span>¿Qué problema presenta o qué trabajo necesitas? *</span>
                            <textarea
                                id="descripcion_solicitud"
                                name="descripcion_solicitud"
                                rows="6"
                                minlength="20"
                                maxlength="2500"
                                placeholder="Ejemplo: El motor hace un ruido diferente al arrancar y la banda se mueve con dificultad. Necesita revisión antes de que falle por completo."
                                required
                            ></textarea>
                            <div class="scp-campo__pie">
                                <small>Incluye qué sucede, desde cuándo y en qué momento se presenta.</small>
                                <span id="contadorDescripcion">0/2500</span>
                            </div>
                            <small class="scp-error-campo" data-error-for="descripcion_solicitud"></small>
                        </label>
                    </div>
                </section>

                <details class="scp-opcionales" id="detallesOpcionales">
                    <summary>
                        <span>Información adicional</span>
                        <small>Opcional: llénala solo si la conoces</small>
                    </summary>

                    <div class="scp-opcionales__contenido">
                        <div class="scp-grid">
                            <label class="scp-campo">
                                <span>Tipo de falla <em>opcional</em></span>
                                <select id="tipo_falla_id" name="tipo_falla_id" disabled>
                                    <option value="">No lo sé / dejar en blanco</option>
                                </select>
                                <small class="scp-error-campo" data-error-for="tipo_falla_id"></small>
                            </label>

                            <label class="scp-campo">
                                <span>Causa de la avería <em>opcional</em></span>
                                <select id="causa_averia_id" name="causa_averia_id" disabled>
                                    <option value="">No lo sé / dejar en blanco</option>
                                </select>
                                <small class="scp-error-campo" data-error-for="causa_averia_id"></small>
                            </label>

                            <label class="scp-campo scp-campo--completo">
                                <span>Señales o comportamiento observado <em>opcional</em></span>
                                <textarea
                                    id="descripcion_falla"
                                    name="descripcion_falla"
                                    rows="3"
                                    maxlength="1500"
                                    placeholder="Ejemplo: vibra, pierde presión, se calienta, hace ruido, se apaga..."
                                ></textarea>
                                <small class="scp-error-campo" data-error-for="descripcion_falla"></small>
                            </label>

                            <label class="scp-campo scp-campo--completo">
                                <span>Impacto en la operación <em>opcional</em></span>
                                <textarea
                                    id="impacto_operacion"
                                    name="impacto_operacion"
                                    rows="3"
                                    maxlength="1500"
                                    placeholder="Ejemplo: reduce la producción, retrasa el empaque o afecta la calidad."
                                ></textarea>
                                <small class="scp-error-campo" data-error-for="impacto_operacion"></small>
                            </label>

                            <label class="scp-campo scp-campo--completo">
                                <span>Otras observaciones <em>opcional</em></span>
                                <textarea
                                    id="observaciones_solicitante"
                                    name="observaciones_solicitante"
                                    rows="3"
                                    maxlength="1500"
                                    placeholder="Agrega cualquier dato que pueda ayudar al administrador o al técnico."
                                ></textarea>
                                <small class="scp-error-campo" data-error-for="observaciones_solicitante"></small>
                            </label>
                        </div>
                    </div>
                </details>

                <section class="scp-seccion scp-seccion--seguridad" aria-labelledby="tituloSeguridad">
                    <div class="scp-seccion__titulo">
                        <span aria-hidden="true"><?= $numeroSeguridad ?></span>
                        <div>
                            <h3 id="tituloSeguridad">Datos de seguridad</h3>
                            <p>Marca únicamente lo que puedas identificar. El administrador podrá corregirlo.</p>
                        </div>
                    </div>

                    <div class="scp-checks">
                        <label class="scp-check">
                            <input
                                type="checkbox"
                                id="requiere_paro_equipo"
                                name="requiere_paro_equipo"
                                value="1"
                            >
                            <span>
                                <strong>El equipo debe detenerse para revisarlo</strong>
                                <small>Márcalo si no puede trabajarse de forma segura mientras sigue operando.</small>
                            </span>
                        </label>

                        <label class="scp-check">
                            <input
                                type="checkbox"
                                id="trabajo_peligroso"
                                name="trabajo_peligroso"
                                value="1"
                            >
                            <span>
                                <strong>El trabajo parece peligroso</strong>
                                <small>Altura, electricidad, temperatura, presión, partes móviles u otro riesgo.</small>
                            </span>
                        </label>
                    </div>

                    <label class="scp-campo scp-campo--riesgo" id="campoNivelRiesgo" hidden>
                        <span>Nivel de riesgo observado</span>
                        <select id="nivel_riesgo" name="nivel_riesgo">
                            <option value="MEDIO" selected>Medio</option>
                            <option value="ALTO">Alto</option>
                        </select>
                    </label>

                    <label class="scp-campo scp-campo--completo scp-campo--peligro" id="campoDetallePeligro" hidden>
                        <span>Motivo o precaución del trabajo peligroso *</span>
                        <textarea
                            id="detalle_trabajo_peligroso"
                            name="detalle_trabajo_peligroso"
                            rows="2"
                            minlength="3"
                            maxlength="200"
                            placeholder="Ej. Trabajo en altura, carga pesada o riesgo eléctrico."
                        ></textarea>
                        <small class="scp-error-campo" data-error-for="detalle_trabajo_peligroso"></small>
                    </label>
                </section>

                <div class="scp-form-acciones">
                    <p>
                        Al enviar, la solicitud quedará <strong>PENDIENTE</strong> para que el administrador revise y corrija los datos.
                    </p>

                    <button
                        type="submit"
                        class="scp-btn scp-btn--principal"
                        id="btnRegistrarSolicitud"
                        disabled
                    >
                        <span class="scp-btn__icono" aria-hidden="true">
                            <svg><use href="#scp-icon-clipboard"></use></svg>
                        </span>
                        <span>Registrar solicitud</span>
                    </button>
                </div>
            </form>
        </article>

        <aside class="scp-columna-lateral">
            <section class="scp-panel scp-panel--usuario">
                <span class="scp-panel__etiqueta"><?= $esAdministrador ? 'Registro actual' : 'Solicitante' ?></span>
                <h2 id="nombreSolicitante">Cargando...</h2>
                <p id="departamentoSolicitante">—</p>
            </section>

            <section class="scp-panel scp-panel--guia">
                <span class="scp-panel__etiqueta">Antes de enviar</span>
                <h2>Revisión rápida</h2>

                <div class="scp-checklist">
                    <?php if ($esAdministrador): ?>
                        <div data-check="solicitante">
                            <span>1</span>
                            <p>Solicitante seleccionado</p>
                        </div>
                    <?php endif; ?>
                    <div data-check="equipo">
                        <span><?= $esAdministrador ? '2' : '1' ?></span>
                        <p>Equipo identificado por código</p>
                    </div>
                    <div data-check="descripcion">
                        <span><?= $esAdministrador ? '3' : '2' ?></span>
                        <p>Problema explicado claramente</p>
                    </div>
                    <div data-check="prioridad">
                        <span><?= $esAdministrador ? '4' : '3' ?></span>
                        <p>Prioridad seleccionada</p>
                    </div>
                </div>
            </section>

            <section class="scp-panel scp-panel--recientes">
                <header class="scp-recientes__cabecera">
                    <div>
                        <span class="scp-panel__etiqueta">Historial breve</span>
                        <h2>Solicitudes recientes</h2>
                    </div>
                    <button
                        type="button"
                        id="btnActualizarRecientes"
                        class="scp-btn-icono"
                        title="Actualizar"
                        aria-label="Actualizar solicitudes recientes"
                    >
                        <svg aria-hidden="true"><use href="#scp-icon-refresh"></use></svg>
                    </button>
                </header>

                <div class="scp-cargando" id="cargandoRecientes">
                    <span aria-hidden="true"></span>
                    Cargando...
                </div>

                <div class="scp-vacio" id="vacioRecientes" hidden>
                    <?= $esAdministrador ? 'Todavía no has registrado solicitudes programables.' : 'Todavía no tienes solicitudes programables.' ?>
                </div>

                <div class="scp-recientes-lista" id="listaRecientes" hidden></div>
            </section>
        </aside>
    </section>

    <footer class="scp-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Registro de correctivos programables · Los Chapeteados División Petfood</span>
    </footer>

    <div class="scp-tools-background" aria-hidden="true"></div>
</main>

<?php include __DIR__ . '/../inc/alertas.php'; ?>

<script>
(function () {
    'use strict';

    var ENDPOINT = '../funciones/solicitud_correctivo_programable_funciones.php';
    var estado = {
        cargado: false,
        buscandoEquipo: false,
        enviando: false,
        equipo: null,
        codigoValidado: '',
        formToken: '',
        rol: '',
        usuarioSesion: null,
        solicitantes: [],
        resultadosEquipo: []
    };

    var elementos = {};

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        capturarElementos();
        registrarEventos();
        actualizarContador();
        actualizarChecklist();
        cargarInicial(true);
    }

    function capturarElementos() {
        elementos.form = document.getElementById('formSolicitud');
        elementos.formToken = document.getElementById('form_token');
        elementos.solicitanteOpcion = document.getElementById('solicitante_opcion');
        elementos.identidadSeleccionada = document.getElementById('identidadSeleccionada');
        elementos.identidadNombre = document.getElementById('identidadNombre');
        elementos.identidadDepartamento = document.getElementById('identidadDepartamento');
        elementos.equipoId = document.getElementById('equipo_id');
        elementos.codigoEquipo = document.getElementById('codigo_equipo');
        elementos.btnBuscarEquipo = document.getElementById('btnBuscarEquipo');
        elementos.resultadosEquipo = document.getElementById('resultadosEquipo');
        elementos.textoResultadosEquipo = document.getElementById('textoResultadosEquipo');
        elementos.listaResultadosEquipo = document.getElementById('listaResultadosEquipo');
        elementos.btnCerrarResultadosEquipo = document.getElementById('btnCerrarResultadosEquipo');
        elementos.equipoSeleccionado = document.getElementById('equipoSeleccionado');
        elementos.equipoCodigo = document.getElementById('equipoCodigo');
        elementos.equipoNombre = document.getElementById('equipoNombre');
        elementos.equipoDepartamento = document.getElementById('equipoDepartamento');
        elementos.equipoArea = document.getElementById('equipoArea');
        elementos.equipoProceso = document.getElementById('equipoProceso');
        elementos.equipoDescripcion = document.getElementById('equipoDescripcion');
        elementos.prioridad = document.getElementById('prioridad');
        elementos.fechaSugerida = document.getElementById('fecha_sugerida');
        elementos.descripcion = document.getElementById('descripcion_solicitud');
        elementos.contadorDescripcion = document.getElementById('contadorDescripcion');
        elementos.tipoFalla = document.getElementById('tipo_falla_id');
        elementos.causaAveria = document.getElementById('causa_averia_id');
        elementos.trabajoPeligroso = document.getElementById('trabajo_peligroso');
        elementos.nivelRiesgo = document.getElementById('nivel_riesgo');
        elementos.campoNivelRiesgo = document.getElementById('campoNivelRiesgo');
        elementos.campoDetallePeligro = document.getElementById('campoDetallePeligro');
        elementos.detalleTrabajoPeligroso = document.getElementById('detalle_trabajo_peligroso');
        elementos.btnRegistrar = document.getElementById('btnRegistrarSolicitud');
        elementos.btnNueva = document.getElementById('btnNuevaSolicitud');
        elementos.btnActualizarRecientes = document.getElementById('btnActualizarRecientes');
        elementos.mensaje = document.getElementById('mensajePagina');
        elementos.fechaHoraServidor = document.getElementById('fechaHoraServidor');
        elementos.nombreSolicitante = document.getElementById('nombreSolicitante');
        elementos.departamentoSolicitante = document.getElementById('departamentoSolicitante');
        elementos.listaRecientes = document.getElementById('listaRecientes');
        elementos.cargandoRecientes = document.getElementById('cargandoRecientes');
        elementos.vacioRecientes = document.getElementById('vacioRecientes');
    }

    function registrarEventos() {
        if (elementos.solicitanteOpcion && elementos.solicitanteOpcion.tagName === 'SELECT') {
            elementos.solicitanteOpcion.addEventListener('change', function () {
                limpiarErrorCampo('solicitante_opcion');
                pintarIdentidadActual();
                actualizarChecklist();
                actualizarBotonRegistro();
            });
        }

        elementos.btnBuscarEquipo.addEventListener('click', buscarEquipo);

        elementos.btnCerrarResultadosEquipo.addEventListener('click', ocultarResultadosEquipo);

        elementos.listaResultadosEquipo.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-equipo-id]');

            if (!boton || boton.disabled) {
                return;
            }

            var equipoId = Number(boton.getAttribute('data-equipo-id'));
            var equipo = estado.resultadosEquipo.find(function (item) {
                return Number(item.id) === equipoId;
            });

            if (equipo) {
                seleccionarEquipo(equipo);
            }
        });

        elementos.codigoEquipo.addEventListener('input', function () {
            var termino = normalizarBusqueda(elementos.codigoEquipo.value);

            if (
                estado.equipo
                && normalizarCodigo(termino) !== estado.codigoValidado
            ) {
                limpiarEquipoSeleccionado(false);
            }

            ocultarResultadosEquipo();
            limpiarErrorCampo('codigo_equipo');
            actualizarChecklist();
            actualizarBotonRegistro();
        });

        elementos.codigoEquipo.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                buscarEquipo();
            }
        });

        elementos.descripcion.addEventListener('input', function () {
            limpiarErrorCampo('descripcion_solicitud');
            actualizarContador();
            actualizarChecklist();
            actualizarBotonRegistro();
        });

        elementos.prioridad.addEventListener('change', function () {
            limpiarErrorCampo('prioridad');
            actualizarChecklist();
            actualizarBotonRegistro();
        });

        elementos.fechaSugerida.addEventListener('change', function () {
            limpiarErrorCampo('fecha_sugerida');
        });

        elementos.trabajoPeligroso.addEventListener('change', function () {
            var activo = elementos.trabajoPeligroso.checked;
            elementos.campoNivelRiesgo.hidden = !activo;
            elementos.campoDetallePeligro.hidden = !activo;
            elementos.detalleTrabajoPeligroso.required = activo;
            elementos.nivelRiesgo.value = activo ? 'MEDIO' : 'BAJO';
            if (!activo) elementos.detalleTrabajoPeligroso.value = '';
        });

        elementos.form.addEventListener('submit', enviarFormulario);
        elementos.btnNueva.addEventListener('click', confirmarLimpiar);
        elementos.btnActualizarRecientes.addEventListener('click', function () {
            cargarInicial(false);
        });

        elementos.form.querySelectorAll('input, select, textarea').forEach(function (campo) {
            campo.addEventListener('change', function () {
                limpiarErrorCampo(campo.name || campo.id);
            });
        });
    }

    async function cargarInicial(mostrarCargaCompleta) {
        ocultarMensaje();
        mostrarEstadoRecientes('cargando');

        if (mostrarCargaCompleta) {
            elementos.btnRegistrar.disabled = true;
        }

        try {
            var respuesta = await SistemaUI.peticionJson(ENDPOINT + '?accion=inicial');

            estado.cargado = true;
            estado.formToken = respuesta.form_token || '';
            estado.rol = respuesta.rol || '';
            estado.usuarioSesion = respuesta.usuario_sesion || respuesta.solicitante || {};
            estado.solicitantes = Array.isArray(respuesta.solicitantes) ? respuesta.solicitantes : [];
            elementos.formToken.value = estado.formToken;

            prepararOrigenSolicitud();
            pintarCatalogos(respuesta.catalogos || {});
            pintarResumen(respuesta.resumen || {});
            pintarRecientes(respuesta.recientes || []);
            elementos.fechaHoraServidor.textContent = respuesta.fecha_hora_servidor || 'Sin fecha';

            if (respuesta.fecha_servidor) {
                elementos.fechaSugerida.min = respuesta.fecha_servidor;
            }

            actualizarBotonRegistro();
        } catch (error) {
            estado.cargado = false;
            mostrarEstadoRecientes('error');
            mostrarMensaje(
                error.message || 'No fue posible cargar el formulario.',
                'error'
            );
        }
    }

    async function buscarEquipo() {
        if (estado.buscandoEquipo) {
            return;
        }

        var termino = normalizarBusqueda(elementos.codigoEquipo.value);
        elementos.codigoEquipo.value = termino;
        limpiarErrorCampo('codigo_equipo');

        if (termino.length < 2) {
            marcarErrorCampo(
                'codigo_equipo',
                'Escribe al menos 2 caracteres del código o nombre.'
            );
            elementos.codigoEquipo.focus();
            return;
        }

        estado.buscandoEquipo = true;
        SistemaUI.estadoBoton(elementos.btnBuscarEquipo, true, 'Buscando...');
        limpiarEquipoSeleccionado(false);
        ocultarResultadosEquipo();

        try {
            var respuesta = await SistemaUI.peticionJson(
                ENDPOINT + '?accion=buscar_equipo&termino=' + encodeURIComponent(termino)
            );

            estado.resultadosEquipo = Array.isArray(respuesta.equipos)
                ? respuesta.equipos
                : [];

            if (estado.resultadosEquipo.length === 0) {
                throw new Error('No se recibieron equipos para seleccionar.');
            }

            if (
                respuesta.seleccion_automatica === true
                && Number(estado.resultadosEquipo[0].seleccionable) === 1
            ) {
                seleccionarEquipo(estado.resultadosEquipo[0]);
                return;
            }

            pintarResultadosEquipo(estado.resultadosEquipo);
        } catch (error) {
            limpiarEquipoSeleccionado(false);

            if (
                error.datos
                && Array.isArray(error.datos.equipos)
                && error.datos.equipos.length > 0
            ) {
                estado.resultadosEquipo = error.datos.equipos;
                pintarResultadosEquipo(estado.resultadosEquipo);
            }

            marcarErrorCampo(
                (error.datos && error.datos.campo) || 'codigo_equipo',
                error.message || 'No fue posible encontrar el equipo.'
            );
        } finally {
            estado.buscandoEquipo = false;
            SistemaUI.estadoBoton(elementos.btnBuscarEquipo, false);
        }
    }

    function pintarResultadosEquipo(equipos) {
        elementos.listaResultadosEquipo.innerHTML = equipos.map(function (equipo) {
            var seleccionable = Number(equipo.seleccionable) === 1;
            var ubicacion = [
                equipo.departamento || 'Sin departamento',
                equipo.area || 'Sin área',
                equipo.proceso || 'Sin proceso'
            ].join(' · ');

            return '' +
                '<article class="scp-resultado-equipo' +
                    (seleccionable ? '' : ' scp-resultado-equipo--invalido') +
                '">' +
                    '<div class="scp-resultado-equipo__datos">' +
                        '<span>' + escapar(equipo.codigo_equipo || 'Sin código') + '</span>' +
                        '<strong>' + escapar(equipo.nombre_equipo || 'Sin nombre') + '</strong>' +
                        '<small>' + escapar(ubicacion) + '</small>' +
                    '</div>' +
                    '<button type="button" class="scp-btn scp-btn--seleccionar" ' +
                        'data-equipo-id="' + escaparAtributo(equipo.id || '') + '" ' +
                        (seleccionable ? '' : 'disabled') +
                    '>' +
                        (seleccionable ? 'Seleccionar' : 'Ubicación incompleta') +
                    '</button>' +
                '</article>';
        }).join('');

        elementos.textoResultadosEquipo.textContent = equipos.length === 1
            ? '1 coincidencia'
            : equipos.length + ' coincidencias';
        elementos.resultadosEquipo.hidden = false;
    }

    function seleccionarEquipo(equipo) {
        if (!equipo || Number(equipo.seleccionable) !== 1) {
            return;
        }

        estado.equipo = equipo;
        estado.codigoValidado = normalizarCodigo(equipo.codigo_equipo || '');
        elementos.codigoEquipo.value = estado.codigoValidado;
        elementos.equipoId.value = equipo.id || '';

        ocultarResultadosEquipo();
        limpiarErrorCampo('codigo_equipo');
        pintarEquipo(equipo);
        actualizarChecklist();
        actualizarBotonRegistro();
    }

    function ocultarResultadosEquipo() {
        estado.resultadosEquipo = [];
        elementos.listaResultadosEquipo.innerHTML = '';
        elementos.resultadosEquipo.hidden = true;
    }

    async function enviarFormulario(evento) {
        evento.preventDefault();

        if (estado.enviando) {
            return;
        }

        limpiarErrores();
        ocultarMensaje();

        var errorLocal = validarFormulario();

        if (errorLocal) {
            marcarErrorCampo(errorLocal.campo, errorLocal.mensaje);
            enfocarCampo(errorLocal.campo);
            return;
        }

        var confirmar = await SistemaUI.confirmar({
            titulo: '¿Registrar solicitud?',
            texto: estado.rol === 'ADMIN'
                ? 'La solicitud se registrará con estado PENDIENTE y quedará lista para revisión administrativa.'
                : 'Se enviará al administrador con estado PENDIENTE para su revisión.',
            textoConfirmar: 'Sí, registrar',
            icono: 'question'
        });

        if (!confirmar) {
            return;
        }

        estado.enviando = true;
        SistemaUI.estadoBoton(elementos.btnRegistrar, true, 'Registrando...');

        try {
            var datos = new FormData(elementos.form);
            datos.set('accion', 'crear');
            datos.set('form_token', estado.formToken);
            datos.set('codigo_equipo', normalizarCodigo(elementos.codigoEquipo.value));
            datos.set('equipo_id', elementos.equipoId.value);
            datos.set('trabajo_peligroso', elementos.trabajoPeligroso.checked ? '1' : '0');
            datos.set(
                'requiere_paro_equipo',
                document.getElementById('requiere_paro_equipo').checked ? '1' : '0'
            );
            datos.set(
                'nivel_riesgo',
                elementos.trabajoPeligroso.checked ? elementos.nivelRiesgo.value : 'BAJO'
            );

            var respuesta = await SistemaUI.peticionJson(ENDPOINT, {
                method: 'POST',
                body: datos
            });

            estado.formToken = respuesta.form_token || '';
            elementos.formToken.value = estado.formToken;
            pintarResumen(respuesta.resumen || {});
            pintarRecientes(respuesta.recientes || []);

            await Swal.fire({
                icon: 'success',
                title: 'Solicitud registrada',
                html:
                    '<p class="scp-swal-texto">' +
                        escapar(estado.rol === 'ADMIN'
                            ? 'Solicitud registrada para ' + (respuesta.registrada_para || 'el solicitante seleccionado') + '.'
                            : 'Tu solicitud quedó pendiente de revisión.') +
                    '</p>' +
                    '<strong class="scp-swal-folio">' + escapar(respuesta.folio || '') + '</strong>',
                confirmButtonText: 'Aceptar',
                allowOutsideClick: false,
                heightAuto: false
            });

            limpiarFormulario(false);
        } catch (error) {
            if (error.datos && error.datos.form_token) {
                estado.formToken = error.datos.form_token;
                elementos.formToken.value = estado.formToken;
            }

            if (error.datos && error.datos.campo) {
                marcarErrorCampo(error.datos.campo, error.message);
                enfocarCampo(error.datos.campo);
            } else {
                mostrarMensaje(
                    error.message || 'No fue posible registrar la solicitud.',
                    'error'
                );
            }

            if (elementos.trabajoPeligroso.checked) {
            var detallePeligro = elementos.detalleTrabajoPeligroso.value.trim();
            if (detallePeligro.length < 3 || detallePeligro.length > 200) {
                return {
                    campo: 'detalle_trabajo_peligroso',
                    mensaje: 'Describe brevemente el peligro (3 a 200 caracteres).'
                };
            }
        }

        if (!estado.formToken) {
                cargarInicial(false);
            }
        } finally {
            estado.enviando = false;
            SistemaUI.estadoBoton(elementos.btnRegistrar, false);
            actualizarBotonRegistro();
        }
    }

    function validarFormulario() {
        if (
            estado.rol === 'ADMIN'
            && (!elementos.solicitanteOpcion || !elementos.solicitanteOpcion.value)
        ) {
            return {
                campo: 'solicitante_opcion',
                mensaje: 'Selecciona a nombre de quién se registrará la solicitud.'
            };
        }

        if (!estado.equipo || !elementos.equipoId.value) {
            return {
                campo: 'codigo_equipo',
                mensaje: 'Busca por código o nombre y selecciona el equipo antes de enviar.'
            };
        }

        if (normalizarCodigo(elementos.codigoEquipo.value) !== estado.codigoValidado) {
            return {
                campo: 'codigo_equipo',
                mensaje: 'La búsqueda cambió. Vuelve a seleccionar el equipo.'
            };
        }

        if (!['BAJA', 'MEDIA', 'ALTA'].includes(elementos.prioridad.value)) {
            return {
                campo: 'prioridad',
                mensaje: 'Selecciona una prioridad válida.'
            };
        }

        var descripcion = elementos.descripcion.value.trim();

        if (descripcion.length < 20) {
            return {
                campo: 'descripcion_solicitud',
                mensaje: 'Explica el problema usando al menos 20 caracteres.'
            };
        }

        if (!estado.formToken) {
            return {
                campo: '',
                mensaje: 'El formulario todavía no está listo. Actualiza la página.'
            };
        }

        return null;
    }

    async function confirmarLimpiar() {
        var tieneDatos =
            elementos.codigoEquipo.value.trim() !== '' ||
            elementos.descripcion.value.trim() !== '' ||
            elementos.fechaSugerida.value !== '' ||
            elementos.tipoFalla.value !== '' ||
            elementos.causaAveria.value !== '';

        if (tieneDatos) {
            var confirmar = await SistemaUI.confirmar({
                titulo: '¿Limpiar formulario?',
                texto: 'Se borrarán los datos que todavía no has enviado.',
                textoConfirmar: 'Sí, limpiar',
                peligro: true
            });

            if (!confirmar) {
                return;
            }
        }

        limpiarFormulario(true);
    }

    function limpiarFormulario(enfocarCodigo) {
        elementos.form.reset();
        elementos.prioridad.value = 'MEDIA';
        elementos.formToken.value = estado.formToken;
        elementos.campoNivelRiesgo.hidden = true;
        elementos.campoDetallePeligro.hidden = true;
        elementos.detalleTrabajoPeligroso.required = false;
        elementos.detalleTrabajoPeligroso.value = '';
        elementos.nivelRiesgo.value = 'BAJO';

        if (estado.rol === 'ADMIN' && elementos.solicitanteOpcion) {
            elementos.solicitanteOpcion.value = 'ADMIN';
            pintarIdentidadActual();
        }

        limpiarEquipoSeleccionado();
        limpiarErrores();
        ocultarMensaje();
        actualizarContador();
        actualizarChecklist();
        actualizarBotonRegistro();

        if (enfocarCodigo) {
            elementos.codigoEquipo.focus();
        }
    }

    function pintarEquipo(equipo) {
        elementos.equipoCodigo.textContent = equipo.codigo_equipo || 'Sin código';
        elementos.equipoNombre.textContent = equipo.nombre_equipo || 'Sin nombre';
        elementos.equipoDepartamento.textContent = equipo.departamento || 'Sin departamento';
        elementos.equipoArea.textContent = equipo.area || 'Sin área';
        elementos.equipoProceso.textContent = equipo.proceso || 'Sin proceso';

        var descripcion = String(equipo.descripcion || '').trim();
        elementos.equipoDescripcion.textContent = descripcion;
        elementos.equipoDescripcion.hidden = descripcion === '';
        elementos.equipoSeleccionado.hidden = false;
    }

    function limpiarEquipoSeleccionado(limpiarBusqueda) {
        estado.equipo = null;
        estado.codigoValidado = '';
        elementos.equipoId.value = '';
        elementos.equipoSeleccionado.hidden = true;
        ocultarResultadosEquipo();

        if (limpiarBusqueda === true) {
            elementos.codigoEquipo.value = '';
        }
    }

    function prepararOrigenSolicitud() {
        if (estado.rol === 'ADMIN') {
            llenarSelectorSolicitantes();
            pintarIdentidadActual();
            return;
        }

        pintarSolicitante(estado.usuarioSesion || {});
    }

    function llenarSelectorSolicitantes() {
        if (!elementos.solicitanteOpcion || elementos.solicitanteOpcion.tagName !== 'SELECT') {
            return;
        }

        elementos.solicitanteOpcion.innerHTML = '';

        var opcionAdministrador = document.createElement('option');
        opcionAdministrador.value = 'ADMIN';
        opcionAdministrador.textContent =
            'Registro directo del administrador — ' +
            (estado.usuarioSesion.nombre_completo || 'Administrador');
        elementos.solicitanteOpcion.appendChild(opcionAdministrador);

        if (estado.solicitantes.length > 0) {
            var grupo = document.createElement('optgroup');
            grupo.label = 'Solicitantes activos';

            estado.solicitantes.forEach(function (solicitante) {
                var opcion = document.createElement('option');
                opcion.value = 'SOLICITANTE:' + String(solicitante.id || '');
                opcion.textContent =
                    (solicitante.nombre_completo || solicitante.usuario || 'Solicitante') +
                    ' — ' +
                    (solicitante.departamento || 'Sin departamento');
                grupo.appendChild(opcion);
            });

            elementos.solicitanteOpcion.appendChild(grupo);
        }

        elementos.solicitanteOpcion.disabled = false;
        elementos.solicitanteOpcion.value = 'ADMIN';
    }

    function pintarIdentidadActual() {
        if (estado.rol !== 'ADMIN') {
            pintarSolicitante(estado.usuarioSesion || {});
            return;
        }

        var valor = elementos.solicitanteOpcion
            ? elementos.solicitanteOpcion.value
            : '';
        var persona = null;
        var departamento = '';

        if (valor === 'ADMIN') {
            persona = estado.usuarioSesion || {};
            departamento = 'Registro directo del administrador';
        } else if (valor.indexOf('SOLICITANTE:') === 0) {
            var id = Number(valor.split(':')[1] || 0);
            persona = estado.solicitantes.find(function (solicitante) {
                return Number(solicitante.id || 0) === id;
            }) || null;
            departamento = persona ? (persona.departamento || 'Sin departamento') : '';
        }

        if (!persona) {
            elementos.nombreSolicitante.textContent = 'Selecciona un solicitante';
            elementos.departamentoSolicitante.textContent = '—';

            if (elementos.identidadSeleccionada) {
                elementos.identidadSeleccionada.hidden = true;
            }
            return;
        }

        pintarSolicitante({
            nombre_completo: persona.nombre_completo || 'Sin nombre',
            departamento: departamento
        });

        if (elementos.identidadSeleccionada) {
            elementos.identidadNombre.textContent = persona.nombre_completo || 'Sin nombre';
            elementos.identidadDepartamento.textContent = departamento;
            elementos.identidadSeleccionada.hidden = false;
        }
    }

    function pintarSolicitante(solicitante) {
        elementos.nombreSolicitante.textContent = solicitante.nombre_completo || 'Solicitante';
        elementos.departamentoSolicitante.textContent = solicitante.departamento || 'Sin departamento asignado';
    }

    function pintarCatalogos(catalogos) {
        llenarSelect(
            elementos.tipoFalla,
            catalogos.tipos_falla || [],
            'No lo sé / dejar en blanco'
        );

        llenarSelect(
            elementos.causaAveria,
            catalogos.causas_averia || [],
            'No lo sé / dejar en blanco'
        );
    }

    function llenarSelect(select, lista, textoVacio) {
        select.innerHTML = '';

        var opcionVacia = document.createElement('option');
        opcionVacia.value = '';
        opcionVacia.textContent = textoVacio;
        select.appendChild(opcionVacia);

        lista.forEach(function (item) {
            var opcion = document.createElement('option');
            opcion.value = String(item.id || '');
            opcion.textContent = item.nombre || 'Sin nombre';
            select.appendChild(opcion);
        });

        select.disabled = false;
    }

    function pintarResumen(resumen) {
        document.getElementById('resumenTotal').textContent = numero(resumen.total);
        document.getElementById('resumenPendientes').textContent = numero(resumen.pendientes);
        document.getElementById('resumenAutorizadas').textContent = numero(resumen.autorizadas);
        document.getElementById('resumenSeguimiento').textContent = numero(resumen.seguimiento);
        document.getElementById('resumenTerminadas').textContent = numero(resumen.terminadas);
    }

    function pintarRecientes(lista) {
        elementos.listaRecientes.innerHTML = '';

        if (!Array.isArray(lista) || lista.length === 0) {
            mostrarEstadoRecientes('vacio');
            return;
        }

        lista.forEach(function (solicitud) {
            var articulo = document.createElement('article');
            articulo.className = 'scp-reciente';
            articulo.innerHTML =
                '<div class="scp-reciente__cabecera">' +
                    '<div>' +
                        '<strong>' + escapar(solicitud.folio || 'Sin folio') + '</strong>' +
                        '<span>' + escapar(solicitud.fecha_registro_formato || '') + '</span>' +
                    '</div>' +
                    badgeEstado(solicitud.estado) +
                '</div>' +
                (estado.rol === 'ADMIN'
                    ? '<small class="scp-reciente__solicitante">Para: ' + escapar(solicitud.nombre_solicitante || 'Sin solicitante') + '</small>'
                    : '') +
                '<p>' + escapar(recortar(solicitud.descripcion_solicitud || '', 115)) + '</p>' +
                '<div class="scp-reciente__pie">' +
                    '<span>' + escapar((solicitud.codigo_equipo || 'Sin código') + ' · ' + (solicitud.nombre_equipo || 'Sin equipo')) + '</span>' +
                    '<strong>' + escapar(textoPrioridad(solicitud.prioridad)) + '</strong>' +
                '</div>';

            elementos.listaRecientes.appendChild(articulo);
        });

        mostrarEstadoRecientes('lista');
    }

    function mostrarEstadoRecientes(tipo) {
        elementos.cargandoRecientes.hidden = tipo !== 'cargando';
        elementos.vacioRecientes.hidden = tipo !== 'vacio';
        elementos.listaRecientes.hidden = tipo !== 'lista';

        if (tipo === 'error') {
            elementos.vacioRecientes.hidden = false;
            elementos.vacioRecientes.textContent = 'No fue posible cargar las solicitudes recientes.';
        } else {
            elementos.vacioRecientes.textContent = estado.rol === 'ADMIN'
                ? 'Todavía no has registrado solicitudes programables.'
                : 'Todavía no tienes solicitudes programables.';
        }
    }

    function actualizarChecklist() {
        if (estado.rol === 'ADMIN') {
            cambiarCheck(
                'solicitante',
                Boolean(elementos.solicitanteOpcion && elementos.solicitanteOpcion.value)
            );
        }

        cambiarCheck('equipo', Boolean(estado.equipo && elementos.equipoId.value));
        cambiarCheck('descripcion', elementos.descripcion.value.trim().length >= 20);
        cambiarCheck('prioridad', ['BAJA', 'MEDIA', 'ALTA'].includes(elementos.prioridad.value));
    }

    function cambiarCheck(clave, completo) {
        var elemento = document.querySelector('[data-check="' + clave + '"]');

        if (elemento) {
            elemento.classList.toggle('is-complete', completo);
        }
    }

    function actualizarBotonRegistro() {
        elementos.btnRegistrar.disabled = !(
            estado.cargado &&
            !estado.enviando &&
            (
                estado.rol !== 'ADMIN' ||
                (elementos.solicitanteOpcion && elementos.solicitanteOpcion.value)
            ) &&
            estado.equipo &&
            elementos.equipoId.value &&
            elementos.descripcion.value.trim().length >= 20 &&
            estado.formToken
        );
    }

    function actualizarContador() {
        elementos.contadorDescripcion.textContent =
            elementos.descripcion.value.length + '/2500';
    }

    function mostrarMensaje(texto, tipo) {
        elementos.mensaje.textContent = texto;
        elementos.mensaje.className = 'scp-mensaje scp-mensaje--' + (tipo || 'info');
        elementos.mensaje.hidden = false;
        elementos.mensaje.scrollIntoView({behavior: 'smooth', block: 'center'});
    }

    function ocultarMensaje() {
        elementos.mensaje.hidden = true;
        elementos.mensaje.textContent = '';
        elementos.mensaje.className = 'scp-mensaje';
    }

    function marcarErrorCampo(campo, mensaje) {
        if (!campo) {
            mostrarMensaje(mensaje, 'error');
            return;
        }

        var error = document.querySelector('[data-error-for="' + campo + '"]');
        var control = document.getElementById(campo);

        if (error) {
            error.textContent = mensaje;
        }

        if (control) {
            control.classList.add('is-invalid');
            control.setAttribute('aria-invalid', 'true');
        }
    }

    function limpiarErrorCampo(campo) {
        if (!campo) {
            return;
        }

        var error = document.querySelector('[data-error-for="' + campo + '"]');
        var control = document.getElementById(campo);

        if (error) {
            error.textContent = '';
        }

        if (control) {
            control.classList.remove('is-invalid');
            control.removeAttribute('aria-invalid');
        }
    }

    function limpiarErrores() {
        document.querySelectorAll('.scp-error-campo').forEach(function (elemento) {
            elemento.textContent = '';
        });

        elementos.form.querySelectorAll('.is-invalid').forEach(function (elemento) {
            elemento.classList.remove('is-invalid');
            elemento.removeAttribute('aria-invalid');
        });
    }

    function enfocarCampo(campo) {
        var control = document.getElementById(campo);

        if (control) {
            control.focus();
            control.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    }

    function normalizarBusqueda(valor) {
        return String(valor || '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function normalizarCodigo(valor) {
        return String(valor || '')
            .trim()
            .replace(/\s+/g, '')
            .toUpperCase();
    }

    function numero(valor) {
        var convertido = Number(valor || 0);
        return Number.isFinite(convertido) ? String(convertido) : '0';
    }

    function textoPrioridad(prioridad) {
        return {
            BAJA: 'Baja',
            MEDIA: 'Media',
            ALTA: 'Alta'
        }[prioridad] || prioridad || 'Sin prioridad';
    }

    function badgeEstado(estadoSolicitud) {
        var textos = {
            PENDIENTE: 'Pendiente',
            APROBADO: 'Aprobado',
            AGENDADO: 'Agendado',
            EN_PROCESO: 'En proceso',
            PAUSADO: 'Pausado',
            ATRASADO: 'Atrasado',
            TERMINADO: 'Terminado',
            RECHAZADO: 'Rechazado',
            CANCELADO: 'Cancelado'
        };

        var clase = String(estadoSolicitud || 'PENDIENTE').toLowerCase();

        return '<span class="scp-badge scp-badge--' + escapar(clase) + '">' +
            escapar(textos[estadoSolicitud] || estadoSolicitud || 'Pendiente') +
        '</span>';
    }

    function recortar(texto, limite) {
        var limpio = String(texto || '').trim();
        return limpio.length > limite ? limpio.slice(0, limite - 1) + '…' : limpio;
    }

    function escaparAtributo(valor) {
        return escapar(valor).replace(/`/g, '&#96;');
    }

    function escapar(valor) {
        return String(valor == null ? '' : valor).replace(/[&<>'"]/g, function (caracter) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#039;',
                '"': '&quot;'
            }[caracter];
        });
    }
}());
</script>

</body>
</html>