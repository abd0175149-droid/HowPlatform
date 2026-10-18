/* admin_router.js */
/* global Chart, Swal, showWarning, showError, showConfirm */

'use strict';

/* ========================================================================== *
 *  Version (cache-busting) — عدّل الرقم عند كل نشر
 * ========================================================================== */
const VERSION = 'v=2.1.3'; // ← طلبات الخدمة: أول رسالة الطالب + معاينة القائمة + زر الحذف النهائي

/* ========================================================================== *
 *  Section Modules (ESM) — خريطة الأقسام
 * ========================================================================== */
const SECTION_MODULES = {
  dashboard: () => import(`./sections/dashboard.js?${VERSION}`),
  courses: () => import(`./sections/courses.js?${VERSION}`),
  weeks: () => import(`./sections/weeks.js?${VERSION}`),
  tasks: () => import(`./sections/tasks.js?${VERSION}`),
  academic_year_options: () => import(`./sections/academic_year_options.js?${VERSION}`),
  task_type_options: () => import(`./sections/task_type_options.js?${VERSION}`),
  university_options: () => import(`./sections/university_options.js?${VERSION}`),
  manage_users: () => import(`./sections/manage_users.js?${VERSION}`),
  account: () => import(`./sections/account.js?${VERSION}`),
  pending_requests: () => import(`./sections/pending_requests.js?${VERSION}`),
  service_chat: () => import(`./sections/service_requests_chat.js?${VERSION}`),
  email_broadcast: () => import(`./sections/email_broadcast.js?${VERSION}`),
  library_upload: () => import(`./sections/library_upload.js?${VERSION}`),
  plans: () => import(`./sections/plans.js?${VERSION}`),

  ai_lesson_plan: () => import(`./ai_lesson_plan.js?${VERSION}`),
  ai_tools: () => import(`./sections/ai_tools.js?${VERSION}`),
  notifications: () => import(`./sections/notifications.js?${VERSION}`),


};

/* ========================================================================== *
 *  DOM Elements — عناصر الواجهة الثابتة في اللاياوت
 * ========================================================================== */
const contentArea = document.getElementById('contentArea');
const sidebar = document.querySelector('.sidebar');
const overlay = document.querySelector('.overlay');
const menuToggle = document.querySelector('.menu-toggle');        // زر ☰ للهاتف
const submenuLinks = document.querySelectorAll('.submenu-link, .menu-link[data-section]');
const menuItems = document.querySelectorAll('.menu-item');
const toggleBtn = document.getElementById('sidebarToggleBtn');   // زر طي/فتح السايدبار
// Removed static logoutLink definition to use delegation instead

/* ========================================================================== *
 *  THEME (Light/Dark) — يعمل مع زر: .theme-toggle-btn داخل النافبار
 * ========================================================================== */
const LS_THEME_KEY = 'app:theme';          // 'light' | 'dark'
const THEME_ATTR = 'data-theme';         // نضبطه على <html>
const rootEl = document.documentElement;

function applyTheme(theme) {
  if (theme === 'dark') rootEl.setAttribute(THEME_ATTR, 'dark');
  else rootEl.removeAttribute(THEME_ATTR);
  syncThemeIcon();
}
function getCurrentTheme() {
  return rootEl.getAttribute(THEME_ATTR) === 'dark' ? 'dark' : 'light';
}
function initTheme() {
  const stored = localStorage.getItem(LS_THEME_KEY);
  if (stored === 'light' || stored === 'dark') {
    applyTheme(stored);
  } else {
    const prefersDark = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) || false;
    applyTheme(prefersDark ? 'dark' : 'light');
  }
}
function syncThemeIcon() {
  const isDark = getCurrentTheme() === 'dark';
  document.querySelectorAll('.theme-toggle-btn i').forEach((i) => {
    i.classList.toggle('fa-moon', !isDark);
    i.classList.toggle('fa-sun', isDark);
  });
}
// تفويض للزر داخل النافبار
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.theme-toggle-btn');
  if (!btn) return;
  e.preventDefault();
  const next = getCurrentTheme() === 'dark' ? 'light' : 'dark';
  localStorage.setItem(LS_THEME_KEY, next);
  applyTheme(next);
});

