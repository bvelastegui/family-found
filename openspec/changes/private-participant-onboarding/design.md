# Diseño de incorporación privada

## Context

Fortify 1.x ya tiene `Features::registration()` desactivada y conserva inicio de sesión y restablecimiento de contraseñas. Una cuenta administrativa inicial se crea con `fund:install`. La designación actual tiene interfaz web; el tesorero es el único autorizado por el fondo para incorporar participantes.

## Decisions

### Invitaciones

Un registro guarda correo normalizado, hash del token aleatorio, tesorero emisor, vencimiento de siete días y consumo. El enlace se envía por notificación al correo indicado. GET y POST requieren la firma temporal del enlace; el token crudo nunca se guarda en la base ni aparece en listados o eventos. Al emitir otra invitación para el mismo correo, solo la más reciente queda activa. La aceptación se ejecuta en una transacción con bloqueo; verifica firma, token, correo disponible y plazo antes de crear la cuenta y marcar el enlace como usado. Tras completarla, redirige al inicio de sesión. Los enlaces vencidos o usados no permiten crear usuarios.

### Alta directa

El tesorero introduce nombre y correo desde la misma pantalla privada. Se crea la cuenta con una contraseña aleatoria desconocida para él y se envía un enlace de restablecimiento de Fortify para que el dueño defina su contraseña. Se conserva auditoría de quién creó la cuenta. El correo no puede pertenecer a otra cuenta o invitación pendiente.

### Designación de tesorero

`fund:assign-treasurer {name}` busca cuentas existentes por nombre. Con una coincidencia, el comando confirma el cambio; con varias, pide elegir mostrando nombre y correo o falla sin cambiar nada si se ejecuta sin interacción. Reutiliza la acción de designación que bloquea el fondo y audita responsable anterior y nuevo, actuando a través de la cuenta administrativa inicial. No hay rutas web de designación.

## Risks / Trade-offs

- La entrega de invitaciones y enlaces de contraseña depende del transporte de correo, que está configurado como `log` en desarrollo. Una configuración de correo real es necesaria antes de invitar destinatarios externos.
- Se conservan invitaciones antiguas para auditoría, pero solo la más reciente sin consumir es válida.
- Una cuenta creada directamente no podrá entrar hasta establecer su contraseña desde el enlace privado.
