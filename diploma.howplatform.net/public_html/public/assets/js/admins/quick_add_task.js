/* global Swal, showSuccess, showError, showWarning */

const API_BASE = (window.API_BASE || '').replace(/\/+$/, '');

/* --------------------------- الحالة العامّة -------------------------- */
let selectedFiles = [];        // ملفات جديدة فقط

/* ------------------------- عناصر المودال ----------------------------- */
function getEls() {
  const m = document.getElementById('taskModalOverlay');
  return {
    modal     : m,
    idInp     : m.querySelector('#taskId'),
    yearSel   : m.querySelector('#modalAcademicYear'),
    semSel    : m.querySelector('#modalSemester'),
    uniSel    : m.querySelector('#modalUniversity'),
    courseSel : m.querySelector('#modalCourse'),
    weekSel   : m.querySelector('#modalWeek'),
    typeSel   : m.querySelector('#taskType'),
    titleInp  : m.querySelector('#taskTitle'),
    descInp   : m.querySelector('#taskDescription'),
    pubInp    : m.querySelector('#publishDate'),
    dueInp    : m.querySelector('#dueDate'),
    specDiv   : m.querySelector('#specialtyOptions'),
    fileInp   : m.querySelector('#fileInput'),
    fileBtn   : m.querySelector('#fileUploadBtn'),
    filePrev  : m.querySelector('#filePreviewContainer'),
    dropZone  : m.querySelector('.file-upload-section'),
    btnSave   : m.querySelector('#saveTaskBtn'),
    btnClose  : m.querySelector('#closeModalBtn'),
    btnCancel : m.querySelector('#cancelModalBtn'),
  };
}

/* --------------------- دوال مساعدة للملفّات -------------------------- */
function getIconClass(name = '') {
  const ext = name.split('.').pop().toLowerCase();
  switch (ext) {
    case 'pdf':  return 'fas fa-file-pdf';
    case 'doc':
    case 'docx': return 'fas fa-file-word';
    case 'ppt':
    case 'pptx': return 'fas fa-file-powerpoint';
    case 'xls':
    case 'xlsx': return 'fas fa-file-excel';
    case 'jpg':
    case 'jpeg':
    case 'png':
    case 'gif':  return 'fas fa-file-image';
    case 'zip':
    case 'rar':  return 'fas fa-file-archive';
    default:     return 'fas fa-file';
  }
}

function renderFilePreviews() {
  const { filePrev } = getEls();
  filePrev.innerHTML = '';
  selectedFiles.forEach((f, i) => {
    filePrev.insertAdjacentHTML(
      'beforeend',
        `<div class="file-card">
        <i class="${getIconClass(f.name)} file-icon"></i>
        <div class="file-name">${f.name}</div>
        <textarea class="file-desc" placeholder="وصف الملف"></textarea>
        <button type="button" class="remove-file" data-idx="${i}">&times;</button>
      </div>`
    );
  });

  // زر الحذف
  filePrev.querySelectorAll('.remove-file').forEach((btn) =>
    btn.addEventListener('click', () => {
      const idx = +btn.dataset.idx;
      selectedFiles.splice(idx, 1);
      renderFilePreviews();
    })
  );
}

/* ---------------------- تهيئة الأحداث الأُولى ------------------------ */
function bindModalEvents() {
  const e = getEls();

  /* تحميل البيانات الثابتة */
  loadAcademicYears();
  loadTaskTypes();

  /* ترابط القوائم المنسدلة */
  e.yearSel.addEventListener('change', async () => {
    e.semSel.disabled = e.uniSel.disabled = true;
    e.semSel.innerHTML = e.uniSel.innerHTML = '<option>…</option>';

    if (!e.yearSel.value) return;
    await Promise.all([loadSemesters(e.yearSel.value), loadUniversities(e.yearSel.value)]);
    e.semSel.disabled = e.uniSel.disabled = false;

    clearSelect(e.courseSel);
    clearSelect(e.weekSel);
  });

  e.semSel.addEventListener('change', () => {
    clearSelect(e.courseSel);
    clearSelect(e.weekSel);
    tryLoadCourses();
  });

  e.uniSel.addEventListener('change', () => {
    clearSelect(e.courseSel);
    clearSelect(e.weekSel);
    loadSpecialties(e.uniSel.value);
    tryLoadCourses();
  });

  e.courseSel.addEventListener('change', () => {
    e.courseSel.value ? loadWeeks(e.courseSel.value) : clearSelect(e.weekSel);
  });

  /* التواريخ */
  e.pubInp.addEventListener('change', () => {
    if (!e.pubInp.value) return;
    const dt = new Date(e.pubInp.value);
    dt.setDate(dt.getDate() + 7);
    e.dueInp.value = dt.toISOString().slice(0, 10);
    e.dueInp.removeAttribute('readonly');
  });

  /* الملفات */
  e.fileInp.addEventListener('change', (ev) => {
    selectedFiles = selectedFiles.concat([...ev.target.files]);
    e.fileInp.value = '';
    renderFilePreviews();
  });
  e.fileBtn.addEventListener('click', () => e.fileInp.click());

  /* Drag-&-Drop */
  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((evt) =>
    e.dropZone.addEventListener(evt, (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
    })
  );
  ['dragenter', 'dragover'].forEach((evt) =>
    e.dropZone.addEventListener(evt, () => e.dropZone.classList.add('dragover'))
  );
  ['dragleave', 'drop'].forEach((evt) =>
    e.dropZone.addEventListener(evt, () => e.dropZone.classList.remove('dragover'))
  );
  e.dropZone.addEventListener('drop', (ev) => {
    const dt = ev.dataTransfer;
    if (dt && dt.files.length) {
      selectedFiles = selectedFiles.concat([...dt.files]);
      renderFilePreviews();
    }
  });

  /* أزرار الإغلاق والحفظ */
  e.btnClose.addEventListener('click', hide);
  e.btnCancel.addEventListener('click', hide);
  e.btnSave.addEventListener('click', saveTask);
}

