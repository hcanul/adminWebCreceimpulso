# Entrega para el hosting de pruebas

Destino: `https://pruebas.creceimpulso.com`. Carpeta pública confirmada en cPanel: `/home/creceimp/pruebas.creceimpulso.com`.

La carpeta privada de preparación es `/home/creceimp/jenkins-creceimpulso-pruebas`, modo 700. Contiene `shared.env` en modo 600, con clave de aplicación independiente y configuración de pruebas; falta que el propietario establezca la contraseña del usuario MySQL. No se debe publicar esta carpeta ni subir el backend completo a la raíz del dominio.

Se verificó mediante cron que `/usr/local/bin/php` utiliza PHP 8.3.36 y tiene intl, pdo_mysql y zip. `platform-probe.php` es un diagnóstico privado de CLI. La tarea de diagnóstico debe sustituirse o eliminarse al instalar la tarea definitiva de despliegue.

`bash ci/cpanel/package` crea `artifacts/creceimpulso-cpanel.zip` y su SHA-256. Incluye Laravel con dependencias sin desarrollo y las tres compilaciones web, excluyendo archivos .env, almacenamiento persistente, pruebas y ejecutables de vendor/bin. El trabajo de Jenkins solo genera este paquete si las verificaciones anteriores finalizaron correctamente. No contiene comandos de publicación ni migraciones: esos pasos todavía no están activados.

El backend debe permanecer fuera de la carpeta pública. La publicación necesita definir las rutas de las aplicaciones web y dirigir las solicitudes de API y comprobantes al controlador público de Laravel. Los archivos de configuración generados por cPanel (.htaccess, php.ini y .user.ini) deben conservarse. El almacenamiento y shared.env deben persistir entre entregas.

Antes de activar CD quedan por verificar: permisos del usuario exclusivamente sobre creceimp_pruebas; contraseña en shared.env; instalación y migraciones con respaldo; configuración HTTPS de la aplicación y comprobación HTTP sin la pantalla de protección de Imunify; credencial FTPS guardada en Jenkins y estrategia de recuperación. Los dos fallos existentes de ClientPushTest mantienen bloqueada la entrega automática.

El empaquetado completo se probó en el agente de Jenkins: ZIP de 7,761 entradas, autoloader y tres index.html presentes, sin archivos .env, dependencias de desarrollo, almacenamiento persistente ni ejecutables de vendor/bin. También se comprobó que el empaquetador rechaza un .env introducido accidentalmente. Esta verificación del formato no representa una publicación del sistema.
