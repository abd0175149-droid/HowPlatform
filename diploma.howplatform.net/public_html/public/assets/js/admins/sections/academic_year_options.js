


 /* global Swal, reloadCurrentSection */  // SweetAlert2 + دالة موجودة عالميًا من admin_router
const reloadCurrentSection = window.reloadCurrentSection || (() => {});

/*==============================================================
 | ثابت عنوان الـ API (متوافق مع الراوتر الجديد)
 *=============================================================*/
const API_BASE = '/admins/api/academic-years';

/* سنة الفوتر */
const currentYear  = document.getElementById('currentYear');
if (currentYear) currentYear.textContent = new Date().getFullYear();

/*==============================================================
 | addTap → يجعل الزر يستجيب للمس/قلم/فأرة
 *=============================================================*/
function addTap(el, handler) {
  if (!el) return;
  el.addEventListener('pointerup', e => { e.preventDefault(); handler(e); }, { passive:false });
  el.addEventListener('click', handler); // fallback
}

/*==============================================================
 | نقطة الدخول (يُستدعى بعد حقن HTML من admin_router.js)
 *=============================================================*/
export function init() {
  // 1) الإحصاءات + نماذج الحفظ
  loadCounts();
  bindYearForm();
  bindSemesterForm();
  bindLinkForm();

  // 2) القوائم الجانبيّة وكروت الأعوام
  refreshYearsList();
  bindYearCards();

  // 3) تحميل مبدئي للفصول والجامعات
  const yearSelect     = document.getElementById('academicYear');      // للفصول
  const yearLinkSelect = document.getElementById('academicYearLink');  // للجامعات
  if (yearSelect?.value)     refreshSemestersList(yearSelect.value);
  if (yearLinkSelect?.value) refreshUniversitiesList(yearLinkSelect.value);

  // 4) Event Delegation: change لكل select
  document.addEventListener('change', e => {
    const el = e.target;
    if (el.id === 'academicYear')     refreshSemestersList(el.value);
    if (el.id === 'academicYearLink') refreshUniversitiesList(el.value);
  });
}

/*==============================================================
 | 1) بطاقات الإحصاء
 *=============================================================*/
async function loadCounts() {
  try {
    const res = await fetch(`${API_BASE}/summary`, { headers:{Accept:'application/json'} });
    if (!res.ok) throw new Error('فشل جلب الإحصاءات');
    const d = await res.json();

    // يدعم التصميمين:
    const yearsEl =
      document.getElementById('yearsCount') ||
      document.querySelector('.mu-stats .mu-card:nth-child(1) .mu-value') ||
      document.querySelector('.dashboard-card:nth-child(1) .count');

    const semsEl =
      document.getElementById('semestersCount') ||
      document.querySelector('.mu-stats .mu-card:nth-child(2) .mu-value') ||
      document.querySelector('.dashboard-card:nth-child(2) .count');

    const unisEl =
      document.getElementById('universitiesCount') ||
      document.querySelector('.mu-stats .mu-card:nth-child(3) .mu-value') ||
      document.querySelector('.dashboard-card:nth-child(3) .count');

    if (yearsEl) yearsEl.textContent = d.years;
    if (semsEl)  semsEl.textContent  = d.semesters;
    if (unisEl)  unisEl.textContent  = d.universities;

  } catch (e) {
    console.error(e);
  }
}

/*==============================================================
 | 2) نموذج إضافة عام دراسي
 *=============================================================*/
