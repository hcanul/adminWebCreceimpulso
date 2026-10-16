# Revisión de solicitudes por Gestión

Implementado el 13 de octubre de 2026 en `frontend/apps/admin-web` y `backend`.

## Uso

1. En **Usuarios**, el Super Admin puede asignar el nuevo rol **Gestión** y una sucursal. El permiso `credit.review` también está disponible en Roles. Super Admin y Gerente General cuentan con él inicialmente; no se otorga al gerente de sucursal, coordinador de campo, promotor ni caja.
2. Recargar la sesión para actualizar sus permisos y abrir **Gestión de solicitudes** (`/#/gestion`). La bandeja permite buscar por folio/grupo, filtrar estado y paginar.
3. Abrir una solicitud y expandir cada integrante en **Revisión de Gestión**. Se muestran los datos del expediente capturado y seis comprobaciones: identidad, domicilio, referencias, visita, historial interno y documentos firmados.
4. Para cada comprobación, marcar Pendiente, Verificada o Con observaciones. Los dos últimos estados exigen una descripción de la evidencia o de la corrección necesaria. Guardar avance permite continuar después, incluso tras recargar la página.
5. **Devolver con observaciones** exige al menos una comprobación observada y una nota general. Solo los expedientes de esas personas regresan a borrador. El gerente los abre desde Clientes, consulta la CURP, confirma la edición, corrige y vuelve a enviarlos. El crédito mantiene sus montos originales.
6. Con los expedientes enviados, el gerente usa **Reenviar a Gestión** y explica qué corrigió. Las comprobaciones regresan a pendiente; se conserva la revisión anterior en el historial.
7. **Enviar a autorización** exige todas las comprobaciones verificadas, con evidencia escrita y nota general. Cierra la revisión, pero no aprueba el crédito: la autorización de Coordinación Comercial es el siguiente módulo.

## Seguridad y trazabilidad

- DDD Lite: Request, DTO tipado, Action transaccional, enum de estados y servicio de consulta.
- Estado de Gestión separado del estado financiero: `pending`, `in_review`, `returned`, `ready_for_authorization`. El préstamo sigue `pendiente` y los montos aprobados permanecen nulos.
- El permiso de Gestión amplía la consulta de créditos a su sucursal, sin conceder edición general de clientes ni administración técnica. El alcance global sigue reservado a Super Admin y Gerente General. No se supone que Gestión pueda acceder a todas las sucursales.
- El promotor puede consultar el estado de sus créditos; no recibe notas, comprobaciones ni copias de expedientes de Gestión por los endpoints de revisión ni por el detalle general.
- Bloqueo de grupo y solicitud dentro de transacción. Control de versión optimista: una revisión desactualizada devuelve 409 y debe recargarse, sin sobrescribir decisiones.
- Historial por movimiento con UUID, sucursal, usuario, nombre del usuario al decidir, fecha, versión, acción, notas, comprobaciones y copia de los expedientes vistos. La devolución conserva además una revisión del expediente antes de su próxima edición.
- Solicitudes aprobadas, entregadas o históricas sin snapshot no pueden entrar en este flujo. Se bloquean operaciones sobre grupos/sucursales inactivos.
- El servidor exige exactamente seis comprobaciones de cada participante: rechaza faltantes, duplicados y clientes ajenos.
- Sin datos de expedientes en almacenamiento persistente del navegador. Los cambios guardados se recuperan desde la API. Se advierte antes de abandonar cambios sin guardar.

## Alcance pendiente

Las comprobaciones son declaraciones del personal: no hay consulta a RENAPO, carga de archivos ni validación automática de firmas. Los documentos se cotejan físicamente y se describen en las notas. La carga documental y los PDF se desarrollarán por separado.

Esta entrega permite corregir datos del expediente. No modifica importes, tasa, participantes ni snapshot de la solicitud financiera; una futura corrección de condiciones requerirá un flujo de versiones propio. El grupo queda ocupado mientras su solicitud financiera permanezca pendiente.

El módulo de autorización comercial debe exigir `ready_for_authorization`, volver a comprobar elegibilidad vigente y registrar montos aprobados, medios de entrega y de pago por separado. No debe habilitar desembolsos solo por completar esta revisión.

## Migración y pruebas

Aplicada en MySQL local: `2026_10_13_010000_add_credit_management_review.php`. Agrega tres campos de revisión, historial y permisos; conserva los registros existentes. No se iniciaron servicios ni se insertaron clientes o créditos de prueba reales. En otro entorno se debe ejecutar `php artisan migrate` desde `backend`, revisando antes sus migraciones pendientes. La reversión del historial requiere un plan explícito.

- Suite aislada SQLite en memoria: pruebas de captura, administración, crédito y Gestión; revisa transiciones, evidencia, permisos, sucursales, concurrencia optimista, historial y montos inmutables.
- TypeScript, ESLint y formato de los archivos modificados.
- Prueba de navegador con API simulada: bandeja, devolución, recarga, reenvío, reinicio de comprobaciones, envío a autorización y recarga final. Vista móvil de 390 px sin desbordamiento horizontal; tabla financiera con desplazamiento interno.
- Estas pruebas no sustituyen una prueba de concurrencia real de múltiples conexiones MySQL ni una validación operativa con documentación real. Todos los procesos de prueba terminan al finalizar.


Actualización del 14 de octubre: la autorización comercial ya está implementada. Consulta [Autorización comercial](autorizacion-comercial.md). El desembolso sigue pendiente.
