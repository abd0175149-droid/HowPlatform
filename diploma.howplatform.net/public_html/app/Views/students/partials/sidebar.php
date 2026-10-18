<?php
declare(strict_types=1);

/**
 * ✅ لا نقرأ المساقات من هنا. فقط نقرأ بيانات الجلسة لما نحتاجه في الـ Feature Gates.
 */
$session   = session();
$authUser  = (array) ($session->get('auth_user') ?? []);
$userId    = $session->get('user_id') ?? ($authUser['id'] ?? null);
$userEmail = $session->get('user_email') ?? ($authUser['email'] ?? null);

/* ============================================================
   ✅ Feature Gate: زر "الترقية/الاشتراكات"
   أولوية: FeatureGate (عام) → ثم FeatureGates (قديم) كـ fallback
   - إن كانت الميزة Public ⇒ يظهر الزر للجميع وتختفي شارة BETA.
   - إن كانت Beta ⇒ يظهر الزر لمن في السماح/التدرّج فقط وتظهر شارة BETA.
   - إن كانت Off ⇒ لا يظهر الزر.
   ============================================================ */
$canSeeUpgrade  = false;
$showBetaBadge  = false;

if (class_exists(\App\Libraries\FeatureGate::class)) {
    /** @var \App\Libraries\FeatureGate $gate */
    $gate = service('featureGate');
    $uid  = $userId !== null ? (int) $userId : null;
    $eml  = $userEmail !== null ? (string) $userEmail : null;

    $canSeeUpgrade = $gate->isEnabled('upgrade_page', $uid, $eml);
    $showBetaBadge = ! $gate->isPublic('upgrade_page');

} elseif (class_exists(\App\Config\FeatureGates::class)) {
    /** @var \App\Config\FeatureGates $g */
    $g = config(\App\Config\FeatureGates::class);

    if ($g->studentUpgradeEnabled) {
        $allowIds    = (array) ($g->studentUpgradeAllowIds    ?? []);
        $allowEmails = (array) ($g->studentUpgradeAllowEmails ?? []);

        // 🔓 إذا الميزة مفعّلة والقوائم فارغة ⇒ اعتبرها Public للجميع
        if (empty($allowIds) && empty($allowEmails)) {
            $canSeeUpgrade = true;
            $showBetaBadge = false;
        } else {
            $okById = $userId && in_array((string) $userId, $allowIds, true);

            $okByEmail = false;
            if (!empty($userEmail)) {
                $needle = mb_strtolower(trim((string) $userEmail));
                foreach ($allowEmails as $em) {
                    if ($needle === mb_strtolower(trim((string) $em))) {
                        $okByEmail = true;
                        break;
                    }
                }
            }

            $canSeeUpgrade = ($okById || $okByEmail);
            $showBetaBadge = true; // تظل بيتا طالما هناك قوائم سماح
        }
    }
}

/** ✅ عدّاد رسائل “طلبات الخدمة” غير المقروءة (اختياري) */
$unreadServiceRequests = 0;
if ($userId) {
    $db = \Config\Database::connect();
    $unreadServiceRequests = $db->table('service_requests sr')
        ->join('service_request_messages srm', 'srm.service_request_id = sr.id')
        ->where('sr.user_id', $userId)
        ->where('srm.is_read', 0)
        ->where('srm.sender_id !=', $userId)
        ->countAllResults();
}

/** ✅ جلب النقاط (Points) للطالب */
$studentPoints = 0;
$canSeeRewards = false;
if ($userId) {
    try {
        // التحقق من تفعيل نظام المكافآت
        if (class_exists(\App\Libraries\FeatureGate::class)) {
            $gate = service('featureGate');
            $uid  = $userId !== null ? (int) $userId : null;
            $eml  = $userEmail !== null ? (string) $userEmail : null;
            $canSeeRewards = $gate->isEnabled('rewards_system', $uid, $eml);
        }
        
        if ($canSeeRewards) {
            $rewardsService = new \App\Services\RewardsService();
            $studentPoints = $rewardsService->getUserPoints((int)$userId);
        }
    } catch (\Throwable $e) {
        // في حالة الخطأ، نعرض 0
        log_message('debug', 'Sidebar: Error fetching points - ' . $e->getMessage());
        $studentPoints = 0;
    }
}
?>

