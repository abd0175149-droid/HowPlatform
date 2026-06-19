// students/sections/ai_lesson_plan_modal.js
// Requires in layout.php: <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

let __chipsDelegated = false;

const UI = {
  root: null, openBtn: null, downloadBtn: null, statusBadge: null,
  modalBackdrop: null, closeModalBtn: null, form: null, submitBtn: null,
  fields: {},
};

// -------------------- Helpers --------------------
function urlParam(name) { return new URLSearchParams(location.search).get(name); }

function getTaskId() {
  const fromPayload = (window.__pendingSectionPayload && window.__pendingSectionPayload.task_id) || null;
  return String(
    UI.root?.dataset.taskId ||
    fromPayload ||
    urlParam('task_id') ||
    UI.fields.task_id?.value ||
    '198'
  ).trim();
}

function getTypeKey() {
  return (
    UI.root?.dataset.typeKey ||
    urlParam('type_key') ||
    UI.fields.type_key?.value ||
    'lesson_plan_v1'
  );
}

function getCsrfHeader() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? { 'X-CSRF-TOKEN': meta.getAttribute('content') || '' } : {};
}

function showEl(el) { if (!el) return; el.hidden = false; el.style.display = 'block'; }
function hideEl(el) { if (!el) return; el.hidden = true; el.style.display = 'none'; }

// -------------------- Modal open/close --------------------
function openModal() {
  if (!UI.modalBackdrop) return;
  // عند كل فتح: أعد غلق الخيارات المتقدمة فورًا بدون أنيميشن
  advCollapseImmediate();
  UI.modalBackdrop.style.display = 'flex';
  UI.modalBackdrop.setAttribute('aria-hidden', 'false');
}

function closeModal() {
  if (!UI.modalBackdrop) return;
  // عند الإغلاق: نظّف وأغلق اللوحة حتى لا تبقى أنماط عالقة
  advCollapseImmediate();
  UI.modalBackdrop.style.display = 'none';
  UI.modalBackdrop.setAttribute('aria-hidden', 'true');
}

function setStatusBadge(text) {
  if (!UI.statusBadge) return;
  UI.statusBadge.style.display = text ? 'inline-flex' : 'none';
  UI.statusBadge.textContent = text || '';
}
function disableRequest(msg = '') {
  if (UI.openBtn) UI.openBtn.disabled = true;
  setStatusBadge(msg);
}
function enableRequest() {
  if (UI.openBtn) UI.openBtn.disabled = false;
  setStatusBadge('');
}
function showDownload(url) {
  if (!url || !UI.downloadBtn) return;
  UI.downloadBtn.href = url;
  UI.downloadBtn.style.display = 'inline-flex';
}
function hideDownload() {
  if (!UI.downloadBtn) return;
  UI.downloadBtn.style.display = 'none';
  UI.downloadBtn.removeAttribute('href');
}

// ===== Profile Cache (only 4 fields) =====
const PROFILE_CACHE_NS = 'lp:v1:profile';
function _debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }

// مفتاح الكاش يعتمد على user_id إن توفّر
function _profileKey() {
  const uid = UI?.root?.dataset?.userId || document.body?.dataset?.userId || 'u0';
  return `${PROFILE_CACHE_NS}:${uid}`;
}

// احفظ فقط: teacher_name, school_name, student_gender, teacher_gender
function saveProfileDefaults() {
  const data = {
    teacher_name: UI.fields.teacher_name?.value || '',
    school_name: UI.fields.school_name?.value || '',
    student_gender: UI.fields.student_gender?.value || '',
    teacher_gender: UI.fields.teacher_gender?.value || '',
  };
  try { localStorage.setItem(_profileKey(), JSON.stringify(data)); } catch { }
}

// حمّل فقط نفس الحقول، ولا تلمس أي حقول أخرى
function loadProfileDefaults() {
  try {
    const js = JSON.parse(localStorage.getItem(_profileKey()) || 'null');
    if (!js) return;
    if (UI.fields.teacher_name && !UI.fields.teacher_name.value) UI.fields.teacher_name.value = js.teacher_name || '';
    if (UI.fields.school_name && !UI.fields.school_name.value) UI.fields.school_name.value = js.school_name || '';
    if (UI.fields.student_gender && !UI.fields.student_gender.value) UI.fields.student_gender.value = js.student_gender || '';
    if (UI.fields.teacher_gender && !UI.fields.teacher_gender.value) UI.fields.teacher_gender.value = js.teacher_gender || '';
  } catch { }
}

