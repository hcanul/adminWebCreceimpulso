<template>
  <q-page class="intake-page"
    ><div class="intake-heading">
      <div>
        <p class="eyebrow">CATÁLOGO FINANCIERO</p>
        <h1>Tasas de crédito</h1>
        <p>Factor semanal por cada $1,000. Los cambios afectan solicitudes nuevas.</p>
      </div>
      <q-btn color="primary" no-caps icon="add" label="Nueva tasa" @click="open()" />
    </div>
    <q-banner class="bg-grey-2 q-mb-lg"
      >Cada grupo comparte una tasa. El gerente define el límite por integrante al registrar la
      solicitud; los límites del catálogo son opcionales.</q-banner
    ><q-banner v-if="error" class="bg-red-1 text-negative">{{ error }}</q-banner
    ><q-card flat bordered class="intake-card"
      ><q-card-section
        ><q-form @submit="searchRates"
          ><q-input v-model="search" outlined label="Buscar tasa" maxlength="100"
            ><template #append
              ><q-btn
                flat
                round
                icon="search"
                type="submit"
                aria-label="Buscar tasa" /></template></q-input></q-form></q-card-section
      ><q-linear-progress v-if="loading" indeterminate /><q-list separator
        ><q-item v-for="rate in rates" :key="rate.id"
          ><q-item-section
            ><q-item-label>{{ rate.name }} · {{ money(rate.rate_factor) }}</q-item-label
            ><q-item-label caption
              >{{
                rate.calculation_method === 'weekly_per_thousand'
                  ? 'Factor semanal por cada $1,000'
                  : 'Tasa histórica: requiere configuración'
              }}
              · {{ rate.is_active ? 'Activa' : 'Inactiva' }}</q-item-label
            ><q-item-label caption
              >Mínimo: {{ rate.min_amount ? money(rate.min_amount) : 'Sin límite de catálogo' }} ·
              Máximo:
              {{ rate.max_amount ? money(rate.max_amount) : 'Lo define el gerente' }}</q-item-label
            ></q-item-section
          ><q-item-section side
            ><q-btn
              flat
              round
              color="primary"
              icon="edit"
              :aria-label="`Editar ${rate.name}`"
              @click="open(rate)" /></q-item-section></q-item></q-list
      ><q-card-actions align="center"
        ><q-pagination
          v-model="page"
          :max="pages"
          :max-pages="5"
          @update:model-value="load" /></q-card-actions
    ></q-card>
    <q-dialog v-model="dialog" persistent :allow-focus-outside="alertIsOpen"
      ><q-card class="intake-dialog" style="width: 560px; max-width: 95vw"
        ><q-form @submit="save"
          ><q-card-section
            ><h2>{{ editing ? 'Editar tasa' : 'Nueva tasa' }}</h2>
            <div class="q-gutter-md">
              <q-input
                v-model="form.name"
                outlined
                label="Nombre *"
                :rules="[(v) => !!v?.trim() || 'Escribe el nombre']"
              /><q-input
                v-model="form.rate_factor"
                outlined
                inputmode="decimal"
                label="Pago semanal por cada $1,000 (MXN) *"
                hint="Cobre: 80 · Bronce: 75 · Plata: 70"
              /><q-input
                v-model.number="form.cicle"
                outlined
                type="number"
                label="Ciclo de referencia del catálogo"
                min="1"
                max="100"
              /><q-input
                v-model="form.min_amount"
                outlined
                inputmode="decimal"
                label="Mínimo opcional por integrante"
                clearable
              /><q-input
                v-model="form.max_amount"
                outlined
                inputmode="decimal"
                label="Máximo opcional por integrante"
                clearable
              /><q-toggle v-model="form.is_active" label="Disponible para nuevas solicitudes" />
              <p class="text-caption">
                El ciclo se conserva como dato del catálogo; la tasa se selecciona expresamente para
                el grupo. Los créditos ya solicitados conservarán sus condiciones originales.
              </p>
            </div></q-card-section
          ><q-card-actions align="right"
            ><q-btn flat no-caps label="Cancelar" :disable="saving" @click="dialog = false" /><q-btn
              color="primary"
              no-caps
              label="Guardar tasa"
              type="submit"
              :loading="saving" /></q-card-actions></q-form></q-card></q-dialog
  ></q-page>
</template>
<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { creditService, type CreditRate, type RateInput } from '../services/credit.service';
import { administrationError } from '../services/administration.service';
import { alerts, alertIsOpen } from '../services/alerts.service';
import { money } from '../domain/credit-calculation';
const rates = ref<CreditRate[]>([]),
  loading = ref(false),
  saving = ref(false),
  dialog = ref(false),
  error = ref(''),
  search = ref(''),
  page = ref(1),
  pages = ref(1),
  editing = ref<string>();
const empty = (): RateInput => ({
  name: '',
  cicle: 1,
  min_amount: null,
  max_amount: null,
  rate_factor: '',
  is_active: true,
});
const form = reactive<RateInput>(empty());
function open(rate?: CreditRate) {
  editing.value = rate?.id;
  Object.assign(
    form,
    empty(),
    rate
      ? {
          name: rate.name,
          cicle: rate.cicle,
          min_amount: rate.min_amount,
          max_amount: rate.max_amount,
          rate_factor: rate.rate_factor,
          is_active: rate.is_active,
        }
      : {},
  );
  dialog.value = true;
}
async function load() {
  loading.value = true;
  error.value = '';
  try {
    const result = await creditService.rates(search.value, page.value, true);
    rates.value = result.data;
    pages.value = result.last_page;
  } catch (e) {
    error.value = administrationError(e);
  } finally {
    loading.value = false;
  }
}
async function searchRates() {
  page.value = 1;
  await load();
}
async function save() {
  if (saving.value) return;
  saving.value = true;
  try {
    await creditService.saveRate(
      { ...form, min_amount: form.min_amount || null, max_amount: form.max_amount || null },
      editing.value,
    );
    dialog.value = false;
    await load();
    void alerts.success('Tasa guardada. Las solicitudes existentes conservan sus condiciones.');
  } catch (e) {
    void alerts.error(administrationError(e));
  } finally {
    saving.value = false;
  }
}
onMounted(load);
</script>
