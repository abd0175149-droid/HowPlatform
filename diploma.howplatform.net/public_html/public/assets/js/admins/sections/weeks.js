/* sections/weeks.js */
/* global Swal */
'use strict';

/* =================== Config =================== */
const API_BASE = '/admins/api/weeks';
const LS_KEY = 'admins:weeks:filters';
// حافظ على نفس الصيغة المستخدمة سابقًا في الاستدعاءات (?v=...)
const VERSION = 'v=2.6.0';

/* =================== Helpers =================== */
const $id = (x) => document.getElementById(x);
const qs = (s, root = document) => root.querySelector(s);
const qsa = (s, root = document) => Array.from(root.querySelectorAll(s));
const esc = (s = '') => String(s)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;')
  .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');

/* Inline styles removed; now using admin_style.css */

/* Fetch (مع تسجيل الخطأ) */
async function xfetchJSON(url, opts = {}) {
  const res = await fetch(url, {
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest', ...(opts.headers || {}) },
    cache: 'no-store',
    ...opts
  });
  if (!res.ok) {
    const txt = await res.text().catch(() => '');
    console.error('[weeks] fetch error:', url, res.status, txt.slice(0, 300));
    const e = new Error(`HTTP ${res.status}`); e.responseText = txt; throw e;
  }
  return res.json();
}

/* Swal */
function swSuccessHTML({ title = 'تم', html = '', confirmText = 'تم' }) {
  if (typeof Swal === 'undefined') { alert((title + '\n' + html).replace(/<[^>]*>/g, '')); return Promise.resolve(); }
  return Swal.fire({ icon: 'success', title, html, confirmButtonText: confirmText, showCloseButton: true, allowOutsideClick: false });
}
// استخدام showWarning, showError من alerts.js
const swWarn = showWarning;
const swError = showError;
async function swConfirm({ title = 'هل أنت متأكد؟', text = 'لن تتمكن من التراجع!', confirmText = 'تأكيد', cancelText = 'إلغاء' }) {
  if (typeof Swal === 'undefined') return confirm(text);
  const r = await Swal.fire({ icon: 'warning', title, text, showCancelButton: true, confirmButtonText: confirmText, cancelButtonText: cancelText, reverseButtons: true });
  return !!r.isConfirmed;
}

/* =================== State & Cache =================== */
let state = {
  page: 1,
  last: 1,
  limit: 10,
  sort: { field: 'index', dir: 'asc' }, // index | course | tasks
  rows: [],
  cache: {
    universitiesByYear: new Map(),           // yearId -> [{id,name}]
    coursesByYUS: new Map()                  // `${y}-${u}-${s}` -> [{course_id,name}]
  }
};

/* =================== Filters persistence =================== */
function saveFilters() {
  const y = $id('academicYear')?.value || '';
  const u = $id('university')?.value || '';
  const s = $id('semester')?.value || '';
  const c = $id('course')?.value || '';
  localStorage.setItem(LS_KEY, JSON.stringify({ y, u, s, c }));
}
function loadSavedFilters() {
  try { return JSON.parse(localStorage.getItem(LS_KEY) || '') || null; }
  catch { return null; }
}

/* =================== Header counter =================== */
// يدعم كلا الهيكلين: .ap-head__actions أو .weeks-container .header-actions
function ensureWeeksCounter() {
  let el = $id('weeksCount');
  if (el) return el;
  const actions = qs('#weeksPage .ap-head__actions') || qs('.weeks-container .header-actions');
  if (!actions) return null;
  el = document.createElement('span');
  el.id = 'weeksCount';
  el.className = 'ap-counter';
  el.textContent = '—';
  actions.prepend(el);
  return el;
}
function setWeeksCount(n) {
  const el = ensureWeeksCounter();
  if (el) el.textContent = Number.isFinite(n) ? `عدد الأسابيع: ${n}` : '—';
}

