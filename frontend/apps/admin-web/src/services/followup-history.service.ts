import { api } from './http';
import type { Page } from './cash.service';
export interface HistoryFilters {
  search: string;
  author: string;
  page: number;
  branch_id?: string | undefined;
  from?: string | undefined;
  to?: string | undefined;
}
export interface HistoryEntry {
  id: string;
  loan_application_id: string;
  credit_folio: string;
  client_folio: string;
  first_name: string;
  last_name: string;
  group: string;
  branch: string;
  actor_name: string;
  observation: string;
  created_at: string;
}
export interface FollowupHistory {
  branches: { id: string; name: string }[];
  entries: Page<HistoryEntry>;
}
export const followupHistoryService = {
  async get(params: HistoryFilters, signal: AbortSignal) {
    return (await api.get<FollowupHistory>('/credit/followup', { params, signal })).data;
  },
};
