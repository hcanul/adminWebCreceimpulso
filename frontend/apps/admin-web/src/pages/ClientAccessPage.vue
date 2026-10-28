<template>
  <q-page class="access-page q-pa-lg">
    <header class="q-mb-lg">
      <div class="text-overline text-primary">ATENCIÓN EN SUCURSAL</div>
      <h1 class="text-h4 q-my-sm">Activación de clientes</h1>
      <p class="text-grey-7">
        Verifica presencialmente la identidad antes de entregar un código de acceso.
      </p>
    </header>
    <q-card flat bordered class="q-pa-lg">
      <q-form @submit="search" class="q-gutter-md">
        <q-input
          v-model="identifier"
          outlined
          label="CURP o número de cliente completo"
          maxlength="80"
          :disable="busy"
        />
        <q-btn
          type="submit"
          color="primary"
          label="Buscar cliente"
          icon="person_search"
          :loading="busy"
          :disable="identifier.trim().length < 3"
        />
      </q-form>
      <p v-if="searched && !clients.length" class="q-mt-lg">
        No se encontró un cliente activo disponible en tu sucursal con ese identificador.
      </p>
    </q-card>
    <q-card v-for="client in clients" :key="client.id" flat bordered class="q-pa-lg q-mt-lg">
      <h2 class="text-h6 q-mt-none">{{ client.first_name }} {{ client.last_name }}</h2>
      <p>{{ client.folio }} · {{ client.branch.name }}</p>
      <q-checkbox
        v-model="verified"
        label="Verifiqué presencialmente la identidad del titular"
        :disable="busy || !!issued"
      />
      <div class="q-mt-md">
        <q-btn
          color="primary"
          label="Generar código de un solo uso"
          :disable="!verified || busy || !!issued"
          :loading="busy"
          @click="issue(client.id)"
        />
      </div>
    </q-card>
    <q-banner v-if="issued" rounded class="bg-grey-2 q-mt-lg q-pa-lg">
      <div class="text-weight-bold">Entrega este código únicamente al titular</div>
      <code class="activation-code">{{ issued.code }}</code>
      <p>
        Vence: {{ new Date(issued.expires_at).toLocaleString('es-MX') }}. No se guarda en este
        navegador. Al salir o recargar se oculta.
      </p>
      <p>
        Generar un código nuevo invalida el anterior. Una cuenta ya activada no puede reemplazarse
        desde aquí.
      </p>
    </q-banner>
  </q-page>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import { clientAccessService, type ActivationClient } from '../services/client-access.service';
import { alerts } from '../services/alerts.service';
import { administrationError } from '../services/administration.service';
const identifier = ref('');
const clients = ref<ActivationClient[]>([]);
const busy = ref(false);
const verified = ref(false);
const searched = ref(false);
const issued = ref<{ code: string; expires_at: string } | null>(null);
async function search() {
  if (busy.value) return;
  busy.value = true;
  issued.value = null;
  verified.value = false;
  clients.value = [];
  searched.value = false;
  try {
    clients.value = await clientAccessService.search(identifier.value.trim().toUpperCase());
    searched.value = true;
  } catch (e) {
    await alerts.error(administrationError(e));
  } finally {
    busy.value = false;
  }
}
async function issue(id: string) {
  if (busy.value || !verified.value) return;
  busy.value = true;
  try {
    issued.value = await clientAccessService.issue(id);
  } catch (e) {
    await alerts.error(administrationError(e));
  } finally {
    busy.value = false;
  }
}
</script>
<style scoped>
.access-page {
  max-width: 980px;
  margin: auto;
}
.activation-code {
  display: block;
  overflow-wrap: anywhere;
  font-size: 1.3rem;
  letter-spacing: 0.08em;
  margin: 18px 0;
  color: #7e1838;
}
</style>
