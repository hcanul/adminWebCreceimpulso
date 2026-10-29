import { api } from './http';
export interface SignedFile {
  id: string;
  actor_name: string;
  created_at: string;
  sha256: string;
  bytes: number;
}
export interface IssuedDocument {
  id: string;
  kind: string;
  source_version: number;
  template_version: number;
  actor_name: string;
  created_at: string;
  sha256: string;
  signed_files: SignedFile[];
}
export interface DocumentIndex {
  source_version: number;
  can_issue: boolean;
  documents: IssuedDocument[];
}
export const documentsService = {
  async list(type: string, id: string, signal: AbortSignal) {
    return (await api.get<DocumentIndex>(`/documents/sources/${type}/${id}`, { signal })).data;
  },
  async issue(type: string, id: string, kind: string) {
    return (await api.post<{ id: string }>(`/documents/sources/${type}/${id}`, { kind })).data;
  },
  async show(id: string) {
    return (await api.get<{ html: string; sha256: string }>(`/documents/versions/${id}`)).data;
  },
  async print(id: string) {
    return (await api.post<{ html: string }>(`/documents/versions/${id}/print`)).data;
  },
  async attach(id: string, file: File) {
    const form = new FormData();
    form.append('file', file);
    await api.post(`/documents/versions/${id}/signed`, form);
  },
  async download(id: string) {
    return (await api.get<Blob>(`/documents/signed/${id}`, { responseType: 'blob' })).data;
  },
};
