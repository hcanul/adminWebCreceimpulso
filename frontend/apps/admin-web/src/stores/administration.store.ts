import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  administrationService,
  type AdminUser,
  type AdminRole,
} from '../services/administration.service';
export const useAdministrationStore = defineStore('administration', () => {
  const users = ref<AdminUser[]>([]),
    roles = ref<AdminRole[]>([]);
  const branches = ref<{ id: string; name: string }[]>([]),
    permissions = ref<{ id: number; name: string }[]>([]);
  const lastPage = ref(1),
    total = ref(0);
  let generation = 0;
  async function loadOptions() {
    const current = generation;
    const data = await administrationService.options();
    if (current !== generation) return;
    roles.value = data.roles;
    branches.value = data.branches;
    permissions.value = data.permissions;
  }
  async function loadUsers(search = '', page = 1) {
    const current = generation;
    const data = await administrationService.users(search, page);
    if (current !== generation) return;
    users.value = data.data;
    lastPage.value = data.last_page;
    total.value = data.total;
  }
  function clear() {
    generation++;
    users.value = [];
    roles.value = [];
    branches.value = [];
    permissions.value = [];
    lastPage.value = 1;
    total.value = 0;
  }
  return { users, roles, branches, permissions, lastPage, total, loadOptions, loadUsers, clear };
});
