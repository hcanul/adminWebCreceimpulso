# Comprobantes: carta en dos partes y ticket

18/11/2026. Configuración disponible en **Cobros → Ver comprobante QR**. No requiere migración de base de datos. Solo cambia la presentación de impresión de Admin Web; los importes, recibos, QR y permisos siguen procediendo del backend autorizado.

## Uso

1. Abrir el comprobante de un pago registrado en Caja.
2. En **Formato del comprobante**, elegir **Hoja carta · dos copias**, **Ticket · 80 mm** o **Ticket · 58 mm**. Se puede escribir para filtrar las opciones. Carta es el formato inicial y la elección se conserva en ese navegador/dispositivo, sin guardar información de clientes.
3. Abrir **Vista previa de impresión** para revisar. Carta distribuye el mismo comprobante en la mitad superior para cliente y la inferior para sucursal, con línea de corte y QR en ambas copias.
4. Pulsar **Imprimir comprobante**. Se abre el diálogo del navegador/sistema para elegir la impresora, cantidad de copias o Guardar PDF. La aplicación web no enumera impresoras ni fija silenciosamente una impresora: esa selección pertenece al diálogo de impresión. La versión Electron utiliza también el diálogo del sistema; no se implementa impresión silenciosa.
5. Para carta, elegir **Carta / Letter**, orientación vertical, escala 100% y desactivar encabezados/pies añadidos por el navegador. Para ticket, elegir la impresora térmica y el papel del ancho correspondiente en su controlador. Los márgenes físicos y opciones admitidas dependen de la impresora/controlador.

## Detalle y continuidad

Se conserva importe, desglose de cuotas/mora, crédito, fecha, cajero, datos del depósito cuando existen, aporte/aplicación por integrante y QR. Los comprobantes revertidos mantienen su advertencia. Las cadenas procedentes del recibo se escapan antes de producir HTML.

Si hay más de seis aplicaciones, carta genera hojas de continuación con ambas copias en cada hoja. Cada parte identifica su número; el importe total y QR se repiten porque siguen correspondiendo al mismo pago. No sumar los totales de las partes como si fueran cobros adicionales.

No se recorta deliberadamente texto para forzarlo dentro de media carta. Si los datos excepcionalmente largos exceden el espacio, la interfaz bloquea esa impresión y pide elegir ticket o revisar el formato de ese caso. La visualización detallada del comprobante permanece disponible.

Los tickets utilizan una sola copia con el detalle completo; el largo de página se ajusta al contenido dentro de los límites del motor de impresión. Las tiras largas pueden requerir más de una página. El corte automático del rollo y selección real del papel dependen del controlador y no se configuran desde la web.

La impresión se realiza en un documento aislado: no imprime controles de reverso ni la configuración. La vista del comprobante original se mantiene para consulta. Usar el botón de impresión del comprobante para obtener el formato elegido.

## Integridad

Reimprimir nunca registra otro cobro, no aplica saldo ni modifica cortes. La elección de formato no concede permisos. Ambas copias llevan el mismo QR del comprobante, y su consulta muestra el estado vigente, incluido un reverso posterior. No se persisten recibos, contraseñas ni datos del cliente como preferencia local.

## Verificación y límites

Compilación SPA y TypeScript/ESLint. Prueba de UI con API simulada: carta con dos copias, tickets de 80/58 mm, persistencia del formato tras recarga, continuación de 20 aplicaciones sin pérdida, comprobante revertido, QR y llamada al diálogo sin escrituras API. PDFs de muestra con datos ficticios revisados visualmente. Los ejemplos no corresponden a pagos reales ni sus QR validan operaciones de producción.

Falta probar salida en la impresora física del usuario, con su controlador, escala, márgenes y papel. Las compilaciones macOS/Windows ya entregadas son anteriores a este cambio y requieren recompilar antes de distribuirlo en escritorio.

Referencia técnica: [Window.print abre el diálogo de impresión](https://developer.mozilla.org/en-US/docs/Web/API/Window/print), [CSS @page size](https://developer.mozilla.org/en-US/docs/Web/CSS/@page/size).
