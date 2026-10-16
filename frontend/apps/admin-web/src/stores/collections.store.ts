import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  collectionsService,
  type CollectionData,
  type CollectionInput,
} from '../services/collections.service';
export const useCollectionsStore = defineStore('creditCollections', () => {
  const data = ref<CollectionData | null>(null);
  let generation = 0;
  async function load(id: string, page = 1) {
    const current = ++generation;
    const result = await collectionsService.get(id, page);
    if (current === generation) data.value = result;
  }
  async function save(id: string, input: CollectionInput) {
    const current = ++generation;
    const result = await collectionsService.save(id, input);
    if (current === generation) data.value = result;
  }
  function clear() {
    generation++;
    data.value = null;
  }
  return { data, load, save, clear };
});
