/* public/assets/js/sections/plans.js */
/* إدارة الخطط + الميزات — ديناميكي، مع SweetAlert، Repeaters، وتشبس من سطرين */

/*===============================================
  Helpers & API
================================================*/
const API = {
  list:   '/admins/api/plans',
  create: '/admins/api/plans',
  update: (id) => `/admins/api/plans/${id}`,
  remove: (id) => `/admins/api/plans/${id}`,
  featAdd:    (pid) => `/admins/api/plans/${pid}/features`,
  featUpdate: (fid) => `/admins/api/plan-features/${fid}`,
  featDelete: (fid) => `/admins/api/plan-features/${fid}`,
};

let el = {};
let state = {
  plans: [],
  featuresByPlan: {},
  filter: { showInactive: false, q: '' },
};

function qs(s, r=document){ return r.querySelector(s); }
function qsa(s, r=document){ return Array.from(r.querySelectorAll(s)); }

/* Fetch آمن مع هيدرات AJAX */
async function jsonFetch(url, opts={}) {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(opts.headers || {})
  };
  const res = await fetch(url, { ...opts, headers });
  if (res.status === 401) {
    window.location.href = '/auth/login';
    throw new Error('Unauthorized');
  }
  const j = await res.json().catch(()=>null);
  if (!res.ok) throw new Error(j?.message || `HTTP ${res.status}`);
  return j;
}

