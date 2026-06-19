// =================== SweetAlert helpers (Toast + Dialog) ===================
const swalToast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 2600,
  timerProgressBar: true,
  showCloseButton: true,
  customClass: {
    popup: 'swal-toast',
    title: 'swal-title',
    container: 'swal-container',
  }
});

/** بديل alert() يحافظ على نفس الاسم toast لعدم كسر النداءات */
function toast(message, type = 'info') {
  return swalToast.fire({
    icon: type, // 'success' | 'error' | 'warning' | 'info' | 'question'
    title: String(message ?? '')
  });
}

const notify = {
  success: (m)=> toast(m,'success'),
  error  : (m)=> toast(m,'error'),
  warn   : (m)=> toast(m,'warning'),
  info   : (m)=> toast(m,'info'),
  confirm: async ({title='هل أنت متأكد؟', text='', icon='question', confirmText='نعم', cancelText='إلغاء'} = {})=>{
    const res = await Swal.fire({
      title, text, icon,
      showCancelButton: true,
      confirmButtonText: confirmText,
      cancelButtonText : cancelText,
      reverseButtons: true,
      focusCancel: true
    });
    return res.isConfirmed;
  },
  withLoading: async (fn, title='جارٍ التنفيذ…')=>{
    await Swal.fire({
      title, allowOutsideClick:false, allowEscapeKey:false, didOpen:()=>Swal.showLoading()
    });
    try { return await fn(); }
    finally { Swal.close(); }
  }
};

// =================== Helpers ===================
const $  = s => document.querySelector(s);
const $$ = s => Array.from(document.querySelectorAll(s));
const debounce = (fn, ms=260)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; };

// =================== DOM ===================
const weekSel       = $('#weekSelect');
const taskFilter    = $('#taskFilter');
const groupSel      = $('#groupSelect');

const trackingArea  = $('#trackingArea');
const headRow       = $('#trackingHead');
const bodyTable     = $('#trackingBody');

const pickAllBox    = $('#pickAllStudents');
const saveBtn       = $('#bulkSave');

const studentSearch = $('#studentSearch');
const gradeApplyInp = $('#gradeApplyValue');
const applyOnTime   = $('#applyOnTime');
const applyLate     = $('#applyLate');
const applyClear    = $('#applyClear');
const applyGradeBtn = $('#applyGrade');

// =================== Pagination State & DOM ===================
let PAGE_SIZE    = 10;
let CURRENT_PAGE = 1;
let SEARCH_Q     = '';

const pageSizeSel = document.getElementById('pageSizeSel');
const pgPrev      = document.getElementById('pgPrev');
const pgNext      = document.getElementById('pgNext');
const pgNums      = document.getElementById('pgNums');
const pageInfo    = document.getElementById('pageInfo');

// =================== Data State ===================
let TASKS = []; // [{id,title,task_url}]
let GRID  = []; // [{student_id,student_name,tasks:[{task_id,submission_status,grade}]}]
const DIRTY = new Set(); // `${sid}:${tid}`

// =================== Safe fetch ===================
async function fetchJson(url, options = {}) {
  const res = await fetch(url, {
    headers: { 'Accept': 'application/json', ...(options.headers||{}) },
    ...options
  });
  const text = await res.text();
  if (!res.ok) {
    let msg = text; try { msg = JSON.parse(text).message || msg; } catch(_){}
    console.error('HTTP '+res.status, text);
    notify.error(`خطأ ${res.status}: ${msg}`);
    return null;
  }
  try { return JSON.parse(text); }
  catch(e){ console.error('Parse error', e, text); notify.error('استجابة غير صالحة من الخادم.'); return null; }
}

// =================== Restore filters & page size, then auto-load ===================
(async function restoreAndAutoLoad(){
  const savedSize = parseInt(localStorage.getItem('pf_page_size') || '10', 10);
  if ([10,25,50].includes(savedSize)) PAGE_SIZE = savedSize;
  if (pageSizeSel) pageSizeSel.value = String(PAGE_SIZE);

  const w = localStorage.getItem('pf_week_id');
  const g = localStorage.getItem('pf_group_id');
  if (w && weekSel)  weekSel.value  = w;
  if (g && groupSel) groupSel.value = g;

  if (weekSel?.value) {
    await loadTasksForWeek(weekSel.value);
    await loadGrid();
  }
})();

