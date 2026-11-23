import {
  createSessionActivity,
  startSessionActivity,
} from '../../../../packages/shared/session-inactivity';
import { api } from './http';
import { useAuthStore } from '../stores/auth.store';
export const sessionActivity = createSessionActivity('crece.admin', () =>
  String(api.defaults.baseURL),
);
export function trackSessionInactivity() {
  const auth = useAuthStore();
  return startSessionActivity({
    namespace: 'crece.admin',
    server: () => String(api.defaults.baseURL),
    readToken: () => Promise.resolve(auth.token),
    async heartbeat(token, idleSeconds) {
      await api.post(
        '/session/activity',
        { idle_seconds: idleSeconds },
        { headers: { Authorization: `Bearer ${token}` } },
      );
    },
    async expire(token) {
      if (auth.token !== token) return;
      await Promise.resolve(auth.clearSession());
      window.location.replace(
        `${window.location.pathname}${window.location.search}#/login?expired=idle`,
      );
      window.location.reload();
    },
  });
}
