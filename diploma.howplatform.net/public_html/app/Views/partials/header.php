<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <?= view('partials/seo_jsonld') ?>

  <title>منصة كيف التعليمية | الدبلوم العالي </title>
  <meta name="description" content="دليل عملي لبرنامج الدبلوم العالي في التربية: مهام أسبوعية، مقالات تكوينية وختامية، شروحات وأدوات تعليمية.">
  <link rel="canonical" href="https://diploma.howplatform.net/">
  <link rel="alternate" href="https://diploma.howplatform.net/" hreflang="ar-JO">
  <link rel="alternate" href="https://diploma.howplatform.net/" hreflang="ar">
  <link rel="alternate" href="https://diploma.howplatform.net/" hreflang="x-default">

  <!-- Open Graph -->
  <meta property="og:site_name" content="منصة كيف التعليمية">
  <meta property="og:title" content="منصة كيف التعليمية | الدبلوم العالي لإعداد المعلمين">
  <meta property="og:description" content="كل ما تحتاجه للنجاح في الدبلوم العالي: مهام أسبوعية، شروحات، مقالات، وأدوات.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://diploma.howplatform.net/">
  <meta property="og:image" content="https://diploma.howplatform.net/assets/images/icon.ico">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title"  content="منصة كيف التعليمية | الدبلوم العالي لإعداد المعلمين">
  <meta name="twitter:description" content="كل ما تحتاجه للنجاح في الدبلوم العالي: مهام أسبوعية، شروحات، مقالات، وأدوات.">
  <meta name="twitter:image" content="https://diploma.howplatform.net/assets/images/icon.ico">

  <link rel="icon" type="image/png" href="<?= base_url('assets/images/icon.ico') ?>">
    <meta name="google-adsense-account" content="ca-pub-3572625518315562">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3572625518315562"
     crossorigin="anonymous"></script>
  <!-- ====== الخطوط: Almarai محلي إن وُجد، وإلا Google Fonts غير حاجب ====== -->
  <?php
    $almaraiReg = FCPATH.'assets/css/fonts/Almarai-Regular.woff2';
    $almaraiXB  = FCPATH.'assets/css/fonts/Almarai-ExtraBold.woff2';
  ?>
  <?php if (is_file($almaraiReg) && is_file($almaraiXB)): ?>
    <link rel="preload" as="font" type="font/woff2" href="<?= base_url('assets/css/fonts/Almarai-Regular.woff2') ?>" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="<?= base_url('assets/css/fonts/Almarai-ExtraBold.woff2') ?>" crossorigin>
    <style>
      @font-face{font-family:'Almarai';font-style:normal;font-weight:400;font-display:swap;
        src:url('<?= base_url('assets/css/fonts/Almarai-Regular.woff2') ?>') format('woff2');}
      @font-face{font-family:'Almarai';font-style:normal;font-weight:800;font-display:swap;
        src:url('<?= base_url('assets/css/fonts/Almarai-ExtraBold.woff2') ?>') format('woff2');}
      html,body{font-family:'Almarai',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,'Noto Sans',sans-serif;}
    </style>
  <?php else: ?>
    <!-- Google Fonts بطريقة غير حاجبة + display=swap -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style"
          href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
      <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap">
    </noscript>
  <?php endif; ?>

  <!-- ====== style.css: Inline إن وُجد (3KB)، وإلا تحميل غير حاجب ====== -->
  <?php $stylePath = FCPATH.'assets/css/style.css'; ?>
  <?php if (is_file($stylePath)): ?>
    <style><?= file_get_contents($stylePath) ?></style>
  <?php else: ?>
    <link rel="preload" as="style" href="<?= base_url('assets/css/style.css?v=1.0.2') ?>" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="<?= base_url('assets/css/style.css?v=1.0.2') ?>"></noscript>
  <?php endif; ?>

  <!-- ====== Font Awesome: محلي إن وُجد (مع font-display:swap)، وإلا CDN غير حاجب ====== -->
  <?php $faCss = FCPATH.'assets/css/all.min.css'; ?>
  <?php if (is_file($faCss)): ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/all.min.css') ?>" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?= base_url('assets/css/all.min.css') ?>"></noscript>
    <!-- (اختياري) Preload لأكثر ويب-فونت استخدامًا -->
    <!-- <link rel="preload" as="font" type="font/woff2" href="<?= base_url('assets/css/webfonts/fa-solid-900.woff2') ?>" crossorigin> -->
  <?php else: ?>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          media="print" onload="this.media='all'" crossorigin>
    <noscript>
      <link rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin>
    </noscript>
  <?php endif; ?>

  <!-- مساحة لأنماط إضافية للصفحات -->
  <?= $this->renderSection('styles') ?>
</head>


