# Cartera y vencimientos

Actualización: 23 de octubre de 2026.

## Uso

1. Abre **Cartera y vencimientos** en el menú lateral.
2. Busca por folio del crédito, nombre del grupo o sucursal.
3. Filtra todos los créditos activos, con atraso, con cuota pendiente hoy o sin atraso.
4. Revisa los totales de la búsqueda y el detalle por crédito. La lista muestra 25 créditos por página, priorizando el vencimiento pendiente más antiguo.
5. Abre **Estado de cuenta** para consultar integrantes, las 16 semanas y movimientos.

La búsqueda, el filtro y la página permanecen en la dirección al recargar. **Actualizar** consulta los saldos actuales. Los totales corresponden a todos los resultados del filtro, no únicamente a la página visible.

## Significado de los importes

- **Saldo total:** cuotas aún no cubiertas (capital e interés pactados) más mora pendiente autorizada. Las columnas separan cuotas, mora y total.
- **Exigible a la fecha:** cuotas vencidas y de hoy más mora pendiente.
- **Pendiente de hoy:** saldo pendiente de cuotas cuya fecha de vencimiento es hoy.
- **Importe en atraso:** cuotas anteriores a hoy más mora pendiente. El día del vencimiento todavía no genera atraso.
- **Días de atraso:** mayor antigüedad entre las cuotas pendientes y el inicio del cargo de mora pendiente más antiguo. El primer día del cargo cuenta como un día de atraso.
- **Próxima cuota pendiente:** primera fecha de hoy o posterior con saldo pendiente. Puede haber atrasos aunque no existan cuotas futuras.

Los importes exigibles y atrasados ya están incluidos en el saldo pendiente. No deben sumarse otra vez. Un crédito sin atraso puede tener una cuota pendiente hoy.

Solo incluye créditos entregados: excluye solicitudes, autorizados sin entregar y créditos pagados. Los pagos parciales, anticipos, aportaciones solidarias y reversos se reflejan a través de los saldos vigentes del calendario. Incluye la mora autorizada incluso cuando las cuotas estén cubiertas; excluye simulaciones de créditos anteriores. La consulta no registra cargos ni modifica pagos y no constituye un módulo de gestiones del futuro departamento de cobranza.

## Implementación

- `GET /api/credit/portfolio`, autenticación y permiso `credit.view`, alcance existente de `CreditAccess` por sucursal o grupos asignados. Tanto las filas como los totales parten de ese mismo conjunto autorizado.
- Búsqueda en servidor con parámetros enlazados, comodines de búsqueda tratados como texto literal, límite de 100 caracteres y validación del filtro y página.
- Cálculos en centavos, paginación de 25 filas y orden estable. Lectura transaccional con fecha operativa de Cancún. Se proyecta la mora con el mismo motor de Caja y cargas de historiales por lotes de 100 créditos; los filtros y totales se aplican antes de paginar. Se conservan resúmenes por crédito en memoria. Debe medirse el tiempo de respuesta con el volumen real antes de producción.
- No expone CURP, expedientes, referencias bancarias ni notas privadas. Respuesta `private, no-store`.
- Frontend Vue/TypeScript, servicio sobre Axios central, Pinia, cancelación de consultas y limpieza de resultados anteriores ante cambios o errores. No persiste saldos en el almacenamiento del navegador.
- No requiere migración ni permisos nuevos.

## Validación

- Backend: 120 pruebas, 1,576 aserciones, SQLite en memoria. Nuevos casos de vencimiento hoy frente a atraso, pago parcial, reverso, filtros, búsquedas literales, resumen independiente de página, alcance de promotor y sucursal, cuenta inactiva y falta de permiso.
- TypeScript, ESLint y Pint revisados.
- `frontend/apps/admin-web/tests/credit-portfolio-ui.cjs`: API simulada, búsqueda, cambio de página y filtro, recarga, resultados vacíos, errores sin datos anteriores, reintento y diseño móvil.
- Inspección visual en escritorio y móvil de 390 px. Las tablas tienen desplazamiento interno.
- No se modificó MySQL ni se ejecutó una prueba de carga sobre una cartera de producción. La validación automatizada de consultas se realizó en SQLite; falta comprobar rendimiento e índices con el volumen real de MySQL antes del despliegue productivo.

Casos adicionales: mora aún no registrada, deuda exclusiva de mora, reversos, cambio de semana, exclusión por sucursal/promotor y totales de 101 créditos a través de varios lotes y páginas. Las lecturas no generan movimientos.

## Descarga para Gerencia — 24 de octubre de 2026

El botón **Descargar CSV** obtiene todos los resultados de la búsqueda y filtro actuales, independientemente de la página visible. El archivo contiene fecha operativa de Cancún, filtros utilizados, número de créditos, desglose por crédito y una fila de totales. Los importes se expresan en MXN, con dos decimales, sin sumar la mora dos veces.

El endpoint `GET /api/credit/portfolio/export` usa los mismos permisos y alcance de la consulta. No incluye expedientes, CURP ni referencias bancarias. Es una consulta actual, no un cierre contable ni una reconstrucción histórica. No genera cargos ni pagos. Una descarga posterior puede cambiar si existen movimientos nuevos.

CSV con codificación UTF-8 y marca BOM, compatible con importación en Excel; delimitador coma. Si Excel no separa las columnas automáticamente, importar desde Texto/CSV eligiendo UTF-8 y coma. Los textos que comienzan como fórmulas se protegen con un apóstrofo. Hay un límite de cinco descargas por minuto y usuario. La descarga se cancela al abandonar la pantalla.

No requiere migraciones. Se verificaron filtros, acceso, filas más allá de la página visible (101 créditos), totales con mora, protección de fórmulas y descarga desde el navegador con API simulada. El rendimiento con volumen productivo en MySQL sigue pendiente.
