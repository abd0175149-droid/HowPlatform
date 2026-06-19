/* sections/course-content.js - Module for Course Content Display (init-safe, hardened) */
'use strict';

const API_BASE = (window.API_BASE || '').replace(/\/+$/, '');

let courseData = null;
let currentCourseId = null;
let searchTimeout = null;

// Cached DOM refs (تُملأ داخل init بعد حقن القالب)
let loadingState = null;
let errorState = null;
let courseContentEl = null;
let weeksContainer = null;

// Templates
let weekTemplate = null;
let taskTemplate = null;
let fileTemplate = null;

// Flags
let outsideClickBound = false;

// ======== A11y / Modal state ========
let modalKeydownBound = false;
let _bodyPrevOverflow = '';
let _prevFocused = null;
let _siblingsMadeInert = [];

/* ========================= Helpers ========================= */

// بديل مبسط لـ CSS.escape إذا لم يكن متوفّرًا
function cssEscapeLite(str) {
  return String(str).replace(/[^a-zA-Z0-9_\-]/g, ch => '\\' + ch);
}
function cssEscape(str) {
  return (typeof CSS !== 'undefined' && typeof CSS.escape === 'function')
    ? CSS.escape(String(str))
    : cssEscapeLite(str);
}

// تحويل القيم النصية/الرقمية إلى Boolean بشكل صحيح (للقيم القادمة من API)
function toBool(v) {
  if (v === true || v === false) return v;
  const s = String(v).toLowerCase().trim();
  return s === '1' || s === 'true' || s === 'yes';
}

function cacheDom() {
  loadingState    = document.getElementById('loading-state');
  errorState      = document.getElementById('error-state');

  // ✅ الصحيح حسب الـ View: 'course-content'
  courseContentEl = document.getElementById('course-content');

  weeksContainer  = document.getElementById('weeks-container');

  weekTemplate = document.getElementById('week-template');
  taskTemplate = document.getElementById('task-template');
  fileTemplate = document.getElementById('file-template');
}

function ensureLayoutOrError() {
  const ok = loadingState && errorState && courseContentEl && weeksContainer &&
             weekTemplate && taskTemplate && fileTemplate;
  if (!ok) {
    console.error('Course content layout missing required nodes.');
    showError('تعذّر تهيئة واجهة المساق. يرجى تحديث الصفحة.');
  }
  return ok;
}

function escapeHtml(str) {
  return String(str)
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'",'&#039;');
}

