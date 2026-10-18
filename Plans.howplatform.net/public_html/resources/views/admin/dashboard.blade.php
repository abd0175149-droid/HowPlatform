@extends('layouts.admin')
@section('title','الرئيسية | Plans')
@section('heading','نظرة عامة')

@section('content')
  {{-- بطاقات إحصائية --}}
  <div class="row g-3">
    @php
      $cards = [
        ['الخطط المفعّلة', $stats['plans'], 'fa-box', '#6366f1', route('admin.plans.index')],
        ['اشتراكات فعّالة', $stats['subs_active'], 'fa-id-card', '#16a34a', route('admin.subscriptions.index')],
        ['بطاقات متاحة', $stats['cards_active'], 'fa-ticket', '#f59e0b', route('admin.cards.index')],
        ['بطاقات مستبدَلة', $stats['cards_redeemed'], 'fa-circle-check', '#0ea5e9', route('admin.cards.index', ['status'=>'redeemed'])],
        ['إجمالي النقاط', number_format($stats['points_total']), 'fa-coins', '#db2777', route('admin.users.index')],
        ['مبيعات الشهر', number_format($salesMonth,2).' JOD', 'fa-cash-register', '#0d9488', route('seller.dashboard')],
      ];
    @endphp
    @foreach($cards as [$label,$num,$icon,$color,$href])
      <div class="col-6 col-md-4 col-lg-3">
        <a href="{{ $href }}" class="text-reset text-decoration-none">
          <div class="stat h-100">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="text-muted small">{{ $label }}</div>
                <div class="n">{{ $num }}</div>
              </div>
              <i class="fas {{ $icon }}" style="font-size:1.5rem; color:{{ $color }};"></i>
            </div>
          </div>
        </a>
      </div>
    @endforeach
  </div>

  <div class="row g-3 mt-1">
    {{-- آخر النشاط --}}
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0"><i class="fas fa-clock-rotate-left text-secondary"></i> آخر النشاط</h2>
            <a href="{{ route('admin.notifications.index') }}" class="small text-decoration-none">عرض الكل</a>
          </div>
          <div class="list-group list-group-flush">
            @forelse($recent as $n)
              <a href="{{ $n->link ?: route('admin.notifications.index') }}" class="list-group-item list-group-item-action d-flex align-items-start gap-3 px-0 {{ $n->read_at ? '' : 'fw-bold' }}">
                <div style="width:32px;height:32px;border-radius:9px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;color:#fff;background:{{ $n->color }}">
                  <i class="fas {{ $n->icon }}" style="font-size:.8rem;"></i>
                </div>
                <div class="flex-grow-1">
                  <div style="font-size:.85rem; color:#243b44;">{{ $n->title }}</div>
                  <div class="text-muted" style="font-size:.72rem; font-weight:400;">{{ $n->timeAgo() }}</div>
                </div>
              </a>
            @empty
              <div class="text-center text-muted py-4"><i class="fas fa-check-circle fa-lg mb-2 d-block"></i>لا يوجد نشاط بعد.</div>
            @endforelse
          </div>
        </div>
      </div>
    </div>

    {{-- روابط سريعة --}}
    <div class="col-lg-5">
      <div class="card h-100">
        <div class="card-body">
          <h2 class="h6 mb-3"><i class="fas fa-bolt text-warning"></i> إجراءات سريعة</h2>
          <div class="d-grid gap-2">
            <a href="{{ route('admin.cards.issue') }}" class="btn btn-brand"><i class="fas fa-bolt"></i> إصدار دفعة بطاقات</a>
            <a href="{{ route('admin.packages.index') }}" class="btn btn-outline-secondary"><i class="fas fa-coins"></i> حزم النقاط</a>
            <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-secondary"><i class="fas fa-box"></i> الخطط والمزايا</a>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary"><i class="fas fa-users"></i> المستخدمون والمحافظ</a>
            <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary"><i class="fas fa-chart-line"></i> التقارير</a>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
