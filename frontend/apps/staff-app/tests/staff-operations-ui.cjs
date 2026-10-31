const {chromium}=require(process.env.PLAYWRIGHT_MODULE);const assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({channel:'chrome',headless:true});try{
for(const profile of [
 {role:'Promotor',permissions:['intake.access','credit.view','credit.create'],blocked:['/usuarios','/roles','/gestion','/autorizaciones','/desembolsos','/cobros','/cortes']},
 {role:'Gestión',permissions:['credit.view','credit.review'],blocked:['/usuarios','/roles','/autorizaciones','/desembolsos','/cobros','/cortes']},
 {role:'Caja',permissions:['credit.view','credit.collect','credit.disburse','cash.view','cash.close'],blocked:['/usuarios','/roles','/gestion','/autorizaciones']},
 {role:'Gerente General',permissions:['credit.view','credit.propose','credit.reverse','cash.view'],blocked:['/usuarios','/roles']},
 {role:'Super Admin',permissions:[],blocked:[]}
]){
 const page=await browser.newPage({viewport:{width:390,height:844}});page.setDefaultTimeout(60000);const errors=[];page.on('pageerror',e=>errors.push(e.message));await page.addInitScript(()=>sessionStorage.setItem('crece.staff.session','qa-staff'));
 await page.route('**/api/**',async route=>{const p=new URL(route.request().url()).pathname;assert.equal(route.request().headers().authorization,'Bearer qa-staff');let data={};
 if(p.endsWith('/me'))data={id:'11111111-1111-4111-8111-111111111111',name:'Personal QA',roles:[{name:profile.role}],permissions:profile.permissions,password_change_required:false,is_active:true};
 else if(p.endsWith('/credit/applications'))data={data:[],last_page:1,total:0};else {await route.fulfill({status:403,json:{}});return;}await route.fulfill({json:data}); });
 await page.goto('http://127.0.0.1:9014/#/');await page.getByRole('heading',{name:'Hola, Personal QA'}).waitFor();
 for(const path of profile.blocked){await page.goto('http://127.0.0.1:9014/#'+path);await page.waitForFunction(()=>location.hash==='#/');}
 await page.goto('http://127.0.0.1:9014/#/solicitudes-credito');await page.getByRole('heading',{name:'Solicitudes de crédito',exact:true}).waitFor();
 assert.equal(await page.getByRole('link',{name:'Nueva solicitud'}).count(),profile.role==='Promotor'||profile.role==='Super Admin'?1:0);
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);assert.deepEqual(errors,[]);
 if(profile.role==='Super Admin')await page.screenshot({path:'/tmp/staff-operaciones.png',fullPage:true});await page.close();
}
const expired=await browser.newPage();await expired.addInitScript(()=>sessionStorage.setItem('crece.staff.session','expired'));
await expired.route('**/api/me',r=>r.fulfill({json:{name:'QA',roles:[{name:'Super Admin'}],permissions:[],password_change_required:true}}));
await expired.goto('http://127.0.0.1:9014/#/solicitudes-credito');await expired.waitForURL('**/#/cambiar-contrasena');await expired.close();
console.log('OK: rutas por rol, axios staff, solicitudes móviles, bloqueo por vencimiento y sin desbordamiento.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1});
