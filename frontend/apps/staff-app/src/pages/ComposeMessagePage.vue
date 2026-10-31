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
          { label: 'Personal', value: 'staff' },
        ]"
        :disable="busy"
        no-caps
        toggle-color="primary"
      />
      <q-input
        v-model="search"
        label="Buscar por nombre, folio o correo"
        outlined
        debounce="350"
        :disable="busy"
        hint="Escribe al menos 3 caracteres; se muestran hasta 20 coincidencias."
      />
      <q-linear-progress v-if="searching" indeterminate />
      <q-list bordered separator v-if="options.length"
        ><q-item
          v-for="r in options"
          :key="r.id"
          clickable
          :active="recipient?.id === r.id"
          :disable="busy"
          @click="recipient = r"
          ><q-item-section
            ><q-item-label>{{ r.name }}</q-item-label
            ><q-item-label caption
              >{{ r.identifier }} · {{ r.branch || 'Acceso general' }}</q-item-label
            ></q-item-section
          ><q-item-section side
            ><q-icon
              v-if="recipient?.id === r.id"
              name="check_circle"
              color="primary" /></q-item-section></q-item
      ></q-list>
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
import { ref, watch, onUnmounted } from 'vue';
import Swal from 'sweetalert2';
import { api } from '../services/http';
interface Recipient {
  id: string;
  name: string;
  identifier: string;
  branch: string | null;
}
const type = ref<'client' | 'staff'>('client'),
  search = ref(''),
  title = ref(''),
  body = ref(''),
  recipient = ref<Recipient | null>(null),
  options = ref<Recipient[]>([]),
  busy = ref(false),
  searching = ref(false),
  error = ref('');
let version = 0,
  requestId = crypto.randomUUID();
watch([type, title, body, recipient], () => {
  requestId = crypto.randomUUID();
});
watch(type, () => {
  search.value = '';
  recipient.value = null;
  options.value = [];
  version++;
});
watch(search, async (value) => {
  const current = ++version;
  recipient.value = null;
  options.value = [];
  if (value.trim().length < 3) {
    searching.value = false;
    return;
  }
  searching.value = true;
  error.value = '';
  try {
    const { data } = await api.get<{ data: Recipient[] }>('/messaging/recipients', {
      params: { type: type.value, search: value.trim() },
    });
    if (current === version) options.value = data.data;
  } catch {
    if (current === version)
      error.value = 'No fue posible buscar destinatarios. Revisa conexión y permisos.';
  } finally {
    if (current === version) searching.value = false;
  }
});
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
