## ADDED Requirements

### Requirement: Designación mediante búsqueda por nombre

El operador SHALL poder ejecutar un comando que busque usuarios existentes por nombre para designar al único tesorero. Una selección ambigua en modo no interactivo SHALL NOT cambiar el responsable. El cambio SHALL conservar la auditoría del responsable anterior y el nuevo.

#### Scenario: Nombre único

- **WHEN** el operador ejecuta el comando con un nombre que identifica a un usuario único
- **THEN** esa cuenta pasa a ser el único tesorero y el responsable anterior pierde acceso financiero.

#### Scenario: Varias coincidencias

- **WHEN** la búsqueda devuelve varias cuentas en una ejecución no interactiva
- **THEN** se muestran las coincidencias para precisar la búsqueda y no se designa a nadie.

### Requirement: No designación web

El sistema SHALL retirar del menú y de las rutas web la administración del tesorero. Mantener el identificador de administrador inicial SHALL permitir operar el comando sin crear un registro público.

#### Scenario: Antigua ruta

- **WHEN** alguien intenta acceder a la antigua página de designación
- **THEN** no encuentra una acción web que cambie el tesorero.
