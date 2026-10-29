# App de colaboradores — primera entrega

Ubicación: `frontend/apps/staff-app`.

Incluye acceso con correo y contraseña de `users`, restauración de sesión al recargar, cierre de sesión, configuración persistente del servidor, menú lateral, consulta paginada de grupos e integrantes y comprobación de permisos. Usa Axios centralizado, servicios TypeScript y Pinia, sin duplicar reglas financieras. El backend sigue siendo autoridad para sucursal y asignación de promotor. Se requiere `intake.access` o rol Super Admin para consultar grupos.

La dirección local puede guardarse incluso con el servidor apagado. Ejemplo: `192.168.1.67:8000`. Cambiarla elimina la sesión. La comprobación de conexión reutiliza el endpoint público existente de la API; no utiliza credenciales de clientes.

Primera etapa en línea: no incluye edición offline, cola de sincronización, instalación nativa ni cobros. No se guardan expedientes localmente. El token se conserva en sessionStorage durante la sesión del navegador; no se promete acceso offline ni persistencia segura nativa entre reinicios. Próximas etapas: captura de clientes con validación CURP, edición de grupos, almacenamiento local aislado por usuario/sucursal y sincronización con conflictos explícitos, y paquetes Capacitor con identidad propia.

Ejecutar desde la carpeta de la app:

```sh
QCLI_API_URL=http://192.168.1.67:8000/api pnpm dev --hostname 0.0.0.0 --port 9014
```

El teléfono debe usar la misma Wi-Fi y abrir `http://192.168.1.67:9014`. Usar Node compatible con Quasar (22.12+ o 24), MySQL y la API activos. Usuarios existentes del personal: no usar folios ni credenciales de `client_accounts`.

## Validación de la primera entrega

Compilación SPA con comprobación de TypeScript y ESLint correcta. Prueba de interfaz `tests/staff-core-ui.cjs`: acceso, rutas protegidas, cambio de permisos, búsqueda por nombre, integrantes, recarga y cierre de sesión. Usa respuestas API simuladas, sin credenciales reales. Capturas móviles revisadas a 390 px, sin desbordamiento horizontal.

Ejecutar la prueba con la compilación servida en el puerto 9014 y la variable `PLAYWRIGHT_MODULE` apuntando al paquete Playwright instalado. No necesita poblar la base de datos.

## Segunda entrega: expediente de clientes

Disponible desde Grupos → Ver integrantes → Capturar cliente o desde el menú Expediente de cliente. La búsqueda de grupo consulta coincidencias del servidor restringidas a sucursal y permisos. Antes de capturar se verifica la CURP en el backend y se presenta una confirmación explícita con sucursal, créditos activos y último crédito registrado cuando existe. Los registros fuera del alcance del colaborador no se duplican ni se abren para edición.

Se usa directamente el catálogo `admin-web/src/domain/intake-fields.json`, sin crear una segunda definición de campos. El formulario tiene nueve secciones, catálogos filtrables, validaciones por campo y advertencia de cambios sin guardar. No incluye monto solicitado ni captura manual de folio; el backend asigna el número de cliente.

Guardar borrador conserva el expediente en el servidor. Se recupera seleccionando el mismo grupo y verificando la misma CURP. Enviar a revisión solo aparece con `intake.submit` o Super Admin; un expediente enviado queda en consulta. El UUID y las versiones acompañan cada guardado. Ante un conflicto 409 se conservan los datos en pantalla y se solicita consultar nuevamente la CURP. Los datos de consulta se limpian al cerrar sesión.

Esto todavía requiere conexión; no hay guardado local offline ni sincronización de expedientes.

Validación: compilación con TypeScript y ESLint; prueba móvil con API simulada para CURP, guardado y recuperación, conflicto y restricción de duplicados; 27 pruebas del backend en SQLite en memoria, 165 comprobaciones.

## Prueba integrada con backend local — 27 de octubre de 2026

Validación con API real y MySQL, usando una sucursal QA, dos promotores ficticios y un expediente de prueba. Se verificaron acceso, aislamiento entre promotores, rechazo de CURP inválida, asignación automática de folio, bloqueo de envío sin permiso y de edición sin confirmación. En navegador a 390 × 844 se recorrieron login, grupo, captura, edición, guardado, recarga con recuperación desde MySQL y cierre de sesión. No hubo errores JavaScript ni desbordamiento horizontal; una versión obsoleta fue rechazada con 409.

Se corrigió `/api/me`: la relación de permisos cargada por Spatie sobrescribía el atributo con nombres durante la serialización de Eloquent. Ahora la respuesta construye explícitamente `permissions` como lista de nombres, incluyendo permisos directos y heredados. Se añadió una regresión al backend. Resultado: **28 pruebas, 172 comprobaciones**, SQLite en memoria.

Las cuentas QA de esta ejecución quedaron desactivadas y sus tokens revocados; se conserva el expediente ficticio con su auditoría. Esta validación fue en navegador automatizado con API real, no en dispositivo físico ni en modo offline.

## Paquetes nativos de prueba

Se añadieron proyectos Capacitor independientes con identificador `com.creceimpulso.colaboradores`, nombre Crece Colaboradores y el icono de la marca. Android assembleDebug y Xcode Debug para iPhone finalizaron correctamente; firmas verificadas. Scripts `ios:prepare`, `android:prepare` y `android:apk` disponibles. Consulta `instalacion-colaboradores.md`. La incorporación del contenedor nativo no incorpora aún almacenamiento offline ni persistencia segura del token.

## Grupos, comité y listado de expedientes — 28 de octubre de 2026

Crear grupo desde Grupos. El formulario exige nombre, sucursal y promotor habilitado. Para quien no tiene rol Promotor no se presupone un responsable; debe seleccionarlo. El promotor de campo usa su propio perfil activo. Los nombres pueden repetirse; los UUID distinguen los grupos. Editar conserva UUID y sucursal y permite cambiar nombre y responsable dentro del alcance del backend.

En el detalle se asignan Presidenta, Tesorera e Integrante. Los cargos son propuesta para el siguiente ciclo; no modifican participaciones históricas del crédito. El backend impide repetir Presidenta o Tesorera y bloquea accesos ajenos. Los indicadores de tres integrantes y comité completo no autorizan créditos. Desde cada persona se abre la captura con CURP precargada, pero su verificación y confirmación siguen siendo obligatorias.

El menú Expedientes de clientes muestra registros paginados con folio, grupo y estado dentro del alcance del usuario. Al abrirlos vuelve a verificarse CURP; no se guardan datos sensibles en parámetros de URL. Se reutilizan servicios y reglas existentes, sin cambios de base de datos.

Validación: compilación TypeScript/ESLint, 28 pruebas backend y 172 comprobaciones; prueba móvil automatizada con API simulada para creación, selección obligatoria, promotor propio, edición, comité, listado y barrera de CURP. Falta adaptar documentos adjuntos e impresión móvil para completar la etapa de expedientes. No incluye offline.

## Actualización de alcance: documentos y correcciones offline

Por indicación del usuario, todos los documentos se generan en admin-web. Se elimina la generación/impresión documental móvil de la hoja de ruta. La app del personal incorpora preparación de borradores existentes, almacenamiento cifrado con clave local y sincronización explícita de correcciones con validación de versiones. Véase `correcciones-offline.md` para procedimiento y límites.
