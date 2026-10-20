# Documentos del expediente y aplicación de garantía

Entrega: 17 de octubre de 2026.

## Activación

Esta entrega incorpora dos migraciones nuevas. Ejecutarlas en el backend del proyecto nuevo antes de utilizar los módulos:

```sh
cd /Users/hugocanulechazarreta/Documents/PersonalProjects/creceimpulso-workspace/backend
php artisan migrate
```

No ejecutarlas en el sistema anterior. Durante el desarrollo solamente se migró SQLite en memoria para pruebas; no se modificó MySQL local. Se crean tablas de versiones, archivos firmados, aplicaciones de garantía, detalle por cuota y reversos. No se reescriben expedientes, autorizaciones, pagos o calendarios existentes.

El permiso `guarantee.apply` se asigna inicialmente a Coordinación Comercial y Super Admin. Las sesiones abiertas deben volver a cargar el perfil (recargar la página) para reflejarlo. Se conserva el alcance por sucursal/grupos de `CreditAccess`.

## Documentos: uso

- **Clientes y expedientes → Documentos**: generar expediente del cliente o acta de instalación.
- **Revisión de Gestión → Consultar expediente capturado → Documentos y archivos firmados**: consultar los documentos de la persona revisada.
- **Detalle del crédito → Documentos**: generar el acta de autorización una vez autorizado.
- El menú **Documentos** explica estos accesos.
- **Generar** conserva una copia de los datos y del HTML de impresión. **Ver e imprimir** recupera esa misma versión. El navegador permite imprimir o guardar como PDF.
- Después de la firma manuscrita, adjuntar el PDF escaneado, hasta 10 MB. Los archivos se conservan de forma privada y solo se descargan tras comprobar acceso al expediente/crédito. No se publican bajo `public/storage`.
- Volver a emitir la misma combinación de origen, versión y plantilla recupera el documento existente. Una nueva versión de expediente genera otro documento. Volver a adjuntar los mismos bytes al mismo documento no duplica el archivo.
- Un archivo adicional no reemplaza al anterior. Se conservan autor, fecha, tamaño y huella SHA-256. No existe eliminación o sustitución silenciosa.
- Una carga no verifica la firma ni completa automáticamente el control de documentos firmados de Gestión.

## Alcance de formatos

Se revisaron `public/static/pdfs` y los helpers de impresión del sistema anterior.

| Formato anterior | Entrega actual |
| --- | --- |
| Solicitud individual / captura | Expediente del cliente: campos del catálogo actual, declaración y espacios para firma. El número de crédito no se incluye porque el expediente no es una solicitud financiera. No se captura monto solicitado aquí. |
| Acta de instalación | Domicilio capturado, croquis y comentarios para completar en visita, fecha/reunión y firmas. Multas, ahorro y ciclo no se inventan: requieren datos/reglas todavía no modelados. |
| Acta de autorización | Integrantes, cargos, solicitado frente a aprobado, tasa conservada, garantía, neto, formas de operación y primer pago pactado. No certifica desembolso. |
| Contrato/pagaré del helper `contrato.php` | Pendiente de portar y contrastar sus cláusulas y variables con las reglas actuales. El acta de autorización no lo sustituye. |
| Relación de depósitos | No portada en esta entrega; Caja y cortes conservan su detalle actual. |
| Agenda y bitácora de cobranza | Pendientes de los procedimientos de campo/departamento de cobranza. |

Por tanto, esta entrega cubre la base de emisión y conservación y tres formatos; no declara finalizada la migración de todos los documentos antiguos. La declaración del expediente proviene del formato anterior y no equivale a una revisión jurídica del conjunto de formatos.

Los documentos nuevos usan el logotipo, guinda y gris, tamaño carta, paginación de impresión, secciones legibles y firmas. Un expediente en borrador se identifica como tal. Los datos faltantes se señalan; no se inventan. El croquis y los comentarios de visita siguen siendo manuscritos en este formato.

## Garantía: regla y uso

Regla confirmada por el usuario: la aplica el encargado comercial al final, desde el vencimiento de la semana 16. La fecha se calcula desde el primer pago efectivamente registrado más 105 días, en fecha operativa de Cancún.

