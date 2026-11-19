# Crece Impulso · Administración para macOS

## Instalar

1. Abre **CreceImpulso-Admin-macOS-arm64.dmg** en tu Mac con Apple Silicon (M1, M2, M3 o posteriores).
2. Arrastra **Crece Impulso Admin** a **Aplicaciones**.
3. Abre la aplicación desde Aplicaciones.
4. Si macOS bloquea esta compilación de pruebas por no estar notarizada, revisa **Configuración del Sistema → Privacidad y seguridad → Abrir de todos modos**, después de intentar abrirla. Esta compilación tiene firma local; no tiene notarización de Apple.

Esta entrega es para macOS con Apple Silicon. No es un instalador para Windows ni para iPhone.

## Conectar al servidor

En el inicio de sesión, pulsa **Configurar servidor** e introduce la dirección de la API:

- Backend en la misma Mac: `http://127.0.0.1:8000/api`.
- Backend en otra computadora de la red: `http://IP-DEL-SERVIDOR:8000/api`.
- Servidor publicado: `https://tu-dominio/api`.

La dirección se guarda en esta computadora y se conserva al cerrar la aplicación. Cambiarla cierra la sesión para evitar utilizar la cuenta del servidor anterior. También puedes escribir la dirección sin `/api`; la aplicación lo agrega.

El backend Laravel y MySQL deben estar funcionando. Este instalador contiene la interfaz administrativa; la base de datos permanece en el servidor. En una red local, el backend debe aceptar conexiones de otros equipos y el puerto debe estar disponible. Utiliza HTTPS para el servidor publicado.

Inicia sesión con tu cuenta habitual de Admin Web. Se aplican los mismos roles, permisos y renovación de contraseñas. No se crean usuarios ni contraseñas con la instalación. Los archivos descargados se guardan mediante el diálogo de macOS y los documentos se imprimen desde sus opciones habituales.

## Volver a compilar (desarrollo)

Desde `frontend/apps/admin-web`, con las dependencias instaladas y una versión de Node admitida por el proyecto:

```sh
pnpm desktop:dev
# Compilación de producción para el sistema y arquitectura actuales:
pnpm desktop:build
# Crear el DMG con firma local y su suma SHA-256:
sh scripts/package-desktop-macos.sh
```

El `.app` se genera en `dist/electron/Packaged/` y el DMG en `~/Downloads/CreceImpulso-Desktop-FECHA/`. El script también acepta una carpeta de salida como primer argumento.

## Verificación de esta entrega

Compilación Quasar/Electron y apertura de la aplicación verificadas. Se comprueba la configuración persistente del servidor y que Axios utilice esa dirección. La verificación de conexión usa una respuesta simulada; no equivale a una prueba de operaciones financieras contra el backend real. Antes de operar, valida el flujo completo con tu servidor de pruebas.
