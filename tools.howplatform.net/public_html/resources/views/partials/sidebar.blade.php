@php
  // التاب النشط + مولّد الروابط مع الحفاظ على باقي الباراميترات
  $activeTab = $activeTab ?? request('tab', 'all');
  $qs = request()->except(['page']);
  $link = function(string $tab) use ($qs) {
      return route('tools.index', array_merge($qs, ['tab' => $tab]));
  };

  // أيقونات لكل تصنيف حسب الـ slug (أضف/عدّل بحرّية)
  $catIcons = [
    'writing'    => 'fas fa-pencil-alt',
    'analytics'  => 'fas fa-chart-bar',
    'classroom'  => 'fas fa-chalkboard-teacher',
    'fun'        => 'fas fa-gamepad',
    'media'      => 'fas fa-video',
    'id-cards'   => 'fas fa-id-card',     // التصنيف الجديد
  ];
@endphp

  {{-- موبايل: روابط الناف + الحساب داخل السايدبار --}}
  <div class="mobile-nav">
    <div class="mobile-nav-links">
      @include('partials.nav-links')
    </div>

    @php $authUser = session('auth_user'); @endphp
    @if ($authUser)
      <div class="user-col">
        <div class="student-avatar">
        @php $avatar = trim($authUser['avatar'] ?? ''); @endphp
        <img src="{{ $avatar !== '' ? $avatar : 'https://tools.howplatform.net/public/assets/images/Avatar.png' }}"
             alt="Avatar"
             class="avatar-img"
             data-section="account"
             onerror="this.onerror=null;this.src='https://tools.howplatform.net/public/assets/images/Avatar.png';" />
        </div>
                <span class="user-name">{{ $authUser['name'] }}</span>

      </div>
      <a class="sidebar-link" href="https://auth.howplatform.net/logout-everywhere?redirect={{ urlencode(url()->current()) }}">
        <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
      </a>
    @else
      <div class="user-actions">
        <a href="https://auth.howplatform.net/login?redirect=https://tools.howplatform.net" class="btn btn-outline">تسجيل الدخول</a>
        <a href="https://auth.howplatform.net/register?redirect=https://tools.howplatform.net" class="btn btn-primary">إنشاء حساب</a>
      </div>
    @endif
  </div>

  {{-- التصنيفات (ديناميكي من قاعدة البيانات) --}}
  <div class="sidebar-section">
    <h3 class="sidebar-title">التصنيفات</h3>

    <a href="{{ $link('all') }}"
       class="sidebar-link {{ $activeTab==='all' ? 'active' : '' }}"
       aria-current="{{ $activeTab==='all' ? 'page' : 'false' }}">
      <i class="fas fa-th-large"></i>
      جميع الأدوات
    </a>

    @forelse($categories ?? [] as $cat)
      <a href="{{ $link($cat->slug) }}"
         class="sidebar-link {{ $activeTab===$cat->slug ? 'active' : '' }}"
         aria-current="{{ $activeTab===$cat->slug ? 'page' : 'false' }}">
        <i class="{{ $catIcons[$cat->slug] ?? 'fas fa-folder' }}"></i>
        {{ $cat->name }}
      </a>
    @empty
      <div class="no-results" style="padding: .75rem 1.5rem;">لا توجد تصنيفات بعد.</div>
    @endforelse
  </div>

  {{-- الترتيب / الشهرة --}}
  <div class="sidebar-section">
    <h3 class="sidebar-title">الترتيب</h3>

    <a href="{{ $link('featured') }}" class="sidebar-link {{ $activeTab==='featured' ? 'active' : '' }}" aria-current="{{ $activeTab==='featured' ? 'page' : 'false' }}">
      <i class="fas fa-star"></i> الأدوات المميزة
    </a>

    <a href="{{ $link('most-used') }}" class="sidebar-link {{ $activeTab==='most-used' ? 'active' : '' }}" aria-current="{{ $activeTab==='most-used' ? 'page' : 'false' }}">
      <i class="fas fa-fire"></i> الأكثر استخداماً
    </a>

    <a href="{{ $link('most-popular') }}" class="sidebar-link {{ $activeTab==='most-popular' ? 'active' : '' }}" aria-current="{{ $activeTab==='most-popular' ? 'page' : 'false' }}">
      <i class="fas fa-chart-line"></i> الأكثر شهرة
    </a>

    <a href="{{ $link('new') }}" class="sidebar-link {{ $activeTab==='new' ? 'active' : '' }}" aria-current="{{ $activeTab==='new' ? 'page' : 'false' }}">
      <i class="fas fa-bolt"></i> الأدوات الجديدة
    </a>
  </div>
  
<div class="sidebar-section">
  <h3 class="sidebar-title">صفوفي الدراسية</h3>

  @php $authUser = session('auth_user'); @endphp
  @if ($authUser)
    <a href="{{ route('user.classes.index') }}" class="sidebar-link">
      <i class="fas fa-school"></i> إدارة صفوفي
    </a>
  @else
    <a href="{{ route('login', ['redirect' => url('/my/classes')]) }}" class="sidebar-link">
      <i class="fas fa-sign-in-alt"></i> تسجيل الدخول لإدارة الصفوف
    </a>
  @endif
</div>


  {{-- الحساب (ثابتة كمثال للديسكتوب) --}}
  <div class="sidebar-section">
    <h3 class="sidebar-title">الحساب</h3>
    <a href="https://auth.howplatform.net/profile" class="sidebar-link">
      <i class="fas fa-user"></i> الملف الشخصي
    </a>
    <!--
    <a href="#" class="sidebar-link">
      <i class="fas fa-cog"></i> الإعدادات
    </a>
    <a href="#" class="sidebar-link">
      <i class="fas fa-question-circle"></i> المساعدة
    </a>
    -->
  </div>

