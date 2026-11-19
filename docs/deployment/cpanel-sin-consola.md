# Preparación de despliegue en cPanel sin consola

18/11/2026. Dominio indicado: **creceimpulso.com**. El usuario informa phpMyAdmin disponible y Trabajos de cron no disponible. PHP por dominio y límites del plan siguen por verificar. No se ha accedido a cPanel, publicado código ni modificado bases del hosting. Firefox está abierto en la Mac, pero la herramienta de accesibilidad no tiene permiso de macOS para inspeccionarlo.

## Viabilidad y límites

La SPA de Quasar puede publicarse como archivos estáticos; no necesita Node en el hosting. Laravel requiere PHP compatible, extensiones, MySQL y almacenamiento privado escribible. Sin consola puede prepararse `vendor` localmente y subirse un ZIP mediante Administrador de archivos. Esta preparación debe respetar el `composer.lock` y la versión PHP real del servidor; no usar `--ignore-platform-reqs` para ocultar incompatibilidades.

El backend actual es **Laravel 12**, no 11: `composer.lock` contiene 12.69.3 y exige PHP 8.2 como mínimo. Además de los requisitos estándar de Laravel, el proyecto utiliza `pdo_mysql`, `intl` para importes en letra del contrato y OpenSSL para firmas push. Verificar cURL con HTTP/2 si se usará APNs directo, y acceso HTTPS saliente a los proveedores de mensajería.

La ausencia de cron **no impide por sí sola** servir las pantallas y la API, pero deja sin disparador los recordatorios automáticos y el despachador push. El calendario actual está en `backend/routes/console.php`: planificación por hora y despacho por minuto. No declarar operativos los mensajes automáticos hasta resolver y probar su ejecución.

## Decisiones del hosting pendientes

| Dato | Estado / comprobación |
|---|---|
| Proveedor y plan | No informado |
| PHP del dominio y extensiones | Pendiente; la versión PHP del panel no siempre es la del sitio |
| MySQL o MariaDB y versión | Pendiente; comprobar importaciones, collation, índices y privilegios |
| Administrador de archivos y extracción ZIP | Verificar disponibilidad y límites |
| Límite de subida, ejecución, memoria y espacio/inodos | Pendiente; incluye archivos firmados, respaldos y logs |
| Subdominios y carpeta pública configurable | Pendiente |
| SSL para cada subdominio | Pendiente |
| Cron | Usuario informa que no está disponible |
| Ejecución inicial de migraciones y creación de administrador | Definir vía proveedor o procedimiento de importación probado |
| Conexiones salientes a FCM/APNs y límites de envío | Pendiente |

## Topología propuesta, todavía no creada

- `admin.creceimpulso.com`: contenido compilado de Admin Web.
- `api.creceimpulso.com`: únicamente el directorio público de Laravel.
- Mantener la web principal y el sistema existente mientras se valida la nueva instalación. Comprobar DNS y subdominios existentes antes de elegir esos nombres.

El backend completo, `.env`, claves push, `vendor`, logs y documentos privados deben permanecer fuera de la raíz pública. Preferir un Document Root que apunte a `backend/public`. Si el proveedor no permite esa disposición, definir con él una separación equivalente entre archivos públicos y aplicación privada; no subir todo Laravel a una carpeta navegable.

El `.htaccess` público conserva el encabezado Authorization requerido por Sanctum. SSL y CORS deben permitir los dominios reales de las apps y los orígenes nativos necesarios; nunca reemplazar autenticación por CORS abierto. La web actual usa router hash, por lo que sus enlaces incluyen `#/`.

## Cómo operar sin cron disponible

Orden recomendado:

1. Solicitar al proveedor habilitar cron y confirmar la ruta del PHP del dominio; algunos planes ocultan esa función. Es la opción más sencilla para el calendario ya implementado.
2. Si no se habilita, preparar un programador externo que active un endpoint HTTPS dedicado y autenticado, con operaciones fijas, exclusión mutua, reintentos e idempotencia. Ese endpoint **todavía no existe** y debe desarrollarse/probarse antes de activarlo. No exponer Artisan ni un ejecutor genérico de comandos por URL.
3. Otra opción es que el proveedor ejecute el calendario en un servicio administrado o alojar la API en un entorno con programación disponible. No contratar servicios ni cambiar el hosting sin definir el plan.

