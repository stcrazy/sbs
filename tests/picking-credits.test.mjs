import test from 'node:test';
import assert from 'node:assert/strict';
import {account} from '../src/accounting.ts';
import {parsePastedContact,parseContactCards} from '../src/contact-input.ts';
test('only picked squares are charged and released squares reduce the amount owed',()=>{
 const p={settings:{price:25},squares:{},ledger:[{playerId:'a',credits:5,dollars:0}]};
 assert.equal(account(p,'a').owed,0);
 p.squares[0]={playerId:'a'};p.squares[1]={playerId:'a'};assert.equal(account(p,'a').owed,50);
 p.ledger.push({playerId:'a',credits:0,dollars:25});assert.equal(account(p,'a').owed,25);
 delete p.squares[0];assert.equal(account(p,'a').owed,0);
 delete p.squares[1];assert.equal(account(p,'a').overpaid,25);assert.equal(account(p,'a').received,25);
});
test('paste accepts common name/phone formats and asks for one number',()=>{
 assert.deepEqual(parsePastedContact('Jane Smith\n(555) 555-0123'),{name:'Jane Smith',phone:'+15555550123'});
 assert.deepEqual(parsePastedContact('Name: Jane Smith\nMobile: +1 555 555 0123'),{name:'Jane Smith',phone:'+15555550123'});
 assert.throws(()=>parsePastedContact('Jane\n5555550123\n5555550124'));
 assert.throws(()=>parsePastedContact('Jane'));
});
test('Apple contact cards unfold lines and offer multiple numbers without saving',()=>{
 assert.deepEqual(parseContactCards('BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Jane \'Lucky\'\r\n Smith\r\nitem1.TEL;type=CELL:(555) 555-0123\r\nTEL;VALUE=uri:tel:+15555550124\r\nEND:VCARD'),[{name:"Jane 'Lucky'Smith",phone:'(555) 555-0123'},{name:"Jane 'Lucky'Smith",phone:'+15555550124'}]);
 assert.equal(parseContactCards('not a contact').length,0);
});
