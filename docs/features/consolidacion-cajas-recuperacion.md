# Caja central y seguimiento consolidado

Entrega de código del 18/11/2026. Corresponde a la integración funcional de `Downloads/cajas` en la plataforma nueva, sin otro sistema de autenticación ni conexiones permanentes a bases de sucursales. No se modificaron datos operativos durante las pruebas.

## Instalación

Ejecutar las migraciones pendientes en el entorno de ensayo antes de iniciar la nueva interfaz:

```sh
php artisan migrate
```

La migración `2026_11_18_010000_consolidate_portfolio_followup.php` agrega `portfolio_class`, `portfolio_version`, `portfolio_events` y `credit_followup_entries`, así como permisos de seguimiento y roles de Cobranza/Bancos. No se ejecutó en la base operativa en esta entrega. No ejecutar migraciones ni seeders de demostración contra producción sin el procedimiento de despliegue.

Recompilar Admin Web con `pnpm build`. Las compilaciones de escritorio entregadas anteriormente no incluyen esta actualización; deben regenerarse después de la validación funcional.

## Cartera ordinaria / recuperación / consolidada

En **Cartera y vencimientos** se elige la clasificación. Por defecto se consulta ordinaria. El CSV incluye la clasificación y el reporte de impresión permite elegir ordinaria, recuperación o consolidada. El dashboard conserva el total consolidado: no oculta deuda al trasladar un crédito.

Un usuario con `credit.followup` abre **Seguimiento** desde el crédito en la cartera. Allí ve todas las integrantes con saldo, exigible, atraso, días y teléfono, más el historial de notas y reclasificaciones.

Solo Gerente General o Super Admin con la facultad correspondiente puede trasladar a recuperación o reincorporar a ordinaria. Se exige crédito entregado, versión actual y motivo de 10–2000 caracteres. El traslado exige atraso en cuotas o mora pendiente. El cambio es manual, transaccional y conserva antes/después, fecha, autor y UUID de operación. Una respuesta perdida puede reintentarse con el mismo UUID y contenido.

La reclasificación no modifica el estado financiero, condiciones, garantía, aplicaciones, recibos ni cortes. No condona ni suspende mora. Los cobros siguen disponibles y no reincorporan automáticamente el crédito. Una liquidación conserva la clasificación en el historial; el crédito pagado deja de aparecer entre los entregados, como ya ocurría.

## Observaciones

Gerente, Gerente General, Super Admin y el nuevo rol Cobranza reciben `credit.followup`, sujeto a su alcance de sucursal. Caja y Bancos no lo reciben automáticamente. El permiso de reclasificación no puede delegarse a otros roles desde el catálogo; el servidor también exige el rol confirmado.

Seleccionar integrante, escribir y guardar. No se puede editar/borrar una observación: las correcciones se agregan como otra nota. Las notas repetidas con un nuevo UUID se conservan. Un reintento del mismo UUID no agrega otra nota y un contenido distinto genera conflicto. Se puede filtrar por integrante, fechas y autor y consultar el historial paginado. El menú **Observaciones de cobranza** reúne las notas de los créditos autorizados con filtros de sucursal, fechas, grupo/cliente/folio y autor; cada resultado permite abrir su seguimiento.

Las observaciones pertenecen al crédito/ciclo y participación concretos, no a cualquier crédito de una persona. No aparecen en la app cliente ni en el estado financiero público. Se advierte al salir con captura pendiente. Si la respuesta de guardado no se confirmó, reintentar la misma operación antes de abandonar; después de recargar, consultar el historial antes de volver a capturar.

## Caja central

Menú **Caja central**: periodo de hasta un año, sucursal y canal con filtros. Caja consulta sus cobros; usuarios con `cash.audit` consultan cajeros dentro del alcance permitido. Super Admin y Gerente General mantienen alcance global. Bancos recibe consulta y auditoría, sin registrar cobros ni cerrar cortes automáticamente.

El resumen muestra efectivo y depósitos originales, reversos separados y netos por canal/sucursal. Incluye cobros con y sin corte sin duplicarlos. Los pagos se ubican por recepción (`received_on`); los reversos por su fecha (`reversed_on`). Un reverso posterior no resta del ingreso original de otro día: figura como ajuste en el día del reverso. La fecha de depósito se conserva aparte.

El detalle paginado incluye grupo, folio de crédito, número de pago, cajero original, ficha bancaria y corte original. **Todos los movimientos CSV** descarga todo el resultado filtrado. **Imprimir resumen** imprime el consolidado y su fecha; no imprime solo una página del detalle como si fuera el registro completo. Utilizar CSV para el detalle completo.

Es un reporte de registros, no conciliación contra estado bancario ni arqueo físico. Un neto negativo es posible si el periodo contiene un reverso de un ingreso anterior. No certifica devolución de dinero. Los cortes inmutables existentes y su flujo por cajero permanecen separados.

## Migración y despliegue

La extracción de notas centrales y el plan sin escrituras se explican en [Migración del historial de Cajas](../migration/cajas-historial.md). La carga real queda pendiente de la base central restaurada, las equivalencias y la conciliación.

Consultar [Análisis y correspondencias](../analisis/consolidacion-cajas-cartera.md) y [Preparación cPanel sin consola](../deployment/cpanel-sin-consola.md). Falta confirmar PHP, proveedor y resolución del programador automático porque el usuario informa que no dispone de cron.

## Validación

Pruebas backend con SQLite temporal, sin bases operativas; UI con API simulada. Incluyen traslado, reintento, versión en conflicto, autorización por rol, ausencia de atraso, reincorporación, cobro tras traslado, rollback, notas repetidas, integrante/sucursal ajena, canales de caja, cortes y reversos en fecha posterior, exportación y aislamiento por cajero. El ensayo de migración de notas comprueba UUID deterministas, autor histórico, repetición legítima, caché de última nota y cuarentena de relaciones/fechas.

La validación automatizada no sustituye el recorrido manual con MySQL y datos conciliados ni certifica el hosting cPanel. El módulo no declara concluida la migración real ni la publicación.

### Resultado final de esta entrega

- Backend: **195 pruebas, 2,369 comprobaciones**, aprobadas con SQLite temporal. Incluye conservación del crédito visible en client-app y separación de impresión/CSV al trasladar a recuperación.
- Admin Web: compilación SPA, TypeScript y ESLint aprobados.
- UI con API simulada: recuperación, notas, reintento de respuesta perdida sin duplicados, historial general con filtros tras recarga, caja central, CSV, impresión de resumen y ancho móvil de 390 px. Regresión de cartera anterior aprobada.
- El puerto MySQL 3306 local no estaba disponible al cerrar la revisión. La migración nueva no se ejecutó sobre tu base de desarrollo/operación; debe aplicarse en ensayo antes de abrir estas pantallas.
- No se extrajo la base central real de Cajas, ni se importaron notas, reclasificaron créditos operativos o publicó en cPanel.
