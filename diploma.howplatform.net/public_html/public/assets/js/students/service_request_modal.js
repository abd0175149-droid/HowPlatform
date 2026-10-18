/* public/assets/js/students/service_request_modal.js
 * موديل طلب خدمة متخصصة — نسخة مُحسّنة لإعادة الإحياء بعد تنقلات SPA
 * - ensureMounted(): تعيد ربط الـDOM والأحداث إذا فُقدت المراجع
 * - open(): تستدعي ensureMounted() دائمًا قبل الفتح
 * - ربط أحداث آمن (idempotent) عبر data-srm-bound
 * - جلب المساقات عند أول فتح (ويُعاد بعد إعادة الإحياء)
 */

export const ServiceRequestModal = (() => {
  /* ===================== Selectors ===================== */
  // مبدئيًا نفضّل البحث داخل الموديل، وإن لم يكن جاهزًا نرجع للوثيقة
  const qs  = (s, ctx) => (ctx ? ctx.querySelector(s) :
                  (state.modal && state.modal.isConnected ? state.modal.querySelector(s) : document.querySelector(s)));
  const qsa = (s, ctx) => Array.from(ctx
                  ? ctx.querySelectorAll(s)
                  : (state.modal && state.modal.isConnected ? state.modal.querySelectorAll(s) : document.querySelectorAll(s)));
  // على مستوى الوثيقة دائمًا (مفيد لأزرار الفتح الموزعة بالصفحات)
  const qsd  = (s) => document.querySelector(s);
  const qsad = (s) => Array.from(document.querySelectorAll(s));

  const state = {
    modal: null,
    form: null,
    courseDDL: null,
    pickBtn: null,
    fileInput: null,
    uploadArea: null,
    filesList: null,
    closeBtn: null,
    cancelBtn: null,
    submitBtn: null,
    files: [],
    isOpen: false,
    isLoading: false,
    _observer: null
  };

  /* ===================== CSRF Helpers ===================== */
  function getCsrf() {
    const tokenEl  = qsd('meta[name="csrf-token"]');
    const headerEl = qsd('meta[name="csrf-header"]');
    return {
      token: tokenEl?.getAttribute('content') || '',
      header: headerEl?.getAttribute('content') || 'X-CSRF-TOKEN'
    };
  }
  function maybeUpdateCsrfFromResponse(res) {
    const newToken =
      res.headers.get('X-CSRF-TOKEN') ||
      res.headers.get('X-Csrf-Token') ||
      res.headers.get('X-Csrf');
    if (newToken) {
      const el = qsd('meta[name="csrf-token"]');
      if (el) el.setAttribute('content', newToken);
    }
  }

  /* ===================== فتح/إغلاق ===================== */
  function open() {
    // ⚠️ المهم: أعِد الإحياء قبل أي شيء
    ensureMounted();

    if (!state.modal) {
      console.warn('[ServiceModal] open(): modal not found in DOM.');
      return;
    }

    // إظهار العنصر قبل الأنيميشن
    state.modal.style.display = 'flex';
    state.modal.classList.add('show');
    state.modal.removeAttribute('aria-hidden');
    document.documentElement.classList.add('modal-open');
    document.body.classList.add('modal-open');
    state.isOpen = true;

    // اجلب المساقات عند أول فتح (أو بعد إعادة الإحياء)
    if (state.courseDDL && !state.courseDDL.dataset.loaded) {
      fetchCourses();
    }
  }

  function close() {
    if (!state.modal) return;

    // إزالة علامات فتح المودال على الوثيقة
    document.documentElement.classList.remove('modal-open');
    document.body.classList.remove('modal-open');

    state.modal.classList.remove('show');
    state.modal.setAttribute('aria-hidden', 'true');
    state.isOpen = false;

    const onDone = () => {
      state.modal.style.display = 'none';
      state.modal.removeEventListener('transitionend', onDone);
    };
    state.modal.addEventListener('transitionend', onDone);
    // احتياطي
    setTimeout(() => {
      if (!state.modal?.classList.contains('show')) {
        if (state.modal) state.modal.style.display = 'none';
      }
    }, 350);

    clearForm();
  }

  /* ===================== DOM & Events ===================== */
  function cacheDom() {
    // ابحث عن جذر الموديل في الوثيقة، ثم باقي العناصر تُجلب ضمن الموديل
    state.modal     = qsd('#serviceRequestModal');
    state.form      = qs('#serviceRequestForm');
    state.courseDDL = qs('#requestCourse');
    state.pickBtn   = qs('#pickFilesBtn');
    state.fileInput = qs('#requestFiles');
    state.uploadArea= qs('#fileUploadArea');
    state.filesList = qs('#uploadedFilesList');
    state.closeBtn  = qs('#srvReqCloseBtn');
    state.cancelBtn = qs('#srvReqCancelBtn');
    state.submitBtn = qs('#srvReqSubmitBtn');
  }

  // ربط آمن: لا يضاعف المستمعات على نفس العناصر (يستخدم data-srm-bound)
  function bindEventsIdempotent() {
    // أزرار الفتح (على مستوى الوثيقة)
    qsad('#svcNewRequestBtn, .btn-service-request, [data-action="open-service-request"]').forEach(btn => {
      if (!btn || btn.dataset.srmBound === '1') return;
      btn.addEventListener('click', (e) => {
        // إن كان لديك onclick inline سيعمل؛ لكن نمنع التكرار من جهتنا
        if (e) e.preventDefault();
        open();
      });
      btn.dataset.srmBound = '1';
    });

    // أزرار الإغلاق
    if (state.closeBtn && state.closeBtn.dataset.srmBound !== '1') {
      state.closeBtn.addEventListener('click', close);
      state.closeBtn.dataset.srmBound = '1';
    }
    if (state.cancelBtn && state.cancelBtn.dataset.srmBound !== '1') {
      state.cancelBtn.addEventListener('click', close);
      state.cancelBtn.dataset.srmBound = '1';
    }

    // اختيار الملفات
    if (state.pickBtn && state.pickBtn.dataset.srmBound !== '1') {
      state.pickBtn.addEventListener('click', () => state.fileInput?.click());
      state.pickBtn.dataset.srmBound = '1';
    }
    if (state.fileInput && state.fileInput.dataset.srmBound !== '1') {
      state.fileInput.addEventListener('change', (e) => {
        addFiles(e.target.files);
        state.fileInput.value = ''; // للسماح بإعادة اختيار نفس الملف
      });
      state.fileInput.dataset.srmBound = '1';
    }

    // سحب وإفلات
    if (state.uploadArea && state.uploadArea.dataset.srmBound !== '1') {
      ['dragenter','dragover'].forEach(evt => state.uploadArea.addEventListener(evt, (e) => {
        e.preventDefault(); e.stopPropagation();
        state.uploadArea.classList.add('dragover');
      }));
      ['dragleave','drop'].forEach(evt => state.uploadArea.addEventListener(evt, (e) => {
        e.preventDefault(); e.stopPropagation();
        state.uploadArea.classList.remove('dragover');
      }));
      state.uploadArea.addEventListener('drop', (e) => {
        if (e.dataTransfer?.files?.length) addFiles(e.dataTransfer.files);
      });
      state.uploadArea.dataset.srmBound = '1';
    }

    // إرسال النموذج
    if (state.form && state.form.dataset.srmBound !== '1') {
      state.form.addEventListener('submit', onSubmit);
      state.form.dataset.srmBound = '1';
    }
  }

  // راقب تغييرات DOM: إذا خرجت عقدة المودال من الوثيقة، افرغ الكاش لإعادة الإحياء لاحقًا
  function watchDom() {
    try { state._observer?.disconnect?.(); } catch {}
    state._observer = new MutationObserver(() => {
      if (state.modal && !document.contains(state.modal)) {
        // المودال أُزيل/استُبدل — سنُعيد الإحياء عند أول open()
        state.modal = null;
        state.form = state.courseDDL = state.pickBtn = state.fileInput = state.uploadArea = state.filesList = state.closeBtn = state.cancelBtn = state.submitBtn = null;
      }
    });
    state._observer.observe(document.body, { childList: true, subtree: true });
  }

  // واجهة عامة لإعادة الإحياء عند الحاجة (تُستدعى تلقائيًا من open() ويمكن استدعاؤها يدويًا)
  function ensureMounted() {
    // إذا لم نجد المودال أو لم تعد العقدة متصلة بالـDOM → أعد الاكتشاف والربط
    if (!state.modal || !state.modal.isConnected) {
      cacheDom();
      if (!state.modal) {
        // قد يكون المودال غير مُضمّن في الصفحة الحالية (حالة نادرة إن تغيّر اللاياوت)
        console.warn('[ServiceModal] ensureMounted(): modal element not found.');
        return false;
      }
      // أعد ربط الأحداث
      bindEventsIdempotent();
      // راقب DOM للاستبدالات القادمة
      watchDom();
      // تهيئة قائمة المساقات يعتبر Lazy عند الفتح
      console.log('[ServiceModal] rehydrated & bound');
    } else {
      // عناصر موجودة — تأكّد فقط من ربط أزرار فُتحت ديناميكيًا
      bindEventsIdempotent();
    }
    return true;
  }

  /* ===================== جلب المساقات ===================== */
  async function fetchCourses() {
    try {
      if (!state.courseDDL) return;
      state.courseDDL.disabled = true;
      state.courseDDL.innerHTML = '<option value="">... جارِ التحميل</option>';

      const res  = await fetch('/students/service-requests/meta', { credentials: 'same-origin' });
      const json = await res.json();
      const list = (json?.data?.courses ?? []);

      state.courseDDL.innerHTML = '<option value="">-- اختر المادة (اختياري) --</option>';
      for (const c of list) {
        const opt = document.createElement('option');
        opt.value = c.offering_id;
        opt.textContent = `${c.course_name} — ${c.semester_name}`;
        state.courseDDL.appendChild(opt);
      }
      state.courseDDL.dataset.loaded = '1';
    } catch (err) {
      console.error('[ServiceModal] fetchCourses error', err);
    } finally {
      if (state.courseDDL) state.courseDDL.disabled = false;
    }
  }

  /* ===================== الملفات والمعاينة ===================== */
  function addFiles(fileList) {
    const maxBytes = 10 * 1024 * 1024; // 10MB
    const allowed = new Set([
      'image/jpeg','image/png','image/gif','image/webp',
      'video/mp4','video/webm','video/ogg',
      'application/pdf',
      'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
      'application/zip','application/x-rar-compressed'
    ]);

    for (const f of fileList) {
      if (f.size > maxBytes) {
        toast('⚠️ ملف أكبر من 10MB تم تجاهله: ' + f.name);
        continue;
      }
      if (!allowed.has(f.type)) {
        toast('⚠️ نوع ملف غير مسموح: ' + f.name);
        continue;
      }
      state.files.push(f);
    }
    renderFiles();
  }

  function renderFiles() {
    if (!state.filesList) return;
    state.filesList.innerHTML = '';

    state.files.forEach((f, i) => {
      const isImage = f.type.startsWith('image/');
      const isVideo = f.type.startsWith('video/');

      const card = document.createElement('div');
      card.className = 'file-card';

      const removeBtn = document.createElement('button');
      removeBtn.className = 'remove-file';
      removeBtn.innerHTML = '<i class="fas fa-times"></i>';
      removeBtn.addEventListener('click', () => {
        state.files.splice(i, 1);
        renderFiles();
      });

      const thumb = document.createElement('div');
      thumb.className = 'thumb ' + (isImage ? 'img-thumb' : 'icon-thumb');

      if (isImage) {
        const img = document.createElement('img');
        img.alt = f.name;
        img.src = URL.createObjectURL(f);
        thumb.appendChild(img);
      } else if (isVideo) {
        const v = document.createElement('div');
        v.className = 'video-thumb';
        v.innerHTML = '<i class="fas fa-video"></i>';
        thumb.appendChild(v);
      } else {
        thumb.innerHTML = '<i class="fas fa-file-alt"></i>';
      }

      const meta = document.createElement('div');
      meta.className = 'meta';
      meta.innerHTML = `
        <div class="name" title="${f.name}">${f.name}</div>
        <div class="sub">${(f.size/1024/1024).toFixed(2)} MB</div>
      `;

      card.appendChild(removeBtn);
      card.appendChild(thumb);
      card.appendChild(meta);
      state.filesList.appendChild(card);
    });
  }

  /* ===================== إرسال الطلب ===================== */
  async function onSubmit(e) {
    e.preventDefault();
    if (state.isLoading) return;

    // ⚠️ اجلب الحقول من داخل الموديل فقط لتفادي التقاط عناصر الصفحة
    const subjectEl = qs('#requestSubject');
    const descEl    = qs('#requestDescription');
    const courseEl  = qs('#requestCourse');

    const subject = subjectEl?.value?.trim() || '';
    const desc    = descEl?.value?.trim() || '';
    const coId    = courseEl?.value || '';

    // فاليديشن واجهة
    let hasErr = false;
    clearInvalids();
    if (subject.length < 3) {
      setInvalid('#requestSubject', 'الرجاء كتابة موضوع واضح (3 أحرف على الأقل).');
      hasErr = true;
    }
    if (desc.length < 10) {
      setInvalid('#requestDescription', 'الرجاء كتابة تفاصيل كافية (10 أحرف على الأقل).');
      hasErr = true;
    }
    if (hasErr) return;

    // بناء الـ FormData
    const fd = new FormData();
    fd.append('subject', subject);
    fd.append('description', desc);
    if (coId) fd.append('course_offering_id', coId);
    for (const f of state.files) {
      fd.append('files[]', f, f.name);
    }

    // إعدادات CSRF
    const { token, header } = getCsrf();
    const headers = {};
    if (token) headers[header] = token;

    // حماية من النقر المزدوج
    state.isLoading = true;
    state.submitBtn?.setAttribute('disabled', 'disabled');
    state.submitBtn?.classList.add('is-loading');
    overlayShow('جارٍ معالجة طلبك…', 'يرجى الانتظار قليلاً — نُرسل طلبك ونجهّز رد «فَطين».');

    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 45000);

      const res = await fetch('/students/service-requests', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers,
        signal: controller.signal
      });
      clearTimeout(timeoutId);

      maybeUpdateCsrfFromResponse(res);

      if (res.status === 401) { overlayHide(); toast('🔒 يجب تسجيل الدخول لإرسال الطلب.'); return; }
      if (res.status === 403 || res.status === 419) { overlayHide(); toast('⛔ فشل التحقق الأمني (CSRF). حدّث الصفحة.'); return; }
      if (res.status === 413) { overlayHide(); toast('⚠️ أحد الملفات كبير جدًا.'); return; }

      let json = null;
      try { json = await res.json(); } catch {
        if (!res.ok) { overlayHide(); toast(`❌ فشل الإرسال (HTTP ${res.status})`); return; }
        json = { success: true, data: {} };
      }

      if (!res.ok || !json?.success) {
        overlayHide();
        if (res.status === 422 && json?.fields) applyFieldErrors(json.fields);
        const msg = json?.message || `فشل الإرسال (HTTP ${res.status})`;
        toast('❌ ' + msg);
        return;
      }

      // نجاح: حالة "تم الإرسال" ثم إغلاق + إشعار صفحة الخدمة لفتح الطلب
      const reqId = json?.data?.request_id || json?.request_id || null;
      overlaySuccess('تم إرسال طلبك ✓', 'فَطين يجهّز ردّه الآن، وسيتابعك الفريق الأكاديمي.');
      try { resetForm(); } catch (_) {}
      setTimeout(() => {
        overlayHide();
        close();
        try { window.dispatchEvent(new CustomEvent('svc:request-created', { detail: { request_id: reqId } })); } catch (_) {}
        if (typeof window.NotifCenter?.reloadCount === 'function') window.NotifCenter.reloadCount();
      }, 1400);

    } catch (err) {
      overlayHide();
      if (err?.name === 'AbortError') {
        toast('⏱️ انتهت المهلة. تحقّق من الاتصال وحاول مرة أخرى.');
      } else {
        console.error('[ServiceModal] submit error', err);
        toast('❌ حدث خطأ غير متوقع أثناء الإرسال.');
      }
    } finally {
      state.isLoading = false;
      state.submitBtn?.removeAttribute('disabled');
      state.submitBtn?.classList.remove('is-loading');
    }
  }

  /* ===== طبقة اللودر/النجاح ===== */
  function overlayShow(title, text) {
    const ov = qs('#srvReqOverlay'); if (!ov) return;
    const sp = qs('#srvSpin'), ck = qs('#srvCheck');
    if (sp) sp.style.display = ''; if (ck) ck.style.display = 'none';
    const t = qs('#srvOverlayTitle'), p = qs('#srvOverlayText');
    if (t) t.textContent = title || 'جارٍ المعالجة…';
    if (p) p.textContent = text || '';
    ov.classList.add('show');
  }
  function overlaySuccess(title, text) {
    const sp = qs('#srvSpin'), ck = qs('#srvCheck');
    if (sp) sp.style.display = 'none'; if (ck) ck.style.display = 'flex';
    const t = qs('#srvOverlayTitle'), p = qs('#srvOverlayText');
    if (t) t.textContent = title || 'تم ✓';
    if (p) p.textContent = text || '';
  }
  function overlayHide() { const ov = qs('#srvReqOverlay'); if (ov) ov.classList.remove('show'); }
  function resetForm() {
    const f = qs('#serviceRequestForm'); if (f) f.reset();
    const list = qs('#uploadedFilesList'); if (list) list.innerHTML = '';
    if (state) state.selectedFiles = [];
    clearInvalids();
  }

  /* ===================== أدوات واجهة ===================== */
  function setInvalid(selInput, msg) {
    const input = qs(selInput);
    const fb = qs(`.invalid-feedback[data-for="${input?.id}"]`);
    input?.classList.add('is-invalid');
    if (fb) fb.textContent = msg || '';
  }
  function clearInvalids() {
    qsa('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    qsa('.invalid-feedback').forEach(el => el.textContent = '');
  }
  function applyFieldErrors(fields) {
    for (const [k,v] of Object.entries(fields)) {
      const input = qs(`[name="${k}"]`);
      if (!input) continue;
      input.classList.add('is-invalid');
      const fb = qs(`.invalid-feedback[data-for="${input.id}"]`);
      if (fb) fb.textContent = String(v);
    }
  }
  function toast(msg) {
    if (window?.Swal?.fire) return Swal.fire({icon:'info', text:String(msg), confirmButtonText:'حسنًا'});
    alert(String(msg));
  }

  /* ===================== Init / Ensure ===================== */
  function init() {
    cacheDom();
    if (!state.modal || !state.form) {
      console.warn('[ServiceModal] init(): modal DOM not found at init time. Will rehydrate on demand.');
      // حتى لو غير موجود الآن، سنُعيد الإحياء عند أول open()
    } else {
      bindEventsIdempotent();
      watchDom();
      console.log('[ServiceModal] initialized');
    }
  }

  function clearForm() {
    state.form?.reset();
    state.files = [];
    renderFiles();
    clearInvalids();
    // لا نعبث بـ dataset.loaded للمساقات؛ يُعاد الجلب فقط إذا تغيّر DOM
  }

  return {
    init,
    open,
    close,
    ensureMounted,
    isReady: () => !!(state.modal && state.modal.isConnected)
  };
})();
