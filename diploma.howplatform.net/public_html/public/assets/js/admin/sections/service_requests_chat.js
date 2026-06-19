/* =========================================================================
 *  Admin: Service Requests Chat
 *  Path: assets/js/admins/sections/service_requests_chat.js
 *  ملاحظة: هذا الملف مُنقّح ومصحّح — أزيلت الكتلة المكرّرة بعد videoCard
 *  (تعديل): ربط زر المحادثة لفتح الخيط تلقائيًا + تفضيل openRequest
 *  (تعديل): تصحيح السلكتور إلى [data-req-id="..."]
 *  (تعديل جديد): Polling كل 30 ثانية لعدادات غير المقروء + تنظيف التايمر عند unmount
 *  (تعديل CSRF): syncCsrfFromResponse في apiGet/apiPost ويدعم المفتاح _csrf من الكنترولر
 *  (تعديل قراءة 1): markConversationRead يُمكنه الإرسال من دون last_id ليفعّل fallback السيرفر
 *  (تعديل قراءة 2): loadMessages يستدعي markConversationRead عند "أول دفعة" فورًا
 *  (LOG غير المقروء): إضافة ULOG لتتبع شامل لمنطق غير المقروء
 *  (تحديث معاينة): مطابقة واجهة الطالب (صور/فيديو/صوت/مستندات) + تكبير الصور Overlay
 *  (قائمة الرسائل): إبقاء Copy + Delete فقط، وحذف Reply نهائيًا
 * =========================================================================
 */

window.__SERVICE_DEBUG = true;
const LOG = (...a) => { try { if (window.__SERVICE_DEBUG) console.log('[service-admin]', ...a); } catch(_){} };

/* ===== Logger خاص بغير المقروء: يضيف معرّف جلسة وسياق ===== */
const __ULOG_SESSION = Math.random().toString(36).slice(2, 8).toUpperCase();
const ULOG = (phase, payload = {}, level = 'log') => {
  try {
    if (!window.__SERVICE_DEBUG) return;
    const stamp = new Date().toISOString();
    const base = `[UNREAD][${__ULOG_SESSION}][${phase}]`;
    const line = { t: stamp, ...payload };
    if (level === 'warn') console.warn(base, line);
    else if (level === 'error') console.error(base, line);
    else if (level === 'group') console.groupCollapsed(base, line);
    else if (level === 'groupEnd') console.groupEnd(base);
    else console.log(base, line);
  } catch (_) {}
};

const LGR = (label) => {
  const id = Math.random().toString(36).slice(2, 8).toUpperCase();
  const t0 = performance.now();
  console.groupCollapsed(`[API ${id}] ${label}`);
  return {
    ok(res, extra = {}) {
      const dt = (performance.now() - t0).toFixed(1);
      console.log('status:', `${res.status}  (${dt}ms)`);
      Object.keys(extra).length && console.log('extra:', extra);
      console.groupEnd();
    },
    err(e) {
      const dt = (performance.now() - t0).toFixed(1);
      console.error('error:', e, `(${dt}ms)`);
      console.groupEnd();
    }
  };
};

/* ========================= Constants ========================= */
const API_BASE = '/admins/api/service-requests';
const MAX_FILE_BYTES = 150 * 1024 * 1024; // 150MB

const SERVER_ALLOWED_MIME = new Set([
  // Images
  'image/jpeg','image/png','image/gif','image/webp',
  // Videos
  'video/mp4','video/webm','video/ogg',
  // Docs
  'application/pdf',
  'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
  // Archives
  'application/zip','application/x-rar-compressed',
  // Audio
  'audio/mpeg','audio/mp4','audio/ogg','audio/webm','audio/wav',
]);

const EXT_TO_MIME = {
  // images
  jpg:'image/jpeg', jpeg:'image/jpeg', png:'image/png', gif:'image/gif', webp:'image/webp',
  // videos
  mp4:'video/mp4', webm:'video/webm', ogg:'video/ogg',
  // docs
  pdf:'application/pdf', doc:'application/msword', docx:'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  xls:'application/vnd.ms-excel', xlsx:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  ppt:'application/vnd.ms-powerpoint', pptx:'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  // archives
  zip:'application/zip', rar:'application/x-rar-compressed',
  // audio
  mp3:'audio/mpeg', wav:'audio/wav', m4a:'audio/mp4', oga:'audio/ogg',
};

// امتدادات للمعاينة فقط (قد يُرفض رفعها)
const PREVIEW_ONLY_EXT_TO_MIME = {
  mov:'video/quicktime', mkv:'video/x-matroska', avi:'video/x-msvideo', '3gp':'video/3gpp'
};

const STATUS_OPTIONS = ['pending','in_progress','resolved','cancelled'];

/* ========================= State / Elements ========================= */
let els = {};
let state = {};
let listeners = [];
let _unreadPollTimer = null; // ← NEW: تايمر عدادات غير المقروء

/* ========================= Helpers ========================= */
const $  = (s, c=document) => c.querySelector(s);
const $$ = (s, c=document) => Array.from(c.querySelectorAll(s));
const on = (el, ev, fn, opts) => { if (!el) return; el.addEventListener(ev, fn, opts); listeners.push(()=> el.removeEventListener(ev, fn, opts)); };
const toast = (m) => (window?.Swal?.fire ? Swal.fire({icon:'info', text:String(m)}) : alert(m));
const danger = (m) => (window?.Swal?.fire ? Swal.fire({icon:'error', text:String(m)}) : alert(m));

function addCsrf(fd) {
  const name = els.csrfName?.value;
  const hash = els.csrfHash?.value;
  if (name && hash) fd.append(name, hash);
  return fd;
}

/* ===== NEW: مزامنة CSRF القادمة من السيرفر بعد كل استجابة (يدعم _csrf) ===== */
function syncCsrfFromResponse(json) {
  try {
    const name = json?._csrf?.name || json?.csrf?.name || json?.csrf_name || json?.token_name;
    const hash = json?._csrf?.hash || json?.csrf?.hash || json?.csrf_hash || json?.token_hash;
    if (name && hash) {
      if (els.csrfName) els.csrfName.value = name;
      if (els.csrfHash) els.csrfHash.value = hash;
      ULOG('csrf.sync', { sourceKeys: Object.keys(json || {}), applied: true, name, hash: (hash||'').slice(0,6)+'...' });
    } else {
      ULOG('csrf.sync', { applied: false, reason: 'missing_keys' }, 'warn');
    }
  } catch (e) {
    ULOG('csrf.sync', { error: String(e) }, 'error');
  }
}

/* ========================= Bridge: فتح محادثة تلقائيًا ========================= */
function consumeRequestedThreadId() {
  let id = null;
  try {
    id = (window.__pendingSectionPayload && window.__pendingSectionPayload.request_id)
      || new URLSearchParams(window.location.search).get('request_id')
      || sessionStorage.getItem('chat:request_id');
  } catch (_) {}

  if (id) {
    try { sessionStorage.removeItem('chat:request_id'); } catch (_) {}
    return String(id).trim();
  }
  return null;
}

function bindBridgeEvents() {
  window.addEventListener('service-chat:open', (ev) => {
    const rid = ev?.detail?.requestId ? String(ev.detail.requestId).trim() : '';
    if (!rid) return;
    state.preselectReqId = rid;

    if (typeof openRequest === 'function') {
      openRequest(rid);
    } else if (typeof openThread === 'function') {
      openThread(rid);
    } else if (typeof loadThreadById === 'function') {
      loadThreadById(rid);
    } else {
      const li = document.querySelector(`.request-item[data-req-id="${rid}"]`);
      if (li?.click) li.click();
    }

    const item = document.querySelector(`.request-item[data-req-id="${rid}"]`);
    if (item) {
      document.querySelectorAll('.request-item').forEach((el) => el.classList.remove('active'));
      item.classList.add('active');
    }
  });
}

function exposePublicAPI() {
  window.ServiceChat = {
    open: (rid) => {
      const id = String(rid || '').trim();
      if (!id) return;
      state.preselectReqId = id;

      if (typeof openRequest === 'function') {
        openRequest(id);
      } else if (typeof openThread === 'function') {
        openThread(id);
      } else if (typeof loadThreadById === 'function') {
        loadThreadById(id);
      } else {
        const li = document.querySelector(`.request-item[data-req-id="${id}"]`);
        if (li?.click) li.click();
      }
    }
  };
}

