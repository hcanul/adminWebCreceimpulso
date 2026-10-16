<template>
  <q-card flat bordered class="intake-card q-mt-lg">
    <q-card-section
      ><p class="eyebrow">ENTREGA Y VENCIMIENTOS</p>
      <h2>Desembolso del crédito</h2>
      <q-linear-progress v-if="loading" indeterminate />
      <q-banner v-if="error" class="bg-red-1 text-negative"
        >{{ error }}<q-btn flat no-caps label="Recargar entrega" :disable="busy" @click="reload"
      /></q-banner>
      <template v-if="data?.record"
        ><q-banner class="bg-green-1" rounded
          >Entrega registrada el {{ data.record.disbursed_on }} por {{ data.record.actor_name }}.
          Calendario de 16 semanas generado.</q-banner
        >
        <p class="q-mt-md">Registro: {{ data.record.id }}</p>
        <p>
          Modalidad: {{ methodLabel(data.record.method) }}. Garantía retenida:
          <strong>{{ money(data.record.guarantee_retained) }}</strong
          >. Neto entregado: <strong>{{ money(data.record.net_delivered) }}</strong
          >.
        </p>
        <p v-if="data.record.bank_reference">
          Banco: {{ data.record.bank_name }} · Referencia: {{ data.record.bank_reference }}
        </p>
        <p class="notes">{{ data.record.notes }}</p>
      </template>
      <template v-else-if="canDisburse">
        <CreditTermsSummary :terms="loan.approved_snapshot!" approved />
        <q-banner class="bg-orange-1 q-mt-lg" rounded
          >Registra una entrega ya realizada. Esta acción no realiza transferencias bancarias. El
          capital, la garantía y el neto se toman de la autorización.</q-banner
        >
        <div class="delivery-fields q-my-lg">
          <q-input
            v-model="disbursedOn"
            type="date"
            stack-label
            outlined
            label="Fecha real de entrega"
            :max="today"
            :readonly="busy"
          />
          <q-input
            v-model="firstDue"
            type="date"
            stack-label
            outlined
            label="Fecha del primer pago"
            :readonly="busy || !!agreedDate"
            :hint="
              agreedDate
                ? 'Fecha pactada en la autorización; no editable en Caja.'
                : 'Crédito anterior sin fecha pactada: captura el primer vencimiento.'
            "
          />
          <template v-if="loan.disbursement_method === 'bank_deposit'"
            ><q-input
              v-model="bankName"
              outlined
              label="Banco de la entrega"
              maxlength="100"
              :readonly="busy" /><q-input
              v-model="bankReference"
              outlined
              label="Referencia de la entrega bancaria"
              maxlength="150"
              :readonly="busy"
          /></template>
        </div>
        <q-input
          v-model="notes"
          outlined
          autogrow
          label="Observaciones de la entrega"
          maxlength="4000"
          :readonly="busy"
        />
        <q-checkbox
          v-model="confirmed"
          :disable="busy"
          :label="`Confirmo que se entregaron ${money(loan.approved_snapshot?.totals.net_delivery)} y se retuvieron ${money(loan.approved_snapshot?.totals.guarantee)} de garantía.`"
          class="q-mt-md"
        />
        <p>
          Se respeta el primer vencimiento pactado en la autorización. Los siguientes serán cada 7
          días, sin mover automáticamente sábados, domingos o días festivos.
        </p>
        <q-banner v-if="calendarError" class="bg-orange-1">{{ calendarError }}</q-banner>
        <q-btn
          color="primary"
          no-caps
          label="Registrar entrega realizada"
          :loading="busy"
          :disable="!confirmed || !calendar.length || !!calendarError || !notes.trim()"
          class="q-mt-md"
          @click="submit"
        />
        <p v-if="dirty" class="text-caption text-primary">Hay datos de entrega sin registrar.</p>
      </template>
      <q-banner v-else-if="!loading && !data?.record" class="bg-grey-2"
        >La entrega todavía no se ha registrado. Se requiere un crédito autorizado y permiso de
        desembolso.</q-banner
      >
    </q-card-section>
    <q-card-section v-if="calendar.length"
      ><h2>{{ data?.record ? 'Calendario registrado' : 'Vista previa del calendario' }}</h2>
      <div class="scroll">
        <table class="schedule-table">
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
      <q-expansion-item
        v-for="member in loan.approved_snapshot?.members"
        :key="member.client_id"
        :label="member.name"
        :caption="`${member.folio ?? ''} · ${money(member.estimate.weekly_payment)} semanales`"
        icon="person_outline"
        class="q-mt-sm"
        ><q-card-section
          ><p
            v-for="row in installments.filter((row) => row.client_id === member.client_id)"
            :key="row.number"
          >
            Semana {{ row.number }} · {{ row.due_on }} · {{ money(row.amount_due) }}
          </p></q-card-section
        ></q-expansion-item
      >
      <p class="text-caption text-grey-7">La garantía retenida no se aplica como pago de cuotas.</p>
    </q-card-section>
  </q-card>
