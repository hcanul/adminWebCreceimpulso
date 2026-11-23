import { startSessionActivity } from '../../../../packages/shared/session-inactivity';
import { readSession, removeSession } from './session';
import { getServerAddress } from './server-settings';
import { api } from './http';
export function trackSessionInactivity() {
  const redirect = () => {
    window.dispatchEvent(new Event('client-session-expired'));
    window.location.replace(
      `${window.location.pathname}${window.location.search}#/login?expired=idle`,
    );
    window.location.reload();
  };
  window.addEventListener('client-idle-expired', redirect);
  const stop = startSessionActivity({
    namespace: 'crece.client',
    server: getServerAddress,
    readToken: () => readSession(false),
    async heartbeat(token, idleSeconds) {
      await api.post(
        '/session/activity',
        { idle_seconds: idleSeconds },
        { headers: { Authorization: `Bearer ${token}` } },
      );
    },
    async expire(token) {
      if (!(await removeSession(token))) return;
      window.dispatchEvent(new Event('client-session-expired'));
      window.location.replace(
        `${window.location.pathname}${window.location.search}#/login?expired=idle`,
      );
      window.location.reload();
    },
  });
  return () => {
    stop();
    window.removeEventListener('client-idle-expired', redirect);
  };
}
