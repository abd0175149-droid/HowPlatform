@extends('layouts.app')
@section('title','صفوفي الدراسية')

@push('styles')
<style>
  :root {
    --primary-hover: #3b82f6;
    --transition: all 0.2s ease-in-out;
    --card-hover-shadow: 0 5px 12px -3px rgba(0, 0, 0, 0.1);
  }
  
  .classes-shell {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    max-width: 1200px;
    margin: 0 auto;
  }
  
  .panel {
    background: var(--surface-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    padding: 1.25rem;
    transition: var(--transition);
  }
  
  .panel.edit-mode {
    border-color: var(--primary-500);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    animation: pulse-edit 2s infinite;
  }
  
  @keyframes pulse-edit {
    0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.2); }
    70% { box-shadow: 0 0 0 6px rgba(59, 130, 246, 0); }
    100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
  }
  
  .panel-title {
    font-weight: 700;
    margin-bottom: 0.75rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border-color);
  }
  
  /* تحويل النموذج إلى Flexbox */
  .form-flex {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
  }
  
  .form-group {
    flex: 1 1 calc(25% - 0.75rem);
    min-width: 200px;
    margin-bottom: 0;
  }
  
  .form-group-full {
    flex: 1 1 100%;
  }
  
  @media (max-width: 992px) {
    .form-group {
      flex: 1 1 calc(50% - 0.75rem);
    }
  }
  
  @media (max-width: 576px) {
    .form-group {
      flex: 1 1 100%;
    }
  }
  
  .label {
    font-weight: 600;
    margin-bottom: 0.35rem;
    display: block;
    color: var(--text-primary);
    font-size: 0.9rem;
  }
  
  .required::after {
    content: " *";
    color: #dc2626;
  }
  
  .input, .select, .textarea {
    width: 100%;
    padding: 0.6rem 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--surface-base);
    transition: var(--transition);
    font-size: 0.9rem;
    border: 2px solid var(--primary-300);
  }
  
  .input:focus, .select:focus, .textarea:focus {
    outline: none;
    border-color: var(--primary-500);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
  }
  
  .actions-row {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.6rem 1rem;
    background: var(--surface-base);
    cursor: pointer;
    font-weight: 500;
    transition: var(--transition);
    text-decoration: none;
    font-size: 0.9rem;
  }
  
  .btn:hover {
    transform: translateY(-1px);
  }
  
  .btn-primary {
    background: var(--primary-500);
    border-color: var(--primary-500);
    color: #fff;
  }
  
  .btn-primary:hover {
    background: var(--primary-hover);
    border-color: var(--primary-hover);
    box-shadow: 0 2px 4px -1px rgba(59, 130, 246, 0.3);
  }
  
  .btn-ghost {
    background: transparent;
    border-color: var(--border-color);
  }
  
  .btn-ghost:hover {
    background: var(--surface-weak);
  }
  
  .btn-danger {
    background: #dc2626;
    border-color: #dc2626;
    color: #fff;
  }
  
  .btn-danger:hover {
    background: #b91c1c;
    border-color: #b91c1c;
    box-shadow: 0 2px 4px -1px rgba(220, 38, 38, 0.3);
  }
  
  .btn[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
  }
  
  .list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
  }
  
  @media (max-width: 768px) {
    .list {
      grid-template-columns: 1fr;
    }
  }
  
  .card {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 1rem;
    background: var(--surface-base);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
  }
  
  .card:hover {
    transform: translateY(-2px);
    box-shadow: var(--card-hover-shadow);
  }
  
  .card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--primary-500);
  }
  
  .card.edit-highlight {
    border-color: var(--primary-500);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    animation: highlight-card 1.5s ease-in-out;
  }
  
  @keyframes highlight-card {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
  }
  
  .card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
  }
  
  .card-title {
    font-weight: 700;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--text-primary);
  }
  
  .badge-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.5rem;
  }
  
  .badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border: 1px solid var(--border-color);
    border-radius: 999px;
    padding: 0.25rem 0.6rem;
    font-size: 0.8rem;
    background: var(--surface-card);
  }
  
  .badge-student {
    background: var(--primary-300);
    border-color: var(--primary-500);
    color:var(--primary-700);
  }
  
  .badge-grade {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
    color: #2563eb;
  }
  
  .badge-year {
    background: rgba(168, 85, 247, 0.1);
    border-color: rgba(168, 85, 247, 0.3);
    color: #7c3aed;
  }
  
  .badge-archived {
    background: rgba(107, 114, 128, 0.1);
    border-color: rgba(107, 114, 128, 0.3);
    color: #6b7280;
  }
  .badge-active{
  background: rgba(34,197,94,.1);
  border-color: rgba(34,197,94,.3);
  color: #16a34a;
}

  .muted {
    color: var(--text-secondary);
    font-size: 0.85rem;
    line-height: 1.4;
  }
  
  .icon-btn {
    border: none;
    background: transparent;
    cursor: pointer;
    padding: 0.4rem;
    border-radius: 6px;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
  }
  
  .icon-btn:hover {
    background: var(--surface-weak);
    color: var(--text-primary);
  }
  
  .inline-sep {
    width: 1px;
    height: 1.1rem;
    background: var(--border-color);
    margin: 0 0.2rem;
  }
  
  .hint {
    background: var(--surface-weak);
    border: 1px dashed var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
    color: var(--text-secondary);
  }
  
  .hint i {
    font-size: 1.5rem;
    margin-bottom: 0.75rem;
    color: var(--primary-500);
  }
  
  /* إطار الإحصائيات المحسن */
  .stats-container {
    background: var(--surface-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
  }
  
  .stats-row {
    display: flex;
    gap: 0.75rem;
    justify-content: space-between;
  }
  
  .stat-card {
    flex: 1;
    text-align: center;
    padding: 0.75rem 0.5rem;
    border-radius: 8px;
    background: var(--surface-base);
    border: 1px solid var(--border-color);
    transition: var(--transition);
  }
  
  .stat-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.05);
        background: var(--primary-100);
  }
  
  .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-500);
    margin-bottom: 0.2rem;
    line-height: 1;
  }
  
  .stat-label {
    font-size: 0.8rem;
    color: var(--text-secondary);
  }
  
  @media (max-width: 768px) {
    .stats-row {
      flex-direction: column;
      gap: 0.5rem;
    }
    
    .panel {
      padding: 1rem;
    }
    
    .stats-container {
      padding: 0.75rem;
    }
  }
  
  .form-actions {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-color);
    margin-top: 0.75rem;
    width: 100%;
  }
  
  .empty-state {
    text-align: center;
    padding: 1.5rem;
    color: var(--text-secondary);
  }
  
  .empty-state i {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    color: var(--border-color);
  }
  
  .empty-state h3 {
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
  }

  .checkbox-wrapper-7 .tgl {
    display: none;
  }
  .checkbox-wrapper-7 .tgl,
  .checkbox-wrapper-7 .tgl:after,
  .checkbox-wrapper-7 .tgl:before,
  .checkbox-wrapper-7 .tgl *,
  .checkbox-wrapper-7 .tgl *:after,
  .checkbox-wrapper-7 .tgl *:before,
  .checkbox-wrapper-7 .tgl + .tgl-btn {
    box-sizing: border-box;
  }
  .checkbox-wrapper-7 .tgl::-moz-selection,
  .checkbox-wrapper-7 .tgl:after::-moz-selection,
  .checkbox-wrapper-7 .tgl:before::-moz-selection,
  .checkbox-wrapper-7 .tgl *::-moz-selection,
  .checkbox-wrapper-7 .tgl *:after::-moz-selection,
  .checkbox-wrapper-7 .tgl *:before::-moz-selection,
  .checkbox-wrapper-7 .tgl + .tgl-btn::-moz-selection,
  .checkbox-wrapper-7 .tgl::selection,
  .checkbox-wrapper-7 .tgl:after::selection,
  .checkbox-wrapper-7 .tgl:before::selection,
  .checkbox-wrapper-7 .tgl *::selection,
  .checkbox-wrapper-7 .tgl *:after::selection,
  .checkbox-wrapper-7 .tgl *:before::selection,
  .checkbox-wrapper-7 .tgl + .tgl-btn::selection {
    background: none;
  }
  .checkbox-wrapper-7 .tgl + .tgl-btn {
    outline: 0;
    display: block;
    width: 4em;
    height: 2em;
    position: relative;
    cursor: pointer;
    -webkit-user-select: none;
       -moz-user-select: none;
        -ms-user-select: none;
            user-select: none;
  }
  .checkbox-wrapper-7 .tgl + .tgl-btn:after,
  .checkbox-wrapper-7 .tgl + .tgl-btn:before {
    position: relative;
    display: block;
    content: "";
    width: 50%;
    height: 100%;
  }
  .checkbox-wrapper-7 .tgl + .tgl-btn:after {
    right: 0;
  }
  .checkbox-wrapper-7 .tgl + .tgl-btn:before {
    display: none;
  }
  .checkbox-wrapper-7 .tgl:checked + .tgl-btn:after {
    right: 50%;
  }

  .checkbox-wrapper-7 .tgl-ios + .tgl-btn {
    background: #fbfbfb;
    border-radius: 2em;
    padding: 2px;
    transition: all 0.4s ease;
    border: 1px solid #e8eae9;
  }
  .checkbox-wrapper-7 .tgl-ios + .tgl-btn:after {
    border-radius: 2em;
    background: #fbfbfb;
    transition: left 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), padding 0.3s ease, margin 0.3s ease;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1), 0 4px 0 rgba(0, 0, 0, 0.08);
  }
  .checkbox-wrapper-7 .tgl-ios + .tgl-btn:hover:after {
    will-change: padding;
  }
  .checkbox-wrapper-7 .tgl-ios + .tgl-btn:active {
    box-shadow: inset 0 0 0 2em #e8eae9;
  }
  .checkbox-wrapper-7 .tgl-ios + .tgl-btn:active:after {
    padding-right: 0.8em;
  }
  .checkbox-wrapper-7 .tgl-ios:checked + .tgl-btn {
    background: var(--primary-500);
  }
  .checkbox-wrapper-7 .tgl-ios:checked + .tgl-btn:active {
    box-shadow: none;
  }
  .checkbox-wrapper-7 .tgl-ios:checked + .tgl-btn:active:after {
    margin-left: -0.8em;
  }



