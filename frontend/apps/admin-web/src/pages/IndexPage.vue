<template>
  <q-page class="dashboard-page">
    <section class="dashboard-heading">
      <div>
        <p class="eyebrow">CADA GRUPO, UNA NUEVA OPORTUNIDAD</p>
        <h1>Hola, {{ firstName }}.</h1>
        <p class="subtitle">Tu operación, organizada desde el primer paso.</p>
      </div>
      <div class="today">
        <q-icon name="today" size="20px" /><span>{{ today }}</span
        ><q-btn
          flat
          round
          icon="refresh"
          aria-label="Actualizar indicadores"
          :loading="loading"
          @click="load"
        />
      </div>
    </section>
    <section class="welcome-card">
      <div class="welcome-content">
        <span class="welcome-label">CRECE IMPULSO · OFICINA DIGITAL</span>
        <h2>El próximo logro<br />comienza con un grupo.</h2>
        <p>
          Crea el grupo, incorpora a sus integrantes y completa sus expedientes antes de solicitar
          el crédito.
        </p>
        <q-btn
          unelevated
          color="white"
          text-color="primary"
          no-caps
          label="Conocer el recorrido"
          icon-right="arrow_forward"
          @click="showWorkflow"
        />
      </div>
      <div class="welcome-art" aria-hidden="true">
        <div class="orbit orbit-outer"></div>
        <div class="orbit orbit-inner"></div>
        <q-icon name="diversity_3" size="90px" /><span>Personas que crecen juntas</span>
      </div>
    </section>
    <q-banner v-if="error" rounded class="bg-red-1 text-negative q-mt-lg" role="alert"
      >{{ error
      }}<template #action
        ><q-btn flat no-caps label="Reintentar" :disable="loading" @click="load" /></template
    ></q-banner>
    <section :aria-busy="loading" aria-label="Indicadores operativos" class="metrics-grid">
      <article v-for="metric in metrics" :key="metric.title" class="metric-card">
        <div class="metric-top">
          <span>{{ metric.title }}</span
          ><q-icon :name="metric.icon" size="23px" />
        </div>
        <q-skeleton v-if="loading && !data" type="text" height="65px" /><strong v-else>{{
          metric.value
        }}</strong>
        <div class="metric-note">{{ metric.note }}</div>
      </article>
    </section>
    <p class="data-note">
      <q-icon name="info_outline" size="17px" /><span v-if="data"
        >{{ data.scope }} · Datos según tus permisos. Actualizado {{ updatedAt }} (hora de
        Cancún).</span
      ><span v-else>{{
        loading ? 'Consultando los registros…' : 'No fue posible obtener los indicadores.'
      }}</span>
    </p>
    <section v-if="data?.portfolio" class="panel q-mb-lg" aria-label="Resumen de cartera">
      <div class="panel-heading">
        <div>
          <p class="eyebrow">SALDOS Y VENCIMIENTOS</p>
          <h2>Cartera a la fecha</h2>
        </div>
        <q-btn flat no-caps color="primary" label="Ver cartera" to="/cartera" />
      </div>
      <div class="metrics-grid">
        <article v-for="metric in portfolioMetrics" :key="metric.title" class="metric-card">
          <div class="metric-top">{{ metric.title }}</div>
          <strong>{{ metric.value }}</strong>
          <div class="metric-note">{{ metric.note }}</div>
        </article>
      </div>
      <p class="panel-description">
        La mora pendiente ya está incluida en el saldo total, el exigible y el atraso. Los créditos
        anteriores sin política autorizada no incorporan cargos simulados.
      </p>
    </section>
    <div class="dashboard-grid">
      <section class="panel" id="workflow" ref="workflowElement" tabindex="-1">
        <div class="panel-heading">
          <div>
            <p class="eyebrow">DE LA CAPTURA AL DESEMBOLSO</p>
            <h2>Así comienza un crédito</h2>
          </div>
          <q-badge outline color="primary">Paso a paso</q-badge>
        </div>
        <p class="panel-description">Selecciona una etapa para conocer qué se necesita.</p>
        <div class="workflow-tabs" role="group" aria-label="Etapas del crédito">
          <button
            v-for="(step, index) in steps"
            :key="step.title"
            class="workflow-step"
            :class="{ selected: selectedStep === index }"
            :aria-pressed="selectedStep === index"
            @click="selectedStep = index"
          >
            <span class="step-number">{{ String(index + 1).padStart(2, '0') }}</span
            ><span>{{ step.title }}</span
            ><q-icon name="chevron_right" size="18px" />
          </button>
        </div>
        <div class="step-detail" aria-live="polite">
          <q-icon :name="activeStep.icon" size="28px" color="primary" />
          <div>
            <h3>{{ activeStep.title }}</h3>
            <p>{{ activeStep.description }}</p>
            <span>{{ activeStep.owner }}</span>
          </div>
        </div>
      </section>
      <section class="panel activity-panel">
        <div class="panel-heading">
          <div>
            <p class="eyebrow">ATENCIÓN Y SEGUIMIENTO</p>
            <h2>Mi bandeja de trabajo</h2>
          </div>
          <q-icon name="inbox" color="grey-6" size="24px" />
        </div>
        <q-skeleton v-if="loading && !data" height="200px" />
        <q-list v-else-if="data?.inbox.length" separator class="q-mt-md">
          <q-item v-for="item in data.inbox" :key="item.to" clickable :to="item.to" class="q-py-md"
            ><q-item-section>{{ item.label }}</q-item-section
            ><q-item-section side
              ><q-badge color="primary">{{ item.count }}</q-badge></q-item-section
            ><q-item-section side><q-icon name="chevron_right" /></q-item-section
          ></q-item>
        </q-list>
        <div v-else class="empty-inbox">
          <div class="inbox-icon">
            <q-icon :name="data ? 'assignment_turned_in' : 'cloud_off'" size="38px" />
          </div>
          <h3>{{ data ? 'Sin pendientes en estas etapas' : 'Bandeja no disponible' }}</h3>
          <p>
            {{
              data
                ? 'No hay revisiones, correcciones, autorizaciones, entregas ni cortes de hoy pendientes que correspondan a tus permisos.'
                : 'Actualiza para consultar tus pendientes.'
            }}
          </p>
        </div>
      </section>
    </div>
    <section v-if="canCapture" class="next-panel">
      <div class="next-icon"><q-icon name="groups" size="30px" /></div>
      <div>
        <p class="eyebrow">CAPTURA DISPONIBLE</p>
        <h2>Grupos solidarios</h2>
        <p>
          Nombre del grupo, sucursal y asesor. Después, integrantes, comité y revisión de
          elegibilidad.
        </p>
      </div>
      <q-btn v-if="canCapture" outline color="primary" no-caps label="Abrir grupos" to="/grupos" />
    </section>
    <footer class="dashboard-footer">
      Crece Impulso <span>Nuestro impulso, son tus metas.</span>
    </footer>
  </q-page>
