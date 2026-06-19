{{-- resources/views/spinners/_sidebar.blade.php --}}
<aside class="kp-sidebar">
  <div class="kp-sidebar__section">
    <h4 class="kp-sidebar__title">أداة السبنر</h4>
    <ul class="kp-menu">
      <li><a class="{{ request()->routeIs('user.spinners.index')?'active':'' }}"
             href="{{ route('user.spinners.index') }}">سبنراتي</a></li>
      @php
        $auth = session('auth_user'); $uid = $auth['id'] ?? null;
        $clsCount = $uid ? DB::table('user_classes')->where('user_id',$uid)->count() : 0; // :contentReference[oaicite:2]{index=2}
      @endphp
      @if($clsCount>0)
        <li><a class="{{ request()->routeIs('user.spinners.create')?'active':'' }}"
               href="{{ route('user.spinners.create') }}">إنشاء سبنر</a></li>
      @else
        <li class="muted">
          <div class="kp-hint danger">
            لا تملك صفوفًا بعد. <a href="{{ route('user.classes.students') }}">أضف صفًا</a> أولًا.
          </div>
        </li>
      @endif
    </ul>
  </div>
</aside>
