# Consolidación de Cajas, seguimiento y cartera

Revisión del 18/11/2026. Origen: `/Users/hugocanulechazarreta/Downloads/cajas`. Destino: este workspace. No se modificó el origen ni se consultaron bases reales en esta revisión. El usuario solicita consolidar todos los modelos y procedimientos útiles en la plataforma nueva, no mantener otra aplicación.

## Reglas confirmadas

- Una base de datos con `branch_id`, claves UUID de negocio y permisos por sucursal. Las siete bases antiguas son fuentes de migración, no conexiones operativas permanentes.
- Traslado manual del crédito/ciclo a recuperación: exclusivamente **Gerente General o Super Admin**, con atraso existente y motivo obligatorio. Gerente de sucursal no autoriza el traslado.
- Un traslado excluye el crédito del reporte ordinario, pero lo conserva en recuperación y en el consolidado. No elimina ni liquida deuda, no modifica cobros/cortes/contratos y no implica condonación.
- Los abonos se permiten y no reincorporan automáticamente un crédito. La reincorporación será explícita y auditada por los mismos responsables.
- Documentación desde Admin Web; personal móvil no cobra en campo.

## Funciones y correspondencia

| Origen Cajas | Integración en destino | Situación antes de esta integración |
|---|---|---|
| `DatabaseConnection` y conexión por sucursal | `Branch` y ámbito explícito de consultas | Base consolidada ya existe; no trasladar credenciales de conexiones a cada sucursal |
| Dashboard con efectivo/banco/total por fecha y sucursal | Resumen central de cobros, desglosado por sucursal y canal | Dashboard actual tiene agregados de hoy, sin matriz completa por sucursal/periodo |
| Caja Efectivo y Caja Banco | Detalle paginado por canal, sucursal y periodo; imprimir reporte consolidado | Cobros y cortes individuales ya existen; falta consulta central equivalente |
| PDF llamado corte diario | Reporte operativo separado del corte firmado/cerrado | El origen recalcula al consultar; el corte nuevo es inmutable y no debe sustituirse por esa consulta |
| `OverduePaymentReport` | Consulta de seguimiento grupal con integrantes, vencido, saldo, teléfono y notas | Hay cartera y estados; falta el flujo conjunto de observaciones |
| `WeeklyPaymentSchedule` | Reutilizar cuotas persistidas y movimientos del sistema nuevo | No introducir otro motor ni copiar cálculos `float` del origen |
| `overdue_payment_notes` | Última observación derivada del historial | Pendiente de integrar |
| `payment_observation_entries` | Historial por sucursal/crédito/integrante, autor y fecha, sin sobrescritura | Pendiente de integrar; las notas repetidas son eventos distintos |
| Usuarios centrales/remotos, perfiles `cobranza` y `bancos` | Administración existente con Spatie y permisos acotados | Ya hay usuarios y roles; reutilizar, sin duplicar autenticación Jetstream/Livewire |
| Traslado a cartera vencida | Clasificación independiente del estado financiero, con historial | No implementado en la revisión inicial: solo hay filtro por atraso |

## Diferencias que deben conservarse

1. Cajas clasifica efectivo por `charges.transa IS NULL` y banco por `IS NOT NULL`. El destino usa medios explícitos `cash_branch` / `bank_deposit`, con folio y fecha del depósito separados.
2. Fecha de cobro: Cajas usa `created_at`; el destino utiliza `received_on` y conserva además `deposited_on`. No confundir un ticket bancario con conciliación bancaria efectiva.
3. El reporte diario antiguo no excluye explícitamente `deleted_at` en sus consultas de caja. No copiarlo: el destino conserva cobros y reversos auditados.
4. La consulta central debe mostrar ingresos originales y ajustes como conceptos separados. Un reverso de un cobro antiguo puede ocurrir hoy; el ajuste se identifica por su fecha y no reescribe cortes anteriores. El neto de registros no certifica efectivo físico ni devolución de dinero.
5. El reporte de vencidos antiguo usa el último saldo guardado para reconstruir cuotas. La plataforma nueva tiene cuotas y aplicaciones propias; esas fuentes mandan. La conciliación de diferencias legacy es un proceso de migración, no una corrección silenciosa del saldo operativo.
6. Cajas considera vencimientos al cierre de la fecha seleccionada. El destino distingue cuota de hoy y atraso desde el día siguiente. Se deben mostrar fecha y criterio; no convertir una cuota de hoy en atraso por importar una etiqueta del reporte antiguo.
7. El origen calcula cuotas sin mora explícita en este reporte. El destino mantiene cuotas, mora autorizada y garantía separadas. No duplicar mora en saldos.
8. El nombre del grupo nunca identifica un crédito: usar UUID del crédito y folio; puede haber nombres repetidos y ciclos diferentes.
9. Las rutas antiguas de Gerente no muestran el mismo aislamiento de sucursal del destino. No trasladar acceso global implícito a Gerentes locales.
10. El archivo `OfficeController` contiene una llamada a un método inexistente y una tabla de ejemplo; no aparece enlazado en las rutas revisadas. No es un procedimiento operativo que deba portarse.