// =================== Week→Tasks (and auto grid) ===================
weekSel?.addEventListener('change', async ()=>{
  const wid = weekSel.value;
  localStorage.setItem('pf_week_id', wid || '');
  if (!wid){
    if (taskFilter){
      taskFilter.innerHTML = `<option value="">— كل المهمات —</option>`;
      taskFilter.disabled = true;
    }
    headRow.innerHTML=''; bodyTable.innerHTML=''; trackingArea.hidden = true;
    return;
  }
  await loadTasksForWeek(wid);
  await loadGrid();
});

groupSel?.addEventListener('change', async ()=>{
  const gid = groupSel.value;
  localStorage.setItem('pf_group_id', gid || '');
  if (weekSel?.value) await loadGrid();
});

async function loadTasksForWeek(weekId){
  if (!taskFilter) return;
  taskFilter.disabled = true;
  taskFilter.innerHTML = `<option value="">… تحميل</option>`;
  const tasks = await fetchJson(`/api/performance/tasks?week_id=${encodeURIComponent(weekId)}`) || [];
  taskFilter.innerHTML = `<option value="">— كل المهمات —</option>` +
    tasks.map(t=> `<option value="${t.id}">${escapeHtml(t.title||('مهمة #'+t.id))}</option>`).join('');
  taskFilter.disabled = false;
}

async function loadGrid(){
  const weekId  = weekSel?.value || '';
  const groupId = groupSel?.value || '';
  if (!weekId){ notify.info('يرجى اختيار أسبوع.'); return; }

  let url = `/api/performance/tracking?week_id=${encodeURIComponent(weekId)}`;
  if (groupId) url += `&group_id=${encodeURIComponent(groupId)}`;
  const data = await fetchJson(url);
  if (!data) return;

  TASKS = Array.isArray(data.tasks) ? data.tasks : [];
  GRID  = Array.isArray(data.grid)  ? data.grid  : [];
  DIRTY.clear();

  buildTable();
  trackingArea.hidden = false;
}