</template>
<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { onBeforeRouteLeave } from 'vue-router';
import { useDisbursementStore } from '../stores/disbursement.store';
import { useAuthStore } from '../stores/auth.store';
import type { CreditApplication } from '../services/credit.service';
import { administrationError } from '../services/administration.service';
import { alerts } from '../services/alerts.service';
import { methodLabel } from '../services/authorization.service';
import { money } from '../domain/credit-calculation';
import { groupSchedule, previewSchedule } from '../domain/credit-schedule';
import CreditTermsSummary from './CreditTermsSummary.vue';
const props = defineProps<{ loan: CreditApplication }>();
const emit = defineEmits<{ updated: [] }>();
const store = useDisbursementStore(),
  auth = useAuthStore();
const { data } = storeToRefs(store);
const today = new Intl.DateTimeFormat('en-CA', {
  timeZone: 'America/Cancun',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
}).format(new Date());
const agreedDate = computed(() => props.loan.approved_snapshot?.first_due_on ?? null);
const disbursedOn = ref(today),
  firstDue = ref(props.loan.approved_snapshot?.first_due_on ?? ''),
  bankName = ref(''),
  bankReference = ref(''),
  notes = ref(''),
  confirmed = ref(false),
  busy = ref(false),
  loading = ref(true),
  error = ref('');
const requestId = crypto.randomUUID();
const canDisburse = computed(
  () =>
    !loading.value &&
    !error.value &&
    !data.value?.record &&
    props.loan.status === 'aprobado' &&
    !!props.loan.approved_snapshot &&
    auth.can('credit.disburse'),
);
const dirty = computed(
  () =>
    !data.value?.record &&
    !!(
      (firstDue.value && firstDue.value !== agreedDate.value) ||
      bankName.value ||
      bankReference.value ||
      notes.value ||
      confirmed.value
    ),
);
const calendarError = computed(() => {
  if (!canDisburse.value) return '';
  if (!firstDue.value) return 'Selecciona la fecha del primer pago para revisar el calendario.';
  if (!disbursedOn.value || firstDue.value <= disbursedOn.value)
    return 'El primer pago debe ser posterior a la entrega.';
  if (disbursedOn.value > today) return 'La entrega no puede tener una fecha futura.';
  try {
    previewSchedule(props.loan.approved_snapshot!, firstDue.value);
    return '';
  } catch (e) {
    return e instanceof Error ? e.message : 'Fecha inválida.';
  }
});
const installments = computed(() =>
  data.value?.record
    ? data.value.installments
    : canDisburse.value && !calendarError.value
      ? previewSchedule(props.loan.approved_snapshot!, firstDue.value)
      : [],
);
const calendar = computed(() => groupSchedule(installments.value));
let disposed = false;
async function load() {
  loading.value = true;
  try {
    await store.load(props.loan.id);
    if (!disposed) error.value = '';
  } catch (e) {
    if (!disposed) error.value = administrationError(e);
  } finally {
    if (!disposed) loading.value = false;
  }
}
async function reload() {
  await load();
  emit('updated');
}
async function submit() {
  if (busy.value || !canDisburse.value || !confirmed.value || calendarError.value) return;
  if (
    props.loan.disbursement_method === 'bank_deposit' &&
    (!bankName.value.trim() || !bankReference.value.trim())
  )
    return alerts.error('Captura el banco y la referencia de la entrega.');
  busy.value = true;
  try {
    if (
      !(await alerts.confirm(
        'Registrar entrega realizada',
        `Neto entregado: ${money(props.loan.approved_snapshot?.totals.net_delivery)}. Primer vencimiento: ${firstDue.value}. Se conservarán 16 cuotas y este registro de entrega. No podrá editarse desde esta pantalla.`,
        'Sí, registrar entrega',
      ))
    )
      return;
    await store.save(props.loan.id, {
      id: requestId,
      authorization_version: props.loan.authorization_version!,
      disbursed_on: disbursedOn.value,
      first_due_on: firstDue.value,
      bank_name: bankName.value.trim() || null,
      bank_reference: bankReference.value.trim() || null,
      notes: notes.value.trim(),
      confirmed: true,
    });
    emit('updated');
    await alerts.success('Entrega registrada y calendario generado');
  } catch (e) {
    error.value = administrationError(e);
    await alerts.error(error.value);
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
        'Entrega sin registrar',
        '¿Deseas salir y descartar los datos capturados?',
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
.delivery-fields {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.schedule-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}
.schedule-table td,
.schedule-table th {
  padding: 12px;
  border-bottom: 1px solid #e5e7eb;
}
.schedule-table th {
  background: #faf5f7;
  color: #811335;
}
.notes {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
@media (max-width: 700px) {
  .delivery-fields {
    grid-template-columns: 1fr;
  }
}
</style>
