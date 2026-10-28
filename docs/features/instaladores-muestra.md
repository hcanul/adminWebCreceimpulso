# Instaladores de muestra

## Antes de empezar

Estas son muestras de desarrollo para demostraciones supervisadas, no una publicación en las tiendas. Los importes, pagos y QR del ejemplo son ficticios. La app necesita conexión; esta versión no funciona fuera de la red local de tu Mac.

## 1. Preparar la conexión

Conecta la Mac y los teléfonos a la misma red Wi-Fi, sin aislamiento entre dispositivos. Mantén la Mac encendida, MySQL disponible y el backend escuchando en 0.0.0.0:8000. La dirección incorporada es http://192.168.100.118:8000/api. Si cambia la IP de la Mac, hay que recompilar las apps con la nueva dirección. Abre la app y pulsa «Probar conexión» antes de iniciar sesión.

## 2. Instalar en Android

Copia CreceImpulso-DEMO-Android.apk al teléfono mediante USB, Drive o un enlace privado. Abre el APK desde Archivos o Descargas. Si Android lo solicita, permite temporalmente «Instalar aplicaciones desconocidas» para esa aplicación. Pulsa Instalar y después Abrir. Puedes retirar ese permiso al terminar. Se requiere Android 7.0 o posterior y un Android System WebView actualizado. Si ya existe una versión con otra firma, no se podrá actualizar directamente; conserva los datos necesarios antes de desinstalarla. Este APK está firmado para desarrollo y aún debe comprobarse en un Android físico.

## 3. Instalar en tu iPhone registrado

CreceImpulso-DEMO-iPhone-registrado.ipa está firmado únicamente para tu iPhone registrado (iPhone 12 Pro Max, Hp Developer). Conecta y desbloquea el teléfono. En Xcode abre Window → Devices and Simulators, selecciona tu iPhone y usa + en Installed Apps para seleccionar el IPA, si esa opción está disponible. También puedes instalar la app desde el proyecto: abre App.xcodeproj, elige el target App, tu Personal Team en Signing & Capabilities, firma automática, tu iPhone como destino y pulsa Run. Activa Modo de desarrollador y confía en el desarrollador en Ajustes cuando el dispositivo lo solicite. No se instala simplemente tocando el IPA recibido por AirDrop.

## 4. Vigencia y otros iPhone

La firma de este paquete vence el 1 de noviembre de 2026 a las 09:06, hora de Cancún. Después recompila e instala desde Xcode. Este IPA no autoriza otros iPhone. Para una muestra presencial en otro dispositivo, habrá que conectarlo a Xcode y firmar una compilación para ese equipo, sujeto a los límites de la cuenta gratuita. Para distribuir a clientes mediante TestFlight se necesita Apple Developer Program y preparar la distribución correspondiente; no está configurada en esta entrega.

## 6. Recorrido recomendado

Mi crédito: capital $10,000, interés pactado $2,800, 16 cuotas de $800, garantía $1,000 y entrega neta $9,000.
Calendario: consulta las 16 semanas, cuotas pagadas, parciales, vencidas y futuras.
Mis pagos: abre los tres comprobantes ficticios de efectivo y depósito; prueba el QR desde otro teléfono en la misma red. El enlace firmado identifica una muestra, no un comprobante financiero real.
Ayuda: consulta instrucciones para pagar y recibir atención.
Cierra sesión al terminar. El historial mostrado corresponde a pagos del crédito de ejemplo; no representa una integración completa de la cartera real.

## 7. Si algo falla

Conexión fallida: revisa Wi-Fi, IP de la Mac, backend, firewall y permisos de red local del iPhone. No uses localhost para conectar desde el celular.
Acceso rechazado con conexión correcta: revisa el folio y la contraseña exactos; verifica que estás usando la misma base local de demostración.
App iPhone no disponible: revisa confianza del desarrollador, Modo de desarrollador y vencimiento de la firma.
QR no abre: el dispositivo que lo escanea también necesita alcanzar la API de tu Mac.

## Qué contiene esta entrega

APK Android de desarrollo, IPA iOS de desarrollo restringido al dispositivo registrado, esta guía y un archivo SHA256SUMS.txt para verificar integridad. No incluye un servidor público, publicación en tiendas ni distribución general de iOS. Para muestras remotas será necesario preparar una API de demostración por HTTPS.

## Regenerar Android

Desde `frontend/apps/client-app`, con Node compatible con Quasar, JDK 21 en `JAVA_HOME` y SDK Android configurado en `src-capacitor/android/local.properties`:

```sh
QCLI_API_URL=http://192.168.100.118:8000/api pnpm android:apk
```

El script prepara los recursos para Android y genera `src-capacitor/android/app/build/outputs/apk/debug/app-debug.apk`. HTTP y contenido mixto se permiten únicamente en compilaciones de depuración. Release requiere una API HTTPS y firma de distribución propia. El SDK usa plataforma 36 y Gradle descarga las herramientas requeridas cuando sus licencias ya están aceptadas. No subir keystores, certificados privados ni `local.properties` al repositorio.
