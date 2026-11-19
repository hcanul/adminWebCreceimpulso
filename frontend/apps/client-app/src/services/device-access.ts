import { Capacitor } from '@capacitor/core';
import { SecureStorage, KeychainAccess } from '@aparajita/capacitor-secure-storage';
import {
  BiometricAuth,
  BiometryType,
  AndroidBiometryStrength,
} from '@aparajita/capacitor-biometric-auth';
import { getServerAddress } from './server-settings';

const namespace = 'crece.client.device-access';
const key = (kind: string) => `${namespace}:${encodeURIComponent(getServerAddress())}:${kind}`;
const native = () => Capacitor.isNativePlatform();
export interface DeviceCredential {
  identifier: string;
  password: string;
  server: string;
}
export function rememberedIdentifier(): string {
  try {
    return localStorage.getItem(key('identifier')) || '';
  } catch {
    return '';
  }
}
export function rememberIdentifier(identifier: string, enabled: boolean): void {
  if (enabled) localStorage.setItem(key('identifier'), identifier);
  else localStorage.removeItem(key('identifier'));
}
export async function biometricAvailability() {
  if (!native()) return { available: false, label: 'biometría' };
  const info = await BiometricAuth.checkBiometry();
  const label =
    info.biometryType === BiometryType.faceId
      ? 'Face ID'
      : info.biometryType === BiometryType.touchId
        ? 'Touch ID'
        : info.biometryType === BiometryType.faceAuthentication
          ? 'reconocimiento facial'
          : 'huella';
  return { available: info.strongBiometryIsAvailable, label };
}
export function hasBiometricAccess(): boolean {
  return native() && localStorage.getItem(key('biometric')) === 'enabled';
}
async function identifierHash(identifier: string): Promise<string> {
  const hash = await crypto.subtle.digest(
    'SHA-256',
    new TextEncoder().encode(identifier.trim().toLowerCase()),
  );
  return Array.from(new Uint8Array(hash), (byte) => byte.toString(16).padStart(2, '0')).join('');
}
export async function biometricAccountMatches(identifier: string): Promise<boolean> {
  return localStorage.getItem(key('owner')) === (await identifierHash(identifier));
}
export async function removeBiometricAccess(): Promise<void> {
  localStorage.removeItem(key('biometric'));
  localStorage.removeItem(key('owner'));
  if (native()) await SecureStorage.remove(key('credential'), false);
}
async function authenticate(): Promise<void> {
  const info = await biometricAvailability();
  if (!info.available) throw new Error('La biometría no está disponible. Entra con tu contraseña.');
  await BiometricAuth.authenticate({
    reason: 'Acceder a Crece Impulso',
    cancelTitle: 'Usar contraseña',
    allowDeviceCredential: false,
    iosFallbackTitle: '',
    androidTitle: 'Crece Impulso',
    androidBiometryStrength: AndroidBiometryStrength.strong,
  });
}
export async function enableBiometricAccess(identifier: string, password: string): Promise<void> {
  // Call only after the API has accepted this password. No web credential persistence.
  await authenticate();
  await SecureStorage.set(
    key('credential'),
    { identifier, password, server: getServerAddress() },
    false,
    false,
    KeychainAccess.whenUnlockedThisDeviceOnly,
  );
  try {
    localStorage.setItem(key('owner'), await identifierHash(identifier));
    localStorage.setItem(key('biometric'), 'enabled');
  } catch (error) {
    localStorage.removeItem(key('biometric'));
    localStorage.removeItem(key('owner'));
    await SecureStorage.remove(key('credential'), false);
    throw error;
  }
}
export async function biometricCredential(): Promise<DeviceCredential> {
  if (!hasBiometricAccess())
    throw new Error('Activa el acceso entrando primero con tu contraseña.');
  const server = getServerAddress();
  await authenticate();
  if (server !== getServerAddress() || !hasBiometricAccess())
    throw new Error('La configuración cambió. Vuelve a ingresar.');
  const value = (await SecureStorage.get(
    key('credential'),
    false,
    false,
  )) as DeviceCredential | null;
  if (
    !value ||
    typeof value.identifier !== 'string' ||
    typeof value.password !== 'string' ||
    value.server !== server
  ) {
    await removeBiometricAccess();
    throw new Error('El acceso guardado ya no está disponible. Entra con tu contraseña.');
  }
  return value;
}
