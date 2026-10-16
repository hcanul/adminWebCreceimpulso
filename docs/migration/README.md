# Consolidación del legacy y comprobantes de clientes

Fecha: 8 de octubre de 2026. Origen de código: `/Users/hugocanulechazarreta/Documents/LaravelProjects/sistema_creceimpulso`. Destino: este workspace. Alcance: análisis de código, metodología y primera implementación de validación en seco. No se accedió a bases operativas ni se importaron datos.

## Decisiones confirmadas

Sucursales: Carrillo, Tulum, Morelos, Playa 1, Playa 2, Cancún 1 y Cancún 2. El registro `branches.registry.json` conserva los siete nombres. La asociación de cada base y cada city_id a una sucursal/UUID está pendiente: no se rellenó con suposiciones.

App cliente: únicamente su crédito activo, calendario/vencimientos, atrasos y comprobantes virtuales de pagos recibidos. El QR no es una orden de pago, ni confirma un movimiento que solo existe en la cola offline.

## Qué se implementó

- `backend/app/Domains/LegacyMigration/DTOs/SourceSnapshot.php`: contrato tipado de exportaciones locales.
- `backend/app/Domains/LegacyMigration/Actions/BuildConsolidationPlanAction.php`: planificación sin escrituras. Clave de origen `(source_id, tabla, id)` y UUID candidato determinista. No usa IDs locales como identificadores globales.
- Detección de tablas faltantes, IDs repetidos, referencias huérfanas dentro de cada origen, sucursales sin mapa, CURP con formato inválido/repetidas, participaciones repetidas, discrepancia sucursal crédito/grupo, detalle de pago vinculado a otra participación/crédito y diferencia cabecera/detalle de cobro.
- Montos de intercambio como texto decimal exacto. No acepta floats JSON ni redondea automáticamente importes de precisión inesperada.
- `backend/tools/legacy-preflight.php`: CLI que carga únicamente autoload de Composer. No inicia Laravel, no lee .env y no abre conexiones a bases. Solo escribe el informe en stdout.
- `backend/app/Domains/Tickets/Services/TicketQrAuthenticator.php`: autenticador HMAC-SHA256 para una referencia opaca de comprobante, con versión, identificador de clave, propósito receipt y vigencia. Rechaza alteración, clave incorrecta, vencimiento y propósito distinto. No expone datos personales ni importes dentro del QR.
- Trece pruebas unitarias y 29 aserciones pasaron, sin servidor ni base de datos. Ejecución CLI de la muestra ficticia satisfactoria.
- Inventario de los 72 campos preservado en `legacy-application-fields.json`.

Esto NO es aún un importador completo ni una pantalla de comprobantes. El resultado mantiene `ready_for_import: false` incluso sin incidencias: los controles estructurales no sustituyen la conciliación financiera, la validación del esquema real ni la aprobación del corte.

## Hallazgos del sistema antiguo y consecuencias

### Identificadores locales

Todas las tablas principales usan IDs numéricos locales. El cliente 25 de Carrillo y el cliente 25 de Tulum no son la misma persona por compartir ese número. Todas las búsquedas de relaciones durante extracción/transformación deben estar limitadas por source_id.

Los UUID candidatos del preflight identifican filas de origen. El UUID de la sucursal se define en el mapa aprobado; no se toma del candidato de citys. Una persona puede aparecer en varias solicitudes o bases: candidato de demanda, cliente definitivo e instantánea de solicitud no son necesariamente la misma entidad. La tabla definitiva de equivalencias debe conservar todos esos destinos.

### Montos solicitados que se sobrescriben

En `CreditController::ejecutar`, el total aprobado se asigna a credits.totReq. Este campo deja de ser evidencia fiable de lo originalmente solicitado. `credit_groups.preNvo`, `crePreApro` y `creAprob` conservan conceptos distintos. Reconstruir el monto original requiere validar esos campos y los documentos de cada caso. Si no es recuperable, marcarlo como desconocido con procedencia; nunca inventarlo copiando el aprobado.

