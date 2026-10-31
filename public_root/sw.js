// OpenMeru Mail service worker: shows push notifications for new mail.
self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));

self.addEventListener("push", (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data && event.data.text() }; }
  event.waitUntil(Promise.all([
    self.registration.showNotification(data.title || "OpenMeru Mail", {
      body: data.body || "",
      icon: data.icon || "/assets/icon-192.png",
      badge: data.icon || "/assets/icon-192.png",
      tag: data.tag || "openmeru-mail",
      data: { url: data.url || "/" }
    }),
    // Open OpenMeru Mail tabs refresh their list right away.
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((list) => {
      list.forEach((client) => client.postMessage({ type: "new-mail" }));
    })
  ]));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const url = new URL((event.notification.data && event.notification.data.url) || "/", self.location.origin).href;
  event.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((list) => {
      for (const client of list) {
        if (client.url.startsWith(self.location.origin + "/") && "focus" in client) {
          client.postMessage({ type: "open", url });
          return client.focus();
        }
      }
      return self.clients.openWindow(url);
    })
  );
});
