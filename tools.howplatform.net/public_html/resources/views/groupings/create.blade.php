@extends('layouts.app')
@section('title','توزيع مجموعة جديدة')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/groupings.css') }}">
@endpush

@section('sidebar')
  @include('partials.sidebar-groupings')
@endsection



@section('content')
  @include('groupings._steps', ['active'=>'create'])
<div class="container py-3 shell">
  <div class="panel">
    <div class="panel-title"><i class="fas fa-plus-circle"></i> بدء توزيع مجموعة جديدة</div>

    @if($errors->any())
      <div class="hint auto-dismiss" style="margin-bottom:.6rem;color:#b91c1c;border-color:#fecaca;background:#fff1f2">
        <i class="fas fa-exclamation-triangle"></i> {{ implode(' - ', $errors->all()) }}
      </div>
    @endif

    <form id="grouping-form" method="POST" action="{{ route('user.groupings.store') }}">
      @csrf

      <div class="form-grid">
        <div>
          <label class="label">اختر الصف</label>
          <select name="class_id" id="class_id" class="select" required>
            <option value="">— اختر صفًا —</option>
            @foreach($classes as $c)
              <option value="{{ $c->id }}" data-grade="{{ $c->grade_level ?? '' }}" {{ old('class_id')==$c->id?'selected':'' }}>
                {{ $c->name }} ({{ (int)$c->students_count }} طالب) — مستوى: {{ $c->grade_level ?? '—' }}
              </option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="label">عنوان المجموعة (اختياري)</label>
          <input type="text" name="title" value="{{ old('title') }}" class="input" placeholder="مثال: توزيع المسابقة الأسبوعية">
        </div>

        <div>
          <label class="label">طريقة التوزيع</label>
          <select name="mode" id="mode" class="select" required>
            <option value="by_size"  {{ old('mode','by_size')==='by_size'?'selected':'' }}>حسب الحجم (عدد الطلاب/مجموعة)</option>
            <option value="by_count" {{ old('mode')==='by_count'?'selected':'' }}>حسب العدد (عدد المجموعات)</option>
          </select>
        </div>

        <div id="field-by-size">
          <label class="label">حجم كل مجموعة</label>
          <input type="number" name="group_size" value="{{ old('group_size', 4) }}" min="2" max="50" class="input">
        </div>

        <div id="field-by-count" style="display:none">
          <label class="label">عدد المجموعات</label>
          <input type="number" name="group_count" value="{{ old('group_count', 5) }}" min="2" max="50" class="input">
        </div>

        {{-- hidden دائماً ليمرّر قيمة وضع التخصص حتى لو الميّزة غير مفعّلة --}}
        <input type="hidden" name="major_mode" id="major_mode_hidden" value="{{ old('major_mode','none') }}">

        {{-- صندوق التخصصات (يظهر فقط عند وجود تخصصات) --}}
        <div id="major_box" style="display:none">
          <label class="label">التوزيع حسب التخصص</label>
          <select id="major_mode_ui" class="select">
            <option value="none" {{ old('major_mode','none')==='none'?'selected':'' }}>بدون تخصّص</option>
            <option value="per_major" {{ old('major_mode')==='per_major'?'selected':'' }}>تجميع حسب التخصص</option>
            <option value="bundle_majors" {{ old('major_mode')==='bundle_majors'?'selected':'' }}>تجميع تخصّصات محددة</option>
            <option value="mixed" {{ old('major_mode')==='mixed'?'selected':'' }}>مختلط</option>
          </select>
        </div>

        <div id="bundle_box" style="display:none">
          <label class="label">اختر التخصصات المراد تجميعها</label>
          <select name="major_selected[]" id="major_selected" class="select" multiple size="6"></select>
          <div class="muted" style="margin-top:.25rem">اختر تخصصين فأكثر.</div>
        </div>
      </div>

      <div style="display:flex;gap:.6rem;align-items:center;margin-top:1rem;flex-wrap:wrap">
        <button id="btn-save-draft" type="button" class="btn"
                data-action="{{ route('user.groupings.store') }}">
          <i class="fas fa-save"></i> حفظ كمسودة
        </button>

        <button id="btn-quick" type="button" class="btn btn-primary"
                data-action="{{ route('user.groupings.quick') }}">
          <i class="fas fa-bolt"></i> إنشاء وتوزيع الآن
        </button>
      </div>
    </form>
  </div>

</div>
@endsection