<!-- ✅ الشريط الجانبي (عرض فقط) -->
<aside id="student-sidebar" class="sidebar" data-courses-source="api">
  <div class="logo-container" style="display:none;">
    <div class="sidebar-logo">
      <img src="<?= base_url('assets/images/Logo_how_gr.svg') ?>" alt="شعار المنصة"/>
      <h1>واجهة الطالب</h1>
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

    <li class="menu-item">
      <a href="#" class="menu-link" data-section="tasks">
        <i class="fas fa-list-check"></i>
        <span>المهام</span>
      </a>
    </li>

    <?php if ($canSeeRewards): ?>
    <li class="menu-item points-item">
      <a href="https://auth.howplatform.net/profile?active=points" class="menu-link" title="نقاطك — النقاط والمحفظة">
        <i class="fas fa-star" style="color: #fbbf24;"></i>
        <span>نقاطك: <?= number_format($studentPoints) ?></span>
      </a>
    </li>
    <?php endif; ?>

    <!-- ============ مجموعة: المساقات ============ -->
    <li class="nav-group-label"><span>المساقات</span></li>

    <!-- 🔄 Mount point للمساقات (JS سيحقن عناصر الفصول قبل المرساة) -->
    <li class="menu-item" id="courses-mount" data-course-mount="true">
      <a href="#" class="menu-link" tabindex="-1" aria-disabled="true">
        <i class="fas fa-layer-group"></i>
        <span>يتم تحميل المساقات...</span>
      </a>
    </li>
    <!-- مرساة ثابتة: JS يدرج الفصول/المساقات قبل هذا العنصر -->
    <li id="courses-anchor" class="nav-anchor" aria-hidden="true"></li>

    <!-- ============ مجموعة: الخدمات والحساب ============ -->
    <li class="nav-group-label"><span>الخدمات والحساب</span></li>

    <li class="menu-item service-item">
      <a href="#" class="menu-link" data-section="service">
        <i class="fas fa-headset"></i>
        <span>طلبات الخدمة</span>
        <?php if ($unreadServiceRequests > 0): ?>
          <span class="badge unread-badge" id="serviceRequestsBadge">
            <?= (int) $unreadServiceRequests ?>
          </span>
        <?php endif; ?>
      </a>
    </li>

    <?php
    // ✅ Feature Gate: زر "نظام المكافآت"
    $canSeeRewards = false;
    $showRewardsBeta = false;
    if (class_exists(\App\Libraries\FeatureGate::class)) {
        $gate = service('featureGate');
        $uid  = $userId !== null ? (int) $userId : null;
        $eml  = $userEmail !== null ? (string) $userEmail : null;
        $canSeeRewards = $gate->isEnabled('rewards_system', $uid, $eml);
        $showRewardsBeta = ! $gate->isPublic('rewards_system');
    }
    ?>
    <?php if ($canSeeRewards): ?>
      <li class="menu-item rewards-item">
        <a href="https://auth.howplatform.net/profile?active=points" class="menu-link" title="نظام المكافآت — النقاط والمحفظة">
          <i class="fas fa-gift"></i>
          <span>المكافآت</span>
          <?php if ($showRewardsBeta): ?>
            <span class="badge">BETA</span>
          <?php endif; ?>
        </a>
      </li>
    <?php endif; ?>

    <?php if ($canSeeUpgrade): ?>
      <li class="menu-item upgrade-item">
        <a href="https://auth.howplatform.net/profile?active=plans" class="menu-link" title="الترقية / الاشتراكات — الخطط والأسعار">
          <i class="fas fa-crown"></i>
          <span>الترقية / الاشتراكات</span>
          <?php if ($showBetaBadge): ?>
            <span class="badge">BETA</span>
          <?php endif; ?>
        </a>
      </li>
    <?php endif; ?>

    <li class="menu-item">
      <a href="https://auth.howplatform.net/profile?active=diploma" class="menu-link">
        <i class="fas fa-cog"></i>
        <span>الحساب الشخصي</span>
      </a>
    </li>

    <li class="menu-item refresh-item">
      <a href="#" class="menu-link refresh-link" id="refresh-courses">
        <i class="fas fa-sync-alt"></i>
        <span>تحديث البيانات</span>
      </a>
    </li>
  </ul>

  <!-- معلومات المستخدم في أسفل السايد بار (تُحدّث من JS) -->
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-stats">
        <span><span id="stat-semesters-count">0</span> فصل</span>
        <span><span id="stat-courses-count">0</span> مساق</span>
      </div>
    </div>
  </div>
