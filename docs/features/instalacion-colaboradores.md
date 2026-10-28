# Crece Colaboradores — versión de pruebas

Incluye acceso del personal, grupos asignados, integrantes y captura/edición de expedientes con validación de CURP. Requiere conexión; no incluye sincronización offline ni cobros de campo. Es independiente de la app de clientes.

## Android
Copia Crece-Colaboradores-Android.apk al dispositivo y ábrelo desde Archivos. Si Android lo solicita, autoriza instalar aplicaciones desde esa fuente. APK firmado para desarrollo; Android 7 o posterior y WebView actualizado. Compilación y firma verificadas; pendiente probar en Android físico.

## iPhone
Crece-Colaboradores-iPhone.ipa tiene firma de desarrollo y autoriza el iPhone registrado Hp Developer. No se instala tocando el archivo: utiliza Xcode con el teléfono conectado o ejecuta el proyecto App.xcodeproj de staff-app. La firma vence el 3 de noviembre de 2026 a las 20:10, hora de Cancún; después requiere recompilar y reinstalar. No es un paquete de distribución general.

## Servidor y acceso
En Configuración del dispositivo guarda http://192.168.100.118:8000/api (o la IP actual de tu Mac). Teléfono y Mac deben estar en la misma Wi-Fi. Permite acceso a red local si iOS lo solicita. MySQL debe estar activo y Laravel debe escuchar en la red local, no solo 127.0.0.1. Desde la carpeta backend puedes ejecutar:

php artisan serve --host=0.0.0.0 --port=8000

Si el puerto está ocupado, detén primero la instancia anterior o usa otro puerto y actualiza la configuración del teléfono. Usa correo y contraseña de un usuario del personal activo con permisos y promotor asignado; no uses el folio de clientes. Las cuentas QA de las pruebas están desactivadas. La configuración del servidor persiste; el acceso puede requerir iniciar sesión nuevamente al cerrar la app. Esta entrega no implementa almacenamiento seguro nativo del token entre reinicios.
