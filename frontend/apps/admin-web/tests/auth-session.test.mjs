import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import ts from 'typescript';
import * as vue from 'vue';
import * as pinia from 'pinia';
import axios from 'axios';

const source = ts.transpileModule(
  await readFile(new URL('../src/stores/auth.store.ts', import.meta.url), 'utf8'),
  {
    compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 },
  },
).outputText;
async function setup(service, saved = 'saved-token') {
  const values = new Map(saved ? [['access_token', saved]] : []);
  const dependencies = {
    '../stores/intake.store': { useIntakeStore: () => ({ clear() {} }) },
    './intake.store': { useIntakeStore: () => ({ clear() {} }) },
    pinia,
    vue,
    quasar: {
      LocalStorage: {
        getItem: (key) => values.get(key) ?? null,
        set: (key, value) => values.set(key, value),
        remove: (key) => values.delete(key),
      },
    },
    axios: { default: axios },
    '../services/auth.service': { authService: service },
  };
  const module = new vm.SourceTextModule(source);
  await module.link((specifier) => {
    const exports = dependencies[specifier];
    return new vm.SyntheticModule(Object.keys(exports), function () {
      for (const [key, value] of Object.entries(exports)) this.setExport(key, value);
    });
  });
  await module.evaluate();
  return { auth: module.namespace.useAuthStore(pinia.createPinia()), values };
}
const profile = {
  id: 'uuid',
  name: 'Usuario de prueba',
  email: 'test@example.invalid',
  branch_id: null,
  is_active: true,
  roles: [{ id: 1, name: 'Gerente' }],
};
const unauthorized = () =>
  new axios.AxiosError('Unauthorized', undefined, undefined, undefined, { status: 401 });

test('recargar recupera usuario y roles consultando la API, no solo el token', async () => {
  let calls = 0;
  const { auth } = await setup({
    me: async (token) => {
      calls++;
      assert.equal(token, 'saved-token');
      return profile;
    },
  });
  assert.equal(auth.isAuthenticated, false);
  await Promise.all([auth.restoreSession(), auth.restoreSession()]);
  assert.equal(calls, 1);
  assert.equal(auth.isAuthenticated, true);
  assert.deepEqual([...auth.roles], ['Gerente']);
});
test('un 401 limpia sesión persistida', async () => {
  const { auth, values } = await setup({
    me: async () => {
      throw unauthorized();
    },
  });
  await auth.restoreSession();
  assert.equal(auth.isAuthenticated, false);
  assert.equal(values.has('access_token'), false);
});
test('un fallo de red conserva token y permite revalidar', async () => {
  let available = false;
  const { auth, values } = await setup({
    me: async () => {
      if (!available) throw new axios.AxiosError('Network Error');
      return profile;
    },
  });
  await auth.restoreSession();
  assert.equal(auth.restoreFailed, true);
  assert.equal(auth.isAuthenticated, false);
  assert.equal(values.get('access_token'), 'saved-token');
  available = true;
  await auth.retrySession();
  assert.equal(auth.isAuthenticated, true);
  assert.equal(auth.restoreFailed, false);
});
test('una respuesta tardía no restaura una sesión cerrada', async () => {
  let resolve;
  const { auth } = await setup({
    me: () =>
      new Promise((r) => {
        resolve = r;
      }),
  });
  const pending = auth.restoreSession();
  auth.clearSession();
  resolve(profile);
  await pending;
  assert.equal(auth.user, null);
  assert.equal(auth.token, null);
});
test('login persiste solo token; logout revoca antes de limpiar', async () => {
  let revoked;
  const { auth, values } = await setup(
    {
      login: async () => ({ user: profile, token: 'new-token', roles: ['Gerente'] }),
      logout: async (token) => {
        revoked = token;
      },
    },
    null,
  );
  await auth.login('test@example.invalid', 'not-a-real-password');
  assert.equal(values.get('access_token'), 'new-token');
  assert.equal(values.has('user_data'), false);
  await auth.logout();
  assert.equal(revoked, 'new-token');
  assert.equal(auth.isAuthenticated, false);
  assert.equal(values.size, 0);
});
test('logout fallido conserva sesión para reintentar la revocación', async () => {
  const { auth, values } = await setup({
    me: async () => profile,
    logout: async () => {
      throw new Error('offline');
    },
  });
  await auth.restoreSession();
  await assert.rejects(auth.logout());
  assert.equal(auth.isAuthenticated, true);
  assert.equal(values.get('access_token'), 'saved-token');
});
test('usuario inactivo no puede restaurar sesión', async () => {
  const { auth, values } = await setup({ me: async () => ({ ...profile, is_active: false }) });
  await auth.restoreSession();
  assert.equal(auth.isAuthenticated, false);
  assert.equal(values.size, 0);
});