### Significado de la tasa

`CreditController::printed`, `controlPagos` y `ChargeController::store` calculan cuotas a partir de `(principal / 1000) * rates.rate` y total de 16 cuotas. Por lo observado, ese valor actúa como importe de cuota por cada mil de principal en esos caminos, no como factor porcentual directo. No copiar rates.rate a rate_factor del nuevo esquema. Confirmar catálogos y contratos, conservar la tasa original y documentar cualquier transformación.

### Saldo individual y crédito incorrecto

`ChargeController::store` y `show` buscan en determinados cálculos el último BillingDetail por demand_id sin limitar el crédito. Puede mezclar operaciones históricas. El nuevo saldo debe derivarse de movimientos aplicados a la participación concreta. La migración debe contrastar saldos almacenados con los detalles del mismo crédito, sin corregir silenciosamente el origen.

### Cobros y posiciones en arrays

El registro de pagos asocia importes y participantes por posición en arrays; cabecera y detalles se guardan por separado sin una transacción que cubra la operación completa. En el nuevo sistema: comandos identificados por UUID de participación, transacciones, idempotencia y verificación de sumas. El preflight ya detecta una parte de las inconsistencias resultantes, pero no prueba que nunca existieran.

### Vencimientos y atrasos

`EstadocuentaController::hacer` construye fechas a partir de pacted_date más semanas en función de los cobros existentes. Si faltan pagos, esa lista no representa todas las obligaciones vencidas. La app cliente necesita un calendario independiente de 16 vencimientos y aplicación de pagos a cuotas. Definir documentalmente qué representa pacted_date y cuándo vence la primera cuota. El importe atrasado y los días de atraso requieren una política de fecha de corte, zona horaria y aplicación de pagos aprobada.

### Bajas, garantía y comprobantes

El legacy elimina cobros y detalles con lógica de delete; hay saldos integer y float según la tabla. Recuperar también deleted_at para conciliar anulaciones e historial. El 10 % aparece en cálculos de base/garantía y liquidación; no tratarlo como pago ni descontarlo automáticamente en la migración. Los tickets encontrados se generan como PDF; no se encontró un esquema criptográfico de verificación en el código revisado.

## Mapa preliminar de entidades

| Legacy | Destino / tratamiento |
|---|---|
| citys | branches mediante mapa explícito por base |
| users / promoters / coordinators | Identidades, cuentas y asignaciones; conciliación manual de roles y personas, no equivalencia automática por ID |
| demands | Client + instantánea completa de solicitud, conservando los 72 campos y procedencia |
| teams | Groups/Teams con sucursal y asignación |
| rates | Catálogo normalizado y condiciones congeladas de cada operación; conversión financiera revisada |
| credits | Solicitud/operación histórica con ciclo, estados, fechas y montos separados |
| credit_groups | LoanParticipations por cliente/operación; cargos 1/2 del legacy conservados y traducidos tras revisión |
| paydays | Evidencia de fechas para reconstrucción validada del calendario |
| charges | Cabecera de cobro original con folio de origen; no recalcular fecha de emisión |
| billing_details | Aplicaciones individuales del cobro a participaciones |
| latepayments | Movimientos de mora separados; verificar cobrado vs adeudado antes de clasificar |
| Tablas restantes | Inventariar y clasificar; personal/nómina no se descartan ni se importan tácitamente al dominio de créditos |

## Procedimiento por etapas

