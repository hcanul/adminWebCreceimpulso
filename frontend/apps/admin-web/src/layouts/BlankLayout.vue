<template>
  <q-layout view="lHh Lpr lFf">
    <div class="blank-module-help no-print"><ModuleHelp /></div>
    <q-page-container data-module-help-content>
      <!-- Aquí se inyectará tu LoginPage.vue -->
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup lang="ts">
import ModuleHelp from '../components/ModuleHelp.vue';
</script>

<style scoped>
.blank-module-help {
  position: fixed;
  top: 12px;
  right: 16px;
  z-index: 1900;
  background: white;
  border-radius: 10px;
  box-shadow: 0 2px 8px #262b3814;
}
@media print {
  .blank-module-help {
    display: none !important;
  }
}
</style>
