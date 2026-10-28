<template>
  <main class="client-home">
    <header>
      <q-btn flat round icon="settings" aria-label="Configuración del dispositivo" to="/configuracion" />
      <span class="eyebrow">CRECE IMPULSO · MI CUENTA</span
      ><q-btn flat label="Cerrar sesión" icon="logout" :loading="busy" @click="logout" />
    </header>
    <section class="welcome-card">
      <q-icon name="verified_user" size="36px" />
      <h1>Hola, {{ auth.profile?.name }}</h1>
      <p>Tu acceso está activo y protegido.</p>
      <div class="profile-details">
        <span
          >Número de cliente<strong>{{ auth.profile?.folio }}</strong></span
        ><span
          >Sucursal<strong>{{ auth.profile?.branch }}</strong></span
        >
      </div>
    </section>
    <q-banner v-if="loading" class="q-mt-lg">Cargando tu información…</q-banner>
    <q-banner v-else-if="loadError" class="q-mt-lg"
      >{{ loadError }} <q-btn flat label="Reintentar" @click="load"
    /></q-banner>
    <ClientDemo v-else-if="demo" :data="demo" />
    <q-card v-else flat bordered class="q-pa-lg q-mt-lg"
      ><h2 class="text-h6">Tu acceso está listo</h2>
      <p>
        La consulta de créditos reales aún está en preparación. Consulta tu información de pagos en
        sucursal.
      </p></q-card
    >
  </main>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import ClientDemo from '../components/ClientDemo.vue';
import { loadClientDemo, type DemoData } from '../services/demo.service';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { useClientAuth } from '../stores/auth';
import { errorMessage } from '../services/http';
const auth = useClientAuth();
const router = useRouter();
const busy = ref(false);
const loading = ref(true);
const loadError = ref('');
const demo = ref<DemoData | null>(null);
async function load() {
  loading.value = true;
  loadError.value = '';
  try {
    demo.value = await loadClientDemo();
  } catch (e) {
    loadError.value = errorMessage(e);
  } finally {
    loading.value = false;
  }
}
onMounted(load);
async function logout() {
  if (busy.value) return;
  busy.value = true;
  try {
    await auth.logout();
    await router.replace('/login');
  } catch (e) {
    await Swal.fire({
      icon: 'error',
      title: 'No pudimos cerrar la sesión',
      text: errorMessage(e),
      confirmButtonColor: '#7e1838',
    });
  } finally {
    busy.value = false;
  }
}
</script>
