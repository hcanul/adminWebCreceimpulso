# Dashboard administrativo

Implementado en `frontend/apps/admin-web/src/pages/IndexPage.vue` y su layout existente.

Incluye identidad guinda/gris, saludo de usuario autenticado, fecha de Quintana Roo, panel de bienvenida, cuatro indicadores sin datos ficticios, recorrido interactivo del crédito, bandeja pendiente y anuncio del módulo de grupos. Menú lateral Quasar desplegable en móvil y visible inicialmente en escritorio. La cuenta y el cierre de sesión permanecen accesibles en la cabecera.

Indicadores: grupos, solicitudes en revisión, créditos activos y cobros del día. Muestran ausencia de datos, no cero. No se inventó un endpoint ni se agregaron peticiones periódicas: la API de indicadores se implementará con los módulos y autorización correspondientes.

Los módulos futuros aparecen deshabilitados y etiquetados Próximamente. Sucursales/tasas visibles como módulos previstos para Super Admin y Gerente General; Usuarios/permisos solo para Super Admin. Esto es presentación, no reemplaza las Policies futuras del servidor. Ningún permiso operativo nuevo fue concedido.

El recorrido conserva grupo → integrantes y elegibilidad → revisión de Gestión → autorización comercial y entrega en sucursal como operaciones separadas. El máximo de integrantes sigue pendiente; no se impuso una restricción de veinte.

Verificación: TypeScript y ESLint sobre los dos componentes. Sin servidores, contenedores ni procesos permanentes. Falta inspección visual en navegador con sesión real. No se alteró el flujo de autenticación ni se implementaron operaciones financieras en esta entrega.
