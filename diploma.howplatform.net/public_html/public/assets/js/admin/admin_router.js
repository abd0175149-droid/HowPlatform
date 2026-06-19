/* admin_router.js */
/* global Chart, Swal, showWarning, showError, showConfirm */

const VERSION = 'v=1.21.6';           // ✦ عدّل رقم الإصدار عند كل نشر

// 🗺️ خريطة الأقسام (ES Modules)
const SECTION_MODULES = {
  dashboard            : () => import(`./sections/dashboard.js?${VERSION}`),
  courses              : () => import(`./sections/courses.js?${VERSION}`),
  weeks                : () => import(`./sections/weeks.js?${VERSION}`),
  tasks                : () => import(`./sections/tasks.js?${VERSION}`),
  academic_year_options: () => import(`./sections/academic_year_options.js?${VERSION}`),
  task_type_options    : () => import(`./sections/task_type_options.js?${VERSION}`),
  university_options   : () => import(`./sections/university_options.js?${VERSION}`),
  manage_users         : () => import(`./sections/manage_users.js?${VERSION}`),
  account              : () => import(`./sections/account.js?${VERSION}`),
  pending_requests     : () => import(`./sections/pending_requests.js?${VERSION}`),
  service_requests     : () => import(`./sections/service_requests_table.js?${VERSION}`),
  service_chat         : () => import(`./sections/service_requests_chat.js?${VERSION}`),
  email_broadcast      : () => import(`./sections/email_broadcast.js?${VERSION}`),
  library_upload       : () => import(`./sections/library_upload.js?${VERSION}`),
  // داخل SECTION_MODULES:
    plans: () => import(`./sections/plans.js?${VERSION}`),


};

/* عناصر DOM */
const contentArea  = document.getElementById('contentArea');
const sidebar      = document.querySelector('.sidebar');
const overlay      = document.querySelector('.overlay');
const menuToggle   = document.querySelector('.menu-toggle');
const submenuLinks = document.querySelectorAll('.submenu-link, .menu-link[data-section]');
const menuItems    = document.querySelectorAll('.menu-item');
const toggleBtn    = document.getElementById('sidebarToggleBtn');
const logoutLink   = document.getElementById('logoutLink');

/* ============================================================
   دوال fetch آمنة تتعامل مع 401 لتجنب "Illegal return statement"
   ============================================================ */

// ✅ دالة fetch عامة تضيف هيدرات AJAX وتحوّل لصفحة الدخول عند 401
async function safeFetch(url, options = {}) {
  const headers = {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'text/html,application/json;q=0.9,*/*;q=0.1',
    ...(options.headers || {})
  };

  const res = await fetch(url, { ...options, headers });

  if (res.status === 401) {
    try {
      const clone = res.clone();
      const contentType = clone.headers.get('Content-Type') || '';
      if (contentType.includes('application/json')) {
        const j = await clone.json();
        if (j && j.message && typeof showWarning === 'function') {
          showWarning(j.message);
        }
      }
    } catch (_) { /* تجاهُل آمن */ }

    // 🚪 إعادة التوجيه إلى صفحة الدخول ثم إيقاف التدفق برمي خطأ
    window.location.href = '/auth/login';
    throw new Error('Unauthorized (401) – redirected to /auth/login');
  }

  return res;
}

async function safeFetchText(url, options) {
  const res = await safeFetch(url, options);
  return res.text();
}
async function safeFetchJSON(url, options) {
  const res = await safeFetch(url, options);
  return res.json();
}

/*=== تفعيل الروابط في القائمة ===*/
function setActiveLink(section) {
  submenuLinks.forEach(link => {
    const parent  = link.closest('.menu-item');
    const submenu = parent?.querySelector('.submenu');

    if (link.dataset.section === section) {
      link.classList.add('active');
      if (submenu) parent.classList.add('active'); // فتح القائمة التي تحتوي الرابط
    } else {
      link.classList.remove('active');
    }
  });

  // إزالة active عن أي عنصر لا يحتوي رابطًا فعّالًا بداخله
  menuItems.forEach(item => {
    if (!item.querySelector('.submenu-link.active')) {
      item.classList.remove('active');
    }
  });
}

/*=== تحميل قسم محدّد ===*/
export async function loadSection(section) {
  try {
    // ترميز احتياطي لاسم القسم + كسر كاش HTML بالـ VERSION
    const encoded = encodeURIComponent(section);
    const htmlUrl = `/admin/pages/${encoded}?${VERSION}`;

    // ⛑️ استخدم safeFetchText لضمان التعامل مع 401
    const html = await safeFetchText(htmlUrl);
    contentArea.innerHTML = html; // حقن الـ HTML

    let mod = null;
    if (SECTION_MODULES[section]) {
      mod = await SECTION_MODULES[section]();  // تحميل سكربت القسم (ESM)
      if (typeof mod.init === 'function') {
        // استدعاء init بعد التأكد من حقن الـ HTML
        await Promise.resolve(mod.init());
      }
    }

    setActiveLink(section);
    localStorage.setItem('activeSection', section);
    return mod;
  } catch (err) {
    // 401: سيتم التحويل تلقائيًا داخل safeFetch — الأخطاء الأخرى تُعرض هنا
    console.error(err);
    contentArea.innerHTML = `
      <div class="content-placeholder">
        <i class="fas fa-exclamation-triangle fa-2x"></i>
        <h3>حدث خطأ أثناء تحميل القسم</h3>
        <p>${err.message}</p>
      </div>`;
    return null;
  }
}

