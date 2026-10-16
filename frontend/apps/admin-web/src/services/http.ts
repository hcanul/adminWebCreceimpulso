import axios from 'axios';

// Todos los servicios comparten esta instancia; la URL incluye /api.
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  timeout: 15000,
  headers: { Accept: 'application/json' },
});

export function apiErrorMessage(error: unknown): string {
  if (!axios.isAxiosError(error)) return 'No fue posible completar la operación.';
  if (!error.response)
    return 'No pudimos conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.';
  if (error.response.status === 429)
    return 'Demasiados intentos. Espera un momento antes de continuar.';
  if (error.response.status === 401 || error.response.status === 422)
    return 'Revisa tu correo y contraseña, o consulta si tu cuenta está activa.';
  if (error.response.status === 403) return 'Tu cuenta no tiene acceso a esta operación.';
  return 'El servidor no pudo completar la operación. Inténtalo de nuevo.';
}
