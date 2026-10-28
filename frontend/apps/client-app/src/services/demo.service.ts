import { api } from './http';
export interface DemoReceipt {
  id: string;
  date: string;
  amount: number;
  method: string;
  reference: string | null;
  verification_url: string;
}
export interface DemoData {
  demo: true;
  as_of: string;
  loan: {
    folio: string;
    group: string;
    role: string;
    principal: number;
    interest: number;
    total: number;
    paid: number;
    balance: number;
    guarantee: number;
    delivered: number;
    weekly: number;
    overdue: number;
    due_today: number;
    late_fees: number;
    payable: number;
    next_due: string;
    first_due: string;
    last_due: string;
    days_overdue: number;
    rate: string;
  };
  schedule: {
    week: number;
    due_on: string;
    amount: number;
    paid: number;
    remaining: number;
    status: string;
  }[];
  receipts: DemoReceipt[];
  fees: { date: string; week: number; base: number; amount: number }[];
}
export async function loadClientDemo(): Promise<DemoData | null> {
  const { data } = await api.get<DemoData | { demo: false }>('/client/demo');
  return data.demo ? data : null;
}