/* =================== Table utils =================== */
function renderEmptyTable(msg = 'لا توجد بيانات') {
  const tb = $id('weeksTableBody');
  const inf = $id('paginationInfo');
  if (tb) tb.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#6b7280;padding:18px">${esc(msg)}</td></tr>`;
  if (inf) inf.textContent = 'صفحة 1 من 1';
  setWeeksCount(0);
  setPagerDisabled(true, true, true, true);
}
function setPagerDisabled(f, p, n, l) {
  const map = { firstPageBtn: f, prevPageBtn: p, nextPageBtn: n, lastPageBtn: l };
  Object.entries(map).forEach(([id, dis]) => {
    const btn = $id(id); if (!btn) return;
    btn.classList.toggle('disabled', !!dis);
    btn.toggleAttribute('disabled', !!dis);
  });
}
function installSortingControls() {
  const headRow = qs('.weeks-table thead tr'); if (!headRow) return;
  const thIndex = headRow.children[0];
  const thCourse = headRow.children[1];
  const thTasks = headRow.children[3];
  const mk = (th, field, label) => { if (th) th.innerHTML = `<button type="button" class="sort-btn" data-field="${field}">${label} <i class="fas fa-sort"></i></button>`; };
  mk(thIndex, 'index', '#'); mk(thCourse, 'course', 'المساق'); mk(thTasks, 'tasks', 'عدد المهام');

  // تفعيل الأيقونة الافتراضية
  const setIcon = () => {
    headRow.querySelectorAll('.sort-btn i').forEach(i => i.className = 'fas fa-sort');
    const activeBtn = headRow.querySelector(`.sort-btn[data-field="${state.sort.field}"] i`);
    if (activeBtn) activeBtn.className = (state.sort.dir === 'asc') ? 'fas fa-sort-up' : 'fas fa-sort-down';
  };
  setIcon();

  headRow.addEventListener('click', (e) => {
    const btn = e.target.closest('.sort-btn'); if (!btn) return;
    const field = btn.dataset.field;
    state.sort.dir = (state.sort.field === field && state.sort.dir === 'asc') ? 'desc' : 'asc';
    state.sort.field = field;
    renderRowsFromState();
    setIcon();
  });
}
function renderRowsFromState() {
  const tb = $id('weeksTableBody'); if (!tb) return;
  const data = state.rows.slice();
  const { field, dir } = state.sort;
  if (field === 'index') { if (dir === 'desc') data.reverse(); }
  else if (field === 'course') {
    data.sort((a, b) => (a.course_name || '').localeCompare(b.course_name || '')); if (dir === 'desc') data.reverse();
  } else if (field === 'tasks') {
    data.sort((a, b) => (parseInt(a.tasks_count || 0, 10) - parseInt(b.tasks_count || 0, 10))); if (dir === 'desc') data.reverse();
  }
  const offset = (state.page - 1) * state.limit;
  tb.innerHTML = data.map((w, i) => `
    <tr>
      <td><span class="week-number">${offset + i + 1}</span></td>
      <td>${esc(w.course_name || '—')}</td>
      <td>${esc(w.title || '')}</td>
      <td><span class="tasks-badge"><i class="fas fa-tasks"></i> ${Number(w.tasks_count || 0)}</span></td>
      <td>
        <div class="action-buttons-we">
          <button class="action-btn-we edit-btn-we"   data-id="${w.id}" title="تعديل"><i class="fas fa-edit"></i></button>
          <button class="action-btn-we delete-btn-we" data-id="${w.id}" title="حذف"><i class="fas fa-trash"></i></button>
        </div>
      </td>
    </tr>
  `).join('');
}

