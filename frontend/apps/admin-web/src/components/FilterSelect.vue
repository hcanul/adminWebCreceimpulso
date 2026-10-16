<template>
  <q-select
    v-bind="$attrs"
    :model-value="modelValue"
    :options="filtered"
    :option-label="optionLabel ?? 'label'"
    use-input
    :multiple="multiple ?? false"
    :fill-input="!multiple"
    :hide-selected="!multiple"
    input-debounce="350"
    :loading="remoteLoading"
    stack-label
    @filter="filter"
    @blur="stopSearch"
    @popup-hide="stopSearch"
    @update:model-value="update"
  >
    <template #no-option
      ><q-item
        ><q-item-section class="text-grey-7">{{
          remoteError || 'No hay coincidencias. Prueba con otro texto.'
        }}</q-item-section></q-item
      ></template
    >
  </q-select>
</template>
<script setup lang="ts" generic="T extends string | number | string[] | null | undefined">
import { onBeforeUnmount, ref, watch } from 'vue';
defineOptions({ inheritAttrs: false });
const props = defineProps<{
  modelValue: T;
  multiple?: boolean;
  options?: readonly unknown[];
  optionLabel?: string;
  fetchOptions?: (term: string) => Promise<readonly unknown[]>;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: T] }>();
const filtered = ref<unknown[]>([]);
const remoteLoading = ref(false),
  remoteError = ref('');
let request = 0;
onBeforeUnmount(() => {
  request++;
});
const normalize = (text: string) =>
  text
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('es');
function label(option: unknown): string {
  const value =
    option && typeof option === 'object'
      ? (option as Record<string, unknown>)[props.optionLabel ?? 'label']
      : option;
  return typeof value === 'string' || typeof value === 'number' ? String(value) : '';
}
function filter(term: string, updateOptions: (callback: () => void) => void) {
  const current = ++request;
  remoteError.value = '';
  if (props.fetchOptions) {
    remoteLoading.value = true;
    void props
      .fetchOptions(term.trim())
      .then((options) => {
        if (current === request)
          updateOptions(() => {
            filtered.value = [...options];
          });
      })
      .catch(() => {
        if (current === request)
          updateOptions(() => {
            filtered.value = [];
            remoteError.value =
              'No pudimos consultar las opciones. Vuelve a escribir para reintentar.';
          });
      })
      .finally(() => {
        if (current === request) remoteLoading.value = false;
      });
    return;
  }
  updateOptions(() => {
    filtered.value = (props.options ?? []).filter((option) =>
      normalize(label(option)).includes(normalize(term.trim())),
    );
  });
}
function stopSearch() {
  request++;
  remoteLoading.value = false;
}
function update(value: T) {
  stopSearch();
  emit('update:modelValue', value);
}
watch(
  () => props.options,
  (options) => {
    filtered.value = [...(options ?? [])];
  },
  { immediate: true },
);
</script>
