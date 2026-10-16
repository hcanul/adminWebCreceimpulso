<template>
  <q-page class="intake-page"
    ><div class="intake-heading">
      <div>
        <p class="eyebrow">RESPONSABLES DE CAMPO</p>
        <h1>Promotores</h1>
        <p>Registro de usuarios con rol Promotor para asignarles grupos en su sucursal.</p>
      </div>
      <q-btn
        v-if="canRegister"
        color="primary"
        no-caps
        icon="person_add"
        label="Vincular cuenta existente"
        :disable="loading"
        @click="openRegistration"
      />
    </div>
    <q-banner v-if="auth.roles.includes('Super Admin')" rounded class="bg-grey-2 q-mb-md"
      >Crea la cuenta y asígnale el rol Promotor: su registro será automático.
      <template #action
        ><q-btn
          flat
          color="primary"
          no-caps
          label="Crear usuario"
          to="/administracion/usuarios?crear=promotor" /></template
    ></q-banner>
    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-md" role="alert">{{ error }}</q-banner
    ><q-card flat bordered class="intake-card"
      ><q-linear-progress v-if="loading" indeterminate /><q-list separator
        ><q-item v-for="promoter in options.promoters" :key="promoter.id" class="q-py-md"
          ><q-item-section avatar
            ><q-avatar icon="badge" color="grey-2" text-color="primary" /></q-item-section
          ><q-item-section
            ><q-item-label>{{ promoter.name }}</q-item-label
            ><q-item-label caption>{{ branchName(promoter.branch_id) }}</q-item-label
            ><q-item-label caption class="uuid-text">{{
              promoter.id
            }}</q-item-label></q-item-section
          ><q-item-section side
            ><q-badge color="primary">Habilitado</q-badge></q-item-section
          ></q-item
        ></q-list
      ><q-card-section
        v-if="!loading && !options.promoters.length"
        class="text-center q-pa-xl text-grey-7"
        >No hay promotores habilitados en tu ámbito. Para registrar uno debe existir una cuenta
        activa con rol Promotor y sucursal asignada.</q-card-section
      ></q-card
    ><q-dialog v-model="dialog" :allow-focus-outside="alertIsOpen" persistent
      ><q-card class="intake-dialog" style="width: 540px; max-width: 95vw"
        ><q-form @submit="save"
          ><q-card-section
            ><h2>Vincular cuenta existente</h2>
            <p>El registro vincula una cuenta existente. No crea usuarios ni modifica sus roles.</p>
            <FilterSelect
              v-model="userId"
              :options="candidateOptions"
              :disable="!candidateOptions.length"
              stack-label
              :display-value="userId ? undefined : 'Selecciona una cuenta'"
              option-label="label"
              option-value="id"
              emit-value
              map-options
              outlined
              label="Usuario con rol Promotor"
              :rules="[(v: string | null) => !!v || 'Selecciona un usuario']"
            />
            <p v-if="!options.promoter_candidates.length">
              No hay cuentas pendientes de registro con ese rol y una sucursal asignada.
            </p></q-card-section
          ><q-card-actions align="right"
            ><q-btn flat label="Cancelar" :disable="saving" @click="dialog = false" /><q-btn
              color="primary"
              no-caps
              type="submit"
              label="Registrar promotor"
              :loading="saving"
              :disable="!userId" /></q-card-actions></q-form></q-card></q-dialog
  ></q-page>
</template>
<script setup lang="ts">
import FilterSelect from '../components/FilterSelect.vue';
import { computed, onMounted, reactive, ref } from 'vue';
import { useAuthStore } from '../stores/auth.store';
import {
  intakeService,
  intakeError,
  type BranchOption,
  type PromoterOption,
} from '../services/intake.service';
import { alerts, alertIsOpen } from '../services/alerts.service';
import { useAlertFeedback } from '../composables/useAlertFeedback';
const auth = useAuthStore();
const options = reactive<{
  branches: BranchOption[];
  promoters: PromoterOption[];
  promoter_candidates: PromoterOption[];
}>({ branches: [], promoters: [], promoter_candidates: [] });
const loading = ref(false),
  saving = ref(false),
  dialog = ref(false),
  userId = ref(''),
  error = ref('');
const canRegister = computed(() => auth.can('promoters.register'));
const branchName = (id: string | null) =>
  options.branches.find((b) => b.id === id)?.name ?? 'Sucursal no disponible';
const candidateOptions = computed(() =>
  options.promoter_candidates.map((user) => ({
    ...user,
    label: `${user.name} · ${branchName(user.branch_id)}`,
  })),
);
async function load() {
  loading.value = true;
  error.value = '';
  try {
    Object.assign(options, await intakeService.options());
  } catch (e) {
    error.value = intakeError(e).message;
  } finally {
    loading.value = false;
  }
}
async function openRegistration() {
  await load();
  if (error.value) return;
  userId.value = '';
  dialog.value = true;
}
async function save() {
  if (saving.value) return;
  saving.value = true;
  try {
    await intakeService.registerPromoter(userId.value);
    dialog.value = false;
    await load();
    void alerts.success('Promotor registrado. Ya puede recibir grupos en su sucursal.');
  } catch (e) {
    void alerts.error(intakeError(e).message);
  } finally {
    saving.value = false;
  }
}
useAlertFeedback(error);
onMounted(load);
</script>