/* =================== API loaders =================== */
async function loadMetaYears() {
  const d = await xfetchJSON(`${API_BASE}/meta?${VERSION}`);
  const sel = $id('academicYear'); if (!sel) return d;
  sel.innerHTML = '<option value="">اختر العام الدراسي</option>';
  (d.academic_years || []).forEach(y => sel.innerHTML += `<option value="${y.id}">${esc(y.label)}</option>`);
  return d;
}
async function loadUniversities(yearId) {
  const uniSel = $id('university');
  const cache = state.cache.universitiesByYear.get(yearId);
  if (cache) { fill(cache); return cache; }
  const d = await xfetchJSON(`${API_BASE}/universities/${encodeURIComponent(yearId)}?${VERSION}`);
  const list = d.universities || [];
  state.cache.universitiesByYear.set(yearId, list);
  fill(list); return list;
  function fill(list) { if (!uniSel) return; uniSel.disabled = false; uniSel.innerHTML = '<option value="">اختر الجامعة</option>'; list.forEach(u => uniSel.innerHTML += `<option value="${u.id}">${esc(u.name)}</option>`); }
}
async function loadSemesters(yearId) {
  const semSel = $id('semester');
  const d = await xfetchJSON(`${API_BASE}/semesters/${encodeURIComponent(yearId)}?${VERSION}`);
  semSel.disabled = false; semSel.innerHTML = '<option value="">اختر الفصل الدراسي</option>';
  (d.semesters || []).forEach(s => semSel.innerHTML += `<option value="${s.id}">${esc(s.name)}</option>`);
  return d.semesters || [];
}
async function loadCoursesFor(y, u, s) {
  const sel = $id('course'); const key = `${y}-${u}-${s}`;
  const cache = state.cache.coursesByYUS.get(key);
  if (cache) { fill(cache); return cache; }
  const url = `${API_BASE}/courses?academic_year_id=${encodeURIComponent(y)}&university_id=${encodeURIComponent(u)}&semester_id=${encodeURIComponent(s)}&${VERSION}`;
  const d = await xfetchJSON(url); const list = d.courses || [];
  state.cache.coursesByYUS.set(key, list); fill(list); return list;
  function fill(list) { if (!sel) return; sel.disabled = false; sel.innerHTML = '<option value="">اختر المساق (اختياري)</option>'; list.forEach(c => sel.innerHTML += `<option value="${c.course_id}">${esc(c.name)}</option>`); }
}

/* =================== List weeks (course filter optional) =================== */
async function loadWeeks(page = 1) {
  state.page = page;
  const y = $id('academicYear')?.value; const u = $id('university')?.value; const s = $id('semester')?.value; const c = $id('course')?.value;
  if (!y || !u || !s) { renderEmptyTable('يرجى اختيار السنة والجامعة والفصل لعرض الأسابيع'); return; }
  const tb = $id('weeksTableBody'); if (tb) tb.innerHTML = `<tr><td colspan="5" style="text-align:center">... جاري التحميل</td></tr>`;

  const qsParams = new URLSearchParams({ academic_year_id: y, university_id: u, semester_id: s, page: String(page), limit: String(state.limit) });
  if (c) qsParams.set('course_id', c);
  // مرّر النسخة لكسر الكاش مثل باقي الاستدعاءات
  const url = `${API_BASE}/list?${qsParams.toString()}&${VERSION}`;

  const d = await xfetchJSON(url);
  if (!d?.success) { renderEmptyTable(d?.message || 'لا توجد أسابيع وفق الفلترة المختارة'); return; }

  setWeeksCount(Number(d.total || 0));

  const list = d.weeks || [];
  if (!list.length) { renderEmptyTable('لا توجد أسابيع وفق الفلترة المختارة'); return; }

  state.last = Number(d.last_page || 1);
  state.page = Number(d.current_page || 1);
  state.limit = Number(d.limit || state.limit);
  state.rows = list.slice();

  const inf = $id('paginationInfo'); if (inf) inf.textContent = `صفحة ${state.page} من ${state.last}`;
  setPagerDisabled(state.page === 1, state.page === 1, state.page === state.last, state.page === state.last);
  renderRowsFromState();
}

