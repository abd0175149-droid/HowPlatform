/* ==========================================================================
 * File: public_html/assets/js/student/dashboard.js
 * Purpose: Student dashboard interactions (upcoming tasks + modals)
 * ========================================================================== */

/* ================== ① الثوابت العامة ================== */
const API_BASE = String(window.API_BASE || '').replace(/\/+$/, '');
const USER_IS_PREMIUM = Number(
  window.USER_IS_PREMIUM ??
  document.documentElement?.dataset?.userPremium ??
  document.body?.dataset?.userPremium ?? 0
);

const qs  = (sel, ctx = document) => ctx.querySelector(sel);
const qsa = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

/* ================== ② أدوات المودالات (متسامحة مع اختلاف الـ CSS) ================== */
const ModalUtils = {
  // ابحث عن الـ overlay حسب عدة IDs محتملة
  findOverlay() {
    const ids = ['viewModalOverlay', 'taskViewModal', 'viewTaskModal'];
    for (const id of ids) {
      const el = document.getElementById(id);
      if (el) return el;
    }
    return null;
  },

  // جرّب إيجاد "صندوق" داخلي للمودال إن كان الـ overlay لا يحتوي الأنماط
  findInnerBox(overlay) {
    if (!overlay) return null;
    // إن كان الـ overlay نفسه هو المودال (يحمل class modal)، استعمله
    if (overlay.classList.contains('modal')) return overlay;
    // وإلا جرّب الأنماط الشائعة
    const sel = '.modal, .modal-dialog, .modal-box, .view-modal, .modal-content, .page-card';
    return overlay.querySelector(sel);
  },

  open() {
    const overlay = this.findOverlay();
    if (!overlay) {
      alert('لم يتم العثور على عنصر المودال: رجاءً تأكد من وجود #viewModalOverlay أو غيّر IDs في الكود.');
      return;
    }
    const box = this.findInnerBox(overlay);

    // أضف كلا الكلاسين لدعم أطر مختلفة
    overlay.classList.add('active', 'show');
    if (box) box.classList.add('active', 'show', 'open');

    // فرض أنماط العرض لتجاوز أي CSS يخفي العنصر
    overlay.style.display       = 'block';
    overlay.style.visibility    = 'visible';
    overlay.style.opacity       = '1';
    overlay.style.pointerEvents = 'auto';
    overlay.style.zIndex        = overlay.style.zIndex || '9999';

    // اختياري: بعض الأطر تعتمد transform/transition على الصندوق نفسه
    if (box) {
      box.style.display       = 'block';
      box.style.visibility    = 'visible';
      box.style.opacity       = '1';
      box.style.pointerEvents = 'auto';
    }

    // وصولية + قفل خلفية
    overlay.removeAttribute('aria-hidden');
    document.body.classList.add('modal-open');
    document.documentElement.classList.add('no-scroll');

    // أربط عناصر الإغلاق
    this.bindCloseButtons(overlay);
  },

  close() {
    const overlay = this.findOverlay();
    if (!overlay) return;
    const box = this.findInnerBox(overlay);

    overlay.classList.remove('active', 'show');
    if (box) box.classList.remove('active', 'show', 'open');

    // أزل الأنماط المفروضة (نرجع للتحكم للـ CSS)
    overlay.style.display       = '';
    overlay.style.visibility    = '';
    overlay.style.opacity       = '';
    overlay.style.pointerEvents = '';
    overlay.style.zIndex        = '';

    if (box) {
      box.style.display       = '';
      box.style.visibility    = '';
      box.style.opacity       = '';
      box.style.pointerEvents = '';
    }

    overlay.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    document.documentElement.classList.remove('no-scroll');
  },

  bindCloseButtons(overlay) {
    if (!overlay) return;
    const selectors = ['[data-close]', '.modal-close', '#viewCloseBtn', '#viewModalCloseFooter'];
    selectors.forEach(sel => {
      qsa(sel, overlay).forEach(btn => {
        if (btn.__boundClose) return;
        btn.addEventListener('click', (e) => { e.preventDefault(); ModalUtils.close(); });
        btn.__boundClose = true;
      });
    });

    if (!overlay.__boundBackdrop) {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) ModalUtils.close();
      });
      overlay.__boundBackdrop = true;
    }
  }
};