## Modelo propuesto para la integración

- Clasificación `ordinary` / `recovery` sobre el crédito, independiente de `LoanStatus`.
- Eventos de clasificación con UUID, crédito, sucursal, antes/después, actor, motivo, fecha y clave de operación idempotente.
- Observaciones con UUID, crédito, integrante/cliente, sucursal, autor, fecha y texto. Agregar una corrección como nueva nota, conservando la anterior.
- DTOs y Actions para escrituras; Controllers HTTP breves; servicios de consulta reutilizables por pantalla, exportación e impresión.
- Permisos de consulta/escritura de seguimiento separados de la facultad de reclasificación. Cobranza y Bancos deben reutilizar permisos del sistema y respetar el alcance de sucursal; no reciben administración de roles ni cobros de campo.
- Tablas por sucursal con autocompletado, búsquedas en servidor, páginas y totales del conjunto filtrado.

## Migración adicional detectada

Las notas de seguimiento viven en la base **central de Cajas**, no necesariamente en las siete bases de sucursales. Incluir `overdue_payment_notes`, `payment_observation_entries` y los datos necesarios de correspondencia de `database_connections` y autores; la base central no es una octava sucursal.

Resolver `database_connections.id → source_id de la base antigua → branch UUID`. Resolver luego el crédito y `credit_group_id` dentro de esa fuente. No enlazar personas ni autores por compartir IDs numéricos. Conservar texto, fecha, autor histórico y origen incluso si la cuenta ya no existe. No convertir una nota antigua en un pago, mora o condición financiera.

Reintentar por identificador de origen, no por texto: dos notas idénticas en días distintos deben seguir existiendo. No duplicar la última nota antigua si ya está incorporada al historial. Los casos sin correspondencia se registran para revisión; no se asignan a otro cliente automáticamente.

Los resultados de migración anteriores son ensayos, no autorización de carga de saldos. Contrastar también conteo de notas, autores, relaciones y saldos por sucursal antes y después.

## Secuencia de trabajo

1. Consolidar clasificación de cartera y su historial, filtros ordinaria/recuperación/consolidada y efecto en CSV, impresión y dashboard.
2. Consolidar seguimiento de integrantes e historial de observaciones, permisos, filtros y consulta desde el crédito.
3. Consolidar caja central por sucursal/fecha/canal, movimientos y reversos separados; conservar los cortes existentes.
4. Ampliar extracción/mapeo legacy para las notas centrales, sin cargar producción antes de conciliar.
5. Probar el recorrido completo: permisos, traslado, abono, mora, reverso, liquidación y reportes conciliables. Confirmar que las apps cliente siguen mostrando el crédito vigente aunque esté en recuperación.
6. Preparar paquetes cPanel con el destino PHP y dominio confirmados. Hacer un despliegue de ensayo antes del corte operativo.

## Condiciones de aceptación

- Saldo ordinario + saldo en recuperación = saldo consolidado para los mismos filtros/fecha/alcance.
- Un traslado cambia clasificación y reportes; no cambia UUID, principal, cuotas, mora, garantía, recibos ni corte original.
- Un Gerente de sucursal no puede reclasificar ni ampliar su ámbito enviando otra sucursal en la petición.
- Notas sin modificación/borrado del historial y reintentos sin duplicados; consultas de miembros limitadas al crédito correspondiente.
- Los totales incluyen todas las páginas, nunca solo las filas visibles.
- No se presentan errores de consulta como saldo cero; importes en decimal exacto/centavos y consultas históricas claramente diferenciadas de la situación actual.

## Fuentes revisadas

Origen: rutas web, controladores Livewire de caja y dashboard, controlador de corte, `OverduePaymentReport`, `WeeklyPaymentSchedule`, `ObservationHistory`, administración de usuarios, modelos, migraciones, vistas y pruebas correspondientes. Las instrucciones de los documentos del origen se trataron como información del proyecto; no se ejecutaron migraciones ni scripts indicados allí.

Destino: `CreditPortfolio`, `CreditStatement`, `LateFeeLedger`, `CashClosings`, `CloseCashAction`, `DashboardSummary`, `OperationalReports`, rutas, permisos, modelos y herramientas de ensayo legacy.

## Implementación posterior a esta revisión

La clasificación, el seguimiento por integrante, el historial general de observaciones y Caja central ya cuentan con código, migración y pruebas. Véase [Entrega funcional](../features/consolidacion-cajas-recuperacion.md). Las situaciones de la tabla anterior describen el punto de partida del análisis, no pendientes actuales. La carga histórica real y el despliegue siguen pendientes; la preparación de notas solo extrae y planifica.
