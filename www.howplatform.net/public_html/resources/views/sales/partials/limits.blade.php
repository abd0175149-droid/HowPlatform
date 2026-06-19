{{-- resources/views/sales/partials/limits.blade.php --}}
<style>
.pos-limits{color:var(--text,#0d2a3a)}
.pos-limits .container-narrow{max-width:var(--maxw,1100px)}
.pos-limits .hp-section-title{
  margin:10px 0 12px;font-weight:900;color:var(--primary-900,#0a3247);
  display:flex;align-items:center;gap:.5rem;padding-inline-start:.6rem;
  border-inline-start:6px solid var(--accent-500,#fd9e02)
}
.pos-limits .hp-card{
  background:#fff;border:1px solid rgba(0,0,0,.06);
  border-radius:16px;padding:18px;box-shadow:0 6px 16px rgba(0,0,0,.08)
}
.pos-limits .hp-table{
  width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border-radius:14px;background:#fff
}
.pos-limits .hp-table thead th{
  background:linear-gradient(135deg,var(--primary-800,#104b65),var(--primary-900,#0a3247));
  color:#fff;font-weight:800;text-align:right;padding:.9rem;border-bottom:1px solid rgba(0,0,0,.06)
}
.pos-limits .hp-table th,.pos-limits .hp-table td{vertical-align:middle;text-align:center}
.pos-limits .hp-table tbody td{padding:.85rem;border-bottom:1px solid rgba(0,0,0,.06)}
.pos-limits .hp-table tbody tr:nth-child(even){background:var(--primary-50,#e8f5fb)}
.pos-limits .hp-table tbody tr:hover{background:color-mix(in srgb, var(--primary-50,#e8f5fb) 70%, #fff)}
.pos-limits .btn-primary{
  background:var(--primary-600,#1b81a0);border-color:var(--primary-600,#1b81a0);font-weight:800
}
.pos-limits .btn-primary:hover{
  background:var(--primary-700,#156683);border-color:var(--primary-700,#156683)
}
</style>

{{-- resources/views/sales/partials/limits.blade.php --}}
<section class="pos-limits container container-narrow py-3" dir="rtl">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="hp-section-title m-0">
      <i class="fa-solid fa-bullseye me-2"></i> سقوف المبيعات
    </h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#limitModal"
            data-user_id="" data-user_name="" data-monthly_limit="">
      <i class="fa-solid fa-plus me-1"></i> تعيين سقف
    </button>
  </div>

  <div class="hp-card">
    <div class="table-responsive">
      <table id="salesLimitsTable" class="hp-table text-center align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>المندوب</th>
            <th>الرصيد الحالي</th>
            <th>السقف الشهري (JOD)</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
@php
  $statsMap = collect($userStats ?? [])->mapWithKeys(function($r){
    return [
      $r->user_id => [
        'id'    => $r->user_id,
        'name'  => $r->user_name,
        'bal'   => (float) $r->balance,
        'limit' => (float) ($r->monthly_limit ?? 0),
      ]
    ];
  })->all();
@endphp

          @forelse(($salesLimits ?? collect()) as $row)
            @php
              $st  = $statsMap[$row->user_id] ?? null;
              $bal = $st['bal'] ?? 0;
            @endphp
            <tr>
              <td>{{ $row->id }}</td>
              <td>
                {{ $row->user_name }}
                <small class="text-muted">(# {{ $row->user_id }})</small>
              </td>
              <td>{{ number_format($bal, 2) }}</td>
              <td>{{ number_format((float)$row->monthly_limit, 2) }}</td>
              <td>
                <button class="btn btn-sm btn-warning"
                        data-bs-toggle="modal" data-bs-target="#limitModal"
                        data-user_id="{{ $row->user_id }}"
                        data-user_name="{{ $row->user_name }}"
                        data-monthly_limit="{{ (float)$row->monthly_limit }}"
                        title="تعديل سقف: {{ $row->user_name }} (# {{ $row->user_id }})">
                  <i class="fas fa-edit"></i>
                </button>

                <form class="d-inline" method="POST" action="{{ route('pos.sales-limits.delete', $row->user_id) }}"
                      onsubmit="return confirm('تأكيد حذف سقف هذا المندوب؟');">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-danger" title="حذف سقف: {{ $row->user_name }} (# {{ $row->user_id }})">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-muted">لا توجد بيانات بعد</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  {{-- ترقيم HOW الزجاجي --}}
    @if(method_exists($salesLimits ?? null, 'hasPages') && $salesLimits->hasPages())
      <div class="d-flex justify-content-center mt-2">
        {{ $salesLimits->onEachSide(1)->links('pagination::how') }}
      </div>
    @endif
</section>

<!-- Modal -->
<div class="modal fade" id="limitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST" action="{{ route('pos.sales-limits.upsert') }}">
      @csrf
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold text-dark">تعديل سقف المبيعات</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body bg-white text-dark">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">المندوب</label>
            @if(!empty($salesUsers) && count($salesUsers) > 0)
              <select class="form-select" name="user_id" id="limit_user_id_select" required>
                <option value="">-- اختر المندوب --</option>
                @foreach($salesUsers as $u)
                  @php
                    $uid   = $u['id'] ?? $u['user_id'] ?? '';
                    $uname = $u['name'] ?? ($u['user_name'] ?? ('#'.$uid));
                  @endphp
                  <option value="{{ $uid }}">{{ $uname }} (# {{ $uid }})</option>
                @endforeach
              </select>
            @elseif(!empty($limitUsers) && count($limitUsers) > 0)
              <select class="form-select" name="user_id" id="limit_user_id_select" required>
                <option value="">-- اختر المندوب --</option>
                @foreach($limitUsers as $u)
                  <option value="{{ $u->id }}">{{ $u->name }} (# {{ $u->id }})</option>
                @endforeach
              </select>
            @else
              <input type="number" class="form-control" name="user_id" id="limit_user_id_input" placeholder="ID" required>
            @endif
            <div class="form-text" id="limit_user_hint"></div>
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold">السقف الشهري (JOD)</label>
            <input type="number" step="0.01" min="0" class="form-control" name="monthly_limit" id="limit_monthly_limit" required>
            <div class="form-text" id="limit_balance_hint"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="submit" class="btn btn-success"><i class="fa-solid fa-save me-1"></i> حفظ</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
@php
  $__STATS = collect($userStats ?? [])->mapWithKeys(function($r){
    return [
      $r->user_id => [
        'id'    => $r->user_id,
        'name'  => $r->user_name,
        'bal'   => round((float)$r->balance, 2),
        'limit' => round((float)($r->monthly_limit ?? 0), 2),
      ]
    ];
  })->toArray();
@endphp
<script>
document.addEventListener('DOMContentLoaded', function(){
  // JSON آمن
  const STATS = {!! json_encode($__STATS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};

  function updateHints(uid){
    const info = STATS[uid] || null;
    const hint1 = document.getElementById('limit_user_hint');
    const hint2 = document.getElementById('limit_balance_hint');
    if (!info){ hint1.textContent=''; hint2.textContent=''; return; }
    const remaining = Math.max(0, (info.limit || 0) - (info.bal || 0));
    hint1.textContent = `المندوب: ${info.name || ''} (# ${uid || ''})`;
    hint2.textContent = `الرصيد الحالي: ${Number(info.bal).toFixed(2)} — السقف: ${Number(info.limit).toFixed(2)} — المتاح: ${remaining.toFixed(2)} JOD`;
  }

  const modalEl = document.getElementById('limitModal');
  modalEl.addEventListener('show.bs.modal', function (ev) {
    const btn = ev.relatedTarget;
    const sel = document.getElementById('limit_user_id_select');
    const inp = document.getElementById('limit_user_id_input');
    const amt = document.getElementById('limit_monthly_limit');
    const title = modalEl.querySelector('.modal-title');

    const uid   = btn?.dataset?.user_id || '';
    const uname = btn?.dataset?.user_name || '';
    const limit = btn?.dataset?.monthly_limit || '';

    if (sel){ sel.value = uid || ''; sel.dispatchEvent(new Event('change')); }
    if (inp){ inp.value = uid || ''; updateHints(inp.value); }
    if (amt){ amt.value = limit; }

    if (title){
      title.textContent = uid ? `تعيين سقف المبيعات — ${uname || 'مستخدم'} (# ${uid})` : 'تعيين/تعديل سقف المبيعات';
    }
  });

  document.addEventListener('change', function(e){
    if (e.target && e.target.id === 'limit_user_id_select'){
      updateHints(e.target.value);
    }
  });
});
</script>
@endpush
