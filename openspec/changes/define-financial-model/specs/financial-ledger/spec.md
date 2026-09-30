## ADDED Requirements

### Requirement: FL-01 Moneda y apertura

El sistema SHALL registrar todas las operaciones financieras exclusivamente en USD y comenzar con todos los saldos en cero.

#### Scenario: Inicio del fondo

- **WHEN** se habilita el fondo y todavía no existen operaciones aprobadas
- **THEN** los aportes, préstamos, efectivo e intereses acumulados son cero.

#### Scenario: Moneda no admitida

- **WHEN** se intenta registrar una operación en una moneda distinta de USD
- **THEN** el sistema la rechaza sin generar movimientos.

### Requirement: FL-02 Trazabilidad operativa y contable

El sistema SHALL conservar un historial operativo de creación, revisión, aprobación, rechazo, reserva, cancelación y corrección, separado del ledger. SHALL identificar el actor, instante, operación de origen, usuario afectado, datos registrados y motivos o evidencias que correspondan. Los eventos históricos y los asientos SHALL ser inmutables. Las operaciones pendientes o rechazadas y las reservas SHALL ser trazables sin crear asientos en el ledger.

#### Scenario: Transacción pendiente trazable

- **WHEN** un usuario registra una transacción con evidencia
- **THEN** existe un registro PENDIENTE y un evento de creación con sus datos y actor, pero ningún asiento ni línea contable asociados.

#### Scenario: Consulta de una operación corregida

- **WHEN** se consulta una operación que fue revertida
- **THEN** permanecen visibles la operación original y los movimientos compensatorios vinculados.

### Requirement: FL-03 Aprobación previa al ledger

El sistema SHALL crear las transacciones registradas por usuarios en estado PENDIENTE, con evidencia, banco, número de comprobante o autorización, monto y fecha de transacción. El registro SHALL permanecer fuera del ledger hasta que el tesorero lo apruebe. La aprobación SHALL validar la asignación entre aportes y pagos de préstamos y registrar, de forma atómica y una sola vez, la decisión, los asientos y sus aplicaciones. Si falla cualquier paso SHALL conservarse PENDIENTE sin efectos contables parciales. El rechazo SHALL exigir un motivo y no producir asientos. Una transacción RECHAZADA SHALL NOT aprobarse directamente.

#### Scenario: Transferencia combinada

- **WHEN** el tesorero aprueba un depósito de USD 150 asignado a USD 100 de aportes y USD 50 de pago de préstamo
- **THEN** se generan los asientos vinculados al registro y a la aprobación; solo USD 100 incrementan los aportes del usuario y los USD 50 se aplican al préstamo con su desglose de capital e interés.

#### Scenario: Depósito pendiente

- **WHEN** un usuario registra un depósito que todavía no se ha aprobado
- **THEN** el depósito aparece PENDIENTE, no tiene asientos en el ledger, no paga meses ni reduce deuda y no incrementa el capital disponible para prestar.

#### Scenario: Rechazo

- **WHEN** el tesorero rechaza un depósito indicando un motivo
- **THEN** se conserva como RECHAZADA con ese motivo, actor y fecha en el historial operativo, sin asientos ni compensaciones en el ledger.

#### Scenario: Rechazo sin motivo

- **WHEN** el tesorero intenta rechazar un depósito sin motivo
- **THEN** la operación no se completa y el depósito permanece PENDIENTE.

#### Scenario: Aprobación repetida

- **WHEN** se vuelve a procesar la aprobación de un depósito ya aprobado
- **THEN** no se duplican sus movimientos, pagos ni intereses acreditados.

#### Scenario: Fallo al contabilizar la aprobación

- **WHEN** falla la creación de un asiento o de una aplicación durante la aprobación
- **THEN** no se confirma la aprobación, el registro permanece PENDIENTE y no quedan asientos ni aplicaciones parciales.

#### Scenario: Aprobación y rechazo concurrentes

- **WHEN** una aprobación y un rechazo compiten por el mismo registro PENDIENTE
- **THEN** solo una decisión se confirma y únicamente si gana la aprobación existen asientos vinculados.

### Requirement: FL-04 Patrimonio común no retirable

El sistema SHALL registrar los aportes mensuales y todos los intereses efectivamente cobrados como patrimonio común del fondo. Los aportes SHALL ser no retirables y su atribución al usuario SHALL ser informativa. Los intereses SHALL permanecer en el fondo para financiar nuevos préstamos, sin repartirse ni incrementar los aportes individuales. Los préstamos recibidos SHALL ser deuda separada del historial de aportes.

#### Scenario: Intereses comunes

- **WHEN** un pago aprobado produce USD 60 de intereses
- **THEN** el fondo incrementa sus intereses acumulados y su efectivo en USD 60, sin repartir ese importe entre usuarios.

