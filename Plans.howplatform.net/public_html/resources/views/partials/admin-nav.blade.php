@php
  $r = $r ?? request()->route()?->getName();
  $au = session('auth_user');
  $isAdmin = (int)($au['is_admin'] ?? 0) === 1;
  $isSales = (int)($au['is_sales'] ?? 0) === 1;
@endphp

@if($isAdmin)
  <div class="grp">الرئيسية</div>
  <a href="{{ route('admin.dashboard') }}" class="{{ $r==='admin.dashboard' ? 'active' : '' }}">
    <i class="fas fa-gauge-high"></i> لوحة المعلومات
  </a>
  <a href="{{ route('admin.notifications.index') }}" class="{{ str_starts_with((string)$r,'admin.notifications') ? 'active' : '' }}">
    <i class="fas fa-bell"></i> الإشعارات
    @if(($notifUnread ?? 0) > 0)<span class="soon" style="background:var(--accent);color:#fff;">{{ $notifUnread }}</span>@endif
  </a>

  <div class="grp">الاشتراكات</div>
  <a href="{{ route('admin.plans.index') }}" class="{{ str_starts_with((string)$r,'admin.plans') ? 'active' : '' }}">
    <i class="fas fa-box"></i> الخطط والمزايا
  </a>
  <a href="{{ route('admin.cards.index') }}" class="{{ str_starts_with((string)$r,'admin.cards') ? 'active' : '' }}">
    <i class="fas fa-ticket"></i> البطاقات
  </a>
  <a href="{{ route('admin.packages.index') }}" class="{{ str_starts_with((string)$r,'admin.packages') ? 'active' : '' }}">
    <i class="fas fa-coins"></i> حزم النقاط
  </a>
  <a href="{{ route('admin.subscriptions.index') }}" class="{{ str_starts_with((string)$r,'admin.subscriptions') ? 'active' : '' }}">
    <i class="fas fa-id-card"></i> اشتراكات المستخدمين
  </a>
@endif

@if($isAdmin || $isSales)
  <div class="grp">المبيعات</div>
  <a href="{{ route('seller.issue') }}" class="{{ $r==='seller.issue' ? 'active' : '' }}">
    <i class="fas fa-cash-register"></i> إصدار بطاقة
  </a>
  @if($isSales && !$isAdmin)
    <a href="{{ route('seller.mine') }}" class="{{ $r==='seller.mine' ? 'active' : '' }}">
      <i class="fas fa-chart-pie"></i> لوحتي
    </a>
    <a href="{{ route('seller.cards.vouchers') }}" target="_blank" rel="noopener">
      <i class="fas fa-print"></i> قسائمي
    </a>
  @endif
  @if($isAdmin)
    <a href="{{ route('seller.dashboard') }}" class="{{ $r==='seller.dashboard' ? 'active' : '' }}">
      <i class="fas fa-chart-pie"></i> لوحة المبيعات
    </a>
    <a href="{{ route('seller.points') }}" class="{{ str_starts_with((string)$r,'seller.points') ? 'active' : '' }}">
      <i class="fas fa-store"></i> نقاط البيع والسقوف
    </a>
    <a href="{{ route('seller.claims') }}" class="{{ (str_starts_with((string)$r,'seller.claims') || $r==='seller.payments.store') ? 'active' : '' }}">
      <i class="fas fa-file-invoice-dollar"></i> المطالبات والمدفوعات
    </a>
  @endif
@endif

@if($isAdmin)
  <div class="grp">المستخدمون</div>
  <a href="{{ route('admin.users.index') }}" class="{{ str_starts_with((string)$r,'admin.users') ? 'active' : '' }}">
    <i class="fas fa-users"></i> المستخدمون والمحافظ
  </a>
  <a href="{{ route('admin.reports.index') }}" class="{{ str_starts_with((string)$r,'admin.reports') ? 'active' : '' }}">
    <i class="fas fa-chart-line"></i> التقارير
  </a>
@endif
