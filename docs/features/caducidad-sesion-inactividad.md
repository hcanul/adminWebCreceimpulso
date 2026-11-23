# Sesiones: 30 minutos de inactividad

Aplica a Admin Web, app de clientes y app del personal, incluida la distribución Electron de Admin Web cuando se vuelva a compilar.

## Comportamiento

- La sesión termina al llegar a 30 minutos desde la última interacción del usuario: teclado, puntero, rueda, toque o desplazamiento táctil.
- Las consultas automáticas, notificaciones, recargas y cambios de visibilidad no renuevan ese tiempo. Una interacción posterior al vencimiento tampoco reactiva la sesión.
- Al volver de segundo plano, el temporizador compara la hora actual con la última actividad; no depende de que el sistema móvil haya ejecutado intervalos mientras la app estaba suspendida.
- Se elimina el token local, se descartan los datos en memoria al recargar y se muestra el acceso con el aviso del motivo. Las pestañas del mismo navegador y servidor que compartan el token comparten la marca de actividad y cierre.
- El correo/número de cliente recordado y el acceso biométrico son preferencias del dispositivo: se conservan para volver a ingresar. El nuevo login vuelve a validar la cuenta contra la API.
- La vigencia máxima del token de ocho horas y la renovación de contraseña del personal cada 15 días siguen vigentes; la inactividad no las sustituye.
- En personal, el espacio offline desbloqueado también se bloquea después de 30 minutos sin interacción. Se conserva la cola cifrada que ya se guardó; las ediciones sin guardar se descartan al bloquear o recargar. Después se requiere la clave local para abrirlo.

## Backend

Migración: `2026_11_19_010000_add_token_idle_activity.php`, columna nullable `personal_access_tokens.last_activity_at`.

`RequireActiveSession` se ejecuta después de Sanctum en todos los grupos de API autenticada. Comprueba la actividad de cada token persistido y elimina únicamente el token inactivo al recibir una petición posterior al vencimiento. Si nunca hubo actividad usa la fecha de creación; no usa `last_used_at`, que también cambia por consultas automáticas. No requiere cron.

`POST /api/session/activity`, autenticado y limitado a 120 peticiones por minuto, recibe `idle_seconds` entre 0 y 1799. Solo este endpoint renueva la marca; acepta cuánto tiempo pasó desde la interacción, sin convertir una petición retrasada en una actividad nueva. El vencimiento se comprueba antes de actualizar y no se permite resucitar un token. Las comprobaciones y actualizaciones usan transacciones y bloqueo de la fila del token.

Las apps envían como máximo una actualización por minuto cuando hay actividad nueva, manteniendo la fecha real del último gesto. Una sesión vencida recibe HTTP 401, código `SESSION_IDLE_EXPIRED`. Las sesiones que ya estaban abiertas antes de la actualización pueden requerir un nuevo login según su antigüedad.

Los tokens vencidos se eliminan de la base cuando se vuelven a presentar; no hay un proceso programado de limpieza. El cierre por inactividad conserva las preferencias de push y no equivale a desregistrar todos los dispositivos del cliente.

## Frontend

El temporizador compartido vive en `frontend/packages/shared/session-inactivity.ts`. Las integraciones usan el servicio Axios y el almacén de sesión de cada app. En el almacenamiento del temporizador solo se escriben el ID público del token, servidor, fecha y estado de cierre; nunca su secreto ni una contraseña. Los móviles mantienen el token en su almacenamiento seguro nativo.

Para las apps existentes, se inicializa una marca local si falta. Esto no prolonga un token antiguo en el backend, que sigue comprobando su fecha de creación. La API conserva la autoridad sobre el vencimiento; la marca del navegador no sustituye la autenticación.

## Activación

En local se aplicó únicamente la nueva migración a la base MySQL `creceimpulso_db`.

Para otro entorno, publicar el backend con su migración y las tres interfaces de forma coordinada:

```sh
# Desde backend
php artisan migrate
```

Después generar las distribuciones de cada app con sus scripts habituales. Las versiones móviles ya instaladas necesitan recompilarse y actualizarse para incorporar el temporizador local y el endpoint de actividad; la validación del backend por sí sola no añade la interfaz a binarios anteriores. Lo mismo aplica al instalador Electron. No se modifica el hosting de producción desde este desarrollo.

## Comprobaciones

- Backend: suite completa de 210 pruebas y 2,481 comprobaciones en SQLite aislado, incluyendo tokens reales de personal y cliente, límite exacto de 30 minutos, actividad explícita, polling sin renovación, otros tokens conservados y rechazo de resurrección.
- Lógica de frontend: `node frontend/tests/session-inactivity.cjs`, con reloj controlado, varias instancias/pestañas, eventos sintéticos ignorados y regreso de segundo plano.
- Interfaz: `frontend/tests/session-inactivity-ui.cjs` sirve los builds SPA de las tres apps y verifica pantallas protegidas, renovación por clic y redirección al vencer, con API simulada y reloj acelerado de navegador.
- TypeScript, ESLint y builds SPA de las tres apps.

Durante la prueba se corrigió además la pantalla de renovación de contraseña del cliente: su contenedor ahora es `main`, coherente con las demás rutas sin `QLayout`, para evitar que quedara en blanco.

El temporizador no guarda automáticamente formularios a medio capturar. Antes de dejar de trabajar, guardar los cambios que se quieran conservar.