// اربط حفظ تلقائي لهذه الحقول فقط
const saveProfileDebounced = _debounce(saveProfileDefaults, 350);
function bindProfileAutosave() {
  [
    UI.fields.teacher_name,
    UI.fields.school_name,
    UI.fields.student_gender,
    UI.fields.teacher_gender
  ].forEach(el => {
    if (!el) return;
    el.addEventListener('input', saveProfileDebounced);
    el.addEventListener('change', saveProfileDebounced);
  });
}

// -------------------- SweetAlert2 --------------------
const SWZ = 20000;
function showSuccess(msg) { Swal.fire({ icon: 'success', title: 'تم بنجاح', text: msg, confirmButtonText: 'حسناً', heightAuto: false, zIndex: SWZ }); }
function showError(msg) { Swal.fire({ icon: 'error', title: 'حدث خطأ', text: msg, confirmButtonText: 'إغلاق', heightAuto: false, zIndex: SWZ }); }
function showInfo(msg) { Swal.fire({ icon: 'info', title: 'تنبيه', text: msg, confirmButtonText: 'حسناً', heightAuto: false, zIndex: SWZ }); }
function showLoading(msg = 'جاري المعالجة...') { Swal.fire({ title: msg, allowOutsideClick: false, heightAuto: false, zIndex: SWZ, didOpen: () => Swal.showLoading() }); }
function closeLoading() { try { Swal.close(); } catch (_) { } }
function toastInfo(msg) { Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: msg, showConfirmButton: false, timer: 1800, timerProgressBar: true, heightAuto: false, zIndex: SWZ }); }

// -------------------- Advanced Toggle (no memory, always starts collapsed) --------------------
const ADV = { btn: null, panel: null, isAnimating: false };

// ⚙️ إغلاق فوري بدون أنيميشن (نستخدمه عند فتح/إغلاق المودال وعند التهيئة)
function advCollapseImmediate() {
  if (!ADV.panel || !ADV.btn) return;
  ADV.isAnimating = false;
  ADV.panel.style.transition = '';
  ADV.panel.style.overflow = '';
  ADV.panel.style.maxHeight = '';
  ADV.panel.style.display = 'none';
  ADV.panel.hidden = true;
  ADV.panel.dataset.open = '0';        // حالة داخلية غير محفوظة
  ADV.btn.setAttribute('aria-expanded', 'false');
}

// ▶️ فتح مع أنيميشن
function advOpen() {
  if (!ADV.panel || !ADV.btn || ADV.isAnimating) return;
  const panel = ADV.panel;
  ADV.isAnimating = true;

  // حضّر الحالة الابتدائية
  panel.hidden = false;
  panel.style.display = 'block';
  panel.style.overflow = 'hidden';
  panel.style.maxHeight = '0px';

  // transition + القياس
  requestAnimationFrame(() => {
    const h = panel.scrollHeight;
    panel.style.transition = 'max-height 220ms ease';
    panel.style.maxHeight = h + 'px';
  });

  const onEnd = () => {
    panel.removeEventListener('transitionend', onEnd);
    panel.style.transition = '';
    panel.style.overflow = '';
    panel.style.maxHeight = '';
    panel.dataset.open = '1';
    ADV.btn.setAttribute('aria-expanded', 'true');
    ADV.isAnimating = false;
  };
  panel.addEventListener('transitionend', onEnd, { once: true });
}

