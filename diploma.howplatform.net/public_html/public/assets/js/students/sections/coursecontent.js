/* sections/coursecontent.js - Module for Course Content Display (Cache-first + SWR) */
'use strict';

const API_BASE = '/students';

/* ========= Config ========= */
const COURSE_CACHE_TTL_MS = 60 * 60 * 1000; // 1h

/* ========= In-Memory ========= */
let courseData = null;
let currentCourseId = null;
let searchTimeout = null;

/* ========= Cached DOM refs ========= */
let loadingState = null;
let errorState = null;
let courseContentEl = null;
let weeksContainer = null;

/* ========= Templates ========= */
let weekTemplate = null;
let taskTemplate = null;
let fileTemplate = null;

let outsideClickBound = false;
let taskDetailsBound = false;
let taskDetailsHandler = null;

/* ========= Course Cache (localStorage + TTL) ========= */
const COURSE_CACHE_NS = 'coursectx:v1:';
const CourseCache = {
  key: id => `${COURSE_CACHE_NS}${id}`,
  get(id) {
    try {
      const raw = localStorage.getItem(this.key(id));
      if (!raw) return null;
      const obj = JSON.parse(raw);
      if (!obj || !obj.ts || !obj.data) return null;
      if (Date.now() - obj.ts > COURSE_CACHE_TTL_MS) return null;
      return obj.data;
    } catch { return null; }
  },
  set(id, data) {
    try {
      const lean = pickLeanCourseData(data);
      localStorage.setItem(this.key(id), JSON.stringify({ ts: Date.now(), data: lean }));
    } catch {}
  },
  clearFor(id) {
    try { localStorage.removeItem(this.key(id)); } catch {}
  }
};
try { window.__COURSE_CACHE__ = CourseCache; } catch {}

/* ========= Helpers ========= */
function pickLeanCourseData(data) {
  // نسخة خفيفة للتخزين — تُحافظ على ما تحتاجه الواجهة فقط
  if (!data) return null;
  return {
    course: {
      course_offering_id: data.course?.course_offering_id,
      course_id: data.course?.course_id,
      course_code: data.course?.course_code,
      course_name: data.course?.course_name,
      course_description: data.course?.course_description,
    },
    stats: {
      total_weeks: data.stats?.total_weeks ?? 0,
      total_tasks: data.stats?.total_tasks ?? 0,
      total_files: data.stats?.total_files ?? 0,
      available_weeks: data.stats?.available_weeks ?? 0,
      upcoming_weeks: data.stats?.upcoming_weeks ?? 0,
      locked_weeks: data.stats?.locked_weeks ?? 0,
    },
    weeks: Array.isArray(data.weeks) ? data.weeks.map(w => ({
      weekly_content_id: w.weekly_content_id,
      week_number: w.week_number,
      week_title: w.week_title,
      publish_date: w.publish_date,
      week_status: w.week_status,
      tasks: Array.isArray(w.tasks) ? w.tasks.map(t => ({
        task_id: t.task_id,
        task_title: t.task_title,
        task_type_name: t.task_type_name,
        task_description: t.task_description,
        due_date: t.due_date,
        files: Array.isArray(t.files) ? t.files.map(f => ({
          file_id: f.file_id,
          file_name: f.file_name,
          mime_type: f.mime_type,
          file_size_bytes: f.file_size_bytes,
          file_is_premium: f.file_is_premium,
          can_download: f.can_download
        })) : []
      })) : []
    })) : []
  };
}

/* جلب من الخادم */
async function getCourseDataNetwork(courseId, { signal } = {}) {
  const res = await fetch(`${API_BASE}/course-content/${encodeURIComponent(courseId)}?_=${Date.now()}`, { signal, credentials: 'same-origin' });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const js = await res.json();
  if (!js.success) throw new Error(js.message || 'Failed');
  return js.data;
}

