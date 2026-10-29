import { defineStore } from 'pinia';
import { ref } from 'vue';
import { guaranteeService, type GuaranteeState } from '../services/guarantee.service';
import { administrationError } from '../services/administration.service';
export const useGuaranteeStore = defineStore('staff-creditGuarantee', () => {
  const data = ref<GuaranteeState | null>(null),
    loading = ref(false),
    error = ref('');
  let controller: AbortController | null = null;
  async function load(id: string) {
    controller?.abort();
    const request = new AbortController();
    controller = request;
    data.value = null;
    error.value = '';
    loading.value = true;
    try {
      const result = await guaranteeService.get(id, request.signal);
      if (!request.signal.aborted) data.value = result;
    } catch (e) {
      if (!request.signal.aborted) error.value = administrationError(e);
    } finally {
      if (!request.signal.aborted) loading.value = false;
    }
  }
  function clear() {
    controller?.abort();
    data.value = null;
    error.value = '';
    loading.value = false;
  }
  return { data, loading, error, load, clear };
});
