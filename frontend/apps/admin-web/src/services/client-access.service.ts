import { api } from './http';
export interface ActivationClient {
  id: string;
  folio: string;
  first_name: string;
  last_name: string;
  branch: { name: string };
}
export const clientAccessService = {
  async search(identifier: string) {
    return (
      await api.post<{ clients: ActivationClient[] }>('/client-access/search', { identifier })
    ).data.clients;
  },
  async issue(id: string) {
    return (
      await api.post<{ code: string; expires_at: string }>(
        `/client-access/clients/${id}/activation`,
        { identity_verified: true },
      )
    ).data;
  },
};
