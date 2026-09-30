# Diseño propuesto del modelo financiero

Estado: implementación en curso. Fecha: 2026-09-27.

## Context

La aplicación administra un fondo común formado por aportes mensuales fijos no retirables y por los intereses cobrados de sus préstamos. El tesorero también aporta, puede recibir préstamos y conciliar sus propias operaciones. El sistema comienza sin saldos previos y usa exclusivamente USD.

El usuario redujo expresamente el alcance: no existen cuentas de ahorro, retiros, autorizaciones porcentuales ni reparto de rendimientos personales. Todas las transacciones registradas por usuarios quedan PENDIENTES fuera del ledger hasta que el tesorero las aprueba.

Los requisitos de `specs/` consolidan las respuestas del usuario. El mapa contable, los importes en centavos y la separación entre registros pendientes, historial operativo y ledger están aprobados. D11 a D14 documentan la implementación sobre MySQL; su migración se aplicó y las pruebas usan una base MySQL independiente.

## Goals / Non-Goals

### Goals

- Explicar cada saldo con movimientos trazables.
- Distinguir registros pendientes, efectivo, disponibilidad, reservas y deuda.
- Evitar doble uso de dinero y duplicación por reintentos.
- Separar aportes, capital recuperado e intereses cobrados.
- Conservar toda la trazabilidad y corregir operaciones sin borrar historia.

### Non-Goals

- Admitir pagos parciales, amortizaciones extraordinarias a capital, recargos por mora o condonaciones.
- Diseñar pantallas, rutas, almacenamiento de evidencias o permisos de consulta global.
- Importar saldos históricos, convertir monedas o ejecutar transferencias bancarias automáticas.

## Decisions

### D1. Ledger de partida doble y registros de reservas

Decisión confirmada: usar asientos balanceados para hechos financieros aprobados y un historial operativo separado e inmutable para todos los registros y decisiones. Las transacciones pendientes o rechazadas no tienen asientos, ni siquiera asientos provisionales. Las reservas cambian disponibilidad, pero no se registran como movimientos del ledger. Las vistas de saldo son proyecciones reconstruibles, no una fuente editable de verdad.

Alternativa: un historial de entradas y salidas sin partida doble. Es más corto inicialmente, pero dificulta comprobar la conservación entre efectivo, préstamos por cobrar y patrimonio común.

Mapa contable aprobado:

| Operación                        | Débito                                          | Crédito                                                     |
| -------------------------------- | ----------------------------------------------- | ----------------------------------------------------------- |
| Aporte aprobado                  | Efectivo administrado                           | Patrimonio común por aportes, con participante identificado |
| Desembolso aprobado              | Préstamos por cobrar, identificando el préstamo | Efectivo administrado                                       |
| Recuperación de capital aprobada | Efectivo administrado                           | Préstamos por cobrar, identificando el préstamo             |
| Interés común cobrado            | Efectivo administrado                           | Rendimientos retenidos del fondo                            |

Los aportes individuales son atribuciones históricas del patrimonio común, no obligaciones retirables. Desembolsar un préstamo cambia efectivo por una cuenta por cobrar; no consume patrimonio ni descuenta aportes históricos. Recuperar su capital tampoco es una ganancia. Los intereses cobrados sí incrementan el patrimonio común.

### D2. Exactitud monetaria y componentes del pago

Decisión confirmada: importes registrados en centavos enteros de USD y aritmética exacta para tasas y cálculos. Ningún importe contabilizado usa punto flotante binario.

No existe distribución entre fuentes o usuarios. Cada monto de transacción es positivo, sus asignaciones son no negativas y su suma debe explicar exactamente el total tanto al registrar como al aprobar. Cada pago de préstamo se desglosa en capital e interés; únicamente el componente de capital se compara con el principal pendiente y no puede superarlo. Una reversión compensa los importes originales, no recalcula una cuota con condiciones actuales.

El préstamo usa la tabla inmutable definida en D9. El ledger recibe sus componentes y los contabiliza; no calcula una amortización diferente al aprobar. Solo se admiten cuotas completas, por lo que el principal y el interés de cada cuota se liquidan juntos. El esquema no crea un interés residual independiente después de pagar la última cuota.

### D3. Modelo conceptual propuesto

