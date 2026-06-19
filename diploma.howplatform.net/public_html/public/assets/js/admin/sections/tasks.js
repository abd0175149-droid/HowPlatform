/* =========================================================================
 * tasks.js v3.1.0 — إدارة المهام (Admin)
 * إصلاحات مطلوبة:
 *  - مودال النسخ/النقل فوق مودال التعديل (ز-index + overlay مستقل)
 *  - معاينة الملفات: شبكي 2 عمود فقط
 *  - التنبهيات فوق كل شيء
 *  - تحسين تفعيل الفلاتر بالتسلسل + تفويض الأحداث
 *  - fallback للمعاينة عند فشل /download/:id
 * ========================================================================= */
'use strict';

/* ============================== أدوات عامّة ============================== */
function buildUrl(base, path) {
  if (/^https?:\/\//i.test(path)) return path;
  return `${base.replace(/\/+$/, '')}/${String(path).replace(/^\/+/, '')}`;
}
const API_BASE = (window.API_BASE || '/admins/api').replace(/\/+$/, '');
const API = (p) => buildUrl(API_BASE, p);

async function safeFetch(url, options) {
  try {
    const res = await fetch(url, options);
    if (!res.ok) {
      const text = await res.text().catch(() => '');
      console.error(`[TASKS] HTTP ${res.status} :: ${url}`, text);
      throw new Error(`HTTP ${res.status}`);
    }
    return res;
  } catch (err) {
    console.error('[TASKS] Network/Fetch error:', err);
    (window.showError || window.alert)?.('حدث خطأ في الاتصال بالخادم.');
    throw err;
  }
}
const toArray = (v) => (Array.isArray(v) ? v : []);

/* خرائط عرض عامة */
window.specIdToName = {};
window.taskTypeIdToName = {};

/* ============================ كاش الحالة ============================ */
const STATE_KEY = 'tasksPageState:v3.1.0';
function loadState() { try { const raw = localStorage.getItem(STATE_KEY); return raw ? JSON.parse(raw) : null; } catch { return null; } }
function saveState(s) { try { localStorage.setItem(STATE_KEY, JSON.stringify(s)); } catch (e) { console.warn('[TASKS] Failed to save state:', e); } }
function defaultState() {
  return {
    pageSize: 10,
    currentPage: 1,
    filters: { yearId: null, universityId: null, semesterId: null, courseOfferingId: null, weekId: null, specialtyId: null },
    search: { qTitle: '', qSpec: '', dateFrom: '', dateTo: '' },
  };
}

/* ============================ ستايل ديناميكي ============================ */
function injectDynamicStyles() {
  if (document.getElementById('tasksDynamicStyles')) return;
  const css = `
:root{ --pri-500:#219ebc; --pri-700:#166b83; --acc-500:#fd9e02; --border:#e5e7eb; --txt:#0f172a; --bg:#fff; }

/* === أساس المودالات (تأكدنا منه ليتوسّط الشاشة ويعلو كل شيء) === */
.ap-modal-overlay{
  position:fixed; inset:0; display:none; place-items:center;
  background:rgb(15 23 42 / .45); backdrop-filter: blur(2px);
  padding:16px; z-index:10000;
}
.ap-modal-overlay.active{ display:grid; }
.ap-modal{
  position:relative; width:min(980px, 96vw); max-height:90vh; overflow:hidden;
  background:#fff; border-radius:14px; box-shadow:0 20px 60px rgb(0 0 0 / .35);
  display:flex; flex-direction:column;
}
.ap-modal-header{ display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 14px; border-bottom:1px solid var(--border); }
.ap-modal-title{ margin:0; font-size:1.1rem; font-weight:800; }
.ap-modal-body{ padding:12px 14px; overflow:auto; }
.ap-modal-footer{ padding:10px 14px; border-top:1px solid var(--border); display:flex; gap:8px; flex-wrap:wrap; }

/* مودال النسخ/النقل فوق مودال التعديل */
#copyMoveModalOverlay{ z-index:11000 !important; }
#confirmModalOverlay{ z-index:12000 !important; }

/* تنبيهات فوق كل شيء (يشمل SweetAlert/Toastr/IziToast/Notyf) */
.ap-toast,.toast,.toast-container,.notyf,.iziToast,#toast-container,.swal2-container{
  z-index:999999 !important;
}

/* Chips */
.specialty-options{ display:flex; flex-wrap:wrap; gap:10px; }
.spec-card{ flex:0 1 auto; padding:10px 14px; border:2px solid var(--pri-500);
  border-radius:999px; background:#fff; color:var(--txt); cursor:pointer; user-select:none;
  transition:.2s ease-in-out; font-weight:800; display:inline-flex; align-items:center; gap:8px; }
.spec-card:hover{ transform: translateY(-1px); box-shadow:0 2px 8px rgb(0 0 0 / .06); }
.spec-card.selected{ background:linear-gradient(135deg, var(--pri-500), var(--pri-700)); color:#fff; border-color:#17708a; box-shadow:0 6px 18px rgb(33 158 188 / .20); }
.spec-card input{ display:none; }
.spec-card.general{ border-color: var(--acc-500); background:#fff; color:var(--txt); }
.spec-card.general.selected{ background:linear-gradient(135deg, var(--acc-500), #e28900); color:#2b1e00; border-color:#e28900; box-shadow:0 6px 18px rgb(253 158 2 / .20); }

/* أقسام المودال */
.modal-section{ border:1px solid var(--border); border-radius:12px; padding:12px; background:var(--bg); margin-bottom:12px; }
.modal-section__title{ font-weight:800; margin-bottom:10px; display:flex; align-items:center; gap:8px; }
.modal-grid{ display:grid; grid-template-columns: repeat(2, minmax(180px,1fr)); gap:10px; }
.switch{ display:inline-flex; align-items:center; gap:6px; }
.switch input{ transform:translateY(1px); }

/* أزرار داخل فوتر مودال التعديل لفتح مودال النسخ/النقل */
.modal-actions-inline{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; }

/* مصفوفة الوجهات في مودال النسخ/النقل */
.target-head{ display:grid; grid-template-columns: 1fr 1fr 1fr 40px; gap:8px; font-weight:800; margin-bottom:6px; }
.target-row{ display:grid; grid-template-columns: 1fr 1fr 1fr 40px; gap:8px; align-items:center; margin-bottom:8px; }
.target-row .remove-row{ width:36px; height:36px; border:none; border-radius:8px; background:#fee2e2; cursor:pointer; }

/* معاينة الملفات — شبكي بعمودين دائمًا */
#filePreviewContainer, #viewFilesContainer{
  display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px;
}
.file-card{ position:relative; border:1px solid var(--border); border-radius:12px; padding:10px;
  background:#fff; box-shadow:0 2px 8px rgb(0 0 0 / .04); width:100%;
}
.file-card .remove-file{ position:absolute; top:6px; inset-inline-end:6px; width:28px; height:28px; border:none; border-radius:50%;
  background:#f1f5f9; cursor:pointer; font-size:16px; line-height:28px; }
.file-card .thumb{ display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap; }
.file-card .thumb img{ max-width:160px; height:auto; border-radius:8px; }
.file-card .thumb video, .file-card .thumb audio{ max-width:320px; display:block; border-radius:8px; }
.file-card .thumb .file-icon{ font-size:28px; opacity:.8; }
.file-card .file-name{ font-weight:700; word-break:break-word; }
.file-card .file-desc{ width:100%; min-height:56px; }
.premium-label, .public-label{ display:flex; align-items:center; gap:8px; margin-top:6px; font-size:.92rem;
  background:#f7f9fb; border:1px solid var(--border); padding:6px 10px; border-radius:8px; }
.premium-label input, .public-label input{ transform:translateY(1px); }

/* Loader داخل المودال الأساسي - كامل الحجم */
.modal-uploading{ position:absolute; inset:0; background:rgb(255 255 255 / .95); backdrop-filter:blur(3px);
  display:none; place-items:center; z-index:10010; border-radius:14px; }
.modal-uploading.active{ display:grid; }
.modal-uploading__box{ background:#fff; border:1px solid var(--border); border-radius:14px; padding:24px 28px; min-width:320px; max-width:90%; box-shadow:0 12px 32px rgb(0 0 0 / .18); text-align:center; }
.modal-uploading__spinner{ width:48px; height:48px; border-radius:50%; border:4px solid #e5e7eb; border-top-color:var(--pri-500); animation:spin 1s linear infinite; margin:0 auto 16px; }
@keyframes spin{ to{ transform:rotate(360deg);} }
.modal-uploading__progress{ height:10px; background:#f1f5f9; border-radius:999px; overflow:hidden; margin-top:12px; }
.modal-uploading__bar{ height:100%; width:0%; background: linear-gradient(90deg, var(--pri-500), var(--pri-700)); transition:width .15s linear; }
.modal-uploading__text{ font-size:1rem; color:var(--txt); }
.modal-uploading__text b{ font-weight:800; color:var(--pri-600); }

/* Steps Navigation */
.task-modal-steps{ margin-bottom:20px; padding-bottom:16px; border-bottom:2px solid var(--border); }
.step-indicator{ display:flex; justify-content:space-between; align-items:center; position:relative; }
.step-indicator::before{ content:''; position:absolute; top:20px; left:0; right:0; height:2px; background:var(--border); z-index:0; }
.step-item{ display:flex; flex-direction:column; align-items:center; gap:8px; position:relative; z-index:1; flex:1; }
.step-number{ width:40px; height:40px; border-radius:50%; background:#fff; border:2px solid var(--border); color:var(--muted); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1rem; transition:all .3s ease; }
.step-label{ font-size:.85rem; color:var(--muted); font-weight:600; text-align:center; }
.step-item.active .step-number{ background:var(--pri-500); border-color:var(--pri-500); color:#fff; box-shadow:0 4px 12px rgba(33,158,188,.3); }
.step-item.active .step-label{ color:var(--pri-600); font-weight:800; }
.step-item.completed .step-number{ background:var(--accent-500); border-color:var(--accent-500); color:#fff; }
.step-item.completed .step-label{ color:var(--accent-500); }

/* Modal Steps */
.modal-step{ display:none; }
.modal-step.active{ display:block; animation:fadeIn .3s ease; }
@keyframes fadeIn{ from{ opacity:0; transform:translateY(10px); } to{ opacity:1; transform:translateY(0); } }

/* Error States */
.ap-field.error .ap-label{ color:#dc2626; }
.ap-field.error .ap-input,
.ap-field.error .upg-input,
.ap-field.error .ap-input-wrap{ border-color:#dc2626; background:#fef2f2; }
.ap-field.error .ap-input:focus,
.ap-field.error .upg-input:focus{ box-shadow:0 0 0 3px rgba(220,38,38,.18); }
.ap-field.error-message{ color:#dc2626; font-size:.85rem; margin-top:4px; display:flex; align-items:center; gap:4px; }
.ap-field.error-message::before{ content:'⚠'; font-size:1rem; }

/* Toast Notifications - استخدام النظام الموحد من admin_style.css (admin-toast-container) */
`.trim();
  const s = document.createElement('style'); s.id = 'tasksDynamicStyles'; s.textContent = css; document.head.appendChild(s);
}

/* =============================== التصدير العام ============================== */
export function init() {
  injectDynamicStyles();

  /* ------------------------- الحالة ------------------------- */
  let state = loadState() || defaultState();
  const pageSize = state.pageSize || 10;
  let currentPage = state.currentPage || 1;
  let totalPages = 1;

  let selectedFiles = [];   // File[]
  let existingFiles = [];   // {file_id, name, path, description, is_premium, allow_public_view}[]
  let removedFileIds = [];   // number[]
  let originalSnapshot = null; // لقطة مرجعية عند فتح التعديل
  const filters = { ...state.filters };

  /* ------------------------- عناصر DOM ------------------------- */
  const academicYearSelect = document.getElementById('academicYear');
  const universitySelect = document.getElementById('university');
  const semesterSelect = document.getElementById('semester');
  const courseSelect = document.getElementById('course');
  const weekSelect = document.getElementById('week');
  const specialtySelect = document.getElementById('specialty');
  const tasksTableBody = document.getElementById('tasksTableBody');

  const firstPageBtn = document.getElementById('firstPageBtn');
  const prevPageBtn = document.getElementById('prevPageBtn');
  const nextPageBtn = document.getElementById('nextPageBtn');
  const lastPageBtn = document.getElementById('lastPageBtn');
  const paginationInfo = document.getElementById('paginationInfo');

  const addTaskBtn = document.getElementById('addTaskBtn');
  const resetFiltersBtn = document.getElementById('resetFiltersBtn');

  const taskModalOverlay = document.getElementById('taskModalOverlay');
  const modalTitle = document.getElementById('modalTitle');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  // استنساخ الزر للتخلص من أي مستمعات مثبتة خارجيًا (مثل quick_add_task.js)
  const originalSaveBtn = document.getElementById('saveTaskBtn');
  const saveTaskBtn = originalSaveBtn ? originalSaveBtn.cloneNode(true) : null;
  if (originalSaveBtn && saveTaskBtn) originalSaveBtn.replaceWith(saveTaskBtn);
  const prevStepBtn = document.getElementById('prevStepBtn');
  const nextStepBtn = document.getElementById('nextStepBtn');

  // Step management
  let currentStep = 1;
  const totalSteps = 3;

  const taskIdInput = document.getElementById('taskId');
  const modalAcademicYear = document.getElementById('modalAcademicYear');
  const modalSemester = document.getElementById('modalSemester');
  const modalUniversity = document.getElementById('modalUniversity');
  const modalCourse = document.getElementById('modalCourse');
  const modalWeek = document.getElementById('modalWeek');
  const taskTitleInput = document.getElementById('taskTitle');
  const taskTypeSelect = document.getElementById('taskType');
  const taskDescription = document.getElementById('taskDescription');
  const taskPromptInput = document.getElementById('taskPrompt');
  const aiToolVersionSelect = document.getElementById('aiToolVersion');
  const specialtyOptions = document.getElementById('specialtyOptions');
  const publishDateInput = document.getElementById('publishDate');
  const dueDateInput = document.getElementById('dueDate');

  const fileInput = document.getElementById('fileInput');
  const fileUploadBtn = document.getElementById('fileUploadBtn');
  const filePreviewContainer = document.getElementById('filePreviewContainer');

  const searchTitleInput = document.getElementById('searchTitle');
  const searchSpecInput = document.getElementById('searchSpecialty');
  const dateFromInput = document.getElementById('dateFrom');
  const dateToInput = document.getElementById('dateTo');

  const viewModalOverlay = document.getElementById('viewModalOverlay');
  const viewCloseBtn = document.getElementById('viewCloseBtn');
  const viewModalCloseFooter = document.getElementById('viewModalCloseFooter');

  const currentYear = document.getElementById('currentYear');
  if (currentYear) currentYear.textContent = new Date().getFullYear();

  /* ================ Loader داخل مودال المهمة ================ */
  let modalLoader = taskModalOverlay ? taskModalOverlay.querySelector('.modal-uploading') : null;
  if (!modalLoader && taskModalOverlay) {
    const div = document.createElement('div');
    div.className = 'modal-uploading';
    div.innerHTML = `
      <div class="modal-uploading__box">
        <div class="modal-uploading__spinner"></div>
        <div class="modal-uploading__text">جاري <b>حفظ/نسخ/نقل</b> المهمة… <span class="js-batch-note"></span></div>
        <div class="modal-uploading__progress" aria-hidden="true">
          <div class="modal-uploading__bar" style="width:0%"></div>
        </div>
      </div>`;
    const host = taskModalOverlay.querySelector('.ap-modal') || taskModalOverlay;
    host.appendChild(div);
    modalLoader = div;
  }
  const loaderBar = modalLoader ? modalLoader.querySelector('.modal-uploading__bar') : null;
  const loaderNote = modalLoader ? modalLoader.querySelector('.js-batch-note') : null;
  const showLoader = (txt) => { if (modalLoader) { modalLoader.classList.add('active'); } if (loaderBar) loaderBar.style.width = '0%'; if (loaderNote) loaderNote.textContent = txt ? `— ${txt}` : ''; };
  const hideLoader = () => { if (modalLoader) { modalLoader.classList.remove('active'); } if (loaderNote) loaderNote.textContent = ''; };
  const setLoaderProgress = (p) => { if (loaderBar) loaderBar.style.width = `${Math.max(0, Math.min(100, p))}%`; };

  // Toast notification system
  function showToast(type, message, duration = 5000) {
    const overlay = document.getElementById('toastOverlay') || (() => {
      const div = document.createElement('div');
      div.id = 'toastOverlay';
      div.className = 'ap-toast-overlay';
      document.body.appendChild(div);
      return div;
    })();

    const icons = {
      success: '✓',
      error: '✕',
      warning: '⚠',
      info: 'ℹ'
    };

    const toast = document.createElement('div');
    toast.className = `ap-toast ${type}`;
    toast.innerHTML = `
      <span class="ap-toast-icon">${icons[type] || icons.info}</span>
      <span class="ap-toast-message">${message}</span>
      <button class="ap-toast-close" aria-label="إغلاق">&times;</button>
    `;

    overlay.appendChild(toast);

    const closeBtn = toast.querySelector('.ap-toast-close');
    const close = () => {
      toast.style.animation = 'slideUp .3s ease';
      setTimeout(() => toast.remove(), 300);
    };

    closeBtn?.addEventListener('click', close);
    setTimeout(close, duration);
  }

  /* ============================ Endpoints Helpers ============================ */
  async function loadAcademicYears() {
    const years = await (await safeFetch(API('task/getAcademicYears'))).json();
    if (academicYearSelect) academicYearSelect.innerHTML = '<option value="">اختر العام الدراسي</option>' + years.map(y => `<option value="${y.id}">${y.label}</option>`).join('');
  }
  async function loadTaskTypes() {
    const types = await (await safeFetch(API('task/getTaskTypes'))).json();
    if (taskTypeSelect) {
      taskTypeSelect.innerHTML = '<option value="">-- اختر نوع المهمة --</option>' + types.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
      types.forEach(t => window.taskTypeIdToName[t.id] = t.name);
    }
  }
  async function loadAiJobTypes() {
    try {
      const types = await (await safeFetch(API('task/getAiJobTypes'))).json();
      if (aiToolVersionSelect) {
        let opts = '<option value="">-- الافتراضي لنوع المهمة --</option>';
        opts += '<option value="none" class="text-danger">-- بدون أداة ذكاء اصطناعي --</option>';
        types.forEach(t => {
          opts += `<option value="${t.type_key}">${t.title} (${t.type_key})</option>`;
        });
        aiToolVersionSelect.innerHTML = opts;
      }
    } catch (e) { console.warn('ميزة جلب أدوات الذكاء الاصطناعي قيد التطوير أو فاشلة', e); }
  }
  async function apiGetSpecialties(univId) {
    let url = 'task/getSpecialties'; if (univId) url += `/${encodeURIComponent(univId)}`;
    return await (await safeFetch(API(url))).json();
  }
  async function apiGetUniversities(yearId) {
    return await (await safeFetch(API(`task/getUniversities/${encodeURIComponent(yearId)}`))).json();
  }
  async function apiGetSemesters(yearId) {
    return await (await safeFetch(API(`task/getSemesters/${encodeURIComponent(yearId)}`))).json();
  }
  async function apiGetCourses(yearId, univId, semId) {
    const params = new URLSearchParams({ yearId, univId, semId }); // yearId غير مستخدم بالسيرفر لكنه آمن
    return await (await safeFetch(API(`task/getCourses?${params}`))).json();
  }
  async function apiGetWeeks(offeringId) {
    return await (await safeFetch(API(`task/getWeeks/${encodeURIComponent(offeringId)}`))).json();
  }

  /* ============================ مودالات عامة ============================ */
  function showOverlay(el) { if (!el) return; el.classList.add('active'); el.removeAttribute('hidden'); el.setAttribute('aria-hidden', 'false'); document.documentElement.style.overflow = 'hidden'; }
  function hideOverlay(el) { if (!el) return; el.classList.remove('active'); el.setAttribute('aria-hidden', 'true'); el.setAttribute('hidden', ''); document.documentElement.style.overflow = ''; }

  function resetFileArrays() { existingFiles = []; selectedFiles = []; removedFileIds = []; }
  function clearSelectedFiles() {
    filePreviewContainer?.querySelectorAll('.file-card.new')?.forEach(card => {
      const url = card.getAttribute('data-url'); if (url) { try { URL.revokeObjectURL(url); } catch { } }
    });
    selectedFiles = [];
    if (fileInput) fileInput.value = '';
  }
  function resetModalFields() {
    if (taskIdInput) taskIdInput.value = '';
    if (taskTitleInput) taskTitleInput.value = '';
    if (taskTypeSelect) taskTypeSelect.value = '';
    if (taskDescription) taskDescription.value = '';
    if (taskPromptInput) taskPromptInput.value = '';
    if (aiToolVersionSelect) aiToolVersionSelect.value = '';
    if (publishDateInput) publishDateInput.value = '';
    if (dueDateInput) dueDateInput.value = '';
    if (filePreviewContainer) filePreviewContainer.innerHTML = '';
    if (specialtyOptions) {
      const inputs = specialtyOptions.querySelectorAll('input');
      inputs.forEach(chk => { chk.checked = false; if (chk.parentElement) chk.parentElement.classList.remove('selected'); });
    }
    originalSnapshot = null;
    resetFileArrays();
    currentStep = 1;
    updateStepDisplay();
    clearFieldErrors();

    // إعادة تعيين قسم دراسة الحالة
    toggleCaseStudySection(false);
    const taskPromptField = taskPromptInput?.closest('.ap-field');
    if (taskPromptField) taskPromptField.style.display = 'block';
    const criteriaContainer = document.getElementById('criteriaContainer');
    const casesContainer = document.getElementById('casesContainer');
    if (criteriaContainer) {
      criteriaContainer.innerHTML = '';
      addCriterionRow();
    }
    if (casesContainer) {
      casesContainer.innerHTML = '';
      addCaseItem();
    }
  }

  function clearFieldErrors() {
    taskModalOverlay?.querySelectorAll('.ap-field.error').forEach(field => {
      field.classList.remove('error');
      const errMsg = field.querySelector('.error-message');
      if (errMsg) errMsg.remove();
    });
  }

  function showFieldError(fieldEl, message) {
    if (!fieldEl) return;
    const field = fieldEl.closest('.ap-field');
    if (!field) return;
    field.classList.add('error');
    let errMsg = field.querySelector('.error-message');
    if (!errMsg) {
      errMsg = document.createElement('div');
      errMsg.className = 'error-message';
      field.appendChild(errMsg);
    }
    errMsg.textContent = message;
  }

  function updateStepDisplay() {
    // Update step indicators
    document.querySelectorAll('.step-item').forEach((item, idx) => {
      const stepNum = idx + 1;
      item.classList.remove('active', 'completed');
      if (stepNum === currentStep) item.classList.add('active');
      else if (stepNum < currentStep) item.classList.add('completed');
    });

    // Update step content
    document.querySelectorAll('.modal-step').forEach((step, idx) => {
      const stepNum = idx + 1;
      step.classList.toggle('active', stepNum === currentStep);
    });

    // Update navigation buttons
    if (prevStepBtn) prevStepBtn.style.display = currentStep > 1 ? 'inline-flex' : 'none';
    if (nextStepBtn) nextStepBtn.style.display = currentStep < totalSteps ? 'inline-flex' : 'none';
    if (saveTaskBtn) saveTaskBtn.style.display = currentStep === totalSteps ? 'inline-flex' : 'none';
  }

  function validateStep(step) {
    clearFieldErrors();
    let isValid = true;

    if (step === 1) {
      if (!taskTypeSelect?.value || taskTypeSelect.value === '') {
        showFieldError(taskTypeSelect, 'يجب اختيار نوع المهمة');
        isValid = false;
      }
    } else if (step === 2) {
      if (!taskTitleInput?.value?.trim()) {
        showFieldError(taskTitleInput, 'يجب كتابة عنوان المهمة');
        isValid = false;
      }
      if (!taskDescription?.value?.trim()) {
        showFieldError(taskDescription, 'يجب كتابة وصف للمهمة');
        isValid = false;
      }
      const selectedSpecs = pickSelectedSpecs();
      const isGeneral = selectedSpecs.includes('all');
      const specCount = selectedSpecs.filter(v => v !== 'all').length;
      if (!isGeneral && specCount === 0) {
        if (specialtyOptions) showFieldError(specialtyOptions, 'اختر التخصصات المستهدفة أو علِّم خيار "عام لكل التخصصات"');
        isValid = false;
      }
      if (!publishDateInput?.value) {
        showFieldError(publishDateInput, 'يجب اختيار تاريخ عرض المهمة');
        isValid = false;
      }
      if (!dueDateInput?.value) {
        showFieldError(dueDateInput, 'يجب اختيار تاريخ الاستحقاق');
        isValid = false;
      }
    }

    return isValid;
  }

  function openModal(isEdit = false) {
    if (!isEdit) {
      resetModalFields();
      // التحقق من نوع المهمة المحدد حالياً
      const taskTypeId = parseInt(taskTypeSelect?.value || '0');
      if (taskTypeId === 42) { // CASE_STUDY_TASK_TYPE_ID
        toggleCaseStudySection(true);
        const taskPromptField = taskPromptInput?.closest('.ap-field');
        if (taskPromptField) taskPromptField.style.display = 'none';
      }
    }
    if (modalTitle) modalTitle.textContent = isEdit ? 'تعديل مهمة' : 'إضافة مهمة جديدة';
    showOverlay(taskModalOverlay);
    currentStep = 1;
    updateStepDisplay();
  }
  function closeTaskModal() {
    hideOverlay(taskModalOverlay);
    clearSelectedFiles();
    clearFieldErrors();
    // تنظيف حدث الحفظ الخاص بالتعديل لمنع التداخل
    if (saveTaskBtn) saveTaskBtn.onclick = null;
  }
  function openViewModal() { showOverlay(viewModalOverlay); }
  function closeViewModal() { hideOverlay(viewModalOverlay); }

  if (closeModalBtn) closeModalBtn.addEventListener('click', (e) => { e.preventDefault(); closeTaskModal(); });
  if (cancelModalBtn) cancelModalBtn.addEventListener('click', (e) => { e.preventDefault(); closeTaskModal(); });
  if (taskModalOverlay) taskModalOverlay.addEventListener('click', (e) => { if (e.target === taskModalOverlay) closeTaskModal(); });

  // Step navigation
  if (nextStepBtn) nextStepBtn.addEventListener('click', (e) => {
    e.preventDefault();
    if (validateStep(currentStep)) {
      currentStep++;
      updateStepDisplay();
    }
  });

  if (prevStepBtn) prevStepBtn.addEventListener('click', (e) => {
    e.preventDefault();
    if (currentStep > 1) {
      currentStep--;
      updateStepDisplay();
    }
  });
  if (viewCloseBtn) viewCloseBtn.addEventListener('click', (e) => { e.preventDefault(); closeViewModal(); });
  if (viewModalCloseFooter) viewModalCloseFooter.addEventListener('click', (e) => { e.preventDefault(); closeViewModal(); });
  if (viewModalOverlay) viewModalOverlay.addEventListener('click', (e) => { if (e.target === viewModalOverlay) closeViewModal(); });

  /* ============================ مودال التخصّصات ============================ */
  async function loadModalSpecialties(univId) {
    const specs = await apiGetSpecialties(univId);
    if (!specialtyOptions) return;
    specialtyOptions.innerHTML = `
      <div class="modal-section">
        <div class="modal-section__title"><i class="fas fa-user-graduate"></i> التخصصات المستهدفة</div>
        <div class="specialty-options">
          ${specs.map(s => `<label class="spec-card"><input type="checkbox" value="${s.id}" hidden /> ${s.name}</label>`).join('')}
          <label class="spec-card general"><input type="checkbox" value="all" hidden /> عام لكل التخصصات</label>
        </div>
      </div>`;
    const root = specialtyOptions.querySelector('.specialty-options');
    const chkAll = root.querySelector('input[value="all"]');
    root.querySelectorAll('input:not([value="all"])').forEach(chk => {
      const card = chk.parentElement;
      card.addEventListener('click', () => {
        chk.checked = !chk.checked; card.classList.toggle('selected', chk.checked);
        if (chk.checked && chkAll) { chkAll.checked = false; chkAll.parentElement.classList.remove('selected'); }
      });
    });
    chkAll?.parentElement?.addEventListener('click', () => {
      chkAll.checked = !chkAll.checked; chkAll.parentElement.classList.toggle('selected', chkAll.checked);
      if (chkAll.checked) { root.querySelectorAll('input:not([value="all"])').forEach(chk => { chk.checked = false; chk.parentElement.classList.remove('selected'); }); }
    });
  }

  /* ====================== Helpers للفلاتر (تفعيل/تعطيل) ====================== */
  function setSelectOptions(selectEl, optionsHtml, placeholder) {
    if (!selectEl) return;
    selectEl.innerHTML = `<option value="">${placeholder}</option>` + (optionsHtml || '');
  }
  function setEnabled(selectEl, enabled) {
    if (!selectEl) return;
    selectEl.disabled = !enabled;
  }
  function clearAndDisable(selectEl, placeholder) {
    if (!selectEl) return;
    setSelectOptions(selectEl, '', placeholder);
    selectEl.disabled = true;
  }

  /* =============================== وظائف الجدول =============================== */
  function linkify(text) { const urlRegex = /(https?:\/\/[^\s<>"]+)/g; const span = document.createElement('span'); span.textContent = text || ''; return span.innerHTML.replace(urlRegex, url => `<a href="${url}" target="_blank" rel="noopener">${url}</a>`); }
  function getIconClass(fileName = '') {
    const ext = (fileName.split('.').pop() || '').toLowerCase();
    switch (ext) {
      case 'pdf': return 'fas fa-file-pdf'; case 'doc': case 'docx': return 'fas fa-file-word'; case 'ppt': case 'pptx': return 'fas fa-file-powerpoint';
      case 'xls': case 'xlsx': return 'fas fa-file-excel'; case 'jpg': case 'jpeg': case 'png': case 'gif': return 'fas fa-file-image';
      case 'zip': case 'rar': return 'fas fa-file-archive'; case 'txt': return 'fas fa-file-alt';
      case 'mp3': case 'wav': case 'm4a': case 'aac': case 'ogg': case 'opus': case 'flac': return 'fas fa-file-audio';
      case 'mp4': case 'm4v': case 'mov': case 'mkv': case 'webm': case 'avi': return 'fas fa-file-video'; default: return 'fas fa-file';
    }
  }
  const isAudioOrVideoByName = (n = '') => { const ext = (String(n).split('.').pop() || '').toLowerCase(); return ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'opus', 'flac', 'mp4', 'm4v', 'mov', 'mkv', 'webm', 'avi'].includes(ext); };
  function isAudioOrVideoFile(file) { const t = (file?.type || '').toLowerCase(); return t.startsWith('audio/') || t.startsWith('video/') || isAudioOrVideoByName(file?.name || ''); }

  function persist() {
    state = {
      pageSize, currentPage, filters: { ...filters },
      search: { qTitle: (searchTitleInput?.value || '').trim(), qSpec: (searchSpecInput?.value || '').trim(), dateFrom: (dateFromInput?.value || ''), dateTo: (dateToInput?.value || '') }
    };
    saveState(state);
  }

  // تهيئة
  loadTaskTypes();
  loadAiJobTypes();
  loadAcademicYears();
  loadSpecialtiesForView(''); // تحميل لـ fallback
  async function loadSpecialtiesForView(univId) {
    try {
      const specs = await apiGetSpecialties(univId); window.specIdToName = {}; specs.forEach(s => window.specIdToName[s.id] = s.name);
    } catch (err) { console.error('loadSpecialtiesForView', err); window.specIdToName = {}; }
  }

  async function loadTasks(page = 1) {
    if (!filters.weekId || !filters.yearId || !filters.universityId || !filters.semesterId) {
      if (tasksTableBody) tasksTableBody.innerHTML = `<tr><td colspan="9" style="text-align:center">يرجى اختيار جميع الفلاتر</td></tr>`;
      if (paginationInfo) paginationInfo.textContent = 'صفحة 1 من 1'; currentPage = 1; persist(); return;
    }
    currentPage = page; persist();
    const params = new URLSearchParams({
      weekId: filters.weekId, specId: filters.specialtyId || '', page: currentPage, limit: pageSize,
      qTitle: (searchTitleInput?.value || '').trim(), qSpec: (searchSpecInput?.value || '').trim(),
      dateFrom: (dateFromInput?.value || ''), dateTo: (dateToInput?.value || ''),
    });
    const { tasks: rawTasks = [], total = 0 } = await (await safeFetch(API(`task/getTasks?${params}`))).json();
    const tasks = toArray(rawTasks).map(r => { const o = {}; for (const k in r) o[k?.trim?.() ?? k] = r[k]; return o; });
    totalPages = Math.max(1, Math.ceil(total / pageSize));
    if (tasksTableBody) tasksTableBody.innerHTML = tasks.length
      ? tasks.map((t, i) => `
        <tr>
          <td>${(currentPage - 1) * pageSize + i + 1}</td>
          <td>${t.university ?? ''}</td>
          <td>${t.course ?? ''}</td>
          <td>${t.week_number !== undefined ? 'أسبوع ' + t.week_number : ''}</td>
          <td>${t.title ?? ''}</td>
          <td>${t.task_type ?? ''}</td>
          <td>${toArray(t.specialties).join(', ')}</td>
          <td>${t.attachments_count ?? 0}</td>
          <td class="action-buttons-task">
            <button class="action-btn-task view-btn-task" data-id="${t.id}" title="عرض"><i class="fas fa-eye"></i></button>
            <button class="action-btn-task edit-btn-task" data-id="${t.id}" title="تعديل/نسخ/نقل"><i class="fas fa-external-link-alt"></i></button>
            <button class="action-btn-task delete-btn-task" data-id="${t.id}" title="حذف"><i class="fas fa-trash-alt"></i></button>
          </td>
        </tr>`).join('')
      : '<tr><td colspan="9" style="text-align:center">لا يوجد مهام</td></tr>';
    attachTaskActionHandlers(); refreshPaginationControls();
  }
  function refreshPaginationControls() {
    if (!paginationInfo) return;
    paginationInfo.textContent = `صفحة ${currentPage} من ${totalPages}`;
    if (firstPageBtn) firstPageBtn.disabled = currentPage <= 1;
    if (prevPageBtn) prevPageBtn.disabled = currentPage <= 1;
    if (nextPageBtn) nextPageBtn.disabled = currentPage >= totalPages;
    if (lastPageBtn) lastPageBtn.disabled = currentPage >= totalPages;
  }
  firstPageBtn?.addEventListener('click', () => { if (currentPage > 1) loadTasks(1); });
  prevPageBtn?.addEventListener('click', () => { if (currentPage > 1) loadTasks(currentPage - 1); });
  nextPageBtn?.addEventListener('click', () => { if (currentPage < totalPages) loadTasks(currentPage + 1); });
  lastPageBtn?.addEventListener('click', () => { if (currentPage < totalPages) loadTasks(totalPages); });

  function attachTaskActionHandlers() {
    tasksTableBody?.querySelectorAll('.view-btn-task').forEach(btn => btn.addEventListener('click', () => viewTask(btn.dataset.id)));
    tasksTableBody?.querySelectorAll('.edit-btn-task').forEach(btn => btn.addEventListener('click', () => editTask(btn.dataset.id)));
    tasksTableBody?.querySelectorAll('.delete-btn-task').forEach(btn => btn.addEventListener('click', () => deleteTask(btn.dataset.id)));
  }

  /* ========================== عرض مهمة (مع معاينات) ========================== */
  function filePrimaryUrl(f) { return API(`task/download/${encodeURIComponent(f.file_id)}`); }
  function fileFallbackUrl(f) {
    const p = f.file_path || f.path || '';
    return (typeof p === 'string' && p.startsWith('/uploads/')) ? p : '';
  }
  function bindPreviewFallbacks(container) {
    if (!container) return;
    container.querySelectorAll('[data-fallback-src]').forEach(el => {
      el.addEventListener('error', function onErr() {
        el.removeEventListener('error', onErr);
        const alt = el.getAttribute('data-fallback-src');
        if (alt) el.setAttribute('src', alt);
      }, { once: true });
    });
  }

  async function viewTask(id) {
    try {
      const data = await (await safeFetch(API(`task/getTaskForView/${encodeURIComponent(id)}`))).json();
      const t = data.task || {};
      const specialty_ids = data.specialty_ids || [];
      const files = data.files || [];
      await loadSpecialtiesForView(t.university_id);

      const now = new Date(); const due = t.due_date ? new Date(t.due_date) : null; const active = !due || now <= due;
      const badge = document.getElementById('viewStatusBadge'); const st = document.getElementById('viewStatusText');
      if (st) st.textContent = active ? 'نشطة' : 'منتهية';
      if (badge) { badge.classList.toggle('status-active', active); badge.classList.toggle('status-inactive', !active); }

      const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
      setTxt('viewTaskTitle', t.title || '-');
      setTxt('viewUniversity', t.university || '-');
      setTxt('viewCourse', t.course || '-');
      setTxt('viewWeek', t.week_number != null ? `أسبوع ${t.week_number}` : '-');
      setTxt('viewTaskType', t.task_type || '-');
      const pub = t.visible_from_date ? new Date(t.visible_from_date) : null;
      setTxt('viewPublishDateValue', pub ? pub.toISOString().split('T')[0] : '-');
      setTxt('viewDueDateValue', due ? due.toISOString().split('T')[0] : '-');

      const descEl = document.getElementById('viewDescription');
      if (descEl) descEl.innerHTML = t.description ? linkify(t.description.trim()).replace(/\n/g, '<br>') : 'لا يوجد وصف.';

      const specBox = document.getElementById('viewSpecialtiesContainer');
      if (specBox) {
        specBox.innerHTML = String(t.is_for_all_specialties) === '1'
          ? `<span class="specialty-card general"><i class="fas fa-globe"></i> عام لكل التخصصات</span>`
          : (specialty_ids.length ? specialty_ids.map(id => `<span class="specialty-card"><i class="fas fa-graduation-cap"></i> ${window.specIdToName[id] || '#' + id}</span>`).join('') : '<span>لا توجد تخصصات محددة.</span>');
      }

      const filesBox = document.getElementById('viewFilesContainer');
      if (filesBox) {
        filesBox.innerHTML = files.length ? files.map(f => {
          const primary = filePrimaryUrl(f);
          const fallback = fileFallbackUrl(f);
          const ext = (f.file_name || '').split('.').pop()?.toLowerCase() || '';
          if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext)) {
            return `<div class="file-card"><div class="thumb"><img src="${primary}" ${fallback ? `data-fallback-src="${fallback}"` : ''} alt="${f.file_name}"></div><div class="file-name">${f.file_name}</div><div class="file-description">${f.description || ''}</div><div class="file-actions"><a href="${primary}" target="_blank" rel="noopener" class="file-action-btn preview-btn"><i class="fas fa-external-link-alt"></i> فتح</a></div></div>`;
          } else if (['mp4', 'm4v', 'mov', 'mkv', 'webm', 'avi'].includes(ext)) {
            return `<div class="file-card"><div class="thumb"><video src="${primary}" ${fallback ? `data-fallback-src="${fallback}"` : ''} controls></video></div><div class="file-name">${f.file_name}</div><div class="file-description">${f.description || ''}</div></div>`;
          } else if (['mp3', 'wav', 'm4a', 'aac', 'ogg', 'opus', 'flac'].includes(ext)) {
            return `<div class="file-card"><div class="thumb"><audio src="${primary}" ${fallback ? `data-fallback-src="${fallback}"` : ''} controls></audio></div><div class="file-name">${f.file_name}</div><div class="file-description">${f.description || ''}</div></div>`;
          }
          return `<div class="file-card"><div class="thumb"><i class="${getIconClass(f.file_name)} file-icon"></i><div><div class="file-name">${f.file_name}</div></div></div><div class="file-description">${f.description || ''}</div><div class="file-actions"><a href="${primary}" target="_blank" rel="noopener" class="file-action-btn preview-btn"><i class="fas fa-external-link-alt"></i> فتح</a></div></div>`;
        }).join('') : '<p>لا توجد ملفات مرفقة.</p>';

        // اربط fallback عند خطأ التحميل
        bindPreviewFallbacks(filesBox);
      }

      openViewModal();
    } catch (err) { console.error('viewTask error:', err); (window.showError || window.alert)?.('فشل في تحميل بيانات المهمة'); }
  }

  /* ========================= مودال النسخ/النقل العلوي ========================= */
  let copyMoveModalOverlay = null;
  function createCopyMoveModalIfMissing() {
    if (copyMoveModalOverlay) return;
    copyMoveModalOverlay = document.createElement('div');
    copyMoveModalOverlay.id = 'copyMoveModalOverlay';
    copyMoveModalOverlay.className = 'ap-modal-overlay';
    copyMoveModalOverlay.setAttribute('hidden', '');
    copyMoveModalOverlay.setAttribute('aria-hidden', 'true');
    copyMoveModalOverlay.innerHTML = `
      <div class="ap-modal">
        <div class="ap-modal-header">
          <h3 class="ap-modal-title"><i class="fas fa-exchange-alt"></i> نسخ/نقل المهمة</h3>
          <button type="button" class="ap-modal-close" id="cmCloseBtn" aria-label="إغلاق">&times;</button>
        </div>
        <div class="ap-modal-body">
          <div class="modal-section">
            <div class="modal-section__title"><i class="fas fa-route"></i> الوجهة</div>
            <div class="modal-grid" style="margin-bottom:8px">
              <div>
                <label>العام</label>
                <select id="cmYear"><option value="">اختر العام</option></select>
              </div>
              <div>
                <label>الفصل</label>
                <select id="cmSemester" disabled><option value="">اختر الفصل</option></select>
              </div>
            </div>

            <div class="modal-section" style="margin-top:8px">
              <div class="modal-section__title"><i class="fas fa-layer-group"></i> الأهداف (يمكن إضافة عدة وجهات)</div>
              <div style="margin-bottom:6px; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <button id="cmAddTargetRowBtn" class="btn btn-outline" disabled><i class="fas fa-plus"></i> إضافة وجهة</button>
                <small>كل صف = (جامعة ← مساق ← أسبوع)</small>
              </div>
              <div class="target-head">
                <div>الجامعة</div><div>المساق</div><div>الأسبوع</div><div></div>
              </div>
              <div id="cmTargetRows"></div>
            </div>

            <label class="switch" style="margin-top:8px">
              <input type="checkbox" id="cmLinkExistingSwitch" checked>
              <span>اربط المرفقات الحالية بالمهمات الجديدة</span>
            </label>
          </div>

          <div class="modal-section" id="cmConfirmBox" style="display:none">
            <div class="modal-section__title"><i class="fas fa-info-circle"></i> تأكيد العملية</div>
            <div id="cmConfirmMessage"></div>
          </div>
        </div>
        <div class="ap-modal-footer" style="display:flex; gap:8px; flex-wrap:wrap">
          <button id="cmCopyBtn" class="btn btn-secondary"><i class="fas fa-copy"></i> نسخ</button>
          <button id="cmMoveBtn" class="btn btn-warning"><i class="fas fa-arrows-alt"></i> نقل</button>
          <button id="cmCancelBtn" class="btn btn-light">إلغاء</button>
          <div style="flex:1"></div>
          <button id="cmConfirmDoBtn" class="btn btn-primary" style="display:none"><i class="fas fa-check"></i> تأكيد</button>
          <button id="cmConfirmBackBtn" class="btn btn-light" style="display:none">رجوع</button>
        </div>
      </div>
    `;
    document.body.appendChild(copyMoveModalOverlay);

    // إغلاقات
    const cmCloseBtn = copyMoveModalOverlay.querySelector('#cmCloseBtn');
    const cmCancelBtn = copyMoveModalOverlay.querySelector('#cmCancelBtn');
    copyMoveModalOverlay.addEventListener('click', (e) => { if (e.target === copyMoveModalOverlay) hideCopyMoveModal(); });
    cmCloseBtn?.addEventListener('click', hideCopyMoveModal);
    cmCancelBtn?.addEventListener('click', hideCopyMoveModal);

    bindCopyMoveHandlers();
  }

  function showCopyMoveModal() { createCopyMoveModalIfMissing(); showOverlay(copyMoveModalOverlay); }
  function hideCopyMoveModal() {
    if (!copyMoveModalOverlay) return;
    hideOverlay(copyMoveModalOverlay);
    const box = copyMoveModalOverlay.querySelector('#cmConfirmBox');
    const btnD = copyMoveModalOverlay.querySelector('#cmConfirmDoBtn');
    const btnB = copyMoveModalOverlay.querySelector('#cmConfirmBackBtn');
    if (box) box.style.display = 'none';
    if (btnD) btnD.style.display = 'none';
    if (btnB) btnB.style.display = 'none';
  }

  function bindCopyMoveHandlers() {
    const y = copyMoveModalOverlay.querySelector('#cmYear');
    const s = copyMoveModalOverlay.querySelector('#cmSemester');
    const rowsBox = copyMoveModalOverlay.querySelector('#cmTargetRows');
    const addBtn = copyMoveModalOverlay.querySelector('#cmAddTargetRowBtn');
    const copyBtn = copyMoveModalOverlay.querySelector('#cmCopyBtn');
    const moveBtn = copyMoveModalOverlay.querySelector('#cmMoveBtn');
    const confirmBox = copyMoveModalOverlay.querySelector('#cmConfirmBox');
    const confirmMsg = copyMoveModalOverlay.querySelector('#cmConfirmMessage');
    const confirmDoBtn = copyMoveModalOverlay.querySelector('#cmConfirmDoBtn');
    const confirmBackBtn = copyMoveModalOverlay.querySelector('#cmConfirmBackBtn');

    // تغيّر العام -> تعبئة الفصول وإفراغ الصفوف
    if (y && !y.dataset.bound) {
      y.dataset.bound = '1';
      y.addEventListener('change', async () => {
        if (!y.value) {
          clearAndDisable(s, 'اختر الفصل');
          if (addBtn) addBtn.disabled = true;
          rowsBox.innerHTML = '';
          return;
        }
        const semesters = await apiGetSemesters(y.value || 0);
        if (s) {
          setSelectOptions(s, semesters.map(r => `<option value="${r.id}">${r.name}</option>`).join(''), 'اختر الفصل');
          setEnabled(s, true);
        }
        if (addBtn) addBtn.disabled = false; // يسمح بإضافة صف بعد اختيار الفصل
        rowsBox.innerHTML = '';
      });
    }
    // تغيّر الفصل -> إفراغ الصفوف
    if (s && !s.dataset.bound) {
      s.dataset.bound = '1';
      s.addEventListener('change', () => { rowsBox.innerHTML = ''; });
    }

    // إضافة صف وجهة
    if (addBtn && !addBtn.dataset.bound) {
      addBtn.dataset.bound = '1';
      addBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        if (!y.value || !s.value) { (window.showWarning || window.alert)?.('اختر العام والفصل أولاً'); return; }
        const unis = await apiGetUniversities(y.value);
        const row = document.createElement('div');
        row.className = 'target-row';
        row.innerHTML = `
          <select class="t-univ"><option value="">اختر الجامعة</option>${unis.map(r => `<option value="${r.id}">${r.name}</option>`).join('')}</select>
          <select class="t-course" disabled><option value="">اختر المساق</option></select>
          <select class="t-week" disabled><option value="">اختر الأسبوع</option></select>
          <button type="button" class="remove-row" title="إزالة">&times;</button>
        `;
        rowsBox.appendChild(row);

        const uSel = row.querySelector('.t-univ');
        const cSel = row.querySelector('.t-course');
        const wSel = row.querySelector('.t-week');
        const rm = row.querySelector('.remove-row');

        uSel.addEventListener('change', async () => {
          setSelectOptions(cSel, '', 'اختر المساق'); cSel.disabled = true;
          setSelectOptions(wSel, '', 'اختر الأسبوع'); wSel.disabled = true;
          if (!uSel.value) return;
          const courses = await apiGetCourses(y.value, uSel.value, s.value);
          setSelectOptions(cSel, courses.map(r => `<option value="${r.id}">${r.name}</option>`).join(''), 'اختر المساق');
          cSel.disabled = false;
        });
        cSel.addEventListener('change', async () => {
          setSelectOptions(wSel, '', 'اختر الأسبوع'); wSel.disabled = true;
          if (!cSel.value) return;
          const weeks = await apiGetWeeks(cSel.value);
          setSelectOptions(wSel, weeks.map(r => `<option value="${r.id}">أسبوع ${r.week_number} - ${r.title}</option>`).join(''), 'اختر الأسبوع');
          wSel.disabled = false;
        });
        rm.addEventListener('click', () => row.remove());
      });
    }

    async function promptConfirm(mode) {
      const weeks = getCopyMoveWeekIds();
      if (mode === 'move' && weeks.length !== 1) { showWarning('النقل يقبل وجهة واحدة فقط.'); return; }
      if (weeks.length === 0) { showWarning('أضف على الأقل وجهة واحدة مكتملة.'); return; }

      // بناء ملخص الوجهات
      const rows = Array.from(copyMoveModalOverlay.querySelectorAll('#cmTargetRows .target-row'));
      const items = rows.map(r => {
        const uTxt = r.querySelector('.t-univ')?.selectedOptions?.[0]?.text || '';
        const cTxt = r.querySelector('.t-course')?.selectedOptions?.[0]?.text || '';
        const wTxt = r.querySelector('.t-week')?.selectedOptions?.[0]?.text || '';
        return (uTxt && cTxt && wTxt) ? `• ${uTxt} → ${cTxt} → ${wTxt}` : '';
      }).filter(Boolean);

      if (confirmMsg) {
        confirmMsg.innerHTML = `
          <p>ستقوم بـ <b>${mode === 'clone' ? 'نسخ' : 'نقل'}</b> المهمة إلى:</p>
          <div style="margin:.5rem 0 0.75rem; line-height:1.6">${items.join('<br>')}</div>
          <small>هل تريد المتابعة؟</small>
        `;
      }
      if (confirmBox) confirmBox.style.display = 'block';
      if (confirmDoBtn) { confirmDoBtn.style.display = 'inline-flex'; confirmDoBtn.dataset.mode = mode; }
      if (confirmBackBtn) confirmBackBtn.style.display = 'inline-flex';
    }

    copyBtn?.addEventListener('click', () => promptConfirm('clone'));
    moveBtn?.addEventListener('click', () => promptConfirm('move'));

    confirmBackBtn?.addEventListener('click', () => {
      if (confirmBox) confirmBox.style.display = 'none';
      if (confirmDoBtn) confirmDoBtn.style.display = 'none';
      if (confirmBackBtn) confirmBackBtn.style.display = 'none';
    });

    confirmDoBtn?.addEventListener('click', async () => {
      const mode = confirmDoBtn.dataset.mode || 'clone';
      const weeks = getCopyMoveWeekIds();
      const linkSwitch = copyMoveModalOverlay.querySelector('#cmLinkExistingSwitch');
      const linkIds = linkSwitch?.checked ? existingFiles.map(f => f.file_id) : [];

      let successCount = 0;
      showLoader(`(${0}/${weeks.length})`);
      for (let i = 0; i < weeks.length; i++) {
        const fd = buildCommonFD({ overrideWeekId: weeks[i], linkIds });
        fd.append('taskId', '');
        fd.append('sourceTaskId', String(taskIdInput?.value || ''));
        fd.append('actionMode', mode); // clone | move

        const ok = await sendOne(fd, { batchIndex: i, batchTotal: weeks.length });
        if (ok) successCount++;
        if (loaderNote) loaderNote.textContent = `— (${i + 1}/${weeks.length})`;
      }
      hideLoader();

      if (mode === 'move' && successCount === 1 && taskIdInput?.value) {
        try { await safeFetch(API(`task/deleteTask/${encodeURIComponent(taskIdInput.value)}`), { method: 'DELETE' }); }
        catch (e) { console.error('[TASKS] move: delete original failed', e); }
      }

      hideCopyMoveModal();
      if (successCount > 0) {
        closeTaskModal(); await loadTasks(currentPage);
        showSuccess(mode === 'clone' ? `تم نسخ المهمة إلى ${successCount} وجهة` : 'تم نقل المهمة');
      } else {
        showError('تعذّر تنفيذ العملية.');
      }
    });
  }

  async function primeCopyMoveModalWithFilters() {
    createCopyMoveModalIfMissing();
    const y = copyMoveModalOverlay.querySelector('#cmYear');
    const s = copyMoveModalOverlay.querySelector('#cmSemester');
    const rowsBox = copyMoveModalOverlay.querySelector('#cmTargetRows');
    const addBtn = copyMoveModalOverlay.querySelector('#cmAddTargetRowBtn');

    const years = await (await safeFetch(API('task/getAcademicYears'))).json();
    if (y) {
      y.innerHTML = '<option value="">اختر العام</option>' + years.map(r => `<option value="${r.id}">${r.label}</option>`).join('');
      if (filters.yearId) y.value = String(filters.yearId);
      s.disabled = !y.value;
    }
    if (y && y.value) {
      const semesters = await apiGetSemesters(y.value);
      if (s) {
        s.innerHTML = '<option value="">اختر الفصل</option>' + semesters.map(r => `<option value="${r.id}">${r.name}</option>`).join('');
        if (filters.semesterId) s.value = String(filters.semesterId);
        s.disabled = false;
      }
      if (addBtn) addBtn.disabled = false;
    } else {
      if (s) {
        s.innerHTML = '<option value="">اختر الفصل</option>';
        s.disabled = true;
      }
      if (addBtn) addBtn.disabled = true;
    }
    if (rowsBox) rowsBox.innerHTML = '';
  }

  function getCopyMoveWeekIds() {
    const rowsBox = copyMoveModalOverlay.querySelector('#cmTargetRows');
    const weekIds = Array.from(rowsBox.querySelectorAll('.target-row .t-week')).map(s => s.value).filter(Boolean);
    return weekIds;
  }

  /* ====================== زرّي فتح مودال النسخ/النقل ====================== */
  function ensureInlineOpenButtons() {
    if (!taskModalOverlay) return;
    const footer = taskModalOverlay.querySelector('.ap-modal-footer') || taskModalOverlay;
    if (!footer) return;

    let actions = taskModalOverlay.querySelector('#inlineOpenCopyMove');
    if (!actions) {
      actions = document.createElement('div');
      actions.id = 'inlineOpenCopyMove';
      actions.className = 'modal-actions-inline';
      actions.innerHTML = `
        <button id="openCopyModalBtn" class="btn btn-secondary"><i class="fas fa-copy"></i> فتح مودال النسخ</button>
        <button id="openMoveModalBtn" class="btn btn-warning"><i class="fas fa-arrows-alt"></i> فتح مودال النقل</button>
      `;
      footer.insertAdjacentElement('afterbegin', actions);
    }
  }
  // Event Delegation لضمان عمل الأزرار حتى لو أعيد بناء DOM
  document.addEventListener('click', async (e) => {
    const copyBtn = e.target.closest('#openCopyModalBtn');
    const moveBtn = e.target.closest('#openMoveModalBtn');
    if (copyBtn || moveBtn) {
      e.preventDefault();
      await primeCopyMoveModalWithFilters();
      showCopyMoveModal();
    }
  });

  /* ======================= مودال تأكيد عام (الحفظ) ======================= */
  let confirmModalOverlay = null;
  function createConfirmModalIfMissing() {
    if (confirmModalOverlay) return;
    confirmModalOverlay = document.createElement('div');
    confirmModalOverlay.id = 'confirmModalOverlay';
    confirmModalOverlay.className = 'ap-modal-overlay';
    confirmModalOverlay.setAttribute('hidden', '');
    confirmModalOverlay.setAttribute('aria-hidden', 'true');
    confirmModalOverlay.innerHTML = `
      <div class="ap-modal">
        <div class="ap-modal-header">
          <h3 class="ap-modal-title"><i class="fas fa-check-double"></i> تأكيد العملية</h3>
          <button type="button" class="ap-modal-close" id="cfCloseBtn" aria-label="إغلاق">&times;</button>
        </div>
        <div class="ap-modal-body">
          <div class="modal-section">
            <div class="modal-section__title"><i class="fas fa-info-circle"></i> ملخص التغييرات</div>
            <div id="cfMessage"></div>
          </div>
        </div>
        <div class="ap-modal-footer" style="display:flex; gap:8px; flex-wrap:wrap">
          <button id="cfConfirmBtn" class="btn btn-primary"><i class="fas fa-save"></i> تأكيد</button>
          <button id="cfCancelBtn" class="btn btn-light">إلغاء</button>
        </div>
      </div>
    `;
    document.body.appendChild(confirmModalOverlay);
    const closeBtn = confirmModalOverlay.querySelector('#cfCloseBtn');
    const cancelBtn = confirmModalOverlay.querySelector('#cfCancelBtn');

    // Default close behaviors (can be overridden or augmented by showConfirmModal's onclick methods)
    // لاحظ أن showConfirmModal يستبدل onclick للأزرار، لكن addEventListener هنا سيعمل أيضاً.
    // الأفضل إزالة addEventListener هنا والاعتماد على showConfirmModal لربط الأحداث، 
    // أو تركها كـ fallback بسيط. سنتركها كـ fallback ونعدل overlay handler.

    closeBtn?.addEventListener('click', () => hideOverlay(confirmModalOverlay));
    cancelBtn?.addEventListener('click', () => hideOverlay(confirmModalOverlay)); // fallback

    confirmModalOverlay.addEventListener('click', (e) => {
      if (e.target === confirmModalOverlay) {
        hideOverlay(confirmModalOverlay);
        if (confirmModalOverlay._currentOnCancel && typeof confirmModalOverlay._currentOnCancel === 'function') {
          confirmModalOverlay._currentOnCancel();
        }
      }
    });
  }
  function showConfirmModal(html, onConfirm, onCancel) {
    createConfirmModalIfMissing();
    const box = confirmModalOverlay.querySelector('#cfMessage');
    const btn = confirmModalOverlay.querySelector('#cfConfirmBtn');
    const closeBtn = confirmModalOverlay.querySelector('#cfCloseBtn');
    const cancelBtn = confirmModalOverlay.querySelector('#cfCancelBtn');

    if (box) box.innerHTML = html || '';

    // تنظيف الأحداث السابقة
    const cleanUp = () => {
      if (btn) btn.onclick = null;
      if (closeBtn) closeBtn.onclick = null;
      if (cancelBtn) cancelBtn.onclick = null;
      // لا يزال لدينا click on overlay في createConfirmModalIfMissing لكنه عام
    };
    cleanUp();

    // Confirm Action
    if (btn) {
      btn.onclick = async () => {
        // تعطيل الزر فوراً لمنع التكرار
        btn.disabled = true;
        hideOverlay(confirmModalOverlay);
        try {
          await onConfirm?.();
        } finally {
          btn.disabled = false; // إعادة التفعيل عند الانتهاء (للمرات القادمة)
          cleanUp();
        }
      };
    }

    // Cancel Actions
    const handleCancel = () => {
      hideOverlay(confirmModalOverlay);
      onCancel?.(); // استدعاء دالة الإلغاء لفك القفل
      cleanUp();
    };

    if (closeBtn) closeBtn.onclick = handleCancel;
    if (cancelBtn) cancelBtn.onclick = handleCancel;

    // إعادة تعريف النقر على الخلفية ليدعم onCancel
    // نحتاج لإزالة المستمع القديم أو التحقق من التداخل، لكن الأبسط الآن هو الاعتماد على أن الـ overlay click 
    // المعرف في createConfirmModalIfMissing يستدعي hideOverlay فقط. 
    // سنقوم بتعديل createConfirmModalIfMissing لدعم استدعاء دالة مركزية، أو نكتفي بإضافة مستمع وحيد مؤقت.
    // الحل الأضمن: تخزين onCancel الحالي في عنصر الـ DOM
    confirmModalOverlay.dataset.hasCancel = '1';
    confirmModalOverlay._currentOnCancel = onCancel;

    showOverlay(confirmModalOverlay);
  }

  /* =========================== تعديل/نسخ/نقل =========================== */
  async function editTask(id) {
    try {
      const t = await (await safeFetch(API(`task/getTask/${encodeURIComponent(id)}`))).json();

      // حقول المودال
      if (taskIdInput) taskIdInput.value = t.id || '';
      if (modalAcademicYear) modalAcademicYear.value = t.academic_year || '';
      if (modalSemester) modalSemester.value = t.semester || '';
      if (modalUniversity) modalUniversity.value = t.university || '';
      if (modalCourse) modalCourse.value = t.course || '';
      if (modalWeek) modalWeek.value = (t.week_number !== undefined && t.week_number !== null) ? `أسبوع ${t.week_number}` : '';
      if (taskTitleInput) taskTitleInput.value = t.title ?? '';
      if (taskTypeSelect) taskTypeSelect.value = t.task_type_id ?? '';
      if (taskDescription) taskDescription.value = t.description ?? '';

      // التحقق من نوع المهمة وإظهار قسم دراسة الحالة إذا لزم الأمر
      const taskTypeId = parseInt(t.task_type_id || '0');
      const isCaseStudy = (taskTypeId === 42); // CASE_STUDY_TASK_TYPE_ID

      if (isCaseStudy) {
        toggleCaseStudySection(true);
        // إخفاء taskPrompt العادي
        const taskPromptField = taskPromptInput?.closest('.ap-field');
        if (taskPromptField) taskPromptField.style.display = 'none';

        // تحميل البيانات من JSON إذا كانت موجودة
        if (t.task_prompt) {
          try {
            const caseStudyData = JSON.parse(t.task_prompt);
            if (caseStudyData && caseStudyData.type === 'case_study') {
              loadCaseStudyData(caseStudyData);
            } else {
              if (taskPromptInput) taskPromptInput.value = t.task_prompt;
            }
          } catch (e) {
            // ليس JSON - استخدمه كنص عادي
            if (taskPromptInput) taskPromptInput.value = t.task_prompt || '';
          }
        }
      } else {
        toggleCaseStudySection(false);
        // إظهار taskPrompt العادي
        const taskPromptField = taskPromptInput?.closest('.ap-field');
        if (taskPromptField) taskPromptField.style.display = 'block';
        if (taskPromptInput) taskPromptInput.value = t.task_prompt ?? '';
      }
      if (aiToolVersionSelect) aiToolVersionSelect.value = t.ai_tool_version ?? '';
      if (publishDateInput) publishDateInput.value = t.visible_from_date?.slice(0, 10) || '';
      if (dueDateInput) dueDateInput.value = t.dueDate?.slice(0, 10) || t.due_date?.slice(0, 10) || '';

      await loadModalSpecialties(filters.universityId);
      const ids = toArray(t.specialty_ids).map(String);
      specialtyOptions?.querySelectorAll('input').forEach(chk => {
        const selected = (chk.value === 'all' && String(t.is_for_all_specialties) === '1') || (chk.value !== 'all' && ids.includes(chk.value));
        chk.checked = selected; chk.parentElement?.classList.toggle('selected', selected);
      });

      existingFiles = toArray(t.files).map(f => ({ file_id: f.file_id, name: f.file_name, path: f.file_path, description: f.description ?? '', is_premium: Number(f.is_premium) || 0, allow_public_view: Number(f.allow_public_view) || 0 }));
      selectedFiles = []; removedFileIds = [];
      renderFilePreviews();

      openModal(true);
      ensureInlineOpenButtons();
      currentStep = 1;
      updateStepDisplay();

      // لقطة مرجعية
      originalSnapshot = captureSnapshot();

      // زر الحفظ (تأكيد عند وجود تغييرات)
      if (saveTaskBtn) {
        saveTaskBtn.onclick = async (e) => {
          e.preventDefault();
          // منع double-click
          if (isSaving) return;

          // نتحقق أولاً من وجود تغييرات
          const diff = computeChanges();
          if (!diff.has) {
            closeTaskModal(); // لا تغييرات
            return;
          }

          // نقوم بتفعيل القفل هنا ولا نفكه إلا عند الإلغاء أو انتهاء الحفظ الفعلي
          isSaving = true;
          if (saveTaskBtn) saveTaskBtn.disabled = true;

          const html = `<div style="line-height:1.7">${diff.details.join('<br>')}</div><p style="margin-top:.5rem">هل تريد حفظ هذه التغييرات؟</p>`;

          showConfirmModal(html,
            async () => {
              // onConfirm
              try {
                await saveEditPayloadSingle({ mode: 'update', taskId: t.id });
              } finally {
                // فك القفل فقط بعد انتهاء محاولة الحفظ
                isSaving = false;
                if (saveTaskBtn) saveTaskBtn.disabled = false;
              }
            },
            () => {
              // onCancel
              // فك القفل عند إلغاء المودال
              isSaving = false;
              if (saveTaskBtn) saveTaskBtn.disabled = false;
            }
          );
        };
      }

    } catch (err) { console.error('editTask error:', err); (window.showError || window.alert)?.('فشل في تحميل بيانات المهمة'); }
  }

  async function deleteTask(id) {
    await (window.showConfirm || (opts => { if (confirm(opts?.text || 'متأكد؟')) opts?.onConfirm?.(); }))({
      title: 'تأكيد الحذف', text: 'هل أنت متأكد من حذف المهمة؟', confirmText: 'حذف', cancelText: 'إلغاء',
      onConfirm: async () => {
        try {
          const res = await safeFetch(API(`task/deleteTask/${encodeURIComponent(id)}`), { method: 'DELETE' });
          const data = await res.json();
          if (data?.status === 'deleted' || res.ok) {
            await loadTasks(currentPage);
            showSuccess('تم حذف المهمة بنجاح');
          } else {
            showError('فشل حذف المهمة. يرجى المحاولة مرة أخرى.');
          }
        } catch (err) {
          console.error('[TASKS] deleteTask error:', err);
          showError('حدث خطأ أثناء حذف المهمة');
        }
      }
    });
  }

  function pickSelectedSpecs() {
    const root = specialtyOptions?.querySelector('.specialty-options') || specialtyOptions;
    return Array.from(root?.querySelectorAll('input:checked') || []).map(chk => chk.value);
  }
  function alertRequired(message, el = null) { (window.showWarning || window.alert)?.(message); if (el) setTimeout(() => el.focus(), 150); return false; }

  /* ========== بناء الـ FormData من الحقول المشتركة ========== */
  function buildCommonFD({ overrideWeekId = '', linkIds = [] } = {}) {
    const title = taskTitleInput?.value?.trim() || '';
    const desc = taskDescription?.value?.trim() || '';
    const selectedSpecs = pickSelectedSpecs();

    const fd = new FormData();
    fd.append('taskTitle', title);
    fd.append('taskType', taskTypeSelect?.value || '');
    fd.append('taskDescription', desc);

    // حفظ بيانات دراسة الحالة كـ JSON في task_prompt
    const caseStudySection = document.getElementById('caseStudySection');
    if (caseStudySection && caseStudySection.style.display !== 'none') {
      const caseStudyData = buildCaseStudyData();
      fd.append('taskPrompt', JSON.stringify(caseStudyData));
    } else {
      const taskPrompt = taskPromptInput?.value?.trim() || '';
      fd.append('taskPrompt', taskPrompt);
    }
    fd.append('aiToolVersion', aiToolVersionSelect?.value || '');
    fd.append('publishDate', publishDateInput?.value || '');
    fd.append('dueDate', dueDateInput?.value || '');
    fd.append('specialties', JSON.stringify(selectedSpecs));
    fd.append('removedFiles', JSON.stringify(removedFileIds));
    fd.append('weekId', overrideWeekId || (filters.weekId || ''));

    // ملفات جديدة
    const newCards = filePreviewContainer?.querySelectorAll('.file-card.new') || [];
    selectedFiles.forEach((file, i) => {
      const card = newCards[i];
      const fDesc = card?.querySelector('.file-desc')?.value?.trim() ?? '';
      const isPremium = card?.querySelector('.is-premium-checkbox')?.checked ? 1 : 0;
      const isPublic = card?.querySelector('.is-public-checkbox')?.checked ? 1 : 0;
      fd.append('files[]', file);
      fd.append('filesDesc[]', fDesc);
      fd.append('filesPremium[]', isPremium);
      fd.append('filesPublic[]', isPublic);
    });

    // ملفات موجودة (لتحديث الوصف/الفلاغات في وضع update)
    const existingCards = filePreviewContainer?.querySelectorAll('.file-card.existing') || [];
    const existingPayload = existingFiles.map((f, i) => {
      const card = existingCards[i];
      return {
        file_id: f.file_id,
        description: card?.querySelector('.file-desc')?.value ?? '',
        is_premium: card?.querySelector('.is-premium-checkbox')?.checked ? 1 : 0,
        allow_public_view: card?.querySelector('.is-public-checkbox')?.checked ? 1 : 0,
      };
    });
    fd.append('existingFiles', JSON.stringify(existingPayload));

    // ربط ملفات موجودة (اختياري عند النسخ/النقل)
    if (linkIds?.length) fd.append('linkExistingFiles', JSON.stringify(linkIds));

    // إشعار الخادم إن كنا نتوقع ملفات جديدة
    fd.append('expectFiles', selectedFiles.length > 0 ? '1' : '0');

    return fd;
  }

  /* ========== مقارنة تغييرات للحفظ ========== */
  function captureSnapshot() {
    const getSpecs = () => {
      const root = specialtyOptions?.querySelector('.specialty-options') || specialtyOptions;
      return Array.from(root?.querySelectorAll('input:checked') || []).map(chk => chk.value).sort();
    };
    const existingCards = filePreviewContainer?.querySelectorAll('.file-card.existing') || [];
    const existingMeta = existingFiles.map((f, i) => {
      const card = existingCards[i];
      return {
        file_id: f.file_id,
        description: (card?.querySelector('.file-desc')?.value ?? '').trim(),
        is_premium: card?.querySelector('.is-premium-checkbox')?.checked ? 1 : 0,
        allow_public_view: card?.querySelector('.is-public-checkbox')?.checked ? 1 : 0,
        name: f.name
      };
    });

    return {
      title: (taskTitleInput?.value || '').trim(),
      typeId: taskTypeSelect?.value || '',
      description: (taskDescription?.value || '').trim(),
      taskPrompt: (taskPromptInput?.value || '').trim(),
      aiToolVersion: (aiToolVersionSelect?.value || '').trim(),
      publishDate: publishDateInput?.value || '',
      dueDate: dueDateInput?.value || '',
      specs: getSpecs(),
      existingMeta,
      existingIds: existingFiles.map(f => f.file_id).slice().sort(),
    };
  }

  function computeChanges() {
    if (!originalSnapshot) return { has: false, details: [] };

    const cur = captureSnapshot();
    const det = [];
    function add(label, from, to) {
      det.push(`• ${label}: <code>${from || '-'}</code> → <code>${to || '-'}</code>`);
    }

    if (cur.title !== originalSnapshot.title) add('العنوان', originalSnapshot.title, cur.title);
    if (cur.typeId !== originalSnapshot.typeId) add('نوع المهمة', window.taskTypeIdToName[originalSnapshot.typeId] || originalSnapshot.typeId, window.taskTypeIdToName[cur.typeId] || cur.typeId);
    if (cur.description !== originalSnapshot.description) add('الوصف', (originalSnapshot.description || '').slice(0, 40) + '…', (cur.description || '').slice(0, 40) + '…');
    if (cur.taskPrompt !== originalSnapshot.taskPrompt) add('موجه المهمة', (originalSnapshot.taskPrompt || '').slice(0, 40) + '…', (cur.taskPrompt || '').slice(0, 40) + '…');
    if (cur.aiToolVersion !== originalSnapshot.aiToolVersion) add('إصدار أداة الذكاء الاصطناعي', originalSnapshot.aiToolVersion, cur.aiToolVersion);
    if (cur.publishDate !== originalSnapshot.publishDate) add('تاريخ الإتاحة', originalSnapshot.publishDate, cur.publishDate);
    if (cur.dueDate !== originalSnapshot.dueDate) add('تاريخ الاستحقاق', originalSnapshot.dueDate, cur.dueDate);

    const oSpecs = (originalSnapshot.specs || []).join(', ');
    const nSpecs = (cur.specs || []).join(', ');
    if (oSpecs !== nSpecs) add('التخصصات', oSpecs, nSpecs);

    const addedCount = selectedFiles.length;
    const removedCount = removedFileIds.length;

    const mapOrig = new Map(originalSnapshot.existingMeta.map(m => [m.file_id, m]));
    const changedMeta = cur.existingMeta.filter(m => {
      const o = mapOrig.get(m.file_id);
      return o && (o.description !== m.description || o.is_premium !== m.is_premium || o.allow_public_view !== m.allow_public_view);
    });
    if (addedCount > 0) det.push(`• ملفات مضافة: ${addedCount} ملف/ملفات`);
    if (removedCount > 0) det.push(`• ملفات محذوفة: ${removedCount} ملف/ملفات`);
    if (changedMeta.length > 0) det.push(`• ملفات تم تعديل خصائصها: ${changedMeta.length}`);

    return { has: det.length > 0, details: det };
  }

  /* ========== تحديث المهمة الأصلية فقط ========== */
  async function saveEditPayloadSingle({ mode = 'update', taskId = '' } = {}) {
    clearFieldErrors();
    let hasError = false;

    const title = taskTitleInput?.value?.trim(); if (!title) { showFieldError(taskTitleInput, 'يجب كتابة عنوان المهمة'); hasError = true; }
    const desc = taskDescription?.value?.trim(); if (!desc) { showFieldError(taskDescription, 'يجب كتابة وصف للمهمة'); hasError = true; }
    const taskTypeVal = taskTypeSelect?.value; if (!taskTypeVal || taskTypeVal === '') { showFieldError(taskTypeSelect, 'يجب اختيار نوع المهمة'); hasError = true; }
    const publishVal = publishDateInput?.value; if (!publishVal) { showFieldError(publishDateInput, 'يجب اختيار تاريخ عرض المهمة'); hasError = true; }

    const selectedSpecs = pickSelectedSpecs();
    const isGeneral = selectedSpecs.includes('all');
    const specCount = selectedSpecs.filter(v => v !== 'all').length;
    if (!isGeneral && specCount === 0) { if (specialtyOptions) showFieldError(specialtyOptions, 'اختر التخصصات المستهدفة أو علِّم خيار "عام لكل التخصصات"'); hasError = true; }

    if (hasError) {
      // Go to first step with error
      if (!taskTypeVal || taskTypeVal === '') currentStep = 1;
      else if (!title || !desc || !publishVal || (!isGeneral && specCount === 0)) currentStep = 2;
      updateStepDisplay();
      showError('يرجى إكمال جميع الحقول الإلزامية');
      return false;
    }

    const fd = buildCommonFD();
    fd.append('taskId', taskId || '');
    fd.append('actionMode', mode); // update

    showLoader();
    const ok = await sendOne(fd);
    hideLoader();
    if (ok) { closeTaskModal(); await loadTasks(currentPage); showSuccess('تم حفظ التعديلات'); }
    else { showError('حدث خطأ أثناء الحفظ'); }
  }

  /* ========== مرسل XHR واحد (مع progress) ========== */
  function sendOne(fd, { batchIndex = 0, batchTotal = 1 } = {}) {
    return new Promise((resolve) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', API('task/createOrUpdateTask'));
      xhr.responseType = 'json';
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      try {
        const filesCount = fd.getAll('files[]').length;
        xhr.setRequestHeader('X-Debug-Expect-Files', filesCount > 0 ? '1' : '0');
      } catch (_) { }

      xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
          const base = (batchIndex / Math.max(1, batchTotal)) * 100;
          setLoaderProgress(base + Math.round((e.loaded / e.total) * (100 / Math.max(1, batchTotal))));
        }
      };
      xhr.onload = () => {
        const ok = xhr.status >= 200 && xhr.status < 300 && xhr.response?.status === 'success';
        if (!ok) {
          console.error('[TASKS] save error:', xhr.status, xhr.response || xhr.responseText);
        }
        resolve(!!ok);
      };
      xhr.onerror = () => { console.error('[TASKS] XHR network error'); resolve(false); };
      try { xhr.send(fd); } catch (e) { console.error('[TASKS] XHR send failed', e); resolve(false); }
    });
  }

  /* ======================= معاينة/إزالة الملفات ======================= */
  function createObjectPreview(file) {
    const ext = (file.name || '').split('.').pop()?.toLowerCase() || ''; const url = URL.createObjectURL(file);
    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext)) return { url, html: `<img src="${url}" alt="${file.name}">` };
    if (['mp4', 'm4v', 'mov', 'mkv', 'webm', 'avi'].includes(ext)) return { url, html: `<video src="${url}" controls></video>` };
    if (['mp3', 'wav', 'm4a', 'aac', 'ogg', 'opus', 'flac'].includes(ext)) return { url, html: `<audio src="${url}" controls></audio>` };
    return { url, html: `<i class="${getIconClass(file.name)} file-icon"></i>` };
  }

  function renderFilePreviews() {
    if (!filePreviewContainer) return;
    filePreviewContainer.innerHTML = '';
    // موجودة
    existingFiles.forEach((f, idx) => {
      const ext = (f.name || '').split('.').pop()?.toLowerCase() || ''; const canPublic = isAudioOrVideoByName(f.name);
      let preview = ''; const primary = filePrimaryUrl(f); const fallback = fileFallbackUrl(f);
      if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext)) preview = `<img src="${primary}" ${fallback ? `data-fallback-src="${fallback}"` : ''} alt="${f.name}">`;
      else if (['mp4', 'm4v', 'mov', 'mkv', 'webm', 'avi'].includes(ext)) preview = `<video src="${primary}" ${fallback ? `data-fallback-src="${fallback}"` : ''} controls></video>`;
      else if (['mp3', 'wav', 'm4a', 'aac', 'ogg', 'opus', 'flac'].includes(ext)) preview = `<audio src="${primary}" ${fallback ? `data-fallback-src="${fallback}"` : ''} controls></audio>`;
      else preview = `<i class="${getIconClass(f.name)} file-icon"></i>`;
      filePreviewContainer.insertAdjacentHTML('beforeend', `
        <div class="file-card existing">
          <button type="button" class="remove-file existing" data-idx="${idx}" title="إزالة">&times;</button>
          <div class="thumb">${preview}<div><div class="file-name">${f.name}</div></div></div>
          <textarea class="file-desc" placeholder="وصف الملف">${f.description || ''}</textarea>
          <label class="premium-label"><input type="checkbox" class="is-premium-checkbox" ${f.is_premium === 1 ? 'checked' : ''}> متاح فقط للمشتركين Premium</label>
          <label class="public-label" title="${canPublic ? '' : 'هذا الخيار متاح لملفات الصوت/الفيديو فقط'}">
            <input type="checkbox" class="is-public-checkbox" ${f.allow_public_view === 1 ? 'checked' : ''} ${canPublic ? '' : 'disabled'}><span>عام للجميع</span>
          </label>
        </div>`);
    });
    // جديدة
    selectedFiles.forEach((f, idx) => {
      const canPublic = isAudioOrVideoFile(f); const prev = createObjectPreview(f);
      filePreviewContainer.insertAdjacentHTML('beforeend', `
        <div class="file-card new" data-url="${prev.url}">
          <button type="button" class="remove-file new" data-idx="${idx}" title="إزالة">&times;</button>
          <div class="thumb">${prev.html}<div><div class="file-name">${f.name}</div></div></div>
          <textarea class="file-desc" placeholder="وصف الملف"></textarea>
          <label class="premium-label"><input type="checkbox" class="is-premium-checkbox"> متاح فقط للمشتركين Premium</label>
          <label class="public-label" title="${canPublic ? '' : 'هذا الخيار متاح لملفات الصوت/الفيديو فقط'}">
            <input type="checkbox" class="is-public-checkbox" ${canPublic ? '' : 'disabled'}><span>عام للجميع</span>
          </label>
        </div>`);
    });
    // ربط fallback عند الأخطاء
    bindPreviewFallbacks(filePreviewContainer);

    // حذف
    filePreviewContainer.querySelectorAll('.remove-file').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx, 10);
        if (btn.classList.contains('existing')) { removedFileIds.push(existingFiles[idx].file_id); existingFiles.splice(idx, 1); }
        else { const card = btn.closest('.file-card.new'); const url = card?.getAttribute('data-url'); if (url) { try { URL.revokeObjectURL(url); } catch { } } selectedFiles.splice(idx, 1); }
        renderFilePreviews();
      });
    });
  }

  // زر الرفع — منع فتح مزدوج
  let pickerOpening = false;
  if (fileUploadBtn) {
    fileUploadBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (pickerOpening) return;
      pickerOpening = true;
      if (fileInput) fileInput.click();
      setTimeout(() => { pickerOpening = false; }, 300);
    }, { passive: false });
  }
  if (fileInput && !fileInput.dataset.bound) {
    fileInput.dataset.bound = '1';
    fileInput.addEventListener('change', () => {
      const list = Array.from(fileInput.files || []);
      if (list.length) { selectedFiles = selectedFiles.concat(list); renderFilePreviews(); }
      fileInput.value = '';
    });
  }

  // Drop-zone
  const dropZone = document.querySelector('.file-upload-section');
  if (dropZone) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => dropZone.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); }));
    ['dragenter', 'dragover'].forEach(evt => dropZone.addEventListener(evt, () => dropZone.classList.add('dragover')));
    ['dragleave', 'drop'].forEach(evt => dropZone.addEventListener(evt, () => dropZone.classList.remove('dragover')));
    dropZone.addEventListener('drop', e => { const dt = e.dataTransfer; if (!dt || !dt.files.length) return; selectedFiles = selectedFiles.concat(Array.from(dt.files)); renderFilePreviews(); });
  }

  /* ============================= حفظ مهمة جديدة ============================= */
  let isSaving = false; // منع double-click
  if (saveTaskBtn) saveTaskBtn.addEventListener('click', async (e) => {
    // في حالة مودال الإضافة فقط (edit يعيد ربط onclick أعلاه)
    if (saveTaskBtn.onclick) return;
    e.preventDefault();

    // منع double-click
    if (isSaving) return;

    // Validate all steps
    clearFieldErrors();
    let hasError = false;

    const title = taskTitleInput?.value?.trim(); if (!title) { showFieldError(taskTitleInput, 'يجب كتابة عنوان المهمة'); hasError = true; }
    const desc = taskDescription?.value?.trim(); if (!desc) { showFieldError(taskDescription, 'يجب كتابة وصف للمهمة'); hasError = true; }
    const taskTypeVal = taskTypeSelect?.value; if (!taskTypeVal || taskTypeVal === '') { showFieldError(taskTypeSelect, 'يجب اختيار نوع المهمة'); hasError = true; }
    const selectedSpecs = pickSelectedSpecs();
    const isGeneral = selectedSpecs.includes('all'); const specCount = selectedSpecs.filter(v => v !== 'all').length;
    if (!isGeneral && specCount === 0) { if (specialtyOptions) showFieldError(specialtyOptions, 'اختر التخصصات المستهدفة أو علِّم خيار "عام لكل التخصصات"'); hasError = true; }
    const publishVal = publishDateInput?.value; if (!publishVal) { showFieldError(publishDateInput, 'يجب اختيار تاريخ عرض المهمة'); hasError = true; }

    if (hasError) {
      // Go to first step with error
      if (!taskTypeVal || taskTypeVal === '') currentStep = 1;
      else if (!title || !desc || !publishVal || (!isGeneral && specCount === 0)) currentStep = 2;
      updateStepDisplay();
      showError('يرجى إكمال جميع الحقول الإلزامية');
      return;
    }

    isSaving = true;
    if (saveTaskBtn) saveTaskBtn.disabled = true;

    try {
      const fd = buildCommonFD(); // weekId من الفلاتر
      fd.append('taskId', '');
      fd.append('actionMode', 'update');

      showLoader();
      const ok = await sendOne(fd);
      hideLoader();
      if (ok) { closeTaskModal(); await loadTasks(currentPage); showSuccess('تم حفظ المهمة بنجاح'); }
      else { showError('حدث خطأ أثناء الحفظ'); }
    } finally {
      isSaving = false;
      if (saveTaskBtn) saveTaskBtn.disabled = false;
    }
  });

  /* ============================ تواريخ تلقائية ============================ */
  publishDateInput?.addEventListener('change', () => {
    if (!publishDateInput.value) return; const dt = new Date(publishDateInput.value); dt.setDate(dt.getDate() + 7);
    if (dueDateInput) { dueDateInput.value = dt.toISOString().slice(0, 10); dueDateInput.removeAttribute('readonly'); }
  });

  /* ============================= تغيّر الفلاتر ============================= */
  // ابدأ بتعطيل جميع الفلاتر التابعة
  if (universitySelect) universitySelect.disabled = !academicYearSelect?.value;
  if (semesterSelect) semesterSelect.disabled = !academicYearSelect?.value;
  if (courseSelect) courseSelect.disabled = true;
  if (weekSelect) weekSelect.disabled = true;

  academicYearSelect?.addEventListener('change', async () => {
    filters.yearId = academicYearSelect.value || null; persist();

    clearAndDisable(universitySelect, 'اختر الجامعة');
    clearAndDisable(semesterSelect, 'اختر الفصل');
    clearAndDisable(courseSelect, 'اختر المساق');
    clearAndDisable(weekSelect, 'اختر الأسبوع');

    if (filters.yearId) {
      const [unis, sems] = await Promise.all([apiGetUniversities(filters.yearId), apiGetSemesters(filters.yearId)]);
      if (universitySelect) {
        setSelectOptions(universitySelect, unis.map(u => `<option value="${u.id}">${u.name}</option>`).join(''), 'اختر الجامعة');
        setEnabled(universitySelect, true);
      }
      if (semesterSelect) {
        setSelectOptions(semesterSelect, sems.map(s => `<option value="${s.id}">${s.name}</option>`).join(''), 'اختر الفصل');
        setEnabled(semesterSelect, true);
      }
    }
    if (specialtySelect) specialtySelect.selectedIndex = 0;
  });

  universitySelect?.addEventListener('change', async () => {
    filters.universityId = universitySelect.value || null; persist();

    clearAndDisable(courseSelect, 'اختر المساق');
    clearAndDisable(weekSelect, 'اختر الأسبوع');

    if (filters.yearId && filters.universityId && filters.semesterId) {
      const courses = await apiGetCourses(filters.yearId, filters.universityId, filters.semesterId);
      if (courseSelect) {
        setSelectOptions(courseSelect, courses.map(c => `<option value="${c.id}">${c.name}</option>`).join(''), 'اختر المساق');
        setEnabled(courseSelect, true);
      }
    }
    // تخصصات
    const specs = await apiGetSpecialties(filters.universityId);
    if (specialtySelect) {
      specialtySelect.innerHTML = '<option value="">جميع التخصصات</option>' + specs.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    }
  });

  semesterSelect?.addEventListener('change', async () => {
    filters.semesterId = semesterSelect.value || null; persist();

    clearAndDisable(courseSelect, 'اختر المساق');
    clearAndDisable(weekSelect, 'اختر الأسبوع');

    if (filters.yearId && filters.universityId && filters.semesterId) {
      const courses = await apiGetCourses(filters.yearId, filters.universityId, filters.semesterId);
      if (courseSelect) {
        setSelectOptions(courseSelect, courses.map(c => `<option value="${c.id}">${c.name}</option>`).join(''), 'اختر المساق');
        setEnabled(courseSelect, true);
      }
    }
  });

  courseSelect?.addEventListener('change', async () => {
    filters.courseOfferingId = courseSelect.value || null; persist();

    clearAndDisable(weekSelect, 'اختر الأسبوع');

    if (filters.courseOfferingId) {
      const weeks = await apiGetWeeks(filters.courseOfferingId);
      if (weekSelect) {
        setSelectOptions(weekSelect, weeks.map(w => `<option value="${w.id}">أسبوع ${w.week_number} - ${w.title}</option>`).join(''), 'اختر الأسبوع');
        setEnabled(weekSelect, true);
      }
    }
  });

  weekSelect?.addEventListener('change', () => { filters.weekId = weekSelect.value || null; loadTasks(1); });

  specialtySelect?.addEventListener('change', () => { filters.specialtyId = specialtySelect.value || null; loadTasks(1); });

  // بحث (debounce)
  const filterListeners = [
    { el: searchTitleInput, ev: 'input' }, { el: searchSpecInput, ev: 'input' }, { el: dateFromInput, ev: 'change' }, { el: dateToInput, ev: 'change' },
  ];
  let debounceTimer; const doLoad = () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => loadTasks(1), 600); };
  filterListeners.forEach(({ el, ev }) => { if (!el) return; el.addEventListener(ev, () => { persist(); ev === 'input' ? doLoad() : loadTasks(1); }); });

  /* =========================== أزرار عامة =========================== */
  addTaskBtn?.addEventListener('click', async (e) => {
    e.preventDefault();
    // تأكد من تنظيف أي حدث onclick سابق (مخلفات التعديل)
    if (saveTaskBtn) saveTaskBtn.onclick = null;

    if (modalAcademicYear) modalAcademicYear.value = academicYearSelect?.selectedOptions?.[0]?.text || '';
    if (modalSemester) modalSemester.value = semesterSelect?.selectedOptions?.[0]?.text || '';
    if (modalUniversity) modalUniversity.value = universitySelect?.selectedOptions?.[0]?.text || '';
    if (modalCourse) modalCourse.value = courseSelect?.selectedOptions?.[0]?.text || '';
    if (modalWeek) modalWeek.value = weekSelect?.selectedOptions?.[0]?.text || '';
    await loadModalSpecialties(filters.universityId);
    openModal(false);
    currentStep = 1;
    updateStepDisplay();

    taskModalOverlay?.querySelector('#inlineOpenCopyMove')?.remove();
    if (saveTaskBtn) saveTaskBtn.onclick = null;
  });

  resetFiltersBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    state = defaultState(); currentPage = 1;
    if (academicYearSelect) academicYearSelect.selectedIndex = 0;
    if (universitySelect) clearAndDisable(universitySelect, 'اختر الجامعة');
    if (semesterSelect) clearAndDisable(semesterSelect, 'اختر الفصل');
    if (courseSelect) clearAndDisable(courseSelect, 'اختر المساق');
    if (weekSelect) clearAndDisable(weekSelect, 'اختر الأسبوع');
    if (specialtySelect) specialtySelect.selectedIndex = 0;
    if (searchTitleInput) searchTitleInput.value = '';
    if (searchSpecInput) searchSpecInput.value = '';
    if (dateFromInput) dateFromInput.value = '';
    if (dateToInput) dateToInput.value = '';
    Object.assign(filters, state.filters); saveState(state);
    if (tasksTableBody) tasksTableBody.innerHTML = ''; if (paginationInfo) paginationInfo.textContent = 'صفحة 1 من 1';
  });

  /* ==================== استعادة الكاش عند التهيئة ==================== */
  async function restoreFromCacheAndLoad() {
    try {
      await loadAcademicYears(); await loadTaskTypes();

      if (filters.yearId) {
        if (academicYearSelect) academicYearSelect.value = String(filters.yearId);
        const [unis, sems] = await Promise.all([apiGetUniversities(filters.yearId), apiGetSemesters(filters.yearId)]);
        if (universitySelect) {
          setSelectOptions(universitySelect, unis.map(u => `<option value="${u.id}">${u.name}</option>`).join(''), 'اختر الجامعة');
          setEnabled(universitySelect, true);
        }
        if (semesterSelect) {
          setSelectOptions(semesterSelect, sems.map(s => `<option value="${s.id}">${s.name}</option>`).join(''), 'اختر الفصل');
          setEnabled(semesterSelect, true);
        }
      }
      if (filters.universityId && universitySelect) universitySelect.value = String(filters.universityId);
      if (filters.semesterId && semesterSelect) semesterSelect.value = String(filters.semesterId);

      if (filters.yearId && filters.universityId && filters.semesterId) {
        const courses = await apiGetCourses(filters.yearId, filters.universityId, filters.semesterId);
        if (courseSelect) {
          setSelectOptions(courseSelect, courses.map(c => `<option value="${c.id}">${c.name}</option>`).join(''), 'اختر المساق');
          setEnabled(courseSelect, true);
        }
      }

      if (filters.courseOfferingId && courseSelect) {
        courseSelect.value = String(filters.courseOfferingId);
        const weeks = await apiGetWeeks(filters.courseOfferingId);
        if (weekSelect) {
          setSelectOptions(weekSelect, weeks.map(w => `<option value="${w.id}">أسبوع ${w.week_number} - ${w.title}</option>`).join(''), 'اختر الأسبوع');
          setEnabled(weekSelect, true);
        }
      }
      if (filters.weekId && weekSelect) weekSelect.value = String(filters.weekId);

      if (filters.universityId) {
        const specs = await apiGetSpecialties(filters.universityId);
        if (specialtySelect) specialtySelect.innerHTML = '<option value="">جميع التخصصات</option>' + specs.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
      }
      if (filters.specialtyId && specialtySelect) specialtySelect.value = String(filters.specialtyId);

      if (searchTitleInput) searchTitleInput.value = state.search.qTitle || '';
      if (searchSpecInput) searchSpecInput.value = state.search.qSpec || '';
      if (dateFromInput) dateFromInput.value = state.search.dateFrom || '';
      if (dateToInput) dateToInput.value = state.search.dateTo || '';

      if (filters.weekId && filters.yearId && filters.universityId && filters.semesterId) await loadTasks(currentPage);
      else {
        if (paginationInfo) paginationInfo.textContent = 'صفحة 1 من 1';
        if (tasksTableBody) tasksTableBody.innerHTML = '<tr><td colspan="9" style="text-align:center">يرجى اختيار جميع الفلاتر</td></tr>';
      }
    } catch (e) {
      console.warn('[TASKS] Failed to restore state, resetting…', e);
      state = defaultState(); saveState(state);
      if (paginationInfo) paginationInfo.textContent = 'صفحة 1 من 1';
      if (tasksTableBody) tasksTableBody.innerHTML = '<tr><td colspan="9" style="text-align:center">يرجى اختيار جميع الفلاتر</td></tr>';
    }
  }

  // أنشئ مودال النسخ/النقل مبكراً لربط الأحداث حتى قبل فتح التعديل
  createCopyMoveModalIfMissing();

  /* ======================================================================
   * إدارة قسم دراسة الحالة
   * ==================================================================== */
  const CASE_STUDY_TASK_TYPE_ID = 42; // ID نوع مهمة دراسة الحالة

  // إظهار/إخفاء قسم دراسة الحالة
  function toggleCaseStudySection(show) {
    const section = document.getElementById('caseStudySection');
    if (!section) return;
    section.style.display = show ? 'block' : 'none';
  }

  // بناء بيانات دراسة الحالة كـ JSON
  function buildCaseStudyData() {
    const criteria = [];
    const criteriaInputs = document.querySelectorAll('#criteriaContainer .criterion-input');
    criteriaInputs.forEach(input => {
      const value = input.value.trim();
      if (value) criteria.push(value);
    });

    const cases = [];
    document.querySelectorAll('#casesContainer .case-item').forEach((item, index) => {
      const title = item.querySelector('.case-title')?.value.trim() || '';
      const text = item.querySelector('.case-text')?.value.trim() || '';
      if (title || text) {
        cases.push({
          index: index + 1,
          title: title || `الحالة ${index + 1}`,
          text: text
        });
      }
    });

    return {
      type: 'case_study',
      criteria: criteria,
      cases: cases
    };
  }

  // تحميل بيانات دراسة الحالة من JSON
  function loadCaseStudyData(data) {
    // تحميل المعايير
    const criteriaContainer = document.getElementById('criteriaContainer');
    if (criteriaContainer && data.criteria) {
      criteriaContainer.innerHTML = '';
      data.criteria.forEach(criterion => {
        addCriterionRow(criterion);
      });
      if (data.criteria.length === 0) {
        addCriterionRow();
      }
    }

    // تحميل الحالات
    const casesContainer = document.getElementById('casesContainer');
    if (casesContainer && data.cases) {
      casesContainer.innerHTML = '';
      data.cases.forEach(caseData => {
        addCaseItem(caseData.title, caseData.text);
      });
      if (data.cases.length === 0) {
        addCaseItem();
      }
    }
  }

  // إضافة صف معيار
  function addCriterionRow(value = '') {
    const container = document.getElementById('criteriaContainer');
    if (!container) return;

    const div = document.createElement('div');
    div.className = 'criterion-row flex-row';
    // div.style.cssText = 'display: flex; gap: 8px; margin-bottom: 8px;'; // Removed in favor of CSS classes
    div.innerHTML = `
      <input type="text" class="criterion-input ap-input flex-grow" value="${value.replace(/"/g, '&quot;')}" placeholder="مثال: 1.1.4 - فهم طبيعة التعلم">
      <button type="button" class="btn-remove-criterion btn-icon-danger">
        <i class="fas fa-trash"></i>
      </button>
    `;

    div.querySelector('.btn-remove-criterion').addEventListener('click', () => {
      div.remove();
    });

    container.appendChild(div);
  }

  // إضافة حالة جديدة
  function addCaseItem(title = '', text = '') {
    const container = document.getElementById('casesContainer');
    if (!container) return;

    const caseCount = container.querySelectorAll('.case-item').length + 1;
    const div = document.createElement('div');
    div.className = 'case-item case-card';
    // div.style.cssText = ...; // Removed
    div.innerHTML = `
      <div class="case-card-header">
        <h5 class="case-title-text">الحالة #${caseCount}</h5>
        <button type="button" class="btn-remove-case btn-icon-danger-sm">
          <i class="fas fa-trash"></i> حذف
        </button>
      </div>
      <div class="ap-field mb-2">
        <label class="ap-label">عنوان الحالة</label>
        <input type="text" class="case-title ap-input" value="${title.replace(/"/g, '&quot;')}" placeholder="عنوان الحالة">
      </div>
      <div class="ap-field">
        <label class="ap-label">نص دراسة الحالة</label>
        <textarea class="case-text ap-input" rows="5" placeholder="نص دراسة الحالة...">${text.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea>
      </div>
    `;

    div.querySelector('.btn-remove-case').addEventListener('click', () => {
      div.remove();
      updateCaseNumbers();
    });

    container.appendChild(div);
  }

  // تحديث أرقام الحالات
  function updateCaseNumbers() {
    document.querySelectorAll('#casesContainer .case-item').forEach((item, index) => {
      const h5 = item.querySelector('h5');
      if (h5) h5.textContent = `الحالة #${index + 1}`;
    });
  }

  // مستمع تغيير نوع المهمة
  if (taskTypeSelect) {
    taskTypeSelect.addEventListener('change', () => {
      const taskTypeId = parseInt(taskTypeSelect.value || '0');
      const isCaseStudy = (taskTypeId === CASE_STUDY_TASK_TYPE_ID);

      toggleCaseStudySection(isCaseStudy);

      // إخفاء/إظهار taskPrompt العادي
      const taskPromptField = taskPromptInput?.closest('.ap-field');
      if (taskPromptField) {
        taskPromptField.style.display = isCaseStudy ? 'none' : 'block';
      }
    });
  }

  // مستمعات الأزرار
  const btnAddCriterion = document.getElementById('btnAddCriterion');
  const btnAddCase = document.getElementById('btnAddCase');

  if (btnAddCriterion) {
    btnAddCriterion.addEventListener('click', () => addCriterionRow());
  }

  if (btnAddCase) {
    btnAddCase.addEventListener('click', () => addCaseItem());
  }

  restoreFromCacheAndLoad();
}
