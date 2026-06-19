/* public/assets/js/students/sections/upgrade.js
 * واجهة الترقية/الاشتراكات - نسخة مُحدَّثة بلا أي كاش + متوافقة مع التنقل الديناميكي
 * - init() يعيد الجلب والرسم في كل مرة
 * - _bound يحمي ربط الأحداث فقط
 * - إلغاء fetch عند مغادرة الصفحة (AbortController)
 * - جميع الطلبات تستخدم no-store + مُعامل زمني لمنع التخزين المؤقت تمامًا
 */

let _bound   = false;   // لمنع ازدواج المستمعات فقط
let _onEsc   = null;    // مستمع ESC لإغلاق المودالات
let _ctrl    = null;    // AbortController لإلغاء الطلبات عند cleanup()

// متغيرات لمودال الوصول/التركيز
let _lastActiveEl = null; // آخر عنصر كان عليه التركيز قبل فتح المودال
let _trapFn = null;       // دالة فوكس-تراب (Tab cycling)

// إحضار العناصر القابلة للتركيز داخل حوار
function getFocusable(el){
  return Array.from(el.querySelectorAll(
    'a[href], button, input, textarea, select, summary, details, [tabindex]:not([tabindex="-1"])'
  )).filter(n => !n.hasAttribute('disabled') && n.getAttribute('aria-hidden') !== 'true');
}

