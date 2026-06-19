@extends('layouts.app')
@section('title','مولد باجات الطالب | HOW Tools')

@section('sidebar')
  @php
    use Illuminate\Support\Facades\DB;

    $authUser = session('auth_user');
    $currentRoute = request()->route() ? request()->route()->getName() : '';
    $currentClassId = (in_array($currentRoute, ['user.classes.students']) ? request()->route('id') : null);

    $myClasses = collect();
    if ($authUser && isset($authUser['id'])) {
      $uid = (int) $authUser['id'];
      // نجلب الصفوف + عدد الطلاب (LEFT JOIN + GROUP BY نظيف)
      $myClasses = DB::table('user_classes as c')
        ->leftJoin('user_class_students as s', 's.class_id', '=', 'c.id')
        ->select(
          'c.id','c.name','c.year','c.grade_level','c.is_active',
          DB::raw('COUNT(s.id) as students_count')
        )
        ->where('c.user_id', $uid)
        ->groupBy('c.id','c.name','c.year','c.grade_level','c.is_active')
        ->orderBy('c.created_at','desc')
        ->get();
    }
  @endphp

  {{-- رجوع لكل الأدوات --}}
  <div class="sidebar-section">
    <a href="{{ route('tools.index') }}" class="sidebar-link">
      <i class="fas fa-arrow-right"></i> العودة لجميع الأدوات
    </a>
  </div>

  {{-- أداة (إن أردت إبقاء هذا القسم) --}}
  <div class="sidebar-section">
    <h3 class="sidebar-title">الأداة</h3>
    <div class="sidebar-link active" style="cursor:default">
      <i class="fas fa-id-card"></i> مولِّد باجات الطالب
    </div>
  </div>

  {{-- إدارة الصفوف --}}
  <div class="sidebar-section">
    <h3 class="sidebar-title">إدارة الصفوف</h3>

    @if(!$authUser)
      <a href="{{ route('login', ['redirect' => url('/my/classes')]) }}" class="sidebar-link">
        <i class="fas fa-sign-in-alt"></i> تسجيل الدخول لإدارة صفوفي
      </a>
    @else
      {{-- زر الذهاب لواجهة إدارة الصفوف (إضافة/تعديل) --}}
      <a href="{{ route('user.classes.index') }}" class="sidebar-link {{ $currentRoute==='user.classes.index' ? 'active' : '' }}">
        <i class="fas fa-school"></i> واجهة إدارة الصفوف
      </a>

      @if($myClasses->isEmpty())
        <div class="no-results" style="padding: .75rem 1.5rem; color: var(--text-secondary);">
          لا يوجد لديك صفوف بعد.
        </div>
      @else
        <div class="sidebar-subtitle" style="padding: .5rem 1.5rem .25rem; font-size:.85rem; color:var(--text-secondary);">
          صفوفي
        </div>

        {{-- قائمة الصفوف --}}
        @foreach($myClasses as $cls)
          @php
            $isActive = (string)$currentClassId === (string)$cls->id; // إبراز الصف الحالي (صفحة الطلاب)
          @endphp
          <a href="{{ route('user.classes.students', $cls->id) }}"
             class="sidebar-link {{ $isActive ? 'active' : '' }}"
             aria-current="{{ $isActive ? 'page' : 'false' }}">
            <i class="fas fa-chalkboard"></i>
            <span style="flex:1">{{ $cls->name }}</span>

            {{-- شارة عدد الطلاب --}}
            <span title="عدد الطلاب" style="margin-inline-start:auto; display:inline-flex; align-items:center; gap:.35rem; font-size:.85rem; color:var(--text-secondary);">
              <i class="fas fa-user-graduate"></i> {{ (int) $cls->students_count }}
            </span>
          </a>
        @endforeach
      @endif
    @endif
  </div>


  {{-- الحساب --}}
  <div class="sidebar-section">
    <h3 class="sidebar-title">الحساب</h3>
    <a href="https://auth.howplatform.net/profile" class="sidebar-link"><i class="fas fa-user"></i> الملف الشخصي</a>
    <!--
    <a href="#" class="sidebar-link"><i class="fas fa-cog"></i> الإعدادات</a>
    <a href="#" class="sidebar-link"><i class="fas fa-question-circle"></i> المساعدة</a>-->
    
  </div>
@endsection

@section('content')
@php
  $years = $years ?? ['2025/2026','2026/2027'];
  $universities = $universities ?? [
    'jordan'    => ['name'=>'الجامعة الأردنية',   'logo'=>asset('assets/images/universities/jordan.png')],
    'yarmouk'   => ['name'=>'جامعة اليرموك',      'logo'=>asset('assets/images/universities/yarmouk.png')],
    'mutah'     => ['name'=>'جامعة مؤتة',         'logo'=>asset('assets/images/universities/mutah.png')],
    'hashemite' => ['name'=>'الجامعة الهاشمية',   'logo'=>asset('assets/images/universities/hashemite.png')],
  ];
