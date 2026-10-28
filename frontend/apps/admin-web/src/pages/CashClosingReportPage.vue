<template>
  <q-page class="closing-report-page">
    <div class="report-actions">
      <q-btn flat no-caps icon="arrow_back" label="Volver al corte" :to="`/cortes/${id}`" />
      <div class="q-gutter-sm">
        <q-btn
          flat
          no-caps
          icon="refresh"
          label="Actualizar reporte"
          :disable="loading"
          @click="load"
        />
        <q-btn
          color="primary"
          no-caps
          icon="print"
          label="Imprimir o guardar PDF"
          :disable="!report || loading || !!error"
          @click="printReport"
        />
      </div>
    </div>
    <q-linear-progress v-if="loading" indeterminate aria-label="Preparando reporte completo" />
    <q-banner v-if="error" class="report-error bg-red-1 text-negative" role="alert">
      {{ error }}
      <template #action
        ><q-btn flat no-caps label="Reintentar" :disable="loading" @click="load"
      /></template>
    </q-banner>
    <p v-if="!report" class="print-unavailable">
      {{
        loading
          ? 'Preparando el reporte completo. Espera antes de imprimir.'
          : 'Reporte no disponible. Vuelve a consultar el corte antes de imprimir.'
      }}
    </p>
    <article v-if="report" class="report-paper">
      <header class="report-header">
        <img :src="logoUrl" alt="Crece Impulso" class="report-logo" />
        <div>
          <p class="eyebrow">CORTE DE CAJA · REPORTE COMPLETO</p>
          <h1>{{ report.record.folio }}</h1>
        </div>
      </header>
      <dl class="report-metadata">
        <div>
          <dt>Sucursal</dt>
          <dd>{{ report.record.branch_name }}</dd>
        </div>
        <div>
          <dt>Cajero responsable</dt>
          <dd>{{ report.record.actor_name }}</dd>
        </div>
        <div>
          <dt>Día de recepción de cobros</dt>
          <dd>{{ report.record.business_date }}</dd>
        </div>
        <div>
          <dt>Cierre original · hora de Cancún</dt>
          <dd>{{ report.record.closed_at_local }}</dd>
        </div>
      </dl>
      <h2>Totales originales del corte</h2>
      <div class="report-totals">
        <div>
          <span>Efectivo registrado</span><strong>{{ money(report.record.cash_total) }}</strong>
        </div>
        <div>
          <span>Depósitos bancarios</span><strong>{{ money(report.record.bank_total) }}</strong>
        </div>
        <div>
          <span>Efectivo contado</span><strong>{{ money(report.record.cash_counted) }}</strong>
        </div>
        <div>
          <span>Diferencia de efectivo</span
          ><strong>{{ money(report.record.cash_difference) }}</strong>
        </div>
      </div>
      <p class="report-note">
        {{ report.record.payment_count }} cobros incluidos. Diferencia positiva: sobrante; negativa:
        faltante. Importes en MXN.
      </p>
      <h2>Observaciones del cierre</h2>
      <p class="preserve-text">{{ report.record.notes || 'Sin observaciones.' }}</p>
      <section v-for="method in methods" :key="method.value">
        <h2>
          {{ method.label }} <small>· {{ paymentsFor(method.value).length }} cobros</small>
        </h2>
        <div class="report-scroll">
          <table class="report-table" :aria-label="method.label">
            <thead>
              <tr>
                <th>Crédito y pago</th>
                <th>Recepción</th>
                <template v-if="method.value === 'bank_deposit'"
                  ><th>Banco y folio</th>
                  <th>Fecha de depósito</th></template
                >
                <th class="amount">Importe</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="payment in paymentsFor(method.value)" :key="payment.id">
                <td>
                  {{ payment.credit_folio }}<br />Pago #{{ payment.number }}
                  <span v-if="reversedIds.has(payment.id)" class="reversed-label"
                    >Revertido posteriormente</span
                  >
                </td>
                <td>{{ payment.received_on }}</td>
                <template v-if="method.value === 'bank_deposit'"
                  ><td>{{ payment.bank_name }}<br />{{ payment.bank_reference }}</td>
                  <td>{{ payment.deposited_on }}</td></template
                >
                <td class="amount">
                  {{ money(payment.amount) }}<br /><small
                    >Cuotas: {{ money(payment.installment_amount ?? payment.amount) }} · Mora:
                    {{ money(payment.late_fee_amount ?? '0.00') }}</small
                  >
                </td>
              </tr>
              <tr v-if="!paymentsFor(method.value).length">
                <td :colspan="method.value === 'bank_deposit' ? 5 : 3">
                  Sin cobros de este medio en el corte.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="method-total">
          Total original de {{ method.value === 'bank_deposit' ? 'depósitos' : 'efectivo' }}:
          <strong>{{
            money(
              method.value === 'bank_deposit' ? report.record.bank_total : report.record.cash_total,
            )
          }}</strong>
        </p>
      </section>
      <section>
        <h2>Reversos posteriores y ajustes separados</h2>
        <p>
          Los pagos originales y los totales del cierre se conservan. Los reversos listados corrigen
          el registro del pago; no acreditan una devolución física de dinero ni cambian el efectivo
          contado.
        </p>
        <p v-if="!report.adjustments.length">
          Sin reversos registrados para este corte al consultar el reporte.
        </p>
        <template v-else>
          <p>
            <strong
              >{{ report.adjustment_totals.count }}
              {{ report.adjustment_totals.count === 1 ? 'reverso' : 'reversos' }}.</strong
            >
            Efectivo revertido: {{ money(report.adjustment_totals.cash_total) }}. Depósitos
            revertidos: {{ money(report.adjustment_totals.bank_total) }}.
          </p>
          <div v-for="adjustment in report.adjustments" :key="adjustment.id" class="adjustment">
            <h3>{{ adjustment.credit_folio }} · Pago #{{ adjustment.number }}</h3>
            <p>
              <strong>Importe revertido: {{ money(adjustment.amount) }}</strong> ·
              {{
                adjustment.method === 'bank_deposit' ? 'Depósito bancario' : 'Efectivo en sucursal'
              }}
              · {{ adjustment.reversed_on }}
            </p>
            <p>Responsable: {{ adjustment.actor_name }}</p>
            <p class="preserve-text">Motivo: {{ adjustment.reason }}</p>
          </div>
        </template>
      </section>
      <footer>
        <p>
          Reporte consultado: {{ report.generated_at_local }} · Hora de Cancún. Los reversos
          registrados después de esta consulta se mostrarán al actualizar.
        </p>
        <p>
          Este documento cierra ingresos de cobranza. No es un arqueo completo de la sucursal, una
          conciliación bancaria ni una constancia de entrega física a Tesorería. Reimprimir no
          registra otro corte.
        </p>
      </footer>
    </article>
  </q-page>
