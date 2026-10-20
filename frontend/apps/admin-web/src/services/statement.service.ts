import { api } from './http';
import type { CollectionMember, LateFeeState } from './collections.service';
export interface StatementMember extends CollectionMember {
  role_in_group: string;
  principal: string;
  interest: string;
  weekly_payment: string;
  guarantee: string;
  net_delivery: string;
  total_repayment: string;
  paid: string;
}
export interface StatementPayment {
  id: string;
  number: number;
  folio: string;
  received_on: string;
  method: string;
  amount: string;
  reversed_on: string | null;
  allocations: {
    kind?: 'installment' | 'late_fee';
    charged_on?: string | null;
    client_id: string;
    payer_client_id: string;
    amount: string;
    number: number;
    due_on: string;
  }[];
}
export interface CreditStatement {
  late_fees?: LateFeeState;
  credit: {
    id: string;
    folio: string;
    status: string;
    group: string | null;
    branch: string | null;
    disbursed_on: string;
    first_due_on: string;
    repayment_method: string;
    rate_name: string;
    rate_factor: string;
  };
  as_of: string;
  generated_at_local: string;
  members: StatementMember[];
  installments: {
    client_id: string;
    number: number;
    due_on: string;
    amount_due: string;
    amount_paid: string;
  }[];
  guarantees?: {
    id: string;
    amount: string;
    applied_on: string;
    reversed_on: string | null;
    allocations: {
    kind?: 'installment' | 'late_fee';
    charged_on?: string | null; client_id: string; amount: string; number: number; due_on: string }[];
  }[];
  late_fee_preview?: {
    mode: 'simulation';
    policy: string;
    weekly_percent: number;
    as_of: string;
    total: string;
    installments: {
      client_id: string;
      number: number;
      due_on: string;
      amount: string;
      charges: { week: number; charged_on: string; base: string; amount: string }[];
    }[];
  } | null;
  payments: StatementPayment[];
}
export const statementService = {
  async get(id: string, signal: AbortSignal) {
    return (await api.get<CreditStatement>(`/credit/applications/${id}/statement`, { signal }))
      .data;
  },
};
