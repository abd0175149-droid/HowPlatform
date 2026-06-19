@extends('layouts.app')
@section('title','قيود التوزيع وتوليد المجموعات')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/groupings.css') }}">
@endpush
@section('sidebar')
  @include('partials.sidebar-groupings')
@endsection


@section('content')
  @include('groupings._steps', ['active'=>'constraints'])


<div id="page-root" class="container py-3 shell" tabindex="-1">

  {{-- رأس المجموعة --}}
  <div class="panel">
    <div class="panel-title">
      <i class="fas fa-user-cog"></i>
      <span>المجموعة: {{ $session->title ?: 'بدون عنوان' }}</span>
      <span class="muted" style="margin-inline-start:auto">
        الصف: <strong>{{ $session->class_name }}</strong> •
        النمط: {{ $session->mode==='by_size'?'حسب الحجم':'حسب العدد' }}
        @if($session->mode==='by_size') ({{ (int)$session->group_size }}) @endif
        @if($session->mode==='by_count') ({{ (int)$session->group_count }}) @endif
      </span>
    </div>

    @if($errorMsg)
      <div class="badge badge-yellow auto-dismiss"><i class="fas fa-exclamation-triangle"></i> {{ $errorMsg }}</div>
    @endif
    @if(session('ok'))
      <div class="badge badge-green auto-dismiss"><i class="fas fa-check-circle"></i> {{ session('ok') }}</div>
    @endif
  </div>

  {{-- إدارة القيود (مخفية افتراضيًا) --}}
  <div class="panel">
    <div class="panel-title" style="display:flex;align-items:center;gap:.6rem">
      <i class="fas fa-shield-alt"></i> إدارة القيود
      <button id="toggle-constraints"
              type="button"
              class="btn"
              aria-expanded="false"
              aria-controls="constraints-box">
        <i class="fas fa-eye"></i> إظهار القيود
      </button>
    </div>

    <div id="constraints-box" style="display:none">
      {{-- إضافة قيد --}}
      <div class="panel" style="margin:.6rem 0;background:var(--surface-base)">
        <div class="panel-title"><i class="fas fa-plus-circle"></i> إضافة قيد</div>
        <form method="POST" action="{{ route('user.groupings.constraints.store', $session->id) }}" autocomplete="off">
          @csrf
          <input type="hidden" name="action" value="add_constraint">

          <div class="grid-2">
            <div>
              <label class="label">نوع القيد</label>
              <select name="type" class="input" required>
                <option value="MUST_TOGETHER">توزيعهم معًا دائمًا</option>
                <option value="MUST_APART">عدم التقائهم في نفس المجموعة</option>
              </select>
            </div>
            <div>
              <label class="label">ملاحظة (اختياري)</label>
              <input type="text" name="note" class="input" placeholder="مثال: ثنائي مشروع / لا يتفقان..." autocomplete="off">
            </div>

            {{-- ثنائي --}}
            <div>
              <label class="label">الطالب الأول</label>
              <select name="student_a" class="input" required>
                <option value="" selected disabled hidden>— اختر —</option>
                @foreach($students as $st)
                  <option value="{{ $st->id }}">{{ $st->student_name }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="label">الطالب الثاني</label>
              <select name="student_b" class="input" required>
                <option value="" selected disabled hidden>— اختر —</option>
                @foreach($students as $st)
                  <option value="{{ $st->id }}">{{ $st->student_name }}</option>
                @endforeach
              </select>
            </div>

            {{-- متقدّم: Chips --}}
            <div style="grid-column:1/-1">
              <div style="display:flex;align-items:center;gap:.5rem;margin:.25rem 0 .35rem">
                <button id="adv-toggle" type="button" class="btn"><i class="fas fa-users"></i> وضع متقدّم</button>
                <span class="muted">انقر للتبديل ثم اختر أكثر من طالب بالنقر.</span>
              </div>

              <div id="adv-box" style="display:none">
                <div class="muted" style="margin-bottom:.35rem">انقر على الأسماء لإضافتهم/إزالتهم:</div>
                <div id="chips" class="chips-wrap" style="display:flex;flex-wrap:wrap;gap:.4rem">
                  @foreach($students as $st)
                    <button type="button" class="chip" data-id="{{ $st->id }}">{{ $st->student_name }}</button>
                  @endforeach
                </div>
                <div id="chips-hidden"></div>
              </div>
            </div>
          </div>

          <div style="display:flex;gap:.6rem;align-items:center;margin-top:.8rem">
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> إضافة القيد</button>
          </div>
        </form>
      </div>

      {{-- القيود الحالية --}}
      <div class="panel" style="background:var(--surface-base)">
        <div class="panel-title"><i class="fas fa-list"></i> القيود الحالية</div>
        @if($constraints->isEmpty())
          <div class="muted">لا توجد قيود بعد.</div>
        @else
          <div class="list">
            @foreach($constraints as $c)
              @php $cm = $members[$c->id] ?? collect(); @endphp
              <div class="card">
                <div style="display:flex;align-items:center;gap:.5rem;justify-content:space-between">
                  <div>
                    <span class="badge {{ $c->type==='MUST_TOGETHER' ? 'badge-blue' : 'badge-yellow' }}">
                      <i class="fas {{ $c->type==='MUST_TOGETHER' ? 'fa-link' : 'fa-ban' }}"></i>
                      {{ $c->type==='MUST_TOGETHER' ? 'معًا دائمًا' : 'لا يلتقون' }}
                    </span>
                    @if(!empty($c->note)) <span class="muted"> • {{ $c->note }}</span> @endif
                  </div>

                  <form method="POST" action="{{ route('user.groupings.constraints.store', $session->id) }}">
                    @csrf
                    <input type="hidden" name="action" value="delete_constraint">
                    <input type="hidden" name="constraint_id" value="{{ $c->id }}">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> حذف</button>
                  </form>
                </div>

                <div style="margin-top:.5rem">
                  @forelse($cm as $m)
                    <form method="POST" action="{{ route('user.groupings.constraints.store', $session->id) }}" style="display:inline">
                      @csrf
                      <input type="hidden" name="action" value="remove_member">
                      <input type="hidden" name="constraint_id" value="{{ $c->id }}">
                      <input type="hidden" name="student_id" value="{{ $m->student_id }}">
                      <span class="member">
                        {{ $m->student_name }}
                        <button type="submit" class="btn" style="padding:.1rem .4rem"><i class="fas fa-times"></i></button>
                      </span>
                    </form>
                  @empty
                    <div class="muted">لا أعضاء!</div>
                  @endforelse
                </div>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- التوليد والحفظ --}}
  <div class="panel">
    <div class="panel-title"><i class="fas fa-bolt"></i> توليد التوزيع</div>

    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
      {{-- توليد/تحديث --}}
      <form method="POST" action="{{ route('user.groupings.generate', $session->id) }}">
        @csrf
        <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> توليد المعاينة</button>
      </form>

      {{-- إعادة توزيع --}}
      <form method="POST" action="{{ route('user.groupings.generate', $session->id) }}">
        @csrf
        <input type="hidden" name="reshuffle" value="1">
        <button type="submit" class="btn"><i class="fas fa-dice"></i> إعادة توزيع </button>
      </form>
    </div>
  </div>

  @if($preview)
    <div class="panel">
      <div class="panel-title"><i class="fas fa-eye"></i> المعاينة</div>
      <div class="groups">
        @foreach($preview['groups'] as $g)
          <div class="group">
            <div style="font-weight:700;margin-bottom:.4rem"><i class="fas fa-layer-group"></i> المجموعة {{ $g['label'] }} عدد الأفراد ({{ count($g['members']) }})</div>
            <div>
              @foreach($g['members'] as $m)
                <span class="member">{{ $m['name'] }}</span>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>

      <div style="display:flex;gap:.6rem;align-items:center;margin-top:.8rem;flex-wrap:wrap">
        {{-- حفظ نهائي --}}
        <form method="POST" action="{{ route('user.groupings.finalize', $session->id) }}">
          @csrf
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ نهائي</button>
        </form>

        {{-- إعادة توزيع + حفظ --}}
        <form method="POST" action="{{ route('user.groupings.finalize', $session->id) }}">
          @csrf
          <input type="hidden" name="reshuffle" value="1">
          <button type="submit" class="btn"><i class="fas fa-dice"></i> إعادة توزيع + حفظ</button>
        </form>
      </div>
    </div>
  @endif