| Entidad                 | Datos principales                                                                       | Relaciones                                                                                     |
| ----------------------- | --------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| Operación financiera    | Identificador, clase, USD, actor, beneficiario, instante, clave de idempotencia, origen | Una operación tiene varios asientos y eventos                                                  |
| Asiento y línea         | Operación, cuenta, débito o crédito, importe en centavos                                | Cada asiento tiene dos o más líneas balanceadas                                                |
| Registro de transacción | Usuario, banco, referencia, fecha bancaria, monto, evidencia, estado derivado           | Asignaciones propuestas a meses y préstamos; cero asientos mientras está pendiente o rechazado |
| Evento operativo        | Actor, instante, acción, registro o reserva afectada, motivo cuando aplique             | Historial de creación, revisión, decisión y corrección                                         |
| Aporte mensual          | Usuario, mes, cuota aplicable, pago acreditado                                          | Una aplicación aprobada por usuario y mes, con historial de compensaciones                     |
| Reserva del fondo       | Importe, prestatario, préstamo, actor, eventos                                          | Un compromiso previo al desembolso, sin asiento contable                                       |
| Préstamo                | Prestatario, principal, plazo, tasa, amortización, evidencia                            | Una reserva y varias aplicaciones de pagos                                                     |
| Aplicación de pago      | Transacción aprobada, préstamo, capital e interés                                       | Vinculada a los asientos creados por la aprobación                                             |
| Reversión               | Operación original, actor, motivo, instante                                             | Una compensación completa por operación original                                               |

Este es un mapa de dominio para revisión, no una migración aprobada. Nombres físicos, tipos de identificadores, índices y contratos de acciones quedan pendientes del diseño de implementación.

### D4. Saldos del fondo y procedencia

Dentro del alcance vigente, sin gastos, pérdidas ni distribuciones, las identidades son:

```text
patrimonio_fondo = aportes_aprobados_netos + intereses_cobrados_netos
efectivo_contable = patrimonio_fondo - principal_prestado_pendiente
disponible_para_prestar = efectivo_contable - reservas_activas
patrimonio_fondo = efectivo_contable + principal_prestado_pendiente
```

Los importes netos incluyen compensaciones registradas. Las reservas se descuentan una sola vez del disponible, no del efectivo ni del patrimonio. Las transacciones pendientes no integran ninguna de estas magnitudes. El efectivo contable representa lo reconocido por el sistema y puede diferir del banco mientras haya transferencias sin conciliar.

| Valor visible o producido  | Procedencia                                                             |
| -------------------------- | ----------------------------------------------------------------------- |
| Aportes del usuario        | Atribuciones de aportes aprobados y compensaciones                      |
| Ganancias del fondo        | Todo interés de pagos aprobados y sus compensaciones                    |
| Efectivo del fondo         | Entradas y salidas aprobadas del ledger                                 |
| Reservas activas           | Eventos operativos de reserva, cancelación y consumo por desembolso     |
| Disponible para prestar    | Efectivo contable menos reservas activas                                |
| Deuda del prestatario      | Desembolsos menos capital pagado, con compensaciones                    |
| Capital e interés del pago | Componentes inmutables de las cuotas seleccionadas, calculadas según D9 |
| Meses pagados              | Aplicaciones de aportes aprobados y compensaciones                      |
| Primer mes exigible        | Mes mínimo configurado en contribution_periods                          |
| Mes más antiguo pendiente  | Primer mes configurado desde el inicio sin aporte aprobado vigente      |
| Motivo y evidencia         | Entrada del actor en la operación correspondiente                       |

### D5. Aportes y transacciones combinadas

La cuota mensual es común y la define el tesorero. El usuario selecciona meses completos y consecutivos desde el más antiguo exigible sin pago acreditado. Para meses con una misma tarifa:

```text
importe_para_aportes = monto_transaccion - importe_asignado_a_prestamos
maximo_meses = parte_entera(importe_para_aportes / cuota_mensual)
```

Decisión confirmada: se exige asignación exacta al registrar y se valida nuevamente al aprobar:

```text
monto_transaccion = suma_cuotas_mensuales_seleccionadas + suma_pagos_prestamos
```

El máximo de meses es un límite, no una autorización para descartar un sobrante. Si las asignaciones suman más o menos que el monto, el formulario informa la diferencia e impide registrar la transacción. Si la validación falla al aprobar un registro existente, permanece PENDIENTE sin asientos. No se admiten aportes parciales ni se cambia automáticamente el monto o el destino del dinero para cuadrar; el monto debe corresponder a la evidencia bancaria.

