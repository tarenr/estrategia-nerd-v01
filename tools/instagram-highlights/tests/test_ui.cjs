'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
const context = {module:{exports:{}}};
require('node:vm').runInNewContext(fs.readFileSync(path.join(root, 'public/assets/js/admin-instagram-highlights.js'),'utf8'), context);
const { Operation } = context.module.exports;
test('wizard allows preparation without connection, blocks skipping review and busy navigation', () => {
  const wizard = new context.module.exports.Wizard();
  assert.equal(wizard.step,1);
  wizard.go(2); assert.equal(wizard.step,2);
  assert.throws(()=>wizard.go(3), /Revise/);
  assert.throws(()=>wizard.go(1,{uploads:1}), /Aguarde/);
  wizard.go(3,{reviewed:true}); assert.equal(wizard.step,3);
  wizard.go(2); assert.equal(wizard.step,2);
});
test('back and forward preserves draft; a changed review never publishes old content', () => {
  const wizard = new context.module.exports.Wizard(); let ids=0;
  const op = new Operation(()=>{throw Error('No request expected');},storage(),()=>String(++ids).padStart(32,'0'));
  const draft=content(); wizard.go(2); const first=op.review(draft); wizard.go(3,{reviewed:true});
  wizard.go(1); wizard.go(2); assert.equal(draft.items[0].link,'https://example.com');
  draft.items[0].link='https://example.com/edited'; const second=op.review(draft);
  assert.notEqual(first.job_id,second.job_id); assert.equal(second.items[0].link,draft.items[0].link);
});
test('stored session alone is not enough without runtime, and progress names next action', () => {
  const {canPublish,progressLabel}=context.module.exports;
  assert.equal(canPublish({runtime_ready:true,session_stored:false}),false);
  assert.equal(canPublish({runtime_ready:false,session_stored:true}),false);
  assert.equal(canPublish({runtime_ready:true,session_stored:true}),true);
  assert.equal(progressLabel({status:'ready',next_action:'upload',completed_steps:1,items:[{asset:'a'},{asset:'b'}]}),'Publicando story 2 de 2');
  assert.equal(progressLabel({status:'unknown',next_action:'upload',items:[]}), 'Confira o resultado no Instagram antes de continuar');
});
const id = 'a'.repeat(32);
const storage = () => { const values = new Map(); return { getItem:k=>values.get(k), setItem:(k,v)=>values.set(k,v), removeItem:k=>values.delete(k) }; };
const content = () => ({account:'estrategia_nerd',items:[{asset:'b'.repeat(32),link:'https://example.com'}],highlight:{id:'',title:'PC',cover_asset:''}});
test('review freezes nested content and retains ID for unchanged review', () => {
  let ids = 0; const op = new Operation(async()=>{}, storage(), ()=>{ ids++; return id; });
  const draft = content(); const reviewed = op.review(draft); draft.items[0].link = 'https://changed.example';
  assert.equal(reviewed.items[0].link, 'https://example.com');
  assert.throws(()=> { reviewed.items[0].link='changed'; }, /read only/);
  assert.equal(op.review(content()).job_id,id); assert.equal(ids,1);
});
test('lost step response performs one local lookup and no automatic mutation retry', async () => {
  const calls=[];
  const op = new Operation(async (route, payload) => {
    calls.push(route);
    if(route==='/prepare') return {job:{id,status:'ready'}};
    if(route==='/step') throw new Error('network');
    return {job:{id,status:'unknown'}};
  },storage(),()=>id);
  op.review(content()); await assert.rejects(op.publish(), /network/);
  assert.deepEqual(calls, ['/prepare','/step','/job?id='+id]);
  assert.equal(op.job.status,'unknown'); assert.equal(op.attempted,true);
  assert.throws(()=>op.review(content()), /operação/);
  await assert.rejects(op.publish(), /conferida/);
  assert.equal(calls.filter(x=>x==='/step').length,1);
});
test('reload restores ID and recovery only reads local state', async () => {
  const saved=storage(); saved.setItem('ih:estrategia_nerd:pending',id);
  const calls=[]; const op=new Operation(async route=>{ calls.push(route); return {job:{id,status:'ready'}}; },saved,()=>{throw Error('new id');});
  assert.equal(op.id,id); await op.recover(); assert.deepEqual(calls,['/job?id='+id]);
});
test('missing unstarted revision unlocks editing only on explicit not-found', async () => {
  const saved=storage(); saved.setItem('ih:estrategia_nerd:pending', id);
  const op=new Operation(async()=>{ const error=new Error('not found'); error.code='JOB_NOT_FOUND'; throw error; },saved,()=>id);
  await assert.rejects(op.recover()); assert.equal(op.attempted,false); assert.equal(op.id,'');
  assert.equal(saved.getItem(op.key),undefined);
});
test('all literal DOM IDs referenced by script exist in the view', () => {
  const source=fs.readFileSync(path.join(root,'public/assets/js/admin-instagram-highlights.js'),'utf8');
  const view=fs.readFileSync(path.join(root,'app/Views/admin/instagram-highlights/index.php'),'utf8');
  for(const match of source.matchAll(/byId\('([^']+)'\)/g)) assert.ok(view.includes('id="'+match[1]+'"'), 'Missing '+match[1]);
  assert.ok(!source.includes('.innerHTML'));
  assert.ok(!view.includes('foi ou não'));
});
