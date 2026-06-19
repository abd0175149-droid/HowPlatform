@extends('layouts.main')
@section('title','إصدار فاوتشر')

@push('styles')
  {{-- ملف ستايل الصفحة المخصص (حمّله بعد home.css ليأخذ الأولوية) --}}
  <link rel="stylesheet" href="{{ asset('assets/css/voucher.css?v=1.0.2') }}">

@endpush

@section('content')
@php
  // في حال لم يمرّر الكونترولر مصفوفة $platforms، نضع قيم افتراضية للتجربة
  $platforms = $platforms ?? [
    ['key'=>'library', 'label'=>'المكتبة'],
    ['key'=>'diploma', 'label'=>'الدبلوم'],
    ['key'=>'digital', 'label'=>'الأدوات الرقمية'],
  ];
@endphp

<div class="container py-4 voucher-page voucher-light" id="voucherRoot">
  <h2 class="fw-bold mb-2"><i class="fa-solid fa-ticket me-2"></i> إصدار الفاوتشر</h2>
  <p class="text-muted mb-4">اختر المنصّة ثم الخطة، وأدخل بيانات المشتري لإصدار البطاقة.</p>

  {{-- شريط المراحل --}}
  <nav class="wizard-steps mb-4" aria-label="مراحل الإصدار">
    <div class="step is-active" data-step="platforms">المنصّة</div>
    <div class="divider"></div>
    <div class="step" data-step="plans">الخطة</div>
    <div class="divider"></div>
    <div class="step" data-step="details">البيانات</div>
  </nav>

  {{-- STEP 1: المنصات --}}
  <section id="platforms-step" class="active">
    <div class="cards-wrap">
      @foreach($platforms as $p)
        <button type="button"
                class="platform-card"
                data-platform="{{ $p['key'] }}"
                data-label="{{ $p['label'] }}"
                aria-label="اختيار منصة {{ $p['label'] }}">
          <div class="card-icon"><i class="fa-solid fa-layer-group"></i></div>
          <h5 class="fw-bold">{{ $p['label'] }}</h5>
          <div class="sub">اضغط للاختيار</div>
        </button>
      @endforeach
    </div>
  </section>

  {{-- STEP 2: الخطط --}}
  <section id="plans-step" class="d-none">
    <div class="panel">
      <div class="panel-head">
        <h5 class="fw-bold m-0">
          الخطط المتاحة لمنصة: <span id="picked-platform" class="text-primary"></span>
        </h5>
        <button class="btn btn-outline-secondary btn-sm" id="back-to-platforms">
          رجوع
        </button>
      </div>
      <div class="panel-body">
        <div id="plans-container" class="cards-wrap"></div>
      </div>
    </div>
  </section>

  {{-- STEP 3: تفاصيل الفاوتشر --}}
  <section id="voucher-step" class="d-none">
    <div class="row g-4 align-items-start">
      <div class="col-lg-6">
        <div class="voucher-card" id="voucher-card" aria-live="polite">
          <div class="kv">
            <div>
              <div class="kv-label">المنصة</div>
              <div class="kv-value" id="v-platform-name">—</div>
            </div>
            <div>
              <div class="kv-label">الخطة</div>
              <div class="kv-value" id="v-plan-title">—</div>
            </div>
          </div>
          <hr>
          <div class="kv">
            <div><span class="kv-label">السعر</span> <span class="kv-value" id="v-price">—</span></div>
            <div><span class="kv-label">المدة</span> <span class="kv-value" id="v-duration">—</span></div>
          </div>
          <div class="mt-2"><span class="kv-label">رقم الفاوتشر</span> <span class="kv-value" id="v-code">—</span></div>
          <div class="mt-1"><span class="kv-label">تاريخ الانتهاء</span> <span class="kv-value" id="v-expiry">—</span></div>
          <hr>
          <div class="note">سيُولَّد QR تلقائيًا بعد الإصدار.</div>
          <div id="qr-box" class="qr-box"></div>
        </div>

        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-primary btn-sm" id="btn-pdf">تنزيل PDF</button>
          <button class="btn btn-outline-secondary btn-sm" id="btn-print">طباعة</button>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="panel">
          <div class="panel-head between">
            <span class="badge plan-chip" id="picked-plan-chip">—</span>
            <button class="btn btn-link p-0 small d-none" id="new-voucher">إصدار بطاقة جديدة</button>
          </div>
          <div class="panel-body">
            <div class="mb-2">
              <label class="form-label">اسم المشتري <span class="text-danger">*</span></label>
              <input class="form-control" id="buyer-name" placeholder="مثال: أحمد محمد" autocomplete="name">
            </div>
            <div class="mb-2">
              <label class="form-label">البريد الإلكتروني</label>
              <input type="email" class="form-control" id="buyer-email" placeholder="name@email.com" autocomplete="email">
            </div>
            <div class="mb-2">
              <label class="form-label">الهاتف</label>
              <input type="tel" class="form-control" id="buyer-phone" placeholder="07XXXXXXXX" autocomplete="tel">
            </div>
            <div class="mb-3">
              <label class="form-label">ملاحظات</label>
              <textarea class="form-control" id="buyer-notes" rows="2"></textarea>
            </div>

            <div class="d-grid">
              <button class="btn btn-primary" id="lock-and-prepare">
                <i class="fa-solid fa-lock me-1"></i> تثبيت البيانات وتوليد الكود
              </button>
            </div>

            <div class="form-hint">سيتم الحفظ محليًا وإرسال نسخة إلى المنصة المختارة.</div>
          </div>
        </div>
      </div>
    </div>
  </section>


