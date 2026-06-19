@extends('layouts.app')
@section('title','طلاب الصف')

@push('styles')
<style>
  :root{
    --primary-hover:#3b82f6;
    --transition:all .2s ease-in-out;
    --card-hover-shadow:0 5px 12px -3px rgba(0,0,0,.1);
  }

  .classes-shell{display:flex;flex-direction:column;gap:1.25rem;max-width:1200px;margin:0 auto}
  .panel{background:var(--surface-card);border:1px solid var(--border-color);border-radius:12px;box-shadow:var(--shadow-sm);padding:1.25rem;transition:var(--transition)}
  .panel-title{font-weight:700;margin-bottom:.75rem;color:var(--text-primary);display:flex;align-items:center;gap:.5rem;font-size:1.1rem;padding-bottom:.5rem;border-bottom:1px solid var(--border-color)}

  /* إحصائيات بنفس ستايل صفحة الصفوف */
  .stats-container{background:var(--surface-card);}
  .stats-row{display:flex;gap:.75rem;justify-content:space-between}
  .stat-card{flex:1;text-align:center;padding:.75rem .5rem;border-radius:8px;background:var(--surface-base);border:1px solid var(--border-color);transition:var(--transition)}
  .stat-card:hover{transform:translateY(-1px);box-shadow:0 2px 4px -1px rgba(0,0,0,.05);background:var(--primary-100)}
  .stat-value{font-size:1.5rem;font-weight:700;color:var(--primary-600);margin-bottom:.2rem;line-height:1}
  .stat-label{font-size:.8rem;color:var(--text-secondary)}
  @media(max-width:768px){.stats-row{flex-direction:column;gap:.5rem}.panel{padding:1rem}.stats-container{padding:.75rem}}

  /* صندوق عدّاد مع تأكيد ألوان النص */
  .count-box{background:var(--primary-100);border:1px solid var(--primary-200);border-radius:10px;padding:.9rem;text-align:center}
  .count-box .count-value{color:var(--primary-700)!important;font-weight:800;font-size:1.25rem}
  .count-box .count-label{color:var(--primary-800)!important;font-size:.85rem}
  .btn{display:inline-flex;align-items:center;gap:.4rem;border:1px solid var(--border-color);border-radius:8px;padding:.55rem .9rem;background:var(--surface-base);cursor:pointer;font-weight:500;transition:var(--transition);text-decoration:none;font-size:.9rem}
  .btn:hover{transform:translateY(-1px)}
  .btn-primary{background:var(--primary-500);border-color:var(--primary-500);color:#fff}
  .btn-primary:hover{background:var(--primary-600);border-color:var(--primary-50);box-shadow:0 2px 4px -1px rgba(59,130,246,.3)}
  .btn-ghost{background:transparent;border-color:var(--border-color)}
  .btn-ghost:hover{background:var(--surface-weak)}
  .btn-danger{background:#dc2626;border-color:#dc2626;color:#fff}
  .btn-danger:hover{background:#b91c1c;border-color:#b91c1c;box-shadow:0 2px 4px -1px rgba(220,38,38,.3)}
  .btn[disabled]{opacity:.6;cursor:not-allowed;transform:none}
  .muted{color:var(--text-secondary);font-size:.9rem}

  .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
  @media(max-width:900px){.grid-2{grid-template-columns:1fr}}

  .inline-list{display:flex;flex-wrap:wrap;gap:.5rem}
  .badge{display:inline-flex;align-items:center;gap:.35rem;border:1px solid var(--border-color);border-radius:999px;padding:.25rem .6rem;font-size:.8rem;background:var(--surface-card)}
  .badge-info{background:var(--primary-100);border-color:var(--primary-300);color:var(--primary-800)}
  .badge-soft{background:var(--surface-weak);color:var(--primary-700)}

  .student-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.75rem}
  .student-item{border:1px solid var(--border-color);border-radius:10px;padding:.7rem;background:var(--surface-base);display:flex;align-items:center;gap:.6rem;justify-content:space-between}
  .student-name{font-weight:600;color:var(--text-primary)}
  .student-actions{display:flex;gap:.35rem}
  .icon-btn{border:none;background:transparent;cursor:pointer;padding:.35rem;border-radius:6px;transition:var(--transition);display:inline-flex;align-items:center;justify-content:center;color:var(--text-secondary)}
  .icon-btn:hover{background:var(--surface-weak);color:var(--text-primary)}
  .student-edit-input{width:100%;padding:.45rem .6rem;border:1px solid var(--border-color);border-radius:8px;background:var(--surface-base)}

  .preview-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.6rem}
  .preview-item{border:1px solid var(--border-color);border-radius:10px;padding:.6rem;background:var(--surface-base);display:flex;align-items:center;justify-content:space-between;gap:.5rem}
  .pill{display:inline-flex;align-items:center;gap:.4rem;border:1px dashed var(--border-color);border-radius:999px;padding:.25rem .55rem;font-size:.8rem;margin-top:15px;}

  .file-heads{display:flex;flex-wrap:wrap;gap:.5rem}
  .file-heads .head{padding:.35rem .6rem;border:1px solid var(--border-color);border-radius:8px;background:var(--surface-base)}
  .text-xs{font-size:.8rem}
  .text-danger{color:#dc2626}

  /* تحسينات طفيفة للوصول والوضوح عند التعديل */
  .student-item.is-editing { outline:2px solid var(--primary-200); background:var(--primary-50); }
  .icon-btn:focus-visible, .btn:focus-visible { outline:2px solid var(--primary-400); outline-offset:2px; }
</style>
@endpush

@section('sidebar')
  @include('partials.sidebar-classes')
@endsection

@section('content')
<div class="container py-3 classes-shell">

  {{-- عنوان الصفحة --}}
  <div class="panel">
    <div class="panel-title">
      <i class="fas fa-users"></i>
      <span>طلاب الصف: {{ $class->name }}</span>
      <span class="inline-list" style="margin-inline-start:auto">
        @if(!empty($class->year))
          <span class="badge badge-soft"><i class="fas fa-calendar-alt"></i> {{ $class->year }}</span>
        @endif
        @if(!empty($class->grade_level))
          <span class="badge badge-soft"><i class="fas a fa-layer-group"></i> {{ $class->grade_level }}</span>
        @endif
      </span>
    </div>

    {{-- إحصائيات --}}
    <div class="stats-container">
      @php
        $total = (int)($counts['total'] ?? 0);
        $max   = (int)($counts['max'] ?? 50);
        $left  = (int)($counts['left'] ?? max(0, $max - $total));
        $occ   = $max > 0 ? round(($total / $max) * 100, 1) : 0;
      @endphp
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-value">{{ $total }}</div>
          <div class="stat-label">الطلاب الحاليون</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ $left }}</div>
          <div class="stat-label">المتبقي حتى الحد</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ $max }}</div>
          <div class="stat-label">الحد الأقصى للطلاب</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ $occ }}%</div>
          <div class="stat-label">نسبة الإشغال</div>
        </div>
      </div>
    </div>

    @if(session('ok'))
      <div class="pill auto-dismiss" style="border-color:#16a34a;color:#166534;background:rgba(22,101,52,.08)">
        <i class="fas fa-check-circle"></i> {{ session('ok') }}
      </div>
    @endif
    @if($errors->any())
      <div class="pill text-danger auto-dismiss" style="border-color:#dc2626;background:rgba(220,38,38,.08)">
        <i class="fas fa-exclamation-triangle"></i> {{ implode(' - ', $errors->all()) }}
      </div>
    @endif
  </div>

  {{-- إدخال الأسماء (ملف أو نص) --}}
  <div class="panel">
    <div class="panel-title">
      <i class="fas fa-file-import"></i> إضافة طلاب
      <span class="muted" style="margin-inline-start:auto">الحد الأقصى: {{ $max }} طالب</span>
    </div>

    <div class="grid-2">

      {{-- من ملف --}}
      <div>
        <div class="muted" style="margin-bottom:.5rem">
          ارفع ملف <strong>Excel/CSV</strong>. سنقرأ الصف الأول كعناوين لتختار عمود الاسم.
        </div>

        <form method="POST" action="{{ route('user.classes.students.preview', $class->id) }}" enctype="multipart/form-data" class="upload-form" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
          @csrf
          <label class="btn-ghost" style="display:inline-flex;align-items:center;gap:.5rem;padding:.55rem .9rem;border-radius:8px;border-style:dashed;cursor:pointer">
            <i class="fas fa-folder-open"></i>
            <span id="file-label-text">اختر ملفاً…</span>
            <input type="file" name="names_file" id="names_file" accept=".xlsx,.xls,.csv,.txt" required style="display:none">
          </label>

          <button type="submit" class="btn btn-primary" id="upload-submit" disabled>
            <i class="fas fa-upload"></i> تحميل ومعاينة
          </button>

          <div class="muted text-xs" style="margin-inline-end:auto">
            الصيغ المدعومة: XLSX, XLS, CSV, TXT
          </div>
        </form>

        {{-- اختيار الأعمدة بعد الرفع --}}
        @if(!empty($headings))
          <hr style="margin:1rem 0;opacity:.25"/>
          <form method="POST" action="{{ route('user.classes.students.preview', $class->id) }}">
            @csrf
            <input type="hidden" name="file_token" value="{{ $fileToken }}">
            <div class="inline-list" style="align-items:center;gap:.5rem;flex-wrap:wrap">
              <span class="muted">اختر عمود الاسم:</span>
              <select name="name_col" class="btn-ghost" required>
                @foreach($headings as $i=>$label)
                  <option value="{{ $i }}">{{ $label ?: ('عمود '.($i+1)) }}</option>
                @endforeach
              </select>

              @if(!empty($allowsMajor) && $allowsMajor)
                <span class="muted">– و(اختياريًا) عمود التخصص:</span>
                <select name="major_col" class="btn-ghost">
                  <option value="">— بدون —</option>
                  @foreach($headings as $i=>$label)
                    <option value="{{ $i }}">{{ $label ?: ('عمود '.($i+1)) }}</option>
                  @endforeach
                </select>
              @endif

              <button type="submit" class="btn btn-primary">
                <i class="fas fa-eye"></i> استخراج ومعاينة
              </button>
            </div>
            <div class="file-heads text-xs" style="margin-top:.5rem">
              @foreach($headings as $i=>$label)
                <span class="head">{{ $label ?: ('عمود '.($i+1)) }}</span>
              @endforeach
            </div>
          </form>
        @endif
      </div>

      {{-- من نص متعدد الأسطر (موحّد: الاسم | التخصص اختياري) --}}
      <div>
        <div class="muted" style="margin-bottom:.5rem">
          ألصق الأسماء — <strong>كل طالب في سطر</strong>:
          <span class="badge badge-info">الاسم | التخصص (اختياري)</span>
        </div>

        <form method="POST" action="{{ route('user.classes.students.preview', $class->id) }}">
          @csrf

          <label class="muted" style="display:block;margin-bottom:.35rem">الأسماء</label>
          <textarea name="names_text" rows="7" class="student-edit-input" placeholder="مثال:
