<template>
  <q-page class="page-shell">
    <p class="eyebrow">MI CUENTA</p>
    <h1>Mi acceso</h1>
    <q-card flat bordered
      ><q-card-section
        ><h2>{{ auth.user?.name }}</h2>
        <p>{{ auth.user?.email }}</p>
        <q-chip v-for="role in auth.roles" :key="role" color="primary" text-color="white">{{
          role
        }}</q-chip>
        <p v-if="auth.isSuperAdmin">Acceso total. Puedes administrar usuarios, roles y permisos.</p>
        <p v-else>
          Tu alcance depende de los permisos, la sucursal y los grupos asignados. El servidor valida
          cada operación.
        </p>
      </q-card-section></q-card
    >
    <h2>Permisos de tu cuenta</h2>
    <q-list bordered separator
      ><q-item v-for="permission in auth.user?.permissions" :key="permission"
        ><q-item-section>{{ permissionLabels[permission] || permission }}</q-item-section></q-item
      ></q-list
    >
    <p class="text-caption">
      Esta lista muestra los permisos del sistema. Los módulos financieros y de oficina aún se
      utilizan desde admin-web; se incorporarán al móvil por etapas. La administración de accesos
      requiere conexión.
    </p>
  </q-page>
</template>
<script setup lang="ts">
import { useStaffAuth } from '../stores/auth';
import { permissionLabels } from '../services/administration.service';
const auth = useStaffAuth();
</script>
