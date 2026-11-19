import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  centralCashService,
  type CentralCash,
  type CentralCashFilters,
} from '../services/central-cash.service';
import { administrationError } from '../services/administration.service';
export const useCentralCashStore = defineStore('centralCash', () => {
  const data = ref<CentralCash | null>(null),
    branches = ref<{ id: string; name: string }[]>([]),
    loading = ref(false),
    error = ref('');
  let request: AbortController | undefined, optionsRequest: AbortController | undefined;
  function invalidate() {
    request?.abort();
    data.value = null;
    loading.value = false;
    error.value = '';
  }
  function clear() {
    invalidate();
    optionsRequest?.abort();
    branches.value = [];
  }
  async function initialize() {
    optionsRequest?.abort();
    const c = new AbortController();
    optionsRequest = c;
    try {
      const options = await centralCashService.options(c.signal);
      if (!c.signal.aborted) branches.value = options.branches;
    } catch (e) {
      if (!c.signal.aborted) error.value = administrationError(e);
    }
  }
  async function load(filters: CentralCashFilters) {
    invalidate();
    const c = new AbortController();
    request = c;
    loading.value = true;
    try {
      const result = await centralCashService.get(filters, c.signal);
      if (!c.signal.aborted) data.value = result;
    } catch (e) {
      if (!c.signal.aborted) error.value = administrationError(e);
    } finally {
      if (!c.signal.aborted) loading.value = false;
    }
  }
  return { data, branches, loading, error, load, initialize, invalidate, clear };
});
