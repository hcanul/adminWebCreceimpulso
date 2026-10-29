# Estados de cuenta en app de clientes

Dos tablas: Personal y Grupo, con semana, vencimiento, cuota, aplicado, pendiente y estado. Verde: cuota completa; amarillo: incompleta no vencida; rojo: vencida con saldo pendiente, incluso con abono parcial. Cuotas futuras sin abono y las que vencen hoy usan estado neutro. El estado tiene texto además de color. Importes en MXN calculados en centavos; totales al pie. Desplazamiento horizontal dentro de la tabla en teléfonos.

GET /api/client/statement requiere ClientAccount activo y token client:read. Selecciona por participación del titular en un crédito entregado; no acepta IDs de préstamo/cliente para elegir información ajena. Sin crédito activo devuelve active:false. Si hay varios créditos activos o calendario incompleto/inconsistente responde 409. Se adquiere el bloqueo del crédito para leer cuotas consistentes con cobros/reversos. Calendario validado contra entrega y condiciones aprobadas.

Personal contiene únicamente las cuotas del titular. Grupo agrega las cuotas de todas las integrantes por semana: no expone nombres, CURP, identificadores de otras clientas, referencias bancarias ni comentarios internos. Las tablas reflejan capital/interés pactado y aplicaciones vigentes a cuotas (incluida garantía), no incluyen mora. Los reversos repercuten en los saldos de cuotas del backend. No se generan documentos desde el móvil.

Las cuentas demo conservan la etiqueta de datos ficticios y muestran una tabla grupal sintética de tres integrantes. Una cuenta real utiliza exclusivamente el endpoint de estado de cuenta; no toma importes de la demo.

Validación: prueba backend del flujo real de entrega/cobro, 24 comprobaciones de alcance y totales; prueba de interfaz con API simulada para 16 filas, tablas, totales y colores. Compilación con TypeScript y ESLint.
