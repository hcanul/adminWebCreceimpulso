import { api } from './http';

export type ReportKind = 'portfolio' | 'statement' | 'deposits' | 'collection_log' | 'agenda';
export interface ReportCredit {
  id: string;
  folio: string;
  team?: { name: string };
}
export const reportsService = {
  async credits(search: string, signal: AbortSignal) {
    return (
      await api.get<{ data: ReportCredit[] }>('/credit/applications', {
        params: { search, financial_status: 'entregado' },
        signal,
      })
    ).data.data;
  },
  async credit(id: string) {
    return (
      await api.get<
        ReportCredit & {
          participations: {
            client_id: string;
            client: { first_name: string; last_name: string };
          }[];
        }
      >(`/credit/applications/${id}`)
    ).data;
  },
  async render(kind: ReportKind, params: Record<string, string>, signal: AbortSignal) {
    return (await api.get<{ html: string }>(`/credit/reports/${kind}`, { params, signal })).data
      .html;
  },
};
