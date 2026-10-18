@extends('layouts.admin')
@section('title', 'مطالبة ' . ($claim->claim_code ?? $claim->id))
@section('heading', 'مطالبة ' . ($claim->claim_code ?? $claim->id))

@push('styles')
<style>
  .st{font-size:.72rem;padding:.15rem .55rem;border-radius:20px;font-weight:700;}
  .st-open{background:#fff3e0;color:#b9770e;}
  .st-paid{background:#e7f5ec;color:#1c7a45;}
  .it{font-size:.72rem;padding:.15rem .5rem;border-radius:20px;font-weight:700;}
  .it-1{background:#e7f5ec;color:#1c7a45;}
  .it-0{background:#fdeaea;color:#c0392b;}
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <div></div>
  <div class="d-flex gap-2">
    <a href="{{ route('seller.claims.print', $claim->id) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="fas fa-print"></i> طباعة</a>
    <a href="{{ route('seller.claims') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-right"></i> رجوع</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">المندوب</div><div class="fw-bold" style="color:var(--brand-deep)">{{ $claim->user_name }}</div><div class="text-muted small">UID {{ $claim->user_id }}</div></div></div>
  <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">الفترة</div><div class="fw-bold" dir="ltr" style="font-size:.9rem">{{ $claim->date_from ?: '—' }} → {{ $claim->date_to ?: '—' }}</div></div></div>
  <div class="col-6 col-md-2"><div class="stat"><div class="text-muted small">عدد البطاقات</div><div class="n">{{ number_format($claim->invoices_count) }}</div></div></div>
  <div class="col-6 col-md-2"><div class="stat"><div class="text-muted small">الإجمالي</div><div class="n" style="font-size:1.2rem">{{ number_format((float)$claim->invoices_total,2) }}</div><div class="text-muted small">JOD</div></div></div>
  <div class="col-6 col-md-2"><div class="stat"><div class="text-muted small">الحالة</div><div class="mt-1"><span class="st st-{{ $claim->status }}">{{ $claim->status === 'paid' ? 'مدفوعة' : 'مفتوحة' }}</span></div><div class="text-muted small mt-1">سُدّد {{ number_format($paidTotal,2) }} JOD</div></div></div>
</div>

<div class="card p-0">
  <div class="p-3 fw-bold" style="color:var(--brand-deep)">بطاقات المطالبة</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0 text-center">
      <thead><tr><th>#</th><th>كود البطاقة</th><th>الخطة</th><th>المشتري</th><th>المبلغ</th><th>المسدّد</th><th>الحالة</th><th>تاريخ السداد</th></tr></thead>
      <tbody>
      @forelse($items as $row)
        <tr>
          <td class="text-muted">{{ $row->id }}</td>
          <td class="fw-bold" dir="ltr">{{ $row->card_code ?? '—' }}</td>
          <td>{{ $row->plan_name }}</td>
          <td>{{ $row->buyer_name ?: '—' }}</td>
          <td>{{ number_format((float)$row->amount,2) }}</td>
          <td>{{ number_format((float)$row->paid_amount,2) }}</td>
          <td><span class="it it-{{ (int)$row->is_paid }}">{{ (int)$row->is_paid === 1 ? 'مدفوع' : 'غير مدفوع' }}</span></td>
          <td class="text-muted small" dir="ltr">{{ $row->paid_at ? \Illuminate\Support\Str::of((string)$row->paid_at)->limit(16,'') : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-muted py-4">لا توجد بطاقات ضمن هذه المطالبة.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3">{{ $items->links() }}</div>
</div>
@endsection
