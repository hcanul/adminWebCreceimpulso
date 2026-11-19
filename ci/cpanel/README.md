# Entrega para el hosting de pruebas

Destino: https://pruebas.creceimpulso.com. Carpeta pública: /home/creceimp/pruebas.creceimpulso.com. Administración en /admin/, clientes en /clientes/ y personal en /personal/; la API permanece en /api y los comprobantes en /comprobantes. Las tres apps se compilan en modo SPA; los instaladores nativos se generan por separado.

La carpeta privada /home/creceimp/jenkins-creceimpulso-pruebas, modo 700, contiene shared.env en modo 600 y una clave propia. El propietario guarda la contraseña MySQL directamente en ese archivo. Se creó creceimp_pruebas y un usuario exclusivo con permisos de operación, migración y respaldo sobre esa base. El diagnóstico real de PHP comprobó PHP 8.3.36, autoload de las dependencias y conexión correcta a la base vacía.

`bash ci/cpanel/package` crea el ZIP y SHA-256, con Laravel sin dependencias de desarrollo y las tres apps web. Excluye .env, almacenamiento persistente, pruebas y vendor/bin. Se comprobó el formato completo y el rechazo de archivos .env accidentales. El paquete solo se genera en Jenkins cuando pasan las verificaciones.

El parámetro DEPLOY_CPANEL solicita la publicación en este entorno, usando la credencial Jenkins `creceimpulso-pruebas-ftps`. `upload.php` verifica TLS con svgm374.serverneubox.com.mx:21, carga el ZIP y publica la petición al final mediante renombrado. Espera hasta diez minutos la confirmación del cron. Las credenciales se inyectan como variables y no se guardan en el repositorio.

El cron ejecuta cada cinco minutos:

```sh
/bin/bash /home/creceimp/jenkins-creceimpulso-pruebas/deploy.sh
```

Se instalan deploy.sh y deploy-support.php fuera de la raíz pública. El cron no expone un endpoint de ejecución. PHP tiene desactivadas proc_open y symlink; los comandos fijos de Artisan, mysqldump y enlaces se ejecutan desde Bash. Un bloqueo impide entregas simultáneas; sin request.json el cron solo verifica que está listo.

La tarea valida revisión, SHA-256, rutas del ZIP, ausencia de .env y PHP en los estáticos, URL de API de cada aplicación y configuración exclusiva de pruebas. Crea una entrega privada y conserva shared.env y storage entre versiones. Antes de migrar genera un respaldo SQL comprimido en backups y exige que el comando termine correctamente. No ejecuta seeders ni crea contraseñas de aplicación.

Durante las migraciones y el cambio de entrega se activa mantenimiento. Después comprueba Laravel /up y conexión a la base mediante su kernel local. Solo al pasar retira mantenimiento y confirma éxito a Jenkins. La confirmación local no sustituye verificar en navegador los subdirectorios, HTTPS y el acceso por la protección Imunify del hosting.

Si falla una migración o la comprobación, Jenkins recibe fallo y el mantenimiento permanece activo. No se revierte automáticamente la base: el respaldo queda disponible para recuperación supervisada. Los registros privados están en deploy.log y status.json; las peticiones terminadas quedan en requests. Una petición processing.json dejada por una interrupción abrupta requiere revisión antes de continuar. Se conservan las entregas, respaldos y archivos estáticos anteriores; los assets previos se copian para atender sesiones con HTML en caché. Revisar espacio periódicamente.

Se mantienen los bloques de configuración PHP generados por cPanel en .htaccess, php.ini y .user.ini. El backend completo y sus dependencias permanecen fuera de la raíz del dominio; en ella solo hay un controlador PHP fijo y los archivos públicos. No se modifican otros dominios ni bases.

El trabajo sigue en la rama ci/jenkins mientras se valida la integración. DEPLOY_CPANEL es falso por defecto. Cambiar la rama del trabajo y la política de publicación tras integrar los cambios en main; esta configuración no activa despliegues de producción.