1. Abre un crédito entregado o pagado y selecciona **Garantía** (también accesible desde Caja).
2. Revisa retención original, aplicación vigente, saldo disponible, adeudo y monto aplicable de cada integrante.
3. Coordinación Comercial captura un motivo y confirma **Aplicar garantía al adeudo**. Antes de la semana 16 el servidor y la pantalla lo impiden.
4. El sistema aplica a cada integrante el menor entre su garantía disponible y su adeudo, a sus cuotas pendientes más antiguas. No transfiere sobrantes a otras integrantes.
5. El remanente continúa retenido e identificado. No se devuelve, transfiere ni arrastra automáticamente a otra renovación: falta definir esa regla.
6. Se conserva un movimiento con responsable, motivo y cuotas cubiertas. No es un cobro recibido: no crea ticket de efectivo/depósito ni se suma a cortes o indicadores de ingresos de Caja.
7. Si todas las cuotas quedan cubiertas, el crédito pasa a pagado. La cartera y el estado de cuenta reflejan los nuevos saldos; el estado de cuenta separa el historial de garantía del de pagos.
8. Para corregir una aplicación existe **Revertir aplicación**, con el mismo permiso y motivo obligatorio. Restituye las cuotas originales y garantía disponible, conserva el evento y puede reabrir el crédito. No redistribuye pagos posteriores ni devuelve dinero. No se permite duplicar un reverso.

No hay ejecución automática por fecha. Llegar a la semana 16 habilita la decisión de Comercial; no autoriza por sí mismo una aplicación.

## Controles técnicos

- Lecturas y movimientos de garantía bloquean el crédito para mantener consistencia con cobros/reversos. Se valida la versión de saldos y se usa un UUID de operación para reintentos idempotentes.
- Cálculos monetarios en centavos; verificación del calendario contra las condiciones originales y de la retención contra el desembolso.
- Aplicaciones y reversos en tablas separadas de `credit_payments`; no alteran registros originales ni cortes.
- Documentos escapados mediante Blade, conservación del HTML emitido con imagen embebida y SHA-256. La reimpresión no depende de la versión actual de la plantilla.
- Archivos PDF privados bajo `storage/app/private/signed-documents`, límite y tipo comprobados, descarga como adjunto y verificación de integridad. La validación de tipo no equivale a análisis antivirus ni a autenticación de firma.
- En producción, incluir tanto la base de datos como este almacenamiento privado en los respaldos. Configurar el límite del proxy/PHP para permitir archivos de 10 MB y probar la restauración.
- Servicios sobre Axios central, stores Pinia y consultas cancelables. No se guarda información documental/financiera en localStorage.

## Verificación

- 92 pruebas de backend, 1,264 aserciones, SQLite en memoria.
- TypeScript, ESLint y Pint.
- Prueba de navegador con API simulada: emisión, reimpresión, carga/descarga, recarga, bloqueo por semana, aplicación, remanente, reverso y móvil.
- PDFs de prueba con datos ficticios inspeccionados visualmente: expediente de tres páginas, instalación de una y autorización de una. El número de páginas depende del contenido.
- No se realizaron aplicaciones de garantía ni cargas sobre datos reales. Falta la comprobación operativa con MySQL, almacenamiento y volumen reales, además de impresoras físicas, antes de producción.

## Continuación: calendario autorizado (17 de octubre de 2026)

En **Crédito → Documentos → Generar Calendario de pagos** se emite un documento con cuotas individuales, las 16 fechas semanales y el importe grupal de cada vencimiento. Utiliza exclusivamente la autorización conservada y el mismo servicio de calendario que el desembolso; los totales se suman en centavos. Conserva versión, HTML y huella para reimpresión. No requiere una migración adicional a las dos indicadas arriba.

No es un estado de cuenta, contrato, pagaré ni comprobante de entrega o cobro. La aplicación de garantía no reduce automáticamente las cuotas impresas. Si una autorización histórica carece de primer vencimiento, se rechaza la emisión y se indica consultar el estado de cuenta; no se inventa una fecha. Una versión ya emitida sigue disponible.

Verificación de esta continuación: 93 pruebas de backend y 1,328 aserciones; TypeScript y ESLint sin errores. Calendario HTML revisado visualmente en Chrome con datos ficticios y 16 filas de vencimiento. No se verificó impresión física ni se modificó MySQL.

### Diferencias identificadas en contrato y pagaré antiguos

Fuente: `sistema_creceimpulso/app/Helpers/contrato.php`.

- Línea 162: pagaré con mora del 10% mensual y referencia a una serie de 16 títulos. No se ha trasladado esta condición al motor de cobros.
- Desde línea 753: contrato (además del pagaré que lo precede).
- Línea 835 aproximadamente: interés sobre saldos insolutos, distinto al cálculo global semanal aprobado para el nuevo sistema.
- Línea 842: comisión de apertura del 10%; es un concepto diferente de la garantía retenida y aplicable al final.
- Incluye referencias a impuestos, gastos y vencimiento anticipado que no tienen reglas nuevas confirmadas.
- El pagaré reemplaza la fecha recibida por la fecha actual; el nuevo calendario usa el primer vencimiento autorizado más intervalos de siete días.

