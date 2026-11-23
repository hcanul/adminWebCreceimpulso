<template>
  <router-view />
</template>

<script setup lang="ts">
import { onMounted, onBeforeUnmount } from 'vue';
import { trackSessionInactivity } from './services/session-inactivity';
let stop: (() => void) | undefined;
onMounted(() => {
  stop = trackSessionInactivity();
});
onBeforeUnmount(() => stop?.());
</script>
