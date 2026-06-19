<?php
/**
 * Upgrade Page (Students) — View only (HTML)
 * يعتمد على design tokens العامة ويستورد CSS خفيف خاص بالمكوّن.
 *
 * المتغيرات القادمة من الكنترولر:
 * $allowed (bool), $isPremium (bool), $premiumUntil (?string), $userName (string)
 */
?>

<?php
/**
 * Upgrade Page (Students) — View only (HTML)
 * يحافظ على نفس الكلاسات والمعرّفات، مع إعادة ترتيب هيكل العرض.
 *
 * المتغيرات من الكنترولر:
 * $allowed (bool), $isPremium (bool), $premiumUntil (?string), $userName (string)
 */
?>

<div class="upg-page" data-component="student-upgrade" dir="rtl">
    <!-- رأس الصفحة -->
    <div class="upg-head">
        <div class="upg-title">
            <i class="fas fa-crown"></i>
            <h2>الترقية والاشتراكات</h2>
            <span class="upg-badge">BETA</span>
        </div>

    </div>

    <?php if (!$allowed): ?>
        <!-- تنبيه تحت التطوير -->
        <div class="upg-alert upg-alert--warning">
            <div class="upg-alert__icon"><i class="fas fa-flask"></i></div>
            <div class="upg-alert__body">
                <div class="upg-alert__title">الميزة تحت التطوير</div>
                <div class="upg-alert__desc">هذه الميزة متاحة حاليًا لمجموعة محدودة من الطلاب. تابعنا قريبًا!</div>
            </div>
        </div>
    <?php else: ?>

        <!-- شبكة المحتوى العلوية: حالة الاشتراك + الخطة القادمة -->
        <div class="upg-grid">
            <!-- بطاقة: حالة الاشتراك (لا نغيّر data-ref حتى يملؤها الـJS) -->
            <section class="upg-card upg-current">
                <div class="upg-card__head">
                    <i class="fas fa-id-card-alt"></i>
                    <h3>حالة اشتراكك</h3>
                </div>
                <div class="upg-card__body" data-ref="stateBody">
                    <p class="upg-muted">جاري تحميل الحالة...</p>
                </div>
            </section>

            <!-- بطاقة: الخطة القادمة -->
            <section class="upg-card upg-upcoming">
                <div class="upg-card__head">
                    <i class="fas fa-clock"></i>
                    <h3>الخطة القادمة</h3>
                </div>
                <div class="upg-card__body" data-ref="upcomingBody">
                    <p class="upg-muted">لا توجد خطة قادمة.</p>
                </div>
            </section>
        </div>

        <!-- قسم الخطط -->
        <section class="upg-card upg-plans-card">
            <div class="upg-card__head">
                <i class="fas fa-list-ul"></i>
                <h3>الخطط المتاحة</h3>
            </div>
            <div class="upg-card__body">
                <!-- شبكة الخطط (JS) -->
                <div class="upg-plans" data-ref="plansGrid" aria-live="polite">
                    <!-- تُملأ عبر JS -->
                </div>

                <!-- شريط الأزرار (Sticky بعد اختيار خطة) -->
                   <div class="upg-actions" data-ref="actionsBar" hidden>
                  <a href="https://wa.me/962782550901?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%20%D8%A3%D8%B1%D9%8A%D8%AF%20%D9%85%D8%B9%D8%B1%D9%81%D8%A9%20%D9%83%D9%8A%D9%81%D9%8A%D8%A9%20%D8%A7%D9%84%D8%AD%D8%B5%D9%88%D9%84%20%D8%B9%D9%84%D9%89%20%D8%A7%D8%B4%D8%AA%D8%B1%D8%A7%D9%83%20%D9%81%D9%8A%20%D9%85%D9%86%D8%B5%D8%A9%20%D8%A7%D9%84%D8%AF%D8%A8%D9%84%D9%88%D9%85%20%D8%A7%D9%84%D8%B9%D8%A7%D9%84%D9%8A%D8%9F"
                     class="btn"
                     data-action="open-sales"
                     id="btn-get-card">
                    <i class="fas fa-store"></i> احصل على بطاقة الآن
                  </a>
                    <button type="button" class="btn btn-primary" data-action="open-activate">
                        <i class="fas fa-bolt"></i> فعِّل البطاقة
                    </button>
                </div>
            </div>
        </section>

        <!-- مودال: أماكن البيع (بدون تغيير على المعرّفات) -->
        <div class="upg-modal" data-modal="sales" role="dialog" aria-modal="true" aria-labelledby="salesTitle" hidden>
            <div class="upg-modal__dialog" tabindex="-1">
                <div class="upg-modal__head">
                    <h4 id="salesTitle"><i class="fas fa-store-alt"></i> أماكن شراء البطاقة</h4>
                    <button class="upg-modal__close" data-action="close-modal" aria-label="إغلاق">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="upg-modal__body">
                    <ul class="upg-sales-list" data-ref="salesList">
                        <li>عمّان - وسط البلد - مقابل ... (السبت - الخميس 10:00-18:00) <a href="#" target="_blank" rel="noopener">خرائط</a></li>
                        <li>إربد - شارع الجامعة - ... <a href="#" target="_blank" rel="noopener">خرائط</a></li>
                    </ul>
                    <div class="upg-note"><i class="fas fa-info-circle"></i> احتفظ بالإيصال والبطاقة حتى إتمام التفعيل.</div>
                </div>
                <div class="upg-modal__foot">
                    <button class="btn" data-action="close-modal">إغلاق</button>
                </div>
            </div>
        </div>

        <!-- مودال: تفعيل البطاقة (يحافظ على id="vcode" و data-ref) -->
        <div class="upg-modal" data-modal="activate" role="dialog" aria-modal="true" aria-labelledby="activateTitle" hidden>
            <div class="upg-modal__dialog" tabindex="-1">
                <div class="upg-modal__head">
                    <h4 id="activateTitle"><i class="fas fa-key"></i> تفعيل بطاقة الاشتراك</h4>
                    <button class="upg-modal__close" data-action="close-modal" aria-label="إغلاق">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="upg-modal__body">
                    <label class="upg-label" for="vcode">أدخل كود الفاوتشر</label>
                    <div class="upg-input-wrap">
                        <input id="vcode" type="text" class="upg-input" data-ref="voucherInput"
                               placeholder="ABCD-1234-XYZ" inputmode="latin" autocomplete="one-time-code" />
                        <button class="btn" data-action="paste" title="لصق"><i class="fas fa-paste"></i></button>
                    </div>
                    <div class="upg-form-actions">
                        <button class="btn btn-primary" data-action="verify"><i class="fas fa-shield-check"></i> تحقّق</button>
                        <button class="btn" data-action="close-modal">إلغاء</button>
                    </div>

                    <div class="upg-verify" data-ref="verifyResult" hidden></div>

                    <div class="upg-confirm" data-ref="confirmBar" hidden>
                        <button class="btn btn-primary" data-action="redeem"><i class="fas fa-check-circle"></i> تفعيل الآن</button>
                        <button class="btn" data-action="close-modal">تراجع</button>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<style>
  /* ================== Upgrade Page — Modern, Clean, RTL ================== */
