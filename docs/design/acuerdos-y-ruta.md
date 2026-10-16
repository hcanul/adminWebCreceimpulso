# Crece Impulso — acuerdos y primera propuesta visual

Actualizado: 8 de octubre de 2026. Fuente: decisiones explícitas del usuario. Este documento actualiza las definiciones anteriores cuando exista contradicción.

## Reglas confirmadas

- Prioridad: solicitudes y comprobaciones; seguimiento (departamento de cobranza posterior); pagos parciales y fichas bancarias; cortes separados por efectivo y depósitos; trabajo offline.
- Gerente captura, Gestión revisa, Coordinación Comercial autoriza, sucursal desembolsa. El asesor captura información y documentos para impresión. El alcance exacto de su permiso para iniciar/enviar solicitudes queda pendiente; no se le concede autorización financiera.
- Promotor no cobra en campo; offline solamente actualiza clientes y grupos. No implementar cola offline de pagos.
- CURP es la identidad principal del cliente, independiente de sus solicitudes históricas. Mantener los 72 campos del inventario y todos los documentos impresos necesarios.
- Información editable con permisos, historial de cambios y revisión de cambios posteriores a autorización; monto solicitado original y condiciones autorizadas conservan su historia.
- Plazo estricto de 16 semanas. Cuota semanal = capital / 1000 × factor. Total = cuota × 16. Interés = total menos capital. Liquidación anticipada conserva el interés pactado.
- Catálogo configurable; referencias proporcionadas: Cobre 80, Bronce 75, Plata 70. Confirmar catálogo completo, rangos y elegibilidad antes de cargar producción. Guardar una copia de condiciones por crédito para que editar el catálogo no cambie créditos vigentes.
- Caso confirmado: capital 10,000; semanal 800; total 12,800; interés 2,800; garantía retenida 1,000; entrega neta 9,000. Garantía separada; devolución/aplicación y permisos aún pendientes.
- Pagos parciales dejan saldo; aportaciones solidarias deben distinguir aportante y obligación cubierta. Adelantos cubren cuotas futuras. Mora pendiente bloquea renovación; definir calendario de primera cuota, hora de corte y redondeo antes de implementar cálculo productivo.
- Cajero recibe ficha, captura folio y emite comprobante empresarial. No se ha confirmado conciliación bancaria real: distinguir recepción de ficha, confirmación del cajero y conciliación con banco. El ticket debe reflejar el estado real. Definir qué evento aplica el abono y si existe verificación adicional, sin atribuirla automáticamente al cajero.
- Folio bancario no es universalmente único: diseñar detección de duplicados por banco/cuenta/referencia/fecha/importe y revisión de coincidencias; no bloquear con folio solo sin conocer su alcance.
- Cliente consulta únicamente crédito activo propio, vencimientos, atrasos y comprobantes de pago recibido. Capturar datos del crédito actual es comprobación adicional de identidad, no solicitud nueva. CURP y monto conocido no bastan como autenticación: completar con activación en sucursal o canal previamente verificado. Evitar revelar existencia de clientes en respuestas públicas.
- Plataformas: Android, iPhone, iPad, Windows y navegador; definir empaquetado después de validar UX.

## CURP y duplicados: diseño propuesto

Normalizar espacios y mayúsculas; validar estructura y consistencia con información personal. Diferenciar formato correcto, coincidencia interna y validación documental. No afirmar consulta a RENAPO sin integración real.

La identidad de persona debe poder detectarse entre las siete sucursales sin revelar expedientes fuera del permiso del operador. Resolver duplicados históricos antes de imponer unicidad. La CURP puede corregirse con justificación e historial; el UUID nunca cambia. Una persona puede tener múltiples solicitudes históricas sin duplicar su identidad. Compartir domicilio, teléfono o apellidos genera señales para revisión, no unicidad obligatoria.

En offline la unicidad global no puede garantizarse: conservar el registro pendiente y resolver coincidencias al sincronizar, sin fusión silenciosa ni pérdida de datos.

## Documentos y seguridad

