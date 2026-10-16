# Avisos y confirmaciones con SweetAlert2

Integrado en admin-web: sweetalert2 11.26.25, instalado con pnpm y lockfile actualizado. No se añadieron dependencias al backend.

`src/services/alerts.service.ts` centraliza confirmación, error, información y éxito, con identidad guinda/gris y textos en español. Se usa texto plano para contenido proveniente de la API. `useAlertFeedback` conserva errores contextuales en pantalla y presenta el aviso general. Quasar permite foco externo solamente mientras SweetAlert está abierto sobre sus diálogos.

La CURP continúa verificándose en la API antes de abrir la captura. La consulta muestra SweetAlert con sucursal, estado, créditos activos y último crédito registrado, luego solicita confirmación. Sin permiso de edición se presenta información y se bloquea la entrada. Una persona nueva también requiere confirmar el inicio de captura; una solicitud enviada solo ofrece consulta. Cancelar mantiene la pantalla de búsqueda.

Se integraron avisos en login, grupos, comité, solicitudes y cierre de sesión. Envío a Gestión, cambios sin guardar y cierre de sesión usan confirmaciones asíncronas. Los errores específicos permanecen junto a los campos. La advertencia al cerrar una pestaña o recargar sigue siendo nativa: el navegador no permite sustituir `beforeunload` por un modal asíncrono personalizado.

Verificación: TypeScript, ESLint y seis pruebas de avisos/confirmaciones aprobadas. Las pruebas cubren cliente nuevo, registro existente, acceso restringido, solicitud enviada, cancelación y uso de texto plano. No se inició servidor ni se modificó la base de datos en esta iteración. Queda pendiente la inspección visual del recorrido autenticado.

Siguiente etapa propuesta: bandeja de Gestión, observaciones por campo/documento, devolución a captura con historial, nueva presentación y pase a Coordinación Comercial. La autorización comercial y el desembolso deben permanecer como acciones posteriores e independientes.

Referencia de API: https://github.com/sweetalert2/sweetalert2/blob/main/sweetalert2.d.ts
