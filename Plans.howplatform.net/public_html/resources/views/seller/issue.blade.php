@extends('layouts.admin')
@section('title', 'إصدار بطاقة')
@section('heading', 'إصدار بطاقة اشتراك')

@section('content')
@php $hasPoint = (bool) $salePoint; @endphp

<div class="row g-3">
  {{-- ملخّص رصيد البائع --}}
  <div class="col-12">
    <div class="row g-3">
      <div class="col-6 col-md-3">
        <div class="stat"><div class="text-muted small">عمولتك</div><div class="n">{{ rtrim(rtrim(number_format($discount,2),'0'),'.') }}%</div></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat"><div class="text-muted small">رصيد الشهر</div><div class="n">{{ number_format($balance,2) }}</div><div class="text-muted small">JOD</div></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat"><div class="text-muted small">السقف الشهري</div><div class="n">{{ $limit > 0 ? number_format($limit,2) : '∞' }}</div><div class="text-muted small">JOD</div></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat"><div class="text-muted small">المتاح للإصدار</div><div class="n">{{ is_null($available) ? '∞' : number_format($available,2) }}</div><div class="text-muted small">JOD</div></div>
      </div>
    </div>
  </div>

  @unless($hasPoint)
    <div class="col-12">
      <div class="alert alert-warning mb-0">
        <i class="fas fa-triangle-exclamation"></i>
        لا توجد <strong>نقطة بيع</strong> مرتبطة بحسابك بعد. تواصل مع الإدارة لإضافتها قبل إصدار البطاقات.
      </div>
    </div>
  @endunless

  {{-- النموذج --}}
  <div class="col-12 col-lg-7">
    <div class="card p-3 p-md-4">
      <form id="issueForm" autocomplete="off">
        <div class="mb-3">
          <label class="form-label fw-bold">نوع البطاقة</label>
          <select name="type" id="typeSel" class="form-select" {{ $hasPoint ? '' : 'disabled' }}>
            <option value="subscription">اشتراك (تفعّل خطة)</option>
            <option value="points">نقاط (رصيد للمحفظة)</option>
          </select>
        </div>

        {{-- بطاقة اشتراك --}}
        <div id="subWrap">
          <div class="mb-3">
            <label class="form-label fw-bold">الخطة</label>
            <select name="plan_id" id="planSel" class="form-select" {{ $hasPoint ? '' : 'disabled' }}>
              <option value="">— اختر خطة —</option>
              @foreach($plans as $p)
                <option value="{{ $p->id }}" data-price="{{ $p->price }}" data-duration="{{ $p->duration_days }}">
                  {{ $p->name }} — {{ number_format((float)$p->price,2) }} JOD / {{ $p->duration_days }} يوم
                </option>
              @endforeach
            </select>
          </div>

          <div class="row g-3">
            <div class="col-6">
              <label class="form-label fw-bold">السعر (JOD)</label>
              <input type="number" step="0.01" min="0" name="price_amount" id="priceInp" class="form-control" {{ $hasPoint ? '' : 'disabled' }}>
            </div>
            <div class="col-6">
              <label class="form-label fw-bold">المدّة (يوم)</label>
              <input type="number" min="1" max="1095" name="duration_days" id="durInp" class="form-control" {{ $hasPoint ? '' : 'disabled' }}>
            </div>
          </div>
        </div>

        {{-- بطاقة نقاط --}}
        <div id="ptsWrap" style="display:none;">
          @if($packages->isEmpty())
            <div class="alert alert-warning py-2">لا توجد حزم نقاط مفعّلة. تواصل مع الإدارة لإضافتها.</div>
          @else
            <div class="mb-3">
              <label class="form-label fw-bold">حزمة النقاط</label>
              <select name="package_id" id="pkgSel" class="form-select" {{ $hasPoint ? '' : 'disabled' }}>
                <option value="">— اختر حزمة —</option>
                @foreach($packages as $pkg)
                  <option value="{{ $pkg->id }}" data-price="{{ $pkg->price_amount }}" data-points="{{ $pkg->points }}">
                    {{ number_format($pkg->points) }} نقطة — {{ rtrim(rtrim(number_format((float)$pkg->price_amount,2),'0'),'.') }} JOD
                  </option>
                @endforeach
              </select>
              <div class="form-text">السعر وعدد النقاط محدّدان من سلّم التسعير.</div>
            </div>
          @endif
        </div>

        <hr class="my-3">
        <div class="text-muted small mb-2">بيانات المشتري</div>
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label">الاسم</label>
            <input type="text" name="buyer_name" class="form-control" required {{ $hasPoint ? '' : 'disabled' }}>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">الهاتف</label>
            <input type="text" name="buyer_phone" class="form-control" {{ $hasPoint ? '' : 'disabled' }}>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">البريد (اختياري)</label>
            <input type="email" name="buyer_email" class="form-control" {{ $hasPoint ? '' : 'disabled' }}>
          </div>
          <div class="col-12">
            <label class="form-label">ملاحظات (اختياري)</label>
            <input type="text" name="notes" class="form-control" maxlength="2000" {{ $hasPoint ? '' : 'disabled' }}>
          </div>
        </div>

        <div class="d-flex align-items-center gap-2 mt-4">
          <button type="submit" class="btn btn-brand px-4" id="issueBtn" {{ $hasPoint ? '' : 'disabled' }}>
            <i class="fas fa-ticket"></i> إصدار البطاقة
          </button>
          <div id="netHint" class="text-muted small"></div>
        </div>
      </form>
    </div>
  </div>

  {{-- نتيجة الإصدار --}}
  <div class="col-12 col-lg-5">
    <div class="card p-3 p-md-4 h-100" id="resultCard" style="display:none;">
      <div class="text-center">
        <div class="mb-2" style="color:var(--accent);"><i class="fas fa-circle-check fa-2x"></i></div>
        <div class="fw-bold mb-1" style="color:var(--brand-deep);">تم إصدار البطاقة</div>
        <div class="text-muted small mb-3" id="resPlan"></div>

        <div class="p-3 mb-3" style="background:#f1f5f8; border-radius:14px;">
          <div class="text-muted small mb-1">كود البطاقة</div>
          <div class="d-flex align-items-center justify-content-center gap-2">
            <code id="resCode" style="font-size:1.3rem; letter-spacing:2px; color:var(--brand-deep);"></code>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="copyBtn"><i class="fas fa-copy"></i></button>
          </div>
        </div>

        <div class="row text-center g-2 small">
          <div class="col-4"><div class="text-muted">المدّة</div><div class="fw-bold" id="resDur"></div></div>
          <div class="col-4"><div class="text-muted">السعر</div><div class="fw-bold" id="resPrice"></div></div>
          <div class="col-4"><div class="text-muted">صافٍ عليك</div><div class="fw-bold" id="resNet"></div></div>
        </div>
        <div class="text-muted small mt-3">يسلّم الكود للمشتري ليُفعّل بطاقته.</div>
        <a id="printBtn" href="#" class="btn btn-outline-primary btn-sm mt-2" onclick="return openVoucherPopup(this.href)"><i class="fas fa-print"></i> طباعة القسيمة</a>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
