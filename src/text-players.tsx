'use client';
import {useEffect,useState} from 'react';
import {Clipboard,MessageCircle,Phone,Users} from 'lucide-react';
import type {Player} from './pool-model';
import {displayPhone,iphoneSmsLink} from './text-links';
import {poolUrl} from './paths';

type Props={players:Player[];onContacts:()=>void;flash:(message:string)=>void};
const draftKey='squares-club:organizer-text-draft';
export function TextPlayers({players,onContacts,flash}:Props){
  const [message,setMessage]=useState('');
  const [selected,setSelected]=useState<string[]>(()=>players.filter(p=>p.phone).map(p=>p.id));
  const contacts=players.filter(p=>p.phone),missing=players.length-contacts.length;
  const chosen=contacts.filter(p=>selected.includes(p.id));
  useEffect(()=>{try{setMessage(sessionStorage.getItem(draftKey)||'')}catch{}},[]);
  function changeMessage(value:string){setMessage(value);try{sessionStorage.setItem(draftKey,value)}catch{}}
  async function copyMessage(){try{await navigator.clipboard.writeText(message);flash('Message copied. Paste it in Messages if the text is not filled in.')}catch{flash('Copy unavailable here. Select the message above and copy it manually.')}}
  return <section className="content-panel texting-panel">
    <span className="eyebrow">FROM YOUR IPHONE, IN YOUR OWN WORDS</span>
    <h2>Text your players.</h2>
    <p>Write once, then open an individual text for each player. Messages uses your iPhone’s normal service. You review and tap Send yourself.</p>
    <div className="texting-compose">
      <label htmlFor="player-text-message">Your message</label>
      <textarea id="player-text-message" value={message} maxLength={1500} rows={5} onChange={e=>changeMessage(e.target.value)} placeholder="Hi everyone! Here’s the latest on our football pool…"/>
      <div className="texting-toolbar"><small>{message.length}/1,500 characters · Draft kept in this browser tab</small><button className="outline" disabled={!message.trim()} onClick={copyMessage}><Clipboard size={15}/> Copy message</button></div>
      <button className="text-link" onClick={()=>changeMessage(message+(message&&!message.endsWith('\n')?'\n\n':'')+poolUrl())}>Add pool link</button>
    </div>
    <div className="texting-selection"><div><h3><Users size={18}/> Choose recipients</h3><p className="muted">{chosen.length} selected · {contacts.length} players with phone numbers</p></div><div className="texting-selection-actions"><button className="outline" disabled={!contacts.length} onClick={()=>setSelected(contacts.map(p=>p.id))}>Select all</button><button className="outline" disabled={!selected.length} onClick={()=>setSelected([])}>Clear selection</button></div></div>
    {contacts.length>0?<div className="text-contact-list">{contacts.map(player=>{const checked=selected.includes(player.id);return <div className="text-contact" key={player.id}><label className="text-contact-check"><input type="checkbox" checked={checked} onChange={e=>setSelected(ids=>e.target.checked?[...ids,player.id]:ids.filter(id=>id!==player.id))}/><span><strong>{player.name}</strong><small>{displayPhone(player.phone!)}</small></span></label>{checked&&message.trim()?<a className="primary" href={iphoneSmsLink(player.phone!,message)} target="_top" aria-label={'Text '+player.name}><MessageCircle size={16}/> Text {player.name}</a>:<button className="outline" disabled><MessageCircle size={16}/> {checked?'Write a message first':'Not selected'}</button>}</div>})}</div>:<div className="empty"><Phone/><h3>Add player phone numbers to get started</h3><p>Save them in Players & ledger, then return here to prepare your texts.</p></div>}
    {missing>0&&<div className="notice">{missing} {missing===1?'player has':'players have'} no phone number yet. <button className="text-link" onClick={onContacts}>Add numbers in Players & ledger</button></div>}
    {!players.length&&<button className="outline" onClick={onContacts}>Add your first player</button>}
    <div className="texting-help"><strong>Use Safari on your iPhone</strong><p>Tap a player’s Text button, review their number and message in Messages, then tap Send. Return here for the next player. Each link contains only that player’s number.</p><p>If your browser opens Messages without the text, use Copy message and paste it. This site cannot tell whether you sent or delivered a text.</p></div>
  </section>;
}

export function PhoneEditor({player,busy,act}:{player:Player;busy:boolean;act:(payload:unknown,message?:string)=>Promise<boolean>}){
  return <form className="phone-editor" key={player.phone||'empty'} onSubmit={async e=>{e.preventDefault();const phone=new FormData(e.currentTarget).get('phone');await act({action:'phone',playerId:player.id,phone},'Player phone number saved')}}><input name="phone" type="tel" autoComplete="tel" inputMode="tel" aria-label={'Phone for '+player.name} placeholder="(555) 555-0123" defaultValue={player.phone||''} maxLength={40}/><button className="outline" disabled={busy} aria-label={'Save phone for '+player.name}>Save</button></form>;
}
