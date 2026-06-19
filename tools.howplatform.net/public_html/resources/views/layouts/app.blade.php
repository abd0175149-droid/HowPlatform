<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  {{-- Bootstrap (مطابق للرئيسي) --}}
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

  {{-- ================= تهيئة SEO الموحدة (مطابقة للرئيسي) ================= --}}
  @php
    $siteName   = 'HOW Tools | أدوات كيف التعليمية';
    $title      = trim(($seo['title'] ?? '')) ?: $siteName;
    $desc       = trim(($seo['description'] ?? '')) ?: 'مجموعة أدوات تعليمية عربية لتحويل ودمج الملفات، إنشاء QR، ضغط الوسائط، وغير ذلك لدعم المعلّم والطالب.';
    $canonical  = $seo['canonical'] ?? url()->current();

    // صورة OG مطلقة دائمًا
    $ogRel      = $seo['og_image'] ?? asset('assets/images/Logo_Tools.svg'); // تأكد من وضع logo.png (1200x630) في public/assets/images
    $ogImage    = \Illuminate\Support\Str::startsWith($ogRel, ['http://', 'https://']) ? $ogRel : url($ogRel);

    // Robots لكل صفحة (يمكن تخصيصه من كل View)
    $robots     = $seo['robots'] ?? 'index,follow';
  @endphp

  {{-- ================= SEO الأساسية ================= --}}
  <title>{{ $title }}</title>
  <meta name="description" content="{{ $desc }}">
  <meta name="robots" content="{{ $robots }}">
  <link rel="canonical" href="{{ $canonical }}">

  {{-- Adsense account declaration (لا يستدعي السكربت وحده) --}}
  <meta name="google-adsense-account" content="ca-pub-3572625518315562">

  {{-- ================= Hreflang ================= --}}
  <link rel="alternate" href="{{ $canonical }}" hreflang="ar-jo">
  <link rel="alternate" href="{{ $canonical }}" hreflang="x-default">

  {{-- ================= Open Graph / Twitter ================= --}}
  <meta property="og:type" content="website">
  <meta property="og:locale" content="ar_JO">
  <meta property="og:site_name" content="HOW Tools">
  <meta property="og:title" content="{{ $title }}">
  <meta property="og:description" content="{{ $desc }}">
  <meta property="og:url" content="{{ $canonical }}">
  <meta property="og:image" content="{{ $ogImage }}">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $title }}">
  <meta name="twitter:description" content="{{ $desc }}">
  <meta name="twitter:image" content="{{ $ogImage }}">

  <link rel="icon" href="{{ asset('assets/images/icon.ico') }}" sizes="32x32">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  {{-- ================= JSON-LD ================= --}}
  @include('partials.seo_jsonld')

  {{-- ================= Google Ads (نفس الرئيسي) ================= --}}
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3572625518315562"
          crossorigin="anonymous"></script>

  {{-- Bootstrap JS (مطابق للرئيسي) --}}
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  {{-- ================= خطوط Almarai (مطابقة للرئيسي) ================= --}}
  <style>
    /* @font-face محلي — تأكد من إضافة src عندك (ملفات الخط) */
    @font-face {
      font-family: 'Almarai';
      font-style: normal;
      font-weight: 400;
      font-display: swap;
    }
    @font-face {
      font-family: 'Almarai';
      font-style: normal;
      font-weight: 800;
      font-display: swap;
    }
    html, body {
      font-family: 'Almarai', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
    }
  </style>
<!-- Boot: حدد حالة السايدبار مبكرًا (ديسكتوب مفتوح / موبايل مغلق) -->
<script>
  (function () {
    var r = document.documentElement;
    r.classList.add((window.innerWidth > 1024) ? 'sidebar-open' : 'sidebar-closed');
    // لاحظ: لا نضيف 'reveal-ready' هنا عمداً حتى لا تظهر .reveal قبل تهيئة الـ JS
  })();
</script>

<!-- Gate CSS: امنع ظهور .reveal مطلقًا حتى تُضاف 'reveal-ready' من سكربت الواجهة -->
<style>
  html:not(.reveal-ready) .reveal { display: none !important; }

  /* قواعد التشغيل بعد الجاهزية */
  .sidebar-open  .reveal { display: none !important; }   /* عند فتح السايدبار: مخفية */
  .sidebar-closed .reveal { display: block !important; } /* عند إغلاقه: تظهر */
</style>


  {{-- ================= home.css: inline إن وُجد، وإلا preload غير حاجب (مطابق للرئيسي) ================= --}}
  @php
    $homeCssPath = public_path('assets/css/home.css');
  @endphp
  @if (is_file($homeCssPath))
    <style>{!! file_get_contents($homeCssPath) !!}</style>
  @else
    <link rel="preload" as="style" href="{{ asset('assets/css/home.css?v=1.2.6') }}" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset('assets/css/home.css?v=1.2.6') }}"></noscript>
  @endif

  {{-- ================= Font Awesome: محلي إن وُجد، وإلا CDN غير حاجب (مطابق للرئيسي) ================= --}}
  @php
    $faCssPath = public_path('assets/css/all.min.css');
  @endphp
  @if (is_file($faCssPath))
    <link rel="stylesheet" href="{{ asset('assets/css/all.min.css') }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ asset('assets/css/all.min.css') }}"></noscript>
  @else
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          media="print"
          onload="this.media='all'"
          crossorigin>
    <noscript>
      <link rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
            crossorigin>
    </noscript>
  @endif

  {{-- مساحة لأنماط الصفحات الفرعية --}}
  @stack('styles')
</head>
<body>
    @include('partials.header')
    <div class="app-container">
    <aside class="sidebar" id="sidebar">
      @hasSection('sidebar')
        @yield('sidebar')
      @else
        @include('partials.sidebar') {{-- السايدبار الافتراضي لباقي الصفحات --}}
      @endif
    </aside>
        <main class="main-content" id="mainContent">
            @yield('content')
        </main>
    </div>
    @include('partials.footer')
    <script src="{{ asset('assets/js/home.js') }}"></script>
    @stack('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('assets/js/qrcode.min.js') }}" defer></script>
</body>
</html>
