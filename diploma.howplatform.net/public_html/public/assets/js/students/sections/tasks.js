/* ==========================================================================
 * File: public/assets/js/students/sections/tasks.js
 * Purpose: صفحة "كل المهام" — تبويب لكل فصل دراسي، تحميل كسول لمهام الفصل عند اختياره.
 * يُحمّل كقسم static عبر student_router (init() يُستدعى عند كل تحميل للقسم).
 * ========================================================================== */

/* ===== ثوابت ===== */
const RAW_BASE = String(window.API_BASE || '').trim().replace(/\/+$/, '');
const STUDENTS_BASE = (RAW_BASE && RAW_BASE.endsWith('/students'))
  ? RAW_BASE
  : (RAW_BASE ? `${RAW_BASE}/students` : '/students');

let _abort = null;
const _cache = new Map();   // semester_id -> tasks[] (خام، قبل الترتيب/الفلترة)
let _activeSem = null;
let _mode = 'all';          // all | alpha | date | overdue

/* ===== أدوات ===== */
function escapeHTML(s = '') {
  const span = document.createElement('span');
  span.textContent = String(s ?? '');
  return span.innerHTML;
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  try {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '—';
    return new Intl.DateTimeFormat('ar', { year: 'numeric', month: 'long', day: 'numeric' }).format(d);
  } catch { return '—'; }
}

function isOverdue(dateStr) {
  if (!dateStr) return false;
  const d = new Date(dateStr);
  return !isNaN(d.getTime()) && d.getTime() < Date.now();
}

function buildTaskDetailsHash(id, week, co) {
  const p = new URLSearchParams();
  p.set('task', String(id));
  if (co)   p.set('co', String(co));
  if (week) p.set('week', String(week));
  return `#/task-details?${p.toString()}`;
}

function navigateToHash(hash) {
  if (window.StudentRouter && typeof window.StudentRouter.navigate === 'function') {
    try { window.StudentRouter.navigate(hash); return; } catch {}
  }
  location.hash = hash;
}

/* ===== عرض البطاقات ===== */
function taskCardHTML(task = {}) {
  const id    = String(task.id ?? '');
  const week  = task.week_number ?? '';
  const co    = task.course_offering_id ?? '';
  const href  = (id && co) ? buildTaskDetailsHash(id, week, co) : '#';

  const title = escapeHTML(task.title ?? 'مهمة');
  const courseName = escapeHTML(task.course_name ?? '');
  const courseCode = escapeHTML(task.course_code ?? '');
  const courseLabel = courseCode ? `${courseName} · ${courseCode}` : courseName;

  const overdue = isOverdue(task.due_date);
  const due = formatDate(task.due_date);

  const weekChip = (week !== '' && week !== null)
    ? `<span class="t-chip week"><i class="fas fa-calendar-week"></i> الأسبوع ${escapeHTML(week)}</span>`
    : '';

  return `
    <a class="t-card" href="${href}" data-id="${id}" data-week="${escapeHTML(String(week))}" data-co="${escapeHTML(String(co))}">
      <h3 class="t-card-title">${title}</h3>
      <div class="t-card-chips">
        ${courseLabel ? `<span class="t-chip course"><i class="fas fa-book"></i> ${courseLabel}</span>` : ''}
        ${weekChip}
      </div>
      <div class="t-card-foot">
        <span class="t-due ${overdue ? 'overdue' : ''}">
          <i class="far fa-calendar-alt"></i> ${overdue ? 'انتهى: ' : ''}${due}
        </span>
        <span class="t-open">التفاصيل <i class="fas fa-arrow-left"></i></span>
      </div>
    </a>
  `;
}

function stateHTML(icon, title, sub = '') {
  return `
    <div class="tasks-state">
      <i class="${icon}"></i>
      <h3>${escapeHTML(title)}</h3>
      ${sub ? `<p>${escapeHTML(sub)}</p>` : ''}
    </div>
  `;
}