أحمد علي | حاسوب
سارة محمد | رياضيات
محمد خالد
..."></textarea>

          <div class="muted text-xs" style="margin-top:.35rem">
            يمكن ترك التخصص فارغًا. سيتم تجاهل الزائد إن تجاوز {{ $left }} متاحاً.
          </div>

          <div style="display:flex;gap:.5rem;margin-top:.6rem;align-items:center;flex-wrap:wrap">

            <button type="submit" class="btn btn-primary" style="margin-inline-start:auto">
              <i class="fas fa-eye"></i> معاينة
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

  {{-- معاينة قبل الحفظ --}}
  @if(!empty($preview))
    <div class="panel">
      <div class="panel-title">
        <i class="fas fa-list-check"></i> معاينة الأسماء ({{ count($preview) }})
        @if(!empty($dropped))
          <span class="badge badge-info"><i class="fas fa-info-circle"></i> تم استثناء {{ count($dropped) }}</span>
        @endif
      </div>

      <form method="POST" action="{{ route('user.classes.students.store', $class->id) }}" id="preview-save-form">
        @csrf

        <div class="inline-list" style="margin-bottom:.6rem;align-items:center;gap:.75rem;flex-wrap:wrap">
          <label class="muted" style="display:inline-flex;align-items:center;gap:.4rem">
            <input type="checkbox" name="replace_all" value="1">
            <span>استبدال القائمة الحالية (حذف كل الطلاب الحاليين قبل الإضافة)</span>
          </label>

          <button type="button" class="btn btn-ghost" id="btn-clear-preview">
            <i class="fas fa-broom"></i> حذف كل عناصر المعاينة
          </button>
        </div>

        <div class="preview-list" id="preview-list">
          @foreach($preview as $i => $st)
            <div class="preview-item" data-index="{{ $i }}">
              <div style="display:flex;flex-direction:column;gap:.2rem">
                <span><strong>{{ $st['name'] }}</strong></span>
                <span class="muted text-xs">
                  التخصص:
                  @if(array_key_exists('major',$st) && $st['major'])
                    {{ $st['major'] }}
                  @else
                    بلا تخصص
                  @endif
                </span>
              </div>
              <div>
                <button type="button" class="icon-btn btn-remove" title="إزالة">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <input type="hidden" name="names[]"  value="{{ $st['name'] }}">
              <input type="hidden" name="majors[]" value="{{ ($st['major'] ?? '') }}">
            </div>
          @endforeach
        </div>

        <div style="display:flex;gap:.5rem;margin-top:1rem;align-items:center">
          <button type="submit" class="btn btn-primary" id="btn-save-preview">
            <i class="fas fa-save"></i> حفظ الطلاب
          </button>
          <span class="muted">سيتم الإضافة إلى هذا الصف مباشرة.</span>
        </div>
      </form>
    </div>
  @endif

  {{-- الطلاب الحاليون --}}
  <div class="panel">
    <div class="panel-title" style="gap:.75rem;flex-wrap:wrap">
      <i class="fas fa-users"></i> قائمة الطلاب
      <span class="muted" style="margin-inline-start:auto">{{ $total }} / {{ $max }}</span>

      @if(!$students->isEmpty())
        {{-- أدوات الحذف الجماعي --}}
        <div style="display:flex;align-items:center;gap:.75rem;margin-inline-start:auto;flex-wrap:wrap">
          <label class="muted" style="display:inline-flex;align-items:center;gap:.4rem;cursor:pointer">
            <input type="checkbox" id="select-all">
            <span>تحديد الكل</span>
          </label>

          <form id="bulk-delete-form"
                method="POST"
                action="{{ route('user.classes.students.destroyBulk.post', $class->id) }}"
                onsubmit="return confirm('حذف جميع الطلاب المحددين؟');"
                style="display:inline-flex;align-items:center;gap:.5rem">
            @csrf  
            <span class="muted text-xs" id="selected-counter" style="display:none"></span>

            <button type="submit" id="bulk-delete-btn" class="btn btn-danger" disabled>
              <i class="fas fa-trash"></i> حذف المحدّد
            </button>
          </form>
        </div>
      @endif
    </div>

    @if($students->isEmpty())
      <div class="muted">لا يوجد طلاب بعد. أضف أسماء من الأعلى.</div>
    @else
      {{-- ⚠️ استبدلنا النموذج الغلاف بـ DIV لتجنّب تعشيق النماذج --}}
      <div id="bulk-checkboxes-wrapper" style="display:contents">
        <div class="student-list">
          @foreach($students as $st)
            <div class="student-item" data-id="{{ $st->id }}">
              <div style="display:flex;align-items:center;gap:.6rem;flex:1">
                <input type="checkbox" class="row-check" name="ids[]" value="{{ $st->id }}">
                <div style="flex:1">
                  <div class="view-mode">
                    <div class="student-name">{{ $st->student_name }}</div>
                    <div class="muted text-xs">
                      التخصص: {{ $st->major ? $st->major : 'بلا تخصص' }}
                    </div>
                  </div>

                  {{-- تعديل مفرد --}}
                  <form method="POST"
                        action="{{ route('user.classes.student.update', [$class->id, $st->id]) }}"
                        class="edit-form"
                        style="display:none"
                        autocomplete="off">
                    @csrf @method('PATCH')
                    <div class="grid-2">
                      <input type="text" name="student_name" value="{{ $st->student_name }}" class="student-edit-input" placeholder="الاسم">
                      <input type="text" name="major" value="{{ $st->major }}" class="student-edit-input" placeholder="التخصص (اختياري)">
                    </div>
                  </form>
                </div>
              </div>

              <div class="student-actions">
                <button type="button" class="icon-btn btn-edit" title="تعديل"><i class="fas fa-pen"></i></button>
                <button type="button" class="icon-btn btn-save" title="حفظ" style="display:none"><i class="fas fa-check"></i></button>

                {{-- حذف مفرد --}}
                <form method="POST" action="{{ route('user.classes.student.destroy', [$class->id, $st->id]) }}" onsubmit="return confirm('حذف هذا الطالب؟');">
                  @csrf @method('DELETE')
                  <button type="submit" class="icon-btn" title="حذف"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endif
  </div>

