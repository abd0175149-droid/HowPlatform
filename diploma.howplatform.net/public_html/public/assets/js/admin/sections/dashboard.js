/* =========================================================================
 * Admin Dashboard (Tabs + Date) — Dynamic Refresh + Event Delegation
 * - Lazy-load modules (diploma + library) with refresh fallback
 * - Tools (inline): always refresh on tab activation
 * - Diploma (inline): Active Users table (window filter + pagination)
 *   • IDs متوافقة مع الواجهة: dipAUPrev / dipAUNext / dipAUPaginationInfo
 *   • تفويض أحداث على سكشن الدبلوم لتفادي فقدان الربط عند إعادة بناء DOM
 * ========================================================================= */

function qs(s, c=document){ return c.querySelector(s); }
function qsa(s, c=document){ return Array.from(c.querySelectorAll(s)); }

// 🔽 Loader for external script once
function loadScriptOnce(src, readyCheck, timeoutMs = 10000){
  return new Promise((resolve, reject) => {
    try {
      if (readyCheck && readyCheck()) return resolve();
      const id = 'js-' + btoa(src).replace(/=/g,'');
      if (document.getElementById(id)) {
        const start = Date.now();
        (function waitReady(){
          if (readyCheck && readyCheck()) return resolve();
          if (Date.now() - start > timeoutMs) return reject(new Error('Timeout loading '+src));
          requestAnimationFrame(waitReady);
        })();
        return;
      }
      const s = document.createElement('script');
      s.id = id; s.async = true; s.src = src;
      s.onload = () => {
        const start = Date.now();
        (function waitReady(){
          if (readyCheck && readyCheck()) return resolve();
          if (Date.now() - start > timeoutMs) return reject(new Error('Timeout after load '+src));
          requestAnimationFrame(waitReady);
        })();
      };
      s.onerror = () => reject(new Error('Failed to load '+src));
      document.head.appendChild(s);
    } catch (e) { reject(e); }
  });
}

// ✅ Ensure Chart.js (v4)
async function ensureChartJs(){
  if (window.Chart) return;
  await loadScriptOnce('https://cdn.jsdelivr.net/npm/chart.js', () => !!window.Chart);
}

/* ========= Modules per tab ========= */
const MODULES = {
  diploma: '/assets/js/admins/sections/diploma_stats.js?v=1.0.3',
  library: '/assets/js/admins/sections/library_stats.js?v=1.0.1',
  // tools: inline here
};
const INIT_FNS    = { diploma: 'initDiploma',     library: 'initLibrary' };
const REFRESH_FNS = { diploma: 'refreshDiploma',  library: 'refreshLibrary' };
const loaded = new Set();
const MODULE_CACHE = {}; // target -> imported module

/* ======================= Tools (Inline) ======================= */
const toolCharts = new Map(); // key: canvas id -> Chart instance
let _toolsReloadFn = null;    // expose reload for dynamic refresh

async function fetchToolCards({ onlyActive = true, limit = null } = {}){
  const params = new URLSearchParams();
  if (onlyActive) params.set('only_active','1');
  if (limit && Number(limit) > 0) params.set('limit', String(limit));
  const url = `/admins/api/stats/tools/cards${params.toString() ? ('?' + params.toString()) : ''}`;

  const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
  let data;
  try { data = await res.json(); } catch { data = {}; }
  if (!res.ok) {
    const msg = data?.error || data?.message || '';
    throw new Error(`HTTP ${res.status}${msg ? ' - ' + msg : ''}`);
  }
  if (!data.ok || !Array.isArray(data.items)) throw new Error('Malformed response');
  return data.items;
}

function destroyExistingToolCharts(){
  toolCharts.forEach((ch) => { try { ch.destroy(); } catch {} });
  toolCharts.clear();
}

