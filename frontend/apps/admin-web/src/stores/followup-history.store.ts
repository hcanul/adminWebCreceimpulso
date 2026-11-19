import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  followupHistoryService,
  type FollowupHistory,
  type HistoryFilters,
} from '../services/followup-history.service';
import { administrationError } from '../services/administration.service';
export const useFollowupHistoryStore = defineStore('followupHistory', () => {
  const data = ref<FollowupHistory | null>(null),
    loading = ref(false),
    error = ref('');
  let request: AbortController | undefined;
  function clear() {
    request?.abort();
    data.value = null;
    loading.value = false;
    error.value = '';
  }
  async function load(filters: HistoryFilters) {
    clear();
    const c = new AbortController();
    request = c;
    loading.value = true;
    try {
      const result = await followupHistoryService.get(filters, c.signal);
      if (!c.signal.aborted) data.value = result;
    } catch (e) {
      if (!c.signal.aborted) error.value = administrationError(e);
    } finally {
      if (!c.signal.aborted) loading.value = false;
    }
  }
  return { data, loading, error, load, clear };
});
