<template>
  <q-page class="intake-page"
    ><div class="intake-heading">
      <div>
        <p class="eyebrow">NUEVA SOLICITUD · CRÉDITO GRUPAL</p>
        <h1>Preparar solicitud</h1>
        <p>El gerente define el límite; cada integrante solicita su propio monto.</p>
      </div>
      <q-btn flat no-caps icon="arrow_back" label="Solicitudes" to="/solicitudes-credito" />
    </div>
    <q-banner
      v-if="error"
      class="bg-red-1 text-negative q-mb-md"
      role="alert"
      style="white-space: pre-line"
      >{{ error }}</q-banner
    >
    <q-card v-if="!context" flat bordered class="intake-card"
      ><q-card-section
        ><h2>1. Busca el grupo</h2>
        <GroupSearch v-model="team" :disabled="loading" /><q-linear-progress
          v-if="loading"
          indeterminate /></q-card-section
    ></q-card>
    <template v-else
      ><q-card flat bordered class="intake-card q-mb-lg"
        ><q-card-section class="row items-center justify-between q-gutter-md"
          ><div>
            <h2>{{ context.team.name }}</h2>
            <p class="q-mb-none">
              {{ context.team.branch }} · {{ context.team.promoter || 'Promotor pendiente' }}
            </p>
          </div>
          <div>
            <q-btn flat no-caps label="Ver grupo" :to="`/grupos/${context.team.id}`" /><q-btn
              flat
              no-caps
              label="Cambiar grupo"
              :disable="saving"
              @click="changeGroup"
            /></div></q-card-section
      ></q-card>
      <q-banner v-if="context.issues.length" class="bg-orange-1 text-orange-10 q-mb-md"
        ><div v-for="issue in context.issues" :key="issue">{{ issue }}</div></q-banner
      >
      <q-form @submit="save"
        ><div class="credit-columns">
          <div>
            <q-card flat bordered class="intake-card q-mb-lg"
              ><q-card-section
                ><h2>2. Condiciones del grupo</h2>
                <div class="intake-form-grid">
                  <FilterSelect
                    v-model="rateId"
                    :options="rates"
                    :fetch-options="searchRates"
                    option-label="name"
                    option-value="id"
                    emit-value
                    map-options
                    outlined
                    label="Tasa común *"
                    :disable="saving"
                    :rules="[(v: string) => !!v || 'Selecciona una tasa']"
                  /><q-input
                    v-model="limit"
                    outlined
                    stack-label
                    inputmode="decimal"
                    label="Límite por integrante (MXN) *"
                    :disable="saving"
                    :rules="[positiveAmount]"
                  />
                </div>
                <p v-if="rate" class="text-caption">
                  {{ rate.name }}: {{ money(rate.rate_factor) }} semanales por cada $1,000
                  solicitados · 16 semanas.
                  {{ rate.min_amount ? `Mínimo de catálogo: ${money(rate.min_amount)}.` : '' }}
                  {{ rate.max_amount ? `Máximo de catálogo: ${money(rate.max_amount)}.` : '' }}
                </p>
                <q-banner v-if="!rates.length" class="bg-orange-1"
                  >No hay tasas disponibles. Solicita su configuración antes de continuar.</q-banner
                ></q-card-section
              ></q-card
            >
            <q-card flat bordered class="intake-card"
              ><q-card-section
                ><h2>3. Integrantes y montos</h2>
                <p>
                  Al menos tres personas, con Presidenta y Tesorera. Los expedientes deben haberse
                  enviado a Gestión.
                </p>
                <div
                  v-for="member in context.members"
                  :key="member.client_id"
                  class="credit-member"
                >
                  <div class="row items-start no-wrap">
                    <q-checkbox
                      v-model="selected"
                      :val="member.client_id"
                      :disable="saving || !member.eligible"
                      :aria-label="`Incluir a ${member.name}`"
                    />
                    <div class="col">
                      <strong>{{ member.name }}</strong>
                      <p class="text-caption q-mb-xs">
                        {{ member.folio }} · {{ member.role_in_group }}
                      </p>
                      <p
                        v-for="issue in member.issues"
                        :key="issue"
                        class="text-caption text-negative"
                      >
                        {{ issue }}
                      </p>
                    </div>
                  </div>
                  <q-input
                    v-if="selected.includes(member.client_id)"
                    v-model="amounts[member.client_id]"
                    outlined
                    stack-label
                    inputmode="decimal"
                    :label="`Monto solicitado por ${member.name} (MXN)`"
                    :disable="saving"
                    :rules="[positiveAmount]"
                  />
                  <p
                    v-if="memberEstimate(member.client_id)"
                    class="text-caption text-grey-7 q-mt-sm"
                  >
                    Cuota estimada: {{ money(memberEstimate(member.client_id)?.weekly_payment) }} ·
                    Total: {{ money(memberEstimate(member.client_id)?.total_repayment) }}
                  </p>
                </div></q-card-section
              ></q-card
            >
          </div>
          <aside>
            <q-card flat bordered class="intake-card credit-summary"
              ><q-card-section
                ><p class="eyebrow">RESUMEN ESTIMADO</p>
                <h2>{{ selected.length }} integrantes · 16 semanas</h2>
                <dl>
                  <dt>Capital solicitado</dt>
                  <dd>{{ money(totals.principal) }}</dd>
                  <dt>Pago semanal del grupo</dt>
                  <dd>{{ money(totals.weekly_payment) }}</dd>
                  <dt>Interés total</dt>
                  <dd>{{ money(totals.interest) }}</dd>
                  <dt>Total a devolver</dt>
                  <dd>{{ money(totals.total_repayment) }}</dd>
                  <dt>Garantía estimada (10%)</dt>
                  <dd>{{ money(totals.guarantee) }}</dd>
                  <dt>Entrega neta estimada</dt>
                  <dd>{{ money(totals.net_delivery) }}</dd>
                </dl>
                <p class="text-caption">
                  Estimaciones sobre lo solicitado. Los montos aprobados y la entrega real se
                  definirán después. Adelantar cuotas no reduce el interés pactado.
                </p>
                <q-separator class="q-my-md" />
                <p class="text-caption">
                  La entrega del crédito y el medio de pago semanal se definirán durante la
                  autorización. Esta solicitud no registra un desembolso.
                </p>
                <q-btn
                  class="full-width"
                  color="primary"
                  no-caps
                  label="Registrar solicitud"
                  type="submit"
                  :disable="context.issues.length > 0 || selected.length < 3 || !rate"
                  :loading="saving" /></q-card-section
            ></q-card>
          </aside></div></q-form></template
  ></q-page>
