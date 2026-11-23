<template>
  <div class="mobile-help-shell" data-module-help-content>
    <router-view />
    <div class="mobile-help-launcher no-print">
      <MobileHelp :guide="help" :create-tour="createTour" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount } from 'vue';
import { useRoute } from 'vue-router';
import MobileHelp from '@creceimpulso/mobile-help';
import { helpTourConfig, type HelpStep } from '@creceimpulso/mobile-help/types';
import { mobileHelpFor } from './help/mobile-help';
const route = useRoute();
const help = computed(() => mobileHelpFor(route.path));
async function createTour(steps: HelpStep[], done: () => void) {
  const { driver } = await import('driver.js');
  return driver(helpTourConfig(steps, done));
}
import { trackSessionInactivity } from './services/session-inactivity';
let stop: (() => void) | undefined;
onMounted(() => {
  stop = trackSessionInactivity();
});
onBeforeUnmount(() => stop?.());
</script>

<style>
.mobile-help-shell {
  padding-bottom: calc(84px + env(safe-area-inset-bottom, 0px));
}
.mobile-help-launcher {
  position: fixed;
  right: max(16px, env(safe-area-inset-right, 0px));
  bottom: calc(16px + env(safe-area-inset-bottom, 0px));
  z-index: 1900;
  background: white;
  border: 1px solid #e7dbe0;
  border-radius: 24px;
  box-shadow: 0 3px 14px #262b3820;
}
@media print {
  .mobile-help-launcher {
    display: none !important;
  }
  .mobile-help-shell {
    padding-bottom: 0;
  }
}
</style>
