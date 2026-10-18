@extends('layouts.admin')
@section('title', 'لوحتي')
@section('heading', 'لوحة مبيعاتي')

@push('styles')
<style>
  .tt{font-size:.72rem;padding:.15rem .5rem;border-radius:20px;font-weight:700;}
  .tt-card_sale{background:#e7f5ec;color:#1c7a45;}
  .tt-payment{background:#e7eefc;color:#2350b8;}
  .tt-refund{background:#fdeaea;color:#c0392b;}
  .tt-adjust_invoice{background:#fff3e0;color:#b9770e;}
  .tt-adjustment{background:#eef0f2;color:#566;}
</style>
@endpush

@php
  $available = $limit > 0 ? max(0, $limit - $balance) : null;
  $labels = ['card_sale'=>'بيع بطاقة','payment'=>'دفعة','refund'=>'استرداد','adjust_invoice'=>'تعديل فاتورة','adjustment'=>'تسوية'];
@endphp

@section('content')
@unless($sp)
  <div class="alert alert-warning"><i class="fas fa-triangle-exclamation"></i> لا توجد نقطة بيع مرتبطة بحسابك بعد. تواصل مع الإدارة.</div>
@endunless

<div class="row g-3 mb-2">
  <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">رصيد الشهر {{ $periodKey }}</div><div class="n">{{ number_format($balance,2) }}</div><div class="text-muted small">JOD مستحقة</div></div></div>
  <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">السقف الشهري</div><div class="n">{{ $limit>0 ? number_format($limit,2) : '∞' }}</div><div class="text-muted small">JOD</div></div></div>
  <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">المتاح للإصدار</div><div class="n">{{ is_null($available) ? '∞' : number_format($available,2) }}</div><div class="text-muted small">JOD</div></div></div>
  <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">بطاقاتي / مبيعاتي</div><div class="n" style="font-size:1.2rem">{{ number_format($totalCards) }}</div><div class="text-muted small">{{ number_format($totalSales,2) }} JOD</div></div></div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card p-0 h-100"><div class="p-3 fw-bold" style="color:var(--brand-deep)">مبيعاتي حسب الخطة</div>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>الخطة</th><th>عدد</th><th>الإجمالي</th></tr></thead><tbody>
        @forelse($byPlan as $p)
          <tr><td class="fw-bold">{{ $p->plan }}</td><td>{{ number_format($p->count) }}</td><td>{{ number_format((float)$p->total,2) }}</td></tr>
        @empty<tr><td colspan="3" class="text-center text-muted py-4">لا مبيعات بعد.</td></tr>@endforelse
        </tbody></table></div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="card p-0">
      <div class="p-3 d-flex flex-wrap gap-2 align-items-center">
        <span class="fw-bold" style="color:var(--brand-deep)">معاملاتي</span>
        <form class="d-flex gap-2 ms-auto" method="get">
          <select name="type" class="form-select form-select-sm" style="width:auto">
            <option value="">كل الأنواع</option>
            @foreach($labels as $k=>$lbl)<option value="{{ $k }}" @selected(request('type')===$k)>{{ $lbl }}</option>@endforeach
          </select>
          <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="بحث..." style="width:140px">
          <button class="btn btn-sm btn-brand">تصفية</button>
        </form>
      </div>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>المستند</th><th>النوع</th><th>المبلغ</th><th>بعد</th><th>التاريخ</th></tr></thead><tbody>
        @forelse($transactions as $t)
          <tr>
            <td class="text-muted small">{{ $t->doc_code }}</td>
            <td><span class="tt tt-{{ $t->type }}">{{ $labels[$t->type] ?? $t->type }}</span></td>
            <td class="fw-bold">{{ number_format((float)$t->amount,2) }}</td>
            <td class="text-muted small">{{ number_format((float)$t->balance_after,2) }}</td>
            <td class="text-muted small" dir="ltr">{{ \Illuminate\Support\Str::of((string)$t->created_at)->limit(16,'') }}</td>
          </tr>
        @empty<tr><td colspan="5" class="text-center text-muted py-4">لا معاملات.</td></tr>@endforelse
        </tbody></table></div>
      <div class="p-3">{{ $transactions->links() }}</div>
    </div>
  </div>
</div>
@endsection
