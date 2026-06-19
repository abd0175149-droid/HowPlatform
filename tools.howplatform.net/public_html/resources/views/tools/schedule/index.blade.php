{{-- resources/views/tools/schedule/index.blade.php --}}
@extends('layouts.app')
@section('title','جدول الطالب | HOW Tools')

@section('sidebar')
  {{-- رجوع لكل الأدوات --}}
  <div class="sidebar-section">
    <a href="{{ route('tools.index') }}" class="sidebar-link">
      <i class="fas fa-arrow-right"></i> العودة لجميع الأدوات
    </a>
  </div>

  {{-- الأداة الحالية --}}
  <div class="sidebar-section">
    <h3 class="sidebar-title">الأداة</h3>
    <div class="sidebar-link active" style="cursor:default">
      <i class="fas fa-table"></i> جدول الطالب
    </div>
  </div>

  {{-- روابط مساعدة (اختياري) --}}
  <div class="sidebar-section">
    <h3 class="sidebar-title">خيارات</h3>
    <a href="{{ url('tools/schedule') }}" class="sidebar-link">
      <i class="fas fa-rotate"></i> إعادة التحميل
    </a>
    <a href="#" id="sidebarClear" class="sidebar-link">
      <i class="fas fa-broom"></i> تفريغ الاختيارات
    </a>
  </div>

  {{-- الحساب --}}
  <div class="sidebar-section">
    <h3 class="sidebar-title">الحساب</h3>
    <a href="https://auth.howplatform.net/profile" class="sidebar-link">
      <i class="fas fa-user"></i> الملف الشخصي
    </a>
  </div>
@endsection

@section('content')
<div class="page-header">
  <h1 class="page-title">جدول الطالب (طلبة الدبلوم)</h1>
</div>

<div class="idcard-wrap" style="gap:1rem">
  {{-- لوحة التحكّم بنفس أسلوب البطاقة --}}
  <aside class="idcard-controls card-like">
    <form id="uiForm" class="controls-grid" onsubmit="return false">
      <div class="form-row">
        <label for="secA">بيداغوجيا التعلم والتعليم – الشعبة</label>
        <select id="secA" data-course="بيداغوجيا التعلم والتعليم" disabled>
          <option value="">اختر…</option>
        </select>
      </div>

      <div class="form-row">
        <label for="secB">أساليب تدريس المبحث – الشعبة</label>
        <select id="secB" data-course="أساليب تدريس المبحث" disabled>
          <option value="">اختر…</option>
        </select>
      </div>

      <div class="form-row">
        <label for="secC">الخبرة المدرسية – الشعبة</label>
        <select id="secC" data-course="الخبرة المدرسية" disabled>
          <option value="">اختر…</option>
        </select>
      </div>

      <button id="btnDownloadPdf" type="button" class="btn btn-primary" style="margin-top:.25rem;width:100%" disabled>
        <i class="fas fa-file-pdf"></i> تنزيل جدول PDF
      </button>

      <button id="btnReset" type="button" class="btn" style="width:100%;margin-top:.35rem">
        <i class="fas fa-eraser"></i> تفريغ الاختيارات
      </button>
    </form>

    {{-- نموذج GET مخفي لاستدعاء PDF بنفس روح صفحة الباجات --}}
    <form id="pdfForm" method="GET" action="{{ route('tools.schedule.pdf') }}" target="_blank" style="display:none">
      <input type="hidden" name="secA">
      <input type="hidden" name="secB">
      <input type="hidden" name="secC">
    </form>
  </aside>

  {{-- المعاينة / الجدول --}}
  <section class="idcard-preview">
    <div class="card-like" style="padding:0">
      <div class="table-responsive" style="padding:1rem">
        <table class="table-like" id="summaryTbl" style="width:100%;border-collapse:collapse">
          <thead>
            <tr>
              <th>المادة</th>
              <th>رقم الشعبة</th>
              <th>الموعد</th>
              <th style="text-align:right">المدرّس</th>
              <th>القاعة</th>
              <th>أيّام الدوام</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="7" style="opacity:.75;text-align:center;padding:.8rem">
                اختر الشعب لإظهار الجدول.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      {{-- شريط أزرار سفلي (اختياري) --}}
      <div class="card-actions" style="display:flex;gap:.5rem;justify-content:flex-end;padding: .75rem 1rem;border-top:1px solid var(--border-color,#e5e7eb)">
        <button class="btn btn-primary" id="btnDownloadPdfBottom" disabled>
          <i class="fas fa-file-pdf"></i> تنزيل PDF
        </button>
        <button class="btn" id="btnResetBottom">
          <i class="fas fa-eraser"></i> تفريغ
        </button>
      </div>
    </div>
  </section>
