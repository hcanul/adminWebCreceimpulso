<template>
  <q-card flat bordered class="q-pa-lg q-mt-lg">
    <h2 class="text-h6 q-mt-none">
      <q-icon name="notifications_active" class="q-mr-sm" />Notificaciones
    </h2>
    <p>
      Recibe avisos cuando tu sucursal te envíe un mensaje. El texto completo estará disponible en
      Mis mensajes.
    </p>
    <p v-if="!nativePush">Disponibles en la aplicación instalada para Android o iPhone.</p>
    <p v-else-if="available === false">
      La sucursal todavía no ha habilitado el servicio de notificaciones.
    </p>
    <p v-else-if="enabled">Notificaciones activadas para este dispositivo.</p>
    <p v-if="error" role="alert" class="text-negative">{{ error }}</p>
    <q-btn
      v-if="nativePush && available && !enabled"
      color="primary"
      label="Activar notificaciones"
      :loading="busy"
      @click="enable"
    />
    <q-btn
      v-if="enabled"
      outline
      color="primary"
      label="Desactivar notificaciones"
      :loading="busy"
      @click="disable"
    />
    <q-btn v-if="error && !busy" flat label="Reintentar consulta" @click="load" />
    <p class="text-caption q-mb-none q-mt-md">
      La entrega depende de internet y de los permisos del teléfono. Aunque no recibas un aviso,
      consulta tu bandeja de mensajes. Al cerrar sesión se desactivan los recordatorios de tu
      cuenta; puedes volver a activarlos al entrar.
    </p>
  </q-card>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import {
  nativePush,
  pushPlatform,
  pushState,
  enableReminders,
  disableReminders,
} from '../services/push.service';
import { errorMessage } from '../services/http';
const enabled = ref(false),
  available = ref<boolean | null>(null),
  busy = ref(false),
  error = ref('');
async function load() {
  if (!nativePush) return;
  busy.value = true;
  error.value = '';
  try {
    const state = await pushState();
    enabled.value = state.enabled;
    available.value = pushPlatform === 'ios' ? state.available.ios : state.available.android;
  } catch (e) {
    error.value = errorMessage(e);
  } finally {
    busy.value = false;
  }
}
async function enable() {
  busy.value = true;
  error.value = '';
  try {
    await enableReminders();
    enabled.value = true;
  } catch (e) {
    error.value = e instanceof Error && !('isAxiosError' in e) ? e.message : errorMessage(e);
  } finally {
    busy.value = false;
  }
}
async function disable() {
  busy.value = true;
  error.value = '';
  try {
    await disableReminders();
    enabled.value = false;
  } catch (e) {
    error.value = errorMessage(e);
  } finally {
    busy.value = false;
  }
}
onMounted(load);
</script>
