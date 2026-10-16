import type { Lookup } from '../services/intake.service';
export function clientEntryNotice(result: Lookup) {
  if (!result.found)
    return {
      allowed: true,
      title: 'CURP verificada · sin coincidencias',
      text: 'La estructura de la CURP es válida y no encontramos un registro interno. ¿Deseas iniciar la captura? La identidad aún requiere revisión documental.',
      confirmLabel: 'Iniciar expediente',
    };
  const last = result.last_credit;
  const active = result.active_credits ?? [];
  const summary = [
    result.client?.name || 'Persona registrada',
    `Sucursal de alta: ${result.client?.branch || 'No disponible'}`,
    `Estado del cliente: ${result.client?.status || 'No disponible'}`,
    active.length
      ? `Créditos activos: ${active.length}. ${active.map((credit) => `${credit.group || 'Grupo sin nombre'} (${credit.branch || 'Sucursal no disponible'})`).join('; ')}`
      : 'Sin créditos activos registrados en esta base.',
    last
      ? `Último crédito: ${last.group || 'Grupo sin nombre'} · ${last.status}. Fecha de registro: ${last.registered_at ? new Date(last.registered_at).toLocaleDateString('es-MX', { timeZone: 'America/Cancun' }) : 'No disponible'}. Esta no es la fecha de desembolso.`
      : 'No hay créditos entregados o pagados registrados en esta base.',
  ].join('\n\n');
  if (!result.can_edit)
    return {
      allowed: false,
      title: 'CURP registrada · edición restringida',
      text: `${summary}\n\nSolicita apoyo a la sucursal responsable. No se creará otra persona con esta CURP.`,
      confirmLabel: 'Entendido',
    };
  const readOnly = !!result.application && result.application.status !== 'borrador';
  return {
    allowed: true,
    title: 'Esta CURP ya está registrada',
    text: `${summary}\n\n${readOnly ? 'La expediente ya fue enviado. Puedes consultarla sin modificar su versión.' : '¿Deseas abrir el expediente para capturar o modificar su información?'}`,
    confirmLabel: readOnly ? 'Consultar expediente' : 'Sí, abrir expediente',
  };
}
