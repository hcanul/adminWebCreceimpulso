# Datos documentales de empresa y sucursales

24 de octubre de 2026.

En **Administración → Datos documentales**, Super Admin configura domicilio legal, representante, cargo, lugar de pago y lugar habitual de firma. Se permite guardar campos vacíos y completarlos al iniciar operaciones.

- **Empresa · Datos generales** define el perfil común.
- Cada sucursal usa los datos generales inicialmente. Desactivar **Usar los datos generales de la empresa** permite capturar su perfil completo. Un campo vacío del perfil propio queda pendiente: no se mezcla silenciosamente con el general.
- Volver al perfil general conserva los datos propios para reutilizarlos después.
- Cada guardado aumenta la versión y conserva responsable, valores anteriores y nuevos en auditoría. Una edición desactualizada se rechaza para evitar sobrescritura.
- Las propuestas nuevas de contrato (plantilla 2) y pagaré (plantilla 3) conservan una copia del perfil efectivo. Las reimpresiones mantienen los datos originalmente emitidos. Configurar un perfil no convierte las propuestas en documentos definitivos ni altera versiones anteriores.
- Lugar habitual de firma no equivale a fecha efectiva de suscripción. La formalización y firma por crédito siguen pendientes; no se inventa una fecha ni se usa automáticamente la de emisión.

## Activación

Ejecutar `php artisan migrate` en el backend del proyecto nuevo. La migración `2026_10_24_010000_create_document_profiles` crea los perfiles sin datos inventados. También debe estar aplicada la migración de mora anterior. No se migró la base de trabajo durante esta entrega.

## Verificación

Pruebas de herencia, perfiles propios, campos pendientes, auditoría, conflictos y acceso exclusivo de Super Admin. Prueba de navegador con API simulada: guardado, recarga, selector filtrable, perfil de sucursal y adaptación móvil.

Resultado: 125 pruebas y 1,676 aserciones aprobadas en MySQL local aislado y SQLite; TypeScript y ESLint correctos. La base temporal MySQL se eliminó al terminar.
