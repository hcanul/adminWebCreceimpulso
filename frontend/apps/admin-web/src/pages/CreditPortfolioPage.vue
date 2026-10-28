<template>
  <q-page class="q-pa-md portfolio-page">
    <div class="row items-center justify-between q-mb-md">
      <div>
        <h1>Cartera y vencimientos</h1>
        <p>Créditos entregados · Consulta al {{ data?.as_of ?? '—' }} (Cancún)</p>
      </div>
      <div class="row q-gutter-sm">
        <q-btn
          outline
          color="primary"
          icon="download"
          label="Descargar CSV"
          :loading="exporting"
          :disable="loading || !data"
          @click="downloadReport"
        />
        <q-btn
          outline
          color="primary"
          icon="refresh"
          label="Actualizar"
          :disable="loading"
          @click="load"
        />
      </div>
    </div>
    <div class="filters q-mb-lg">
      <q-input
        v-model="search"
        outlined
        label="Buscar folio, grupo o sucursal"
        maxlength="100"
        clearable
        @update:model-value="updateSearch"
      />
      <FilterSelect
        v-model="status"
        outlined
        label="Situación del crédito"
        emit-value
        map-options
        :options="statuses"
      />
    </div>
    <q-linear-progress v-if="loading" indeterminate />
    <q-banner v-if="error" role="alert" class="bg-red-1 text-negative q-mb-md"
      >{{ error }}<template #action><q-btn flat label="Reintentar" @click="load" /></template
    ></q-banner>
    <template v-if="data">
      <div class="metrics">
        <q-card flat bordered v-for="card in cards" :key="card.label"
          ><q-card-section
            ><div>{{ card.label }}</div>
            <strong>{{ card.value }}</strong></q-card-section
          ></q-card
        >
      </div>
      <p class="q-mt-md text-grey-8">
        Totales de todos los resultados filtrados, no solo de esta página. El saldo total y lo
        exigible incluyen la mora pendiente de los créditos con esa política autorizada. La mora ya
        está incluida en los importes en atraso: no debe sumarse de nuevo. “Vence hoy” muestra
        únicamente las cuotas de hoy.
      </p>
      <q-banner v-if="!data.total" class="bg-grey-2"
        >No hay créditos que coincidan con esta búsqueda.</q-banner
      >
      <div v-else class="table-scroll">
        <table aria-label="Cartera de créditos">
          <thead>
            <tr>
              <th>Crédito / grupo</th>
              <th>Sucursal</th>
              <th>Cuotas pendientes</th>
              <th>Mora pendiente</th>
              <th>Saldo total</th>
              <th>Vence hoy</th>
              <th>En atraso</th>
              <th>Días de atraso</th>
              <th>Próxima cuota pendiente</th>
              <th>Consulta</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in data.data" :key="row.id">
              <td>
                <strong>{{ row.folio }}</strong
                ><br />{{ row.group }}
              </td>
              <td>{{ row.branch }}</td>
              <td>{{ money(row.installment_balance) }}</td>
              <td>{{ money(row.late_fee_balance) }}</td>
              <td>{{ money(row.balance) }}</td>
              <td>{{ money(row.due_today) }}</td>
              <td :class="row.days_overdue ? 'text-negative' : ''">{{ money(row.overdue) }}</td>
              <td>{{ row.days_overdue }}</td>
              <td>{{ row.next_due ?? 'Sin cuotas futuras' }}</td>
              <td>
                <q-btn
                  flat
                  no-caps
                  color="primary"
                  label="Estado de cuenta"
                  :to="`/creditos/${row.id}/estado-de-cuenta`"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="row items-center justify-between q-mt-md">
        <span>{{ data.total }} créditos · Página {{ data.page }} de {{ data.last_page }}</span>
        <div>
          <q-btn flat label="Anterior" :disable="page <= 1" @click="setPage(page - 1)" /><q-btn
            flat
            label="Siguiente"
            :disable="page >= data.last_page"
            @click="setPage(page + 1)"
          />
        </div>
      </div>
    </template>
  </q-page>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute, useRouter } from 'vue-router';
