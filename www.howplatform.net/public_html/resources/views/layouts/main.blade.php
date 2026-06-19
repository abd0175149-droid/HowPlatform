<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  {{-- ═══════════════════════════════════════════════════════
       Anti-FOUC: يُضاف data-theme على <html> قبل رسم أي CSS
       الأولوية: localStorage → prefers-color-scheme → light
       ═══════════════════════════════════════════════════════ --}}
  <script>
    (function () {
      var KEY = 'how-theme';
      var saved;
      try { saved = localStorage.getItem(KEY); } catch (e) {}
      var theme = (saved === 'dark' || saved === 'light')
        ? saved
        : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', theme);
    })();
  </script>

  {{-- ═══════════════════════════════════════════════════════
       رقم الإصدار — غيّر هذا الرقم عند أي تعديل في CSS أو JS
       ═══════════════════════════════════════════════════════ --}}
  @php $v = '3.0.0'; @endphp

  {{-- ═══════════════════════════════════════════════════════
       Design Tokens — أول CSS يُحمَّل (الأولوية القصوى)
       ═══════════════════════════════════════════════════════ --}}
  <link rel="preload" as="style" href="{{ asset('assets/css/tokens.css?v='.$v) }}" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="{{ asset('assets/css/tokens.css?v='.$v) }}"></noscript>

  {{-- Bootstrap (بعد tokens مباشرة) --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

  {{-- ================= تهيئة SEO الموحدة ================= --}}
  @php
    $siteName = 'كيف التعليمية | HOWPlatform';
    $title = trim(($seo['title'] ?? '')) ?: $siteName;
    $desc = trim(($seo['description'] ?? '')) ?: 'منصة تعليمية عربية لدعم إعداد المعلمين وتقديم الموارد التعليمية المتنوعة.';
    $canonical = $seo['canonical'] ?? url()->current();

    // صورة OG مطلقة دائمًا
    $ogRel = $seo['og_image'] ?? asset('assets/images/logo.png');
    $ogImage = \Illuminate\Support\Str::startsWith($ogRel, ['http://', 'https://']) ? $ogRel : url($ogRel);

    // Robots لكل صفحة
    $robots = $seo['robots'] ?? 'index,follow';
  @endphp

  {{-- ================= SEO الأساسية ================= --}}
  <title>{{ $title }}</title>
  <meta name="description" content="{{ $desc }}">
  <meta name="robots" content="{{ $robots }}">
  <link rel="canonical" href="{{ $canonical }}">

  {{-- Adsense account declaration --}}
  <meta name="google-adsense-account" content="ca-pub-3572625518315562">

  {{-- ================= Hreflang ================= --}}
  <link rel="alternate" href="{{ $canonical }}" hreflang="ar-jo">
  <link rel="alternate" href="{{ $canonical }}" hreflang="x-default">

  {{-- ================= Open Graph / Twitter ================= --}}
  <meta property="og:type" content="website">
  <meta property="og:locale" content="ar_JO">
  <meta property="og:site_name" content="كيف التعليمية">
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

  {{-- ================= Fonts: Almarai + Google Fonts ================= --}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" as="style"
    href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Cairo:wght@700;900&display=swap"
    onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&family=Cairo:wght@700;900&display=swap">
  </noscript>

  {{-- Base font definition (immediate, no FOUT) --}}
  <style>
    html, body {
      font-family: 'Almarai', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }
    h1, h2, h3 {
      font-family: 'Cairo', 'Almarai', system-ui, sans-serif;
    }
  </style>

  {{-- ================= home.css: Inline إن وُجد، وإلا تحميل غير حاجب ================= --}}
  @php
    $homeCssPath = public_path('assets/css/home.css');
  @endphp
  @if (is_file($homeCssPath))
    <style>{!! file_get_contents($homeCssPath) !!}</style>
  @else
    <link rel="preload" as="style" href="{{ asset('assets/css/home.css?v='.$v) }}"
      onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
      <link rel="stylesheet" href="{{ asset('assets/css/home.css?v='.$v) }}">
    </noscript>
  @endif

  {{-- ================= Font Awesome ================= --}}
  @php
    $faCssPath = public_path('assets/css/all.min.css');
  @endphp
  @if (is_file($faCssPath))
    <link rel="stylesheet" href="{{ asset('assets/css/all.min.css') }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ asset('assets/css/all.min.css') }}"></noscript>
  @else
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
      media="print" onload="this.media='all'" crossorigin>
    <noscript>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin>
    </noscript>
  @endif

  {{-- DataFlow Analytics --}}
  <script src="https://analytics.grade.sbs/tracker/index.js" data-endpoint="https://api-analytics.grade.sbs/api/track"
    data-tracking-id="trk_kDUjfBNdd2Klfbon" data-batch-size="10" data-interval="5000" data-debug="false">
  </script>

  {{-- Adsense lazy --}}
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3572625518315562"
    crossorigin="anonymous"></script>

  {{-- Theme JS (defer — init يعمل بعد DOM) --}}
  <script src="{{ asset('assets/js/theme.js?v='.$v) }}" defer></script>

  {{-- Bootstrap JS --}}
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>

  {{-- مساحة لأنماط الصفحات الفرعية --}}
  @stack('styles')
</head>

<body>
  @php $now = \Carbon\Carbon::now('Asia/Amman'); @endphp
  @if ($now->between('2026-04-14', '2026-04-30 23:59:59'))
    @include('partials.launch_banner')
  @endif

  {{-- خلفية متحركة / نبض لوني --}}
  <canvas id="fxbg"></canvas>
  <div class="pulse-gradient" aria-hidden="true"></div>

  @include('partials.header')

  <main class="content-area" id="main-content">
    @if(session('error'))
      <div id="errorModal" class="modal-overlay" role="alertdialog" aria-modal="true" aria-labelledby="errorModalTitle">
        <div class="modal-box">
          <button class="modal-close" onclick="closeModal()" aria-label="إغلاق النافذة">×</button>
          <h3 id="errorModalTitle">تنبيه</h3>
          <p>{{ session('error') }}</p>
          <p class="countdown">سيتم إغلاق هذه النافذة خلال <span id="countdown">30</span> ثانية</p>
        </div>
      </div>

      <style>
        .modal-overlay {
          position: fixed; inset: 0;
          background: rgba(0, 0, 0, .5);
          display: flex; align-items: center; justify-content: center;
          z-index: 9999;
        }
        .modal-box {
          background: var(--modal-bg, #fff);
          padding: 20px; border-radius: var(--radius-lg, 18px);
          max-width: 400px; width: 90%;
          text-align: center;
          box-shadow: var(--shadow-lg);
          position: relative;
          animation: fadeInModal .3s var(--ease-default);
        }
        .modal-box h3 { margin-bottom: 10px; color: var(--modal-title, #b30000); }
        .modal-box p  { margin: 10px 0; color: var(--text-primary); line-height: var(--lh-relaxed, 1.8); }
        .modal-close {
          position: absolute; top: 10px; right: 10px;
          border: none; background: none;
          font-size: 28px; cursor: pointer;
          color: var(--text-muted);
          width: 40px; height: 40px; border-radius: 50%;
          display: flex; align-items: center; justify-content: center;
          transition: background var(--duration-base), transform var(--duration-base);
        }
        .modal-close:hover { background: rgba(0,0,0,.08); transform: scale(1.1); }
        .countdown { margin-top: 15px; font-weight: bold; color: var(--text-secondary); }
        @keyframes fadeInModal {
          from { opacity: 0; transform: scale(.92); }
          to   { opacity: 1; transform: scale(1); }
        }
      </style>

      <script>
        let remaining = 30;
        let timer = setInterval(() => {
          remaining--;
          document.getElementById("countdown").textContent = remaining;
          if (remaining <= 0) closeModal();
        }, 1000);
        function closeModal() {
          const modal = document.getElementById("errorModal");
          if (modal) { modal.style.display = "none"; clearInterval(timer); }
        }
      </script>
    @endif

    @yield('content')
  </main>

  @include('partials.footer')

  {{-- ================= Scripts ================= --}}
  <script src="{{ asset('assets/js/home.js?v='.$v) }}" defer></script>

  {{-- Adsense lazy observer --}}
  <script>
    (function () {
      const onIdle = window.requestIdleCallback || function (cb) { setTimeout(cb, 1200); };
      function loadAdsScript(cb) {
        if (window.adsbygoogle) { cb && cb(); return; }
        const s = document.createElement('script');
        s.src = "https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3572625518315562";
        s.async = true; s.crossOrigin = "anonymous";
        s.onload = cb; document.head.appendChild(s);
      }
      function initAd(el) {
        try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) {}
        el.dataset.inited = "1";
      }
      function observe() {
        const els = Array.prototype.slice.call(
          document.querySelectorAll('ins.adsbygoogle[data-lazy="1"]:not([data-inited])')
        );
        if (!els.length) return;
        const io = new IntersectionObserver((entries) => {
          entries.forEach(en => {
            if (!en.isIntersecting) return;
            const el = en.target; io.unobserve(el);
            loadAdsScript(() => initAd(el));
          });
        }, { rootMargin: "300px 0px" });
        els.forEach(el => io.observe(el));
      }
      onIdle(observe);
    })();
  </script>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>

  {{-- مساحة لسكربتات الصفحات الفرعية --}}
  @stack('scripts')
</body>

</html>