<template>
  <q-page class="q-pa-lg password-page">
    <img src="/crece-impulso-logo.png" alt="Crece Impulso" width="230" />
    <h1>Elige tu contraseña personal</h1>
    <p>Para proteger tu cuenta, cambia la contraseña temporal que te entregó tu sucursal antes de consultar tu crédito.</p>
    <q-form class="q-gutter-md" @submit="save">
      <q-input v-model="current" type="password" outlined label="Contraseña temporal o actual" autocomplete="current-password" :disable="busy" :rules="[(v) => !!v || 'Escribe tu contraseña actual']" />
      <q-input v-model="password" type="password" outlined label="Nueva contraseña" autocomplete="new-password" maxlength="128" :disable="busy" :rules="[(v) => (v.length >= 12 && /[a-z]/i.test(v) && /[0-9]/.test(v)) || 'Usa 12 caracteres como mínimo, letras y números', (v) => v !== current || 'Elige una contraseña diferente']" />
      <q-input v-model="confirmation" type="password" outlined label="Confirma la nueva contraseña" autocomplete="new-password" :disable="busy" :rules="[(v) => v === password || 'Las contraseñas no coinciden']" />
      <q-btn type="submit" color="primary" label="Guardar contraseña" no-caps :loading="busy" class="full-width" />
      <q-btn flat label="Cerrar sesión" no-caps :disable="busy" @click="logout" />
    </q-form>
  </q-page>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { authService } from '../services/auth.service';
import { useClientAuth } from '../stores/auth';
import { errorMessage } from '../services/http';
const current = ref(''), password = ref(''), confirmation = ref(''), busy = ref(false);
const auth = useClientAuth(), router = useRouter();
async function save() {
  if (busy.value) return;
  busy.value = true;
  try {
    await authService.changePassword(current.value, password.value, confirmation.value);
    current.value = password.value = confirmation.value = '';
    auth.clear();
    await Swal.fire({ icon: 'success', title: 'Contraseña actualizada', text: 'Inicia sesión con tu nueva contraseña.', confirmButtonColor: '#7e1838' });
    await router.replace('/login');
  } catch (e) { await Swal.fire({ icon: 'error', title: 'No se pudo cambiar', text: errorMessage(e), confirmButtonColor: '#7e1838' }); }
  finally { busy.value = false; }
}
async function logout() {
  try { await auth.logout(); await router.replace('/login'); }
  catch (e) { await Swal.fire({ icon: 'error', text: errorMessage(e) }); }
}
</script>
<style scoped>
.password-page { max-width: 560px; margin: auto; }
h1 { font-size: 28px; line-height: 1.3; font-weight: 700; }
p { line-height: 1.6; }
</style>
