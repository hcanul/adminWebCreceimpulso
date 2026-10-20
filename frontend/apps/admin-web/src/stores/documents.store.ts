import { defineStore } from 'pinia';
import { ref } from 'vue';
import { documentsService, type DocumentIndex } from '../services/documents.service';
import { administrationError } from '../services/administration.service';
export const useDocumentsStore = defineStore('documents', () => {
  const data = ref<DocumentIndex | null>(null),
    loading = ref(false),
    error = ref('');
  let controller: AbortController | null = null;
  async function load(type: string, id: string) {
    controller?.abort();
    const c = new AbortController();
    controller = c;
    data.value = null;
    loading.value = true;
    error.value = '';
    try {
      const result = await documentsService.list(type, id, c.signal);
      if (!c.signal.aborted) data.value = result;
    } catch (e) {
      if (!c.signal.aborted) error.value = administrationError(e);
    } finally {
      if (!c.signal.aborted) loading.value = false;
    }
  }
  function clear() {
    controller?.abort();
    data.value = null;
    loading.value = false;
    error.value = '';
  }
  return { data, loading, error, load, clear };
});
