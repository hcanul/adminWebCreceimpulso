# Cobros en caja

Implementado el 14 de octubre de 2026. Complementa la autorización, el desembolso y el calendario de 16 semanas.

## Uso

1. Inicia sesión con Caja y abre **Cobros en caja**. Si la sesión estaba abierta antes de la migración, recarga para obtener sus nuevos permisos.
2. Busca un crédito entregado por folio o nombre de grupo. Los créditos aprobados pero no entregados todavía no se pueden cobrar.
3. Consulta saldo total, exigible al día de hoy, saldo vencido y días de atraso por integrante. Las fechas se evalúan en America/Cancun. Una cuota vence en la fecha del calendario y se considera atrasada desde el día siguiente.
4. Captura el total recibido. La modalidad corresponde a la autorización: efectivo en sucursal o depósito bancario. No se cambia durante el cobro.
5. Para depósito registra banco, folio del ticket y fecha del depósito. Caja coteja el ticket; no existe conciliación automática con el banco. La fecha de depósito no puede ser futura ni anterior a la entrega.
6. Distribuye exactamente el total entre las integrantes. Por cada aportación indica quién aporta y a quién se aplica. Los dos campos permiten buscar por nombre o folio dentro de este crédito. Puedes agregar varias aportaciones para registrar cobertura solidaria.
7. Revisa la distribución, marca la confirmación y acepta el aviso. El registro conserva el usuario, sucursal, fecha de recepción del sistema, medio de pago y aplicación exacta a cada cuota.
8. El historial se consulta desde la misma pantalla y permanece después de recargar. Al abrir un cobro muestra el origen de cada aportación y las cuotas que cubrió. Tiene paginación de 20 registros.

Los datos capturados sin confirmar no se guardan como pagos ni se persisten en el navegador. La pantalla avisa antes de salir. Ante una respuesta de red incierta conserva en memoria el mismo UUID y contenido, bloquea la edición y permite reintentar esa operación. Si se cierra o recarga durante un resultado incierto, revisar el historial antes de capturar de nuevo; no reconstruir el pago como una operación nueva sin comprobar su registro.

## Regla confirmada por el usuario

Aplicar primero a la cuota pendiente más antigua **de la integrante beneficiaria** y usar el excedente para adelantar las siguientes cuotas, sin reducir el interés pactado. Otra integrante puede cubrir su faltante: se conserva quién aporta y a quién se aplica. No se transfiere una deuda ni se genera automáticamente una cuenta por cobrar entre integrantes.

- Se aceptan abonos menores a la cuota. El saldo no cubierto permanece pendiente.
- No se permite que una aportación exceda el saldo de su beneficiaria, ni distribuir un total distinto del recibido.
- Una aportación no cubre automáticamente saldos de otras integrantes: Caja debe indicar esa distribución.
- La liquidación conserva todo el interés contratado. Cuando las 48 cuotas de un crédito de tres integrantes están cubiertas (16 por persona), el crédito pasa a `pagado`. Una integrante puede quedar con saldo cero antes que las demás.
- La garantía retenida no se aplica a cuotas ni se devuelve en este módulo.
- No se calculan penalizaciones, descuentos ni un reparto contable entre capital e interés. Se aplica a las cuotas pactadas completas o parcialmente.
- El atraso se calcula del calendario y los pagos, sin depender de un proceso diario ni sobrescribir estados manuales del expediente del cliente.

## Datos y protección

`credit_payments` registra UUID, número secuencial dentro del crédito, crédito, sucursal, cajero, fecha de recepción, medio, importe, datos bancarios, notas y hash de la petición. El número interno garantiza ordenar cronológicamente cobros registrados en el mismo segundo; no es aún un folio de comprobante oficial.

`credit_payment_allocations` relaciona cada cobro con las cuotas y con la integrante que aportó. Se actualiza `loan_installments.amount_paid` dentro de la misma transacción. Si falla cualquier parte, se revierte el cobro completo.

La API opera con `credit.collect`, concedido inicialmente a Caja, Gerente General y Super Admin; no a Promotor, Gerente, Gestión ni Coordinación Comercial. El administrador puede configurar permisos. Se mantiene el aislamiento por sucursal y Caja no obtiene acceso a los expedientes de captura. La consulta del historial bancario también requiere este permiso.

Los importes se calculan en centavos enteros. Hay bloqueos por grupo/crédito/cuotas y un hash del saldo para detectar cambios concurrentes. Un UUID repetido con el mismo usuario, crédito y contenido devuelve el resultado existente, incluso si ya liquidó el crédito. Cambiar el contenido del mismo UUID genera conflicto. El UUID no se devuelve como prueba de autenticidad de un recibo.

Se rechaza repetir un ticket con la misma sucursal, banco, fecha de depósito y folio, ignorando mayúsculas y espacios exteriores. Los nombres de banco son texto libre; esta protección no sustituye conciliación bancaria y las variantes del nombre requieren un catálogo posterior. Un ticket de esta primera versión se aplica a un solo crédito; no se fragmenta entre varios créditos.

No existen endpoints para editar o borrar pagos. Las correcciones se registran con [reversos auditados completos](reversos-de-pagos.md) por Gerencia; el pago original se conserva.

## Instalación local

Nueva migración: `backend/database/migrations/2026_10_14_030000_add_credit_payments.php`.

La migración se aplicó en MySQL local al continuar con los comprobantes QR. Se verificaron sus tablas y el permiso de Caja; no se insertaron pagos. Para instalar en otro entorno de desarrollo, desde la carpeta `backend`:

```sh
php artisan migrate --path=database/migrations/2026_10_14_030000_add_credit_payments.php
```

La migración agrega tablas y permisos, no reinicia el demo ni modifica los créditos que ya hayas procesado. Para probar usa la cuenta `demo.caja@example.test` y un crédito que hayas autorizado y entregado.

## Validación

- 60 pruebas del backend y 538 comprobaciones, con SQLite en memoria. Casos nuevos: abono parcial, aporte solidario, adelanto, repetición exacta, saldo desactualizado, ticket duplicado, sobrepago, distribución inconsistente, permisos, sucursal, liquidación, atraso calculado y reversión de transacción ante fallo al insertar una aplicación.
- TypeScript y ESLint sin errores; formato PHP revisado.
- Prueba de navegador reproducible: `frontend/apps/admin-web/tests/credit-collections-ui.cjs`. Verifica Caja sin acceso a captura, búsqueda de integrantes, aporte solidario, depósito, confirmación, reintento con contenido idéntico, historial tras recarga y vista móvil de 390 px sin desbordamiento. Usa API simulada y cierra el navegador al finalizar; no inserta pagos en MySQL.
- No se ejecutó una prueba de concurrencia con varias conexiones MySQL ni una conciliación bancaria real.

## Siguiente entrega

Los [comprobantes imprimibles y verificables por QR](comprobantes-qr.md) ya están integrados con el historial. Los [cortes de caja](cortes-de-caja.md) ya permiten cerrar cobros por cajero, día y sucursal, con efectivo y depósitos separados. Todavía no se controlan el arqueo completo de la sucursal, los saldos físicos ni los reversos.
