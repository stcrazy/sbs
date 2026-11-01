import assert from 'node:assert/strict';
const origin='http://127.0.0.1:8091',admin=new Map(),alice=new Map(),bob=new Map();
async function request(jar,path,body,game=''){
 const r=await fetch(origin+'/sbs/api/'+path+(game?'?game='+game:''),{method:body?'POST':'GET',headers:{Cookie:[...jar].map(x=>x.join('=')).join('; '),...(body?{Origin:origin,'Content-Type':'application/json'}:{})},...(body?{body:JSON.stringify(body)}:{})});
 for(const cookie of r.headers.getSetCookie()){const [key,value]=cookie.split(';')[0].split('=');if(value&&!/Max-Age=0(?:;|$)/i.test(cookie))jar.set(key,value);else jar.delete(key);}return {status:r.status,data:await r.json()};
}
async function ok(...args){const r=await request(...args);assert.equal(r.status,200,JSON.stringify(r.data));return r.data;}
await ok(admin,'auth',{action:'admin',password:'local-test-password-123'});
const game=(await ok(admin,'games',{action:'create',name:'Credit request fixture',year:2026})).id;
for(const [name,phone] of [['Alice','4155550101'],['Bob','4155550102']])await ok(admin,'pool',{action:'invite',name,phone},game);
const players=(await ok(admin,'pool',null,game)).pool.players;
await ok(alice,'auth',{action:'player',phone:'4155550101'},game);await ok(bob,'auth',{action:'player',phone:'4155550102'},game);
assert.ok([401,403].includes((await request(alice,'requests',null,game)).status));
await ok(alice,'pool',{action:'requestCredits',credits:4,note:'Please add four',playerId:players[1].id},game);
assert.equal((await request(alice,'pool',{action:'requestCredits',credits:2},game)).status,400);
await ok(bob,'pool',{action:'requestCredits',credits:2},game);
const alicePool=(await ok(alice,'pool',null,game)).pool;assert.equal(alicePool.creditRequests.length,1);assert.equal(alicePool.creditRequests[0].playerId,players[0].id);
const id=alicePool.creditRequests[0].id;
const replies=await Promise.all([request(admin,'pool',{action:'resolveRequest',requestId:id,approve:true},game),request(admin,'pool',{action:'resolveRequest',requestId:id,approve:true},game)]);assert.deepEqual(replies.map(r=>r.status).sort(),[200,400]);
const after=await ok(alice,'pool',null,game);assert.equal(after.me.balance,4);assert.equal(after.pool.ledger.length,1);assert.equal(after.pool.ledger[0].dollars,0);
assert.ok((await ok(admin,'requests')).requests.some(r=>r.game===game&&r.credits===2));
assert.ok([401,403].includes((await request(alice,'push')).status));
assert.equal((await request(admin,'push',{action:'subscribe',subscription:{endpoint:'http://127.0.0.1/private',keys:{}}})).status,400);
const config=await ok(admin,'push');assert.equal(config.available,true);assert.match(config.publicKey,/^[A-Za-z0-9_-]{87}$/);assert.equal((await ok(admin,'push')).publicKey,config.publicKey);
console.log('Credit request HTTP checks passed: owner privacy, one pending request, no self-approval, race-safe approval, ledger audit, global request list, and push access controls.');
