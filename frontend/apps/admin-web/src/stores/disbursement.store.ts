import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  disbursementService,
  type DisbursementData,
  type DisbursementInput,
} from '../services/disbursement.service';
export const useDisbursementStore = defineStore('creditDisbursement', () => {
  const data = ref<DisbursementData | null>(null);
  let generation = 0;
  async function load(id: string) {
    const current = ++generation;
    const result = await disbursementService.get(id);
    if (current === generation) data.value = result;
  }
  async function save(id: string, input: DisbursementInput) {
    const current = ++generation;
    const result = await disbursementService.save(id, input);
    if (current === generation) data.value = result;
  }
  function clear() {
    generation++;
    data.value = null;
  }
  return { data, load, save, clear };
});
