/* =========================================================================
 * Library Stats (tokens.css + unified pies)
 * ========================================================================= */

const API = {
  downloadsSummary:  '/admins/api/stats/library/downloads-summary',
  topGrades:         '/admins/api/stats/library/top-grades',
  downloadsAuthAnon: '/admins/api/stats/library/downloads-auth-vs-anon',
  visitsSummary:     '/admins/api/stats/library/visits-summary',
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
    accent400:  cssVar('--accent-400'),
    accent500:  cssVar('--accent-500'),
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
    plugins: { legend: { labels: { color: tok.textStrong } } },
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
    maintainAspectRatio: false, // الحجم من CSS (.is-pie)
    cutout: '58%',
    radius: '100%',
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
function fmt(n){ return Number(n ?? 0).toLocaleString('ar-JO'); }

/* ------------------- Downloads: KPI ------------------- */
async function loadDownloadsSummary(window='month'){
  const res = await getJSON(API.downloadsSummary, { window, filter_bots: 1 });
  if (!res?.ok) return;
  const overall = res.data?.overall ?? 0;
  const win     = res.data?.window_total ?? 0;
  const elOverall = qs('#libKpiDownloadsOverall');
  const elWindow  = qs('#libKpiDownloadsWindow');
  if (elOverall) elOverall.textContent = fmt(overall);
  if (elWindow)  elWindow.textContent  = fmt(win);
}

/* ------------------- Visits: KPI ------------------- */
async function loadVisitsSummary(window='month'){
  const res = await getJSON(API.visitsSummary, { window, filter_bots: 1 });
  if (!res?.ok) return;
  const visits = res.data?.visits ?? 0;
  const el = qs('#libKpiVisitsWindow');
  if (el) el.textContent = fmt(visits);
}

/* ------------------- Top Grades: Bar ------------------- */
async function loadTopGrades(window='month', normalized=false){
  const res = await getJSON(API.topGrades, {
    window, limit: 10, normalized: normalized ? 1 : 0, filter_bots: 1
  });
  if (!res?.ok) return;

  const items = res.data?.items || [];
  const labels = items.map(x => x.grade || '—');
  const values = normalized
    ? items.map(x => Number(x.rate_per_file || 0))
    : items.map(x => Number(x.downloads || 0));
  const datasetLabel = normalized ? 'معدل/ملف' : 'إجمالي التنزيلات';

  const tok = getTOK();
  const opts = commonOptions();
  renderChart('libTopGradesChart', {
    type: 'bar',
    data: { labels, datasets: [{
      label: datasetLabel,
      data: values,
      backgroundColor: hexToRgba(tok.primary500, 0.22),
      borderColor: tok.primary600, borderWidth: 2
    }]},
    options: opts
  });
}

/* ------------------- Auth vs Anon: Doughnut ------------------- */
async function loadAuthVsAnon(window='month'){
  const res = await getJSON(API.downloadsAuthAnon, { window, filter_bots: 1 });
  if (!res?.ok) return;
  const a = Number(res.data?.authenticated ?? 0);
  const n = Number(res.data?.anonymous ?? 0);
  const total = a + n;

  const tok = getTOK();
  renderChart('libAuthAnonChart', {
    type: 'doughnut',
    data: {
      labels: ['موثّق','مجهول'],
      datasets: [{
        data: [a, n],
        backgroundColor: [tok.primary500, tok.neutral400],
        borderColor: pieBorderColor(), borderWidth: 2, hoverOffset: 4
      }]
    },
    options: pieOptions((ctx) => {
      const val = ctx.parsed, pct = total>0 ? ((val/total)*100).toFixed(1) : 0;
      return `${ctx.label}: ${fmt(val)} (${pct}%)`;
    })
  });
}

/* ------------------- Public init ------------------- */
export async function initLibrary(root){
  const sec = qs('[data-db-section="library"]', root) || qs('[data-db-section="library"]');
  if (!sec) return;

  // إظهار السكشن إن كان مخفي
  sec.hidden = false;
  sec.classList.add('is-active');

  // تحميل أولي
  await Promise.all([
    loadDownloadsSummary('month'),
    loadVisitsSummary('month'),
    loadTopGrades('month', false),
    loadAuthVsAnon('month'),
  ]);

  // مرّات التحميل: أزرار النطاق
  const dlBtns = qsa('#libDownloadsCard .segmented-control .seg-btn[data-window]', sec);
  dlBtns.forEach(btn => {
    btn.addEventListener('click', async () => {
      dlBtns.forEach(b => b.classList.toggle('active', b === btn));
      await loadDownloadsSummary(btn.getAttribute('data-window') || 'month');
    });
  });

  // الزيارات: أزرار النطاق
  const vsBtns = qsa('#libVisitsCard .segmented-control .seg-btn[data-window]', sec);
  vsBtns.forEach(btn => {
    btn.addEventListener('click', async () => {
      vsBtns.forEach(b => b.classList.toggle('active', b === btn));
      await loadVisitsSummary(btn.getAttribute('data-window') || 'month');
    });
  });

  // الصفوف: أزرار النطاق + نمط الترتيب
  const tgBtns = qsa('#libTopGradesCard .segmented-control .seg-btn[data-window]', sec);
  const modeBtns = qsa('#libTopGradesCard .segmented-control .seg-btn[data-mode]', sec);
  let modeNormalized = false;

  modeBtns.forEach(mb => {
    mb.addEventListener('click', async () => {
      modeBtns.forEach(b => b.classList.toggle('active', b === mb));
      modeBtns.forEach(b => b.setAttribute('aria-pressed', String(b === mb)));
      modeNormalized = (mb.getAttribute('data-mode') === 'normalized');
      const w = (tgBtns.find(b => b.classList.contains('active'))?.getAttribute('data-window')) || 'month';
      await loadTopGrades(w, modeNormalized);
    });
  });

  tgBtns.forEach(btn => {
    btn.addEventListener('click', async () => {
      tgBtns.forEach(b => b.classList.toggle('active', b === btn));
      const w = btn.getAttribute('data-window') || 'month';
      await loadTopGrades(w, modeNormalized);
    });
  });

  // Auth vs Anon: أزرار النطاق
  const aaBtns = qsa('#libAuthAnonCard .segmented-control .seg-btn[data-window]', sec);
  aaBtns.forEach(btn => {
    btn.addEventListener('click', async () => {
      aaBtns.forEach(b => b.classList.toggle('active', b === btn));
      await loadAuthVsAnon(btn.getAttribute('data-window') || 'month');
    });
  });

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
export async function refreshLibrary(root){
  await initLibrary(root);
}
