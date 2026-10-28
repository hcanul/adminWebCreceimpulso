# Crece Impulso — Configuración del dispositivo

Actualización del 26 de octubre de 2026.

1. Abre la app y pulsa **Configuración del dispositivo** en la pantalla de acceso. Dentro de la cuenta también está disponible el engrane.
2. Captura la IP con puerto, por ejemplo `192.168.100.118:8000`, o un dominio como `https://api.miempresa.com`.
3. Pulsa **Probar conexión**. El servidor debe responder como la API de Crece Impulso.
4. Pulsa **Guardar servidor** y confirma la dirección. La app cerrará la sesión anterior y volverá al acceso.
5. Inicia sesión en el servidor elegido. La configuración permanece después de cerrar y abrir la app.

Se agrega `/api` automáticamente si falta. Para una API bajo otra carpeta puedes usar `https://dominio.com/sistema/api`. No escribas localhost: en el teléfono apuntaría al propio teléfono. Para usar la API de tu Mac, ambos equipos deben estar en la misma red, el servidor debe escuchar en 0.0.0.0:8000 y MySQL debe estar activo.

Los dominios públicos requieren HTTPS. HTTP para IP privadas y nombres .local se admite en estos instaladores de desarrollo. La versión de distribución necesitará HTTPS. La prueba de conexión se hace sin enviar el token de la sesión existente. Verifica el dominio con tu administrador: una respuesta correcta no certifica por sí sola la identidad de su propietario.

Este apartado configura el dispositivo; no otorga roles administrativos ni modifica datos del servidor. El botón **Usar dirección inicial** recupera la dirección incorporada al compilar; después debes probarla y guardarla. Reinstalar eliminando los datos de la app puede borrar este ajuste.

## Instalar Android

Abre `CreceImpulso-Android.apk` en el dispositivo y autoriza la instalación desde la aplicación de archivos si Android lo solicita. Instala como actualización para conservar los ajustes. APK firmado para desarrollo; pendiente de prueba en Android físico.

## Instalar iPhone

La actualización ya se instaló y abrió en el iPhone conectado Hp Developer. El IPA no es un instalador universal: únicamente funciona en los dispositivos incluidos en su perfil de desarrollo. Para otro iPhone, conéctalo a Xcode, selecciónalo como destino y compila con firma automática y tu Personal Team. El iPhone debe permitir Modo de desarrollador y confiar en el certificado. La firma temporal deberá renovarse al vencer. Cambiar la dirección del servidor no resuelve restricciones de firma o instalación.

## Demostración

El historial financiero de ejemplo sigue siendo ficticio. La cuenta de prueba solo existe en la base donde fue creada; cambiar de servidor no copia cuentas ni contraseñas.

## Verificación

TypeScript y ESLint correctos. Compilación iOS y Android correcta, firmas verificadas. Prueba de interfaz móvil: direcciones inválidas rechazadas, prueba sin token, borrado de sesión al guardar, persistencia y uso de la nueva URL por Axios después de recargar. No se dejó un servidor adicional de pruebas de interfaz activo.

Firma iOS de este paquete: vence 2026-11-02T01:30:09 UTC. Dispositivos autorizados: 2.


## Corrección del guardado — 26 de octubre

Guardar ya no requiere una prueba de conexión exitosa. La dirección se valida, se persiste y se verifica su lectura antes de cerrar la sesión y recargar. Se muestra la dirección actualmente guardada. Probar conexión diferencia errores 500 (API/base de datos), 404 (ruta), 429 y fallas de red. El acceso utiliza la misma prueba sin token.

En la red actual, escribir `192.168.1.67:8000` o `http://192.168.1.67:8000/api`. Una IP sin puerto usa el puerto estándar 80; no se agrega 8000 silenciosamente porque otros servidores pueden usar otro puerto. Se comprobó que la API respondía 500 por MySQL detenido y volvió a 200 tras iniciarlo. Mantener la base y la API activas para iniciar sesión.

Prueba de regresión: guardar después de HTTP 500, recargar, recuperar el valor, borrar el token previo y usar la URL guardada en Axios. TypeScript y ESLint correctos.