/* ========================================================================== *
 *  safeFetch — دوال جلب آمنة مع تشخيص وتعامل 401
 * ========================================================================== */
async function safeFetch(url, options = {}) {
  const merged = {
    method: options.method || 'GET',
    credentials: options.credentials || 'same-origin',
    cache: 'no-store',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      // مبدئيًا: نقبل الاثنين؛ سنفضّل في الدوال المتخصصة أدناه
      'Accept': 'application/json,text/html;q=0.9,*/*;q=0.1',
      ...(options.headers || {})
    },
    body: options.body,
  };

  console.groupCollapsed('%c[safeFetch] → ' + url, 'color:#7a7a7a');
  console.log('method:', merged.method);
  console.log('headers:', merged.headers);

  let res;
  try {
    res = await fetch(url, merged);
  } catch (netErr) {
    console.error('❌ Network error:', netErr);
    console.groupEnd();
    throw netErr;
  }

  const status = res.status;
  const type = res.headers.get('Content-Type') || '';
  let preview = '';
  try { preview = (await res.clone().text()).slice(0, 400); } catch (_) { }

  console.log('status:', status);
  console.log('content-type:', type);
  console.log('response preview:', preview);

  if (status === 401) {
    try {
      const clone = res.clone();
      if ((clone.headers.get('Content-Type') || '').includes('application/json')) {
        const j = await clone.json();
        if (j?.message && typeof showWarning === 'function') showWarning(j.message);
      }
    } catch (_) { }
    console.warn('↪ redirecting to /auth/login due to 401');
    console.groupEnd();
    window.location.href = '/auth/login';
    throw new Error('Unauthorized (401) – redirected to /auth/login');
  }

  console.groupEnd();
  return res;
}

// نفضّل HTML هنا
async function safeFetchText(url, options = {}) {
  const res = await safeFetch(url, {
    ...options,
    headers: { ...(options.headers || {}), 'Accept': 'text/html,application/json;q=0.9,*/*;q=0.1' }
  });
  return res.text();
}

// نفضّل JSON هنا
async function safeFetchJSON(url, options = {}) {
  const res = await safeFetch(url, {
    ...options,
    headers: { ...(options.headers || {}), 'Accept': 'application/json' }
  });
  const type = res.headers.get('Content-Type') || '';
  if (!type.includes('application/json')) {
    const t = await res.text();
    try { return JSON.parse(t); }
    catch (e) {
      const err = new Error('Expected JSON but got: ' + type);
      err.responseText = t;
      throw err;
    }
  }
  return res.json();
}

/* ========================================================================== *
 *  Session Debug — طباعة السِشن في الكونسول (Inline + Fallback AJAX)
 * ========================================================================== */
