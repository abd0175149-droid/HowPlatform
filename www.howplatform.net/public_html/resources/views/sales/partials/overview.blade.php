{{-- resources/views/sales/partials/overview.blade.php --}}
<style>
/* ============ POS Overview — Scoped Styles (Aligned with points) ============ */
.pos-overview{color:var(--text,#0d2a3a)}
.pos-overview .container-narrow{max-width:var(--maxw,1100px)}

/* Cards */
.pos-overview .hp-card{
  background:#fff;
  border:1px solid rgba(0,0,0,.06);
  border-radius:var(--radius-lg,16px);
  padding:18px;
  box-shadow:var(--shadow-1,0 6px 16px rgba(0,0,0,.08));
}

/* KPIs */
.pos-overview .hp-kpi{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:.35rem;min-height:112px
}
.pos-overview .hp-kpi h5{margin:0;color:var(--primary-800,#104b65);font-weight:800}
.pos-overview .hp-kpi h2{margin:0;font-weight:900;color:var(--primary-900,#0a3247)}
.pos-overview .hp-kpi small{font-weight:700;opacity:.9;color:var(--primary-700,#156683)}

/* Section title (accent edge) */
.pos-overview .hp-section-title{
  margin: 10px 0 12px;
  font-weight:900;
  color:var(--primary-50,#0a3247);
  display:flex;align-items:center;gap:.5rem;
  padding-inline-start:.6rem;
  border-inline-start:6px solid var(--accent-500,#fd9e02);
}

/* Table (glass-head style consistent with points) */
.pos-overview .hp-table{
  width:100%;
  border-collapse:separate;border-spacing:0;
  overflow:hidden;border-radius:14px;background:#fff
}
.pos-overview .hp-table thead th{
  /* تدرّج primary الداكن + نص معكوس لمطابقة نقاط البيع */
  background:linear-gradient(135deg,var(--primary-800,#104b65),var(--primary-900,#0a3247));
  color:var(--text-inverse,#fff);
  font-weight:800;text-align:right;padding:.9rem;
  border-bottom:1px solid rgba(0,0,0,.06)
}
.pos-overview .hp-table tbody td{padding:.85rem;border-bottom:1px solid rgba(0,0,0,.06);color:var(--text,#0d2a3a)}
.pos-overview .hp-table tbody tr:hover{background:var(--primary-50,#e8f5fb)}

/* Grid */
.pos-overview .hp-grid{display:grid;gap:14px}
@media(min-width:768px){.pos-overview .grid-3{grid-template-columns:repeat(3,1fr)}}
</style>
{{-- resources/views/sales/partials/overview.blade.php --}}
<div class="pos-overview container container-narrow py-3" dir="rtl">

  {{-- كروت المؤشرات الرئيسية --}}
  <div class="hp-grid grid-3 mb-3">
    <div class="hp-card">
      <div class="hp-kpi text-center">
        <h5 class="text-muted"><i class="fa-solid fa-ticket me-1"></i> إجمالي الفاوتشرات</h5>
        <h2>{{ number_format($totalVouchers ?? 0) }}</h2>
      </div>
    </div>

    <div class="hp-card">
      <div class="hp-kpi text-center">
        <h5 class="text-muted"><i class="fa-solid fa-sack-dollar me-1"></i> إجمالي المبيعات</h5>
        <h2>
          {{ number_format($totalSales ?? 0, 2) }}
          <small>JOD</small>
        </h2>
      </div>
    </div>

    <div class="hp-card">
      <div class="hp-kpi text-center">
        <h5 class="text-muted"><i class="fa-solid fa-chart-line me-1"></i> مؤشر الأداء</h5>
        @php $perf = ($totalSales ?? 0) > 0 ? 100 : 0; @endphp
        <h2>{{ number_format($perf, 0) }}<small>%</small></h2>
      </div>
    </div>
  </div>

  {{-- المبيعات حسب المنصة --}}
  <h4 class="hp-section-title"><i class="fa-solid fa-globe"></i> المبيعات حسب المنصة</h4>
  <div class="hp-card mb-3">
    <div class="table-responsive">
      <table class="hp-table text-center align-middle">
        <thead>
          <tr>
            <th>المنصة</th>
            <th>عدد الفاوتشرات</th>
            <th>إجمالي المبيعات (JOD)</th>
          </tr>
        </thead>
        <tbody>
          @forelse($salesByPlatform as $row)
            <tr>
              <td>{{ $row->platform }}</td>
              <td>{{ number_format($row->count ?? 0) }}</td>
              <td>{{ number_format($row->total ?? 0, 2) }}</td>
            </tr>
          @empty
            <tr><td colspan="3" class="text-muted">لا توجد بيانات بعد</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(method_exists($salesByPlatform, 'hasPages') && $salesByPlatform->hasPages())
      <div class="d-flex justify-content-center mt-2">
        {{ $salesByPlatform->onEachSide(1)->links('pagination::how') }}
      </div>
    @endif
  </div>

  {{-- أفضل البائعين (مع إظهار ID) --}}
  <h4 class="hp-section-title"><i class="fa-solid fa-user-tie"></i> أفضل البائعين</h4>
  <div class="hp-card">
    <div class="table-responsive">
      <table class="hp-table text-center align-middle">
        <thead>
          <tr>
            <th>الاسم</th>
            <th>عدد الفاوتشرات</th>
            <th>إجمالي المبيعات (JOD)</th>
          </tr>
        </thead>
        <tbody>
          @forelse($salesByUser as $row)
            <tr>
              <td>
                {{ $row->name }}
                @if(!empty($row->user_id))
                  <small class="text-muted">(#{{ $row->user_id }})</small>
                @endif
              </td>
              <td>{{ number_format($row->count ?? 0) }}</td>
              <td>{{ number_format($row->total ?? 0, 2) }}</td>
            </tr>
          @empty
            <tr><td colspan="3" class="text-muted">لا توجد بيانات بعد</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(method_exists($salesByUser, 'hasPages') && $salesByUser->hasPages())
      <div class="d-flex justify-content-center mt-2">
        {{ $salesByUser->onEachSide(1)->links('pagination::how') }}
      </div>
    @endif
  </div>
</div>
