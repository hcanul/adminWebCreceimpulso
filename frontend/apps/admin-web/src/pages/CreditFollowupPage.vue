<template>
  <q-page class="q-pa-md followup-page">
    <header class="row items-start justify-between q-mb-lg">
      <div>
        <h1>Seguimiento de cobranza</h1>
        <p>{{ data?.folio }} · {{ data?.group }}</p>
      </div>
      <div class="row q-gutter-sm">
        <q-btn
          outline
          color="primary"
          label="Todas las observaciones"
          to="/observaciones-cobranza"
        />
        <q-btn outline color="primary" label="Cartera" to="/cartera" /><q-btn
          outline
          color="primary"
          label="Estado de cuenta"
          :to="`/creditos/${id}/estado-de-cuenta`"
        /><q-btn
          outline
          color="primary"
          icon="refresh"
          label="Actualizar"
          :disable="loading || busy"
          @click="load"
        />
      </div>
    </header>
    <q-linear-progress v-if="loading" indeterminate />
    <q-banner v-if="error" class="bg-red-1 text-negative" role="alert"
      >{{ error }}<template #action><q-btn flat label="Reintentar" @click="load" /></template
    ></q-banner>
    <template v-if="data">
      <q-card flat bordered class="q-mb-lg"
        ><q-card-section>
          <div class="row items-center justify-between">
            <div>
              <q-badge :color="data.classification === 'recovery' ? 'negative' : 'primary'">{{
                className(data.classification)
              }}</q-badge>
              <p class="q-mt-sm">
                La clasificación conserva la deuda, los pagos y los cortes. Los abonos no
                reincorporan automáticamente el crédito.
              </p>
            </div>
            <q-btn
              v-if="data.can_manage"
              color="primary"
              no-caps
              :label="
                data.classification === 'ordinary'
                  ? 'Trasladar a recuperación'
                  : 'Reincorporar a ordinaria'
              "
              :loading="busy"
              @click="classify"
            />
          </div> </q-card-section
      ></q-card>
      <div class="table-scroll q-mb-lg">
        <table>
          <caption>
            Integrantes del crédito · situación actual
          </caption>
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Teléfono</th>
              <th>Saldo total</th>
              <th>Exigible</th>
              <th>En atraso</th>
              <th>Días</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in data.members" :key="m.client_id">
              <td>
                {{ m.name }}<br /><small>{{ m.folio }}</small>
              </td>
              <td>{{ m.phone || 'No registrado' }}</td>
              <td>{{ money(m.balance) }}</td>
              <td>{{ money(m.due) }}</td>
              <td :class="Number(m.overdue) > 0 ? 'text-negative' : 'text-positive'">
                {{ money(m.overdue) }}
              </td>
              <td>{{ m.days_overdue }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <q-card flat bordered class="q-mb-lg"
        ><q-card-section
          ><h2>Agregar observación</h2>
          <p>
            Las observaciones se agregan al historial. Para corregir una nota, registra otra que
            explique la corrección.
          </p>
          <q-form @submit="append"
            ><FilterSelect
              v-model="noteClient"
              outlined
              stack-label
              emit-value
              map-options
              label="Integrante"
              :options="members"
              :disable="busy || !!pendingNote"
              :rules="[(v: unknown) => !!v || 'Selecciona una integrante']" />
            <q-input
              v-model="observation"
              outlined
              stack-label
              type="textarea"
              label="Nueva observación"
              maxlength="2000"
              counter
              :disable="busy || !!pendingNote"
              :rules="[(v: string) => !!v.trim() || 'Escribe una observación']" />
            <q-banner v-if="pendingNote && !busy" class="bg-amber-1 q-mb-md"
              >La respuesta no se confirmó. Reintenta la misma operación para evitar
              duplicados.</q-banner
            >
            <q-btn
              color="primary"
              type="submit"
              :label="pendingNote ? 'Reintentar observación' : 'Guardar observación'"
              :loading="busy" /></q-form></q-card-section
      ></q-card>
      <q-card flat bordered class="q-mb-lg"
        ><q-card-section
          ><h2>Historial de observaciones</h2>
          <div class="filters">
            <FilterSelect
              v-model="filterClient"
              outlined
              stack-label
              label="Todas las integrantes"
              clearable
              emit-value
              map-options
              :options="members"
            /><q-input v-model="from" outlined stack-label type="date" label="Desde" /><q-input
              v-model="to"
              outlined
              stack-label
              type="date"
              label="Hasta"
            /><q-input
              v-model="author"
              outlined
              stack-label
              label="Nombre del autor"
              maxlength="100"
            />
          </div>
          <q-btn
            flat
            color="primary"
            label="Aplicar filtros"
            class="q-mt-sm"
            @click="applyFilters" />
          <p v-if="!data.notes.total">No hay observaciones para estos filtros.</p>
          <q-list separator
            ><q-item v-for="note in data.notes.data" :key="note.id"
              ><q-item-section
                ><q-item-label class="note">{{ note.observation }}</q-item-label
                ><q-item-label caption
                  >{{ memberName(note.client_id) }} · {{ note.actor_name }} ·
                  {{ date(note.created_at) }}</q-item-label
                ></q-item-section
              ></q-item
            ></q-list
          >
          <div class="row items-center justify-between q-mt-md">
            <span
              >{{ data.notes.total }} observaciones · {{ page }} / {{ data.notes.last_page }}</span
            >
            <div>
              <q-btn flat label="Anterior" :disable="page <= 1" @click="changePage(-1)" /><q-btn
                flat
                label="Siguiente"
                :disable="page >= data.notes.last_page"
                @click="changePage(1)"
              />
            </div></div></q-card-section
      ></q-card>
      <q-card flat bordered
        ><q-card-section
          ><h2>Historial de clasificación</h2>
          <p v-if="!data.events.length">Sin reclasificaciones.</p>
          <q-list separator
            ><q-item v-for="event in data.events" :key="event.id"
              ><q-item-section
                ><q-item-label
                  >{{ className(event.previous_class) }} →
                  {{ className(event.next_class) }}</q-item-label
                >
                <p class="note">{{ event.reason }}</p>
                <q-item-label caption
                  >{{ event.actor_name }} · {{ date(event.created_at) }}</q-item-label
                ></q-item-section
              ></q-item
            ></q-list
          ></q-card-section
        ></q-card
      >
    </template>
  </q-page>
</template>
<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { useRoute, useRouter, onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router';
import { storeToRefs } from 'pinia';
import Swal from 'sweetalert2';
import axios from 'axios';
import FilterSelect from '../components/FilterSelect.vue';
import { useFollowupStore } from '../stores/followup.store';
import {
  followupService,
  type FollowupNoteCommand,
  type ClassifyCommand,
} from '../services/followup.service';
import { administrationError } from '../services/administration.service';
import { alerts } from '../services/alerts.service';
import { money } from '../domain/credit-calculation';
const route = useRoute(),
  router = useRouter(),
  store = useFollowupStore();
const { data, loading, error } = storeToRefs(store);
const id = computed(() => String((route.params as { id?: string }).id ?? ''));
const page = computed(() => Math.max(1, Math.floor(Number(route.query.page) || 1)));
const filterClient = ref<string | null>(null),
  from = ref(''),
  to = ref(''),
  author = ref('');
const noteClient = ref<string | null>(null),
  observation = ref(''),
  busy = ref(false);
const pendingNote = ref<FollowupNoteCommand | null>(null);
let pendingClassification: ClassifyCommand | null = null;
const members = computed(
  () =>
    data.value?.members.map((m) => ({ label: `${m.name} · ${m.folio}`, value: m.client_id })) ?? [],
);
const memberName = (client: string) =>
  data.value?.members.find((m) => m.client_id === client)?.name ?? client;
const className = (value: string) =>
  value === 'recovery' ? 'Recuperación / cartera vencida' : 'Ordinaria';
const date = (value: string) =>
  new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'America/Cancun',
  }).format(new Date(value));