function escapeHtml(str = '') {
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
function deepPrint(obj, title = 'object', level = 0) {
  const pad = '  '.repeat(level);
  try {
    if (obj && typeof obj === 'object') {
      const isArray = Array.isArray(obj);
      const keys = isArray ? obj.map((_, i) => i) : Object.keys(obj);
      console.groupCollapsed(`${pad}%c${title} ${isArray ? `[${obj.length}]` : `{${keys.length}}`}`, 'color:#156683');
      try { console.table(obj); } catch { console.dir(obj); }
      for (const k of keys) {
        const v = obj[k];
        const label = isArray ? `[${k}]` : `.${k}`;
        if (v && typeof v === 'object') deepPrint(v, `${title}${label}`, level + 1);
        else console.log(`${pad}${label}:`, v);
      }
      console.groupEnd();
    } else {
      console.log(`${pad}${title}:`, obj);
    }
  } catch (e) {
    console.warn('deepPrint error:', e);
    console.dir(obj);
  }
}

function printInlineSession() {
  try {
    const hasInline = typeof window.__SESSION__ !== 'undefined' && window.__SESSION__;
    console.group('%c[admins inline session]', 'color:#219ebc;font-weight:bold');
    if (!hasInline) {
      console.warn('No inline session found on window.__SESSION__ (admin layout).');
      console.groupEnd();
      return false;
    }
    const inline = window.__SESSION__;
    const uid = (inline.user_id ?? inline.id ?? inline?.auth_user?.id ?? 'N/A');
    console.log(`admins:${uid} 🔎 Session Data:`, inline);

    try {
      const flat = Object.fromEntries(
        Object.entries(inline).map(([k, v]) => [k, (v && typeof v === 'object') ? '[object]' : v])
      );
      console.groupCollapsed('%cTop-level (flat)', 'color:#156683'); console.table(flat); console.groupEnd();
    } catch (_) { }

    for (const [k, v] of Object.entries(inline)) {
      if (v && typeof v === 'object') deepPrint(v, `session.${k}`);
    }
    console.groupEnd();
    return true;
  } catch (e) {
    console.error('[admins inline session] error:', e);
    return false;
  }
}

const SESSION_ENDPOINTS = [
  '/admins/debug/session',
  '/admin/debug/session',
  '/admins/session-debug',
  '/admins/session',
  '/debug/session',
];

export async function printAdminsSession(opts = {}) {
  const { showModal = false } = opts;

  console.group('%c[admins session debug]', 'color:#219ebc;font-weight:bold');
  console.time('[session] total');

  console.log('location:', location.href);
  console.log('origin:', location.origin);
  console.log('document.cookie (JS-readable):', document.cookie || '<empty>');
  console.log('current theme:', getCurrentTheme());

  let lastErr = null;
  let resolvedData = null;
  let usedUrl = null;

  for (const base of SESSION_ENDPOINTS) {
    const url = `${base}?ts=${Date.now()}&${VERSION}`;
    console.groupCollapsed('try endpoint:', url);

    try {
      const data = await safeFetchJSON(url, { headers: { 'Cache-Control': 'no-cache' } });
      const sessionData = (data && typeof data.data === 'object') ? data.data : (data || {});
      const keysCount = sessionData && typeof sessionData === 'object' ? Object.keys(sessionData).length : 0;

      console.log('parsed JSON keys:', keysCount);
      if (keysCount > 0 || data?.id || data?.started_at || data?.ip) {
        resolvedData = data;
        usedUrl = url;
        console.log('✔ success with endpoint:', url);
        console.groupEnd();
        break;
      } else {
        console.warn('endpoint returned JSON but appears empty.');
      }
    } catch (e) {
      lastErr = e;
      console.error('endpoint failed:', e.message || e);
      if (e.responseText) {
        console.log('response text preview:\n', (e.responseText || '').slice(0, 500));
      }
    }
    console.groupEnd();
  }

  if (!resolvedData) {
    console.error('❌ No session data resolved from endpoints.');
    console.warn('Hints: تأكد من تسجيل الدخول، وتوحيد Cookie Domain/Path/Name، وأن المسار يعيد JSON.');
    console.timeEnd('[session] total');
    console.groupEnd();
    if (typeof showError === 'function') showError('تعذّر جلب بيانات السِشن — راجع الكونسول للتفاصيل.');
    return;
  }

  console.group('%cResolved endpoint', 'color:#0a3247'); console.log(usedUrl); console.groupEnd();
  console.group('%cRaw JSON', 'color:#7a7a7a'); console.dir(resolvedData); console.groupEnd();

  const top = {
    id: resolvedData?.id,
    ip: resolvedData?.ip,
    user_agent: resolvedData?.user_agent,
    started_at: resolvedData?.started_at,
    last_regenerated_at: resolvedData?.last_regenerated_at,
  };
  console.groupCollapsed('%cTop-level fields', 'color:#156683'); console.table(top); console.groupEnd();

  const sessionObj = (resolvedData && typeof resolvedData.data === 'object')
    ? resolvedData.data
    : (resolvedData || {});
  console.group('%cSession Data (flatten + deep)', 'color:#104b65;font-weight:bold');

  try {
    const flat = Object.fromEntries(
      Object.entries(sessionObj).map(([k, v]) => [k, (v && typeof v === 'object') ? '[object]' : v])
    );
    console.groupCollapsed('%cFlat keys', 'color:#5fb6e2'); console.table(flat); console.groupEnd();
  } catch (e) { console.warn('flat table error:', e); }

  for (const [k, v] of Object.entries(sessionObj)) {
    deepPrint(v, `session.${k}`);
  }
  console.groupEnd();

  console.timeEnd('[session] total');
  console.groupEnd();

  if (showModal && typeof Swal === 'function') {
    const pretty = escapeHtml(JSON.stringify(resolvedData, null, 2));
    await Swal.fire({
      title: 'PHP Session (admins) — Full JSON',
      html: `<pre style="text-align:left;direction:ltr;max-height:60vh;overflow:auto;margin:0">${pretty}</pre>`,
      confirmButtonText: 'إغلاق',
      width: 780,
    });
  }
}

/* إتاحة دوال عالميًا */
window.printAdminsSession = () => printAdminsSession({ showModal: false });
window.reloadCurrentSection = reloadCurrentSection;

// Alt + Shift + S → نافذة طوارئ لطباعة السِشن
document.addEventListener('keydown', (e) => {
  if (e.altKey && e.shiftKey && e.code === 'KeyS') {
    printAdminsSession({ showModal: true });
  }
});

/* ========================================================================== *
 *  Navigation — تفعيل الروابط وتحديد النشط
 * ========================================================================== */
function setActiveLink(section) {
  // فعّل الرابط المطابق
  submenuLinks.forEach(link => {
    const parent = link.closest('.menu-item');
    if (link.dataset.section === section) {
      link.classList.add('active');
      // ✅ افتح الأب دائمًا إذا كان لهذا البند قائمة فرعية
      if (parent) parent.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });

  // أبقِ فقط البند الذي يحتوي على رابط نشط مفتوحًا
  menuItems.forEach(item => {
    const hasActiveChild = !!item.querySelector('[data-section].active');
    item.classList.toggle('active', hasActiveChild);
  });
}

/* ========================================================================== *
 *  Load Section — تحميل صفحة قسم داخل #contentArea + تشغيل سكربتها
 *  مضاف: payload اختياري يُخزَّن في window.__pendingSectionPayload ليقرأه سكربت القسم
 * ========================================================================== */
export async function loadSection(section, payload = null) {
  try {
    const encoded = encodeURIComponent(section);
    const htmlUrl = `/admins/pages/${encoded}?${VERSION}`;

    // 🔗 خزّن payload عالميًا ليستلمه سكربت القسم (مثال: service_requests_chat يقرأ request_id)
    window.__pendingSectionPayload = (payload && typeof payload === 'object') ? payload : null;

    console.group('%c[loadSection]', 'color:#7c3aed');
    console.log('section:', section);
    console.log('GET HTML:', htmlUrl);
    if (window.__pendingSectionPayload) {
      console.log('payload:', window.__pendingSectionPayload);
    }

    const html = await safeFetchText(htmlUrl);
    if (contentArea) contentArea.innerHTML = html;

    let mod = null;
    if (SECTION_MODULES[section]) {
      console.log('→ importing module for:', section);

      // استيراد الموديول
      const ns = await SECTION_MODULES[section]();
      // التفضيل للـ default export إن وُجد
      const page = (ns && ns.default) ? ns.default : ns;

      // 1) حاول init() على الـnamespace أولاً (توافقاً مع أقسام قديمة)
      if (typeof ns?.init === 'function') {
        await Promise.resolve(ns.init());
        console.log('✓ module.init() done (namespace)');
      }
      // 2) ثم جرّب init() على الـdefault (لو كان يصدّرها هناك)
      else if (typeof page?.init === 'function') {
        await Promise.resolve(page.init());
        console.log('✓ default.init() done');
      }

      // 3) وأخيراً، إن لم توجد init()، جرّب mount(root)
      if (typeof page?.mount === 'function') {
        await Promise.resolve(page.mount(contentArea || document));
        console.log('✓ module.mount() done');
      } else if (!ns?.init && !page?.init) {
        console.log('ℹ no init()/mount() found on module:', section);
      }

      // أعد المرجع (يُفيد إن أردت استخدامه لاحقاً)
      mod = page;
    } else {
      console.log('⚠ no JS module for section:', section);
    }

    setActiveLink(section);
    localStorage.setItem('activeSection', section);

    // بعد أي تحميل، تأكد من تزامن أيقونة الثيم
    syncThemeIcon();
    console.groupEnd();
    return mod;
  } catch (err) {
    console.error(err);
    if (contentArea) {
      contentArea.innerHTML = `
        <div class="content-placeholder">
          <i class="fas fa-exclamation-triangle fa-2x"></i>
          <h3>حدث خطأ أثناء تحميل القسم</h3>
          <p>${escapeHtml(err.message)}</p>
        </div>`;
    }
    return null;
  }
}

/* ⬅️ توفير حمّال عالمي: ليستدعيه أي سكربت مثل جدول الطلبات */
window.loadSection = (section, payload) => loadSection(section, payload);

/* تفويض عام لعناصر data-section — يدعم العناصر المحقونة لاحقًا */
document.addEventListener('click', (e) => {
  const trigger = e.target.closest('[data-section]');
  if (!trigger) return;
  e.preventDefault();
  e.stopPropagation();
  const section = trigger.getAttribute('data-section');
  if (section) loadSection(section);
});

/* ========================================================================== *
 *  Service Chat — جسـر لفتح خيط محادثة محدد من أي مكان باللوحة
 *  استخدم data-open-service-chat و data-request-id (أو data-req-id)
 * ========================================================================== */
document.addEventListener('click', (e) => {
  const t = e.target.closest('[data-open-service-chat]');
  if (!t) return;
  e.preventDefault();
  const rid = t.getAttribute('data-request-id') || t.getAttribute('data-req-id') || t.dataset.requestId || '';
  if (!rid) { console.warn('data-open-service-chat بدون request id'); return; }
  loadSection('service_chat', { request_id: String(rid).trim() });
});

/* ========================================================================== *
 *  Sidebar — فتح/إغلاق + تفاعل الغطاء على الهاتف
 * ========================================================================== */
function initSidebar() {
  const isMobile = () => window.innerWidth <= 768;

  const updateToggleIcon = () => {
    const isCollapsed = sidebar?.classList.contains('collapsed');
    // تحديث aria-expanded للوصولية
    if (toggleBtn) {
      toggleBtn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
      toggleBtn.classList.toggle('sidebar-is-open', !isCollapsed);
    }
    if (isMobile()) document.body.classList.toggle('no-scroll', !isCollapsed);
  };

  const setInitialState = () => {
    if (!sidebar || !overlay) return;
    if (isMobile()) {
      sidebar.classList.add('collapsed');
      overlay.classList.remove('active');
      document.body.classList.remove('no-scroll');
    } else {
      sidebar.classList.remove('collapsed');
      overlay.classList.remove('active');
      document.body.classList.remove('no-scroll');
    }
    updateToggleIcon();
  };

  setInitialState();
  window.addEventListener('resize', setInitialState);

  // زر ☰ للهاتف
  menuToggle?.addEventListener('click', () => {
    if (!sidebar || !overlay) return;
    sidebar.classList.remove('collapsed');
    overlay.classList.add('active');
    updateToggleIcon();
  });

  // زر طي/فتح السايدبار
  toggleBtn?.addEventListener('click', () => {
    if (!sidebar || !overlay) return;
    sidebar.classList.toggle('collapsed');
    if (isMobile()) {
      overlay.classList.toggle('active', !sidebar.classList.contains('collapsed'));
    }
    updateToggleIcon();
  });

  // الضغط على الغطاء خارج السايدبار
  overlay?.addEventListener('click', () => {
    if (!sidebar || !overlay) return;
    sidebar.classList.add('collapsed');
    overlay.classList.remove('active');
    updateToggleIcon();
  });

  // روابط القائمة — تغلق السايدبار على الهاتف بعد الاختيار
  submenuLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const sec = link.dataset.section;
      if (sec) loadSection(sec);
      if (isMobile()) {
        sidebar.classList.add('collapsed');
        overlay.classList.remove('active');
        updateToggleIcon();
      }
    });
  });

  // طي/فتح القوائم الفرعية
  menuItems.forEach(item => {
    const link = item.querySelector('.menu-link');
    const hasSub = !!item.querySelector('.submenu');
    if (link && hasSub) {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        item.classList.toggle('active');
      });
    }
  });
}

