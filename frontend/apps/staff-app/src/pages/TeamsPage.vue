<template>
  <q-page class="page-shell"
    ><p class="eyebrow">TRABAJO DE CAMPO</p>
    <h1>Grupos</h1>
    <q-btn
      class="q-mb-md"
      color="primary"
      icon="add"
      no-caps
      label="Crear grupo"
      @click="creating = true" />
    <TeamEditor v-model="creating" @saved="created" />
    <p>El servidor muestra únicamente los grupos autorizados para tu cuenta.</p>
    <q-form @submit="search"
      ><q-input
        v-model="query"
        outlined
        stack-label
        label="Buscar grupo por nombre"
        maxlength="100"
        clearable
        ><template #append
          ><q-btn
            flat
            round
            icon="search"
            aria-label="Buscar grupos"
            @click="search" /></template></q-input
    ></q-form>
    <p class="text-caption">{{ total }} grupos encontrados</p>
    <q-linear-progress v-if="loading" indeterminate color="primary" /><q-banner
      v-if="error"
      class="bg-red-1 q-my-md"
      >{{ error }}<template #action><q-btn flat label="Reintentar" @click="load" /></template
    ></q-banner>
    <div v-if="!loading && !error && !teams.length" class="empty-state">
      <q-icon name="group_off" size="48px" />
      <h2>No hay grupos para mostrar</h2>
      <p>Prueba otro nombre o consulta la asignación con tu sucursal.</p>
    </div>
    <q-card v-for="team in teams" :key="team.id" flat bordered class="q-my-md"
      ><q-card-section
        ><div class="row items-start justify-between">
          <h2 class="q-ma-none">{{ team.name }}</h2>
          <q-badge :color="team.is_active ? 'positive' : 'grey'">{{
            team.is_active ? 'Activo' : 'Inactivo'
          }}</q-badge>
        </div>
        <p>
          {{ team.branch?.name || 'Sin sucursal' }} · {{ team.memberships_count ?? 0 }} integrantes
        </p>
        <p class="text-caption">Promotor: {{ team.promoter?.name || 'Sin asignar' }}</p>
        <q-btn
          flat
          no-caps
          color="primary"
          label="Ver integrantes"
          :to="`/grupos/${team.id}`" /></q-card-section
    ></q-card>
    <q-pagination
      v-if="pages > 1"
      v-model="page"
      :max="pages"
      :max-pages="5"
      boundary-numbers
      :disable="loading"
      @update:model-value="load"
  /></q-page>
</template>
<script setup lang="ts">
import TeamEditor from '../components/TeamEditor.vue';
import { useRouter } from 'vue-router';
import { ref, onMounted, onUnmounted } from 'vue';
import { teamsService, type Team } from '../services/teams.service';
import { errorMessage } from '../services/http';
const router = useRouter(),
  creating = ref(false);
async function created(team: Team) {
  await router.push(`/grupos/${team.id}`);
}
const query = ref<string | null>(''),
  teams = ref<Team[]>([]),
  page = ref(1),
  pages = ref(1),
  total = ref(0),
  loading = ref(false),
  error = ref('');
let controller: AbortController | undefined;
async function load() {
  controller?.abort();
  const current = new AbortController();
  controller = current;
  loading.value = true;
  error.value = '';
  teams.value = [];
  try {
    const result = await teamsService.list(query.value?.trim() || '', page.value, current.signal);
    if (current.signal.aborted) return;
    teams.value = result.data;
    pages.value = result.last_page;
    total.value = result.total;
  } catch (e) {
    if (!current.signal.aborted) {
      error.value = errorMessage(e);
      total.value = 0;
    }
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}
function search() {
  page.value = 1;
  void load();
}
onMounted(load);
onUnmounted(() => controller?.abort());
</script>
