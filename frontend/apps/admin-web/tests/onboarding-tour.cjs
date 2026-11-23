const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const project = path.resolve(__dirname, '..');
const ts = require(project + '/node_modules/typescript');
const code = ts.transpileModule(fs.readFileSync(project + '/src/composables/useOnboardingTour.ts', 'utf8'), {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText;
function fixture(options = {}) {
  const mounted = [], watchers = [], unmounted = [], requests = [], tours = [], notices = [];
  const auth = { token: 'artificial-session', user: { id: 'artificial-user', password_change_required: false } };
  const route = { fullPath: '/' };
  const drawer = { value: false };
  const elements = [
    { innerText: 'Grupos solidarios', dataset: { tourModule: '/grupos' }, getClientRects: () => [1] },
    { innerText: 'Clientes', dataset: { tourModule: '/clientes' }, getClientRects: () => [1] },
    { innerText: 'Cobros en caja', dataset: { tourModule: '/cobros' }, getClientRects: () => [] },
  ];
  const exports = {};
  const modules = {
    vue: { nextTick: async () => {}, onMounted: fn => mounted.push(fn), watch: (source, callback) => watchers.push(callback), onBeforeUnmount: fn => unmounted.push(fn) },
    'vue-router': { useRoute: () => route },
    quasar: { useQuasar: () => ({ notify: notice => notices.push(notice) }) },
    '../stores/auth.store': { useAuthStore: () => auth },
    '../services/http': { api: { post: async (...args) => { requests.push(args); return options.response ? options.response() : { data: { show: options.show !== false } }; } } },
    'driver.js': { driver: config => {
      const instance = { live: false, config, drive() { this.live = true; tours.push(config); }, isActive() { return this.live; }, destroy() { this.live = false; config.onDestroyed(); } };
      return instance;
    } },
  };
  vm.runInNewContext(code, {
    exports, require: name => { if (!(name in modules)) throw new Error(name); return modules[name]; },
    document: { querySelector: selector => ({ selector }), querySelectorAll: () => elements },
    window: { setTimeout: fn => { fn(); return 1; }, matchMedia: () => ({ matches: false }) },
    Set, Promise, Error,
  });
  return { ...exports.useOnboardingTour(drawer), mounted, watchers, unmounted, auth, route, drawer, requests, tours, notices };
}
async function settle() { for (let i = 0; i < 35; i++) await Promise.resolve(); }
(async () => {
  const first = fixture(); first.mounted[0](); await settle();
  assert.equal(first.requests.length, 1);
  assert.equal(first.tours.length, 1);
  assert.equal(first.drawer.value, true);
  const titles = Array.from(first.tours[0].steps, step => step.popover.title);
  assert.ok(titles.includes('Grupos solidarios'));
  assert.ok(titles.includes('Clientes'));
  assert.ok(!titles.includes('Cobros en caja'), 'hidden modules are never highlighted');
  first.watchers[1](); assert.equal(first.drawer.value, false, 'navigation destroys tour and restores drawer');
  await first.repeatTour(); assert.equal(first.requests.length, 1, 'manual replay does not consume automatic logins');
  assert.equal(first.tours.length, 2);
  first.unmounted[0](); assert.equal(first.drawer.value, false);
  const third = fixture({ show: false }); third.mounted[0](); await settle();
  assert.equal(third.tours.length, 0, 'server limit respected');
  await third.repeatTour(); assert.equal(third.tours.length, 1, 'manual help remains available');
  let release;
  const pending = fixture({ response: () => new Promise(resolve => { release = resolve; }) });
  pending.mounted[0](); await settle(); pending.auth.token = null; pending.watchers[0]();
  release({ data: { show: true } }); await settle();
  assert.equal(pending.tours.length, 0, 'late response after logout cannot open tour');
  const expired = fixture(); expired.auth.user.password_change_required = true;
  expired.mounted[0](); await settle(); assert.equal(expired.requests.length, 0);
  const offline = fixture({ response: async () => { throw new Error('offline'); } });
  offline.mounted[0](); await settle(); assert.equal(offline.tours.length, 0); assert.equal(offline.notices.length, 0);
  console.log('OK: authorized visible modules, server limit, manual replay, logout race, password renewal deferral, drawer cleanup and non-blocking API failure.');
})().catch(error => { console.error(error); process.exitCode = 1; });
