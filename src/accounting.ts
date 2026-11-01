import type {Pool} from './pool-model';
export function account(p:Pool,id:string){
 const squares=Object.values(p.squares).filter(s=>s.playerId===id).length;
 const receivedCents=p.ledger.filter(e=>e.playerId===id).reduce((sum,e)=>sum+Math.round(e.dollars*100),0);
 const chargedCents=squares*Math.round(p.settings.price*100);
 return {squares,received:receivedCents/100,charged:chargedCents/100,owed:Math.max(0,chargedCents-receivedCents)/100,overpaid:Math.max(0,receivedCents-chargedCents)/100};
}
