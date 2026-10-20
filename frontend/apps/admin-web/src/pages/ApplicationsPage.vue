<template>
  <q-page class="intake-page"
    ><div class="intake-heading">
      <div>
        <p class="eyebrow">CAPTURA Y SEGUIMIENTO</p>
        <h1>Clientes y expedientes</h1>
        <p>Verifica la CURP antes de abrir o modificar un expediente.</p>
      </div>
      <q-btn
        color="primary"
        icon="person_search"
        no-caps
        label="Verificar CURP"
        to="/clientes/captura"
      />
    </div>
    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-md">{{ error }}</q-banner
    ><q-card flat bordered class="intake-card"
      ><q-linear-progress v-if="loading" indeterminate /><q-list separator
        ><q-item
          v-for="application in store.applications"
          :key="application.id"
          clickable
          @click="open(application)"
          ><q-item-section
            ><q-item-label
              >{{ application.client?.first_name }}
              {{ application.client?.last_name }}</q-item-label
            ><q-item-label caption
              >{{ application.team?.name }} · {{ application.client?.curp }} ·
              {{ application.client?.folio }}</q-item-label
            ><q-item-label caption class="uuid-text">{{
              application.id
            }}</q-item-label></q-item-section
          ><q-item-section side
            ><q-btn
              flat
              no-caps
              label="Documentos"
              :to="`/documentos/file/${application.id}`"
              @click.stop
            /><q-badge :color="application.status === 'borrador' ? 'grey-7' : 'primary'">{{
              application.status === 'borrador' ? 'Borrador' : 'En revisión'
            }}</q-badge></q-item-section
          ></q-item
        ></q-list
      ><q-card-section
        v-if="!loading && !store.applications.length"
        class="q-pa-xl text-center text-grey-7"
        >Todavía no hay expedientes. Comienza con un grupo y la CURP de su
        integrante.</q-card-section
      ><q-card-actions align="center"
        ><q-pagination
          v-model="page"
          :max="store.applicationPages"
          :max-pages="6"
          @update:model-value="load" /></q-card-actions></q-card
  ></q-page>
</template>
<script setup lang="ts">
import { useAlertFeedback } from '../composables/useAlertFeedback';
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useIntakeStore } from '../stores/intake.store';
import { intakeError, type Application } from '../services/intake.service';
const store = useIntakeStore(),
  router = useRouter(),
  loading = ref(false),
  error = ref(''),
  page = ref(1);
async function load() {
  loading.value = true;
  error.value = '';
  try {
    await store.loadApplications(page.value);
  } catch (e) {
    error.value = intakeError(e).message;
  } finally {
    loading.value = false;
  }
}
async function open(a: Application) {
  store.pendingCurp = a.client?.curp ?? '';
  await router.push({ path: '/clientes/captura', query: { grupo: a.team_id } });
}
onMounted(load);

useAlertFeedback(error);
</script>
