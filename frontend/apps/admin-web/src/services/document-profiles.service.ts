import { api } from './http';
export interface DocumentProfile {
  scope: string;
  version: number;
  use_general: boolean;
  fields: {
    legal_address: string;
    representative_name: string;
    representative_position: string;
    payment_place: string;
    signing_place: string;
  };
}
export interface ProfileResult {
  profile: DocumentProfile;
  effective: {
    source: string;
    version: number;
    fields: DocumentProfile['fields'];
    missing: string[];
  };
}
export const documentProfilesService = {
  async get(scope: string, signal: AbortSignal) {
    return (await api.get<ProfileResult>(`/administration/document-profiles/${scope}`, { signal }))
      .data;
  },
  async save(profile: DocumentProfile) {
    return (
      await api.put<ProfileResult>(`/administration/document-profiles/${profile.scope}`, profile)
    ).data;
  },
  async branches(signal: AbortSignal) {
    return (
      await api.get<{ branches: { id: string; name: string }[] }>('/administration/options', {
        signal,
      })
    ).data.branches;
  },
};
