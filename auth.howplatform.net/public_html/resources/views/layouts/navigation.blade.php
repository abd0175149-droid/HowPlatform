<nav class="nav-container" x-data="{ dropdownOpen:false }">
    {{-- الشعار --}}
    <a href="{{ url('/') }}" class="logo">
        <div class="logo-icon"><i class="fas fa-cube"></i></div>
        {{ config('app.name', 'HowPlatform') }}
    </a>

    {{-- الروابط الأساسية --}}
    <div class="nav-links">
        <a href="{{ route('profile.edit') }}"
           class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
            <i class="fas fa-user-circle"></i> الملف الشخصي
        </a>

        @guest
            <a href="{{ route('login') }}" class="nav-link">تسجيل الدخول</a>
            <a href="{{ route('register') }}" class="nav-link">إنشاء حساب</a>
        @else
            {{-- قائمة المستخدم --}}
            @php
                $u = auth()->user();
                $avatar = $u->avatar ?: 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($u->email))).'?s=200&d=identicon';
            @endphp

            <div class="user-menu"> <a href="{{ route('profile.edit') }}" class="dropdown-item">
                        <i class="fas fa-user"></i> الملف الشخصي
                    </a>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item" style="width:100%;text-align:right;background:none;border:0;cursor:pointer">
                            <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                        </button>
                    </form>
                <button type="button" class="user-btn" @click="dropdownOpen = !dropdownOpen">
                    <img src="{{ $avatar }}" alt="User" class="user-avatar">
                    <span>{{ $u->name }}</span>
                    <i class="fas fa-chevron-down"></i>
                </button>

                <div class="dropdown-menu" :class="{ 'show': dropdownOpen }" @click.outside="dropdownOpen=false">
                   
                </div>
            </div>
        @endguest
    </div>
</nav>
