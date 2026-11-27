<template>
  <q-page class="intake-page">
    <div class="intake-heading">
      <div>
        <p class="eyebrow">CRÉDITO GRUPAL</p>
        <h1>{{ loan?.folio ?? 'Solicitud de crédito' }}</h1>
        <p>{{ loan?.team?.name }} · {{ loan?.branch?.name }}</p>
      </div>
      <q-btn flat no-caps icon="arrow_back" label="Solicitudes" to="/solicitudes-credito" />
      <q-btn
        v-if="delivered"
        flat
        no-caps
        icon="account_balance_wallet"
        label="Estado de cuenta"
        :to="`/creditos/${loan?.id}/estado-de-cuenta`"
      />
      <q-btn
        v-if="delivered && (auth.can('credit.collect') || auth.can('credit.reverse'))"
        color="primary"
        no-caps
        label="Cobros y saldos"
        :to="`/cobros/${loan?.id}`"
      />
      <q-btn
        v-if="delivered"
        flat
        no-caps
        icon="savings"
        label="Garantía"
        :to="`/creditos/${loan?.id}/garantia`"
      />
    </div>
    <q-linear-progress v-if="loading" indeterminate />
    <q-banner v-if="error" class="bg-red-1 text-negative">{{ error }}</q-banner>
    <template v-if="loan">
      <ol class="credit-stages" aria-label="Etapas del crédito">
        <li>1. Expedientes</li>
        <li>2. Montos solicitados</li>
        <li :class="{ active: loan.status === 'pendiente' }">3. Autorizar</li>
        <li :class="{ active: loan.status !== 'pendiente' }">4. Firmas y desembolso</li>
      </ol>
      <q-banner class="bg-primary text-white q-mb-lg">{{ nextAction }}</q-banner>
      <div class="credit-facts">
        <q-card flat bordered class="intake-card"
          ><q-card-section
            ><p class="eyebrow">SOLICITADO</p>
            <h2>{{ money(loan.total_requested) }}</h2>
            <p>Otorgado: {{ money(loan.total_approved) }}</p></q-card-section
          ></q-card
        >
        <q-card flat bordered class="intake-card"
          ><q-card-section
            ><p class="eyebrow">CONDICIONES DEFINITIVAS</p>
            <h2>{{ loan.approved_snapshot?.rate.name ?? 'Por definir al autorizar' }}</h2>
            <p v-if="loan.approved_snapshot">
              {{ money(loan.approved_snapshot.rate.factor) }} por cada $1,000 por semana · 16
              semanas
            </p>
            <p v-else>La solicitud no fija intereses ni calendario.</p></q-card-section
          ></q-card
        >
        <q-card flat bordered class="intake-card"
          ><q-card-section
            ><p>
              Desembolso: <strong>{{ methodLabel(loan.disbursement_method) }}</strong>
            </p>
            <p>
              Pagos semanales: <strong>{{ methodLabel(loan.repayment_method) }}</strong>
            </p></q-card-section
          ></q-card
        >
      </div>
      <q-expansion-item
        v-if="snapshot"
        label="Consultar montos solicitados por integrante"
        icon="groups"
        class="q-mt-lg"
      >
        <q-card flat bordered
          ><q-card-section
            ><div class="scroll">
              <table class="credit-table">
                <thead>
                  <tr>
                    <th>Clienta / cargo</th>
                    <th>Monto solicitado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="member in snapshot.members" :key="member.client_id">
                    <td>
                      <strong>{{ member.name }}</strong
                      ><br />{{ member.folio }} · {{ member.role_in_group }}
                    </td>
                    <td>{{ money(member.amount_requested) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="q-mt-md">
              Los montos solicitados se conservan separados de los otorgados.
            </p></q-card-section
          ></q-card
        >
      </q-expansion-item>
      <q-banner v-else class="bg-orange-1 q-mt-md"
        >Registro histórico sin detalle de solicitud. Requiere revisión antes de operar.</q-banner
      >
      <CreditAuthorizationPanel
        v-if="snapshot && (auth.can('credit.authorize') || auth.can('credit.propose'))"
        :key="loan.authorization_version ?? 0"
        :loan="loan"
        @updated="loadLoan"
      />
      <CreditDisbursementPanel v-if="loan.approved_snapshot" :loan="loan" @updated="loadLoan" />
    </template>
  </q-page>
</template>
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useStaffAuth as useAuthStore } from '../stores/auth';
import { creditService, type CreditApplication } from '../services/credit.service';
import { methodLabel } from '../services/authorization.service';
import { administrationError } from '../services/administration.service';
import { money } from '../domain/credit-calculation';
import CreditAuthorizationPanel from '../components/CreditAuthorizationPanel.vue';
import CreditDisbursementPanel from '../components/CreditDisbursementPanel.vue';
const auth = useAuthStore(),
  route = useRoute(),
  loan = ref<CreditApplication | null>(null),
  loading = ref(false),
  error = ref('');
const snapshot = computed(() => loan.value?.request_snapshot);
const delivered = computed(() => ['entregado', 'pagado'].includes(loan.value?.status ?? ''));
const nextAction = computed(() =>
  delivered.value
    ? 'Desembolso registrado. Consulta los pagos y el estado de cuenta.'
    : loan.value?.status === 'aprobado'
      ? 'Siguiente responsable: sucursal. Genera los documentos en Admin Web, recaba firmas y registra la entrega real.'
      : loan.value?.authorization_status === 'returned'
        ? 'Coordinación solicitó correcciones. Consulta las observaciones en el historial de autorización.'
        : 'Siguiente responsable: Coordinación Comercial. Captura montos otorgados, tasa, primer vencimiento y formas de pago y desembolso.',
);
async function loadLoan() {
  loading.value = true;
  error.value = '';
  try {
    loan.value = await creditService.get(String((route.params as { id: string }).id));
  } catch (e) {
    error.value = administrationError(e);
  } finally {
    loading.value = false;
  }
}
onMounted(loadLoan);
</script>
<style scoped>
.credit-stages {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  list-style: none;
  padding: 0;
  margin-bottom: 24px;
}
.credit-stages li {
  padding: 10px 16px;
  border-radius: 24px;
  background: #eee;
  color: #555;
}
.credit-stages li.active {
  background: #811335;
  color: white;
  font-weight: 600;
}
.credit-facts {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
}
.credit-table {
  width: 100%;
  text-align: left;
  border-collapse: collapse;
}
.credit-table td,
.credit-table th {
  padding: 12px;
  border-bottom: 1px solid #eee;
}
@media (max-width: 900px) {
  .credit-facts {
    grid-template-columns: 1fr;
  }
}
</style>