/* ترميز HTML بسيط */
function escapeHtml(s) {
  return String(s ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

/* SweetAlert Helpers */
function swalError(text){
  if (window.Swal && Swal.fire) return Swal.fire({ icon:'error', title:'حدث خطأ', text, confirmButtonText:'حسناً' });
  alert(text);
}
function swalSuccess(text){
  if (window.Swal && Swal.fire) return Swal.fire({ icon:'success', title:'تم بنجاح', text, timer:1600, showConfirmButton:false });
  alert(text);
}
async function swalConfirm({ title, text, confirmText='تأكيد', cancelText='إلغاء' }){
  if (window.Swal && Swal.fire){
    const res = await Swal.fire({
      icon:'warning', title, text, showCancelButton:true,
      confirmButtonText: confirmText, cancelButtonText: cancelText,
      reverseButtons: true
    });
    return res.isConfirmed;
  }
  return confirm(text || title);
}

/*===============================================
  Rendering
================================================*/

/** اختصار limits_json إلى صيغة قصيرة: key:value[, key2:value2] */
function summarizeLimits(limitsJsonString) {
  if (!limitsJsonString) return '';
  try {
    const obj = (typeof limitsJsonString === 'string') ? JSON.parse(limitsJsonString) : limitsJsonString;
    if (!obj || typeof obj !== 'object') return '';
    const entries = Object.entries(obj);
    if (!entries.length) return '';
    const parts = entries.slice(0, 2).map(([k, v]) => `${k}:${Array.isArray(v) ? v.length : (typeof v === 'object' ? '...' : v)}`);
    return parts.join(', ') + (entries.length > 2 ? '…' : '');
  } catch (_){ return ''; }
}

function render() {
  const grid = el.plansGrid;
  const q = state.filter.q.trim().toLowerCase();
  const showInactive = state.filter.showInactive;

  const plans = state.plans.filter(p => {
    if (!showInactive && String(p.is_active) !== '1') return false;
    if (q && !String(p.name || '').toLowerCase().includes(q)) return false;
    return true;
  });

  if (!plans.length) {
    grid.innerHTML = `<div class="upg-msg">لا توجد خطط مطابقة.</div>`;
    return;
  }

  const html = plans.map(p => {
    const feats = state.featuresByPlan[p.id] || [];
    const chips = feats.length
      ? `<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3">
          ${feats.map(f => {
            const lim = summarizeLimits(f.limits_json);
            return `
              <div class="bg-slate-50 border border-slate-100 rounded-xl p-3 flex flex-col gap-2 transition-colors hover:border-cyan-200" data-feature-id="${f.id}">
                <div class="flex items-start gap-2 text-sm font-semibold text-slate-700">
                  <div class="bg-cyan-100 text-cyan-600 rounded-full w-5 h-5 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fas fa-check text-[10px]"></i></div>
                  <div>
                    ${escapeHtml(f.feature_key)}
                    ${lim ? `<div class="text-xs text-slate-400 font-normal mt-0.5">${escapeHtml(lim)}</div>` : ''}
                  </div>
                </div>
                <div class="flex items-center gap-2 mt-auto pt-1">
                  <button class="text-xs bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 px-2.5 py-1 rounded-lg transition-colors flex-1 text-center font-semibold outline-none" data-edit-feature="${f.id}"><i class="fas fa-pen"></i> تعديل</button>
                  <button class="text-xs bg-white border border-red-100 hover:bg-red-50 text-red-500 px-2.5 py-1 rounded-lg transition-colors flex-[0.5] text-center outline-none" data-del-feature="${f.id}" title="حذف"><i class="fas fa-trash"></i></button>
                </div>
              </div>`;
          }).join('')}
        </div>`
      : `<div class="bg-slate-50 text-slate-400 text-sm font-semibold p-3 rounded-xl mt-3 border border-slate-100 text-center">لا توجد مزايا مخصصة لهذه الخطة.</div>`;

    const price = (p.price == null) ? '—' : String(p.price);
    const duration = (p.default_duration_days ?? p.duration_days ?? null);
    const durationHtml = `<div class="text-sm font-semibold text-slate-500">المدة: ${duration == null ? '—' : escapeHtml(String(duration))} يوم</div>`;

    // لون الخلفية إذا كانت غير نشطة
    const inactiveFilter = String(p.is_active) !== '1' ? 'grayscale opacity-75' : '';

    return `
      <article class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:shadow-md transition-all flex flex-col h-full ${inactiveFilter} upg-plan" data-plan-id="${p.id}">
        
        <div class="flex items-start justify-between border-b border-slate-100 pb-3 mb-3">
          <div>
            <h3 class="text-lg font-black text-slate-800">${escapeHtml(p.name)}</h3>
            <span class="inline-block bg-slate-100 text-slate-600 text-[11px] font-bold px-2 py-0.5 rounded-md mt-1">Level ${escapeHtml(p.level)}</span>
          </div>
          <div class="text-left">
            <div class="text-xl font-black text-cyan-600">${escapeHtml(price)}<span class="text-xs text-cyan-700 ml-1">JOD</span></div>
             ${durationHtml}
          </div>
        </div>

        <div class="flex-1">
          <h4 class="text-sm font-bold text-slate-800">المزايا (${feats.length}):</h4>
          ${chips}
        </div>

        <div class="mt-5 pt-4 border-t border-slate-50 flex items-center gap-2 flex-wrap">
          <button class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-3 py-1.5 rounded-lg text-sm font-bold transition-colors flex-1 outline-none" data-add-feature="${p.id}"><i class="fas fa-plus"></i> إضافة ميزة</button>
          <button class="bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-bold transition-colors outline-none" data-edit-plan="${p.id}"><i class="fas fa-pen"></i> تعديل</button>
          <button class="bg-white hover:bg-red-50 border border-red-100 text-red-500 px-3 py-1.5 rounded-lg text-sm font-bold transition-colors flex-shrink-0 outline-none" data-del-plan="${p.id}"><i class="fas fa-trash"></i></button>
        </div>
      </article>
    `;
  }).join('');

  grid.innerHTML = html;
}

/*===============================================
  Data Loading (with normalization)
================================================*/
async function loadData() {
  const url = `${API.list}?show_inactive=${state.filter.showInactive ? 1 : 0}&q=${encodeURIComponent(state.filter.q)}`;
  const j = await jsonFetch(url);
  const data = j?.data || {};

  // تطبيع plan ids
  state.plans = (data.plans || []).map(p => ({
    ...p,
    id: Number(p.id),
    level: Number(p.level),
    default_duration_days: p.default_duration_days != null ? Number(p.default_duration_days) : p.default_duration_days,
  }));

  // تطبيع featuresByPlan
  const raw = data.features || {};
  state.featuresByPlan = {};
  Object.keys(raw).forEach(k => {
    const pid = Number(k);
    state.featuresByPlan[pid] = (raw[k] || []).map(f => ({
      ...f,
      id: Number(f.id),
      plan_id: f.plan_id != null ? Number(f.plan_id) : pid
    }));
  });

  render();
}

/*===============================================
  Modals Core
================================================*/
function openModal(sel){
  const m = qs(sel);
  if(!m) return;
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function closeModal(sel){
  const m = qs(sel);
  if(!m) return;
  m.classList.add('hidden');
  m.classList.remove('flex');
}

/*===============================================
  Repeater Utilities (Create & Feature modals)
================================================*/
function makeFeatureRow({ key='', limits='' } = {}, removable = true) {
  const row = document.createElement('div');
  row.className = 'feat-row grid grid-cols-1 sm:grid-cols-[1fr_1.5fr_auto] gap-3 items-start bg-slate-50 p-3 rounded-xl border border-slate-100';
  row.innerHTML = `
    <div class="space-y-1">
      <label class="text-xs font-bold text-slate-700">feature_key</label>
      <input class="feat-key w-full outline-none border border-slate-200 rounded-lg px-3 py-2 text-sm focus:border-cyan-500 font-mono text-slate-800" placeholder="مثال: ai_quota" value="${escapeHtml(key)}">
    </div>
    <div class="space-y-1">
      <label class="text-xs font-bold text-slate-700">limits_json (JSON)</label>
      <textarea class="feat-limits w-full outline-none border border-slate-200 rounded-lg px-3 py-2 text-sm focus:border-cyan-500 font-mono text-slate-800" rows="1" placeholder='{"messages_per_day": 20}'>${escapeHtml(limits)}</textarea>
    </div>
    <div class="pt-6">
      ${removable ? `<button class="bg-white hover:bg-red-50 border border-red-100 text-red-500 px-3 py-2 rounded-lg transition-colors outline-none h-[38px] flex items-center justify-center" data-remove-row title="إزالة الصف"><i class="fas fa-trash"></i></button>` : `<span></span>`}
    </div>
  `;
  row.addEventListener('click', e => {
    if (e.target.closest('[data-remove-row]')) row.remove();
  });
  return row;
}

function collectRows(container) {
  const rows = qsa('.feat-row', container);
  return rows.map(r => {
    const key = qs('.feat-key', r)?.value.trim() || '';
    const lim = qs('.feat-limits', r)?.value.trim() || '';
    return { feature_key: key, limits_json: lim === '' ? null : lim };
  }).filter(r => r.feature_key !== '');
}

/*===============================================
  Plan Modal (Create / Edit) + edit/delete features inside modal
================================================*/
function renderEditFeaturesList(planId){
  const list = qs('#editFeaturesList');
  const feats = state.featuresByPlan[Number(planId)] || [];
  list.innerHTML = feats.length
    ? `
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2" id="editFeaturesChips">
        ${feats.map(f => {
          const lim = summarizeLimits(f.limits_json);
          return `
            <div class="bg-white border border-slate-200 rounded-xl p-3 flex flex-col gap-2 transition-colors hover:border-cyan-200" data-feature-id="${f.id}">
              <div class="flex items-start gap-2 text-sm font-semibold text-slate-700">
                <div class="bg-emerald-100 text-emerald-600 rounded-full w-5 h-5 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fas fa-check text-[10px]"></i></div>
                <div>
                  ${escapeHtml(f.feature_key)}
                  ${lim ? `<div class="text-xs text-slate-400 font-normal mt-0.5">${escapeHtml(lim)}</div>` : ''}
                </div>
              </div>
              <div class="flex items-center gap-2 mt-auto pt-2 border-t border-slate-50">
                <button class="text-xs bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-600 px-3 py-1.5 rounded-lg transition-colors flex-1 text-center font-bold outline-none" data-edit-feature="${f.id}"><i class="fas fa-pen"></i> تعديل</button>
                <button class="text-xs bg-white border border-red-100 hover:bg-red-50 text-red-500 px-3 py-1.5 rounded-lg transition-colors flex-[0.5] text-center font-bold outline-none" data-del-feature="${f.id}" title="حذف"><i class="fas fa-trash"></i></button>
              </div>
            </div>`;
        }).join('')}
      </div>`
    : `<div class="bg-slate-50 p-3 text-center text-sm font-semibold text-slate-400 rounded-xl mt-2 border border-slate-100">لا مزايا مخصصة لهذه الخطة للمراجعة.</div>`;

  qs('#editFeaturesBlock').classList.remove('hidden');
}

function openPlanModalForCreate() {
  qs('#planModalTitle').textContent = 'إضافة خطة';
  qs('#planId').value = '';
  qs('#planName').value = '';
  qs('#planLevel').value = 1;
  qs('#planDuration').value = 30;
  qs('#planPrice').value = '';
  qs('#planActive').checked = true;

  const blk = qs('#createFeaturesBlock');
  const rows = qs('#createFeaturesRows');
  blk.classList.remove('hidden');
  rows.innerHTML = '';
  rows.appendChild(makeFeatureRow({}, false));
  rows.appendChild(makeFeatureRow());
  rows.appendChild(makeFeatureRow());

  qs('#editFeaturesBlock').classList.add('hidden');

  openModal('#planModal');
}

function openPlanModalForEdit(plan) {
  try {
    qs('#planModalTitle').textContent = 'تعديل خطة';
    qs('#planId').value = plan.id;
    qs('#planName').value = plan.name ?? '';
    qs('#planLevel').value = plan.level ?? 1;
    qs('#planDuration').value = plan.default_duration_days ?? 30;
    qs('#planPrice').value = (plan.price == null ? '' : plan.price);
    qs('#planActive').checked = String(plan.is_active ?? '1') === '1';

    // إخفاء بلوك الإنشاء، وإظهار بلوك المزايا مع أزرار تعديل/حذف
    qs('#createFeaturesBlock').classList.add('hidden');
    renderEditFeaturesList(plan.id);

    openModal('#planModal');
  } catch (err) {
    swalError(err.message || 'تعذّر فتح نافذة تعديل الخطة');
  }
}

/** تفويض أحداث داخل مودال تعديل الخطة (تحرير/حذف المزايا) */
function bindEditModalDelegation() {
  const container = qs('#planModal')?.querySelector('.upg-modal__body');
  if (!container) return;

  container.addEventListener('click', async (e) => {
    try {
      const editBtn = e.target.closest('[data-edit-feature]');
      const delBtn  = e.target.closest('[data-del-feature]');
      if (!editBtn && !delBtn) return;

      const featureId = Number((editBtn || delBtn).getAttribute('data-edit-feature') || (editBtn || delBtn).getAttribute('data-del-feature'));
      const planId = Number(qs('#planId').value || 0);
      if (!planId || !featureId) return;

      const feature = (state.featuresByPlan[planId] || []).find(x => Number(x.id) === featureId);
      if (!feature) { swalError('لم يتم العثور على الميزة'); return; }

      if (editBtn) {
        openFeatureModalEdit(planId, feature);
        return;
      }

      if (delBtn) {
        const ok = await swalConfirm({
          title: 'حذف الميزة؟',
          text: 'سيتم حذف هذه الميزة فقط.',
          confirmText: 'نعم، احذف',
          cancelText: 'إلغاء'
        });
        if (!ok) return;

        await jsonFetch(API.featDelete(featureId), { method:'DELETE' });

        // حدّث الحالة محليًا ثم أعد رسم قائمة المزايا داخل المودال
        state.featuresByPlan[planId] = (state.featuresByPlan[planId] || []).filter(x => Number(x.id) !== featureId);
        renderEditFeaturesList(planId);
        // حدّث أيضًا بطاقات الصفحة
        render();
        swalSuccess('تم حذف الميزة');
      }
    } catch (err) {
      swalError(err.message || 'حدث خطأ أثناء معالجة الميزة');
    }
  });
}

/*===============================================
  Feature Modal (Add multiple / Edit single)
================================================*/
function openFeatureModalAdd(planId) {
  qs('#featureModalTitle').textContent = 'إضافة ميزات';
  qs('#featureId').value = '';
  qs('#featurePlanId').value = planId;
  const wrap = qs('#featureRows');
  wrap.innerHTML = '';
  wrap.appendChild(makeFeatureRow({}, false));
  openModal('#featureModal');
}

function openFeatureModalEdit(planId, feature) {
  qs('#featureModalTitle').textContent = 'تعديل ميزة';
  qs('#featureId').value = feature.id;
  qs('#featurePlanId').value = planId;
  const wrap = qs('#featureRows');
  wrap.innerHTML = '';
  wrap.appendChild(makeFeatureRow({
    key: feature.feature_key,
    limits: (typeof feature.limits_json === 'string') ? feature.limits_json
            : (feature.limits_json ? JSON.stringify(feature.limits_json, null, 2) : '')
  }, false));
  openModal('#featureModal');
}

/*===============================================
  Bindings & Events
================================================*/
function bindCommon() {
  // إغلاق المودالات
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-close]');
    if (!btn) return;
    e.preventDefault();
    closeModal(btn.getAttribute('data-close'));
  });

  // فلترة
  el.chkShowInactive.addEventListener('change', () => {
    state.filter.showInactive = el.chkShowInactive.checked;
    loadData();
  });

  el.txtSearch.addEventListener('input', () => {
    state.filter.q = el.txtSearch.value;
    render();
  });

  // فتح إنشاء خطة
  el.btnNewPlan.addEventListener('click', () => openPlanModalForCreate());

  // إضافة صف في create-plan repeater
  qs('#btnAddCreateFeature').addEventListener('click', () => {
    qs('#createFeaturesRows').appendChild(makeFeatureRow());
  });

  // إضافة صف في feature modal repeater
  qs('#btnAddFeatureRow').addEventListener('click', () => {
    qs('#featureRows').appendChild(makeFeatureRow());
  });
}

