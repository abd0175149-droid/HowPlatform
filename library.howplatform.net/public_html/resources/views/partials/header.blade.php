<header class="site-header" dir="rtl">
    <div class="header-inner">
        <!-- شعار -->
        <a class="brand" href="{{ url('/') }}">
            <img src="{{ asset('assets/images/Logo_how_w.svg') }}" alt="كيف التعليمية">
            <span>كيف التعليمية</span>
        </a>

        <!-- زر القائمة للموبايل -->
        <button class="nav-toggle" aria-controls="mainnav" aria-expanded="false" aria-label="فتح القائمة">
            <i class="fa-solid fa-bars"></i>
        </button>

        <!-- القائمة الرئيسية -->
        <nav id="mainnav" class="main-nav" aria-label="الملاحة الرئيسية" aria-expanded="false">
            <a href="{{ url('/') }}">المكتبة الرقمية</a>
            <a href="https://howplatform.net/about">من نحن</a>
            <a href="https://howplatform.net/services">خدماتنا</a>
            <a href="https://howplatform.net/contact">تواصل معنا</a>
            <a href="https://www.howplatform.net/">HowPlatform</a>

            @php
                $authUser = session('auth_user');
            @endphp
            
            @if ($authUser)
                <div class="user-dropdown">
                    <button class="user-name" onclick="this.parentElement.classList.toggle('open')">
                        {{ $authUser['name'] }}
                        <i class="fa-solid fa-caret-down"></i>
                    </button>
            
                    <div class="dropdown-menu">
                        <a href="https://auth.howplatform.net/logout-everywhere?redirect={{ urlencode(url()->current()) }}">
  تسجيل الخروج
</a>
                    </div>
                </div>
            @else
                <a href="https://auth.howplatform.net/login?redirect=https://library.howplatform.net" class="btn-login">
                    تسجيل الدخول
                </a>
            @endif
        </nav>
    </div>
</header>
