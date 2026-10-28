<template>
  <main class="login-shell">
    <section class="brand-hero">
      <img src="/crece-impulso-logo.png" alt="Crece Impulso" />
      <p class="eyebrow">COLABORADORES</p>
      <h1>Cerca de cada grupo.<br />Contigo en cada visita.</h1>
      <p>Tu espacio de trabajo para acompañar a nuestros clientes.</p>
    </section>
    <q-card flat class="login-card"
      ><q-card-section
        ><h2>Inicia tu jornada</h2>
        <p>Ingresa con tu cuenta de colaborador.</p>
        <q-form @submit="login" class="q-gutter-y-md">
          <q-input
            v-model="email"
            outlined
            stack-label
            label="Correo electrónico"
            type="email"
            autocomplete="username"
            :disable="busy"
            :rules="[(v) => !!v?.trim() || 'Escribe tu correo']"
          />
          <q-input
            v-model="password"
            outlined
            stack-label
            label="Contraseña"
            :type="show ? 'text' : 'password'"
            autocomplete="current-password"
            :disable="busy"
            :rules="[(v) => !!v || 'Escribe tu contraseña']"
            ><template #append
              ><q-btn
                flat
                round
                :icon="show ? 'visibility_off' : 'visibility'"
                aria-label="Mostrar u ocultar contraseña"
                @click="show = !show" /></template
          ></q-input>
          <q-btn
            type="submit"
            color="primary"
            no-caps
            label="Entrar"
            size="lg"
            class="full-width"
            :loading="busy"
          />
        </q-form>
        <q-btn
          flat
          no-caps
          icon="settings"
          class="full-width q-mt-lg"
          label="Configuración del dispositivo"
          to="/configuracion"
          :disable="busy"
        />
        <q-btn
          class="full-width q-mt-md"
          outline
          no-caps
          color="primary"
          label="Correcciones sin conexión"
          to="/sin-conexion"
        />
        <p class="text-caption">
          La cuenta la administra tu sucursal. El primer acceso necesita conexión a internet.
        </p>
      </q-card-section></q-card
    >
  </main>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { useStaffAuth } from '../stores/auth';
import { errorMessage } from '../services/http';
const email = ref(''),
  password = ref(''),
  busy = ref(false),
  show = ref(false);
const auth = useStaffAuth(),
  router = useRouter();
async function login() {
  if (busy.value) return;
  busy.value = true;
  try {
    await auth.login(email.value.trim(), password.value);
    password.value = '';
    await router.replace('/');
  } catch (e) {
    await Swal.fire({
      icon: 'error',
      title: 'Revisa tu acceso',
      text: errorMessage(e),
      confirmButtonColor: '#7e1838',
    });
  } finally {
    busy.value = false;
  }
}
</script>
