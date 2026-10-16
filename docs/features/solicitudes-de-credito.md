# Solicitud de crédito grupal

Implementación: 13 de octubre de 2026. Admin Web + API Laravel.

## Acuerdos de esta iteración

- Cada persona solicita una cantidad distinta. Todos los integrantes seleccionados comparten una tasa.
- El gerente define el límite por integrante para esa solicitud. No se inventan mínimos/máximos fijos para Cobre, Bronce o Plata.
- Cobre: $80; Bronce: $75; Plata: $70 por cada $1,000 por semana. No son porcentajes. Plazo fijo: 16 semanas.
- La forma de entrega del crédito (`disbursement_method`) y la forma de pago semanal (`repayment_method`) son datos separados: sucursal (`cash_branch`) o depósito (`bank_deposit`). Quedan pendientes hasta autorización; la captura no puede establecerlos.
- El gerente define esos medios durante autorización. Se conserva el acuerdo previo de autorización comercial por Coordinación Comercial y entrega por sucursal: el flujo y sus pantallas se implementarán en la siguiente etapa, sin otorgar aquí facultades nuevas de aprobación.
- Para cobranza: cliente presenta ficha bancaria, cajero captura su folio y el movimiento corresponde al corte de depósitos, separado del efectivo. Este módulo aún no registra cobros, fichas, cortes ni comprobantes QR.

## Recorrido

Menú Solicitudes de crédito → Nueva solicitud, o desde la ficha del grupo → Solicitar crédito.

1. Buscar grupo con el buscador remoto limitado a la sucursal y al alcance del usuario.
2. Revisar integrantes y requisitos pendientes. Elegir una tasa común y el límite por integrante.
3. Seleccionar participantes y capturar montos individuales. Resumen estimado de capital, cuota grupal, interés, total, garantía del 10% y entrega neta.
4. Confirmar y registrar. El servidor asigna folio `CRE-Sxxx-xxxxxx` y estado `pendiente`. Los montos aprobados permanecen null, sin simular una aprobación.
5. Consultar detalle/listado; recargar vuelve a consultar la API. Cambios aún sin guardar generan advertencia al salir. No se guarda información financiera en LocalStorage.

## Reglas del servidor

- Al menos tres participantes distintos y pertenecientes al grupo, exactamente una Presidenta y una Tesorera. No se establece un máximo de 20 sin confirmación.
- Grupo/sucursal activos, promotor registrado y elegible en la misma sucursal.
- Cada participante debe tener expediente del grupo `en_revision`. Esto prueba que fue enviado completo; NO significa revisión de Gestión aprobada.
- Clientes archivados, de baja o con mora registrada no son elegibles. Un integrante con mora registrada bloquea la solicitud del grupo, aunque se excluya de la selección.
- Se bloquea otra solicitud/crédito pendiente, aprobado o entregado del grupo o de cualquiera de los participantes seleccionados, incluso en otro grupo. La detección usa la base unificada disponible; no consulta las siete bases legacy sin migrar.
- Montos mayores que cero, hasta dos decimales, sin comas/exponentes. Ninguno supera el límite del gerente ni, si existen, las restricciones del catálogo.
- UUID de petición estable e idempotencia: el mismo UUID con datos equivalentes devuelve el registro existente. Cambiar el contenido con ese UUID produce conflicto. Bloqueos de grupo/clientes y restricción única de participación impiden altas duplicadas dentro del flujo.
- Lecturas con bloqueo antes de consultar elegibilidad evitan establecer una vista antigua de MySQL antes de esperar los clientes compartidos. Transacción con hasta tres reintentos ante deadlock.
- Una firma de condiciones de tasa detecta cambios entre la selección y el guardado; una tasa modificada exige revisar nuevamente el cálculo.

## Cálculos e inmutabilidad

