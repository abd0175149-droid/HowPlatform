@extends('layouts.admin')
@section('title','إصدار بطاقات | Plans')
@section('heading','إصدار البطاقات')

@section('content')
  <p><a href="{{ route('admin.cards.index') }}" class="text-decoration-none">&larr; رجوع للبطاقات</a></p>

  <div class="card" style="max-width:680px;">
    <div class="card-body">
      <form method="post" action="{{ route('admin.cards.store') }}">
        @csrf

        {{-- نمط الإصدار --}}
        <div class="mb-3">
          <label class="form-label fw-bold">نمط الإصدار</label>
          <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="mode" id="modeBatch" value="batch" @checked(old('mode','batch')==='batch')>
            <label class="btn btn-outline-secondary" for="modeBatch"><i class="fas fa-layer-group"></i> دفعة بطاقات</label>
            <input type="radio" class="btn-check" name="mode" id="modeSingle" value="single" @checked(old('mode')==='single')>
            <label class="btn btn-outline-secondary" for="modeSingle"><i class="fas fa-ticket"></i> بطاقة واحدة</label>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">نوع البطاقة</label>
          <select name="type" id="type" class="form-select">
            <option value="subscription" @selected(old('type')==='subscription')>اشتراك (تفعّل خطة)</option>
            <option value="points" @selected(old('type')==='points')>نقاط (تمنح رصيدًا)</option>
            <option value="mixed" @selected(old('type')==='mixed')>مختلطة (اشتراك + نقاط)</option>
          </select>
        </div>

        <div class="mb-3" id="planWrap">
          <label class="form-label fw-bold">الخطة</label>
          <select name="plan_id" class="form-select">
            <option value="">— اختر خطة —</option>
            @foreach($plans as $p)
              <option value="{{ $p->id }}" @selected((int)old('plan_id')===$p->id)>{{ $p->name }} ({{ number_format($p->price,2) }} {{ $p->currency }} / {{ $p->duration_days }} يوم)</option>
            @endforeach
          </select>
          <div class="form-text">عند الاستبدال، تُفعَّل هذه الخطة + تُمنح نقاطها (إن وُجدت).</div>
        </div>

        <div class="mb-3" id="pointsWrap">
          <label class="form-label fw-bold">حزمة النقاط (سلّم التسعير)</label>
          @if($packages->isEmpty())
            <div class="alert alert-warning py-2 mb-2">لا توجد حزم نقاط مفعّلة. أضِفها من «حزم النقاط» أو أدخِل عدد نقاط يدويًا أدناه.</div>
          @else
            <select name="package_id" id="package_id" class="form-select mb-2">
              <option value="">— اختر حزمة —</option>
              @foreach($packages as $pkg)
                <option value="{{ $pkg->id }}" @selected((int)old('package_id')===$pkg->id)>
                  {{ number_format($pkg->points) }} نقطة — {{ rtrim(rtrim(number_format((float)$pkg->price_amount,2),'0'),'.') }} {{ $pkg->price_currency }}
                </option>
              @endforeach
            </select>
            <div class="form-text mb-2">تُحدِّد الحزمة عدد النقاط وسعرها معًا. أو أدخِل عددًا يدويًا (بلا سعر):</div>
          @endif
          <input type="number" name="points" class="form-control" min="0" value="{{ old('points',0) }}" placeholder="عدد نقاط يدوي (إن لم تختر حزمة)">
        </div>

        {{-- خاص بالدفعة --}}
        <div class="row g-3" id="batchWrap">
          <div class="col-md-6">
            <label class="form-label fw-bold">عدد البطاقات</label>
            <input type="number" name="count" class="form-control" min="1" max="1000" value="{{ old('count',10) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">اسم الدفعة (اختياري)</label>
            <input name="batch" class="form-control" value="{{ old('batch') }}" placeholder="تلقائي">
          </div>
        </div>

        {{-- خاص بالبطاقة الواحدة: بيانات المشتري (اختيارية) --}}
        <div id="buyerWrap" style="display:none;">
          <div class="text-muted small mb-2 mt-2">بيانات المشتري (اختيارية — تظهر في القسيمة)</div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">اسم المشتري</label><input name="buyer_name" class="form-control" value="{{ old('buyer_name') }}"></div>
            <div class="col-md-6"><label class="form-label">هاتف المشتري</label><input name="buyer_phone" class="form-control" value="{{ old('buyer_phone') }}"></div>
            <div class="col-md-12"><label class="form-label">بريد المشتري</label><input type="email" name="buyer_email" class="form-control" value="{{ old('buyer_email') }}"></div>
          </div>
        </div>

        <div class="mt-3" style="max-width:240px;">
          <label class="form-label">تاريخ الانتهاء (اختياري)</label>
          <input type="date" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
        </div>

        <button class="btn btn-brand mt-3" id="submitBtn"><i class="fas fa-bolt"></i> <span id="submitLabel">إصدار الدفعة</span></button>
      </form>
    </div>
  </div>

  @push('scripts')
  <script>
    (function(){
      const t = document.getElementById('type');
      const plan = document.getElementById('planWrap');
      const pts = document.getElementById('pointsWrap');
      const batchWrap = document.getElementById('batchWrap');
      const buyerWrap = document.getElementById('buyerWrap');
      const label = document.getElementById('submitLabel');
      const modeBatch = document.getElementById('modeBatch');
      const modeSingle = document.getElementById('modeSingle');

      function updType(){
        const v=t.value;
        plan.style.display = (v==='subscription'||v==='mixed') ? '' : 'none';
        pts.style.display  = (v==='points'||v==='mixed') ? '' : 'none';
      }
      function updMode(){
        const single = modeSingle.checked;
        batchWrap.style.display = single ? 'none' : '';
        buyerWrap.style.display = single ? '' : 'none';
        label.textContent = single ? 'إصدار البطاقة' : 'إصدار الدفعة';
      }
      t.addEventListener('change', updType); updType();
      modeBatch.addEventListener('change', updMode);
      modeSingle.addEventListener('change', updMode); updMode();
    })();
  </script>
  @endpush
@endsection
