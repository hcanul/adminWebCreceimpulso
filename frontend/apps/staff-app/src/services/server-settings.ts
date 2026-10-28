import axios from 'axios';
import { normalizeServerAddress } from './server-address';
export const serverKey = 'crece.staff.server';
export const defaultServer = import.meta.env.QCLI_API_URL || 'http://localhost:8000/api';
export function getServerAddress(): string {
  const saved = localStorage.getItem(serverKey);
  if (saved) {
    try {
      return normalizeServerAddress(saved);
    } catch {
      localStorage.removeItem(serverKey);
    }
  }
  return defaultServer;
}
export function saveServerAddress(value: string): void {
  const normalized = normalizeServerAddress(value);
  localStorage.setItem(serverKey, normalized);
  if (localStorage.getItem(serverKey) !== normalized)
    throw new Error('No se pudo persistir el servidor');
  // Session and in-memory data belong exclusively to the previous server.
  sessionStorage.removeItem('crece.staff.session');
  window.location.hash = '/login';
  window.location.reload();
}
export async function probeServer(value: string): Promise<number> {
  const baseURL = normalizeServerAddress(value);
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  const hex = Array.from(bytes, (v) => v.toString(16).padStart(2, '0')).join('');
  const challenge = `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
  const start = performance.now();
  // Isolated client: probing another server must never send an existing session token.
  const probe = axios.create({
    baseURL,
    timeout: 10000,
    headers: { Accept: 'application/json' },
    withCredentials: false,
  });
  const { data } = await probe.get<{ service: string; challenge: string }>('/client/connection', {
    params: { challenge },
  });
  if (data.service !== 'creceimpulso-client-api' || data.challenge !== challenge)
    throw new Error('El servidor no respondió como una API de Crece Impulso.');
  return Math.round(performance.now() - start);
}

export function probeErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const status = error.response?.status;
    if (status && status >= 500)
      return `El servidor respondió con error ${status}. Revisa que MySQL y la API estén funcionando. Puedes guardar la dirección de todos modos.`;
    if (status === 404)
      return 'El servidor respondió, pero no encontró la API. Revisa el puerto y la ruta /api. Puedes guardar la dirección de todos modos.';
    if (status === 429)
      return 'Demasiadas pruebas seguidas. Espera un minuto; puedes guardar la dirección ahora.';
    if (status)
      return `El servidor respondió con error ${status}. Revisa la configuración de la API.`;
    return 'No hay respuesta. Comprueba la IP y el puerto (por ejemplo 192.168.1.67:8000), la misma Wi-Fi y el permiso de red local. Puedes guardar y probar más tarde.';
  }
  return error instanceof Error
    ? error.message
    : 'No fue posible verificar la conexión. Puedes guardar la dirección y probar después.';
}
