/* global showSuccess, showError, showWarning, showConfirm */
/**
 * إدارة أدوات الذكاء الاصطناعي (الأدمن فقط).
 * يدير جدولَي ai_job_types (حقول آمنة) و ai_tool_placements (إسناد كامل).
 * تفاصيل الأداة تُعرض في قائمة جانبية (drawer) شاملة: التوجيه الأساسي، الأيقونات،
 * الفورم، والإسناد (المهام/الجامعات/المساقات) — مع نموذج تعديل الحقول الآمنة.
 */

const API = '/admins/api/ai-tools';

const state = {
  tools: [],
  toolsById: {},
  placements: [],
  placementsById: {},
  taskTypes: [],
};

// الأداة المعروضة حالياً في القائمة الجانبية (لتحرير إسناداتها)
let currentTool = null;

/* ---------- helpers ---------- */
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

const notify = {
  ok:   (m) => (typeof showSuccess === 'function' ? showSuccess(m, undefined, true) : console.log(m)),
  err:  (m) => (typeof showError === 'function' ? showError(m) : alert(m)),
  warn: (m) => (typeof showWarning === 'function' ? showWarning(m) : alert(m)),
};

function confirmDelete(onConfirm) {
  if (typeof showConfirm === 'function') {
    showConfirm({ title: 'حذف الإسناد؟', text: 'لا يمكن التراجع عن هذه العملية.', confirmText: 'نعم، احذف', cancelText: 'إلغاء', onConfirm });
  } else if (confirm('حذف الإسناد؟')) {
    onConfirm();
  }
}

async function api(url, opts = {}) {
  const res = await fetch(url, {
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { Accept: 'application/json', ...(opts.body ? { 'Content-Type': 'application/json' } : {}), ...(opts.headers || {}) },
    ...opts,
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok || json.ok === false) {
    throw new Error(json.message || (json.messages && Object.values(json.messages)[0]) || json.error || `HTTP ${res.status}`);
  }
  return json;
}

function showModal(id) { const m = $(id); if (m) m.style.display = 'flex'; }
function hideModal(id) { const m = $(id); if (m) m.style.display = 'none'; }

/** أيقونة FontAwesome/Bootstrap مع عرض السلسلة الخام. */
function iconHtml(icon) {
  const s = String(icon == null ? '' : icon).trim();
  if (!s) return '<span class="aitd-empty">—</span>';
  let cls = s;
  if (/^fa-/.test(s)) cls = 'fas ' + s;
  else if (/^bi-/.test(s)) cls = 'bi ' + s;
  return `<i class="${esc(cls)}"></i> <span class="aitd-mono" style="font-size:.74rem;color:var(--text-muted);">${esc(s)}</span>`;
}

const chip = (on, onText, offText) => on
  ? `<span class="aitd-chip aitd-chip--on"><i class="fas fa-check"></i> ${esc(onText)}</span>`
  : `<span class="aitd-chip aitd-chip--off"><i class="fas fa-xmark"></i> ${esc(offText)}</span>`;

/* ---------- init ---------- */
export function init() {
  bindTabs();
  bindModalClose();
  bindDrawerClose();
  bindPlacementButtons();
  loadTools();
  loadTaskTypes().then(loadPlacements);
}

function bindTabs() {
  document.querySelectorAll('.ait-tab').forEach((btn) => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;
      document.querySelectorAll('.ait-tab').forEach((b) => {
        const active = b === btn;
        b.classList.toggle('is-active', active);
        b.style.background = active ? '#2E75B6' : '#e5e7eb';
        b.style.color = active ? '#fff' : '#1f2933';
      });
      $('aitToolsPanel').style.display = tab === 'tools' ? '' : 'none';
      $('aitPlacementsPanel').style.display = tab === 'placements' ? '' : 'none';
    });
  });
}

function bindModalClose() {
  document.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => hideModal(b.dataset.close)));
  ['aiPlacementModal'].forEach((id) => {
    const m = $(id);
    if (m) m.addEventListener('click', (e) => { if (e.target === m) hideModal(id); });
  });
}

/* ---------- Drawer ---------- */
function openDrawer() { const d = $('aiToolDrawer'); if (d) d.hidden = false; }
function closeDrawer() { const d = $('aiToolDrawer'); if (d) d.hidden = true; }

