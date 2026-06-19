/* ==========================================================================
 * File: public_html/assets/js/student/dashboard.js  (v1.4.2)
 * Purpose: Student dashboard interactions (upcoming tasks + modals)
 * محدث لدعم التنقل الديناميكي بدون Reload عبر MutationObserver + hashchange.
 * + دمج منطق الأدوات التعليمية (EduTools) مع Notifier ورسائل "قريبًا".
 * ========================================================================== */

/* ================== ① الثوابت العامة ================== */
const RAW_BASE = String(window.API_BASE || '').trim().replace(/\/+$/, '');
const STUDENTS_BASE = (RAW_BASE && RAW_BASE.endsWith('/students'))
  ? RAW_BASE
  : (RAW_BASE ? `${RAW_BASE}/students` : '/students');

const USER_IS_PREMIUM = Number(
  window.USER_IS_PREMIUM ??
  document.documentElement?.dataset?.userPremium ??
  document.body?.dataset?.userPremium ?? 0
);

const qs  = (sel, ctx = document) => ctx.querySelector(sel);
const qsa = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

/* ================== ①.أ — EduTools (Notifier + Quick Actions) ================== */
/* تم تجميع كود الأدوات التعليمية في IIFE مع إتاحة window.EduTools.init
   دون المساس بباقي الملف. */
(function (global) {
  // طبقة تنبيهات موحّدة
  const Notifier = {
    info(message, title) {
      if (typeof global.showInfo === 'function') {
        return global.showInfo(String(message ?? ''), String(title ?? ''));
      }
      alert(title ? `${title}\n\n${message}` : String(message ?? ''));
    },
    error(message) {
      if (typeof global.showError === 'function') {
        return global.showError(String(message ?? 'حدث خطأ غير متوقع'));
      }
      alert(`خطأ\n\n${String(message ?? 'حدث خطأ غير متوقع')}`);
    },
  };

  // مؤشرات تمرير (يدعم RTL والـ scrollLeft السالب)
  function eduSetupScrollIndicators(container, opts) {
    const left  = qs(opts.leftIndicatorSelector);
    const right = qs(opts.rightIndicatorSelector);
    if (!container || !left || !right) return;

    const isRTL = getComputedStyle(container).direction === 'rtl';
    function update() {
      let scrollLeft = container.scrollLeft;
      const maxScroll = container.scrollWidth - container.clientWidth;
      if (isRTL && scrollLeft < 0) scrollLeft = -scrollLeft;

      if (scrollLeft <= 10) {
        left.style.opacity  = isRTL ? '1' : '0';
        right.style.opacity = isRTL ? '0' : '1';
      } else if (scrollLeft >= maxScroll - 10) {
        left.style.opacity  = isRTL ? '0' : '1';
        right.style.opacity = isRTL ? '1' : '0';
      } else {
        left.style.opacity = right.style.opacity = '1';
      }
    }
    container.addEventListener('scroll', update);
    global.addEventListener('resize', update);
    setTimeout(update, 100);
  }

  function defaultSoonMessageBuilder(toolTitle, opts) {
    const lines = Array.isArray(opts.messageLines) && opts.messageLines.length
      ? opts.messageLines
      : [
          'قريبًا ✨',
          '',
          'نقوم بتوفير الأدوات التعليمية تدريجيًا بالتزامن مع انطلاق المنصّة.',
          'تابعنا—سيتم تفعيل هذه الأداة قريبًا بإذن الله!',
        ];
    return {
      title: `${opts.soonTitlePrefix}${toolTitle || 'أداة تعليمية'}`,
      body: lines.join('\n'),
    };
  }

  // ربط الكروت برسالة "قريبًا" + دعم Premium وoverride بالنصوص
  function eduBindQuickActions(container, opts) {
    if (!container || container.__eduToolsBound) return;

    container.addEventListener('click', (e) => {
      const card = e.target.closest(opts.cardSelector);
      if (!card) return;

      const toolTitle =
        (card.querySelector(opts.titleSelector)?.textContent || card.dataset.title || 'أداة تعليمية').trim();

      const customTitle = card.dataset.soonTitle;
      const customBody  = card.dataset.soonBody;

      const premRequired = String(card.dataset.premium || '0') === '1';
      const userIsPremium = Number(
        global.USER_IS_PREMIUM ??
        document.documentElement?.dataset?.userPremium ??
        document.body?.dataset?.userPremium ?? 0
      ) === 1;

      let title, body;
      if (premRequired && !userIsPremium) {
        title = `${toolTitle} — للمشتركين المميزين`;
        body = [
          'هذه الأداة متاحة للمشتركين في الباقة المميزة.',
          'اشترك الآن للوصول الكامل إلى الأدوات التعليمية ✨',
        ].join('\n');
      } else if (customTitle || customBody) {
        title = customTitle || `${opts.soonTitlePrefix}${toolTitle}`;
        body  = customBody  || (defaultSoonMessageBuilder(toolTitle, opts).body);
      } else {
        const msg = defaultSoonMessageBuilder(toolTitle, opts);
        title = msg.title;
        body  = msg.body;
      }

      if (typeof opts.onClick === 'function') {
        const handled = opts.onClick({ card, toolTitle, title, body, Notifier });
        if (handled === true) return;
      }

      opts.notifier.info(body, title);
    });

    container.__eduToolsBound = true;
  }

  function initEduTools(userOptions = {}) {
    const opts = {
      containerSelector: '.quick-actions-scroll',
      cardSelector: '.action-card',
      titleSelector: '.action-title',
      leftIndicatorSelector: '.scroll-indicator.left',
      rightIndicatorSelector: '.scroll-indicator.right',
      soonTitlePrefix: 'قريبًا — ',
      messageLines: [
        'قريبًا ✨',
        '',
        'نقوم بتوفير الأدوات التعليمية تدريجيًا بالتزامن مع انطلاق المنصّة.',
        'تابعنا—سيتم تفعيل هذه الأداة قريبًا بإذن الله!',
      ],
      notifier: Notifier,
      onClick: null,
      ...userOptions,
    };

    const container = qs(opts.containerSelector);
    if (!container) return;

    eduSetupScrollIndicators(container, opts);
    eduBindQuickActions(container, opts);
  }

  global.EduTools = global.EduTools || { init: initEduTools, Notifier };
})(window);