function bindYearForm() {
  const yearNameEl = document.getElementById('yearName');
  const btn        = yearNameEl?.closest('.panel-section, .upg-card')?.querySelector('button.btn, button.btn-primary');
  if (!btn) return;

  addTap(btn, async () => {
    const payload = {
      label      : yearNameEl.value.trim(),
      start_date : document.getElementById('startDate').value,
      end_date   : document.getElementById('endDate').value,
    };
    if (!payload.label || !payload.start_date || !payload.end_date) {
      showWarning('يرجى ملء جميع الحقول');
      return;
    }

    btn.disabled = true;
    try {
      const res = await fetch(`${API_BASE}/store`, {
        method :'POST',
        headers:{'Content-Type':'application/json', Accept:'application/json'},
        body   : JSON.stringify(payload),
      });

      if (res.ok) {
        const created = await res.json();
        // أضف للسلتين
        const opt = new Option(created.label, created.id);
        document.getElementById('academicYear')    ?.appendChild(opt.cloneNode(true));
        document.getElementById('academicYearLink')?.appendChild(opt);

        refreshYearsList();
        loadCounts();

        yearNameEl.value = '';
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value   = '';

        reloadCurrentSection();
        showSuccess('تم إنشاء العام الدراسي بنجاح', undefined, true);

      } else { await handleError(res); }
    } catch(e) {
      console.error(e);
      showError('تعذّر الاتصال بالخادم');
    } finally { btn.disabled = false; }
  });
}

/*==============================================================
 | 3) قائمة الأعوام + حذف عام
 *=============================================================*/
async function refreshYearsList() {
  const ul = document.getElementById('yearsList');
  if (!ul) return;
  ul.innerHTML = '';

  document.querySelectorAll('#academicYear option[value]').forEach(opt => {
    if (!String(opt.value).trim()) return; // تخطّي الفارغ
    ul.insertAdjacentHTML(
      'beforeend',
      `<li><span>${opt.textContent}</span>
         <button data-id="${opt.value}" class="link-danger"><i class="fas fa-trash"></i></button>
       </li>`
    );
  });

  ul.querySelectorAll('button[data-id]').forEach(btn => {
    addTap(btn, () => deleteYear(btn.dataset.id));
  });
}

async function deleteYear(id) {
  if (!id) return;
  await showConfirm({
    title: 'حذف عام؟',
    text: 'سيُحذف العام مع فصوله وربطه بالجامعات.',
    confirmText: 'نعم، احذف',
    cancelText: 'لا',
    onConfirm: async () => {
      const res = await fetch(`${API_BASE}/delete/${id}`, { method:'DELETE' });
      if (res.ok) {
        document.querySelectorAll(`select option[value="${id}"]`).forEach(o => o.remove());
        refreshYearsList();
        loadCounts();
        reloadCurrentSection();
        showSuccess('حُذف العام بنجاح', undefined, true);
      } else {
        await handleError(res);
      }
    }
  });
}

/*==============================================================
 | 4) نموذج إضافة فصل
 *=============================================================*/
function bindSemesterForm() {
  const btn = document.querySelector('#semesterName')?.closest('.panel-section, .upg-card')?.querySelector('button.btn, button.btn-primary');
  if (!btn) return;

  addTap(btn, async () => {
    const payload = {
      academic_year_id : document.getElementById('academicYear').value,
      name             : document.getElementById('semesterName').value.trim(),
    };
    if (!payload.academic_year_id || !payload.name) {
      showWarning('املأ البيانات كاملة');
      return;
    }

    btn.disabled = true;
    try {
      const res = await fetch(`${API_BASE}/semesters/store`, {
        method :'POST',
        headers:{'Content-Type':'application/json', Accept:'application/json'},
        body   : JSON.stringify(payload),
      });

      if (res.ok) {
        loadCounts();
        refreshSemestersList(payload.academic_year_id);
        document.getElementById('semesterName').value = '';
        showSuccess('تم إنشاء الفصل بنجاح', undefined , true);
      } else { await handleError(res); }
    } finally { btn.disabled = false; }
  });
}

/*==============================================================
 | 5) قائمة الفصول + حذف فصل
 *=============================================================*/
