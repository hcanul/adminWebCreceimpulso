<template>
  <q-page class="intake-page"
    ><div class="intake-heading">
      <div>
        <p class="eyebrow">SOLICITUD REGISTRADA</p>
        <h1>{{ loan?.folio ?? 'Solicitud de crédito' }}</h1>
        <p>{{ loan?.team?.name }} · {{ loan?.branch?.name }}</p>
      </div>
      <q-btn
        v-if="['entregado', 'pagado'].includes(loan?.status ?? '')"
        flat
        no-caps
        icon="savings"
        label="Garantía"
        :to="`/creditos/${loan?.id}/garantia`"
      />
      <q-btn
        v-if="loan"
        flat
        no-caps
        icon="folder_open"
        label="Documentos"
        :to="`/documentos/credit/${loan.id}`"
      />
      <q-btn
        v-if="['entregado', 'pagado'].includes(loan?.status ?? '')"
        outline
        color="primary"
        no-caps
        icon="account_balance_wallet"
        label="Estado de cuenta"
        :to="`/creditos/${loan?.id}/estado-de-cuenta`"
      />
      <q-btn
        v-if="
          (auth.can('credit.collect') || auth.can('credit.reverse')) &&
          ['entregado', 'pagado'].includes(loan?.status ?? '')
        "
        color="primary"
        no-caps
        icon="payments"
        label="Cobros y saldos"
        :to="`/cobros/${loan?.id}`"
      /><q-btn flat no-caps icon="arrow_back" label="Solicitudes" to="/solicitudes-credito" />
    </div>
    <q-linear-progress v-if="loading" indeterminate /><q-banner
      v-if="error"
      class="bg-red-1 text-negative"
      >{{ error }}</q-banner
    ><template v-if="loan"
      ><q-banner rounded class="bg-grey-2 q-mb-lg"
        >Estado:
        <strong>{{
          loan.status === 'pendiente'
            ? loan.review_status === 'ready_for_authorization'
              ? authorizationLabels[loan.authorization_status ?? 'awaiting_proposal']
              : reviewLabels[loan.review_status ?? 'pending']
            : loan.status
        }}</strong
        >. Los montos solicitados se conservan; la autorización y la entrega del dinero se registran
        por separado.</q-banner
      >
      <div class="credit-facts">
        <q-card flat bordered class="intake-card"
          ><q-card-section
            ><p class="eyebrow">SOLICITADO</p>
            <h2>{{ money(loan.total_requested) }}</h2>
            <p>Aprobado: {{ money(loan.total_approved) }}</p></q-card-section
          ></q-card
        ><q-card flat bordered class="intake-card"
          ><q-card-section
            ><p class="eyebrow">TASA CONSERVADA</p>
            <h2>{{ snapshot?.rate.name ?? 'Histórico sin snapshot' }}</h2>
            <p v-if="snapshot">
              {{ money(snapshot.rate.factor) }} por cada $1,000 por semana ·
              {{ snapshot.weeks }} semanas
            </p>
            <p v-if="snapshot">
              Límite por integrante: {{ money(snapshot.member_limit) }}
            </p></q-card-section
          ></q-card
        ><q-card flat bordered class="intake-card"
          ><q-card-section
            ><p class="eyebrow">FORMAS DE OPERACIÓN</p>
            <p>
              Entrega del crédito: <strong>{{ method(loan.disbursement_method) }}</strong>
            </p>
            <p>
              Pagos semanales: <strong>{{ method(loan.repayment_method) }}</strong>
            </p></q-card-section
          ></q-card
        >
      </div>
      <q-card v-if="snapshot" flat bordered class="intake-card q-mt-lg"
        ><q-card-section
          ><h2>Integrantes y condiciones solicitadas</h2>
          <p class="text-caption text-grey-7 lt-md">
            Desliza la tabla para consultar las cuotas y los totales.
          </p>
          <div class="scroll">
            <table class="credit-table">
              <thead>
                <tr>
                  <th>Cliente / cargo</th>
                  <th>Solicitado</th>
                  <th>Cuota semanal estimada</th>
                  <th>Total estimado</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="member in snapshot.members" :key="member.client_id">
                  <td>
                    <strong>{{ member.name }}</strong
                    ><br /><small>{{ member.folio }} · {{ member.role_in_group }}</small>
                  </td>
                  <td>{{ money(member.amount_requested) }}</td>
                  <td>{{ money(member.estimate.weekly_payment) }}</td>
                  <td>{{ money(member.estimate.total_repayment) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <th>Total grupal</th>
                  <th>{{ money(snapshot.totals.principal) }}</th>
                  <th>{{ money(snapshot.totals.weekly_payment) }}</th>
                  <th>{{ money(snapshot.totals.total_repayment) }}</th>
                </tr>
              </tfoot>
            </table>
          </div>
          <p class="q-mt-lg">
            Interés estimado: <strong>{{ money(snapshot.totals.interest) }}</strong> · Garantía
            estimada (10%): <strong>{{ money(snapshot.totals.guarantee) }}</strong> · Entrega neta
            estimada: <strong>{{ money(snapshot.totals.net_delivery) }}</strong>
          </p>
          <p class="text-caption text-grey-7">
            Calculado sobre lo solicitado. La retención real se calculará sobre lo aprobado.
            Consulta las condiciones aprobadas y el calendario en la sección de desembolso.
          </p></q-card-section
        ></q-card
      ><q-banner v-else class="bg-orange-1 q-mt-md"
        >Registro anterior sin detalle de cálculo conservado. No se reconstruyen condiciones
        históricas usando tasas actuales.</q-banner
      ><CreditReviewPanel
        v-if="
          snapshot &&
          (auth.can('credit.review') || auth.can('credit.create') || auth.can('credit.authorize'))
        "
        :loan="loan"
        @updated="loan.review_status = $event" /><CreditAuthorizationPanel
        v-if="snapshot && (auth.can('credit.propose') || auth.can('credit.authorize'))"
        :loan="loan"
        @updated="loadLoan" /><CreditDisbursementPanel
        v-if="loan.approved_snapshot"
        :loan="loan"
        @updated="loadLoan" /></template
  ></q-page>
</template>
<script setup lang="ts">
import CreditDisbursementPanel from '../components/CreditDisbursementPanel.vue';
import { authorizationLabels } from '../services/authorization.service';
import CreditAuthorizationPanel from '../components/CreditAuthorizationPanel.vue';
import CreditReviewPanel from '../components/CreditReviewPanel.vue';
import { useAuthStore } from '../stores/auth.store';
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { creditService, reviewLabels, type CreditApplication } from '../services/credit.service';
import { administrationError } from '../services/administration.service';
import { money } from '../domain/credit-calculation';
const auth = useAuthStore();
const route = useRoute(),
  loan = ref<CreditApplication | null>(null),
  loading = ref(false),
  error = ref('');
const snapshot = computed(() => loan.value?.request_snapshot);
const method = (value: string | null | undefined) =>
  value === 'cash_branch'
    ? 'En sucursal'
    : value === 'bank_deposit'
      ? 'Depósito bancario'
      : 'Pendiente';
async function loadLoan() {
  loading.value = true;
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
.credit-facts {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
}
.credit-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  min-width: 620px;
}
.credit-table td,
.credit-table th {
  padding: 16px;
  border-bottom: 1px solid #e5e7eb;
}
.credit-table th {
  color: #811335;
  background: #faf5f7;
}
.credit-table small {
  color: #646b79;
}
@media (max-width: 900px) {
  .credit-facts {
    grid-template-columns: 1fr;
  }
}
</style>
