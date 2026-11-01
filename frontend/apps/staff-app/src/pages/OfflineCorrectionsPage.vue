<template>
  <main class="page-shell">
    <q-btn flat no-caps label="Volver al inicio" to="/" />
    <h1>Correcciones sin conexión</h1>
    <p>
      Solo expedientes preparados. Los documentos se generan en admin-web. Guarda antes de cerrar
      esta pantalla.
    </p>
    <q-banner v-if="error" role="alert" class="bg-red-1 q-my-md">{{ error }}</q-banner>
    <template v-if="!vault"
      ><p>Selecciona tu espacio y escribe la clave local. No es la contraseña de tu cuenta.</p>
      <FilterSelect
        v-model="space"
        label="Espacio local"
        :options="
          spaces.map((id, i) => ({
            label: `Espacio ${i + 1} · ${id.split('|')[1]?.slice(-8)}`,
            value: id,
          }))
        "
        emit-value
        map-options
        outlined
      />
      <q-input
        v-model="password"
        type="password"
        label="Clave local"
        outlined
        class="q-my-md"
        autocomplete="off"
      />
      <q-btn
        label="Desbloquear"
        color="primary"
        no-caps
        :loading="busy"
        :disable="!space"
        @click="unlock"
      />
      <p v-if="!spaces.length">
        Con conexión, entra a Grupos y prepara el expediente de cada integrante.
      </p></template
    >
    <template v-else
      ><h2>{{ vault.data.name }}</h2>
      <p>
        {{ vault.data.records.filter((x) => x.pending).length }} correcciones pendientes de
        sincronizar.
      </p>
      <div class="row q-gutter-sm">
        <q-btn label="Bloquear" outline no-caps :disable="busy" @click="lock" /><q-btn
          label="Sincronizar pendientes"
          color="primary"
          no-caps
          :loading="busy"
          @click="sync"
        />
      </div>
      <p class="text-caption">
        Para sincronizar inicia sesión con la misma cuenta y servidor. Ante errores o conflictos,
        los datos locales se conservan. No se envía nada a Gestión.
      </p>
      <q-card v-for="record in vault.data.records" :key="record.id" flat bordered class="q-my-md"
        ><q-card-section>
          <h3>{{ record.name }}</h3>
          <p>{{ record.group }} · {{ record.message }}</p>
          <q-btn
            flat
            color="primary"
            no-caps
            label="Corregir información"
            :disable="busy"
            @click="edit(record)"
          />
          <q-btn
            v-if="record.message.startsWith('Conflicto:')"
            flat
            no-caps
            color="primary"
            label="Revisar conflicto con oficina"
            :disable="busy"
            @click="reviewConflict(record)"
          />
          <q-btn
            flat
            no-caps
            label="Eliminar copia local"
            :disable="busy"
            @click="remove(record)"
          /> </q-card-section
      ></q-card>
      <q-dialog v-model="reviewing" persistent>
        <q-card style="width: 850px; max-width: 95vw">
          <q-card-section
            ><h2>Conciliar con oficina</h2>
            <p>
              Elige qué valor conservar en cada diferencia. Esto prepara una nueva corrección local;
              todavía no modifica el servidor.
            </p>
            <q-markup-table flat wrap-cells
              ><thead>
                <tr>
                  <th>Campo</th>
                  <th>En dispositivo</th>
                  <th>En oficina</th>
                  <th>Conservar</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="diff in differences" :key="diff.name">
                  <td>{{ diff.label }}</td>
                  <td>{{ diff.local || 'Sin dato' }}</td>
                  <td>{{ diff.remote || 'Sin dato' }}</td>
                  <td>
                    <q-option-group
                      v-model="choices[diff.name]"
                      :options="[
                        { label: 'Dispositivo', value: 'local' },
                        { label: 'Oficina', value: 'remote' },
                      ]"
                    />
                  </td>
                </tr></tbody
            ></q-markup-table>
            <p v-if="!differences.length">
              No hay diferencias en los campos editables. Puedes actualizar tu copia con la versión
              de oficina.
            </p>
          </q-card-section>
          <q-card-actions align="right"
            ><q-btn flat label="Cancelar" :disable="busy" @click="closeReview" /><q-btn
              color="primary"
              label="Guardar conciliación local"
              :loading="busy"
              :disable="differences.some((d) => !choices[d.name])"
              @click="applyReview"
          /></q-card-actions>
        </q-card>
      </q-dialog>
      <q-dialog v-model="editing" persistent
        ><q-card style="width: 650px; max-width: 95vw"
          ><q-card-section
            ><h2>Corregir información</h2>
            <p>
              CURP y asignaciones no se cambian sin conexión. Los cambios no llegarán a oficina
              hasta sincronizar.
            </p>
            <q-expansion-item
              v-for="section in sections"
              :key="section"
              :label="section"
              group="sections"
              ><div class="q-pa-md q-gutter-md">
                <template
                  v-for="field in fields.filter((f) => f.section === section)"
                  :key="field.name"
                >
                  <FilterSelect
                    v-if="field.options.length"
                    v-model="values[field.name]"
                    :label="field.label"
                    :options="field.options"
                    outlined
                  />
                  <q-input
                    v-else
                    v-model="values[field.name]"
                    :label="field.label"
                    :type="
                      field.control === 'date'
                        ? 'date'
                        : field.control === 'textarea'
                          ? 'textarea'
                          : 'text'
                    "
                    outlined
                    stack-label
                  />
                </template></div
            ></q-expansion-item> </q-card-section
          ><q-card-actions align="right"
            ><q-btn flat label="Cancelar" :disable="busy" @click="cancelEdit" /><q-btn
              color="primary"
              no-caps
              label="Guardar en dispositivo"
              :loading="busy"
              @click="save" /></q-card-actions></q-card
      ></q-dialog>
    </template>
  </main>