La razón social fue confirmada: **PROMOTORA PENINSULAR CRECEIMPULSO LEAL S.A. DE C.V.** La respuesta inicial sobre conservar/eliminar comisión y mora fue ambigua y se solicitó aclaración explícita. Contrato y pagaré permanecen pendientes; no se agregan cargos ni cláusulas por interpretación de esa respuesta. Antes de incorporarlos también deberán definirse base de cálculo, inicio, periodicidad y tratamiento de esos cargos si se confirma su vigencia.

### Confirmación de cargos — 17 de octubre de 2026

El usuario confirmó expresamente: «Conservar comisión de apertura del 10% y mora del 10% mensual». La garantía retenida del 10% sigue siendo independiente. Esto reemplaza la ambigüedad registrada en el apartado anterior, pero no activa todavía cargos en el sistema.

Pendiente precisar antes de implementar:

1. Base de la comisión y forma de cobro: descuento del desembolso, pago separado o incorporación al financiamiento. Si se calcula sobre capital aprobado y se descuenta, un capital de $10,000 tendría $1,000 de garantía más $1,000 de comisión y un neto entregado de $8,000. Es un ejemplo condicional, no una regla confirmada.
2. Base de la mora: cuota vencida completa, capital vencido u otra base; inicio del cómputo, posibles días de gracia y cálculo proporcional por día (convención mensual) o por periodos completos.
3. Tratamiento de impuestos, orden de aplicación de pagos a estos cargos y presentación en comprobantes y cortes, según las reglas operativas que se confirmen.

Hasta resolver esas definiciones, el cálculo financiero actual y sus documentos permanecen sin cargos adicionales. Contrato y pagaré definitivos siguen pendientes de reflejar condiciones consistentes con el motor de cobros.

### Aclaración operativa posterior — 17 de octubre de 2026

El usuario precisó: «no, solo se retiene la garantia» y, para la base de mora, «solo cuota vencida».

- En el desembolso únicamente se descuenta la garantía del 10%. No se implementa descuento de comisión adicional: $10,000 aprobados menos $1,000 de garantía producen $9,000 entregados. Esta aclaración prevalece sobre el ejemplo condicional de $8,000 anterior.
- No hay autorización operativa suficientemente definida para cobrar una comisión por separado ni financiarla. No introducirla en cálculos o documentos como cargo exigible; la mención del contrato antiguo queda por conciliar, no debe confundirse con la garantía.
- La mora confirmada es del 10% mensual sobre la cuota vencida, no sobre todo el crédito ni sobre cuotas futuras.
- Sigue pendiente definir prorrateo diario frente a mes completo, convención del mes si se prorratea y días de gracia. No activar el cálculo de mora hasta resolverlo.

### Regla de mora confirmada — 17 de octubre de 2026

El usuario aclaró que se cobra por cada semana vencida, confirmó $80 por una semana de atraso de una cuota de $800 y confirmó que dos semanas acumulan $160.

Esta definición sustituye expresamente las menciones anteriores al 10% mensual:

- Mora del 10% por semana de atraso sobre cada cuota vencida.
- Una misma cuota impagada sigue acumulando mora semanal: $800 genera $80 en una semana y $160 en dos semanas. No es un cargo único por cuota.
- El ejemplo confirmado acumula cargos sobre la cuota, sin capitalizar la mora previa.
- La base no es el saldo total del crédito ni las cuotas futuras.
- Solo se retiene la garantía al desembolsar; no agregar comisión de apertura adicional.

La confirmación fija tasa y recurrencia. Antes de activar cobros se debe resolver el tratamiento de semanas incompletas, abonos parciales y orden de aplicación entre cuota y mora. No inferir cobro por semana iniciada, condonaciones ni capitalización. Esta actualización es documental; no activa todavía cargos ni modifica créditos existentes. El contrato y pagaré deberán reflejar la regla semanal confirmada, no copiar la tasa mensual del legacy.

### Regla completada y vista previa — 18 de octubre de 2026

El usuario confirmó semanas iniciadas, base sobre saldo pendiente ($300 → $30) y aplicación de cobros en orden: cuotas pendientes, mora, adelantos. Ver [Mora semanal](mora-semanal.md) para la regla vigente, implementación y límites. El estado de cuenta incorpora una simulación verificable; todavía no registra ni cobra mora. Las notas anteriores que señalan estas tres definiciones como pendientes son históricas y quedan sustituidas por esta confirmación.
