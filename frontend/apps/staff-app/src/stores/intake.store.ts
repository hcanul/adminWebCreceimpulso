import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  intakeService,
  type Team,
  type Application,
  type Lookup,
} from '../services/intake.service';
export const useIntakeStore = defineStore('intake', () => {
  const teams = ref<Team[]>([]);
  const teamPages = ref(1);
  const applications = ref<Application[]>([]);
  const applicationPages = ref(1);
  const lookup = ref<Lookup | null>(null);
  const pendingCurp = ref('');
  let generation = 0;
  async function loadTeams(page = 1, search = '') {
    const epoch = generation;
    const result = await intakeService.teams(page, search);
    if (epoch !== generation) return;
    teams.value = result.data;
    teamPages.value = result.last_page;
  }
  async function loadApplications(page = 1) {
    const epoch = generation;
    const result = await intakeService.applications(page);
    if (epoch !== generation) return;
    applications.value = result.data;
    applicationPages.value = result.last_page;
  }
  async function verifyCurp(curp: string, team: string) {
    lookup.value = null;
    const epoch = generation;
    const result = await intakeService.lookup(curp, team);
    if (epoch !== generation) throw new Error('La sesión cambió durante la consulta.');
    lookup.value = result;
    return result;
  }
  function clear() {
    generation++;
    teams.value = [];
    applications.value = [];
    lookup.value = null;
    pendingCurp.value = '';
  }
  return {
    teams,
    teamPages,
    applications,
    applicationPages,
    lookup,
    pendingCurp,
    loadTeams,
    loadApplications,
    verifyCurp,
    clear,
  };
});
