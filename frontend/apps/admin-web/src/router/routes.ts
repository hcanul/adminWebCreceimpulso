import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    path: '/creditos/:id/estado-de-cuenta',
    component: () => import('@/layouts/BlankLayout.vue'),
    meta: { requiresAuth: true, credit: true },
    children: [{ path: '', component: () => import('@/pages/CreditStatementPage.vue') }],
  },
  {
    path: '/cortes/:id/imprimir',
    component: () => import('@/layouts/BlankLayout.vue'),
    meta: { requiresAuth: true, cash: true },
    children: [{ path: '', component: () => import('@/pages/CashClosingReportPage.vue') }],
  },
  {
    path: '/comprobantes/:id',
    component: () => import('@/layouts/BlankLayout.vue'),
    meta: { requiresAuth: true, credit: true, creditCollect: true },
    children: [{ path: '', component: () => import('@/pages/PaymentReceiptPage.vue') }],
  },
  {
    path: '/login',
    component: () => import('@/layouts/BlankLayout.vue'),
    meta: { guestOnly: true },
    children: [{ path: '', component: () => import('@/pages/Auth/LoginPage.vue') }],
  },
  {
    path: '/',
    component: () => import('@/layouts/Layout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: 'creditos/:id/garantia',
        component: () => import('@/pages/GuaranteePage.vue'),
        meta: { credit: true },
      },
      { path: 'documentos', component: () => import('@/pages/DocumentsPage.vue') },
      { path: 'documentos/:type/:id', component: () => import('@/pages/DocumentsPage.vue') },
      {
        path: 'cartera',
        component: () => import('@/pages/CreditPortfolioPage.vue'),
        meta: { credit: true },
      },
      {
        path: 'cortes',
        component: () => import('@/pages/CashClosingsPage.vue'),
        meta: { cash: true },
      },
      {
        path: 'cortes/:id',
        component: () => import('@/pages/CashClosingsPage.vue'),
        meta: { cash: true },
      },
      {
        path: 'administracion/usuarios',
        component: () => import('@/pages/UsersPage.vue'),
        meta: { superAdmin: true },
      },
      {
        path: 'administracion/roles',
        component: () => import('@/pages/RolesPage.vue'),
        meta: { superAdmin: true },
      },
      {
        path: 'cobros',
        component: () => import('@/pages/CreditApplicationsPage.vue'),
        meta: { credit: true, creditCollect: true },
      },
      {
        path: 'cobros/:id',
        component: () => import('@/pages/CreditCollectionPage.vue'),
        meta: { credit: true, creditCollect: true },
      },
      {
        path: 'desembolsos',
        component: () => import('@/pages/CreditApplicationsPage.vue'),
        meta: { credit: true, creditDisburse: true },
      },
      {
        path: 'autorizaciones',
        component: () => import('@/pages/CreditApplicationsPage.vue'),
        meta: { credit: true, creditAuthorization: true },
      },
      {
        path: 'gestion',
        component: () => import('@/pages/CreditApplicationsPage.vue'),
        meta: { credit: true, creditReview: true },
      },
      {
        path: 'solicitudes-credito',
        component: () => import('@/pages/CreditApplicationsPage.vue'),
        meta: { credit: true },
      },
      {
        path: 'solicitudes-credito/nueva',
        component: () => import('@/pages/CreditCapturePage.vue'),
        meta: { intake: true, creditCreate: true },
      },
      {
        path: 'solicitudes-credito/:id',
        component: () => import('@/pages/CreditDetailPage.vue'),
        meta: { credit: true },
      },
      {
        path: 'tasas',
        component: () => import('@/pages/CreditRatesPage.vue'),
        meta: { intake: true, creditRates: true },
      },
      { path: '', component: () => import('@/pages/IndexPage.vue') },
      { path: 'solicitudes', redirect: '/clientes' },
      {
        path: 'solicitudes/captura',
        redirect: (to) => ({ path: '/clientes/captura', query: to.query }),
      },
      {
        path: 'promotores',
        component: () => import('@/pages/PromotersPage.vue'),
        meta: { intake: true },
      },
      { path: 'grupos', component: () => import('@/pages/GroupsPage.vue'), meta: { intake: true } },
      {
        path: 'grupos/:id',
        component: () => import('@/pages/GroupDetailPage.vue'),
        meta: { intake: true },
      },
      {
        path: 'clientes',
        component: () => import('@/pages/ApplicationsPage.vue'),
        meta: { intake: true },
      },
      {
        path: 'clientes/captura',
        component: () => import('@/pages/ApplicationCapturePage.vue'),
        meta: { intake: true },
      },
    ],
  },
  { path: '/:catchAll(.*)*', component: () => import('@/pages/ErrorNotFound.vue') },
];
export default routes;
