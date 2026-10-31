import { Capacitor } from '@capacitor/core';
import { PushNotifications } from '@capacitor/push-notifications';
import { api } from './http';
import { getServerAddress } from './server-settings';

export interface PushState {
  available: { android: boolean; ios: boolean };
  enabled: boolean;
}
export const pushPlatform = Capacitor.getPlatform();
export const nativePush = pushPlatform === 'android' || pushPlatform === 'ios';
const key = 'crece.client.push.installation';
function installationId(): string {
  let value = localStorage.getItem(key);
  if (!value) {
    value = crypto.randomUUID();
    localStorage.setItem(key, value);
  }
  return value;
}
export async function pushState(): Promise<PushState> {
  return (
    await api.get<PushState>('/client/push-device', {
      params: { installation_id: installationId() },
    })
  ).data;
}
export async function enableReminders(): Promise<void> {
  if (!nativePush) throw new Error('Los recordatorios requieren la aplicación instalada.');
  const source = getServerAddress();
  const status = await pushState();
  if (!(pushPlatform === 'ios' ? status.available.ios : status.available.android))
    throw new Error('La sucursal todavía no habilita los recordatorios.');
  let permission = await PushNotifications.checkPermissions();
  if (permission.receive === 'prompt' || permission.receive === 'prompt-with-rationale')
    permission = await PushNotifications.requestPermissions();
  if (permission.receive !== 'granted')
    throw new Error(
      'Permite las notificaciones en Ajustes del dispositivo para recibir recordatorios.',
    );
  const token = await new Promise<string>((resolve, reject) => {
    let done = false;
    const cleanups: (() => void)[] = [];
    const finish = (value?: string) => {
      if (done) return;
      done = true;
      clearTimeout(timer);
      cleanups.forEach((f) => f());
      if (value) resolve(value);
      else
        reject(
          new Error(
            'El dispositivo no pudo registrarse para notificaciones. Intenta de nuevo más tarde.',
          ),
        );
    };
    const timer = setTimeout(() => finish(), 15000);
    void (async () => {
      try {
        const success = await PushNotifications.addListener('registration', (t) => finish(t.value));
        cleanups.push(() => {
          void success.remove();
        });
        const failure = await PushNotifications.addListener('registrationError', () => finish());
        cleanups.push(() => {
          void failure.remove();
        });
        if (!done) await PushNotifications.register();
      } catch {
        finish();
      }
    })();
  });
  if (source !== getServerAddress())
    throw new Error('Cambió el servidor. Vuelve a iniciar sesión.');
  await api.post('/client/push-device', {
    platform: pushPlatform,
    token,
    installation_id: installationId(),
    consent: true,
  });
}
export async function disableReminders(): Promise<void> {
  // Revoke on the server first: do not report success if it could still send.
  await api.delete('/client/push-device', { data: { installation_id: installationId() } });
}