// =================== Build table ===================
function buildTable(){
  headRow.innerHTML = '';
  bodyTable.innerHTML = '';

  // pick column
  const thPick = document.createElement('th');
  thPick.className = 'pick-cell';
  thPick.innerHTML = `<input type="checkbox" id="headPickAll" title="تحديد/إلغاء تحديد الطلبة">`;
  headRow.appendChild(thPick);

  // student name column
  const thName = document.createElement('th');
  thName.textContent = 'الطالب';
  headRow.appendChild(thName);

  // tasks to show (filtered or all)
  const selectedTaskId = taskFilter?.value || '';
  let displayed = [];
  if (selectedTaskId){
    const tid = +selectedTaskId;
    displayed = TASKS.filter(t=> t.id === tid);
  } else {
    displayed = [...TASKS].sort((a,b)=> a.id-b.id);
  }

  for (const t of displayed) {
    const th = document.createElement('th');
    th.dataset.task = String(t.id);
    th.innerHTML = `
      <div class="th-cell">
        <span>${escapeHtml(t.title || ('مهمة #' + t.id))}</span>
        ${t.task_url ? `<a href="${t.task_url}" target="_blank" title="فتح رابط المهمة"><i class="fa-solid fa-up-right-from-square"></i></a>` : ''}
      </div>`;
    headRow.appendChild(th);
  }

  for (const row of GRID) {
    const tr = document.createElement('tr');

    // pick
    const tdPick = document.createElement('td');
    tdPick.className = 'pick-cell';
    tdPick.innerHTML = `<input type="checkbox" class="row-pick" data-student="${row.student_id}">`;
    tr.appendChild(tdPick);

    // name
    const td0 = document.createElement('td');
    td0.className = 'student-name';
    td0.textContent = row.student_name ?? `#${row.student_id}`;
    tr.appendChild(td0);

    const mapById = new Map((Array.isArray(row.tasks) ? row.tasks : []).map(c => [c.task_id, c]));

    for (const t of displayed) {
      const src = mapById.get(t.id) ?? { task_id: t.id, submission_status: 'not_submitted', grade: null };

      const td   = document.createElement('td');
      const wrap = document.createElement('div');
      wrap.className = 'cell-flex';
      wrap.dataset.student = row.student_id;
      wrap.dataset.task    = src.task_id;

      // ✅ / 🕒 / ◯ buttons
      const btnDone = document.createElement('button');
      btnDone.className = 'pill done';
      btnDone.title = 'في الموعد';
      btnDone.innerHTML = `<i class="fa-solid fa-check"></i>`;

      const btnLate = document.createElement('button');
      btnLate.className = 'pill late';
      btnLate.title = 'متأخر';
      btnLate.innerHTML = `<i class="fa-solid fa-clock"></i>`;

      const btnGrade = document.createElement('button');
      btnGrade.className = 'pill grade';
      btnGrade.title = 'العلامة';
      btnGrade.innerHTML = `<span class="g-label">–</span>`;
      btnGrade.dataset.editing = '0';
      btnGrade.dataset.rawText = '';

      // events (status)
      btnDone.addEventListener('click', ()=>{
        const now = getStatusPills(wrap);
        const next = (now === 'on_time') ? 'not_submitted' : 'on_time';
        setStatusPills(wrap, next);
        markDirtyKey(wrap, true);
      });
      btnLate.addEventListener('click', ()=>{
        const now = getStatusPills(wrap);
        const next = (now === 'late') ? 'not_submitted' : 'late';
        setStatusPills(wrap, next);
        markDirtyKey(wrap, true);
      });

      // events (grade inline edit)
      btnGrade.addEventListener('click', ()=> beginEditGrade(btnGrade, wrap));

      wrap.appendChild(btnDone);
      wrap.appendChild(btnLate);
      wrap.appendChild(btnGrade);
      td.appendChild(wrap);
      tr.appendChild(td);

      // init status/grade
      const initGrade  = normalizeGradeValue(src.grade);
      const initStatus = normalizeStatus(src.submission_status ?? src.status, initGrade);
      setStatusPills(wrap, initStatus);
      setGradePill(btnGrade, initGrade);
    }

    bodyTable.appendChild(tr);
  }

  // head pick-all
  $('#headPickAll')?.addEventListener('change', (e)=>{
    const on = e.currentTarget.checked;
    $$('.row-pick').forEach(cb=> cb.checked = on && !cb.closest('tr')?.hidden);
    if (pickAllBox) pickAllBox.checked = on;
  });

  // reset to page 1 then show current page
  CURRENT_PAGE = 1;
  refreshPagination();
}

// =================== Inline grade editor (no numeric conversion) ===================
function beginEditGrade(btnGrade, wrap){
  if (btnGrade.dataset.editing === '1') return;
  btnGrade.dataset.editing = '1';

  const currentText = btnGrade.querySelector('.g-label')?.textContent?.trim() || '';
  const initVal = (currentText === '–') ? '' : currentText;

  btnGrade.innerHTML = '';
  const input = document.createElement('input');
  input.type = 'text';
  input.className = 'grade-editor';
  input.value = initVal;
  btnGrade.appendChild(input);
  input.focus(); input.select();

  const commit = ()=>{
    const raw = (input.value || '').trim();
    if (raw === '') setGradePill(btnGrade, null);
    else setGradePill(btnGrade, raw);
    end(true);
  };

  const cancel = ()=>{
    setGradePill(btnGrade, initVal || null);
    end(false);
  };

  function end(dirty){
    btnGrade.dataset.editing = '0';
    if (dirty) markDirtyKey(wrap, true);
  }

  input.addEventListener('keydown', e=>{
    if (e.key === 'Enter'){ e.preventDefault(); commit(); }
    else if (e.key === 'Escape'){ e.preventDefault(); cancel(); }
  });
  input.addEventListener('blur', commit);
}

