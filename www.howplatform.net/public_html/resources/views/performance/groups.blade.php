@extends('layouts.tools')

@section('title', '👥 إدارة المجموعات')

@section('content')
<div class="tools-wrapper">
  {{-- شريط أدوات علوي (إضافة/بحث/اختيارات) --}}
  <div class="toolbar card">
    <div class="toolbar-row">
      <div class="add-group">
        <input type="text" id="newGroupName" class="form-input" placeholder="اسم المجموعة الجديدة">
        <button id="addGroupBtn" class="btn btn-primary">
          <i class="fa-solid fa-plus"></i>
          <span>إضافة مجموعة</span>
        </button>
      </div>

      <div class="students-actions">
        <label class="checkbox">
          <input type="checkbox" id="selectAllStudents">
          <span>تحديد الكل</span>
        </label>
        <input type="text" id="studentSearch" class="form-input" placeholder="بحث عن طالب...">
        <span class="badge" id="selectedCount">0 محدد</span>
      </div>
    </div>
  </div>

  {{-- تخطيط عمودين: الطلبة — المجموعات --}}
  <div class="grid-2">
    {{-- قائمة الطلبة --}}
    <div class="card">
      <div class="card-header students-header">
        <h3><i class="fa-solid fa-user-graduate"></i> الطلبة</h3>
        <small class="hint">اسحب الطالب إلى المجموعة أو حدّد عدة طلبة ثم اختر تعيين جماعي</small>

        {{-- ⬅️ نُقِل خيار إظهار غير الموزعين للأعلى قبل الأسماء --}}
        <div class="inline-actions before-list">
          <label class="checkbox">
            <input type="checkbox" id="onlyUnassigned">
            <span>إظهار غير الموزعين فقط</span>
          </label>
        </div>
      </div>

      <div class="card-body students-list">
        <ul id="studentsList" class="list" aria-live="polite"></ul>
      </div>

      {{-- حُذِف من الفوتر لأننا نقلناه للأعلى --}}
      {{-- <div class="card-footer"> ... </div> --}}
    </div>

    {{-- المجموعات --}}
    <div class="card">
      <div class="card-header">
        <h3><i class="fa-solid fa-layer-group"></i> المجموعات</h3>
        <small class="hint">يمكن إسقاط الطالب على كامل مساحة بطاقة المجموعة (منطقة الإفلات).</small>
      </div>

      <div class="card-body">
        <div id="groupsToolbar" class="groups-toolbar">
          <label class="checkbox">
            <input type="checkbox" id="selectAllGroups">
            <span>تحديد كل المجموعات</span>
          </label>

          <div class="spacer"></div>

          <button id="assignSelectedToCheckedGroups" class="btn btn-accent">
            <i class="fa-solid fa-user-plus"></i>
            <span>إضافة الطلبة المحددين للمجموعات المختارة</span>
          </button>

          <button id="unassignSelectedFromCheckedGroups" class="btn btn-ghost">
            <i class="fa-solid fa-user-minus"></i>
            <span>إزالة الطلبة المحددين من المجموعات المختارة</span>
          </button>
        </div>

        <div id="groupsContainer" class="groups-container" aria-live="polite">
          {{-- يُرسم ديناميكيًا --}}
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
/* ===== هوية عامة ===== */

/* ✅ ملء الشاشة عرضًا وتقليل الحواف العامة */
.tools-wrapper{width:100%;max-width:none;margin:0;padding:1rem 2%}

/* حُذف عنوان الصفحة لتوسيع المساحة العمودية
.page-title{display:none}
*/

