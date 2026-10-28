<template>
  <section class="demo-space">
    <q-banner rounded class="demo-notice"
      ><strong>VISTA DE EJEMPLO · DATOS FICTICIOS</strong>
      <div>Escenario al {{ date(data.as_of) }}. No representa una deuda real.</div></q-banner
    >
    <q-tabs
      v-model="tab"
      dense
      active-color="primary"
      indicator-color="primary"
      class="q-mt-md"
      align="justify"
      ><q-tab name="summary" label="Mi crédito" /><q-tab name="calendar" label="Calendario" /><q-tab
        name="payments"
        label="Mis pagos" /><q-tab name="help" label="Ayuda"
    /></q-tabs>
    <q-tab-panels v-model="tab" animated class="demo-panels">
      <q-tab-panel name="summary">
        <article class="credit-hero">
          <div class="eyebrow">MI CRÉDITO ACTIVO</div>
          <div class="credit-amount">{{ money(data.loan.balance) }}</div>
          <p>Saldo de capital e interés pactado · mora aparte</p>
          <q-linear-progress
            :value="data.loan.paid / data.loan.total"
            color="white"
            track-color="pink-3"
            rounded
            size="8px"
          />
          <div class="q-mt-sm">
            {{ money(data.loan.paid) }} pagados de {{ money(data.loan.total) }}
          </div>
        </article>
        <div class="metric-grid">
          <q-card flat bordered class="q-pa-md"
            ><span>Cuota semanal</span><strong>{{ money(data.loan.weekly) }}</strong
            ><small>16 semanas</small></q-card
          ><q-card flat bordered class="q-pa-md"
            ><span>Siguiente vencimiento</span><strong>{{ date(data.loan.next_due) }}</strong
            ><small>Semana 6</small></q-card
          >
        </div>
        <q-card flat bordered class="q-pa-lg q-mt-md"
          ><div class="text-h6">Importe exigible al día del ejemplo</div>
          <div class="amount-row">
            <span>Cuotas anteriores vencidas</span><b>{{ money(data.loan.overdue) }}</b>
          </div>
          <div class="amount-row">
            <span>Cuota que vence hoy</span><b>{{ money(data.loan.due_today) }}</b>
          </div>
          <div class="amount-row">
            <span>Mora acumulada</span><b>{{ money(data.loan.late_fees) }}</b>
          </div>
          <q-separator />
          <div class="amount-row text-primary">
            <strong>Total exigible</strong><strong>{{ money(data.loan.payable) }}</strong>
          </div>
          <p class="text-negative q-mb-none">
            {{ data.loan.days_overdue }} días desde la cuota pendiente más antigua.
          </p></q-card
        >
        <q-expansion-item label="Condiciones de mi crédito" icon="description" class="detail-card"
          ><div class="q-pa-md">
            <div v-for="item in conditions" :key="item[0]" class="amount-row">
              <span>{{ item[0] }}</span
              ><b>{{ item[1] }}</b>
            </div>
            <p>
              Los adelantos conservan el interés pactado. Se cubren cuotas pendientes, después mora
              y finalmente cuotas futuras.
            </p>
          </div></q-expansion-item
        >
        <q-expansion-item label="Mi garantía retenida" icon="shield" class="detail-card"
          ><div class="q-pa-md">
            <div class="text-h5">{{ money(data.loan.guarantee) }}</div>
            <p>
              10% del capital aprobado. Permanece retenida; su aplicación a cuotas pendientes se
              autoriza por Comercial a partir de la semana 16. No está descontada del saldo
              mostrado.
            </p>
          </div></q-expansion-item
        >
        <q-expansion-item label="Detalle de mis atrasos" icon="event_busy" class="detail-card"
          ><div class="q-pa-md">
            <p>
              Mora del 10% sobre el saldo pendiente al iniciar cada semana de atraso; no se cobra
              interés sobre la mora.
            </p>
            <div v-for="(fee, index) in data.fees" :key="index" class="history-row">
              <div>
                {{ date(fee.date) }} · cuota {{ fee.week
                }}<small>Saldo pendiente {{ money(fee.base) }}</small>
              </div>
              <strong>{{ money(fee.amount) }}</strong>
            </div>
          </div></q-expansion-item
        >
      </q-tab-panel>
      <q-tab-panel name="calendar"
        ><h2 class="text-h6 q-mt-none">Mis 16 pagos semanales</h2>
        <p>Primero se cubre la cuota pendiente más antigua.</p>
        <q-card
          v-for="row in data.schedule"
          :key="row.week"
          flat
          bordered
          class="schedule-card q-mb-sm"
          ><div class="history-row">
            <div>
              <strong>Semana {{ row.week }}</strong
              ><small>{{ date(row.due_on) }}</small>
            </div>
            <q-badge
              :color="
                row.status === 'Pagado'
                  ? 'teal'
                  : row.status.includes('encido')
                    ? 'negative'
                    : row.status === 'Vence hoy'
                      ? 'orange-9'
                      : 'grey-7'
              "
              >{{ row.status }}</q-badge
            >
          </div>
          <div class="amount-row">
            <span>Cuota {{ money(row.amount) }}</span
            ><b>Pendiente {{ money(row.remaining) }}</b>
          </div>
          <small v-if="row.paid">Abonado: {{ money(row.paid) }}</small></q-card
        ></q-tab-panel
      >
      <q-tab-panel name="payments"
        ><h2 class="text-h6 q-mt-none">Historial del crédito activo</h2>
        <p>
          Comprobantes de pagos recibidos. Selecciona uno para consultar sus datos y QR de ejemplo.
        </p>
        <q-card
          v-for="receipt in data.receipts"
          :key="receipt.id"
          flat
          bordered
          class="q-pa-md q-mb-md"
          ><div class="history-row">
            <div>
              <strong>{{ money(receipt.amount) }}</strong
              ><small>{{ date(receipt.date) }} · {{ receipt.method }}</small>
            </div>
            <q-icon name="check_circle" color="teal" size="24px" />
          </div>
          <div class="q-mt-sm">{{ receipt.id }}</div>
          <q-btn
            class="q-mt-md"
            outline
            color="primary"
            icon="qr_code_2"
            label="Ver comprobante"
            @click="openReceipt(receipt)" /></q-card
      ></q-tab-panel>
      <q-tab-panel name="help"
        ><h2 class="text-h6 q-mt-none">Tu sucursal te acompaña</h2>
        <q-card flat bordered class="q-pa-lg"
          ><p>
            <strong>Pagos en sucursal</strong><br />Realiza tu pago en caja y conserva el
            comprobante de la empresa.
          </p>
          <p>
            <strong>Depósitos bancarios</strong><br />Lleva la ficha a Caja para que registren el
            folio y apliquen el pago. Depositar no actualiza automáticamente el saldo.
          </p>
          <p>
            <strong>Si necesitas una aclaración</strong><br />Indica tu número de cliente y el folio
            del comprobante. Esta app no recibe pagos ni solicita transferencias.
          </p>
          <p class="q-mb-none">
            Los datos de contacto de la sucursal se incorporarán cuando estén configurados.
          </p></q-card
        ></q-tab-panel
      >
    </q-tab-panels>
    <q-dialog v-model="receiptOpen"
      ><q-card class="receipt-card"
        ><q-card-section
          ><div class="row items-center justify-between">
            <strong>Comprobante de ejemplo</strong
            ><q-btn flat round icon="close" v-close-popup aria-label="Cerrar comprobante" />
          </div>
          <q-banner class="demo-notice q-mt-md">DEMO · SIN VALIDEZ DE PAGO</q-banner>
          <div class="text-h4 q-my-lg">{{ money(selected?.amount || 0) }}</div>
          <p>{{ selected?.id }} · {{ selected ? date(selected.date) : '' }}</p>
          <p>{{ selected?.method }}</p>
          <p v-if="selected?.reference">Folio bancario: {{ selected.reference }}</p>
          <p>
            Aplicado a cuotas del crédito {{ data.loan.folio }}. Sin cargos de mora pagados en este
            ejemplo.
          </p>
          <img
            v-if="qr"
            :src="qr"
            alt="QR de verificación del comprobante ficticio"
            class="receipt-qr"
          />
          <p class="text-caption">
            El QR abre una verificación firmada de este ejemplo. No acredita un pago real. Requiere
            acceso a la red local.
          </p></q-card-section
        ></q-card
      ></q-dialog
    >
  </section>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import QRCode from 'qrcode';
