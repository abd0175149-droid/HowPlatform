<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <title>لوحة التحكم - منصة كيف التعليمية</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

<!-- CSRF for AJAX -->
<meta name="csrf-token"  content="<?= csrf_hash() ?>">
<meta name="csrf-header" content="<?= csrf_header() ?>"> <!-- غالبًا X-CSRF-TOKEN -->
<!-- (اختياري) اسم كوكي CSRF إذا أردت القراءة من الكوكي مباشرة -->
<meta name="csrf-cookie" content="<?= config('Security')->cookieName ?? 'csrf_cookie_name' ?>">



  <?php $JS_VER = '1.10.15'; // ← وحّد رقم الإصدار هنا فقط ?>

  <!-- تحسين التحميل المسبق للـ CDNs الشائعة -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin />
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin />

  <!-- Google Font: non-blocking -->
  <link rel="preload" as="style"
        href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap">
  <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap"
        media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap">
  </noscript>

  <!-- Font Awesome: non-blocking -->
  <link rel="preload" as="style"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  </noscript>

  <!-- CSS الرئيسي (اتركه كما هو إن لم تُبدّل الملف) -->
  <link rel="stylesheet" href="<?= base_url('assets/css/admin_style.css?v=1.11.5') ?>">

  <link rel="icon" type="image/png" href="<?= base_url('assets/images/icon.ico') ?>">

  <style>
    * { font-family: 'Almarai', -apple-system, BlinkMacSystemFont, sans-serif; }
    body {
      zoom: 80%;
    }
  </style>

  <!-- مكتبات عامّة -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js" defer></script>
<script src="https://cdn.tailwindcss.com"></script>

  <!-- إعدادات عامة موحّدة -->
  <script defer>
    (function () {
      window.__APP_CONFIG__ = Object.freeze({
        API_BASE: '<?= site_url() ?>',
        currentUserId: <?= (int) (session('user_id') ?? 0) ?>,
        currentUserRole: <?= (int) (
          session('ROLE')
          ?? session('role')
          ?? session('account_type_id')
          ?? session('account_type')
          ?? session('role_id')
          ?? 0
        ) ?>
      });

      // أداة مساعدة: تحميل سكربت مرة واحدة (مفيدة لـ Chart.js داخل صفحات الرسم)
      window.loadScriptOnce = (src) => new Promise((resolve, reject) => {
        const id = 'script:' + src;
        const exist = document.getElementById(id);
        if (exist) return exist.dataset.ready ? resolve() : exist.addEventListener('load', resolve, { once: true });
        const s = document.createElement('script');
        s.id = id; s.src = src; s.defer = true; s.dataset.ready = '';
        s.onload = () => { s.dataset.ready = '1'; resolve(); };
        s.onerror = reject;
        document.head.appendChild(s);
      });
    })();
  </script>

  <!-- مهم: modulepreload بنفس الإصدار الذي سنشغّله لاحقًا -->
  <link rel="modulepreload" href="<?= base_url("assets/js/admins/admin_router.js?v={$JS_VER}") ?>">
</head>

<body>
  <div class="container">
    <?= view('admins/partials/sidebar') ?>
    <div class="main-content">
      <?= view('admins/partials/navbar', ['adminName' => $adminName, 'profileImage' => $profileImage]) ?>
      <div class="content-area" id="contentArea"><!-- صفحات ديناميكية --></div>
    </div>
    <div class="overlay"></div>
  </div>

  <!-- نفس الإصدار المستخدم في modulepreload -->
  <script type="module" defer src="<?= base_url("assets/js/admins/admin_router.js?v={$JS_VER}") ?>"></script>
  <script defer src="<?= base_url('assets/js/admins/student_view.js') ?>"></script>


<!-- ضع هذا بعد تضمين النافبار مباشرة -->
<script>
  // ✅ نحقن جلسة PHP مثل الطالب
  window.__SESSION__ = <?= json_encode(session()->get(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>;
  window.__SESSION_ORIGIN__ = 'admin-layout-inline';

  // نفس شكل لوج الطالب مع تعريف المصدر
  console.log(
    "admins:<?= (int) (session('user_id') ?? 0) ?> 🔎 Session Data:",
    window.__SESSION__
  );
</script>

  <!-- سكربتات عامة -->
  <script defer src="<?= base_url('assets/js/admin-modal-utils.js') ?>"></script>
  <script defer src="<?= base_url('assets/js/alerts.js') ?>"></script>
  <script defer src="<?= base_url('assets/js/chatBotBcak.js?v=1.3.6') ?>"></script>
  <script defer src="<?= base_url('assets/js/audio_player.js?v=1.0.2') ?>"></script>
</body>
</html>
