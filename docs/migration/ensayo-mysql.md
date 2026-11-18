# Primera prueba de consolidación en MySQL

El ensayo crea una base **nueva y aislada** con nombre `crece_migration_trial_FECHA_HORA_ALEATORIO`. No admite elegir una base existente como destino. Usa las migraciones actuales de Laravel, sin modificar `.env` ni la conexión de la aplicación que ya está corriendo. No ejecutar `migrate:fresh` sobre las fuentes o la base operativa.

## Qué comprueba

1. Verifica que las once tablas de cada base local todavía coincidan exactamente con los archivos de staging. Las consultas de origen se hacen en una transacción de solo lectura. Si los datos cambiaron desde la extracción, se detiene antes de crear la base de ensayo.
2. Copia las siete sucursales registradas a la estructura nueva. Conserva sus UUID y códigos de folio; no utiliza sucursales de demostración.
3. Conserva todas las filas de las once tablas en `legacy_trial_rows`, incluidos datos dados de baja, valores inesperados y referencias sin padre. Cada fila conserva fuente, tabla, ID original, UUID determinista, contenido crudo y SHA-256. Este archivo relacional de procedencia no sustituye las tablas de negocio.
4. Lee de nuevo el contenido almacenado y lo compara con el origen. Comprueba recuentos y hashes; no se limita a confiar en la columna de checksum.
5. Repite la carga del archivo crudo. Debe insertar cero filas y conservar exactamente los mismos UUID y valores. Un registro cambiado se rechaza y no sobrescribe lo importado. Esta idempotencia corresponde al archivo crudo; la proyección de clientes se ejecuta una sola vez por base nueva.
6. Proyecta a `clients` únicamente expedientes con CURP de formato válido y no repetida en ninguna fuente, sucursal no ambigua, nombres utilizables y fechas compatibles. CURP estructural válida no significa validación ante RENAPO. Todos los candidatos de una CURP repetida quedan en revisión; nunca se elige arbitrariamente uno.
7. Emite folios de ensayo mediante el servicio del backend y preserva el expediente original completo en `legacy_trial_client_profiles`. Contrasta cada campo del cliente y del expediente después de guardarlos. Los folios emitidos aquí no se consideran emitidos en operación.

El estado del cliente proyectado es **solo de ensayo** (`prospecto` o `baja`). No se deduce cartera activa ni morosidad a partir de un indicador histórico. No se crean cuentas, contraseñas, notificaciones, grupos operativos, contratos ni créditos/pagos operativos. Los expedientes conservados en la tabla auxiliar todavía no se convierten en `client_applications`: faltan las equivalencias revisadas de grupo, promotor y actor.

## Ejecución

Desde `backend`, con MySQL local encendido y las credenciales de Laravel ya configuradas:

```sh
php -d memory_limit=768M tools/legacy-rehearse.php /ruta/privada/staging /ruta/privada/NUEVO_ENSAYO
php -d memory_limit=768M tools/legacy-reconcile-trial.php /ruta/privada/NUEVO_ENSAYO/database.txt /ruta/privada/NUEVO_ENSAYO/conciliacion.json
```

El usuario local de MySQL necesita crear una base nueva. La herramienta usa esa cuenta en memoria; no imprime ni copia sus credenciales. El directorio de informes debe ser nuevo y su padre existir. Permisos de archivos 0600 y directorios 0700. Los datos personales del ensayo permanecen en MySQL y en los archivos privados; no subirlos al repositorio.

`summary.json` contiene recuentos, resultados de ambas pasadas y límites. `quarantine.json` identifica fuente/ID y motivos de revisión, sin nombres ni CURP. `schema-migrations.log` registra la construcción de la estructura. Si falla, `failure.json` identifica la etapa; una base de ensayo parcial se conserva para diagnosticar y nunca se reutiliza automáticamente.

## Conciliación de cobros

La segunda herramienta solo lee la base de ensayo. Compara `charges.pagoTotal` con la suma de `billing_details.pago` no dados de baja. Utiliza centavos enteros; no redondea floats ni valores negativos inesperados. Un importe inválido bloquea la comparación de esa cabecera y queda como incidencia, evitando declarar una igualdad o diferencia engañosa.

Separa referencias ausentes de referencias a padres dados de baja. **No dado de baja** es un estado técnico, no significa crédito vigente. Los agregados son importes registrados y pueden incluir exclusiones por datos inválidos; no representan un saldo contractual conciliado. El informe de diferencias no modifica movimientos ni los ajusta para forzar coincidencias.

## Criterio de aceptación de esta etapa

- Igualdad origen → archivo crudo → MySQL en todas las filas.
- Segunda carga cruda sin duplicados ni cambios de UUID.
- Coincidencia de todos los campos de clientes proyectados y expedientes preservados.
- Todo expediente de origen queda aceptado en el ensayo o identificado en revisión, sin pérdidas silenciosas.
- Registro explícito de diferencias financieras, sin autorización para producción.

Todavía requieren aceptación operativa las identidades repetidas, adscripciones entre sucursales, asignación de promotores, estados/ciclos de crédito, significado de las tasas históricas, montos solicitados recuperables, 16 vencimientos, mora, garantías, anulaciones y saldos. La carga al dominio financiero queda pendiente de esas conciliaciones y de casos contrastados con sus documentos. Esta prueba no certifica que los créditos ya funcionen en las aplicaciones.
