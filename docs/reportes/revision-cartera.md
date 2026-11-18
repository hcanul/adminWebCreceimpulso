# Cartera vencida y catálogo de reportes

Revisión del código: 18 de noviembre de 2026. No se alteraron registros operativos.

## Resultado

Existe detección de atraso y filtro `overdue`, pero **no existe un traslado administrativo auditado a cartera vencida/recuperación**. Tampoco se encontró un reporte específico que sustituya a la cartera ordinaria para esos créditos.

`backend/app/Domains/Credit/Services/CreditPortfolio.php` toma créditos con `status = entregado`. Calcula cuotas pendientes, exigibles, vencidas y mora, y después aplica filtros `all`, `overdue`, `today` o `current`. El controlador valida esos cuatro valores y el exportador CSV reutiliza el mismo conjunto. Un crédito atrasado permanece en el reporte general. Elegir «Sin atraso» oculta todos los atrasados para esa consulta: no clasifica casos ni equivale al traslado solicitado.

`loan_applications.status` admite pendiente, aprobado, entregado, pagado y cancelado. No debe marcarse como pagado/cancelado ni eliminarse para ocultarlo de cartera: dejaría de representar su estado financiero. El dashboard utiliza el mismo resumen de cartera, por lo que una separación futura debe definirse también allí.

La cartera actual ofrece CSV; no se encontró un botón propio de impresión/PDF en `CreditPortfolioPage.vue`. Los estados de cuenta y cortes sí tienen impresión específica. Es posible que el reporte al que se refiere la operación se imprima desde Excel o desde el sistema antiguo; ese formato debe identificarse antes de sustituirlo.

## Diseño propuesto, todavía no implementado

- Clasificar **cada crédito grupal/ciclo**, no desactivar globalmente al grupo o a todas sus integrantes. La deuda sigue vigente.
- Mantener una clasificación de seguimiento independiente: ordinaria / recuperación por cartera vencida. Conservar usuario, fecha, motivo y transiciones de clasificación.
- Permiso específico para trasladar/reincorporar, sujeto a los roles y sucursales autorizadas. No se han fijado permisos ni un umbral automático sin decisión operativa.
- El reporte ordinario excluiría los créditos trasladados. Un reporte separado de cartera vencida conservaría su saldo, cuotas, mora, antigüedad y datos de seguimiento. Un consolidado distinguiría ambas y permitiría conciliar el total.
- Seguir permitiendo cobros, recibos, estados de cuenta, reversos y aplicaciones autorizadas de garantía. La clasificación no registra pagos, no condona, no congela automáticamente mora y no cambia cortes originales.
- Mantener el bloqueo de renovación por deuda conforme a las reglas existentes. No liberar clientes/grupos por una mera reclasificación.
- Probar filtros y totales en pantalla/CSV/dashboard, alcance por sucursal, pagos parciales, liquidación, reversos y reincorporación.

Antes de implementar deben definirse responsable de autorización, requisito de atraso/plazo y política de reincorporación. No se ejecutó ningún traslado.

## Salidas verificadas y ejemplos

Los ejemplos en `ejemplos-reportes.html` usan exclusivamente datos ficticios y explican el contenido; **no son renderizaciones exactas de plantillas del sistema**. Incluyen cortes temporales distintos explícitos. El CSV adjunto es ilustrativo y no fue generado contra una cartera real.

| Salida | Ubicación en Admin Web | Formato actual |
|---|---|---|
| Cartera y vencimientos | Menú de cartera | Consulta y descarga CSV; filtros de atraso/hoy/sin atraso |
| Estado de cuenta grupal | Crédito → Estado de cuenta → Grupo | Impresión / guardar PDF desde navegador |
| Estado de cuenta individual | Mismo estado → integrante | Impresión / guardar PDF desde navegador |
| Ticket de pago recibido | Caja → comprobante | Impresión y referencia QR verificable |
| Corte de caja completo | Cortes → reporte | Impresión / PDF; efectivo, depósitos y reversos separados |
| Expediente del cliente | Expediente → Documentos | Documento versionado e imprimible |
| Acta de instalación y domicilio | Expediente → Documentos | Documento versionado e imprimible; croquis y visita |
| Acta de autorización | Crédito aprobado → Documentos | Documento versionado e imprimible |
| Calendario autorizado | Crédito aprobado → Documentos | Documento versionado con 16 vencimientos |
| Contrato para revisión | Crédito aprobado → Documentos | Borrador versionado; no utilizar para firma |
| Pagaré grupal para revisión | Crédito aprobado → Documentos | Borrador versionado; no utilizar para firma |
| Hoja de accesos temporales | Impresión de contrato, cuando corresponde | Anexo confidencial; no es listado general de claves recuperables |

Contrato y pagaré conservan explícitamente el estado de revisión en las plantillas actuales. La hoja de accesos es un anexo condicionado al flujo de impresión, no una generación indiscriminada de usuarios al abrir Documentos. No se emitieron credenciales para esta muestra.

Dashboard, listas operativas, cobranza y garantía tienen consultas, pero no se identificaron exportaciones independientes adicionales en las rutas revisadas. Los reversos se reflejan en comprobantes/cortes/estados, no en un reporte exportable independiente verificado. En las apps de clientes existen consultas personal/grupal; la impresión documental permanece en Admin Web.

Fuentes de código: `CreditPortfolio`, `PortfolioCsv`, `CreditPortfolioController`, `IssueDocumentAction`, `DocumentsPage.vue`, `CreditStatementPage.vue`, `PaymentReceiptPage.vue`, `CashClosingReportPage.vue` y vistas de `backend/resources/views/documents/`.
