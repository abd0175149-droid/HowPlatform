<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'لوحة أدوات HowPlatform')</title>

  <!-- ألوان وهوية howPlatform -->
  <link rel="stylesheet" href="{{ asset('assets/css/tools.css') }}">
  
  <!-- خط Almarai المحسن -->
  <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">
  
  <!-- أيقونات -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <!-- يمكن لاحقًا إضافة مكتبات مثل SweetAlert أو Chart.js -->
  @stack('styles')
</head>
<body>
  <header class="tools-header">
    <div class="tools-container">
      <div class="brand">
        <img src="{{ asset('assets/images/Logo_how_w.svg') }}" alt="كيف التعليمية" class="logo">
        <h1>منصة متابعة المعلمين</h1>
      </div>
      <button class="mobile-menu-toggle" aria-label="فتح القائمة">
        <span></span>
        <span></span>
        <span></span>
      </button>
      <nav class="tools-nav">
        <a href="{{ route('performance.index') }}" class="{{ request()->is('performance') ? 'active' : '' }}">
          <i class="fas fa-database"></i>
          البيانات الأساسية
        </a>
        <a href="{{ url('/performance/tracking') }}">
          <i class="fas fa-calendar-week"></i>
          متابعة الأسبوع
        </a>
        <a href="{{ url('/performance/reports') }}">
          <i class="fas fa-chart-bar"></i>
          التقارير
        </a>
        <a href="{{ url('/performance/groups') }}">
          <i class="fas fa-users"></i>
          المجموعات
        </a>
      </nav>
    </div>
  </header>

  <main class="tools-main">
    @yield('content')
  </main>

  <footer class="tools-footer">
    <div class="tools-container">
      <p>© {{ date('Y') }} HOW Platform — جميع الحقوق محفوظة.</p>
      <div class="footer-links">
        <a href="#"><i class="fas fa-question-circle"></i> المساعدة</a>
        <a href="#"><i class="fas fa-envelope"></i> اتصل بنا</a>
      </div>
    </div>
  </footer>

  @stack('scripts')
</body>
</html>