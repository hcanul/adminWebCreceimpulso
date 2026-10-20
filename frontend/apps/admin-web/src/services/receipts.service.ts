import { api } from './http';
export interface PaymentReceipt {
  installment_amount?: string;
  late_fee_amount?: string;
  id: string;
  folio: string;
  credit_folio: string;
  amount: string;
  received_on: string;
  method: string;
  status: 'registered' | 'reversed';
  balance_version: string;
  reversed_on: string | null;
  reversal: null | { id: string; actor_name: string; reason: string; reversed_on: string };
  actor_name: string;
  bank_name: string | null;
  bank_reference: string | null;
  deposited_on: string | null;
  verification_url: string;
  allocations: {
    kind?: 'installment' | 'late_fee';
    charged_on?: string | null;
    beneficiary: string;
    payer: string;
    number: number;
    due_on: string;
    amount: string;
  }[];
}
export interface ReversalInput {
  id: string;
  balance_version: string;
  reason: string;
  confirmed: boolean;
}
export const receiptsService = {
  async reverse(id: string, input: ReversalInput) {
    return (await api.post<PaymentReceipt>(`/credit/receipts/${id}/reverse`, input)).data;
  },
  async get(id: string) {
    return (await api.get<PaymentReceipt>(`/credit/receipts/${id}`)).data;
  },
};