Se usan enteros en centavos (PHP) y BigInt (TypeScript). Cuota semanal = capital / 1000 × factor semanal, redondeada a centavos con mitad hacia arriba. Total = cuota × 16. Interés = total − capital. Garantía estimada = 10% del capital solicitado. La retención real se calculará sobre lo aprobado, en el flujo posterior.

Ejemplo: $10,000 con Cobre → cuota $800; total $12,800; interés $2,800; garantía estimada $1,000; entrega neta estimada $9,000. Los adelantos conservan el interés pactado.

`request_snapshot` conserva nombres/folios/cargos de participantes, tasa y restricciones, límite del gerente y cálculos. Cambiar la tasa del catálogo o los cargos actuales del grupo no reescribe la solicitud. Montos solicitados y composición se protegen contra cambios mediante los modelos; aprobación tendrá sus propios importes. No hay endpoint de edición/eliminación de solicitudes registradas en esta entrega. Correcciones formales, devolución, cancelación y nueva versión quedan para el flujo de revisión.

No se generan fechas de vencimiento antes de definir la entrega. Los importes mostrados son estimaciones de la solicitud, no una cartera exigible.

## Catálogo y permisos

Menú Tasas de crédito permite listar/buscar, crear, editar y desactivar tasas, con auditoría de cambios. Los límites de catálogo son opcionales. `cicle` se conserva como dato de referencia; no selecciona tasas automáticamente por historial.

La migración agrega Cobre, Bronce y Plata con sus factores confirmados y sin límites inventados. Las tasas antiguas tienen `calculation_method = null` y no se reutilizan como factor semanal sin configuración explícita.

Permisos `credit.view`, `credit.create`, `credit.rates`, editables solo por Super Admin en el catálogo técnico. Por defecto:

- Super Admin/Gerente General: consulta, registro y catálogo.
- Gerente: consulta y registro dentro de su sucursal.
- Coordinador/Promotor: consulta dentro de sus grupos permitidos; no registro ni tasas.
- Caja: sin acceso a esta captura.

La API usa `CreditAccess` y el alcance existente de `IntakeAccess`. Roles nuevos no obtienen acceso global por recibir un permiso. El selector de tasas consulta por texto en servidor, con 20 resultados por consulta.

## Aplicación local pendiente

Migración: `2026_10_09_020000_add_credit_request_workflow.php`.

La migración de solicitudes fue aplicada por el usuario. El 13 de octubre se aplicó además la migración de Gestión en MySQL local. No se crearon créditos de prueba en la base real. En otro entorno, desde `backend` ejecutar la migración correspondiente:

```sh
php artisan migrate --path=database/migrations/2026_10_09_020000_add_credit_request_workflow.php
```

Incluye columnas de solicitud, medios de entrega/pago pendientes, restricción única de participación, tasas y permisos. Antes de aplicar en otro entorno revisar duplicados preexistentes de `loan_participations`: la migración no elimina ni combina historial. El rollback requiere un plan explícito para conservar snapshots.

## Validación

39 pruebas de integración aisladas en SQLite memoria (235 aserciones), incluidas ocho nuevas pruebas de crédito: montos diferentes, cálculo, comité, expedientes, mora, doble participación, sucursales, permisos, idempotencia y tasas. Tres pruebas del cálculo TypeScript con el estándar, redondeo y entradas inválidas. TypeScript y ESLint en archivos modificados.

Comprobación de interfaz con API simulada: captura con tres montos distintos, búsqueda de tasa, resumen, confirmación, detalle tras recarga, catálogo y ancho móvil de 390 px sin desbordamiento. No sustituye un recorrido real con MySQL ni una prueba concurrente de carga en ese motor. Las pruebas terminan; no se mantienen procesos continuos.


La revisión por Gestión se encuentra implementada. Consulta [Revisión de Gestión](revision-de-gestion.md) para el alcance, los permisos y las pruebas. La autorización comercial está implementada en [Autorización comercial](autorizacion-comercial.md). El desembolso continúa pendiente.
