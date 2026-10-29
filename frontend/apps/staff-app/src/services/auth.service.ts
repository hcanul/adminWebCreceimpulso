import { api } from './http';
export interface StaffUser {
  password_change_required?: boolean;
  password_expires_at?: string | null;
  id: string;
  name: string;
  email: string;
  branch_id: string | null;
  is_active: boolean | number;
  roles: { id: number; name: string }[];
  permissions: string[];
}
export const authService = {
  async changePassword(current: string, password: string, confirmation: string) {
    return (
      await api.post<{ token: string }>('/password', {
        current_password: current,
        password,
        password_confirmation: confirmation,
      })
    ).data;
  },
  async login(email: string, password: string) {
    return (
      await api.post<{ data: { token: string } }>('/login', {
        email,
        password,
        device_name: 'Crece Impulso Colaboradores',
      })
    ).data.data;
  },
  async me() {
    return (await api.get<StaffUser>('/me')).data;
  },
  async logout() {
    await api.post('/logout');
  },
};
