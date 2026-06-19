/* sections/taskdetails.js - Task Details (SWR + AR + media UX + audio waves + poster/doc cache + monthly caps) */
'use strict';

const API_BASE = '/students';

/* ========= Config ========= */
const VIDEO_THUMBNAIL_AT_SECONDS = 30;     // زمن الفريم لبوستر الفيديو
const VIDEO_FALLBACK_PERCENT = 0.10;   // fallback لو الفيديو قصير
const TASK_CACHE_TTL_MS = 3 * 24 * 60 * 60 * 1000; // 3 days

// PDF.js (CDN) — عند الحاجة فقط
const PDFJS_VER = '3.11.174';
const PDFJS_CDN = `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${PDFJS_VER}/pdf.min.js`;
const PDFJS_WORKER = `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${PDFJS_VER}/pdf.worker.min.js`;

// حدود التنزيل الشهرية المحلية (fallback عندما لا يتوفر check_url من الخادم)
const LOCAL_MONTHLY_FILE_LIMIT = 3;

/* ========= State ========= */
let _state = { taskId: null, courseOfferingId: null, weeklyContentId: null, data: null };
let _abortCtrl = null;
let _currentTaskId = null;

/* ========= SWR Cache (localStorage) ========= */
const TASK_CACHE_NS = 'taskctx:v3:';
const TaskCache = {
  key: id => `${TASK_CACHE_NS}${id}`,
  get(id) {
    try {
      const raw = localStorage.getItem(this.key(id)); if (!raw) return null;
      const obj = JSON.parse(raw); if (!obj || !obj.ts || !obj.data) return null;
      if (Date.now() - obj.ts > TASK_CACHE_TTL_MS) return null;
      return obj.data;
    } catch { return null; }
  },
  set(id, data) {
    try {
      const lean = pickLeanTaskContext(data);
      localStorage.setItem(this.key(id), JSON.stringify({ ts: Date.now(), data: lean }));
    } catch { }
  },
  clearAll() {
    try {
      Object.keys(localStorage).forEach(k => { if (k.startsWith(TASK_CACHE_NS)) localStorage.removeItem(k); });
    } catch { }
  }
};
try { window.__TASK_CACHE__ = TaskCache; } catch { }

/* ========= اختيار نسخة خفيفة للتخزين ========= */
function pickLeanTaskContext(ctx) {
  if (!ctx) return null;
  return {
    course: {
      course_offering_id: ctx.course?.course_offering_id,
      course_id: ctx.course?.course_id,
      course_name: ctx.course?.course_name,
      course_code: ctx.course?.course_code,
      university_id: ctx.course?.university_id ?? null,

    },
    week: {
      weekly_content_id: ctx.week?.weekly_content_id,
      week_number: ctx.week?.week_number,
      week_title: ctx.week?.week_title,
      publish_date: ctx.week?.publish_date,
    },
    task: {
      task_id: ctx.task?.task_id,
      task_title: ctx.task?.task_title,
      task_description: ctx.task?.task_description,
      task_type_name: ctx.task?.task_type_name,
      task_type_id: ctx.task?.task_type_id ?? null,

      visible_from_date: ctx.task?.visible_from_date,
      due_date: ctx.task?.due_date,
      is_for_all_specialties: ctx.task?.is_for_all_specialties,
      user_premium: ctx.task?.user_premium,
      files: Array.isArray(ctx.task?.files) ? ctx.task.files.map(f => ({
        file_id: f.file_id,
        file_name: f.file_name,
        mime_type: f.mime_type,
        file_size_bytes: f.file_size_bytes,
        file_description: f.file_description,
        file_is_premium: f.file_is_premium,
        can_stream: f.can_stream,
        can_download: f.can_download,
        stream_url: f.stream_url || null,
        download_url: f.download_url || null,
        check_url: f.check_url || null,
        is_media: f.is_media,
        is_pdf: f.is_pdf,
        poster_url: f.poster_url || null,
        preview_pages: Array.isArray(f.preview_pages) ? f.preview_pages.slice(0, 4) : null,
        preview_page_count: typeof f.preview_page_count === 'number' ? f.preview_page_count : null
      })) : []
    },
    siblings: {
      index: ctx.siblings?.index ?? null,
      prev_task_id: ctx.siblings?.prev_task_id ?? null,
      next_task_id: ctx.siblings?.next_task_id ?? null,
      list: Array.isArray(ctx.siblings?.list) ? ctx.siblings.list.map(s => ({
        task_id: s.task_id, task_title: s.task_title, task_type_name: s.task_type_name
      })) : []
    }
  };
}

/* ========= DOM helpers ========= */
const qs = id => document.getElementById(id);
const safe = t => String(t ?? '');

