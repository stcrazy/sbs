export type Theme='classic'|'stadium'|'clean'|'retro';
const styles:{id:Theme;name:string;description:string}[]=[
 {id:'classic',name:'Club Classic',description:'Forest green, warm cream, and gold. Your original look.'},
 {id:'stadium',name:'Stadium',description:'Midnight navy and electric lime, with a cool silver board.'},
 {id:'clean',name:'Clean & Bright',description:'Crisp white, clear blue, and a simple modern finish.'},
 {id:'retro',name:'Vintage Game Day',description:'Warm paper, burnt orange, and old-school scoreboard charm.'},
];
export default function StyleChoices({saved,preview,onPreview,busy,act}:{saved:Theme;preview:Theme|null;onPreview:(v:Theme|null)=>void;busy:boolean;act:(v:unknown,m?:string)=>Promise<boolean>}){
 const selected=preview||saved;
 return <section className="content-panel style-panel"><h3>Choose your game’s style</h3><p className="muted">Tap a style to preview it. Save to show it to every player in this game.</p><div className="style-choices">{styles.map(style=><button key={style.id} type="button" className={'style-choice '+(selected===style.id?'selected':'')} aria-pressed={selected===style.id} onClick={()=>onPreview(style.id)}><div className={'style-sample sample-'+style.id} aria-hidden="true"><div className="sample-heading"><span>GAME DAY</span><b>01</b></div><div className="sample-board">{Array.from({length:20},(_,i)=><i key={i} className={i%7===0?'sample-picked':''}>{i<5?i:i%7===0?'★':''}</i>)}</div><div className="sample-payout">Q1 · Q2 · Q3 · FINAL</div></div><div className="style-description"><strong>{style.name}</strong><span className="style-status">{saved===style.id?'Saved style':selected===style.id?'Previewing':''}</span><p>{style.description}</p></div></button>)}</div><div className="style-actions"><button className="primary" disabled={busy||selected===saved} onClick={async()=>{if(await act({action:'theme',theme:selected},'Game style saved'))onPreview(null)}}>Save style</button>{preview&&preview!==saved&&<button className="outline" onClick={()=>onPreview(null)}>Cancel preview</button>}</div><p className="muted">Each game has its own style. Printed boards keep a clear, ink-friendly layout.</p></section>;
}