function bindDrawerClose() {
  const d = $('aiToolDrawer');
  if (!d) return;
  d.querySelectorAll('[data-close-drawer]').forEach((b) => b.addEventListener('click', closeDrawer));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && d && !d.hidden) closeDrawer(); });
}

/* ---------- Tools ---------- */
async function loadTools() {
  try {
    const j = await api(`${API}/tools`);
    state.tools = j.tools || [];
    state.toolsById = {};
    state.tools.forEach((t) => { state.toolsById[t.id] = t; });
    renderTools();
  } catch (e) {
    const tb = $('aiToolsTbody');
    if (tb) tb.innerHTML = `<tr><td colspan="7" style="padding:18px;text-align:center;color:#c00;">تعذّر التحميل: ${esc(e.message)}</td></tr>`;
  }
}

function renderTools() {
  const tb = $('aiToolsTbody');
  if (!tb) return;
  if (!state.tools.length) { tb.innerHTML = '<tr><td colspan="7" style="padding:18px;text-align:center;color:#888;">لا توجد أدوات.</td></tr>'; return; }
  tb.innerHTML = state.tools.map((t) => `
    <tr class="ait-toolrow" data-id="${t.id}" style="border-bottom:1px solid #eee;cursor:pointer;">
      <td style="padding:10px;font-family:monospace;direction:ltr;text-align:left;">${esc(t.type_key)}</td>
      <td style="padding:10px;">${esc(t.title)}</td>
      <td style="padding:10px;font-size:0.85rem;color:#555;">${esc(t.provider)} / ${esc(t.model_name)}</td>
      <td style="padding:10px;">${t.points_cost == null ? '—' : esc(t.points_cost)}</td>
      <td style="padding:10px;">${t.is_enabled ? '<span style="color:#15803d;font-weight:700;">مُفعّلة</span>' : '<span style="color:#b91c1c;">معطّلة</span>'}</td>
      <td style="padding:10px;">${t.is_admin_only
        ? '<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;background:var(--primary-50);color:var(--primary-700);border:1px solid var(--primary-300);font-weight:700;font-size:0.78rem;"><i class="fas fa-user-shield"></i> خاص بالأدمن</span>'
        : '<span style="color:#888;font-size:0.82rem;">الطلاب</span>'}</td>
      <td style="padding:10px;"><button type="button" class="ait-edit-tool" data-id="${t.id}" style="padding:6px 12px;border:none;border-radius:8px;background:#eef2ff;color:#2E75B6;cursor:pointer;font-weight:700;"><i class="fas fa-circle-info"></i> التفاصيل</button></td>
    </tr>`).join('');
  tb.querySelectorAll('.ait-toolrow').forEach((row) => row.addEventListener('click', (e) => {
    if (e.target.closest('a')) return;
    openToolDrawer(Number(row.dataset.id));
  }));
}

async function openToolDrawer(id) {
  openDrawer();
  const titleEl = $('aitDrawerTitle');
  const body = $('aitDrawerBody');
  const cached = state.toolsById[id];
  if (titleEl) titleEl.textContent = cached ? cached.title : 'تفاصيل الأداة';
  if (body) body.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:30px;"><i class="fas fa-spinner fa-spin"></i> جارٍ تحميل التفاصيل…</div>';

  // أنواع المهام مطلوبة لقوائم تحرير الإسناد داخل الدروير
  if (!state.taskTypes.length) { try { await loadTaskTypes(); } catch { /* تجاهل */ } }

  let t;
  try {
    const j = await api(`${API}/tools/${id}`);
    t = j.tool;
  } catch (e) {
    if (body) body.innerHTML = `<div style="color:#c00;padding:20px;">تعذّر تحميل التفاصيل: ${esc(e.message)}</div>`;
    return;
  }
  if (!t) return;
  if (titleEl) titleEl.textContent = t.title || t.type_key;
  renderDrawerBody(t);
}

