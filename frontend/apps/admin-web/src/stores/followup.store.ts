import { defineStore } from 'pinia';
import { ref } from 'vue';
import { followupService, type Followup, type FollowupFilters } from '../services/followup.service';
import { administrationError } from '../services/administration.service';
export const useFollowupStore = defineStore('portfolioFollowup', () => {
  const data = ref<Followup | null>(null),
    loading = ref(false),
    error = ref('');
  let request: AbortController | undefined;
  function clear() {
    request?.abort();
    data.value = null;
    error.value = '';
    loading.value = false;
  }
  async function load(id: string, filters: FollowupFilters) {
    clear();
    const controller = new AbortController();
    request = controller;
    loading.value = true;
    try {
      const result = await followupService.get(id, filters, controller.signal);
      if (!controller.signal.aborted) data.value = result;
    } catch (e) {
      if (!controller.signal.aborted) error.value = administrationError(e);
    } finally {
      if (!controller.signal.aborted) loading.value = false;
    }
  }
  return { data, loading, error, load, clear };
});
