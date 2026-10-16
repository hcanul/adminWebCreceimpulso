<template>
  <q-page class="intake-page"
    ><q-btn flat icon="arrow_back" label="Grupos" no-caps to="/grupos" class="q-mb-md" /><q-banner
      v-if="error"
      class="bg-red-1 text-negative q-mb-md"
      role="alert"
      >{{ error }}</q-banner
    ><q-linear-progress v-if="loading" indeterminate />
    <template v-if="team"
      ><div class="intake-heading">
        <div>
          <p class="eyebrow">EXPEDIENTE GRUPAL</p>
          <h1>{{ team.name }}</h1>
          <p>
            {{ team.branch?.name }} · {{ team.promoter?.name || 'Promotor pendiente de asignar' }}
          </p>
          <p class="uuid-text">{{ team.id }}</p>
        </div>
        <div class="q-gutter-sm">
          <q-btn outline color="primary" label="Editar grupo" no-caps @click="openEdit" /><q-btn
            color="primary"
            icon="person_add"
            label="Incorporar integrante"
            :disable="!team.promoter_id"
            no-caps
            @click="capture('')"
          />
        </div>
      </div>
      <div class="intake-columns">
        <q-card flat bordered class="intake-card"
          ><q-card-section
            ><h2>Integrantes y comité</h2>
            <p class="text-grey-7">
              Los cargos del grupo se usarán como propuesta para el próximo ciclo.
            </p></q-card-section
          ><q-list separator
            ><q-item v-for="member in team.memberships" :key="member.id" class="q-py-md"
              ><q-item-section
                ><q-item-label>{{
                  member.client
                    ? `${member.client.first_name} ${member.client.last_name}`
                    : 'Cliente archivado'
                }}</q-item-label
                ><q-item-label caption>{{ member.client?.curp }}</q-item-label
                ><q-btn
                  v-if="member.client"
                  flat
                  dense
                  no-caps
                  color="primary"
                  label="Verificar CURP y abrir expediente"
                  class="self-start"
                  @click="capture(member.client.curp)" /></q-item-section
              ><q-item-section side
                ><FilterSelect
                  :model-value="member.role_in_group"
                  :options="roles"
                  emit-value
                  map-options
                  dense
                  outlined
                  style="width: 145px"
                  :disable="saving"
                  aria-label="Cargo en el grupo"
                  @update:model-value="
                    (role) => assign(member.id, role)
                  " /></q-item-section></q-item></q-list
          ><q-card-section v-if="!team.memberships?.length" class="q-pa-xl text-center text-grey-7"
            >El grupo está creado. Incorpora a su primera integrante.</q-card-section
          ></q-card
        >
        <q-card flat bordered class="intake-card"
          ><q-card-section
            ><h2>Preparación del grupo</h2>
            <q-btn
              v-if="auth.can('credit.create')"
              class="q-mb-md"
              color="primary"
              no-caps
              label="Solicitar crédito"
              :to="`/solicitudes-credito/nueva?grupo=${team.id}`"
            />
            <div class="text-h3 text-primary q-my-md">{{ team.memberships?.length ?? 0 }}</div>
            <p>integrantes registradas</p>
            <q-separator />
            <p class="q-mt-md">
              <q-icon
                :name="
                  (team.memberships?.length ?? 0) >= 3 ? 'check_circle' : 'radio_button_unchecked'
                "
              />
              Mínimo inicial: 3 integrantes
            </p>
            <p>
              <q-icon :name="committeeReady ? 'check_circle' : 'radio_button_unchecked'" />
              Presidenta y Tesorera asignadas
            </p>
            <q-banner class="bg-grey-2"
              >La elegibilidad crediticia aún requiere revisión de expedientes, historial y
              condiciones. Estos indicadores no autorizan un crédito.</q-banner
            ></q-card-section
          ></q-card
        >
      </div>
      <q-dialog :allow-focus-outside="alertIsOpen" v-model="edit"
        ><q-card class="intake-dialog" style="width: 450px"
          ><q-form @submit="saveName"
            ><q-card-section
              ><h2>Editar grupo y promotor</h2>
              <q-input
                v-model="name"
                label="Nombre"
                outlined
                :rules="[(v: string | null) => !!v?.trim() || 'Escribe el nombre']"
                maxlength="150"
              />
              <FilterSelect
                v-model="promoterId"
                :options="promoterOptions"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                outlined
                label="Promotor responsable"
                :rules="[(v: string | null) => !!v || 'Selecciona un promotor']"
              />
              <p v-if="!promoterOptions.length" class="text-negative">
                No hay promotores habilitados en esta sucursal. Puedes registrarlos desde el menú
                Promotores.
              </p>
              <p>El UUID y la sucursal se conservan.</p>
              <q-banner v-if="editError" class="bg-red-1 text-negative">{{
                editError
              }}</q-banner></q-card-section
            ><q-card-actions align="right"
              ><q-btn flat label="Cancelar" v-close-popup :disable="saving" /><q-btn
                type="submit"
                color="primary"
                label="Guardar"
                :loading="saving" /></q-card-actions></q-form></q-card
      ></q-dialog> </template
  ></q-page>
