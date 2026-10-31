> Estado actualizado: [entrega móvil 29/10/2026](cierre-moviles-2026-10-29.md). El texto posterior conserva el historial de etapas anteriores.

# Acceso a la app de clientes

## Entrega actual

En `frontend/apps/client-app` se incorporaron pantallas adaptables para activar la cuenta con código presencial, definir contraseña, iniciar sesión con número de cliente y cerrar sesión. Colores guinda/gris y logotipo de la empresa. Axios centraliza la API y los errores; los servicios y Pinia gestionan la sesión, con rutas manuales protegidas.

La sesión sobrevive a la recarga de la misma pestaña. Se guarda únicamente el token en sessionStorage; no se guardan contraseñas, códigos o perfiles. Al cerrar la pestaña no se ofrece persistencia deliberada. El servidor vence el token a las ocho horas y lo revoca al cerrar sesión. Los errores de conexión conservan el token para permitir reintentar. La protección frente a acceso administrativo y la comprobación de cuenta, cliente y sucursal activos se aplican en el servidor.

No es todavía una distribución nativa para Android/iOS. Antes de empaquetar Capacitor debe integrarse almacenamiento seguro del dispositivo. El inicio muestra datos reales del titular (nombre, folio y sucursal); aún no consulta créditos, pagos o comprobantes. Recuperación de contraseña y revocación administrativa requieren una próxima entrega.

## Ejecución local

Requiere Node 22.22 o superior compatible y pnpm. Desde la carpeta `frontend`:

```sh
pnpm install
pnpm --filter client-app dev --port 9013
```

Abrir `http://localhost:9013/#/activar` o `http://localhost:9013/#/login`.

La API debe estar iniciada y tener aplicadas las migraciones de la entrega anterior (`php artisan migrate` desde `backend`). La URL predeterminada es `http://localhost:8000/api`. Para otra dirección, crear `frontend/apps/client-app/.env` con:

```dotenv
QCLI_API_URL=http://localhost:8000/api
```

El prefijo QCLI es el que reconoce esta versión de Quasar; reiniciar el servidor después de cambiarlo. Las credenciales de prueba no están incluidas ni se crean clientes sobre la base de trabajo. Primero generar un código en admin-web → Accesos de clientes para un cliente activo de una sucursal activa.

## Flujo manual

1. Verificar identidad en sucursal y entregar código y número de cliente.
2. Abrir «Activa tu cuenta»; capturar código y contraseña confirmada de al menos doce caracteres con letras y números.
3. Iniciar sesión con número de cliente y contraseña. Verificar nombre, folio y sucursal.
4. Recargar la página: permanece dentro de la sesión si sigue vigente.
5. Cerrar sesión: se elimina el token del servidor y del navegador.

## Validación

132 pruebas y 1726 aserciones correctas en SQLite. Las nuevas pruebas utilizan tokens reales de Sanctum: perfil, cierre de sesión, vencimiento, cuenta desactivada, permisos incorrectos y separación del personal. La interfaz fue probada en navegador con API simulada: activación, acceso, recarga, cierre y diseño móvil. TypeScript, ESLint y compilación SPA correctos. No equivale a prueba integrada con la base operativa o un dispositivo nativo; MySQL sigue pendiente de disponibilidad local. No se aplicaron migraciones a la base de trabajo.