export async function init() {
  const root = document.querySelector('[data-component="student-upgrade"]');
  if (!root) return;

  // ألغِ أي طلب سابق قبل البدء بدورة جديدة
  if (_ctrl) { try { _ctrl.abort(); } catch {} }
  _ctrl = new AbortController();

  // مراجع عناصر الصفحة
  const refs = {
    // أقسام الصفحة
    plansGrid:     root.querySelector('[data-ref="plansGrid"]'),
    actionsBar:    root.querySelector('[data-ref="actionsBar"]'),
    voucherInput:  root.querySelector('[data-ref="voucherInput"]'),
    verifyResult:  root.querySelector('[data-ref="verifyResult"]'),
    confirmBar:    root.querySelector('[data-ref="confirmBar"]'),
    modalSales:    root.querySelector('[data-modal="sales"]'),
    modalActivate: root.querySelector('[data-modal="activate"]'),

    // لعرض الحالة
    stateBody:     root.querySelector('[data-ref="stateBody"]'),
    upcomingBody:  root.querySelector('[data-ref="upcomingBody"]'),
  };

  const state = {
    selectedPlanId: null,
    verifyData: null,
    busy: false,
  };

  /* ---------- Helpers ---------- */
  const qsa  = (sel, el = document) => Array.from(el.querySelectorAll(sel));
  const show = (el) => el && (el.hidden = false);
  const hide = (el) => el && (el.hidden = true);

  const escapeHtml = (s) =>
    String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

  // ✅ إبقِ الشرطة "-" كما هي؛ احذف فقط المسافات وحوّل لأحرف كبيرة
  const normalizeCode = (code) =>
    String(code || '')
      .toUpperCase()
      .replace(/\s+/g, '')
      .trim();

  const setBusy = (val) => {
    state.busy = !!val;
    qsa('button, input', root).forEach((el) => (el.disabled = state.busy));
    root.classList.toggle('is-busy', state.busy);
  };

  const toast = (msg, type = 'info') => {
    if (window.showToast) return window.showToast(msg, { type });
    alert(msg);
  };

  // منع الكاش كليًا لكل طلبات هذه الصفحة
  const withTs = (url) => {
    const sep = url.includes('?') ? '&' : '?';
    return `${url}${sep}_=${Date.now()}`;
  };
  const noStoreFetch = (url, options = {}) => {
    const headers = {
      'Cache-Control': 'no-store',
      'Pragma': 'no-cache',
      'X-Requested-With': 'XMLHttpRequest',
      ...(options.headers || {})
    };
    return fetch(withTs(url), {
      cache: 'no-store',
      credentials: 'same-origin',
      signal: options.signal || _ctrl.signal,
      method: options.method || 'GET',
      headers,
      body: options.body
    });
  };

  // قراءة ديب-لينك من الهاش #/upgrade?code=...&open=...&autoverify=1
  function readUpgradeFromHash() {
    const raw = (window.location.hash || '').replace(/^#\/?/, '');
    const [seg, qs] = raw.split('?');
    if (seg !== 'upgrade') return null;
    const p = new URLSearchParams(qs || '');
    return {
      code:       (p.get('code') || '').toString(),
      open:       (p.get('open') || ''),
      autoverify: (p.get('autoverify') || '')
    };
  }

  /* ================================
   * استخراج/عرض الحد من limits_json فقط
   * ================================ */

  // تنسيق رقم بسيط دون فواصل أو كسور لا لزوم لها
  function formatPlainNumber(n) {
    const num = Number(n);
    if (!Number.isFinite(num)) return '';
    return Number.isInteger(num) ? String(num) : String(+num.toFixed(2)).replace(/\.00$/, '');
  }

  /** ترجمة نص الحد من الإنجليزية إلى العربية قدر الإمكان (limits_json فقط) */
  function translateLimitText(input) {
    let s = String(input || '').trim();
    if (!s) return '';

    // حالات خاصة شائعة أولاً
    s = s.replace(/\bpriority support\b/gi, 'دعم أولوية');
    s = s.replace(/\bvip support\b/gi, 'دعم كبار الشخصيات');

    // الكلمات المطلوبة تحديدًا
    s = s.replace(/\bbasic\b/gi, 'أساسي');
    s = s.replace(/\bpro\b/gi, 'احترافي');
    s = s.replace(/\belite\b/gi, 'نخبة');
    s = s.replace(/\bstandard\b/gi, 'قياسي');
    s = s.replace(/\bpriority\b/gi, 'أولوية');
    s = s.replace(/\bvip\b/gi, 'كبار الشخصيات');

    // تعبيرات "غير محدود"
    if (/(unlimited|no\s*limit|infinite|∞)/i.test(s)) s = 'غير محدود';

    // وحدات/تواتر
    s = s.replace(/\/\s*day|\bper\s*day\b|\bdaily\b/gi, ' يوميًا');
    s = s.replace(/\/\s*week|\bper\s*week\b|\bweekly\b/gi, ' أسبوعيًا');
    s = s.replace(/\/\s*month|\bper\s*month\b|\bmonthly\b/gi, ' شهريًا');

    // عبارات شائعة
    s = s.replace(/\b(max(?:imum)?|up to|at most)\b/gi, 'بحد أقصى');
    s = s.replace(/\brequests?\b/gi, 'طلبات');
    s = s.replace(/\bdownloads?\b/gi, 'تنزيلات');
    s = s.replace(/\bmessages?\b/gi, 'رسائل');
    s = s.replace(/\bminutes?\b/gi, 'دقيقة');
    s = s.replace(/\bhours?\b/gi, 'ساعة');
    s = s.replace(/\bfiles?\b/gi, 'ملف');
    s = s.replace(/\bitems?\b/gi, 'عنصر');

    // مسافات لطيفة قبل "يوميًا/أسبوعيًا/شهريًا" لو كانت ملتصقة برقم
    s = s.replace(/(\d)(?=\s*(يوميًا|أسبوعيًا|شهريًا))/g, '$1 ');

    // تنظيف مسافات زائدة
    s = s.replace(/\s+/g, ' ').trim();

    return s;
  }

  /**
   * يُعيد كائن عرض للحد المستخرج من limits_json:
   * { kind: 'number'|'text'|'', value: string }
   * - رقم مباشر/داخل كائن ⇒ kind='number'
   * - نص (مثل "5 per day" أو "unlimited") ⇒ kind='text' بعد ترجمة بسيطة
   */
  function getLimitDisplay(limits_json) {
    if (limits_json === null || limits_json === undefined || limits_json === '') {
      return { kind: '', value: '' };
    }

    // رقم مباشر
    if (typeof limits_json === 'number') {
      return { kind: 'number', value: formatPlainNumber(limits_json) };
    }

    // نص
    if (typeof limits_json === 'string') {
      const s = limits_json.trim();

      // رقم كنص (صِرف) → نعامله كرقم
      if (/^\d+(\.\d+)?$/.test(s)) {
        return { kind: 'number', value: formatPlainNumber(s) };
      }

      // جرّب JSON
      try {
        const parsed = JSON.parse(s);
        return getLimitDisplay(parsed);
      } catch {
        // نص حر → نترجمه ونعرضه كنص
        return { kind: 'text', value: translateLimitText(s) };
      }
    }

    // كائن
    if (typeof limits_json === 'object') {
      if (limits_json.unlimited === true || limits_json.is_unlimited === true) {
        return { kind: 'text', value: 'غير محدود' };
      }

      // مفاتيح مفضلة للأرقام
      const prefKeys = [
        'per_day','perDay','per_week','perWeek','per_month','perMonth',
        'limit','max','value','count','requests','downloads','minutes','hours'
      ];
      for (const k of prefKeys) {
        const v = limits_json[k];
        if (v !== undefined && v !== null && Number.isFinite(Number(v))) {
          return { kind: 'number', value: formatPlainNumber(v) };
        }
      }

      // أي نصوص بداخل الكائن؟ خذ أول نص مفهوم
      for (const v of Object.values(limits_json)) {
        if (typeof v === 'string' && v.trim()) {
          return { kind: 'text', value: translateLimitText(v) };
        }
      }

      // وإلا التقط أكبر رقم
      const nums = Object.values(limits_json)
        .map(Number)
        .filter((v) => Number.isFinite(v));
      if (nums.length) {
        return { kind: 'number', value: formatPlainNumber(Math.max(...nums)) };
      }
    }

    return { kind: '', value: '' };
  }

  /**
   * عنصر <li> لميزة واحدة:
   * - اسم الميزة كما هو (العناوين عربية لديك)
   * - بجانبه "قيمة الحد" من limits_json (رقم فقط أو نص مترجم)
   */
  function featureLiHtml(f) {
    // سلاسل نصية قديمة → نعرضها فقط
    if (typeof f === 'string') {
      const txt = String(f).trim();
      if (!txt) return '';
      return `
        <li class="upg-feature">
          <span class="upg-feature__icon" aria-hidden="true"><i class="fas fa-check"></i></span>
          <span class="upg-feature__text">${escapeHtml(txt)}</span>
        </li>`;
    }

    // كائن حديث: { feature_key, feature_text?, limits_json }
    const name = String(f?.feature_text ?? f?.feature_key ?? '').trim();
    const disp = getLimitDisplay(f?.limits_json);

    let limitSpan = '';
    if (disp.value) {
      const cls = disp.kind === 'text' ? 'is-text' : '';
      const dir = disp.kind === 'number' ? 'dir="ltr"' : '';
      limitSpan = `<span class="upg-feature__limit ${cls}" ${dir}>${escapeHtml(disp.value)}</span>`;
    }

    return `
      <li class="upg-feature">
        <span class="upg-feature__icon" aria-hidden="true"><i class="fas fa-check"></i></span>
        <span class="upg-feature__text">${escapeHtml(name)}</span>
        ${limitSpan}
      </li>`;
  }

  /* =========================================================
   *  STATE (current / upcoming / features)
   * =======================================================*/
  async function loadState() {
    try {
      if (refs.stateBody)    refs.stateBody.innerHTML    = `<p class="upg-muted">جاري تحميل الحالة...</p>`;
      if (refs.upcomingBody) refs.upcomingBody.innerHTML = `<p class="upg-muted">جاري التحميل...</p>`;

      const res = await noStoreFetch('/students/pages/upgrade/state');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      renderState(json);
    } catch (e) {
      console.error('loadState error:', e);
      if (refs.stateBody)    refs.stateBody.innerHTML    = `<div class="upg-msg upg-msg--error">تعذّر جلب الحالة الآن.</div>`;
      if (refs.upcomingBody) refs.upcomingBody.innerHTML = `<p class="upg-muted">—</p>`;
    }
  }

  // تنسيق تاريخ عربي (توقيت عمّان)
  function fmtDateAr(input) {
    if (!input) return '';
    const d = new Date(input);
    if (!Number.isNaN(d.getTime())) {
      try {
        const parts = new Intl.DateTimeFormat('en-GB', {
          year: 'numeric', month: '2-digit', day: '2-digit', timeZone: 'Asia/Amman'
        }).formatToParts(d);
        const yy = parts.find(p => p.type === 'year')?.value || '';
        const mm = parts.find(p => p.type === 'month')?.value || '';
        const dd = parts.find(p => p.type === 'day')?.value || '';
        const MONTHS_AR = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
        const mIndex = Math.max(1, Math.min(12, parseInt(mm, 10))) - 1;
        const day = String(parseInt(dd, 10));
        const monthName = MONTHS_AR[mIndex] || mm;
        return `${day} ${monthName} ${yy}`;
      } catch {}
    }
    const s = String(input);
    const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) {
      const yy = m[1], mm = m[2], dd = m[3];
      const MONTHS_AR = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
      const monthName = MONTHS_AR[Math.max(1, Math.min(12, parseInt(mm, 10))) - 1] || mm;
      return `${parseInt(dd,10)} ${monthName} ${yy}`;
    }
    return s;
  }

  // عرض الحالة والميزات (مع حدّ الميزة)
  function renderState(json) {
    // الخطة الحالية
    if (json && json.current_plan) {
      const cp = json.current_plan;
      const feats = Array.isArray(json.features) ? json.features : [];

      const featsHtml = feats.length
        ? `<ul class="upg-features" role="list">${feats.map(featureLiHtml).join('')}</ul>`
        : `<p class="upg-muted upg-features--empty">لا مزايا معروضة لهذه الخطة.</p>`;

      refs.stateBody.innerHTML = `
        <div class="upg-current__row">
          <div class="upg-current__item">
            <span class="upg-current__label">الخطة الحالية:</span>
            <strong class="upg-current__value">${escapeHtml(cp.name)} (Lv.${cp.level})</strong>
          </div>
          <div class="upg-current__item">
            <span class="upg-current__label">الانتهاء:</span>
              <strong class="upg-current__value">${escapeHtml(fmtDateAr(cp.end_at))}</strong>
          </div>
        </div>

        <div class="upg-current__features">
          <h4 class="upg-current__features-title">مزايا الخطة الحالية</h4>
          ${featsHtml}
        </div>
      `;

      refs.stateBody.querySelector('[data-action="open-activate"]')?.addEventListener('click', () => {
        openModal(refs.modalActivate);
        refs.voucherInput?.focus();
      });

    } else {
      refs.stateBody.innerHTML = `
        <p class="upg-muted">لا يوجد لديك اشتراك فعّال.</p>
        <div class="upg-current__actions">
          <button type="button" class="btn btn-primary" data-action="open-activate">
            <i class="fas fa-key"></i> فعِّل كود
          </button>
        </div>`;
      refs.stateBody.querySelector('[data-action="open-activate"]')?.addEventListener('click', () => {
        openModal(refs.modalActivate);
        refs.voucherInput?.focus();
      });
    }

    // الخطة القادمة
    if (json && json.upcoming_plan) {
      const up = json.upcoming_plan;
      refs.upcomingBody.innerHTML = `
        <div class="upg-upcoming__row">
          <div class="upg-upcoming__item">
            <span class="upg-upcoming__label">الخطة:</span>
            <strong class="upg-upcoming__value">${escapeHtml(up.name)} (Lv.${up.level})</strong>
          </div>
          <div class="upg-upcoming__item">
            <span class="upg-upcoming__label">من:</span>
            <strong class="upg-upcoming__value">${escapeHtml(fmtDateAr(up.start_at))}</strong>
          </div>
          <div class="upg-upcoming__item">
            <span class="upg-upcoming__label">إلى:</span>
            <strong class="upg-upcoming__value">${escapeHtml(fmtDateAr(up.end_at))}</strong>
          </div>
        </div>`;
    } else {
      refs.upcomingBody.innerHTML = `<p class="upg-muted">لا توجد خطة قادمة.</p>`;
    }
  }

  async function refreshStateCard() {
    await loadState();
  }

  /* =========================================================
   *  PLANS (normalize response & render) + v2 fallback
   * =======================================================*/
  async function fetchJsonWithFallback(urls) {
    for (const url of urls) {
      try {
        const res = await noStoreFetch(url);
        if (!res.ok) continue;
        return await res.json();
      } catch {
        // جرّب التالي
      }
    }
    throw new Error('No plan endpoint responded.');
  }

  function getFeatureText(f) {
    if (!f) return '';
    if (typeof f === 'string') return f;
    return String(f.feature_text ?? f.description ?? f.feature_key ?? '');
  }

  function normalizePlan(p) {
    if (!p || typeof p !== 'object') return null;
    return {
      id: Number(p.id ?? p.plan_id ?? 0),
      name: String(p.name ?? p.plan_name ?? '—'),
      amount: (p.amount ?? p.price ?? p.price_jod ?? '—'),
      duration_days: Number(p.duration_days ?? p.duration ?? 0),
      level: Number(p.level ?? 0),
      features: Array.isArray(p.features) ? p.features : []
    };
  }

  async function loadPlans() {
    try {
      refs.plansGrid.innerHTML = `<div class="upg-skeleton-grid">
        ${'<div class="upg-skeleton-card"></div>'.repeat(3)}
      </div>`;

      const json = await fetchJsonWithFallback(['/api/v2/plans', '/api/plans']);

      const raw = Array.isArray(json) ? json
                : Array.isArray(json?.data) ? json.data
                : Array.isArray(json?.plans) ? json.plans
                : [];

      const plans = raw.map(normalizePlan).filter(Boolean);
      if (!plans.length) return renderEmpty('لا توجد خطط متاحة الآن.');
      renderPlans(plans);
    } catch (e) {
      console.error(e);
      renderEmpty('تعذر تحميل الخطط الآن.');
    }
  }

  function renderEmpty(msg) {
    refs.plansGrid.innerHTML = `
      <div class="upg-empty">
        <i class="fas fa-inbox"></i>
        <div>${escapeHtml(msg)}</div>
      </div>`;
    hide(refs.actionsBar);
  }

  function renderPlans(plans) {
    const html = plans.map((p) => {
      const features = Array.isArray(p.features) ? p.features : [];

      // ✅ نستخدم featureLiHtml لإظهار الحد (رقم/نص) بجانب اسم الميزة
      const feats = features
        .map((f) => {
          if (typeof featureLiHtml === 'function') {
            return featureLiHtml(f);
          }
          const txt = getFeatureText(f);
          return txt
            ? `
              <li class="upg-feature">
                <span class="upg-feature__icon" aria-hidden="true"><i class="fas fa-check"></i></span>
                <span class="upg-feature__text">${escapeHtml(txt)}</span>
              </li>
            `
            : '';
        })
        .join('');

      const featsBlock = feats
        ? `<ul class="upg-features upg-plan__features" role="list">${feats}</ul>`
        : `<p class="upg-muted upg-features--empty">لا مزايا متاحة لهذه الخطة.</p>`;

      const amount = (p.amount === null || p.amount === undefined || p.amount === '' || Number.isNaN(Number(p.amount)))
        ? '—'
        : String(p.amount);

      const dur = (p.duration_days && Number(p.duration_days) > 0)
        ? String(p.duration_days)
        : '—';

      const pressed = (state.selectedPlanId === p.id);

      return `
        <article class="upg-plan ${pressed ? 'is-selected' : ''}"
                 data-plan-id="${p.id}" tabindex="0"
                 role="button" aria-pressed="${pressed}">
          <header class="upg-plan__head">
            <div class="upg-plan__title">${escapeHtml(p.name)}</div>
            <div class="upg-plan__price">
              <span class="upg-plan__amount">${escapeHtml(amount)}</span>
              <span class="upg-plan__currency">JOD</span>
            </div>
          </header>
          <div class="upg-plan__meta">المدة: ${escapeHtml(dur)} يوم</div>

          ${featsBlock}

          <footer class="upg-plan__foot"><!-- reserved --></footer>
        </article>
      `;
    }).join('');

    refs.plansGrid.innerHTML = html;

    // اختيار الخطة عبر النقر على البطاقة
    qsa('.upg-plan', refs.plansGrid).forEach((card) => {
      card.addEventListener('click', () => {
        const pid = parseInt(card.getAttribute('data-plan-id'), 10);
        state.selectedPlanId = pid;
        highlightSelectedPlan(pid);
        show(refs.actionsBar);
      });

      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          const pid = parseInt(card.getAttribute('data-plan-id'), 10);
          state.selectedPlanId = pid;
          highlightSelectedPlan(pid);
          show(refs.actionsBar);
        }
      });
    });

    // (لو بقي لديك أزرار select-plan قديمة داخل البطاقات، ندعمها أيضًا)
    qsa('[data-action="select-plan"]', refs.plansGrid).forEach((btn) => {
      btn.addEventListener('click', () => {
        state.selectedPlanId = parseInt(btn.getAttribute('data-plan-id'), 10);
        highlightSelectedPlan(state.selectedPlanId);
        show(refs.actionsBar);
      });
    });
  }

  function highlightSelectedPlan(id) {
    qsa('.upg-plan', refs.plansGrid).forEach((card) => {
      const pid = parseInt(card.getAttribute('data-plan-id'), 10);
      const active = pid === id;
      card.classList.toggle('is-selected', active);
      card.setAttribute('aria-pressed', String(active));
    });
  }

  /* =========================================================
   *  MODALS — منطق عرض/إخفاء متوافق مع الستايل الجديد
   * =======================================================*/
  hide(refs.modalSales);
  hide(refs.modalActivate);

  function openModal(mod) {
    if (!mod) return;

    // أغلق أي مودال آخر أولاً
    closeModal();

    // سجّل العنصر المُركّز لاستعادته بعد الإغلاق
    _lastActiveEl = document.activeElement;

    // أظهر المودال + إعداد سمات الوصول
    mod.hidden = false;
    mod.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');

    const dlg = mod.querySelector('.upg-modal__dialog');
    if (dlg) {
      if (!dlg.hasAttribute('tabindex')) dlg.setAttribute('tabindex', '-1');
      dlg.focus({ preventScroll: true });

      // فوكس تراب داخل الحوار (tab cycling)
      _trapFn = (e) => {
        if (e.key !== 'Tab') return;
        const items = getFocusable(dlg);
        if (!items.length) return;
        const first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault(); first.focus();
        }
      };
      dlg.addEventListener('keydown', _trapFn);
    }
  }

  function closeModal() {
    // أخفِ كلا المودالين إن وُجدا
    [refs.modalSales, refs.modalActivate].forEach((mod) => {
      if (!mod) return;
      mod.setAttribute('aria-hidden', 'true');
      mod.hidden = true;
      const dlg = mod.querySelector('.upg-modal__dialog');
      if (dlg && _trapFn) dlg.removeEventListener('keydown', _trapFn);
    });

    document.body.classList.remove('modal-open');
    _trapFn = null;

    // استعادة التركيز إلى آخر عنصر كان نشطًا
    try { _lastActiveEl && _lastActiveEl.focus({ preventScroll: true }); } catch {}
    _lastActiveEl = null;

    // نظّف حالة النتائج/التأكيد كما هو لديك
    if (refs.verifyResult) refs.verifyResult.hidden = true;
    if (refs.confirmBar)  refs.confirmBar.hidden = true;
    state.verifyData = null;
    if (refs.voucherInput) refs.voucherInput.value = '';
  }

  // فتح "نقاط البيع" ← تعديل مطلوب: توجيه مباشر للرابط الخارجي
  qsa('[data-action="open-sales"]', root).forEach((el) =>
    el.addEventListener('click', (ev) => {
      // توجيه صريح إلى صفحة نقاط البيع
      ev.preventDefault(); // حتى لو كان العنصر <button> نحفظ السلوك موحّدًا
      window.location.assign('https://wa.me/962782550901?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%20%D8%A3%D8%B1%D9%8A%D8%AF%20%D9%85%D8%B9%D8%B1%D9%81%D8%A9%20%D9%83%D9%8A%D9%81%D9%8A%D8%A9%20%D8%A7%D9%84%D8%AD%D8%B5%D9%88%D9%84%20%D8%B9%D9%84%D9%89%20%D8%A7%D8%B4%D8%AA%D8%B1%D8%A7%D9%83%20%D9%81%D9%8A%20%D9%85%D9%86%D8%B5%D8%A9%20%D8%A7%D9%84%D8%AF%D8%A8%D9%84%D9%88%D9%85%20%D8%A7%D9%84%D8%B9%D8%A7%D9%84%D9%8A%D8%9F');
    })
  );

  qsa('[data-action="open-activate"]', root).forEach((el) =>
    el.addEventListener('click', () => {
      openModal(refs.modalActivate);
      refs.voucherInput?.focus();
    }));

  // إغلاق بزر ×
  qsa('[data-action="close-modal"]', root).forEach((el) =>
    el.addEventListener('click', () => closeModal()));

  // إغلاق بالنقر على الخلفية
  [refs.modalSales, refs.modalActivate].forEach((mod) => {
    if (!mod) return;
    mod.addEventListener('click', (e) => { if (e.target === mod) closeModal(); });
  });

  // إغلاق بـ ESC (مستمع عام)
  const onEsc = (e) => {
    if (e.key === 'Escape') {
      const anyOpen = (refs.modalSales && !refs.modalSales.hidden) || (refs.modalActivate && !refs.modalActivate.hidden);
      if (anyOpen) { e.preventDefault(); closeModal(); }
    }
  };
  _onEsc = onEsc;
  document.addEventListener('keydown', onEsc);

  // ✅ تنظيف إدخال الكود مباشرة أثناء الكتابة (يحافظ على الشرطة)
  refs.voucherInput?.addEventListener('input', () => {
    const cur  = refs.voucherInput.value;
    const norm = normalizeCode(cur);
    if (cur !== norm) refs.voucherInput.value = norm;
  });

  // لصق سريع
  root.querySelector('[data-action="paste"]')?.addEventListener('click', async () => {
    try {
      const text = await navigator.clipboard.readText();
      if (text && refs.voucherInput) refs.voucherInput.value = normalizeCode(text);
    } catch { /* تجاهل */ }
  });

  /* =========================================================
   *  VERIFY
   * =======================================================*/
  root.querySelector('[data-action="verify"]')?.addEventListener('click', onVerify);

  async function onVerify() {
    if (state.busy) return;
    const code = normalizeCode(refs.voucherInput?.value);
    if (!code) return toast('أدخل كود الفاوتشر أولاً.', 'warning');
    setBusy(true);
    try {
      const form = new FormData();
      form.append('code', code); // نرسل مع الشرطات كما هي
      const res = await noStoreFetch('/api/vouchers/verify', {
        method: 'POST',
        body: form
      });

      if (res.status === 403) {
        showVerify('الميزة غير متاحة لحسابك حاليًا (403).', 'warn');
        return;
      }
      if (!res.ok) {
        showVerify('تعذر التحقق من الكود الآن.', 'error');
        return;
      }

      const json = await res.json();
      handleVerify(json);
    } catch (e) {
      if (e?.name !== 'AbortError') console.error(e);
      showVerify('تعذر الاتصال. حاول لاحقًا.', 'error');
    } finally {
      setBusy(false);
    }
  }

  function handleVerify(json) {
    if (!json || !json.status) return showVerify('استجابة غير متوقعة.', 'error');

    if (json.status === 'valid') {
      state.verifyData = json.data || null;
      const d = state.verifyData || {};
      refs.verifyResult.innerHTML = `
        <div class="upg-ok">
          <div><span>الخطة:</span> <strong>${escapeHtml(d.plan_name || '')}</strong></div>
          <div><span>المدة:</span> <strong>${d.duration_days} يوم</strong></div>
          <div><span>الإجراء:</span> <strong>${escapeHtml(d.action || '')}</strong></div>
          <div><span>الانتهاء الجديد:</span> <strong>${escapeHtml(d.will_expire_to || '')}</strong></div>
        </div>`;
      show(refs.verifyResult);
      show(refs.confirmBar);
      return;
    }

    const map = {
      expired: 'الكود منتهي الصلاحية.',
      redeemed: 'الكود مستخدم مسبقًا.',
      unknown: 'الكود غير موجود. تواصل مع نقطة البيع.',
      policy_denied: 'لا يمكن تفعيل هذه الخطة حسب السياسة.',
    };
    showVerify(map[json.status] || 'لا يمكن التحقق من الكود الآن.', 'warn');
  }

  function showVerify(msg, type = 'warn') {
    refs.verifyResult.innerHTML = `<div class="upg-msg upg-msg--${type}">${escapeHtml(msg)}</div>`;
    show(refs.verifyResult);
    hide(refs.confirmBar);
    state.verifyData = null;
  }

  /* =========================================================
   *  REDEEM
   * =======================================================*/

  // دالة مسح الكاش عبر زر السايدبار مع خطة احتياط (مطلوب)