<body>
<header>
        <div class="logo-group">
        <div class="logo">
                <img src="<?= base_url('assets/images/Logo_how_gr.svg') ?>" alt="كيف التعليمية" width="150" height="30">
            <div class="logo-text">منصة كيف؟ التعليمية</div>
        </div>
        <div class="hamburger" id="hamburger" role="button" aria-label="القائمة الرئيسية" tabindex="0">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
    
    <div class="nav-container">
        <?php
            $uri = service('uri');
            $segment1 = $uri->getSegment(1, '');
            $segment2 = '';
            
            if ($segment1 === 'auth') {
                $segment2 = $uri->getSegment(2, '');
            }
            
            $isProfilePage = ($segment1 === 'auth' && ($segment2 === 'complete-profile' || $segment2 === 'survey' || $segment2 === 'accept_terms' || $segment2 === 'temp-profile'));
        ?>
        
        <ul class="nav-links">
            <?php if ($isProfilePage): ?>
                <li>
                    <a href="<?= site_url('auth/logout') ?>">
                        <i class="fas fa-sign-out-alt"></i>
                        تسجيل الخروج
                    </a>
                </li>
            <?php else: ?>
            <li>
                <a href="<?= site_url('/') ?>" class="<?= ($segment1 === '') ? 'active-link' : '' ?>">
                    <i class="fas fa-home"></i>
                    الصفحة الرئيسية
                </a>
            </li>
            <li>
                <a href="https://auth.howplatform.net/login?redirect=https://diploma.howplatform.net/students" class="<?= ($segment2 === 'login') ? 'active-link' : '' ?>">
                    <i class="fas fa-sign-in-alt"></i>
                    تسجيل الدخول
                </a>
            </li>
            <li>
                <a href="https://auth.howplatform.net/register?redirect=https://diploma.howplatform.net/students" class="<?= ($segment2 === 'register') ? 'active-link' : '' ?>">
                    <i class="fas fa-user-plus"></i>
                    التسجيل
                </a>
            </li>
           <li>
                <a href="<?= site_url('auth/news') ?>" class="<?= ($segment2 === 'news') ? 'active-link' : '' ?>">
                    <i class="fas fa-newspaper"></i>
                    الأخبار
                </a>
            </li>
            <li>
                <a href="<?= site_url('auth/contact') ?>" class="<?= ($segment2 === 'contact') ? 'active-link' : '' ?>">
                    <i class="fas fa-headset"></i>
                    الدعم
                </a>
            </li>
            <?php endif; ?>
        </ul>

    </div>

</header>
    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-header">
            <div class="mobile-logo">
           <img src="<?= base_url('assets/images/Logo_how_gr.svg') ?>" alt="شعار المنصة" width="150" height="30" style="height:30px">
                <h3> منصة كيف التعليمية</h3>
            </div>
            <button class="close-menu" id="closeMenu" aria-label="إغلاق القائمة">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <ul class="mobile-nav">
            <?php
                $uri = service('uri');
                $segment1 = $uri->getSegment(1, '');
                $segment2 = '';
                
                if ($segment1 === 'auth') {
                    $segment2 = $uri->getSegment(2, '');
                }
                
                $isProfilePage = ($segment1 === 'auth' && ($segment2 === 'complete-profile' || $segment2 === 'survey' || $segment2 === 'accept_terms' || $segment2 === 'temp-profile'));
                ?>

            <?php if ($isProfilePage): ?>
            <li>
                    <a href="<?= site_url('auth/logout') ?>">
                        <i class="fas fa-sign-out-alt"></i>
                        تسجيل الخروج
                    </a>
                </li>
            <?php else: ?>
            <li>
                <a href="<?= site_url('/') ?>" class="<?= ($segment1 === '/' || $segment1 === '') ? 'active-link' : '' ?>"> 
                    <i class="fas fa-home"></i>
                    الصفحة الرئيسية
                </a>
            </li>
            <li>
                <a href="https://auth.howplatform.net/login?redirect=https://diploma.howplatform.net/students" class="<?= ($segment2 === 'login') ? 'active-link' : '' ?>">
                    <i class="fas fa-sign-in-alt"></i>
                    تسجيل الدخول
                </a>
            </li>
            <li>
                <a href="https://auth.howplatform.net/register?redirect=https://diploma.howplatform.net/students" class="<?= ($segment2 === 'register') ? 'active-link' : '' ?>">
                    <i class="fas fa-user-plus"></i>
                    التسجيل
                </a>
            </li>
            <li>
                <a href="<?= site_url('auth/contact') ?>" class="<?= ($segment2 === 'contact') ? 'active-link' : '' ?>">
                    <i class="fas fa-headset"></i>
                    الدعم
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="overlay" id="overlay"></div>
<script>
        // Mobile Menu Toggle
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        const closeMenu = document.getElementById('closeMenu');
        const overlay = document.getElementById('overlay');
        
        hamburger.addEventListener('click', () => {
            mobileMenu.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
        
        closeMenu.addEventListener('click', () => {
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = 'auto';
        });
        
        overlay.addEventListener('click', () => {
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = 'auto';
        });
        
        // Mobile Dropdown Toggle
        const mobileDropdownToggle = document.getElementById('mobileDropdownToggle');
        const mobileDropdown = document.getElementById('mobileDropdown');
            
         if (mobileDropdownToggle && mobileDropdown) {
            mobileDropdownToggle.addEventListener('click', (e) => {
                e.preventDefault();
                mobileDropdown.classList.toggle('active');
        
                // Toggle icon
                const icon = mobileDropdownToggle.querySelector('.fa-chevron-down, .fa-chevron-up');
                if (mobileDropdown.classList.contains('active')) {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            });
        }
        
        // Dropdown hover effect
        const dropdownItems = document.querySelectorAll('.dropdown-item');
        dropdownItems.forEach(item => {
            item.addEventListener('mouseenter', () => {
                item.style.transform = 'translateX(5px)';
            });
            
            item.addEventListener('mouseleave', () => {
                item.style.transform = 'translateX(0)';
            });
        });
    </script>

    <main>
<script defer src="<?= base_url('assets/js/alerts.js') ?>"></script>