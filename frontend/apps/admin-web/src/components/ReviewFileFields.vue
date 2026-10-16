<template>
  <dl class="file-fields">
    <template v-for="field in visibleFields" :key="field.name">
      <div>
        <dt>{{ field.label }}</dt>
        <dd>{{ display(fields[field.name]) }}</dd>
      </div>
    </template>
  </dl>
</template>
<script setup lang="ts">
import schema from '../domain/intake-fields.json';
defineProps<{ fields: Record<string, unknown> }>();
const visibleFields = schema.filter(
  (field) => !['city_id', 'team_id', 'promoter_id', 'nCredito'].includes(field.name),
);
const display = (value: unknown) =>
  value === null || value === undefined || value === ''
    ? 'Sin capturar'
    : typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean'
      ? String(value)
      : JSON.stringify(value);
</script>
<style scoped>
.file-fields {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
}
.file-fields div {
  min-width: 0;
}
dt {
  font-size: 12px;
  color: #646b79;
}
dd {
  margin: 4px 0 0;
  overflow-wrap: anywhere;
}
</style>