</template>
<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { cashService, type ClosingReport } from '../services/cash.service';
import { administrationError } from '../services/administration.service';
import { money } from '../domain/credit-calculation';
const route = useRoute();
const id = computed(() => String((route.params as { id?: string }).id ?? ''));
const report = ref<ClosingReport | null>(null);
const loading = ref(false),
  error = ref('');
const logoUrl = `${import.meta.env.BASE_URL}crece-impulso-logo.png`;
const methods = [
  { value: 'cash_branch', label: 'Efectivo en sucursal' },
  { value: 'bank_deposit', label: 'Depósitos bancarios' },
];
const reversedIds = computed(
  () => new Set(report.value?.adjustments.map((a) => a.credit_payment_id) ?? []),
);
const paymentsFor = (method: string) =>
  report.value?.payments.filter((p) => p.method === method) ?? [];
let controller: AbortController | null = null;
async function load() {
  controller?.abort();
  const request = new AbortController();
  controller = request;
  report.value = null;
  error.value = '';
  loading.value = true;
  try {
    const data = await cashService.report(id.value, request.signal);
    if (!request.signal.aborted) report.value = data;
  } catch (e) {
    if (!request.signal.aborted) error.value = administrationError(e);
  } finally {
    if (!request.signal.aborted) loading.value = false;
  }
}
function printReport() {
  if (report.value && !loading.value && !error.value) window.print();
}
watch(id, load, { immediate: true });
onUnmounted(() => controller?.abort());
</script>
<style scoped>
.closing-report-page {
  background: #f4f4f7;
  padding: 24px;
}
.report-actions {
  max-width: 1020px;
  margin: 0 auto 20px;
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}
.report-paper {
  max-width: 1020px;
  margin: auto;
  background: white;
  padding: 36px;
  border: 1px solid #e2e3e8;
  border-radius: 16px;
  color: #272c38;
}
.report-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}
.report-logo {
  width: 240px;
  max-width: 100%;
  height: auto;
}
h1 {
  font-size: 25px;
  line-height: 1.3;
  font-weight: 700;
  margin: 8px 0;
  overflow-wrap: anywhere;
}
h2 {
  font-size: 18px;
  line-height: 1.4;
  font-weight: 700;
  margin: 28px 0 12px;
}
h3 {
  font-size: 15px;
  line-height: 1.5;
  font-weight: 700;
  margin: 0 0 8px;
}
h2 small {
  font-size: 13px;
  font-weight: 400;
}
.eyebrow {
  color: #811335;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
}
.report-metadata,
.report-totals {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
  margin: 24px 0;
}
dt,
.report-totals span {
  font-size: 12px;
  color: #606776;
  display: block;
}
dd {
  margin: 5px 0 0;
  overflow-wrap: anywhere;
}
.report-totals {
  padding: 18px;
  background: #faf5f7;
  border-radius: 12px;
}
.report-totals strong {
  display: block;
  font-size: 23px;
  color: #811335;
  margin-top: 5px;
}
.report-note,
footer {
  font-size: 12px;
  color: #606776;
}
.preserve-text {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.report-scroll {
  overflow-x: auto;
}
.report-table {
  border-collapse: collapse;
  width: 100%;
  min-width: 550px;
  text-align: left;
}
th,
td {
  border: 1px solid #dfe1e7;
  padding: 10px;
  vertical-align: top;
  overflow-wrap: anywhere;
}
th {
  background: #faf5f7;
  color: #811335;
  font-size: 12px;
}
td {
  font-size: 12px;
}
.amount {
  text-align: right;
  white-space: nowrap;
}
.reversed-label {
  display: block;
  color: #9e2635;
  font-size: 11px;
  margin-top: 4px;
}
.method-total {
  text-align: right;
  margin-top: 12px;
}
.adjustment {
  border-top: 1px solid #e2e3e8;
  padding: 14px 0;
}
.adjustment p {
  margin: 5px 0;
}
footer {
  margin-top: 28px;
  border-top: 1px solid #ddd;
  padding-top: 16px;
}
@media (max-width: 700px) {
  .closing-report-page {
    padding: 12px;
  }
  .report-paper {
    padding: 18px;
  }
  .report-header {
    align-items: flex-start;
    flex-direction: column;
  }
  .report-metadata,
  .report-totals {
    grid-template-columns: 1fr;
  }
}
@media print {
  @page {
    size: A4;
    margin: 14mm;
  }
  .closing-report-page {
    padding: 0;
    background: white;
  }
  .report-actions,
  .report-error,
  .q-linear-progress {
    display: none !important;
  }
  .report-paper {
    border: 0;
    border-radius: 0;
    max-width: none;
    padding: 0;
  }
  .report-header {
    flex-direction: row;
  }
  .report-logo {
    width: 180px;
  }
  .report-metadata,
  .report-totals {
    grid-template-columns: 1fr 1fr;
    margin: 14px 0;
  }
  .report-totals {
    break-inside: avoid;
    padding: 12px;
  }
  .report-scroll {
    overflow: visible;
  }
  .report-table {
    min-width: 0;
    table-layout: fixed;
  }
  th,
  td {
    padding: 6px;
    font-size: 9pt;
  }
  .amount {
    white-space: normal;
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
    margin: 18px 0 10px;
  }
  footer {
    margin-top: 14px;
    padding-top: 10px;
  }
  .adjustment {
    break-inside: auto;
  }
  .report-paper {
    font-size: 10pt;
  }
}
</style>