No usar la Mac como programador permanente de producción: depende de que esté encendida, con conexión y sesión disponible.

## Preparación local cuando estén cerradas las funciones

1. Crear un paquete de backend de producción con dependencias `--no-dev`, autoload optimizado y sin `.env` local, bases, respaldos, tokens, logs ni datos demo. Conservar estructura vacía de almacenamiento necesaria.
2. No copiar el `bootstrap/cache/config.php` de la Mac: contiene configuración y rutas locales. Las cachés se generan en el servidor o se posponen hasta disponer de un mecanismo compatible.
3. Compilar la SPA con `QCLI_API_URL=https://api.creceimpulso.com/api` si se confirma ese subdominio. Esta URL es pública; nunca colocar secretos en variables del frontend. Entregar solo `dist/spa`, no el código fuente ni `node_modules`.
4. Crear la base y usuario MySQL desde cPanel y conceder únicamente permisos requeridos. Preparar `.env` de producción en ubicación privada: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` real y configuración de BD, caché, sesión y push correspondiente al hosting.
5. Crear una APP_KEY de producción y conservarla estable. Si se trasladan datos cifrados o enlaces firmados existentes, planificar su compatibilidad; no regenerar la clave en cada actualización ni copiar automáticamente la del entorno de pruebas.
6. Reservar ubicaciones persistentes para documentos firmados y credenciales privadas de FCM/APNs. No sustituirlas al subir otra versión.

No ejecutar el `DatabaseSeeder` general en producción sin prepararlo: el actual contiene una contraseña inicial fija de desarrollo. Se requiere un procedimiento de alta de administrador con clave temporal propia y renovación obligatoria; no distribuir ese dato en el ZIP.

## Instalación de base y actualización sin terminal

Preferir que el proveedor ejecute las migraciones Laravel y la inicialización aprobada. Si habilita cron, se puede realizar una tarea temporal de instalación controlada, registrar su resultado y retirarla, aparte del calendario permanente.

Si solo hay phpMyAdmin, una instalación nueva puede prepararse como SQL de esquema más catálogos autorizados a partir de una base limpia compatible. Debe incluir el estado correcto de migraciones y excluir cuentas demo, tokens y movimientos ficticios. No equivale a importar la base de desarrollo ni a reconstruir saldos históricos. Cada actualización posterior exige migraciones o SQL de cambio ensayado y respaldos; no importar otra vez un esquema completo sobre datos operativos.

La consolidación de las siete bases y las notas centrales de Cajas sigue un ensayo de migración separado con conciliación. No transformar importaciones de phpMyAdmin en correcciones silenciosas de cartera.

## Verificación antes del cambio operativo

- API HTTPS, inicio de sesión, renovación de contraseña, permisos y aislamiento de sucursales.
- Consultas ordinaria/recuperación/consolidada y totales conciliados.
- Solicitud → revisión → autorización → documentos → entrega → cobro parcial → ticket QR → corte → reverso.
- Archivos privados inaccesibles directamente; subida/descarga autorizada e impresión.
- Mensajería probada con destinatarios de ensayo y programación externa/cron supervisada.
- Apps móviles y escritorio con la URL pública, sin IP de la Mac; comprobar recibos y enlaces firmados con el dominio definitivo.
- Prueba de restauración del respaldo y procedimiento para volver a la versión anterior sin pérdida de pagos capturados.

Los archivos preparados todavía no se publicarán sobre el sitio en operación antes de esta comprobación.

## Referencias primarias

- [Laravel 12: requisitos, directorio público y permisos](https://laravel.com/docs/12.x/deployment).
- [cPanel: Administrador de archivos](https://docs.cpanel.net/cpanel/files/file-manager/).
- [cPanel: dominios](https://docs.cpanel.net/cpanel/domains/domains/).
- [cPanel: cron y disponibilidad según el proveedor](https://docs.cpanel.net/cpanel/advanced/cron-jobs/).
