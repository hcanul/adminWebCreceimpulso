<template>
  <q-page class="profiles q-pa-md">
    <h1>Datos documentales por sucursal</h1>
    <p>
      Configura un domicilio general o datos propios por sucursal. Puedes completar los campos al
      iniciar operaciones.
    </p>
    <FilterSelect
      v-model="scope"
      label="Empresa o sucursal"
      outlined
      emit-value
      map-options
      :options="options"
      :disable="busy"
    />
    <q-banner v-if="error" class="bg-red-1 text-negative q-my-md" role="alert"
      >{{ error }}<q-btn flat label="Recargar" @click="load"
    /></q-banner>
    <q-linear-progress v-if="busy" indeterminate class="q-mt-md" />
    <q-card v-if="draft" flat bordered class="q-mt-lg"
      ><q-card-section>
        <h2>
          {{ scope === 'general' ? 'Datos generales de la empresa' : 'Configuración de sucursal' }}
        </h2>
        <q-toggle
          v-if="scope !== 'general'"
          v-model="draft.use_general"
          label="Usar los datos generales de la empresa"
          :disable="busy"
        />
        <p v-if="inherited">
          Esta sucursal utiliza el perfil general. Desactiva la opción para capturar sus propios
          datos.
        </p>
        <div class="fields">
          <q-input
            v-for="field in fields"
            :key="field.key"
            :model-value="inherited ? store.general?.fields[field.key] : draft.fields[field.key]"
            @update:model-value="
              (value) => {
                if (draft) draft.fields[field.key] = String(value ?? '');
              }
            "
            :label="field.label"
            outlined
            stack-label
            :type="field.key.includes('address') ? 'textarea' : 'text'"
            maxlength="600"
            :disable="busy || inherited"
          />
        </div>
        <q-banner class="bg-grey-2 q-mt-md"
          >Los campos vacíos quedan pendientes. Guardar estos datos no autoriza el uso de un
          contrato definitivo ni modifica documentos emitidos.</q-banner
        >
        <q-btn
          class="q-mt-md"
          color="primary"
          label="Guardar configuración"
          :loading="busy"
          @click="save"
        /> </q-card-section
    ></q-card>
  </q-page>
</template>
<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import FilterSelect from '../components/FilterSelect.vue';
import { useDocumentProfilesStore } from '../stores/document-profiles.store';
import type { DocumentProfile } from '../services/document-profiles.service';
import { administrationError } from '../services/administration.service';
import { alerts } from '../services/alerts.service';
const store = useDocumentProfilesStore();
const scope = ref('general'),
  busy = ref(false),
  error = ref('');
const draft = ref<DocumentProfile | null>(null);
let disposed = false;
const options = computed(() => [
  { label: 'Empresa · Datos generales', value: 'general' },
  ...store.branches.map((b) => ({ label: b.name, value: b.id })),
]);
const inherited = computed(() => scope.value !== 'general' && draft.value?.use_general);
const fields: { key: keyof DocumentProfile['fields']; label: string }[] = [
  { key: 'legal_address', label: 'Domicilio legal' },
  { key: 'representative_name', label: 'Nombre del representante' },
  { key: 'representative_position', label: 'Cargo del representante' },
  { key: 'payment_place', label: 'Lugar de pago' },
  { key: 'signing_place', label: 'Lugar habitual de firma' },
];
async function load() {
  busy.value = true;
  error.value = '';
  draft.value = null;
  try {
    await store.load(scope.value);
    if (!disposed && store.data)
      draft.value = JSON.parse(JSON.stringify(store.data.profile)) as DocumentProfile;
  } catch (e) {
    if (!disposed) error.value = administrationError(e);
  } finally {
    if (!disposed) busy.value = false;
  }
}
async function save() {
  if (!draft.value || busy.value) return;
  busy.value = true;
  try {
    await store.save(draft.value);
    if (!disposed && store.data) {
      draft.value = JSON.parse(JSON.stringify(store.data.profile)) as DocumentProfile;
      await alerts.success('Configuración guardada');
    }
  } catch (e) {
    if (!disposed) await alerts.error(administrationError(e));
  } finally {
    if (!disposed) busy.value = false;
  }
}
watch(scope, () => void load(), { immediate: true });
onBeforeUnmount(() => {
  disposed = true;
  store.clear();
});
</script>
<style scoped>
.profiles {
  max-width: 1000px;
  margin: auto;
}
h1 {
  font-size: 26px;
  line-height: 1.3;
  color: #811335;
}
h2 {
  font-size: 20px;
  line-height: 1.4;
}
.fields {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
  margin-top: 20px;
}
@media (max-width: 650px) {
  .fields {
    grid-template-columns: 1fr;
  }
}
</style>
