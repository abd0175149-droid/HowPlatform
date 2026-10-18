/* ============================================================
 * تسجيل إشعارات الدفع (FCM Web Push) — مشترك للطالب والآدمن
 * يُفعّل فقط عند توفّر window.__FCM__.vapidKey (من .env).
 * ============================================================ */
(function () {
  'use strict';
  if (window.__WEBPUSH_INIT__) return;
  window.__WEBPUSH_INIT__ = true;

  var FCM = window.__FCM__ || {};
  var SDK = 'https://www.gstatic.com/firebasejs/10.12.0';

  function base() { return location.pathname.indexOf('/admins') === 0 ? '/admins' : '/students'; }
  function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? (m.getAttribute('content') || '') : ''; }

  function register(token) {
    var fd = new FormData();
    fd.append('token', token);
    fd.append('csrf_test_name', csrf());
    fetch(base() + '/notifications/register-device', {
      method: 'POST', credentials: 'include',
      headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    }).catch(function () {});
  }

  function navTo(link) {
    try {
      if (link && link.charAt(0) === '#') {
        if (window.StudentRouter && typeof window.StudentRouter.navigate === 'function') window.StudentRouter.navigate(link);
        else if (typeof window.loadSection === 'function') window.loadSection(link.replace(/^#\/?/, '').split('?')[0]);
        else location.hash = link;
      } else if (link) { location.href = link; }
    } catch (e) {}
  }

  async function init() {
    try {
      if (!FCM.vapidKey || !FCM.config || !FCM.config.apiKey) return;            // غير مُفعّل (لا VAPID)
      if (!('serviceWorker' in navigator) || !('Notification' in window) || !('PushManager' in window)) return;
      if (Notification.permission === 'denied') return;

      var reg = await navigator.serviceWorker.register('/firebase-messaging-sw.js', { scope: '/' });

      if (Notification.permission === 'default') {
        var perm = await Notification.requestPermission();
        if (perm !== 'granted') return;
      }

      var appMod = await import(SDK + '/firebase-app.js');
      var msgMod = await import(SDK + '/firebase-messaging.js');
      var app = (appMod.getApps && appMod.getApps().length) ? appMod.getApps()[0] : appMod.initializeApp(FCM.config);
      var messaging = msgMod.getMessaging(app);

      var token = await msgMod.getToken(messaging, { vapidKey: FCM.vapidKey, serviceWorkerRegistration: reg });
      if (token) register(token);

      msgMod.onMessage(messaging, function () {
        try { if (window.NotifCenter && window.NotifCenter.reloadCount) window.NotifCenter.reloadCount(); } catch (e) {}
      });
    } catch (e) { /* صامت */ }
  }

  if (navigator.serviceWorker) {
    navigator.serviceWorker.addEventListener('message', function (ev) {
      var d = ev.data || {};
      if (d.type === 'notif-nav' && d.link) navTo(d.link);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 1500); });
  } else { setTimeout(init, 1500); }
})();
