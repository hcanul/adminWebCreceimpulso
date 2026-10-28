# Ruta de cierre del admin-web

Actualización: 24 de octubre de 2026. Continúa la ruta de `acuerdos-y-ruta.md`; los módulos móviles permanecen posteriores al cierre operativo del administrador.

## Implementado y verificado en desarrollo

- Captura de grupos, promotores y expedientes; comprobación de CURP.
- Solicitud, Gestión, autorización, entrega y calendario de 16 semanas.
- Cobros parciales, aportaciones solidarias y anticipos; tickets QR.
- Cortes separados de efectivo y depósitos; reversos auditados.
- Aplicación de garantía desde semana 16; mora semanal según política autorizada.
- Estado de cuenta, Cartera y Dashboard con mora.
- Reporte CSV de cartera filtrada para revisión de Gerencia.
- Conservación de versiones documentales; contrato y pagaré grupal todavía para revisión.

Esta lista describe capacidades implementadas, no una certificación de preparación para producción.

## Orden de continuación

1. Completar los datos de suscripción y lugares de pago de contrato/pagaré, y el texto definitivo. El pagaré ya tiene confirmados capital más interés y vencimiento en la semana 16. Mantener propuestas anteriores inmutables.
2. Ejecutar un recorrido de aceptación en el entorno de desarrollo con MySQL: captura, revisión, autorización, desembolso, cobro, corte, reverso, garantía y cierre. Conciliar Caja, estado de cuenta, cartera, dashboard y reporte. Verificar impresoras y documentos definitivos.
3. Verificar respaldo/restauración de base y documentos privados; revisar permisos por los perfiles reales y rendimiento con un volumen representativo. Registrar incidencias y criterios de salida.
4. Iniciar app de clientes y flujo de activación de identidad definido con la oficina. Limitar la consulta al crédito activo y sus comprobantes.
5. Implementar actualización offline de clientes y grupos para promotores, con conflictos explícitos y sin cobros de campo.
6. Ensayar la consolidación de las siete sucursales con conciliación y correspondencia de identidades; piloto y despliegue progresivo.

El departamento de cobranza y la devolución del remanente de garantía requieren reglas operativas adicionales; no se han incorporado por suposición. Las pruebas de desarrollo no reemplazan la aceptación con la oficina.

## Avance del 24 de octubre: configuración y MySQL

El propietario autorizó completar datos documentales al iniciar operaciones y permitir perfil general o por sucursal. El apartado ya está implementado; faltan la formalización por crédito y los documentos definitivos. Confirmó activación presencial por código para la app de clientes; ver `activacion-clientes.md`.

Se incorporó `backend/scripts/verify-mysql.php`: crea una base aleatoria local, ejecuta la batería allí y elimina la base al finalizar. Nunca ejecuta migraciones sobre la base configurada de trabajo. No permite servidores remotos ni DB_URL. La comprobación detectó y corrigió una comparación de la política de mora dependiente del orden de claves JSON; MySQL normaliza ese orden. Las pruebas de rollback ahora inyectan fallos sin triggers exclusivos de SQLite.

La base de trabajo consultada aún tiene pendiente la migración de mora; también requiere la nueva migración de perfiles. El cierre operativo sigue sujeto a respaldo/restauración, impresión y aceptación de oficina. No equivale todavía a aprobación para producción.

Resultado final de la batería aislada: **125 pruebas y 1,676 aserciones aprobadas en MySQL 8.0.31**, con eliminación confirmada de la base temporal. La misma batería pasó en SQLite. Esta validación cubre el recorrido automatizado y no reemplaza la impresión física, pruebas de concurrencia/carga o restauración de un respaldo real.


## Avance: acceso presencial de clientes

La emisión de códigos desde admin-web y el consumo atómico mediante API están implementados; ver [activacion-clientes.md](activacion-clientes.md). La pantalla de activación e inicio de sesión de la app sigue pendiente. La suite ampliada pasa 129 pruebas/1709 aserciones en SQLite; repetir en MySQL cuando la conexión local esté disponible. No se aplicaron migraciones sobre la base de trabajo.
