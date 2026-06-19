@extends('layouts.tools')

@section('title', '📈 تقرير الأداء الشامل')

@section('content')
<div class="tools-wrapper wide">
  <h1 class="page-title">📈 تقرير الأداء الشامل</h1>

  {{-- ===== فلاتر DDL ===== --}}
  <div class="card header-unified">
    <div class="row-1">
      <div class="fg">
        <label>الأسبوع</label>
        <select id="weekFilter" class="form-input">
          <option value="">الكل</option>
        </select>
      </div>

      <div class="fg">
        <label>المهمة</label>
        <select id="taskFilter" class="form-input" disabled>
          <option value="">الكل</option>
        </select>
      </div>

      <div class="fg">
        <label>المجموعة</label>
        <select id="groupSelect" class="form-input">
          <option value="">الكل</option>
        </select>
      </div>

      <div class="fg grow">
        <label>بحث باسم الطالب</label>
        <input type="text" id="studentSearch" class="form-input" placeholder="اكتب اسم الطالب…">
      </div>

      <div class="fg">
        <label>الحالة</label>
        <select id="statusSelect" class="form-input">
          <option value="">الكل</option>
          <option value="on_time">في الموعد</option>
          <option value="late">متأخر</option>
          <option value="not_submitted">غير مُسلّم</option>
        </select>
      </div>

      <div class="fg">
        <label>نطاق العلامة</label>
        <div style="display:flex; gap:.5rem;">
          <input type="number" id="gradeMin" class="form-input form-input-sm" placeholder="من" min="0" max="100" step="0.1">
          <input type="number" id="gradeMax" class="form-input form-input-sm" placeholder="إلى" min="0" max="100" step="0.1">
        </div>
        <small class="hint">تطبق على القيم الرقمية فقط.</small>
      </div>
    </div>

    <div class="row-2">
      <div class="report-actions" style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <button id="printPDF"   class="btn btn-accent"><i class="fa-solid fa-file-pdf"></i> طباعة PDF</button>
        <button id="exportExcel" class="btn btn-primary"><i class="fa-solid fa-table"></i> تصدير Excel</button>
      </div>
      <div class="spacer"></div>
      <div class="table-footer left">
        <label>عدد الصفوف:</label>
        <select id="pageSizeSel" class="form-input form-input-sm">
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
        </select>
      </div>
    </div>
  </div>

  {{-- ===== جدول تفصيلي ===== --}}
  <div class="card">
    <div class="table-scroller">
      <table id="reportTable" class="tracking-table" dir="rtl">
        <thead>
          <tr>
            <th>الطالب</th>
            <th>الأسبوع</th>
            <th>المهمة</th>
            <th>الحالة</th>
            <th>العلامة</th>
          </tr>
        </thead>
        <tbody id="reportBody"></tbody>
      </table>
    </div>

    {{-- ترقيم --}}
    <div class="table-footer">
      <div></div>
      <div class="right pagination">
        <button id="pgPrev" class="btn btn-ghost" title="السابق">‹</button>
        <span id="pgNums" class="pg-nums"></span>
        <button id="pgNext" class="btn btn-ghost" title="التالي">›</button>
        <span id="pageInfo" class="hint"></span>
      </div>
    </div>
  </div>

  {{-- ===== إحصاءات مقارنة حسب المهمة ===== --}}
  <div class="card" style="padding:1rem;">
    <h3 style="margin:.2rem 0 1rem;">إحصاءات مقارنة حسب المهمة (وفق الفلاتر)</h3>
    <div class="table-scroller">
      <table class="tracking-table" dir="rtl">
        <thead>
          <tr>
            <th>المهمة</th>
            <th>الأسبوع</th>
            <th>إجمالي الطلبة</th>
            <th>في الموعد</th>
            <th>متأخر</th>
            <th>غير مُسلّمين</th>
            <th>مُقيَّمون</th>
            <th>بحاجة لتقييم</th>
          </tr>
        </thead>
        <tbody id="aggBody"></tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  .tools-wrapper.wide{ max-width:100%; width:100%; }
  .header-unified{ padding:1rem .9rem }
  .row-1{ display:grid; grid-template-columns: 1fr 1fr 1fr 1.5fr 1fr 1fr; gap:.8rem; align-items:end; }
  @media (max-width:1200px){ .row-1{ grid-template-columns: 1fr 1fr 1fr; } }
  @media (max-width:720px){ .row-1{ grid-template-columns: 1fr; } }
  .row-2{ display:grid; grid-template-columns: 1fr auto; gap:1rem; align-items:center; margin-top:.75rem; }
  .fg{ display:flex; flex-direction:column; gap:.35rem; min-width:180px }
  .fg.grow{ flex:1; min-width:220px }
  .form-input{ padding:.7rem .9rem; border:1px solid #e2e8f0; border-radius:12px; background:#fff }
  .form-input-sm{ padding:.5rem .7rem; border-radius:10px; max-width:150px }
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:.65rem 1rem;border-radius:12px;border:0;cursor:pointer;font-weight:800;gap:.5rem}
  .btn-primary{background:var(--primary);color:#fff}
  .btn-accent{background:#0ea5e9;color:#fff}
  .btn-ghost{background:#fff;border:2px solid var(--primary);color:var(--primary);border-radius:12px}
  .tracking-table thead th{ position:sticky; top:0; z-index:6; background:#0f172a; color:#fff; border-color:#0b1220!important; font-weight:800; text-align:center; }
  .tracking-table td{ padding:.55rem .6rem; border-bottom:1px solid #eef2f7; text-align:center; }
  .status-on_time{ color:#16a34a; font-weight:800 }
  .status-late{ color:#f59e0b; font-weight:800 }
  .status-not_submitted{ color:#ef4444; font-weight:800 }
  .table-footer{ display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.6rem .4rem; border-top:1px solid #e6eef6; margin-top:.5rem; }
  .pg-nums button{ min-width: 34px; height: 34px; border-radius:10px; border:1px solid #e2e8f0; background:#fff; cursor:pointer; padding: 0 .5rem; font-weight:700; }
  .pg-nums button.active{ background: var(--primary); color:#fff; border-color: var(--primary); }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('assets/js/performance_reports.js') }}"></script>
@endpush
