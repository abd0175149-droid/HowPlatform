/* student_router.js - Router صفحات الطالب (IIFE + double-load guard + HTML cache + hash sync)
 * محدث: دعم الروابط المباشرة، تزامن مع location.hash، الرجوع/التقدّم، وحراسة التحميل
 */
(function () {
  'use strict';

  // امنع التشغيل المكرر لو الملف اتحمّل مرتين
  if (window.__STUDENT_ROUTER_LOADED__) return;
  window.__STUDENT_ROUTER_LOADED__ = true;

  const VERSION = 'v=1.4.0'; // حدثت رقم النسخة للكاش

  /* ====== DOM ====== */
  const contentArea = document.getElementById('student-content-area');
  const sidebar     = document.querySelector('.sidebar');
  const overlay     = document.querySelector('.overlay');
  const menuToggle  = document.querySelector('.menu-toggle');
  const toggleBtn   = document.getElementById('sidebarToggleBtn');
  const menuItems   = document.querySelectorAll('.menu-item');

  /* ====== حالة عامة ====== */
  let currentModule   = null;
  let sidebarMeta     = null;
  let currentLoadSeq  = 0;
  let ignoreNextHashChange = false; // لمنع حلقة لانهائية عندما نحدث الهاش برمجيًا
  let currentSectionId = '';        // آخر قسم تم تحميله (لمنع إعادة التحميل بلا داعٍ)

  /* ====== موديولات الأقسام ====== */
  const SECTION_MODULES = {
    course1:          () => import(`./sections/course1.js?${VERSION}`),
    course2:          () => import(`./sections/course2.js?${VERSION}`),
    notifications:    () => import(`./sections/notifications.js?${VERSION}`),
    account:          () => import(`./sections/account.js?${VERSION}`),
    service:          () => import(`./sections/service.js?${VERSION}`),
    'course-content': () => import(`./sections/coursecontent.js?${VERSION}`),
    dashboard:        () => import(`./sections/dashboard.js?${VERSION}`),
    upgrade:          () => import(`./sections/upgrade.js?${VERSION}`),
    'task-details':   () => import(`./sections/taskdetails.js?${VERSION}`)
  };

  /* ====== HTML Cache للصفحات الثابتة ====== */
  const htmlCache = new Map(); // بالذاكرة فقط
  async function fetchStudentPage(page) {
    const key = `page:${page}`;
    if (htmlCache.has(key)) return htmlCache.get(key);
    const url = `/students/pages/${encodeURIComponent(page)}?_=${Date.now()}`;
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`فشل تحميل الصفحة (HTTP ${res.status})`);
    const html = await res.text();
    htmlCache.set(key, html);
    return html;
  }
  async function fetchCourseContentPage() {
    return fetchStudentPage('course_content'); // محفوظ في htmlCache
  }

  /* ====== Helpers ====== */
  function setActiveLink(section) {
    // أزل تفعيل جميع الروابط والبنود
    const links = document.querySelectorAll('.submenu-link, .menu-link[data-section]');
    const allMenuItems = document.querySelectorAll('.sidebar .menu .menu-item');
    links.forEach(link => link.classList.remove('active'));
    allMenuItems.forEach(mi => mi.classList.remove('active'));

    // فعّل الرابط المطلوب وافتح القائمة الأب إن وجدت
    let target = null;
    links.forEach(link => {
      if (link.dataset.section === section) {
        target = link;
      }
    });
    if (target) {
      target.classList.add('active');
      const parentItem = target.closest('.menu-item');
      if (parentItem) parentItem.classList.add('active');
    }
  }

  function showLoader(message = 'جاري التحميل...') {
    if (!contentArea) return;
    contentArea.innerHTML = `
      <div class="content-placeholder">
        <div class="loading-spinner"></div>
        <h3>${message}</h3>
      </div>`;
  }

  // بناء هاش مناسب للقسم الحالي (للمشاركة/التحديث)
  function buildHashForSection(section, extras = {}) {
    // course-X => استخدم #/course-123
    if (section && section.startsWith('course-')) {
      const id = section.split('course-')[1] || '';
      return `#/course-${id}`;
    }
    // task-details => استخدم باراميترات
    if (section === 'task-details') {
      const p = new URLSearchParams();
      if (extras.task_id)  p.set('task', String(extras.task_id));
      if (extras.co)       p.set('co',   String(extras.co));
      if (extras.week)     p.set('week', String(extras.week));
      return `#/task-details?${p.toString()}`;
    }
    // upgrade => تمرير الرمز/التصرف
    if (section === 'upgrade') {
      const p = new URLSearchParams();
      if (extras.code)       p.set('code', String(extras.code));
      if (extras.open)       p.set('open', String(extras.open));
      if (extras.autoverify) p.set('autoverify', String(extras.autoverify));
      const qs = p.toString();
      return qs ? `#/upgrade?${qs}` : `#/upgrade`;
    }
    // أقسام ثابتة أخرى
    return `#/${section || 'dashboard'}`;
  }

  // تحديث الهاش بدون إعادة تحميل مزدوج
  function updateHashSafely(newHash) {
    if (window.location.hash === newHash) return;
    ignoreNextHashChange = true;
    window.location.hash = newHash;
  }

  /* ===== Sidebar Meta ===== */
  async function fetchSidebarMeta(force = false) {
    const url = `/students/sidebar-meta?${force ? 'refresh=1&' : ''}_=${Date.now()}`;
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
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

    // نظّف أي عناصر قديمة للمساقات
    container.querySelectorAll('.menu-item[data-course-item]').forEach(el => el.remove());

    let semestersCount = 0;
    let coursesCount   = 0;

    const serviceLink = container.querySelector('[data-section="service"]');
    const serviceItem = serviceLink ? serviceLink.closest('.menu-item') : null;

    (meta.semesters || []).forEach(block => {
      const sem = block.semester || {};
      const courses = Array.isArray(block.courses) ? block.courses : [];
      if (courses.length === 0) return;

      const item = createSemesterMenuItem({
        id:   sem.id,
        name: sem.name || 'فصل دراسي',
        courses
      });

      if (serviceItem) container.insertBefore(item, serviceItem);
      else             container.appendChild(item);

      semestersCount += 1;
      coursesCount   += courses.length;
    });

    const semEl = document.getElementById('stat-semesters-count');
    const crsEl = document.getElementById('stat-courses-count');
    if (semEl) semEl.textContent = String(semestersCount);
    if (crsEl) crsEl.textContent = String(coursesCount);

    // حالة عدم وجود مساقات
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

        if (serviceItem) container.insertBefore(mount, serviceItem);
        else             container.appendChild(mount);
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
      a.href = `#/course-${cs.offering_id}`; // رابط قابل للمشاركة/التحديث
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
        credentials: 'same-origin'
      });
      return await res.json();
    } catch (e) {
      console.error('خطأ في التحقق من صحة القسم:', e);
      return { valid: false };
    }
  }

  /* ===== Course Content Loader ===== */
  async function loadCourseContent(courseOfferingId, section, options = {}) {
    const loadSeq = ++currentLoadSeq;

    try {
      if (currentModule && typeof currentModule.cleanup === 'function') {
        currentModule.cleanup();
      }

      showLoader('جاري تحميل محتوى المساق...');

      // 1) بيانات المساق (JSON)
      const dataRes = await fetch(`/students/course-content/${encodeURIComponent(courseOfferingId)}?_=${Date.now()}`, {
        credentials: 'same-origin'
      });
      if (!dataRes.ok) throw new Error('خطأ في جلب محتوى المساق: HTTP ' + dataRes.status);
      const result = await dataRes.json();
      if (!result.success) throw new Error(result.message || 'خطأ في جلب محتوى المساق');

      // 2) صفحة HTML (من الكاش إن وجدت)
      const html = await fetchCourseContentPage();
      if (loadSeq !== currentLoadSeq) return null;

      contentArea.innerHTML = html;

      // 3) تهيئة الموديول
      const mod = await SECTION_MODULES['course-content']();
      if (typeof mod.init === 'function') mod.init(result.data);
      currentModule = mod;

      currentSectionId = section;
      setActiveLink(section);
      try { localStorage.setItem('student_active_section', section); } catch {}

      // حدث الهاش (إلا إذا قال لنا المتصل لا تحدثه)
      if (!options.skipHashUpdate) {
        updateHashSafely(buildHashForSection(section));
      }

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
      return null;
    }
  }

  /* ===== Static Page Loader ===== */
  async function loadStaticPage(section, loadSeq, extras = {}, options = {}) {
    const html = await fetchStudentPage(section);
    if (loadSeq !== currentLoadSeq) return null;

    contentArea.innerHTML = html;

    let mod = null;
    if (SECTION_MODULES[section]) {
      mod = await SECTION_MODULES[section]();
      if (typeof mod.init === 'function') mod.init();
    }

    currentSectionId = section;
    setActiveLink(section);
    try { localStorage.setItem('student_active_section', section); } catch {}

    // سلوك خاص بالـ service
    if (section === 'service' && window.location.hash === '#/new-request') {
      setTimeout(() => {
        if (window.openNewServiceModal) window.openNewServiceModal();
        updateHashSafely('#/service'); // نظف الهاش
      }, 400);
    }

    // حدث الهاش إن لم يُطلب عدم ذلك
    if (!options.skipHashUpdate) {
      updateHashSafely(buildHashForSection(section, extras));
    }

    return mod;
  }

  /* ===== Load Section (عام) ===== */
  async function loadSection(section, extras = {}, options = {}) {
    const loadSeq = ++currentLoadSeq;

    try {
      if (currentModule && typeof currentModule.cleanup === 'function') {
        currentModule.cleanup();
      }

      // تجنّب إعادة التحميل بلا داعٍ
      if (!options.force && currentSectionId === section) {
        return null;
      }

      showLoader('جاري تحميل القسم...');

      const validation = await validateSection(section);
      if (!validation.valid) throw new Error('القسم غير موجود أو ليست لديك صلاحية للوصول إليه');

      if (validation.type === 'course') {
        const coId = validation.course_offering_id;
        const sec  = section || `course-${coId}`;
        return await loadCourseContent(coId, sec, options);
      }
      if (validation.type === 'static') {
        return await loadStaticPage(section, loadSeq, extras, options);
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
      return null;
    }
  }

  /* ===== Deep Links & Routing ===== */
  // قراءة هاش عام: #/upgrade?..., #/task-details?..., #/course-123, #/account ...
  function readRouteFromHash() {
    const raw = (window.location.hash || '').trim();
    if (!raw) return null;

    // نمط موحد: #/something[?qs]
    const noHash = raw.replace(/^#\/?/, '');
    const [path, qs] = noHash.split('?');
    const section = (path || '').trim();

    // upgrade
    if (section === 'upgrade') {
      const p = new URLSearchParams(qs || '');
      return {
        kind: 'upgrade',
        section: 'upgrade',
        params: {
          code: (p.get('code') || '').toString(),
          open: (p.get('open') || ''),
          autoverify: (p.get('autoverify') || '')
        }
      };
    }

    // task-details
    if (section === 'task-details') {
      const p = new URLSearchParams(qs || '');
      const task = Number(p.get('task') || 0);
      const co   = Number(p.get('co')   || 0);
      const week = Number(p.get('week') || 0);
      return { kind: 'task', section: 'task-details', params: { task, co, week } };
    }

    // course-<id>
    if (section.startsWith('course-')) {
      const id = Number(section.split('course-')[1] || 0);
      return { kind: 'course', section: `course-${id}`, params: { co: id } };
    }

    // static
    if (section) {
      return { kind: 'static', section, params: {} };
    }

    return null;
  }

  async function handleRouteChange(initial = false) {
    const route = readRouteFromHash();

    // لو حدث تغيير هاش ناتج عن تحديثنا الداخلي — تجاهله مرة واحدة
    if (!initial && ignoreNextHashChange) {
      ignoreNextHashChange = false;
      return;
    }

    // حمل الميتا أولًا (لا يؤخّر العرض إذا تعذر)
    try { await fetchSidebarMeta(false); } catch (_e) {}

    // 1) لو فيه ديب-لينك واضح
    if (route) {
      if (route.kind === 'upgrade') {
        window.__UPGRADE_DEEPLINK = {
          code: (route.params.code || '').toString(),
          open: (route.params.open || 'activate').toString(),
          autoverify: (route.params.autoverify || '1').toString()
        };
        return loadSection('upgrade', route.params, { skipHashUpdate: true });
      }

      if (route.kind === 'task') {
        if (route.params.task) {
          window.__TASK_DEEPLINK = {
            task_id: Number(route.params.task),
            course_offering_id: Number(route.params.co || 0),
            weekly_content_id: Number(route.params.week || 0)
          };
          return loadSection('task-details', {}, { skipHashUpdate: true });
        }
      }

      if (route.kind === 'course') {
        const id = Number(route.params.co || 0);
        if (id > 0) {
          return loadCourseContent(id, `course-${id}`, { skipHashUpdate: true });
        }
      }

      if (route.kind === 'static') {
        return loadSection(route.section, {}, { skipHashUpdate: true });
      }
    }

    // 2) إن لم يوجد هاش: استخدم المحفوظ أو الافتراضي
    const saved = (localStorage.getItem('student_active_section') || '').trim();
    if (saved) {
      // لا نحدّث الهاش الآن؛ loadSection سيقوم بتحديثه
      return loadSection(saved, {}, { force: true, skipHashUpdate: false });
    }

    // 3) آخر حل: حمّل الترحيبي/الداشبورد
    return loadSection('welcome', {}, { force: true, skipHashUpdate: false });
  }

  /* واجهات عامة */
  window.loadSection = function (section) {
    // عند التنقل اليدوي: حدّث الهاش أولاً ثم اترك handleRouteChange يحمّل القسم
    const newHash = buildHashForSection(section);
    updateHashSafely(newHash);
    // handleRouteChange سيتم استدعاؤه عبر hashchange
  };

  window.openTaskDetails = function (taskId, courseOfferingId, weeklyContentId) {
    window.__TASK_DEEPLINK = {
      task_id: Number(taskId),
      course_offering_id: Number(courseOfferingId || 0),
      weekly_content_id: Number(weeklyContentId || 0)
    };
    const newHash = buildHashForSection('task-details', {
      task_id: taskId, co: courseOfferingId, week: weeklyContentId
    });
    updateHashSafely(newHash);
  };

  function reloadCurrentSection() {
    const route = readRouteFromHash();
    if (route) return handleRouteChange(); // سيعيد تحميل الحالي
    const saved = localStorage.getItem('student_active_section');
    if (saved) {
      const newHash = buildHashForSection(saved);
      updateHashSafely(newHash);
    } else {
      updateHashSafely('#/welcome');
    }
  }
  window.reloadCurrentSection = reloadCurrentSection;

  /* ===== Sidebar UI ===== */
  function initSidebar() {
    const isMobile = function () { return window.innerWidth <= 768; };

    const updateToggleIcon = function () {
      const icon = toggleBtn ? toggleBtn.querySelector('i') : null;
      if (!icon) return;
      const isCollapsed = sidebar.classList.contains('collapsed');
      icon.classList.toggle('fa-angle-left', isCollapsed);
      icon.classList.toggle('fa-angle-right', !isCollapsed);
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
      });
    }

    if (toggleBtn) {
      toggleBtn.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
        if (isMobile()) {
          if (overlay) overlay.classList.toggle('active', !sidebar.classList.contains('collapsed'));
        }
        updateToggleIcon();
      });
    }

    if (overlay) {
      overlay.addEventListener('click', function () {
        sidebar.classList.add('collapsed');
        overlay.classList.remove('active');
        updateToggleIcon();
      });
    }

    if (sidebar) {
      // روابط ثابتة
      sidebar.addEventListener('click', function (e) {
        const target = e.target;
        const link = target && target.closest ? target.closest('.submenu-link, .menu-link[data-section]') : null;
        if (!link) return;

        // روابط المساقات لها معاملة خاصة أدناه
        if (link.hasAttribute('data-course-offering-id')) return;

        e.preventDefault();
        // حدث الهاش فقط — handleRouteChange سيكمل
        updateHashSafely(buildHashForSection(link.dataset.section));

        if (isMobile()) {
          sidebar.classList.add('collapsed');
          if (overlay) overlay.classList.remove('active');
          updateToggleIcon();
        }
      });

      // روابط المساقات
      sidebar.addEventListener('click', function (e) {
        const target = e.target;
        const courseLink = target && target.closest ? target.closest('[data-course-offering-id]') : null;
        if (!courseLink) return;
        e.preventDefault();

        const id = courseLink.getAttribute('data-course-offering-id');
        // حدث الهاش إلى #/course-<id> — ثم handleRouteChange سيحمّل المحتوى
        updateHashSafely(buildHashForSection(`course-${id}`));

        if (isMobile()) {
          sidebar.classList.add('collapsed');
          if (overlay) overlay.classList.remove('active');
          updateToggleIcon();
        }
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
          });
        }
      }
    });
  }

  /* ===== Boot ===== */
  document.addEventListener('DOMContentLoaded', async function () {
    initSidebar();

    // حمّل الميتا مبكرًا (لا يوقف البدء لو فشل)
    try { await fetchSidebarMeta(false); } catch (_e) { /* عرض placeholder لاحقًا */ }

    // ابدأ بالراوت الحالي (هاش)، أو بالمحفوظ، أو بالافتراضي
    await handleRouteChange(true);

    // زر تحديث بيانات المساقات
    const refreshBtn = document.getElementById('refresh-courses');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', async function (e) {
        e.preventDefault();
        document.body.classList.add('updating-courses');
        try {
          await fetch('/students/course-content/clear-cache', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });
          await fetchSidebarMeta(true);
          // نبه صفحات التفاصيل تمسح كاشها المحلي
          try { window.dispatchEvent(new CustomEvent('students:meta-refreshed')); } catch (_) { }
          if (typeof window.showToast === 'function') window.showToast('تم تحديث قائمة المساقات من الخادم');
        } finally {
          document.body.classList.remove('updating-courses');
        }
      });
    }
  });

  // استمع لتغيير الهاش (الرجوع/التقدّم والتنقل اليدوي)
  window.addEventListener('hashchange', () => handleRouteChange());

  /* ===== Logout ===== */
  const logoutLink = document.getElementById('logoutLink');
  if (logoutLink) {
    logoutLink.addEventListener('click', function (e) {
      e.preventDefault();
      if (typeof window.showConfirm === 'function') {
        window.showConfirm({
          title: 'هل أنت متأكد؟',
          text: 'سيتم تسجيل الخروج من حسابك.',
          confirmText: 'نعم، سجل الخروج',
          cancelText: 'إلغاء',
          onConfirm: () => { window.location.href = logoutLink.href; }
        });
      } else {
        if (confirm('تأكيد تسجيل الخروج؟')) window.location.href = logoutLink.href;
      }
    });
  }

})(); // نهاية IIFE
