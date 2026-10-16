# Grupos y expedientes de clientes — implementación de captura

Fecha: 8 de octubre de 2026. Aplicación: `frontend/apps/admin-web`.

## Recorrido implementado

1. Entrar en Grupos solidarios y crear un grupo con nombre, sucursal y asesor. El nombre NO es único; se distingue por UUID, sucursal y responsable. Listado paginado con búsqueda y edición del nombre.
2. Abrir el grupo e incorporar integrantes. Antes de abrir el formulario, elegir grupo y verificar CURP mediante POST autenticado, evitando poner la CURP en la URL.
3. Consulta previa: estructura, fecha, entidad y dígito verificador; búsqueda interna normalizada. Si existe, muestra sucursal de alta, estado, créditos activos y último crédito registrado. Solicita confirmación para abrir/modificar. Una coincidencia no autorizada muestra resumen limitado y bloquea edición/duplicación.
4. Capturar los 72 campos originales, guardar borrador en servidor o enviar a revisión. Los campos contextuales de grupo/sucursal/asesor se derivan en servidor; CURP no es editable dentro del formulario. Los demás datos del borrador pueden corregirse. Los datos personales de un expediente previo accesible pueden precargarse; deben revisarse al crear otro expediente.
5. Recuperar un borrador desde Clientes o integrantes del grupo: se vuelve a verificar la CURP. Al recargar, los borradores GUARDADOS permanecen en servidor; cambios sin guardar generan advertencia al salir o recargar. No se guardan CURP/expedientes en LocalStorage.
6. Asignar Presidenta/Tesorera/Integrante al grupo. No permite dos Presidentas ni dos Tesoreras. Estos cargos preparan el siguiente ciclo; las participaciones históricas del crédito permanecen separadas.

## Datos y reglas

- Clientes únicos por CURP, UUID estable; se conserva la sucursal original al vincular a otro grupo autorizado. Los nombres de grupos no reciben índice único.
- Se agregó `clients.version` para detectar ediciones concurrentes.
- `team_memberships`: pertenencia actual a grupo, única por grupo/cliente, con UUID y branch_id.
- `client_applications`: expediente individual de captura vinculado al grupo, con los 72 campos JSON, estado, versión y autor. No es un desembolso ni sustituye `loan_applications`, la cabecera financiera grupal existente.
- `application_revisions`: historial de cada guardado, actor y snapshot de campos/estado.
- Esta captura da de alta al cliente y su expediente: no pide monto solicitado. La API prohíbe `amount_requested`; los valores históricos existentes se conservan en BD, pero no se exponen ni se editan. El monto solicitado por integrante pertenece al futuro flujo de solicitudes de crédito. Se conserva el nombre interno `client_applications` por compatibilidad.
- Rutas canónicas: `/clientes` y `/clientes/captura`; las rutas anteriores `/solicitudes` redirigen conservando la consulta. La API usa `/intake/client-files` y conserva el alias anterior `/intake/applications`.
- Estados implementados: borrador y en_revision. Enviado se consulta sin sobrescribir. Devolución con observaciones, aprobación comercial, creación del crédito y desembolso son pasos posteriores; no se simulan en esta entrega.
- Mínimo de tres integrantes mostrado como preparación inicial; no se fija máximo de veinte. La captura individual no exige tres personas, pues es precisamente la vía para incorporarlas. La elegibilidad financiera final queda pendiente de su motor de reglas.

## Consulta de créditos

Activo significa `loan_applications.status = entregado`; último crédito consulta entregado/pagado. Pendiente/aprobado no se presenta como préstamo ya entregado. Se muestra estado, grupo, sucursal, fecha de registro y fecha pactada, sin saldos ajenos.

El esquema existente NO tiene fecha real de desembolso ni de liquidación. La interfaz identifica expresamente la fecha de registro y no la presenta como entrega real. No hay datos de las siete bases legacy hasta su migración. Cero coincidencias en esta base no acredita ausencia de créditos en bases sin migrar.

## Acceso y arquitectura

