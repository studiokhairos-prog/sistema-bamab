'use strict';

const CACHE_PREFIX='bamab-pwa-';
const CACHE_NAME='bamab-pwa-v2';
const SCOPE_URL=new URL(self.registration.scope);
const OFFLINE_URL=new URL('offline.html',SCOPE_URL).toString();
const PRECACHE=[
  OFFLINE_URL,
  new URL('manifest.webmanifest',SCOPE_URL).toString(),
  new URL('assets/bamab-app-192.png',SCOPE_URL).toString(),
  new URL('assets/bamab-app-maskable-512.png',SCOPE_URL).toString()
];

self.addEventListener('install',event=>{
  event.waitUntil(caches.open(CACHE_NAME).then(cache=>cache.addAll(PRECACHE)).then(()=>self.skipWaiting()));
});

self.addEventListener('activate',event=>{
  event.waitUntil(
    caches.keys()
      .then(keys=>Promise.all(keys.filter(key=>key.startsWith(CACHE_PREFIX)&&key!==CACHE_NAME).map(key=>caches.delete(key))))
      .then(()=>self.clients.claim())
  );
});

self.addEventListener('fetch',event=>{
  const request=event.request;
  if(request.method!=='GET') return;

  const url=new URL(request.url);
  if(url.origin!==SCOPE_URL.origin) return;

  const relativePath=url.pathname.slice(SCOPE_URL.pathname.length).replace(/^\/+/, '');
  const privateArea=/^(admin|instrutor|uploads|data)\//i.test(relativePath);
  if(privateArea) return;

  if(request.mode==='navigate'){
    event.respondWith(fetch(request).catch(()=>caches.match(OFFLINE_URL)));
    return;
  }

  const isStatic=/\.(?:css|js|png|jpe?g|webp|gif|svg|ico|woff2?|webmanifest)$/i.test(url.pathname);
  if(!isStatic) return;

  event.respondWith(
    caches.match(request).then(cached=>{
      const refreshed=fetch(request).then(response=>{
        if(response.ok&&response.type==='basic'){
          const copy=response.clone();
          caches.open(CACHE_NAME).then(cache=>cache.put(request,copy));
        }
        return response;
      }).catch(()=>null);
      if(cached){event.waitUntil(refreshed);return cached;}
      return refreshed.then(response=>response||new Response('',{status:503,statusText:'Offline'}));
    })
  );
});
