// public/assets/js/library.js
(function () {
  // عناصر التحكم
  const q        = document.getElementById('q');
  const subject  = document.getElementById('subject');
  const semSel   = document.getElementById('semester_id') || document.getElementById('semester');
  const sortSel  = document.getElementById('sort');
  const viewTog  = document.querySelectorAll('.view-toggle [data-view]');
  const pills    = document.querySelectorAll('.pill[data-cat]');
  const grid     = document.getElementById('resources');
  const noRes    = document.getElementById('no-results');

  // حالة الفلاتر الحالية
  let state = {
    q: '',
    subject_id: '',
    semester_id: '',
    category: '',
    sort: 'recent',
    view: 'grid'
  };

  // ========= Helpers =========
  function sameOriginUrl(u) {
    // يضمن أن أي URL نستخدمه على نفس الدومين الحالي
    let url;
    try { url = new URL(u, window.location.origin); } catch { url = new URL(window.location.pathname, window.location.origin); }
    if (url.host !== window.location.host) {
      url = new URL(url.pathname + url.search, window.location.origin);
    }
    return url;
  }

  function buildPageUrl(meta, page) {
    // meta.path قد يكون مطلقًا أو نسبيًا
    let base = sameOriginUrl(meta.path || window.location.pathname);
    const params = new URLSearchParams(meta.query || {});
    params.set('page', page);
    base.search = params.toString();
    return base.toString();
  }

  function windowPages(current, last){
    const pages = new Set([1, last, current-1, current, current+1]);
    return [...pages].filter(p => p >= 1 && p <= last).sort((a,b)=>a-b);
  }

  function renderPaginationFromMeta(meta){
    const holder = document.getElementById('pagination');
    if (!holder) return;

    const current = meta.current_page;
    const last    = meta.last_page;
    const pages   = windowPages(current, last);

    const withDots = [];
    for (let i=0;i<pages.length;i++){
      withDots.push(pages[i]);
      if (i < pages.length-1 && pages[i+1] - pages[i] > 1) withDots.push('…');
    }

    let html = `
    <div class="pagination-wrap" id="pagination">
      <nav class="pagination-nav" role="navigation" aria-label="Pagination">
        ${current > 1
          ? `<a class="page-btn" href="${buildPageUrl(meta,current-1)}" rel="prev" aria-label="Previous">
               <svg viewBox="0 0 24 24" class="chev"><path d="M9 6l6 6-6 6"/></svg>
             </a>`
          : `<span class="page-btn is-disabled" aria-disabled="true" aria-label="Previous">
               <svg viewBox="0 0 24 24" class="chev"><path d="M9 6l6 6-6 6"/></svg>
             </span>`
        }
        <ul class="pages">
          ${withDots.map(p => {
            if (p === '…') return `<li class="dots" aria-hidden="true">…</li>`;
            const active = (p === current) ? ' is-active' : '';
            const tag    = (p === current) ? 'span' : 'a';
            const href   = (p === current) ? '' : ` href="${buildPageUrl(meta,p)}"`;
            return `<li><${tag} class="page-num${active}"${href}>${p}</${tag}></li>`;
          }).join('')}
        </ul>
        ${current < last
          ? `<a class="page-btn" href="${buildPageUrl(meta,current+1)}" rel="next" aria-label="Next">
               <svg viewBox="0 0 24 24" class="chev"><path d="M15 18l-6-6 6-6"/></svg>
             </a>`
          : `<span class="page-btn is-disabled" aria-disabled="true" aria-label="Next">
               <svg viewBox="0 0 24 24" class="chev"><path d="M15 18l-6-6 6-6"/></svg>
             </span>`
        }
      </nav>
    </div>`;
    holder.outerHTML = html;
  }

  function reinitCardInteractions(){
    // لو عندك تأثير "tilt" أو غيره لازم يُعاد تفعيله بعد استبدال الـ HTML
    document.querySelectorAll(".tilt").forEach((card) => {
      const rect = () => card.getBoundingClientRect();
      function onMove(e) {
        const r = rect();
        const x = (e.clientX - r.left) / r.width;
        const y = (e.clientY - r.top) / r.height;
        const rx = (y - 0.5) * 6;
        const ry = (x - 0.5) * -8;
        card.style.setProperty("--rx", rx + "deg");
        card.style.setProperty("--ry", ry + "deg");
        card.style.setProperty("--mx", x * 100 + "%");
        card.style.setProperty("--my", y * 100 + "%");
      }
      card.addEventListener("pointermove", onMove);
      card.addEventListener("pointerleave", () => {
        card.style.removeProperty("--rx");
        card.style.removeProperty("--ry");
        card.style.removeProperty("--mx");
        card.style.removeProperty("--my");
      });
    });
  }

  async function applyAjaxResult(data){
    // استبدال شبكة البطاقات
    if (grid && typeof data.grid === 'string') {
      grid.innerHTML = data.grid;
    }

    // إعادة بناء الترقيم من الميتاداتا (أفضل)
    if (data.pmeta) {
      // لو الفلاتر صغّرت عدد الصفحات وكنا على صفحة أكبر من last_page -> اقفز لآخر صفحة
      if (data.pmeta.current_page > data.pmeta.last_page && data.pmeta.last_page >= 1) {
        const lastUrl = buildPageUrl(data.pmeta, data.pmeta.last_page);
        const res = await fetch(lastUrl, {
          headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' }
        });
        const again = await res.json();
        return applyAjaxResult(again);
      }
      renderPaginationFromMeta(data.pmeta);

      // حدّث شريط العنوان بحيث يعكس الفلاتر والصفحة الحالية
      const urlNow = buildPageUrl(data.pmeta, data.pmeta.current_page);
      history.replaceState({}, '', urlNow);

      // إظهار/إخفاء "لا نتائج"
      if (typeof data.noResults !== 'undefined' && noRes) {
        noRes.hidden = !data.noResults;
      }
    } else {
      // fallback: استخدم HTML الجاهز إن وُجد
      const holder = document.getElementById('pagination');
      if (holder) holder.outerHTML = data.pagination || '<div class="pagination-wrap" id="pagination"></div>';
    }

    // أعد تهيئة أي تأثيرات على البطاقات
    reinitCardInteractions();
  }

  function currentFilters(){
    const f = {};
    const term = (q?.value || '').trim();
    if (term) f.q = term;

    const sid = subject?.value;
    if (sid) f.subject_id = sid;

    const sem = semSel?.value;
    if (sem !== '' && sem !== undefined && sem !== null) f.semester_id = sem;

    f.sort = (sortSel?.value || 'recent');
    if (state.category) f.category = state.category;

    // نطلب JSON دومًا
    f.ajax = 1;
    return f;
  }

  async function load(page = 1){
    const params = currentFilters();
    params.page = page;

    const url = new URL(window.location.pathname, window.location.origin);
    url.search = new URLSearchParams(params).toString();

    const res = await fetch(url.toString(), {
      headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' }
    });
    const data = await res.json();
    await applyAjaxResult(data);
  }

  // ========= أحداث الفلاتر =========
  // Debounce للبحث
  let qTimer = null;
  q?.addEventListener('input', () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(() => { state.q = (q.value||'').trim(); load(1); }, 280);
  });

  subject?.addEventListener('change', () => { state.subject_id = subject.value; load(1); });
  semSel?.addEventListener('change', () => { state.semester_id = semSel.value; load(1); });
  sortSel?.addEventListener('change', () => { state.sort = sortSel.value; load(1); });

  pills.forEach(p => p.addEventListener('click', () => {
    pills.forEach(x => x.classList.remove('active'));
    p.classList.add('active');
    state.category = p.dataset.cat || '';
    load(1);
  }));

  // تبديل العرض (لا يؤثر على الترقيم)
  viewTog.forEach(btn => btn.addEventListener('click', () => {
    viewTog.forEach(x => x.setAttribute('aria-selected','false'));
    btn.setAttribute('aria-selected','true');
    state.view = btn.dataset.view;
    grid?.setAttribute('data-view', state.view);
  }));

  // اعتراض نقرات الترقيم للتحميل عبر AJAX
  document.addEventListener('click', async (e) => {
    const link = e.target.closest('.pagination-wrap a, .pagination-nav a');
    if (!link) return;
    e.preventDefault();

    // لو الرابط لا يحمل نفس الأصل، حوّله لنفس الأصل
    const safe = sameOriginUrl(link.href);
    const res  = await fetch(safe.toString(), { headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } });
    const data = await res.json();
    applyAjaxResult(data);
  });

  // تفعيل العرض الافتراضي
  if (grid) grid.setAttribute('data-view', state.view);
})();