/* =================== Modal helpers =================== */
function getModalOverlay() { return $id('weekModalOverlay'); }
function toggleModal(show) {
  const ov = getModalOverlay(); if (!ov) return;
  if (show) { ov.classList.add('active'); ov.setAttribute('aria-hidden', 'false'); }
  else { ov.classList.remove('active'); ov.setAttribute('aria-hidden', 'true'); }
}
function setModalTitle(text) {
  const span = $id('weekModalTitle');
  if (span) { span.textContent = text; return; }
  const h2Span = qs('#weekModalOverlay .modal-header h2 span'); if (h2Span) { h2Span.textContent = text; }
}

/* =================== Assignments UI (use existing HTML) =================== */
function getAssignmentsContainer() { return $id('assignmentsContainer'); }
function getAssignmentsCard() { return $id('assignmentsCard'); }
function bindAssignmentButtonsOnce() {
  const addBtn = $id('addAssignRowBtn');
  if (addBtn && !addBtn.__bound) {
    addBtn.__bound = true;
    addBtn.addEventListener('click', () => addAssignRow());
  }
  const cont = getAssignmentsContainer();
  if (cont && !cont.__rmBound) {
    cont.__rmBound = true;
    cont.addEventListener('click', (e) => {
      const rm = e.target.closest('.remove-assign');
      if (rm) rm.closest('.ap-assign-row')?.remove();
    });
    cont.addEventListener('change', async (e) => {
      const row = e.target.closest('.ap-assign-row'); if (!row) return;
      if (e.target.matches('.uni-select')) {
        const y = $id('academicYear')?.value; const s = $id('semester')?.value; const u = e.target.value;
        const courseSel = row.querySelector('.course-multi'); if (!courseSel) return;
        courseSel.innerHTML = '<option value="">جاري التحميل...</option>'; courseSel.disabled = true;
        if (y && s && u) {
          const list = await loadCoursesFor(y, u, s);
          courseSel.innerHTML = '';
          list.forEach(c => courseSel.innerHTML += `<option value="${c.course_id}">${esc(c.name)}</option>`);
          courseSel.disabled = false;
        } else {
          courseSel.innerHTML = '<option value="">—</option>'; courseSel.disabled = false;
        }
      }
    });
  }
}

async function addAssignRow(defaultUniId = '', defaultCourseIds = []) {
  const container = getAssignmentsContainer();
  if (!container) { console.warn('[weeks] assignmentsContainer not found'); return false; }

  const row = document.createElement('div');
  row.className = 'ap-row ap-assign-row';
  row.innerHTML = `
    <div class="ap-field">
      <label class="ap-label">الجامعة</label>
      <div class="ap-input-wrap">
        <i class="fas fa-university"></i>
        <select class="ap-input uni-select">
          <option value="">اختر الجامعة</option>
        </select>
      </div>
    </div>
    <div class="ap-field">
      <label class="ap-label">المساقات (يمكن متعدد)</label>
      <div class="ap-input-wrap">
        <i class="fas fa-book"></i>
        <select class="ap-input course-multi" multiple size="5" disabled>
          <option value="">—</option>
        </select>
      </div>
    </div>
    <div class="ap-field" style="align-self:end;">
      <button type="button" class="btn btn-ghost remove-assign"><i class="fas fa-trash"></i> إزالة</button>
    </div>
  `;
  container.appendChild(row);

  // املأ الجامعات من الكاش/الـAPI
  const uniSel = row.querySelector('.uni-select');
  const crsSel = row.querySelector('.course-multi');
  const y = $id('academicYear')?.value || '';
  const s = $id('semester')?.value || '';

  let uniList = [];
  if (y) {
    uniList = state.cache.universitiesByYear.get(y) || [];
    if (!uniList.length) {
      try { uniList = await loadUniversities(y); }
      catch (e) { console.error('[weeks] loadUniversities failed', e); uniList = []; }
    }
  }
  uniSel.innerHTML = '<option value="">اختر الجامعة</option>';
  uniList.forEach(u => uniSel.innerHTML += `<option value="${u.id}">${esc(u.name)}</option>`);

  // افتراضي من الفلترة/التمرير
  if (defaultUniId) {
    uniSel.value = String(defaultUniId);
    crsSel.innerHTML = '<option value="">جاري التحميل...</option>'; crsSel.disabled = true;
    if (y && s) {
      try {
        const list = await loadCoursesFor(y, defaultUniId, s);
        crsSel.innerHTML = '';
        list.forEach(c => crsSel.innerHTML += `<option value="${c.course_id}">${esc(c.name)}</option>`);
        crsSel.disabled = false;
        // اختيار مساقات افتراضية (مصوفة)
        if (Array.isArray(defaultCourseIds) && defaultCourseIds.length) {
          const set = new Set(defaultCourseIds.map(String));
          qsa('option', crsSel).forEach(op => { if (set.has(op.value)) op.selected = true; });
        }
      } catch (e) {
        console.error('[weeks] loadCoursesFor failed', e);
        crsSel.innerHTML = '<option value="">—</option>'; crsSel.disabled = false;
      }
    } else {
      crsSel.innerHTML = '<option value="">—</option>'; crsSel.disabled = false;
    }
  } else {
    crsSel.innerHTML = '<option value="">—</option>'; crsSel.disabled = false;
  }

  return true;
}