/* ألوان/توكنز (يمكنها الاستفادة من :root العامة إن وُجدت) */
.upg-page{
  --radius:16px;
  --shadow-sm: 0 1px 3px rgba(0,0,0,.08);
  --shadow-md: 0 6px 18px rgba(0,0,0,.10);
  --shadow-lg: 0 16px 44px rgba(0,0,0,.16);
  --transition: .22s ease;

  /* لوحة مُحسَّنة */
  --bg: #f7f8fb;
  --surface: #ffffff;
  --border: #e8e8ef;

  --text: #0f172a;
  --muted: #6b7280;

  --primary-50:#ebf7ff;
  --primary-200:#bfe7ff;
  --primary-400:#55bfff;
  --primary-500:#1aa7ff;
  --primary-600:#198ee0;
  --primary-700:#1473b5;

  --accent-400:#ffc857;
  --ok:#10b981;
  --warn-bg:#fff7e6; --warn-br:#ffe0ad; --warn-ink:#a05a00;

  background: var(--bg);
  padding: 24px 16px;
  display: grid;
  gap: 18px;
}

/* رأس الصفحة */
.upg-head{
  background: linear-gradient(135deg, var(--primary-600), var(--primary-400));
  color: #fff;
  border-radius: var(--radius);
  box-shadow: var(--shadow-lg);
  padding: 22px 20px;
  display:flex; align-items:center; justify-content:space-between; gap:12px;
  position:relative; overflow:hidden;
}
.upg-head::before{
  content:""; position:absolute; inset:-60% auto auto -60%;
  width:200%; height:200%;
  background: radial-gradient(transparent 50%, rgba(255,255,255,.1)),
              repeating-linear-gradient(45deg, rgba(255,255,255,.06) 0 10px, transparent 10px 20px);
  animation: upgFloat 22s linear infinite;
}
@keyframes upgFloat{ from{transform:translate(0,0)} to{transform:translate(120px,120px)} }
.upg-title{ display:flex; align-items:center; gap:10px; position:relative; z-index:1; }
.upg-title h2{ margin:0; font-size:1.6rem; font-weight:800; }
.upg-badge{
  background: rgba(255,255,255,.18);
  border:1px solid rgba(255,255,255,.35);
  color:#fff; border-radius:999px; font-size:.72rem; padding:2px 8px; font-weight:800; backdrop-filter: blur(6px);
}
.upg-hello{ position:relative; z-index:1; opacity:.95; }

