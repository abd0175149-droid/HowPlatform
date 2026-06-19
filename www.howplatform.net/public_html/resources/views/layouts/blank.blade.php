<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Sales Dashboard')</title>

  {{-- CSS الأساسية الخاصة بلوحتك (اختياري ربط أي ملفات عامة عندك) --}}
  {{-- <link rel="stylesheet" href="{{ asset('assets/css/site.css') }}"> --}}
  @stack('styles')
</head>
<body class="sales-dashboard">
  @yield('content')

  @stack('scripts')
</body>
</html>
