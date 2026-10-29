import type { AuthorizationStatus, AuthorizationTerms } from './authorization.service';
import { api } from './http';
import type { Page } from './intake.service';
export interface CreditRate {
  id: string;
  name: string;
  cicle: number;
  min_amount: string | null;
  max_amount: string | null;
  rate_factor: string;
  is_active: boolean;
  calculation_method: string | null;
  terms_hash: string;
}
export interface Estimate {
  principal: string;
  weekly_payment: string;
  total_repayment: string;
  interest: string;
  guarantee: string;
  net_delivery: string;
}
export interface CreditMember {
  client_id: string;
  folio: string | null;
  name: string;
  role_in_group: string;
  eligible: boolean;
  issues: string[];
}
export interface CreditContext {
  team: { id: string; name: string; branch: string; promoter: string | null };
  issues: string[];
  members: CreditMember[];
}
export interface CreditSnapshot {
  weeks: number;
  guarantee_percent: number;
  member_limit: string;
  rate: { id: string; name: string; factor: string };
  members: Array<CreditMember & { amount_requested: string; estimate: Estimate }>;
  totals: Estimate;
  team_name: string;
  branch_name: string;
}
export interface CreditApplication {
  id: string;
  folio: string;
  status: string;
  review_status?: ReviewStatus;
  authorization_status?: AuthorizationStatus;
  authorization_version?: number;
  approved_snapshot?: AuthorizationTerms | null;
  total_requested: string;
  total_approved: string | null;
  requested_at: string | null;
  request_snapshot?: CreditSnapshot | null;
  disbursement_method: 'cash_branch' | 'bank_deposit' | null;
  repayment_method?: 'cash_branch' | 'bank_deposit' | null;
  team?: { id: string; name: string };
  branch?: { id: string; name: string };
}
export interface CreditInput {
  id: string;
  team_id: string;
  rate_id: string;
  rate_signature: string;
  member_limit: string;
  participants: Array<{ client_id: string; amount_requested: string }>;
}
export type RateInput = Pick<
  CreditRate,
  'name' | 'cicle' | 'min_amount' | 'max_amount' | 'rate_factor' | 'is_active'
>;
export type ReviewStatus = 'pending' | 'in_review' | 'returned' | 'ready_for_authorization';
export type ReviewAction = 'save' | 'return' | 'complete' | 'resubmit';
export interface ReviewCheck {
  client_id: string;
  key: string;
  status: 'pending' | 'verified' | 'observed';
  notes: string | null;
}
export interface ReviewFile {
  id: string;
  client_id: string;
  version: number;
  status: string;
  fields: Record<string, unknown>;
}
export interface CreditReview {
  status: ReviewStatus;
  version: number;
  checks: ReviewCheck[];
  files: ReviewFile[];
  history: Array<{
    id: string;
    version: number;
    actor_name: string;
    action: ReviewAction;
    to_status: ReviewStatus;
    notes: string | null;
    created_at: string;
    checks: ReviewCheck[];
    files: ReviewFile[];
  }>;
}
export const reviewLabels: Record<ReviewStatus, string> = {
  pending: 'Pendiente de Gestión',
  in_review: 'En revisión',
  returned: 'Devuelta para corrección',
  ready_for_authorization: 'Lista para autorización',
};
export const reviewCheckLabels: Record<string, string> = {
  identity: 'Identidad',
  address: 'Domicilio',
  references: 'Referencias',
  visit: 'Visita',
  internal_history: 'Historial interno',
  signed_documents: 'Documentos firmados',
};
export const creditService = {
  async review(id: string) {
    return (await api.get<CreditReview>(`/credit/applications/${id}/review`)).data;
  },
  async saveReview(
    id: string,
    input: { version: number; action: ReviewAction; notes: string; checks: ReviewCheck[] },
  ) {
    return (
      await api.post<{ status: ReviewStatus; version: number }>(
        `/credit/applications/${id}/review`,
        input,
      )
    ).data;
  },
  async list(
    search = '',
    page = 1,
    reviewStatus = '',
    authorizationStatus = '',
    financialStatus = '',
  ) {
    return (
      await api.get<Page<CreditApplication>>('/credit/applications', {
        params: {
          search,
          page,
          review_status: reviewStatus || undefined,
          authorization_status: authorizationStatus || undefined,
          financial_status: financialStatus || undefined,
        },
      })
    ).data;
  },
  async get(id: string) {
    return (await api.get<CreditApplication>(`/credit/applications/${id}`)).data;
  },
  async context(team: string) {
    return (await api.get<CreditContext>(`/credit/teams/${team}/context`)).data;
  },
  async create(input: CreditInput) {
    return (await api.post<CreditApplication>('/credit/applications', input)).data;
  },
  async rates(search = '', page = 1, management = false) {
    return (
      await api.get<Page<CreditRate>>('/credit/rates', {
        params: { search, page, management: management ? 1 : 0 },
      })
    ).data;
  },
  async saveRate(input: RateInput, id?: string) {
    return (
      id
        ? await api.put<CreditRate>(`/credit/rates/${id}`, input)
        : await api.post<CreditRate>('/credit/rates', input)
    ).data;
  },
};
