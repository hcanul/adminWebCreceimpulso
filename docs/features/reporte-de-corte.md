# Reporte completo de corte de caja

Entrega: 15 de octubre de 2026.

## Uso

1. Abre **Cortes de caja** y selecciona un corte cerrado.
2. Selecciona **Reporte para imprimir**.
3. Revisa sucursal, cajero, fecha de recepción, hora del cierre, totales originales, efectivo contado y diferencia.
4. Consulta todos los cobros separados en efectivo y depósitos, con banco, folio y fecha cuando corresponde. El reporte incluye todas las páginas del corte.
5. Revisa **Reversos posteriores y ajustes separados**. Los pagos afectados se señalan en el detalle original; el importe original no se reemplaza por cero ni se resta de los totales del cierre. Se muestran los importes revertidos por medio, responsable, motivo y fecha.
6. Usa **Imprimir o guardar PDF** y elige impresora o guardar PDF en el navegador. **Actualizar reporte** vuelve a consultar los reversos; se indica la hora de consulta de Cancún.

El reporte no registra movimientos, no aprueba diferencias, no acredita conciliación bancaria ni entrega física a Tesorería. No requiere migración ni nuevos permisos. Se mantienen `cash.view`, el alcance por sucursal y la restricción a los cobros propios salvo facultad `cash.audit`.

## Implementación

- `GET /api/cash/closings/{id}/report` autenticado, con límite de solicitudes y `Cache-Control: private, no-store`.
- Servicio `CashClosings::report`: carga la relación completa e inmutable del corte; verifica cantidad y sumas por medio contra el cierre antes de responder. Si falta detalle, responde conflicto en lugar de generar un reporte incompleto.
- Los reversos se consultan una sola vez; sus totales se calculan sobre esa misma colección en centavos. No se modifican las tablas ni los saldos.
- Ruta protegida `/cortes/:id/imprimir`, servicio sobre Axios central y estado de lectura local. Las solicitudes se cancelan al cambiar de corte o salir. No hay almacenamiento persistente de datos del reporte en el navegador.
- La actualización retira el reporte anterior mientras consulta; ante errores no se puede imprimir un resultado antiguo con el botón. La impresión directa del navegador tampoco muestra el reporte retirado.
- Diseño adaptable, tablas con desplazamiento interno en móvil, encabezados de tabla repetidos en impresión y texto escapado para notas y motivos.

## Validación

- Suite de backend: 79 pruebas, 954 aserciones, en SQLite en memoria. Los nuevos casos cubren un corte de 35 pagos, reversos sin modificar totales originales, reimpresión sin escrituras, permisos de cajero/Gerencia, sucursales ajenas, cuentas inactivas, falta de sesión e integridad del detalle.
- TypeScript, ESLint y Pint revisados.
- `tests/cash-closing-report-ui.cjs`: API simulada, 36 cobros, acceso desde el corte, reversos, texto escapado, impresión/PDF, error sin datos obsoletos, reintento, recarga y móvil de 390 px sin desbordamiento. Inspección visual del PDF de varias páginas.
- No se registraron pagos, cortes ni reversos en MySQL local. No se inició ningún servicio persistente. No se ha realizado una prueba de carga con cortes de gran volumen ni validación de impresoras físicas.