#### Scenario: Préstamo a un aportante

- **WHEN** se desembolsa un préstamo a un usuario
- **THEN** se registra su deuda por separado sin descontar el monto de sus aportes.

### Requirement: FL-05 Consulta de cartera

El sistema SHALL mostrar a cada usuario sus transacciones y decisiones, aportes aprobados por mes, total aportado, préstamos recibidos y deuda pendiente. SHALL permitir al tesorero consultar los aportes acumulados, intereses cobrados, principal prestado pendiente, efectivo, reservas activas y dinero disponible del fondo, con trazabilidad a los registros y asientos que explican cada saldo.

#### Scenario: Consulta sin confundir registros y saldo

- **WHEN** un usuario tiene USD 100 de aportes aprobados, otro depósito de USD 25 PENDIENTE y USD 200 de principal pendiente de un préstamo
- **THEN** ve USD 100 aportados, el depósito de USD 25 como pendiente sin efecto contable y la deuda de USD 200 por separado.

### Requirement: FL-06 Reversión completa

El sistema SHALL permitir al tesorero corregir errores de registro de una operación aprobada mediante reversión completa y reemplazo explícitamente aprobado en una sola transacción atómica, indicando motivo y conservando ambos vínculos. SHALL impedir la corrección mientras existan operaciones posteriores dependientes sin resolver o cuando su resultado produzca saldos inválidos. SHALL validar el reemplazo con las reglas del flujo original. SHALL NOT publicar disponibilidad transitoria entre la reversión y el reemplazo ni admitir anulación contable aislada en este alcance. El procedimiento SHALL corregir el registro del hecho bancario, sin representar una devolución real ni borrar la operación original.

#### Scenario: Operación sin dependencias

- **WHEN** el tesorero confirma la corrección de un aporte aprobado sin dependencias y aporta un reemplazo válido
- **THEN** se compensan todos los efectos originales y se contabiliza el reemplazo juntos, conservando el original, el motivo, el actor y ambos asientos vinculados.

#### Scenario: Capital ya utilizado

- **WHEN** se intenta corregir una operación con pagos posteriores dependientes o cuyo reemplazo dejaría disponible insuficiente para las reservas vigentes
- **THEN** se bloquea toda la corrección y se identifican las dependencias o insuficiencia que deben resolverse primero.

#### Scenario: Reversión repetida

- **WHEN** se reintenta una reversión ya completada
- **THEN** no se generan nuevas compensaciones.

#### Scenario: Reemplazo inválido

- **WHEN** el reemplazo de una corrección no cumple la asignación exacta o falla su contabilización
- **THEN** la operación original sigue vigente, no se confirma su reversión y no se expone saldo transitorio.

### Requirement: FL-07 Operaciones del tesorero

El sistema SHALL permitir que el tesorero sea aportante y prestatario y que concilie sus propias transacciones. SHALL identificar por separado al actor y al beneficiario aun cuando sean la misma persona. Un usuario sin permiso de tesorería SHALL NOT aprobar transacciones ni ejecutar desembolsos, cancelaciones de reservas o reversiones. El permiso del tesorero SHALL NOT omitir el estado PENDIENTE al registrar su propio depósito.

#### Scenario: Aprobación propia

- **WHEN** el tesorero aprueba su depósito
- **THEN** se aplican las mismas validaciones que para otro usuario y queda identificado como actor y beneficiario.

#### Scenario: Aprobación no autorizada

- **WHEN** un usuario sin permiso de tesorería intenta aprobar un depósito
- **THEN** se deniega la operación sin efectos financieros.

### Requirement: FL-08 Asignación exacta y conservación de importes

El sistema SHALL exigir montos positivos y asignaciones no negativas a aportes y pagos de préstamos. Tanto al registrar como al aprobar, la suma de las cuotas mensuales completas seleccionadas y los pagos válidos de préstamos SHALL coincidir exactamente con el monto de la transacción, en centavos de USD. Si existe un sobrante o faltante, SHALL informar la diferencia e impedir el registro o la aprobación, según corresponda. Cada pago SHALL desglosarse en capital e interés y el componente de capital SHALL NOT exceder el principal pendiente. SHALL impedir saldos negativos. SHALL NOT descartar diferencias, crear aportes parciales ni cambiar automáticamente el monto o el destino del dinero para cuadrar. El monto declarado SHALL corresponder a la evidencia bancaria.

#### Scenario: Asignación negativa

- **WHEN** se intenta asignar un depósito de USD 100 a USD 110 de pago de préstamo y USD menos 10 de aportes
- **THEN** se rechaza la asignación aunque su suma sea USD 100.

#### Scenario: Registro con sobrante

- **WHEN** se intenta registrar USD 110 con cuatro cuotas de USD 25 y ningún pago de préstamo
- **THEN** se informa que faltan USD 10 por asignar y no se crea la transacción PENDIENTE ni un asiento.