</aside>


<!-- مساعد فَطين -->
<div class="fateen-initiator" id="chat-initiator">
    <span class="fateen-img fateen-svg-host" aria-hidden="true"><?php
        $fateenSvg = @file_get_contents(FCPATH . 'assets/images/Fateen.svg');
        echo $fateenSvg !== false ? $fateenSvg : '<img src="' . base_url('assets/images/Fateen.svg') . '" alt="Fateen">';
    ?></span>
    <div class="fateen-text">
        <span class="fateen-label">اسأل فَطين</span>
    </div>
</div>

<div class="floating-chatbot" id="chatbot">
    <div class="fateen-modal-header">
        <h2>
            <span class="fateen-avatar fateen-svg-host" aria-hidden="true"><?php
                echo isset($fateenSvg) && $fateenSvg !== false
                    ? $fateenSvg
                    : '<img src="' . base_url('assets/images/Fateen.svg') . '" alt="Fateen Logo">';
            ?></span>
            فَطين هُنا لمساعدتك
        </h2>
        <div class="fateen-header-actions">
            <button class="close-btn" id="history-btn" title="سجل المحادثات">
                <i class="fas fa-history"></i>
            </button>
            <button class="close-btn" id="maximize-btn" title="تكبير المحادثة">
                <i class="fas fa-expand"></i>
            </button>
            <button class="close-btn" id="minimize-btn" title="تصغير / العودة للنافذة العائمة">
                <i class="fas fa-compress"></i>
            </button>
            <button class="close-btn" id="close-btn" title="إغلاق">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <div class="chat-container">
        <div class="fateen-history" id="fateen-history">
            <div class="fateen-history-head">
                <h3><i class="fas fa-history"></i> سجل المحادثات</h3>
                <button id="history-new-btn" title="محادثة جديدة"><i class="fas fa-plus"></i> جديدة</button>
            </div>
            <div class="fateen-history-list" id="fateen-history-list"></div>
        </div>

        <div class="service-selection" id="service-selection">
            <h3>اختر نوع الخدمة المطلوبة</h3>
            <p class="service-indicator">الرجاء تحديد نوع الخدمة لبدء المحادثة</p>

            <div class="service-options">
                <div class="service-option" data-service="تقييم مقال تأملي">
                    <i class="fas fa-lightbulb"></i>
                    <div class="option-text">
                        <h4>تقييم مقال تأملي</h4>
                        <p>تحليل المقالات التأملية ومراجعة العمق الفكري والترابط المنطقي</p>
                    </div>
                </div>

                <div class="service-option" data-service="تقييم مقال تحليلي نقدي">
                    <i class="fas fa-search"></i>
                    <div class="option-text">
                        <h4>تقييم مقال تحليلي نقدي</h4>
                        <p>فحص نقدي للحجج والأدلة والمنهجية والاستنتاجات</p>
                    </div>
                </div>

                <div class="service-option" data-service="تقييم بحث استقصائي">
                    <i class="fas fa-book"></i>
                    <div class="option-text">
                        <h4>تقييم بحث استقصائي</h4>
                        <p>مراجعة شاملة للمنهجية وجمع البيانات والتحليل والنتائج</p>
                    </div>
                </div>

                <div class="service-option" data-service="سؤال حول مهمة معينة">
                    <i class="fas fa-question-circle"></i>
                    <div class="option-text">
                        <h4>سؤال حول مهمة معينة</h4>
                        <p>مساعدة في الإجابة على أسئلتك المتعلقة بمهامك الأكاديمية</p>
                    </div>
                </div>

                <div class="service-option" data-service="دردشة مع AI">
                    <i class="fas fa-comment-dots"></i>
                    <div class="option-text">
                        <h4>دردشة مع AI</h4>
                        <p>محادثة عامة مع المساعد الذكي</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="chat-area" id="chat-area">
            <div class="chat-toolbar">
                <div class="new-chat-wrapper">
                    <button id="new-chat-btn" class="new-chat-btn" title="ابدأ محادثة جديدة">
                        <i class="fas fa-arrows-rotate"></i>
                    </button>
                </div>
                <div id="usage-counter" class="usage-counter"></div>
            </div>

            <div class="chat-messages" id="chat-messages"></div>

            <div class="typing-indicator" id="typing-indicator">
                <span></span><span></span><span></span>
            </div>

            <div class="input-container">
                <textarea id="user-input" placeholder="اكتب استفسارك هنا..."></textarea>
                <button id="send-btn"><i class="fas fa-paper-plane"></i></button>
            </div>
            <span class="fateen-mistakes">فَطين قد يُخطئ أحيانًا. تحقق من المعلومات المهمة.</span>
        </div>
    </div>