Decisiones confirmadas del calendario:

- La obligación de aportar empieza en el primer período configurado en `contribution_periods`, sin depender de cuándo se creó la cuenta. La cuota de cada mes es completa; los instantes se conservan en UTC y el calendario de negocio usa America/Guayaquil.
- Cada mes tiene una cuota común para todos los participantes. El tesorero puede cambiar cuotas de meses futuros que todavía no aparezcan en transacciones registradas. Una vez referenciado el mes, se conserva su valor, independientemente de cuándo se apruebe la transacción.
- Un usuario con una transacción PENDIENTE que contiene aportes no puede registrar otra con aportes hasta que se apruebe o rechace la primera. Puede registrar pagos exclusivos de préstamos.
- Este bloqueo pertenece al registro operativo y no significa que los meses estén pagados. La aprobación vuelve a comprobar que no existan aplicaciones duplicadas.

- Se admiten adelantos hasta diciembre del año actual en America/Guayaquil. Los atrasos de años anteriores siguen pendientes y deben cubrirse primero. Cada mes requiere una cuota configurada; si falta, se solicita su configuración al tesorero y no se inventa un importe.

### D6. Estados y atomicidad

| Flujo                   | Transiciones                                                   |
| ----------------------- | -------------------------------------------------------------- |
| Transacción del usuario | PENDIENTE → APROBADA o RECHAZADA                               |
| Reserva de préstamo     | RESERVADA → DESEMBOLSADA o CANCELADA                           |
| Corrección              | Operación original conservada más reversión completa vinculada |

Los estados son una lectura del historial operativo. Las columnas de estado o saldo que se usen para acelerar consultas serán proyecciones; no sustituyen los eventos originales. Los registros enviados no se editan. Para corregir un pendiente, el tesorero lo rechaza con motivo y el usuario envía un nuevo registro vinculado al anterior, que vuelve a comenzar PENDIENTE.

Flujo obligatorio de aprobación:

1. El usuario envía datos, evidencia y asignaciones. Tras validar el monto y su asignación exacta, se crea el registro PENDIENTE y su evento operativo, sin asiento.
2. El tesorero revisa la transacción. La revisión tampoco crea asientos.
3. Al aprobar, se validan permisos, estado pendiente, monto, evidencia, asignaciones y saldos actuales.
4. Se confirman juntos la aprobación, sus asientos y los meses o pagos aplicados. Si falla un paso, no se confirma ninguno.
5. Al rechazar, solo se registra la decisión con motivo. No hay asiento que revertir.

Preparar o reservar un préstamo no es desembolsarlo. La confirmación explícita del tesorero, con evidencia, es la aprobación del desembolso y genera su asiento; adjuntar el archivo por sí solo no lo hace.

Se propone una clave de idempotencia por comando, unicidad por aprobación y reversión, y control de concurrencia sobre el registro, los meses, préstamos y disponibilidad del fondo afectados. Un reintento nunca duplica asientos. Dos revisiones concurrentes no pueden aprobar y rechazar el mismo registro, ni acreditar dos veces el mismo mes. Ante error no quedan asientos o reservas parciales.

El usuario eligió MySQL con InnoDB para implementación y pruebas; se confirmó el motor de ambas bases. La estrategia de D13 usa bloqueos de fila y unicidad de MySQL. Falta una prueba con operaciones simultáneas desde conexiones independientes, más allá de las pruebas de secuencia e idempotencia existentes.

### D7. Reversiones y dinero externo

Decisión confirmada: una corrección contable consiste en compensar la operación aprobada completa y aprobar su reemplazo en una sola transacción de base de datos. Se exige motivo y se conservan los datos y evidencias del original, la compensación y el reemplazo. Si no se puede confirmar todo, el original continúa vigente. No se ofrece reversión aislada ni se exponen saldos intermedios.

El flujo corrige datos de un hecho bancario, no ejecuta devoluciones. El reemplazo debe cumplir las mismas validaciones que una operación nueva, incluido monto exacto, evidencia, cuotas completas y permisos. Puede conservar banco y referencia del original mediante una sustitución atómica de su vigencia. Nunca puede ocupar la identidad de un comprobante ajeno.

Dependencias que impiden confirmar la corrección:

