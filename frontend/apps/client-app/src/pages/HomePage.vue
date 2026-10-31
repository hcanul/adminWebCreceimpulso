<template>
  <main class="client-home">
    <header>
      <q-btn
        flat
        round
        icon="settings"
        aria-label="Configuración del dispositivo"
        to="/configuracion"
      />
      <span class="eyebrow">CRECE IMPULSO · MI CUENTA</span
      ><q-btn flat label="Cerrar sesión" icon="logout" :loading="busy" @click="logout" />
    </header>
    <section class="welcome-card">
      <q-icon name="verified_user" size="36px" />
      <h1>Hola, {{ auth.profile?.name }}</h1>
      <p>Tu acceso está activo y protegido.</p>
      <div class="profile-details">
        <span
          >Número de cliente<strong>{{ auth.profile?.folio }}</strong></span
        ><span
          >Sucursal<strong>{{ auth.profile?.branch }}</strong></span
        >
      </div>
    </section>
    <q-btn class="q-mt-md" color="primary" outline icon="mail" label="Mis mensajes" to="/mensajes" />
    <PaymentReminders />
    <q-banner v-if="loading" class="q-mt-lg">Cargando tu información…</q-banner>
    <q-banner v-else-if="loadError" class="q-mt-lg"
      >{{ loadError }} <q-btn flat label="Reintentar" @click="load"
    /></q-banner>
    <template v-else
      ><ActiveCredit v-if="statement && !statement.demo" :data="statement" /><AccountStatement
        v-if="statement"
        :data="statement"
      /><ClientPayments v-if="statement && !statement.demo" /><ClientDemo
        v-if="demo"
        :data="demo"
      />
      <q-card v-if="!statement && !demo" flat bordered class="q-pa-lg q-mt-lg"
        ><h2>No tienes un crédito activo para consultar</h2>
        <p>Consulta en tu sucursal si necesitas revisar un crédito anterior.</p></q-card
      ></template
    >
  </main>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import PaymentReminders from '../components/PaymentReminders.vue';
import ActiveCredit from '../components/ActiveCredit.vue';
import ClientPayments from '../components/ClientPayments.vue';
import AccountStatement from '../components/AccountStatement.vue';
import { loadStatement, type ClientStatement } from '../services/statement.service';
import ClientDemo from '../components/ClientDemo.vue';
import { loadClientDemo, type DemoData } from '../services/demo.service';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { useClientAuth } from '../stores/auth';
import { errorMessage } from '../services/http';
const auth = useClientAuth();
const router = useRouter();
const busy = ref(false);
const loading = ref(true);
const loadError = ref('');
const statement = ref<ClientStatement | null>(null);
const demo = ref<DemoData | null>(null);
async function load() {
  loading.value = true;
  loadError.value = '';
  try {
    statement.value = null;
    demo.value = null;
    const real = await loadStatement();
    const example = real ? null : await loadClientDemo();
    statement.value = real;
    demo.value = real ? null : example;
    if (!real && example) {
      const personal = example.schedule.map((r) => ({
        ...r,
        status:
          r.remaining === 0
            ? ('paid' as const)
            : r.due_on < example.as_of
              ? ('overdue' as const)
              : r.paid > 0
                ? ('partial' as const)
                : r.due_on === example.as_of
                  ? ('due_today' as const)
                  : ('upcoming' as const),
      }));
      // Explicit synthetic group: Maria and two fictitious members paying 800 each.
      const group = personal.map((r) => {
        const otherPaid = r.week <= 5 ? 160000 : r.week === 6 ? 40000 : 0;
        const amount = r.amount * 3,
          paid = r.paid + otherPaid;
        return {
          ...r,
          amount,
          paid,
          remaining: amount - paid,
          status:
            paid === amount
              ? ('paid' as const)
              : r.due_on < example.as_of
                ? ('overdue' as const)
                : paid > 0
                  ? ('partial' as const)
                  : r.due_on === example.as_of
                    ? ('due_today' as const)
                    : ('upcoming' as const),
        };
      });
      statement.value = {
        active: true,
        demo: true,
        as_of: example.as_of,
        folio: example.loan.folio,
        group: example.loan.group,
        personal,
        group_schedule: group,
      };
    }
  } catch (e) {
    loadError.value = errorMessage(e);
  } finally {
    loading.value = false;
  }
}
onMounted(load);
async function logout() {
  if (busy.value) return;
  busy.value = true;
  try {
    await auth.logout();
    await router.replace('/login');
  } catch (e) {
    await Swal.fire({
      icon: 'error',
      title: 'No pudimos cerrar la sesión',
      text: errorMessage(e),
      confirmButtonColor: '#7e1838',
    });
  } finally {
    busy.value = false;
  }
}
</script>
