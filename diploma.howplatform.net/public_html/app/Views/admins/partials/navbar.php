<?php
// ==== قراءة السِشن (بدون طباعة role في الواجهة) ====
$__ses        = session()->get();
$__auth       = $__ses['auth_user'] ?? [];
$adminName    = $__ses['user_name']          ?? ($__auth['name']   ?? 'مسؤول النظام');
$profileImage = $__ses['profile_image_url']  ?? ($__auth['avatar'] ?? base_url('public/uploads/profile/default_male.jpg'));
$adminRole    = $__ses['role']               ?? ($__auth['role']   ?? null); // للاستخدام الداخلي فقط
$adminId      = $__ses['user_id']            ?? ($__auth['id']     ?? null);
?>
<nav class="navbar">
    <!-- زر القائمة الجانبية (hamburger) — مثبّت داخل navbar -->
    <!-- معلومات الموقع -->
    <div class="site-info">
        <button class="navbar-menu-btn" id="sidebarToggleBtn" title="القائمة الجانبية" aria-label="تبديل القائمة الجانبية" aria-expanded="true">
            <i class="fas fa-bars"></i>
        </button>
        <img src="<?= base_url('assets/images/Logo_how_gr.svg') ?>" alt="شعار المنصة" data-section="dashboard" class="site-logo">
        <span class="site-name">منصة كيف التعليمية</span>
    </div>

    <!-- يمين الهيدر: أزرار + بيانات الأدمن -->
    <div class="user-actions">

        <!-- تبديل الوضع (متوافق مع admin_router.js: .theme-toggle-btn) -->
        <button class="action-btn-nav theme-toggle-btn" title="تبديل الوضع" aria-label="Toggle theme">
            <i class="fas fa-moon"></i>
        </button>

        <!-- بيانات الأدمن من السِشن (بدون role) -->
        <a class="admin-info" href="https://auth.howplatform.net/profile?active=diploma" style="text-decoration: none; color: inherit; cursor: pointer; flex-shrink: 0;">
            <div class="admin-avatar">
                <img
                    src="<?= esc($profileImage) ?>"
                    alt="Avatar"
                    class="avatar-img"
                    onerror="this.onerror=null;this.src='<?= esc(base_url('public/uploads/profile/default_male.jpg')) ?>';"
                />
            </div>
            <span class="admin-name"><?= esc($adminName) ?></span>
        </a>

        <!-- الإشعارات (مربوطة بنقاط /admins/notifications/*) -->
        <div class="nav-notif notifications-container" id="navNotif" style="position:relative;">
            <button class="action-btn-nav notifications-btn" id="notifBellBtn" title="الإشعارات" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notifBadge" style="display:none;">0</span>
            </button>
            <div class="notifications-dropdown" id="notifDropdown" role="menu" aria-label="Notifications">
                <div class="notifications-header">
                    <h3 class="notifications-title">الإشعارات</h3>
                    <button type="button" class="mark-all-read" id="notifMarkAll">تعيين الكل كمقروء</button>
                </div>

                <div class="notifications-list" id="notifList">
                    <div class="notif-state" style="padding:26px 18px;text-align:center;color:var(--text-muted);">جارٍ التحميل…</div>
                </div>
                <div class="notifications-footer" style="padding:10px 16px;border-top:1px solid var(--border-color);text-align:center;">
                    <a href="#notifications" id="notifViewAll" style="color:var(--primary-600);font-weight:700;font-size:.88rem;text-decoration:none;cursor:pointer;">عرض كل الإشعارات</a>
                </div>
            </div>
        </div>

        <!-- تسجيل الخروج -->
        <a href="<?= base_url('auth/logout') ?>" id="logoutLink" class="action-btn-nav logout-btn" title="تسجيل الخروج">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</nav>

<!-- (اختياري) حقن السِشن في JS للاستخدام بالصلاحيات فقط؛ بدون أي عرض مرئي -->
<script>
  // ملاحظة: هذه البيانات لا تُعرض في الواجهة، مجرد توفر داخلي للمنطق
  window.__SESSION__ = <?= json_encode(session()->get(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  window.__ADMIN_ROLE__ = <?= json_encode($adminRole) ?>; // إن احتجته سريعًا بدون التنقير داخل __SESSION__
</script>
