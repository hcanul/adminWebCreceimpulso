<template>
  <q-page class="intake-page"
    ><div class="intake-heading">
      <div>
        <p class="eyebrow">IDENTIDAD ANTES DE LA CAPTURA</p>
        <h1>{{ entered ? 'Expediente del cliente' : 'Verificar CURP' }}</h1>
        <p>Una persona, un registro. Su expediente conserva el historial de cambios.</p>
      </div>
      <q-btn flat icon="arrow_back" no-caps label="Grupos" to="/grupos" />
    </div>
    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-md" role="alert"
      >{{ error }}
      <ul v-if="Object.keys(fieldErrors).length">
        <li v-for="(messages, key) in fieldErrors" :key="key">
          <button class="error-link" @click="goToError(String(key))">
            {{ fieldLabel(String(key)) }}: {{ messages.join(' ') }}
          </button>
        </li>
      </ul></q-banner
    >
    <q-banner v-if="success" class="bg-green-1 text-green-9 q-mb-md" role="status">{{
      success
    }}</q-banner>
    <q-card v-if="!entered" flat bordered class="intake-card q-mb-lg"
      ><q-card-section
        ><h2>1. Selecciona el grupo y consulta la CURP</h2>
        <p class="text-grey-7">
          Verificamos estructura, fecha y dígito de control, y buscamos coincidencias internas. Esto
          no sustituye la comprobación documental ni una consulta oficial a RENAPO.
        </p>
        <q-form @submit="verify"
          ><div class="intake-form-grid">
            <GroupSearch
              v-model="selectedTeam"
              :disabled="busy"
              @update:model-value="resetLookup"
            />
            <q-input
              v-model="curp"
              outlined
              label="CURP"
              maxlength="18"
              :disable="busy"
              autocomplete="off"
              autocapitalize="characters"
              :rules="[(v) => v.trim().length === 18 || 'La CURP debe tener 18 caracteres']"
              @update:model-value="resetLookup"
            />
          </div>
          <q-btn
            color="primary"
            icon="person_search"
            type="submit"
            label="Verificar antes de continuar"
            no-caps
            :loading="busy" /></q-form></q-card-section
    ></q-card>
    <q-card v-if="lookup && !entered" flat bordered class="intake-card q-mb-lg"
      ><q-card-section
        ><template v-if="lookup.found"
          ><div class="intake-heading">
            <div>
              <p class="eyebrow">REGISTRO EXISTENTE</p>
              <h2>{{ lookup.client?.name || 'Persona registrada en el sistema' }}</h2>
              <p>
                Número de cliente: <strong>{{ lookup.client?.folio }}</strong
                ><br />Sucursal de alta:
                <strong>{{ lookup.client?.branch || 'No disponible' }}</strong> · Estado:
                {{ lookup.client?.status }}
              </p>
            </div>
            <q-badge :color="lookup.active_credits?.length ? 'orange-9' : 'grey-7'">{{
              lookup.active_credits?.length ? 'Con crédito activo' : 'Sin crédito activo registrado'
            }}</q-badge>
          </div>
          <div class="q-mb-md" v-for="credit in lookup.active_credits" :key="credit.id">
            <strong>Crédito {{ credit.folio }} · {{ credit.group }}</strong>
            <p>{{ credit.branch }} · Fecha pactada: {{ credit.pacted_date || 'No disponible' }}</p>
          </div>
          <q-banner class="bg-grey-2 q-mb-md"
            ><template v-if="lookup.last_credit"
              >Último crédito registrado: {{ dateLabel(lookup.last_credit.registered_at) }} ·
              {{ lookup.last_credit.status }} · {{ lookup.last_credit.group }}.<br />La fecha
              corresponde al registro; el modelo actual no conserva la fecha real del
              desembolso.</template
            ><template v-else
              >No hay créditos entregados o pagados registrados para esta persona en esta base de
              datos.</template
            ></q-banner
          >
          <p>{{ lookup.message }}</p>
          <div class="q-gutter-sm">
            <q-btn
              v-if="lookup.can_edit"
              color="primary"
              no-caps
              :label="
                lookup.application?.status === 'en_revision'
                  ? 'Consultar expediente enviado'
                  : 'Sí, abrir para modificar'
              "
              @click="enter"
            /><q-btn flat no-caps label="No, realizar otra búsqueda" @click="resetLookup" />
          </div>
          <p class="text-caption text-grey-7 q-mt-md">
            El resumen corresponde a los datos disponibles en el sistema unificado; no incluye bases
            antiguas aún no migradas.
          </p></template
        ><template v-else
          ><h2>Sin coincidencias</h2>
          <p>
            La CURP tiene una estructura válida y no existe un cliente con esa clave. Puedes iniciar
            su expediente.
          </p>
          <q-btn
            color="primary"
            no-caps
            label="Capturar nuevo expediente"
            @click="enter" /></template></q-card-section
    ></q-card>
    <template v-if="entered"
      ><q-banner class="bg-white q-mb-lg intake-card"
        ><strong>{{ selectedTeam?.name }}</strong> · {{ selectedTeam?.branch?.name }}<br />CURP:
        {{ verifiedCurp }} ·
        {{ readOnly ? 'Expediente enviado · solo consulta' : 'Borrador editable'
        }}<template #action
          ><q-btn
            flat
            color="primary"
            no-caps
            label="Cambiar de persona"
            :disable="busy"
            @click="startOver" /></template
      ></q-banner>
      <div class="capture-layout">
        <nav class="capture-sections" aria-label="Secciones del expediente">
          <button
            v-for="(section, index) in sections"
            :key="section"
            :class="{ active: currentSection === index }"
            :aria-current="currentSection === index ? 'step' : undefined"
            @click="currentSection = index"
          >
            <span>{{ String(index + 1).padStart(2, '0') }}</span
            >{{ section }}
          </button>
        </nav>
        <q-card flat bordered class="intake-card"
          ><q-card-section
            ><p class="eyebrow">SECCIÓN {{ currentSection + 1 }} DE {{ sections.length }}</p>
            <h2>{{ sections[currentSection] }}</h2>
            <p class="text-caption text-grey-7">
              * Obligatorio al enviar a revisión. Puedes guardar una captura incompleta como
              borrador.
            </p>
            <div class="intake-form-grid">
              <template v-for="field in visibleFields" :key="field.name"
                ><FilterSelect
                  v-if="field.options.length"
                  v-model="values[field.name]"
                  outlined
                  stack-label
                  :label="field.label + (field.required ? ' *' : '')"
                  :options="field.options"
                  emit-value
                  map-options
                  :disable="readOnly || busy"
                  :error="!!errorsFor(field.name)"
                  :error-message="errorsFor(field.name)"
                  clearable /><q-input
                  v-else
                  :model-value="displayValue(field.name)"
                  @update:model-value="(v) => (values[field.name] = v === null ? null : String(v))"
                  outlined
                  stack-label
                  :label="field.label + (field.required ? ' *' : '')"
                  :type="
                    field.control === 'date'
                      ? 'date'
                      : field.name === 'Observaciones'
                        ? 'textarea'
                        : 'text'
                  "
                  :readonly="contextFields.includes(field.name) || readOnly"
                  :disable="busy"
                  :inputmode="field.control === 'number' ? 'decimal' : 'text'"
                  :maxlength="field.name === 'Observaciones' ? 10000 : 500"
                  :error="!!errorsFor(field.name)"
                  :error-message="errorsFor(field.name)"
              /></template>
            </div>
            <q-separator class="q-my-lg" />
            <div class="intake-heading q-mt-lg">
              <q-btn
                flat
                no-caps
                label="Anterior"
                :disable="currentSection === 0"
                @click="currentSection--"
              /><q-btn
                flat
                color="primary"
                no-caps
                label="Siguiente sección"
                :disable="currentSection === sections.length - 1"
                @click="currentSection++"
              /></div></q-card-section
          ><q-separator /><q-card-actions align="right" class="q-pa-md"
            ><span class="text-caption text-grey-7 q-mr-auto">{{
              dirty ? 'Hay cambios sin guardar' : 'Sin cambios pendientes'
            }}</span
            ><q-btn
              v-if="!readOnly"
              outline
              color="primary"
              no-caps
              label="Guardar borrador"
              :loading="busy"
              @click="save(false)" /><q-btn
              v-if="!readOnly && canSubmit"
              color="primary"
              no-caps
              label="Enviar a revisión"
              :loading="busy"
              @click="save(true)" /></q-card-actions
        ></q-card></div
    ></template>
  </q-page>
