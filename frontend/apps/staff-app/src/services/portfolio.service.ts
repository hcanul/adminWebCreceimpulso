import { api } from './http';
export interface PortfolioFilters {
  search: string;
  status: string;
  page: number;
}
export interface PortfolioTotals {
  installment_balance: string;
  late_fee_balance: string;
  balance: string;
  due: string;
  overdue: string;
  due_today: string;
}
export interface PortfolioRow extends PortfolioTotals {
  id: string;
  folio: string;
  group: string;
  branch: string;
  days_overdue: number;
  oldest_due: string | null;
  next_due: string | null;
}
export interface Portfolio {
  as_of: string;
  summary: PortfolioTotals & { credits: number; overdue_credits: number };
  data: PortfolioRow[];
  page: number;
  last_page: number;
  total: number;
}
export const portfolioService = {
  async get(params: PortfolioFilters, signal: AbortSignal) {
    return (await api.get<Portfolio>('/credit/portfolio', { params, signal })).data;
  },
};