/** Build tool stat-cards inside tools section */
function renderToolCards(toolsSection, items){
  if (!toolsSection) return;

  destroyExistingToolCharts();

  // 🧹 remove previous cards/states
  qsa('.stat-card[data-tool-id]', toolsSection).forEach(el => el.remove());
  qsa('.stat-card[data-generated="tools-empty"], .stat-card[data-generated="tools-error"]', toolsSection).forEach(el => el.remove());

  if (!items.length) {
    const empty = document.createElement('div');
    empty.className = 'stat-card';
    empty.setAttribute('data-generated', 'tools-empty');
    empty.innerHTML = `
      <div class="stat-header">
        <h4 class="stat-title">لا توجد أدوات للعرض</h4>
        <div class="stat-icon"><i class="fas fa-toolbox"></i></div>
      </div>
      <div class="stat">جرّب إلغاء تصفية "الأدوات المفعّلة فقط" أو زيادة حدّ العرض.</div>
    `;
    toolsSection.appendChild(empty);
    return;
  }

  const totalUses = items.reduce((s, it) => s + Number(it?.uses_count ?? 0), 0) || 0;
  const safeTotal = totalUses > 0 ? totalUses : 1;

  items.forEach((item, idx) => {
    const toolId  = item?.tool_id ?? `x${idx}`;
    const name    = item?.name ?? 'Tool';

    const usesNum   = Number(item?.uses_count ?? 0);
    const usesTxt   = Number.isFinite(usesNum) ? usesNum.toLocaleString('ar-JO') : '—';

    const uniqueNum = Number(item?.unique_users_count ?? 0);
    const uniqueTxt = Number.isFinite(uniqueNum) ? uniqueNum.toLocaleString('ar-JO') : '—';

    const repeated  = Math.max(usesNum - uniqueNum, 0);
    const rateRaw   = item?.rating_avg;
    const rateNum   = (rateRaw === null || rateRaw === undefined) ? null : Number(rateRaw);
    const ratingText= Number.isFinite(rateNum) ? rateNum.toFixed(2) : '—';

    const last      = item?.last_used_display ?? item?.last_used_at_local_short ?? '—';

    const miniId = `toolMini-${toolId}-${idx}`;
    const pieId  = `toolPie-${toolId}-${idx}`;

    const card = document.createElement('div');
    card.className = 'stat-card';
    card.setAttribute('data-tool-id', String(toolId));
    card.innerHTML = `
      <div class="stat-header">
        <h4 class="stat-title">${escapeHtml(name)}</h4>
        <div class="stat-icon"><i class="fas fa-toolbox"></i></div>
      </div>

      <div class="tool-top" style="display:flex; gap:12px; align-items:center; margin-bottom:8px;">
        <div class="chart-container is-pie mini-pie" style="--chart-pie-size: 96px; flex:0 0 auto;">
          <canvas id="${miniId}" width="96" height="96" aria-label="فريدون مقابل مكرّر لـ ${escapeHtml(name)}" role="img"></canvas>
        </div>
        <div class="tool-metrics" style="display:grid; gap:4px;">
          <div class="stat">عدد الاستخدامات: <b>${usesTxt}</b></div>
          <div class="stat">المستخدمون الفريدون: <b>${uniqueTxt}</b></div>
          <div class="stat">تقييم الأداة: <b>${ratingText}</b></div>
          <div class="stat">آخر استخدام: <b>${escapeHtml(last)}</b></div>
        </div>
      </div>

      <div class="chart-container is-pie" style="margin-top:6px; position:relative;">
        <canvas id="${pieId}" width="180" height="180" aria-label="نسبة استخدام ${escapeHtml(name)} من إجمالي الأدوات" role="img"></canvas>
        <div class="donut-center" aria-hidden="true"></div>
      </div>
    `;
    toolsSection.appendChild(card);

    const miniCanvas = card.querySelector(`#${CSS.escape(miniId)}`);
    if (miniCanvas) {
      const miniChart = new Chart(miniCanvas.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: ['مستخدمون فريدون', 'استخدامات مكرّرة'],
          datasets: [{ data: [uniqueNum, repeated], backgroundColor: ['rgba(16,185,129,.9)','rgba(0,0,0,.12)'], borderWidth: 0 }]
        },
        options: {
          responsive:true, maintainAspectRatio:false, cutout:'60%',
          plugins:{ legend:{display:false}, tooltip:{ callbacks:{
            label:(ctx)=>{ const v=ctx.parsed, total=uniqueNum+repeated||1, p=((v/total)*100).toFixed(1); return `${ctx.label||''}: ${v.toLocaleString('ar-JO')} (${p}%)`; }
          }}}
        }
      });
      toolCharts.set(miniId, miniChart);
    }

    const others  = Math.max(0, safeTotal - usesNum);
    const pieCanvas  = card.querySelector(`#${CSS.escape(pieId)}`);
    if (pieCanvas) {
      const pieChart = new Chart(pieCanvas.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: ['هذه الأداة', 'بقية الأدوات'],
          datasets: [{ data:[usesNum, others], backgroundColor:['rgba(59,130,246,.9)','rgba(0,0,0,.08)'], borderWidth:0 }]
        },
        options: {
          responsive:true, maintainAspectRatio:false, cutout:'65%',
          plugins:{ legend:{display:false}, tooltip:{ callbacks:{
            label:(ctx)=>{ const v=ctx.parsed, total=v+(ctx.dataset.data[0]===v?others:usesNum), p=((v/total)*100).toFixed(1); return `${ctx.label||''}: ${v.toLocaleString('ar-JO')} (${p}%)`; }
          }}}
        }
      });
      toolCharts.set(pieId, pieChart);

      const center = card.querySelector('.donut-center');
      const percent = safeTotal ? Math.round((usesNum / safeTotal) * 100) : 0;
      if (center) { center.textContent = `${percent}%`; }
    }
  });
}

