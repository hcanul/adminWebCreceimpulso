<template>
  <q-page class="page-shell"
    ><p class="eyebrow">CAPTURA Y SEGUIMIENTO</p>
    <h1>Expedientes de clientes</h1>
    <p>
      Consulta los expedientes de los grupos autorizados para tu cuenta. Para abrir uno, primero se
      verificará su CURP.
    </p>
    <q-btn color="primary" no-caps label="Capturar cliente" to="/clientes/capturar" />
    <q-linear-progress v-if="loading" indeterminate class="q-mt-md" />
    <q-banner v-if="error" class="bg-red-1 q-my-md"
      >{{ error }}<template #action><q-btn flat label="Reintentar" @click="load" /></template
    ></q-banner>
    <q-card v-for="file in files" :key="file.id" flat bordered class="q-my-md"
      ><q-card-section>
        <q-chip color="grey-2">{{ statusLabel(file.status) }}</q-chip>
        <h2>{{ file.client?.first_name }} {{ file.client?.last_name }}</h2>
        <p>{{ file.client?.folio }} · {{ file.team?.name }}</p>
        <q-btn
          v-if="file.client?.curp"
          flat
          color="primary"
          no-caps
          label="Verificar CURP y abrir expediente"
          @click="open(file)"
        /> </q-card-section
    ></q-card>
    <p v-if="!loading && !error && !files.length">No hay expedientes registrados en tus grupos.</p>
    <q-pagination
      v-if="pages > 1"
      v-model="page"
      :max="pages"
      :max-pages="5"
      :disable="loading"
      @update:model-value="load"
    />
  </q-page>
</template>
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { intakeService, intakeError, type Application } from '../services/intake.service';
import { useIntakeStore } from '../stores/intake.store';
const router = useRouter(),
  store = useIntakeStore(),
  files = ref<Application[]>([]),
  page = ref(1),
  pages = ref(1),
  loading = ref(false),
  error = ref('');
const statusLabel = (status: string) =>
  ({
    borrador: 'Borrador',
    en_revision: 'En revisión',
    aprobado: 'Aprobado',
    rechazado: 'Rechazado',
  })[status] || status;
let generation = 0;
async function load() {
  const current = ++generation;
  loading.value = true;
  error.value = '';
  files.value = [];
  try {
    const result = await intakeService.applications(page.value);
    if (current === generation) {
      files.value = result.data;
      pages.value = result.last_page;
    }
  } catch (e) {
    if (current === generation) error.value = intakeError(e).message;
  } finally {
    if (current === generation) loading.value = false;
  }
}
async function open(file: Application) {
  store.pendingCurp = file.client?.curp || '';
  await router.push({ path: '/clientes/capturar', query: { grupo: file.team_id } });
}
onMounted(load);
onUnmounted(() => {
  generation++;
});
</script>
