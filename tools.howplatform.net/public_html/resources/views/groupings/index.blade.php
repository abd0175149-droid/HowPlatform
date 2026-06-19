@extends('layouts.app')
@section('title','توزيع المجموعات')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/groupings.css') }}">
@endpush
@section('sidebar')
  @include('partials.sidebar-groupings')
@endsection

@section('content')
<div id="page-root" class="container py-3 shell" tabindex="-1">

  <div class="panel">
    <div class="panel-title">
      <i class="fas fa-clone"></i> مجموعاتي
      <a href="{{ route('user.groupings.create') }}" class="btn btn-primary" style="margin-inline-start:auto">
        <i class="fas fa-plus-circle"></i> مجموعة جديدة
      </a>
    </div>

    @if(session('ok'))
      <div class="badge badge-green auto-dismiss"><i class="fas fa-check-circle"></i> {{ session('ok') }}</div>
    @endif

    @if($sessions->isEmpty())
      <div class="muted">لا توجد مجموعات توزيع بعد. ابدأ بمجموعة جديدة.</div>
    @else
      <div class="list">
        @foreach($sessions as $s)
          <div class="card">
            <div class="row">
              <div style="font-weight:700">{{ $s->title ?: 'مجموعة بدون عنوان' }}</div>
              <div class="muted">{{ $s->created_at }}</div>
            </div>
            <div class="muted" style="margin-top:.25rem">الصف: <strong>{{ $s->class_name }}</strong></div>
            <div class="badges">
              <span class="badge badge-blue">
                <i class="fas fa-sliders-h"></i>
                {{ $s->mode==='by_size' ? 'حسب الحجم' : 'حسب العدد' }}
                @if($s->mode==='by_size') ({{ (int)$s->group_size }}) @endif
                @if($s->mode==='by_count') ({{ (int)$s->group_count }}) @endif
              </span>
              <span class="badge"><i class="fas fa-tag"></i>
                @switch($s->major_mode)
                  @case('per_major') مجموعة لكل تخصّص @break
                  @case('bundle_majors') تجميع تخصّصات @break
                  @case('mixed') مختلط @break
                  @default بدون تخصّص
                @endswitch
              </span>
              @if(!empty($s->seed))
                <span class="badge"><i class="fas fa-seedling"></i>توزيع ثابت</span>
              @endif
              <span class="badge {{ $s->status==='final' ? 'badge-green' : '' }}"><i class="fas fa-flag-checkered"></i> {{ $s->status==='final' ? 'نهائي' : 'مسودة' }}</span>
              <span class="badge"><i class="fas fa-layer-group"></i> مجموعات: {{ (int)$s->groups_count }}</span>
            </div>

            <div class="row" style="margin-top:.6rem; gap:.5rem">
              <a class="btn" href="{{ route('user.groupings.constraints', $s->id) }}"><i class="fas fa-user-cog"></i> القيود/التوليد</a>
              <a class="btn" href="{{ route('user.groupings.show', $s->id) }}"><i class="fas fa-eye"></i> العرض</a>

              {{-- حذف = POST + DELETE (AJAX) --}}
              <form method="POST" action="{{ route('user.groupings.destroy', $s->id) }}" style="margin-inline-start:auto">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger"
                        data-confirm="حذف هذه المجموعة وكافة نتائجها؟">
                  <i class="fas fa-trash"></i> حذف
                </button>
              </form>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

</div>
@endsection

@push('scripts')
<script>
(function(){
  // ===== AJAX موحّد لكل النماذج في الصفحة (خاصة الحذف) =====
  const root = document.getElementById('page-root');

  function getCsrf(){
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }
  function showErrors(messages){
    alert(Array.isArray(messages) ? messages.join('\\n') : (messages || 'حدث خطأ غير متوقع.'));
  }

  async function submitAjaxForm(form, actionUrl){
    try{
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

      // لو رجع HTML (نادر هنا) اعرضه مباشرة
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
      } else if (data?.reload || data?.ok) {
        window.location.reload();
      } else {
        window.location.reload();
      }
    }catch(e){
      showErrors('فشل الاتصال بالخادم. تأكّد من الشبكة وحاول مجددًا.');
    }
  }

  // اعتراض الإرسال + إصلاح action عبر submitter/formaction
  root && root.addEventListener('submit', function(e){
    const form = e.target.closest('form');
    if (!form) return;
    e.preventDefault();

    // تأكيد مخصص للحذف (إن وُجد data-confirm على الزر)
    const submitter = e.submitter || document.activeElement;
    const confirmMsg = submitter?.getAttribute?.('data-confirm');
    if (confirmMsg && !confirm(confirmMsg)) return;

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