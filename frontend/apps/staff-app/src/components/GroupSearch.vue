<template>
  <div class="group-search">
    <FilterSelect
      v-if="chooseBranch"
      v-model="branchId"
      class="q-mb-md"
      outlined
      stack-label
      label="Sucursal de captura"
      :options="branches"
      option-label="name"
      option-value="id"
      emit-value
      map-options
      :disable="disabled"
      @update:model-value="changeBranch"
    />
    <q-input
      :model-value="query"
      outlined
      stack-label
      label="Buscar grupo por nombre"
      placeholder="Escribe al menos 2 letras"
      maxlength="100"
      autocomplete="off"
      :disable="disabled || !branchId"
      :loading="loading"
      lazy-rules="ondemand"
      :rules="[() => !!modelValue || 'Elige un grupo de las coincidencias']"
      role="combobox"
      aria-autocomplete="list"
      :aria-expanded="open && results.length > 0"
      :aria-controls="listId"
      :aria-activedescendant="active >= 0 ? `${listId}-${active}` : undefined"
      @update:model-value="search"
      @keydown.down.prevent="move(1)"
      @keydown.up.prevent="move(-1)"
      @keydown.enter="chooseActive"
      @keydown.esc="open = false"
    >
      <template #prepend><q-icon name="search" /></template>
      <template #append
        ><q-btn
          v-if="query"
          flat
          round
          dense
          icon="close"
          aria-label="Limpiar grupo"
          :disable="disabled"
          @click="search('')"
      /></template>
    </q-input>
    <p v-if="!branchId" class="text-caption text-grey-7">
      {{
        chooseBranch
          ? 'Selecciona una sucursal para buscar sus grupos.'
          : 'Tu cuenta necesita una sucursal asignada para buscar grupos.'
      }}
    </p>
    <p v-else class="text-caption text-grey-7 q-mt-xs">
      Solo grupos de {{ branchName }} a los que tienes acceso.
    </p>
    <p v-if="error" role="alert" class="text-negative">{{ error }}</p>
    <q-list
      v-if="open && results.length"
      :id="listId"
      bordered
      separator
      role="listbox"
      aria-label="Grupos encontrados"
      class="group-results"
    >
      <q-item
        v-for="(team, index) in results"
        :id="`${listId}-${index}`"
        :key="team.id"
        clickable
        role="option"
        :aria-selected="active === index"
        :active="active === index"
        active-class="bg-grey-2"
        @click="select(team)"
        ><q-item-section
          ><q-item-label>{{ team.name }}</q-item-label
          ><q-item-label caption
            >{{ team.branch.name }} ·
            {{ team.promoter?.name ?? 'Promotor pendiente' }}</q-item-label
          ><q-item-label caption class="uuid-text">{{ team.id }}</q-item-label></q-item-section
        ></q-item
      >
    </q-list>
    <p
      v-if="open && searched && !loading && !results.length && !error"
      role="status"
      class="text-caption"
    >
      No hay grupos que coincidan en esta sucursal. Revisa el nombre o crea primero el grupo.
    </p>
    <p v-if="open && hasMore" class="text-caption" role="status">
      Hay más coincidencias. Escribe más letras para precisar la búsqueda.
    </p>
    <p v-if="modelValue" class="text-caption text-primary q-mt-xs">
      Seleccionado: {{ modelValue.name }} · {{ modelValue.branch.name }}<br /><span
        class="uuid-text"
        >{{ modelValue.id }}</span
      >
    </p>
  </div>
</template>
<script setup lang="ts">
import FilterSelect from './FilterSelect.vue';
import { computed, onMounted, onBeforeUnmount, ref, useId, watch } from 'vue';
import { useStaffAuth as useAuthStore } from '../stores/auth';
import {
  intakeService,
  intakeError,
  type Team,
  type BranchOption,
} from '../services/intake.service';
const props = defineProps<{ modelValue: Team | null; disabled?: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [team: Team | null] }>();
const auth = useAuthStore();
const chooseBranch = computed(
  () =>
    !auth.user?.branch_id && auth.roles.some((r) => ['Super Admin', 'Gerente General'].includes(r)),
);
const branchId = ref<string | null>(auth.user?.branch_id ?? props.modelValue?.branch_id ?? null);
const branches = ref<BranchOption[]>([]),
  results = ref<Team[]>([]),
  query = ref(props.modelValue?.name ?? '');
const open = ref(false),
  loading = ref(false),
  searched = ref(false),
  hasMore = ref(false),
  error = ref(''),
  active = ref(-1);
const listId = `group-results-${useId()}`;
const branchName = computed(
  () =>
    branches.value.find((b) => b.id === branchId.value)?.name ??
    props.modelValue?.branch.name ??
    'tu sucursal',
);
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;
let generation = 0;
function cancel() {
  generation++;
  clearTimeout(timer);
  controller?.abort();
  loading.value = false;
}
function search(value: string | number | null) {
  cancel();
  query.value = String(value ?? '');
  emit('update:modelValue', null);
  results.value = [];
  error.value = '';
  active.value = -1;
  hasMore.value = false;
  searched.value = false;
  open.value = true;
  if (query.value.trim().length < 2 || !branchId.value) return;
  const current = generation,
    term = query.value.trim(),
    branch = branchId.value;
  loading.value = true;
  timer = setTimeout(() => {
    void (async () => {
      controller = new AbortController();
      try {
        const response = await intakeService.searchTeams(term, branch, controller.signal);
        if (current !== generation) return;
        results.value = response.data;
        hasMore.value = response.has_more;
        searched.value = true;
      } catch (e) {
        if (current === generation) error.value = intakeError(e).message;
      } finally {
        if (current === generation) loading.value = false;
      }
    })();
  }, 350);
}
function select(team: Team) {
  cancel();
  query.value = team.name;
  open.value = false;
  emit('update:modelValue', team);
}
function move(direction: number) {
  if (!results.value.length) return;
  open.value = true;
  active.value = (active.value + direction + results.value.length) % results.value.length;
}
function chooseActive(event: KeyboardEvent) {
  if (open.value) {
    event.preventDefault();
    const team = results.value[active.value];
    if (team) select(team);
  }
}
function changeBranch() {
  search('');
}
watch(
  () => props.modelValue,
  (team) => {
    if (team) {
      cancel();
      query.value = team.name;
      branchId.value = team.branch_id;
      open.value = false;
    }
  },
);
onMounted(async () => {
  try {
    branches.value = (await intakeService.options()).branches;
  } catch (e) {
    error.value = intakeError(e).message;
  }
});
onBeforeUnmount(cancel);
</script>
<style scoped>
.group-search {
  min-width: 0;
}
.group-results {
  max-height: 300px;
  overflow-y: auto;
  border-radius: 8px;
  background: white;
}
</style>
