const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const ts=require('../apps/client-app/node_modules/typescript');
const code=ts.transpileModule(fs.readFileSync(require('node:path').join(__dirname,'../packages/shared/session-inactivity.ts'),'utf8'),{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022}}).outputText;
function fixture(){let now=1000000000,interval;const local=new Map(),winEvents=new Map(),docEvents=new Map();const exports={};const doc={visibilityState:'visible',addEventListener:(n,f)=>docEvents.set(n,f),removeEventListener:n=>docEvents.delete(n)};
vm.runInNewContext(code,{exports,Date:{now:()=>now},Map,Number,JSON,encodeURIComponent,localStorage:{getItem:k=>local.get(k)||null,setItem:(k,v)=>local.set(k,v)},document:doc,window:{addEventListener:(n,f)=>winEvents.set(n,f),removeEventListener:n=>winEvents.delete(n),setInterval:f=>{interval=f;return 1;},clearInterval:()=>{interval=null;}}});
return{api:exports,local,doc,advance:ms=>now+=ms,tick:()=>interval?.(),event:(n,trusted=true)=>winEvents.get(n)?.({isTrusted:trusted}),resume:()=>docEvents.get('visibilitychange')?.()};}
const settle=async()=>{for(let i=0;i<20;i++)await Promise.resolve();};
(async()=>{
 const f=fixture(),token='123|secret-token-never-stored',a=f.api.createSessionActivity('admin',()=> 'https://a.test/api');a.begin(token);
 assert.ok(!JSON.stringify([...f.local]).includes('secret-token'));
 f.advance(29*60000);assert.equal(a.expired(token),false);a.touch(token);f.advance(29*60000);assert.equal(a.expired(token),false);
 f.advance(60000);assert.equal(a.expired(token),true);a.touch(token);assert.equal(a.expired(token),true,'touch cannot revive expired state');
 a.begin(token);const other=f.api.createSessionActivity('admin',()=> 'https://a.test/api');f.advance(1000);other.touch(token);assert.equal(a.lastActivity(token),other.lastActivity(token));other.end(token);assert.equal(a.expired(token),true,'other tabs share the end marker');
 a.begin(token);assert.equal(a.expired(token),false);assert.equal(other.expired(token),false);
 const run=fixture(),activity=run.api.createSessionActivity('staff',()=> 'server');let expires=0,beats=[];activity.begin(token);
 const stop=run.api.startSessionActivity({namespace:'staff',server:()=> 'server',readToken:async()=>token,expire:async()=>{expires++;},heartbeat:async(t,idle)=>{beats.push(idle);}});await settle();assert.deepEqual(beats,[0]);
 run.advance(10*60000);run.tick();await settle();assert.equal(beats.length,1,'timer polling is not activity');
 run.event('pointerdown',false);await settle();assert.equal(beats.length,1,'scripted events are not user activity');
 run.event('keydown');await settle();assert.equal(beats.length,2,'trusted keyboard activity renews');
 run.doc.visibilityState='hidden';run.advance(31*60000);run.doc.visibilityState='visible';run.resume();await settle();assert.equal(expires,1,'suspended app expires on resume');assert.equal(activity.expired(token),true);
 stop();
 const passive=fixture(),p=passive.api.createSessionActivity('client',()=> 'server');p.begin(token);let count=0;const end=passive.api.startSessionActivity({namespace:'client',server:()=> 'server',readToken:async()=>token,expire:async()=>count++,heartbeat:async()=>{}});await settle();passive.advance(30*60000);passive.event('pointerdown');await settle();assert.equal(count,1,'first touch after expiry must not revive the session');end();
 console.log('OK: exact 30-minute boundary, activity renewal, shared tabs, no token secret persisted, polling/synthetic events ignored, background resume and no revival on touch.');
})().catch(e=>{console.error(e);process.exitCode=1;});
