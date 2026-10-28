const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({channel:'chrome',headless:true});try{
 const page=await browser.newPage({viewport:{width:1280,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.addInitScript(()=>localStorage.setItem('access_token','__q_strn|activation-test'));
 let issued=0; const id='11111111-1111-4111-8111-111111111111';
 await page.route('**/api/**',async route=>{const url=new URL(route.request().url());let body;
 if(url.pathname.endsWith('/me'))body={id,name:'Gerente Demo',is_active:true,roles:[{name:'Gerente'}],permissions:[]};
 else if(url.pathname.endsWith('/search'))body={clients:[{id,folio:'CAR-000001',first_name:'María',last_name:'Prueba',branch:{name:'Carrillo'}}]};
 else if(url.pathname.endsWith('/activation')){assert.equal(route.request().postDataJSON().identity_verified,true);issued++;body={code:'0123456789abcdef0123456789abcdef',expires_at:'2026-10-24T23:30:00-05:00'};}
 else throw Error('Unexpected route '+url.pathname);
 await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(body)});
 });
 await page.goto('http://localhost:9012/#/accesos-clientes');
 await page.getByLabel('CURP o número de cliente completo').fill('CAR-000001'); await page.getByRole('button',{name:'Buscar cliente'}).click();
 const button=page.getByRole('button',{name:'Generar código de un solo uso'});await button.waitFor();assert.equal(await button.isDisabled(),true);
 await page.getByRole('checkbox').click();await button.click();await page.getByText('0123456789abcdef0123456789abcdef').waitFor();assert.equal(issued,1);assert.equal(await button.isDisabled(),true);
 await page.setViewportSize({width:390,height:844});await page.waitForTimeout(700);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 await page.screenshot({path:'/tmp/crece-client-access-mobile.png',fullPage:true});await page.reload();await page.getByLabel('CURP o número de cliente completo').waitFor();assert.equal(await page.getByText('0123456789abcdef0123456789abcdef').count(),0);assert.deepEqual(errors,[]);
 console.log('OK: búsqueda, verificación presencial, código único, móvil y limpieza al recargar (API simulada).');
 }finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