// دوال واجهة بسيطة
function openViewModal()  { ModalUtils.open(); }
function closeViewModal() { ModalUtils.close(); }

/* ================== ③ Fallback لمودال طلب خدمة متخصصة ================== */
window.ServiceRequestModal = window.ServiceRequestModal || (function () {
  function _get() {
    // IDs محتملة لمودال الخدمة
    return document.getElementById('serviceRequestModal')
        || document.getElementById('ServiceRequestModal')
        || document.getElementById('service-modal');
  }
  return {
    open() {
      const el = _get();
      if (!el) { alert('Modal الخدمة غير موجود. أضف #serviceRequestModal أو حدّث IDs.'); return; }
      // فتح بسيط متوافق
      el.classList.add('active', 'show');
      el.style.display       = 'block';
      el.style.visibility    = 'visible';
      el.style.opacity       = '1';
      el.style.pointerEvents = 'auto';
      el.style.zIndex        = el.style.zIndex || '9999';
      document.body.classList.add('modal-open');
      document.documentElement.classList.add('no-scroll');

      // إغلاق بالخلفية
      if (!el.__boundBackdrop) {
        el.addEventListener('click', (e) => { if (e.target === el) ServiceRequestModal.close(); });
        el.__boundBackdrop = true;
      }
    },
    close() {
      const el = _get(); if (!el) return;
      el.classList.remove('active', 'show');
      el.style.display       = '';
      el.style.visibility    = '';
      el.style.opacity       = '';
      el.style.pointerEvents = '';
      el.style.zIndex        = '';
      document.body.classList.remove('modal-open');
      document.documentElement.classList.remove('no-scroll');
    }
  };
})();
function bindServiceRequestButton() {
  const btn = qs('.btn-service-request');
  if (!btn || btn.__boundSR) return;
  btn.addEventListener('click', (e) => { e.preventDefault(); window.ServiceRequestModal.open(); });
  btn.__boundSR = true;
}

