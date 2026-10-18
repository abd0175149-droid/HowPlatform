<!-- app/Views/students/layout.php -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="<?= csrf_hash() ?>">
  <meta name="csrf-header" content="X-CSRF-TOKEN">

  <title>واجهة الطالب - منصة كيف التعليمية</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <?php $uid = session('user_id'); ?>

  <!-- ميتا: يسهّل التقاط الـ user_id من JS -->
  <meta name="current-user-id" content="<?= esc($uid ?? '') ?>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link rel="preload" as="style"
    href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap"
    media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap">
  </noscript>

  <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print"
    onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  </noscript>

  <link rel="stylesheet" href="<?= base_url('assets/css/student_style.css?v=1.12.5') ?>">

  <link rel="icon" type="image/png" href="<?= base_url('assets/images/icon.ico') ?>">

  <style>
    * {
      font-family: 'Almarai', sans-serif;
    }

    .blurred {
      filter: blur(15px);
      transition: filter 0.3s ease-in-out;
    }

    /* يمنع تمرير الخلفية كلياً عند فتح أي مودال */
    body.modal-open {
      overflow: hidden !important;
      height: 100%;
    }

    /* ====================== ستايل مودال خدمة الطلبات ====================== */
    /* أخفي جذر المودال افتراضياً لمنع أي وميض قبل تحميل JS */
    #serviceRequestModal,
    [data-svc-modal] {
      position: fixed;
      inset: 0;
      display: none;
      /* مخفي افتراضياً */
      align-items: center;
      justify-content: center;
      background: rgba(0, 0, 0, .38);
      /* تمويه الخلفية */
      z-index: 1000;
      /* أعلى من المحتوى */
      padding: 16px;
      transition: opacity .15s ease;
      opacity: 0;
    }

    /* عند الفتح */
    #serviceRequestModal.show,
    [data-svc-modal].show {
      display: flex;
      opacity: 1;
    }

    /* مربع الحوار الداخلي */
    #serviceRequestModal .modal-dialog,
    [data-svc-modal] .modal-dialog,
    [data-svc-modal] [data-svc-dialog] {
      width: 100%;
      max-width: 720px;
      background: #fff;
      color: #222;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
      overflow: hidden;
      transform: translateY(8px);
      animation: svcModalIn .18s ease-out both;
    }

    @keyframes svcModalIn {
      from {
        opacity: 0;
        transform: translateY(10px) scale(.98);
      }

      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    /* الرأس */
    .svc-modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 16px 20px;
      border-bottom: 1px solid #eee;
      background: #fafafa;
    }

    .svc-modal-title {
      font-size: 18px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .svc-close,
    [data-svc-close] {
      appearance: none;
      border: 0;
      background: transparent;
      font-size: 18px;
      color: #666;
      padding: 6px 8px;
      cursor: pointer;
      border-radius: 8px;
    }

    .svc-close:hover,
    [data-svc-close]:hover {
      background: #f0f0f0;
      color: #111;
    }

    /* الجسم */
    .svc-modal-body {
      padding: 18px 20px 6px;
      max-height: min(70vh, 75dvh);
      overflow-y: auto;
    }

    .svc-form .form-row {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
      margin-bottom: 12px;
    }

    .svc-form label {
      font-size: 13px;
      color: #555;
      margin-bottom: 6px;
      display: inline-block;
    }

    .svc-form input[type="text"],
    .svc-form select,
    .svc-form textarea {
      width: 100%;
      border: 1px solid #e3e3e3;
      border-radius: 10px;
      padding: 10px 12px;
      font-size: 14px;
      outline: none;
      background: #fff;
    }

    .svc-form textarea {
      min-height: 110px;
      resize: vertical;
    }

    /* رفع الملفات + المعاينة */
    .svc-files {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .svc-files input[type="file"] {
      border: 1px dashed #cfd6dc;
      padding: 10px;
      border-radius: 10px;
      background: #fcfdff;
    }

    .svc-files-preview,
    [data-svc-files-preview] {
      margin-top: 8px;
      display: grid;
      gap: 10px;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    }

    .svc-files-preview .file-card {
      display: grid;
      grid-template-columns: 44px 1fr;
      gap: 8px;
      align-items: center;
      padding: 8px;
      border: 1px solid #eee;
      border-radius: 10px;
      background: #fff;
    }

    .svc-files-preview .thumb.img-thumb {
      width: 44px;
      height: 44px;
      overflow: hidden;
      border-radius: 8px;
      background: #f5f5f5;
    }

    .svc-files-preview .thumb.img-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .svc-files-preview .thumb.video-thumb {
      width: 44px;
      height: 44px;
      overflow: hidden;
      border-radius: 8px;
      background: #000;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .svc-files-preview .thumb.video-thumb video {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .svc-files-preview .thumb.icon-thumb {
      width: 44px;
      height: 44px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f2f6ff;
      color: #4563d6;
      border-radius: 8px;
    }

    .svc-files-preview .meta .name {
      font-size: 13px;
      font-weight: 600;
      color: #222;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .svc-files-preview .meta .sub {
      font-size: 12px;
      color: #6b7280;
    }

    /* رسالة الخطأ داخل المودال */
    .svc-error {
      display: none;
      margin-top: 6px;
      padding: 10px 12px;
      background: #fff2f2;
      border: 1px solid #ffd7d7;
      color: #b00020;
      border-radius: 10px;
      font-size: 13px;
    }

    /* الذيل */
    .svc-modal-footer {
      padding: 14px 20px;
      border-top: 1px solid #eee;
      display: flex;
      gap: 10px;
      justify-content: flex-start;
      background: #fafafa;
    }

    .svc-btn {
      appearance: none;
      border: 0;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 14px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .svc-btn-primary {
      background: #2563eb;
      color: #fff;
    }

    .svc-btn-primary:disabled {
      opacity: .7;
      cursor: not-allowed;
    }

    .svc-btn-secondary {
      background: #f3f4f6;
      color: #111;
    }

    .svc-btn-secondary:hover {
      background: #e5e7eb;
    }

    /* استجابة */
    @media (max-width: 520px) {

      #serviceRequestModal .modal-dialog,
      [data-svc-modal] .modal-dialog,
      [data-svc-modal] [data-svc-dialog] {
        max-width: 94vw;
        border-radius: 12px;
      }

      .svc-modal-body {
        max-height: 70vh;
      }

      /* --- طبقة الأوفرلاي العامة في الموقع أقل من المودال --- */
      .overlay {
        position: fixed;
        inset: 0;
        z-index: 900;
        /* يجب أن تكون أقل من المودال */
      }

      /* --- مودال طلبات الخدمة أعلى كل شيء --- */
      #serviceRequestModal,
      [data-svc-modal] {
        position: fixed;
        inset: 0;
        display: none;
        /* يبقى مخفي إلى أن نضيف show */
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, .38);
        /* الخلفية الداكنة خلف الصندوق */
        z-index: 2000;
        /* أعلى من .overlay وأي عناصر أخرى */
        padding: 16px;
        opacity: 0;
        transition: opacity .15s ease;
        isolation: isolate;
        /* سياق تكديس مستقل للمودال */
      }

      #serviceRequestModal.show,
      [data-svc-modal].show {
        display: flex;
        opacity: 1;
      }

      /* الصندوق الداخلي */
      #serviceRequestModal .modal-dialog,
      [data-svc-modal] .modal-dialog,
      [data-svc-modal] [data-svc-dialog] {
        position: relative;
        z-index: 2001;
        /* فوق خلفية المودال نفسه */
        width: 100%;
        max-width: 720px;
        background: #fff;
        color: #222;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
        overflow: hidden;
        transform: translateY(8px);
        animation: svcModalIn .18s ease-out both;
      }

      @keyframes svcModalIn {
        from {
          opacity: 0;
          transform: translateY(10px) scale(.98);
        }

        to {
          opacity: 1;
          transform: translateY(0) scale(1);
        }
      }

      /* إخفاء الأوفرلاي العام أثناء فتح مودال الخدمة كي لا يغطيه */
      body.svc-modal-open .overlay {
        display: none !important;
      }

      /* إن كان عندك فلتر تمويه يطبق على .container عند فتح مودالات أخرى،
   لا تطبقه مع مودال الخدمة */
      body.svc-modal-open .container.blurred {
        filter: none !important;
      }

      /* استجابة */
      @media (max-width: 520px) {

        #serviceRequestModal .modal-dialog,
        [data-svc-modal] .modal-dialog,
        [data-svc-modal] [data-svc-dialog] {
          max-width: 94vw;
          border-radius: 12px;
        }
      }



    }



    }

    /* ==================== /ستايل مودال خدمة الطلبات ==================== */

    /* ====================== مودال موافقة الشروط ====================== */
    .terms-consent-modal {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      width: 100% !important;
      height: 100% !important;
      z-index: 99999 !important;
      background: rgba(4, 9, 30, .65) !important;
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
      overflow-y: auto;
      animation: fadeIn 0.3s ease-out;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
      }

      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    .terms-consent-modal.show {
      display: flex !important;
      opacity: 1 !important;
      visibility: visible !important;
    }

    .terms-consent-modal:not(.show) {
      display: none !important;
      opacity: 0;
      visibility: hidden;
    }

    .terms-consent-modal__dialog {
      width: 100% !important;
      max-width: 640px !important;
      background: linear-gradient(135deg, #ffffff, #f7f9ff) !important;
      border-radius: 24px;
      padding: 32px;
      box-shadow: 0 30px 120px rgba(0, 0, 0, .4);
      color: #111 !important;
      position: relative !important;
      z-index: 100000 !important;
      border: 1px solid rgba(69, 99, 214, 0.15);
      margin: auto;
      animation: slideUp 0.3s ease-out;
      max-height: 90vh;
      overflow-y: auto;
      visibility: visible !important;
      opacity: 1 !important;
    }

    .terms-consent-modal__badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(69, 99, 214, 0.12);
      color: #1b2a59;
      border-radius: 999px;
      padding: 6px 14px;
      font-size: 13px;
      font-weight: 600;
    }

    .terms-consent-modal__dialog h2 {
      font-size: 26px;
      margin: 18px 0 10px;
      line-height: 1.4;
    }

    .terms-consent-modal__student {
      display: block;
      font-size: 18px;
      color: #6b7280;
      margin-top: 4px;
    }

    .terms-consent-modal__intro {
      font-size: 15px;
      color: #374151;
      margin-bottom: 16px;
    }

    .terms-consent-modal__intro a {
      color: #2563eb;
      text-decoration: underline;
    }

    .terms-consent-modal__list {
      list-style: none;
      margin: 0 0 20px;
      padding: 0;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .terms-consent-modal__list li {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 12px 14px;
      background: #fff;
      border-radius: 14px;
      border: 1px solid #eef2ff;
      box-shadow: 0 8px 20px rgba(0, 0, 0, .05);
      font-size: 14px;
      color: #1f2937;
    }

    .terms-consent-modal__list i {
      color: #2563eb;
      font-size: 18px;
      margin-top: 2px;
    }

    .terms-consent-modal__actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 12px;
    }

    .terms-consent-modal__btn {
      flex: 1 1 220px;
      border: none;
      border-radius: 14px;
      padding: 14px 18px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: transform .15s ease, box-shadow .15s ease;
    }

    .terms-consent-modal__btn:disabled {
      opacity: .65;
      cursor: not-allowed;
    }

    .terms-consent-modal__btn--primary {
      background: #2563eb;
      color: #fff;
      box-shadow: 0 10px 25px rgba(37, 99, 235, .25);
    }

    .terms-consent-modal__btn--ghost {
      background: #fff;
      color: #b91c1c;
      border: 1px solid rgba(185, 28, 28, .2);
    }

    .terms-consent-modal__btn:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(0, 0, 0, .12);
    }

    .terms-consent-modal__warning {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #b91c1c;
      background: rgba(185, 28, 28, .08);
      border-radius: 12px;
      padding: 10px 12px;
      border: 1px solid rgba(185, 28, 28, .1);
    }

    body.terms-modal-open {
      overflow: hidden !important;
      position: fixed;
      width: 100%;
      height: 100%;
    }

    body.terms-modal-open .container,
    body.terms-modal-open .main-content,
    body.terms-modal-open .content-area {
      filter: blur(3px);
      pointer-events: none;
      user-select: none;
      transition: filter 0.3s ease;
    }

    /* تعديل padding للـ container عند وجود البانر */
    body:has(.admin-view-banner) .container {
      padding-top: 120px;
    }

    /* منع النقر خلف المودال */
    .terms-consent-modal::before {
      content: '';
      position: absolute;
      inset: 0;
      z-index: -1;
    }

    /* ضمان ظهور dialog */
    .terms-consent-modal.show .terms-consent-modal__dialog {
      display: block !important;
      visibility: visible !important;
      opacity: 1 !important;
      transform: none !important;
    }

    /* تحسينات إضافية للمودال */
    .terms-consent-modal__dialog::-webkit-scrollbar {
      width: 8px;
    }

    .terms-consent-modal__dialog::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }

    .terms-consent-modal__dialog::-webkit-scrollbar-thumb {
      background: #888;
      border-radius: 10px;
    }

    .terms-consent-modal__dialog::-webkit-scrollbar-thumb:hover {
      background: #555;
    }

    /* منع النقر خلف المودال */
    .terms-consent-modal.show {
      pointer-events: auto;
    }

    .terms-consent-modal:not(.show) {
      pointer-events: none;
    }

    @media(max-width:520px) {
      .terms-consent-modal {
        padding: 10px;
      }

      .terms-consent-modal__dialog {
        padding: 22px;
        max-width: 95vw;
        border-radius: 20px;
      }

      .terms-consent-modal__btn {
        flex: 1 1 100%;
      }

      .terms-consent-modal__dialog h2 {
        font-size: 22px;
      }
    }
  </style>

  <!-- مكتبات عامة -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>

  <script>
    // قيَم عامة على Window
    window.API_BASE = '<?= site_url() ?>';
    window.USER_IS_PREMIUM = <?= (int) session('is_premium'); ?>;
  </script>
  <script>
    // تعطيل كل سجلات الكونسول في واجهة الطالب إلا عند تمرير studentDebug=1
    (function disableStudentConsoleLogs() {
      try {
        var hasConsole = typeof window !== 'undefined' && typeof window.console === 'object';
        if (!hasConsole) return;
        var debugAllowed = /(?:\?|&)studentDebug=1\b/.test(window.location.search);
        if (debugAllowed) {
          window.console.info('[student-console] Debug mode enabled via studentDebug=1');
          return;
        }
        var noop = function () { };
        var methods = [
          'log',
          'info',
          'debug',
          'warn',
          // 'error', // نترك error مفعلاً للتشخيص
          'group',
          'groupCollapsed',
          'groupEnd',
          'table',
          'trace'
        ];
        for (var i = 0; i < methods.length; i++) {
          if (typeof window.console[methods[i]] === 'function') {
            window.console[methods[i]] = noop;
          }
        }
      } catch (err) {
        // في حال حدث خطأ أثناء التعطيل، لا نفعل شيئًا حتى لا نكسر الصفحة
      }
    })();
  </script>
