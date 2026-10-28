<template>
  <q-page class="statement-page">
    <div class="statement-controls">
      <q-btn
        flat
        no-caps
        icon="arrow_back"
        label="Volver al crédito"
        :to="`/solicitudes-credito/${id}`"
      />
      <div class="q-gutter-sm">
        <q-btn
          flat
          no-caps
          icon="refresh"
          label="Actualizar"
          :disable="loading"
          @click="store.load(id)"
        />
        <q-btn
          color="primary"
          no-caps
          icon="print"
          label="Imprimir o guardar PDF"
          :disable="!data || loading || !!error || invalidMember"
          @click="print"
        />
      </div>
      <FilterSelect
        v-if="data"
        v-model="selected"
        outlined
        emit-value
        map-options
        label="Consultar grupo o integrante"
        :options="options"
        class="member-filter"
      />
    </div>
    <q-linear-progress v-if="loading" indeterminate />
    <q-banner v-if="error" class="bg-red-1 text-negative" role="alert"
      >{{ error
      }}<template #action
        ><q-btn flat no-caps label="Reintentar" @click="store.load(id)" /></template
    ></q-banner>
    <q-banner v-if="invalidMember" class="bg-red-1 text-negative" role="alert"
      >La integrante seleccionada no pertenece a este crédito. Selecciona una opción
      válida.</q-banner
    >
    <p v-if="!data" class="unavailable">
      {{
        loading
          ? 'Consultando saldos y movimientos…'
          : 'Estado de cuenta no disponible. Actualiza antes de imprimir.'
      }}
    </p>
    <article v-if="data && !invalidMember" class="statement-paper">
      <header>
        <img :src="logoUrl" alt="Crece Impulso" />
        <div>
          <p class="eyebrow">ESTADO DE CUENTA {{ selected ? 'INDIVIDUAL' : 'GRUPAL' }}</p>
          <h1>{{ data.credit.folio }}</h1>
        </div>
      </header>
      <h2 class="account-title">{{ selectedMember?.name ?? data.credit.group }}</h2>
      <p v-if="selectedMember">
        {{ selectedMember.folio }} · {{ role(selectedMember.role_in_group) }} · Grupo
        {{ data.credit.group }}
      </p>
      <p>
        {{ data.credit.branch }} · Crédito
        {{ data.credit.status === 'pagado' ? 'pagado' : 'entregado' }} · Entrega:
        {{ data.credit.disbursed_on }}
      </p>
      <p>Consulta al {{ data.as_of }} · Hora de Cancún: {{ data.generated_at_local }}</p>
      <div class="balance-grid">
        <div v-for="item in cards" :key="item.label">
          <span>{{ item.label }}</span
          ><strong :class="item.warning ? 'warning' : ''">{{ money(item.amount) }}</strong>
        </div>
      </div>
      <p class="balance-note">
        <strong>Días de atraso: {{ daysOverdue }}.</strong> Lo exigible incluye cuotas vencidas y
        las de hoy. El atraso empieza al día siguiente del vencimiento. Estos importes están
        incluidos en el saldo pendiente; no deben sumarse de nuevo.
      </p>
      <p>
        <strong>Próximo vencimiento pendiente:</strong>
        {{ nextDue ?? 'No hay cuotas futuras pendientes.' }}
      </p>
      <section
        v-if="data.late_fee_preview"
        class="late-fee-preview"
        aria-label="Vista previa de mora"
      >
        <div class="late-fee-heading">
          <div>
            <span class="preview-badge">SIMULACIÓN · NO COBRABLE</span>
            <h2>Mora semanal estimada</h2>
          </div>
          <strong class="late-fee-total">{{ money(lateFeeTotal) }}</strong>
        </div>
        <p>
          10% del saldo pendiente de cada cuota al comenzar cada semana de atraso: días 1, 8, 15 y
          siguientes. No se cobra mora sobre mora.
        </p>
        <p class="balance-note">
          Esta vista previa no se suma al saldo ni al exigible y no genera cargos, tickets o
          movimientos de Caja. Incluso si las cuotas aparecen pagadas, puede mostrar mora histórica
          simulada. Los abonos posteriores no eliminan la mora de semanas anteriores.
        </p>
        <p v-if="!lateFeeRows.length">
          No se estima mora para esta selección a la fecha de consulta.
        </p>
        <details v-else class="late-fee-details">
          <summary>
            Ver desglose de {{ lateFeeRows.length }}
            {{ lateFeeRows.length === 1 ? 'cuota' : 'cuotas' }} con mora estimada
          </summary>
          <div class="statement-scroll">
            <table aria-label="Desglose de mora simulada">
              <thead>
                <tr>
                  <th>Integrante / cuota</th>
                  <th>Semana de atraso</th>
                  <th>Inicio</th>
                  <th>Base pendiente</th>
                  <th>Mora estimada</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="row in lateFeeRows" :key="`${row.client_id}-${row.number}`">
                  <tr
                    v-for="charge in row.charges"
                    :key="`${row.client_id}-${row.number}-${charge.week}`"
                  >
                    <td>
                      {{ memberName(row.client_id) }}<br /><small
                        >Cuota {{ row.number }} · Vence {{ row.due_on }}</small
                      >
                    </td>
                    <td>{{ charge.week }}</td>
                    <td>{{ charge.charged_on }}</td>
                    <td>{{ money(charge.base) }}</td>
                    <td>{{ money(charge.amount) }}</td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </details>
        <p class="balance-note">
          Se consideran pagos registrados y aplicaciones de garantía vigentes, excluyendo los
          revertidos. El cálculo toma el saldo al inicio del día, en horario de Cancún; un abono
          registrado ese día reduce la base de la siguiente semana. Para depósitos se utiliza la
          fecha de registro en Caja, no la fecha de la ficha bancaria.
        </p>
      </section>
      <section
        v-if="data.late_fees?.enabled"
        class="late-fee-preview"
        aria-label="Mora del crédito"
      >
        <h2>Mora semanal del crédito</h2>
        <p>
          10% sobre el saldo pendiente de cada cuota al inicio de cada semana de atraso. Cuotas
          exigibles → mora → adelantos. Sin mora sobre mora.
        </p>
        <p>
          <strong
            >Generada: {{ money(feeSum('amount')) }} · Pagada: {{ money(feeSum('paid')) }} ·
            Pendiente: {{ money(feeSum('balance')) }}</strong
          >
        </p>
        <p>
          La mora pendiente ya está incluida en el saldo y en el exigible mostrados arriba; no la
          sumes de nuevo. Los cargos semanales conservados no se recalculan al revertir un pago.
        </p>
        <details v-if="activeFeeCharges.length">
          <summary>Ver cargos semanales</summary>
          <div class="statement-scroll">
            <table aria-label="Cargos de mora">
              <thead>
                <tr>
                  <th>Integrante / cuota</th>
                  <th>Inicio</th>
                  <th>Base</th>
                  <th>Cargo</th>
                  <th>Pagado</th>
                  <th>Pendiente</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="fee in activeFeeCharges" :key="fee.id">
                  <td>{{ memberName(fee.client_id) }} · {{ fee.number }}</td>
                  <td>{{ fee.charged_on }}</td>
                  <td>{{ money(fee.base) }}</td>
                  <td>{{ money(fee.amount) }}</td>
                  <td>{{ money(fee.paid) }}</td>
                  <td>{{ money(fee.balance) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </details>
      </section>
      <h2>Condiciones aprobadas</h2>
      <div class="statement-scroll">
        <table aria-label="Condiciones aprobadas">
          <thead>
            <tr>
              <th>Capital</th>
              <th>Interés pactado</th>
              <th>Cuota semanal</th>
              <th>Garantía retenida</th>
              <th>Entrega neta</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>{{ money(total('principal')) }}</td>
              <td>{{ money(total('interest')) }}</td>
              <td>{{ money(total('weekly_payment')) }}</td>
              <td>{{ money(total('guarantee')) }}</td>
              <td>{{ money(total('net_delivery')) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p>
        Tasa {{ data.credit.rate_name }} · Factor {{ money(data.credit.rate_factor) }} por cada
        $1,000 por semana · 16 semanas. Pagos: {{ method(data.credit.repayment_method) }}.
      </p>
      <p class="balance-note">
        La retención no es un abono. Solo una aplicación autorizada de garantía reduce el saldo. Los
        anticipos conservan el interés pactado. Este estado presenta los saldos actuales; no
        reconstruye un saldo histórico a una fecha anterior.
      </p>
      <section v-if="!selected">
        <h2>Saldo por integrante</h2>
        <div class="statement-scroll">
          <table aria-label="Saldo por integrante">
            <thead>
              <tr>
                <th>Integrante</th>
                <th>Aplicado a cuotas</th>
                <th>Mora pendiente</th>
                <th>Saldo pendiente</th>
                <th>Exigible</th>
                <th>Atraso</th>
                <th>Días</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="member in members" :key="member.client_id">
                <td>
                  {{ member.name }}<br /><small
                    >{{ member.folio }} · {{ role(member.role_in_group) }}</small
                  >
                </td>
                <td>{{ money(member.paid) }}</td>
                <td>{{ money(member.late_fee_balance ?? '0.00') }}</td>
                <td>{{ money(member.balance) }}</td>
                <td>{{ money(member.due) }}</td>
                <td>{{ money(member.overdue) }}</td>
                <td>{{ member.days_overdue }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <h2>Calendario de las 16 semanas</h2>
      <div class="statement-scroll">
        <table aria-label="Calendario de pagos">
          <thead>
            <tr>
              <th>Semana</th>
              <th>Vencimiento</th>
              <th>Cuota</th>
              <th>Aplicado a cuotas</th>
              <th>Mora pendiente</th>
              <th>Pendiente</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in schedule" :key="row.number">
              <td>{{ row.number }}</td>
              <td>{{ row.due_on }}</td>
              <td>{{ money(decimal(row.due)) }}</td>
              <td>{{ money(decimal(row.paid)) }}</td>
              <td>{{ money(decimal(row.due - row.paid)) }}</td>
              <td :class="row.due_on < data.as_of && row.due > row.paid ? 'warning' : ''">
                {{ installmentStatus(row) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <h2 v-if="data.guarantees?.length">Aplicaciones de garantía</h2>
      <p v-if="data.guarantees?.length">
        El aplicado vigente incluye pagos y garantías aplicadas. Estas últimas no son efectivo ni
        depósitos recibidos.
      </p>
      <section v-for="event in data.guarantees" :key="event.id" class="payment-entry">
        <template v-if="!selected || event.allocations.some((a) => a.client_id === selected)">
          <strong>Aplicación de garantía · {{ event.applied_on }}</strong>
          <p v-if="event.reversed_on" class="warning">
            REVERTIDA · {{ event.reversed_on }} · Sin efecto en el saldo actual.
          </p>
          <p
            v-for="(allocation, index) in event.allocations.filter(
              (a) => !selected || a.client_id === selected,
            )"
            :key="index"
          >
            {{ data.members.find((m) => m.client_id === allocation.client_id)?.name }} · Semana
            {{ allocation.number }} · {{ allocation.due_on }} · {{ money(allocation.amount) }}
          </p>
        </template>
      </section>
      <h2>Historial de pagos y aportaciones</h2>
      <p v-if="!payments.length">
        Sin pagos registrados{{ selected ? ' aplicados a esta integrante' : '' }}.
      </p>
      <p v-else class="balance-note">
        Se distingue quién aportó el dinero y a qué deuda se aplicó. Los pagos revertidos se
        conservan como antecedente y no forman parte del pagado vigente.
      </p>
      <section v-for="payment in payments" :key="payment.id" class="payment-entry">
        <h3>{{ payment.folio }}</h3>
        <p>
          {{ payment.received_on }} · {{ method(payment.method) }} · Total del recibo:
          <strong>{{ money(payment.amount) }}</strong>
        </p>
        <p v-if="selected">
          Aplicación {{ payment.reversed_on ? 'original' : 'vigente' }} a esta integrante:
          <strong>{{ money(appliedAmount(payment)) }}</strong>
        </p>
        <p v-if="payment.reversed_on" class="warning">
          <strong>PAGO REVERTIDO · {{ payment.reversed_on }}</strong> · Aplicación original sin
          efecto en el saldo actual.
        </p>
        <div class="statement-scroll">
          <table aria-label="Aplicación del pago">
            <thead>
              <tr>
                <th>Aporta</th>
                <th>Se aplica a</th>
                <th>Semana y vencimiento</th>
                <th>Importe aplicado</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(allocation, index) in allocationsFor(payment)" :key="index">
                <td>{{ memberName(allocation.payer_client_id) }}</td>
                <td>{{ memberName(allocation.client_id) }}</td>
                <td>
                  {{ allocation.kind === 'late_fee' ? 'Mora de cuota' : 'Cuota' }}
                  {{ allocation.number }} · {{ allocation.due_on
                  }}<br v-if="allocation.charged_on" /><small v-if="allocation.charged_on"
                    >Cargo {{ allocation.charged_on }}</small
                  >
                </td>
                <td>{{ money(allocation.amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <q-btn
          v-if="canReadReceipts"
          flat
          no-caps
          icon="qr_code"
          label="Ver comprobante QR"
          :to="`/comprobantes/${payment.id}`"
          class="receipt-link q-mt-sm"
        />
      </section>
      <footer>
        <p>
          Los reversos pueden reabrir cuotas y atrasos sin redistribuir pagos posteriores. El saldo
          aquí mostrado incluye pagos y aplicaciones de garantía vigentes.
        </p>
        <p>
          Documento de consulta. No sustituye los comprobantes de pago ni registra nuevos
          movimientos. Los cambios posteriores se reflejarán al actualizar.
        </p>
      </footer>
    </article>
  </q-page>
</template>
<script setup lang="ts">
import { computed, onUnmounted, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute, useRouter } from 'vue-router';
import FilterSelect from '../components/FilterSelect.vue';
import { useStatementStore } from '../stores/statement.store';
import { useAuthStore } from '../stores/auth.store';
import { cents, decimal, money } from '../domain/credit-calculation';
import type { StatementMember, StatementPayment } from '../services/statement.service';
const route = useRoute(),
  router = useRouter(),
  store = useStatementStore(),
  auth = useAuthStore();
const { data, loading, error } = storeToRefs(store);
const id = computed(() => String((route.params as { id?: string }).id ?? ''));
const selected = computed({
  get: () => (typeof route.query.cliente === 'string' ? route.query.cliente : ''),
  set: (value: string) => {
    void router.replace({ query: { ...route.query, cliente: value || undefined } });
  },
});
const selectedMember = computed(() =>
  data.value?.members.find((m) => m.client_id === selected.value),
);
const invalidMember = computed(() => !!data.value && !!selected.value && !selectedMember.value);
const members = computed(
  () => data.value?.members.filter((m) => !selected.value || m.client_id === selected.value) ?? [],
);
const options = computed(() => [
  { value: '', label: 'Todo el grupo' },
  ...(data.value?.members.map((m) => ({ value: m.client_id, label: `${m.name} · ${m.folio}` })) ??
    []),
]);
type AmountKey =
  | 'principal'
  | 'interest'
  | 'weekly_payment'
  | 'guarantee'
  | 'net_delivery'
  | 'total_repayment'
  | 'paid'
  | 'balance'
  | 'due'
  | 'overdue';
const total = (key: AmountKey) =>
  decimal(members.value.reduce((sum, m: StatementMember) => sum + cents(m[key]), 0n));
const cards = computed(() => [
  { label: 'Total pactado a devolver', amount: total('total_repayment'), warning: false },
  { label: 'Aplicado a cuotas', amount: total('paid'), warning: false },
  { label: 'Saldo pendiente', amount: total('balance'), warning: false },
  { label: 'Exigible a la fecha', amount: total('due'), warning: false },
  { label: 'Importe en atraso', amount: total('overdue'), warning: cents(total('overdue')) > 0n },
]);
const activeFeeCharges = computed(
  () =>
    data.value?.late_fees?.charges.filter(
      (fee) => (!selected.value || fee.client_id === selected.value) && cents(fee.amount) > 0n,
    ) ?? [],
);
const feeSum = (key: 'amount' | 'paid' | 'balance') =>
  decimal(activeFeeCharges.value.reduce((sum, fee) => sum + cents(fee[key]), 0n));
const lateFeeRows = computed(
  () =>
    data.value?.late_fee_preview?.installments.filter(
      (row) => !selected.value || row.client_id === selected.value,
    ) ?? [],
);
const lateFeeTotal = computed(() =>
  decimal(lateFeeRows.value.reduce((sum, row) => sum + cents(row.amount), 0n)),
);
const daysOverdue = computed(() => Math.max(0, ...members.value.map((m) => m.days_overdue)));
type Week = { number: number; due_on: string; due: bigint; paid: bigint };
const schedule = computed(() => {
  const weeks = new Map<number, Week>();
  for (const row of data.value?.installments ?? []) {
    if (selected.value && row.client_id !== selected.value) continue;
    const week = weeks.get(row.number) ?? {
      number: row.number,
      due_on: row.due_on,
      due: 0n,
      paid: 0n,
    };
    week.due += cents(row.amount_due);
    week.paid += cents(row.amount_paid);
    weeks.set(row.number, week);
  }
  return [...weeks.values()].sort((a, b) => a.number - b.number);
});
const nextDue = computed(
  () => schedule.value.find((r) => r.due_on >= (data.value?.as_of ?? '') && r.due > r.paid)?.due_on,
);
function installmentStatus(row: Week) {
  if (row.due === row.paid) return 'Pagada';
  const stage =
    row.due_on < (data.value?.as_of ?? '')
      ? 'Vencida'
      : row.due_on === data.value?.as_of
        ? 'Vence hoy'
        : 'Por vencer';
  return row.paid > 0n ? `Parcial · ${stage}` : stage;
}
const payments = computed(
  () =>
    data.value?.payments.filter(
      (p) => !selected.value || p.allocations.some((a) => a.client_id === selected.value),
    ) ?? [],
);
const allocationsFor = (p: StatementPayment) =>
  p.allocations.filter((a) => !selected.value || a.client_id === selected.value);
const appliedAmount = (p: StatementPayment) =>
  decimal(allocationsFor(p).reduce((sum, a) => sum + cents(a.amount), 0n));
const memberName = (client: string) =>
  data.value?.members.find((m) => m.client_id === client)?.name ?? 'Integrante';
const canReadReceipts = computed(() => auth.can('credit.collect') || auth.can('credit.reverse'));
const role = (value: string) =>
  ({ presidenta: 'Presidenta', tesorera: 'Tesorera', integrante: 'Integrante' })[value] ?? value;
const method = (value: string) =>
  value === 'bank_deposit' ? 'Depósito bancario' : 'Efectivo en sucursal';
const logoUrl = `${import.meta.env.BASE_URL}crece-impulso-logo.png`;
function print() {
  if (data.value && !loading.value && !error.value && !invalidMember.value) window.print();
}
watch(id, (value) => store.load(value), { immediate: true });
onUnmounted(() => store.clear());
</script>
<style scoped>
.late-fee-preview {
  margin: 24px 0;
  padding: 20px;
  border: 1px solid #dfc4cd;
  border-radius: 12px;
  background: #fcf8fa;
}
.late-fee-heading {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  align-items: center;
}
.late-fee-heading h2 {
  margin: 8px 0;
}
.preview-badge {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.6px;
  color: #811335;
}
.late-fee-total {
  font-size: 24px;
  color: #811335;
}
.late-fee-details summary {
  cursor: pointer;
  padding: 12px 0;
  font-weight: 600;
}

.statement-page {
  background: #f4f4f7;
  padding: 24px;
}
.statement-controls {
  max-width: 1100px;
  margin: 0 auto 20px;
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 14px;
}
.member-filter {
  width: 100%;
}
.statement-paper {
  max-width: 1100px;
  margin: auto;
  padding: 32px;
  border: 1px solid #e3e4e8;
  border-radius: 16px;
  background: #fff;
  color: #282d39;
}
header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
}
header img {
  width: 230px;
  max-width: 100%;
  height: auto;
}
h1 {
  font-size: 26px;
  font-weight: 700;
  line-height: 1.4;
  margin: 8px 0;
  overflow-wrap: anywhere;
}
h2 {
  font-size: 19px;
  font-weight: 700;
  line-height: 1.4;
  margin: 26px 0 14px;
}
h3 {
  font-size: 15px;
  font-weight: 700;
  line-height: 1.5;
  margin: 12px 0;
  overflow-wrap: anywhere;
}
.eyebrow {
  font-size: 11px;
  color: #811335;
  letter-spacing: 0.09em;
  font-weight: 700;
}
.balance-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin: 24px 0;
}
.balance-grid > div {
  padding: 16px;
  border: 1px solid #eadde2;
  border-radius: 12px;
  background: #fcf8fa;
}
.balance-grid span {
  display: block;
  font-size: 12px;
  color: #656b78;
}
.balance-grid strong {
  display: block;
  font-size: 24px;
  margin-top: 8px;
  color: #811335;
}
.warning {
  color: #b12336 !important;
  font-weight: 600;
}
.balance-note,
footer {
  font-size: 12px;
  color: #606775;
}
.statement-scroll {
  overflow-x: auto;
}
table {
  width: 100%;
  min-width: 640px;
  border-collapse: collapse;
  text-align: left;
}
th,
td {
  padding: 11px;
  border: 1px solid #e1e3e8;
  font-size: 12px;
  overflow-wrap: anywhere;
}
th {
  background: #faf4f7;
  color: #811335;
}
.payment-entry {
  margin: 22px 0;
  border-top: 1px solid #e1e3e8;
  padding-top: 8px;
}
footer {
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid #ddd;
}
@media (max-width: 700px) {
  .statement-page {
    padding: 12px;
  }
  .statement-paper {
    padding: 18px;
  }
  header {
    flex-direction: column;
    align-items: flex-start;
  }
  .balance-grid {
    grid-template-columns: 1fr;
  }
}
@media print {
  @page {
    size: A4;
    margin: 14mm;
  }
  .statement-controls,
  .receipt-link,
  .q-linear-progress {
    display: none !important;
  }
  .statement-page {
    background: white;
    padding: 0;
  }
  .statement-paper {
    padding: 0;
    border: 0;
    font-size: 10pt;
  }
  header {
    flex-direction: row;
  }
  header img {
    width: 170px;
  }
  .balance-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    break-inside: avoid;
  }
  .balance-grid > div {
    padding: 10px;
  }
  .balance-grid strong {
    font-size: 18px;
  }
  .statement-scroll {
    overflow: visible;
  }
  table {
    min-width: 0;
    table-layout: fixed;
  }
  th,
  td {
    font-size: 9pt;
    padding: 6px;
  }
  thead {
    display: table-header-group;
  }
  tr {
    break-inside: avoid;
  }
  h2,
  h3 {
    break-after: avoid;
  }
  h2 {
    margin-top: 18px;
  }
}
</style>
