<template>
  <q-dialog v-model="visible" persistent :allow-focus-outside="alertIsOpen">
    <q-card class="intake-dialog" style="width: 540px; max-width: 95vw">
      <q-form @submit="save"
        ><q-card-section>
          <h2>{{ team ? 'Editar grupo' : 'Crear grupo' }}</h2>
          <p>
            Primero registra el grupo; después incorpora a sus integrantes. Los nombres pueden
            repetirse.
          </p>
          <q-linear-progress v-if="loading" indeterminate />
          <div class="q-gutter-md">
            <q-input
              v-model="form.name"
              outlined
              stack-label
              label="Nombre del grupo"
              maxlength="150"
              :rules="[required]"
              :disable="loading || saving"
            />
            <FilterSelect
              v-model="form.branch_id"
              outlined
              label="Sucursal"
              :options="branches"
              option-label="name"
              option-value="id"
              emit-value
              map-options
              :disable="!!team || loading || saving"
              :rules="[required]"
              @update:model-value="branchChanged"
            />
            <FilterSelect
              v-model="form.promoter_id"
              outlined
              label="Promotor responsable"
              :options="promoters"
              option-label="name"
              option-value="id"
              emit-value
              map-options
              :rules="[required]"
              :disable="loading || saving || ownOnly"
            />
          </div>
          <p v-if="ownOnly" class="text-caption">
            Como promotor, los grupos se asignan a tu perfil activo.
          </p>
          <p v-if="!loading && form.branch_id && !promoters.length">
            No hay promotores habilitados en esta sucursal. Solicita el alta de una cuenta con rol
            Promotor.
          </p>
          <p v-if="team" class="text-caption">El UUID y la sucursal se conservan.</p>
          <q-banner v-if="error" class="bg-red-1 q-mt-md" role="alert">{{
            error
          }}</q-banner> </q-card-section
        ><q-card-actions align="right"
          ><q-btn flat no-caps label="Cancelar" :disable="saving" @click="visible = false" /><q-btn
            type="submit"
            color="primary"
            no-caps
            label="Guardar grupo"
            :disable="loading || !promoters.length"
            :loading="saving" /></q-card-actions
      ></q-form>
    </q-card>
  </q-dialog>
</template>
<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import FilterSelect from './FilterSelect.vue';
import {
  intakeService,
  intakeError,
  type Team,
  type BranchOption,
  type PromoterOption,
} from '../services/intake.service';
import { useStaffAuth } from '../stores/auth';
import { alerts, alertIsOpen } from '../services/alerts.service';
const visible = defineModel<boolean>({ required: true });
const props = defineProps<{ team?: Team }>();
const emit = defineEmits<{ saved: [team: Team] }>();
const auth = useStaffAuth(),
  loading = ref(false),
  saving = ref(false),
  error = ref('');
const branches = ref<BranchOption[]>([]),
  allPromoters = ref<PromoterOption[]>([]);
const form = reactive({ name: '', branch_id: '', promoter_id: '' });
const ownOnly = computed(
  () =>
    auth.roles.includes('Promotor') &&
    !auth.roles.some((r) => ['Super Admin', 'Gerente General', 'Gerente'].includes(r)),
);
const promoters = computed(() =>
  allPromoters.value.filter(
    (p) => p.branch_id === form.branch_id && (!ownOnly.value || p.id === auth.user?.id),
  ),
);
const required = (v: string | null) => !!v?.trim() || 'Este campo es obligatorio';
function branchChanged() {
  form.promoter_id = auth.roles.includes('Promotor')
    ? promoters.value.find((p) => p.id === auth.user?.id)?.id || ''
    : '';
}
let generation = 0;
watch(visible, async (open) => {
  const current = ++generation;
  if (!open) {
    Object.assign(form, { name: '', branch_id: '', promoter_id: '' });
    return;
  }
  loading.value = true;
  error.value = '';
  branches.value = [];
  allPromoters.value = [];
  try {
    const options = await intakeService.options();
    if (current !== generation) return;
    branches.value = options.branches;
    allPromoters.value = options.promoters;
    Object.assign(form, {
      name: props.team?.name || '',
      branch_id:
        props.team?.branch_id || (branches.value.length === 1 ? branches.value[0]!.id : ''),
      promoter_id: '',
    });
    if (props.team)
      form.promoter_id = promoters.value.find((p) => p.id === props.team?.promoter_id)?.id || '';
    else branchChanged();
  } catch (e) {
    if (current === generation) error.value = intakeError(e).message;
  } finally {
    if (current === generation) loading.value = false;
  }
});
async function save() {
  if (saving.value || loading.value) return;
  saving.value = true;
  error.value = '';
  try {
    const result = await intakeService.saveTeam(
      { ...form, name: form.name.trim() },
      props.team?.id,
    );
    visible.value = false;
    emit('saved', result);
    void alerts.success('Grupo guardado correctamente.');
  } catch (e) {
    const result = intakeError(e);
    error.value = Object.values(result.fields).flat().join(' ') || result.message;
    void alerts.error(error.value);
  } finally {
    saving.value = false;
  }
}
</script>
