<template>
  <main class="server-settings q-pa-lg">
    <q-btn flat no-caps icon="arrow_back" label="Volver al acceso" to="/login" />
    <q-card flat bordered class="q-mt-lg">
      <q-card-section class="settings-heading">
        <q-icon name="dns" size="32px" />
        <h1>Configuración del dispositivo</h1>
        <p>Conexión al servidor de Crece Impulso</p>
      </q-card-section>
      <q-card-section>
        <p>Usa la dirección que te indique el administrador de tu sucursal. Este ajuste se guarda únicamente en este dispositivo y no otorga permisos administrativos.</p>
        <q-banner rounded class="bg-grey-2 q-mb-lg">Al guardar, se cerrará la sesión. Tus credenciales se enviarán al servidor elegido cuando vuelvas a iniciar sesión.</q-banner>
        <p class="text-caption">Servidor guardado: <strong>{{ savedAddress }}</strong></p>
        <q-form @submit="save">
          <q-input v-model="address" outlined stack-label label="IP o dominio del servidor" placeholder="192.168.100.118:8000 o https://mi-dominio.com" autocomplete="off" autocapitalize="off" :spellcheck="false" :disable="checking || saving" :error="!!validationError" :error-message="validationError" />
          <p class="text-caption">Puedes guardar aunque el servidor esté apagado. Para la Mac escribe IP y puerto: 192.168.1.67:8000. Agregamos /api automáticamente si falta. Los dominios públicos necesitan HTTPS; HTTP local está disponible en las versiones de pruebas.</p>
          <q-btn outline no-caps color="primary" icon="wifi" label="Probar conexión" :loading="checking" @click="test" />
          <p v-if="result" role="status" :class="verified ? 'text-positive' : 'text-negative'">{{ result }}</p>
          <div class="q-mt-lg row q-gutter-sm">
            <q-btn color="primary" no-caps type="submit" label="Guardar servidor" :disable="checking || saving" />
            <q-btn flat no-caps label="Usar dirección inicial" :disable="checking || saving" @click="address = defaultServer" />
          </div>
        </q-form>
      </q-card-section>
    </q-card>
  </main>
</template>
<script setup lang="ts">
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import { normalizeServerAddress } from '../services/server-address';
import { defaultServer, getServerAddress, probeServer, probeErrorMessage, saveServerAddress } from '../services/server-settings';
const savedAddress = getServerAddress();
const address = ref(savedAddress);
const checking = ref(false);
const saving = ref(false);
const verified = ref(false);
const validationError = ref('');
const result = ref('');
watch(address, () => { verified.value = false; result.value = ''; validationError.value = ''; });
async function test() {
  if (checking.value) return;
  validationError.value = '';
  let normalized: string;
  try { normalized = normalizeServerAddress(address.value); }
  catch (e) { validationError.value = (e as Error).message; return; }
  checking.value = true;
  verified.value = false;
  try {
    const elapsed = await probeServer(normalized);
    verified.value = true;
    result.value = `Conexión correcta (${elapsed} ms). Dirección: ${normalized}`;
  } catch (error) {
    result.value = probeErrorMessage(error);
  } finally { checking.value = false; }
}
async function save() {
  if (checking.value || saving.value) return;
  let normalized: string;
  try { normalized = normalizeServerAddress(address.value); }
  catch (error) { validationError.value = (error as Error).message; return; }
  const confirmation = await Swal.fire({ icon: 'warning', title: '¿Usar este servidor?', text: `${normalized}. Comprueba esta dirección con tu administrador antes de ingresar tus datos. Se cerrará tu sesión actual.`, showCancelButton: true, confirmButtonText: 'Guardar y volver al acceso', cancelButtonText: 'Cancelar', confirmButtonColor: '#7e1838' });
  if (!confirmation.isConfirmed) return;
  saving.value = true;
  try { saveServerAddress(normalized); }
  catch { await Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: 'El dispositivo no permitió guardar la configuración. Inténtalo de nuevo.' }); }
  finally { saving.value = false; }
}
</script>
<style scoped>
.server-settings { max-width: 680px; margin: auto; padding-top: max(24px, env(safe-area-inset-top)); padding-bottom: max(24px, env(safe-area-inset-bottom)); }
.settings-heading { background: #7e1838; color: white; }
h1 { font-size: 24px; line-height: 1.3; font-weight: 700; margin: 14px 0 8px; }
p { overflow-wrap: anywhere; }
</style>