- Cambiar condiciones o fecha de un desembolso con pagos registrados sobre su tabla. Se muestran esos registros y no se reescribe su amortización.
- Corregir un pago de préstamo cuando existen pagos aprobados posteriores en el mismo préstamo. No se reconstruyen automáticamente pagos dependientes.
- Cambiar aplicaciones de aportes de forma que se duplique un mes o quede una secuencia incompatible con aportes posteriores ya aprobados.
- Obtener efectivo o disponibilidad negativos al considerar juntos compensación, reemplazo y reservas vigentes.

La aplicación informa el bloqueo y conserva el original; esta versión no introduce un mecanismo para saltarlo ni modificar el historial. Las transacciones pendientes o rechazadas no tienen asientos que revertir: para corregir pendientes se usa rechazo y nuevo registro.

### D8. Límites de acceso acordados

El usuario registra sus transacciones y consulta sus aportes, préstamos y decisiones. El tesorero define cuotas, administra bancos, concilia transacciones, reserva y desembolsa préstamos, cancela reservas y confirma correcciones contables. Puede operar sobre sí mismo, pero registrar su propio depósito también crea un PENDIENTE y exige una aprobación explícita posterior para contabilizarlo.

El tesorero consulta los saldos y la trazabilidad de todos los participantes. Los usuarios ordinarios solo consultan su información. El administrador gestiona la designación del único tesorero y no obtiene acceso financiero ajeno por su condición de administrador, salvo que también esté designado como tesorero.

### D9. Amortización y aplicación de cuotas

Decisiones confirmadas: sistema francés, tasa mensual fija no negativa, plazo en meses, cuotas completas consecutivas, adelantos sin recalcular intereses y atrasos sin recargo. La fecha bancaria del desembolso determina la primera cuota y los vencimientos mensuales.

Sean `P` el principal en centavos, `i` la tasa mensual porcentual dividida entre 100 y `n` el plazo en meses:

```text
cuota_exacta = P * i / (1 - (1 + i)^(-n))  si i > 0
cuota_exacta = P / n                      si i = 0
cuota_base = redondear_al_centavo(cuota_exacta)
interes_cuota = redondear_al_centavo(saldo_anterior * i)
capital_cuota = cuota_base - interes_cuota
ultima_cuota = saldo_principal_restante + interes_ultima_cuota
```

La aritmética intermedia es decimal o racional exacta. El redondeo de mitades es hacia arriba y opera sobre centavos, no sobre valores binarios aproximados. El interés se calcula sobre el saldo principal restante después del redondeo de la cuota anterior. Se valida que ninguna cuota sea no positiva, ningún capital sea negativo y que el saldo final sea cero. Si una combinación de monto y plazo produce una tabla inválida por granularidad de centavos, se impide confirmar el préstamo y se explica que debe ajustarse el monto o plazo.

El vencimiento número `k` se obtiene sumando `k` meses a la fecha original del desembolso y ajustando el día solo para el mes de destino. Nunca se suma un mes a una fecha previamente recortada. El atraso comienza al día siguiente del vencimiento en America/Guayaquil.

La tabla se conserva al confirmar el desembolso. Cada pago selecciona identificadores de cuotas completas; el servidor obtiene de ellas capital e interés. Se pueden adelantar cuotas futuras, incluso de años posteriores: el límite de diciembre aplica a aportes, no a cuotas de préstamos. Un pago pendiente no cancela cuotas. Si una aprobación previa invalida la selección, la aprobación posterior se bloquea y el tesorero puede rechazar el registro para que se presente uno corregido.

### D10. Administración y bancos

Durante la instalación se crea una cuenta administrativa con datos y credenciales proporcionados por el operador, sin valores predeterminados ni privilegios automáticos para el primer registro público. El procedimiento debe poder repetirse sin sobrescribir al administrador, sus credenciales ni los saldos. La designación inicial se registra como evento de instalación.

Desde la aplicación, el administrador selecciona al único tesorero entre las cuentas existentes. El cambio se confirma de forma atómica y audita responsable anterior, nuevo, actor y fecha. Los permisos se comprueban otra vez al ejecutar cada operación financiera, para que una sesión abierta del tesorero anterior no conserve acceso.

