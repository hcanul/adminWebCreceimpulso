# Cartera y vencimientos

Entrega: 16 de octubre de 2026.

## Uso

1. Abre **Cartera y vencimientos** en el menú lateral.
2. Busca por folio del crédito, nombre del grupo o sucursal.
3. Filtra todos los créditos activos, con atraso, con cuota pendiente hoy o sin atraso.
4. Revisa los totales de la búsqueda y el detalle por crédito. La lista muestra 25 créditos por página, priorizando el vencimiento pendiente más antiguo.
5. Abre **Estado de cuenta** para consultar integrantes, las 16 semanas y movimientos.

La búsqueda, el filtro y la página permanecen en la dirección al recargar. **Actualizar** consulta los saldos actuales. Los totales corresponden a todos los resultados del filtro, no únicamente a la página visible.

## Significado de los importes

- **Saldo pendiente:** suma de cuotas aún no cubiertas, incluidos capital e interés pactados.
- **Exigible a la fecha:** saldo pendiente de cuotas vencidas y de hoy.
- **Pendiente de hoy:** saldo pendiente de cuotas cuya fecha de vencimiento es hoy.
- **Importe en atraso:** saldo pendiente de cuotas anteriores a hoy. El día de vencimiento todavía no genera atraso.
- **Días de atraso:** días desde la cuota pendiente más antigua del crédito.
- **Próxima cuota pendiente:** primera fecha de hoy o posterior con saldo pendiente. Puede haber atrasos aunque no existan cuotas futuras.

Los importes exigibles y atrasados ya están incluidos en el saldo pendiente. No deben sumarse otra vez. Un crédito sin atraso puede tener una cuota pendiente hoy.

Solo incluye créditos entregados: excluye solicitudes, autorizados sin entregar y créditos pagados. Los pagos parciales, anticipos, aportaciones solidarias y reversos se reflejan a través de los saldos vigentes del calendario. No aplica recargos, no modifica pagos y no constituye un módulo de gestiones del futuro departamento de cobranza.

## Implementación

- `GET /api/credit/portfolio`, autenticación y permiso `credit.view`, alcance existente de `CreditAccess` por sucursal o grupos asignados. Tanto las filas como los totales parten de ese mismo conjunto autorizado.
- Búsqueda en servidor con parámetros enlazados, comodines de búsqueda tratados como texto literal, límite de 100 caracteres y validación del filtro y página.
- Cálculos agregados en centavos, paginación de 25 filas y orden estable. Una consulta transaccional obtiene los totales y el detalle; fecha operativa de Cancún.
- No expone CURP, expedientes, referencias bancarias ni notas privadas. Respuesta `private, no-store`.
- Frontend Vue/TypeScript, servicio sobre Axios central, Pinia, cancelación de consultas y limpieza de resultados anteriores ante cambios o errores. No persiste saldos en el almacenamiento del navegador.
- No requiere migración ni permisos nuevos.

## Validación

- Backend: 85 pruebas, 1,113 aserciones, SQLite en memoria. Nuevos casos de vencimiento hoy frente a atraso, pago parcial, reverso, filtros, búsquedas literales, resumen independiente de página, alcance de promotor y sucursal, cuenta inactiva y falta de permiso.
- TypeScript, ESLint y Pint revisados.
- `frontend/apps/admin-web/tests/credit-portfolio-ui.cjs`: API simulada, búsqueda, cambio de página y filtro, recarga, resultados vacíos, errores sin datos anteriores, reintento y diseño móvil.
- Inspección visual en escritorio y móvil de 390 px. Las tablas tienen desplazamiento interno.
- No se modificó MySQL ni se ejecutó una prueba de carga sobre una cartera de producción. La validación automatizada de consultas se realizó en SQLite; falta comprobar rendimiento e índices con el volumen real de MySQL antes del despliegue productivo.
