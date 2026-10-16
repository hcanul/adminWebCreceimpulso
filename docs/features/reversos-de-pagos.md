# Reversos auditados de pagos

Entrega del 15 de octubre de 2026. Regla confirmada por el usuario: reversos completos por Gerente, Gerente General y Super Admin, motivo obligatorio; Caja consulta el resultado. Si el pago pertenece a un corte, el corte original se conserva y el reverso aparece como ajuste separado. No se devuelve dinero automáticamente.

## Operación

1. Recarga la sesión de Gerencia para obtener `credit.reverse`.
2. Abre **Cobros en caja**, el crédito y su historial. Abre **Ver comprobante QR** del pago incorrecto.
3. Captura un motivo de al menos 10 caracteres, revisa el importe y confirma **Revertir pago completo**.
4. El comprobante mostrará **PAGO REVERTIDO**, fecha, responsable y motivo. El mismo enlace QR indica públicamente **Pago revertido**, sin nombres del gerente, motivo, referencias bancarias ni datos personales.
5. Si corresponde, Caja registra el pago correcto como una operación nueva. No se modifica el importe, beneficiaria, medio ni distribución del pago original.

El permiso permite consultar el historial para revisar el pago, pero no concede a Gerencia permiso de cobrar. Caja sigue sin poder revertir. Se mantiene la restricción de sucursal; los perfiles globales conservan su alcance.

## Efecto financiero

- Se crea un registro inmutable en `credit_payment_reversals`, con UUID único, pago original único, sucursal, actor, nombre, fecha local, importe, medio, motivo y snapshot de las aplicaciones y saldos antes/después.
- Se resta exclusivamente lo aplicado por ese pago de cada cuota original. Los registros `credit_payments` y `credit_payment_allocations` permanecen intactos.
- Se conservan las condiciones aprobadas, interés y garantía. No se recalculan intereses ni se ejecutan transferencias, retiros o devoluciones.
- Si el crédito estaba `pagado`, regresa a `entregado` y recupera el saldo pendiente. Los atrasos se calculan nuevamente desde las cuotas originales.
- No se redistribuyen pagos posteriores para cubrir los huecos reabiertos: eso alteraría su aplicación histórica. Los nuevos cobros vuelven a aplicar a las cuotas pendientes más antiguas.
- Una reapertura no cancela ni modifica otros créditos posteriores del cliente. Gerencia debe revisar su situación; el sistema vuelve a considerar el crédito reabierto al evaluar nuevas solicitudes.
- No se admite reverso parcial ni revertir un reverso. Una corrección posterior requiere un pago nuevo, nunca borrar la auditoría.

## Cortes, depósitos y dashboard

- Un pago revertido antes de cerrar deja de estar pendiente para nuevos cortes.
- Si estaba cerrado, el corte mantiene su folio, totales, efectivo contado, diferencia y relación original. En su detalle se agrega **Reversos posteriores · ajustes separados**, con importe, medio, fecha, responsable, motivo y referencia al crédito/pago. Los ajustes tienen paginación y no se mezclan con los totales originales.
- El dashboard suma cobros vigentes recibidos en el día; excluye revertidos. Un reverso de un pago antiguo no es un egreso de efectivo de hoy ni un movimiento automático de Tesorería.
- Se separó la reserva de tickets bancarios activos en `active_bank_payment_references`. La migración copia las referencias existentes antes de retirar la unicidad del campo histórico. El pago conserva para siempre su clave original; al revertir solo se libera la reserva activa. Así se permite volver a capturar el ticket correctamente, manteniendo la protección contra dos pagos activos con la misma sucursal, banco, fecha y folio.

## Protección y reintentos

La operación se ejecuta en una transacción con el bloqueo del cajero que capturó originalmente, grupo, crédito y cuotas. El bloqueo del cajero también se utiliza al cobrar y cerrar: un corte no puede incorporar a medias un pago mientras se revierte.

Se verifica el hash de los saldos revisados. Si cambiaron, se exige actualizar. Se valida que el total de aplicaciones coincida con el pago y que ninguna cuota quede negativa. Un fallo revierte toda la transacción, incluida la auditoría; la reserva bancaria permanece si no se completó el reverso.

Repetir el mismo UUID, contenido, usuario y pago devuelve el resultado registrado, sin restar otra vez. Otro UUID para un pago ya revertido se rechaza. Ante respuesta incierta, la pantalla conserva la petición en memoria, bloquea su modificación y permite reintentar el mismo reverso. Al abandonar/recargar, se debe consultar el estado del comprobante antes de intentar otra operación. El formulario advierte al salir.

## Instalación y pruebas

Migración `backend/database/migrations/2026_10_15_020000_add_payment_reversals.php`, aplicada en MySQL local. Se comprobaron referencias activas, permiso de Gerencia y ausencia de permiso en Caja. No se ejecutaron reversos ni se modificaron pagos reales para la prueba.

Para otro entorno, desplegar la migración y el código conjuntamente, con la escritura de cobros detenida durante la copia de referencias bancarias. La migración conserva pagos y cortes; no tiene borrado automático en `down`.

- 76 pruebas de backend, 886 comprobaciones, en SQLite en memoria: restauración de cuotas, permisos/sucursales, motivo, versión obsoleta, repetición, devolución del ticket a disponibilidad, cortes originales, liquidación reabierta, pagos posteriores intactos y rollback ante un fallo al actualizar cuotas.
- TypeScript y ESLint revisados.
- `tests/payment-reversal-ui.cjs`: Gerencia con motivo y confirmación, respuesta perdida/reintento idéntico, marca después de recargar, Caja sin acción y móvil de 390 px. API simulada; navegador cerrado al finalizar. Se inspeccionó el comprobante móvil.
- No se probó concurrencia con varias conexiones MySQL ni un flujo de devolución bancaria real. Este módulo registra correcciones contables de cobros, no órdenes de devolución.