function linkifySafe(text) {
  const span = document.createElement('span');
  span.textContent = text || '';
  const safe = span.innerHTML;
  return safe.replace(/(https?:\/\/[^\s<>"]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
}

/* ========================= Modal A11y utilities ========================= */

const FOCUSABLE_SELECTOR = [
  'a[href]',
  'area[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  'details',
  '[tabindex]:not([tabindex^="-"])'
].join(',');

function getFocusableIn(container) {
  return Array.from(container.querySelectorAll(FOCUSABLE_SELECTOR))
    .filter(el => el.offsetParent !== null || el === document.activeElement);
}

function getTopLevelSiblingsExcept(node) {
  const siblings = [];
  const children = Array.from(document.body.children);
  for (const el of children) {
    if (el === node) continue;
    siblings.push(el);
  }
  return siblings;
}

function toggleSiblingsInert(overlay, makeInert) {
  const siblings = getTopLevelSiblingsExcept(overlay);
  if (makeInert) {
    _siblingsMadeInert = [];
    siblings.forEach(el => {
      // لا تجعل عناصر داخل <script> أو <style> إلخ
      if (!(el instanceof HTMLElement)) return;
      if (!el.hasAttribute('inert')) {
        el.setAttribute('inert', '');
        _siblingsMadeInert.push(el);
      }
      // إضافة aria-hidden لتخفيف ضجيج قارئات الشاشة (اختياري)
      if (!el.hasAttribute('aria-hidden')) el.setAttribute('aria-hidden', 'true');
    });
  } else {
    // أزل inert فقط من العناصر التي أضفناها نحن
    _siblingsMadeInert.forEach(el => el.removeAttribute('inert'));
    siblings.forEach(el => {
      if (el.getAttribute && el.getAttribute('aria-hidden') === 'true') {
        el.removeAttribute('aria-hidden');
      }
    });
    _siblingsMadeInert = [];
  }
}

/* ========================= Task View Modal (mount once) ========================= */

// فتح المودال + قفل تمرير الخلفية + A11y
function openViewModal() {
  ensureTaskViewModalMounted(); // تأكد دومًا قبل الفتح
  const overlay = document.getElementById('viewModalOverlay');
  if (!overlay) return;

  // خزّن العنصر المركّز قبل الفتح
  _prevFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;

  // فعّل المودال
  overlay.classList.add('active');
  overlay.removeAttribute('aria-hidden'); // لا تُخفِ المودال عن قارئ الشاشة
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('tabindex', '-1'); // حتى يمكن التركيز على الحاوية

  // انقل التركيز أولاً إلى زر الإغلاق داخل المودال (أو أول عنصر قابل للتركيز)
  const closeBtn = document.getElementById('viewCloseBtn');
  const focusables = getFocusableIn(overlay);
  if (closeBtn && typeof closeBtn.focus === 'function') {
    closeBtn.focus({ preventScroll: true });
  } else if (focusables[0]) {
    focusables[0].focus({ preventScroll: true });
  } else {
    overlay.focus({ preventScroll: true });
  }

  // بعد نقل التركيز للمودال، فعّل inert على بقية الصفحة
  toggleSiblingsInert(overlay, true);

  // قفل تمرير الخلفية بصرياً
  _bodyPrevOverflow = document.body.style.overflow;
  document.body.style.overflow = 'hidden';
  document.body.classList.add('modal-open');

  // مصيدة تركيز (Tab/Shift+Tab)
  if (!modalKeydownBound) {
    document.addEventListener('keydown', trapFocusInsideModal, true);
    document.addEventListener('keydown', escToClose, true);
    modalKeydownBound = true;
  }
}

function escToClose(e) {
  if (e.key === 'Escape') closeViewModal();
}

function trapFocusInsideModal(e) {
  if (e.key !== 'Tab') return;
  const overlay = document.getElementById('viewModalOverlay');
  if (!overlay || !overlay.classList.contains('active')) return;

  const focusables = getFocusableIn(overlay);
  if (focusables.length === 0) {
    e.preventDefault();
    overlay.focus();
    return;
  }

  const first = focusables[0];
  const last  = focusables[focusables.length - 1];

  if (e.shiftKey) {
    if (document.activeElement === first || document.activeElement === overlay) {
      e.preventDefault();
      last.focus();
    }
  } else {
    if (document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }
}

// إغلاق المودال + استرجاع تمرير الخلفية + تنظيف ملفات العرض
function closeViewModal() {
  const overlay = document.getElementById('viewModalOverlay');
  const filesContainer = document.getElementById('viewFilesContainer');

  if (overlay) {
    overlay.classList.remove('active');
    overlay.setAttribute('aria-hidden', 'true'); // إخفاء المودال من قارئ الشاشة
    overlay.removeAttribute('aria-modal');
  }
  if (filesContainer) filesContainer.innerHTML = "";

  // أعد تمكين الصفحة
  if (overlay) toggleSiblingsInert(overlay, false);

  // استرجاع تمرير الخلفية
  document.body.classList.remove('modal-open');
  document.body.style.overflow = _bodyPrevOverflow || '';

  // أعد التركيز للمكان السابق إذا وُجد
  if (_prevFocused && typeof _prevFocused.focus === 'function') {
    _prevFocused.focus({ preventScroll: true });
  }
  _prevFocused = null;
}

// ربط مستمعات المودال سواء كان مُولَّدًا أم موجودًا مسبقًا
function bindTaskViewModalEvents(overlay) {
  if (!overlay || overlay.dataset.bound === '1') return;

  // تفويض النقر: زر الإغلاق العلوي، زر "تم"، أو النقر خارج الصندوق
  overlay.addEventListener('click', (e) => {
    const clickedClose =
      e.target === overlay ||
      !!e.target.closest('#viewCloseBtn') ||
      !!e.target.closest('#viewModalCloseFooter') ||
      !!e.target.closest('.close-modal') ||
      !!e.target.closest('.close-btn-task');

    if (clickedClose) {
      e.preventDefault();
      closeViewModal();
    }
  });

  overlay.dataset.bound = '1';
}

function ensureTaskViewModalMounted() {
  let overlay = document.getElementById('viewModalOverlay');
  if (!overlay) {
    const wrap = document.createElement('div');
    wrap.innerHTML = `
      <div class="modal-overlay" id="viewModalOverlay" role="dialog" aria-modal="true" aria-hidden="true" dir="rtl">
        <div class="view-modal">
          <div class="modal-header">
            <div class="modal-header-content">
              <h2><i class="fas fa-tasks" aria-hidden="true"></i> <span id="viewTaskTitle">-</span></h2>
              <div class="secondary-info-grid-header">
                <div class="info-item-header"><i class="fas fa-university" aria-hidden="true"></i><span class="value" id="viewUniversity">-</span></div>
                <div class="info-item-header"><i class="fas fa-book" aria-hidden="true"></i><span class="value" id="viewCourse">-</span></div>
                <div class="info-item-header"><i class="fas fa-calendar-week" aria-hidden="true"></i><span class="value" id="viewWeek">-</span></div>
                <div class="info-item-header"><i class="fas fa-clipboard-list" aria-hidden="true"></i><span class="value" id="viewTaskType">-</span></div>
                <div class="info-item-header"><i class="far fa-calendar-alt" aria-hidden="true"></i><span class="value" id="viewDueDateValue">-</span></div>
              </div>
            </div>
            <button class="close-modal" id="viewCloseBtn" aria-label="إغلاق"><i class="fas fa-times" aria-hidden="true"></i></button>
          </div>

          <div class="modal-body">
            <section class="description-section">
              <h3><i class="fas fa-align-left" aria-hidden="true"></i> وصف المهمة</h3>
              <p id="viewDescription">—</p>
            </section>

            <section class="files-section">
              <h3 class="section-title"><i class="fas fa-paperclip" aria-hidden="true"></i> الملفات</h3>
              <div class="files-container" id="viewFilesContainer"></div>
            </section>

            <div class="modal-footer">
              <button class="action-btn close-btn-task" id="viewModalCloseFooter">
                <i class="fas fa-check" aria-hidden="true"></i> تم
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
    overlay = wrap.firstElementChild;
    document.body.appendChild(overlay);
  }

  // ربط المستمعات حتى لو كانت الـ HTML قادمة من الـ PHP مسبقًا
  bindTaskViewModalEvents(overlay);
}

/* ========================= Lifecycle ========================= */

/** تهيئة الموديول مع بيانات المساق */
export function init(data) {
  try {
    courseData = data;
    currentCourseId = data?.course?.course_offering_id;

    // التقط عناصر الـ DOM الآن (بعد حقن القالب في student_router)
    cacheDom();
    ensureTaskViewModalMounted();

    if (!ensureLayoutOrError()) return;

    hideAllStates();
    renderCourseContent(data);
    showContent();

    // تحديث آخر وصول
    updateLastAccess(currentCourseId);
  } catch (error) {
    console.error('Error initializing course content:', error);
    showError('حدث خطأ في تحميل محتوى المساق');
  }
}

/** تنظيف الموديول */
export function cleanup() {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
    searchTimeout = null;
  }
  clearContent();
  hideAllStates();

  // تأكد من إغلاق المودال وإلغاء inert إن بقي مفتوح
  const overlay = document.getElementById('viewModalOverlay');
  if (overlay && overlay.classList.contains('active')) {
    closeViewModal();
  }

  courseData = null;
  currentCourseId = null;
}

/* ========================= UI State ========================= */
function hideAllStates() {
  if (loadingState)    loadingState.style.display = 'none';
  if (errorState)      errorState.style.display   = 'none';
  if (courseContentEl) courseContentEl.style.display = 'none';
}

function showLoading() {
  hideAllStates();
  if (loadingState) loadingState.style.display = 'flex';
}

function showError(message = 'حدث خطأ غير متوقع') {
  hideAllStates();
  if (errorState) {
    errorState.style.display = 'block';
    const errorMessage = errorState.querySelector('.error-message');
    if (errorMessage) errorMessage.textContent = message;
  }
}

function showContent() {
  hideAllStates();
  if (courseContentEl) courseContentEl.style.display = 'block';
}

function clearContent() {
  if (weeksContainer) weeksContainer.innerHTML = '';
}

/* ========================= Render ========================= */

function renderCourseContent(data) {
  try {
    if (!data) return;

    renderCourseHeader(data.course, data.stats);
    renderProgressSection(data.stats);
    addSearchBar();         // idempotent
    renderWeeks(data.weeks);
    addEventListeners();    // مساحة لأي مستمعات عامة لاحقًا
  } catch (error) {
    console.error('Error rendering course content:', error);
    throw error;
  }
}

/** رأس المساق */
function renderCourseHeader(course = {}, stats = {}) {
  const codeEl  = document.getElementById('course-code');
  const titleEl = document.getElementById('course-title');
  const descEl  = document.getElementById('course-description');

  if (codeEl)  codeEl.textContent  = course.course_code || 'غير محدد';
  if (titleEl) titleEl.textContent = course.course_name || 'اسم المساق';
  if (descEl)  descEl.textContent  = course.course_description || 'لا يوجد وصف متاح';

  updateStatistic('total-weeks', `${stats.total_weeks ?? 0} أسبوع`);
  updateStatistic('total-tasks', `${stats.total_tasks ?? 0} مهمة`);
  updateStatistic('total-files', `${stats.total_files ?? 0} ملف`);
}

/** قسم التقدم */
function renderProgressSection(stats = {}) {
  const total   = Number(stats.total_weeks ?? 0);
  const avail   = Number(stats.available_weeks ?? 0);
  const percent = total > 0 ? Math.round((avail / total) * 100) : 0;

  const pctEl  = document.getElementById('progress-percentage');
  const fillEl = document.getElementById('progress-fill');

  if (pctEl)  pctEl.textContent = `${percent}%`;
  if (fillEl) fillEl.style.width = `${percent}%`;

  updateStatistic('available-weeks', avail);
  updateStatistic('upcoming-weeks', Number(stats.upcoming_weeks ?? 0));
  updateStatistic('locked-weeks',   Number(stats.locked_weeks ?? 0));
}

function updateStatistic(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

/** الأسابيع */
function renderWeeks(weeks) {
  if (!weeksContainer) return;
  weeksContainer.innerHTML = '';

  if (!Array.isArray(weeks) || weeks.length === 0) {
    weeksContainer.innerHTML = `
      <div class="empty-state">
        <i class="fas fa-calendar-times fa-3x"></i>
        <h3>لا توجد أسابيع متاحة</h3>
        <p>سيتم إضافة المحتوى قريباً</p>
      </div>`;
    return;
  }

  weeks.forEach((week, index) => {
    const el = createWeekElement(week, index);
    weeksContainer.appendChild(el);
  });
}

function createWeekElement(week, index) {
  const tpl = weekTemplate?.content?.cloneNode(true);
  if (!tpl) return document.createDocumentFragment();

  const cont     = tpl.querySelector('.week-container');
  const marker   = tpl.querySelector('.week-marker');
  const card     = tpl.querySelector('.week-card');
  const title    = tpl.querySelector('.week-title');
  const dateText = tpl.querySelector('.week-date-text');
  const status   = tpl.querySelector('.week-status');
  const grid     = tpl.querySelector('.tasks-grid');
  const empty    = tpl.querySelector('.empty-week');

  cont?.setAttribute('data-week-id', week.weekly_content_id ?? '');

  if (marker) {
    marker.textContent = week.week_number ?? '';
    marker.classList.add(getWeekMarkerClass(week.week_status));
  }
  if (week.week_status === 'locked' && card) card.classList.add('locked');

  if (title)    title.textContent    = week.week_title || `الأسبوع ${week.week_number}`;
  if (dateText) dateText.textContent = `تاريخ النشر: ${week.publish_date ? formatDate(week.publish_date) : 'غير محدد'}`;

  if (status) {
    status.textContent = getWeekStatusText(week.week_status);
    status.classList.add(`status-${week.week_status}`);
  }

  if (Array.isArray(week.tasks) && week.tasks.length > 0) {
    if (empty) empty.style.display = 'none';
    week.tasks.forEach(task => {
      const taskEl = createTaskElement(task);
      grid?.appendChild(taskEl);
    });
  } else {
    if (empty) empty.style.display = 'block';
  }

  setTimeout(() => {
    if (cont) cont.style.animationDelay = `${index * 0.1}s`;
  }, 100);

  return tpl;
}

function createTaskElement(task) {
  const tpl = taskTemplate?.content?.cloneNode(true);
  if (!tpl) return document.createDocumentFragment();

  const card     = tpl.querySelector('.task-card');
  const title    = tpl.querySelector('.task-title');
  const typeEl   = tpl.querySelector('.task-type');
  const dateText = tpl.querySelector('.task-date-text');
  const desc     = tpl.querySelector('.task-description');
  const filesSec = tpl.querySelector('.files-section');
  const filesUl  = tpl.querySelector('.files-list');
  const viewBtn  = tpl.querySelector('.view-btn');
  const submitBtn= tpl.querySelector('.submit-btn');

  card?.setAttribute('data-task-id', task.task_id ?? '');

  if (title) title.textContent = task.task_title || 'مهمة بدون عنوان';

  const typeName = task.task_type_name || 'مهمة';
  if (typeEl) {
    typeEl.textContent = typeName;
    typeEl.classList.add(`type-${getTaskTypeClass(typeName)}`);
  }

  const dueText = task.due_date ? `موعد التسليم: ${formatDate(task.due_date)}` : 'لا يوجد موعد محدد';
  if (dateText) dateText.textContent = dueText;

  if (desc) desc.textContent = task.task_description || 'لا يوجد وصف متاح';

  if (Array.isArray(task.files) && task.files.length > 0) {
    if (filesSec) filesSec.style.display = 'block';
    task.files.forEach(f => filesUl?.appendChild(createFileElement(f)));
  }

  if (viewBtn)   viewBtn.addEventListener('click', () => handleTaskView(task));
  if (submitBtn) {
    submitBtn.addEventListener('click', () => handleTaskAction(task));
    const actionText = getTaskActionText(typeName);
    const iconEl = submitBtn.querySelector('i');
    if (iconEl) iconEl.className = getTaskActionIcon(typeName);

    let textNode = Array.from(submitBtn.childNodes).find(n => n.nodeType === Node.TEXT_NODE);
    if (textNode) textNode.nodeValue = ` ${actionText}`;
    else submitBtn.appendChild(document.createTextNode(` ${actionText}`));
  }

  return tpl;
}

function createFileElement(file) {
  const tpl = fileTemplate?.content?.cloneNode(true);
  if (!tpl) return document.createDocumentFragment();

  const item    = tpl.querySelector('.file-item');
  const icon    = tpl.querySelector('.file-icon');
  const name    = tpl.querySelector('.file-name');
  const details = tpl.querySelector('.file-details');
  const badge   = tpl.querySelector('.file-premium');

  item?.setAttribute('data-file-id', file.file_id ?? '');

  if (icon) icon.className = `fas ${getFileIcon(file.mime_type)} file-icon`;
  if (name) name.textContent = file.file_name || 'ملف بدون اسم';

  let det = '';
  if (toBool(file.can_download) && file.file_size_bytes) {
    det = formatFileSize(file.file_size_bytes);
  }
  if (details) details.textContent = det;

  if (toBool(file.file_is_premium) && badge) badge.style.display = 'block';

  const canDownload = toBool(file.can_download) && !!file.file_path;
  if (!canDownload) {
    if (item) { item.classList.add('file-locked'); item.style.cursor = 'not-allowed'; }
  } else {
    if (item) {
      item.style.cursor = 'pointer';
      item.addEventListener('click', () => handleFileDownload(file));
    }
  }

  return tpl;
}

/* ========================= Search ========================= */

function addSearchBar() {
  const scope = courseContentEl || document;
  const progressSection = scope.querySelector('.progress-section');
  if (!progressSection) return;

  // لا تُعيد الإنشاء إن كان موجودًا
  if (scope.querySelector('#course-search')) return;

  // أضف CSS مرة واحدة فقط
  if (!document.getElementById('course-search-styles')) {
    const styles = document.createElement('style');
    styles.id = 'course-search-styles';
    styles.textContent = `
      .search-container{margin-top:20px;position:relative}
      .search-wrapper{position:relative;max-width:400px}
      .search-wrapper input{
        width:100%;padding:12px 40px 12px 16px;border:1px solid var(--border-color);
        border-radius:8px;font-size:1rem;transition:var(--transition);background:var(--surface-0)
      }
      .search-wrapper input:focus{outline:none;border-color:var(--primary-500);box-shadow:var(--focus-ring)}
      .search-icon{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--text-muted)}
      .search-results{
        position:absolute;top:100%;left:0;right:0;background:var(--surface-0);border:1px solid var(--border-color);
        border-radius:8px;box-shadow:var(--shadow-md);max-height:300px;overflow-y:auto;z-index:1000;margin-top:4px
      }
      .search-result-item{padding:12px 16px;border-bottom:1px solid var(--border-color);cursor:pointer;transition:var(--transition)}
      .search-result-item:hover{background:var(--primary-50)}
      .search-result-item:last-child{border-bottom:none}
      .search-result-title{font-weight:600;margin-bottom:4px}
      .search-result-meta{font-size:.85rem;color:var(--text-muted)}
    `;
    document.head.appendChild(styles);
  }

  const container = document.createElement('div');
  container.className = 'search-container';
  container.innerHTML = `
    <div class="search-wrapper">
      <i class="fas fa-search search-icon"></i>
      <input type="text" id="course-search" placeholder="البحث في محتوى المساق..." autocomplete="off">
      <div id="search-results" class="search-results" style="display:none"></div>
    </div>`;
  progressSection.appendChild(container);

  const input   = scope.querySelector('#course-search');
  const results = scope.querySelector('#search-results');

  if (input) input.addEventListener('input', handleSearch);

  // تفويض أحداث على النتائج (بدل onclick inline)
  if (results && !results._delegated) {
    results.addEventListener('click', (e) => {
      const item = e.target.closest('.search-result-item');
      if (!item) return;
      scrollToResult(item.dataset.type, item.dataset.id);
      results.style.display = 'none';
    });
    results._delegated = true;
  }

  // إخفاء النتائج عند النقر خارجها — مرة واحدة فقط
  if (!outsideClickBound) {
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.search-wrapper')) {
        const rc = scope.querySelector('#search-results');
        if (rc) rc.style.display = 'none';
      }
    });
    outsideClickBound = true;
  }
}

function handleSearch(e) {
  const q = e.target.value.trim();
  const rc = (courseContentEl || document).querySelector('#search-results');
  if (!rc) return;

  if (!q || q.length < 2) { rc.style.display = 'none'; return; }
  if (searchTimeout) clearTimeout(searchTimeout);

  searchTimeout = setTimeout(async () => {
    try {
      const res = await fetch(`${API_BASE}/student/course-content/search?course_id=${encodeURIComponent(currentCourseId)}&q=${encodeURIComponent(q)}`);
      const result = await res.json();
      if (result.success) displaySearchResults(result.data, rc);
      else console.error('Search failed:', result.message);
    } catch (err) { console.error('Search error:', err); }
  }, 300);
}

function displaySearchResults(results, container) {
  if (!Array.isArray(results) || results.length === 0) {
    container.innerHTML = '<div class="search-result-item">لا توجد نتائج</div>';
    container.style.display = 'block';
    return;
  }
  const html = results.map(r => {
    const title  = escapeHtml(r.title ?? '');
    const type   = r.type === 'week' ? 'week' : 'task';
    const meta   = r.type === 'week' ? 'أسبوع' : escapeHtml(r.task_type || 'مهمة');
    const wk     = escapeHtml(String(r.week_number ?? ''));
    const id     = String(r.id);
    return `
      <div class="search-result-item" data-type="${type}" data-id="${id}">
        <div class="search-result-title">${title}</div>
        <div class="search-result-meta">${meta} - الأسبوع ${wk}</div>
      </div>`;
  }).join('');
  container.innerHTML = html;
  container.style.display = 'block';
}

function scrollToResult(type, id) {
  const selector = type === 'week'
    ? `[data-week-id="${cssEscape(id)}"]`
    : `[data-task-id="${cssEscape(id)}"]`;
  const scope = courseContentEl || document;
  const el = scope.querySelector(selector);
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.style.transition = 'all .3s ease';
    el.style.background = 'var(--primary-100)';
    el.style.transform  = 'scale(1.02)';
    setTimeout(() => { el.style.background=''; el.style.transform=''; }, 2000);
  }
}

/* ========================= Actions ========================= */

async function updateLastAccess(courseId) {
  if (!courseId) return;
  try {
    await fetch(`${API_BASE}/student/course-content/access/${encodeURIComponent(courseId)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }
    });
  } catch (error) {
    console.log('Could not update last access:', error);
  }
}

export async function getCourseStats(courseId) {
  try {
    const res = await fetch(`${API_BASE}/student/course-content/stats/${encodeURIComponent(courseId)}`);
    const result = await res.json();
    return result.success ? result.data : null;
  } catch (error) {
    console.error('Stats error:', error);
    return null;
  }
}

export async function refreshCourseContent() {
  if (!currentCourseId) return;
  showLoading();
  try {
    const res = await fetch(`${API_BASE}/student/course-content/${encodeURIComponent(currentCourseId)}`);
    const result = await res.json();
    if (result.success) {
      courseData = result.data;
      renderCourseContent(result.data);
      showContent();
    } else {
      showError(result.message || 'فشل في إعادة تحميل المحتوى');
    }
  } catch (error) {
    console.error('Refresh error:', error);
    showError('حدث خطأ في إعادة تحميل المحتوى');
  }
}

/* ========================= Actions (Implementations) ========================= */

function handleTaskAction(task) {
  try {
    const type = (task.task_type_name || '').toLowerCase();
    alert(`تشغيل إجراء المهمة (${type || 'task'}) — (placeholder)`);
  } catch (e) {
    console.error('handleTaskAction error:', e);
  }
}

// تنزيل ملف — عبر الراوت الآمن /download?p=base64url(file_path)
function handleFileDownload(file) {
  try {
    if (!file || !file.file_path) return;
    const enc = base64url(file.file_path);
    window.location.href = `${API_BASE}/download?p=${enc}`;
  } catch (e) {
    console.error('handleFileDownload error:', e);
  }
}

// أداة مساعدة: Base64-URL
function base64url(str) {
  try {
    const b64 = btoa(unescape(encodeURIComponent(String(str))));
    return b64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
  } catch {
    // fallback بسيط
    return encodeURIComponent(str);
  }
}

/* ====== View Task (same as dashboard) ====== */

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

async function handleTaskView(task) {
  try {
    ensureTaskViewModalMounted(); // ⬅️ تأكيد وجود المودال وربط المستمعات
    const id = task?.task_id || task?.id;
    if (!id) return;

    const res = await fetch(`${API_BASE}/student/dashboard/getTaskForView/${encodeURIComponent(id)}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    const { task: t = {}, files = [] } = await res.json();
    const dueDate = t.due_date ? new Date(t.due_date) : null;

    const setText = (elId, val) => {
      const el = document.getElementById(elId);
      if (el) el.textContent = (val ?? '-') + '';
    };

    setText('viewTaskTitle', t.title || '-');
    setText('viewUniversity', t.university || '-');
    setText('viewCourse', t.course || (courseData?.course?.course_name || '-'));
    setText('viewWeek', (t.week_number != null ? `أسبوع ${t.week_number}` : '-'));
    setText('viewTaskType', t.task_type || (task.task_type_name || '-'));
    setText('viewDueDateValue', dueDate ? formatDate(dueDate) : '-');

    const descEl = document.getElementById('viewDescription');
    if (descEl) descEl.innerHTML = linkifySafe(t.description || 'لا يوجد وصف.');

    const filesBox = document.getElementById('viewFilesContainer');
    if (filesBox) {
      filesBox.innerHTML = files.length
        ? files.map(f => {
            const locked  = f.is_premium == 1 && (window.USER_IS_PREMIUM == 0);
            const btnCls  = locked ? 'btn-secondary locked' : 'btn-primary';
            const btnIcon = locked ? 'fas fa-lock' : 'fas fa-download';
            const btnText = locked ? 'متاح للمشتركين المميزين' : 'تحميل';
            const dlUrl   = `${API_BASE}/student/dashboard/download/${f.file_id}`;

            return `
              <div class="file-card">
                <div class="file-header">
                  <div class="file-icon"><i class="${getIconClass(f.file_name)}"></i></div>
                  <div class="file-info"><div class="file-name">${escapeHtml(f.file_name || '')}</div></div>
                </div>
                <div class="file-actions">
                  <a href="${locked ? '#' : dlUrl}"
                     class="file-action-btn ${btnCls}"
                     ${locked ? 'title="اشترك في الباقة المميزة للوصول"' : 'target="_blank" rel="noopener"'}
                  ><i class="${btnIcon}"></i> ${btnText}</a>
                </div>
              </div>`;
          }).join('')
        : '<p>لا توجد ملفات مرفقة.</p>';
    }

    // افتح المودال بعد تجهيز المحتوى (مع A11y الصحيح)
    openViewModal();
  } catch (e) {
    console.error('handleTaskView error:', e);
    showError('فشل في تحميل بيانات المهمة');
  }
}

/* ========================= Misc ========================= */
function addEventListeners() {
  // مساحة لإضافة مستمعين عامين لاحقًا
}

/* ========================= Utils ========================= */
function getWeekMarkerClass(status) {
  switch (status) {
    case 'available': return 'available';
    case 'upcoming':  return 'upcoming';
    case 'locked':    return 'locked';
    default:          return '';
  }
}
function getWeekStatusText(status) {
  switch (status) {
    case 'available': return 'متاح';
    case 'upcoming':  return 'قريباً';
    case 'locked':    return 'مغلق';
    default:          return 'غير محدد';
  }
}
function getTaskTypeClass(typeName) {
  if (!typeName) return 'lecture';
  const name = String(typeName).toLowerCase();
  // تغطية عربية إضافية
  if (name.includes('lecture') || name.includes('محاضرة')) return 'lecture';
  if (name.includes('assignment') || name.includes('واجب') || name.includes('قبل') || name.includes('بعد')) return 'assignment';
  if (name.includes('project') || name.includes('مشروع'))   return 'project';
  if (name.includes('quiz') || name.includes('اختبار'))     return 'quiz';
  return 'lecture';
}
function getTaskActionText(typeName) {
  switch (getTaskTypeClass(typeName)) {
    case 'lecture':    return 'بدء المحاضرة';
    case 'assignment': return 'رفع الحل';
    case 'project':    return 'البدء';
    case 'quiz':       return 'بدء الاختبار';
    default:           return 'بدء المهمة';
  }
}
function getTaskActionIcon(typeName) {
  switch (getTaskTypeClass(typeName)) {
    case 'lecture':    return 'fas fa-play';
    case 'assignment': return 'fas fa-upload';
    case 'project':    return 'fas fa-code';
    case 'quiz':       return 'fas fa-clipboard-check';
    default:           return 'fas fa-play';
  }
}
function getFileIcon(mimeType) {
  if (!mimeType) return 'fa-file';
  const mt = String(mimeType);
  if (mt.includes('pdf'))                                   return 'fa-file-pdf';
  if (mt.includes('word'))                                  return 'fa-file-word';
  if (mt.includes('excel') || mt.includes('spreadsheet'))   return 'fa-file-excel';
  if (mt.includes('powerpoint') || mt.includes('presentation')) return 'fa-file-powerpoint';
  if (mt.includes('image'))                                 return 'fa-file-image';
  if (mt.includes('video'))                                 return 'fa-file-video';
  if (mt.includes('audio'))                                 return 'fa-file-audio';
  if (mt.includes('zip') || mt.includes('rar'))             return 'fa-file-archive';
  return 'fa-file';
}
function formatDate(input) {
  if (!input) return 'غير محدد';
  try {
    const d = (input instanceof Date) ? input : new Date(input);
    return new Intl.DateTimeFormat('ar-EG-u-ca-gregory', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    }).format(d);
  } catch {
    return String(input);
  }
}

function formatFileSize(bytes) {
  const b = Number(bytes || 0);
  if (!b) return '';
  const sizes = ['بايت', 'كيلوبايت', 'ميجابايت', 'جيجابايت'];
  const i = Math.floor(Math.log(b) / Math.log(1024));
  return Math.round((b / Math.pow(1024, i)) * 100) / 100 + ' ' + sizes[i];
}

/* -------------- Reload current -------------- */
export function reloadCurrentSection() {
  const section = localStorage.getItem('student_active_section');
  if (section) loadSection(section);
}

// ✅ اكشف دالة الراوتر تحت اسم آمن لا يتصادم مع أي global آخر
if (!window.__student_reloadCurrentSection) {
  window.__student_reloadCurrentSection = reloadCurrentSection;
}

/* تصدير وظائف الحالة */
export { showLoading, showError, showContent };
