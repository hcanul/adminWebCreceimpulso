<template>
  <q-page class="q-pa-md">
    <p class="eyebrow">DOCUMENTOS DE OPERACIÓN</p>
    <h1>Reportes para impresión</h1>
    <p>Consulta los formatos de referencia y guarda una copia en PDF desde la impresión.</p>
    <div class="report-controls q-gutter-md q-mb-md">
      <q-select v-model="kind" :options="kinds" emit-value map-options outlined label="Formato" />
      <q-select
        v-if="requiresCredit"
        v-model="credit"
        :options="credits"
        :option-label="creditLabel"
        use-input
        clearable
        fill-input
        hide-selected
        input-debounce="300"
        outlined
        label="Buscar crédito entregado por grupo o folio"
        @filter="searchCredits"
        @update:model-value="loadMembers"
      >
        <template #no-option
          ><q-item
            ><q-item-section
              >Sin coincidencias. Escribe otra parte del grupo o folio.</q-item-section
            ></q-item
          ></template
        >
      </q-select>
      <q-select
        v-if="kind === 'collection_log' || kind === 'statement'"
        v-model="member"
        :options="members"
        emit-value
        map-options
        use-input
        clearable
        input-debounce="0"
        outlined
        :label="kind === 'statement' ? 'Integrante opcional o grupo completo' : 'Integrante'"
        @filter="filterMembers"
      />
      <q-input
        v-if="kind === 'agenda'"
        v-model="week"
        outlined
        type="date"
        label="Semana de la agenda"
        stack-label
      />
      <q-input
        v-if="kind === 'portfolio'"
        v-model="search"
        outlined
        label="Filtrar cartera por grupo, folio o sucursal"
      />
      <q-select
        v-if="kind === 'portfolio'"
        v-model="status"
        :options="statuses"
        emit-value
        map-options
        outlined
        label="Estado de cartera"
      />
    </div>
    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-md" role="alert">{{
      error
    }}</q-banner>
    <div class="q-gutter-sm q-mb-md">
      <q-btn
        color="primary"
        label="Preparar reporte"
        no-caps
        :loading="loading"
        :disable="!canPrepare"
        @click="prepare"
      />
      <q-btn
        outline
        color="primary"
        icon="print"
        label="Imprimir o guardar PDF"
        no-caps
        :disable="!html || !ready || loading"
        @click="printReport"
      />
    </div>
    <iframe
      v-if="html"
      ref="frame"
      :srcdoc="html"
      title="Reporte preparado para impresión"
      sandbox="allow-same-origin allow-modals"
      @load="ready = true"
    />
    <q-banner v-else class="bg-grey-2"
      >Los reportes consultan datos actuales. La bitácora y la agenda incluyen espacios para llenado
      manual; imprimir no registra cobros ni compromisos.</q-banner
    >
  </q-page>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth.store';
import { reportsService, type ReportCredit, type ReportKind } from '../services/reports.service';
const auth = useAuthStore(),
  route = useRoute();
const kind = ref<ReportKind>('portfolio'),
  credit = ref<ReportCredit | null>(null),
  credits = ref<ReportCredit[]>([]);
const member = ref<string | null>(null),
  allMembers = ref<{ label: string; value: string }[]>([]),
  members = ref<{ label: string; value: string }[]>([]);
const html = ref(''),
  loading = ref(false),
  error = ref(''),
  ready = ref(false),
  week = ref(''),
  search = ref(''),
  status = ref('all');
const frame = ref<HTMLIFrameElement>();
let searchRequest: AbortController | null = null,
  reportRequest: AbortController | null = null,
  generation = 0,
  memberGeneration = 0;
