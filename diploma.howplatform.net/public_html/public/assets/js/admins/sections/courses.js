/* sections/courses.js */
/* global Swal, showWarning, showError, showSuccess, showConfirm */

'use strict';

/* =========================================
 * Config
 * ========================================= */
const API_BASE   = '/admins/api/courses';
const CACHE_KEY  = 'admins:courses:filters';   // {year, uni, sem}
const COOKIE_KEY = 'admins_courses_filters';   // fallback cookie name

/* =========================================
 * Shortcuts & Utils
 * ========================================= */
const $id = (x) => document.getElementById(x);
const $qs = (s, r = document) => r.querySelector(s);

function escapeHtml(str = '') {
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/* Inject once: small CSS fixes (contrast in dark mode etc.) */
function injectStylesOnce() {
  if (document.getElementById('courses-css-injected')) return;
  const css = `
    /* تباين أوضح لرمز المساق في الوضع الليلي */
    html[data-theme="dark"] .course-code{
      background: rgba(33,158,188,.25) !important;
      border-color: rgba(33,158,188,.55) !important;
      color: #e6f1f6 !important;
    }
    /* حقول القراءة فقط داخل المودال */
    .ap-modal__body .ap-field.readonly input[disabled]{
      background:#f8fafc; color:#111827; opacity:1; border:1px solid var(--border-color,#e6e6e6);
    }
    html[data-theme="dark"] .ap-modal__body .ap-field.readonly input[disabled]{
      background:#0f172a; color:#e5e7eb; border-color:#1f2a44;
    }
    /* تنسيق خاص بنافذة Swal */
    .swal2-popup.ap-swal{ direction: rtl; text-align: right; border-radius: 16px; padding: 18px; }
    .ap-swal__intro{ margin: 0 0 10px; font-weight: 800; }
    .ap-swal__list{ margin:0; padding:0 1.2rem; list-style: disc; }
    .ap-swal__list li{ margin: 4px 0; }
    .ap-swal__list .u{ font-weight: 800; }
    .ap-swal__confirm{
      border-radius: 12px !important;
      padding: 8px 16px !important;
      font-weight: 800 !important;
      background: var(--primary-500) !important;
      border: none !important;
    }
    .ap-swal__confirm:hover{ background: var(--primary-600) !important; }
    /* دارك مود للنافذة */
    html[data-theme="dark"] .swal2-popup.ap-swal{
      background: #0b1220; color: #e5e7eb;
    }
  `.trim();
  const el = document.createElement('style');
  el.id = 'courses-css-injected';
  el.textContent = css;
  document.head.appendChild(el);
}

/* =========================================
 * CSRF helpers (CodeIgniter 4)
 * ========================================= */
function readCookie(name) {
  const m = document.cookie.split('; ').find(r => r.startsWith(name + '='));
  return m ? decodeURIComponent(m.split('=').slice(1).join('=')) : '';
}
function setCookie(name, value, days = 180, path = '/') {
  const exp = new Date(Date.now() + days*864e5).toUTCString();
  document.cookie = `${name}=${encodeURIComponent(value)}; expires=${exp}; path=${path}`;
}
function getMeta(name, fallback = '') {
  const el = document.querySelector(`meta[name="${name}"]`);
  return el ? el.getAttribute('content') : fallback;
}
function getCsrfPair() {
  const headerName = getMeta('csrf-header', 'X-CSRF-TOKEN');
  let token = getMeta('csrf-token', '');
  if (!token) {
    const cookieName = getMeta('csrf-cookie', '') || (document.cookie.split('; ')
      .map(c => c.split('=')[0]).find(n => /csrf|xsrf/i.test(n)) || '');
    if (cookieName) token = readCookie(cookieName);
  }
  return { headerName, token };
}

/* Unified fetch with CSRF & JSON + basic error bubbling */
async function xfetch(url, opts = {}) {
  const method = (opts.method || 'GET').toUpperCase();
  const isWrite = !['GET', 'HEAD', 'OPTIONS'].includes(method);

  const headers = {
    'X-Requested-With': 'XMLHttpRequest',
    ...(opts.headers || {})
  };

  if (isWrite) {
    const { headerName, token } = getCsrfPair();
    if (token) headers[headerName] = token;
  }

  const merged = {
    method,
    credentials: 'same-origin',
    headers,
    body: opts.body
  };

  const res  = await fetch(url, merged);
  const text = await res.text();
  const type = res.headers.get('Content-Type') || '';

  const parse = () => {
    try { return JSON.parse(text); } catch { return text; }
  };

  if (!res.ok) {
    const payload = parse();
    const msg = (payload && payload.message) ? payload.message : (text || `HTTP ${res.status}`);
    const err = new Error(msg);
    err.status = res.status;
    err.payload = payload;
    throw err;
  }
  return type.includes('application/json') ? JSON.parse(text) : parse();
}

/* =========================================
 * SweetAlert helpers (منسّقة ولا تختفي تلقائيًا)
 * ========================================= */
function successDialogHtml(title, html) {
  if (typeof Swal === 'undefined') {
    alert((title ? title + '\n\n' : '') + html.replace(/<[^>]+>/g, ''));
    return;
  }
  Swal.fire({
    title: title || 'تم بنجاح',
    html,
    icon: 'success',
    showConfirmButton: true,
    confirmButtonText: 'تم',
    customClass: { popup: 'ap-swal', confirmButton: 'ap-swal__confirm' },
    allowOutsideClick: false,
    allowEscapeKey: false,
    showCloseButton: false,
    didOpen: () => {
      // تركيز على زر "تم"
      const btn = document.querySelector('.ap-swal__confirm');
      btn && btn.focus();
    }
  });
}
function successDialogText(title, text) {
  successDialogHtml(title, `<div class="ap-swal__intro">${escapeHtml(text || '')}</div>`);
}

/* =========================================
 * UI helpers
 * ========================================= */
function setTableMessage(html) {
  const tb = $id('coursesTableBody');
  if (!tb) return;
  tb.innerHTML = `<tr><td class="upg-empty" colspan="6">${html}</td></tr>`;
}
function setCoursesCount(n) {
  const el = $id('coursesCount');
  if (el) el.textContent = Number.isFinite(n) ? `${n} مساق` : '—';
}
function toggleModal(show) {
  const modal = $id('courseModalOverlay');
  if (!modal) return;
  if (show) {
    modal.classList.add('active');
    modal.removeAttribute('hidden');
    modal.setAttribute('aria-hidden', 'false');
  } else {
    modal.classList.remove('active');
    modal.setAttribute('hidden', 'hidden');
    modal.setAttribute('aria-hidden', 'true');
  }
}
function ensureEditUniversityRow() {
  // Create once if missing (shows in edit only)
  if ($id('modalUniversitySingleRow')) return;
  const body   = $qs('.ap-modal__body');
  const before = body?.querySelector('.ap-row'); // after year/semester row
  if (!body || !before) return;

  const wrapper = document.createElement('div');
  wrapper.className = 'ap-field readonly';
  wrapper.id = 'modalUniversitySingleRow';
  wrapper.innerHTML = `
    <label class="ap-label" for="modalUniversitySingle">الجامعة</label>
    <input type="text" id="modalUniversitySingle" class="ap-input" disabled>
  `;
  before.insertAdjacentElement('afterend', wrapper);
}
function showEditUniversityRow(show) {
  const row = $id('modalUniversitySingleRow');
  if (!row) return;
  row.style.display = show ? '' : 'none';
}

/* sync labels in modal (year/semester) from filters */
function syncFiltersToModal() {
  const ySel = $id('academicYear');
  const sSel = $id('semester');

  const yearText = ySel?.options[ySel.selectedIndex]?.text || '';
  const semText  = sSel?.options[sSel.selectedIndex]?.text || '';

  const y = $id('modalAcademicYear');
  const s = $id('modalSemester');
  if (y) y.value = yearText;
  if (s) s.value = semText;
}
function clearModalInputs() {
  const name = $id('courseName');
  const code = $id('courseCode');
  const desc = $id('courseDescription');
  if (name) name.value = '';
  if (code) code.value = '';
  if (desc) desc.value = '';
}

/* =========================================
 * Filters cache (LocalStorage + cookie fallback)
 * ========================================= */
function saveFiltersCache() {
  const y = $id('academicYear')?.value || '';
  const u = $id('university')?.value || '';
  const s = $id('semester')?.value || '';
  const j = JSON.stringify({ year: y, uni: u, sem: s });
  localStorage.setItem(CACHE_KEY, j);
  setCookie(COOKIE_KEY, j, 180, '/'); // fallback for other tabs or contexts
}
function loadFiltersCache() {
  try {
    const j1 = localStorage.getItem(CACHE_KEY);
    if (j1) return JSON.parse(j1);
  } catch { /* ignore */ }
  try {
    const j2 = readCookie(COOKIE_KEY);
    if (j2) return JSON.parse(j2);
  } catch { /* ignore */ }
  return { year: '', uni: '', sem: '' };
}
function resetFiltersCache() {
  localStorage.removeItem(CACHE_KEY);
  setCookie(COOKIE_KEY, '', -1, '/');
}

/* =========================================
 * Populate selects
 * ========================================= */
async function populateYears() {
  const yearSel = $id('academicYear');
  if (!yearSel) return;
  yearSel.innerHTML = '<option value="">اختر العام الدراسي</option>';
  try {
    const d = await xfetch(`${API_BASE}/meta`);
    (d.academic_years || []).forEach(y => {
      yearSel.insertAdjacentHTML('beforeend', `<option value="${y.id}">${escapeHtml(y.label)}</option>`);
    });
  } catch {
    yearSel.innerHTML = '<option value="">تعذّر تحميل الأعوام</option>';
  }
}
async function populateUniversities(yearId) {
  const uniSel = $id('university');
  if (!uniSel) return;
  uniSel.disabled = true;
  uniSel.innerHTML = '<option value="">جاري التحميل...</option>';
  try {
    const d = await xfetch(`${API_BASE}/universities/${encodeURIComponent(yearId)}`);
    uniSel.disabled = false;
    uniSel.innerHTML = '<option value="">اختر الجامعة</option>';
    (d.universities || []).forEach(u => {
      uniSel.insertAdjacentHTML('beforeend', `<option value="${u.id}">${escapeHtml(u.name)}</option>`);
    });
  } catch {
    uniSel.disabled = false;
    uniSel.innerHTML = '<option value="">تعذّر تحميل الجامعات</option>';
  }
}
async function populateSemesters(yearId) {
  const semSel = $id('semester');
  if (!semSel) return;
  semSel.disabled = true;
  semSel.innerHTML = '<option value="">جاري التحميل...</option>';
  try {
    const d = await xfetch(`${API_BASE}/semesters/${encodeURIComponent(yearId)}`);
    semSel.disabled = false;
    semSel.innerHTML = '<option value="">اختر الفصل الدراسي</option>';
    (d.semesters || []).forEach(s => {
      semSel.insertAdjacentHTML('beforeend', `<option value="${s.id}">${escapeHtml(s.name)}</option>`);
    });
  } catch {
    semSel.disabled = false;
    semSel.innerHTML = '<option value="">تعذّر تحميل الفصول</option>';
  }
}

/* =========================================
 * Universities chips inside modal (ADD mode)
 * ========================================= */
function buildUniversitiesChipsFromFilter(preselectIds = []) {
  const list = $id('modalUniversitiesList');
  const uniSel = $id('university');
  if (!list || !uniSel) return;

  list.innerHTML = '';

  const options = Array.from(uniSel.options).filter(o => o.value);
  const selectedFromFilter = uniSel.value;

  options.forEach((opt) => {
    const id   = String(opt.value);
    const name = opt.text;
    const checked = preselectIds.length
      ? preselectIds.includes(id)
      : (selectedFromFilter === id); // افتراضيًا حدّد الجامعة المختارة بالفلترة

    const inputId = `uni-${id}`;
    list.insertAdjacentHTML('beforeend', `
      <input type="checkbox" class="specialty-checkbox" id="${inputId}" value="${id}" ${checked ? 'checked' : ''}>
      <label class="specialty-label" for="${inputId}">
        <i class="fas fa-university"></i> ${escapeHtml(name)}
      </label>
    `);
  });

  updateUniSelectedCount();
  list.onchange = updateUniSelectedCount;
}
function updateUniSelectedCount() {
  const wrap = $id('modalUniversitiesList');
  const cntEl = $id('uniSelectedCount');
  if (!wrap || !cntEl) return;
  const count = wrap.querySelectorAll('input[type="checkbox"]:checked').length;
  cntEl.textContent = count ? `${count} جامعة مختارة` : 'لا شيء مختار';
}
function getSelectedUniversityIdsFromModal() {
  const wrap = $id('modalUniversitiesList');
  if (!wrap) return [];
  return Array.from(wrap.querySelectorAll('input[type="checkbox"]:checked')).map(ch => ch.value);
}

/* =========================================
 * Fetch & render courses
 * ========================================= */
async function fetchAndRenderCourses() {
  const y = $id('academicYear')?.value;
  const u = $id('university')?.value;
  const s = $id('semester')?.value;

  if (!y || !u || !s) {
    setTableMessage('يرجى اختيار السنة والجامعة والفصل لعرض المساقات');
    setCoursesCount(0);
    return;
  }

  setTableMessage('... جاري التحميل');
  try {
    const data = await xfetch(`${API_BASE}/filter?academic_year_id=${encodeURIComponent(y)}&university_id=${encodeURIComponent(u)}&semester_id=${encodeURIComponent(s)}`);
    renderCourses(data);
    saveFiltersCache(); // keep updated
  } catch {
    setTableMessage('حدث خطأ أثناء التحميل');
    setCoursesCount(0);
  }
}
function renderCourses(data) {
  const tb = $id('coursesTableBody');
  if (!tb) return;

  if (!data?.success || !(data.courses || []).length) {
    setTableMessage('لا توجد مساقات متاحة');
    setCoursesCount(0);
    return;
  }

  const rows = data.courses.map((c, i) => `
    <tr>
      <td>${i + 1}</td>
      <td>${escapeHtml(c.name)}</td>
      <td><span class="course-code">${escapeHtml(c.code)}</span></td>
      <td>${escapeHtml(c.university_name)}</td>
      <td>${Number(c.weeks_count) || 0}</td>
      <td>
        <div class="action-buttons-co">
          <button class="action-btn-co edit-btn" data-id="${c.id}" title="تعديل المساق"><i class="fas fa-edit"></i></button>
          <button class="action-btn-co delete-btn" data-course-id="${c.id}" title="حذف المساق"><i class="fas fa-trash"></i></button>
        </div>
      </td>
    </tr>
  `).join('');

  tb.innerHTML = rows;
  setCoursesCount(data.courses.length);
}

/* =========================================
 * Modal: open/create/edit/save/delete
 * ========================================= */
function openCreateModal() {
  // يجب اختيار (سنة + فصل) قبل الإضافة
  const y = $id('academicYear')?.value;
  const s = $id('semester')?.value;
  if (!y || !s) {
    showWarning && showWarning('يرجى اختيار "العام الدراسي" و"الفصل الدراسي" أولًا.');
    return;
  }

  const dlg = $qs('.ap-modal');
  dlg?.classList.remove('edit-mode');   // إظهار اختيار الجامعات
  showEditUniversityRow(false);         // إخفاء سطر الجامعة المفردة إن وُجد

  const titleSpan = $id('apModalTitle');
  if (titleSpan) titleSpan.textContent = 'إضافة مساق جديد';

  const saveBtn = $id('saveCourseBtn');
  saveBtn.dataset.mode = 'create';
  saveBtn.removeAttribute('data-course-id');

  clearModalInputs();
  syncFiltersToModal();
  buildUniversitiesChipsFromFilter();   // توليد الشرائح من قائمة الجامعات الحالية بالفلترة

  toggleModal(true);
}

async function openEditModal(id) {
  ensureEditUniversityRow();            // أنشئ صف الجامعة إن لم يكن موجودًا
  const dlg = $qs('.ap-modal');
  dlg?.classList.add('edit-mode');      // إخفاء اختيار الجامعات
  showEditUniversityRow(true);          // إظهار سطر الجامعة المفردة

  const titleSpan = $id('apModalTitle');
  if (titleSpan) titleSpan.textContent = 'تعديل المساق';

  const saveBtn = $id('saveCourseBtn');
  saveBtn.dataset.mode = 'edit';
  saveBtn.dataset.courseId = id;

  clearModalInputs();

  try {
    const d = await xfetch(`${API_BASE}/show/${id}`);
    if (!d?.success) throw new Error();

    const c = d.course;
    $id('courseName').value        = c.name || '';
    $id('courseCode').value        = c.code || '';
    $id('courseDescription').value = c.description || '';

    // عرض السنة/الفصل + الجامعة الخاصة بهذا المساق
    const yText = c.academic_year_label || ($id('academicYear')?.options[$id('academicYear').selectedIndex]?.text || '');
    const sText = c.semester_name       || ($id('semester')?.options[$id('semester').selectedIndex]?.text || '');
    $id('modalAcademicYear').value = yText;
    $id('modalSemester').value     = sText;

    const uniSingle = $id('modalUniversitySingle');
    if (uniSingle) uniSingle.value = c.university_name || ($id('university')?.options[$id('university').selectedIndex]?.text || '');

    toggleModal(true);
  } catch {
    showError && showError('تعذّر جلب بيانات المساق');
  }
}

async function saveCourse() {
  const saveBtn = $id('saveCourseBtn');
  const mode = saveBtn.dataset.mode;

  const name = $id('courseName').value.trim();
  const code = $id('courseCode').value.trim();
  const desc = $id('courseDescription').value.trim();

  const year = $id('academicYear').value;
  const uni  = $id('university').value;   // يُستخدم للتحديث
  const sem  = $id('semester').value;

  if (!name || !code || !year || !sem) {
    showWarning && showWarning('يرجى تعبئة الحقول المطلوبة (الاسم، الرمز، السنة، الفصل).');
    return;
  }

  saveBtn.disabled = true;
  const oldHTML = saveBtn.innerHTML;
  saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جارٍ الحفظ...';

  try {
    if (mode === 'create') {
      const uniIds = getSelectedUniversityIdsFromModal();
      if (!uniIds.length) {
        showWarning && showWarning('يرجى اختيار جامعة واحدة على الأقل.');
        saveBtn.disabled = false;
        saveBtn.innerHTML = oldHTML;
        return;
      }

      const body = JSON.stringify({
        name, code, description: desc,
        academic_year_id: year,
        semester_id:      sem,
        university_ids:   uniIds  // المنطق الجديد: عدة جامعات
      });

      const res = await xfetch(`${API_BASE}/store`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body
      });

      if (res?.success) {
        // إشعار منظَّم وواضح، دون هروب HTML
        const uniSel   = $id('university');
        const yearText = $id('academicYear')?.options[$id('academicYear').selectedIndex]?.text || '';
        const semText  = $id('semester')?.options[$id('semester').selectedIndex]?.text || '';
        const names = uniIds.map(id => {
          const opt = Array.from(uniSel.options).find(o => o.value === id);
          return opt ? opt.text : `ID ${id}`;
        });

        const listItems = names
          .map(n => `<li><span class="u">${escapeHtml(n)}</span> <span class="sep">—</span> ${escapeHtml(yearText)} <span class="sep">—</span> ${escapeHtml(semText)}</li>`)
          .join('');

        const html = `
          <div class="ap-swal__intro">
            تم إضافة المساق <b>${escapeHtml(name)}</b> بنجاح إلى:
          </div>
          <ul class="ap-swal__list">${listItems}</ul>
        `;

        successDialogHtml('إضافة مساق', html);

        toggleModal(false);
        await fetchAndRenderCourses();
      } else {
        throw new Error(res?.message || 'حدث خطأ أثناء الحفظ.');
      }

    } else {
      // edit: تحديث بيانات المساق وربطه بالجامعة الحالية من الفلترة
      if (!uni) {
        showWarning && showWarning('يرجى اختيار جامعة من الفلتر قبل التعديل.');
        saveBtn.disabled = false;
        saveBtn.innerHTML = oldHTML;
        return;
      }

      const id = saveBtn.dataset.courseId;
      const body = JSON.stringify({
        name, code, description: desc,
        academic_year_id: year,
        semester_id:      sem,
        university_id:    uni
      });

      const res = await xfetch(`${API_BASE}/update/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body
      });

      if (res?.success) {
        const yearText = $id('academicYear')?.options[$id('academicYear').selectedIndex]?.text || '';
        const semText  = $id('semester')?.options[$id('semester').selectedIndex]?.text || '';
        const uniText  = $id('university')?.options[$id('university').selectedIndex]?.text || '';

        const html = `
          <div class="ap-swal__intro">تم تحديث بيانات المساق <b>${escapeHtml(name)}</b>.</div>
          <ul class="ap-swal__list">
            <li><b>الجامعة:</b> <span class="u">${escapeHtml(uniText)}</span></li>
            <li><b>السنة:</b> ${escapeHtml(yearText)}</li>
            <li><b>الفصل:</b> ${escapeHtml(semText)}</li>
          </ul>
        `;
        successDialogHtml('تعديل مساق', html);

        toggleModal(false);
        await fetchAndRenderCourses();
      } else {
        throw new Error(res?.message || 'حدث خطأ أثناء التحديث.');
      }
    }
  } catch (e) {
    showError && showError(e?.message || 'تعذّر الاتصال بالخادم.');
  } finally {
    saveBtn.disabled = false;
    saveBtn.innerHTML = oldHTML;
  }
}

function deleteCourse(id) {
  showConfirm({
    title: 'هل أنت متأكد؟',
    text: 'سيتم حذف المساق بشكل نهائي!',
    confirmText: 'نعم، احذفه',
    cancelText: 'إلغاء',
    onConfirm: async () => {
      try {
        const d = await xfetch(`${API_BASE}/delete/${id}`, { method: 'DELETE' });
        if (d?.success) {
          // نُبقيه بسيطًا هنا
          successDialogText('حذف مساق', d.message || 'تم حذف المساق بنجاح.');
          await fetchAndRenderCourses();
        } else {
          showError && showError(d?.message || 'فشل في حذف المساق.');
        }
      } catch {
        showError && showError('فشل الاتصال بالخادم.');
      }
    }
  });
}

/* =========================================
 * Filters logic (events & restore)
 * ========================================= */
async function handleYearChange({ restoreFromCache = false } = {}) {
  const yearId = $id('academicYear').value;
  const uniSel = $id('university');
  const semSel = $id('semester');

  if (!yearId) {
    if (uniSel) { uniSel.innerHTML = '<option value="">اختر الجامعة</option>'; uniSel.disabled = true; }
    if (semSel) { semSel.innerHTML = '<option value="">اختر الفصل الدراسي</option>'; semSel.disabled = true; }
    setTableMessage('يرجى اختيار السنة والجامعة والفصل لعرض المساقات');
    setCoursesCount(0);
    resetFiltersCache();
    return;
  }

  await Promise.all([ populateUniversities(yearId), populateSemesters(yearId) ]);

  if (restoreFromCache) {
    const cache = loadFiltersCache();
    if (cache.uni && Array.from(uniSel.options).some(o => o.value === cache.uni)) uniSel.value = cache.uni;
    if (cache.sem && Array.from(semSel.options).some(o => o.value === cache.sem)) semSel.value = cache.sem;
    if (uniSel.value && semSel.value) {
      await fetchAndRenderCourses();
      return;
    }
  }

  setTableMessage('اختر الجامعة والفصل لعرض المساقات');
  setCoursesCount(0);
}
function resetFilters() {
  const yearSel = $id('academicYear');
  const uniSel  = $id('university');
  const semSel  = $id('semester');

  if (yearSel) yearSel.selectedIndex = 0;
  if (uniSel)  { uniSel.innerHTML = '<option value="">اختر الجامعة</option>'; uniSel.disabled = true; }
  if (semSel)  { semSel.innerHTML = '<option value="">اختر الفصل الدراسي</option>'; semSel.disabled = true; }

  setTableMessage('يرجى اختيار السنة والجامعة والفصل لعرض المساقات');
  setCoursesCount(0);
  resetFiltersCache();
}
window.resetFilters = resetFilters;

/* =========================================
 * Event delegation for table action buttons
 * ========================================= */
function setupTableDelegation() {
  const tblBody = $id('coursesTableBody');
  if (!tblBody) return;
  tblBody.addEventListener('click', (e) => {
    const editBtn = e.target.closest('.edit-btn');
    const delBtn  = e.target.closest('.delete-btn');
    if (editBtn) {
      e.preventDefault();
      openEditModal(editBtn.dataset.id);
    } else if (delBtn) {
      e.preventDefault();
      deleteCourse(delBtn.dataset.courseId);
    }
  });
}

/* =========================================
 * INIT
 * ========================================= */
export async function init() {
  injectStylesOnce();

  // Guard: required elements must exist
  const required = ['academicYear', 'semester', 'coursesTableBody'];
  const missing = required.filter(id => !$id(id));
  if (missing.length) {
    console.warn('[courses] Required elements missing');
    console.warn('missing:', missing.map(x => `#${x}`).join(', '));
    const ca = $id('contentArea');
    if (ca) {
      const prev = ca.innerHTML.slice(0, 400).replace(/\s+/g, ' ').trim();
      console.log('contentArea HTML preview:', prev);
    }
    return;
  }

  // Buttons & modal actions
  $id('addCourseBtn')?.addEventListener('click', openCreateModal);
  $id('closeModalBtn')?.addEventListener('click', () => toggleModal(false));
  $id('cancelModalBtn')?.addEventListener('click', () => toggleModal(false));
  $id('saveCourseBtn')?.addEventListener('click', saveCourse);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') toggleModal(false); });

  setupTableDelegation();

  // Populate lists
  await populateYears();

  // Restore from cache (works for dynamic navigation & full reload)
  const cache = loadFiltersCache();
  if (cache.year && Array.from($id('academicYear').options).some(o => o.value === cache.year)) {
    $id('academicYear').value = cache.year;
    await handleYearChange({ restoreFromCache: true }); // will set uni/sem and fetch table
  } else {
    setTableMessage('يرجى اختيار السنة والجامعة والفصل لعرض المساقات');
    setCoursesCount(0);
  }

  // Wire change events (after initial restore)
  $id('academicYear').addEventListener('change', async () => {
    await handleYearChange({ restoreFromCache: false });
    saveFiltersCache();
  });
  $id('university').addEventListener('change', () => { saveFiltersCache(); fetchAndRenderCourses(); });
  $id('semester').addEventListener('change',  () => { saveFiltersCache(); fetchAndRenderCourses(); });
}
