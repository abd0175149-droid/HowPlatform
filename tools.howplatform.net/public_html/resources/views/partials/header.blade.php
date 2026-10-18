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
            @if(!empty($plansBadge))
            <a href="https://auth.howplatform.net/profile?active=points" title="النقاط والاشتراك"
               style="display:inline-flex;align-items:center;gap:.4rem;background:#eef6f9;border:1.5px solid #cfe6ee;border-radius:999px;padding:.28rem .8rem;color:#0a3247;font-weight:700;font-size:.8rem;text-decoration:none;margin-inline:.4rem;white-space:nowrap;">
                <i class="fas fa-coins" style="color:#fd9e02;"></i> {{ number_format($plansBadge['points']) }}
                <span style="opacity:.4;">|</span>
                <i class="fas fa-crown" style="color:#fd9e02;"></i> {{ !empty($plansBadge['has_plan']) ? ($plansBadge['plan_name'] ?? 'مشترك') : 'ترقية' }}
            </a>
            @endif
            @if((int)($authUser['is_admin'] ?? 0) === 1)
             <a href="{{ route('admin.tools.index') }}" class="btn btn-outline" style="margin-inline:.4rem;"><i class="fas fa-screwdriver-wrench"></i> الأدوات</a>
            @endif
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