function renderDrawerBody(t) {
  const body = $('aitDrawerBody');
  if (!body) return;
  currentTool = t;

  const modeLabels = { ignore: 'تجاهل', append: 'إلحاق بعد', prepend: 'إضافة قبل', replace: 'استبدال' };
  const ttOptions = (selId) => '<option value="0">— كل أنواع المهام —</option>' +
    state.taskTypes.map((tt) => `<option value="${tt.id}" ${Number(selId) === tt.id ? 'selected' : ''}>${esc(tt.name)} (#${tt.id})</option>`).join('');

  // ── معلومات عامة ──
  const generalSec = `
    <div class="aitd-sec">
      <div class="aitd-sec__h"><i class="fas fa-circle-info"></i> معلومات عامة</div>
      <div class="aitd-sec__b">
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:14px;">
          <div class="aitd-iconprev"><i class="${/^fa-/.test(t.panel_icon) ? 'fas ' + esc(t.panel_icon) : (/^bi-/.test(t.panel_icon) ? 'bi ' + esc(t.panel_icon) : (esc(t.panel_icon) || 'fas fa-robot'))}"></i></div>
          <div style="min-width:0;">
            <div style="font-weight:800;color:var(--text-strong);font-size:1rem;">${esc(t.title)}</div>
            <div class="aitd-mono" style="font-size:.78rem;color:var(--text-muted);">${esc(t.type_key)}</div>
          </div>
        </div>
        <div class="aitd-grid">
          <div class="aitd-kv aitd-kv--full">
            <span class="aitd-kv__l"><i class="fas fa-align-right"></i> الوصف</span>
            <span class="aitd-kv__v">${esc(t.description) || '<span class="aitd-empty">—</span>'}</span>
          </div>
          <div class="aitd-kv">
            <span class="aitd-kv__l"><i class="fas fa-microchip"></i> المزوّد / النموذج</span>
            <span class="aitd-kv__v aitd-mono">${esc(t.provider)} / ${esc(t.model_name)}</span>
          </div>
          <div class="aitd-kv">
            <span class="aitd-kv__l"><i class="fas fa-coins"></i> تكلفة النقاط</span>
            <span class="aitd-kv__v">${t.points_cost == null ? '<span class="aitd-empty">مجانية</span>' : esc(t.points_cost) + ' نقطة'}</span>
          </div>
          <div class="aitd-kv">
            <span class="aitd-kv__l"><i class="fas fa-toggle-on"></i> الحالة</span>
            <span class="aitd-kv__v">${chip(t.is_enabled, 'مُفعّلة', 'معطّلة')}</span>
          </div>
          <div class="aitd-kv">
            <span class="aitd-kv__l"><i class="fas fa-user-shield"></i> النطاق</span>
            <span class="aitd-kv__v">${t.is_admin_only
              ? '<span class="aitd-chip aitd-chip--admin"><i class="fas fa-user-shield"></i> خاص بالأدمن</span>'
              : '<span class="aitd-chip aitd-chip--muted">متاحة للطلاب</span>'}</span>
          </div>
          <div class="aitd-kv">
            <span class="aitd-kv__l"><i class="fas fa-diagram-project"></i> وضع موجّه المهمة</span>
            <span class="aitd-kv__v">${esc(modeLabels[t.task_prompt_mode] || t.task_prompt_mode)}</span>
          </div>
          <div class="aitd-kv">
            <span class="aitd-kv__l"><i class="fas fa-sliders"></i> يحتاج سياق المهمة</span>
            <span class="aitd-kv__v">${chip(t.needs_task_context, 'نعم', 'لا')}</span>
          </div>
          <div class="aitd-kv">
            <span class="aitd-kv__l"><i class="fas fa-user-graduate"></i> يحتاج التخصص</span>
            <span class="aitd-kv__v">${chip(t.needs_specialty, 'نعم', 'لا')}</span>
          </div>
          <div class="aitd-kv">
            <span class="aitd-kv__l"><i class="fas fa-image"></i> أيقونة الأداة</span>
            <span class="aitd-kv__v">${iconHtml(t.panel_icon)}</span>
          </div>
        </div>
      </div>
    </div>`;

  // ── الإعداد التقني ──
  const techSec = `
    <div class="aitd-sec">
      <div class="aitd-sec__h"><i class="fas fa-gears"></i> الإعداد التقني</div>
      <div class="aitd-sec__b">
        <div class="aitd-grid">
          <div class="aitd-kv aitd-kv--full">
            <span class="aitd-kv__l"><i class="fas fa-code"></i> صانع التوجيه (PromptMaker)</span>
            <span class="aitd-kv__v aitd-mono">${esc(t.prompt_maker_class) || '<span class="aitd-empty">—</span>'}</span>
          </div>
          <div class="aitd-kv aitd-kv--full">
            <span class="aitd-kv__l"><i class="fas fa-file-word"></i> قالب الإخراج (template)</span>
            <span class="aitd-kv__v aitd-mono">${esc(t.template_path) || '<span class="aitd-empty">لا يوجد</span>'}</span>
          </div>
          <div class="aitd-kv">
            <span class="aitd-kv__l"><i class="fas fa-file-export"></i> نوع الناتج</span>
            <span class="aitd-kv__v">${esc(t.result_type) || '<span class="aitd-empty">—</span>'}</span>
          </div>
          <div class="aitd-kv">
            <span class="aitd-kv__l"><i class="fas fa-clock"></i> آخر تحديث</span>
            <span class="aitd-kv__v aitd-mono" style="font-size:.78rem;">${esc(t.updated_at) || '—'}</span>
          </div>
        </div>
      </div>
    </div>`;

  // ── التوجيه الأساسي (system_prompt) ──
  const promptSec = `
    <div class="aitd-sec">
      <div class="aitd-sec__h"><i class="fas fa-scroll"></i> التوجيه الأساسي (System Prompt)</div>
      <div class="aitd-sec__b">
        ${t.system_prompt ? `<div class="aitd-prompt">${esc(t.system_prompt)}</div>` : '<div class="aitd-empty">لا يوجد توجيه أساسي محفوظ.</div>'}
      </div>
    </div>`;

  // ── فورم الطالب ──
  const sections = Array.isArray(t.form_sections) ? t.form_sections : [];
  let formInner = '';
  if (t.form_modal_title || t.form_submit_label) {
    formInner += `<div class="aitd-grid" style="margin-bottom:12px;">
      <div class="aitd-kv"><span class="aitd-kv__l"><i class="fas fa-window-maximize"></i> عنوان النافذة</span><span class="aitd-kv__v">${esc(t.form_modal_title) || '—'}</span></div>
      <div class="aitd-kv"><span class="aitd-kv__l"><i class="fas fa-paper-plane"></i> نص زر الإرسال</span><span class="aitd-kv__v">${esc(t.form_submit_label) || '—'}</span></div>
    </div>`;
  }
  if (sections.length) {
    formInner += sections.map((s) => `
      <div style="margin-bottom:10px;">
        <div style="font-weight:700;font-size:.84rem;color:var(--text-strong);margin-bottom:6px;"><i class="fas fa-layer-group" style="color:var(--primary);"></i> ${esc(s.title) || 'قسم'}</div>
        <div>${(s.fields || []).map((f) => `<span class="aitd-fld">${esc(f.label || f.key)}${f.required ? ' <span class="req">*</span>' : ''} <span class="aitd-mono" style="opacity:.6;">${esc(f.type)}</span></span>`).join('') || '<span class="aitd-empty">لا حقول</span>'}</div>
      </div>`).join('');
  } else if (!formInner) {
    formInner = '<div class="aitd-empty">لا يوجد فورم مُعرّف لهذه الأداة (قد تعتمد على رفع ملف أو سياق المهمة).</div>';
  }
  const formSec = `
    <div class="aitd-sec">
      <div class="aitd-sec__h"><i class="fas fa-keyboard"></i> فورم إدخال الطالب</div>
      <div class="aitd-sec__b">${formInner}</div>
    </div>`;

  // ── الإسناد بنوع المهمة (قابل للتحرير) ──
  const pls = Array.isArray(t.placements) ? t.placements : [];
  const plRows = pls.length ? pls.map((p) => `
    <div class="aitd-pl" data-pid="${p.id}">
      <div class="aitd-pl__top">
        <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;flex-wrap:wrap;">
          <span class="aitd-kv__l" style="margin:0;white-space:nowrap;"><i class="fas fa-list-check"></i> نوع المهمة:</span>
          <select class="aitd-pl-tt" data-pid="${p.id}" style="flex:1;min-width:150px;padding:6px 9px;border:1px solid var(--border-color);border-radius:8px;background:var(--surface-0);color:var(--text);font-family:inherit;font-size:.82rem;">
            ${ttOptions(p.task_type_id)}
          </select>
        </div>
        <div style="display:flex;gap:6px;flex:0 0 auto;">
          <button type="button" class="aitd-pl-save" data-pid="${p.id}" title="حفظ نوع المهمة" style="padding:6px 12px;border:none;border-radius:8px;background:var(--primary-600);color:var(--text-inverse);cursor:pointer;font-weight:700;font-size:.78rem;"><i class="fas fa-save"></i> حفظ</button>
          <button type="button" class="aitd-pl-del" data-pid="${p.id}" title="حذف الإسناد" style="padding:6px 10px;border:none;border-radius:8px;background:#fee2e2;color:#b91c1c;cursor:pointer;font-weight:700;"><i class="fas fa-trash"></i></button>
        </div>
      </div>
      <div class="aitd-pl__meta">
        ${chip(p.is_enabled, 'مُفعّل', 'معطّل')}
        <span class="aitd-pltag"><i class="fas fa-building-columns"></i> ${p.university_id ? esc(p.university_name || ('#' + p.university_id)) : 'كل الجامعات'}</span>
        <span class="aitd-pltag"><i class="fas fa-book"></i> ${p.course_id ? esc(p.course_name || ('#' + p.course_id)) : 'كل المساقات'}</span>
        <span class="aitd-pltag"><i class="fas fa-arrow-down-1-9"></i> ترتيب ${esc(p.button_order)}</span>
        ${p.button_label ? `<span class="aitd-pltag"><i class="fas fa-tag"></i> ${esc(p.button_label)}</span>` : ''}
        ${p.button_icon ? `<span class="aitd-pltag">${iconHtml(p.button_icon)}</span>` : ''}
        ${p.min_subscription_level ? `<span class="aitd-pltag"><i class="fas fa-crown"></i> اشتراك ${esc(p.min_subscription_level)}+</span>` : ''}
      </div>
    </div>`).join('') : '<div class="aitd-empty" style="margin-bottom:10px;">لا توجد إسنادات لهذه الأداة — اربطها بنوع مهمة أدناه لتظهر للطلاب.</div>';

  const plAddRow = `
    <div class="aitd-pl" style="border-style:dashed;background:var(--surface-0);">
      <div class="aitd-pl__top">
        <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;flex-wrap:wrap;">
          <span class="aitd-kv__l" style="margin:0;white-space:nowrap;color:var(--primary-700);"><i class="fas fa-plus-circle"></i> ربط بنوع مهمة:</span>
          <select id="aitdNewTt" style="flex:1;min-width:150px;padding:6px 9px;border:1px solid var(--primary-300);border-radius:8px;background:var(--surface-0);color:var(--text);font-family:inherit;font-size:.82rem;">
            ${ttOptions(0)}
          </select>
        </div>
        <button type="button" id="aitdLinkBtn" style="padding:6px 14px;border:none;border-radius:8px;background:var(--primary-600);color:var(--text-inverse);cursor:pointer;font-weight:700;font-size:.78rem;flex:0 0 auto;"><i class="fas fa-link"></i> ربط</button>
      </div>
      <div class="aitd-empty" style="font-size:.74rem;margin-top:6px;">يُنشئ إسناداً جديداً لهذه الأداة (كل الجامعات والمساقات). للتحكّم بالجامعة/المساق ونص الزر استخدم تبويب «الإسناد».</div>
    </div>`;

  const plSec = `
    <div class="aitd-sec">
      <div class="aitd-sec__h"><i class="fas fa-link"></i> الإسناد بنوع المهمة (${pls.length})</div>
      <div class="aitd-sec__b">${plRows}${plAddRow}</div>
    </div>`;

  // ── نموذج تعديل الحقول الآمنة ──
  const editSec = `
    <div class="aitd-sec">
      <div class="aitd-sec__h"><i class="fas fa-pen-to-square"></i> تعديل الحقول الآمنة</div>
      <div class="aitd-sec__b">
        <div class="aitd-form">
          <input type="hidden" id="tf_id" value="${esc(t.id)}">
          <div class="aitd-f--full"><label>العنوان</label><input id="tf_title" type="text" value="${esc(t.title)}"></div>
          <div class="aitd-f--full"><label>الوصف</label><textarea id="tf_description" rows="2">${esc(t.description)}</textarea></div>
          <div><label>تكلفة النقاط</label><input id="tf_points_cost" type="number" min="0" value="${t.points_cost == null ? '' : esc(t.points_cost)}"></div>
          <div><label>المزوّد</label>
            <select id="tf_provider">
              ${['gemini', 'openai', 'vertex'].map((p) => `<option value="${p}" ${t.provider === p ? 'selected' : ''}>${p}</option>`).join('')}
            </select>
          </div>
          <div><label>النموذج (model_name)</label><input id="tf_model_name" type="text" value="${esc(t.model_name)}"></div>
          <div><label>وضع موجّه المهمة</label>
            <select id="tf_task_prompt_mode">
              ${['ignore', 'append', 'prepend', 'replace'].map((m) => `<option value="${m}" ${t.task_prompt_mode === m ? 'selected' : ''}>${m}</option>`).join('')}
            </select>
          </div>
          <div class="aitd-chk"><input id="tf_is_enabled" type="checkbox" ${t.is_enabled ? 'checked' : ''}><label for="tf_is_enabled" style="margin:0;">مُفعّلة</label></div>
          <div class="aitd-chk"><input id="tf_needs_task_context" type="checkbox" ${t.needs_task_context ? 'checked' : ''}><label for="tf_needs_task_context" style="margin:0;">تحتاج سياق المهمة</label></div>
          <div class="aitd-chk"><input id="tf_needs_specialty" type="checkbox" ${t.needs_specialty ? 'checked' : ''}><label for="tf_needs_specialty" style="margin:0;">تحتاج التخصص</label></div>
          <div class="aitd-f--full" style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;border:1px solid var(--primary-300);background:var(--primary-50);border-radius:10px;">
            <input id="tf_is_admin_only" type="checkbox" style="margin-top:3px;" ${t.is_admin_only ? 'checked' : ''}>
            <label for="tf_is_admin_only" style="font-weight:700;font-size:0.85rem;color:var(--primary-700);margin:0;">
              <i class="fas fa-user-shield"></i> أداة خاصة بالأدمن
              <span style="display:block;font-weight:400;color:var(--text-muted);font-size:0.78rem;margin-top:2px;">المهام التي تستخدم هذه الأداة لن تظهر للطلاب ولن يُشعَروا بها.</span>
            </label>
          </div>
        </div>
      </div>
    </div>`;

  body.innerHTML = generalSec + techSec + promptSec + formSec + plSec + editSec +
    `<div class="aitd-drawer__foot">
       <button type="button" id="btnSaveTool" class="aitd-savebtn"><i class="fas fa-save"></i> حفظ التعديلات</button>
     </div>`;

  $('btnSaveTool')?.addEventListener('click', () => saveTool(t.id));

  // تحرير إسناد نوع المهمة داخل الدروير
  body.querySelectorAll('.aitd-pl-save').forEach((b) => b.addEventListener('click', () => savePlacementTaskType(Number(b.dataset.pid))));
  body.querySelectorAll('.aitd-pl-del').forEach((b) => b.addEventListener('click', () => deletePlacementFromDrawer(Number(b.dataset.pid))));
  $('aitdLinkBtn')?.addEventListener('click', linkTaskTypeFromDrawer);
}

