(function (window, document) {
    'use strict';

    var config = window.__SISTEMA_UI_CONFIG__ || {};
    var currentDialog = null;
    var toastRegion = null;
    var loadingDialog = null;

    var ICONS = {
        success: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.1 4.1L19.5 6.2"></path></svg>',
        error: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"></circle><path d="m8.8 8.8 6.4 6.4M15.2 8.8l-6.4 6.4"></path></svg>',
        warning: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.2 21 19H3L12 3.2Z"></path><path d="M12 9v4.5M12 16.8h.01"></path></svg>',
        info: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"></circle><path d="M12 10.7v5M12 7.7h.01"></path></svg>',
        question: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"></circle><path d="M9.7 9a2.5 2.5 0 0 1 4.8 1c0 1.8-2.5 2-2.5 3.8M12 17h.01"></path></svg>'
    };

    function normalizeType(type) {
        var value = String(type || 'info').toLowerCase();
        if (value === 'danger' || value === 'fail' || value === 'failed') return 'error';
        if (value === 'warn' || value === 'alert') return 'warning';
        if (value === 'ok' || value === 'done') return 'success';
        return ICONS[value] ? value : 'info';
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function classNames() {
        return Array.prototype.slice.call(arguments)
            .filter(Boolean)
            .join(' ')
            .trim();
    }

    function customClassValue(customClass, key) {
        /* Las clases heredadas se ignoran deliberadamente: el tema maestro
           controla todas las alertas para evitar estilos distintos por módulo. */
        void customClass;
        void key;
        return '';
    }

    function ensureToastRegion() {
        if (toastRegion && document.body.contains(toastRegion)) return toastRegion;
        toastRegion = document.createElement('div');
        toastRegion.className = 'sm-ui-toast-region';
        toastRegion.setAttribute('aria-live', 'polite');
        toastRegion.setAttribute('aria-atomic', 'false');
        document.body.appendChild(toastRegion);
        return toastRegion;
    }

    function toast(message, type, options) {
        var opts = options || {};
        var finalType = normalizeType(type || opts.icon);
        var region = ensureToastRegion();
        var item = document.createElement('div');
        var duration = Number(opts.timer || opts.duration || 3800);
        var title = opts.title || (
            finalType === 'success' ? 'Operación realizada' :
            finalType === 'error' ? 'No fue posible continuar' :
            finalType === 'warning' ? 'Atención' : 'Información'
        );

        item.className = classNames(
            'sm-ui-toast',
            'sm-ui-toast--' + finalType,
            customClassValue(opts.customClass, 'popup')
        );
        item.setAttribute('role', finalType === 'error' ? 'alert' : 'status');
        item.innerHTML =
            '<span class="sm-ui-toast__icon">' + ICONS[finalType] + '</span>' +
            '<span class="sm-ui-toast__copy">' +
                '<strong>' + escapeHtml(title) + '</strong>' +
                '<span>' + escapeHtml(message || '') + '</span>' +
            '</span>' +
            '<button type="button" class="sm-ui-toast__close" aria-label="Cerrar aviso">×</button>' +
            (opts.timerProgressBar === false ? '' : '<span class="sm-ui-toast__progress"></span>');

        region.appendChild(item);
        var closeButton = item.querySelector('.sm-ui-toast__close');
        var progress = item.querySelector('.sm-ui-toast__progress');
        var closed = false;
        var timer = null;

        function closeToast() {
            if (closed) return;
            closed = true;
            window.clearTimeout(timer);
            item.classList.remove('is-visible');
            item.classList.add('is-leaving');
            window.setTimeout(function () {
                if (item.parentNode) item.parentNode.removeChild(item);
            }, 240);
        }

        closeButton.addEventListener('click', closeToast);
        window.requestAnimationFrame(function () {
            item.classList.add('is-visible');
            if (progress && duration > 0) {
                progress.style.animationDuration = duration + 'ms';
            }
        });

        if (duration > 0) timer = window.setTimeout(closeToast, duration);
        return { close: closeToast, element: item };
    }

    function focusableElements(root) {
        return Array.prototype.slice.call(root.querySelectorAll(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(function (element) {
            return !element.hidden && element.offsetParent !== null;
        });
    }

    function createInput(options, popup) {
        if (!options.input) return null;
        var group = document.createElement('label');
        group.className = 'sm-ui-dialog__field';

        if (options.inputLabel) {
            var label = document.createElement('span');
            label.className = 'sm-ui-dialog__label';
            label.textContent = options.inputLabel;
            group.appendChild(label);
        }

        var input;
        if (options.input === 'textarea') {
            input = document.createElement('textarea');
        } else if (options.input === 'select') {
            input = document.createElement('select');
            var inputOptions = options.inputOptions || {};
            Object.keys(inputOptions).forEach(function (key) {
                var option = document.createElement('option');
                option.value = key;
                option.textContent = inputOptions[key];
                input.appendChild(option);
            });
        } else {
            input = document.createElement('input');
            input.type = options.input === 'email' || options.input === 'number' || options.input === 'password'
                ? options.input
                : 'text';
        }

        input.className = classNames('sm-ui-dialog__input', customClassValue(options.customClass, 'input'));
        input.placeholder = options.inputPlaceholder || '';
        input.value = options.inputValue == null ? '' : String(options.inputValue);

        var attributes = options.inputAttributes || {};
        Object.keys(attributes).forEach(function (name) {
            input.setAttribute(name, String(attributes[name]));
        });

        group.appendChild(input);
        popup.querySelector('.sm-ui-dialog__content').appendChild(group);
        return input;
    }

    function fire(first, second, third) {
        var options = typeof first === 'object' && first !== null
            ? Object.assign({}, first)
            : { title: first, html: second, icon: third };

        if (options.toast) {
            toast(options.text || options.title || '', options.icon || 'info', {
                title: options.text ? options.title : undefined,
                timer: options.timer,
                timerProgressBar: options.timerProgressBar,
                customClass: options.customClass
            });
            return Promise.resolve({ isConfirmed: false, isDismissed: true, isDenied: false, value: undefined });
        }

        return new Promise(function (resolve) {
            if (!document.body) {
                document.addEventListener('DOMContentLoaded', function () {
                    fire(options).then(resolve);
                }, { once: true });
                return;
            }

            if (currentDialog && typeof currentDialog.close === 'function') {
                currentDialog.close('replaced');
            }

            var previousFocus = document.activeElement;
            var iconType = normalizeType(options.icon || 'info');
            var overlay = document.createElement('div');
            var popup = document.createElement('section');
            var icon = document.createElement('div');
            var title = document.createElement('h2');
            var content = document.createElement('div');
            var validation = document.createElement('div');
            var actions = document.createElement('div');
            var cancelButton = document.createElement('button');
            var confirmButton = document.createElement('button');
            var input = null;
            var settled = false;
            var timer = null;
            var validationText = '';

            overlay.className = classNames('sm-ui-dialog', customClassValue(options.customClass, 'container'));
            popup.className = classNames('sm-ui-dialog__popup', 'sm-ui-dialog__popup--' + iconType, customClassValue(options.customClass, 'popup'));
            popup.setAttribute('role', 'dialog');
            popup.setAttribute('aria-modal', 'true');
            popup.setAttribute('aria-labelledby', 'smUiDialogTitle');
            if (options.width) popup.style.maxWidth = typeof options.width === 'number' ? options.width + 'px' : String(options.width);

            icon.className = 'sm-ui-dialog__icon sm-ui-dialog__icon--' + iconType;
            icon.innerHTML = ICONS[iconType];

            title.id = 'smUiDialogTitle';
            title.className = classNames('sm-ui-dialog__title', customClassValue(options.customClass, 'title'));
            title.textContent = options.title || '';

            content.className = classNames('sm-ui-dialog__content', customClassValue(options.customClass, 'htmlContainer'));
            if (options.html != null) content.innerHTML = String(options.html);
            else if (options.text != null) content.textContent = String(options.text);

            validation.className = 'sm-ui-dialog__validation';
            validation.hidden = true;
            validation.setAttribute('role', 'alert');

            actions.className = classNames('sm-ui-dialog__actions', customClassValue(options.customClass, 'actions'));
            cancelButton.type = 'button';
            cancelButton.className = classNames('sm-ui-dialog__button', 'sm-ui-dialog__button--cancel', customClassValue(options.customClass, 'cancelButton'));
            cancelButton.textContent = options.cancelButtonText || 'Cancelar';
            confirmButton.type = 'button';
            confirmButton.className = classNames(
                'sm-ui-dialog__button',
                options.confirmButtonColor || options.dangerMode ? 'sm-ui-dialog__button--danger' : 'sm-ui-dialog__button--confirm',
                customClassValue(options.customClass, 'confirmButton')
            );
            confirmButton.textContent = options.confirmButtonText || 'Aceptar';
            if (options.confirmButtonColor) confirmButton.style.setProperty('--sm-ui-confirm-color', String(options.confirmButtonColor));

            popup.appendChild(icon);
            if (options.title) popup.appendChild(title);
            popup.appendChild(content);
            input = createInput(options, popup);
            popup.appendChild(validation);

            var reverse = options.reverseButtons !== false;
            if (options.showCancelButton) {
                if (reverse) actions.appendChild(cancelButton);
                else actions.appendChild(confirmButton);
            }
            if (options.showConfirmButton !== false && (!options.showCancelButton || reverse)) actions.appendChild(confirmButton);
            if (options.showCancelButton && !reverse) actions.appendChild(cancelButton);
            if (options.showConfirmButton !== false || options.showCancelButton) popup.appendChild(actions);

            overlay.appendChild(popup);
            document.body.appendChild(overlay);
            document.body.classList.add('sm-ui-dialog-open');

            function setValidation(message) {
                validationText = String(message || '');
                validation.textContent = validationText;
                validation.hidden = validationText === '';
                popup.classList.toggle('has-validation', validationText !== '');
            }

            function setBusy(busy) {
                confirmButton.disabled = busy;
                cancelButton.disabled = busy;
                popup.classList.toggle('is-busy', busy);
                if (busy) {
                    if (!confirmButton.dataset.originalText) confirmButton.dataset.originalText = confirmButton.textContent;
                    confirmButton.innerHTML = '<span class="sm-ui-spinner" aria-hidden="true"></span>Procesando...';
                } else if (confirmButton.dataset.originalText) {
                    confirmButton.textContent = confirmButton.dataset.originalText;
                    delete confirmButton.dataset.originalText;
                }
            }

            function finish(result, reason) {
                if (settled) return;
                settled = true;
                window.clearTimeout(timer);
                overlay.classList.remove('is-visible');
                overlay.classList.add('is-closing');
                document.body.classList.remove('sm-ui-dialog-open');
                document.removeEventListener('keydown', onKeydown, true);

                if (typeof options.willClose === 'function') {
                    try { options.willClose(popup); } catch (error) { console.error(error); }
                }

                window.setTimeout(function () {
                    if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                    if (currentDialog && currentDialog.overlay === overlay) currentDialog = null;
                    if (options.returnFocus !== false && previousFocus && typeof previousFocus.focus === 'function') {
                        try { previousFocus.focus({ preventScroll: true }); } catch (error) { previousFocus.focus(); }
                    }
                    resolve(Object.assign({
                        isConfirmed: false,
                        isDismissed: true,
                        isDenied: false,
                        value: undefined,
                        dismiss: reason || 'dismiss'
                    }, result || {}));
                }, 220);
            }

            async function confirm() {
                setValidation('');
                var value = input ? input.value : undefined;

                if (typeof options.inputValidator === 'function') {
                    var inputError = await options.inputValidator(value);
                    if (inputError) {
                        setValidation(inputError);
                        if (input) input.focus();
                        return;
                    }
                }

                if (typeof options.preConfirm === 'function') {
                    setBusy(true);
                    window.Swal.__validationMessage = '';
                    try {
                        var result = await options.preConfirm(value);
                        if (window.Swal.__validationMessage) {
                            setValidation(window.Swal.__validationMessage);
                            setBusy(false);
                            return;
                        }
                        if (result === false) {
                            setBusy(false);
                            return;
                        }
                        if (result !== undefined) value = result;
                    } catch (error) {
                        setValidation(error && error.message ? error.message : 'No fue posible validar la información.');
                        setBusy(false);
                        return;
                    }
                    setBusy(false);
                }

                finish({ isConfirmed: true, isDismissed: false, value: value }, 'confirm');
            }

            function cancel(reason) {
                finish({ isConfirmed: false, isDismissed: true, value: undefined }, reason || 'cancel');
            }

            function onKeydown(event) {
                if (event.key === 'Escape' && options.allowEscapeKey !== false) {
                    event.preventDefault();
                    cancel('esc');
                    return;
                }

                if (event.key === 'Enter' && options.allowEnterKey !== false && event.target.tagName !== 'TEXTAREA') {
                    event.preventDefault();
                    confirm();
                    return;
                }

                if (event.key === 'Tab') {
                    var elements = focusableElements(popup);
                    if (!elements.length) return;
                    var firstElement = elements[0];
                    var lastElement = elements[elements.length - 1];
                    if (event.shiftKey && document.activeElement === firstElement) {
                        event.preventDefault();
                        lastElement.focus();
                    } else if (!event.shiftKey && document.activeElement === lastElement) {
                        event.preventDefault();
                        firstElement.focus();
                    }
                }
            }

            confirmButton.addEventListener('click', confirm);
            cancelButton.addEventListener('click', function () { cancel('cancel'); });
            overlay.addEventListener('mousedown', function (event) {
                if (event.target === overlay && options.allowOutsideClick !== false) cancel('backdrop');
            });
            document.addEventListener('keydown', onKeydown, true);

            currentDialog = {
                overlay: overlay,
                popup: popup,
                input: input,
                validation: validation,
                close: cancel,
                setValidation: setValidation
            };

            window.requestAnimationFrame(function () {
                overlay.classList.add('is-visible');
                if (typeof options.didOpen === 'function') {
                    try { options.didOpen(popup); } catch (error) { console.error(error); }
                }
                var focusTarget = options.focusCancel && options.showCancelButton
                    ? cancelButton
                    : (input || confirmButton || popup);
                if (focusTarget && typeof focusTarget.focus === 'function') focusTarget.focus();
            });

            if (Number(options.timer) > 0) {
                timer = window.setTimeout(function () { cancel('timer'); }, Number(options.timer));
            }
        });
    }

    var Swal = {
        __validationMessage: '',
        fire: fire,
        showValidationMessage: function (message) {
            Swal.__validationMessage = String(message || '');
            if (currentDialog && currentDialog.setValidation) currentDialog.setValidation(Swal.__validationMessage);
        },
        resetValidationMessage: function () {
            Swal.__validationMessage = '';
            if (currentDialog && currentDialog.setValidation) currentDialog.setValidation('');
        },
        close: function () {
            if (currentDialog && currentDialog.close) currentDialog.close('close');
        },
        isVisible: function () {
            return Boolean(currentDialog && currentDialog.overlay && document.body.contains(currentDialog.overlay));
        },
        getPopup: function () {
            return currentDialog ? currentDialog.popup : null;
        },
        getInput: function () {
            return currentDialog ? currentDialog.input : null;
        }
    };

    function mergeOptions(options) {
        return Object.assign({
            confirmButtonText: 'Aceptar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            allowOutsideClick: false,
            allowEscapeKey: true,
            heightAuto: false
        }, options || {});
    }

    function success(title, text) {
        return Swal.fire(mergeOptions({
            icon: 'success',
            title: title,
            text: text || '',
            timer: 1750,
            showConfirmButton: false
        }));
    }

    function error(title, text) {
        return Swal.fire(mergeOptions({ icon: 'error', title: title, text: text || '' }));
    }

    function warning(title, text) {
        return Swal.fire(mergeOptions({ icon: 'warning', title: title, text: text || '' }));
    }

    function info(title, text) {
        return Swal.fire(mergeOptions({ icon: 'info', title: title, text: text || '' }));
    }

    async function confirm(options) {
        var opts = options || {};
        var result = await Swal.fire(mergeOptions({
            icon: opts.icono || (opts.peligro ? 'warning' : 'question'),
            title: opts.titulo || '¿Confirmar acción?',
            text: opts.texto || '',
            html: opts.html,
            showCancelButton: true,
            confirmButtonText: opts.textoConfirmar || 'Sí, continuar',
            cancelButtonText: opts.textoCancelar || 'Cancelar',
            focusCancel: opts.peligro !== false,
            confirmButtonColor: opts.peligro ? '#b4233b' : undefined
        }));
        return result.isConfirmed;
    }

    function prepareForm(body) {
        var csrf = config.csrfToken || '';
        if (body instanceof FormData || body instanceof URLSearchParams) body.set('csrf_token', csrf);
        return body;
    }

    async function requestJson(url, options) {
        var settings = Object.assign({ method: 'GET', headers: {} }, options || {});
        settings.headers = Object.assign({
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': config.csrfToken || ''
        }, settings.headers || {});
        if (settings.body) settings.body = prepareForm(settings.body);

        var response;
        try {
            response = await fetch(url, settings);
        } catch (networkError) {
            throw new Error('No fue posible comunicarse con el servidor. Revisa la red local y vuelve a intentarlo.');
        }

        var raw = await response.text();
        var data = {};
        if (raw.trim() !== '') {
            try { data = JSON.parse(raw); }
            catch (jsonError) { throw new Error('El servidor devolvió una respuesta inválida.'); }
        }

        if (response.status === 401 || data.sesion_expirada) {
            await info('Sesión finalizada', data.mensaje || 'Debes iniciar sesión nuevamente.');
            window.location.assign(data.redirect || (config.basePath || '') + 'login.php?sesion=expirada');
            throw new Error('La sesión ya no está activa.');
        }

        if (response.status === 419 || data.csrf_invalido) {
            await warning('Formulario vencido', data.mensaje || 'Recarga la página para continuar.');
            window.location.reload();
            throw new Error('El formulario venció.');
        }

        if (!response.ok || data.success === false || data.ok === false) {
            var requestError = new Error(data.mensaje || 'No se pudo completar la operación.');
            requestError.datos = data;
            requestError.status = response.status;
            throw requestError;
        }
        return data;
    }

    function buttonState(button, loading, loadingText) {
        if (!button) return;
        if (loading) {
            if (!button.dataset.smOriginalHtml) button.dataset.smOriginalHtml = button.innerHTML;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.innerHTML = '<span class="sm-ui-spinner" aria-hidden="true"></span>' + escapeHtml(loadingText || 'Procesando...');
            return;
        }
        button.disabled = false;
        button.removeAttribute('aria-busy');
        if (button.dataset.smOriginalHtml) {
            button.innerHTML = button.dataset.smOriginalHtml;
            delete button.dataset.smOriginalHtml;
        }
    }

    function onlyNumbers(input, maxLength) {
        if (!input) return;
        var maximum = Number(maxLength || 10);
        function clean() { input.value = input.value.replace(/\D/g, '').slice(0, maximum); }
        input.setAttribute('inputmode', 'numeric');
        input.addEventListener('input', clean);
        input.addEventListener('paste', function () { window.setTimeout(clean, 0); });
    }

    function validateForm(form) {
        if (!form) return false;
        Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'), function (field) {
            field.classList.remove('is-invalid');
        });
        if (form.checkValidity()) return true;
        var invalid = form.querySelector(':invalid');
        if (invalid) {
            invalid.classList.add('is-invalid');
            invalid.focus({ preventScroll: true });
            invalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        warning('Revisa el formulario', 'Completa correctamente los campos marcados antes de continuar.');
        return false;
    }

    function loading(title, text) {
        loadingDialog = Swal.fire({
            icon: 'info',
            title: title || 'Procesando',
            text: text || 'Espera un momento…',
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false
        });
        return loadingDialog;
    }

    function closeLoading() {
        Swal.close();
        loadingDialog = null;
    }

    window.Swal = Swal;
    window.SistemaUI = Object.freeze({
        csrfToken: config.csrfToken || '',
        exito: success,
        error: error,
        advertencia: warning,
        info: info,
        confirmar: confirm,
        toast: toast,
        cargando: loading,
        cerrarCargando: closeLoading,
        peticionJson: requestJson,
        estadoBoton: buttonState,
        soloNumeros: onlyNumbers,
        validarFormulario: validateForm,
        escaparHtml: escapeHtml
    });
})(window, document);
