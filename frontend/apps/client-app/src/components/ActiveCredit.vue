<template>
  <section class="q-mt-lg" aria-label="Resumen del crédito activo">
    <q-card flat bordered class="q-pa-lg">
      <h2 class="text-h5 q-mt-none">Mi crédito activo</h2>
      <p>{{ data.folio }} · {{ data.group }}</p>
      <div class="metrics">
        <div>
          <span>Saldo de cuotas</span><strong>{{ money(balance) }}</strong>
        </div>
        <div>
          <span>Cuotas vencidas</span><strong>{{ money(overdue) }}</strong>
        </div>
        <div>
          <span>Mora pendiente</span><strong>{{ money(data.late_fees?.balance || 0) }}</strong>
        </div>
        <div>
          <span>Exigible al {{ date(data.as_of) }}</span
          ><strong>{{ money(due + (data.late_fees?.balance || 0)) }}</strong>
        </div>
      </div>
      <q-linear-progress
        class="q-my-md"
        :value="total ? (total - balance) / total : 0"
        color="primary"
        size="8px"
        rounded
      />
      <p>{{ money(total - balance) }} aplicados a cuotas de {{ money(total) }} pactados.</p>
      <p v-if="next">
        Siguiente vencimiento pendiente: <b>{{ date(next.due_on) }}</b> ·
        {{ money(next.remaining) }}
      </p>
      <q-expansion-item
        v-if="data.terms"
        label="Condiciones y entrega"
        icon="account_balance_wallet"
      >
        <dl>
          <dt>Solicitado</dt>
          <dd>{{ money(data.terms.requested) }}</dd>
          <dt>Aprobado</dt>
          <dd>{{ money(data.terms.approved) }}</dd>
          <dt>Interés pactado</dt>
          <dd>{{ money(data.terms.interest) }}</dd>
          <dt>Garantía retenida al entregar</dt>
          <dd>{{ money(data.terms.guarantee_retained) }}</dd>
          <dt>Entregado</dt>
          <dd>{{ money(data.terms.net_delivered) }}</dd>
          <dt>Tasa</dt>
          <dd>{{ data.terms.rate }}</dd>
          <dt>Forma de pago semanal acordada</dt>
          <dd>
            {{
              data.terms.repayment_method === 'bank_deposit' ? 'Depósito bancario' : 'En sucursal'
            }}
          </dd>
        </dl>
        <p>
          La garantía retenida es el importe original. Sus aplicaciones autorizadas ya se reflejan
          en las cuotas; no debes restarla nuevamente. Adelantar pagos no reduce el interés pactado.
        </p>
      </q-expansion-item>
      <q-expansion-item label="Detalle de mora" icon="event_busy">
        <p v-if="!data.late_fees?.enabled">
          Este crédito no tiene cargos de mora calculados por esta política.
        </p>
        <template v-else
          ><p>
            10% del saldo de la cuota al iniciar cada semana de atraso. Primero se cubren cuotas
            pendientes, después mora y finalmente adelantos.
          </p>
          <ul>
            <li v-for="(fee, index) in data.late_fees.charges" :key="index">
              {{ date(fee.charged_on) }}: cargo {{ money(fee.amount) }} sobre
              {{ money(fee.base) }} · pendiente {{ money(fee.balance) }}
            </li>
          </ul>
          <p v-if="!data.late_fees.charges.length">Sin cargos de mora.</p>
        </template>
      </q-expansion-item>
    </q-card>
  </section>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import type { ClientStatement } from '../services/statement.service';
const props = defineProps<{ data: ClientStatement }>();
const total = computed(() => props.data.personal.reduce((s, r) => s + r.amount, 0));
const balance = computed(() => props.data.personal.reduce((s, r) => s + r.remaining, 0));
const overdue = computed(() =>
  props.data.personal
    .filter((r) => r.due_on < props.data.as_of)
    .reduce((s, r) => s + r.remaining, 0),
);
const due = computed(() =>
  props.data.personal
    .filter((r) => r.due_on <= props.data.as_of)
    .reduce((s, r) => s + r.remaining, 0),
);
const next = computed(() =>
  props.data.personal.find((r) => r.remaining > 0 && r.due_on >= props.data.as_of),
);
const money = (n: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n / 100);
const date = (d: string) => new Date(d + 'T12:00:00').toLocaleDateString('es-MX');
</script>
<style scoped>
.metrics {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
}
.metrics strong {
  display: block;
  font-size: 1.4rem;
  color: #7e1838;
}
dl {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
dd {
  margin: 0;
  font-weight: 600;
}
</style>
