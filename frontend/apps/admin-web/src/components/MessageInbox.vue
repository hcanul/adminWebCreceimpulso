<template>
  <section class="q-pa-md inbox" aria-label="Bandeja de mensajes">
    <div class="row items-center justify-between q-mb-md">
      <h1 class="text-h5 q-my-sm">Mis mensajes</h1>
      <q-btn flat icon="refresh" label="Actualizar" :loading="loading" @click="reload" />
    </div>
    <p>
      Los mensajes permanecen aquí aunque el teléfono no muestre la notificación. Consulta el estado
      de cuenta para conocer tus saldos actuales.
    </p>
    <q-banner v-if="error" class="bg-red-1 q-mb-md">{{ error }}</q-banner>
    <q-card v-for="m in messages" :key="m.id" flat bordered class="q-pa-md q-mb-md">
      <div class="row items-center q-gutter-sm">
        <q-icon :name="m.read_at ? 'drafts' : 'markunread'" color="primary" />
        <h2 class="text-h6 q-my-none">{{ m.title }}</h2>
      </div>
      <p class="body q-mt-md">{{ m.body }}</p>
      <small>{{ date(m.created_at) }}</small>
      <q-btn
        v-if="!m.read_at"
        flat
        color="primary"
        label="Marcar leído"
        :disable="reading === m.id"
        @click="read(m)"
      />
    </q-card>
    <p v-if="!messages.length && !loading && !error">Aún no tienes mensajes.</p>
    <q-btn v-if="more" outline label="Cargar mensajes" :loading="loading" @click="load" />
  </section>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { api } from '../services/http';
interface Message {
  id: string;
  title: string;
  body: string;
  created_at: string;
  read_at: string | null;
}
const props = withDefaults(defineProps<{ endpoint?: string }>(), { endpoint: '/messages' });
const messages = ref<Message[]>([]),
  page = ref(1),
  more = ref(true),
  loading = ref(false),
  error = ref(''),
  reading = ref('');
const date = (d: string) => new Date(d.replace(' ', 'T') + 'Z').toLocaleString('es-MX');
async function load() {
  if (loading.value) return;
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get<{ data: Message[]; has_more: boolean }>(props.endpoint, {
      params: { page: page.value },
    });
    messages.value.push(...data.data);
    more.value = data.has_more;
    page.value++;
  } catch {
    error.value = 'No fue posible cargar los mensajes. Verifica tu conexión y reintenta.';
  } finally {
    loading.value = false;
  }
}
async function reload() {
  if (loading.value) return;
  messages.value = [];
  page.value = 1;
  more.value = true;
  await load();
}
async function read(m: Message) {
  reading.value = m.id;
  try {
    await api.post(`${props.endpoint}/${m.id}/read`);
    m.read_at = new Date().toISOString();
  } catch {
    error.value = 'No se pudo confirmar la lectura.';
  } finally {
    reading.value = '';
  }
}
onMounted(load);
</script>
<style scoped>
.inbox {
  max-width: 850px;
  margin: auto;
}
.body {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
</style>