/* Cache-first مع إعادة تحقق بالخلفية */
async function getCourseData(courseId, { force = false, revalidate = true, signal } = {}) {
  const cached = !force ? CourseCache.get(courseId) : null;
  if (cached) {
    // إعادة التحقق بالخلفية
    if (revalidate) {
      getCourseDataNetwork(courseId, { signal }).then(fresh => {
        CourseCache.set(courseId, fresh);
        // إن ما زلنا على نفس المساق، أعِد الرسم
        if (Number(currentCourseId) === Number(courseId)) {
          courseData = fresh;
          safeRenderCourseContent(fresh);
        }
      }).catch(() => {});
    }
    return cached;
  }
  const fresh = await getCourseDataNetwork(courseId, { signal });
  CourseCache.set(courseId, fresh);
  return fresh;
}

function cssEscapeLite(str) {
  return String(str).replace(/[^a-zA-Z0-9_\-]/g, ch => '\\' + ch);
}
function cssEscape(str) {
  return (typeof CSS !== 'undefined' && typeof CSS.escape === 'function')
    ? CSS.escape(String(str))
    : cssEscapeLite(str);
}
function toBool(v) {
  if (v === true || v === false) return v;
  const s = String(v).toLowerCase().trim();
  return s === '1' || s === 'true' || s === 'yes';
}
function cacheDom() {
  loadingState    = document.getElementById('loading-state');
  errorState      = document.getElementById('error-state');
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

/* ======== Task Details Fallback ======== */
/* إن لم يعرّف الراوتر window.openTaskDetails، ننشئ فالباك يفتح صفحة HTML مباشرة */
function ensureOpenTaskDetailsFallback() {
  if (typeof window.openTaskDetails === 'function') return;

  window.openTaskDetails = function (taskId, courseOfferingId, weeklyContentId) {
    // مسار صفحات الطلبة (يتوافق مع نمط /students/pages/...)
    const params = new URLSearchParams({
      task_id: String(taskId || ''),
      course_offering_id: String(courseOfferingId || ''),
      weekly_content_id: String(weeklyContentId || '')
    });
    const url = `${API_BASE}/pages/taskdetails?${params.toString()}`;
    // توجيه صريح ليعمل حتى بدون الراوتر
    window.location.href = url;
  };
}

/* ========================= Lifecycle ========================= */
export async function init(data) {
  try {
    // استنتاج courseOfferingId من البيانات الممرّرة أو من حالة سابقة
    const fromParamId = data?.course?.course_offering_id;
    const fromGlobal  = window.__CURRENT_COURSE_OFFERING_ID;
    const fromLS      = Number(localStorage.getItem('last_course_offering') || 0) || null;

    currentCourseId = Number(fromParamId || fromGlobal || fromLS || 0) || null;
    courseData = data || null;

    // متاح عالميًا للعودة من صفحة التفاصيل
    if (currentCourseId) window.__CURRENT_COURSE_OFFERING_ID = currentCourseId;

    // خزّن آخر Course Offering للرجوع السريع
    try { if (currentCourseId) localStorage.setItem('last_course_offering', String(currentCourseId)); } catch {}

    cacheDom();
    if (!ensureLayoutOrError()) return;

    // ✅ ضمّن فالباك لزر التفاصيل
    ensureOpenTaskDetailsFallback();

    hideAllStates();

    // ✅ Cache-first دائماً: إن وُجد كاش — اعرضه فورًا
    let initial = null;
    if (currentCourseId) initial = CourseCache.get(currentCourseId);

    if (initial) {
      courseData = initial;
      safeRenderCourseContent(initial);
      showContent();
    } else if (courseData) {
      // لا يوجد كاش لكن الراوتر مرّر بيانات — اعرضها سريعاً وخزّنها
      safeRenderCourseContent(courseData);
      showContent();
      CourseCache.set(courseData.course?.course_offering_id, courseData);
    } else {
      // لا كاش ولا بيانات — أظهر لودينغ مؤقتًا
      showLoading();
    }

    // إعادة التحقق بالخلفية:
    if (currentCourseId) {
      // لو الراوتر مرّر بيانات، استخدمها لتحديث الكاش فورًا (لن يلمع شيء إن كانت متطابقة)
      if (data?.course?.course_offering_id) {
        CourseCache.set(currentCourseId, data);
      }
      // ثم اجلب أحدث نسخة من الخادم لتحديث العرض
      getCourseData(currentCourseId, { force: !initial && !data, revalidate: true })
        .then(fresh => {
          courseData = fresh;
          safeRenderCourseContent(fresh);
          showContent();
        })
        .catch(err => {
          console.warn('Revalidate failed:', err);
          // حافظ على العرض من الكاش أو البيانات الممرّرة
          if (!initial && !courseData) showError('حدث خطأ في تحميل محتوى المساق');
        });
    }

    updateLastAccess(currentCourseId);

    // ✅ تفويض زر "التفاصيل" — ربط واحد فقط
    const root = document.getElementById('student-content-area') || document;
    if (!taskDetailsBound) {
      taskDetailsHandler = (e) => {
        const btn = e.target.closest('[data-open-task]');
        if (!btn) return;

        // دائمًا امنع السلوك الافتراضي لأن لدينا فالباك مضمون
        e.preventDefault();

        const taskId = btn.getAttribute('data-task-id');
        const coId   = btn.getAttribute('data-course-offering-id') || (window.__CURRENT_COURSE_OFFERING_ID || '');
        const weekId = btn.getAttribute('data-weekly-content-id') || '';

        // استدعِ الدالة (من الراوتر أو الفالباك)
        window.openTaskDetails(taskId, coId, weekId);
      };
      root.addEventListener('click', taskDetailsHandler, { passive: false });
      taskDetailsBound = true;
    }

  } catch (error) {
    console.error('Error initializing course content:', error);
    showError('حدث خطأ في تحميل محتوى المساق');
  }
}

export function cleanup() {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
    searchTimeout = null;
  }
  clearContent();
  hideAllStates();
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
  if (errorState)      errorState.style.display   = 'none';
  if (courseContentEl) courseContentEl.style.display = 'none';
  if (loadingState)    loadingState.style.display = 'flex';
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
  if (loadingState)    loadingState.style.display = 'none';
  if (errorState)      errorState.style.display   = 'none';
  if (courseContentEl) courseContentEl.style.display = 'block';
}
function clearContent() {
  if (weeksContainer) weeksContainer.innerHTML = '';
}