@endphp

<div class="page-header">
  <h1 class="page-title">مولد باجات الطالب (الدبلوم العالي)</h1>
</div>

<div class="idcard-wrap">
  <aside class="idcard-controls card-like">
    <form id="uiForm" class="controls-grid" onsubmit="return false">
      <div class="form-row">
        <label for="year">السنة الأكاديمية</label>
        <select id="year" name="year">
          @foreach($years as $y)
            <option value="{{ $y }}" {{ $y==='2025/2026'?'selected':'' }}>{{ $y }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-row">
        <label for="university">الجامعة</label>
        <select id="university" name="university">
          @foreach($universities as $key => $u)
            <option value="{{ $key }}" data-logo="{{ $u['logo'] }}">{{ $u['name'] }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-row">
        <label for="studentName">الاسم</label>
        <input id="studentName" name="name" type="text" placeholder="أدخل الاسم" value="">
      </div>

      <div class="form-row">
        <label for="major">التخصص</label>
        <input id="major" name="major" type="text" placeholder="أدخل التخصص" value="">
      </div>

      <button id="btnDownloadPdf" type="button" class="btn btn-primary" style="margin-top:.25rem;width:100%">
        <i class="fas fa-file-pdf"></i> تحميل الباجة
      </button>
      <div class="hint">       لتخصصات الاجتماعيات: يكتب التخصص الدراسات الاجتماعية
ولتخصصات التربية الاسلامية : يكتب التخصص الدراسات الاسلامية ،لتخصصات العلوم : يكتب التخصص العلوم العامة</div>
    </form>

    {{-- نموذج الإرسال الفعلي (مخفي) --}}
    <form id="pdfForm" method="POST" action="{{ route('tools.idcard.stamp') }}" target="_blank" style="display:none">
      @csrf
      <input type="hidden" name="university">
      <input type="hidden" name="year">
      <input type="hidden" name="name">
      <input type="hidden" name="major">
    </form>

  </aside>

  <section class="idcard-preview">
    <div class="pdf-frame-wrap">
      <iframe id="pdfPreview" class="pdf-frame" src="" title="معاينة القالب"></iframe>
    </div>
  </section>
</div>
{{-- Rating Modal --}}
<div id="ratingModal" class="rate-modal" hidden aria-hidden="true">
  <div class="rate-overlay"></div>
  <div class="rate-dialog" role="dialog" aria-modal="true" aria-labelledby="rateTitle">
    <button type="button" class="rate-close" aria-label="إغلاق">&times;</button>

    <h3 id="rateTitle" class="rate-title">قيّم الأداة</h3>
    <p class="rate-sub">كيف تقيّم تجربتك مع "مولّد باجات الطالب"؟</p>

    <div class="stars" dir="ltr" aria-label="اختر تقييماً من 1 إلى 5">
      <button class="star" data-v="1" aria-label="1 star"><i class="fas fa-star"></i></button>
      <button class="star" data-v="2" aria-label="2 stars"><i class="fas fa-star"></i></button>
      <button class="star" data-v="3" aria-label="3 stars"><i class="fas fa-star"></i></button>
      <button class="star" data-v="4" aria-label="4 stars"><i class="fas fa-star"></i></button>
      <button class="star" data-v="5" aria-label="5 stars"><i class="fas fa-star"></i></button>
    </div>

    <div class="rate-actions">
      <button id="btnSubmitRating" class="btn btn-primary" disabled>إرسال التقييم</button>
      <button id="btnLater" class="btn btn-outline">لاحقاً</button>
    </div>

    <div id="rateMsg" class="rate-msg" aria-live="polite"></div>
  </div>
</div>
<style>
/* ===== Rating Modal ===== */
.rate-modal[hidden]{ display:none; }
.rate-modal{ position:fixed; inset:0; z-index:2000; }
.rate-overlay{
  position:absolute; inset:0; background:rgba(0,0,0,.45); backdrop-filter:blur(2px);
}
.rate-dialog{
  position:relative; z-index:1;
  width:min(520px, 92vw);
  margin:10vh auto; padding:1.25rem 1.25rem 1rem;
  background:#fff; border:1px solid var(--border); border-radius:16px; box-shadow:var(--shadow-lg);
}
.rate-close{
  position:absolute; top:.5rem; left:.75rem; width:36px; height:36px; border-radius:12px;
  border:1px solid var(--border); background:#fff; cursor:pointer; font-size:1.2rem; line-height:1;
}
.rate-title{ margin:0 0 .25rem; font-weight:900; font-size:1.25rem; color:var(--neutral-800); text-align:center; }
.rate-sub{ margin:0 0 .75rem; color:var(--text-secondary); text-align:center; }

.stars{ display:flex; justify-content:center; gap:.35rem; margin:.5rem 0 1rem; }
.star{
  width:48px; height:48px; display:grid; place-items:center;
  border-radius:12px; border:1px solid var(--border); background:#fff; cursor:pointer;
  transition: transform .12s ease, background .2s ease, border-color .2s ease;
}
.star i{ font-size:22px; color:#e2e8f0; transition: color .15s ease; }
.star:hover{ transform: translateY(-2px); border-color: var(--primary-300); }
.star.active i,
.star:hover i,
.star.hover i{ color: var(--primary-500); }

.rate-actions{ display:flex; justify-content:center; gap:.6rem; margin-top:.5rem; }
.rate-msg{ text-align:center; margin-top:.6rem; font-size:.95rem; color:var(--text-secondary); }

/* Buttons inherit مشروعك */
</style>
<script>
(function(){
  'use strict';

  // عناصر لازمة من سكربتك الحالي:
  const btnDownload = document.getElementById('btnDownloadPdf');

  // عناصر المودال:
  const modal   = document.getElementById('ratingModal');
  const closeX  = modal?.querySelector('.rate-close');
  const overlay = modal?.querySelector('.rate-overlay');
  const stars   = modal?.querySelectorAll('.star') || [];
  const btnSend = document.getElementById('btnSubmitRating');
  const btnLater= document.getElementById('btnLater');
  const msgBox  = document.getElementById('rateMsg');

  if (!modal || !btnDownload) return; // سلامة

  // إعدادات
  const RATED_COOKIE   = 'rated_tool_idcard';
  const DEFAULT_RATING = 5;

  // أدوات مساعدة للكوكي
  function setCookie(name, value, days){
    const d = new Date();
    d.setTime(d.getTime() + (days*24*60*60*1000));
    document.cookie = name + "=" + (value||"") + "; expires=" + d.toUTCString() + "; path=/; SameSite=Lax";
  }
  function getCookie(name){
    return document.cookie.split('; ').reduce((acc, c) => {
      const [k,v] = c.split('=');
      acc[k] = v; return acc;
    }, {})[name];
  }

  // لا تظهر المودال إذا كان الجهاز قيّم سابقًا
  function alreadyRated(){ return !!getCookie(RATED_COOKIE); }

  // حالة النجوم المختارة
  let currentRating = 0;

  function setActiveStars(n){
    currentRating = n;
    stars.forEach((b, i) => {
      if (i < n) b.classList.add('active'); else b.classList.remove('active');
    });
    btnSend.disabled = (n < 1 || n > 5);
  }

  // ابدأ بـ 5 نجوم (مرئيًا)
  document.addEventListener('DOMContentLoaded', function(){
    setActiveStars(DEFAULT_RATING);
  });

  // تفاعلات النجوم
  stars.forEach(btn => {
    btn.addEventListener('mouseenter', () => {
      const v = parseInt(btn.dataset.v, 10);
      stars.forEach((b, i) => { if (i < v) b.classList.add('hover'); else b.classList.remove('hover'); });
    });
    btn.addEventListener('mouseleave', () => { stars.forEach(b => b.classList.remove('hover')); });
    btn.addEventListener('click', () => { setActiveStars(parseInt(btn.dataset.v,10)); });
  });

  // منع فتح المودال أكثر من مرة بسرعة
  let lastOpenTs = 0;
  function openModal(){
    if (alreadyRated()) return; // لا تفتح إن كان قيّم
    const now = Date.now();
    if (now - lastOpenTs < 1200) return; // حارس بسيط
    lastOpenTs = now;

    setActiveStars(DEFAULT_RATING); // ← بدلاً من 0
    msgBox.textContent = '';
    modal.removeAttribute('hidden');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal(){
    modal.setAttribute('hidden','true');
    modal.setAttribute('aria-hidden','true');
  }

  closeX?.addEventListener('click', closeModal);
  overlay?.addEventListener('click', closeModal);
  btnLater?.addEventListener('click', (e)=>{ e.preventDefault(); closeModal(); });

  // إرسال التقييم
  btnSend?.addEventListener('click', async function(){
    if (currentRating < 1 || currentRating > 5) return;
    btnSend.disabled = true;
    msgBox.textContent = 'جاري حفظ التقييم...';

    try{
      const res = await fetch(@json(route('tools.idcard.rate')), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': @json(csrf_token()),
          'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ rating: currentRating })
      });

      // قد يرمي res.json() إن لم يكن JSON صالح؛ نحتويه بالـ try/catch العام
      const json = await res.json();

      if (!res.ok || !json.ok){
        throw new Error(json.msg || 'تعذّر حفظ التقييم');
      }

      // ضع كوكي حتى لا نطلبه مرة أخرى
      setCookie(RATED_COOKIE, '1', 365);

      msgBox.textContent = 'شكراً لتقييمك! تم الحفظ.';
      setTimeout(closeModal, 900);
    }catch(err){
      msgBox.textContent = 'حدث خطأ أثناء حفظ التقييم. حاول لاحقاً.';
      btnSend.disabled = false;
    }
  });

  // منطق الإظهار "بعد التحميل"
  btnDownload.addEventListener('click', function(){
    // تم فتح تبويب وتحميل PDF… بعد 1.5 ثانية نطلب التقييم إن لم يكن قيّم
    setTimeout(openModal, 1500);
  });

})();
</script>


<style>
  .idcard-wrap{display:grid;grid-template-columns:340px 1fr;gap:1.25rem}
  .card-like{
    background:var(--surface);border:1px solid var(--border);border-radius:16px;
    box-shadow:var(--shadow);padding:1rem
  }
  .controls-grid{display:grid;gap:.85rem}
  .form-row{display:flex;flex-direction:column;gap:.4rem}
  .form-row label{font-weight:800;color:var(--text-secondary);font-size:.95rem}
  .form-row input[type="text"], .form-row select{
    padding:.6rem .75rem;border:1px solid var(--border);border-radius:10px;
    background:var(--surface-weak);outline:none
  }
  .hint{font-size:.9rem;color:var(--text-secondary);margin-top:.5rem}

  .idcard-preview{display:flex;flex-direction:column;gap:.75rem;align-items:stretch}
  .pdf-frame-wrap{
    border:1px solid var(--border);
    border-radius:16px;
    overflow:hidden;
    background:#000;
    box-shadow:var(--shadow-md);
    min-height:560px;
  }
  .pdf-frame{
    width:100%;
    height:70vh;
    min-height:560px;
    border:0;
    background:#fff;
  }

  @media (max-width:1024px){ .idcard-wrap{grid-template-columns:1fr;gap:1rem} }
</style>

<script>
(function () {
  'use strict';

  // التسهيل على الاختيار
  const $  = (s, c = document) => c.querySelector(s);

  // عناصر الصفحة
  const uniSel   = $('#university');
  const yearSel  = $('#year');
  const nameInp  = $('#studentName');
  const majorInp = $('#major');
  const btn      = $('#btnDownloadPdf');
  const pdfForm  = $('#pdfForm');
  const preview  = $('#pdfPreview');

  // تأكّد من وجود كل العناصر قبل متابعة السكربت
  if (!uniSel || !yearSel || !nameInp || !majorInp || !btn || !pdfForm || !preview) return;

  // خريطة الجامعة -> رقم الصفحة (طابقها مع config/idcard_templates.php)
  const uniPage = {
    jordan:    1,
    yarmouk:   2,
    mutah:     3,
    hashemite: 4,
  };

  // معاينة صفحة واحدة فقط عبر الراوت المخصص للمعاينة
  const previewBase = @json(route('tools.idcard.preview')); // URL مطلق

  function updatePreview() {
    const uni  = uniSel.value;
    // نطلب نسخة PDF من صفحة واحدة فقط (الخادم يقتطع الصفحة)
    // ونختار تكبير مناسب داخل الـ iframe
    preview.src = previewBase + '?university=' + encodeURIComponent(uni) + '#zoom=page-fit';
  }

  // فعّل المعاينة عند التغيير وعند التحميل
  uniSel.addEventListener('change', updatePreview);
  updatePreview();

  // تفعيل/تعطيل الزر بحسب إدخال الاسم
  function validate() {
    btn.disabled = !nameInp.value.trim();
  }
  nameInp.addEventListener('input', validate);
  validate();

  // عند الضغط: جهّز الحقول المخفية وأرسل النموذج لختم/توليد PDF
  btn.addEventListener('click', function () {
    const u = uniSel.value;
    const y = yearSel.value;
    const n = (nameInp.value || '').trim();
    const m = (majorInp.value || '').trim();

    if (!n) {
      alert('يرجى إدخال اسم الطالب.');
      return;
    }

    // تعبئة الحقول المخفية بالنموذج
    pdfForm.university.value = u;
    pdfForm.year.value       = y;
    pdfForm.name.value       = n;
    pdfForm.major.value      = m;

    // (اختياري) إن كان الخادم يحتاج رقم الصفحة صراحةً:
    // if (pdfForm.page) pdfForm.page.value = uniPage[u] || 1;

    pdfForm.submit(); // يفتح تبويب/تنزيل PDF المختوم
  });
})();
</script>

@endsection
