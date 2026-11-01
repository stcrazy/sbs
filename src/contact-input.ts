import {normalizePhone} from './text-links.ts';
export type ContactCard={name:string;phone:string};
const clean=(s:string)=>s.replace(/\\n/gi,' ').replace(/\\([,;\\])/g,'$1').trim();
export function parseContactCards(input:string):ContactCard[]{
 const result:ContactCard[]=[];
 for(const block of input.replace(/\r\n?/g,'\n').replace(/\n[ \t]/g,'').split(/BEGIN:VCARD/i).slice(1)){
  let name='',fallback='';const phones:string[]=[];
  for(const line of block.split('\n')){const at=line.indexOf(':');if(at<0)continue;const key=line.slice(0,at).split(';')[0].split('.').pop()?.toUpperCase(),value=line.slice(at+1).trim();
   if(key==='FN')name=clean(value);if(key==='N'){const n=value.split(';');fallback=clean([n[1],n[2],n[0]].filter(Boolean).join(' '));}
   if(key==='TEL')phones.push(clean(value.replace(/^tel:/i,'')).split(';')[0]);
  }
  for(const phone of [...new Set(phones)])if(name||fallback)result.push({name:(name||fallback).slice(0,50),phone});
 }
 return result;
}

export function parsePastedContact(text:string):ContactCard {
 if(text.length>5000)throw Error('Paste one contact at a time.');
 if(/BEGIN:VCARD/i.test(text)){const cards=parseContactCards(text);if(cards.length!==1)throw Error('Paste a single contact with one phone number, or use Import contact card to choose a number.');return cards[0];}
 const matches=[...text.matchAll(/\+?\(?\d[\d ().-]{6,}\d\)?/g)].map(m=>({raw:m[0],index:m.index!})).filter(m=>{try{return !!normalizePhone(m.raw)}catch{return false}});
 if(matches.length!==1)throw Error('Include one phone number. If there are several, paste only the number you want.');
 const match=matches[0],name=(text.slice(0,match.index)+' '+text.slice(match.index+match.raw.length)).replace(/\b(?:name|phone|mobile|cell|tel|telephone|home|work)\s*:/gi,' ').replace(/[:|,;\r\n]+/g,' ').replace(/\s+/g,' ').trim();
 if(!name||name.length>50)throw Error('Include a name (up to 50 characters) and one phone number.');
 return {name,phone:normalizePhone(match.raw)};
}
