import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  cashService,
  type ClosingDetail,
  type ClosingPreview,
  type ClosingRecord,
  type ClosingInput,
  type Page,
} from '../services/cash.service';
export const useCashStore = defineStore('cashClosings', () => {
  const records = ref<Page<ClosingRecord> | null>(null),
    preview = ref<ClosingPreview | null>(null),
    detail = ref<ClosingDetail | null>(null);
  let generation = 0;
  async function list(branch: string, date: string, page = 1) {
    const g = generation;
    const data = await cashService.list(branch, date, page);
    if (g === generation) records.value = data;
  }
  async function loadPreview(branch: string, date: string, page = 1) {
    const g = generation;
    const data = await cashService.preview(branch, date, page);
    if (g === generation) preview.value = data;
  }
  async function load(id: string, page = 1, adjustmentsPage = 1) {
    const g = generation;
    const data = await cashService.get(id, page, adjustmentsPage);
    if (g === generation) detail.value = data;
  }
  async function close(input: ClosingInput) {
    const data = await cashService.close(input);
    return data;
  }
  function resetPreview() {
    preview.value = null;
  }
  function clear() {
    generation++;
    records.value = null;
    preview.value = null;
    detail.value = null;
  }
  return { records, preview, detail, list, loadPreview, load, close, resetPreview, clear };
});