function bindGridDelegation() {
  el.plansGrid.addEventListener('click', async (e) => {
    try {
      const addBtn = e.target.closest('[data-add-feature]');
      const editPlanBtn = e.target.closest('[data-edit-plan]');
      const delPlanBtn = e.target.closest('[data-del-plan]');
      const editFeatBtn = e.target.closest('[data-edit-feature]');
      const delFeatBtn = e.target.closest('[data-del-feature]');

      if (addBtn) {
        const pid = Number(addBtn.getAttribute('data-add-feature'));
        openFeatureModalAdd(pid);
        return;
      }
      if (editPlanBtn) {
        const pid = Number(editPlanBtn.getAttribute('data-edit-plan'));
        const p = state.plans.find(x => Number(x.id) === pid);
        if (p) openPlanModalForEdit(p);
        else swalError('لم يتم العثور على الخطة');
        return;
      }
      if (delPlanBtn) {
        const pid = Number(delPlanBtn.getAttribute('data-del-plan'));
        const ok = await swalConfirm({
          title: 'حذف الخطة؟',
          text: 'سيتم حذف الخطة وجميع مزاياها.',
          confirmText: 'نعم، احذف',
          cancelText: 'إلغاء'
        });
        if (ok) {
          await jsonFetch(API.remove(pid), { method:'DELETE' });
          await loadData();
          swalSuccess('تم حذف الخطة');
        }
        return;
      }
      if (editFeatBtn) {
        const fid = Number(editFeatBtn.getAttribute('data-edit-feature'));
        const planEl = e.target.closest('.upg-plan');
        const planId = planEl ? Number(planEl.getAttribute('data-plan-id')) : NaN;
        const f = (state.featuresByPlan[planId] || []).find(x => Number(x.id) === fid);
        if (f) openFeatureModalEdit(planId, f);
        else swalError('لم يتم العثور على الميزة');
        return;
      }
      if (delFeatBtn) {
        const fid = Number(delFeatBtn.getAttribute('data-del-feature'));
        const ok = await swalConfirm({
          title: 'حذف الميزة؟',
          text: 'سيتم حذف هذه الميزة فقط.',
          confirmText: 'نعم، احذف',
          cancelText: 'إلغاء'
        });
        if (ok) {
          await jsonFetch(API.featDelete(fid), { method:'DELETE' });
          await loadData();
          swalSuccess('تم حذف الميزة');
        }
        return;
      }
    } catch (err) {
      swalError(err.message || 'حدث خطأ غير متوقع');
    }
  });
}

