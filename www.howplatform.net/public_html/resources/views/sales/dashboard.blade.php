@extends('layouts.main')

@section('title', 'لوحة مبيعات نقطة البيع')
@push('styles')
<style>
/* ===== POS Hub (scoped to this view only) ===== */

/* تضييق عرض الصفحة المركزية */
.container-fluid.py-4{
  max-width: 1100px;
  margin-inline: auto;
}

/* عنوان الصفحة مع حافة accent */
.container-fluid.py-4 > h2.fw-bold{
  color: var(--primary-50, #0a3247);
  padding-inline-start: .7rem;
  border-inline-start: 8px solid var(--accent-500, #fd9e02);
  line-height: 1.2;
}

/* تبويبات (Bootstrap nav-tabs) بستايل الهوية */
#posTabs{
  border-bottom: none !important;
  background: var(--surface-1, #fff);
  padding: 6px;
  border: 1px solid rgba(0,0,0,.06);
  border-radius: 14px;
  display: flex; gap: 6px; flex-wrap: wrap;
}

/* الروابط داخل التبويبات */
#posTabs .nav-link{
  border: 1px solid transparent !important;
  border-radius: 12px !important;
  font-weight: 800;
  color: var(--primary-700, #156683);
  padding: .6rem .9rem;
  transition: transform .18s, box-shadow .18s, background .18s, color .18s;
}
#posTabs .nav-link:hover{
  background: var(--primary-50, #e8f5fb);
  color: var(--primary-900, #0a3247);
  transform: translateY(-1px);
  box-shadow: 0 6px 14px rgba(0,0,0,.08);
}

/* التبويب النشط: تدرّج primary داكن + نص أبيض */
#posTabs .nav-link.active{
  background: linear-gradient(135deg, var(--primary-600,#1b81a0), var(--primary-800,#104b65)) !important;
  color: var(--text-inverse, #fff) !important;
  border-color: transparent !important;
  box-shadow: 0 10px 22px rgba(16,75,101,.22);
}

/* تماسك المسافات تحت التبويبات */
#posTabsContent{ margin-top: 12px; }

/* تحسين تباين عناوين الأقسام داخل التبويبات (إن وُجدت) */
#posTabsContent h4{
  color: var(--primary-50, #0a3247);
}

/* دعم RTL بشكل طبيعي (Bootstrap بالفعل RTL-ready، نحافظ فقط على inline-start) */
:root{ /* متغيّر عرض افتراضي إن استخدمته الأقسام الفرعية */
  --maxw: 1100px;
}
.modal-header .btn-close {
    margin: 0 !important;
}
</style>
@endpush

@section('content')
<div class="container-fluid py-4">

  {{-- عند الرجوع من أي فورم، خزّن التبويب الذي كنا عليه (القيمة تأتي من الكنترولر) --}}
  @if (session('flash_tab'))
    <script>
      localStorage.setItem('pos_active_tab', @json(session('flash_tab')));
    </script>
  @endif

  <h2 class="mb-4 fw-bold"><i class="fa-solid fa-chart-line me-2"></i> لوحة مبيعات نقطة البيع</h2>

  <!-- تبويبات -->
  <ul class="nav nav-tabs mb-4" id="posTabs" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" id="overview-tab" data-bs-toggle="tab" href="#overview" role="tab">الإحصائيات</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="points-tab" data-bs-toggle="tab" href="#points" role="tab">نقاط البيع</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="limits-tab" data-bs-toggle="tab" href="#limits" role="tab">سقوف المبيعات</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="transactions-tab" data-bs-toggle="tab" href="#transactions" role="tab">العمليات المالية</a>
    </li>
  </ul>

  <div class="tab-content" id="posTabsContent">
    <!-- القسم 1: الإحصائيات -->
    <div class="tab-pane fade show active" id="overview" role="tabpanel">
      @include('sales.partials.overview')
    </div>

    <!-- القسم 2: نقاط البيع -->
    <div class="tab-pane fade" id="points" role="tabpanel">
      @include('sales.partials.points', [
          'salesPoints' => ($salesPoints ?? (isset($points) ? $points : collect())),
          'salesUsers'  => ($salesUsers ?? [])
      ])
    </div>

    <!-- القسم 3: سقوف المبيعات -->
    <div class="tab-pane fade" id="limits" role="tabpanel">
      @include('sales.partials.limits')
    </div>

    <!-- القسم 4: العمليات المالية -->
    <div class="tab-pane fade" id="transactions" role="tabpanel">
      @include('sales.partials.transactions')
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
// ===== SweetAlert2 — إظهار رسائل الفلاش كتَوست تلقائي الإغلاق =====
document.addEventListener('DOMContentLoaded', function () {
  @php
    $flashType = null; $flashMsg = null;
    foreach (['success','error','warning','info','status'] as $k) {
        if (session($k)) { $flashType = $k; $flashMsg = session($k); break; }
    }
    if (!$flashType && $errors->any()) { $flashType = 'error'; $flashMsg = $errors->first(); }
    $iconMap = ['success'=>'success','error'=>'error','warning'=>'warning','info'=>'info','status'=>'success'];
    $icon    = $iconMap[$flashType] ?? null;
  @endphp

  @if ($flashMsg ?? false)
    Swal.fire({
      icon: @json($icon ?: 'info'),
      title: @json($flashMsg),
      toast: true,
      position: 'top-end',
      timer: 4000,
      timerProgressBar: true,
      showConfirmButton: false,
      showCloseButton: true
    });
  @endif
});

// ===== حفظ/استعادة التبويب النشط =====
document.addEventListener('DOMContentLoaded', function () {
  // عند تبديل التبويب خزّنه
  document.querySelectorAll('#posTabs a[data-bs-toggle="tab"]').forEach(a => {
    a.addEventListener('shown.bs.tab', (e) => {
      const target = e.target.getAttribute('href'); // مثال "#points"
      if (target) localStorage.setItem('pos_active_tab', target);
    });
  });

  // استعادة آخر تبويب محفوظ
  const saved = localStorage.getItem('pos_active_tab') || '#overview';
  const link  = document.querySelector(`#posTabs a[href="${saved}"]`);
  if (link) new bootstrap.Tab(link).show();
});
</script>
@endpush
