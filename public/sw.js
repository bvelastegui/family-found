self.addEventListener('push', (event) => {
  let message = {};

  if (event.data) {
    try {
      message = event.data.json();
    } catch {
      message = { body: event.data.text() };
    }
  }

  const url =
    typeof message.data?.url === 'string' ? message.data.url : '/notifications';

  event.waitUntil(
    (async () => {
      await self.registration.showNotification(
        message.title || 'Fondo Familiar',
        {
          body: message.body || 'Tienes una notificación nueva.',
          icon: '/pwa-192.png',
          badge: '/pwa-192.png',
          data: { url },
          tag: message.tag,
        },
      );
      const windows = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
      });
      windows.forEach((window) =>
        window.postMessage({ type: 'fund-notification' }),
      );
    })(),
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = new URL(
    event.notification.data?.url || '/notifications',
    self.location.origin,
  );
  const url =
    target.origin === self.location.origin
      ? target.href
      : new URL('/notifications', self.location.origin).href;

  event.waitUntil(
    (async () => {
      const windows = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
      });
      const existing = windows.find(
        (window) => new URL(window.url).origin === self.location.origin,
      );

      if (existing) {
        await existing.navigate(url);
        return existing.focus();
      }

      return self.clients.openWindow(url);
    })(),
  );
});