/* ---------------------------- أدوات عامّة --------------------------- */
function clearSelect(sel) {
  sel.innerHTML = '<option value="">—</option>';
  sel.disabled = true;
}
function tryLoadCourses() {
  const e = getEls();
  if (e.yearSel.value && e.uniSel.value && e.semSel.value) loadCourses();
}

/* -------------------------- إظهار / إخفاء --------------------------- */
function show() {
  const { modal } = getEls();
  if (!modal.dataset.bound) {
    bindModalEvents();
    modal.dataset.bound = '1';
  }
  resetForm();
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}
function hide() {
const { modal, filePrev, fileInp } = getEls();
   modal.classList.remove('active');
   document.body.style.overflow = '';
   selectedFiles = [];
   filePrev.innerHTML = '';
   fileInp.value = '';
}

/* يمكن استدعاء هذه الدالة من أي مكان لفتح المودال */
export function showQuickTaskModal() {
  show();
}

/* -------------------------- إعادة التعيين --------------------------- */
function resetForm() {
  const e = getEls();
  e.idInp.value = '';
  e.titleInp.value = e.descInp.value = '';
  e.pubInp.value = e.dueInp.value = '';

  e.yearSel.selectedIndex = 0;
  ['semSel', 'uniSel', 'courseSel', 'weekSel'].forEach((k) => clearSelect(e[k]));

  e.specDiv.innerHTML = '';
  selectedFiles = [];
  e.fileInp.value = '';
  renderFilePreviews();
}

/* -------------------------- التحقّق والحفظ -------------------------- */
function validate(e) {
  if (
    !e.yearSel.value ||
    !e.semSel.value ||
    !e.uniSel.value ||
    !e.courseSel.value ||
    !e.weekSel.value ||
    !e.typeSel.value ||
    !e.titleInp.value.trim() ||
    !e.descInp.value.trim() ||
    !e.pubInp.value ||
    !e.dueInp.value
  ) {
    showWarning('يرجى تعبئة جميع الحقول الإلزامية.');
    return false;
  }
  return true;
}

/* ===============================================================
 * حفظ المهمة مع دعم أوصاف الملفات               (quick_add_task.js)
 * ===============================================================*/
