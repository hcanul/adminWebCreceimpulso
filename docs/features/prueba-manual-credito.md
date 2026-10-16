# Prueba manual de un crédito completo

Datos creados en MySQL local el 14 de octubre de 2026. Todo es ficticio y está separado de las siete sucursales operativas.

- Sucursal: **DEMO · Pruebas locales**, código **S008**.
- Grupo: **DEMO · Las Semillas**.
- Promotor asignado: **DEMO · Promotor**.
- Tres expedientes completos, enviados a Gestión y sin comprobaciones aprobadas todavía.
- No existe aún solicitud financiera, autorización ni desembolso de este grupo.

## Usuarios

| Rol | Correo |
| --- | --- |
| Gerente | demo.gerencia@example.test |
| Gestión | demo.gestion@example.test |
| Coordinación Comercial | demo.comercial@example.test |
| Caja | demo.caja@example.test |
| Promotor | demo.promotor@example.test |

La contraseña inicial está en el mensaje de entrega y en `backend/storage/app/private/manual-credit-demo.json`, archivo privado con permisos 0600, excluido de Git. No se cambiaron contraseñas de cuentas existentes ni se crearon Super Administradores. Cierra sesión para cambiar de usuario; las pestañas del mismo navegador comparten la sesión.

## Integrantes

| Cliente ficticio | Folio | CURP sintética | Cargo | Solicitar |
| --- | --- | --- | --- | ---: |
| Alfa DEMO Prueba Demo | CLI-S008-000001 | PUDA900101MQRRML07 | Presidenta | $10,000.00 |
| Beta DEMO Prueba Demo | CLI-S008-000002 | PUDB910202MQRRMT02 | Tesorera | $8,000.00 |
| Gama DEMO Prueba Demo | CLI-S008-000003 | PUDG920303MQRRMM08 | Integrante | $5,000.00 |

Las CURP tienen estructura y dígito verificador aceptados por el validador local. Son ejemplos sintéticos, no identidades verificadas por RENAPO. Los teléfonos, domicilios y referencias son ficticios. Los expedientes se crearon mediante la misma Action y las mismas validaciones que usa la aplicación.

## Recorrido

1. **Gerente:** abrir Solicitudes de crédito → Nueva solicitud. Buscar **DEMO · Las Semillas**, seleccionar Cobre ($80 por $1,000 por semana), poner límite de $12,000 por integrante e ingresar los tres montos de la tabla. Registrar y anotar el folio de crédito generado.
2. **Gestión:** entrar en Gestión de solicitudes, abrir el folio y expandir cada integrante. Marcar las seis comprobaciones por persona como verificadas con la nota **DEMO: comprobación simulada, sin documentación real**. Agregar observaciones generales y enviar a autorización. Opcionalmente devolver primero un expediente observado para probar la corrección y el reenvío del gerente.
3. **Gerente:** abrir Autorización comercial → Pendiente de condiciones del gerente. Proponer los mismos tres importes para facilitar la comparación. Seleccionar entrega En sucursal y pagos semanales por Depósito bancario (son campos independientes). Escribir **DEMO: condiciones simuladas** y enviar a Coordinación.
4. **Coordinación Comercial:** abrir Autorización comercial → Pendiente de autorización. Revisar condiciones y autorizar con **DEMO: autorización simulada**. También se puede devolver una propuesta para probar otra versión antes de aprobarla.
5. **Caja:** abrir Desembolsos y calendario → Autorizado pendiente de entrega. Capturar la fecha actual como entrega y elegir expresamente el primer vencimiento, por ejemplo siete días después. Escribir **DEMO: entrega simulada, sin movimiento real de dinero**, marcar la confirmación y registrar. Si se autorizó entrega bancaria, usar banco y referencia claramente ficticios, por ejemplo Banco DEMO y DEMO-ENTREGA-001. No realizar transferencias reales.
6. Recargar y verificar que se conserva el estado Entregado y las 16 cuotas grupales y por integrante. Caja no tiene acceso a captura de clientes/grupos ni a autorización comercial. Promotor puede consultar sus grupos y créditos, pero no autorizar ni desembolsar.

## Resultados si se aprueban los montos propuestos

| Concepto grupal | Resultado |
| --- | ---: |
| Capital | $23,000.00 |
| Cuota semanal | $1,840.00 |
| Número de cuotas | 16 |
| Interés total | $6,440.00 |
| Total a devolver | $29,440.00 |
| Garantía retenida | $2,300.00 |
| Neto entregado | $20,700.00 |

Cuotas individuales: Alfa $800, Beta $640 y Gama $400 semanales. Garantías: $1,000, $800 y $500. Netos: $9,000, $7,200 y $4,500. Tras desembolsar habrá 48 registros de cuotas individuales y 16 fechas grupales, con monto pagado inicialmente cero.

Los cobros, comprobantes QR, cortes y reversos auditados ya están implementados. La conciliación bancaria automática continúa pendiente. Para el recorrido operativo completo, consulta el [Manual de usuario](../manual/manual-de-usuario.md).

## Preparación reproducible

Herramienta: `backend/tools/create-manual-credit-demo.php`. Solo permite el entorno local y la base MySQL de desarrollo prevista. Usa transacción y bloqueo para evitar duplicados. Si el demo ya existe, informa y no reinicia el progreso ni cambia las contraseñas. No forma parte del DatabaseSeeder general. No ejecutar `migrate:fresh` ni resetear la base para repetir este ejercicio; un segundo ciclo requiere preparar otro grupo de prueba.

Se comprobó acceso mediante LoginAction para las cinco cuentas, revocando inmediatamente los tokens temporales de verificación. Se verificaron tres expedientes enviados, elegibilidad del grupo y ausencia de solicitud financiera. No se avanzó ninguna etapa manual del crédito.

## Después de la entrega: cobros

El nuevo módulo **Cobros en caja** permite continuar con `demo.caja@example.test`. La migración de cobros ya quedó aplicada en MySQL local. Conserva tu crédito y avanza manualmente; no se crearon pagos de ejemplo. Consulta [el procedimiento y las reglas de aplicación](cobros-en-caja.md).

Cada pago registrado permite abrir **Ver comprobante QR** desde su historial. Ver [uso y límites de verificación](comprobantes-qr.md).

Después de registrar pagos, usa **Cortes de caja → Preparar mi corte** con Caja. Gerencia puede consultar el cierre. La migración de cortes está aplicada; no se generaron cierres de prueba. [Procedimiento completo](cortes-de-caja.md).
