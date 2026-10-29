import { defineBoot } from '#q-app';
import axios, { type AxiosInstance } from 'axios';
import { api } from '../services/http';
import { useAuthStore } from '../stores/auth.store';

declare module 'vue' {
  interface ComponentCustomProperties {
    $axios: AxiosInstance;
    $api: AxiosInstance;
  }
}
export default defineBoot(({ app, router, store }) => {
  const auth = useAuthStore(store);
  const requestId = api.interceptors.request.use((config) => {
    if (auth.token && config.url !== '/login' && !config.headers.Authorization) {
      config.headers.Authorization = `Bearer ${auth.token}`;
    }
    return config;
  });
  const responseId = api.interceptors.response.use(
    (response) => response,
    (error: unknown) => {
      if (
        axios.isAxiosError(error) &&
        error.response?.status === 403 &&
        (error.response.data as { code?: string })?.code === 'PASSWORD_CHANGE_REQUIRED'
      ) {
        if (auth.user) auth.user.password_change_required = true;
        void router.replace('/cambiar-contrasena');
      }
      if (
        axios.isAxiosError(error) &&
        error.response?.status === 401 &&
        auth.token &&
        error.config?.headers.Authorization === `Bearer ${auth.token}`
      ) {
        auth.clearSession();
        if (router.currentRoute.value.meta.requiresAuth) {
          void router.replace({
            path: '/login',
            query: { redirect: router.currentRoute.value.fullPath },
          });
        }
      }
      return Promise.reject(
        error instanceof Error ? error : new Error('Error de comunicación con la API'),
      );
    },
  );
  app.config.globalProperties.$axios = axios;
  app.config.globalProperties.$api = api;
  if (import.meta.hot)
    import.meta.hot.dispose(() => {
      api.interceptors.request.eject(requestId);
      api.interceptors.response.eject(responseId);
    });
});
export { api };
