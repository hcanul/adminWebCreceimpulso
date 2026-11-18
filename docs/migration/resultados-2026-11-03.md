# Diagnóstico inicial de las siete bases — 3 de noviembre de 2026

Se consultaron las siete bases locales y la lista de sucursales del sistema nuevo. No se modificaron ni importaron registros en ninguna base. Se exportaron las once tablas conocidas del crédito, una fuente por archivo, y se ejecutaron controles preliminares por fuente.

## Volumen histórico inventariado

| Sucursal | Base | Solicitantes/expedientes | Grupos | Créditos | Cobros | Detalles de cobro |
|---|---|---:|---:|---:|---:|---:|
| Carrillo | creceimp_creceimpulso | 1,524 | 227 | 1,353 | 24,620 | 120,214 |
| Tulum | creceimp_tulum | 1,435 | 252 | 941 | 17,271 | 83,714 |
| Morelos | creceimp_morelos | 977 | 203 | 1,287 | 21,741 | 97,218 |
| Playa 1 | creceimp_playa | 1,699 | 294 | 933 | 15,364 | 71,551 |
| Playa 2 | creceimp_playa2 | 1,376 | 256 | 740 | 11,199 | 48,494 |
| Cancún 1 | creceimp_cancun | 1,087 | 192 | 652 | 11,087 | 49,964 |
| Cancún 2 | creceimp_cancun2 | 1,268 | 231 | 704 | 11,241 | 51,447 |
| **Total** | | **9,366** | **1,655** | **6,610** | **112,523** | **522,602** |

Estos recuentos incluyen historia y bajas; no equivalen a personas únicas ni a cartera activa. Todas las fuentes contienen 28 tablas InnoDB. Las tablas fuera del crédito aún requieren tratamiento propio.

## Hallazgos que requieren revisión

- 69 filas de solicitantes con CURP fuera del patrón estructural revisado. Esta comprobación no es validación RENAPO.
- 319 grupos de coincidencias de CURP válidas en formato; 131 abarcan más de una base. No se fusionó ninguna identidad.
- 48 solicitantes llevan una sucursal interna diferente de la sucursal principal de su base: Playa 1 (1), Playa 2 (16), Cancún 1 (17), Cancún 2 (14). Pueden reflejar movimientos históricos o capturas incorrectas; revisar antes de decidir la adscripción actual.
- Diferencias de columnas: `charges.transa` es texto de distinta longitud o entero según la fuente. En Carrillo varios indicadores de `credits` permiten NULL y en las otras fuentes no. Se preservaron los valores y esquemas; no se normalizaron por suposición.
- 19 cabeceras de cobro no coinciden con la suma de sus detalles no dados de baja según el control preliminar.
- 10 importes no cumplen el formato decimal exacto del intercambio; requieren revisar negativos/precisión y su significado antes de convertir.
- 51610 referencias no resuelven dentro de su propia base en las relaciones comprobadas. Incluyen historia y posibles registros eliminados; no deben interpretarse como errores de cartera activa sin clasificación adicional. Nunca se enlazarán contra otra base por compartir un ID numérico.

## Artefactos privados

`inventario.json`: esquema completo y recuentos; `origenes-mapeados.json`: correspondencias de catálogos; `snapshots/`: datos crudos por fuente con hashes en sus registros de extracción; `preflight/`: incidencias por tabla/ID; `revisiones-identidad.json`: referencias para revisión sin nombres/CURP en el informe; `resumen.json`: indicadores consolidados. Los archivos crudos permanecen fuera del repositorio.

## Siguiente paso

Clasificar las incidencias por estado histórico/activo; preparar la tabla de correspondencias de personas, sucursales y operaciones; contrastar ejemplos de créditos y saldos; y construir la transformación hacia una base de ensayo con la estructura nueva. Los UUID candidatos de filas antiguas no son automáticamente el UUID definitivo de una persona consolidada. El sistema nuevo contiene sucursales de demostración/QA: no se tomarán como destino de las siete bases.

La carga financiera continúa bloqueada hasta la conciliación y las decisiones de tratamiento. No se imputaron cuotas, intereses, mora ni garantías ni se corrigieron saldos de origen.

## Verificación de staging y destino

Se generaron 77 archivos NDJSON de las once tablas del crédito de las siete fuentes y se verificaron sus huellas SHA-256. El staging conserva las filas con incidencias; no es una importación aprobada. Las coincidencias de CURP entre fuentes antiguas se reportan para revisión. La comprobación adicional contra `clients` del destino no encontró coincidencias de CURP con las filas antiguas en esta ejecución.