</template>
<script setup lang="ts">
import FilterSelect from '../components/FilterSelect.vue';
import { alerts } from '../services/alerts.service';
import { useAlertFeedback } from '../composables/useAlertFeedback';
import { clientEntryNotice } from '../domain/client-entry';
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute } from 'vue-router';
import { useStaffAuth as useAuthStore } from '../stores/auth';
import { useIntakeStore } from '../stores/intake.store';
import { intakeService, intakeError, type Team, type Lookup } from '../services/intake.service';
import schema from '../../../admin-web/src/domain/intake-fields.json';
import { newUuid } from '../services/uuid';
import GroupSearch from '../components/GroupSearch.vue';
const auth = useAuthStore();
const canSubmit = computed(() => auth.can('intake.submit'));
const store = useIntakeStore(),
  route = useRoute();
const selectedTeam = ref<Team | null>(null),
  curp = ref(''),
  verifiedCurp = ref(''),
  lookup = ref<Lookup | null>(null),
  busy = ref(false),
  entered = ref(false),
  error = ref(''),
  success = ref('');
const values = ref<Record<string, string | null>>({}),
  fieldErrors = ref<Record<string, string[]>>({}),
  currentSection = ref(0),
  applicationId = ref(''),
  version = ref(0),
  clientId = ref<string | null>(null),
  clientVersion = ref<number | null>(null),
  readOnly = ref(false),
  baseline = ref('');
