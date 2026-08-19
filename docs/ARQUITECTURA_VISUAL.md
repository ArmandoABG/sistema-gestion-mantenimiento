# Arquitectura visual unificada

## Objetivo

Todas las interfaces usan un solo lenguaje visual, basado en el acabado de los catálogos: azul marino, acento cian, superficies claras, bordes suaves, sombras controladas y estados semánticos consistentes.

## Archivos maestros

- `css/sistema-maestro.css`: tokens de color, tipografía, accesibilidad, formularios y diseño de alertas.
- `js/sistema-ui.js`: alertas, confirmaciones, avisos tipo toast, estados de carga, validación y peticiones JSON.
- `inc/alertas.php`: cargador único de los recursos anteriores y configuración del token CSRF.

No se requiere SweetAlert2, CDN ni conexión a internet para mostrar alertas.

## API disponible

```javascript
SistemaUI.exito('Guardado', 'Los cambios se registraron.');
SistemaUI.error('No fue posible guardar', 'Revisa la información.');
SistemaUI.advertencia('Atención', 'Hay campos pendientes.');
SistemaUI.info('Información', 'La operación sigue en proceso.');
SistemaUI.toast('Lista actualizada.', 'success');

const confirmado = await SistemaUI.confirmar({
    titulo: '¿Desactivar registro?',
    texto: 'El registro dejará de estar disponible.',
    textoConfirmar: 'Sí, desactivar',
    peligro: true
});
```

También se conserva una capa compatible con los usos existentes de `Swal.fire()`, incluyendo:

- Confirmaciones.
- Alertas temporizadas.
- Textarea dentro de una alerta.
- `inputValidator`.
- `preConfirm`.
- `didOpen`.
- `Swal.showValidationMessage()`.

## Regla para nuevos módulos

1. Incluir `inc/alertas.php` dentro de `<head>`.
2. Agregar `data-sm-theme="master"` al `<body>`.
3. Usar `SistemaUI` en lugar de crear nuevos toasts o modales de confirmación.
4. Crear CSS del módulo solamente para su distribución específica; los colores y componentes generales deben salir del tema maestro.

## Compatibilidad

Los modales funcionales de cada módulo se conservaron. Solo las alertas, confirmaciones y notificaciones breves fueron centralizadas. La lógica PHP, las consultas, permisos, rutas y nombres de campos no fueron modificados.
