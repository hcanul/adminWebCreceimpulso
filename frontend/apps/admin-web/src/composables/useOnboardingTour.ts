import { nextTick, onBeforeUnmount, onMounted, watch, type Ref } from 'vue';
import { useRoute } from 'vue-router';
import { useQuasar } from 'quasar';
import type { Driver, DriveStep } from 'driver.js';
import { useAuthStore } from '../stores/auth.store';
import { api } from '../services/http';

const descriptions: Record<string, string> = {
  '/grupos': 'Primero crea el grupo solidario y asigna su promotor. Después incorpora a sus integrantes y define el comité.',
  '/clientes': 'Verifica la CURP antes de capturar o corregir un expediente. El monto solicitado se captura posteriormente en la solicitud de crédito.',
  '/solicitudes-credito': 'Con el grupo elegible, registra las cantidades de cada integrante y la tasa compartida. Lo solicitado se conserva separado de lo aprobado.',
  '/gestion': 'Revisa identidad, domicilio, referencias y documentación antes de continuar con la autorización.',
  '/autorizaciones': 'Consulta las condiciones, los montos aprobados y la fecha pactada del primer pago, de acuerdo con tus permisos.',
  '/desembolsos': 'Consulta la entrega del crédito, la retención de garantía y el calendario de 16 semanas.',
  '/cobros': 'Registra los pagos recibidos, incluidos los abonos parciales, y consulta sus comprobantes. Los reversos requieren autorización y motivo.',
  '/cartera': 'Consulta vencimientos, atrasos y estados de cuenta. Los filtros permiten localizar los créditos de tu alcance.',
  '/reportes': 'Genera los reportes disponibles para tu perfil y revisa los filtros antes de imprimir.',
  '/observaciones-cobranza': 'Registra el seguimiento y los compromisos de pago, conservando el historial de atención.',
  '/cobros-depositos': 'Distingue los movimientos en efectivo de los depósitos bancarios y conserva sus referencias.',
  '/cortes': 'Revisa los cobros y los totales separados de efectivo y depósitos antes de cerrar caja.',
  '/administracion/usuarios': 'Administra las cuentas y sus sucursales. Asigna únicamente los permisos necesarios para cada función.',
  '/administracion/roles': 'Configura los roles y permisos disponibles para el personal. Estos controles también se aplican en el servidor.',
};

export function useOnboardingTour(drawer: Ref<boolean>) {
  const auth = useAuthStore();
  const route = useRoute();
  const q = useQuasar();
  let active: Driver | undefined;
  let alive = true;
  let sequence = 0;
  let opening = false;
  let previousDrawer = false;
  const checked = new Set<string>();

  function stop() {
    sequence++;
    active?.destroy();
    active = undefined;
  }

  async function start(manual = false) {
    const token = auth.token;
    if (!token || !auth.user || auth.user.password_change_required || opening || active?.isActive()) return;
    if (!manual && checked.has(token)) return;
    if (!manual) checked.add(token);
    opening = true;
    const attempt = ++sequence;
    const path = route.fullPath;
    const stillCurrent = () => alive && attempt === sequence && auth.token === token && route.fullPath === path;
    try {
      // Load the library only when needed; never send a tour request before it loads.
      const { driver } = await import('driver.js');
      if (!stillCurrent()) return;
      if (!manual) {
        const { data } = await api.post<{ show: boolean }>('/onboarding/admin-web/start', {}, {
          headers: { Authorization: `Bearer ${token}` },
        });
        if (!data.show || !stillCurrent()) return;
      }
      previousDrawer = drawer.value;
      drawer.value = true;
      await nextTick();
      // Allow the responsive drawer to finish opening before measuring targets.
      await new Promise((resolve) => window.setTimeout(resolve, 320));
      if (!stillCurrent()) { drawer.value = previousDrawer; return; }
      const visible = (element: Element) => element.getClientRects().length > 0;
      const steps: DriveStep[] = [{ popover: {
        title: 'Bienvenido a Crece Impulso',
        description: 'Esta guía aparece en tus dos primeros accesos a Oficina Digital. Puedes cerrarla cuando quieras y volver a abrirla con el botón de ayuda. No modifica ningún registro.',
      } }];
      const menuButton = document.querySelector('[data-tour="menu"]');
      if (menuButton) steps.push({ element: menuButton, popover: {
        title: 'Tu espacio de trabajo', description: 'Abre o cierra el menú lateral. Solo verás las opciones que permite tu cuenta.',
      } });
      for (const element of document.querySelectorAll<HTMLElement>('[data-tour-module]')) {
        const path = element.dataset.tourModule || '';
        if (descriptions[path] && visible(element)) steps.push({ element, popover: {
          title: element.innerText.trim(), description: descriptions[path], side: 'right',
        } });
      }
      const session = document.querySelector('[data-tour="session"]');
      if (session) steps.push({ element: session, popover: {
        title: 'Tu cuenta y tu sesión', description: 'Aquí puedes cerrar sesión. Por seguridad, la sesión vence después de 30 minutos de inactividad.', side: 'bottom',
      } });
      steps.push({ element: '[data-tour="help"]', popover: {
        title: 'Vuelve a consultar la guía', description: 'Pulsa este botón cuando necesites repetir el recorrido. Las repeticiones manuales no cuentan como nuevos accesos.', side: 'bottom',
      } });
      active = driver({
        steps, showProgress: true, progressText: '{{current}} de {{total}}',
        nextBtnText: 'Siguiente', prevBtnText: 'Anterior', doneBtnText: 'Finalizar',
        allowClose: true, allowKeyboardControl: true, disableActiveInteraction: true,
        animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        overlayColor: '#262b38', overlayOpacity: 0.65, stageRadius: 10,
        popoverClass: 'crece-tour',
        onPopoverRender: (popover) => { popover.closeButton.setAttribute('aria-label', 'Cerrar recorrido'); },
        onDestroyed: () => { active = undefined; drawer.value = previousDrawer; },
      });
      active.drive();
    } catch {
      if (manual && stillCurrent()) q.notify({ type: 'warning', message: 'No fue posible abrir el recorrido. Inténtalo de nuevo.' });
      // A tour failure must not prevent access to the application.
    } finally { opening = false; }
  }

  onMounted(() => { void start(); });
  watch(() => auth.token, () => { stop(); void start(); });
  watch(() => route.fullPath, stop);
  onBeforeUnmount(() => { alive = false; stop(); });
  return { repeatTour: () => start(true) };
}
