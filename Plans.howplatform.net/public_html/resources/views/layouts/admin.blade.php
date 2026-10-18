<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'لوحة إدارة Plans')</title>
  <link rel="icon" href="{{ asset('assets/images/icon.ico') }}" sizes="32x32">
  <link rel="icon" type="image/png" href="{{ asset('assets/images/icon.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('assets/images/icon.png') }}">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&display=swap" rel="stylesheet">
  <style>
    :root{
      --brand-deep:#013046; --brand:#116580; --brand-light:#0f5d77;
      --accent:#f99e1c; --accent-d:#e08b0c;
      --nav-h:62px; --side-w:250px;
    }
    *{font-family:'Almarai',system-ui,'Segoe UI',Tahoma,sans-serif;}
    body{background:#eef2f5; margin:0;}
    a{text-decoration:none;}

    /* Navbar */
    .app-navbar{position:fixed; top:0; right:0; left:0; height:var(--nav-h); background:#fff;
      box-shadow:0 1px 10px rgba(1,48,70,.08); display:flex; align-items:center; gap:.75rem;
      padding:0 1rem; z-index:1040;}
    .app-navbar .logo{height:34px;}
    .app-navbar .brand-sub{color:var(--brand-deep); font-weight:800; font-size:.95rem; line-height:1;}
    .app-navbar .brand-sub small{display:block; color:#7a8a93; font-weight:400; font-size:.7rem;}

    .icon-btn{position:relative; width:42px; height:42px; border-radius:12px; border:none; background:#f1f5f8;
      color:var(--brand-deep); font-size:1.05rem;}
    .icon-btn:hover{background:#e2ebef;}
    .icon-btn .dot{position:absolute; top:7px; left:9px; min-width:16px; height:16px; padding:0 4px; border-radius:8px;
      background:var(--accent); color:#fff; font-size:.62rem; font-weight:700; display:flex; align-items:center; justify-content:center;}

    .user-chip{display:flex; align-items:center; gap:.5rem; background:#f1f5f8; border:none; border-radius:30px; padding:.25rem .75rem .25rem .25rem;}
    .user-chip .av{width:32px; height:32px; border-radius:50%; background:var(--brand); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700;}
    .user-chip .nm{font-weight:700; font-size:.85rem; color:var(--brand-deep);}

    .notif-menu{width:330px; max-height:420px; overflow:auto; border:none; box-shadow:0 12px 40px rgba(1,48,70,.18); border-radius:14px;}
    .notif-menu .head{padding:.7rem 1rem; font-weight:800; color:var(--brand-deep); border-bottom:1px solid #eef2f5;}
    .notif-item{display:flex; gap:.6rem; padding:.6rem 1rem; border-bottom:1px solid #f3f6f8;}
    .notif-item:hover{background:#f6f9fb;}
    .notif-item.unread{background:#fff7ec;}
    .notif-item .ic{width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; flex:0 0 auto;}
    .notif-empty{padding:1.5rem 1rem; text-align:center; color:#9aa7ad;}

    /* Sidebar */
    .app-sidebar{position:fixed; top:var(--nav-h); right:0; width:var(--side-w); height:calc(100vh - var(--nav-h));
      background:var(--brand-deep); color:#bcd3dd; padding:1rem .8rem; overflow-y:auto; z-index:1035;}
    .app-sidebar .grp{color:#5d8092; font-size:.7rem; font-weight:700; margin:1rem .4rem .35rem; letter-spacing:.5px;}
    .app-sidebar a{display:flex; align-items:center; gap:.7rem; color:#bcd3dd; padding:.6rem .8rem; border-radius:11px; margin-bottom:.15rem; font-weight:700; font-size:.9rem;}
    .app-sidebar a i{width:20px; text-align:center;}
    .app-sidebar a:hover{background:var(--brand-light); color:#fff;}
    .app-sidebar a.active{background:var(--accent); color:#fff; box-shadow:0 6px 16px rgba(249,158,28,.35);}
    .app-sidebar a.disabled{opacity:.5; pointer-events:none;}
    .app-sidebar a .soon{margin-inline-start:auto; font-size:.62rem; background:#0a4f68; color:#9fc0cf; padding:.1rem .4rem; border-radius:20px;}

    /* Main */
    .app-main{margin-top:var(--nav-h); margin-right:var(--side-w); padding:1.5rem;}
    @media (max-width:991.98px){ .app-sidebar{display:none;} .app-main{margin-right:0;} }

    .card{border:none; box-shadow:0 1px 10px rgba(1,48,70,.06); border-radius:16px;}
    .btn-brand{background:var(--accent); border-color:var(--accent); color:#fff; font-weight:700;}
    .btn-brand:hover{background:var(--accent-d); border-color:var(--accent-d); color:#fff;}
    .page-title{color:var(--brand-deep); font-weight:800;}
    .stat{background:#fff; border-radius:16px; padding:1.1rem; box-shadow:0 1px 10px rgba(1,48,70,.06);}
    .stat .n{font-size:1.7rem; font-weight:800; color:var(--brand-deep);}
    .table thead th{color:#6b7c84; font-weight:700; font-size:.82rem;}
    .badge-soft{background:#eaf2f5; color:var(--brand);}
  </style>
  @stack('styles')
</head>
<body>
  @php $au = session('auth_user'); $r = request()->route()?->getName(); @endphp

  <!-- Navbar -->
  <nav class="app-navbar">
    <button class="icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar"><i class="fas fa-bars"></i></button>
    <img src="{{ asset('assets/images/Logo_how_gr.svg') }}" alt="HOW" class="logo">
    <div class="brand-sub d-none d-sm-block">منصّة الاشتراكات والنقاط<small>Plans — لوحة الإدارة</small></div>

    <div class="ms-auto d-flex align-items-center gap-2">
      <!-- Notifications -->
      <div class="dropdown">
        <button class="icon-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
          <i class="fas fa-bell"></i>
          @if(($notifUnread ?? 0) > 0)<span class="dot">{{ $notifUnread > 99 ? '99+' : $notifUnread }}</span>@endif
        </button>
        <div class="dropdown-menu dropdown-menu-start notif-menu p-0">
          <div class="head d-flex justify-content-between align-items-center">
            <span><i class="fas fa-bell text-warning"></i> الإشعارات @if(($notifUnread ?? 0) > 0)<span class="badge bg-warning text-dark">{{ $notifUnread }}</span>@endif</span>
            @if(($notifUnread ?? 0) > 0)
              <form method="post" action="{{ route('admin.notifications.readAll') }}" class="m-0">@csrf
                <button class="btn btn-sm p-0 border-0 bg-transparent text-primary" style="font-size:.72rem;">تمييز الكل كمقروء</button>
              </form>
            @endif
          </div>
          @forelse(($notifications ?? []) as $n)
            <a href="{{ $n->link ?: route('admin.notifications.index') }}" class="notif-item text-reset text-decoration-none {{ $n->read_at ? '' : 'unread' }}">
              <div class="ic" style="background:{{ $n->color }}"><i class="fas {{ $n->icon }}"></i></div>
              <div><div style="font-size:.85rem; color:#243b44;">{{ $n->title }}</div><div class="text-muted" style="font-size:.72rem;">{{ $n->timeAgo() }}</div></div>
            </a>
          @empty
            <div class="notif-empty"><i class="fas fa-check-circle fa-lg mb-2 d-block"></i>لا توجد إشعارات</div>
          @endforelse
          <a href="{{ route('admin.notifications.index') }}" class="d-block text-center py-2 text-decoration-none" style="font-size:.8rem; border-top:1px solid #eef2f5;">عرض كل الإشعارات</a>
        </div>
      </div>

      <!-- User -->
      <div class="dropdown">
        <button class="user-chip" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="nm d-none d-sm-inline">{{ $au['name'] ?? 'مشرف' }}</span>
          <span class="av">{{ mb_substr($au['name'] ?? 'م', 0, 1) }}</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-start shadow border-0" style="border-radius:14px;">
          <li class="px-3 py-2"><div class="fw-bold" style="color:var(--brand-deep)">{{ $au['name'] ?? 'مشرف' }}</div><div class="text-muted small">{{ $au['email'] ?? '' }}</div></li>
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

  <!-- Sidebar (desktop) -->
  <aside class="app-sidebar d-none d-lg-block">
    @include('partials.admin-nav', ['r' => $r])
  </aside>

  <!-- Sidebar (mobile offcanvas) -->
  <div class="offcanvas offcanvas-end text-bg-dark" tabindex="-1" id="mobileSidebar" style="background:var(--brand-deep)!important; width:260px;">
    <div class="offcanvas-header"><img src="{{ asset('assets/images/Logo_how_w.svg') }}" alt="HOW" style="height:30px;"><button class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button></div>
    <div class="offcanvas-body p-2">@include('partials.admin-nav', ['r' => $r])</div>
  </div>

  <!-- Main -->
  <main class="app-main">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 page-title mb-0">@yield('heading', 'لوحة الإدارة')</h1>
    </div>

    @if(session('ok'))<div class="alert alert-success py-2">{{ session('ok') }}</div>@endif
    @if(session('err'))<div class="alert alert-danger py-2">{{ session('err') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @yield('content')
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  @stack('scripts')
</body>
</html>
