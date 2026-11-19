<template>
  <q-page class="q-pa-md history-page"
    ><h1>Observaciones de cobranza</h1>
    <p>
      Historial por sucursal, grupo, cliente y autor. Cada nota conserva el crédito al que
      pertenece.
    </p>
    <q-form @submit="apply" class="filters q-mb-lg"
      ><q-input
        v-model="search"
        outlined
        stack-label
        label="Buscar grupo, folio o nombre del cliente"
        maxlength="100" /><q-input
        v-model="author"
        outlined
        stack-label
        label="Nombre del autor"
        maxlength="100" /><FilterSelect
        v-model="branch"
        outlined
        label="Todas las sucursales permitidas"
        clearable
        emit-value
        map-options
        :options="branches" /><q-input
        v-model="from"
        outlined
        stack-label
        type="date"
        label="Desde" /><q-input
        v-model="to"
        outlined
        stack-label
        type="date"
        label="Hasta" /><q-btn color="primary" label="Consultar" type="submit" :loading="loading"
    /></q-form>
    <q-linear-progress v-if="loading" indeterminate /><q-banner
      v-if="error"
      role="alert"
      class="bg-red-1 text-negative"
      >{{ error }}<template #action><q-btn flat label="Reintentar" @click="load" /></template
    ></q-banner>
    <template v-if="data"
      ><p>
        {{ data.entries.total }} observaciones · Página {{ page }} de {{ data.entries.last_page }}
      </p>
      <q-banner v-if="!data.entries.total" class="bg-grey-2"
        >No hay observaciones para estos filtros.</q-banner
      >
      <q-card v-for="note in data.entries.data" :key="note.id" flat bordered class="q-mb-md"
        ><q-card-section
          ><div class="row justify-between items-start">
            <div>
              <strong>{{ note.first_name }} {{ note.last_name }}</strong>
              <p>
                {{ note.client_folio }} · {{ note.group }} · {{ note.credit_folio }}<br />{{
                  note.branch
                }}
              </p>
            </div>
            <q-btn
              flat
              color="primary"
              no-caps
              label="Abrir seguimiento"
              :to="`/creditos/${note.loan_application_id}/seguimiento`"
            />
          </div>
          <p class="observation">{{ note.observation }}</p>
          <span class="text-grey-8"
            >{{ note.actor_name }} · {{ date(note.created_at) }} (Cancún)</span
          ></q-card-section
        ></q-card
      >
      <div class="row justify-end">
        <q-btn flat label="Anterior" :disable="page <= 1" @click="changePage(-1)" /><q-btn
          flat
          label="Siguiente"
          :disable="page >= data.entries.last_page"
          @click="changePage(1)"
        /></div
    ></template>
  </q-page>
</template>
<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import FilterSelect from '../components/FilterSelect.vue';
import { useFollowupHistoryStore } from '../stores/followup-history.store';
const store = useFollowupHistoryStore(),
  route = useRoute(),
  router = useRouter();
const { data, loading, error } = storeToRefs(store);
const search = ref(''),
  author = ref(''),
  from = ref(''),
  to = ref(''),
  branch = ref<string | null>(null);
const branches = ref<{ label: string; value: string }[]>([]);
const page = computed(() => Math.max(1, Math.floor(Number(route.query.page) || 1)));
const date = (value: string) =>
  new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'America/Cancun',
  }).format(new Date(value));
function load() {
  return store.load({
    search: search.value,
    author: author.value,
    from: from.value || undefined,
    to: to.value || undefined,
    branch_id: branch.value || undefined,
    page: page.value,
  });
}
function apply() {
  const target = {
    query: {
      search: search.value || undefined,
      author: author.value || undefined,
      from: from.value || undefined,
      to: to.value || undefined,
      branch: branch.value || undefined,
      page: '1',
    },
  };
  if (router.resolve(target).fullPath === route.fullPath) void load();
  else void router.replace(target);
}
function changePage(delta: number) {
  void router.replace({ query: { ...route.query, page: String(page.value + delta) } });
}
watch(data, (result) => {
  if (result) branches.value = result.branches.map((b) => ({ label: b.name, value: b.id }));
});
watch(
  () => route.fullPath,
  () => {
    const text = (name: string) =>
      typeof route.query[name] === 'string' ? String(route.query[name]) : '';
    search.value = text('search');
    author.value = text('author');
    from.value = text('from');
    to.value = text('to');
    branch.value = text('branch') || null;
    void load();
  },
  { immediate: true },
);
onBeforeUnmount(() => store.clear());
</script>
<style scoped>
.history-page {
  max-width: 1400px;
  margin: auto;
}
h1 {
  font-size: 1.8rem;
  line-height: 1.3;
  font-weight: 700;
  margin: 0 0 12px;
}
.filters {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}
.observation {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
@media (max-width: 800px) {
  .filters {
    grid-template-columns: 1fr;
  }
}
</style>
