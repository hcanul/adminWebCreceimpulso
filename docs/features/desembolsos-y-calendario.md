# Desembolsos y calendario de créditos

Entrega del 14 de octubre de 2026. Proyecto: `creceimpulso-workspace`, backend Laravel y `frontend/apps/admin-web`.

## Uso

1. Recargar la sesión para actualizar permisos y abrir **Desembolsos y calendario**. La bandeja filtra Autorizado pendiente de entrega, Entregado y Liquidado, con búsqueda por folio/grupo y paginación.
2. Abrir un crédito aprobado. Se muestran el capital, las cuotas, la garantía del 10 % y el neto autorizado por integrante y para el grupo. Los importes y la modalidad no son editables en desembolso.
3. Capturar la fecha real de entrega y la fecha del primer pago. La entrega no puede ser futura ni anterior a la fecha de autorización en America/Cancun. El primer pago debe ser posterior a la entrega; no se supone automáticamente que deba ser siete días después. La sucursal elige expresamente esa fecha.
4. En modalidad bancaria, registrar banco y referencia de la operación ya realizada. En entrega en sucursal no se admiten datos bancarios. Agregar observaciones y confirmar expresamente el neto entregado y la garantía retenida.
5. Revisar las 16 fechas de la vista previa. Todas están separadas por siete días; no se trasladan sábados, domingos o festivos automáticamente.
6. **Registrar entrega realizada** confirma el desembolso, cambia el crédito a `entregado` y genera el calendario. Se puede consultar por grupo y por integrante después de recargar la página.

El sistema registra una entrega efectuada; no ejecuta transferencias ni entrega efectivo automáticamente. No genera aún un recibo PDF de desembolso ni modifica un saldo de caja.

## Datos y reglas

- `credit_disbursements`: UUID, crédito único, sucursal, usuario y nombre al registrar, fecha real de entrega, primer vencimiento, modalidad autorizada, banco/referencia cuando aplica, capital, garantía retenida, neto entregado, observaciones, snapshot aprobado y fecha del registro.
- La garantía queda documentada separada del dinero entregado y de la deuda. No cuenta como pago de cuotas. La devolución o aplicación posterior de la garantía requiere su propio flujo y todavía no se implementa.
- `loan_installments`: UUID, sucursal, crédito, participación, cliente, número 1 a 16, vencimiento, importe exigible y monto pagado inicialmente cero. La unicidad de participación/número impide repetir cuotas.
- Cada integrante conserva la cuota semanal fija del snapshot aprobado. La suma de sus 16 cuotas coincide con el total a devolver; los totales grupales suman esos importes. No se recalculan con el catálogo actual ni se reparte capital/interés por cuota sin una política de aplicación definida.
- No se infiere el estado pagado o en mora a partir de una captura de entrega. El registro y aplicación de cobros se desarrolla en la siguiente etapa.
- El estado financiero cambia de aprobado a entregado; la autorización permanece conservada. La fecha pactada heredada no se reutiliza con un significado nuevo.

## Protección

Permiso `credit.disburse`, inicialmente para Caja, Gerente, Gerente General y Super Admin. Promotores, Gestión y Coordinación Comercial no lo reciben automáticamente. Los perfiles locales están limitados a su sucursal; Super Admin y Gerente General mantienen alcance global.

Caja obtiene consulta de créditos y desembolsos sin permiso `intake.access`: no puede capturar grupos/clientes ni consultar los endpoints de expedientes. Se separó el middleware de acceso a créditos de la captura; Roles admite configurar consulta/desembolso sin abrir permisos de captura. Las pruebas anteriores de aislamiento de Caja siguen pasando.

Antes de entregar se revalida autorización, versión, integridad de importes, sucursal/grupo activos, promotor, integrantes y cargos, ausencia de mora u otros créditos y expedientes coincidentes con Gestión. Todo se registra en una transacción con bloqueos. Si cualquier cuota falla, se revierte también la entrega y el estado del crédito.

Se utiliza un UUID estable mientras se registra la entrega. Repetir exactamente la misma petición con el mismo usuario devuelve el resultado original. Cambiar su contenido o intentar otra entrega del mismo crédito produce conflicto, sin duplicar cuotas. Tras una respuesta incierta se puede recargar para comprobar si el registro quedó guardado.

No hay endpoints para editar o eliminar desembolsos/cuotas. Correcciones, reversos y anulaciones necesitarán acciones auditadas específicas. No hay transferencia bancaria real ni caja contable completa en esta fase. No se asume que un folio bancario sea único entre todos los bancos: la protección de duplicados del desembolso se basa en el crédito único y el UUID.

Banco, referencia y observaciones se muestran solo a perfiles con permiso de desembolso. Los demás perfiles con acceso al crédito consultan fechas, importes y calendario sin esos datos. La app futura del cliente deberá tener un endpoint restringido a su identidad, no reutilizar sin cambios el endpoint operativo grupal.

## Migración y pruebas

Aplicada en MySQL local: `2026_10_14_020000_add_credit_disbursements.php`, comprobando previamente el entorno, destino y migración de autorización. No se insertaron desembolsos ni clientes de prueba en la base real. El rollback requiere plan de conservación financiera.

- 55 pruebas de backend con 454 comprobaciones en SQLite en memoria. Incluyen importes, 16 cuotas por integrante, reintentos, doble entrega, fechas, banco/referencia, confirmación, permisos, sucursales, mora, rollback, año bisiesto y fines de semana.
- TypeScript, ESLint y formato de los archivos modificados.
- Prueba reproducible `frontend/apps/admin-web/tests/credit-disbursement-ui.cjs`: Caja sin acceso de captura, vista previa, entrega bancaria, confirmación, calendario persistente y recarga, escritorio y móvil de 390 px sin desbordamiento de página. API simulada; no toca datos reales. Usa frontend ya disponible, Playwright y Chrome; se puede indicar `PLAYWRIGHT_MODULE` y `CRECE_UI_URL`. El navegador se cierra al terminar.
- No se realizó una prueba de carga/concurrencia entre múltiples conexiones MySQL ni conciliación con bancos. El registro de transferencia es declarativo, con referencia capturada por la sucursal.

## Siguiente paso

La primera entrega de cobros y aplicación a cuotas está implementada en [Cobros en caja](cobros-en-caja.md): pagos parciales, adelantos y aportaciones solidarias con quién aporta y quién recibe la aplicación. El usuario confirmó aplicar primero a la cuota pendiente más antigua de cada beneficiaria. Ver en esa guía el estado de instalación local. Los comprobantes verificables y cortes separados son la siguiente entrega. La política de días inhábiles sigue pendiente; el calendario conserva las fechas semanales elegidas.