import FilterSelect from '../components/FilterSelect.vue';
import { usePortfolioStore } from '../stores/portfolio.store';
import { money } from '../domain/credit-calculation';
import { portfolioService } from '../services/portfolio.service';
import { alerts } from '../services/alerts.service';
const route = useRoute(),
  router = useRouter(),
  store = usePortfolioStore();
const { data, loading, error } = storeToRefs(store);
const statuses = [
  { label: 'Todos los activos', value: 'all' },
  { label: 'Con atraso (cuotas o mora)', value: 'overdue' },
  { label: 'Con cuota pendiente hoy', value: 'today' },
  { label: 'Sin atraso', value: 'current' },
];
const search = ref('');
const exporting = ref(false);
let exportController: AbortController | undefined;
async function downloadReport() {
  if (exporting.value) return;
  exporting.value = true;
  const controller = new AbortController();
  exportController = controller;
  try {
    const blob = await portfolioService.export(
      {
        search: typeof route.query.search === 'string' ? route.query.search.slice(0, 100) : '',
        status: status.value,
      },
      controller.signal,
    );
    if (controller.signal.aborted) return;
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'cartera-y-vencimientos.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch {
    if (!controller.signal.aborted)
      await alerts.error('No se pudo descargar el reporte. Intenta nuevamente.');
  } finally {
    exporting.value = false;
  }
}
const page = computed(() =>
  Math.max(1, Math.min(1000000, Math.floor(Number(route.query.page)) || 1)),
);
const status = computed({
  get: () =>
    statuses.some((s) => s.value === route.query.status) ? String(route.query.status) : 'all',
  set: (value) => {
    void router.replace({ query: { ...route.query, status: value, page: '1' } });
  },
});
let timer: ReturnType<typeof setTimeout> | undefined;
function updateSearch() {
  clearTimeout(timer);
  store.clear();
  timer = setTimeout(() => {
    const target = { query: { ...route.query, search: search.value || undefined, page: '1' } };
    if (router.resolve(target).fullPath === route.fullPath) void load();
    else void router.replace(target);
  }, 300);
}
function setPage(value: number) {
  void router.replace({ query: { ...route.query, page: String(value) } });
}
function load() {
  return store.load({
    search: typeof route.query.search === 'string' ? route.query.search.slice(0, 100) : '',
    status: status.value,
    page: page.value,
  });
}
watch(
  () => route.fullPath,
  () => {
    clearTimeout(timer);
    search.value = typeof route.query.search === 'string' ? route.query.search.slice(0, 100) : '';
    void load();
  },
  { immediate: true },
);
onBeforeUnmount(() => {
  exportController?.abort();
  clearTimeout(timer);
  store.clear();
});
const cards = computed(() =>
  data.value
    ? [
        { label: 'Créditos activos', value: String(data.value.summary.credits) },
        { label: 'Saldo total', value: money(data.value.summary.balance) },
        { label: 'Mora pendiente', value: money(data.value.summary.late_fee_balance) },
        { label: 'Exigible a la fecha', value: money(data.value.summary.due) },
        { label: 'Pendiente de hoy', value: money(data.value.summary.due_today) },
        { label: 'Importe en atraso', value: money(data.value.summary.overdue) },
      ]
    : [],
);
</script>
<style scoped>
.portfolio-page {
  max-width: 1600px;
  margin: auto;
}
h1 {
  font-size: 1.8rem;
  line-height: 1.3;
  font-weight: 700;
  margin: 0 0 8px;
}
.filters {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 16px;
}
.metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.metrics strong {
  display: block;
  font-size: 1.3rem;
  color: var(--q-primary);
  margin-top: 8px;
  overflow-wrap: anywhere;
}
.table-scroll {
  overflow-x: auto;
  background: white;
  border: 1px solid #e1dce0;
  border-radius: 12px;
}
table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}
th,
td {
  padding: 14px;
  border-bottom: 1px solid #eee;
}
th {
  background: #f8f2f5;
  color: var(--q-primary);
}
td {
  min-width: 110px;
}
@media (max-width: 900px) {
  .metrics {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 600px) {
  .filters {
    grid-template-columns: 1fr;
  }
}
</style>