#### Scenario: Registro con monto insuficiente

- **WHEN** se intenta registrar USD 90 con cuatro cuotas de USD 25 y ningún pago de préstamo
- **THEN** se informa que las asignaciones superan el monto en USD 10 y no se crea la transacción PENDIENTE ni un asiento.

#### Scenario: Registro con asignación exacta

- **WHEN** se registran USD 150 con cuatro cuotas de USD 25 y USD 50 de pago válido de préstamo
- **THEN** la asignación exacta permite crear la transacción PENDIENTE, sin generar asientos hasta su aprobación.

#### Scenario: Diferencia detectada al aprobar

- **WHEN** al aprobar una transacción existente se detecta que la suma de sus asignaciones válidas ya no coincide con el monto registrado
- **THEN** se informa la diferencia y la transacción permanece PENDIENTE sin asiento ni aplicación parcial, hasta resolver la asignación sin alterar el monto real transferido.

### Requirement: FL-09 Partida doble y centavos

El sistema SHALL mantener asientos de partida doble en centavos enteros de USD. Cada asiento SHALL tener igual suma de débitos y créditos y vincularse a su operación aprobada. SHALL rechazar cualquier asiento desbalanceado sin confirmar la operación. Los aportes SHALL aumentar efectivo y patrimonio común; los desembolsos SHALL convertir efectivo en principal por cobrar; los pagos SHALL aumentar efectivo y reducir principal por cobrar o aumentar intereses retenidos, según su desglose.

#### Scenario: Asiento de aporte

- **WHEN** se aprueba un aporte de USD 25
- **THEN** se registra un débito de 2500 centavos a efectivo y un crédito de 2500 centavos a patrimonio común por aportes, vinculados al usuario y mes.

#### Scenario: Asiento desbalanceado

- **WHEN** una operación intenta generar débitos y créditos con totales distintos
- **THEN** la operación no se confirma ni deja asientos parciales.

### Requirement: FL-10 Comprobantes y corrección de registros

El sistema SHALL identificar comprobantes de ingreso por el banco seleccionado del catálogo y el número normalizado de comprobante o autorización, sin incluir fecha ni usuario. SHALL impedir crear otra transacción con esa combinación mientras exista una PENDIENTE o APROBADA vigente. Una transacción registrada SHALL conservar sus datos y evidencia sin edición. Para corregirla mientras está pendiente, el tesorero SHALL rechazarla con motivo y el usuario SHALL crear un nuevo registro vinculado al anterior. Tras un rechazo SHALL poder reutilizarse la combinación, conservando ambos registros. El reemplazo de una operación aprobada SHALL poder conservar su combinación únicamente dentro de la corrección atómica autorizada, sustituyendo su vigencia sin borrar el original. La verificación de duplicados SHALL ser atómica y repetirse al aprobar.

#### Scenario: Misma referencia en otra fecha

- **WHEN** un usuario intenta registrar el mismo banco y número de un registro pendiente o aprobado, aunque cambie la fecha o el usuario
- **THEN** se bloquea el duplicado sin crear otro registro.

#### Scenario: Corrección posterior a rechazo

- **WHEN** el tesorero rechaza un registro por monto incorrecto y el usuario presenta el monto correcto con el mismo banco y número
- **THEN** se admite el nuevo registro si cumple las validaciones, queda PENDIENTE y vinculado al rechazado, y se conservan la evidencia y datos originales.

#### Scenario: Intento de editar un pendiente

- **WHEN** un usuario intenta sustituir datos o evidencia de una transacción ya registrada
- **THEN** se impide la modificación y se informa que requiere rechazo y nuevo registro corregido.

### Requirement: FL-11 Evidencias privadas

El sistema SHALL exigir un único archivo de evidencia JPG, PNG o PDF de hasta 10 MB por transacción de ingreso y por confirmación de desembolso. SHALL validar el contenido del archivo y su tamaño, además de su extensión. SHALL conservarlo vinculado al registro y permitir acceso únicamente al usuario asociado y al tesorero mediante autorización en servidor, sin enlace público abierto.

#### Scenario: Evidencia inválida

- **WHEN** se intenta registrar una transacción sin evidencia, con un archivo de tipo distinto a los admitidos o superior a 10 MB
- **THEN** se rechaza el registro sin asiento ni transacción pendiente parcialmente creada.

#### Scenario: Evidencia de otro participante

- **WHEN** un usuario sin permiso de tesorería intenta descargar la evidencia de otro participante
- **THEN** el acceso se deniega sin exponer el archivo.

#### Scenario: Evidencia de un rechazo

- **WHEN** el propietario o el tesorero consulta una transacción rechazada
- **THEN** puede acceder a la evidencia original y al motivo sin que el rechazo haya eliminado el archivo.
