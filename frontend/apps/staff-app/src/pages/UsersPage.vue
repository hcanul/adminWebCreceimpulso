<template>
  <q-page class="intake-page">
    <div class="intake-heading">
      <div>
        <p class="eyebrow">ADMINISTRACIÓN · ACCESOS</p>
        <h1>Usuarios</h1>
        <p>Cuentas de trabajo, sucursales y responsabilidades.</p>
      </div>
      <q-btn
        color="primary"
        icon="person_add"
        no-caps
        label="Crear usuario"
        :disable="loading"
        @click="open()"
      />
    </div>
    <q-banner class="bg-grey-2 q-mb-lg" rounded
      >Solo el Super Admin administra cuentas y roles. Al asignar el rol Promotor, su perfil queda
      disponible automáticamente para los grupos.</q-banner
    >
    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-md" role="alert">{{
      error
    }}</q-banner>
    <q-card flat bordered class="intake-card">
      <q-card-section
        ><q-form @submit="searchUsers"
          ><q-input
            v-model="search"
            outlined
            label="Buscar por nombre o correo"
            clearable
            maxlength="150"
            ><template #append
              ><q-btn
                flat
                round
                icon="search"
                @click="searchUsers"
                aria-label="Buscar usuarios"
                :disable="loading" /></template></q-input></q-form
      ></q-card-section>
      <q-linear-progress v-if="loading" indeterminate />
      <q-list separator
        ><q-item v-for="user in store.users" :key="user.id" class="q-py-md"
          ><q-item-section avatar
            ><q-avatar color="grey-2" text-color="primary" icon="person" /></q-item-section
          ><q-item-section
            ><q-item-label>{{ user.name }}</q-item-label
            ><q-item-label caption>{{ user.email }}</q-item-label
            ><q-item-label caption
              >{{ user.roles.map((r) => r.name).join(' · ') }} ·
              {{ branchName(user.branch_id) }}</q-item-label
            ><q-item-label caption>{{
              user.is_active ? 'Activo' : 'Inactivo'
            }}</q-item-label></q-item-section
          ><q-item-section side
            ><q-btn
              flat
              round
              color="primary"
              icon="edit"
              :aria-label="`Editar ${user.name}`"
              :disable="loading"
              @click="open(user)" /></q-item-section></q-item
      ></q-list>
      <q-card-section v-if="!loading && !store.users.length" class="text-grey-7 text-center"
        >No se encontraron usuarios.</q-card-section
      >
      <q-card-section class="row justify-between items-center"
        ><span class="text-caption">{{ store.total }} cuentas</span
        ><q-pagination
          v-model="page"
          :max="store.lastPage"
          :max-pages="5"
          :disable="loading"
          @update:model-value="loadUsers"
      /></q-card-section>
    </q-card>
    <q-dialog v-model="dialog" persistent :allow-focus-outside="alertIsOpen"
      ><q-card class="intake-dialog" style="width: 650px; max-width: 95vw"
        ><q-form @submit="save"
          ><q-card-section
            ><h2>{{ editingId ? 'Editar usuario' : 'Crear usuario' }}</h2>
            <p>
              Los campos marcados con * son obligatorios. La contraseña debe contener al menos 12
              caracteres, mayúsculas, minúsculas y números.
            </p>
            <div class="q-gutter-md">
              <q-input
                v-model="form.name"
                outlined
                stack-label
                label="Nombre completo *"
                maxlength="150"
                :rules="[required]"
                autocomplete="name"
              />
              <q-input
                v-model="form.email"
                outlined
                stack-label
                label="Correo electrónico *"
                type="email"
                maxlength="254"
                :rules="[required]"
                autocomplete="off"
              />
              <FilterSelect
                v-model="form.roles"
                outlined
                stack-label
                label="Roles *"
                multiple
                use-chips
                :options="store.roles.map((r) => r.name)"
                :rules="[(v: string[]) => v.length > 0 || 'Selecciona al menos un rol']"
              />
              <FilterSelect
                v-model="form.branch_id"
                outlined
                stack-label
                label="Sucursal"
                :options="store.branches"
                option-label="name"
                option-value="id"
                emit-value
                map-options
                clearable
                :hint="'Obligatoria para Promotor y perfiles de sucursal.'"
              />
              <q-input
                v-model="form.password"
                outlined
                stack-label
                type="password"
                autocomplete="new-password"
                :label="editingId ? 'Nueva contraseña (opcional)' : 'Contraseña *'"
              />
              <q-input
                v-model="form.password_confirmation"
                outlined
                stack-label
                type="password"
                autocomplete="new-password"
                label="Confirmar contraseña"
              />
              <q-toggle v-model="form.is_active" label="Cuenta activa" color="primary" />
              <q-banner v-if="editingId" rounded class="bg-grey-2"
                >Guardar cambios cerrará las sesiones de esta cuenta. Si tiene grupos asignados,
                deberás reasignarlos antes de desactivar su perfil de promotor o cambiar su
                sucursal.</q-banner
              >
            </div></q-card-section
          ><q-card-actions align="right"
            ><q-btn flat no-caps label="Cancelar" :disable="saving" @click="close" /><q-btn
              color="primary"
              no-caps
              type="submit"
              label="Guardar usuario"
              :loading="saving" /></q-card-actions></q-form></q-card
    ></q-dialog>
  </q-page>
