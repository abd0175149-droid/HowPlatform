/* =========================================================================
 * Diploma Stats (tokens.css colors + unified pies)
 * ========================================================================= */

const API = {
  activeBreakdown: '/admins/api/stats/diploma/active-breakdown',
  regCompletion:   '/admins/api/stats/diploma/registration-completion',
  planDistribution:'/admins/api/stats/diploma/plan-distribution',
  activeByUniv:    '/admins/api/stats/diploma/active-by-university',
  univTotals:      '/admins/api/stats/diploma/universities-distribution',
  fateenUsage:     '/admins/api/stats/diploma/fateen-usage',
  aiJobStats:      '/admins/api/stats/diploma/ai-job-stats',
};

const CHARTS = {};
const qs  = (s, c=document)=> c.querySelector(s);
const qsa = (s, c=document)=> Array.from(c.querySelectorAll(s));

/* ===== tokens helpers ===== */
function cssVar(name){ return getComputedStyle(document.documentElement).getPropertyValue(name).trim(); }
function hexToRgba(hex, alpha = 1){
  const m = hex.replace('#','').match(/^([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i);
  if (!m) return hex;
  const r = parseInt(m[1],16), g = parseInt(m[2],16), b = parseInt(m[3],16);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

// تحويل TOK إلى دالة تقرأ CSS variables ديناميكياً
function getTOK(){
  return {
    primary300: cssVar('--primary-300'),
    primary500: cssVar('--primary-500'),
    primary600: cssVar('--primary-600'),
    primary700: cssVar('--primary-700'),
    accent300:  cssVar('--accent-300'),
    accent400:  cssVar('--accent-400'),
    accent500:  cssVar('--accent-500'),
    info500:    cssVar('--info-500'),
    info600:    cssVar('--info-600'),
    error500:   cssVar('--error-500'),
    neutral0:   cssVar('--neutral-0'),
    neutral300: cssVar('--neutral-300'),
    neutral400: cssVar('--neutral-400'),
    text:       cssVar('--text'),
    textStrong: cssVar('--text-strong'),
  };
}

// للحفاظ على التوافق مع الكود القديم
const TOK = getTOK();

function pieBorderColor(){ 
  const tok = getTOK();
  return tok.neutral0 || '#fff'; 
}

/* ===== common options ===== */
function commonOptions(){
  const tok = getTOK();
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { labels: { color: tok.textStrong } }
    },
    scales: {
      x: { grid: { color: hexToRgba(tok.neutral300 || '#d9d9d9', .5) }, ticks: { color: tok.text }},
      y: { grid: { color: hexToRgba(tok.neutral300 || '#d9d9d9', .5) }, ticks: { color: tok.text }}
    }
  };
}

/* ===== unified doughnut options (same size/feel) ===== */
function pieOptions(labelCb){
  const tok = getTOK();
  return {
    responsive: true,
    maintainAspectRatio: false, // الحجم يُفرض من CSS container، لكن جميع الدونات لها نفس الإعدادات هنا
    cutout: '58%',              // فتحة الدونات (سماكة موحّدة)
    radius: '100%',             // يملأ المساحة الممنوحة
    layout: { padding: 0 },
    animation: { animateRotate: true, animateScale: true },
    plugins: {
      legend: { labels: { color: tok.textStrong } },
      tooltip: { callbacks: { label: labelCb } }
    }
  };
}

/* ===== utils ===== */
async function getJSON(url, params = {}) {
  const usp = new URLSearchParams(params);
  const res = await fetch(`${url}${usp.toString() ? `?${usp.toString()}` : ''}`, {
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' }
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}
function destroyChart(id){ if (CHARTS[id]) { try{CHARTS[id].destroy();}catch(_){} delete CHARTS[id]; } }
function renderChart(id, cfg){ const el = document.getElementById(id); if(!el) return; destroyChart(id); CHARTS[id] = new Chart(el, cfg); }

function showChartLoading(id) {
  const container = document.getElementById(id)?.parentElement;
  if (container && !qs('.chart-spinner', container)) {
    const spinner = document.createElement('div');
    spinner.className = 'chart-spinner';
    spinner.innerHTML = '<i class="fas fa-circle-notch fa-spin fa-2x" style="color: var(--primary-500)"></i>';
    spinner.style.position = 'absolute';
    spinner.style.inset = '0';
    spinner.style.display = 'flex';
    spinner.style.alignItems = 'center';
    spinner.style.justifyContent = 'center';
    spinner.style.background = 'var(--surface-0)';
    spinner.style.opacity = '0.7';
    spinner.style.zIndex = '10';
    spinner.style.borderRadius = '12px';
    container.style.position = 'relative';
    container.appendChild(spinner);
  }
}
function hideChartLoading(id) {
  const container = document.getElementById(id)?.parentElement;
  if (container) {
    const spinner = qs('.chart-spinner', container);
    if (spinner) spinner.remove();
  }
}

/* ------------------- Active Breakdown (Pie) ------------------- */
async function loadActiveBreakdown(window='day'){
  showChartLoading('dipActiveBreakdownChart');
  try {
    const res = await getJSON(API.activeBreakdown, { window });
    if (!res?.ok) return;
    const a = Number(res.data?.active ?? 0);
    const i = Number(res.data?.inactive ?? 0);
    const total = a + i;
    const tok = getTOK();

    renderChart('dipActiveBreakdownChart', {
      type: 'doughnut',
      data: {
        labels: ['نشِط','غير نشِط'],
        datasets: [{
          data: [a, i],
          backgroundColor: [tok.primary500, tok.neutral400],
          borderColor: pieBorderColor(), borderWidth: 2, hoverOffset: 4
        }]
      },
      options: pieOptions((ctx) => {
        const val = ctx.parsed, pct = total>0 ? ((val/total)*100).toFixed(1) : 0;
        return `${ctx.label}: ${val.toLocaleString('ar-JO')} (${pct}%)`;
      })
    });
  } finally {
    hideChartLoading('dipActiveBreakdownChart');
  }
}

/* ------------------- Registration Completion (Pie) ------------------- */
async function loadRegistrationCompletion(){
  showChartLoading('dipRegCompletionChart');
  try {
    const res = await getJSON(API.regCompletion);
    if (!res?.ok) return;
    const completed   = Number(res.data?.completed ?? 0);
    const uncompleted = Number(res.data?.uncompleted ?? 0);
    const total = completed + uncompleted;
    const tok = getTOK();

    renderChart('dipRegCompletionChart', {
      type: 'doughnut',
      data: {
        labels: ['مكتمل','غير مكتمل'],
        datasets: [{
          data: [completed, uncompleted],
          backgroundColor: [tok.primary500, tok.neutral400],
          borderColor: pieBorderColor(), borderWidth: 2, hoverOffset: 4
        }]
      },
      options: pieOptions((ctx) => {
        const val = ctx.parsed, pct = total>0 ? ((val/total)*100).toFixed(1) : 0;
        return `${ctx.label}: ${val.toLocaleString('ar-JO')} (${pct}%)`;
      })
    });
  } finally {
    hideChartLoading('dipRegCompletionChart');
  }
}

/* ------------------- Plan Distribution (Pie) ------------------- */
async function loadPlanDistribution(){
  showChartLoading('dipPlanChart');
  try {
    const res = await getJSON(API.planDistribution);
    if (!res?.ok) return;
    const items  = res.data?.items || [];
    const labels = items.map(x => String(x.label || '—'));
    const values = items.map(x => Number(x.users || 0));
    const total  = values.reduce((s,v)=>s+v,0);
    const tok = getTOK();

    const palette = [
      tok.primary500, tok.accent500, tok.info600,
      tok.primary700, tok.accent400, tok.primary300
    ];
    const colors = values.map((_, i) => palette[i % palette.length]);

    renderChart('dipPlanChart', {
      type: 'doughnut',
      data: { labels, datasets: [{
        data: values, backgroundColor: colors,
        borderColor: pieBorderColor(), borderWidth: 2, hoverOffset: 4
      }]},
      options: pieOptions((ctx) => {
        const val = ctx.parsed, pct = total>0 ? ((val/total)*100).toFixed(1) : 0;
        return `${ctx.label}: ${val.toLocaleString('ar-JO')} (${pct}%)`;
      })
    });
  } finally {
    hideChartLoading('dipPlanChart');
  }
}

/* ------------------- Universities Totals (Pie) ------------------- */
async function loadUniversitiesTotalsPie(){
  showChartLoading('dipUnivTotalsChart');
  try {
    const res = await getJSON(API.univTotals, { limit: 100 });
    if (!res?.ok) return;
    const items  = res.data?.items || [];
    const labels = items.map(x => String(x.university || 'غير مُحدّد'));
    const values = items.map(x => Number(x.users || 0));
    const total  = values.reduce((s,v)=>s+v,0);
    const tok = getTOK();

    const palette = [
      tok.primary500, tok.accent500, tok.info600,
      tok.primary700, tok.accent400, tok.primary300
    ];
    const colors = values.map((_, i) => palette[i % palette.length]);

    renderChart('dipUnivTotalsChart', {
      type: 'doughnut',
      data: { labels, datasets: [{
        data: values, backgroundColor: colors,
        borderColor: pieBorderColor(), borderWidth: 2, hoverOffset: 4
      }]},
      options: pieOptions((ctx) => {
        const val = ctx.parsed, pct = total>0 ? ((val/total)*100).toFixed(1) : 0;
        return `${ctx.label}: ${val.toLocaleString('ar-JO')} (${pct}%)`;
      })
    });
  } finally {
    hideChartLoading('dipUnivTotalsChart');
  }
}

/* ------------------- Active by University (Top, Bar) ------------------- */
async function loadActiveByUniversity(window='day'){
  showChartLoading('dipUnivChart');
  try {
    const res = await getJSON(API.activeByUniv, { window, limit: 10 });
    if (!res?.ok) return;
    const items  = res.data?.items || [];
    const labels = items.map(x => x.university || '—');
    const values = items.map(x => Number(x.active_users || 0));
    const tok = getTOK();

    const opts = commonOptions();

    renderChart('dipUnivChart', {
      type: 'bar',
      data: { labels, datasets: [{
        label: 'Active Users',
        data: values,
        backgroundColor: hexToRgba(tok.primary500, 0.22),
        borderColor: tok.primary600, borderWidth: 2
      }]},
      options: opts
    });
  } finally {
    hideChartLoading('dipUnivChart');
  }
}

/* ------------------- Fateen Usage (Bar stacked) ------------------- */
/* day => 24 ساعة، month => أيام الشهر، year => 12 شهر */
async function loadFateenUsage(bucket='day'){
  showChartLoading('dipFateenChart');
  try {
    const res = await getJSON(API.fateenUsage, { bucket });
    if (!res?.ok) return;

    const series = res.data?.series || [];
    const labels  = series.map(x => x.bucket);
    const success = series.map(x => Number(x.success_count || 0));
    const fail    = series.map(x => Number(x.fail_count || 0));
    const tok = getTOK();

    const opts = commonOptions();
    opts.scales.x.stacked = true; opts.scales.y.stacked = true;

    renderChart('dipFateenChart', {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'نجاح', data: success, stack: 'sum',
            backgroundColor: hexToRgba(tok.primary500, 0.25),
            borderColor: tok.primary600, borderWidth: 2 },
          { label: 'فشل',  data: fail,    stack: 'sum',
            backgroundColor: hexToRgba(tok.error500, 0.25),
            borderColor: tok.error500, borderWidth: 2 }
        ]
      },
      options: opts
    });
  } finally {
    hideChartLoading('dipFateenChart');
  }
}