// =================== Normalizers & helpers ===================
function normalizeStatus(raw, gradeText){
  if (raw === undefined || raw === null) raw = '';
  let s = String(raw).trim().toLowerCase();

  if (s === '1' || s === 'true' || s === 'yes') s = 'on_time';
  if (s === '0' || s === 'false' || s === 'no')  s = 'not_submitted';

  const arMap = {
    'في الموعد':'on_time','ضمن الموعد':'on_time','مسلم في الوقت':'on_time',
    'متأخر':'late','متاخر':'late','تاخر':'late',
    'غير مسلم':'not_submitted','غير مسلّم':'not_submitted','لم يسلم':'not_submitted','لا يوجد':'not_submitted','فارغ':'not_submitted'
  };
  if (arMap[s]) return arMap[s];

  const enMap = {
    'on_time':'on_time','ontime':'on_time','in_time':'on_time','submitted':'on_time','done':'on_time','complete':'on_time',
    'late':'late','late_submission':'late','delayed':'late','tardy':'late',
    'not_submitted':'not_submitted','missing':'not_submitted','none':'not_submitted','':'not_submitted'
  };
  if (enMap[s]) return enMap[s];

  // لو الحالة غير معروفة لكن هناك علامة نصية غير فارغة ⇒ اعتبرها on_time
  if ((!enMap[s]) && gradeText && String(gradeText).trim() !== '') return 'on_time';
  return 'not_submitted';
}

function normalizeGradeValue(raw){
  if (raw === null || raw === undefined) return null;
  const s = String(raw).trim();
  return s === '' ? null : s; // لا تحويل، تُعاد كما هي
}

function getStatusPills(wrap){
  const done = wrap.querySelector('.pill.done')?.classList.contains('active');
  const late = wrap.querySelector('.pill.late')?.classList.contains('active');
  if (done) return 'on_time';
  if (late) return 'late';
  return 'not_submitted';
}
function setStatusPills(wrap, status){
  const bDone = wrap.querySelector('.pill.done');
  const bLate = wrap.querySelector('.pill.late');
  if (!bDone || !bLate) return;
  bDone.classList.remove('active');
  bLate.classList.remove('active');
  if (status === 'on_time') bDone.classList.add('active');
  else if (status === 'late') bLate.classList.add('active');
}

function setGradePill(btnGrade, value){
  const label = normalizeGradeValue(value);
  btnGrade.innerHTML = '';
  const span = document.createElement('span');
  span.className = 'g-label';
  span.textContent = label ?? '–';
  btnGrade.appendChild(span);
  btnGrade.classList.toggle('has-grade', !!label);
  btnGrade.dataset.editing = '0';
  btnGrade.dataset.rawText = label ?? '';
}

function getGradePayload(btnGrade){
  const raw = (btnGrade.dataset.rawText || '').trim();
  return raw === '' ? null : raw; // دائماً نص كما كُتب
}

// =================== Filters & Search ===================
const applyFiltersDebounced = debounce(applyFilters, 260);
studentSearch?.addEventListener('input', applyFiltersDebounced);
taskFilter?.addEventListener('change', ()=> buildTable());

function applyFilters(){
  SEARCH_Q = (studentSearch?.value || '').trim().toLowerCase();
  CURRENT_PAGE = 1;
  refreshPagination();
}

// =================== Selection ===================
pickAllBox?.addEventListener('change', (e)=>{
  const on = e.currentTarget.checked;
  $$('.row-pick').forEach(cb=>{
    const row = cb.closest('tr');
    if (!row?.hidden) cb.checked = on;
  });
});

// =================== Apply scope helpers ===================
function getApplyScope(){ return ($('input[name="applyScope"]:checked')?.value || 'selected'); }
function targetStudents(){
  const scope = getApplyScope();
  if (scope === 'visible'){
    const ids = [];
    $$('#trackingBody tr').forEach(tr=>{
      if (!tr.hidden){
        const cb = tr.querySelector('.row-pick');
        if (cb) ids.push(parseInt(cb.dataset.student,10));
      }
    });
    return ids;
  } else {
    return $$('.row-pick:checked')
      .map(cb=> parseInt(cb.dataset.student,10))
      .filter(Boolean);
  }
}
function targetTasks(){
  const selectedTaskId = taskFilter?.value || '';
  if (selectedTaskId){ return [parseInt(selectedTaskId,10)]; }
  return TASKS.map(t=> t.id);
}

