# Mora semanal: cálculo y vista previa

Entrega: 18 de octubre de 2026.

## Regla vigente confirmada

- Mora simple del 10% sobre el saldo pendiente de cada cuota, por semana de atraso iniciada.
- Inicios: día siguiente al vencimiento y después cada siete días (días 1, 8, 15, 22…). El vencimiento mismo no genera mora.
- Una cuota de $800 sin abonos produce $80 por semana: $160 al comenzar la segunda semana. No se capitaliza la mora.
- Un abono reduce la base de los cargos siguientes. Si quedan $300, el siguiente cargo es de $30. No desaparecen los cargos calculados para semanas anteriores.
- Orden confirmado para la futura integración de Caja: cuotas exigibles pendientes, mora, adelantos. Este orden aún NO se activa en el flujo de cobros actual.
- Solo se retiene la garantía al desembolsar; no agregar una comisión de apertura.

Esta regla reemplaza las referencias previas al 10% mensual del contrato antiguo.

## Entrega disponible

En **Crédito → Estado de cuenta** aparece **Mora semanal estimada**, marcada **SIMULACIÓN · NO COBRABLE**. Muestra el total grupal o individual según el filtro, y un desglose desplegable: cuota original, semana de atraso, fecha de inicio, base pendiente y cargo estimado. Se puede recargar sin perder el filtro de la URL.

La simulación reconstruye las bases de las semanas con las asignaciones de pagos y garantías vigentes; no utiliza simplemente el saldo actual multiplicado por semanas. Comprueba que las asignaciones de cada cuota coincidan con su saldo registrado. Los reversos se excluyen de la reconstrucción, por lo que corregir un registro puede cambiar la estimación histórica. No se elimina ni se modifica ningún movimiento original.

Convenciones de esta vista previa:

- Fechas operativas de Cancún; el cargo se simula al inicio del día.
- Los abonos registrados ese mismo día reducen la base de la siguiente semana, no la del cargo ya iniciado.
- Para depósitos se utiliza la fecha de registro en Caja, no la fecha del comprobante bancario. Antes de activar cargos, comprobar que esta convención coincide con la operación de depósitos presentados tarde.
- El 10% se calcula en centavos enteros y se redondea por cargo al centavo más próximo, con mitades hacia arriba.
- Puede mostrarse mora histórica estimada en un crédito cuyas cuotas ya están pagadas. El estado del crédito sigue correspondiendo al flujo existente.

## Límites y siguiente integración

Esta entrega NO crea cargos exigibles, no aplica retroactivamente la política a créditos existentes, no modifica cuotas ni cambia el orden de cobro. No emite tickets de mora ni modifica cortes. El total simulado NO se suma al saldo, al exigible, a ingresos o al dashboard.

Antes de activar el cobro falta:

1. Conservar la política aplicable en cada autorización y definir el inicio de vigencia para créditos existentes, sin reescribir condiciones autorizadas.
2. Registrar cargos semanales con identidad estable y unicidad por cuota/política/semana; separar ajustes y reversos para conservar auditoría.
3. Integrar cuotas exigibles → mora → adelantos, incluyendo quién aporta y a quién se aplica, reintentos idempotentes y control de concurrencia.
4. Desglosar cuota/mora en comprobantes QR, depósitos, cortes y reversos. Ajustar liquidación, garantía y estado pagado para no cerrar un crédito con cargos pendientes.
5. Actualizar contrato y pagaré con las reglas vigentes, eliminando la tasa mensual anterior y sin convertir la garantía en comisión.

## Implementación y verificación

- `WeeklyLateFeeCalculator`: cálculo puro de fechas, bases explícitas y cargos; rechaza fechas inválidas, importes negativos e historia incompleta.
- `LateFeePreview`: reconstrucción de aplicaciones válidas bajo el bloqueo y permisos del estado de cuenta. Sin escrituras ni tareas periódicas.
- `CreditStatement`: entrega el campo adicional `late_fee_preview`; Axios y el store existente conservan el flujo de lectura y cancelación.
- Pruebas: vencimiento, días 1/7/8/15, cambio de año y año bisiesto, redondeo, ejemplo $800→$160, abonos antes/después del cargo, pago completo de una cuota, reversos de pagos y garantías, aislamiento por sucursal y ausencia de cobros generados.
- Prueba de navegador `frontend/apps/admin-web/tests/late-fee-preview-ui.cjs`: API simulada, total grupal/individual, desglose, recarga y móvil; confirma que la pantalla no hace escrituras. Requiere Chrome instalado, servidor temporal y variable `PLAYWRIGHT_MODULE` si el módulo no está en la resolución local.

No se necesitan migraciones adicionales para esta entrega. El módulo requiere las tablas de documentos/garantías de las entregas anteriores. No se migró ni modificó MySQL durante estas pruebas.

Resultado de validación: 114 pruebas y 1,409 aserciones en SQLite en memoria; TypeScript, ESLint y Pint correctos. Capturas de escritorio y móvil inspeccionadas. Servidor de prueba temporal detenido al terminar.
