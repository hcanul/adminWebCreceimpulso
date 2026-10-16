import { api } from './http';
export interface Contribution {
  client_id: string;
  payer_client_id: string;
  amount: string;
}
export interface CollectionInput {
  id: string;
  balance_version: string;
  amount: string;
  method: string;
  bank_name: string | null;
  bank_reference: string | null;
  deposited_on: string | null;
  notes: string | null;
  confirmed: boolean;
  contributions: Contribution[];
}
export interface CollectionMember {
  client_id: string;
  name: string;
  folio: string;
  balance: string;
  due: string;
  overdue: string;
  days_overdue: number;
}
export interface PaymentRecord {
  reversal?: null | { id: string; actor_name: string; reason: string; reversed_on: string };
  id: string;
  actor_name: string;
  received_on: string;
  method: string;
  amount: string;
  bank_name: string | null;
  bank_reference: string | null;
  deposited_on: string | null;
  notes: string | null;
  allocations: {
    client_id: string;
    payer_client_id: string;
    amount: string;
    number: number;
    due_on: string;
  }[];
}
export interface CollectionData {
  balance_version: string;
  status: string;
  as_of: string;
  members: CollectionMember[];
  payments: { data: PaymentRecord[]; current_page: number; last_page: number; total: number };
}
export const collectionsService = {
  async get(id: string, page = 1) {
    return (
      await api.get<CollectionData>(`/credit/applications/${id}/payments`, { params: { page } })
    ).data;
  },
  async save(id: string, input: CollectionInput) {
    return (await api.post<CollectionData>(`/credit/applications/${id}/payments`, input)).data;
  },
};
