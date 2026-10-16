import { api } from './http';
import type { AuthorizationTerms } from './authorization.service';
export interface Installment {
  id?: string;
  client_id: string;
  number: number;
  due_on: string;
  amount_due: string;
  amount_paid: string;
}
export interface DisbursementInput {
  id: string;
  authorization_version: number;
  disbursed_on: string;
  first_due_on: string;
  bank_name: string | null;
  bank_reference: string | null;
  notes: string;
  confirmed: boolean;
}
export interface DisbursementData {
  record: null | {
    id: string;
    actor_name: string;
    disbursed_on: string;
    first_due_on: string;
    method: string;
    bank_name?: string | null;
    bank_reference?: string | null;
    capital: string;
    guarantee_retained: string;
    net_delivered: string;
    notes?: string;
    created_at: string;
    snapshot: AuthorizationTerms;
  };
  installments: Installment[];
}
export const disbursementService = {
  async get(id: string) {
    return (await api.get<DisbursementData>(`/credit/applications/${id}/disbursement`)).data;
  },
  async save(id: string, input: DisbursementInput) {
    return (await api.post<DisbursementData>(`/credit/applications/${id}/disbursement`, input))
      .data;
  },
};
