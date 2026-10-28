import { defineRouter } from '#q-app';
import { createRouter, createWebHashHistory } from 'vue-router';
import { useStaffAuth } from '../stores/auth';
export default defineRouter(({ store }) => {
  const router = createRouter({
    history: createWebHashHistory(import.meta.env.QUASAR_VUE_ROUTER_BASE),
    routes: [
      { path: '/sin-conexion', component: () => import('../pages/OfflineCorrectionsPage.vue') },
      { path: '/login', component: () => import('../pages/LoginPage.vue') },
      { path: '/configuracion', component: () => import('../pages/ServerSettingsPage.vue') },
      {
        path: '/',
        component: () => import('../layouts/StaffLayout.vue'),
        meta: { requiresAuth: true },
        children: [
          {
            path: 'clientes/expedientes',
            component: () => import('../pages/ClientFilesPage.vue'),
            meta: { intake: true },
          },
          {
            path: 'usuarios',
            component: () => import('../pages/UsersPage.vue'),
            meta: { superAdmin: true },
          },
          {
            path: 'roles',
            component: () => import('../pages/RolesPage.vue'),
            meta: { superAdmin: true },
          },
          { path: 'mi-acceso', component: () => import('../pages/MyAccessPage.vue') },
          {
            path: 'clientes/capturar',
            component: () => import('../pages/ApplicationCapturePage.vue'),
            meta: { intake: true },
          },
          { path: '', component: () => import('../pages/HomePage.vue') },
          {
            path: 'grupos',
            component: () => import('../pages/TeamsPage.vue'),
            meta: { intake: true },
          },
          {
            path: 'grupos/:id',
            component: () => import('../pages/TeamPage.vue'),
            meta: { intake: true },
          },
        ],
      },
      { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
  });
  router.beforeEach(async (to) => {
    if (!to.meta.requiresAuth) return true;
    const auth = useStaffAuth(store);
    try {
      await auth.restore();
    } catch {
      auth.clear();
      return '/login';
    }
    if (!auth.user) return '/login';
    if (to.meta.superAdmin && !auth.isSuperAdmin) return '/';
    if (to.meta.intake && !auth.canIntake) return '/';
    return true;
  });
  window.addEventListener('staff-session-expired', () => {
    if (router.currentRoute.value.meta.requiresAuth) void router.replace('/login');
  });
  return router;
});
