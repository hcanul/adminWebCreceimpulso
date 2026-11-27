export interface ModuleHelp {
  id: string;
  title: string;
  purpose: string;
  steps: string[];
  important: string;
  primaryLabel: string;
}

const entries: Array<{ pattern: RegExp; help: ModuleHelp }> = [
  {
    pattern: new RegExp("^/login$"),
    help: {
      id: "login",
      title: "Inicio de sesión",
      purpose: "Accede a Oficina Digital con tu cuenta de trabajo.",
      steps: [
        "Escribe el correo y la contraseña de tu cuenta.",
        "Pulsa Ingresar al sistema. Si la contraseña venció, completa su renovación.",
        "Si no puedes entrar, solicita apoyo a Administración; no uses una cuenta ajena.",
      ],
      important:
        "La guía automática se reserva para los dos primeros accesos de cuentas nuevas. Las cuentas existentes pueden consultar Ayuda en cualquier momento.",
      primaryLabel: "Ingresar al sistema",
    },
  },
  {
    pattern: new RegExp("^/cambiar-contrasena$"),
    help: {
      id: "password",
      title: "Renovar contraseña",
      purpose:
        "Actualiza la contraseña de tu cuenta para continuar trabajando.",
      steps: [
        "Comprueba el correo de la cuenta que aparece en pantalla.",
        "Captura tu contraseña actual y una nueva de al menos 12 caracteres, con letras y números.",
        "Confirma la nueva contraseña y guarda. Puedes usar Mostrar contraseñas para revisar lo escrito.",
      ],
      important:
        "La contraseña nueva debe ser diferente de la actual y de la anterior. La renovación no cuenta como un nuevo inicio de sesión para la guía.",
      primaryLabel: "Guardar y continuar",
    },
  },
  {
    pattern: new RegExp("^/$"),
    help: {
      id: "home",
      title: "Resumen operativo",
      purpose:
        "Consulta el estado de la operación y accede a los módulos de tu perfil.",
      steps: [
        "Revisa la sucursal y el periodo seleccionados, si están disponibles.",
        "Consulta los indicadores y sus estados antes de interpretar los totales.",
        "Abre desde el menú el proceso que necesitas atender.",
      ],
      important:
        "Los datos y las opciones dependen de tus permisos y alcance de sucursal.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/grupos$"),
    help: {
      id: "groups",
      title: "Grupos solidarios",
      purpose: "Crea primero el grupo y asigna a su promotor responsable.",
      steps: [
        "Busca el grupo antes de crear otro. Los nombres pueden repetirse; verifica su sucursal e identificador.",
        "En Crear grupo, selecciona la sucursal y el promotor correspondiente.",
        "Abre el grupo para incorporar integrantes y definir los cargos.",
      ],
      important:
        "Quien captura no se convierte automáticamente en promotor. El promotor debe ser una cuenta habilitada con ese rol.",
      primaryLabel: "Crear grupo",
    },
  },
  {
    pattern: new RegExp("^/grupos/[^/]+$"),
    help: {
      id: "group-detail",
      title: "Integrantes y comité",
      purpose:
        "Administra los integrantes y cargos del grupo antes de preparar una solicitud.",
      steps: [
        "Confirma el grupo y la sucursal que estás consultando.",
        "Incorpora a cada integrante después de verificar su CURP y expediente.",
        "Define Presidenta, Tesorera e integrantes y revisa la elegibilidad antes de solicitar crédito.",
      ],
      important:
        "Modificar integrantes o cargos no equivale a autorizar un crédito; respeta las restricciones de créditos activos.",
      primaryLabel: "Incorporar integrante",
    },
  },
  {
    pattern: new RegExp("^/clientes/captura$"),
    help: {
      id: "client-capture",
      title: "Expediente del cliente",
      purpose:
        "Verifica la identidad antes de capturar o corregir un expediente.",
      steps: [
        "Captura la CURP completa y pulsa Verificar antes de continuar.",
        "Si ya existe, revisa la sucursal y los créditos mostrados antes de elegir modificarlo.",
        "Completa las secciones, selecciona el grupo de la sucursal y guarda el expediente o borrador.",
      ],
      important:
        "El folio se asigna en el backend al guardar. El monto solicitado pertenece al paso de solicitud de crédito, no al alta del cliente.",
      primaryLabel: "Verificar antes de continuar",
    },
  },
  {
    pattern: new RegExp("^/clientes$"),
    help: {
      id: "clients",
      title: "Clientes y expedientes",
      purpose:
        "Consulta expedientes y accede a la verificación previa de CURP.",
      steps: [
        "Busca y revisa los expedientes existentes del alcance de tu cuenta.",
        "Usa Verificar CURP para iniciar un alta o localizar a una persona ya registrada.",
        "Abre el expediente o sus documentos para revisar y completar la información.",
      ],
      important:
        "Evita duplicados: la CURP es el identificador principal de la persona, incluso entre sucursales.",
      primaryLabel: "Verificar CURP",
    },
  },
  {
    pattern: new RegExp("^/solicitudes-credito/nueva$"),
    help: {
      id: "credit-create",
      title: "Preparar solicitud de crédito",
      purpose:
        "Registra lo que pide cada integrante conservando las condiciones del grupo.",
      steps: [
        "Selecciona un grupo elegible y revisa sus integrantes.",
        "Captura únicamente el monto solicitado por cada integrante; la revisión del expediente se realiza previamente de forma manual.",
        "Pulsa Enviar a autorización. Coordinación fijará las condiciones definitivas.",
      ],
      important:
        "Los montos solicitados se conservan separados de los aprobados. Guardar una solicitud no entrega dinero.",
      primaryLabel: "Enviar a autorización",
    },
  },
  {
    pattern: new RegExp("^/solicitudes-credito/[^/]+$"),
    help: {
      id: "credit-detail",
      title: "Detalle del crédito",
      purpose:
        "Consulta el avance, las condiciones y los vínculos del crédito.",
      steps: [
        "Revisa el folio, grupo, sucursal y estado del proceso.",
        "Compara los montos solicitados con los aprobados y verifica las condiciones conservadas.",
        "Abre Documentos, Estado de cuenta, Cobros o Garantía según el estado y tus permisos.",
      ],
      important:
        "Cada etapa conserva su historial. No confundas autorización con desembolso ni una corrección con un nuevo pago.",
      primaryLabel: "Documentos",
    },
  },
  {
    pattern: new RegExp("^/solicitudes-credito$"),
    help: {
      id: "credit-list",
      title: "Solicitudes de crédito",
      purpose: "Localiza y consulta solicitudes grupales.",
      steps: [
        "Filtra por estado o busca por folio y nombre del grupo.",
        "Revisa el estado y abre el detalle del crédito que corresponda.",
        "Usa Nueva solicitud cuando el grupo ya tenga integrantes y sea elegible.",
      ],
      important:
        "La captura de clientes y la solicitud de crédito son procesos distintos.",
      primaryLabel: "Nueva solicitud",
    },
  },
  {
    pattern: new RegExp("^/gestion$"),
    help: {
      id: "review",
      title: "Gestión de solicitudes",
      purpose:
        "Comprueba el expediente antes de enviarlo a autorización comercial.",
      steps: [
        "Localiza la solicitud y abre su detalle de revisión.",
        "Valida identidad, domicilio, referencias, visita, historial y documentos requeridos.",
        "Registra observaciones o el resultado de revisión conforme a tus permisos.",
      ],
      important:
        "Gestión revisa; la autorización comercial y la entrega se registran en etapas separadas.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/autorizaciones$"),
    help: {
      id: "authorization",
      title: "Autorización comercial",
      purpose: "Revisa y registra las condiciones aprobadas para el grupo.",
      steps: [
        "Busca una solicitud pendiente y abre su autorización; no requiere una segunda revisión de Gestión.",
        "Captura montos otorgados, tasa común, primer vencimiento y formas de pago y desembolso.",
        "Autoriza el crédito o solicita correcciones explicando el motivo.",
      ],
      important:
        "El monto solicitado es inmutable. Los siguientes vencimientos se calculan semanalmente a partir del primer pago pactado.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/desembolsos$"),
    help: {
      id: "disbursement",
      title: "Entrega del crédito",
      purpose: "Confirma firmas y registra la entrega real del dinero de un crédito autorizado.",
      steps: [
        "Selecciona el crédito autorizado y revisa sus documentos y condiciones.",
        "Comprueba la modalidad de entrega y la garantía retenida antes de confirmar.",
        "Registra el desembolso y revisa el calendario y estado de cuenta.",
      ],
      important:
        "Solo se retiene la garantía acordada del 10%; no registres otra entrega al reintentar una operación incierta.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/cobros/[^/]+$"),
    help: {
      id: "payment",
      title: "Capturar un pago",
      purpose:
        "Registra el importe recibido y cómo se aplica a las integrantes.",
      steps: [
        "Confirma el crédito y revisa cuotas y saldos pendientes.",
        "Captura el importe y, si es depósito, banco, folio y fecha del ticket.",
        "Registra quién aporta, a quién se aplica, revisa el desglose y confirma el cobro; consulta su comprobante.",
      ],
      important:
        "Se cubren cuotas pendientes, después mora y finalmente adelantos. Si otra integrante aporta, conserva por separado aportante y beneficiaria.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/cobros$"),
    help: {
      id: "collections",
      title: "Cobros en caja",
      purpose:
        "Localiza el crédito para registrar pagos o consultar comprobantes.",
      steps: [
        "Busca el crédito por folio o grupo.",
        "Abre Cobros y saldos del crédito correcto.",
        "Verifica el importe recibido y registra el efectivo o los datos del depósito antes de confirmar.",
      ],
      important:
        "Un pago parcial es válido y deja su pendiente. Los errores se corrigen mediante reversos autorizados, sin borrar el comprobante original.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/cartera$"),
    help: {
      id: "portfolio",
      title: "Cartera y vencimientos",
      purpose: "Consulta vencimientos, saldos pendientes y recuperación.",
      steps: [
        "Selecciona el periodo, sucursal y clasificación de cartera.",
        "Revisa las cuotas vencidas, abonos y días de atraso del grupo e integrantes.",
        "Abre el estado de cuenta o seguimiento y confirma los filtros antes de exportar.",
      ],
      important:
        "El traslado a cartera vencida corresponde a Gerente General o Super Admin, con motivo. Un abono no reincorpora automáticamente el crédito.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/creditos/[^/]+/seguimiento$"),
    help: {
      id: "followup",
      title: "Seguimiento de cobranza",
      purpose:
        "Conserva observaciones y compromisos del crédito y sus integrantes.",
      steps: [
        "Revisa el crédito, grupo y situación de atraso.",
        "Selecciona la integrante o el grupo y captura la observación o compromiso correspondiente.",
        "Guarda y comprueba que la entrada aparezca en el historial; utiliza los filtros para consultar.",
      ],
      important:
        "Registrar una observación o compromiso no registra un cobro ni modifica el saldo.",
      primaryLabel: "Guardar observación",
    },
  },
  {
    pattern: new RegExp("^/observaciones-cobranza$"),
    help: {
      id: "followup-history",
      title: "Observaciones de cobranza",
      purpose: "Consulta las actuaciones de cobranza dentro de tu alcance.",
      steps: [
        "Aplica filtros de sucursal, periodo o crédito disponibles.",
        "Lee el historial con su responsable y fecha.",
        "Abre el seguimiento del crédito para añadir una observación o revisar compromisos.",
      ],
      important:
        "El historial de seguimiento es independiente de los pagos y conserva su trazabilidad.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/creditos/[^/]+/garantia$"),
    help: {
      id: "guarantee",
      title: "Garantía retenida",
      purpose: "Consulta la garantía del grupo y su aplicación al adeudo.",
      steps: [
        "Revisa el crédito, el saldo retenido y el desglose de cada integrante.",
        "Al llegar a la semana 16, el encargado comercial revisa y autoriza la aplicación disponible.",
        "Captura el motivo y comprueba el efecto en el estado de cuenta; cualquier reverso requiere su motivo.",
      ],
      important:
        "La garantía se aplica al final, hasta la semana 16. Una consulta no aplica ni devuelve dinero.",
      primaryLabel: "Aplicar garantía al adeudo",
    },
  },
  {
    pattern: new RegExp("^/creditos/[^/]+/estado-de-cuenta$"),
    help: {
      id: "statement",
      title: "Estado de cuenta",
      purpose: "Consulta el calendario y la aplicación real de los pagos.",
      steps: [
        "Confirma el folio, grupo e integrante que estás revisando.",
        "Compara cuotas, pagos aplicados, pendientes y mora en las fechas indicadas.",
        "Revisa movimientos y totales antes de imprimir o compartir el documento.",
      ],
      important:
        "Los adelantos no reducen el interés pactado. Revisa los reversos y aplicaciones de garantía al interpretar el saldo.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/cortes/[^/]+/imprimir$"),
    help: {
      id: "closing-print",
      title: "Reporte de corte",
      purpose: "Imprime el corte conservando sus cobros y totales originales.",
      steps: [
        "Comprueba folio, cajero, sucursal y fecha del corte.",
        "Revisa efectivo, depósitos, diferencias y ajustes mostrados.",
        "Selecciona impresora y formato en el diálogo de impresión del navegador.",
      ],
      important:
        "El corte cerrado no se edita. Los reversos posteriores se conservan como ajustes separados.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/cortes(?:/[^/]+)?$"),
    help: {
      id: "closings",
      title: "Cortes de caja",
      purpose: "Revisa y cierra los cobros de un cajero por día y sucursal.",
      steps: [
        "Selecciona la sucursal y el día de recepción; consulta o prepara tu corte.",
        "Compara efectivo registrado con efectivo contado y revisa los depósitos por separado.",
        "Explica cualquier diferencia, confirma el cierre y consulta el reporte para imprimir.",
      ],
      important:
        "El cierre es definitivo: no modifica pagos ni convierte depósitos en efectivo. Gerencia puede consultarlo según sus permisos.",
      primaryLabel: "Preparar mi corte",
    },
  },
  {
    pattern: new RegExp("^/comprobantes/[^/]+$"),
    help: {
      id: "receipt",
      title: "Comprobante de pago",
      purpose: "Consulta e imprime la constancia de un pago recibido.",
      steps: [
        "Comprueba el folio, importe, fecha, crédito y estado del comprobante.",
        "Usa el QR para consultar su validación y detectar un reverso si existe.",
        "Elige impresión en ticket o carta cuando esté disponible y selecciona la impresora en el navegador.",
      ],
      important:
        "El comprobante refleja un pago registrado; el QR debe validarse contra el servidor, no solo por su apariencia.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/reportes$"),
    help: {
      id: "reports",
      title: "Reportes para impresión",
      purpose: "Genera reportes del alcance y periodo seleccionados.",
      steps: [
        "Selecciona el reporte, sucursal, periodo o crédito que necesites.",
        "Revisa la clasificación de cartera y los totales antes de generar el documento.",
        "Abre la vista de impresión y selecciona la impresora y tamaño de papel.",
      ],
      important:
        "La cartera ordinaria excluye los créditos trasladados a recuperación; utiliza la vista de recuperación o consolidada cuando corresponda.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/cobros-depositos$"),
    help: {
      id: "cash-movements",
      title: "Cobros y depósitos",
      purpose:
        "Consulta efectivo, depósitos y ajustes sin duplicar movimientos.",
      steps: [
        "Filtra fechas, sucursal, medio de cobro o referencia bancaria.",
        "Revisa las partidas y separa los depósitos del efectivo recibido en caja.",
        "Exporta el CSV o imprime el resumen después de comprobar los filtros.",
      ],
      important:
        "Los totales de registros no certifican efectivo físico ni conciliación bancaria.",
      primaryLabel: "Consultar",
    },
  },
  {
    pattern: new RegExp("^/caja-central$"),
    help: {
      id: "central-cash",
      title: "Caja central",
      purpose: "Revisa el consolidado de caja de las sucursales autorizadas.",
      steps: [
        "Define el periodo y las sucursales que vas a comparar.",
        "Revisa ingresos, depósitos y ajustes y consulta su detalle.",
        "Exporta o imprime el resumen con el periodo y fecha de generación.",
      ],
      important:
        "El neto de movimientos es distinto del dinero físico disponible. Los reversos no borran los cobros originales.",
      primaryLabel: "Consultar",
    },
  },
  {
    pattern: new RegExp("^/promotores$"),
    help: {
      id: "promoters",
      title: "Promotores",
      purpose: "Habilita promotores y consulta su asignación a grupos.",
      steps: [
        "Revisa si la persona ya figura en el catálogo.",
        "Para registrarla, selecciona una cuenta activa con rol Promotor y sucursal asignada.",
        "Después asigna el promotor al crear o editar un grupo.",
      ],
      important:
        "Este registro vincula una cuenta existente. Las cuentas y sus roles se administran en Usuarios.",
      primaryLabel: "Registrar promotor",
    },
  },
  {
    pattern: new RegExp("^/tasas$"),
    help: {
      id: "rates",
      title: "Tasas de crédito",
      purpose: "Administra las tasas disponibles para nuevas solicitudes.",
      steps: [
        "Revisa las tasas existentes, su factor y sus condiciones.",
        "Captura o modifica la tasa conforme a tu permiso y revisa los importes.",
        "Guarda y utiliza la tasa adecuada al preparar una nueva solicitud.",
      ],
      important:
        "Cada grupo comparte una tasa; cada integrante puede solicitar un importe distinto. Los créditos conservan sus condiciones históricas.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/accesos-clientes$"),
    help: {
      id: "access",
      title: "Acceso de clientes",
      purpose:
        "Entrega códigos de activación o recuperación después de verificar identidad.",
      steps: [
        "Busca la CURP o número de cliente completo.",
        "Verifica presencialmente la identidad y marca la comprobación requerida.",
        "Selecciona activación o recuperación y entrega el código al titular por el procedimiento autorizado.",
      ],
      important:
        "No compartas códigos ni contraseñas por canales públicos. Un código de activación no crea otra solicitud de crédito.",
      primaryLabel: "Buscar cliente",
    },
  },
  {
    pattern: new RegExp("^/documentos(?:/[^/]+/[^/]+)?$"),
    help: {
      id: "documents",
      title: "Documentos y expediente",
      purpose: "Consulta y genera documentación desde Admin Web.",
      steps: [
        "Selecciona el expediente o crédito correspondiente.",
        "Comprueba la información y los datos documentales de la sucursal.",
        "Genera o imprime el documento y sigue el procedimiento de firmas y resguardo.",
      ],
      important:
        "Los contratos conservan el texto aprobado. Revisa nombres, importes y fechas antes de imprimir y protege la hoja de accesos temporales.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/administracion/sucursales$"),
    help: {
      id: "branches",
      title: "Sucursales",
      purpose: "Administra las sucursales y su información operativa.",
      steps: [
        "Busca la sucursal antes de dar de alta otra.",
        "Revisa nombre, estado y datos disponibles en su formulario.",
        "Guarda y comprueba sus asignaciones; revisa datos legales en Datos documentales.",
      ],
      important:
        "Una sucursal delimita el acceso operativo. No reasignes información histórica sin revisar sus relaciones.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/administracion/datos-documentales$"),
    help: {
      id: "document-profiles",
      title: "Datos documentales",
      purpose: "Configura los datos de empresa que usan los documentos.",
      steps: [
        "Elige la configuración general o la sucursal que deseas configurar.",
        "Captura y verifica domicilio legal, firmante, cargo y lugar de pago.",
        "Guarda y comprueba un documento de muestra antes de utilizarlo en operación.",
      ],
      important:
        "La configuración puede ser general o específica por sucursal; no sustituye la revisión del contrato.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/administracion/usuarios$"),
    help: {
      id: "users",
      title: "Usuarios",
      purpose: "Administra cuentas, sucursales y roles de los colaboradores.",
      steps: [
        "Busca la cuenta para evitar duplicarla.",
        "Captura o actualiza los datos y asigna sucursal, rol y estado correctos.",
        "Guarda y verifica el acceso que corresponde a la función de la persona.",
      ],
      important:
        "Solo concede los permisos necesarios. Los colaboradores renuevan su contraseña cada 15 días.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/administracion/roles$"),
    help: {
      id: "roles",
      title: "Roles y permisos",
      purpose: "Define qué acciones puede realizar cada perfil.",
      steps: [
        "Selecciona el rol y revisa los permisos ya asignados.",
        "Habilita únicamente las acciones que correspondan a su responsabilidad.",
        "Guarda y comprueba con una cuenta de ese perfil; los cambios afectan a sus usuarios.",
      ],
      important:
        "Gerente General tiene control operativo, pero no permisos técnicos para editar roles.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/mensajes/enviar$"),
    help: {
      id: "compose",
      title: "Enviar mensajes",
      purpose: "Prepara mensajes para clientes o personal autorizados.",
      steps: [
        "Selecciona sucursal y tipo de destinatario.",
        "Escribe parte del nombre para encontrar al cliente o colaborador y confirma la selección.",
        "Redacta, revisa y confirma el mensaje antes de enviarlo.",
      ],
      important:
        "Enviar un mensaje no modifica deudas ni registra pagos. La entrega push depende de la configuración y del dispositivo.",
      primaryLabel: "",
    },
  },
  {
    pattern: new RegExp("^/mensajes$"),
    help: {
      id: "messages",
      title: "Mis mensajes",
      purpose: "Consulta los mensajes de tu cuenta.",
      steps: [
        "Revisa el título, contenido y fecha de los mensajes recibidos.",
        "Marca como leído cuando hayas revisado el mensaje.",
        "Actualiza o carga más mensajes si necesitas consultar otros avisos.",
      ],
      important:
        "La bandeja conserva mensajes aunque el teléfono no muestre la notificación; revisa los saldos en el estado de cuenta.",
      primaryLabel: "Actualizar",
    },
  },
];
export function moduleHelpFor(path: string): ModuleHelp {
  const normalized = path.split(/[?#]/)[0]!.replace(/\/$/, "") || "/";
  return (
    entries.find((entry) => entry.pattern.test(normalized))?.help ?? {
      id: "unknown",
      title: "Ayuda de esta pantalla",
      purpose: "Consulta las opciones disponibles en esta pantalla.",
      steps: [
        "Verifica la dirección de la página.",
        "Utiliza el menú para acceder a un módulo autorizado.",
      ],
      important: "Si el problema persiste, solicita apoyo al administrador.",
      primaryLabel: "",
    }
  );
}
