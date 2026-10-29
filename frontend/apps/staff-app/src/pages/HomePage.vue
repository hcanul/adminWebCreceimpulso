<template>
  <q-page class="page-shell"
    ><section class="welcome">
      <p class="eyebrow">MI JORNADA</p>
      <h1>Hola, {{ auth.user?.name }}</h1>
      <p>Consulta las opciones disponibles para tu cuenta.</p>
      <q-chip color="white" text-color="primary" icon="badge">{{
        auth.roles.join(' · ') || 'Colaborador'
      }}</q-chip>
    </section>
    <q-card v-if="auth.isSuperAdmin" flat bordered class="action-card q-mt-lg"
      ><q-card-section>
        <h2>Administración</h2>
        <p>Administra cuentas, sucursales asignadas, roles y permisos del personal.</p>
        <div class="row q-gutter-sm">
          <q-btn color="primary" no-caps to="/usuarios" label="Administrar usuarios" /><q-btn
            outline
            color="primary"
            no-caps
            to="/roles"
            label="Roles y permisos"
          />
        </div> </q-card-section
    ></q-card>
    <h2>Expedientes y grupos</h2>
    <q-card v-if="auth.canIntake" flat bordered class="action-card"
      ><q-card-section
        ><q-icon name="groups" color="primary" size="38px" />
        <h3>Grupos e integrantes</h3>
        <p>Busca los grupos que tienes autorizados y consulta a sus integrantes.</p>
        <q-btn
          color="primary"
          no-caps
          label="Consultar grupos"
          to="/grupos" /></q-card-section></q-card
    ><q-banner v-else rounded class="bg-grey-2"
      >Tu cuenta no tiene habilitado el acceso a grupos. Solicita a tu administrador que revise tus
      permisos.</q-banner
    >
    <q-card v-if="auth.can('credit.view')" flat bordered class="q-mt-lg"
      ><q-card-section
        ><h2>Créditos y seguimiento</h2>
        <div class="row q-gutter-sm">
          <q-btn color="primary" to="/solicitudes-credito" label="Solicitudes" no-caps /><q-btn
            outline
            color="primary"
            to="/cartera"
            label="Cartera y atrasos"
            no-caps
          /></div></q-card-section
    ></q-card>
    <q-card flat bordered class="q-mt-lg"
      ><q-card-section
        ><h3>Operación por función</h3>
        <p>
          Desde el menú encontrarás las solicitudes, Gestión, autorizaciones, entrega, cartera y
          Caja disponibles según tus permisos. Los cobros se registran únicamente en oficina con
          conexión.
        </p>
        <p class="text-caption">
          La captura de expedientes está disponible desde cada grupo y requiere conexión. Las
          correcciones de borradores preparados se guardan sin conexión. Los documentos se generan
          en admin-web; los promotores no realizan cobros desde esta app.
        </p></q-card-section
      ></q-card
    ></q-page
  >
</template>
<script setup lang="ts">
import { useStaffAuth } from '../stores/auth';
const auth = useStaffAuth();
</script>
