import { api } from './http';

export interface AuthUser {
  password_change_required?: boolean;
  password_expires_at?: string | null;
  id: string;
  name: string;
  email: string;
  branch_id: string | null;
  is_active: boolean | number;
  permissions?: string[];
  roles?: Array<{ id: number; name: string }>;
}
interface LoginResponse {
  data: { token: string; user: AuthUser; roles: string[] };
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
  async login(email: string, password: string, deviceName: string) {
    const { data } = await api.post<LoginResponse>('/login', {
      email,
      password,
      device_name: deviceName,
    });
    return data.data;
  },
  async me(token: string) {
    const { data } = await api.get<AuthUser>('/me', {
      headers: { Authorization: `Bearer ${token}` },
    });
    return data;
  },
  async logout(token: string) {
    await api.post('/logout', {}, { headers: { Authorization: `Bearer ${token}` } });
  },
};
