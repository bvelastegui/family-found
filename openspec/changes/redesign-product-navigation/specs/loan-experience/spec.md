## ADDED Requirements

### Requirement: Historial de préstamos legible

Préstamos SHALL mostrar un listado paginado en formato de tabla con participante autorizado, principal original, saldo pendiente, tasa mensual, plazo y estado. Un usuario común SHALL ver solo sus préstamos y no SHALL ver un formulario de creación. El tesorero SHALL poder consultar los préstamos para gestionar el fondo. El estado vacío SHALL explicar que no hay préstamos y no mostrar controles de paginación si hay una sola página.

#### Scenario: Aportante sin préstamos

- **WHEN** un participante sin préstamos abre el historial
- **THEN** ve el estado vacío sin formulario de creación y sin enlace aislado de página 1.

#### Scenario: Préstamos registrados

- **WHEN** existen varios préstamos accesibles al usuario
- **THEN** aparecen como filas con montos, condiciones, estado y enlace al detalle de cada uno.

### Requirement: Reserva exclusiva de Tesorería

La creación de una reserva de préstamo SHALL estar disponible desde Tesorería en una pantalla independiente y protegida para el tesorero. SHALL mostrar el dinero disponible del fondo y explicar que reservar no desembolsa ni crea un asiento. SHALL utilizar el endpoint existente que valida el capital disponible y genera el historial de reserva.

#### Scenario: Un usuario intenta abrir la creación

- **WHEN** un usuario sin permiso de tesorería solicita la pantalla de creación directamente
- **THEN** recibe acceso denegado y no obtiene datos de otros participantes ni del disponible del fondo.

### Requirement: Corrección de desembolso comprensible

Un préstamo desembolsado SHALL ofrecer al tesorero una pantalla separada con datos originales y formulario precargado que explique motivo, datos bancarios, condiciones y nueva evidencia. SHALL explicar antes de confirmar que la corrección revierte contablemente el desembolso original y crea un reemplazo en una sola operación, sin transferir dinero nuevamente. Si existen pagos pendientes o aprobados sobre la tabla original, SHALL advertir el bloqueo antes de mostrar el formulario. El backend SHALL mantener la autorización y las restricciones de corrección originales.

#### Scenario: Corrección sin pagos

- **WHEN** el tesorero abre la corrección de un desembolso vigente sin pagos dependientes
- **THEN** ve los valores originales, campos precargados, motivo y evidencia obligatorios y un resumen antes de confirmar.

#### Scenario: Pagos dependientes

- **WHEN** el préstamo tiene pagos registrados pendientes o aprobados
- **THEN** el tesorero recibe una explicación del impedimento y no se le ofrece enviar una corrección que el servidor rechazará.

#### Scenario: Corrección no autorizada

- **WHEN** el prestatario sin permiso de tesorería intenta abrir o enviar una corrección
- **THEN** el servidor deniega la operación y no modifica ni el préstamo ni el ledger.
