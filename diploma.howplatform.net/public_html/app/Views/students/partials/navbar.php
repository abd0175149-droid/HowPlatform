<!-- app/Views/students/partials/navbar.php -->
<nav class="navbar">
    <!-- معلومات الموقع -->
    <div class="site-info">
        <button class="navbar-menu-btn" id="sidebarToggleBtn" title="القائمة الجانبية" aria-label="تبديل القائمة الجانبية" aria-expanded="true">
            <i class="fas fa-bars"></i>
        </button>
        <img src="<?= base_url('assets/images/Logo_how_gr.svg') ?>" alt="شعار المنصة" data-section="dashboard" class="site-logo">
        <span class="site-name">منصة كيف التعليمية</span>
    </div>

    <!-- الأزرار الجانبية -->
    <div class="user-actions">
        <!-- الإشعارات -->
        <div class="nav-notif" id="navNotif" style="position:relative;">
            <button class="action-btn-nav" id="notifBellBtn" title="الإشعارات" aria-label="الإشعارات" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notifBadge" style="display:none;">0</span>
            </button>
            <div class="notifications-dropdown" id="notifDropdown">
                <div class="notifications-header">
                    <span class="notifications-title">الإشعارات</span>
                    <button type="button" class="mark-all-read" id="notifMarkAll">تعليم الكل كمقروء</button>
                </div>
                <div class="notifications-list" id="notifList">
                    <div class="notif-state" style="padding:26px 18px;text-align:center;color:var(--text-muted);">جارٍ التحميل…</div>
                </div>
                <div class="notifications-footer" style="padding:10px 16px;border-top:1px solid var(--border-color);text-align:center;">
                    <a href="#/notifications" id="notifViewAll" style="color:var(--primary-600);font-weight:700;font-size:.88rem;text-decoration:none;">عرض كل الإشعارات</a>
                </div>
            </div>
        </div>

         <button class="action-btn-nav theme-toggle-btn" title="تبديل الوضع">
            <i class="fas fa-moon"></i>
        </button>
        <!-- معلومات الطالب -->
        <a href="https://auth.howplatform.net/profile?active=diploma" class="student-info" style="text-decoration: none; color: inherit; cursor: pointer; flex-shrink: 0;">
            <div class="student-avatar">
<img src="<?= esc($profileImage) ?>"
     alt="Avatar"
     class="avatar-img" />

            </div>
            <span class="student-name">
                <?= esc(session('user_name') ?? 'طالب') ?>
            </span>
        </a>
                <a href="<?= site_url('students/logout') ?>" id="logoutLink" class="action-btn-nav logout-btn" title="تسجيل الخروج">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</nav>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const themeToggleBtn = document.querySelector(".theme-toggle-btn");
    const icon = themeToggleBtn.querySelector("i");

    // استرجاع الوضع من localStorage أو من إعدادات الجهاز
    function getPreferredTheme() {
        const storedTheme = localStorage.getItem("theme");
        if (storedTheme) return storedTheme;
        return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
    }

    // تطبيق الوضع
    function applyTheme(theme) {
        document.documentElement.setAttribute("data-theme", theme);
        localStorage.setItem("theme", theme);
        icon.className = theme === "dark" ? "fas fa-sun" : "fas fa-moon";
    }

    // عند الضغط على الزر
    themeToggleBtn.addEventListener("click", () => {
        const currentTheme = document.documentElement.getAttribute("data-theme") || getPreferredTheme();
        const newTheme = currentTheme === "dark" ? "light" : "dark";
        applyTheme(newTheme);
    });

    // تطبيق الوضع الافتراضي عند التحميل
    applyTheme(getPreferredTheme());

    // تأكيد تسجيل الخروج للطالب
    const logoutBtn = document.getElementById('logoutLink');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            
            // استخدام SweetAlert2 إذا كان متاحاً (نفس ستايل الأدمن)
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: 'سيتم تسجيل الخروج من حسابك.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--primary-500)',
                    cancelButtonColor: 'var(--error-500)',
                    confirmButtonText: 'نعم، سجل الخروج',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = href;
                    }
                });
            } else {
                const ok = window.confirm('سيتم تسجيل الخروج من حسابك. هل أنت متأكد؟');
                if (ok) window.location.href = href;
            }
        });
    }
});
</script>

