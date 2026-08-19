<?php

declare(strict_types=1);

require_once __DIR__ . '/../inc/seguridad.php';

sm_requerir_sesion(['ADMIN', 'SOLICITANTE'], false);

$rolSesion = strtoupper((string) ($_SESSION['tipo_usuario'] ?? ''));
$esAdministrador = $rolSesion === 'ADMIN';
$numeroEquipo = $esAdministrador ? '2' : '1';
$numeroFalla = $esAdministrador ? '3' : '2';
$numeroSeguridad = $esAdministrador ? '4' : '3';

$cssSolicitud = __DIR__ . '/../css/style_solicitud_correctivo_urgente.css';
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
    <meta name="theme-color" content="#071d33">
    <meta
        name="description"
        content="Registro profesional de correctivos urgentes del Sistema de Mantenimiento"
    >

    <title>Correctivo urgente | Sistema de Mantenimiento</title>

    <link
        rel="stylesheet"
        href="../css/style_solicitud_correctivo_urgente.css?v=<?= htmlspecialchars($versionCss, ENT_QUOTES, 'UTF-8') ?>"
    >
</head>
<body>

<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="scp-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="scu-icon-alert" viewBox="0 0 24 24">
        <path d="M12 3 2.8 19h18.4L12 3Z"/>
        <path d="M12 9v4M12 17h.01"/>
    </symbol>
    <symbol id="scu-icon-siren" viewBox="0 0 24 24">
        <path d="M7 16V9a5 5 0 0 1 10 0v7"/>
        <path d="M5 16h14v4H5zM12 2V0M4.5 4.5 3 3M19.5 4.5 21 3M2 10H0M24 10h-2"/>
    </symbol>
    <symbol id="scu-icon-reset" viewBox="0 0 24 24">
        <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>
        <path d="M3 3v5h5"/>
    </symbol>
    <symbol id="scu-icon-radio" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="2"/>
        <path d="M8.5 8.5a5 5 0 0 0 0 7M15.5 8.5a5 5 0 0 1 0 7M5 5a10 10 0 0 0 0 14M19 5a10 10 0 0 1 0 14"/>
    </symbol>
    <symbol id="scu-icon-clipboard" viewBox="0 0 24 24">
        <rect x="5" y="4" width="14" height="17" rx="2"/>
        <path d="M9 4V2h6v2M9 9h6M9 13h6M9 17h4"/>
    </symbol>
    <symbol id="scu-icon-eye" viewBox="0 0 24 24">
        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/>
        <circle cx="12" cy="12" r="3"/>
    </symbol>
    <symbol id="scu-icon-send" viewBox="0 0 24 24">
        <path d="m22 2-7 20-4-9-9-4 20-7Z"/>
        <path d="M22 2 11 13"/>
    </symbol>
    <symbol id="scu-icon-activity" viewBox="0 0 24 24">
        <path d="M3 12h4l2-7 4 14 2-7h6"/>
    </symbol>
    <symbol id="scu-icon-check" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8 12 2.6 2.6L16.5 9"/>
    </symbol>
    <symbol id="scu-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="scu-icon-shield" viewBox="0 0 24 24">
        <path d="M12 3 5 6v5c0 4.5 2.8 8.1 7 10 4.2-1.9 7-5.5 7-10V6l-7-3Z"/>
        <path d="M12 8v4M12 16h.01"/>
    </symbol>
    <symbol id="scu-icon-user" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="4"/>
        <path d="M4 21a8 8 0 0 1 16 0"/>
    </symbol>
    <symbol id="scu-icon-tools" viewBox="0 0 24 24">
        <path d="M14.7 6.3a4 4 0 0 0-5-5L7.4 3.6l3 3 2.3-2.3a4 4 0 0 0 2 2Z"/>
        <path d="m10.3 6.7-8.6 8.6a2.1 2.1 0 0 0 3 3l8.6-8.6"/>
        <path d="m14 14 6 6"/>
    </symbol>
    <symbol id="scu-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="scu-icon-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </symbol>
    <symbol id="scu-icon-pause" viewBox="0 0 24 24">
        <rect x="6" y="5" width="4" height="14" rx="1"/>
        <rect x="14" y="5" width="4" height="14" rx="1"/>
    </symbol>
</svg>