async function refreshSemestersList(yearId) {
  const ul = document.getElementById('semestersList');
  if (!ul) return;
  if (!yearId) { ul.innerHTML = ''; return; }

  const res = await fetch(`${API_BASE}/show/${yearId}`, { headers:{Accept:'application/json'} });
  if (!res.ok) { ul.innerHTML = ''; return; }

  const { semesters = [] } = await res.json();
  ul.innerHTML = '';
  semesters.forEach(s => {
    ul.insertAdjacentHTML(
      'beforeend',
      `<li><span>${s.name}</span>
         <button data-id="${s.id}" class="link-danger"><i class="fas fa-trash"></i></button>
       </li>`
    );
  });
  ul.querySelectorAll('button[data-id]').forEach(btn => {
    addTap(btn, () => deleteSemester(btn.dataset.id, yearId));
  });
}

async function deleteSemester(id, yearId) {
  await showConfirm({
    title: 'حذف فصل؟',
    text: '',
    confirmText: 'نعم، احذف',
    cancelText: 'إلغاء',
    onConfirm: async () => {
      const res = await fetch(`${API_BASE}/semesters/delete/${id}`, { method:'DELETE' });
      if (res.ok) {
        refreshSemestersList(yearId);
        loadCounts();
        reloadCurrentSection();
        showSuccess('حُذف الفصل', undefined, true);
      } else {
        await handleError(res);
      }
    }
  });
}

/*==============================================================
 | 6) قائمة الجامعات + حذف جامعة
 *=============================================================*/
async function refreshUniversitiesList(yearId) {
  const ul = document.getElementById('universitiesList');
  const universitiesContainer = document.getElementById('universities');
  if (!ul || !universitiesContainer) return;
  if (!yearId) { ul.innerHTML = ''; return; }

  const res = await fetch(`${API_BASE}/show/${yearId}`, { headers:{ Accept: 'application/json' } });
  if (!res.ok) { ul.innerHTML = ''; return; }

  const { universities = [] } = await res.json();

  // ❶ عرض الجامعات المرتبطة
  ul.innerHTML = '';
  universities.forEach(u => {
    ul.insertAdjacentHTML(
      'beforeend',
      `<li><span>${u.name}</span>
         <button data-id="${u.id}" class="link-danger"><i class="fas fa-trash"></i></button>
       </li>`
    );
  });

  // ❷ ربط زر الحذف
  ul.querySelectorAll('button[data-id]').forEach(btn => {
    addTap(btn, () => deleteUniversity(yearId, btn.dataset.id));
  });

  // ❸ تمييز Checkboxes المرتبطة
  const linkedIds = universities.map(u => String(u.id));
  universitiesContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => {
    const uniId = cb.id.replace('uni', '');
    cb.checked = linkedIds.includes(uniId);
  });
}

async function deleteUniversity(yearId, uniId) {
  await showConfirm({
    title: 'إلغاء ربط الجامعة؟',
    text: 'سيتم حذف الربط مع هذه الجامعة من العام الدراسي المحدد',
    confirmText: 'نعم، أزل الربط',
    cancelText: 'إلغاء',
    onConfirm: async () => {
      const res = await fetch(`${API_BASE}/link-universities/${yearId}/${uniId}`, { method: 'DELETE' });
      if (res.ok) {
        refreshUniversitiesList(yearId);
        reloadCurrentSection();
        showSuccess('أُزيل ربط الجامعة', undefined, true);
      } else {
        await handleError(res);
      }
    }
  });
}

/*==============================================================
 | 7) ربط الجامعات بالعام
 *=============================================================*/
