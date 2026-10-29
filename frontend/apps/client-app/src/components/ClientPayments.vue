<template>
  <q-card flat bordered class="q-pa-lg q-mt-lg">
    <h2 class="text-h5 q-mt-none">Mis comprobantes</h2>
    <p>
      Pagos recibidos del crédito activo en los que aportaste o recibiste una aplicación. El total
      del ticket puede incluir pagos de otras integrantes.
    </p>
    <q-banner v-if="error" class="bg-red-1"
      >{{ error }} <q-btn flat label="Reintentar" @click="load"
    /></q-banner>
    <q-list separator>
      <q-item v-for="r in receipts" :key="r.id" class="q-px-none">
        <q-item-section
          ><q-item-label class="text-weight-bold">{{ r.folio }}</q-item-label
          ><q-item-label
            >{{ r.received_on }} ·
            {{
              r.method === 'bank_deposit' ? 'Depósito bancario' : 'Efectivo en sucursal'
            }}</q-item-label
          >
          <q-item-label>Total del ticket: {{ money(r.amount) }}</q-item-label>
          <q-item-label
            >Aportaste: {{ money(r.personal_contributed) }} · Aplicado a tu crédito:
            {{ money(r.personal_applied) }}</q-item-label
          >
          <q-badge
            :color="r.status === 'reversed' ? 'negative' : 'positive'"
            class="self-start q-my-sm"
            >{{
              r.status === 'reversed' ? 'Reversado · sin efecto en el saldo' : 'Registrado'
            }}</q-badge
          >
          <q-btn
            outline
            class="self-start"
            label="Ver ticket QR"
            icon="qr_code_2"
            @click="open(r)"
          />
        </q-item-section>
      </q-item>
    </q-list>
    <p v-if="!loading && !error && !receipts.length">Todavía no hay comprobantes para mostrar.</p>
    <q-btn
      v-if="hasMore && !error"
      flat
      color="primary"
      label="Cargar comprobantes"
      :loading="loading"
      @click="load"
    />
    <q-dialog v-model="dialog"
      ><q-card class="q-pa-lg receipt-card"
        ><h3 class="text-h6">Comprobante de pago</h3>
        <template v-if="selected"
          ><p>{{ selected.folio }}</p>
          <p>{{ selected.received_on }} · {{ money(selected.amount) }}</p>
          <q-banner v-if="selected.status === 'reversed'" class="bg-red-1"
            >Este pago fue reversado. No acredita un pago vigente.</q-banner
          ><img
            v-if="qr"
            :src="qr"
            alt="QR para verificar el estado actual del comprobante"
            width="240"
            height="240"
          />
          <p>
            Escanea para consultar la validez actual en el servidor. Una captura de pantalla no
            confirma que el pago siga vigente.
          </p>
          <p v-if="qrError" role="alert">{{ qrError }}</p></template
        ><q-btn flat label="Cerrar" v-close-popup /></q-card
    ></q-dialog>
  </q-card>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import QRCode from 'qrcode';
import { api, errorMessage } from '../services/http';
interface Receipt {
  id: string;
  folio: string;
  amount: string;
  received_on: string;
  method: string;
  status: 'registered' | 'reversed';
  personal_applied: string;
  personal_contributed: string;
  verification_url: string;
}
const receipts = ref<Receipt[]>([]),
  hasMore = ref(true),
  loading = ref(false),
  error = ref(''),
  page = ref(1),
  dialog = ref(false),
  selected = ref<Receipt | null>(null),
  qr = ref(''),
  qrError = ref('');
const money = (v: string) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v));
async function load() {
  if (loading.value) return;
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get<{ receipts: Receipt[]; has_more: boolean }>('/client/payments', {
      params: { page: page.value },
    });
    receipts.value.push(...data.receipts);
    hasMore.value = data.has_more;
    page.value++;
  } catch (e) {
    error.value = errorMessage(e);
  } finally {
    loading.value = false;
  }
}
async function open(r: Receipt) {
  selected.value = r;
  qr.value = '';
  qrError.value = '';
  dialog.value = true;
  try {
    qr.value = await QRCode.toDataURL(r.verification_url, { width: 240, margin: 2 });
  } catch {
    qrError.value = 'No fue posible generar el código QR. Intenta nuevamente.';
  }
}
onMounted(load);
</script>
<style scoped>
.receipt-card {
  width: 420px;
  max-width: 95vw;
  overflow-wrap: anywhere;
}
.receipt-card img {
  display: block;
  margin: 12px auto;
  max-width: 100%;
  height: auto;
}
</style>
