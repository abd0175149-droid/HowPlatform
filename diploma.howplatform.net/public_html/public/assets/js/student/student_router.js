/* student_router.js - تحميل صفحات الطالب ديناميكياً عبر Student::pages فقط (بدون أي بناء محلي) */
const VERSION = 'v=1.3.10'; // ↑ تحديث بسيط لكسر الكاش

/* ========================= موديولات الأقسام ========================= */
/* ملاحظة: نحافظ على نمطك (sections/*) ونضيف upgrade */
const SECTION_MODULES = {
  course1:        () => import(`./sections/course1.js?${VERSION}`),
  course2:        () => import(`./sections/course2.js?${VERSION}`),
  notifications:  () => import(`./sections/notifications.js?${VERSION}`),
  account:        () => import(`./sections/account.js?${VERSION}`),
  service:        () => import(`./sections/service.js?${VERSION}`),
  'course-content': () => import(`./sections/coursecontent.js?${VERSION}`),
  dashboard:      () => import(`./sections/dashboard.js?${VERSION}`),
  // ✅ القسم الجديد: ترقية/الاشتراكات
  upgrade:        () => import(`./sections/upgrade.js?${VERSION}`),
  welcome:        () => import(`./sections/welcome.js?${VERSION}`)

};

const contentArea = document.getElementById('student-content-area');
const sidebar     = document.querySelector('.sidebar');
const overlay     = document.querySelector('.overlay');
const menuToggle  = document.querySelector('.menu-toggle');
const toggleBtn   = document.getElementById('sidebarToggleBtn');
const menuItems   = document.querySelectorAll('.menu-item');

let currentModule = null;
let coursesCache  = null;
let currentLoadSeq = 0;

/* ============================== Helpers ============================== */