</div>
@endsection

@push('scripts')
<script>
(function(){
  // ===== عناصر إدارة القيود =====
  const tgl  = document.getElementById('toggle-constraints');
  const box  = document.getElementById('constraints-box');

  // ===== عناصر الوضع المتقدّم =====
  const advBtn = document.getElementById('adv-toggle');
  const advBox = document.getElementById('adv-box');
  const chips  = document.getElementById('chips');
  const sink   = document.getElementById('chips-hidden');

  // حقول الثنائي داخل "إضافة قيد"
  const addConstraintForm = document.querySelector('#constraints-box form[action*="constraints"]');
  const selA = addConstraintForm?.querySelector('select[name="student_a"]');
  const selB = addConstraintForm?.querySelector('select[name="student_b"]');

  // حقل خفي يعلن نمط الإرسال (pair | advanced)
  const modeHidden = document.createElement('input');
  modeHidden.type = 'hidden';
  modeHidden.name = 'members_mode';
  modeHidden.value = 'pair';
  addConstraintForm?.appendChild(modeHidden);

  // ===== أدوات مساعدة UI =====
  const isHidden = el => (getComputedStyle(el).display === 'none');

  function setConstraintsBtn(open){
    if (!tgl) return;
    tgl.setAttribute('aria-expanded', open ? 'true' : 'false');
    tgl.innerHTML = open
      ? '<i class="fas fa-eye-slash"></i> إخفاء القيود'
      : '<i class="fas fa-eye"></i> إظهار القيود';
  }
  function setAdvBtn(on){
    if (!advBtn) return;
    advBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
    advBtn.innerHTML = on
      ? '<i class="fas fa-minus-square"></i> إخفاء المتقدّم'
      : '<i class="fas fa-users"></i> وضع متقدّم';
  }

  // الحالة الابتدائية
  if (box) setConstraintsBtn(false);
  if (advBox) setAdvBtn(false);

  // إظهار/إخفاء لوحة القيود
  tgl && tgl.addEventListener('click', ()=>{
    const willOpen = isHidden(box);
    box.style.display = willOpen ? '' : 'none';
    setConstraintsBtn(willOpen);
  });

  // ===== متقدّم + chips =====
  function addHiddenMember(id){
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'members[]';
    input.value = String(id);
    sink.appendChild(input);
  }
  function removeHiddenMember(id){
    Array.from(sink.querySelectorAll('input[name="members[]"]')).forEach(el=>{
      if (String(el.value)===String(id)) el.remove();
    });
  }
  function toggleChip(chip){
    const id = chip.getAttribute('data-id');
    const active = chip.classList.toggle('is-selected');
    chip.setAttribute('aria-pressed', active ? 'true' : 'false');
    if (active) addHiddenMember(id); else removeHiddenMember(id);
  }

  // تبديل وضع المتقدّم
  function enableAdvancedMode(){
    advBox.style.display = '';
    setAdvBtn(true);
    // تعطيل متطلبات الثنائي
    selA && selA.removeAttribute('required');
    selB && selB.removeAttribute('required');
    modeHidden.value = 'advanced';
  }
  function disableAdvancedMode(){
    advBox.style.display = 'none';
    setAdvBtn(false);
    // تنظيف اختيارات المتقدّم
    sink.innerHTML = '';
    chips && chips.querySelectorAll('.chip.is-selected').forEach(c=>c.classList.remove('is-selected'));
    // إعادة متطلبات الثنائي
    selA && selA.setAttribute('required','required');
    selB && selB.setAttribute('required','required');
    modeHidden.value = 'pair';
  }

  advBtn && advBtn.addEventListener('click', ()=>{
    const willOpen = isHidden(advBox);
    if (willOpen) enableAdvancedMode(); else disableAdvancedMode();
  });

  // تفاعل الشيبس
  chips && chips.addEventListener('click',(e)=>{
    const t = e.target.closest('.chip'); if (!t) return;
    e.preventDefault(); toggleChip(t); t.blur();
  });
  chips && chips.addEventListener('keydown',(e)=>{
    if (e.key==='Enter' || e.key===' ') {
      const t = e.target.closest('.chip'); if (!t) return;
      e.preventDefault(); toggleChip(t);
    }
  });

  // ===== إرسال AJAX موحّد لكل النماذج داخل الصفحة =====
  const root = document.getElementById('page-root');

  function getCsrf(){
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }
  function showErrors(messages){
    alert(Array.isArray(messages) ? messages.join('\n') : (messages || 'حدث خطأ غير متوقع.'));
  }

  async function submitAjaxForm(form, actionUrl){
    try{
      // تحقق خاص بنموذج "إضافة قيد" قبل الإرسال
      if (form === addConstraintForm) {
        if (modeHidden.value === 'advanced') {
          const count = sink.querySelectorAll('input[name="members[]"]').length;
          if (count < 2) {
            showErrors('في الوضع المتقدّم يجب اختيار طالبين على الأقل.');
            return;
          }
          // عند المتقدّم: أفرغ الثنائي لتجنّب إرباك الباك-إند
          if (selA) selA.value = '';
          if (selB) selB.value = '';
        } else {
          // عند الثنائي: نظّف أي members[] متبقّي
          sink.innerHTML = '';
        }
      }

      const fd = new FormData(form);
      fd.append('via','ajax');

      const method = (form.getAttribute('method') || 'POST').toUpperCase();

      const res = await fetch(actionUrl, {
        method,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrf()
        },
        body: fd
      });

      const ctype = res.headers.get('Content-Type') || '';

      if (!ctype.includes('application/json')) {
        const html = await res.text();
        if (res.ok) {
          document.open(); document.write(html); document.close();
        } else {
          showErrors('تعذّر التنفيذ (HTML). رمز الحالة: ' + res.status);
        }
        return;
      }

      const data = await res.json();

      if (!res.ok) {
        if (res.status === 422 && data?.errors) {
          const msgs = [];
          Object.values(data.errors).forEach(arr => arr.forEach(m => msgs.push(m)));
          showErrors(msgs.length?msgs:'تحقق من الحقول');
        } else {
          showErrors(data?.message || 'تعذّر الإجراء. حاول لاحقًا.');
        }
        return;
      }

      if (data?.redirect) {
        window.location.assign(data.redirect);
      } else if (data?.reload) {
        window.location.reload();
      } else {
        window.location.reload();
      }
    }catch(e){
      showErrors('فشل الاتصال بالخادم. تأكّد من الشبكة وحاول مجددًا.');
    }
  }

  // اعتراض جميع النماذج داخل الصفحة + إصلاح action
  root && root.addEventListener('submit', function(e){
    const form = e.target.closest('form');
    if (!form) return;
    e.preventDefault();

    const submitter = e.submitter || document.activeElement;

    let action =
      (submitter && submitter.getAttribute && submitter.getAttribute('formaction')) ||
      form.getAttribute('action') ||
      form.action || '';

    if (action.includes('[object')) {
      action = form.getAttribute('action') || '';
    }
    try { action = new URL(action, window.location.href).toString(); } catch(_) {}

    submitAjaxForm(form, action);
  });

  // 🔧 افتراضيًا: الثنائي مُفعّل ويتطلّب required
  // لو حاب تفتح المتقدّم تلقائيًا عند تحميل الصفحة، استدعِ:
  // enableAdvancedMode();

})();
</script>
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
@endpush
