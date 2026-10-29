import { api } from './http';
export interface GuaranteeState {
  credit_folio: string;
  status: string;
  as_of: string;
  available_on: string;
  eligible_date: boolean;
  version: string;
  members: {
    client_id: string;
    name: string;
    folio: string;
    retained: string;
    applied: string;
    available: string;
    balance: string;
    applicable: string;
  }[];
  history: {
    id: string;
    actor_name: string;
    amount: string;
    reason: string;
    applied_on: string;
    reversal: { actor_name: string; reason: string; reversed_on: string } | null;
    allocations: { client_id: string; amount: string; number: number; due_on: string }[];
  }[];
}
export const guaranteeService = {
  async get(id: string, signal: AbortSignal) {
    return (await api.get<GuaranteeState>(`/credit/applications/${id}/guarantee`, { signal })).data;
  },
  async apply(id: string, input: { id: string; version: string; reason: string }) {
    return (await api.post<GuaranteeState>(`/credit/applications/${id}/guarantee`, input)).data;
  },
  async reverse(id: string, reason: string) {
    await api.post(`/guarantees/${id}/reverse`, { reason });
  },
};
