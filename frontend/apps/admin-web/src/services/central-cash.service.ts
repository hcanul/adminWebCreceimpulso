import { api } from './http';
import type { Page } from './cash.service';
export interface CentralCashFilters {
  search?: string;
  from: string;
  to: string;
  method: string;
  page: number;
  branch_id?: string | undefined;
}
export interface CashTotals {
  cash_gross: string;
  bank_gross: string;
  cash_reversed: string;
  bank_reversed: string;
  cash_net: string;
  bank_net: string;
}
export interface CashMovement {
  id: string;
  payment_id: string;
  event_on: string;
  kind: 'payment' | 'reversal';
  method: string;
  amount: string;
  branch: string;
  credit_folio: string;
  group: string;
  number: number;
  cashier: string;
  closing_folio: string | null;
  bank_name: string | null;
  bank_reference: string | null;
  deposited_on: string | null;
}
export interface CentralCash {
  from: string;
  to: string;
  branches: (CashTotals & {
    branch_id: string;
    branch: string;
    payment_count: number;
    reversal_count: number;
  })[];
  summary: CashTotals & { net: string };
  movements: Page<CashMovement>;
  generated_at_local: string;
}
export const centralCashService = {
  async options(signal: AbortSignal) {
    return (
      await api.get<{ branches: { id: string; name: string }[]; today: string }>(
        '/cash/central/options',
        { signal },
      )
    ).data;
  },
  async get(params: CentralCashFilters, signal: AbortSignal) {
    return (await api.get<CentralCash>('/cash/central', { params, signal })).data;
  },
  async export(params: CentralCashFilters, signal: AbortSignal) {
    return (await api.get<Blob>('/cash/central/export', { params, signal, responseType: 'blob' }))
      .data;
  },
};
