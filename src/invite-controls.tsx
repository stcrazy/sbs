import {useState} from 'react';
import {Clipboard,Share2,MessageCircle} from 'lucide-react';
import type {Player} from './pool-model';
import {currentGame} from './paths';
import {iphoneSmsLink} from './text-links';
export default function InviteControls({player,gameName,flash,available,price}:{player:Player;gameName:string;flash:(text:string)=>void;available:number;price:number}){
 const [open,setOpen]=useState(false);
 const url=new URL('/sbs/',window.location.origin);url.searchParams.set('game',currentGame());url.searchParams.set('phone',player.phone||'');
 const link=url.href,message='Hi '+player.name+'! Join '+gameName+'. You have '+available+' picking credits. Squares are $'+price.toFixed(2)+' each; you only owe for squares picked. This link signs you in automatically.\n'+link;
 async function share(){if(navigator.share){try{await navigator.share({title:gameName,text:'Hi '+player.name+'! Your game link signs you in automatically.',url:link});return;}catch(e){if(e instanceof Error&&e.name==='AbortError')return;}}setOpen(!open);}
 return <div className="share-player-link"><button className="outline" disabled={!player.phone} onClick={share}><Share2 size={14}/> Share link</button>{open&&<div className="share-options"><a className="primary" href={iphoneSmsLink(player.phone!,message)} target="_top"><MessageCircle size={14}/> Text link</a><button className="text-link" onClick={async()=>{try{await navigator.clipboard.writeText(link);flash('Player sign-in link copied.');}catch{flash('Select and copy the link below.');}}}><Clipboard size={13}/> Copy link</button><input aria-label={'Sign-in link for '+player.name} value={link} readOnly onFocus={e=>e.currentTarget.select()}/></div>}</div>;
}
