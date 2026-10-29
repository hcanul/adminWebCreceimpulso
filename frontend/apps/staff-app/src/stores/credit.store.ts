import { defineStore } from 'pinia';
import { ref } from 'vue';
import { creditService, type CreditApplication } from '../services/credit.service';
export const useCreditStore = defineStore('staff-credit', () => {
  const requests = ref<CreditApplication[]>([]),
    pages = ref(1),
    total = ref(0);
  let generation = 0;
  async function load(
    search = '',
    page = 1,
    reviewStatus = '',
    authorizationStatus = '',
    financialStatus = '',
  ) {
    const current = ++generation;
    const result = await creditService.list(
      search,
      page,
      reviewStatus,
      authorizationStatus,
      financialStatus,
    );
    if (current !== generation) return;
    requests.value = result.data;
    pages.value = result.last_page;
    total.value = result.total;
  }
  function clear() {
    generation++;
    requests.value = [];
    pages.value = 1;
    total.value = 0;
  }
  return { requests, pages, total, load, clear };
});
