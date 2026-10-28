<template>
  <q-layout view="lHh Lpr lFf" class="workspace-shell">
    <q-header bordered class="bg-white text-dark">
      <q-toolbar class="workspace-toolbar">
        <q-btn
          flat
          round
          icon="menu"
          aria-label="Abrir o cerrar menú lateral"
          :aria-expanded="drawer"
          aria-controls="workspace-navigation"
          @click="drawer = !drawer"
        />
        <div class="toolbar-title">
          <span>OFICINA DIGITAL</span><strong>{{ heading }}</strong>
        </div>
        <q-space />
        <q-btn flat round icon="account_circle" aria-label="Abrir opciones de sesión"
          ><q-menu
            ><q-list style="min-width: 240px"
              ><q-item
                ><q-item-section
                  ><q-item-label>{{ auth.user?.name }}</q-item-label
                  ><q-item-label caption>{{ auth.user?.email }}</q-item-label></q-item-section
                ></q-item
              ><q-separator /><q-item clickable :disable="closing" @click="logout"
                ><q-item-section avatar><q-icon name="logout" /></q-item-section
                ><q-item-section>{{
                  closing ? 'Cerrando sesión…' : 'Cerrar sesión'
                }}</q-item-section></q-item
              ></q-list
            ></q-menu
          ></q-btn
        >
      </q-toolbar>
    </q-header>
    <q-drawer
      v-model="drawer"
      show-if-above
      :width="264"
      :breakpoint="1024"
      :behavior="$q.screen.width < 1024 ? 'mobile' : 'desktop'"
      bordered
    >
      <nav id="workspace-navigation" aria-label="Menú principal" class="workspace-navigation">
        <router-link to="/" class="brand-link" aria-label="Crece Impulso, inicio"
          ><img :src="logoUrl" alt="Crece Impulso"
        /></router-link>
        <div class="nav-caption">OPERACIÓN</div>
        <q-list padding
          ><q-item clickable v-ripple to="/" exact active-class="nav-active" class="nav-item"
            ><q-item-section avatar><q-icon name="space_dashboard" /></q-item-section
            ><q-item-section>Inicio</q-item-section></q-item
          >
          <q-item
            v-for="item in operations"
            :key="item.label"
            :disable="!item.to"
            :clickable="!!item.to"
            :to="item.to"
            active-class="nav-active"
            class="nav-item"
            ><q-item-section avatar><q-icon :name="item.icon" /></q-item-section
            ><q-item-section
              ><q-item-label>{{ item.label }}</q-item-label
              ><q-item-label v-if="!item.to" caption>Próximamente</q-item-label></q-item-section
            ></q-item
          >
        </q-list>
        <div class="nav-caption">ADMINISTRACIÓN</div>
        <q-list padding
          ><q-item
            v-for="item in administration"
            :key="item.label"
            :disable="!item.to"
            :clickable="!!item.to"
            :to="item.to"
            active-class="nav-active"
            class="nav-item"
            ><q-item-section avatar><q-icon :name="item.icon" /></q-item-section
            ><q-item-section
              ><q-item-label>{{ item.label }}</q-item-label
              ><q-item-label v-if="!item.to" caption>Próximamente</q-item-label></q-item-section
            ></q-item
          ></q-list
        >
        <div class="nav-profile">
          <q-avatar color="primary" text-color="white" size="38px">{{ initials }}</q-avatar>
          <div>
            <strong>{{ auth.user?.name }}</strong
            ><span>{{ auth.roles.join(' · ') || 'Cuenta de trabajo' }}</span>
          </div>
        </div>
      </nav>
    </q-drawer>
    <q-page-container
      ><q-banner v-if="error" class="bg-red-1 text-negative" role="alert">{{ error }}</q-banner
      ><router-view :key="$route.fullPath"
    /></q-page-container>
  </q-layout>
