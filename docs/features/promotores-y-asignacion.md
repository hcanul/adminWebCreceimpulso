# Promotores y asignación de grupos

Corrección solicitada: quien captura un grupo no necesariamente es su promotor. Este documento sustituye la excepción anterior que permitía asignar administradores como promotores.

- Tabla `promoters` independiente. Su UUID es el UUID del usuario asociado (clave primaria compartida y FK a users). Tiene sucursal y estado activo; no crea credenciales ni roles.
- Solo cuentas activas con rol exacto `Promotor`, sin baja y con sucursal activa pueden registrarse. El rol vuelve a verificarse al listar y asignar, por lo que retirarlo elimina su elegibilidad aunque el registro exista.
- Crear/editar grupo requiere promotor registrado y activo de la misma sucursal. Todo usuario sin rol Promotor debe seleccionarlo explícitamente: Gerente, Gerente General, Coordinador y Super Admin incluidos.
- Si el actor tiene rol Promotor, puede sugerirse su UUID. El backend permite omitir el campo solo para ese rol, y valida el mismo registro, sucursal y estado. Aplica igual a web y futuras llamadas de móvil.
- `teams.created_by` guarda quién captura, separado de `promoter_id`. En grupos históricos queda desconocido/null: no se reconstruye a partir de la asignación anterior.
- Alta y consulta desde el apartado independiente Promotores (`/promotores`) del menú, con enlace desde Grupos → Administrar promotores. Registro disponible para Super Admin/Gerente General sobre usuarios que ya poseen el rol. Esta operación no crea usuarios ni asigna permisos. Cuando no hay candidatos, la pantalla explica el requisito.
- Edición de grupo permite seleccionar el promotor correcto, conservando sucursal y UUID del grupo.

## Migración local aplicada

`2026_10_08_140000_separate_promoters` aplicada en MySQL local tras comprobar APP_ENV y loopback. Registra usuarios elegibles existentes; la FK de teams ahora referencia promoters. Las asignaciones antiguas no elegibles se conservan en `previous_promoter_user_id` y el grupo queda con promotor pendiente (null), sin asignarle otra persona automáticamente. Ese null solo sirve para la corrección de datos anteriores: la API no admite altas sin responsable válido.

Resultado local: cero promotores elegibles registrados y un grupo pendiente de reasignación. No se cambiaron roles, contraseñas, integrantes ni el UUID de ese grupo. Para continuar, hace falta una cuenta con rol Promotor y sucursal; después se registra su perfil y se asigna al grupo.

Los créditos históricos conservan su referencia de usuario existente; al compartir UUID entre usuario/promotor no se renumeran. La captura de nuevos expedientes exige que el grupo ya tenga un promotor válido.

## Verificación

21 pruebas de integración (98 aserciones): rechazo de Super Admin como promotor, selección obligatoria para todos los roles sin Promotor, autoasignación del Promotor registrado, rechazo de otra sucursal o rol retirado, alta de perfil sin cambiar roles y conservación de la asignación inválida anterior. TypeScript y ESLint verificados. Sin servidores nuevos ni contenedores.