/* حفظ JSON String بأمان */
function parseJsonOrThrow(str) {
  if (str === null || str === '' || str === undefined) return null;
  try { return JSON.parse(str); } catch (_){ throw new Error('limits_json ليس JSON صالحًا'); }
}

/* Payload إنشاء خطة + ميزات */
function getCreatePlanPayloadWithFeatures() {
  const features = collectRows(qs('#createFeaturesRows')).map(r => ({
    feature_key: r.feature_key,
    limits_json: r.limits_json ? parseJsonOrThrow(r.limits_json) : null
  }));
  return {
    name: qs('#planName').value.trim(),
    level: parseInt(qs('#planLevel').value, 10) || 1,
    default_duration_days: parseInt(qs('#planDuration').value, 10) || 30,
    price: qs('#planPrice').value === '' ? null : parseFloat(qs('#planPrice').value),
    is_active: qs('#planActive').checked ? 1 : 0,
    features,
  };
}

/* Payload تعديل الخطة (حقول فقط) */
function getEditPlanPayloadOnlyFields() {
  return {
    name: qs('#planName').value.trim(),
    level: parseInt(qs('#planLevel').value, 10) || 1,
    default_duration_days: parseInt(qs('#planDuration').value, 10) || 30,
    price: qs('#planPrice').value === '' ? null : parseFloat(qs('#planPrice').value),
    is_active: qs('#planActive').checked ? 1 : 0,
  };
}