/* ------------------- AI Jobs Stats (KPI Flat) ------------------- */
async function loadAiJobStats(){
  try {
    const res = await getJSON(API.aiJobStats);
    if (!res?.ok) return;
    const d = res.data;

    const fmt = n => Number(n).toLocaleString('ar-JO');

    const setEl = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = fmt(val);
    };

    setEl('aiJobsTotalUses',    d.total_uses   ?? 0);
    setEl('aiJobsUniqueUsers',  d.unique_users ?? 0);
    setEl('aiJobsDoneCount',    d.done_count   ?? 0);
    setEl('aiJobsFailedCount',  d.failed_count ?? 0);

    // توزيع حسب النوع
    const list = document.getElementById('aiJobsByTypeList');
    if (list) {
      const types = d.by_type ?? [];
      if (types.length === 0) {
        list.innerHTML = '<p style="color:var(--text-muted);font-size:13px;text-align:center;padding:8px 0;">لا توجد بيانات</p>';
      } else {
        list.innerHTML = types.map(t => `
          <div class="ai-job-type-row">
            <span class="ai-job-type-name">${t.type_name || t.type_key || '—'}</span>
            <span class="ai-job-type-uses">${fmt(t.total_uses)} استخدام</span>
            <span class="ai-job-type-uniq">${fmt(t.unique_users)} مستخدم</span>
          </div>
        `).join('');
      }
    }
  } catch(e) {
    console.warn('AI Jobs stats error:', e);
  }
}

