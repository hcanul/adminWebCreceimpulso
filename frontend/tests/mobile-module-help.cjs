const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=path.resolve(__dirname,'..');
const ts=require(root+'/apps/admin-web/node_modules/typescript');
function load(file,modules={}){const exports={};const src=fs.readFileSync(file,'utf8').replace(/import\.meta\.env\.QUASAR_VUE_ROUTER_BASE/g,"'/'");vm.runInNewContext(ts.transpileModule(src,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022}}).outputText,{exports,require:n=>{assert.ok(n in modules,n);return modules[n];},window:{addEventListener(){}}});return exports;}
const shared=load(root+'/packages/shared/help/staff-base.ts');
let total=0;
for(const app of ['client-app','staff-app']){
 const help=load(root+'/apps/'+app+'/src/help/mobile-help.ts',{'../../../../packages/shared/help/staff-base':shared}).mobileHelpFor;
 const routes=load(root+'/apps/'+app+'/src/router/index.ts',{'#q-app':{defineRouter:f=>f({store:{}})},'vue-router':{createRouter:o=>({...o,beforeEach(){}}),createWebHashHistory:()=>null},'../stores/auth':{useStaffAuth(){},useClientAuth(){}}}).default.routes;
 let count=0;function walk(list,parent=''){for(const route of list){if(route.redirect)continue;const full=route.path.startsWith('/')?route.path:parent.replace(/\/$/,'')+'/'+route.path;const guide=help(full.replace(/:id/g,'artificial-id'));assert.notEqual(guide.id,'unknown',full);assert.ok(guide.steps.length===3);assert.ok(guide.important);if(route.children)walk(route.children,full);count++;}}
 walk(routes);total+=count;
 if(app==='staff-app'){assert.equal(help('/sin-conexion').id,'offline');assert.match(help('/sin-conexion').important,/No se cobran pagos en campo/);assert.equal(help('/clientes/capturar').id,'client-capture');}
 else{assert.ok(help('/').targets.length===4);assert.match(help('/').important,/no es tu deuda personal/);assert.match(help('/activar').important,/uso único/);}
 console.log(`PASS ${app}: ${count} routes covered, procedures and safety notes present`);
}
console.log(`OK: ${total} mobile route records covered by contextual help.`);
