## ADDED Requirements

### Requirement: Bandeja de conciliación

El tesorero SHALL disponer de una pantalla propia que muestre primero las transacciones PENDIENTES, con fecha, participante, monto, banco y acceso al comprobante. Debajo SHALL poder consultar reservas y saldos contables del fondo; los pendientes SHALL NOT contarse como efectivo ni ganancias.

#### Scenario: Comprobante pendiente

- **WHEN** existe una transferencia registrada sin aprobación
- **THEN** aparece en la bandeja del tesorero y ningún saldo contable se incrementa por ella.

### Requirement: Ajustes de tesorería separados

El tesorero SHALL disponer de pantallas propias para períodos de aporte y catálogo de bancos. SHALL poder configurar de una vez entre uno y 120 meses consecutivos y modificar una cuota futura aún no utilizada; si un mes del rango no admite edición, SHALL revertirse todo el rango. La designación del tesorero SHALL estar disponible exclusivamente mediante el comando `fund:assign-treasurer`, sin acceso web.

Las entradas Cuotas y Bancos SHALL estar en el grupo Tesorería del menú. La pantalla de resumen SHALL centrarse en conciliación y saldos, sin enlaces secundarios redundantes a esos ajustes. La reserva de préstamos SHALL estar disponible desde Resumen y Préstamos. La pantalla Bancos SHALL omitir un enlace redundante a Cuotas.

#### Scenario: Administrador sin tesorería

- **WHEN** un administrador sin permiso de tesorero intenta acceder a Tesorería
- **THEN** no puede consultar saldos ni conciliar transacciones de otros usuarios; la designación se realiza por consola.

### Requirement: Corrección comprensible

El tesorero SHALL editar la corrección de una operación aprobada desde una pantalla guiada con datos originales precargados. SHALL seleccionar períodos y cuotas por etiquetas legibles, indicar motivo y evidencia y ver el efecto previsto antes de confirmar. SHALL conservar los datos ingresados si la validación falla; la corrección SHALL seguir usando la reversión y el reemplazo atómicos existentes.

#### Scenario: Reemplazo inválido

- **WHEN** la asignación corregida no iguala el monto del comprobante
- **THEN** no se confirma la corrección, se explica la diferencia y el asiento original permanece vigente.

### Requirement: Historial de decisiones legible

El detalle de una transacción SHALL mostrar sus eventos en orden cronológico con acciones en lenguaje comprensible, fecha y hora del fondo en America/Guayaquil y el nombre del usuario que registró, aprobó, rechazó o corrigió cada paso. SHALL presentar el motivo completo, incluidos saltos de línea. La aprobación SHALL identificar expresamente a quien la autorizó. Los registros operativos PENDIENTES y RECHAZADOS SHALL seguir fuera del ledger.

#### Scenario: Aprobación por otra persona

- **WHEN** un participante registra una transferencia y el tesorero la aprueba
- **THEN** el detalle identifica al participante como autor del registro y al tesorero por nombre como quien autorizó la aprobación, con las fechas legibles de ambas acciones.

#### Scenario: Motivo de rechazo extenso

- **WHEN** el tesorero rechaza con un motivo de varios renglones
- **THEN** el usuario ve el autor, la hora y el motivo completo sin perder los saltos de línea.

### Requirement: Estadísticas con contexto real

Las cifras de Inicio y Tesorería SHALL presentarse en tarjetas consistentes con la composición Card del diseño existente: etiqueta, cifra destacada, insignia y explicación. Las insignias SHALL describir el estado real de cada importe, sin porcentajes de tendencia inventados. Solo se mostrarán al participante los datos financieros autorizados para su rol.

#### Scenario: Rendimiento sin tendencia calculada

- **WHEN** el tesorero consulta los intereses efectivamente cobrados
- **THEN** ve la cifra con una etiqueta de ganancia y una explicación, sin un porcentaje de crecimiento que el sistema no calcula.