// =================== Apply actions ===================
applyOnTime?.addEventListener('click', ()=> applyStatusToTargets('on_time'));
applyLate?.addEventListener('click',   ()=> applyStatusToTargets('late'));
applyClear?.addEventListener('click',  ()=> applyStatusToTargets('not_submitted'));

applyGradeBtn?.addEventListener('click', ()=>{
  const raw = (gradeApplyInp?.value || '').trim();
  if (raw === ''){ notify.warn('أدخل قيمة علامة لتطبيقها.'); return; }
  const sids = targetStudents();
  const tids = targetTasks();
  if (!sids.length){ notify.info('اختر طلبة أو فعّل “تطبيق على الكل الظاهر”.'); return; }

  for (const sid of sids){
    for (const tid of tids){
      const wrap = document.querySelector(`.cell-flex[data-student="${sid}"][data-task="${tid}"]`);
      if (!wrap) continue;
      const btnGrade = wrap.querySelector('.pill.grade');
      setGradePill(btnGrade, raw); // نص كما هو
      markDirtyKey(wrap, true);
    }
  }
});

function applyStatusToTargets(status){
  const sids = targetStudents();
  const tids = targetTasks();
  if (!sids.length){ notify.info('اختر طلبة أو فعّل “تطبيق على الكل الظاهر”.'); return; }
  for (const sid of sids){
    for (const tid of tids){
      const wrap = document.querySelector(`.cell-flex[data-student="${sid}"][data-task="${tid}"]`);
      if (!wrap) continue;
      setStatusPills(wrap, status);
      markDirtyKey(wrap, true);
    }
  }
}

