import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  authorizationService,
  type AuthorizationData,
  type ProposalInput,
} from '../services/authorization.service';
export const useAuthorizationStore = defineStore('staff-creditAuthorization', () => {
  const data = ref<AuthorizationData | null>(null);
  let generation = 0;
  async function load(id: string) {
    const current = ++generation;
    const result = await authorizationService.get(id);
    if (current === generation) data.value = result;
    return current === generation;
  }
  async function propose(id: string, input: ProposalInput) {
    return authorizationService.propose(id, input);
  }
  async function decide(
    id: string,
    decision: 'approve' | 'return',
    notes: string,
    firstDueOn?: string,
  ) {
    if (!data.value) throw Error('Carga las condiciones antes de decidir.');
    return authorizationService.decide(id, {
      version: data.value.version,
      decision,
      notes,
      ...(decision === 'approve' && firstDueOn ? { first_due_on: firstDueOn } : {}),
    });
  }
  function clear() {
    generation++;
    data.value = null;
  }
  return { data, load, propose, decide, clear };
});
