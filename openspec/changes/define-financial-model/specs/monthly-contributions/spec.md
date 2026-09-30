## ADDED Requirements

### Requirement: MC-01 Aporte mensual común

El sistema SHALL permitir al tesorero definir un aporte mensual fijo positivo en USD que aplique por igual a todos los participantes, incluido él mismo. Cada participante SHALL tener un único aporte exigible por mes y su pago aprobado SHALL ingresar directamente al fondo, sin derecho de retiro.

El sistema SHALL tomar como primera cuota exigible el mes más antiguo definido explícitamente en `contribution_periods` para todos los participantes, independientemente de su fecha de registro. No SHALL derivar el primer mes de `users.created_at`. Cada mes SHALL tener un monto común para todos. El tesorero SHALL poder cambiar el monto de meses futuros únicamente mientras no estén referenciados en transacciones registradas; los meses ya referenciados SHALL conservar su importe.

Una vez que algún período está referenciado en una transacción, el sistema SHALL impedir añadir un período anterior al primero configurado para no alterar retroactivamente la secuencia de aportes ya registrados.

#### Scenario: Mismo monto para el tesorero

- **WHEN** el aporte mensual aplicable es USD 25
- **THEN** tanto el tesorero como los demás participantes deben aportar USD 25 para cubrir ese mes.

#### Scenario: Usuario registrado después del primer período

- **WHEN** el tesorero definió enero como primer período y un usuario se registra en marzo
- **THEN** el primer mes que puede aportar es enero, con su cuota completa, seguido de febrero y marzo en orden.

#### Scenario: Primer período definido en el calendario local

- **WHEN** el tesorero define marzo como primer período de aporte y una cuenta se crea en abril
- **THEN** el primer mes exigible es marzo, según el período configurado, sin tomar abril del registro de la cuenta.

#### Scenario: No cambiar el inicio después de registrar aportes

- **WHEN** marzo es el primer período configurado y ya aparece en una transacción registrada
- **THEN** el tesorero no puede añadir enero como nuevo primer período, aunque marzo aún esté PENDIENTE.

#### Scenario: Cuota futura todavía no utilizada

- **WHEN** el tesorero cambia la cuota de un mes futuro sin transacciones que lo referencien
- **THEN** el nuevo monto se aplica por igual a todos los participantes para ese mes y se conserva el historial del cambio.

#### Scenario: Cuota ya referenciada

- **WHEN** el tesorero intenta cambiar el monto de un mes incluido en una transacción registrada
- **THEN** el sistema impide el cambio, incluso si esa transacción todavía está PENDIENTE.

### Requirement: MC-02 Selección consecutiva de meses

El sistema SHALL permitir seleccionar varios meses por transacción, comenzando por el mes más antiguo exigible no pagado y continuando consecutivamente. SHALL impedir omitir un mes pendiente o cubrir dos veces el mismo mes del mismo usuario.

Si aún no hay ningún período configurado, el sistema SHALL informar que el tesorero debe definir la primera cuota y SHALL impedir registrar aportes hasta entonces.

#### Scenario: Sin primera cuota configurada

- **WHEN** un usuario desea registrar un aporte y `contribution_periods` no contiene períodos
- **THEN** no se ofrecen meses para seleccionar y no se registra un aporte.

El sistema SHALL permitir adelantos como máximo hasta diciembre del año actual según America/Guayaquil, siempre que exista cuota definida para cada mes seleccionado. Los meses atrasados de años anteriores SHALL permanecer exigibles y tener prioridad sobre meses más recientes. Registrar o aprobar una transacción SHALL NOT inventar una cuota para un mes sin configuración.

#### Scenario: Selección desde el más antiguo

- **WHEN** enero es el mes más antiguo pendiente y el usuario selecciona enero, febrero y marzo
- **THEN** la secuencia es válida si dispone de monto suficiente.

#### Scenario: Salto de un mes

- **WHEN** enero y febrero están pendientes y el usuario intenta pagar febrero sin enero
- **THEN** la selección es inválida.

#### Scenario: Límite anual de adelantos

- **WHEN** en septiembre de 2026 se intenta seleccionar enero de 2027
- **THEN** el sistema impide seleccionar ese mes aunque exista monto suficiente.

#### Scenario: Aportes atrasados del año anterior

- **WHEN** en enero de 2027 el mes más antiguo pendiente es noviembre de 2026
- **THEN** la selección comienza en noviembre de 2026, continúa sin saltos y puede llegar como máximo a diciembre de 2027.

### Requirement: MC-03 Límite por importe disponible

El sistema SHALL limitar los meses seleccionables por el importe restante después de asignar pagos de préstamos. Para meses con la misma cuota, el máximo SHALL ser la parte entera de ese importe dividido por el aporte mensual. El monto asignado a aportes SHALL coincidir con la suma de las cuotas completas de los meses seleccionados.

#### Scenario: Cuatro aportes

- **WHEN** la transacción es de USD 100, no incluye pagos de préstamos y el aporte mensual es USD 25
- **THEN** se pueden seleccionar como máximo cuatro meses consecutivos.

#### Scenario: Transferencia combinada

- **WHEN** la transacción es de USD 150, se asignan USD 50 a un préstamo y el aporte mensual es USD 25
- **THEN** quedan USD 100 para seleccionar como máximo cuatro meses consecutivos.

### Requirement: MC-04 Acreditación al aprobar

El sistema SHALL marcar los meses como pagados únicamente al aprobar la transacción y generar sus asientos. SHALL volver a validar cuota, secuencia, importe y ausencia de pagos duplicados al aprobar. Los registros PENDIENTES o RECHAZADOS SHALL NOT pagar meses. Si otro pago aprobado invalida la asignación, SHALL impedir la aprobación completa sin producir asientos parciales.

#### Scenario: Meses todavía pendientes

- **WHEN** se registra una transacción PENDIENTE destinada a enero y febrero
- **THEN** ambos meses siguen sin pago acreditado y el usuario puede distinguir la transacción en revisión.

#### Scenario: Mes acreditado antes de una aprobación

- **WHEN** se intenta aprobar una asignación cuyo mes ya tiene una aplicación de aporte vigente
- **THEN** el sistema impide la aprobación sin acreditar el mes otra vez ni crear asientos parciales.

### Requirement: MC-05 Una transacción con aportes pendiente

El sistema SHALL permitir como máximo una transacción PENDIENTE que contenga aportes por usuario. Hasta que se apruebe o rechace, SHALL impedir registrar otra transacción con aportes de ese mismo usuario. SHALL permitirle registrar pagos exclusivos de préstamos que cumplan las demás validaciones. La comprobación y creación SHALL ser atómicas para impedir pendientes simultáneos por solicitudes concurrentes.

#### Scenario: Segundo aporte en revisión

- **WHEN** un usuario con una transacción PENDIENTE que cubre enero intenta registrar otra con aportes
- **THEN** se impide el segundo registro y se le indica que debe esperar la revisión de la primera transacción.

#### Scenario: Pago exclusivo de préstamo

- **WHEN** un usuario con un aporte PENDIENTE registra una transacción válida destinada solo a un préstamo
- **THEN** se admite el nuevo registro PENDIENTE sin crear asientos.

#### Scenario: Rechazo libera el bloqueo

- **WHEN** el tesorero rechaza la transacción con aportes pendiente de un usuario
- **THEN** el usuario puede registrar un nuevo aporte desde el mes más antiguo todavía sin pago, conservándose el rechazo anterior.
