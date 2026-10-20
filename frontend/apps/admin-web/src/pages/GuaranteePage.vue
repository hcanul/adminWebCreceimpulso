<template>
  <q-page class="q-pa-md guarantee-page"
    ><div class="row items-center justify-between">
      <div>
        <h1>Garantía retenida</h1>
        <p>{{ data?.credit_folio }}</p>
      </div>
      <q-btn
        flat
        label="Volver al crédito"
        :to="`/solicitudes-credito/${id}`"
        :disable="busy"
      /><q-btn outline label="Actualizar" :disable="busy || loading" @click="load" />
    </div>
    <q-linear-progress v-if="loading" indeterminate /><q-banner
      v-if="error"
      role="alert"
      class="bg-red-1 text-negative"
      >{{ error }}</q-banner
    >
    <template v-if="data"
      ><q-banner class="bg-grey-2 q-my-md"
        >Aplicable desde el vencimiento de la semana 16: <strong>{{ data.available_on }}</strong
        >. Consulta al {{ data.as_of }}. La garantía se aplica al adeudo de cada integrante,
        empezando por su cuota pendiente más antigua. El remanente permanece retenido.</q-banner
      >
      <div class="scroll">
        <table aria-label="Saldos de garantía">
          <thead>
            <tr>
              <th>Integrante</th>
              <th>Retenida</th>
              <th>Aplicada vigente</th>
              <th>Disponible</th>
              <th>Adeudo</th>
              <th>A aplicar</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in data.members" :key="m.client_id">
              <td>{{ m.name }}<br />{{ m.folio }}</td>
              <td>{{ money(m.retained) }}</td>
              <td>{{ money(m.applied) }}</td>
              <td>{{ money(m.available) }}</td>
              <td>{{ money(m.balance) }}</td>
              <td>{{ money(m.applicable) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <q-card v-if="auth.can('guarantee.apply')" flat bordered class="q-my-lg"
        ><q-card-section
          ><h2>Aplicación por Coordinación Comercial</h2>
          <p>
            Se aplicará un total de <strong>{{ money(total) }}</strong
            >. No se registra ingreso de dinero ni se modifica el corte de Caja.
          </p>
          <q-banner v-if="!data.eligible_date" class="bg-orange-1"
            >Aún no llega el vencimiento de la semana 16.</q-banner
          ><q-input
            v-model="reason"
            outlined
            autogrow
            label="Motivo de la aplicación"
            maxlength="2000"
            :readonly="busy"
            class="q-my-md" /><q-btn
            color="primary"
            no-caps
            label="Aplicar garantía al adeudo"
            :loading="busy"
            :disable="!data.eligible_date || total === '0.00' || reason.trim().length < 10"
            @click="apply" /></q-card-section
      ></q-card>
      <h2>Historial de aplicaciones</h2>
      <p v-if="!data.history.length">No hay aplicaciones registradas.</p>
      <q-card v-for="event in data.history" :key="event.id" flat bordered class="q-mb-md"
        ><q-card-section
          ><strong
            >{{ event.applied_on }} · {{ money(event.amount) }} · {{ event.actor_name }}</strong
          >
          <p>{{ event.reason }}</p>
          <p v-for="(a, index) in event.allocations" :key="index">
            {{ data.members.find((m) => m.client_id === a.client_id)?.name }} · Semana
            {{ a.number }} · {{ money(String(a.amount)) }}
          </p>
          <q-banner v-if="event.reversal" class="bg-red-1"
            >Revertida el {{ event.reversal.reversed_on }} por {{ event.reversal.actor_name }}.
            {{ event.reversal.reason }}</q-banner
          ><template v-else-if="auth.can('guarantee.apply')"
            ><q-input
              v-model="reasons[event.id]"
              outlined
              label="Motivo del reverso"
              maxlength="2000"
              :readonly="busy" /><q-btn
              flat
              color="negative"
              no-caps
              label="Revertir aplicación"
              :disable="busy || (reasons[event.id]?.trim().length ?? 0) < 10"
              @click="reverse(event.id)" /></template></q-card-section
      ></q-card>
      <q-btn
        flat
        no-caps
        label="Ver estado de cuenta"
        :to="`/creditos/${id}/estado-de-cuenta`"
      /> </template
  ></q-page>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useGuaranteeStore } from '../stores/guarantee.store';
import { useAuthStore } from '../stores/auth.store';
import { guaranteeService } from '../services/guarantee.service';
import { administrationError } from '../services/administration.service';
import { alerts } from '../services/alerts.service';
import { money, cents, decimal } from '../domain/credit-calculation';
const route = useRoute(),
  store = useGuaranteeStore(),
  auth = useAuthStore(),
  { data, loading, error } = storeToRefs(store);
const id = computed(() => String((route.params as { id?: string }).id));
const reason = ref(''),
  reasons = ref<Record<string, string>>({}),
  busy = ref(false);
let requestId = crypto.randomUUID();
const total = computed(() =>
  decimal((data.value?.members ?? []).reduce((s, m) => s + cents(m.applicable), 0n)),
);
function load() {
  return store.load(id.value);
}
async function apply() {
  if (busy.value || !data.value) return;
  busy.value = true;
  try {
    if (
      !(await alerts.confirm(
        'Aplicar garantía',
        `Se aplicarán ${money(total.value)} al adeudo. El remanente seguirá retenido. No es una devolución ni un ingreso en Caja.`,
      ))
    )
      return;
    await guaranteeService.apply(id.value, {
      id: requestId,
      version: data.value.version,
      reason: reason.value.trim(),
    });
    requestId = crypto.randomUUID();
    reason.value = '';
    await load();
    await alerts.success('Garantía aplicada');
  } catch (e) {
    await alerts.error(administrationError(e));
  } finally {
    busy.value = false;
  }
}
async function reverse(eventId: string) {
  if (busy.value) return;
  busy.value = true;
  try {
    if (
      !(await alerts.confirm(
        'Revertir garantía',
        'Se restablecerán la garantía disponible y las cuotas originales. El crédito puede volver a tener adeudo.',
      ))
    )
      return;
    await guaranteeService.reverse(eventId, reasons.value[eventId]!.trim());
    await load();
    await alerts.success('Aplicación revertida con historial');
  } catch (e) {
    await alerts.error(administrationError(e));
  } finally {
    busy.value = false;
  }
}
watch(
  id,
  () => {
    reason.value = '';
    reasons.value = {};
    requestId = crypto.randomUUID();
    void load();
  },
  { immediate: true },
);
onBeforeUnmount(() => store.clear());
</script>
<style scoped>
.guarantee-page {
  max-width: 1200px;
  margin: auto;
}
h1 {
  font-size: 28px;
  line-height: 1.3;
  font-weight: 700;
}
h2 {
  font-size: 20px;
  line-height: 1.4;
  font-weight: 600;
}
table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}
th,
td {
  min-width: 120px;
  padding: 12px;
  border-bottom: 1px solid #eee;
  text-align: left;
}
th {
  color: #811335;
  background: #faf5f7;
}
.scroll {
  overflow: auto;
}
</style>