</template>
<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, shallowRef } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import axios from 'axios';
import FilterSelect from '../components/FilterSelect.vue';
import schema from '../../../admin-web/src/domain/intake-fields.json';
import {
  localSpaces,
  unlockVault,
  persistVault,
  scopeFor,
  type Vault,
  type OfflineRecord,
} from '../services/offline-vault';
import { useStaffAuth } from '../stores/auth';
import { intakeService, intakeError, type Lookup } from '../services/intake.service';
import { alerts } from '../services/alerts.service';
const auth = useStaffAuth(),
  vault = shallowRef<Vault | null>(null),
  spaces = ref<string[]>([]),
  space = ref(''),
  password = ref(''),
  busy = ref(false),
  error = ref(''),
  editing = ref(false),
  current = ref(''),
  values = ref<Record<string, string | null>>({});
const fields = schema.filter(
  (f) => !['curp', 'team_id', 'city_id', 'promoter_id', 'ncliente', 'nCredito'].includes(f.name),
);
const reviewing = ref(false);
const reviewedRecord = shallowRef<OfflineRecord | null>(null);
const remoteReview = ref<Lookup | null>(null);
const choices = ref<Record<string, string>>({});
const differences = computed(() =>
  fields
    .map((f) => ({
      name: f.name,
      label: f.label,
      local: reviewedRecord.value?.payload.fields[f.name] ?? '',
      remote: remoteReview.value?.application?.fields[f.name] ?? '',
    }))
    .filter((d) => d.local !== d.remote),
);
function closeReview() {
  reviewing.value = false;
  reviewedRecord.value = null;
  remoteReview.value = null;
  choices.value = {};
}
async function reviewConflict(record: OfflineRecord) {
  if (!vault.value || busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await auth.restore();
    if (!auth.user || !auth.canIntake || scopeFor(auth.user) !== vault.value.id)
      throw new Error('Inicia sesión con la cuenta que preparó este espacio.');
    const remote = await intakeService.lookup(record.payload.curp, record.payload.team_id);
    if (
      !remote.can_edit ||
      remote.client?.id !== record.payload.client_id ||
      !remote.client.version ||
      remote.application?.id !== record.id ||
      remote.application.status !== 'borrador'
    )
      throw new Error(
        'El expediente ya no se puede conciliar como borrador. Consulta con oficina; tu copia se conserva.',
      );
    reviewedRecord.value = record;
    remoteReview.value = remote;
    choices.value = {};
    reviewing.value = true;
  } catch (e) {
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
async function applyReview() {
  if (
    !vault.value ||
    !reviewedRecord.value ||
    !remoteReview.value?.application ||
    !remoteReview.value.client?.version ||
    busy.value ||
    differences.value.some((d) => !choices.value[d.name])
  )
    return;
  busy.value = true;
  const record = reviewedRecord.value,
    before = structuredClone(record),
    remote = remoteReview.value;
  try {
    const merged = { ...remote.application!.fields };
    for (const diff of differences.value)
      if (choices.value[diff.name] === 'local')
        merged[diff.name] = record.payload.fields[diff.name] ?? null;
    record.payload = {
      ...record.payload,
      fields: merged,
      version: remote.application!.version,
      client_version: remote.client!.version,
      submit: false,
    };
    record.operationId = crypto.randomUUID();
    record.pending = true;
    record.message = 'Conciliado en dispositivo; pendiente de sincronizar';
    await persistVault(vault.value);
    vault.value = { ...vault.value };
    closeReview();
  } catch (e) {
    Object.assign(record, before);
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
const sections = computed(() => [...new Set(fields.map((f) => f.section))]);
async function unlock() {
  busy.value = true;
  error.value = '';
  try {
    vault.value = await unlockVault(space.value, password.value);
  } catch (e) {
    error.value = message(e);
  } finally {
    password.value = '';
    busy.value = false;
  }
}
function message(e: unknown) {
  return e instanceof Error ? e.message : 'No se pudo completar la operación.';
}
function lock() {
  closeReview();
  vault.value = null;
  values.value = {};
  editing.value = false;
}
function edit(r: OfflineRecord) {
  current.value = r.id;
  values.value = { ...r.payload.fields };
  editing.value = true;
}
async function cancelEdit() {
  if (
    await alerts.confirm(
      'Descartar edición',
      'Los últimos cambios de esta pantalla no se guardarán.',
      'Descartar',
    )
  ) {
    editing.value = false;
    values.value = {};
  }
}
async function save() {
  if (!vault.value || busy.value) return;
  busy.value = true;
  error.value = '';
  const r = vault.value.data.records.find((x) => x.id === current.value)!;
  const before = structuredClone(r);
  try {
    r.payload.fields = { ...values.value };
    r.pending = true;
    r.operationId = crypto.randomUUID();
    r.message = 'Pendiente de sincronizar';
    await persistVault(vault.value);
    editing.value = false;
    values.value = {};
    vault.value = { ...vault.value };
    void alerts.success('Corrección guardada en el dispositivo.');
  } catch (e) {
    Object.assign(r, before);
    error.value = message(e);
    void alerts.error(error.value);
  } finally {
    busy.value = false;
  }
}
async function remove(r: OfflineRecord) {
  if (
    !vault.value ||
    busy.value ||
    !(await alerts.confirm(
      'Eliminar copia local',
      r.pending
        ? 'Se perderán las correcciones pendientes. El registro del servidor se conserva.'
        : 'El registro del servidor se conserva.',
      'Eliminar copia',
    ))
  )
    return;
  busy.value = true;
  const before = vault.value.data.records;
  try {
    vault.value.data.records = before.filter((x) => x.id !== r.id);
    await persistVault(vault.value);
    vault.value = { ...vault.value };
  } catch (e) {
    vault.value.data.records = before;
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
async function sync() {
  if (!vault.value || busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await auth.restore();
    if (!auth.user || scopeFor(auth.user) !== vault.value.id || !auth.canIntake)
      throw new Error(
        'Inicia sesión con la cuenta que preparó estos expedientes y conserva su sucursal y servidor.',
      );
    for (const r of vault.value.data.records.filter((x) => x.pending)) {
      let before: OfflineRecord | undefined;
      try {
        if (!r.operationId) r.operationId = crypto.randomUUID();
        // The operation must be durable before any request, including after a failed local write.
        await persistVault(vault.value);
        before = structuredClone(r);
        const result = await intakeService.syncCorrection(
          { ...r.payload, submit: false },
          r.operationId,
        );
        r.payload = {
          ...r.payload,
          version: result.version,
          client_version: result.client!.version,
          fields: result.fields,
        };
        r.pending = false;
        r.message = 'Sincronizado';
        await persistVault(vault.value);
      } catch (e) {
        if (before) Object.assign(r, before);
        const code = axios.isAxiosError(e) ? e.response?.status : undefined;
        r.message =
          code === 409
            ? 'Conflicto: conserva tus cambios y revisa la versión de oficina antes de corregir nuevamente.'
            : intakeError(e).message;
        await persistVault(vault.value);
        if (!code || [401, 403].includes(code)) break;
        continue;
      }
    }
    vault.value = { ...vault.value };
  } catch (e) {
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
function beforeUnload(e: BeforeUnloadEvent) {
  if (editing.value || reviewing.value || busy.value) {
    e.preventDefault();
    e.returnValue = '';
  }
}
onBeforeRouteLeave(
  async () =>
    !busy.value &&
    (!(editing.value || reviewing.value) ||
      (await alerts.confirm(
        'Cambios sin guardar',
        'Guarda en el dispositivo antes de salir para conservar la edición.',
        'Salir sin guardar',
      ))),
);
onMounted(async () => {
  window.addEventListener('beforeunload', beforeUnload);
  try {
    spaces.value = await localSpaces();
    space.value = spaces.value[0] || '';
  } catch (e) {
    error.value = message(e);
  }
});
onUnmounted(() => {
  window.removeEventListener('beforeunload', beforeUnload);
  lock();
});
</script>
