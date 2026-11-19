# Acceso recordado y biométrico en las apps móviles

Aplicaciones: `frontend/apps/staff-app` y `frontend/apps/client-app`.

## Uso

1. En personal, escribir el correo; en clientes, el número de cliente que ya utiliza el sistema. No se cambia el identificador de las cuentas existentes.
2. Marcar **Recordar correo** o **Recordar número de cliente** si se desea conservarlo en ese dispositivo.
3. En la app nativa, cuando haya biometría fuerte disponible, marcar **Activar acceso con Face ID**, **Touch ID**, **huella** o reconocimiento facial, según lo que informe el dispositivo.
4. Entrar con la contraseña. Solo después de que el servidor la acepte se solicita la comprobación biométrica para guardar el acceso.
5. En los siguientes inicios se puede pulsar **Entrar con…** o utilizar el formulario de contraseña. Cancelar o fallar la comprobación no abre una sesión.
6. En el acceso o en **Configuración del dispositivo → Acceso en este dispositivo**, pulsar **Eliminar acceso biométrico guardado** para retirarlo.

Desmarcar el recordatorio elimina el identificador recordado. El acceso biométrico es una preferencia independiente y se elimina con su botón. Cerrar sesión revoca la sesión del servidor y conserva la preferencia biométrica para el próximo inicio; no es lo mismo que eliminar el acceso guardado.

## Reglas y límites

- En clientes se recuerda el número de cliente; no se añade un login por correo ni se altera su identidad.
- Navegadores: solo se recuerda el identificador. No hay simulación biométrica ni persistencia de la contraseña en almacenamiento web.
- Apps nativas: se conserva identificador, contraseña y servidor en el almacenamiento seguro del sistema, sin sincronización de llavero entre dispositivos. El código solicita biometría antes de recuperar esas credenciales. La contraseña no se guarda en `localStorage`, ni en registros ni en la base offline.
- Esta integración usa un control biométrico previo a la lectura del almacén seguro; no agrega una política criptográfica de acceso por biometría al elemento del llavero. Las plantillas de cara/huella permanecen bajo control del sistema operativo.
- Cada acceso biométrico vuelve a autenticar las credenciales contra la API. No constituye un inicio offline ni una exención de los permisos y restricciones del backend.
- Contraseñas vencidas: no se activa la biometría con una clave temporal o pendiente de renovación. La caducidad de 15 días del personal sigue vigente. Después del cambio debe volver a activarse entrando con la nueva contraseña.
- Un rechazo de autenticación al usar biometría elimina el acceso guardado; un fallo de red permite reintentar. Entrar con contraseña conserva la preferencia biométrica de la misma cuenta; cambiar de cuenta retira el acceso anterior y exige activar el nuevo explícitamente. Solo se conserva una huella SHA-256 del identificador para comprobar esa correspondencia.
- Cambiar el servidor elimina el acceso biométrico y el identificador recordado del servidor anterior. Las claves se separan por aplicación y servidor.
- Android admite únicamente biometría clasificada como fuerte por el sistema; si solo hay reconocimiento débil, se mantiene la contraseña.

## Integración nativa

Dependencia fijada: `@aparajita/capacitor-biometric-auth` 10.0.0, compatible con el Capacitor 8 usado en estas apps. Se declara también en cada `src-capacitor`, para que Capacitor registre el plugin nativo. No se conserva ningún parche local del paquete.

Se agregaron `NSFaceIDUsageDescription` en las configuraciones iOS normal y Debug, y `USE_BIOMETRIC` en Android. El proyecto iOS integra el plugin por Swift Package Manager. La disponibilidad y la comprobación siguen la [API oficial del plugin](https://github.com/aparajita/capacitor-biometric-auth).

Desde la carpeta de cada app, con Node compatible y las dependencias instaladas:

```sh
pnpm install
cd src-capacitor
pnpm install
cd ..
QCLI_API_URL=https://api.tu-dominio.com/api pnpm ios:prepare
# o, para Android:
QCLI_API_URL=https://api.tu-dominio.com/api pnpm android:prepare
```

Los scripts preparan el proyecto con los assets actuales y sincronizan los plugins. Después compilar/instalar desde Xcode o Android Studio. Una instalación anterior no adquiere este plugin por recargar el contenido web: necesita una compilación nativa nueva.

## Verificación

Pruebas automatizadas de la lógica con dobles del sistema nativo:

```sh
node frontend/tests/mobile-device-access.cjs
```

Incluyen cancelación sin lectura de credenciales, biometría fuerte, alta/borrado, aislamiento por servidor y ausencia de almacenamiento de contraseñas en web. Las pruebas de interfaz `frontend/tests/mobile-device-access-ui.cjs` verifican ambos accesos con API simulada: persistencia del identificador, eliminación desde configuración, contraseña no persistida y redirección por contraseña vencida.

Las comprobaciones físicas de Face ID/Touch ID/huella (cancelación, bloqueo, falta de permiso, reenrolamiento y cambio de usuario) se deben ejecutar en iPhone y Android con la versión nativa actualizada. Las pruebas con dobles y las compilaciones no sustituyen esa validación.

## Instalación de prueba del 19 de noviembre de 2026

Se verificaron TypeScript, ESLint, builds de Quasar y los proyectos nativos para ambas apps. Los builds iOS para simulador y los builds Debug firmados para dispositivo finalizaron correctamente; se instalaron y se abrieron ambas apps en el iPhone conectado «Hp Developer». La API de la Mac respondió por la LAN. Esto no acredita todavía una prueba física de enrolamiento/rechazo biométrico: debe realizarla el titular con Face ID.

Se apartaron dos archivos duplicados idénticos `android/app/src/main/res/xml/config 2.xml` que impedían compilar Android (se conservaron copias en la caché local de herramientas). Los proyectos mantienen `config.xml`. Fue necesario limpiar productos Android generados duplicados; después ambos proyectos compilaron en Debug.
