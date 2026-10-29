<template>
  <q-card flat bordered class="q-pa-lg q-mt-lg">
    <h2 class="text-h6 q-mt-none"><q-icon name="notifications_active" class="q-mr-sm" />Recordatorios de pago</h2>
    <p>Recibe un aviso el día anterior al vencimiento de una cuota pendiente. Las notificaciones no muestran tu nombre ni importes.</p>
    <p v-if="!nativePush">Disponibles en la aplicación instalada para Android o iPhone.</p>
    <p v-else-if="available === false">La sucursal todavía no ha habilitado el servicio de notificaciones.</p>
    <p v-else-if="enabled">Recordatorios activados para esta sesión y dispositivo.</p>
    <p v-if="error" role="alert" class="text-negative">{{ error }}</p>
    <q-btn v-if="nativePush && available && !enabled" color="primary" label="Activar recordatorios" :loading="busy" @click="enable" />
    <q-btn v-if="enabled" outline color="primary" label="Desactivar recordatorios" :loading="busy" @click="disable" />
    <q-btn v-if="error && !busy" flat label="Reintentar consulta" @click="load" />
    <p class="text-caption q-mb-none q-mt-md">La entrega depende de internet y de los permisos del teléfono. Aunque no recibas un aviso, consulta tus vencimientos en el estado de cuenta. Al cerrar sesión se desactiva el registro de esa sesión.</p>
  </q-card>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { nativePush, pushPlatform, pushState, enableReminders, disableReminders } from '../services/push.service';
import { errorMessage } from '../services/http';
const enabled = ref(false), available = ref<boolean | null>(null), busy = ref(false), error = ref('');
async function load() {
  if (!nativePush) return;
  busy.value = true; error.value = '';
  try { const state = await pushState(); enabled.value = state.enabled; available.value = pushPlatform === 'ios' ? state.available.ios : state.available.android; }
  catch(e) { error.value = errorMessage(e); }
  finally { busy.value = false; }
}
async function enable() {
  busy.value = true; error.value = '';
  try { await enableReminders(); enabled.value = true; }
  catch(e) { error.value = e instanceof Error && !('isAxiosError' in e) ? e.message : errorMessage(e); }
  finally { busy.value = false; }
}
async function disable() {
  busy.value = true; error.value = '';
  try { await disableReminders(); enabled.value = false; }
  catch(e) { error.value = errorMessage(e); }
  finally { busy.value = false; }
}
onMounted(load);
</script>