</style>
@endpush
@section('sidebar')
  @include('partials.sidebar-classes')
@endsection

@section('content')
<div class="container py-3 classes-shell">
  {{-- إحصائيات سريعة - محسنة بإطار --}}
  @if(!$classes->isEmpty())
  <div class="stats-container">
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-value">{{ $classes->count() }}</div>
        <div class="stat-label">عدد الصفوف</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ $classes->sum('students_count') }}</div>
        <div class="stat-label">إجمالي الطلاب</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ $classes->where('is_active', true)->count() }}</div>
        <div class="stat-label">صفوف نشطة</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">
          @php
            $avgStudents = $classes->count() > 0 ? round($classes->sum('students_count') / $classes->count(), 1) : 0;
          @endphp
          {{ $avgStudents }}
        </div>
        <div class="stat-label">متوسط الطلاب</div>
      </div>
    </div>
  </div>
  @endif
@if(isset($caps))
  <div class="hint" style="display:flex;align-items:center;gap:.5rem;justify-content:space-between;">
    <div>
      <i class="fas fa-info-circle"></i>
      @if($caps['source']==='no-plan')
        لديك خطة مجانية: مسموح حتى {{ $caps['max_total'] }} صفوف ({{ $caps['max_active'] }} فعّال).
      @elseif($caps['source']==='plan')
        حد الصفوف لخُطتك: {{ $caps['max_total'] }} صفوف.
      @else
        ليس لديك حد ظاهر للصفوف في الخطة الحالية.
      @endif
    </div>
    <div class="muted">
      لديك الآن {{ $classes->count() }} / {{ $caps['max_total']===PHP_INT_MAX ? '∞' : $caps['max_total'] }}
    </div>
  </div>
