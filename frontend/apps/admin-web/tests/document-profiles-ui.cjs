const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({channel:'chrome',headless:true});try{
const page=await browser.newPage({viewport:{width:1280,height:950}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.addInitScript(()=>localStorage.setItem('access_token','__q_strn|profile-test'));
const id='11111111-1111-4111-8111-111111111111';
const fields={legal_address:'Domicilio general',representative_name:'',representative_position:'',payment_place:'',signing_place:''};
let profile={scope:'general',version:0,use_general:true,fields};
await page.route('**/api/**',async route=>{const u=new URL(route.request().url());let body;
if(u.pathname.endsWith('/me'))body={id,name:'Admin Demo',is_active:true,roles:[{name:'Super Admin'}],permissions:[]};
else if(u.pathname.endsWith('/options'))body={branches:[{id,name:'Carrillo'}]};
else {const scope=u.pathname.split('/').at(-1);if(route.request().method()==='PUT'){const data=route.request().postDataJSON();assert.equal(data.fields.legal_address,'Domicilio actualizado');profile={...data,version:1};}body={profile:scope==='general'?profile:{...profile,scope,use_general:true},effective:{source:'general',version:profile.version,fields:profile.fields,missing:[]}};}
await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(body)});});
await page.goto('http://localhost:9012/#/administracion/datos-documentales');
const address=page.getByLabel('Domicilio legal',{exact:true});await address.fill('Domicilio actualizado');await page.getByRole('button',{name:'Guardar configuración'}).click();await page.getByText('Configuración guardada',{exact:true}).waitFor();await page.reload();await address.waitFor();assert.equal(await address.inputValue(),'Domicilio actualizado');
const select=page.getByLabel('Empresa o sucursal',{exact:true});await select.click();await select.fill('Carrillo');await page.getByRole('option',{name:'Carrillo',exact:true}).click();await page.getByText('Esta sucursal utiliza el perfil general.',{exact:false}).waitFor();assert.equal(await address.isDisabled(),true);
await page.getByRole('switch').click();assert.equal(await address.isDisabled(),false);
await page.setViewportSize({width:390,height:844});await page.waitForTimeout(700);await page.screenshot({path:'/tmp/crece-document-profiles-mobile.png',fullPage:true});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);assert.deepEqual(errors,[]);console.log('OK: guardado, recarga, sucursal, herencia y móvil (API simulada).');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
