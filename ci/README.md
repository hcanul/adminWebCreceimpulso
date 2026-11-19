# Integración con Jenkins

El Jenkinsfile comprueba Laravel mediante PHPUnit y las tres apps Quasar mediante tipos, ESLint y compilación SPA. Usa Node 24.22.2 y pnpm 12.10.1 en .tools, y los lockfiles de cada aplicación. Para admin-web instala también las dependencias de src-electron sin descargar el ejecutable de escritorio. Para client-app y staff-app instala las dependencias de src-capacitor antes de compilar. Los fallos de PHPUnit mantienen el resultado final en FAILURE y permiten verificar también los frontends.

El agente bussix-linux debe disponer de PHP 8.2 o superior, Composer, intl, SQLite, mbstring, XML, cURL y ZIP. El agente compartido ya utiliza creceimpulso-ci-check:20261118 con intl; se conserva el contenedor anterior detenido para recuperación.

El trabajo debe apuntar a https://github.com/hcanul/adminWebCreceimpulso.git y a la rama ci/jenkins mientras se revisa esta integración. Después de integrarla en main, cambiar la rama del trabajo. El sondeo está configurado cada diez minutos. Cada proyecto ocupa un solo ejecutor y las apps se procesan secuencialmente.

Las compilaciones usan QCLI_API_URL=https://pruebas.creceimpulso.com/api. Los artefactos web se conservan para revisión; el paquete cPanel requiere que las verificaciones pasen. El Jenkinsfile todavía no contiene subida al hosting ni ejecución remota de migraciones. Véase ci/cpanel/README.md.

El destino solicitado es pruebas.creceimpulso.com, con PHP 8.3.27 informado por el usuario. Se verificó FTPS explícito en el puerto 21 de svgm374.serverneubox.com.mx con certificado válido, alias del mismo servidor que ftp.creceimpulso.com. Las credenciales se deben guardar en Jenkins. El dominio ya resuelve al hosting y su carpeta pública es /home/creceimp/pruebas.creceimpulso.com. Se creó la base vacía creceimp_pruebas. Un diagnóstico privado mediante cron verificó PHP 8.3.36 e intl, pdo_mysql y zip. La configuración de la base, publicación, migraciones y comprobación HTTP de la aplicación siguen pendientes.

La validación se hizo sobre e7b947f, la revisión versionada de GitHub. Los cambios locales pendientes de guardar en la carpeta original se conservaron. Con intl y el fixture de documentación requerido, las pruebas de backend mostraron dos fallos de ClientPushTest relacionados con el contrato de inscripción de dispositivos. Las pruebas de contratos pasaron. Los fallos existentes deben seguir apareciendo en CI hasta corregirse.
