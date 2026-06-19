// public/assets/js/admins/sections/library_upload.js
'use strict';

/**
 * Module: library-upload
 * - رفع الملفات + صورة معاينة
 * - عرض جميع الملفات + فلاتر (صف/فصل/مادة) + حذف
 * - تخزين محلي للفلاتر (قائمة) + تخزين محلي لحقول نموذج الرفع (الصف، الفصل) فقط
 */

export function init() {
  // ===== حارس تهيئة مرتبط بجذر القسم =====
  const root =
    document.getElementById('library-upload-root') ||
    document.querySelector('.library-files-container') ||
    document;

  if (root && root.dataset.bound === '1') return;
  if (root) root.dataset.bound = '1';

  // ===== إعدادات عامة =====
  const BASE = (window.BASE_URL || '');

  // CSRF (اختياري)
  const CSRF = (() => {
    const headerName =
      document.querySelector('meta[name="X-CSRF-HEADER"]')?.getAttribute('content') ||
      document.querySelector('meta[name="csrf-header"]')?.getAttribute('content') ||
      'X-CSRF-TOKEN';
    const token =
      document.querySelector('meta[name="X-CSRF-TOKEN"]')?.getAttribute('content') ||
      document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
      '';
    return { headerName, token };
  })();

  function withCsrf(headers = {}) {
    if (CSRF.token) headers[CSRF.headerName] = CSRF.token;
    return headers;
  }

  // ===== أدوات مساعدة عامة =====
  async function GET_JSON(url, opts = {}) {
    const headers = withCsrf({ Accept: 'application/json', ...(opts.headers || {}) });
    const res = await fetch(url, { ...opts, headers });
    let data = null;
    try { data = await res.json(); }
    catch { data = { ok: false, error: 'Invalid JSON', status: res.status }; }
    return { ok: res.ok, status: res.status, data };
  }
  const GET = (url) => GET_JSON(url).then(({ data }) => data);

  function debounce(fn, ms = 250) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  }

  // ===== LocalStorage helpers (وُضعت مبكّرًا لأننا سنستخدمها مبكرًا) =====
  const LS_KEYS = {
    // فلاتر قائمة الملفات
    grade:          'admin.library.filters.grade',
    semester:       'admin.library.filters.semester',
    // نموذج الرفع (لا نخزّن المادة ولا التصنيف)
    uploadGrade:    'admin.library.upload.grade',
    uploadSemester: 'admin.library.upload.semester',
  };
  function saveLS(key, val){ try{ localStorage.setItem(key, val ?? ''); }catch{} }
  function readLS(key){ try{ return localStorage.getItem(key) || ''; }catch{ return ''; } }
  const cssEscape = (window.CSS && window.CSS.escape) ? window.CSS.escape : (s) => String(s).replace(/[^a-zA-Z0-9_\-]/g, '\\$&');
  function hasOption(select, val){
    return !!(select && val && select.querySelector(`option[value="${cssEscape(String(val))}"]`));
  }

  // ======= عناصر نموذج الرفع =======
  const form        = document.getElementById('library-upload-form');
  const out         = document.getElementById('upload-result');
  const dropZone    = document.getElementById('drop-zone');
  const fileInput   = document.getElementById('file');

  const ddlG        = document.getElementById('grade_id');
  const ddlS        = document.getElementById('subject_id');
  const ddlSem      = document.getElementById('semester_id');
  const ddlCat      = document.getElementById('category_id');

  const previewInput     = document.getElementById('preview');
  const previewContainer = document.getElementById('preview-card');
  const previewNameSpan  = document.getElementById('preview-selected-name');
  const previewZone      = document.getElementById('preview-zone');

  // حاوية كروت الملفات المختارة قبل الرفع
  let selectedFiles = [];
  const filesContainer = document.createElement('div');
  filesContainer.id = 'files-preview';
  filesContainer.style.display = 'flex';
  filesContainer.style.flexWrap = 'wrap';
  filesContainer.style.gap = '1rem';
  if (dropZone) dropZone.appendChild(filesContainer);

  // ======= عناصر جدول العرض والفلاتر =======
  const tableBody      = document.getElementById('libraryFilesTableBody');
  const gradeFilter    = document.getElementById('gradeFilter');
  const subjectFilter  = document.getElementById('subjectFilter');
  const semesterFilter = document.getElementById('semesterFilter'); // "" | "1" | "2"
  const refreshBtn     = document.getElementById('refreshLibraryFiles');

  // ======= منطق المعاينة =======
  let previewImg = null;

  function fillOptions(select, rows, makeLabel, placeholder) {
    if (!select) return;
    select.innerHTML = '';
    if (placeholder) {
      const opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = placeholder;
      select.appendChild(opt0);
    }
    (rows || []).forEach(row => {
      const opt = document.createElement('option');
      opt.value = row.id;
      opt.textContent = makeLabel ? makeLabel(row) : (row.name_ar || row.name || '');
      select.appendChild(opt);
    });
    select.disabled = false;
  }

  function renderPreviewImage() {
    if (!previewContainer || !previewInput) return;
    previewContainer.innerHTML = '';
    if (!previewImg) {
      if (previewNameSpan) previewNameSpan.textContent = 'لم يتم اختيار صورة المعاينة';
      const dt = new DataTransfer(); // تنظيف input.files
      previewInput.files = dt.files;
      return;
    }
    if (previewNameSpan) previewNameSpan.textContent = previewImg.name;

    const card = document.createElement('div');
    card.className = 'file-card';

    const img = document.createElement('img');
    img.src = URL.createObjectURL(previewImg);
    img.style.width = '80px';
    img.style.height = '80px';
    img.style.objectFit = 'cover';
    img.style.borderRadius = '8px';
    img.style.marginBottom = '0.5rem';
    card.appendChild(img);

    const name = document.createElement('div');
    name.className = 'file-name';
    name.textContent = previewImg.name;
    card.appendChild(name);

    const remove = document.createElement('button');
    remove.className = 'remove-btn';
    remove.innerHTML = '&times;';
    remove.addEventListener('click', () => { previewImg = null; renderPreviewImage(); });
    card.appendChild(remove);

    previewContainer.appendChild(card);

    // ربط صورة المعاينة كملف واحد
    const dt = new DataTransfer();
    dt.items.add(previewImg);
    previewInput.files = dt.files;
  }

  function setPreview(file) {
    if (!file) { previewImg = null; renderPreviewImage(); return; }
    if (!file.type.startsWith('image/')) {
      if (out) { out.textContent = 'الملف المختار للمعاينة يجب أن يكون صورة.'; out.style.color = 'var(--accent-600, #b71c1c)'; }
      previewImg = null; renderPreviewImage(); return;
    }
    if (file.size > 4 * 1024 * 1024) {
      if (out) { out.textContent = 'حجم صورة المعاينة يجب ألا يتجاوز 4MB.'; out.style.color = 'var(--accent-600, #b71c1c)'; }
      previewImg = null; renderPreviewImage(); return;
    }
    previewImg = file; renderPreviewImage();
  }

  // ======= كروت الملفات المختارة قبل الرفع =======
  function getFileIcon(mime = '', name = '') {
    if (mime.startsWith('image/')) return 'fa-file-image';
    if (mime === 'application/pdf') return 'fa-file-pdf';
    if (mime.includes('word')) return 'fa-file-word';
    if (mime.includes('excel') || mime.includes('spreadsheet')) return 'fa-file-excel';
    if (mime.includes('powerpoint')) return 'fa-file-powerpoint';
    if (mime.startsWith('audio/')) return 'fa-file-audio';
    if (mime.startsWith('video/')) return 'fa-file-video';
    if (mime.startsWith('text/'))  return 'fa-file-lines';
    if (name.endsWith('.zip') || name.endsWith('.rar')) return 'fa-file-archive';
    return 'fa-file';
  }

  function renderFiles() {
    if (!filesContainer) return;
    filesContainer.innerHTML = '';
    selectedFiles.forEach((file, index) => {
      const card = document.createElement('div');
      card.className = 'file-card';

      if (file.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.style.width = '60px';
        img.style.height = '60px';
        img.style.objectFit = 'cover';
        img.style.borderRadius = '6px';
        img.style.marginBottom = '0.5rem';
        card.appendChild(img);
      } else {
        const icon = document.createElement('i');
        icon.className = `fas ${getFileIcon(file.type, file.name)}`;
        card.appendChild(icon);
      }

      const name = document.createElement('div');
      name.className = 'file-name';
      name.textContent = file.name;
      card.appendChild(name);

      const remove = document.createElement('button');
      remove.className = 'remove-btn';
      remove.innerHTML = '&times;';
      remove.addEventListener('click', () => { selectedFiles.splice(index, 1); renderFiles(); });
      card.appendChild(remove);

      filesContainer.appendChild(card);
    });

    // مزامنة input[type=file] مع التحديد الحالي
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    if (fileInput) fileInput.files = dt.files;
  }

  function addFiles(files) {
    for (let i = 0; i < files.length; i++) selectedFiles.push(files[i]);
    renderFiles();
  }

  // ======= Drag & Drop للملف الرئيسي =======
  if (!window.__LIB_UPLOAD_DOC_DRAG_BOUND__) {
    const prevent = (e) => { e.preventDefault(); e.stopPropagation(); };
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev => document.addEventListener(ev, prevent, false));
    window.__LIB_UPLOAD_DOC_DRAG_BOUND__ = true;
  }
  if (dropZone && fileInput) {
    ['dragenter','dragover'].forEach(ev => {
      dropZone.addEventListener(ev, () => dropZone.classList.add('dragover'), { passive: true });
    });
    ['dragleave','drop'].forEach(ev => {
      dropZone.addEventListener(ev, () => dropZone.classList.remove('dragover'), { passive: true });
    });
    dropZone.addEventListener('drop', e => { if (e.dataTransfer?.files?.length) addFiles(e.dataTransfer.files); });
    fileInput.addEventListener('change', function () { if (this.files?.length) addFiles(this.files); });
  }

  // ======= Drag & Drop لصورة المعاينة =======
  if (previewZone && previewInput) {
    ['dragenter','dragover'].forEach(ev => {
      previewZone.addEventListener(ev, (e) => { e.preventDefault(); e.stopPropagation(); previewZone.classList.add('dragover'); }, false);
    });
    ['dragleave','drop'].forEach(ev => {
      previewZone.addEventListener(ev, (e) => { e.preventDefault(); e.stopPropagation(); previewZone.classList.remove('dragover'); }, false);
    });
    previewZone.addEventListener('drop', (e) => {
      const file = e.dataTransfer?.files?.[0] || null; setPreview(file);
      if (file) { const dt = new DataTransfer(); dt.items.add(file); previewInput.files = dt.files; }
    });
    previewInput.addEventListener('change', function () { setPreview(this.files?.[0] || null); });
  }

  // ======= دوال خاصة بنموذج الرفع: تحميل مواد الصف المختار =======
  async function loadUploadSubjectsFor(gradeId) {
    if (!ddlS) return;
    ddlS.disabled = true;
    ddlS.innerHTML = '<option value="">اختر المادة…</option>';
    if (!gradeId) return;
    try {
      const sub = await GET(`${BASE}/admins/library/meta/grades/${encodeURIComponent(gradeId)}/subjects`);
      if (sub?.ok) fillOptions(ddlS, sub.data, r => r.name_ar, 'اختر المادة…');
      else if (out) { out.textContent = 'لا توجد مواد مرتبطة بهذا الصف.'; out.style.color = 'var(--accent-600, #b71c1c)'; }
    } catch {
      if (out) { out.textContent = 'تعذر جلب المواد.'; out.style.color = 'var(--accent-600, #b71c1c)'; }
    }
  }

  // ======= تحميل بيانات الميتا لحقول الرفع =======
  Promise.all([
    GET(`${BASE}/admins/library/meta/grades`),
    GET(`${BASE}/admins/library/meta/semesters`),
    GET(`${BASE}/admins/library/meta/categories`),
  ]).then(async ([g, sem, cat]) => {
    if (ddlG && g?.ok)    fillOptions(ddlG, g.data, r => `${r.name_ar} (${r.level})`, 'اختر الصف…');
    if (ddlSem && sem?.ok) fillOptions(ddlSem, sem.data, r => r.name_ar, 'يغطي العام كاملًا');
    if (ddlCat && cat?.ok) fillOptions(ddlCat, cat.data, r => r.name_ar, 'اختر التصنيف…');

    // ✅ استرجاع قيم نموذج الرفع المخزّنة (الصف، الفصل) — المادة مستثناة
    const savedUG   = readLS(LS_KEYS.uploadGrade);
    const savedUSem = readLS(LS_KEYS.uploadSemester);

    if (ddlG && savedUG && hasOption(ddlG, savedUG)) {
      ddlG.value = savedUG;
      await loadUploadSubjectsFor(savedUG);   // نحدّث قائمة المواد وفق الصف المحفوظ
      if (ddlS) ddlS.value = '';              // المادة تبقى فارغة (غير محفوظة)
    }
    if (ddlSem && savedUSem && hasOption(ddlSem, savedUSem)) {
      ddlSem.value = savedUSem;
    }
  }).catch(() => {
    if (out) { out.textContent = 'تعذر تحميل بيانات القوائم.'; out.style.color = 'var(--accent-600, #b71c1c)'; }
  });

  // عند تغيير الصف في نموذج الرفع — نحفظ الصف ونحدّث المواد
  if (ddlG && ddlS) {
    ddlS.disabled = true;
    ddlS.innerHTML = '<option value="">اختر المادة…</option>';
    ddlG.addEventListener('change', async () => {
      saveLS(LS_KEYS.uploadGrade, ddlG.value || ''); // ✅ حفظ اختيار الصف (نموذج الرفع)
      await loadUploadSubjectsFor(ddlG.value);
      if (ddlS) ddlS.value = ''; // المادة غير محفوظة
    });
  }

  // عند تغيير الفصل في نموذج الرفع — نحفظ الفصل
  if (ddlSem) {
    ddlSem.addEventListener('change', () => {
      saveLS(LS_KEYS.uploadSemester, ddlSem.value || ''); // ✅ حفظ اختيار الفصل (نموذج الرفع)
    });
  }

  // ======= إرسال نموذج الرفع عبر Fetch =======
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (out) { out.textContent = 'جاري الرفع...'; out.style.color = ''; }

      const fd = new FormData(form);
      const force = form.querySelector('[name="force_new"]');
      if (force) fd.set('force_new', force.checked ? '1' : '0');

      if (!fd.get('grade_id') || !fd.get('subject_id') || !fd.get('category_id') || !fd.get('file')) {
        if (out) { out.textContent = 'الحقول المطلوبة: الصف، المادة، التصنيف، والملف.'; out.style.color = 'var(--accent-600, #b71c1c)'; }
        return;
      }

      try {
        const resp = await fetch(`${BASE}/admins/library/uploads`, {
          method: 'POST',
          body: fd,
          headers: withCsrf({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }),
        });

        const isJson = (resp.headers.get('content-type') || '').includes('application/json');
        const payload = isJson ? await resp.json() : { ok: resp.ok };

        if (!resp.ok) {
          if (out) { out.textContent = `فشل (${resp.status}). ${payload?.error ?? ''}`; out.style.color = 'var(--accent-600, #b71c1c)'; }
          return;
        }

        if (window.showSuccess) showSuccess('تم رفع الملف بنجاح', 'نجاح', true);
        else if (out) { out.textContent = 'تم رفع الملف بنجاح.'; out.style.color = 'green'; }

        // تنظيف واجهة الرفع (لا نمسّ القيم المخزّنة للصف/الفصل)
        if (out) out.textContent = '';
        form.reset();
        if (ddlS) ddlS.disabled = true;
        selectedFiles = [];
        renderFiles();
        previewImg = null;
        renderPreviewImage();

        // حدّث جدول الملفات
        loadFiles();

      } catch (err) {
        console.error('[library-upload] network error:', err);
        if (out) { out.textContent = 'خطأ شبكة أثناء الرفع.'; out.style.color = 'var(--accent-600, #b71c1c)'; }
      }
    });
  }

  renderPreviewImage(); // تهيئة واجهة المعاينة

  // ======= منطق جدول "كل الملفات" + الفلاتر =======
  const MIME_MAP = {
    'application/pdf': 'PDF',
    'application/msword': 'DOC',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'DOCX',
    'application/vnd.ms-excel': 'XLS',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'XLSX',
    'application/vnd.ms-powerpoint': 'PPT',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PPTX',
    'application/zip': 'ZIP',
    'application/x-rar-compressed': 'RAR',
  };
  function mimeToExt(mime) {
    if (!mime) return 'FILE';
    return MIME_MAP[mime] || mime.split('/').pop().toUpperCase();
  }
  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => (
      { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]
    ));
  }
  function extractItems(payload) {
    if (!payload) return [];
    if (Array.isArray(payload)) return payload;
    if (payload.items && Array.isArray(payload.items)) return payload.items;
    if (payload.data && Array.isArray(payload.data)) return payload.data;
    if (payload.data?.items && Array.isArray(payload.data.items)) return payload.data.items;
    if (payload.files && Array.isArray(payload.files)) return payload.files;
    return [];
  }

  function buildRow(item, index) {
    const fileId      = item.id ?? item.file_id ?? item.fileId ?? '';
    const subjectTxt  = item.subject_name ?? item.subject ?? '';
    const name        = item.title ?? item.original_name ?? item.name ?? 'ملف بدون اسم';
    const typeTxt     = mimeToExt(item.mime_type ?? item.mimetype ?? item.type);
    const categoryTxt = item.category_name ?? item.category ?? '';

    const uploadedSrc = item.uploaded_at_iso || item.uploaded_at || item.created_at || null;
    const uploadedTxt = uploadedSrc ? formatDateTime(uploadedSrc) : '-';

    return `
      <tr>
        <td style="text-align:center">${index + 1}</td>
        <td style="text-align:center">
          <img src="https://library.howplatform.net/preview/${fileId}" alt="معاينة الملف" class="preview-thumb">
        </td>
        <td style="text-align:center">${subjectTxt ? escapeHtml(subjectTxt) : '-'}</td>
        <td style="text-align:center">${escapeHtml(name)}</td>
        <td style="text-align:center">${categoryTxt ? escapeHtml(categoryTxt) : '-'}</td>
        <td style="text-align:center"><span class="file-type-badge">${escapeHtml(typeTxt)}</span></td>
        <td style="text-align:center">${escapeHtml(uploadedTxt)}</td>
        <td>
          <div class="action-buttons-lib">
            <button class="action-btn-lib lib-delete-btn" data-action="delete" data-file-id="${escapeHtml(String(fileId))}" title="حذف">
              <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
              <span class="sr-only">حذف</span>
            </button>
          </div>
        </td>
      </tr>
    `;
  }

  function currentFilters() {
    const gid = (gradeFilter?.value ?? '').trim();
    const sid = (subjectFilter?.value ?? '').trim();
    const sem = (semesterFilter?.value ?? '').trim();

    const params = new URLSearchParams();
    if (gid) params.set('grade_id', gid);
    if (sid) params.set('subject_id', sid);
    if (sem) params.set('semester_id', sem);
    return params.toString();
  }

  let listAbort = null;
  async function loadFiles() {
    if (!tableBody) return;
    tableBody.innerHTML = `<tr><td colspan="8" class="no-results-cell">جارِ التحميل...</td></tr>`;

    if (listAbort) listAbort.abort();
    listAbort = new AbortController();

    const q = currentFilters();
    const url = q ? `${BASE}/admins/library/files?${q}` : `${BASE}/admins/library/files`;
    const { ok, data } = await GET_JSON(url, { signal: listAbort.signal });

    const items = ok ? extractItems(data) : [];
    if (!items.length) {
      tableBody.innerHTML = `<tr><td colspan="8" class="no-results-cell">لا توجد بيانات مطابقة للفلاتر الحالية.</td></tr>`;
      return;
    }
    tableBody.innerHTML = items.map((item, i) => buildRow(item, i)).join('');
  }
  const loadFilesDebounced = debounce(loadFiles, 250);

  async function deleteFile(fileId) {
    const nativeConfirm = () => window.confirm('هل أنت متأكد من حذف الملف؟ لا يمكن التراجع.');
    const confirmed = (window.Swal && Swal.fire)
      ? await Swal.fire({
          title: 'تأكيد الحذف',
          text: 'هل تريد حذف هذا الملف نهائيًا؟',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'نعم، احذف',
          cancelButtonText: 'إلغاء'
        }).then(r => r.isConfirmed)
      : nativeConfirm();

    if (!confirmed) return;

    let res = await GET_JSON(`${BASE}/admins/library/files/${encodeURIComponent(fileId)}`, { method: 'DELETE' });
    if (!res.ok) {
      res = await GET_JSON(`${BASE}/admins/library/files/${encodeURIComponent(fileId)}/delete`, { method: 'POST' });
    }

    if (res.ok) {
      if (window.Swal && Swal.fire) await Swal.fire({ icon: 'success', title: 'تم الحذف', timer: 1200, showConfirmButton: false });
      await loadFiles();
    } else {
      const msg = (res.data && (res.data.error || res.data.message)) || 'فشل حذف الملف.';
      if (window.Swal && Swal.fire) Swal.fire({ icon: 'error', title: 'خطأ', text: msg });
      else alert(msg);
    }
  }

  // ======= تعبئة فلاتر الجدول (صفوف ثم مواد) =======
  async function loadListGrades() {
    if (!gradeFilter) return;
    const { ok, data } = await GET_JSON(`${BASE}/admins/library/meta/grades`);
    const items = ok ? extractItems(data) : [];
    for (const g of items) {
      const id   = g.id ?? g.grade_id ?? g.gradeId;
      const name = g.name_ar ?? g.name ?? g.title ?? `الصف ${g.level ?? ''}`;
      if (id != null) {
        const opt = document.createElement('option');
        opt.value = id; opt.textContent = name;
        gradeFilter.appendChild(opt);
      }
    }
  }
  async function loadListSubjects(gradeId) {
    if (!subjectFilter) return;
    subjectFilter.innerHTML = '<option value="">الكل</option>';
    if (!gradeId) return;

    const { ok, data } = await GET_JSON(`${BASE}/admins/library/meta/grades/${encodeURIComponent(gradeId)}/subjects`);
    const items = ok ? extractItems(data) : [];
    for (const s of items) {
      const id   = s.id ?? s.subject_id ?? s.subjectId;
      const name = s.name_ar ?? s.name ?? s.title ?? 'مادة';
      if (id != null) {
        const opt = document.createElement('option');
        opt.value = id; opt.textContent = name;
        subjectFilter.appendChild(opt);
      }
    }
  }

  // ===== ربط أحداث فلاتر الجدول + التخزين =====
  if (gradeFilter) {
    gradeFilter.addEventListener('change', async () => {
      saveLS(LS_KEYS.grade, gradeFilter.value || '');
      await loadListSubjects(gradeFilter.value);
      if (subjectFilter) subjectFilter.value = '';
      await loadFilesDebounced();
    });
  }
  if (subjectFilter) subjectFilter.addEventListener('change', loadFilesDebounced);
  if (semesterFilter) {
    semesterFilter.addEventListener('change', () => {
      saveLS(LS_KEYS.semester, semesterFilter.value || '');
      loadFilesDebounced();
    });
  }
  if (refreshBtn) refreshBtn.addEventListener('click', loadFiles);

  if (tableBody) {
    tableBody.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action="delete"]');
      if (!btn) return;
      const fid = btn.getAttribute('data-file-id');
      if (fid) deleteFile(fid);
    });
  }

  // ======= Bootstrap لقسم القائمة (عند وجود الجدول) =======
  (async function bootstrapList() {
    if (!tableBody) return;

    await loadListGrades();

    const savedGrade = readLS(LS_KEYS.grade);
    if (savedGrade && hasOption(gradeFilter, savedGrade)) {
      gradeFilter.value = savedGrade;
      await loadListSubjects(savedGrade);
      if (subjectFilter) subjectFilter.value = '';
    }

    const savedSem = readLS(LS_KEYS.semester);
    if (savedSem && hasOption(semesterFilter, savedSem)) {
      semesterFilter.value = savedSem;
    }

    await loadFiles();
  })();
}

// ✅ تنسيق التاريخ (Asia/Amman)
function formatDateTime(iso) {
  if (!iso) return '-';
  try {
    const str = typeof iso === 'string' && iso.includes(' ') ? iso.replace(' ', 'T') : iso;
    const d = new Date(str);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleString('ar-JO', {
      timeZone: 'Asia/Amman',
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit'
    });
  } catch { return iso; }
}

/* ربط مع admin_router.js — حمّل هذا السكربت فقط عندما data-section="library-upload" */
if (window.SECTION_MODULES) {
  window.SECTION_MODULES['library-upload'] = { init };
}
