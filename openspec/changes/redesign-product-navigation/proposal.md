# Convertir el fondo en la aplicación principal

## Why

El inicio actual muestra contenido de ejemplo y el menú trata al fondo familiar como una función secundaria. La portada del fondo mezcla aportes, transferencias, préstamos y ajustes, de modo que cuesta encontrar la siguiente acción.

## What Changes

- Convertir `/dashboard` en el inicio útil del producto y redirigir `/fund` a ese inicio.
- Navegar por Inicio, Aportes, Transacciones y Préstamos. Mostrar Tesorería y Administración según permisos; quitar los enlaces del starter.
- Crear vistas propias para el calendario personal, el historial unificado de transacciones y la bandeja de conciliación.
- Priorizar pendientes y siguientes acciones en Inicio, con información diferente para participantes y tesorero.
- Guiar el registro de transferencias con monto, comprobante, selección consecutiva y resumen de asignación exacta.
- Presentar Préstamos como una tabla consultable; reservar capital desde una pantalla exclusiva de Tesorería y corregir desembolsos desde un recorrido guiado separado.
- Corregir transferencias mediante un formulario guiado con meses y cuotas legibles en lugar de identificadores numéricos.
- Conservar el diseño visual y los componentes ya instalados, con la marca Fondo Familiar y prioridad de uso móvil para participantes.

## Capabilities

### New Capabilities

- `product-navigation`: inicio, navegación contextual por permisos y jerarquía de pantallas.
- `contribution-experience`: calendario y estado de aportes personales.
- `transaction-experience`: historial, bandeja de conciliación y formularios guiados.

### Modified Capabilities

- `loan-funding`: enlazar préstamos desde su propia sección y presentar sus acciones de tesorería con contexto.

## Impact

Cambian rutas de lectura, props de Inertia, páginas Vue, navegación y pruebas de acceso. Las acciones financieras, sus validaciones, el ledger y la estructura de tablas se reutilizan. La portada pública queda fuera de este cambio. `define-financial-model` continúa abierta hasta completar sus verificaciones propias.
