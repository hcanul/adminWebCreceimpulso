# Integración con Jenkins

El Jenkinsfile comprueba Laravel mediante PHPUnit y las tres apps Quasar mediante tipos, ESLint y compilación SPA. Usa Node 24.22.2 y pnpm 12.10.1 en .tools, y los lockfiles de cada aplicación. Para admin-web instala también las dependencias de src-electron sin descargar el ejecutable de escritorio.

El agente bussix-linux debe disponer de PHP 8.2 o superior, Composer, intl, SQLite, mbstring, XML, cURL y ZIP. La imagen actual de BussiX necesita añadir php-intl para los contratos de Creceimpulso. Durante la preparación se construyó creceimpulso-ci-check:20261118 con esa extensión y se verificaron las pruebas en un contenedor temporal.

El trabajo debe apuntar a https://github.com/hcanul/adminWebCreceimpulso.git y a la rama ci/jenkins mientras se revisa esta integración. Después de integrarla en main, cambiar la rama del trabajo. El sondeo está configurado cada diez minutos. Cada proyecto ocupa un solo ejecutor y las apps se procesan secuencialmente.

Los paquetes de artifacts corresponden a compilaciones de CI. Antes de publicarlos se debe definir QCLI_API_URL para el destino confirmado. El Jenkinsfile todavía no contiene subida al hosting ni ejecución remota de migraciones.

El destino solicitado es pruebas.creceimpulso.com, con PHP 8.3.27 informado por el usuario. Se verificó FTPS explícito en el puerto 21 de svgm374.serverneubox.com.mx con certificado válido, alias del mismo servidor que ftp.creceimpulso.com. Las credenciales se deben guardar en Jenkins. La carpeta pública, SSL, DNS del subdominio, base de datos de pruebas y procedimiento de migraciones siguen pendientes de configurar/verificar. En la revisión, el subdominio no resolvía por DNS.

La validación se hizo sobre e7b947f, la revisión versionada de GitHub. Los cambios locales pendientes de guardar en la carpeta original se conservaron. Con intl y el fixture de documentación requerido, las pruebas de backend mostraron dos fallos de ClientPushTest relacionados con el contrato de inscripción de dispositivos. Las pruebas de contratos pasaron. Los fallos existentes deben seguir apareciendo en CI hasta corregirse.
