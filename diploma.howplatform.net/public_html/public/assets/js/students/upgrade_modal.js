// public/assets/js/student/upgrade_modal.js
// موديول المودال (ترقية الحساب) — يعتمد تضمين View خارجي عبر cfg.ui.modalUrl
// - Event Delegation ليتوافق مع التحميل الديناميكي
// - لا يوجد أي HTML/CSS داخل JS
// - يستخدم upg- selectors كما في الـ View

let _state = {
  inited: false,
  cfg: null,
  cache: { plans: null, methods: null, ts: 0 },
  handlers: [],
  injected: false,
};

/* ========================= Utils ========================= */

// قراءة CSRF من الإعدادات أو من نافذة عامة (fallback)
function getCsrf() {
  const c = _state.cfg?.csrf || window.CI_CSRF || {};
  return { token: c.token || c.tokenName, hash: c.hash || c.tokenHash };
}

// جلب JSON مع معالجة الأخطاء
async function fetchJSON(url) {
  const res = await fetch(url, { credentials: 'same-origin' });
  if (!res.ok) throw new Error('Network error');
  return res.json();
}

// حقن الـ View (HTML المودال) مرة واحدة عبر modalUrl
async function ensureModalInjected() {
  if (_state.injected && document.getElementById('upgradeModal')) return;

  const url = _state.cfg?.ui?.modalUrl;
  if (!url) {
    console.error('upgrade_modal.js: modalUrl غير ممرّر في init({...})');
    throw new Error('تعذر تحميل المودال (modalUrl مفقود)');
  }

  const res = await fetch(url, { credentials: 'same-origin' });
  if (!res.ok) {
    if (res.status === 401) throw new Error('غير مصرح');
    if (res.status === 403) throw new Error('الميزة غير متاحة لحسابك');
    throw new Error('تعذر تحميل واجهة المودال');
  }

  const html = await res.text();
  document.body.insertAdjacentHTML('beforeend', html);
  _state.injected = true;
}

/* ========================= Core ========================= */

// فتح/إغلاق
async function openModal() {
  await ensureModalInjected();

  const modal = document.getElementById('upgradeModal');
  if (!modal) throw new Error('واجهة المودال غير متاحة');

  modal.classList.add('show');
  modal.setAttribute('aria-hidden', 'false');

  try {
    await renderPlans();
  } catch (err) {
    alert('حدث خطأ في تحميل الخطط: ' + (err?.message || ''));
  }
}

function closeModal() {
  const modal = document.getElementById('upgradeModal');
  if (!modal) return;
  modal.classList.remove('show');
  modal.setAttribute('aria-hidden', 'true');
}

// رسم الكروت ديناميكياً (مع Cache)
async function renderPlans() {
  const slot = document.querySelector('#upgradeModal [data-slot="plans"]');
  if (!slot) return;

  // كاش لـ 5 دقائق
  const now = Date.now();
  const fresh = _state.cache.plans && _state.cache.methods && (now - _state.cache.ts < 5 * 60 * 1000);

  let plans, methods;
  if (fresh) {
    ({ plans, methods } = _state.cache);
  } else {
    const [pRes, mRes] = await Promise.all([
      fetchJSON(_state.cfg.endpoints.plans),
      fetchJSON(_state.cfg.endpoints.payMethods),
    ]);
    plans = pRes.data || [];
    methods = mRes.data || [];
    _state.cache = { plans, methods, ts: now };
  }

  slot.innerHTML = ''; // تفريغ

  plans.forEach((p, idx) => {
    const badgeClass = idx === 1 ? 'silver' : (idx === 2 ? 'gold' : 'bronze');
    const badgeText  = idx === 1 ? 'الأفضل قيمة' : (idx === 2 ? 'الأكثر توفيراً' : 'الأكثر شهرة');

    const featuresHtml = (p.features || []).map(f => `
      <div class="upg-feature"><i class="fas fa-check-circle"></i><span>${f}</span></div>
    `).join('');

    const mOptions = methods.map(m => `<option value="${m.id}">${m.name}</option>`).join('');

    const card = document.createElement('div');
    card.className = `upg-card ${badgeClass}`;
    card.innerHTML = `
      <div class="upg-badge">${badgeText}</div>
      <div class="upg-header">
        <div class="upg-name">${p.name}</div>
        <div class="upg-duration">${p.duration_days} يوم</div>
      </div>
      <div class="upg-price">
        <span class="upg-price-amt">${Number(p.price).toFixed(2)}</span>
        <span class="upg-price-cur">دينار</span>
      </div>
      <div class="upg-features">${featuresHtml}</div>
      <div class="upg-actions">
        <div style="margin-bottom:10px">
          <select class="upg-ddl-method" style="width:100%;padding:10px;border-radius:10px;border:1px solid #ddd;">
            <option value="">اختر طريقة الدفع</option>
            ${mOptions}
          </select>
        </div>
        <button class="upg-btn" data-plan="${p.id}">اختيار الباقة</button>
      </div>
    `;
    slot.appendChild(card);
  });
}