// ◀️ إغلاق مع أنيميشن
function advClose() {
  if (!ADV.panel || !ADV.btn || ADV.isAnimating) return;
  const panel = ADV.panel;
  const cs = getComputedStyle(panel);
  if (panel.hidden || cs.display === 'none') { advCollapseImmediate(); return; }

  ADV.isAnimating = true;

  // عيّن الارتفاع الحالي كبداية للأنيميشن
  const h = panel.scrollHeight;
  panel.style.overflow = 'hidden';
  panel.style.maxHeight = h + 'px';
  // force reflow
  // eslint-disable-next-line no-unused-expressions
  panel.offsetHeight;
  panel.style.transition = 'max-height 200ms ease';
  panel.style.maxHeight = '0px';

  const onEnd = () => {
    panel.removeEventListener('transitionend', onEnd);
    panel.style.transition = '';
    panel.style.overflow = '';
    panel.style.maxHeight = '';
    panel.style.display = 'none';
    panel.hidden = true;
    panel.dataset.open = '0';
    ADV.btn.setAttribute('aria-expanded', 'false');
    ADV.isAnimating = false;
  };
  panel.addEventListener('transitionend', onEnd, { once: true });
}

function isAdvancedOpen() {
  if (!ADV.panel) return false;
  const cs = getComputedStyle(ADV.panel);
  return ADV.panel.dataset.open === '1' && !ADV.panel.hidden && cs.display !== 'none';
}

function initAdvancedToggle() {
  ADV.btn = document.getElementById('toggleAdvanced');
  ADV.panel = document.getElementById('advancedOptions');
  if (!ADV.btn || !ADV.panel) return;

  ADV.btn.type = 'button';

  // ابدأ مغلقًا دائمًا
  advCollapseImmediate();

  const onToggle = (e) => {
    e.preventDefault();
    if (ADV.isAnimating) return;
    if (isAdvancedOpen()) advClose(); else advOpen();
  };

  ADV.btn.addEventListener('click', onToggle);
  ADV.btn.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') onToggle(e);
  });
}

// -------------------- Chips (max 3) --------------------
const MAX_CHIPS = 3;

// تفويض + منع تكرار الربط
function initChips() {
  if (__chipsDelegated) return;           // لا نكرر الربط
  __chipsDelegated = true;

  const groups = [
    { id: 'teachingStrategies', field: 'teaching_strategies' },
    { id: 'assessmentMethods', field: 'assessment_methods' }
  ];

  groups.forEach(({ id, field }) => {
    const container = document.getElementById(id);
    if (!container) return;

    // تأكد من وجود hidden input لتجميع القيم
    if (!UI.fields[field]) {
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = field;
      hidden.id = field;
      (UI.form || document.body).appendChild(hidden);
      UI.fields[field] = hidden;
    }

    // تفويض أحداث النقر داخل الحاوية
    container.addEventListener('click', (e) => {
      const chip = e.target.closest('.chip');
      if (!chip || !container.contains(chip)) return;

      // لو chip عبارة عن <button> أو <a> امنع السلوك الافتراضي
      if (chip.tagName === 'BUTTON' || chip.tagName === 'A') {
        e.preventDefault();
      }

      // لا تسمح بتجاوز الحد الأقصى
      const willActivate = !chip.classList.contains('active');
      const activeNow = container.querySelectorAll('.chip.active').length;
      if (willActivate && activeNow >= MAX_CHIPS) {
        chip.classList.add('deny');
        setTimeout(() => chip.classList.remove('deny'), 260);
        toastInfo(`يمكن اختيار ${MAX_CHIPS} فقط في هذه المجموعة`);
        return;
      }

      // بدّل الحالة وحدث القيمة المخفية
      chip.classList.toggle('active');
      const selected = Array.from(container.querySelectorAll('.chip.active'))
        .map(ch => ch.dataset.value ?? ch.getAttribute('data-value') ?? ch.textContent.trim());
      UI.fields[field].value = selected.join(',');
    });
  });
}

function collectSelectedChips() {
  const res = {};
  ['teachingStrategies', 'assessmentMethods'].forEach(id => {
    const chips = document.querySelectorAll(`#${id} .chip.active`);
    res[id] = Array.from(chips).map(ch => ch.dataset.value ?? ch.getAttribute('data-value') ?? ch.textContent.trim());
  });
  return res;
}

