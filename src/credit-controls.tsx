import {useState} from 'react';
import type {Pool} from './pool-model';
type Act=(payload:unknown,message?:string)=>Promise<boolean>;
export function AddPlayerCredits({playerId,busy,act}:{playerId:string;busy:boolean;act:Act}){
 const [amount,setAmount]=useState(5);
 return <div className="row-credit-control"><input aria-label="Credits to issue" type="number" min="1" max="1000" step="1" value={amount} disabled={busy} onChange={e=>setAmount(Number(e.target.value))}/><button className="outline" disabled={busy||!Number.isInteger(amount)||amount<1||amount>1000} onClick={()=>act({action:'credit',playerId,credits:amount,cents:0,note:'Picking credits issued from player list'},'Credits issued. No payment was recorded.')}>Issue credits</button></div>;
}
export function RequestCredits({pool,playerId,busy,act}:{pool:Pool;playerId:string;busy:boolean;act:Act}){
 const [amount,setAmount]=useState(5),[note,setNote]=useState('');
 const requests=(pool.creditRequests||[]).filter(r=>r.playerId===playerId),pending=requests.find(r=>r.status==='pending');
 return <section className="request-credits"><h3>Request more credits</h3><p>Ask the organizer for picking credits. You only owe for squares you pick.</p>{pending?<div className="notice" role="status">Your request for {pending.credits} credits is waiting for the organizer.</div>:<form onSubmit={async e=>{e.preventDefault();if(await act({action:'requestCredits',credits:amount,note},'Request sent to the organizer.'))setNote('');}}><label>Credits requested<input type="number" min="1" max="100" step="1" value={amount} onChange={e=>setAmount(Number(e.target.value))} required/></label><label>Note (optional)<input maxLength={200} value={note} onChange={e=>setNote(e.target.value)}/></label><button className="primary" disabled={busy||!!pool.rows.length}>Send credit request</button></form>}{requests.slice().reverse().slice(0,5).map(r=><p className="muted" key={r.id}>{r.credits} credits requested · {r.status}{r.granted?` (${r.granted} issued)`:''}</p>)}</section>;
}
export function CreditRequests({pool,busy,act}:{pool:Pool;busy:boolean;act:Act}){
 const pending=(pool.creditRequests||[]).filter(r=>r.status==='pending');
 return <section className="content-panel"><h3>Credit requests <span className="badge">{pending.length}</span></h3>{!pending.length?<p>No pending requests for this game.</p>:pending.map(r=><article className="credit-request" key={r.id}><div><strong>{pool.players.find(p=>p.id===r.playerId)?.name||'Player'}</strong><p>{r.credits} credits requested · {new Date(r.at).toLocaleString()}</p>{r.note&&<p>{r.note}</p>}</div><div className="request-actions"><button className="primary" disabled={busy} onClick={()=>act({action:'resolveRequest',requestId:r.id,approve:true},'Request approved and credits issued.')}>Approve {r.credits} credits</button><button className="outline" disabled={busy} onClick={()=>act({action:'resolveRequest',requestId:r.id,approve:false},'Request declined.')}>Decline</button></div></article>)}</section>;
}
