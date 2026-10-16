<template>
  <div v-for="method in methods" :key="method.value" class="q-mt-lg">
    <h3 class="text-subtitle1 text-weight-bold">{{ method.label }}</h3>
    <div class="scroll">
      <table class="closing-table">
        <thead>
          <tr>
            <th>Crédito / pago</th>
            <th>Recepción</th>
            <th>Importe</th>
            <template v-if="method.value === 'bank_deposit'"
              ><th>Banco / folio</th>
              <th>Fecha del depósito</th></template
            >
          </tr>
        </thead>
        <tbody>
          <tr v-for="payment in rows.filter((p) => p.method === method.value)" :key="payment.id">
            <td>{{ payment.credit_folio }} · #{{ payment.number }}</td>
            <td>{{ payment.received_on }}</td>
            <td>{{ money(payment.amount) }}</td>
            <template v-if="method.value === 'bank_deposit'"
              ><td>{{ payment.bank_name }} · {{ payment.bank_reference }}</td>
              <td>{{ payment.deposited_on }}</td></template
            >
          </tr>
          <tr v-if="!rows.some((p) => p.method === method.value)">
            <td :colspan="method.value === 'bank_deposit' ? 5 : 3">
              Sin registros de este medio en esta página.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
<script setup lang="ts">
import type { ClosingPayment } from '../services/cash.service';
import { money } from '../domain/credit-calculation';
defineProps<{ rows: ClosingPayment[] }>();
const methods = [
  { value: 'cash_branch', label: 'Efectivo en sucursal' },
  { value: 'bank_deposit', label: 'Depósitos bancarios' },
];
</script>
<style scoped>
.closing-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 540px;
  text-align: left;
}
.closing-table th,
.closing-table td {
  padding: 14px;
  border-bottom: 1px solid #e3e4e8;
}
.closing-table th {
  color: #811335;
  background: #faf5f7;
}
</style>