</div>

<!-- CSS إضافي للسايد بار المحدث -->
<style>
/* تحسينات السايد بار والإحصاءات */
.sidebar .stats-overview { background: var(--surface-1); margin: 8px 12px; padding: 12px; border-radius: 9.6px; border: none; }
.sidebar-stats { display: flex; justify-content: space-between; gap: 8px; }
.sidebar-stats .stat-item { display: flex; align-items: center; gap: 4px; font-size: 0.8rem; color: var(--text-muted); }
.sidebar-stats .stat-item i { font-size: 0.9rem; color: var(--primary-500); }
.sidebar-stats .stat-item.premium { color: var(--accent-600); }
.sidebar-stats .stat-item.premium i { color: var(--accent-500); }

/* تحسين عرض المساقات */
.course-item .course-link { position: relative; padding: 9.6px 12px; border-radius: 6.4px; transition: all 0.2s ease; }
.course-item .course-link:hover { background: var(--primary-50); transform: translateX(-1.6px); }
.course-name { display: block; font-weight: 600; margin-bottom: 3.2px; }
.course-code { display: inline-block; background: var(--neutral-200); color: var(--neutral-700); padding: 1.6px 4.8px; border-radius: 3.2px; font-size: 0.7rem; font-weight: 600; margin-bottom: 4.8px; }
.course-stats { display: flex; gap: 6.4px; margin-top: 3.2px; flex-wrap: wrap; }
.course-stats span { display: flex; align-items: center; gap: 2.4px; font-size: 0.7rem; color: var(--text-muted); background: var(--surface-2); padding: 1.6px 4.8px; border-radius: 3.2px; }
.course-stats i { font-size: 0.7rem; }
.weeks-count i { color: var(--info-500); }
.tasks-count i { color: var(--warning-500); }
.available-indicator i.available { color: var(--success-500); font-size: 0.6rem; }

/* عداد المساقات في الفصل */
.semester-link .course-count { background: var(--primary-500); color: var(--text-inverse); padding: 1.6px 4.8px; border-radius: 50%; font-size: 0.7rem; font-weight: 600; min-width: 14.4px; height: 14.4px; display: flex; align-items: center; justify-content: center; margin-left: 4px; }