El tesorero administra un catálogo de bancos. Se conserva una identidad estable por banco; un cambio de nombre no crea otro banco ni altera el nombre capturado en comprobantes históricos. La desactivación impide nuevas selecciones y no impide conciliar registros previos. Los bancos usados no se eliminan.

### D11. Persistencia propuesta para MySQL

Este modelo físico desarrolla la tarea 1.3 y fue aplicado en `database/migrations/2026_09_27_145117_create_family_fund_tables.php`. Usa claves primarias enteras, claves foráneas con borrado restringido para historia financiera, instantes UTC y fechas bancarias sin hora. Todos los importes usan enteros de centavos y los porcentajes decimales exactos. No se instaló una dependencia contable externa.

| Tabla                     | Datos y relaciones principales                                                                                                                      | Restricciones                                                                                               |
| ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `fund_settings`           | Fila única, `administrator_id`, `treasurer_id` nullable antes de designar, moneda USD, zona America/Guayaquil                                       | Referencias a `users`; fila usada para serializar operaciones del fondo                                     |
| `banks`                   | Nombre, nombre normalizado, activo, fechas                                                                                                          | Nombre normalizado único; identidad estable                                                                 |
| `contribution_periods`    | Mes como primer día del mes, `amount_cents`, instante de primera referencia                                                                         | Mes único; importe positivo; cuota bloqueada al primer registro que la usa                                  |
| `evidences`               | Propietario, autor de carga, ruta privada, nombre original, MIME, tamaño, huella del contenido, fecha                                               | Ruta única; acceso autorizado; datos inmutables tras vincular                                               |
| `fund_transactions`       | Usuario, banco y nombre capturado, referencia original y normalizada, fecha bancaria, monto, evidencia, estado, anterior corregido, reemplazado por | Datos del registro inmutables; estado y vigencia son proyecciones del historial                             |
| `transaction_allocations` | Transacción, mes o cuota de préstamo, importe, capital e interés cuando aplique                                                                     | Exactamente uno de mes o cuota; sin destinos duplicados en una transacción; sumas verificadas por la acción |
| `loans`                   | Prestatario, principal, tasa mensual porcentual exacta, plazo, estado de reserva, datos bancarios de desembolso, evidencia y fecha                  | Condiciones fijadas al desembolsar; principal y plazo positivos, tasa no negativa                           |
| `loan_installments`       | Préstamo, número, vencimiento, capital, interés y saldo previsto                                                                                    | Número único por préstamo; tabla inmutable después del desembolso                                           |
| `journal_entries`         | Clase, origen transacción o préstamo, actor, instante, asiento compensado cuando aplique                                                            | Una contabilización por aprobación; una compensación por asiento original; filas inmutables                 |
| `journal_lines`           | Asiento, cuenta, lado débito o crédito, centavos, usuario y préstamo cuando corresponda                                                             | Importes positivos; cuentas cerradas: efectivo, aportes, préstamos por cobrar, intereses; filas inmutables  |
| `operation_events`        | Actor, evento, instante, entidad e identificador, datos de contexto y vínculos                                                                      | Solo inserciones; conserva decisiones, configuración y correcciones                                         |
| `operation_requests`      | Actor, clase de acción, clave de idempotencia, huella de entrada, resultado                                                                         | Clave única por actor y acción; misma clave con otro contenido es un conflicto                              |

Las reservas activas se obtienen de préstamos en estado RESERVADA, junto con sus eventos; no necesitan otro saldo editable. Los meses pagados y las cuotas liquidadas se derivan de asignaciones de transacciones aprobadas vigentes. Los registros sustituidos por correcciones permanecen consultables pero no se cuentan como aplicaciones vigentes.

Índices y defensa frente a duplicados:

- Unicidad de mes en `contribution_periods` y de `(loan_id, number)` en cuotas.
- En ingresos, unicidad de `(bank_id, active_reference)`; `active_reference` es la referencia normalizada solo mientras el registro esté pendiente o aprobado vigente, y NULL si fue rechazado o sustituido. Es una proyección, no la referencia histórica original.
- Unicidad de `pending_contributor_id`, proyección con el usuario solo para transacciones pendientes que contienen aportes, y NULL en otros casos.
- Índices de consultas por usuario, estado, fecha e identificador; destinos de asignaciones indexados por período o cuota.
- La referencia se normaliza eliminando espacios y convirtiendo a mayúsculas; conserva ceros iniciales y signos. El banco se identifica por su clave, no por texto enviado por el cliente.
- El acceso de escritura a asientos, líneas y eventos debe impedir actualizaciones y borrados, tanto desde los modelos como mediante protecciones de MySQL. El balance de un asiento se valida con el conjunto completo dentro de la transacción antes de confirmar.

