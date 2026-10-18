<aside class="sidebar">
<div class="logo-container" style="display:none;">
    <div class="sidebar-logo">
<img src="<?= base_url('assets/images/Logo_how_gr.svg') ?>" alt="شعار المنصة">
        <h1>لوحة التحكم</h1>
    </div>
</div>

<ul class="menu">

    <!-- ============ مجموعة: الرئيسية ============ -->
    <li class="nav-group-label"><span>الرئيسية</span></li>

    <li class="menu-item">
        <a href="#" class="menu-link" data-section="dashboard">
            <i class="fas fa-home"></i>
            <span>لوحة التحكم</span>
        </a>
    </li>

    <!-- ============ مجموعة: إدارة المحتوى ============ -->
    <li class="nav-group-label"><span>إدارة المحتوى</span></li>

    <li class="menu-item">
        <a href="#" class="menu-link" data-section="courses">
            <i class="fas fa-book"></i>
            <span>إدارة المساقات</span>
        </a>
    </li>
    <li class="menu-item">
        <a href="#" class="menu-link" data-section="weeks">
            <i class="fas fa-calendar-week"></i>
            <span>إدارة الأسابيع</span>
        </a>
    </li>
    <li class="menu-item">
        <a href="#" class="menu-link" data-section="tasks">
            <i class="fas fa-tasks"></i>
            <span>إدارة المهام</span>
        </a>
    </li>

<?php if (session('role') == 1): ?>
    <!-- ============ مجموعة: ضبط الموقع ============ -->
    <li class="nav-group-label"><span>ضبط خيارات الموقع</span></li>

    <li class="menu-item">
        <a href="#" class="menu-link" data-section="academic_year_options">
            <i class="fas fa-calendar-alt"></i>
            <span>العام الدراسي والفصول</span>
        </a>
    </li>
    <li class="menu-item">
        <a href="#" class="menu-link" data-section="university_options">
            <i class="fas fa-university"></i>
            <span>الجامعات والتخصصات</span>
        </a>
    </li>
    <li class="menu-item">
        <a href="#" class="menu-link" data-section="task_type_options">
            <i class="fas fa-list-ul"></i>
            <span>أنواع المهام</span>
        </a>
    </li>
    <li class="menu-item">
        <a href="#" class="menu-link" data-section="ai_tools">
            <i class="fas fa-robot"></i>
            <span>أدوات الذكاء الاصطناعي</span>
        </a>
    </li>
    <li class="menu-item">
        <a href="#" class="menu-link" data-section="plans">
            <i class="fas fa-layer-group"></i>
            <span>إدارة الخطط</span>
        </a>
    </li>
    <li class="menu-item">
        <a href="#" class="menu-link" data-section="library_upload">
            <i class="fas fa-book-open"></i>
            <span>المكتبة الرقمية</span>
        </a>
    </li>

    <!-- ============ مجموعة: المستخدمون والخدمة ============ -->
    <li class="nav-group-label"><span>المستخدمون والخدمة</span></li>

    <li class="menu-item">
        <a href="#" class="menu-link" data-section="manage_users">
            <i class="fas fa-user-cog"></i>
            <span>إدارة الحسابات</span>
        </a>
    </li>
    <li class="menu-item">
        <a href="#" class="menu-link" data-section="service_chat">
            <i class="fas fa-headset"></i>
            <span>طلبات الخدمة</span>
            <span id="serviceChatTotalUnread" class="menu-badge" style="display:none">0</span>
        </a>
    </li>
<?php endif; ?>

    <!-- ============ مجموعة: الحساب ============ -->
    <li class="nav-group-label"><span>الحساب</span></li>

    <li class="menu-item">
      <a href="https://auth.howplatform.net/profile?active=diploma" class="menu-link">
        <i class="fas fa-cog"></i>
        <span>الحساب الشخصي</span>
      </a>
    </li>

</ul>
</aside>
