# Ensayo escalonado de clientes: Carrillo primero

## Alcance

Herramienta: `backend/tools/branch-client-rehearsal.php`. Transforma **clientes** de la estructura antigua (`demands`) hacia `clients` del esquema Laravel actual, conserva la fila completa del expediente en una tabla auxiliar de procedencia y mantiene la equivalencia de ID antiguo → UUID y folio nuevo.

Este ensayo no importa créditos, grupos, solicitudes operativas, movimientos, saldos o notas de Cajas ni genera cuentas. Los clientes se cargan como `prospecto` o `baja`, sin inferir situación financiera. Tampoco crea un `client_application` utilizable hasta resolver grupo/promotor/actor y las validaciones del flujo nuevo. No basta esta carga para entrar a client-app o procesar un crédito histórico.

## Antes del respaldo del viernes

Podemos probar la planificación con datos ficticios, sin MySQL, desde `backend`:

```sh
php tools/branch-client-rehearsal.php --help
mkdir -p "$HOME/CreceImpulso-Ensayos"
php tools/branch-client-rehearsal.php plan-snapshot \
  tools/fixtures/branch-clients-demo.json \
  "$HOME/CreceImpulso-Ensayos/plan-demo-1"
```

El último directorio debe ser nuevo. La muestra produce un cliente candidato y uno en revisión. No contiene personas reales y no abre Laravel ni conexiones a bases. Para repetir, elegir otra carpeta o consultar el resultado existente. No editar un plan para omitir controles.

## Preparar Carrillo

1. Descargar el respaldo SQL de Carrillo desde producción. Guardar el original sin editar, su fecha de extracción y una huella SHA-256. No subirlo al repositorio ni a una carpeta pública.
2. Restaurarlo en **otra base local**, por ejemplo `carrillo_respaldo_viernes`. Este script no ejecuta archivos SQL; la restauración se hace con phpMyAdmin o el cliente MySQL. Revisar si el respaldo contiene `CREATE DATABASE` o `USE` antes de importarlo, para no sobrescribir una base existente.
3. Copiar `docs/migration/carrillo-clientes.example.json` a una carpeta privada fuera del repositorio. El ejemplo ya trae el `source_id` estable `creceimp_creceimpulso`, el UUID de Carrillo del registro de sucursales y su `legacy_city_id=1`; confirmar estos datos contra el respaldo. No cambiar `source_id` porque cambió el nombre de la copia local o llegó un respaldo nuevo.
4. En `source.database`, indicar el nombre real de la base restaurada. La configuración permite solo localhost. `lab_connection` es la conexión local con permiso de crear una base nueva de ensayo; puede usar otro usuario/puerto. No usa las credenciales de producción.
5. Confirmar la zona en que el sistema antiguo guardó `created_at`, `updated_at` y `deleted_at`. Escribirla en `source_timezone` y cambiar `source_timezone_confirmed` a `true`. El ejemplo bloquea la ejecución hasta esa confirmación: no asumir UTC o Cancún por la ubicación de la sucursal. El plan transforma las fechas a UTC y conserva la fila original.
6. Escribir en `backup_label` la fecha/hora real del respaldo, no una fecha supuesta a partir del nombre del archivo.

El ejemplo separa nombres de variables de entorno y configuración; **no contiene contraseñas**. Configurarlas en la Terminal local, sin pegarlas en el chat ni ponerlas en los argumentos del comando. En zsh pueden solicitarse sin eco:

```sh
read -r 'LEGACY_DB_USER?Usuario MySQL de lectura de la copia local: '
read -rs 'LEGACY_DB_PASSWORD?Contraseña local: '
export LEGACY_DB_USER LEGACY_DB_PASSWORD
read -r 'TRIAL_DB_USER?Usuario MySQL para crear el ensayo: '
read -rs 'TRIAL_DB_PASSWORD?Contraseña local del ensayo: '
export TRIAL_DB_USER TRIAL_DB_PASSWORD
```

El usuario origen idealmente solo tiene lectura. El usuario de laboratorio necesita crear bases y ejecutar las migraciones en la nueva base. No compartir sus contraseñas. Las variables se usan solo en memoria; `lab.json` guarda nombres de variables, no sus valores.

## Tres pasos de ejecución

Desde la carpeta `backend` del proyecto, con PHP compatible y las dependencias instaladas:

```sh
# 1. Extraer y planificar Carrillo. Lee la copia local, no escribe en ella.
php tools/branch-client-rehearsal.php plan \
  "$HOME/CreceImpulso-Ensayos/carrillo-local.json" \
  "$HOME/CreceImpulso-Ensayos/plan-carrillo-viernes-1"

# 2. Crear UNA base nueva aislada con las migraciones actuales.
php tools/branch-client-rehearsal.php init-lab \
  "$HOME/CreceImpulso-Ensayos/carrillo-local.json" \
  "$HOME/CreceImpulso-Ensayos/laboratorio-1"

# 3. Cargar únicamente los clientes aceptados en ese laboratorio.
php tools/branch-client-rehearsal.php apply \
  "$HOME/CreceImpulso-Ensayos/plan-carrillo-viernes-1" \
  "$HOME/CreceImpulso-Ensayos/laboratorio-1"
```

La base se llama `crece_branch_trial_FECHA_HORA_ALEATORIO`: se crea con `CREATE DATABASE`, sin reutilizar una existente. Las migraciones crean el esquema actual y permisos/catálogos en ese laboratorio; no se ejecuta el seeder general de usuarios, no se crean administradores ni contraseñas. La conexión se configura solo en el proceso, sin modificar `.env`. Si existe configuración Laravel cacheada, el script se detiene para revisarla, sin borrar ni reescribir el caché.