/* حالة عدم وجود مساقات */
.no-courses-message { display: flex; align-items: center; gap: 6.4px; padding: 12px; color: var(--text-muted); font-size: 0.9rem; background: var(--surface-1); border-radius: 6.4px; margin: 8px 12px; }

/* badge للطلبات غير المقروءة */
.unread-badge { background: var(--error-500); color: var(--text-inverse); padding: 1.6px 4.8px; border-radius: 8px; font-size: 0.7rem; font-weight: 600; min-width: 14.4px; height: 14.4px; display: flex; align-items: center; justify-content: center; margin-left: auto; animation: pulse 2s infinite; }

/* أسفل السايد بار */
.sidebar-footer { margin-top: auto; padding: 12px; border-top: 0.8px solid var(--border-color); background: var(--surface-1); }
.premium-badge { display: flex; align-items: center; gap: 4.8px; background: linear-gradient(135deg, var(--accent-500), var(--accent-600)); color: var(--text-inverse); padding: 6.4px 9.6px; border-radius: 6.4px; font-size: 0.8rem; font-weight: 600; margin-bottom: 8px; }
.user-stats { display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted); }

/* زر التحديث */
.refresh-link { color: var(--neutral-600) !important; transition: all 0.2s ease; }
.refresh-link:hover { color: var(--primary-600) !important; background: var(--primary-50); }
.refresh-link:hover i { animation: rotate 0.5s ease; }
@keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* شارات */
.menu-link .badge, .submenu-link .badge { background: #e74c3c; color: #fff; padding: 1.6px 4.8px; border-radius: 8px; font-size: 8.8px; margin-left: auto; min-width: 14.4px; text-align: center; font-weight: 600; }
.menu-item.collapsed .badge { position: absolute; top: 6.4px; right: 6.4px; font-size: 7.2px; padding: 0.8px 3.2px; }

/* شعار */
#student-sidebar .logo { text-align: center; padding: 1rem 0; border-bottom: 0.8px solid var(--gray-light); }
#student-sidebar .logo img { max-width: 96px; height: auto; display: inline-block; object-fit: contain; filter: drop-shadow(0 0.8px 2.4px rgba(0,0,0,0.1)); }

/* تحسينات متجاوبة */
@media (max-width: 768px) {
  .sidebar-stats { flex-direction: column; gap: 6.4px; }
  .course-stats { flex-direction: column; gap: 3.2px; }
  .course-count { font-size: 0.6rem !important; min-width: 12.8px !important; height: 12.8px !important; }
}


/* ================== Dark Mode — Colors Only ================== */
/* ملاحظة: لا تغييرات هيكلية — ألوان/توكنز فقط مع الحفاظ على الهوية (أزرق/برتقالي) */
[data-theme="dark"] .service-requests-container{
  /* سطوح وخلفيات متناغمة مع الثيم الداكن العام */
  --background: #0e141a;
  --surface-0: #0f1720;
  --surface-1: #121c25;
  --surface-2: #18232d;
  --surface-3: #0f1720;

  /* نص وحدود وظلال */
  --text: #cfe0e7;
  --text-light:#a6c4d2;
  --border-color: rgba(255,255,255,.08);

  /* سلّم الأزرق (Primary) على الدارك */
  --primary-50:  #0a2631;
  --primary-200: #11465a;
  --primary-400: #1e8eab;
  --primary-500: #2bb3d3; /* اللون الرئيسي */
  --primary-600: #21a1be;
  --primary-700: #1a8aa6;

  --primary: var(--primary-500);
  --primary-dark: var(--primary-600);
  --primary-darker: var(--primary-700);

  /* حالات */
  --shadow: 0 0.8px 2.4px rgba(0,0,0,.55);
}

/* الهيدر: يبقى الجradient لكن بقيم primary الداكنة الجديدة */
[data-theme="dark"] .service-header{
  border: 0.8px solid rgba(255,255,255,.18);
  color:#fff;
}

/* تلميح الترقية على الدارك */
[data-theme="dark"] .svc-upgrade-hint .alert{
  border-color: rgba(255,255,255,.12);
  background: rgba(253,158,2,.08); /* amber لطيف */
  color: #ffd08a;
}

/* الحقول والأزرار والأيقونات */

[data-theme="dark"] .btn-new-request{
  --_bg: var(--surface-0);
  --_ink: var(--text);
  background: var(--surface-0);
  color: var(--text);
  border-color: var(--border-color);
}

[data-theme="dark"] .btn-icon,
[data-theme="dark"] .tool-btn{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text-light);
}
[data-theme="dark"] .btn-icon:hover,
[data-theme="dark"] .tool-btn:hover{
  background: var(--surface-2);
}