/* ========================================================================== *
 *  Global Service Chat Unread Badge — Polling كل 30 ثانية على السايدبار
 *  يعتمد على /admins/api/service-requests/unread-counters
 * ========================================================================== */
const SVC_BADGE_SELECTOR = '#serviceChatTotalUnread';
const SVC_UNREAD_ENDPOINT = '/admins/api/service-requests/unread-counters';
const SVC_POLL_VISIBLE_MS = 30000;  // الصفحة مرئية
const SVC_POLL_HIDDEN_MS = 60000;  // الصفحة بالخلفية
let _svcBadgeTimer = null;

function setServiceChatBadge(total) {
  const el = document.querySelector(SVC_BADGE_SELECTOR);
  if (!el) return;
  const t = Number(total || 0);
  if (t > 0) {
    el.textContent = t > 99 ? '99+' : String(t);
    el.style.display = 'inline-flex';
  } else {
    el.textContent = '0';
    el.style.display = 'none';
  }
}

export async function refreshServiceChatBadge() {
  try {
    const data = await safeFetchJSON(`${SVC_UNREAD_ENDPOINT}?ts=${Date.now()}`);
    const counters = data?.data?.counters || [];
    const total = counters.reduce((sum, row) => sum + (Number(row?.unread_count) || 0), 0);
    setServiceChatBadge(total);
  } catch (e) {
    // لا نُزعج المستخدم — نطبع فقط
    console.warn('[svc-badge] failed to refresh:', e?.message || e);
  }
}

