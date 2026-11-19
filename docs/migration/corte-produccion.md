# Importación de producción a la plataforma consolidada

## Cómo encaja con DDD-Lite

No se importa el SQL antiguo directamente sobre las tablas de Laravel nuevas. El origen tiene estructuras y relaciones distintas, IDs numéricos locales y datos financieros que requieren conciliación. Las siete sucursales se convierten en una sola base con UUID y `branch_id`; la base central de Cajas aporta historial adicional y no es otra sucursal.

La migración es un proceso de extracción, transformación y carga fuera del tráfico normal de la API. Debe utilizar acciones de importación separadas de las acciones de alta de créditos nuevos: la historia no debe disparar autorizaciones, folios nuevos de operación, usuarios, mensajes push, mora retroactiva o recibos como si fueran operaciones del día.

## Estado real de las herramientas

Ya existen extracción local, preflight, staging con huellas, ensayo MySQL aislado, proyección restringida de clientes y conciliación preliminar de cobros. También existe extracción/planificación de notas de Cajas. **No hay todavía una carga financiera operativa completa aprobada.** El ensayo no convierte automáticamente los créditos, aplicaciones de pagos, garantías y saldos pendientes en créditos utilizables en las apps.

Los resultados de noviembre identificaron CURP repetidas y datos por revisar. No basta con que se pueda importar un archivo SQL o con que coincidan recuentos. `ready_for_import: false` no se debe cambiar por comodidad: faltan equivalencias revisadas, transformación financiera y pruebas contra casos reales.

## Etapas

1. **Respaldar.** Exportar mediante phpMyAdmin las siete bases en producción y la base central de Cajas, incluyendo historia y bajas. Conservar archivos originales privados, fecha de extracción y SHA-256. Incluir documentos firmados/archivos que deban migrarse por separado. Los respaldos parciales tomados mientras se capturan pagos no son por sí solos un corte consistente.
2. **Restaurar localmente.** Trabajar sobre copias MySQL aisladas. El extractor local no se conecta a producción ni modifica sus tablas. El origen queda intacto.
3. **Validar y preparar correspondencias.** Identificar cada origen por `source_id` y mapear sus ciudades/sucursales. La clave de una fila es `(origen, tabla, ID antiguo)`. Conservar un mapa hacia UUID definitivo por persona, grupo, crédito, participación, pago y documento. No fusionar personas por nombre ni por un ID repetido entre bases. Una coincidencia de CURP requiere resolver identidad y adscripción sin perder ciclos históricos.
4. **Transformar al modelo nuevo.** Separar persona/expediente/solicitud/crédito y monto solicitado/aprobado; preservar condiciones financieras históricas y procedencia. Reconstruir los 16 vencimientos desde fechas verificadas y enlazar abonos a la participación correcta. Tratar anulaciones, garantía y mora con las reglas del crédito de origen; no imponer retroactivamente las reglas nuevas ni inventar solicitado cuando el legacy lo sobrescribió.
5. **Cargar en una base nueva de ensayo.** Las acciones de importación se ejecutan con transacciones e idempotencia por identidad de origen. Cambios de origen se reportan, no sobrescriben una operación ya importada. Deben conservarse fecha/usuario histórico, documento/folio antiguo y referencias bancarias. Los folios de la nueva plataforma y sus secuencias deben quedar conciliados, sin colisiones ni uso de folios de demostración.
6. **Conciliar.** Por sucursal y crédito, comparar capital, interés, cuotas, pagos válidos/anulados, saldo contractual, vencido, mora y garantía. Comparar cabecera/detalles de cobros y verificar que las notas y documentos quedaron vinculados al crédito correcto. Diferencias se explican o quedan en revisión; no crear pagos de ajuste para forzar igualdad. Separar conteos históricos de personas únicas y créditos activos.
7. **Probar la operación nueva.** Revisar expedientes, cartera ordinaria/recuperación/consolidada, estado individual y grupal, abono parcial, ticket, corte y reverso; probar aislamiento de sucursal y permisos. Clasificar recuperación solo por decisión autorizada, no a partir de una nota o de un saldo mal conciliado.
8. **Corte definitivo.** Acordar fecha y hora; detener escrituras en el sistema anterior, obtener respaldos finales consistentes de todos los orígenes, repetir la transformación y conciliación sobre una base destino nueva. Verificar diferencias desde el ensayo; no añadir por fecha sin considerar cambios retroactivos y bajas. Mientras se prepara y verifica la carga, las capturas permanecen suspendidas. Activar las escrituras nuevas solo después de validar el corte.
9. **Publicar y conservar consulta histórica.** Mantener el anterior solo en lectura y conservar los respaldos. Planificar reversión: volver sin más al respaldo anterior después de recibir pagos nuevos perdería operaciones. El procedimiento debe conservar y conciliar cualquier movimiento posterior al corte antes de decidir una reversión.

## En cPanel sin consola

El ensayo, Composer, transformación y validación se ejecutan fuera del hosting. Una vez aprobada, se exporta una **base nueva ya transformada y conciliada** con el esquema exacto de la versión desplegada y sus registros de migraciones; se crea otro usuario/base en cPanel y se importa mediante phpMyAdmin. Si el tamaño excede los límites del panel, se requiere importación asistida del proveedor o un mecanismo probado que conserve el orden y la integridad; no fragmentar arbitrariamente el SQL.

Se configuran las credenciales de la base nueva en el backend privado, HTTPS y la URL pública del frontend. No importar la base local de desarrollo con cuentas demo, tokens y contraseñas de prueba. Las cuentas móviles/activaciones y la administración inicial tienen un procedimiento propio; no generar claves masivas ni enviar recordatorios durante la importación.

La APP_KEY de destino y las claves de verificación deben ser estables. Los comprobantes legacy no adquieren retrospectivamente un QR firmado del nuevo sistema. Si se requiere reimprimir comprobantes históricos verificados, diseñar el registro de origen y su enlace de verificación, distinguiéndolo del cobro original; no registrar otro cobro.

La disponibilidad de PHP/extensiones, archivos privados, tareas programadas y límites del hosting se valida antes de la publicación. Sin cron sigue pendiente un programador externo seguro o la habilitación por el proveedor; importar datos no habilita automáticamente los recordatorios.

## Próximo paso de la importación

Cerrar el mapa definitivo de identidades y créditos y las diferencias financieras del ensayo. A continuación implementar y probar las acciones de transformación/carga histórica. Después de esa aceptación se agenda el corte de producción. Ninguna herramienta actual debe presentarse como un botón que ya migra toda la operación.

Referencias internas: [Ensayo MySQL](ensayo-mysql.md), [Historial de Cajas](cajas-historial.md), [Preparación de cPanel](../deployment/cpanel-sin-consola.md).