/* ========= URL / Deeplink ========= */
function parseFromURL() {
  const q = new URLSearchParams(window.location.search || '');
  let task = parseInt(q.get('task') || '', 10);
  let co = parseInt(q.get('co') || '', 10);
  let week = parseInt(q.get('week') || '', 10);
  if (task) return { taskId: task, courseOfferingId: co || 0, weeklyContentId: week || 0 };

  const rawHash = (window.location.hash || '').replace(/^#\/?/, '');
  if (rawHash) {
    const params = rawHash.includes('?') ? rawHash.split('?')[1] : rawHash;
    const h = new URLSearchParams(params);
    task = parseInt(h.get('task') || '', 10);
    co = parseInt(h.get('co') || '', 10);
    week = parseInt(h.get('week') || '', 10);
    if (task) return { taskId: task, courseOfferingId: co || 0, weeklyContentId: week || 0 };
  }

  try {
    const saved = JSON.parse(localStorage.getItem('last_task_details') || 'null');
    if (saved && saved.taskId) {
      return {
        taskId: Number(saved.taskId) || 0,
        courseOfferingId: Number(saved.courseOfferingId) || 0,
        weeklyContentId: Number(saved.weeklyContentId) || 0
      };
    }
  } catch { }
  return { taskId: 0, courseOfferingId: 0, weeklyContentId: 0 };
}
function syncURLHash(taskId, coId, weekId) {
  const desired = `#/task-details?task=${encodeURIComponent(taskId)}&co=${encodeURIComponent(coId || 0)}&week=${encodeURIComponent(weekId || 0)}`;
  if (window.location.hash !== desired) {
    if (history.replaceState) {
      history.replaceState(null, '', `${window.location.pathname}${window.location.search}${desired}`);
    } else {
      window.location.hash = desired;
    }
  }
}

/* ========= Cleanup registry & URL reset ========= */
const _cleanups = new Set();
function registerCleanup(fn) { if (typeof fn === 'function') _cleanups.add(fn); }
function runCleanups() { for (const fn of _cleanups) { try { fn(); } catch { } } _cleanups.clear(); }

function resetStudentsURLBase() {
  try {
    const path = window.location.pathname;
    const anchor = '/students';
    const i = path.indexOf(anchor);
    if (i !== -1) {
      const base = path.slice(0, i + anchor.length);
      if (location.pathname !== base || location.search || location.hash) {
        history.replaceState(null, '', base);
      }
    } else {
      history.replaceState(null, '', location.pathname);
    }
  } catch { }
}

/* ========= Video Poster Cache ========= */
const POSTER_CACHE_NS = 'td:poster:v1:';
const POSTER_INDEX_KEY = POSTER_CACHE_NS + '__i';
const POSTER_TTL_MS = 7 * 24 * 60 * 60 * 1000;
const POSTER_MAX_ITEMS = 40;

const PosterCache = {
  key(idOrUrl) { return POSTER_CACHE_NS + String(idOrUrl); },
  _loadIndex() { try { return JSON.parse(localStorage.getItem(POSTER_INDEX_KEY) || '[]'); } catch { return []; } },
  _saveIndex(list) { try { localStorage.setItem(POSTER_INDEX_KEY, JSON.stringify(list)); } catch { } },
  _touchKey(k) {
    const idx = this._loadIndex();
    const now = Date.now();
    const filtered = idx.filter(x => x.k !== k);
    filtered.unshift({ k: k, ts: now });
    while (filtered.length > POSTER_MAX_ITEMS) {
      const victim = filtered.pop();
      try { localStorage.removeItem(victim.k); } catch { }
    }
    this._saveIndex(filtered);
  },
  getByFile(file) {
    const k = this.key(file.file_id || file.stream_url || file.file_name || '');
    try {
      const raw = localStorage.getItem(k); if (!raw) return null;
      const obj = JSON.parse(raw);
      if (!obj || !obj.ts || !obj.data) return null;
      if (Date.now() - obj.ts > POSTER_TTL_MS) { try { localStorage.removeItem(k); } catch { } return null; }
      this._touchKey(k);
      return obj.data;          // DataURL
    } catch { return null; }
  },
  setByFile(file, dataUrl) {
    const k = this.key(file.file_id || file.stream_url || file.file_name || '');
    try {
      localStorage.setItem(k, JSON.stringify({ ts: Date.now(), data: dataUrl }));
      this._touchKey(k);
    } catch {
      try {
        const idx = this._loadIndex();
        while (idx.length > 0) {
          const victim = idx.pop();
          localStorage.removeItem(victim.k);
          if (localStorage.getItem(k) === null) {
            localStorage.setItem(k, JSON.stringify({ ts: Date.now(), data: dataUrl }));
            idx.unshift({ k, ts: Date.now() });
            this._saveIndex(idx);
            break;
          }
        }
      } catch { }
    }
  }
};
try { window.__POSTER_CACHE__ = PosterCache; } catch { }

/* ========= Doc Preview Cache ========= */
const DOC_PREVIEW_NS = 'td:docprev:v1:';
const DOC_PREVIEW_IDX = DOC_PREVIEW_NS + '__i';
const DOC_PREVIEW_TTL = 7 * 24 * 60 * 60 * 1000;
const DOC_PREVIEW_MAX = 60;

const DocPreviewCache = {
  key(file) { return DOC_PREVIEW_NS + String(file?.file_id || file?.file_name || file?.stream_url || ''); },
  _loadIndex() { try { return JSON.parse(localStorage.getItem(DOC_PREVIEW_IDX) || '[]'); } catch { return []; } },
  _saveIndex(list) { try { localStorage.setItem(DOC_PREVIEW_IDX, JSON.stringify(list)); } catch { } },
  _touchKey(k) {
    const idx = this._loadIndex();
    const now = Date.now();
    const filtered = idx.filter(x => x.k !== k);
    filtered.unshift({ k, ts: now });
    while (filtered.length > DOC_PREVIEW_MAX) {
      const victim = filtered.pop();
      try { localStorage.removeItem(victim.k); } catch { }
    }
    this._saveIndex(filtered);
  },
  get(file) {
    const k = this.key(file);
    try {
      const raw = localStorage.getItem(k); if (!raw) return null;
      const obj = JSON.parse(raw);
      if (!obj || !obj.ts || !obj.pages) return null;
      if (Date.now() - obj.ts > DOC_PREVIEW_TTL) { try { localStorage.removeItem(k); } catch { } return null; }
      this._touchKey(k);
      return obj; // { pages: [dataUrl...], pageCount }
    } catch { return null; }
  },
  set(file, data) {
    const k = this.key(file);
    try {
      localStorage.setItem(k, JSON.stringify({ ts: Date.now(), pages: data.pages || [], pageCount: data.pageCount || data.pages?.length || 0 }));
      this._touchKey(k);
    } catch {
      // إخلاء LRU عند الامتلاء
      try {
        const idx = this._loadIndex();
        while (idx.length > 0) {
          const victim = idx.pop();
          localStorage.removeItem(victim.k);
          if (localStorage.getItem(k) === null) {
            localStorage.setItem(k, JSON.stringify({ ts: Date.now(), pages: data.pages || [], pageCount: data.pageCount || data.pages?.length || 0 }));
            idx.unshift({ k, ts: Date.now() });
            this._saveIndex(idx);
            break;
          }
        }
      } catch { }
    }
  }
};
try { window.__DOC_PREVIEW_CACHE__ = DocPreviewCache; } catch { }

/* ========= Downloads monthly caps (client fallback) ========= */
function monthKey(d) { return d.getFullYear().toString() + String(d.getMonth() + 1).padStart(2, '0'); }
function firstDayOfNextMonth() {
  const d = new Date();
  const y = d.getFullYear();
  const m = d.getMonth();
  const next = new Date(y, m + 1, 1);
  return next;
}
function fmtDateArabic(d) {
  try { return new Intl.DateTimeFormat('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' }).format(d); }
  catch { return d.toISOString().slice(0, 10); }
}
function localMonthlyCountKey(fileId) {
  const mk = monthKey(new Date());
  return `td:dls:${fileId}:${mk}`;
}
function getLocalMonthlyCount(fileId) {
  try { return Number(localStorage.getItem(localMonthlyCountKey(fileId)) || '0'); } catch { return 0; }
}
function incLocalMonthlyCount(fileId) {
  try {
    const k = localMonthlyCountKey(fileId);
    const n = Number(localStorage.getItem(k) || '0') + 1;
    localStorage.setItem(k, String(n));
  } catch { }
}

