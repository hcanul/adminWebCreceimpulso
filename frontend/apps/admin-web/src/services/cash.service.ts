import { api } from './http';
export interface Page<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}
export interface ClosingPayment {
  id: string;
  credit_folio: string;
  number: number;
  received_on: string;
  method: string;
  amount: string;
  bank_name: string | null;
  bank_reference: string | null;
  deposited_on: string | null;
}
export interface ClosingRecord {
  id: string;
  folio: string;
  branch_id: string;
  branch_name: string;
  actor_name: string;
  business_date: string;
  cash_total: string;
  bank_total: string;
  cash_counted: string;
  cash_difference: string;
  payment_count: number;
  notes: string | null;
  created_at: string;
  closed_at_local: string;
}
export interface ClosingPreview {
  cash_total: string;
  bank_total: string;
  payment_count: number;
  version: string;
  payments: Page<ClosingPayment>;
}
export interface ClosingDetail {
  adjustments?: Page<{
    id: string;
    credit_payment_id: string;
    credit_folio: string;
    number: number;
    actor_name: string;
    amount: string;
    method: string;
    reason: string;
    reversed_on: string;
  }>;
  record: ClosingRecord;
  payments: Page<ClosingPayment>;
}
export interface ClosingReport {
  record: ClosingRecord;
  payments: ClosingPayment[];
  adjustments: NonNullable<ClosingDetail['adjustments']>['data'];
  adjustment_totals: { cash_total: string; bank_total: string; count: number };
  generated_at_local: string;
}
export interface ClosingInput {
  id: string;
  branch_id: string;
  business_date: string;
  version: string;
  cash_counted: string;
  notes: string | null;
  confirmed: boolean;
}
export const cashService = {
  async report(id: string, signal: AbortSignal) {
    return (await api.get<ClosingReport>(`/cash/closings/${id}/report`, { signal })).data;
  },
  async options() {
    return (
      await api.get<{ branches: { id: string; name: string }[]; today: string }>('/cash/options')
    ).data;
  },
  async list(branch: string, date: string, page = 1) {
    return (
      await api.get<Page<ClosingRecord>>('/cash/closings', {
        params: { branch_id: branch || undefined, business_date: date || undefined, page },
      })
    ).data;
  },
  async preview(branch: string, date: string, page = 1) {
    return (
      await api.get<ClosingPreview>('/cash/preview', {
        params: { branch_id: branch, business_date: date, page },
      })
    ).data;
  },
  async get(id: string, page = 1, adjustmentsPage = 1) {
    return (
      await api.get<ClosingDetail>(`/cash/closings/${id}`, {
        params: { page, adjustments_page: adjustmentsPage },
      })
    ).data;
  },
  async close(input: ClosingInput) {
    return (await api.post<ClosingDetail>('/cash/closings', input)).data;
  },
};