function skeletonHTML() {
  return `<div class="tasks-grid"><div class="tasks-skel"></div><div class="tasks-skel"></div><div class="tasks-skel"></div></div>`;
}

function bindCardNav(container) {
  container.querySelectorAll('.t-card').forEach(card => {
    card.addEventListener('click', (e) => {
      const href = card.getAttribute('href') || '';
      if (!href.startsWith('#/')) return;
      e.preventDefault();
      navigateToHash(href);
    });
  });
}

function showFilterBar(show) {
  const bar = document.getElementById('tasks-filter');
  if (bar) bar.style.display = show ? 'flex' : 'none';
}

/* ترتيب/فلترة مهام الفصل الحالي حسب الزر المختار */
function applyMode(rawTasks) {
  const tasks = Array.isArray(rawTasks) ? rawTasks.slice() : [];
  switch (_mode) {
    case 'alpha':
      return tasks.sort((a, b) => String(a.title || '').localeCompare(String(b.title || ''), 'ar'));
    case 'date':
      return tasks.sort((a, b) => {
        const da = a.due_date ? new Date(a.due_date).getTime() : Infinity;
        const db = b.due_date ? new Date(b.due_date).getTime() : Infinity;
        return da - db;
      });
    case 'overdue':
      return tasks.filter(t => isOverdue(t.due_date));
    case 'all':
    default:
      return tasks;
  }
}

function renderTasks(tasks) {
  const container = document.getElementById('tasks-list');
  if (!container) return;
  if (!Array.isArray(tasks) || !tasks.length) {
    const msg = _mode === 'overdue'
      ? 'لا توجد مهام متأخرة في هذا الفصل'
      : 'لا توجد مهام في هذا الفصل';
    container.innerHTML = stateHTML('fas fa-clipboard-check', msg, _mode === 'overdue' ? 'كل مهامك ضمن الموعد 👌' : 'ستظهر هنا مهام هذا الفصل عند توفّرها.');
    return;
  }
  container.innerHTML = `<div class="tasks-grid">${tasks.map(taskCardHTML).join('')}</div>`;
  bindCardNav(container);
}

/* يعرض مهام الفصل النشط بعد تطبيق الترتيب/الفلترة الحالية */
function renderCurrent() {
  if (_activeSem == null || !_cache.has(_activeSem)) return;
  const raw = _cache.get(_activeSem) || [];
  showFilterBar(raw.length > 0);
  renderTasks(applyMode(raw));
}

function setTabCount(semId, count) {
  const tab = document.querySelector(`.sem-tab[data-sem="${semId}"] .sem-tab-count`);
  if (tab) { tab.textContent = String(count); tab.style.display = 'inline-block'; }
}

/* ===== تحميل مهام فصل (مع تخزين مؤقت) ===== */
async function selectSemester(semId) {
  _activeSem = semId;

  document.querySelectorAll('.sem-tab').forEach(t => {
    t.classList.toggle('active', String(t.dataset.sem) === String(semId));
  });

  if (_cache.has(semId)) {
    renderCurrent();
    return;
  }

  const container = document.getElementById('tasks-list');
  if (container) container.innerHTML = skeletonHTML();
  showFilterBar(false);

  try { _abort?.abort(); } catch {}
  _abort = new AbortController();

  try {
    const res = await fetch(`${STUDENTS_BASE}/dashboard/all-tasks?semester_id=${encodeURIComponent(semId)}`, {
      credentials: 'include',
      headers: { 'Accept': 'application/json', 'Cache-Control': 'no-store' },
      cache: 'no-store',
      signal: _abort.signal,
    });

    if (res.status === 401) { renderState('fas fa-lock', 'انتهت الجلسة', 'يرجى تسجيل الدخول من جديد.'); return; }

    const payload = await res.json().catch(() => ({}));
    if (!res.ok) { renderState('fas fa-triangle-exclamation', 'تعذّر تحميل المهام', payload?.message || ''); return; }

    const sem = Array.isArray(payload?.semesters) ? payload.semesters.find(s => String(s.semester_id) === String(semId)) : null;
    const tasks = sem?.tasks || [];

    // إن لم يُرجِع الخادم الفصل المطلوب (مثلاً بلا تطابق) نأخذ أول مجموعة متاحة
    const finalTasks = tasks.length ? tasks : (payload?.semesters?.[0]?.tasks || []);

    _cache.set(semId, finalTasks);
    if (_activeSem === semId) renderCurrent();
    setTabCount(semId, finalTasks.length);
  } catch (err) {
    if (err?.name === 'AbortError') return;
    renderState('fas fa-triangle-exclamation', 'تعذّر تحميل المهام', 'تحقّق من الاتصال وحاول مجددًا.');
  }
}

