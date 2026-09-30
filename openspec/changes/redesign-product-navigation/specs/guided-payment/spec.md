## ADDED Requirements

### Requirement: Registro guiado de transferencia

El formulario SHALL guiar al participante por datos bancarios y evidencia, asignación de meses y cuotas completas consecutivas, y revisión final de importes. SHALL permitir retroceder sin perder datos y señalar el paso actual. SHALL restringir la selección por el monto disponible y no SHALL enviar una asignación incompleta o excedida.

#### Scenario: Selección exacta

- **WHEN** el usuario registra USD 100 y selecciona cuatro meses consecutivos de USD 25
- **THEN** el resumen indica asignación exacta y permite enviar el registro como PENDIENTE.

#### Scenario: Sobrante

- **WHEN** el usuario registra USD 110 y solo asigna cuatro meses de USD 25
- **THEN** ve que faltan USD 10 por asignar y no puede confirmar el envío.

### Requirement: Accesibilidad y móvil

Las pantallas SHALL permitir uso con teclado y etiquetas legibles. En móvil, registrar comprobante, comprobar estado y consultar próximos pagos SHALL ser accesible sin tablas horizontales para esas tareas principales; las tablas financieras extensas pueden desplazarse en su propio contenedor.

#### Scenario: Registro desde teléfono

- **WHEN** un usuario abre el formulario en un ancho de teléfono
- **THEN** puede identificar el paso actual, adjuntar evidencia, revisar el importe y corregir errores sin perder lo ya escrito.

### Requirement: Motivos de texto extensos

Los campos para motivos de rechazo, cancelación y corrección SHALL ser áreas de texto con etiquetas accesibles, capaces de crecer con el contenido sin perder saltos de línea. SHALL respetar la validación de longitud existente del servidor.

#### Scenario: Motivo largo

- **WHEN** el tesorero escribe un motivo de varias líneas
- **THEN** el campo crece con el contenido, permite editarlo completo y conserva el texto al enviarlo.