async function saveTask() {
  const e = getEls();
  if (!validate(e)) return;

  /* ---------- التخصُّصات ---------- */
  const specChecks = [...e.specDiv.querySelectorAll('input[type="checkbox"]')];
  const isAll      = specChecks.find(c => c.value === 'all')?.checked;
  const specIds    = isAll
    ? ['all']
    : specChecks.filter(c => c.checked && c.value !== 'all').map(c => c.value);

  if (!isAll && specIds.length === 0) {
    showWarning('اختر التخصصات المستهدفة أو علِّم خيار "عام لكل التخصصات".');
    return;
  }

  /* ---------- FormData ---------- */
  const fd = new FormData();
  if (e.idInp.value) fd.append('taskId', e.idInp.value);          // وضع Edit
  fd.append('weekId',          e.weekSel.value);
  fd.append('taskTitle',       e.titleInp.value.trim());
  fd.append('taskType',        e.typeSel.value);
  fd.append('taskDescription', e.descInp.value.trim());
  fd.append('publishDate',     e.pubInp.value);
  fd.append('dueDate',         e.dueInp.value);
  fd.append('specialties',     JSON.stringify(specIds));
  fd.append('is_for_all_specialties', isAll ? 1 : 0);

  /* ---------- الملفات + الأوصاف ---------- */
const newFileDescs = Array.from(
  e.filePrev.querySelectorAll('.file-card .file-desc')
).map(t => t.value.trim());

// إرسال الملفات الجديدة وأوصافها بنفس الترتيب
selectedFiles.forEach((f, i) => {
  fd.append('files[]',     f, f.name);
  fd.append('filesDesc[]', newFileDescs[i] ?? '');
});



  /* ---------- واجهة المستخدم ---------- */
  e.btnSave.disabled  = true;
  e.btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> …';

  try {
    const res = await fetch(`${API_BASE}/task/createOrUpdateTask`, {
      method : 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body   : fd,
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const j = await res.json();

    if (j.status === 'success' || j.success) {
      showSuccess(j.message || 'تم حفظ المهمة', undefined , true);
      hide();
    } else {
      throw new Error(j.message || 'خطأ غير معروف');
    }
  } catch (err) {
    console.error(err);
    showError('تعذّر الاتصال بالخادم أو فشل الحفظ');
  } finally {
    e.btnSave.disabled  = false;
    e.btnSave.innerHTML = '<i class="fas fa-save"></i> حفظ المهمة';
  }
}

/* ------------------- تحميل البيانات (منفصلة) ----------------------- */
async function loadAcademicYears() {
  const { yearSel } = getEls();
  const json  = await (await fetch(`${API_BASE}/task/getAcademicYears`)).json();
  const years = Array.isArray(json) ? json : json.data || [];
  yearSel.innerHTML =
    '<option value="">اختر العام الدراسي</option>' +
    years.map((y) => `<option value="${y.id}">${y.label}</option>`).join('');
}

async function loadTaskTypes() {
  const { typeSel } = getEls();
  const json  = await (await fetch(`${API_BASE}/task/getTaskTypes`)).json();
  const types = Array.isArray(json) ? json : json.data || [];
  typeSel.innerHTML =
    '<option value="">-- اختر نوع المهمة --</option>' +
    types.map((t) => `<option value="${t.id}">${t.name}</option>`).join('');
}

async function loadUniversities(yearId) {
  const { uniSel } = getEls();
  const list = await (await fetch(`${API_BASE}/task/getUniversities/${yearId}`)).json();
  uniSel.innerHTML =
    '<option value="">اختر الجامعة</option>' +
    list.map((u) => `<option value="${u.id}">${u.name}</option>`).join('');
  uniSel.disabled = false;
}

async function loadSemesters(yearId) {
  const { semSel } = getEls();
  const list = await (await fetch(`${API_BASE}/task/getSemesters/${yearId}`)).json();
  semSel.innerHTML =
    '<option value="">اختر الفصل</option>' +
    list.map((s) => `<option value="${s.id}">${s.name}</option>`).join('');
  semSel.disabled = false;
}

async function loadCourses() {
  const { courseSel, yearSel, uniSel, semSel } = getEls();
  const params = new URLSearchParams({
    yearId: yearSel.value,
    univId: uniSel.value,
    semId : semSel.value,
  });
  const list = await (await fetch(`${API_BASE}/task/getCourses?${params}`)).json();
  courseSel.innerHTML =
    '<option value="">اختر المساق</option>' +
    list.map((c) => `<option value="${c.id}">${c.name}</option>`).join('');
  courseSel.disabled = false;
}

async function loadWeeks(offeringId) {
  const { weekSel } = getEls();
  const list = await (await fetch(`${API_BASE}/task/getWeeks/${offeringId}`)).json();
  weekSel.innerHTML =
    '<option value="">اختر الأسبوع</option>' +
    list
      .map((w) => `<option value="${w.id}">أسبوع ${w.week_number} - ${w.title}</option>`)
      .join('');
  weekSel.disabled = false;
}

async function loadSpecialties(univId) {
  const { specDiv } = getEls();
  let url = `${API_BASE}/task/getSpecialties`;
  if (univId) url += `/${univId}`;

  const specs = await (await fetch(url)).json();
  specDiv.innerHTML =
    specs
      .map(
        (s) => `
      <label class="spec-card">
        <input type="checkbox" value="${s.id}" hidden /> ${s.name}
      </label>`
      )
      .join('') +
    `
    <label class="spec-card">
      <input type="checkbox" value="all" hidden /> عام لكل التخصصات
    </label>`;

  /* تفاعل البطاقات */
  const chkAll = specDiv.querySelector('input[value="all"]');
  specDiv.querySelectorAll('.spec-card input:not([value="all"])').forEach((chk) => {
    const card = chk.parentElement;
    card.addEventListener('click', () => {
      chk.checked = !chk.checked;
      card.classList.toggle('selected', chk.checked);
      if (chk.checked) {
        chkAll.checked = false;
        chkAll.parentElement.classList.remove('selected');
      }
    });
  });
  chkAll.parentElement.addEventListener('click', () => {
    chkAll.checked = !chkAll.checked;
    chkAll.parentElement.classList.toggle('selected', chkAll.checked);
    if (chkAll.checked) {
      specDiv
        .querySelectorAll('.spec-card input:not([value="all"])')
        .forEach((c) => {
          c.checked = false;
          c.parentElement.classList.remove('selected');
        });
    }
  });
}

/* -------------------- تهيئة فورية للمودال --------------------------- */
document.addEventListener('DOMContentLoaded', () => {
  // إن رغبت في زر ثابت بصفحة ما استدعِ showQuickTaskModal() منه.
});
