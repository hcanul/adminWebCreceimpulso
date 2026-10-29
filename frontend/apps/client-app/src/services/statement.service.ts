import { api } from './http';
export interface StatementRow {
  week: number;
  due_on: string;
  amount: number;
  paid: number;
  remaining: number;
  status: 'paid' | 'partial' | 'overdue' | 'due_today' | 'upcoming';
}
export interface ClientStatement {
  active: true;
  demo: boolean;
  as_of: string;
  folio: string;
  group: string;
  personal: StatementRow[];
  group_schedule: StatementRow[];
  terms?: {
    requested: number;
    approved: number;
    interest: number;
    guarantee_retained: number;
    net_delivered: number;
    repayment_method: string;
    rate: string;
  };
  late_fees?: {
    enabled: boolean;
    balance: number;
    charges: { charged_on: string; base: number; amount: number; paid: number; balance: number }[];
  };
}
export async function loadStatement(): Promise<ClientStatement | null> {
  const { data } = await api.get<ClientStatement | { active: false }>('/client/statement');
  return data.active ? data : null;
}