const kinds = computed(() => [
  { label: 'Reporte de cartera', value: 'portfolio' },
  { label: 'Estado de cuenta', value: 'statement' },
  ...(auth.can('credit.collect') || auth.can('cash.view')
    ? [{ label: 'Relación de fichas de depósito', value: 'deposits' }]
    : []),
  ...(auth.can('credit.collect') || auth.can('credit.review')
    ? [{ label: 'Bitácora de cobranza', value: 'collection_log' }]
    : []),
  { label: 'Agenda de actividades', value: 'agenda' },
]);
const statuses = [
  { label: 'Todos', value: 'all' },
  { label: 'Con atraso', value: 'overdue' },
  { label: 'Vence hoy', value: 'today' },
  { label: 'Sin atraso', value: 'current' },
];
const requiresCredit = computed(() => !['portfolio', 'agenda'].includes(kind.value));
const canPrepare = computed(
  () =>
    (!requiresCredit.value || !!credit.value) &&
    (kind.value !== 'collection_log' || !!member.value),
);
const creditLabel = (c: ReportCredit) => (c ? `${c.folio} · ${c.team?.name ?? ''}` : '');
function invalidate() {
  generation++;
  reportRequest?.abort();
  html.value = '';
  ready.value = false;
  loading.value = false;
  error.value = '';
}
watch([kind, credit, member, week, search, status], invalidate);
async function searchCredits(value: string, update: (fn: () => void) => void) {
  searchRequest?.abort();
  const request = new AbortController();
  searchRequest = request;
  try {
    const rows = await reportsService.credits(value, request.signal);
    if (!request.signal.aborted)
      update(() => {
        credits.value = rows;
      });
  } catch {
    if (!request.signal.aborted) {
      error.value = 'No se pudo consultar la lista de créditos.';
      update(() => {
        credits.value = [];
      });
    }
  }
}
async function loadMembers() {
  const attempt = ++memberGeneration;
  member.value = null;
  allMembers.value = [];
  members.value = [];
  if (!credit.value) return;
  try {
    const record = await reportsService.credit(credit.value.id);
    if (attempt !== memberGeneration) return;
    allMembers.value = record.participations.map((p) => ({
      label: `${p.client.first_name} ${p.client.last_name}`,
      value: p.client_id,
    }));
    members.value = allMembers.value;
  } catch {
    if (attempt === memberGeneration) error.value = 'No se pudo consultar a las integrantes.';
  }
}
function filterMembers(value: string, update: (fn: () => void) => void) {
  update(() => {
    members.value = allMembers.value.filter((m) =>
      m.label.toLocaleLowerCase().includes(value.toLocaleLowerCase()),
    );
  });
}
async function prepare() {
  invalidate();
  const attempt = generation;
  reportRequest = new AbortController();
  loading.value = true;
  const params: Record<string, string> = { search: search.value, status: status.value };
  if (requiresCredit.value && credit.value) params.credit_id = credit.value.id;
  if (member.value && requiresCredit.value) params.client_id = member.value;
  if (week.value) params.week_on = week.value;
  try {
    const result = await reportsService.render(kind.value, params, reportRequest.signal);
    if (attempt === generation) html.value = result;
  } catch {
    if (attempt === generation)
      error.value =
        'No se pudo preparar el reporte. Revisa el crédito, la integrante y tus permisos.';
  } finally {
    if (attempt === generation) loading.value = false;
  }
}
function printReport() {
  if (html.value && ready.value && !loading.value) frame.value?.contentWindow?.print();
}
onMounted(async () => {
  if (typeof route.query.credit === 'string') {
    try {
      credit.value = await reportsService.credit(route.query.credit);
      kind.value = 'statement';
      await loadMembers();
    } catch {
      error.value = 'Crédito no disponible.';
    }
  }
});
onBeforeUnmount(() => {
  generation++;
  memberGeneration++;
  searchRequest?.abort();
  reportRequest?.abort();
});
</script>
<style scoped>
.report-controls {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}
iframe {
  width: 100%;
  height: 78vh;
  border: 1px solid #ccc;
  background: white;
}
h1 {
  font-size: 28px;
}
</style>
