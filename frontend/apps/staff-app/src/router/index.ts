import { defineRouter } from '#q-app';
import { createRouter, createWebHashHistory } from 'vue-router';
import { useStaffAuth } from '../stores/auth';
export default defineRouter(({ store }) => {
  const router = createRouter({
    history: createWebHashHistory(import.meta.env.QUASAR_VUE_ROUTER_BASE),
    routes: [
      {
        path: '/cambiar-contrasena',
        component: () => import('../pages/ChangePasswordPage.vue'),
        meta: { requiresAuth: true },
      },
      { path: '/sin-conexion', component: () => import('../pages/OfflineCorrectionsPage.vue') },
      { path: '/login', component: () => import('../pages/LoginPage.vue') },
      { path: '/configuracion', component: () => import('../pages/ServerSettingsPage.vue') },
      {
        path: '/',
        component: () => import('../layouts/StaffLayout.vue'),
        meta: { requiresAuth: true },
        children: [
          { path: 'mensajes', component: () => import('../pages/MessagesPage.vue') },
          {
            path: 'mensajes/enviar',
            component: () => import('../pages/ComposeMessagePage.vue'),
            meta: { messagesSend: true },
          },
          {
            path: 'solicitudes-credito',
            component: () => import('../pages/CreditApplicationsPage.vue'),
            meta: { credit: true },
          },
          {
            path: 'solicitudes-credito/nueva',
            component: () => import('../pages/CreditCapturePage.vue'),
            meta: { intake: true, creditCreate: true },
          },
          {
            path: 'solicitudes-credito/:id',
            component: () => import('../pages/CreditDetailPage.vue'),
            meta: { credit: true },
          },
          {
            path: 'gestion',
            component: () => import('../pages/CreditApplicationsPage.vue'),
            meta: { credit: true, creditReview: true },
          },
          {
            path: 'autorizaciones',
            component: () => import('../pages/CreditApplicationsPage.vue'),
            meta: { credit: true, creditAuthorization: true },
          },
          {
            path: 'desembolsos',
            component: () => import('../pages/CreditApplicationsPage.vue'),
            meta: { credit: true, creditDisburse: true },
          },
          {
            path: 'cartera',
            component: () => import('../pages/CreditPortfolioPage.vue'),
            meta: { credit: true },
          },
          {
            path: 'creditos/:id/estado-de-cuenta',
            component: () => import('../pages/CreditStatementPage.vue'),
            meta: { credit: true },
          },
          {
            path: 'creditos/:id/garantia',
            component: () => import('../pages/GuaranteePage.vue'),
            meta: { credit: true },
          },
          {
            path: 'cobros',
            component: () => import('../pages/CreditApplicationsPage.vue'),
            meta: { credit: true, creditCollect: true },
          },
          {
            path: 'cobros/:id',
            component: () => import('../pages/CreditCollectionPage.vue'),
            meta: { credit: true, creditCollect: true },
          },
          {
            path: 'comprobantes/:id',
            component: () => import('../pages/PaymentReceiptPage.vue'),
            meta: { credit: true, creditCollect: true },
          },
          {
            path: 'cortes',
            component: () => import('../pages/CashClosingsPage.vue'),
            meta: { cash: true },
          },
          {
            path: 'cortes/:id',
            component: () => import('../pages/CashClosingsPage.vue'),
            meta: { cash: true },
          },
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
    if (auth.user.password_change_required && to.path !== '/cambiar-contrasena')
      return '/cambiar-contrasena';
    if (to.meta.messagesSend && !auth.can('messages.send')) return '/';
    if (to.meta.superAdmin && !auth.isSuperAdmin) return '/';
    if (to.meta.intake && !auth.canIntake) return '/';
    if (to.meta.credit && !auth.can('credit.view')) return '/';
    if (to.meta.creditCreate && !auth.can('credit.create')) return '/';
    if (to.meta.creditReview && !auth.can('credit.review')) return '/';
    if (to.meta.creditAuthorization && !auth.can('credit.propose') && !auth.can('credit.authorize'))
      return '/';
    if (to.meta.creditDisburse && !auth.can('credit.disburse')) return '/';
    if (to.meta.creditCollect && !auth.can('credit.collect') && !auth.can('credit.reverse'))
      return '/';
    if (to.meta.cash && !auth.can('cash.view')) return '/';
    return true;
  });
  window.addEventListener('staff-password-expired', () => {
    const auth = useStaffAuth(store);
    if (auth.user) auth.user.password_change_required = true;
    void router.replace('/cambiar-contrasena');
  });
  window.addEventListener('staff-session-expired', () => {
    if (router.currentRoute.value.meta.requiresAuth) void router.replace('/login');
  });
  return router;
});
