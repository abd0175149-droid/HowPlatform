@extends('layouts.admin')
@section('title', 'المطالبات والمدفوعات')
@section('heading', 'المطالبات والمدفوعات')

@push('styles')
<style>
  .seg{display:inline-flex;background:#e9eff3;border-radius:12px;padding:.25rem;margin-bottom:1rem;}
  .seg a{padding:.4rem 1rem;border-radius:9px;font-weight:700;color:#5d8092;font-size:.85rem;}
  .seg a.on{background:var(--accent);color:#fff;}
  .st{font-size:.72rem;padding:.15rem .55rem;border-radius:20px;font-weight:700;}
  .st-open{background:#fff3e0;color:#b9770e;}
  .st-paid{background:#e7f5ec;color:#1c7a45;}
  .prog{height:8px;border-radius:6px;background:#e9eff3;overflow:hidden;}
  .prog>i{display:block;height:100%;background:var(--accent);}
</style>
@endpush

@section('content')
@php $tab = request('tab','claims'); @endphp

<div class="seg">
  <a href="?tab=claims" class="{{ $tab==='claims' ? 'on':'' }}">إنشاء مطالبة</a>
  <a href="?tab=pay" class="{{ $tab==='pay' ? 'on':'' }}">تسجيل دفعة</a>
</div>

{{-- ================= إنشاء مطالبة ================= --}}
<div @if($tab!=='claims') style="display:none" @endif>
  <div class="card p-3 p-md-4 mb-3">
    <div class="fw-bold mb-3" style="color:var(--brand-deep)"><i class="fas fa-file-invoice-dollar"></i> إنشاء مطالبة من مبيعات البطاقات غير المسوّاة</div>
    <form method="post" action="{{ route('seller.claims.store') }}" class="row g-3">
      @csrf
      <div class="col-12 col-md-4">
        <label class="form-label small">المندوب</label>
        <select name="user_id" id="clmSeller" class="form-select" required>
          <option value="">— المندوب —</option>
          @foreach($sellers as $s)<option value="{{ $s->user_id }}">{{ $s->user_name }} (UID {{ $s->user_id }})</option>@endforeach
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">من تاريخ (اختياري)</label>
        <input type="date" name="from" id="clmFrom" class="form-control">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">إلى تاريخ (اختياري)</label>
        <input type="date" name="to" id="clmTo" class="form-control">
      </div>
      <div class="col-12 col-md-2 d-flex align-items-end gap-2">
        <button type="button" id="clmPreview" class="btn btn-outline-secondary w-100"><i class="fas fa-eye"></i> معاينة</button>
      </div>
      <div class="col-12">
        <div id="clmResult" class="text-muted small">اختر المندوب ثم اضغط معاينة لرؤية عدد البطاقات وإجماليها غير المسوّاة.</div>
      </div>
      <div class="col-12">
        <button class="btn btn-brand"><i class="fas fa-plus-circle"></i> إنشاء المطالبة</button>
      </div>
    </form>
  </div>
</div>

{{-- ================= تسجيل دفعة ================= --}}
<div @if($tab!=='pay') style="display:none" @endif>
  <div class="card p-3 p-md-4 mb-3">
    <div class="fw-bold mb-3" style="color:var(--brand-deep)"><i class="fas fa-money-bill-wave"></i> تسجيل دفعة وتوزيعها على مطالبة</div>
    <form method="post" action="{{ route('seller.payments.store') }}" class="row g-3">
      @csrf
      <div class="col-12 col-md-4">
        <label class="form-label small">المندوب</label>
        <select name="user_id" class="form-select" required>
          <option value="">— المندوب —</option>
          @foreach($sellers as $s)<option value="{{ $s->user_id }}">{{ $s->user_name }} (UID {{ $s->user_id }})</option>@endforeach
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">المبلغ (JOD)</label>
        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small">الطريقة (اختياري)</label>
        <input type="text" name="method" class="form-control" placeholder="نقدي / حوالة">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">رقم المطالبة (اختياري)</label>
        <input type="number" min="1" name="claim_id" class="form-control" placeholder="claim_id">
      </div>
      <div class="col-12">
        <label class="form-label small">ملاحظة (اختياري)</label>
        <input type="text" name="note" class="form-control">
      </div>
      <div class="col-12">
        <button class="btn btn-brand"><i class="fas fa-check"></i> حفظ الدفعة</button>
      </div>
    </form>
  </div>
</div>

{{-- ================= آخر المطالبات ================= --}}
<div class="card p-0">
  <div class="p-3 fw-bold" style="color:var(--brand-deep)">آخر المطالبات</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>الكود</th><th>المندوب</th><th>الفترة</th><th>العدد</th><th>الإجمالي</th><th>المسدّد</th><th>الحالة</th><th></th></tr></thead>
      <tbody>
      @forelse($claims as $c)
        @php
          $total = (float)$c->invoices_total;
          $paid  = (float)$c->paid_total;
          $pct   = $total > 0 ? min(100, round($paid / $total * 100)) : 0;
        @endphp
        <tr>
          <td class="fw-bold">{{ $c->claim_code }}</td>
          <td>{{ $c->user_name }}<div class="text-muted small">UID {{ $c->user_id }}</div></td>
          <td class="text-muted small" dir="ltr">{{ $c->date_from ?: '—' }} → {{ $c->date_to ?: '—' }}</td>
          <td>{{ number_format($c->invoices_count) }}</td>
          <td>{{ number_format($total,2) }} JOD</td>
          <td style="min-width:130px">
            <div class="small mb-1">{{ number_format($paid,2) }} / {{ number_format($total,2) }}</div>
            <div class="prog"><i style="width:{{ $pct }}%"></i></div>
          </td>
          <td><span class="st st-{{ $c->status }}">{{ $c->status === 'paid' ? 'مدفوعة' : 'مفتوحة' }}</span></td>
          <td class="text-end">
            <a href="{{ route('seller.claims.show', $c->id) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
            <a href="{{ route('seller.claims.print', $c->id) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print"></i></a>
          </td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-muted py-4">لا توجد مطالبات بعد.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3">{{ $claims->links() }}</div>
</div>

@push('scripts')
<script>
document.getElementById('clmPreview')?.addEventListener('click', async () => {
  const uid = document.getElementById('clmSeller').value;
  const out = document.getElementById('clmResult');
  if (!uid) { out.textContent = 'اختر المندوب أولًا.'; return; }
  out.textContent = 'جارٍ المعاينة…';
  const params = new URLSearchParams({ user_id: uid });
  const from = document.getElementById('clmFrom').value;
  const to   = document.getElementById('clmTo').value;
  if (from) params.set('from', from);
  if (to) params.set('to', to);
  try {
    const res = await fetch('{{ route('seller.claims.preview') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } });
    const j = await res.json();
    out.innerHTML = '<span class="fw-bold" style="color:var(--brand-deep)">' + j.count + '</span> بطاقة غير مسوّاة بإجمالي <span class="fw-bold" style="color:var(--brand-deep)">' + Number(j.total).toFixed(2) + ' JOD</span>.';
  } catch (e) {
    out.textContent = 'تعذّر المعاينة.';
  }
});
</script>
@endpush
@endsection