/** تحديث نوع المهمة لإسناد قائم (يحافظ على بقية حقول الإسناد كما هي). */
async function savePlacementTaskType(pid) {
  if (!currentTool) return;
  const p = (currentTool.placements || []).find((x) => x.id === pid);
  if (!p) return;
  const sel = document.querySelector(`.aitd-pl-tt[data-pid="${pid}"]`);
  const newTt = sel ? (Number(sel.value) || 0) : (p.task_type_id || 0);
  const body = {
    type_key: currentTool.type_key,
    task_type_id: newTt,
    university_id: p.university_id || 0,
    course_id: p.course_id || 0,
    min_subscription_level: p.min_subscription_level || 0,
    button_order: p.button_order || 0,
    button_label: p.button_label || '',
    button_icon: p.button_icon || '',
    is_enabled: p.is_enabled ? 1 : 0,
  };
  const btn = document.querySelector(`.aitd-pl-save[data-pid="${pid}"]`);
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
  try {
    await api(`${API}/placements/${pid}`, { method: 'POST', body: JSON.stringify(body) });
    notify.ok('تم تحديث نوع المهمة');
    await openToolDrawer(currentTool.id);
    loadPlacements();
  } catch (e) {
    notify.err(e.message);
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> حفظ'; }
  }
}

