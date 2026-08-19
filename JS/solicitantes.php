<?php

declare(strict_types=1);

/*
 * La interfaz consulta esta misma página mediante ?sol_api=1 para evitar
 * rutas relativas frágiles hacia la carpeta funciones.
 */
if (isset($_GET['sol_api'])) {
    $endpoint = __DIR__ . '/../funciones/solicitantes_funciones.php';

    if (!is_file($endpoint)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode(
            [
                'success' => false,
                'mensaje' => 'No se encontró funciones/solicitantes_funciones.php. Copia juntos los tres archivos del módulo.',
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

$cssPath = __DIR__ . '/../css/style_solicitantes.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '3.0';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b2944">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Gestión segura de cuentas solicitantes del Sistema de Mantenimiento.">
    <title>Solicitantes | Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="../css/style_solicitantes.css?v=<?= rawurlencode($cssVersion) ?>">
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="sol-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="sol-icon-users" viewBox="0 0 24 24">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
    </symbol>
    <symbol id="sol-icon-user-plus" viewBox="0 0 24 24">
        <path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="8" cy="7" r="4"/>
        <path d="M19 8v6M16 11h6"/>
    </symbol>
    <symbol id="sol-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="sol-icon-building" viewBox="0 0 24 24">
        <path d="M3 21h18M5 21V5l7-3 7 3v16M9 9h.01M15 9h.01M9 13h.01M15 13h.01M9 17h.01M15 17h.01"/>
    </symbol>
    <symbol id="sol-icon-shield" viewBox="0 0 24 24">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
        <path d="m9 12 2 2 4-4"/>
    </symbol>
    <symbol id="sol-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="sol-icon-list" viewBox="0 0 24 24">
        <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
    </symbol>
    <symbol id="sol-icon-check" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8 12 2.6 2.6L16.5 9"/>
    </symbol>
    <symbol id="sol-icon-user-x" viewBox="0 0 24 24">
        <path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="8" cy="7" r="4"/>
        <path d="m18 8 5 5M23 8l-5 5"/>
    </symbol>
    <symbol id="sol-icon-login" viewBox="0 0 24 24">
        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>
    </symbol>
    <symbol id="sol-icon-filter" viewBox="0 0 24 24">
        <path d="M4 5h16M7 12h10M10 19h4"/>
    </symbol>
</svg>

<main class="sol-page">
    <header class="sol-heading" aria-labelledby="tituloSolicitantes">
        <div class="sol-heading__pattern" aria-hidden="true"></div>

        <div class="sol-heading__content">
            <div class="sol-heading__copy">
                <p class="sol-eyebrow">
                    <span class="sol-eyebrow__icon"><svg><use href="#sol-icon-users"></use></svg></span>
                    Gestión de personal
                </p>
                <h1 id="tituloSolicitantes">Solicitantes</h1>
                <p>
                    Administra a las personas autorizadas para reportar necesidades de mantenimiento,
                    controla su departamento y conserva intacto el historial de cada cuenta.
                </p>

                <div class="sol-heading__meta">
                    <span><i class="sol-live-dot" aria-hidden="true"></i> Acceso departamental protegido</span>
                    <span>Administrador: <strong><?= htmlspecialchars($nombreAdmin, ENT_QUOTES, 'UTF-8') ?></strong></span>
                </div>
            </div>

            <div class="sol-heading__actions" aria-label="Acciones de solicitantes">
                <button type="button" class="sol-btn sol-btn--secondary" id="btnActualizar">
                    <svg><use href="#sol-icon-refresh"></use></svg>
                    <span>Actualizar</span>
                </button>
                <button type="button" class="sol-btn sol-btn--primary" id="btnNuevo">
                    <svg><use href="#sol-icon-user-plus"></use></svg>
                    <span>Nuevo solicitante</span>
                </button>
            </div>

            <div class="sol-heading__mini-card" aria-hidden="true">
                <span><svg><use href="#sol-icon-building"></use></svg></span>
                <div>
                    <small>Centro de solicitudes</small>
                    <strong>Personas, departamentos e historial protegido</strong>
                </div>
            </div>
        </div>
    </header>

    <section class="sol-security-note" aria-label="Reglas del módulo">
        <span class="sol-security-note__icon"><svg><use href="#sol-icon-shield"></use></svg></span>
        <div>
            <strong>Vinculación departamental y trazabilidad</strong>
            <p>
                Las cuentas se desactivan, no se eliminan. Un solicitante activo debe pertenecer a un
                departamento activo y sus reportes anteriores permanecen intactos ante cualquier cambio.
            </p>
        </div>
        <span class="sol-security-note__badge">Historial protegido</span>
    </section>

    <div class="sol-status" id="estadoPagina" role="status" aria-live="polite">
        <span class="sol-spinner sol-spinner--small" aria-hidden="true"></span>
        <span>Cargando solicitantes...</span>
    </div>

    <section class="sol-kpis" aria-label="Resumen de solicitantes">
        <article class="sol-kpi sol-kpi--total">
            <span class="sol-kpi__icon"><svg><use href="#sol-icon-users"></use></svg></span>
            <span class="sol-kpi__body">
                <span>Total</span>
                <strong id="kpiTotal">0</strong>
                <small>Cuentas registradas</small>
            </span>
        </article>
        <article class="sol-kpi sol-kpi--active">
            <span class="sol-kpi__icon"><svg><use href="#sol-icon-check"></use></svg></span>
            <span class="sol-kpi__body">
                <span>Activos</span>
                <strong id="kpiActivos">0</strong>
                <small>Con acceso al sistema</small>
            </span>
        </article>
        <article class="sol-kpi sol-kpi--inactive">
            <span class="sol-kpi__icon"><svg><use href="#sol-icon-user-x"></use></svg></span>
            <span class="sol-kpi__body">
                <span>Inactivos</span>
                <strong id="kpiInactivos">0</strong>
                <small>Conservados por historial</small>
            </span>
        </article>
        <article class="sol-kpi sol-kpi--neutral">
            <span class="sol-kpi__icon"><svg><use href="#sol-icon-login"></use></svg></span>
            <span class="sol-kpi__body">
                <span>Sin ingreso</span>
                <strong id="kpiSinAcceso">0</strong>
                <small>Nunca han iniciado sesión</small>
            </span>
        </article>
    </section>

    <section class="sol-card sol-filters-card" aria-labelledby="tituloFiltrosSolicitantes">
        <header class="sol-section-head">
            <div>
                <p class="sol-eyebrow">Búsqueda y filtros</p>
                <h2 id="tituloFiltrosSolicitantes">Encuentra una cuenta</h2>
                <p>Busca por identidad o contacto y filtra por departamento, estado y cantidad de registros.</p>
            </div>
            <span class="sol-section-head__chip"><svg><use href="#sol-icon-filter"></use></svg> Consulta local</span>
        </header>

        <div class="sol-filters sol-filters--solicitantes">
            <label class="sol-field sol-field--search" for="filtroBusqueda">
                <span>Buscar</span>
                <div class="sol-search">
                    <span aria-hidden="true"><svg><use href="#sol-icon-search"></use></svg></span>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        maxlength="120"
                        placeholder="Nombre, usuario, teléfono o correo"
                        autocomplete="off"
                    >
                </div>
            </label>

            <label class="sol-field" for="filtroDepartamento">
                <span>Departamento</span>
                <select id="filtroDepartamento">
                    <option value="">Todos</option>
                </select>
            </label>

            <label class="sol-field" for="filtroEstado">
                <span>Estado</span>
                <select id="filtroEstado">
                    <option value="TODOS">Todos</option>
                    <option value="ACTIVO">Activos</option>
                    <option value="INACTIVO">Inactivos</option>
                    <option value="SIN_ACCESO">Sin ingreso</option>
                </select>
            </label>

            <label class="sol-field sol-field--small" for="filtroCantidad">
                <span>Mostrar</span>
                <select id="filtroCantidad">
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                    <option value="40">40</option>
                    <option value="80">80</option>
                </select>
            </label>

            <div class="sol-filter-actions">
                <button type="button" class="sol-btn sol-btn--ghost" id="btnLimpiar">Limpiar</button>
            </div>
        </div>
    </section>

    <section class="sol-card sol-results sol-results-card" aria-labelledby="tituloListadoSolicitantes">
        <header class="sol-results__head">
            <div>
                <p class="sol-eyebrow">Resultados</p>
                <h2 id="tituloListadoSolicitantes">Cuentas solicitantes</h2>
                <p id="textoResultados">Preparando resultados...</p>
            </div>

            <div class="sol-results__tools">
                <span class="sol-updated" id="ultimaActualizacion">Sin actualizar</span>
                <span class="sol-results__badge"><svg><use href="#sol-icon-list"></use></svg> Listado protegido</span>
            </div>
        </header>

        <div class="sol-loading" id="estadoCarga">
            <span class="sol-spinner" aria-hidden="true"></span>
            <strong>Cargando solicitantes...</strong>
        </div>

        <div class="sol-empty" id="estadoVacio" hidden>
            <span aria-hidden="true"><svg><use href="#sol-icon-search"></use></svg></span>
            <h3>No hay coincidencias</h3>
            <p>Prueba con otro nombre o modifica los filtros aplicados.</p>
        </div>

        <div class="sol-table-wrap" id="contenedorTabla" hidden tabindex="0" aria-label="Listado desplazable de solicitantes">
            <table class="sol-table">
                <thead>
                    <tr>
                        <th>Solicitante</th>
                        <th>Departamento</th>
                        <th>Contacto</th>
                        <th>Solicitudes</th>
                        <th>Último acceso</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaSolicitantes"></tbody>
            </table>
        </div>

        <footer class="sol-pagination" id="paginacion" hidden>
            <span id="textoPaginacion">Sin resultados</span>
            <div class="sol-pagination__buttons">
                <button type="button" id="btnAnterior">Anterior</button>
                <span id="paginaActual">Página 1</span>
                <button type="button" id="btnSiguiente">Siguiente</button>
            </div>
        </footer>
    </section>

    <footer class="sol-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Gestión de solicitantes protegida · Los Chapeteados División Petfood</span>
    </footer>

    <div class="sol-tools-background" aria-hidden="true"></div>
</main>

<!-- Alta y edición -->
<section class="sol-modal" id="modalSolicitante" hidden>
    <div class="sol-modal__backdrop" aria-hidden="true"></div>
    <div class="sol-modal__dialog sol-modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="tituloModal">
        <header class="sol-modal__header">
            <div>
                <p class="sol-eyebrow" id="etiquetaModal">NUEVO REGISTRO</p>
                <h2 id="tituloModal">Nuevo solicitante</h2>
                <p id="subtituloModal">Crea una cuenta para registrar solicitudes.</p>
            </div>
            <button type="button" class="sol-modal__close" data-close="modalSolicitante" aria-label="Cerrar">×</button>
        </header>

        <form id="formSolicitante" novalidate>
            <input type="hidden" id="solicitanteId" name="solicitante_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="sol-modal__body">
                <section class="sol-form-section">
                    <header>
                        <span>01</span>
                        <div>
                            <h3>Datos de acceso</h3>
                            <p>El usuario y el correo deben ser únicos en todo el sistema.</p>
                        </div>
                    </header>

                    <div class="sol-form-grid">
                        <label class="sol-form-field" for="usuario">
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
                                placeholder="Ej. produccion.solicita"
                                required
                            >
                            <small>Letras minúsculas, números, punto, guion o guion bajo.</small>
                            <em class="sol-error" data-error-for="usuario"></em>
                        </label>

                        <label class="sol-form-field" for="correo">
                            <span>Correo electrónico</span>
                            <input type="email" id="correo" name="correo" maxlength="150" autocomplete="email" placeholder="persona@empresa.com">
                            <small>Opcional, pero no puede repetirse.</small>
                            <em class="sol-error" data-error-for="correo"></em>
                        </label>
                    </div>
                </section>

                <section class="sol-form-section" id="seccionPasswordNuevo">
                    <header>
                        <span>02</span>
                        <div>
                            <h3>Contraseña inicial</h3>
                            <p>Después podrá restablecerse desde la lista de solicitantes.</p>
                        </div>
                    </header>

                    <div class="sol-form-grid">
                        <label class="sol-form-field" for="password">
                            <span>Contraseña *</span>
                            <div class="sol-password">
                                <input type="password" id="password" name="password" minlength="10" maxlength="72" autocomplete="new-password">
                                <button type="button" data-toggle-password="password">Mostrar</button>
                            </div>
                            <small>Mínimo 10 caracteres, mayúscula, minúscula y número.</small>
                            <em class="sol-error" data-error-for="password"></em>
                        </label>

                        <label class="sol-form-field" for="confirmarPassword">
                            <span>Confirmar contraseña *</span>
                            <div class="sol-password">
                                <input type="password" id="confirmarPassword" name="confirmar_password" minlength="10" maxlength="72" autocomplete="new-password">
                                <button type="button" data-toggle-password="confirmarPassword">Mostrar</button>
                            </div>
                            <em class="sol-error" data-error-for="confirmar_password"></em>
                        </label>
                    </div>

                    <div class="sol-password-rules" id="reglasPassword">
                        <span data-rule="length">10 o más caracteres</span>
                        <span data-rule="lower">Una minúscula</span>
                        <span data-rule="upper">Una mayúscula</span>
                        <span data-rule="number">Un número</span>
                        <span data-rule="match">Coinciden</span>
                    </div>
                </section>

                <section class="sol-form-section">
                    <header>
                        <span id="numeroSeccionPerfil">03</span>
                        <div>
                            <h3>Perfil y departamento</h3>
                            <p>Información utilizada para identificar al solicitante y su área habitual.</p>
                        </div>
                    </header>

                    <div class="sol-form-grid sol-form-grid--three">
                        <label class="sol-form-field" for="nombre">
                            <span>Nombre *</span>
                            <input type="text" id="nombre" name="nombre" minlength="2" maxlength="100" autocomplete="given-name" required>
                            <em class="sol-error" data-error-for="nombre"></em>
                        </label>

                        <label class="sol-form-field" for="apellidoPaterno">
                            <span>Apellido paterno</span>
                            <input type="text" id="apellidoPaterno" name="apellido_paterno" maxlength="100" autocomplete="family-name">
                            <em class="sol-error" data-error-for="apellido_paterno"></em>
                        </label>

                        <label class="sol-form-field" for="apellidoMaterno">
                            <span>Apellido materno</span>
                            <input type="text" id="apellidoMaterno" name="apellido_materno" maxlength="100">
                            <em class="sol-error" data-error-for="apellido_materno"></em>
                        </label>

                        <label class="sol-form-field" for="telefono">
                            <span>Teléfono</span>
                            <input type="tel" id="telefono" name="telefono" inputmode="numeric" maxlength="14" autocomplete="tel" placeholder="10 dígitos">
                            <em class="sol-error" data-error-for="telefono"></em>
                        </label>

                        <label class="sol-form-field sol-form-field--span-two" for="departamentoId">
                            <span>Departamento *</span>
                            <select id="departamentoId" name="departamento_id" required>
                                <option value="">Selecciona un departamento</option>
                            </select>
                            <small>Determina el departamento habitual desde el que registra solicitudes.</small>
                            <em class="sol-error" data-error-for="departamento_id"></em>
                        </label>
                    </div>
                </section>
            </div>

            <footer class="sol-modal__footer">
                <button type="button" class="sol-btn sol-btn--ghost" data-close="modalSolicitante">Cancelar</button>
                <button type="submit" class="sol-btn sol-btn--primary" id="btnGuardar">Guardar solicitante</button>
            </footer>
        </form>
    </div>
</section>

<!-- Restablecimiento de contraseña -->
<section class="sol-modal" id="modalPassword" hidden>
    <div class="sol-modal__backdrop" aria-hidden="true"></div>
    <div class="sol-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tituloPassword">
        <header class="sol-modal__header">
            <div>
                <p class="sol-eyebrow">SEGURIDAD</p>
                <h2 id="tituloPassword">Restablecer contraseña</h2>
                <p id="subtituloPassword">Cuenta seleccionada</p>
            </div>
            <button type="button" class="sol-modal__close" data-close="modalPassword" aria-label="Cerrar">×</button>
        </header>

        <form id="formPassword" novalidate>
            <input type="hidden" id="passwordSolicitanteId" name="solicitante_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="sol-modal__body">
                <div class="sol-security-callout">
                    <strong>Autorización requerida</strong>
                    <p>Confirma tu propia contraseña administrativa antes de cambiar la credencial de otra persona.</p>
                </div>

                <label class="sol-form-field" for="passwordActualActor">
                    <span>Tu contraseña actual *</span>
                    <div class="sol-password">
                        <input type="password" id="passwordActualActor" name="password_actual_actor" maxlength="72" autocomplete="current-password" required>
                        <button type="button" data-toggle-password="passwordActualActor">Mostrar</button>
                    </div>
                    <em class="sol-error" data-error-for="password_actual_actor"></em>
                </label>

                <div class="sol-form-grid">
                    <label class="sol-form-field" for="nuevaPassword">
                        <span>Nueva contraseña *</span>
                        <div class="sol-password">
                            <input type="password" id="nuevaPassword" name="nueva_password" minlength="10" maxlength="72" autocomplete="new-password" required>
                            <button type="button" data-toggle-password="nuevaPassword">Mostrar</button>
                        </div>
                        <em class="sol-error" data-error-for="nueva_password"></em>
                    </label>

                    <label class="sol-form-field" for="confirmarNuevaPassword">
                        <span>Confirmar nueva contraseña *</span>
                        <div class="sol-password">
                            <input type="password" id="confirmarNuevaPassword" name="confirmar_nueva_password" minlength="10" maxlength="72" autocomplete="new-password" required>
                            <button type="button" data-toggle-password="confirmarNuevaPassword">Mostrar</button>
                        </div>
                        <em class="sol-error" data-error-for="confirmar_nueva_password"></em>
                    </label>
                </div>

                <div class="sol-password-rules" id="reglasNuevaPassword">
                    <span data-rule="length">10 o más caracteres</span>
                    <span data-rule="lower">Una minúscula</span>
                    <span data-rule="upper">Una mayúscula</span>
                    <span data-rule="number">Un número</span>
                    <span data-rule="match">Coinciden</span>
                </div>
            </div>

            <footer class="sol-modal__footer">
                <button type="button" class="sol-btn sol-btn--ghost" data-close="modalPassword">Cancelar</button>
                <button type="submit" class="sol-btn sol-btn--primary" id="btnGuardarPassword">Actualizar contraseña</button>
            </footer>
        </form>
    </div>
</section>

<!-- Confirmación de estado -->
<section class="sol-modal" id="modalConfirmacion" hidden>
    <div class="sol-modal__backdrop" aria-hidden="true"></div>
    <div class="sol-modal__dialog sol-modal__dialog--small" role="dialog" aria-modal="true" aria-labelledby="tituloConfirmacion">
        <header class="sol-modal__header">
            <div>
                <p class="sol-eyebrow">CONFIRMACIÓN</p>
                <h2 id="tituloConfirmacion">Confirmar cambio</h2>
                <p id="textoConfirmacion">Revisa la operación antes de continuar.</p>
            </div>
            <button type="button" class="sol-modal__close" data-close="modalConfirmacion" aria-label="Cerrar">×</button>
        </header>
        <footer class="sol-modal__footer sol-modal__footer--alone">
            <button type="button" class="sol-btn sol-btn--ghost" data-close="modalConfirmacion">Cancelar</button>
            <button type="button" class="sol-btn sol-btn--danger" id="btnConfirmarEstado">Confirmar</button>
        </footer>
    </div>
</section>

<div class="sol-toast-region" id="toastRegion" aria-live="polite" aria-atomic="true"></div>

<script>
(function () {
    'use strict';

    const API = 'solicitantes.php?sol_api=1';
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
        lastFocused: null
    };

    const el = {
        status: document.getElementById('estadoPagina'),
        loading: document.getElementById('estadoCarga'),
        empty: document.getElementById('estadoVacio'),
        tableWrap: document.getElementById('contenedorTabla'),
        tbody: document.getElementById('tablaSolicitantes'),
        pagination: document.getElementById('paginacion'),
        paginationText: document.getElementById('textoPaginacion'),
        pageText: document.getElementById('paginaActual'),
        prev: document.getElementById('btnAnterior'),
        next: document.getElementById('btnSiguiente'),
        resultsText: document.getElementById('textoResultados'),
        updated: document.getElementById('ultimaActualizacion'),
        search: document.getElementById('filtroBusqueda'),
        departmentFilter: document.getElementById('filtroDepartamento'),
        statusFilter: document.getElementById('filtroEstado'),
        amount: document.getElementById('filtroCantidad'),
        total: document.getElementById('kpiTotal'),
        active: document.getElementById('kpiActivos'),
        inactive: document.getElementById('kpiInactivos'),
        never: document.getElementById('kpiSinAcceso'),
        form: document.getElementById('formSolicitante'),
        requesterId: document.getElementById('solicitanteId'),
        user: document.getElementById('usuario'),
        email: document.getElementById('correo'),
        password: document.getElementById('password'),
        confirmPassword: document.getElementById('confirmarPassword'),
        passwordSection: document.getElementById('seccionPasswordNuevo'),
        profileNumber: document.getElementById('numeroSeccionPerfil'),
        name: document.getElementById('nombre'),
        lastName: document.getElementById('apellidoPaterno'),
        secondLastName: document.getElementById('apellidoMaterno'),
        phone: document.getElementById('telefono'),
        department: document.getElementById('departamentoId'),
        saveButton: document.getElementById('btnGuardar'),
        passwordForm: document.getElementById('formPassword'),
        passwordRequesterId: document.getElementById('passwordSolicitanteId'),
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
    el.form.addEventListener('submit', saveRequester);
    el.passwordForm.addEventListener('submit', saveNewPassword);
    el.confirmState.addEventListener('click', executeStateChange);
    el.tbody.addEventListener('click', handleTableAction);

    el.search.addEventListener('input', debounce(function () {
        state.page = 1;
        load(false);
    }, 350));
    el.departmentFilter.addEventListener('change', function () {
        state.page = 1;
        load(false);
    });
    el.statusFilter.addEventListener('change', function () {
        state.page = 1;
        load(false);
    });
    el.amount.addEventListener('change', function () {
        state.page = 1;
        load(false);
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
        const open = document.querySelector('.sol-modal.is-open');
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
        el.status.textContent = 'Cargando solicitantes...';
        buttonState(document.getElementById('btnActualizar'), true, 'Actualizando...');

        try {
            const params = new URLSearchParams({
                accion: 'LISTAR',
                q: el.search.value.trim(),
                estado: el.statusFilter.value,
                departamento_id: el.departmentFilter.value,
                pagina: String(state.page),
                por_pagina: el.amount.value
            });
            const data = await request(API + '&' + params.toString());

            state.records = Array.isArray(data.solicitantes) ? data.solicitantes : [];
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
            if (!el.loading.hidden && state.records.length === 0) {
                showResultsState('empty');
            }
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
        el.never.textContent = safeNumber(summary.sin_acceso);
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
            el.resultsText.textContent = 'No se encontraron solicitantes con los filtros actuales.';
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
            ? '1 cuenta encontrada'
            : total + ' cuentas encontradas';
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
        const totalRequests = Number(record.solicitudes_total) || 0;
        const openRequests = Number(record.solicitudes_abiertas) || 0;
        const contact = [
            record.telefono ? 'Tel. ' + formatPhone(record.telefono) : '',
            record.correo || ''
        ].filter(Boolean);

        const stateButton = active
            ? '<button type="button" class="is-danger" data-action="state" data-id="' + Number(record.id) + '" data-active="0">Desactivar</button>'
            : '<button type="button" class="is-success" data-action="state" data-id="' + Number(record.id) + '" data-active="1">Reactivar</button>';

        return '' +
            '<td><div class="sol-person"><span>' + escapeHtml(initialsFrom(record.nombre_completo)) + '</span><div><strong>' + escapeHtml(record.nombre_completo) + '</strong><small>@' + escapeHtml(record.usuario) + '</small></div></div></td>' +
            '<td><div class="sol-department"><strong>' + escapeHtml(record.departamento) + '</strong><small class="' + (departmentActive ? '' : 'is-warning') + '">' + (departmentActive ? 'Departamento activo' : 'Departamento inactivo') + '</small></div></td>' +
            '<td><div class="sol-contact">' + (contact.length ? contact.map(function (item) { return '<span>' + escapeHtml(item) + '</span>'; }).join('') : '<span>Sin datos de contacto</span>') + '</div></td>' +
            '<td><div class="sol-request-count"><strong>' + totalRequests + '</strong><span>Total</span>' + (openRequests > 0 ? '<small>' + openRequests + ' abierta' + (openRequests === 1 ? '' : 's') + '</small>' : '<small>Sin abiertas</small>') + '</div></td>' +
            '<td><div class="sol-access"><strong>' + escapeHtml(record.ultimo_acceso_texto) + '</strong><small>Registrado ' + escapeHtml(record.fecha_registro_texto || '—') + '</small></div></td>' +
            '<td><span class="sol-badge ' + (active ? 'sol-badge--active' : 'sol-badge--inactive') + '">' + (active ? 'Activo' : 'Inactivo') + '</span></td>' +
            '<td><div class="sol-actions"><button type="button" data-action="edit" data-id="' + Number(record.id) + '">Editar</button><button type="button" data-action="password" data-id="' + Number(record.id) + '">Contraseña</button>' + stateButton + '</div></td>';
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
        el.requesterId.value = '';
        document.getElementById('etiquetaModal').textContent = 'NUEVO REGISTRO';
        document.getElementById('tituloModal').textContent = 'Nuevo solicitante';
        document.getElementById('subtituloModal').textContent = 'Crea una cuenta para registrar solicitudes.';
        el.passwordSection.hidden = false;
        el.password.required = true;
        el.confirmPassword.required = true;
        el.profileNumber.textContent = '03';
        el.saveButton.textContent = 'Guardar solicitante';
        fillDepartmentForm(null);
        updatePasswordRules('reglasPassword', '', '');
        openModal('modalSolicitante', document.getElementById('btnNuevo'));
        setTimeout(function () { el.user.focus(); }, 50);
    }

    async function openEdit(record, button) {
        buttonState(button, true, 'Cargando...');
        try {
            const params = new URLSearchParams({ accion: 'DETALLE', id: String(record.id) });
            const data = await request(API + '&' + params.toString());
            const requester = data.solicitante;

            clearForm(el.form);
            el.requesterId.value = requester.id;
            el.user.value = requester.usuario || '';
            el.email.value = requester.correo || '';
            el.name.value = requester.nombre || '';
            el.lastName.value = requester.apellido_paterno || '';
            el.secondLastName.value = requester.apellido_materno || '';
            el.phone.value = requester.telefono || '';
            fillDepartmentForm(requester.departamento_id);

            document.getElementById('etiquetaModal').textContent = 'EDITAR CUENTA';
            document.getElementById('tituloModal').textContent = 'Editar solicitante';
            document.getElementById('subtituloModal').textContent = requester.nombre_completo || requester.usuario;
            el.passwordSection.hidden = true;
            el.password.required = false;
            el.confirmPassword.required = false;
            el.profileNumber.textContent = '02';
            el.saveButton.textContent = 'Actualizar solicitante';
            openModal('modalSolicitante', button);
            setTimeout(function () { el.user.focus(); }, 50);
        } catch (error) {
            toast(error.message || 'No se pudo abrir la cuenta.', 'error');
        } finally {
            buttonState(button, false);
        }
    }

    function openPassword(record, button) {
        clearForm(el.passwordForm);
        el.passwordRequesterId.value = record.id;
        document.getElementById('subtituloPassword').textContent = record.nombre_completo + ' · @' + record.usuario;
        updatePasswordRules('reglasNuevaPassword', '', '');
        openModal('modalPassword', button);
        setTimeout(function () { el.actorPassword.focus(); }, 50);
    }

    function openStateConfirmation(record, active, button) {
        if (active === 1 && Number(record.departamento_activo) !== 1) {
            toast('Edita primero la cuenta y asígnala a un departamento activo.', 'error');
            return;
        }

        const activating = active === 1;
        document.getElementById('tituloConfirmacion').textContent = activating
            ? '¿Reactivar solicitante?'
            : '¿Desactivar solicitante?';

        let text = activating
            ? record.nombre_completo + ' podrá volver a iniciar sesión y registrar solicitudes.'
            : record.nombre_completo + ' dejará de iniciar sesión. Su historial permanecerá disponible.';

        const openRequests = Number(record.solicitudes_abiertas) || 0;
        if (!activating && openRequests > 0) {
            text += ' Sus ' + openRequests + ' solicitud' + (openRequests === 1 ? '' : 'es') + ' abierta' + (openRequests === 1 ? '' : 's') + ' continuarán en el flujo normal.';
        }

        document.getElementById('textoConfirmacion').textContent = text;
        el.confirmState.textContent = activating ? 'Sí, reactivar' : 'Sí, desactivar';
        el.confirmState.classList.toggle('sol-btn--danger', !activating);
        el.confirmState.classList.toggle('sol-btn--primary', activating);
        state.pendingState = { record: record, active: active };
        openModal('modalConfirmacion', button);
    }

    async function saveRequester(event) {
        event.preventDefault();
        if (state.saving || !validateProfileForm()) return;

        state.saving = true;
        clearErrors(el.form);
        buttonState(el.saveButton, true, el.requesterId.value ? 'Actualizando...' : 'Guardando...');

        try {
            const form = new FormData(el.form);
            form.set('accion', 'GUARDAR');
            form.set('csrf_token', CSRF);
            const isNew = !el.requesterId.value;
            const data = await request(API, { method: 'POST', body: form });
            closeModal('modalSolicitante');
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
            form.set('solicitante_id', String(pending.record.id));
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

        if (!el.department.value) {
            setFieldError(el.form, 'departamento_id', 'Selecciona un departamento activo.');
            return false;
        }

        const selectedDepartment = state.departments.find(function (department) {
            return String(department.id) === el.department.value;
        });
        if (!selectedDepartment || Number(selectedDepartment.activo) !== 1) {
            setFieldError(el.form, 'departamento_id', 'Selecciona un departamento activo.');
            return false;
        }

        if (!el.requesterId.value) {
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
            throw new Error('El servidor devolvió una respuesta no válida. Revisa que los tres archivos estén en las carpetas correctas.');
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
        el.resultsText.textContent = 'No se pudieron cargar los solicitantes.';
        toast(error.message || 'No se pudo cargar la información.', 'error');
    }

    function clearFilters() {
        el.search.value = '';
        el.departmentFilter.value = '';
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
        document.querySelector('.sol-results').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function openModal(id, trigger) {
        const modal = document.getElementById(id);
        if (!modal) return;
        state.lastFocused = trigger || document.activeElement;
        modal.hidden = false;
        requestAnimationFrame(function () { modal.classList.add('is-open'); });
        document.body.classList.add('sol-modal-open');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal || modal.hidden) return;
        modal.classList.remove('is-open');
        setTimeout(function () {
            modal.hidden = true;
            if (!document.querySelector('.sol-modal.is-open')) {
                document.body.classList.remove('sol-modal-open');
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
        form.querySelectorAll('.sol-error').forEach(function (error) { error.textContent = ''; });
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
        return password.length >= 10 && password.length <= 72 &&
            /[a-z]/.test(password) && /[A-Z]/.test(password) && /\d/.test(password);
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
        item.className = 'sol-toast sol-toast--' + (type || 'info');
        item.innerHTML = '<strong>' + (type === 'error' ? 'Revisa la operación' : 'Operación realizada') + '</strong><p>' + escapeHtml(message) + '</p>';
        el.toast.appendChild(item);
        setTimeout(function () { item.classList.add('is-visible'); }, 10);
        setTimeout(function () {
            item.classList.remove('is-visible');
            setTimeout(function () { item.remove(); }, 200);
        }, 4200);
    }

    function debounce(fn, wait) {
        let timer;
        return function () {
            clearTimeout(timer);
            timer = setTimeout(fn, wait);
        };
    }

    function formatPhone(value) {
        const digits = String(value || '').replace(/\D+/g, '');
        return digits.length === 10
            ? digits.slice(0, 3) + ' ' + digits.slice(3, 6) + ' ' + digits.slice(6)
            : String(value || '');
    }

    function timeLabel(value) {
        const date = value ? new Date(String(value).replace(' ', 'T')) : new Date();
        if (Number.isNaN(date.getTime())) return 'ahora';
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
        return String(value).replace(/(["\\])/g, '\\$1');
    }

    function safeNumber(value) {
        const number = Number(value);
        return Number.isFinite(number) ? String(number) : '0';
    }

    function initialsFrom(value) {
        const words = String(value || '').trim().split(/\s+/).filter(Boolean);
        return words.slice(0, 2).map(function (word) {
            return word.charAt(0).toUpperCase();
        }).join('') || 'S';
    }
})();
</script>
</body>
</html>