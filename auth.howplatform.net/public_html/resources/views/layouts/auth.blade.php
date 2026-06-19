<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | كيف التعليمية</title>

    {{-- ربط ملفات الهوية البصرية --}}
    <link rel="stylesheet" href="{{ asset('css/site.css?v=1.0.1') }}">

</head>
<body style="background:var(--primary-50); font-family:'Almarai',sans-serif;">

    <main style="min-height:100vh; display:flex; align-items:center; justify-content:center;">
        @yield('content')
    </main>

</body>
</html>
