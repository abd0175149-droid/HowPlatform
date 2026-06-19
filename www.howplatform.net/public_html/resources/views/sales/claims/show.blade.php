@extends('layouts.main')

@section('title', 'مطالبة مبيعات #' . ($claim->claim_code ?? $claim->id))

@push('styles')
<style>
.container-narrow{max-width:1100px;margin-inline:auto}
.cardish{background:#fff;border:1px solid rgba(0,0,0,.06);border-radius:16px;padding:16px;box-shadow:0 6px 16px rgba(0,0,0,.08)}
.headline{display:flex;justify-content:space-between;align-items:center;gap:12px}
.headline h3{margin:0;font-weight:900;color:var(--primary-900,#0a3247);padding-inline-start:.6rem;border-inline-start:8px solid var(--accent-500,#fd9e02)}
.meta small{display:block;color:#555}
.table thead th{background:linear-gradient(135deg,var(--primary-800,#104b65),var(--primary-900,#0a3247));color:#fff}
.badge-paid{background:#16a34a} .badge-unpaid{background:#ef4444}
</style>
@endpush

@section('content')
<div class="container-fluid py-4 container-narrow" dir="rtl">

  <div class="cardish mb-3">
    <div class="headline">
      <h3><i class="fa-solid fa-file-invoice-dollar me-2"></i> مطالبة: {{ $claim->claim_code }}</h3>
      <div class="d-flex gap-2">
        <a href="{{ route('pos.claims.print', $claim->id) }}" class="btn btn-outline-primary" target="_blank" rel="noopener">
          <i class="fa-solid fa-print me-1"></i> معاينة/طباعة
        </a>
        <a href="{{ route('pos.sales-points.index', ['tab'=>'points']) }}" class="btn btn-secondary">
          <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> رجوع
        </a>
      </div>
    </div>

    <div class="row g-3 mt-2">
      <div class="col-md-4">
        <div class="cardish">
          <div class="fw-bold text-muted mb-1">المندوب</div>
          <div class="fs-5">{{ $claim->user_name ?? 'غير معروف' }} <span class="text-muted">(#{{ $claim->user_id }})</span></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="cardish">
          <div class="fw-bold text-muted mb-1">الفترة</div>
          <div class="fs-6">{{ $claim->date_from }} → {{ $claim->date_to }}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="cardish">
          <div class="fw-bold text-muted mb-1">الإجمالي</div>
          <div class="fs-5">{{ number_format((float)$claim->invoices_total, 2) }} JOD</div>
          <small class="text-muted">عدد الفواتير: {{ (int)$claim->invoices_count }}</small>
        </div>
      </div>
    </div>
  </div>

  <div class="cardish">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="mb-0 fw-bold"><i class="fa-solid fa-receipt me-2"></i> فواتير المطالبة</h5>
      <div class="text-muted small">تعرض 10 سجلات في كل صفحة</div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered align-middle text-center">
        <thead>
          <tr>
            <th>#</th>
            <th>رقم العملية (TX)</th>
            <th>كود الفاوتر</th>
            <th>الخطة</th>
            <th>المنصة</th>
            <th>سعر الفاوتر</th>
            <th>الصافي للمندوب</th>
            <th>تاريخ البيع</th>
            <th>الحالة</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $row)
            <tr>
              <td>{{ $row->id }}</td>
              <td class="fw-bold">{{ $row->tx_doc_code ?? '—' }}</td>
              <td>{{ $row->voucher_code }}</td>
              <td>{{ $row->plan_name }}</td>
              <td>{{ $row->platform }}</td>
              <td>{{ number_format((float)$row->price_amount, 2) }}</td>
              <td class="fw-bold">{{ number_format((float)($row->net_due ?? $row->amount), 2) }}</td>
              <td>{{ $row->sold_at ? \Illuminate\Support\Carbon::parse($row->sold_at)->format('Y-m-d H:i') : '—' }}</td>
              <td>
                @if((int)$row->is_paid === 1)
                  <span class="badge text-white badge-paid">مدفوع</span>
                @else
                  <span class="badge text-white badge-unpaid">غير مدفوع</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-muted">لا توجد فواتير ضمن هذه المطالبة</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(method_exists($items, 'hasPages') && $items->hasPages())
      <div class="mt-3 d-flex justify-content-center">
        {{ $items->onEachSide(1)->links('pagination::how') }}
      </div>
    @endif
  </div>

</div>
@endsection
