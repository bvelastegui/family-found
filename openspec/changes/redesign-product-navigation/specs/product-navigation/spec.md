## ADDED Requirements

### Requirement: Inicio operativo

El sistema SHALL ofrecer un Inicio autenticado en `/dashboard` que destaque el próximo aporte, transacciones propias en revisión y cuotas de préstamos del usuario, con acceso a registrar transferencia. La vista del tesorero SHALL incluir pendientes de conciliación, sin sumar importes PENDIENTES al saldo del fondo. `/fund` SHALL redirigir a `/dashboard`.

#### Scenario: Aportante sin movimientos

- **WHEN** un participante sin aportes ni préstamos abre Inicio
- **THEN** ve un estado vacío útil, su primera cuota configurada si existe y la acción para registrar transferencia.

#### Scenario: Aportante con pago en revisión

- **WHEN** el participante ya registró un aporte PENDIENTE
- **THEN** Inicio muestra que está en revisión, enlaza su detalle y no lo marca como pagado.

### Requirement: Navegación de primer nivel

El sistema SHALL presentar Inicio, Aportes, Transacciones y Préstamos como navegación general. SHALL mostrar en un grupo separado Tesorería las entradas Resumen, Cuotas y Bancos solo al tesorero. SHALL mostrar en un grupo Administración la designación del tesorero solo al administrador. SHALL destacar la entrada activa en páginas de detalle y usar la misma navegación en pantallas pequeñas. La marca visible SHALL decir Fondo Familiar.

#### Scenario: Usuario ordinario

- **WHEN** un participante sin permisos financieros navega por la aplicación
- **THEN** puede abrir las cuatro secciones personales y no ve enlaces de Tesorería o Administración.

#### Scenario: URL privilegiada conocida

- **WHEN** un usuario sin rol intenta acceder directamente a Tesorería o Administración
- **THEN** el servidor deniega el acceso y no entrega datos ajenos.

#### Scenario: Administrador sin tesorería

- **WHEN** el administrador no es el tesorero designado
- **THEN** ve Administración y no ve el grupo Tesorería con Resumen, Cuotas o Bancos.

### Requirement: Aportes como calendario personal

El sistema SHALL ofrecer Aportes como página propia organizada en años. SHALL mostrar los doce meses del año seleccionado sin paginación numérica, incluidos los meses antes del inicio del fondo o todavía sin cuota configurada. Los períodos definidos SHALL distinguir cuota pagada, en revisión, pendiente y próxima, con importe y vínculo a la transacción cuando corresponda. SHALL mostrar total aprobado del año y total histórico. El primer mes procede de `contribution_periods`, no del registro del usuario. El selector y enlaces entre años SHALL permitir consultar desde el primer año configurado hasta el año actual, sin mezclar meses de años distintos.

#### Scenario: Inscripción posterior al primer mes

- **WHEN** el primer período definido es enero y el usuario se registra en marzo
- **THEN** el calendario inicia en enero e identifica enero como pendiente hasta que una aprobación lo acredite.

#### Scenario: Dos años de aportes

- **WHEN** el fondo contiene períodos de 2026 y 2027
- **THEN** Aportes muestra doce meses del año seleccionado y permite cambiar de 2027 a 2026, conservando los estados y totales de cada año sin paginar los meses.

#### Scenario: Meses sin cuota

- **WHEN** febrero aún no tiene un período configurado
- **THEN** febrero aparece como cuota por definir, sin inventar un importe ni marcarlo como atraso.

### Requirement: Historial de transacciones

El sistema SHALL ofrecer un índice paginado de transacciones separado del calendario de aportes, con filtro por estado y por aportes o pagos de préstamos. Los participantes SHALL ver solo sus registros. El tesorero SHALL poder consultar todas las transacciones para conciliar. Cada transacción aprobada SHALL mostrar el nombre y la fecha local de quien la autorizó.

#### Scenario: Filtrar pendientes

- **WHEN** un usuario filtra PENDIENTE
- **THEN** ve solo registros de ese estado accesibles para él, con enlaces a su detalle y evidencia autorizada.

#### Scenario: Aprobación visible en el historial

- **WHEN** un tesorero aprueba una transferencia
- **THEN** la fila correspondiente muestra quién la autorizó y cuándo, además de su estado aprobado.

### Requirement: Fechas y títulos comprensibles

Las fechas bancarias SHALL mostrarse en español sin desplazamientos de día por zona horaria. Las fechas de decisiones SHALL mostrarse con hora en America/Guayaquil. La vista de Transacciones SHALL tener un título principal visible único y evitar encabezados repetidos para el mismo historial.

#### Scenario: Fecha bancaria e instante de aprobación

- **WHEN** una transferencia tiene fecha bancaria 2026-09-30 y su aprobación ocurre durante el día siguiente en UTC
- **THEN** la fecha bancaria sigue indicando 30 de septiembre, mientras la fecha de aprobación usa la fecha y hora local correcta del fondo.

### Requirement: Controles de paginación relevantes

Los listados paginados SHALL mostrar controles de página únicamente cuando exista más de una página. SHALL reutilizar un mismo componente de navegación, con el número de páginas proporcionado por el servidor y sin mostrar un botón aislado "1" cuando no hay registros o todo cabe en una página.

#### Scenario: Sin préstamos

- **WHEN** el usuario abre Préstamos y todavía no tiene ninguno
- **THEN** ve el estado vacío sin un enlace de página "1".
