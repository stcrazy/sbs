export const basePath='/sbs';
export const apiPath=(value:string)=>basePath+value;
export const imagePath=(value:string)=>value.startsWith('/api/')?apiPath(value):value;
export const poolUrl=()=>window.location.origin+basePath+'/';