<main class="contenido-principal scp-page scp-page--urgente">
    <div class="scp-ambient scp-ambient--one" aria-hidden="true"></div>
    <div class="scp-ambient scp-ambient--two" aria-hidden="true"></div>

    <section class="scp-encabezado scu-encabezado" aria-labelledby="tituloCorrectivoUrgente">
        <div class="scp-encabezado__pattern" aria-hidden="true"></div>

        <div class="scp-encabezado__contenido">
            <div class="scp-encabezado__copy">
                <span class="scp-kicker scu-kicker">
                    <span class="scp-kicker__icono" aria-hidden="true">
                        <svg><use href="#scu-icon-siren"></use></svg>
                    </span>
                    Atención inmediata
                </span>

                <h1 id="tituloCorrectivoUrgente">Correctivo urgente</h1>

                <p>
                    <?= $esAdministrador
                        ? 'Registra una urgencia directa o a nombre de un solicitante. Se publicará inmediatamente para que los técnicos puedan aceptarla.'
                        : 'Reporta una falla que requiere atención inmediata. La urgencia se publicará para todos los técnicos activos.'
                    ?>
                </p>

                <div class="scp-encabezado__meta">
                    <span>
                        <i class="scp-live-dot scu-live-dot" aria-hidden="true"></i>
                        Canal urgente activo
                    </span>
                    <span><?= $esAdministrador ? 'Captura administrativa' : 'Portal del solicitante' ?></span>
                </div>
            </div>

            <div class="scp-encabezado__acciones">
                <div class="scp-hero-mini-card scu-hero-mini-card" aria-hidden="true">
                    <span class="scp-hero-mini-card__icono">
                        <svg><use href="#scu-icon-radio"></use></svg>
                    </span>
                    <div>
                        <small>Publicación</small>
                        <strong>Inmediata a técnicos</strong>
                    </div>
                </div>

                <button
                    type="button"
                    class="scp-btn scp-btn--secundario scp-btn--hero"
                    id="btnNuevaSolicitud"
                >
                    <span class="scp-btn__icono" aria-hidden="true">
                        <svg><use href="#scu-icon-reset"></use></svg>
                    </span>
                    <span>Limpiar formulario</span>
                </button>
            </div>
        </div>
    </section>

    <section class="scu-alerta-principal" aria-label="Funcionamiento de la urgencia">
        <span class="scu-alerta-principal__icono" aria-hidden="true">
            <svg><use href="#scu-icon-alert"></use></svg>
        </span>
        <div class="scu-alerta-principal__contenido">
            <span class="scu-alerta-principal__etiqueta">Flujo automático de respuesta</span>
            <strong>La urgencia no espera una asignación manual.</strong>
            <p>
                Al registrarla se notificará a todos los técnicos activos. Podrán aceptarla directamente hasta completar el límite configurado. También quedará visible para revisión administrativa sin detener su atención.
            </p>
        </div>
        <span class="scu-alerta-principal__estado" aria-label="Publicación automática">
            <i aria-hidden="true"></i>
            Publicación automática
        </span>
    </section>

    <section class="scp-resumen" aria-label="Resumen de solicitudes urgentes">
        <article class="scp-resumen__card scu-resumen--urgente">
            <span class="scp-resumen__icono" aria-hidden="true">
                <svg><use href="#scu-icon-siren"></use></svg>
            </span>
            <div>
                <span>Urgencias registradas</span>
                <strong id="resumenTotal">0</strong>
                <small>Histórico disponible</small>
            </div>
        </article>

        <article class="scp-resumen__card scp-resumen--pendiente">
            <span class="scp-resumen__icono" aria-hidden="true">
                <svg><use href="#scu-icon-eye"></use></svg>
            </span>
            <div>
                <span>Sin revisión administrativa</span>
                <strong id="resumenSinRevisar">0</strong>
                <small>Control pendiente</small>
            </div>
        </article>

        <article class="scp-resumen__card scu-resumen--publicadas">
            <span class="scp-resumen__icono" aria-hidden="true">
                <svg><use href="#scu-icon-radio"></use></svg>
            </span>
            <div>
                <span>Publicadas o en espera</span>
                <strong id="resumenPublicadas">0</strong>
                <small>Disponibles para aceptar</small>
            </div>
        </article>

        <article class="scp-resumen__card scu-resumen--atencion">
            <span class="scp-resumen__icono" aria-hidden="true">
                <svg><use href="#scu-icon-activity"></use></svg>
            </span>
            <div>
                <span>En atención o pausadas</span>
                <strong id="resumenAtencion">0</strong>
                <small>Respuesta en curso</small>
            </div>
        </article>

        <article class="scp-resumen__card scp-resumen--terminada">
            <span class="scp-resumen__icono" aria-hidden="true">
                <svg><use href="#scu-icon-check"></use></svg>
            </span>
            <div>
                <span>Cerradas</span>
                <strong id="resumenCerradas">0</strong>
                <small>Urgencias concluidas</small>
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
            <header class="scp-panel__encabezado scu-panel__encabezado">
                <div>
                    <span class="scp-panel__etiqueta scu-panel__etiqueta">Formulario urgente</span>
                    <h2>Información necesaria para responder</h2>
                    <p>Todos los campos operativos son obligatorios. Las observaciones finales son opcionales.</p>
                </div>

                <div class="scp-fecha-servidor scu-fecha-servidor">
                    <span>Fecha y hora del servidor</span>
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
                                <p>Puede ser una urgencia directa del administrador o una captura para un solicitante activo.</p>
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
                                    Selecciona “Registro directo del administrador” cuando la urgencia sea reportada directamente por administración.
                                </small>
                                <small class="scp-error-campo" data-error-for="solicitante_opcion"></small>
                            </label>
                        </div>

                        <div class="scp-identidad" id="identidadSeleccionada" hidden>
                            <span class="scp-identidad__icono" aria-hidden="true">S</span>
                            <div>
                                <small>Urgencia registrada a nombre de</small>
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
                            <h3 id="tituloEquipo">Identifica el equipo afectado</h3>
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
                                    maxlength="100"
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
                                        <svg><use href="#scu-icon-search"></use></svg>
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
                        <div class="scp-resultados-equipo__lista" id="listaResultadosEquipo"></div>
                    </div>

                    <div
                        class="scp-equipo"
                        id="equipoSeleccionado"
                        aria-live="polite"
                        hidden
                    >
                        <div class="scp-equipo__marca scu-equipo__marca" aria-hidden="true">!</div>
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

                <section class="scp-seccion" aria-labelledby="tituloFalla">
                    <div class="scp-seccion__titulo">
                        <span aria-hidden="true"><?= $numeroFalla ?></span>
                        <div>
                            <h3 id="tituloFalla">Describe la falla y su impacto</h3>
                            <p>Describe únicamente lo que observas. El técnico clasificará el tipo y la causa al iniciar la atención.</p>
                        </div>
                    </div>

                    <div class="scu-prioridad-fija" aria-label="Prioridad urgente">
                        <div>
                            <small>Prioridad</small>
                            <strong>URGENTE</strong>
                        </div>
                        <p>No puede modificarse en este tipo de solicitud.</p>
                    </div>

                    <div class="scp-grid">
                        <label class="scp-campo scp-campo--completo" for="descripcion_solicitud">
                            <span>¿Qué ocurrió y por qué es urgente? *</span>
                            <textarea
                                id="descripcion_solicitud"
                                name="descripcion_solicitud"
                                rows="5"
                                minlength="20"
                                maxlength="2500"
                                placeholder="Explica qué estaba ocurriendo, qué dejó de funcionar y por qué necesita atención inmediata."
                                required
                            ></textarea>
                            <span class="scp-campo__pie">
                                <small class="scp-ayuda">Evita escribir únicamente “no funciona”. Incluye el contexto.</small>
                                <small id="contadorDescripcion">0/2500</small>
                            </span>
                            <small class="scp-error-campo" data-error-for="descripcion_solicitud"></small>
                        </label>

                        <label class="scp-campo scp-campo--completo" for="descripcion_falla">
                            <span>Síntomas o condición actual del equipo *</span>
                            <textarea
                                id="descripcion_falla"
                                name="descripcion_falla"
                                rows="4"
                                minlength="10"
                                maxlength="1800"
                                placeholder="Ejemplo: ruido anormal, olor a quemado, fuga, paro repentino, alarma mostrada o pieza dañada."
                                required
                            ></textarea>
                            <span class="scp-campo__pie">
                                <small class="scp-ayuda">Describe únicamente lo observable. El diagnóstico técnico se capturará al iniciar la urgencia.</small>
                                <small id="contadorFalla">0/1800</small>
                            </span>
                            <small class="scp-error-campo" data-error-for="descripcion_falla"></small>
                        </label>

                        <label class="scp-campo scp-campo--completo" for="impacto_operacion">
                            <span>Impacto en la operación *</span>
                            <textarea
                                id="impacto_operacion"
                                name="impacto_operacion"
                                rows="4"
                                minlength="10"
                                maxlength="1800"
                                placeholder="Indica si detuvo producción, afecta calidad, genera riesgo, bloquea un proceso o provoca pérdida de tiempo."
                                required
                            ></textarea>
                            <span class="scp-campo__pie">
                                <small class="scp-ayuda">El impacto ayuda a los técnicos a dimensionar la prioridad real.</small>
                                <small id="contadorImpacto">0/1800</small>
                            </span>
                            <small class="scp-error-campo" data-error-for="impacto_operacion"></small>
                        </label>
                    </div>
                </section>

                <section class="scp-seccion" aria-labelledby="tituloSeguridad">
                    <div class="scp-seccion__titulo">
                        <span aria-hidden="true"><?= $numeroSeguridad ?></span>
                        <div>
                            <h3 id="tituloSeguridad">Condiciones de seguridad</h3>
                            <p>Selecciona una respuesta explícita para cada condición.</p>
                        </div>
                    </div>

                    <div class="scu-seguridad-grid">
                        <fieldset class="scu-eleccion" data-field-group="trabajo_peligroso">
                            <legend>¿La atención implica trabajo peligroso? *</legend>
                            <div class="scu-eleccion__opciones">
                                <label>
                                    <input type="radio" name="trabajo_peligroso" value="1" required>
                                    <span><strong>Sí</strong><small>Requiere precauciones especiales.</small></span>
                                </label>
                                <label>
                                    <input type="radio" name="trabajo_peligroso" value="0" required>
                                    <span><strong>No</strong><small>No se identifica trabajo peligroso.</small></span>
                                </label>
                            </div>
                            <small class="scp-error-campo" data-error-for="trabajo_peligroso"></small>
                        </fieldset>

                        <fieldset class="scu-eleccion" data-field-group="requiere_paro_equipo">
                            <legend>¿El equipo debe permanecer detenido? *</legend>
                            <div class="scu-eleccion__opciones">
                                <label>
                                    <input type="radio" name="requiere_paro_equipo" value="1" required>
                                    <span><strong>Sí</strong><small>No debe operarse hasta su revisión.</small></span>
                                </label>
                                <label>
                                    <input type="radio" name="requiere_paro_equipo" value="0" required>
                                    <span><strong>No</strong><small>Puede permanecer operando con precaución.</small></span>
                                </label>
                            </div>
                            <small class="scp-error-campo" data-error-for="requiere_paro_equipo"></small>
                        </fieldset>
                    </div>

                    <label
                        class="scp-campo scp-campo--completo scu-detalle-peligro"
                        id="detallePeligroCampo"
                        for="detalle_trabajo_peligroso"
                        hidden
                    >
                        <span>Motivo o precaución del trabajo peligroso *</span>
                        <textarea
                            id="detalle_trabajo_peligroso"
                            name="detalle_trabajo_peligroso"
                            rows="2"
                            minlength="3"
                            maxlength="200"
                            placeholder="Ejemplo: trabajo en altura, movimiento de una pieza pesada o riesgo eléctrico."
                        ></textarea>
                        <span class="scp-campo__pie">
                            <small class="scp-ayuda">Escribe solo la condición principal para que el técnico sepa qué precaución tomar.</small>
                            <small id="contadorDetallePeligro">0/200</small>
                        </span>
                        <small class="scp-error-campo" data-error-for="detalle_trabajo_peligroso"></small>
                    </label>

                    <div class="scp-grid scu-riesgo-grid">
                        <label class="scp-campo scp-campo--riesgo" for="nivel_riesgo">
                            <span>Nivel de riesgo *</span>
                            <select id="nivel_riesgo" name="nivel_riesgo" required>
                                <option value="">Selecciona el nivel</option>
                                <option value="BAJO">Bajo</option>
                                <option value="MEDIO">Medio</option>
                                <option value="ALTO">Alto</option>
                            </select>
                            <small class="scp-ayuda" id="ayudaRiesgo">Si marcas trabajo peligroso, selecciona MEDIO o ALTO.</small>
                            <small class="scp-error-campo" data-error-for="nivel_riesgo"></small>
                        </label>

                        <div class="scu-limite-tecnicos">
                            <span>Participación técnica</span>
                            <strong>Hasta <b id="limiteTecnicosTexto">10</b> técnicos</strong>
                            <p>Los técnicos aceptan directamente. El administrador no los asigna desde este formulario.</p>
                        </div>
                    </div>

                    <details class="scp-opcionales scu-opcionales" id="detallesOpcionales">
                        <summary>
                            <span>Observaciones adicionales</span>
                            <small>Opcional</small>
                        </summary>

                        <div class="scp-opcionales__contenido">
                            <label class="scp-campo" for="observaciones_solicitante">
                                <span>Información útil para llegar preparados</span>
                                <textarea
                                    id="observaciones_solicitante"
                                    name="observaciones_solicitante"
                                    rows="4"
                                    maxlength="1500"
                                    placeholder="Acceso al área, persona de contacto, herramienta que podría necesitarse, restricción o cualquier dato adicional."
                                ></textarea>
                                <span class="scp-campo__pie">
                                    <small class="scp-ayuda">No repitas la descripción de la falla.</small>
                                    <small id="contadorObservaciones">0/1500</small>
                                </span>
                                <small class="scp-error-campo" data-error-for="observaciones_solicitante"></small>
                            </label>
                        </div>
                    </details>
                </section>

                <footer class="scp-form-acciones scu-form-acciones">
                    <div>
                        <strong>La publicación es inmediata.</strong>
                        <span>Revisa equipo, impacto y seguridad antes de continuar.</span>
                    </div>
                    <button
                        type="submit"
                        class="scp-btn scp-btn--principal scu-btn--urgente"
                        id="btnRegistrarSolicitud"
                        disabled
                    >
                        <span class="scp-btn__icono" aria-hidden="true">
                            <svg><use href="#scu-icon-send"></use></svg>
                        </span>
                        <span>Publicar urgencia ahora</span>
                    </button>
                </footer>
            </form>
        </article>

        <aside class="scp-columna-lateral">
            <section class="scp-panel scp-panel--usuario scu-panel--usuario">
                <span class="scp-panel__icono" aria-hidden="true">
                    <svg><use href="#scu-icon-user"></use></svg>
                </span>
                <div>
                    <span class="scp-panel__etiqueta">Registrada para</span>
                    <h2 id="nombreSolicitante">Cargando...</h2>
                    <p id="departamentoSolicitante">—</p>
                </div>
            </section>

            <section class="scp-panel scp-panel--guia scu-panel--guia">
                <span class="scp-panel__etiqueta">Antes de publicar</span>
                <h2>Revisión rápida</h2>
                <div class="scp-checklist">
                    <div class="scp-check" data-check="origen">
                        <span aria-hidden="true">1</span>
                        <div><strong>Solicitante</strong><small>Identidad confirmada</small></div>
                    </div>
                    <div class="scp-check" data-check="equipo">
                        <span aria-hidden="true">2</span>
                        <div><strong>Equipo</strong><small>Código y ubicación validados</small></div>
                    </div>
                    <div class="scp-check" data-check="clasificacion">
                        <span aria-hidden="true">3</span>
                        <div><strong>Síntomas</strong><small>Condición observada</small></div>
                    </div>
                    <div class="scp-check" data-check="descripcion">
                        <span aria-hidden="true">4</span>
                        <div><strong>Descripción</strong><small>Urgencia e impacto</small></div>
                    </div>
                    <div class="scp-check" data-check="seguridad">
                        <span aria-hidden="true">5</span>
                        <div><strong>Seguridad</strong><small>Riesgo, peligro y paro</small></div>
                    </div>
                </div>
            </section>

            <section class="scp-panel scp-panel--recientes">
                <header class="scp-recientes__cabecera">
                    <div>
                        <span class="scp-panel__etiqueta">Seguimiento</span>
                        <h2>Urgencias recientes</h2>
                    </div>
                    <button
                        type="button"
                        class="scp-btn-icono"
                        id="btnActualizarRecientes"
                        title="Actualizar"
                        aria-label="Actualizar urgencias recientes"
                    >
                        <svg aria-hidden="true"><use href="#scu-icon-refresh"></use></svg>
                    </button>
                </header>

                <div class="scp-cargando" id="cargandoRecientes">
                    Cargando urgencias...
                </div>

                <div class="scp-vacio" id="vacioRecientes" hidden>
                    Todavía no hay urgencias registradas desde esta cuenta.
                </div>

                <div class="scp-recientes-lista" id="listaRecientes" hidden></div>
            </section>
        </aside>
    </section>

    <footer class="scp-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Canal de correctivos urgentes · Los Chapeteados División Petfood</span>
    </footer>

    <div class="scp-tools-background" aria-hidden="true"></div>
