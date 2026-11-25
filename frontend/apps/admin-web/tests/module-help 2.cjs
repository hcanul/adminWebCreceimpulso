const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const project = path.resolve(__dirname, '..');
const ts = require(project + '/node_modules/typescript');
function load(relative) {
  const exports = {};
  vm.runInNewContext(
    ts.transpileModule(fs.readFileSync(path.join(project, relative), 'utf8'), {
      compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText,
    { exports },
  );
  return exports;
}
const { moduleHelpFor } = load('src/help/module-help.ts');
const routes = load('src/router/routes.ts').default;
let count = 0;
function check(records, parent = '') {
  for (const route of records) {
    if (route.path.includes('catchAll') || route.redirect) continue;
    const full = route.path.startsWith('/')
      ? route.path
      : parent.replace(/\/$/, '') + '/' + route.path;
    const actual = full.replace(/:id/g, '123-test-id').replace(/:type/g, 'credit');
    const help = moduleHelpFor(actual);
    assert.notEqual(help.id, 'unknown', `Missing guide for ${full}`);
    assert.ok(help.purpose.length > 20);
    assert.equal(help.steps.length, 3);
    assert.ok(help.important.length > 20);
    if (route.children) check(route.children, full);
    count++;
  }
}
check(routes);
for (const [route, expected] of [
  ['/clientes/captura', 'client-capture'],
  ['/solicitudes-credito/nueva', 'credit-create'],
  ['/cortes/x/imprimir', 'closing-print'],
  ['/cobros-depositos', 'cash-movements'],
  ['/creditos/x/estado-de-cuenta', 'statement'],
  ['/documentos/credit/x?folio=test', 'documents'],
  ['/no-existe', 'unknown'],
  ['/clientes/', 'clients'],
])
  assert.equal(moduleHelpFor(route).id, expected);
console.log(
  `OK: ${count} route records have contextual help; capture, detail and print guides resolve correctly.`,
);
