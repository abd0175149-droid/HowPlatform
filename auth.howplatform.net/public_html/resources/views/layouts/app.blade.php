{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', config('app.name','HowPlatform'))</title>
  <link rel="icon" href="{{ asset('images/icon.png') }}">
  
  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <!-- CSS Libs -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css" rel="stylesheet">
  
  <!-- Design Tokens -->
  <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">

  <style>
    /* =================== Base =================== */
    *, *::before, *::after { box-sizing: border-box; }

    body {
      font-family: 'Tajawal', 'Almarai', system-ui, sans-serif;
      background: var(--neutral-100);
      color: var(--text);
      text-align: right;
      line-height: 1.7;
      margin: 0;
      padding: 0;
    }

    a {
      color: var(--link);
      text-decoration: none;
      transition: var(--transition);
    }
    a:hover { color: var(--link-hover); }

    /* =================== Container =================== */
    .container-custom {
      max-width: 1400px;
      margin: 0 auto;
      padding: 0 1.5rem;
    }
    @media (min-width: 1400px) {
      .container-custom { max-width: 1600px; }
    }

    /* =================== Navbar Shell =================== */
    .site-header {
      position: sticky;
      top: 0;
      z-index: 1000;
      background: white;
      border-bottom: 1.5px solid var(--neutral-200);
      box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
    }

    .site-nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1.5rem;
      padding: .5rem 0;
    }

    /* =================== Brand =================== */
    .nav-brand {
      display: flex;
      align-items: center;
      gap: .65rem;
      text-decoration: none;
      flex-shrink: 0;
    }

    .nav-brand-logo {
      height: 40px;
      width: 40px;
      object-fit: contain;
    }

    .nav-brand-text {
      font-size: 1.15rem;
      font-weight: 800;
      color: var(--text-strong);
      letter-spacing: -.02em;
      transition: color .2s ease;
    }

    .nav-brand:hover .nav-brand-text { color: var(--primary-600); }

    /* =================== Nav Links (desktop) =================== */
    .nav-links {
      display: flex;
      align-items: center;
      gap: .25rem;
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .nav-links .nav-item { position: relative; }

    .nav-links .nav-link {
      display: flex;
      align-items: center;
      gap: .45rem;
      padding: .55rem .95rem;
      border-radius: 10px;
      font-size: .9rem;
      font-weight: 700;
      color: var(--text);
      text-decoration: none;
      transition: all .22s ease;
      white-space: nowrap;
      position: relative;
    }

    .nav-links .nav-link::after {
      content: '';
      position: absolute;
      bottom: 4px;
      right: 50%;
      transform: translateX(50%);
      width: 0;
      height: 2.5px;
      background: var(--primary-500);
      border-radius: 2px;
      transition: width .25s ease;
    }

    .nav-links .nav-link:hover {
      color: var(--primary-600);
      background: var(--primary-50);
    }

    .nav-links .nav-link:hover::after { width: 55%; }

    .nav-links .nav-link.active {
      color: var(--primary-600);
      background: var(--primary-50);
    }

    .nav-links .nav-link.active::after { width: 55%; }

    .nav-links .nav-link i {
      font-size: .8rem;
      opacity: .7;
    }

    /* =================== Nav Actions (right side) =================== */
    .nav-actions {
      display: flex;
      align-items: center;
      gap: .6rem;
      flex-shrink: 0;
    }

    /* =================== User Dropdown =================== */
    .user-dropdown {
      position: relative;
    }

    .user-dropdown-toggle {
      display: flex;
      align-items: center;
      gap: .6rem;
      padding: .45rem .85rem .45rem .6rem;
      border-radius: 999px;
      border: 1.5px solid var(--neutral-200);
      background: white;
      cursor: pointer;
      transition: all .22s ease;
      outline: none;
      text-decoration: none;
      font-family: inherit;
    }

    .user-dropdown-toggle:hover {
      border-color: var(--primary-300);
      background: var(--primary-50);
      box-shadow: 0 4px 12px rgba(33, 158, 188, .12);
    }

    .user-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--primary-100);
      flex-shrink: 0;
    }

    .user-name {
      font-size: .85rem;
      font-weight: 700;
      color: var(--text-strong);
      max-width: 120px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .user-caret {
      font-size: .7rem;
      color: var(--text-muted);
      transition: transform .22s ease;
    }

    .user-dropdown.open .user-caret { transform: rotate(180deg); }

    /* Dropdown Menu */
    .user-dropdown-menu {
      position: absolute;
      top: calc(100% + .6rem);
      left: 0;
      min-width: 200px;
      background: white;
      border: 1.5px solid var(--neutral-200);
      border-radius: 16px;
      box-shadow: 0 12px 40px rgba(0, 0, 0, .12);
      padding: .5rem;
      display: none;
      flex-direction: column;
      gap: .25rem;
      z-index: 999;
      animation: dropDown .2s ease;
    }

    @keyframes dropDown {
      from { opacity: 0; transform: translateY(-8px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .user-dropdown.open .user-dropdown-menu { display: flex; }

    .dropdown-item {
      display: flex;
      align-items: center;
      gap: .65rem;
      padding: .65rem .9rem;
      border-radius: 10px;
      font-size: .875rem;
      font-weight: 700;
      color: var(--text);
      text-decoration: none;
      transition: all .18s ease;
      cursor: pointer;
      border: none;
      background: none;
      width: 100%;
      text-align: right;
      font-family: inherit;
    }

    .dropdown-item i {
      width: 18px;
      text-align: center;
      color: var(--primary-500);
      font-size: .9rem;
    }

    .dropdown-item:hover {
      background: var(--primary-50);
      color: var(--primary-700);
    }

    .dropdown-item:hover i { color: var(--primary-600); }

    .dropdown-item.danger { color: var(--error-600); }
    .dropdown-item.danger i { color: var(--error-500); }
    .dropdown-item.danger:hover { background: var(--error-soft); color: var(--error-700); }

    .dropdown-divider {
      height: 1px;
      background: var(--neutral-100);
      margin: .3rem 0;
      border: none;
    }

    /* =================== Auth Buttons =================== */
    .btn-nav-ghost {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: .55rem 1.1rem;
      border-radius: 10px;
      font-size: .875rem;
      font-weight: 700;
      color: var(--text);
      border: 1.5px solid var(--neutral-200);
      background: white;
      cursor: pointer;
      transition: all .22s ease;
      text-decoration: none;
      font-family: inherit;
      white-space: nowrap;
    }

    .btn-nav-ghost:hover {
      background: var(--neutral-50);
      border-color: var(--neutral-300);
      color: var(--text-strong);
    }

    .btn-nav-primary {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: .55rem 1.25rem;
      border-radius: 10px;
      font-size: .875rem;
      font-weight: 700;
      color: white;
      border: 1.5px solid transparent;
      background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
      cursor: pointer;
      transition: all .22s ease;
      text-decoration: none;
      font-family: inherit;
      white-space: nowrap;
      box-shadow: 0 4px 12px rgba(33, 158, 188, .25);
    }

    .btn-nav-primary:hover {
      background: linear-gradient(135deg, var(--primary-600), var(--primary-700));
      box-shadow: 0 6px 18px rgba(33, 158, 188, .35);
      transform: translateY(-1px);
      color: white;
    }

    /* =================== Mobile Hamburger =================== */
    .nav-hamburger {
      display: none;
      flex-direction: column;
      justify-content: center;
      gap: 5px;
      width: 40px;
      height: 40px;
      border-radius: 10px;
      border: 1.5px solid var(--neutral-200);
      background: white;
      cursor: pointer;
      padding: .5rem;
      transition: all .22s ease;
      flex-shrink: 0;
    }

    .nav-hamburger:hover {
      background: var(--primary-50);
      border-color: var(--primary-200);
    }

    .nav-hamburger span {
      display: block;
      height: 2px;
      background: var(--text);
      border-radius: 2px;
      transition: all .25s ease;
    }

    .nav-hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .nav-hamburger.open span:nth-child(2) { opacity: 0; }
    .nav-hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    /* =================== Mobile Drawer =================== */
    .nav-drawer {
      display: none;
      flex-direction: column;
      gap: .3rem;
      padding: .875rem 1rem 1rem;
      border-top: 1.5px solid var(--neutral-100);
      background: white;
    }

    .nav-drawer.open { display: flex; }

    .drawer-link {
      display: flex;
      align-items: center;
      gap: .65rem;
      padding: .75rem 1rem;
      border-radius: 12px;
      font-size: .9rem;
      font-weight: 700;
      color: var(--text);
      text-decoration: none;
      transition: all .2s ease;
    }

    .drawer-link i {
      width: 20px;
      text-align: center;
      color: var(--primary-500);
      font-size: .9rem;
    }

    .drawer-link:hover,
    .drawer-link.active {
      background: var(--primary-50);
      color: var(--primary-700);
    }

    .drawer-link:hover i,
    .drawer-link.active i { color: var(--primary-600); }

    .drawer-divider {
      height: 1px;
      background: var(--neutral-100);
      margin: .4rem 0;
      border: none;
    }

    .drawer-link.danger { color: var(--error-600); }
    .drawer-link.danger i { color: var(--error-500); }
    .drawer-link.danger:hover { background: var(--error-soft); }

    /* =================== Main Content =================== */
    main {
      padding: 2rem 0;
      min-height: calc(100vh - 68px);
    }

    /* =================== Responsive =================== */
    @media (max-width: 991px) {
      .nav-links { display: none; }
      .user-dropdown { display: none; }
      .nav-hamburger { display: flex; }
      .btn-nav-ghost, .btn-nav-primary { display: none; }
    }

    @media (min-width: 992px) {
      .nav-drawer { display: none !important; }
    }

    @media (max-width: 576px) {
      .container-custom { padding: 0 1rem; }
      .nav-brand-text { display: none; }
      main { padding: 1.25rem 0; }
    }

    /* =================== Dark Mode =================== */
    html[data-theme="dark"] .site-header {
      background: var(--surface-0);
      border-bottom-color: var(--border-color);
    }

    html[data-theme="dark"] .nav-links .nav-link {
      color: var(--text);
    }

    html[data-theme="dark"] .nav-links .nav-link:hover,
    html[data-theme="dark"] .nav-links .nav-link.active {
      background: var(--primary-100);
      color: var(--primary-400);
    }

    html[data-theme="dark"] .user-dropdown-toggle {
      background: var(--surface-1);
      border-color: var(--border-color);
    }

    html[data-theme="dark"] .user-dropdown-toggle:hover {
      background: var(--primary-100);
      border-color: var(--primary-700);
    }

    html[data-theme="dark"] .user-dropdown-menu {
      background: var(--surface-1);
      border-color: var(--border-color);
    }

    html[data-theme="dark"] .dropdown-item:hover {
      background: var(--primary-100);
    }

    html[data-theme="dark"] .nav-hamburger {
      background: var(--surface-1);
      border-color: var(--border-color);
    }

    html[data-theme="dark"] .nav-hamburger span { background: var(--text); }

    html[data-theme="dark"] .nav-drawer {
      background: var(--surface-0);
      border-top-color: var(--border-color);
    }

    html[data-theme="dark"] .drawer-link:hover,
    html[data-theme="dark"] .drawer-link.active {
      background: var(--primary-100);
      color: var(--primary-400);
    }

    html[data-theme="dark"] .btn-nav-ghost {
      background: var(--surface-1);
      border-color: var(--border-color);
      color: var(--text);
    }

    html[data-theme="dark"] .btn-nav-ghost:hover {
      background: var(--surface-2);
      color: var(--text-strong);
    }

    html[data-theme="dark"] .nav-brand-text { color: var(--text-strong); }
  </style>

  @stack('styles')
</head>
<body>

<style>
/* مُشغّل المنصّات — بجانب الصورة الشخصية في الناف بار */
.apps-menu{position:relative;}
.apps-btn{list-style:none;cursor:pointer;width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#eef3f6;color:#013046;border:1.5px solid #dfe7ec;transition:background .15s;}
.apps-btn::-webkit-details-marker{display:none;}
.apps-btn:hover,.apps-menu[open] .apps-btn{background:#dbe7ec;}
.apps-panel{position:absolute;top:calc(100% + 10px);inset-inline-end:0;background:#fff;border-radius:16px;box-shadow:0 16px 40px rgba(1,48,70,.18);padding:.75rem;min-width:280px;z-index:1300;}
.apps-panel .apps-title{color:#7a8a93;font-weight:700;font-size:.72rem;letter-spacing:.5px;padding:.15rem .35rem .55rem;}
.apps-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.4rem;}
.apps-tile{display:flex;flex-direction:column;align-items:center;gap:.45rem;padding:.85rem .4rem;border-radius:12px;color:#013046;text-align:center;border:1.5px solid transparent;text-decoration:none;transition:all .16s;}
.apps-tile:hover{background:#eaf3f6;border-color:#cfe6ee;transform:translateY(-2px);}
.apps-tile .ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;color:#fff;background:linear-gradient(135deg,#219ebc,#156683);}
.apps-tile span:last-child{font-size:.74rem;font-weight:700;line-height:1.2;}
@media (max-width:400px){.apps-panel{min-width:240px;}}
</style>
<script>
document.addEventListener('click',function(e){
  document.querySelectorAll('details.apps-menu[open]').forEach(function(d){
    if(!d.contains(e.target)) d.removeAttribute('open');
  });
});
</script>

<div class="site-header">
  <div class="container-custom">
    <nav class="site-nav">

      {{-- ===== Brand ===== --}}
      <a href="{{ url('/') }}" class="nav-brand">
        <img src="{{ asset('images/logo.png') }}" alt="HOW" class="nav-brand-logo">
        <span class="nav-brand-text">HOW Platform</span>
      </a>

      {{-- روابط المنصّات نُقلت إلى مُشغّل المنصّات بجانب الصورة الشخصية (ناف بار نظيف) --}}

      {{-- ===== Desktop Actions ===== --}}
      <div class="nav-actions">
        {{-- مُشغّل المنصّات (بجانب الصورة الشخصية) --}}
        <details class="apps-menu">
          <summary class="apps-btn" title="المنصّات"><i class="fa fa-table-cells-large"></i></summary>
          <div class="apps-panel">
            <div class="apps-title">منصّات HOW</div>
            <div class="apps-grid">
              <a href="https://diploma.howplatform.net/auth/redirect" class="apps-tile" target="_blank" rel="noopener"><span class="ic"><i class="fa fa-graduation-cap"></i></span><span>الدبلوم</span></a>
              <a href="https://library.howplatform.net/" class="apps-tile" target="_blank" rel="noopener"><span class="ic"><i class="fa fa-book-open"></i></span><span>المكتبة</span></a>
              <a href="https://tools.howplatform.net/" class="apps-tile" target="_blank" rel="noopener"><span class="ic"><i class="fa fa-wand-magic-sparkles"></i></span><span>الأدوات</span></a>
              <a href="https://www.howplatform.net/" class="apps-tile" target="_blank" rel="noopener"><span class="ic"><i class="fa fa-house"></i></span><span>الرئيسية</span></a>
            </div>
          </div>
        </details>
        @auth
          @php
            $authUser   = auth()->user();
            $navAvatar  = $authUser->avatar
              ? (filter_var($authUser->avatar, FILTER_VALIDATE_URL) ? $authUser->avatar : asset($authUser->avatar))
              : 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($authUser->email))).'?s=64&d=identicon';
          @endphp

          <div class="user-dropdown" id="userDropdown">
            <button class="user-dropdown-toggle" id="userDropdownToggle" type="button" aria-expanded="false">
              <img src="{{ $navAvatar }}" alt="avatar" class="user-avatar">
              <span class="user-name">{{ str()->limit($authUser->name, 16) }}</span>
              <i class="fa fa-chevron-down user-caret"></i>
            </button>

            <div class="user-dropdown-menu" id="userDropdownMenu" role="menu">
              <a href="{{ route('profile.index') }}" class="dropdown-item">
                <i class="fa fa-user-circle"></i> الملف الشخصي
              </a>
              <a href="{{ route('profile.index') }}?active=security" class="dropdown-item">
                <i class="fa fa-shield-halved"></i> الأمان وكلمة المرور
              </a>
              <hr class="dropdown-divider">
              <form method="POST" action="{{ route('logout') }}" style="margin:0">
                @csrf
                <button type="submit" class="dropdown-item danger">
                  <i class="fa fa-right-from-bracket"></i> تسجيل الخروج
                </button>
              </form>
            </div>
          </div>
        @else
          <a href="{{ route('login') }}" class="btn-nav-ghost">
            <i class="fa fa-sign-in-alt"></i> دخول
          </a>
          @if(Route::has('register'))
            <a href="{{ route('register') }}" class="btn-nav-primary">
              <i class="fa fa-user-plus"></i> تسجيل
            </a>
          @endif
        @endauth
      </div>

      {{-- ===== Hamburger (mobile) ===== --}}
      <button class="nav-hamburger" id="navHamburger" aria-label="القائمة" type="button">
        <span></span><span></span><span></span>
      </button>
    </nav>

    {{-- ===== Mobile Drawer ===== --}}
    <nav class="nav-drawer" id="navDrawer">
      <a href="https://www.howplatform.net/" class="drawer-link">
        <i class="fa fa-house"></i> الرئيسية
      </a>
      <a href="https://diploma.howplatform.net/auth/redirect" class="drawer-link">
        <i class="fa fa-graduation-cap"></i> منصة الدبلوم
      </a>
      <a href="https://library.howplatform.net/" class="drawer-link">
        <i class="fa fa-book-open"></i> المكتبة الرقمية
      </a>
      <a href="https://tools.howplatform.net/" class="drawer-link">
        <i class="fa fa-wand-magic-sparkles"></i> الأدوات التعليمية
      </a>

      @auth
        <a href="{{ route('profile.index') }}" class="drawer-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
          <i class="fa fa-user-circle"></i> الملف الشخصي
        </a>
        <a href="{{ route('profile.index') }}?active=security" class="drawer-link">
          <i class="fa fa-shield-halved"></i> الأمان وكلمة المرور
        </a>
        <hr class="drawer-divider">
        <form method="POST" action="{{ route('logout') }}" style="margin:0">
          @csrf
          <button type="submit" class="drawer-link danger" style="width:100%; cursor:pointer; border:none; background:none; text-align:right; font-family:inherit;">
            <i class="fa fa-right-from-bracket"></i> تسجيل الخروج
          </button>
        </form>
      @else
        <hr class="drawer-divider">
        <a href="{{ route('login') }}" class="drawer-link">
          <i class="fa fa-sign-in-alt"></i> دخول
        </a>
        @if(Route::has('register'))
          <a href="{{ route('register') }}" class="drawer-link">
            <i class="fa fa-user-plus"></i> تسجيل حساب جديد
          </a>
        @endif
      @endauth
    </nav>
  </div>
</div>

{{-- Navbar JS --}}
<script>
  (function () {
    const hamburger = document.getElementById('navHamburger');
    const drawer    = document.getElementById('navDrawer');
    const dropdown  = document.getElementById('userDropdown');
    const toggle    = document.getElementById('userDropdownToggle');

    // Hamburger
    if (hamburger && drawer) {
      hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('open');
        drawer.classList.toggle('open');
      });
    }

    // User Dropdown
    if (toggle && dropdown) {
      toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('open');
        toggle.setAttribute('aria-expanded', dropdown.classList.contains('open'));
      });

      document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target)) {
          dropdown.classList.remove('open');
          toggle.setAttribute('aria-expanded', 'false');
        }
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          dropdown.classList.remove('open');
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
    }
  })();
</script>

<main>
  <div class="container-custom">
    @yield('content')
  </div>
</main>

<!-- JS Libs -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>

@stack('scripts')
</body>
</html>
