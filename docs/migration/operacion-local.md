# Consolidación local — extracción, staging y validación

Primera etapa preparada el 3 de noviembre de 2026. Conserva las siete bases separadas y utiliza copias restauradas. La importación financiera a la base nueva sigue pendiente de examinar los datos reales y acordar correspondencias y conciliaciones.

## Archivos

- `local-sources.example.json`: plantilla de las siete fuentes. Copiarla fuera del repositorio y cambiar los nombres de bases a los realmente restaurados. Cada `source_id` es permanente y no debe cambiar entre ejecuciones.
- `backend/tools/legacy-export.php`: exportación de las tablas de crédito conocidas desde MySQL local, mediante transacciones de solo lectura y lecturas sin buffer. Los importes y los IDs se conservan como cadenas. Incluye esquema e inventario de motores; omite tablas ausentes para que el preflight las marque. Rechaza tablas no InnoDB en este piloto, destinos existentes y archivos mayores de 50 MiB.
- `backend/tools/legacy-stage.php`: conserva cada fila cruda en NDJSON, junto con `source_id`, tabla e ID anterior. Produce un manifiesto de recuentos y SHA-256 por archivo y el plan de incidencias y UUID candidatos. No arranca Laravel ni se conecta a la base de destino.
- `backend/tools/legacy-preflight.php`: informe estructural sin escribir en ninguna base.

## Preparación

1. Restaurar cada SQL en una base local distinta, por ejemplo `legacy_carrillo`, `legacy_tulum`, etc. No restaurarlos sobre `creceimpulso_db` ni concatenar los archivos SQL.
2. Indicar nombres reales y sucursal de cada base. Los nombres de la plantilla son ejemplos, no una detección automática.
3. Utilizar una cuenta local con SELECT sobre los orígenes y definir en la terminal `LEGACY_DB_USER` y `LEGACY_DB_PASSWORD`. Los scripts leen esas variables; no introducir claves en el JSON, el repositorio ni el chat. Las credenciales de la base nueva no se utilizan.
4. Revisar el mapa `branches`: ID de `citys` en cada base → UUID de sucursal en destino. Puede exportarse con mapa vacío para inventario; el informe bloqueará la preparación definitiva por sucursales sin correspondencia.

## Ejecución desde backend

Las rutas siguientes son ejemplos: el directorio privado debe existir y cada archivo/directorio de salida debe ser nuevo.

```sh
php tools/legacy-export.php /ruta/privada/origenes.json /ruta/privada/snapshot.json
php tools/legacy-preflight.php /ruta/privada/snapshot.json > /ruta/privada/preflight.json
php tools/legacy-stage.php /ruta/privada/snapshot.json /ruta/privada/staging-nuevo
```

Preflight/staging salen con código 2 cuando hay incidencias; el staging conserva esas filas para revisión y marca `ready_for_import: false`. Código 0 significa únicamente controles estructurales aprobados. Código 1 significa rechazo de entrada/configuración; revisar permisos y formato sin publicar datos personales. Los archivos quedan con permisos 0600 y los directorios de staging con 0700. No compartir el staging crudo como reporte público.

Cada conexión tiene su propia instantánea consistente; esto no garantiza que los siete respaldos correspondan al mismo corte. `captured_at_utc` es la hora de extracción local, no la fecha original de corte financiero. Exportación piloto limitada a las once tablas del crédito conocidas; usuarios, nómina, documentos y otros dominios requieren inventario y tratamiento adicionales. Para bases que excedan 50 MiB se necesita ampliar el pipeline a lotes, antes de ejecutar la extracción completa.

## Demostración

`~/Downloads/Crece-Migracion-Demo-2026-11-03` contiene siete fuentes ficticias, con los mismos IDs locales de sucursal y tablas financieras vacías. `staging/manifest.json` registra 77 archivos y `staging/plan.json` identifica UUID candidatos diferentes por fuente. Los UUID de sucursal de la demo son ficticios y no deben copiarse a la configuración real. Esta muestra verifica el procedimiento de archivos, no la conciliación de créditos.

## Siguiente etapa al recibir las bases

Comparar esquemas y recuentos reales, confirmar mapas por sucursal, detectar personas repetidas por CURP y obtener casos representativos de créditos activos/liquidados, parciales y anulados. Preparar equivalencias explícitas origen/tabla/id → entidad/UUID destino, transformar los 72 campos y validar tasas, capital, intereses, garantía, mora y pagos. Solo después se construye y ensaya la carga transaccional en una copia aislada de la estructura nueva. No reinterpretar automáticamente tasas o montos solicitados sobrescritos por el sistema antiguo.

## Ejecución real del 3 de noviembre

Las siete bases restauradas se inventariaron y exportaron por separado, manteniendo el límite de 50 MiB por instantánea. Se comprobó el staging con hashes. Ver `resultados-2026-11-03.md`. Para informes preliminares grandes puede usarse `legacy-preflight.php snapshot.json --summary`: conserva recuentos e incidencias y omite la lista completa de UUID candidatos; no altera la validación ni permite importar.

## Ensayo aislado con estructura actual

El siguiente escalón es `tools/legacy-rehearse.php`, que crea una base MySQL nueva, comprueba el archivo crudo y ensaya clientes sin ambigüedades. `tools/legacy-reconcile-trial.php` contrasta cabeceras/detalles de cobro sin modificar movimientos. Ver [alcance, ejecución y límites](ensayo-mysql.md). No es una autorización de carga financiera a producción.