</div>
{{-- Modal تأكيد الإصدار --}}
<div class="modal fade" id="confirmIssueModal" tabindex="-1" aria-labelledby="confirmIssueLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="confirmIssueLabel">تأكيد إصدار الفاوتشر</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>

      <div class="modal-body">
        <ul class="list-group">
          <li class="list-group-item d-flex justify-content-between"><span class="fw-bold">المنصة</span><span id="confirm-platform">—</span></li>
          <li class="list-group-item d-flex justify-content-between"><span class="fw-bold">الخطة</span><span id="confirm-plan">—</span></li>
          <li class="list-group-item d-flex justify-content-between"><span class="fw-bold">السعر</span><span id="confirm-price">—</span></li>
          <li class="list-group-item d-flex justify-content-between"><span class="fw-bold">المدة</span><span id="confirm-duration">—</span></li>
          <li class="list-group-item d-flex justify-content-between"><span class="fw-bold">المشتري</span><span id="confirm-buyer">—</span></li>
        </ul>
        <div class="small text-muted mt-2">بالضغط على "تأكيد" سيتم إصدار الفاوتشر وتوليد الكود وإرساله للمنصة.</div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
        <button type="button" class="btn btn-primary" id="confirm-issue-btn">
          <span class="spinner-border spinner-border-sm me-1 d-none" id="confirm-spinner" role="status" aria-hidden="true"></span>
          تأكيد
        </button>
      </div>

    </div>
  </div>
</div>

