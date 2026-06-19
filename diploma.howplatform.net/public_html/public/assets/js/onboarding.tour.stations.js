/* =========================================================================
 * Onboarding Tour v6.5 — Mobile-first, Smart Tooltip Placement, Sidebar Flow
 * - لا نخفي التولتيب؛ نعيد تموضعه تلقائياً لتفادي تغطية الهدف.
 * - إبراز زر فتح السايدبار عندما تكون الخطوة في السايدبار وهو مغلق (تدفق مسبق).
 * - overlay/helperLayer لا يحجبان النقرات، وأزرار التولتيب فقط قابلة للنقر.
 * ========================================================================= */
(function () {
  const DEBUG = false;
  const log   = (...a) => DEBUG && console.log('[tour:v6.5]', ...a);
  const sleep = (ms) => new Promise(r => setTimeout(r, ms));
  const qs  = (s, c = document) => c.querySelector(s);
  const qsa = (s, c = document) => Array.from(c.querySelectorAll(s));
  const IS_TOUCH = (('ontouchstart' in window) || navigator.maxTouchPoints > 0);
  const IS_SMALL = () => Math.min(window.innerWidth, window.innerHeight) <= 480;

  /* ====== Utils ====== */
  function isVisible(el) {
    if (!el) return false;
    const st = getComputedStyle(el);
    if (st.display === 'none' || st.visibility === 'hidden' || st.opacity === '0') return false;
    const rects = el.getClientRects();
    return !!rects && rects.length > 0;
  }
  function rect(el) { return el.getBoundingClientRect(); }
  function intersects(a, b, m = 0) {
    return !(a.right < b.left - m || a.left > b.right + m || a.bottom < b.top - m || a.top > b.bottom + m);
  }
  function viewport() {
    return { w: window.innerWidth, h: window.innerHeight };
  }
  function ensureInView(el, { margin = 12 } = {}) {
    return new Promise(async (resolve) => {
      if (!el) return resolve();
      const r = rect(el), vh = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
      if (r.top < margin || r.bottom > vh - margin) {
        try { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_){}
        await sleep(220);
      }
      resolve();
    });
  }
  function isInteractable(el) {
    if (!isVisible(el)) return false;
    const r = rect(el);
    const cx = Math.min(Math.max(r.left + r.width/2, 0), window.innerWidth - 1);
    const cy = Math.min(Math.max(r.top + r.height/2, 0), window.innerHeight - 1);
    const topEl = document.elementFromPoint(cx, cy);
    return topEl && (el === topEl || el.contains(topEl));
  }
  async function waitFor(selector, { timeout = 15000, interval = 100 } = {}) {
    return new Promise((resolve, reject) => {
      const t0 = performance.now();
      (function tick() {
        const el = qs(selector);
        if (el) return resolve(el);
        if (performance.now() - t0 > timeout) return reject(new Error('Timeout: ' + selector));
        setTimeout(tick, interval);
      })();
    });
  }
  async function waitUntilInteractable(el, { timeout = 20000 } = {}) {
    const t0 = performance.now();
    while (performance.now() - t0 < timeout) {
      if (isInteractable(el)) return true;
      await sleep(80);
    }
    return false;
  }

  /* ====== هوية الطالب + كوكي ====== */
  function getStudentId() {
    if (window.__STUDENT_ID__) return String(window.__STUDENT_ID__);
    const byData = document.body?.getAttribute?.('data-student-id');
    if (byData) return String(byData);
    return 'anonymous';
  }
  function getCookie(name) {
    const escaped = String(name).replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&');
    const rx = new RegExp('(?:^|;\\s*)' + escaped + '=([^;]*)');
    const m = document.cookie.match(rx);
    return m ? decodeURIComponent(m[1]) : null;
  }
  function setCookie(name, value, { days = 730, domain, path = '/' } = {}) {
    let expires = '';
    if (days) {
      const d = new Date(); d.setTime(d.getTime() + days*24*60*60*1000);
      expires = '; expires=' + d.toUTCString();
    }
    const dom = domain ? '; domain=' + domain : '';
    document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=' + path + dom;
  }
  function deleteCookie(name, { domain, path = '/' } = {}) {
    const dom = domain ? '; domain=' + domain : '';
    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=' + path + dom;
  }
  function getBaseDomain(host) {
    if (window.__ONB_COOKIE_DOMAIN__) return window.__ONB_COOKIE_DOMAIN__;
    const parts = (host || location.hostname).split('.');
    if (parts.length <= 2) return location.hostname;
    return parts.slice(-2).join('.');
  }

  /* ====== مفاتيح الحالة ====== */
  function makeKeys(uid) {
    const BASE = `onboarding:stations:v6:uid:${uid}`;
    return { DONE: BASE, WIP: `${BASE}:inprogress`, AT: `${BASE}:at`, LOCK: `onb:v6:lock` };
  }
  function isTourDone(uid) {
    const K = makeKeys(uid);
    if (window.__ONBOARDING_DONE__ === true) return true;
    try { if (localStorage.getItem(K.DONE) === 'done') return true; } catch(_){}
    try { if (getCookie(`onb_v6_${uid}_done`) === '1') return true; } catch(_){}
    return false;
  }

  /* ====== قفل التبويب ====== */
  const ONB_CH = 'onb_tour_v6_channel';
  let bc = null;
  function acquireTabLock(uid) {
    if (window.__ONB_TOUR_LOCK__) return false;
    const K = makeKeys(uid);
    try {
      if (sessionStorage.getItem(K.LOCK) === '1') return false;
      sessionStorage.setItem(K.LOCK, '1');
    } catch (_){}
    window.__ONB_TOUR_LOCK__ = true;
    try { bc = new BroadcastChannel(ONB_CH); bc.postMessage({ type: 'LOCK', uid, ts: Date.now() }); } catch (_){}
    return true;
  }
  function releaseTabLock(uid) {
    const K = makeKeys(uid);
    try { sessionStorage.removeItem(K.LOCK); } catch(_){}
    window.__ONB_TOUR_LOCK__ = false;
    try { bc?.postMessage?.({ type: 'RELEASE', uid, ts: Date.now() }); bc?.close?.(); } catch (_){}
    bc = null;
  }

  /* ====== Selectors ====== */
  const SEL = {
    sidebar: '.sidebar, #student-sidebar',
    themeToggleBtn: 'button.action-btn-nav.theme-toggle-btn',

    sideWelcome:   '.menu-link[data-section="welcome"]',
    sideDashboard: '.menu-link[data-section="dashboard"]',
    sideService:   '.menu-item.service-item > a.menu-link[data-section="service"], .menu-link[data-section="service"]',
    sideUpgrade:   '.menu-link[data-section="upgrade"]',
    sideAccount:   'li.menu-item > a.menu-link[href*="auth.howplatform.net/profile?active=diploma"]',

    sectionNav:  'nav.section-nav',
    sectionBtns: 'nav.section-nav .nav-button',

    svcBtn:            '#svcNewRequestBtn',
    serviceModal:      '#serviceRequestModal',
    serviceModalClose: '#srvReqCloseBtn',
    quickActions:      '.quick-actions-container',

    courseTitle:    '#course-title, .course-title',
    courseDesc:     '#course-description, .course-description',
    weeksContainer: '#weeks-container',
    weekTitle:      '.week-title',
    tasksArea:      '.tasks-grid',
    filesArea:      '.files-chips',
    detailsBtn:     'button.action-btn.view-btn[data-open-task="1"]',

    firstSemesterLinkDoc: '.menu-item[data-course-item] > a.menu-link, .menu-item[data-course-item="true"] > a.menu-link',

    tdSiblings: '#td-siblings',
    tdDescBody: '.td-desc-body',
    tdMedia:    '#td-media',

    serviceSearchHeader: '.sidebar-header',
    serviceList:         '#serviceList',
    chatContainer:       '#chatContainer',

    upgCurrent:      '.upg-card.upg-current',
    upgPlansCard:    'section.upg-card.upg-plans-card',
    upgPlansHead:    'section.upg-card.upg-plans-card .upg-card__head',
    btnGetCard:      '#btn-get-card',
    btnActivateCard: 'button.btn[data-action="open-activate"]'
  };

  /* ====== Intro.js loader + click-through CSS ====== */
  function ensureIntroLoaded() {
    return new Promise((resolve) => {
      if (window.introJs && typeof window.introJs.tour === 'function') return resolve();
      const link = document.createElement('link'); link.rel = 'stylesheet';
      link.href = 'https://unpkg.com/intro.js/minified/introjs.min.css'; document.head.appendChild(link);
      const s = document.createElement('script'); s.src = 'https://unpkg.com/intro.js/minified/intro.min.js';
      s.onload = () => resolve(); document.head.appendChild(s);
    });
  }
  (function injectClickThroughStyles() {
    const css = `
      .introjs-overlay, .introjs-helperLayer, .introjs-tooltipReferenceLayer { pointer-events: none !important; }
      .introjs-tooltip, .introjs-tooltip * { pointer-events: auto !important; }
      .introjs-tooltip.introjs-custom-rtl { max-width: min(92vw, 380px); border-radius: 14px; }
      @media (max-width: 420px){ .introjs-tooltip.introjs-custom-rtl { max-width: 94vw; } }
    `;
    const tag = document.createElement('style');
    tag.id = 'onb-intro-clickthrough';
    tag.appendChild(document.createTextNode(css));
    document.head.appendChild(tag);
  })();

  /* ====== تموضع ذكي للتولتيب ====== */
  function estimateTooltipSize() {
    // تقدير أولي قبل الرسم: أفضل من لا شيء
    const vw = viewport().w;
    const w = Math.min(380, Math.floor(vw * 0.92));
    const h = IS_SMALL() ? 160 : 180;
    return { w, h };
  }
  function chooseInitialPosition(el, preferred = 'auto') {
    const r = rect(el), { w: vw, h: vh } = viewport(), est = estimateTooltipSize();
    const space = { top: r.top, bottom: vh - r.bottom, left: r.left, right: vw - r.right };
    const order = [];
    if (preferred && preferred !== 'auto') order.push(preferred);
    // تفضيل رأسي أولاً للهواتف
    if (space.bottom >= est.h) order.push('bottom');
    if (space.top    >= est.h) order.push('top');
    if (space.right  >= Math.max(240, est.w*0.8)) order.push('right');
    if (space.left   >= Math.max(240, est.w*0.8)) order.push('left');
    order.push('floating');
    // إزالة التكرارات
    return Array.from(new Set(order));
  }
  async function tryReposition(t, el, tried) {
    // نحاول تغيير الموضع حتى لا يتقاطع التولتيب مع الهدف
    for (const pos of tried) {
      const step = { element: el, intro: el.__introHtml || '', position: pos };
      t.setOptions({ steps: [step] });
      t.refresh();
      await sleep(30);
      const tip = qs('.introjs-tooltip');
      if (!tip) continue;
      if (!intersects(rect(tip), rect(el), 8)) return pos;
    }
    // الملاذ الأخير: نبقى على آخر موضع
    return tried[tried.length - 1] || 'floating';
  }

  const IntroAdaptor = {
    tour: null,
    async open({ element, html, preferredPosition = 'auto', showButtons = true, doneLabel = 'تم', tooltipClass = 'introjs-custom-rtl' }) {
      await ensureIntroLoaded();
      const t = window.introJs.tour();
      element.__introHtml = html;

      // اختيار أولي للموضع
      const order = chooseInitialPosition(element, preferredPosition);
      const firstPos = order[0] || 'floating';

      t.setOptions({
        rtl: true,
        tooltipClass,
        highlightClass: 'onboarding-pulse',
        disableInteraction: false,
        scrollTo: false,
        showStepNumbers: false,
        showBullets: false,
        exitOnEsc: false,
        exitOnOverlayClick: false,
        overlayOpacity: 0, // نستخدم القناع المخصص
        steps: [{ element, intro: html, position: firstPos }],
        showButtons,
        doneLabel
      });

      this.tour = t;
      t.start();
      await sleep(20);

      // إعادة التموضع الذكي إن كان هناك تداخل
      const posUsed = await tryReposition(t, element, order);
      log('tooltip positioned at:', posUsed);
      return t;
    },
    refresh() { try { if (this.tour && typeof this.tour.refresh === 'function') this.tour.refresh(); } catch (_){ } },
    async close() {
      try { this.tour?.exit?.(); } catch (_){}
      this.tour = null;
      qsa('.introjs-tooltip,.introjs-overlay,.introjs-helperLayer').forEach(n => n.remove());
      qsa('.introjs-showElement,.introjs-relativePosition').forEach(n => n.classList.remove('introjs-showElement','introjs-relativePosition'));
      await sleep(10);
    }
  };

  /* ====== Overlay Mask (SVG/Quad) — مع عدم حجب النقر ====== */
  const Mask = (() => {
    let usingSVG = false, target = null, opts = { padding: 8, cornerRadius: 12 };
    let extraScrollEl = null, raf = 0, ro = null, io = null;
    let rootQuad = null, pieces = null;
    let svgRoot = null, svgRectDim = null, svgHole = null;

    function canUseSVG() {
      try { return !!document.createElementNS && (document.documentElement.style.mask !== undefined || document.documentElement.style.webkitMask !== undefined); }
      catch (_) { return false; }
    }
    function ensureDomQuad() {
      if (rootQuad) return;
      rootQuad = document.createElement('div');
      rootQuad.className = 'onb-dim';
      Object.assign(rootQuad.style, { zIndex: '999996', pointerEvents: 'none' });
      const mk = (cn) => { const d = document.createElement('div'); d.className = 'onb-dim-piece ' + cn; d.style.pointerEvents = 'none'; rootQuad.appendChild(d); return d; };
      const t = mk('onb-dim-top'), b = mk('onb-dim-bottom'), l = mk('onb-dim-left'), r = mk('onb-dim-right');
      pieces = { t, b, l, r };
      document.body.appendChild(rootQuad);
    }
    function layoutQuad() {
      if (!rootQuad || !target) return;
      const r = rect(target), pad = opts.padding || 8;
      const top = Math.max(0, r.top - pad), left = Math.max(0, r.left - pad);
      const right = Math.max(0, window.innerWidth - (r.right + pad)), bottom = Math.max(0, window.innerHeight - (r.bottom + pad));
      pieces.t.style.top = '0px'; pieces.t.style.left = '0px'; pieces.t.style.right = '0px'; pieces.t.style.height = `${top}px`;
      pieces.b.style.bottom = '0px'; pieces.b.style.left = '0px'; pieces.b.style.right = '0px'; pieces.b.style.height = `${bottom}px`;
      pieces.l.style.top = `${top}px`; pieces.l.style.bottom = `${bottom}px`; pieces.l.style.left = '0px'; pieces.l.style.width = `${left}px`;
      pieces.r.style.top = `${top}px`; pieces.r.style.bottom = `${bottom}px`; pieces.r.style.right = '0px'; pieces.r.style.width = `${right}px`;
    }
    function destroyQuad() { try { rootQuad?.remove?.(); } catch(_){ } rootQuad = null; pieces = null; }

    function ensureDomSVG() {
      if (svgRoot) return;
      svgRoot = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      Object.assign(svgRoot.style, { position: 'fixed', inset: '0', zIndex: '999996', pointerEvents: 'none' });
      svgRoot.setAttribute('width', '100%'); svgRoot.setAttribute('height', '100%');
      svgRoot.setAttribute('viewBox', `0 0 ${window.innerWidth} ${window.innerHeight}`);
      const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
      const mask = document.createElementNS('http://www.w3.org/2000/svg', 'mask'); mask.setAttribute('id', 'onb-mask');
      const whiteBg = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
      whiteBg.setAttribute('x', '0'); whiteBg.setAttribute('y', '0'); whiteBg.setAttribute('width', '100%'); whiteBg.setAttribute('height', '100%'); whiteBg.setAttribute('fill', 'white');
      svgHole = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
      svgHole.setAttribute('x', '0'); svgHole.setAttribute('y', '0'); svgHole.setAttribute('width', '0'); svgHole.setAttribute('height', '0');
      svgHole.setAttribute('rx', String(opts.cornerRadius || 12)); svgHole.setAttribute('ry', String(opts.cornerRadius || 12)); svgHole.setAttribute('fill', 'black');
      mask.appendChild(whiteBg); mask.appendChild(svgHole); defs.appendChild(mask);
      svgRectDim = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
      svgRectDim.setAttribute('x', '0'); svgRectDim.setAttribute('y', '0'); svgRectDim.setAttribute('width', '100%'); svgRectDim.setAttribute('height', '100%');
      svgRectDim.setAttribute('fill', 'rgba(10,12,14,.58)'); svgRectDim.setAttribute('mask', 'url(#onb-mask)');
      svgRoot.appendChild(defs); svgRoot.appendChild(svgRectDim); document.body.appendChild(svgRoot);
    }
    function layoutSVG() {
      if (!svgRoot || !target) return;
      svgRoot.setAttribute('viewBox', `0 0 ${window.innerWidth} ${window.innerHeight}`);
      const r = rect(target), pad = opts.padding || 8;
      const x = Math.max(0, r.left - pad), y = Math.max(0, r.top - pad);
      const w = Math.min(window.innerWidth, r.width + pad*2), h = Math.min(window.innerHeight, r.height + pad*2);
      svgHole.setAttribute('x', String(x)); svgHole.setAttribute('y', String(y));
      svgHole.setAttribute('width', String(w)); svgHole.setAttribute('height', String(h));
      svgHole.setAttribute('rx', String(opts.cornerRadius || 12)); svgHole.setAttribute('ry', String(opts.cornerRadius || 12));
    }
    function destroySVG() { try { svgRoot?.remove?.(); } catch(_){ } svgRoot = svgRectDim = svgHole = null; }

    function schedule() {
      if (raf) return; raf = requestAnimationFrame(() => { raf = 0; usingSVG ? layoutSVG() : layoutQuad(); });
    }
    function bind() {
      window.addEventListener('scroll', schedule, { passive: true });
      window.addEventListener('resize', schedule, { passive: true });
      window.addEventListener('orientationchange', schedule, { passive: true });
      if (extraScrollEl) extraScrollEl.addEventListener('scroll', schedule, { passive: true });
      try { ro = new ResizeObserver(schedule); ro.observe(document.documentElement); if (target) ro.observe(target); } catch(_){}
      try { io = new IntersectionObserver(schedule, { threshold: [0,.25,.5,.75,1] }); io.observe(target); } catch(_){}
    }
    function unbind() {
      window.removeEventListener('scroll', schedule);
      window.removeEventListener('resize', schedule);
      window.removeEventListener('orientationchange', schedule);
      if (extraScrollEl) extraScrollEl.removeEventListener('scroll', schedule);
      try { ro?.disconnect?.(); } catch(_){}
      try { io?.disconnect?.(); } catch(_){}
      ro = io = null;
    }

    return {
      open(el, options = {}) {
        target = el; opts = { padding: 8, cornerRadius: 12, ...options };
        extraScrollEl = options.extraScrollEl || null;
        usingSVG = canUseSVG();
        if (usingSVG) { ensureDomSVG(); layoutSVG(); } else { ensureDomQuad(); layoutQuad(); }
        bind();
      },
      close() {
        unbind();
        if (usingSVG) destroySVG(); else destroyQuad();
        target = null; extraScrollEl = null;
      },
      update() { schedule(); }
    };
  })();

  /* ====== Sidebar helpers ====== */
  function isSidebarOpen() {
    const sb = qs(SEL.sidebar);
    if (!sb) return false;
    const probe = qs(SEL.sideWelcome) || sb.querySelector('a,button,[role="button"]');
    return !!(probe && isVisible(probe));
  }
  function showSidebarToggleHint() {
    const btn = qs('#sidebarToggleBtn');
    if (!btn) return;
    btn.classList.add('onb-show', 'onb-pulse');
    btn.style.zIndex = '1000000';
  }
  function hideSidebarToggleHint() {
    const btn = qs('#sidebarToggleBtn');
    if (!btn) return;
    btn.classList.remove('onb-pulse');
    setTimeout(() => btn.classList.remove('onb-show'), 220);
  }
  async function ensureSidebarOpenFlow(orc, msg = 'افتح <b>القائمة الجانبية</b> أولًا للمتابعة.') {
    const btn = qs('#sidebarToggleBtn');
    if (!btn) return;
    showSidebarToggleHint();
    await ensureInView(btn);
    const coach = await orc.showCoach({
      el: btn,
      html: msg,
      preferredPosition: 'right',
      showButtons: true
    });
    // انتظر حتى تُفتح
    const t0 = performance.now();
    while (!isSidebarOpen() && performance.now() - t0 < 20000) { await sleep(120); }
    await new Promise(resolve => {
      const handler = (e) => { if (!e.target?.closest?.('.introjs-donebutton')) return; document.removeEventListener('click', handler, true); resolve(true); };
      document.addEventListener('click', handler, true);
    });
    await coach.exit?.(); // لا بأس إن لم توجد
    hideSidebarToggleHint();
  }

  /* ====== Glow ====== */
  function addGlow(el) { el?.classList?.add('onb-glow'); }
  function rmGlow(el)  { el?.classList?.remove('onb-glow'); }

  /* ====== Orchestrator ====== */
  function createOrchestrator() {
    let cancelled = false;
    async function hardCleanup() {
      try { await IntroAdaptor.close(); } catch(_){}
      try { Mask.close(); } catch(_){}
      hideSidebarToggleHint();
    }
    function cancel() { cancelled = true; hardCleanup(); }
    window.OnboardingTourV6 = window.OnboardingTourV6 || {};
    window.OnboardingTourV6.__cancelActive = cancel;

    let raf = 0;
    const throttle = (fn) => () => { if (raf) return; raf = requestAnimationFrame(() => { raf = 0; fn(); }); };

    async function showCoach({ el, html, preferredPosition = 'auto', showButtons = true, mask = true, maskPadding = 8, cornerRadius = 12 }) {
      if (cancelled) return { close: async () => {} };
      const sidebar = qs(SEL.sidebar);
      const targetInSidebar = sidebar && el && sidebar.contains(el);
      if (targetInSidebar && !isSidebarOpen()) {
        await ensureSidebarOpenFlow({ showCoach }, 'لفتح هذه الصفحة اختر زر <b>فتح القائمة</b> أولاً.');
      }
      addGlow(el);
      await ensureInView(el);
      const scroller = (() => {
        let cur = el?.parentElement;
        while (cur && cur !== document.body) {
          const st = getComputedStyle(cur);
          if ((st.overflowY === 'auto' || st.overflowY === 'scroll') && cur.scrollHeight > cur.clientHeight + 2) return cur;
          cur = cur.parentElement;
        }
        return window;
      })();
      const isWin = (scroller === window);
      if (mask) Mask.open(el, { padding: maskPadding, cornerRadius, extraScrollEl: (isWin ? null : scroller) });
      await IntroAdaptor.open({ element: el, html, preferredPosition, showButtons });
      const reflow = throttle(() => { try { IntroAdaptor.refresh(); } catch(_){ } try { Mask.update(); } catch(_){ } });
      const onScroll = reflow;
      window.addEventListener('resize', reflow, { passive: true });
      window.addEventListener('orientationchange', reflow, { passive: true });
      (isWin ? window : scroller).addEventListener('scroll', onScroll, { passive: true });

      return {
        async close() {
          (isWin ? window : scroller).removeEventListener('scroll', onScroll);
          window.removeEventListener('resize', reflow);
          window.removeEventListener('orientationchange', reflow);
          await IntroAdaptor.close();
          rmGlow(el);
          if (mask) Mask.close();
          await sleep(30);
        }
      };
    }

    return { cancel, showCoach };
  }

  /* ====== Steps ====== */
  const Steps = {
    async requireClick(orc, selector, hint, preferredPos = 'right') {
      const el = await waitFor(selector);
      // إن كان الهدف داخل السايدبار وهو مغلق → أظهر تدفق فتح السايدبار أولاً
      const sb = qs(SEL.sidebar);
      const inSidebar = sb && el && sb.contains(el);
      if (inSidebar && !isSidebarOpen()) {
        await ensureSidebarOpenFlow(orc, 'لفتح هذه الصفحة اضغط أولًا على زر <b>فتح القائمة</b>.');
        // انتظر أن يصبح الهدف قابلاً للتفاعل
        await waitUntilInteractable(el);
      }

      await ensureInView(el);
      const coach = await orc.showCoach({ el, html: hint, preferredPosition: preferredPos, showButtons: false, mask: true });
      // انتظر النقرة على الهدف نفسه (click/pointer/touch/keyboard)
      await new Promise((resolve) => {
        const handler = (e) => { if (!e.target?.closest?.(selector)) return; cleanup(); resolve(true); };
        const keyHandler = (e) => {
          const t = document.activeElement?.closest?.(selector);
          if (!t) return;
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cleanup(); resolve(true); }
        };
        function cleanup() {
          document.removeEventListener('click', handler, true);
          document.removeEventListener('pointerup', handler, true);
          document.removeEventListener('touchend', handler, true);
          document.removeEventListener('keydown', keyHandler, true);
        }
        document.addEventListener('click', handler, true);
        document.addEventListener('pointerup', handler, true);
        document.addEventListener('touchend', handler, true);
        document.addEventListener('keydown', keyHandler, true);
      });
      await coach.close();
      return el;
    },

    async infoConfirm(orc, elOrSel, html, preferredPos = 'auto') {
      const el = (typeof elOrSel === 'string') ? await waitFor(elOrSel) : elOrSel;
      await ensureInView(el);
      const coach = await orc.showCoach({ el, html, preferredPosition: preferredPos, showButtons: true, mask: true });
      await new Promise(resolve => {
        const handler = (e) => { if (!e.target?.closest?.('.introjs-donebutton')) return; document.removeEventListener('click', handler, true); resolve(true); };
        document.addEventListener('click', handler, true);
      });
      await coach.close();
    },

    async hoverWithConfirm(orc, elOrSel, ms, html, preferredPos = 'auto') {
      const el = (typeof elOrSel === 'string') ? await waitFor(elOrSel) : elOrSel;
      await ensureInView(el);
      if (IS_TOUCH) {
        await Steps.infoConfirm(orc, el, html + '<br><small>اضغط "تم" للمتابعة.</small>', preferredPos);
        return;
      }
      let hoveredMs = 0, inside = false, lastTs = 0;
      const onMove = (e) => {
        if (!el.contains(e.target)) { inside = false; return; }
        const now = performance.now();
        if (!inside) { inside = true; lastTs = now; } else { hoveredMs += (now - lastTs); lastTs = now; }
      };
      document.addEventListener('mousemove', onMove, { capture: true, passive: true });
      const coach = await orc.showCoach({ el, html, preferredPosition: preferredPos, showButtons: true, mask: true });
      const doneBtn = qs('.introjs-donebutton');
      if (doneBtn) {
        doneBtn.disabled = true; doneBtn.classList.add('introjs-disabled');
        const t0 = performance.now();
        while (hoveredMs < ms && performance.now() - t0 < ms + 8000) { await sleep(60); }
        doneBtn.disabled = false; doneBtn.classList.remove('introjs-disabled');
      }
      await new Promise(resolve => {
        const handler = (e) => { if (!e.target?.closest?.('.introjs-donebutton')) return; document.removeEventListener('click', handler, true); resolve(true); };
        document.addEventListener('click', handler, true);
      });
      document.removeEventListener('mousemove', onMove, true);
      await coach.close();
    },

    async scrollContainerWithConfirm(orc, containerSel, { minDelta = 300, bottomRatio = 0.95, timeout = 20000 }, html, preferredPos = 'auto') {
      const container = await waitFor(containerSel);
      await ensureInView(container);
      const scroller = (() => {
        let cur = container?.parentElement;
        while (cur && cur !== document.body) {
          const st = getComputedStyle(cur);
          if ((st.overflowY === 'auto' || st.overflowY === 'scroll') && cur.scrollHeight > cur.clientHeight + 2) return cur;
          cur = cur.parentElement;
        }
        return window;
      })();
      const isWin = (scroller === window);
      let movedDown = 0;
      let lastTop = isWin ? (window.scrollY || document.documentElement.scrollTop || 0) : scroller.scrollTop;
      const onScrollTrack = () => {
        const curTop = isWin ? (window.scrollY || document.documentElement.scrollTop || 0) : scroller.scrollTop;
        if (curTop > lastTop) movedDown += (curTop - lastTop);
        lastTop = curTop;
      };
      (isWin ? window : scroller).addEventListener('scroll', onScrollTrack, { passive: true });
      const reachedBottom = () => {
        if (isWin) {
          const r = rect(container), vh = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
          return r.bottom <= vh * bottomRatio;
        }
        return (Math.ceil(scroller.scrollTop + scroller.clientHeight) >= scroller.scrollHeight - 8);
      };
      const coach = await orc.showCoach({ el: container, html, preferredPosition: preferredPos, showButtons: true, mask: true });
      const doneBtn = qs('.introjs-donebutton');
      if (doneBtn) {
        doneBtn.disabled = true; doneBtn.classList.add('introjs-disabled');
        const t0 = performance.now();
        while ((movedDown < minDelta || !reachedBottom()) && performance.now() - t0 < timeout) { await sleep(80); }
        doneBtn.disabled = false; doneBtn.classList.remove('introjs-disabled');
      }
      await new Promise(resolve => {
        const handler = (e) => { if (!e.target?.closest?.('.introjs-donebutton')) return; document.removeEventListener('click', handler, true); resolve(true); };
        document.addEventListener('click', handler, true);
      });
      (isWin ? window : scroller).removeEventListener('scroll', onScrollTrack);
      await coach.close();
    },

    async visibleWithConfirm(orc, elOrSel, { threshold = .55, timeout = 20000 }, html, preferredPos = 'auto') {
      const el = (typeof elOrSel === 'string') ? await waitFor(elOrSel) : elOrSel;
      await ensureInView(el);
      let visible = false;
      const io = new IntersectionObserver((entries) => {
        for (const ent of entries) if (ent.target === el && ent.intersectionRatio >= threshold) { visible = true; }
      }, { root: null, threshold: [threshold] });
      io.observe(el);
      const coach = await orc.showCoach({ el, html, preferredPosition: preferredPos, showButtons: true, mask: true });
      const doneBtn = qs('.introjs-donebutton');
      if (doneBtn) {
        doneBtn.disabled = true; doneBtn.classList.add('introjs-disabled');
        const t0 = performance.now();
        while (!visible && performance.now() - t0 < timeout) { await sleep(100); }
        doneBtn.disabled = false; doneBtn.classList.remove('introjs-disabled');
      }
      await new Promise(resolve => {
        const handler = (e) => { if (!e.target?.closest?.('.introjs-donebutton')) return; document.removeEventListener('click', handler, true); resolve(true); };
        document.addEventListener('click', handler, true);
      });
      io.disconnect();
      await coach.close();
    }
  };

  /* ====== shouldStart + السيناريو (نفس محطاتك مع التحسينات) ====== */
  function shouldStart() {
    const force = /[?&]tour=1\b/.test(location.search) || (window.__SHOW_ONBOARDING__ === true);
    const uid = getStudentId(); const K = makeKeys(uid);
    if (force) return true;
    if (isTourDone(uid)) return false;
    try { if (localStorage.getItem(K.WIP) === '1') return false; } catch(_){}
    try { if (sessionStorage.getItem(K.LOCK) === '1') return false; } catch(_){}
    return true;
  }

  async function runScenario() {
    const orc = createOrchestrator();
    const uid = getStudentId(); const K = makeKeys(uid);
    if (!acquireTabLock(uid)) return;

    try {
      try { localStorage.setItem(K.WIP, '1'); } catch (_){}

      /* (0) Theme toggle */
      const themeBtn = await waitFor(SEL.themeToggleBtn).catch(()=>null);
      if (themeBtn) await Steps.infoConfirm(orc, themeBtn, 'يمكنك <b>تبديل الوضع</b> بين الداكن والفاتح من هذا الزر.', 'bottom');

      /* (1) Welcome */
      await Steps.requireClick(orc, SEL.sideWelcome, 'اضغط على <b>الصفحة الترحيبية</b> لبدء الجولة.', 'right');
      await waitFor(SEL.sectionNav);
      {
        const nav = await waitFor(SEL.sectionNav);
        let visited = 0; const clicked = new Set();
        const onClick = (e) => { const btn = e.target?.closest?.(SEL.sectionBtns); if (!btn) return;
          const id = btn.id || btn.getAttribute('data-section-target') || btn.textContent.trim();
          if (!clicked.has(id)) { clicked.add(id); visited++; }
        };
        document.addEventListener('click', onClick, true);
        const coach = await orc.showCoach({ el: nav, html: 'تنقّل بين <b>تبويبين</b> على الأقل، ثم اضغط <b>تم</b>.', preferredPosition: 'bottom', showButtons: true, mask: true });
        const doneBtn = qs('.introjs-donebutton'); if (doneBtn) { doneBtn.disabled = true; while (visited < 2) { await sleep(120); } doneBtn.disabled = false; }
        await new Promise(r => { const h = (e) => { if (!e.target?.closest?.('.introjs-donebutton')) return; document.removeEventListener('click', h, true); r(true); }; document.addEventListener('click', h, true); });
        document.removeEventListener('click', onClick, true);
        await coach.close();
      }

      /* (2) Dashboard */
      await Steps.requireClick(orc, SEL.sideDashboard, 'اضغط على <b>لوحة التحكم</b> للمتابعة.', 'right');
      await waitFor(SEL.svcBtn);
      await Steps.requireClick(orc, SEL.svcBtn, 'افتح <b>طلب خدمة متخصصة</b>.', 'bottom');
      const modal = qs(SEL.serviceModal); if (modal && isVisible(modal)) await Steps.requireClick(orc, SEL.serviceModalClose, 'الآن <b>أغلق النافذة</b> للمتابعة.', 'top');
      await Steps.hoverWithConfirm(orc, SEL.quickActions, 1800, 'تعرّف على <b>منطقة الأدوات</b> لبضع ثوانٍ، ثم اضغط <b>تم</b>.', 'top');

      /* (3) Semester → Course */
      await Steps.requireClick(orc, SEL.firstSemesterLinkDoc, 'اضغط لفتح <b>الفصل الأول</b>.', 'right');
      // هنا يمكن تخصيص اختيار مساق محدد كما في كودك إن رغبت

      /* (4) Course page */
      const title = qs(SEL.courseTitle); if (title) await Steps.hoverWithConfirm(orc, title, 900, 'هذا <b>عنوان المساق</b> — حرّك المؤشر عليه، ثم <b>تم</b>.', 'bottom');
      const desc  = qs(SEL.courseDesc);  if (desc)  await Steps.hoverWithConfirm(orc, desc, 900, 'هذا <b>وصف المساق</b> — حرّك المؤشر عليه، ثم <b>تم</b>.', 'bottom');

      await Steps.scrollContainerWithConfirm(orc, SEL.weeksContainer, { minDelta: 300, bottomRatio: .95, timeout: 20000 }, 'انزل لأسفل لاستعراض <b>الأسابيع</b> حتى النهاية، ثم اضغط <b>تم</b>.', 'top');
      await Steps.visibleWithConfirm(orc, SEL.weekTitle, { threshold: .55, timeout: 20000 }, 'ارجع للأعلى حتى يظهر <b>الأسبوع الأول</b> داخل الشاشة، ثم <b>تم</b>.', 'right');

      await Steps.requireClick(orc, SEL.tasksArea, 'هذه <b>منطقة المهام</b> — اضغط داخلها للمتابعة.', 'top');
      const files = qs(SEL.filesArea); if (files) await Steps.hoverWithConfirm(orc, files, 900, 'هذه <b>منطقة الملفات</b> — حرّك المؤشر فوقها، ثم <b>تم</b>.', 'top');
      await Steps.requireClick(orc, SEL.detailsBtn, 'اضغط <b>تفاصيل</b> للمتابعة.', 'left');

      await Promise.race([ waitFor(SEL.tdSiblings).catch(()=>null), waitFor(SEL.tdDescBody).catch(()=>null), waitFor(SEL.tdMedia).catch(()=>null) ]);
      try { await Steps.infoConfirm(orc, SEL.tdSiblings, 'يمكنك <b>التنقّل بين مهام الأسبوع</b> من هنا.', 'bottom'); } catch (_){}
      try { await Steps.infoConfirm(orc, SEL.tdDescBody, 'اقرأ <b>تفاصيل المهمة</b> بعناية هنا.', 'right'); } catch (_){}
      try { await Steps.infoConfirm(orc, SEL.tdMedia, '📎 <b>المرفقات</b> تظهر وفق <b>خطة المستخدم</b>.', 'left'); } catch (_){}

      /* (5) Service */
      await Steps.requireClick(orc, SEL.sideService, 'انتقل إلى <b>طلبات الخدمة</b>.', 'right');
      const svcBtn = await waitFor(SEL.svcBtn).catch(()=>null);
      if (svcBtn) await Steps.infoConfirm(orc, SEL.svcBtn, 'قدّم <b>طلب خدمة</b> جديد من هنا.', 'bottom');
      const searchHdr = await waitFor(SEL.serviceSearchHeader).catch(()=>null);
      if (searchHdr) await Steps.infoConfirm(orc, SEL.serviceSearchHeader, 'ابحث وصفِّ حسب <b>الحالة</b>.', 'bottom');
      const svcList = await waitFor(SEL.serviceList).catch(()=>null);
      if (svcList) await Steps.infoConfirm(orc, SEL.serviceList, 'هذه قائمة <b>طلباتك السابقة</b>.', 'top');
      const chatBox = await waitFor(SEL.chatContainer).catch(()=>null);
      if (chatBox) await Steps.infoConfirm(orc, SEL.chatContainer, 'تواصل مع <b>الدعم</b> هنا.', 'left');

      /* (6) Upgrade */
      await Steps.requireClick(orc, SEL.sideUpgrade, 'انتقل إلى <b>الترقية / الاشتراكات</b>.', 'right');
      const upgCurrent = await waitFor(SEL.upgCurrent).catch(()=>null);
      if (upgCurrent) await Steps.infoConfirm(orc, SEL.upgCurrent, 'هذه <b>خطتك الحالية</b>.', 'bottom');
      const upgPlansAnchor = qs(SEL.upgPlansHead) || qs(SEL.upgPlansCard);
      if (upgPlansAnchor) await Steps.infoConfirm(orc, upgPlansAnchor, 'اطّلع على <b>الخطط المتاحة</b>.', 'bottom');
      const getCardAnchor = qs(SEL.btnGetCard) || qs('[data-ref="actionsBar"]');
      if (getCardAnchor) await Steps.infoConfirm(orc, getCardAnchor, 'احصل على <b>بطاقات الاشتراك</b> من هنا.', 'right');
      const btnActivate = await waitFor(SEL.btnActivateCard).catch(()=>null);
      if (btnActivate) await Steps.infoConfirm(orc, SEL.btnActivateCard, 'بعد الحصول على بطاقة، اضغط هنا لـ <b>تفعيلها</b>.', 'left');

      /* (7) Account */
      const accLink = await waitFor(SEL.sideAccount).catch(()=>null);
      if (accLink) await Steps.infoConfirm(orc, SEL.sideAccount, 'تعديل <b>الملف الشخصي</b> من هنا.', 'right');

      // ختم الجولة لمرة واحدة
      try {
        localStorage.setItem(K.DONE, 'done');
        localStorage.removeItem(K.WIP);
        localStorage.setItem(K.AT, new Date().toISOString());
        setCookie(`onb_v6_${uid}_done`, '1', { days: 730, domain: getBaseDomain() });
      } catch (_){}
    } catch (e) {
      console.error('[tour] failed:', e);
    } finally {
      try { await IntroAdaptor.close(); } catch(_){}
      try { Mask.close(); } catch(_){}
      try { if (!isTourDone(uid)) localStorage.removeItem(K.WIP); } catch (_){}
      releaseTabLock(uid);
    }
  }

  /* ====== Boot ====== */
  window.OnboardingTourV6 = {
    start() { if (!shouldStart()) return; runScenario(); },
    reset() {
      try {
        const uid = getStudentId(); const K = makeKeys(uid);
        localStorage.removeItem(K.DONE);
        localStorage.removeItem(K.WIP);
        localStorage.removeItem(K.AT);
        deleteCookie(`onb_v6_${uid}_done`, { domain: getBaseDomain() });
      } catch (_){}
    }
  };
  document.addEventListener('DOMContentLoaded', function () {
    if (!shouldStart()) return;
    runScenario();
  });
})();
