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

        <!-- الإشعارات -->
        <div class="notifications-container">
            <button class="action-btn-nav notifications-btn js-notif-toggle" title="الإشعارات" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">4</span>
            </button>
            <div class="notifications-dropdown" role="menu" aria-label="Notifications">
                <div class="notifications-header">
                    <h3 class="notifications-title">الإشعارات</h3>
                    <button class="mark-all-read">تعيين الكل كمقروء</button>
                </div>

                <div class="notifications-list">
                    <div class="notification-item unread">
                        <div class="notification-icon" style="background: linear-gradient(135deg, var(--primary-300), var(--primary-600)); color: white;">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="notification-info">
                            <div class="notification-title">
                                <span class="unread-badge"></span>
                                طلب ترقية جديد
                            </div>
                            <p class="notification-desc">قدم المستخدم أحمد محمد طلب ترقية إلى مدرب</p>
                            <div class="notification-time">
                                <i class="far fa-clock"></i> منذ 10 دقائق
                            </div>
                        </div>
                    </div>
                </div>

                <div class="notifications-footer">
                    <a href="#" class="view-all-link">
                        عرض جميع الإشعارات
                        <i class="fas fa-arrow-left"></i>
                    </a>
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
