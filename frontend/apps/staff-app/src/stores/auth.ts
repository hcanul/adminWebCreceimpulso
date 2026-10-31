import { useStatementStore } from './statement.store';
import { usePortfolioStore } from './portfolio.store';
import { useGuaranteeStore } from './guarantee.store';
import { useDisbursementStore } from './disbursement.store';
import { useCollectionsStore } from './collections.store';
import { useAuthorizationStore } from './authorization.store';
import { useCashStore } from './cash.store';
import { useCreditStore } from './credit.store';
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
    useCreditStore().clear();
    useCashStore().clear();
    useAuthorizationStore().clear();
    useCollectionsStore().clear();
    useDisbursementStore().clear();
    useGuaranteeStore().clear();
    usePortfolioStore().clear();
    useStatementStore().clear();

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
  async function changePassword(current: string, password: string, confirmation: string) {
    const result = await authService.changePassword(current, password, confirmation);
    sessionStorage.setItem(sessionKey, result.token);
    user.value = result.user;
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
    changePassword,
    logout,
    clear,
  };
});
