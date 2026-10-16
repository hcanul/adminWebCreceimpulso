<template>
  <q-page class="login-page">
    <section class="brand-panel">
      <img :src="logoUrl" alt="Crece Impulso: nuestro impulso, son tus metas" class="brand-logo" />
      <div class="brand-copy">
        <span class="eyebrow">CERCA DE LAS PERSONAS</span>
        <h1>Impulsamos metas.<br />Construimos confianza.</h1>
        <p>
          Un espacio para acompañar a cada grupo, desde su primera solicitud hasta su próximo logro.
        </p>
      </div>
      <div class="brand-footer">Gestión de créditos grupales · Crece Impulso</div>
    </section>
    <section class="login-panel">
      <div class="login-box">
        <span class="eyebrow text-primary">PORTAL ADMINISTRATIVO</span>
        <h2>Te damos la bienvenida</h2>
        <p class="intro">Ingresa con tu cuenta de trabajo para continuar.</p>
        <q-banner
          v-if="auth.restoreFailed"
          rounded
          class="bg-amber-1 text-brown-9 q-mb-lg"
          role="alert"
          >No pudimos verificar tu sesión. Conservamos tu acceso para reintentarlo.<template #action
            ><q-btn flat label="Reintentar conexión" :loading="loading" @click="retry" /></template
        ></q-banner>
        <q-banner v-if="errorMessage" rounded class="bg-red-1 text-negative q-mb-lg" role="alert">{{
          errorMessage
        }}</q-banner>
        <q-form class="q-gutter-md" @submit="submit">
          <q-input
            v-model="email"
            outlined
            label="Correo electrónico"
            type="email"
            autocomplete="username"
            :disable="loading"
            :rules="[required]"
            lazy-rules
            ><template #prepend><q-icon name="mail_outline" /></template
          ></q-input>
          <q-input
            v-model="password"
            outlined
            label="Contraseña"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            :disable="loading"
            :rules="[required]"
            lazy-rules
            ><template #prepend><q-icon name="lock_outline" /></template
            ><template #append
              ><q-btn
                flat
                round
                dense
                :icon="showPassword ? 'visibility_off' : 'visibility'"
                :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                @click="showPassword = !showPassword" /></template
          ></q-input>
          <q-btn
            type="submit"
            unelevated
            color="primary"
            label="Ingresar al sistema"
            :loading="loading"
            class="full-width login-button"
            no-caps
            icon-right="arrow_forward"
          />
        </q-form>
        <p class="help">
          ¿Necesitas acceso o recuperar tu cuenta?<br />Comunícate con la administración del
          sistema.
        </p>
        <div class="session-note">
          <q-icon name="verified_user" size="20px" /><span
            >Tu sesión se conserva al recargar. Ciérrala al terminar si utilizas un equipo
            compartido.</span
          >
        </div>
      </div>
    </section>
  </q-page>
</template>
<script setup lang="ts">
import { useAlertFeedback } from '../../composables/useAlertFeedback';
import { alerts } from '../../services/alerts.service';
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth.store';
import { apiErrorMessage } from '../../services/http';
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const logoUrl = `${import.meta.env.BASE_URL}crece-impulso-logo.png`;
const email = ref('');
const password = ref('');
const showPassword = ref(false);
const loading = ref(false);
const errorMessage = ref('');
const required = (value: string) => !!value.trim() || 'Este campo es obligatorio';
async function enter() {
  const target = route.query.redirect;
  const path =
    typeof target === 'string' &&
    target.startsWith('/') &&
    !target.startsWith('//') &&
    !target.includes('\\') &&
    target !== '/login'
      ? target
      : '/';
  await router.replace(path);
}
async function submit() {
  if (loading.value) return;
  loading.value = true;
  errorMessage.value = '';
  try {
    await auth.login(email.value, password.value);
    password.value = '';
    void alerts.success('Bienvenido a Crece Impulso.');
    if (auth.isAuthenticated) await enter();
  } catch (error) {
    errorMessage.value = apiErrorMessage(error);
  } finally {
    loading.value = false;
  }
}
async function retry() {
  loading.value = true;
  errorMessage.value = '';
  try {
    await auth.retrySession();
    if (auth.isAuthenticated) await enter();
  } finally {
    loading.value = false;
  }
}

useAlertFeedback(errorMessage);
</script>
<style scoped lang="scss">
.login-page {
  display: grid;
  grid-template-columns: 1.05fr 1fr;
  min-height: 100vh;
  background: #fff;
  color: #252936;
}
.brand-panel {
  padding: 60px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  background: radial-gradient(ellipse at bottom left, #e4cbd5, transparent 65%), #f2f0f3;
  border-right: 1px solid #e7e2e7;
  position: relative;
  overflow: hidden;
}
.brand-panel::after {
  content: '';
  position: absolute;
  width: 430px;
  height: 430px;
  border: 1px solid #81133518;
  border-radius: 50%;
  right: -220px;
  bottom: 40px;
  pointer-events: none;
}
.brand-logo {
  width: min(330px, 100%);
}
.eyebrow {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 2px;
}
.brand-copy {
  padding: 70px 0;
  max-width: 520px;
  z-index: 1;
}
.brand-copy h1 {
  font-size: clamp(32px, 3.5vw, 52px);
  line-height: 1.15;
  letter-spacing: -1.5px;
  font-weight: 700;
  margin: 24px 0;
}
.brand-copy p {
  font-size: 17px;
  line-height: 1.8;
  color: #636a79;
  max-width: 400px;
}
.brand-footer {
  font-size: 12px;
  color: #687082;
}
.login-panel {
  padding: 50px 35px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.login-box {
  width: 100%;
  max-width: 410px;
}
.login-box h2 {
  font-size: 29px;
  line-height: 1.3;
  letter-spacing: -0.7px;
  margin: 16px 0 12px;
  font-weight: 700;
}
.intro {
  color: #687082;
  margin-bottom: 35px;
  line-height: 1.7;
}
.login-button {
  height: 52px;
  border-radius: 8px;
  font-weight: 600;
}
.help {
  text-align: center;
  color: #6b7280;
  font-size: 13px;
  line-height: 1.8;
  margin: 28px 0;
}
.session-note {
  display: flex;
  gap: 12px;
  align-items: center;
  border-top: 1px solid #e6e8ee;
  padding-top: 22px;
  color: #777e8b;
  font-size: 12px;
  line-height: 1.6;
}
@media (max-width: 800px) {
  .login-page {
    grid-template-columns: 1fr;
  }
  .brand-panel {
    padding: 25px 30px;
  }
  .brand-logo {
    width: 215px;
  }
  .brand-copy,
  .brand-footer {
    display: none;
  }
  .login-panel {
    padding: 38px 25px;
    align-items: start;
  }
}
</style>