</div>

  {{-- Rating Modal --}}
<div id="ratingModal" class="rate-modal" hidden aria-hidden="true">
  <div class="rate-overlay"></div>
  <div class="rate-dialog" role="dialog" aria-modal="true" aria-labelledby="rateTitle">
    <button type="button" class="rate-close" aria-label="إغلاق">&times;</button>

    <h3 id="rateTitle" class="rate-title">قيّم الأداة</h3>
    <p class="rate-sub">كيف تقيّم تجربتك مع "الجدول الدراسي"؟</p>

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

  const btnDownload = document.getElementById('btnDownloadPdf');
  const modal   = document.getElementById('ratingModal');
  const closeX  = modal?.querySelector('.rate-close');
  const overlay = modal?.querySelector('.rate-overlay');
  const stars   = modal?.querySelectorAll('.star');
  const btnSend = document.getElementById('btnSubmitRating');
  const btnLater= document.getElementById('btnLater');
  const msgBox  = document.getElementById('rateMsg');

  if (!modal || !btnDownload) return;

  function setCookie(name, value, days){
    const d = new Date(); d.setTime(d.getTime() + (days*24*60*60*1000));
    document.cookie = name + "=" + (value||"") + "; expires=" + d.toUTCString() + "; path=/; SameSite=Lax";
  }
  function getCookie(name){
    return document.cookie.split('; ').reduce((acc, c) => {
      const [k,v] = c.split('=');
      acc[k] = v; return acc;
    }, {})[name];
  }

  const RATED_COOKIE = 'rated_tool_schedule_generator';

  function alreadyRated(){ return !!getCookie(RATED_COOKIE); }

  let currentRating = 0;
  function setActiveStars(n){
    currentRating = n;
    stars.forEach((b, i) => { (i < n) ? b.classList.add('active') : b.classList.remove('active'); });
    btnSend.disabled = (n < 1 || n > 5);
  }

  stars?.forEach(btn => {
    btn.addEventListener('mouseenter', () => {
      const v = parseInt(btn.dataset.v, 10);
      stars.forEach((b, i) => { (i < v) ? b.classList.add('hover') : b.classList.remove('hover'); });
    });
    btn.addEventListener('mouseleave', () => { stars.forEach(b => b.classList.remove('hover')); });
    btn.addEventListener('click', () => { setActiveStars(parseInt(btn.dataset.v,10)); });
  });

  function openModal(){
    if (alreadyRated()) return;
    setActiveStars(5); // اختياري: ابدأ بـ 5 نجوم
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

  btnSend?.addEventListener('click', async function(){
    if (currentRating < 1 || currentRating > 5) return;
    btnSend.disabled = true;
    msgBox.textContent = 'جاري حفظ التقييم...';

    try{
      const res = await fetch(@json(route('tools.schedule.rate')), {
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

  // إظهار المودال بعد محاولة التنزيل بقليل
  btnDownload.addEventListener('click', function(){
    setTimeout(openModal, 1500);
  });
})();
</script>

@endsection


@push('styles')
<style>
  /* استخدام نفس روح الستايل العام للمنصة */
  .controls-grid{
    display:grid;
    grid-template-columns:1fr;
    gap:.75rem;
  }
  .form-row label{font-weight:700;display:block;margin-bottom:.35rem}
  .form-row select{
    width:100%;padding:.6rem .7rem;border-radius:10px;border:1px solid #d1d5db; /* tailwind gray-300 */
    background:#fff;color:#111827;outline:none
  }
  .table-like thead th{
    background:#EBEEF3;color:#141414;font-weight:800;text-align:center;
    padding:.6rem;border:1px solid #e5e7eb
  }
  .table-like tbody td{
    text-align:center;padding:.6rem;border:1px solid #e5e7eb
  }
  .table-like tbody td:nth-child(5){text-align:right}
  @media (max-width:860px){
    .table-like thead th:nth-child(7),
    .table-like tbody td:nth-child(7){display:none}
  }
</style>
@endpush

@push('scripts')
<script>
(function(){
  const selects = ['secA','secB','secC'].map(id=>document.getElementById(id));
  const btnsPdf = [document.getElementById('btnDownloadPdf'), document.getElementById('btnDownloadPdfBottom')];
  const btnsReset = [document.getElementById('btnReset'), document.getElementById('btnResetBottom')];
  const pdfForm = document.getElementById('pdfForm');

  // روابط من الشريط الجانبي
  const sidebarClear = document.getElementById('sidebarClear');
  if (sidebarClear) sidebarClear.addEventListener('click', (e)=>{ e.preventDefault(); resetSelections(); });

  // تحميل الشعب لكل مادة
  (async function init(){
    for (const sel of selects) {
      const course = sel.dataset.course;
      const url = `{{ url("tools/schedule/api/sections") }}?course=${encodeURIComponent(course)}`;
      try{
        const sections = await fetch(url).then(r=>r.json());
        sel.innerHTML = `<option value="">اختر…</option>` + sections.map(s=>`<option>${s}</option>`).join('');
        sel.disabled = false;
      }catch(e){
        sel.innerHTML = `<option value="">تعذّر التحميل</option>`;
        sel.disabled = true;
      }
      sel.addEventListener('change', refreshTable);
    }
  })();

  // توليد PDF
  for (const b of btnsPdf){
    b.addEventListener('click', ()=>{
      const a = document.getElementById('secA').value.trim();
      const b = document.getElementById('secB').value.trim();
      const c = document.getElementById('secC').value.trim();
      pdfForm.elements['secA'].value = a;
      pdfForm.elements['secB'].value = b;
      pdfForm.elements['secC'].value = c;
      pdfForm.submit();
    });
  }

  // تفريغ
  for (const b of btnsReset){ b.addEventListener('click', resetSelections); }

  function resetSelections(){
    for (const sel of selects){ sel.value=''; }
    renderRows([]);
  }

  async function refreshTable(){
    const picks = [];
    for (const sel of selects) {
      const course = sel.dataset.course;
      const section = sel.value.trim();
      if (!section) continue;
      const row = await fetch(`{{ url("tools/schedule/api/section") }}?course=${encodeURIComponent(course)}&section=${encodeURIComponent(section)}`).then(r=>r.json());
      if (row && row.course) picks.push(row);
    }
    renderRows(picks);
  }

  function renderRows(rows){
    const tb = document.querySelector('#summaryTbl tbody');
    if (!rows.length){
      tb.innerHTML = `<tr><td colspan="7" style="opacity:.75;text-align:center;padding:.8rem">اختر الشعب لإظهار الجدول.</td></tr>`;
      setPdfEnabled(false);
      return;
    }
    const trs = rows.map(r=>{
      const day = firstDay(r.days);
      return `<tr>
        <td>${esc(r.course)}</td>
        <td>${esc(String(r.section))}</td>
        <td>${esc(r.time)}</td>
        <td style="text-align:right">${esc(r.teacher)}</td>
        <td>${esc(r.room)}</td>
        <td>${esc(r.days)}</td>
      </tr>`;
    }).join('');
    tb.innerHTML = trs;
    setPdfEnabled(true);
  }

  function setPdfEnabled(on){
    for (const b of btnsPdf){ b.disabled = !on; }
  }

  function firstDay(daysStr){
    if(!daysStr) return '';
    const parts = daysStr.split(/[-–—,،]+/).map(s=>s.trim()).filter(Boolean);
    return parts[0] || '';
  }
  function esc(s){ return (s??'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[m])); }
})();
</script>
@endpush
