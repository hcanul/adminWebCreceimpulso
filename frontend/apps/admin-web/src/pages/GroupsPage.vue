<template>
  <q-page class="intake-page"
    ><div class="intake-heading">
      <div>
        <p class="eyebrow">PRIMERO, EL GRUPO</p>
        <h1>Grupos solidarios</h1>
        <p>Crea el grupo y después incorpora sus integrantes mediante su expediente de cliente.</p>
      </div>
      <q-btn color="primary" icon="add" label="Crear grupo" no-caps @click="openCreate" />
    </div>
    <q-btn
      v-if="canRegister"
      outline
      color="primary"
      no-caps
      icon="badge"
      label="Administrar promotores"
      class="q-mb-md"
      to="/promotores"
    />
    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-md" role="alert">{{
      error
    }}</q-banner>
    <q-card flat bordered class="intake-card"
      ><q-card-section
        ><q-input
          v-model="search"
          outlined
          dense
          label="Buscar por nombre"
          debounce="400"
          @update:model-value="searchTeams"
          ><template #prepend><q-icon name="search" /></template></q-input
      ></q-card-section>
      <q-linear-progress v-if="loading" indeterminate color="primary" />
      <q-list separator
        ><q-item
          v-for="team in store.teams"
          :key="team.id"
          clickable
          :to="`/grupos/${team.id}`"
          class="q-py-md"
          ><q-item-section avatar
            ><q-avatar color="grey-2" text-color="primary" icon="diversity_3" /></q-item-section
          ><q-item-section
            ><q-item-label class="text-weight-bold">{{ team.name }}</q-item-label
            ><q-item-label caption
              >{{ team.branch?.name }} ·
              {{ team.promoter?.name || 'Promotor pendiente de asignar' }}</q-item-label
            ><q-item-label caption class="uuid-text"
              >ID: {{ team.id }}</q-item-label
            ></q-item-section
          ><q-item-section side
            >{{ team.memberships_count ?? 0 }} integrantes<q-icon
              name="chevron_right" /></q-item-section></q-item
      ></q-list>
      <q-card-section v-if="!loading && !store.teams.length" class="text-center q-pa-xl text-grey-7"
        >No hay grupos que coincidan. Comienza creando uno.</q-card-section
      ><q-card-actions align="center"
        ><q-pagination
          v-model="page"
          :max="store.teamPages"
          :max-pages="6"
          @update:model-value="load" /></q-card-actions
    ></q-card>
    <q-dialog :allow-focus-outside="alertIsOpen" v-model="dialog" persistent
      ><q-card class="intake-dialog intake-card" style="width: 540px; max-width: 95vw"
        ><q-form @submit="save"
          ><q-card-section
            ><h2>Nuevo grupo</h2>
            <p class="text-grey-7">
              Los nombres pueden repetirse. Cada grupo tendrá un UUID propio.
            </p>
            <q-input
              v-model="form.name"
              outlined
              label="Nombre del grupo"
              :rules="[required]"
              maxlength="150"
            /><FilterSelect
              v-model="form.branch_id"
              outlined
              label="Sucursal"
              :options="options.branches"
              option-label="name"
              option-value="id"
              emit-value
              map-options
              :rules="[required]"
              @update:model-value="selectOwnPromoter"
            /><FilterSelect
              v-model="form.promoter_id"
              outlined
              label="Promotor responsable"
              :options="promoters"
              option-label="name"
              option-value="id"
              emit-value
              map-options
              :rules="[required]"
            />
            <p v-if="form.branch_id && !promoters.length" class="text-negative">
              No hay promotores habilitados en esta sucursal. Registra un usuario con rol Promotor
              antes de continuar.
            </p>
            <q-banner v-if="dialogError" class="bg-red-1 text-negative" role="alert">{{
              dialogError
            }}</q-banner>
            <p v-if="!options.branches.length" class="text-negative">
              No tienes una sucursal activa asignada. Solicita su configuración antes de crear el
              grupo.
            </p></q-card-section
          ><q-card-actions align="right"
            ><q-btn flat label="Cancelar" :disable="saving" @click="dialog = false" /><q-btn
              type="submit"
              color="primary"
              label="Crear grupo"
              no-caps
              :loading="saving" /></q-card-actions></q-form></q-card
    ></q-dialog>
  </q-page>
</template>
<script setup lang="ts">
import FilterSelect from '../components/FilterSelect.vue';
import { useAlertFeedback } from '../composables/useAlertFeedback';
import { alerts, alertIsOpen } from '../services/alerts.service';
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useIntakeStore } from '../stores/intake.store';
import { useAuthStore } from '../stores/auth.store';
import {
  intakeService,
  intakeError,
  type BranchOption,
  type PromoterOption,
} from '../services/intake.service';
const store = useIntakeStore();
const auth = useAuthStore();
const router = useRouter();
const loading = ref(false),
  saving = ref(false),
  dialog = ref(false),
  error = ref(''),
  dialogError = ref(''),
  search = ref(''),
  page = ref(1);
const options = reactive<{
  branches: BranchOption[];
  promoters: PromoterOption[];
  promoter_candidates: PromoterOption[];
}>({
  branches: [],
  promoters: [],
  promoter_candidates: [],
});

const canRegister = computed(() =>
  auth.roles.some((role) => ['Super Admin', 'Gerente General'].includes(role)),
);
const form = reactive({ name: '', branch_id: '', promoter_id: '' });
const promoters = computed(() => options.promoters.filter((p) => p.branch_id === form.branch_id));
function selectOwnPromoter() {
  form.promoter_id = auth.roles.includes('Promotor')
    ? (promoters.value.find((p) => p.id === auth.user?.id)?.id ?? '')
    : '';
}
const required = (v: string) => !!v?.trim() || 'Este campo es obligatorio';
let request = 0;
async function load() {
  const current = ++request;
  loading.value = true;
  error.value = '';
  try {
    const result = await intakeService.teams(page.value, search.value);
    if (current === request) {
      store.teams = result.data;
      store.teamPages = result.last_page;
    }
  } catch (e) {
    if (current === request) error.value = intakeError(e).message;
  } finally {
    if (current === request) loading.value = false;
  }
}
function searchTeams() {
  page.value = 1;
  void load();
}
async function openCreate() {
  dialogError.value = '';
  try {
    Object.assign(options, await intakeService.options());
    Object.assign(form, {
      name: '',
      branch_id: options.branches.length === 1 ? options.branches[0]!.id : '',
      promoter_id: '',
    });
    selectOwnPromoter();
    dialog.value = true;
  } catch (e) {
    error.value = intakeError(e).message;
  }
}
async function save() {
  saving.value = true;
  dialogError.value = '';
  try {
    const team = await intakeService.saveTeam(form);
    dialog.value = false;
    void alerts.success('Grupo creado correctamente. Ahora puedes incorporar integrantes.');
    await router.push(`/grupos/${team.id}`);
  } catch (e) {
    const result = intakeError(e);
    dialogError.value = Object.values(result.fields).flat().join(' ') || result.message;
  } finally {
    saving.value = false;
  }
}
onMounted(load);

useAlertFeedback(error);
useAlertFeedback(dialogError);
</script>
