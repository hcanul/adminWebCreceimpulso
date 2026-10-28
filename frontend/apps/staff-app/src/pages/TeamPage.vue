<template>
  <q-page class="page-shell"
    ><q-btn flat no-caps icon="arrow_back" label="Volver a grupos" to="/grupos" /><q-linear-progress
      v-if="loading"
      indeterminate
    /><q-banner v-if="error" class="bg-red-1 q-my-md"
      >{{ error
      }}<template #action><q-btn flat label="Reintentar" @click="load" /></template></q-banner
    ><template v-if="team"
      ><h1>{{ team.name }}</h1>
      <q-btn
        class="q-mb-md"
        outline
        color="primary"
        no-caps
        label="Editar grupo"
        @click="editing = true"
      />
      <TeamEditor v-model="editing" :team="team" @saved="load" />
      <q-btn
        v-if="team.is_active"
        color="primary"
        no-caps
        icon="person_add"
        label="Capturar cliente"
        :to="{ path: '/clientes/capturar', query: { grupo: team.id } }"
      />
      <p>{{ team.branch?.name }} · {{ team.memberships?.length || 0 }} integrantes</p>
      <p>Promotor: {{ team.promoter?.name || 'Sin asignar' }}</p>
      <q-banner class="bg-grey-2 q-my-md">
        {{
          (team.memberships?.length || 0) >= 3
            ? 'Mínimo de tres integrantes cubierto.'
            : 'Se requieren al menos tres integrantes para preparar un crédito.'
        }}
        {{
          committeeReady
            ? 'Presidenta y Tesorera asignadas.'
            : 'Falta completar el comité: Presidenta y Tesorera.'
        }}
        Estos indicadores no autorizan el crédito; los cargos proponen el comité del próximo ciclo.
      </q-banner>
      <q-card v-for="member in team.memberships" :key="member.id" flat bordered class="q-my-md"
        ><q-card-section
          ><q-chip color="primary" text-color="white">{{ member.role_in_group }}</q-chip>
          <h2>
            {{
              member.client
                ? `${member.client.first_name} ${member.client.last_name}`
                : 'Integrante no disponible'
            }}
          </h2>
          <p>{{ member.client?.folio }} · {{ member.client?.status }}</p>
          <FilterSelect
            :model-value="member.role_in_group"
            :options="groupRoles"
            emit-value
            map-options
            outlined
            :label="`Cargo de ${member.client?.first_name || 'integrante'}`"
            :disable="saving"
            @update:model-value="(role) => assign(member.id, role)" />
          <q-btn
            v-if="member.client"
            class="q-mt-sm"
            flat
            no-caps
            color="primary"
            label="Verificar CURP y abrir expediente"
            @click="capture(member.client.curp)" />
          <q-btn
            v-if="member.client"
            flat
            no-caps
            color="primary"
            label="Preparar edición sin conexión"
            :disable="saving"
            @click="prepare(member.client.curp)" /></q-card-section></q-card
      ><q-banner v-if="!team.memberships?.length" class="bg-grey-2"
        >Este grupo todavía no tiene integrantes registrados.</q-banner
      ></template
    ></q-page
  >
</template>
<script setup lang="ts">
import Swal from 'sweetalert2';
import { useStaffAuth } from '../stores/auth';
import { scopeFor, localSpaces, unlockVault, prepareRecord } from '../services/offline-vault';
import TeamEditor from '../components/TeamEditor.vue';
import FilterSelect from '../components/FilterSelect.vue';
import { intakeService, intakeError } from '../services/intake.service';
import { alerts } from '../services/alerts.service';
import { useIntakeStore } from '../stores/intake.store';
import { ref, watch, computed, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { teamsService, type Team } from '../services/teams.service';
import { errorMessage } from '../services/http';
const auth = useStaffAuth();
const editing = ref(false),
  saving = ref(false),
  router = useRouter(),
  store = useIntakeStore();
const route = useRoute(),
  team = ref<Team | null>(null),
  error = ref(''),
  loading = ref(false);
async function prepare(curp: string) {
  if (saving.value || !team.value) return;
  saving.value = true;
  try {
    await auth.restore();
    if (!auth.user || !auth.canIntake) throw new Error('Necesitas una sesión autorizada.');
    const owner = auth.user,
      id = scopeFor(owner),
      existing = (await localSpaces()).includes(id);
    const answer = await Swal.fire({
      title: existing ? 'Clave local' : 'Crear clave local',
      text: 'Protege las copias del dispositivo con al menos ocho caracteres. No se guarda tu contraseña de acceso. Si olvidas esta clave no podremos recuperar las correcciones locales.',
      input: 'password',
      inputAttributes: { autocomplete: 'new-password', minlength: '8' },
      showCancelButton: true,
      confirmButtonText: 'Continuar',
      cancelButtonText: 'Cancelar',
      inputValidator: (v) => (v.length >= 8 ? undefined : 'Escribe al menos ocho caracteres.'),
    });
    if (!answer.isConfirmed) return;
    const passphrase = String(answer.value);
    if (!existing) {
      const confirmation = await Swal.fire({
        title: 'Confirma la clave local',
        input: 'password',
        showCancelButton: true,
        confirmButtonText: 'Preparar',
        cancelButtonText: 'Cancelar',
        inputValidator: (v) => (v === passphrase ? undefined : 'Las claves no coinciden.'),
      });
      if (!confirmation.isConfirmed) return;
    }
    const vault = await unlockVault(id, passphrase, owner);
    if (auth.user?.id !== owner.id || scopeFor(owner) !== id) throw new Error('La sesión cambió.');
    await prepareRecord(vault, team.value, curp);
    await alerts.success('Expediente preparado. Ábrelo en Correcciones sin conexión.');
  } catch (e) {
    await alerts.error(e instanceof Error ? e.message : 'No se pudo preparar el expediente.');
  } finally {
    saving.value = false;
  }
}
const groupRoles = [
  { label: 'Presidenta', value: 'presidenta' },
  { label: 'Tesorera', value: 'tesorera' },
  { label: 'Integrante', value: 'integrante' },
];
const committeeReady = computed(
  () =>
    team.value?.memberships?.some((m) => m.role_in_group === 'presidenta') &&
    team.value?.memberships?.some((m) => m.role_in_group === 'tesorera'),
);
async function capture(curp: string) {
  store.pendingCurp = curp;
  await router.push({ path: '/clientes/capturar', query: { grupo: team.value!.id } });
}
async function assign(id: string, role: string) {
  if (saving.value || !team.value) return;
  saving.value = true;
  try {
    await intakeService.committee(team.value.id, id, role);
    await load();
    void alerts.success('Cargo actualizado.');
  } catch (e) {
    void alerts.error(intakeError(e).message);
  } finally {
    saving.value = false;
  }
}
let request = 0;
onUnmounted(() => {
  request++;
});
async function load() {
  const seq = ++request;
  loading.value = true;
  error.value = '';
  team.value = null;
  try {
    const result = await teamsService.get(String(route.params.id));
    if (seq === request) team.value = result;
  } catch (e) {
    if (seq === request) error.value = errorMessage(e);
  } finally {
    if (seq === request) loading.value = false;
  }
}
watch(() => route.params.id, load, { immediate: true });
</script>
