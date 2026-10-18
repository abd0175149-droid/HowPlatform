@extends('layouts.admin')
@section('title', 'لوحة المبيعات')
@section('heading', 'لوحة المبيعات')

@push('styles')
<style>
  .seg{display:inline-flex;background:#e9eff3;border-radius:12px;padding:.25rem;margin-bottom:1rem;}
  .seg a{padding:.4rem 1rem;border-radius:9px;font-weight:700;color:#5d8092;font-size:.85rem;}
  .seg a.on{background:var(--accent);color:#fff;}
  .tt{font-size:.72rem;padding:.15rem .5rem;border-radius:20px;font-weight:700;}
  .tt-card_sale{background:#e7f5ec;color:#1c7a45;}
  .tt-payment{background:#e7eefc;color:#2350b8;}
  .tt-refund{background:#fdeaea;color:#c0392b;}
  .tt-adjust_invoice{background:#fff3e0;color:#b9770e;}
  .tt-adjustment{background:#eef0f2;color:#566;}
</style>
@endpush

@php
  $tab = request('tab','overview');
  $labels = ['card_sale'=>'بيع بطاقة','payment'=>'دفعة','refund'=>'استرداد','adjust_invoice'=>'تعديل فاتورة','adjustment'=>'تسوية'];
@endphp

@section('content')
<div class="row g-3 mb-2">
  <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">بطاقات مُباعة</div><div class="n">{{ number_format($totalCards) }}</div></div></div>
  <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">إجمالي المبيعات</div><div class="n">{{ number_format($totalSales,2) }}</div><div class="text-muted small">JOD</div></div></div>
  <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">عدد البائعين</div><div class="n">{{ $bySeller->count() }}</div></div></div>
  <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">الشهر</div><div class="n" style="font-size:1.1rem">{{ $periodKey }}</div></div></div>
</div>

<div class="seg">
  <a href="?tab=overview" class="{{ $tab==='overview'?'on':'' }}">نظرة عامة</a>
  <a href="?tab=transactions" class="{{ $tab==='transactions'?'on':'' }}">المعاملات والإجراءات</a>
</div>

{{-- ===== نظرة عامة ===== --}}
<div @if($tab!=='overview') style="display:none" @endif>
  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card p-0"><div class="p-3 fw-bold" style="color:var(--brand-deep)">المبيعات حسب البائع</div>
        <div class="table-responsive"><table class="table align-middle mb-0">
          <thead><tr><th>البائع</th><th>عدد</th><th>الإجمالي</th></tr></thead><tbody>
          @forelse($bySeller as $s)
            <tr><td><div class="fw-bold">{{ $s->name ?: 'غير معروف' }}</div><div class="text-muted small">UID {{ $s->user_id }}</div></td>
            <td>{{ number_format($s->count) }}</td><td>{{ number_format((float)$s->total,2) }} JOD</td></tr>
          @empty<tr><td colspan="3" class="text-center text-muted py-4">لا مبيعات بعد.</td></tr>@endforelse
          </tbody></table></div>
      </div>
    </div>
    <div class="col-12 col-lg-6">
      <div class="card p-0"><div class="p-3 fw-bold" style="color:var(--brand-deep)">المبيعات حسب الخطة</div>
        <div class="table-responsive"><table class="table align-middle mb-0">
          <thead><tr><th>الخطة</th><th>عدد</th><th>الإجمالي</th></tr></thead><tbody>
          @forelse($byPlan as $p)
            <tr><td class="fw-bold">{{ $p->plan }}</td><td>{{ number_format($p->count) }}</td><td>{{ number_format((float)$p->total,2) }} JOD</td></tr>
          @empty<tr><td colspan="3" class="text-center text-muted py-4">لا مبيعات بعد.</td></tr>@endforelse
          </tbody></table></div>
      </div>
    </div>
  </div>
</div>

{{-- ===== المعاملات والإجراءات ===== --}}
<div @if($tab!=='transactions') style="display:none" @endif>
  <div class="row g-3 mb-3">
    {{-- دفعة --}}
    <div class="col-12 col-md-4">
      <div class="card p-3 h-100">
        <div class="fw-bold mb-2" style="color:var(--brand-deep)"><i class="fas fa-money-bill-wave"></i> تسجيل دفعة</div>
        <form method="post" action="{{ route('seller.tx.store') }}">@csrf<input type="hidden" name="type" value="payment">
          <select name="user_id" class="form-select mb-2" required>
            <option value="">— المندوب —</option>
            @foreach($sellers as $s)<option value="{{ $s->user_id }}">{{ $s->user_name }} (UID {{ $s->user_id }})</option>@endforeach
          </select>
          <input type="number" step="0.01" min="0" name="amount" class="form-control mb-2" placeholder="المبلغ JOD" required>
          <input type="text" name="note" class="form-control mb-2" placeholder="ملاحظة (اختياري)">
          <button class="btn btn-brand w-100"><i class="fas fa-check"></i> حفظ الدفعة</button>
        </form>
      </div>
    </div>
    {{-- استرداد --}}
    <div class="col-12 col-md-4">
      <div class="card p-3 h-100">
        <div class="fw-bold mb-2" style="color:var(--brand-deep)"><i class="fas fa-rotate-left"></i> استرداد بطاقة</div>
        <form method="post" action="{{ route('seller.tx.store') }}">@csrf<input type="hidden" name="type" value="refund">
          <select name="user_id" class="form-select mb-2" required>
            <option value="">— المندوب —</option>
            @foreach($sellers as $s)<option value="{{ $s->user_id }}">{{ $s->user_name }} (UID {{ $s->user_id }})</option>@endforeach
          </select>
          <input type="text" name="card_ref" class="form-control mb-2" placeholder="كود البطاقة أو رقمها" required>
          <input type="text" name="note" class="form-control mb-2" placeholder="ملاحظة (اختياري)">
          <button class="btn btn-outline-danger w-100"><i class="fas fa-rotate-left"></i> استرداد</button>
        </form>
      </div>
    </div>
    {{-- تعديل فاتورة --}}
    <div class="col-12 col-md-4">
      <div class="card p-3 h-100">
        <div class="fw-bold mb-2" style="color:var(--brand-deep)"><i class="fas fa-pen-to-square"></i> تعديل فاتورة</div>
        <form method="post" action="{{ route('seller.tx.store') }}">@csrf<input type="hidden" name="type" value="adjust_invoice">
          <select name="user_id" class="form-select mb-2" required>
            <option value="">— المندوب —</option>
            @foreach($sellers as $s)<option value="{{ $s->user_id }}">{{ $s->user_name }} (UID {{ $s->user_id }})</option>@endforeach
          </select>
          <input type="text" name="adjust_doc_code" class="form-control mb-2" placeholder="رقم الفاتورة (TX-...)" required>
          <input type="number" step="0.01" min="0" name="new_amount" class="form-control mb-2" placeholder="المبلغ الجديد" required>
          <input type="text" name="note" class="form-control mb-2" placeholder="ملاحظة (اختياري)">
          <button class="btn btn-outline-warning w-100"><i class="fas fa-pen"></i> تطبيق التعديل</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card p-0">
    <div class="p-3 fw-bold" style="color:var(--brand-deep)">المعاملات</div>
    <div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>المستند</th><th>المندوب</th><th>النوع</th><th>المبلغ</th><th>قبل/بعد</th><th>ملاحظة</th><th>التاريخ</th></tr></thead>
      <tbody>
      @forelse($transactions as $t)
        <tr>
          <td class="text-muted small">{{ $t->doc_code }}</td>
          <td>{{ $t->user_name }}<div class="text-muted small">UID {{ $t->user_id }}</div></td>
          <td><span class="tt tt-{{ $t->type }}">{{ $labels[$t->type] ?? $t->type }}</span></td>
          <td class="fw-bold">{{ number_format((float)$t->amount,2) }}</td>
          <td class="text-muted small">{{ number_format((float)$t->balance_before,2) }} → {{ number_format((float)$t->balance_after,2) }}</td>
          <td class="small" style="max-width:260px">{{ $t->note }}</td>
          <td class="text-muted small" dir="ltr">{{ \Illuminate\Support\Str::of((string)$t->created_at)->limit(16,'') }}</td>
        </tr>
      @empty<tr><td colspan="7" class="text-center text-muted py-4">لا معاملات.</td></tr>@endforelse
      </tbody></table></div>
    <div class="p-3">{{ $transactions->links() }}</div>
  </div>
</div>
@endsection