/* ================== حارس تهيئة + مراقبة DOM ================== */
let __dashAbortController = null;
let __dashMO = null; // MutationObserver

function getHashSection() {
  const raw = (location.hash || '').replace(/^#\/?/, '');
  return (raw.split('?')[0] || '').trim();
}

async function initDashboardNow() {
  // أوقف أي طلبات جارية (لو تنقلت سريعًا)
  if (__dashAbortController) { try { __dashAbortController.abort(); } catch {} }
  __dashAbortController = new AbortController();

  try {
    const container = await waitForElement('#upcomingTasks', 6000);
    await loadUpcomingTasks(container, __dashAbortController.signal);
    attachTaskActionHandlers(container);
    try { activateButtonHoverEffects(container); } catch {}

    // ✅ أعِد ربط أدوات EduTools بعد حقن صفحة الداشبورد
    try { window.EduTools?.init?.(); } catch {}
  } catch (err) {
    console.warn('[dashboard] initDashboardNow failed:', err);
  }
}

function ensureDomObserver() {
  if (__dashMO) return;
  const root = document.getElementById('student-content-area') || document.body;

  __dashMO = new MutationObserver((mutations) => {
    for (const m of mutations) {
      for (const node of m.addedNodes) {
        if (!(node instanceof Element)) continue;
        if (node.id === 'upcomingTasks' || node.querySelector?.('#upcomingTasks')) {
          // تم حقن صفحة الداشبورد الآن -> فعّلها فورًا
          initDashboardNow().catch(() => {});
          return;
        }
      }
    }
  });

  try { __dashMO.observe(root, { childList: true, subtree: true }); } catch {}

  // لو قررت ترسل حدث من الراوتر بعد تحميل أي قسم:
  // window.dispatchEvent(new CustomEvent('students:section-loaded', {detail:{section}}));
  window.addEventListener('students:section-loaded', (e) => {
    if (e?.detail?.section === 'dashboard') initDashboardNow().catch(() => {});
  });
}

/* ================== ② أدوات المودالات ================== */
const ModalUtils = {
  findOverlay() {
    const ids = ['viewModalOverlay', 'taskViewModal', 'viewTaskModal'];
    for (const id of ids) { const el = document.getElementById(id); if (el) return el; }
    return null;
  },
  findInnerBox(overlay) {
    if (!overlay) return null;
    if (overlay.classList.contains('modal')) return overlay;
    const sel = '.modal, .modal-dialog, .modal-box, .view-modal, .modal-content, .page-card';
    return overlay.querySelector(sel);
  },
  open() {
    const overlay = this.findOverlay();
    if (!overlay) { alert('لم يتم العثور على عنصر المودال'); return; }
    const box = this.findInnerBox(overlay);
    overlay.classList.add('active','show');
    if (box) box.classList.add('active','show','open');
    overlay.style.display='block'; overlay.style.visibility='visible';
    overlay.style.opacity='1'; overlay.style.pointerEvents='auto';
    overlay.style.zIndex=overlay.style.zIndex||'9999';
    if (box){ box.style.display='block'; box.style.visibility='visible'; box.style.opacity='1'; box.style.pointerEvents='auto'; }
    overlay.removeAttribute('aria-hidden');
    document.body.classList.add('modal-open');
    document.documentElement.classList.add('no-scroll');
    this.bindCloseButtons(overlay);
  },
  close() {
    const overlay = this.findOverlay(); if (!overlay) return;
    const box = this.findInnerBox(overlay);
    overlay.classList.remove('active','show');
    if (box) box.classList.remove('active','show','open');
    overlay.style.display=overlay.style.visibility=overlay.style.opacity=overlay.style.pointerEvents=overlay.style.zIndex='';
    if (box){ box.style.display=box.style.visibility=box.style.opacity=box.style.pointerEvents=''; }
    overlay.setAttribute('aria-hidden','true');
    document.body.classList.remove('modal-open');
    document.documentElement.classList.remove('no-scroll');
  },
  bindCloseButtons(overlay) {
    if (!overlay) return;
    const selectors = ['[data-close]','.modal-close','#viewCloseBtn','#viewModalCloseFooter'];
    selectors.forEach(sel => {
      qsa(sel, overlay).forEach(btn => {
        if (btn.__boundClose) return;
        btn.addEventListener('click', e => { e.preventDefault(); ModalUtils.close(); });
        btn.__boundClose = true;
      });
    });
    if (!overlay.__boundBackdrop) {
      overlay.addEventListener('click', e => { if (e.target === overlay) ModalUtils.close(); });
      overlay.__boundBackdrop = true;
    }
  }
};
function openViewModal(){ ModalUtils.open(); }
function closeViewModal(){ ModalUtils.close(); }

/* ================== ③ Fallback لمودال طلب خدمة متخصصة ================== */
window.ServiceRequestModal = window.ServiceRequestModal || (function () {
  function _get() {
    return document.getElementById('serviceRequestModal')
        || document.getElementById('ServiceRequestModal')
        || document.getElementById('service-modal');
  }
  return {
    open() {
      const el = _get();
      if (!el) { alert('Modal الخدمة غير موجود.'); return; }
      el.classList.add('active','show');
      el.style.display='block'; el.style.visibility='visible'; el.style.opacity='1';
      el.style.pointerEvents='auto'; el.style.zIndex=el.style.zIndex||'9999';
      document.body.classList.add('modal-open'); document.documentElement.classList.add('no-scroll');
      if (!el.__boundBackdrop) {
        el.addEventListener('click', e => { if (e.target === el) ServiceRequestModal.close(); });
        el.__boundBackdrop = true;
      }
    },
    close() {
      const el = _get(); if (!el) return;
      el.classList.remove('active','show');
      el.style.display=el.style.visibility=el.style.opacity=el.style.pointerEvents=el.style.zIndex='';
      document.body.classList.remove('modal-open'); document.documentElement.classList.remove('no-scroll');
    }
  };
})();
function bindServiceRequestButton() {
  const btn = qs('.btn-service-request');
  if (!btn || btn.__boundSR) return;
  btn.addEventListener('click', e => { e.preventDefault(); window.ServiceRequestModal.open(); });
  btn.__boundSR = true;
}

/* ================== ④ نقطة الدخول (تشغيل أوّل + مراقبة) ================== */
async function init() {
  try { animateWelcomeCard(); } catch (e) { console.warn('animateWelcomeCard failed', e); }
  try { activateButtonHoverEffects(); } catch (e) { console.warn('activateButtonHoverEffects failed', e); }

  // ✅ استدعاء EduTools للأدوات التعليمية (مؤشرات + كروت "قريبًا")
  try { window.EduTools?.init?.(); } catch (e) { console.warn('EduTools.init failed', e); }

  try { bindServiceRequestButton(); } catch (e) { console.warn('bindServiceRequestButton failed', e); }

  // شغّل المراقِب ليسمع أي حقن لاحق لصفحة الداشبورد
  ensureDomObserver();

  // إن كانت صفحة الداشبورد مرئية الآن (hash أو DOM)
  if (getHashSection() === 'dashboard' || qs('#upcomingTasks')) {
    initDashboardNow().catch(() => {});
  }

  // دعم سيناريو الانتقال بالهاش
  window.addEventListener('hashchange', () => {
    if (getHashSection() === 'dashboard') initDashboardNow().catch(() => {});
  });

  // أربط أزرار إغلاق مودال التفاصيل إن كان موجودًا مسبقًا
  const overlay = ModalUtils.findOverlay();
  if (overlay) ModalUtils.bindCloseButtons(overlay);
}
(function autoInitOnce() {
  if (window.__StudentDashboardInit) return;
  window.__StudentDashboardInit = true;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init().catch(() => {}));
  else init().catch(() => {});
})();

