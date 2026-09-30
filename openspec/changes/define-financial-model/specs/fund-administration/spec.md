## ADDED Requirements

### Requirement: FA-01 Cuenta administrativa inicial

El sistema SHALL crear una cuenta administrativa inicial mediante el proceso de instalación con nombre, correo y credenciales proporcionados expresamente por el operador. SHALL NOT asignar administración automáticamente al primer registro público ni incluir credenciales predeterminadas. La instalación SHALL registrar la designación inicial y no crear otra cuenta o sustituir al administrador al repetirse.

#### Scenario: Instalación repetida

- **WHEN** el fondo ya tiene administrador y se vuelve a ejecutar su instalación
- **THEN** se conserva la cuenta y designación existentes, sin reiniciar saldos ni sobrescribir credenciales.

### Requirement: FA-02 Un tesorero designado desde la aplicación

El sistema SHALL permitir al administrador designar desde la aplicación una cuenta existente como único tesorero. SHALL permitir transferir la responsabilidad a otra cuenta en una operación atómica, conservando actor, fecha, responsable anterior y nuevo. La administración por sí sola SHALL NOT conceder permisos financieros; el administrador puede designarse tesorero mediante el mismo flujo auditado. Las operaciones históricas SHALL conservar su actor original tras una transferencia de tesorería.

#### Scenario: Transferencia de responsabilidad

- **WHEN** el administrador cambia la tesorería de un participante a otro
- **THEN** solo el nuevo designado puede realizar nuevas operaciones de tesorería y las decisiones anteriores siguen atribuidas a sus autores.

#### Scenario: Autodesignación no autorizada

- **WHEN** un participante que no es administrador intenta designarse tesorero
- **THEN** la operación se deniega y no se modifica el responsable.

### Requirement: FA-03 Catálogo de bancos

El sistema SHALL permitir al tesorero mantener un catálogo de bancos y a los usuarios seleccionar el banco de origen de su comprobante. SHALL usar el identificador del banco para detectar duplicados junto al número del comprobante. SHALL conservar la identidad del banco y el nombre presentado al registrar cada transacción. Los bancos referenciados SHALL NOT eliminarse; podrán desactivarse para nuevos registros conservando consultas y conciliación de los existentes.

#### Scenario: Selección del banco

- **WHEN** un usuario registra un comprobante
- **THEN** selecciona un banco activo del catálogo y no crea una identidad alternativa escribiendo otro nombre.

#### Scenario: Banco desactivado

- **WHEN** el tesorero desactiva un banco con transacciones históricas
- **THEN** deja de ofrecerse para nuevos registros, pero los registros y sus evidencias permanecen consultables y los pendientes pueden conciliarse.

### Requirement: FA-04 Permisos de consulta

El sistema SHALL limitar a los participantes la consulta de sus propias transacciones, aportes, préstamos, amortizaciones y evidencias. El tesorero SHALL poder consultar la información financiera del fondo y de sus participantes para conciliar. El administrador que no sea tesorero SHALL gestionar la designación de tesorería sin obtener automáticamente acceso financiero ajeno.

#### Scenario: Consulta de préstamo ajeno

- **WHEN** un participante sin permiso de tesorería intenta consultar el préstamo de otro
- **THEN** se deniega la consulta incluso si conoce su identificador.
