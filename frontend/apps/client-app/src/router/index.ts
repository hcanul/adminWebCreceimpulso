import { defineRouter } from '#q-app';
import { createRouter, createWebHashHistory } from 'vue-router';
import { useClientAuth } from '../stores/auth';
export default defineRouter(({ store }) => {
  const router = createRouter({
    history: createWebHashHistory(import.meta.env.QUASAR_VUE_ROUTER_BASE),
    routes: [
      { path: '/configuracion', component: () => import('../pages/ServerSettingsPage.vue') },
      { path: '/login', component: () => import('../pages/AccessPage.vue') },
      { path: '/activar', component: () => import('../pages/AccessPage.vue') },
      { path: '/cambiar-contrasena', component: () => import('../pages/ChangePasswordPage.vue'), meta: { requiresAuth: true } },
      { path: '/', component: () => import('../pages/HomePage.vue'), meta: { requiresAuth: true } },
      { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
  });
  router.beforeEach(async (to) => {
    const auth = useClientAuth(store);
    if (to.meta.requiresAuth) {
      try {
        await auth.restore();
      } catch {
        return '/login';
      }
      if (!auth.profile) return '/login';
      if (auth.profile.must_change_password && to.path !== '/cambiar-contrasena') return '/cambiar-contrasena';
    }
    return true;
  });
  window.addEventListener('client-session-expired', () => {
    if (router.currentRoute.value.meta.requiresAuth) void router.replace('/login');
  });
  return router;
});
