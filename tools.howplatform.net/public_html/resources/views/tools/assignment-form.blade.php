@extends('layouts.app')
@section('title','نموذج المهمة | HOW Tools')

@section('sidebar')
  <div class="sidebar-section">
    <a href="{{ route('tools.index') }}" class="sidebar-link">
      <i class="fas fa-arrow-right"></i> العودة لجميع الأدوات
    </a>
  </div>

  <div class="sidebar-section">
    <h3 class="sidebar-title">الأداة</h3>
    <div class="sidebar-link active" style="cursor:default">
      <i class="fas fa-file-alt"></i> نموذج المهمة </div>
  </div>
@endsection

@section('content')
@php
  $universities = $universities ?? [
    'jordan'    => ['name'=>'الجامعة الأردنية',   'logo'=>asset('assets/images/universities/jordan.png')],
    'yarmouk'   => ['name'=>'جامعة اليرموك',      'logo'=>asset('assets/images/universities/yarmouk.png')],
    'mutah'     => ['name'=>'جامعة مؤتة',         'logo'=>asset('assets/images/universities/mutah.png')],
    'hashemite' => ['name'=>'الجامعة الهاشمية',   'logo'=>asset('assets/images/universities/hashemite.png')],
  ];
  $taskTypes   = $taskTypes   ?? ['مهمة قبلية','مهمة بعدية'];
  $courseNames = $courseNames ?? [
    'بيداغوجيا التعلم والتعليم - 1',
    'بيداغوجيا التعلم والتعليم - 2',
    'أساليب تدريس المبحث - 1',
    'أساليب تدريس المبحث - 2',
    'الخبرة المدرسية - 1',
    'الخبرة المدرسية - 2',
  ];
  $defaultRating = isset($defaultRating) ? (int)$defaultRating : 5;
@endphp

<div class="page-header">
  <h1 class="page-title">نموذج المهمة</h1>
</div>

<div class="tool-page">
  <section class="card-like">
    <form id="uiForm" class="form-grid" onsubmit="return false" autocomplete="off">
      <div class="grid-2">
        <div class="form-row">
          <label for="university">الجامعة</label>
          <select id="university" name="university" required>
            @foreach($universities as $key => $u)
              <option value="{{ $key }}" data-logo="{{ $u['logo'] }}">{{ $u['name'] }}</option>
            @endforeach
          </select>
        </div>

        <div class="form-row">
          <label for="student_name">اسم الطالب/المعلّم</label>
          <input id="student_name" name="student_name" type="text" placeholder="اكتب الاسم الثلاثي" required>
        </div>

        <div class="form-row">
          <label for="student_id">الرقم الجامعي</label>
          <input id="student_id" name="student_id" type="text" placeholder="مثال: 7250000" required>
        </div>

        <div class="form-row">
          <label for="specialty">التخصص</label>
          <input id="specialty" name="specialty" type="text" placeholder="مثال: الدراسات الاجتماعية" required>
        </div>

        <div class="form-row">
          <label for="course_teacher">اسم مدرس المادة</label>
          <input id="course_teacher" name="course_teacher" type="text" required>
        </div>

        <div class="form-row">
          <label for="academic_advisor">اسم المرشد الأكاديمي</label>
          <input id="academic_advisor" name="academic_advisor" type="text" required>
        </div>

        <div class="form-row">
          <label for="course_name">اسم المادة</label>
          <select id="course_name" name="course_name" required>
            <option value="">— اختر —</option>
            @foreach($courseNames as $c)
              <option value="{{ $c }}">{{ $c }}</option>
            @endforeach
          </select>
        </div>

        <div class="form-row">
          <label for="task_type">نوع المهمة</label>
          <select id="task_type" name="task_type" required>
            @foreach($taskTypes as $t) <option value="{{ $t }}">{{ $t }}</option> @endforeach
          </select>
        </div>

        <div class="form-row">
          <label for="date">اليوم والتاريخ</label>
          <input id="date" name="date" type="date" required>
          <div id="datePreview" class="hint" aria-live="polite"></div>
        </div>
      </div>

      {{-- محرر نص الإجابة --}}
      <div class="form-row">
        <label for="answer_editor">نصّ الإجابة</label>
        <textarea id="answer_editor"></textarea>
        <small class="hint">يدعم تنسيق النص، القوائم، الجداول، الصور، الروابط، العناوين… إلخ.</small>
      </div>

      <div class="actions">
        <button id="btnTogglePreview" type="button" class="btn btn-outline">
          <i class="fas fa-eye"></i> معاينة النموذج (إظهار/إخفاء)
        </button>
        <button id="btnDownloadPdf" type="button" class="btn btn-primary">
          <i class="fas fa-file-pdf"></i> تحميل PDF
        </button>
      </div>
    </form>

    {{-- POST الفعلي --}}
    <form id="pdfForm" method="POST" action="{{ route('tools.assignform.stamp') }}" target="_blank" style="display:none">
      @csrf
      <input type="hidden" name="university">
      <input type="hidden" name="student_name">
      <input type="hidden" name="student_id">
      <input type="hidden" name="specialty">
      <input type="hidden" name="course_teacher">
      <input type="hidden" name="academic_advisor">
      <input type="hidden" name="course_name">
      <input type="hidden" name="task_type">
      <input type="hidden" name="date">
      <input type="hidden" name="answer_html">
    </form>
  </section>

  {{-- المعاينة أسفل الصفحة --}}
  <section class="card-like preview-section" id="previewSection" hidden aria-hidden="true">
    <h3 class="section-title">معاينة النموذج</h3>
    <div class="pdf-frame-wrap">
      <iframe id="pdfPreview" class="pdf-frame" src="" title="معاينة القالب"></iframe>
    </div>
  </section>
