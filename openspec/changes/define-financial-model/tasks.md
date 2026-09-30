## 1. Cerrar el diseño antes de implementar

- [x] 1.1 Revisar las especificaciones del alcance simplificado: ledger, aportes mensuales, préstamos del fondo y administración.
- [x] 1.2 Aprobar el mapa contable, los centavos de USD y la separación física entre registro pendiente, historial operativo y asiento aprobado (FL-01, FL-02, FL-03, FL-08, FL-09).
- [x] 1.3 Diseñar persistencia, tipos, acciones, permisos, idempotencia y concurrencia para MySQL, documentados en design.md D11 a D14. Su implementación y pruebas corresponden a las secciones siguientes (FL-03, FL-05, FL-07, LF-02, FA-01 a FA-04).
- [x] 1.4 Resolver vigencia de cuota, primer mes exigible desde `contribution_periods` para todos los usuarios y política de registros pendientes superpuestos; agregar escenarios (MC-01 a MC-05).
- [x] 1.5 Resolver comprobantes duplicados, corrección de asignaciones pendientes y formatos de evidencia, respetando la asignación exacta ya acordada (FL-03, FL-08, FL-10, FL-11).
- [x] 1.6 Especificar amortización, tasa, plazo, redondeo y aplicación de pagos antes de implementar cobros (LF-04 a LF-10).
- [x] 1.7 Precisar correcciones de transferencias reales y resolución de dependencias antes de habilitar reversiones (FL-06).

## 2. Aportes desde el registro hasta la aprobación

- [x] 2.0 Habilitar MySQL de desarrollo y una base separada para pruebas. Verificadas family_found y family_found_testing sobre InnoDB.
- [x] 2.1 Implementar apertura en cero, moneda única e historial operativo separado del ledger, con pruebas de inmutabilidad, partida doble y trazabilidad (FL-01, FL-02, FL-09).
- [x] 2.2 Implementar cuota común y selección de meses completos consecutivos limitada por el monto disponible; verificar igualdad para el tesorero (MC-01, MC-02, MC-03).
- [x] 2.3 Implementar registro PENDIENTE con evidencia y asignación exacta; probar sobrantes, faltantes, duplicados, único aporte pendiente y ausencia de asientos o aplicaciones al registrar (FL-02, FL-03, FL-08, FL-10, FL-11, MC-04, MC-05).
- [ ] 2.4 Implementar aprobación atómica y rechazo con motivo; reintentos y rechazos probados, falta prueba de decisiones concurrentes en conexiones MySQL independientes (FL-03, FL-07, FL-08).
- [x] 2.5 Mostrar aportes por mes, registros pendientes y patrimonio común sin saldos retirables, verificando conflictos de meses al aprobar (FL-04, FL-05, MC-04).
- [x] 2.6 Implementar instalación de cuenta administrativa, designación del único tesorero y catálogo de bancos; probar permisos y transferencia de responsabilidad (FA-01 a FA-04).

## 3. Préstamos financiados por el fondo

- [ ] 3.1 Implementar disponible del fondo y reserva atómica previa; saldos y ausencia de asientos probados, falta prueba de reservas simultáneas con conexiones MySQL independientes (LF-01, LF-02).
- [x] 3.2 Implementar cancelación con motivo sin vencimiento automático, conservando trazabilidad y liberando disponibilidad sin modificar efectivo (LF-03).
- [x] 3.3 Implementar confirmación de desembolso y tabla francesa con evidencia, deuda y consumo de reserva atómicos; probar tasa cero, redondeo, finales de mes, fallos y reintentos (LF-04, LF-08).

## 4. Pagos e intereses del fondo

- [x] 4.1 Integrar cuotas de préstamos completas y consecutivas, anticipos sin recálculo, atrasos sin recargo y transferencias combinadas, revalidando al aprobar (FL-03, FL-08, MC-03, LF-05, LF-09, LF-10).
- [x] 4.2 Registrar capital recuperado separado de intereses; probar que los pendientes no generan ganancias y todo interés aprobado permanece en el fondo (FL-04, LF-05, LF-06).
- [x] 4.3 Mostrar amortización, deuda, efectivo, reservas, disponible y ganancias, conciliando reportes con registros, decisiones, asientos y compensaciones (FL-05, LF-01, LF-07).

## 5. Correcciones y auditoría

- [x] 5.1 Implementar reversión completa y reemplazo aprobado en una sola operación, con motivo y dependencias; probar bloqueo, sustitución de referencia y compensación única (FL-06, FL-10).
- [x] 5.2 Verificar que correcciones preservan historial, evidencia, actores y vínculos, sin exponer disponibilidad transitoria ni tratar rechazados como movimientos a revertir (FL-02, FL-03, FL-06).

## 6. Verificar y consolidar

- [x] 6.1 Validar los artefactos con OpenSpec y revisar los escenarios contra las decisiones aprobadas. `npx --yes --package=@fission-ai/openspec openspec validate define-financial-model --strict --no-interactive`: cambio válido, sin añadir dependencias al proyecto.
- [x] 6.2 Ejecutar las pruebas de los recorridos implementados y verificar que no existen saldos negativos, duplicados ni dinero contado dos veces. Suite completa sobre MySQL independiente; volver a ejecutar tras cualquier cambio de código.
- [ ] 6.3 Revisar la experiencia del usuario y del tesorero, incluyendo operaciones propias y evidencia de correcciones.
- [ ] 6.4 Archivar el cambio y consolidar sus especificaciones en openspec/specs solo después de implementar y verificar el alcance aprobado.