/** ربط الأداة بنوع مهمة جديد (إنشاء إسناد بإعدادات افتراضية). */
async function linkTaskTypeFromDrawer() {
  if (!currentTool) return;
  const sel = $('aitdNewTt');
  const ttId = sel ? (Number(sel.value) || 0) : 0;
  const body = {
    type_key: currentTool.type_key,
    task_type_id: ttId,
    university_id: 0,
    course_id: 0,
    min_subscription_level: 0,
    button_order: 0,
    button_label: '',
    button_icon: '',
    is_enabled: 1,
  };
  const btn = $('aitdLinkBtn');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جارٍ الربط…'; }
  try {
    await api(`${API}/placements`, { method: 'POST', body: JSON.stringify(body) });
    notify.ok('تم ربط الأداة بنوع المهمة');
    await openToolDrawer(currentTool.id);
    loadPlacements();
  } catch (e) {
    notify.err(e.message);
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-link"></i> ربط'; }
  }
}

/** حذف إسناد من داخل الدروير. */
function deletePlacementFromDrawer(pid) {
  confirmDelete(async () => {
    try {
      await api(`${API}/placements/${pid}`, { method: 'DELETE' });
      notify.ok('تم حذف الإسناد');
      if (currentTool) await openToolDrawer(currentTool.id);
      loadPlacements();
    } catch (e) { notify.err(e.message); }
  });
}

