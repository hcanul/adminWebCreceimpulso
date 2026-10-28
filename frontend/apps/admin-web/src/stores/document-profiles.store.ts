import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  documentProfilesService,
  type ProfileResult,
  type DocumentProfile,
} from '../services/document-profiles.service';
export const useDocumentProfilesStore = defineStore('document-profiles', () => {
  const data = ref<ProfileResult | null>(null);
  const general = ref<DocumentProfile | null>(null);
  const branches = ref<{ id: string; name: string }[]>([]);
  let controller: AbortController | undefined;
  async function load(scope: string) {
    controller?.abort();
    const c = new AbortController();
    controller = c;
    data.value = null;
    const [profile, options, defaults] = await Promise.all([
      documentProfilesService.get(scope, c.signal),
      documentProfilesService.branches(c.signal),
      documentProfilesService.get('general', c.signal),
    ]);
    if (!c.signal.aborted) {
      data.value = profile;
      general.value = defaults.profile;
      branches.value = options;
    }
  }
  async function save(profile: DocumentProfile) {
    data.value = await documentProfilesService.save(profile);
  }
  function clear() {
    controller?.abort();
    data.value = null;
    branches.value = [];
    general.value = null;
  }
  return { data, general, branches, load, save, clear };
});