Las cuentas existentes que tengan historia financiera o sean administrador o tesorero no deben eliminarse mediante el flujo de perfil. La integridad referencial conserva sus vínculos y la interfaz debe explicar el impedimento.

### D12. Contratos de acciones y consultas propuestos

Las rutas usarán sesión autenticada, autorización por rol o propiedad y validación en servidor. Las rutas de escritura aceptan una clave de idempotencia. Las acciones de negocio viven en `app/Actions`, siguiendo la estructura del proyecto; controladores delgados y validadores específicos evitan duplicar reglas. La respuesta de un conflicto no cambia el registro ni deja efectos parciales.

| Acción                   | Entradas                                                                | Resultado y permiso                                                                             |
| ------------------------ | ----------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| Configurar cuota         | Mes e importe decimal exacto                                            | Cuota común y evento; tesorero, solo creación o cambio permitido por MC-01                      |
| Mantener banco           | Nombre o estado activo                                                  | Banco y evento; tesorero                                                                        |
| Registrar ingreso        | Banco, referencia, fecha, monto, archivo y meses o cuotas seleccionados | PENDIENTE sin asiento; usuario para sí mismo                                                    |
| Aprobar ingreso          | Identificador pendiente                                                 | Decisión, asientos y aplicaciones atómicos; tesorero                                            |
| Rechazar ingreso         | Identificador pendiente y motivo                                        | Rechazo sin asiento; tesorero                                                                   |
| Reservar préstamo        | Prestatario, principal, tasa mensual, plazo en meses                    | Reserva, condiciones y evento; tesorero                                                         |
| Cancelar reserva         | Préstamo reservado y motivo                                             | Liberación sin asiento; tesorero                                                                |
| Confirmar desembolso     | Reserva, banco, referencia, fecha y evidencia                           | Tabla, asiento, deuda y consumo de reserva; tesorero                                            |
| Corregir contabilización | Original, motivo y reemplazo completo validable                         | Compensación y reemplazo aprobados juntos; tesorero                                             |
| Designar tesorero        | Usuario existente                                                       | Cambio único de responsable y evento; administrador                                             |
| Consultar cartera        | Usuario autenticado                                                     | Aportes, registros, préstamos y tablas propios; tesorero puede consultar cualquier participante |
| Consultar fondo          | Filtros de fecha                                                        | Efectivo, reservas, principal pendiente, aportes e intereses y trazabilidad; tesorero           |
| Consultar evidencia      | Identificador vinculado                                                 | Archivo privado; propietario o tesorero                                                         |

La configuración de cuotas requiere monto para cada mes antes de registrar un aporte. El primer período configurado determina el inicio común; la pantalla permite al tesorero configurar meses con el mismo importe, validando cada mes. No se asigna automáticamente un valor de ejemplo como USD 25.

### D13. Atomicidad, archivos e idempotencia

Toda mutación financiera o de configuración se ejecuta en una transacción MySQL InnoDB. La primera lectura de negocio dentro de ella bloquea la fila única de `fund_settings` mediante `SELECT ... FOR UPDATE`. Después se revalidan permiso actual, registro, cuotas y disponibilidad, y se escriben eventos, proyecciones y asientos. Este orden común serializa las escrituras del fondo y evita lecturas de saldo previas al bloqueo. Se pueden reintentar conflictos transitorios de la transacción completa; nunca solo el último asiento.

La clave de idempotencia se asocia a actor, acción y huella del contenido. Una repetición idéntica devuelve el resultado ya confirmado. Una repetición con contenido distinto se rechaza. Las restricciones únicas constituyen una segunda defensa para comprobantes, pendientes por usuario, contabilizaciones y compensaciones.

Los archivos se validan y almacenan en una ruta privada generada por el servidor antes de la transacción corta de base de datos. Si no se confirma su vinculación, se elimina únicamente el archivo nuevo no referenciado. Un reintento no sustituye una evidencia histórica. Las descargas pasan por una acción autorizada; no se publica el disco privado mediante enlaces de almacenamiento público.

