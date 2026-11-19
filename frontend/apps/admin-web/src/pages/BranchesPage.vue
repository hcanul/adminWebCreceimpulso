<template>
  <q-page class="q-pa-md branches-page">
    <header class="row items-start justify-between q-mb-lg">
      <div>
        <p class="eyebrow">ADMINISTRACIÓN OPERATIVA</p>
        <h1>Sucursales</h1>
        <p>Catálogo unificado para clientes, grupos y créditos.</p>
      </div>
      <q-btn color="primary" icon="add_business" no-caps label="Nueva sucursal" @click="open()" />
    </header>
    <q-form class="filters q-mb-lg" @submit="apply"
      ><q-input
        v-model="search"
        outlined
        clearable
        label="Buscar nombre, localidad o código"
        maxlength="100" /><FilterSelect
        v-model="status"
        outlined
        label="Estado"
        emit-value
        map-options
        :options="[
          { label: 'Todas', value: 'all' },
          { label: 'Activas', value: 'active' },
          { label: 'Inactivas', value: 'inactive' },
        ]" /><q-btn type="submit" color="primary" label="Buscar" :loading="loading"
    /></q-form>
    <q-linear-progress v-if="loading" indeterminate /><q-banner
      v-if="error"
      class="bg-red-1 text-negative"
      role="alert"
      >{{ error }}<template #action><q-btn flat label="Reintentar" @click="load" /></template
    ></q-banner>
    <template v-if="data"
      ><q-banner v-if="!data.total" class="bg-grey-2"
        >No hay sucursales para esta búsqueda.</q-banner
      >
      <div class="branch-grid">
        <q-card v-for="b in data.data" :key="b.id" flat bordered
          ><q-card-section
            ><div class="row items-center justify-between">
              <q-badge color="primary">{{ b.folio_code }}</q-badge
              ><q-badge :color="b.is_active ? 'positive' : 'grey'">{{
                b.is_active ? 'Activa' : 'Inactiva'
              }}</q-badge>
            </div>
            <h2>{{ b.name }}</h2>
            <p>{{ b.city || 'Localidad pendiente' }}</p>
            <p class="contact">
              {{ b.address || 'Sin domicilio operativo' }}<br />{{ b.phone || 'Sin teléfono'
              }}<br />{{ b.email || 'Sin correo' }}
            </p>
            <q-btn
              flat
              no-caps
              color="primary"
              icon="edit"
              label="Editar sucursal"
              @click="open(b)" /><q-btn
              v-if="auth.roles.includes('Super Admin')"
              flat
              no-caps
              color="primary"
              label="Datos de contrato y pagaré"
              to="/administracion/datos-documentales" /></q-card-section
        ></q-card>
      </div>
      <div class="row justify-between items-center q-mt-lg">
        <span>{{ data.total }} sucursales · {{ page }} / {{ data.last_page }}</span>
        <div>
          <q-btn flat label="Anterior" :disable="page <= 1" @click="changePage(-1)" /><q-btn
            flat
            label="Siguiente"
            :disable="page >= data.last_page"
            @click="changePage(1)"
          />
        </div></div
    ></template>
    <q-dialog v-model="dialog" persistent :allow-focus-outside="alertIsOpen"
      ><q-card class="branch-dialog"
        ><q-form @submit="save"
          ><q-card-section
            ><h2>{{ editing ? 'Editar sucursal' : 'Nueva sucursal' }}</h2>
            <p v-if="editing">Código {{ editing.folio_code }} · UUID {{ editing.id }}</p>
            <p v-else>El código de folios y UUID se asignan al guardar. El código es permanente.</p>
            <q-input
              v-model="form.name"
              outlined
              stack-label
              label="Nombre de la sucursal"
              maxlength="255"
              :disable="busy || !!pending"
              :rules="[(v) => !!v.trim() || 'Escribe el nombre']"
            /><q-input
              v-model="form.city"
              outlined
              stack-label
              label="Localidad"
              maxlength="255"
              :disable="busy || !!pending"
            /><q-input
              v-model="form.phone"
              outlined
              stack-label
              label="Teléfono"
              maxlength="40"
              :disable="busy || !!pending"
            /><q-input
              v-model="form.email"
              outlined
              stack-label
              label="Correo de contacto"
              type="email"
              maxlength="254"
              :disable="busy || !!pending"
            /><q-input
              v-model="form.address"
              outlined
              stack-label
              label="Domicilio operativo"
              type="textarea"
              maxlength="2000"
              :disable="busy || !!pending"
            /><q-toggle
              v-model="form.is_active"
              label="Sucursal activa"
              :disable="busy || !!pending"
            /><q-banner class="bg-grey-2"
              >No se elimina el historial. Para desactivar, primero deben resolverse las solicitudes
              y créditos abiertos y reasignarse o desactivarse sus usuarios activos. El domicilio
              legal y los firmantes se configuran en Datos documentales.</q-banner
            ><q-banner v-if="pending && !busy" class="bg-amber-1 q-mt-sm"
              >La respuesta no se confirmó. Reintenta el mismo guardado antes de volver a
              capturar.</q-banner
            ><q-banner v-if="saveError" class="bg-red-1 text-negative q-mt-sm" role="alert">{{
              saveError
            }}</q-banner></q-card-section
          ><q-card-actions align="right"
            ><q-btn
              flat
              label="Cancelar"
              :disable="busy || !!pending"
              @click="dialog = false" /><q-btn
              color="primary"
              type="submit"
              :label="pending ? 'Reintentar guardado' : 'Guardar sucursal'"
              :loading="busy" /></q-card-actions></q-form></q-card
    ></q-dialog>
  </q-page>
