# Autorización comercial de créditos

Implementado el 14 de octubre de 2026. Aplicación: `frontend/apps/admin-web`. API: `backend`.

## Recorrido

1. Gestión completa las seis comprobaciones de todos los integrantes. Solo entonces la solicitud puede recibir condiciones propuestas.
2. En **Autorización comercial**, el gerente filtra **Pendiente de condiciones del gerente**, abre el crédito y captura el importe propuesto de cada integrante, la forma de entregar el crédito y la forma de realizar los pagos semanales. Ambas formas se eligen por separado: En sucursal o Depósito bancario. La propuesta requiere observaciones.
3. Al enviar, se cierra esa versión y los importes aprobados siguen nulos. Se guardan la identidad del gerente, las condiciones completas y sus cálculos. Recargar la página recupera lo guardado desde la API.
4. Coordinación Comercial abre las propuestas pendientes. Puede consultar el expediente de Gestión, el solicitado original, la propuesta y las versiones anteriores. Puede autorizar exactamente la propuesta o devolverla con observaciones. No modifica silenciosamente lo enviado por el gerente.
5. La devolución permite al gerente enviar otra versión de condiciones; la anterior sigue en el historial. No reabre los expedientes personales ni altera la revisión de Gestión.
6. Al autorizar, la solicitud pasa a `aprobado`; se registran importes aprobados por integrante, total aprobado, condiciones calculadas, ambas formas de operación, usuario y fecha. El crédito queda pendiente de entrega.

## Reglas

- Participantes y cargos son los de la solicitud original: no se eliminan integrantes en autorización. El importe de cada integrante debe ser positivo, no superar lo solicitado y respetar el límite del gerente y los límites de la tasa conservados al solicitar. No se inventan mínimos nuevos si el catálogo no tenía uno.
- Una tasa común, tomada del snapshot original: modificar el catálogo después no cambia la solicitud ni su autorización.
- Cálculo en centavos enteros, redondeo comercial y 16 cuotas semanales. Garantía del 10 % sobre el aprobado de cada integrante. Los totales grupales suman los importes individuales ya redondeados.
- El interés pactado se mantiene incluso ante liquidación anticipada. Esta entrega no implementa cobros ni liquidaciones.
- Antes de proponer y autorizar se revalida grupo/sucursal activos, promotor elegible, pertenencia y cargos de los integrantes, mora, otros créditos abiertos y expedientes enviados que coincidan con las versiones verificadas por Gestión.
- Se conserva la separación entre solicitado, propuesto y aprobado. Los importes autorizados, el snapshot aprobado y ambas formas de operación no se pueden sobrescribir mediante los modelos.
- No se genera calendario, salida de caja, transferencia, retención real ni comprobante de entrega al autorizar. El resumen muestra lo que se debe entregar y retener cuando se registre el desembolso.
- En pagos semanales por depósito, el módulo posterior de cobros exigirá el folio bancario y separará depósitos del efectivo. No se implementó todavía esa captura ni el corte de caja.

## Acceso

- `credit.propose`: Gerente, Gerente General y Super Admin inicialmente.
- `credit.authorize`: nuevo rol **Coordinación Comercial**, Gerente General y Super Admin inicialmente.
- El rol de campo **Coordinador** no obtiene autorización financiera. Gestión, Promotor y Caja tampoco.
- El nuevo rol Comercial requiere sucursal en Usuarios. Los nuevos permisos tienen alcance de su sucursal; el alcance global se mantiene para Super Admin y Gerente General. No se asignan roles a usuarios existentes automáticamente.
- Solo Super Admin administra usuarios, roles y permisos. Los permisos nuevos requieren `intake.access` y `credit.view`.
- Coordinación puede consultar la revisión y sus expedientes en modo lectura. Las propuestas y notas comerciales se restringen a quien puede proponer o autorizar; no se exponen en el detalle general a promotores.
- Un perfil con ambos permisos puede efectuar ambas operaciones. No se incorporó una restricción de dos personas distintas, que no se había definido como regla; se conservan ambos usuarios en auditoría.

## Arquitectura y persistencia

DTOs `ProposeCreditData` y `AuthorizeCreditData`, Actions por operación, enum `AuthorizationStatus`, validación de entrada y servicio `CreditAuthorization`. Reutiliza `CreditCalculator` y la elegibilidad excluyendo el crédito actual de la búsqueda de otros créditos activos.

Estados comerciales: `awaiting_proposal`, `pending_authorization`, `returned`, `authorized`. Se mantienen separados de la revisión de Gestión y del estado financiero.

