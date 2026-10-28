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
            flat
            no-caps
            label="Eliminar copia local"
            :disable="busy"
            @click="remove(record)"
          /> </q-card-section
      ></q-card>
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
import { intakeService, intakeError } from '../services/intake.service';
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
      try {
        const result = await intakeService.saveApplication({ ...r.payload, submit: false });
        r.payload = {
          ...r.payload,
          version: result.version,
          client_version: result.client!.version,
          fields: result.fields,
        };
        r.pending = false;
        r.message = 'Sincronizado';
      } catch (e) {
        const code = axios.isAxiosError(e) ? e.response?.status : undefined;
        r.message =
          code === 409
            ? 'Conflicto: conserva tus cambios y revisa la versión de oficina antes de corregir nuevamente.'
            : intakeError(e).message;
        await persistVault(vault.value);
        if (!code || [401, 403].includes(code)) break;
        continue;
      }
      await persistVault(vault.value);
    }
    vault.value = { ...vault.value };
  } catch (e) {
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
function beforeUnload(e: BeforeUnloadEvent) {
  if (editing.value || busy.value) {
    e.preventDefault();
    e.returnValue = '';
  }
}
onBeforeRouteLeave(
  async () =>
    !busy.value &&
    (!editing.value ||
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
