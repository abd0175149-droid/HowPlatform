/* ============================================================
 * Global Unread Poller (students) — resilient & SPA-safe
 * File: public/assets/js/students/sections/unread_poller.js
 * ============================================================ */

(function () {
  // ممنوع نعمل أكثر من نسخة
  if (window.ServiceUnreadPoller?.__alive) return;

  const POLL_MS = Number(window.SVC_UNREAD_POLL_MS ?? 30000); // ← غيّر الفترة من اللاياوت
  const BADGE_ID = 'serviceRequestsBadge';
  const LINK_SELECTOR = '.menu-item.service-item .menu-link';

  // لوج بسيط
  const LOG = {
    i: (...a) => console.log('[unread_poller]', ...a),
    w: (...a) => console.warn('[unread_poller]', ...a),
    e: (...a) => console.error('[unread_poller]', ...a),
  };

  // إنشاء/إحضار رابط السايدبار
  function getServiceMenuLink() {
    return document.querySelector(LINK_SELECTOR) || null;
  }

  // تأكُّد من وجود البادج — وإن ما كان موجود ننشئه
  function ensureBadge() {
    const link = getServiceMenuLink();
    if (!link) return null;

    let badge = document.getElementById(BADGE_ID);
    if (!badge) {
      // لو تم إعادة بناء السايدبار، نُنشئ البادج من جديد
      badge = document.createElement('span');
      badge.id = BADGE_ID;
      badge.className = 'badge unread-badge';
      badge.style.display = 'none';
      link.appendChild(badge);
    }
    return badge;
  }

  // تحديث قيمة البادج (مع إنشاءه لو ناقص)
  function renderTotal(total) {
    const link = getServiceMenuLink();
    if (!link) return;

    const badge = ensureBadge();
    if (!badge) return;

    total = Number(total || 0);
    if (total > 0) {
      badge.textContent = String(total);
      badge.style.display = '';
    } else {
      badge.textContent = '';
      // نخفيه بدل ما نمسحه عشان ما يختفي مع إعادة البناء
      badge.style.display = 'none';
    }
  }

  // اجمع الإجمالي من counters endpoint
  async function fetchTotal() {
    try {
      const res = await fetch('/students/service-requests/unread-counters', { credentials: 'same-origin' });
      const j = await res.json();
      if (!j?.success) return 0;

      const rows = j.data?.counters || [];
      let sum = 0;
      for (const r of rows) sum += Number(r.unread_count || 0);
      return sum;
    } catch (e) {
      LOG.w('fetchTotal error', e);
      return 0;
    }
  }

  // شغّل دورة واحدة: تأكيد البادج + جلب + عرض + بثّ حدث
  async function tick() {
    ensureBadge(); // مهم: يعيد إنشاءه لو السايدبار اتبنَى من جديد
    const total = await fetchTotal();
    renderTotal(total);

    // نبثّ حدث عام ليستفيد منه service.js
    try {
      const ev = new CustomEvent('service:unread-total', { detail: { total } });
      window.dispatchEvent(ev);
    } catch (_) {}
  }

  // راقب تغيّرات الـDOM لإعادة حقن البادج عند إعادة بناء السايدبار
  let mo = null;
  function startObserver() {
    try {
      mo?.disconnect?.();
      mo = new MutationObserver(() => {
        // أي تغيّر بنية → تأكد من وجود البادج
        ensureBadge();
      });
      mo.observe(document.body, { childList: true, subtree: true });
    } catch (e) {
      LOG.w('MutationObserver failed', e);
    }
  }

  // API عام على window (للاستخدام من service.js أو غيره)
  window.ServiceUnreadPoller = {
    __alive: true,
    set(total) { renderTotal(total); },       // تحديث يدوي
    refresh: tick,                            // إجبار دورة
    stop() { try { clearInterval(this.__timer); } catch (_) {} },
  };

  // تهيئة أولية
  ensureBadge();
  startObserver();

  // دورة أولى مباشرة
  tick();

  // مؤقّت دائم — ما بيتلغى أثناء الانتقال بين الأقسام
  window.ServiceUnreadPoller.__timer = setInterval(tick, POLL_MS);

  LOG.i('started with interval =', POLL_MS, 'ms');
})();
