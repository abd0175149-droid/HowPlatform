<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'حسابي | Plans')</title>
  <link rel="icon" href="{{ asset('assets/images/icon.ico') }}" sizes="32x32">
  <link rel="icon" type="image/png" href="{{ asset('assets/images/icon.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('assets/images/icon.png') }}">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/css/tokens.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/profile.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/account.css') }}">
  @stack('styles')
</head>
<body>
  @php
    $au = session('auth_user') ?? [];
    $r  = request()->route()?->getName();
    $uname = $au['name'] ?? 'مستخدم';
    $uinit = mb_substr($uname, 0, 1);
    $uavatar = $au['avatar'] ?? null;

    // شرائح التنقّل بين صفحات الحساب
    $nav = [
      ['user.dashboard',     route('user.dashboard'),     'fa-gauge-high',  'لوحتي'],
      ['user.wallet',        route('user.wallet'),        'fa-wallet',      'محفظتي'],
      ['user.plans',         route('user.plans'),         'fa-crown',       'الخطط والأسعار'],
      ['user.subscriptions', route('user.subscriptions'), 'fa-id-card',     'اشتراكاتي'],
      ['user.profile',       route('user.profile'),       'fa-id-badge',    'ملفي'],
    ];

    // شبكة المنصّات (app launcher)
    $platforms = [
      ['منصة الدبلوم',  'https://diploma.howplatform.net',        'fa-graduation-cap'],
      ['المكتبة',       'https://library.howplatform.net',        'fa-book-open'],
      ['الأدوات',       'https://tools.howplatform.net',          'fa-screwdriver-wrench'],
      ['حسابي',         'https://auth.howplatform.net/profile',   'fa-user'],
      ['الموقع الرئيسي','https://www.howplatform.net',            'fa-globe'],
    ];

    $accEnt = $accEnt ?? ['has_plan' => false];
    $accBalance = $accBalance ?? 0;
  @endphp

  {{-- Navbar --}}
  <nav class="acc-navbar">
    <img src="{{ asset('assets/images/Logo_how_gr.svg') }}" alt="HOW" class="acc-logo">
    <div class="acc-brand d-none d-sm-block">منصّة الاشتراكات والنقاط<small>Plans — حسابي</small></div>

    <div class="acc-nav-actions">
      {{-- App launcher --}}
      <div class="dropdown">
        <button class="acc-icon-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false" title="المنصّات">
          <i class="fas fa-table-cells-large"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-start app-launcher-menu">
          <div class="app-launcher-title">منصّات HOW</div>
          <div class="app-launcher-grid">
            @foreach($platforms as [$plabel, $phref, $picon])
              <a href="{{ $phref }}" class="app-tile" target="_blank" rel="noopener">
                <span class="ic"><i class="fas {{ $picon }}"></i></span>
                <span class="lbl">{{ $plabel }}</span>
              </a>
            @endforeach
          </div>
        </div>
      </div>

      {{-- User menu --}}
      <div class="dropdown">
        <button class="acc-user-chip" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="nm d-none d-sm-inline">{{ $uname }}</span>
          @if($uavatar)
            <img src="{{ $uavatar }}" alt="" class="av" style="object-fit:cover;">
          @else
            <span class="av">{{ $uinit }}</span>
          @endif
        </button>
        <ul class="dropdown-menu dropdown-menu-start shadow border-0" style="border-radius:14px; min-width:220px;">
          <li class="px-3 py-2">
            <div class="fw-bold" style="color:var(--text-strong)">{{ $uname }}</div>
            <div class="text-muted small">{{ $au['email'] ?? '' }}</div>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="{{ route('user.profile') }}"><i class="fas fa-id-badge me-1"></i> ملفي</a></li>
          <li><a class="dropdown-item" href="https://auth.howplatform.net/profile" target="_blank" rel="noopener"><i class="fas fa-gear me-1"></i> إعدادات الحساب</a></li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <form method="post" action="{{ route('logout') }}" class="px-2">@csrf
              <button class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-right-from-bracket"></i> تسجيل الخروج</button>
            </form>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="profile-wrapper">
    <div class="profile-main-container">

      {{-- Banner --}}
      <div class="profile-banner">
        <div class="profile-banner-content">
          <div class="profile-avatar-container">
            @if($uavatar)
              <img src="{{ $uavatar }}" alt="Avatar" class="profile-avatar-main">
            @else
              <div class="profile-avatar-main d-flex align-items-center justify-content-center"
                   style="background:linear-gradient(135deg,var(--primary-600),var(--primary-900)); color:#fff; font-size:2.6rem; font-weight:800;">
                {{ $uinit }}
              </div>
            @endif
          </div>
          <div class="profile-info-main">
            <h1 class="profile-name">{{ $uname }}</h1>
            <div class="profile-email">{{ $au['email'] ?? '' }}</div>
            <div class="profile-status-badges">
              <span class="banner-chip">
                <i class="fas fa-coins"></i>
                <span>{{ number_format($accBalance) }}</span>
                <span class="lbl">نقطة</span>
              </span>
              @if(!empty($accEnt['has_plan']))
                <span class="banner-chip">
                  <i class="fas fa-crown"></i>
                  <span>{{ $accEnt['plan']['name'] ?? '' }}</span>
                  <span class="lbl">خطتك</span>
                </span>
              @else
                <a href="{{ route('user.plans') }}" class="status-badge">
                  <i class="fas fa-arrow-up-right-dots"></i>
                  <span>لا يوجد اشتراك — ترقية</span>
                </a>
              @endif
            </div>
          </div>
        </div>
      </div>

      {{-- Alerts --}}
      @if(session('ok'))
        <div class="profile-alert profile-alert-success auto-dismiss"><i class="fa fa-check-circle profile-alert-icon"></i><div>{{ session('ok') }}</div></div>
      @endif
      @if(session('err'))
        <div class="profile-alert profile-alert-danger auto-dismiss"><i class="fa fa-exclamation-circle profile-alert-icon"></i><div>{{ session('err') }}</div></div>
      @endif
      @if($errors->any())
        <div class="profile-alert profile-alert-danger"><i class="fa fa-exclamation-circle profile-alert-icon"></i><div><ul class="mb-0" style="padding-inline-start:1.1rem;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
      @endif

      {{-- Chip navigation --}}
      <div class="profile-sections-nav">
        @foreach($nav as [$name,$href,$icon,$label])
          <a href="{{ $href }}" class="section-chip {{ $r === $name ? 'active' : '' }}">
            <span class="chip-icon"><i class="fas {{ $icon }}"></i></span>
            <span class="chip-text">{{ $label }}</span>
          </a>
        @endforeach
      </div>

      {{-- Content --}}
      <main class="profile-content-area">
        @yield('content')
      </main>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    setTimeout(function(){
      document.querySelectorAll('.auto-dismiss').forEach(function(el){
        el.style.transition='opacity .5s ease'; el.style.opacity='0';
        setTimeout(function(){ el.remove(); }, 500);
      });
    }, 8000);

    // نسخ نص إلى الحافظة مع تأكيد بصري على الزر
    function accCopy(text, btn){
      var done = function(){
        if(!btn) return;
        var o = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> تم النسخ';
        setTimeout(function(){ btn.innerHTML = o; }, 1500);
      };
      if(navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(text).then(done).catch(function(){ accCopyFallback(text); done(); });
      } else { accCopyFallback(text); done(); }
    }
    function accCopyFallback(text){
      var ta = document.createElement('textarea');
      ta.value = text; ta.style.position='fixed'; ta.style.opacity='0';
      document.body.appendChild(ta); ta.select();
      try{ document.execCommand('copy'); }catch(e){}
      document.body.removeChild(ta);
    }
  </script>
  @stack('scripts')
</body>
</html>
