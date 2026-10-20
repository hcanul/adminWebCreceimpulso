<template>
  <q-card flat bordered class="intake-card q-mt-lg">
    <q-card-section
      ><p class="eyebrow">GERENCIA Y COORDINACIÓN COMERCIAL</p>
      <h2>Autorización del crédito</h2>
      <q-badge v-if="data" color="primary">{{ authorizationLabels[data.status] }}</q-badge>
      <p class="q-mt-md">
        El gerente propone las condiciones y Coordinación Comercial las confirma o devuelve. La
        autorización conserva lo solicitado y deja pendiente la entrega del dinero.
      </p>
      <q-banner v-if="error" class="bg-red-1 text-negative"
        >{{ error
        }}<q-btn flat no-caps label="Recargar condiciones" :disable="busy" @click="reload"
      /></q-banner>
      <q-linear-progress v-if="loading" indeterminate />
      <q-banner v-if="loan.review_status !== 'ready_for_authorization'" class="bg-orange-1"
        >Completa primero la revisión de Gestión para preparar las condiciones.</q-banner
      >
    </q-card-section>
    <template v-if="data">
      <q-card-section><q-banner v-if="data?.late_fee_policy" class="bg-orange-1 q-mb-md">Política de mora: 10% del saldo pendiente de cada cuota por semana de atraso iniciada (días 1, 8, 15…), sin capitalización. Los pagos cubren cuotas exigibles, mora y después adelantos. Solo se retiene la garantía al entregar.</q-banner></q-card-section>
      <q-card-section v-if="canPropose">
        <h2>Propuesta del gerente</h2>
        <p>
          Cada importe debe ser mayor a cero y no superar lo solicitado ni los límites pactados. Los
          integrantes, la tasa y las 16 semanas se conservan.
        </p>
        <div v-for="(member, index) in members" :key="member.client_id" class="proposal-row">
          <div>
            <strong>{{ member.name }}</strong>
            <p class="text-caption">
              {{ member.folio }} · {{ member.role_in_group }}<br />Solicitado:
              {{ money(member.amount_requested) }}
            </p>
          </div>

      <q-input
            v-model="amounts[index]"
            outlined
            inputmode="decimal"
            :label="`Importe propuesto para ${member.name} (MXN)`"
            :readonly="busy"
            maxlength="11"
          />
        </div>
        <div class="method-fields q-my-lg">
          <FilterSelect
            emit-value
            map-options
            v-model="disbursement"
            outlined
            label="Forma de entregar el crédito"
            :options="methodOptions"
            :disable="busy"
          />
          <FilterSelect
            emit-value
            map-options
            v-model="repayment"
            outlined
            label="Forma de realizar los pagos semanales"
            :options="methodOptions"
            :disable="busy"
          />
        </div>
        <p class="text-caption text-grey-7">
          En los pagos por depósito, Caja registrará el folio bancario y lo separará del efectivo en
          el corte.
        </p>
        <q-banner v-if="preview.error" class="bg-orange-1 q-mb-md">{{ preview.error }}</q-banner>
        <CreditTermsSummary v-if="preview.terms" :terms="preview.terms" />
      </q-card-section>
      <q-card-section v-else-if="data.proposal">
        <h2>
          {{
            data.status === 'authorized'
              ? 'Condiciones autorizadas'
              : 'Condiciones enviadas por el gerente'
          }}
        </h2>
        <p>Propuesta de {{ data.proposal.proposed_by_name }}. Versión {{ data.version }}.</p>
        <CreditTermsSummary
          :terms="loan.approved_snapshot ?? data.proposal"
          :approved="data.status === 'authorized'"
        />
      </q-card-section>
      <q-card-section v-else-if="loan.review_status === 'ready_for_authorization'"
        ><q-banner class="bg-grey-2"
          >El gerente debe enviar los importes propuestos y ambas formas de operación antes de
          autorizar.</q-banner
        ></q-card-section
      >
      <q-card-section v-if="canDecide">
        <q-input
          v-model="firstDueOn"
          type="date"
          stack-label
          outlined
          label="Fecha pactada del primer pago"
          :readonly="busy"
          hint="Obligatoria para autorizar. Los otros 15 pagos vencerán cada siete días."
        />
        <q-banner v-if="firstDueOn && scheduleError" class="bg-orange-1 q-mt-md">{{
          scheduleError
        }}</q-banner>
        <div v-if="agreedCalendar.length" class="q-mt-md scroll">
          <table class="agreed-calendar" aria-label="Calendario pactado">
            <thead>
              <tr>
                <th>Semana</th>
                <th>Fecha de pago</th>
                <th>Cuota grupal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in agreedCalendar" :key="row.number">
                <td>{{ row.number }}</td>
                <td>{{ row.due_on }}</td>
                <td>{{ money(row.amount_due) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="text-caption">
          La entrega deberá realizarse antes del primer pago. No se desplazan automáticamente fines
          de semana ni días festivos.
        </p>
      </q-card-section>
      <q-card-section v-if="canPropose || canDecide">
        <q-input
          v-model="notes"
          outlined
          autogrow
          label="Observaciones de la propuesta o decisión"
          maxlength="4000"
          :readonly="busy"
          hint="Obligatorias. Se conservarán en el historial."
        />
        <div class="authorization-actions q-mt-lg">
          <q-btn
            v-if="canPropose"
            color="primary"
            no-caps
            label="Enviar propuesta a Coordinación"
            :loading="busy"
            :disable="!preview.terms || !disbursement || !repayment"
            @click="submit('propose')"
          />
          <template v-if="canDecide"
            ><q-btn
              outline
              color="negative"
              no-caps
              label="Devolver condiciones al gerente"
              :disable="busy"
              @click="submit('return')" /><q-btn
              color="primary"
              no-caps
              label="Autorizar crédito"
              :loading="busy"
              :disable="!firstDueOn || !!scheduleError"
              @click="submit('approve')"
          /></template>
        </div>
        <p v-if="dirty" class="text-caption text-primary">Hay cambios sin enviar.</p>
      </q-card-section>
      <q-card-section v-if="data.status === 'authorized' && loan.status === 'aprobado'"
        ><q-banner class="bg-green-1" rounded
          >Crédito autorizado. La entrega efectiva y el calendario de vencimientos se registrarán en
          el módulo de desembolso. No se ha registrado salida de dinero.</q-banner
        ></q-card-section
      >
      <q-card-section
        ><h2>Historial de autorización</h2>
        <p v-if="!data.history.length" class="text-grey-7">
          Todavía no hay propuestas ni decisiones.
        </p>
        <q-list separator
          ><q-expansion-item
            v-for="event in data.history"
            :key="event.id"
            icon="history"
            :label="`${eventLabels[event.action]} · ${event.actor_name}`"
            :caption="`Versión ${event.version} · ${event.created_at}`"
            ><q-card-section
              ><p class="notes">{{ event.notes }}</p>
              <CreditTermsSummary :terms="event.proposal" /></q-card-section></q-expansion-item
        ></q-list>
      </q-card-section>
    </template>
  </q-card>
</template>
<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { onBeforeRouteLeave } from 'vue-router';
import type { CreditApplication } from '../services/credit.service';
import {
  authorizationLabels,
  type AuthorizationTerms,
  type PaymentMethod,
} from '../services/authorization.service';
import { useAuthorizationStore } from '../stores/authorization.store';
import { useAuthStore } from '../stores/auth.store';
import { administrationError } from '../services/administration.service';
import { alerts } from '../services/alerts.service';
import { cents, estimateCredit, money, sumEstimates } from '../domain/credit-calculation';
import FilterSelect from './FilterSelect.vue';
import CreditTermsSummary from './CreditTermsSummary.vue';
import { previewSchedule, groupSchedule } from '../domain/credit-schedule';
const props = defineProps<{ loan: CreditApplication }>();
const emit = defineEmits<{ updated: [] }>();
const store = useAuthorizationStore(),
  auth = useAuthStore();
const { data } = storeToRefs(store);
const firstDueOn = ref('');
const scheduleError = computed(() => {
  if (!firstDueOn.value || !data.value?.proposal)
    return 'Selecciona la fecha pactada del primer pago.';
  try {
    const today = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'America/Cancun',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(new Date());
    if (firstDueOn.value <= today) return 'El primer pago debe ser posterior a hoy.';
    previewSchedule(data.value.proposal, firstDueOn.value);
    return '';
  } catch {
    return 'Revisa la fecha del primer pago.';
  }
});
const agreedCalendar = computed(() =>
  !scheduleError.value && data.value?.proposal
    ? groupSchedule(previewSchedule(data.value.proposal, firstDueOn.value))
    : [],
);
const amounts = ref<string[]>([]),
  notes = ref(''),
  disbursement = ref<PaymentMethod | null>(null),
  repayment = ref<PaymentMethod | null>(null);
const loading = ref(true),
  busy = ref(false),
  error = ref(''),
  baseline = ref('');
const members = computed(() => props.loan.request_snapshot?.members ?? []);
const available = computed(
  () =>
    !loading.value &&
    !error.value &&
    props.loan.status === 'pendiente' &&
    props.loan.review_status === 'ready_for_authorization',
);
const canPropose = computed(
  () =>
    available.value &&
    auth.can('credit.propose') &&
    ['awaiting_proposal', 'returned'].includes(data.value?.status ?? ''),
);
const canDecide = computed(
  () =>
    available.value &&
    auth.can('credit.authorize') &&
    data.value?.status === 'pending_authorization',
);
const methodOptions = [
  { value: 'cash_branch', label: 'En sucursal' },
  { value: 'bank_deposit', label: 'Depósito bancario' },
];
const eventLabels = {
  propose: 'Propuesta enviada',
  approve: 'Crédito autorizado',
  return: 'Condiciones devueltas',
};
const state = () =>
  JSON.stringify([
    amounts.value,
    disbursement.value,
    repayment.value,
    notes.value,
    firstDueOn.value,
  ]);
const dirty = computed(() => !!baseline.value && state() !== baseline.value);
const preview = computed<{ terms: AuthorizationTerms | null; error: string }>(() => {
  const snapshot = props.loan.request_snapshot;
  if (!snapshot) return { terms: null, error: '' };
  try {
    const rows = members.value.map((member, index) => {
      const amount = amounts.value[index] ?? '';
      if (
        cents(amount) > cents(member.amount_requested) ||
        cents(amount) > cents(snapshot.member_limit)
      )
        throw Error(`${member.name}: el importe supera lo solicitado o el límite del gerente.`);
      return {
        ...member,
        amount_proposed: amount,
        estimate: estimateCredit(amount, snapshot.rate.factor),
      };
    });
    return {
      terms: {
        review_version: 0,
        weeks: 16,
        guarantee_percent: 10,
        rate: snapshot.rate,
        members: rows,
        totals: sumEstimates(rows.map((row) => row.estimate)),
        disbursement_method: disbursement.value!,
        repayment_method: repayment.value!,
        proposed_by_name: '',
        proposed_at: '',
      },
      error: '',
    };
  } catch (e) {
    return { terms: null, error: e instanceof Error ? e.message : 'Revisa los importes.' };
  }
});
let disposed = false;
async function load() {
  loading.value = true;
  try {
    if (!(await store.load(props.loan.id)) || disposed) return;
    amounts.value = members.value.map(
      (member) =>
        data.value?.proposal?.members.find((row) => row.client_id === member.client_id)
          ?.amount_proposed ?? member.amount_requested,
    );
    disbursement.value = data.value?.proposal?.disbursement_method ?? null;
    repayment.value = data.value?.proposal?.repayment_method ?? null;
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
    !(await alerts.confirm('Recargar condiciones', 'Se descartarán los cambios sin enviar.'))
  )
    return;
  await load();
  emit('updated');
}
async function submit(action: 'propose' | 'approve' | 'return') {
  if (busy.value || !data.value) return;
  if (!notes.value.trim())
    return alerts.error('Escribe las observaciones de tu propuesta o decisión.');
  if (action === 'propose' && (!preview.value.terms || !disbursement.value || !repayment.value))
    return alerts.error('Revisa los importes y selecciona ambas formas de operación.');
  if (action === 'approve' && scheduleError.value) return alerts.error(scheduleError.value);
  busy.value = true;
  try {
    const text =
      action === 'approve'
        ? `Se autorizará un capital de ${money(data.value.proposal?.totals.principal)} con primer pago el ${firstDueOn.value} y 16 cuotas semanales. La entrega del dinero quedará pendiente.`
        : action === 'return'
          ? 'El gerente podrá corregir la propuesta. Los importes solicitados y la revisión de Gestión se conservarán.'
          : 'La propuesta quedará cerrada mientras Coordinación Comercial la revisa.';
    if (
      !(await alerts.confirm(
        eventLabels[action],
        text,
        action === 'approve' ? 'Sí, autorizar' : 'Sí, continuar',
      ))
    )
      return;
    if (action === 'propose')
      await store.propose(props.loan.id, {
        version: data.value.version,
        notes: notes.value.trim(),
        disbursement_method: disbursement.value!,
        repayment_method: repayment.value!,
        participants: members.value.map((member, index) => ({
          client_id: member.client_id,
          amount_proposed: amounts.value[index]!,
        })),
      });
    else await store.decide(props.loan.id, action, notes.value.trim(), firstDueOn.value);
    baseline.value = state();
    await load();
    emit('updated');
    await alerts.success(eventLabels[action]);
  } catch (e) {
    await alerts.error(administrationError(e));
  } finally {
    busy.value = false;
  }
}
const beforeUnload = (event: BeforeUnloadEvent) => {
  if (dirty.value || busy.value) {
    event.preventDefault();
    event.returnValue = '';
  }
};
onBeforeRouteLeave(
  async () =>
    !busy.value &&
    (!dirty.value ||
      (await alerts.confirm(
        'Cambios sin enviar',
        '¿Deseas salir y descartar los cambios?',
        'Salir',
      ))),
);
onMounted(() => {
  store.clear();
  window.addEventListener('beforeunload', beforeUnload);
  void load();
});
onUnmounted(() => {
  disposed = true;
  store.clear();
  window.removeEventListener('beforeunload', beforeUnload);
});
</script>
<style scoped>
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
.agreed-calendar th {
  color: #811335;
  background: #faf5f7;
}
.proposal-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  align-items: center;
  padding: 16px 0;
  border-bottom: 1px solid #e5e7eb;
}
.method-fields {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.authorization-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.notes {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
@media (max-width: 750px) {
  .proposal-row,
  .method-fields {
    grid-template-columns: 1fr;
  }
}
</style>
