import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
const root = fileURLToPath(new URL('../', import.meta.url));
function run(command, args, cwd) {
  const result = spawnSync(command, args, { cwd, stdio: 'inherit', env: process.env });
  if (result.error) throw result.error;
  if (result.status !== 0) process.exit(result.status || 1);
}
run(process.execPath, ['scripts/prepare-android.mjs'], root);
run(process.platform === 'win32' ? 'gradlew.bat' : './gradlew',
  ['assembleDebug', '--no-daemon', '--max-workers=2'], `${root}src-capacitor/android`);
console.log('APK: src-capacitor/android/app/build/outputs/apk/debug/app-debug.apk');
