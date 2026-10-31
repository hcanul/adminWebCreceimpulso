import { defineRouter } from '#q-app';
import {
  createMemoryHistory,
  createRouter,
  createWebHashHistory,
  createWebHistory,
} from 'vue-router';
import routes from './routes';
import { useAuthStore } from '../stores/auth.store';

export default defineRouter(({ store }) => {
  const createHistory = import.meta.env.QUASAR_SERVER
    ? createMemoryHistory
    : import.meta.env.QUASAR_VUE_ROUTER_MODE === 'history'
      ? createWebHistory
      : createWebHashHistory;
  const router = createRouter({
    routes,
    history: createHistory(import.meta.env.QUASAR_VUE_ROUTER_BASE),
    scrollBehavior: () => ({ left: 0, top: 0 }),
  });
  router.beforeEach(async (to) => {
    const auth = useAuthStore(store);
    await auth.restoreSession();
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      return { path: '/login', query: { redirect: to.fullPath } };
    }
    if (
      auth.isAuthenticated &&
      auth.user?.password_change_required &&
      to.path !== '/cambiar-contrasena'
    )
      return '/cambiar-contrasena';
    if (
      to.meta.clientAccess &&
      !auth.roles.some((role) => ['Super Admin', 'Gerente General', 'Gerente'].includes(role))
    )
      return '/';
    if (to.meta.messagesSend && !auth.can('messages.send')) return '/';
    if (to.meta.cash && !auth.can('cash.view')) return '/';
    if (to.meta.intake && !auth.can('intake.access')) return '/';
    if (to.meta.credit && !auth.can('credit.view')) return '/';
    if (to.meta.creditCreate && !auth.can('credit.create')) return '/';
    if (to.meta.creditAuthorization && !auth.can('credit.authorize') && !auth.can('credit.propose'))
      return '/';
    if (to.meta.creditCollect && !auth.can('credit.collect') && !auth.can('credit.reverse'))
      return '/';
    if (to.meta.creditDisburse && !auth.can('credit.disburse')) return '/';
    if (to.meta.creditReview && !auth.can('credit.review')) return '/';
    if (to.meta.creditRates && !auth.can('credit.rates')) return '/';
    if (to.meta.superAdmin && !auth.roles.includes('Super Admin')) return '/';
    if (to.meta.guestOnly && auth.isAuthenticated) return '/';
    return true;
  });
  return router;
});
