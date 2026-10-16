<template>
  <q-page class="receipt-page">
    <div class="receipt-actions">
      <q-btn flat no-caps icon="arrow_back" label="Volver a cobros" to="/cobros" /><q-btn
        color="primary"
        no-caps
        icon="print"
        label="Imprimir comprobante"
        :disable="!receipt || !qr || !!error || busy || !!pending"
        @click="printReceipt"
      />
    </div>
    <q-linear-progress v-if="loading" indeterminate />
    <q-banner v-if="error" class="bg-red-1 text-negative">{{ error }}</q-banner>
    <div
      v-if="receipt?.status === 'registered' && auth.can('credit.reverse')"
      class="receipt-actions reversal-form"
    >
      <q-banner rounded class="bg-orange-1"
        >Reverso completo: se restituirá el saldo de las cuotas originales. Si el crédito estaba
        liquidado, se reabrirá. El pago y sus cortes se conservan. Esto no devuelve dinero
        automáticamente.</q-banner
      ><q-input
        v-model="reason"
        outlined
        type="textarea"
        label="Motivo del reverso"
        maxlength="2000"
        :disable="busy || !!pending"
        class="full-width"
      /><q-btn
        v-if="!pending"
        no-caps
        color="negative"
        label="Revertir pago completo"
        :loading="busy"
        :disable="reason.trim().length < 10"
        @click="reverse"
      /><q-btn
        v-else
        no-caps
        color="negative"
        label="Reintentar el mismo reverso"
        :loading="busy"
        @click="retry"
      />
    </div>
    <article v-if="receipt" class="receipt-paper">
      <header>
        <img src="/crece-impulso-logo.png" alt="Crece Impulso" class="logo" />
        <p>COMPROBANTE DE PAGO RECIBIDO</p>
        <h1>{{ receipt.folio }}</h1>
      </header>
      <p class="receipt-amount">{{ money(receipt.amount) }} <small>MXN</small></p>
      <dl>
        <div>
          <dt>Crédito</dt>
          <dd>{{ receipt.credit_folio }}</dd>
        </div>
        <div>
          <dt>Fecha de recepción</dt>
          <dd>{{ receipt.received_on }}</dd>
        </div>
        <div>
          <dt>Medio de pago</dt>
          <dd>
            {{ receipt.method === 'bank_deposit' ? 'Depósito bancario' : 'Efectivo en sucursal' }}
          </dd>
        </div>
        <div>
          <dt>Recibió</dt>
          <dd>{{ receipt.actor_name }}</dd>
        </div>
        <template v-if="receipt.bank_reference"
          ><div>
            <dt>Banco / folio del depósito</dt>
            <dd>{{ receipt.bank_name }} · {{ receipt.bank_reference }}</dd>
          </div>
          <div>
            <dt>Fecha del depósito</dt>
            <dd>{{ receipt.deposited_on }}</dd>
          </div></template
        >
      </dl>
      <q-banner v-if="receipt.status === 'reversed'" rounded class="bg-red-1 text-negative"
        ><strong>PAGO REVERTIDO · {{ receipt.reversed_on }}</strong>
        <p>Este pago ya no abona al crédito. Se conserva su aplicación original como historial.</p>
        <p v-if="receipt.reversal">
          {{ receipt.reversal.actor_name }}: {{ receipt.reversal.reason }}
        </p></q-banner
      >
      <h2>
        {{
          receipt.status === 'reversed' ? 'Aplicación original revertida' : 'Aplicación del pago'
        }}
      </h2>
      <div class="receipt-scroll">
        <table>
          <thead>
            <tr>
              <th>Aporta</th>
              <th>Se aplica a</th>
              <th>Cuota / vencimiento</th>
              <th>Importe</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, index) in receipt.allocations" :key="index">
              <td>{{ row.payer }}</td>
              <td>{{ row.beneficiary }}</td>
              <td>{{ row.number }} · {{ row.due_on }}</td>
              <td>{{ money(row.amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <footer>
        <img v-if="qr" :src="qr" alt="QR para verificar este pago" width="220" height="220" />
        <div>
          <h2>Verifica tu comprobante</h2>
          <p>
            Escanea el QR y compara el folio, importe y fecha. La consulta necesita conexión a
            internet.
          </p>
          <a :href="receipt.verification_url" target="_blank" rel="noopener noreferrer"
            >Abrir verificación</a
          >
          <p class="text-caption">
            Este comprobante conserva el historial del pago y su estado actual; no acredita la
            liquidación de todo el crédito. Reimprimirlo no registra un nuevo pago.
          </p>
        </div>
      </footer>
    </article>
  </q-page>
</template>
<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { isAxiosError } from 'axios';
import { useAuthStore } from '../stores/auth.store';
import { alerts } from '../services/alerts.service';
import type { ReversalInput } from '../services/receipts.service';
import { useRoute } from 'vue-router';
import QRCode from 'qrcode';
import { receiptsService, type PaymentReceipt } from '../services/receipts.service';
import { administrationError } from '../services/administration.service';
import { money } from '../domain/credit-calculation';
const id = String((useRoute().params as { id: string }).id);
const receipt = ref<PaymentReceipt | null>(null),
  qr = ref(''),
  error = ref(''),
  loading = ref(false);
const auth = useAuthStore();
const reason = ref(''),
  busy = ref(false),
  pending = ref<ReversalInput | null>(null);
async function reverse() {
  if (busy.value || pending.value || !receipt.value || reason.value.trim().length < 10) return;
  busy.value = true;
  try {
    if (
      !(await alerts.confirm(
        'Revertir pago completo',
        `Se revertirá ${money(receipt.value.amount)} del comprobante ${receipt.value.folio}. Se conservará el pago original y se reabrirán sus cuotas. No es una devolución de dinero.`,
        'Sí, registrar reverso',
      ))
    )
      return;
    pending.value = {
      id: crypto.randomUUID(),
      balance_version: receipt.value.balance_version,
      reason: reason.value.trim(),
      confirmed: true,
    };
    await savePending();
  } finally {
    busy.value = false;
  }
}
async function savePending() {
  if (!pending.value) return;
  try {
    receipt.value = await receiptsService.reverse(id, pending.value);
    pending.value = null;
    reason.value = '';
    error.value = '';
    await alerts.success('Pago revertido; historial conservado');
  } catch (e) {
    error.value = administrationError(e);
    if (
      isAxiosError(e) &&
      e.response &&
      [401, 403, 404, 409, 422, 429].includes(e.response.status)
    ) {
      pending.value = null;
      if (e.response.status === 409) {
        try {
          receipt.value = await receiptsService.get(id);
          if (receipt.value.status === 'reversed') reason.value = '';
        } catch {
          error.value += ' No se pudo actualizar el estado. Recarga el comprobante.';
        }
      }
    } else error.value += ' Reintenta el mismo reverso; no generes otra operación.';
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
  if (busy.value || pending.value || reason.value) {
    e.preventDefault();
    e.returnValue = '';
  }
};
onBeforeRouteLeave(
  async () =>
    !busy.value &&
    (!(pending.value || reason.value) ||
      (await alerts.confirm(
        'Reverso sin confirmar',
        'Si sales, revisa el estado del comprobante antes de intentar otro reverso.',
        'Salir',
      ))),
);
onMounted(() => window.addEventListener('beforeunload', beforeUnload));
onUnmounted(() => window.removeEventListener('beforeunload', beforeUnload));
const printReceipt = () => window.print();
onMounted(async () => {
  loading.value = true;
  try {
    const result = await receiptsService.get(id);
    qr.value = await QRCode.toDataURL(result.verification_url, {
      width: 440,
      margin: 4,
      errorCorrectionLevel: 'M',
    });
    receipt.value = result;
  } catch (e) {
    error.value = administrationError(e);
  } finally {
    loading.value = false;
  }
});
</script>
<style scoped>
.receipt-page {
  padding: 24px;
  background: #f3f4f7;
}
.receipt-actions {
  max-width: 850px;
  margin: 0 auto 20px;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.receipt-paper {
  max-width: 850px;
  margin: auto;
  background: #fff;
  padding: 32px;
  border: 1px solid #e3e4e8;
  border-radius: 16px;
}
.logo {
  max-width: 260px;
  width: 100%;
  height: auto;
}
header p {
  color: #811335;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.1em;
  margin-top: 24px;
}
h1 {
  font-size: 22px;
  line-height: 1.4;
  overflow-wrap: anywhere;
  margin: 12px 0;
}
h2 {
  font-size: 17px;
  line-height: 1.5;
  font-weight: 700;
  margin: 18px 0;
}
.receipt-amount {
  font-size: 36px;
  font-weight: 700;
  color: #811335;
  margin: 18px 0;
}
.receipt-amount small {
  font-size: 15px;
}
dl {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
dt {
  color: #626b7b;
  font-size: 12px;
}
dd {
  margin: 4px 0;
  overflow-wrap: anywhere;
}
.receipt-scroll {
  overflow-x: auto;
}
table {
  width: 100%;
  border-collapse: collapse;
  min-width: 560px;
  text-align: left;
}
td,
th {
  padding: 10px;
  border-bottom: 1px solid #e3e4e8;
}
th {
  background: #faf5f7;
  color: #811335;
}
footer {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-top: 24px;
  border-top: 1px solid #e3e4e8;
  padding-top: 16px;
}
footer img {
  flex-shrink: 0;
}
a {
  color: #811335;
}
@media (max-width: 600px) {
  .receipt-page {
    padding: 12px;
  }
  .receipt-paper {
    padding: 18px;
  }
  dl {
    grid-template-columns: 1fr;
  }
  footer {
    flex-direction: column;
  }
}
@media print {
  .receipt-page {
    background: white;
    padding: 0;
  }
  .receipt-actions {
    display: none;
  }
  .receipt-paper {
    border: 0;
    border-radius: 0;
    padding: 0;
    max-width: none;
  }
  .receipt-scroll {
    overflow: visible;
  }
  table {
    min-width: 0;
    width: 100%;
    table-layout: fixed;
    font-size: 10pt;
  }
  td,
  th {
    overflow-wrap: anywhere;
  }
  thead {
    display: table-header-group;
  }
  tr,
  footer {
    break-inside: avoid;
  }
  footer a {
    display: none;
  }
  dl {
    grid-template-columns: 1fr 1fr;
  }
  footer {
    flex-direction: row;
  }
}
</style>