// =================== Save (only DIRTY) — reload grid from DB after success ===================
saveBtn?.addEventListener('click', async ()=>{
  if (DIRTY.size === 0){ notify.info('لا توجد تعديلات للحفظ.'); return; }

  const items = [];
  for (const k of DIRTY) {
    const [sid, tid] = k.split(':').map(Number);
    const wrap  = document.querySelector(`.cell-flex[data-student="${sid}"][data-task="${tid}"]`);
    if (!wrap) continue;

    const status   = getStatusPills(wrap);
    const btnGrade = wrap.querySelector('.pill.grade');
    const grade    = getGradePayload(btnGrade); // نص/رمز/رقم أو null

    items.push({ student_id: sid, task_id: tid, submission_status: status, grade });
  }
  if (!items.length){ notify.info('لم تُحدَّد خلايا صالحة للحفظ.'); return; }

  // UI: تعطيل الزر أثناء الحفظ مع مؤشر
  const oldHtml = saveBtn.innerHTML;
  saveBtn.disabled = true;
  saveBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i><span>جاري الحفظ…</span>`;

  try {
    const res = await fetch('/api/performance/bulk/update', {
      method:'POST',
      headers:{ 'Content-Type':'application/json', 'Accept':'application/json' },
      body: JSON.stringify({ items, updated_by: 'admin' })
    });

    const text = await res.text();
    if (!res.ok){
      let msg = text; try { msg = JSON.parse(text).message || msg; } catch(_){}
      notify.error(`خطأ ${res.status}: ${msg}`);
      return;
    }

    let data = null; try { data = JSON.parse(text); } catch(_){}
    notify.success((data && data.message) || 'تم الحفظ');

    // نظّف حالة التعديل
    DIRTY.clear();

    // الأهم: أعد جلب البيانات من الـ API لتأكيد العرض بالقيم النهائية من قاعدة البيانات
    await loadGrid();

    // إعادة تطبيق البحث لو كان موجوداً
    if (studentSearch && studentSearch.value.trim()){
      SEARCH_Q = studentSearch.value.trim().toLowerCase();
      refreshPagination();
    }
  } catch (e){
    console.error(e);
    notify.error('حدث خطأ غير متوقع أثناء الحفظ.');
  } finally {
    saveBtn.disabled = false;
    saveBtn.innerHTML = oldHtml;
  }
});

// =================== Dirty key helpers ===================
function markDirtyKey(wrap, on){
  const sid = wrap.dataset.student, tid = wrap.dataset.task;
  const key = `${sid}:${tid}`;
  if (on) { DIRTY.add(key); wrap.classList.add('is-dirty'); }
  else    { DIRTY.delete(key); wrap.classList.remove('is-dirty'); }
}

// =================== Pagination ===================
function refreshPagination(){
  const allRows = Array.from(bodyTable.querySelectorAll('tr'));

  // 1) name filter
  const filtered = allRows.filter(tr=>{
    if (!SEARCH_Q) return true;
    const nameTd = tr.querySelector('.student-name');
    return !!(nameTd && nameTd.textContent.toLowerCase().includes(SEARCH_Q));
  });

  // 2) pages
  const total = filtered.length;
  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
  if (CURRENT_PAGE > totalPages) CURRENT_PAGE = totalPages;
  if (CURRENT_PAGE < 1) CURRENT_PAGE = 1;

  // 3) show current page
  const start = (CURRENT_PAGE - 1) * PAGE_SIZE;
  const end   = start + PAGE_SIZE;
  const pageSlice = new Set(filtered.slice(start, end));

  allRows.forEach(tr => { tr.hidden = !pageSlice.has(tr); });

  // 4) UI
  renderPageNumbers(totalPages);
  updatePageInfo(total, start, Math.min(end, total));
  updatePickAllCheck();
}

function renderPageNumbers(totalPages){
  if (!pgNums) return;
  pgNums.innerHTML = '';

  const makeBtn = (p, label=p)=>{
    const b = document.createElement('button');
    b.textContent = String(label);
    b.className = (p === CURRENT_PAGE) ? 'active' : '';
    b.addEventListener('click', ()=>{ CURRENT_PAGE = p; refreshPagination(); });
    return b;
  };

  if (totalPages <= 7){
    for (let p=1; p<=totalPages; p++){ pgNums.appendChild(makeBtn(p)); }
  } else {
    const pushEllipsis = ()=> {
      const span = document.createElement('span');
      span.textContent = '…';
      span.style.padding = '0 .4rem';
      pgNums.appendChild(span);
    };
    pgNums.appendChild(makeBtn(1));
    if (CURRENT_PAGE > 3) pushEllipsis();
    const start = Math.max(2, CURRENT_PAGE - 1);
    const end   = Math.min(totalPages - 1, CURRENT_PAGE + 1);
    for (let p = start; p <= end; p++){ pgNums.appendChild(makeBtn(p)); }
    if (CURRENT_PAGE < totalPages - 2) pushEllipsis();
    pgNums.appendChild(makeBtn(totalPages));
  }

  if (pgPrev){
    pgPrev.disabled = CURRENT_PAGE <= 1;
    pgPrev.onclick = ()=>{ if (CURRENT_PAGE > 1){ CURRENT_PAGE--; refreshPagination(); } };
  }
  if (pgNext){
    pgNext.disabled = CURRENT_PAGE >= totalPages;
    pgNext.onclick = ()=>{ if (CURRENT_PAGE < totalPages){ CURRENT_PAGE++; refreshPagination(); } };
  }
}

function updatePageInfo(total, from, to){
  if (!pageInfo) return;
  if (total === 0) pageInfo.textContent = 'لا توجد نتائج';
  else pageInfo.textContent = `عرض ${from+1}–${to} من ${total}`;
}

function updatePickAllCheck(){
  const head = document.getElementById('headPickAll');
  if (!head) return;
  const pageChecks = Array.from(bodyTable.querySelectorAll('tr:not([hidden]) .row-pick'));
  head.checked = pageChecks.length > 0 && pageChecks.every(cb=> cb.checked);
}

// page size change + persist
pageSizeSel?.addEventListener('change', ()=>{
  PAGE_SIZE = parseInt(pageSizeSel.value, 10) || 10;
  localStorage.setItem('pf_page_size', String(PAGE_SIZE));
  CURRENT_PAGE = 1;
  refreshPagination();
});

// also update on blur/change
studentSearch?.addEventListener('change', ()=>{
  SEARCH_Q = (studentSearch.value||'').trim().toLowerCase();
  CURRENT_PAGE = 1;
  refreshPagination();
});

// =================== Utils ===================
function escapeHtml(str){
  return String(str || '')
    .replaceAll('&','&amp;').replaceAll('<','&lt;')
    .replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
}
