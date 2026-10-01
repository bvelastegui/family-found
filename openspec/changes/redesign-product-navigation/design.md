# Diseño de navegación del producto

## Context

La aplicación usa Laravel 13, Inertia 3, Vue 3, Tailwind 4 y componentes locales de shadcn-vue. `Dashboard.vue` contiene placeholders. `fund/Index.vue` combina cuatro dominios. Existen vistas de préstamos y detalles de transferencias, pero faltan índices separados para aportes, transacciones y conciliación.

## Goals / Non-Goals

### Goals

- Dar una tarea clara al entrar y a cada página una función identificable.
- Ofrecer rutas directas a las secciones y menús accesibles por permiso sin exponer datos ajenos.
- Reducir la complejidad al cargar comprobantes y al corregir registros.
- Reutilizar diseños, componentes, acciones y rutas nombradas ya disponibles.

### Non-Goals

- Modificar importes, intereses, estados financieros ni reglas de aprobación.
- Rediseñar la portada pública del starter.
- Introducir dependencias de componentes o nuevas tablas.

## Decisions

### Navegación y destino inicial

`/dashboard` presenta acciones personales y contexto breve; `/fund` redirige allí. El sidebar separa navegación general (Inicio, Aportes y Transacciones) del grupo Tesorería (Resumen, Préstamos, Cuotas, Bancos y Participantes). La designación del tesorero se hace por consola. Las pantallas de tesorería no repiten enlaces a Cuotas o Bancos dentro del contenido. El menú activo considera detalles e hijos, no solo coincidencia exacta; el logo vuelve a Inicio. Se eliminan enlaces de ejemplo del footer. En móvil, el sidebar colapsable existente mantiene las mismas rutas y etiquetas.

La marca visible usa Fondo Familiar. El diseño deriva de los tokens, tipografía y componentes ya instalados, sin imágenes de stock ni estilos ajenos al proyecto.

Las tarjetas de estadísticas reutilizan CardHeader, CardDescription, CardTitle, CardAction, Badge y CardFooter del proyecto siguiendo la referencia `ExampleCards.vue`. Muestran cifras provenientes de las consultas del fondo y etiquetas contextuales. No inventan porcentajes ni tendencias históricas que todavía no se calculan.

### Páginas y datos

| Página          | Contenido principal                                                                                                          | Origen de datos                                                                                  |
| --------------- | ---------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| Inicio          | Siguiente aporte o bloqueo en revisión, transferencias pendientes propias, cuotas de préstamo por atender, botón de registro | Períodos definidos, aplicaciones aprobadas, registros pendientes, cuotas, zona America/Guayaquil |
| Aportes         | Meses configurados del año elegido, pagados, próximos, total anual e histórico y enlace a comprobante                        | `contribution_periods`, asignaciones vigentes aprobadas, registro pendiente personal             |
| Transacciones   | Historial unificado paginado, filtro por estado y tipos de destino, acceso al detalle y evidencia                            | `fund_transactions` y `transaction_allocations`, solo las propias salvo tesorero                 |
| Préstamos       | Tabla con capital, pendiente, tasa, plazo y estado, más detalle con amortización; sin formulario de alta                     | Préstamos, saldo contable y cuotas actuales                                                      |
| Tesorería       | Bandeja PENDIENTE paginada, detalle de verificación, reservas y saldos del fondo                                             | Registros sin asientos pendientes, `FundBalances`, préstamos reservados                          |
| Cuotas y Bancos | Dos pantallas propias, cada una con su formulario, lista y estados vacíos                                                    | Catálogos actuales                                                                               |
| Participantes   | Invitación privada y alta directa por tesorero                                                                               | `fund_invitations` y `users`                                                                     |

Las consultas paginan historiales y presentan estados vacíos. El saldo mostrado proviene solo de asientos aprobados; la bandeja jamás suma transferencias pendientes al efectivo. El participante puede ver sus datos, el tesorero los de todos para conciliar y el administrador solo sus finanzas propias salvo que también sea tesorero.

Aportes consulta un año a la vez, genera doce meses visibles y admite elegir el año entre el primer período definido y el año actual. Los meses sin configuración se presentan sin importe y antes del inicio se distinguen de los meses sin cuota. Un mes futuro con cuota se presenta como próximo, no vencido. El paginador compartido de los listados restantes usa `last_page` y solo aparece cuando es mayor que uno. Se sustituye el paginador manual de Préstamos y Administración, que mostraba "1" incluso con listas vacías.

