@extends('layouts.tools')

@section('title','📊 لوحة متابعة الأسبوع')

@section('content')
<div class="tools-wrapper wide">

  {{-- ===== الهيدير الموحّد (فلترة + أدوات تطبيق) ===== --}}
  <div class="card header-unified">
    {{-- صف 1: الفلاتر الأساسية --}}
    <div class="row-1">
      <div class="fg">
        <label>الأسبوع التعليمي</label>
        <select id="weekSelect" class="form-input">
          <option value="">— اختر أسبوعًا —</option>
          @foreach($weeks as $w)
            <option value="{{ $w->id }}">أسبوع {{ $w->week_number }} — {{ $w->title }}</option>
          @endforeach
        </select>
      </div>

      <div class="fg">
        <label>فلترة مهمة (اختياري)</label>
        <select id="taskFilter" class="form-input" disabled>
          <option value="">— كل المهمات —</option>
        </select>
      </div>

      <div class="fg">
        <label>المجموعة</label>
        <select id="groupSelect" class="form-input">
          <option value="">الكل</option>
          @foreach($groups as $g)
            <option value="{{ $g->id }}">{{ $g->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="fg grow">
        <label>بحث باسم الطالب</label>
        <input type="text" id="studentSearch" class="form-input" placeholder="اكتب اسم الطالب…">
      </div>
    </div>

    {{-- صف 2: نطاق التطبيق + أزرار الحالة + تطبيق العلامة + حفظ --}}
    <div class="row-2">
      <div class="scope-card">
        <label class="pickall">
          <input type="checkbox" id="pickAllStudents">
          <span>تحديد كل الطلبة الظاهرين</span>
        </label>

        <div class="vsep"></div>

        <div class="scope">
          <label class="radio">
            <input type="radio" name="applyScope" value="selected" checked>
            <span>تطبيق على المحددين</span>
          </label>
          <label class="radio">
            <input type="radio" name="applyScope" value="visible">
            <span>تطبيق على الكل الظاهر</span>
          </label>
        </div>
      </div>

      <div class="apply-card">
        <button id="applyOnTime" class="pill pill-apply" title="تعيين: في الموعد">
          <i class="fa-solid fa-check"></i>
        </button>
        <button id="applyLate" class="pill pill-apply" title="تعيين: متأخر">
          <i class="fa-solid fa-clock"></i>
        </button>
        <button id="applyClear" class="pill pill-apply" title="مسح التسليم (غير مسلم)">
          <i class="fa-regular fa-circle"></i>
        </button>
      </div>

      <div class="grade-card">
        <label>تطبيق العلامة</label>
        <div class="grade-row">
          <input type="text" id="gradeApplyValue" class="form-input form-input-sm" placeholder="علامة (رقم/حرف)">
          <button id="applyGrade" class="btn btn-ghost">تطبيق</button>
        </div>
        <small class="hint">مسموح رقم بمنزلة أو حرف (A..F مع +/-)</small>
      </div>

      <div class="spacer"></div>

    <button id="bulkSave" class="btn btn-success btn-save">
      <i class="fa-solid fa-floppy-disk"></i>
      <span>حفظ التعديلات</span>
    </button>
    </div>
  </div>

  {{-- ===== الجدول ===== --}}
  <div id="trackingArea" class="card" hidden>
    <div class="table-scroller">
      <table class="tracking-table" id="trackingTable" dir="rtl">
        <thead>
          <tr id="trackingHead"></tr>
        </thead>
        <tbody id="trackingBody"></tbody>
      </table>
    </div>
        <!-- ===== شريط الترقيم ===== -->
    <div class="table-footer">
      <div class="left">
        <label>عدد الصفوف:</label>
        <select id="pageSizeSel" class="form-input form-input-sm">
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
        </select>
      </div>
    
      <div class="right pagination">
        <button id="pgPrev" class="btn btn-ghost" title="السابق">‹</button>
        <span id="pgNums" class="pg-nums"></span>
        <button id="pgNext" class="btn btn-ghost" title="التالي">›</button>
        <span id="pageInfo" class="hint"></span>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  .tools-wrapper.wide{ max-width:100%; width:100%; }

  /* ===== الهيدير الموحّد (تصميم نظيف ومتماسك) ===== */
  .header-unified{ padding:1rem .9rem }
  .row-1{
    display:grid;
    grid-template-columns: 1fr 1fr 1fr 1.5fr auto;
    gap:.8rem;
    align-items:end;
  }
  @media (max-width:1100px){
    .row-1{ grid-template-columns: 1fr 1fr 1fr; }
    .load-wrap{ grid-column: 1 / -1; max-width:220px }
  }
  @media (max-width:720px){
    .row-1{ grid-template-columns: 1fr; }
  }

  .fg{ display:flex; flex-direction:column; gap:.35rem; min-width:180px }
  .fg.grow{ flex:1; min-width:220px }
  .form-input{ padding:.7rem .9rem; border:1px solid #e2e8f0; border-radius:12px; background:#fff }
  .form-input-sm{ padding:.5rem .7rem; border-radius:10px; max-width:150px }

  .btn{display:inline-flex;align-items:center;justify-content:center;padding:.65rem 1rem;border-radius:12px;border:0;cursor:pointer;font-weight:800;gap:.5rem}
  .btn-primary{background:var(--primary);color:#fff}
  .btn-success{background:#16a34a;color:#fff}
  .btn-ghost{background:#fff;border:2px solid var(--primary);color:var(--primary);border-radius:12px}
  .w-100{ width:100% }

  .row-2{
    display:grid;
    grid-template-columns: auto auto 1fr auto;
    gap:1rem;
    align-items:center;
    margin-top:.75rem;
  }
  @media (max-width:980px){
    .row-2{ grid-template-columns: 1fr; }
    .grade-card{ max-width: 360px }
    #bulkSave{ justify-self:start }
  }

  .scope-card, .apply-card, .grade-card{
    display:flex; align-items:center; gap:.75rem;
    padding:.6rem .75rem; background:#fbfdff; border:1px solid #e6eef6; border-radius:12px;
  }
  .scope-card{ flex-wrap:wrap }
  .apply-card{ flex-wrap:wrap }
  .grade-card{ flex-direction:column; align-items:flex-start; gap:.35rem }
  .grade-row{ display:flex; gap:.5rem; align-items:center }

  .pickall{ display:flex; align-items:center; gap:.4rem; font-weight:700 }
  .vsep{ width:1px; height:22px; background:#e5e7eb }
  .scope{ display:flex; gap:.9rem; align-items:center }
  .radio{ display:flex; gap:.35rem; align-items:center }
  .hint{ color:#6b7280 }

  /* أزرار الحالة الكبيرة في الشريط */
  .pill-apply{
    display:inline-flex; align-items:center; gap:.45rem;
    padding:.5rem .8rem; border-radius:999px; border:1px solid #e2e8f0; background:#fff; cursor:pointer;
  }

  /* ===== الجدول ===== */
  :root{ --thead-bg:#0f172a; --thead-color:#fff; --thead-bd:#0b1220; }
  .table-scroller{ overflow:auto; }
  .tracking-table{ width:max-content; min-width:100%; border-collapse:separate; border-spacing:0; }
  .tracking-table th,.tracking-table td{ border:1px solid #eef2f6; padding:.5rem .6rem; background:#fff; }
  .tracking-table thead th{ position:sticky; top:0; z-index:6; background:var(--thead-bg); color:var(--thead-color); border-color:var(--thead-bd)!important; font-weight:800; letter-spacing:.2px; text-align:center; }
  .tracking-table th:first-child,.tracking-table td:first-child{ position:sticky; right:0; z-index:5; background:#fff; }
  .tracking-table thead th:first-child{ z-index:8; background:var(--thead-bg); color:var(--thead-color); }
  .th-cell{ display:flex; align-items:center; justify-content:center; gap:.5rem; padding:.4rem .6rem; min-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .th-cell a{ color:inherit; opacity:.85; } .th-cell a:hover{ opacity:1; }
  .student-name{ white-space:nowrap; min-width:200px; font-weight:700; }
  .pick-cell{ width:56px; text-align:center; }
  .row-pick{ width:18px; height:18px; }

  /* ===== خلايا الأيقونات الثلاث ===== */
  .cell-flex{ display:flex; align-items:center; justify-content:center; gap:.45rem; min-width:160px; }
  .pill{
    width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;
    border-radius:999px; border:1.5px solid #cbd5e1; background:transparent; cursor:pointer;
    font-size:14px; line-height:1; user-select:none;
  }
  .pill i{ opacity:.9 }
  .pill:focus{ outline:2px solid #94a3b8; outline-offset:2px }

  /* مفعّل */
  .pill.done.active{ background:#22c55e; border-color:#22c55e; color:#fff; }
  .pill.late.active{ background:#f59e0b; border-color:#f59e0b; color:#fff; }

  /* دائرة العلامة */
  .pill.grade{ font-weight:800; }
  .pill.grade.has-grade{ border-color:#334155; color:#0f172a; } /* بلا خلفية كما طُلب */

  /* تقليل مؤثرات */
  *, *::before, *::after { animation:none !important; transition:none !important; }

  @media (max-width: 992px){
    .th-cell{ min-width:150px; }
    .student-name{ min-width:160px; }
  }
  /* محرر العلامة داخل الدائرة */
.pill.grade .grade-editor{
  width: 42px; height: 28px;
  border: 1.5px solid #334155; border-radius: 999px;
  text-align: center; font-weight: 800;
  background: #fff; outline: none;
  /* نفس الإحساس المرئي للدائرة */
  padding: 0; margin: 0;
}
.table-footer{
  display:flex; align-items:center; justify-content:space-between;
  gap:1rem; padding:.6rem .4rem; border-top:1px solid #e6eef6; margin-top:.5rem;
}
.table-footer .left{ display:flex; align-items:center; gap:.5rem; }
.pagination{ display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
.pg-nums button{
  min-width: 34px; height: 34px; border-radius:10px; border:1px solid #e2e8f0; background:#fff; cursor:pointer;
  padding: 0 .5rem; font-weight:700;
}
.pg-nums button.active{ background: var(--primary); color:#fff; border-color: var(--primary); }
.btn-save{ justify-self: end; }
@media (max-width:980px){
  .btn-save{ justify-self: start; }
}
/* SweetAlert Toast theme (HOW Platform) */
.swal-toast.swal2-popup {
  border-radius: 14px !important;
  box-shadow: 0 10px 24px rgba(0,0,0,.12) !important;
  padding-inline: 1rem !important;
}
.swal-title {
  direction: rtl;
  font-weight: 800 !important;
  letter-spacing: .1px;
}
.swal2-icon {
  box-shadow: none !important;
}
.swal-container { direction: rtl; }
.swal2-actions button.swal2-confirm {
  background: var(--primary) !important;
  border-radius: 12px !important;
}
.swal2-actions button.swal2-cancel {
  border-radius: 12px !important;
}

</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('assets/js/performance_tracking.js') }}"></script>
@endpush
