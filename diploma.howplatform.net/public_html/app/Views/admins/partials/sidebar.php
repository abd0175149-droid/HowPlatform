<aside class="sidebar">
<div class="logo-container" style="display:none;">
    <div class="sidebar-logo">
<img src="<?= base_url('assets/images/Logo_how_gr.svg') ?>" alt="شعار المنصة">
        <h1>لوحة التحكم</h1>
    </div>
</div>

<ul class="menu">
    <!-- الرئيسية -->
    <li class="menu-item">
        <a href="#" class="menu-link" data-section="dashboard">
            <i class="fas fa-home"></i>
            الرئيسية
        </a>
    </li>

    <!-- إدارة المحتوى -->
    <li class="menu-item">
        <a href="#" class="menu-link">
            <i class="fas fa-folder-open"></i>
            إدارة المحتوى
            <i class="fas fa-chevron-down arrow"></i>
        </a>
        <ul class="submenu">
            <li class="submenu-item">
                <a href="#" class="submenu-link" data-section="courses">
                    <i class="fas fa-book"></i>
                    إدارة المساقات
                </a>
            </li>
            <li class="submenu-item">
                <a href="#" class="submenu-link" data-section="weeks">
                    <i class="fas fa-calendar-week"></i>
                    إدارة الأسابيع
                </a>
            </li>
            <li class="submenu-item">
                <a href="#" class="submenu-link" data-section="tasks">
                    <i class="fas fa-tasks"></i>
                    إدارة المهام
                </a>
            </li>
            
        </ul>
    </li>
<?php if (session('role') == 1): ?>
    <li class="menu-item">
        <a href="#" class="menu-link">
            <i class="fas fa-cogs"></i>
            ضبط خيارات الموقع
            <i class="fas fa-chevron-down arrow"></i>
        </a>
        <ul class="submenu">
            <li class="submenu-item">
                <a href="#" class="submenu-link" data-section="academic_year_options">
                    <i class="fas fa-calendar-alt"></i> 
                    ادارة العام الدراسي والفصول
                </a>
            </li>
            
            <li class="submenu-item">
                <a href="#" class="submenu-link" data-section="university_options">
                    <i class="fas fa-university"></i>
                    خيارات الجامعات والتخصصات
                </a>
            </li>
            <li class="submenu-item">
                <a href="#" class="submenu-link" data-section="task_type_options">
                    <i class="fas fa-tasks"></i>
                    ادارة أنواع المهام
                </a>
            </li>
            <!-- Removed Lesson Plan Generator -->


            <li class="submenu-item">
                <a class="submenu-link" data-section="plans" href="#">
                    <i class="fas fa-layer-group"></i>
                إدارة الخطط
                </a>
            </li>
             <li class="submenu-item">
              <a href="#" class="submenu-link" data-section="library_upload">
                <i class="fas fa-envelope-open-text"></i>
                المكتبة الرقمية
              </a>
            </li>

        </ul>
    </li>
    <!-- إدارة المستخدمين -->
    <li class="menu-item">
        <a href="#" class="menu-link">
            <i class="fas fa-users"></i>
            إدارة المستخدمين
            <i class="fas fa-chevron-down arrow"></i>
        </a>
        <ul class="submenu">
            <li class="submenu-item">
                <a href="#" class="submenu-link" data-section="manage_users">
                    <i class="fas fa-user-cog"></i>
                    إدارة الحسابات
                </a>
            </li>
             <li class="menu-item">
              <a href="#" class="menu-link" data-section="service_chat">
                <i class="fas fa-comments"></i>
                <span>طلبات المراسلة</span>
                <span id="serviceChatTotalUnread" class="menu-badge" style="display:none">0</span>
              </a>
            </li>
            <li class="submenu-item">
                <a href="#" class="submenu-link" data-section="service_requests">
                    <i class="fas fa-user-plus"></i>
                     جدول طلبات المراسلة
                </a>
            </li>
            <li class="submenu-item">
                <a href="#" class="submenu-link" data-section="service_requests_new">
                    <i class="fas fa-comments"></i>
                     طلبات الخدمة - جديد
                </a>
            </li>

        </ul>
    </li>
<?php endif; ?>
    <li class="menu-item">
      <a href="https://auth.howplatform.net/profile?active=diploma" class="menu-link">
        <i class="fas fa-cog"></i>
        الحساب الشخصي
      </a>
    </li>

</ul>
</aside>

