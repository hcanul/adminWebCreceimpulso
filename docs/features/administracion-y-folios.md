# Administración de acceso, búsqueda y folios

Actualizado: 9 de octubre de 2026.

## Cuentas y permisos

Menú Administración → Usuarios y Roles y permisos. Ambas pantallas y todos sus endpoints están restringidos a un Super Admin activo. Gerente General conserva su alcance operativo, sin administración técnica.

Usuarios permite crear y editar nombre, correo, sucursal, roles, estado y contraseña. Las contraseñas requieren 12 caracteres, mayúsculas, minúsculas y números, confirmación y hash del servidor; no se guardan en almacenamiento del navegador. Al guardar una cuenta con rol Promotor se crea/actualiza automáticamente su perfil con el mismo UUID. Se conserva el diálogo para vincular cuentas antiguas elegibles.

Los cambios de cuenta revocan sus tokens existentes. No se permite retirar el propio rol Super Admin ni desactivar la propia cuenta; debe quedar al menos un administrador activo. Un promotor con grupos asignados no puede desactivarse, perder ese rol o cambiar de sucursal hasta reasignarlos. Las asignaciones y cambios de acceso se registran en `access_audits`, sin contraseñas ni tokens.

El catálogo de permisos implementado es `intake.access`, `intake.submit` y `promoters.register`. Se comprueban en la API y se devuelven en login y `/me`. El Super Admin conserva acceso total. Crear roles no otorga acceso global: roles nuevos operan en su sucursal y grupos creados por ellos. Los nombres de roles existentes no se renombran, pues determinan alcance y elegibilidad como Promotor. El menú se actualiza al recargar la sesión; la autorización del servidor aplica inmediatamente.

Migración local aplicada: `2026_10_08_190000_seed_operational_permissions`. Crea roles base faltantes, permisos operativos y auditoría; no crea usuarios ni cambia credenciales. El rollback deliberadamente requiere un plan explícito para preservar auditoría y cambios administrativos.

## Búsqueda de grupos y filtros

El alta de cliente usa un campo de búsqueda por nombre; desde dos caracteres consulta `/intake/team-search` con espera de 350 ms y cancelación de consultas anteriores. Muestra hasta 15 coincidencias, avisa si existen más y conserva el UUID como selección real. Nombres repetidos se distinguen por sucursal, promotor y UUID.

La API obliga a la sucursal del usuario. Las cuentas globales sin sucursal deben seleccionar una explícitamente. Conserva además el alcance de grupos del promotor/coordinador; no es un filtro puramente visual. Buscar no sustituye la selección del resultado ni la verificación posterior de CURP.

Todos los selectores actuales de admin-web usan `FilterSelect`, con entrada de texto, filtrado sin distinción de mayúsculas/acentos y mensajes sin coincidencias. Catálogos como roles, sucursales y opciones del expediente filtran las opciones recibidas; la búsqueda de grupos se realiza en servidor. No se implementa texto libre como valor de catálogo. Los listados de promotores aún usan el catálogo existente: su paginación remota será necesaria al crecer ese catálogo.

Los títulos de diálogos tienen estilo propio porque Quasar los renderiza fuera de la página; los valores vacíos ya no generan la etiqueta `undefined`.

## Folios por sucursal

- Cliente: `CLI-S001-000001`.
- Crédito: `CRE-S001-000001`.
- UUID: continúa siendo la clave primaria interna y la identidad para sincronización.

`branches.folio_code` es un código estable, asignado automáticamente. Las siete sucursales existentes recibieron códigos por orden de nombre/UUID durante la migración; el código no depende del nombre posterior. Los catálogos futuros deben conservarlo.

`folio_sequences` contiene contadores separados por sucursal y tipo. La asignación usa transacciones y bloqueo de fila; cada folio tiene índice único. No se usa `MAX + 1`. Un cliente existente conserva su folio al editarse o participar en otro grupo. El crédito conserva el suyo a través de sus estados. Los folios y la sucursal de origen no se pueden modificar mediante los modelos ni se aceptan como campos de captura. No se reutilizan números de registros archivados. Puede haber saltos si una creación falla fuera de una transacción contenedora; no son una numeración fiscal ni garantizan ausencia de huecos.

Los créditos reciben su folio al crear `LoanApplication`; esta infraestructura no implementa todavía el flujo financiero completo de solicitud/aprobación/desembolso. El expediente de cliente no obtiene un folio de crédito. La consulta de CURP y los listados de expedientes exponen los folios correspondientes.

Offline: el dispositivo seguirá generando UUIDs; el folio definitivo se asignará al sincronizar con el servidor. El motor offline sigue pendiente. Importaciones que usen SQL directo deben invocar el asignador explícitamente; los índices únicos y campos no nulos impiden insertar sin folio, pero no sustituyen esa integración.

Migración local aplicada: `2026_10_09_010000_add_branch_folios`. Incluye asignación de folios a registros previos, incluso archivados. En la base local había siete sucursales y ningún cliente ni crédito; no se inventaron registros de ejemplo. Para otros entornos se requiere respaldo y una ventana sin escrituras al ejecutar este cambio de esquema y asignación inicial.

## Validación

Suite aislada en SQLite memoria: 31 pruebas, 173 aserciones, incluida autorización, perfiles de promotor, folios separados por sucursal/tipo, permanencia tras cambio de nombre y búsqueda acotada. TypeScript y lint se verifican en los archivos cambiados. No se lanzan pruebas continuas ni contenedores. Los bloqueos usan las primitivas transaccionales de Laravel; no se ha realizado una prueba de carga concurrente en MySQL.

El formulario omite `ncliente` y `nCredito`. `ncliente` se incorpora al snapshot del expediente con el folio generado por el backend al guardar. En edición se conserva el folio de la persona. El servidor ignora números enviados por el navegador; los números de crédito antiguos se preservan únicamente como antecedentes de expedientes existentes. El contrato de 72 campos legacy se mantiene para futuras plantillas PDF, aunque estos identificadores ya no son campos manuales.

Comprobación visual con API simulada en navegador aislado: diálogo de promotores sin etiquetas indefinidas y título de 21 px, alta de usuario y filtrado de roles, editor de permisos y búsqueda/selección de grupos por sucursal. Verificado ancho móvil de 390 px sin desbordamiento horizontal. El navegador temporal se cerró; no se escribieron usuarios de prueba en MySQL.