El modo `apply` solo admite el nombre reservado de ensayo y un marcador interno coincidente. No acepta una base de producción como destino. No elimina el laboratorio al terminar o fallar: queda para inspección. El bloqueo del marcador serializa las cargas del piloto. No modificar manualmente el marcador para apuntar a otra base.

## Resultados

En el plan:

- `snapshot.json`: campos originales completos de `demands` y catálogo de ciudades. Es información personal privada.
- `plan.json`: candidatos transformados y filas identificadas para revisión por motivo. `ready_for_production` siempre permanece `false`.
- `SHA256.txt`: huellas de los archivos extraídos en modo `plan` para comprobar su conservación. No sustituyen la revisión de procedencia del respaldo.

En el laboratorio:

- `lab.json`: ubicación de la base nueva, marcador y nombres de variables de credenciales.
- `schema-migrations.log`: instalación del esquema.
- `result-FECHA-ALEATORIO.json`: clientes insertados, reconocidos en reintento y pendientes de revisión.

En MySQL de ensayo:

- `clients`: UUID, sucursal, CURP normalizada, nombres, teléfono, calle, estado técnico y folio generado por el servicio nuevo.
- `branch_trial_client_maps`: correspondencia por `(source_id, legacy_id)`, UUID, folio, hash y expediente original completo. No se pierden campos porque no existan como columna en `clients`; aún no se presentan automáticamente en el formulario nuevo.
- `branch_trial_sources`: sucursal y huella del respaldo incorporado.
- `branch_trial_marker`: identificación del laboratorio.

El folio del ensayo sirve para probar asignación y reintentos; no se declara emitido en producción. No confundir el UUID candidato de una fila histórica con una identidad ya conciliada entre sucursales.

## Verificar reintentos y agregar otra sucursal

Repetir exactamente el comando `apply` sobre el mismo plan y laboratorio: debe insertar **cero**, reconocer los clientes ya cargados y conservar sus UUID/folios. Si alguien modificó un cliente importado, el reintento se detiene sin sobrescribir esa edición. Un respaldo diferente del mismo `source_id` no reemplaza la carga anterior: exige un nuevo laboratorio de ensayo. La actualización incremental de un respaldo ya incorporado es una etapa posterior.

Para probar la siguiente sucursal, crear otra configuración con su `source_id`, UUID y ciudad del registro aprobado, generar otro plan y aplicar **al mismo laboratorio**. No ejecutar otra vez `init-lab` para agregar la sucursal. Los IDs numéricos iguales entre bases reciben correspondencias separadas. Una CURP que ya exista en el destino queda en revisión; no se mueve de sucursal ni se fusiona automáticamente. La revisión entre sucursales es necesaria aunque Carrillo se importe primero.

## Qué queda en revisión

CURP con formato inválido, CURP repetidas dentro de la fuente, sucursal ambigua o no mapeada, nombres/campos incompatibles, fechas inválidas y coincidencias con clientes ya existentes. La validación de formato no certifica identidad ante RENAPO. Los candidatos aceptados siguen siendo de ensayo. El archivo original conserva también los registros pendientes.

No se inventan montos, condiciones ni estados de crédito. Límites de este piloto: 100,000 filas de `demands`, archivos de 50 MiB, MySQL local e InnoDB. El plan conserva los datos en memoria; para un respaldo mayor debe prepararse una variante por lotes.

## Paso posterior hacia producción escalonada

Después de revisar Carrillo: resolver equivalencias de identidades, grupos, promotores, ciclos y saldos; implementar y ensayar su transformación financiera. Acordar un corte de captura **para cada sucursal** y conservar un registro central de cuáles ya migraron. Una sucursal activada deja de capturar en el sistema anterior.

La primera base validada puede instalarse completa si el destino está vacío. **Desde que la plataforma nueva recibe operaciones, nunca reemplazar toda la base desde una copia local para añadir otra sucursal.** Se necesita un importador incremental que trabaje sobre el destino vigente, compruebe las equivalencias/idempotencia y preserve pagos, notas, usuarios y secuencias ya emitidas. Si cPanel no ofrece consola, esa etapa requerirá un mecanismo acotado de importación desde Admin Web o ejecución asistida del proveedor; este piloto no lo instala ni exporta SQL incremental para producción.

No crear pagos para cuadrar diferencias, no reasignar CURP por conveniencia y no activar una sucursal antes de conciliar su cartera. Este script es el primer ensayo de clientes; no una migración completa de negocio.

## Verificación de esta entrega

Suite completa: **202 pruebas y 2,411 comprobaciones aprobadas**, incluidas siete pruebas nuevas del piloto. Cubren transformación de campos y fechas, expediente íntegro, reintento sin emitir otro folio, sucursales escalonadas, CURP ya presente en destino, filas ambiguas, cambios de origen/destino rechazados, marcador incorrecto, rollback y planificación CLI sin conexión. También se ejecutó `plan-snapshot` con la muestra ficticia: 2 filas, 1 candidata y 1 en revisión.

Las pruebas de carga utilizaron SQLite temporal con el esquema actual. La ejecución de `plan/init-lab/apply` contra MySQL real **sigue pendiente**: el servicio local no estaba disponible en el puerto 3306 y el respaldo del viernes aún no se ha recibido/restaurado. No se modificaron bases operativas ni producción. Formato PHP y sintaxis comprobados.