/* ================== ④ نقطة الدخول ================== */
export async function init() {
  try {
    animateWelcomeCard();
    activateButtonHoverEffects();
    setupScrollIndicators();
    bindQuickActions();
    bindServiceRequestButton();

    const container = await waitForElement('#upcomingTasks');
    await loadUpcomingTasks(container);
    attachTaskActionHandlers(container);

  } catch (err) {
    console.warn('❌ فشل أثناء التهيئة:', err);
  }

  // اربط أزرار إغلاق مودال التفاصيل إن كان موجودًا مسبقًا
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
async function loadUpcomingTasks(container) {
  try {
    const res = await fetch(`${API_BASE}/student/dashboard/upcoming-tasks`, {
      credentials: 'include', headers: { 'Accept': 'application/json' }
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const payload = await res.json();
    const tasks = Array.isArray(payload) ? payload : (payload?.data ?? payload?.tasks ?? []);
    if (!Array.isArray(tasks) || tasks.length === 0) {
      container.innerHTML = `<div class="no-tasks">لا توجد مهام قادمة حالياً</div>`;
      return;
    }
    container.innerHTML = tasks.map(renderTaskCard).join('');
    activateButtonHoverEffects(container);
  } catch (err) {
    console.error('فشل في تحميل المهام:', err);
    container.innerHTML = `<div class="no-tasks">حدث خطأ أثناء جلب المهام</div>`;
  }
}

function renderTaskCard(task = {}) {
  const title      = task.title ?? '-';
  const due        = task.due_date ? formatDate(task.due_date) : '-';
  const courseName = task.course_name ?? task.course ?? '';
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
        <button class="action-btn view-btn" data-id="${String(task.id ?? '')}">
          <i class="fas fa-eye"></i> التفاصيل
        </button>
        <button class="action-btn submit-btn" data-id="${String(task.id ?? '')}">
          <i class="fas fa-paper-plane"></i> انتقل إلى الأسبوع
        </button>
      </div>
    </div>
  `;
}

/* ================== ⑥ عرض تفاصيل المهمة ================== */
async function viewTask(id) {
  if (!id) return;
  try {
    const res = await fetch(`${API_BASE}/student/dashboard/getTaskForView/${encodeURIComponent(id)}`, {
      credentials: 'include', headers: { 'Accept': 'application/json' }
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    const raw   = await res.json();
    const t     = raw?.task ?? raw?.data?.task ?? raw ?? {};
    const files = raw?.files ?? raw?.data?.files ?? [];
    const specialty_ids = raw?.specialty_ids ?? raw?.data?.specialty_ids ?? [];

    const now     = new Date();
    const dueDate = t.due_date ? new Date(t.due_date) : null;

    setText('#viewTaskTitle', t.title || '-');
    setText('#viewUniversity', t.university || t.university_name || '-');
    setText('#viewCourse', t.course || t.course_name || '-');
    setText('#viewWeek', t.week_number != null ? `أسبوع ${t.week_number}` : '-');
    setText('#viewTaskType', t.task_type || t.task_type_name || '-');
    setText('#viewDueDateValue', dueDate ? toISODate(dueDate) : '-');

    const badge = qs('#viewStatusBadge');
    if (badge) {
      let statusText = 'نشِطة', cls = 'badge-primary';
      if (dueDate && dueDate < now) { statusText = 'منتهية'; cls = 'badge-danger'; }
      badge.textContent = statusText;
      badge.className = `badge ${cls}`;
    }

    const descEl = qs('#viewDescription');
    if (descEl) {
      const html = t.description ? linkify(t.description.trim()).replace(/\n/g, '<br>') : 'لا يوجد وصف.';
      descEl.innerHTML = html;
    }

    const filesBox = qs('#viewFilesContainer');
    if (filesBox) {
      filesBox.innerHTML = (Array.isArray(files) && files.length)
        ? files.map(renderFileCard).join('')
        : '<p>لا توجد ملفات مرفقة.</p>';
    }

    // افتح المودال (الآن بأسلوب قسري يضمن الظهور)
    openViewModal();

  } catch (err) {
    console.error('viewTask error:', err);
    showError('فشل في تحميل بيانات المهمة');
  }
}

function renderFileCard(f = {}) {
  const locked  = Number(f.is_premium ?? f.file_is_premium ?? 0) === 1 && Number(USER_IS_PREMIUM) === 0;
  const btnCls  = locked ? 'btn-secondary locked' : 'btn-primary';
  const btnIcon = locked ? 'fas fa-lock' : 'fas fa-download';
  const btnText = locked ? 'متاح للمشتركين المميزين' : 'تحميل';
  const url     = `${API_BASE}/student/dashboard/download/${encodeURIComponent(f.file_id ?? f.id ?? '')}`;

  return `
    <div class="file-card">
      <div class="file-header">
        <i class="${getIconClass(f.file_name)} file-icon"></i>
        <div class="file-info"><div class="file-name">${escapeHTML(f.file_name ?? 'file')}</div></div>
      </div>
      <div class="file-description">${escapeHTML(f.description ?? '')}</div>
      <div class="file-actions">
        <a href="${locked ? '#' : url}" class="file-action-btn ${btnCls}" ${locked ? 'title="اشترك في الباقة المميزة للوصول"' : 'target="_blank" rel="noopener"'}>
          <i class="${btnIcon}"></i> ${btnText}
        </a>
      </div>
    </div>
  `;
}

/* ================== ⑦ الأحداث (تفويض داخل قائمة المهام) ================== */
function attachTaskActionHandlers(container) {
  if (!container || container.__delegated) return;
  container.addEventListener('click', (e) => {
    const viewBtn = e.target.closest('.view-btn');
    if (viewBtn) { viewTask(viewBtn.dataset.id); return; }
    const submitBtn = e.target.closest('.submit-btn');
    if (submitBtn) { goToWeekByTask(submitBtn.dataset.id); return; }
  });
  container.__delegated = true;
}

function goToWeekByTask(taskId) {
  if (!taskId) return;
  const url = `${API_BASE}/student/weeks/by-task/${encodeURIComponent(taskId)}`; // عدّل إن لزم
  window.location.href = url;
}

/* ================== ⑧ المؤثرات البصرية ================== */
function animateWelcomeCard() {
  const welcomeCard = qs('.welcome-card');
  if (!welcomeCard) return;
  welcomeCard.style.opacity = '0';
  welcomeCard.style.transform = 'translateY(20px)';
  setTimeout(() => {
    welcomeCard.style.transition = 'all 0.5s ease';
    welcomeCard.style.opacity = '1';
    welcomeCard.style.transform = 'translateY(0)';
  }, 300);
}
function activateButtonHoverEffects(context = document) {
  qsa('.action-btn', context).forEach(btn => {
    if (btn.__hoverBound) return;
    btn.addEventListener('mouseenter', () => { btn.style.transform = 'scale(1.05)'; });
    btn.addEventListener('mouseleave', () => { btn.style.transform = 'scale(1)'; });
    btn.__hoverBound = true;
  });
}

/* ================== ⑨ أدوات مساعدة ================== */
function waitForElement(selector, timeout = 3000) {
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
  const span = document.createElement('span');
  span.textContent = text || '';
  const safe = span.innerHTML;
  return safe.replace(urlRegex, (url) => `<a href="${url}" target="_blank" rel="noopener">${url}</a>`);
}
function escapeHTML(s = '') {
  const span = document.createElement('span');
  span.textContent = String(s);
  return span.innerHTML;
}
function setText(selector, value) {
  const el = qs(selector);
  if (el) el.textContent = String(value ?? '');
}
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
function showError(message = 'حدث خطأ غير متوقع') {
  if (typeof window.showError === 'function') return window.showError(message);
  alert(`خطأ\n\n${message}`);
}
function showInfo(message = 'معلومة') {
  if (typeof window.showInfo === 'function') return window.showInfo(message);
  alert(message);
}

/* ================== ⑩ مؤشرات تمرير الأدوات السريعة ================== */
function setupScrollIndicators() {
  const container = qs('.quick-actions-scroll');
  const leftIndicator  = qs('.scroll-indicator.left');
  const rightIndicator = qs('.scroll-indicator.right');
  if (!container || !leftIndicator || !rightIndicator) return;
  const isRTL = getComputedStyle(container).direction === 'rtl';
  function updateIndicators() {
    let scrollLeft = container.scrollLeft;
    const maxScroll = container.scrollWidth - container.clientWidth;
    if (isRTL && scrollLeft < 0) scrollLeft = -scrollLeft;
    if (scrollLeft <= 10) {
      leftIndicator.style.opacity  = isRTL ? '1' : '0';
      rightIndicator.style.opacity = isRTL ? '0' : '1';
    } else if (scrollLeft >= maxScroll - 10) {
      leftIndicator.style.opacity  = isRTL ? '0' : '1';
      rightIndicator.style.opacity = isRTL ? '1' : '0';
    } else {
      leftIndicator.style.opacity  = '1';
      rightIndicator.style.opacity = '1';
    }
  }
  container.addEventListener('scroll', updateIndicators);
  window.addEventListener('resize', updateIndicators);
  setTimeout(updateIndicators, 100);
}

/* ================== ⑪ “الأدوات السريعة” ================== */
function bindQuickActions() {
  const container = document.querySelector('.quick-actions-scroll');
  if (!container || container.__qaBound) return;
  container.addEventListener('click', (e) => {
    const card = e.target.closest('.action-card');
    if (!card) return;
    const toolTitle = (card.querySelector('.action-title')?.textContent || 'أداة تعليمية').trim();
    const message = [
      'قريبًا ✨', '',
      'نقوم بتوفير الأدوات التعليمية تدريجيًا بالتزامن مع انطلاق المنصّة.',
      'تابعنا—سيتم تفعيل هذه الأداة قريبًا بإذن الله!'
    ].join('\n');
    if (typeof window.showInfo === 'function') window.showInfo(message, `قريبًا — ${toolTitle}`);
    else alert(`قريبًا — ${toolTitle}\n\n${message}`);
  });
  container.__qaBound = true;
}

/* ================== ⑫ إغلاق مودال التفاصيل من الوثيقة (احتياطي) ================== */
document.addEventListener('click', (e) => {
  if (e.target?.matches?.('#viewCloseBtn, #viewModalCloseFooter')) {
    e.preventDefault();
    closeViewModal();
  }
});
