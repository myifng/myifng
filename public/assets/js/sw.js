/* सर्विस वर्कर: वेब पुश + (PWA चालू हो तो) कैश और ऑफ़लाइन।
   सर्वर इस फ़ाइल से पहले self.NP = {cache, v, offline, precache, skip, pages, images} जोड़ता है (PwaController)। */
var NP = self.NP || { cache: false, v: '0', precache: [], pages: 0, images: 0, skip: '^$' };
var CORE = 'np-core-' + NP.v, PAGES = 'np-pages', IMGS = 'np-img', RT = 'np-rt';
var SKIP = new RegExp(NP.skip);
var BASE = new URL(self.registration.scope).pathname;

self.addEventListener('install', function (e) {
  self.skipWaiting();
  if (!NP.cache) return;
  // एक फ़ाइल न मिले तो बाकी फिर भी कैश हों
  e.waitUntil(caches.open(CORE).then(function (c) {
    return Promise.all(NP.precache.map(function (u) { return c.add(new Request(u, { cache: 'reload' })).catch(function () {}); }));
  }));
});

self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) {
      if (k.indexOf('np-') !== 0) return false;
      if (!NP.cache) return true;                      // PWA बंद: सारा कैश साफ़
      if (k === PAGES) return NP.pages === 0;
      return k.indexOf('np-core-') === 0 && k !== CORE; // पुराना वर्ज़न
    }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

function trim(name, max) {
  return caches.open(name).then(function (c) {
    return c.keys().then(function (keys) {
      var extra = keys.length - max;
      return extra > 0 ? Promise.all(keys.slice(0, extra).map(function (k) { return c.delete(k); })) : null;
    });
  });
}

function isHtml(res) { return /text\/html/.test(res.headers.get('content-type') || ''); }

/* पेज: पहले नेटवर्क; नेट न हो तो कैश वाली कॉपी, वरना ऑफ़लाइन पेज */
function page(e) {
  var req = e.request;
  return fetch(req).then(function (res) {
    if (NP.pages > 0 && res.status === 200 && res.type === 'basic' && isHtml(res)) {
      var copy = res.clone();
      e.waitUntil(caches.open(PAGES).then(function (c) { return c.put(req.url, copy); }).then(function () { return trim(PAGES, NP.pages); }));
    }
    return res;
  }).catch(function () {
    return caches.match(req.url, { cacheName: PAGES })
      .then(function (r) { return r || caches.match(req.url, { cacheName: PAGES, ignoreSearch: true }); })
      .then(function (r) { return r || caches.match(NP.offline); })
      .then(function (r) { return r || new Response('<h1>ऑफ़लाइन</h1>', { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }); });
  });
}

/* CSS/JS/फ़ॉन्ट: कैश से तुरंत, पीछे से ताज़ा */
function swr(e, name) {
  return caches.open(name).then(function (c) {
    return c.match(e.request).then(function (hit) {
      var net = fetch(e.request).then(function (res) {
        if (res.ok || res.type === 'opaque') c.put(e.request, res.clone());
        return res;
      });
      if (hit) { e.waitUntil(net.catch(function () {})); return hit; }
      return net;
    });
  });
}

/* अपलोड की इमेज: कैश पहले (फ़ाइल का नाम नहीं बदलता), सीमा के साथ */
function img(e) {
  return caches.open(IMGS).then(function (c) {
    return c.match(e.request).then(function (hit) {
      return hit || fetch(e.request).then(function (res) {
        if (res.ok) { c.put(e.request, res.clone()); e.waitUntil(trim(IMGS, NP.images)); }
        return res;
      });
    });
  });
}

self.addEventListener('fetch', function (e) {
  if (!NP.cache) return;
  var req = e.request;
  if (req.method !== 'GET' || req.headers.has('range')) return;
  var url = new URL(req.url);
  if (url.origin === location.origin) {
    if (url.pathname.indexOf(BASE) !== 0) return;
    var rel = url.pathname.slice(BASE.length);
    if (SKIP.test(rel) || url.searchParams.has('preview')) return; // एडमिन, अकाउंट, API, लॉगिन आदि कभी कैश नहीं
    if (req.mode === 'navigate') { e.respondWith(page(e)); return; }
    if (rel.indexOf('public/assets/') === 0) { e.respondWith(swr(e, CORE)); return; }
    if (rel.indexOf('public/uploads/') === 0 && req.destination === 'image') { e.respondWith(img(e)); return; }
    if (req.url === NP.offline) { e.respondWith(caches.match(req).then(function (r) { return r || fetch(req); })); }
    return;
  }
  if (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') e.respondWith(swr(e, RT));
});

/* वेब पुश: सूचना दिखाना और क्लिक पर ख़बर खोलना */
self.addEventListener('push', function (e) {
  var d = {};
  try { d = e.data ? e.data.json() : {}; } catch (x) { d = { title: e.data ? e.data.text() : '' }; }
  if (!d.title) return;
  e.waitUntil(self.registration.showNotification(d.title, {
    body: d.body || '', icon: d.icon, badge: d.icon, image: d.image, tag: d.tag, renotify: !!d.tag, data: { url: d.url || self.registration.scope }
  }));
});
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = (e.notification.data && e.notification.data.url) || self.registration.scope;
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    for (var i = 0; i < list.length; i++) { if (list[i].url === url && 'focus' in list[i]) return list[i].focus(); }
    return self.clients.openWindow(url);
  }));
});
