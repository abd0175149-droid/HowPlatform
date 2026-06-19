@extends('layouts.app')
@section('title','إنشاء عجلة دوّارة')

@push('styles')
<style>
  /* Flat Design Overrides */
  .card-flat {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #e5e7eb;
    box-shadow: none;
    margin-bottom: 1.5rem;
  }
  .input-tabs { 
    display: flex; gap: 0.5rem; margin-bottom: 2rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0;
  }
  .input-tab { 
    padding: 0.75rem 1.5rem; cursor: pointer; border-bottom: 3px solid transparent; 
    font-weight: 600; color: #6b7280; border-radius: 8px 8px 0 0; transition: all 0.2s;
  }
  .input-tab:hover { background: #f9fafb; color: #374151; }
  .input-tab.active { 
    border-bottom-color: var(--primary, #219ebc); color: var(--primary, #219ebc); background: #f0f9ff;
  }
  .tab-pane { display: none; }
  .tab-pane.active { display: block; animation: fadeIn 0.3s ease; }
  
  .save-class-box { 
    background: #f8fafc; padding: 1.5rem; border-radius: 12px; margin-top: 1rem; border: 1px dashed #cbd5e1; 
  }
  .form-row { margin-bottom: 1.5rem; }
  .form-row label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #334155; }
  .form-row .input, .form-row select, .form-row textarea { 
    width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; 
    background: #fff; transition: border-color 0.2s;
  }
  .form-row .input:focus, .form-row select:focus, .form-row textarea:focus { 
    border-color: var(--primary, #219ebc); outline: none; 
  }
  
  .btn-flat {
    background: var(--primary, #219ebc); color: #fff; border: none; padding: 0.75rem 1.5rem;
    border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s;
  }
  .btn-flat:hover { background: #1a829c; }
  .btn-outline-flat {
    background: transparent; color: #475569; border: 1px solid #cbd5e1; padding: 0.75rem 1.5rem;
    border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; text-decoration: none;
  }
  .btn-outline-flat:hover { background: #f1f5f9; color: #334155; }

  @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

  .radio-group { display: flex; gap: 1.5rem; }
  .radio-card {
    display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; border: 1px solid #cbd5e1; 
    border-radius: 8px; cursor: pointer; background: #fff; transition: all 0.2s;
  }
  .radio-card:hover { border-color: var(--primary, #219ebc); background: #f0f9ff; }
  .radio-card input { margin: 0; accent-color: var(--primary, #219ebc); }
</style>
@endpush

@section('sidebar')
  @include('partials.sidebar-spinners')
@endsection

@section('content')
<div class="page-head" style="margin-bottom: 2rem;">
  <div style="display:flex; align-items:center; gap:1rem;">
    <div style="background:#e0f2fe; color:#0284c7; width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.5rem;">
      <i class="fas fa-dharmachakra"></i>
    </div>
    <div>
      <h1 style="margin:0; font-size:1.5rem; font-weight:800; color:#0f172a;">إنشاء عجلة دوّارة</h1>
      <div style="font-size:0.875rem; color:#64748b;">اختر الأسماء من صف موجود أو أضف أسماء جديدة لتوزيع عادل</div>
    </div>
  </div>
</div>

@if($classes->isEmpty() && !request()->has('manual'))
  <div class="card-flat" style="background: #fffbeb; border-color: #fde68a;">
    <p style="margin:0; color:#b45309;"><i class="fas fa-info-circle"></i> لا تملك صفوفًا حالياً للتبويب المباشر. يمكنك إنشاء العجلة عبر تبويب الإدخال اليدوي أدناه.</p>
  </div>
@endif

<form method="post" action="{{ route('user.spinners.store') }}" id="spinner-form">
  @csrf
  <input type="hidden" name="input_mode" id="input_mode" value="class">
  <input type="hidden" name="spin_ms" value="3000"> {{-- 3 seconds exactly to match audio --}}

  <div class="card-flat">
    <div class="input-tabs">
      <div class="input-tab active" data-target="tab-class" onclick="switchTab('class')">
        <i class="fas fa-users" style="margin-inline-end: 0.5rem;"></i> من صف حالي
      </div>
      <div class="input-tab" data-target="tab-manual" onclick="switchTab('manual')">
        <i class="fas fa-keyboard" style="margin-inline-end: 0.5rem;"></i> إدخال يدوي
      </div>
    </div>

    <div id="tab-class" class="tab-pane active">
      <div class="form-row">
        <label>اختر الصف الدراسي <span style="color:red">*</span></label>
        <select name="class_id" id="class_id" class="input">
          <option value="">-- اضغط للاختيار --</option>
          @foreach($classes as $c)
            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->students_count }} طالب)</option>
          @endforeach
        </select>
      </div>
    </div>

    <div id="tab-manual" class="tab-pane">
      <div class="form-row">
        <label>أسماء الطلاب <span style="color:red">*</span></label>
        <div style="font-size:0.85rem; color:#64748b; margin-bottom:0.5rem;">اكتب كل اسم في سطر منفصل</div>
        <textarea name="names_text" id="names_text" rows="6" class="input" placeholder="أحمد محمد&#10;خالد علي&#10;سارة أحمد..."></textarea>
      </div>
      
      <div class="form-row">
        <label class="radio-card" style="display:inline-flex;">
          <input type="checkbox" name="save_as_class" id="save_as_class" value="1" onchange="toggleSaveBox()">
          <strong>حفظ هذه الأسماء كصف دراسي جديد في النظام</strong>
        </label>
      </div>

      <div class="save-class-box" id="save_class_box" style="display:none;">
        <div class="form-row">
          <label>اسم الصف الجديد <span style="color:red">*</span></label>
          <input type="text" name="new_class_name" id="new_class_name" class="input" placeholder="مثال: الصف التاسع (أ)" />
        </div>
        <div class="form-row" style="margin-bottom:0;">
          <label>المرحلة الدراسية (اختياري)</label>
          <input type="text" name="new_class_grade" class="input" placeholder="أساسي، ثانوي، الخ..." />
        </div>
      </div>
    </div>
  </div>

  <div class="card-flat">
    <h3 style="margin-top:0; margin-bottom:1.5rem; font-size:1.25rem; font-weight:800; color:#0f172a; border-bottom:1px solid #e5e7eb; padding-bottom:1rem;">إعدادات العجلة التفاعلية</h3>

    <div class="form-row">
      <label>عنوان العجلة (اختياري)</label>
      <input type="text" name="title" maxlength="190" class="input" placeholder="مثال: عجلة اختيار الإجابات..." />
    </div>

    <div class="form-row">
      <label>قاعدة الحذف التلقائي (للاسم الفائز)</label>
      <select name="remove_rule" class="input">
        <option value="none">بدون حذف (الاحتفاظ بجميع الأسماء)</option>
        <option value="remove_selected">حذف الفائز فقط من العجلة (لن يُحذف من الصف)</option>
        <option value="remove_others">حذف البقية والإبقاء على الفائز فقط</option>
      </select>
    </div>

    <div class="form-row">
      <label>التأثيرات الصوتية</label>
      <div class="radio-group">
        <label class="radio-card">
          <input type="radio" name="sound_on" value="1" checked> 
          <span><i class="fas fa-volume-up" style="color:#0ea5e9;"></i> مفعّل</span>
        </label>
        <label class="radio-card">
          <input type="radio" name="sound_on" value="0"> 
          <span><i class="fas fa-volume-mute" style="color:#94a3b8;"></i> صامت</span>
        </label>
      </div>
    </div>
  </div>

  <div style="display:flex; gap:1rem; margin-top:2rem;">
    <button type="submit" class="btn-flat" onclick="return validateForm()">
      <i class="fas fa-magic" style="margin-inline-end: 0.5rem;"></i> حفظ وإنشاء العجلة
    </button>
    <a class="btn-outline-flat" href="{{ route('user.spinners.index') }}">إلغاء</a>
  </div>
</form>
@endsection

@push('scripts')
<script>
  function switchTab(mode) {
    document.getElementById('input_mode').value = mode;
    document.querySelectorAll('.input-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    
    document.querySelector(`.input-tab[data-target="tab-${mode}"]`).classList.add('active');
    document.getElementById(`tab-${mode}`).classList.add('active');
  }

  function toggleSaveBox() {
    const isChecked = document.getElementById('save_as_class').checked;
    document.getElementById('save_class_box').style.display = isChecked ? 'block' : 'none';
  }

  function validateForm() {
    const mode = document.getElementById('input_mode').value;
    if (mode === 'class') {
      const cid = document.getElementById('class_id').value;
      if (!cid) {
        alert("الرجاء اختيار صف أو استخدام خيار الإدخال اليدوي.");
        return false;
      }
    } else {
      const names = document.getElementById('names_text').value.trim();
      if (!names) {
        alert("الرجاء إدخال أسماء الطلاب للإدخال اليدوي.");
        return false;
      }
      if (document.getElementById('save_as_class').checked) {
        const cname = document.getElementById('new_class_name').value.trim();
        if (!cname) {
          alert("الرجاء إدخال اسم الصف الجديد.");
          return false;
        }
      }
    }
    return true;
  }
</script>
@endpush
