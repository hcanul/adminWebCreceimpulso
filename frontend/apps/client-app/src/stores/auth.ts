import { defineStore } from 'pinia';
import { ref } from 'vue';
import { authService, type ClientProfile } from '../services/auth.service';
import { readSession, writeSession, removeSession } from '../services/session';
export const useClientAuth = defineStore('client-auth', () => {
  const profile = ref<ClientProfile | null>(null);
  let generation = 0;
  async function clear() {
    generation++;
    profile.value = null;
    await removeSession();
  }
  window.addEventListener('client-session-expired', () => {
    void clear().catch(() => undefined);
  });
  async function restore() {
    const attempt = generation;
    if (!(await readSession())) {
      profile.value = null;
      return;
    }
    const restored = await authService.me();
    if (attempt === generation) profile.value = restored;
  }
  async function login(identifier: string, password: string) {
    await clear();
    const result = await authService.login(identifier, password);
    await writeSession(result.token);
    await restore();
  }
  async function logout() {
    await authService.logout();
    await clear();
  }
  return { profile, restore, login, logout, clear };
});