/* ========================= Render ========================= */
function safeRenderCourseContent(data) {
  try {
    renderCourseContent(data);
  } catch (e) {
    console.error('Render failed:', e);
    showError('حدث خطأ في عرض محتوى المساق');
  }
}

function renderCourseContent(data) {
  try {
    if (!data) return;
    renderCourseHeader(data.course, data.stats);
    renderProgressSection(data.stats);
    addSearchBar();
    renderWeeks(data.weeks);
    addEventListeners();
  } catch (error) {
    console.error('Error rendering course content:', error);
    throw error;
  }
}

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

function renderWeeks(weeks) {
  if (!weeksContainer) return;
  weeksContainer.innerHTML = '';

  if (!Array.isArray(weeks) || weeks.length === 0) {
    weeksContainer.innerHTML = `
      <div class="empty-state slide-up" style="text-align: center; padding: 60px 20px; background: var(--surface-0); border-radius: var(--border-radius); border: 2px dashed var(--primary-300); box-shadow: var(--shadow-sm); margin: 40px auto; max-width: 600px;">
        <div style="width: 90px; height: 90px; background: linear-gradient(135deg, var(--primary-100), var(--primary-50)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px auto; box-shadow: inset 0 0 0 4px var(--surface-0), 0 4px 15px rgba(33, 158, 188, 0.15);">
            <i class="fas fa-calendar-times" style="font-size: 2.8rem; color: var(--primary-500);"></i>
        </div>
        <h3 style="color: var(--text-strong); font-size: 1.6rem; margin-bottom: 12px; font-weight: 800;">لا توجد أسابيع متاحة</h3>
        <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 450px; margin: 0 auto; line-height: 1.6;">سيتم إضافة المحتوى التعليمي والمهام الخاصة بهذا المساق قريباً. نرجو منك التحقق مرة أخرى لاحقاً.</p>
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
      const taskEl = createTaskElement(task, week.weekly_content_id);
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

/* --------- Files chips (general vs premium) ---------- */
function renderFilesChips(files, filesSec) {
  if (!filesSec) return;

  const list = Array.isArray(files) ? files : [];
  const generalCount = list.filter(f => !toBool(f.file_is_premium)).length;
  const premiumCount = list.filter(f => toBool(f.file_is_premium)).length;

  if ((generalCount + premiumCount) === 0) {
    filesSec.style.display = 'none';
    return;
  }

  filesSec.style.display = 'block';

  const filesUl = filesSec.querySelector('.files-list');
  if (filesUl) filesUl.innerHTML = '';

  let chips = filesSec.querySelector('.files-chips');
  if (!chips) {
    chips = document.createElement('div');
    chips.className = 'files-chips';
    filesSec.appendChild(chips);
  }

  chips.innerHTML = `
    <span class="file-chip chip-general" title="ملفات عامة">
      <i class="fas fa-folder-open"></i>
      <span>ملفات عامة</span>
      <b class="chip-count">${generalCount}</b>
    </span>
    <span class="file-chip chip-premium" title="ملفات للمستخدمين المميزين">
      <i class="fas fa-crown"></i>
      <span>ملفات للمستخدمين المميزين</span>
      <b class="chip-count">${premiumCount}</b>
    </span>
  `;

  if (!document.getElementById('files-chips-styles')) {
    const st = document.createElement('style');
    st.id = 'files-chips-styles';
    st.textContent = `
      .files-chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}
      .file-chip{
        display:inline-flex;align-items:center;gap:8px;
        padding:6px 10px;border:1px solid var(--border-color, #e5e7eb);
        border-radius:999px;background:var(--surface-0, #fff);
        font-size:.9rem;line-height:1
      }
      .file-chip .chip-count{
        display:inline-block;min-width:20px;text-align:center;
        border-radius:999px;padding:2px 8px;border:1px solid var(--border-color, #e5e7eb)
      }
      .file-chip.chip-general .chip-count{background:var(--primary-50, #eef6ff)}
      .file-chip.chip-premium .chip-count{background:var(--accent-50, #fff3e0)}
    `;
    document.head.appendChild(st);
  }
}

function createTaskElement(task, weeklyContentId) {
  const tpl = taskTemplate?.content?.cloneNode(true);
  if (!tpl) return document.createDocumentFragment();

  const card     = tpl.querySelector('.task-card');
  const title    = tpl.querySelector('.task-title');
  const typeEl   = tpl.querySelector('.task-type');
  const dateText = tpl.querySelector('.task-date-text');
  const desc     = tpl.querySelector('.task-description');
  const filesSec = tpl.querySelector('.files-section');
  const viewBtn  = tpl.querySelector('.view-btn');
  const submitBtn= tpl.querySelector('.submit-btn'); // سنزيله إن وُجد في القالب

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

  // ✅ تشِبس للملفات
  renderFilesChips(task.files, filesSec);

  // زر التفاصيل → نحدد href فالباك مفيد (يفيد فتح تبويب جديد أيضًا)
  if (viewBtn) {
    const fallbackUrl = (() => {
      const params = new URLSearchParams({
        task_id: String(task.task_id || ''),
        course_offering_id: String(currentCourseId || ''),
        weekly_content_id: String(weeklyContentId || '')
      }).toString();
      return `${API_BASE}/pages/taskdetails?${params}`;
    })();

    viewBtn.setAttribute('href', fallbackUrl);
    viewBtn.setAttribute('data-open-task', '1');
    viewBtn.setAttribute('data-task-id', String(task.task_id || ''));
    viewBtn.setAttribute('data-weekly-content-id', String(weeklyContentId || ''));
    viewBtn.setAttribute('data-course-offering-id', String(currentCourseId || ''));

    viewBtn.addEventListener('click', (e) => {
      // لدينا فالباك مضمون، لذا نمنع السلوك الافتراضي ونستدعي الدالة دائمًا
      e.preventDefault();
      window.openTaskDetails(task.task_id, currentCourseId, weeklyContentId);
    });
  }

  // احذف زر الإجراء الثاني إن كان موجودًا في القالب
  if (submitBtn && submitBtn.parentNode) submitBtn.parentNode.removeChild(submitBtn);

  return tpl;
}

function createFileElement(file) {
  // لم يعد مستخدمًا لسرد الملفات، لكن نُبقيه للاستخدام المستقبلي إن لزم
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

  const canDownload = toBool(file.can_download);
  if (item) {
    if (!canDownload) {
      item.classList.add('file-locked');
      item.style.cursor = 'not-allowed';
    } else {
      item.style.cursor = 'pointer';
      item.addEventListener('click', () => {
        window.location.href = `${API_BASE}/course-content/download/${encodeURIComponent(file.file_id)}`;
      });
    }
  }

  return tpl;
}

/* ========================= Search ========================= */
function addSearchBar() {
  const scope = courseContentEl || document;
  const progressSection = scope.querySelector('.progress-section');
  if (!progressSection) return;

  if (scope.querySelector('#course-search')) return;

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

  if (results && !results._delegated) {
    results.addEventListener('click', (e) => {
      const item = e.target.closest('.search-result-item');
      if (!item) return;
      scrollToResult(item.dataset.type, item.dataset.id);
      results.style.display = 'none';
    });
    results._delegated = true;
  }

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
      const res = await fetch(`${API_BASE}/course-content/search?course_id=${encodeURIComponent(currentCourseId)}&q=${encodeURIComponent(q)}`);
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
    await fetch(`${API_BASE}/course-content/access/${encodeURIComponent(courseId)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }
    });
  } catch (error) {
    console.log('Could not update last access:', error);
  }
}

export async function getCourseStats(courseId) {
  // يمكن لاحقًا جعله Cache-first أيضًا، لكنه غير مؤثر على العرض
  try {
    const res = await fetch(`${API_BASE}/course-content/stats/${encodeURIComponent(courseId)}`);
    const result = await res.json();
    return result.success ? result.data : null;
  } catch (error) {
    console.error('Stats error:', error);
    return null;
  }
}

export async function refreshCourseContent() {
  if (!currentCourseId) return;

  // ✅ فضّل الكاش أولًا على الواجهة الحالية
  const cached = CourseCache.get(currentCourseId);
  if (cached) {
    courseData = cached;
    safeRenderCourseContent(cached);
    showContent();
  } else {
    showLoading();
  }

  // ثم حدِّث من الشبكة وأعِد العرض
  try {
    const fresh = await getCourseDataNetwork(currentCourseId);
    CourseCache.set(currentCourseId, fresh);
    courseData = fresh;
    safeRenderCourseContent(fresh);
    showContent();
  } catch (error) {
    console.error('Refresh error:', error);
    if (!cached) showError('حدث خطأ في إعادة تحميل المحتوى');
  }
}

/* ========================= Misc ========================= */
function addEventListeners() {
  // مساحة لإضافة مستمعين لاحقًا (لا شيء الآن)
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
  if (name.includes('lecture') || name.includes('محاضرة')) return 'lecture';
  if (name.includes('assignment') || name.includes('واجب') || name.includes('قبل') || name.includes('بعد')) return 'assignment';
  if (name.includes('project') || name.includes('مشروع'))   return 'project';
  if (name.includes('quiz') || name.includes('اختبار'))     return 'quiz';
  return 'lecture';
}
function getFileIcon(mimeType) {
  if (!mimeType) return 'fa-file';
  const mt = String(mimeType).toLowerCase();
  if (mt.includes('pdf'))                                   return 'fa-file-pdf';
  if (mt.includes('word') || mt.includes('doc'))            return 'fa-file-word';
  if (mt.includes('excel') || mt.includes('spreadsheet') ||
      mt.includes('sheet') || mt.includes('xls'))           return 'fa-file-excel';
  if (mt.includes('powerpoint') || mt.includes('presentation') || mt.includes('ppt')) return 'fa-file-powerpoint';
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
if (!window.__student_reloadCurrentSection) {
  window.__student_reloadCurrentSection = reloadCurrentSection;
}

export { showLoading, showError, showContent };
