import { moduleHelpFor } from '@creceimpulso/mobile-help/staff-base';
import type { HelpGuide } from '@creceimpulso/mobile-help/types';
const guides: Record<string, HelpGuide> = {
  '/': {
    id: 'staff-home',
    title: 'Mi jornada',
    purpose: 'Accede a las tareas disponibles para tu perfil.',
    steps: [
      'Revisa tu nombre, rol y sucursal.',
      'Entra a grupos, expedientes o al módulo operativo que permita tu cuenta.',
      'Antes de salir a campo, prepara los expedientes necesarios para trabajar sin conexión.',
    ],
    important:
      'Los promotores no cobran en campo. Las operaciones financieras de Caja requieren conexión y permisos de sucursal.',
    primaryLabel: 'Consultar grupos',
  },
  '/login': {
    id: 'staff-login',
    title: 'Acceso del personal',
    purpose: 'Entra con tu correo de colaborador y contraseña.',
    steps: [
      'Comprueba el servidor configurado antes de iniciar sesión.',
      'Escribe tu correo y contraseña, o utiliza la biometría si la activaste previamente.',
      'Si tu contraseña venció, renuévala antes de continuar.',
    ],
    important:
      'El correo puede recordarse sin guardar la contraseña en texto. La sesión termina tras 30 minutos de inactividad.',
    primaryLabel: 'Entrar',
  },
  '/configuracion': {
    id: 'server',
    title: 'Configurar servidor',
    purpose: 'Define la API con la que se comunicará esta instalación.',
    steps: [
      'Captura la dirección HTTPS de la API; para pruebas usamos https://pruebas.creceimpulso.com/api.',
      'Prueba la conexión para comprobar la dirección y revisa el resultado.',
      'Guarda la dirección. Si cambias de servidor, vuelve a iniciar sesión con una cuenta de ese servidor.',
    ],
    important:
      'Cambiar el servidor no migra expedientes ni pagos. No borres información local pendiente de sincronizar.',
    primaryLabel: 'Guardar',
  },
  '/sin-conexion': {
    id: 'offline',
    title: 'Trabajo sin conexión',
    purpose: 'Captura altas y correcciones en el espacio local preparado para campo.',
    steps: [
      'Con internet, prepara el espacio y los expedientes autorizados; define y conserva tu clave local.',
      'Sin internet, desbloquea ese espacio y guarda los cambios antes de cerrar la pantalla.',
      'Al recuperar conexión, sincroniza y revisa cada resultado o conflicto antes de darlo por aplicado.',
    ],
    important:
      'La clave local es distinta de tu contraseña de acceso. No se cobran pagos en campo ni se generan contratos desde este espacio. No desinstales la app con cambios pendientes.',
    primaryLabel: 'Desbloquear',
  },
  '/mi-acceso': {
    id: 'my-access',
    title: 'Mi acceso',
    purpose: 'Consulta tu perfil, sucursal y permisos disponibles.',
    steps: [
      'Comprueba que aparezcan tu nombre, rol y sucursal correctos.',
      'Revisa las funciones permitidas antes de solicitar acceso a un módulo.',
      'Si hay una asignación incorrecta, pide su corrección a Administración.',
    ],
    important:
      'Los permisos se comprueban en el servidor. Esta pantalla no permite concederte privilegios.',
    primaryLabel: '',
  },
  '/clientes/expedientes': {
    id: 'staff-clients',
    title: 'Expedientes de clientes',
    purpose: 'Consulta y prepara expedientes para su captura o corrección.',
    steps: [
      'Busca la persona o revisa los expedientes de tu sucursal.',
      'Verifica la CURP antes de crear o modificar sus datos.',
      'Prepara para trabajo sin conexión solo los expedientes que necesites y sincroniza después las correcciones.',
    ],
    important:
      'Los contratos y documentos se generan e imprimen en Admin Web. La captura de expediente no registra un monto de crédito.',
    primaryLabel: '',
  },
};
export function mobileHelpFor(path: string): HelpGuide {
  if (guides[path]) return guides[path];
  const canonical =
    path === '/clientes/capturar'
      ? '/clientes/captura'
      : path === '/usuarios'
        ? '/administracion/usuarios'
        : path === '/roles'
          ? '/administracion/roles'
          : path;
  const guide = moduleHelpFor(canonical);
  if (guide.id === 'documents')
    return { ...guide, important: 'La documentación se genera en Admin Web.' };
  return guide;
}
