// /public/assets/js/students/sections/ai_bilingual_lesson_plan_modal.js
// Dedicated modal for bilingual_lesson_plan_v1

(function () {
  'use strict';

  // ─── Helpers ───────────────────────────────────────────────────────────────
  function getCsrfHeader() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? { 'X-CSRF-TOKEN': m.getAttribute('content') || '' } : {};
  }
  function urlParam(n) { return new URLSearchParams(location.search).get(n); }
  function showSwal(icon, title, text) {
    if (window.Swal) Swal.fire({ icon, title, text, confirmButtonText: 'حسناً', heightAuto: false });
    else alert(text);
  }
  function showLoading(msg) {
    if (window.Swal) Swal.fire({ title: msg || 'جاري الإرسال...', allowOutsideClick: false, heightAuto: false, didOpen: () => Swal.showLoading() });
  }
  function closeLoading() { try { if (window.Swal) Swal.close(); } catch (_) { } }

  // ─── State ─────────────────────────────────────────────────────────────────
  let root, openBtn, downloadBtn, statusBadge, backdrop, closeBtn, submitBtn, form;
  let _timer = null;

  function getTaskId() {
    return String(root?.dataset?.taskId || urlParam('task_id') || '0').trim();
  }
  function getTypeKey() { return 'bilingual_lesson_plan_v1'; }

  // ─── Status badge ──────────────────────────────────────────────────────────
  function setBadge(text, cls) {
    if (!statusBadge) return;
    statusBadge.textContent = text || '';
    statusBadge.style.display = text ? 'inline-flex' : 'none';
    statusBadge.className = 'ai-tool-status show ' + (cls || '');
  }
  function showDownload(url) {
    if (!downloadBtn || !url) return;
    downloadBtn.href = url;
    downloadBtn.style.display = 'inline-flex';
  }
  function hideDownload() {
    if (!downloadBtn) return;
    downloadBtn.style.display = 'none';
    downloadBtn.removeAttribute('href');
  }

  // ─── Modal open/close ──────────────────────────────────────────────────────
  function openModal() {
    if (!backdrop) return;
    backdrop.style.display = 'flex';
    backdrop.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  function closeModal() {
    if (!backdrop) return;
    backdrop.style.display = 'none';
    backdrop.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  // ─── Status check ──────────────────────────────────────────────────────────
  async function checkStatus(silent) {
    const taskId = getTaskId();
    if (!taskId || taskId === '0') return;
    try {
      const q = new URLSearchParams({ type_key: getTypeKey(), task_id: taskId });
      const res = await fetch(`/students/ai-tools/status?${q}`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json().catch(() => ({}));
      if (!json.ok) { setBadge('', ''); hideDownload(); return; }
      switch (json.state) {
        case 'done':
          setBadge('✅ جاهز للتنزيل', 'done');
          if (openBtn) openBtn.disabled = false;
          if (json.download_url) showDownload(json.download_url);
          stopTimer();
          break;
        case 'processing':
          setBadge('⏳ قيد المعالجة...', 'processing');
          if (openBtn) openBtn.disabled = true;
          hideDownload();
          startTimer();
          break;
        case 'pending':
          setBadge('🕐 في قائمة الانتظار', 'pending');
          if (openBtn) openBtn.disabled = true;
          hideDownload();
          startTimer();
          break;
        case 'failed':
          setBadge('❌ فشل', 'failed');
          if (openBtn) openBtn.disabled = false;
          hideDownload();
          stopTimer();
          break;
        default:
          setBadge('', '');
          if (openBtn) openBtn.disabled = false;
          hideDownload();
          stopTimer();
      }
    } catch (e) {
      if (!silent) console.error('[bilingual-modal] status error', e);
    }
  }

  function startTimer() { if (!_timer) _timer = setInterval(() => checkStatus(true), 20000); }
  function stopTimer() { if (_timer) { clearInterval(_timer); _timer = null; } }

  // ─── Collect chips ─────────────────────────────────────────────────────────
  function collectChips(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return [];
    return Array.from(container.querySelectorAll('.blp-chip.active'))
      .map(ch => ch.dataset.value || ch.textContent.trim());
  }

  // ─── Submit ────────────────────────────────────────────────────────────────
  async function submitForm(e) {
    e.preventDefault();

    // جمع القيم
    const language = document.getElementById('blp_language')?.value || 'ar';
    const subject = (document.getElementById('blp_subject')?.value || '').trim();
    const teacherGender = document.getElementById('blp_teacher_gender')?.value || '';
    const studentGender = document.getElementById('blp_student_gender')?.value || '';
    const gradeLevel = (document.getElementById('blp_grade_level')?.value || '').trim();
    const unitTitle = (document.getElementById('blp_unit_title')?.value || '').trim();
    const lessonTitle = (document.getElementById('blp_lesson_title')?.value || '').trim();
    const classesCount = (document.getElementById('blp_classes_count')?.value || '').trim();
    const previousLearning = (document.getElementById('blp_previous_learning')?.value || '').trim();
    const mainOutcomes = (document.getElementById('blp_main_outcomes')?.value || '').trim();
    const generalDescription = (document.getElementById('blp_general_description')?.value || '').trim();
    const teachingStrategies = collectChips('blp_teaching_chips');
    const evaluationStrategies = collectChips('blp_evaluation_chips');

    // التحقق من الحقول الإلزامية
    if (!subject) { showSwal('info', 'تنبيه', 'يرجى إدخال اسم المبحث / المادة الدراسية.'); return; }
    if (!gradeLevel) { showSwal('info', 'تنبيه', 'يرجى إدخال الصف الدراسي.'); return; }
    if (!unitTitle) { showSwal('info', 'تنبيه', 'يرجى إدخال عنوان الوحدة.'); return; }
    if (!lessonTitle) { showSwal('info', 'تنبيه', 'يرجى إدخال عنوان الدرس.'); return; }
    if (!mainOutcomes) { showSwal('info', 'تنبيه', 'يرجى إدخال النتاجات الرئيسية.'); return; }

    const payload = {
      type_key: getTypeKey(),
      task_id: Number(getTaskId()),
      language,
      subject,
      teacher_gender: teacherGender,
      student_gender: studentGender,
      grade_level: gradeLevel,
      unit_title: unitTitle,
      lesson_title: lessonTitle,
      classes_count: classesCount,
      previous_learning: previousLearning,
      main_outcomes: mainOutcomes,
      teaching_strategies: teachingStrategies,
      evaluation_strategies: evaluationStrategies,
      general_description: generalDescription,
    };

    console.log('[bilingual-modal] payload:', JSON.stringify(payload, null, 2));

    try {
      showLoading('جاري إرسال الطلب...');
      const res = await fetch('/students/ai-tools/request', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json; charset=utf-8',
          'Accept': 'application/json',
          ...getCsrfHeader()
        },
        body: JSON.stringify(payload),
      });
      closeLoading();
      const json = await res.json().catch(() => ({}));

      if (res.status === 200 && json.ok && json.status === 'accepted') {
        closeModal();
        setBadge('🕐 في قائمة الانتظار', 'pending');
        if (openBtn) openBtn.disabled = true;
        startTimer();
        showSwal('success', 'تم الإرسال', 'تم استلام الطلب وسيتم إنشاء الخطة قريباً.');
        return;
      }

      if (res.status === 409 && json.status === 'already_active') {
        closeModal();
        showSwal('info', 'طلب نشط', 'لديك طلب قيد المعالجة، يرجى الانتظار.');
        checkStatus(true);
        return;
      }

      if (res.status === 200 && json.status === 'already_done' && json.download_url) {
        closeModal();
        showDownload(json.download_url);
        setBadge('✅ جاهز للتنزيل', 'done');
        showSwal('info', 'جاهز', 'الملف منشأ مسبقاً، يمكنك تنزيله الآن.');
        return;
      }

      showSwal('error', 'خطأ', json.message || 'تعذّر تنفيذ الطلب. حاول لاحقاً.');
    } catch (err) {
      closeLoading();
      console.error('[bilingual-modal] submit error', err);
      showSwal('error', 'خطأ', 'حدث خطأ في الاتصال بالخادم.');
    }
  }

  // ─── Chips builder ─────────────────────────────────────────────────────────
  const TEACHING_OPTIONS = [
    'المناقشة والحوار', 'التعلم التعاوني', 'الاستقصاء', 'حل المشكلات',
    'العصف الذهني', 'التعلم بالمشروع', 'لعب الأدوار', 'التعلم الذاتي',
    'القصة والسرد', 'التدريس المباشر', 'الخرائط المعرفية', 'التعلم القائم على الأدلة'
  ];
  const EVALUATION_OPTIONS = [
    'الملاحظة', 'الأسئلة الشفهية', 'الاختبار القصير', 'بطاقة الخروج',
    'ورقة العمل', 'التقييم الذاتي', 'تقييم الأقران', 'المشروع',
    'العرض التقديمي', 'المهمة الأدائية', 'السجل القصصي', 'ملف الإنجاز'
  ];
  const MAX_CHIPS = 3;

  function buildChips(containerId, options) {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = '';
    options.forEach(opt => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'blp-chip';
      btn.textContent = opt;
      btn.dataset.value = opt;
      btn.addEventListener('click', () => {
        const active = container.querySelectorAll('.blp-chip.active').length;
        if (btn.classList.contains('active')) {
          btn.classList.remove('active');
        } else if (active >= MAX_CHIPS) {
          btn.classList.add('deny');
          setTimeout(() => btn.classList.remove('deny'), 300);
          if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: `الحد الأقصى ${MAX_CHIPS} اختيارات`, showConfirmButton: false, timer: 1800 });
        } else {
          btn.classList.add('active');
        }
      });
      container.appendChild(btn);
    });
  }

  // ─── Build Modal HTML ──────────────────────────────────────────────────────
  function buildModalHTML() {
    return `
<div id="blpBackdrop" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:16px;" aria-hidden="true">
  <div style="background:var(--surface-0,#fff);color:var(--text,#023047);border-radius:16px;width:min(860px,96vw);max-height:90vh;overflow:auto;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,.22);border:1px solid var(--border-color,#e1e5e9);">
    
    <!-- Header -->
    <div style="padding:16px 20px;border-bottom:1px solid var(--border-color,#e1e5e9);display:flex;align-items:center;justify-content:space-between;background:var(--primary-900,#023047);border-radius:16px 16px 0 0;">
      <h2 style="margin:0;font-size:1.05rem;font-weight:700;color:#fff;display:flex;align-items:center;gap:8px;">
        <i class="fa-solid fa-language"></i> أداة التخطيط ثنائية اللغة
      </h2>
      <button id="blpCloseBtn" type="button" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:8px;color:#fff;padding:6px 12px;cursor:pointer;font-size:.9rem;">✕</button>
    </div>

    <!-- Form -->
    <form id="blpForm" style="padding:20px;display:flex;flex-direction:column;gap:18px;">

      <!-- Row: Language -->
      <div class="blp-grid-2">
        <div class="blp-field">
          <label class="blp-label">لغة الخطة <span class="blp-req">*</span></label>
          <select id="blp_language" class="blp-input">
            <option value="ar">🇯🇴 العربية (Arabic)</option>
            <option value="en">🇬🇧 الإنجليزية (English)</option>
          </select>
        </div>
        <div class="blp-field">
          <label class="blp-label">المبحث / المادة الدراسية <span class="blp-req">*</span></label>
          <input id="blp_subject" class="blp-input" type="text" placeholder="مثال: الرياضيات، العلوم، اللغة العربية...">
        </div>
      </div>

      <!-- Row: Gender -->
      <div class="blp-grid-2">
        <div class="blp-field">
          <label class="blp-label">جنس المعلم <span class="blp-req">*</span></label>
          <select id="blp_teacher_gender" class="blp-input">
            <option value="ذكر">ذكر (Male)</option>
            <option value="أنثى">أنثى (Female)</option>
          </select>
        </div>
        <div class="blp-field">
          <label class="blp-label">جنس الطلبة <span class="blp-req">*</span></label>
          <select id="blp_student_gender" class="blp-input">
            <option value="ذكور">ذكور (Male Students)</option>
            <option value="إناث">إناث (Female Students)</option>
            <option value="مختلط">مختلط (Mixed)</option>
          </select>
        </div>
      </div>

      <!-- Row: Grade + Classes -->
      <div class="blp-grid-2">
        <div class="blp-field">
          <label class="blp-label">الصف الدراسي <span class="blp-req">*</span></label>
          <input id="blp_grade_level" class="blp-input" type="text" placeholder="مثال: الصف العاشر، Grade 10">
        </div>
        <div class="blp-field">
          <label class="blp-label">عدد الحصص</label>
          <input id="blp_classes_count" class="blp-input" type="number" min="1" max="10" placeholder="مثال: 2">
        </div>
      </div>

      <!-- Row: Unit + Lesson -->
      <div class="blp-grid-2">
        <div class="blp-field">
          <label class="blp-label">عنوان الوحدة <span class="blp-req">*</span></label>
          <input id="blp_unit_title" class="blp-input" type="text" placeholder="مثال: الأعداد الصحيحة">
        </div>
        <div class="blp-field">
          <label class="blp-label">عنوان الدرس <span class="blp-req">*</span></label>
          <input id="blp_lesson_title" class="blp-input" type="text" placeholder="مثال: الضرب في الأعداد السالبة">
        </div>
      </div>

      <!-- Previous Learning -->
      <div class="blp-field">
        <label class="blp-label">التعلم القبلي</label>
        <textarea id="blp_previous_learning" class="blp-input" rows="2" placeholder="ما يعرفه الطلبة مسبقاً من معلومات ومفاهيم ترتبط بهذا الدرس..."></textarea>
      </div>

      <!-- Main Outcomes -->
      <div class="blp-field">
        <label class="blp-label">النتاجات الرئيسية للدرس <span class="blp-req">*</span></label>
        <textarea id="blp_main_outcomes" class="blp-input" rows="3" placeholder="اذكر النتاجات التعليمية المراد تحقيقها في هذا الدرس..."></textarea>
      </div>

      <!-- Teaching Strategies chips -->
      <div class="blp-field">
        <label class="blp-label">استراتيجيات التدريس <small style="font-weight:400;color:var(--text-muted,#666)">(اختر حتى ${MAX_CHIPS})</small></label>
        <div id="blp_teaching_chips" class="blp-chips-wrap"></div>
      </div>

      <!-- Evaluation Strategies chips -->
      <div class="blp-field">
        <label class="blp-label">استراتيجيات التقويم <small style="font-weight:400;color:var(--text-muted,#666)">(اختر حتى ${MAX_CHIPS})</small></label>
        <div id="blp_evaluation_chips" class="blp-chips-wrap"></div>
      </div>

      <!-- General Description (optional) -->
      <div class="blp-field">
        <label class="blp-label">وصف عام للدرس <small style="font-weight:400;color:var(--text-muted,#666)">(اختياري)</small></label>
        <textarea id="blp_general_description" class="blp-input" rows="2" placeholder="أي ملاحظات إضافية أو سياق للدرس..."></textarea>
      </div>

    </form>

    <!-- Footer -->
    <div style="padding:14px 20px;border-top:1px solid var(--border-color,#e1e5e9);display:flex;gap:10px;background:var(--surface-0,#fff);border-radius:0 0 16px 16px;">
      <button id="blpSubmitBtn" type="button" class="btn btn-primary" style="min-width:140px;">
        <i class="fa-solid fa-wand-magic-sparkles"></i> توليد الخطة
      </button>
      <button id="blpCancelBtn" type="button" class="btn btn-secondary">إلغاء</button>
    </div>
  </div>
</div>

<style>
.blp-grid-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;}
.blp-field{display:flex;flex-direction:column;gap:6px;}
.blp-label{font-weight:600;font-size:.92rem;color:var(--text-strong,#023047);}
.blp-req{color:#e53935;}
.blp-input{border:1px solid var(--border-color,#dcdfe4);border-radius:10px;padding:9px 12px;font-size:.93rem;background:var(--surface-0,#fff);color:var(--text,#111);font-family:inherit;width:100%;box-sizing:border-box;transition:border-color .2s;}
.blp-input:focus{outline:none;border-color:var(--primary-500,#219ebc);box-shadow:0 0 0 3px rgba(33,158,188,.15);}
textarea.blp-input{resize:vertical;}
.blp-chips-wrap{display:flex;flex-wrap:wrap;gap:7px;}
.blp-chip{border:1px solid var(--border-color,#dfe7f3);border-radius:20px;padding:6px 12px;cursor:pointer;background:var(--surface-1,#f8fafc);color:var(--text,#023047);font-weight:600;font-size:.87rem;transition:.2s;line-height:1.4;}
.blp-chip.active{background:var(--primary-500,#219ebc);color:#fff;border-color:var(--primary-500,#219ebc);}
.blp-chip.deny{animation:blpDeny .25s;}
@keyframes blpDeny{0%,100%{transform:translateX(0);}25%{transform:translateX(-4px);}75%{transform:translateX(4px));}
</style>
`;
  }

  // ─── Mount ─────────────────────────────────────────────────────────────────
  function mount() {
    // إيجاد جذر الأداة
    root = document.querySelector('[data-tool-type="bilingual_lesson_plan_v1"]') ||
           document.getElementById('aiBilingualLessonPlanRoot');
    if (!root) return;

    openBtn = root.querySelector('[data-action="open-modal"]') ||
              root.querySelector('.btn-open-tool') ||
              root.querySelector('button');

    downloadBtn = root.querySelector('[data-action="download"]') ||
                  root.querySelector('.btn-download');

    statusBadge = root.querySelector('.ai-tool-status') ||
                  root.querySelector('[data-role="status"]');

    // حقن HTML المودال
    const wrapper = document.createElement('div');
    wrapper.innerHTML = buildModalHTML();
    document.body.appendChild(wrapper);

    backdrop = document.getElementById('blpBackdrop');
    form = document.getElementById('blpForm');
    submitBtn = document.getElementById('blpSubmitBtn');
    closeBtn = document.getElementById('blpCloseBtn');
    const cancelBtn = document.getElementById('blpCancelBtn');

    // بناء الشيبس
    buildChips('blp_teaching_chips', TEACHING_OPTIONS);
    buildChips('blp_evaluation_chips', EVALUATION_OPTIONS);

    // الأحداث
    openBtn?.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
    submitBtn?.addEventListener('click', submitForm);
    form?.addEventListener('submit', submitForm);

    // فحص الحالة عند التحميل
    checkStatus(true);
    startTimer();
  }

  // ─── Init ──────────────────────────────────────────────────────────────────
  function init() {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', mount);
    } else {
      mount();
    }
  }

  init();

  // تصدير للاستخدام الخارجي
  window.AI_BILINGUAL_LESSON_PLAN = { openModal, closeModal };

})();