/* الشريط الجانبي + البحث */
[data-theme="dark"] .requests-sidebar,
[data-theme="dark"] .svc-top-meta,
[data-theme="dark"] .chat-container,
[data-theme="dark"] .request-details,
[data-theme="dark"] .chat-header{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}

[data-theme="dark"] .input-group .form-control,
[data-theme="dark"] .input-group .input-group-text{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}

/* التبويبات (Chips) */
[data-theme="dark"] .filter-tabs .tab-btn{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text-light);
}
[data-theme="dark"] .filter-tabs .tab-btn:hover{
  background: var(--primary-50);
  border-color: var(--primary-200);
  color: var(--primary-400);
}
[data-theme="dark"] .filter-tabs .tab-btn.active{
  background: var(--primary-600);
  color:#fff;
  border-color: transparent;
}
[data-theme="dark"] .filter-tabs .tab-btn .count{
  background: rgba(255,255,255,.08);
}
[data-theme="dark"] .filter-tabs .tab-btn.active .count{
  background: rgba(255,255,255,.22);
}

/* قائمة الطلبات */
[data-theme="dark"] .request-item{ border-color: var(--border-color); }
[data-theme="dark"] .request-item:hover{ background: var(--surface-2); }
[data-theme="dark"] .request-item.active{
  background: linear-gradient(90deg, rgba(43,179,211,.10), transparent);
}

/* شارات الحالة */
[data-theme="dark"] .status-badge{
  background: var(--surface-2);
  color: var(--text-light);
}
[data-theme="dark"] .status-badge.pending{
  background: #2a2204;  /* ~ warning-100 */
  color: #f1c40f;       /* warning-500 */
}
[data-theme="dark"] .status-badge.in_progress{
  background: var(--primary-50);
  color: var(--primary-600);
}
[data-theme="dark"] .status-badge.resolved{
  background: #0f2d1f;  /* success-100 */
  color: #2ecc71;       /* success-500 */
}
[data-theme="dark"] .status-badge.cancelled{
  background: #2e1512;  /* error-100 */
  color: #e74c3c;       /* error-500 */
}

/* منطقة الرسائل */
[data-theme="dark"] #messagesWrapper{
  background: var(--surface-2);
  color: var(--text);
}

/* الفقاعات */
[data-theme="dark"] .message-content{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}
/* المرسلة تستخدم درجات الأزرق على الدارك */
[data-theme="dark"] .sentmsg .message-content{
  background: var(--primary-50);
  border-color: var(--primary-200);
}

/* النصوص الثانوية */
[data-theme="dark"] .sender-name{ color: var(--text); }
[data-theme="dark"] .message-time{ color: var(--text-light); }
[data-theme="dark"] .message-text{ color: var(--text); }

