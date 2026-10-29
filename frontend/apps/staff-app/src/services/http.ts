import axios from 'axios';
import { getServerAddress } from './server-settings';
export const sessionKey = 'crece.staff.session';
export const api = axios.create({
  baseURL: getServerAddress(),
  timeout: 15000,
  headers: { Accept: 'application/json' },
});
api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem(sessionKey);
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});
api.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    if (
      axios.isAxiosError(error) &&
      error.response?.status === 403 &&
      error.response.data?.code === 'PASSWORD_CHANGE_REQUIRED'
    ) {
      window.dispatchEvent(new Event('staff-password-expired'));
    }
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      sessionStorage.removeItem(sessionKey);
      window.dispatchEvent(new Event('staff-session-expired'));
    }
    return Promise.reject(error instanceof Error ? error : new Error('Error de conexión'));
  },
);
export function errorMessage(error: unknown): string {
  if (!axios.isAxiosError(error)) return 'No fue posible completar la operación.';
  if (!error.response)
    return 'No hay conexión con el servidor. Revisa tu red y la configuración del dispositivo.';
  if (error.response.status === 403) return 'Tu cuenta no tiene permiso para esta operación.';
  if (error.response.status === 401) return 'Tu sesión venció. Vuelve a iniciar sesión.';
  if (error.response.status === 429) return 'Demasiados intentos. Espera un minuto.';
  if (error.response.status >= 500)
    return 'El servidor no está disponible. Revisa la API y su base de datos.';
  const data = error.response.data as { errors?: Record<string, string[]> };
  return (
    Object.values(data.errors || {})
      .flat()
      .join(' ') || 'No fue posible completar la operación.'
  );
}