/** Bind controls and load (Tools) */
function bindToolsControls(root){
  const toolsSection = qs('.stats-section[data-db-section="tools"]', root);
  const onlyActiveEl = qs('#toolsOnlyActive', root);
  const limitEl      = qs('#toolsLimit', root);
  const refreshBtn   = qs('#toolsRefreshBtn', root);

  async function load(){
    try {
      await ensureChartJs();
      const onlyActive = !!(onlyActiveEl && onlyActiveEl.checked);
      const limit = (limitEl && limitEl.value !== '') ? Number(limitEl.value) : null;
      const items = await fetchToolCards({ onlyActive, limit });
      renderToolCards(toolsSection, items);
    } catch (err) {
      console.error('Failed to load tools cards:', err);
      destroyExistingToolCharts();
      if (toolsSection) {
        qsa('.stat-card[data-tool-id]', toolsSection).forEach(el => el.remove());
        qsa('.stat-card[data-generated="tools-empty"], .stat-card[data-generated="tools-error"]', toolsSection).forEach(el => el.remove());
        const errCard = document.createElement('div');
        errCard.className = 'stat-card';
        errCard.setAttribute('data-generated', 'tools-error');
        errCard.innerHTML = `
          <div class="stat-header">
            <h4 class="stat-title">فشل التحميل</h4>
            <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
          </div>
          <div class="stat">تعذّر جلب بيانات الأدوات. حاول مجددًا.</div>
        `;
        toolsSection.appendChild(errCard);
      }
    }
  }

  _toolsReloadFn = load;

  if (onlyActiveEl) onlyActiveEl.addEventListener('change', load);
  if (limitEl)      limitEl.addEventListener('change', load);
  if (refreshBtn)   refreshBtn.addEventListener('click', load);

  load(); // أول تحميل
}

/** init tools tab once */
async function initTools(root){
  bindToolsControls(root);
}

/* ======================= Diploma: Active Users List (Inline) ======================= */
let _diplomaDelegatedBound = false;   // تفويض مرة واحدة على سكشن الدبلوم
let _activeUsersReloadFn = null;      // expose loader for dynamic refresh

