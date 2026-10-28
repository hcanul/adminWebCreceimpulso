import { api } from './http';
export interface ClientProfile {
  name: string;
  folio: string;
  branch: string;
}
export const authService = {
  async activate(code: string, password: string, password_confirmation: string) {
    await api.post('/client/activate', { code, password, password_confirmation });
  },
  async login(identifier: string, password: string) {
    return (
      await api.post<{ token: string; expires_at: string }>('/client/login', {
        identifier,
        password,
      })
    ).data;
  },
  async me() {
    return (await api.get<ClientProfile>('/client/me')).data;
  },
  async logout() {
    await api.post('/client/logout');
  },
};
