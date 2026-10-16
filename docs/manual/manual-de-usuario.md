# Crece Impulso · Manual de usuario

**Edición 1.0 — 15 de octubre de 2026**  
**Aplicación:** portal administrativo web.  
**Dirigido a:** Gerencia, Gestión, Coordinación Comercial, Caja, promotores y administración del sistema.

Este manual describe las funciones implementadas hasta los reversos auditados de pagos. Los nombres de opciones pueden variar ligeramente según los permisos de la cuenta. Las aplicaciones móviles independientes, la operación sin conexión y el portal de clientes no forman parte de este recorrido.

## Contenido

1. [Acceso y uso general](#1-acceso-y-uso-general)
2. [Perfiles y responsabilidades](#2-perfiles-y-responsabilidades)
3. [Inicio e indicadores](#3-inicio-e-indicadores)
4. [Usuarios, permisos y promotores](#4-usuarios-permisos-y-promotores)
5. [Crear el grupo solidario](#5-crear-el-grupo-solidario)
6. [Registrar al cliente y su expediente](#6-registrar-al-cliente-y-su-expediente)
7. [Tasas y cálculos](#7-tasas-y-cálculos)
8. [Crear la solicitud de crédito](#8-crear-la-solicitud-de-crédito)
9. [Revisión de Gestión](#9-revisión-de-gestión)
10. [Propuesta y autorización comercial](#10-propuesta-y-autorización-comercial)
11. [Entrega del crédito](#11-entrega-del-crédito)
12. [Registrar cobros](#12-registrar-cobros)
13. [Comprobantes y verificación QR](#13-comprobantes-y-verificación-qr)
14. [Cortes de caja](#14-cortes-de-caja)
15. [Reversar un pago incorrecto](#15-reversar-un-pago-incorrecto)
16. [Rutina de trabajo por perfil](#16-rutina-de-trabajo-por-perfil)
17. [Problemas frecuentes](#17-problemas-frecuentes)
18. [Estados y términos](#18-estados-y-términos)
19. [Ejercicio de capacitación](#19-ejercicio-de-capacitación)
20. [Alcance actual](#20-alcance-actual)

## 1. Acceso y uso general

### Iniciar y cerrar sesión

1. Abre la dirección del portal indicada por administración. En el entorno local de desarrollo se utiliza `http://localhost:9001/#/`; esta dirección solo funciona en el equipo donde se ejecuta la aplicación.
2. Escribe tu correo electrónico y contraseña.
3. Selecciona **Ingresar al sistema**.
4. Revisa tu sucursal y las opciones disponibles en el menú lateral. En pantallas pequeñas, abre el menú con su botón.
5. Al terminar en un equipo compartido, cierra la sesión. Para recuperar una cuenta o contraseña, contacta a administración del sistema.

La sesión se conserva al recargar. Si aparece un problema de verificación de sesión, utiliza **Reintentar conexión**. Que la sesión permanezca abierta no significa que un formulario sin guardar esté protegido.

### Buscar y seleccionar

Los campos de catálogo permiten escribir para filtrar. Después de escribir, selecciona una coincidencia: el texto escrito por sí solo no representa una selección válida. Para buscar grupos, escribe al menos dos caracteres y afina el nombre si hay demasiadas coincidencias. Comprueba también sucursal y promotor, porque varios grupos pueden llamarse igual.

### Guardar y confirmar

- **Guardar borrador** conserva el avance del expediente en el servidor.
- **Enviar a revisión** cambia su etapa y restringe la edición.
- Las confirmaciones muestran el efecto de una operación antes de registrarla. Lee el mensaje completo.
- Los datos guardados se recuperan al volver a entrar. Los cambios sin guardar pueden perderse al recargar, cerrar la pestaña o salir.
- Si un registro cambió mientras lo consultabas, actualiza y revisa de nuevo antes de confirmar. El sistema evita sobrescribir el trabajo de otra persona.

Las operaciones actuales requieren conexión. No captures cobros suponiendo que se enviarán automáticamente cuando vuelva internet.

## 2. Perfiles y responsabilidades

Esta tabla resume la configuración inicial. Administración puede ajustar permisos; la sucursal y el alcance de cada cuenta siguen determinando qué información puede consultar.

| Perfil | Trabajo principal |
| --- | --- |
| Super Admin | Administración de usuarios, roles y permisos; acceso operativo global. |
| Gerente General | Supervisión y operación global, tasas, revisión, autorizaciones y caja según permisos. No administra técnicamente usuarios y roles. |
| Gerente | Grupos y expedientes de su sucursal, solicitudes, propuesta de condiciones, entrega, consulta de cortes y reversos. No cobra ni autoriza comercialmente por defecto. |
| Gestión | Revisión de identidad, domicilio, referencias, visita, historial y documentos; devolución o envío a autorización. |
| Coordinación Comercial | Autorizar o devolver las condiciones propuestas en su sucursal. |
| Caja | Registrar entregas y cobros, emitir comprobantes y cerrar sus propios cobros. Puede consultar reversos, pero no ejecutarlos. |
| Promotor | Preparar grupos y expedientes dentro de su alcance. No cobra ni autoriza créditos. |
| Coordinador de campo | Captura y consulta dentro de su alcance. Es distinto de Coordinación Comercial. |

Solo Gerente, Gerente General y Super Admin pueden enviar expedientes a revisión en la configuración inicial. El promotor prepara borradores.

**Recorrido general:** grupo → clientes y expedientes → solicitud grupal → Gestión → propuesta del gerente → autorización comercial → entrega → cobros y comprobantes → cortes. Los reversos corrigen pagos ya registrados cuando corresponde.

## 3. Inicio e indicadores

La pantalla de Inicio muestra información real dentro del alcance de tu cuenta.

| Indicador | Qué representa |
| --- | --- |
| Grupos solidarios | Grupos activos que puedes consultar. |
| Solicitudes en revisión | Solicitudes de crédito pendientes de Gestión o en revisión; no cuenta cada expediente individual como una solicitud de crédito. |
| Créditos activos | Créditos entregados que todavía no están liquidados. Un crédito aprobado sin entregar no entra aquí. |
| Cobros del día | Pagos vigentes recibidos hoy, separados en efectivo y depósitos. Excluye pagos revertidos. |

Las fechas operativas se interpretan con horario de Cancún. El cobro cuenta por el día de recepción en Caja, aunque el depósito bancario tenga otra fecha. Cerrar un corte no elimina el cobro del indicador.

Caja consulta sus cobros; los perfiles con facultad de auditoría pueden consultar el conjunto permitido de cajeros. **Sin acceso** significa que tu cuenta no puede consultar ese dato. Un cero significa que la consulta sí se realizó y no encontró operaciones correspondientes.

La bandeja de pendientes ofrece accesos a las etapas que requieren atención. Usa el botón de actualización para traer los cambios recientes; no es una pantalla de actualización continua ni un reporte completo de mora o de cortes de días anteriores.

## 4. Usuarios, permisos y promotores

### Crear una cuenta — Super Admin

1. Abre **Administración → Usuarios**.
2. Crea el usuario y captura nombre, correo, sucursal, rol o roles, estado y contraseña con su confirmación.
3. La contraseña debe tener al menos 12 caracteres, mayúsculas, minúsculas y números.
4. Guarda y entrega las credenciales al titular por el procedimiento interno autorizado.

Los cambios de cuenta revocan sus sesiones existentes: el usuario deberá ingresar nuevamente. No se permite que el administrador desactive su propia cuenta o se retire su propio rol Super Admin. Debe conservarse al menos un administrador activo.

### Roles y permisos — Super Admin

Abre **Administración → Roles y permisos**, revisa las facultades y guarda los cambios necesarios. Conceder un permiso no convierte una cuenta en un perfil con acceso a todas las sucursales. Para ver el menú actualizado, recarga o vuelve a iniciar sesión.

### Registrar un promotor

Para una cuenta nueva, asigna el rol **Promotor** y su sucursal desde Usuarios: al guardar, se crea o actualiza su perfil de promotor automáticamente.

El apartado **Promotores** permite consultar los perfiles y vincular cuentas anteriores elegibles mediante **Registrar promotor**. Solo aparecen cuentas con el rol y la sucursal requeridos. Si no hay coincidencias, primero revisa la cuenta en Usuarios.

Un promotor con grupos asignados no puede desactivarse, perder ese rol o cambiar de sucursal hasta resolver la reasignación de sus grupos.

## 5. Crear el grupo solidario

1. Abre **Grupos solidarios** y la opción para crear un grupo.
2. Captura el nombre y selecciona la sucursal permitida.
3. Selecciona el promotor responsable. Si tu cuenta es de promotor, el sistema utiliza tu propio perfil; los demás capturistas deben elegirlo expresamente.
4. Guarda y abre la ficha del grupo.
5. Incorpora a las personas mediante el procedimiento de verificación de CURP y captura de expediente.
6. Asigna los cargos del comité: una **Presidenta**, una **Tesorera** y el resto como **Integrantes**.

El nombre puede repetirse. Confirma siempre la sucursal y el responsable al seleccionar un grupo. Crear un grupo no crea un crédito ni asigna al capturista como promotor.

Para solicitar crédito se requieren al menos tres participantes, exactamente una Presidenta y una Tesorera. Actualmente no se ha establecido un máximo obligatorio de veinte integrantes. El grupo puede crearse antes de estar completo.

## 6. Registrar al cliente y su expediente

### Verificar la CURP antes de capturar

1. Entra desde **Clientes** o desde la ficha del grupo.
2. Selecciona el grupo correcto mediante la búsqueda.
3. Captura la CURP y presiona **Verificar antes de continuar**.
4. Revisa la respuesta:
   - **Persona no registrada:** continúa con **Capturar nuevo expediente**.
   - **Persona existente y accesible:** revisa sucursal de alta y antecedentes disponibles; confirma si deseas abrir o modificar su expediente.
   - **Persona existente fuera de tu alcance:** consulta el resumen permitido y solicita atención a Gerencia. No crees una variante de CURP para evitar la restricción.
5. Completa y revisa los datos antes de guardar.

La validación de CURP revisa su estructura y consistencia. **No equivale a una consulta a RENAPO ni acredita por sí sola la identidad.** Los antecedentes mostrados corresponden a los datos disponibles en la base actual; no garantizan que no haya créditos en sucursales históricas todavía sin migrar.

La CURP no se modifica dentro del formulario. Una corrección excepcional debe canalizarse a administración; no se resuelve creando otra persona.

### Secciones del expediente

Usa la navegación por secciones y los botones **Anterior** y **Siguiente sección**. Los campos marcados con asterisco son obligatorios para completar el envío; un borrador permite conservar avance.

| Sección | Información que debes revisar |
| --- | --- |
| Datos Generales | Fecha, oficina, grupo y promotor. La información contextual depende del grupo seleccionado. |
| Datos Solicitante | Nombres y apellidos, género, nacimiento, país, nacionalidad, identificación y número, RFC, CURP, estado civil, régimen, escolaridad y oficio. |
| Datos de Ubicación del Domicilio | Calle, números exterior e interior, colonia, municipio, ciudad, código postal, referencia, tipo y número de teléfono, tipo de vivienda. |
| Datos de la Familia y Cónyuge | Nombre del cónyuge, trabajo y ocupación, número de hijos y dependientes económicos. |
| Situación Económica del Solicitante | Actividad económica, ocupación, ingresos del hogar, habitantes, habitaciones y material de vivienda. |
| Referencia Familiar | Nombre, parentesco, teléfono, domicilio, código postal, ocupación y lugar de trabajo. |
| Referencia Personal | Nombre, parentesco, teléfono, domicilio, código postal, ocupación y lugar de trabajo. |
| Créditos Vigentes | Antecedentes declarados de hasta dos créditos: empresa, fechas y total. No sustituye la consulta del historial interno. |
| Información Importante al Solicitante | Relaciones con directivos de la empresa o cargos públicos, nombres relacionados y observaciones. |

Respeta los mensajes de validación de la pantalla. Si un campo obligatorio no corresponde a la situación real de la persona, consulta el criterio de captura con Gestión; no inventes información para superar la validación.

### Guardar y enviar

- Usa **Guardar borrador** para continuar después.
- Revisa los datos precargados de expedientes anteriores: no asumas que el domicilio, teléfono o referencias siguen vigentes.
- Cuando esté completo, el perfil autorizado utiliza **Enviar a revisión**.
- Un expediente enviado se consulta sin sobrescribirlo. Si Gestión lo devuelve, recupera la posibilidad de corregirlo.

El número de cliente se asigna automáticamente al guardar, por ejemplo `CLI-S001-000001`. No debes capturarlo manualmente. El expediente **no pide el monto solicitado del préstamo**: ese dato se registra en la solicitud financiera del grupo.

## 7. Tasas y cálculos

Los perfiles autorizados administran **Tasas de crédito**: pueden buscar, crear, editar y desactivar tasas. Los límites de catálogo son opcionales; el gerente establece además el límite por integrante de cada solicitud.

El factor es la **cuota semanal por cada $1,000 de capital**. No es una tasa porcentual anual ni un interés que se suma nuevamente al importe calculado.

| Tasa de referencia | Factor semanal por cada $1,000 |
| --- | ---: |
| Cobre | $80 |
| Bronce | $75 |
| Plata | $70 |

Las personas pueden solicitar cantidades distintas, pero comparten la tasa del grupo. El plazo es de **16 semanas**.

### Ejemplo: $10,000 aprobados con Cobre

| Concepto | Cálculo | Resultado |
| --- | --- | ---: |
| Cuota semanal | $10,000 ÷ 1,000 × $80 | $800 |
| Total a devolver | $800 × 16 | $12,800 |
| Interés total | $12,800 − $10,000 | $2,800 |
| Garantía retenida | $10,000 × 10% | $1,000 |
| Entrega neta | $10,000 − $1,000 | $9,000 |

La garantía no reduce la deuda a $9,000 ni se registra como abono. Su devolución o aplicación posterior no está automatizada. Los adelantos y la liquidación anticipada conservan el interés pactado. Los importes se redondean a centavos; usa el resumen del sistema como referencia del cálculo registrado.

Cambiar una tasa del catálogo no modifica las condiciones de solicitudes o créditos ya registrados.

## 8. Crear la solicitud de crédito

**Responsable habitual: Gerente.** Antes de comenzar, verifica grupo activo, promotor elegible, comité y expedientes completos enviados.

1. Abre **Solicitudes de crédito → Nueva solicitud**, o **Solicitar crédito** desde el grupo.
2. Busca y selecciona el grupo.
3. Revisa los requisitos pendientes mostrados.
4. Selecciona la tasa común y establece el límite por integrante.
5. Selecciona las personas que participarán y captura el monto solicitado de cada una.
6. Revisa capital grupal, cuota semanal, interés, total a devolver, garantía estimada y entrega neta estimada.
7. Confirma el registro y conserva el folio asignado, por ejemplo `CRE-S001-000001`.

Cada importe debe ser positivo y respetar los límites aplicables. Se requieren al menos tres personas con el comité completo. El sistema verifica expedientes, mora y solicitudes o créditos abiertos incompatibles del grupo o de sus participantes.

En esta etapa no hay importe aprobado ni entrega de dinero. Los montos solicitados quedan conservados como antecedente; la aprobación se registra por separado. Tampoco se eligen todavía los medios de entrega y pago semanal.

La solicitud registrada no tiene un formulario libre para cambiar participantes, tasa o montos originales. Si estos datos fueron capturados incorrectamente, escala el caso antes de continuar; devolver expedientes desde Gestión no cambia las condiciones financieras originales.

## 9. Revisión de Gestión

1. Abre **Gestión de solicitudes** y busca el folio o grupo.
2. Abre la solicitud y expande cada integrante en **Revisión de Gestión**.
3. Revisa las seis comprobaciones: **identidad, domicilio, referencias, visita, historial interno y documentos firmados**.
4. Marca cada una como pendiente, verificada o con observaciones.
5. Para una comprobación verificada u observada, escribe la evidencia revisada o la corrección concreta requerida.
6. Guarda el avance si continuarás después.

### Devolver para corregir

Utiliza **Devolver con observaciones**, registra al menos una comprobación observada y una nota general. Solo los expedientes observados vuelven a borrador.

Gerencia abre esos expedientes desde Clientes, verifica la CURP, corrige y vuelve a enviarlos. Después usa **Reenviar a Gestión** en el crédito y explica los cambios. La revisión anterior se conserva y las comprobaciones vuelven a pendiente para revisarlas nuevamente.

### Terminar la revisión

Con todas las comprobaciones verificadas y sus evidencias escritas, añade la nota general y utiliza **Enviar a autorización**. Esto acredita el término de Gestión; todavía no aprueba ni entrega el crédito.

Actualmente las comprobaciones son declaraciones del personal basadas en documentos y verificaciones externas. No existe carga de archivos, validación automática de firmas ni generación de los formatos oficiales del expediente desde este flujo.

## 10. Propuesta y autorización comercial

### Proponer condiciones — Gerente

1. Abre **Autorización comercial** y filtra **Pendiente de condiciones del gerente**.
2. Abre el crédito con Gestión terminada.
3. Captura el importe propuesto por integrante. Debe ser positivo, no superar lo solicitado y respetar los límites originales.
4. Define por separado:
   - **Forma de entrega del crédito:** en sucursal o depósito bancario.
   - **Forma de pagos semanales:** en sucursal o depósito bancario.
5. Revisa cálculos, garantía y entrega neta; escribe las observaciones y envía la propuesta.

Las dos formas de operación pueden ser diferentes. La propuesta conserva a los participantes originales y no modifica lo que pidieron inicialmente.

### Resolver — Coordinación Comercial

1. Abre una propuesta pendiente de autorización.
2. Compara el solicitado, la propuesta y la revisión de Gestión.
3. Autoriza exactamente las condiciones presentadas o devuelve con observaciones.
4. Si se devuelve, Gerencia envía una nueva versión; la anterior permanece en el historial.

Al autorizar, el crédito queda **aprobado** y pendiente de entrega. Los importes aprobados y ambos medios quedan registrados. El rol de Coordinador de campo no sustituye al de Coordinación Comercial.

## 11. Entrega del crédito

**Responsable habitual: oficina de sucursal, con cuenta autorizada para entregas.**

1. Abre **Desembolsos** y localiza un crédito aprobado.
2. Comprueba identidad, importes autorizados, garantía, neto y medio de entrega.
3. Captura la fecha real de entrega y la fecha del primer vencimiento.
4. Si la entrega es bancaria, registra el banco y la referencia correspondiente.
5. Escribe las notas requeridas, revisa y confirma la entrega.

La fecha real no puede ser futura ni anterior a la autorización. El primer vencimiento debe ser posterior a la entrega. El calendario continúa cada siete días durante 16 semanas; no desplaza automáticamente vencimientos por festivos o fines de semana.

El registro cambia el crédito a **entregado** y genera las cuotas de cada integrante. La pantalla registra una entrega realizada: **no ejecuta una transferencia bancaria ni entrega dinero automáticamente**.

La garantía real es el 10% del capital aprobado. No vuelvas a restarla de la deuda al revisar el calendario. La entrega registrada no tiene edición o eliminación directa.

## 12. Registrar cobros

**Responsable habitual: Caja.** El promotor no registra cobranza en campo.

1. Abre **Cobros en caja** y localiza el crédito entregado.
2. Revisa saldo, cuotas exigibles, atrasos y distribución por integrante.
3. Comprueba la forma de pago semanal autorizada.
4. Captura el importe recibido y su distribución, identificando **quién aporta** y **a quién se aplica** cada aportación.
5. Para depósitos, captura banco, folio del ticket y fecha del depósito.
6. Verifica que la suma de aportaciones coincida exactamente con el importe recibido.
7. Confirma y revisa el pago en el historial. Desde allí puedes abrir su comprobante.

La fecha de recepción la asigna el sistema. La fecha del depósito se conserva aparte y no puede ser futura ni anterior a la entrega del crédito. Capturar un ticket no constituye conciliación automática con el banco.

### Cómo se aplican los importes

Se cubre primero la cuota pendiente más antigua de la persona beneficiada y después sus siguientes cuotas. No se aceptan importes superiores a su saldo pendiente.

| Caso | Registro y resultado |
| --- | --- |
| Cuota de $800; recibe $500 | Se registra $500 y quedan $300 pendientes. Si vence sin cubrirse, aparece atraso. |
| Otra integrante cubre esos $300 | Registra quién aporta y que la beneficiaria es la persona con el faltante. No se registra como pago a la deuda de quien ayudó. |
| Aporta $1,600 con cuotas de $800 y sin atrasos previos | Cubre dos cuotas. Si hay pendientes anteriores, estos tienen prioridad. |
| Liquida el saldo total | Se conserva el interés pactado. El crédito grupal se marca pagado cuando todas sus participaciones quedan saldadas. |

Una cuota es exigible en su fecha de vencimiento; el atraso comienza al día siguiente. El grupo puede estar completo en el periodo si las aportaciones solidarias cubren los faltantes, manteniendo registrado quién aportó y a quién se aplicó.

### Evitar duplicados

El sistema impide dos pagos activos con la misma combinación de sucursal, banco, fecha y folio bancario. Si aparece una coincidencia, revisa el historial antes de intentar otra captura.

Cuando la respuesta de un cobro es incierta por una falla de conexión, utiliza el reintento de la misma operación que ofrece la pantalla. Si ya recargaste o saliste, consulta primero el historial: el pago pudo haberse guardado aunque no recibieras la confirmación.

## 13. Comprobantes y verificación QR

1. Abre el historial del crédito en Cobros.
2. Selecciona **Ver comprobante QR** del pago.
3. Comprueba folio, crédito, fecha, importe, medio y distribución.
4. Usa **Imprimir comprobante**. El navegador permite imprimir o guardar como PDF según sus opciones.

Reimprimir conserva el mismo folio y no crea otro pago. El comprobante acredita un pago recibido; no es una referencia para efectuar un pago futuro.

### Validar un comprobante

Escanea el QR, comprueba que la dirección corresponda al sistema de Crece Impulso y compara folio, crédito, importe, fecha y estado con el documento presentado. La consulta pública muestra información limitada y no expone CURP ni datos personales del expediente.

La verificación consulta el registro del servidor. Un QR puede copiarse: su utilidad es contrastar el documento con el pago registrado, no impedir físicamente que se copie una impresión. Si el pago fue revertido, el mismo QR mostrará **Pago revertido** y no debe considerarse un pago vigente.

En desarrollo, un QR con dirección `localhost` no funciona desde otro teléfono. Para usarlo con clientes se necesita el servidor publicado y accesible mediante la dirección oficial.

## 14. Cortes de caja

Cada cajero cierra **sus propios cobros**, por día de recepción y sucursal, separando efectivo y depósitos.

1. Abre **Cortes de caja**.
2. Selecciona sucursal y fecha de recepción.
3. Presiona **Preparar mi corte**.
4. Revisa los totales separados y el detalle. Aunque haya varias páginas, el cierre incluye todos los cobros pendientes del ámbito elegido.
5. Captura el efectivo contado correspondiente únicamente a esos cobros.
6. Si hay diferencia, escribe la explicación obligatoria.
7. Confirma **Cerrar mi corte** y consulta el folio asignado, por ejemplo `COR-S001-000001`.

**Diferencia = efectivo contado − efectivo registrado.** Una cantidad positiva es sobrante; una negativa es faltante. En un corte únicamente de depósitos, el efectivo contado será cero cuando no exista efectivo correspondiente.

No incluyas fondo inicial, garantías, desembolsos, retiros ni otros movimientos: este módulo cierra ingresos de cobranza y no realiza el arqueo completo de la sucursal.

### Después del cierre

- El corte no cambia los pagos ni sus saldos.
- Los cobros no pueden incluirse en dos cortes.
- Si recibes nuevos pagos del mismo día, puedes realizar otro corte con los pendientes; el anterior se conserva.
- Gerencia puede consultar los cortes dentro de su alcance, pero no cerrarlos en nombre del cajero ni modificar su conteo.
- Las diferencias quedan registradas. No generan cargos automáticos ni tienen todavía un flujo de aprobación gerencial.
- Si un pago del corte se revierte después, aparece en **Reversos posteriores · ajustes separados**. Los totales originales permanecen intactos.

Un depósito de una fecha anterior entra al corte del día en que Caja registró su recepción. Un corte no certifica conciliación bancaria ni entrega física de efectivo a Tesorería.

## 15. Reversar un pago incorrecto

**Autorizados inicialmente:** Gerente, Gerente General y Super Admin, dentro de su alcance. Caja consulta el resultado.

Utiliza esta función para corregir un pago capturado por error. El reverso es completo: no permite modificar una parte del pago ni borrar su comprobante.

1. Abre **Cobros en caja**, selecciona el crédito y localiza el pago incorrecto en el historial.
2. Abre **Ver comprobante QR**.
3. Comprueba folio, importe, distribución y si pertenece a un corte.
4. Escribe un motivo específico de al menos diez caracteres. Ejemplo: «Se aplicó el depósito a la integrante equivocada; se registrará nuevamente con la beneficiaria correcta».
5. Selecciona **Revertir pago completo**, revisa la confirmación y acepta cuando los datos sean correctos.
6. Verifica la marca **PAGO REVERTIDO**, fecha, responsable y motivo.
7. Si corresponde, Caja registra el pago correcto como una operación nueva.

### Qué cambia

Se restituyen los saldos de las cuotas a las que se aplicó ese pago. Si el crédito estaba pagado, vuelve a entregado con saldo pendiente; pueden reaparecer atrasos desde los vencimientos originales. Los pagos posteriores conservan su aplicación y no se redistribuyen automáticamente.

El pago y el comprobante originales permanecen en el historial. El QR público comunica el reverso, sin mostrar el motivo privado ni el nombre del responsable. Un pago revertido antes del corte ya no entra a un nuevo corte; si ya estaba cerrado, su ajuste se muestra por separado.

El ticket bancario se libera para una captura corregida, manteniendo el registro histórico y la protección contra dos pagos activos iguales.

### Qué no cambia

El reverso no altera las condiciones aprobadas, intereses o garantía, y **no devuelve dinero automáticamente**. Tampoco cancela créditos posteriores de la persona: Gerencia debe revisar esa situación si se reabre una deuda anterior.

No hay reverso parcial ni función para revertir un reverso. Si se revierte por equivocación, la corrección requiere un nuevo pago debidamente documentado. Ante respuesta incierta, reintenta la misma operación desde la pantalla; si ya saliste, verifica primero el estado del comprobante.

## 16. Rutina de trabajo por perfil

| Perfil | Revisión recomendada |
| --- | --- |
| Promotor | Confirmar grupo asignado; verificar CURP antes de capturar; revisar referencias y domicilio; guardar cada avance. |
| Gerente | Completar comité y expedientes; enviar a revisión; atender devoluciones; proponer condiciones; revisar entregas, cortes y errores reportados. |
| Gestión | Revisar las seis comprobaciones de cada persona; registrar evidencia concreta; guardar avances; devolver observaciones claras o enviar a autorización. |
| Coordinación Comercial | Comparar solicitud, revisión y propuesta; verificar los dos medios de operación; autorizar o devolver con motivo. |
| Caja | Verificar crédito y beneficiarias antes de cobrar; capturar folios bancarios fielmente; entregar comprobante; revisar pagos del día y cerrar sus propios cobros. |
| Super Admin | Mantener cuentas y sucursales asignadas; revisar permisos; atender accesos y perfiles de promotor sin compartir cuentas. |

Antes de cerrar sesión, confirma que tus operaciones aparecen guardadas. Los movimientos financieros deben identificarse por su folio, no solo por el nombre del grupo.

## 17. Problemas frecuentes

| Situación | Qué hacer |
| --- | --- |
| No veo una opción del menú | Revisa tu rol y sucursal con administración. Si hubo un cambio de permisos, vuelve a iniciar sesión. |
| No encuentro un grupo | Escribe dos o más caracteres, afina la búsqueda y verifica sucursal, promotor y alcance de tu cuenta. |
| No aparece un promotor para registrar | Revisa que la cuenta tenga el rol Promotor, sucursal y estado elegibles; puede tener ya su perfil creado. |
| La CURP ya existe | Consulta el resultado y reutiliza el registro permitido. No cambies letras o números para duplicarlo. |
| La persona está en otra sucursal | Solicita revisión a Gerencia; la coincidencia no concede permiso para editar su expediente. |
| No puedo enviar el expediente | Atiende los campos obligatorios y comprueba que tu perfil tenga facultad de envío. |
| El grupo no puede solicitar crédito | Revisa comité, mínimo de integrantes, expedientes enviados, promotor, estados activos y créditos o mora incompatibles. |
| No puedo editar el monto solicitado | Es el antecedente original. La autorización registra importes propuestos y aprobados por separado; consulta el caso con Gerencia. |
| La pantalla dice que los datos cambiaron | Actualiza, revisa las nuevas condiciones o saldos y confirma de nuevo. No intentes forzar la versión anterior. |
| El depósito se indica como duplicado | Busca banco, fecha y folio en el historial; si el registro es erróneo, solicita un reverso autorizado. |
| Se perdió la conexión al cobrar, cerrar o revertir | Usa el reintento conservado por la pantalla. Si la abandonaste, consulta el historial antes de registrar otra operación. |
| El QR no abre en el teléfono | Comprueba conexión y dirección. Una dirección local de desarrollo no es accesible desde el dispositivo del cliente. |
| El comprobante dice Pago revertido | No lo tomes como pago vigente; consulta el historial y, si existe, el comprobante de la nueva captura correcta. |
| El dashboard muestra un error o guiones | Reintenta la consulta. No interpretes la falta de conexión como saldo o actividad en cero. |
| El corte no coincide con todo el efectivo del cajón | Compara solo los ingresos de cobranza incluidos; separa fondos, entregas, garantías y otros movimientos. |

Para reportar una incidencia, proporciona módulo, folio, sucursal, fecha y mensaje mostrado. Evita compartir contraseñas o expedientes completos en canales no autorizados.

## 18. Estados y términos

| Término o estado | Significado operativo |
| --- | --- |
| Cliente | Persona identificada por CURP y folio de cliente. Puede tener historia en distintos ciclos. |
| Expediente en borrador | Información personal que todavía puede corregirse dentro de los permisos. |
| Expediente en revisión | Fue enviado completo; no significa que Gestión ya lo haya validado. |
| Solicitud pendiente | Registro financiero que todavía no está aprobado. |
| Revisión devuelta | Hay observaciones de Gestión que deben corregirse en los expedientes correspondientes. |
| Lista para autorización | Gestión concluyó; faltan las condiciones y la decisión comercial según su etapa. |
| Propuesta devuelta | Coordinación Comercial pide corregir las condiciones propuestas; no cambia por sí sola los expedientes personales. |
| Crédito aprobado | Condiciones autorizadas, todavía sin entrega registrada. |
| Crédito entregado | Entrega registrada y calendario vigente; se considera activo mientras exista saldo. |
| Crédito pagado | Todas las participaciones están saldadas. Un reverso puede reabrirlo. |
| Exigible | Importe que corresponde pagar hasta la fecha de consulta, incluido el vencimiento de hoy. |
| Atraso | Importe vencido pendiente; los días se calculan conforme a sus vencimientos. |
| Aportante / beneficiaria | Quien aporta el dinero / persona cuya deuda recibe la aplicación. |
| Folio | Número legible para localizar cliente, crédito, comprobante o corte. Lo asigna el sistema. |
| Reverso | Corrección auditada de un pago completo, conservando el original y recuperando el saldo aplicado. |

Los estados del expediente, de Gestión, de autorización y del crédito describen etapas diferentes. No deben interpretarse como una sola aprobación.

## 19. Ejercicio de capacitación

Realiza esta práctica únicamente con datos de demostración. La guía [Prueba manual de un crédito](../features/prueba-manual-credito.md) identifica las cuentas de capacitación existentes; las credenciales se entregan por separado. Los registros pueden haber avanzado desde su creación: revisa su estado antes de comenzar y no los reinicies para repetir el ejercicio.

Para un grupo de tres integrantes con Cobre, importes de $10,000, $8,000 y $5,000 y aprobación por esos mismos valores:

| Resultado grupal | Importe |
| --- | ---: |
| Capital | $23,000 |
| Cuota semanal | $1,840 |
| Total de las 16 semanas | $29,440 |
| Interés | $6,440 |
| Garantía retenida | $2,300 |
| Entrega neta | $20,700 |

Recorre con cada perfil: solicitud → Gestión → propuesta → autorización → entrega → un cobro → comprobante → corte. Después, en el entorno de prueba, revierte ese cobro con Gerencia y verifica que el comprobante cambie de estado, el saldo se restituya y el corte conserve su total original con un ajuste separado. Si se necesita corregirlo, registra un nuevo pago con Caja.

Al cambiar de perfil, cierra la sesión anterior. Las pestañas del mismo navegador pueden compartir la sesión.

## 20. Alcance actual

Este manual cubre el portal administrativo implementado. Las siguientes funciones requieren desarrollo o puesta en marcha adicional y no deben darse por disponibles:

- Operación offline y sincronización de aplicaciones móviles.
- App de clientes para consultar créditos y tickets.
- Consolidación completa de las siete bases históricas: las consultas solo abarcan información realmente incorporada.
- Consulta oficial de identidad, carga documental y generación de los PDF originales del expediente para firma. La impresión del comprobante de pago sí está disponible.
- Conciliación automática con bancos, transferencias automáticas y devolución de dinero por reverso.
- Devolución o aplicación de garantías, tesorería integral y aprobación de diferencias de caja.
- Corrección libre de solicitudes registradas, modificación de entregas y ajustes automáticos de fechas por días inhábiles.

Las opciones marcadas como **Próximamente** son informativas. Este documento debe actualizarse cuando cambien pantallas, permisos o reglas operativas.
