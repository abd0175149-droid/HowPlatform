/* ============================================================
 * مركز إشعارات الأدمن (جرس الناف بار)
 * يقرأ من /admins/notifications/* — polling خفيف + قائمة منسدلة.
 * نسخة موازية لنظام الطالب (notifications_center.js) لكن بمسارات الأدمن.
 * ============================================================ */
(function () {
  'use strict';
  if (window.__ADMIN_NOTIF_CENTER_LOADED__) return;
  window.__ADMIN_NOTIF_CENTER_LOADED__ = true;

  var BASE = (function () {
    var b = String((window.__APP_CONFIG__ && window.__APP_CONFIG__.API_BASE) || window.API_BASE || '').trim().replace(/\/+$/, '');
    if (/\/admins$/.test(b)) return b;
    return b ? b + '/admins' : '/admins';
  })();

  var POLL_MS = 45000;
  var els = {};
  var pollTimer = null;

  function $(id) { return document.getElementById(id); }
  function esc(s) { var d = document.createElement('span'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }

  function timeAgo(ts) {
    if (!ts) return '';
    var d = new Date(String(ts).replace(' ', 'T'));
    if (isNaN(d.getTime())) return esc(ts);
    var diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 60) return 'الآن';
    if (diff < 3600) return Math.floor(diff / 60) + ' د';
    if (diff < 86400) return Math.floor(diff / 3600) + ' س';
    if (diff < 604800) return Math.floor(diff / 86400) + ' يوم';
    try { return new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'short' }).format(d); } catch (e) { return esc(ts); }
  }

  function getCsrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    if (m) return { name: m.getAttribute('name') || 'csrf_token', hash: m.getAttribute('content') || '' };
    var c = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]+)/i);
    return { name: 'csrf_token', hash: c ? decodeURIComponent(c[1]) : '' };
  }

  function setBadge(n) {
    if (!els.badge) return;
    n = Number(n) || 0;
    if (n > 0) { els.badge.textContent = n > 99 ? '99+' : String(n); els.badge.style.display = ''; }
    else { els.badge.style.display = 'none'; }
  }

  function jget(url) {
    return fetch(BASE + url, { credentials: 'include', headers: { 'Accept': 'application/json', 'Cache-Control': 'no-store' }, cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });
  }
  function jpost(url) {
    var c = getCsrf(); var h = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (c.hash) h['X-CSRF-TOKEN'] = c.hash;
    return fetch(BASE + url, { method: 'POST', credentials: 'include', headers: h }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });
  }

  function pollCount() {
    jget('/notifications/unread-count').then(function (d) { if (d && d.success) setBadge(d.unread); });
  }

  function render(items, unread) {
    if (!els.list) return;
    setBadge(unread);
    if (!items || !items.length) {
      els.list.innerHTML = '<div class="notif-state" style="padding:26px 18px;text-align:center;color:var(--text-muted);">لا توجد إشعارات بعد</div>';
      return;
    }
    els.list.innerHTML = items.map(function (n) {
      var unreadCls = (Number(n.is_read) === 0) ? 'unread' : 'read';
      var url = n.redirect_url ? esc(n.redirect_url) : '';
      return '<div class="notification-item ' + unreadCls + '" data-id="' + esc(n.id) + '" data-url="' + url + '" style="cursor:pointer;">'
        + '<div class="notification-icon"><i class="fas fa-bell"></i></div>'
        + '<div class="notification-info">'
        + '<div class="notification-title">' + (Number(n.is_read) === 0 ? '<span class="unread-badge"></span>' : '') + esc(n.title) + '</div>'
        + '<div class="notification-desc">' + esc(n.message) + '</div>'
        + '<div class="notification-time"><i class="far fa-clock"></i> ' + timeAgo(n.created_at) + '</div>'
        + '</div></div>';
    }).join('');

    Array.prototype.forEach.call(els.list.querySelectorAll('.notification-item'), function (item) {
      item.addEventListener('click', function () {
        var id = item.getAttribute('data-id');
        var url = item.getAttribute('data-url');
        if (item.classList.contains('unread')) {
          item.classList.remove('unread'); item.classList.add('read');
          var ub = item.querySelector('.unread-badge'); if (ub) ub.remove();
          jpost('/notifications/mark-read/' + id).then(function (d) { if (d && d.success) setBadge(d.unread); });
        }
        if (url) {
          // داخل لوحة الأدمن (SPA): إن كان قسمًا معروفًا حمّله بدل إعادة التحميل
          if (url.charAt(0) === '#' && typeof window.loadSection === 'function') {
            var sec = url.replace(/^#\/?/, '').split('?')[0];
            if (sec) { try { window.loadSection(sec); } catch (e) {} }
          } else if (url.charAt(0) === '#') {
            try { location.hash = url; } catch (e) {}
          } else {
            window.location.href = url;
          }
          closeDropdown();
        }
      });
    });
  }

  function loadList() {
    if (els.list) els.list.innerHTML = '<div class="notif-state" style="padding:26px 18px;text-align:center;color:var(--text-muted);">جارٍ التحميل…</div>';
    jget('/notifications/list?limit=15').then(function (d) {
      if (d && d.success) render(d.items, d.unread);
      else render([], 0);
    });
  }

  function openDropdown() { if (els.dd) { els.dd.classList.add('active'); els.bell.setAttribute('aria-expanded', 'true'); loadList(); } }
  function closeDropdown() { if (els.dd) { els.dd.classList.remove('active'); els.bell.setAttribute('aria-expanded', 'false'); } }
  function toggleDropdown() { if (els.dd && els.dd.classList.contains('active')) closeDropdown(); else openDropdown(); }

  function init() {
    els.bell = $('notifBellBtn'); els.dd = $('notifDropdown'); els.list = $('notifList');
    els.badge = $('notifBadge'); els.markAll = $('notifMarkAll'); els.wrap = $('navNotif');
    if (!els.bell || !els.dd) return;

    els.bell.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); toggleDropdown(); });
    if (els.markAll) els.markAll.addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation();
      jpost('/notifications/mark-all-read').then(function () { loadList(); });
    });
    document.addEventListener('click', function (e) {
      if (els.wrap && !els.wrap.contains(e.target)) closeDropdown();
    });
    var viewAll = $('notifViewAll');
    if (viewAll) viewAll.addEventListener('click', function (e) {
      e.preventDefault(); closeDropdown();
      if (typeof window.loadSection === 'function') window.loadSection('notifications');
    });

    pollCount();
    pollTimer = setInterval(pollCount, POLL_MS);
    window.addEventListener('beforeunload', function () { if (pollTimer) clearInterval(pollTimer); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

  window.AdminNotifCenter = { reloadCount: pollCount };
})();
