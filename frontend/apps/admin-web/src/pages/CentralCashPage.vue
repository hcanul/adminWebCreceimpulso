<template>
  <q-page class="q-pa-md central-cash-page">
    <header class="row items-start justify-between q-mb-lg">
      <div>
        <h1>{{ route.meta.cashMovements ? 'Cobros y depósitos' : 'Caja central' }}</h1>
        <p>Efectivo, depósitos y ajustes por sucursal</p>
      </div>
      <div class="row q-gutter-sm no-print">
        <q-btn
          v-if="auth.can('credit.collect')"
          color="primary"
          no-caps
          icon="add"
          label="Registrar cobro o depósito"
          to="/cobros"
        />
        <q-btn
          outline
          color="primary"
          icon="download"
          label="Todos los movimientos CSV"
          :disable="!data || loading"
          :loading="exporting"
          @click="download"
        /><q-btn
          outline
          color="primary"
          icon="print"
          label="Imprimir resumen"
          :disable="!data || loading"
          @click="print"
        />
      </div>
    </header>
    <q-form class="filters no-print q-mb-md" @submit="apply"
      ><q-input
        v-model="from"
        outlined
        stack-label
        type="date"
        label="Desde"
        :rules="[(v) => !!v || 'Selecciona la fecha']"
      /><q-input
        v-model="to"
        outlined
        stack-label
        type="date"
        label="Hasta"
        :rules="[(v) => (!!v && v >= from) || 'Revisa la fecha final']"
      />
      <FilterSelect
        v-model="branch"
        outlined
        stack-label
        label="Todas las sucursales permitidas"
        clearable
        emit-value
        map-options
        :options="branchOptions"
      />
      <FilterSelect
        v-model="method"
        outlined
        label="Medio del cobro"
        emit-value
        map-options
        :options="methods"
      />
      <q-input
        v-model="search"
        outlined
        clearable
        label="Buscar folio bancario, crédito, grupo o cajero"
        maxlength="100"
      />
      <q-btn color="primary" type="submit" label="Consultar" :loading="loading" />
    </q-form>
    <q-linear-progress v-if="loading" indeterminate />
    <q-banner v-if="error" class="bg-red-1 text-negative" role="alert">{{ error }}</q-banner>
    <template v-if="data">
      <p>
        Periodo: {{ data.from }} al {{ data.to }} · Generado: {{ data.generated_at_local }} (Cancún)
      </p>
      <div class="metrics q-mb-lg">
        <q-card flat bordered v-for="card in cards" :key="card.label"
          ><q-card-section
            ><div>{{ card.label }}</div>
            <strong>{{ money(card.value) }}</strong></q-card-section
          ></q-card
        >
      </div>
      <q-banner class="bg-grey-2 q-mb-lg"
        >Los cobros se agrupan por fecha de recepción y los reversos por fecha del ajuste. Se
        incluyen cobros con y sin corte, cada uno una sola vez. El neto es de registros: no
        certifica efectivo físico, devolución de dinero ni conciliación bancaria.</q-banner
      >
      <div class="table-scroll q-mb-lg">
        <table>
          <caption>
            Consolidado de sucursales · MXN
          </caption>
          <thead>
            <tr>
              <th>Sucursal</th>
              <th>Efectivo registrado</th>
              <th>Depósitos registrados</th>
              <th>Reversos efectivo</th>
              <th>Reversos banco</th>
              <th>Efectivo neto</th>
              <th>Banco neto</th>
              <th>Cobros / reversos</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in data.branches" :key="b.branch_id">
              <th>{{ b.branch }}</th>
              <td>{{ money(b.cash_gross) }}</td>
              <td>{{ money(b.bank_gross) }}</td>
              <td>{{ money(b.cash_reversed) }}</td>
              <td>{{ money(b.bank_reversed) }}</td>
              <td>{{ money(b.cash_net) }}</td>
              <td>{{ money(b.bank_net) }}</td>
              <td>{{ b.payment_count }} / {{ b.reversal_count }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <section class="no-print">
        <h2>Movimientos del periodo</h2>
        <p>Detalle de la página actual. Descarga CSV para obtener todas las páginas.</p>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Fecha / tipo</th>
                <th>Sucursal / crédito</th>
                <th>Grupo</th>
                <th>Medio / importe</th>
                <th>Cajero del cobro</th>
                <th>Ficha bancaria</th>
                <th>Corte original</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in data.movements.data" :key="m.id">
                <td>
                  {{ m.event_on }}<br /><q-badge
                    :color="m.kind === 'reversal' ? 'negative' : 'primary'"
                    >{{ m.kind === 'reversal' ? 'Reverso' : 'Cobro' }}</q-badge
                  >
                  · pago {{ m.number }}
                </td>
                <td>{{ m.branch }}<br />{{ m.credit_folio }}</td>
                <td>{{ m.group }}</td>
                <td>
                  {{ m.method === 'bank_deposit' ? 'Depósito bancario' : 'Efectivo' }}<br />{{
                    m.kind === 'reversal' ? '-' : ''
                  }}{{ money(m.amount) }}
                </td>
                <td>{{ m.cashier }}</td>
                <td>
                  {{ m.bank_name || '—' }}<br />{{ m.bank_reference }}<br />{{ m.deposited_on }}
                </td>
                <td>
                  {{ m.closing_folio || 'Sin corte' }}
                  <q-btn
                    v-if="auth.can('credit.collect') || auth.can('credit.reverse')"
                    flat
                    no-caps
                    color="primary"
                    label="Ver comprobante"
                    :to="`/comprobantes/${m.payment_id}`"
                  />
                </td>
              </tr>
              <tr v-if="!data.movements.total">
                <td colspan="7">Sin movimientos en este periodo.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="row justify-between items-center q-mt-md">
          <span
            >{{ data.movements.total }} movimientos · {{ page }} /
            {{ data.movements.last_page }}</span
          >
          <div>
            <q-btn flat label="Anterior" :disable="page <= 1" @click="changePage(-1)" /><q-btn
              flat
              label="Siguiente"
              :disable="page >= data.movements.last_page"
              @click="changePage(1)"
            />
          </div>
        </div>
      </section>
    </template>
  </q-page>
</template>
<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import FilterSelect from '../components/FilterSelect.vue';
import { useCentralCashStore } from '../stores/central-cash.store';
import { centralCashService, type CentralCashFilters } from '../services/central-cash.service';
import { useAuthStore } from '../stores/auth.store';
import { money } from '../domain/credit-calculation';
import { alerts } from '../services/alerts.service';
import { administrationError } from '../services/administration.service';
const auth = useAuthStore();
const search = ref('');
const route = useRoute(),
  router = useRouter(),
  store = useCentralCashStore();
const { data, branches, loading, error } = storeToRefs(store);
const today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Cancun' }).format(new Date());
const from = ref(''),
  to = ref(''),
  branch = ref<string | null>(null),
  method = ref('all'),
  exporting = ref(false);
let exportRequest: AbortController | undefined;
const methods = [
  { label: 'Efectivo y depósitos', value: 'all' },
  { label: 'Efectivo', value: 'cash_branch' },
  { label: 'Depósitos bancarios', value: 'bank_deposit' },
];
const branchOptions = computed(() => branches.value.map((b) => ({ label: b.name, value: b.id })));
const page = computed(() => Math.max(1, Math.floor(Number(route.query.page) || 1)));
function parameters(): CentralCashFilters {
  return {
    search: search.value || '',
    from: from.value,
    to: to.value,
    method: method.value,
    branch_id: branch.value || undefined,
    page: page.value,
  };
}
function load() {
  return store.load(parameters());
}
function apply() {
  const target = {
    query: {
      search: search.value || undefined,
      from: from.value,
      to: to.value,
      branch: branch.value || undefined,
      method: method.value,
      page: '1',
    },
  };
  if (router.resolve(target).fullPath === route.fullPath) void load();
  else void router.replace(target);
}
function changePage(delta: number) {
  void router.replace({ query: { ...route.query, page: String(page.value + delta) } });
}
function print() {
  if (data.value && !loading.value) window.print();
}
async function download() {
  if (!data.value || exporting.value) return;
  exporting.value = true;
  const c = new AbortController();
  exportRequest = c;
  try {
    const blob = await centralCashService.export(parameters(), c.signal);
    if (c.signal.aborted) return;
    const url = URL.createObjectURL(blob),
      link = document.createElement('a');
    link.href = url;
    link.download = `caja-central-${from.value}-${to.value}.csv`;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (e) {
    if (!c.signal.aborted) await alerts.error(administrationError(e));
  } finally {
    exporting.value = false;
  }
}
watch(
  [from, to, branch, method, search],
  () => {
    exportRequest?.abort();
    store.invalidate();
  },
  { flush: 'sync' },
);
watch(
  () => route.fullPath,
  () => {
    search.value = typeof route.query.search === 'string' ? route.query.search : '';
    from.value = typeof route.query.from === 'string' ? route.query.from : today;
    to.value = typeof route.query.to === 'string' ? route.query.to : today;
    branch.value = typeof route.query.branch === 'string' ? route.query.branch : null;
    method.value = methods.some((m) => m.value === route.query.method)
      ? String(route.query.method)
      : 'all';
    void load();
  },
  { immediate: true },
);
void store.initialize();
onBeforeUnmount(() => {
  exportRequest?.abort();
  store.clear();
});
const cards = computed(() =>
  data.value
    ? [
        { label: 'Efectivo recibido', value: data.value.summary.cash_gross },
        { label: 'Depósitos registrados', value: data.value.summary.bank_gross },
        { label: 'Neto después de reversos', value: data.value.summary.net },
      ]
    : [],
);
</script>
<style scoped>
.central-cash-page {
  max-width: 1600px;
  margin: auto;
}
h1 {
  font-size: 1.8rem;
  line-height: 1.3;
  font-weight: 700;
  margin: 0 0 10px;
}
h2 {
  font-size: 1.2rem;
  line-height: 1.4;
  font-weight: 700;
}
.filters {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}
.metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}
.metrics strong {
  font-size: 1.4rem;
  display: block;
  color: var(--q-primary);
  overflow-wrap: anywhere;
}
.table-scroll {
  overflow-x: auto;
  background: white;
  border: 1px solid #e5dce0;
  border-radius: 12px;
}
table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}
caption {
  padding: 14px;
  text-align: left;
  font-weight: 600;
}
th,
td {
  padding: 12px;
  border-bottom: 1px solid #eee;
  min-width: 110px;
}
thead {
  background: #f8f2f5;
  color: var(--q-primary);
}
@media (max-width: 650px) {
  .filters,
  .metrics {
    grid-template-columns: 1fr;
  }
}
@media print {
  .no-print {
    display: none !important;
  }
  .table-scroll {
    overflow: visible;
  }
  th,
  td {
    font-size: 9px;
    padding: 5px;
    min-width: 0;
  }
  .metrics {
    grid-template-columns: repeat(3, 1fr);
  }
}
</style>

<style>
@media print {
  body:has(.central-cash-page) .q-drawer,
  body:has(.central-cash-page) .q-header {
    display: none !important;
  }
  body:has(.central-cash-page) .q-page-container {
    padding: 0 !important;
  }
  .central-cash-page {
    min-height: 0 !important;
    max-width: none !important;
  }
}
</style>
