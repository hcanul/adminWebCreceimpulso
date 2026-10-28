import axios from 'axios';
import { api, errorMessage } from './http';
export interface BranchOption {
  id: string;
  name: string;
}
export interface PromoterOption {
  id: string;
  name: string;
  branch_id: string | null;
}
export interface Member {
  id: string;
  role_in_group: string;
  client: {
    id: string;
    curp: string;
    folio: string;
    first_name: string;
    last_name: string;
    status: string;
  } | null;
}
export interface Team {
  id: string;
  name: string;
  branch_id: string;
  promoter_id: string | null;
  is_active: boolean;
  branch: BranchOption;
  promoter: PromoterOption | null;
  memberships_count?: number;
  memberships?: Member[];
}
export interface Page<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}
export interface Application {
  id: string;
  team_id: string;
  client_id: string;
  version: number;
  status: string;
  fields: Record<string, string | null>;
  client?: { folio: string; version: number; curp: string; first_name: string; last_name: string };
  team?: { id: string; name: string };
  updated_at?: string;
}
export interface CreditSummary {
  id: string;
  status: string;
  branch: string | null;
  folio: string;
  group: string | null;
  registered_at: string | null;
  pacted_date: string | null;
}
export interface Lookup {
  found: boolean;
  can_edit?: boolean;
  client?: {
    id: string;
    name: string | null;
    folio: string;
    branch: string | null;
    status: string;
    version: number | null;
  };
  active_credits?: CreditSummary[];
  last_credit?: CreditSummary | null;
  application?: Application | null;
  profile?: Record<string, string | null> | null;
  message?: string;
}
export interface SaveApplication {
  id: string;
  version: number;
  team_id: string;
  curp: string;
  client_id: string | null;
  client_version: number | null;
  confirm_existing: boolean;
  submit: boolean;
  fields: Record<string, string | null>;
}
export const intakeService = {
  async options() {
    return (
      await api.get<{
        branches: BranchOption[];
        promoters: PromoterOption[];
        promoter_candidates: PromoterOption[];
      }>('/intake/options')
    ).data;
  },
  async registerPromoter(user_id: string) {
    return (await api.post<PromoterOption>('/intake/promoters', { user_id })).data;
  },
  async teams(page = 1, search = '') {
    return (await api.get<Page<Team>>('/intake/teams', { params: { page, search } })).data;
  },
  async searchTeams(search: string, branch_id: string, signal?: AbortSignal) {
    return (
      await api.get<{ data: Team[]; has_more: boolean }>('/intake/team-search', {
        params: { search, branch_id },
        ...(signal ? { signal } : {}),
      })
    ).data;
  },
  async team(id: string) {
    return (await api.get<Team>(`/intake/teams/${id}`)).data;
  },
  async saveTeam(data: { name: string; branch_id: string; promoter_id: string }, id?: string) {
    return (
      await api.request<Team>({
        url: id ? `/intake/teams/${id}` : '/intake/teams',
        method: id ? 'put' : 'post',
        data,
      })
    ).data;
  },
  async committee(team: string, member: string, role: string) {
    await api.patch(`/intake/teams/${team}/members/${member}`, { role_in_group: role });
  },
  async lookup(curp: string, team_id: string) {
    return (await api.post<Lookup>('/intake/clients/lookup', { curp, team_id })).data;
  },
  async applications(page = 1) {
    return (await api.get<Page<Application>>('/intake/client-files', { params: { page } })).data;
  },
  async saveApplication(data: SaveApplication) {
    return (await api.post<Application>('/intake/client-files', data)).data;
  },
};
export function intakeError(error: unknown): { message: string; fields: Record<string, string[]> } {
  if (axios.isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(error)) {
    if (error.response?.status === 422)
      return {
        message: error.response.data.errors
          ? 'Revisa los campos señalados antes de continuar.'
          : error.response.data.message || 'No se puede completar esta operación.',
        fields: error.response.data.errors ?? {},
      };
    if (error.response?.status === 409)
      return {
        message:
          error.response.data.message || 'El expediente cambió. Consulta nuevamente la CURP.',
        fields: {},
      };
    if (error.response?.status === 404)
      return { message: 'No se encontró el registro o no tienes acceso a él.', fields: {} };
  }
  return { message: errorMessage(error), fields: {} };
}
