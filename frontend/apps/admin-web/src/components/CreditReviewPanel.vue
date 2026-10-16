<template>
  <q-card flat bordered class="intake-card q-mt-lg">
    <q-card-section>
      <div class="row items-center justify-between q-gutter-sm">
        <div>
          <p class="eyebrow">CONTROL DEL EXPEDIENTE</p>
          <h2>Revisión de Gestión</h2>
        </div>
        <q-badge color="primary">{{ review ? reviewLabels[review.status] : 'Cargando' }}</q-badge>
      </div>
      <p>
        Comprueba identidad, domicilio, referencias, visita, historial interno y documentos firmados
        de cada integrante.
      </p>
      <q-banner class="bg-grey-2" rounded
        >Las comprobaciones son declaradas por Gestión. Registra la evidencia consultada; esta
        pantalla no valida ante RENAPO ni sustituye los documentos originales.</q-banner
      >
      <q-banner v-if="error" class="bg-red-1 text-negative q-mt-md"
        >{{ error }} <q-btn flat no-caps label="Recargar revisión" :disable="busy" @click="reload"
      /></q-banner>
      <q-linear-progress v-if="loading" indeterminate class="q-mt-md" />
    </q-card-section>
    <template v-if="review">
      <q-card-section v-if="review.status === 'returned'">
        <q-banner rounded class="bg-orange-1"
          >Corrige y envía los expedientes observados desde Clientes. Después, el gerente puede
          reenviar esta solicitud. Los montos originales se conservan.</q-banner
        >
        <q-btn
          v-if="auth.can('credit.create')"
          flat
          color="primary"
          no-caps
          label="Abrir clientes"
          to="/clientes"
          class="q-mt-sm"
        />
      </q-card-section>
      <q-list bordered separator class="q-mx-md rounded-borders">
        <q-expansion-item
          v-for="member in members"
          :key="member.client_id"
          :label="member.name"
          :caption="`${member.folio ?? ''} · ${member.role_in_group} · ${verifiedFor(member.client_id)}/6 verificadas`"
          icon="person_outline"
        >
          <q-card-section>
            <q-expansion-item
              label="Consultar expediente capturado"
              icon="folder_open"
              class="bg-grey-1 q-mb-md"
            >
              <q-card-section v-if="fileFor(member.client_id)">
                <p>
                  Versión {{ fileFor(member.client_id)?.version }} ·
                  {{
                    fileFor(member.client_id)?.status === 'borrador' ? 'En corrección' : 'Enviado'
                  }}
                </p>
                <ReviewFileFields :fields="fileFor(member.client_id)!.fields" />
              </q-card-section>
              <p v-else class="q-pa-md text-negative">Expediente no disponible.</p>
            </q-expansion-item>
            <div
              v-for="check in checks.filter((row) => row.client_id === member.client_id)"
              :key="check.key"
              class="check-row"
            >
              <div>
                <strong>{{ reviewCheckLabels[check.key] }}</strong>
                <q-option-group
                  v-model="check.status"
                  :options="checkOptions"
                  :disable="!editable || busy"
                  color="primary"
                  inline
                  class="q-mt-xs"
                />
              </div>
              <q-input
                v-model="check.notes"
                outlined
                autogrow
                :readonly="!editable || busy"
                label="Evidencia consultada u observación"
                maxlength="2000"
                :hint="
                  check.status === 'pending'
                    ? 'Opcional mientras está pendiente'
                    : 'Describe qué verificaste o qué debe corregirse'
                "
              />
            </div>
          </q-card-section>
        </q-expansion-item>
      </q-list>
      <q-card-section v-if="editable || canResubmit">
        <q-input
          v-model="notes"
          outlined
          autogrow
          label="Observaciones generales / respuesta del gerente"
          maxlength="4000"
          :readonly="busy"
          hint="Obligatorio para devolver, reenviar o enviar a autorización."
        />
        <div class="review-actions q-mt-lg">
          <template v-if="editable">
            <q-btn
              outline
              color="primary"
              no-caps
              label="Guardar avance"
              :loading="busy"
              @click="submit('save')"
            />
            <q-btn
              outline
              color="negative"
              no-caps
              label="Devolver con observaciones"
              :disable="busy"
              @click="submit('return')"
            />
            <q-btn
              color="primary"
              no-caps
              label="Enviar a autorización"
              :disable="busy || !allVerified"
              @click="submit('complete')"
            />
          </template>
          <q-btn
            v-if="canResubmit"
            color="primary"
            no-caps
            label="Reenviar a Gestión"
            :loading="busy"
            @click="submit('resubmit')"
          />
        </div>
        <p v-if="dirty" class="text-caption text-primary">Hay cambios sin guardar.</p>
      </q-card-section>
      <q-card-section v-if="review.status === 'ready_for_authorization'">
        <q-banner rounded class="bg-green-1"
          >Gestión completó la revisión. Consulta la sección de autorización para conocer la
          decisión comercial.</q-banner
        >
      </q-card-section>
      <q-card-section>
        <h2>Historial de revisión</h2>
        <p v-if="!review.history.length" class="text-grey-7">
          La revisión todavía no tiene movimientos.
        </p>
        <q-list separator>
          <q-expansion-item
            v-for="event in review.history"
            :key="event.id"
            icon="history"
            :label="`${eventLabels[event.action]} · ${event.actor_name}`"
            :caption="`Versión ${event.version} · ${event.created_at}`"
          >
            <q-card-section
              ><p class="preserve-lines">
                {{ event.notes || 'Avance guardado sin nota general.' }}
              </p>
              <q-expansion-item
                v-for="file in event.files"
                :key="file.id"
                :label="memberName(file.client_id)"
                :caption="`Expediente conservado · versión ${file.version}`"
              >
                <q-card-section>
                  <p
                    v-for="check in event.checks.filter((row) => row.client_id === file.client_id)"
                    :key="check.key"
                    class="preserve-lines"
                  >
                    <strong
                      >{{ reviewCheckLabels[check.key] }} ·
                      {{ checkStatusLabels[check.status] }}:</strong
                    >
                    {{ check.notes || 'Sin nota' }}
                  </p>
                  <ReviewFileFields :fields="file.fields" />
                </q-card-section>
              </q-expansion-item>
            </q-card-section>
          </q-expansion-item>
        </q-list>
      </q-card-section>
    </template>
  </q-card>