</div>

{{-- === مودال التقييم === --}}
<div id="ratingModal" class="rate-modal" hidden aria-hidden="true">
  <div class="rate-overlay"></div>
  <div class="rate-dialog" role="dialog" aria-modal="true" aria-labelledby="rateTitle">
    <button type="button" class="rate-close" aria-label="إغلاق">&times;</button>

    <h3 id="rateTitle" class="rate-title">قيّم الأداة</h3>
    <p class="rate-sub">كيف تقيّم تجربتك مع "نموذج المهمة"؟</p>

    <div class="stars" dir="ltr" aria-label="اختر تقييماً من 1 إلى 5">
      @for($i=1;$i<=5;$i++)
        <button class="star" data-v="{{ $i }}" aria-label="{{ $i }} star"><i class="fas fa-star"></i></button>
      @endfor
    </div>

    <div class="rate-actions">
      <button id="btnSubmitRating" class="btn btn-primary" disabled>إرسال التقييم</button>
      <button id="btnLater" class="btn btn-outline">لاحقاً</button>
    </div>

    <div id="rateMsg" class="rate-msg" aria-live="polite"></div>
  </div>
</div>

<style>
  .tool-page{display:flex;flex-direction:column;gap:1rem}
  .card-like{
    background:var(--surface);border:1px solid var(--border);border-radius:16px;
    box-shadow:var(--shadow);padding:1rem
  }
  .form-grid{display:flex;flex-direction:column;gap:1rem}
  .grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
  .form-row{display:flex;flex-direction:column;gap:.4rem}
  .form-row label{font-weight:800;color:var(--text-secondary);font-size:.95rem}
  .form-row input[type="text"], .form-row input[type="date"], .form-row select{
    padding:.6rem .75rem;border:1px solid var(--border);border-radius:10px;background:var(--surface-weak);outline:none
  }
  .hint{font-size:.9rem;color:var(--text-secondary);margin-top:.35rem}

  .actions{display:flex;gap:.6rem;justify-content:flex-start}
  .actions .btn{min-width:180px}

  .preview-section[hidden]{display:none}
  .section-title{margin:.25rem 0 .75rem;font-size:1.05rem;font-weight:900;color:var(--neutral-800)}
  .pdf-frame-wrap{
    border:1px solid var(--border); border-radius:16px; overflow:hidden; background:#000;
    box-shadow:var(--shadow-md); min-height:420px;
  }
  .pdf-frame{width:100%; height:75vh; min-height:420px; border:0; background:#fff;}

  @media (max-width:900px){
    .grid-2{grid-template-columns:1fr}
    .actions{flex-direction:column}
    .actions .btn{width:100%}
  }

  /* ===== Rating Modal ===== */
  .rate-modal[hidden]{ display:none; }
  .rate-modal{ position:fixed; inset:0; z-index:2000; }
  .rate-overlay{ position:absolute; inset:0; background:rgba(0,0,0,.45); backdrop-filter:blur(2px); }
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
  .star.active i, .star:hover i, .star.hover i{ color: var(--primary-500); }
  .rate-actions{ display:flex; justify-content:center; gap:.6rem; margin-top:.5rem; }
  .rate-msg{ text-align:center; margin-top:.6rem; font-size:.95rem; color:var(--text-secondary); }
</style>

{{-- TinyMCE بمفتاحك (أو استخدم jsDelivr بدل هذا السطر لو أردت بدون API) --}}
<script src="https://cdn.tiny.cloud/1/hw9i625ncdv18e1tqeql9kygavozx4v1q0wtjvmdrxw9mik9/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<script>
(function(){
  'use strict';
  const $  = (s, c=document)=>c.querySelector(s);

  const uniSel    = $('#university');
  const nameInp   = $('#student_name');
  const idInp     = $('#student_id');
  const specInp   = $('#specialty');
  const ctInp     = $('#course_teacher');
  const advInp    = $('#academic_advisor');
  const courseSel = $('#course_name');
  const ttypeSel  = $('#task_type');
  const dateInp   = $('#date');
  const datePrev  = $('#datePreview');

  const btnToggle = $('#btnTogglePreview');
  const btnPdf    = $('#btnDownloadPdf');
  const pdfForm   = $('#pdfForm');
  const previewSec= $('#previewSection');
  const previewIF = $('#pdfPreview');

  // === عناصر المودال
  const modal   = $('#ratingModal');
  const closeX  = modal?.querySelector('.rate-close');
  const overlay = modal?.querySelector('.rate-overlay');
  const stars   = modal?.querySelectorAll('.star');
  const btnSend = $('#btnSubmitRating');
  const btnLater= $('#btnLater');
  const msgBox  = $('#rateMsg');

  const rateUrl = @json(route('tools.assignform.rate'));

  // كوكي لمنع التقييم المتكرر
  const RATED_COOKIE = 'rated_tool_assignform';

  function setCookie(name, value, days){
    const d = new Date(); d.setTime(d.getTime() + (days*24*60*60*1000));
    document.cookie = `${name}=${value||''}; expires=${d.toUTCString()}; path=/; SameSite=Lax`;
  }
  function getCookie(name){
    return document.cookie.split('; ').reduce((acc, c) => {
      const [k,v] = c.split('='); acc[k]=v; return acc;
    }, {})[name];
  }
  function alreadyRated(){ return !!getCookie(RATED_COOKIE); }

  // ===== اليوم + التاريخ بالعربية =====
  const daysAr = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
  function fmtDateForServer(iso){
    const d = new Date(iso+'T00:00:00'); if (isNaN(d)) return iso;
    const day = daysAr[d.getDay()];
    const dd  = String(d.getDate()).padStart(2,'0');
    const mm  = String(d.getMonth()+1).padStart(2,'0');
    const yy  = d.getFullYear();
    return `${day} ${dd}/${mm}/${yy}`;
  }
  function updateDatePreview(){
    const v = dateInp.value;
    datePrev.textContent = v ? ('سيتم إدراج: ' + fmtDateForServer(v)) : '';
  }
  dateInp.addEventListener('change', updateDatePreview);
  updateDatePreview();

  // ===== TinyMCE =====
  tinymce.init({
    selector: '#answer_editor',
    directionality: 'rtl',
    menubar: 'file edit view insert format table',
    plugins: 'lists table image link paste autoresize code',
    toolbar: [
      'undo redo | blocks | bold italic underline | alignright aligncenter alignleft alignjustify',
      '| bullist numlist outdent indent | table image link | removeformat | code'
    ].join(' '),
    images_upload_handler: (blobInfo) => new Promise((resolve) => {
      resolve('data:' + blobInfo.blob().type + ';base64,' + blobInfo.base64());
    }),
    paste_data_images: true,
    autoresize_bottom_margin: 20,
    min_height: 260,
    content_style: `
      body { font-family: "Amiri","Arial",sans-serif; font-size: 15px; line-height: 1.9; }
      table { border-collapse: collapse; width: 100%; }
      table, th, td { border: 1px solid #999; }
      th, td { padding: .45rem .55rem; }
      img { max-width: 100%; height: auto; }
      ul,ol { margin: 0 0 0 1.25rem; }
      h1,h2,h3,h4 { margin: .6rem 0 .35rem; }
      a { color: #0d6efd; text-decoration: underline; }
    `
  });

  // ===== المعاينة أسفل الصفحة =====
  const previewBase = @json(route('tools.assignform.preview'));
  function ensurePreviewSrc(){
    const uni = uniSel.value || 'jordan';
    const url = previewBase + '?university=' + encodeURIComponent(uni) + '#zoom=page-fit';
    if (previewIF && previewIF.src !== url) previewIF.src = url;
  }
  function togglePreview(){
    const isHidden = previewSec.hasAttribute('hidden');
    if (isHidden){
      ensurePreviewSrc();
      previewSec.removeAttribute('hidden');
      previewSec.setAttribute('aria-hidden','false');
      btnToggle.innerHTML = '<i class="fas fa-eye-slash"></i> إخفاء المعاينة';
      previewSec.scrollIntoView({behavior:'smooth', block:'start'});
    } else {
      previewSec.setAttribute('hidden','true');
      previewSec.setAttribute('aria-hidden','true');
      btnToggle.innerHTML = '<i class="fas fa-eye"></i> معاينة النموذج (إظهار/إخفاء)';
    }
  }
  btnToggle.addEventListener('click', togglePreview);
  uniSel.addEventListener('change', ()=>{ if (!previewSec.hasAttribute('hidden')) ensurePreviewSrc(); });

  // ===== تحقق الحقول =====
  function validate(){
    btnPdf.disabled = !(
      uniSel.value && nameInp.value.trim() && idInp.value.trim() &&
      specInp.value.trim() && ctInp.value.trim() && advInp.value.trim() &&
      courseSel.value && ttypeSel.value && dateInp.value
    );
  }
  [uniSel,nameInp,idInp,specInp,ctInp,advInp,courseSel,ttypeSel,dateInp].forEach(el=>el.addEventListener('input', validate));
  validate();

  // ===== مودال التقييم =====
  let currentRating = 0;
  function setActiveStars(n){
    currentRating = n;
    stars?.forEach((b, i) => { if (i < n) b.classList.add('active'); else b.classList.remove('active'); });
    btnSend.disabled = (n < 1 || n > 5);
  }
  // 5 نجوم افتراضياً
  document.addEventListener('DOMContentLoaded', () => {
    const def = Number(@json($defaultRating));
    setActiveStars(def && def>=1 && def<=5 ? def : 5);
  });
  stars?.forEach(btn => {
    btn.addEventListener('mouseenter', () => {
      const v = parseInt(btn.dataset.v, 10);
      stars.forEach((b, i) => { if (i < v) b.classList.add('hover'); else b.classList.remove('hover'); });
    });
    btn.addEventListener('mouseleave', () => { stars.forEach(b => b.classList.remove('hover')); });
    btn.addEventListener('click', () => { setActiveStars(parseInt(btn.dataset.v,10)); });
  });
  function openModal(){
    if (alreadyRated()) return;
    msgBox.textContent = '';
    modal.removeAttribute('hidden');
    modal.setAttribute('aria-hidden','false');
  }
  function closeModal(){
    modal.setAttribute('hidden','true');
    modal.setAttribute('aria-hidden','true');
  }
  closeX?.addEventListener('click', closeModal);
  overlay?.addEventListener('click', closeModal);
  btnLater?.addEventListener('click', (e)=>{ e.preventDefault(); closeModal(); });

  btnSend?.addEventListener('click', async function(){
    if (currentRating < 1 || currentRating > 5) return;
    btnSend.disabled = true;
    msgBox.textContent = 'جاري حفظ التقييم...';

    try{
      const res = await fetch(rateUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': @json(csrf_token()),
          'Accept': 'application/json',
        },
        body: JSON.stringify({ rating: currentRating })
      });
      const json = await res.json();
      if (!res.ok || !json.ok) throw new Error(json.msg || 'تعذّر حفظ التقييم');

      setCookie(RATED_COOKIE, '1', 365);
      msgBox.textContent = 'شكراً لتقييمك! تم الحفظ.';
      setTimeout(closeModal, 900);
    }catch(err){
      msgBox.textContent = 'حدث خطأ أثناء حفظ التقييم. حاول لاحقاً.';
      btnSend.disabled = false;
    }
  });

  // ===== تنزيل PDF + فتح مودال التقييم بعده =====
  btnPdf.addEventListener('click', function(){
    if (btnPdf.disabled) { alert('يرجى تعبئة جميع الحقول المطلوبة.'); return; }

    const editor = tinymce.get('answer_editor');
    const html   = editor ? editor.getContent() : '';

    pdfForm.university.value       = uniSel.value;
    pdfForm.student_name.value     = nameInp.value.trim();
    pdfForm.student_id.value       = idInp.value.trim();
    pdfForm.specialty.value        = specInp.value.trim();
    pdfForm.course_teacher.value   = ctInp.value.trim();
    pdfForm.academic_advisor.value = advInp.value.trim();
    pdfForm.course_name.value      = courseSel.value;
    pdfForm.task_type.value        = ttypeSel.value;
    pdfForm.date.value             = fmtDateForServer(dateInp.value);
    pdfForm.answer_html.value      = html;

    pdfForm.submit();

    // أظهر التقييم بعد قليل
    setTimeout(openModal, 1500);
  });

})();
</script>
@endsection
