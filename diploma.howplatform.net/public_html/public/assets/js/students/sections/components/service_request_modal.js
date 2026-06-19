// public/assets/js/students/sections/components/service_request_modal.js
// مودال إنشاء طلب خدمة متخصصة — نسخة مُحسّنة ضد تعارضات الراوتر + Hoist + تفويض أحداث + لوج
(function (w, d) {
  'use strict';

  /* ================== ثابت IDs وتكوين عام ================== */
  const modalId        = 'serviceRequestModal';
  const formId         = 'serviceRequestForm';
  const inputSubjectId = 'requestSubject';
  const inputCourseId  = 'requestCourse';
  const inputDescId    = 'requestDescription';
  const inputFilesId   = 'requestFiles';
  const filesListId    = 'uploadedFilesList';
  const newBtnId       = 'svcNewRequestBtn';

  const MAX_FILE_MB    = 10;
  const MAX_FILES      = 20;

  let filesToUpload = [];
  let submitting    = false;

  /* ================== أدوات مساعدة ================== */
  const LOG = {
    i: (...a)=> console.log('[SR Modal]', ...a),
    w: (...a)=> console.warn('[SR Modal]', ...a),
    e: (...a)=> console.error('[SR Modal]', ...a),
    g: (m, o)=> { console.group(`[SR Modal] ${m}`); if (o !== undefined) console.log(o); },
    ge: ()=> console.groupEnd()
  };

  function getCsrfPair(){
    const nameEl = d.getElementById('__csrf_name');
    const hashEl = d.getElementById('__csrf_hash');
    return { name: nameEl?.value || '', value: hashEl?.value || '' };
  }
  function applyCsrf(fd){
    const {name, value} = getCsrfPair();
    if (name && value) fd.append(name, value);
    return fd;
  }
  function updateCsrfFromResponse(j){
    // لو السيرفر يعيد زوج CSRF جديد، حدّث الحقول المخفية في اللاياوت
    try {
      const n = j?.csrf?.name, v = j?.csrf?.hash || j?.csrf?.value;
      if (n && v) {
        const nameEl = d.getElementById('__csrf_name');
        const hashEl = d.getElementById('__csrf_hash');
        if (nameEl) nameEl.value = n;
        if (hashEl) hashEl.value = v;
        LOG.i('CSRF updated from response');
      }
    } catch(_){}
  }

  // ⚠️ مهم: كل عمليات الـ query لازم تكون داخل المودال حتى ما نصطدم بعناصر أخرى تحمل نفس الـ IDs
  function getModalEl(){ return d.getElementById(modalId); }
  function qsm(sel){ const m = getModalEl(); return m ? m.querySelector(sel) : null; }
  function qsma(sel){ const m = getModalEl(); return m ? Array.from(m.querySelectorAll(sel)) : []; }

  function escapeHtml(s){ return s==null ? '' : String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
  function formatBytes(bytes){
    if (!+bytes) return '0 Bytes';
    const k=1024, sizes=['Bytes','KB','MB','GB','TB'];
    const i=Math.floor(Math.log(bytes)/Math.log(k));
    return (bytes/Math.pow(k,i)).toFixed(2)+' '+sizes[i];
  }
  function iconByExt(ext){
    if (['pdf'].includes(ext)) return 'fa-file-pdf';
    if (['doc','docx'].includes(ext)) return 'fa-file-word';
    if (['xls','xlsx'].includes(ext)) return 'fa-file-excel';
    if (['ppt','pptx'].includes(ext)) return 'fa-file-powerpoint';
    if (['zip','rar'].includes(ext)) return 'fa-file-archive';
    if (['jpg','jpeg','png','gif','webp'].includes(ext)) return 'fa-file-image';
    if (['mp4','webm','mov','avi','mkv','mpeg'].includes(ext)) return 'fa-file-video';
    return 'fa-file';
  }

  /* ================== Hoist: تأكد أن المودال دائمًا تحت body ================== */
  function hoistModalToBody(){
    const el = getModalEl();
    if (!el) return false;
    if (el.parentNode !== d.body) {
      d.body.appendChild(el); // ← انقل المودال خارج أي حاوية قد يستبدلها الراوتر
      LOG.i('Modal hoisted to <body>');
    }
    return true;
  }

  /* ================== فتح/إغلاق/تهيئة ================== */
  async function open(){
    LOG.g('open()');
    if (!hoistModalToBody()){
      alert('تعذر فتح النافذة (العنصر غير موجود).'); LOG.ge(); return;
    }
    ensureMounted(); // ربط آمن
    const el = getModalEl();
    el.style.display = 'block';
    await loadCourses();
    setTimeout(()=> qsm('#'+inputSubjectId)?.focus(), 0);
    // أرسل حدثًا إرشاديًا
    try { d.dispatchEvent(new CustomEvent('ServiceRequestModal:open')); } catch(_){}
    LOG.ge();
  }

  function close(){
    const el = getModalEl();
    if (el) el.style.display = 'none';
    resetForm();
    try { d.dispatchEvent(new CustomEvent('ServiceRequestModal:close')); } catch(_){}
    LOG.i('close()');
  }

  function resetForm(){
    const form = qsm('#'+formId);
    if (form) form.reset();
    filesToUpload = [];
    renderFiles();
  }

  /* ================== جلب المساقات (محصور داخل المودال) ================== */
  async function loadCourses(){
    const sel = qsm('#'+inputCourseId);
    if (!sel) { LOG.w('loadCourses(): #requestCourse not found inside modal'); return; }

    const first = sel.querySelector('option[value=""]');
    sel.innerHTML = first ? first.outerHTML : `<option value="">-- اختر المادة (اختياري) --</option>`;

    const endpoints = [
      '/students/course-content/user-courses', // جديد
      '/student/courses/available'             // قديم
    ];

    let loaded = false, total = 0;

    for (const url of endpoints) {
      try {
        const res = await fetch(`${url}?_=${Date.now()}`, {
          headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json', 'Cache-Control':'no-store' },
          credentials: 'same-origin',
          cache:'no-store'
        });
        if (!res.ok) continue;
        const data = await res.json().catch(()=> ({}));
        updateCsrfFromResponse(data);

        let list = [];
        if (Array.isArray(data?.data)) list = data.data;
        else if (Array.isArray(data?.courses)) list = data.courses;
        else if (Array.isArray(data)) list = data;

        list.forEach(c=>{
          const id   = c.id || c.course_offering_id || c.offering_id || c.courseId || c.course_id;
          const name = c.name || c.course_name || c.title || `مساق ${id}`;
          if (!id) return;
          const opt = d.createElement('option');
          opt.value = String(id);
          opt.textContent = String(name);
          sel.appendChild(opt);
          total++;
        });

        if (total > 0) { loaded = true; break; }
      } catch (e) {
        LOG.w('loadCourses failed at', url, e);
      }
    }
    LOG.i('loadCourses()', { loaded, count: total });
  }

  /* ================== الملفات داخل المودال ================== */
  function onPickFiles(){ qsm('#'+inputFilesId)?.click(); }

  function onFilesChange(e){
    const files = e.target?.files;
    if (!files?.length) return;

    if (filesToUpload.length + files.length > MAX_FILES){
      w.showWarning?.(`الحد الأقصى للمرفقات ${MAX_FILES} ملفًا لكل طلب`);
      return;
    }
    let added=0;
    for (let i=0; i<files.length; i++){
      const f = files[i];
      if (f.size > MAX_FILE_MB * 1024 * 1024){
        w.showWarning?.(`الملف "${f.name}" يتجاوز ${MAX_FILE_MB}MB، تم تجاهله.`);
        continue;
      }
      filesToUpload.push(f); added++;
    }
    e.target.value = '';
    renderFiles();
    LOG.i('onFilesChange()', { added, total: filesToUpload.length });
  }

  function renderFiles(){
    const box = qsm('#'+filesListId);
    if (!box) return;
    if (filesToUpload.length===0){ box.innerHTML = ''; return; }
    box.innerHTML = filesToUpload.map((f, idx)=>{
      const name = f.name || `file-${idx+1}`;
      const ext  = name.includes('.') ? name.split('.').pop().toLowerCase() : '';
      const icon = iconByExt(ext);
      const size = formatBytes(f.size);
      return `
        <div class="file-card has-remove">
          <div class="thumb icon-thumb"><i class="fas ${icon}"></i></div>
          <div class="meta">
            <div class="name" title="${escapeHtml(name)}">${escapeHtml(name)}</div>
            <div class="sub">${[size, ext?('.'+ext):''].filter(Boolean).join(' • ')}</div>
          </div>
          <button class="remove-btn" data-i="${idx}" title="حذف"><i class="fas fa-times"></i></button>
        </div>`;
    }).join('');
    qsma('.remove-btn').forEach(btn=>{
      if (!btn.__srBound){
        btn.__srBound = true;
        btn.addEventListener('click', (ev)=>{
          ev.preventDefault();
          const i = Number(btn.dataset.i);
          if (!isNaN(i) && i>=0 && i<filesToUpload.length){
            filesToUpload.splice(i,1);
            renderFiles();
          }
        });
      }
    });
  }

  /* ================== إرسال النموذج ================== */
  async function submitForm(e){
    e?.preventDefault?.();

    const subject     = qsm('#'+inputSubjectId)?.value?.trim() || '';
    const description = qsm('#'+inputDescId)?.value?.trim() || '';
    const courseId    = qsm('#'+inputCourseId)?.value || '';

    if (subject.length < 3){
      w.showWarning?.('يرجى كتابة موضوع واضح (3 أحرف على الأقل).');
      return;
    }
    if (description.length < 10){
      w.showWarning?.('الرجاء كتابة تفاصيل كافية (10 أحرف على الأقل).');
      return;
    }

    if (submitting) return;
    submitting = true;

    const fd = new FormData();
    fd.append('subject', subject);
    fd.append('description', description);
    if (courseId) fd.append('course_offering_id', courseId);
    filesToUpload.forEach(f => fd.append('files[]', f));
    applyCsrf(fd);

    const btnSubmit = qsm('#'+formId+' .btn-submit');
    const prev = btnSubmit?.innerHTML;
    if (btnSubmit){ btnSubmit.disabled = true; btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }

    try{
      const endpointCandidates = [
        '/students/service-requests',
        '/student/service-requests/create'
      ];
      let sent = false, lastData=null, lastStatus=null;

      for (const url of endpointCandidates){
        LOG.i('POST', url, {withFiles: filesToUpload.length});
        const res = await fetch(url, {
          method: 'POST', body: fd,
          headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json', 'Cache-Control':'no-store' },
          credentials: 'same-origin',
          cache: 'no-store'
        });
        lastStatus = res.status;
        lastData = await res.json().catch(()=>({}));
        updateCsrfFromResponse(lastData);

        const ok = res.ok && !!(lastData && (lastData.success===true || lastData.ok===true));
        if (ok){ sent = true; break; }
      }

      if (!sent){
        w.showError?.(lastData?.message || lastData?.error || `فشل في إنشاء الطلب [${lastStatus}]`);
        return;
      }

      w.showSuccess?.('تم إنشاء الطلب بنجاح');
      close();

      // بلّغ صفحة الطلبات لو كانت مفتوحة
      try {
        if (typeof w.reloadServiceRequests === 'function') await w.reloadServiceRequests();
        const newId = lastData?.data?.request_id || lastData?.request?.id;
        if (newId && typeof w.loadRequest === 'function') w.loadRequest(newId);
      } catch(_) {}

    } catch(err){
      LOG.e('submit error', err);
      w.showError?.('حدث خطأ أثناء الإرسال');
    } finally {
      submitting = false;
      if (btnSubmit){ btnSubmit.disabled = false; btnSubmit.innerHTML = prev || '<i class="fas fa-paper-plane"></i> إرسال الطلب'; }
    }
  }

  /* ================== ربط آمن + تفويض أحداث ================== */
  function bindInsideModal(){
    const form = qsm('#'+formId);
    if (form && !form.__srBound){
      form.__srBound = true;
      form.addEventListener('submit', submitForm);
    }
    const btnSubmit = qsm('#'+formId+' .btn-submit');
    if (btnSubmit && !btnSubmit.__srBound){
      btnSubmit.__srBound = true;
      btnSubmit.addEventListener('click', submitForm);
    }
    const fileInput = qsm('#'+inputFilesId);
    if (fileInput && !fileInput.__srBound){
      fileInput.__srBound = true;
      fileInput.addEventListener('change', onFilesChange);
    }
    // أي زر قديم عليه onclick=ServiceRequestModal.close() نحترمه أيضًا
    qsma(`[onclick="ServiceRequestModal.close()"]`).forEach(b=>{
      if (!b.__srBound){
        b.__srBound = true;
        b.addEventListener('click', (ev)=>{ ev.preventDefault(); close(); });
      }
    });
  }

  function ensureMounted(){
    // 1) Hoist دائم
    hoistModalToBody();
    // 2) أربط عناصر المودال الداخلية
    bindInsideModal();
    LOG.i('ensureMounted(): modal bindings active');
  }

  // تفويض عام لزر فتح المودال (حتى لو تغيّر DOM أو تعددت النسخ)
  // ملاحظة: لا نوقف الانتشار حتى لا نعطّل أي لوجيك آخر؛ فقط نضمن الفتح.
  if (!d.__srModalDelegated){
    d.__srModalDelegated = true;
    d.addEventListener('click', (ev)=>{
      const t = ev.target.closest?.('#'+newBtnId);
      if (t){
        ev.preventDefault();
        open();
      }
    });
  }

  // ربط عند DOMContentLoaded لأول تحميل
  d.addEventListener('DOMContentLoaded', ()=> ensureMounted());

  // كشف عام
  w.ServiceRequestModal = { open, close, pickFiles: onPickFiles, submit: submitForm, ensureMounted };

})(window, document);
