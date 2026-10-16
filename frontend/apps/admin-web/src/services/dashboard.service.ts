import { api } from './http';
export interface DashboardSummary {
  as_of: string;
  business_date: string;
  scope: string;
  groups: number | null;
  in_review: number | null;
  active_credits: number | null;
  payments: null | { count: number; total: string; cash: string; bank: string; scope: string };
  inbox: { label: string; count: number; to: string }[];
}
export const dashboardService = {
  async get() {
    return (await api.get<DashboardSummary>('/dashboard')).data;
  },
};