</template>
<script setup lang="ts">
import { newUuid as createUuid } from '../services/uuid';
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import GroupSearch from '../components/GroupSearch.vue';
import FilterSelect from '../components/FilterSelect.vue';
import { intakeService, type Team } from '../services/intake.service';
import {
  creditService,
  type CreditContext,
  type CreditRate,
  type Estimate,
} from '../services/credit.service';
import { administrationError } from '../services/administration.service';
import { alerts } from '../services/alerts.service';
import { cents, estimateCredit, money, sumEstimates } from '../domain/credit-calculation';
const route = useRoute(),
  router = useRouter();
const team = ref<Team | null>(null),
  context = ref<CreditContext | null>(null),
  rates = ref<CreditRate[]>([]),
  rateId = ref(''),
  limit = ref(''),
  selected = ref<string[]>([]),
  amounts = ref<Record<string, string>>({});
const loading = ref(false),
  saving = ref(false),
  saved = ref(false),
  error = ref(''),
  requestId = createUuid();
let generation = 0;
const rate = computed(() => rates.value.find((r) => r.id === rateId.value));
const dirty = computed(
  () => !saved.value && (!!limit.value || Object.values(amounts.value).some(Boolean)),
);
const positiveAmount = (value: string | undefined) => {
  try {
    return (!!value && cents(value) > 0n) || 'Escribe un monto mayor a cero';
  } catch {
    return 'Usa números con hasta dos decimales, sin comas';
  }
};
function memberEstimate(id: string): Estimate | null {
  try {
    return rate.value && amounts.value[id]
      ? estimateCredit(amounts.value[id], rate.value.rate_factor)
      : null;
  } catch {
    return null;
  }
}
const totals = computed(() =>
  sumEstimates(
    selected.value.map((id) => memberEstimate(id)).filter((row): row is Estimate => !!row),
  ),
);
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
    error.value = administrationError(e);
  } finally {
    if (current === generation) loading.value = false;
  }
});
async function searchRates(term: string) {
  const result = await creditService.rates(term);
  const known = new Map(rates.value.map((r) => [r.id, r]));
  result.data.forEach((r) => known.set(r.id, r));
  rates.value = [...known.values()];
  return result.data;
}
async function changeGroup() {
  if (
    dirty.value &&
    !(await alerts.confirm(
      'Cambiar grupo',
      'Se descartarán los montos de esta captura.',
      'Cambiar grupo',
    ))
  )
    return;
  context.value = null;
  team.value = null;
  selected.value = [];
  amounts.value = {};
  limit.value = '';
  error.value = '';
}
async function canLeave() {
  return (
    !dirty.value ||
    (await alerts.confirm(
      'Solicitud sin guardar',
      'Los importes aún no se han registrado. ¿Deseas salir?',
      'Salir sin guardar',
    ))
  );
}
onBeforeRouteLeave(canLeave);
function unload(event: BeforeUnloadEvent) {
  if (dirty.value) {
    event.preventDefault();
    event.returnValue = '';
  }
}
async function save() {
  if (saving.value || !context.value || !rate.value) return;
  saving.value = true;
  error.value = '';
  try {
    const cap = cents(limit.value);
    if (cap <= 0n) throw Error('Indica un límite mayor a cero.');
    for (const id of selected.value) {
      const amount = cents(amounts.value[id] ?? '');
      if (amount <= 0n || amount > cap)
        throw Error('Cada monto debe ser mayor a cero y no superar el límite por integrante.');
    }
    if (
      !(await alerts.confirm(
        'Registrar solicitud',
        `Se conservarán los montos solicitados por ${selected.value.length} integrantes: ${money(totals.value.principal)} en total, con tasa ${rate.value.name}. No se autoriza ni entrega dinero en este paso.`,
        'Registrar solicitud',
      ))
    )
      return;
    saving.value = true;
    const result = await creditService.create({
      id: requestId,
      team_id: context.value.team.id,
      rate_id: rate.value.id,
      rate_signature: rate.value.terms_hash,
      member_limit: limit.value,
      participants: selected.value.map((id) => ({
        client_id: id,
        amount_requested: amounts.value[id]!,
      })),
    });
    saved.value = true;
    await router.replace(`/solicitudes-credito/${result.id}`);
    void alerts.success(`Solicitud ${result.folio} registrada para revisión.`);
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
    rates.value = (await creditService.rates()).data;
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
.credit-columns {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 24px;
  align-items: start;
}
.credit-summary {
  position: sticky;
  top: 100px;
}
.credit-member {
  padding: 18px 0;
  border-bottom: 1px solid #e5e7eb;
}
.credit-member:last-child {
  border: 0;
}
dl {
  margin: 20px 0;
}
dt {
  color: #646b79;
  font-size: 13px;
  margin-top: 14px;
}
dd {
  margin: 3px 0;
  font-size: 20px;
  font-weight: 650;
  color: #811335;
}
@media (max-width: 1000px) {
  .credit-columns {
    grid-template-columns: 1fr;
  }
  .credit-summary {
    position: static;
  }
}
</style>