function bindLinkForm() {
  const btn = document.querySelector('#universities')?.closest('.panel-section, .upg-card')?.querySelector('button.btn, button.btn-primary');
  if (!btn) return;

  addTap(btn, async () => {
    const yearId = document.getElementById('academicYearLink').value;
    const uniIds = Array.from(document.querySelectorAll('#universities input:checked'))
                        .map(cb => cb.id.replace('uni',''));

    if (!yearId || !uniIds.length) {
      showWarning('اختر عامًا وجامعة واحدة على الأقل');
      return;
    }

    btn.disabled = true;
    try {
      const res = await fetch(`${API_BASE}/link-universities/${yearId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ university_ids: uniIds }),
      });

      if (res.ok) {
        refreshUniversitiesList(yearId);
        document.querySelectorAll('#universities input:checked').forEach(cb => cb.checked = false);
        showSuccess('تم ربط الجامعات الجديدة بنجاح', undefined, true);

      } else if (res.status === 409) {
        const { duplicates = [] } = await res.json();
        showWarning(`الجامعات (${duplicates.join(', ')}) مرتبطة بالفعل.`);
      } else {
        await handleError(res);
      }
    } catch (e) {
      console.error(e);
      showError('تعذّر الاتصال بالخادم');
    } finally {
      btn.disabled = false;
    }
  });
}

/*==============================================================
 | 8) كروت الأعوام (تعديل/حذف) — Event Delegation عام
 *=============================================================*/
function bindYearCards() {
  // نلتقط الأحداث على document ليدعم كلا التخطيطين
  document.addEventListener('click', e => {
    const delBtn  = e.target.closest('.delete-btn');
    const editBtn = e.target.closest('.edit-btn');

    if (delBtn) {
      const id = delBtn.dataset.id;
      deleteYear(id).then(() => delBtn.closest('.year-card')?.remove());
    }

    if (editBtn) {
      const id    = editBtn.dataset.id;
      const card  = editBtn.closest('.year-card');
      if (!card) return;

      const titleEl = card.querySelector('.year-title');
      const label   = titleEl ? titleEl.textContent.trim() : '';

      const perEl   = card.querySelector('.year-period');
      const dates   = perEl ? (perEl.textContent.match(/(\d{2}\/\d{2}\/\d{4})/g) || []) : [];

      openEditYearModal({ id, label, start:dates[0], end:dates[1] });
    }
  });
}

async function openEditYearModal({ id, label, start, end }) {
  const { value: formValues, isConfirmed } = await Swal.fire({
    title: 'تعديل العام الدراسي',
    html: `
      <div dir="rtl" style="text-align:right">
        <div style="margin-bottom: 1em;">
          <label for="swalLabel" style="display:block; margin-bottom: 0.4em;">اسم العام الدراسي</label>
          <input id="swalLabel" class="swal2-input" placeholder="مثال: 2024-2025" value="${label || ''}">
        </div>
        <div style="margin-bottom: 1em;">
          <label for="swalStart" style="display:block; margin-bottom: 0.4em;">تاريخ البداية</label>
          <input id="swalStart" type="date" class="swal2-input" value="${formatISO(start || '')}">
        </div>
        <div style="margin-bottom: 1em;">
          <label for="swalEnd" style="display:block; margin-bottom: 0.4em;">تاريخ النهاية</label>
          <input id="swalEnd" type="date" class="swal2-input" value="${formatISO(end || '')}">
        </div>
      </div>
    `,
    focusConfirm: false,
    showCancelButton: true,
    cancelButtonText: 'إلغاء',
    confirmButtonText: 'حفظ',
    preConfirm: () => ({
      label: document.getElementById('swalLabel').value.trim(),
      start_date: document.getElementById('swalStart').value,
      end_date: document.getElementById('swalEnd').value,
    })
  });

  if (!isConfirmed) return;

  const res = await fetch(`${API_BASE}/update/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(formValues),
  });

  if (res.ok) {
    showSuccess('تم تحديث بيانات العام', undefined , true);
    reloadCurrentSection();
  } else {
    await handleError(res);
  }
}

function formatISO(dmy) {
  if (!dmy) return '';
  const [d,m,y] = dmy.split('/');
  if (!y || !m || !d) return '';
  return `${y}-${m}-${d}`;
}

/*==============================================================
 | 9) معالجة الأخطاء الموحَّدة
 *=============================================================*/
async function handleError(res) {
  let msg = `HTTP ${res.status}`;
  try {
    const e = await res.json();
    msg = Object.values(e.errors || e.messages || e).join('\n');
  } catch(_) { /* ignore */ }
  showError(msg);
}

/* حوارات عامة - استخدام الدوال الموحدة من alerts.js */
// showError, showWarning, showSuccess متاحة من alerts.js
