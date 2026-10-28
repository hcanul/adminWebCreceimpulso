import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const capacitorAndroidPath = path.join(__dirname, 'src-capacitor', 'android');
const appBuildGradlePath = path.join(capacitorAndroidPath, 'app', 'build.gradle');
const variablesGradlePath = path.join(capacitorAndroidPath, 'variables.gradle');
const manifestPath = path.join(capacitorAndroidPath, 'app', 'src', 'main', 'AndroidManifest.xml');

console.log("=== Analizador de Requisitos para Google Play (Capacitor) ===\n");

function checkFileExists(filePath, name) {
    if (!fs.existsSync(filePath)) {
        console.error(`❌ No se encontró: ${name}`);
        process.exit(1);
    }
}

checkFileExists(appBuildGradlePath, 'app/build.gradle');
checkFileExists(manifestPath, 'AndroidManifest.xml');

const manifestContent = fs.readFileSync(manifestPath, 'utf8');
const appGradleContent = fs.readFileSync(appBuildGradlePath, 'utf8');

// 1. Verificar Nivel de API (Busca en variables.gradle y en build.gradle)
let targetApi = 0;

if (fs.existsSync(variablesGradlePath)) {
    const varsContent = fs.readFileSync(variablesGradlePath, 'utf8');
    const matchVars = varsContent.match(/targetSdkVersion\s*=\s*(\d+)/) || varsContent.match(/targetSdk\s*=\s*(\d+)/);
    if (matchVars) targetApi = parseInt(matchVars[1]);
}

if (!targetApi) {
    const matchDirect = appGradleContent.match(/targetSdkVersion\s*=?\s*(\d+)/) || appGradleContent.match(/targetSdk\s*=?\s*(\d+)/);
    if (matchDirect) targetApi = parseInt(matchDirect[1]);
}

if (targetApi >= 34) {
    console.log(`✅ Nivel de API objetivo correcto: ${targetApi}`);
} else if (targetApi > 0) {
    console.log(`❌ ALERTA: Tu targetSdkVersion es ${targetApi}. Google Play exige nivel 34 o superior.`);
} else {
    console.log("⚠️ No se detectó automáticamente targetSdkVersion. Revisa 'src-capacitor/android/variables.gradle'.");
}

// 2. Verificar Application ID
const appIdMatch = appGradleContent.match(/applicationId\s*"?([^"\s]+)"?/);
if (appIdMatch) {
    const appId = appIdMatch[1];
    if (appId.includes('com.example') || appId.split('.').length < 2) {
        console.log(`❌ ALERTA: Application ID (${appId}) es genérico.`);
    } else {
        console.log(`✅ Application ID válido: ${appId}`);
    }
}

// 3. Detectar Permisos
console.log("\n--- Análisis de Permisos ---");
const permissionRegex = /<uses-permission android:name="android\.permission\.([^"]+)"/g;
let match;
let permissions = [];
while ((match = permissionRegex.exec(manifestContent)) !== null) {
    permissions.push(match[1]);
}

if (permissions.length === 0) {
    console.log("✅ No se detectaron permisos especiales.");
} else {
    console.log(`Se encontraron ${permissions.length} permiso(s):`);
    permissions.forEach(perm => {
        let warning = ["CAMERA", "READ_CONTACTS", "ACCESS_FINE_LOCATION", "RECORD_AUDIO", "READ_EXTERNAL_STORAGE"].includes(perm)
            ? " ⚠️ (Requiere justificación)"
            : "";
        console.log(` - ${perm}${warning}`);
    });
}
console.log("\n=================================================");
