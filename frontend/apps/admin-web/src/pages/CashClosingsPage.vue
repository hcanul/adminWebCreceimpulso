<template>
  <q-page class="intake-page">
    <div class="intake-heading">
      <div>
        <p class="eyebrow">TESORERÍA · CONTROL DE COBROS</p>
        <h1>{{ detail?.record.folio ?? 'Cortes de caja' }}</h1>
        <p>
          {{
            id
              ? 'Corte cerrado: los cobros y sus importes se conservan.'
              : 'Efectivo y depósitos separados, con cierre por cajero, día y sucursal.'
          }}
        </p>
      </div>
      <div v-if="id" class="q-gutter-sm">
        <q-btn flat no-caps icon="arrow_back" label="Cortes de caja" to="/cortes" />
        <q-btn
          v-if="detail"
          outline
          color="primary"
          no-caps
          icon="print"
          label="Reporte para imprimir"
          :to="`/cortes/${id}/imprimir`"
        />
      </div>
    </div>
    <q-linear-progress v-if="loading" indeterminate /><q-banner
      v-if="error"
      rounded
      class="bg-red-1 text-negative q-mb-md"
      >{{ error }}</q-banner
    >
    <template v-if="!id">
      <q-card flat bordered class="intake-card q-mb-lg"
        ><q-card-section
          ><fieldset :disabled="loading || busy || !!pending" class="fields">
            <div class="filter-grid">
              <FilterSelect
                v-model="branch"
                outlined
                emit-value
                map-options
                label="Sucursal"
                :options="branches.map((b) => ({ value: b.id, label: b.name }))"
                @update:model-value="clearPreview"
              /><q-input
                v-model="date"
                type="date"
                outlined
                stack-label
                label="Día de recepción de los cobros"
                :max="today"
                @update:model-value="clearPreview"
              />
            </div>
            <div class="q-gutter-sm q-mt-md">
              <q-btn
                outline
                color="primary"
                no-caps
                label="Consultar cortes"
                @click="list(1)"
              /><q-btn
                v-if="auth.can('cash.close')"
                color="primary"
                no-caps
                icon="point_of_sale"
                label="Preparar mi corte"
                :disable="!branch || !date"
                @click="prepare(1)"
              />
            </div></fieldset></q-card-section
      ></q-card>
      <q-card v-if="preview" flat bordered class="intake-card q-mb-lg"
        ><q-card-section
          ><h2>Mis cobros pendientes de corte</h2>
          <p>
            {{ preview.payment_count }} cobros. Incluye todos tus cobros pendientes del día y
            sucursal seleccionados, también los de otras páginas.
          </p>
          <div class="totals-grid">
            <div>
              <p class="eyebrow">EFECTIVO REGISTRADO</p>
              <h2>{{ money(preview.cash_total) }}</h2>
            </div>
            <div>
              <p class="eyebrow">DEPÓSITOS REGISTRADOS</p>
              <h2>{{ money(preview.bank_total) }}</h2>
            </div>
          </div>
          <q-banner rounded class="bg-grey-2 q-my-md"
            >Cuenta únicamente el efectivo de los cobros incluidos en este corte. No sumes el fondo
            de caja, garantías ni desembolsos. Los depósitos no forman parte del efectivo
            contado.</q-banner
          >
          <ClosingPaymentsTable :rows="preview.payments.data" /><q-pagination
            class="q-my-md"
            :model-value="preview.payments.current_page"
            :max="preview.payments.last_page"
            :max-pages="5"
            :disable="loading || busy || !!pending"
            @update:model-value="prepare"
          />
          <q-form v-if="preview.payment_count" @submit="submit"
            ><fieldset :disabled="loading || busy || !!pending" class="fields">
              <q-input
                v-model="counted"
                outlined
                inputmode="decimal"
                label="Efectivo contado para este corte (MXN)"
                maxlength="15"
              />
              <p
                class="q-my-md"
                :class="difference !== '0.00' ? 'text-negative text-weight-bold' : 'text-positive'"
              >
                Diferencia:
                {{ difference === null ? 'Captura el efectivo contado' : money(difference) }} ·
                Positiva: sobrante; negativa: faltante.
              </p>
              <q-input
                v-model="notes"
                outlined
                type="textarea"
                label="Observaciones / explicación de diferencias"
                maxlength="2000"
              /><q-checkbox
                v-model="confirmed"
                label="Revisé los cobros y confirmé el efectivo contado. Entiendo que el corte no podrá editarse."
                class="q-mt-md"
              />
            </fieldset>
            <q-btn
              v-if="!pending"
              type="submit"
              color="primary"
              no-caps
              label="Cerrar mi corte"
              icon="lock"
              :loading="busy"
              :disable="loading || !confirmed"
              class="q-mt-md"
          /></q-form>
          <q-banner v-if="!preview.payment_count" rounded class="bg-grey-2"
            >No tienes cobros pendientes en esta fecha y sucursal.</q-banner
          >
          <q-banner v-if="pending" rounded class="bg-orange-1 q-mt-md"
            >La respuesta del cierre no se pudo confirmar. Reintenta la misma operación para evitar
            duplicados.<template #action
              ><q-btn
                flat
                no-caps
                label="Reintentar el mismo corte"
                :loading="busy"
                @click="retry" /></template
          ></q-banner> </q-card-section
      ></q-card>
      <q-card flat bordered class="intake-card"
        ><q-card-section
          ><h2>Cortes registrados</h2>
          <p v-if="auth.can('cash.audit')">
            Consulta los cortes de los cajeros de las sucursales a tu alcance.
          </p>
          <p v-else>Consulta tus propios cortes.</p></q-card-section
        ><q-list separator
          ><q-item
            v-for="record in records?.data ?? []"
            :key="record.id"
            clickable
            :to="`/cortes/${record.id}`"
            ><q-item-section
              ><q-item-label class="text-weight-bold"
                >{{ record.folio }} · {{ record.branch_name }}</q-item-label
              ><q-item-label caption
                >{{ record.actor_name }} · {{ record.business_date }} ·
                {{ record.payment_count }} cobros</q-item-label
              ><q-item-label
                >Efectivo {{ money(record.cash_total) }} · Depósitos
                {{ money(record.bank_total) }}</q-item-label
              ><q-item-label v-if="record.cash_difference !== '0.00'" class="text-negative"
                >Diferencia: {{ money(record.cash_difference) }}</q-item-label
              ></q-item-section
            ><q-item-section side><q-icon name="lock" /></q-item-section></q-item></q-list
        ><q-card-section v-if="records && !records.total"
          >No hay cortes con estos filtros.</q-card-section
        ><q-card-actions align="center"
          ><q-pagination
            v-if="records"
            :model-value="records.current_page"
            :max="records.last_page"
            :max-pages="5"
            :disable="loading || busy || !!pending"
            @update:model-value="list" /></q-card-actions
      ></q-card>
    </template>
    <q-card v-else-if="detail" flat bordered class="intake-card"
      ><q-card-section
        ><h2>{{ detail.record.branch_name }}</h2>
        <p>{{ detail.record.actor_name }} · Día de cobros: {{ detail.record.business_date }}</p>
        <p>
          Cerrado (hora de Cancún): {{ detail.record.closed_at_local }} ·
          {{ detail.record.payment_count }} cobros incluidos
        </p>
        <div class="totals-grid">
          <div>
            <p class="eyebrow">EFECTIVO REGISTRADO</p>
            <h2>{{ money(detail.record.cash_total) }}</h2>
          </div>
          <div>
            <p class="eyebrow">DEPÓSITOS</p>
            <h2>{{ money(detail.record.bank_total) }}</h2>
          </div>
          <div>
            <p class="eyebrow">EFECTIVO CONTADO</p>
            <h2>{{ money(detail.record.cash_counted) }}</h2>
          </div>
          <div>
            <p class="eyebrow">DIFERENCIA</p>
            <h2
              :class="detail.record.cash_difference !== '0.00' ? 'text-negative' : 'text-positive'"
            >
              {{ money(detail.record.cash_difference) }}
            </h2>
          </div>
        </div>
        <q-banner rounded class="bg-grey-2 q-my-md">{{
          detail.record.notes || 'Sin observaciones.'
        }}</q-banner>
        <section v-if="detail.adjustments?.total" class="q-my-lg">
          <h2>Reversos posteriores · ajustes separados</h2>
          <p>
            Los totales originales del corte no cambian. Estos reversos corrigen los pagos
            indicados; no registran una devolución física.
          </p>
          <q-list separator
            ><q-item v-for="adjustment in detail.adjustments.data" :key="adjustment.id"
              ><q-item-section
                ><q-item-label class="text-negative"
                  >−{{ money(adjustment.amount) }} ·
                  {{ adjustment.method === 'bank_deposit' ? 'Depósito' : 'Efectivo' }} ·
                  {{ adjustment.reversed_on }}</q-item-label
                ><q-item-label>{{ adjustment.actor_name }}: {{ adjustment.reason }}</q-item-label
                ><q-item-label caption
                  >Crédito: {{ adjustment.credit_folio }} · Pago #{{
                    adjustment.number
                  }}</q-item-label
                ></q-item-section
              ></q-item
            ></q-list
          ><q-pagination
            :model-value="detail.adjustments.current_page"
            :max="detail.adjustments.last_page"
            :max-pages="5"
            :disable="loading"
            @update:model-value="loadAdjustments"
          />
        </section>
        <ClosingPaymentsTable :rows="detail.payments.data" /><q-pagination
          class="q-mt-md"
          :model-value="detail.payments.current_page"
          :max="detail.payments.last_page"
          :max-pages="5"
          :disable="loading"
          @update:model-value="loadDetail" /></q-card-section
    ></q-card>
  </q-page>
