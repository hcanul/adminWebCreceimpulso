# Contrato y pagaré grupal para revisión

24 de octubre de 2026.

## Uso disponible

En Crédito → Documentos aparecen **Generar Contrato para revisión** y **Generar Pagaré grupal para revisión**. Requieren autorización conservada y primer vencimiento. Se imprimen desde la vista existente del navegador y pueden guardarse como PDF.

Son propuestas documentales, marcadas **NO UTILIZAR PARA FIRMA**. No admiten adjuntos firmados: el servidor rechaza la carga y la pantalla oculta esa opción. Los otros documentos mantienen su flujo habitual. No se consideran documentos firmados ni completan controles de Gestión.

## Contenido y origen

- Razón social confirmada: PROMOTORA PENINSULAR CRECEIMPULSO LEAL S.A. DE C.V.
- Contrato: grupo e integrantes, solicitado frente a aprobado, interés ordinario pactado, total, garantía y neto. Incluye las 16 fechas calculadas desde el primer vencimiento autorizado, formas de entrega y pago, abonos, anticipos y aplicación de garantía.
- Mora: solo se incorpora cuando consta en la autorización, con la política compatible del motor. Las autorizaciones históricas sin esa política no reciben cláusulas semanales nuevas.
- Pagaré: **un solo título grupal**, confirmado por el usuario. Se eliminó la referencia contradictoria a una serie de 16 títulos del formato antiguo. El usuario confirmó capital más interés ordinario pactado y vencimiento en la fecha del último pago de la semana 16. La plantilla 2 conserva importe y vencimiento en la copia emitida; la plantilla 1 permanece disponible sin cambios.
- No se agrega comisión de apertura, interés mensual, capitalización, gastos, impuestos, vencimiento anticipado o declaración de recepción de dinero por interpretación del texto antiguo.
- Los importes de la propuesta no son saldos actuales: el estado de cuenta conserva los movimientos y aplicaciones correspondientes.

Cada emisión conserva HTML, datos, responsable, versión y huella SHA-256. Reemitir la misma combinación recupera el documento existente, aunque cambien los datos de grupo o catálogo después. Las futuras plantillas definitivas deberán utilizar otro tipo o una nueva versión, nunca reemplazar estos registros.

## Datos pendientes para los formatos definitivos

1. Capturar lugar y fecha de suscripción, lugar de pago, domicilio y representante de la empresa y datos contractuales requeridos de las integrantes.
2. Completar el acuerdo sobre remanente de garantía y revisión del texto definitivo. El sistema actual conserva el remanente; no ejecuta devoluciones.
3. Incorporar las firmas al formato definitivo y comprobar el proceso con la oficina.

La consulta del artículo 170 de la Ley General de Títulos y Operaciones de Crédito se utilizó para identificar datos propios del pagaré; no se afirma que estos borradores cumplan todos los requisitos jurídicos aplicables ni que la tasa operativa haya sido validada jurídicamente. Fuente oficial: https://www.diputados.gob.mx/LeyesBiblio/pdf/LGTOC.pdf .

## Verificación y despliegue

Sin migraciones nuevas. Se utilizan las tablas de versiones documentales existentes. Pruebas de generación, importes, calendario, reimpresión inmutable, bloqueo de adjuntos firmados, aislamiento por sucursal, autorizaciones históricas y rechazo de fechas faltantes. TypeScript, ESLint y Pint revisados. Vista HTML de impresión inspeccionada con datos ficticios; impresión física y paginación del PDF definitivo pendientes.
