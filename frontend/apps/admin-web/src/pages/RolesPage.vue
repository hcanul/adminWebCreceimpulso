<template>
  <q-page class="intake-page"
    ><div class="intake-heading">
      <div>
        <p class="eyebrow">ADMINISTRACIÓN · SEGURIDAD</p>
        <h1>Roles y permisos</h1>
        <p>Define qué operaciones puede realizar cada perfil.</p>
      </div>
      <q-btn
        color="primary"
        no-caps
        icon="add_moderator"
        label="Crear rol"
        :disable="loading"
        @click="open()"
      />
    </div>
    <q-banner rounded class="bg-grey-2 q-mb-lg"
      >Los permisos habilitan operaciones; no amplían el acceso a sucursales. Los roles nuevos
      quedan limitados a su sucursal y a los grupos que crean. Solo Super Admin administra usuarios,
      roles y permisos; esta facultad no es delegable desde este catálogo.</q-banner
    >
    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-md" role="alert">{{
      error
    }}</q-banner>
    <q-card flat bordered class="intake-card"
      ><q-linear-progress v-if="loading" indeterminate /><q-list separator
        ><q-item v-for="role in store.roles" :key="role.id" class="q-py-md"
          ><q-item-section avatar
            ><q-avatar color="grey-2" text-color="primary" icon="shield" /></q-item-section
          ><q-item-section
            ><q-item-label>{{ role.name }}</q-item-label
            ><q-item-label caption>{{
              role.name === 'Super Admin'
                ? 'Acceso total · rol protegido'
                : role.permissions.map((p) => permissionLabel(p.name)).join(' · ') ||
                  'Sin permisos operativos de captura'
            }}</q-item-label></q-item-section
          ><q-item-section side
            ><q-btn
              v-if="role.name !== 'Super Admin'"
              flat
              round
              color="primary"
              icon="edit"
              :aria-label="`Editar permisos de ${role.name}`"
              @click="open(role)" /></q-item-section></q-item></q-list
    ></q-card>
    <q-dialog v-model="dialog" persistent :allow-focus-outside="alertIsOpen"
      ><q-card class="intake-dialog" style="width: 580px; max-width: 95vw"
        ><q-form @submit="save"
          ><q-card-section
            ><h2>{{ editing ? `Permisos de ${editing.name}` : 'Crear rol' }}</h2>
            <q-input
              v-if="!editing"
              v-model="name"
              outlined
              stack-label
              label="Nombre del rol"
              maxlength="80"
              :rules="[(v) => !!v?.trim() || 'Escribe un nombre']"
            />
            <p>
              Los cambios afectan a todos los usuarios con este rol. Con varios roles, sus permisos
              se suman.
            </p>
            <div v-for="permission in store.permissions" :key="permission.id">
              <q-checkbox
                v-model="selected"
                :val="permission.name"
                :label="permissionLabel(permission.name)"
                color="primary"
              />
            </div>
            <p class="text-caption">
              Para enviar expedientes o vincular promotores, habilita también el acceso a grupos y
              expedientes. Los nombres de roles existentes se conservan para proteger las reglas de
              alcance.
            </p></q-card-section
          ><q-card-actions align="right"
            ><q-btn flat no-caps label="Cancelar" :disable="saving" @click="dialog = false" /><q-btn
              color="primary"
              no-caps
              label="Guardar permisos"
              type="submit"
              :loading="saving" /></q-card-actions></q-form></q-card
    ></q-dialog>
  </q-page>
</template>
<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { useAdministrationStore } from '../stores/administration.store';
import {
  administrationService,
  administrationError,
  permissionLabels,
  type AdminRole,
} from '../services/administration.service';
import { alerts, alertIsOpen } from '../services/alerts.service';
const store = useAdministrationStore();
const permissionLabel = (name: string) => permissionLabels[name] ?? name;
const loading = ref(false),
  saving = ref(false),
  error = ref(''),
  dialog = ref(false),
  name = ref(''),
  selected = ref<string[]>([]),
  editing = ref<AdminRole>();
function open(role?: AdminRole) {
  editing.value = role;
  name.value = '';
  selected.value = role?.permissions.map((p) => p.name) ?? [];
  dialog.value = true;
}
async function load() {
  loading.value = true;
  error.value = '';
  try {
    await store.loadOptions();
  } catch (e) {
    error.value = administrationError(e);
  } finally {
    loading.value = false;
  }
}
async function save() {
  if (saving.value) return;
  saving.value = true;
  try {
    if (
      editing.value &&
      !(await alerts.confirm(
        'Modificar permisos',
        `El cambio se aplicará a todas las cuentas con rol ${editing.value.name}.`,
        'Guardar cambios',
      ))
    )
      return;
    await administrationService.saveRole(selected.value, editing.value?.id, name.value.trim());
    dialog.value = false;
    await load();
    void alerts.success(
      'Rol guardado. Los permisos se aplican en el servidor inmediatamente; actualiza la sesión para renovar el menú.',
    );
  } catch (e) {
    void alerts.error(administrationError(e));
  } finally {
    saving.value = false;
  }
}
onMounted(load);
onUnmounted(() => store.clear());
</script>
