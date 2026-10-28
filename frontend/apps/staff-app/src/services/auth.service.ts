import { api } from './http';
export interface StaffUser {
  id: string;
  name: string;
  email: string;
  branch_id: string | null;
  is_active: boolean | number;
  roles: { id: number; name: string }[];
  permissions: string[];
}
export const authService = {
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
