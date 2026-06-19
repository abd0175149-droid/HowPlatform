/* student_router.js - Router صفحات الطالب (IIFE + double-load guard + HTML cache)
 * v1.4.4 (delay fix)
 * - تأخير تنظيف URL بعد التنقل (300ms) كي لا يُمحى الهاش قبل معالجة الراوت
 * - إزالة التنظيف المسبق قبل التوجيه في StudentRouter.navigate
 */
(function () {
  'use strict';

  if (window.__STUDENT_ROUTER_LOADED__) return;
  window.__STUDENT_ROUTER_LOADED__ = true;

  const VERSION = 'v=2.0.1';
  // === Date-bounded full purge (Amman TZ) ===
  const CLEAR_ON_DATES = ['2025-11-09', '2025-11-10']; // ✅ اليوم وغدًا فقط

  function getTodayYmdAmman() {
    try {
      return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Amman',
        year: 'numeric', month: '2-digit', day: '2-digit'
      }).format(new Date());
    } catch (_) {
      // Fallback للمتصفحات القديمة
      const d = new Date();
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      return `${y}-${m}-${day}`;
    }
  }

  function isClearDay() {
    const today = getTodayYmdAmman();
    return CLEAR_ON_DATES.includes(today);
  }

  // حذف كل الكوكيز للمجال الحالي وما أمكن من المستويات العليا
  function clearAllCookies() {
    try {
      const cookieStr = document.cookie || '';
      if (!cookieStr) return;
      const cookies = cookieStr.split(';');
      const hostname = location.hostname;
      const parts = hostname.split('.');

      cookies.forEach(c => {
        const eq = c.indexOf('=');
        const name = (eq > -1 ? c.slice(0, eq) : c).trim();
        if (!name) return;

        // نحاول المسح على عدّة مستويات دومين + المسارات الشائعة
        const domains = [];
        for (let i = 0; i < parts.length; i++) {
          const dom = '.' + parts.slice(i).join('.');
          domains.push(dom);
        }
        domains.push(hostname); // المجال كاملًا أيضًا

        ['/', '/students', location.pathname].forEach(path => {
          domains.forEach(dom => {
            try {
              document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=${path}; domain=${dom}; SameSite=Lax`;
            } catch (_) { }
          });
          // بدون domain
          try {
            document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=${path}; SameSite=Lax`;
          } catch (_) { }
        });
      });
    } catch (_) { }
  }

  // مسح شامل للعميل (أقوى من clearBrowserCaches الموجودة)
  async function forceClearAllClientStorage() {
    // 1) Cache Storage
    if ('caches' in window) {
      try {
        const names = await caches.keys();
        await Promise.all(names.map(n => caches.delete(n)));
      } catch (_) { }
    }

    // 2) HTML cache داخل الذاكرة
    try { htmlCache.clear(); } catch (_) { }

    // 3) local/session storage — الكل بلا استثناء
    try { localStorage.clear(); } catch (_) { }
    try { sessionStorage.clear(); } catch (_) { }

    // 4) Cookies
    clearAllCookies();

    // 5) أبلِغ بقية الواجهات إن لزم
    try { window.dispatchEvent(new CustomEvent('students:client-cache-cleared')); } catch (_) { }
  }

  // تشغيل المسح في أيام محددة فقط، مرة واحدة في اليوم نفسه
  async function runDateBoundClientPurge() {
    if (!isClearDay()) return;

    const today = getTodayYmdAmman();

    // نستخدم window.name كحارس يعبر إعادة التحميل ولا يُمسح بالكوكيز/ستوريج
    if (window.name === `purged:${today}`) return;
    window.name = `purged:${today}`;

    try {
      // مُستحب: مسح كاش الخادم إن أمكن (لن يفشل بقية العملية لو أخفق)
      try { await callServerClearCache(); } catch (_) { }

      // مسح كامل على المتصفح
      await forceClearAllClientStorage();
    } finally {
      // إعادة التحميل لضمان حالة نظيفة بعد المسح
      try { location.replace(getStudentsBasePath()); } catch (_) { location.reload(); }
    }
  }

  /* ===== إعدادات تنظيف عنوان الصفحة ===== */
  // تأخير موحد للتنظيف بعد التنقل حتى تكتمل معالجة الراوت (hashchange/loaders)
  const CLEAN_DELAY_MS = 300;

  /* ===== موديولات الأقسام ===== */
  const SECTION_MODULES = {
    course1: () => import(`./sections/course1.js?${VERSION}`),
    course2: () => import(`./sections/course2.js?${VERSION}`),
    notifications: () => import(`./sections/notifications.js?${VERSION}`),
    account: () => import(`./sections/account.js?${VERSION}`),
    service: () => import(`./sections/service.js?${VERSION}`),
    'course-content': () => import(`./sections/coursecontent.js?${VERSION}`),
    dashboard: () => import(`./sections/dashboard.js?${VERSION}`),
    upgrade: () => import(`./sections/upgrade.js?${VERSION}`),
    'task-details': () => import(`./sections/taskdetails.js?${VERSION}`),
    welcome: () => import(`./sections/welcome.js?${VERSION}`)

  };

  /* ===== DOM ===== */
  const contentArea = document.getElementById('student-content-area');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.overlay');
  const menuToggle = document.querySelector('.menu-toggle');
  const toggleBtn = document.getElementById('sidebarToggleBtn');
  const menuItems = document.querySelectorAll('.menu-item');

  /* ===== حالة عامة ===== */
  let currentModule = null;
  let sidebarMeta = null;
  let currentLoadSeq = 0;

  /* ===== HTML Cache داخل الذاكرة ===== */
  const htmlCache = new Map();

  /* ===== Utilities / URL ===== */
  function getStudentsBasePath() {
    const path = window.location.pathname;
    const idx = path.toLowerCase().indexOf('/students');
    return idx >= 0 ? path.slice(0, idx + '/students'.length) : '/students';
  }

  // ✅ ينظّف الـ URL ليكون /students فقط (بدون hash/ query)
  function replaceUrlToStudentsBase() {
    try {
      const basePath = getStudentsBasePath();
      const cleanUrl = basePath; // لا نحتفظ بالهاش ولا الكويري
      const curr = window.location.pathname + (window.location.search || '') + (window.location.hash || '');
      if (curr !== cleanUrl) {
        window.history.replaceState({}, '', cleanUrl);
      }
    } catch (_) { }
  }

  const scheduleClean = (delay = CLEAN_DELAY_MS) => {
    try { setTimeout(replaceUrlToStudentsBase, delay); } catch (_) { }
  };

  // ✅ Patch تاريخ المتصفح لالتقاط أي push/replace من سكربتات أخرى (مثل صفحة المهمة)
  (function patchHistoryForCleanUrl() {
    try {
      const _pushState = history.pushState;
      const _replaceState = history.replaceState;
      history.pushState = function () {
        const ret = _pushState.apply(this, arguments);
        scheduleClean(); // ← نظّف بعد قليل
        return ret;
      };
      history.replaceState = function () {
        const ret = _replaceState.apply(this, arguments);
        scheduleClean();
        return ret;
      };
      // popstate عند رجوع/تقدم التاريخ
      window.addEventListener('popstate', () => scheduleClean(), { passive: true });
    } catch (_) { }
  })();

  // ✅ أي نقرة على <a> قد تغير الـ URL ديناميكيًا — ننظّف بعد النقرة بقليل
  document.addEventListener('click', (e) => {
    const a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a) return;
    // لا تتدخل في الروابط الخارجية/تبويب جديد
    const href = a.getAttribute('href') || '';
    const isExternal = a.target === '_blank' || /^https?:\/\//i.test(href);
    if (isExternal) return;
    // اترك فرصة للـ hashchange/المعالجة ثم نظّف
    scheduleClean();
  }, true);

  async function fetchStudentPage(page) {
    const key = `page:${page}`;
    if (htmlCache.has(key)) return htmlCache.get(key);
    const url = `/students/pages/${encodeURIComponent(page)}?_=${Date.now()}`;
    const res = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Cache-Control': 'no-store' }
    });
    if (!res.ok) throw new Error(`فشل تحميل الصفحة (HTTP ${res.status})`);
    const html = await res.text();
    htmlCache.set(key, html);
    return html;
  }
  async function fetchCourseContentPage() {
    return fetchStudentPage('course_content');
  }

  /* ===== Sidebar Meta ===== */
  async function fetchSidebarMeta(force = false) {
    const url = `/students/sidebar-meta?${force ? 'refresh=1&' : ''}_=${Date.now()}`;
    const res = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Accept': 'application/json', 'Cache-Control': 'no-store' }
    });
    if (!res.ok) throw new Error(`فشل جلب بيانات السايدبار (HTTP ${res.status})`);
    const data = await res.json();
    if (data.status !== 'ok' || !Array.isArray(data.semesters)) {
      throw new Error(data.message || 'بيانات غير متوقعة من الخادم');
    }
    sidebarMeta = data;
    renderSidebarMeta(data);
    return data;
  }

  function renderSidebarMeta(meta) {
    const container = document.querySelector('.sidebar .menu');
    if (!container) return;

    container.querySelectorAll('.menu-item[data-course-item]').forEach(el => el.remove());

    let semestersCount = 0;
    let coursesCount = 0;

    const serviceLink = container.querySelector('[data-section="service"]');
    const serviceItem = serviceLink ? serviceLink.closest('.menu-item') : null;

    (meta.semesters || []).forEach(block => {
      const sem = block.semester || {};
      const courses = Array.isArray(block.courses) ? block.courses : [];
      if (courses.length === 0) return;

      const item = createSemesterMenuItem({
        id: sem.id,
        name: sem.name || 'فصل دراسي',
        courses
      });

      if (serviceItem) container.insertBefore(item, serviceItem);
      else container.appendChild(item);

      semestersCount += 1;
      coursesCount += courses.length;
    });

    const semEl = document.getElementById('stat-semesters-count');
    const crsEl = document.getElementById('stat-courses-count');
    if (semEl) semEl.textContent = String(semestersCount);
    if (crsEl) crsEl.textContent = String(coursesCount);

    let mount = document.getElementById('courses-mount');
    if (semestersCount > 0) {
      if (mount) mount.remove();
    } else {
      if (!mount) {
        mount = document.createElement('li');
        mount.id = 'courses-mount';
        mount.className = 'menu-item';
        mount.setAttribute('data-course-mount', 'true');

        const a = document.createElement('a');
        a.href = '#';
        a.className = 'menu-link';
        a.tabIndex = -1;
        a.setAttribute('aria-disabled', 'true');
        a.innerHTML = `<i class="fas fa-info-circle"></i> <span>لا توجد مساقات متاحة حالياً</span>`;

        mount.appendChild(a);

        const serviceLink2 = container.querySelector('[data-section="service"]');
        const serviceItem2 = serviceLink2 ? serviceLink2.closest('.menu-item') : null;
        if (serviceItem2) container.insertBefore(mount, serviceItem2);
        else container.appendChild(mount);
      } else {
        const span = mount.querySelector('.menu-link span');
        if (span) span.textContent = 'لا توجد مساقات متاحة حالياً';
      }
    }
  }

  function createSemesterMenuItem(semester) {
    const li = document.createElement('li');
    li.className = 'menu-item';
    li.setAttribute('data-course-item', 'true');

    const link = document.createElement('a');
    link.href = '#';
    link.className = 'menu-link';
    link.innerHTML = `
      <i class="fas fa-layer-group"></i> ${semester.name}
      <i class="fas fa-chevron-down arrow"></i>
    `;

    const submenu = document.createElement('ul');
    submenu.className = 'submenu';

    semester.courses.forEach(cs => {
      const ci = document.createElement('li');
      ci.className = 'submenu-item';

      const a = document.createElement('a');
      a.href = '#';
      a.className = 'submenu-link';
      a.setAttribute('data-section', `course-${cs.offering_id}`);
      a.setAttribute('data-course-offering-id', cs.offering_id);
      a.innerHTML = `<i class="fas fa-book"></i> ${cs.name || cs.code || ('مساق #' + cs.course_id)}`;

      ci.appendChild(a);
      submenu.appendChild(ci);
    });

    li.appendChild(link);
    li.appendChild(submenu);

    link.addEventListener('click', function (e) {
      e.preventDefault();
      li.classList.toggle('active');
    });

    return li;
  }

  /* ===== Validation ===== */
  async function validateSection(section) {
    if (section === 'upgrade') return { valid: true, type: 'static' };
    try {
      const res = await fetch(`/students/api/validate-section/${encodeURIComponent(section)}`, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Cache-Control': 'no-store' }
      });
      return await res.json();
    } catch (e) {
      console.error('خطأ في التحقق من صحة القسم:', e);
      return { valid: false };
    }
  }

  /* ===== Loaders ===== */
  async function loadCourseContent(courseOfferingId, section) {
    const loadSeq = ++currentLoadSeq;

    try {
      if (currentModule && typeof currentModule.cleanup === 'function') {
        currentModule.cleanup();
      }

      showLoader('جاري تحميل محتوى المساق...');

      const dataRes = await fetch(`/students/course-content/${encodeURIComponent(courseOfferingId)}?_=${Date.now()}`, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Cache-Control': 'no-store' }
      });
      if (!dataRes.ok) throw new Error('خطأ في جلب محتوى المساق: HTTP ' + dataRes.status);
      const result = await dataRes.json();
      if (!result.success) throw new Error(result.message || 'خطأ في جلب محتوى المساق');

      const html = await fetchCourseContentPage();
      if (loadSeq !== currentLoadSeq) return null;

      contentArea.innerHTML = html;

      const mod = await SECTION_MODULES['course-content']();
      if (typeof mod.init === 'function') mod.init(result.data);
      currentModule = mod;

      setActiveLink(section);
      try { localStorage.setItem('student_active_section', section); } catch { }

      // ✅ تنظيف URL بعد كل تحميل ديناميكي (مع تأخير)
      scheduleClean();

      return mod;
    } catch (error) {
      console.error('خطأ في تحميل محتوى المساق:', error);
      if (loadSeq !== currentLoadSeq) return null;
      contentArea.innerHTML = `
        <div class="content-placeholder">
          <i class="fas fa-exclamation-triangle fa-2x"></i>
          <h3>حدث خطأ أثناء تحميل محتوى المساق</h3>
          <p>${error.message}</p>
          <button onclick="reloadCurrentSection()" class="btn btn-primary">
            <i class="fas fa-redo"></i> إعادة المحاولة
          </button>
        </div>`;

      scheduleClean();
      return null;
    }
  }

  async function loadStaticPage(section, loadSeq) {
    const html = await fetchStudentPage(section);
    if (loadSeq !== currentLoadSeq) return null;

    contentArea.innerHTML = html;

    // ✅ تنفيذ السكربتات بعد تحميل HTML (للدعم الديناميكي)
    const scripts = contentArea.querySelectorAll('script');
    scripts.forEach(script => {
      const newScript = document.createElement('script');
      if (script.src) {
        newScript.src = script.src;
      } else {
        newScript.textContent = script.textContent;
      }
      if (script.type) newScript.type = script.type;
      if (script.id) newScript.id = script.id;
      document.body.appendChild(newScript);
      script.remove();
    });

    let mod = null;
    if (SECTION_MODULES[section]) {
      mod = await SECTION_MODULES[section]();
      if (typeof mod.init === 'function') mod.init();
    }

    setActiveLink(section);
    try { localStorage.setItem('student_active_section', section); } catch { }

    // ✅ تنظيف URL بعد كل تحميل ديناميكي (مع تأخير)
    scheduleClean();
    return mod;
  }

  // 🆕 Loader لدعم الصفحات الديناميكية (HTML + Module.mount)
  async function loadDynamicPage(section, moduleName, loadSeq) {
    // 1) HTML
    const html = await fetchStudentPage(section);
    if (loadSeq !== currentLoadSeq) return null;
    contentArea.innerHTML = html;

    // 2) Module: من الخريطة أو import مباشر
    let mod = null;
    try {
      if (moduleName && SECTION_MODULES[moduleName]) {
        mod = await SECTION_MODULES[moduleName]();
      } else if (SECTION_MODULES[section]) {
        mod = await SECTION_MODULES[section]();
      } else {
        mod = await import(`/public/assets/js/students/${moduleName || section}.js?${VERSION}`);
      }
    } catch (e) {
      console.error('[router] فشل استيراد الموديول الديناميكي:', e);
      throw new Error('تعذّر تحميل مكوّن الصفحة');
    }

    // 3) تهيئة/ماونت بتوافق كامل:
    // - إن وُجد default.mount(container) ننفّذه (نمط الموديول الجديد)
    // - وإلا إن وُجد init() ننفّذه (نمط قديم)
    try {
      if (mod?.default && typeof mod.default.mount === 'function') {
        mod.default.mount(contentArea);
        // تجهيز unmount المتوافق
        currentModule = {
          cleanup: (typeof mod.default.unmount === 'function') ? mod.default.unmount : (mod.cleanup || null)
        };
      } else {
        if (typeof mod.init === 'function') mod.init();
        currentModule = mod;
      }
    } catch (e) {
      console.error('[router] خطأ أثناء تهيئة الموديول:', e);
      throw new Error('خطأ أثناء تهيئة مكوّن الصفحة');
    }

    setActiveLink(section);
    try { localStorage.setItem('student_active_section', section); } catch { }

    scheduleClean();
    return currentModule;
  }

  async function loadSection(section) {
    const loadSeq = ++currentLoadSeq;

    try {
      if (currentModule && typeof currentModule.cleanup === 'function') {
        currentModule.cleanup();
      }

      showLoader('جاري تحميل القسم...');

      const validation = await validateSection(section);
      if (!validation.valid) throw new Error('القسم غير موجود أو ليس لديك صلاحية للوصول إليه');

      if (validation.type === 'course') {
        return await loadCourseContent(validation.course_offering_id, section);
      }
      if (validation.type === 'static') {
        return await loadStaticPage(section, loadSeq);
      }
      // 🆕 دعم النوع الديناميكي
      if (validation.type === 'dynamic') {
        const moduleName = validation.module || section;
        return await loadDynamicPage(section, moduleName, loadSeq);
      }

      throw new Error('نوع القسم غير مدعوم');
    } catch (err) {
      console.error('خطأ في تحميل القسم:', err);
      if (loadSeq !== currentLoadSeq) return null;
      contentArea.innerHTML = `
        <div class="content-placeholder">
          <i class="fas fa-exclamation-triangle fa-2x"></i>
          <h3>حدث خطأ أثناء تحميل القسم</h3>
          <p>${err.message}</p>
          <button onclick="reloadCurrentSection()" class="btn btn-primary">
            <i class="fas fa-redo"></i> إعادة المحاولة
          </button>
        </div>`;

      scheduleClean();
      return null;
    }
  }

  /* ===== Helpers ===== */
  function setActiveLink(section) {
    const links = document.querySelectorAll('.submenu-link, .menu-link[data-section]');
    links.forEach(link => {
      const parent = link.closest('.menu-item');
      if (link.dataset.section === section) {
        link.classList.add('active');
        if (parent) parent.classList.add('active');
      } else {
        link.classList.remove('active');
        if (parent) parent.classList.remove('active');
      }
    });
  }

  function showLoader(message = 'جاري التحميل...') {
    if (!contentArea) return;
    contentArea.innerHTML = `
      <div class="content-placeholder">
        <div class="loading-spinner"></div>
        <h3>${message}</h3>
      </div>`;
  }

  /* ===== Hash Router ===== */
  async function handleHashRoute() {
    // ❗ لا ننظّف قبل القراءة حتى لا نفقد الهاش

    const raw = (window.location.hash || '').replace(/^#\/?/, '');
    if (!raw) return false;

    const [sectionRaw, queryRaw] = raw.split('?');
    const section = (sectionRaw || '').trim();
    const params = new URLSearchParams(queryRaw || '');

    // task-details
    if (section === 'task-details') {
      const pTask = Number(params.get('task') || 0);
      const pCo = Number(params.get('co') || 0);
      const pWeek = Number(params.get('week') || 0);

      const dl = window.__TASK_DEEPLINK || {};
      const task = pTask || Number(dl.task_id || 0);
      const co = pCo || Number(dl.course_offering_id || 0);
      const week = pWeek || Number(dl.weekly_content_id || 0);

      if (!task) return false;

      window.__TASK_DEEPLINK = { task_id: task, course_offering_id: co, weekly_content_id: week };
      try { await fetchSidebarMeta(false); } catch (e) { }
      await loadSection('task-details');

      scheduleClean(); // ✅ نظّف بعد تمام المعالجة
      return true;
    }

    // upgrade
    if (section === 'upgrade') {
      const code = (params.get('code') || '').toString().toUpperCase().replace(/\s+/g, '').trim();
      const open = (params.get('open') || 'activate').toString();
      const autoverify = (params.get('autoverify') || '1').toString();
      window.__UPGRADE_DEEPLINK = { code, open, autoverify };
      await loadSection('upgrade');
      scheduleClean();
      return true;
    }

    // static sections
    const STATIC = new Set(['dashboard', 'account', 'service', 'notifications', 'welcome']);
    if (STATIC.has(section)) {
      await loadSection(section);
      scheduleClean();
      return true;
    }

    // course-<id>
    const m = section.match(/^course-(\d+)$/);
    if (m) {
      const id = m[1];
      try { await fetchSidebarMeta(false); } catch (e) { }
      await loadCourseContent(id, `course-${id}`);
      scheduleClean();
      return true;
    }

    return false;
  }

  // واجهة عامة للتنقل
  window.StudentRouter = {
    navigate(target) {
      // ❌ لا ننظّف قبل تعيين الهاش (كي لا نمسحه قبل القراءة)
      const hash = target.startsWith('#') ? target : '#/' + target.replace(/^\//, '');
      if (location.hash === hash) {
        // حفّز إعادة المعالجة عند نفس المسار
        const bump = hash + (hash.includes('?') ? '&' : '?') + `_ts=${Date.now()}`;
        location.hash = bump;
      } else {
        location.hash = hash;
      }
      // ✅ نظّف بعد قليل كي يشتغل handleHashRoute أولًا
      scheduleClean();
    }
  };

  window.loadSection = loadSection;

  // فتح تفاصيل المهمة عبر الهاش + تخزين ديب لينك
  window.openTaskDetails = function (taskId, courseOfferingId, weeklyContentId, { push = true } = {}) {
    const task = Number(taskId);
    const co = Number(courseOfferingId || 0);
    const week = Number(weeklyContentId || 0);

    window.__TASK_DEEPLINK = { task_id: task, course_offering_id: co, weekly_content_id: week };

    const qs = new URLSearchParams({ task: String(task), co: String(co), week: String(week) }).toString();
    const target = `#/task-details?${qs}`;

    if (push) {
      window.StudentRouter.navigate(target);
    } else {
      if (location.hash === target) {
        location.hash = `${target}&_ts=${Date.now()}`;
      } else {
        location.hash = target;
      }
      scheduleClean();
    }
  };

  function reloadCurrentSection() {
    const section = localStorage.getItem('student_active_section') || 'welcome';
    loadSection(section);
  }
  window.reloadCurrentSection = reloadCurrentSection;

  /* ===== Sidebar UI ===== */
  function initSidebar() {
    const isMobile = function () { return window.innerWidth <= 768; };

    const updateToggleIcon = function () {
      const isCollapsed = sidebar.classList.contains('collapsed');
      if (toggleBtn) {
        toggleBtn.classList.toggle('sidebar-is-open', !isCollapsed);
        toggleBtn.setAttribute('aria-expanded', !isCollapsed);
      }
      if (isMobile()) {
        if (!isCollapsed) document.body.classList.add('no-scroll');
        else document.body.classList.remove('no-scroll');
      }
    };

    const setInitialState = function () {
      if (isMobile()) {
        sidebar.classList.add('collapsed');
        if (overlay) overlay.classList.remove('active');
      } else {
        sidebar.classList.remove('collapsed');
        if (overlay) overlay.classList.remove('active');
      }
      updateToggleIcon();
    };

    setInitialState();
    window.addEventListener('resize', setInitialState);

    if (menuToggle) {
      menuToggle.addEventListener('click', function () {
        sidebar.classList.remove('collapsed');
        if (overlay) overlay.classList.add('active');
        updateToggleIcon();
        scheduleClean();
      });
    }

    if (toggleBtn) {
      toggleBtn.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
        if (isMobile()) {
          if (overlay) overlay.classList.toggle('active', !sidebar.classList.contains('collapsed'));
        }
        updateToggleIcon();
        scheduleClean();
      });
    }

    if (overlay) {
      overlay.addEventListener('click', function () {
        sidebar.classList.add('collapsed');
        overlay.classList.remove('active');
        updateToggleIcon();
        scheduleClean();
      });
    }

    if (sidebar) {
      // روابط ثابتة
      sidebar.addEventListener('click', function (e) {
        const target = e.target;
        const link = target && target.closest ? target.closest('.submenu-link, .menu-link[data-section]') : null;
        if (!link) return;
        if (link.hasAttribute('data-course-offering-id')) return;
        e.preventDefault();
        loadSection(link.dataset.section);
        if (isMobile()) {
          sidebar.classList.add('collapsed');
          if (overlay) overlay.classList.remove('active');
          updateToggleIcon();
        }
        scheduleClean();
      });

      // روابط المساقات
      sidebar.addEventListener('click', function (e) {
        const target = e.target;
        const courseLink = target && target.closest ? target.closest('[data-course-offering-id]') : null;
        if (!courseLink) return;
        e.preventDefault();
        const id = courseLink.getAttribute('data-course-offering-id');
        const section = courseLink.getAttribute('data-section') || `course-${id}`;
        loadCourseContent(id, section);
        if (isMobile()) {
          sidebar.classList.add('collapsed');
          if (overlay) overlay.classList.remove('active');
          updateToggleIcon();
        }
        scheduleClean();
      });
    }

    // فتح/غلق القوائم الموجودة مسبقًا
    menuItems.forEach(item => {
      const link = item.querySelector('.menu-link');
      if (item.querySelector('.submenu') && !item.hasAttribute('data-course-item')) {
        if (link) {
          link.addEventListener('click', function (e) {
            e.preventDefault();
            item.classList.toggle('active');
            scheduleClean();
          });
        }
      }
    });
  }

  /* ===== Clear Caches ===== */
  function getCsrf() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    const m = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]+)/i);
    return m ? decodeURIComponent(m[1]) : null;
  }
  async function clearBrowserCaches() {
    if ('caches' in window) {
      try {
        const names = await caches.keys();
        await Promise.all(names.map(n => caches.delete(n)));
      } catch (_) { }
    }
    try { htmlCache.clear(); } catch (_) { }
    const LOCAL_PREFIXES = [
      'students_', 'student_', 'course_', 'courses_', 'task_', 'tasks_',
      'weekly_', 'content_', 'meta_', 'howp_', 'diploma_'
    ];
    try {
      for (let i = localStorage.length - 1; i >= 0; i--) {
        const key = localStorage.key(i);
        if (!key) continue;
        if (LOCAL_PREFIXES.some(p => key.startsWith(p))) {
          localStorage.removeItem(key);
        }
      }
    } catch (_) { }
    try { sessionStorage.clear(); } catch (_) { }
    try { window.dispatchEvent(new CustomEvent('students:client-cache-cleared')); } catch (_) { }
  }
  async function callServerClearCache() {
    const headers = { 'X-Requested-With': 'XMLHttpRequest', 'Cache-Control': 'no-store' };
    const csrf = getCsrf();
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    const res = await fetch('/students/course-content/clear-cache', {
      method: 'POST', credentials: 'same-origin', cache: 'no-store', headers
    });
    return res.ok;
  }
  function bindRefreshButton() {
    const refreshBtn = document.getElementById('refresh-courses');
    if (!refreshBtn) return;
    refreshBtn.addEventListener('click', async function (e) {
      e.preventDefault();
      document.body.classList.add('updating-courses');
      const icon = refreshBtn.querySelector('i');
      const oldIconClass = icon ? icon.className : '';
      if (icon) icon.className = 'fas fa-sync fa-spin';
      try {
        const serverOk = await callServerClearCache();
        await clearBrowserCaches();
        try { await fetchSidebarMeta(true); } catch (_) { }
        try { window.dispatchEvent(new CustomEvent('students:meta-refreshed')); } catch (_) { }
        scheduleClean();
        if (typeof window.showToast === 'function') {
          window.showToast(serverOk ? 'تم مسح الكاش بالكامل وتحديث البيانات' : 'تم تحديث البيانات (الكاش الجزئي)');
        } else {
          alert(serverOk ? 'تم مسح الكاش بالكامل وتحديث البيانات' : 'تم تحديث البيانات (الكاش الجزئي)');
        }
      } catch (err) {
        if (typeof window.showToast === 'function') window.showToast('تعذر إتمام عملية التحديث. حاول لاحقاً.');
        else alert('تعذر إتمام عملية التحديث. حاول لاحقاً.');
      } finally {
        document.body.classList.remove('updating-courses');
        if (icon) icon.className = oldIconClass || 'fas fa-sync-alt';
      }
      scheduleClean();
    });
  }

  /* ===== Boot ===== */
  document.addEventListener('DOMContentLoaded', async function () {
    try { await runDateBoundClientPurge(); } catch (_) { }

    // ⚠️ لا ننظّف قبل محاولة معالجة الهاش حتى نقرأه أولاً

    initSidebar();
    bindRefreshButton();

    // استمع لأي تغيّر في الهاش: عالج أولاً ثم نظّف (بتأخير بسيط)
    window.addEventListener('hashchange', () => {
      handleHashRoute()
        .then(() => scheduleClean())
        .catch(() => scheduleClean());
    });

    // حاول معالجة ديب لينك عبر الهاش أولاً
    const handled = await handleHashRoute();
    if (handled) {
      scheduleClean();
      return;
    }

    // بناء السايدبار ثم تحميل القسم المحفوظ/الترحيبي
    try { await fetchSidebarMeta(false); } catch (e) {
      console.error(e);
      let mount = document.getElementById('courses-mount');
      const container = document.querySelector('.sidebar .menu');
      const serviceLink = container ? container.querySelector('[data-section="service"]') : null;
      const serviceItem = serviceLink ? serviceLink.closest('.menu-item') : null;

      if (!mount && container) {
        mount = document.createElement('li');
        mount.id = 'courses-mount';
        mount.className = 'menu-item';
        mount.setAttribute('data-course-mount', 'true');

        const a = document.createElement('a');
        a.href = '#';
        a.className = 'menu-link';
        a.tabIndex = -1;
        a.setAttribute('aria-disabled', 'true');
        a.innerHTML = `<i class="fas fa-info-circle"></i> <span>تعذّر تحميل المساقات</span>`;

        mount.appendChild(a);
        if (serviceItem) container.insertBefore(mount, serviceItem);
        else container.appendChild(mount);
      } else if (mount) {
        const span = mount.querySelector('.menu-link span');
        if (span) span.textContent = 'تعذّر تحميل المساقات';
      }
    }

    const saved = localStorage.getItem('student_active_section') || 'dashboard';
    await loadSection(saved);

    // تأكيد أخير لتنظيف العنوان بعد التحميل
    scheduleClean();
  });

})(); // نهاية IIFE