</template>
<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import {
  creditService,
  reviewLabels,
  reviewCheckLabels,
  type CreditApplication,
  type CreditReview,
  type ReviewCheck,
  type ReviewAction,
} from '../services/credit.service';
import { administrationError } from '../services/administration.service';
import { alerts } from '../services/alerts.service';
import { useAuthStore } from '../stores/auth.store';
import ReviewFileFields from './ReviewFileFields.vue';
const props = defineProps<{ loan: CreditApplication }>();
const emit = defineEmits<{ updated: [status: CreditReview['status']] }>();
const auth = useAuthStore();
const review = ref<CreditReview | null>(null),
  checks = ref<ReviewCheck[]>([]),
  notes = ref(''),
  loading = ref(false),
  busy = ref(false),
  error = ref(''),
  saved = ref('');
const members = computed(() => props.loan.request_snapshot?.members ?? []);
const editable = computed(
  () =>
    !loading.value &&
    !error.value &&
    auth.can('credit.review') &&
    props.loan.status === 'pendiente' &&
    ['pending', 'in_review'].includes(review.value?.status ?? ''),
);
const canResubmit = computed(
  () =>
    !loading.value &&
    !error.value &&
    auth.can('credit.create') &&
    props.loan.status === 'pendiente' &&
    review.value?.status === 'returned',
);
const state = () => JSON.stringify({ checks: checks.value, notes: notes.value });
const dirty = computed(() => !!saved.value && state() !== saved.value);
const allVerified = computed(
  () =>
    checks.value.length > 0 &&
    checks.value.every((check) => check.status === 'verified' && check.notes?.trim()),
);
const checkStatusLabels = {
  pending: 'Pendiente',
  verified: 'Verificada',
  observed: 'Con observaciones',
};
const checkOptions = Object.entries(checkStatusLabels).map(([value, label]) => ({ value, label }));
const eventLabels = {
  save: 'Avance de revisión',
  return: 'Devuelta para corrección',
  complete: 'Enviada a autorización',
  resubmit: 'Reenviada a Gestión',
};
const fileFor = (id: string) => review.value?.files.find((file) => file.client_id === id);
const memberName = (id: string) =>
  members.value.find((member) => member.client_id === id)?.name ?? id;