@endif

  {{-- لوحة الإنشاء/التعديل --}}
  <div class="panel" id="class-form-panel">
    <div class="panel-title">
      <i class="fas fa-school"></i> 
      <span id="form-title">إضافة صف جديد</span>
    </div>
    <form id="class-form" method="POST" action="{{ route('user.classes.store') }}">
      @csrf
      <input type="hidden" id="class_id" name="class_id" value="">
      
      <div class="form-flex">
        <div class="form-group">
          <label for="name" class="label required">اسم الصف</label>
          <input class="input" type="text" name="name" id="name" required placeholder="مثال: الصف السادس - أ">
        </div>
        
        <div class="form-group">
          <label for="grade_level" class="label">المستوى</label>
          <select class="select" name="grade_level" id="grade_level">
            <option value="">— اختر المستوى —</option>
            <option value="أساسي">أساسي</option>
            <option value="ثانوي">ثانوي</option>
            <option value="جامعي">جامعي</option>
            <option value="مجتمع تعلم">مجتمع تعلم</option>
            <option value="أخرى">أخرى</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="year" class="label">السنة الدراسية</label>
          <select class="select" name="year" id="year">
            <option value="">— اختر السنة —</option>
            @foreach(($academicYears ?? ['2025/2026']) as $yr)
              <option value="{{ $yr }}">{{ $yr }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label class="label">حالة الصف</label>
          {{-- لجعل القيمة 0 عند عدم التأشير --}}
          <input type="hidden" name="is_active" value="0">
          <div class="checkbox-wrapper-7">
            <input class="tgl tgl-ios" id="is_active" type="checkbox" name="is_active" value="1" checked />
            <label class="tgl-btn" for="is_active"></label>
          </div>
          <div class="muted" style="margin-top:.3rem">عند التفعيل يصبح الصف متاحًا للاستخدام داخل الأدوات.</div>
        </div>
        <div class="form-group-full">
          <label for="notes" class="label">ملاحظات</label>
          <textarea class="textarea" name="notes" id="notes" rows="2" placeholder="معلومات إضافية (اختياري)"></textarea>
        </div>
        
        <div class="form-group-full">
          <div class="form-actions">
            @php
              $atCap = isset($caps) && $caps['max_total'] !== PHP_INT_MAX && $classes->count() >= $caps['max_total'];
            @endphp
            
            <button type="submit" class="btn btn-primary" id="submit-btn" {{ $atCap ? 'disabled' : '' }}>
              <i class="fas fa-plus-circle" id="submit-icon"></i>
              <span id="submit-text">إضافة صف</span>
            </button>
            
            @if($atCap)
              <div class="muted" style="margin-top:.5rem;color:#dc2626">
                وصلت للحد الأقصى للصفوف. فضلاً احذف صفوفًا قديمة أو قم بالترقية.
              </div>
            @endif

            <button type="button" class="btn btn-ghost" id="cancel-edit" style="display:none;">
              <i class="fas fa-times"></i> إلغاء التعديل
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>

  {{-- قائمة الصفوف --}}
  <div class="panel">
    <div class="panel-title">
      <i class="fas fa-list"></i> قائمة صفوفي
      <span class="muted" style="margin-left: auto; font-size: 0.85rem; font-weight: normal;">
        {{ $classes->count() }} صف
      </span>
    </div>

    @if($classes->isEmpty())
      <div class="empty-state">
        <i class="fas fa-school-circle-exclamation"></i>
        <h3>لا يوجد لديك صفوف بعد</h3>
        <p>استخدم النموذج أعلاه لإضافة صفك الدراسي الأول</p>
      </div>
    @else
      <div class="list">
        @foreach($classes as $c)
          <div class="card" id="class-card-{{ $c->id }}" data-class-id="{{ $c->id }}"
               data-class-name="{{ $c->name }}"
               data-class-grade="{{ $c->grade_level }}"
               data-class-year="{{ $c->year }}"
               data-class-notes="{{ $c->notes }}"
               data-class-active="{{ (int) $c->is_active }}">
            <div class="card-head">
              <div class="card-title">
                <i class="fas fa-chalkboard"></i>
                {{ $c->name }}
              </div>
              <div class="actions-row">
                {{-- الطلاب --}}
                <a class="icon-btn" title="إضافة/إدارة الطلاب" href="{{ route('user.classes.students', $c->id) }}">
                  <i class="fas fa-users"></i>
                </a>
                <span class="inline-sep"></span>
                {{-- تعديل --}}
                <button class="icon-btn btn-edit" title="تعديل" data-class-id="{{ $c->id }}">
                  <i class="fas fa-pen"></i>
                </button>
                {{-- حذف --}}
                <form method="POST" action="{{ route('user.classes.destroy', $c->id) }}" onsubmit="return confirm('حذف هذا الصف نهائيًا؟');" style="display:inline;">
                  @csrf
                  @method('DELETE')
                  <button class="icon-btn" title="حذف" type="submit">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </div>

            @if(!empty($c->notes))
              <div class="muted" style="margin-bottom: 0.5rem;">{{ $c->notes }}</div>
            @endif

            <div class="badge-container">
              <span class="badge badge-student" title="عدد الطلاب">
                <i class="fas fa-user-graduate"></i>
                {{ (int)($c->students_count ?? 0) }} طالب
              </span>
              
              @if($c->grade_level)
                <span class="badge badge-grade">
                  <i class="fas fa-layer-group"></i> 
                  {{ $c->grade_level }}
                </span>
              @endif
              
              @if($c->year)
                <span class="badge badge-year">
                  <i class="fas fa-calendar-alt"></i> 
                  {{ $c->year }}
                </span>
              @endif
              
              @php
                  $isActive = isset($c->is_active) ? (int)$c->is_active === 1 : true; // افتراضي نشط لو الحقل غير موجود
                @endphp
                
                @if(!$isActive)
                  <span class="badge badge-archived">
                    <i class="fas fa-archive"></i> مؤرشف
                  </span>
                @else
                  <span class="badge badge-active">
                    <i class="fas fa-check-circle"></i> نشط
                  </span>
                @endif
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
  const form = document.getElementById('class-form');
  const formPanel = document.getElementById('class-form-panel');
  const formTitle = document.getElementById('form-title');
  const classIdIpt = document.getElementById('class_id');
  const nameIpt = document.getElementById('name');
  const gradeIpt = document.getElementById('grade_level');
  const yearSel = document.getElementById('year');
  const notesTa = document.getElementById('notes');
  const submitBtn = document.getElementById('submit-btn');
  const submitIcon = document.getElementById('submit-icon');
  const submitTxt = document.getElementById('submit-text');
  const cancelBtn = document.getElementById('cancel-edit');
  const activeChk  = document.getElementById('is_active');
  const STORE_URL = @json(route('user.classes.store'));
  const UPDATE_URL = (id) => @json(route('user.classes.update', ['id' => '__ID__'])).replace('__ID__', id);

  function switchToEditMode(row){
    const id = row.dataset.classId;
    const name = row.dataset.className || '';
    const grade = row.dataset.classGrade || '';
    const year = row.dataset.classYear || '';
    const notes = row.dataset.classNotes || '';
    const active= row.dataset.classActive === '1';

    classIdIpt.value = id;
    nameIpt.value = name;
    gradeIpt.value = grade;
    yearSel.value = year;
    notesTa.value = notes;
    activeChk.checked= active;
    
    form.action = UPDATE_URL(id);
    ensureMethodSpoof('_method','PUT');

    submitIcon.className = 'fas fa-save';
    submitTxt.textContent = 'حفظ التعديلات';
    formTitle.textContent = 'تعديل الصف';
    cancelBtn.style.display = 'inline-flex';
    
    // إضافة تأثيرات بصرية
    formPanel.classList.add('edit-mode');
    
    // إضافة تأثير للبطاقة التي يتم تعديلها
    const card = document.getElementById(`class-card-${id}`);
    if (card) {
      card.classList.add('edit-highlight');
      setTimeout(() => {
        card.classList.remove('edit-highlight');
      }, 1500);
    }
    
    // الانتقال للنموذج مع تأثير سلس
    formPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    // التركيز على حقل الاسم
    setTimeout(() => {
      nameIpt.focus();
    }, 500);
  }

  function switchToCreateMode(){
    classIdIpt.value = '';
    nameIpt.value = '';
    gradeIpt.value = '';
    yearSel.value = '';
    notesTa.value = '';
    activeChk.checked = true;
    
    form.action = STORE_URL;
    removeMethodSpoof('_method');

    submitIcon.className = 'fas fa-plus-circle';
    submitTxt.textContent = 'إضافة صف';
    formTitle.textContent = 'إضافة صف جديد';
    cancelBtn.style.display = 'none';
    
    // إزالة تأثيرات التعديل
    formPanel.classList.remove('edit-mode');
    
    // التركيز على حقل الاسم
    nameIpt.focus();
  }

  function ensureMethodSpoof(name, val){
    let hidden = form.querySelector(`input[name="${name}"]`);
    if (!hidden){
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = name;
      form.appendChild(hidden);
    }
    hidden.value = val;
  }

  function removeMethodSpoof(name){
    const hidden = form.querySelector(`input[name="${name}"]`);
    if (hidden) hidden.remove();
  }

  // ربط أزرار التعديل
  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', (e)=>{
      e.preventDefault();
      const classId = e.currentTarget.dataset.classId;
      const card = document.getElementById(`class-card-${classId}`);
      if (card) switchToEditMode(card);
    });
  });

  // إلغاء التعديل
  cancelBtn.addEventListener('click', (e)=>{
    e.preventDefault();
    switchToCreateMode();
  });

  // عند تحميل الصفحة ضمّن وضع الإنشاء
  switchToCreateMode();
  
})();
</script>
@endpush

