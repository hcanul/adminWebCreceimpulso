<template>
  <q-layout view="hHh Lpr lFf"
    ><q-header class="bg-white text-dark"
      ><q-toolbar
        ><q-btn
          flat
          round
          icon="menu"
          aria-label="Abrir menú"
          @click="drawer = !drawer"
        /><q-toolbar-title
          >Crece Impulso <span class="text-caption">· Colaboradores</span></q-toolbar-title
        ></q-toolbar
      ></q-header
    >
    <q-drawer v-model="drawer" overlay bordered
      ><q-list padding
        ><q-item
          ><q-item-section
            ><strong>{{ auth.user?.name }}</strong
            ><span>{{ auth.roles.join(' · ') }}</span></q-item-section
          ></q-item
        ><q-separator spaced /><q-item clickable to="/" exact @click="drawer = false"
          ><q-item-section avatar><q-icon name="home" /></q-item-section
          ><q-item-section>Mi jornada</q-item-section></q-item
        ><q-item v-if="auth.canIntake" clickable to="/grupos" @click="drawer = false"
          ><q-item-section avatar><q-icon name="groups" /></q-item-section
          ><q-item-section>Grupos</q-item-section></q-item
        ><q-item v-if="auth.canIntake" clickable to="/clientes/expedientes" @click="drawer = false"
          ><q-item-section avatar><q-icon name="person_add" /></q-item-section
          ><q-item-section>Expedientes de clientes</q-item-section></q-item
        ><q-item v-if="auth.isSuperAdmin" clickable to="/usuarios" @click="drawer = false">
          <q-item-section avatar><q-icon name="manage_accounts" /></q-item-section
          ><q-item-section>Usuarios</q-item-section> </q-item
        ><q-item v-if="auth.isSuperAdmin" clickable to="/roles" @click="drawer = false">
          <q-item-section avatar><q-icon name="admin_panel_settings" /></q-item-section
          ><q-item-section>Roles y permisos</q-item-section> </q-item
        ><q-item v-if="auth.canIntake" clickable to="/sin-conexion" @click="drawer = false"
          ><q-item-section avatar><q-icon name="offline_pin" /></q-item-section
          ><q-item-section>Correcciones sin conexión</q-item-section></q-item
        ><q-item clickable to="/mi-acceso" @click="drawer = false">
          <q-item-section avatar><q-icon name="badge" /></q-item-section
          ><q-item-section>Mi acceso</q-item-section> </q-item
        ><q-item clickable to="/configuracion"
          ><q-item-section avatar><q-icon name="settings" /></q-item-section
          ><q-item-section>Configurar servidor</q-item-section></q-item
        ><q-item clickable :disable="busy" @click="logout"
          ><q-item-section avatar><q-icon name="logout" /></q-item-section
          ><q-item-section>Cerrar sesión</q-item-section></q-item
        ></q-list
      ></q-drawer
    >
    <q-page-container
      ><q-banner v-if="!online" class="bg-orange-1 text-dark"
        >Sin conexión. Abre Correcciones sin conexión para editar expedientes preparados. Los demás
        módulos requieren internet.</q-banner
      ><router-view /></q-page-container
  ></q-layout>
</template>
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { useStaffAuth } from '../stores/auth';
const auth = useStaffAuth(),
  router = useRouter(),
  drawer = ref(false),
  busy = ref(false),
  online = ref(navigator.onLine);
function update() {
  online.value = navigator.onLine;
}
onMounted(() => {
  window.addEventListener('online', update);
  window.addEventListener('offline', update);
});
onUnmounted(() => {
  window.removeEventListener('online', update);
  window.removeEventListener('offline', update);
});
async function logout() {
  const result = await Swal.fire({
    title: '¿Cerrar sesión?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Cerrar sesión',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#7e1838',
  });
  if (!result.isConfirmed) return;
  busy.value = true;
  try {
    await auth.logout();
  } catch {
    await Swal.fire({
      icon: 'info',
      title: 'Sesión cerrada en este dispositivo',
      text: 'No fue posible contactar al servidor para revocar el acceso remoto.',
    });
  } finally {
    busy.value = false;
    await router.replace('/login');
  }
}
</script>
