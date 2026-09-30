## ADDED Requirements

### Requirement: LF-01 Financiamiento exclusivo del fondo

El sistema SHALL financiar todos los préstamos con el dinero disponible del fondo común. SHALL calcularlo como aportes aprobados netos más intereses cobrados netos, menos capital prestado pendiente y reservas activas, considerando las compensaciones registradas. SHALL impedir que transacciones PENDIENTES o RECHAZADAS incrementen esa disponibilidad. El monto del préstamo SHALL ser positivo y no superar el disponible.

#### Scenario: Fondo parcialmente colocado

- **WHEN** el fondo tiene USD 1000 de aportes aprobados, USD 100 de intereses cobrados, USD 400 de capital prestado pendiente y USD 200 reservados
- **THEN** tiene USD 700 de efectivo contable y USD 500 disponibles para reservar nuevos préstamos.

#### Scenario: Fondos insuficientes

- **WHEN** se intenta reservar USD 600 y el fondo solo tiene USD 500 disponibles, aunque exista un depósito PENDIENTE de USD 100
- **THEN** se rechaza la reserva completa sin usar el depósito pendiente.

### Requirement: LF-02 Reserva previa

El sistema SHALL permitir al tesorero reservar el monto del fondo antes de desembolsar un préstamo e impedir que se comprometa en otra operación. La reserva SHALL conservar actor, monto, fecha y préstamo asociado en el historial operativo. SHALL NOT crear asientos en el ledger, salida de efectivo, intereses ni deuda desembolsada al prestatario.

#### Scenario: Competencia por el fondo

- **WHEN** dos reservas intentan usar los mismos USD 100 disponibles del fondo
- **THEN** como máximo una compromete ese importe y la otra no produce saldos negativos ni sobreasignación.

### Requirement: LF-03 Cancelación de reserva

El sistema SHALL permitir al tesorero cancelar una reserva no desembolsada con motivo obligatorio, sin vencimiento automático. SHALL liberar el importe a la disponibilidad del fondo y conservar el historial, sin generar asientos en el ledger.

#### Scenario: Cancelación de reserva

- **WHEN** el tesorero cancela una reserva de USD 200 con motivo
- **THEN** el disponible del fondo aumenta USD 200, el efectivo contable no cambia y quedan registrados la reserva y su cancelación.

### Requirement: LF-04 Desembolso confirmado

El sistema SHALL permitir al tesorero confirmar y aprobar el desembolso de una reserva activa registrando evidencia y datos de la transferencia, incluido el monto. Esa confirmación SHALL generar una sola vez, de forma atómica, la salida de dinero, el principal por cobrar y el consumo de la reserva. Preparar el préstamo o adjuntar evidencia sin confirmar SHALL NOT contabilizarlo. El monto desembolsado SHALL coincidir con el principal reservado. El préstamo SHALL conservar prestatario, monto, plazo, tasa y tabla de amortización calculada según LF-08. La fecha bancaria de desembolso SHALL determinar los vencimientos.

#### Scenario: Desembolso aprobado

- **WHEN** el tesorero confirma la transferencia de un préstamo reservado de USD 1000
- **THEN** se registran USD 1000 de salida y de principal por cobrar, se consume la reserva y no se reducen los aportes históricos del prestatario ni de otros participantes.

#### Scenario: Confirmación de reserva cancelada

- **WHEN** se intenta desembolsar una reserva cancelada
- **THEN** se rechaza sin salida de dinero ni deuda.

### Requirement: LF-05 Aplicación de pagos aprobados

El sistema SHALL aplicar pagos a préstamos únicamente al aprobar la transacción que los contiene. SHALL separar capital e interés, con componentes no negativos cuya suma sea el pago asignado. El capital SHALL reducir el principal pendiente y recuperar disponibilidad del fondo sin contarse como ganancia. Los componentes SHALL provenir de las reglas de préstamos, no de una estimación del ledger, y validarse contra el saldo vigente al aprobar. Un pago incompatible con el saldo SHALL permanecer PENDIENTE sin contabilización parcial hasta resolver su asignación.

#### Scenario: Capital e interés de un pago

- **WHEN** se aprueba la primera cuota de USD 340,02 de un préstamo de USD 1000 a tres meses con tasa mensual de 1 %, con USD 330,02 de capital y USD 10 de intereses
- **THEN** el efectivo aumenta USD 340,02, el principal pendiente queda en USD 669,98 y las ganancias aumentan solo USD 10.

#### Scenario: Intereses todavía no cobrados

- **WHEN** la tabla de amortización muestra intereses futuros o existe un pago pendiente de aprobación
- **THEN** esos importes no incrementan el efectivo, las ganancias ni el disponible del fondo.

#### Scenario: Saldo modificado por otro pago

- **WHEN** un pago pendiente asigna USD 80 a capital pero una aprobación previa deja solo USD 20 de principal pendiente
- **THEN** la aprobación del pago de USD 80 se bloquea sin asiento ni cambio automático de destino del excedente.

### Requirement: LF-06 Destino de rendimientos

