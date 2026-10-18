/* Service Worker لإشعارات FCM في الخلفية — منصة كيف */
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: 'AIzaSyA0TtBYM055A40NHxE8qbHIMSzo6RFihLA',
  authDomain: 'howplatform-471514.firebaseapp.com',
  projectId: 'howplatform-471514',
  storageBucket: 'howplatform-471514.firebasestorage.app',
  messagingSenderId: '1040777194075',
  appId: '1:1040777194075:web:46b3ce92d3f09e8ea7ad7e'
});

try {
  const messaging = firebase.messaging();
  messaging.onBackgroundMessage(function (payload) {
    const n = payload.notification || {};
    const data = payload.data || {};
    self.registration.showNotification(n.title || 'إشعار جديد', {
      body: n.body || '',
      icon: '/assets/images/icon.ico',
      badge: '/assets/images/icon.ico',
      dir: 'rtl',
      data: { link: data.link || '/' }
    });
  });
} catch (e) { /* messaging غير متاح */ }

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  const link = (event.notification.data && event.notification.data.link) || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
      for (const client of clientList) {
        if ('focus' in client) {
          client.focus();
          try { client.postMessage({ type: 'notif-nav', link: link }); } catch (e) {}
          return;
        }
      }
      if (clients.openWindow) return clients.openWindow('/');
    })
  );
});
