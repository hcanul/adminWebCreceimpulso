# Crece Impulso · Admin para Windows

## Instalación

Esta entrega es para Windows de 64 bits, en equipos Intel o AMD. Utiliza Windows 10 u 11 actualizado.

1. Copia el archivo **CreceImpulso-Admin-0.0.1-Windows-x64-Setup.exe** al equipo Windows.
2. Ejecuta el archivo y sigue el asistente en español. Puedes instalarla para el usuario actual y elegir la carpeta de destino; instalarla para todos los usuarios puede solicitar permisos de administrador.
3. Abre **Crece Impulso Admin** desde el menú Inicio o el acceso directo del escritorio.
4. Si Windows muestra una advertencia de editor desconocido, esta compilación de pruebas no tiene certificado de firma digital. Verifica que corresponda al archivo entregado antes de continuar. SmartScreen puede ofrecer **Más información → Ejecutar de todas formas**; las políticas de una empresa pueden requerir autorización de su administrador.

Para comprobar la integridad desde PowerShell, en la carpeta del instalador:

```powershell
Get-FileHash .\CreceImpulso-Admin-0.0.1-Windows-x64-Setup.exe -Algorithm SHA256
```

Compara el resultado con **SHA256.txt**. La suma comprueba que el archivo coincide con la entrega; no sustituye una firma digital.

## Conexión y acceso

1. En la pantalla de inicio de sesión, pulsa **Configurar servidor**.
2. Escribe la dirección completa de la API Laravel, por ejemplo `https://tu-dominio/api`. Si el backend se ejecuta en otra computadora de la red, usa `http://IP-DEL-SERVIDOR:8000/api`, sustituyendo el texto por su IP real.
3. Guarda e inicia sesión con tu cuenta habitual de Admin Web.

La dirección se conserva al cerrar y abrir la aplicación. Al cambiar de servidor se cierra la sesión anterior. Si omites `/api`, la aplicación lo añade.

**Importante:** `localhost` y `127.0.0.1` se refieren al equipo Windows donde se ejecuta la app. Si Laravel está en tu Mac, utiliza la IP o el dominio de esa Mac, con el backend disponible para conexiones de red. El puerto debe estar permitido por la configuración de red del servidor. Para un servidor publicado, utiliza HTTPS.

El instalador contiene la interfaz administrativa. Laravel y MySQL permanecen en el servidor y deben estar funcionando. Se conservan los roles, permisos, formularios, reportes y políticas de contraseñas de Admin Web. No se crean cuentas con la instalación.

Los documentos se imprimen desde sus opciones habituales; puedes elegir una impresora disponible en Windows o **Microsoft Print to PDF**. Las descargas muestran el diálogo para guardar archivos.

## Actualizar o desinstalar

Cierra la aplicación antes de instalar otra versión. Puedes desinstalarla desde **Configuración → Aplicaciones → Aplicaciones instaladas**. Los datos de negocio permanecen en el servidor; no se eliminan por desinstalar la interfaz.

## Alcance de la verificación

El instalador se genera desde macOS mediante Quasar, Electron y NSIS. Se comprueba su compilación y estructura. No se ha ejecutado una instalación en Windows ni una prueba de impresión con una impresora Windows. La prueba final debe realizarse en el equipo destino conectado al backend de pruebas.

## Volver a compilar

Desde `frontend/apps/admin-web`, con las dependencias instaladas y Node compatible:

```sh
pnpm desktop:build:windows
```

El instalador se genera en `dist/electron/Packaged/`. No se publica automáticamente ni modifica la base de datos. El comando compila la interfaz con Quasar y ejecuta electron-builder 26.18.0 desde el caché de npm, con NSIS 3.12 fijado en la configuración. Esto evita las rutas profundas de pnpm que pueden hacer fallar NSIS. Necesita npm y conexión a internet para descargar las herramientas durante la primera compilación.