</template>
<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { isAxiosError } from 'axios';
import FilterSelect from '../components/FilterSelect.vue';
import ClosingPaymentsTable from '../components/ClosingPaymentsTable.vue';
import { cashService, type ClosingInput } from '../services/cash.service';
import { useCashStore } from '../stores/cash.store';
import { useAuthStore } from '../stores/auth.store';
import { administrationError } from '../services/administration.service';
import { alerts } from '../services/alerts.service';
import { cents, decimal, money } from '../domain/credit-calculation';
const route = useRoute(),
  router = useRouter(),
  auth = useAuthStore(),
  store = useCashStore();
const id = (route.params as { id?: string }).id;
const { records, preview, detail } = storeToRefs(store);
const branches = ref<{ id: string; name: string }[]>([]),
  branch = ref(''),
  date = ref(''),
  today = ref('');
const counted = ref(''),
  notes = ref(''),
  confirmed = ref(false),
  pending = ref<ClosingInput | null>(null);
const loading = ref(false),
  busy = ref(false),
  error = ref('');
const dirty = computed(() => !!(counted.value || notes.value || confirmed.value || pending.value));
const difference = computed(() => {
  try {
    if (!preview.value) return null;
    const d = cents(counted.value) - cents(preview.value.cash_total);
    return (d < 0n ? '-' : '') + decimal(d < 0n ? -d : d);
  } catch {
    return null;
  }
});
function clearPreview() {
  store.resetPreview();
  counted.value = '';
  notes.value = '';
  confirmed.value = false;
}
async function list(page = 1) {
  if (loading.value || busy.value || pending.value) return;
  loading.value = true;
  try {
    await store.list(branch.value, date.value, page);
    error.value = '';
  } catch (e) {
    error.value = administrationError(e);
  } finally {
    loading.value = false;
  }
}
async function prepare(page = 1) {
  if (loading.value || busy.value || pending.value) return;
  loading.value = true;
  try {
    const version = preview.value?.version;
    await store.loadPreview(branch.value, date.value, page);
    if (version && version !== preview.value?.version) {
      confirmed.value = false;
      error.value = 'Los cobros cambiaron. Revisa el efectivo contado antes de cerrar.';
    } else error.value = '';
  } catch (e) {
    error.value = administrationError(e);
    store.resetPreview();
  } finally {
    loading.value = false;
  }
}
async function loadAdjustments(page: number) {
  if (!id) return;
  loading.value = true;
  try {
    await store.load(id, detail.value?.payments.current_page ?? 1, page);
  } catch (e) {
    error.value = administrationError(e);
  } finally {
    loading.value = false;
  }
}
async function loadDetail(page = 1) {
  if (!id) return;
  loading.value = true;
  try {
    await store.load(id, page);
    error.value = '';
  } catch (e) {
    error.value = administrationError(e);
  } finally {
    loading.value = false;
  }
}
async function submit() {
  if (busy.value || loading.value || pending.value || !preview.value || !confirmed.value) return;
  if (difference.value === null)
    return alerts.error('Captura un importe válido de efectivo contado.');
  if (difference.value !== '0.00' && !notes.value.trim())
    return alerts.error('Explica la diferencia antes de cerrar.');
  busy.value = true;
  try {
    if (
      !(await alerts.confirm(
        'Cerrar corte de caja',
        `${preview.value.payment_count} cobros. Efectivo: ${money(preview.value.cash_total)}. Depósitos: ${money(preview.value.bank_total)}. Diferencia de efectivo: ${money(difference.value)}. El cierre no puede editarse. Cobros posteriores requerirán otro corte.`,
        'Sí, cerrar corte',
      ))
    )
      return;
    pending.value = {
      id: crypto.randomUUID(),
      branch_id: branch.value,
      business_date: date.value,
      version: preview.value.version,
      cash_counted: decimal(cents(counted.value)),
      notes: notes.value.trim() || null,
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
    const result = await store.close(pending.value);
    pending.value = null;
    counted.value = '';
    notes.value = '';
    confirmed.value = false;
    busy.value = false;
    await alerts.success('Corte cerrado y conservado');
    await router.push(`/cortes/${result.record.id}`);
  } catch (e) {
    error.value = administrationError(e);
    if (
      isAxiosError(e) &&
      e.response &&
      [401, 403, 404, 409, 422, 429].includes(e.response.status)
    ) {
      pending.value = null;
      confirmed.value = false;
      if (e.response.status === 409) store.resetPreview();
    }
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
const beforeUnload = (event: BeforeUnloadEvent) => {
  if (dirty.value || busy.value) {
    event.preventDefault();
    event.returnValue = '';
  }
};
onBeforeRouteLeave(
  async () =>
    !busy.value &&
    (!dirty.value ||
      (await alerts.confirm(
        'Corte sin confirmar',
        pending.value
          ? 'El resultado del cierre es incierto. Si sales, consulta el historial antes de intentar un nuevo corte.'
          : '¿Deseas salir y descartar la captura del corte?',
        'Salir',
      ))),
);
onMounted(async () => {
  store.clear();
  window.addEventListener('beforeunload', beforeUnload);
  if (id) {
    await loadDetail();
    return;
  }
  loading.value = true;
  try {
    const options = await cashService.options();
    branches.value = options.branches;
    today.value = options.today;
    date.value = options.today;
    branch.value =
      options.branches.find((b) => b.id === auth.user?.branch_id)?.id ??
      options.branches[0]?.id ??
      '';
    await store.list(branch.value, date.value);
  } catch (e) {
    error.value = administrationError(e);
  } finally {
    loading.value = false;
  }
});
onUnmounted(() => {
  store.clear();
  window.removeEventListener('beforeunload', beforeUnload);
});
</script>
<style scoped>
.fields {
  border: 0;
  margin: 0;
  padding: 0;
  min-width: 0;
}
.fields:disabled {
  pointer-events: none;
  opacity: 0.6;
}
.filter-grid,
.totals-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
}
.totals-grid {
  margin: 24px 0;
}
.totals-grid h2 {
  color: #811335;
  margin: 0;
}
@media (max-width: 700px) {
  .filter-grid,
  .totals-grid {
    grid-template-columns: 1fr;
  }
}
</style>