/* ================== ⑤ تحميل المهام القادمة ================== */
async function loadUpcomingTasks(container, signal) {
  try {
    container.innerHTML = `
      <div class="content-placeholder">
        <div class="loading-spinner"></div>
        <h4>جاري جلب المهام القادمة…</h4>
      </div>`;

    const url = `${STUDENTS_BASE}/dashboard/upcoming-tasks`;
    const res = await fetch(url, {
      credentials: 'include',
      headers: { 'Accept': 'application/json', 'Cache-Control': 'no-store' },
      cache: 'no-store',
      signal
    });

    if (res.status === 401) {
      container.innerHTML = `
        <div class="no-tasks">
          تحتاج إلى تسجيل الدخول لاستخدام لوحة الطالب.
          <div style="margin-top:8px">
            <a class="action-btn" href="/login"><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</a>
          </div>
        </div>`;
      return;
    }

    const payload = await res.json();
    const tasks = Array.isArray(payload) ? payload : (payload?.data ?? payload?.tasks ?? []);

    if (payload?.reason === 'profile_incomplete') {
      container.innerHTML = `
        <div class="no-tasks">
          حسابك بحاجة لإكمال الملف الشخصي (الجامعة، التخصص، السنة...).
          <div style="margin-top:8px">
            <a class="action-btn" href="/auth/complete_profile">
              <i class="far fa-id-card"></i> إكمال الملف الشخصي
            </a>
          </div>
        </div>`;
      return;
    }
    if (payload?.reason === 'no_profile') {
      container.innerHTML = `
        <div class="no-tasks">
          لم يتم العثور على ملف الطالب في المنصة. الرجاء إعادة فتح الداشبورد بعد لحظات،
          أو التواصل مع الدعم إن استمرت المشكلة.
        </div>`;
      return;
    }

    if (!Array.isArray(tasks) || tasks.length === 0) {
      container.innerHTML = `<div class="no-tasks">لا توجد مهام قادمة حالياً</div>`;
      return;
    }

    let html = '';
    let adInjected = false;
    tasks.forEach((task, index) => {
      html += renderTaskCard(task);
      // إدراج الإعلان بعد المهمة الثانية (index 1)
      if (index === 1) {
        html += `
          <div class="task-card dashboard-ad-card" style="padding: 10px; display: flex; align-items: center; justify-content: center; background: var(--surface-0); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); grid-column: span 1;">
            <div style="width: 100%; text-align: center; overflow: hidden;">
              <!-- DashboardTaskAd -->
              <ins class="adsbygoogle"
                   style="display:block"
                   data-ad-client="ca-pub-3572625518315562"
                   data-ad-slot="8742899911"
                   data-ad-format="auto"
                   data-full-width-responsive="true"></ins>
            </div>
          </div>
        `;
        adInjected = true;
      }
    });

    container.innerHTML = html;
    activateButtonHoverEffects(container);

    // تفعيل إعلان أدسنس بعد الحقن في DOM
    if (adInjected) {
      if (!document.querySelector('script[src*="adsbygoogle.js"]')) {
        const script = document.createElement('script');
        script.src = "https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3572625518315562";
        script.async = true;
        script.crossOrigin = "anonymous";
        document.head.appendChild(script);
      }
      setTimeout(() => {
        try {
          (window.adsbygoogle = window.adsbygoogle || []).push({});
        } catch (e) {
          console.error("AdSense Injection Error: ", e);
        }
      }, 500);
    }
  } catch (err) {
    if (err?.name === 'AbortError') return; // تم الإلغاء بسبب تنقل سريع
    console.error('فشل في تحميل المهام:', err);
    container.innerHTML = `<div class="no-tasks">حدث خطأ أثناء جلب المهام</div>`;
  }
}

