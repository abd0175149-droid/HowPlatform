@extends('layouts.admin')
@section('title', 'نقاط البيع والسقوف')
@section('heading', 'نقاط البيع والسقوف')

@push('styles')
<style>
  .seg{display:inline-flex;background:#e9eff3;border-radius:12px;padding:.25rem;margin-bottom:1rem;}
  .seg a{padding:.4rem 1rem;border-radius:9px;font-weight:700;color:#5d8092;font-size:.85rem;}
  .seg a.on{background:var(--accent);color:#fff;}
  .sp-img{width:42px;height:42px;border-radius:10px;object-fit:cover;background:#e9eff3;}
</style>
@endpush

@section('content')
@php $tab = request('tab','points'); @endphp

<div class="seg">
  <a href="?tab=points" class="{{ $tab==='points' ? 'on':'' }}">نقاط البيع</a>
  <a href="?tab=limits" class="{{ $tab==='limits' ? 'on':'' }}">السقوف والأرصدة</a>
</div>

{{-- ================= نقاط البيع ================= --}}
<div @if($tab!=='points') style="display:none" @endif>
  <div class="card p-3 p-md-4 mb-3">
    <div class="fw-bold mb-3" style="color:var(--brand-deep)"><i class="fas fa-plus-circle"></i> إضافة نقطة بيع (مندوب)</div>
    <form method="post" action="{{ route('seller.points.store') }}" enctype="multipart/form-data" class="row g-3">
      @csrf
      <div class="col-6 col-md-3">
        <label class="form-label small">user_id العالمي</label>
        <input type="number" name="user_id" class="form-control" required>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">اسم المندوب</label>
        <input type="text" name="user_name" class="form-control" required>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">اسم المتجر</label>
        <input type="text" name="store_name" class="form-control" required>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">الهاتف</label>
        <input type="text" name="phone" class="form-control" required>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">طريقة البيع</label>
        <select name="sell_method" class="form-select" required>
          <option value="both">الاثنان</option>
          <option value="online">أونلاين</option>
          <option value="in_person">حضوري</option>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">العمولة %</label>
        <input type="number" step="0.01" min="0" max="100" name="discount_percent" class="form-control" value="0">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">المحافظة</label>
        <select name="governorate" class="form-select">
          <option value="">—</option>
          @foreach($governorates as $g)<option>{{ $g }}</option>@endforeach
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">صورة (اختياري)</label>
        <input type="file" name="image" accept="image/*" class="form-control">
      </div>
      <div class="col-12 col-md-9">
        <label class="form-label small">العنوان / رابط الموقع</label>
        <input type="text" name="address_details" class="form-control" placeholder="تفاصيل العنوان">
      </div>
      <div class="col-12 col-md-3 d-flex align-items-end">
        <button class="btn btn-brand w-100"><i class="fas fa-save"></i> حفظ</button>
      </div>
      <input type="hidden" name="location_url" value="">
    </form>
  </div>

  <div class="card p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr>
          <th>#</th><th>المندوب</th><th>المتجر</th><th>الهاتف</th><th>الطريقة</th><th>العمولة</th><th>المحافظة</th><th></th>
        </tr></thead>
        <tbody>
        @forelse($points as $p)
          <tr>
            <td class="text-muted">{{ $p->id }}</td>
            <td>
              <div class="d-flex align-items-center gap-2">
                @if($p->image_url)<img src="{{ $p->image_url }}" class="sp-img">@else<div class="sp-img"></div>@endif
                <div><div class="fw-bold">{{ $p->user_name }}</div><div class="text-muted small">UID {{ $p->user_id }}</div></div>
              </div>
            </td>
            <td>{{ $p->store_name }}</td>
            <td dir="ltr" class="text-muted small">{{ $p->phone }}</td>
            <td><span class="badge badge-soft">{{ ['both'=>'الاثنان','online'=>'أونلاين','in_person'=>'حضوري'][$p->sell_method] ?? $p->sell_method }}</span></td>
            <td>{{ rtrim(rtrim(number_format((float)$p->discount_percent,2),'0'),'.') }}%</td>
            <td class="text-muted small">{{ $p->governorate ?: '—' }}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary editBtn"
                data-id="{{ $p->id }}" data-store="{{ $p->store_name }}" data-phone="{{ $p->phone }}"
                data-method="{{ $p->sell_method }}" data-discount="{{ $p->discount_percent }}"
                data-gov="{{ $p->governorate }}" data-addr="{{ $p->address_details }}"
                data-bs-toggle="modal" data-bs-target="#editPoint"><i class="fas fa-pen"></i></button>
              <form method="post" action="{{ route('seller.points.delete', $p->id) }}" class="d-inline" onsubmit="return confirm('حذف نقطة البيع؟')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">لا توجد نقاط بيع بعد.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-3">{{ $points->links() }}</div>
  </div>
</div>

{{-- ================= السقوف والأرصدة ================= --}}
<div @if($tab!=='limits') style="display:none" @endif>
  <div class="card p-3 p-md-4 mb-3">
    <div class="fw-bold mb-3" style="color:var(--brand-deep)"><i class="fas fa-gauge"></i> تعيين سقف شهري</div>
    <form method="post" action="{{ route('seller.limits.upsert') }}" class="row g-3">
      @csrf
      <div class="col-6 col-md-4">
        <label class="form-label small">user_id المندوب</label>
        <input type="number" name="user_id" class="form-control" required>
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label small">السقف الشهري (JOD، 0 = بلا سقف)</label>
        <input type="number" step="0.01" min="0" name="monthly_limit" class="form-control" required>
      </div>
      <div class="col-12 col-md-4 d-flex align-items-end">
        <button class="btn btn-brand w-100"><i class="fas fa-save"></i> حفظ السقف</button>
      </div>
    </form>
  </div>

  <div class="card p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>المندوب</th><th>رصيد {{ $periodKey }}</th><th>السقف</th><th>المتاح</th><th></th></tr></thead>
        <tbody>
        @forelse($stats as $s)
          @php
            $bal = ((string)$s->balance_month_key === $periodKey) ? (float)$s->balance : 0.0;
            $lim = (float)($s->monthly_limit ?? 0);
            $avail = $lim > 0 ? max(0, $lim - $bal) : null;
          @endphp
          <tr>
            <td><div class="fw-bold">{{ $s->user_name }}</div><div class="text-muted small">UID {{ $s->user_id }}</div></td>
            <td>{{ number_format($bal,2) }} JOD</td>
            <td>{{ $lim > 0 ? number_format($lim,2).' JOD' : '∞' }}</td>
            <td>{{ is_null($avail) ? '∞' : number_format($avail,2).' JOD' }}</td>
            <td class="text-end">
              <form method="post" action="{{ route('seller.limits.delete', $s->user_id) }}" class="d-inline" onsubmit="return confirm('حذف السقف؟')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> حذف السقف</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-4">لا يوجد مندوبون بعد.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- مودال تعديل نقطة البيع --}}
<div class="modal fade" id="editPoint" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="editForm" method="post" enctype="multipart/form-data">
      @csrf @method('PUT')
      <div class="modal-header"><h5 class="modal-title">تعديل نقطة البيع</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12"><label class="form-label small">اسم المتجر</label><input name="store_name" class="form-control" required></div>
          <div class="col-6"><label class="form-label small">الهاتف</label><input name="phone" class="form-control" required></div>
          <div class="col-6"><label class="form-label small">العمولة %</label><input type="number" step="0.01" min="0" max="100" name="discount_percent" class="form-control"></div>
          <div class="col-6"><label class="form-label small">طريقة البيع</label>
            <select name="sell_method" class="form-select"><option value="both">الاثنان</option><option value="online">أونلاين</option><option value="in_person">حضوري</option></select>
          </div>
          <div class="col-6"><label class="form-label small">المحافظة</label>
            <select name="governorate" class="form-select"><option value="">—</option>@foreach($governorates as $g)<option>{{ $g }}</option>@endforeach</select>
          </div>
          <div class="col-12"><label class="form-label small">العنوان</label><input name="address_details" class="form-control"></div>
          <div class="col-12"><label class="form-label small">صورة جديدة (اختياري)</label><input type="file" name="image" accept="image/*" class="form-control"></div>
          <input type="hidden" name="location_url" value="">
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-brand"><i class="fas fa-save"></i> حفظ</button></div>
    </form>
  </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.editBtn').forEach(b => b.addEventListener('click', () => {
  const f = document.getElementById('editForm');
  f.action = '{{ url('seller/points') }}/' + b.dataset.id;
  f.store_name.value = b.dataset.store || '';
  f.phone.value = b.dataset.phone || '';
  f.discount_percent.value = b.dataset.discount || '0';
  f.sell_method.value = b.dataset.method || 'both';
  f.governorate.value = b.dataset.gov || '';
  f.address_details.value = b.dataset.addr || '';
}));
</script>
@endpush
@endsection