</template>
<script setup lang="ts">
import FilterSelect from '../components/FilterSelect.vue';
import { onMounted, onUnmounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAdministrationStore } from '../stores/administration.store';
import { useStaffAuth } from '../stores/auth';
import {
  administrationService,
  administrationError,
  type AdminUser,
  type UserInput,
} from '../services/administration.service';
import { alerts, alertIsOpen } from '../services/alerts.service';
const store = useAdministrationStore(),
  auth = useStaffAuth(),
  route = useRoute(),
  router = useRouter();
const loading = ref(false),
  saving = ref(false),
  dialog = ref(false),
  error = ref(''),
  search = ref<string | null>(''),
  page = ref(1),
  editingId = ref<string>();
const empty = (): UserInput => ({
  name: '',
  email: '',
  branch_id: null,
  is_active: true,
  roles: [],
  password: '',
  password_confirmation: '',
});
const form = reactive<UserInput>(empty());
const required = (v: string) => !!v?.trim() || 'Completa este campo';
const branchName = (id: string | null) =>
  id ? (store.branches.find((b) => b.id === id)?.name ?? 'Sucursal no disponible') : 'Sin sucursal';
async function loadUsers() {
  loading.value = true;
  error.value = '';
  try {
    await store.loadUsers(search.value ?? '', page.value);
  } catch (e) {
    error.value = administrationError(e);
  } finally {
    loading.value = false;
  }
}
async function searchUsers() {
  page.value = 1;
  await loadUsers();
}
function open(user?: AdminUser) {
  editingId.value = user?.id;
  Object.assign(
    form,
    empty(),
    user
      ? {
          name: user.name,
          email: user.email,
          branch_id: user.branch_id,
          is_active: !!user.is_active,
          roles: user.roles.map((r) => r.name),
        }
      : {},
  );
  dialog.value = true;
}
function close() {
  dialog.value = false;
  Object.assign(form, empty());
}
async function save() {
  if (saving.value) return;
  saving.value = true;
  try {
    const payload = { ...form, roles: [...form.roles] };
    if (!payload.password) {
      delete payload.password;
      delete payload.password_confirmation;
    }
    await administrationService.saveUser(payload, editingId.value);
    const own = editingId.value === auth.user?.id;
    close();
    if (own) {
      auth.clear();
      await router.replace('/login');
      void alerts.success('Cuenta actualizada. Inicia sesión nuevamente.');
    } else {
      void alerts.success('Usuario guardado. El perfil de promotor se actualizó según sus roles.');
      await loadUsers();
    }
  } catch (e) {
    void alerts.error(administrationError(e));
  } finally {
    saving.value = false;
  }
}
onMounted(async () => {
  loading.value = true;
  try {
    await store.loadOptions();
    await loadUsers();
    if (route.query.crear === 'promotor') {
      await router.replace({ path: route.path });
      open();
      form.roles = ['Promotor'];
    }
  } catch (e) {
    error.value = administrationError(e);
  } finally {
    loading.value = false;
  }
});
onUnmounted(() => {
  Object.assign(form, empty());
  store.clear();
});
</script>