// -------------------- SEN visibility --------------------
function initSENVisibility() {
  const senSupportField = document.getElementById('senSupportField');
  const senSupportOtherField = document.getElementById('senSupportOtherField');

  // ابدأ بإخفاء الاثنين دائماً
  hideEl(senSupportField);
  hideEl(senSupportOtherField);

  function update() {
    const hasSEN = !!UI.fields.sen_present?.checked;
    const isOther = (UI.fields.sen_support?.value || '') === 'أخرى';

    if (hasSEN) {
      showEl(senSupportField); // عند تفعيل وجود طلبة → أظهر القائمة
      if (isOther) showEl(senSupportOtherField);
      else {
        hideEl(senSupportOtherField);
        if (UI.fields.sen_support_other) UI.fields.sen_support_other.value = '';
      }
    } else {
      hideEl(senSupportField);          // لا يوجد طلبة → أخفِ كِلاهما وصَفِّر القيم
      hideEl(senSupportOtherField);
      if (UI.fields.sen_support) UI.fields.sen_support.value = '';
      if (UI.fields.sen_support_other) UI.fields.sen_support_other.value = '';
    }
  }

  UI.fields.sen_present?.addEventListener('change', update);
  UI.fields.sen_support?.addEventListener('change', update);

  // تأكيد الحالة الابتدائية (دائمًا مخفي حتى لو كان الـcheckbox مضبوطاً مسبقًا)
  update();
}

// ===== Auto-Refresh (status) + mini spinner =====
let _statusTimer = null;
let _statusCssInjected = false;

function injectStatusSpinnerCss() {
  if (_statusCssInjected) return;
  const css = `
    #ai-actions-card .mini-spin{
      display:inline-block;width:14px;height:14px;border-radius:50%;
      border:2px solid var(--border-color, rgba(0,0,0,.15)); border-top-color:var(--primary-500, #fd9e02);
      margin-inline:6px; vertical-align:-2px; animation:lpSpin 1s linear infinite;
    }
    @keyframes lpSpin{ to{ transform:rotate(360deg); } }
  `;
  const s = document.createElement('style');
  s.id = 'lp-status-spinner-css';
  s.textContent = css;
  document.head.appendChild(s);
  _statusCssInjected = true;
}

function setStatusBadgeLoading(text) {
  if (!UI.statusBadge) return;
  injectStatusSpinnerCss();
  UI.statusBadge.style.display = 'inline-flex';
  UI.statusBadge.innerHTML = `<span class="mini-spin"></span>`;
  UI.statusBadge.appendChild(document.createTextNode(text || 'جاري التحقق...'));
}

function startStatusAutoRefresh() {
  if (_statusTimer) return;
  _statusTimer = setInterval(() => { checkStatus({ silent: true, withSpinner: true }); }, 60_000);
}

function stopStatusAutoRefresh() {
  if (_statusTimer) { clearInterval(_statusTimer); _statusTimer = null; }
}

// -------------------- Status (auto-refresh friendly) --------------------
async function checkStatus(opts = {}) {
  const { silent = false, withSpinner = false } = opts;
  try {
    const taskId = getTaskId();
    const typeKey = getTypeKey();
    if (!taskId || !typeKey) {
      enableRequest();
      hideDownload();
      stopStatusAutoRefresh();
      return;
    }

    if (withSpinner) setStatusBadgeLoading('جاري التحقق من الحالة...');

    const q = new URLSearchParams({ type_key: typeKey, task_id: taskId });
    const res = await fetch(`/students/ai-tools/status?${q.toString()}`, {
      method: 'GET', headers: { 'Accept': 'application/json' }, credentials: 'include'
    });
    const json = await res.json().catch(() => ({}));

    if (!json.ok) {
      enableRequest();
      hideDownload();
      if (!silent) showError(json.message || 'تعذّر التحقق من الحالة.');
      stopStatusAutoRefresh();
      return;
    }

    switch (json.state) {
      case 'done':
        disableRequest('تم إنشاء الملف — متاح للتنزيل');
        if (json.download_url) showDownload(json.download_url);
        stopStatusAutoRefresh();
        break;

      case 'processing':
        disableRequest('');
        if (withSpinner) setStatusBadgeLoading('قيد المعالجة...');
        else setStatusBadge('قيد المعالجة...');
        hideDownload();
        startStatusAutoRefresh();
        break;

      case 'pending':
        disableRequest('');
        if (withSpinner) setStatusBadgeLoading('في قائمة الانتظار...');
        else setStatusBadge('في قائمة الانتظار...');
        hideDownload();
        startStatusAutoRefresh();
        break;

      case 'failed':
        disableRequest('فشل سابق — راجع السجل أو أعد المحاولة');
        hideDownload();
        stopStatusAutoRefresh();
        break;

      default:
        enableRequest();
        hideDownload();
        stopStatusAutoRefresh();
    }
  } catch (e) {
    console.error('status error:', e);
    if (!silent) showError('حدث خطأ أثناء التحقق من الحالة.');
  }
}

