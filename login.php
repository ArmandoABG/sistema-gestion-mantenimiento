<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/seguridad.php';

/*
|--------------------------------------------------------------------------
| Redirigir una sesión ya autenticada
|--------------------------------------------------------------------------
*/

if (sm_sesion_autenticada()) {
    $rutasPorRol = [
        'ADMIN' => 'JS/dashboard_admin.php',
        'SOLICITANTE' => 'JS/dashboard_solicitante.php',
        'TECNICO' => 'JS/dashboard_tecnico.php',
    ];

    $tipoUsuario = (string) ($_SESSION['tipo_usuario'] ?? '');

    if (isset($rutasPorRol[$tipoUsuario])) {
        header('Location: ' . $rutasPorRol[$tipoUsuario]);
        exit;
    }

    sm_destruir_sesion();
    sm_iniciar_sesion_segura();
}

$csrfLogin = sm_token_csrf('csrf_login');
$cssLogin = __DIR__ . '/css/style_login.css';
$versionCss = file_exists($cssLogin) ? (string) filemtime($cssLogin) : (string) time();

$mensajeInicial = '';
$tipoMensajeInicial = 'info';

if (isset($_GET['sesion']) && (string) $_GET['sesion'] === 'expirada') {
    $mensajeInicial = 'Tu sesión terminó por inactividad. Inicia sesión nuevamente.';
    $tipoMensajeInicial = 'warning';
} elseif (isset($_GET['acceso']) && (string) $_GET['acceso'] === 'denegado') {
    $mensajeInicial = 'Tu cuenta no tiene permiso para entrar a esa sección.';
    $tipoMensajeInicial = 'warning';
} elseif (isset($_GET['logout'])) {
    $mensajeInicial = 'La sesión se cerró correctamente.';
    $tipoMensajeInicial = 'success';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#071c31">
    <meta name="color-scheme" content="light">
    <meta name="description" content="Acceso al Sistema de Mantenimiento de Los Chapeteados División Petfood">
    <title>Iniciar sesión | Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="css/style_login.css?v=<?php echo htmlspecialchars($versionCss, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <div class="page-loader" id="pageLoader" aria-hidden="true">
        <span class="page-loader__mark">
            <span></span><span></span><span></span>
        </span>
    </div>

    <div class="ambient ambient--one" aria-hidden="true"></div>
    <div class="ambient ambient--two" aria-hidden="true"></div>
    <div class="ambient-grid" aria-hidden="true"></div>

    <main class="login-shell" aria-labelledby="loginTitle">
        <section class="brand-panel" id="brandPanel" aria-label="Información del sistema">
            <div class="brand-panel__glow" aria-hidden="true"></div>
            <div class="brand-panel__noise" aria-hidden="true"></div>

            <header class="brand-topbar reveal reveal--1">
                <a class="brand-identity" href="login.php" aria-label="Sistema de Mantenimiento, página de acceso">
                    <span class="brand-identity__logo">
                        <img
                            src="imagenes/logo_chapeteadosss.png"
                            alt="Los Chapeteados División Petfood"
                            draggable="false"
                        >
                    </span>
                    <span class="brand-identity__text">
                        <strong>Los Chapeteados</strong>
                        <small>División Petfood</small>
                    </span>
                </a>

                <span class="system-badge">
                    <span class="system-badge__pulse" aria-hidden="true"></span>
                    Plataforma interna
                </span>
            </header>

            <div class="brand-copy">
                <div class="eyebrow reveal reveal--2">
                    <span class="eyebrow__line" aria-hidden="true"></span>
                    Gestión operacional inteligente
                </div>

                <h1 class="reveal reveal--3">
                    Mantenimiento bajo control,
                    <span>de principio a fin.</span>
                </h1>

                <p class="brand-lead reveal reveal--4">
                    Centraliza solicitudes, asignaciones y avances en una plataforma diseñada para mantener cada proceso visible, ordenado y seguro.
                </p>

                <div class="brand-features reveal reveal--5" aria-label="Beneficios principales">
                    <article class="feature-card">
                        <span class="feature-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M12 3.5a8.5 8.5 0 1 0 8.5 8.5" />
                                <path d="M12 7v5l3.25 2" />
                                <path d="M16.5 3.5h4v4" />
                                <path d="m20.5 3.5-4.25 4.25" />
                            </svg>
                        </span>
                        <span>
                            <strong>Seguimiento en tiempo real</strong>
                            <small>Estado, responsables y tiempos siempre visibles.</small>
                        </span>
                    </article>

                    <article class="feature-card">
                        <span class="feature-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M12 3 5 6v5c0 4.45 2.85 8.37 7 9.75 4.15-1.38 7-5.3 7-9.75V6l-7-3Z" />
                                <path d="m9 12 2 2 4-4" />
                            </svg>
                        </span>
                        <span>
                            <strong>Acceso seguro por perfil</strong>
                            <small>Cada persona ve únicamente lo que necesita.</small>
                        </span>
                    </article>
                </div>
            </div>

            <div class="operations-visual reveal reveal--6" aria-hidden="true">
                <div class="visual-orbit visual-orbit--outer"></div>
                <div class="visual-orbit visual-orbit--inner"></div>

                <div class="visual-panel">
                    <div class="visual-panel__top">
                        <span class="visual-panel__title">
                            <i></i>
                            Operación activa
                        </span>
                        <span class="visual-panel__live">EN LÍNEA</span>
                    </div>

                    <div class="visual-panel__metrics">
                        <div class="visual-metric visual-metric--main">
                            <span class="visual-metric__icon">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M14.7 6.3a4 4 0 0 0-5 5l-6.4 6.4a1.4 1.4 0 0 0 0 2l1 1a1.4 1.4 0 0 0 2 0l6.4-6.4a4 4 0 0 0 5-5l-2.35 2.35-3-3L14.7 6.3Z" />
                                </svg>
                            </span>
                            <span>
                                <small>Flujo centralizado</small>
                                <strong>Solicitudes y mantenimiento</strong>
                            </span>
                        </div>

                        <div class="visual-metric">
                            <small>Disponibilidad</small>
                            <strong>24/7</strong>
                        </div>

                        <div class="visual-metric">
                            <small>Perfiles</small>
                            <strong>3</strong>
                        </div>
                    </div>

                    <div class="visual-progress">
                        <div class="visual-progress__header">
                            <span>Control del proceso</span>
                            <strong>100%</strong>
                        </div>
                        <div class="visual-progress__track"><span></span></div>
                    </div>
                </div>

                <span class="floating-chip floating-chip--one">
                    <svg viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6" /></svg>
                    Trazabilidad
                </span>
                <span class="floating-chip floating-chip--two">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 3v4M12 17v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M3 12h4M17 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83" /><circle cx="12" cy="12" r="3" /></svg>
                    Operación
                </span>
            </div>

            <footer class="brand-footer reveal reveal--7">
                <span>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" />
                        <path d="M3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" />
                    </svg>
                    Sistema de Mantenimiento
                </span>
                <span>© <?php echo date('Y'); ?> Los Chapeteados</span>
            </footer>
        </section>

        <section class="form-panel">
            <div class="form-panel__decor form-panel__decor--one" aria-hidden="true"></div>
            <div class="form-panel__decor form-panel__decor--two" aria-hidden="true"></div>

            <div class="login-card" id="loginCard">
                <header class="mobile-brand reveal reveal--1">
                    <span class="mobile-brand__logo">
                        <img
                            src="imagenes/logo_chapeteadosss.png"
                            alt="Los Chapeteados División Petfood"
                            draggable="false"
                        >
                    </span>
                    <span class="mobile-brand__status">
                        <i aria-hidden="true"></i>
                        Sistema en línea
                    </span>
                </header>

                <div class="login-header reveal reveal--2">
                    <div class="login-kicker">
                        <span class="login-kicker__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <rect x="4" y="10" width="16" height="11" rx="3" />
                                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                                <path d="M12 14v3" />
                            </svg>
                        </span>
                        Acceso protegido
                    </div>

                    <h2 id="loginTitle">Bienvenido de nuevo</h2>
                    <p>Ingresa tus credenciales para continuar al panel correspondiente a tu perfil.</p>
                </div>

                <div
                    id="mensajeLogin"
                    class="login-message"
                    role="alert"
                    aria-live="polite"
                    hidden
                >
                    <span id="mensajeLoginIcono" class="message-icon" aria-hidden="true"></span>
                    <span class="message-content">
                        <strong id="mensajeLoginTitulo">Aviso</strong>
                        <span id="mensajeLoginTexto"></span>
                    </span>
                    <button type="button" id="cerrarMensaje" class="message-close" aria-label="Cerrar aviso">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="m7 7 10 10M17 7 7 17" />
                        </svg>
                    </button>
                </div>

                <form id="formLogin" class="login-form reveal reveal--3" autocomplete="on" novalidate>
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($csrfLogin, ENT_QUOTES, 'UTF-8'); ?>"
                    >

                    <div class="form-group">
                        <div class="field-heading">
                            <label for="usuario">Usuario</label>
                            <span>Cuenta asignada</span>
                        </div>

                        <div class="input-wrap" id="wrapUsuario">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="8" r="4" />
                                    <path d="M4.5 20a7.5 7.5 0 0 1 15 0" />
                                </svg>
                            </span>
                            <input
                                type="text"
                                id="usuario"
                                name="usuario"
                                minlength="1"
                                maxlength="60"
                                autocomplete="username"
                                autocapitalize="none"
                                spellcheck="false"
                                placeholder="Escribe tu usuario"
                                aria-describedby="ayudaUsuario errorUsuario"
                                required
                            >
                            <span class="input-state" aria-hidden="true">
                                <svg class="input-state__ok" viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6" /></svg>
                                <svg class="input-state__error" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" /><path d="M12 7v6M12 17h.01" /></svg>
                            </span>
                        </div>

                        <small class="field-help" id="ayudaUsuario">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></svg>
                            Sin espacios al inicio o al final.
                        </small>
                        <small class="field-error" id="errorUsuario" aria-live="polite"></small>
                    </div>

                    <div class="form-group">
                        <div class="field-heading">
                            <label for="password">Contraseña</label>
                            <span>Acceso personal</span>
                        </div>

                        <div class="input-wrap" id="wrapPassword">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="4" y="10" width="16" height="11" rx="3" />
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                                </svg>
                            </span>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                maxlength="255"
                                autocomplete="current-password"
                                placeholder="Escribe tu contraseña"
                                aria-describedby="capsWarning errorPassword"
                                required
                            >
                            <button
                                type="button"
                                id="btnMostrarPassword"
                                class="password-toggle"
                                aria-label="Mostrar contraseña"
                                aria-pressed="false"
                            >
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                                    <circle cx="12" cy="12" r="2.75" />
                                </svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="m3 3 18 18" />
                                    <path d="M10.6 6.15A10.8 10.8 0 0 1 12 6c6 0 9.5 6 9.5 6a15.8 15.8 0 0 1-2.1 2.75M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.5 6 9.5 6a9.8 9.8 0 0 0 3.1-.5" />
                                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                                </svg>
                            </button>
                        </div>

                        <small class="caps-warning" id="capsWarning" hidden>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m12 3 9 16H3L12 3Z" /><path d="M12 9v4M12 16h.01" /></svg>
                            Bloq Mayús está activado.
                        </small>
                        <small class="field-error" id="errorPassword" aria-live="polite"></small>
                    </div>

                    <button type="submit" id="btnLogin" class="login-button">
                        <span class="login-button__shine" aria-hidden="true"></span>
                        <span class="button-normal">
                            <span>Iniciar sesión</span>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5" /></svg>
                        </span>
                        <span class="button-loading" hidden>
                            <span class="spinner" aria-hidden="true"></span>
                            Validando acceso...
                        </span>
                    </button>
                </form>

                <div class="security-note reveal reveal--4">
                    <span class="security-note__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 3 5 6v5c0 4.45 2.85 8.37 7 9.75 4.15-1.38 7-5.3 7-9.75V6l-7-3Z" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                    </span>
                    <span>
                        <strong>Conexión protegida</strong>
                        <small>Tus credenciales se procesan de forma segura y el acceso queda registrado.</small>
                    </span>
                </div>

                <aside class="support-box reveal reveal--5">
                    <span class="support-box__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M9.75 9a2.35 2.35 0 1 1 3.65 1.95c-.82.54-1.4 1-1.4 2.05M12 17h.01" />
                        </svg>
                    </span>
                    <span>
                        <strong>¿Problemas para ingresar?</strong>
                        <small>Solicita al administrador la revisión de tu cuenta o el restablecimiento de tus datos.</small>
                    </span>
                </aside>

                <footer class="login-footer reveal reveal--6">
                    <span>Uso exclusivo de personal autorizado</span>
                    <span class="login-footer__status"><i aria-hidden="true"></i> Servicios disponibles</span>
                </footer>
            </div>
        </section>
    </main>

    <noscript>
        <div class="noscript-message">
            Debes habilitar JavaScript para iniciar sesión.
        </div>
    </noscript>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    (function () {
        'use strict';

        var form = document.getElementById('formLogin');
        var usuario = document.getElementById('usuario');
        var password = document.getElementById('password');
        var btnLogin = document.getElementById('btnLogin');
        var btnMostrarPassword = document.getElementById('btnMostrarPassword');
        var mensaje = document.getElementById('mensajeLogin');
        var mensajeTexto = document.getElementById('mensajeLoginTexto');
        var mensajeTitulo = document.getElementById('mensajeLoginTitulo');
        var mensajeIcono = document.getElementById('mensajeLoginIcono');
        var cerrarMensaje = document.getElementById('cerrarMensaje');
        var capsWarning = document.getElementById('capsWarning');
        var loginCard = document.getElementById('loginCard');
        var brandPanel = document.getElementById('brandPanel');
        var pageLoader = document.getElementById('pageLoader');
        var enviando = false;
        var temporizadorBloqueo = null;
        var temporizadorMensaje = null;

        var mensajeInicial = <?php echo json_encode($mensajeInicial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var tipoMensajeInicial = <?php echo json_encode($tipoMensajeInicial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        document.addEventListener('DOMContentLoaded', function () {
            window.setTimeout(function () {
                document.body.classList.add('is-ready');
                if (pageLoader) {
                    pageLoader.classList.add('is-hidden');
                }
            }, 120);

            usuario.focus({ preventScroll: true });

            if (mensajeInicial) {
                mostrarMensaje(mensajeInicial, tipoMensajeInicial);
            }
        });

        window.addEventListener('pageshow', function () {
            if (!temporizadorBloqueo) {
                cambiarCarga(false);
            }
        });

        if (brandPanel && window.matchMedia('(pointer: fine)').matches) {
            brandPanel.addEventListener('pointermove', function (event) {
                var rect = brandPanel.getBoundingClientRect();
                var x = ((event.clientX - rect.left) / rect.width) * 100;
                var y = ((event.clientY - rect.top) / rect.height) * 100;
                brandPanel.style.setProperty('--pointer-x', x.toFixed(2) + '%');
                brandPanel.style.setProperty('--pointer-y', y.toFixed(2) + '%');
            });
        }

        cerrarMensaje.addEventListener('click', ocultarMensaje);

        btnMostrarPassword.addEventListener('click', function () {
            var visible = password.type === 'text';
            password.type = visible ? 'password' : 'text';
            btnMostrarPassword.classList.toggle('is-visible', !visible);
            btnMostrarPassword.setAttribute('aria-pressed', visible ? 'false' : 'true');
            btnMostrarPassword.setAttribute(
                'aria-label',
                visible ? 'Mostrar contraseña' : 'Ocultar contraseña'
            );
            password.focus({ preventScroll: true });
        });

        password.addEventListener('keyup', revisarMayusculas);
        password.addEventListener('keydown', revisarMayusculas);
        password.addEventListener('blur', function () {
            capsWarning.hidden = true;
        });

        [usuario, password].forEach(function (campo) {
            campo.addEventListener('input', function () {
                limpiarCampo(campo);
                actualizarEstadoCampo(campo);

                if (mensaje.classList.contains('is-error')) {
                    ocultarMensaje();
                }
            });

            campo.addEventListener('blur', function () {
                actualizarEstadoCampo(campo);
            });
        });

        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            if (enviando) {
                return;
            }

            ocultarMensaje();
            limpiarCampo(usuario);
            limpiarCampo(password);

            var usuarioValor = usuario.value.trim();
            var passwordValor = password.value;
            var formularioValido = true;

            if (usuarioValor === '') {
                marcarCampo(usuario, 'Ingresa tu usuario.');
                formularioValido = false;
            } else if (usuarioValor.length > 60) {
                marcarCampo(usuario, 'El usuario no puede superar 60 caracteres.');
                formularioValido = false;
            } else if (!/^[A-Za-z0-9._-]+$/.test(usuarioValor)) {
                marcarCampo(usuario, 'El usuario contiene caracteres no permitidos.');
                formularioValido = false;
            }

            if (passwordValor === '') {
                marcarCampo(password, 'Ingresa tu contraseña.');
                formularioValido = false;
            } else if (passwordValor.length > 255) {
                marcarCampo(password, 'La contraseña no es válida.');
                formularioValido = false;
            }

            if (!formularioValido) {
                mostrarMensaje('Revisa los campos marcados antes de continuar.', 'error');
                loginCard.classList.remove('is-shaking');
                void loginCard.offsetWidth;
                loginCard.classList.add('is-shaking');
                return;
            }

            usuario.value = usuarioValor;
            cambiarCarga(true);

            try {
                var datos = new FormData(form);
                datos.set('usuario', usuarioValor);
                datos.set('password', passwordValor);

                var respuesta = await fetch('funciones/login_funciones.php', {
                    method: 'POST',
                    body: datos,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                var texto = await respuesta.text();
                var data;

                try {
                    data = texto ? JSON.parse(texto) : {};
                } catch (errorJson) {
                    throw new Error('El servidor devolvió una respuesta inválida.');
                }

                if (respuesta.status === 419 || data.csrf_invalido) {
                    await mostrarSweetAlert(
                        'Formulario vencido',
                        data.mensaje || 'Recarga la página para continuar.',
                        'warning'
                    );
                    window.location.reload();
                    return;
                }

                if (respuesta.status === 429) {
                    var segundos = Number(data.espera_segundos || 60);
                    mostrarMensaje(
                        data.mensaje || 'Espera antes de volver a intentarlo.',
                        'warning'
                    );
                    iniciarBloqueo(segundos);
                    return;
                }

                if (!respuesta.ok || data.success !== true) {
                    var mensajeError = data.mensaje || 'No fue posible iniciar sesión.';
                    mostrarMensaje(mensajeError, 'error');

                    loginCard.classList.remove('is-shaking');
                    void loginCard.offsetWidth;
                    loginCard.classList.add('is-shaking');

                    if (data.campo === 'usuario') {
                        marcarCampo(usuario, mensajeError);
                    } else if (data.campo === 'password') {
                        marcarCampo(password, mensajeError);
                    } else {
                        password.value = '';
                        actualizarEstadoCampo(password);
                        password.focus({ preventScroll: true });
                    }

                    return;
                }

                mostrarMensaje(
                    data.mensaje || 'Inicio de sesión exitoso.',
                    'success'
                );

                loginCard.classList.add('is-success');
                btnLogin.classList.add('is-success');
                btnLogin.querySelector('.button-loading').innerHTML =
                    '<span class="button-success-icon" aria-hidden="true">' +
                    '<svg viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6"></path></svg>' +
                    '</span> Acceso correcto';

                window.setTimeout(function () {
                    window.location.assign(data.redirect || 'login.php');
                }, 700);

            } catch (error) {
                var textoError = error && error.message
                    ? error.message
                    : 'No fue posible comunicarse con el servidor.';

                mostrarMensaje(textoError, 'error');
                await mostrarSweetAlert('No se pudo iniciar sesión', textoError, 'error');
            } finally {
                if (!temporizadorBloqueo && !loginCard.classList.contains('is-success')) {
                    cambiarCarga(false);
                }
            }
        });

        function revisarMayusculas(event) {
            if (!event || typeof event.getModifierState !== 'function') {
                capsWarning.hidden = true;
                return;
            }

            capsWarning.hidden = !event.getModifierState('CapsLock');
        }

        function actualizarEstadoCampo(campo) {
            var contenedor = campo.closest('.input-wrap');

            if (!contenedor || contenedor.classList.contains('is-invalid')) {
                return;
            }

            var tieneValor = campo.id === 'usuario'
                ? campo.value.trim() !== ''
                : campo.value !== '';

            contenedor.classList.toggle('has-value', tieneValor);
        }

        function marcarCampo(campo, texto) {
            var contenedor = campo.closest('.input-wrap');
            var error = document.getElementById(
                campo.id === 'usuario' ? 'errorUsuario' : 'errorPassword'
            );

            if (contenedor) {
                contenedor.classList.remove('has-value');
                contenedor.classList.add('is-invalid');
            }

            campo.setAttribute('aria-invalid', 'true');
            error.textContent = texto;

            if (document.activeElement !== campo) {
                campo.focus({ preventScroll: true });
            }
        }

        function limpiarCampo(campo) {
            var contenedor = campo.closest('.input-wrap');
            var error = document.getElementById(
                campo.id === 'usuario' ? 'errorUsuario' : 'errorPassword'
            );

            if (contenedor) {
                contenedor.classList.remove('is-invalid');
            }

            campo.removeAttribute('aria-invalid');
            error.textContent = '';
        }

        function mostrarMensaje(texto, tipo) {
            if (temporizadorMensaje) {
                window.clearTimeout(temporizadorMensaje);
                temporizadorMensaje = null;
            }

            var tipoFinal = tipo || 'info';
            var titulos = {
                success: 'Acceso confirmado',
                warning: 'Atención',
                error: 'No fue posible continuar',
                info: 'Información'
            };

            var iconos = {
                success: '<svg viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6"></path></svg>',
                warning: '<svg viewBox="0 0 24 24" fill="none"><path d="m12 3 9 16H3L12 3Z"></path><path d="M12 9v4M12 16h.01"></path></svg>',
                error: '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"></circle><path d="m9 9 6 6M15 9l-6 6"></path></svg>',
                info: '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8h.01"></path></svg>'
            };

            mensaje.hidden = false;
            mensaje.className = 'login-message is-' + tipoFinal;
            mensajeTitulo.textContent = titulos[tipoFinal] || titulos.info;
            mensajeTexto.textContent = texto;
            mensajeIcono.innerHTML = iconos[tipoFinal] || iconos.info;

            window.requestAnimationFrame(function () {
                mensaje.classList.add('is-visible');
            });
        }

        function ocultarMensaje() {
            if (temporizadorMensaje) {
                window.clearTimeout(temporizadorMensaje);
                temporizadorMensaje = null;
            }

            if (mensaje.hidden) {
                return;
            }

            mensaje.classList.remove('is-visible');

            temporizadorMensaje = window.setTimeout(function () {
                mensaje.hidden = true;
                mensaje.className = 'login-message';
                mensajeTexto.textContent = '';
                mensajeTitulo.textContent = 'Aviso';
                mensajeIcono.innerHTML = '';
                temporizadorMensaje = null;
            }, 180);
        }

        function cambiarCarga(cargando) {
            enviando = cargando;
            btnLogin.disabled = cargando;
            btnLogin.querySelector('.button-normal').hidden = cargando;
            btnLogin.querySelector('.button-loading').hidden = !cargando;
            usuario.readOnly = cargando;
            password.readOnly = cargando;
            form.setAttribute('aria-busy', cargando ? 'true' : 'false');
        }

        function iniciarBloqueo(segundos) {
            if (temporizadorBloqueo) {
                window.clearInterval(temporizadorBloqueo);
            }

            var restantes = Math.max(1, Math.floor(segundos));
            cambiarCarga(false);
            enviando = true;
            usuario.readOnly = false;
            password.readOnly = false;
            btnLogin.disabled = true;
            btnLogin.classList.add('is-locked');

            function pintar() {
                btnLogin.querySelector('.button-normal').hidden = false;
                btnLogin.querySelector('.button-loading').hidden = true;
                btnLogin.querySelector('.button-normal').innerHTML =
                    '<span>Espera ' + restantes + ' s</span>' +
                    '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 7v5l3 2"></path><circle cx="12" cy="12" r="9"></circle></svg>';
            }

            pintar();

            temporizadorBloqueo = window.setInterval(function () {
                restantes -= 1;

                if (restantes <= 0) {
                    window.clearInterval(temporizadorBloqueo);
                    temporizadorBloqueo = null;
                    enviando = false;
                    btnLogin.disabled = false;
                    btnLogin.classList.remove('is-locked');
                    btnLogin.querySelector('.button-normal').innerHTML =
                        '<span>Iniciar sesión</span>' +
                        '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"></path></svg>';
                    ocultarMensaje();
                    return;
                }

                pintar();
            }, 1000);
        }

        async function mostrarSweetAlert(titulo, texto, icono) {
            if (typeof Swal === 'undefined') {
                return;
            }

            await Swal.fire({
                icon: icono || 'info',
                title: titulo,
                text: texto,
                confirmButtonText: 'Entendido',
                allowOutsideClick: false,
                allowEscapeKey: true,
                heightAuto: false,
                buttonsStyling: false,
                customClass: {
                    popup: 'sm-swal-popup',
                    title: 'sm-swal-title',
                    htmlContainer: 'sm-swal-text',
                    confirmButton: 'sm-swal-confirm'
                }
            });
        }
    })();
    </script>
</body>
</html>