/* =================== Modal open/edit/save/delete =================== */
async function openCreateModal() {
  const ySel = $id('academicYear'); const sSel = $id('semester');
  if (!ySel?.value || !sSel?.value) return swWarn('اختر العام والفصل أولًا.');

  setModalTitle('إضافة أسبوع جديد');
  const card = getAssignmentsCard(); if (card) card.style.display = ''; // أظهر بطاقة التعيينات
  const fixed = $id('editFixedMeta'); if (fixed) fixed.style.display = 'none';

  // الثوابت (اعمل .value بدل setAttribute)
  if ($id('modalAcademicYear')) $id('modalAcademicYear').value = ySel.options[ySel.selectedIndex]?.text || '';
  if ($id('modalSemester')) $id('modalSemester').value = sSel.options[sSel.selectedIndex]?.text || '';

  // الحقول
  $id('weekNumber') && ($id('weekNumber').value = '');
  $id('weekTitle') && ($id('weekTitle').value = '');
  $id('publishDate') && ($id('publishDate').value = '');

  // تهيئة منطقة التعيينات
  bindAssignmentButtonsOnce();
  const cont = getAssignmentsContainer(); if (cont) cont.innerHTML = '';
  const defUni = $id('university')?.value || '';
  const defCrs = $id('course')?.value ? [$id('course').value] : [];
  await addAssignRow(defUni, defCrs);

  const saveBtn = $id('saveWeekBtn'); if (saveBtn) { saveBtn.dataset.mode = 'create'; saveBtn.removeAttribute('data-week-id'); }

  toggleModal(true);
}

async function openEditModal(id) {
  try {
    const d = await xfetchJSON(`${API_BASE}/show/${id}?${VERSION}`);
    if (!d?.success) throw new Error('bad show');

    setModalTitle('تعديل أسبوع');
    const card = getAssignmentsCard(); if (card) card.style.display = 'none';  // إخفِ التعيينات
    const fixed = $id('editFixedMeta'); if (fixed) fixed.style.display = '';    // أظهر البيانات الثابتة

    // عرض فقط الجامعة/المساق للمراجعة
    $id('editUniName') && ($id('editUniName').textContent = d.week.university || '—');
    $id('editCourseName') && ($id('editCourseName').textContent = d.week.course || '—');

    // تعبئة باقي الحقول
    if ($id('modalAcademicYear')) $id('modalAcademicYear').value = d.week.academic_year || '';
    if ($id('modalSemester')) $id('modalSemester').value = d.week.semester || '';
    $id('weekNumber') && ($id('weekNumber').value = d.week.week_number || '');
    $id('weekTitle') && ($id('weekTitle').value = d.week.title || '');
    $id('publishDate') && ($id('publishDate').value = d.week.publish_date || '');

    const saveBtn = $id('saveWeekBtn'); if (saveBtn) { saveBtn.dataset.mode = 'edit'; saveBtn.dataset.weekId = String(id); }
    toggleModal(true);
  } catch (e) {
    console.error(e); swError('تعذّر تحميل بيانات الأسبوع.');
  }
}