function humanMB(bytes) { return (bytes/1024/1024).toFixed(2) + ' MB'; }
function escapeHtml(s=''){ return String(s).replace(/[&<>"]/g, (c)=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
function getExt(name='') {
  const m = String(name).toLowerCase().match(/\.([a-z0-9]+)$/i);
  return m ? m[1] : '';
}
function getEffectiveMime(file) {
  if (file?.type) return file.type;
  const ext = getExt(file?.name || '');
  return EXT_TO_MIME[ext] || PREVIEW_ONLY_EXT_TO_MIME[ext] || '';
}

/* مسار بثّ الملفات من الكنترولر */
function fileUrl(fileId, inline = true) {
  return `${API_BASE}/file/${encodeURIComponent(fileId)}${inline ? '?disposition=inline' : ''}`;
}

/* ============ Voice/Audio/Video classification ============ */
function isVoiceRecordingLocal(file) {
  const t = (getEffectiveMime(file) || '').toLowerCase();
  const n = (file?.name||'').toLowerCase();
  return (n.startsWith('voice_') && t.startsWith('audio/')) || t === 'audio/webm';
}
function isVoiceRecordingServer(f) {
  const name = (f?.file_name||'').toLowerCase();
  const ft   = (f?.file_type||'').toLowerCase();
  const mime = (f?.mime_type||'').toLowerCase();
  const isv  = String(f?.is_voice||'0') === '1';
  return isv || ft === 'voice_recording' || name.startsWith('voice_') || mime === 'audio/webm';
}

/* ========================= API ========================= */
async function apiGet(path, params=null) {
  const url = params ? `${API_BASE}${path}?${new URLSearchParams(params).toString()}` : `${API_BASE}${path}`;
  const g = LGR(`GET ${url}`);
  try {
    const res = await fetch(url, { credentials:'same-origin', headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, cache:'no-store' });
    const j = await res.json().catch(()=> ({}));
    syncCsrfFromResponse(j);
    g.ok(res, j);
    return { res, json: j };
  } catch (e) {
    g.err(e);
    throw e;
  }
}
async function apiPost(path, body, isForm=true) {
  const url = `${API_BASE}${path}`;
  const g = LGR(`POST ${url}`);
  try {
    const headers = {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'};
    if (!isForm) headers['Content-Type'] = 'application/json';

    const res = await fetch(url, {
      method:'POST',
      credentials:'same-origin',
      headers,
      body: isForm ? body : JSON.stringify(body),
    });
    const j = await res.json().catch(()=> ({}));
    syncCsrfFromResponse(j);
    g.ok(res, j);
    return { res, json: j };
  } catch (e) {
    g.err(e);
    throw e;
  }
}

/* ========================= Image Viewer CSS (NEW) ========================= */
function ensureImageViewerCSS(){
  const ID='img-viewer-css-admin';
  if (document.getElementById(ID)) return;
  const css = `
  .img-viewer-overlay{position:fixed;inset:0;background:rgba(0,0,0,.72);backdrop-filter:blur(2px);z-index:9999;display:flex;align-items:center;justify-content:center;padding:24px;}
  .img-viewer-glass{position:relative;max-width:95vw;max-height:90vh;display:flex;flex-direction:column;gap:10px}
  .img-viewer-content{position:relative;display:flex;flex-direction:column;align-items:center;gap:10px}
  .img-viewer-content img{max-width:95vw;max-height:80vh;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.35)}
  .img-viewer-caption{color:#e5e7eb;font-size:14px;text-align:center}
  .img-viewer-close{position:absolute;top:-12px;right:-12px;width:38px;height:38px;border:none;border-radius:50%;background:#111827;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.35);cursor:pointer}
  `;
  const tag=document.createElement('style'); tag.id=ID; tag.textContent=css; document.head.appendChild(tag);
}

/* ========================= Simple Action Menu CSS (NEW) ========================= */
function ensureActionMenuCSS(){
  const ID='msg-action-menu-css-admin';
  if (document.getElementById(ID)) return;
  const css = `
    .msg-actions{display:flex;align-items:center;justify-content:center;margin-inline-start:6px}
    .msg-menu-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;cursor:pointer}
    .msg-menu-btn:hover{background:#f3f4f6}
    .msg-menu{position:relative}
    .msg-menu-pop{position:absolute;inset-inline-end:0;top:36px;min-width:140px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 10px 22px rgba(0,0,0,.08);padding:6px 0;z-index:50;display:none}
    .msg-menu-pop.show{display:block}
    .msg-menu-item{width:100%;text-align:start;background:none;border:none;padding:8px 12px;font-size:13px;cursor:pointer}
    .msg-menu-item:hover{background:#f9fafb}
    .msg-menu-item[data-action="delete"]{color:#ef4444}
  `;
  const tag=document.createElement('style'); tag.id=ID; tag.textContent=css; document.head.appendChild(tag);
}

/* ========================= Media Helpers ========================= */
function videoPlaceholderDataURL(label='VIDEO') {
  const svg = `
  <svg xmlns="http://www.w3.org/2000/svg" width="640" height="360">
    <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#1f2937"/><stop offset="100%" stop-color="#111827"/>
    </linearGradient></defs>
    <rect width="100%" height="100%" fill="url(#g)"/>
    <circle cx="320" cy="180" r="52" fill="rgba(255,255,255,0.15)"/>
    <polygon points="300,150 360,180 300,210" fill="rgba(255,255,255,0.9)"/>
    <text x="50%" y="92%" fill="#9ca3af" font-size="20" font-family="sans-serif" text-anchor="middle">${escapeHtml(label)}</text>
  </svg>`;
  return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
}

async function makeVideoPosterFromFile(file, atSec=0.3) {
  return new Promise((resolve) => {
    try {
      const url = URL.createObjectURL(file);
      const v = document.createElement('video');
      v.preload = 'metadata'; v.src = url; v.muted = true; v.playsInline = true; v.crossOrigin = 'anonymous';

      const cleanup = () => { try { URL.revokeObjectURL(url); } catch{} };
      const done = (dataUrl) => { cleanup(); resolve(dataUrl); };

      v.onloadedmetadata = () => {
        try { v.currentTime = Math.min(Math.max(0.05, atSec), (v.duration||1)-0.05); }
        catch (_) { done(videoPlaceholderDataURL(file.name || 'VIDEO')); }
      };
      v.onseeked = () => {
        try {
          const canvas = document.createElement('canvas');
          canvas.width = v.videoWidth || 640; canvas.height = v.videoHeight || 360;
          canvas.getContext('2d').drawImage(v, 0, 0, canvas.width, canvas.height);
          done(canvas.toDataURL('image/png'));
        } catch (_) {
          done(videoPlaceholderDataURL(file.name || 'VIDEO'));
        }
      };
      v.onerror = () => done(videoPlaceholderDataURL(file.name || 'VIDEO'));
    } catch (_) {
      resolve(videoPlaceholderDataURL(file.name || 'VIDEO'));
    }
  });
}

/* ========== Aspect Ratio Fallback (NEW: like student) ========== */
function attachAspectFallback(previewEl, videoEl, initialRatio = 16/9){
  let ratio = initialRatio;
  const apply = () => {
    const w = previewEl.clientWidth || videoEl.clientWidth || 0;
    if (w > 0 && ratio > 0) {
      const h = Math.round(w / ratio);
      previewEl.style.height = h + 'px';
      videoEl.style.height = h + 'px';
    }
  };
  const ro = new ResizeObserver(()=> apply());
  try { ro.observe(previewEl); } catch(_) {}
  try { ro.observe(videoEl); } catch(_) {}
  apply();
  return {
    set(w, h){
      if (w > 0 && h > 0) {
        ratio = w / h;
        previewEl.style.aspectRatio = (w/h).toFixed(4);
        videoEl.style.aspectRatio   = (w/h).toFixed(4);
        apply();
      }
    }
  };
}

/* ========================= Image Viewer ========================= */
function openImageViewer(src, title='') {
  ensureImageViewerCSS();
  const overlay = document.createElement('div');
  overlay.className = 'img-viewer-overlay';
  const safeTitle = escapeHtml(title || '');
  overlay.innerHTML = `
    <div class="img-viewer-glass">
      <button type="button" class="img-viewer-close" title="Close"><i class="fas fa-times"></i></button>
      <div class="img-viewer-content">
        <img src="${src}" alt="${safeTitle}">
        ${safeTitle ? `<div class="img-viewer-caption">${safeTitle}</div>` : ''}
      </div>
    </div>
  `;
  document.body.appendChild(overlay);

  const doClose = () => {
    try { document.body.removeChild(overlay); } catch {}
    document.removeEventListener('keydown', handleKey);
  };
  const handleKey = (evt) => { if (evt.key === 'Escape') doClose(); };
  document.addEventListener('keydown', handleKey, { once: true });
  on(overlay, 'click', (evt) => { if (evt.target === overlay) doClose(); });
  on(overlay.querySelector('.img-viewer-close'), 'click', doClose);
}

/* ========================= Cards ========================= */
function imgCard(src, name, sizeBytes) {
  const wrap = document.createElement('div');
  wrap.className = 'file-card img-card zoomable-media';

  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'img-card-button';

  const thumb = document.createElement('div');
  thumb.className = 'thumb img-thumb';
  thumb.style.minHeight = '120px';
  const img = document.createElement('img');
  img.src = src;
  img.alt = name || '';
  img.loading = 'lazy';
  img.onerror = () => {
    thumb.innerHTML = `<div class="fallback-note">المعاينة غير متاحة. <span>اضغط للفتح</span></div>`;
  };
  thumb.appendChild(img);
  button.appendChild(thumb);

  const meta = document.createElement('div');
  meta.className = 'meta';
  meta.innerHTML = `<div class="name">${escapeHtml(name || 'صورة')}</div>${typeof sizeBytes === 'number' ? `<div class="sub">${humanMB(sizeBytes)}</div>` : ''}`;

  wrap.appendChild(button);
  wrap.appendChild(meta);

  on(button, 'click', (e)=> {
    if (e.metaKey || e.ctrlKey) { window.open(src, '_blank', 'noopener'); return; }
    openImageViewer(src, name || '');
  });

  return wrap;
}

function videoCard(src, name, sizeBytes, opts={poster:null}) {
  const wrap = document.createElement('div');
  wrap.className = 'file-card video-card rich-media-card';

  const thumb = document.createElement('div');
  thumb.className = 'thumb video-thumb';

  const poster = opts.poster || videoPlaceholderDataURL(name || 'Video');
  const preview = document.createElement('div');
  preview.className = 'video-preview';
  preview.style.backgroundImage = `url('${poster}')`;
  preview.style.aspectRatio = '16 / 9';   // baseline
  preview.style.minHeight = '160px';
  preview.style.width = '100%';
  preview.style.backgroundSize = 'cover';
  preview.style.backgroundPosition = 'center';

  const playBtn = document.createElement('button');
  playBtn.type = 'button';
  playBtn.className = 'video-glass-play';
  playBtn.innerHTML = '<span class="icon"><i class="fas fa-play"></i></span>';

  const playerShell = document.createElement('div');
  playerShell.className = 'video-player-shell';
  playerShell.hidden = true;

  const video = document.createElement('video');
  video.src = src;
  video.controls = true;
  video.preload = 'metadata';
  video.playsInline = true;
  video.poster = poster;
  video.style.width = '100%';
  video.style.height = 'auto';
  video.style.objectFit = 'contain';
  playerShell.appendChild(video);

  // NEW: fallback لتعديل الارتفاع عند تغير عرض الحاوية
  const arCtl = attachAspectFallback(preview, video, 16/9);
  const applyRatio = (w, h) => { if (w && h) arCtl.set(w, h); };
  video.addEventListener('loadedmetadata', () => {
    applyRatio(video.videoWidth, video.videoHeight);
  });

  thumb.appendChild(preview);
  thumb.appendChild(playBtn);
  thumb.appendChild(playerShell);

  const meta = document.createElement('div');
  meta.className = 'meta';
  meta.innerHTML = `<div class="name">${escapeHtml(name || 'Video')}</div>${typeof sizeBytes === 'number' ? `<div class="sub">${humanMB(sizeBytes)}</div>` : ''}`;

  wrap.appendChild(thumb);
  wrap.appendChild(meta);

  const activatePlayer = () => {
    preview.classList.add('is-hidden');
    playBtn.classList.add('is-hidden');
    playerShell.hidden = false;
    wrap.classList.add('is-playing');
    video.play().catch(()=>{});
  };

  const resetPlayer = () => {
    wrap.classList.remove('is-playing');
    playBtn.classList.remove('is-hidden');
    preview.classList.remove('is-hidden');
    playerShell.hidden = true;
    try { video.pause(); video.currentTime = 0; } catch {}
  };

  on(playBtn, 'click', activatePlayer);
  on(video, 'ended', resetPlayer);
  on(video, 'error', () => {
    resetPlayer();
    preview.classList.add('has-error');
    preview.innerHTML = '<div class="video-error">تعذّر تشغيل الفيديو</div>';
  });

  return wrap;
}

function buildAudioBubble({ src, name, sizeBytes, warn=false, variant='voice' }) {
  const isVoice = variant === 'voice';
  const wrap = document.createElement('div');
  wrap.className = `audio-bubble ${isVoice ? 'voice-bubble voice-inline' : 'file-bubble'}`;
  if (warn) wrap.classList.add('has-warning');

  const playBtn = document.createElement('button');
  playBtn.type = 'button';
  playBtn.className = 'audio-play';
  playBtn.innerHTML = '<i class="fas fa-play"></i>';

  const visual = document.createElement('div');
  visual.className = 'audio-visual';
  visual.innerHTML = '<span></span><span></span><span></span><span></span><span></span>';

  const slider = document.createElement('input');
  slider.type = 'range';
  slider.min = 0;
  slider.max = 1000;
  slider.value = 0;
  slider.className = 'audio-slider';

  const paintSlider = () => {
    const pct = Math.min(1, Math.max(0, slider.value / 1000));
    const percent = (pct * 100).toFixed(1);
    slider.style.background = `linear-gradient(90deg, var(--primary-500) ${percent}%, rgba(255,255,255,0.25) ${percent}%)`;
  };
  paintSlider();

  const meta = document.createElement('div');
  meta.className = 'audio-meta';
  const subParts = [];
  if (!isVoice && typeof sizeBytes === 'number') subParts.push(humanMB(sizeBytes));
  if (!isVoice && warn) subParts.push('<span class="badge warn">معاينة فقط</span>');
  const safeName = escapeHtml(name || (variant === 'voice' ? 'تسجيل صوتي' : 'ملف صوتي'));
  meta.innerHTML = `<div class="audio-name">${safeName}</div>${subParts.length ? `<div class="audio-sub">${subParts.join(' · ')}</div>` : ''}`;

  const footer = document.createElement('div');
  footer.className = 'audio-footer';
  const timeLbl = document.createElement('span');
  timeLbl.className = 'audio-time';
  timeLbl.textContent = '0:00';
  const speedBtn = document.createElement('button');
  speedBtn.type = 'button';
  speedBtn.className = 'audio-speed';
  speedBtn.textContent = '1x';
  footer.appendChild(timeLbl);
  footer.appendChild(speedBtn);
  if (isVoice) footer.classList.add('voice-footer');

  const audio = document.createElement('audio');
  audio.src = src;
  audio.preload = 'metadata';
  audio.controls = false;
  audio.className = 'audio-element';

  wrap.appendChild(playBtn);
  wrap.appendChild(visual);
  wrap.appendChild(slider);
  if (!isVoice) wrap.appendChild(meta);
  wrap.appendChild(footer);
  wrap.appendChild(audio);

  const speeds = [1, 1.5, 2];
  const fmt = (secs) => { secs = Math.max(0, Math.round(secs)); const m = Math.floor(secs / 60); const s = secs % 60; return `${m}:${String(s).padStart(2, '0')}`; };
  let raf = null;

  const stopRaf = () => { if (raf) { cancelAnimationFrame(raf); raf = null; } };
  const pump = () => {
    if (!audio.duration) { raf = requestAnimationFrame(pump); return; }
    slider.value = Math.round((audio.currentTime / audio.duration) * 1000);
    paintSlider();
    timeLbl.textContent = fmt(audio.currentTime);
    raf = requestAnimationFrame(pump);
  };

  on(playBtn, 'click', () => {
    if (audio.paused) {
      audio.play().catch(()=>{});
    } else {
      audio.pause();
    }
  });

  on(slider, 'input', () => {
    if (!audio.duration) return;
    audio.currentTime = (slider.value / 1000) * audio.duration;
    paintSlider();
  });

  on(speedBtn, 'click', () => {
    const idx = speeds.indexOf(Number(audio.playbackRate) || 1);
    const next = speeds[(idx + 1) % speeds.length];
    audio.playbackRate = next;
    speedBtn.textContent = `${next}x`;
  });

  on(audio, 'play', () => {
    wrap.classList.add('is-playing');
    playBtn.innerHTML = '<i class="fas fa-pause"></i>';
    stopRaf();
    paintSlider();
    raf = requestAnimationFrame(pump);
  });

  on(audio, 'pause', () => {
    wrap.classList.remove('is-playing');
    playBtn.innerHTML = '<i class="fas fa-play"></i>';
    stopRaf();
    paintSlider();
    timeLbl.textContent = fmt(audio.currentTime);
  });

  on(audio, 'ended', () => {
    wrap.classList.remove('is-playing');
    playBtn.innerHTML = '<i class="fas fa-play"></i>';
    stopRaf();
    slider.value = 0;
    paintSlider();
    timeLbl.textContent = fmt(audio.duration || 0);
  });

  on(audio, 'error', () => {
    wrap.classList.add('has-error');
    playBtn.disabled = true;
    // ملاحظة: لم نعطّل speedBtn لتمييز حالة "ملف صوتي" (كما بالطالب)
    paintSlider();
    timeLbl.textContent = 'خطأ في التشغيل';
  });

  return wrap;
}

function audioVoiceCard(src, name, sizeBytes, warn=false) {
  return buildAudioBubble({ src, name, sizeBytes, warn, variant: 'voice' });
}
function audioFileCard(src, name, sizeBytes, warn=false) {
  return buildAudioBubble({ src, name, sizeBytes, warn, variant: 'file' });
}
function documentCard(src, name, sizeBytes, isVideoIcon=false, warn=false) {
  const a = document.createElement('a');
  a.className = 'file-card has-actions document-card';
  a.href = src; a.target = '_blank'; a.rel = 'noopener';
  a.innerHTML = `
    <div class="thumb icon-thumb">
      <i class="fas ${isVideoIcon ? 'fa-video' : 'fa-file-alt'}"></i>
    </div>
    <div class="meta">
      <div class="name">${escapeHtml(name || 'غير معيّن')}</div>
      <div class="sub">
        ${typeof sizeBytes === 'number' ? `${humanMB(sizeBytes)}` : ''}
        ${warn ? ` · <span class="badge warn">معاينة فقط</span>` : ''}
      </div>
    </div>
    <span class="fab-download"><i class="fas fa-download"></i></span>
  `;
  return a;
}

function renderFilesCards(container, files) {
  LOG('renderFilesCards()', {count: files?.length||0, files});
  if (!container) return;
  container.innerHTML = '';
  (files||[]).forEach((f) => {
    const mime = (f.mime_type||'').toLowerCase();
    const isImg = mime.startsWith('image/');
    const isVid = mime.startsWith('video/') || (String(f.file_type||'').toLowerCase() === 'video');
    const isAud = mime.startsWith('audio/');
    const isVoice = isVoiceRecordingServer(f);

    const src = fileUrl(f.file_id, /*inline*/ true);

    if (isVoice) {
      container.appendChild(audioVoiceCard(src, f.file_name, f.file_size_bytes));
      return;
    }
    if (isImg) {
      container.appendChild(imgCard(src, f.file_name, f.file_size_bytes));
      return;
    }
    if (isVid) {
      container.appendChild(videoCard(src, f.file_name, f.file_size_bytes, { poster: videoPlaceholderDataURL(f.file_name) }));
      return;
    }
    if (isAud) {
      container.appendChild(audioFileCard(src, f.file_name, f.file_size_bytes));
      return;
    }
    container.appendChild(documentCard(src, f.file_name, f.file_size_bytes, false));
  });
}

/* ========================= Message Action Menu (NEW) ========================= */
function buildMsgActionMenu(messageId, requestId) {
  ensureActionMenuCSS();
  const wrap = document.createElement('div');
  wrap.className = 'msg-actions';
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'msg-menu-btn';
  btn.setAttribute('aria-haspopup','true');
  btn.setAttribute('aria-expanded','false');
  btn.innerHTML = '<i class="fas fa-ellipsis-v" aria-hidden="true"></i>';

  const menuWrap = document.createElement('div');
  menuWrap.className = 'msg-menu';
  const pop = document.createElement('div');
  pop.className = 'msg-menu-pop';
  pop.innerHTML = `
    <button type="button" class="msg-menu-item" data-action="copy" data-msg-id="${messageId}" data-req-id="${requestId}">
      Copy
    </button>
    <button type="button" class="msg-menu-item" data-action="delete" data-msg-id="${messageId}" data-req-id="${requestId}">
      Delete
    </button>
  `;
  menuWrap.appendChild(pop);

  // Toggle
  on(btn, 'click', (e) => {
    e.preventDefault();
    const shown = pop.classList.contains('show');
    document.querySelectorAll('.msg-menu-pop.show').forEach(x=> x.classList.remove('show'));
    pop.classList.toggle('show', !shown);
    btn.setAttribute('aria-expanded', String(!shown));
  });

  // close on outside
  const closeOnOutside = (evt) => {
    if (!pop.classList.contains('show')) return;
    const inMenu = evt.target === pop || pop.contains(evt.target) || evt.target === btn;
    if (!inMenu) {
      pop.classList.remove('show');
      btn.setAttribute('aria-expanded','false');
    }
  };
  on(document, 'click', closeOnOutside);

  wrap.appendChild(btn);
  wrap.appendChild(menuWrap);
  return wrap;
}

async function handleMessageAction(action, msgId, reqId, triggerEl) {
  const id = Number(msgId||0), rid = Number(reqId||0);
  if (!id || !rid) return;

  if (action === 'copy') {
    const host = triggerEl?.closest?.('.message-service');
    const textEl = host ? host.querySelector('.message-text') : null;
    const text = (textEl?.textContent || '').trim();
    if (!text) { toast('لا يوجد نص لنسخه'); return; }
    try {
      await navigator.clipboard.writeText(text);
      toast('تم النسخ');
    } catch {
      // fallback
      const ta = document.createElement('textarea');
      ta.value = text; ta.style.position='fixed'; ta.style.inset='-9999px';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); toast('تم النسخ'); } catch { danger('تعذّر النسخ'); }
      finally { document.body.removeChild(ta); }
    }
    return;
  }

  if (action === 'delete') {
    const confirmMsg = 'هل تريد حذف هذه الرسالة؟ سيتم حذف المرفقات التابعة لها.';
    let ok = true;
    if (window?.Swal?.fire) {
      const r = await Swal.fire({icon:'warning', text:confirmMsg, showCancelButton:true, confirmButtonText:'نعم، حذف', cancelButtonText:'إلغاء'});
      ok = r.isConfirmed;
    } else {
      ok = confirm(confirmMsg);
    }
    if (!ok) return;

    const fd = addCsrf(new FormData());
    const { json } = await apiPost(`/${rid}/messages/${id}/delete`, fd, true);
    if (!json?.success) { danger(json?.message || 'فشل الحذف'); return; }

    // إزالة من DOM
    const host = triggerEl?.closest?.('.message-service');
    if (host && host.parentNode) host.parentNode.removeChild(host);
    toast('تم الحذف');
    try { await refreshUnreadCounters(); } catch(_) {}
    return;
  }
}

/* ========================= Render Message ========================= */
function renderMessage(m) {
  const wrap = document.createElement('div');
  wrap.className = 'message-service ' + (m.is_me ? 'sentmsg' : 'receivedmsg');
  wrap.dataset.msgId = String(m.id || '');
  wrap.dataset.reqId = String(state.currentReqId || '');

  const content = document.createElement('div');
  content.className = 'message-content';

  const senderName = String(m.sender_name || '').trim() || (m.is_me ? 'أنا' : 'الطالب/ـة');
  const senderEmail = String(m.sender_email || '').trim();
  const head = document.createElement('div');
  head.className = 'message-header';
  head.innerHTML = `<div><span class="sender-name">${escapeHtml(senderName)}</span>${senderEmail ? `<span class="sender-email">${escapeHtml(senderEmail)}</span>` : ''}</div><span class="message-time">${escapeHtml(m.created_at)}</span>`;
  content.appendChild(head);

  if (m.body) {
    const text = document.createElement('p');
    text.className = 'message-text';
    text.textContent = m.body || '';
    content.appendChild(text);
  }

  if (Array.isArray(m.files) && m.files.length) {
    const filesWrap = document.createElement('div');
    filesWrap.className = 'message-files';
    m.files.forEach(f=>{
      const mime = (f.mime_type||'').toLowerCase();
      const isImg = mime.startsWith('image/');
      const isVid = mime.startsWith('video/') || (String(f.file_type||'').toLowerCase() === 'video');
      const isAud = mime.startsWith('audio/');
      const isVoice = isVoiceRecordingServer(f);

      const src = fileUrl(f.file_id, /*inline*/ true);
      if (isVoice) {
        filesWrap.appendChild(audioVoiceCard(src, f.file_name, f.file_size_bytes));
      } else if (isImg) {
        filesWrap.appendChild(imgCard(src, f.file_name, f.file_size_bytes));
      } else if (isVid) {
        filesWrap.appendChild(videoCard(src, f.file_name, f.file_size_bytes, { poster: videoPlaceholderDataURL(f.file_name) }));
      } else if (isAud) {
        filesWrap.appendChild(audioFileCard(src, f.file_name, f.file_size_bytes));
      } else {
        filesWrap.appendChild(documentCard(src, f.file_name, f.file_size_bytes));
      }
    });
    content.appendChild(filesWrap);
  }

  wrap.appendChild(content);

  // NEW: خيارات الرسالة (Copy/Delete فقط)
  const menu = buildMsgActionMenu(String(m.id||''), String(state.currentReqId||''));
  wrap.appendChild(menu);

  return wrap;
}

/* ========================= Scroll / UX ========================= */
function scrollToBottom() {
  if (!els.messagesWrapper) return;
  els.messagesWrapper.scrollTop = els.messagesWrapper.scrollHeight + 999;
}

let _scrollLoading = false;
function bindMessagesScroll() {
  on(els.messagesWrapper, 'scroll', async ()=>{
    const top = els.messagesWrapper.scrollTop;
    const threshold = 60;
    if (_scrollLoading) return;
    if (top <= threshold && state.beforeMsgId && state.hasMoreMsgs && !state.messagesLoading) {
      LOG('[scroll] top<=threshold, loading older...', {top, before: state.beforeMsgId});
      _scrollLoading = true;
      await loadMessages();
      _scrollLoading = false;
    }
  });
}

/* ========================= Top Meta ========================= */
async function loadTopMeta() {
  try {
    const { json } = await apiGet('/top-meta');
    if (!json?.success) return;
    state.canVoice = true;
    if (els.topMeta) {
      els.topMeta.innerHTML = `
        <div style="display:flex;align-items:center;gap:10px;">
          <strong>وضع الإدمن</strong>
          <span class="status-badge normal" data-status="normal">chat enabled</span>
        </div>`;
    }
    if (els.upgradeHint) els.upgradeHint.style.display = 'none';
  } catch (e) {
    console.error('[service-admin] top-meta', e);
  }
}

/* ========================= Filters / List ========================= */
function bindFilters() {
  $$('.filter-tabs .tab-btn', els.root).forEach(btn=>{
    on(btn, 'click', ()=>{
      $$('.filter-tabs .tab-btn', els.root).forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      state.reqStatus = btn.getAttribute('data-status-tab') || '';
      state.reqOffset = 0;
      state.hasMoreReq= true;
      loadRequests(true);
    });
  });
  let t=null;
  on(els.search, 'input', ()=>{
    clearTimeout(t);
    const next = (els.search.value || '').trim();
    t = setTimeout(()=>{
      state.reqQuery = next;
      state.reqOffset = 0;
      state.hasMoreReq= true;
      loadRequests(true);
    }, 700);
  });
}

async function loadRequests(reset = false) {
  if (state.loadingList || (!state.hasMoreReq && !reset)) return;
  state.loadingList = true;

  const limit = reset ? state.reqFirstLimit : state.reqPageSize;

  if (reset) {
    state.reqOffset = 0;
    if (els.list) {
      els.list.innerHTML = `
        <div class="loading-placeholder">
          <i class="fas fa-spinner fa-spin"></i>
          <p>جاري تحميل الطلبات...</p>
        </div>`;
    }
  }

  try {
    const params = {
      status: state.reqStatus || '',
      q: state.reqQuery || '',
      offset: String(state.reqOffset),
      limit: String(limit),
    };

    const { json } = await apiGet('/list', params);

    if (!json?.success) {
      if (els.list) els.list.innerHTML = `<p style="padding:10px;">فشل تحميل الطلبات</p>`;
      return;
    }

    // Counters
    if (els.root) {
      $('#countAll', els.root).textContent       = json.data.counts?.all ?? 0;
      $('#countPending', els.root).textContent   = json.data.counts?.pending ?? 0;
      $('#countProgress', els.root).textContent  = json.data.counts?.in_progress ?? 0;
      $('#countResolved', els.root).textContent  = json.data.counts?.resolved ?? 0;
      $('#countCancelled', els.root).textContent = json.data.counts?.cancelled ?? 0;
    }

    if (reset && els.list) els.list.innerHTML = '';

    const items = json.data.items || [];

    // بناء العناصر
    for (const it of items) {
      const el = document.createElement('div');
      el.className = 'request-item';
      el.dataset.reqId = it.id;
      el.innerHTML = `
        <div class="request-title">
          <span class="status-indicator ${it.status}"></span>
          <span>${escapeHtml(it.subject || '—')}</span>
            ${(+it.unread_count > 0
              ? `<span class="badge unread" data-unread="1" style="display:inline-flex">${it.unread_count}</span>`
              : `<span class="badge unread" data-unread="1" style="display:none"></span>`)}
        </div>
        <div class="request-meta">
          <span class="course-name"><i class="fas fa-book"></i> ${escapeHtml(it.course_name || 'غير محدد')}</span>
          <span class="request-date"><i class="far fa-clock"></i> ${escapeHtml(it.created_at)}</span>
        </div>
      `;
      on(el, 'click', () => openRequest(it.id));
      els.list && els.list.appendChild(el);
    }

    // تحديث حالة التصفح
    state.reqOffset += items.length;
    state.hasMoreReq = !!json.data.has_more;

    // ترقيم/زر "تحميل المزيد"
    if (els.pagination) {
      els.pagination.innerHTML = '';
      if (state.hasMoreReq) {
        const btn = document.createElement('button');
        btn.className = 'btn';
        btn.textContent = 'تحميل المزيد';
        on(btn, 'click', () => loadRequests(false));
        els.pagination.appendChild(btn);
      }
    }

    const targetId = String(state.preselectReqId || state.currentReqId || '');
    if (targetId && els.list) {
      const item = els.list.querySelector(`.request-item[data-req-id="${targetId}"]`);
      if (item) {
        els.list.querySelectorAll('.request-item').forEach((el) => el.classList.remove('active'));
        item.classList.add('active');

        if (state.preselectReqId && String(state.currentReqId) !== targetId && typeof openRequest === 'function') {
          openRequest(targetId);
        }
        if (state.preselectReqId) state.preselectReqId = null;
      }
    }

    ULOG('requests.rendered', {
      hasMore: state.hasMoreReq,
      offset: state.reqOffset,
      listCount: items.length
    });
  } catch (e) {
    if (els.list) els.list.innerHTML = `<p style="padding:10px;">فشل تحميل الطلبات</p>`;
    console.error('[service-admin] loadRequests', e);
  } finally {
    state.loadingList = false;
    refreshUnreadCounters().catch(() => {});
  }
}

function setItemUnread(reqId, count) {
  if (!els.list) return;
  const key = String(reqId);
  const item = els.list.querySelector(`[data-req-id="${key}"]`);
  if (!item) return;
  let badge = item.querySelector('[data-unread]');
  if (!badge) {
    const title = item.querySelector('.request-title');
    if (!title) return;
    badge = document.createElement('span');
    badge.className = 'badge unread';
    badge.setAttribute('data-unread', '1');
    title.appendChild(badge);
  }
  if (count > 0) {
    badge.textContent = count > 99 ? '99+' : count;
    badge.style.display = 'inline-flex';
  } else {
    badge.textContent = '';
    badge.style.display = 'none';
  }
  if (String(reqId) === String(state.currentReqId)) {
    updateActiveUnread(count);
  }
  ULOG('setItemUnread', { reqId: String(reqId), count });
}

function updateSidebarTotal(total) {
  if (!els.serviceChatBadge) return;
  if (total > 0) {
    els.serviceChatBadge.textContent = total > 99 ? '99+' : total;
    els.serviceChatBadge.style.display = 'inline-flex';
  } else {
    els.serviceChatBadge.textContent = '0';
    els.serviceChatBadge.style.display = 'none';
  }
  ULOG('sidebar.total', { total });
}

function updateActiveUnread(count) {
  if (!els.activeChatBadge) return;
  if (count > 0) {
    els.activeChatBadge.textContent = count > 99 ? '99+' : count;
    els.activeChatBadge.style.display = 'inline-flex';
  } else {
    els.activeChatBadge.textContent = '';
    els.activeChatBadge.style.display = 'none';
  }
  ULOG('active.unread', { reqId: String(state.currentReqId || ''), count });
}

function recalcTotalUnread() {
  if (!state.unreadMap) state.unreadMap = new Map();
  let total = 0;
  state.unreadMap.forEach((v) => { total += Number(v || 0); });
  state.totalUnread = total;
  updateSidebarTotal(total);
}

async function refreshUnreadCounters() {
  ULOG('counters.fetch.start');
  try {
    const { json } = await apiGet('/unread-counters');
    if (!json?.success) {
      ULOG('counters.fetch.fail', { reason: 'response_not_success' }, 'warn');
      return;
    }
    if (!state.unreadMap) state.unreadMap = new Map();
    state.unreadMap.clear();
    const counters = json.data.counters || [];
    counters.forEach((row) => {
      const reqId = String(row.request_id ?? '');
      if (!reqId) return;
      const count = Number(row.unread_count || 0);
      state.unreadMap.set(reqId, count);
      setItemUnread(reqId, count);
    });
    if (els.list) {
      els.list.querySelectorAll('.request-item').forEach((item) => {
        const reqId = item.dataset.reqId;
        if (!state.unreadMap.has(reqId)) setItemUnread(reqId, 0);
      });
    }
    recalcTotalUnread();

    if (state.currentReqId != null) {
      const cur = String(state.currentReqId);
      const curCount = Number(state.unreadMap.get(cur) || 0);
      updateActiveUnread(curCount);
    }
    ULOG('counters.fetch.done', {
      total: state.totalUnread,
      mapSize: state.unreadMap.size,
      currentReqId: String(state.currentReqId || '')
    });
  } catch (err) {
    console.error('[service-admin] unread counters', err);
    ULOG('counters.fetch.error', { error: String(err) }, 'error');
  }
}

/* ===== NEW: markConversationRead مع fallback سيرفري ===== */
async function markConversationRead(items) {
  if (!state.currentReqId) return false;
  if (!state.unreadMap) state.unreadMap = new Map();

  const curKey = String(state.currentReqId);
  const currentUnread = Number(state.unreadMap.get(curKey) || 0);

  const postReadUpTo = async (lastIdOrNull) => {
    const fd = addCsrf(new FormData());
    if (lastIdOrNull && Number(lastIdOrNull) > 0) {
      fd.append('last_id', String(lastIdOrNull));
    } // إذا لم نضع last_id سيطبق السيرفر fallback إلى MAX(id) للطرف الآخر
    ULOG('read.post.start', { reqId: curKey, lastId: lastIdOrNull ?? null, fallback: !lastIdOrNull });
    const { json } = await apiPost(`/${state.currentReqId}/read-up-to`, fd, true);
    const ok = !!json?.success;
    ULOG('read.post.done', {
      ok,
      inserted: json?.data?.inserted,
      unread_after: json?.data?.unread_after,
      last_id: json?.data?.last_id
    }, ok ? 'log' : 'warn');
    return ok;
  };

  const findLastOtherId = (arr=[]) => {
    const others = (arr || []).filter((m) => Number(m.is_me) === 0);
    if (!others.length) return 0;
    return Math.max(...others.map((m) => Number(m.id || 0)));
  };

  // من الدفعة الحالية
  let lastId = findLastOtherId(items);
  ULOG('read.scan.batch', { phase: 'initial', foundLastId: lastId, currentUnread });

  // إذا ما وجدنا lastId وكان عندنا غير مقروء، اعتمدنا على fallback السيرفري
  const ok = await postReadUpTo(lastId || null);
  if (!ok) {
    ULOG('read.server_reject', { lastId: lastId || null }, 'warn');
    return false;
  }

  // صفّر محليًا ثم أعِد مزامنة العدادات من السيرفر للتأكيد
  state.unreadMap.set(curKey, 0);
  setItemUnread(state.currentReqId, 0);
  recalcTotalUnread();

  try { await refreshUnreadCounters(); } catch (e) {
    ULOG('counters.refreshAfterRead.error', { error: String(e) }, 'error');
  }
  ULOG('read.done', { reqId: curKey, lastId: lastId || null, totalAfter: state.totalUnread });
  return true;
}

/* ========================= Details + Messages ========================= */
async function openRequest(reqId) {
  state.currentReqId = reqId;
  state.beforeMsgId  = null;
  state.hasMoreMsgs  = true;

  els.chatPlaceholder.style.display = 'none';
  els.chatContainer.style.display   = 'flex';

  $$('.request-item', els.root).forEach(it=> it.classList.toggle('active', String(it.dataset.reqId)===String(reqId)));

  const { json } = await apiGet(`/${reqId}`);
  if (!json?.success) { toast('تعذّر تحميل تفاصيل الطلب'); return; }

  const r = json.data.request;
  $('#chatRequestTitle', els.root).textContent = r.subject;
  const badge = $('#chatRequestStatus', els.root);
  badge.textContent = r.status; badge.dataset.status = r.status;
  $('#chatRequestDate', els.root).textContent = r.created_at;
  $('#requestDescription', els.root).textContent = r.description || '';
  $('#requestCourse', els.root).textContent = r.course_name ? `${r.course_name} — ${r.semester_name || ''}` : 'غير محدد';

  if (els.statusSelect) {
    els.statusSelect.innerHTML = STATUS_OPTIONS.map(st => `<option value="${st}" ${st===r.status?'selected':''}>${st}</option>`).join('');
    els.statusSelect.dataset.prev = r.status;
    els.statusSelect.disabled = false;
  } else {
    console.warn('[service-admin] statusSelect element not found — add <select id="statusSelect"> داخل تفاصيل الطلب.');
  }

  renderFilesCards($('#requestAttachments', els.root), json.data.attachments || []);

  // الرسائل الأخيرة
  els.messagesList.innerHTML = '';
  await loadMessages(/* initial */ true);
  scrollToBottom();

  // تحديث الشارات مباشرةً بعد الفتح
  try { await refreshUnreadCounters(); } catch (_) {}
  ULOG('openRequest.done', { reqId: String(reqId) });
}

async function loadMessages(isInitial=false) {
  if (!state.currentReqId || state.messagesLoading) return;
  state.messagesLoading = true;

  const qs = {
    limit: String(state.msgLimit),
    ...(state.beforeMsgId ? { before_id: String(state.beforeMsgId) } : {})
  };

  const prevHeight = els.messagesList.scrollHeight;

  ULOG('messages.fetch.start', { reqId: String(state.currentReqId), beforeId: state.beforeMsgId, limit: state.msgLimit, isInitial });
  const { json } = await apiGet(`/${state.currentReqId}/messages`, qs);
  if (!json?.success) {
    state.messagesLoading = false;
    toast('فشل تحميل الرسائل');
    ULOG('messages.fetch.fail', { reason: 'response_not_success' }, 'warn');
    return;
  }

  const items = json.data.items || [];
  ULOG('messages.fetch.done', { got: items.length, has_more: json.data.has_more, next_before_id: json.data.next_before_id });

  const frag = document.createDocumentFragment();
  items.forEach(m => frag.appendChild(renderMessage(m)));

  if (state.beforeMsgId) {
    els.messagesList.prepend(frag);
    const newHeight = els.messagesList.scrollHeight;
    els.messagesWrapper.scrollTop = newHeight - prevHeight;
  } else {
    els.messagesList.appendChild(frag);
  }

  state.beforeMsgId = json.data.next_before_id || null;
  state.hasMoreMsgs = !!json.data.has_more;
  state.messagesLoading = false;
  LOG('loadMessages(): updated', {next_before_id: state.beforeMsgId, has_more: state.hasMoreMsgs});

  // ✅ جديد: التأشير كمقروء عند "أول دفعة" فورًا (أي لا يوجد before_id في الطلب الحالي)
  if (!('before_id' in qs)) {
    try {
      await markConversationRead(items);
    } catch (err) {
      console.error('[service-admin] markConversationRead', err);
      ULOG('read.exception', { error: String(err) }, 'error');
    }
  }
}

/* ========================= Composer ========================= */
function bindComposer() {
  on(els.btnAttachFile, 'click', (evt)=>{
    evt.preventDefault();
    if (!els.chatFileInput || state.fileDialogOpen) return;
    state.fileDialogOpen = true;
    els.chatFileInput.click();
    setTimeout(() => { state.fileDialogOpen = false; }, 500);
  });
  on(els.chatFileInput, 'change', (e)=>{
    state.fileDialogOpen = false;
    addPendingFiles(e.target.files);
    e.target.value='';
  });
  on(els.btnSendMessage, 'click', (evt)=>{ evt.preventDefault(); sendMessage(); });

  on(els.messageInput, 'keydown', (ev)=>{
    if (ev.key === 'Enter' && !ev.shiftKey) {
      ev.preventDefault();
      sendMessage();
    }
  });

  on(els.voiceBtn, 'click', ()=> startRecording());
  on(els.btnStopRecording, 'click', ()=> stopRecordingAndQueue());
  on(els.btnCancelRecording, 'click', ()=> cancelRecording());
}

function addPendingFiles(fileList) {
  const files = Array.from(fileList || []);
  let added = 0;

  for (const file of files) {
    const effMime = (getEffectiveMime(file) || '').toLowerCase();
    const ext = getExt(file.name);

    const serverAllowed = SERVER_ALLOWED_MIME.has(effMime) || SERVER_ALLOWED_MIME.has(EXT_TO_MIME[ext] || '');
    const previewOnly = !!PREVIEW_ONLY_EXT_TO_MIME[ext] && !serverAllowed;

    if (!serverAllowed && !previewOnly) {
      toast(`نوع الملف غير مدعوم: ${file.name}`);
      continue;
    }
    if (file.size > MAX_FILE_BYTES) {
      toast(`حجم الملف ${humanMB(file.size)} يتجاوز الحد 150MB: ${file.name}`);
      continue;
    }

    file._effectiveMime = effMime || EXT_TO_MIME[ext] || PREVIEW_ONLY_EXT_TO_MIME[ext] || '';
    file._serverAllowed = !!serverAllowed;
    file._previewOnlyWarn = !!previewOnly;

    state.pendingFiles.push(file);
    added++;
  }

  LOG('addPendingFiles()', { added, total: state.pendingFiles.length });
  renderPendingPreviews();
  if (files.length > 0 && added === 0) toast('لم يتم قبول أي ملف من اختيارك');
}

async function renderPendingPreviews() {
  if (!els.pendingAttachments) return;
  els.pendingAttachments.innerHTML = '';

  const bindRemoval = (root, idx) => {
    const removeBtn = root.querySelector('.remove-btn');
    if (!removeBtn) return;
    on(removeBtn, 'click', (evt) => {
      evt.preventDefault();
      evt.stopPropagation();
      state.pendingFiles.splice(idx, 1);
      renderPendingPreviews();
    });
  };

  for (let idx = 0; idx < state.pendingFiles.length; idx++) {
    const file = state.pendingFiles[idx];
    const mime = (file._effectiveMime || file.type || '').toLowerCase();
    const isImg = mime.startsWith('image/');
    const isVid = mime.startsWith('video/');
    const isAud = mime.startsWith('audio/');
    const isVoice = isVoiceRecordingLocal(file);
    const warn = !!file._previewOnlyWarn && !file._serverAllowed;

    const removeBtnHtml = `
      <button type="button" class="remove-btn" title="Remove" style="position:absolute;top:6px;inset-inline-end:6px;width:28px;height:28px;border-radius:50%;background:#ef4444;border:none;color:#fff;cursor:pointer;">
        <i class="fas fa-times"></i>
      </button>`;

    if (isVoice) {
      const url = URL.createObjectURL(file);
      const box = audioVoiceCard(url, file.name, file.size, warn);
      box.style.position = 'relative';
      box.insertAdjacentHTML('afterbegin', removeBtnHtml);
      bindRemoval(box, idx);
      els.pendingAttachments.appendChild(box);
      continue;
    }

    if (isImg) {
      const url = URL.createObjectURL(file);
      const card = imgCard(url, file.name, file.size);
      card.style.position = 'relative';
      if (warn) {
        const sub = card.querySelector('.meta .sub');
        if (sub) sub.innerHTML += ' · <span class="badge warn">معاينة فقط</span>';
        card.classList.add('has-warning');
      }
      card.insertAdjacentHTML('afterbegin', removeBtnHtml);
      bindRemoval(card, idx);
      els.pendingAttachments.appendChild(card);
      continue;
    }

    if (isVid) {
      const url = URL.createObjectURL(file);
      const poster = await makeVideoPosterFromFile(file).catch(()=> videoPlaceholderDataURL(file.name));
      const card = videoCard(url, file.name, file.size, { poster });
      card.style.position = 'relative';
      if (warn) {
        const sub = card.querySelector('.meta .sub');
        if (sub) sub.innerHTML += ' · <span class="badge warn">معاينة فقط</span>';
        card.classList.add('has-warning');
      }
      card.insertAdjacentHTML('afterbegin', removeBtnHtml);
      bindRemoval(card, idx);
      els.pendingAttachments.appendChild(card);
      continue;
    }

    if (isAud) {
      const url = URL.createObjectURL(file);
      const card = audioFileCard(url, file.name, file.size, warn);
      card.style.position = 'relative';
      if (warn) {
        const sub = card.querySelector('.audio-sub');
        if (sub) sub.innerHTML += ' · <span class="badge warn">معاينة فقط</span>';
        card.classList.add('has-warning');
      }
      card.insertAdjacentHTML('afterbegin', removeBtnHtml);
      bindRemoval(card, idx);
      els.pendingAttachments.appendChild(card);
      continue;
    }

    const url = URL.createObjectURL(file);
    const doc = documentCard(url, file.name, file.size, false, warn);
    doc.style.position = 'relative';
    doc.insertAdjacentHTML('afterbegin', removeBtnHtml);
    bindRemoval(doc, idx);
    els.pendingAttachments.appendChild(doc);
  }
}

async function sendMessage() {
  if (!state.currentReqId) return toast('الرجاء فتح طلب قبل إرسال الرسائل');
  if (state.sendingMessage) return;

  const body = els.messageInput?.value?.trim() || '';

  if (!body && state.pendingFiles.length === 0) {
    toast('الرجاء كتابة رسالة أو إرفاق ملف قبل الإرسال');
    return;
  }

  state.sendingMessage = true;
  if (els.btnSendMessage) {
    els.btnSendMessage.disabled = true;
    els.btnSendMessage.classList.add('is-busy');
  }

  try {
    const fd = new FormData();
    if (body) fd.append('body', body);
    state.pendingFiles.forEach(f => fd.append('files[]', f, f.name));
    addCsrf(fd);

    const { json } = await apiPost(`/${state.currentReqId}/messages`, fd, true);
    if (!json?.success) {
      danger(json?.message || 'فشل إرسال الرسالة');
      return;
    }

    els.messageInput.value = '';
    state.pendingFiles = [];
    renderPendingPreviews();

    state.beforeMsgId = null;
    state.hasMoreMsgs = true;
    els.messagesList.innerHTML = '';
    await loadMessages(/* initial */ true);
    scrollToBottom();
  } catch (error) {
    console.error('[service-admin] sendMessage', error);
    danger('حدث خطأ غير متوقع أثناء إرسال الرسالة');
  } finally {
    state.sendingMessage = false;
    if (els.btnSendMessage) {
      els.btnSendMessage.disabled = false;
      els.btnSendMessage.classList.remove('is-busy');
    }
  }
}

/* ========================= Voice Recording ========================= */
async function startRecording() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    state.mediaRecorder = new MediaRecorder(stream);
    state.recordingChunks = [];
    state.mediaRecorder.ondataavailable = (e)=> { if (e.data.size) state.recordingChunks.push(e.data); };
    state.mediaRecorder.start();
    state.recordingStart = Date.now();
    if (els.voiceRecording) els.voiceRecording.style.display = 'flex';
    animateTimer();
  } catch (e) {
    console.error(e);
    danger('تعذر بدء التسجيل الصوتي');
  }
}
function animateTimer() {
  const tick = ()=>{
    if (!state.mediaRecorder || (els.voiceRecording && els.voiceRecording.style.display === 'none')) return;
    const s = Math.floor((Date.now()-state.recordingStart)/1000);
    const m = String(Math.floor(s/60)).padStart(2,'0');
    const ss= String(s%60).padStart(2,'0');
    if (els.recordingTime) els.recordingTime.textContent = `${m}:${ss}`;
    requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
}
function stopRecordingAndQueue() {
  if (!state.mediaRecorder) return;
  state.mediaRecorder.onstop = ()=>{
    const blob = new Blob(state.recordingChunks, { type: 'audio/webm' });
    const file = new File([blob], `voice_${Date.now()}.webm`, { type: 'audio/webm' });
    file._effectiveMime = 'audio/webm';
    file._serverAllowed = SERVER_ALLOWED_MIME.has('audio/webm');
    state.pendingFiles.push(file);
    renderPendingPreviews();
    if (els.voiceRecording) els.voiceRecording.style.display = 'none';
    try { state.mediaRecorder.stream.getTracks().forEach(t=>t.stop()); } catch {}
    state.mediaRecorder = null;
  };
  state.mediaRecorder.stop();
}
function cancelRecording() {
  if (state.mediaRecorder) {
    try { state.mediaRecorder.stream.getTracks().forEach(t=>t.stop()); } catch {}
    state.mediaRecorder = null;
  }
  if (els.voiceRecording) els.voiceRecording.style.display = 'none';
}

/* ========================= Toggles & Status ========================= */
function bindToggles() {
  on(els.btnToggleRequestDetails, 'click', ()=>{
    const el = els.requestDetails;
    el.style.display = (el.style.display==='none' || !el.style.display) ? 'block' : 'none';
  });

  on(els.cancelRequestBtn, 'click', async ()=>{
    if (!state.currentReqId) return;
    if (!confirm('هل تريد إلغاء الطلب؟')) return;
    const fd = addCsrf(new FormData());
    const { json } = await apiPost(`/${state.currentReqId}/cancel`, fd, true);
    if (!json?.success) { danger(json?.message || 'فشل الإلغاء'); return; }
    toast('تم إلغاء الطلب');
    state.reqOffset = 0; state.hasMoreReq = true;
    await loadRequests(true);
    await openRequest(state.currentReqId);
  });

  if (els.statusSelect) {
    on(els.statusSelect, 'change', async ()=>{
      if (!state.currentReqId) return;
      const prev = els.statusSelect.dataset.prev || '';
      const newStatus = els.statusSelect.value;
      LOG('[status] change →', {prev, newStatus});
      const fd = addCsrf(new FormData());
      fd.append('status', newStatus);
      els.statusSelect.disabled = true;
      const { json } = await apiPost(`/${state.currentReqId}/status`, fd, true);
      els.statusSelect.disabled = false;
      if (!json?.success) {
        console.warn('[status] server rejected:', json);
        danger(json?.message || 'فشل تحديث الحالة');
        els.statusSelect.value = prev;
        return;
      }
      els.statusSelect.dataset.prev = newStatus;
      const badge = $('#chatRequestStatus', els.root);
      badge.textContent = newStatus; badge.dataset.status = newStatus;
      toast('تم تحديث الحالة');
      state.reqOffset = 0; state.hasMoreReq = true;
      await loadRequests(true);
    });
  }

  // تفويض إجراءات قائمة الرسائل (Copy/Delete)
  on(els.messagesList, 'click', (evt) => {
    const actBtn = evt.target.closest('.msg-menu-item');
    if (!actBtn) return;
    const action = String(actBtn.dataset.action || '');
    const msgId  = actBtn.dataset.msgId || '';
    const reqId  = actBtn.dataset.reqId || String(state.currentReqId || '');
    handleMessageAction(action, msgId, reqId, actBtn).catch(err=>{
      console.error('[service-admin] action error', err);
      danger('تعذر تنفيذ العملية');
    });
  });
}

/* ========================= Mount / Unmount ========================= */
function collectEls(rootEl=document) {
  els = {
    root: rootEl.querySelector('#serviceRoot') || rootEl,
    csrfName: rootEl.querySelector('#__csrf_name'),
    csrfHash: rootEl.querySelector('#__csrf_hash'),
    topMeta: rootEl.querySelector('#serviceTopMeta'),
    upgradeHint: rootEl.querySelector('#serviceUpgradeHint'),

    // Sidebar
    list: rootEl.querySelector('#serviceList'),
    pagination: rootEl.querySelector('#svcPagination'),
    search: rootEl.querySelector('#svcSearch'),

    // Chat area
    chatPlaceholder: rootEl.querySelector('#chatPlaceholder'),
    chatContainer: rootEl.querySelector('#chatContainer'),
    messagesWrapper: rootEl.querySelector('#messagesWrapper'),
    messagesList: rootEl.querySelector('#messagesList'),

    // Details
    requestDetails: rootEl.querySelector('#requestDetails'),
    btnToggleRequestDetails: rootEl.querySelector('#btnToggleRequestDetails'),
    cancelRequestBtn: rootEl.querySelector('#cancelRequestBtn'),
    statusSelect: rootEl.querySelector('#statusSelect'),

    // Composer
    btnAttachFile: rootEl.querySelector('#btnAttachFile'),
    chatFileInput: rootEl.querySelector('#chatFileInput'),
    pendingAttachments: rootEl.querySelector('#chatPendingAttachments'),
    messageInput: rootEl.querySelector('#messageInput'),
    btnSendMessage: rootEl.querySelector('#btnSendMessage'),

    // Voice
    voiceBtn: rootEl.querySelector('#voiceBtn'),
    voiceRecording: rootEl.querySelector('#voiceRecording'),
    btnStopRecording: rootEl.querySelector('#btnStopRecording'),
    btnCancelRecording: rootEl.querySelector('#btnCancelRecording'),
    recordingTime: rootEl.querySelector('#recordingTime'),

    // Sidebar badges
    serviceChatBadge: document.querySelector('#serviceChatTotalUnread'),
    activeChatBadge: document.querySelector('#activeChatUnread'),
  };
}

function defaultState() {
  state = {
    reqOffset: 0,
    reqStatus: '',
    reqQuery: '',
    loadingList: false,
    hasMoreReq: true,
    reqFirstLimit: 10,
    reqPageSize: 5,

    currentReqId: null,
    msgLimit: 10,
    beforeMsgId: null,
    hasMoreMsgs: true,
    messagesLoading: false,

    pendingFiles: [],
    mediaRecorder: null,
    recordingChunks: [],
    recordingStart: 0,
    canVoice: true,
    fileDialogOpen: false,
    sendingMessage: false,
    unreadMap: new Map(),
    totalUnread: 0,
    preselectReqId: null,
  };
}

function bindMessagesScrollWrapper() {
  bindMessagesScroll();
}

function mount(rootEl=document) {
  LOG('mount(): start');
  collectEls(rootEl);
  defaultState();

  bindFilters();
  bindComposer();
  bindMessagesScrollWrapper();
  bindToggles();

  loadTopMeta();
  loadRequests(true);
}

function unmount() {
  try { listeners.forEach(off => off()); } catch {}
  listeners = [];
  try { if (state?.mediaRecorder) state.mediaRecorder.stream.getTracks().forEach(t=>t.stop()); } catch {}
  els = {}; state = {};
  if (_unreadPollTimer) { clearInterval(_unreadPollTimer); _unreadPollTimer = null; }
  LOG('unmount(): done');
}

/* ========================= Export ========================= */
export async function init() {
  mount(document);

  exposePublicAPI();
  bindBridgeEvents();

  const preselectId = consumeRequestedThreadId();
  if (preselectId) {
    if (typeof openRequest === 'function')       openRequest(preselectId);
    else if (typeof openThread === 'function')   openThread(preselectId);
    else if (typeof loadThreadById === 'function') loadThreadById(preselectId);
    else {
      const li = document.querySelector(`[data-req-id="${preselectId}"]`);
      if (li?.click) li.click();
    }
  }

  try { await refreshUnreadCounters(); } catch (_) {}
  if (_unreadPollTimer) clearInterval(_unreadPollTimer);
  _unreadPollTimer = setInterval(() => {
    refreshUnreadCounters().catch(()=>{});
  }, 30000);

  ULOG('init.done', { pollIntervalSec: 30 });
}