function load() {
  return store.load(id.value, {
    page: page.value,
    client_id: filterClient.value || undefined,
    from: from.value || undefined,
    to: to.value || undefined,
    author: author.value || undefined,
  });
}
function applyFilters() {
  if (page.value !== 1) void router.replace({ query: { ...route.query, page: '1' } });
  else void load();
}
function changePage(delta: number) {
  void router.replace({ query: { ...route.query, page: String(page.value + delta) } });
}
async function append() {
  if (busy.value || !noteClient.value || !observation.value.trim()) return;
  const command = pendingNote.value ?? {
    id: crypto.randomUUID(),
    client_id: noteClient.value,
    observation: observation.value.trim(),
  };
  pendingNote.value = command;
  busy.value = true;
  try {
    await followupService.append(id.value, command);
    pendingNote.value = null;
    observation.value = '';
    await load();
    await alerts.success('Observación conservada en el historial.');
  } catch (e) {
    if (
      axios.isAxiosError(e) &&
      e.response &&
      e.response.status >= 400 &&
      e.response.status < 500 &&
      e.response.status !== 429
    )
      pendingNote.value = null;
    await alerts.error(administrationError(e));
  } finally {
    busy.value = false;
  }
}
async function classify() {
  if (busy.value || !data.value?.can_manage) return;
  let command = pendingClassification;
  if (!command) {
    const result = await Swal.fire({
      title:
        data.value.classification === 'ordinary'
          ? 'Trasladar a recuperación'
          : 'Reincorporar a cartera ordinaria',
      text: 'La deuda y los cortes permanecen intactos. Registra el motivo de esta decisión.',
      input: 'textarea',
      inputLabel: 'Motivo obligatorio',
      showCancelButton: true,
      confirmButtonText: 'Confirmar',
      cancelButtonText: 'Cancelar',
      inputValidator: (v) =>
        v.trim().length >= 10 && v.trim().length <= 2000
          ? undefined
          : 'Escribe un motivo de 10 a 2000 caracteres.',
    });
    if (!result.isConfirmed || typeof result.value !== 'string') return;
    command = {
      id: crypto.randomUUID(),
      version: data.value.version,
      classification: data.value.classification === 'ordinary' ? 'recovery' : 'ordinary',
      reason: result.value.trim(),
    };
  }
  busy.value = true;
  pendingClassification = command;
  try {
    await followupService.classify(id.value, command);
    pendingClassification = null;
    await load();
    await alerts.success('Clasificación registrada con auditoría.');
  } catch (e) {
    if (
      axios.isAxiosError(e) &&
      e.response &&
      e.response.status >= 400 &&
      e.response.status < 500 &&
      e.response.status !== 429
    ) {
      pendingClassification = null;
      await load();
    }
    await alerts.error(administrationError(e));
  } finally {
    busy.value = false;
  }
}
async function canLeave() {
  if (busy.value) return false;
  if (!observation.value.trim() && !pendingClassification && !pendingNote.value) return true;
  return alerts.confirm(
    '¿Salir del seguimiento?',
    'Hay una observación sin guardar o una operación sin confirmar. Consulta el historial antes de volver a capturarla.',
  );
}
onBeforeRouteLeave(canLeave);
onBeforeRouteUpdate(async (target, current) => {
  if ((target.params as { id?: string }).id !== (current.params as { id?: string }).id) {
    if (!(await canLeave())) return false;
    observation.value = '';
    noteClient.value = null;
    pendingNote.value = null;
    pendingClassification = null;
    filterClient.value = null;
    from.value = '';
    to.value = '';
    author.value = '';
  }
});
watch(
  () => route.fullPath,
  () => {
    void load();
  },
  { immediate: true },
);
onBeforeUnmount(() => store.clear());
</script>
<style scoped>
.followup-page {
  max-width: 1500px;
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
  margin: 0 0 12px;
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
th,
td {
  padding: 14px;
  border-bottom: 1px solid #eee;
  min-width: 100px;
}
th {
  background: #f8f2f5;
  color: var(--q-primary);
}
caption {
  text-align: left;
  padding: 15px;
  font-weight: 600;
}
.filters {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.note {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
@media (max-width: 600px) {
  .filters {
    grid-template-columns: 1fr;
  }
}
</style>
