/* ===========================================================
 * Performance Groups — إدارة المجموعات (تحديد بالنقر + تفريغ بعد الإضافة)
 * =========================================================== */
(() => {
  "use strict";

  const API = {
    groups:     '/api/performance/groups',
    students:   '/api/performance/students',
    createG:    '/api/performance/groups/create',
    renameG:    id => `/api/performance/groups/${id}`,
    deleteG:    id => `/api/performance/groups/${id}`,
    assign:     '/api/performance/groups/assign',
    assignBulk: '/api/performance/groups/assign-bulk',
    unassign:   '/api/performance/groups/unassign',
  };

  const $  = (s, r=document) => r.querySelector(s);
  const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  const STATE = {
    students: [],
    groups:   [],
    selectedStudents: new Set(),
    selectedGroups:   new Set(),
    onlyUnassigned:   false,
    search:           '',
  };

  // ---------- Alerts ----------
  const swalErr = (msg, title='حدث خطأ') =>
    Swal.fire({icon:'error', title, text: msg || 'تعذر تنفيذ العملية حالياً.'});

  const swalOk = (msg, title='تم') =>
    Swal.fire({icon:'success', title, text: msg || 'تمت العملية بنجاح', timer: 1200, showConfirmButton:false});

  // ---------- Helpers ----------
  const withLoading = async (btn, fn) => {
    if (!btn) return fn();
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="loading-dot"></span>`;
    try { return await fn(); }
    finally { btn.disabled = false; btn.innerHTML = oldHtml; }
  };

  async function apiFetch(url, {method='GET', headers={}, body} = {}) {
    const opts = { method, headers: { ...headers }, body };
    const wantsJson = (headers['Content-Type'] || '').includes('application/json');
    if (method !== 'GET' && CSRF) opts.headers['X-CSRF-TOKEN'] = CSRF;
    if (method !== 'GET' && wantsJson && typeof body === 'undefined') opts.body = '{}';

    const res = await fetch(url, opts);
    let data;
    try { data = await res.json(); } catch { data = null; }
    if (!res.ok || (data && typeof data.status === 'string' && data.status.toLowerCase() !== 'ok')) {
      const msg = data?.message || `HTTP ${res.status}`;
      throw new Error(msg);
    }
    return data;
  }

  const filteredStudents = () => {
    const q = (STATE.search||'').trim().toLowerCase();
    return STATE.students.filter(s=>{
      if (STATE.onlyUnassigned && (s.groups?.length||0)>0) return false;
      if (q && !s.full_name.toLowerCase().includes(q)) return false;
      return true;
    });
  };

  const updateSelectedCount = () => {
    const el = $('#selectedCount');
    if (el) el.textContent = `${STATE.selectedStudents.size} محدد`;
  };

  const clearStudentSelection = () => {
    STATE.selectedStudents.clear();
    const selAll = $('#selectAllStudents');
    if (selAll) selAll.checked = false;
    updateSelectedCount();
    // إعادة تلوين القائمة الحالية دون انتظار إعادة التحميل
    $$('#studentsList li').forEach(li => li.classList.remove('selected'));
  };

  document.addEventListener('DOMContentLoaded', async () => {
    try {
      await Promise.all([loadGroups(true), loadStudents()]);
      renderStudents();
      renderGroups();
      wireBasics();
    } catch (e) { swalErr(e.message); }
  });

  async function loadGroups(withMembers=false) {
    const url = withMembers ? `${API.groups}?with=members` : API.groups;
    STATE.groups = await apiFetch(url);
  }

  async function loadStudents() {
    STATE.students = await apiFetch(API.students);
  }

  // ---------- Renderers ----------
  function renderStudents() {
    const ul = $('#studentsList');
    if (!ul) return;
    ul.innerHTML = '';

    const items = filteredStudents();
    for (const s of items) {
      const li = document.createElement('li');
      li.setAttribute('draggable', 'true');
      li.dataset.id = s.id;

      // مقبض سحب
      const handle = document.createElement('span');
      handle.className = 'drag-handle';
      handle.innerHTML = `<i class="fa-solid fa-grip-vertical"></i>`;

      // (نُبقي الـ checkbox لكن مخفيًا عبر CSS)
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.checked = STATE.selectedStudents.has(s.id);

      const nm = document.createElement('div');
      nm.className = 'name';
      nm.textContent = s.full_name;

      const tags = document.createElement('div');
      tags.className = 'tags';
      (s.groups||[]).forEach(g => {
        const span = document.createElement('span');
        span.className = 'tag';
        span.textContent = g.name;
        tags.appendChild(span);
      });

      li.appendChild(handle);
      li.appendChild(cb);
      li.appendChild(nm);
      li.appendChild(tags);

      // تمييز بصري إذا كان محددًا
      if (STATE.selectedStudents.has(s.id)) li.classList.add('selected');

      // تبديل التحديد بالنقر على الصف (مع تجنّب أثناء السحب)
      li.addEventListener('click', (e) => {
        if (li.classList.contains('dragging')) return; // تجنّب تفعيل click بعد السحب
        const sid = parseInt(li.dataset.id, 10);
        if (STATE.selectedStudents.has(sid)) {
          STATE.selectedStudents.delete(sid);
          li.classList.remove('selected');
          cb.checked = false;
        } else {
          STATE.selectedStudents.add(sid);
          li.classList.add('selected');
          cb.checked = true;
        }
        updateSelectedCount();
      });

      ul.appendChild(li);
    }

    enableDrag();
    updateSelectedCount();
  }

  function renderGroups() {
    const container = $('#groupsContainer');
    if (!container) return;
    container.innerHTML = '';

    for (const g of STATE.groups) {
      const card = document.createElement('div');
      card.className = 'group-card';
      card.dataset.groupId = g.id;

      const head = document.createElement('div');
      head.className = 'group-head';

      const chk = document.createElement('input');
      chk.type = 'checkbox';
      chk.checked = STATE.selectedGroups.has(g.id);
      chk.addEventListener('change', ()=> {
        chk.checked ? STATE.selectedGroups.add(g.id) : STATE.selectedGroups.delete(g.id);
      });

      const title = document.createElement('div');
      title.className = 'group-title';
      title.innerHTML = `<i class="fa-solid fa-users"></i> <span class="t">${escapeHtml(g.name)}</span> <span class="tag">${(g.students?.length||0)} طالب</span>`;

      const actions = document.createElement('div');
      actions.className = 'group-actions';
      actions.innerHTML = `
        <button class="btn-icon rename" title="إعادة تسمية" aria-label="إعادة تسمية"><i class="fa-solid fa-pen"></i></button>
        <button class="btn-icon delete" title="حذف المجموعة" aria-label="حذف المجموعة"><i class="fa-solid fa-trash"></i></button>
      `;

      head.appendChild(chk);
      head.appendChild(title);
      head.appendChild(actions);

      const body = document.createElement('div');
      body.className = 'group-body drop-host';
      body.dataset.group = g.id;

      const helper = document.createElement('div');
      helper.className = 'dz-helper';
      helper.innerHTML = `<i class="fa-solid fa-arrow-turn-down"></i> اسحب الطلبة إلى هنا للإضافة`;

      const addBtn = document.createElement('button');
      addBtn.className = 'btn btn-accent';
      addBtn.innerHTML = `<i class="fa-solid fa-user-plus"></i> <span>إضافة الطلبة المحددين لهذه المجموعة</span>`;
      addBtn.addEventListener('click', () => assignSelectedToGroup(g.id, addBtn));

      const membersList = document.createElement('div');
      (g.students||[]).forEach(m => {
        membersList.appendChild(memberRow(g.id, m.id, m.full_name));
      });

      body.appendChild(helper);
      body.appendChild(addBtn);
      body.appendChild(membersList);

      card.appendChild(head);
      card.appendChild(body);
      container.appendChild(card);
    }

    enableDropHosts();
    wireGroupActions();
  }

  function memberRow(groupId, studentId, name) {
    const row = document.createElement('div');
    row.className = 'member';
    row.dataset.studentId = studentId;
    row.innerHTML = `
      <i class="fa-solid fa-user"></i>
      <span class="nm">${escapeHtml(name)}</span>
      <div style="flex:1"></div>
      <span class="remove" title="إزالة من المجموعة" aria-label="إزالة من المجموعة"><i class="fa-solid fa-xmark"></i></span>
    `;
    row.querySelector('.remove').addEventListener('click', async (e) => {
      const btn = e.currentTarget;
      const ok = await Swal.fire({
        icon:'warning',
        title:'تأكيد الإزالة',
        text:'سيتم إزالة الطالب من هذه المجموعة.',
        showCancelButton:true,
        confirmButtonText:'نعم، إزالة',
        cancelButtonText:'إلغاء'
      });
      if (!ok.isConfirmed) return;

      try {
        await withLoading(btn, () => apiFetch(API.unassign, {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ group_id: groupId, student_ids: [studentId] })
        }));
        await reloadAll();
        swalOk('تمت الإزالة.');
      } catch (err) { swalErr(err.message); }
    });
    return row;
  }

  // ---------- Wire UI ----------
  function wireBasics(){
    $('#addGroupBtn')?.addEventListener('click', async (e)=> {
      const btn = e.currentTarget;
      const name = ($('#newGroupName')?.value || '').trim();
      if(!name) return swalErr('أدخل اسم المجموعة أولاً.', 'تنبيه');
      try {
        await withLoading(btn, () => apiFetch(API.createG, {
          method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({name})
        }));
        $('#newGroupName').value='';
        await loadGroups(true); renderGroups();
        swalOk('تمت إضافة المجموعة.');
      } catch (err) { swalErr(err.message); }
    });

    $('#selectAllStudents')?.addEventListener('change', (e)=>{
      const items = filteredStudents();
      STATE.selectedStudents = new Set(e.target.checked ? items.map(s=>s.id) : []);
      renderStudents();
    });

    $('#onlyUnassigned')?.addEventListener('change', (e)=>{
      STATE.onlyUnassigned = !!e.target.checked;
      renderStudents();
    });

    $('#studentSearch')?.addEventListener('input', (e)=>{
      STATE.search = e.target.value;
      renderStudents();
    });

    $('#selectAllGroups')?.addEventListener('change', (e)=>{
      STATE.selectedGroups = new Set(e.target.checked ? STATE.groups.map(g=>g.id) : []);
      renderGroups();
    });

    $('#assignSelectedToCheckedGroups')?.addEventListener('click', assignSelectedToCheckedGroups);
    $('#unassignSelectedFromCheckedGroups')?.addEventListener('click', unassignSelectedFromCheckedGroups);
  }

  function wireGroupActions(){
    // إعادة تسمية
    $$('#groupsContainer .rename').forEach(btn=>{
      btn.addEventListener('click', async (e)=>{
        const card = e.currentTarget.closest('.group-card');
        const id = parseInt(card.dataset.groupId,10);
        const oldName = card.querySelector('.group-title .t').textContent.trim();

        const { value: nn } = await Swal.fire({
          title: 'إعادة تسمية المجموعة',
          input: 'text', inputValue: oldName,
          inputPlaceholder: 'اسم المجموعة',
          showCancelButton: true, confirmButtonText:'حفظ', cancelButtonText:'إلغاء',
          inputValidator: v => (!v || !v.trim()) ? 'أدخل اسمًا صحيحًا' : null
        });
        if (!nn || nn.trim()===oldName) return;

        try {
          await withLoading(e.currentTarget, () => apiFetch(API.renameG(id), {
            method:'PATCH', headers:{'Content-Type':'application/json'}, body: JSON.stringify({name: nn.trim()})
          }));
          await loadGroups(true); renderGroups();
          swalOk('تم تحديث الاسم.');
        } catch (err) { swalErr(err.message); }
      });
    });

    // حذف
    $$('#groupsContainer .delete').forEach(btn=>{
      btn.addEventListener('click', async (e)=>{
        const card = e.currentTarget.closest('.group-card');
        const id = parseInt(card.dataset.groupId,10);

        const ok = await Swal.fire({
          icon:'warning', title:'حذف المجموعة',
          text:'سيُحذف سجل المجموعة فقط (لن تُحذف حسابات الطلبة).',
          showCancelButton:true, confirmButtonText:'حذف', cancelButtonText:'إلغاء'
        });
        if (!ok.isConfirmed) return;

        try {
          await withLoading(e.currentTarget, () => apiFetch(API.deleteG(id), { method:'DELETE' }));
          await reloadAll();
          swalOk('تم حذف المجموعة.');
        } catch (err) { swalErr(err.message); }
      });
    });
  }

  // ---------- Drag & Drop ----------
  function enableDrag(){
    $$('#studentsList li[draggable="true"]').forEach(li=>{
      li.addEventListener('dragstart', e=>{
        e.dataTransfer.effectAllowed = 'copy';
        e.dataTransfer.dropEffect = 'copy';
        e.dataTransfer.setData('text/plain', li.dataset.id);
        li.classList.add('dragging');
      });
      li.addEventListener('dragend', ()=> li.classList.remove('dragging'));
    });
  }

  function enableDropHosts(){
    $$('#groupsContainer .group-body.drop-host').forEach(host=>{
      const onDragEnter = (e)=>{ e.preventDefault(); host.classList.add('drag-over'); };
      const onDragOver  = (e)=>{ e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; host.classList.add('drag-over'); };
      const onDragLeave = ()=> host.classList.remove('drag-over');
      const onDrop = async (e)=>{
        e.preventDefault();
        host.classList.remove('drag-over');

        const gid = parseInt(host.dataset.group, 10);
        let toAssign = Array.from(STATE.selectedStudents);

        if (!toAssign.length) {
          const draggedId = parseInt(e.dataTransfer.getData('text/plain'), 10);
          if (draggedId) toAssign = [draggedId];
        }
        if (!toAssign.length) return swalErr('اختر طالبًا أو اسحبه إلى المجموعة.');

        try {
          await apiFetch(API.assignBulk, {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ group_ids: [gid], student_ids: toAssign })
          });
          // ⬅️ إلغاء التحديد بعد الإضافة
          clearStudentSelection();
          await reloadAll();
          swalOk('تمت الإضافة للمجموعة عبر السحب والإفلات.');
        } catch (err) { swalErr(err.message); }
      };

      host.addEventListener('dragenter', onDragEnter);
      host.addEventListener('dragover',  onDragOver);
      host.addEventListener('dragleave', onDragLeave);
      host.addEventListener('drop',      onDrop);
    });
  }

  // ---------- Assign / Unassign ----------
  async function assignSelectedToGroup(groupId, btn){
    const ids = Array.from(STATE.selectedStudents);
    if(!ids.length) return swalErr('اختر طلبة أولًا.', 'تنبيه');
    try {
      await withLoading(btn, () => apiFetch(API.assign, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ group_id: groupId, student_ids: ids })
      }));
      clearStudentSelection();   // ⬅️ إلغاء التحديد بعد الإضافة
      await reloadAll();
      swalOk('تمت الإضافة للمجموعة.');
    } catch (err) { swalErr(err.message); }
  }

  async function assignSelectedToCheckedGroups(e){
    const btn = e.currentTarget;
    const sids = Array.from(STATE.selectedStudents);
    if(!sids.length) return swalErr('اختر طلبة أولًا.', 'تنبيه');
    const gids = Array.from(STATE.selectedGroups);
    if(!gids.length) return swalErr('اختر مجموعة/مجموعات أولًا.', 'تنبيه');

    try {
      await withLoading(btn, () => apiFetch(API.assignBulk, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ group_ids: gids, student_ids: sids })
      }));
      clearStudentSelection();   // ⬅️ إلغاء التحديد بعد الإضافة
      await reloadAll();
      swalOk('تمت الإضافة للمجموعات المحددة.');
    } catch (err) { swalErr(err.message); }
  }

  async function unassignSelectedFromCheckedGroups(e){
    const btn = e.currentTarget;
    const sids = Array.from(STATE.selectedStudents);
    if(!sids.length) return swalErr('اختر طلبة أولًا.', 'تنبيه');
    const gids = Array.from(STATE.selectedGroups);
    if(!gids.length) return swalErr('اختر مجموعة/مجموعات أولًا.', 'تنبيه');

    const ok = await Swal.fire({
      icon:'warning', title:'تأكيد الإزالة',
      text:'سيتم إزالة الطلبة المحددين من كل المجموعات المختارة.',
      showCancelButton:true, confirmButtonText:'موافق', cancelButtonText:'إلغاء'
    });
    if (!ok.isConfirmed) return;

    try {
      for (const gid of gids) {
        await apiFetch(API.unassign, {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ group_id: gid, student_ids: sids })
        });
      }
      clearStudentSelection();   // ⬅️ تفريغ التحديد بعد الإزالة
      await reloadAll();
      swalOk('تمت الإزالة من المجموعات المحددة.');
    } catch (err) { swalErr(err.message); }
  }

  // ---------- Utilities ----------
  async function reloadAll(){
    await Promise.all([loadGroups(true), loadStudents()]);
    renderGroups();
    renderStudents();
  }

  function escapeHtml(str=''){
    return String(str)
      .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
      .replaceAll('"','&quot;').replaceAll("'",'&#039;');
  }
})();
