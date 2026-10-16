import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { LocalStorage } from 'quasar';
import axios from 'axios';
import { useIntakeStore } from './intake.store';
import { authService, type AuthUser } from '../services/auth.service';

export const useAuthStore = defineStore('auth', () => {
  const saved = LocalStorage.getItem<string>('access_token');
  const token = ref<string | null>(typeof saved === 'string' && saved ? saved : null);
  const user = ref<AuthUser | null>(null);
  const roles = ref<string[]>([]);
  const can = (permission: string) =>
    roles.value.includes('Super Admin') || !!user.value?.permissions?.includes(permission);
  const initialized = ref(false);
  const restoreFailed = ref(false);
  const isAuthenticated = computed(() => !!token.value && !!user.value);
  let restoring: Promise<void> | null = null;
  let generation = 0;

  // No persistir perfiles, contraseñas ni expedientes en el almacenamiento de sesión.
  LocalStorage.remove('user_data');
  function clearSession() {
    generation++;
    useIntakeStore().clear();
    token.value = null;
    user.value = null;
    roles.value = [];
    initialized.value = true;
    restoreFailed.value = false;
    LocalStorage.remove('access_token');
    LocalStorage.remove('user_data');
  }
  async function login(email: string, password: string, deviceName = 'CreceImpulso Admin Web') {
    const attempt = ++generation;
    const data = await authService.login(email.trim(), password, deviceName);
    if (attempt !== generation) return;
    LocalStorage.set('access_token', data.token);
    token.value = data.token;
    user.value = data.user;
    roles.value = data.roles;
    initialized.value = true;
    restoreFailed.value = false;
  }
  async function restoreSession() {
    if (restoring) return restoring;
    if (initialized.value) return;
    const currentToken = token.value;
    const attempt = generation;
    if (!currentToken) {
      initialized.value = true;
      return;
    }
    restoring = (async () => {
      restoreFailed.value = false;
      try {
        const profile = await authService.me(currentToken);
        if (attempt !== generation) return;
        if (!profile.is_active) {
          clearSession();
          return;
        }
        user.value = profile;
        roles.value = profile.roles?.map((role) => role.name) ?? [];
      } catch (error) {
        if (attempt !== generation) return;
        if (axios.isAxiosError(error) && error.response?.status === 401) clearSession();
        else restoreFailed.value = true; // Un fallo de red no elimina el token.
      } finally {
        if (attempt === generation) initialized.value = true;
        restoring = null;
      }
    })();
    return restoring;
  }
  async function retrySession() {
    initialized.value = false;
    await restoreSession();
  }
  async function logout() {
    const currentToken = token.value;
    // Conservar la sesión si el servidor no pudo revocarla; permitir reintentar.
    if (currentToken) {
      try {
        await authService.logout(currentToken);
      } catch (error) {
        if (!(axios.isAxiosError(error) && error.response?.status === 401)) throw error;
      }
    }
    if (token.value === currentToken) clearSession();
  }
  return {
    user,
    token,
    roles,
    can,
    initialized,
    restoreFailed,
    isAuthenticated,
    login,
    restoreSession,
    retrySession,
    logout,
    clearSession,
  };
});
