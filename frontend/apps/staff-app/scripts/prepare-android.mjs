import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { existsSync } from 'node:fs';
const root = fileURLToPath(new URL('../', import.meta.url));
const api = process.env.QCLI_API_URL;
if (!api) throw new Error('Indica la API: QCLI_API_URL=http://IP-DE-TU-MAC:8000/api pnpm android:prepare');
const url = new URL(api);
if (!['http:', 'https:'].includes(url.protocol) || ['localhost','127.0.0.1','0.0.0.0','::1','[::1]'].includes(url.hostname)) throw new Error('El dispositivo necesita la IP de tu Mac o una API HTTPS accesible, no localhost.');
const local = /^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/.test(url.hostname) || url.hostname.endsWith('.local');
if (url.protocol === 'http:' && !local) throw new Error('HTTP solo está permitido para pruebas en una red local. Usa HTTPS fuera de ella.');
const native = `${root}src-capacitor`;
function run(script,args,cwd=root) {
 const result=spawnSync(process.execPath,[script,...args],{cwd,stdio:'inherit',env:process.env});
 if(result.status!==0) process.exit(result.status || 1);
}
if (!existsSync(`${native}/node_modules/@capacitor/cli/bin/capacitor`)) throw new Error('Primero instala dependencias: cd src-capacitor && pnpm install');
run(`${root}node_modules/@quasar/app-vite/bin/quasar.js`,['build','-m','capacitor','-T','android','--skip-pkg']);
run(`${native}/node_modules/@capacitor/cli/bin/capacitor`,['sync','android'],native);
console.log('Proyecto Android listo. Compila assembleDebug con JDK 21 y Android SDK 36.');