// ✅ إتاحة الدالة عالميًا ليستدعيها أي سكربت (مثل زر المحادثة في الجدول)
window.loadSection = loadSection;

/* ✅ تفويض أحداث عام لعناصر data-section (يدعم العناصر المحقونة لاحقًا) */
document.addEventListener('click', (e) => {
  const trigger = e.target.closest('[data-section]');
  if (!trigger) return;
  e.preventDefault();              // منع الانتقال الكامل
  e.stopPropagation();             // منع التداخل مع مستمعين آخرين
  const section = trigger.getAttribute('data-section');
  if (section) loadSection(section);
});

/* =========[ تهيئة السايدبار ]========= */
function initSidebar() {
  const isMobile = () => window.innerWidth <= 768;

  const updateToggleIcon = () => {
    const icon = toggleBtn?.querySelector('i');
    if (!icon) return;

    const isCollapsed = sidebar?.classList.contains('collapsed');

    if (isCollapsed) {
      icon.classList.remove('fa-angle-right');
      icon.classList.add('fa-angle-left');   // ← للفتح
    } else {
      icon.classList.remove('fa-angle-left');
      icon.classList.add('fa-angle-right');  // → للإغلاق
    }

    // ✅ التحكم في التمرير على الجوال
    if (isMobile()) {
      document.body.classList.toggle('no-scroll', !isCollapsed);
    }
  };

  const setInitialState = () => {
    if (!sidebar || !overlay) return;

    if (isMobile()) {
      sidebar.classList.add('collapsed');
      overlay.classList.remove('active');
      document.body.classList.remove('no-scroll');
    } else {
      sidebar.classList.remove('collapsed');
      overlay.classList.remove('active');
      document.body.classList.remove('no-scroll');
    }
    updateToggleIcon();
  };

  setInitialState();
  window.addEventListener('resize', setInitialState);

  // زر الهامبرجر (☰) - للهواتف
  menuToggle?.addEventListener('click', () => {
    if (!sidebar || !overlay) return;
    sidebar.classList.remove('collapsed');
    overlay.classList.add('active');
    updateToggleIcon();
  });

  // زر الطيّ/الفتح
  toggleBtn?.addEventListener('click', () => {
    if (!sidebar || !overlay) return;
    sidebar.classList.toggle('collapsed');
    if (isMobile()) {
      overlay.classList.toggle('active', !sidebar.classList.contains('collapsed'));
    }
    updateToggleIcon();
  });

  // الضغط على الغطاء خارج السايدبار
  overlay?.addEventListener('click', () => {
    if (!sidebar || !overlay) return;
    sidebar.classList.add('collapsed');
    overlay.classList.remove('active');
    updateToggleIcon();
  });

  // روابط القائمة – تغلق السايدبار على الهواتف بعد الاختيار
  submenuLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const sec = link.dataset.section;
      if (sec) loadSection(sec);
      if (isMobile()) {
        sidebar.classList.add('collapsed');
        overlay.classList.remove('active');
        updateToggleIcon();
      }
    });
  });

  // طي/فتح القوائم الفرعية
  menuItems.forEach(item => {
    const link = item.querySelector('.menu-link');
    const hasSub = !!item.querySelector('.submenu');
    if (link && hasSub) {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        item.classList.toggle('active');
      });
    }
  });
}

/* =========[ نقطة البداية ]========= */
document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  const saved = localStorage.getItem('activeSection') || 'dashboard';
  loadSection(saved);
});

/* تسجيل الخروج مع تأكيد */
logoutLink?.addEventListener('click', function (e) {
  e.preventDefault();

  if (typeof showConfirm === 'function') {
    showConfirm({
      title: 'هل أنت متأكد؟',
      text: 'سيتم تسجيل الخروج من حسابك.',
      confirmText: 'نعم، سجل الخروج',
      cancelText: 'إلغاء',
      onConfirm: () => {
        window.location.href = this.href;
      }
    });
  } else {
    // إن لم تتوفر showConfirm عالميًا، نfallback لتأكيد المتصفح
    const ok = window.confirm('سيتم تسجيل الخروج من حسابك. هل أنت متأكد؟');
    if (ok) window.location.href = this.href;
  }
});

/* إعادة تحميل القسم الحالي (للاستخدام من سكربتات الأقسام) */
export function reloadCurrentSection() {
  const section = localStorage.getItem('activeSection');
  if (section) loadSection(section);
}
