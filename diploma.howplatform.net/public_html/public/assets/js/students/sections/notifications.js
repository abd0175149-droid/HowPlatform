/* ==========================================================================
 * notifications.js — صفحة كل الإشعارات (طالب)
 * قائمة كاملة مرقّمة + تعليم/حذف/مسح الكل. يُحمّل كقسم static (init).
 * ========================================================================== */

const RAW_BASE = String(window.API_BASE || '').trim().replace(/\/+$/, '');
const BASE = (RAW_BASE && /\/students$/.test(RAW_BASE)) ? RAW_BASE : (RAW_BASE ? RAW_BASE + '/students' : '/students');
const NB = BASE + '/notifications';
const PAGE = 20;

let _offset = 0, _loading = false, _hasMore = true;

function $(id) { return document.getElementById(id); }
function esc(s) { const d = document.createElement('span'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
function csrf() { const m = document.querySelector('meta[name="csrf-token"]'); return m ? (m.getAttribute('content') || '') : ''; }
function timeAgo(ts) {
  if (!ts) return '';
  const d = new Date(String(ts).replace(' ', 'T'));
  if (isNaN(d.getTime())) return esc(ts);
  const diff = (Date.now() - d.getTime()) / 1000;
  if (diff < 60) return 'الآن';
  if (diff < 3600) return Math.floor(diff / 60) + ' د';
  if (diff < 86400) return Math.floor(diff / 3600) + ' س';
  if (diff < 604800) return Math.floor(diff / 86400) + ' يوم';
  try { return new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'short' }).format(d); } catch (e) { return esc(ts); }
}
function jget(u) { return fetch(NB + u, { credentials: 'include', headers: { 'Accept': 'application/json', 'Cache-Control': 'no-store' }, cache: 'no-store' }).then(r => r.ok ? r.json() : null).catch(() => null); }
function jpost(u) { const h = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() }; return fetch(NB + u, { method: 'POST', credentials: 'include', headers: h }).then(r => r.ok ? r.json() : null).catch(() => null); }

function navTo(url) {
  if (!url) return;
  if (url.charAt(0) === '#') {
    if (window.StudentRouter && window.StudentRouter.navigate) { window.StudentRouter.navigate(url); return; }
    location.hash = url;
  } else location.href = url;
}

function itemHtml(n) {
  const unread = Number(n.is_read) === 0;
  const url = n.redirect_url ? esc(n.redirect_url) : '';
  return `<div class="np-item ${unread ? 'unread' : ''}" data-id="${esc(n.id)}" data-url="${url}">
    <div class="np-ic"><i class="fas fa-bell"></i></div>
    <div class="np-body">
      <div class="np-title">${unread ? '<span class="np-dot"></span>' : ''}${esc(n.title)}</div>
      <div class="np-msg">${esc(n.message)}</div>
      <div class="np-time"><i class="far fa-clock"></i> ${timeAgo(n.created_at)}</div>
    </div>
    <button class="np-del" title="حذف" data-del="${esc(n.id)}"><i class="fas fa-xmark"></i></button>
  </div>`;
}

function bindItems(container) {
  container.querySelectorAll('.np-item').forEach(item => {
    item.addEventListener('click', (e) => {
      if (e.target.closest('.np-del')) return;
      const id = item.getAttribute('data-id');
      if (item.classList.contains('unread')) {
        item.classList.remove('unread');
        const dot = item.querySelector('.np-dot'); if (dot) dot.remove();
        jpost('/mark-read/' + id);
      }
      navTo(item.getAttribute('data-url'));
    });
  });
  container.querySelectorAll('.np-del').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.getAttribute('data-del');
      const row = btn.closest('.np-item');
      jpost('/delete/' + id).then(() => { if (row) row.remove(); ensureNotEmpty(); });
    });
  });
}

function ensureNotEmpty() {
  const list = $('np-list');
  if (list && !list.querySelector('.np-item')) {
    list.innerHTML = '<div class="np-state"><i class="fas fa-bell-slash"></i><h3>لا توجد إشعارات</h3><p>ستظهر هنا إشعاراتك عند وصولها.</p></div>';
    const more = $('np-more'); if (more) more.style.display = 'none';
  }
}

function load(reset) {
  if (_loading) return;
  _loading = true;
  const list = $('np-list');
  if (reset) { _offset = 0; _hasMore = true; }
  jget(`/list?limit=${PAGE}&offset=${_offset}`).then(d => {
    _loading = false;
    if (!d || !d.success) { if (reset && list) list.innerHTML = '<div class="np-state">تعذّر تحميل الإشعارات.</div>'; return; }
    const items = d.items || [];
    if (reset) list.innerHTML = '';
    if (!items.length && reset) { ensureNotEmpty(); return; }
    const frag = document.createElement('div');
    frag.innerHTML = items.map(itemHtml).join('');
    while (frag.firstChild) list.appendChild(frag.firstChild);
    bindItems(list);
    _offset += items.length;
    _hasMore = !!d.has_more;
    const more = $('np-more'); if (more) more.style.display = _hasMore ? '' : 'none';
    if (window.NotifCenter && window.NotifCenter.reloadCount) window.NotifCenter.reloadCount();
  });
}

export function init() {
  if (!$('notif-page')) return;
  const more = $('np-more'); if (more) more.onclick = () => load(false);
  const ma = $('np-mark-all'); if (ma) ma.onclick = () => jpost('/mark-all-read').then(() => load(true));
  const ca = $('np-clear-all'); if (ca) ca.onclick = () => { if (confirm('حذف كل الإشعارات؟')) jpost('/clear-all').then(() => load(true)); };
  load(true);
}

export default { init };
