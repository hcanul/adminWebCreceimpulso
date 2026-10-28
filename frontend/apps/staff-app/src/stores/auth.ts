import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { authService, type StaffUser } from '../services/auth.service';
import { useIntakeStore } from './intake.store';
import { useAdministrationStore } from './administration.store';
import { sessionKey } from '../services/http';
export const useStaffAuth = defineStore('staff-auth', () => {
  const user = ref<StaffUser | null>(null);
  const roles = computed(() => user.value?.roles?.map((role) => role.name) || []);
  const canIntake = computed(
    () =>
      roles.value.includes('Super Admin') || !!user.value?.permissions?.includes('intake.access'),
  );
  const isSuperAdmin = computed(() => roles.value.includes('Super Admin'));
  const isAuthenticated = computed(() => !!user.value);
  function can(permission: string) {
    return roles.value.includes('Super Admin') || !!user.value?.permissions?.includes(permission);
  }
  function clear() {
    useIntakeStore().clear();
    useAdministrationStore().clear();
    user.value = null;
    sessionStorage.removeItem(sessionKey);
  }
  window.addEventListener('staff-session-expired', clear);
  async function restore() {
    if (!sessionStorage.getItem(sessionKey)) {
      user.value = null;
      return;
    }
    user.value = await authService.me();
  }
  async function login(email: string, password: string) {
    clear();
    const result = await authService.login(email, password);
    sessionStorage.setItem(sessionKey, result.token);
    try {
      await restore();
    } catch (error) {
      clear();
      throw error;
    }
  }
  async function logout() {
    try {
      await authService.logout();
    } finally {
      clear();
    }
  }
  return {
    user,
    roles,
    isSuperAdmin,
    canIntake,
    isAuthenticated,
    can,
    restore,
    login,
    logout,
    clear,
  };
});
