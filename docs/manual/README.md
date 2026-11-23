# Manuales de Crece Impulso

Edición 2.0, actualizada al 19 de noviembre de 2026.

La guía general reúne el recorrido completo. Las guías por puesto explican las tareas y límites de cada perfil. La app de Colaboradores conserva el rol y el alcance de la cuenta; su guía complementa la del puesto. Los menús concretos dependen de los permisos asignados y del estado del trámite.

| Documento | Destinatario |
| --- | --- |
| Manual de usuario Crece Impulso | Consulta general de Admin Web y ambas apps |
| Manual Super Admin | Administración integral, accesos, sucursales y operación |
| Manual Gerente General | Supervisión multisucursal y clasificación de cartera |
| Manual Gerente | Operación de sucursal y seguimiento |
| Manual Gestión | Verificaciones y revisión de solicitudes |
| Manual Coordinación Comercial | Autorización y garantía |
| Manual Coordinador | Grupos y expedientes según alcance |
| Manual Promotor | Captura y atención de grupos asignados |
| Manual Caja | Entrega, pagos, depósitos, comprobantes y cortes |
| Manual Cobranza | Cartera y observaciones por integrante |
| Manual Bancos | Consulta de cobros, depósitos y cortes |
| Manual Client App | Activación, crédito, estados de cuenta, QR y acceso móvil |
| Manual Staff App | Acceso móvil, menú y correcciones offline |

## Cambios incorporados

- Recordatorio de correo o número de cliente, desactivación del recordatorio, alta y eliminación de biometría.
- Cierre tras 30 minutos sin actividad y recuperación de acceso. Se distingue de la caducidad de contraseña del personal cada 15 días.
- Bloqueo del espacio offline, conservación de registros guardados y sincronización de correcciones.
- Alta y edición de sucursales, datos de contacto, código permanente y bloqueos para desactivar.
- Consulta de cobros y depósitos por fecha, sucursal y referencia, CSV, impresión y comprobantes.
- Formatos de comprobante en Carta con dos copias, ticket de 80 o 58 mm y selección de impresora mediante el diálogo del sistema.
- Cartera ordinaria, recuperación, observaciones e historial; traslado y reincorporación por Super Admin o Gerente General.

Se conservaron los procedimientos del ciclo de crédito, sus validaciones, roles y restricciones. Esta actualización de manuales no modifica contratos ni plantillas legales.

## Archivos y mantenimiento

Los manuales por puesto se encuentran en `por-rol/` en Word, PDF y Markdown. La guía general está en esta carpeta en Word, PDF y Markdown. Los PDF se generaron a partir de los Word y se revisaron visualmente.

`generadores/crear-manuales.py` contiene el catálogo de procedimientos y genera las fuentes Word y Markdown con `python-docx`. Ejecutarlo desde el proyecto no modifica la aplicación ni la base de datos. Después de editar o regenerar se deben convertir todos los Word a PDF, revisar todas las páginas y renovar el paquete descargable.

La entrega se copió a Descargas en `CreceImpulso-Manuales-2026-11-19`, con índice HTML y ZIP. También se actualizaron los nombres de archivo de la carpeta habitual `CreceImpulso-Manuales`, preservando antes su edición previa en una carpeta de respaldo fechada.
