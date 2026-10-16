# Login administrativo — primera implementación

Aplicación existente: `frontend/apps/admin-web` (no `apps/web-admin`).

## Responsabilidades

- `src/services/http.ts`: instancia Axios única, URL, timeout y mensajes de error.
- `src/boot/axios.ts`: integra Axios con Quasar, adjunta token y elimina sesión cuando la API rechaza el token actual. Mantiene export `api` para compatibilidad.
- `src/services/auth.service.ts`: contratos tipados y llamadas login, me, logout.
- `src/stores/auth.store.ts`: usuario/roles en memoria, token persistido, recuperación de sesión y revocación. Deduplica recuperación simultánea y descarta respuestas de una sesión anterior.
- Router manual: espera `/me` antes de permitir una ruta protegida. No identifica como autenticado a alguien solo por tener un token guardado.
- Login: identidad guinda/gris y logotipo, campos vacíos, contraseña visible opcional, estados de carga/error, recuperación ante fallo de red.

API existente extendida: POST `/api/login`, GET `/api/me`, POST `/api/logout`. Login limitado a diez intentos por minuto según throttle de Laravel. `/me` rechaza usuarios inactivos; logout revoca el token actual, no todos los dispositivos. El control de usuarios inactivos en futuros endpoints deberá ser transversal al añadirlos. Las autorizaciones operativas seguirán siendo responsabilidad del backend.

## Persistencia y límites

Se conserva el token Sanctum en LocalStorage, siguiendo la arquitectura de tokens acordada. Usuario y roles se vuelven a consultar; no se persisten contraseñas ni expedientes. No confundir esta persistencia con guardado de formularios. El almacenamiento web del token implica acceso por JavaScript: evitar HTML no confiable e introducir CSP apropiada al despliegue. No se añadió expiración automática nueva ni refresh tokens.

Un fallo de conexión al restaurar conserva el token y muestra reintento, sin abrir rutas protegidas. Un 401 elimina la sesión. Si el servidor no pudo revocar al salir, se mantiene la sesión y se muestra error para reintentar. La fecha de expiración efectiva depende de Sanctum y del servidor.

## Configuración y ejecución

Configurar `VITE_API_URL` con la raíz completa de API, por ejemplo `http://localhost:8000/api`, en el entorno de admin-web. Ese es también el valor de desarrollo por defecto. El backend debe permitir el origen del frontend en CORS.

Desde `frontend/apps/admin-web`, con Node y pnpm compatibles con los manifests existentes: `pnpm dev`. No se arrancaron procesos en esta iteración. El backend y su base de datos deben estar disponibles para comprobar acceso real; no se ejecutaron migraciones ni seeders.

Verificación finita: vue-tsc, ESLint sobre archivos modificados, sintaxis PHP y siete pruebas del store con Vue/Pinia reales y API/almacenamiento simulados. Pruebas: `node --experimental-vm-modules --test tests/auth-session.test.mjs`. No sustituyen verificación de API real ni inspección visual en navegador.

## Próximos pasos

Validar login contra el entorno local existente, luego menú lateral por rol y módulo de grupos. Mínimo inicial propuesto por usuario: tres integrantes. Máximo tentativo: veinte o sin límite; mantener configurable y confirmar antes de imponer bloqueo de negocio.
