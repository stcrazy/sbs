import {useEffect,useRef,useState} from 'react';
export default function ScoreboardDigit({value,index,axis}:{value?:number;index:number;axis:'Row'|'Column'}){
 const previous=useRef(value),[revealing,setRevealing]=useState(false);
 useEffect(()=>{const justAssigned=value!==undefined&&previous.current===undefined;previous.current=value;if(!justAssigned)return;setRevealing(true);const timer=setTimeout(()=>setRevealing(false),1050+index*35);return()=>clearTimeout(timer);},[value,index]);
 const waiting=value===undefined,offset=index+(axis==='Row'?10:0),numbers=Array.from({length:11},(_,i)=>(i+(value??offset))%10);
 return <div className={'digit scoreboard-digit '+(waiting?'scoreboard-waiting':revealing?'scoreboard-revealing':'scoreboard-assigned')} role="img" aria-label={`${axis} ${index+1}: ${waiting?'numbers not assigned yet':value}`} title={waiting?'Rolling preview — numbers have not been assigned.':`${axis} ${index+1}: ${value}`}>
  <span className="scoreboard-static" aria-hidden="true">{value??'?'}</span>
  {(waiting||revealing)&&<span className="scoreboard-window" aria-hidden="true"><span className="scoreboard-reel" style={{animationDelay:waiting?`${-offset*1.37}s`:'0s',animationDuration:waiting?`${18+(offset%3)*2}s`:`${1050+index*35}ms`}}>{numbers.map((n,i)=><span key={i}>{n}</span>)}</span></span>}
  {waiting&&<span className="scoreboard-indicator" aria-hidden="true"/>}
 </div>;
}
