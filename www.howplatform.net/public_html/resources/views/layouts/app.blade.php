<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>

    @php
        $siteName   = 'منصة كيف التعليمية | HOWPlatform';
        $title      = trim(($seo['title'] ?? '' )) ?: $siteName;
        $desc       = trim(($seo['description'] ?? '' )) ?: 'منصة تعليمية عربية لدعم إعداد المعلمين وتقديم الموارد التعليمية.';
        $canonical  = ($seo['canonical'] ?? url()->current());
        // اجعل الصورة مطلقة دومًا (مهم للسوشيال/جوجل)
        $ogImageRel = $seo['og_image'] ?? asset('assets/images/logo.png'); // ضع og-image.jpg في public/assets/images
        $ogImageAbs = str_starts_with($ogImageRel, 'http') ? $ogImageRel : url($ogImageRel);
        // robots (لصفحات auth مثلًا: noindex,follow)
        $robots     = $seo['robots'] ?? 'index,follow';
    @endphp

    <title>{{ $title }}</title>
    <meta name="description" content="{{ $desc }}"/>
    <meta name="robots" content="{{ $robots }}"/>
    <link rel="canonical" href="{{ $canonical }}"/>

    {{-- Hreflang (لو عندك لغات إضافية أضفها هنا) --}}
    <link rel="alternate" href="{{ $canonical }}" hreflang="ar-jo"/>
    <link rel="alternate" href="{{ $canonical }}" hreflang="x-default"/>

    {{-- Open Graph --}}
    <meta property="og:type" content="website"/>
    <meta property="og:locale" content="ar_JO"/>
    <meta property="og:site_name" content="منصة كيف التعليمية"/>
    <meta property="og:title" content="{{ $title }}"/>
    <meta property="og:description" content="{{ $desc }}"/>
    <meta property="og:url" content="{{ $canonical }}"/>
    <meta property="og:image" content="{{ $ogImageAbs }}"/>
    <meta property="og:image:width" content="1200"/>
    <meta property="og:image:height" content="630"/>

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image"/>
    <meta name="twitter:title" content="{{ $title }}"/>
    <meta name="twitter:description" content="{{ $desc }}"/>
    <meta name="twitter:image" content="{{ $ogImageAbs }}"/>

    {{-- Favicon / App Icons --}}
    <link rel="icon" href="{{ asset('assets/images/icon.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('assets/images/icon.png') }}">

    {{-- JSON-LD --}}
    @include('partials.seo_jsonld')

    {{-- Fonts & Icons --}}
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

    {{-- CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/css/home.css') }}?v=1.1.9"/>

    @stack('head')
</head>
<body>
    <canvas id="fxbg"></canvas>
    <div class="pulse-gradient" aria-hidden="true"></div>

    @include('partials.header')

    <main class="content-area">
        @yield('content')
    </main>

    @include('partials.footer')

    <script src="{{ asset('assets/js/main.js') }}?v=1.1.8" defer></script>
    @stack('scripts')
</body>
</html>
