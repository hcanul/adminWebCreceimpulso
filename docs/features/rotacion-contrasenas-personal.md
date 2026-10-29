# Contraseñas del personal

Todas las cuentas de colaboradores, incluido Super Admin, deben cambiar su contraseña cada 15 días. La vigencia se cuenta desde el último cambio registrado, no desde el inicio de sesión. Las cuentas existentes sin fecha registrada deberán renovarla al aplicar la migración; no se asigna una antigüedad supuesta.

Admin-web y staff-app presentan una pantalla obligatoria. Se requiere la contraseña actual y una nueva confirmada de al menos 12 caracteres con letras y números, diferente de la actual y de la inmediatamente anterior. El servidor bloquea operaciones cuando vence, incluso si la pantalla seguía abierta; solo permite perfil, cambio y salida. Se revocan las otras sesiones al cambiar y se entrega una sesión nueva con vigencia de ocho horas. Las contraseñas y el hash anterior no se exponen en la API ni en auditoría.

Las ediciones offline ya guardadas se conservan. La caducidad se exige al conectar o sincronizar; el teléfono sin red no puede consultar revocaciones. Esta entrega no bloquea ni elimina copias cifradas pendientes por vencimiento de la contraseña de servidor.

Aplicar la migración `2026_10_28_180000_staff_password_rotation.php` antes de publicar esta versión. No se ejecutó sobre la base operativa durante el desarrollo. El usuario sigue usando el correo como identificador.

Comprobaciones: frontera exacta de 15 días, fecha desconocida, acceso limitado, contraseña actual, rechazo de reutilización, revocación de sesiones, renovación y ausencia de secretos en respuesta/auditoría.