async function saveWeek() {
  const saveBtn = $id('saveWeekBtn'); const mode = saveBtn?.dataset.mode || 'create';
  const y = $id('academicYear')?.value; const s = $id('semester')?.value;
  const week_number = Number($id('weekNumber')?.value || 0);
  const title = ($id('weekTitle')?.value || '').trim();
  const publish_date = $id('publishDate')?.value || '';
  if (!y || !s) return swWarn('اختر العام والفصل أولًا.');
  if (!week_number || !title || !publish_date) return swWarn('يرجى تعبئة رقم الأسبوع والعنوان وتاريخ النشر.');

  const old = saveBtn?.innerHTML; if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جارٍ الحفظ...'; }

  try {
    if (mode === 'edit') {
      const id = saveBtn?.dataset.weekId;
      const res = await xfetchJSON(`${API_BASE}/update/${encodeURIComponent(id)}`, {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ week_number, title, publish_date })
      });
      toggleModal(false);
      await swSuccessHTML({ title: 'تم التحديث', html: esc(res.message || 'تم تحديث الأسبوع') });
      await loadWeeks(state.page);
      return;
    }

    // create (متعدد الجامعات/المساقات)
    const targets = [];
    let badRows = 0;

    qsa('#assignmentsContainer .ap-assign-row').forEach(row => {
      const uniId = row.querySelector('.uni-select')?.value || '';
      const selectedCourses = qsa('.course-multi option:checked', row).map(op => op.value).filter(Boolean);

      if (uniId && selectedCourses.length) {
        selectedCourses.forEach(cid => targets.push({ university_id: Number(uniId), course_id: Number(cid) }));
      } else if (uniId && !selectedCourses.length) {
        badRows++;
      }
    });

    const cFilter = $id('course')?.value || '';

    // تحقّق صارم لتفادي 400
    if (!targets.length && !cFilter) {
      if (badRows > 0) return swWarn('اختر مساقًا واحدًا على الأقل لكل جامعة قمت بإضافتها.');
      return swWarn('أضف سطرًا واحدًا (جامعة + مساقات) أو اختر مساقًا من الفلترة.');
    }

    console.group('%c[weeks:save:create]', 'color:#156683');
    console.log('week_number:', week_number, 'title:', title, 'publish_date:', publish_date);
    console.log('targets count:', targets.length, 'filter course:', cFilter);

    // حالة استخدام مساق الفلترة فقط
    if (!targets.length && cFilter) {
      const payload = { course_id: Number(cFilter), week_number, title, publish_date };
      console.log('POST → /store payload:', payload);
      const res = await xfetchJSON(`${API_BASE}/store`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      console.groupEnd();
      toggleModal(false);
      await swSuccessHTML({ title: 'تم الحفظ', html: esc(res.message || 'تمت إضافة الأسبوع') });
      await loadWeeks(1);
      return;
    }

    // نفّذ لكل هدف (مساق)
    const results = [];
    for (const t of targets) {
      const payload = { course_id: t.course_id, week_number, title, publish_date };
      console.log('POST → /store payload:', payload);
      try {
        const r = await xfetchJSON(`${API_BASE}/store`, {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        results.push({ ok: true, t, r });
      } catch (e) {
        results.push({ ok: false, t, e });
      }
    }
    console.groupEnd();

    // أغلق المودال قبل التنبيه
    toggleModal(false);

    // أسماء مفهومة في الملخّص
    const yText = $id('modalAcademicYear')?.value || '';
    const sText = $id('modalSemester')?.value || '';
    const uniNameBy = (u) => {
      const list = state.cache.universitiesByYear.get(y) || [];
      return (list.find(x => String(x.id) === String(u))?.name) || `جامعة #${u}`;
    };
    const key = (u) => `${y}-${u}-${s}`;
    const courseNameBy = (u, c) => {
      const list = state.cache.coursesByYUS.get(key(u)) || [];
      return (list.find(x => String(x.course_id) === String(c))?.name) || `مساق #${c}`;
    };

    const okLis = results.filter(x => x.ok).map(({ t }) => `<li><b>${esc(uniNameBy(t.university_id))}</b> — ${esc(courseNameBy(t.university_id, t.course_id))}</li>`).join('');
    const badLis = results.filter(x => !x.ok).map(({ t }) => `<li><b>${esc(uniNameBy(t.university_id))}</b> — مساق #${esc(String(t.course_id))}</li>`).join('');
    let html = `<div style="text-align:right"><div style="margin-bottom:6px"><b>السنة:</b> ${esc(yText)} &nbsp; <b>الفصل:</b> ${esc(sText)}</div>`;
    if (okLis) html += `<div style="margin:6px 0">تمت الإضافة إلى:</div><ul style="margin:0 0 6px; padding-inline-start:18px">${okLis}</ul>`;
    if (badLis) html += `<div style="margin:6px 0">فشلت الإضافة إلى:</div><ul style="margin:0; padding-inline-start:18px">${badLis}</ul>`;
    html += `</div>`;
    await swSuccessHTML({ title: 'نتيجة الحفظ', html });
    await loadWeeks(1);

  } catch (e) {
    console.error(e); await swError('حدث خطأ أثناء الحفظ.');
  } finally {
    if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = old; }
  }
}