</template>
<script setup lang="ts">
import { computed, reactive, ref, watch, onBeforeUnmount } from 'vue';
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import { storeToRefs } from 'pinia';
import { isAxiosError } from 'axios';
import FilterSelect from '../components/FilterSelect.vue';
import {
  branchesService,
  type BranchRecord,
  type BranchCommand,
} from '../services/branches.service';
import { useBranchesStore } from '../stores/branches.store';
import { useAuthStore } from '../stores/auth.store';
import { alerts, alertIsOpen } from '../services/alerts.service';
import { administrationError } from '../services/administration.service';
const route = useRoute(),
  router = useRouter(),
  store = useBranchesStore(),
  auth = useAuthStore();
const { data, loading, error } = storeToRefs(store);
const search = ref(''),
  status = ref('all'),
  dialog = ref(false),
  busy = ref(false),
  saveError = ref(''),
  editing = ref<BranchRecord | null>(null),
  pending = ref<BranchCommand | null>(null);
let creationId = '';
const form = reactive({ name: '', city: '', phone: '', email: '', address: '', is_active: true });
const page = computed(() => Math.max(1, Math.floor(Number(route.query.page) || 1)));
function load() {
  return store.load({
    search: typeof route.query.search === 'string' ? route.query.search : '',
    status: typeof route.query.status === 'string' ? route.query.status : 'all',
    page: page.value,
  });
}
function apply() {
  const target = { query: { search: search.value || undefined, status: status.value, page: '1' } };
  if (router.resolve(target).fullPath === route.fullPath) void load();
  else void router.replace(target);
}
function changePage(delta: number) {
  void router.replace({ query: { ...route.query, page: String(page.value + delta) } });
}
function open(record?: BranchRecord) {
  editing.value = record ?? null;
  creationId = crypto.randomUUID();
  pending.value = null;
  saveError.value = '';
  Object.assign(form, {
    name: record?.name ?? '',
    city: record?.city ?? '',
    phone: record?.phone ?? '',
    email: record?.email ?? '',
    address: record?.address ?? '',
    is_active: record?.is_active ?? true,
  });
  dialog.value = true;
}
async function save() {
  if (busy.value) return;
  busy.value = true;
  saveError.value = '';
  const fields = {
    name: form.name.trim(),
    city: form.city.trim() || null,
    phone: form.phone.trim() || null,
    email: form.email.trim() || null,
    address: form.address.trim() || null,
    is_active: form.is_active,
  };
  const command = pending.value ?? {
    ...fields,
    ...(editing.value ? { version: editing.value.management_version } : { id: creationId }),
  };
  pending.value = command;
  try {
    await branchesService.save(command, editing.value?.id ?? null);
    pending.value = null;
    dialog.value = false;
    await load();
    await alerts.success('Sucursal guardada.');
  } catch (e) {
    saveError.value = administrationError(e);
    if (
      isAxiosError(e) &&
      e.response &&
      e.response.status >= 400 &&
      e.response.status < 500 &&
      e.response.status !== 429
    )
      pending.value = null;
    await alerts.error(saveError.value);
  } finally {
    busy.value = false;
  }
}
onBeforeRouteLeave(
  async () =>
    !busy.value &&
    (!dialog.value ||
      (await alerts.confirm(
        '¿Salir de Sucursales?',
        'Hay un formulario abierto. Si un guardado no se confirmó, revisa la lista antes de volver a capturarlo.',
      ))),
);
watch(
  () => route.fullPath,
  () => {
    search.value = typeof route.query.search === 'string' ? route.query.search : '';
    status.value = typeof route.query.status === 'string' ? route.query.status : 'all';
    void load();
  },
  { immediate: true },
);
onBeforeUnmount(() => store.clear());
</script>
<style scoped>
.branches-page {
  max-width: 1400px;
  margin: auto;
}
h1 {
  font-size: 1.8rem;
  line-height: 1.3;
  font-weight: 700;
  margin: 0 0 10px;
}
h2 {
  font-size: 1.25rem;
  line-height: 1.4;
  font-weight: 700;
}
.filters {
  display: grid;
  grid-template-columns: 2fr 1fr auto;
  gap: 14px;
}
.branch-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}
.contact {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.branch-dialog {
  width: 650px;
  max-width: 95vw;
}
.branch-dialog .q-input {
  margin-bottom: 14px;
}
@media (max-width: 750px) {
  .branch-grid,
  .filters {
    grid-template-columns: 1fr;
  }
}
</style>
