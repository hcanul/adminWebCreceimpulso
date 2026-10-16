<template>
  <div>
    <div class="terms-facts">
      <div>
        <span>Capital</span><strong>{{ money(terms.totals.principal) }}</strong>
      </div>
      <div>
        <span>Cuota semanal · 16 semanas</span
        ><strong>{{ money(terms.totals.weekly_payment) }}</strong>
      </div>
      <div>
        <span>Total a devolver</span><strong>{{ money(terms.totals.total_repayment) }}</strong>
      </div>
      <div>
        <span>Interés pactado</span><strong>{{ money(terms.totals.interest) }}</strong>
      </div>
      <div>
        <span>Garantía a retener · 10 %</span><strong>{{ money(terms.totals.guarantee) }}</strong>
      </div>
      <div>
        <span>Neto por entregar</span><strong>{{ money(terms.totals.net_delivery) }}</strong>
      </div>
    </div>
    <p class="q-mt-md">
      Tasa conservada: <strong>{{ terms.rate.name }} · {{ money(terms.rate.factor) }}</strong> por
      cada $1,000 por semana.
    </p>
    <p>
      Entrega del crédito: <strong>{{ methodLabel(terms.disbursement_method) }}</strong> · Pagos
      semanales: <strong>{{ methodLabel(terms.repayment_method) }}</strong>
    </p>
    <p v-if="terms.first_due_on">
      Primer pago pactado: <strong>{{ terms.first_due_on }}</strong> · Los siguientes vencimientos
      serán cada 7 días.
    </p>
    <p class="text-caption text-grey-7 lt-md">
      Desliza la tabla para consultar todos los importes.
    </p>
    <div class="scroll">
      <table class="terms-table">
        <thead>
          <tr>
            <th>Integrante</th>
            <th>Solicitado</th>
            <th>{{ approved ? 'Aprobado' : 'Propuesto' }}</th>
            <th>Cuota semanal</th>
            <th>Garantía</th>
            <th>Neto por entregar</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="member in terms.members" :key="member.client_id">
            <td>
              {{ member.name }}<br /><small>{{ member.folio }} · {{ member.role_in_group }}</small>
            </td>
            <td>{{ money(member.amount_requested) }}</td>
            <td>{{ money(member.amount_approved ?? member.amount_proposed) }}</td>
            <td>{{ money(member.estimate.weekly_payment) }}</td>
            <td>{{ money(member.estimate.guarantee) }}</td>
            <td>{{ money(member.estimate.net_delivery) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
<script setup lang="ts">
import { money } from '../domain/credit-calculation';
import { methodLabel, type AuthorizationTerms } from '../services/authorization.service';
defineProps<{ terms: AuthorizationTerms; approved?: boolean }>();
</script>
<style scoped>
.terms-facts {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.terms-facts div {
  background: #faf5f7;
  border: 1px solid #eee3e7;
  border-radius: 12px;
  padding: 16px;
}
.terms-facts span {
  display: block;
  color: #646b79;
  font-size: 12px;
}
.terms-facts strong {
  display: block;
  color: #811335;
  font-size: 20px;
  margin-top: 6px;
}
.terms-table {
  width: 100%;
  min-width: 720px;
  border-collapse: collapse;
  text-align: left;
}
.terms-table th,
.terms-table td {
  padding: 14px;
  border-bottom: 1px solid #e5e7eb;
}
.terms-table th {
  color: #811335;
  background: #faf5f7;
}
@media (max-width: 750px) {
  .terms-facts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .terms-facts strong {
    font-size: 18px;
  }
}
</style>