function openVoucherPopup(url){
  if(!url || url.charAt(url.length-1)==='#') return false;
  var m = url.match(/cards\/(\d+)\/voucher/);
  var name = m ? ('howV_'+m[1]) : 'howV_bulk';
  var w = window.open(url, name, 'width=900,height=1180,scrollbars=yes,resizable=yes');
  if(!w){ window.open(url, '_blank'); } else { w.focus(); }
  return false;
}
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const typeSel = document.getElementById('typeSel');
const subWrap = document.getElementById('subWrap');
const ptsWrap = document.getElementById('ptsWrap');
const planSel = document.getElementById('planSel');
const priceInp = document.getElementById('priceInp');
const durInp = document.getElementById('durInp');
const pkgSel = document.getElementById('pkgSel');
const discount = {{ json_encode($discount) }};
const netHint = document.getElementById('netHint');

function effectivePrice(){
  if (typeSel && typeSel.value === 'points'){
    const o = pkgSel && pkgSel.selectedOptions[0];
    return o && o.value ? parseFloat(o.dataset.price || '0') : 0;
  }
  return parseFloat(priceInp.value || '0');
}

function recalc(){
  const price = effectivePrice();
  if (price > 0){
    const net = (price * (1 - discount/100)).toFixed(2);
    netHint.textContent = 'الصافي المستحق عليك بعد العمولة: ' + net + ' JOD';
  } else { netHint.textContent = ''; }
}

function applyType(){
  const isPoints = typeSel && typeSel.value === 'points';
  if (subWrap) subWrap.style.display = isPoints ? 'none' : '';
  if (ptsWrap) ptsWrap.style.display = isPoints ? '' : 'none';
  recalc();
}

typeSel?.addEventListener('change', applyType);
pkgSel?.addEventListener('change', recalc);

planSel?.addEventListener('change', () => {
  const o = planSel.selectedOptions[0];
  if (o && o.value){
    if (!priceInp.value) priceInp.value = o.dataset.price || '';
    if (!durInp.value)   durInp.value   = o.dataset.duration || '';
  }
  recalc();
});
priceInp?.addEventListener('input', recalc);
applyType();

document.getElementById('issueForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('issueBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جارٍ الإصدار...';
  const fd = new FormData(e.target);
  try {
    const res = await fetch('{{ route('seller.issue.store') }}', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: fd,
    });
    const data = await res.json();
    if (res.ok && data.status === 'success'){
      document.getElementById('resCode').textContent  = data.code;
      document.getElementById('resPlan').textContent  = data.plan;
      document.getElementById('resDur').textContent   = data.duration_days;
      document.getElementById('resPrice').textContent = data.price + ' JOD';
      document.getElementById('resNet').textContent   = data.net_due + ' JOD';
      document.getElementById('printBtn').href        = '/seller/cards/' + data.card_id + '/voucher';
      document.getElementById('resultCard').style.display = 'block';
      e.target.reset(); netHint.textContent = ''; applyType();
    } else {
      alert(data.message || 'تعذّر الإصدار.');
    }
  } catch(err){
    alert('خطأ في الاتصال. حاول مجددًا.');
  } finally {
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-ticket"></i> إصدار البطاقة';
  }
});

document.getElementById('copyBtn')?.addEventListener('click', () => {
  navigator.clipboard.writeText(document.getElementById('resCode').textContent);
});
</script>
@endpush
@endsection