async function saveTool(id) {
  const title = $('tf_title').value.trim();
  if (!title) return notify.warn('العنوان مطلوب');
  const body = {
    title,
    description: $('tf_description').value.trim(),
    points_cost: $('tf_points_cost').value === '' ? null : Number($('tf_points_cost').value),
    provider: $('tf_provider').value,
    model_name: $('tf_model_name').value.trim(),
    task_prompt_mode: $('tf_task_prompt_mode').value,
    is_enabled: $('tf_is_enabled').checked ? 1 : 0,
    is_admin_only: $('tf_is_admin_only').checked ? 1 : 0,
    needs_task_context: $('tf_needs_task_context').checked ? 1 : 0,
    needs_specialty: $('tf_needs_specialty').checked ? 1 : 0,
  };
  const btn = $('btnSaveTool');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جارٍ الحفظ…'; }
  try {
    await api(`${API}/tools/${id}`, { method: 'POST', body: JSON.stringify(body) });
    notify.ok('تم تحديث الأداة');
    closeDrawer();
    loadTools();
    loadPlacements();
  } catch (e) {
    notify.err(e.message);
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> حفظ التعديلات'; }
  }
}

/* ---------- Task types ---------- */
async function loadTaskTypes() {
  try {
    const j = await api(`${API}/task-types`);
    state.taskTypes = j.task_types || [];
  } catch { state.taskTypes = []; }
}

