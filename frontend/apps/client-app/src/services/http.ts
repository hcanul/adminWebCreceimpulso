import axios from 'axios';
import { getServerAddress } from './server-settings';
export { sessionKey } from './session';
import { readSession, removeSession } from './session';
export const api = axios.create({
  baseURL: getServerAddress(),
  timeout: 15000,
  headers: { Accept: 'application/json' },
});
api.interceptors.request.use(async (config) => {
  const token = await readSession();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});
api.interceptors.response.use(
  (response) => response,
  async (error: unknown) => {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      const sent = error.config?.headers.Authorization;
      if (
        typeof sent === 'string' &&
        sent.startsWith('Bearer ') &&
        (await removeSession(sent.slice(7)))
      )
        window.dispatchEvent(new Event('client-session-expired'));
    }
    return Promise.reject(error instanceof Error ? error : new Error('Error de conexión'));
  },
);
export function errorMessage(error: unknown): string {
  if (!axios.isAxiosError(error)) return 'No fue posible completar la operación.';
  if (!error.response) return 'No pudimos conectar. Revisa tu conexión e inténtalo de nuevo.';
  if (error.response.status === 429)
    return 'Demasiados intentos. Espera un minuto antes de continuar.';
  if (error.response.status === 401)
    return 'Tu sesión venció o tu cuenta ya no está disponible. Inicia sesión nuevamente.';
  const data = error.response.data as { errors?: Record<string, string[]> };
  return (
    Object.values(data.errors || {})
      .flat()
      .join(' ') || 'No fue posible completar la operación. Consulta en tu sucursal.'
  );
}
