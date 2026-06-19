<header class="site-header" dir="rtl">
    <div class="header-inner">

        {{-- ── الشعار: ملون في اللايت مود، أبيض في الدارك ── --}}
        <a class="brand" href="{{ url('/') }}">
            <img
              id="how-logo-img"
              src="{{ asset('assets/images/Logo_how_w.svg') }}"
              alt="كيف التعليمية"
              width="44" height="44"
              data-how-logo
              data-logo-white="{{ asset('assets/images/Logo_how_w.svg') }}"
              data-logo-color="{{ asset('assets/images/Logo_how_gr.svg') }}"
            >
            <span>كيف التعليمية</span>
        </a>

        {{-- ── شريط أدوات اليمين (موبايل: toggle + hamburger) ── --}}
        <div class="header-actions">

            {{-- زر تبديل الثيم ── مكانه بجانب الهامبرجر --}}
            <button
              id="how-theme-toggle"
              class="theme-toggle-btn"
              aria-label="التبديل إلى الوضع المظلم"
              title="الوضع المظلم"
              type="button"
              data-theme-current="light"
            >
                {{-- أيقونة القمر افتراضياً (اللايت مود) — يحدّثها theme.js --}}
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>

            {{-- زر الهامبرجر (موبايل فقط) --}}
            <button class="nav-toggle" id="nav-toggle-btn"
                    aria-controls="mainnav"
                    aria-expanded="false"
                    aria-label="فتح القائمة">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>

        <nav id="mainnav" class="main-nav" aria-label="الملاحة الرئيسية" aria-expanded="false">
            <a href="{{ url('/') }}">الرئيسية</a>
            <a href="{{ url('/about') }}">من نحن</a>
            <a href="{{ url('/services') }}">خدماتنا</a>
            <a href="{{ url('/contact') }}">تواصل معنا</a>
            <a href="{{ url('/platforms') }}">منصاتنا التعليمة</a>

            @php $authUser = session('auth_user'); @endphp

            @if ($authUser)
                <div class="user-dropdown">
                    <button class="user-name"
                            onclick="this.parentElement.classList.toggle('open')"
                            aria-haspopup="true" aria-expanded="false">
                        {{ $authUser['name'] }}
                        <i class="fa-solid fa-caret-down" aria-hidden="true"></i>
                    </button>
                    <div class="dropdown-menu" role="menu">
                        @if (!empty($authUser['is_sales']) && $authUser['is_sales'] == 1)
                            <a href="{{ url('/pos/dashboard') }}" class="dropdown-item" role="menuitem">لوحة المبيعات</a>
                            <a href="{{ url('/pos/sales/dashboard') }}" class="dropdown-item" role="menuitem">لوحة المبيعات - مستخدم</a>
                            <a href="{{ url('/pos/issue') }}" class="dropdown-item" role="menuitem">اصدار بطاقة</a>
                        @endif
                        <a href="https://auth.howplatform.net/profile" class="dropdown-item" role="menuitem">الحساب الشخصي</a>
                        <a href="https://auth.howplatform.net/logout-everywhere?redirect={{ urlencode(url()->current()) }}"
                           class="dropdown-item" role="menuitem">تسجيل الخروج</a>
                    </div>
                </div>
            @else
                <a href="https://auth.howplatform.net/login?redirect=https://www.howplatform.net"
                   class="btn-login" id="header-login-btn">
                    تسجيل الدخول
                </a>
            @endif
        </nav>
    </div>
</header>
