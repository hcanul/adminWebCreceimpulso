# App del personal: accesos y próximas etapas

## Entrega: administración móvil

Super Admin dispone de Usuarios (crear/editar, sucursal, roles, contraseña y estado) y Roles y permisos (crear roles y asignar permisos del catálogo implementado). Super Admin permanece protegido. Crear permisos con nombres arbitrarios no implementa operaciones nuevas. Al asignar Promotor se crea o actualiza su perfil de negocio con el mismo UUID. Backend y app usan los mismos accesos del panel web; no se ampliaron permisos globales de los roles existentes.

El menú y las rutas administrativas requieren Super Admin; el backend valida cada solicitud. Gerente General conserva control operativo sin administración técnica de cuentas o permisos. Mi acceso permite a cualquier perfil consultar sus permisos vigentes. Se mantienen los límites por sucursal y grupos; un permiso no elimina esos límites. La administración requiere conexión. Las sesiones de cuentas editadas se revocan según las reglas existentes.

Pruebas: compilación SPA con TypeScript/ESLint, seis pruebas backend de administración (51 comprobaciones), prueba de interfaz a 390 px con API simulada para alta de usuario, rol/sucursal, permisos y bloqueo de rutas para Gerente General, Gerente, Gestión, Coordinación Comercial, Caja y Promotor. No se modificaron cuentas operativas para probar la UI.

## Orden de trabajo

1. **Grupos y expedientes completos:** crear/editar grupos desde el móvil, asignar promotor según rol, integrantes, comité, documentos y entrega del expediente a Gestión. Mantener grupo primero, mínimo inicial de tres integrantes y CURP validada.
2. **Flujo de crédito por función:** solicitud y montos por integrante; revisión de Gestión; condiciones y fechas; autorización comercial; entrega en sucursal. Conservar monto solicitado, aprobado y auditoría. El móvil debe mostrar cada acción solo cuando existe permiso y el estado permite ejecutarla.
3. **Seguimiento y oficina:** cartera y atrasos por alcance, tareas de seguimiento; adaptación de Caja para usuarios autorizados que operan en oficina, pagos, depósitos, tickets, reversos y cortes. Los promotores no cobran en campo. No implementar cobros offline.
4. **Base offline para campo:** almacenamiento local protegido y aislado por usuario/sucursal/servidor, borradores UUID, estado pendiente y sesión nativa segura. Definir vigencia de autorización offline y cierre de sesión antes de permitir expedientes locales.
5. **Sincronización:** cola durable, idempotencia, versiones del servidor, reintentos y resolución visible de conflictos. No resolver conflictos financieros con último timestamp. Revalidar permisos y alcance al sincronizar.
6. **Preparación operativa:** pruebas físicas Android/iPhone/iPad, recuperación y cambios de red, auditoría, privacidad, accesibilidad, backend HTTPS y distribución controlada.

Los permisos financieros listados en Mi acceso no significan que sus pantallas móviles estén implementadas. Hasta su adaptación se opera desde admin-web. La versión instalada antes de esta entrega necesita actualizarse para mostrar Usuarios y Roles.
