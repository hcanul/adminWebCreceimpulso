<template>
  <q-page class="q-pa-lg compose">
    <h1 class="text-h4">Enviar mensaje</h1>
    <p>
      Elige un cliente o colaborador autorizado de tu sucursal. El mensaje quedará en su bandeja y
      se intentará avisar a sus dispositivos registrados.
    </p>
    <q-form @submit="send" class="q-gutter-md">
      <q-btn-toggle
        v-model="type"
        :options="[
          { label: 'Cliente', value: 'client' },
          { label: 'Colaboradores', value: 'staff' },
        ]"
        :disable="busy"
        no-caps
        toggle-color="primary"
      />
      <q-select
        v-model="branch"
        :options="branchOptions"
        option-label="name"
        use-input
        clearable
        outlined
        label="Sucursal"
        :loading="loadingBranches"
        :disable="busy || loadingBranches"
        :rules="[(v) => type !== 'client' || !!v || 'Selecciona una sucursal']"
        @filter="filterBranches"
      >
        <template #no-option
          ><q-item
            ><q-item-section>No hay sucursales disponibles.</q-item-section></q-item
          ></template
        >
      </q-select>
      <q-btn v-if="branchError" flat label="Reintentar carga de sucursales" @click="loadBranches" />
      <q-select
        v-model="recipient"
        :options="options"
        option-label="name"
        use-input
        fill-input
        hide-selected
        clearable
        outlined
        :input-debounce="350"
        :label="type === 'client' ? 'Cliente' : 'Colaborador'"
        :disable="busy || (type === 'client' && !branch)"
        :loading="searching"
        hint="Escribe desde la primera letra del nombre. Hasta 20 coincidencias; escribe más letras para precisar."
        @filter="filterRecipients"
        @filter-abort="cancelSearch"
      >
        <template #option="scope"
          ><q-item v-bind="scope.itemProps"
            ><q-item-section
              ><q-item-label>{{ scope.opt.name }}</q-item-label
              ><q-item-label caption
                >{{ scope.opt.identifier }} ·
                {{ scope.opt.branch || 'Acceso general' }}</q-item-label
              ></q-item-section
            ></q-item
          ></template
        >
        <template #no-option
          ><q-item
            ><q-item-section
              >Escribe el inicio del nombre o prueba con otras letras.</q-item-section
            ></q-item
          ></template
        >
      </q-select>
      <p v-if="recipient"><b>Para:</b> {{ recipient.name }} · {{ recipient.identifier }}</p>
      <q-input
        v-model="title"
        label="Asunto"
        maxlength="100"
        counter
        outlined
        :disable="busy"
        :rules="[(v) => !!v?.trim() || 'Escribe un asunto']"
      />
      <q-input
        v-model="body"
        type="textarea"
        label="Mensaje"
        maxlength="1000"
        counter
        outlined
        :disable="busy"
        :rules="[(v) => !!v?.trim() || 'Escribe el mensaje']"
      />
      <p class="text-caption">
        No incluyas contraseñas ni datos bancarios. El aviso de la pantalla bloqueada será genérico;
        el texto completo se consulta al entrar a la app.
      </p>
      <q-banner v-if="error" class="bg-red-1">{{ error }}</q-banner>
      <q-btn
        type="submit"
        color="primary"
        icon="send"
        label="Revisar y enviar"
        :disable="!recipient"
        :loading="busy"
      />
    </q-form>
  </q-page>
</template>
<script setup lang="ts">
import { ref, watch, onUnmounted, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth.store';
import Swal from 'sweetalert2';
import { api } from '../services/http';
interface Recipient {
  id: string;
  name: string;
  identifier: string;
  branch: string | null;
}
const type = ref<'client' | 'staff'>('client'),
  title = ref(''),
  body = ref(''),
  recipient = ref<Recipient | null>(null),
  options = ref<Recipient[]>([]),
  busy = ref(false),
  searching = ref(false),
  error = ref('');
interface Branch {
  id: string;
  name: string;
}
const auth = useAuthStore();
const branch = ref<Branch | null>(null),
  branches = ref<Branch[]>([]),
  branchOptions = ref<Branch[]>([]),
  loadingBranches = ref(false),
  branchError = ref(false);
async function loadBranches() {
  loadingBranches.value = true;
  branchError.value = false;
  try {
    const { data } = await api.get<{ data: Branch[] }>('/messaging/branches');
    branches.value = data.data;
    branchOptions.value = data.data;
    branch.value =
      data.data.find((b) => b.id === auth.user?.branch_id) ??
      (data.data.length === 1 ? data.data[0]! : null);
    error.value = '';
  } catch {
    branchError.value = true;
    error.value = 'No fue posible cargar las sucursales. Reintenta la consulta.';
  } finally {
    loadingBranches.value = false;
  }
}
function filterBranches(value: string, update: (fn: () => void) => void) {
  update(() => {
    branchOptions.value = branches.value.filter((b) =>
      b.name.toLocaleLowerCase().includes(value.trim().toLocaleLowerCase()),
    );
  });
}
onMounted(loadBranches);
let version = 0,
  requestId = crypto.randomUUID();
watch([type, title, body, recipient], () => {
  requestId = crypto.randomUUID();
});
function cancelSearch() {
  version++;
  searching.value = false;
}
watch([type, branch], () => {
  recipient.value = null;
  options.value = [];
  cancelSearch();
});
async function filterRecipients(
  value: string,
  update: (fn: () => void) => void,
  abort: () => void,
) {
  const current = ++version;
  recipient.value = null;
  options.value = [];
  if (!value.trim() || (type.value === 'client' && !branch.value)) {
    searching.value = false;
    abort();
    return;
  }
  searching.value = true;
  error.value = '';
  try {
    const { data } = await api.get<{ data: Recipient[] }>('/messaging/recipients', {
      params: {
        type: type.value,
        search: value.trim(),
        match: 'prefix',
        ...(branch.value ? { branch_id: branch.value.id } : {}),
      },
    });
    if (current === version)
      update(() => {
        options.value = data.data;
      });
  } catch {
    if (current === version) {
      error.value = 'No fue posible buscar destinatarios. Revisa conexión y permisos.';
      abort();
    }
  } finally {
    if (current === version) searching.value = false;
  }
}
onUnmounted(() => version++);
async function send() {
  if (!recipient.value || busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    const answer = await Swal.fire({
      title: 'Confirmar envío',
      text: `Para ${recipient.value.name} (${recipient.value.identifier})\n${title.value}\n\n${body.value}`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Enviar mensaje',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#7e1838',
    });
    if (!answer.isConfirmed) return;
    await api.post('/messaging/send', {
      id: requestId,
      type: type.value,
      recipient_id: recipient.value.id,
      title: title.value,
      body: body.value,
    });
    title.value = '';
    body.value = '';
    await Swal.fire({
      icon: 'success',
      title: 'Mensaje guardado',
      text: 'Está en la bandeja del destinatario. Esto no confirma que haya recibido o leído el aviso push.',
      confirmButtonColor: '#7e1838',
    });
  } catch {
    error.value =
      'No se confirmó el envío. Puedes reintentar sin modificar el mensaje; no se duplicará si ya fue guardado.';
  } finally {
    busy.value = false;
  }
}
</script>
<style scoped>
.compose {
  max-width: 850px;
  margin: auto;
}
</style>
