# Dashboard operativo

Actualizado el 15 de octubre de 2026. Los indicadores y la bandeja se consultan en `GET /api/dashboard`, con sesión Sanctum y usuario activo. El servicio de Axios y el store Pinia centralizan la carga; no se guardan indicadores en localStorage.

## Indicadores

- **Grupos solidarios:** grupos activos visibles mediante el mismo alcance de captura que usa el módulo de grupos. No incluye archivados. Requiere acceso de captura.
- **Solicitudes en revisión:** solicitudes de crédito pendientes, en estado de Gestión `pending` o `in_review`. No incluye expedientes de cliente por sí solos, solicitudes devueltas o listas para autorización.
- **Créditos activos:** créditos `entregado`, todavía no liquidados. Los aprobados pero sin entregar no se cuentan aquí.
- **Cobros del día:** importe total y número de pagos recibidos en la fecha actual de America/Cancun, separados en efectivo y depósitos. Se usa `received_on`, no la fecha del depósito. Se excluyen los pagos que hayan sido revertidos; no es un flujo de egresos por reversos del día. Cerrar un corte no elimina el cobro de este indicador ni lo duplica. No representa un saldo físico de caja, utilidad ni cartera por cobrar.

Los créditos respetan `CreditAccess`. Los grupos respetan `IntakeAccess`: Promotor ve sus grupos asignados; Gerencia ve su sucursal; los perfiles globales tienen su alcance habitual. Para cobros, los usuarios con `credit.collect` ven sus propios registros, salvo que también tengan `cash.audit`, que habilita la agregación de los cajeros dentro del alcance de sucursales. Super Admin y Gerente General mantienen alcance global. Los perfiles sin acceso ven `Sin acceso`; un cero significa una consulta permitida sin registros coincidentes.

## Bandeja

Muestra únicamente categorías con pendientes y permisos: revisión nueva/en curso, observaciones para captura, condiciones por proponer, propuestas devueltas, autorizaciones, entregas y cobros propios de hoy pendientes de corte. Los enlaces abren la bandeja correspondiente con su estado preseleccionado. No se presenta como una lista de toda la cobranza atrasada ni incluye cortes de días anteriores.

El botón de actualización vuelve a consultar el servidor. También se consulta al abrir o recargar Inicio. No hay sondeo continuo en segundo plano. Se muestra el alcance y la hora de actualización. Ante un error se retiran los valores anteriores y se ofrece reintentar; no se transforman errores en ceros ni se presenta una bandeja vacía como si fuera un resultado válido.

## Validación

- 70 pruebas de backend, 774 comprobaciones, con SQLite en memoria. Los casos nuevos cubren permisos, sucursal, grupos inactivos, cobros propios versus sucursal, solicitudes devueltas, cortes ya cerrados, cambio de día en Cancún y usuarios inactivos.
- TypeScript y ESLint correctos.
- `tests/dashboard-ui.cjs` en admin-web: indicadores desde la API simulada, enlaces con filtro, recarga, error/reintento, acceso restringido y móvil sin desbordamiento. Se inspeccionó la vista de escritorio.
- Consulta de solo lectura al MySQL local con los usuarios demo de Gerencia, Caja y Promotor. El demo tenía un grupo activo y ningún crédito entregado o cobro del día; los ceros corresponden a sus registros reales. No se crearon operaciones para inflar indicadores.

No requiere nuevas migraciones. El endpoint usa las tablas de cobros y cortes ya instaladas en las etapas anteriores. No se ejecutaron pruebas de carga sobre una cartera de producción; los conteos y sumas se agregan en la base, sin traer expedientes completos al navegador.
