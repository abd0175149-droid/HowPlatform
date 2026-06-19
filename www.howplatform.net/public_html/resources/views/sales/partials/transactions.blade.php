{{-- resources/views/sales/partials/transactions.blade.php --}}
<style>
/* ===== POS Transactions — Scoped (aligned with brand) ===== */
.pos-trans{color:var(--text,#0d2a3a)}
.pos-trans .container-narrow{max-width:var(--maxw,1100px)}

/* Section title with accent edge */
.pos-trans .hp-section-title{
  margin:0 0 12px;font-weight:900;color:var(--primary-900,#0a3247);
  display:flex;align-items:center;gap:.5rem;padding-inline-start:.6rem;
  border-inline-start:6px solid var(--accent-500,#fd9e02)
}

/* Toolbar */
.pos-trans .hp-toolbar{margin-bottom:12px}
.pos-trans .btn-primary{
  background:var(--primary-600,#1b81a0);border-color:var(--primary-600,#1b81a0);font-weight:800
}
.pos-trans .btn-primary:hover{background:var(--primary-700,#156683);border-color:var(--primary-700,#156683)}

/* Card */
.pos-trans .hp-card{
  background:#fff;border:1px solid rgba(0,0,0,.06);
  border-radius:16px;padding:18px;box-shadow:0 6px 16px rgba(0,0,0,.08)
}

/* Table (same head look as points) */
.pos-trans .hp-table{
  width:100%;border-collapse:separate;border-spacing:0;
  overflow:hidden;border-radius:14px;background:#fff
}
.pos-trans .hp-table thead th{
  background:linear-gradient(135deg,var(--primary-800,#104b65),var(--primary-900,#0a3247));
  color:var(--text-inverse,#fff);font-weight:800;text-align:right;padding:.9rem;
  border-bottom:1px solid rgba(0,0,0,.06)
}
.pos-trans .hp-table th,.pos-trans .hp-table td{vertical-align:middle;text-align:center}
.pos-trans .hp-table tbody td{padding:.85rem;border-bottom:1px solid rgba(0,0,0,.06);color:var(--text,#0d2a3a)}
.pos-trans .hp-table tbody tr:nth-child(even){background:var(--primary-50,#e8f5fb)}
.pos-trans .hp-table tbody tr:hover{background:color-mix(in srgb, var(--primary-50,#e8f5fb) 70%, #fff)}

/* Modal: solid white, brand header */
.pos-trans .modal-content{
  background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px;
  box-shadow:0 12px 28px rgba(0,0,0,.18)
}
.pos-trans .modal-header{border-bottom:1px solid rgba(0,0,0,.06)}
.pos-trans .modal-title{font-weight:900;color:var(--primary-900,#0a3247)}
.pos-trans .form-label{font-weight:800;color:var(--primary-800,#104b65)}
</style>
<style>
/* نفس CSS السابق المختصر (pos-trans ...) */
.pos-trans{color:var(--text,#0d2a3a)} .pos-trans .container-narrow{max-width:var(--maxw,1100px)}
.pos-trans .hp-section-title{margin:0 0 12px;font-weight:900;color:var(--primary-900,#0a3247);display:flex;align-items:center;gap:.5rem;padding-inline-start:.6rem;border-inline-start:6px solid var(--accent-500,#fd9e02)}
.pos-trans .hp-toolbar{margin-bottom:12px}
.pos-trans .btn-primary{background:var(--primary-600,#1b81a0);border-color:var(--primary-600,#1b81a0);font-weight:800}
.pos-trans .btn-primary:hover{background:var(--primary-700,#156683);border-color:var(--primary-700,#156683)}
.pos-trans .hp-card{background:#fff;border:1px solid rgba(0,0,0,.06);border-radius:16px;padding:18px;box-shadow:0 6px 16px rgba(0,0,0,.08)}
.pos-trans .hp-table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border-radius:14px;background:#fff}
.pos-trans .hp-table thead th{background:linear-gradient(135deg,var(--primary-800,#104b65),var(--primary-900,#0a3247));color:#fff;font-weight:800;text-align:right;padding:.9rem;border-bottom:1px solid rgba(0,0,0,.06)}
.pos-trans .hp-table th,.pos-trans .hp-table td{text-align:center;vertical-align:middle}
.pos-trans .hp-table tbody td{padding:.85rem;border-bottom:1px solid rgba(0,0,0,.06)}
.pos-trans .hp-table tbody tr:nth-child(even){background:var(--primary-50,#e8f5fb)}
.pos-trans .hp-table tbody tr:hover{background:color-mix(in srgb, var(--primary-50,#e8f5fb) 70%, #fff)}
.pos-trans .modal-content{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px;box-shadow:0 12px 28px rgba(0,0,0,.18)}
.pos-trans .modal-header{border-bottom:1px solid rgba(0,0,0,.06)}
.pos-trans .modal-title{font-weight:900;color:var(--primary-900,#0a3247)}
.pos-trans .form-label{font-weight:800;color:var(--primary-800,#104b65)}
</style>
{{-- resources/views/sales/partials/transactions.blade.php --}}
<section class="pos-trans container container-narrow py-3" dir="rtl">
  <div class="d-flex justify-content-between align-items-center hp-toolbar">
    <h4 class="hp-section-title m-0">
      <i class="fa-solid fa-money-bill-transfer me-2"></i> العمليات المالية
    </h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal" id="btnOpenTransaction">
      <i class="fa-solid fa-plus me-1"></i> تسجيل عملية
    </button>
  </div>

  <div class="hp-card">
    <div class="table-responsive">
      @php
        $__types = [
          'payment'        => 'دفعة',
          'refund'         => 'استرداد',
          'adjust_invoice' => 'تعديل فاتورة',
          'adjustment'     => 'تسوية', // قد تظهر تاريخيًا من سجلات قديمة
          'voucher_sale'   => 'بيع بطاقة',
        ];
      @endphp
      <table id="salesTransactionsTable" class="hp-table text-center align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>رقم الفاتورة</th>
            <th>المندوب</th>
            <th>نوع العملية</th>
            <th>المبلغ</th>
            <th>الرصيد قبل</th>
            <th>الرصيد بعد</th>
            <th>التاريخ</th>
            <th>ملاحظات</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          @forelse(($salesTransactions ?? collect()) as $tx)
            <tr>
              <td>{{ $tx->id }}</td>
              <td>
                @if(!empty($tx->doc_code))
                  <span class="badge bg-secondary">{{ $tx->doc_code }}</span>
                @else
                  -
                @endif
              </td>
              <td>
                {{ $tx->user_name ?? ($__mapUsers[$tx->user_id] ?? 'غير معروف') }}
                <small class="text-muted">(#{{ $tx->user_id }})</small>
              </td>
              <td>{{ $__types[$tx->type] ?? $tx->type }}</td>
              <td>{{ number_format((float)$tx->amount, 2) }}</td>
              <td>{{ number_format((float)$tx->balance_before, 2) }}</td>
              <td>{{ number_format((float)$tx->balance_after, 2) }}</td>
              <td>{{ \Illuminate\Support\Carbon::parse($tx->created_at)->format('Y-m-d H:i') }}</td>
              <td>{{ $tx->note }}</td>
              <td>
                {{-- مثال: السماح بتعديل دفعة قائمة (اختياري) --}}
                @if($tx->type === 'payment')
                  <button type="button"
                          class="btn btn-sm btn-warning btnEditPayment"
                          data-bs-toggle="modal"
                          data-bs-target="#editTxModal"
                          data-id="{{ $tx->id }}"
                          data-user_id="{{ $tx->user_id }}"
                          data-amount="{{ (float)$tx->amount }}"
                          data-doc="{{ $tx->doc_code ?? '' }}">
                    <i class="fas fa-edit"></i>
                  </button>
                @else
                  -
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="text-muted">لا توجد عمليات بعد</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

  </div>
    @if(isset($salesTransactions) && method_exists($salesTransactions, 'hasPages') && $salesTransactions->hasPages())
      <div class="mt-3 d-flex justify-content-center">
        {{-- استخدم ترقيمكم الزجاجي --}}
        {{ $salesTransactions->onEachSide(1)->links('pagination::how') }}
      </div>
    @endif
</section>

<!-- Modal: إضافة/تسجيل عملية -->
<div class="modal fade" id="addTransactionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST" action="{{ route('pos.sales-transactions.store') }}">
      @csrf
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold text-dark">تسجيل عملية مالية</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body bg-white text-dark">
        <div class="row g-3">

          {{-- المندوب --}}
          <div class="col-md-6">
            <label class="form-label fw-semibold">المندوب</label>
            @if(!empty($salesUsers) && count($salesUsers) > 0)
              <select class="form-select" name="user_id" id="tx_user_select" required>
                <option value="">-- اختر المندوب --</option>
                @foreach($salesUsers as $u)
                  @php $uid = $u['id'] ?? $u['user_id'] ?? ''; $uname = $u['name'] ?? ($u['user_name'] ?? ('#'.$uid)); @endphp
                  <option value="{{ $uid }}">{{ $uname }} (ID: {{ $uid }})</option>
                @endforeach
              </select>
            @elseif(!empty($limitUsers) && count($limitUsers) > 0)
              <select class="form-select" name="user_id" id="tx_user_select" required>
                <option value="">-- اختر المندوب --</option>
                @foreach($limitUsers as $u)
                  <option value="{{ $u->id }}">{{ $u->name }} (ID: {{ $u->id }})</option>
                @endforeach
              </select>
            @else
              <input type="number" class="form-control" name="user_id" id="tx_user_input" placeholder="ID" required>
            @endif
            <div class="form-text" id="tx_balance_hint"></div>
          </div>

          {{-- نوع العملية --}}
          <div class="col-md-6">
            <label class="form-label fw-semibold">نوع العملية</label>
            <select class="form-select" name="type" id="tx_type" required>
              <option value="payment">دفعة</option>
              <option value="adjust_invoice">تعديل فاتورة قديمة</option>
              <option value="refund">استرداد</option>
            </select>
          </div>

          {{-- مبلغ (للـ payment فقط) --}}
          <div class="col-md-6 block-payment">
            <label class="form-label fw-semibold">المبلغ</label>
            <input type="number" step="0.01" min="0" class="form-control" name="amount" id="tx_amount">
          </div>

          {{-- استرداد: كود/مُعرّف الفاوتشر --}}
          <div class="col-md-6 refund-only d-none">
            <label class="form-label fw-semibold">رقم/كود الفاوتشر</label>
            <input type="text" class="form-control" name="voucher_ref" id="voucher_ref" placeholder="ABC123456" autocomplete="off">
            <div class="form-text" id="vref_status"></div>
          </div>

          {{-- تعديل فاتورة: رقم الفاتورة القديمة + عرض المبلغ القديم + إدخال الجديد --}}
          <div class="col-12 adjust-only d-none">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">رقم الفاتورة القديمة</label>
                <input type="text" class="form-control" name="adjust_doc_code" id="adjust_doc_code" placeholder="TX-000123" autocomplete="off">
                <div class="form-text" id="adj_status"></div>
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold">المبلغ القديم</label>
                <input type="number" class="form-control" id="adj_old_amount" readonly>
                <div class="form-text" id="adj_old_type_hint"></div>
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold">المبلغ الجديد</label>
                <input type="number" step="0.01" min="0" class="form-control" name="new_amount" id="adj_new_amount" disabled>
              </div>
              <div class="col-12">
                <div class="form-text" id="adj_effect_hint"></div>
              </div>
            </div>
          </div>

          {{-- ملاحظات --}}
          <div class="col-12">
            <label class="form-label fw-semibold">ملاحظات</label>
            <input type="text" class="form-control" name="note" maxlength="255" placeholder="اختياري">
          </div>
        </div>
      </div>

      <div class="modal-footer bg-light">
        <button type="submit" class="btn btn-success"><i class="fa-solid fa-save me-1"></i> حفظ</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: تعديل دفعة موجودة (إن كنت تستخدمه) -->
<div class="modal fade" id="editTxModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="editTxForm" method="POST" action="">
      @csrf
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold text-dark">تعديل دفعة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body bg-white text-dark">
        <input type="hidden" id="edit_tx_id" name="tx_id" value="">
        <div class="mb-2">
          <label class="form-label fw-semibold">رقم المستند</label>
          <input type="text" class="form-control" id="edit_doc_code" readonly>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">المبلغ القديم</label>
            <input type="number" class="form-control" id="old_amount" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">المبلغ الجديد</label>
            <input type="number" step="0.01" min="0" class="form-control" id="new_amount" name="new_amount" required>
          </div>
        </div>
        <div class="form-text mt-2" id="delta_hint"></div>
        <div class="form-text" id="edit_balance_hint"></div>
        <div class="mt-3">
          <label class="form-label fw-semibold">ملاحظات (اختياري)</label>
          <input type="text" class="form-control" name="note" id="edit_note" maxlength="255" placeholder="سبب التعديل (يُضاف في قيد الفرق)">
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="submit" class="btn btn-warning"><i class="fa-solid fa-scale-balanced me-1"></i> تطبيق الفرق</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
@php
  // تحضير STATS (رصيد/سقف) حسب آخر نقطة بيع لكل مندوب، للـ hints
  $__STATS = [];
  foreach (($userStats ?? []) as $r) {
      $uid  = is_object($r) ? ($r->user_id ?? null)          : ($r['user_id'] ?? null);
      $name = is_object($r) ? ($r->user_name ?? 'غير معروف') : ($r['user_name'] ?? 'غير معروف');
      $bal  = (float) (is_object($r) ? ($r->balance ?? 0)     : ($r['balance'] ?? 0));
      $lim  = (float) (is_object($r) ? ($r->monthly_limit ?? 0): ($r['monthly_limit'] ?? 0));
      if ($uid !== null) {
          $__STATS[$uid] = ['name'=>$name,'bal'=>round($bal,2),'limit'=>round($lim,2)];
      }
  }
@endphp

<script>
document.addEventListener('DOMContentLoaded', function(){
  // ===== ثوابت =====
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const REFUND_PREVIEW_URL = "{{ route('pos.sales-transactions.refundPreview') }}";
  const ADJ_PREVIEW_URL    = "{{ route('pos.sales-transactions.adjustInvoicePreview') }}";

  // ===== عناصر مشتركة =====
  const typeSel  = document.getElementById('tx_type');
  const amtBlock = document.querySelector('.block-payment');
  const refundBl = document.querySelector('.refund-only');
  const adjustBl = document.querySelector('.adjust-only');

  const amtInput = document.getElementById('tx_amount');

  const userSel  = document.getElementById('tx_user_select');
  const userInp  = document.getElementById('tx_user_input');

  // ===== تلميح الرصيد/السقف =====
  const STATS = {!! json_encode($__STATS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
  function getUserId(){
    if (userSel && userSel.value) return userSel.value;
    if (userInp && userInp.value) return userInp.value;
    return '';
  }
  function showBalanceHint(uid, elId){
    const el = document.getElementById(elId || 'tx_balance_hint');
    const s  = STATS[uid] || null;
    if (!el) return;
    if (!s){ el.textContent = ''; return; }
    const remain = Math.max(0, (s.limit || 0) - (s.bal || 0));
    el.textContent = `الرصيد الحالي: ${Number(s.bal).toFixed(2)} — السقف: ${Number(s.limit).toFixed(2)} — المتاح: ${remain.toFixed(2)} JOD`;
  }
  userSel?.addEventListener('change', ()=> showBalanceHint(getUserId(), 'tx_balance_hint'));
  userInp?.addEventListener('input',  ()=> showBalanceHint(getUserId(), 'tx_balance_hint'));

  // ===== تبديل واجهة حسب النوع =====
  function toggleTypeUI(){
    const t = typeSel?.value;
    // إظهار/إخفاء الكتل
    amtBlock?.classList.toggle('d-none', t !== 'payment');
    refundBl?.classList.toggle('d-none', t !== 'refund');
    adjustBl?.classList.toggle('d-none', t !== 'adjust_invoice');
  }
  typeSel?.addEventListener('change', toggleTypeUI);
  toggleTypeUI();

  // ===== REFUND: معاينة =====
  (function setupRefund(){
    const vrefInput = document.getElementById('voucher_ref');
    const vrefStat  = document.getElementById('vref_status');

    if (!vrefInput) return;

    let timer = null;
    const DEBOUNCE_MS = 200;

    async function runRefundPreview(){
      const uid = getUserId();
      if (!uid){ vrefStat.textContent = 'اختر المندوب أولاً.'; return; }

      let code = (vrefInput.value || '').trim().toUpperCase();
      if (!code){ vrefStat.textContent=''; return; }

      vrefStat.textContent = 'جاري التحقق...';
      try {
        const res = await fetch(REFUND_PREVIEW_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
          body: JSON.stringify({ user_id: uid, voucher_ref: code })
        });
        const json = await res.json();
        if (!res.ok || !json.ok){
          vrefStat.textContent = json.message || 'تعذّر التحقق';
          return;
        }
        vrefStat.textContent = json.message || (json.used ? 'مستخدم' : 'غير مستخدم');
        // المبلغ سيُملأ تلقائيًا في الباك-إند؛ هنا مجرد معاينة
      } catch(e){
        vrefStat.textContent = 'فشل الاتصال للتحقق.';
      }
    }

    vrefInput.addEventListener('input', function(){
      clearTimeout(timer);
      timer = setTimeout(runRefundPreview, DEBOUNCE_MS);
    });
    userSel?.addEventListener('change', ()=> { if (vrefInput.value) runRefundPreview(); });
    userInp?.addEventListener('input',  ()=> { if (vrefInput.value) runRefundPreview(); });
  })();

  // ===== ADJUST_INVOICE: معاينة وجلب المبلغ القديم + حساب التأثير =====
  (function setupAdjustInvoice(){
    const codeInput   = document.getElementById('adjust_doc_code');
    const statusEl    = document.getElementById('adj_status');
    const oldAmtEl    = document.getElementById('adj_old_amount');
    const oldTypeHint = document.getElementById('adj_old_type_hint');
    const newAmtEl    = document.getElementById('adj_new_amount');
    const effectHint  = document.getElementById('adj_effect_hint');

    if (!codeInput) return;

    let lastOldType = null; // 'payment' | 'adjustment'
    let lastOldAmt  = 0;

    function renderEffect(){
      effectHint.textContent = '';
      effectHint.className = 'form-text';
      const t = typeSel?.value;
      if (t !== 'adjust_invoice') return;

      const newV = parseFloat(newAmtEl.value || '0');
      if (!isFinite(newV) || lastOldType === null) return;

      const delta = +(newV - lastOldAmt).toFixed(2);
      const signOld = (lastOldType === 'payment') ? -1 : +1;
      const effect = +(signOld * delta).toFixed(2);

      if (effect > 0){
        effectHint.textContent = `سيرتفع الرصيد بمقدار ${effect.toFixed(2)} JOD (فرق موجب).`;
        effectHint.className = 'form-text text-success';
      } else if (effect < 0){
        effectHint.textContent = `سينخفض الرصيد بمقدار ${Math.abs(effect).toFixed(2)} JOD (فرق سالب).`;
        effectHint.className = 'form-text text-warning';
      } else {
        effectHint.textContent = 'لا يوجد فرق على الرصيد.';
        effectHint.className = 'form-text text-muted';
      }
    }

    newAmtEl?.addEventListener('input', renderEffect);

    let timer = null;
    const DEBOUNCE_MS = 250;

    async function runAdjustPreview(){
      const uid  = getUserId();
      if (!uid){ statusEl.textContent = 'اختر المندوب أولاً.'; return; }

      const code = (codeInput.value || '').trim();
      if (!code){ statusEl.textContent = ''; oldAmtEl.value=''; newAmtEl.value=''; lastOldType=null; lastOldAmt=0; oldTypeHint.textContent=''; newAmtEl.disabled = true; effectHint.textContent=''; return; }

      statusEl.textContent = 'جاري التحقق...';
      newAmtEl.disabled = true;
      try{
        const res = await fetch(ADJ_PREVIEW_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
          body: JSON.stringify({ user_id: uid, adjust_doc_code: code })
        });
        const json = await res.json();
        if (!res.ok || !json.ok){
          statusEl.textContent = json.message || 'تعذّر التحقق من الفاتورة.';
          oldAmtEl.value = '';
          newAmtEl.value = '';
          lastOldType = null; lastOldAmt = 0;
          oldTypeHint.textContent = '';
          newAmtEl.disabled = true;
          effectHint.textContent='';
          return;
        }

        // نجح
        statusEl.textContent = 'تم التحقق من الفاتورة القديمة.';
        lastOldAmt  = Number(json.old_amount || 0);
        lastOldType = json.old_type || null;
        oldAmtEl.value = isFinite(lastOldAmt) ? lastOldAmt.toFixed(2) : '0.00';
        oldTypeHint.textContent = (lastOldType === 'payment') ? 'نوع الفاتورة القديمة: دفعة (كانت تقلّل الرصيد).' : 'نوع الفاتورة القديمة: تسوية (كانت تزيد الرصيد).';
        newAmtEl.disabled = false;

        // عرض تلميح الرصيد/السقف هنا أيضاً (اختياري)
        showBalanceHint(uid, 'tx_balance_hint');

        // حساب التأثير إذا كان هناك قيمة جديدة بالفعل
        renderEffect();
      }catch(e){
        statusEl.textContent = 'فشل الاتصال للتحقق.';
        oldAmtEl.value = '';
        newAmtEl.value = '';
        lastOldType = null; lastOldAmt = 0;
        oldTypeHint.textContent = '';
        newAmtEl.disabled = true;
        effectHint.textContent='';
      }
    }

    codeInput.addEventListener('input', function(){
      clearTimeout(timer);
      timer = setTimeout(runAdjustPreview, DEBOUNCE_MS);
    });
    userSel?.addEventListener('change', ()=> { if (codeInput.value) runAdjustPreview(); });
    userInp?.addEventListener('input',  ()=> { if (codeInput.value) runAdjustPreview(); });
  })();

  // ===== (اختياري) مودال تعديل دفعة قائمة كما كان عندك =====
  (function setupEditPayment(){
    const editForm   = document.getElementById('editTxForm');
    const oldAmount  = document.getElementById('old_amount');
    const newAmount  = document.getElementById('new_amount');
    const deltaHint  = document.getElementById('delta_hint');
    const balanceHint= document.getElementById('edit_balance_hint');
    const docField   = document.getElementById('edit_doc_code');

    if (!editForm) return;

    function renderDelta(){
      const oldV = parseFloat(oldAmount.value || '0');
      const newV = parseFloat(newAmount.value || '0');
      const delta = +(newV - oldV).toFixed(2);
      if (!isFinite(delta)) return;
      if (delta > 0){
        deltaHint.textContent = `سيتم تسجيل دفعة إضافية قدرها ${delta.toFixed(2)} JOD (ينقص الرصيد).`;
        deltaHint.className = 'form-text text-success';
      } else if (delta < 0){
        deltaHint.textContent = `سيتم تسجيل تسوية زيادة قدرها ${(Math.abs(delta)).toFixed(2)} JOD (يزيد الرصيد).`;
        deltaHint.className = 'form-text text-warning';
      } else {
        deltaHint.textContent = 'لا يوجد فرق.';
        deltaHint.className = 'form-text text-muted';
      }
    }
    newAmount?.addEventListener('input', renderDelta);

    document.querySelectorAll('.btnEditPayment')?.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const id   = btn.getAttribute('data-id');
        const uid  = btn.getAttribute('data-user_id');
        const amt  = parseFloat(btn.getAttribute('data-amount') || '0');
        const doc  = btn.getAttribute('data-doc') || '';

        document.getElementById('edit_tx_id').value = id;
        oldAmount.value = isFinite(amt) ? amt.toFixed(2) : '0.00';
        newAmount.value = oldAmount.value;
        docField.value  = doc;

        const s = STATS[uid] || null;
        if (s){
          const remain = Math.max(0, (s.limit || 0) - (s.bal || 0));
          balanceHint.textContent = `الرصيد الحالي: ${Number(s.bal).toFixed(2)} — السقف: ${Number(s.limit).toFixed(2)} — المتاح: ${remain.toFixed(2)} JOD`;
        } else {
          balanceHint.textContent = '';
        }

        renderDelta();
        // عدّل هذا حسب مسار API لديك:
        const baseAdjustUrl = "{{ url('/pos/api/sales-transactions') }}";
        editForm.action = `${baseAdjustUrl}/${id}/adjust`;
      });
    });
  })();
});
</script>
@endpush

