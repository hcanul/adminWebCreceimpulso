import { defineStore } from 'pinia';
import { ref } from 'vue';
import { branchesService, type BranchRecord } from '../services/branches.service';
import type { Page } from '../services/cash.service';
import { administrationError } from '../services/administration.service';
export const useBranchesStore = defineStore('branchesManagement', () => {
  const data = ref<Page<BranchRecord> | null>(null),
    loading = ref(false),
    error = ref('');
  let request: AbortController | undefined;
  function clear() {
    request?.abort();
    data.value = null;
    error.value = '';
    loading.value = false;
  }
  async function load(params: { search: string; status: string; page: number }) {
    clear();
    const c = new AbortController();
    request = c;
    loading.value = true;
    try {
      const result = await branchesService.list(params, c.signal);
      if (!c.signal.aborted) data.value = result;
    } catch (e) {
      if (!c.signal.aborted) error.value = administrationError(e);
    } finally {
      if (!c.signal.aborted) loading.value = false;
    }
  }
  return { data, loading, error, load, clear };
});
