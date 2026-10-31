<template>
  <main class="password-screen">
    <q-card flat bordered class="password-card">
      <q-card-section>
        <q-icon name="lock_reset" color="primary" size="40px" />
        <h1>Renueva tu contraseña</h1>
        <p class="account-label">
          Cuenta: <strong>{{ auth.user?.email }}</strong>
        </p>
        <p>
          Por seguridad, la contraseña del personal vence cada 15 días. Usa una distinta de la
          actual y de la anterior.
        </p>
        <q-form class="q-gutter-y-md" @submit="save">
          <input
            :value="auth.user?.email"
            type="text"
            name="username"
            autocomplete="username"
            class="sr-only"
            tabindex="-1"
            aria-hidden="true"
          />
          <q-input
            v-model="current"
            outlined
            stack-label
            :type="showPasswords ? 'text' : 'password'"
            name="current_password"
            label="Contraseña actual"
            hint="La misma con la que ingresas a esta cuenta."
            @update:model-value="error = ''"
            autocomplete="current-password"
            :disable="busy"
            :rules="[(v: string) => !!v || 'Escribe la contraseña actual']"
          />
          <q-input
            v-model="password"
            outlined
            stack-label
            :type="showPasswords ? 'text' : 'password'"
            name="new_password"
            label="Nueva contraseña"
            autocomplete="new-password"
            :disable="busy"
            :rules="[
              (v: string) =>
                (v.length >= 12 && /[a-zA-Z]/.test(v) && /[0-9]/.test(v)) ||
                'Usa al menos 12 caracteres con letras y números',
            ]"
          />
          <q-input
            v-model="confirmation"
            outlined
            stack-label
            :type="showPasswords ? 'text' : 'password'"
            name="password_confirmation"
            label="Confirma la nueva contraseña"
            autocomplete="new-password"
            :disable="busy"
            :rules="[(v: string) => v === password || 'Las contraseñas no coinciden']"
          />
          <q-checkbox v-model="showPasswords" label="Mostrar contraseñas" :disable="busy" />
          <p v-if="error" role="alert" class="text-negative">{{ error }}</p>
          <q-btn
            type="submit"
            color="primary"
            no-caps
            label="Guardar y continuar"
            class="full-width"
            :loading="busy"
          />
          <q-btn
            flat
            no-caps
            label="Salir e ingresar con otra cuenta"
            class="full-width"
            :disable="busy"
            @click="logout"
          />
        </q-form>
      </q-card-section>
    </q-card>
  </main>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '../../stores/auth.store';
const auth = useAuthStore(),
  router = useRouter();
const showPasswords = ref(false);
const current = ref(''),
  password = ref(''),
  confirmation = ref(''),
  error = ref(''),
  busy = ref(false);
function message(e: unknown) {
  if (axios.isAxiosError(e)) {
    if (e.response?.status === 429)
      return 'Demasiados intentos. Espera un minuto y vuelve a intentar.';
    if (e.response?.status === 401)
      return 'Tu sesión terminó. Vuelve a ingresar con tu correo y contraseña.';
    const body = e.response?.data as { errors?: Record<string, string[]> } | undefined;
    return (
      Object.values(body?.errors || {})
        .flat()
        .join(' ') || 'No se pudo guardar. Revisa tu conexión e inténtalo de nuevo.'
    );
  }
  return 'No se pudo completar la operación.';
}
async function save() {
  if (busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await auth.changePassword(current.value, password.value, confirmation.value);
    current.value = '';
    password.value = '';
    confirmation.value = '';
    await router.replace('/');
  } catch (e) {
    error.value = message(e);
    showPasswords.value = false;
  } finally {
    busy.value = false;
  }
}
async function logout() {
  busy.value = true;
  try {
    await auth.logout();
    await router.replace('/login');
  } catch (e) {
    error.value = message(e);
    showPasswords.value = false;
  } finally {
    busy.value = false;
  }
}
</script>
<style scoped>
.account-label {
  overflow-wrap: anywhere;
  color: #555866;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip-path: inset(50%);
  border: 0;
}
.password-screen {
  min-height: 100dvh;
  display: grid;
  place-items: center;
  padding: max(24px, env(safe-area-inset-top)) 20px;
  background: #f4f4f8;
}
.password-card {
  width: min(100%, 480px);
  border-radius: 20px;
  padding: 16px;
}
h1 {
  font-size: 26px;
  line-height: 1.2;
  margin: 16px 0;
  font-weight: 700;
}
p {
  line-height: 1.5;
}
</style>