const verifiedFor = (id: string) =>
  checks.value.filter((check) => check.client_id === id && check.status === 'verified').length;
let disposed = false;
async function load() {
  loading.value = true;
  try {
    const result = await creditService.review(props.loan.id);
    if (disposed) return;
    review.value = result;
    checks.value = members.value.flatMap((member) =>
      Object.keys(reviewCheckLabels).map((key) => ({
        ...(result.checks.find(
          (check) => check.client_id === member.client_id && check.key === key,
        ) ?? { client_id: member.client_id, key, status: 'pending' as const, notes: '' }),
      })),
    );
    notes.value = '';
    saved.value = state();
    error.value = '';
    emit('updated', result.status);
  } catch (e) {
    if (!disposed) error.value = administrationError(e);
  } finally {
    if (!disposed) loading.value = false;
  }
}
async function reload() {
  if (
    dirty.value &&
    !(await alerts.confirm('Recargar revisión', 'Se descartarán los cambios sin guardar.'))
  )
    return;
  await load();
}
async function submit(action: ReviewAction) {
  if (busy.value || !review.value) return;
  if (action !== 'save' && !notes.value.trim())
    return alerts.error('Agrega las observaciones generales o tu respuesta.');
  if (
    action !== 'resubmit' &&
    checks.value.some((check) => check.status !== 'pending' && !check.notes?.trim())
  )
    return alerts.error('Describe la evidencia o la observación de cada comprobación marcada.');
  if (action === 'return' && !checks.value.some((check) => check.status === 'observed'))
    return alerts.error('Marca al menos una comprobación con observaciones.');
  busy.value = true;
  try {
    if (
      !(await alerts.confirm(
        eventLabels[action],
        action === 'return'
          ? 'Se reabrirán los expedientes observados para que se corrijan. Se conservará esta revisión.'
          : action === 'complete'
            ? 'Se cerrará la revisión de Gestión y quedará pendiente de autorización comercial.'
            : 'Se guardará este movimiento en el historial de la solicitud.',
      ))
    )
      return;
    await creditService.saveReview(props.loan.id, {
      version: review.value.version,
      action,
      notes: notes.value.trim(),
      checks: action === 'resubmit' ? [] : checks.value,
    });
    saved.value = state();
    await load();
    await alerts.success(eventLabels[action]);
  } catch (e) {
    await alerts.error(administrationError(e));
  } finally {
    busy.value = false;
  }
}
const beforeUnload = (event: BeforeUnloadEvent) => {
  if (dirty.value || busy.value) {
    event.preventDefault();
    event.returnValue = '';
  }
};
onBeforeRouteLeave(
  async () =>
    !busy.value &&
    (!dirty.value ||
      (await alerts.confirm(
        'Cambios sin guardar',
        '¿Deseas salir y descartar los cambios de la revisión?',
        'Salir',
      ))),
);
onMounted(() => {
  window.addEventListener('beforeunload', beforeUnload);
  void load();
});
onUnmounted(() => {
  disposed = true;
  window.removeEventListener('beforeunload', beforeUnload);
});
</script>
<style scoped>
.check-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  border-bottom: 1px solid #e5e7eb;
  padding: 20px 0;
}
.review-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.preserve-lines {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
@media (max-width: 750px) {
  .check-row {
    grid-template-columns: 1fr;
    gap: 12px;
  }
}
</style>
