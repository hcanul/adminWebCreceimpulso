<template>
  <section class="q-mt-lg">
    <h2>Estados de cuenta</h2>
    <q-banner v-if="data.demo" class="bg-orange-1"
      >Ejemplo con datos ficticios. No representa una deuda real.</q-banner
    >
    <p>{{ data.folio }} · {{ data.group }}<br />Corte al {{ date(data.as_of) }}</p>
    <q-tabs v-model="tab" active-color="primary" align="justify"
      ><q-tab name="personal" label="Personal" /><q-tab name="group" label="Grupo"
    /></q-tabs>
    <h3>{{ tab === 'personal' ? 'Mi estado de cuenta' : 'Estado de cuenta del grupo' }}</h3>
    <p v-if="tab === 'group'">
      Totales semanales de todas las integrantes. Este saldo grupal no es tu deuda personal.
    </p>
    <div class="legend">
      <span class="paid">Pagado</span><span class="partial">Incompleto</span
      ><span class="overdue">Vencido</span><span>Próximo / vence hoy</span>
    </div>
    <div
      class="statement-scroll"
      tabindex="0"
      role="region"
      :aria-label="tab === 'personal' ? 'Tabla de pagos personales' : 'Tabla de pagos del grupo'"
    >
      <table>
        <caption>
          {{
            tab === 'personal' ? 'Cuotas personales' : 'Cuotas grupales'
          }}
          · importes en MXN
        </caption>
        <thead>
          <tr>
            <th scope="col">Semana</th>
            <th scope="col">Vencimiento</th>
            <th scope="col">Cuota</th>
            <th scope="col">Aplicado</th>
            <th scope="col">Pendiente</th>
            <th scope="col">Estado</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.week" :class="row.status">
            <th scope="row">{{ row.week }}</th>
            <td>{{ date(row.due_on) }}</td>
            <td>{{ money(row.amount) }}</td>
            <td>{{ money(row.paid) }}</td>
            <td>{{ money(row.remaining) }}</td>
            <td>
              {{ labels[row.status]
              }}{{ row.status === 'overdue' && row.paid > 0 ? ' · abono parcial' : '' }}
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <th scope="row" colspan="2">Total</th>
            <td>{{ money(total('amount')) }}</td>
            <td>{{ money(total('paid')) }}</td>
            <td>{{ money(total('remaining')) }}</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="text-caption">
      Desliza la tabla para ver todas las columnas. Las cuotas incluyen capital e interés pactado;
      no incluyen mora. Aplicado incluye abonos y aplicaciones de garantía registradas, descontando
      reversos. Un abono incompleto vencido aparece en rojo.
    </p>
  </section>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import type { ClientStatement } from '../services/statement.service';
const props = defineProps<{ data: ClientStatement }>(),
  tab = ref('personal');
const rows = computed(() =>
  tab.value === 'personal' ? props.data.personal : props.data.group_schedule,
);
const labels = {
  paid: 'Pagado',
  partial: 'Incompleto',
  overdue: 'Vencido',
  due_today: 'Vence hoy',
  upcoming: 'Próximo',
};
const money = (cents: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(cents / 100);
const date = (value: string) =>
  new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(new Date(value + 'T00:00:00Z'));
const total = (field: 'amount' | 'paid' | 'remaining') =>
  rows.value.reduce((sum, row) => sum + row[field], 0);
</script>
<style scoped>
h2 { font-size: 24px; line-height: 1.3; font-weight: 700; margin: 24px 0 12px; }
h3 { font-size: 20px; line-height: 1.3; font-weight: 600; margin: 20px 0 12px; }
.statement-scroll {
  overflow-x: auto;
  border: 1px solid #d7dae2;
  border-radius: 12px;
  max-width: 100%;
}
table {
  border-collapse: collapse;
  min-width: 650px;
  width: 100%;
  font-size: 14px;
}
caption {
  padding: 12px;
  text-align: left;
}
th,
td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid #d7dae2;
  white-space: nowrap;
}
thead,
tfoot {
  background: #f1f2f6;
  font-weight: 700;
}
.paid {
  background: #e0f3e7;
  color: #185c35;
}
.partial {
  background: #fff3bf;
  color: #6b5000;
}
.overdue {
  background: #fbe1e3;
  color: #8b1a29;
}
.legend {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 12px 0;
}
.legend span {
  padding: 6px 10px;
  border-radius: 8px;
  font-size: 13px;
}
</style>
