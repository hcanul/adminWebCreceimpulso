<template>
  <q-page class="intake-page">
    <div class="intake-heading">
      <div>
        <p class="eyebrow">CAJA · COBROS</p>
        <h1>{{ loan?.folio ?? 'Cobro de crédito' }}</h1>
        <p>{{ loan?.team?.name }} · {{ loan?.branch?.name }}</p>
      </div>
      <div class="q-gutter-sm">
        <q-btn
          v-if="loan && ['entregado', 'pagado'].includes(loan.status)"
          outline
          color="primary"
          no-caps
          icon="account_balance_wallet"
          label="Estado de cuenta"
          :to="`/creditos/${loan.id}/estado-de-cuenta`"
        />
        <q-btn v-if="loan" flat no-caps label="Garantía" :to="`/creditos/${loan.id}/garantia`" />
        <q-btn flat no-caps icon="arrow_back" label="Cobros" to="/cobros" />
      </div>
    </div>
    <q-linear-progress v-if="loading" indeterminate />
    <q-banner v-if="error" rounded class="bg-red-1 text-negative q-mb-md">{{ error }}</q-banner>
    <template v-if="data && loan">
      <div class="summary-grid q-mb-lg">
        <q-card v-for="card in summary" :key="card.label" flat bordered class="intake-card"
          ><q-card-section
            ><p class="eyebrow">{{ card.label }}</p>
            <h2 :class="card.warning ? 'text-negative' : 'text-primary'">
              {{ money(card.value) }}
            </h2></q-card-section
          ></q-card
        >
      </div>
      <q-card flat bordered class="intake-card q-mb-lg"
        ><q-card-section
          ><h2>Saldo por integrante</h2>
          <p>
            Al {{ data.as_of }}. Lo exigible incluye las cuotas de hoy; el atraso empieza al día
            siguiente del vencimiento.
          </p>
          <div class="scroll">
            <table class="collection-table">
              <thead>
                <tr>
                  <th>Integrante</th>
                  <th>Cuotas pendientes</th>
                  <th>Mora pendiente</th>
                  <th>Saldo total</th>
                  <th>Exigible hoy</th>
                  <th>Atraso</th>
                  <th>Días de atraso</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="member in data.members" :key="member.client_id">
                  <td>
                    {{ member.name }}<br /><small>{{ member.folio }}</small>
                  </td>
                  <td>{{ money(member.installment_balance ?? member.balance) }}</td>
                  <td>{{ money(member.late_fee_balance ?? '0.00') }}</td>
                  <td>{{ money(member.balance) }}</td>
                  <td>{{ money(member.due) }}</td>
                  <td :class="member.days_overdue ? 'text-negative' : ''">
                    {{ money(member.overdue) }}
                  </td>
                  <td>{{ member.days_overdue }}</td>
                </tr>
              </tbody>
            </table>
          </div></q-card-section
        ></q-card
      >
      <q-card
        v-if="data.status === 'entregado' && auth.can('credit.collect')"
        flat
        bordered
        class="intake-card q-mb-lg"
        ><q-card-section
          ><h2>Registrar pago recibido</h2>
          <q-banner rounded class="bg-grey-2 q-mb-lg"
            ><template v-if="data.late_fees?.enabled"
              >Cada aportación cubre primero las cuotas exigibles más antiguas, después la mora
              pendiente y finalmente adelanta cuotas.</template
            ><template v-else
              >Este crédito conserva sus condiciones anteriores: cada aportación cubre las cuotas
              más antiguas y después adelanta cuotas; la simulación de mora no se cobra.</template
            >
            El interés pactado se conserva. La garantía se administra por separado.</q-banner
          >
          <q-form @submit="submit"
            ><fieldset :disabled="busy || !!pending" class="payment-fields">
              <div class="form-grid">
                <q-input
                  :model-value="bank ? 'Depósito bancario' : 'Efectivo en sucursal'"
                  outlined
                  readonly
                  label="Modalidad autorizada"
                /><q-input
                  v-model="amount"
                  outlined
                  label="Total recibido (MXN)"
                  inputmode="decimal"
                  maxlength="12"
                  :rules="[required]"
                />
                <template v-if="bank"
                  ><q-input
                    v-model="bankName"
                    outlined
                    label="Banco del ticket"
                    maxlength="100"
                    :rules="[required]" /><q-input
                    v-model="bankReference"
                    outlined
                    label="Folio del ticket bancario"
                    maxlength="150"
                    :rules="[required]" /><q-input
                    v-model="depositedOn"
                    outlined
                    type="date"
                    stack-label
                    label="Fecha del depósito"
                    :rules="[required]"
                /></template>
              </div>
              <h3 class="text-subtitle1 text-weight-bold">Distribución del pago</h3>
              <p>
                Indica quién aporta y a qué integrante se aplica. Agrega otra aportación si el grupo
                cubre un faltante.
              </p>
              <div v-for="(row, index) in contributions" :key="row.key" class="contribution-row">
                <FilterSelect
                  v-model="row.payer_client_id"
                  outlined
                  emit-value
                  map-options
                  :options="memberOptions"
                  label="Quién aporta"
                  :rules="[required]"
                />
                <FilterSelect
                  v-model="row.client_id"
                  outlined
                  emit-value
                  map-options
                  :options="memberOptions"
                  label="Aplicar al crédito de"
                  :rules="[required]"
                />
                <q-input
                  v-model="row.amount"
                  outlined
                  label="Importe (MXN)"
                  inputmode="decimal"
                  maxlength="11"
                  :rules="[required]"
                />
                <q-btn
                  flat
                  round
                  icon="delete_outline"
                  color="negative"
                  aria-label="Quitar aportación"
                  :disable="contributions.length === 1"
                  @click="contributions.splice(index, 1)"
                />
              </div>
              <q-btn
                outline
                no-caps
                icon="add"
                label="Agregar aportación"
                :disable="contributions.length >= 100"
                @click="addContribution"
              />
              <p class="text-weight-bold q-mt-md">
                Total distribuido:
                {{ distributed === null ? 'Revisa los importes' : money(distributed) }}
              </p>
              <q-input
                v-model="notes"
                outlined
                type="textarea"
                label="Observaciones (opcional)"
                maxlength="2000"
                class="q-mt-md"
              />
              <q-checkbox
                v-model="confirmed"
                label="Confirmo que recibí y cotejé el efectivo o el ticket bancario y revisé la distribución."
                class="q-mt-md"
              />
            </fieldset>
            <q-btn
              v-if="!pending"
              color="primary"
              no-caps
              type="submit"
              label="Confirmar cobro"
              icon="payments"
              :loading="busy"
              :disable="!confirmed || loading"
              class="q-mt-md"
          /></q-form>
          <q-banner v-if="pending" class="bg-orange-1 q-mt-md"
            >La respuesta del cobro no se pudo confirmar. Conservamos la misma operación para que
            puedas reintentar sin duplicarla. No captures otro cobro para este dinero.<template
              #action
              ><q-btn
                flat
                no-caps
                label="Reintentar el mismo cobro"
                :loading="busy"
                @click="retry" /></template
          ></q-banner> </q-card-section
      ></q-card>
      <q-banner v-else-if="data.status === 'pagado'" rounded class="bg-green-1 q-mb-lg"
        >Crédito liquidado. Cuotas y cargos aplicables quedaron cubiertos.</q-banner
      >
      <q-card flat bordered class="intake-card"
        ><q-card-section
          ><h2>Historial de cobros</h2>
          <p>
            El registro es permanente. Abre un pago para consultar su comprobante QR. La impresión se realiza en admin-web.
          </p>
          <p v-if="!data.payments.total" class="text-grey-7">
            Todavía no se han registrado pagos.
          </p></q-card-section
        >
        <q-list separator
          ><q-expansion-item
            v-for="payment in data.payments.data"
            :key="payment.id"
            :label="`${money(payment.amount)} · ${payment.method === 'bank_deposit' ? 'Depósito bancario' : 'Efectivo'} · ${payment.received_on}`"
            :caption="payment.actor_name"
            expand-separator
            ><q-card-section
              ><q-banner v-if="payment.reversal" class="bg-red-1 text-negative q-mb-md"
                >Pago revertido el {{ payment.reversal.reversed_on }} por
                {{ payment.reversal.actor_name }}: {{ payment.reversal.reason }}</q-banner
              >
              <p class="payment-id">Registro: {{ payment.id }}</p>
              <q-btn
                outline
                no-caps
                icon="qr_code_2"
                label="Ver comprobante QR"
                :to="`/comprobantes/${payment.id}`"
                class="q-mb-md"
              />
              <p v-if="payment.bank_reference">
                {{ payment.bank_name }} · Folio {{ payment.bank_reference }} · Depósito
                {{ payment.deposited_on }}
              </p>
              <p v-if="payment.notes">{{ payment.notes }}</p>
              <div class="scroll">
                <table class="collection-table">
                  <thead>
                    <tr>
                      <th>Aporta</th>
                      <th>Se aplica a</th>
                      <th>Cuota</th>
                      <th>Vencimiento</th>
                      <th>Importe</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(allocation, i) in payment.allocations" :key="i">
                      <td>{{ memberName(allocation.payer_client_id) }}</td>
                      <td>{{ memberName(allocation.client_id) }}</td>
                      <td>
                        {{ allocation.kind === 'late_fee' ? 'Mora de cuota' : 'Cuota' }}
                        {{ allocation.number
                        }}<small v-if="allocation.charged_on">
                          · Cargo {{ allocation.charged_on }}</small
                        >
                      </td>
                      <td>{{ allocation.due_on }}</td>
                      <td>{{ money(allocation.amount) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div></q-card-section
            ></q-expansion-item
          ></q-list
        >
        <q-card-actions align="center"
          ><q-pagination
            :model-value="data.payments.current_page"
            :max="data.payments.last_page"
            :max-pages="5"
            :disable="busy || loading || !!pending"
            @update:model-value="loadHistory"
        /></q-card-actions>
      </q-card>
    </template>
  </q-page>
</template>
<script setup lang="ts">
import { newUuid as createUuid } from '../services/uuid';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { onBeforeRouteLeave, useRoute } from 'vue-router';
import { isAxiosError } from 'axios';
import FilterSelect from '../components/FilterSelect.vue';
import { creditService, type CreditApplication } from '../services/credit.service';
import { type CollectionInput, type Contribution } from '../services/collections.service';
import { useStaffAuth as useAuthStore } from '../stores/auth';
import { useCollectionsStore } from '../stores/collections.store';
import { administrationError } from '../services/administration.service';
import { alerts } from '../services/alerts.service';
import { cents, decimal, money } from '../domain/credit-calculation';
const id = String((useRoute().params as { id: string }).id);
const auth = useAuthStore();
const store = useCollectionsStore(),
  { data } = storeToRefs(store);
const loan = ref<CreditApplication | null>(null),
  loading = ref(false),
  busy = ref(false),
  error = ref('');
const amount = ref(''),
  bankName = ref(''),
  bankReference = ref(''),
  depositedOn = ref(''),
  notes = ref(''),
  confirmed = ref(false);
const contributions = ref<(Contribution & { key: string })[]>([]);
const pending = ref<CollectionInput | null>(null);
const bank = computed(() => loan.value?.repayment_method === 'bank_deposit');
const required = (v: string) => !!v?.trim() || 'Completa este campo';
const memberOptions = computed(
  () =>
    data.value?.members.map((m) => ({ value: m.client_id, label: `${m.name} · ${m.folio}` })) ?? [],
);
const memberName = (clientId: string) =>
  data.value?.members.find((m) => m.client_id === clientId)?.name ?? clientId;
const distributed = computed(() => {
  try {
    return decimal(contributions.value.reduce((total, r) => total + cents(r.amount), 0n));
  } catch {
    return null;
  }
});
const dirty = computed(
  () =>
    !!(
      amount.value ||
      bankReference.value ||
      bankName.value ||
      depositedOn.value ||
      notes.value ||
      confirmed.value ||
      contributions.value.some((r) => r.amount || r.client_id || r.payer_client_id)
    ),
);
const summary = computed(() =>
  [
    { label: 'Saldo total', field: 'balance' as const },
    { label: 'Exigible al día de hoy', field: 'due' as const },
    { label: 'Vencido', field: 'overdue' as const },
  ].map((c) => ({
    label: c.label,
    value: decimal((data.value?.members ?? []).reduce((sum, m) => sum + cents(m[c.field]), 0n)),
    warning: c.field === 'overdue',
  })),
);
function addContribution() {
  contributions.value.push({
    key: createUuid(),
    client_id: '',
    payer_client_id: '',
    amount: '',
  });
}
async function loadHistory(page: number) {
  loading.value = true;
  try {
    await store.load(id, page);
    error.value = '';
  } catch (e) {
    error.value = administrationError(e);
  } finally {
    loading.value = false;
  }
}
async function submit() {
  if (busy.value || pending.value || !data.value || !confirmed.value) return;
  try {
    if (
      cents(amount.value) <= 0n ||
      distributed.value === null ||
      cents(amount.value) !== cents(distributed.value)
    )
      throw Error('El total recibido debe coincidir con la distribución.');
    const perClient = new Map<string, bigint>();
    for (const row of contributions.value) {
      if (!row.client_id || !row.payer_client_id || cents(row.amount) <= 0n)
        throw Error('Completa todas las aportaciones con importes positivos.');
      perClient.set(row.client_id, (perClient.get(row.client_id) ?? 0n) + cents(row.amount));
    }
    for (const [clientId, total] of perClient) {
      const member = data.value.members.find((m) => m.client_id === clientId);
      if (!member || total > cents(member.balance))
        throw Error('La distribución supera el saldo de una integrante.');
    }
    busy.value = true;
    if (
      !(await alerts.confirm(
        'Confirmar pago recibido',
        `${money(amount.value)} en ${bank.value ? 'depósito bancario' : 'efectivo'}. ${data.value.late_fees?.enabled ? 'Se cubrirán cuotas exigibles, mora y después adelantos.' : 'Se cubrirán las cuotas más antiguas y después adelantos.'} Este registro no se puede editar.`,
        'Sí, registrar cobro',
      ))
    )
      return;
    pending.value = {
      id: createUuid(),
      balance_version: data.value.balance_version,
      amount: decimal(cents(amount.value)),
      method: bank.value ? 'bank_deposit' : 'cash_branch',
      bank_name: bank.value ? bankName.value.trim() : null,
      bank_reference: bank.value ? bankReference.value.trim() : null,
      deposited_on: bank.value ? depositedOn.value : null,
      notes: notes.value.trim() || null,
      confirmed: true,
      contributions: contributions.value.map((r) => ({
        client_id: r.client_id,
        payer_client_id: r.payer_client_id,
        amount: decimal(cents(r.amount)),
      })),
    };
    await savePending();
  } catch (e) {
    await alerts.error(e instanceof Error ? e.message : administrationError(e));
  } finally {
    busy.value = false;
  }
}
async function savePending() {
  if (!pending.value) return;
  try {
    await store.save(id, pending.value);
    pending.value = null;
    amount.value = '';
    bankReference.value = '';
    bankName.value = '';
    depositedOn.value = '';
    notes.value = '';
    confirmed.value = false;
    contributions.value = [];
    addContribution();
    error.value = '';
    await alerts.success('Cobro registrado; distribución disponible en el comprobante');
  } catch (e) {
    error.value = administrationError(e);
    if (
      isAxiosError(e) &&
      e.response &&
      [401, 403, 404, 409, 422, 429].includes(e.response.status)
    ) {
      pending.value = null;
      if (e.response.status === 409) {
        await store.load(id);
        confirmed.value = false;
      }
    }
    await alerts.error(error.value);
  }
}
async function retry() {
  if (busy.value) return;
  busy.value = true;
  try {
    await savePending();
  } finally {
    busy.value = false;
  }
}
const beforeUnload = (e: BeforeUnloadEvent) => {
  if (dirty.value || busy.value || pending.value) {
    e.preventDefault();
    e.returnValue = '';
  }
};
onBeforeRouteLeave(
  async () =>
    !busy.value &&
    (!(dirty.value || pending.value) ||
      (await alerts.confirm(
        'Cobro sin confirmar',
        pending.value
          ? 'Hay un cobro con respuesta incierta. Si sales, revisa el historial antes de capturarlo otra vez.'
          : '¿Deseas descartar la captura y salir?',
        'Salir',
      ))),
);
onMounted(async () => {
  store.clear();
  addContribution();
  window.addEventListener('beforeunload', beforeUnload);
  loading.value = true;
  try {
    loan.value = await creditService.get(id);
    await store.load(id);
  } catch (e) {
    error.value = administrationError(e);
  } finally {
    loading.value = false;
  }
});
onUnmounted(() => {
  store.clear();
  window.removeEventListener('beforeunload', beforeUnload);
});
</script>
<style scoped>
.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}
.contribution-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 160px 44px;
  align-items: start;
  gap: 12px;
  margin-bottom: 12px;
}
.payment-fields {
  border: 0;
  margin: 0;
  padding: 0;
  min-width: 0;
}
.payment-fields:disabled {
  pointer-events: none;
  opacity: 0.65;
}
.collection-table {
  width: 100%;
  min-width: 620px;
  border-collapse: collapse;
  text-align: left;
}
.collection-table td,
.collection-table th {
  padding: 14px;
  border-bottom: 1px solid #e5e7eb;
}
.collection-table th {
  color: #811335;
  background: #faf5f7;
}
.payment-id {
  overflow-wrap: anywhere;
  color: #626b7b;
}
@media (max-width: 900px) {
  .summary-grid,
  .form-grid,
  .contribution-row {
    grid-template-columns: 1fr;
  }
}
</style>