@push('scripts')
<script>
(function(){
  const form     = document.getElementById('grouping-form');
  const btnDraft = document.getElementById('btn-save-draft');
  const btnQuick = document.getElementById('btn-quick');
  const modeSel  = document.getElementById('mode');
  const bySize   = document.getElementById('field-by-size');
  const byCount  = document.getElementById('field-by-count');

  // ===== عرض/إخفاء الحقول حسب وضع التوزيع =====
  function syncMode(){
    const m = modeSel.value;
    bySize.style.display  = (m==='by_size') ? '' : 'none';
    byCount.style.display = (m==='by_count') ? '' : 'none';
  }
  modeSel.addEventListener('change', syncMode); syncMode();

  // ===== منطق التخصصات =====
  const clsSel   = document.getElementById('class_id');
  const mjBox    = document.getElementById('major_box');
  const mjUI     = document.getElementById('major_mode_ui');
  const mjHidden = document.getElementById('major_mode_hidden');
  const bBox     = document.getElementById('bundle_box');
  const bSel     = document.getElementById('major_selected');

  function syncBundle(){ bBox.style.display = (mjUI.value==='bundle_majors') ? '' : 'none'; }
  mjUI.addEventListener('change', ()=>{ mjHidden.value = mjUI.value; syncBundle(); });

  async function fetchMajors(classId){
    mjBox.style.display = 'none';
    bBox.style.display  = 'none';
    bSel.innerHTML = '';
    mjHidden.value = mjHidden.value || 'none';

    if (!classId) return;
    try{
      const res = await fetch(`{{ url('/my/classes') }}/${classId}/majors`, {
        headers:{ 'X-Requested-With':'XMLHttpRequest' }
      });
      const js  = await res.json();
      if (!js.ok || !js.allow || !js.majors || js.majors.length===0) {
        mjBox.style.display = 'none';
        mjHidden.value = 'none';
        return;
      }
      js.majors.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m; opt.textContent = m;
        bSel.appendChild(opt);
      });
      mjBox.style.display = '';
      mjUI.value = mjHidden.value || 'none';
      syncBundle();
    }catch(e){ /* تجاهل أي خطأ تحميل */ }
  }

  clsSel.addEventListener('change', ()=> fetchMajors(clsSel.value));
  if (clsSel.value) fetchMajors(clsSel.value);

  // ===== إرسال AJAX موحّد لتفادي 405 =====
  function getCsrf(){
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || form.querySelector('input[name="_token"]')?.value
        || '';
  }

  function setLoading(isLoading){
    [btnDraft, btnQuick].forEach(b=>{
      b.disabled = isLoading;
      b.classList.toggle('is-loading', isLoading);
    });
  }

  function showErrors(messages){
    // يمكنك لاحقًا استبدال alert بعنصر تنبيهات داخل الصفحة
    alert(Array.isArray(messages) ? messages.join('\\n') : messages);
  }

  async function submitAjax(actionUrl){
    setLoading(true);
    try{
      const fd = new FormData(form);
      fd.append('via', 'ajax');

      const res = await fetch(actionUrl, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrf()
        },
        body: fd
      });

      const contentType = res.headers.get('Content-Type') || '';

      // لو رجع HTML وليس JSON (مثلاً View جاهز لعرض النتيجة)، اعرضه مباشرة
      if (!contentType.includes('application/json')) {
        const html = await res.text();
        if (res.ok) {
          document.open(); document.write(html); document.close();
          return;
        } else {
          showErrors('تعذّر الإرسال (HTML). رمز الحالة: '+res.status);
          return;
        }
      }

      // JSON
      const data = await res.json();

      if (!res.ok) {
        if (res.status === 422 && data?.errors) {
          const msgs = [];
          Object.values(data.errors).forEach(arr => arr.forEach(m=>msgs.push(m)));
          showErrors(msgs.length?msgs:'تحقق من الحقول');
        } else {
          showErrors(data?.message || 'تعذّر الإرسال. حاول لاحقًا.');
        }
        return;
      }

      // نجاح
      if (data?.redirect) {
        window.location.assign(data.redirect);
      } else if (data?.id) {
        window.location.assign(`{{ url('/user/groupings') }}/${data.id}`);
      } else {
        showErrors('تم الحفظ بنجاح، لكن لم يصل رابط التحويل. حدّث الصفحة للتحقّق.');
      }
    } catch (e){
      showErrors('فشل الاتصال بالخادم. تأكّد من الشبكة وحاول مجددًا.');
    } finally {
      setLoading(false);
    }
  }

  btnDraft.addEventListener('click', ()=> submitAjax(btnDraft.dataset.action));
  btnQuick.addEventListener('click', ()=> submitAjax(btnQuick.dataset.action));

  // منع الإرسال التقليدي كليًا
  form.addEventListener('submit', (e)=> e.preventDefault());
})();
</script>
@endpush
@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    setTimeout(() => {
      document.querySelectorAll('.auto-dismiss').forEach(el => {
        el.style.transition = "opacity 0.5s ease";
        el.style.opacity = "0";
        setTimeout(() => el.remove(), 500); // يُحذف من DOM بعد الانميشن
      });
    }, 10000); // 10000ms = 10 ثواني
  });
</script>
@endpush