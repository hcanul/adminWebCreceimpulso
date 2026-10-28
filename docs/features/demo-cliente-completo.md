# Ejemplo completo para la app de clientes

Disponible exclusivamente en local/testing para la cuenta ficticia CLI-S009-000001, sucursal DEMO APP — Solo pruebas, CURP con prefijo DEMO. No inserta créditos, pagos o cargos financieros: es una proyección de ejemplo de solo lectura. Los demás clientes no reciben estos datos.

Escenario fijo al 25 de octubre de 2026: capital $10,000, interés $2,800, total $12,800, 16 cuotas de $800, garantía retenida $1,000 y desembolso $9,000. Primer vencimiento 27 de septiembre de 2026; último 10 de enero de 2027. Dos cuotas completas y un pago parcial de $500 suman $2,100. Saldo contractual $10,700, cuotas anteriores vencidas $1,100, cuota del día $800 y mora $140; total exigible $2,040. Mora de $30 el 12 de octubre y $110 el 19 de octubre sobre los saldos entonces pendientes.

Pestañas: Mi crédito, Calendario, Mis pagos, Ayuda. Condiciones, garantía y desglose de atrasos expandibles. Solo se muestra historial del crédito activo, no expedientes ni información de otras integrantes.

Tres comprobantes ficticios incluyen efectivo y depósito con folio. Sus QR enlazan a verificaciones firmadas por Laravel, identificadas como DEMO SIN VALIDEZ DE PAGO. No constituyen recibos reales. Requieren acceso a la API de la Mac desde la misma red. Los comprobantes reales deberán consultar el pago y su estado de reverso en el servidor: esta demostración no sustituye esa integración.

Validación: prueba backend de 13 aserciones para importes, aislamiento de cuenta y rechazo de firma alterada; prueba móvil con API real para carga del crédito, 16 semanas, historial y generación QR. TypeScript y ESLint revisados. Datos financieros reales, recuperación de acceso y distribución siguen pendientes.
