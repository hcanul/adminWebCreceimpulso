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
import { readSession, writeSession, removeSession } from '../services/session';
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
  let generation = 0;
  async function clear() {
    generation++;
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
    await removeSession();
  }
  window.addEventListener('staff-session-expired', () => {
    void clear().catch(() => undefined);
  });
  async function restore() {
    const attempt = generation;
    if (!(await readSession())) {
      user.value = null;
      return;
    }
    const restored = await authService.me();
    if (attempt === generation) user.value = restored;
  }
  async function login(email: string, password: string) {
    await clear();
    const result = await authService.login(email, password);
    await writeSession(result.token);
    try {
      await restore();
    } catch (error) {
      await clear();
      throw error;
    }
  }
  async function changePassword(current: string, password: string, confirmation: string) {
    const result = await authService.changePassword(current, password, confirmation);
    await writeSession(result.token);
    user.value = result.user;
  }
  async function logout() {
    await authService.logout();
    await clear();
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