@endsection
@push('scripts')
  {{-- مكتبات هذه الصفحة فقط لو ليست محمّلة مسبقًا --}}

  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

  <script>
  (function(){
    // ================== حالة الصفحة ==================
    let pickedPlatform = null;
    let pickedPlatformLabel = null;
    let pickedPlan = null;
    let pendingPayload = null;

    const stepsOrder = ['platforms-step','plans-step','voucher-step'];

    // ================== أدوات مساعدة ==================
    async function safeJson(res){ try { return await res.json(); } catch { return null; } }
    function money(n){ try { return Number(n).toFixed(2)+' JOD'; } catch { return n+' JOD'; } }
    function setText(id, val){ const el = document.getElementById(id); if (el) el.textContent = val; }
    function showStep(id){
      document.querySelectorAll('.voucher-page section').forEach(sec=>{
        sec.classList.remove('active'); sec.classList.add('d-none');
      });
      const el = document.getElementById(id);
      if(el){ el.classList.add('active'); el.classList.remove('d-none'); }
      const idx = stepsOrder.indexOf(id);
      document.querySelectorAll('.wizard-steps .step').forEach((s,i)=> s.classList.toggle('is-active', i <= idx));
    }
    function showServerError(res, json){
      const msg = (json && (json.message || json.error)) ? `\n${json.message || json.error}` : '';
      alert(`تعذر إصدار الفاوتشر.${msg ? ' ' + msg : ' حاول لاحقًا.'}`);
    }

    // ================== STEP 1: اختيار المنصّة ==================
    document.querySelectorAll('.platform-card').forEach(card=>{
      card.addEventListener('click', ()=>{
        pickedPlatform = card.dataset.platform;
        pickedPlatformLabel = card.dataset.label || pickedPlatform;
        setText('picked-platform', pickedPlatformLabel);
        loadPlans(pickedPlatform);
        showStep('plans-step');
      });
    });

    const backBtn = document.getElementById('back-to-platforms');
    if (backBtn){
      backBtn.addEventListener('click', ()=>{
        const box = document.getElementById('plans-container');
        if (box) box.innerHTML = '';
        showStep('platforms-step');
      });
    }

    // ================== STEP 2: جلب وعرض الخطط ==================
async function loadPlans(platform){
  const box = document.getElementById('plans-container');
  if (!box) return;

  box.innerHTML = '<div class="empty-state">جاري جلب الخطط…</div>';

  try{
    const res = await fetch(`/pos/api/${platform}/plans`, {
      headers: { 'Accept':'application/json' }
    });
    const data = await safeJson(res);

    if (!res.ok || !data || data.status !== 'success') {
      // فشل الجلب → اعرض الحالة الفارغة
      renderPlans([]); 
      return;
    }

    renderPlans(data.plans || []);
  } catch (e) {
    // أي خطأ (شبكة/بارس) → اعرض الحالة الفارغة
    renderPlans([]);
  }
}
  function escapeHtml(s){
    return String(s ?? '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }
function humanizeFeatureText(f){
    const label = f?.feature_key ?? '';
    let parsed = {};
    try {
      parsed = typeof f?.limits_json === 'string' ? JSON.parse(f.limits_json) : (f?.limits_json ?? {});
    } catch { parsed = {}; }

    // خرائط الترجمة
    const tierMap   = { basic: 'اساسي', pro: 'محترف', elite: 'نخبة' };
    const prioMap   = { standard: 'قياسي', priority: 'أولوية', vip: 'مميز' };

    // تطبيع مفتاح الميزة لالتقاط العربية/الإنجليزية
    const k = String(label).toLowerCase().replace(/\s+/g,'');
    const has = (s) => k.includes(s);

    // 1) الوصول للمحتوى
    if (has('الوصولالىالمحتوى') || parsed.tier !== undefined || has('content_access')) {
      const v = parsed.tier ?? '';
      return tierMap[v] ?? String(v || '—');
    }

    // 2) استخدام فَطين (حصة رسائل يومية)
    if (has('استخدامفَطين') || has('استخدامفطين') || parsed.messages_per_day !== undefined || has('ai_quota')) {
      const n = parsed.messages_per_day ?? '';
      return String(n || '—');
    }

    // 3) عدد التحميلات لكل يوم
    if (has('التحميلات') || parsed.limit !== undefined || has('downloads_per_day')) {
      const n = parsed.limit ?? '';
      return String(n || '—');
    }

    // 4) أولوية الدعم
    if (has('الدعم') || parsed.level !== undefined || has('support_priority')) {
      const v = parsed.level ?? '';
      return prioMap[v] ?? String(v || '—');
    }

    // افتراضي: إن وُجد feature_text أعرضه، وإلا أعرض limits_json كسلسلة
    if (f?.feature_text) return String(f.feature_text);
    return (Object.keys(parsed).length ? JSON.stringify(parsed) : '—');
  }

  function renderPlans(plans){
    const box = document.getElementById('plans-container');
    if (!box) return;

    if (!Array.isArray(plans) || !plans.length){
      box.innerHTML = '<div class="empty-state">لا توجد خطط متاحة.</div>';
      return;
    }

    box.innerHTML = plans.map(p => {
      const amount = (p.amount ?? p.price ?? 0);
      const days   = (p.duration_days ?? p.default_duration_days ?? null);
      const name   = (p.name ?? p.plan_type_name ?? `Plan #${p.id ?? ''}`);
      const ccy    = (p.price_currency ?? 'JOD');

      const feats = Array.isArray(p.features) ? p.features.map(f => `
        <li>
          <strong>${escapeHtml(f?.feature_key ?? '')}</strong>
          ${escapeHtml(humanizeFeatureText(f))}
        </li>
      `).join('') : '';

      return `
        <button type="button" class="plan-card"
          data-id="${p.id}"
          data-name="${escapeHtml(name)}"
          data-amount="${amount}"
          data-days="${days ?? 30}">
          <div class="plan-head">
            <h5>${escapeHtml(name)}</h5>
            ${p.level ? `<span class="badge level">Lv ${escapeHtml(p.level)}</span>` : ''}
            <div class="price">${Number(amount).toFixed(2)} <span class="ccy">${escapeHtml(ccy)}</span></div>
          </div>
          ${days ? `<div class="meta"><i class="fa-regular fa-clock"></i> ${days} يوم</div>` : ''}
          ${feats ? `<ul class="features">${feats}</ul>` : ''}

          <span class="pick">اختيار</span>
        </button>
      `;
    }).join('');

    box.querySelectorAll('.plan-card').forEach(el=>{
      el.addEventListener('click', ()=>{
        box.querySelectorAll('.plan-card').forEach(c=>c.classList.remove('is-selected'));
        el.classList.add('is-selected');

        pickedPlan = {
          id: parseInt(el.dataset.id),
          name: el.dataset.name,
          amount: parseFloat(el.dataset.amount),
          days: parseInt(el.dataset.days),
        };

        setText('v-platform-name', pickedPlatformLabel);
        setText('v-plan-title', pickedPlan.name);
        setText('v-price', pickedPlan.amount.toFixed(2) + ' JOD');
        setText('v-duration', pickedPlan.days + ' يوم');
        setText('picked-plan-chip', `${pickedPlan.name} — ${pickedPlan.amount.toFixed(2)} JOD`);

        showStep('voucher-step');
      });
    });
  }

    // ================== STEP 3: تثبيت → إظهار مودال التأكيد ==================
    const btnLock = document.getElementById('lock-and-prepare');
    if (btnLock){
      btnLock.addEventListener('click', ()=>{
        if (!pickedPlatform || !pickedPlan){ alert('اختر المنصة والخطة أولًا'); return; }

        const buyer = {
          name:  document.getElementById('buyer-name')?.value.trim()  || '',
          email: document.getElementById('buyer-email')?.value.trim() || '',
          phone: document.getElementById('buyer-phone')?.value.trim() || '',
          notes: document.getElementById('buyer-notes')?.value.trim() || '',
        };
        if (!buyer.name){ alert('الاسم مطلوب'); return; }

        pendingPayload = {
          platform: pickedPlatform,
          plan_id: pickedPlan.id,
          plan_name: pickedPlan.name,
          price_amount: pickedPlan.amount,
          price_currency: 'JOD',
          duration_days: pickedPlan.days,
          buyer_name: buyer.name,
          buyer_email: buyer.email || null,
          buyer_phone: buyer.phone || null,
          notes: buyer.notes || null,
        };

        // تعبئة المودال
        setText('confirm-platform', pickedPlatformLabel || pickedPlatform);
        setText('confirm-plan', pickedPlan.name);
        setText('confirm-price', money(pickedPlan.amount));
        setText('confirm-duration', pickedPlan.days + ' يوم');
        setText('confirm-buyer', buyer.name);

        // عرض مودال Bootstrap
        const modalEl = document.getElementById('confirmIssueModal');
        if (window.bootstrap?.Modal && modalEl){
          bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
          alert('تحقق من تحميل bootstrap.bundle.js لعرض المودال.');
        }
      });
    }

    // ================== تأكيد الإصدار داخل المودال ==================
    const btnConfirm = document.getElementById('confirm-issue-btn');
    const spinnerEl = document.getElementById('confirm-spinner');

    if (btnConfirm){
      btnConfirm.addEventListener('click', async ()=>{
        if (!pendingPayload) return;

        btnConfirm.disabled = true;
        spinnerEl?.classList.remove('d-none');

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || null;

        try{
          const res = await fetch('/pos/api/vouchers', {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              ...(csrf ? {'X-CSRF-TOKEN': csrf} : {})
            },
            body: JSON.stringify(pendingPayload),
            credentials: 'same-origin'
          });

          const json = await safeJson(res);
          if (!res.ok || !json || (json.status !== 'success' && json.status !== 'partial_success')) {
            showServerError(res, json);
            btnConfirm.disabled = false;
            spinnerEl?.classList.add('d-none');
            return;
          }

          // أغلق المودال
          const modalEl = document.getElementById('confirmIssueModal');
          if (modalEl && window.bootstrap?.Modal){
            bootstrap.Modal.getInstance(modalEl)?.hide();
          }

          // عرض النتائج في البطاقة
          setText('v-code',   json.code);
          setText('v-expiry', json.expiry);

          const qrBox = document.getElementById('qr-box');
          if (qrBox){
            qrBox.innerHTML = '';
            new QRCode(qrBox, {
              text: json.verify_url || json.code,
              width: 128, height: 128,
              correctLevel: QRCode.CorrectLevel.M
            });
          }

          if (json.status === 'partial_success') {
            alert('تم الحفظ محليًا لكن تعذرت المزامنة مع المنصة. يمكنك إعادة المحاولة لاحقًا.');
          }

          pendingPayload = null;
          // أظهر زر "بطاقة جديدة" إن لديك واحدًا:
          document.getElementById('new-voucher')?.classList.remove('d-none');

        } catch {
          alert('تعذر إصدار الفاوتشر. حاول لاحقًا.');
        } finally {
          btnConfirm.disabled = false;
          spinnerEl?.classList.add('d-none');
        }
      });
    }

    // ================== جديد / طباعة / PDF ==================
    document.getElementById('new-voucher')?.addEventListener('click', ()=>{
      pickedPlan = null; pendingPayload = null;
      ['buyer-name','buyer-email','buyer-phone','buyer-notes'].forEach(id=>{ const el = document.getElementById(id); if (el) el.value = ''; });
      ['v-code','v-expiry'].forEach(id=>{ const el = document.getElementById(id); if (el) el.textContent = '—'; });
      const qrBox = document.getElementById('qr-box'); if (qrBox) qrBox.innerHTML = '';
      showStep('plans-step');
    });

    document.getElementById('btn-print')?.addEventListener('click', ()=> window.print());

    document.getElementById('btn-pdf')?.addEventListener('click', async ()=>{
      const jspdfNS = window.jspdf || {};
      const { jsPDF } = jspdfNS;
      if (!jsPDF){ alert('مكتبة jsPDF غير محمّلة.'); return; }
      const node = document.getElementById('voucher-card'); if (!node) return;

      const canvas = await html2canvas(node, {scale:2});
      const imgData = canvas.toDataURL('image/png');
      const pdf = new jsPDF({orientation:'p', unit:'pt', format:'a4'});
      const pageW = pdf.internal.pageSize.getWidth();
      const pageH = pdf.internal.pageSize.getHeight();
      const ratio = Math.min(pageW / canvas.width, pageH / canvas.height);
      const w = canvas.width * ratio, h = canvas.height * ratio;
      pdf.addImage(imgData, 'PNG', (pageW - w)/2, 40, w, h);
      pdf.save('voucher.pdf');
    });

    // ابدأ بالخطوة الأولى
    showStep('platforms-step');
  })();
  </script>
@endpush