/* بطاقات الملفات */
[data-theme="dark"] .file-card{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}
[data-theme="dark"] .file-card:hover{
  border-color: var(--primary-200);
  box-shadow: 0 4.8px 14.4px rgba(0,0,0,.45);
}
[data-theme="dark"] .file-card .thumb{ background:#12212a; }
[data-theme="dark"] .file-card .icon-thumb i{ color: var(--primary-600); }
[data-theme="dark"] .file-card .meta .name{ color: var(--primary-darker); }
[data-theme="dark"] .file-card .meta .sub{ color: var(--text-light); }

/* زر التحميل العائم يبقى كما هو (تباين ممتاز على الدارك) */

/* المحرر/الإرسال */
[data-theme="dark"] .input-area{
  background: var(--surface-0);
  border-color: var(--border-color);
}
[data-theme="dark"] #messageInput{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}
[data-theme="dark"] #messageInput:focus{
  border-color: var(--primary-400);
  box-shadow: var(--focus-ring);  /* يستخدم حلقة التركيز العامة */
}
[data-theme="dark"] .send-btn{
  background: var(--primary-600);
  color:#fff;
}
[data-theme="dark"] .send-btn:hover{ background: var(--primary-700); }

/* التسجيل الصوتي */
[data-theme="dark"] .voice-recording{
  background: linear-gradient(135deg, var(--primary-600), var(--primary-400));
  border-color: rgba(255,255,255,.22);
  color:#fff;
}
[data-theme="dark"] .btn-stop-voice{
  background: var(--surface-0);
  color: var(--primary-700);
}
[data-theme="dark"] .btn-cancel-voice{
  background: rgba(239,68,68,.95);
  color:#fff;
}

/* الفقاعة الصوتية/العادية */
[data-theme="dark"] .audio-bubble{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}
[data-theme="dark"] .audio-play{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--primary-500);
}
[data-theme="dark"] .audio-footer{ color: var(--text-light); }
[data-theme="dark"] .audio-footer .audio-speed{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}

/* حقول DDL/الموبايل على الدارك */
@media (max-width: 768px) {
  [data-theme="dark"] .requests-ddl-mobile{
    background: var(--surface-1);
    border-bottom: 0.8px solid var(--border-color);
  }
}

/* فيديو بابل: لا تغيير هيكلي — فقط تأكد من تباين الأزرار الزجاجية */
[data-theme="dark"] .video-glass-play{
  background: rgba(0,0,0,.25);
  color:#fff;
}

/* تحسين بسيط للظل على الدارك لبعض البطاقات */
[data-theme="dark"] .card,
[data-theme="dark"] .chat-container,
[data-theme="dark"] .requests-sidebar{
  box-shadow: 0 6.4px 19.2px rgba(0,0,0,.45);
}

</style>

<script>
/* ============================================================
 * وظائف JavaScript للسايد بار (تنبيهات/تحديثات خفيفة)
 * ========================================================== */

// إشعار بسيط (قابل للاستبدال بسويت أليرت أو نظامك)
function showNotification(message, type = 'info') {
  console.log(`[${type.toUpperCase()}] ${message}`);
}

document.addEventListener('DOMContentLoaded', function() {
  // زر تحديث البيانات
  const refreshBtn = document.getElementById('refresh-courses');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const icon = this.querySelector('i');
      icon?.classList.add('fa-spin');
      setTimeout(() => {
        if (typeof loadUserCourses === 'function') {
          loadUserCourses().then(() => {
            icon?.classList.remove('fa-spin');
            showNotification('تم تحديث البيانات بنجاح', 'success');
          }).catch(() => {
            icon?.classList.remove('fa-spin');
            showNotification('تعذّر تحديث البيانات', 'error');
          });
        } else {
          location.reload();
        }
      }, 1000);
    });
  }

  // تحسين hover للمساقات
  document.querySelectorAll('.course-link').forEach(link => {
    link.addEventListener('mouseenter', function() {
      const stats = this.querySelector('.course-stats');
      if (stats) {
        stats.style.opacity = '1';
        stats.style.transform = 'translateY(0)';
      }
    });
  });

  // Tooltips للنصوص الطويلة
  document.querySelectorAll('[title]').forEach(item => {
    if (item.title && item.title.length > 50) {
      item.title = item.title.substring(0, 47) + '...';
    }
  });

  // بدء تحديث العداد فور التحميل
});


</script>
