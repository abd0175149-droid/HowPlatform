/* public/assets/js/components/service_request_modal.js */
/* خدمة طلب المساعدة - بارشل مشترك يصلح لكل الصفحات
   - يدعم الصور + الفيديوهات وملفات المكتب والضغط
   - حد أقصى للملف: 20MB
   - معاينة: صورة مصغّرة للصور، <video> للفيديو، وأيقونة للملفات الأخرى
   - تفويض أحداث عام ليعمل حتى مع التحميل الديناميكي
   - إصلاح فتح نافذة اختيار الملف مرتين via fileDialogGuard
   - واجهة: window.ServiceRequestModal { open, close, init, pickFiles }
*/

(function () {
  'use strict';

  // =============== ① الإعدادات العامة ===============
  const API_BASE = (window.API_BASE || '').replace(/\/+$/, '');
  const ENDPOINTS = {
    courses: (API_BASE || '') + '/student/courses/available',
    create:  (API_BASE || '') + '/student/service-requests/create',
  };

  // CSRF من الميتا (إن وُجد)
  function getCsrf() {
    const name  = document.querySelector('meta[name="csrf-token-name"]')?.content;
    const value = document.querySelector('meta[name="csrf-token-value"]')?.content;
    return (name && value) ? { name, value } : null;
  }

  // =============== ② الحالة الداخلية ===============
  let filesBucket = [];               // قائمة ملفات الطلب (File[])
  let wiredGlobalHandlers = false;    // إغلاق بالنقر خارج / ESC
  let coursesController = null;       // لإلغاء الفيتش السابق
  let lastCoursesReqId = 0;           // حارس للتزامن
  let delegatedWired = false;         // تفويض أحداث مرة واحدة
  let fileDialogGuard = false;        // 🔒 يمنع فتح نافذة الاختيار مرتين
  let previewUrls = [];               // لإدارة Object URLs وتحريرها

  // =============== ③ أدوات رسائل ===============
  const toast = {
    success: (msg, title) => (window.showSuccess ? window.showSuccess(msg, title) : alert(msg)),
    error:   (msg)        => (window.showError   ? window.showError(msg)   : alert(msg)),
    warn:    (msg)        => (window.showWarning ? window.showWarning(msg) : alert(msg)),
    info:    (msg)        => (window.showInfo    ? window.showInfo(msg)    : alert(msg)),
  };

  // =============== ④ دوال مساعدة ===============
  function getModal() { return document.getElementById('serviceRequestModal'); }
  function s(selector) { const m = getModal(); return m ? m.querySelector(selector) : null; }

  // حماية نصوص بسيطة
  const esc = (str) => {
    if (typeof str !== 'string') return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return str.replace(/[&<>"']/g, (ch) => map[ch]);
  };

  // تنسيق حجم الملف
  function fmtSize(bytes) {
    if (!bytes) return '0 Bytes';
    const k = 1024, sizes = ['Bytes','KB','MB','GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
  }

  // انتظار عنصر داخل المودال
  function waitForIn(root, selector, timeout = 2000) {
    return new Promise((resolve, reject) => {
      const t0 = performance.now();
      (function loop() {
        const el = root.querySelector(selector);
        if (el) return resolve(el);
        if (performance.now() - t0 >= timeout) return reject(new Error('timeout ' + selector));
        requestAnimationFrame(loop);
      })();
    });
  }

  // قبول بالامتداد أو MIME (يشمل الفيديو الآن)
  function isAllowedFile(file) {
    const name = (file?.name || '').toLowerCase();
    const ext  = name.includes('.') ? name.split('.').pop() : '';
    const mime = (file?.type || '').toLowerCase();

    const allowedExt = new Set([
      // صور
      'jpg','jpeg','png','gif','webp',
      // فيديو
      'mp4','webm','mov','avi','mkv','mpeg',
      // مستندات وأرشيف
      'pdf','doc','docx','xls','xlsx','ppt','pptx','zip','rar'
    ]);

    const allowedMime = new Set([
      // صور
      'image/jpeg','image/png','image/gif','image/webp',
      // فيديو
      'video/mp4','video/webm','video/quicktime','video/x-msvideo','video/x-matroska','video/mpeg',
      // مستندات
      'application/pdf',
      'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
      // أرشيف
      'application/zip','application/x-zip-compressed','application/x-rar-compressed',
      // fallback
      'application/octet-stream'
    ]);

    if (allowedExt.has(ext)) return true;
    if (allowedMime.has(mime)) return true;
    return false;
  }

  // أيقونة حديثة حسب الامتداد (Font Awesome)
  function getIconByExt(ext) {
    switch ((ext || '').toLowerCase()) {
      case 'pdf': return 'fa-file-pdf';
      case 'doc':
      case 'docx': return 'fa-file-word';
      case 'xls':
      case 'xlsx': return 'fa-file-excel';
      case 'ppt':
      case 'pptx': return 'fa-file-powerpoint';
      case 'zip':
      case 'rar': return 'fa-file-archive';
      case 'jpg':
      case 'jpeg':
      case 'png':
      case 'gif':
      case 'webp': return 'fa-file-image';
      case 'mp4':
      case 'webm':
      case 'mov':
      case 'avi':
      case 'mkv':
      case 'mpeg': return 'fa-file-video';
      default: return 'fa-file';
    }
  }

  // =============== ⑤ تحميل المساقات ===============
  async function loadAvailableCourses() {
    const modal = getModal();
    if (!modal) return;

    const select = s('#requestCourse') || await waitForIn(modal, '#requestCourse', 1500).catch(() => null);
    if (!select) return;

    if (coursesController) { try { coursesController.abort(); } catch (_) {} }
    coursesController = new AbortController();
    const currentReqId = ++lastCoursesReqId;

    try {
      const headers = { 'X-Requested-With': 'XMLHttpRequest', 'Cache-Control': 'no-store' };
      const csrf = getCsrf();
      if (csrf) headers[csrf.name] = csrf.value;

      const res = await fetch(ENDPOINTS.courses, { headers, signal: coursesController.signal, cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      if (currentReqId !== lastCoursesReqId) return;

      select.innerHTML = '<option value="">-- اختر المادة (اختياري) --</option>';
      if (data?.success && Array.isArray(data.courses)) {
        const frag = document.createDocumentFragment();
        for (const c of data.courses) {
          const opt = document.createElement('option');
          opt.value = String(c.id ?? '');
          opt.textContent = (c.code ? `[${c.code}] ` : '') + (c.name ?? '');
          frag.appendChild(opt);
        }
        select.appendChild(frag);
      }
    } catch (err) {
      if (err?.name !== 'AbortError') {
        console.error('Error loading courses:', err);
        toast.error('حدث خطأ في تحميل المساقات');
      }
    }
  }

  // =============== ⑥ إدارة الملفات ===============
  // 🔒 حارس يمنع فتح نافذة اختيار الملف مرتين حتى تُغلق
  function pickFiles() {
    const fi = s('#requestFiles');
    if (!fi) return;

    if (fileDialogGuard) return;        // منع الفتح المكرر
    fileDialogGuard = true;

    // إعادة ضبط الحارس عند عودة التركيز (إغلاق نافذة الاختيار)
    const onFocusBack = () => {
      fileDialogGuard = false;
      window.removeEventListener('focus', onFocusBack, true);
    };
    window.addEventListener('focus', onFocusBack, true);

    // فالسيف في حال لم يعد التركيز (بعض المتصفحات)
    setTimeout(() => { fileDialogGuard = false; }, 2000);

    fi.click();
  }

  // استبدال input[type=file] لإعادة ربط change
  function resetNativeFileInput() {
    const fileInput = s('#requestFiles');
    if (!fileInput) return;

    const clone = fileInput.cloneNode(true);
    fileInput.parentNode.replaceChild(clone, fileInput);

    const fresh = s('#requestFiles');
    if (fresh) {
      fresh.addEventListener('change', onFileInputChange);
    }
  }

  function onFileInputChange(e) {
    // أيًا كان الناتج (اختيار/إلغاء) نُتيح الفتح مرة أخرى
    fileDialogGuard = false;

    if (e?.target?.files?.length) handleFiles(e.target.files);
  }

  function handleFiles(fileList) {
    const maxSize = 20 * 1024 * 1024; // ✅ 20MB

    for (const f of fileList) {
      if (f.size > maxSize) { toast.warn(`الملف ${f.name} يتجاوز 20MB`); continue; }
      if (!isAllowedFile(f)) { toast.warn(`نوع الملف ${f.name} غير مدعوم`); continue; }
      const exists = filesBucket.some(x => x.name === f.name && x.size === f.size);
      if (exists) { toast.info(`الملف ${f.name} موجود مسبقًا`); continue; }
      filesBucket.push(f);
    }
    renderFiles();

    const fi = s('#requestFiles');
    if (fi) fi.value = ''; // السماح باختيار نفس الاسم لاحقًا
  }

  // تنظيف روابط المعاينة القديمة
  function revokePreviews() {
    try { previewUrls.forEach((u) => URL.revokeObjectURL(u)); } catch (_) {}
    previewUrls = [];
  }

  // بناء الكروت: صورة مصغّرة للصور، فيديو للفيديو، وأيقونة للملفات الأخرى
  function renderFiles() {
    const list = s('#uploadedFilesList');
    if (!list) return;

    if (!filesBucket.length) {
      revokePreviews();
      list.innerHTML = '';
      return;
    }

    revokePreviews();

    const cards = filesBucket.map((f, i) => {
      const name = f.name || '';
      const ext  = (name.includes('.') ? name.split('.').pop() : '').toLowerCase();
      const isImg   = f.type?.startsWith('image/') || ['jpg','jpeg','png','gif','webp'].includes(ext);
      const isVideo = f.type?.startsWith('video/') || ['mp4','webm','mov','avi','mkv','mpeg'].includes(ext);

      let thumbHtml = '';
      if (isImg) {
        const url = URL.createObjectURL(f);
        previewUrls.push(url);
        thumbHtml = `
          <div class="thumb img-thumb">
            <img src="${url}" alt="${esc(name)}" loading="lazy"/>
          </div>
        `;
      } else if (isVideo) {
        const url = URL.createObjectURL(f);
        previewUrls.push(url);
        thumbHtml = `
          <div class="thumb video-thumb">
            <video src="${url}" preload="metadata" controls playsinline></video>
          </div>
        `;
      } else {
        const icon = getIconByExt(ext);
        thumbHtml = `
          <div class="thumb icon-thumb">
            <i class="fas ${icon}"></i>
          </div>
        `;
      }

      return `
        <div class="file-card" data-index="${i}">
          ${thumbHtml}
          <div class="meta">
            <div class="name" title="${esc(name)}">${esc(name)}</div>
            <div class="sub">${fmtSize(f.size)} • ${ext ? '.'+ext : ''}</div>
          </div>
          <button type="button" class="remove-file" data-index="${i}" aria-label="Remove file">
            <i class="fas fa-times"></i>
          </button>
        </div>
      `;
    }).join('');

    list.classList.add('file-preview-container');
    list.innerHTML = cards;
  }

  // =============== ⑦ تفويض أحداث عالمي ===============
  function wireDelegatedHandlersOnce() {
    if (delegatedWired) return;
    delegatedWired = true;

    // change على input[type=file] داخل المودال
    document.addEventListener('change', (e) => {
      const modal = getModal();
      if (!modal || !modal.isConnected) return;
      const input = e.target;
      if (input && modal.contains(input) && input.id === 'requestFiles') {
        onFileInputChange(e);
      }
    });

    // click: placeholder يفتح منتقي الملفات + إزالة ملف
    document.addEventListener('click', (e) => {
      const modal = getModal();
      if (!modal || !modal.isConnected) return;

      const ph = e.target.closest('#serviceRequestModal .upload-placeholder');
      if (ph && modal.contains(ph)) {
        e.preventDefault();
        e.stopPropagation();
        pickFiles();
        return;
      }

      const rm = e.target.closest('#serviceRequestModal .remove-file');
      if (rm && modal.contains(rm)) {
        const idx = Number(rm.getAttribute('data-index'));
        if (!Number.isNaN(idx)) {
          filesBucket.splice(idx, 1);
          renderFiles();
        }
      }
    });

    // Drag & Drop داخل منطقة الرفع
    document.addEventListener('dragover', (e) => {
      const modal = getModal();
      if (!modal || !modal.isConnected) return;
      const dropArea = modal.querySelector('#fileUploadArea');
      if (!dropArea) return;

      if (dropArea === e.target || dropArea.contains(e.target)) {
        e.preventDefault();
        dropArea.classList.add('dragover');
      }
    }, { passive: false });

    document.addEventListener('dragleave', (e) => {
      const modal = getModal();
      if (!modal || !modal.isConnected) return;
      const dropArea = modal.querySelector('#fileUploadArea');
      if (!dropArea) return;

      if (dropArea === e.target || dropArea.contains(e.target)) {
        dropArea.classList.remove('dragover');
      }
    });

    document.addEventListener('drop', (e) => {
      const modal = getModal();
      if (!modal || !modal.isConnected) return;
      const dropArea = modal.querySelector('#fileUploadArea');
      if (!dropArea) return;

      if (dropArea === e.target || dropArea.contains(e.target)) {
        e.preventDefault();
        dropArea.classList.remove('dragover');
        if (e.dataTransfer?.files?.length) handleFiles(e.dataTransfer.files);
      }
    });
  }

  // =============== ⑧ إرسال النموذج ===============
  function wireSubmit() {
    const form = s('#serviceRequestForm');
    if (!form) return;

    const cloned = form.cloneNode(true);
    form.parentNode.replaceChild(cloned, form);

    const real = s('#serviceRequestForm');
    real.addEventListener('submit', onSubmit);
  }

  async function onSubmit(e) {
    e.preventDefault(); e.stopPropagation();

    const form = e.currentTarget;
    const btn  = form.querySelector('.btn-submit');

    const subject = s('#requestSubject')?.value?.trim() || '';
    const desc    = s('#requestDescription')?.value?.trim() || '';
    const course  = s('#requestCourse')?.value || '';

    if (!subject)                 { toast.warn('الرجاء إدخال موضوع الطلب'); return; }
    if (!desc || desc.length < 10){ toast.warn('تفاصيل الطلب قصيرة (10 أحرف على الأقل)'); return; }

    const originalHTML = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...'; }

    const fd = new FormData();
    fd.append('subject', subject);
    fd.append('description', desc);
    if (course) fd.append('course_offering_id', course);
    filesBucket.forEach(f => fd.append('files[]', f));

    try {
      const headers = { 'X-Requested-With': 'XMLHttpRequest' };
      const csrf = getCsrf();
      if (csrf) headers[csrf.name] = csrf.value;

      const res  = await fetch(ENDPOINTS.create, { method: 'POST', body: fd, headers });
      let data = null;
      try { data = await res.json(); } catch (_) { data = { success: false, message: 'استجابة غير متوقعة من الخادم' }; }

      if (res.ok && data?.success) {
        toast.success('تم إرسال طلبك بنجاح وسيتم الرد عليك قريبًا', 'تم الإرسال');
        close();
        setTimeout(() => {
          if (window.loadSection && typeof window.loadSection === 'function') {
            window.loadSection('service');
          }
        }, 600);
      } else {
        const msg = data?.message || `فشل في إرسال الطلب (HTTP ${res.status})`;
        toast.error(msg);
      }
    } catch (err) {
      console.error('Error submitting request:', err);
      toast.error('حدث خطأ أثناء إرسال الطلب. الرجاء المحاولة مرة أخرى.');
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = originalHTML; }
    }
  }

  // =============== ⑨ فتح/إغلاق المودال ===============
  async function open() {
    const modal = getModal();
    if (!modal) return;

    modal.classList.add('show');

    await waitForIn(modal, '#requestCourse', 1500).catch(() => {});
    await loadAvailableCourses();

    resetNativeFileInput();
    wireSubmit();
    wireGlobalsOnce();
  }

  function close() {
    const modal = getModal();
    if (!modal) return;

    if (coursesController) { try { coursesController.abort(); } catch (_) {} coursesController = null; }

    modal.classList.remove('show');

    const form = s('#serviceRequestForm');
    if (form) form.reset();
    const list = s('#uploadedFilesList');
    if (list) list.innerHTML = '';
    filesBucket = [];
    revokePreviews();
    fileDialogGuard = false; // إعادة ضبط الحارس
  }

  // =============== ⑩ مستمعون عامّون (مرّة واحدة) ===============
  function wireGlobalsOnce() {
    if (wiredGlobalHandlers) return;
    wiredGlobalHandlers = true;

    // إغلاق بالنقر خارج المحتوى
    document.addEventListener('click', (e) => {
      const modal = getModal();
      if (!modal) return;
      if (e.target === modal) close();
    });

    // Esc
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const modal = getModal();
        if (modal && modal.classList.contains('show')) close();
      }
    });
  }

  // =============== ⑪ init (يعمل دائمًا) ===============
  function init() {
    wireDelegatedHandlersOnce(); // عرض الملفات يعمل بغضّ النظر عن طريقة فتح المودال
  }

  // نفّذ init فور تحميل الملف
  init();

  // =============== ⑫ تصدير الواجهة ===============
  window.ServiceRequestModal = {
    open,      // فتح المودال
    close,     // إغلاق المودال
    init,      // اختياري
    pickFiles, // اختيار الملفات (استخدمه في الـ HTML إن رغبت)
  };
})();
