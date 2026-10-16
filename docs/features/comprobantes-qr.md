# Comprobantes de pagos recibidos con QR

Implementado el 14 de octubre de 2026. Se emiten exclusivamente a partir de `credit_payments`, después de que el cobro y sus aplicaciones quedaron guardados. Consultar o reimprimir no registra otro pago.

## Uso

1. Caja abre **Cobros en caja**, selecciona el crédito y despliega un registro del historial.
2. **Ver comprobante QR** abre la versión imprimible: folio, crédito, importe, fecha de recepción, medio, cajero, datos de depósito cuando corresponden y detalle de aplicación a cuotas.
3. **Imprimir comprobante** abre el diálogo de impresión del navegador; también permite guardar como PDF si el navegador lo ofrece. No requiere un servicio externo ni una biblioteca de PDF en el servidor.
4. El QR abre una página pública de verificación del servidor. No requiere iniciar sesión y muestra únicamente folio, crédito, importe, fecha y medio del pago. No expone nombres, CURP, referencias bancarias, notas ni otras operaciones del cliente.

El folio es `REC-{folio_del_credito}-{numero_del_pago}` y permanece igual al reimprimir. El folio del crédito ya contiene el código de sucursal. El número del pago se asigna bajo el bloqueo del crédito, no en el navegador.

## Autenticidad y límites

- Se usa una URL firmada por Laravel con HMAC; el navegador recibe la URL, nunca la clave del servidor. La firma protege el identificador y la URL completa. Alterar el identificador, la firma o agregar parámetros invalida la verificación.
- El servidor consulta el pago persistido y presenta sus datos reales. El importe del papel no se toma como fuente de verdad.
- Una firma válida sin pago existente tampoco se acepta. Los enlaces inválidos presentan una respuesta genérica.
- El QR se genera localmente en el navegador con `qrcode`; no se envían los datos del pago a un proveedor externo.
- La consulta pública es de solo lectura, tiene límite de peticiones y encabezados para impedir caché, indexación, referencias hacia terceros y enmarcado. No incluye recursos externos.
- El detalle privado exige sesión, `credit.collect` y acceso al crédito/sucursal. La consulta pública es accesible a quien posea el enlace: compartirlo permite ver sus datos mínimos.
- Copiar un QR no crea otro pago. Un QR no es físicamente imposible de copiar; se debe comparar el resultado del servidor con el papel y verificar el dominio de Crece Impulso. Tampoco sirve para demostrar que todo el crédito está liquidado.
- No se fijó caducidad a estos enlaces para permitir consultar comprobantes históricos. El dominio y la clave de firma deben conservarse o tener un plan de compatibilidad antes de cambiarlos. No regenerar `APP_KEY` sin plan de migración.
- La verificación indica pago registrado o pago revertido. Los [reversos auditados](reversos-de-pagos.md) conservan el enlace y muestran su estado actualizado sin exponer el motivo en público.

## Entorno local y despliegue

Se aplicó correctamente la migración de cobros `2026_10_14_030000_add_credit_payments.php` en MySQL local. Se comprobó el permiso de la cuenta demo de Caja y que no había pagos; no se insertaron cobros para probar esta entrega. Los comprobantes no necesitan una migración adicional.

En desarrollo el QR apunta al servidor local de la API. Una dirección `localhost` no puede abrirse desde otro teléfono. Antes de usarlos con clientes debe desplegarse la API en un dominio HTTPS accesible, configurar correctamente las URLs/proxy y comprobar que los enlaces generados apuntan a ese dominio estable. No entregar comprobantes locales como comprobantes de producción.

El servicio PHP `TicketQrAuthenticator` anterior permanece como utilidad independiente; esta integración utiliza las URLs firmadas nativas de Laravel y no una segunda clave ni el formato experimental de aquella utilidad.

## Validación

- 62 pruebas del backend, 578 comprobaciones en SQLite en memoria. Los nuevos casos validan reimpresión estable, consulta pública, ausencia de información privada, manipulación de firma/identificador/parámetros, registros inexistentes, permisos y sucursales. Se comprueba que consultar un recibo no cambia el cobro ni las cuotas.
- TypeScript, ESLint y formato PHP sin errores.
- Prueba de navegador con API simulada extendida en `frontend/apps/admin-web/tests/credit-collections-ui.cjs`: desde un pago en el historial abre el comprobante, genera la imagen QR, comprueba la vista móvil sin desbordamientos y oculta los controles en modo impresión. Se inspeccionó visualmente la impresión. No se realizó una prueba física de escaneo con teléfono o impresora.

## Siguiente paso

Los [cortes de caja por cajero, día y sucursal](cortes-de-caja.md) ya están implementados, con medios separados, diferencias explicadas y relaciones únicas por cobro. Los comprobantes no crean ni cierran cortes. La aprobación de diferencias y los reversos siguen pendientes.
