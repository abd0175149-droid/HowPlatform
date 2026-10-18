/* =========================================================================
 *  Admin: Manage Users — Professional dashboard
 *  KPIs + charts + date/activity filters + multi-select bulk actions
 *  + 30-day deletion notice + per-user activity drawer.
 * ========================================================================= */
/* global Swal, showSuccess, showError, showConfirm, showWarning */

export function init() {
  const $ = (id) => document.getElementById(id);
  const API = {
    LIST:   '/manage_users/list',
    OPTS:   '/manage_users/options',
    STATS:  '/manage_users/stats',
    DASH:   '/manage_users/dashboard',
    ACT:    '/manage_users/activity/',
    EXPORT: '/manage_users/export',
    UPDATE: '/manage_users/update_account',
    DEL:    '/manage_users/delete',
    BULK:   '/manage_users/bulk_delete',
    SCHED:  '/manage_users/schedule_deletion',
    CANCEL: '/manage_users/cancel_deletion',
  };

  const state = {
    page: 1, perPage: 10, totalPages: 1,
    selected: new Set(),
    opts: { roles: [], universities: [], years: [], specialties: [] },
    charts: {},
  };

  const els = {
    tbody: $('usersTbody'), selectAll: $('selectAll'),
    pgInfo: $('paginationInfo'),
    first: $('firstPageBtn'), prev: $('prevPageBtn'), next: $('nextPageBtn'), last: $('lastPageBtn'),
    bulkBar: $('bulkBar'), bulkCount: $('bulkCount'),
    drawer: $('activityPanel'), drawerBody: $('activityBody'),
  };

  const F = {
    role: $('fRole'), complete: $('fComplete'), activity: $('fActivity'),
    regFrom: $('fRegFrom'), regTo: $('fRegTo'),
    username: $('fUsername'), email: $('fEmail'), phone: $('fPhone'),
    sort: $('fSort'), scheduled: $('fScheduled'),
  };

  /* ---------- helpers ---------- */
  const esc = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  const ok  = (m) => (typeof showSuccess === 'function' ? showSuccess(m, undefined, true) : console.log(m));
  const err = (m) => (typeof showError === 'function' ? showError(m) : alert(m));
  const confirmAct = (opts) => (typeof showConfirm === 'function'
    ? showConfirm(opts)
    : (window.confirm(`${opts.title}\n${opts.text||''}`) && opts.onConfirm && opts.onConfirm()));

  function jget(url){ return fetch(url, { headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } }); }
  function jpost(url, body){
    const h = { 'X-Requested-With':'XMLHttpRequest', 'Content-Type':'application/json', 'Accept':'application/json' };
    const m = document.querySelector('meta[name="csrf-token"]'); if (m) h['X-CSRF-TOKEN'] = m.getAttribute('content') || '';
    return fetch(url, { method:'POST', headers:h, body: JSON.stringify(body || {}) });
  }
  function fmtDate(s){ if(!s) return '—'; const d = new Date(String(s).replace(' ','T')); if(isNaN(d)) return esc(s);
    return d.toLocaleDateString('ar-EG-u-nu-latn', { year:'numeric', month:'short', day:'numeric' }); }
  function timeAgo(s){ if(!s) return 'لم يدخل قط'; const d = new Date(String(s).replace(' ','T')); if(isNaN(d)) return '—';
    const diff=(Date.now()-d.getTime())/1000; if(diff<3600) return 'قبل '+Math.max(1,Math.floor(diff/60))+' د';
    if(diff<86400) return 'قبل '+Math.floor(diff/3600)+' س'; if(diff<2592000) return 'قبل '+Math.floor(diff/86400)+' يوم';
    return fmtDate(s); }
  function roleName(id){ const r = state.opts.roles.find(x=>String(x.id)===String(id)); return r?r.name:('#'+id); }
  function avatar(url, gender){ const fb = gender==='female' ? '/public/uploads/profile/default_female.jpg' : '/public/uploads/profile/default_male.jpg';
    return (url && String(url).trim()) ? esc(url) : fb; }
  const truthy = (v) => Number(v) === 1;               // يتعامل مع "0"/"1" النصية الآتية من DB
  function premiumState(u){                            // 'active' | 'expired' | 'none'
    if (!truthy(u.is_premium)) return 'none';
    if (!u.premium_until) return 'active';             // بلا تاريخ انتهاء = مدى الحياة
    return (new Date(String(u.premium_until).replace(' ','T')).getTime() >= Date.now()) ? 'active' : 'expired';
  }

  /* ---------- boot ---------- */
  (async () => {
    await loadOptions();
    renderRoleFilter();
    wireFilters();
    wirePager();
    wireBulk();
    wireDrawer();
    fetchUsers();
    fetchStats();
    fetchDashboard();
  })();

  /* ---------- options ---------- */
  async function loadOptions(){
    try { const j = await (await jget(API.OPTS)).json();
      state.opts = { roles:j.roles||[], universities:j.universities||[], years:j.years||[], specialties:j.specialties||[] };
    } catch { /* silent */ }
  }
  function renderRoleFilter(){
    if(!F.role) return;
    F.role.innerHTML = '<option value="">كل الأدوار</option>' + state.opts.roles.map(r=>`<option value="${esc(r.id)}">${esc(r.name)}</option>`).join('');
  }

  /* ---------- filters ---------- */
  let deb;
  function wireFilters(){
    ['change'].forEach(ev=>{ [F.role,F.complete,F.activity,F.regFrom,F.regTo,F.sort,F.scheduled].forEach(el=> el && el.addEventListener(ev, ()=>{ state.page=1; fetchUsers(); })); });
    [F.username,F.email,F.phone].forEach(el=> el && el.addEventListener('input', ()=>{ clearTimeout(deb); deb=setTimeout(()=>{ state.page=1; fetchUsers(); }, 600); }));
    const reset = $('btnResetFilters'); if(reset) reset.addEventListener('click', ()=>{
      [F.role,F.complete,F.activity,F.regFrom,F.regTo,F.username,F.email,F.phone,F.sort].forEach(el=>{ if(el) el.value=''; });
      if(F.scheduled) F.scheduled.checked=false; state.page=1; fetchUsers();
    });
    const refresh = $('btnRefreshAll'); if(refresh) refresh.addEventListener('click', ()=>{ fetchUsers(); fetchStats(); fetchDashboard(); });
  }
  function queryParams(){
    const p = new URLSearchParams({
      role_id: F.role?.value||'', complete: F.complete?.value||'', activity: F.activity?.value||'',
      registered_from: F.regFrom?.value||'', registered_to: F.regTo?.value||'',
      username:(F.username?.value||'').trim(), email:(F.email?.value||'').trim(), phone:(F.phone?.value||'').trim(),
      sort: F.sort?.value||'', scheduled: F.scheduled?.checked?'1':'',
      page:String(state.page), per_page:String(state.perPage),
    });
    return p;
  }

  /* ---------- users list ---------- */
  function fetchUsers(){
    els.tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--text-muted)">جارٍ التحميل…</td></tr>`;
    jget(`${API.LIST}?${queryParams().toString()}`).then(r=>r.json()).then(j=>{
      const users = j?.data?.users || [];
      const pg = j?.data?.pagination;
      if (pg){ state.page=pg.page; state.perPage=pg.per_page; state.totalPages=pg.total_pages||1; }
      renderUsers(users); updatePager(); syncSelectAll();
    }).catch(()=>{ els.tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:#ef4444;padding:20px">فشل تحميل البيانات</td></tr>`; });
  }

  function renderUsers(list){
    if(!list.length){ els.tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--text-muted)">لا يوجد مستخدمون مطابقون</td></tr>`; return; }
    els.tbody.innerHTML = list.map(u=>{
      const sel = state.selected.has(u.id);
      const badges = [];
      const pst = premiumState(u);
      if (pst === 'active') badges.push('<span class="mu2-bdg mu2-bdg--prem">مميّز</span>');
      else if (pst === 'expired') badges.push('<span class="mu2-bdg mu2-bdg--inc" title="اشتراك منتهٍ">اشتراك منتهٍ</span>');
      if (!truthy(u.is_complete)) badges.push('<span class="mu2-bdg mu2-bdg--inc">غير مكتمل</span>');
      if (u.deletion_scheduled_at) badges.push(`<span class="mu2-bdg mu2-bdg--del" title="مجدول للحذف: ${esc(u.deletion_scheduled_at)}">للحذف</span>`);
      const act = u.last_seen_at || u.last_login_at;
      const idle = act ? ((Date.now()-new Date(String(act).replace(' ','T')).getTime())/86400000 > 30) : true;
      return `<tr data-id="${esc(u.id)}" class="${sel?'is-sel':''}">
        <td class="mu2-chkcol"><input type="checkbox" class="mu2-rowchk" data-id="${esc(u.id)}" ${sel?'checked':''}></td>
        <td><div class="mu2-uc"><img src="${avatar(u.profile_image_url,u.gender)}" onerror="this.onerror=null;this.src='/public/uploads/profile/default_male.jpg'">
          <div class="mu2-uc__t"><div class="mu2-uc__n">${esc(u.name||'—')}</div><div class="mu2-uc__e">${esc(u.email||'')}</div></div></div></td>
        <td class="mu2-dim">${esc(u.phone_number||'—')}</td>
        <td><span class="mu2-rolechip">${esc(u.role_name||roleName(u.role_id))}</span></td>
        <td class="mu2-dim">${fmtDate(u.created_at)}</td>
        <td><span class="${idle?'mu2-bdg mu2-bdg--idle':'mu2-dim'}">${timeAgo(act)}</span></td>
        <td><div class="mu2-badges">${badges.join('')||'<span class="mu2-dim">—</span>'}</div></td>
        <td><div class="mu2-act">
          <button class="mu2-iconbtn mu2-act-view" data-id="${esc(u.id)}" title="سجل النشاط"><i class="fas fa-chart-simple"></i></button>
          <button class="mu2-iconbtn del mu2-act-del" data-id="${esc(u.id)}" data-name="${esc(u.name||'المستخدم')}" title="حذف"><i class="fas fa-trash"></i></button>
        </div></td>
      </tr>`;
    }).join('');

    els.tbody.querySelectorAll('.mu2-rowchk').forEach(c=> c.addEventListener('change', ()=>toggleSelect(Number(c.dataset.id), c.checked)));
    els.tbody.querySelectorAll('.mu2-act-view').forEach(b=> b.addEventListener('click', (e)=>{ e.stopPropagation(); openActivity(Number(b.dataset.id)); }));
    els.tbody.querySelectorAll('.mu2-act-del').forEach(b=> b.addEventListener('click', (e)=>{ e.stopPropagation(); singleDelete(Number(b.dataset.id), b.dataset.name); }));

    // النقر على أي مكان من الصف يحدّده (عدا الأزرار/التشيك-بوكس)
    els.tbody.querySelectorAll('tr[data-id]').forEach(tr=>{
      tr.style.cursor = 'pointer';
      tr.addEventListener('click', (e)=>{
        if (e.target.closest('button, a, input, .mu2-act')) return;
        const id  = Number(tr.dataset.id);
        const on  = !state.selected.has(id);
        const chk = tr.querySelector('.mu2-rowchk');
        if (chk) chk.checked = on;
        toggleSelect(id, on);
      });
    });
  }

  /* ---------- selection ---------- */
  function toggleSelect(id, on){
    if(on) state.selected.add(id); else state.selected.delete(id);
    const row = els.tbody.querySelector(`tr[data-id="${id}"]`); if(row) row.classList.toggle('is-sel', on);
    updateBulkBar(); syncSelectAll();
  }
  function syncSelectAll(){
    if(!els.selectAll) return;
    const rows = Array.from(els.tbody.querySelectorAll('.mu2-rowchk'));
    els.selectAll.checked = rows.length>0 && rows.every(c=>c.checked);
  }
  function updateBulkBar(){
    const n = state.selected.size;
    if(els.bulkCount) els.bulkCount.textContent = n;
    if(els.bulkBar) els.bulkBar.hidden = n===0;
  }
  function selectedIds(){ return Array.from(state.selected); }

  /* ---------- bulk ---------- */
  function wireBulk(){
    if(els.selectAll) els.selectAll.addEventListener('change', ()=>{
      const on = els.selectAll.checked;                 // التُقِط قبل الحلقة (toggleSelect كان يُعيد ضبطه ويُفسد الباقي)
      els.tbody.querySelectorAll('.mu2-rowchk').forEach(c=>{
        c.checked = on;
        const id = Number(c.dataset.id);
        if (on) state.selected.add(id); else state.selected.delete(id);
        c.closest('tr')?.classList.toggle('is-sel', on);
      });
      updateBulkBar();
      els.selectAll.checked = on;                        // ثبّت الحالة بعد التحديث
    });
    $('btnBulkDelete')?.addEventListener('click', bulkDelete);
    $('btnBulkNotify')?.addEventListener('click', bulkNotify);
    $('btnBulkCancel')?.addEventListener('click', bulkCancel);
    $('btnBulkExport')?.addEventListener('click', ()=>{ const ids=selectedIds(); window.open(`${API.EXPORT}${ids.length?`?ids=${ids.join(',')}`:''}`, '_blank'); });
  }
  function bulkDelete(){
    const ids = selectedIds(); if(!ids.length) return;
    confirmAct({ title:`حذف ${ids.length} حساباً نهائياً؟`, text:'سيُحذف الحساب وبياناته المرتبطة في الدبلوم (لا يمسّ حساب المصادقة). لا يمكن التراجع.',
      confirmText:'نعم، احذف', cancelText:'إلغاء', onConfirm: async ()=>{
        try { const j = await (await jpost(API.BULK, { user_ids:ids })).json();
          if(j.success){ ok(j.message||'تم الحذف'); state.selected.clear(); updateBulkBar(); fetchUsers(); fetchStats(); } else err(j.message||'فشل'); }
        catch { err('فشل الاتصال'); } } });
  }
  function bulkNotify(){
    const ids = selectedIds(); if(!ids.length) return;
    confirmAct({ title:`إشعار ${ids.length} مستخدماً بالحذف خلال 30 يوماً؟`, text:'سيتلقّى كلٌّ منهم إشعاراً، ويُجدوَل حذف بياناتهم تلقائياً بعد 30 يوماً ما لم يسجّلوا الدخول.',
      confirmText:'نعم، أرسل وجدوِل', cancelText:'إلغاء', onConfirm: async ()=>{
        try { const j = await (await jpost(API.SCHED, { user_ids:ids })).json();
          if(j.success){ ok(j.message||'تمت الجدولة'); state.selected.clear(); updateBulkBar(); fetchUsers(); fetchStats(); } else err(j.message||'فشل'); }
        catch { err('فشل الاتصال'); } } });
  }
  function bulkCancel(){
    const ids = selectedIds(); if(!ids.length) return;
    jpost(API.CANCEL, { user_ids:ids }).then(r=>r.json()).then(j=>{
      if(j.success){ ok(j.message||'أُلغيت الجدولة'); state.selected.clear(); updateBulkBar(); fetchUsers(); fetchStats(); } else err(j.message||'فشل');
    }).catch(()=>err('فشل الاتصال'));
  }
  function singleDelete(id, name){
    confirmAct({ title:`حذف ${name}؟`, text:'سيُحذف الحساب وبياناته المرتبطة. لا يمكن التراجع.', confirmText:'نعم، احذف', cancelText:'إلغاء',
      onConfirm: async ()=>{ try { const j = await (await jpost(API.DEL, { user_id:id })).json();
        if(j.success){ ok('تم الحذف'); state.selected.delete(id); updateBulkBar(); fetchUsers(); fetchStats(); } else err(j.message||'فشل'); }
        catch { err('فشل الاتصال'); } } });
  }

  /* ---------- pager ---------- */
  function wirePager(){
    els.first?.addEventListener('click', ()=>go(1));
    els.prev?.addEventListener('click', ()=>go(state.page-1));
    els.next?.addEventListener('click', ()=>go(state.page+1));
    els.last?.addEventListener('click', ()=>go(state.totalPages));
  }
  function go(p){ if(p<1||p>state.totalPages||p===state.page) return; state.page=p; fetchUsers(); }
  function updatePager(){
    if(els.pgInfo) els.pgInfo.textContent = `صفحة ${state.page} من ${state.totalPages}`;
    const dp = state.page<=1, dn = state.page>=state.totalPages;
    [els.first,els.prev].forEach(b=> b && (b.disabled=dp)); [els.next,els.last].forEach(b=> b && (b.disabled=dn));
  }

  /* ---------- stats KPIs ---------- */
  async function fetchStats(){
    try { const j = await (await jget(API.STATS)).json(); const d = j?.data||{};
      const set=(id,v)=>{ const e=$(id); if(e) e.textContent = Number(v||0).toLocaleString('en-US'); };
      set('kTotal',d.total); set('kStudents',d.students); set('kTeachers',d.teachers); set('kAdmins',d.admins);
      set('kActive30',d.active_30); set('kNew30',d.new_30); set('kCompleted',d.completed); set('kPremium',d.premium); set('kScheduled',d.scheduled_deletion);
      drawRoles(d.by_role||[]);
    } catch { /* silent */ }
  }

  /* ---------- charts ---------- */
  async function ensureChart(){ if(window.Chart) return true;
    try { await (window.loadScriptOnce ? window.loadScriptOnce('https://cdn.jsdelivr.net/npm/chart.js') : Promise.reject()); } catch {}
    return !!window.Chart;
  }
  function cssVar(n){ return getComputedStyle(document.documentElement).getPropertyValue(n).trim() || undefined; }
  async function fetchDashboard(){
    if(!(await ensureChart())) return;
    try { const j = await (await jget(API.DASH)).json(); const d = j?.data||{};
      drawReg(d.registrations||[]); drawLogins(d.daily_logins||[]);
    } catch { /* silent */ }
  }
  function mk(id,type,data,options){ const c=$(id); if(!c||!window.Chart) return;
    if(state.charts[id]) state.charts[id].destroy();
    state.charts[id]=new Chart(c.getContext('2d'),{type,data,options:Object.assign({responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}}},options||{})});
  }
  function drawReg(rows){ mk('chartReg','line',{ labels:rows.map(r=>r.ym), datasets:[{ data:rows.map(r=>Number(r.c)||0),
    borderColor:cssVar('--primary-500')||'#219ebc', backgroundColor:'rgba(33,158,188,.12)', fill:true, tension:.35, pointRadius:2 }] },
    { scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } } }); }
  function drawLogins(rows){ mk('chartLogins','bar',{ labels:rows.map(r=>String(r.d).slice(5)), datasets:[{ data:rows.map(r=>Number(r.c)||0),
    backgroundColor:cssVar('--accent')||'#fd9e02', borderRadius:4 }] }, { scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } } }); }
  function drawRoles(byRole){ if(!window.Chart){ return; } const labels=byRole.map(r=>r.role_name||('#'+r.role_id)); const vals=byRole.map(r=>Number(r.count)||0);
    mk('chartRoles','doughnut',{ labels, datasets:[{ data:vals, backgroundColor:['#219ebc','#fd9e02','#8ecae6','#22c55e','#ef4444','#a78bfa'], borderWidth:0 }] },
    { cutout:'62%', plugins:{ legend:{ display:true, position:'bottom', labels:{ boxWidth:10, font:{ size:11 } } } } }); }

  /* ---------- activity drawer ---------- */
  function wireDrawer(){
    els.drawer?.querySelectorAll('[data-close-drawer]').forEach(b=> b.addEventListener('click', closeDrawer));
  }
  function closeDrawer(){ if(els.drawer) els.drawer.hidden = true; }
  async function openActivity(id){
    if(!els.drawer) return; els.drawer.hidden=false;
    els.drawerBody.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:30px">جارٍ التحميل…</div>';
    try {
      const j = await (await jget(API.ACT+id)).json();
      if(!j.success){ els.drawerBody.innerHTML='<div style="color:#ef4444">تعذّر جلب البيانات</div>'; return; }
      const p=j.data.profile||{}, devices=j.data.devices||[], logins=j.data.logins||[];
      const row=(k,v)=>`<div class="mu2-actrow"><span>${k}</span><b>${v}</b></div>`;
      const yes=(v)=> truthy(v) ? '<span style="color:#22c55e">نعم</span>' : '<span style="color:#ef4444">لا</span>';
      const pst = premiumState(p);
      const premTxt = pst==='active' ? ('نعم'+(p.premium_until?(' · حتى '+fmtDate(p.premium_until)):' · مدى الحياة'))
                    : pst==='expired' ? ('<span style="color:#ef4444">منتهٍ · '+fmtDate(p.premium_until)+'</span>')
                    : 'لا';
      els.drawerBody.innerHTML = `
        <div class="mu2-actsec" style="display:flex;align-items:center;gap:12px">
          <img src="${avatar(p.avatar)}" style="width:54px;height:54px;border-radius:50%;object-fit:cover;border:1px solid var(--border-color)" onerror="this.onerror=null;this.src='/public/uploads/profile/default_male.jpg'">
          <div><div style="font-weight:800;color:var(--text-strong)">${esc(p.name||'—')}</div><div class="mu2-dim">${esc(p.email||'')}</div></div>
        </div>
        <div class="mu2-actsec"><h4>النشاط</h4>
          ${row('تاريخ التسجيل', fmtDate(p.created_at))}
          ${row('آخر دخول', p.last_login_at? (fmtDate(p.last_login_at)+' · '+timeAgo(p.last_login_at)) : 'لم يدخل قط')}
          ${row('آخر نشاط', p.last_seen_at? (fmtDate(p.last_seen_at)+' · '+timeAgo(p.last_seen_at)) : '—')}
          ${row('الاشتراك المميّز', premTxt)}
          ${row('اكتمال الملف', yes(p.is_complete))}
          ${row('قبول الشروط والسياسة', yes((truthy(p.terms_accepted) || truthy(p.consent_given)) ? 1 : 0))}
          ${p.deletion_scheduled_at? row('مجدول للحذف', '<span style="color:#ef4444">'+fmtDate(p.deletion_scheduled_at)+'</span>') : ''}
        </div>
        <div class="mu2-actsec"><h4>الأجهزة (${devices.length})</h4>
          ${devices.length? devices.map(d=>`<div class="mu2-dev"><div class="d1">${esc(d.device_label||'جهاز')} ${d.is_active?'<span style="color:#22c55e">●</span>':''}</div>
            <div class="d2">IP: ${esc(d.ip_address||'—')} · آخر استخدام: ${d.last_used_at?timeAgo(d.last_used_at):'—'}</div>
            <div class="d2" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc((d.user_agent||'').slice(0,80))}</div></div>`).join('') : '<div class="mu2-dim">لا أجهزة مسجّلة</div>'}
        </div>
        <div class="mu2-actsec"><h4>سجل الدخول (آخر ${logins.length})</h4>
          <div style="display:flex;flex-wrap:wrap;gap:5px">${logins.length? logins.map(l=>`<span class="mu2-bdg mu2-bdg--inc">${esc(l.d)}</span>`).join('') : '<span class="mu2-dim">لا يوجد</span>'}</div>
        </div>
        <div class="mu2-actsec" style="display:flex;gap:8px;flex-wrap:wrap">
          ${p.deletion_scheduled_at
            ? `<button class="mu2-btn mu2-btn--ghost" id="drwCancel"><i class="fas fa-ban"></i> إلغاء جدولة الحذف</button>`
            : `<button class="mu2-btn mu2-btn--warn" id="drwNotify"><i class="fas fa-bell"></i> إشعار حذف 30 يوم</button>`}
          <button class="mu2-btn mu2-btn--danger" id="drwDelete"><i class="fas fa-trash"></i> حذف نهائي</button>
        </div>`;
      $('drwNotify')?.addEventListener('click', ()=> jpost(API.SCHED,{user_ids:[id]}).then(r=>r.json()).then(()=>{ ok('تمت الجدولة والإشعار'); openActivity(id); fetchUsers(); fetchStats(); }));
      $('drwCancel')?.addEventListener('click', ()=> jpost(API.CANCEL,{user_ids:[id]}).then(r=>r.json()).then(()=>{ ok('أُلغيت الجدولة'); openActivity(id); fetchUsers(); fetchStats(); }));
      $('drwDelete')?.addEventListener('click', ()=> singleDelete(id, p.name||'المستخدم'));
    } catch { els.drawerBody.innerHTML='<div style="color:#ef4444">فشل الاتصال</div>'; }
  }
}