/* Payload صفوف ميزات من مودال الإضافة المتعددة */
function getFeatureRowsPayload(planId) {
  const arr = collectRows(qs('#featureRows')).map(r => ({
    feature_key: r.feature_key,
    limits_json: r.limits_json ? parseJsonOrThrow(r.limits_json) : null
  }));
  return { planId, rows: arr };
}

function bindModalActions() {
  // حفظ خطة (Create أو Edit)
  qs('#btnSavePlan').addEventListener('click', async () => {
    const id = qs('#planId').value ? Number(qs('#planId').value) : null;
    try {
      if (!id) {
        const payload = getCreatePlanPayloadWithFeatures();
        if (!payload.name) throw new Error('اسم الخطة مطلوب');
        await jsonFetch(API.create, { method:'POST', body: JSON.stringify(payload) });
        swalSuccess('تم إنشاء الخطة');
      } else {
        const payload = getEditPlanPayloadOnlyFields();
        if (!payload.name) throw new Error('اسم الخطة مطلوب');
        await jsonFetch(API.update(id), { method:'PUT', body: JSON.stringify(payload) });
        swalSuccess('تم تحديث الخطة');
      }
      closeModal('#planModal');
      await loadData();
    } catch (err) {
      swalError(err.message);
    }
  });

  // حفظ ميزات (إضافة متعددة أو تعديل مفرد)
  qs('#btnSaveFeature').addEventListener('click', async () => {
    try {
      const fid = qs('#featureId').value ? Number(qs('#featureId').value) : null;
      const pid = qs('#featurePlanId').value ? Number(qs('#featurePlanId').value) : null;

      if (!fid) {
        const { rows } = getFeatureRowsPayload(pid);
        if (!rows.length) throw new Error('لا توجد ميزات لإضافتها');
        await jsonFetch(API.featAdd(pid), { method:'POST', body: JSON.stringify(rows) });
        swalSuccess('تم إضافة الميزات');
      } else {
        const r = collectRows(qs('#featureRows'))[0];
        if (!r || !r.feature_key) throw new Error('feature_key مطلوب');
        const payload = {
          feature_key: r.feature_key,
          limits_json: r.limits_json ? parseJsonOrThrow(r.limits_json) : null
        };
        await jsonFetch(API.featUpdate(fid), { method:'PUT', body: JSON.stringify(payload) });
        swalSuccess('تم تحديث الميزة');
      }

      closeModal('#featureModal');
      // تحديث مصدر البيانات ثم تحديث قائمة المزايا داخل مودال الخطة إن كان مفتوحًا
      await loadData();
      const planModalOpen = !qs('#planModal')?.hasAttribute('hidden');
      if (planModalOpen) {
        const planId = Number(qs('#planId').value || 0);
        if (planId) renderEditFeaturesList(planId);
      }
    } catch (err) {
      swalError(err.message);
    }
  });
}

/*===============================================
  Init
================================================*/
export async function init(){
  // Cleanup previously appended modals to avoid duplicates in SPA
  document.querySelectorAll('body > #planModal, body > #featureModal').forEach(el => el.remove());

  // Move newly rendered modals to the body to escape restricted z-index contexts (like .main-content)
  const pModal = document.querySelector('[data-component="admin-plans"] #planModal');
  const fModal = document.querySelector('[data-component="admin-plans"] #featureModal');
  if (pModal) document.body.appendChild(pModal);
  if (fModal) document.body.appendChild(fModal);

  el = {
    page: qs('.upg-page[data-component="admin-plans"]') || qs('[data-component="admin-plans"]'),
    plansGrid: qs('#plansGrid'),
    btnNewPlan: qs('#btnNewPlan'),
    chkShowInactive: qs('#chkShowInactive'),
    txtSearch: qs('#txtSearch'),
  };
  bindCommon();
  bindGridDelegation();
  bindEditModalDelegation(); // التقاط تعديل/حذف داخل مودال الخطة
  bindModalActions();
  await loadData();
}