### Formulario de registro y corrección

El registro tiene pasos visibles: datos bancarios y evidencia, selección de meses y cuotas consecutivas, resumen antes de enviar. El monto limita lo seleccionable y el último paso explica sobrantes o faltantes. No se crea una transacción ni asiento si falta asignación exacta. El servidor conserva la autoridad sobre cuota, capital e interés. Tras registrar, se muestra PENDIENTE con enlace al comprobante.

El detalle de una transacción aprobada ofrece una acción de corrección solo al tesorero. Abre una pantalla con banco, referencia, fecha, monto y destinos precargados. Meses se muestran por año y mes; cuotas por préstamo y número. Debe ingresar motivo y nueva evidencia. Una confirmación explica que la reversión y el reemplazo se guardan juntos. Los errores del servidor conservan los valores del formulario y no modifican el asiento anterior.

El listado de préstamos no incluye el formulario de reserva. Tesorería ofrece una acción para reservar capital que abre una pantalla exclusiva del tesorero y muestra el disponible real. El detalle de un préstamo desembolsado ofrece al tesorero un enlace a una corrección independiente; una pantalla con pagos registrados explica de antemano que no puede reemplazar su tabla. Sin pagos, la corrección guía por motivo, datos bancarios y condiciones, y una confirmación describe la reversión y el reemplazo sin transferencia bancaria adicional. El POST financiero y sus validaciones permanecen iguales.

### Superficie

Conservar endpoints de escritura. Agregar rutas de lectura `fund.contributions.index`, `fund.transactions.index`, `fund.treasury.index` y pantallas independientes para `fund.contribution-periods.index` y `fund.banks.index`. Agregar `fund.transactions.edit`, `fund.treasury.loans.create` y `fund.loans.correction` para formularios protegidos. Cambiar `dashboard` de ruta Inertia estática a controlador. La redirección de `/fund` preserva las rutas de detalle existentes. Usar Wayfinder en todos los enlaces y formularios Vue.

### Estados y accesibilidad

Todos los apartados tienen encabezado y título únicos, foco visible, navegación por teclado y descripciones de errores conectadas a los campos. Los pasos del registro muestran avance y conservan datos al retroceder. Los estados pendiente, rechazado y aprobado tienen texto además de color. La evidencia conserva descarga autorizada fuera de Inertia. Evitar depender de índices numéricos visibles al usuario.

### Historia comprensible y fechas

La historia del comprobante se obtiene de `operation_events`, uniendo `actor_id` con `users.id` para mostrar `users.name`, además del código de evento, su instante y el motivo contenido en `data`. Una aprobación identifica expresamente al usuario que la autorizó tanto en el detalle como en el historial paginado, consultando solo los eventos de las filas visibles. Los motivos se presentan completos conservando saltos de línea; no se expone JSON como etiqueta de decisión. Los eventos continúan fuera del ledger cuando el comprobante permanece pendiente o fue rechazado.

La fecha bancaria es un valor `DATE`, presentado como día, mes y año en español sin convertirlo desde UTC. Los instantes de registro y decisión son UTC y se formatean en America/Guayaquil con fecha y hora. Un formato compartido se usa en el detalle, historial, bandeja y calendarios. En Transacciones se mantiene un único encabezado principal y se omite un segundo título para el mismo historial.

Los motivos de rechazo y de corrección usan una misma área de texto con altura automática basada en la función `useTextareaAutosize` de VueUse, ya instalada. El motivo de cancelación o corrección de un préstamo reutiliza el mismo control. Se conservan las etiquetas, los errores y el límite de 5000 caracteres validado por el servidor.

## Risks / Trade-offs

- Distribuir la interfaz exige más rutas y consultas específicas. El beneficio es una navegación entendible y páginas con un propósito claro.
- El inicio enseña un resumen, no duplica historiales completos; se accede a ellos por las secciones.
- La selección guiada permite volver entre pasos, pero debe volver a validar la asignación al enviar y al aprobar.

## Open Questions

No hay nuevas reglas financieras por definir para esta interfaz. La verificación manual en móvil y escritorio queda como tarea del cambio.