</template>
<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useDashboardStore } from '../stores/dashboard.store';
import { administrationError } from '../services/administration.service';
import { money } from '../domain/credit-calculation';
import { useAuthStore } from '../stores/auth.store';
const auth = useAuthStore();
const canCapture = computed(() => auth.can('intake.access'));
const firstName = computed(() => auth.user?.name.trim().split(/\s+/)[0] || 'bienvenido');
const store = useDashboardStore(),
  { data } = storeToRefs(store);
const loading = ref(false),
  error = ref('');
const today = computed(() =>
  new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'America/Cancun',
  }).format(data.value ? new Date(data.value.as_of) : new Date()),
);
const updatedAt = computed(() =>
  data.value
    ? new Intl.DateTimeFormat('es-MX', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'America/Cancun',
      }).format(new Date(data.value.as_of))
    : '',
);
const count = (value: number | null | undefined) =>
  !data.value ? '—' : value == null ? 'Sin acceso' : new Intl.NumberFormat('es-MX').format(value);
const metrics = computed(() => [
  {
    title: 'Grupos solidarios',
    icon: 'diversity_3',
    value: count(data.value?.groups),
    note: 'Grupos activos a tu alcance',
  },
  {
    title: 'Solicitudes en revisión',
    icon: 'fact_check',
    value: count(data.value?.in_review),
    note: 'Pendientes de Gestión o en revisión',
  },
  {
    title: 'Créditos activos',
    icon: 'account_balance_wallet',
    value: count(data.value?.active_credits),
    note: 'Entregados y todavía sin liquidar',
  },
  {
    title: 'Cobros del día',
    icon: 'payments',
    value: !data.value
      ? '—'
      : data.value.payments
        ? money(data.value.payments.total)
        : 'Sin acceso',
    note: data.value?.payments
      ? `${data.value.payments.scope} · ${data.value.payments.count} pagos. Efectivo: ${money(data.value.payments.cash)} · Depósitos: ${money(data.value.payments.bank)}`
      : 'Por fecha de recepción, no de depósito',
  },
]);
const portfolioMetrics = computed(() => {
  const portfolio = data.value?.portfolio;
  return portfolio
    ? [
        {
          title: 'Saldo total',
          value: money(portfolio.balance),
          note: `Cuotas: ${money(portfolio.installment_balance)} + mora pendiente`,
        },
        {
          title: 'Exigible a la fecha',
          value: money(portfolio.due),
          note: 'Cuotas vencidas, de hoy y mora pendiente',
        },
        {
          title: 'Mora pendiente',
          value: money(portfolio.late_fee_balance),
          note: 'Cargos pendientes de pago, sin capitalización',
        },
        {
          title: 'Importe en atraso',
          value: money(portfolio.overdue),
          note: `${portfolio.overdue_credits} créditos con cuotas o mora pendientes en atraso`,
        },
      ]
    : [];
});
let disposed = false;
async function load() {
  if (loading.value) return;
  loading.value = true;
  error.value = '';
  try {
    await store.load();
  } catch (e) {
    if (!disposed) error.value = administrationError(e);
  } finally {
    if (!disposed) loading.value = false;
  }
}
onMounted(() => {
  store.clear();
  void load();
});
onUnmounted(() => {
  disposed = true;
  store.clear();
});
const steps = [
  {
    title: 'Crear el grupo',
    icon: 'group_add',
    description:
      'Registra nombre, sucursal y asesor responsable. El grupo existe antes de incorporar a sus integrantes.',
    owner: 'Inicio del expediente grupal',
  },
  {
    title: 'Integrar y comprobar',
    icon: 'person_search',
    description:
      'Vincula clientes por CURP, completa sus expedientes y define Presidenta, Tesorera e integrantes. Revisa su elegibilidad antes de solicitar crédito.',
    owner: 'Captura y comprobaciones de identidad',
  },
  {
    title: 'Revisar la solicitud',
    icon: 'assignment',
    description:
      'Gestión revisa identidad, domicilio, referencias, visita, historial y documentación. Las observaciones regresan a captura para su corrección.',
    owner: 'Departamento de Gestión',
  },
  {
    title: 'Autorizar y entregar',
    icon: 'verified',
    description:
      'Coordinación Comercial autoriza las condiciones. Después, la sucursal registra el desembolso; ambas operaciones quedan separadas.',
    owner: 'Coordinación Comercial → Sucursal',
  },
];
const selectedStep = ref(0);
const activeStep = computed(() => steps[selectedStep.value] ?? steps[0]!);
const workflowElement = ref<HTMLElement | null>(null);
function showWorkflow() {
  workflowElement.value?.scrollIntoView({ behavior: 'auto', block: 'center' });
  workflowElement.value?.focus({ preventScroll: true });
}
</script>
<style scoped lang="scss">
.dashboard-page {
  max-width: 1550px;
  margin: auto;
  padding: 34px 38px 22px;
}
.dashboard-heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  margin-bottom: 26px;
}
.eyebrow {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1.7px;
  color: #811335;
  margin: 0 0 9px;
}
h1 {
  font-size: 32px;
  line-height: 1.2;
  letter-spacing: -1px;
  font-weight: 700;
  margin: 0 0 10px;
}
.subtitle {
  font-size: 14px;
  color: #70788a;
  margin: 0;
}
.today {
  display: flex;
  gap: 9px;
  align-items: center;
  color: #737b8a;
  font-size: 12px;
}
.welcome-card {
  border-radius: 18px;
  background: linear-gradient(115deg, #67112f, #951c44);
  padding: 34px 40px;
  display: flex;
  justify-content: space-between;
  overflow: hidden;
  color: white;
  position: relative;
  min-height: 265px;
}
.welcome-content {
  position: relative;
  z-index: 1;
}
.welcome-label {
  font-size: 9px;
  font-weight: 600;
  letter-spacing: 2px;
  color: #edc3d3;
}
.welcome-card h2 {
  font-size: 32px;
  line-height: 1.18;
  font-weight: 650;
  letter-spacing: -0.5px;
  margin: 16px 0;
}
.welcome-card p {
  max-width: 470px;
  font-size: 13px;
  color: #f0dce4;
  line-height: 1.8;
  margin-bottom: 22px;
}
.welcome-card :deep(.q-btn) {
  border-radius: 7px;
  font-size: 12px;
  padding: 8px 16px;
}
.welcome-art {
  min-width: 250px;
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 20px;
  color: #f5e1e9;
}
.welcome-art span {
  font-size: 11px;
  letter-spacing: 0.8px;
}
.orbit {
  position: absolute;
  border: 1px solid #ffffff22;
  border-radius: 50%;
  pointer-events: none;
}
.orbit-outer {
  width: 350px;
  height: 350px;
}
.orbit-inner {
  width: 280px;
  height: 280px;
}
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 18px;
  margin-top: 25px;
}
.metric-card {
  background: white;
  border: 1px solid #e3e6ed;
  border-radius: 12px;
  padding: 21px;
}
.metric-top {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 12px;
  color: #677084;
  align-items: center;
}
.metric-top .q-icon {
  color: #97455f;
}
.metric-card strong {
  font-size: clamp(22px, 2.2vw, 34px);
  overflow-wrap: anywhere;
  display: block;
  margin: 14px 0 9px;
  font-weight: 650;
}
.metric-note {
  font-size: 11px;
  line-height: 1.7;
  color: #7c8492;
  display: flex;
  align-items: center;
  gap: 6px;
}
.status-dot {
  width: 5px;
  height: 5px;
  background: #a5abba;
  border-radius: 50%;
}
.data-note {
  font-size: 11px;
  color: #6e7686;
  display: flex;
  gap: 7px;
  align-items: center;
  line-height: 1.7;
  margin: 14px 0 25px;
}
.dashboard-grid {
  display: grid;
  grid-template-columns: 1.3fr 1fr;
  gap: 22px;
}
.panel {
  background: white;
  border: 1px solid #e3e6ed;
  border-radius: 14px;
  padding: 26px;
}
.panel-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.panel h2,
.next-panel h2 {
  font-size: 19px;
  font-weight: 650;
  line-height: 1.35;
  margin: 0;
}
.panel-description {
  font-size: 12px;
  color: #7b8290;
  line-height: 1.7;
}
.workflow-tabs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin: 22px 0;
}
.workflow-step {
  display: flex;
  align-items: center;
  text-align: left;
  gap: 10px;
  border: 1px solid #e4e7ee;
  border-radius: 9px;
  padding: 13px 10px;
  background: #fff;
  font: inherit;
  font-size: 11px;
  color: #586173;
  cursor: pointer;
}
.workflow-step.selected {
  border-color: #d1a8b7;
  background: #fbf2f5;
  color: #811335;
}
.workflow-step .q-icon {
  margin-left: auto;
}
.step-number {
  font-weight: 700;
  font-size: 12px;
}
.step-detail {
  display: flex;
  gap: 14px;
  padding: 19px;
  background: #f7f8fa;
  border-radius: 10px;
  min-height: 170px;
}
.step-detail h3 {
  font-size: 14px;
  font-weight: 650;
  margin: 0 0 8px;
  line-height: 1.5;
}
.step-detail p {
  font-size: 12px;
  color: #687285;
  line-height: 1.8;
  margin: 0 0 12px;
}
.step-detail span {
  font-size: 10px;
  color: #811335;
  font-weight: 600;
}
.empty-inbox {
  text-align: center;
  padding: 35px 15px 15px;
}
.inbox-icon {
  display: inline-flex;
  padding: 18px;
  border-radius: 50%;
  background: #f5edf1;
  color: #995b72;
}
.empty-inbox h3 {
  font-size: 16px;
  font-weight: 600;
  margin: 20px 0 10px;
}
.empty-inbox p {
  font-size: 12px;
  line-height: 1.9;
  color: #747d8d;
  max-width: 300px;
  margin: 0 auto 20px;
}
.next-panel {
  display: flex;
  gap: 20px;
  align-items: center;
  padding: 24px;
  margin-top: 22px;
  border: 1px solid #e0d7de;
  border-radius: 13px;
  background: #f2edf1;
}
.next-icon {
  color: #811335;
  background: white;
  border-radius: 12px;
  padding: 14px;
}
.next-panel p:not(.eyebrow) {
  font-size: 12px;
  color: #6b7485;
  line-height: 1.7;
  margin: 7px 0 0;
}
.next-panel .q-badge {
  margin-left: auto;
  white-space: nowrap;
}
.dashboard-footer {
  display: flex;
  justify-content: space-between;
  margin-top: 28px;
  font-size: 10px;
  color: #828997;
}
button:focus-visible,
#workflow:focus-visible {
  outline: 3px solid #bd8198;
  outline-offset: 3px;
}
@media (max-width: 1250px) {
  .welcome-art {
    min-width: 170px;
  }
  .metrics-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .dashboard-grid {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 650px) {
  .dashboard-page {
    padding: 23px 16px;
  }
  .dashboard-heading {
    align-items: flex-start;
    flex-direction: column;
    gap: 12px;
  }
  h1 {
    font-size: 28px;
  }
  .welcome-card {
    padding: 27px;
  }
  .welcome-card h2 {
    font-size: 27px;
  }
  .welcome-art {
    display: none;
  }
  .metrics-grid {
    gap: 10px;
  }
  .metric-card {
    padding: 16px;
  }
  .metric-top {
    font-size: 11px;
  }
  .panel {
    padding: 20px;
  }
  .workflow-tabs {
    grid-template-columns: 1fr;
  }
  .next-panel {
    flex-wrap: wrap;
    padding: 20px;
  }
  .next-panel .q-badge {
    margin-left: 0;
  }
  .next-icon {
    display: none;
  }
  .dashboard-footer {
    gap: 15px;
  }
  .eyebrow {
    font-size: 9px;
  }
}
</style>
