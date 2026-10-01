# Incorporación privada de participantes

## Why

El fondo no debe permitir altas públicas. El tesorero necesita un único lugar desde el cual invitar a nuevos participantes o crear una cuenta directamente, sin conocer ni compartir sus contraseñas.

## What Changes

- Conservar desactivada la inscripción pública de Fortify.
- Permitir solo al tesorero emitir enlaces de invitación por correo, válidos una vez durante siete días.
- Dejar que la persona invitada indique nombre y contraseña desde el enlace asociado a su correo.
- Permitir al tesorero crear una cuenta con nombre y correo; el nuevo usuario establecerá su contraseña mediante el flujo de recuperación existente.
- Retirar la designación de tesorero de la web y sustituirla por un comando que busca usuarios por nombre.

## Capabilities

### New Capabilities

- `private-participant-onboarding`: invitación, aceptación y creación directa de participantes.
- `treasurer-assignment-command`: cambio de tesorero mediante un comando auditado.

### Modified Capabilities

- `product-navigation`: el grupo Tesorería incorpora Participantes y deja de mostrar Administración.

## Impact

Una tabla de invitaciones, acciones de negocio, una notificación por correo, formularios Inertia, un comando Artisan y pruebas MySQL. El transporte de correo actual es `log`, por lo que entregar correos reales requiere configurar un transporte al desplegar. Las cuentas históricas conservan sus aportes y acciones.