function renderState(icon, title, sub) {
  showFilterBar(false);
  const container = document.getElementById('tasks-list');
  if (container) container.innerHTML = stateHTML(icon, title, sub);
}

function bindFilters() {
  const bar = document.getElementById('tasks-filter');
  if (!bar || bar.__bound) return;
  bar.__bound = true;
  bar.querySelectorAll('.tf-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const mode = btn.dataset.mode || 'all';
      if (mode === _mode) return;
      _mode = mode;
      bar.querySelectorAll('.tf-btn').forEach(b => b.classList.toggle('active', b === btn));
      renderCurrent();
    });
  });
}

/* ===== بناء التبويبات ===== */
function renderTabs(semesters) {
  const bar = document.getElementById('tasks-tabs');
  if (!bar) return;
  bar.innerHTML = semesters.map(s => `
    <button type="button" class="sem-tab" role="tab" data-sem="${s.semester_id}">
      <i class="fas fa-layer-group"></i>
      <span>${escapeHTML(s.semester_name)}</span>
      <span class="sem-tab-count" style="display:none"></span>
    </button>
  `).join('');

  bar.querySelectorAll('.sem-tab').forEach(tab => {
    tab.addEventListener('click', () => selectSemester(Number(tab.dataset.sem)));
  });
}

async function loadSemesters() {
  try {
    const res = await fetch(`${STUDENTS_BASE}/dashboard/semesters`, {
      credentials: 'include',
      headers: { 'Accept': 'application/json', 'Cache-Control': 'no-store' },
      cache: 'no-store',
    });

    if (res.status === 401) { renderState('fas fa-lock', 'انتهت الجلسة', 'يرجى تسجيل الدخول من جديد.'); return; }

    const payload = await res.json().catch(() => ({}));

    if (payload?.reason === 'no_profile') { renderState('fas fa-user-slash', 'لم يتم العثور على ملفك الدراسي', payload.message || ''); return; }
    if (payload?.reason === 'profile_incomplete') { renderState('fas fa-id-card', 'ملفك الشخصي غير مكتمل', payload.message || 'أكمل بياناتك لعرض المهام.'); return; }

    const semesters = Array.isArray(payload?.semesters) ? payload.semesters : [];
    if (!semesters.length) { renderState('fas fa-clipboard-check', 'لا توجد فصول دراسية', 'لم يتم العثور على مساقات بعد.'); return; }

    renderTabs(semesters);
    selectSemester(Number(semesters[0].semester_id)); // الفصل الأول نشط افتراضياً
  } catch (err) {
    renderState('fas fa-triangle-exclamation', 'تعذّر تحميل الفصول', 'تحقّق من الاتصال وحاول مجددًا.');
  }
}

function bindBack() {
  const btn = document.getElementById('tasksBackBtn');
  if (btn && !btn.__bound) {
    btn.__bound = true;
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      if (typeof window.loadSection === 'function') window.loadSection('dashboard');
      else navigateToHash('#/dashboard');
    });
  }
}

export function init() {
  if (!document.getElementById('tasks-page')) return;
  // إعادة الضبط عند كل دخول للقسم
  _cache.clear();
  _activeSem = null;
  _mode = 'all';
  bindBack();
  bindFilters();
  loadSemesters();
}

export default { init };