// -------------------- Submit --------------------
async function submitRequest() {
  if (!UI.submitBtn) return;
  UI.submitBtn.disabled = true;

  const teacher = UI.fields.teacher_name?.value.trim();
  const topic = UI.fields.topic_summary?.value.trim();
  const grade = UI.fields.grade_level?.value.trim();

  if (!teacher || !topic || !grade) {
    showInfo('يرجى تعبئة: اسم المعلم + ملخص الموضوع + الصف/المستوى.');
    UI.submitBtn.disabled = false;
    return;
  }
  if ((topic || '').length < 100) {
    showInfo('الوصف المختصر يجب أن يحتوي على 100 حرف على الأقل.');
    UI.submitBtn.disabled = false;
    return;
  }

  const oc = Number(UI.fields.outcomes_count?.value || 0);
  if (isNaN(oc) || oc < 2) {
    showInfo('عدد النتاجات يجب ألا يقل عن 2.');
    UI.submitBtn.disabled = false;
    return;
  }

  const senSupport = UI.fields.sen_support?.value || '';
  const senSupportOther = (UI.fields.sen_support_other?.value || '').trim();
  if (UI.fields.sen_present?.checked && senSupport === 'أخرى' && senSupportOther.length < 2) {
    showInfo('يرجى إدخال نوع الدعم المطلوب عند اختيار "أخرى".');
    UI.submitBtn.disabled = false;
    return;
  }

  const selections = collectSelectedChips();
  if ((selections.teachingStrategies || []).length > MAX_CHIPS) {
    showInfo(`يمكن اختيار ${MAX_CHIPS} فقط من استراتيجيات التدريس.`);
    UI.submitBtn.disabled = false;
    return;
  }
  if ((selections.assessmentMethods || []).length > MAX_CHIPS) {
    showInfo(`يمكن اختيار ${MAX_CHIPS} فقط من استراتيجيات التقويم.`);
    UI.submitBtn.disabled = false;
    return;
  }

  const payload = {
    type_key: getTypeKey(),
    task_id: Number(getTaskId() || '0'),

    // الأساسية
    teacher_name: teacher,
    school_name: UI.fields.school_name?.value?.trim() || '',
    grade_level: grade,
    subject_name: UI.fields.subject_name?.value?.trim() || '',
    unit_name: UI.fields.unit_name?.value?.trim() || '',
    lesson_title: UI.fields.lesson_title?.value?.trim() || '',
    topic_summary: topic,
    outcomes_count: oc,
    outcomes_description: UI.fields.outcomes_description?.value?.trim() || '',

    // المتقدمة
    duration_minutes: Number(UI.fields.duration_minutes?.value || 40),
    class_size: Number(UI.fields.class_size?.value || 30),
    focus_axis: UI.fields.focus_axis?.value || '',
    constraints: (UI.fields.constraints?.value || '').trim(),
    student_gender: UI.fields.student_gender?.value || '',
    teacher_gender: UI.fields.teacher_gender?.value || '',

    // SEN
    sen_present: !!UI.fields.sen_present?.checked,
    sen_support: senSupport,
    sen_support_other: senSupport === 'أخرى' ? senSupportOther : '',

    // أخرى
    learning_assets: UI.fields.learning_assets?.value?.trim() || '',
    rough_goals_note: UI.fields.rough_goals_note?.value?.trim() || '',

    // شيبس
    teaching_strategies: selections.teachingStrategies || [],
    assessment_methods: selections.assessmentMethods || [],
  };

  // احفظ فقط الحقول المطلوبة في الكاش (بروفايل مصغّر)
  try {
    const LP_CACHE_KEY = 'lp_form_cache_v1';
    const cache = {
      teacher_name: payload.teacher_name || '',
      school_name: payload.school_name || '',
      student_gender: payload.student_gender || '',
      teacher_gender: payload.teacher_gender || ''
    };
    localStorage.setItem(LP_CACHE_KEY, JSON.stringify(cache));
  } catch (_) { }

  try {
    console.log('%c📦 JSON Payload:', 'color:#0a7; font-weight:bold;');
    console.log(JSON.stringify(payload, null, 2));
  } catch (_) { }

  try {
    showLoading('جاري إرسال الطلب...');
    const res = await fetch('/students/ai-tools/request', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json; charset=utf-8',
        'Accept': 'application/json',
        ...getCsrfHeader()
      },
      credentials: 'include',
      body: JSON.stringify(payload),
    });
    closeLoading();
    const json = await res.json().catch(() => ({}));

    if (res.status === 200 && json.ok && json.status === 'accepted') {
      closeModal();
      disableRequest('تم استلام الطلب — قيد المعالجة');
      hideDownload();
      if (typeof checkStatus === 'function') checkStatus({ withSpinner: true, silent: true });
      if (typeof startStatusAutoRefresh === 'function') startStatusAutoRefresh();
      showSuccess('تم استلام الطلب وسيتم تجهيز الملف في الخلفية.');
      return;
    }

    if (res.status === 409 && json.status === 'already_active') {
      closeModal();
      disableRequest('لديك طلب فعّال بالفعل');
      if (typeof checkStatus === 'function') checkStatus({ withSpinner: true, silent: true });
      if (typeof startStatusAutoRefresh === 'function') startStatusAutoRefresh();
      showInfo('لا يمكنك إرسال طلب جديد الآن لهذه المهمة.');
      return;
    }

    if (res.status === 200 && json.status === 'already_done' && json.download_url) {
      closeModal();
      disableRequest('تم الإنشاء سابقًا — متاح للتنزيل');
      showDownload(json.download_url);
      if (typeof stopStatusAutoRefresh === 'function') stopStatusAutoRefresh();
      showInfo('الملف منشأ سابقًا — تم إظهار زر التنزيل.');
      return;
    }

    if (res.status === 409 && json.status === 'failed_locked') {
      closeModal();
      disableRequest('فشل سابق — يحتاج تدخّل');
      hideDownload();
      if (typeof stopStatusAutoRefresh === 'function') stopStatusAutoRefresh();
      showError('توجد عملية فاشلة مقفلة لنفس المهمة والنوع.');
      return;
    }

    showError(json.message || 'تعذّر تنفيذ الطلب. حاول لاحقًا.');
  } catch (e) {
    console.error('request error:', e);
    closeLoading();
    showError('حدث خطأ أثناء الاتصال بالخادم.');
  } finally {
    UI.submitBtn.disabled = false;
  }
}

