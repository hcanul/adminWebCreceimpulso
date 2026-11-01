import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import ts from '../apps/client-app/node_modules/typescript/lib/typescript.js';
for(const app of ['client-app','staff-app']){
 const source=ts.transpileModule(await readFile(new URL(`../apps/${app}/src/services/session.ts`,import.meta.url),'utf8'),{compilerOptions:{module:ts.ModuleKind.ESNext,target:ts.ScriptTarget.ES2022}}).outputText;
 async function load(native, disk=new Map(), browser=new Map()){
  let server='https://first.example/api';const storage={get:async k=>disk.get(k)||null,set:async(k,v,...options)=>{assert.equal(options[1],false);disk.set(k,v);},remove:async k=>disk.delete(k)};
  const deps={'@capacitor/core':{Capacitor:{isNativePlatform:()=>native}},'@aparajita/capacitor-secure-storage':{SecureStorage:storage,KeychainAccess:{whenUnlockedThisDeviceOnly:1}},'./server-settings':{getServerAddress:()=>server}};
  const context=vm.createContext({sessionStorage:{getItem:k=>browser.get(k)||null,setItem:(k,v)=>browser.set(k,v),removeItem:k=>browser.delete(k)},Date,encodeURIComponent});
  const module=new vm.SourceTextModule(source,{context});await module.link(s=>new vm.SyntheticModule(Object.keys(deps[s]),function(){for(const[k,v]of Object.entries(deps[s]))this.setExport(k,v)},{context}));await module.evaluate();return {...module.namespace,setServer:s=>server=s,disk,browser};
 }
 test(`${app}: native session survives app restart without web storage`,async()=>{const a=await load(true);await a.writeSession('secret');assert.equal(a.browser.size,0);const b=await load(true,a.disk);assert.equal(await b.readSession(),'secret');});
 test(`${app}: isolate server, expired record and stale unauthorized response`,async()=>{const a=await load(true);await a.writeSession('old');a.setServer('https://second.example/api');assert.equal(await a.readSession(),null);a.setServer('https://first.example/api');await a.writeSession('new');assert.equal(await a.removeSession('old'),false);assert.equal(await a.readSession(),'new');for(const v of a.disk.values())v.expiresAt=0;assert.equal(await a.readSession(),null);assert.equal(a.disk.size,0);});
 test(`${app}: removal remains removed after restart; ordered writes`,async()=>{const a=await load(true);await Promise.all([a.writeSession('a'),a.removeSession(),a.writeSession('b')]);assert.equal(await a.readSession(),'b');await a.removeSession();assert.equal(await(await load(true,a.disk)).readSession(),null);});
 test(`${app}: browser uses session storage and never native fallback`,async()=>{const a=await load(false);await a.writeSession('browser');assert.equal(a.disk.size,0);assert.equal(await a.readSession(),'browser');await a.removeSession();assert.equal(await a.readSession(),null);});
}
