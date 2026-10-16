import { defineStore } from 'pinia';
import { ref } from 'vue';
import { statementService, type CreditStatement } from '../services/statement.service';
import { administrationError } from '../services/administration.service';
export const useStatementStore = defineStore('creditStatement', () => {
  const data = ref<CreditStatement | null>(null),
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
      const result = await statementService.get(id, request.signal);
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
