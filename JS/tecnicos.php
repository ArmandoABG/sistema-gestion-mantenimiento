<?php

declare(strict_types=1);

/*
 * La interfaz consulta esta misma página mediante ?tec_api=1 para evitar
 * rutas relativas frágiles hacia la carpeta funciones.
 */
if (isset($_GET['tec_api'])) {
    // Las advertencias se registran en PHP, pero nunca deben mezclarse con el JSON.
    @ini_set('display_errors', '0');
    @ini_set('html_errors', '0');

    $endpoint = __DIR__ . '/../funciones/tecnicos_funciones.php';

    if (!is_file($endpoint)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode(
            [
                'success' => false,
                'mensaje' => 'No se encontró funciones/tecnicos_funciones.php. Copia juntos los tres archivos del módulo.',
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

$csrfToken = sm_token_csrf();

$nombreAdmin = trim((string) (
    $_SESSION['nombre_completo']
    ?? $_SESSION['nombre']
    ?? $_SESSION['usuario']
    ?? 'Administrador'
));

if ($nombreAdmin === '') {
    $nombreAdmin = 'Administrador';
}

$cssPath = __DIR__ . '/../css/style_tecnicos.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '4.0';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b2944">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Gestión segura de cuentas técnicas del Sistema de Mantenimiento.">
    <title>Técnicos | Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="../css/style_tecnicos.css?v=<?= rawurlencode($cssVersion) ?>">
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="tec-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="tec-icon-technicians" viewBox="0 0 24 24">
        <path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="8" cy="7" r="4"/>
        <path d="M17 11l2 2 4-4M18 21v-5M15.5 18.5h5"/>
    </symbol>
    <symbol id="tec-icon-user-plus" viewBox="0 0 24 24">
        <path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="8" cy="7" r="4"/>
        <path d="M19 8v6M16 11h6"/>
    </symbol>
    <symbol id="tec-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="tec-icon-toolbox" viewBox="0 0 24 24">
        <path d="M3 8h18v12H3zM8 8V5h8v3M3 13h18"/>
        <path d="M10 12h4v3h-4z"/>
    </symbol>
    <symbol id="tec-icon-shield" viewBox="0 0 24 24">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
        <path d="m9 12 2 2 4-4"/>
    </symbol>
    <symbol id="tec-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="tec-icon-list" viewBox="0 0 24 24">
        <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
    </symbol>
    <symbol id="tec-icon-check" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8 12 2.6 2.6L16.5 9"/>
    </symbol>
    <symbol id="tec-icon-activity" viewBox="0 0 24 24">
        <path d="M3 12h4l2-6 4 12 2-6h6"/>
    </symbol>
    <symbol id="tec-icon-user-x" viewBox="0 0 24 24">
        <path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="8" cy="7" r="4"/>
        <path d="m18 8 5 5M23 8l-5 5"/>
    </symbol>
    <symbol id="tec-icon-filter" viewBox="0 0 24 24">
        <path d="M4 5h16M7 12h10M10 19h4"/>
    </symbol>
    <symbol id="tec-icon-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </symbol>
</svg>

<main class="tec-page">
    <header class="tec-heading" aria-labelledby="tituloTecnicos">
        <div class="tec-heading__pattern" aria-hidden="true"></div>

        <div class="tec-heading__content">
            <div class="tec-heading__copy">
                <p class="tec-eyebrow">
                    <span class="tec-eyebrow__icon"><svg><use href="#tec-icon-technicians"></use></svg></span>
                    Gestión de personal técnico
                </p>
                <h1 id="tituloTecnicos">Técnicos</h1>
                <p>
                    Administra las cuentas del personal técnico, su departamento, turno, especialidad
                    y carga operativa sin perder la trazabilidad de sus mantenimientos.
                </p>

                <div class="tec-heading__meta">
                    <span><i class="tec-live-dot" aria-hidden="true"></i> Asignaciones y ejecuciones protegidas</span>
                    <span>Administrador: <strong><?= htmlspecialchars($nombreAdmin, ENT_QUOTES, 'UTF-8') ?></strong></span>
                </div>
            </div>

            <div class="tec-heading__actions" aria-label="Acciones de técnicos">
                <button type="button" class="tec-btn tec-btn--secondary" id="btnActualizar">
                    <svg><use href="#tec-icon-refresh"></use></svg>
                    <span>Actualizar</span>
                </button>
                <button type="button" class="tec-btn tec-btn--primary" id="btnNuevo">
                    <svg><use href="#tec-icon-user-plus"></use></svg>
                    <span>Nuevo técnico</span>
                </button>
            </div>

            <div class="tec-heading__mini-card" aria-hidden="true">
                <span><svg><use href="#tec-icon-toolbox"></use></svg></span>
                <div>
                    <small>Centro operativo</small>
                    <strong>Personal, turnos y carga de trabajo protegida</strong>
                </div>
            </div>
        </div>
    </header>

    <section class="tec-security-note" aria-label="Reglas del módulo">
        <span class="tec-security-note__icon"><svg><use href="#tec-icon-shield"></use></svg></span>
        <div>
            <strong>Protección de trabajos activos</strong>
            <p>
                Las cuentas se desactivan, no se eliminan. Un técnico con asignaciones o ejecuciones
                activas no puede desactivarse ni cambiar de departamento o turno hasta liberar su carga.
            </p>
        </div>
        <span class="tec-security-note__badge">Historial protegido</span>
    </section>

    <div class="tec-status" id="estadoPagina" role="status" aria-live="polite">
        <span class="tec-spinner tec-spinner--small" aria-hidden="true"></span>
        <span>Cargando técnicos...</span>
    </div>

    <section class="tec-kpis" aria-label="Resumen de técnicos">
        <article class="tec-kpi tec-kpi--total">
            <span class="tec-kpi__icon"><svg><use href="#tec-icon-technicians"></use></svg></span>
            <span class="tec-kpi__body">
                <span>Total</span>
                <strong id="kpiTotal">0</strong>
                <small>Cuentas registradas</small>
            </span>
        </article>
        <article class="tec-kpi tec-kpi--active">
            <span class="tec-kpi__icon"><svg><use href="#tec-icon-check"></use></svg></span>
            <span class="tec-kpi__body">
                <span>Activos</span>
                <strong id="kpiActivos">0</strong>
                <small>Con acceso al sistema</small>
            </span>
        </article>
        <article class="tec-kpi tec-kpi--work">
            <span class="tec-kpi__icon"><svg><use href="#tec-icon-activity"></use></svg></span>
            <span class="tec-kpi__body">
                <span>Con trabajo</span>
                <strong id="kpiConTrabajo">0</strong>
                <small>Con asignaciones activas</small>
            </span>
        </article>
        <article class="tec-kpi tec-kpi--inactive">
            <span class="tec-kpi__icon"><svg><use href="#tec-icon-user-x"></use></svg></span>
            <span class="tec-kpi__body">
                <span>Inactivos</span>
                <strong id="kpiInactivos">0</strong>
                <small>Conservados por historial</small>
            </span>
        </article>
    </section>

    <section class="tec-card tec-filters-card" aria-labelledby="tituloFiltrosTecnicos">
        <header class="tec-section-head">
            <div>
                <p class="tec-eyebrow">Búsqueda y filtros</p>
                <h2 id="tituloFiltrosTecnicos">Encuentra un técnico</h2>
                <p>Consulta por identidad, especialidad, departamento, turno, carga actual o estado de cuenta.</p>
            </div>
            <span class="tec-section-head__chip"><svg><use href="#tec-icon-filter"></use></svg> Consulta operativa</span>
        </header>

        <div class="tec-filters tec-filters--tecnicos">
            <label class="tec-field tec-field--search" for="filtroBusqueda">
                <span>Buscar</span>
                <div class="tec-search">
                    <span aria-hidden="true"><svg><use href="#tec-icon-search"></use></svg></span>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        maxlength="120"
                        placeholder="Nombre, usuario, especialidad o contacto"
                        autocomplete="off"
                    >
                </div>
            </label>

            <label class="tec-field" for="filtroDepartamento">
                <span>Departamento</span>
                <select id="filtroDepartamento">
                    <option value="">Todos</option>
                </select>
            </label>

            <label class="tec-field" for="filtroTurno">
                <span>Turno</span>
                <select id="filtroTurno">
                    <option value="TODOS">Todos</option>
                    <option value="MATUTINO">Matutino</option>
                    <option value="VESPERTINO">Vespertino</option>
                    <option value="NOCTURNO">Nocturno</option>
                </select>
            </label>

            <label class="tec-field" for="filtroCarga">
                <span>Carga actual</span>
                <select id="filtroCarga">
                    <option value="TODOS">Todos</option>
                    <option value="CON_TRABAJO">Con trabajo</option>
                    <option value="SIN_TRABAJO">Sin trabajo</option>
                    <option value="EN_EJECUCION">En ejecución</option>
                </select>
            </label>

            <label class="tec-field" for="filtroEstado">
                <span>Estado</span>
                <select id="filtroEstado">
                    <option value="TODOS">Todos</option>
                    <option value="ACTIVO">Activos</option>
                    <option value="INACTIVO">Inactivos</option>
                    <option value="SIN_ACCESO">Sin ingreso</option>
                </select>
            </label>

            <label class="tec-field tec-field--small" for="filtroCantidad">
                <span>Mostrar</span>
                <select id="filtroCantidad">
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                    <option value="40">40</option>
                    <option value="80">80</option>
                </select>
            </label>

            <div class="tec-filter-actions">
                <button type="button" class="tec-btn tec-btn--ghost" id="btnLimpiar">Limpiar</button>
            </div>
        </div>
    </section>

    <section class="tec-card tec-results tec-results-card" aria-labelledby="tituloListadoTecnicos">
        <header class="tec-results__head">
            <div>
                <p class="tec-eyebrow">Resultados</p>
                <h2 id="tituloListadoTecnicos">Personal técnico</h2>
                <p id="textoResultados">Preparando resultados...</p>
            </div>

            <div class="tec-results__tools">
                <span class="tec-updated" id="ultimaActualizacion">Sin actualizar</span>
                <span class="tec-results__badge"><svg><use href="#tec-icon-list"></use></svg> Carga protegida</span>
            </div>
        </header>

        <div class="tec-loading" id="estadoCarga">
            <span class="tec-spinner" aria-hidden="true"></span>
            <strong>Cargando técnicos...</strong>
        </div>

        <div class="tec-empty" id="estadoVacio" hidden>
            <span aria-hidden="true"><svg><use href="#tec-icon-search"></use></svg></span>
            <h3>No hay coincidencias</h3>
            <p>Prueba con otro nombre o modifica los filtros aplicados.</p>
        </div>

        <div class="tec-table-wrap" id="contenedorTabla" hidden tabindex="0" aria-label="Listado desplazable de técnicos">
            <table class="tec-table">
                <thead>
                    <tr>
                        <th>Técnico</th>
                        <th>Perfil laboral</th>
                        <th>Contacto</th>
                        <th>Carga</th>
                        <th>Último acceso</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaTecnicos"></tbody>
            </table>
        </div>

        <footer class="tec-pagination" id="paginacion" hidden>
            <span id="textoPaginacion">Sin resultados</span>
            <div class="tec-pagination__buttons">
                <button type="button" id="btnAnterior">Anterior</button>
                <span id="paginaActual">Página 1</span>
                <button type="button" id="btnSiguiente">Siguiente</button>
            </div>
        </footer>
    </section>

    <footer class="tec-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Gestión de técnicos protegida · Los Chapeteados División Petfood</span>
    </footer>

    <div class="tec-tools-background" aria-hidden="true"></div>
</main>

<!-- Alta y edición -->
<section class="tec-modal" id="modalTecnico" hidden>
    <div class="tec-modal__dialog tec-modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="tituloModal">
        <header class="tec-modal__header">
            <div>
                <p class="tec-eyebrow" id="etiquetaModal">NUEVO REGISTRO</p>
                <h2 id="tituloModal">Nuevo técnico</h2>
                <p id="subtituloModal">Crea una cuenta para atender mantenimientos.</p>
            </div>
            <button type="button" class="tec-modal__close" data-close="modalTecnico" aria-label="Cerrar">×</button>
        </header>

        <form id="formTecnico" novalidate>
            <input type="hidden" id="tecnicoId" name="tecnico_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="tec-modal__body">
                <section class="tec-form-section">
                    <header>
                        <span>01</span>
                        <div>
                            <h3>Datos de acceso</h3>
                            <p>El usuario y el correo deben ser únicos en todo el sistema.</p>
                        </div>
                    </header>

                    <div class="tec-form-grid">
                        <label class="tec-form-field" for="usuario">
                            <span>Usuario *</span>
                            <input
                                type="text"
                                id="usuario"
                                name="usuario"
                                minlength="3"
                                maxlength="60"
                                autocomplete="username"
                                autocapitalize="none"
                                spellcheck="false"
                                placeholder="Ej. tecnico.mecanico"
                                required
                            >
                            <small>Letras minúsculas, números, punto, guion o guion bajo.</small>
                            <em class="tec-error" data-error-for="usuario"></em>
                        </label>

                        <label class="tec-form-field" for="correo">
                            <span>Correo electrónico</span>
                            <input type="email" id="correo" name="correo" maxlength="150" autocomplete="email" placeholder="tecnico@empresa.com">
                            <small>Opcional, pero no puede repetirse.</small>
                            <em class="tec-error" data-error-for="correo"></em>
                        </label>
                    </div>
                </section>

                <section class="tec-form-section" id="seccionPasswordNuevo">
                    <header>
                        <span>02</span>
                        <div>
                            <h3>Contraseña inicial</h3>
                            <p>Después podrá restablecerse desde la lista de técnicos.</p>
                        </div>
                    </header>

                    <div class="tec-form-grid">
                        <label class="tec-form-field" for="password">
                            <span>Contraseña *</span>
                            <div class="tec-password">
                                <input type="password" id="password" name="password" minlength="10" maxlength="72" autocomplete="new-password">
                                <button type="button" data-toggle-password="password">Mostrar</button>
                            </div>
                            <small>Mínimo 10 caracteres, mayúscula, minúscula y número.</small>
                            <em class="tec-error" data-error-for="password"></em>
                        </label>

                        <label class="tec-form-field" for="confirmarPassword">
                            <span>Confirmar contraseña *</span>
                            <div class="tec-password">
                                <input type="password" id="confirmarPassword" name="confirmar_password" minlength="10" maxlength="72" autocomplete="new-password">
                                <button type="button" data-toggle-password="confirmarPassword">Mostrar</button>
                            </div>
                            <em class="tec-error" data-error-for="confirmar_password"></em>
                        </label>
                    </div>

                    <div class="tec-password-rules" id="reglasPassword">
                        <span data-rule="length">10 o más caracteres</span>
                        <span data-rule="lower">Una minúscula</span>
                        <span data-rule="upper">Una mayúscula</span>
                        <span data-rule="number">Un número</span>
                        <span data-rule="match">Coinciden</span>
                    </div>
                </section>

                <section class="tec-form-section">
                    <header>
                        <span id="numeroSeccionPersonal">03</span>
                        <div>
                            <h3>Datos personales</h3>
                            <p>Información utilizada para identificar al técnico.</p>
                        </div>
                    </header>

                    <div class="tec-form-grid tec-form-grid--three">
                        <label class="tec-form-field" for="nombre">
                            <span>Nombre *</span>
                            <input type="text" id="nombre" name="nombre" minlength="2" maxlength="100" autocomplete="given-name" required>
                            <em class="tec-error" data-error-for="nombre"></em>
                        </label>

                        <label class="tec-form-field" for="apellidoPaterno">
                            <span>Apellido paterno</span>
                            <input type="text" id="apellidoPaterno" name="apellido_paterno" maxlength="100" autocomplete="family-name">
                            <em class="tec-error" data-error-for="apellido_paterno"></em>
                        </label>

                        <label class="tec-form-field" for="apellidoMaterno">
                            <span>Apellido materno</span>
                            <input type="text" id="apellidoMaterno" name="apellido_materno" maxlength="100">
                            <em class="tec-error" data-error-for="apellido_materno"></em>
                        </label>

                        <label class="tec-form-field" for="telefono">
                            <span>Teléfono</span>
                            <input type="tel" id="telefono" name="telefono" inputmode="numeric" maxlength="14" autocomplete="tel" placeholder="10 dígitos">
                            <em class="tec-error" data-error-for="telefono"></em>
                        </label>
                    </div>
                </section>

                <section class="tec-form-section">
                    <header>
                        <span id="numeroSeccionLaboral">04</span>
                        <div>
                            <h3>Perfil laboral</h3>
                            <p>Datos utilizados para programación, disponibilidad y asignación.</p>
                        </div>
                    </header>

                    <div class="tec-security-callout" id="avisoTrabajoActivo" hidden>
                        <strong>Este técnico tiene trabajo activo</strong>
                        <p>Puede corregir sus datos personales y especialidad, pero no cambiar su departamento o turno hasta finalizar o reasignar sus mantenimientos.</p>
                    </div>

                    <div class="tec-form-grid tec-form-grid--three">
                        <label class="tec-form-field" for="departamentoId">
                            <span>Departamento *</span>
                            <select id="departamentoId" name="departamento_id" required>
                                <option value="">Selecciona un departamento</option>
                            </select>
                            <em class="tec-error" data-error-for="departamento_id"></em>
                        </label>

                        <label class="tec-form-field" for="turno">
                            <span>Turno *</span>
                            <select id="turno" name="turno" required>
                                <option value="">Selecciona un turno</option>
                                <option value="MATUTINO">Matutino</option>
                                <option value="VESPERTINO">Vespertino</option>
                                <option value="NOCTURNO">Nocturno</option>
                            </select>
                            <small>El turno nocturno se considera en las validaciones de riesgo.</small>
                            <em class="tec-error" data-error-for="turno"></em>
                        </label>

                        <label class="tec-form-field" for="especialidad">
                            <span>Especialidad *</span>
                            <input
                                type="text"
                                id="especialidad"
                                name="especialidad"
                                minlength="2"
                                maxlength="150"
                                placeholder="Ej. Mecánica industrial"
                                required
                            >
                            <em class="tec-error" data-error-for="especialidad"></em>
                        </label>
                    </div>
                </section>
            </div>

            <footer class="tec-modal__footer">
                <button type="button" class="tec-btn tec-btn--ghost" data-close="modalTecnico">Cancelar</button>
                <button type="submit" class="tec-btn tec-btn--primary" id="btnGuardar">Guardar técnico</button>
            </footer>
        </form>
    </div>
</section>

<!-- Restablecimiento de contraseña -->
<section class="tec-modal" id="modalPassword" hidden>
    <div class="tec-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tituloPassword">
        <header class="tec-modal__header">
            <div>
                <p class="tec-eyebrow">SEGURIDAD</p>
                <h2 id="tituloPassword">Restablecer contraseña</h2>
                <p id="subtituloPassword">Cuenta seleccionada</p>
            </div>
            <button type="button" class="tec-modal__close" data-close="modalPassword" aria-label="Cerrar">×</button>
        </header>

        <form id="formPassword" novalidate>
            <input type="hidden" id="passwordTecnicoId" name="tecnico_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="tec-modal__body">
                <div class="tec-security-callout">
                    <strong>Autorización requerida</strong>
                    <p>Confirma tu propia contraseña administrativa antes de cambiar la credencial del técnico.</p>
                </div>

                <label class="tec-form-field" for="passwordActualActor">
                    <span>Tu contraseña actual *</span>
                    <div class="tec-password">
                        <input type="password" id="passwordActualActor" name="password_actual_actor" maxlength="72" autocomplete="current-password" required>
                        <button type="button" data-toggle-password="passwordActualActor">Mostrar</button>
                    </div>
                    <em class="tec-error" data-error-for="password_actual_actor"></em>
                </label>

                <div class="tec-form-grid">
                    <label class="tec-form-field" for="nuevaPassword">
                        <span>Nueva contraseña *</span>
                        <div class="tec-password">
                            <input type="password" id="nuevaPassword" name="nueva_password" minlength="10" maxlength="72" autocomplete="new-password" required>
                            <button type="button" data-toggle-password="nuevaPassword">Mostrar</button>
                        </div>
                        <em class="tec-error" data-error-for="nueva_password"></em>
                    </label>

                    <label class="tec-form-field" for="confirmarNuevaPassword">
                        <span>Confirmar nueva contraseña *</span>
                        <div class="tec-password">
                            <input type="password" id="confirmarNuevaPassword" name="confirmar_nueva_password" minlength="10" maxlength="72" autocomplete="new-password" required>
                            <button type="button" data-toggle-password="confirmarNuevaPassword">Mostrar</button>
                        </div>
                        <em class="tec-error" data-error-for="confirmar_nueva_password"></em>
                    </label>
                </div>

                <div class="tec-password-rules" id="reglasNuevaPassword">
                    <span data-rule="length">10 o más caracteres</span>
                    <span data-rule="lower">Una minúscula</span>
                    <span data-rule="upper">Una mayúscula</span>
                    <span data-rule="number">Un número</span>
                    <span data-rule="match">Coinciden</span>
                </div>
            </div>

            <footer class="tec-modal__footer">
                <button type="button" class="tec-btn tec-btn--ghost" data-close="modalPassword">Cancelar</button>
                <button type="submit" class="tec-btn tec-btn--primary" id="btnGuardarPassword">Actualizar contraseña</button>
            </footer>
        </form>
    </div>
</section>

<!-- Confirmación de estado -->
<section class="tec-modal" id="modalConfirmacion" hidden>
    <div class="tec-modal__dialog tec-modal__dialog--small" role="dialog" aria-modal="true" aria-labelledby="tituloConfirmacion">
        <header class="tec-modal__header">
            <div>
                <p class="tec-eyebrow">CONFIRMACIÓN</p>
                <h2 id="tituloConfirmacion">Confirmar cambio</h2>
                <p id="textoConfirmacion">Revisa la operación antes de continuar.</p>
            </div>
            <button type="button" class="tec-modal__close" data-close="modalConfirmacion" aria-label="Cerrar">×</button>
        </header>
        <footer class="tec-modal__footer tec-modal__footer--alone">
            <button type="button" class="tec-btn tec-btn--ghost" data-close="modalConfirmacion">Cancelar</button>
            <button type="button" class="tec-btn tec-btn--danger" id="btnConfirmarEstado">Confirmar</button>
        </footer>
    </div>
</section>

<div class="tec-toast-region" id="toastRegion" aria-live="polite" aria-atomic="true"></div>

<script>
(function () {
    'use strict';

    const API = 'tecnicos.php?tec_api=1';
    const CSRF = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const state = {
        records: [],
        departments: [],
        page: 1,
        totalPages: 1,
        totalRecords: 0,
        loading: false,
        reloadPending: false,
        saving: false,
        pendingState: null,
        editingRecord: null,
        lastFocused: null
    };

    const el = {
        status: document.getElementById('estadoPagina'),
        loading: document.getElementById('estadoCarga'),
        empty: document.getElementById('estadoVacio'),
        tableWrap: document.getElementById('contenedorTabla'),
        tbody: document.getElementById('tablaTecnicos'),
        pagination: document.getElementById('paginacion'),
        paginationText: document.getElementById('textoPaginacion'),
        pageText: document.getElementById('paginaActual'),
        prev: document.getElementById('btnAnterior'),
        next: document.getElementById('btnSiguiente'),
        resultsText: document.getElementById('textoResultados'),
        updated: document.getElementById('ultimaActualizacion'),
        search: document.getElementById('filtroBusqueda'),
        departmentFilter: document.getElementById('filtroDepartamento'),
        shiftFilter: document.getElementById('filtroTurno'),
        loadFilter: document.getElementById('filtroCarga'),
        statusFilter: document.getElementById('filtroEstado'),
        amount: document.getElementById('filtroCantidad'),
        total: document.getElementById('kpiTotal'),
        active: document.getElementById('kpiActivos'),
        inactive: document.getElementById('kpiInactivos'),
        working: document.getElementById('kpiConTrabajo'),
        form: document.getElementById('formTecnico'),
        technicianId: document.getElementById('tecnicoId'),
        user: document.getElementById('usuario'),
        email: document.getElementById('correo'),
        password: document.getElementById('password'),
        confirmPassword: document.getElementById('confirmarPassword'),
        passwordSection: document.getElementById('seccionPasswordNuevo'),
        personalNumber: document.getElementById('numeroSeccionPersonal'),
        workNumber: document.getElementById('numeroSeccionLaboral'),
        activeWorkWarning: document.getElementById('avisoTrabajoActivo'),
        name: document.getElementById('nombre'),
        lastName: document.getElementById('apellidoPaterno'),
        secondLastName: document.getElementById('apellidoMaterno'),
        phone: document.getElementById('telefono'),
        department: document.getElementById('departamentoId'),
        shift: document.getElementById('turno'),
        specialty: document.getElementById('especialidad'),
        saveButton: document.getElementById('btnGuardar'),
        passwordForm: document.getElementById('formPassword'),
        passwordTechnicianId: document.getElementById('passwordTecnicoId'),
        actorPassword: document.getElementById('passwordActualActor'),
        newPassword: document.getElementById('nuevaPassword'),
        confirmNewPassword: document.getElementById('confirmarNuevaPassword'),
        savePasswordButton: document.getElementById('btnGuardarPassword'),
        confirmState: document.getElementById('btnConfirmarEstado'),
        toast: document.getElementById('toastRegion')
    };

    document.getElementById('btnNuevo').addEventListener('click', openNew);
    document.getElementById('btnActualizar').addEventListener('click', function () { load(false); });
    document.getElementById('btnLimpiar').addEventListener('click', clearFilters);
    el.form.addEventListener('submit', saveTechnician);
    el.passwordForm.addEventListener('submit', saveNewPassword);
    el.confirmState.addEventListener('click', executeStateChange);
    el.tbody.addEventListener('click', handleTableAction);

    el.search.addEventListener('input', debounce(function () {
        state.page = 1;
        load(false);
    }, 350));

    [el.departmentFilter, el.shiftFilter, el.loadFilter, el.statusFilter, el.amount]
        .forEach(function (input) {
            input.addEventListener('change', function () {
                state.page = 1;
                load(false);
            });
        });

    el.prev.addEventListener('click', function () { changePage(-1); });
    el.next.addEventListener('click', function () { changePage(1); });

    document.querySelectorAll('[data-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(button.dataset.close);
        });
    });

    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.togglePassword);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            button.textContent = input.type === 'password' ? 'Mostrar' : 'Ocultar';
        });
    });

    [el.password, el.confirmPassword].forEach(function (input) {
        input.addEventListener('input', function () {
            updatePasswordRules('reglasPassword', el.password.value, el.confirmPassword.value);
        });
    });
    [el.newPassword, el.confirmNewPassword].forEach(function (input) {
        input.addEventListener('input', function () {
            updatePasswordRules('reglasNuevaPassword', el.newPassword.value, el.confirmNewPassword.value);
        });
    });

    el.phone.addEventListener('input', function () {
        el.phone.value = el.phone.value.replace(/\D+/g, '').slice(0, 10);
    });
    el.user.addEventListener('input', function () {
        el.user.value = el.user.value.toLowerCase().replace(/\s+/g, '');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        const open = document.querySelector('.tec-modal.is-open');
        if (open) closeModal(open.id);
    });

    load(false);

    async function load(resetPage) {
        if (state.loading) {
            state.reloadPending = true;
            return;
        }
        if (resetPage) state.page = 1;
        state.loading = true;
        showResultsState('loading');
        el.pagination.hidden = true;
        el.status.hidden = false;
        el.status.classList.remove('is-error');
        el.status.textContent = 'Cargando técnicos...';
        buttonState(document.getElementById('btnActualizar'), true, 'Actualizando...');

        try {
            const params = new URLSearchParams({
                accion: 'LISTAR',
                q: el.search.value.trim(),
                estado: el.statusFilter.value,
                departamento_id: el.departmentFilter.value,
                turno: el.shiftFilter.value,
                carga: el.loadFilter.value,
                pagina: String(state.page),
                por_pagina: el.amount.value
            });
            const data = await request(API + '&' + params.toString());

            state.records = Array.isArray(data.tecnicos) ? data.tecnicos : [];
            state.departments = Array.isArray(data.departamentos) ? data.departamentos : [];
            state.page = Number(data.paginacion && data.paginacion.pagina) || 1;
            state.totalPages = Number(data.paginacion && data.paginacion.total_paginas) || 1;
            state.totalRecords = Number(data.paginacion && data.paginacion.total_registros) || 0;

            paintSummary(data.resumen || {});
            paintDepartmentFilters();
            render(data.paginacion || {});
            el.updated.textContent = 'Actualizado ' + timeLabel(data.fecha_servidor);
            el.status.hidden = true;
        } catch (error) {
            showPageError(error);
        } finally {
            state.loading = false;
            buttonState(document.getElementById('btnActualizar'), false);
            if (state.reloadPending) {
                state.reloadPending = false;
                load(false);
            }
        }
    }

    function paintSummary(summary) {
        el.total.textContent = safeNumber(summary.total);
        el.active.textContent = safeNumber(summary.activos);
        el.inactive.textContent = safeNumber(summary.inactivos);
        el.working.textContent = safeNumber(summary.con_trabajo);
    }

    function paintDepartmentFilters() {
        const current = el.departmentFilter.value;
        el.departmentFilter.innerHTML = '<option value="">Todos</option>';
        state.departments.forEach(function (department) {
            const option = document.createElement('option');
            option.value = String(department.id);
            option.textContent = department.nombre + (Number(department.activo) === 1 ? '' : ' (inactivo)');
            el.departmentFilter.appendChild(option);
        });
        el.departmentFilter.value = current;
    }

    function fillDepartmentForm(selectedId) {
        const selected = selectedId == null ? '' : String(selectedId);
        el.department.innerHTML = '<option value="">Selecciona un departamento</option>';

        state.departments.forEach(function (department) {
            const active = Number(department.activo) === 1;
            if (!active && String(department.id) !== selected) return;

            const option = document.createElement('option');
            option.value = String(department.id);
            option.textContent = department.nombre + (active ? '' : ' (inactivo; selecciona otro)');
            el.department.appendChild(option);
        });

        el.department.value = selected;
    }

    function render(pagination) {
        el.tbody.innerHTML = '';

        if (!state.records.length) {
            showResultsState('empty');
            el.resultsText.textContent = 'No se encontraron técnicos con los filtros actuales.';
            el.pagination.hidden = true;
            return;
        }

        state.records.forEach(function (record) {
            const row = document.createElement('tr');
            row.innerHTML = rowTemplate(record);
            el.tbody.appendChild(row);
        });

        showResultsState('table');
        const start = Number(pagination.inicio) || 0;
        const end = Number(pagination.fin) || 0;
        const total = Number(pagination.total_registros) || 0;
        el.resultsText.textContent = total === 1
            ? '1 técnico encontrado'
            : total + ' técnicos encontrados';
        el.paginationText.textContent = 'Mostrando ' + start + ' a ' + end + ' de ' + total;
        el.pageText.textContent = 'Página ' + state.page + ' de ' + state.totalPages;
        el.prev.disabled = state.page <= 1;
        el.next.disabled = state.page >= state.totalPages;
        el.pagination.hidden = false;
        el.pagination.setAttribute('aria-hidden', 'false');
    }

    function rowTemplate(record) {
        const active = Number(record.activo) === 1;
        const departmentActive = Number(record.departamento_activo) === 1;
        const activeJobs = Number(record.asignaciones_activas) || 0;
        const openExecutions = Number(record.ejecuciones_abiertas) || 0;
        const totalJobs = Number(record.asignaciones_total) || 0;
        const contact = [
            record.telefono ? 'Tel. ' + formatPhone(record.telefono) : '',
            record.correo || ''
        ].filter(Boolean);

        const stateButton = active
            ? '<button type="button" class="is-danger" data-action="state" data-id="' + Number(record.id) + '" data-active="0">Desactivar</button>'
            : '<button type="button" class="is-success" data-action="state" data-id="' + Number(record.id) + '" data-active="1">Reactivar</button>';

        const workloadLabel = openExecutions > 0
            ? '<small class="is-warning">' + openExecutions + ' en ejecución</small>'
            : (activeJobs > 0 ? '<small>' + activeJobs + ' activa' + (activeJobs === 1 ? '' : 's') + '</small>' : '<small>Disponible</small>');

        return '' +
            '<td><div class="tec-person"><span class="tec-avatar">' + escapeHtml(initialsFrom(record.nombre_completo)) + '</span><div><strong>' + escapeHtml(record.nombre_completo) + '</strong><small>@' + escapeHtml(record.usuario) + '</small></div></div></td>' +
            '<td><div class="tec-department"><strong>' + escapeHtml(record.departamento) + '</strong><span>' + escapeHtml(shiftLabel(record.turno)) + '</span><small class="' + (departmentActive ? '' : 'is-warning') + '">' + escapeHtml(record.especialidad || 'Sin especialidad') + (departmentActive ? '' : ' · Departamento inactivo') + '</small></div></td>' +
            '<td><div class="tec-contact">' + (contact.length ? contact.map(function (item) { return '<span>' + escapeHtml(item) + '</span>'; }).join('') : '<span>Sin datos de contacto</span>') + '</div></td>' +
            '<td><div class="tec-request-count"><strong>' + activeJobs + '</strong><span>Activas</span>' + workloadLabel + '<small>' + totalJobs + ' histórica' + (totalJobs === 1 ? '' : 's') + '</small></div></td>' +
            '<td><div class="tec-access"><strong>' + escapeHtml(record.ultimo_acceso_texto) + '</strong><small>Registrado ' + escapeHtml(record.fecha_registro_texto || '—') + '</small></div></td>' +
            '<td><span class="tec-badge ' + (active ? 'tec-badge--active' : 'tec-badge--inactive') + '">' + (active ? 'Activo' : 'Inactivo') + '</span></td>' +
            '<td><div class="tec-actions"><button type="button" data-action="edit" data-id="' + Number(record.id) + '">Editar</button><button type="button" data-action="password" data-id="' + Number(record.id) + '">Contraseña</button>' + stateButton + '</div></td>';
    }

    async function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button || button.disabled || state.saving) return;

        const id = Number(button.dataset.id);
        const record = state.records.find(function (item) { return Number(item.id) === id; });
        if (!record) {
            toast('Actualiza la lista e inténtalo nuevamente.', 'error');
            return;
        }

        if (button.dataset.action === 'edit') {
            await openEdit(record, button);
        } else if (button.dataset.action === 'password') {
            openPassword(record, button);
        } else if (button.dataset.action === 'state') {
            openStateConfirmation(record, Number(button.dataset.active), button);
        }
    }

    function openNew() {
        clearForm(el.form);
        state.editingRecord = null;
        el.technicianId.value = '';
        document.getElementById('etiquetaModal').textContent = 'NUEVO REGISTRO';
        document.getElementById('tituloModal').textContent = 'Nuevo técnico';
        document.getElementById('subtituloModal').textContent = 'Crea una cuenta para atender mantenimientos.';
        el.passwordSection.hidden = false;
        el.password.required = true;
        el.confirmPassword.required = true;
        el.personalNumber.textContent = '03';
        el.workNumber.textContent = '04';
        el.activeWorkWarning.hidden = true;
        el.saveButton.textContent = 'Guardar técnico';
        fillDepartmentForm(null);
        updatePasswordRules('reglasPassword', '', '');
        openModal('modalTecnico', document.getElementById('btnNuevo'));
        setTimeout(function () { el.user.focus(); }, 50);
    }

    async function openEdit(record, button) {
        buttonState(button, true, 'Cargando...');
        try {
            const params = new URLSearchParams({ accion: 'DETALLE', id: String(record.id) });
            const data = await request(API + '&' + params.toString());
            const technician = data.tecnico;

            clearForm(el.form);
            state.editingRecord = technician;
            el.technicianId.value = technician.id;
            el.user.value = technician.usuario || '';
            el.email.value = technician.correo || '';
            el.name.value = technician.nombre || '';
            el.lastName.value = technician.apellido_paterno || '';
            el.secondLastName.value = technician.apellido_materno || '';
            el.phone.value = technician.telefono || '';
            fillDepartmentForm(technician.departamento_id);
            el.shift.value = technician.turno || '';
            el.specialty.value = technician.especialidad || '';

            document.getElementById('etiquetaModal').textContent = 'EDITAR CUENTA';
            document.getElementById('tituloModal').textContent = 'Editar técnico';
            document.getElementById('subtituloModal').textContent = technician.nombre_completo || technician.usuario;
            el.passwordSection.hidden = true;
            el.password.required = false;
            el.confirmPassword.required = false;
            el.personalNumber.textContent = '02';
            el.workNumber.textContent = '03';
            el.activeWorkWarning.hidden = Number(technician.asignaciones_activas) <= 0
                && Number(technician.ejecuciones_abiertas) <= 0;
            el.saveButton.textContent = 'Actualizar técnico';
            openModal('modalTecnico', button);
            setTimeout(function () { el.user.focus(); }, 50);
        } catch (error) {
            toast(error.message || 'No se pudo abrir la cuenta.', 'error');
        } finally {
            buttonState(button, false);
        }
    }

    function openPassword(record, button) {
        clearForm(el.passwordForm);
        el.passwordTechnicianId.value = record.id;
        document.getElementById('subtituloPassword').textContent = record.nombre_completo + ' · @' + record.usuario;
        updatePasswordRules('reglasNuevaPassword', '', '');
        openModal('modalPassword', button);
        setTimeout(function () { el.actorPassword.focus(); }, 50);
    }

    function openStateConfirmation(record, active, button) {
        if (active === 1 && Number(record.puede_reactivar) !== 1) {
            toast('Edita primero la cuenta y completa un departamento activo, turno y especialidad.', 'error');
            return;
        }

        const activeJobs = Number(record.asignaciones_activas) || 0;
        const openExecutions = Number(record.ejecuciones_abiertas) || 0;
        if (active === 0 && (activeJobs > 0 || openExecutions > 0)) {
            toast('No puedes desactivar al técnico mientras tenga mantenimientos activos. Finaliza o reasigna esos trabajos.', 'error');
            return;
        }

        const activating = active === 1;
        document.getElementById('tituloConfirmacion').textContent = activating
            ? '¿Reactivar técnico?'
            : '¿Desactivar técnico?';

        document.getElementById('textoConfirmacion').textContent = activating
            ? record.nombre_completo + ' podrá volver a iniciar sesión y recibir asignaciones.'
            : record.nombre_completo + ' dejará de iniciar sesión y de recibir nuevos trabajos. Su historial permanecerá disponible.';

        el.confirmState.textContent = activating ? 'Sí, reactivar' : 'Sí, desactivar';
        el.confirmState.classList.toggle('tec-btn--danger', !activating);
        el.confirmState.classList.toggle('tec-btn--primary', activating);
        state.pendingState = { record: record, active: active };
        openModal('modalConfirmacion', button);
    }

    async function saveTechnician(event) {
        event.preventDefault();
        if (state.saving || !validateProfileForm()) return;

        state.saving = true;
        clearErrors(el.form);
        buttonState(el.saveButton, true, el.technicianId.value ? 'Actualizando...' : 'Guardando...');

        try {
            const form = new FormData(el.form);
            form.set('accion', 'GUARDAR');
            form.set('csrf_token', CSRF);
            const isNew = !el.technicianId.value;
            const data = await request(API, { method: 'POST', body: form });
            closeModal('modalTecnico');
            state.editingRecord = null;
            if (isNew) state.page = 1;
            await load(false);
            toast(data.mensaje || 'Cuenta guardada.', 'success');
        } catch (error) {
            markServerError(el.form, error);
            toast(error.message || 'No se pudo guardar la cuenta.', 'error');
        } finally {
            state.saving = false;
            buttonState(el.saveButton, false);
        }
    }

    async function saveNewPassword(event) {
        event.preventDefault();
        if (state.saving) return;
        clearErrors(el.passwordForm);

        if (!el.passwordForm.reportValidity()) return;
        if (!isStrongPassword(el.newPassword.value)) {
            setFieldError(el.passwordForm, 'nueva_password', 'La contraseña no cumple todos los requisitos.');
            return;
        }
        if (el.newPassword.value !== el.confirmNewPassword.value) {
            setFieldError(el.passwordForm, 'confirmar_nueva_password', 'Las contraseñas no coinciden.');
            return;
        }

        state.saving = true;
        buttonState(el.savePasswordButton, true, 'Actualizando...');

        try {
            const form = new FormData(el.passwordForm);
            form.set('accion', 'CAMBIAR_PASSWORD');
            form.set('csrf_token', CSRF);
            const data = await request(API, { method: 'POST', body: form });
            closeModal('modalPassword');
            toast(data.mensaje || 'Contraseña actualizada.', 'success');
        } catch (error) {
            markServerError(el.passwordForm, error);
            toast(error.message || 'No se pudo actualizar la contraseña.', 'error');
        } finally {
            state.saving = false;
            buttonState(el.savePasswordButton, false);
        }
    }

    async function executeStateChange() {
        const pending = state.pendingState;
        if (!pending || state.saving) return;

        state.saving = true;
        buttonState(el.confirmState, true, 'Procesando...');

        try {
            const form = new FormData();
            form.set('accion', 'CAMBIAR_ESTADO');
            form.set('csrf_token', CSRF);
            form.set('tecnico_id', String(pending.record.id));
            form.set('activo', String(pending.active));
            const data = await request(API, { method: 'POST', body: form });
            closeModal('modalConfirmacion');
            state.pendingState = null;
            await load(false);
            toast(data.mensaje || 'Estado actualizado.', 'success');
        } catch (error) {
            toast(error.message || 'No se pudo cambiar el estado.', 'error');
        } finally {
            state.saving = false;
            buttonState(el.confirmState, false);
        }
    }

    function validateProfileForm() {
        clearErrors(el.form);
        if (!el.form.reportValidity()) return false;

        const user = el.user.value.trim();
        if (!/^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$/.test(user) || /[._-]{2,}/.test(user)) {
            setFieldError(el.form, 'usuario', 'Revisa el formato del usuario.');
            return false;
        }

        const phone = el.phone.value.replace(/\D+/g, '');
        if (phone && phone.length !== 10) {
            setFieldError(el.form, 'telefono', 'El teléfono debe contener 10 dígitos.');
            return false;
        }

        const selectedDepartment = state.departments.find(function (department) {
            return String(department.id) === el.department.value;
        });
        if (!selectedDepartment || Number(selectedDepartment.activo) !== 1) {
            setFieldError(el.form, 'departamento_id', 'Selecciona un departamento activo.');
            return false;
        }

        if (!['MATUTINO', 'VESPERTINO', 'NOCTURNO'].includes(el.shift.value)) {
            setFieldError(el.form, 'turno', 'Selecciona un turno válido.');
            return false;
        }

        if (el.specialty.value.trim().length < 2) {
            setFieldError(el.form, 'especialidad', 'Escribe una especialidad válida.');
            return false;
        }

        if (state.editingRecord) {
            const hasActiveWork = Number(state.editingRecord.asignaciones_activas) > 0
                || Number(state.editingRecord.ejecuciones_abiertas) > 0;
            const operationalChange = String(state.editingRecord.departamento_id || '') !== el.department.value
                || String(state.editingRecord.turno || '') !== el.shift.value;
            if (hasActiveWork && operationalChange) {
                toast('No puedes cambiar el departamento o turno mientras el técnico tenga trabajo activo.', 'error');
                return false;
            }
        }

        if (!el.technicianId.value) {
            if (!isStrongPassword(el.password.value)) {
                setFieldError(el.form, 'password', 'La contraseña no cumple todos los requisitos.');
                return false;
            }
            if (el.password.value !== el.confirmPassword.value) {
                setFieldError(el.form, 'confirmar_password', 'Las contraseñas no coinciden.');
                return false;
            }
        }

        return true;
    }

    async function request(url, options) {
        const response = await fetch(url, Object.assign({
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }, options || {}));

        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Respuesta no JSON del módulo Técnicos:', text.slice(0, 1500));
            throw new Error(
                'El servidor no pudo completar la consulta del técnico. ' +
                'La respuesta fue registrada en la consola del navegador.'
            );
        }

        if (!response.ok || data.success === false) {
            if (data.redirect && data.sesion_expirada) {
                window.location.href = data.redirect;
            }
            const error = new Error(data.mensaje || 'No fue posible completar la operación.');
            error.data = data;
            throw error;
        }

        return data;
    }

    function showResultsState(stateName) {
        const states = {
            loading: el.loading,
            empty: el.empty,
            table: el.tableWrap
        };

        Object.keys(states).forEach(function (name) {
            const element = states[name];
            const visible = name === stateName;
            element.hidden = !visible;
            element.setAttribute('aria-hidden', visible ? 'false' : 'true');
            element.classList.toggle('is-visible', visible);
        });
    }

    function showPageError(error) {
        state.records = [];
        showResultsState('empty');
        el.empty.querySelector('h3').textContent = 'No se pudo cargar la lista';
        el.empty.querySelector('p').textContent = 'Actualiza la página o revisa el mensaje mostrado arriba.';
        el.pagination.hidden = true;
        el.status.hidden = false;
        el.status.classList.add('is-error');
        el.status.textContent = error.message || 'No se pudo cargar la información.';
        el.resultsText.textContent = 'No se pudieron cargar los técnicos.';
        toast(error.message || 'No se pudo cargar la información.', 'error');
    }

    function clearFilters() {
        el.search.value = '';
        el.departmentFilter.value = '';
        el.shiftFilter.value = 'TODOS';
        el.loadFilter.value = 'TODOS';
        el.statusFilter.value = 'TODOS';
        el.amount.value = '10';
        state.page = 1;
        load(false);
    }

    function changePage(delta) {
        const nextPage = state.page + delta;
        if (nextPage < 1 || nextPage > state.totalPages || state.loading) return;
        state.page = nextPage;
        load(false);
        document.querySelector('.tec-results').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function openModal(id, trigger) {
        const modal = document.getElementById(id);
        if (!modal) return;
        state.lastFocused = trigger || document.activeElement;
        modal.hidden = false;
        requestAnimationFrame(function () { modal.classList.add('is-open'); });
        document.body.classList.add('tec-modal-open');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal || modal.hidden) return;
        modal.classList.remove('is-open');
        setTimeout(function () {
            modal.hidden = true;
            if (!document.querySelector('.tec-modal.is-open')) {
                document.body.classList.remove('tec-modal-open');
            }
            if (state.lastFocused && typeof state.lastFocused.focus === 'function') {
                state.lastFocused.focus();
            }
        }, 150);
    }

    function clearForm(form) {
        form.reset();
        clearErrors(form);
        form.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            const input = document.getElementById(button.dataset.togglePassword);
            if (input) input.type = 'password';
            button.textContent = 'Mostrar';
        });
    }

    function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach(function (input) { input.classList.remove('is-invalid'); });
        form.querySelectorAll('.tec-error').forEach(function (error) { error.textContent = ''; });
    }

    function markServerError(form, error) {
        const field = error && error.data && error.data.campo;
        if (field) setFieldError(form, field, error.message || 'Revisa este campo.');
    }

    function setFieldError(form, field, message) {
        const input = form.querySelector('[name="' + cssEscape(field) + '"]');
        const error = form.querySelector('[data-error-for="' + cssEscape(field) + '"]');
        if (input) {
            input.classList.add('is-invalid');
            input.focus();
        }
        if (error) error.textContent = message;
    }

    function updatePasswordRules(containerId, password, confirmation) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const rules = {
            length: password.length >= 10 && password.length <= 72,
            lower: /[a-z]/.test(password),
            upper: /[A-Z]/.test(password),
            number: /\d/.test(password),
            match: password.length > 0 && password === confirmation
        };
        Object.keys(rules).forEach(function (key) {
            const item = container.querySelector('[data-rule="' + key + '"]');
            if (item) item.classList.toggle('is-valid', rules[key]);
        });
    }

    function isStrongPassword(password) {
        return password.length >= 10 && password.length <= 72
            && /[a-z]/.test(password) && /[A-Z]/.test(password) && /\d/.test(password);
    }

    function buttonState(button, active, text) {
        if (!button) return;
        if (active) {
            button.dataset.originalText = button.textContent;
            button.disabled = true;
            if (text) button.textContent = text;
        } else {
            button.disabled = false;
            if (button.dataset.originalText) {
                button.textContent = button.dataset.originalText;
                delete button.dataset.originalText;
            }
        }
    }

    function toast(message, type) {
        const item = document.createElement('div');
        item.className = 'tec-toast tec-toast--' + (type || 'info');
        item.textContent = message;
        el.toast.appendChild(item);
        requestAnimationFrame(function () { item.classList.add('is-visible'); });
        setTimeout(function () {
            item.classList.remove('is-visible');
            setTimeout(function () { item.remove(); }, 200);
        }, 4200);
    }

    function debounce(fn, wait) {
        let timer;
        return function () {
            const args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, wait);
        };
    }

    function formatPhone(value) {
        const digits = String(value || '').replace(/\D+/g, '');
        if (digits.length !== 10) return value || '';
        return digits.slice(0, 3) + ' ' + digits.slice(3, 6) + ' ' + digits.slice(6);
    }

    function shiftLabel(value) {
        const labels = {
            MATUTINO: 'Turno matutino',
            VESPERTINO: 'Turno vespertino',
            NOCTURNO: 'Turno nocturno'
        };
        return labels[String(value || '').toUpperCase()] || 'Turno sin definir';
    }

    function timeLabel(value) {
        const date = value ? new Date(String(value).replace(' ', 'T')) : new Date();
        if (Number.isNaN(date.getTime())) return 'recientemente';
        return date.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function cssEscape(value) {
        return window.CSS && typeof window.CSS.escape === 'function'
            ? window.CSS.escape(value)
            : String(value).replace(/["\\]/g, '\\$&');
    }

    function safeNumber(value) {
        const number = Number(value);
        return Number.isFinite(number) ? number.toLocaleString('es-MX') : '0';
    }

    function initialsFrom(value) {
        const words = String(value || '').trim().split(/\s+/).filter(Boolean);
        return words.slice(0, 2).map(function (word) { return word.charAt(0).toUpperCase(); }).join('') || 'T';
    }
})();
</script>
</body>
</html>