// -------------------- Mount --------------------
function waitForRoot(maxMs = 3000, intervalMs = 100) {
  return new Promise((resolve, reject) => {
    const start = Date.now();
    const tick = () => {
      const el = document.getElementById('aiLessonPlanRoot');
      if (el) return resolve(el);
      if (Date.now() - start >= maxMs) return reject(new Error('root-timeout'));
      setTimeout(tick, intervalMs);
    };
    tick();
  });
}

async function _mount() {
  UI.root = document.getElementById('aiLessonPlanRoot');
  UI.openBtn = document.getElementById('btnOpenModal');
  UI.downloadBtn = document.getElementById('btnDownload');
  UI.statusBadge = document.getElementById('statusBadge');
  UI.modalBackdrop = document.getElementById('lpModalBackdrop');
  UI.closeModalBtn = document.getElementById('btnCloseModal');
  UI.form = document.getElementById('lessonPlanForm');
  UI.submitBtn = document.getElementById('btnSubmit');

  UI.fields = {
    type_key: document.getElementById('type_key'),
    task_id: document.getElementById('task_id'),

    teacher_name: document.getElementById('teacher_name'),
    teacher_gender: document.getElementById('teacher_gender'),
    school_name: document.getElementById('school_name'),
    grade_level: document.getElementById('grade_level'),
    subject_name: document.getElementById('subject_name'),
    unit_name: document.getElementById('unit_name'),
    lesson_title: document.getElementById('lesson_title'),
    topic_summary: document.getElementById('topic_summary'),
    outcomes_count: document.getElementById('outcomes_count'),
    outcomes_description: document.getElementById('outcomes_description'),

    duration_minutes: document.getElementById('duration_minutes'),
    class_size: document.getElementById('class_size'),
    focus_axis: document.getElementById('focus_axis'),
    constraints: document.getElementById('constraints'),
    student_gender: document.getElementById('student_gender'),

    sen_present: document.getElementById('sen_present'),
    sen_support: document.getElementById('sen_support'),
    sen_support_other: document.getElementById('sen_support_other'),

    learning_assets: document.getElementById('learning_assets'),
    rough_goals_note: document.getElementById('rough_goals_note'),
  };

  // استعادة وحفظ تلقائي لحقول البروفايل الأربعة فقط
  loadProfileDefaults();
  bindProfileAutosave();

  if (UI.fields.constraints && !UI.fields.constraints.placeholder) {
    UI.fields.constraints.placeholder = 'مثال: استخدم أدوات قياس مثل الورنية والميكرومتر';
  }

  if (UI.fields.type_key && !UI.fields.type_key.value) UI.fields.type_key.value = getTypeKey();
  if (UI.fields.task_id && !UI.fields.task_id.value) UI.fields.task_id.value = getTaskId() || '198';

  UI.openBtn?.addEventListener('click', () => {
    if (UI.openBtn.disabled) {
      showInfo('لديك طلب فعّال أو مكتمل لهذه المهمة.');
      return;
    }
    openModal();
  });
  UI.closeModalBtn?.addEventListener('click', closeModal);
  UI.modalBackdrop?.addEventListener('click', (e) => { if (e.target === UI.modalBackdrop) closeModal(); });

  // إغلاق بالمفتاح Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && UI.modalBackdrop && getComputedStyle(UI.modalBackdrop).display !== 'none') {
      closeModal();
    }
  });

  // تأكيد الإرسال
  UI.submitBtn?.addEventListener('click', async (e) => {
    e.preventDefault();
    if (UI.openBtn?.disabled) {
      showInfo('لا يمكن الإرسال الآن لوجود طلب فعّال.');
      return;
    }
    const confirm = await Swal.fire({
      icon: 'question', title: 'تأكيد الإرسال',
      text: 'هل أنت متأكد من إرسال طلب إنشاء خطة الحصة؟',
      showCancelButton: true, confirmButtonText: 'نعم، أرسل', cancelButtonText: 'إلغاء',
      heightAuto: false, zIndex: SWZ
    });
    if (confirm.isConfirmed) submitRequest();
  });

  // تهيئة الميزات
  initAdvancedToggle();   // ✅ الآن بلا أي حفظ حالة — يبدأ مغلقًا دائمًا
  initChips();
  initSENVisibility();

  // حالة الزر/التنزيل عند التحميل
  await checkStatus({ withSpinner: true, silent: true });
  startStatusAutoRefresh();
}

async function init() {
  try {
    await waitForRoot(3000, 100);
    await _mount();
  } catch (e) {
    console.warn('[ai_lesson_plan] root not found, abort init.', e?.message || e);
  }
}

/* ===== UMD Shim (اختياري للاختبار من الكونسول) ===== */
try {
  window.AI_LESSON_PLAN_MODAL = window.AI_LESSON_PLAN_MODAL || {};
  window.AI_LESSON_PLAN_MODAL.init = init;
  window.AI_LESSON_PLAN_MODAL.open = openModal;
  window.AI_LESSON_PLAN_MODAL.close = closeModal;
} catch { }

/* ===== ESM Exports (للاستيراد عبر import()) ===== */
export { init, openModal, closeModal };
export default { init, open: openModal, close: closeModal };