/* ========= Network ========= */
async function fetchTaskContextNetwork(taskId, { signal } = {}) {
  const res = await fetch(`${API_BASE}/course-content/task-context/${encodeURIComponent(taskId)}?_=${Date.now()}`, {
    credentials: 'same-origin', signal
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const js = await res.json();
  if (!js.success) throw new Error(js.message || 'Failed');
  return js.data;
}
async function getTaskContext(taskId, { force = false, revalidate = true, signal } = {}) {
  const cached = !force ? TaskCache.get(taskId) : null;
  if (cached) {
    if (revalidate) {
      fetchTaskContextNetwork(taskId, { signal }).then(fresh => {
        TaskCache.set(taskId, fresh);
        if (Number(_currentTaskId) === Number(taskId)) renderAll(fresh);
      }).catch(() => { });
    }
    return cached;
  }
  const fresh = await fetchTaskContextNetwork(taskId, { signal });
  TaskCache.set(taskId, fresh);
  return fresh;
}
function prefetchNeighbors(ctx) {
  const prev = ctx?.siblings?.prev_task_id;
  const next = ctx?.siblings?.next_task_id;
  if (prev) getTaskContext(prev, { revalidate: false }).catch(() => { });
  if (next) getTaskContext(next, { revalidate: false }).catch(() => { });
}

/* ========= Lifecycle ========= */
export async function init() {
  injectOnceStyles();

  const dl = window.__TASK_DEEPLINK || {};
  try { delete window.__TASK_DEEPLINK; } catch { }

  const fromUrl = parseFromURL();

  _state.taskId = Number(dl.task_id || fromUrl.taskId || 0);
  _state.courseOfferingId = Number(dl.course_offering_id || fromUrl.courseOfferingId || 0);
  _state.weeklyContentId = Number(dl.weekly_content_id || fromUrl.weeklyContentId || 0);

  if (!_state.taskId) { renderError('لم يتم تحديد مهمة لعرض تفاصيلها.'); return; }

  try {
    localStorage.setItem('last_task_details', JSON.stringify({
      taskId: _state.taskId, courseOfferingId: _state.courseOfferingId, weeklyContentId: _state.weeklyContentId
    }));
  } catch { }

  syncURLHash(_state.taskId, _state.courseOfferingId, _state.weeklyContentId);

  bindBaseEvents();
  bindRefreshHook();

  await navigateToTask(_state.taskId);
}
export function cleanup() {
  try { _abortCtrl?.abort(); } catch { }
  runCleanups();
  closePdfModal();
  closeImageZoom();

  _state = { taskId: null, courseOfferingId: null, weeklyContentId: null, data: null };
  _currentTaskId = null;
}

/* ========= Navigation ========= */
async function navigateToTask(taskId) {
  if (!taskId) return;

  if (Number(taskId) === Number(_currentTaskId) && _state.data) {
    renderAll(_state.data);
    return;
  }

  _currentTaskId = Number(taskId);
  showLoading();

  runCleanups();
  closePdfModal();
  closeImageZoom();

  try {
    _abortCtrl?.abort();
    _abortCtrl = new AbortController();

    const ctx = await getTaskContext(_currentTaskId, { revalidate: true, signal: _abortCtrl.signal });
    _state.data = ctx;

    syncURLHash(_currentTaskId,
      _state.courseOfferingId || ctx.course?.course_offering_id || 0,
      _state.weeklyContentId || ctx.week?.weekly_content_id || 0
    );

    renderAll(ctx);
    prefetchNeighbors(ctx);

    try {
      localStorage.setItem('last_task_details', JSON.stringify({
        taskId: _currentTaskId,
        courseOfferingId: _state.courseOfferingId || ctx.course?.course_offering_id || 0,
        weeklyContentId: _state.weeklyContentId || ctx.week?.weekly_content_id || 0
      }));
    } catch { }
  } catch (err) {
    console.error(err);
    renderError('تعذّر تحميل تفاصيل المهمة');
  } finally {
    hideLoading();
  }
}

/* ========= Render ========= */
function renderAll(data) {
  const title = qs('td-title');
  const meta = qs('td-meta');
  if (title) title.textContent = safe(data.task?.task_title || 'تفاصيل المهمة');

  if (meta) {
    const type = safe(data.task?.task_type_name || 'مهمة');
    const due = data.task?.due_date ? formatDate(data.task.due_date) : '—';
    meta.innerHTML = `
      <span><i class="fas fa-tag"></i> ${type}</span>
      <span style="margin-inline:8px;color:#bbb">|</span>
      <span><i class="far fa-calendar-alt"></i> ${due}</span>
    `;
  }

  const weekName = qs('td-week-name');
  if (weekName) weekName.textContent = safe(data.week?.week_title || `الأسبوع ${data.week?.week_number ?? ''}`);

  renderSiblings(data.siblings, data.task?.task_id);

  const desc = qs('td-desc');
  if (desc) {
    const text = safe(data.task?.task_description || '');
    desc.innerHTML = `
      <div class="td-desc-head"><i class="fas fa-align-left"></i> الوصف</div>
      <div class="td-desc-body">${paragraphizeAndLink(text)}</div>
    `;
  }

  const dates = qs('td-dates');
  if (dates) {
    dates.innerHTML = `
      <h3 class="td-block-title"><i class="far fa-calendar"></i> المواعيد</h3>
      <ul class="td-meta-list">
        <li><strong>متاح من:</strong> ${data.task?.visible_from_date ? formatDate(data.task.visible_from_date) : '—'}</li>
        <li><strong>تاريخ التسليم:</strong> ${data.task?.due_date ? formatDate(data.task.due_date) : '—'}</li>
      </ul>
    `;
  }

  renderFiles(data.task?.files || []);

  // ===== (الإضافة الوحيدة المطلوبة) استدعاء موديول خطة الحصة بعد توفر بيانات المهمة =====
  try {
    mountAiModalForTask({
      task_id: Number(data.task?.task_id || 0),
      university_id: Number(data.course?.university_id || 0),
      task_type_id: Number(data.task?.task_type_id || data.task?.task_types_id || 0)
    });
  } catch (e) { /* صامت */ }

}


function renderSiblings(siblings, currentId) {
  const ul = qs('td-siblings'); if (!ul) return;
  ul.innerHTML = '';

  (siblings?.list || []).forEach(s => {
    const li = document.createElement('li');
    const btn = document.createElement('button');
    btn.className = 'td-sibling';
    btn.textContent = s.task_title || `مهمة #${s.task_id}`;
    if (Number(s.task_id) === Number(currentId)) btn.classList.add('active');
    btn.addEventListener('click', e => { e.preventDefault(); navigateToTask(s.task_id); }, { passive: false });
    li.appendChild(btn);
    ul.appendChild(li);
  });

  const prev = qs('td-prev'), next = qs('td-next');
  if (prev) {
    prev.disabled = !siblings?.prev_task_id;
    prev.onclick = e => { e.preventDefault(); if (siblings?.prev_task_id) navigateToTask(siblings.prev_task_id); };
  }
  if (next) {
    next.disabled = !siblings?.next_task_id;
    next.onclick = e => { e.preventDefault(); if (siblings?.next_task_id) navigateToTask(siblings.next_task_id); };
  }
}

/* ========= Preflight (check_url) ========= */
async function preloadDownloadCaps(btn, file) {
  if (!btn || !file) return;

  let checkUrl = file.check_url;
  if (!checkUrl && file.file_id) {
    // مسار احتياطي يتوافق مع FileGateway::check
    checkUrl = `/api/files/${encodeURIComponent(file.file_id)}/check?action=download`;
  }
  if (!checkUrl) return;

  try {
    const r = await fetch(checkUrl, { credentials: 'same-origin' });
    if (!r.ok) return;
    const js = await r.json();

    // إن غير مسموح — عطّل الزر
    if (js?.allowed === false) {
      disableBtn(btn);
      // ملاحظة: التنبيه يتم عند النقر (handleDownloadClick) لرسالة أوضح
    }
  } catch {
    // تجاهل — القرار النهائي عند النقر
  }
}
function disableBtn(btn) {
  btn.classList.add('disabled');
  btn.setAttribute('aria-disabled', 'true');
  btn.style.opacity = '0.7';
  btn.style.pointerEvents = 'none';
}

/* ========= Render files/media ========= */
function renderFiles(files) {
  const mediaWrap = qs('td-media');
  const mediaGrid = qs('td-media-grid');
  const filesWrap = qs('td-files');       // سيتم إخفاؤه بالكامل
  const filesList = qs('td-files-list');  // لم يعد مستخدماً
  const cta = qs('td-upgrade-cta');

  let hasAny = false, hasLocked = false;

  if (mediaGrid) mediaGrid.innerHTML = '';

  // ✅ نُلغي منطقة المرفقات كليّاً
  if (filesWrap) filesWrap.hidden = true;
  if (filesList) filesList.innerHTML = '';

  (files || []).forEach(f => {
    const canStream = Number(f.can_stream || 0) === 1;
    const canDownload = Number(f.can_download || 0) === 1;
    const isMedia = Number(f.is_media || 0) === 1;
    const isPdf = Number(f.is_pdf || 0) === 1;
    const mime = (f.mime_type || '').toLowerCase();
    const stream = f.stream_url || '';
    const dl = f.download_url || `${API_BASE}/course-content/download/${encodeURIComponent(f.file_id)}`;

    // قفل الوسائط = لا يُسمح بالبث
    const mediaLocked = isMedia && !(canStream && stream);
    // قفل الملفات المميّزة في حال منع التنزيل
    const fileLocked = !isMedia && Number(f.file_is_premium || 0) === 1 && !canDownload;

    // 1) صور/فيديو/صوت — كما كانت
    if (isMedia) {
      hasAny = true;
      if (!mediaLocked) {
        if (mime.startsWith('image/')) {
          const box = buildImageBox(stream, f.file_name);
          mediaGrid?.appendChild(box);
        } else if (mime.startsWith('video/')) {
          const box = buildLazyVideoBox({ file_id: f.file_id, stream_url: stream, poster_url: f.poster_url, file_name: f.file_name });
          mediaGrid?.appendChild(box);
        } else if (mime.startsWith('audio/')) {
          const box = buildAudioBox({ stream_url: stream, file_name: f.file_name });
          mediaGrid?.appendChild(box);
        }
      } else {
        hasLocked = true;
        const lockedCard = buildLockedMediaCard({ file_name: f.file_name, mime_type: f.mime_type });
        mediaGrid?.appendChild(lockedCard);
      }
      return;
    }

    // 2) مستندات: PDF/Word/Excel → «كارد» معاينة كصور
    const isOfficeDoc = mime.includes('msword') || mime.includes('vnd.openxmlformats-officedocument.wordprocessingml') ||
      mime.includes('vnd.ms-excel') || mime.includes('vnd.openxmlformats-officedocument.spreadsheetml');
    if (isPdf || isOfficeDoc) {
      // إن كان الملف مميزاً والتنزيل ممنوع — نسمح بالمعاينة فقط إن البث مسموح
      if ((isPdf && (!canStream || !stream)) || (!isPdf && !canStream)) {
        hasLocked = true;
        const lockedCard = buildLockedDocCard({ file: f, message: 'المعاينة متاحة للمشتركين' });
        mediaGrid?.appendChild(lockedCard);
        return;
      }

      hasAny = true;
      const card = buildDocPreviewCard(f, { isPdf, dlUrl: dl, canDownload });
      mediaGrid?.appendChild(card);
      return;
    }

    // 3) بقية الملفات (zip …إلخ) — نعرض بطاقة بسيطة للتنزيل فقط
    hasAny = true;
    const simple = buildSimpleDownloadCard(f, dl, canDownload);
    mediaGrid?.appendChild(simple);
  });

  if (mediaWrap) mediaWrap.hidden = !hasAny;
  if (cta) cta.hidden = !hasLocked; // يظهر فقط إن وُجد محتوى مقفول فعلاً
}

/* ========= Image (modern preview + zoom) ========= */
function buildImageBox(src, name) {
  const box = document.createElement('div');
  box.className = 'media media-image';
  const img = document.createElement('img');
  img.src = src; img.alt = name || 'image';
  img.loading = 'lazy'; img.decoding = 'async';
  img.className = 'zoomable-image';
  img.onerror = () => { img.replaceWith(document.createTextNode('تعذر تحميل الصورة')); };
  img.addEventListener('load', () => { try { if (img.naturalWidth && img.naturalHeight) { box.style.aspectRatio = `${img.naturalWidth}/${img.naturalHeight}`; } } catch { } });
  img.addEventListener('click', e => { e.preventDefault(); openImageZoomWithNav([src], 0, name || 'image'); }, { passive: false });
  box.appendChild(img);
  box.appendChild(buildCaption(name));
  return box;
}

/* ========= Video (poster from specific time + lazy load + cache) ========= */
async function generateVideoThumbnailAt(src, seconds, timeoutMs = 3500) {
  return new Promise((resolve) => {
    let done = false; const video = document.createElement('video');
    try {
      const u = new URL(src, window.location.origin);
      if (u.origin !== window.location.origin) video.crossOrigin = 'anonymous';
    } catch { }
    video.preload = 'metadata'; video.muted = true; video.playsInline = true; video.controls = false;
    video.style.position = 'fixed'; video.style.opacity = '0'; video.style.pointerEvents = 'none';
    video.style.width = '1px'; video.style.height = '1px';
    document.body.appendChild(video);

    const cleanup = (res = null) => { if (done) return; done = true; try { video.pause(); video.removeAttribute('src'); video.load(); video.remove(); } catch { } resolve(res); };
    const to = setTimeout(() => cleanup(null), timeoutMs);

    video.addEventListener('error', () => { clearTimeout(to); cleanup(null); }, { once: true });
    video.addEventListener('loadedmetadata', () => {
      let t = seconds;
      try {
        const dur = Math.max(0, video.duration || 0);
        if (!isFinite(t) || t <= 0) {
          const perc = Math.max(0.01, VIDEO_FALLBACK_PERCENT);
          t = Math.min(dur * perc, dur ? dur - 0.1 : 0.1);
        } else {
          t = Math.min(t, dur ? dur - 0.1 : t);
        }
      } catch { t = seconds || 0.1; }

      const onSeeked = () => {
        video.removeEventListener('seeked', onSeeked);
        try {
          let w = video.videoWidth || 1280;
          let h = video.videoHeight || 720;
          const MAX_W = 640;
          if (w > MAX_W) { const r = MAX_W / w; w = Math.round(w * r); h = Math.round(h * r); }
          const c = document.createElement('canvas'); c.width = w; c.height = h;
          const ctx = c.getContext('2d');
          ctx.drawImage(video, 0, 0, w, h);
          const dataUrl = c.toDataURL('image/jpeg', 0.82);
          clearTimeout(to);
          cleanup(dataUrl);
        } catch {
          clearTimeout(to); cleanup(null);
        }
      };
      video.addEventListener('seeked', onSeeked, { once: true });
      try { video.currentTime = Math.max(0.1, t); } catch { }
    }, { once: true });
    video.src = src;
  });
}

function buildLazyVideoBox(file) {
  const box = document.createElement('div'); box.className = 'media media-video';

  const poster = document.createElement('div'); poster.className = 'video-poster';
  const img = document.createElement('img'); img.alt = file.file_name || 'video'; img.style.display = 'none';
  poster.appendChild(img);

  const overlay = document.createElement('div'); overlay.className = 'play-overlay';
  overlay.innerHTML = `<div class="play-btn"><i class="fas fa-play"></i></div>`;
  poster.appendChild(overlay);

  const setAR = (w, h) => { if (w && h) { box.style.aspectRatio = `${w}/${h}`; } };

  if (file.poster_url) {
    img.src = file.poster_url; img.style.display = '';
    img.addEventListener('load', () => { setAR(img.naturalWidth, img.naturalHeight); });
  } else {
    const cached = PosterCache.getByFile(file);
    if (cached) {
      img.src = cached; img.style.display = '';
      img.addEventListener('load', () => { setAR(img.naturalWidth, img.naturalHeight); });
    } else {
      generateVideoThumbnailAt(file.stream_url, VIDEO_THUMBNAIL_AT_SECONDS).then(dataUrl => {
        if (dataUrl) {
          try { PosterCache.setByFile(file, dataUrl); } catch { }
          img.src = dataUrl; img.style.display = '';
          img.addEventListener('load', () => { setAR(img.naturalWidth, img.naturalHeight); });
        }
      }).catch(() => { });
    }
  }

  poster.addEventListener('click', e => {
    e.preventDefault();
    const v = document.createElement('video');
    v.controls = true; v.playsInline = true; v.preload = 'none'; v.src = file.stream_url;
    v.setAttribute('controlsList', 'nodownload noplaybackrate'); v.setAttribute('disablePictureInPicture', '');
    v.addEventListener('loadedmetadata', () => { setAR(v.videoWidth, v.videoHeight); });
    v.onerror = () => { v.replaceWith(document.createTextNode('تعذر تشغيل الفيديو')); };
    box.replaceChild(v, poster);
    try { v.play().catch(() => { }); } catch { }

    registerCleanup(() => { try { v.pause(); v.removeAttribute('src'); v.load(); } catch { } });
  }, { passive: false });

  box.appendChild(poster);
  box.appendChild(buildCaption(file.file_name));
  return box;
}

/* ========= Audio ========= */
function buildAudioBox(file) {
  const wrap = document.createElement('div');
  wrap.className = 'audio-box';

  const title = document.createElement('div');
  title.className = 'audio-title';
  const name = (file.file_name || '').trim() || 'مقطع صوتي';
  title.textContent = name;
  title.title = name;
  title.setAttribute('aria-label', name);

  const wave = document.createElement('div');
  wave.className = 'audio-visual';
  const BARS = 42;
  for (let i = 0; i < BARS; i++) { const bar = document.createElement('span'); bar.style.setProperty('--i', String(i)); wave.appendChild(bar); }

  const ctrl = document.createElement('div');
  ctrl.className = 'audio-controls';
  const audio = document.createElement('audio');
  audio.controls = true;
  audio.preload = 'none';
  audio.src = file.stream_url;
  audio.setAttribute('controlsList', 'nodownload noplaybackrate');
  audio.setAttribute('preload', 'none');
  ctrl.appendChild(audio);

  let rafId = null; let stopped = false; let start = performance.now();
  const animate = (ts) => {
    if (stopped) return;
    const t = (ts - start) / 1000;
    const playing = wrap.classList.contains('playing');
    const baseAmp = playing ? 28 : 12;
    const speed = playing ? 3.0 : 1.1;
    let idx = 0;
    for (const bar of wave.children) {
      const phase = idx * 0.35;
      const h = 10 + baseAmp * (0.5 + 0.5 * Math.sin(phase + t * speed));
      bar.style.height = `${Math.max(8, Math.min(56, h))}px`;
      idx++;
    }
    rafId = requestAnimationFrame(animate);
  };
  rafId = requestAnimationFrame(animate);

  audio.addEventListener('play', () => wrap.classList.add('playing'));
  audio.addEventListener('pause', () => wrap.classList.remove('playing'));
  audio.addEventListener('ended', () => wrap.classList.remove('playing'));
  audio.onerror = () => { ctrl.replaceChildren(document.createTextNode('تعذر تشغيل الصوت')); };

  registerCleanup(() => {
    try {
      stopped = true;
      if (rafId) cancelAnimationFrame(rafId);
      audio.pause();
      audio.src = '';
    } catch { }
  });

  wrap.appendChild(wave);
  wrap.appendChild(ctrl);
  wrap.appendChild(title);

  return wrap;
}

/* ========= Locked cards ========= */
function buildLockedMediaCard({ file_name, mime_type } = {}) {
  const box = document.createElement('div');
  box.className = 'media locked';
  const inner = document.createElement('div');
  inner.className = 'lock-overlay';
  inner.innerHTML = `<i class="fas fa-lock"></i> محتوى وسائط — مخصص للمشتركين`;
  const cap = buildCaption(file_name || (mime_type?.startsWith('video/') ? 'فيديو' :
    mime_type?.startsWith('audio/') ? 'صوت' : 'صورة'));
  box.appendChild(inner);
  box.appendChild(cap);
  return box;
}
function buildLockedDocCard({ file, message }) {
  const box = document.createElement('div');
  box.className = 'doc-card locked';
  box.innerHTML = `
    <div class="doc-header">
      <div class="doc-title" title="${escapeHtml(file.file_name || 'ملف')}">${escapeHtml(file.file_name || 'ملف')}</div>
      <button class="glass-btn" disabled title="غير متاح"><i class="fas fa-download"></i></button>
    </div>
    <div class="doc-body">
      <div class="doc-empty">
        <i class="${getFileIcon(file.mime_type)}"></i>
        <div>${escapeHtml(message || 'المعاينة غير متاحة')}</div>
      </div>
    </div>`;
  return box;
}
function buildSimpleDownloadCard(file, dlUrl, canDownload) {
  const box = document.createElement('div');
  box.className = 'doc-card';
  box.innerHTML = `
    <div class="doc-header">
      <div class="doc-title" title="${escapeHtml(file.file_name || 'ملف')}">${escapeHtml(file.file_name || 'ملف')}</div>
      <button class="glass-btn"><i class="fas fa-download"></i></button>
    </div>
    <div class="doc-body">
      <div class="doc-empty">
        <i class="${getFileIcon(file.mime_type)}"></i>
        <div>لا تتوفر معاينة</div>
      </div>
    </div>`;
  const btn = box.querySelector('.glass-btn');
  btn.addEventListener('click', e => {
    e.preventDefault();
    handleDownloadClick(file, dlUrl);
  }, { passive: false });
  if (!canDownload) disableBtn(btn);
  // فحص مسبق إن وُجد
  preloadDownloadCaps(btn, file).catch(() => { });
  return box;
}

/* ========= Doc Preview Card (PDF/Word/Excel) ========= */
function buildDocPreviewCard(file, { isPdf, dlUrl, canDownload }) {
  const box = document.createElement('div');
  box.className = 'doc-card';
  box.innerHTML = `
    <div class="doc-header">
      <div class="doc-title" title="${escapeHtml(file.file_name || 'ملف')}">${escapeHtml(file.file_name || 'ملف')}</div>
      <button class="glass-btn" title="تنزيل"><i class="fas fa-download"></i></button>
    </div>
    <div class="doc-body">
      <div class="doc-carousel">
        <div class="doc-slide doc-loading"><span class="spinner"></span></div>
      </div>
      <button class="nav-btn nav-prev" aria-label="السابق"><i class="fas fa-chevron-right"></i></button>
      <button class="nav-btn nav-next" aria-label="التالي"><i class="fas fa-chevron-left"></i></button>
    </div>`;

  const btnDl = box.querySelector('.glass-btn');
  btnDl.addEventListener('click', e => { e.preventDefault(); handleDownloadClick(file, dlUrl); }, { passive: false });
  if (!canDownload) disableBtn(btnDl);
  preloadDownloadCaps(btnDl, file).catch(() => { });

  const carousel = box.querySelector('.doc-carousel');
  const btnPrev = box.querySelector('.nav-prev');
  const btnNext = box.querySelector('.nav-next');

  let images = [];   // dataURLs
  let pageCount = 0;
  let idx = 0;

  const renderSlides = () => {
    carousel.innerHTML = '';
    if (!images.length) {
      const empty = document.createElement('div');
      empty.className = 'doc-slide doc-empty';
      empty.innerHTML = `<i class="${getFileIcon(file.mime_type)}"></i><div>لا تتوفر معاينة</div>`;
      carousel.appendChild(empty);
      btnPrev.style.display = 'none'; btnNext.style.display = 'none';
      return;
    }
    const img = document.createElement('img');
    img.src = images[idx]; img.alt = file.file_name || 'document';
    img.className = 'doc-img';
    img.loading = 'lazy'; img.decoding = 'async';
    img.addEventListener('click', e => {
      e.preventDefault();
      openImageZoomWithNav(images, idx, file.file_name || 'document');
    }, { passive: false });
    carousel.appendChild(img);

    // إن كان هناك صفحات إضافية (أكثر من 3) نعرض "صورة رابعة" افتراضية للتلميح
    if (pageCount > 3) {
      const hint = document.createElement('div');
      hint.className = 'more-badge';
      hint.innerHTML = `<i class="fas fa-file-download"></i> حمّل الملف لعرض المزيد`;
      carousel.appendChild(hint);
    }

    btnPrev.style.display = images.length > 1 ? '' : 'none';
    btnNext.style.display = images.length > 1 ? '' : 'none';
  };

  btnPrev.addEventListener('click', e => { e.preventDefault(); idx = (idx - 1 + images.length) % images.length; renderSlides(); }, { passive: false });
  btnNext.addEventListener('click', e => { e.preventDefault(); idx = (idx + 1) % images.length; renderSlides(); }, { passive: false });

  // تحميل المعاينات (من الكاش أو التوليد)
  const cached = DocPreviewCache.get(file);
  if (cached) {
    images = (cached.pages || []).slice(0, 3);
    pageCount = cached.pageCount || images.length;
    renderSlides();
  } else {
    // === PDF: نولّد عبر pdf.js | Office: نحاول Endpoint افتراضي ===
    (isPdf ? renderPdfPagesToImages(file) : fetchOfficePreviewImages(file))
      .then(res => {
        images = (res?.pages || []).slice(0, 3);
        pageCount = res?.pageCount || images.length || 0;
        if (images.length) {
          try { DocPreviewCache.set(file, { pages: images, pageCount }); } catch { }
        }
        renderSlides();
      })
      .catch(() => {
        images = [];
        pageCount = 0;
        renderSlides();
      });
  }

  return box;
}

/* ========= PDF.js helpers ========= */
function ensurePdfJs() {
  return new Promise((resolve, reject) => {
    if (window.pdfjsLib) {
      try { window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER; } catch { }
      return resolve(window.pdfjsLib);
    }
    const s = document.createElement('script');
    s.src = PDFJS_CDN; s.async = true; s.referrerPolicy = 'no-referrer';
    s.onload = () => { try { window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER; } catch { } resolve(window.pdfjsLib); };
    s.onerror = () => reject(new Error('Failed to load pdf.js'));
    document.head.appendChild(s);
  });
}
async function renderPdfPagesToImages(file) {
  try {
    const pdfjsLib = await ensurePdfJs();
    const res = await fetch(file.stream_url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('pdf fetch failed');
    const buf = await res.arrayBuffer();
    const loadingTask = pdfjsLib.getDocument({ data: buf });
    const doc = await loadingTask.promise;

    const pages = [];
    const pageCount = doc.numPages || 0;
    const count = Math.min(3, pageCount);
    for (let i = 1; i <= count; i++) {
      const page = await doc.getPage(i);
      const viewport = page.getViewport({ scale: 1 });
      const targetW = 1000;
      const scale = targetW / viewport.width;
      const v2 = page.getViewport({ scale: scale });
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      canvas.width = Math.floor(v2.width);
      canvas.height = Math.floor(v2.height);
      await page.render({ canvasContext: ctx, viewport: v2 }).promise;
      const dataUrl = canvas.toDataURL('image/jpeg', 0.86);
      pages.push(dataUrl);
    }
    // نحفظ في الكاش
    try { DocPreviewCache.set(file, { pages, pageCount }); } catch { }
    return { pages, pageCount };
  } catch {
    return { pages: [], pageCount: 0 };
  }
}

/* ========= Office (Word/Excel) previews (تجريبي) ========= */
async function fetchOfficePreviewImages(file) {
  // إن كان السيرفر أعاد مسبقاً معاينات (في سياق المهمة)
  if (Array.isArray(file.preview_pages) && file.preview_pages.length) {
    return { pages: file.preview_pages.slice(0, 3), pageCount: file.preview_page_count || file.preview_pages.length };
  }
  // Endpoint افتراضي (يمكن تعديله لاحقاً ليتوافق مع خادمك)
  if (!file.file_id) return { pages: [], pageCount: 0 };
  const pages = [];
  for (let i = 1; i <= 3; i++) {
    const url = `/api/files/${encodeURIComponent(file.file_id)}/preview?page=${i}&fmt=jpg`;
    try {
      const head = await fetch(url, { method: 'HEAD', credentials: 'same-origin' });
      if (head.ok) {
        pages.push(url);
      } else {
        break;
      }
    } catch { break; }
  }
  const pageCount = pages.length;
  try { if (pageCount) DocPreviewCache.set(file, { pages, pageCount }); } catch { }
  return { pages, pageCount };
}

/* ========= Download handler with monthly caps ========= */
async function handleDownloadClick(file, dlUrl) {
  // 1) نحاول فحص الخادم إن وُجد check_url
  let allowed = true;
  try {
    let checkUrl = file.check_url || (file.file_id ? `/api/files/${encodeURIComponent(file.file_id)}/check?action=download` : null);
    if (checkUrl) {
      const r = await fetch(checkUrl, { credentials: 'same-origin' });
      if (r.ok) {
        const js = await r.json();
        if (js?.allowed === false) {
          allowed = false;
        } else {
          const remaining = js?.caps?.monthly?.remaining;
          if (typeof remaining === 'number' && remaining <= 0) allowed = false;
        }
      }
    }
  } catch { /* نتجاهل — نستخدم fallback المحلي */ }

  // 2) Fallback محلي: حد 3 مرات لكل ملف/شهر
  if (allowed) {
    const used = getLocalMonthlyCount(file.file_id);
    if (used >= LOCAL_MONTHLY_FILE_LIMIT) {
      allowed = false;
    }
  }

  if (!allowed) {
    const next = firstDayOfNextMonth();
    sweet(`تجاوزت الحد المسموح لتنزيل هذا الملف هذا الشهر.`, `يمكنك المحاولة مجدداً بعد ${fmtDateArabic(next)}.`, 'warning');
    return;
  }

  // 3) proceed download + زيادة العداد المحلي
  try { incLocalMonthlyCount(file.file_id); } catch { }
  const a = document.createElement('a');
  a.href = dlUrl; a.rel = 'noopener'; a.target = '_blank';
  document.body.appendChild(a); a.click(); a.remove();
}

/* ========= Captions / helpers ========= */
function buildCaption(name) {
  const cap = document.createElement('div');
  cap.className = 'media-caption';
  const txt = String(name || '').trim() || 'غير مسمى';
  cap.textContent = txt;
  cap.title = txt;
  cap.setAttribute('aria-label', txt);
  return cap;
}

/* ========= Events ========= */
function bindBaseEvents() {
  const backBtn = qs('td-back-week');
  if (backBtn) {
    backBtn.addEventListener('click', e => {
      e.preventDefault();

      try { _abortCtrl?.abort(); } catch { }
      runCleanups();
      closePdfModal();
      closeImageZoom();

      resetStudentsURLBase();

      const coId = _state.courseOfferingId || (_state.data?.course?.course_offering_id) || 0;

      try {
        window.dispatchEvent(new CustomEvent('students:open-course', {
          detail: { courseOfferingId: coId, preferCache: true }
        }));
      } catch { }

      if (coId && typeof window.loadSection === 'function') {
        window.loadSection(`course-${coId}`);
      } else {
        history.back();
      }
    }, { passive: false });
  }

  document.querySelectorAll('[data-close-pdf]').forEach(el => {
    el.addEventListener('click', e => { e.preventDefault(); closePdfModal(); }, { passive: false });
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeImageZoom();
    if (e.key === 'ArrowLeft') imgZoomNav(1);
    if (e.key === 'ArrowRight') imgZoomNav(-1);
  }, { passive: true });
}
function bindRefreshHook() { window.addEventListener('students:meta-refreshed', () => { try { TaskCache.clearAll(); } catch { } }); }

/* ========= PDF Modal ========= */
function openPdfModal(src) { const m = qs('pdfModal'), f = qs('pdfFrame'); if (!m || !f) return; f.src = src; m.setAttribute('aria-hidden', 'false'); }
function closePdfModal() { const m = qs('pdfModal'), f = qs('pdfFrame'); if (!m || !f) return; f.src = 'about:blank'; m.setAttribute('aria-hidden', 'true'); }

/* ========= Image Zoom overlay (with navigation) ========= */
let _zoomImages = []; let _zoomIdx = 0;
function injectOnceStyles() {
  if (document.getElementById('tdetail-inline-css')) return;
  const css = `
  /* ---- media base & AR ---- */
  .media{position:relative;width:100%;background:var(--surface-1,#0f1620);border-radius:16px;overflow:hidden;display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow-sm,0 6px 18px rgba(0,0,0,.10));aspect-ratio:16/9}
  .media img,.media video{width:100%;height:100%;object-fit:contain;display:block;background:#000}
  .media-image{border:1px solid var(--border-color,#163243)}
  .media-image::before{content:"";position:absolute;inset:0;background:radial-gradient(150% 120% at 100% 0%, rgba(33,158,188,.14), transparent 42%), radial-gradient(120% 120% at 0% 100%, rgba(253,158,2,.10), transparent 40%);pointer-events:none}
  .media-image img{transition:transform .35s ease}
  .media-image:hover img{transform:scale(1.01)}
  .zoomable-image{cursor:zoom-in}

  .media-caption{
    position:absolute;bottom:10px;right:10px;max-width:78%;
    padding:6px 10px;border-radius:12px;
    background:rgba(2,48,71,.55);border:1px solid rgba(255,255,255,.18);
    backdrop-filter:blur(6px);color:#fff;font-size:.82rem;line-height:1.35;
    white-space:normal;overflow:hidden;text-overflow:ellipsis;
    display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;
  }

  /* ---- video ---- */
  .video-poster{position:relative;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0a1b24,#07121a);cursor:pointer}
  .video-poster img{width:100%;height:100%;object-fit:contain;display:block}
  .play-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;background:linear-gradient(to bottom, rgba(0,0,0,.08), rgba(0,0,0,.25))}
  .play-overlay .play-btn{width:76px;height:76px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);box-shadow:0 10px 34px rgba(0,0,0,.45)}
  .play-overlay .play-btn i{font-size:30px;color:#fff;margin-left:4px}

  /* ---- audio box ---- */
  .audio-box{position:relative;display:flex;flex-direction:column;gap:12px;padding:14px 14px 16px;background:var(--surface-0,#0d141a);border:1px solid var(--border-color,#1d2e3a);border-radius:16px;box-shadow:var(--shadow-sm,0 6px 18px rgba(0,0,0,.10));overflow:hidden}
  .audio-visual{position:relative;height:64px;padding:8px 6px;border-radius:12px;background:linear-gradient(135deg, rgba(33,158,188,.10), rgba(253,158,2,.10)),radial-gradient(100% 120% at 0% 100%, rgba(33,158,188,.12), transparent 60%);display:flex;align-items:flex-end;gap:3px;overflow:hidden}
  .audio-visual span{width:4px;border-radius:2px;background:linear-gradient(180deg, var(--primary-400,#5fb6e2), var(--primary-600,#219ebc));height:14px;will-change:height}
  .audio-title{position:absolute;top:8px;right:10px;background:rgba(2,48,71,.55);color:#fff;padding:4px 10px;border-radius:10px;font-size:.78rem;max-width:72%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;border:1px solid rgba(255,255,255,.15);pointer-events:none}
  .audio-controls{flex:1;min-width:220px}
  .audio-controls audio{width:100%}

  /* ---- doc card ---- */
  .doc-card{position:relative;width:100%;background:var(--surface-1,#0f1620);border-radius:16px;box-shadow:var(--shadow-sm,0 6px 18px rgba(0,0,0,.10));border:1px solid var(--border-color,#163243);overflow:hidden}
  .doc-header{position:absolute;top:10px;right:10px;left:auto;display:flex;align-items:center;gap:8px;z-index:3}
  .doc-title{max-width:56vw;background:rgba(2,48,71,.55);color:#fff;font-size:.82rem;padding:6px 10px;border-radius:12px;border:1px solid rgba(255,255,255,.18);backdrop-filter:blur(6px);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  /* ================== Glass Button (contrast-safe) ================== */
  .glass-btn{
    width:40px;height:40px;border-radius:12px;
    position:relative; z-index:0; isolation:isolate;
    display:inline-flex; align-items:center; justify-content:center;
    color:#fff; cursor:pointer;
    background: linear-gradient(180deg, rgba(255,255,255,.22), rgba(255,255,255,.06));
    background-clip: padding-box;
    border:1px solid rgba(255,255,255,.28);
    box-shadow:
      0 0 0 1px rgba(0,0,0,.35),
      0 10px 24px rgba(0,0,0,.28);
    backdrop-filter: blur(8px) saturate(140%) brightness(.95);
    -webkit-backdrop-filter: blur(8px) saturate(140%) brightness(.95);
    transition: transform .15s ease, box-shadow .15s ease;
  }
  .glass-btn::before{
    content:""; position:absolute; inset:-8px; border-radius:16px;
    background: radial-gradient(closest-side, rgba(0,0,0,.32), transparent 65%);
    filter: blur(6px); opacity:.55; z-index:-1; pointer-events:none;
  }
  .glass-btn::after{
    content:""; position:absolute; inset:0; border-radius:inherit; pointer-events:none;
    box-shadow:
      inset 0 0 0 .5px rgba(255,255,255,.60),
      inset 0 8px 18px rgba(255,255,255,.12);
  }
  .glass-btn i{
    filter:
      drop-shadow(0 1px 1px rgba(0,0,0,.45))
      drop-shadow(0 0 .5px rgba(255,255,255,.55));
    font-size:14px;
  }
  .glass-btn:hover{ transform: translateY(-1px);
    box-shadow: 0 0 0 1px rgba(0,0,0,.42), 0 12px 26px rgba(0,0,0,.32);
  }
  .glass-btn:active{ transform: translateY(0);
    box-shadow: 0 0 0 1px rgba(0,0,0,.50), inset 0 6px 16px rgba(0,0,0,.35);
  }
  .glass-btn.disabled{ opacity:.65; pointer-events:none; filter: grayscale(.05); }

  .doc-body{position:relative;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;background:#000}
  .doc-carousel{position:absolute;inset:0;display:flex;align-items:center;justify-content:center}
  .doc-slide{display:flex;align-items:center;justify-content:center;width:100%;height:100%}
  .doc-slide img.doc-img{width:100%;height:100%;object-fit:contain;cursor:zoom-in;background:#000}
  .doc-slide.doc-loading{color:#fff}
  .spinner{width:28px;height:28px;border-radius:50%;border:3px solid rgba(255,255,255,.35);border-top-color:#fff;animation:sp 1s linear infinite}
  @keyframes sp{to{transform:rotate(360deg)}}
  .doc-empty{display:flex;flex-direction:column;align-items:center;gap:10px;color:#d7dde5}
  .doc-empty i{font-size:34px}
  .more-badge{position:absolute;bottom:10px;left:10px;background:rgba(0,0,0,.45);color:#fff;padding:6px 10px;border-radius:10px;border:1px solid rgba(255,255,255,.25);backdrop-filter:blur(4px);font-size:.82rem}

  /* ---- nav buttons (arrows) ---- */
  .nav-btn{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:14px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:#fff;display:flex;align-items:center;justify-content:center;z-index:2;backdrop-filter:blur(6px);cursor:pointer}
  .nav-prev{right:10px}
  .nav-next{left:10px}
  /* السهم داخل الزر برتقالي حسب اللون المعتمد */
  .nav-btn i{ color:#fd9e02; text-shadow:0 1px 1px rgba(0,0,0,.45); }

  .doc-card.locked .doc-body{background:linear-gradient(135deg,#0a1b24,#07121a)}
  .doc-card.locked .doc-body::after{content:"";position:absolute;inset:0;background:linear-gradient(to bottom, rgba(0,0,0,.25), rgba(0,0,0,.55))}
  .doc-card.locked .doc-empty{color:#9fb3c8}

  /* ---- description ---- */
  .td-desc-head{font-weight:800;color:var(--text-strong,#023047);margin-bottom:8px;display:flex;align-items:center;gap:8px;font-size:1.05rem}
  .td-desc-body{background:var(--surface-0,#0e141a);border:1px solid var(--border-color,#203040);border-radius:16px;padding:16px 18px;box-shadow:var(--shadow-sm,0 6px 18px rgba(0,0,0,.10));color:var(--text,#023047);font-size:.98rem;line-height:1.85}
  .td-desc-body p{margin:0 0 12px}
  .td-desc-body p:last-child{margin-bottom:0}
  .td-desc-body a{color:var(--primary-600,#1b81a0);text-decoration:none}
  .td-desc-body a:hover{text-decoration:underline}
  .td-block-title{font-size:1.02rem;font-weight:700;color:var(--text-strong,#023047);margin-bottom:8px}

  /* ---- zoom overlay ---- */
  .img-zoom-overlay{position:fixed;inset:0;background:rgba(0,0,0,.92);display:none;align-items:center;justify-content:center;z-index:9999}
  .img-zoom-overlay[aria-hidden="false"]{display:flex}
  .img-zoom-content{position:relative;max-width:92vw;max-height:92vh}
  .img-zoom-content img{max-width:92vw;max-height:92vh;object-fit:contain;border-radius:14px;box-shadow:0 14px 48px rgba(0,0,0,.55);background:#000}
  .img-zoom-close{position:absolute;top:12px;right:12px;width:38px;height:38px;border-radius:10px;background:rgba(0,0,0,.35);color:#fff;border:1px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;cursor:pointer;backdrop-filter:blur(6px);font-size:18px;line-height:1}
  .img-zoom-nav{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:14px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:#fff;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(6px);cursor:pointer}
  .img-zoom-prev{right:10px}
  .img-zoom-next{left:10px}
  .img-zoom-counter{position:absolute;bottom:12px;right:12px;background:rgba(0,0,0,.4);color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:10px;padding:4px 10px;font-size:.82rem}
  `;
  const s = document.createElement('style'); s.id = 'tdetail-inline-css'; s.textContent = css; document.head.appendChild(s);

  const overlay = document.createElement('div');
  overlay.id = 'imgZoomOverlay';
  overlay.className = 'img-zoom-overlay';
  overlay.setAttribute('aria-hidden', 'true');
  overlay.innerHTML = `
    <div class="img-zoom-content">
      <button type="button" class="img-zoom-close" aria-label="إغلاق" title="إغلاق">×</button>
      <button type="button" class="img-zoom-nav img-zoom-prev" aria-label="السابق"><i class="fas fa-chevron-right"></i></button>
      <button type="button" class="img-zoom-nav img-zoom-next" aria-label="التالي"><i class="fas fa-chevron-left"></i></button>
      <div class="img-zoom-counter">1/1</div>
      <img alt="zoomed" />
    </div>`;
  document.body.appendChild(overlay);
  overlay.addEventListener('click', e => { if (e.target === overlay) closeImageZoom(); }, { passive: true });
  overlay.querySelector('.img-zoom-close')?.addEventListener('click', e => { e.preventDefault(); closeImageZoom(); }, { passive: false });
  overlay.querySelector('.img-zoom-prev')?.addEventListener('click', e => { e.preventDefault(); imgZoomNav(-1); }, { passive: false });
  overlay.querySelector('.img-zoom-next')?.addEventListener('click', e => { e.preventDefault(); imgZoomNav(1); }, { passive: false });
}
function openImageZoom(src, alt = '') {
  openImageZoomWithNav([src], 0, alt);
}
function openImageZoomWithNav(list, index = 0, alt = '') {
  _zoomImages = Array.isArray(list) ? list.slice() : [String(list || '')];
  _zoomIdx = Math.max(0, Math.min(index, _zoomImages.length - 1));
  const overlay = document.getElementById('imgZoomOverlay'); if (!overlay) return;
  const img = overlay.querySelector('img');
  const counter = overlay.querySelector('.img-zoom-counter');
  img.src = _zoomImages[_zoomIdx]; img.alt = alt || 'image';
  counter.textContent = `${_zoomIdx + 1}/${_zoomImages.length}`;
  overlay.setAttribute('aria-hidden', 'false');
}
function imgZoomNav(delta) {
  const overlay = document.getElementById('imgZoomOverlay'); if (!overlay || overlay.getAttribute('aria-hidden') === 'true') return;
  if (!_zoomImages.length) return;
  _zoomIdx = (_zoomIdx + delta + _zoomImages.length) % _zoomImages.length;
  const img = overlay.querySelector('img');
  const counter = overlay.querySelector('.img-zoom-counter');
  img.src = _zoomImages[_zoomIdx];
  counter.textContent = `${_zoomIdx + 1}/${_zoomImages.length}`;
}
function closeImageZoom() {
  const overlay = document.getElementById('imgZoomOverlay'); if (!overlay) return;
  const img = overlay.querySelector('img'); if (img) img.src = '';
  overlay.setAttribute('aria-hidden', 'true');
}

/* ========= UI states ========= */
function showLoading() { document.querySelector('.task-details-page')?.classList.add('loading'); }
function hideLoading() { document.querySelector('.task-details-page')?.classList.remove('loading'); }
function renderError(msg) {
  const main = document.querySelector('.task-details-page'); if (!main) return;
  main.innerHTML = `
    <div class="td-card">
      <i class="fas fa-exclamation-triangle"></i>
      <h3>تعذّر تحميل تفاصيل المهمة</h3>
      <p>${escapeHtml(msg || '')}</p>
    </div>`;
}

/* ========= Text helpers ========= */
function paragraphizeAndLink(text) {
  const esc = escapeHtml(String(text || '').trim());
  if (!esc) return `<p>لا يوجد وصف متاح.</p>`;
  const paras = esc.split(/\r?\n\r?\n+/);
  return paras.map(p => {
    const withBr = p.replace(/\r?\n/g, '<br>');
    return `<p>${withBr.replace(/(https?:\/\/[^\s<>"]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>')}</p>`;
  }).join('');
}
function formatDate(input) {
  try { const d = new Date(input); return new Intl.DateTimeFormat('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' }).format(d); }
  catch { return String(input); }
}
function escapeHtml(str) { return String(str || '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", "&#039;"); }
function getFileIcon(mime) {
  const mt = (mime || '').toLowerCase();
  if (mt.includes('pdf')) return 'fas fa-file-pdf';
  if (mt.includes('word') || mt.includes('doc')) return 'fas fa-file-word';
  if (mt.includes('excel') || mt.includes('sheet') || mt.includes('xls')) return 'fas fa-file-excel';
  if (mt.includes('powerpoint') || mt.includes('presentation') || mt.includes('ppt')) return 'fas fa-file-powerpoint';
  if (mt.startsWith('image/')) return 'fas fa-file-image';
  if (mt.startsWith('video/')) return 'fas fa-file-video';
  if (mt.startsWith('audio/')) return 'fas fa-file-audio';
  if (mt.includes('zip') || mt.includes('rar')) return 'fas fa-file-archive';
  return 'fas fa-file';
}

/* ========= SweetAlert helper ========= */
function sweet(title, text, icon = 'info') {
  if (window.Swal && typeof window.Swal.fire === 'function') {
    window.Swal.fire({ title, text, icon, confirmButtonText: 'حسناً' });
  } else {
    alert(`${title}\n\n${text}`);
  }
}



// ===== AI Modal Bridge: mount only if eligible =====
// ===== AI Modal Bridge: mount only if eligible =====
async function mountAiModalForTaskLegacy(taskMeta) {
  try {
    // 1) عناصر الواجهة
    const root = document.getElementById('aiLessonPlanRoot');
    const card = document.getElementById('ai-actions-card');
    const btnOpen = document.getElementById('btnOpenModal');
    const btnDl = document.getElementById('btnDownload');
    const badge = document.getElementById('statusBadge');

    if (!root || !card) return;

    // 2) استخراج المعرفات + حفظها في data-*
    const taskId = Number(taskMeta?.id ?? taskMeta?.task_id ?? 0);
    const universityId = Number(taskMeta?.university_id ?? 0);
    const taskTypeId = Number(taskMeta?.task_type_id ?? taskMeta?.task_types_id ?? 0);

    root.dataset.taskId = taskId ? String(taskId) : '';
    root.dataset.universityId = universityId ? String(universityId) : '';
    root.dataset.taskType = String(taskTypeId);

    if (!root.dataset.userId) {
      const uid = document.body?.dataset?.userId || '';
      if (uid) root.dataset.userId = uid;
    }

    // 3) ريزستري (عالمي) لتحديد الموديول
    const cfg = (window.AI_MODAL_REGISTRY && typeof window.AI_MODAL_REGISTRY.resolveModalConfig === 'function')
      ? window.AI_MODAL_REGISTRY.resolveModalConfig({ universityId, taskTypeId })
      : null;

    if (!cfg) { card.style.display = 'none'; return; }

    if (cfg.typeKey && !root.dataset.typeKey) root.dataset.typeKey = cfg.typeKey;

    // 3.1) فحص الاشتراك/الأهلية مسبقًا عبر endpoint الحالة
    const apiBase = root.dataset.apiBase || '/api'; // غيّرها إلى '/admins/api' إن لزم بوضع data-api-base
    const typeKey = root.dataset.typeKey || '';
    const preflightQs = new URLSearchParams({ type_key: typeKey, task_id: String(taskId) });

    try {
      const res = await fetch(`${apiBase}/ai-jobs/status?${preflightQs.toString()}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'include'
      });

      if (res.status === 402) {
        // لا يوجد اشتراك فعّال (level < 1)
        const data = await res.json().catch(() => ({}));
        card.style.display = '';
        if (badge) { badge.style.display = 'inline-flex'; badge.textContent = data?.message || 'هذه الميزة تتطلب اشتراكًا فعّالًا.'; }
        if (btnOpen) { btnOpen.disabled = true; btnOpen.classList.add('disabled'); btnOpen.title = data?.message || 'الاشتراك غير مفعّل'; }
        if (btnDl) btnDl.style.display = 'none';
        return; // لا نحمّل الموديول
      }
      // غير 402 → دع الموديول يكمل إدارة الحالة/التنزيل/التحديث الدوري
    } catch (_) {
      // نتجاهل أخطاء الشبكة هنا ونسمح للموديول بإدارة الحالة لاحقًا
    }

    // 4) تمكين البطاقة والزر
    card.style.display = '';
    if (badge) { badge.style.display = 'none'; badge.textContent = ''; }
    if (btnOpen) { btnOpen.disabled = false; btnOpen.classList.remove('disabled'); btnOpen.title = ''; }
    if (btnDl) btnDl.style.display = 'none';

    // 5) استيراد الموديول وتهيئته
    const mod = await import(cfg.module);
    const api = mod?.default || mod || {};
    const initFn = api.init || api.mount || api.setup || api.bootstrap;

    if (typeof initFn === 'function') {
      try {
        await initFn({
          root, trigger: btnOpen, downloadBtn: btnDl, badge,
          taskId, universityId, taskTypeId, typeKey,
          apiBase // نمرّرها للموديول ليستخدم نفس المسار (/api أو /admins/api)
        });
      } catch {
        try { await initFn(root); } catch { try { await initFn(); } catch { } }
      }
    }

    // 6) تجهيز دالة الفتح أو فولباك
    const openFn =
      api.openModal || api.open || api.show ||
      (api.default && (api.default.openModal || api.default.open || api.default.show));

    if (btnOpen) {
      const cloned = btnOpen.cloneNode(true);
      btnOpen.replaceWith(cloned);
      const freshBtn = document.getElementById('btnOpenModal');

      freshBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        try {
          if (typeof openFn === 'function') { await openFn(root); return; }
          const modalEl = document.getElementById('aiLessonPlanModal');
          if (modalEl) {
            if (window.bootstrap?.Modal) {
              const m = window.bootstrap.Modal.getOrCreateInstance(modalEl);
              m.show();
            } else {
              modalEl.style.display = 'block';
              modalEl.setAttribute('aria-hidden', 'false');
              modalEl.querySelectorAll('[data-close-modal]').forEach(el => {
                el.addEventListener('click', (ev) => {
                  ev.preventDefault();
                  modalEl.style.display = 'none';
                  modalEl.setAttribute('aria-hidden', 'true');
                }, { once: true });
              });
            }
            return;
          }
          alert('تم تفعيل ميزة خطة الحصة، لكن لم يتم العثور على نافذة المودال أو دالة الفتح.');
        } catch (err) {
          console.warn('[ai-modal-bridge] open failed:', err);
          alert('تعذّر فتح نافذة خطة الحصة.');
        }
      }, { passive: false });
    }

    // 7) لوج مساعد
    console.debug('[AI-LP Ready]', {
      cfg, apiBase, taskId: root.dataset.taskId,
      universityId: root.dataset.universityId,
      taskTypeId: root.dataset.taskType,
      typeKey: root.dataset.typeKey,
      hasOpenFn: !!(api?.openModal || api?.open || api?.show || api?.default?.openModal || api?.default?.open || api?.default?.show)
    });

  } catch (e) {
    console.warn('[ai-modal-bridge] failed:', e);
    try {
      const card = document.getElementById('ai-actions-card');
      const badge = document.getElementById('statusBadge');
      const btnOpen = document.getElementById('btnOpenModal');
      if (card) card.style.display = '';
      if (badge) { badge.style.display = 'inline-block'; badge.textContent = 'تعذّر تحميل مكوّن الخطة (AI)'; }
      if (btnOpen) { btnOpen.disabled = true; btnOpen.classList.add('disabled'); btnOpen.title = 'تعذّر تحميل المكوّن'; }
    } catch { }
  }
}

async function mountAiModalForTask(taskMeta) {
  try {
    const taskId = Number(taskMeta?.id ?? taskMeta?.task_id ?? 0);
    const taskTypeId = Number(taskMeta?.task_type_id ?? taskMeta?.task_types_id ?? 0);
    const universityId = Number(taskMeta?.university_id ?? taskMeta?.course?.university_id ?? 0) || null;
    const courseId = Number(taskMeta?.course_id ?? taskMeta?.course?.course_id ?? 0) || null;

    if (!taskId || !taskTypeId) {
      const container = document.getElementById('ai-tools-panel');
      if (container) {
        container.innerHTML = `
          <div class="ai-tools-empty">
            <i class="fas fa-circle-info"></i>
            <div>
              <strong>لا توجد بيانات كافية لعرض أدوات الذكاء الاصطناعي.</strong>
            </div>
          </div>`;
      }
      return;
    }

    const mod = await import('/assets/js/students/sections/ai_tool_factory.js?v=1.0.1');
    await mod?.default?.mount({
      taskId,
      taskTypeId,
      universityId,
      courseId,
    });
  } catch (e) {
    console.warn('[ai-tools] failed to mount', e);
    const container = document.getElementById('ai-tools-panel');
    if (container) {
      container.innerHTML = `
        <div class="ai-tools-empty">
          <i class="fas fa-triangle-exclamation"></i>
          <div>
            <strong>تعذّر تحميل أدوات الذكاء الاصطناعي.</strong>
            <p>أعد المحاولة لاحقًا.</p>
          </div>
        </div>`;
    }
  }
}

// بعد تعريف الدالة مباشرة
try { window.mountAiModalForTask = mountAiModalForTask; } catch { }

// ✅ نادِ هذه الدالة بعد أن تتوفر لديك بيانات المهمة مباشرة:
// مثال: بعد أن تجلب تفاصيل المهمة وتخزّنها في متغير taskMeta
// mountAiModalForTask(taskMeta);