// دالة مسح الكاش عبر زر السايدبار مع خطة احتياط + لوج نجاح (DEBUG)
function clearAllClientCachesViaSidebar() {
  // 1) نحاول استدعاء زر "تحديث البيانات" الحالي لديك
  const refreshBtn = document.getElementById('refresh-courses');
  if (refreshBtn) {
    try {
      refreshBtn.dispatchEvent(new Event('click', { bubbles: true, cancelable: true }));
      // TODO: إزالة هذا اللوج بعد التأكد أن المنطق يعمل تمامًا
      console.log('[DEBUG] cache-clear: dispatched click on #refresh-courses (sidebar routine)');
      return; // تم تفويض المهمة لروتينك الحالي
    } catch (_) {
      // نكمل للخطة الاحتياطية
    }
  }

  // 2) الخطة الاحتياطية: تنظيف محلي فقط
  try { localStorage.clear(); } catch (_) {}
  try { sessionStorage.clear(); } catch (_) {}

  // وعود لمسح CacheStorage و إلغاء تسجيل SW إن وُجدا
  let cachePromise = Promise.resolve();
  if ('caches' in window) {
    cachePromise = caches.keys()
      .then(keys => Promise.all(keys.map(k => caches.delete(k))))
      .catch(() => {}); // تجاهل الأخطاء بهدوء
  }

  let swPromise = Promise.resolve();
  if ('serviceWorker' in navigator && navigator.serviceWorker.getRegistrations) {
    swPromise = navigator.serviceWorker.getRegistrations()
      .then(regs => Promise.all(regs.map(r => r.unregister())))
      .catch(() => {}); // تجاهل الأخطاء بهدوء
  }

  
}

  root.querySelector('[data-action="redeem"]')?.addEventListener('click', onRedeem);

  async function onRedeem() {
    if (state.busy || !state.verifyData) {
      return toast('تحقّق من صحة الكود أولاً.', 'warning');
    }
    const code = normalizeCode(refs.voucherInput?.value);
    if (!code) return toast('الكود غير صالح.', 'warning');

    setBusy(true);
    try {
      const form = new FormData();
      form.append('code', code); // نرسل مع الشرطات كما هي
      const res = await noStoreFetch('/api/vouchers/redeem', {
        method: 'POST',
        body: form
      });

      if (res.status === 403) {
        toast('لا تملك صلاحية التفعيل (403).', 'warn');
        return;
      }
      if (!res.ok) {
        toast('تعذر إتمام العملية الآن.', 'error');
        return;
      }

      const json = await res.json();
      if (json.status === 'ok') {
        if (json.action_applied === 'switch_now') {
          toast('تمت الترقية فورًا إلى خطة أعلى.', 'success');
        } else if (json.action_applied === 'extend') {
          toast('تم تمديد اشتراكك الحالي.', 'success');
        } else if (['merge_queue','downgrade_queue'].includes(json.action_applied)) {
          toast('تمت جدولة الخطة لتبدأ بعد انتهاء الحالية.', 'info');
        } else {
          toast(`تم التفعيل بنجاح! انتهاء اشتراكك الجديد: ${json.new_subscription_expiry}`, 'success');
        }

        // === التعديل المطلوب: مسح شامل لكل أنواع الكاش بعد تفعيل الكود ===
        clearAllClientCachesViaSidebar();

        await refreshStateCard();
        closeModal();
      } else {
        toast('تعذّر التفعيل: ' + (json.reason || 'تعذر إتمام العملية.'), 'error');
      }
    } catch (e) {
      if (e?.name !== 'AbortError') console.error(e);
      toast('تعذر الاتصال. حاول لاحقًا.', 'error');
    } finally {
      setBusy(false);
    }
  }

  /* ---------- Boot (بدء الصفحة) ---------- */
  await loadState();  // جلب الحالة في كل دخول
  await loadPlans();  // ثم جلب الخطط في كل دخول

  // ✅ دعم ديب-لينك: من الراوتر أو مباشرة من الهاش
  try {
    const dl = window.__UPGRADE_DEEPLINK || readUpgradeFromHash() || null;
    if (dl) {
      try { delete window.__UPGRADE_DEEPLINK; } catch {}
      if (dl.open === 'activate') openModal(refs.modalActivate);
      if (dl.code && refs.voucherInput) refs.voucherInput.value = normalizeCode(dl.code);
      if (String(dl.autoverify) === '1' && typeof onVerify === 'function') {
        await onVerify();
      }
      // لا نعبث بالهاش هنا؛ الراوتر مسؤول عنه
    }
  } catch {}

  // اربط المستمعات العامة مرة واحدة فقط
  if (!_bound) {
    // مستمع ESC عام
    _onEsc = (e) => {
      if (e.key === 'Escape') {
        const anyOpen = (refs.modalSales && !refs.modalSales.hidden) || (refs.modalActivate && !refs.modalActivate.hidden);
        if (anyOpen) { e.preventDefault(); closeModal(); }
      }
    };
    document.addEventListener('keydown', _onEsc);

    _bound = true;
  }
}

/* تنظيف عند مغادرة الصفحة (يُستدعى من الراوتر قبل تحميل موديول آخر) */
export function cleanup() {
  // 1) ألغِ أي طلبات قيد التنفيذ
  if (_ctrl) { try { _ctrl.abort(); } catch {} _ctrl = null; }

  // 2) أزِل مستمع ESC العام
  if (_onEsc) { document.removeEventListener('keydown', _onEsc); _onEsc = null; }

  // 3) اسمح بإعادة ربط الأحداث عند العودة
  _bound = false;

  // 4) أعد الحالة الافتراضية لقفل تمرير الصفحة
  document.body.classList.remove('modal-open');
}
