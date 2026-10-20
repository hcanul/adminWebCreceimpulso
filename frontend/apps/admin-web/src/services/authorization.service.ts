import { api } from './http';
import type { Estimate } from './credit.service';
export type PaymentMethod = 'cash_branch' | 'bank_deposit';
export type AuthorizationStatus =
  'awaiting_proposal' | 'pending_authorization' | 'returned' | 'authorized';
export interface AuthorizationTerms {
  late_fee_policy?: { id: string; weekly_percent: number } | null;
  first_due_on?: string | null;
  review_version: number;
  weeks: number;
  guarantee_percent: number;
  rate: { id: string; name: string; factor: string };
  members: Array<{
    client_id: string;
    name: string;
    folio: string | null;
    role_in_group: string;
    amount_requested: string;
    amount_proposed?: string;
    amount_approved?: string;
    estimate: Estimate;
  }>;
  totals: Estimate;
  disbursement_method: PaymentMethod;
  repayment_method: PaymentMethod;
  proposed_by_name: string;
  proposed_at: string;
}
export interface AuthorizationData {
  late_fee_policy?: { id: string; weekly_percent: number } | null;
  status: AuthorizationStatus;
  version: number;
  proposal: AuthorizationTerms | null;
  history: Array<{
    id: string;
    version: number;
    actor_name: string;
    created_at: string;
    action: 'propose' | 'approve' | 'return';
    notes: string;
    proposal: AuthorizationTerms;
  }>;
}
export interface ProposalInput {
  version: number;
  notes: string;
  disbursement_method: PaymentMethod;
  repayment_method: PaymentMethod;
  participants: Array<{ client_id: string; amount_proposed: string }>;
}
export const authorizationLabels: Record<AuthorizationStatus, string> = {
  awaiting_proposal: 'Pendiente de condiciones del gerente',
  pending_authorization: 'Pendiente de autorización comercial',
  returned: 'Condiciones devueltas al gerente',
  authorized: 'Autorizado',
};
export const methodLabel = (value: string | null | undefined) =>
  value === 'cash_branch'
    ? 'En sucursal'
    : value === 'bank_deposit'
      ? 'Depósito bancario'
      : 'Por definir';
export const authorizationService = {
  async get(id: string) {
    return (await api.get<AuthorizationData>(`/credit/applications/${id}/authorization`)).data;
  },
  async propose(id: string, input: ProposalInput) {
    return (await api.post(`/credit/applications/${id}/proposal`, input)).data as {
      status: AuthorizationStatus;
      version: number;
    };
  },
  async decide(
    id: string,
    input: {
      version: number;
      decision: 'approve' | 'return';
      notes: string;
      first_due_on?: string;
    },
  ) {
    return (await api.post(`/credit/applications/${id}/authorization`, input)).data as {
      status: AuthorizationStatus;
      version: number;
    };
  },
};