/* ---------- Placements ---------- */
async function loadPlacements() {
  try {
    const j = await api(`${API}/placements`);
    state.placements = j.placements || [];
    state.placementsById = {};
    state.placements.forEach((p) => { state.placementsById[p.id] = p; });
    renderPlacements();
  } catch (e) {
    const tb = $('aiPlacementsTbody');
    if (tb) tb.innerHTML = `<tr><td colspan="9" style="padding:18px;text-align:center;color:#c00;">تعذّر التحميل: ${esc(e.message)}</td></tr>`;
  }
}

function renderPlacements() {
  const tb = $('aiPlacementsTbody');
  if (!tb) return;
  if (!state.placements.length) { tb.innerHTML = '<tr><td colspan="9" style="padding:18px;text-align:center;color:#888;">لا توجد إسنادات.</td></tr>'; return; }
  tb.innerHTML = state.placements.map((p) => `
    <tr style="border-bottom:1px solid #eee;">
      <td style="padding:10px;">${esc(p.tool_title || p.type_key)}<br><span style="font-size:0.75rem;color:#999;font-family:monospace;direction:ltr;">${esc(p.type_key)}</span></td>
      <td style="padding:10px;">${p.task_type_id ? esc(p.task_type_name || ('#' + p.task_type_id)) : '<span style="color:#888;">كل الأنواع</span>'}</td>
      <td style="padding:10px;">${p.university_id ? esc(p.university_name || ('#' + p.university_id)) : '<span style="color:#888;">الكل</span>'}</td>
      <td style="padding:10px;">${p.course_id ? esc(p.course_name || ('#' + p.course_id)) : '<span style="color:#888;">الكل</span>'}</td>
      <td style="padding:10px;">${esc(p.button_label || '—')}</td>
      <td style="padding:10px;">${p.button_icon ? iconHtml(p.button_icon) : '<span style="color:#888;">—</span>'}</td>
      <td style="padding:10px;">${esc(p.button_order)}</td>
      <td style="padding:10px;">${p.is_enabled ? '<span style="color:#15803d;font-weight:700;">مُفعّل</span>' : '<span style="color:#b91c1c;">معطّل</span>'}</td>
      <td style="padding:10px;white-space:nowrap;">
        <button type="button" class="ait-edit-pl" data-id="${p.id}" style="padding:6px 10px;border:none;border-radius:8px;background:#eef2ff;color:#2E75B6;cursor:pointer;font-weight:700;"><i class="fas fa-pen"></i></button>
        <button type="button" class="ait-del-pl" data-id="${p.id}" style="padding:6px 10px;border:none;border-radius:8px;background:#fee2e2;color:#b91c1c;cursor:pointer;font-weight:700;"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join('');
  tb.querySelectorAll('.ait-edit-pl').forEach((b) => b.addEventListener('click', () => openPlacementModal(Number(b.dataset.id))));
  tb.querySelectorAll('.ait-del-pl').forEach((b) => b.addEventListener('click', () => {
    confirmDelete(async () => {
      try { await api(`${API}/placements/${Number(b.dataset.id)}`, { method: 'DELETE' }); notify.ok('تم الحذف'); loadPlacements(); }
      catch (e) { notify.err(e.message); }
    });
  }));
}

function fillToolSelect() {
  const sel = $('pf_type_key');
  if (!sel) return;
  sel.innerHTML = state.tools.map((t) => `<option value="${esc(t.type_key)}">${esc(t.title)} (${esc(t.type_key)})</option>`).join('');
}

function fillTaskTypeSelect() {
  const sel = $('pf_task_type_id');
  if (!sel) return;
  sel.innerHTML = '<option value="0">— كل أنواع المهام —</option>' +
    state.taskTypes.map((t) => `<option value="${t.id}">${esc(t.name)} (#${t.id})</option>`).join('');
}

function bindPlacementButtons() {
  $('btnAddPlacement')?.addEventListener('click', () => openPlacementModal(null));
  $('btnSavePlacement')?.addEventListener('click', savePlacement);
}

function openPlacementModal(id) {
  fillToolSelect();
  fillTaskTypeSelect();
  const p = id ? state.placementsById[id] : null;
  $('placementModalTitle').innerHTML = p ? '<i class="fas fa-link"></i> تعديل إسناد' : '<i class="fas fa-link"></i> إضافة إسناد';
  $('pf_id').value = p ? p.id : '';
  $('pf_type_key').value = p ? p.type_key : (state.tools[0]?.type_key || '');
  $('pf_task_type_id').value = p ? String(p.task_type_id || 0) : '0';
  $('pf_university_id').value = p ? p.university_id : 0;
  $('pf_course_id').value = p ? p.course_id : 0;
  $('pf_min_subscription_level').value = p ? p.min_subscription_level : 0;
  $('pf_button_order').value = p ? p.button_order : 0;
  $('pf_button_label').value = p ? (p.button_label || '') : '';
  $('pf_button_icon').value = p ? (p.button_icon || '') : '';
  $('pf_is_enabled').checked = p ? !!p.is_enabled : true;
  showModal('aiPlacementModal');
}

async function savePlacement() {
  const id = $('pf_id').value;
  const typeKey = $('pf_type_key').value;
  if (!typeKey) return notify.warn('يجب اختيار الأداة');
  const body = {
    type_key: typeKey,
    task_type_id: Number($('pf_task_type_id').value) || 0,
    university_id: Number($('pf_university_id').value) || 0,
    course_id: Number($('pf_course_id').value) || 0,
    min_subscription_level: Number($('pf_min_subscription_level').value) || 0,
    button_order: Number($('pf_button_order').value) || 0,
    button_label: $('pf_button_label').value.trim(),
    button_icon: $('pf_button_icon').value.trim(),
    is_enabled: $('pf_is_enabled').checked ? 1 : 0,
  };
  try {
    const url = id ? `${API}/placements/${id}` : `${API}/placements`;
    await api(url, { method: 'POST', body: JSON.stringify(body) });
    hideModal('aiPlacementModal');
    notify.ok(id ? 'تم تحديث الإسناد' : 'تم إنشاء الإسناد');
    loadPlacements();
  } catch (e) { notify.err(e.message); }
}

export default { init };
