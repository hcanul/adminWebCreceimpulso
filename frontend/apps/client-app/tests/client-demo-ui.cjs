const {chromium}=require(process.env.PLAYWRIGHT_MODULE);
const assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({channel:'chrome',headless:true});try{
 const page=await browser.newPage({viewport:{width:390,height:844}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const login=await page.request.post('http://localhost:8000/api/client/login',{data:{identifier:process.env.DEMO_CLIENT,password:process.env.DEMO_PASSWORD}});assert.equal(login.status(),200);const {token}=await login.json();
 await page.addInitScript(t=>sessionStorage.setItem('crece.client.session',t),token);
 await page.goto('http://localhost:9013/#/');await page.getByText('MI CRÉDITO ACTIVO',{exact:true}).waitFor();
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 await page.screenshot({path:'/tmp/crece-demo-credit.png',fullPage:true});
 await page.getByRole('tab',{name:'Calendario',exact:true}).click();await page.getByText('Semana 16',{exact:true}).waitFor();
 await page.getByRole('tab',{name:'Mis pagos',exact:true}).click();await page.getByRole('button',{name:'Ver comprobante'}).first().click();await page.getByAltText('QR de verificación del comprobante ficticio').waitFor();
 await page.waitForTimeout(500);await page.screenshot({path:'/tmp/crece-demo-receipt.png',fullPage:true});assert.deepEqual(errors,[]);
 await page.request.post('http://localhost:8000/api/client/logout',{headers:{Authorization:`Bearer ${token}`}});
 console.log('OK: API real, crédito, 16 semanas, historial y QR en móvil.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