</template>
<script setup lang="ts">
import { useAuthStore } from '../stores/auth.store';
const auth = useAuthStore();
import FilterSelect from '../components/FilterSelect.vue';
import { useAlertFeedback } from '../composables/useAlertFeedback';
import { alerts, alertIsOpen } from '../services/alerts.service';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useIntakeStore } from '../stores/intake.store';
import {
  intakeService,
  intakeError,
  type Team,
  type PromoterOption,
} from '../services/intake.service';
const route = useRoute(),
  router = useRouter(),
  store = useIntakeStore();
const team = ref<Team | null>(null),
  loading = ref(false),
  saving = ref(false),
  error = ref(''),
  editError = ref(''),
  edit = ref(false),
  name = ref('');
const promoterId = ref(''),
  promoterOptions = ref<PromoterOption[]>([]);
async function openEdit() {
  editError.value = '';
  try {
    const options = await intakeService.options();
    promoterOptions.value = options.promoters.filter((p) => p.branch_id === team.value?.branch_id);
    promoterId.value = promoterOptions.value.some((p) => p.id === team.value?.promoter_id)
      ? team.value!.promoter_id!
      : '';
    name.value = team.value!.name;
    edit.value = true;
  } catch (e) {
    error.value = intakeError(e).message;
  }
}
const roles = [
  { label: 'Presidenta', value: 'presidenta' },
  { label: 'Tesorera', value: 'tesorera' },
  { label: 'Integrante', value: 'integrante' },
];
const committeeReady = computed(
  () =>
    team.value?.memberships?.some((m) => m.role_in_group === 'presidenta') &&
    team.value.memberships.some((m) => m.role_in_group === 'tesorera'),
);
async function load() {
  loading.value = true;
  try {
    team.value = await intakeService.team(String((route.params as { id: string }).id));
  } catch (e) {
    error.value = intakeError(e).message;
  } finally {
    loading.value = false;
  }
}
async function capture(curp: string) {
  store.pendingCurp = curp;
  await router.push({ path: '/clientes/captura', query: { grupo: team.value!.id } });
}
async function assign(id: string, role: string) {
  saving.value = true;
  error.value = '';
  try {
    await intakeService.committee(team.value!.id, id, role);
    await load();
    if (!error.value) void alerts.success('Cambios guardados correctamente.');
  } catch (e) {
    error.value = intakeError(e).message;
  } finally {
    saving.value = false;
  }
}
async function saveName() {
  saving.value = true;
  editError.value = '';
  try {
    await intakeService.saveTeam(
      { name: name.value, branch_id: team.value!.branch_id, promoter_id: promoterId.value },
      team.value!.id,
    );
    edit.value = false;
    await load();
    if (!error.value) void alerts.success('Cambios guardados correctamente.');
  } catch (e) {
    editError.value = intakeError(e).message;
  } finally {
    saving.value = false;
  }
}
onMounted(load);

useAlertFeedback(error);
useAlertFeedback(editError);
</script>
