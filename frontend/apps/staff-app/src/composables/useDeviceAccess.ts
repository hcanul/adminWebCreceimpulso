import { onMounted, ref } from 'vue';
import axios from 'axios';
import {
  biometricAccountMatches,
  biometricAvailability,
  rememberedIdentifier,
  rememberIdentifier,
  hasBiometricAccess,
  removeBiometricAccess,
  enableBiometricAccess,
  biometricCredential,
} from '../services/device-access';
export function useDeviceAccess() {
  const identifier = ref(rememberedIdentifier());
  const remember = ref(!!identifier.value);
  const available = ref(false),
    label = ref('biometría'),
    saved = ref(hasBiometricAccess());
  const activate = ref(false),
    notice = ref('');
  onMounted(async () => {
    try {
      const info = await biometricAvailability();
      available.value = info.available;
      label.value = info.label;
    } catch {
      available.value = false;
    }
  });
  function toggleRemember(value: boolean) {
    remember.value = value;
    try {
      rememberIdentifier(identifier.value.trim(), value);
    } catch {
      notice.value = 'No se pudo guardar la preferencia en este dispositivo.';
    }
  }
  async function forget() {
    try {
      await removeBiometricAccess();
      saved.value = false;
      activate.value = false;
      notice.value = 'Acceso biométrico eliminado.';
    } catch {
      notice.value = 'No se pudo eliminar el acceso. Inténtalo de nuevo.';
    }
  }
  async function afterPasswordLogin(password: string, mustChange: boolean) {
    try {
      rememberIdentifier(identifier.value.trim(), remember.value);
      // Keep the selected method for this account; never retain a different account.
      if (saved.value && (mustChange || !(await biometricAccountMatches(identifier.value)))) {
        await removeBiometricAccess();
        saved.value = false;
      }
      if (activate.value && !mustChange) {
        await enableBiometricAccess(identifier.value.trim(), password);
        saved.value = true;
      } else if (activate.value && mustChange) {
        return 'Primero cambia tu contraseña; después podrás activar el acceso biométrico al ingresar.';
      }
    } catch {
      return 'Ingresaste correctamente, pero no se activó el acceso biométrico o el recordatorio. Puedes volver a intentarlo al iniciar sesión.';
    }
    return '';
  }
  async function loginWithBiometry(
    login: (identifier: string, password: string) => Promise<void>,
    mustChange: () => boolean,
  ) {
    const credential = await biometricCredential();
    try {
      await login(credential.identifier, credential.password);
      if (mustChange()) {
        await removeBiometricAccess();
        saved.value = false;
      }
    } catch (error) {
      if (axios.isAxiosError(error) && [401, 403, 422].includes(error.response?.status || 0)) {
        await removeBiometricAccess();
        saved.value = false;
      }
      throw error;
    }
  }
  return {
    identifier,
    remember,
    available,
    label,
    saved,
    activate,
    notice,
    toggleRemember,
    forget,
    afterPasswordLogin,
    loginWithBiometry,
  };
}