Transacciones con bloqueo de grupo, crédito y clientes; bloqueo de participaciones al aprobar y versión optimista para impedir decisiones sobre propuestas anteriores. Repetir un envío o una aprobación devuelve 409 y no duplica movimientos. Tras un fallo de red, recargar para verificar el resultado antes de reintentar.

`credit_authorization_events` conserva UUID, sucursal, usuario, nombre, fecha, versión, acción, observaciones y propuesta completa. La aprobación guarda adicionalmente `approved_snapshot`, `approved_by`, `approved_at` y los importes de cabecera y participaciones, todo en una transacción.

Frontend con servicios sobre el Axios central y Pinia (`authorization.store.ts`). Los datos guardados se recuperan de la API; no se almacenan expedientes o propuestas en localStorage. Los cambios sin enviar generan advertencia al salir.

## Migración y verificación

Aplicada en MySQL local: `2026_10_14_010000_add_credit_authorization.php`. Se verificó previamente el entorno local, el destino y la existencia de la migración de Gestión. No se insertaron créditos de prueba en la base real.

- 51 pruebas de backend, 396 comprobaciones, en SQLite en memoria. Incluyen aprobación sin desembolso, devolución/corrección, permisos, separación de sucursales, versiones desactualizadas, importes inválidos, expedientes cambiados, mora, créditos simultáneos, cargos e inmutabilidad.
- TypeScript y ESLint para los archivos afectados; regresión de los cálculos financieros.
- Prueba de navegador reproducible en `frontend/apps/admin-web/tests/credit-authorization-ui.cjs`, con API simulada: gerente propone, comercial devuelve, gerente corrige y comercial autoriza, luego recarga. Verifica valores de los dos métodos, filtros y permisos visibles. Capturas de escritorio y móvil de 390 px, sin desbordamiento de la página.
- Para ejecutar la prueba visual se necesita el frontend local ya disponible y Playwright con Chrome. Puede configurarse `PLAYWRIGHT_MODULE` con la ruta del módulo instalado y `CRECE_UI_URL` con la URL local. No inicia servidores, no utiliza datos reales y cierra el navegador al terminar.
- Las pruebas de navegador simulan la API y las del backend usan SQLite; no constituyen una prueba de carga/concurrencia real de varias conexiones MySQL.

## Siguiente etapa

Desembolso en sucursal: verificar autorización vigente, registrar entrega efectiva y referencia bancaria si corresponde, calcular retención real y generar las 16 fechas de vencimiento. Se debe definir la fecha de primera cuota y el tratamiento de días inhábiles antes de construir ese calendario.


Actualización: el [desembolso y calendario](desembolsos-y-calendario.md) ya está implementado. La sucursal captura expresamente el primer vencimiento y el calendario mantiene intervalos de siete días. No se ha implementado una política de traslado por días inhábiles.

## Fecha pactada del primer pago — 16 de octubre de 2026

Coordinación Comercial captura **Fecha pactada del primer pago** antes de autorizar. La fecha es obligatoria para aprobar, debe ser válida y posterior al día de autorización en Cancún. Devolver condiciones al gerente no exige fecha.

La pantalla permite revisar las 16 fechas y la cuota grupal antes de confirmar. Semana 1 corresponde a la fecha pactada; las siguientes son esa fecha más 7, 14, …, 105 días. No se ajustan fines de semana ni festivos, ni cambian importes o intereses.

La fecha se guarda en `approved_snapshot.first_due_on` y en el evento de aprobación. Forma parte de las condiciones inmutables y se muestra después de recargar y en el historial. Se reutiliza la estructura JSON existente: no requiere migración.

Al desembolsar, Caja recibe la fecha precargada y de solo lectura. El servidor rechaza sustituirla, incluso mediante una petición manual. La fecha real de entrega debe ser anterior al primer pago. Si la entrega se retrasa hasta esa fecha, el sistema bloquea el registro con esas condiciones; no desplaza silenciosamente el calendario. Un procedimiento para modificar condiciones ya autorizadas requiere un desarrollo separado y auditado.

Las autorizaciones históricas sin este dato conservan el mecanismo anterior: capturar la primera fecha en el desembolso. No se actualizan automáticamente autorizaciones ni calendarios existentes.

Validación: 88 pruebas del backend, 1,175 aserciones en SQLite en memoria; incluye fecha ausente/inválida/pasada, historial, inmutabilidad, intento de cambio en Caja, entrega tardía, compatibilidad histórica y calendario de 16 semanas cruzando el año. TypeScript y pruebas de interfaz con API simulada. No se modificó MySQL.
