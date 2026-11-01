import { Capacitor } from '@capacitor/core';
import { SecureStorage, KeychainAccess } from '@aparajita/capacitor-secure-storage';
import { getServerAddress } from './server-settings';
export const sessionKey = 'crece.client.session';
const native = Capacitor.isNativePlatform();
let pending: Promise<unknown> = Promise.resolve();
function serialize<T>(work: () => Promise<T>): Promise<T> {
  const next = pending.then(work);
  pending = next.catch(() => undefined);
  return next;
}
const key = () => `${sessionKey}:${encodeURIComponent(getServerAddress())}`;
export function readSession(): Promise<string | null> {
  return serialize(async () => {
    if (!native) return sessionStorage.getItem(sessionKey);
    sessionStorage.removeItem(sessionKey);
    const value = await SecureStorage.get(key(), false, false);
    if (!value) return null;
    const data = value as { token?: unknown; expiresAt?: unknown };
    if (
      typeof data.token !== 'string' ||
      typeof data.expiresAt !== 'number' ||
      data.expiresAt <= Date.now()
    ) {
      await SecureStorage.remove(key(), false);
      return null;
    }
    return data.token;
  });
}
export function writeSession(token: string): Promise<void> {
  return serialize(async () => {
    if (native) {
      await SecureStorage.set(
        key(),
        { token, expiresAt: Date.now() + 8 * 60 * 60 * 1000 },
        false,
        false,
        KeychainAccess.whenUnlockedThisDeviceOnly,
      );
      sessionStorage.removeItem(sessionKey);
    } else sessionStorage.setItem(sessionKey, token);
  });
}
export function removeSession(expected?: string): Promise<boolean> {
  return serialize(async () => {
    if (native) {
      const data = (await SecureStorage.get(key(), false, false)) as { token?: string } | null;
      if (expected && data?.token !== expected) return false;
      await SecureStorage.remove(key(), false);
    } else {
      if (expected && sessionStorage.getItem(sessionKey) !== expected) return false;
    }
    sessionStorage.removeItem(sessionKey);
    return true;
  });
}
