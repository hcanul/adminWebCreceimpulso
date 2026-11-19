import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { existsSync, realpathSync } from 'node:fs';

const appDir = fileURLToPath(new URL('../', import.meta.url));
function run(command, args) {
  const result = spawnSync(command, args, { cwd: appDir, stdio: 'inherit' });
  if (result.error) throw result.error;
  if (result.status !== 0) process.exit(result.status ?? 1);
}

run(process.execPath, [
  path.join(appDir, 'node_modules/@quasar/app-vite/bin/quasar.js'),
  'build',
  '-m',
  'electron',
  '--skip-pkg',
]);
// El caché de npm ofrece un árbol plano: NSIS puede abortar con las rutas
// profundas de pnpm. La versión del empaquetador y NSIS quedan fijadas.
const args = [
  'exec',
  '--yes',
  '--package=electron-builder@26.18.0',
  '--',
  'electron-builder',
  '--projectDir',
  path.join(appDir, 'src-electron'),
  '--config',
  path.join(appDir, 'src-electron/electron-builder.windows.json'),
  '--win',
  '--x64',
  '--publish',
  'never',
];
// npm_execpath apunta al gestor que inició el script; puede ser pnpm.
// Ejecutar npm-cli del mismo runtime evita depender de .cmd en Windows.
const candidates = [
  path.join(path.dirname(process.execPath), 'node_modules/npm/bin/npm-cli.js'),
  path.resolve(path.dirname(process.execPath), '../lib/node_modules/npm/bin/npm-cli.js'),
  ...String(process.env.PATH ?? '')
    .split(path.delimiter)
    .map((dir) => path.join(dir, 'npm')),
];
const npmPath = candidates.find((candidate) => existsSync(candidate));
if (!npmPath) throw new Error('Se necesita Node.js con npm instalado para empaquetar Windows.');
run(process.execPath, [realpathSync(npmPath), ...args]);
