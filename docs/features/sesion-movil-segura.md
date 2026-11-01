# Sesión móvil segura — prioridad 1

Implementación del 31 de octubre de 2026 para Clientes y Colaboradores.

En instalaciones nativas, el token se guarda con `@aparajita/capacitor-secure-storage` 8.0.1: Keychain de iOS y almacenamiento cifrado de Android. En iOS se selecciona `whenUnlockedThisDeviceOnly`, sin sincronización iCloud. No se guardan contraseñas, perfiles ni expedientes en el almacén de sesión. El navegador conserva la sesión solo en sessionStorage; no se ofrece una imitación insegura del almacén nativo.

Cada sesión queda separada por aplicación y servidor. Cambiar de servidor elimina la sesión anterior antes de guardar la dirección. Lecturas y escrituras se serializan. Una respuesta 401 solo elimina la credencial con la que se hizo la solicitud; no elimina una sesión nueva. Una respuesta tardía del perfil no restablece una sesión cerrada. Un error de conexión conserva la credencial para reintentar. Los datos temporales del usuario se limpian al cerrar sesión.

Al reabrir se recupera el token y se verifica el perfil con el servidor. La sesión conserva el vencimiento de ocho horas del backend; la persistencia no convierte el token en una sesión indefinida. En la actualización desde la versión anterior se requiere iniciar sesión de nuevo: los tokens antiguos del almacenamiento web no se migran silenciosamente al almacén nativo. Un fallo del almacén nativo no utiliza almacenamiento web como alternativa.

Verificación automatizada: ocho casos en `frontend/tests/mobile-session.test.mjs`: persistencia tras reinicio simulado, aislamiento por servidor, vencimiento, respuesta antigua, orden de operaciones, borrado durable y comportamiento web. TypeScript aprobado en ambas apps. La comprobación física requiere entrar, cerrar/reabrir, cambiar servidor y salir en cada plataforma. Las pruebas simuladas no certifican por sí solas Keychain/Keystore de un dispositivo.

Pendientes siguientes: cola offline durable con reintentos idempotentes, altas offline y resolución visible de conflictos. Las reglas financieras y las operaciones de caja continúan en línea.
