@extends('layouts.admin')
@section('title', ($plan->exists ? 'تعديل خطة' : 'خطة جديدة') . ' | Plans')
@section('heading', $plan->exists ? 'تعديل الخطة' : 'خطة جديدة')

@section('content')
  <p><a href="{{ route('admin.plans.index') }}" class="text-decoration-none">&larr; رجوع للخطط</a></p>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-body">
          <form method="post" action="{{ $plan->exists ? route('admin.plans.update',$plan) : route('admin.plans.store') }}">
            @csrf
            @if($plan->exists) @method('PUT') @endif
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label">اسم الخطة *</label>
                <input name="name" class="form-control" value="{{ old('name',$plan->name) }}" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">الرتبة (level) *</label>
                <input type="number" name="level" class="form-control" value="{{ old('level',$plan->level) }}" min="1" required>
              </div>
              <div class="col-md-8">
                <label class="form-label">المعرّف slug (اتركه فارغًا للتوليد)</label>
                <input name="slug" class="form-control" value="{{ old('slug',$plan->slug) }}" placeholder="pro-monthly">
              </div>
              <div class="col-md-4">
                <label class="form-label">العملة</label>
                <input name="currency" class="form-control" value="{{ old('currency',$plan->currency ?: 'JOD') }}" maxlength="3">
              </div>
              <div class="col-md-4">
                <label class="form-label">السعر</label>
                <input type="number" step="0.01" name="price" class="form-control" value="{{ old('price',$plan->price ?? 0) }}" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">المدة (أيام)</label>
                <input type="number" name="duration_days" class="form-control" value="{{ old('duration_days',$plan->duration_days ?? 30) }}" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">نقاط تُمنح عند التفعيل</label>
                <input type="number" name="grant_points" class="form-control" value="{{ old('grant_points',$plan->grant_points ?? 0) }}">
              </div>
              <div class="col-12">
                <label class="form-label">الوصف</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description',$plan->description) }}</textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label">ترتيب العرض</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order',$plan->sort_order ?? 0) }}">
              </div>
              <div class="col-md-6 d-flex align-items-end">
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" {{ old('is_active',$plan->is_active ?? 1) ? 'checked' : '' }}>
                  <label class="form-check-label" for="is_active">مفعّلة</label>
                </div>
              </div>
            </div>
            <button class="btn btn-brand mt-3"><i class="fas fa-save"></i> {{ $plan->exists ? 'حفظ التعديلات' : 'إنشاء الخطة' }}</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-body">
          <h2 class="h6 mb-3"><i class="fas fa-list-check"></i> المزايا (عبر المنصّات)</h2>
          @unless($plan->exists)
            <p class="text-muted small">احفظ الخطة أولًا ثم أضِف المزايا.</p>
          @else
            <form method="post" action="{{ route('admin.plans.features.add',$plan) }}" class="mb-3">
              @csrf
              <label class="form-label small text-muted">اختر ميزة من الكتالوج وحدّد السقف</label>
              <select name="catalog_id" id="catalog_id" class="form-select form-select-sm mb-2" required>
                <option value="">— اختر ميزة —</option>
                @foreach($catalog->groupBy('platform') as $plat => $items)
                  <optgroup label="منصّة: {{ $plat }}">
                    @foreach($items as $cf)
                      <option value="{{ $cf->id }}"
                        data-type="{{ $cf->value_type }}"
                        data-unit="{{ $cf->unit }}"
                        data-default="{{ $cf->default_value }}"
                        data-options='@json($cf->options)'>{{ $cf->label }}</option>
                    @endforeach
                  </optgroup>
                @endforeach
              </select>
              <div id="valueWrap" class="mb-2"></div>
              <button class="btn btn-sm btn-brand w-100"><i class="fas fa-plus"></i> إضافة الميزة للخطة</button>
            </form>

            <ul class="list-group list-group-flush">
              @forelse($plan->features as $f)
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                  <div>
                    <span class="badge bg-dark">{{ $f->platform }}</span>
                    <code>{{ $f->feature_key }}</code>
                    @if($f->value)<span class="text-muted">= {{ $f->value }}</span>@endif
                    @if($f->label)<div class="text-muted small">{{ $f->label }}</div>@endif
                  </div>
                  <form method="post" action="{{ route('admin.plans.features.delete',$f) }}">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger border-0"><i class="fas fa-times"></i></button>
                  </form>
                </li>
              @empty
                <li class="list-group-item text-muted px-0">لا توجد مزايا بعد.</li>
              @endforelse
            </ul>
          @endunless
        </div>
      </div>
    </div>
  </div>

  @push('scripts')
  <script>
    (function(){
      const sel = document.getElementById('catalog_id');
      const wrap = document.getElementById('valueWrap');
      if(!sel || !wrap) return;
      function render(){
        const o = sel.options[sel.selectedIndex];
        if(!o || !o.value){ wrap.innerHTML=''; return; }
        const type=o.dataset.type, unit=o.dataset.unit||'', def=o.dataset.default||'';
        let opts=[]; try{ opts=JSON.parse(o.dataset.options||'null')||[]; }catch(e){}
        if(type==='select' && opts.length){
          wrap.innerHTML='<select name="value" class="form-select form-select-sm">'+opts.map(v=>'<option value="'+v+'">'+v+'</option>').join('')+'</select>';
        } else if(type==='bool'){
          wrap.innerHTML='<select name="value" class="form-select form-select-sm"><option value="1">مفعّل</option><option value="0">معطّل</option></select>';
        } else if(type==='int'){
          wrap.innerHTML='<div class="input-group input-group-sm"><input type="number" name="value" class="form-control" value="'+def+'" min="0"><span class="input-group-text">'+(unit||'قيمة')+'</span></div>';
        } else {
          wrap.innerHTML='<input type="text" name="value" class="form-control form-control-sm" value="'+def+'" placeholder="القيمة">';
        }
      }
      sel.addEventListener('change', render);
    })();
  </script>
  @endpush
@endsection
