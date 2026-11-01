self.addEventListener('install',event=>{self.skipWaiting();});
self.addEventListener('activate',event=>event.waitUntil(self.clients.claim()));
// Do not cache boards, ledgers, sign-ins, or player details.
self.addEventListener('push',event=>event.waitUntil((async()=>{
 let data={};try{data=event.data?.json()||{};}catch{}
 const url=new URL(data.url||'/sbs/?admin=1',self.location.origin);
 const target=url.origin===self.location.origin&&url.pathname.startsWith('/sbs/')?url.href:'/sbs/?admin=1';
 await self.registration.showNotification(data.title||'Football Pool credit request',{body:data.body||'Open the app to review player requests.',icon:'/sbs/app-icon-192.png',badge:'/sbs/app-icon-192.png',tag:'sbs-credit-requests',data:{url:target}});
 if(Number.isInteger(data.badge)&&data.badge>=0)try{if(data.badge)await self.navigator.setAppBadge?.(data.badge);else await self.navigator.clearAppBadge?.();}catch{}
})()));
self.addEventListener('notificationclick',event=>{event.notification.close();event.waitUntil((async()=>{
 const url=event.notification.data?.url||'/sbs/?admin=1';const windows=await self.clients.matchAll({type:'window',includeUncontrolled:true});
 const client=windows.find(c=>new URL(c.url).pathname.startsWith('/sbs/'));if(client){await client.navigate(url);await client.focus();}else await self.clients.openWindow(url);
})());});
