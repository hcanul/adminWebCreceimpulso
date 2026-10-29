import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// CONFIGURA AQUÍ TU TARIFA POR HORA (En pesos mexicanos - MXN)
const TARIFA_POR_HORA = 250; // Ejemplo: $250 MXN/hr (Ajusta según tu experiencia)

// Rutas clave dentro de un proyecto Quasar
const srcPath = path.join(__dirname, 'src');
const pagesPath = path.join(srcPath, 'pages');
const componentsPath = path.join(srcPath, 'components');
const storePath = path.join(srcPath, 'stores'); // o 'store' para Vuex

function countFiles(dirPath) {
    if (!fs.existsSync(dirPath)) return 0;
    let count = 0;
    const files = fs.readdirSync(dirPath, { withFileTypes: true });
    for (const file of files) {
        if (file.isDirectory()) {
            count += countFiles(path.join(dirPath, file.name));
        } else if (file.name.endsWith('.vue') || file.name.endsWith('.js') || file.name.endsWith('.ts')) {
            count++;
        }
    }
    return count;
}

console.log("=== Analizando Complejidad del Proyecto Quasar ===\n");

const numPages = countFiles(pagesPath);
const numComponents = countFiles(componentsPath);
const numStores = countFiles(storePath);

console.log(`📄 Páginas/Vistas detectadas: ${numPages}`);
console.log(`🧩 Componentes reutilizables: ${numComponents}`);
console.log(`🗃️ Módulos de Estado/API (Stores): ${numStores}`);

// FÓRMULA DE ESTIMACIÓN DE HORAS DE DESARROLLO
// Base por configuración del proyecto + Capacitor: 15 hrs
// Promedio por pantalla/vista (diseño + lógica): 6 hrs
// Promedio por componente reutilizable: 3 hrs
// Promedio por integración de API/Store: 4 hrs
// Pruebas, corrección de bugs y ajustes responsivos: +20% del total
const hrsBase = 15;
const hrsPaginas = numPages * 6;
const hrsComponentes = numComponents * 3;
const hrsStores = numStores * 4;

const subtotalHoras = hrsBase + hrsPaginas + hrsComponentes + hrsStores;
const hrsQA = Math.round(subtotalHoras * 0.20);
const totalHorasDesarrollo = subtotalHoras + hrsQA;

// Horas para proceso de despliegue y subida a Google Play Console: 8 hrs
const hrsDespliegue = 8;
const horasTotalesProyecto = totalHorasDesarrollo + hrsDespliegue;

// CÁLCULOS FINANCIEROS
const costoDesarrollo = horasTotalesProyecto * TARIFA_POR_HORA;
const cuotaGooglePlay = 500; // ~$25 USD a MXN

console.log("\n=================================================");
console.log(`⏱️  Horas estimadas de desarrollo: ${totalHorasDesarrollo} hrs`);
console.log(`🚀 Horas estimadas de configuración y subida a Tienda: ${hrsDespliegue} hrs`);
console.log(`⏱️  TOTAL DE HORAS ESTIMADAS: ${horasTotalesProyecto} hrs`);
console.log("=================================================");
console.log(`💰 Tarifa por hora configurada: $${TARIFA_POR_HORA} MXN`);
console.log(`💵 Subtotal por Honorarios de Desarrollo: $${costoDesarrollo.toLocaleString('es-MX')} MXN`);
console.log(`💳 Licencia Google Play (Pago único a terceros): $${cuotaGooglePlay} MXN`);
console.log("-------------------------------------------------");
console.log(`🏷️  PRESUPUESTO TOTAL SUGERIDO: $${(costoDesarrollo + cuotaGooglePlay).toLocaleString('es-MX')} MXN`);
console.log("=================================================");
