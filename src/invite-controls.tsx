import {useState} from 'react';
import {Clipboard,Link,MessageCircle} from 'lucide-react';
import type {Player} from './pool-model';
import {apiPath} from './paths';
import {iphoneSmsLink} from './text-links';
export default function InviteControls({player,flash}:{player:Player;flash:(text:string)=>void}){
 const [link,setLink]=useState(''),[busy,setBusy]=useState(false);
 const message=`Hi ${player.name}! Join Shawn’s Football Pool using your private invitation:\n${link}\n\nEnter your phone number to sign in. Please keep this link private.`;
 async function create(){setBusy(true);try{const r=await fetch(apiPath('/api/invite'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({playerId:player.id})}),d=await r.json();if(!r.ok)throw Error(d.error);setLink(d.url);flash('Private invitation created. Earlier links and sessions for this player are revoked.')}catch(e){flash(e instanceof Error?e.message:'Could not create invitation.')}finally{setBusy(false)}}
 async function copy(){try{await navigator.clipboard.writeText(message);flash('Private invitation message copied.')}catch{flash('Select and copy the invitation link shown below.')}}
 return <div className="invite-controls"><button className="outline" disabled={busy||!player.phone} onClick={create}><Link size={14}/>{busy?'Creating…':link?'Replace invite':'Create invite'}</button>{link&&<><a className="primary" href={iphoneSmsLink(player.phone!,message)} target="_top"><MessageCircle size={14}/> Text invitation</a><button className="text-link" onClick={copy}><Clipboard size={13}/> Copy invite</button><input className="private-invite-link" aria-label={'Private invitation for '+player.name} value={link} readOnly onFocus={e=>e.currentTarget.select()}/></>}<small>{player.phone?'Creating a new invite revokes previous links and sign-ins.':'Add a phone number first.'}</small></div>;
}