/* شبكة علوية: بطاقتان جنبًا لجنب */
.upg-grid{
  display:grid; gap:12px;
  grid-template-columns: repeat(2, minmax(0,1fr));
}
@media (max-width: 900px){
  .upg-grid{ grid-template-columns: 1fr; }
}

/* البطاقات */
.upg-card{
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
  transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
}
.upg-card:hover{ transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: var(--primary-200); }
.upg-card__head{
  display:flex; align-items:center; gap:8px;
  padding:14px 16px; border-bottom:1px solid var(--border);
}
.upg-card__head h3{ margin:0; font-size:1.12rem; font-weight:800; color:var(--text); }
.upg-card__head i{ color: var(--primary-600); }
.upg-card__body{ padding:16px; color:var(--text); }
.upg-muted{ color: var(--muted); }

/* بطاقة الخطط */
.upg-plans-card{ margin-top: 4px; }

/* شبكة بطاقات الخطط */
.upg-plans{
  display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:12px;
}
.upg-plan{
  background: var(--surface);
  border:1px solid var(--border);
  border-radius:12px; padding:14px;
  box-shadow: var(--shadow-sm);
  transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition);
  outline: none;
}
.upg-plan:hover{ transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--primary-200); }
.upg-plan:focus{ box-shadow: 0 0 0 3px rgba(26,167,255,.25); }
.upg-plan.is-selected{
  background: var(--primary-50);
  border-color: var(--primary-500);
  box-shadow: inset 0 0 0 1px var(--primary-200);
}
.upg-plan__head{ display:flex; align-items:baseline; justify-content:space-between; gap:8px; }
.upg-plan__title{ font-weight:800; }
.upg-plan__price{ color: var(--primary-700); display:flex; align-items:baseline; gap:6px; }
.upg-plan__amount{ font-size:1.28rem; font-weight:800; }
.upg-plan__meta{ color:var(--muted); font-weight:600; }
.upg-plan__features{ list-style:none; margin:8px 0 0; padding:0; display:grid; gap:6px; }
.upg-plan__features i{ color: var(--ok); margin-inline-start:4px; }

/* شريط الأزرار — لاصق بعد الاختيار */
.upg-actions{
  margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;
  position: sticky; bottom: 8px; /* يلتصق بأسفل البطاقة */
  background: linear-gradient(180deg, transparent, var(--surface) 38%);
  padding-top: 10px;
}

