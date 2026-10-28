# Siguiente fase: activación presencial de clientes

Decisión del propietario, 24 de octubre de 2026: **activación presencial con código de un solo uso**. CURP y monto del crédito son comprobaciones adicionales, nunca credenciales suficientes.

## Implementación prevista

1. En sucursal, un operador autorizado verifica la identidad del titular y emite una invitación vinculada al UUID del cliente. Conservar responsable y fecha.
2. Generar un secreto aleatorio con caducidad, almacenar únicamente su hash y mostrarlo al operador solo al emitirlo. Revocar la invitación anterior al reemplazarla.
3. El cliente introduce el código y define su contraseña. Consumo atómico para impedir dos activaciones simultáneas. Aplicar límites de intentos y respuestas que no revelen identidades.
4. Mantener cuentas de clientes separadas de usuarios administrativos. El token de un cliente no habilita rutas administrativas ni permisos de personal.
5. API propia de solo lectura: crédito activo del titular, fechas, cuotas, atrasos y comprobantes que le corresponden. No exponer expedientes o datos de otras integrantes. QR mantiene verificación del pago y reversos.
6. Incluir cierre de sesión, revocación y recuperación presencial auditada. Probar acceso cruzado, invitaciones expiradas/reutilizadas, cuentas inactivas y ausencia de crédito activo.

## Implementado el 24 de octubre de 2026

- Admin-web → **Accesos de clientes** (`/#/accesos-clientes`). Gerente opera en su sucursal; Super Admin y Gerente General pueden operar entre sucursales. Promotores y Caja no emiten códigos en esta fase.
- Búsqueda exacta por CURP o folio; solo clientes activos o morosos de sucursales activas. La búsqueda no devuelve CURP, domicilio ni teléfonos.
- Confirmación obligatoria de verificación presencial. Código aleatorio de 128 bits, duración de 30 minutos, almacenado únicamente como SHA-256. Reemisión invalida los anteriores. No persistir el código en almacenamiento del navegador ni auditorías.
- `POST /api/client-access/search` recibe `identifier`; `POST /api/client-access/clients/{uuid}/activation` recibe `identity_verified: true`.
- `POST /api/client/activate` recibe `code`, `password` y `password_confirmation`. Contraseña de 12 a 128 caracteres con letras y números. Consumo y creación de cuenta en una transacción, bloqueando primero al cliente. No permite reemplazar cuentas existentes.
- Auditoría distingue personal emisor y cuenta del cliente que consume el código. Guarda el identificador de la invitación, nunca el secreto ni la contraseña.
- Todas las rutas administrativas autenticadas rechazan cuentas de cliente y personal inactivo. La activación no entrega todavía una sesión ni acceso a datos financieros.

### Puesta en marcha

Desde `backend`, ejecutar `php artisan migrate` para aplicar las migraciones pendientes, incluida `2026_10_24_020000_create_client_access.php`. No se ejecutaron sobre la base de trabajo durante este desarrollo.

### Verificación

Suite SQLite: **129 pruebas y 1709 aserciones** correctas. Incluye vencimiento, reemisión, uso único, contraseña cifrada con hash, segregación de sucursales y rechazo de cuentas cliente en rutas administrativas. Prueba de interfaz con API simulada: búsqueda, confirmación, emisión, vista móvil y desaparición del código al recargar. TypeScript y ESLint revisados. La conexión MySQL local no estuvo disponible en esta revisión; queda pendiente repetir la suite con las nuevas tablas en MySQL. No se probó concurrencia con múltiples procesos.

### Siguiente entrega

Construir la pantalla de activación y el inicio de sesión de `frontend/apps/client-app`, con rutas y sesiones propias, después la consulta del crédito activo, vencimientos y comprobantes del titular. Recuperación presencial y revocación de cuentas aún pendientes. La app de clientes sigue siendo la base Quasar inicial; no distribuir códigos reales hasta conectar este flujo de usuario.

## Actualización: pantallas y sesión de clientes

La pantalla de activación y el inicio de sesión ya están implementados, junto con `/api/client/login`, `/api/client/me` y `/api/client/logout`. Sustituye la pendiente de pantallas de la sección anterior. Ver [acceso-app-clientes.md](../features/acceso-app-clientes.md) para ejecución y alcance. Sigue pendiente la consulta financiera exclusiva del titular, recuperación presencial y distribución nativa.