/* ================== ⑥ أدوات تنقّل الهش ================== */
function buildTaskDetailsHash(id, week, co) {
  const p = new URLSearchParams();
  p.set('task', String(id));
  if (co)   p.set('co', String(co));
  if (week) p.set('week', String(week));
  return `#/task-details?${p.toString()}`;
}
function forceHashHandlers() {
  try { window.dispatchEvent(new HashChangeEvent('hashchange')); } catch {}
  try { window.dispatchEvent(new Event('hashchange')); } catch {}
  try {
    if (typeof window.onhashchange === 'function') {
      try { window.onhashchange(new HashChangeEvent('hashchange')); } catch { window.onhashchange(); }
    }
  } catch {}
}
function navigateToHash(hash) {
  if (window.StudentRouter && typeof window.StudentRouter.navigate === 'function') {
    try { window.StudentRouter.navigate(hash); return; } catch {}
  }
  const m = hash.match(/^#\/([^?]+)/);
  const section = m ? m[1] : null;
  const q       = new URLSearchParams(hash.split('?')[1] || '');
  const queryObj = Object.fromEntries(q.entries());
  if (typeof window.loadSection === 'function' && section) {
    try { window.loadSection(section, queryObj); return; } catch {}
  }
  if (location.hash === hash) {
    const withTs = hash + (hash.includes('?') ? '&' : '?') + `_ts=${Date.now()}`;
    location.hash = withTs;
  } else {
    location.hash = hash;
  }
  setTimeout(forceHashHandlers, 0);
}

/* ================== ⑦ بطاقة المهمة بزر واحد ================== */
function renderTaskCard(task = {}) {
  const title      = task.title ?? '-';
  const due        = task.due_date ? formatDate(task.due_date) : '-';
  const courseName = task.course_name ?? task.course ?? '';

  const taskId = String(task.id ?? '');
  const week   = task.week_number ?? task.week ?? '';
  const co     = task.course_offering_id ?? task.co_id ?? '';

  const href = (taskId && week && co) ? buildTaskDetailsHash(taskId, week, co) : '#';

  return `
    <div class="task-card">
      <div class="task-header">
        <div class="task-title">${escapeHTML(title)}</div>
        <div class="task-date"><i class="far fa-calendar-alt"></i> ${escapeHTML(due)}</div>
      </div>
      <div class="task-lable course-name">
        <i class="fas fa-book"></i> ${escapeHTML(courseName)}
      </div>
      <div class="task-actions">
        <a 
          class="action-btn view-btn"
          href="${href}"
          data-id="${taskId}"
          data-week="${String(week)}"
          data-co="${String(co)}"
          title="تفاصيل المهمة"
        >
          <i class="fas fa-eye"></i> تفاصيل المهمة
        </a>
      </div>
    </div>
  `;
}

/* ================== ⑧ الأحداث (تفويض داخل قائمة المهام) ================== */
function attachTaskActionHandlers(container) {
  if (!container || container.__delegated) return;

  container.addEventListener('click', async (e) => {
    const viewBtn = e.target.closest('.view-btn');
    if (!viewBtn) return;

    e.preventDefault(); // نتحكم بالتنقّل لضمان الفورية

    const id   = viewBtn.dataset.id;
    let week   = viewBtn.dataset.week;
    let co     = viewBtn.dataset.co;

    if (!id) return;

    // اكمل البيانات إن كانت ناقصة
    if (!week || !co) {
      try {
        const res = await fetch(`${STUDENTS_BASE}/dashboard/getTaskForView/${encodeURIComponent(id)}`, {
          credentials: 'include',
          headers: { 'Accept': 'application/json', 'Cache-Control': 'no-store' },
          cache: 'no-store'
        });
        if (res.ok) {
          const raw = await res.json();
          const t   = raw?.task ?? raw ?? {};
          week = week || t.week_number || '';
          co   = co   || t.course_offering_id || '';
        }
      } catch (err) {
        console.error('fetch getTaskForView failed', err);
      }
    }

    const hash = buildTaskDetailsHash(id, week, co);
    navigateToHash(hash);
  });

  container.__delegated = true;
}

/* ================== ⑨ المؤثرات ================== */
function animateWelcomeCard() {
  const welcomeCard = qs('.welcome-card');
  if (!welcomeCard) return;
  welcomeCard.style.opacity='0';
  welcomeCard.style.transform='translateY(20px)';
  setTimeout(() => {
    welcomeCard.style.transition='all 0.5s ease';
    welcomeCard.style.opacity='1';
    welcomeCard.style.transform='translateY(0)';
  }, 300);
}
function activateButtonHoverEffects(context = document) {
  qsa('.action-btn', context).forEach(btn => {
    if (btn.__hoverBound) return;
    btn.addEventListener('mouseenter', () => { btn.style.transform='scale(1.05)'; });
    btn.addEventListener('mouseleave', () => { btn.style.transform='scale(1)'; });
    btn.__hoverBound = true;
  });
}

/* ================== ⑩ أدوات مساعدة ================== */
function waitForElement(selector, timeout = 6000) {
  return new Promise((resolve, reject) => {
    const start = Date.now();
    (function check() {
      const el = qs(selector);
      if (el) return resolve(el);
      if (Date.now() - start >= timeout) return reject('العنصر لم يظهر خلال الوقت المحدد');
      setTimeout(check, 100);
    })();
  });
}
function formatDate(dateStr) {
  const date = new Date(dateStr);
  if (isNaN(date.getTime())) return '-';
  return date.toLocaleDateString('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' });
}
function toISODate(d) {
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}
function linkify(text) {
  const urlRegex = /(https?:\/\/[^\s<>")]+)(?![^<]*>)/g;
  const span = document.createElement('span'); span.textContent = text || '';
  const safe = span.innerHTML;
  return safe.replace(urlRegex, url => `<a href="${url}" target="_blank" rel="noopener">${url}</a>`);
}
function escapeHTML(s = '') { const span = document.createElement('span'); span.textContent = String(s); return span.innerHTML; }
function setText(selector, value) { const el = qs(selector); if (el) el.textContent = String(value ?? ''); }
function getIconClass(fileName = '') {
  const ext = String(fileName).split('.').pop().toLowerCase();
  switch (ext) {
    case 'pdf': return 'fas fa-file-pdf';
    case 'doc':
    case 'docx': return 'fas fa-file-word';
    case 'ppt':
    case 'pptx': return 'fas fa-file-powerpoint';
    case 'xls':
    case 'xlsx': return 'fas fa-file-excel';
    case 'jpg':
    case 'jpeg':
    case 'png':
    case 'gif': return 'fas fa-file-image';
    case 'zip':
    case 'rar': return 'fas fa-file-archive';
    default: return 'fas fa-file';
  }
}
function showError(message = 'حدث خطأ غير متوقع') { if (typeof window.showError === 'function') return window.showError(message); alert(`خطأ\n\n${message}`); }
function showInfo(message = 'معلومة') { if (typeof window.showInfo === 'function') return window.showInfo(message); alert(message); }

/* ================== ⑪ مؤشرات تمرير الأدوات السريعة (Shim) ================== */
/* تم استبدال المنطق الداخلي لاستخدام EduTools، مع الحفاظ على اسم الدالة
   حتى لا نغير استدعاءات init() القديمة. */
function setupScrollIndicators() {
  try { window.EduTools?.init?.(); } catch {}
}

/* ================== ⑫ “الأدوات السريعة” (Shim) ================== */
function bindQuickActions() {
  try { window.EduTools?.init?.(); } catch {}
}

/* ================== ⑬ إغلاق مودال التفاصيل (احتياطي) ================== */
document.addEventListener('click', (e) => {
  if (e.target?.matches?.('#viewCloseBtn, #viewModalCloseFooter')) {
    e.preventDefault();
    closeViewModal();
  }
});
