@php
  use Illuminate\Support\Facades\DB;

  $authUser     = session('auth_user');
  $currentRoute = request()->route() ? request()->route()->getName() : '';
  $currentClassId = null;         // تُستخدم لتظليل صف محدد إن لزم
  $currentSpinnerId = null;       // تُستخدم لتظليل سبنر معيّن إن أردت لاحقًا

  // لو كنا في صفحة show للسبنر وفيه $session ممرّر (كما في SpinnerController@show)
  if (!isset($currentClassId) && isset($session) && isset($session->class_id)) {
    $currentClassId = (string) $session->class_id;
  }

  // التقاط رقم السبنر من الراوت عند الحاجة
  if (in_array($currentRoute, ['user.spinners.show'])) {
    $currentSpinnerId = (string) (request()->route('id') ?? '');
  }

  // صفوفي
  $myClasses = collect();
  if ($authUser && isset($authUser['id'])) {
    $uid = (int) $authUser['id'];
    $myClasses = DB::table('user_classes as c')
      ->leftJoin('user_class_students as s', 's.class_id', '=', 'c.id')
      ->select('c.id','c.name', DB::raw('COUNT(s.id) as students_count'))
      ->where('c.user_id', $uid)
      ->groupBy('c.id','c.name')
      ->orderBy('c.created_at','desc')
      ->get();
  }
@endphp

{{-- رجوع لجميع الأدوات --}}
<div class="sidebar-section">
  <a href="{{ route('tools.index') }}" class="sidebar-link">
    <i class="fas fa-arrow-right"></i> العودة لجميع الأدوات
  </a>
</div>

{{-- العجلة الدوارة (السبنر) بنفس النمط --}}
<div class="sidebar-section">
  <h3 class="sidebar-title">العجلة الدوّارة</h3>

  <a href="{{ route('user.spinners.index') }}"
     class="sidebar-link {{ $currentRoute === 'user.spinners.index' ? 'active' : '' }}"
     aria-current="{{ $currentRoute === 'user.spinners.index' ? 'page' : 'false' }}">
    <i class="fas fa-compact-disc"></i>
    <span>سبنراتي</span>
  </a>

  <a href="{{ route('user.spinners.create') }}"
     class="sidebar-link {{ $currentRoute === 'user.spinners.create' ? 'active' : '' }}"
     aria-current="{{ $currentRoute === 'user.spinners.create' ? 'page' : 'false' }}">
    <i class="fas fa-plus-circle"></i>
    <span>سبنر جديد</span>
  </a>
</div>

{{-- صفوفي (نفس البلوك الموجود عندك) --}}
<div class="sidebar-section">
  <div class="sidebar-subtitle" style="padding:.5rem 1.5rem .25rem;font-size:.85rem;color:var(--text-secondary);">
    صفوفي
  </div>

  <a href="{{ route('user.classes.index') }}"
     class="sidebar-link {{ $currentRoute === 'user.classes.index' ? 'active' : '' }}"
     aria-current="{{ $currentRoute === 'user.classes.index' ? 'page' : 'false' }}"
     style="margin-bottom:.25rem;display:flex;align-items:center;gap:.5rem">
    <i class="fas fa-plus-circle"></i>
    <span>إضافة صف جديد</span>
  </a>

  @forelse($myClasses as $cls)
    @php $isActiveClass = isset($currentClassId) && (string)$currentClassId === (string)$cls->id; @endphp
    <a href="{{ route('user.classes.students', $cls->id) }}"
       class="sidebar-link {{ $isActiveClass ? 'active' : '' }}"
       aria-current="{{ $isActiveClass ? 'page' : 'false' }}">
      <i class="fas fa-chalkboard"></i>
      <span style="flex:1">{{ $cls->name }}</span>
      <span title="عدد الطلاب"
            style="margin-inline-start:auto;display:inline-flex;align-items:center;gap:.35rem;font-size:.85rem;color:var(--text-secondary);">
        <i class="fas fa-user-graduate"></i> {{ (int)$cls->students_count }}
      </span>
    </a>
  @empty
    <div class="no-results" style="padding:.75rem 1.5rem;color:var(--text-secondary);">
      لا يوجد صفوف بعد.
    </div>
  @endforelse
</div>
