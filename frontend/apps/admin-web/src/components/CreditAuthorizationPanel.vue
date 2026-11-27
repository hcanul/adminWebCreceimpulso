<template>
  <q-card flat bordered class="intake-card q-mt-lg">
    <q-card-section>
      <p class="eyebrow">PASO 3 · COORDINACIÓN COMERCIAL</p>
      <h2>{{ loan.status === 'pendiente' ? 'Autorizar crédito' : 'Condiciones autorizadas' }}</h2>
      <p>
        Confirma los montos otorgados y las condiciones definitivas. Los montos solicitados se
        conservan.
      </p>
      <q-banner v-if="error" class="bg-red-1 text-negative" style="white-space: pre-line"
        >{{ error
        }}<q-btn flat no-caps label="Recargar condiciones" :disable="busy" @click="reload"
      /></q-banner>
      <q-linear-progress v-if="loading" indeterminate />
      <q-banner v-if="data?.status === 'returned'" class="bg-orange-1"
        >Solicitud con correcciones. Revisa el historial antes de autorizar.</q-banner
      >
    </q-card-section>
    <template v-if="data && !loading">
      <q-card-section v-if="canDecide">
        <div v-for="(member, index) in members" :key="member.client_id" class="authorization-row">
          <div>
            <strong>{{ member.name }}</strong>
            <p>
              {{ member.folio }} · {{ member.role_in_group }}<br />Solicitado:
              {{ money(member.amount_requested) }}
            </p>
          </div>
          <q-input
            v-model="amounts[index]"
            outlined
            stack-label
            inputmode="decimal"
            :label="`Monto otorgado a ${member.name} (MXN)`"
            :readonly="busy"
            maxlength="11"
          />
        </div>
        <div class="authorization-fields q-my-lg">
          <FilterSelect
            v-model="rateId"
            :options="rates"
            :fetch-options="searchRates"
            option-label="name"
            option-value="id"
            emit-value
            map-options
            outlined
            label="Tasa de interés común *"
            :disable="busy"
          />
          <q-input
            v-model="firstDueOn"
            type="date"
            stack-label
            outlined
            label="Fecha del primer pago *"
            :readonly="busy"
            hint="Los otros 15 pagos vencerán cada siete días."
          />
          <FilterSelect
            v-model="repayment"
            :options="repaymentOptions"
            emit-value
            map-options
            outlined
            label="Forma de pago semanal *"
            :disable="busy"
          />
          <FilterSelect
            v-model="disbursement"
            :options="disbursementOptions"
            emit-value
            map-options
            outlined
            label="Forma de desembolso *"
            :disable="busy"
          />
        </div>
        <q-banner v-if="preview.error" class="bg-orange-1 q-mb-md">{{ preview.error }}</q-banner>
        <CreditTermsSummary v-if="preview.terms" :terms="preview.terms" approved />
        <q-banner v-if="firstDueOn && scheduleError" class="bg-orange-1 q-mt-md">{{
          scheduleError
        }}</q-banner>
        <q-expansion-item
          v-if="calendar.length"
          label="Consultar calendario de 16 pagos"
          icon="event"
          class="q-mt-md"
        >
          <div class="scroll">
            <table class="agreed-calendar">
              <thead>
                <tr>
                  <th>Semana</th>
                  <th>Vencimiento</th>
                  <th>Cuota grupal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in calendar" :key="row.number">
                  <td>{{ row.number }}</td>
                  <td>{{ row.due_on }}</td>
                  <td>{{ money(row.amount_due) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </q-expansion-item>
        <q-input
          v-model="notes"
          outlined
          autogrow
          label="Observaciones"
          hint="Obligatorias solo al solicitar correcciones."
          maxlength="4000"
          :readonly="busy"
          class="q-mt-lg"
        />
        <div class="row q-gutter-md q-mt-lg">
          <q-btn
            color="primary"
            no-caps
            label="Autorizar crédito"
            :loading="busy"
            :disable="!preview.terms || !!scheduleError"
            @click="submit('approve')"
          />
          <q-btn
            outline
            color="negative"
            no-caps
            label="Solicitar correcciones"
            :disable="busy"
            @click="submit('return')"
          />
        </div>
      </q-card-section>
      <q-card-section v-else-if="loan.approved_snapshot">
        <CreditTermsSummary :terms="loan.approved_snapshot" approved />
        <q-banner class="bg-green-1 q-mt-md">{{
          loan.status === 'aprobado'
            ? 'Condiciones autorizadas. Continúa con las firmas y la entrega en sucursal.'
            : 'Condiciones autorizadas conservadas para consulta.'
        }}</q-banner>
      </q-card-section>
      <q-card-section v-else
        ><q-banner class="bg-grey-2"
          >Pendiente de Coordinación Comercial. La tasa y los montos otorgados se definirán al
          autorizar.</q-banner
        ></q-card-section
      >
      <q-expansion-item label="Historial de autorización" icon="history">
        <q-card-section
          ><p v-if="!data.history.length">Todavía no hay decisiones registradas.</p>
          <div v-for="event in data.history" :key="event.id" class="q-mb-lg">
            <strong>{{ eventLabels[event.action] }} · {{ event.actor_name }}</strong>
            <p>{{ event.created_at }} · {{ event.notes }}</p>
            <CreditTermsSummary v-if="event.proposal" :terms="event.proposal" />
          </div>
        </q-card-section>
      </q-expansion-item>
    </template>
  </q-card>
</template>
<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { onBeforeRouteLeave } from 'vue-router';
import { creditService, type CreditApplication, type CreditRate } from '../services/credit.service';
import type { AuthorizationTerms, PaymentMethod } from '../services/authorization.service';
import { useAuthorizationStore } from '../stores/authorization.store';
import { useAuthStore } from '../stores/auth.store';
import { administrationError } from '../services/administration.service';
import { alerts } from '../services/alerts.service';
import { cents, estimateCredit, money, sumEstimates } from '../domain/credit-calculation';
import { groupSchedule, previewSchedule } from '../domain/credit-schedule';
import FilterSelect from './FilterSelect.vue';
import CreditTermsSummary from './CreditTermsSummary.vue';
const props = defineProps<{ loan: CreditApplication }>(),
  emit = defineEmits<{ updated: [] }>();
const auth = useAuthStore(),
  store = useAuthorizationStore(),
  { data } = storeToRefs(store);
const rates = ref<CreditRate[]>([]),
  rateId = ref(''),
  amounts = ref<string[]>([]);
const firstDueOn = ref(''),
  repayment = ref<PaymentMethod | null>(null),
  disbursement = ref<PaymentMethod | null>(null),
  notes = ref('');
const loading = ref(true),
  busy = ref(false),
  error = ref(''),
  baseline = ref('');
const members = computed(() => props.loan.request_snapshot?.members ?? []);
const rate = computed(() => rates.value.find((row) => row.id === rateId.value));
const canDecide = computed(
  () =>
    !loading.value &&
    !error.value &&
    props.loan.status === 'pendiente' &&
    auth.can('credit.authorize'),
);
const repaymentOptions = [
  { value: 'cash_branch', label: 'Efectivo en sucursal' },
  { value: 'bank_deposit', label: 'Depósito bancario' },
  { value: 'bank_transfer', label: 'Transferencia bancaria' },
];
const disbursementOptions = [
  { value: 'cash_branch', label: 'Efectivo en sucursal' },
  { value: 'bank_transfer', label: 'Transferencia bancaria' },
  ...(props.loan.disbursement_method === 'bank_deposit'
    ? [{ value: 'bank_deposit', label: 'Depósito bancario (anterior)' }]
    : []),
];
const eventLabels = {
  propose: 'Condiciones anteriores',
  approve: 'Crédito autorizado',
  return: 'Correcciones solicitadas',
};
const state = () =>
  JSON.stringify([
    rateId.value,
    amounts.value,
    firstDueOn.value,
    repayment.value,
    disbursement.value,
    notes.value,
  ]);
const dirty = computed(() => !!baseline.value && state() !== baseline.value);
const preview = computed<{ terms: AuthorizationTerms | null; error: string }>(() => {
  if (!rate.value || !repayment.value || !disbursement.value)
    return { terms: null, error: 'Selecciona la tasa y las formas de pago y desembolso.' };
  try {
    const rows = members.value.map((member, index) => {
      const amount = amounts.value[index] || '';
      const value = cents(amount);
      if (value <= 0n || value > cents(member.amount_requested))
        throw Error(
          `${member.name}: el monto otorgado debe ser mayor a cero y no superar lo solicitado.`,
        );
      if (
        (rate.value!.min_amount && value < cents(rate.value!.min_amount)) ||
        (rate.value!.max_amount && value > cents(rate.value!.max_amount))
      )
        throw Error(`${member.name}: el monto no cumple los límites de la tasa seleccionada.`);
      return {
        ...member,
        amount_approved: amount,
        estimate: estimateCredit(amount, rate.value!.rate_factor),
      };
    });
    return {
      terms: {
        workflow: 'direct',
        review_version: 0,
        weeks: 16,
        guarantee_percent: 10,
        rate: { id: rate.value.id, name: rate.value.name, factor: rate.value.rate_factor },
        members: rows,
        totals: sumEstimates(rows.map((row) => row.estimate)),
        disbursement_method: disbursement.value,
        repayment_method: repayment.value,
        proposed_by_name: '',
        proposed_at: '',
      },
      error: '',
    };
  } catch (e) {
    return { terms: null, error: e instanceof Error ? e.message : 'Revisa los montos.' };
  }
});
const scheduleError = computed(() => {
  if (!firstDueOn.value) return 'Selecciona la fecha del primer pago.';
  const today = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Cancun',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date());
  if (firstDueOn.value <= today) return 'El primer pago debe ser posterior a hoy.';
  try {
    if (preview.value.terms) previewSchedule(preview.value.terms, firstDueOn.value);
    return '';
  } catch {
    return 'Revisa la fecha del primer pago.';
  }
});
const calendar = computed(() =>
  preview.value.terms && !scheduleError.value
    ? groupSchedule(previewSchedule(preview.value.terms, firstDueOn.value))
    : [],
);
let disposed = false;
async function searchRates(term: string) {
  const result = await creditService.rates(term);
  const known = new Map(rates.value.map((row) => [row.id, row]));
  result.data.forEach((row) => known.set(row.id, row));
  rates.value = [...known.values()];
  return result.data;
}
async function load() {
  loading.value = true;
  try {
    if (!(await store.load(props.loan.id)) || disposed) return;
    if (props.loan.status === 'pendiente' && auth.can('credit.authorize')) {
      await searchRates('');
      const previous = data.value?.proposal;
      amounts.value = members.value.map(
        (member) =>
          previous?.members.find((row) => row.client_id === member.client_id)?.amount_proposed ??
          member.amount_requested,
      );
      const previousRate = previous?.rate ?? props.loan.request_snapshot?.rate;
      if (previousRate && !rates.value.some((row) => row.id === previousRate.id))
        await searchRates(previousRate.name);
      rateId.value =
        previousRate && rates.value.some((row) => row.id === previousRate.id)
          ? previousRate.id
          : '';
      repayment.value = previous?.repayment_method ?? null;
      disbursement.value =
        previous?.disbursement_method === 'bank_deposit'
          ? 'bank_transfer'
          : (previous?.disbursement_method ?? null);
    }
    notes.value = '';
    firstDueOn.value = props.loan.approved_snapshot?.first_due_on ?? '';
    baseline.value = state();
    error.value = '';
  } catch (e) {
    if (!disposed) error.value = administrationError(e);
  } finally {
    if (!disposed) loading.value = false;
  }
}
async function reload() {
  if (
    dirty.value &&
    !(await alerts.confirm('Recargar condiciones', 'Se descartarán los cambios sin guardar.'))
  )
    return;
  await load();
}
async function submit(decision: 'approve' | 'return') {
  if (busy.value || !canDecide.value || !data.value) return;
  if (decision === 'return' && !notes.value.trim())
    return alerts.error('Describe qué debe corregirse.');
  if (decision === 'approve' && (!preview.value.terms || scheduleError.value))
    return alerts.error(preview.value.error || scheduleError.value);
  busy.value = true;
  try {
    if (
      !(await alerts.confirm(
        decision === 'approve' ? 'Autorizar crédito' : 'Solicitar correcciones',
        decision === 'approve'
          ? `Se autorizarán ${money(preview.value.terms?.totals.principal)} con primer pago el ${firstDueOn.value}. El desembolso quedará pendiente.`
          : 'Se conservará la solicitud y se registrarán tus observaciones.',
        decision === 'approve' ? 'Sí, autorizar' : 'Solicitar correcciones',
      ))
    )
      return;
    await store.decide(
      props.loan.id,
      decision,
      notes.value.trim(),
      firstDueOn.value,
      decision === 'approve'
        ? {
            rate_id: rate.value!.id,
            rate_signature: rate.value!.terms_hash,
            repayment_method: repayment.value!,
            disbursement_method: disbursement.value!,
            participants: members.value.map((member, index) => ({
              client_id: member.client_id,
              amount_approved: amounts.value[index]!,
            })),
          }
        : undefined,
    );
    baseline.value = state();
    emit('updated');
    await alerts.success(
      decision === 'approve'
        ? 'Crédito autorizado; continúa con firmas y desembolso'
        : 'Correcciones registradas',
    );
  } catch (e) {
    await alerts.error(administrationError(e));
  } finally {
    busy.value = false;
  }
}
const unload = (event: BeforeUnloadEvent) => {
  if (dirty.value || busy.value) {
    event.preventDefault();
    event.returnValue = '';
  }
};
onBeforeRouteLeave(
  async () =>
    !busy.value &&
    (!dirty.value ||
      (await alerts.confirm('Condiciones sin guardar', '¿Deseas salir y descartar los cambios?'))),
);
onMounted(() => {
  store.clear();
  window.addEventListener('beforeunload', unload);
  void load();
});
onUnmounted(() => {
  disposed = true;
  store.clear();
  window.removeEventListener('beforeunload', unload);
});
</script>
<style scoped>
.authorization-row,
.authorization-fields {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  align-items: center;
}
.authorization-row {
  padding: 16px 0;
  border-bottom: 1px solid #eee;
}
.agreed-calendar {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}
.agreed-calendar th,
.agreed-calendar td {
  padding: 10px;
  border-bottom: 1px solid #eee;
}
@media (max-width: 750px) {
  .authorization-row,
  .authorization-fields {
    grid-template-columns: 1fr;
  }
}
</style>
