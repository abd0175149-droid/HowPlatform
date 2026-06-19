{{-- resources/views/sales/partials/points.blade.php --}}
@php
    // ناخذ المصدر المتاح: المتغير الجديد أو alias القديم
    $rows = $salesPoints ?? $points ?? null;

    $methodLabel = static function ($m) {
        return $m === 'online' ? 'أونلاين' : ($m === 'in_person' ? 'حضوري' : 'كلاهما');
    };
@endphp
<style>
/* ===== POS Points — Scoped (consistent with brand & other sections) ===== */
.pos-points{color:var(--text,#0d2a3a)}
.pos-points .container-narrow{max-width:var(--maxw,1100px)}
.pos-points .hp-section-title{
  margin:10px 0 12px;font-weight:900;color:var(--primary-900,#0a3247);
  display:flex;align-items:center;gap:.5rem;padding-inline-start:.6rem;
  border-inline-start:6px solid var(--accent-500,#fd9e02)
}
.pos-points .hp-card{
  background:#fff;border:1px solid rgba(0,0,0,.06);
  border-radius:16px;padding:18px;box-shadow:0 6px 16px rgba(0,0,0,.08)
}
.pos-points .hp-table{
  width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border-radius:14px;background:#fff
}
.pos-points .hp-table thead th{
  background:linear-gradient(135deg,var(--primary-800,#104b65),var(--primary-900,#0a3247));
  color:#fff;font-weight:800;text-align:right;padding:.9rem;border-bottom:1px solid rgba(0,0,0,.06)
}
.pos-points .hp-table th,.pos-points .hp-table td{vertical-align:middle;text-align:center}
.pos-points .hp-table tbody td{padding:.85rem;border-bottom:1px solid rgba(0,0,0,.06)}
.pos-points .hp-table tbody tr:nth-child(even){background:var(--primary-50,#e8f5fb)}
.pos-points .hp-table tbody tr:hover{background:color-mix(in srgb, var(--primary-50,#e8f5fb) 70%, #fff)}
.pos-points .btn-primary{
  background:var(--primary-600,#1b81a0);border-color:var(--primary-600,#1b81a0);font-weight:800
}
.pos-points .btn-primary:hover{
  background:var(--primary-700,#156683);border-color:var(--primary-700,#156683)
}
/* Preview remove button */
#btnRemoveImage{
  position:absolute;top:-8px;right:-8px;width:24px;height:24px;border-radius:50%;
  border:none;background:#d33;color:#fff;display:flex;align-items:center;justify-content:center;
  box-shadow:0 2px 6px rgba(0,0,0,.2)
}
</style>

<section class="container container-narrow py-3 pos-points" dir="rtl">
  <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="hp-section-title mb-0"><i class="fa-solid fa-store me-2"></i> نقاط البيع</h4>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPointModal" id="btnOpenCreate">
          <i class="fa-solid fa-plus me-1"></i> إضافة نقطة بيع
      </button>
  </div>
<div class="hp-card">
  <div class="table-responsive">
    <table class="hp-table text-center align-middle">
      <thead class="table-dark">
        <tr>
          <th>نقطة البيع</th>
          <th>المندوب</th>
          <th>الهاتف</th>
          <th>نسبة الخصم %</th>
          <th>المحافظة</th>
          <th>تفاصيل العنوان</th>
          <th>الصورة</th>
          <th>الموقع</th>
          <th>طريقة البيع</th>
          <th>إجراءات</th>
        </tr>
      </thead>
      <tbody>
        @forelse(($rows ?? collect()) as $point)
          @php
            $dp = isset($point->discount_percent) ? (float)$point->discount_percent : null;
            $file = null;
            if (!empty($point->image_file))      $file = $point->image_file;
            elseif (!empty($point->image_url))   $file = basename($point->image_url);
            $imgSrc = $file ? route('media.sales-point', $file) : '';
          @endphp
          <tr>
            <td>
              <div class="fw-bold">{{ $point->store_name ?: '—' }}</div>
            </td>
            <td>
              <div>{{ $point->user_name ?: 'غير معروف' }}</div>
              {{-- لا نعرض (#user_id) هنا --}}
            </td>
            <td>{{ $point->phone }}</td>
            <td>{{ $dp !== null ? number_format($dp, 2) : '—' }}</td>
            <td>{{ $point->governorate ?: '—' }}</td>
            <td style="max-width:240px">{{ $point->address_details ?: '—' }}</td>
            <td>
              @if($imgSrc)
                <img src="{{ $imgSrc }}" alt="صورة" style="height:auto;width:48px;object-fit:cover;border-radius:10px;">
              @endif
            </td>
            <td>
              @if(!empty($point->location_url))
                <a href="{{ $point->location_url }}" target="_blank" rel="noopener">رابط</a>
              @else
                -
              @endif
            </td>
            <td>{{ $methodLabel($point->sell_method) }}</td>
            <td>
              <button
                class="btn btn-sm btn-warning btnEdit"
                data-bs-toggle="modal"
                data-bs-target="#addPointModal"
                data-id="{{ $point->id }}"
                data-user_id="{{ $point->user_id }}"
                data-user_name="{{ $point->user_name }}"
                data-store_name="{{ $point->store_name }}"
                data-phone="{{ $point->phone }}"
                data-location_url="{{ $point->location_url }}"
                data-sell_method="{{ $point->sell_method }}"
                data-discount_percent="{{ $dp !== null ? $dp : '' }}"
                data-governorate="{{ $point->governorate ?? '' }}"
                data-address_details="{{ $point->address_details ?? '' }}"
                data-image_src="{{ $imgSrc }}"
                data-image_file="{{ $file ?? '' }}"
                title="تعديل: {{ $point->store_name ?: $point->user_name }}"
              >
                <i class="fas fa-edit"></i>
              </button>
                <!-- زر إنشاء مطالبة مالية -->
<button
  class="btn btn-sm btn-info btnClaim"
  data-bs-toggle="modal"
  data-bs-target="#claimModal"
  data-user_id="{{ $point->user_id }}"
  data-user_name="{{ $point->user_name }}"
  title="مطالبة مالية من تاريخ إلى تاريخ">
  <i class="fa-solid fa-file-invoice-dollar"></i>
</button>

<!-- زر عرض المطالبات -->
<button
  class="btn btn-sm btn-secondary btnViewClaims"
  data-bs-toggle="modal"
  data-bs-target="#claimsListModal"
  data-user_id="{{ $point->user_id }}"
  data-user_name="{{ $point->user_name }}"
  title="عرض المطالبات الخاصة بهذا المندوب">
  <i class="fa-solid fa-list"></i>
</button>

              <form action="{{ route('pos.sales-points.delete', $point->id) }}" method="POST" class="d-inline">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('تأكيد الحذف؟');">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="10" class="text-muted">لا توجد نقاط بيع مسجلة</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
</section>
@if($rows && method_exists($rows, 'hasPages') && $rows->hasPages())
  <div class="mt-3 d-flex justify-content-center">
      {{ $rows->onEachSide(1)->links('pagination::how') }}
  </div>
@endif

<!-- Modal إضافة / تعديل -->
<div class="modal fade" id="addPointModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="addPointForm" method="POST"
          action="{{ route('pos.sales-points.store', ['tab'=>'points']) }}"
          enctype="multipart/form-data">
      @csrf
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold text-dark" id="modalTitle">إضافة نقطة بيع</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body bg-white text-dark">
        <input type="hidden" name="id" id="point_id">
        <input type="hidden" name="tab" id="return_tab" value="points"><!-- أعطيناه id ليستعمله السكربت -->
        <input type="hidden" name="user_name" id="user_name_hidden">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">المندوب (اختيار)</label>
            <select class="form-select" id="userSelect" name="user_id">
              <option value="">-- اختر المندوب --</option>
              @foreach(($salesUsers ?? []) as $user)
                <option value="{{ $user['id'] }}" data-name="{{ $user['name'] }}">
                  {{ $user['name'] }} (#{!! $user['id'] !!})
                </option>
              @endforeach
            </select>
            <div class="form-text">عند التعديل سيتم قفل اختيار المندوب.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">اسم المكتبة/نقطة البيع</label>
            <input type="text" class="form-control" name="store_name" id="store_name" required>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">الهاتف</label>
            <input type="text" class="form-control" name="phone" id="phone" required>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">رابط الموقع</label>
            <input type="url" class="form-control" name="location_url" id="location_url" placeholder="https://maps.google.com/...">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">طريقة البيع</label>
            <select class="form-select" name="sell_method" id="sell_method">
              <option value="online">أونلاين</option>
              <option value="in_person">حضوري</option>
              <option value="both">كلاهما</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">نسبة الخصم %</label>
            <input type="number" class="form-control" name="discount_percent" id="discount_percent" min="0" max="100" step="0.01" value="0">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">المحافظة</label>
            <select class="form-select" name="governorate" id="governorate">
              <option value="">-- اختر المحافظة --</option>
              @foreach(($governorates ?? []) as $gov)
                <option value="{{ $gov }}">{{ $gov }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">تفاصيل العنوان</label>
            <input type="text" class="form-control" name="address_details" id="address_details" maxlength="255" placeholder="اسم الشارع، معلم قريب...">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">الصورة (اختياري)</label>
            <input type="file" class="form-control" name="image" id="image" accept="image/*">
            <div class="form-text">الأنواع المسموحة: jpg, jpeg, png, webp — بحد أقصى 2MB</div>
          </div>

          <div class="col-12">
            <input type="hidden" name="remove_image" id="remove_image" value="0">
            <input type="hidden" name="current_image_file" id="current_image_file" value="">
            <div id="previewWrap" style="position:relative; display:none; width:72px; height:72px;">
              <img id="previewImg" src="" alt="صورة"
                   style="height:72px;width:72px;object-fit:cover;border-radius:10px; display:block;">
              <button type="button" id="btnRemoveImage" title="حذف الصورة">×</button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="submit" class="btn btn-success" id="btnSubmitForm">
          <i class="fa-solid fa-save me-1"></i> حفظ
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
      </div>
    </form>
  </div>
</div>
<!-- Modal: إنشاء مطالبة مالية -->
<div class="modal fade" id="claimModal" tabindex="-1" aria-hidden="true" dir="rtl">
  <div class="modal-dialog">
    <form class="modal-content" id="claimForm">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold text-dark">مطالبة مالية</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="claim_user_id">
        <div class="mb-2">المندوب: <span id="claim_user_name" class="fw-bold"></span></div>
        <div class="row g-2">
          <div class="col">
            <label class="form-label fw-semibold">من تاريخ</label>
            <input type="date" id="claim_from" class="form-control" required>
          </div>
          <div class="col">
            <label class="form-label fw-semibold">إلى تاريخ</label>
            <input type="date" id="claim_to" class="form-control" required>
          </div>
        </div>

        <div class="mt-3 p-2 border rounded bg-light small" id="claimPreview" style="display:none">
          <div>عدد الفواتير: <span id="prev_count">0</span></div>
          <div>الإجمالي: <span id="prev_total">0.00</span> JOD</div>
        </div>
        <div class="form-text mt-2">ستجمع المطالبة كل فواتير المبيعات غير المدفوعة ضمن النطاق.</div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" id="btnPreviewClaim" class="btn btn-secondary">معاينة</button>
        <button type="submit" class="btn btn-primary" id="btnCreateClaim" disabled>إنشاء مطالبة</button>
      </div>
    </form>
  </div>
</div>
<!-- Modal: قائمة مطالبات المندوب -->
<div class="modal fade" id="claimsListModal" tabindex="-1" aria-hidden="true" dir="rtl">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold text-dark">
          مطالبات المندوب — <span id="claims_user_name"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-secondary">
              <tr>
                <th>#</th>
                <th>الكود</th>
                <th>الفترة</th>
                <th>عدد الفواتير</th>
                <th>الإجمالي</th>
                <th>الحالة</th>
                <th>عرض</th>
              </tr>
            </thead>
            <tbody id="claims_tbody">
              <tr><td colspan="7" class="text-muted text-center">— لا يوجد بيانات —</td></tr>
            </tbody>
          </table>
        </div>
        <div class="small text-muted">تظهر أحدث 20 مطالبة. يمكن توسيعها لاحقًا.</div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
      </div>
    </div>
  </div>
</div>
<script>
/*────────── زر "مطالبة مالية" ──────────*/
let claimUserId = null;

document.querySelectorAll('.btnClaim').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    claimUserId = btn.dataset.user_id;
    document.getElementById('claim_user_id').value = claimUserId;
    document.getElementById('claim_user_name').textContent = btn.dataset.user_name || ('#'+claimUserId);

    // Reset
    document.getElementById('claim_from').value = '';
    document.getElementById('claim_to').value   = '';
    document.getElementById('claimPreview').style.display='none';
    document.getElementById('btnCreateClaim').disabled = true;
  });
});

document.getElementById('btnPreviewClaim')?.addEventListener('click', async ()=>{
  const from = document.getElementById('claim_from').value;
  const to   = document.getElementById('claim_to').value;
  if(!from || !to || !claimUserId) return;

  const qs = new URLSearchParams({user_id: claimUserId, from, to});
  const url = "{{ route('pos.claims.preview') }}?" + qs.toString();

  try{
    const res = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
    if(!res.ok) throw new Error('HTTP '+res.status);
    const data = await res.json(); // {count,total}
    document.getElementById('prev_count').textContent = data.count ?? 0;
    document.getElementById('prev_total').textContent = (data.total ?? 0).toFixed(2);
    document.getElementById('claimPreview').style.display='block';
    document.getElementById('btnCreateClaim').disabled = (data.count ?? 0) < 1;
  }catch(e){
    alert('تعذر المعاينة: '+ e.message);
  }
});

document.getElementById('claimForm')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const from = document.getElementById('claim_from').value;
  const to   = document.getElementById('claim_to').value;

  try{
    const res = await fetch("{{ route('pos.claims.store') }}", {
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        'X-Requested-With':'XMLHttpRequest',
        'X-CSRF-TOKEN':'{{ csrf_token() }}'
      },
      body: JSON.stringify({user_id: claimUserId, from, to})
    });
    const out = await res.json();
    if(out.status==='success' && out.redirect){
      window.location.href = out.redirect;
    }else{
      alert(out.message || 'تعذر إنشاء المطالبة');
    }
  }catch(e){
    alert('خطأ: '+ e.message);
  }
});

/*────────── زر "عرض المطالبات" ──────────*/
document.querySelectorAll('.btnViewClaims').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const uid = btn.dataset.user_id, uname = btn.dataset.user_name || ('#'+uid);
    document.getElementById('claims_user_name').textContent = uname;

    const url = "{{ route('pos.claims.byUser', ['userId'=>'__UID__']) }}".replace('__UID__', uid);
    const tbody = document.getElementById('claims_tbody');
    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">... جاري التحميل ...</td></tr>`;

    try{
      const res = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
      if(!res.ok) throw new Error('HTTP '+res.status);
      const json = await res.json();
      const items = json.data || [];

      if(items.length === 0){
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">— لا يوجد بيانات —</td></tr>`;
        return;
      }

      tbody.innerHTML = '';
      items.forEach((it, i)=>{
        const link = "{{ route('pos.claims.show', ['id'=>'__ID__']) }}".replace('__ID__', it.id);
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${i+1}</td>
          <td><span class="badge bg-dark">${it.claim_code}</span></td>
          <td>${it.date_from} → ${it.date_to}</td>
          <td>${it.invoices_count}</td>
          <td>${Number(it.invoices_total).toFixed(2)}</td>
          <td>
            <span class="badge ${it.status==='paid'?'bg-success':(it.status==='canceled'?'bg-danger':'bg-warning text-dark')}">
              ${it.status}
            </span>
          </td>
          <td><a href="${link}" class="btn btn-sm btn-outline-primary">عرض</a></td>
        `;
        tbody.appendChild(tr);
      });
    }catch(e){
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">فشل التحميل: ${e.message}</td></tr>`;
    }
  });
});
</script>

<script>
const modalTitle      = document.getElementById('modalTitle');
const pointIdEl       = document.getElementById('point_id');
const userSelect      = document.getElementById('userSelect');
const storeNameEl     = document.getElementById('store_name');
const phoneEl         = document.getElementById('phone');
const locEl           = document.getElementById('location_url');
const methodEl        = document.getElementById('sell_method');
const discountEl      = document.getElementById('discount_percent');
const govEl           = document.getElementById('governorate');
const addrEl          = document.getElementById('address_details');
const formEl          = document.getElementById('addPointForm');
const userNameHidden  = document.getElementById('user_name_hidden');

const imageInput      = document.getElementById('image');
const previewWrap     = document.getElementById('previewWrap');
const previewImg      = document.getElementById('previewImg');
const btnRemoveImage  = document.getElementById('btnRemoveImage');
const removeImageEl   = document.getElementById('remove_image');
const currentImageEl  = document.getElementById('current_image_file');

const btnOpenCreate   = document.getElementById('btnOpenCreate');

function showPreview(src){
  if(previewWrap) previewWrap.style.display='inline-block';
  previewImg.src=src;
  previewImg.style.display='inline-block';
  btnRemoveImage.style.display='inline-block';
}
function hidePreview(){
  previewImg.src='';
  previewImg.style.display='none';
  btnRemoveImage.style.display='none';
  if(previewWrap) previewWrap.style.display='none';
}

/* وضع الإضافة */
function resetFormToCreate(){
  // مهم: reset أولاً ثم اضبط القيم
  formEl.reset();

  modalTitle.textContent = 'إضافة نقطة بيع';
  pointIdEl.value = '';
  userSelect.disabled = false;
  userSelect.value = '';
  storeNameEl.value = '';
  phoneEl.value = '';
  locEl.value = '';
  methodEl.value = 'online';
  discountEl.value = '0';
  if (govEl) govEl.value = '';
  if (addrEl) addrEl.value = '';
  if (userNameHidden) userNameHidden.value = '';
  formEl.action = "{{ route('pos.sales-points.store', ['tab'=>'points']) }}";

  imageInput.value = '';
  removeImageEl.value = '0';
  currentImageEl.value = '';
  hidePreview();
}
btnOpenCreate?.addEventListener('click', resetFormToCreate);

/* وضع التعديل */
document.querySelectorAll('.btnEdit').forEach(btn => {
  btn.addEventListener('click', () => {
    const uid   = btn.dataset.user_id || '';
    const uname = btn.dataset.user_name || '';
    const sname = btn.dataset.store_name || '';

    modalTitle.textContent = `تعديل نقطة بيع — ${sname || uname || 'نقطة'}`;
    pointIdEl.value = btn.dataset.id || '';

    userSelect.value = uid;
    userSelect.disabled = true;

    storeNameEl.value = sname;
    phoneEl.value     = btn.dataset.phone || '';
    locEl.value       = btn.dataset.location_url || '';
    methodEl.value    = btn.dataset.sell_method || 'online';
    discountEl.value  = btn.dataset.discount_percent || '0';
    if (govEl)  govEl.value  = btn.dataset.governorate || '';
    if (addrEl) addrEl.value = btn.dataset.address_details || '';
    if (userNameHidden) userNameHidden.value = btn.dataset.user_name || '';

    const imageSrc  = btn.dataset.image_src || '';
    const imageFile = btn.dataset.image_file || '';
    removeImageEl.value = '0';
    currentImageEl.value = imageFile;
    imageInput.value = '';

    if (imageSrc) showPreview(imageSrc); else hidePreview();
  });
});

/* معاينة صورة جديدة */
imageInput?.addEventListener('change', (e) => {
  const f = e.target.files?.[0];
  if (!f) return;
  const reader = new FileReader();
  reader.onload = (ev) => showPreview(ev.target.result);
  reader.readAsDataURL(f);
  removeImageEl.value = '0';
});

/* زر × حذف الصورة */
btnRemoveImage?.addEventListener('click', () => {
  hidePreview();
  imageInput.value = '';
  removeImageEl.value = '1';
  currentImageEl.value = '';
});

/* تعبئة اسم المكتبة تلقائياً باسم المندوب عند الإضافة فقط لو الحقل فاضي */
userSelect?.addEventListener('change', () => {
  const opt = userSelect.selectedOptions[0];
  if (opt && opt.dataset.name && !pointIdEl.value && !storeNameEl.value) {
    storeNameEl.value = opt.dataset.name;
    if (userNameHidden) userNameHidden.value = opt.dataset.name || '';
  }
});

/* إرسال (تعديل عبر X-HTTP-Method-Override) */
formEl.addEventListener('submit', async (e) => {
  if (!pointIdEl.value) return; // إضافة

  e.preventDefault(); // تعديل
  const id = pointIdEl.value;
  const fd = new FormData(formEl);

  // لا نعدل المندوب عند التعديل
  fd.delete('user_id');

  try {
    const res = await fetch(`/pos/api/sales-points/${id}`, {
      method: 'POST',
      headers: {
        'X-HTTP-Method-Override': 'PUT',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: fd
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    await res.json();
    window.location.href = "{{ route('pos.sales-points.index') }}?tab=points";
  } catch (err) {
    alert('فشل التعديل: ' + err.message);
  }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form      = document.getElementById('addPointForm');
  const returnInp = document.getElementById('return_tab');

  form?.addEventListener('submit', function () {
    const tab = localStorage.getItem('pos_active_tab') || '#points';
    if (returnInp) returnInp.value = tab;
  });
});
</script>
