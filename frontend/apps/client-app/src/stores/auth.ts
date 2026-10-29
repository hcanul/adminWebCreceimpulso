import { defineStore } from 'pinia';
import { ref } from 'vue';
import { authService, type ClientProfile } from '../services/auth.service';
import { sessionKey } from '../services/http';
export const useClientAuth = defineStore('client-auth', () => {
  const profile = ref<ClientProfile | null>(null);
  function clear() {
    sessionStorage.removeItem(sessionKey);
    profile.value = null;
  }
  window.addEventListener('client-session-expired', clear);
  async function restore() {
    if (!sessionStorage.getItem(sessionKey)) {
      profile.value = null;
      return;
    }
    profile.value = await authService.me();
  }
  async function login(identifier: string, password: string) {
    clear();
    const result = await authService.login(identifier, password);
    sessionStorage.setItem(sessionKey, result.token);
    await restore();
  }
  async function logout() {
    await authService.logout();
    clear();
  }
  return { profile, restore, login, logout, clear };
});
