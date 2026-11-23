import type { HelpGuide } from '@creceimpulso/mobile-help/types';
const guides: Record<string, HelpGuide> = {
  '/': {
    id: 'client-home',
    title: 'Mi crédito y mis pagos',
    purpose: 'Consulta tu crédito activo, vencimientos, atrasos y comprobantes.',
    steps: [
      'Revisa el crédito y la fecha de corte antes de interpretar el saldo o el siguiente vencimiento.',
      'En Estados de cuenta, cambia entre Personal y Grupo. Desliza la tabla para ver todas las columnas.',
      'En Mis comprobantes abre el ticket QR para consultar la validez del pago en el servidor.',
    ],
    important:
      'Verde: pagado. Amarillo: incompleto aún no vencido. Rojo: vencido, incluso con abonos. El saldo grupal no es tu deuda personal. Las cuotas de la tabla no incluyen mora.',
    primaryLabel: 'Mis mensajes',
  },
  '/login': {
    id: 'client-login',
    title: 'Acceso de clientes',
    purpose: 'Ingresa con tu número de cliente y contraseña.',
    steps: [
      'Comprueba el servidor configurado si no logras conectarte.',
      'Escribe tu número de cliente y contraseña, o elige la biometría si ya la activaste.',
      'Si todavía no activas tu cuenta, solicita tu código presencial en la sucursal y usa Activar mi cuenta.',
    ],
    important:
      'La cuenta de cliente es diferente de la cuenta del personal. No compartas códigos ni contraseñas.',
    primaryLabel: 'Entrar',
  },
  '/activar': {
    id: 'client-activate',
    title: 'Activar o recuperar mi cuenta',
    purpose: 'Utiliza el código entregado después de verificar tu identidad en sucursal.',
    steps: [
      'Ten a mano tu número de cliente y el código recibido.',
      'Completa las comprobaciones solicitadas y define una contraseña propia.',
      'Guarda la activación e inicia sesión; si el código venció, solicita otro en tu sucursal.',
    ],
    important:
      'El código es de uso único. El monto del crédito puede servir como comprobación de identidad; no genera una solicitud nueva.',
    primaryLabel: '',
  },
  '/configuracion': {
    id: 'client-server',
    title: 'Configurar servidor',
    purpose: 'Conecta la app al servidor que te indique tu sucursal.',
    steps: [
      'Captura la dirección HTTPS completa; el servidor de pruebas es https://pruebas.creceimpulso.com/api.',
      'Prueba la conexión y revisa si la API respondió correctamente.',
      'Guarda la dirección y vuelve a iniciar sesión con la cuenta correspondiente a ese servidor.',
    ],
    important:
      'Cambiar de servidor no traslada tu cuenta ni modifica tu crédito. No compartas tu contraseña para comprobar la conexión.',
    primaryLabel: 'Guardar',
  },
  '/mensajes': {
    id: 'client-messages',
    title: 'Mis mensajes',
    purpose: 'Revisa los avisos y recordatorios enviados a tu cuenta.',
    steps: [
      'Consulta el título, contenido y fecha del mensaje.',
      'Marca como leído cuando lo hayas revisado.',
      'Para conocer el saldo actualizado, vuelve a tu estado de cuenta.',
    ],
    important:
      'Un recordatorio no es un comprobante de pago. La bandeja puede conservar mensajes aunque no hayas visto la notificación push.',
    primaryLabel: 'Actualizar',
  },
  '/cambiar-contrasena': {
    id: 'client-password',
    title: 'Actualizar mi contraseña',
    purpose: 'Sustituye tu contraseña temporal por una contraseña propia.',
    steps: [
      'Captura la información que solicita el formulario.',
      'Escribe una contraseña nueva y confírmala exactamente igual.',
      'Guarda y continúa hacia tu cuenta. Si no reconoces el acceso, solicita ayuda en sucursal.',
    ],
    important:
      'No compartas tu contraseña ni tu código. Al cambiarla, revisa la configuración del acceso biométrico de este dispositivo.',
    primaryLabel: '',
  },
};
export function mobileHelpFor(path: string): HelpGuide {
  const guide = guides[path] ?? guides['/']!;
  if (path !== '/') return guide;
  return {
    ...guide,
    targets: [
      {
        selector: '[aria-label="Resumen del crédito activo"]',
        title: 'Tu crédito activo',
        description:
          'Revisa el saldo de cuotas, las cuotas vencidas, la mora y el siguiente vencimiento. Los importes corresponden a la fecha de corte indicada.',
      },
      {
        selector: '[data-help-client="statement-tabs"]',
        title: 'Personal y grupo',
        description:
          'Estas pestañas permiten alternar los pagos personales y los totales del grupo. El saldo del grupo no es tu deuda individual. Al cerrar la guía puedes cambiar de pestaña.',
      },
      {
        selector: '.legend',
        title: 'Colores de los pagos',
        description:
          'Verde: pagado. Amarillo: abono incompleto sin vencer. Rojo: vencido, aunque tenga abonos. Desliza la tabla para consultar todas las columnas.',
      },
      {
        selector: '[data-help-client="receipts"]',
        title: 'Tickets de pagos recibidos',
        description:
          'Abre el ticket QR para consultar su validez en el servidor. Un comprobante reversado ya no acredita un pago vigente.',
      },
    ],
  };
}
