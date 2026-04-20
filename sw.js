// Bump this on every deploy
const CACHE_NAME = "snake-game-v1.17"; //
const urlsToCache = [
  "./",
  "./index.html",
  "./css/style.css",
  "./js/main.js",
  "./js/game.js",
  "./js/ui.js",
  "./js/audio.js",
  "./js/settings.js",
  "./manifest.json",
  "./192.png",
  "./512.png",
  "./audio/snake_movement.ogg",
  "./audio/snake_movement.m4a",
];
function broadcast(msg) {
  self.clients
    .matchAll({ type: "window", includeUncontrolled: true })
    .then((clients) => clients.forEach((c) => c.postMessage(msg)));
}
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(urlsToCache))
  );
  // Tell any open windows there's an update installing (skip first-run UIs later)
  broadcast({ type: "SW_INSTALLING", version: CACHE_NAME });
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(
        keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
      );
      // (Optional) enable navigation preload if you added it
      if (self.registration.navigationPreload) {
        await self.registration.navigationPreload.enable();
      }
      await self.clients.claim();
      // Let pages know the new SW is live
      broadcast({ type: "SW_ACTIVATED", version: CACHE_NAME });
    })()
  );
});
// Allow page to tell us to skip waiting (compat across browsers)
self.addEventListener("message", (event) => {
  if (event?.data?.type === "SKIP_WAITING") self.skipWaiting();
});

// Fetch:

self.addEventListener("fetch", (event) => {
  const req = event.request;

  // 1) HTML navigations: network-first
  if (req.mode === "navigate") {
    event.respondWith(fetch(req).catch(() => caches.match("./index.html")));
    return;
  }

  // 2) Everything else: cache-first
  event.respondWith(caches.match(req).then((res) => res || fetch(req)));
});
