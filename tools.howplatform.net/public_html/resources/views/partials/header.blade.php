<header class="site-header">
    <div class="header-content">
        <div class="logo">
            <img src="{{ asset('assets/images/Logo_Tools.svg') }}" alt="HOWTOOLS">
            <span>منصة كيف - الأدوات التعليمية</span>
        </div>
        <button class="nav-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <nav class="main-nav">
          @include('partials.nav-links')
        <div class="user-actions">
             @php
                $authUser = session('auth_user');
            @endphp

            @if ($authUser)
            <div class="student-avatar">
        @php $avatar = trim($authUser['avatar'] ?? ''); @endphp
        <img src="{{ $avatar !== '' ? $avatar : 'https://tools.howplatform.net/public/assets/images/Avatar.png' }}"
             alt="Avatar"
             class="avatar-img"
             data-section="account"
             onerror="this.onerror=null;this.src='https://tools.howplatform.net/public/assets/images/Avatar.png';" />

            </div>
                    <a class="user-name">{{ $authUser['name'] }}</a>
             <a href="https://auth.howplatform.net/logout-everywhere?redirect={{ urlencode(url()->current()) }}">
              تسجيل الخروج
            </a>
            @else
                <a href="https://auth.howplatform.net/login?redirect=https://tools.howplatform.net" class="btn btn-outline">
                    تسجيل الدخول
                </a>
                <a href="https://auth.howplatform.net/register?redirect=https://tools.howplatform.net" class="btn btn-primary">
إنشاء حساب          </a>
            @endif
        </div> 
        </nav>
    </div>
</header>
