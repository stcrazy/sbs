/** Store an optional phone in E.164 form. Unprefixed ten-digit numbers use +1. */
export function normalizePhone(value:string):string {
  const raw=value.trim();
  if(!raw)return '';
  if(raw.length>40||!/^[+\d\s().-]+$/.test(raw))throw new Error('Enter a valid phone number, including country code for numbers outside the U.S.');
  let phone=raw.replace(/[\s().-]/g,'');
  if(/^\d{10}$/.test(phone))phone='+1'+phone;
  else if(/^1\d{10}$/.test(phone))phone='+'+phone;
  if(!/^\+[1-9]\d{7,14}$/.test(phone))throw new Error('Enter a 10-digit U.S. phone number, or include + and a country code.');
  return phone;
}
export function displayPhone(value:string):string {
  const phone=normalizePhone(value);
  return /^\+1\d{10}$/.test(phone)?`(${phone.slice(2,5)}) ${phone.slice(5,8)}-${phone.slice(8)}`:phone;
}
/** iPhone's commonly supported body extension; keep a copy/paste fallback. */
export function iphoneSmsLink(phone:string,message:string):string {
  const recipient=normalizePhone(phone);
  if(!recipient)throw new Error('A phone number is required.');
  return `sms:${recipient}&body=${encodeURIComponent(message)}`;
}
