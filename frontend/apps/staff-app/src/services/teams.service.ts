import { api } from './http';
import type { Team } from './intake.service';
export type { Team, Member } from './intake.service';
export interface Page<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}
export const teamsService = {
  async list(search: string, page: number, signal?: AbortSignal) {
    return (
      await api.get<Page<Team>>('/intake/teams', {
        params: { search, page },
        ...(signal ? { signal } : {}),
      })
    ).data;
  },
  async get(id: string) {
    return (await api.get<Team>(`/intake/teams/${encodeURIComponent(id)}`)).data;
  },
};
