<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

 {{-- ================= SEO الأساسية ================= --}}
    <title>{{ $seo['title'] ?? 'مكتبة كيف التعليمية | HOWPlatform Library' }}</title>
    <meta name="description" content="{{ $seo['description'] ?? 'مكتبة رقمية تعليمية تحتوي على كتب مدرسية، أدلة معلم، أوراق عمل ومواد داعمة للطلاب والمعلمين.' }}">
    <meta name="keywords" content="مكتبة تعليمية, كيف التعليمية, HOWPlatform, كتب مدرسية, أدلة معلم, أوراق عمل, التعليم في الأردن, مكتبة رقمية">
    <link rel="canonical" href="{{ $seo['canonical'] ?? url()->current() }}">
    
    {{-- Adsense --}}
    <meta name="google-adsense-account" content="ca-pub-3572625518315562">
    <script id="adsbygoogle-js" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3572625518315562"
     crossorigin="anonymous"></script>

    {{-- ================= Open Graph / Twitter ================= --}}
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ar_JO">
    <meta property="og:site_name" content="مكتبة كيف التعليمية">
    <meta property="og:title" content="{{ $seo['title'] ?? 'مكتبة كيف التعليمية | HOWPlatform Library' }}">
    <meta property="og:description" content="{{ $seo['description'] ?? 'مكتبة رقمية تعليمية تحتوي على كتب مدرسية، أدلة معلم، أوراق عمل ومواد داعمة للطلاب والمعلمين.' }}">
    <meta property="og:url" content="{{ $seo['canonical'] ?? url()->current() }}">
    <meta property="og:image" content="{{ $seo['og_image'] ?? asset('assets/images/library/icon.ico') }}">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['title'] ?? 'مكتبة كيف التعليمية | HOWPlatform Library' }}">
    <meta name="twitter:description" content="{{ $seo['description'] ?? 'مكتبة رقمية تعليمية تحتوي على كتب مدرسية، أدلة معلم، أوراق عمل ومواد داعمة للطلاب والمعلمين.' }}">
    <meta name="twitter:image" content="{{ $seo['og_image'] ?? asset('assets/images/library/icon.ico') }}">
    <link rel="icon" href="{{ asset('assets/images/icon.ico') }}" sizes="32x32">


  {{-- ================= Fonts: Almarai محلي + Preload ================= --}}
  <link rel="preload" as="font" type="font/woff2" href="{{ asset('assets/fonts/Almarai-Regular.woff2') }}" crossorigin>
  <link rel="preload" as="font" type="font/woff2" href="{{ asset('assets/fonts/Almarai-ExtraBold.woff2') }}" crossorigin>

  <style>
    /* @font-face محلي — تأكد من المسارات */
    @font-face {
      font-family: 'Almarai';
      font-style: normal;
      font-weight: 400;
      font-display: swap;
      src: url('{{ asset('assets/fonts/Almarai-Regular.woff2') }}') format('woff2');
    }
    @font-face {
      font-family: 'Almarai';
      font-style: normal;
      font-weight: 800;
      font-display: swap;
      src: url('{{ asset('assets/fonts/Almarai-ExtraBold.woff2') }}') format('woff2');
    }
    /* تطبيق الخط مبكرًا لتجنّب FOUT */
    html, body { font-family: 'Almarai', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif; }
  </style>

  {{-- ================= home.css: Inline إن وُجد، وإلا تحميل غير حاجب ================= --}}
  @php
    $homeCssPath = public_path('assets/css/home.css');
  @endphp
  @if (is_file($homeCssPath))
    <style>{!! file_get_contents($homeCssPath) !!}</style>
  @else
    <link rel="preload" as="style" href="{{ asset('assets/css/home.css?v=1.1.7') }}" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset('assets/css/home.css?v=1.1.7') }}"></noscript>
  @endif

  {{-- ================= Font Awesome: محلي إن وُجد، وإلا CDN غير حاجب ================= --}}
  @php
    $faCssPath = public_path('assets/css/all.min.css');
  @endphp
  @if (is_file($faCssPath))
    {{-- محلي + غير حاجب --}}
    <link rel="stylesheet" href="{{ asset('assets/css/all.min.css') }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ asset('assets/css/all.min.css') }}"></noscript>

    {{-- (اختياري) Preload لملف الويب-فونت الأكثر استخدامًا --}}
    {{-- <link rel="preload" as="font" type="font/woff2" href="{{ asset('assets/css/webfonts/fa-solid-900.woff2') }}" crossorigin> --}}
  @else
    {{-- CDN غير حاجب (fallback) --}}
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
  {{-- خلفيات فاتحة هادئة بدون حركة --}}
  <div class="light-bg-base" aria-hidden="true"></div>

  @include('partials.header')

  <main class="content-area" id="main-content">
    @yield('content')
  </main>

  {{-- نظام العرض المنبثق للصور (Lightbox Modal) --}}
  <div id="imageLightbox" class="lightbox-modal" aria-hidden="true">
    <div class="lightbox-overlay" data-close-lightbox></div>
    <div class="lightbox-content">
      <button class="lightbox-close" data-close-lightbox aria-label="إغلاق">&times;</button>
      <iframe id="lightboxFrame" src="" frameborder="0" allowfullscreen style="width:100%; height:85vh; border-radius:12px; background:#fff;"></iframe>
    </div>
  </div>

  @include('partials.footer')

  {{-- ================= Scripts ================= --}}
  {{-- سكربتات الموقع: اجعلها دائماً غير حاجبة --}}
  <script src="{{ asset('assets/js/home.js?v=1.0.1') }}" defer></script>

  {{-- سكربت إعلانات Google الكسول: في آخر الـ body (inline) --}}
  <script>
  (function(){
    const onIdle = window.requestIdleCallback || function(cb){ setTimeout(cb, 1200); };
    function loadAdsScript(cb){
      if (window.adsbygoogle) { cb&&cb(); return; }
      const s = document.createElement('script');
      s.src = "https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3572625518315562";
      s.async = true; s.crossOrigin = "anonymous";
      s.onload = cb; document.head.appendChild(s);
    }
    function initAd(el){
      try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch(e){}
      el.dataset.inited = "1";
    }
    function observe(){
      const els = Array.prototype.slice.call(document.querySelectorAll('ins.adsbygoogle[data-lazy="1"]:not([data-inited])'));
      if (!els.length) return;
      const io = new IntersectionObserver((entries)=>{
        entries.forEach(en=>{
          if (!en.isIntersecting) return;
          const el = en.target;
          io.unobserve(el);
          loadAdsScript(()=> initAd(el));
        });
      }, { rootMargin: "300px 0px" });
      els.forEach(el=> io.observe(el));
    }
    onIdle(observe);
  })();
  </script>

  {{-- مساحة لسكربتات الصفحات الفرعية --}}
  @stack('scripts')
</body>
</html>
