const CACHE = "pazarpress-v7";
const PRECACHE = [
  "/assets/css/site.css",
  "/assets/js/site.js",
  "/assets/js/theme.js",
  "/assets/js/reader-tools.js",
  "/assets/img/icon.svg",
  "/manifest.json",
];

function isStaticAsset(pathname) {
  return (
    pathname.startsWith("/assets/") ||
    /\.(css|js|woff2?|webp|jpg|jpeg|png|gif|svg|ico)$/i.test(pathname)
  );
}

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  const { request } = event;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.startsWith("/admin") || url.pathname.startsWith("/api")) return;

  if (isStaticAsset(url.pathname)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) return cached;
        return fetch(request).then((response) => {
          if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE).then((cache) => cache.put(request, clone));
          }
          return response;
        });
      })
    );
    return;
  }

  // HTML i stranice vijesti: uvijek mreža prvo, bez keširanja početne.
  event.respondWith(
    fetch(request).catch(() => caches.match(request))
  );
});