La instalación crea la fila única del fondo antes de permitir operaciones. Las pruebas de concurrencia deben usar conexiones independientes a MySQL y una base de pruebas separada, verificando registro simultáneo, aprobación frente a rechazo, reserva simultánea y cambio de tesorero frente a una operación financiera.

### D14. Tipos físicos y superficie de aplicación

Convenciones de columnas para las tablas de D11:

| Dato                                | Tipo y validación                                                                                                                                     |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Identificadores y claves foráneas   | BIGINT UNSIGNED; las relaciones históricas impiden borrar el registro referenciado                                                                    |
| Importes                            | BIGINT de centavos, dentro del rango entero con signo de 64 bits; positivos en movimientos y cuotas, cero permitido en componentes de interés y saldo |
| Tasa mensual porcentual             | DECIMAL(12,6) no negativo, convertido a fracción mensual dividiendo entre 100; rechazar precisión excesiva en la entrada                              |
| Plazo y número de cuota             | SMALLINT UNSIGNED, mayor que cero; rechazar tablas fuera del rango de fechas o importes representables                                                |
| Mes de aporte y fecha bancaria      | DATE; el mes siempre usa día 1; fechas bancarias válidas, no futuras en America/Guayaquil                                                             |
| Instantes                           | DATETIME(6) UTC; creación, decisión y corrección conservan su instante original                                                                       |
| Moneda y zona                       | CHAR(3) fijado a USD y VARCHAR(64) fijado a America/Guayaquil en la instalación                                                                       |
| Referencia bancaria                 | VARCHAR(191) original y normalizada; validar longitud antes y después de normalizar                                                                   |
| Nombres de banco                    | VARCHAR(191) original y normalizado; nombre capturado en cada registro para consulta histórica                                                        |
| Estados, cuentas y clases de evento | VARCHAR(32) con valores cerrados controlados por enums y validación                                                                                   |
| Evidencia                           | Ruta VARCHAR(255), nombre VARCHAR(255), MIME VARCHAR(127), tamaño entero y SHA-256 CHAR(64); máximo 10 × 1024 × 1024 bytes                            |
| Idempotencia                        | Clave UUID CHAR(36), clase VARCHAR(64), huella SHA-256 CHAR(64), resultado JSON                                                                       |
| Motivos y datos del evento          | Motivo TEXT obligatorio para rechazo, cancelación y corrección; contexto JSON sin contraseñas ni credenciales                                         |

Los enlaces a decisiones o correcciones son nulos hasta que exista el evento correspondiente. En asignaciones, `contribution_period_id` y `loan_installment_id` son mutuamente excluyentes. Una asignación de cuota conserva sus componentes de capital e interés; una de aporte conserva su importe mensual. El servidor obtiene los importes de las cuotas o períodos, no acepta componentes arbitrarios del navegador.

Las proyecciones de unicidad (`active_reference`, `pending_contributor_id`) son columnas nullable y se actualizan junto al evento que cambia la vigencia. Los índices únicos de MySQL permiten múltiples NULL, pero no dos claves activas iguales. El histórico de referencia, monto y usuario nunca se reemplaza con estas proyecciones.

Superficie prevista, con rutas nombradas para su uso mediante Wayfinder:

| Método y ruta                                   | Nombre                                       | Acceso                                                     |
| ----------------------------------------------- | -------------------------------------------- | ---------------------------------------------------------- |
| GET `/fund`                                     | `fund.index`                                 | Cartera propia; vista global adicional para tesorero       |
| GET `/fund/transactions/create`                 | `fund.transactions.create`                   | Usuario autenticado                                        |
| POST `/fund/transactions`                       | `fund.transactions.store`                    | Usuario para sí mismo                                      |
| GET `/fund/transactions/{transaction}`          | `fund.transactions.show`                     | Propietario o tesorero                                     |
| POST `/fund/transactions/{transaction}/approve` | `fund.transactions.approve`                  | Tesorero                                                   |
| POST `/fund/transactions/{transaction}/reject`  | `fund.transactions.reject`                   | Tesorero                                                   |
| POST `/fund/transactions/{transaction}/correct` | `fund.transactions.correct`                  | Tesorero, reemplazo completo y motivo                      |
| GET `/fund/evidences/{evidence}`                | `fund.evidences.show`                        | Propietario o tesorero                                     |
| GET y POST `/fund/contribution-periods`         | `fund.contribution-periods.index` y `.store` | Tesorero                                                   |
| GET y POST `/fund/banks`                        | `fund.banks.index` y `.store`                | Tesorero                                                   |
| PATCH `/fund/banks/{bank}`                      | `fund.banks.update`                          | Tesorero                                                   |
| GET y POST `/fund/loans`                        | `fund.loans.index` y `.store`                | Listado propio o global autorizado; creación solo tesorero |
| GET `/fund/loans/{loan}`                        | `fund.loans.show`                            | Prestatario o tesorero                                     |
| POST `/fund/loans/{loan}/disburse`              | `fund.loans.disburse`                        | Tesorero                                                   |
| POST `/fund/loans/{loan}/cancel`                | `fund.loans.cancel`                          | Tesorero                                                   |
| POST `/fund/loans/{loan}/correct`               | `fund.loans.correct`                         | Tesorero, corrección del desembolso según D7               |
| GET y POST `/administration/treasurer`          | `administration.treasurer.edit` y `.update`  | Administrador                                              |

