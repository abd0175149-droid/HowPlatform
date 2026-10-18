@extends('layouts.user')
@section('title','محفظتي | Plans')

@section('content')
  <div class="stat-grid mb-3">
    <div class="stat-tile">
      <div>
        <div class="st-label">الرصيد الحالي</div>
        <div class="st-value">{{ number_format($balance) }}</div>
        <div class="st-sub">نقطة</div>
      </div>
      <div class="st-ic" style="background:linear-gradient(135deg,#38bdf8,#0ea5e9);"><i class="fas fa-wallet"></i></div>
    </div>
  </div>

  <div class="profile-section-card active">
    <div class="section-card-header">
      <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fas fa-list"></i></span><span>سجلّ الحركات</span></h3>
    </div>
    <div class="section-card-body">
      <div class="table-responsive">
        <table class="acc-table">
          <thead><tr><th>الوصف</th><th>النوع</th><th>النقاط</th><th>الرصيد بعدها</th><th>التاريخ</th></tr></thead>
          <tbody>
            @forelse($ledger as $row)
              <tr>
                <td>{{ $row->description ?: '—' }}</td>
                <td><span class="badge-soft" style="padding:.15rem .6rem;border-radius:999px;font-size:.75rem;font-weight:700;">{{ $row->kind }}</span></td>
                <td class="{{ $row->points >= 0 ? 'text-success' : 'text-danger' }} fw-bold">{{ $row->points >= 0 ? '+' : '' }}{{ number_format($row->points) }}</td>
                <td>{{ number_format($row->balance_after) }}</td>
                <td class="text-muted small">{{ $row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at)->format('Y-m-d H:i') : '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted py-3">لا توجد حركات بعد.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="mt-3">{{ $ledger->links() }}</div>
    </div>
  </div>
@endsection