/* أزرار */
.btn{
  --_bg: #fff; --_bg-h: #f3f7ff; --_ink: var(--text);
  background: var(--_bg); color: var(--_ink); border:1px solid var(--border);
  padding:10px 14px; border-radius:12px; cursor:pointer;
  transition: background var(--transition), transform var(--transition), opacity var(--transition);
}
.btn:hover{ background: var(--_bg-h); transform: translateY(-1px); }
.btn:disabled{ opacity:.6; cursor:not-allowed; transform:none; }
.btn-primary{ --_bg: var(--primary-600); --_bg-h: var(--primary-700); --_ink:#fff; border-color: transparent; color:#fff; }

/* تنبيهات */
.upg-alert{
  display:grid; grid-template-columns:auto 1fr; gap:12px;
  padding:14px; border-radius:12px; border:1px solid var(--warn-br); background: var(--warn-bg);
  box-shadow: var(--shadow-sm);
}
.upg-alert__title{ font-weight:800; margin-bottom:4px; }
.upg-alert--warning .upg-alert__icon{ color: var(--warn-ink); }

/* مودالات (نظّفنا التكرار وتركّزنا على نسخة واحدة فقط) */
.upg-modal{
  position: fixed; inset: 0; display: none;
  background: rgba(0,0,0,.5); backdrop-filter: blur(2px);
  z-index: 9999; padding: 20px;
  place-items: center;
}
.upg-modal:not([hidden]){ display: grid; }
.upg-modal[hidden], .upg-modal[aria-hidden="true"]{
  display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important;
}
.upg-modal__dialog{
  width: min(720px, 96vw); max-height: 92vh;
  background: var(--surface); border:1px solid var(--border); border-radius:16px;
  box-shadow: var(--shadow-lg);
  display:flex; flex-direction:column; overflow:auto; outline:none;
  transform: scale(.985);
  transition: transform .18s ease, box-shadow .18s ease;
}
.upg-modal:not([hidden]) .upg-modal__dialog{ transform: scale(1); }
.upg-modal__head, .upg-modal__foot{
  display:flex; align-items:center; justify-content:space-between;
  padding:12px 14px; border-bottom:1px solid var(--border);
}
.upg-modal__foot{ border-bottom:0; border-top:1px solid var(--border); }
.upg-modal__head h4{ display:inline-flex; gap:8px; align-items:center; margin:0; }
.upg-modal__head i{ color: var(--primary-600); }
.upg-modal__close{ background:#fff; border:1px solid var(--border); border-radius:10px; padding:6px 10px; cursor:pointer; color: var(--muted); }
.upg-modal__close:hover{ background:#f5f7fb; }
.upg-modal__body{ padding:14px; display:grid; gap:12px; overflow:auto; }

/* حقول الإدخال */
.upg-label{ font-weight:800; color: var(--text); }
.upg-input-wrap{ display:grid; grid-template-columns: 1fr auto; gap:8px; align-items:center; }
.upg-input{ border:1px solid var(--border); background: #fff; color: var(--text); border-radius:10px; padding:10px 12px; }
.upg-form-actions{ display:flex; gap:8px; }

/* التحقق والتأكيد */
.upg-verify{ background: #eef7ff; border:1px solid #d5eaff; padding:10px; border-radius:10px; }
.upg-ok{ display:grid; gap:6px; }
.upg-ok span{ color: var(--muted); }
.upg-confirm{ display:flex; gap:8px; }
.upg-msg{ padding:10px; border-radius:8px; border:1px solid var(--border); background: #f5f6fb; }
.upg-msg--warn{ background: var(--warn-bg); border-color: var(--warn-br); }
.upg-msg--error{ background: #fee2e2; border-color: #fecaca; }

/* قائمة أماكن البيع */
.upg-sales-list{ display:grid; gap:8px; padding-inline-start:18px; }
.upg-note{ color: var(--muted); display:inline-flex; gap:6px; align-items:center; }

/* مظاهر تكيّفية */
@media (max-width:640px){
  .upg-head{ flex-direction:column; align-items:flex-start; gap:10px; }
  .upg-title h2{ font-size:1.34rem; }
  .upg-actions{ width:100%; justify-content:space-between; }
}

/* وضع الليلي (تكيف مع متغيرات عامة إن وُجدت) */
[data-theme="dark"] .upg-card,
[data-theme="dark"] .upg-modal__dialog{ background: #0b1220; border-color: #1f2a44; }
[data-theme="dark"] .upg-plan{ background:#0b1220; }
[data-theme="dark"] .upg-card:hover{ border-color: rgba(26,167,255,.35); }


/* ==== Upgrade Page - Brand Colors Override (keep new layout, old colors) ==== */
.upg-page{
  /* اربط بالخدمات/المتغيرات المعتمدة سابقًا في المنصة */
  --bg: var(--background, #f9f9f9);
  --surface: var(--surface-0, #ffffff);
  --border: var(--border-color, #e6e6e6);

  --text: var(--text-strong, #023047);
  --muted: var(--text-muted, #6b7280);

  /* لوحة الهوية القديمة (كما كانت): */
  --primary-50:#e8f5fb;
  --primary-200:#d2ebf7;   /* قريبة من 100/200 للاستخدامات الثانوية */
  --primary-400:#8ecae6;
  --primary-500:#219ebc;
  --primary-600:#1b81a0;
  --primary-700:#166b83;

  --accent-400:#ffbe34;    /* نفس لون الـ accent السابق */
  --ok:#10b981;
  --warn-bg:#fff7e6; 
  --warn-br:#ffe5b4; 
  --warn-ink:#d97706;
}

/* رأس الصفحة بنفس التدرّج السابق (primary-500 → primary-700) */
.upg-head{
  background: linear-gradient(135deg, var(--primary-500), var(--primary-700));
  color:#fff;
}

/* أيقونات رؤوس البطاقات والأزرار الأساسية تُستخدم من الهوية */
.upg-card__head i{ color: var(--primary-500); }
.btn-primary{
  --_bg: var(--primary-500);
  --_bg-h: var(--primary-600);
  --_ink:#fff;
  border-color: transparent;
  color:#fff;
}

/* تمييز الخطة المختارة بنفس الطيف */
.upg-plan.is-selected{
  background: var(--primary-100, #d2ebf7);
  border-color: var(--primary-500);
  box-shadow: inset 0 0 0 1px var(--primary-400);
}

/* تأثير التحويم متناسق مع الـprimary القديم */
.upg-card:hover,
.upg-plan:hover{
  border-color: var(--primary-400);
}

/* الوضع الليلي يعتمد أسطحك السابقة إن وُجدت */
[data-theme="dark"] .upg-card,
[data-theme="dark"] .upg-modal__dialog{ 
  background: var(--surface-2, #0b1220); 
  border-color: #1f2a44; 
}
[data-theme="dark"] .upg-plan{ background: var(--surface-2, #0b1220); }
[data-theme="dark"] .upg-card:hover{ border-color: rgba(33,158,188,.35); }


/* ====== Current Plan — Features Styling (brand colors kept) ====== */

/* صفّ معلومات الخطة الحالية */
.upg-current__row{
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px,1fr));
  gap: 10px;
  margin-bottom: 10px;
}
.upg-current__item{
  display: flex;
  gap: 6px;
  align-items: baseline;
  background: var(--surface-1, #f7f9fb);
  border: 1px solid var(--border-color, #e6e6e6);
  border-radius: 10px;
  padding: 8px 10px;
}
.upg-current__label{
  color: var(--text-muted, #6b7280);
  font-weight: 700;
}
.upg-current__value{
  color: var(--text-strong, #023047);
}

/* عنوان المزايا */
.upg-current__features-title{
  margin: 0 0 8px;
  font-size: 1rem;
  font-weight: 800;
  color: var(--text-strong, #023047);
}

/* شبكة الميزات: تتحول إلى chips مرتّبة */
.upg-features{
  --chip-bg: var(--primary-50, #e8f5fb);
  --chip-br: var(--primary-400, #8ecae6);
  --chip-ink: var(--text-strong, #023047);

  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px,1fr));
  gap: 8px;
  margin: 0; padding: 0; list-style: none;
}
.upg-features--empty{
  margin: 6px 0 0;
}

/* عنصر الميزة */
.upg-feature{
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  background: var(--chip-bg);
  border: 1px solid var(--chip-br);
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(0,0,0,.05);
  transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.upg-feature:hover{
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(0,0,0,.10);
  border-color: var(--primary-500, #219ebc);
}

/* أيقونة الصح */
.upg-feature__icon{
  display: inline-grid;
  place-items: center;
  width: 26px; height: 26px;
  border-radius: 999px;
  background: var(--primary-500, #219ebc);
  color: #fff;
  flex: 0 0 26px;
  box-shadow: 0 2px 6px rgba(33,158,188,.28);
}
.upg-feature__icon i{
  font-size: .9rem;
}

/* نص الميزة */
.upg-feature__text{
  color: var(--chip-ink);
  font-weight: 700;
  line-height: 1.35;
  /* قصّ أنيق إن طال السطرين */
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* الوضع الليلي */
[data-theme="dark"] .upg-current__item{
  background: var(--surface-2, #0b1220);
  border-color: #1f2a44;
}
[data-theme="dark"] .upg-feature{
  --chip-bg: rgba(33,158,188,.08);
  --chip-br: rgba(33,158,188,.35);
}
[data-theme="dark"] .upg-feature:hover{
  border-color: rgba(33,158,188,.75);
}
/* اجعل لون الصح داخل الدائرة أبيض مع خلفية الهوية الأزرق */
.upg-feature__icon{
  background: var(--primary-500);   /* الأزرق المعتمد */
  color: #fff;                       /* تأكيد الأبيض */
}
.upg-feature__icon i{
  color: #fff;                       /* يتغلب على أي قواعد سابقة */
}

/* عطّل تلوين الأخضر القديم داخل قائمة ميزات الخطط */
.upg-plan__features i{
  color: inherit;                    /* أو احذف القاعدة القديمة بالكامل */
}

/* إن أردت دقة أكثر بدون التأثير على أيقونات أخرى: */
.upg-plan__features .upg-feature__icon i{
  color: #fff;
}

</style>