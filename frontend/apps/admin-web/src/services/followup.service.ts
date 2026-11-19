import { api } from './http';
export interface FollowupMember {
  client_id: string;
  name: string;
  folio: string;
  phone: string | null;
  balance: string;
  due: string;
  overdue: string;
  days_overdue: number;
}
export interface FollowupEntry {
  id: string;
  client_id: string;
  actor_name: string;
  observation: string;
  created_at: string;
}
export interface PortfolioEvent {
  id: string;
  previous_class: string;
  next_class: string;
  version: number;
  actor_name: string;
  reason: string;
  created_at: string;
}
export interface Followup {
  loan_id: string;
  folio: string;
  group: string;
  branch_id: string;
  classification: 'ordinary' | 'recovery';
  version: number;
  can_manage: boolean;
  members: FollowupMember[];
  events: PortfolioEvent[];
  notes: { data: FollowupEntry[]; current_page: number; last_page: number; total: number };
}
export interface FollowupFilters {
  page: number;
  client_id?: string | undefined;
  from?: string | undefined;
  to?: string | undefined;
  author?: string | undefined;
}
export interface FollowupNoteCommand {
  id: string;
  client_id: string;
  observation: string;
}
export interface ClassifyCommand {
  id: string;
  version: number;
  classification: 'ordinary' | 'recovery';
  reason: string;
}
export const followupService = {
  async get(id: string, params: FollowupFilters, signal: AbortSignal) {
    return (await api.get<Followup>(`/credit/applications/${id}/followup`, { params, signal }))
      .data;
  },
  async append(id: string, data: FollowupNoteCommand) {
    await api.post(`/credit/applications/${id}/followup`, data);
  },
  async classify(id: string, data: ClassifyCommand) {
    await api.post(`/credit/applications/${id}/portfolio`, data);
  },
};