async function deleteWeek(id) {
  const ok = await swConfirm({ title: 'هل أنت متأكد؟', text: 'سيتم حذف الأسبوع نهائيًا.', confirmText: 'نعم، احذف', cancelText: 'إلغاء' });
  if (!ok) return;
  try {
    const r = await xfetchJSON(`${API_BASE}/delete/${encodeURIComponent(id)}`, { method: 'DELETE' });
    await swSuccessHTML({ title: 'تم', html: esc(r.message || 'تم حذف الأسبوع') });
    await loadWeeks(state.page);
  } catch (e) {
    console.error(e); swError('فشل حذف الأسبوع.');
  }
}

/* =================== Public init =================== */
export async function init() {
  console.group('%c[weeks:init]', 'color:#156683;font-weight:bold');

  // سنة ©
  const cy = $id('currentYear'); if (cy) cy.textContent = new Date().getFullYear();

  // فرز الجدول
  installSortingControls();

  // تحميل الأعوام
  try { await loadMetaYears(); } catch { renderEmptyTable('تعذّر تحميل الأعوام.'); console.groupEnd(); return; }

  // عناصر DOM
  const yearSel = $id('academicYear');
  const uniSel = $id('university');
  const semSel = $id('semester');
  const courseSel = $id('course');
  const tblBody = $id('weeksTableBody');

  // استعادة فلاتر
  const saved = loadSavedFilters();
  if (saved?.y) {
    yearSel.value = saved.y;
    await Promise.all([loadUniversities(saved.y), loadSemesters(saved.y)]);
    if (saved.u) uniSel.value = saved.u;
    if (saved.s) semSel.value = saved.s;
    if (saved.u && saved.s) {
      await loadCoursesFor(saved.y, saved.u, saved.s);
      if (saved.c) courseSel.value = saved.c;
    }
    await loadWeeks(1);
  } else {
    renderEmptyTable('يرجى اختيار السنة والجامعة والفصل لعرض الأسابيع');
  }

  /* Events */
  if (yearSel) yearSel.addEventListener('change', async () => {
    const y = yearSel.value;
    if (!y) { localStorage.removeItem(LS_KEY); renderEmptyTable('يرجى اختيار السنة'); return; }
    if (uniSel) { uniSel.disabled = true; uniSel.innerHTML = '<option value="">جاري التحميل...</option>'; }
    if (semSel) { semSel.disabled = true; semSel.innerHTML = '<option value="">جاري التحميل...</option>'; }
    if (courseSel) { courseSel.innerHTML = '<option value="">اختر المساق (اختياري)</option>'; courseSel.disabled = true; }
    await Promise.all([loadUniversities(y), loadSemesters(y)]);
    saveFilters();
    renderEmptyTable('اختر الجامعة والفصل لعرض الأسابيع');
  });

  if (uniSel) uniSel.addEventListener('change', async () => {
    const y = yearSel.value, u = uniSel.value, s = semSel.value;
    if (y && u && s) { await loadCoursesFor(y, u, s); await loadWeeks(1); }
    else renderEmptyTable('اختر جميع الفلاتر');
    saveFilters();
  });

  if (semSel) semSel.addEventListener('change', async () => {
    const y = yearSel.value, u = uniSel.value, s = semSel.value;
    if (y && u && s) { await loadCoursesFor(y, u, s); await loadWeeks(1); }
    else renderEmptyTable('اختر جميع الفلاتر');
    saveFilters();
  });

  if (courseSel) courseSel.addEventListener('change', async () => { saveFilters(); await loadWeeks(1); });

  // تفويض الجدول
  if (tblBody) tblBody.addEventListener('click', (e) => {
    const edit = e.target.closest('.edit-btn-we'); const del = e.target.closest('.delete-btn-we');
    if (edit) openEditModal(edit.dataset.id);
    else if (del) deleteWeek(del.dataset.id);
  });

  // مودال
  $id('addWeekBtn')?.addEventListener('click', openCreateModal);
  $id('closeModalBtn')?.addEventListener('click', () => toggleModal(false));
  $id('cancelModalBtn')?.addEventListener('click', () => toggleModal(false));
  $id('saveWeekBtn')?.addEventListener('click', saveWeek);

  // Pager
  $id('firstPageBtn')?.addEventListener('click', () => loadWeeks(1));
  $id('prevPageBtn')?.addEventListener('click', () => state.page > 1 && loadWeeks(state.page - 1));
  $id('nextPageBtn')?.addEventListener('click', () => state.page < state.last && loadWeeks(state.page + 1));
  $id('lastPageBtn')?.addEventListener('click', () => loadWeeks(state.last));

  // داعم لزر إعادة التعيين إن وُجد
  $id('resetFiltersBtn')?.addEventListener('click', () => window.resetFiltersForWeeks?.());

  console.groupEnd();
}

/* =============== Global helpers (Reset Filters) =============== */
// دالة عامة تماثل زر "إعادة تعيين الفلتر" في الـ HTML
window.resetFiltersForWeeks = function resetFiltersForWeeks() {
  try {
    localStorage.removeItem(LS_KEY);
    // تفريغ الكاش في الذاكرة
    state.cache.universitiesByYear.clear();
    state.cache.coursesByYUS.clear();

    // إعادة تعيين الحقول
    const yearSel = $id('academicYear');
    const uniSel = $id('university');
    const semSel = $id('semester');
    const courseSel = $id('course');

    if (yearSel) yearSel.value = '';
    if (uniSel) { uniSel.disabled = true; uniSel.innerHTML = '<option value="">اختر الجامعة</option>'; }
    if (semSel) { semSel.disabled = true; semSel.innerHTML = '<option value="">اختر الفصل الدراسي</option>'; }
    if (courseSel) { courseSel.disabled = true; courseSel.innerHTML = '<option value="">اختر المساق (اختياري)</option>'; }

    renderEmptyTable('يرجى اختيار السنة والجامعة والفصل لعرض الأسابيع');
  } catch (e) {
    console.error('[weeks] resetFiltersForWeeks failed', e);
  }
};
