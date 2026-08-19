<?php

declare(strict_types=1);

/*
 * La interfaz consulta esta misma página mediante ?admin_api=1 para evitar
 * rutas relativas frágiles hacia la carpeta funciones.
 */
if (isset($_GET['admin_api'])) {
    $endpoint = __DIR__ . '/../funciones/administradores_funciones.php';

    if (!is_file($endpoint)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode(
            [
                'success' => false,
                'mensaje' => 'No se encontró funciones/administradores_funciones.php. Copia juntos los tres archivos del módulo.',
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
$adminActualId = (int) ($_SESSION['usuario_id'] ?? 0);
$nombreAdmin = trim((string) (
    $_SESSION['nombre_completo']
    ?? $_SESSION['nombre']
    ?? $_SESSION['usuario']
    ?? 'Administrador'
));

if ($nombreAdmin === '') {
    $nombreAdmin = 'Administrador';
}

$cssPath = __DIR__ . '/../css/style_administradores.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '4.0';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b2944">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Gestión segura de cuentas administrativas del Sistema de Mantenimiento.">
    <title>Administradores | Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="../css/style_administradores.css?v=<?= rawurlencode($cssVersion) ?>">
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
<?php include __DIR__ . '/../inc/topbar.php'; ?>

<svg class="adm-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="adm-icon-users" viewBox="0 0 24 24">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
    </symbol>
    <symbol id="adm-icon-user-plus" viewBox="0 0 24 24">
        <path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="8" cy="7" r="4"/>
        <path d="M19 8v6M16 11h6"/>
    </symbol>
    <symbol id="adm-icon-refresh" viewBox="0 0 24 24">
        <path d="M20 6v5h-5M4 18v-5h5"/>
        <path d="M6.1 9A7 7 0 0 1 18.5 6.5L20 8M4 16l1.5 1.5A7 7 0 0 0 17.9 15"/>
    </symbol>
    <symbol id="adm-icon-shield" viewBox="0 0 24 24">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
        <path d="m9 12 2 2 4-4"/>
    </symbol>
    <symbol id="adm-icon-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-4-4"/>
    </symbol>
    <symbol id="adm-icon-list" viewBox="0 0 24 24">
        <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
    </symbol>
    <symbol id="adm-icon-check" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="m8 12 2.6 2.6L16.5 9"/>
    </symbol>
    <symbol id="adm-icon-user-x" viewBox="0 0 24 24">
        <path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="8" cy="7" r="4"/>
        <path d="m18 8 5 5M23 8l-5 5"/>
    </symbol>
    <symbol id="adm-icon-login" viewBox="0 0 24 24">
        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>
    </symbol>
    <symbol id="adm-icon-filter" viewBox="0 0 24 24">
        <path d="M4 5h16M7 12h10M10 19h4"/>
    </symbol>
</svg>

<main class="adm-page" data-admin-actual="<?= $adminActualId ?>">
    <header class="adm-heading" aria-labelledby="tituloAdministradores">
        <div class="adm-heading__pattern" aria-hidden="true"></div>

        <div class="adm-heading__content">
            <div class="adm-heading__copy">
                <p class="adm-eyebrow">
                    <span class="adm-eyebrow__icon"><svg><use href="#adm-icon-users"></use></svg></span>
                    Gestión de personal
                </p>
                <h1 id="tituloAdministradores">Administradores</h1>
                <p>
                    Controla las cuentas con acceso total al sistema. Registra, actualiza,
                    protege credenciales y conserva cada cambio dentro de la auditoría.
                </p>

                <div class="adm-heading__meta">
                    <span><i class="adm-live-dot" aria-hidden="true"></i> Control de acceso protegido</span>
                    <span>Administrador: <strong><?= htmlspecialchars($nombreAdmin, ENT_QUOTES, 'UTF-8') ?></strong></span>
                </div>
            </div>

            <div class="adm-heading__actions" aria-label="Acciones de administradores">
                <button type="button" class="adm-btn adm-btn--secondary" id="btnActualizar">
                    <svg><use href="#adm-icon-refresh"></use></svg>
                    <span>Actualizar</span>
                </button>
                <button type="button" class="adm-btn adm-btn--primary" id="btnNuevo">
                    <svg><use href="#adm-icon-user-plus"></use></svg>
                    <span>Nuevo administrador</span>
                </button>
            </div>

            <div class="adm-heading__mini-card" aria-hidden="true">
                <span><svg><use href="#adm-icon-shield"></use></svg></span>
                <div>
                    <small>Centro de seguridad</small>
                    <strong>Cuentas, permisos y credenciales protegidas</strong>
                </div>
            </div>
        </div>
    </header>

    <section class="adm-security-note" aria-label="Reglas de seguridad">
        <span class="adm-security-note__icon"><svg><use href="#adm-icon-shield"></use></svg></span>
        <div>
            <strong>Protección de acceso y trazabilidad</strong>
            <p>
                Las cuentas se desactivan, no se eliminan. Tu propia cuenta y el último administrador
                activo permanecen protegidos; cada modificación se registra en auditoría.
            </p>
        </div>
        <span class="adm-security-note__badge">Auditoría activa</span>
    </section>

    <div class="adm-status" id="estadoPagina" role="status" aria-live="polite">
        <span class="adm-spinner adm-spinner--small" aria-hidden="true"></span>
        <span>Cargando administradores...</span>
    </div>

    <section class="adm-kpis" aria-label="Resumen de administradores">
        <article class="adm-kpi adm-kpi--total">
            <span class="adm-kpi__icon"><svg><use href="#adm-icon-users"></use></svg></span>
            <span class="adm-kpi__body">
                <span>Total</span>
                <strong id="kpiTotal">0</strong>
                <small>Cuentas registradas</small>
            </span>
        </article>
        <article class="adm-kpi adm-kpi--active">
            <span class="adm-kpi__icon"><svg><use href="#adm-icon-check"></use></svg></span>
            <span class="adm-kpi__body">
                <span>Activos</span>
                <strong id="kpiActivos">0</strong>
                <small>Con acceso al sistema</small>
            </span>
        </article>
        <article class="adm-kpi adm-kpi--inactive">
            <span class="adm-kpi__icon"><svg><use href="#adm-icon-user-x"></use></svg></span>
            <span class="adm-kpi__body">
                <span>Inactivos</span>
                <strong id="kpiInactivos">0</strong>
                <small>Conservados por historial</small>
            </span>
        </article>
        <article class="adm-kpi adm-kpi--neutral">
            <span class="adm-kpi__icon"><svg><use href="#adm-icon-login"></use></svg></span>
            <span class="adm-kpi__body">
                <span>Sin ingreso</span>
                <strong id="kpiSinAcceso">0</strong>
                <small>Nunca han iniciado sesión</small>
            </span>
        </article>
    </section>

    <section class="adm-card adm-filters-card" aria-labelledby="tituloFiltrosAdministradores">
        <header class="adm-section-head">
            <div>
                <p class="adm-eyebrow">Búsqueda y filtros</p>
                <h2 id="tituloFiltrosAdministradores">Encuentra una cuenta</h2>
                <p>Busca por nombre, usuario o datos de contacto y limita los resultados por estado.</p>
            </div>
            <span class="adm-section-head__chip"><svg><use href="#adm-icon-filter"></use></svg> Consulta local</span>
        </header>

        <div class="adm-filters">
            <label class="adm-field adm-field--search" for="filtroBusqueda">
                <span>Buscar</span>
                <div class="adm-search">
                    <span aria-hidden="true"><svg><use href="#adm-icon-search"></use></svg></span>
                    <input
                        type="search"
                        id="filtroBusqueda"
                        maxlength="120"
                        placeholder="Nombre, usuario, teléfono o correo"
                        autocomplete="off"
                    >
                </div>
            </label>

            <label class="adm-field" for="filtroEstado">
                <span>Estado</span>
                <select id="filtroEstado">
                    <option value="TODOS">Todos</option>
                    <option value="ACTIVO">Activos</option>
                    <option value="INACTIVO">Inactivos</option>
                    <option value="SIN_ACCESO">Sin ingreso</option>
                    <option value="MI_CUENTA">Mi cuenta</option>
                </select>
            </label>

            <label class="adm-field adm-field--small" for="filtroCantidad">
                <span>Mostrar</span>
                <select id="filtroCantidad">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>

            <div class="adm-filter-actions">
                <button type="button" class="adm-btn adm-btn--ghost" id="btnLimpiar">Limpiar</button>
            </div>
        </div>
    </section>

    <section class="adm-card adm-results adm-results-card" aria-labelledby="tituloListadoAdministradores">
        <header class="adm-results__head">
            <div>
                <p class="adm-eyebrow">Resultados</p>
                <h2 id="tituloListadoAdministradores">Cuentas administrativas</h2>
                <p id="textoResultados">Preparando resultados...</p>
            </div>

            <div class="adm-results__tools">
                <span class="adm-updated" id="ultimaActualizacion">Sin actualizar</span>
                <span class="adm-results__badge"><svg><use href="#adm-icon-list"></use></svg> Listado protegido</span>
            </div>
        </header>

        <div class="adm-loading" id="estadoCarga">
            <span class="adm-spinner" aria-hidden="true"></span>
            <strong>Cargando administradores...</strong>
        </div>

        <div class="adm-empty" id="estadoVacio" hidden>
            <span aria-hidden="true"><svg><use href="#adm-icon-search"></use></svg></span>
            <h3>No hay coincidencias</h3>
            <p>Prueba con otro nombre o cambia el filtro de estado.</p>
        </div>

        <div class="adm-table-wrap" id="contenedorTabla" hidden tabindex="0" aria-label="Listado desplazable de administradores">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Administrador</th>
                        <th>Contacto</th>
                        <th>Último acceso</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaAdministradores"></tbody>
            </table>
        </div>

        <footer class="adm-pagination" id="paginacion" hidden>
            <span id="textoPaginacion">Sin resultados</span>
            <div class="adm-pagination__buttons">
                <button type="button" id="btnAnterior">Anterior</button>
                <span id="paginaActual">Página 1</span>
                <button type="button" id="btnSiguiente">Siguiente</button>
            </div>
        </footer>
    </section>

    <footer class="adm-footer">
        <span>Sistema de Mantenimiento</span>
        <span>Gestión administrativa protegida · Los Chapeteados División Petfood</span>
    </footer>

    <div class="adm-tools-background" aria-hidden="true"></div>
</main>

<!-- Formulario de alta y edición -->
<section class="adm-modal" id="modalAdministrador" hidden>
    <div class="adm-modal__backdrop" aria-hidden="true"></div>
    <div class="adm-modal__dialog adm-modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="tituloModal">
        <header class="adm-modal__header">
            <div>
                <p class="adm-eyebrow" id="etiquetaModal">NUEVO REGISTRO</p>
                <h2 id="tituloModal">Nuevo administrador</h2>
                <p id="subtituloModal">Crea una cuenta con acceso administrativo.</p>
            </div>
            <button type="button" class="adm-modal__close" data-close="modalAdministrador" aria-label="Cerrar">×</button>
        </header>

        <form id="formAdministrador" novalidate>
            <input type="hidden" id="administradorId" name="administrador_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="adm-modal__body">
                <section class="adm-form-section">
                    <header>
                        <span>01</span>
                        <div>
                            <h3>Datos de acceso</h3>
                            <p>El usuario debe ser único en todo el sistema.</p>
                        </div>
                    </header>

                    <div class="adm-form-grid">
                        <label class="adm-form-field" for="usuario">
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
                                placeholder="Ej. mantenimiento.admin"
                                required
                            >
                            <small>Letras minúsculas, números, punto, guion o guion bajo.</small>
                            <em class="adm-error" data-error-for="usuario"></em>
                        </label>

                        <label class="adm-form-field" for="correo">
                            <span>Correo electrónico</span>
                            <input
                                type="email"
                                id="correo"
                                name="correo"
                                maxlength="150"
                                autocomplete="email"
                                placeholder="nombre@empresa.com"
                            >
                            <small>Opcional, pero no puede repetirse.</small>
                            <em class="adm-error" data-error-for="correo"></em>
                        </label>
                    </div>
                </section>

                <section class="adm-form-section" id="seccionPasswordNuevo">
                    <header>
                        <span>02</span>
                        <div>
                            <h3>Contraseña inicial</h3>
                            <p>Después podrá restablecerse desde la lista.</p>
                        </div>
                    </header>

                    <div class="adm-form-grid">
                        <label class="adm-form-field" for="password">
                            <span>Contraseña *</span>
                            <div class="adm-password">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    minlength="10"
                                    maxlength="72"
                                    autocomplete="new-password"
                                >
                                <button type="button" data-toggle-password="password">Mostrar</button>
                            </div>
                            <small>Mínimo 10 caracteres, mayúscula, minúscula y número.</small>
                            <em class="adm-error" data-error-for="password"></em>
                        </label>

                        <label class="adm-form-field" for="confirmarPassword">
                            <span>Confirmar contraseña *</span>
                            <div class="adm-password">
                                <input
                                    type="password"
                                    id="confirmarPassword"
                                    name="confirmar_password"
                                    minlength="10"
                                    maxlength="72"
                                    autocomplete="new-password"
                                >
                                <button type="button" data-toggle-password="confirmarPassword">Mostrar</button>
                            </div>
                            <em class="adm-error" data-error-for="confirmar_password"></em>
                        </label>
                    </div>
                    <div class="adm-password-rules" id="reglasPassword">
                        <span data-rule="length">10 o más caracteres</span>
                        <span data-rule="lower">Una minúscula</span>
                        <span data-rule="upper">Una mayúscula</span>
                        <span data-rule="number">Un número</span>
                        <span data-rule="match">Coinciden</span>
                    </div>
                </section>

                <section class="adm-form-section">
                    <header>
                        <span id="numeroSeccionPerfil">03</span>
                        <div>
                            <h3>Información personal</h3>
                            <p>Datos visibles en el sistema y en la bitácora.</p>
                        </div>
                    </header>

                    <div class="adm-form-grid adm-form-grid--three">
                        <label class="adm-form-field" for="nombre">
                            <span>Nombre *</span>
                            <input type="text" id="nombre" name="nombre" minlength="2" maxlength="100" autocomplete="given-name" required>
                            <em class="adm-error" data-error-for="nombre"></em>
                        </label>

                        <label class="adm-form-field" for="apellidoPaterno">
                            <span>Apellido paterno</span>
                            <input type="text" id="apellidoPaterno" name="apellido_paterno" maxlength="100" autocomplete="family-name">
                            <em class="adm-error" data-error-for="apellido_paterno"></em>
                        </label>

                        <label class="adm-form-field" for="apellidoMaterno">
                            <span>Apellido materno</span>
                            <input type="text" id="apellidoMaterno" name="apellido_materno" maxlength="100">
                            <em class="adm-error" data-error-for="apellido_materno"></em>
                        </label>

                        <label class="adm-form-field" for="telefono">
                            <span>Teléfono</span>
                            <input type="tel" id="telefono" name="telefono" inputmode="numeric" maxlength="14" autocomplete="tel" placeholder="10 dígitos">
                            <em class="adm-error" data-error-for="telefono"></em>
                        </label>
                    </div>
                </section>
            </div>

            <footer class="adm-modal__footer">
                <button type="button" class="adm-btn adm-btn--ghost" data-close="modalAdministrador">Cancelar</button>
                <button type="submit" class="adm-btn adm-btn--primary" id="btnGuardar">Guardar administrador</button>
            </footer>
        </form>
    </div>
</section>

<!-- Restablecimiento de contraseña -->
<section class="adm-modal" id="modalPassword" hidden>
    <div class="adm-modal__backdrop" aria-hidden="true"></div>
    <div class="adm-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tituloPassword">
        <header class="adm-modal__header">
            <div>
                <p class="adm-eyebrow">SEGURIDAD</p>
                <h2 id="tituloPassword">Restablecer contraseña</h2>
                <p id="subtituloPassword">Cuenta seleccionada</p>
            </div>
            <button type="button" class="adm-modal__close" data-close="modalPassword" aria-label="Cerrar">×</button>
        </header>

        <form id="formPassword" novalidate>
            <input type="hidden" id="passwordAdministradorId" name="administrador_id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="adm-modal__body">
                <div class="adm-security-callout">
                    <strong>Autorización requerida</strong>
                    <p>Para proteger las cuentas, confirma primero tu propia contraseña administrativa.</p>
                </div>

                <label class="adm-form-field" for="passwordActualActor">
                    <span>Tu contraseña actual *</span>
                    <div class="adm-password">
                        <input
                            type="password"
                            id="passwordActualActor"
                            name="password_actual_actor"
                            maxlength="72"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" data-toggle-password="passwordActualActor">Mostrar</button>
                    </div>
                    <em class="adm-error" data-error-for="password_actual_actor"></em>
                </label>

                <div class="adm-form-grid">
                    <label class="adm-form-field" for="nuevaPassword">
                        <span>Nueva contraseña *</span>
                        <div class="adm-password">
                            <input
                                type="password"
                                id="nuevaPassword"
                                name="nueva_password"
                                minlength="10"
                                maxlength="72"
                                autocomplete="new-password"
                                required
                            >
                            <button type="button" data-toggle-password="nuevaPassword">Mostrar</button>
                        </div>
                        <small>Mínimo 10 caracteres, mayúscula, minúscula y número.</small>
                        <em class="adm-error" data-error-for="nueva_password"></em>
                    </label>

                    <label class="adm-form-field" for="confirmarNuevaPassword">
                        <span>Confirmar nueva contraseña *</span>
                        <div class="adm-password">
                            <input
                                type="password"
                                id="confirmarNuevaPassword"
                                name="confirmar_nueva_password"
                                minlength="10"
                                maxlength="72"
                                autocomplete="new-password"
                                required
                            >
                            <button type="button" data-toggle-password="confirmarNuevaPassword">Mostrar</button>
                        </div>
                        <em class="adm-error" data-error-for="confirmar_nueva_password"></em>
                    </label>
                </div>

                <div class="adm-password-rules" id="reglasNuevaPassword">
                    <span data-rule="length">10 o más caracteres</span>
                    <span data-rule="lower">Una minúscula</span>
                    <span data-rule="upper">Una mayúscula</span>
                    <span data-rule="number">Un número</span>
                    <span data-rule="match">Coinciden</span>
                </div>
            </div>

            <footer class="adm-modal__footer">
                <button type="button" class="adm-btn adm-btn--ghost" data-close="modalPassword">Cancelar</button>
                <button type="submit" class="adm-btn adm-btn--primary" id="btnGuardarPassword">Actualizar contraseña</button>
            </footer>
        </form>
    </div>
</section>

<!-- Confirmación de activar/desactivar -->
<section class="adm-modal" id="modalConfirmacion" hidden>
    <div class="adm-modal__backdrop" aria-hidden="true"></div>
    <div class="adm-modal__dialog adm-modal__dialog--small" role="dialog" aria-modal="true" aria-labelledby="tituloConfirmacion">
        <header class="adm-modal__header">
            <div>
                <p class="adm-eyebrow">CONFIRMACIÓN</p>
                <h2 id="tituloConfirmacion">Confirmar cambio</h2>
                <p id="textoConfirmacion">Revisa la operación antes de continuar.</p>
            </div>
            <button type="button" class="adm-modal__close" data-close="modalConfirmacion" aria-label="Cerrar">×</button>
        </header>
        <footer class="adm-modal__footer adm-modal__footer--alone">
            <button type="button" class="adm-btn adm-btn--ghost" data-close="modalConfirmacion">Cancelar</button>
            <button type="button" class="adm-btn adm-btn--danger" id="btnConfirmarEstado">Confirmar</button>
        </footer>
    </div>
</section>

<div class="adm-toast-region" id="toastRegion" aria-live="polite" aria-atomic="true"></div>

<script>
(function () {
    'use strict';

    const API = 'administradores.php?admin_api=1';
    const CSRF = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const ADMIN_ACTUAL_ID = <?= $adminActualId ?>;

    const state = {
        records: [],
        filtered: [],
        page: 1,
        loading: false,
        saving: false,
        pendingState: null,
        lastFocused: null
    };

    const el = {
        page: document.querySelector('.adm-page'),
        status: document.getElementById('estadoPagina'),
        loading: document.getElementById('estadoCarga'),
        empty: document.getElementById('estadoVacio'),
        tableWrap: document.getElementById('contenedorTabla'),
        tbody: document.getElementById('tablaAdministradores'),
        pagination: document.getElementById('paginacion'),
        paginationText: document.getElementById('textoPaginacion'),
        pageText: document.getElementById('paginaActual'),
        prev: document.getElementById('btnAnterior'),
        next: document.getElementById('btnSiguiente'),
        resultsText: document.getElementById('textoResultados'),
        updated: document.getElementById('ultimaActualizacion'),
        search: document.getElementById('filtroBusqueda'),
        statusFilter: document.getElementById('filtroEstado'),
        amount: document.getElementById('filtroCantidad'),
        total: document.getElementById('kpiTotal'),
        active: document.getElementById('kpiActivos'),
        inactive: document.getElementById('kpiInactivos'),
        never: document.getElementById('kpiSinAcceso'),
        form: document.getElementById('formAdministrador'),
        adminId: document.getElementById('administradorId'),
        user: document.getElementById('usuario'),
        email: document.getElementById('correo'),
        password: document.getElementById('password'),
        confirmPassword: document.getElementById('confirmarPassword'),
        passwordSection: document.getElementById('seccionPasswordNuevo'),
        profileNumber: document.getElementById('numeroSeccionPerfil'),
        name: document.getElementById('nombre'),
        father: document.getElementById('apellidoPaterno'),
        mother: document.getElementById('apellidoMaterno'),
        phone: document.getElementById('telefono'),
        save: document.getElementById('btnGuardar'),
        passwordForm: document.getElementById('formPassword'),
        passwordAdminId: document.getElementById('passwordAdministradorId'),
        actorPassword: document.getElementById('passwordActualActor'),
        newPassword: document.getElementById('nuevaPassword'),
        confirmNewPassword: document.getElementById('confirmarNuevaPassword'),
        savePassword: document.getElementById('btnGuardarPassword'),
        passwordSubtitle: document.getElementById('subtituloPassword'),
        confirmTitle: document.getElementById('tituloConfirmacion'),
        confirmText: document.getElementById('textoConfirmacion'),
        confirmState: document.getElementById('btnConfirmarEstado'),
        toast: document.getElementById('toastRegion')
    };

    document.getElementById('btnNuevo').addEventListener('click', openNew);
    document.getElementById('btnActualizar').addEventListener('click', load);
    document.getElementById('btnLimpiar').addEventListener('click', clearFilters);
    el.search.addEventListener('input', debounce(applyFilters, 180));
    el.statusFilter.addEventListener('change', applyFilters);
    el.amount.addEventListener('change', function () { state.page = 1; render(); });
    el.prev.addEventListener('click', function () { changePage(-1); });
    el.next.addEventListener('click', function () { changePage(1); });
    el.tbody.addEventListener('click', handleTableAction);
    el.form.addEventListener('submit', saveAdministrator);
    el.passwordForm.addEventListener('submit', saveNewPassword);
    el.confirmState.addEventListener('click', executeStateChange);

    document.querySelectorAll('[data-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(button.dataset.close);
        });
    });

    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.togglePassword);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Ocultar' : 'Mostrar';
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
        el.user.value = el.user.value.toLowerCase().replace(/[^a-z0-9._-]/g, '').slice(0, 60);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        const visible = Array.from(document.querySelectorAll('.adm-modal:not([hidden])')).pop();
        if (visible) closeModal(visible.id);
    });

    load();

    async function load() {
        if (state.loading) return;
        state.loading = true;
        setLoading(true);

        try {
            const data = await request(API + '&accion=LISTAR');
            state.records = Array.isArray(data.administradores) ? data.administradores : [];
            paintSummary(data.resumen || {});
            applyFilters();
            el.updated.textContent = 'Actualizado ' + new Intl.DateTimeFormat('es-MX', {
                hour: '2-digit', minute: '2-digit'
            }).format(new Date());
            el.status.hidden = true;
        } catch (error) {
            showPageError(error);
        } finally {
            state.loading = false;
            setLoading(false);
        }
    }

    function paintSummary(summary) {
        el.total.textContent = safeNumber(summary.total);
        el.active.textContent = safeNumber(summary.activos);
        el.inactive.textContent = safeNumber(summary.inactivos);
        el.never.textContent = safeNumber(summary.sin_acceso);
    }

    function applyFilters() {
        const term = normalize(el.search.value);
        const status = el.statusFilter.value;

        state.filtered = state.records.filter(function (record) {
            const haystack = normalize([
                record.usuario,
                record.nombre_completo,
                record.telefono,
                record.correo
            ].join(' '));

            if (term && haystack.indexOf(term) === -1) return false;
            if (status === 'ACTIVO' && Number(record.activo) !== 1) return false;
            if (status === 'INACTIVO' && Number(record.activo) !== 0) return false;
            if (status === 'SIN_ACCESO' && record.ultimo_acceso !== null) return false;
            if (status === 'MI_CUENTA' && Number(record.id) !== ADMIN_ACTUAL_ID) return false;
            return true;
        });

        state.page = 1;
        render();
    }

    function render() {
        const total = state.filtered.length;
        const emptyTitle = el.empty.querySelector('h3');
        const emptyText = el.empty.querySelector('p');
        if (emptyTitle) emptyTitle.textContent = 'No hay coincidencias';
        if (emptyText) emptyText.textContent = 'Prueba con otro nombre o cambia el filtro de estado.';
        const perPage = Number(el.amount.value);
        const pages = perPage === 0 ? 1 : Math.max(1, Math.ceil(total / perPage));
        state.page = Math.min(state.page, pages);

        const start = perPage === 0 ? 0 : (state.page - 1) * perPage;
        const end = perPage === 0 ? total : Math.min(start + perPage, total);
        const visible = state.filtered.slice(start, end);

        el.tbody.innerHTML = visible.map(rowTemplate).join('');
        el.resultsText.textContent = total === 1
            ? '1 cuenta encontrada'
            : total + ' cuentas encontradas';

        showResultsState(total === 0 ? 'empty' : 'table');
        el.pagination.hidden = total === 0 || (perPage === 0 || total <= perPage);
        el.pagination.setAttribute('aria-hidden', el.pagination.hidden ? 'true' : 'false');
        el.paginationText.textContent = total === 0
            ? 'Sin resultados'
            : 'Mostrando ' + (start + 1) + ' a ' + end + ' de ' + total;
        el.pageText.textContent = 'Página ' + state.page + ' de ' + pages;
        el.prev.disabled = state.page <= 1;
        el.next.disabled = state.page >= pages;
    }

    function rowTemplate(record) {
        const active = Number(record.activo) === 1;
        const current = Number(record.id) === ADMIN_ACTUAL_ID;
        const initials = initialsFrom(record.nombre_completo || record.usuario);
        const contact = [
            record.telefono ? '<span>Tel. ' + escapeHtml(record.telefono) + '</span>' : '',
            record.correo ? '<span>' + escapeHtml(record.correo) + '</span>' : ''
        ].filter(Boolean).join('');

        return '<tr>' +
            '<td><div class="adm-person">' +
                '<span class="adm-avatar">' + escapeHtml(initials) + '</span>' +
                '<div><strong>' + escapeHtml(record.nombre_completo || 'Sin nombre') + '</strong>' +
                '<span>@' + escapeHtml(record.usuario || '') + '</span>' +
                (current ? '<em>Tu cuenta</em>' : '') +
                '</div></div></td>' +
            '<td><div class="adm-contact">' + (contact || '<span>Sin datos de contacto</span>') + '</div></td>' +
            '<td><div class="adm-access"><strong>' + escapeHtml(record.ultimo_acceso_texto || 'Nunca ha ingresado') + '</strong>' +
                '<span>Registrado ' + escapeHtml(record.fecha_registro_texto || '—') + '</span></div></td>' +
            '<td><span class="adm-badge ' + (active ? 'adm-badge--active' : 'adm-badge--inactive') + '">' +
                (active ? 'Activo' : 'Inactivo') + '</span></td>' +
            '<td><div class="adm-actions">' +
                '<button type="button" data-action="edit" data-id="' + Number(record.id) + '">Editar</button>' +
                '<button type="button" data-action="password" data-id="' + Number(record.id) + '">Contraseña</button>' +
                (current
                    ? '<button type="button" class="is-disabled" disabled title="No puedes desactivar tu propia cuenta">Tu sesión</button>'
                    : '<button type="button" class="' + (active ? 'is-danger' : 'is-success') + '" data-action="state" data-id="' + Number(record.id) + '" data-active="' + (active ? '0' : '1') + '">' + (active ? 'Desactivar' : 'Reactivar') + '</button>') +
                '</div></td>' +
            '</tr>';
    }

    async function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button || button.disabled) return;
        const id = Number(button.dataset.id);
        const record = state.records.find(function (item) { return Number(item.id) === id; });
        if (!record) {
            toast('No se encontró la cuenta seleccionada.', 'error');
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
        el.adminId.value = '';
        el.passwordSection.hidden = false;
        el.password.required = true;
        el.confirmPassword.required = true;
        el.profileNumber.textContent = '03';
        document.getElementById('etiquetaModal').textContent = 'NUEVO REGISTRO';
        document.getElementById('tituloModal').textContent = 'Nuevo administrador';
        document.getElementById('subtituloModal').textContent = 'Crea una cuenta con acceso administrativo.';
        el.save.textContent = 'Guardar administrador';
        updatePasswordRules('reglasPassword', '', '');
        openModal('modalAdministrador', document.getElementById('btnNuevo'));
        setTimeout(function () { el.user.focus(); }, 50);
    }

    async function openEdit(record, button) {
        buttonState(button, true, 'Cargando...');
        try {
            const data = await request(API + '&accion=DETALLE&id=' + encodeURIComponent(record.id));
            const admin = data.administrador || {};
            clearForm(el.form);
            el.adminId.value = admin.id || '';
            el.user.value = admin.usuario || '';
            el.email.value = admin.correo || '';
            el.name.value = admin.nombre || '';
            el.father.value = admin.apellido_paterno || '';
            el.mother.value = admin.apellido_materno || '';
            el.phone.value = admin.telefono || '';
            el.passwordSection.hidden = true;
            el.password.required = false;
            el.confirmPassword.required = false;
            el.profileNumber.textContent = '02';
            document.getElementById('etiquetaModal').textContent = Number(admin.id) === ADMIN_ACTUAL_ID ? 'TU CUENTA' : 'EDITAR CUENTA';
            document.getElementById('tituloModal').textContent = 'Editar administrador';
            document.getElementById('subtituloModal').textContent = 'Actualiza la información sin modificar la contraseña.';
            el.save.textContent = 'Actualizar administrador';
            openModal('modalAdministrador', button);
            setTimeout(function () { el.user.focus(); }, 50);
        } catch (error) {
            toast(error.message || 'No se pudo abrir la cuenta.', 'error');
        } finally {
            buttonState(button, false);
        }
    }

    function openPassword(record, button) {
        clearForm(el.passwordForm);
        el.passwordAdminId.value = record.id;
        el.passwordSubtitle.textContent = (record.nombre_completo || record.usuario) + ' · @' + record.usuario;
        updatePasswordRules('reglasNuevaPassword', '', '');
        openModal('modalPassword', button);
        setTimeout(function () { el.actorPassword.focus(); }, 50);
    }

    function openStateConfirmation(record, active, button) {
        const reactivate = active === 1;
        state.pendingState = { record: record, active: active, button: button };
        el.confirmTitle.textContent = reactivate ? '¿Reactivar administrador?' : '¿Desactivar administrador?';
        el.confirmText.textContent = reactivate
            ? 'La cuenta de ' + record.nombre_completo + ' volverá a tener acceso al sistema.'
            : 'La cuenta de ' + record.nombre_completo + ' perderá el acceso, pero su historial se conservará.';
        el.confirmState.textContent = reactivate ? 'Sí, reactivar' : 'Sí, desactivar';
        el.confirmState.className = 'adm-btn ' + (reactivate ? 'adm-btn--success' : 'adm-btn--danger');
        openModal('modalConfirmacion', button);
    }

    async function saveAdministrator(event) {
        event.preventDefault();
        if (state.saving) return;
        clearErrors(el.form);

        if (!validateProfileForm()) return;
        state.saving = true;
        buttonState(el.save, true, el.adminId.value ? 'Actualizando...' : 'Guardando...');

        try {
            const form = new FormData(el.form);
            form.set('accion', 'GUARDAR');
            form.set('csrf_token', CSRF);
            const data = await request(API, { method: 'POST', body: form });
            closeModal('modalAdministrador');
            await load();
            toast(data.mensaje || 'Operación realizada.', 'success');
            if (data.actualizo_sesion) {
                setTimeout(function () { window.location.reload(); }, 900);
            }
        } catch (error) {
            markServerError(el.form, error);
            toast(error.message || 'No se pudo guardar el administrador.', 'error');
        } finally {
            state.saving = false;
            buttonState(el.save, false);
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
        buttonState(el.savePassword, true, 'Actualizando...');

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
            buttonState(el.savePassword, false);
        }
    }

    async function executeStateChange() {
        if (!state.pendingState || state.saving) return;
        const pending = state.pendingState;
        state.saving = true;
        buttonState(el.confirmState, true, 'Procesando...');

        try {
            const form = new FormData();
            form.set('accion', 'CAMBIAR_ESTADO');
            form.set('csrf_token', CSRF);
            form.set('administrador_id', String(pending.record.id));
            form.set('activo', String(pending.active));
            const data = await request(API, { method: 'POST', body: form });
            closeModal('modalConfirmacion');
            state.pendingState = null;
            await load();
            toast(data.mensaje || 'Estado actualizado.', 'success');
        } catch (error) {
            toast(error.message || 'No se pudo cambiar el estado.', 'error');
        } finally {
            state.saving = false;
            buttonState(el.confirmState, false);
        }
    }

    function validateProfileForm() {
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

        if (!el.adminId.value) {
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

    function setLoading(active) {
        if (active) {
            showResultsState('loading');
            el.pagination.hidden = true;
            el.pagination.setAttribute('aria-hidden', 'true');
            return;
        }

        /*
         * Al finalizar, render() ya decidió si corresponde mostrar la tabla o
         * el estado vacío. Sólo ocultamos el cargador si todavía quedó visible
         * por una respuesta interrumpida o por estilos externos del sistema.
         */
        if (!el.loading.hidden) {
            el.loading.hidden = true;
            el.loading.setAttribute('aria-hidden', 'true');
        }
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
        showResultsState('empty');
        el.empty.querySelector('h3').textContent = 'No se pudo cargar la lista';
        el.empty.querySelector('p').textContent = 'Actualiza la página o revisa el mensaje mostrado arriba.';
        el.status.hidden = false;
        el.status.classList.add('is-error');
        el.status.textContent = error.message || 'No se pudo cargar la información.';
        el.resultsText.textContent = 'No se pudieron cargar los administradores.';
        toast(error.message || 'No se pudo cargar la información.', 'error');
    }

    function clearFilters() {
        el.search.value = '';
        el.statusFilter.value = 'TODOS';
        el.amount.value = '10';
        applyFilters();
    }

    function changePage(delta) {
        state.page += delta;
        render();
        document.querySelector('.adm-results').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function openModal(id, trigger) {
        const modal = document.getElementById(id);
        if (!modal) return;
        state.lastFocused = trigger || document.activeElement;
        modal.hidden = false;
        requestAnimationFrame(function () { modal.classList.add('is-open'); });
        document.body.classList.add('adm-modal-open');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal || modal.hidden) return;
        modal.classList.remove('is-open');
        setTimeout(function () {
            modal.hidden = true;
            if (!document.querySelector('.adm-modal.is-open')) {
                document.body.classList.remove('adm-modal-open');
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
        form.querySelectorAll('.adm-error').forEach(function (error) { error.textContent = ''; });
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
        item.className = 'adm-toast adm-toast--' + (type || 'info');
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

    function normalize(value) {
        return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
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
        return (words.slice(0, 2).map(function (word) { return word.charAt(0).toUpperCase(); }).join('') || 'A');
    }
})();
</script>
</body>
</html>