## ADDED Requirements

### Requirement: Panel de incorporación exclusivo

El sistema SHALL ofrecer una sola página de incorporación al tesorero con acciones Invitar y Crear cuenta y un historial de invitaciones. Un usuario sin permiso de tesorería SHALL NOT acceder a la página ni ejecutar sus acciones.

#### Scenario: Participante común

- **WHEN** un participante intenta abrir la gestión de participantes o enviar una invitación
- **THEN** recibe acceso denegado sin obtener correos ni datos de otras personas.

### Requirement: Enlace de invitación de un solo uso

El tesorero SHALL poder invitar a un correo que no tenga cuenta. SHALL enviarse un enlace firmado con token aleatorio de uso único que vence en siete días. SHALL almacenarse solo el hash del token. La persona invitada SHALL registrar su nombre y contraseña desde el enlace; la cuenta usará exactamente el correo invitado. Una nueva invitación para el mismo correo SHALL dejar sin efecto las anteriores y conservar la auditoría.

#### Scenario: Aceptar una invitación válida

- **WHEN** el destinatario envía nombre y contraseña válidos usando el enlace no vencido
- **THEN** se crea una sola cuenta para el correo invitado, el enlace se marca utilizado y la persona puede iniciar sesión.

#### Scenario: Reutilización o vencimiento

- **WHEN** alguien vuelve a enviar el enlace usado o intenta usarlo después de siete días
- **THEN** el sistema impide crear otra cuenta y no expone el token ni otro usuario.

### Requirement: Creación directa sin compartir contraseña

El tesorero SHALL poder crear directamente una cuenta con nombre y correo. SHALL asignarse una contraseña aleatoria no visible para él, enviar un enlace de configuración de contraseña mediante el broker de Fortify y conservar la identidad de quien creó al participante.

#### Scenario: Cuenta creada directamente

- **WHEN** el tesorero crea una cuenta para un correo nuevo
- **THEN** aparece el participante, se envía el enlace para definir contraseña y el tesorero no obtiene la credencial.

### Requirement: Registro público cerrado

El sistema SHALL mantener desactivadas las rutas abiertas de registro. Solo la aceptación de una invitación válida y la creación directa autorizada SHALL poder incorporar participantes.

#### Scenario: Intento de registro abierto

- **WHEN** un visitante solicita el registro público sin invitación
- **THEN** no se crea usuario y no se muestra un formulario de registro general.
