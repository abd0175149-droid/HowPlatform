@php
  use Illuminate\Support\Facades\DB;
  $authUser = session('auth_user');
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

<div class="sidebar-section">
  <a href="{{ route('tools.index') }}" class="sidebar-link">
    <i class="fas fa-arrow-right"></i> العودة لجميع الأدوات
  </a>
</div>

<div class="sidebar-section">
  <h3 class="sidebar-title">إدارة المجموعات</h3>

  <a href="{{ route('user.groupings.index') }}"
     class="sidebar-link {{ ($currentRoute ?? '') === 'user.groupings.index' ? 'active' : '' }}"
     aria-current="{{ ($currentRoute ?? '') === 'user.groupings.index' ? 'page' : 'false' }}">
    <i class="fas fa-clone"></i>
    <span>المجموعات</span>
  </a>

  <a href="{{ route('user.groupings.create') }}"
     class="sidebar-link {{ ($currentRoute ?? '') === 'user.groupings.create' ? 'active' : '' }}"
     aria-current="{{ ($currentRoute ?? '') === 'user.groupings.create' ? 'page' : 'false' }}">
    <i class="fas fa-plus-circle"></i>
    <span>مجموعة جديدة</span>
  </a>

  <div class="sidebar-subtitle"
       style="padding:.5rem 1.5rem .25rem;font-size:.85rem;color:var(--text-secondary);">
    صفوفي
  </div>

  {{-- زر إضافة صف جديد كخيار أول ثابت --}}
  <a href="{{ route('user.classes.index') }}"
     class="sidebar-link {{ ($currentRoute ?? '') === 'user.classes.index' ? 'active' : '' }}"
     aria-current="{{ ($currentRoute ?? '') === 'user.classes.index' ? 'page' : 'false' }}"
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