</main>

<?php include __DIR__ . '/../inc/alertas.php'; ?>

<script>
(function () {
    'use strict';

    const URL_FUNCIONES = '../funciones/solicitud_correctivo_urgente_funciones.php';
    const ES_ADMIN = <?= $esAdministrador ? 'true' : 'false' ?>;

    const estado = {
        rol: ES_ADMIN ? 'ADMIN' : 'SOLICITANTE',
        usuarioSesion: null,
        solicitante: null,
        solicitantes: [],
        equipo: null,
        maxTecnicos: 10,
        cargando: false,
        enviando: false
    };

    const el = {};

    document.addEventListener('DOMContentLoaded', iniciar);

    function iniciar() {
        capturarElementos();
        registrarEventos();
        actualizarContadores();
        actualizarChecklist();
        cargarInicial(true);
    }

    function capturarElementos() {
        [
            'formSolicitud', 'form_token', 'equipo_id', 'codigo_equipo',
            'btnBuscarEquipo', 'resultadosEquipo', 'textoResultadosEquipo',
            'btnCerrarResultadosEquipo', 'listaResultadosEquipo',
            'equipoSeleccionado', 'equipoCodigo', 'equipoNombre',
            'equipoDepartamento', 'equipoArea', 'equipoProceso',
            'equipoDescripcion', 'descripcion_solicitud',
            'descripcion_falla', 'impacto_operacion',
            'detallePeligroCampo', 'detalle_trabajo_peligroso',
            'nivel_riesgo', 'ayudaRiesgo', 'observaciones_solicitante',
            'contadorDescripcion', 'contadorFalla', 'contadorImpacto',
            'contadorDetallePeligro', 'contadorObservaciones', 'btnRegistrarSolicitud',
            'btnNuevaSolicitud', 'btnActualizarRecientes', 'mensajePagina',
            'fechaHoraServidor', 'nombreSolicitante', 'departamentoSolicitante',
            'identidadSeleccionada', 'identidadNombre', 'identidadDepartamento',
            'solicitante_opcion', 'resumenTotal', 'resumenSinRevisar',
            'resumenPublicadas', 'resumenAtencion', 'resumenCerradas',
            'limiteTecnicosTexto', 'cargandoRecientes', 'vacioRecientes',
            'listaRecientes'
        ].forEach(function (id) {
            el[id] = document.getElementById(id);
        });
    }

    function registrarEventos() {
        el.btnBuscarEquipo.addEventListener('click', buscarEquipo);

        el.codigo_equipo.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                buscarEquipo();
            }
        });

        el.codigo_equipo.addEventListener('input', function () {
            limpiarErrorCampo('codigo_equipo');

            if (
                estado.equipo
                && normalizarCodigo(el.codigo_equipo.value) !== normalizarCodigo(estado.equipo.codigo_equipo)
            ) {
                limpiarEquipoSeleccionado(false);
            }

            actualizarChecklist();
        });

        el.listaResultadosEquipo.addEventListener('click', function (evento) {
            const boton = evento.target.closest('[data-equipo-id]');

            if (!boton) {
                return;
            }

            const id = numero(boton.dataset.equipoId);
            const equipo = (estado.resultadosEquipo || []).find(function (item) {
                return numero(item.id) === id;
            });

            if (equipo && numero(equipo.seleccionable) === 1) {
                seleccionarEquipo(equipo);
            }
        });

        el.btnCerrarResultadosEquipo.addEventListener('click', ocultarResultadosEquipo);
        el.formSolicitud.addEventListener('submit', enviarFormulario);
        el.btnNuevaSolicitud.addEventListener('click', confirmarLimpiar);
        el.btnActualizarRecientes.addEventListener('click', function () {
            cargarInicial(false);
        });

        [el.nivel_riesgo].forEach(function (campo) {
            campo.addEventListener('change', function () {
                limpiarErrorCampo(campo.name);
                actualizarReglasRiesgo();
                actualizarChecklist();
            });
        });

        document.querySelectorAll('input[name="trabajo_peligroso"], input[name="requiere_paro_equipo"]').forEach(function (campo) {
            campo.addEventListener('change', function () {
                limpiarErrorCampo(campo.name);
                actualizarReglasRiesgo();
                actualizarChecklist();
            });
        });

        [
            el.descripcion_solicitud,
            el.descripcion_falla,
            el.impacto_operacion,
            el.detalle_trabajo_peligroso,
            el.observaciones_solicitante
        ].forEach(function (campo) {
            campo.addEventListener('input', function () {
                limpiarErrorCampo(campo.name);
                actualizarContadores();
                actualizarChecklist();
            });
        });

        if (ES_ADMIN && el.solicitante_opcion) {
            el.solicitante_opcion.addEventListener('change', function () {
                limpiarErrorCampo('solicitante_opcion');
                pintarIdentidadActual();
                actualizarChecklist();
            });
        }
    }

    async function cargarInicial(mostrarCargaCompleta) {
        if (estado.cargando) {
            return;
        }

        estado.cargando = true;

        if (mostrarCargaCompleta) {
            mostrarEstadoRecientes('cargando');
        }

        try {
            const datos = await SistemaUI.peticionJson(URL_FUNCIONES + '?accion=inicial');

            estado.rol = datos.rol || estado.rol;
            estado.usuarioSesion = datos.usuario_sesion || null;
            estado.solicitante = datos.solicitante || null;
            estado.solicitantes = Array.isArray(datos.solicitantes) ? datos.solicitantes : [];
            estado.maxTecnicos = numero(
                datos.configuracion && datos.configuracion.max_tecnicos_urgente
            ) || 10;

            el.form_token.value = datos.form_token || '';
            el.fechaHoraServidor.textContent = datos.fecha_hora_servidor || '—';
            el.limiteTecnicosTexto.textContent = String(estado.maxTecnicos);

            prepararOrigenSolicitud();
            pintarResumen(datos.resumen || {});
            pintarRecientes(Array.isArray(datos.recientes) ? datos.recientes : []);
            ocultarMensaje();
            actualizarChecklist();
        } catch (error) {
            mostrarEstadoRecientes('vacio');
            mostrarMensaje(error.message || 'No fue posible cargar la información.', 'error');
            await SistemaUI.error('No se pudo cargar el formulario', error.message || 'Inténtalo nuevamente.');
        } finally {
            estado.cargando = false;
        }
    }

    async function buscarEquipo() {
        const termino = normalizarBusqueda(el.codigo_equipo.value);

        limpiarErrorCampo('codigo_equipo');

        if (termino.length < 2) {
            marcarErrorCampo('codigo_equipo', 'Escribe al menos 2 caracteres para buscar.');
            el.codigo_equipo.focus();
            return;
        }

        SistemaUI.estadoBoton(el.btnBuscarEquipo, true, 'Buscando...');

        try {
            const datos = await SistemaUI.peticionJson(
                URL_FUNCIONES + '?accion=buscar_equipo&termino=' + encodeURIComponent(termino)
            );

            estado.resultadosEquipo = Array.isArray(datos.equipos) ? datos.equipos : [];

            if (datos.seleccion_automatica && estado.resultadosEquipo.length === 1) {
                seleccionarEquipo(estado.resultadosEquipo[0]);
                return;
            }

            pintarResultadosEquipo(estado.resultadosEquipo);
        } catch (error) {
            estado.resultadosEquipo = [];
            ocultarResultadosEquipo();
            const campo = error.datos && error.datos.campo ? error.datos.campo : 'codigo_equipo';
            marcarErrorCampo(campo, error.message || 'No fue posible buscar el equipo.');
        } finally {
            SistemaUI.estadoBoton(el.btnBuscarEquipo, false);
        }
    }

    function pintarResultadosEquipo(equipos) {
        el.listaResultadosEquipo.innerHTML = '';
        el.textoResultadosEquipo.textContent = equipos.length + (equipos.length === 1 ? ' coincidencia' : ' coincidencias');

        equipos.forEach(function (equipo) {
            const seleccionable = numero(equipo.seleccionable) === 1;
            const articulo = document.createElement('article');
            articulo.className = 'scp-resultado-equipo' + (seleccionable ? '' : ' scp-resultado-equipo--invalido');
            articulo.innerHTML =
                '<div class="scp-resultado-equipo__datos">' +
                    '<span>' + escapar(equipo.codigo_equipo || 'Sin código') + '</span>' +
                    '<strong>' + escapar(equipo.nombre_equipo || 'Equipo') + '</strong>' +
                    '<small>' + escapar([
                        equipo.departamento || 'Sin departamento',
                        equipo.area || 'Sin área',
                        equipo.proceso || 'Sin proceso'
                    ].join(' · ')) + '</small>' +
                '</div>' +
                (seleccionable
                    ? '<button type="button" class="scp-btn scp-btn--seleccionar" data-equipo-id="' + escaparAtributo(equipo.id || '') + '">Seleccionar</button>'
                    : '<span class="scu-equipo-invalido">Ubicación incompleta</span>');
            el.listaResultadosEquipo.appendChild(articulo);
        });

        el.resultadosEquipo.hidden = false;
    }

    function seleccionarEquipo(equipo) {
        estado.equipo = equipo;
        el.equipo_id.value = equipo.id || '';
        el.codigo_equipo.value = equipo.codigo_equipo || '';
        pintarEquipo(equipo);
        ocultarResultadosEquipo();
        limpiarErrorCampo('codigo_equipo');
        actualizarChecklist();
    }

    function ocultarResultadosEquipo() {
        el.resultadosEquipo.hidden = true;
        el.listaResultadosEquipo.innerHTML = '';
    }

    async function enviarFormulario(evento) {
        evento.preventDefault();

        // Impide dobles clics, dobles toques y el envío simultáneo con Enter.
        // El token del formulario es de un solo uso, por lo que nunca deben
        // existir dos peticiones POST activas para la misma captura.
        if (estado.enviando) {
            return;
        }

        estado.enviando = true;
        limpiarErrores();
        actualizarChecklist();

        try {
            if (!validarFormulario()) {
                await SistemaUI.advertencia(
                    'Revisa la urgencia',
                    'Completa correctamente los campos marcados antes de publicarla.'
                );
                return;
            }

            const confirmado = await SistemaUI.confirmar({
                titulo: '¿Publicar urgencia ahora?',
                texto: 'Se notificará inmediatamente a todos los técnicos activos y podrán aceptarla directamente hasta completar ' + estado.maxTecnicos + ' lugares.',
                textoConfirmar: 'Sí, publicar urgencia',
                icono: 'warning',
                peligro: true
            });

            if (!confirmado) {
                return;
            }

            SistemaUI.estadoBoton(el.btnRegistrarSolicitud, true, 'Publicando...');

            const datos = await SistemaUI.peticionJson(URL_FUNCIONES, {
                method: 'POST',
                body: new FormData(el.formSolicitud)
            });

            el.form_token.value = datos.form_token || '';
            pintarResumen(datos.resumen || {});
            pintarRecientes(Array.isArray(datos.recientes) ? datos.recientes : []);

            await Swal.fire({
                icon: 'success',
                title: 'Urgencia publicada',
                html:
                    '<div class="scp-swal-folio">' + escapar(datos.folio || '') + '</div>' +
                    '<p class="scp-swal-texto">Los técnicos activos ya fueron notificados. La urgencia quedó disponible para aceptación directa.</p>',
                confirmButtonText: 'Aceptar',
                allowOutsideClick: false,
                heightAuto: false
            });

            limpiarFormulario(true, false);
            el.form_token.value = datos.form_token || el.form_token.value;
            mostrarMensaje('La urgencia ' + (datos.folio || '') + ' fue publicada correctamente.', 'success');
        } catch (error) {
            if (error.datos && error.datos.form_token) {
                el.form_token.value = error.datos.form_token;
            }

            const campo = error.datos && error.datos.campo ? error.datos.campo : '';

            if (campo) {
                marcarErrorCampo(campo, error.message || 'Revisa este campo.');
                enfocarCampo(campo);
            }

            mostrarMensaje(error.message || 'No fue posible publicar la urgencia.', 'error');
            await SistemaUI.error(
                'No se publicó la urgencia',
                error.message || 'Revisa la información e inténtalo nuevamente.'
            );
        } finally {
            estado.enviando = false;
            SistemaUI.estadoBoton(el.btnRegistrarSolicitud, false);
            actualizarChecklist();
        }
    }

    function validarFormulario() {
        let valido = true;
        let primerCampo = '';

        function invalidar(campo, mensaje) {
            if (!primerCampo) {
                primerCampo = campo;
            }
            marcarErrorCampo(campo, mensaje);
            valido = false;
        }

        if (ES_ADMIN && !el.solicitante_opcion.value) {
            invalidar('solicitante_opcion', 'Selecciona a nombre de quién se registra.');
        }

        if (!estado.equipo || !numero(el.equipo_id.value)) {
            invalidar('codigo_equipo', 'Busca y selecciona el equipo afectado.');
        }

        if (normalizarBusqueda(el.descripcion_solicitud.value).length < 20) {
            invalidar('descripcion_solicitud', 'Escribe al menos 20 caracteres.');
        }

        if (normalizarBusqueda(el.descripcion_falla.value).length < 10) {
            invalidar('descripcion_falla', 'Escribe al menos 10 caracteres.');
        }

        if (normalizarBusqueda(el.impacto_operacion.value).length < 10) {
            invalidar('impacto_operacion', 'Describe el impacto con al menos 10 caracteres.');
        }

        const peligro = valorRadio('trabajo_peligroso');
        const paro = valorRadio('requiere_paro_equipo');

        if (peligro === null) {
            invalidar('trabajo_peligroso', 'Indica si implica trabajo peligroso.');
        } else if (peligro === '1') {
            const detallePeligro = normalizarBusqueda(el.detalle_trabajo_peligroso.value);
            if (detallePeligro.length < 3) {
                invalidar(
                    'detalle_trabajo_peligroso',
                    'Describe brevemente el peligro o la precaución necesaria.'
                );
            }
        }

        if (paro === null) {
            invalidar('requiere_paro_equipo', 'Indica si el equipo debe permanecer detenido.');
        }

        if (!el.nivel_riesgo.value) {
            invalidar('nivel_riesgo', 'Selecciona el nivel de riesgo.');
        }

        if (primerCampo) {
            enfocarCampo(primerCampo);
        }

        return valido;
    }

    async function confirmarLimpiar() {
        const tieneDatos = Boolean(
            el.codigo_equipo.value
            || el.descripcion_solicitud.value
            || el.descripcion_falla.value
            || el.impacto_operacion.value
            || el.detalle_trabajo_peligroso.value
            || valorRadio('trabajo_peligroso') !== null
            || valorRadio('requiere_paro_equipo') !== null
        );

        if (!tieneDatos) {
            limpiarFormulario(true, true);
            return;
        }

        const confirmado = await SistemaUI.confirmar({
            titulo: '¿Limpiar el formulario?',
            texto: 'Se perderá la información que todavía no has publicado.',
            textoConfirmar: 'Sí, limpiar',
            icono: 'question'
        });

        if (confirmado) {
            limpiarFormulario(true, true);
        }
    }

    function limpiarFormulario(enfocarCodigo, conservarToken) {
        const token = el.form_token.value;
        el.formSolicitud.reset();

        if (conservarToken) {
            el.form_token.value = token;
        }

        limpiarEquipoSeleccionado(true);
        ocultarResultadosEquipo();
        limpiarErrores();
        ocultarMensaje();
        actualizarReglasRiesgo();
        actualizarContadores();

        if (ES_ADMIN) {
            llenarSelectorSolicitantes();
            pintarIdentidadActual();
        } else {
            pintarSolicitante(estado.solicitante);
        }

        actualizarChecklist();

        if (enfocarCodigo) {
            window.setTimeout(function () {
                el.codigo_equipo.focus();
            }, 80);
        }
    }

    function pintarEquipo(equipo) {
        el.equipoCodigo.textContent = equipo.codigo_equipo || 'Sin código';
        el.equipoNombre.textContent = equipo.nombre_equipo || 'Equipo';
        el.equipoDepartamento.textContent = equipo.departamento || '—';
        el.equipoArea.textContent = equipo.area || '—';
        el.equipoProceso.textContent = equipo.proceso || '—';
        el.equipoDescripcion.textContent = equipo.descripcion || '';
        el.equipoDescripcion.hidden = !equipo.descripcion;
        el.equipoSeleccionado.hidden = false;
    }

    function limpiarEquipoSeleccionado(limpiarBusqueda) {
        estado.equipo = null;
        el.equipo_id.value = '';
        el.equipoSeleccionado.hidden = true;
        el.equipoDescripcion.hidden = true;

        if (limpiarBusqueda) {
            el.codigo_equipo.value = '';
        }
    }

    function prepararOrigenSolicitud() {
        if (ES_ADMIN) {
            llenarSelectorSolicitantes();
            pintarIdentidadActual();
        } else {
            pintarSolicitante(estado.solicitante);
        }
    }

    function llenarSelectorSolicitantes() {
        if (!el.solicitante_opcion) {
            return;
        }

        const valorActual = el.solicitante_opcion.value;
        el.solicitante_opcion.innerHTML = '';

        const inicial = document.createElement('option');
        inicial.value = '';
        inicial.textContent = 'Selecciona una opción';
        el.solicitante_opcion.appendChild(inicial);

        const admin = document.createElement('option');
        admin.value = 'ADMIN';
        admin.textContent = 'Registro directo del administrador';
        el.solicitante_opcion.appendChild(admin);

        estado.solicitantes.forEach(function (solicitante) {
            const opcion = document.createElement('option');
            opcion.value = 'SOLICITANTE:' + solicitante.id;
            opcion.textContent = (solicitante.nombre_completo || 'Solicitante') + ' · ' + (solicitante.departamento || 'Sin departamento');
            el.solicitante_opcion.appendChild(opcion);
        });

        el.solicitante_opcion.disabled = false;

        if (valorActual && Array.from(el.solicitante_opcion.options).some(function (opcion) {
            return opcion.value === valorActual;
        })) {
            el.solicitante_opcion.value = valorActual;
        }
    }

    function pintarIdentidadActual() {
        const valor = el.solicitante_opcion.value;
        let persona = null;

        if (valor === 'ADMIN') {
            persona = {
                nombre_completo: estado.usuarioSesion ? estado.usuarioSesion.nombre_completo : 'Administrador',
                departamento: 'Registro directo del administrador'
            };
        } else if (valor.indexOf('SOLICITANTE:') === 0) {
            const id = numero(valor.split(':')[1]);
            persona = estado.solicitantes.find(function (item) {
                return numero(item.id) === id;
            }) || null;
        }

        if (!persona) {
            el.identidadSeleccionada.hidden = true;
            el.nombreSolicitante.textContent = 'Selecciona un solicitante';
            el.departamentoSolicitante.textContent = 'La urgencia debe quedar vinculada a una persona.';
            return;
        }

        el.identidadNombre.textContent = persona.nombre_completo || '—';
        el.identidadDepartamento.textContent = persona.departamento || '—';
        el.identidadSeleccionada.hidden = false;
        pintarSolicitante(persona);
    }

    function pintarSolicitante(solicitante) {
        el.nombreSolicitante.textContent = solicitante && solicitante.nombre_completo
            ? solicitante.nombre_completo
            : 'Solicitante';
        el.departamentoSolicitante.textContent = solicitante && solicitante.departamento
            ? solicitante.departamento
            : 'Sin departamento asignado';
    }

    function pintarResumen(resumen) {
        el.resumenTotal.textContent = numero(resumen.total);
        el.resumenSinRevisar.textContent = numero(resumen.sin_revisar);
        el.resumenPublicadas.textContent = numero(resumen.publicadas);
        el.resumenAtencion.textContent = numero(resumen.en_atencion);
        el.resumenCerradas.textContent = numero(resumen.cerradas);
    }

    function pintarRecientes(lista) {
        el.listaRecientes.innerHTML = '';

        if (!lista.length) {
            mostrarEstadoRecientes('vacio');
            return;
        }

        lista.forEach(function (solicitud) {
            const articulo = document.createElement('article');
            articulo.className = 'scp-reciente scu-reciente';
            const aceptaron = numero(solicitud.tecnicos_aceptaron);
            const limite = numero(solicitud.cupo_tecnicos_urgente) || estado.maxTecnicos;
            const revision = solicitud.revisada
                ? '<span class="scu-revision scu-revision--lista">Revisada</span>'
                : '<span class="scu-revision scu-revision--pendiente">Sin revisar</span>';

            articulo.innerHTML =
                '<div class="scp-reciente__cabecera">' +
                    '<strong>' + escapar(solicitud.folio || '') + '</strong>' +
                    '<span class="scp-badge ' + badgeEstado(solicitud.estado) + '">' + escapar(textoEstado(solicitud.estado)) + '</span>' +
                '</div>' +
                '<span class="scp-reciente__solicitante">' + escapar(solicitud.nombre_solicitante || '') + '</span>' +
                '<h3>' + escapar((solicitud.codigo_equipo || '') + ' · ' + (solicitud.nombre_equipo || '')) + '</h3>' +
                '<p>' + escapar(recortar(solicitud.descripcion_solicitud || '', 130)) + '</p>' +
                '<div class="scu-reciente__datos">' +
                    '<span>Riesgo: <b>' + escapar(solicitud.nivel_riesgo || '—') + '</b></span>' +
                    '<span>Técnicos: <b>' + aceptaron + '/' + limite + '</b></span>' +
                '</div>' +
                '<div class="scp-reciente__pie">' +
                    '<span>' + escapar(solicitud.fecha_registro_formato || '') + '</span>' +
                    revision +
                '</div>';
            el.listaRecientes.appendChild(articulo);
        });

        mostrarEstadoRecientes('lista');
    }

    function mostrarEstadoRecientes(tipo) {
        el.cargandoRecientes.hidden = tipo !== 'cargando';
        el.vacioRecientes.hidden = tipo !== 'vacio';
        el.listaRecientes.hidden = tipo !== 'lista';
    }

    function actualizarReglasRiesgo() {
        const peligro = valorRadio('trabajo_peligroso');

        const esPeligroso = peligro === '1';
        el.detallePeligroCampo.hidden = !esPeligroso;
        el.detalle_trabajo_peligroso.required = esPeligroso;

        if (!esPeligroso && el.detalle_trabajo_peligroso.value !== '') {
            el.detalle_trabajo_peligroso.value = '';
            limpiarErrorCampo('detalle_trabajo_peligroso');
            actualizarContadores();
        }

        if (esPeligroso) {
            el.ayudaRiesgo.textContent = 'Se marcó trabajo peligroso. Selecciona el nivel real según las condiciones observadas.';
        } else if (peligro === '0') {
            el.ayudaRiesgo.textContent = 'Selecciona el nivel real de riesgo aunque no se haya identificado trabajo peligroso.';
        } else {
            el.ayudaRiesgo.textContent = 'Indica el nivel real de riesgo observado en la urgencia.';
        }
    }

    function actualizarChecklist() {
        const origenCompleto = ES_ADMIN
            ? Boolean(el.solicitante_opcion.value)
            : Boolean(estado.solicitante);
        const equipoCompleto = Boolean(estado.equipo && numero(el.equipo_id.value));
        const clasificacionCompleta = normalizarBusqueda(el.descripcion_falla.value).length >= 10;
        const descripcionCompleta = normalizarBusqueda(el.descripcion_solicitud.value).length >= 20
            && normalizarBusqueda(el.impacto_operacion.value).length >= 10;
        const peligro = valorRadio('trabajo_peligroso');
        const paro = valorRadio('requiere_paro_equipo');
        const riesgoValido = Boolean(el.nivel_riesgo.value);
        const detallePeligroValido = peligro !== '1'
            || normalizarBusqueda(el.detalle_trabajo_peligroso.value).length >= 3;
        const seguridadCompleta = peligro !== null
            && paro !== null
            && riesgoValido
            && detallePeligroValido;

        cambiarCheck('origen', origenCompleto);
        cambiarCheck('equipo', equipoCompleto);
        cambiarCheck('clasificacion', clasificacionCompleta);
        cambiarCheck('descripcion', descripcionCompleta);
        cambiarCheck('seguridad', seguridadCompleta);

        el.btnRegistrarSolicitud.disabled = estado.enviando || !(
            origenCompleto
            && equipoCompleto
            && clasificacionCompleta
            && descripcionCompleta
            && seguridadCompleta
        );
    }

    function cambiarCheck(clave, completo) {
        const item = document.querySelector('[data-check="' + clave + '"]');
        if (item) {
            item.classList.toggle('is-complete', Boolean(completo));
        }
    }

    function actualizarContadores() {
        el.contadorDescripcion.textContent = el.descripcion_solicitud.value.length + '/2500';
        el.contadorFalla.textContent = el.descripcion_falla.value.length + '/1800';
        el.contadorImpacto.textContent = el.impacto_operacion.value.length + '/1800';
        el.contadorDetallePeligro.textContent = el.detalle_trabajo_peligroso.value.length + '/200';
        el.contadorObservaciones.textContent = el.observaciones_solicitante.value.length + '/1500';
    }

    function valorRadio(nombre) {
        const seleccionado = document.querySelector('input[name="' + nombre + '"]:checked');
        return seleccionado ? seleccionado.value : null;
    }

    function mostrarMensaje(texto, tipo) {
        el.mensajePagina.textContent = texto;
        el.mensajePagina.className = 'scp-mensaje ' + (tipo === 'success' ? 'scp-mensaje--success' : 'scp-mensaje--error');
        el.mensajePagina.hidden = false;
    }

    function ocultarMensaje() {
        el.mensajePagina.hidden = true;
        el.mensajePagina.textContent = '';
        el.mensajePagina.className = 'scp-mensaje';
    }

    function marcarErrorCampo(campo, mensaje) {
        const error = document.querySelector('[data-error-for="' + campo + '"]');
        const input = document.querySelector('[name="' + campo + '"]');
        const grupo = document.querySelector('[data-field-group="' + campo + '"]');

        if (error) {
            error.textContent = mensaje;
        }

        if (input) {
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
        }

        if (grupo) {
            grupo.classList.add('is-invalid');
        }
    }

    function limpiarErrorCampo(campo) {
        const error = document.querySelector('[data-error-for="' + campo + '"]');
        const inputs = document.querySelectorAll('[name="' + campo + '"]');
        const grupo = document.querySelector('[data-field-group="' + campo + '"]');

        if (error) {
            error.textContent = '';
        }

        inputs.forEach(function (input) {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
        });

        if (grupo) {
            grupo.classList.remove('is-invalid');
        }
    }

    function limpiarErrores() {
        document.querySelectorAll('.scp-error-campo').forEach(function (error) {
            error.textContent = '';
        });
        document.querySelectorAll('.is-invalid').forEach(function (campo) {
            campo.classList.remove('is-invalid');
            campo.removeAttribute('aria-invalid');
        });
    }

    function enfocarCampo(campo) {
        const input = document.querySelector('[name="' + campo + '"]');
        const grupo = document.querySelector('[data-field-group="' + campo + '"]');
        const destino = input || grupo;

        if (!destino) {
            return;
        }

        if (destino.focus) {
            destino.focus({ preventScroll: true });
        }

        destino.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function normalizarBusqueda(valor) {
        return String(valor || '').trim().replace(/\s+/g, ' ');
    }

    function normalizarCodigo(valor) {
        return String(valor || '').trim().replace(/\s+/g, '').toUpperCase();
    }

    function numero(valor) {
        const convertido = Number(valor || 0);
        return Number.isFinite(convertido) ? convertido : 0;
    }

    function badgeEstado(estadoSolicitud) {
        const estadoNormalizado = String(estadoSolicitud || '').toLowerCase();
        return 'scp-badge--' + estadoNormalizado;
    }

    function textoEstado(valor) {
        const mapa = {
            AGENDADO: 'Disponible',
            EN_PROCESO: 'En proceso',
            PAUSADO: 'Pausada',
            ATRASADO: 'Atrasada',
            TERMINADO: 'Terminada',
            RECHAZADO: 'Rechazada',
            CANCELADO: 'Cancelada',
            PENDIENTE: 'Pendiente',
            APROBADO: 'Aprobada'
        };
        return mapa[String(valor || '').toUpperCase()] || String(valor || 'Sin estado');
    }

    function recortar(texto, limite) {
        const valor = String(texto || '');
        return valor.length > limite ? valor.slice(0, limite - 1) + '…' : valor;
    }

    function escaparAtributo(valor) {
        return escapar(valor).replace(/`/g, '&#96;');
    }

    function escapar(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}()); 
</script>

</body>
</html>