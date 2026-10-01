## 1. Inicio y navegación

- [x] 1.1 Convertir Dashboard en Inicio con próximos pagos y pendientes personales; mostrar la bandeja resumida solo al tesorero.
- [x] 1.2 Hacer que `/fund` redirija a Inicio; reorganizar sidebar, marca, navegación activa y enlaces por permiso también en móvil.
- [x] 1.3 Separar el menú general de Tesorería y Administración; situar Cuotas y Bancos bajo Tesorería solo para su responsable y quitar accesos duplicados del resumen.

## 2. Secciones del participante

- [x] 2.1 Crear Aportes con calendario personal desde el primer período configurado y vínculos a transacciones aprobadas.
- [x] 2.5 Mostrar doce meses por año con cambio de año, totales y estados diferenciados sin paginación de meses.
- [x] 2.2 Crear Transacciones como historial paginado con filtros de estado y destino, accesible por propiedad o rol.
- [x] 2.3 Guiar el registro en pasos, restringir elecciones por monto y presentar resumen exacto con estados de error.
- [x] 2.4 Enfocar Préstamos en consulta personal y acceso a tabla de amortización, sin perder acciones autorizadas del tesorero.
- [x] 2.6 Convertir el historial de Préstamos en tabla con condiciones, saldos, estado y detalle, sin formulario de creación.

## 3. Tesorería y administración

- [x] 3.1 Crear bandeja de conciliación con saldos contables y reservas separados de pendientes.
- [x] 3.2 Separar pantallas de cuotas y bancos, configurar períodos por rango y editar cuotas futuras; designar tesorero solo por consola.
- [x] 3.3 Crear corrección guiada con datos legibles y precargados, manteniendo motivo, evidencia y reversión atómica.
- [x] 3.4 Presentar historia de decisiones con acciones, autor, motivo y fechas locales; eliminar el título duplicado de Transacciones y usar áreas de texto autoajustables para motivos.
- [x] 3.5 Reutilizar tarjetas de estadísticas según `ExampleCards.vue` en Inicio y Tesorería, mostrando solo etiquetas y datos reales.
- [x] 3.6 Usar paginación compartida solo cuando haya más de una página, incluidos Préstamos y Administración.
- [x] 3.7 Crear desde Tesorería la reserva de préstamos en una pantalla protegida y contextualizar el disponible del fondo.
- [x] 3.8 Trasladar la corrección del desembolso a una pantalla guiada y advertir antes de editar si existen pagos dependientes.
- [x] 3.9 Ocultar próximas cuotas vacías, mostrar solo meses configurados en Aportes, mejorar la tabla y búsqueda de Transacciones y situar Préstamos en Tesorería.

## 4. Verificación

- [x] 4.1 Probar datos y permisos por rol, redirecciones, filtros, períodos, estados financieros del inicio y autor real de aprobaciones y rechazos.
- [x] 4.2 Verificar formato de los archivos del producto, tipos, build, PHPStan, 62 pruebas sobre MySQL y validación estricta OpenSpec.
- [ ] 4.3 Comprobar manualmente navegación y flujo de comprobantes en escritorio y móvil antes de archivar.