/* ------------------- Public init ------------------- */
export async function initDiploma(root){
  const sec = qs('[data-db-section="diploma"]', root);
  if (!sec) return;

  await Promise.all([
    loadActiveBreakdown('day'),
    loadRegistrationCompletion(),
    loadPlanDistribution(),
    loadUniversitiesTotalsPie(),
    loadActiveByUniversity('day'),
    loadFateenUsage('day'),
    loadAiJobStats(),
  ]);

  if (!sec.dataset.eventsBound) {
    // النوافذ (النشطون + الجامعات النشطة)
    const activeBtns = qsa('[data-db-section="diploma"] .segmented-control .seg-btn[data-window]', root);
    activeBtns.forEach(btn => {
      const card = btn.closest('.stat-card');
      btn.addEventListener('click', async () => {
        const siblings = qsa('.seg-btn[data-window]', card);
        siblings.forEach(b => b.classList.toggle('active', b === btn));
        const w = btn.getAttribute('data-window') || 'day';
        if (qs('#dipActiveBreakdownChart', card)) await loadActiveBreakdown(w);
        if (qs('#dipUnivChart', card))            await loadActiveByUniversity(w);
      });
    });

    // فَطين: يوم/شهر/سنة
    const fateenBtns = qsa('[data-db-section="diploma"] .segmented-control .seg-btn[data-bucket]', root);
    fateenBtns.forEach(btn => {
      btn.addEventListener('click', async () => {
        fateenBtns.forEach(b => b.classList.toggle('active', b === btn));
        await loadFateenUsage(btn.getAttribute('data-bucket') || 'day');
      });
    });

    sec.dataset.eventsBound = 'true';
  }

  // إعادة رسم الرسوم البيانية عند تغيير الثيم
  const themeObserver = new MutationObserver(() => {
    // إعادة رسم جميع الرسوم البيانية عند تغيير data-theme
    Object.keys(CHARTS).forEach(chartId => {
      const chart = CHARTS[chartId];
      if (chart && chart.options && chart.options.plugins) {
        const tok = getTOK();
        // تحديث ألوان Legend
        if (chart.options.plugins.legend && chart.options.plugins.legend.labels) {
          chart.options.plugins.legend.labels.color = tok.textStrong;
        }
        // تحديث ألوان Ticks في Scales
        if (chart.options.scales) {
          Object.keys(chart.options.scales).forEach(scaleKey => {
            const scale = chart.options.scales[scaleKey];
            if (scale.ticks) {
              scale.ticks.color = tok.text;
            }
            if (scale.grid) {
              scale.grid.color = hexToRgba(tok.neutral300 || '#d9d9d9', .5);
            }
          });
        }
        chart.update('none'); // تحديث بدون animation
      }
    });
  });

  // مراقبة تغيير data-theme على html
  themeObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-theme']
  });
}

// دالة لإعادة رسم جميع الرسوم البيانية (للاستخدام عند تغيير الثيم)
export async function refreshDiploma(root){
  await initDiploma(root);
}
