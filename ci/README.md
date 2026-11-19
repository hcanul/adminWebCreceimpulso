# CI/CD web de Creceimpulso

Jenkins consulta main cada diez minutos. El flujo valida el backend Laravel con PHPUnit y el frontend administrativo admin-web con TypeScript, ESLint y compilación SPA. Al pasar las verificaciones publica automáticamente en pruebas.creceimpulso.com, con respaldo MySQL, migraciones y comprobación local mediante cron privado cada cinco minutos. DEPLOY_CPANEL permite forzar una publicación manual.

El alcance es exclusivamente backend y frontend web. No instala dependencias de Electron, no genera instaladores y no compila client-app ni staff-app. La comprobación TypeScript web usa tsconfig.web.json y mantiene los tipos generados por Quasar, excluyendo las fuentes nativas. CI_WEB_ONLY aplica ese mismo alcance a los verificadores de la compilación.

El paquete contiene backend con dependencias sin desarrollo y web/admin-web. Los estáticos móviles previamente publicados se conservan sin recompilar en el hosting; no son artefactos de esta entrega. La aplicación administrativa usa /admin/ y la API /api. Véase ci/cpanel/README.md para operación y recuperación.

Las credenciales se guardan en Jenkins y en shared.env fuera de la carpeta pública; no se incluyen en los paquetes. Se conservan los cambios pendientes de la carpeta de trabajo original. Producción no está configurada.
