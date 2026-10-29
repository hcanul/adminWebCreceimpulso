<template>
  <q-page class="intake-page"
    ><div class="intake-heading">
      <div>
        <p class="eyebrow">CRÉDITO GRUPAL · 16 SEMANAS</p>
        <h1>
          {{
            isCollection
              ? 'Cobros en caja'
              : isDisbursement
                ? 'Desembolsos y calendario'
                : isAuthorization
                  ? 'Autorización comercial'
                  : isReview
                    ? 'Bandeja de Gestión'
                    : 'Solicitudes de crédito'
          }}
        </h1>
        <p>Montos por integrante y una tasa común para todo el grupo.</p>
      </div>
      <q-btn
        v-if="auth.can('credit.create')"
        color="primary"
        icon="add"
        no-caps
        label="Nueva solicitud"
        to="/solicitudes-credito/nueva"
      />
    </div>
    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-md">{{ error }}</q-banner
    ><q-card flat bordered class="intake-card"
      ><q-card-section
        ><FilterSelect
          emit-value
          map-options
          v-model="selectedStatus"
          outlined
          :label="
            isDisbursement
              ? 'Estado del crédito'
              : isAuthorization
                ? 'Estado de autorización'
                : 'Estado de revisión'
          "
          :options="statusOptions"
          class="q-mb-md"
          @update:model-value="searchRequests" /><q-form @submit="searchRequests"
          ><q-input v-model="search" outlined label="Buscar por folio o grupo" maxlength="100"
            ><template #append
              ><q-btn
                flat
                round
                icon="search"
                type="submit"
                aria-label="Buscar solicitudes"
                :disable="loading" /></template></q-input></q-form></q-card-section
      ><q-linear-progress v-if="loading" indeterminate /><q-list separator
        ><q-item
          v-for="request in store.requests"
          :key="request.id"
          clickable
          :to="`${isCollection ? '/cobros' : '/solicitudes-credito'}/${request.id}`"
          class="q-py-md"
          ><q-item-section
            ><q-item-label class="text-weight-bold"
              >{{ request.folio }} · {{ request.team?.name }}</q-item-label
            ><q-item-label caption
              >{{ request.branch?.name }} · Solicitado
              {{ money(request.total_requested) }}</q-item-label
            ><q-item-label caption
              >Aprobado: {{ money(request.total_approved) }}</q-item-label
            ></q-item-section
          ><q-item-section side
            ><q-badge color="primary">{{
              request.status === 'pendiente'
                ? request.review_status === 'ready_for_authorization'
                  ? authorizationLabels[request.authorization_status ?? 'awaiting_proposal']
                  : reviewLabels[request.review_status ?? 'pending']
                : request.status
            }}</q-badge></q-item-section
          ></q-item
        ></q-list
      ><q-card-section
        v-if="!loading && !store.requests.length"
        class="text-center q-pa-xl text-grey-7"
        >Aún no hay solicitudes que coincidan. Primero prepara el grupo y sus
        expedientes.</q-card-section
      ><q-card-actions align="center"
        ><q-pagination
          v-model="page"
          :max="store.pages"
          :max-pages="5"
          :disable="loading"
          @update:model-value="load" /></q-card-actions></q-card
  ></q-page>
</template>
<script setup lang="ts">
import { useRoute } from 'vue-router';
import FilterSelect from '../components/FilterSelect.vue';
import { authorizationLabels } from '../services/authorization.service';
import { reviewLabels } from '../services/credit.service';
import { onMounted, onUnmounted, ref } from 'vue';
import { useStaffAuth as useAuthStore } from '../stores/auth';
import { useCreditStore } from '../stores/credit.store';
import { administrationError } from '../services/administration.service';
import { money } from '../domain/credit-calculation';
const isReview = useRoute().path === '/gestion';
const isAuthorization = useRoute().path === '/autorizaciones';
const isCollection = useRoute().path === '/cobros';
const isDisbursement = useRoute().path === '/desembolsos' || isCollection;
const selectedStatus = ref(
  isDisbursement
    ? isCollection
      ? 'entregado'
      : 'aprobado'
    : isAuthorization
      ? useAuthStore().can('credit.authorize')
        ? 'pending_authorization'
        : 'awaiting_proposal'
      : isReview
        ? 'pending'
        : '',
);
const statusOptions = [
  ...(!isAuthorization && !isDisbursement ? [{ value: '', label: 'Todos los estados' }] : []),
  ...Object.entries(
    isDisbursement
      ? {
          ...(!isCollection ? { aprobado: 'Autorizado · pendiente de entrega' } : {}),
          entregado: 'Entregado · calendario registrado',
          pagado: 'Liquidado',
        }
      : isAuthorization
        ? authorizationLabels
        : reviewLabels,
  ).map(([value, label]) => ({
    value,
    label,
  })),
];
const initialStatus = useRoute().query.status;
if (
  typeof initialStatus === 'string' &&
  statusOptions.some((option) => option.value === initialStatus)
)
  selectedStatus.value = initialStatus;
const auth = useAuthStore(),
  store = useCreditStore(),
  search = ref(''),
  page = ref(1),
  loading = ref(false),
  error = ref('');
async function load() {
  loading.value = true;
  error.value = '';
  try {
    await store.load(
      search.value,
      page.value,
      isAuthorization || isDisbursement ? '' : selectedStatus.value,
      isAuthorization ? selectedStatus.value : '',
      isDisbursement ? selectedStatus.value : '',
    );
  } catch (e) {
    error.value = administrationError(e);
  } finally {
    loading.value = false;
  }
}
async function searchRequests() {
  page.value = 1;
  await load();
}
onMounted(load);
onUnmounted(() => store.clear());
</script>
