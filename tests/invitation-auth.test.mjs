import {test} from 'node:test';
import assert from 'node:assert/strict';
import {createInvitation,verifyInvitation,createPlayerSession,verifyPlayerSession,invitationUrl,INVITE_LIFETIME_MS,SESSION_LIFETIME_MS} from '../src/invitation-auth.ts';
const now = 1800000000000;
test('login requires both the private invitation and its assigned phone number',async()=>{
  const {token,grant}=await createInvitation('player-a','(415) 555-0123',0,now);
  assert.equal(grant.phone,'+14155550123');
  assert.notEqual(grant.tokenHash,token);
  assert.equal(await verifyInvitation(grant,token,'4155550123',now),true);
  assert.equal(await verifyInvitation(grant,token,'4155550124',now),false);
  assert.equal(await verifyInvitation(grant,'','4155550123',now),false);
  assert.equal(await verifyInvitation(grant,token,'invalid',now),false);
});
test('new invitations have independent unpredictable tokens and expire at the deadline',async()=>{
  const a=await createInvitation('a','4155550123',0,now),b=await createInvitation('a','4155550123',0,now);
  assert.notEqual(a.token,b.token);assert.notEqual(a.grant.tokenHash,b.grant.tokenHash);
  assert.equal(await verifyInvitation(a.grant,a.token,'4155550123',now+INVITE_LIFETIME_MS),false);
});
test('reissuing an invitation invalidates the old link and sessions',async()=>{
  const old=await createInvitation('a','4155550123',0,now);
  const {token:cookie,session}=await createPlayerSession(old.grant,now);
  assert.equal(await verifyPlayerSession(session,old.grant,cookie,now),true);
  const replacement=await createInvitation('a','4155550123',old.grant.version,now+1);
  assert.equal(await verifyInvitation(replacement.grant,old.token,'4155550123',now+1),false);
  assert.equal(await verifyPlayerSession(session,replacement.grant,cookie,now+1),false);
});
test('sessions are device secrets, expire, and cannot cross player identities',async()=>{
  const a=await createInvitation('a','4155550123',0,now),b=await createInvitation('b','4155550124',0,now);
  const {token,session}=await createPlayerSession(a.grant,now);
  assert.equal(await verifyPlayerSession(session,a.grant,token,now+SESSION_LIFETIME_MS),false);
  assert.equal(await verifyPlayerSession(session,b.grant,token,now),false);
  assert.equal(await verifyPlayerSession(session,a.grant,a.token,now),false);
});
test('private links include the stable game code and random login key',async()=>{
  const {token}=await createInvitation('a','4155550123',0,now);
  const link=new URL(invitationUrl('https://thetrenhailes.com/sbs/',token));
  assert.equal(link.pathname,'/sbs/');assert.equal(link.searchParams.get('game'),'2026_01');assert.equal(link.searchParams.get('login'),token);assert.equal(link.hash,'');
});