</template>
<script setup lang="ts">
import { useAlertFeedback } from '../composables/useAlertFeedback';
import { alerts } from '../services/alerts.service';
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.store';
import { apiErrorMessage } from '../services/http';
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const canCapture = computed(() => auth.can('intake.access'));
const heading = computed(() =>
  route.path.startsWith('/accesos-clientes')
    ? 'Accesos de clientes'
    : route.path.startsWith('/administracion/datos-documentales')
      ? 'Datos documentales'
      : route.path.startsWith('/documentos')
        ? 'Documentos'
        : route.path.endsWith('/garantia')
          ? 'Garantía retenida'
          : route.path.startsWith('/cartera')
            ? 'Cartera y vencimientos'
            : route.path.startsWith('/cortes')
              ? 'Cortes de caja'
              : route.path.startsWith('/cobros')
                ? 'Cobros en caja'
                : route.path.startsWith('/desembolsos')
                  ? 'Desembolsos y calendario'
                  : route.path.startsWith('/autorizaciones')
                    ? 'Autorización comercial'
                    : route.path.startsWith('/gestion')
                      ? 'Gestión de solicitudes'
                      : route.path.startsWith('/grupos')
                        ? 'Grupos solidarios'
                        : route.path.startsWith('/solicitudes-credito')
                          ? 'Solicitudes de crédito'
                          : route.path.startsWith('/tasas')
                            ? 'Tasas de crédito'
                            : route.path.startsWith('/clientes')
                              ? 'Clientes y expedientes'
                              : route.path.startsWith('/promotores')
                                ? 'Promotores'
                                : route.path.startsWith('/administracion/usuarios')
                                  ? 'Usuarios'
                                  : route.path.startsWith('/administracion/roles')
                                    ? 'Roles y permisos'
                                    : 'Resumen operativo',
);
const drawer = ref(false);
const closing = ref(false);
const error = ref('');
const logoUrl = `${import.meta.env.BASE_URL}crece-impulso-logo.png`;
const initials = computed(() =>
  (auth.user?.name ?? 'CI')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((name) => name[0])
    .join('')
    .toUpperCase(),
);
const operations = computed(() => [
  ...(canCapture.value
    ? [
        { label: 'Grupos solidarios', icon: 'diversity_3', to: '/grupos' },
        { label: 'Clientes', icon: 'people_outline', to: '/clientes' },
      ]
    : []),
  ...(auth.can('credit.view')
    ? [{ label: 'Solicitudes de crédito', icon: 'assignment', to: '/solicitudes-credito' }]
    : []),
  ...(auth.can('credit.review')
    ? [{ label: 'Gestión de solicitudes', icon: 'fact_check', to: '/gestion' }]
    : []),
  ...(auth.can('credit.authorize') || auth.can('credit.propose')
    ? [{ label: 'Autorización comercial', icon: 'verified', to: '/autorizaciones' }]
    : []),
  ...(auth.can('credit.collect') || auth.can('credit.reverse')
    ? [{ label: 'Cobros en caja', icon: 'payments', to: '/cobros' }]
    : []),
  ...(auth.can('credit.disburse')
    ? [{ label: 'Desembolsos y calendario', icon: 'account_balance_wallet', to: '/desembolsos' }]
    : []),
  ...(canCapture.value ? [{ label: 'Promotores', icon: 'badge', to: '/promotores' }] : []),
  ...(auth.can('credit.view')
    ? [{ label: 'Cartera y vencimientos', icon: 'account_balance_wallet', to: '/cartera' }]
    : []),
  { label: 'Cobros y depósitos', icon: 'payments' },
  ...(auth.can('cash.view')
    ? [{ label: 'Cortes de caja', icon: 'point_of_sale', to: '/cortes' }]
    : []),
]);
const administration = computed(() => [
  ...(auth.roles.some((role) => ['Super Admin', 'Gerente General', 'Gerente'].includes(role))
    ? [{ label: 'Accesos de clientes', icon: 'key', to: '/accesos-clientes' }]
    : []),
  { label: 'Documentos', icon: 'folder_open', to: '/documentos' },
  ...(auth.can('credit.rates')
    ? [{ label: 'Tasas de crédito', icon: 'percent', to: '/tasas' }]
    : []),
  ...(auth.roles.some((role) => ['Super Admin', 'Gerente General'].includes(role))
    ? [{ label: 'Sucursales', icon: 'tune' }]
    : []),
  ...(auth.roles.includes('Super Admin')
    ? [
        { label: 'Usuarios', icon: 'manage_accounts', to: '/administracion/usuarios' },
        { label: 'Datos documentales', icon: 'business', to: '/administracion/datos-documentales' },
        { label: 'Roles y permisos', icon: 'admin_panel_settings', to: '/administracion/roles' },
      ]
    : []),
]);
async function logout() {
  if (closing.value) return;
  closing.value = true;
  error.value = '';
  try {
    if (
      !(await alerts.confirm(
        'Cerrar sesión',
        '¿Deseas cerrar tu sesión en este equipo?',
        'Cerrar sesión',
      ))
    )
      return;
    await auth.logout();
    await router.replace('/login');
  } catch (cause) {
    error.value = apiErrorMessage(cause);
  } finally {
    closing.value = false;
  }
}

useAlertFeedback(error);
</script>
<style scoped lang="scss">
.workspace-shell {
  background: #f4f5f8;
  color: #262b38;
}
.workspace-toolbar {
  min-height: 76px;
  padding: 0 26px;
  gap: 14px;
}
.toolbar-title {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.toolbar-title span {
  font-size: 9px;
  letter-spacing: 2px;
  color: #747c8b;
}
.toolbar-title strong {
  font-size: 14px;
}
.workspace-navigation {
  min-height: 100%;
  display: flex;
  flex-direction: column;
  padding: 25px 14px;
}
.brand-link {
  padding: 9px 9px 27px;
  display: block;
}
.brand-link img {
  width: 100%;
  display: block;
}
.nav-caption {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1.8px;
  color: #858b99;
  padding: 20px 16px 2px;
}
.nav-item {
  border-radius: 9px;
  margin: 4px 0;
  min-height: 51px;
  font-size: 13px;
}
.nav-item :deep(.q-item__section--avatar) {
  min-width: 38px;
}
.nav-item :deep(.q-item__label--caption) {
  font-size: 10px;
}
.nav-active {
  background: #f6e9ee;
  color: #811335;
  font-weight: 700;
}
.nav-profile {
  margin-top: auto;
  padding: 24px 10px 0;
  display: flex;
  gap: 12px;
  align-items: center;
}
.nav-profile div {
  min-width: 0;
}
.nav-profile strong,
.nav-profile span {
  display: block;
  overflow-wrap: anywhere;
}
.nav-profile strong {
  font-size: 12px;
}
.nav-profile span {
  font-size: 10px;
  color: #70798a;
  margin-top: 3px;
}
@media (max-width: 600px) {
  .workspace-toolbar {
    padding: 0 12px;
    min-height: 65px;
  }
}
</style>