function escapeHtml(s){
  return String(s ?? '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'",'&#39;');
}

async function getJSON(url){
  const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
  const data = await res.json().catch(() => ({}));
  if (!res.ok || !data || data.ok === false) {
    const msg = data?.message || data?.error || `HTTP ${res.status}`;
    throw new Error(msg);
  }
  return data;
}

/** API: /admins/api/stats/diploma/active-users-list?window=day|week|month&page=&per_page= */
async function fetchActiveUsersList({ window='day', page=1, per_page=5 } = {}){
  const params = new URLSearchParams({ window, page: String(page), per_page: String(per_page) });
  const url = `/admins/api/stats/diploma/active-users-list?${params.toString()}`;
  const data = await getJSON(url);
  return data;
}

/** قراءة الحالة من DOM */
function readAUStateFromDOM(root){
  const card = qs('#dipActiveUsersCard', root);
  const def = { window: 'day', page: 1, per_page: 5 };
  if (!card) return def;

  const ds = card.dataset || {};
  let win = ds.window || null;
  let page = Number(ds.page || 0) || 1;
  let per  = Number(ds.perPage || 0) || 5;

  if (!win) {
    const activeBtn = qs('.segmented-control [data-window].active', card);
    win = activeBtn?.getAttribute('data-window') || 'day';
  }
  return { window: win, page, per_page: per };
}

/** كتابة الحالة على DOM */
function writeAUStateToDOM(root, state){
  const card = qs('#dipActiveUsersCard', root);
  if (!card) return;
  card.dataset.window  = state.window;
  card.dataset.page    = String(state.page);
  card.dataset.perPage = String(state.per_page);
}

/** يرسم صفوف جدول المستخدمين النشطين */
function renderActiveUsersListCard(root, payload){
  const card    = qs('#dipActiveUsersCard', root);
  const tbody   = qs('#dipActiveUsersList', root);
  const prevBtn = qs('#dipAUPrev', root);
  const nextBtn = qs('#dipAUNext', root);
  const pageInfo= qs('#dipAUPaginationInfo', root);

  if (!tbody) return;

  const items = payload?.items ?? [];
  if (!items.length) {
    tbody.innerHTML = `
      <tr class="au-empty"><td colspan="4" style="color:var(--text-muted);">لا يوجد مستخدمون نشطون في هذه الفترة.</td></tr>
    `;
  } else {
    tbody.innerHTML = items.map(item => {
      const name   = escapeHtml(item.display_name ?? `مستخدم #${item.id ?? ''}`);
      const univ   = escapeHtml(item.university_name ?? 'غير مُحدّد');
      const spec   = escapeHtml(item.specialty_name ?? 'غير مُحدّد');
      const time   = escapeHtml(item.last_login_display ?? item.last_login_local ?? item.last_login_at ?? '');
      return `
        <tr class="au-row">
          <td>${name}</td>
          <td>${univ}</td>
          <td>${spec}</td>
          <td>${time}</td>
        </tr>
      `;
    }).join('');
  }

  if (prevBtn) prevBtn.disabled = !payload?.has_prev;
  if (nextBtn) nextBtn.disabled = !payload?.has_next;

  if (pageInfo) {
    const p = payload?.page ?? 1;
    const total = payload?.total ?? 0;
    const per = payload?.per_page ?? 5;
    const from = total ? ((p - 1) * per + 1) : 0;
    const to   = Math.min(p * per, total);
    pageInfo.textContent = total ? `عرض ${from}–${to} من ${total}` : 'لا سجلات';
  }

  // حدّث الحالة على DOM حتى لا نضيعها عند إعادة البناء
  if (card) {
    const state = {
      window: payload?.window || (qs('.segmented-control [data-window].active', card)?.getAttribute('data-window') || 'day'),
      page: payload?.page ?? 1,
      per_page: payload?.per_page ?? 5
    };
    writeAUStateToDOM(root, state);
  }
}

/** لودر قابل للنداء دائمًا (يقرأ الحالة من DOM) */
async function reloadActiveUsers(root){
  const card = qs('#dipActiveUsersCard', root);
  if (!card) return;
  const state = readAUStateFromDOM(root);

  card.setAttribute('aria-busy', 'true');
  try {
    const payload = await fetchActiveUsersList(state);
    renderActiveUsersListCard(root, payload);
  } catch (e) {
    const tbody = qs('#dipActiveUsersList', root);
    if (tbody) tbody.innerHTML = `<tr class="au-error"><td colspan="4">تعذّر جلب القائمة. حاول مجددًا.</td></tr>`;
    console.error('[ActiveUsersList] reload error:', e);
  } finally {
    card.removeAttribute('aria-busy');
  }
}

/** تفويض أحداث على سكشن الدبلوم (ثابت لا يتبدّل) */
function bindDiplomaDelegated(root){
  if (_diplomaDelegatedBound) return;

  const diplomaSection = qs('.stats-section[data-db-section="diploma"]', root);
  if (!diplomaSection) return;

  diplomaSection.addEventListener('click', (ev) => {
    const target = ev.target.closest('button, [role="button"]');
    if (!target || !diplomaSection.contains(target)) return;

    // نستهدف كارد المستخدمين النشطين فقط
    const card = target.closest('#dipActiveUsersCard');
    if (!card) return;

    // 1) تغيير النافذة الزمنية
    if (target.hasAttribute('data-window')) {
      const w = target.getAttribute('data-window') || 'day';
      const group = target.closest('.segmented-control');
      if (group) {
        qsa('[data-window]', group).forEach(b => {
          b.classList.toggle('active', b === target);
          b.setAttribute('aria-selected', b === target ? 'true' : 'false');
        });
      }
      // خزّن الحالة على DOM وأعد التحميل
      const cur = readAUStateFromDOM(root);
      writeAUStateToDOM(root, { window: w, page: 1, per_page: cur.per_page });
      reloadActiveUsers(root);
      return;
    }

    // 2) تنقّل الصفحات
    if (target.id === 'dipAUPrev' || target.id === 'dipAUNext') {
      const cur = readAUStateFromDOM(root);
      let nextPage = cur.page;
      if (target.id === 'dipAUPrev' && cur.page > 1) nextPage = cur.page - 1;
      if (target.id === 'dipAUNext') nextPage = cur.page + 1;
      writeAUStateToDOM(root, { ...cur, page: nextPage });
      reloadActiveUsers(root);
      return;
    }
  });

  _diplomaDelegatedBound = true;

  // أول تحميل إن كان الدبلوم ظاهرًا
  if (!diplomaSection.hasAttribute('hidden') && diplomaSection.classList.contains('is-active')) {
    reloadActiveUsers(root);
  }
  // expose
  _activeUsersReloadFn = () => reloadActiveUsers(root);
}

/* ===================================================================== */
/* Unified loader/refresh per tab */

async function loadStatsModule(target, root){
  // الأدوات: inline دائمًا ننعشها
  if (target === 'tools') {
    if (!loaded.has('tools')) {
      await initTools(root);
      loaded.add('tools');
    } else if (typeof _toolsReloadFn === 'function') {
      _toolsReloadFn();
    }
    return;
  }

  // باقي التبويبات ذات الموديولات
  if (!MODULES[target]) return;

  try {
    await ensureChartJs();

    if (!MODULE_CACHE[target]) {
      MODULE_CACHE[target] = await import(MODULES[target]);
    }
    const mod = MODULE_CACHE[target];
    const initName = INIT_FNS[target];
    const refreshName = REFRESH_FNS[target];

    if (!loaded.has(target)) {
      if (mod && typeof mod[initName] === 'function') {
        await mod[initName](root);
        loaded.add(target);
      } else {
        console.error(`Module for "${target}" missing init function: ${initName}`);
      }
    } else {
      // 🔁 refresh إن متاح، وإلا init (idempotent)
      if (mod && typeof mod[refreshName] === 'function') {
        await mod[refreshName](root);
      } else if (mod && typeof mod[initName] === 'function') {
        await mod[initName](root);
      }
    }

    // داخل الدبلوم: فعّل التفويض ثم انعش القائمة
    if (target === 'diploma') {
      bindDiplomaDelegated(root);
      if (typeof _activeUsersReloadFn === 'function') _activeUsersReloadFn();
    }
  } catch (e) {
    console.error(`Failed to load/refresh ${target} module`, e);
  }
}

/* Tabs: show/hide + dynamic refresh */
function initTabs(root){
  const tabs = qsa('.db-tab', root);
  const sections = qsa('[data-db-section]', root);

  async function activate(target){
    tabs.forEach(b => {
      const isActive = (b.getAttribute('data-db') === target);
      b.classList.toggle('active', isActive);
      b.setAttribute('aria-selected', String(isActive));
    });
    sections.forEach(sec => {
      const active = (sec.getAttribute('data-db-section') === target);
      sec.classList.toggle('is-active', active);
      if (active) sec.removeAttribute('hidden'); else sec.setAttribute('hidden', '');
    });

    await loadStatsModule(target, root);
  }

  tabs.forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-db');
      activate(target);
    });
  });

  // انعش عند العودة للنافذة
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      const current = qs('.db-tab.active', root)?.getAttribute('data-db');
      if (current) activate(current);
    }
  });

  // التحقق من sessionStorage للتبويب المطلوب
  const savedTab = sessionStorage.getItem('admin_open_tab');
  if (savedTab) {
    sessionStorage.removeItem('admin_open_tab');
    activate(savedTab);
  } else {
    // افتراضي: الدبلوم
    activate('diploma');
  }
}

function initHeaderDate(root){
  const d = new Date();
  const currentDate = d.toLocaleDateString('ar-JO', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
  const y = d.getFullYear();
  const dateEl = qs('#current-date', root);
  const yearEl = qs('#currentYear', root);
  if (dateEl) dateEl.textContent = currentDate;
  if (yearEl) yearEl.textContent = y;
}

export async function init(){
  const T0 = Date.now();
  const MAX = 3000;
  let root = qs('[data-admin-dashboard]');
  while (!root && Date.now()-T0 < MAX){
    await new Promise(r => setTimeout(r, 60));
    root = qs('[data-admin-dashboard]');
  }
  if (!root) return;

  initHeaderDate(root);
  initTabs(root);
}
