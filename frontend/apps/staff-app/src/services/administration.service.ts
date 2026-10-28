import { intakeError } from './intake.service';
import { api } from './http';
export interface AdminRole {
  id: number;
  name: string;
  permissions: { id: number; name: string }[];
}
export interface AdminUser {
  id: string;
  name: string;
  email: string;
  branch_id: string | null;
  is_active: boolean | number;
  roles: { id: number; name: string }[];
}
export interface UserInput {
  name: string;
  email: string;
  branch_id: string | null;
  is_active: boolean;
  roles: string[];
  password?: string;
  password_confirmation?: string;
}
export const administrationService = {
  async options() {
    return (
      await api.get<{
        branches: { id: string; name: string }[];
        roles: AdminRole[];
        permissions: { id: number; name: string }[];
      }>('/administration/options')
    ).data;
  },
  async users(search = '', page = 1) {
    return (
      await api.get<{ data: AdminUser[]; last_page: number; total: number }>(
        '/administration/users',
        { params: { search, page } },
      )
    ).data;
  },
  async saveUser(data: UserInput, id?: string) {
    return (
      id
        ? await api.put<AdminUser>(`/administration/users/${id}`, data)
        : await api.post<AdminUser>('/administration/users', data)
    ).data;
  },
  async saveRole(permissions: string[], id?: number, name?: string) {
    return (
      id
        ? await api.put<AdminRole>(`/administration/roles/${id}`, { permissions })
        : await api.post<AdminRole>('/administration/roles', { name, permissions })
    ).data;
  },
};
export const permissionLabels: Record<string, string> = {
  'cash.view': 'Consultar mis cortes de caja',
  'cash.close': 'Cerrar mis cobros por día y sucursal',
  'cash.audit': 'Consultar cortes de otros cajeros en las sucursales permitidas',
  'credit.view': 'Consultar solicitudes de crédito',
  'credit.create': 'Registrar solicitudes de crédito',
  'credit.reverse': 'Revertir pagos completos con motivo y auditoría',
  'credit.collect': 'Registrar cobros y consultar su historial en la sucursal asignada',
  'credit.disburse': 'Registrar desembolsos en la sucursal asignada',
  'credit.propose': 'Proponer condiciones de crédito (sucursal asignada)',
  'credit.authorize': 'Autorizar créditos en Coordinación Comercial (sucursal asignada)',
  'credit.review': 'Revisar créditos en Gestión (sucursal asignada)',
  'credit.rates': 'Administrar tasas de crédito',
  'intake.access': 'Acceder a grupos y expedientes',
  'intake.submit': 'Enviar expedientes a Gestión',
  'promoters.register': 'Vincular cuentas existentes de promotores',
};

export function administrationError(error: unknown): string {
  const result = intakeError(error);
  const messages = Object.values(result.fields).flat();
  return messages.length ? messages.join('\n') : result.message;
}
