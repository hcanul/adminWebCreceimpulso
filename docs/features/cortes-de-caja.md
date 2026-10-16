# Cortes de caja

Entrega del 15 de octubre de 2026. El usuario confirmó el cierre por cajero, día y sucursal, con efectivo y depósitos separados, consulta de Gerencia y explicación de diferencias sin modificar los pagos.

## Uso

1. Recarga la sesión para actualizar permisos. Abre **Cortes de caja** con la cuenta de Caja.
2. Selecciona sucursal y día de **recepción del cobro**. **Preparar mi corte** consulta todos tus cobros pendientes de corte en ese ámbito.
3. Revisa efectivo y depósitos por separado, con el detalle de crédito, número de pago, importe y, para depósitos, banco, folio del ticket y fecha del depósito. El detalle tiene páginas de 30 registros; los totales y el cierre incluyen todas las páginas.
4. Captura únicamente el efectivo contado correspondiente a esos cobros. Se excluyen fondo inicial, garantías, desembolsos, retiros y otros movimientos de tesorería. No es un arqueo de todo el dinero físico de la sucursal.
5. La diferencia es `efectivo contado - efectivo registrado`: positiva indica sobrante y negativa faltante. Una diferencia distinta de cero exige explicación. Para un corte de solo depósitos se captura cero de efectivo, si así corresponde.
6. Confirma **Cerrar mi corte**. El servidor asigna un folio `COR-Sxxx-000001`, conserva totales, cajero, sucursal, fecha, observaciones y cada cobro incluido.
7. Consulta el corte en el historial. Gerencia puede consultar los cortes de sus cajeros, pero no cerrarlos por ellos ni cambiar el conteo. El detalle muestra la hora de cierre en America/Cancun.

Un corte no congela toda la jornada. Si llegan cobros nuevos, se puede preparar otro corte de ese mismo día, exclusivamente con los que aún no estén incluidos. El corte anterior permanece intacto. Un ticket bancario de un día anterior pertenece al corte del día en que Caja registró su recepción, conservando además su fecha de depósito.

La vista conserva lo confirmado en el servidor tras recargar. Los formularios sin cerrar no se persisten en el navegador; hay aviso al salir. Ante un fallo de red con resultado incierto, se conserva en memoria la misma operación para reintentar sin duplicar. Si se recarga o abandona esa pantalla, consultar los cortes registrados antes de iniciar otro cierre.

## Permisos

- `cash.view`: consulta propia, limitada a la sucursal asignada.
- `cash.close`: preparación y cierre de los cobros propios; no se acepta un cajero indicado por el navegador.
- `cash.audit`: consulta de los cortes de otros cajeros dentro de las sucursales permitidas; no significa aprobación de diferencias ni autorización para cerrar por terceros.
- Caja recibe consulta y cierre. Gerente recibe consulta y auditoría. Super Admin y Gerente General tienen los tres permisos y alcance global; aun así, el cierre siempre se hace sobre los cobros propios.
- Promotor, Gestión y Coordinación Comercial no reciben estos permisos automáticamente.
- Los permisos de caja no conceden acceso a captura de expedientes ni exigen `intake.access`. La administración de roles exige `cash.view` para conceder cierre/auditoría.

## Integridad

Se agregaron `cash_closings` y `cash_closing_payments`. La relación con un cobro es única: no puede aparecer en dos cortes. Las claves de negocio son UUID; el folio secuencial se genera por sucursal dentro de la transacción.

El servidor recalcula los totales a partir de los pagos pendientes y no acepta importes esperados, listas de pagos ni un cajero enviados por el cliente. No admite cortes vacíos ni fechas futuras. Un hash de los cobros revisados detecta que llegó un pago o que otro cierre cambió la selección; se solicita revisar otra vez antes de cerrar.

El registro de cobros y el cierre comparten un bloqueo del cajero. El corte toma los pagos y sus relaciones en una transacción: una inserción fallida revierte también el corte y su folio. Una petición repetida con el mismo UUID y contenido devuelve el corte existente; cambiar el contenido de ese UUID genera conflicto.

No se modifican los pagos, sus aplicaciones, saldos, garantías ni comprobantes al cerrar. No hay endpoints para editar o borrar cortes. Las diferencias quedan registradas, no se convierten en ajustes automáticos ni en cobros a clientes.

## Instalación y comprobaciones

Migración aplicada en MySQL local: `backend/database/migrations/2026_10_14_040000_add_cash_closings.php`. Se verificaron las tablas, el permiso de cierre del demo de Caja, el permiso de consulta de Gerencia y que no se alteraron los cobros. No se crearon cortes en tu base de desarrollo.

- La suite de backend pasó sus 67 pruebas. Los cinco casos nuevos cubren totales separados, diferencias y explicación, reintentos, saldos de vista previa cambiados, datos manipulados, permisos, aislamiento por cajero/sucursal, cortes adicionales y reversión completa si falla una relación. Se verificó además la hora local del cierre.
- TypeScript y ESLint revisados, y formato PHP validado.
- `frontend/apps/admin-web/tests/cash-closings-ui.cjs`: prueba con API simulada de Caja, explicación obligatoria, un cierre cuya respuesta se pierde y se reintenta con idéntico contenido, persistencia tras recarga, Gerencia de consulta y móvil de 390 px sin desbordamiento. El navegador se cierra al finalizar.
- No se ejecutó una prueba concurrente de varias conexiones MySQL. La validación de concurrencia de esta entrega es mediante bloqueos, restricción única y pruebas de versiones/reintentos; requiere prueba de carga antes de producción.

## Alcance siguiente

Los [reversos auditados de pagos](reversos-de-pagos.md) ya están disponibles y se muestran como ajustes posteriores sin modificar los cortes. Falta un flujo de revisión/aprobación de diferencias por Gerencia y tesorería completa (fondos, movimientos, egresos y arqueos). Este módulo cierra ingresos de cobranza; no afirma que un depósito fue conciliado con el banco ni que hubo entrega física del dinero a Tesorería.

## Reporte para imprimir

Desde un corte cerrado está disponible **Reporte para imprimir → Imprimir o guardar PDF**. Incluye todos los cobros y los reversos posteriores en secciones separadas, conservando los totales originales. Consulta [Reporte completo de corte](reporte-de-corte.md).
