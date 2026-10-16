import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  portfolioService,
  type Portfolio,
  type PortfolioFilters,
} from '../services/portfolio.service';
import { administrationError } from '../services/administration.service';
export const usePortfolioStore = defineStore('creditPortfolio', () => {
  const data = ref<Portfolio | null>(null),
    loading = ref(false),
    error = ref('');
  let controller: AbortController | null = null;
  async function load(filters: PortfolioFilters) {
    controller?.abort();
    const request = new AbortController();
    controller = request;
    data.value = null;
    error.value = '';
    loading.value = true;
    try {
      const result = await portfolioService.get(filters, request.signal);
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
