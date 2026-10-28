# Instalación de prueba en iPhone

## Preparación realizada

Capacitor 8 y proyecto iOS con Swift Package Manager, identificador `com.creceimpulso.clientes`. Abrir `frontend/apps/client-app/src-capacitor/ios/App/App.xcodeproj` en Xcode. Las pantallas están compiladas e incorporadas a la app; no necesitan el servidor web del puerto 9013. La API sí debe seguir encendida y accesible.

La compilación actual usa `http://192.168.100.118:8000/api`. HTTP se permite únicamente en la configuración Debug de iOS, para pruebas locales. Release conserva la política normal de transporte. No publicar esta compilación de pruebas ni utilizar información real en HTTP. El almacenamiento de sesión sigue siendo sessionStorage del WebView; no se ha implementado persistencia en Keychain.

## Xcode y cuenta

El 25 de octubre se encontró Xcode 27.1 en `/Applications/Xcode.app`, pero la primera compilación falló al cargar `IDESimulatorFoundation` por una incompatibilidad con `DVTDownloads`. Xcode solicita completar `xcodebuild -runFirstLaunch`. Ese error inicial ya no se reproduce en la revisión posterior; Xcode resuelve los paquetes del proyecto correctamente.

1. Abrir Xcode, aceptar los acuerdos y terminar la instalación de componentes iniciales.
2. En Xcode → Settings → Locations → Command Line Tools seleccionar el Xcode instalado. La terminal todavía tenía seleccionadas las herramientas independientes; durante la verificación se utilizó DEVELOPER_DIR sin modificar la selección global.
3. Xcode → Settings → Apple Accounts: iniciar sesión con una cuenta Apple personal. Una cuenta gratuita permite pruebas en dispositivo; no se necesita contratar la membresía para esta prueba.
4. Conectar y desbloquear el iPhone, aceptar Confiar. Activar Ajustes → Privacidad y seguridad → Modo desarrollador cuando esté disponible y reiniciar si lo solicita.
5. Abrir App.xcodeproj. Seleccionar el proyecto App → target App → Signing & Capabilities: Automatically manage signing y el Personal Team de la cuenta.
6. Elegir el iPhone como destino, configuración Debug, y pulsar Run. Autorizar acceso a la red local cuando lo solicite.

Si no aparece el teléfono, revisar Window → Devices and Simulators. No compartir aquí contraseñas de Apple; introducirlas directamente en Xcode.

## API local

Desde `backend`:

```sh
php artisan serve --host=0.0.0.0 --port=8000
```

MySQL debe estar encendido y las migraciones de cuentas cliente aplicadas. Mac e iPhone deben compartir red Wi-Fi. Generar el código de activación en admin-web y usar un cliente de prueba.

## Actualizar las pantallas o la dirección de la API

Desde `frontend/apps/client-app`, con Node compatible disponible:

```sh
QCLI_API_URL=http://192.168.100.118:8000/api pnpm ios:prepare
pnpm ios:open
```

El comando valida la dirección, compila las pantallas y sincroniza iOS. No firma ni instala la aplicación. Si cambia la IP de la Mac, cambiarla en el comando y recompilar. Para distribución futura se requiere API HTTPS y completar recuperación de cuentas, almacenamiento seguro, pruebas físicas y los módulos pendientes.

Referencia: https://developer.apple.com/help/account/basics/about-your-developer-account/


## Revisión del teléfono conectado

Xcode detecta el iPhone 12 Pro Max conectado por cable y vinculado. La consulta del dispositivo confirma que Modo desarrollador está desactivado. No hay identidades de firma válidas en el llavero ni DEVELOPMENT_TEAM configurado en el proyecto. Es necesario que el propietario habilite Modo desarrollador en el iPhone e inicie sesión en Xcode, seleccionando su Personal Team en Signing & Capabilities. No se ha instalado la aplicación.

La compilación nativa Debug para iOS arm64 terminó correctamente con CODE_SIGNING_ALLOWED=NO. Artefacto local: /tmp/crece-client-ios-build/Build/Products/Debug-iphoneos/App.app. Al no estar firmado, todavía no es instalable en el teléfono.


## Instalación física realizada

La compilación Debug firmada se instaló correctamente en el iPhone 12 Pro Max el 25 de octubre. La apertura fue rechazada por iOS con un mensaje de seguridad que incluye perfil no confiado explícitamente. Pendiente autorizar el perfil de desarrollador en Ajustes → General → VPN y gestión de dispositivos y volver a abrir Crece Impulso. La API local responde en el puerto 8000. No se ha confirmado todavía el inicio de sesión desde el teléfono.

## Ejemplo de conexión y cuenta ficticia

La pantalla de acceso incluye «Probar conexión». Comprueba una respuesta nueva de `/api/client/connection` mediante un identificador aleatorio y muestra el tiempo de respuesta; no comprueba la base de datos ni sustituye el inicio de sesión. El endpoint no revela información personal, versiones o configuración interna y limita los intentos.

Se aplicó únicamente la migración `2026_10_24_020000_create_client_access.php` en la base local para habilitar las cuentas. Se creó una sucursal nueva `DEMO APP — Solo pruebas` y el cliente ficticio `CLI-S009-000001`, sin crédito ni pagos. La contraseña se entregó en la conversación y no se incorpora al código ni a la documentación. No se modificaron clientes existentes.

Para crear otra cuenta ficticia local: `php artisan client-app:create-demo`. El comando crea registros nuevos y muestra una contraseña aleatoria; no restablece cuentas existentes y rechaza entornos distintos de local/testing y bases remotas. El registro DEMO utiliza una CURP artificial identificada por prefijo DEMO para no suplantar una identidad real.

Validación integrada: respuesta de conexión, login, consulta del perfil propio y logout correctos contra la API y MySQL local con la cuenta ficticia. La comprobación desde el iPhone queda sujeta a acceso a red local y autorización del desarrollador en iOS.