/* شريط الأدوات */
.toolbar.card{padding:1rem;margin-bottom:1rem;background:#fff;border-radius:16px;box-shadow:var(--shadow,0 6px 16px rgba(0,0,0,.08))}
.toolbar-row{display:flex;gap:1rem;align-items:center;justify-content:space-between;flex-wrap:wrap}
.add-group{display:flex;gap:.5rem;align-items:center}
.students-actions{display:flex;gap:.75rem;align-items:center}

.form-input{ padding:.75rem 1rem;border:1px solid #ddd;border-radius:12px;min-width:240px;font-family:inherit }

.btn{display:inline-flex;align-items:center;justify-content:center;padding:.8rem 1.1rem;border-radius:14px;border:0;cursor:pointer;font-weight:800;transition:all .22s;gap:8px}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{transform:translateY(-2px);box-shadow:var(--shadow)}
.btn-ghost{background:transparent;border:2px solid var(--primary);color:var(--primary)}
.btn-ghost:hover{background:rgba(33,158,188,.06);transform:translateY(-2px)}
.btn-accent{background:var(--accent);color:#fff}
.btn-accent:hover{filter:saturate(1.05);transform:translateY(-2px)}

.badge{background:var(--primary);color:#fff;border-radius:999px;padding:.25rem .6rem;font-size:.85rem}

/* ===== شبكة الصفحة: تملأ الارتفاع لضمان قائمة طويلة قابلة للتمرير ===== */
.grid-2{
  display:grid;
  grid-template-columns:1fr 1.2fr;
  gap:1.2rem;
  min-height:calc(100vh - 150px); /* يضمن الامتداد رأسياً */
}
@media (max-width: 992px){.grid-2{grid-template-columns:1fr;min-height:auto}}

.card{background:#fff;border-radius:16px;box-shadow:var(--shadow);display:flex;flex-direction:column;min-height:0;height:100%}
.card-header{padding:1rem 1rem .5rem;border-bottom:1px solid #f0f2f5}
.card-body{padding:1rem;flex:1;min-height:0;overflow:auto} /* ⬅️ تمرير داخلي ذكي */
.card-footer{padding:.8rem 1rem;border-top:1px solid #f0f2f5;background:#fafcff}
.hint{font-size:.88rem;color:#555}

/* ترويسة الطلبة مع فلتر قبل الأسماء */
.students-header .before-list{margin-top:.6rem}

/* ===== قائمة الطلبة ===== */
.list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.5rem}
.list li{
  display:flex;align-items:center;gap:.6rem;padding:.6rem .75rem;border:1px solid #eee;border-radius:12px;background:#fff;
  cursor:grab; user-select:none; transition:background .18s ease,border-color .18s ease, box-shadow .18s ease;
}
.list li[draggable="true"]{cursor:grab}
.drag-handle{ color:#93a1ad; cursor:grab; display:inline-flex; align-items:center; }
.drag-handle:active{ cursor:grabbing }
.list input[type="checkbox"]{ display:none; } /* ⬅️ إخفاء الـ checkbox */
.list .name{ flex:1.75; font-weight:700; color:var(--text,#0a3247) } /* ⬅️ أوسع للاسم */
.list .tags{ flex:1; display:flex; gap:.25rem; flex-wrap:wrap; justify-content:flex-end }
.tag{font-size:.75rem;background:#f3f6fb;border:1px solid #e9eef6;padding:.1rem .4rem;border-radius:999px}

/* تمييز الصف المحدَّد */
.list li.selected{
  background:#eaf6fb;
  border-color:var(--primary);
  box-shadow:0 0 0 2px rgba(33,158,188,.14) inset;
}

/* ===== المجموعات ===== */
.groups-toolbar{display:flex;gap:.75rem;align-items:center;margin-bottom:.75rem}
.groups-toolbar .spacer{flex:1}

/* ✅ على الشاشات العريضة: عمودان على الأقل */
.groups-container{
  display:grid;
  grid-template-columns:repeat(2, minmax(320px, 1fr));
  gap:1rem;
}
@media (max-width: 860px){
  .groups-container{grid-template-columns:1fr}
}

/* توضيح منطقة الإفلات بشكل دائم مع تعزيز عند السحب */
.group-card{border:1px solid #e9eef6;border-radius:14px;background:#fff;box-shadow:var(--shadow);display:flex;flex-direction:column;transition:box-shadow .2s}
.group-head{display:flex;gap:.5rem;align-items:center;padding:.7rem .8rem;border-bottom:1px solid #f0f2f5}
.group-title{font-weight:800;color:var(--text,#0a3247);flex:1;display:flex;gap:.4rem;align-items:center}
.group-actions{display:flex;gap:.25rem}
.btn-icon{background:transparent;border:0;padding:.25rem .4rem;cursor:pointer;border-radius:8px}
.btn-icon:hover{background:#f2f6fb}

.group-body{
  padding:.8rem;display:flex;flex-direction:column;gap:.6rem;position:relative;
  border:2px dashed #e6eef6; border-radius:12px; background:#fbfdff; /* ⬅️ ظاهر دائماً */
}
.group-body.drag-over{
  border-color:var(--primary);
  background:linear-gradient(0deg, rgba(33,158,188,.06), rgba(33,158,188,.06));
  box-shadow:0 0 0 2px rgba(33,158,188,.12) inset;
}

.dz-helper{
  display:flex;align-items:center;gap:.5rem;justify-content:center;
  background:#f8fbfe;border:1px dashed #c9d8e6;border-radius:10px;color:#4b6477;
  padding:.6rem .7rem;font-size:.92rem;
}
.dz-helper i{opacity:.8}

/* عناصر العضو داخل المجموعة */
.member{display:flex;align-items:center;gap:.5rem;padding:.45rem .55rem;border-radius:10px;background:#f9fafb;border:1px solid #eef2f6}
.member .remove{color:#c62828;cursor:pointer}

/* تحميل صغير للأزرار */
.loading-dot{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .9s linear infinite;vertical-align:middle}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
@endpush

@push('scripts')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('assets/js/performance_groups.js') }}" defer></script>
@endpush

@push('scripts')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('assets/js/performance_groups.js') }}" defer></script>
@endpush