1. **Inventario de siete orígenes:** identificadores permanentes de fuente, sucursales incluidas, esquema real, versión de MySQL, tablas y cantidad de filas. Comparar SHOW CREATE TABLE de todas las bases; compartir estructura en el código no garantiza que producción coincida.
2. **Copias consistentes:** respaldos de solo lectura con fecha de corte, zona horaria, checksums y restauración ensayada. Guardar archivos/documentos asociados. No usar cuentas de escritura para extracción.
3. **Staging aislado:** conservar cada fila y valores crudos, campos desconocidos, bajas y esquema. Proteger las exportaciones; no añadir datos personales al repositorio. La muestra incluida es ficticia.
4. **Mapa de sucursales e identidades:** aprobar `(source_id, legacy_city_id) → branch_uuid`. Conservar tabla `legacy_record_links` con clave única `(source_id, entity, legacy_id, target_kind)`, destino UUID, lote y checksum. Esa tabla hace reejecutables las importaciones.
5. **Resolución de duplicados:** CURP es señal de revisión, no orden de fusión. Verificar nombres, documentos e historia. Decidir identidad global con afiliaciones a sucursales o política explícita de pertenencia; el Client actual con CURP único y un solo branch_id necesita resolver este caso antes de importar. No escoger una sucursal arbitraria.
6. **Transformación revisada:** montar relaciones por equivalencias, separar solicitud/aprobación, preservar fórmulas y condiciones históricas. No recalcular contratos antiguos con un catálogo nuevo ni importar tokens/contraseñas/roles administrativos sin una estrategia de acceso acordada.
7. **Conciliación:** por fuente/sucursal/crédito/participante/día contrastar conteos, solicitado recuperable, aprobado, obligación contractual, cobros, aplicaciones, mora, anulaciones y saldo. Diferencias deben producir incidencias; no ajustes automáticos para forzar igualdad. Congelar tasas/fechas y reconstruir 16 cuotas solo cuando existan datos suficientes.
8. **Ensayo de importación:** ambiente independiente, por dominios y con transacciones; segunda ejecución no duplica nada. Probar rollback por lote sin destruir otros orígenes ni operaciones nuevas. Capturar casos de aceptación comparados con documentos reales.
9. **Corte por sucursal:** ventana de escritura detenida o estrategia de delta explícita. También conciliar colas offline pendientes. Migrar las siete sucursales por etapas después del piloto, con responsable y evidencias.
10. **Producción:** aceptación operativa de saldos y acceso, respaldos, seguimiento y plan de reversión. Una reversión después de recibir pagos nuevos exige reconciliar esos movimientos; no basta restaurar un respaldo viejo.

## Ejecutar el preflight actual

Con PHP compatible y vendor ya instalado, desde backend:

```sh
php tools/legacy-preflight.php tools/fixtures/legacy-demo.json
```

Para una copia JSON preparada fuera del repositorio:

```sh
php tools/legacy-preflight.php /ruta/privada/snapshot.json > /ruta/privada/reporte.json
```

Formato de entrada: sources es una lista; cada fuente contiene source_id estable, branches con mapa de city_id antiguo a UUID de sucursal, y tables con listas de filas completas de exportación. Las tablas esperadas están en BuildConsolidationPlanAction::TABLES. Incluso las tablas vacías deben declararse explícitamente. Exportar dinero como cadenas decimales, por ejemplo `"100.25"`, e IDs grandes como cadenas. No convertir ni redondear datos antiguos para ocultar alertas. La herramienta no procesa archivos SQL.

Salidas: 0 para controles estructurales sin incidencias; 2 para incidencias/revisión; 1 para formato/archivo inválido. No hay un modo de escritura ni flag para forzar importación. Límite piloto de 50 MiB, carga en memoria; el importador completo deberá procesar lotes/streams y checkpoints. Las referencias internas se verifican solo para las relaciones declaradas; no incluye usuarios/coordinadores, estados de comité, todas las fechas ni todos los campos del formulario. El informe de ejemplo puede pasar porque solo contiene una sucursal ficticia y tablas vacías: no demuestra compatibilidad financiera.

