@php
  use Illuminate\Support\Facades\DB;
  $authUser = session('auth_user');
  $currentRoute = request()->route() ? request()->route()->getName() : '';
  $currentClassId = (in_array($currentRoute, ['user.classes.students']) ? request()->route('id') : null);
  $myClasses = collect();
  if ($authUser && isset($authUser['id'])) {
    $uid = (int) $authUser['id'];
    $myClasses = DB::table('user_classes as c')
      ->leftJoin('user_class_students as s', 's.class_id', '=', 'c.id')
      ->select('c.id','c.name','c.year','c.grade_level','c.is_active', DB::raw('COUNT(s.id) as students_count'))
      ->where('c.user_id', $uid)
      ->groupBy('c.id','c.name','c.year','c.grade_level','c.is_active')
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
  <h3 class="sidebar-title">إدارة الصفوف</h3>

  @if($myClasses->isEmpty())
    <div class="no-results" style="padding:.75rem 1.5rem;color:var(--text-secondary);">
      لا يوجد لديك صفوف بعد.
    </div>

    <a href="{{ route('user.classes.index') }}"
       class="sidebar-link {{ ($currentRoute ?? '') === 'user.classes.index' ? 'active' : '' }}"
       style="margin:.4rem 1rem;display:flex;align-items:center;gap:.5rem">
      <i class="fas fa-plus-circle"></i>
      <span>إضافة صف</span>
    </a>
  @else
    <div class="sidebar-subtitle" style="padding:.5rem 1.5rem .25rem;font-size:.85rem;color:var(--text-secondary);">
      صفوفي
    </div>

    {{-- كأول خيار قبل سرد الصفوف --}}
    <a href="{{ route('user.classes.index') }}"
       class="sidebar-link {{ ($currentRoute ?? '') === 'user.classes.index' ? 'active' : '' }}"
       aria-current="{{ ($currentRoute ?? '') === 'user.classes.index' ? 'page' : 'false' }}"
       style="margin-bottom:.25rem;display:flex;align-items:center;gap:.5rem">
      <i class="fas fa-plus-circle"></i>
      <span>إضافة صف جديد</span>
    </a>

    @foreach($myClasses as $cls)
      @php $isActive = (string)($currentClassId ?? '') === (string)$cls->id; @endphp
      <a href="{{ route('user.classes.students', $cls->id) }}"
         class="sidebar-link {{ $isActive ? 'active' : '' }}"
         aria-current="{{ $isActive ? 'page' : 'false' }}">
        <i class="fas fa-chalkboard"></i>
        <span style="flex:1">{{ $cls->name }}</span>
        <span title="عدد الطلاب" style="margin-inline-start:auto;display:inline-flex;align-items:center;gap:.35rem;font-size:.85rem;color:var(--text-secondary);">
          <i class="fas fa-user-graduate"></i> {{ (int) $cls->students_count }}
        </span>
      </a>
    @endforeach
  @endif
</div>

{{-- الحساب --}}
<div class="sidebar-section">
  <h3 class="sidebar-title">الحساب</h3>
  <a href="https://auth.howplatform.net/profile" class="sidebar-link"><i class="fas fa-user"></i> الملف الشخصي</a>
</div>