Servicios Axios tipados, store Pinia para listados/consulta y formulario local en memoria; DTOs tipados, Actions transaccionales y validaciones en Laravel. `IntakeAccess` aplica autorización y filtrado explícito: Super Admin/Gerente General global; Gerente su sucursal; Promotor sus grupos asignados en su sucursal; Coordinador sin rol Promotor los grupos que creó en su sucursal. Caja y cuentas inactivas no acceden a captura. Solo Gerente, Gerente General o Super Admin pueden enviar el expediente a Gestión; el personal de campo prepara borradores dentro de su alcance. El backend controla el alcance aunque se manipule la interfaz.

La coincidencia entre sucursales expone solo sucursal/estado y resumen de créditos para prevenir duplicados, según solicitud del usuario; no entrega campos del formulario ni autoriza edición de otra sucursal a perfiles locales. Los modelos financieros usan SoftDeletes para evitar mezclar registros archivados con vigentes. Los endpoints futuros deberán adoptar autorización igualmente explícita; no se añadió un bypass global de permisos.

Una inserción simultánea con la misma CURP devuelve conflicto, no otro cliente. Las versiones impiden sobrescribir cambios ajenos. Asignación de comité serializada por grupo. No se modifica información del proyecto legacy.

## Estado del entorno local

Se verificó la configuración efectiva: APP_ENV local, MySQL en loopback, sin URL alternativa. Se aplicaron únicamente:

- `2026_10_08_120000_create_intake_workflow`
- `2026_10_08_120100_allow_pending_branch_city`

La segunda permite localidad pendiente en sucursales, sin inventar municipios. La base no contenía sucursales; se ejecutó `InitialBranchesSeeder` y quedaron Carrillo, Tulum, Morelos, Playa 1, Playa 2, Cancún 1 y Cancún 2, con UUIDs nuevos. No se crearon clientes, solicitudes, créditos ni usuarios de ejemplo en la base local. No se reinició DatabaseSeeder ni se cambiaron contraseñas.

La aplicación ya estaba escuchando en http://localhost:9001; no se inició su proceso. En una pestaña temporal se verificó que sigue mostrando el login. El recorrido visual autenticado completo aún no se probó.

En otro entorno aplicar ambas migraciones y, si corresponde crear el catálogo confirmado, ejecutar `php artisan db:seed --class=InitialBranchesSeeder`. El seeder respeta sucursales existentes o archivadas con esos nombres. Revisar la conexión de destino antes de ejecutarlo.

## Validación

Pruebas de integración aisladas con SQLite :memory: (configuración forzada en `backend/phpunit.intake.xml`), sin modificar MySQL: nombres repetidos, UUIDs distintos, CURP inválida, duplicado/confirmación, ámbito de sucursal y promotor, inactividad/Caja, versiones concurrentes, rechazo de montos en expedientes y conservación de importes históricos, campos completos y envío bloqueado para edición, comité, resumen de créditos, igualdad de contrato frontend/backend y conservación de los 72 nombres legacy. Resultado actualizado: 21 pruebas de integración aprobadas (98 aserciones), TypeScript y ESLint aprobados para los archivos modificados; seis pruebas de avisos y consulta de CURP aprobadas. La separación de expedientes no requirió migraciones ni cambios en la base local.

No se lanzó servidor, contenedor ni proceso continuo de pruebas. La migración real se verificó en MySQL; las pruebas funcionales exhaustivas usan SQLite, por lo que queda pendiente un ensayo concurrente específico de MySQL.

## Pendientes de la siguiente iteración

Revisión y devolución por Gestión, documentos PDF oficiales y firmas, adjuntos de comprobantes, corrección excepcional auditada de CURP, definición final de elegibilidad, integración financiera por ciclo y migración legacy. La validación local de CURP no demuestra identidad ni constituye consulta a RENAPO: completar revisión documental.

Referencia oficial de estructura: [RENAPO: cómo se conforma la CURP](https://www.gob.mx/segob/renapo/articulos/sabes-como-se-conforma-tu-curp).
