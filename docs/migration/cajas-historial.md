# Migración del historial central de Cajas

18/11/2026. Se agregan extracción local y planificación sin escrituras operativas. No se ha identificado ni conectado la base central restaurada de Cajas. No es una octava sucursal.

## Extracción

Restaurar una copia de la base central en MySQL local. No basta con las siete bases de créditos: las observaciones están en `payment_observation_entries` y `overdue_payment_notes` del panel Cajas.

Crear fuera del repositorio un JSON privado:

```json
{"host":"127.0.0.1","port":3306,"database":"COPIA_LOCAL_CAJAS","user_env":"LEGACY_DB_USER","password_env":"LEGACY_DB_PASSWORD"}
```

Configurar las variables de credenciales en el entorno local, sin incluirlas en el archivo ni en el chat. Desde `backend`:

```sh
php tools/cajas-followup-export.php /ruta/privada/config.json /ruta/privada/NUEVO_SNAPSHOT.json
```

El exportador limita hosts a localhost, usa una transacción de lectura y selecciona columnas explícitas sin contraseñas ni credenciales de conexiones. Conserva nombres, texto, autores y fechas. Genera un archivo privado 0600 con huella SHA-256 y no sustituye uno existente. Límite piloto: 50 MiB. Si la copia tiene otra versión del esquema, se detiene para adaptar la extracción.

## Equivalencias

Preparar un mapa aprobado fuera del repositorio. Los UUID de destino deben provenir de las equivalencias definitivas; los candidatos de un ensayo no son necesariamente identidades operativas.

```json
{
  "source_timezone":"ZONA_HORARIA_CONFIRMADA_DEL_ORIGEN",
  "branches":[{"central_branch_id":1,"source_id":"carrillo","branch_id":"UUID_SUCURSAL"}],
  "participations":[{
    "source_id":"carrillo","legacy_credit_group_id":5,"legacy_credit_id":10,"legacy_demand_id":9,
    "branch_id":"UUID_SUCURSAL","loan_application_id":"UUID_CREDITO",
    "loan_participation_id":"UUID_PARTICIPACION","client_id":"UUID_CLIENTE"
  }]
}
```

Los textos `UUID_...` y la zona son marcadores; sustituirlos por valores revisados. La zona del origen debe confirmarse antes de convertir las fechas a UTC.

```sh
php tools/cajas-followup-plan.php /ruta/privada/SNAPSHOT.json /ruta/privada/MAPA.json /ruta/privada/NUEVO_PLAN.json
```

## Resultado

- Conserva identidad `(cajas_central, tabla:id)`, UUID candidato determinista, texto sin reescritura y fila cruda privada.
- Exige correspondencia explícita de sucursal/base/integrante y contrasta el crédito y cliente histórico cuando están presentes. No enlaza IDs numéricos entre sucursales.
- Notas idénticas con diferente ID o fecha siguen siendo eventos separados.
- La tabla de última observación es una caché: no se duplica si coincide con un evento en sucursal, integrante, texto, fecha y autor. Una nota sin esa coincidencia se conserva como nota histórica adicional.
- Conserva el nombre del autor histórico; no enlaza por ID numérico a cuentas nuevas. El actor de destino queda nulo hasta revisión.
- Texto, fechas o relaciones inválidas quedan en cuarentena con la fila original. Mantener estos archivos con datos personales fuera del repositorio y de carpetas públicas.

`ready_for_import` permanece **false**. Falta verificar la existencia de UUID en destino, confirmar la zona horaria, resolver cuarentena, ordenar secuencias por crédito y ejecutar una carga de ensayo que respete la unicidad de origen. La herramienta no agrega usuarios, roles, observaciones ni movimientos a una base de negocio. Tampoco clasifica créditos por encontrar una nota de atraso: el traslado requiere la decisión auditada confirmada.