</div>
@endsection

@push('scripts')
<script>
(function(){
  // تمكين زر الرفع وتحديث نص الملصق
  const fileInput = document.getElementById('names_file');
  const labelText = document.getElementById('file-label-text');
  const uploadBtn = document.getElementById('upload-submit');
  if (fileInput && labelText && uploadBtn){
    fileInput.addEventListener('change', function(){
      const f = this.files && this.files[0] ? this.files[0].name : null;
      labelText.textContent = f ? f : 'اختر ملفاً…';
      uploadBtn.disabled = !f;
    });
  }

  // إزالة عنصر من المعاينة + حذف كل عناصر المعاينة + تعطيل زر الحفظ عند الفراغ
  const previewList = document.getElementById('preview-list');
  const clearBtn    = document.getElementById('btn-clear-preview');
  const saveBtn     = document.getElementById('btn-save-preview');

  function updatePreviewState(){
    if (!previewList || !saveBtn) return;
    const hasItems = !!previewList.querySelector('.preview-item');
    saveBtn.disabled = !hasItems;
  }

  if (previewList){
    previewList.addEventListener('click', function(e){
      const btn = e.target.closest('.btn-remove');
      if(!btn) return;
      const item = btn.closest('.preview-item');
      if(item) item.remove();
      updatePreviewState();
    });
  }

  if (clearBtn && previewList){
    clearBtn.addEventListener('click', function(){
      previewList.innerHTML = '';
      updatePreviewState();
    });
    updatePreviewState();
  }

  // --- محرّر واحد فقط مفتوح + إغلاق الكل عند الفتح ---
  function closeAllEditors() {
    document.querySelectorAll('.student-item').forEach(function(it){
      it.classList.remove('is-editing');
      const view  = it.querySelector('.view-mode');
      const form  = it.querySelector('.edit-form');
      const be    = it.querySelector('.btn-edit');
      const bs    = it.querySelector('.btn-save');
      if (view) view.style.display = '';
      if (form) form.style.display = 'none';
      if (be)   be.style.display   = '';
      if (bs)   bs.style.display   = 'none';
    });
  }

  // تبديل وضع تعديل/حفظ لطالب مفرد (نسخة محسّنة)
  document.querySelectorAll('.student-item').forEach(function(item){
    const btnEdit = item.querySelector('.btn-edit');
    const btnSave = item.querySelector('.btn-save');
    const viewEl  = item.querySelector('.view-mode');
    const form    = item.querySelector('.edit-form');
    const inputNm = form ? form.querySelector('input[name="student_name"]') : null;

    if (btnEdit && btnSave && viewEl && form) {
      // تأكيد حالة البداية سليمة:
      viewEl.style.display   = '';
      form.style.display     = 'none';
      btnEdit.style.display  = '';
      btnSave.style.display  = 'none';
      item.classList.remove('is-editing');

      btnEdit.addEventListener('click', function(){
        closeAllEditors();
        viewEl.style.display   = 'none';
        form.style.display     = '';
        btnEdit.style.display  = 'none';
        btnSave.style.display  = '';
        item.classList.add('is-editing');
        if (inputNm) { inputNm.focus(); inputNm.select(); }
      });

      btnSave.addEventListener('click', function(){
        form.submit();
      });

      // دعم Enter لحفظ و Esc لإلغاء
      form.addEventListener('keydown', function(e){
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          form.submit();
        } else if (e.key === 'Escape') {
          e.preventDefault();
          // عودة للوضع العادي:
          viewEl.style.display   = '';
          form.style.display     = 'none';
          btnEdit.style.display  = '';
          btnSave.style.display  = 'none';
          item.classList.remove('is-editing');
        }
      });
    }
  });

  // الحذف الجماعي: تحديد الكل/العدد/تفعيل الزر
  const selectAll   = document.getElementById('select-all');
  const checks      = document.querySelectorAll('.row-check');
  const bulkBtn     = document.getElementById('bulk-delete-btn');
  const counterSpan = document.getElementById('selected-counter');
  const bulkForm    = document.getElementById('bulk-delete-form');
  const bulkWrapper = document.getElementById('bulk-checkboxes-wrapper');

  function updateBulkUI(){
    let c = 0;
    checks.forEach(ch => { if (ch.checked) c++; });
    if (bulkBtn) bulkBtn.disabled = (c === 0);
    if (counterSpan){
      if (c > 0) { counterSpan.textContent = `المحدد: ${c}`; counterSpan.style.display = ''; }
      else { counterSpan.style.display = 'none'; }
    }
    if (selectAll){
      const total = checks.length;
      selectAll.indeterminate = (c > 0 && c < total);
      selectAll.checked = (c === total && total > 0);
    }
  }

  if (selectAll){
    selectAll.addEventListener('change', function(){
      checks.forEach(ch => ch.checked = selectAll.checked);
      updateBulkUI();
    });
  }
  checks.forEach(ch => ch.addEventListener('change', updateBulkUI));
  updateBulkUI();

  // عند الضغط على حذف المحدّد: انسخ ids[] من الحاوية إلى فورم الإرسال
  if (bulkForm && bulkWrapper){
    bulkForm.addEventListener('submit', function(e){
      // نتأكد أن الـ ids[] ضمن bulkForm نفسه
      const existing = bulkForm.querySelectorAll('input[name="ids[]"]');
      if (existing.length === 0) {
        const selectedChecks = bulkWrapper.querySelectorAll('input.row-check:checked');
        if (selectedChecks.length === 0) {
          e.preventDefault();
          return;
        }
        selectedChecks.forEach(ch => {
          const clone = document.createElement('input');
          clone.type = 'hidden';
          clone.name = 'ids[]';
          clone.value = ch.value;
          bulkForm.appendChild(clone);
        });
      }
    });
  }

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