import { api } from './http';
import type { Page } from './cash.service';
export interface BranchRecord {
  id: string;
  folio_code: string;
  name: string;
  city: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  is_active: boolean;
  management_version: number;
}
export interface BranchFields {
  name: string;
  city: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  is_active: boolean;
}
export type BranchCommand = BranchFields & { id?: string; version?: number };
export const branchesService = {
  async list(params: { search: string; status: string; page: number }, signal: AbortSignal) {
    return (await api.get<Page<BranchRecord>>('/branches', { params, signal })).data;
  },
  async save(data: BranchCommand, id: string | null) {
    return (
      id
        ? await api.put<BranchRecord>(`/branches/${id}`, data)
        : await api.post<BranchRecord>('/branches', data)
    ).data;
  },
};