// إرسال طلب الترقية
async function createUpgradeRequest(planId, methodId) {
  const { token, hash } = getCsrf();
  const form = new FormData();
  form.append('plan_type_id', String(planId));
  if (methodId) form.append('payment_method_id', String(methodId));
  if (token && hash) form.append(token, hash);

  const res = await fetch(_state.cfg.endpoints.request, {
    method: 'POST',
    body: form,
    credentials: 'same-origin',
  });

  const j = await res.json();
  if (!res.ok || j.error) throw new Error(j.error || 'فشل إنشاء الطلب');
  return j;
}

/* ========================= Delegation ========================= */

function bindDelegatedEvents() {
  const clickHandler = async (e) => {
    // فتح المودال من أي زر جديد/قديم
    const openBtn = e.target.closest?.('.btn-upgrade');
    if (openBtn) {
      e.preventDefault();
      try {
        await openModal();
      } catch (err) {
        alert('تعذر فتح نافذة الترقية: ' + (err?.message || ''));
      }
      return;
    }

    // إغلاق
    const closeBtn = e.target.closest?.('#upgradeModal .close-modal, #upgradeModal .btn-cancel');
    if (closeBtn) {
      e.preventDefault();
      return closeModal();
    }

    // نقر خارج المحتوى
    const overlay = document.getElementById('upgradeModal');
    if (overlay && e.target === overlay) {
      return closeModal();
    }

    // اختيار الباقة
    const selectBtn = e.target.closest?.('#upgradeModal .upg-btn');
    if (selectBtn) {
      e.preventDefault();
      const card = selectBtn.closest('.upg-card');
      const planId = selectBtn.getAttribute('data-plan');
      const method = card?.querySelector('.upg-ddl-method')?.value || '';
      try {
        await createUpgradeRequest(planId, method);
        alert('تم إنشاء طلب الترقية. سنبلغك عند اعتماد الدفع ✨');
        closeModal();
      } catch (err) {
        alert('خطأ: ' + (err?.message || ''));
      }
    }
  };

  const keyHandler = (e) => {
    if (e.key === 'Escape') closeModal();
  };

  document.addEventListener('click', clickHandler);
  document.addEventListener('keydown', keyHandler);
  _state.handlers.push(['click', clickHandler], ['keydown', keyHandler]);
}

function unbindDelegatedEvents() {
  _state.handlers.forEach(([type, fn]) => document.removeEventListener(type, fn));
  _state.handlers = [];
}

/* ========================= Public API ========================= */

/**
 * init(cfg):
 * - تُستدعى مرة واحدة في layout بعد تشغيل الـ router
 * - cfg = {
 *     ui: { modalUrl: '<?= site_url("ui/upgrade-modal") ?>' }, // إجباري
 *     endpoints: { plans, payMethods, request },
 *     csrf: { token, hash }
 *   }
 */
export function init(cfg) {
  _state.cfg = { ..._state.cfg, ...cfg };
  if (_state.inited) return;
  // تأكيد تمرير رابط تضمين المودال
  if (!_state.cfg?.ui?.modalUrl) {
    console.error('upgrade_modal.js: cfg.ui.modalUrl is required');
  }
  _state.inited = true;
  bindDelegatedEvents();
}

/**
 * destroy():
 * - اختياري لو حاب تنظف عند تفريغ التطبيق بالكامل
 */
export function destroy() {
  unbindDelegatedEvents();
  _state = {
    inited: false,
    cfg: null,
    cache: { plans: null, methods: null, ts: 0 },
    handlers: [],
    injected: false,
  };
}
