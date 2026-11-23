const {chromium}=require(process.env.PLAYWRIGHT_MODULE);
const assert=require('node:assert/strict');
const http=require('node:http'),fs=require('node:fs'),path=require('node:path');
const servers=[];
async function serve(app,port){
 const root=path.resolve(__dirname,'../apps',app,'dist/spa');
 const server=http.createServer((req,res)=>{
  const file=path.resolve(root,'.'+decodeURIComponent(new URL(req.url,'http://localhost').pathname));
  if(file!==root&&!file.startsWith(root+path.sep)){res.writeHead(403).end();return;}
  const target=file===root?path.join(root,'index.html'):file;
  if(!fs.existsSync(target)||!fs.statSync(target).isFile()){res.writeHead(404).end();return;}
  const types={'.html':'text/html','.js':'application/javascript','.css':'text/css','.svg':'image/svg+xml','.png':'image/png','.woff2':'font/woff2','.json':'application/json'};
  res.writeHead(200,{'Content-Type':types[path.extname(target)]||'application/octet-stream'});fs.createReadStream(target).pipe(res);
 });
 await new Promise((resolve,reject)=>{server.once('error',reject);server.listen(port,'127.0.0.1',resolve);});servers.push(server);
}

(async()=>{await serve('admin-web',18919);await serve('client-app',18920);await serve('staff-app',18921);const browser=await chromium.launch({channel:'chrome',headless:true});try{
for(const [app,port,namespace,sessionKey] of [['admin-web',18919,'crece.admin','access_token'],['client-app',18920,'crece.client','crece.client.session'],['staff-app',18921,'crece.staff','crece.staff.session']]){
 const page=await browser.newPage();let activityCalls=0;const errors=[];page.on('pageerror',e=>{errors.push(e.message);console.log(app,'PAGE ERROR',e.message);});
 await page.addInitScript(({app,namespace,sessionKey})=>{
 if(sessionStorage.getItem('idle-test-seeded'))return;sessionStorage.setItem('idle-test-seeded','yes');
 const token='987|private-test-token';localStorage.setItem('test-token',token);
 if(app==='admin-web')localStorage.setItem(sessionKey,'__q_strn|'+token);else sessionStorage.setItem(sessionKey,token);
 },{app,namespace,sessionKey});
 await page.route('**/api/**',async route=>{const u=new URL(route.request().url());let data;
 if(u.pathname.endsWith('/session/activity')){activityCalls++;return route.fulfill({status:204});}
 if(u.pathname.endsWith('/me'))data=app==='client-app'?{name:'Prueba',folio:'CLI-DEMO',branch:'DEMO',must_change_password:true}:{id:'test',name:'DEMO',email:'demo@example.test',is_active:true,roles:[{name:'Super Admin'}],permissions:[],password_change_required:true};
 else throw Error(`Unexpected API ${u.pathname}`);
 return route.fulfill({contentType:'application/json',body:JSON.stringify(data)});
 });
 await page.clock.install({time:new Date('2026-11-19T16:00:00Z')});
 await page.goto(`http://127.0.0.1:${port}/#/cambiar-contrasena`);
 try { await page.getByRole('heading',{name:app==='client-app'?'Elige tu contraseña personal':'Renueva tu contraseña',exact:true}).waitFor({timeout:10000}); } catch(e) { console.log(app,page.url(),await page.locator('body').innerText()); throw e; }
 const before=activityCalls;
 await page.clock.fastForward(29*60000);await page.waitForTimeout(50);
 assert.ok(!page.url().includes('/login'),'must remain authenticated before 30 minutes');
 assert.ok(activityCalls<=before+1,'background timer must not renew indefinitely');
 await page.getByRole('heading').click();await page.waitForTimeout(50);
 await page.clock.fastForward(29*60000);await page.waitForTimeout(50);assert.ok(!page.url().includes('/login'),'real interaction renews activity');
 await page.clock.fastForward(60000);await page.waitForURL('**/login?expired=idle');
 await page.getByText('Tu sesión terminó después de 30 minutos sin actividad. Vuelve a ingresar.',{exact:true}).waitFor();
 // addInitScript intentionally reinjects tokens on navigation: verify the persisted end marker wins.
 const ended=await page.evaluate(namespace=>Object.entries(localStorage).filter(([key])=>key.startsWith(namespace+':idle:')).some(([,v])=>JSON.parse(v).ended),namespace);assert.equal(ended,true);
 assert.deepEqual(errors,[]);console.log(`OK ${app}: protected page, renewal by real click, expiry at 30 minutes, redirect and explanation (mocked API, accelerated browser clock).`);await page.close();
}
}finally{await browser.close();servers.forEach(server=>server.close());}})().catch(e=>{console.error(e);servers.forEach(server=>server.close());process.exitCode=1;});