watch(
  () => auth.isAuthenticated,
  (authenticated) => {
    if (!authenticated) {
      entered.value = false;
      lookup.value = null;
      values.value = {};
      curp.value = '';
    }
  },
);
const contextFields = ['city_id', 'team_id', 'promoter_id', 'curp'];
const sections = [...new Set(schema.map((f) => f.section))];
const visibleFields = computed(() =>
  schema.filter(
    (f) =>
      f.section === sections[currentSection.value] && !['ncliente', 'nCredito'].includes(f.name),
  ),
);
const serialized = computed(() => JSON.stringify({ fields: values.value }));
const dirty = computed(
  () => entered.value && !readOnly.value && baseline.value !== serialized.value,
);
const errorsFor = (key: string) =>
  fieldErrors.value[key]?.join(' ') || fieldErrors.value[`fields.${key}`]?.join(' ') || undefined;
const fieldLabel = (key: string) =>
  schema.find((f) => f.name === key.replace('fields.', ''))?.label || key;
const dateLabel = (value: string | null) =>
  value
    ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeZone: 'America/Cancun' }).format(
        new Date(value),
      )
    : 'Fecha no disponible';
function displayValue(key: string) {
  if (key === 'city_id') return selectedTeam.value?.branch?.name ?? '';
  if (key === 'team_id') return selectedTeam.value?.name ?? '';
  if (key === 'promoter_id') return selectedTeam.value?.promoter?.name ?? '';
  return values.value[key] ?? '';
}
function resetLookup() {
  lookup.value = null;
  store.lookup = null;
  success.value = '';
  error.value = '';
  fieldErrors.value = {};
}
async function verify() {
  if (!selectedTeam.value || busy.value) return;
  busy.value = true;
  resetLookup();
  try {
    const normalized = curp.value.trim().toUpperCase();
    lookup.value = await store.verifyCurp(normalized, selectedTeam.value.id);
    verifiedCurp.value = normalized;
    curp.value = normalized;
    await confirmEntry();
  } catch (e) {
    const result = intakeError(e);
    error.value = result.message;
    fieldErrors.value = result.fields;
  } finally {
    busy.value = false;
  }
}
async function enter() {
  if (busy.value) return;
  busy.value = true;
  try {
    await confirmEntry();
  } finally {
    busy.value = false;
  }
}
async function confirmEntry() {
  const result = lookup.value;
  if (!result) return;
  const notice = clientEntryNotice(result);
  if (!notice.allowed) {
    await alerts.info(notice.title, notice.text);
    return;
  }
  const confirmed = await alerts.confirm(notice.title, notice.text, notice.confirmLabel);
  if (confirmed && auth.isAuthenticated && lookup.value === result) openForm();
}
function openForm() {
  if (!lookup.value || !selectedTeam.value || (lookup.value.found && !lookup.value.can_edit))
    return;
  const app = lookup.value.application;
  values.value = Object.fromEntries(schema.map((f) => [f.name, '']));
  Object.assign(values.value, app?.fields ?? lookup.value.profile ?? {});
  Object.assign(values.value, {
    curp: verifiedCurp.value,
    team_id: selectedTeam.value.id,
    city_id: selectedTeam.value.branch_id,
    promoter_id: selectedTeam.value.promoter_id,
  });
  applicationId.value = app?.id ?? newUuid();
  version.value = app?.version ?? 0;
  clientId.value = lookup.value.client?.id ?? null;
  clientVersion.value = lookup.value.client?.version ?? null;
  readOnly.value = !!app && app.status !== 'borrador';
  entered.value = true;
  currentSection.value = 0;
  baseline.value = serialized.value;
}
function goToError(key: string) {
  const field = schema.find((f) => f.name === key.replace('fields.', ''));
  if (field) currentSection.value = sections.indexOf(field.section);
}
async function save(submit: boolean) {
  if (busy.value || !selectedTeam.value || readOnly.value) return;
  busy.value = true;
  if (
    submit &&
    !(await alerts.confirm(
      'Enviar expediente a Gestión',
      'La versión enviada quedará registrada y no podrá sobrescribirse desde captura. ¿Deseas continuar?',
      'Enviar a revisión',
    ))
  ) {
    busy.value = false;
    return;
  }
  if (!auth.isAuthenticated) {
    busy.value = false;
    return;
  }
  error.value = '';
  success.value = '';
  fieldErrors.value = {};
  try {
    const result = await intakeService.saveApplication({
      id: applicationId.value,
      version: version.value,
      team_id: selectedTeam.value.id,
      curp: verifiedCurp.value,
      client_id: clientId.value,
      client_version: clientVersion.value,
      confirm_existing: !!clientId.value,
      submit,
      fields: Object.fromEntries(
        Object.entries(values.value).filter(([key]) => !['ncliente', 'nCredito'].includes(key)),
      ),
    });
    if (!auth.isAuthenticated) return;
    version.value = result.version;
    clientId.value = result.client_id;
    clientVersion.value = result.client!.version;
    values.value = result.fields;
    readOnly.value = result.status !== 'borrador';
    baseline.value = serialized.value;
    success.value = submit
      ? `Cliente ${result.client?.folio ?? ''}. Expediente enviado a revisión.`
      : `Cliente ${result.client?.folio ?? ''}. Borrador guardado; puedes recuperarlo consultando esta CURP.`;
  } catch (e) {
    const result = intakeError(e);
    error.value = result.message;
    fieldErrors.value = result.fields;
    const first = Object.keys(result.fields)[0];
    if (first) goToError(first);
  } finally {
    busy.value = false;
  }
}
async function startOver() {
  if (busy.value) return;
  if (
    dirty.value &&
    !(await alerts.confirm(
      'Cambios sin guardar',
      '¿Deseas descartar los cambios y buscar otra persona?',
      'Descartar cambios',
    ))
  )
    return;
  entered.value = false;
  curp.value = '';
  resetLookup();
}
async function canLeaveCapture() {
  if (!auth.isAuthenticated) return true;
  if (busy.value) return false;
  if (!dirty.value) return true;
  return alerts.confirm(
    'Cambios sin guardar',
    'Guarda tu borrador para conservarlo. ¿Deseas salir y descartar los cambios pendientes?',
    'Salir sin guardar',
  );
}
onBeforeRouteLeave(canLeaveCapture);
onBeforeRouteUpdate(canLeaveCapture);
function beforeUnload(e: BeforeUnloadEvent) {
  if (dirty.value) {
    e.preventDefault();
    e.returnValue = '';
  }
}
onMounted(async () => {
  window.addEventListener('beforeunload', beforeUnload);
  store.lookup = null;
  curp.value = store.pendingCurp;
  store.pendingCurp = '';
  if (typeof route.query.grupo === 'string') {
    try {
      selectedTeam.value = await intakeService.team(route.query.grupo);
    } catch (e) {
      error.value = intakeError(e).message;
    }
  }
});
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnload);
  store.lookup = null;
});

useAlertFeedback(error, success);
</script>
