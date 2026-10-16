import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import ts from 'typescript';
import * as vue from 'vue';

async function moduleAt(path, imports = {}) {
  const source = await readFile(new URL(path, import.meta.url), 'utf8');
  const output = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 },
  }).outputText;
  const module = new vm.SourceTextModule(output);
  await module.link((name) => {
    const exports = name === 'vue' ? vue : imports[name];
    return new vm.SyntheticModule(Object.keys(exports), function () {
      for (const [key, value] of Object.entries(exports)) this.setExport(key, value);
    });
  });
  await module.evaluate();
  return module.namespace;
}
const { clientEntryNotice } = await moduleAt('../src/domain/client-entry.ts');

test('persona nueva requiere confirmación y distingue estructura de identidad documental', () => {
  const notice = clientEntryNotice({ found: false });
  assert.equal(notice.allowed, true);
  assert.equal(notice.confirmLabel, 'Iniciar expediente');
  assert.match(notice.text, /revisión documental/);
});
test('persona existente presenta sucursal, crédito activo e historial antes de editar', () => {
  const notice = clientEntryNotice({
    found: true,
    can_edit: true,
    client: { name: 'Prueba', branch: 'Tulum', status: 'activo' },
    active_credits: [{ group: 'Grupo 1', branch: 'Tulum' }],
    last_credit: { group: 'Grupo 1', status: 'entregado', registered_at: '2026-10-08T12:00:00Z' },
  });
  assert.match(notice.text, /Sucursal de alta: Tulum/);
  assert.match(notice.text, /Créditos activos: 1/);
  assert.match(notice.text, /no es la fecha de desembolso/);
  assert.equal(notice.confirmLabel, 'Sí, abrir expediente');
});
test('registro sin permiso no ofrece entrada al formulario', () => {
  const notice = clientEntryNotice({
    found: true,
    can_edit: false,
    client: { branch: 'Morelos', status: 'activo' },
  });
  assert.equal(notice.allowed, false);
  assert.match(notice.text, /No se creará otra persona/);
});
test('solicitud enviada ofrece consulta y no modificación', () => {
  const notice = clientEntryNotice({
    found: true,
    can_edit: true,
    application: { status: 'en_revision' },
  });
  assert.equal(notice.confirmLabel, 'Consultar expediente');
});
test('cancelación o descarte nunca equivalen a confirmación', async () => {
  let options;
  const { alerts } = await moduleAt('../src/services/alerts.service.ts', {
    sweetalert2: {
      default: {
        mixin: () => ({
          fire: async (data) => {
            options = data;
            return { isConfirmed: false, isDismissed: true };
          },
        }),
      },
    },
  });
  assert.equal(await alerts.confirm('Enviar', 'Texto', 'Enviar a revisión'), false);
  assert.equal(options.focusCancel, true);
  assert.equal(options.allowOutsideClick, false);
});
test('la confirmación utiliza texto seguro y la identidad visual acordada', async () => {
  let defaults, options;
  const { alerts } = await moduleAt('../src/services/alerts.service.ts', {
    sweetalert2: {
      default: {
        mixin: (data) => {
          defaults = data;
          return {
            fire: async (data) => {
              options = data;
              return { isConfirmed: true };
            },
          };
        },
      },
    },
  });
  const input = '<img src=x onerror=alert(1)>';
  assert.equal(await alerts.confirm('Prueba', input), true);
  assert.equal(options.text, input);
  assert.equal('html' in options, false);
  assert.equal(defaults.confirmButtonColor, '#811335');
  assert.equal(defaults.keydownListenerCapture, true);
});
