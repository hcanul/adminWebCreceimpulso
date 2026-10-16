# Estado de cuenta grupal e individual

Entrega: 15 de octubre de 2026.

## Uso

1. Abre un crédito entregado o pagado desde **Solicitudes de crédito** o **Cobros**.
2. Selecciona **Estado de cuenta**.
3. Consulta todo el grupo o busca una integrante por nombre o número de cliente. La selección se conserva al recargar la página.
4. Revisa el total pactado, el pago vigente, el saldo pendiente, lo exigible a la fecha y el importe en atraso.
5. Consulta las condiciones aprobadas, las 16 semanas y el historial de pagos y aportaciones solidarias.
6. Selecciona **Imprimir o guardar PDF**. Usa **Actualizar** para consultar los movimientos más recientes.

Lo exigible incluye las cuotas vencidas y las de hoy; el atraso comienza al día siguiente del vencimiento. Ambos importes forman parte del saldo pendiente y no deben sumarse otra vez. La garantía retenida no se considera un abono.

Una aportación solidaria reduce el saldo de la integrante beneficiaria. El historial distingue quién aportó y a quién se aplicó. Los pagos revertidos permanecen visibles como antecedentes, pero no integran el pago vigente. Un reverso puede reabrir cuotas y atrasos sin redistribuir pagos posteriores.

El documento presenta el saldo actual: no reconstruye estados históricos a una fecha anterior. No sustituye al comprobante de pago. Las personas con permiso de cobro o reverso pueden abrir el comprobante QR desde el historial.

## Implementación y acceso

- `GET /api/credit/applications/{id}/statement`, autenticado, limitado y con respuesta `private, no-store`.
- Conserva el permiso `credit.view` y los alcances existentes por sucursal y grupos asignados. No concede acceso adicional a caja ni a los comprobantes QR.
- Usa las condiciones aprobadas e inmutables del crédito y los mismos cálculos de saldo y atraso que Caja. Un cambio posterior en el catálogo de tasas no altera el estado de cuenta.
- Consulta bajo bloqueo del crédito, compartido con los procesos de cobro y reverso. Verifica el calendario de 16 semanas, montos y concordancia entre pagos vigentes y cuotas antes de responder.
- Devuelve todos los pagos del crédito, sin limitarse a una página. Omite CURP, referencias bancarias, notas internas, responsables y motivos de reverso.
- Axios central, servicio de consulta y store Pinia. Cancela solicitudes al salir o cambiar de crédito. No persiste información financiera en el almacenamiento del navegador.
- Al actualizar o fallar la consulta, retira los datos previos para impedir que se imprima un resultado desactualizado. Una integrante que no pertenece al crédito no genera reporte.
- No requiere migraciones ni modifica movimientos financieros.

## Validación

- Backend: 83 pruebas y 1,057 aserciones en SQLite en memoria. Incluye pagos parciales, solidaridad, reversos, liquidación, historial completo, permisos, sucursales y registros incompletos.
- TypeScript, ESLint y Pint revisados.
- `frontend/apps/admin-web/tests/credit-statement-ui.cjs`: API simulada, filtro individual, recarga, 16 semanas, reversos, permisos de QR, fallos y reintentos, impresión y vista móvil de 390 px.
- PDF individual de dos páginas inspeccionado visualmente, incluidos encabezados repetidos del calendario. Tablas móviles con desplazamiento horizontal interno.
- No se modificó MySQL ni se probaron impresoras físicas. Las pruebas no sustituyen una validación de carga con historiales de gran volumen.
