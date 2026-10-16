import { defineStore } from 'pinia';
import { ref } from 'vue';
import { dashboardService, type DashboardSummary } from '../services/dashboard.service';
export const useDashboardStore = defineStore('dashboard', () => {
  const data = ref<DashboardSummary | null>(null);
  let generation = 0;
  async function load() {
    const current = ++generation;
    try {
      const result = await dashboardService.get();
      if (current === generation) data.value = result;
    } catch (error) {
      if (current === generation) data.value = null;
      throw error;
    }
  }
  function clear() {
    generation++;
    data.value = null;
  }
  return { data, load, clear };
});