El sistema SHALL incorporar todos los intereses efectivamente cobrados mediante pagos aprobados al fondo común, disponibles para financiar nuevos préstamos. SHALL NOT repartirlos entre usuarios ni acreditarlos como aportes mensuales o saldos personales.

#### Scenario: Interés íntegro para el fondo

- **WHEN** se aprueba un pago con USD 100 de intereses
- **THEN** el fondo retiene los USD 100 completos y ningún usuario recibe un reparto personal.

### Requirement: LF-07 Reporte de intereses cobrados

El sistema SHALL reportar los intereses efectivamente cobrados con trazabilidad al registro de transacción, aprobación, asiento y préstamo de origen. SHALL considerar las reversiones, distinguir capital recuperado de ganancias y permitir al prestatario y al tesorero consultar la tabla de amortización y pagos del préstamo.

#### Scenario: Conciliación del reporte

- **WHEN** un pago aprobado contiene USD 200 de capital y USD 100 de intereses
- **THEN** el reporte muestra USD 100 de ganancias del fondo, distingue los USD 200 recuperados y permite identificar la evidencia, aprobación y asiento asociados.

### Requirement: LF-08 Cuota mensual fija y calendario

El sistema SHALL calcular la tabla por sistema francés con tasa mensual fija no negativa y plazo positivo en meses, ambos indicados por el tesorero. Para tasa cero SHALL dividir el principal entre el número de meses. SHALL conservar las condiciones y la tabla al aprobar el desembolso, sin recalcularlas ante cada pago. La primera cuota SHALL vencer un mes después de la fecha bancaria del desembolso y las siguientes en el mismo día de cada mes contado desde esa fecha; si el día no existe SHALL usar el último del mes correspondiente, sin desplazar el día de los meses siguientes. El calendario SHALL usar America/Guayaquil.

Los importes de la tabla SHALL redondearse al centavo, con mitades hacia arriba. La última cuota SHALL ajustar el principal residual para que la suma de capital sea exactamente el monto desembolsado. SHALL impedir confirmar una tabla con cuotas no positivas, capital negativo o capital total distinto del préstamo.

#### Scenario: Tasa mensual positiva

- **WHEN** se confirma un préstamo de USD 1000 a tres meses con tasa mensual de 1 %
- **THEN** la tabla contiene cuotas de USD 340,02, USD 340,02 y USD 340,03; sus intereses son USD 10,00, USD 6,70 y USD 3,37 y su capital total es USD 1000.

#### Scenario: Préstamo sin interés

- **WHEN** se confirma un préstamo de USD 100 a tres meses y tasa cero
- **THEN** las cuotas son USD 33,33, USD 33,33 y USD 33,34, con interés cero y capital total de USD 100.

#### Scenario: Día inexistente en febrero

- **WHEN** se desembolsa un préstamo el 31 de enero de 2027
- **THEN** la primera cuota vence el 28 de febrero y la segunda el 31 de marzo, sin trasladar todas las cuotas al día 28.

### Requirement: LF-09 Cuotas completas consecutivas

El sistema SHALL permitir pagar una o varias cuotas completas desde la más antigua sin pago aprobado, en orden y sin saltos, incluidas cuotas futuras. SHALL NOT admitir pagos parciales de cuotas ni abonos extraordinarios a capital en esta versión. Adelantar cuotas SHALL conservar los intereses y montos de la tabla original, sin reducir tasa, plazo ni recalcular intereses. Las asignaciones SHALL señalar las cuotas concretas y usar su desglose de capital e interés, calculado por el servidor. Al aprobar SHALL volver a comprobar que las cuotas siguen pendientes y forman una secuencia válida.

#### Scenario: Anticipo de cuotas completas

- **WHEN** se registra y aprueba el pago de las dos primeras cuotas pendientes de un préstamo aunque todavía no hayan vencido
- **THEN** se aplican sus montos completos de capital e interés según la tabla original, y las cuotas posteriores conservan sus condiciones y vencimientos.

#### Scenario: Pago parcial

- **WHEN** una cuota pendiente es de USD 50 y se intenta asignar USD 40 a su pago
- **THEN** el sistema impide registrar esa asignación como pago de préstamo.

#### Scenario: Salto de cuota

- **WHEN** la primera cuota sigue sin pago aprobado y se intenta registrar solo la segunda
- **THEN** el sistema impide la selección por no comenzar en la más antigua pendiente.

### Requirement: LF-10 Atrasos sin recargos

El sistema SHALL mostrar como vencida una cuota sin pago aprobado cuando su fecha de vencimiento sea anterior a la fecha actual en America/Guayaquil. SHALL conservar el importe original sin recargos, interés adicional ni capitalización. Un pago pendiente de aprobación SHALL mostrarse en revisión pero no cancelar la obligación todavía.

#### Scenario: Cuota atrasada

- **WHEN** una cuota de USD 50 venció hace diez días y no tiene pago aprobado
- **THEN** sigue adeudándose USD 50, se muestra vencida y debe pagarse antes de las cuotas posteriores.
