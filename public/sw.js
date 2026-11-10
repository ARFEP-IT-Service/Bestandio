// Bestandio service worker.
// Purpose: make the web panel installable as an app. It caches ONLY static files below /assets/
// (CSS, JS, icons). Pages, device data and anything behind the login always come from the
// network and are never stored.
'use strict';

var CACHE = 'bestandio-static-v1';
var scopePath = new URL(self.registration.scope).pathname;
var assetsPath = scopePath + 'assets/';

self.addEventListener('install', function () {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (event) {
  var req = event.request;
  var url = new URL(req.url);
  // Only same-origin GET requests for static assets; everything else is left to the network.
  if (req.method !== 'GET' || url.origin !== self.location.origin || url.pathname.indexOf(assetsPath) !== 0) {
    return;
  }
  event.respondWith(
    caches.open(CACHE).then(function (cache) {
      return cache.match(req).then(function (hit) {
        if (hit) {
          return hit;
        }
        return fetch(req).then(function (res) {
          var type = res.headers.get('Content-Type') || '';
          // Never store HTML, even if a static path should ever return a page.
          if (res.ok && type.indexOf('text/html') === -1) {
            cache.put(req, res.clone());
          }
          return res;
        });
      });
    })
  );
});
