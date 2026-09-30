# Modelo financiero del fondo familiar

Estado: implementación en curso. Fecha: 2026-09-27.

## Why

La familia necesita registrar sus aportes mensuales, financiar préstamos con el fondo común y conocer los intereses cobrados. Separar los registros pendientes de los movimientos aprobados permitirá explicar cada saldo y conservar la trazabilidad de todas las operaciones.

## What Changes

- Establecer un ledger inmutable (historial financiero que se corrige agregando movimientos) en USD, con saldos iniciales en cero.
- Registrar un aporte mensual fijo definido por el tesorero, igual para todos, incluido él mismo. Los aportes ingresan directamente al fondo y no son retirables.
- Permitir que una transferencia cubra meses consecutivos desde el más antiguo pendiente y también pagos de préstamos, limitados por el monto registrado.
- Exigir asignación exacta al registrar y aprobar: monto de la transferencia igual a aportes mensuales completos más pagos válidos de préstamos, sin sobrantes ni faltantes.
- Mantener toda transacción registrada por un usuario como PENDIENTE fuera del ledger. Aprobarla y generar sus asientos como una sola operación; rechazarla con motivo sin generar asientos.
- Separar el historial operativo de los registros contables. La creación, revisión, aprobación, rechazo, reserva, cancelación y corrección conservan actor, fecha y relaciones de origen.
- Financiar los préstamos exclusivamente con dinero disponible del fondo, reservándolo antes del desembolso.
- Registrar separadamente la recuperación de capital y el interés efectivamente cobrado. Todo interés cobrado permanece en el fondo y puede financiar nuevos préstamos.
- Corregir operaciones con reversión completa y nuevo registro, conservando dependencias e historial.
- Permitir al tesorero operar y conciliar sus propias transacciones, identificando siempre actor y beneficiario.
- Usar America/Guayaquil para el calendario. La primera cuota corresponde al período más antiguo configurado explícitamente en `contribution_periods` para todos los usuarios; los adelantos llegan hasta diciembre del año actual y solo se admite una transacción con aportes pendiente por usuario.
- Gestionar un catálogo de bancos y detectar comprobantes duplicados por banco y número. Las correcciones de pendientes requieren rechazo y nuevo registro vinculado.
- Calcular préstamos mediante cuota mensual fija, tasa mensual y plazo en meses, admitiendo tasa cero. Cobrar cuotas completas en orden, con anticipos de cuotas sin recalcular la tabla y sin recargos por atraso.
- Crear una cuenta administrativa durante la instalación y gestionar desde la aplicación la designación del único tesorero.
- Implementar la persistencia financiera y sus pruebas sobre MySQL con InnoDB.

## Capabilities

### New Capabilities

- `financial-ledger`: aprobación previa al asiento, historial operativo, registro contable inmutable, reversiones y consulta de saldos.
- `monthly-contributions`: cuota común, selección de meses consecutivos y acreditación de aportes al aprobar.
- `loan-funding`: reservas y desembolsos del fondo, recuperación de capital e intereses comunes.
- `fund-administration`: cuenta administrativa inicial, designación del tesorero y catálogo de bancos.

### Modified Capabilities

Ninguna. No existen especificaciones financieras consolidadas en este proyecto.

## Impact

La implementación afecta persistencia financiera, autorización, registro de evidencias, servicios de negocio, consultas de cartera y pruebas. Se conserva Laravel y Vue con Inertia. Se aplicó la migración financiera sobre MySQL y las pruebas usan una base MySQL independiente.

Las especificaciones describen el comportamiento acordado. Las tareas verificadas y las comprobaciones pendientes constan en `tasks.md`. El cambio no se archiva hasta comprobar los recorridos restantes.

## Scope boundaries

Los requisitos de aportes recogen cuota común por mes, inicio en el primer período configurado, selección consecutiva hasta diciembre y límite por monto. Las cuotas de meses futuros pueden cambiar mientras no hayan sido referenciadas en transacciones; una vez usadas quedan fijadas.

Las reglas confirmadas de amortización, vencimientos y pago se incorporan a `loan-funding`. Las amortizaciones extraordinarias a capital, cuotas parciales, recargos y condonaciones quedan fuera del alcance vigente.

La evidencia consiste en un archivo privado JPG, PNG o PDF de hasta 10 MB. La conciliación usa los estados PENDIENTE, APROBADA y RECHAZADA con motivo obligatorio. La corrección contable de aprobados comprende reversión y reemplazo juntos, para corregir el registro de un hecho bancario, no para ejecutar devoluciones.

## Superseded discovery decisions

El usuario simplificó expresamente el alcance: se eliminan cuentas de ahorros, porcentajes de autorización, retiros, fuentes personales de préstamos y reparto de intereses entre usuarios. Las especificaciones de esas capacidades se retiran de este cambio todavía no implementado. No se requiere una migración de datos existentes.

Se corrigió la regla anterior que derivaba el primer mes de la fecha de creación del usuario: la primera cuota la determina el tesorero al crear el período inicial en `contribution_periods`.

Los aportes y los intereses cobrados pertenecen directamente al fondo común. La atribución del aporte al usuario es histórica e informativa, no un saldo retirable.

El usuario confirmó la asignación exacta. Una diferencia entre el monto de la transferencia y sus asignaciones impide registrar o aprobar la transacción. No se descarta dinero ni se convierte un excedente en otro concepto; el monto declarado debe corresponder a la evidencia bancaria.
