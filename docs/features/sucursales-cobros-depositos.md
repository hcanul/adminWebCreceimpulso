# Sucursales, cobros y depósitos

## Habilitar la actualización

Desde la carpeta `backend` del proyecto, con la configuración de la base de datos de destino revisada y un respaldo previo:

```sh
php artisan migrate
```

La migración `2026_11_18_020000_add_branch_management` incorpora contacto, domicilio operativo y control de versiones de sucursales. No reemplaza la base de datos ni importa información del sistema anterior. En esta entrega se verificó con SQLite de pruebas; queda pendiente aplicarla a la base MySQL operativa.

Si se usa el frontend compilado, generar nuevamente su distribución desde `frontend/apps/admin-web`:

```sh
pnpm build
```

En desarrollo basta con recargar el frontend una vez que las migraciones estén aplicadas. Para hosting sin consola, consultar `docs/deployment/cpanel-sin-consola.md`; este comando corresponde al entorno local o a un servidor con acceso a terminal.

## Sucursales

Acceso: menú **Sucursales**, disponible para **Super Admin** y **Gerente General**. Otros perfiles conservan el acceso a la información de las sucursales que corresponda a su operación, sin administrar este catálogo.

1. Seleccionar **Nueva sucursal**.
2. Capturar nombre y, opcionalmente, localidad, teléfono, correo y domicilio operativo.
3. Guardar. El backend asigna el código permanente usado por los folios; el UUID identifica el registro.
4. Buscar por nombre, localidad o código y filtrar activas/inactivas.
5. Abrir **Editar sucursal** para corregir los datos o cambiar el estado.

El código y el UUID permanecen al modificar el nombre. El listado se pagina en el servidor. Los cambios quedan auditados con actor y valores anteriores/nuevos. Si dos personas editan a la vez, se rechaza una versión desactualizada. Si se pierde la respuesta de un alta, **Reintentar** reutiliza la misma operación para evitar duplicados.

Para desactivar una sucursal primero deben resolverse sus solicitudes/créditos en operación y reasignarse o desactivarse sus usuarios activos. No se eliminan clientes, pagos ni historia. Los datos legales de contrato y pagaré se mantienen en su configuración documental separada; el enlace correspondiente sigue disponible para Super Admin.

## Cobros y depósitos

Acceso: menú **Cobros y depósitos**, con permiso `cash.view`, respetando el alcance de sucursales del usuario.

1. Seleccionar fechas, sucursal y medio: efectivo, depósito o ambos.
2. Opcionalmente, buscar folio bancario, folio de crédito, nombre de grupo o cajero.
3. Pulsar **Consultar**. El resumen y el detalle usan los mismos filtros; estos se conservan en la URL al recargar.
4. Para capturar, seleccionar **Registrar cobro o depósito** (requiere `credit.collect`). Se abre el flujo de cobranza existente: seleccionar crédito, distribuir importes y elegir el medio.
5. En un depósito, capturar los datos de la ficha bancaria requeridos por el formulario. Se registra como depósito bancario para su corte; no constituye una conciliación automática con el banco.
6. Consultar **Ver comprobante** para abrir el ticket del cobro, si se dispone de permiso de cobro o reverso.
7. Descargar **Todos los movimientos CSV** para obtener todas las páginas del filtro, o **Imprimir resumen** para el consolidado.

El detalle muestra sucursal, crédito, grupo, cajero, medio, importe, banco, referencia, fecha del depósito y corte original cuando exista. Los reversos aparecen como ajustes separados, conservando el registro y corte originales. La fecha del cobro y la del reverso determinan su periodo de reporte, respectivamente.

Se reutiliza el registro de pagos existente: cuotas pendientes más antiguas, mora y después adelantos. Efectivo y depósitos se mantienen separados en los cortes de caja. Los perfiles de consulta no obtienen permisos para capturar por entrar a este apartado.

## Verificación realizada

- Backend: 206 pruebas, 2,456 comprobaciones, con base aislada SQLite.
- TypeScript, compilación SPA y revisión de estilo.
- Pantalla de sucursales con API simulada: creación, pérdida de respuesta/reintento sin duplicado, edición, recarga, permisos y ancho móvil.
- Pantalla de cobros con API simulada: búsqueda persistida, enlace a captura/comprobante, CSV y resumen imprimible. Regresión de captura de depósito: aportación solidaria, reintento idéntico e historial tras recargar.
- La ejecución contra MySQL operativa y la captura real de fichas bancarias deberán verificarse después de aplicar las migraciones.
