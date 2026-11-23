<template>
  <main class="page-shell">
    <q-btn flat no-caps label="Volver al inicio" to="/" />
    <h1>Trabajo sin conexión</h1>
    <p>
      Altas de grupos e integrantes y correcciones de expedientes preparados. Los documentos se
      generan en admin-web. Guarda antes de cerrar esta pantalla.
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
      <q-input
        v-model="passwordConfirmation"
        type="password"
        outlined
        label="Repite la clave local para un espacio nuevo"
        autocomplete="off"
      />
      <q-btn
        outline
        label="Preparar espacio de trabajo con conexión"
        :loading="busy"
        @click="prepareSpace"
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
      <q-btn
        outline
        label="Actualizar sucursales y promotores con conexión"
        :disable="busy"
        @click="refreshContext"
      />
      <q-card v-if="vault.data.context" flat bordered class="q-pa-md q-my-md">
        <h2>Nuevo grupo sin conexión</h2>
        <q-form @submit="addGroup" class="q-gutter-md">
          <q-input
            v-model="groupName"
            outlined
            label="Nombre del nuevo grupo"
            maxlength="150"
            :disable="busy"
            :rules="[(v) => !!v?.trim() || 'Escribe el nombre']"
          />
          <FilterSelect
            v-model="groupBranch"
            label="Sucursal del nuevo grupo"
            :options="vault.data.context.branches.map((b) => ({ label: b.name, value: b.id }))"
            emit-value
            map-options
            outlined
            :disable="busy"
          />
          <FilterSelect
            v-model="groupPromoter"
            label="Promotor del nuevo grupo"
            :options="
              vault.data.context.promoters
                .filter((p) => p.branch_id === groupBranch)
                .map((p) => ({ label: p.name || p.id, value: p.id }))
            "
            emit-value
            map-options
            outlined
            :disable="busy"
          />
          <q-btn
            type="submit"
            label="Guardar grupo en dispositivo"
            color="primary"
            :loading="busy"
            :disable="!groupBranch || !groupPromoter"
          />
        </q-form>
      </q-card>
      <q-card
        v-for="group in vault.data.groups || []"
        :key="group.id"
        flat
        bordered
        class="q-pa-md q-my-md"
        ><h3>{{ group.name }}</h3>
        <p>{{ group.message }}</p>
        <small>{{ group.id }}</small></q-card
      >
      <p class="text-caption">
        Para sincronizar inicia sesión con la misma cuenta y servidor. Ante errores o conflictos,
        los datos locales se conservan. No se envía nada a Gestión.
      </p>
      <q-card v-if="vault.data.groups?.length" flat bordered class="q-pa-md q-my-md">
        <h2>Nuevo integrante sin conexión</h2>
        <p>
          Primero selecciona el grupo. La identidad y el número de cliente quedarán confirmados al
          sincronizar.
        </p>
        <q-form @submit="addClient" class="q-gutter-md">
          <FilterSelect
            v-model="clientGroup"
            label="Grupo del nuevo integrante"
            :options="vault.data.groups.map((g) => ({ label: g.name, value: g.id }))"
            emit-value
            map-options
            outlined
            :disable="busy"
          />
          <q-input
            v-model="clientCurp"
            outlined
            label="CURP del nuevo integrante"
            maxlength="18"
            :disable="busy"
          />
          <q-input
            v-model="clientFirst"
            outlined
            label="Nombre del nuevo integrante"
            :disable="busy"
          />
          <q-input
            v-model="clientLast"
            outlined
            label="Apellido paterno del nuevo integrante"
            :disable="busy"
          />
          <q-btn
            type="submit"
            label="Guardar integrante en dispositivo"
            color="primary"
            :loading="busy"
          />
        </q-form>
      </q-card>
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
            v-if="
              record.newClientId &&
              record.payload.version === 0 &&
              record.message.startsWith('Conflicto:')
            "
            flat
            label="Verificar CURP en oficina"
            :disable="busy"
            @click="resolveNewClient(record)"
          />
          <q-btn
            v-if="record.payload.version > 0 && record.message.startsWith('Conflicto:')"
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
import { computed, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import {
  createSessionActivity,
  startSessionActivity,
} from '../../../../packages/shared/session-inactivity';
import { getServerAddress } from '../services/server-settings';
import { onBeforeRouteLeave } from 'vue-router';
import axios from 'axios';
import FilterSelect from '../components/FilterSelect.vue';
import schema from '../../../admin-web/src/domain/intake-fields.json';
import {
  localSpaces,
  prepareContext,
  createLocalGroup,
  createLocalClient,
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
const offlineActivity = createSessionActivity('crece.staff.offline', getServerAddress);
const offlineActivityToken = 'offline-vault';
let stopOfflineActivity: (() => void) | undefined;
watch(
  vault,
  (value, previous) => {
    if (value && !previous) offlineActivity.begin(offlineActivityToken);
    if (!value && previous) offlineActivity.end(offlineActivityToken);
  },
  { flush: 'sync' },
);
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
const passwordConfirmation = ref('');
const groupName = ref(''),
  groupBranch = ref(''),
  groupPromoter = ref('');
async function prepareSpace() {
  if (busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await auth.restore();
    if (!auth.user || !auth.canIntake) throw new Error('Inicia sesión con permisos de captura.');
    if (password.value.length < 8)
      throw new Error('Escribe una clave local de al menos ocho caracteres.');
    if (
      !(await localSpaces()).includes(scopeFor(auth.user)) &&
      password.value !== passwordConfirmation.value
    )
      throw new Error(
        'Las claves locales no coinciden. Repite la clave antes de crear el espacio.',
      );
    const prepared = await unlockVault(scopeFor(auth.user), password.value, auth.user);
    await prepareContext(prepared, auth.user);
    vault.value = prepared;
  } catch (e) {
    error.value = message(e);
  } finally {
    password.value = passwordConfirmation.value = '';
    busy.value = false;
  }
}
async function refreshContext() {
  if (!vault.value || busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await auth.restore();
    if (!auth.user || !auth.canIntake) throw new Error('Inicia sesión con permisos de captura.');
    await prepareContext(vault.value, auth.user);
    vault.value = { ...vault.value };
  } catch (e) {
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
const clientGroup = ref(''),
  clientCurp = ref(''),
  clientFirst = ref(''),
  clientLast = ref('');
async function addClient() {
  if (!vault.value || busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await createLocalClient(
      vault.value,
      clientGroup.value,
      clientCurp.value,
      clientFirst.value,
      clientLast.value,
    );
    clientCurp.value = clientFirst.value = clientLast.value = '';
    vault.value = { ...vault.value };
  } catch (e) {
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
async function resolveNewClient(record: OfflineRecord) {
  if (!vault.value || busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await auth.restore();
    if (!auth.user || !auth.canIntake || scopeFor(auth.user) !== vault.value.id)
      throw new Error('Inicia sesión con la cuenta del espacio.');
    const remote = await intakeService.lookup(record.payload.curp, record.payload.team_id);
    if (!remote.found || !remote.can_edit || !remote.client?.version)
      throw new Error(
        'No se puede vincular esta identidad. Consulta con la sucursal; la captura local se conserva.',
      );
    if (remote.application)
      throw new Error(
        'Esta persona ya tiene un expediente en el grupo. Revisa ese expediente con oficina antes de continuar; no se creará otro automáticamente.',
      );
    if (
      !(await alerts.confirm(
        'CURP ya registrada',
        `Cliente: ${remote.client.name}. Sucursal: ${remote.client.branch || 'No disponible'}. Créditos activos: ${remote.active_credits?.length || 0}. Último crédito: ${remote.last_credit?.folio || 'Sin registros'}. Se vinculará el expediente nuevo a esta persona y se aplicarán los datos capturados. Revisa su identidad antes de confirmar.`,
        'Vincular persona existente',
      ))
    )
      return;
    const before = structuredClone(record);
    record.payload.fields = { ...(remote.profile || {}), ...record.payload.fields };
    record.payload.client_id = remote.client.id;
    record.payload.client_version = remote.client.version;
    record.payload.confirm_existing = true;
    record.message = 'Identidad vinculada; pendiente de sincronizar';
    try {
      await persistVault(vault.value);
      vault.value = { ...vault.value };
    } catch (e) {
      Object.assign(record, before);
      throw e;
    }
  } catch (e) {
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
async function addGroup() {
  if (!vault.value || busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await createLocalGroup(vault.value, groupName.value, groupBranch.value, groupPromoter.value);
    groupName.value = '';
    vault.value = { ...vault.value };
  } catch (e) {
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
async function unlock() {
  busy.value = true;
  error.value = '';
  try {
    vault.value = await unlockVault(space.value, password.value);
  } catch (e) {
    error.value = message(e);
  } finally {
    password.value = passwordConfirmation.value = '';
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
    for (const group of (vault.value.data.groups || []).filter((g) => g.pending)) {
      try {
        await persistVault(vault.value);
        await intakeService.createOfflineTeam({
          id: group.id,
          name: group.name,
          branch_id: group.branch_id,
          promoter_id: group.promoter_id,
        });
        group.pending = false;
        group.message = 'Grupo sincronizado';
        try {
          await persistVault(vault.value);
        } catch (e) {
          group.pending = true;
          throw e;
        }
      } catch (e) {
        group.message = intakeError(e).message;
        await persistVault(vault.value);
        throw e;
      }
    }
    for (const r of vault.value.data.records.filter((x) => x.pending)) {
      let before: OfflineRecord | undefined;
      try {
        if (!r.operationId) r.operationId = crypto.randomUUID();
        // The operation must be durable before any request, including after a failed local write.
        await persistVault(vault.value);
        before = structuredClone(r);
        const result =
          r.newClientId && r.payload.version === 0
            ? await intakeService.createOfflineClient(
                { ...r.payload, submit: false },
                r.newClientId,
              )
            : await intakeService.syncCorrection({ ...r.payload, submit: false }, r.operationId);
        r.payload = {
          ...r.payload,
          version: result.version,
          client_id: result.client_id,
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
  stopOfflineActivity = startSessionActivity({
    namespace: 'crece.staff.offline',
    server: getServerAddress,
    readToken: () => Promise.resolve(vault.value ? offlineActivityToken : null),
    heartbeat: () => Promise.resolve(),
    expire: () => {
      lock();
      error.value =
        'El espacio local se bloqueó después de 30 minutos sin actividad. Tus datos guardados se conservan; vuelve a desbloquearlo con tu clave local.';
      return Promise.resolve();
    },
  });
  try {
    spaces.value = await localSpaces();
    space.value = spaces.value[0] || '';
  } catch (e) {
    error.value = message(e);
  }
});
onUnmounted(() => {
  stopOfflineActivity?.();
  window.removeEventListener('beforeunload', beforeUnload);
  lock();
});
</script>
