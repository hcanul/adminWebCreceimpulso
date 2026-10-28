# Mora semanal: cobro, comprobantes y reversos

Actualización: 23 de octubre de 2026.

## Regla vigente

- Mora simple del 10% sobre el saldo pendiente de cada cuota, por semana de atraso iniciada.
- Comienza el día siguiente al vencimiento y después cada siete días: días 1, 8, 15, 22… El día del vencimiento no genera mora.
- Una cuota de $800 genera $80 por semana; dos semanas iniciadas producen $160. No se capitaliza la mora.
- Si un abono deja $300 pendientes, el siguiente cargo es $30. Los cargos anteriores se conservan.
- Caja aplica por integrante beneficiaria: **cuotas exigibles → mora → cuotas futuras**. Quien aporta puede ser otra integrante y queda registrado por separado.
- Solo se retiene la garantía al desembolsar; no se descuenta comisión de apertura.

Esta regla reemplaza las referencias al 10% mensual del contrato antiguo.

## Vigencia y activación

La autorización conserva una copia inmutable de la política. Se incorpora a las nuevas autorizaciones cuando `LATE_FEES_NEW_AUTHORIZATIONS` está habilitada (valor predeterminado: `true`). Cambiar esta configuración no modifica autorizaciones anteriores.

Los créditos autorizados sin esa política conservan su funcionamiento anterior: la mora se presenta como **SIMULACIÓN · NO COBRABLE** y no aumenta su deuda. No se activan cargos retroactivos sobre esos créditos.

Antes de usar esta entrega, ejecutar desde `backend`:

```sh
php artisan migrate
```

La migración `2026_10_18_010000_create_weekly_late_fees.php` crea el registro de cargos y sus aplicaciones de pagos. No se ejecutó sobre MySQL durante esta validación. Si el despliegue utiliza configuración en caché, regenerarla después de cambiar las variables del entorno. La migración evita eliminar automáticamente registros financieros mediante rollback.

## Operación de Caja

Caja y el estado de cuenta separan saldo de cuotas y mora. En créditos con la nueva política, el saldo total y el exigible incluyen los cargos pendientes. Un crédito no queda pagado mientras conserve cuotas o mora pendiente.

Se pueden recibir pagos mixtos o exclusivamente de mora, en efectivo o por depósito. El comprobante conserva un único total recibido y muestra cuánto se aplicó a cuotas y cuánto a mora, con su desglose y QR de verificación. Los reintentos con el mismo identificador no duplican el pago.

Los cortes conservan los totales separados de efectivo y depósitos. El desglose cuota/mora explica el pago, pero no duplica el ingreso.

## Fechas y auditoría

- Zona horaria operativa: America/Cancun. Los cargos se calculan al inicio del día.
- Los pagos registrados el día de un cargo reducen la base de semanas posteriores, no el cargo que ya comenzó.
- En depósitos cuenta la fecha de recepción en Caja, no la fecha impresa en la ficha bancaria.
- El cálculo utiliza centavos enteros y redondeo por cargo al centavo, con mitades hacia arriba.
- Las consultas proyectan los cargos sin escribir en la base. Al cobrar, aplicar garantía o revertir, se guardan los cargos transcurridos bajo bloqueo del crédito; no se necesita una tarea periódica para mostrarlos.
- Cada cuota y fecha de cargo tienen identidad estable y unicidad. También se conservan bases en cero para no recrear deuda histórica tras una corrección posterior.
- Los reversos restauran las aplicaciones a cuotas y mora, conservando comprobante y corte originales. Si el pago ya estaba cortado, aparece un ajuste separado. No implican devolución automática de dinero.
- Los cargos ya registrados no se reescriben al revertir un pago. El saldo restaurado afecta los cargos futuros. Aún no existe un flujo de condonación o ajuste de cargos de mora.

## Garantía

A partir de la semana 16, la aplicación autorizada por Comercial cubre cuotas pendientes, no mora. El remanente se conserva. Cubrir todas las cuotas mediante garantía no cierra el crédito si todavía debe mora.

## Alcance pendiente

- Cartera y Dashboard ya incorporan la mora pendiente autorizada en saldo, exigible y atraso, con desglose separado. Los filtros incluyen créditos con cuotas cubiertas y mora pendiente; no incorporan simulaciones de créditos anteriores.
- Falta actualizar las plantillas definitivas de contrato y pagaré con las condiciones vigentes. La autorización ya presenta la política semanal aplicable.
- No se incluyen condonaciones ni ajustes manuales de cargos.

## Verificación

- Suite del backend: 120 pruebas y 1,576 aserciones con SQLite en memoria.
- Casos nuevos: pago mixto con adelanto, pago exclusivo de mora, depósito, reintento, saldo desactualizado, reverso de pago cortado, conservación de cargos y garantía sin condonar mora.
- TypeScript y revisión de formato del backend correctos.
- Prueba de navegador `frontend/apps/admin-web/tests/late-fee-collection-ui.cjs` con API simulada: Caja, desglose del ticket, QR, estado de cuenta y presentación móvil. Capturas inspeccionadas.
- No sustituye la validación de migración y concurrencia sobre MySQL ni una prueba de impresión física.

### Cierre de indicadores — 23 de octubre de 2026

Cartera y Dashboard comparten los totales con mora autorizada. Se verificaron filtros, paginación, recarga, errores y permisos mediante pruebas de API y navegador con respuestas simuladas. TypeScript, ESLint y Pint correctos; capturas de escritorio y móvil inspeccionadas. Esta etapa no añade migraciones: requiere las tablas de mora de la entrega anterior. No se ejecutaron migraciones ni pruebas de carga en MySQL.
