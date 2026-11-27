<template>
  <q-page class="intake-page">
    <div class="intake-heading">
      <div>
        <p class="eyebrow">PASO 2 · SOLICITUD DE CRÉDITO</p>
        <h1>Montos solicitados</h1>
        <p>
          Selecciona el grupo y captura lo que pide cada integrante. Las condiciones se fijarán al
          autorizar.
        </p>
      </div>
      <q-btn flat no-caps icon="arrow_back" label="Solicitudes" to="/solicitudes-credito" />
    </div>
    <q-banner
      v-if="error"
      class="bg-red-1 text-negative q-mb-md"
      style="white-space: pre-line"
      role="alert"
      >{{ error }}</q-banner
    >
    <q-card v-if="!context" flat bordered class="intake-card"
      ><q-card-section>
        <h2>Selecciona el grupo</h2>
        <GroupSearch v-model="team" :disabled="loading" />
        <q-linear-progress v-if="loading" indeterminate /> </q-card-section
    ></q-card>
    <q-form v-else @submit="save">
      <q-card flat bordered class="intake-card q-mb-lg"
        ><q-card-section>
          <h2>{{ context.team.name }}</h2>
          <p>{{ context.team.branch }} · {{ context.team.promoter || 'Promotor pendiente' }}</p>
          <q-btn
            flat
            no-caps
            label="Ver grupo"
            :to="`/grupos/${context.team.id}`"
            :disable="saving"
          />
          <q-btn flat no-caps label="Cambiar grupo" :disable="saving" @click="changeGroup" />
          <q-banner v-if="context.issues.length" class="bg-orange-1 q-mt-md"
            ><div v-for="issue in context.issues" :key="issue">{{ issue }}</div></q-banner
          >
        </q-card-section></q-card
      >
      <q-card flat bordered class="intake-card"
        ><q-card-section>
          <h2>Integrantes y montos</h2>
          <p>
            Los expedientes se revisan previamente de forma manual. Para una clienta nueva captura
            su expediente; para una existente, actualiza sus datos.
          </p>
          <div v-for="member in context.members" :key="member.client_id" class="credit-member">
            <div class="row no-wrap items-start">
              <q-checkbox
                v-model="selected"
                :val="member.client_id"
                :disable="saving || !member.eligible"
                :aria-label="`Incluir a ${member.name}`"
              />
              <div class="col">
                <strong>{{ member.name }}</strong>
                <p class="text-caption">{{ member.folio }} · {{ member.role_in_group }}</p>
                <p v-for="issue in member.issues" :key="issue" class="text-negative">{{ issue }}</p>
              </div>
            </div>
            <q-input
              v-if="selected.includes(member.client_id)"
              v-model="amounts[member.client_id]"
              outlined
              stack-label
              inputmode="decimal"
              :label="`Monto solicitado por ${member.name} (MXN)`"
              :rules="[positiveAmount]"
              :disable="saving"
              maxlength="11"
            />
          </div> </q-card-section
        ><q-separator /><q-card-section>
          <p class="eyebrow">{{ selected.length }} INTEGRANTES</p>
          <h2>Total solicitado: {{ money(total) }}</h2>
          <p>
            Coordinación capturará los montos otorgados, la tasa, el primer vencimiento y las formas
            de pago y desembolso. No se autoriza ni entrega dinero en este paso.
          </p>
          <q-btn
            color="primary"
            no-caps
            label="Enviar a autorización"
            type="submit"
            :loading="saving"
            :disable="!!context.issues.length || selected.length < 3"
          /> </q-card-section
      ></q-card>
    </q-form>
  </q-page>
</template>
<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import GroupSearch from '../components/GroupSearch.vue';
import { intakeService, type Team } from '../services/intake.service';
import { creditService, type CreditContext } from '../services/credit.service';
import { administrationError } from '../services/administration.service';
import { alerts } from '../services/alerts.service';
import { cents, money } from '../domain/credit-calculation';
const route = useRoute(),
  router = useRouter();
const team = ref<Team | null>(null),
  context = ref<CreditContext | null>(null);
const selected = ref<string[]>([]),
  amounts = ref<Record<string, string>>({});
const loading = ref(false),
  saving = ref(false),
  saved = ref(false),
  error = ref('');
const requestId = crypto.randomUUID();
let generation = 0;
const dirty = computed(() => !saved.value && Object.values(amounts.value).some(Boolean));
const positiveAmount = (value: string | undefined) => {
  try {
    return (!!value && cents(value) > 0n) || 'Escribe un monto mayor a cero';
  } catch {
    return 'Usa números con hasta dos decimales, sin comas';
  }
};
const total = computed(() => {
  try {
    return (
      Number(selected.value.reduce((sum, id) => sum + cents(amounts.value[id] || '0'), 0n)) / 100
    ).toFixed(2);
  } catch {
    return '0.00';
  }
});
watch(team, async (value) => {
  if (!value) return;
  const current = ++generation;
  loading.value = true;
  error.value = '';
  try {
    const data = await creditService.context(value.id);
    if (current !== generation) return;
    context.value = data;
    selected.value = data.members.filter((m) => m.eligible).map((m) => m.client_id);
  } catch (e) {
    if (current === generation) error.value = administrationError(e);
  } finally {
    if (current === generation) loading.value = false;
  }
});
async function changeGroup() {
  if (
    dirty.value &&
    !(await alerts.confirm('Cambiar grupo', 'Se descartarán los montos de esta captura.'))
  )
    return;
  generation++;
  context.value = null;
  team.value = null;
  selected.value = [];
  amounts.value = {};
  error.value = '';
}
onBeforeRouteLeave(
  async () =>
    !saving.value &&
    (!dirty.value ||
      (await alerts.confirm(
        'Solicitud sin guardar',
        '¿Deseas salir y descartar los montos?',
        'Salir sin guardar',
      ))),
);
function unload(event: BeforeUnloadEvent) {
  if (dirty.value || saving.value) {
    event.preventDefault();
    event.returnValue = '';
  }
}
async function save() {
  if (saving.value || !context.value) return;
  saving.value = true;
  error.value = '';
  try {
    if (
      selected.value.length < 3 ||
      selected.value.some((id) => positiveAmount(amounts.value[id]) !== true)
    )
      throw Error('Captura montos válidos para al menos tres integrantes.');
    if (
      !(await alerts.confirm(
        'Enviar a autorización',
        `Se conservarán los montos solicitados por ${selected.value.length} integrantes: ${money(total.value)}. Coordinación definirá las condiciones.`,
        'Enviar a autorización',
      ))
    )
      return;
    const result = await creditService.create({
      id: requestId,
      team_id: context.value.team.id,
      participants: selected.value.map((id) => ({
        client_id: id,
        amount_requested: amounts.value[id]!,
      })),
    });
    saved.value = true;
    saving.value = false;
    await router.replace(`/solicitudes-credito/${result.id}`);
    void alerts.success(`Solicitud ${result.folio} enviada a autorización.`);
  } catch (e) {
    error.value = e instanceof Error && !('isAxiosError' in e) ? e.message : administrationError(e);
    void alerts.error(error.value);
  } finally {
    saving.value = false;
  }
}
onMounted(async () => {
  window.addEventListener('beforeunload', unload);
  try {
    if (typeof route.query.grupo === 'string')
      team.value = await intakeService.team(route.query.grupo);
  } catch (e) {
    error.value = administrationError(e);
  }
});
onBeforeUnmount(() => {
  generation++;
  window.removeEventListener('beforeunload', unload);
});
</script>
<style scoped>
.credit-member {
  padding: 18px 0;
  border-bottom: 1px solid #e5e7eb;
}
</style>