Gestión revisa identidad, domicilio, referencias, visita, historial y documentos firmados. Inventariar plantillas PDF existentes y vincular cada dato antes de rehacerlas. Capturar con la app del asesor, generar documentos poblados e imprimir para firma manuscrita; no se ha solicitado firma electrónica. Versionar documentos para conservar lo presentado y firmado.

## Primera propuesta visual

Abrir `solicitudes-prototipo.html` directamente en un navegador. Es un prototipo local de diseño, no la aplicación Vue definitiva. Incluye el logotipo proporcionado, paleta guinda/gris, navegación de 9 secciones con los 72 campos originales, revisión de Gestión y simulador de tres tasas. No requiere servidores ni dependencias externas. No guarda datos, consulta CURP, adjunta archivos, genera PDF oficial ni efectúa autorizaciones.

La identidad visual usa superficies claras, navegación lateral, guinda para acciones principales, tipografía jerarquizada y panel de condiciones. La interfaz se adapta por CSS a teléfono, tablet y escritorio. El simulador usa números de JavaScript solo como demostración; producción requiere precisión decimal/centavos y reglas explícitas de redondeo. Asteriscos reflejan reglas heredadas, todavía sujetas a revisión.

## Ruta y criterios de salida

1. Validar dirección visual y orden de captura con este prototipo.
2. Completar matriz de permisos, calendario, redondeo, depósitos, garantía y catálogo de documentos. Mapear los 72 campos con sus validaciones y dependencias.
3. Corregir núcleo existente: autenticación, rutas, modelos, autorización por sucursal e identidad global; no dar por cerrados los problemas del análisis previo.
4. Construir solicitud completa: guardar borrador, editar con historial, detectar duplicados, revisar, devolver observaciones, autorizar y generar documentos para impresión.
5. Implementar grupos, condiciones de crédito, desembolso y calendario de 16 cuotas con escenarios financieros verificados.
6. Implementar abonos, depósitos, recibos QR y cortes separados. Introducir departamento de cobranza cuando se definan sus reglas.
7. App del cliente y actualización offline de clientes/grupos; arquitectura preparada desde el núcleo.
8. Migración ensayada y conciliada por sucursal, piloto y despliegue progresivo. Preparación de fuentes e identidades desde el inicio.

No se iniciaron contenedores, servidores ni procesos de prueba continuos. No se modificó código operativo en esta iteración.

## Ajuste: grupo primero y menú lateral

El usuario confirma el 8 de octubre que primero se crea el grupo y después se incorporan sus integrantes para evaluar elegibilidad. La entrada del prototipo ahora es `index.html`; el formulario completo se abre desde cada integrante. La navegación global es un panel lateral deslizable/plegable con módulos previstos; las secciones de captura son navegación interna, no sustituyen el menú global.

Contraste con legacy: `TeamController::create/store` y `resources/views/team/form.blade.php` registran nombre, sucursal y promotor. `CreditController::buscar` consulta solicitantes activos (`Demand::whereTeamId(...)->whereActivo('1')`) y rechaza grupos sin integrantes. `CreditController::store` asigna Presidenta/Tesorera a las participaciones del crédito. La comprobación encontrada de integrantes mayores que cero no establece un mínimo comercial definitivo. No inventar mínimo/máximo ni considerar ausencia de validaciones antiguas como permiso de negocio.

Separar pertenencia al grupo de participación y cargo en cada crédito. Reutilizar clientes existentes por CURP sin duplicar persona; conservar composición y comité históricos por ciclo. La revisión de elegibilidad deberá ejecutarse en servidor antes de continuar, con integrantes activos, comité válido, expedientes, mora y políticas comerciales confirmadas. Elegibilidad no equivale a autorización ni a desembolso.

Prototipo: crear grupo de prueba, incorporar integrantes ficticias, asignar cargos distintos y abrir los 72 campos. La solicitud de crédito permanece bloqueada porque no hay verificación real. Los demás módulos se identifican como pantallas pendientes. Los datos viven solo en memoria; el formulario no se guarda y se reinicia al abrir otra integrante. No capturar datos reales en esta demostración.
