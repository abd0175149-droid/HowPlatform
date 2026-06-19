@php
  // $active: one of ['create','constraints','preview','print']
  // $session: optional (model/array) — يُستخدم لتوليد الروابط عند توفر جلسة
  $sid = null;
  if (!empty($session)) {
    if (is_object($session) && isset($session->id))       $sid = (int)$session->id;
    elseif (is_array($session) && isset($session['id']))  $sid = (int)$session['id'];
  }
@endphp

<div class="steps-bar no-print" role="navigation" aria-label="Groupings steps">
  {{-- 1) الإعدادات / إنشاء جلسة --}}
  <a href="{{ route('user.groupings.create') }}"
     class="step {{ ($active==='create') ? 'active' : '' }}"
     aria-current="{{ ($active==='create') ? 'step' : 'false' }}">
    <span class="step-index">1</span>
    <span class="step-label">الإعدادات</span>
  </a>

  {{-- 2) القيود --}}
  @if($sid)
    <a href="{{ route('user.groupings.constraints', $sid) }}"
       class="step {{ ($active==='constraints') ? 'active' : '' }}"
       aria-current="{{ ($active==='constraints') ? 'step' : 'false' }}">
      <span class="step-index">2</span>
      <span class="step-label">القيود</span>
    </a>
  @else
    <span class="step disabled" aria-disabled="true">
      <span class="step-index">2</span>
      <span class="step-label">القيود</span>
    </span>
  @endif

  {{-- 3) المعاينة & التسمية --}}
  @if($sid)
    <a href="{{ route('user.groupings.show', $sid) }}"
       class="step {{ ($active==='preview') ? 'active' : '' }}"
       aria-current="{{ ($active==='preview') ? 'step' : 'false' }}">
      <span class="step-index">3</span>
      <span class="step-label">المعاينة & التسمية</span>
    </a>
  @else
    <span class="step disabled" aria-disabled="true">
      <span class="step-index">3</span>
      <span class="step-label">المعاينة & التسمية</span>
    </span>
  @endif

  {{-- 4) الطباعة --}}
  @if($sid)
    <a href="{{ route('user.groupings.pdf', $sid) }}"
       class="step {{ ($active==='print') ? 'active' : '' }}"
       aria-current="{{ ($active==='print') ? 'step' : 'false' }}">
      <span class="step-index">4</span>
      <span class="step-label">الطباعة</span>
    </a>
  @else
    <span class="step disabled" aria-disabled="true">
      <span class="step-index">4</span>
      <span class="step-label">الطباعة</span>
    </span>
  @endif

  <span class="spacer"></span>
</div>