function setActiveLink(section) {
  const links = document.querySelectorAll('.submenu-link, .menu-link[data-section]');
  links.forEach(link => {
    const parent  = link.closest('.menu-item');
    const submenu = parent?.querySelector('.submenu');
    if (link.dataset.section === section) {
      link.classList.add('active');
      if (submenu) parent.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });
  document.querySelectorAll('.menu-item').forEach(item => {
    if (!item.querySelector('.submenu-link.active')) item.classList.remove('active');
  });
}

function showLoader(message = 'جاري التحميل...') {
  contentArea.innerHTML = `
    <div class="content-placeholder">
      <div class="loading-spinner"></div>
      <h3>${message}</h3>
    </div>`;
}

/* ✅ جلب أي صفحة عبر Student::pages (ثابتة أو مساق بصيغة course-<id>) */
async function fetchStudentPage(page) {
  // كسر الكاش بزمن لحظي + الإبقاء على الـ credentials بنفس الأصل
  const url = `/student/pages/${encodeURIComponent(page)}?_=${Date.now()}`;
  const res = await fetch(url, { credentials: 'same-origin' });

  // معالجة 403 (ميزة محجوبة) هنا أيضاً لو لزم
  if (res.status === 403) {
    // نحاول قراءة JSON ودّي إن أعاده الفلتر، وإلا نعرض رسالة عامة
    let msg = 'الميزة تحت التطوير. لا تملك صلاحية الوصول حالياً.';
    try {
      const data = await res.json();
      if (data?.message) msg = data.message;
    } catch (_) { /* تجاهل */ }
    throw new Error(msg);
  }

  if (res.redirected) {
    // إن أعاد الفلتر Redirect (مثلاً لعدم تسجيل الدخول)، نتبع التحويل
    window.location.href = res.url;
    return '';
  }

  if (!res.ok) {
    throw new Error(`فشل تحميل الصفحة (HTTP ${res.status})`);
  }
  return res.text();
}

/* ============================== API: Courses ============================== */

// نفس السلوك السابق (جلب مساقات المستخدم للسايدبار)
async function loadUserCourses(refresh = false) {
  try {
    const url = `/student/course-content/user-courses${refresh ? '?refresh=1' : ''}`;
    const res = await fetch(url, { credentials: 'same-origin' });
    const result = await res.json();
    if (result.success) {
      coursesCache = result.data;
      updateSidebarWithCourses(result.data);
      if (typeof showToast === 'function') {
        const msg = result.from_cache ? 'تم التحديث (من الكاش)' : 'تم التحديث ببيانات جديدة';
        showToast(msg);
      }
      return result.data;
    }
    console.error('خطأ في جلب المساقات:', result.message);
    return null;
  } catch (e) {
    console.error('خطأ في الشبكة:', e);
    return null;
  }
}

// مسح كاش المستخدم ثم إعادة الجلب
async function forceRefreshCourses() {
  try {
    await fetch('/student/course-content/clear-cache', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
  } catch (e) {
    console.warn('تعذّر مسح الكاش، سيتابع بالجلب:', e);
  }
  await loadUserCourses(true);
}

function updateSidebarWithCourses(semesters) {
  const container = document.querySelector('.sidebar .menu');
  if (!container) return;

  container.querySelectorAll('.menu-item[data-course-item]').forEach(el => el.remove());

  semesters.forEach(sem => {
    if (Array.isArray(sem.courses) && sem.courses.length) {
      const item = createSemesterMenuItem(sem);
      const serviceItem = container.querySelector('[data-section="service"]')?.closest('.menu-item');
      if (serviceItem) container.insertBefore(item, serviceItem);
      else container.appendChild(item);
    }
  });
}

/* بدون عدّاد أسابيع بجانب اسم المساق */
function createSemesterMenuItem(semester) {
  const li = document.createElement('li');
  li.className = 'menu-item';
  li.setAttribute('data-course-item', 'true');

  const link = document.createElement('a');
  link.href = '#';
  link.className = 'menu-link';
  link.innerHTML = `
    <i class="fas fa-layer-group"></i> ${semester.semester_name}
    <i class="fas fa-chevron-down arrow"></i>
  `;

  const submenu = document.createElement('ul');
  submenu.className = 'submenu';

  semester.courses.forEach(course => {
    const ci = document.createElement('li');
    ci.className = 'submenu-item';
    const a = document.createElement('a');
    a.href = '#';
    a.className = 'submenu-link';
    a.setAttribute('data-section', `course-${course.course_offering_id}`);
    a.setAttribute('data-course-offering-id', course.course_offering_id);
    a.innerHTML = `<i class="fas fa-book"></i> ${course.course_name}`;
    ci.appendChild(a);
    submenu.appendChild(ci);
  });

  li.appendChild(link);
  li.appendChild(submenu);

  link.addEventListener('click', e => {
    e.preventDefault();
    li.classList.toggle('active');
  });

  return li;
}

/* ============================== Static Pages ============================== */

async function loadStaticPage(section, loadSeq) {
  try {
    if (loadSeq !== currentLoadSeq) return null;

    // ✅ جلب الـ HTML دائمًا من Student::pages
    const html = await fetchStudentPage(section);
    if (loadSeq !== currentLoadSeq) return null;
    contentArea.innerHTML = html;

    // ✅ استيراد موديول القسم إن وجد
    let mod = null;
    if (SECTION_MODULES[section]) {
      mod = await SECTION_MODULES[section]();
      if (typeof mod.init === 'function') mod.init();
    }

    setActiveLink(section);
    localStorage.setItem('student_active_section', section);

    // منطق خاص بقسم "service" لديك — أحافظ عليه كما هو
    if (section === 'service' && window.location.hash === '#new-request') {
      setTimeout(() => {
        if (window.openNewServiceModal) window.openNewServiceModal();
        window.location.hash = '';
      }, 500);
    }
    return mod;
  } catch (err) {
    // 🔒 تحسين رسالة 403 القادمة من FeatureGate لصفحات Student::pages
    const msg = err?.message || 'حدث خطأ أثناء تحميل الصفحة';
    if (loadSeq !== currentLoadSeq) return null;
    contentArea.innerHTML = `
      <div class="content-placeholder">
        <i class="fas fa-exclamation-triangle fa-2x"></i>
        <h3>تعذّر تحميل الصفحة</h3>
        <p>${msg}</p>
        <button onclick="reloadCurrentSection()" class="btn btn-primary">
          <i class="fas fa-redo"></i> إعادة المحاولة
        </button>
      </div>`;
    return null;
  }
}

// إتاحة الدالة عالميًا ليستدعيها أي سكربت
window.loadSection = loadSection;

/* تفويض أحداث عام لعناصر data-section */
document.addEventListener('click', (e) => {
  const trigger = e.target.closest('[data-section]');
  if (!trigger) return;
  e.preventDefault();
  e.stopPropagation();
  const section = trigger.getAttribute('data-section');
  if (section) loadSection(section);
});

/* ============================== Validation ============================== */

async function validateSection(section) {
  try {
    const res = await fetch(`/student/api/validate-section/${encodeURIComponent(section)}`, { credentials: 'same-origin' });
    return await res.json();
  } catch (e) {
    console.error('خطأ في التحقق من صحة القسم:', e);
    return { valid: false };
  }
}

/* ============================== Load Course ============================== */

async function loadCourseContent(courseOfferingId, section) {
  const loadSeq = ++currentLoadSeq;

  try {
    if (currentModule && typeof currentModule.cleanup === 'function') currentModule.cleanup();

    showLoader('جاري تحميل محتوى المساق...');

    // 1) جلب بيانات المساق (API كما هي)
    const dataRes = await fetch(`/student/course-content/${encodeURIComponent(courseOfferingId)}`, { credentials: 'same-origin' });
    const result = await dataRes.json();
    if (!result.success) throw new Error(result.message || 'خطأ في جلب محتوى المساق');

    // 2) تحميل صفحة المساق ديناميكيًا حصريًا عبر Student::pages
    const html = await fetchStudentPage(`course-${courseOfferingId}`);
    if (loadSeq !== currentLoadSeq) return null;
    contentArea.innerHTML = html;

    // 3) استيراد موديول المساق بعد حقن الـ HTML
    const mod = await SECTION_MODULES['course-content']();
    if (typeof mod.init === 'function') mod.init(result.data);
    currentModule = mod;

    setActiveLink(section);
    localStorage.setItem('student_active_section', section);
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

/* ============================== Load Section (generic) ============================== */

export async function loadSection(section) {
  const loadSeq = ++currentLoadSeq;

  try {
    if (currentModule && typeof currentModule.cleanup === 'function') currentModule.cleanup();

    showLoader('جاري تحميل القسم...');

    // ✅ التحقق من نوع القسم وصلاحية الوصول
    const validation = await validateSection(section);
    if (!validation.valid) throw new Error('القسم غير موجود أو ليس لديك صلاحية للوصول إليه');

    if (validation.type === 'course') {
      return await loadCourseContent(validation.course_offering_id, section);
    }
    if (validation.type === 'static') {
      return await loadStaticPage(section, loadSeq);
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

/* ============================== Sidebar (delegated) ============================== */

function initSidebar() {
  const isMobile = () => window.innerWidth <= 768;

  const updateToggleIcon = () => {
    const icon = toggleBtn?.querySelector('i');
    if (!icon) return;
    const isCollapsed = sidebar.classList.contains('collapsed');
    icon.classList.toggle('fa-angle-left', isCollapsed);
    icon.classList.toggle('fa-angle-right', !isCollapsed);
    if (isMobile()) document.body.classList.toggle('no-scroll', !isCollapsed);
  };

  const setInitialState = () => {
    if (isMobile()) {
      sidebar.classList.add('collapsed');
      overlay?.classList.remove('active');
    } else {
      sidebar.classList.remove('collapsed');
      overlay?.classList.remove('active');
    }
    updateToggleIcon();
  };

  setInitialState();
  window.addEventListener('resize', setInitialState);

  menuToggle?.addEventListener('click', () => {
    sidebar.classList.remove('collapsed');
    overlay?.classList.add('active');
    updateToggleIcon();
  });

  toggleBtn?.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    if (isMobile()) overlay?.classList.toggle('active', !sidebar.classList.contains('collapsed'));
    updateToggleIcon();
  });

  overlay?.addEventListener('click', () => {
    sidebar.classList.add('collapsed');
    overlay.classList.remove('active');
    updateToggleIcon();
  });

  // تفويض: روابط الصفحات الثابتة
  sidebar.addEventListener('click', (e) => {
    const link = e.target.closest('.submenu-link, .menu-link[data-section]');
    if (!link) return;
    if (link.hasAttribute('data-course-offering-id')) return; // مساقات لها معالج خاص
    e.preventDefault();
    loadSection(link.dataset.section);
    if (isMobile()) {
      sidebar.classList.add('collapsed');
      overlay?.classList.remove('active');
      updateToggleIcon();
    }
  });

  // تفويض: روابط المساقات (سواء من السيرفر أو مضافة ديناميكيًا)
  sidebar.addEventListener('click', (e) => {
    const courseLink = e.target.closest('[data-course-offering-id]');
    if (!courseLink) return;
    e.preventDefault();
    const id = courseLink.getAttribute('data-course-offering-id');
    const section = courseLink.getAttribute('data-section') || `course-${id}`;
    loadCourseContent(id, section);
    if (isMobile()) {
      sidebar.classList.add('collapsed');
      overlay?.classList.remove('active');
      updateToggleIcon();
    }
  });

  // فتح/غلق القوائم الموجودة مسبقًا
  menuItems.forEach(item => {
    const link = item.querySelector('.menu-link');
    if (item.querySelector('.submenu') && !item.hasAttribute('data-course-item')) {
      link?.addEventListener('click', e => {
        e.preventDefault();
        item.classList.toggle('active');
      });
    }
  });
}

/* ============================== Reload current ============================== */
export function reloadCurrentSection() {
  const section = localStorage.getItem('student_active_section');
  if (section) loadSection(section);
}

/* ============================== Cleanup on unload ============================== */
window.addEventListener('beforeunload', () => {
  if (currentModule && typeof currentModule.cleanup === 'function') currentModule.cleanup();
});

/* ============================== Boot ============================== */
document.addEventListener('DOMContentLoaded', async () => {
  initSidebar();
  await loadUserCourses(); // جلب أولي لمساقات الطالب
  const saved = localStorage.getItem('student_active_section') || 'dashboard'; // بديل آمن لـ welcome
  loadSection(saved);

  // زر "تحديث البيانات" يمسح الكاش ثم يعيد الجلب مع refresh=1
  document.getElementById('refresh-courses')?.addEventListener('click', async (e) => {
    e.preventDefault();
    document.body.classList.add('updating-courses');
    try {
      await forceRefreshCourses();
    } finally {
      document.body.classList.remove('updating-courses');
    }
  });
});

/* ============================== Logout ============================== */
document.getElementById('logoutLink')?.addEventListener('click', function(e) {
  e.preventDefault();
  if (typeof showConfirm === 'function') {
    showConfirm({
      title: 'هل أنت متأكد؟',
      text: 'سيتم تسجيل الخروج من حسابك.',
      confirmText: 'نعم، سجل الخروج',
      cancelText: 'إلغاء',
      onConfirm: () => { window.location.href = this.href; }
    });
  } else {
    if (confirm('تأكيد تسجيل الخروج؟')) window.location.href = this.href;
  }
});
