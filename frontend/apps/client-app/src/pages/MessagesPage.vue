<template>
  <main class="q-pa-md">
    <q-btn flat label="Volver" to="/" icon="arrow_back" /><MessageInbox
      endpoint="/client/messages"
    />
  </main>
</template>
<script setup lang="ts">
import MessageInbox from '../components/MessageInbox.vue';
</script>
