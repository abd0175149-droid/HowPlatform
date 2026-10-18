@extends('layouts.user')
@section('title','اشتراكاتي | Plans')

@php
  $statusMap = [
    'active'    => ['نشط', 'ok'],
    'scheduled' => ['مجدول', 'warn'],
    'canceled'  => ['ملغى', 'muted'],
    'cancelled' => ['ملغى', 'muted'],
    'expired'   => ['منتهٍ', 'muted'],
  ];
  $sourceMap = [
    'card'      => 'بطاقة',
    'admin'     => 'إدارة',
    'promo'     => 'عرض ترويجي',
    'migration' => 'ترحيل',
  ];
@endphp

@section('content')
  {{-- الاشتراك الحالي --}}
  @if($current)
    <div class="sub-current">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
          <div class="opacity-75 fw-bold" style="font-size:.85rem;">اشتراكك الحالي</div>
          <div class="sc-plan">{{ optional($current->plan)->name ?? '—' }}</div>
          <div class="sc-meta">المستوى {{ optional($current->plan)->level ?? 0 }}</div>
        </div>
        <div class="text-start">
          @if($current->end_at)
            <div class="opacity-75" style="font-size:.8rem;">ينتهي في</div>
            <div class="fw-bold" style="font-size:1.15rem;">{{ $current->end_at->translatedFormat('Y-m-d') }}</div>
            <div class="opacity-75" style="font-size:.8rem;">متبقٍّ {{ max(0, (int) now()->diffInDays($current->end_at, false)) }} يوم</div>
          @else
            <div class="fw-bold">بلا تاريخ انتهاء</div>
          @endif
        </div>
      </div>
    </div>
  @else
    <div class="profile-section-card active">
      <div class="section-card-body text-center py-4">
        <div class="mb-2" style="color:var(--primary-500);"><i class="fas fa-id-card fa-2x"></i></div>
        <h3 class="h6 fw-bold mb-2">لا يوجد اشتراك فعّال</h3>
        <p class="text-muted small mb-3">فعّل خطة للاستفادة من مزايا المنصّات.</p>
        <a href="{{ route('user.plans') }}" class="profile-btn profile-btn-primary"><i class="fas fa-crown"></i> عرض الخطط والأسعار</a>
      </div>
    </div>
  @endif

  {{-- سجلّ الاشتراكات --}}
  <div class="profile-section-card active">
    <div class="section-card-header">
      <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fas fa-clock-rotate-left"></i></span><span>سجلّ الاشتراكات</span></h3>
    </div>
    <div class="section-card-body">
      <div class="table-responsive">
        <table class="acc-table">
          <thead><tr><th>الخطة</th><th>الحالة</th><th>البداية</th><th>الانتهاء</th><th>المصدر</th></tr></thead>
          <tbody>
            @forelse($subs as $s)
              @php
                $isActiveNow = $s->status === 'active' && (is_null($s->end_at) || $s->end_at->isFuture());
                [$stLabel,$stClass] = $statusMap[$s->status] ?? [$s->status, 'muted'];
                if ($s->status === 'active' && !$isActiveNow) { $stLabel = 'منتهٍ'; $stClass = 'muted'; }
              @endphp
              <tr>
                <td class="fw-bold">{{ optional($s->plan)->name ?? '—' }}</td>
                <td><span class="status-pill {{ $stClass }}">{{ $stLabel }}</span></td>
                <td class="text-muted small">{{ $s->start_at ? $s->start_at->format('Y-m-d') : '—' }}</td>
                <td class="text-muted small">{{ $s->end_at ? $s->end_at->format('Y-m-d') : '—' }}</td>
                <td class="text-muted small">{{ $sourceMap[$s->source] ?? ($s->source ?? '—') }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted py-3">لا توجد اشتراكات بعد.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
