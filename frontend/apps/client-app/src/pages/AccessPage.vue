<template>
  <main class="access-shell">
    <section class="brand-panel">
      <div class="brand-mark"><img src="/crece-impulso-logo.png" alt="Crece Impulso" /></div>
      <p class="eyebrow">CRECE IMPULSO</p>
      <h1>Tu tranquilidad,<br />siempre contigo.</h1>
      <p>Un espacio personal para acompañarte en cada paso.</p>
      <div class="brand-footer">
        <q-icon name="verified_user" size="22px" /> Acceso personal y protegido
      </div>
    </section>
    <section class="form-panel">
      <div class="form-content">
        <p class="eyebrow">PORTAL DE CLIENTES</p>
        <h2>{{ activating ? 'Configura tu acceso' : 'Bienvenida de nuevo' }}</h2>
        <p class="intro">
          {{
            activating
              ? 'Usa el código que recibiste después de verificar tu identidad en sucursal.'
              : 'Ingresa con tu número de cliente y tu contraseña.'
          }}
        </p>
        <section v-if="!activating" class="q-mb-md">
          <q-btn
            v-if="saved && available"
            outline
            color="primary"
            icon="fingerprint"
            class="full-width q-mb-sm"
            :label="`Entrar con ${label}`"
            :disable="busy"
            @click="biometricLogin"
          />
          <p v-if="saved && !available" class="text-caption">
            La biometría no está disponible. Puedes entrar con tu contraseña.
          </p>
          <q-btn
            v-if="saved"
            flat
            no-caps
            label="Eliminar acceso biométrico guardado"
            :disable="busy"
            @click="access.forget"
          />
          <p v-if="notice" role="status" class="text-caption">{{ notice }}</p>
        </section>
        <q-banner
          v-if="route.query.expired === 'idle'"
          rounded
          class="bg-amber-1 text-brown-9 q-mb-md"
          role="status"
          >Tu sesión terminó después de 30 minutos sin actividad. Vuelve a ingresar.</q-banner
        >
        <q-form class="q-gutter-y-lg" @submit="submit">
          <q-input
            v-if="activating"
            v-model="code"
            outlined
            label="Código de activación o recuperación"
            autocomplete="off"
            maxlength="32"
            :disable="busy"
            :rules="[
              (v) => /^[a-fA-F0-9]{32}$/.test(v.trim()) || 'Captura los 32 caracteres del código',
            ]"
          />
          <q-input
            v-else
            v-model="identifier"
            outlined
            label="Número de cliente"
            autocomplete="username"
            maxlength="80"
            :disable="busy"
            :rules="[(v) => !!v.trim() || 'Escribe tu número de cliente']"
          />
          <q-input
            v-model="password"
            outlined
            label="Contraseña"
            :type="visible ? 'text' : 'password'"
            :autocomplete="activating ? 'new-password' : 'current-password'"
            maxlength="128"
            :disable="busy"
            :rules="[
              (v) => !!v || 'Escribe tu contraseña',
              (v) =>
                !activating ||
                (v.length >= 12 && /[a-z]/i.test(v) && /[0-9]/.test(v)) ||
                'Usa al menos 12 caracteres, letras y números',
            ]"
            ><template #append
              ><q-btn
                flat
                round
                size="sm"
                :icon="visible ? 'visibility_off' : 'visibility'"
                :aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                @click="visible = !visible" /></template
          ></q-input>
          <q-input
            v-if="activating"
            v-model="confirmation"
            outlined
            label="Confirma tu contraseña"
            type="password"
            autocomplete="new-password"
            maxlength="128"
            :disable="busy"
            :rules="[(v) => v === password || 'Las contraseñas no coinciden']"
          />
          <section v-if="!activating">
            <q-checkbox
              :model-value="remember"
              :disable="busy"
              label="Recordar número de cliente"
              @update:model-value="access.toggleRemember"
            />
            <q-checkbox
              v-if="available"
              v-model="activate"
              :disable="busy"
              :label="`Activar acceso con ${label}`"
            />
            <p v-if="available" class="text-caption">
              Se activa después de validar tu contraseña. Solo úsalo en tu dispositivo personal.
            </p>
          </section>
          <q-btn
            type="submit"
            color="primary"
            class="full-width submit-button"
            :label="activating ? 'Guardar mi acceso' : 'Entrar'"
            :loading="busy"
          />
        </q-form>
        <p class="switch-link">
          <router-link :to="activating ? '/login' : '/activar'" @click="reset">{{
            activating
              ? 'Ya tengo cuenta. Iniciar sesión'
              : 'Tengo un código de activación o recuperación'
          }}</router-link>
        </p>
        <div class="q-mb-lg text-center">
          <q-btn
            outline
            color="primary"
            icon="wifi"
            label="Probar conexión"
            :loading="checking"
            @click="checkConnection"
          />
          <p v-if="connectionResult" class="q-mt-sm" role="status">{{ connectionResult }}</p>
        </div>
        <div class="text-center q-mb-lg">
          <q-btn
            flat
            no-caps
            color="primary"
            icon="settings"
            label="Configuración del dispositivo"
            to="/configuracion"
            :disable="busy || checking"
          />
        </div>
        <aside class="help-box">
          <q-icon name="support_agent" size="24px" /><span
            >Si olvidaste tu contraseña o tu código venció, acude a tu sucursal para recibir
            ayuda.</span
          >
        </aside>
      </div>
    </section>
  </main>
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useDeviceAccess } from '../composables/useDeviceAccess';
import { useRoute, useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { authService } from '../services/auth.service';
import { errorMessage } from '../services/http';
import { getServerAddress, probeServer, probeErrorMessage } from '../services/server-settings';
import { useClientAuth } from '../stores/auth';
const route = useRoute();
const router = useRouter();
const auth = useClientAuth();
const activating = computed(() => route.path === '/activar');
const code = ref('');
const access = useDeviceAccess();
const { identifier, remember, available, label, saved, activate, notice } = access;
const password = ref('');
const confirmation = ref('');
const busy = ref(false);
const visible = ref(false);
const checking = ref(false);
const connectionResult = ref('');
async function checkConnection() {
  if (checking.value) return;
  checking.value = true;
  connectionResult.value = '';
  try {
    const elapsed = await probeServer(getServerAddress());
    connectionResult.value = `Conexión correcta con Crece Impulso (${elapsed} ms). Ya puedes iniciar sesión.`;
  } catch (e) {
    connectionResult.value = probeErrorMessage(e);
  } finally {
    checking.value = false;
  }
}
function reset() {
  code.value = '';
  password.value = '';
  confirmation.value = '';
  visible.value = false;
}
watch(activating, reset);
async function submit() {
  if (busy.value) return;
  busy.value = true;
  try {
    if (activating.value) {
      await authService.activate(
        code.value.trim().toLowerCase(),
        password.value,
        confirmation.value,
      );
      reset();
      await Swal.fire({
        icon: 'success',
        title: 'Tu cuenta está lista',
        text: 'Ahora puedes entrar con tu número de cliente y contraseña.',
        confirmButtonColor: '#7e1838',
      });
      await router.replace('/login');
    } else {
      await auth.login(identifier.value.trim().toUpperCase(), password.value);
      const warning = await access.afterPasswordLogin(
        password.value,
        !!auth.profile?.must_change_password,
      );
      reset();
      if (warning) await Swal.fire({ icon: 'info', text: warning });
      await router.replace('/');
    }
  } catch (e) {
    await Swal.fire({
      icon: 'error',
      title: 'Revisa tu acceso',
      text: errorMessage(e),
      confirmButtonColor: '#7e1838',
    });
  } finally {
    busy.value = false;
  }
}
async function biometricLogin() {
  if (busy.value) return;
  busy.value = true;
  try {
    await access.loginWithBiometry(
      (id, secret) => auth.login(id, secret),
      () => !!auth.profile?.must_change_password,
    );
    password.value = '';
    await router.replace('/');
  } catch (error) {
    const isNetwork = error && typeof error === 'object' && 'isAxiosError' in error;
    await Swal.fire({
      icon: 'info',
      title: 'Usa tu contraseña',
      text: isNetwork
        ? errorMessage(error)
        : 'No se completó la verificación biométrica. Puedes intentarlo de nuevo o ingresar con tu contraseña.',
      confirmButtonColor: '#7e1838',
    });
  } finally {
    busy.value = false;
  }
}
</script>
