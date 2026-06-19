@extends('layouts.tools')
<head>
  <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

@section('title', '🎓 إدارة الأداء الأكاديمي')
<link rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
  referrerpolicy="no-referrer" />
@section('content')
<div class="tools-wrapper">
  <h1 class="page-title">🎓 إدارة البيانات الأساسية</h1>

  {{-- بطاقات العمليات --}}
  <div class="card-grid">
    {{-- إضافة أسبوع --}}
    <div class="card">
      <div class="card-icon"><i class="fas fa-calendar-plus"></i></div>
      <h3>إضافة أسبوع جديد</h3>
      <p class="hint">أضف أسابيع دراسية جديدة للمتابعة</p>
      <button class="btn btn-primary" data-open="#weekModal"><i class="fas fa-plus"></i> إضافة أسبوع</button>
    </div>

    {{-- إضافة مهمة --}}
    <div class="card">
      <div class="card-icon"><i class="fas fa-tasks"></i></div>
      <h3>إضافة مهمة تعليمية</h3>
      <p class="hint">أنشئ مهام تعليمية للطلبة</p>
      <button class="btn btn-primary" data-open="#taskModal"><i class="fas fa-plus"></i> إضافة مهمة</button>
    </div>

    {{-- إدخال أسماء الطلبة (نص متعدد الأسطر) --}}
    <div class="card">
      <div class="card-icon"><i class="fas fa-user-graduate"></i></div>
      <h3>إدخال أسماء الطلبة</h3>
      <p class="hint">أدخل كل اسم في سطر مستقل</p>
      <button class="btn btn-primary" data-open="#studentsTextModal">
        <i class="fas fa-keyboard"></i> إدخال الأسماء
      </button>
    </div>
  </div>

  {{-- نظرة عامة --}}
  <div class="data-overview">
    <div class="overview-card">
      <div class="overview-header"><h3><i class="fas fa-info-circle"></i> نظرة عامة</h3></div>
      <div class="overview-stats">
        <div class="stat-item"><div class="stat-value">{{ $weeksCount ?? 0 }}</div><div class="stat-label">عدد الأسابيع</div></div>
        <div class="stat-item"><div class="stat-value">{{ $tasksCount ?? 0 }}</div><div class="stat-label">عدد المهام</div></div>
        <div class="stat-item"><div class="stat-value">{{ $studentsCount ?? 0 }}</div><div class="stat-label">عدد الطلبة</div></div>
      </div>
    </div>
  </div>

  {{-- مودال إضافة أسبوع --}}
  <div class="modal" id="weekModal" aria-hidden="true">
    <div class="modal-content">
      <h3><i class="fas fa-calendar-plus"></i> إضافة أسبوع جديد</h3>
     <form id="addWeekForm">@csrf
      <div class="form-group">
        <label>رقم الأسبوع</label>
        <input type="number" name="week_number" required class="form-input">
      </div>
      <div class="form-group">
        <label>عنوان الأسبوع</label>
        <input type="text" name="title" required class="form-input">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>تاريخ البدء</label>
          <input type="date" name="start_date" class="form-input">
        </div>
        <div class="form-group">
          <label>تاريخ الانتهاء</label>
          <input type="date" name="end_date" class="form-input">
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-close><i class="fas fa-times"></i> إلغاء</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ</button>
      </div>
    </form>
    </div>
  </div>

  {{-- مودال إضافة مهمة --}}
  <div class="modal" id="taskModal" aria-hidden="true">
    <div class="modal-content">
      <h3><i class="fas fa-tasks"></i> إضافة مهمة تعليمية</h3>
        <form id="addTaskForm">@csrf
          <div class="form-group">
            <label>اسم المهمة</label>
            <input type="text" name="title" required class="form-input">
          </div>
          <div class="form-group">
            <label>وصف المهمة</label>
            <textarea name="description" rows="3" class="form-input"></textarea>
          </div>
          <div class="form-group">
            <label>رابط المهمة</label>
            <input type="url" name="task_url" class="form-input" placeholder="https://...">
          </div>
          <div class="form-group">
            <label>الأسبوع</label>
            <select name="week_id" required class="form-input">
              <option value="">— اختر الأسبوع —</option>
              @foreach($weeks as $w)
                <option value="{{ $w->id }}">أسبوع {{ $w->week_number }} — {{ $w->title }}</option>
              @endforeach
            </select>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn btn-ghost" data-close><i class="fas fa-times"></i> إلغاء</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ المهمة</button>
          </div>
        </form>
    </div>
  </div>

{{-- مودال إدخال الأسماء نصيًا --}}
<div class="modal" id="studentsTextModal" aria-hidden="true">
  <div class="modal-content">
    <h3><i class="fas fa-user-plus"></i> إدخال أسماء الطلبة</h3>

    <form id="addStudentsTextForm">@csrf
      <div class="form-group">
        <label>أدخل الأسماء (كل اسم في سطر)</label>
        <textarea name="names_text" id="namesText"
                  class="form-input" rows="10" required
                  placeholder="مثال:
أحمد محمد
سارة علي
محمد خالد"></textarea>
        <p class="hint">سطر فارغ سيتم تجاهله تلقائيًا.</p>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-close><i class="fas fa-times"></i> إلغاء</button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> حفظ الأسماء
        </button>
      </div>
    </form>
  </div>
</div>

</div>
@endsection

@push('styles')
<style>
  .upload-form {
    display:flex;flex-direction:column;gap:1rem;margin-top:1rem;
  }
  .file-upload-wrapper{position:relative;overflow:hidden;display:inline-block;width:100%;}
  .file-upload-label{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    padding:1.5rem;border:2px dashed #ccc;border-radius:var(--radius);
    background:#f9f9f9;cursor:pointer;transition:.25s;text-align:center;gap:.5rem;
  }
  .file-upload-label:hover{border-color:var(--primary);background:#f0f8ff;}
  .file-upload-label i{font-size:2rem;color:var(--primary);}
  .file-input{position:absolute;left:-9999px;opacity:0;}
  .file-text{font-weight:600;color:var(--text);}
  .data-overview{margin-top:2rem;}
  .overview-card{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;}
  .overview-header{margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:1px solid #eee;}
  .overview-header h3{color:var(--primary);margin:0;display:flex;align-items:center;gap:.5rem;}
  .overview-stats{display:flex;justify-content:space-around;flex-wrap:wrap;gap:1rem;}
  .stat-item{text-align:center;flex:1;min-width:120px;}
  .stat-value{font-size:2.5rem;font-weight:800;color:var(--primary);line-height:1;}
  .stat-label{margin-top:.5rem;font-size:.9rem;color:#666;font-weight:600;}
  .form-row{display:flex;gap:1rem;}
  .form-row .form-group{flex:1;}
  @media(max-width:768px){
    .overview-stats{flex-direction:column;gap:1.5rem;}
    .form-row{flex-direction:column;gap:0;}
  }
  .samples-box{background:#fafafa;border:1px solid #eee;border-radius:12px;padding:.8rem;max-height:200px;overflow:auto;font-family:monospace}
  .samples-box .row{padding:.2rem 0;border-bottom:1px dashed #eee}
  .samples-box .row:last-child{border-bottom:0}
</style>
@endpush

@push('scripts')
<script src="{{ asset('assets/js/performance.js') }}"></script>
@endpush