La interfaz reutilizará los layouts y componentes actuales de la aplicación. Las pantallas muestran formularios y tablas con estado vacío, mensajes de validación y confirmación de acciones; los listados ordenan por fecha e identificador y se paginan. Las respuestas de escritura redirigen al detalle con confirmación o errores de validación. Las peticiones no autorizadas reciben 403; identificadores inexistentes, 404; conflictos de estado o idempotencia, 409 cuando se responde como petición de datos. La validación de entradas usa los mecanismos normales de Laravel e Inertia.

Las migraciones crean primero configuración y catálogos, después registros, préstamos y asignaciones, y finalmente asientos y auditoría, respetando las claves foráneas. La instalación administrativa ocurre después de migrar. La base de pruebas debe ser diferente de la de desarrollo antes de ejecutar pruebas que reconstruyan el esquema.

### D15. Evidencias y comprobantes confirmados

La identidad de un comprobante de ingreso es banco del catálogo más número normalizado, sin fecha ni usuario. Una identidad no puede tener más de un registro PENDIENTE o APROBADO vigente. El rechazo libera esa identidad para un nuevo registro corregido, sin borrar el anterior. Cambiar la fecha no evita la comprobación. Una corrección de aprobado sustituye la vigencia únicamente dentro de su operación atómica.

Se exige un archivo JPG, PNG o PDF de hasta 10 MB por ingreso y desembolso confirmado. Los archivos son privados, accesibles por el usuario asociado y el tesorero mediante una acción autorizada. Se conserva la evidencia de los rechazados y de las operaciones corregidas. Validar tipo real y tamaño, no confiar solo en la extensión.

## Risks / Trade-offs

- La partida doble y las reservas separadas requieren más estructura, pero hacen verificable el origen y destino del dinero.
- Las reversiones completas son más sencillas que ajustes parciales, pero pueden exigir resolver varias dependencias antes de corregir un error.
- El dinero transferido al banco puede seguir pendiente de aprobación en el sistema. Los reportes deben distinguir efectivo contable y transacciones pendientes, sin sumarlas al disponible.
- La asignación exacta impide registrar transferencias con sobrantes o faltantes. Una discrepancia real con el banco debe resolverse sin alterar el monto declarado para forzar la igualdad ni crear asientos automáticos.
- La aprobación propia por el tesorero es una decisión explícita. La trazabilidad de actor y beneficiario se conserva en todos los casos.

## Open Questions

Estos puntos bloquean las tareas de implementación que dependan de ellos, no invalidan los requisitos de negocio ya acordados:

1. Ejecutar las pruebas de concurrencia con conexiones MySQL independientes para decisiones competidoras y reservas sobre el mismo capital.
2. Completar la revisión funcional de interfaz y archivar solo después de verificar el alcance. OpenSpec validó el cambio en modo estricto mediante su CLI ejecutado temporalmente, sin instalar dependencias en el proyecto.

## Delivery approach

Se propone construir por recorridos completos verificables: primero registro PENDIENTE de aportes, aprobación y consulta; luego reserva y desembolso de préstamos; después pagos de capital e interés y reportes; finalmente reversiones. Cada recorrido debe incluir persistencia, permisos, interfaz y pruebas del comportamiento. Las reglas dependientes de otras especificaciones se cierran antes de implementar el recorrido afectado.