function startServiceChatBadgePolling() {
  // شغّل مرة الآن
  refreshServiceChatBadge();

  const setup = () => {
    if (_svcBadgeTimer) clearInterval(_svcBadgeTimer);
    const ms = document.visibilityState === 'visible' ? SVC_POLL_VISIBLE_MS : SVC_POLL_HIDDEN_MS;
    _svcBadgeTimer = setInterval(() => refreshServiceChatBadge(), ms);
  };

  setup();
  document.addEventListener('visibilitychange', setup);
  window.addEventListener('focus', refreshServiceChatBadge);
  window.addEventListener('admins:svc-badge:refresh', refreshServiceChatBadge); // قناة داخلية اختيارية
}

/* ========================================================================== *
 *  Bootstrap — نقطة البداية
 * ========================================================================== */
document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  syncThemeIcon();
  initSidebar();

  // تحميل القسم المحفوظ أو الافتراضي
  const saved = localStorage.getItem('activeSection') || 'dashboard';
  loadSection(saved);

  // طباعة السِشن: Inline أولاً ثم Fallback
  const inlineOk = printInlineSession();
  if (!inlineOk) {
    console.warn('[admins] لا توجد __SESSION__ inline من اللاياوت؛ المحاولة عبر endpoint…');
    printAdminsSession({ showModal: false });
  }

  // تمكين طباعة السِشن تلقائيًا إذا debugSession=1
  const q = new URLSearchParams(location.search);
  if (q.get('debugSession') === '1') {
    printAdminsSession({ showModal: true });
  }

  // 🟣 تشغيل Polling الشارة العامة لمحادثات الخدمة
  startServiceChatBadgePolling();
});

/* ========================================================================== *
 *  Logout — تأكيد قبل الخروج
 * ========================================================================== */
// ✅ تسجيل الخروج (إضافة تأكيد وحفظ التوجيه)
document.addEventListener('click', function (e) {
  const logoutBtn = e.target.closest('#logoutLink');
  if (!logoutBtn) return;

  e.preventDefault();
  const href = logoutBtn.getAttribute('href');

  if (typeof showConfirm === 'function') {
    showConfirm({
      title: 'هل أنت متأكد؟',
      text: 'سيتم تسجيل الخروج من حسابك.',
      confirmText: 'نعم، سجل الخروج',
      cancelText: 'إلغاء',
      onConfirm: () => { window.location.href = href; }
    });
  } else {
    const ok = window.confirm('سيتم تسجيل الخروج من حسابك. هل أنت متأكد؟');
    if (ok) window.location.href = href;
  }
});

/* ========================================================================== *
 *  Reload current section — مفيد بعد عمليات CRUD
 * ========================================================================== */
export function reloadCurrentSection() {
  const section = localStorage.getItem('activeSection');
  if (section) loadSection(section);
}
