export const basePath='/sbs';
export const currentGame=()=>new URLSearchParams(window.location.search).get('game')||'';
export const apiPath=(value:string)=>{const game=currentGame();return basePath+value+(game?(value.includes('?')?'&':'?')+'game='+encodeURIComponent(game):'')};
export const imagePath=(value:string)=>value.startsWith('/api/')?apiPath(value):value;
export const poolUrl=(game=currentGame())=>window.location.origin+basePath+'/'+(game?'?game='+encodeURIComponent(game):'');