</head>

<body>
  <link rel="stylesheet" href="https://unpkg.com/intro.js/minified/introjs.min.css">
  <link rel="stylesheet" href="<?= base_url('assets/css/onboarding.css?v=1.1') ?>">

  <?php if (session('role') === 3): // طالب 
      // هوية الطالب (من السيشن/اليوزر الحالي)
      $studentId = (string) session('user_id');
      // (اختياري) إن كان لديك فلاغ في قاعدة البيانات يفيد أن الجولة اكتملت نهائيًا:
      $onboardingDone = !empty(session('onboarding_done'));
      ?>
    <!-- <script>
    // معرّف الطالب للاستخدام في مفاتيح التخزين
    window.__STUDENT_ID__ = "<?= esc($studentId) ?>";

    // (اختياري) نطاق الكوكي ليغطي الساب-دومينات (عدّله ليطابق نطاقك)
    window.__ONB_COOKIE_DOMAIN__ = ".howplatform.net";

    // (اختياري) فلاغ خادمي نهائي — لو لديك حفظ في DB
    <?php if ($onboardingDone): ?>
      window.__ONBOARDING_DONE__ = true;
    <?php endif; ?>

    // ===== Loader يمنع التكرار نهائيًا =====
    (function(){
      function getCookie(name) {
        var escaped = String(name).replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&');
        var rx = new RegExp('(?:^|;\\s*)' + escaped + '=([^;]*)');
        var m = document.cookie.match(rx);
        return m ? decodeURIComponent(m[1]) : null;
      }
      var uid = window.__STUDENT_ID__ || 'anonymous';
      var force = /[?&]tour=1\b/.test(location.search);

      var doneLS = false;
      try {
        doneLS = (localStorage.getItem('onboarding:stations:v6:uid:' + uid) === 'done');
      } catch(e){}

      var doneCK = (getCookie('onb_v6_' + uid + '_done') === '1');
      var doneServer = (window.__ONBOARDING_DONE__ === true);

      // إذا لم ينطبق أي "تم" — أو كان force=true — نحمل السكربت ونطلق الجولة.
      if (force || !(doneLS || doneCK || doneServer)) {
        window.__SHOW_ONBOARDING__ = true; // إشارة للسكربت الداخلي
        var s = document.createElement('script');
        s.src = "<?= base_url('assets/js/onboarding.tour.stations.js?v=1.2') ?>";
        s.defer = true;
        document.head.appendChild(s);
      }
      // غير ذلك: لا نفعل شيئًا — لن تُحمّل الجولة ولا سكربتها.
    })();
  </script>-->
  <?php endif; ?>


  <?= view('students/partials/admin_view_banner') ?>
  <div class="container">
    <?= view('students/partials/sidebar') ?>
    <div class="main-content">
      <?= view('students/partials/navbar') ?>
      <div class="content-area" id="student-content-area">
        <!-- سيتم تحميل الصفحات الديناميكية هنا -->
      </div>
    </div>
    <div class="overlay"></div>
  </div>
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Router: ES Module + defer -->
  <script type="module" defer src="<?= base_url('assets/js/students/student_router.js?v=1.9.29') ?>"></script>

  <!-- Admin View Script -->
  <script src="<?= base_url('assets/js/students/admin_view.js?v=1.0.1') ?>" defer></script>

  <!-- سكربتات عامة -->
  <script src="<?= base_url('assets/js/alerts.js') ?>" defer></script>
  <script src="<?= base_url('assets/js/vendor/gsap.min.js?v=3.13.0') ?>" defer></script>
  <script src="<?= base_url('assets/js/FateenChatBot.js?v=1.3.10') ?>" defer></script>
  <script src="<?= base_url('assets/js/FateenCharacter.js?v=1.0.1') ?>" defer></script>
  <script src="<?= base_url('assets/js/audio_player.js?v=1.0.2') ?>" defer></script>

  <!-- ✅ سكربت المودال بصيغة UMD (classic) ليعرّف window.openServiceRequestModal -->

  <!-- مودالات HTML -->
  <?= $this->include('students/partials/modals/task_view_modal') ?>
  <?= $this->include('students/partials/modals/service_request_modal') ?>
  <?= $this->include('students/partials/modals/terms_consent_modal') ?>




  <script type="module">
    import { ServiceRequestModal } from '<?= base_url('assets/js/students/service_request_modal.js?v=2.0.23') ?>';
    ServiceRequestModal.init();
    // مهم: اجعل الكائن متاحًا للـ onclick
    window.ServiceRequestModal = ServiceRequestModal;
  </script>

  <script src="<?= base_url('assets/js/students/terms_consent_modal.js?v=1.0.1') ?>" defer></script>
  <script src="<?= base_url('assets/js/students/notifications_center.js?v=1.0.2') ?>" defer></script>
  <script>
    window.__FCM__ = {
      config: {
        apiKey: '<?= esc(getenv('FIREBASE_API_KEY') ?: '', 'js') ?>',
        authDomain: '<?= esc(getenv('FIREBASE_AUTH_DOMAIN') ?: '', 'js') ?>',
        projectId: '<?= esc(getenv('FIREBASE_PROJECT_ID') ?: '', 'js') ?>',
        storageBucket: '<?= esc(getenv('FIREBASE_STORAGE_BUCKET') ?: '', 'js') ?>',
        messagingSenderId: '<?= esc(getenv('FIREBASE_MESSAGING_SENDER_ID') ?: '', 'js') ?>',
        appId: '<?= esc(getenv('FIREBASE_APP_ID') ?: '', 'js') ?>'
      },
      vapidKey: '<?= esc(getenv('FIREBASE_VAPID_KEY') ?: '', 'js') ?>'
    };
  </script>
  <script src="<?= base_url('assets/js/webpush.js?v=1.0.0') ?>" defer></script>




  <script>
    // توفير user_id عالمياً للتوافق
    window.currentUserId = <?= json_encode($uid ?? null) ?>;

    // توفير معلومات وضع الاستعراض للأدمن
    <?php if (\App\Helpers\StudentViewHelper::isAdminViewingAsStudent()): ?>
      window.__IS_ADMIN_VIEWING_AS_STUDENT__ = true;
    <?php else: ?>
      window.__IS_ADMIN_VIEWING_AS_STUDENT__ = false;
    <?php endif; ?>
  </script>

  <!-- طباعة بيانات السشن (تشخيص) -->
  <script>
    const sessionData = <?= json_encode(session()->get(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>;
    console.log("🔎 Session Data:", sessionData);
  </script>

  <!-- سكربت موحّد (إصلاح الـ RegExp داخل getCookie) -->
  <script defer>
    (() => {
      'use strict';
      if (window.__kayfUnifiedInit) return;
      window.__kayfUnifiedInit = true;

      /* ===================== Helpers لتخزين الحالة عبر النطاقات ===================== */
      const COOKIE_DAYS = 365;
      function setCookie(name, value, days, domain) {
        try {
          const exp = new Date(Date.now() + days * 864e5).toUTCString();
          const base = `${name}=${encodeURIComponent(value)};expires=${exp};path=/;SameSite=Lax${location.protocol === 'https:' ? ';Secure' : ''}`;
          document.cookie = base;
          if (domain) document.cookie = `${name}=${encodeURIComponent(value)};expires=${exp};path=/;domain=${domain};SameSite=Lax${location.protocol === 'https:' ? ';Secure' : ''}`;
        } catch (_) { }
      }
      function getCookie(name) {
        const escaped = name.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&');
        const m = document.cookie.match(new RegExp('(?:^|; )' + escaped + '=([^;]*)'));
        return m ? decodeURIComponent(m[1]) : '';
      }

      const storageMux = {
        get(key) {
          let raw = null;
          try { raw = localStorage.getItem(key); } catch (_) { }
          if (!raw) raw = getCookie(key) || null;
          if (!raw && window.name) {
            try {
              const stash = JSON.parse(window.name);
              if (stash && typeof stash === 'object' && stash[key]) raw = stash[key];
            } catch (_) { }
          }
          return raw;
        },
        set(key, json) {
          try { localStorage.setItem(key, json); } catch (_) { }
          setCookie(key, json, COOKIE_DAYS);
          const parts = location.hostname.split('.');
          if (parts.length > 2) setCookie(key, json, COOKIE_DAYS, '.' + parts.slice(-2).join('.'));
          try {
            const stash = window.name ? JSON.parse(window.name) : {};
            stash[key] = json;
            window.name = JSON.stringify(stash);
          } catch (_) {
            window.name = JSON.stringify({ [key]: json });
          }
        }
      };

      /* ===================== Checklist ===================== */
      const CHECKLIST = {
        selector: '#checklist',
        version: 'v2',
        key() {
          const el = document.querySelector(this.selector);
          const user = (el && el.dataset.user) ? el.dataset.user : 'anon';
          return `diploma_checklist_${this.version}_${user}`;
        },
        read() {
          const raw = storageMux.get(this.key()) || '[]';
          try { const a = JSON.parse(raw); return Array.isArray(a) ? a : []; } catch (_) { return []; }
        },
        write(ids) { storageMux.set(this.key(), JSON.stringify(ids)); },
        apply() {
          const list = document.querySelector(this.selector);
          if (!list) return;
          const ids = new Set(this.read());
          list.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = ids.has(cb.id); });
        },
        save() {
          const list = document.querySelector(this.selector);
          if (!list) return;
          const ids = [];
          list.querySelectorAll('input[type="checkbox"]').forEach(cb => { if (cb.checked) ids.push(cb.id); });
          this.write(ids);
        },
        bind() {
          if (document.body.__clDelegated) return;
          document.addEventListener('change', (e) => {
            if (e.target && e.target.matches && e.target.matches(this.selector + ' input[type="checkbox"]')) this.save();
          }, true);
          document.body.__clDelegated = true;
        }
      };

      /* ===================== الجامعة النشطة ===================== */
      const UNI = {
        version: 'v1',
        keyName: 'kayf_uni_active_',
        text2key: { "الجامعة الأردنية": "ju", "جامعة اليرموك": "yu", "الجامعة الهاشمية": "hu", "جامعة مؤتة": "mu" },
        get key() { return `${this.keyName}${this.version}`; },
        getKeyFromEl(el) { return el?.dataset?.uni || this.text2key[(el?.textContent || '').trim()] || null; },
        read() {
          const raw = storageMux.get(this.key);
          try { const obj = raw ? JSON.parse(raw) : null; return obj && obj.k ? obj.k : null; } catch (_) { return null; }
        },
        write(val) { storageMux.set(this.key, JSON.stringify({ k: val })); },
        activate(key, { scroll = true } = {}) {
          if (!key) return;
          document.querySelectorAll('.uni-panel').forEach(p => p.classList.toggle('active', p.dataset.uni === key));
          document.querySelectorAll('#universities-list .chip').forEach(ch => ch.classList.toggle('active', this.getKeyFromEl(ch) === key));
          if (scroll) {
            document.querySelector(`.uni-panel[data-uni="${key}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
          this.write(key);
          window.__activeUni = key;
        },
        bind() {
          if (document.body.__uniDelegated) return;
          document.addEventListener('click', (e) => {
            const chip = e.target.closest('#universities-list .chip');
            if (!chip) return;
            const k = this.getKeyFromEl(chip);
            if (k) this.activate(k);
          });
          document.body.__uniDelegated = true;
        },
        init() {
          const list = document.querySelector('#universities-list');
          const panels = document.querySelectorAll('.uni-panel');
          if (!list || !panels.length) return;
          this.bind();
          const fromHash = new URLSearchParams(location.hash.slice(1)).get('uni');
          const fromStore = this.read();
          const firstKey = panels[0]?.dataset?.uni || null;
          const chosen = fromHash || fromStore || window.__activeUni || firstKey;
          if (chosen) this.activate(chosen, { scroll: false });
        }
      };

      /* ===================== وظائف إضافية خفيفة ===================== */
      function updateYear(root = document) {
        root.querySelectorAll('#currentYear, [data-current-year]').forEach(el => {
          el.textContent = new Date().getFullYear();
        });
      }
      function initMindmap() {
        const card = document.getElementById('mindmap-dl');
        if (!card || card.__bound) return;
        card.__bound = true;
        let revealed = false;
        card.addEventListener('click', (e) => {
          const btn = e.target.closest('.dl-btn');
          if (btn) return;
          if (!revealed) { e.preventDefault(); card.classList.add('touch'); revealed = true; }
          else { card.classList.remove('touch'); revealed = false; }
        });
        document.addEventListener('click', (e) => {
          if (revealed && !card.contains(e.target)) { card.classList.remove('touch'); revealed = false; }
        }, true);
      }

      /* ===================== مُهيّئ موحّد + مراقِب ===================== */
      function initAll() {
        CHECKLIST.apply();
        CHECKLIST.bind();
        UNI.init();
        updateYear();
        initMindmap();
      }
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll, { once: true });
      } else {
        initAll();
      }
      window.addEventListener('pageshow', initAll);
      window.addEventListener('beforeunload', () => { try { CHECKLIST.save(); } catch (_) { } });

      if (!window.__kayfMo) {
        window.__kayfMo = new MutationObserver(() => {
          clearTimeout(initAll.__t);
          initAll.__t = setTimeout(initAll, 60);
        });
        window.__kayfMo.observe(document.documentElement, { childList: true, subtree: true });
      }
    })();
  </script>

  <script>
    // واجهات عامة آمنة
    window.scrollToResult = function (type, id) {
      console.log('Scroll to result:', type, id);
    };
    window.reloadCurrentSection = function () {
      const fn = window.__student_reloadCurrentSection;
      if (typeof fn === 'function') { fn(); } else { location.reload(); }
    };
  </script>

  <script>
    // ينادي API لمسح الكاش ثم يعيد تحميل القسم الحالي
    async function clearCourseCachesAndReload(courseOfferingId = null) {
      try {
        const url = courseOfferingId
          ? `${API_BASE}/students/course-content/clear-cache/${encodeURIComponent(courseOfferingId)}`
          : `${API_BASE}/students/course-content/clear-cache`;
        await fetch(url, { method: 'POST' });
      } catch (e) {
        console.error('clear cache error:', e);
      } finally {
        if (typeof reloadCurrentSection === 'function') reloadCurrentSection();
        else location.reload();
      }
    }
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('#refresh-courses-btn');
      if (!btn) return;
      e.preventDefault();
      clearCourseCachesAndReload();
    });
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('#refresh-this-course');
      if (!btn) return;
      e.preventDefault();
      const coId = window.__CURRENT_COURSE_OFFERING_ID || null;
      clearCourseCachesAndReload(coId || null);
    });
  </script>

  <!-- مثال على تهيئة موديول الترقية (ESM) -->
  <script type="module">
    import { init as initUpgrade } from "<?= base_url('assets/js/students/upgrade_modal.js') ?>";
    initUpgrade({
      ui: { modalUrl: '<?= site_url('ui/upgrade-modal') ?>' },
      endpoints: {
        plans: '<?= site_url('api/plans') ?>',
        payMethods: '<?= site_url('api/payment-methods') ?>',
        request: '<?= site_url('api/upgrade/request') ?>',
      },
      csrf: { token: '<?= csrf_token() ?>', hash: '<?= csrf_hash() ?>' },
    });
  </script>


  <!-- ضبط فترة التحقق (ملّي ثانية) — غيّرها براحتك -->
  <script>
    window.SVC_UNREAD_POLL_MS = 2000; // 2 ثانية
  </script>

  <!-- فوتر عام يظهر في كل أقسام الطالب (يُعاد حقنه بعد كل تحميل قسم) -->
  <script>
    (function () {
      var ca = document.getElementById('student-content-area');
      if (!ca) return;
      function addFooter() {
        if (ca.querySelector('.app-footer')) return;
        var f = document.createElement('footer');
        f.className = 'app-footer';
        f.innerHTML = '© ' + new Date().getFullYear() + ' منصة كيف التعليمية — جميع الحقوق محفوظة';
        ca.appendChild(f);
      }
      addFooter();
      try { new MutationObserver(addFooter).observe(ca, { childList: true }); } catch (e) {}
    })();
  </script>

</body>

</html>