Pruebas sin arrancar Laravel:

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit/LegacyConsolidationTest.php tests/Unit/TicketQrAuthenticatorTest.php
```

## App de clientes: contrato de acceso

La cuenta autenticada se vincula explícitamente al cliente verificado. La CURP o un UUID enviados por el navegador no son autorización para consultar a otra persona.

Lecturas previstas: `GET /api/v1/client/active-loan`, vencimientos y atrasos de ese crédito, listado de comprobantes propios y detalle QR de un comprobante. Son contratos propuestos, aún no registrados como rutas. El endpoint resuelve propietario en el servidor; no admite elegir arbitrariamente client_id. Si no existe crédito activo devuelve estado vacío; si existen varios incompatibles, reporta incidencia para conciliación, no elige el mayor ID.

Respuesta limitada: identificador/folio del crédito, grupo identificable, monto propio aprobado, saldo propio confirmado, próximas cuotas, cuotas vencidas/días e importe atrasado con fecha de corte, y comprobantes propios. No retornar expedientes, CURP, domicilios o pagos individuales de otras integrantes. Un resumen grupal adicional queda fuera del alcance limitado hasta autorización expresa.

No hay acciones de aprobar, editar crédito, generar cobros ni consultar historia general. Los comprobantes históricos se conservan en servidor aunque la app limite su navegación al crédito activo; acceso posterior debe definirse sin borrar evidencia.

## QR de comprobante: autenticidad y estado

No existe un QR absolutamente incopiable. El objetivo es impedir alteraciones aceptadas por el sistema y permitir comprobar la existencia y estado de un pago real.

- Emitir comprobante después de confirmar la transacción del pago. Un pago capturado offline sigue pendiente hasta aceptación; no mostrarlo como liquidado/confirmado por el QR.
- Registro inmutable de comprobante enlazado a payment, branch, participación/cliente, importe, fecha de recepción y folio. Cambios financieros mediante reverso con auditoría, no edición del recibo original.
- El contenido QR contiene una referencia opaca autenticada. El servicio implementado usa HMAC-SHA256 y comparación constante; la clave dedicada y aleatoria vive solo en el servidor, nunca en móvil, frontend, repositorio o exportación.
- El verificador final debe resolver ticket_id, comprobar existencia, propietario/permisos del consultante y estado vigente/anulado. La validez criptográfica NO demuestra que el pago exista ni que continúe vigente.
- La verificación es de lectura y puede repetirse. Escanear un comprobante no crea otro pago y no “consume” el recibo. Una copia de un QR legítimo seguirá apuntando al mismo comprobante, no a otro movimiento.
- La vigencia del token es distinta del estado del comprobante: una referencia vencida puede reemitirse online para el mismo documento legítimo. Definir duración, rotación de claves, retención y formato de URL antes de integrar.
- No incluir toda la información personal en el QR. Verificación externa, si se habilita, debe mostrar datos mínimos; el detalle requiere identidad autorizada. Evitar registrar tokens completos en logs.
- Sin conectividad no puede garantizarse conocimiento de una anulación reciente. HMAC de este incremento se verifica solo en servidor; no distribuir el secreto para escaneo offline. Si se requiere esa capacidad habrá que diseñar firmas asimétricas y política de vigencia/estado aparte.

El autenticador está probado, pero todavía faltan tabla de comprobantes, Actions de emisión/verificación autorizada, persistencia, endpoints, visualización QR y pruebas end-to-end. No se expuso una API pública provisional que permita aparentar pagos válidos.

Referencias: [OWASP — autorización de transacciones](https://cheatsheetseries.owasp.org/cheatsheets/Transaction_Authorization_Cheat_Sheet.html), [PHP HMAC](https://www.php.net/manual/en/function.hash-hmac.php), [comparación constante](https://www.php.net/manual/en/function.hash-equals.php).

## Información necesaria para el siguiente incremento

Exportaciones anonimizadas iniciales y esquemas reales de las siete bases, mapa base/citys/sucursal, fechas/zona horaria y ejemplos conciliados de crédito activo, liquidado, atrasado, pago parcial y anulado. No entregar contraseñas en el chat. Las copias completas para migración se manejarán por una ruta local protegida cuando se acuerde el procedimiento.