import Swal from 'sweetalert2';
import type { DemoData, DemoReceipt } from '../services/demo.service';
const props = defineProps<{ data: DemoData }>();
const tab = ref('summary');
const receiptOpen = ref(false);
const selected = ref<DemoReceipt | null>(null);
const qr = ref('');
const money = (value: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value / 100);
const date = (value: string) =>
  new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }).format(
    new Date(`${value}T12:00:00`),
  );
const conditions = computed(() => [
  ['Folio', props.data.loan.folio],
  ['Grupo', props.data.loan.group],
  ['Mi rol', props.data.loan.role],
  ['Capital aprobado', money(props.data.loan.principal)],
  ['Interés pactado', money(props.data.loan.interest)],
  ['Total del crédito', money(props.data.loan.total)],
  ['Recibido al desembolso', money(props.data.loan.delivered)],
  ['Tasa', props.data.loan.rate],
  ['Primer pago', date(props.data.loan.first_due)],
  ['Último pago', date(props.data.loan.last_due)],
]);
async function openReceipt(receipt: DemoReceipt) {
  try {
    qr.value = await QRCode.toDataURL(receipt.verification_url, { width: 260, margin: 2 });
    selected.value = receipt;
    receiptOpen.value = true;
  } catch {
    await Swal.fire({
      icon: 'error',
      title: 'No se pudo preparar el QR',
      text: 'Inténtalo nuevamente.',
    });
  }
}
</script>
<style scoped>
.demo-space {
  margin-top: 24px;
}
.demo-notice {
  background: #fff3d9;
  color: #654a16;
  font-size: 13px;
}
.demo-panels {
  background: transparent;
}
.demo-panels :deep(.q-tab-panel) {
  padding: 20px 0;
}
.credit-hero {
  background: linear-gradient(125deg, #741c37, #9d3153);
  color: white;
  padding: 26px;
  border-radius: 20px;
}
.credit-amount {
  font-size: 38px;
  font-weight: 650;
  letter-spacing: -1px;
  margin-top: 12px;
}
.metric-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 16px;
}
.metric-grid strong,
.metric-grid small {
  display: block;
  margin-top: 8px;
}
.metric-grid strong {
  font-size: 19px;
}
.metric-grid span,
.metric-grid small {
  color: #666;
}
.amount-row {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 0;
  font-size: 14px;
}
.amount-row b {
  text-align: right;
}
.detail-card {
  background: white;
  border: 1px solid #ddd;
  border-radius: 12px;
  margin-top: 14px;
}
.history-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
}
.history-row small {
  display: block;
  color: #666;
  margin-top: 6px;
}
.schedule-card {
  padding: 12px 16px;
}
.receipt-card {
  width: 420px;
  max-width: 94vw;
  border-radius: 16px;
}
.receipt-qr {
  display: block;
  max-width: 100%;
  margin: auto;
}
.demo-space :deep(.q-tab__label) {
  font-size: 12px;
}
.demo-space :deep(.q-tab) {
  padding: 0 5px;
}
</style>
