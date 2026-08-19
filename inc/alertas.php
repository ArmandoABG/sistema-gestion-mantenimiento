<?php

declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';

$smCsrfToken = sm_token_csrf();

?>

<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11"
></script>

<script>
(function () {
    'use strict';

    const csrfToken = <?php
        echo json_encode(
            $smCsrfToken,
            JSON_UNESCAPED_SLASHES
        );
    ?>;

    const configuracionComun = {
        confirmButtonText: 'Aceptar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        allowOutsideClick: false,
        heightAuto: false
    };

    function combinar(opciones) {
        return Object.assign(
            {},
            configuracionComun,
            opciones || {}
        );
    }

    async function exito(
        titulo,
        texto = ''
    ) {
        return Swal.fire(
            combinar({
                icon: 'success',
                title: titulo,
                text: texto,
                timer: 1800,
                timerProgressBar: true,
                showConfirmButton: false
            })
        );
    }

    async function error(
        titulo,
        texto = ''
    ) {
        return Swal.fire(
            combinar({
                icon: 'error',
                title: titulo,
                text: texto
            })
        );
    }

    async function advertencia(
        titulo,
        texto = ''
    ) {
        return Swal.fire(
            combinar({
                icon: 'warning',
                title: titulo,
                text: texto
            })
        );
    }

    async function confirmar({
        titulo = '¿Confirmar acción?',
        texto = '',
        textoConfirmar = 'Sí, continuar',
        icono = 'warning',
        peligro = false
    } = {}) {
        const opciones = {
            icon: icono,
            title: titulo,
            text: texto,
            showCancelButton: true,
            confirmButtonText:
                textoConfirmar,

            cancelButtonText:
                'Cancelar',

            focusCancel:
                peligro
        };

        if (peligro) {
            opciones.confirmButtonColor =
                '#c0392b';
        }

        const resultado = await Swal.fire(
            combinar(opciones)
        );

        return resultado.isConfirmed;
    }

    function prepararFormulario(
        formData
    ) {
        if (
            formData instanceof FormData
        ) {
            formData.set(
                'csrf_token',
                csrfToken
            );
        }

        if (
            formData
            instanceof URLSearchParams
        ) {
            formData.set(
                'csrf_token',
                csrfToken
            );
        }

        return formData;
    }

    async function peticionJson(
        url,
        opciones = {}
    ) {
        const configuracion =
            Object.assign(
                {
                    method: 'GET',
                    headers: {}
                },
                opciones
            );

        configuracion.headers =
            Object.assign(
                {
                    'X-Requested-With':
                        'XMLHttpRequest',

                    'X-CSRF-Token':
                        csrfToken
                },
                opciones.headers || {}
            );

        if (configuracion.body) {
            configuracion.body =
                prepararFormulario(
                    configuracion.body
                );
        }

        let respuesta;

        try {
            respuesta = await fetch(
                url,
                configuracion
            );
        } catch (falloRed) {
            throw new Error(
                'No fue posible comunicarse '
                + 'con el servidor. '
                + 'Revisa tu conexión.'
            );
        }

        const texto =
            await respuesta.text();

        let datos = {};

        if (texto.trim() !== '') {
            try {
                datos = JSON.parse(
                    texto
                );
            } catch (errorJson) {
                throw new Error(
                    'El servidor devolvió '
                    + 'una respuesta inválida.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Sesión expirada
        |--------------------------------------------------------------------------
        */

        if (
            respuesta.status === 401
            || datos.sesion_expirada
        ) {
            await Swal.fire(
                combinar({
                    icon: 'info',
                    title:
                        'Sesión finalizada',

                    text:
                        datos.mensaje
                        || 'Debes iniciar sesión nuevamente.',

                    confirmButtonText:
                        'Ir al inicio de sesión'
                })
            );

            window.location.assign(
                datos.redirect
                || '../login.php?sesion=expirada'
            );

            throw new Error(
                'La sesión ya no está activa.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Token CSRF vencido
        |--------------------------------------------------------------------------
        */

        if (
            respuesta.status === 419
            || datos.csrf_invalido
        ) {
            await Swal.fire(
                combinar({
                    icon: 'warning',

                    title:
                        'Formulario vencido',

                    text:
                        datos.mensaje
                        || 'Recarga la página para continuar.',

                    confirmButtonText:
                        'Recargar'
                })
            );

            window.location.reload();

            throw new Error(
                'El formulario venció.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Error del servidor o de validación
        |--------------------------------------------------------------------------
        */

        if (
            !respuesta.ok
            || datos.success === false
        ) {
            const errorPeticion =
                new Error(
                    datos.mensaje
                    || 'No se pudo completar la operación.'
                );

            errorPeticion.datos =
                datos;

            errorPeticion.status =
                respuesta.status;

            throw errorPeticion;
        }

        return datos;
    }

    function estadoBoton(
        boton,
        cargando,
        textoCarga = 'Procesando...'
    ) {
        if (!boton) {
            return;
        }

        if (cargando) {
            if (
                !boton.dataset.textoOriginal
            ) {
                boton.dataset.textoOriginal =
                    boton.innerHTML;
            }

            boton.disabled = true;

            boton.setAttribute(
                'aria-busy',
                'true'
            );

            boton.innerHTML =
                '<span '
                + 'class="sm-spinner" '
                + 'aria-hidden="true">'
                + '</span>'
                + textoCarga;

            return;
        }

        boton.disabled = false;

        boton.removeAttribute(
            'aria-busy'
        );

        if (
            boton.dataset.textoOriginal
        ) {
            boton.innerHTML =
                boton.dataset.textoOriginal;

            delete boton.dataset
                .textoOriginal;
        }
    }

    function soloNumeros(
        input,
        maximo = 10
    ) {
        if (!input) {
            return;
        }

        const limpiar = function () {
            input.value =
                input.value
                    .replace(/\D/g, '')
                    .slice(0, maximo);
        };

        input.setAttribute(
            'inputmode',
            'numeric'
        );

        input.addEventListener(
            'input',
            limpiar
        );

        input.addEventListener(
            'paste',
            function () {
                window.setTimeout(
                    limpiar,
                    0
                );
            }
        );
    }

    function validarFormulario(
        formulario
    ) {
        if (!formulario) {
            return false;
        }

        formulario
            .querySelectorAll(
                '.is-invalid'
            )
            .forEach(
                function (campo) {
                    campo.classList.remove(
                        'is-invalid'
                    );
                }
            );

        if (
            formulario.checkValidity()
        ) {
            return true;
        }

        const primerCampoInvalido =
            formulario.querySelector(
                ':invalid'
            );

        if (primerCampoInvalido) {
            primerCampoInvalido
                .classList
                .add('is-invalid');

            primerCampoInvalido.focus({
                preventScroll: true
            });

            primerCampoInvalido
                .scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
        }

        advertencia(
            'Revisa el formulario',
            'Completa correctamente '
            + 'los campos marcados '
            + 'antes de continuar.'
        );

        return false;
    }

    window.SistemaUI =
        Object.freeze({
            csrfToken:
                csrfToken,

            exito:
                exito,

            error:
                error,

            advertencia:
                advertencia,

            confirmar:
                confirmar,

            peticionJson:
                peticionJson,

            estadoBoton:
                estadoBoton,

            soloNumeros:
                soloNumeros,

            validarFormulario:
                validarFormulario
        });

})();
</script>

<style>
.sm-spinner {
    width: 16px;
    height: 16px;
    margin-right: 8px;

    display: inline-block;
    vertical-align: -3px;

    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;

    animation:
        sm-girar
        0.65s
        linear
        infinite;
}

.swal2-container {
    z-index: 20000 !important;
}

@keyframes sm-girar {
    to {
        transform: rotate(360deg);
    }
}
</style>