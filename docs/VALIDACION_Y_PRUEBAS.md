# Validación y pruebas recomendadas

## Validaciones realizadas sobre esta entrega

- Sintaxis PHP de todos los archivos.
- Sintaxis JavaScript de los bloques embebidos.
- Sintaxis del archivo `js/sistema-ui.js`.
- Análisis de las 29 hojas CSS.
- Comprobación de que todas las vistas cargan `inc/alertas.php` una sola vez.
- Comprobación de que todas las vistas usan `data-sm-theme="master"`.
- Búsqueda de referencias externas a SweetAlert2 y CDN.
- Búsqueda de `window.confirm()` y alertas nativas heredadas.

## Prueba funcional en el servidor real

Después de sustituir la carpeta del proyecto, probar por cada rol:

### Administrador

- Inicio de sesión y cierre de sesión.
- Alta, edición, activación y desactivación en Personal y Catálogos.
- Aprobar, rechazar, programar y cancelar solicitudes.
- Reprogramar actividades y editar calendario laboral.
- Abrir expedientes, movimientos, tiempos e incumplimientos.

### Solicitante

- Crear cada tipo de solicitud.
- Limpiar formularios mediante confirmación.
- Consultar bandeja e historial.

### Técnico

- Aceptar una urgencia.
- Registrar diagnóstico inicial.
- Iniciar, pausar, reanudar y finalizar mantenimiento.
- Consultar asignaciones e historial.

## Caché del navegador

Al instalar esta versión, usar `Ctrl + F5` en cada equipo la primera vez. Los archivos maestros llevan versión basada en `filemtime`, por lo que después se actualizarán automáticamente cuando cambien.
