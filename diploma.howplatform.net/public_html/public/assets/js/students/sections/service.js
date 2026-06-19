/* =========================================================================
 *  Service Requests: Chat Page (Dynamic Mount)
 *  File: public/assets/js/students/service.js
 *
 *  تحسينات رئيسية:
 *   1) Image Viewer Overlay لفتح الصور في طبقة علوية (نفس سلوك واجهة الإدمن)
 *   2) فيديو يحترم الـ Aspect Ratio عبر CSS + Fallback بحساب الارتفاع
 *   3) ضمان إمكانية التمرير للأعلى عبر تحميل صفحات إضافية تلقائيًا
 *   4) معاينات Tailwind للصوت/الفيديو/الصور + قائمة خيارات (Reply/Copy/Delete)
 *
 *  النسخة: service.js@3.2.0-admin-parity+tailwind
 * =========================================================================
 */

/* ===================== ثوابت وتعيينات ===================== */
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
  'audio/mpeg','audio/mp4','audio/ogg','audio/webm','audio/wav'
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
  mp3:'audio/mpeg', wav:'audio/wav', oga:'audio/ogg', weba:'audio/webm', m4a:'audio/mp4'
};

// امتدادات نسمح بمعاينتها فقط (قد لا يسمح السيرفر برفعها)
const PREVIEW_ONLY_EXT_TO_MIME = { mov:'video/quicktime', mkv:'video/x-matroska', avi:'video/x-msvideo', '3gp':'video/3gpp' };

const MAX_FILE_BYTES = 150 * 1024 * 1024; // 150MB
const JS_VERSION = 'service.js@3.2.0-admin-parity+tailwind';

/* ===================== تسجيل (لوج) ===================== */
const LOG = {
  i: (...a)=> console.log('[service]', ...a),
  w: (...a)=> console.warn('[service]', ...a),
  e: (...a)=> console.error('[service]', ...a),
  g: (label, data)=> { console.group(`[service] ${label}`); if (data !== undefined) console.log(data); },
  ge: ()=> console.groupEnd()
};

/* ===================== حالة الصفحة ومراجع DOM ===================== */
let els = {};
let state = {};
let listeners = [];
let voiceCtxs = new Set();

/* ===================== دوال مساعدة عامة ===================== */
const $  = (s, c=document)=> c.querySelector(s);
const $$ = (s, c=document)=> Array.from(c.querySelectorAll(s));
const on = (el, ev, fn, opts)=>{ if (!el) return; el.addEventListener(ev, fn, opts); listeners.push(()=> el.removeEventListener(ev, fn, opts)); };
const toast = (m)=> (window?.Swal?.fire ? Swal.fire({icon:'info', text:String(m)}) : alert(m));

function addCsrf(fd) {
  const name = els.csrfName?.value;
  const hash = els.csrfHash?.value;
  if (name && hash) fd.append(name, hash);
  return fd;
}
function humanMB(bytes) { try { return (bytes/1024/1024).toFixed(2) + ' MB'; } catch { return ''; } }
function fmtTime(t){ t = Math.max(0, t|0); const m = String((t/60)|0).padStart(2,'0'); const s = String((t%60)|0).padStart(2,'0'); return `${m}:${s}`; }
function scrollToBottom() { if (!els.messagesWrapper) return; els.messagesWrapper.scrollTop = els.messagesWrapper.scrollHeight + 999; }

/* ===================== Helpers خاصة بالملفات/الميديا ===================== */
function escapeHtml(s=''){
  return String(s).replace(/[&<>"]/g, (c)=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
}
function getFileExt(name=''){ const m = String(name).toLowerCase().match(/\.([a-z0-9]+)(?:\?.*)?$/i); return m ? m[1] : ''; }
function getEffectiveMime(file){
  if (file?.type) return String(file.type);
  const ext = getFileExt(file?.name || '');
  return EXT_TO_MIME[ext] || PREVIEW_ONLY_EXT_TO_MIME[ext] || '';
}
function isVoiceRecordingLocal(file){
  const mime = (getEffectiveMime(file) || '').toLowerCase();
  const name = String(file?.name||'').toLowerCase();
  return (name.startsWith('voice_') && mime.startsWith('audio/')) || mime === 'audio/webm';
}
function isVoiceRecordingServer(f){
  const name = String(f?.file_name||'').toLowerCase();
  const ft   = String(f?.file_type||'').toLowerCase();
  const mime = String(f?.mime_type||'').toLowerCase();
  const isv  = String(f?.is_voice||'0') === '1';
  return isv || ft === 'voice_recording' || name.startsWith('voice_') || mime === 'audio/webm';
}
function isVideoLikeServer(f){
  const mime = String(f?.mime_type||'').toLowerCase();
  const ft   = String(f?.file_type||'').toLowerCase();
  if (isVoiceRecordingServer(f)) return false;
  return mime.startsWith('video/') || ft === 'video';
}
function fileViewUrl(f){
  if (f?.file_id != null) return `/students/service-requests/file/${encodeURIComponent(f.file_id)}?disposition=inline`;
  return '#';
}

/* ===================== Image Viewer (CSS + UI) ===================== */
function ensureImageViewerCSS(){
  const ID='img-viewer-css';
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
    </div>`;
  document.body.appendChild(overlay);
  const doClose = () => { try { document.body.removeChild(overlay); } catch(_){} document.removeEventListener('keydown', handleKey); };
  const handleKey = (evt) => { if (evt.key === 'Escape') doClose(); };
  document.addEventListener('keydown', handleKey, { once: true });
  on(overlay, 'click', (evt) => { if (evt.target === overlay) doClose(); });
  on(overlay.querySelector('.img-viewer-close'), 'click', doClose);
}

/* ===================== فيديو: Poster + Aspect Fallback ===================== */
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
      const cleanup = () => { try { URL.revokeObjectURL(url); } catch(_){} };
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

/* ===================== أدوات إنشاء عناصر Tailwind ===================== */
function h(html) {
  const t = document.createElement('template');
  t.innerHTML = html.trim();
  return t.content.firstElementChild;
}

/* زر القائمة (…) Tailwind لكل رسالة */
function actionsMenu(msgId, reqId) {
  const id = `menu-${msgId}`;
  const btn = h(`
    <button type="button"
      class="shrink-0 inline-flex self-center items-center p-2 text-sm font-medium text-gray-900 bg-white rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none dark:text-white focus:ring-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800 dark:focus:ring-gray-600"
      aria-haspopup="true" aria-expanded="false" aria-controls="${id}">
      <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 4 15">
        <path d="M3.5 1.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 6.041a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.959a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/>
      </svg>
    </button>
  `);

  const menu = h(`
    <div id="${id}" class="z-10 hidden bg-white divide-y divide-gray-100 rounded-lg shadow-sm w-40 dark:bg-gray-700 dark:divide-gray-600 absolute top-8 end-0">
      <ul class="py-2 text-sm text-gray-700 dark:text-gray-200">
                <li><a href="#" data-act="copy"   class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Copy</a></li>
        <li><a href="#" data-act="delete" class="block px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Delete</a></li>
      </ul>
    </div>
  `);
  menu.dataset.msgId = msgId;
  menu.dataset.reqId = reqId;

  const wrap = h(`<div class="relative"></div>`);
  wrap.appendChild(btn);
  wrap.appendChild(menu);

  const toggle = () => {
    const shown = !menu.classList.contains('hidden');
    $$(`[id^="menu-"]`).forEach(el => el.classList.add('hidden'));
    if (!shown) menu.classList.remove('hidden');
    btn.setAttribute('aria-expanded', String(!shown));
  };
  on(btn, 'click', (e) => { e.stopPropagation(); toggle(); });
  on(document, 'click', () => { menu.classList.add('hidden'); btn.setAttribute('aria-expanded','false'); });

  on(menu, 'click', (e) => {
    const a = e.target.closest('a[data-act]');
    if (!a) return;
    e.preventDefault();
    const act = a.dataset.act;
    const msgEl = document.querySelector(`.message-service[data-msg-id="${msgId}"]`);
    const bodyEl = msgEl ? msgEl.querySelector('.message-text') : null;
    const text = bodyEl ? bodyEl.textContent : '';
    if (act === 'reply')   handleReply(msgId, reqId, text);
    if (act === 'copy')    handleCopy(text);
    if (act === 'delete')  handleDelete(msgId, reqId);
    menu.classList.add('hidden');
  });

  return wrap;
}

function handleReply(msgId, reqId, text) {
  /* تعليق: وضع النص في صندوق التحرير مع اقتباس بسيط */
  if (els.messageInput) {
    const prefix = text ? `> ${text}\n` : '';
    els.messageInput.value = `${prefix}`;
    els.messageInput.focus();
  }
}
function handleCopy(text) {
  try { navigator.clipboard.writeText(text || ''); toast('تم النسخ'); }
  catch { toast('تعذّر النسخ'); }
}
async function handleDelete(msgId, reqId) {
  if (!msgId || !reqId) return;
  const result = await Swal.fire({
    title: 'تأكيد الحذف',
    text: 'تأكيد حذف الرسالة؟',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'نعم، حذف',
    cancelButtonText: 'تراجع',
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6'
  });
  if (!result.isConfirmed) return;
  try {
    const fd = addCsrf(new FormData());
    const res = await fetch(`/students/service-requests/${reqId}/messages/${msgId}/delete`, {
      method: 'POST', credentials: 'same-origin', body: fd
    }).then(r => r.json().catch(() => ({})));
    if (!res?.success) { toast(res?.message || 'فشل حذف الرسالة'); return; }
    const el = document.querySelector(`.message-service[data-msg-id="${msgId}"]`);
    removeNode(el);
  } catch (e) {
    LOG.e('delete message', e);
    toast('خطأ أثناء الحذف');
  }
}

/* ===================== بطاقات Tailwind للمرفقات ===================== */
function imgCard(src, name, sizeBytes) {
  const el = h(`
    <button type="button"
      class="group relative overflow-hidden rounded-xl bg-gray-50 dark:bg-gray-700 ring-1 ring-gray-200 dark:ring-gray-600">
      <img src="${src}" alt="${escapeHtml(name||'image')}"
           class="w-full h-auto max-h-72 object-cover group-hover:opacity-95" loading="lazy" />
      <div class="absolute bottom-0 inset-x-0 bg-black/50 text-white text-xs px-2 py-1 flex items-center justify-between">
        <span class="truncate">${escapeHtml(name||'Image')}</span>
        ${typeof sizeBytes==='number' ? `<span class="opacity-80">${humanMB(sizeBytes)}</span>` : ''}
      </div>
    </button>
  `);
  on(el, 'click', (e) => {
    if (e.metaKey || e.ctrlKey) { window.open(src, '_blank'); return; }
    openImageViewer(src, name || '');
  });
  return el;
}

function videoCard(src, name, sizeBytes, opts={poster:null}) {
  const poster = opts.poster || videoPlaceholderDataURL(name || 'VIDEO');
  const el = h(`
    <div class="rounded-xl overflow-hidden bg-gray-50 dark:bg-gray-700 ring-1 ring-gray-200 dark:ring-gray-600">
      <div class="relative">
        <!-- حاوية بنسبة أبعاد ديناميكية: نبدأ 16/9 ثم نضبطها فور تحميل الميتاداتا -->
        <div class="bg-gray-200 dark:bg-gray-600 aspect-video" data-video-frame>
          <video class="absolute inset-0 w-full h-full object-contain" controls preload="metadata" playsinline poster="${poster}" data-video-el>
            <source src="${src}">
          </video>
        </div>
        <div class="absolute bottom-0 inset-x-0 bg-black/50 text-white text-xs px-2 py-1 flex items-center justify-between">
          <span class="truncate">${escapeHtml(name||'Video')}</span>
          ${typeof sizeBytes==='number' ? `<span class="opacity-80">${humanMB(sizeBytes)}</span>` : ''}
        </div>
      </div>
    </div>
  `);
  const v = el.querySelector('[data-video-el]');
  const frame = el.querySelector('[data-video-frame]');
  // عند تحميل الميتاداتا: اضبط نسبة الأبعاد الفعلية بدون حسابات معقدة
  on(v, 'loadedmetadata', () => {
    const w = v.videoWidth || 16;
    const h = v.videoHeight || 9;
    frame.style.aspectRatio = `${w} / ${h}`; // Tailwind يدعم aspect-ratio عبر خاصية CSS أيضًا
  });
  on(v, 'error', () => { /* يمكن عرض ملاحظة أو إبقاء البوستر */ });
  return el;
}

function buildAudioBubble({ src, name, sizeBytes, warn=false, variant='voice' }) {
  const isVoice = variant === 'voice';
  const base = h(`
    <div class="w-full max-w-[320px]">
      <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-100 dark:bg-gray-700 ring-1 ring-gray-200 dark:ring-gray-600">
        <button type="button" class="audio-play inline-flex items-center justify-center w-9 h-9 rounded-full bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-600">
          <i class="fas fa-play"></i>
        </button>
        <div class="flex-1">
          <div class="audio-visual flex items-end gap-1 h-6">
            <span class="w-[3px] bg-gray-500 dark:bg-gray-300 h-2"></span>
            <span class="w-[3px] bg-gray-500 dark:bg-gray-300 h-3"></span>
            <span class="w-[3px] bg-gray-500 dark:bg-gray-300 h-4"></span>
            <span class="w-[3px] bg-gray-500 dark:bg-gray-300 h-3"></span>
            <span class="w-[3px] bg-gray-500 dark:bg-gray-300 h-2"></span>
          </div>
          <input type="range" min="0" max="1000" value="0" class="audio-slider mt-2 w-full h-1 rounded appearance-none bg-gray-200 dark:bg-gray-600" />
          ${!isVoice ? `<div class="mt-1 text-xs text-gray-600 dark:text-gray-300 flex items-center gap-2">
            <span class="audio-name truncate">${escapeHtml(name || 'Audio')}</span>
            ${typeof sizeBytes==='number' ? `<span class="opacity-70">${humanMB(sizeBytes)}</span>` : ''}
            ${warn ? `<span class="px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-700">معاينة فقط</span>` : ''}
          </div>` : ''}
        </div>
        <div class="audio-footer flex flex-col items-end gap-1">
          <span class="audio-time text-xs text-gray-600 dark:text-gray-300">0:00</span>
          <button type="button" class="audio-speed text-xs px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-600">1x</button>
        </div>
        <audio class="audio-element" src="${src}" preload="metadata"></audio>
      </div>
    </div>
  `);

  const playBtn = base.querySelector('.audio-play');
  const audio = base.querySelector('.audio-element');
  const slider = base.querySelector('.audio-slider');
  const speedBtn = base.querySelector('.audio-speed');
  const timeLbl = base.querySelector('.audio-time');

  const paintSlider = () => {
    const pct = Math.min(1, Math.max(0, slider.value / 1000));
    const percent = (pct * 100).toFixed(1);
    slider.style.background = `linear-gradient(90deg, var(--brand-500, #2563eb) ${percent}%, rgba(255,255,255,0.25) ${percent}%)`;
  };
  paintSlider();

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
    if (audio.paused) { audio.play().catch(()=>{}); } else { audio.pause(); }
  });
  on(slider, 'input', () => {
    if (!audio.duration) return;
    audio.currentTime = (slider.value / 1000) * audio.duration;
    paintSlider();
  });
  on(speedBtn, 'click', () => {
    const idx = speeds.indexOf(Number(audio.playbackRate) || 1);
    const next = speeds[(idx + 1) % speeds.length];
    audio.playbackRate = next; speedBtn.textContent = `${next}x`;
  });
  on(audio, 'play', () => { base.classList.add('is-playing'); playBtn.innerHTML = '<i class="fas fa-pause"></i>'; stopRaf(); paintSlider(); raf = requestAnimationFrame(pump); });
  on(audio, 'pause', () => { base.classList.remove('is-playing'); playBtn.innerHTML = '<i class="fas fa-play"></i>'; stopRaf(); paintSlider(); timeLbl.textContent = fmt(audio.currentTime); });
  on(audio, 'ended', () => { base.classList.remove('is-playing'); playBtn.innerHTML = '<i class="fas fa-play"></i>'; stopRaf(); slider.value = 0; paintSlider(); timeLbl.textContent = fmt(audio.duration || 0); });
  on(audio, 'error', () => { base.classList.add('has-error'); playBtn.disabled = true; speedBtn.disabled = true; paintSlider(); timeLbl.textContent = 'خطأ في التشغيل'; });

  return base;
}
function audioVoiceCard(src, name, sizeBytes, warn=false) { return buildAudioBubble({ src, name, sizeBytes, warn, variant: 'voice' }); }
function audioFileCard(src, name, sizeBytes, warn=false)  { return buildAudioBubble({ src, name, sizeBytes, warn, variant: 'file'  }); }

function documentCard(src, name, sizeBytes, isVideoIcon=false, warn=false) {
  const a = h(`
    <a class="group flex items-center gap-3 w-full max-w-[320px] p-3 rounded-xl bg-gray-50 dark:bg-gray-700 ring-1 ring-gray-200 dark:ring-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600" target="_blank" rel="noopener" href="${src}">
      <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-600">
        <i class="fas ${isVideoIcon ? 'fa-video' : 'fa-file-alt'}"></i>
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-medium text-gray-900 dark:text-white truncate">${escapeHtml(name || 'غير معيّن')}</div>
        <div class="text-xs text-gray-600 dark:text-gray-300">
          ${typeof sizeBytes === 'number' ? `${humanMB(sizeBytes)}` : ''}
          ${warn ? ` · <span class="px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-700">معاينة فقط</span>` : ''}
        </div>
      </div>
      <span class="shrink-0 w-8 h-8 inline-flex items-center justify-center rounded-full bg-blue-50 text-blue-700 group-hover:bg-blue-100"><i class="fas fa-download"></i></span>
    </a>
  `);
  return a;
}

/* ===================== عرض المرفقات في الرسائل / التفاصيل ===================== */
function renderFilesCards(container, files, opts={}) {
  const { isOutgoing = false } = opts;
  if (!container) return;
  container.innerHTML = '';
  (files || []).forEach((f) => {
    const mime  = String(f?.mime_type || '').toLowerCase();
    const isImg = mime.startsWith('image/');
    const isVid = isVideoLikeServer(f);
    const isAud = mime.startsWith('audio/');
    const isVoice = isVoiceRecordingServer(f);
    const src   = fileViewUrl(f);

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

/* ===================== رندر الرسالة الواحدة ===================== */
function getMessageId(m){ return m.id || m.message_id || m.msg_id || m.mid || `${m.created_at}|${m.is_me?'me':'oth'}`; }
function renderMessage(m) {
  const wrap = document.createElement('div');
  wrap.className = 'message-service ' + (m.is_me ? 'sentmsg' : 'receivedmsg');
  const mid = getMessageId(m);
  wrap.dataset.msgId = mid;

  const content = document.createElement('div');
  content.className = 'message-content';

  const senderName = String(m.sender_name || '').trim() || (m.is_me ? 'أنا' : 'الدعم');
  const head = document.createElement('div'); head.className = 'message-header';
  head.innerHTML = `<div><span class="sender-name">${escapeHtml(senderName)}</span></div><span class="message-time">${escapeHtml(m.created_at)}</span>`;
  content.appendChild(head);

  if (m.body) {
    const textNode = document.createElement('p');
    textNode.className = 'message-text';
    textNode.textContent = m.body || '';
    content.appendChild(textNode);
  }

  if (Array.isArray(m.files) && m.files.length) {
    const filesWrap = document.createElement('div');
    filesWrap.className = 'message-files';
    renderFilesCards(filesWrap, m.files, { isOutgoing: !!m.is_me });
    content.appendChild(filesWrap);
  }

  wrap.appendChild(content);

  // إضافة قائمة الإجراءات (Reply/Copy/Delete) إلى يمين الرسالة
  try {
    const menu = actionsMenu(mid, state.currentReqId || m.request_id || '');
    wrap.appendChild(menu);
  } catch(_){}

  return wrap;
}

/* ===================== دمج تفاضلي للرسائل ===================== */
state.renderedMsgIds = new Set();
function appendMessagesDifferential(items){
  const frag = document.createDocumentFragment();
  let added = 0;
  (items||[]).forEach(m=>{
    const mid = getMessageId(m);
    if (state.renderedMsgIds.has(mid)) return;
    state.renderedMsgIds.add(mid);
    frag.appendChild(renderMessage(m));
    added++;
  });
  if (added>0) { els.messagesList.appendChild(frag); scrollToBottom(); }
  return added;
}

/* ===================== واجهة ترقية الخطة (صوت) ===================== */
function goUpgrade(){
  if (window.StudentRouter?.go) { window.StudentRouter.go('upgrade'); return; }
  const link = document.querySelector('.menu-link[data-section="upgrade"]');
  if (link) { link.click(); return; }
  window.location.href = '/students/pages/upgrade';
}
function goUpgradeAndCloseAlert(){
  try { window.Swal?.close?.(); } catch (_) {}
  try { if (els?.upgradeHint) els.upgradeHint.style.display = 'none'; } catch (_) {}
  setTimeout(() => goUpgrade(), 0);
}
function renderInlineUpgradeHint(){
  if (!els.upgradeHint) return;
  els.upgradeHint.style.display = 'block';
  els.upgradeHint.innerHTML = `
    <div class="alert alert-warning" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
      <div style="display:flex;gap:8px;align-items:center;">
        <i class="fas fa-info-circle"></i>
        <span>التسجيل الصوتي متاح في الخطط الأعلى. قم بالترقية للاستفادة من هذه الميزة.</span>
      </div>
      <div style="display:flex;gap:8px;">
        <button id="svcGoUpgrade" class="btn" style="background:#219ebc;color:#fff;border:none;border-radius:10px;padding:8px 12px;font-weight:800;">
          الترقية الآن
        </button>
        <button id="svcCloseHint" class="btn" style="border:1px solid #e8e8ef;border-radius:10px;padding:8px 12px;">
          إغلاق
        </button>
      </div>
    </div>`;
  const go = $('#svcGoUpgrade', els.upgradeHint);
  const close = $('#svcCloseHint', els.upgradeHint);
  on(go, 'click', (e)=>{ e.preventDefault(); goUpgradeAndCloseAlert(); });
  on(close, 'click', (e)=>{ e.preventDefault(); els.upgradeHint.style.display='none'; });
}
function showPlanUpgradeAlert(){
  if (window?.Swal?.fire){
    Swal.fire({
      icon: 'info',
      title: 'ترقية مطلوبة',
      html: `
        <p style="margin:0 0 12px;">التسجيل الصوتي متاح في الخطط الأعلى.</p>
        <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
          <button id="swalGoUpg" class="swal2-confirm swal2-styled" style="background:#219ebc;">الترقية الآن</button>
          <button id="swalClose" class="swal2-cancel swal2-styled" style="background:#e5e7eb;color:#111;">إغلاق</button>
        </div>`,
      showConfirmButton: false,
      didOpen: () => {
        document.getElementById('swalGoUpg')?.addEventListener('click', ()=> goUpgradeAndCloseAlert());
        document.getElementById('swalClose')?.addEventListener('click', ()=> Swal.close());
      }
    });
  } else {
    renderInlineUpgradeHint();
  }
}

/* ===================== Top Meta ===================== */
async function loadTopMeta(){ try{
  LOG.g('loadTopMeta()');
  const res=await fetch('/students/service-requests/top-meta',{credentials:'same-origin'});
  const j=await res.json();
  LOG.i('top-meta',j); LOG.ge();
  if(!j?.success) return;

  state.planLevel = j.data.plan_level ?? 0;
  state.priority  = j.data.priority ?? 'low';
  state.canVoice  = !!j.data.canSendVoice;

  // سياسة الصوت: في الخطة 1 نعتبر الصوت غير متاح
  if (Number(state.planLevel) === 1) state.canVoice = false;

  const badge = `<span class="status-badge ${state.priority}" data-status="${state.priority}">${state.priority}</span>`;
  const btnUpg = (state.priority==='low'||state.priority==='medium')
    ? `<button id="btnUpgrade" class="btn" style="margin-inline-start:8px;"><i class="fas fa-rocket"></i> رَقِّ الآن</button>`
    : '';

  if (els.topMeta) {
    els.topMeta.innerHTML = `
      <div style="display:flex;align-items:center;gap:10px;">
        <strong>مستوى خطتك:</strong> <span>${state.planLevel}</span>
        <strong style="margin-inline-start:12px;">أولوية الطلبات:</strong> ${badge}
        ${btnUpg}
      </div>`;
  }

  if (btnUpg) {
    const upg = $('#btnUpgrade', els.root);
    on(upg, 'click', (e) => { e.preventDefault(); goUpgrade(); });
  }

  if (Number(state.planLevel) === 1) {
    renderInlineUpgradeHint();
    els.voiceBtn?.classList.add('disabled');
    els.voiceBtn?.setAttribute('aria-disabled','true');
    els.voiceBtn?.setAttribute('title','الميزة متاحة في الخطط الأعلى');
  } else {
    if (els.upgradeHint) els.upgradeHint.style.display='none';
    els.voiceBtn?.classList.remove('disabled');
    els.voiceBtn?.removeAttribute('aria-disabled');
    els.voiceBtn?.removeAttribute('title');
  }
}catch(e){ LOG.e('top-meta',e); } }

/* ===================== الفلاتر والقائمة ===================== */
function bindFilters(){
  $$('.filter-tabs .tab-btn',els.root).forEach(btn=>{
    on(btn,'click',()=>{
      $$('.filter-tabs .tab-btn',els.root).forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      state.reqStatus=btn.getAttribute('data-status-tab')||'';
      state.reqOffset=0; state.hasMoreReq=true; loadRequests(true);
    });
  });
  let t=null;
  on(els.search,'input',()=>{
    clearTimeout(t);
    t=setTimeout(()=>{
      state.reqQuery=els.search.value.trim();
      state.reqOffset=0; state.hasMoreReq=true;
      loadRequests(true);
    },700);
  });

  if (els.svcReqSelect) {
    on(els.svcReqSelect, 'change', (e) => {
      const id = e.target.value;
      if (id) openRequest(id);
    });
  }
}

function renderUnreadBadgeHtml(count){
  if (!count || count<=0) return '';
  return `<span class="unread-badge" style="display:inline-flex;align-items:center;justify-content:center;margin-inline-start:8px;min-width:18px;height:18px;padding:0 6px;background:#ef4444;color:#fff;border-radius:10px;font-size:12px;font-weight:800;line-height:18px;">${count}</span>`;
}

function syncRequestsDDL(items = [], { reset = false } = {}) {
  if (!els.svcReqSelect) return;
  const sel = els.svcReqSelect;
  if (reset) { while (sel.options.length > 1) sel.remove(1); sel.selectedIndex = 0; }
  const existing = new Set(Array.from(sel.options).map(o => String(o.value)));
  for (const it of (items || [])) {
    const id = String(it.id);
    if (existing.has(id)) continue;
    const opt = document.createElement('option');
    const unread = Number(it.unread_count ?? 0);
    opt.value = id;
    const unreadText = unread > 0 ? ` • ${unread} جديد` : '';
    opt.textContent = `${it.subject || 'بدون عنوان'} — ${it.created_at || ''}${unreadText}`;
    sel.appendChild(opt);
  }
  sel.disabled = sel.options.length <= 1;
}

function renderMoreButtons() {
  if (els.pagination) {
    els.pagination.innerHTML = '';
    if (state.hasMoreReq) {
      const btn = document.createElement('button');
      btn.className = 'btn';
      btn.textContent = 'أعرض المزيد';
      on(btn, 'click', () => loadRequests(false));
      els.pagination.appendChild(btn);
    }
  }
  if (els.paginationMobile) {
    els.paginationMobile.innerHTML = '';
    if (state.hasMoreReq) {
      const btnM = document.createElement('button');
      btnM.className = 'btn';
      btnM.textContent = 'عرض المزيد';
      on(btnM, 'click', () => loadRequests(false));
      els.paginationMobile.appendChild(btnM);
      els.paginationMobile.style.display = '';
    } else {
      els.paginationMobile.style.display = 'none';
    }
  }
}

async function loadRequests(reset=false){
  if(state.loadingList||(!state.hasMoreReq && !reset)) return;
  state.loadingList=true;
  LOG.g('loadRequests()', { reset, offset:state.reqOffset, limit:state.reqLimit, status:state.reqStatus, q:state.reqQuery });

  if(reset && els.list) {
    els.list.innerHTML = `<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i><p>جاري تحميل الطلبات...</p></div>`;
  }

  try{
    const p=new URLSearchParams({ status:state.reqStatus||'', q:state.reqQuery||'', offset:String(state.reqOffset), limit:String(state.reqLimit) });
    const res=await fetch('/students/service-requests/list?'+p.toString(),{credentials:'same-origin'});
    const j=await res.json();
    if(!j?.success){
      if (els.list) els.list.innerHTML=`<p style="padding:10px;">تعذّر تحميل الطلبات</p>`;
      state.loadingList=false; LOG.ge(); return;
    }

    if ($('#countAll',els.root))        $('#countAll',els.root).textContent       = j.data.counts?.all ?? 0;
    if ($('#countPending',els.root))    $('#countPending',els.root).textContent   = j.data.counts?.pending ?? 0;
    if ($('#countProgress',els.root))   $('#countProgress',els.root).textContent  = j.data.counts?.in_progress ?? 0;
    if ($('#countResolved',els.root))   $('#countResolved',els.root).textContent  = j.data.counts?.resolved ?? 0;
    if ($('#countCancelled',els.root))  $('#countCancelled',els.root).textContent = j.data.counts?.cancelled ?? 0;

    if(reset && els.list) els.list.innerHTML='';
    const items = j.data.items || [];
    syncRequestsDDL(items, { reset });

    for(const it of items){
      const el=document.createElement('div');
      el.className='request-item';
      el.dataset.reqId=it.id;
      const unread = Number(it.unread_count ?? 0);
      el.innerHTML=`
        <div class="request-title">
          <span class="status-indicator ${it.status}"></span>
          <span>${escapeHtml(it.subject || '—')}</span>
          ${renderUnreadBadgeHtml(unread)}
        </div>
        <div class="request-meta">
          <span class="course-name"><i class="fas fa-book"></i> ${escapeHtml(it.course_name || 'غير محدد')}</span>
          <span class="request-date"><i class="far fa-clock"></i> ${escapeHtml(it.created_at || '')}</span>
        </div>`;
      on(el,'click',()=> openRequest(it.id));
      els.list && els.list.appendChild(el);
      if (!state.unreadByReq) state.unreadByReq = new Map();
      state.unreadByReq.set(String(it.id), unread);
    }

    state.reqOffset += items.length;
    state.hasMoreReq = !!j.data.has_more;
    renderMoreButtons();
  }catch(e){
    LOG.e('loadRequests error',e);
    if (els.list) els.list.innerHTML=`<p style="padding:10px;">تعذّر تحميل الطلبات</p>`;
  } finally {
    state.loadingList=false;
    LOG.ge();
    try { await refreshPerRequestUnreadCounters(); } catch (_) {}
  }
}

/* ===================== عدّادات غير المقروء ===================== */
function computeTotalUnreadFromMap(){ let total = 0; if (state.unreadByReq) state.unreadByReq.forEach(v => total += (Number(v)||0)); return total; }
function updateSidebarServiceBadge_Fallback(total){
  const menu = document.querySelector('.menu-item.service-item .menu-link');
  if (!menu) return;
  let badge = document.getElementById('serviceRequestsBadge');
  if (!badge){
    badge = document.createElement('span');
    badge.id = 'serviceRequestsBadge';
    badge.className = 'badge unread-badge';
    menu.appendChild(badge);
  }
  if (total > 0){
    badge.textContent = String(total);
    badge.style.display = '';
  } else {
    badge.textContent = '';
    badge.style.display = 'none';
  }
}
function updateRequestRowUnread(reqId, newCount){
  if (!els.root) return;
  const row = $(`.request-item[data-req-id="${reqId}"]`, els.root);
  if (row) {
    const title = $('.request-title', row);
    if (title) {
      let badge = $('.unread-badge', title);
      if (newCount > 0) {
        if (!badge) {
          title.insertAdjacentHTML('beforeend', renderUnreadBadgeHtml(newCount));
          badge = $('.unread-badge', title);
        }
        if (badge) badge.textContent = String(newCount);
      } else if (badge) {
        badge.remove();
      }
    }
  }
  if (els.svcReqSelect) {
    const opt = Array.from(els.svcReqSelect.options).find(o=> String(o.value)===String(reqId));
    if (opt) {
      const base = (opt.textContent || '').replace(/ \u2022 \d+ جديد$/, '').replace(/ • \d+ جديد$/, '');
      opt.textContent = newCount>0 ? `${base} • ${newCount} جديد` : base;
    }
  }
  if (!state.unreadByReq) state.unreadByReq = new Map();
  state.unreadByReq.set(String(reqId), Number(newCount||0));
  const total = computeTotalUnreadFromMap();
  if (window.ServiceUnreadPoller?.set) {
    window.ServiceUnreadPoller.set(total);
  } else {
    updateSidebarServiceBadge_Fallback(total);
  }
}
async function refreshPerRequestUnreadCounters(){
  try {
    const res = await fetch('/students/service-requests/unread-counters', { credentials: 'same-origin' });
    const j = await res.json();
    if (!j?.success) return;
    const rows = j.data?.counters || [];
    state.unreadByReq = new Map();
    let total = 0;
    for (const row of rows) {
      const reqId = String(row.request_id);
      const count = Number(row.unread_count || 0);
      updateRequestRowUnread(reqId, count);
      total += count;
    }
    if (!window.ServiceUnreadPoller?.set) updateSidebarServiceBadge_Fallback(total);
  } catch (e) {
    LOG.w('refreshPerRequestUnreadCounters error', e);
  }
}

/* ===================== فتح الطلب + رسائل ===================== */
async function openRequest(reqId){
  state.currentReqId=reqId; state.beforeMsgId=null; state.hasMoreMsgs=true;
  state.renderedMsgIds = new Set();

  if (els.chatPlaceholder) els.chatPlaceholder.style.display='none';
  if (els.chatContainer)   els.chatContainer.style.display='flex';
  $$('.request-item',els.root).forEach(it=> it.classList.toggle('active', String(it.dataset.reqId)===String(reqId)));

  if (els.svcReqSelect) {
    const opt = Array.from(els.svcReqSelect.options).find(o => String(o.value) === String(reqId));
    if (opt) els.svcReqSelect.value = String(reqId);
  }

  try{
    LOG.g('openRequest()',reqId);
    const res=await fetch(`/students/service-requests/${reqId}`,{credentials:'same-origin'});
    const j=await res.json(); 
    if(!j?.success){ toast('تعذّر تحميل تفاصيل الطلب'); LOG.ge(); return; }

    const r=j.data.request;
    if ($('#chatRequestTitle',els.root)) { $('#chatRequestTitle',els.root).textContent=r.subject || ''; }
    const badge=$('#chatRequestStatus',els.root);
    if (badge){ badge.textContent=r.status || ''; badge.dataset.status=r.status || ''; }
    if ($('#chatRequestDate',els.root)) { $('#chatRequestDate',els.root).textContent = r.created_at || ''; }
    if ($('#requestDescription',els.root)) { $('#requestDescription',els.root).textContent = r.description || ''; }
    if ($('#requestCourse',els.root)) {
      $('#requestCourse',els.root).textContent = r.course_name ? `${r.course_name} — ${r.semester_name || ''}` : 'غير محدد';
    }

    renderFilesCards($('#requestAttachments',els.root), j.data.attachments || []);

    if (els.messagesList) els.messagesList.innerHTML='';
    await loadMessages(true);
    await ensureScrollable();
    scrollToBottom();
    LOG.ge();
  }catch(e){ LOG.e('openRequest error',e); }
}

/* ضمان قابلية التمرير للأعلى عبر ملء الحاوي */
async function ensureScrollable(){
  if (!els.messagesWrapper) return;
  let safety = 6; // أقصى عدد صفحات إضافية
  while (state.hasMoreMsgs && safety > 0) {
    const canScroll = els.messagesWrapper.scrollHeight > els.messagesWrapper.clientHeight + 16;
    if (canScroll) break;
    await loadMessages(false);
    safety--;
  }
}

function pickLastIncomingId(items=[]){
  let maxId = 0;
  for (const m of items) {
    const id = Number(m?.id || 0);
    if (!id) continue;
    if (!!m?.is_me) continue;
    if (typeof m?.is_read !== 'undefined' && Number(m.is_read) === 1) continue;
    if (id > maxId) maxId = id;
  }
  return maxId;
}
async function markReadsUpTo(lastId){
  if (!state.currentReqId || !lastId) return;
  try{
    const fd = addCsrf(new FormData());
    fd.append('last_message_id', String(lastId));
    await fetch(`/students/service-requests/${state.currentReqId}/read-up-to`, {
      method:'POST', credentials:'same-origin', body: fd
    }).then(r=>r.json().catch(()=>({})));
    updateRequestRowUnread(state.currentReqId, 0);
  }catch(e){ LOG.w('markReadsUpTo error', e); }
}

async function loadMessages(reset=false){
  if(!state.currentReqId || state.messagesLoading) return;
  if(!state.hasMoreMsgs && !reset) return;
  state.messagesLoading=true;
  const qs=new URLSearchParams({ limit:String(state.msgLimit), ...(state.beforeMsgId ? { before_id:String(state.beforeMsgId) } : {}) });
  LOG.g('loadMessages()',{ before:state.beforeMsgId, limit:state.msgLimit });
  try{
    const prevTopOffset = els.messagesWrapper.scrollHeight - els.messagesWrapper.scrollTop;
    const res=await fetch(`/students/service-requests/${state.currentReqId}/messages?`+qs.toString(),{credentials:'same-origin'});
    const j=await res.json(); 
    if(!j?.success){ state.messagesLoading=false; LOG.ge(); return; }
    const pageItems = j.data.items || [];
    if (state.beforeMsgId) {
      const frag = document.createDocumentFragment();
      pageItems.forEach(m=>{
        const mid = getMessageId(m);
        if (state.renderedMsgIds.has(mid)) return;
        state.renderedMsgIds.add(mid);
        frag.appendChild(renderMessage(m));
      });
      els.messagesList.prepend(frag);
      els.messagesWrapper.scrollTop = els.messagesWrapper.scrollHeight - prevTopOffset;
    } else {
      appendMessagesDifferential(pageItems);
      const lastIncoming = pickLastIncomingId(pageItems);
      if (lastIncoming) await markReadsUpTo(lastIncoming);
    }
    state.beforeMsgId = j.data.next_before_id || null;
    state.hasMoreMsgs = !!j.data.has_more;
    LOG.i('msgs page', { received: pageItems.length, next_before_id: state.beforeMsgId, has_more: state.hasMoreMsgs });
  }catch(e){ LOG.e('loadMessages error',e); }
  finally{ state.messagesLoading=false; LOG.ge(); }
}

async function refreshLatestMessagesOnce(){
  if (!state.currentReqId) return 0;
  try{
    const qs=new URLSearchParams({ limit:String(state.msgLimit) });
    const res=await fetch(`/students/service-requests/${state.currentReqId}/messages?`+qs.toString(),{credentials:'same-origin'});
    const j=await res.json();
    if (!j?.success) return 0;
    const items = j.data.items||[];
    const added = appendMessagesDifferential(items);
    const lastIncoming = pickLastIncomingId(items);
    if (lastIncoming) await markReadsUpTo(lastIncoming);
    return added;
  }catch(e){ LOG.w('refreshLatestMessagesOnce error', e); return 0; }
}

function bindMessagesScroll(){
  on(els.messagesWrapper,'scroll',async()=>{
    if(!els.messagesWrapper) return;
    const top = els.messagesWrapper.scrollTop;
    if (top <= 0 && state.beforeMsgId && !state.messagesLoading){
      await loadMessages(false);
      await ensureScrollable();
    }
  }, {passive:true});
}

/* ===================== شريط تقدم الرفع ===================== */
function ensureUploadBar(){
  let bar = $('#uploadProgressBar', els.root);
  if (bar) return bar;
  bar = document.createElement('div');
  bar.id = 'uploadProgressBar';
  bar.style.cssText = 'display:none; margin:8px 0; padding:8px; border-radius:8px; background:#f5f7fb; border:1px solid #e3e7ef;';
  bar.innerHTML = `
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
      <strong style="font-size:14px;">جاري رفع الملفات…</strong>
      <span class="up-stats" style="font-size:12px;color:#555;">0% • 0.00 MB/s • ETA --:--</span>
    </div>
    <div class="up-bar-wrap" style="position:relative;height:8px;background:#e9edf5;border-radius:6px;margin-top:8px;overflow:hidden;">
      <div class="up-bar" style="position:absolute;left:0;top:0;height:100%;width:0%;background:#4f8df7;"></div>
    </div>`;
  els.pendingAttachments?.parentElement?.insertBefore(bar, els.pendingAttachments);
  return bar;
}
function showUploadBar(){ const bar=ensureUploadBar(); bar.style.display='block'; }
function hideUploadBar(){ const bar=$('#uploadProgressBar', els.root); if (bar) bar.style.display='none'; }
function updateUploadBar({ loaded, total, startedAt }){
  const bar=$('#uploadProgressBar', els.root); if (!bar) return;
  const pct = total>0 ? Math.min(100, Math.round(loaded/total*100)) : 0;
  const elapsed = (Date.now() - startedAt) / 1000;
  const speed   = elapsed>0 ? (loaded/elapsed) : 0;
  const remain  = total>0 && speed>0 ? (total-loaded)/speed : 0;
  $('.up-bar', bar).style.width = pct+'%';
  $('.up-stats', bar).textContent = `${pct}% • ${(speed/1024/1024).toFixed(2)} MB/s • ETA ${fmtTime(remain|0)}`;
}
function postWithProgress(url, fd, onProgress){
  return new Promise((resolve, reject)=>{
    const xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.onreadystatechange = ()=> {
      if (xhr.readyState === 4) {
        try { resolve({ status: xhr.status, json: JSON.parse(xhr.responseText||'{}') }); }
        catch { resolve({ status: xhr.status, text: xhr.responseText }); }
      }
    };
    xhr.onerror = ()=> reject(new Error('network error'));
    if (xhr.upload && typeof onProgress === 'function') {
      xhr.upload.addEventListener('progress', onProgress);
    }
    xhr.withCredentials = true;
    xhr.send(fd);
  });
}

/* ===================== Composer: مرفقات + إرسال ===================== */
function addSendingPlaceholder({ bodyText, hasFiles }){
  const row = document.createElement('div');
  row.className = 'message-service sentmsg sending';
  row.dataset.placeholder = '1';
  row.innerHTML = `
    <div class="message-content">
      <div class="message-header">
        <div><span class="sender-name">أنت</span></div>
        <span class="message-time">الآن</span>
      </div>
      ${bodyText ? `<p class="message-text">${bodyText.replace(/</g,'&lt;')}</p>` : ''}
      ${hasFiles ? `<div class="message-files"><div class="file-card"><i class="fas fa-spinner fa-spin"></i> جاري معالجة المرفقات…</div></div>` : ''}
    </div>`;
  els.messagesList.appendChild(row); scrollToBottom();
  return row;
}
function removeNode(n){ try{ n?.remove?.(); }catch(_){ if(n && n.parentNode) n.parentNode.removeChild(n); } }

function bindComposer(){
  on(els.btnAttachFile, 'click', ()=> els.chatFileInput?.click());
  on(els.chatFileInput, 'change', (e)=>{ addPendingFiles(e.target.files); e.target.value=''; });
  on(els.btnSendMessage, 'click', sendMessage);
  on(els.messageInput, 'keydown', (ev)=>{ if (ev.key==='Enter' && !ev.shiftKey){ ev.preventDefault(); sendMessage(); } });
  on(els.voiceBtn, 'click', startRecording);
  on(els.btnStopRecording, 'click', stopRecordingAndQueue);
  on(els.btnCancelRecording, 'click', cancelRecording);
}

function addPendingFiles(fileList){
  const files = Array.from(fileList || []);
  let added = 0;
  for (const file of files) {
    const effMime = (getEffectiveMime(file) || '').toLowerCase();
    const ext = getFileExt(file.name);
    const serverAllowed = SERVER_ALLOWED_MIME.has(effMime) || SERVER_ALLOWED_MIME.has(EXT_TO_MIME[ext] || '');
    const previewOnly = !!PREVIEW_ONLY_EXT_TO_MIME[ext] && !serverAllowed;
    if (!serverAllowed && !previewOnly) {
      toast('نوع الملف غير مدعوم: ' + file.name);
      continue;
    }
    if (file.size > MAX_FILE_BYTES) {
      toast(`حجم الملف ${humanMB(file.size)} يتجاوز الحد (${humanMB(MAX_FILE_BYTES)}) : ${file.name}`);
      continue;
    }
    file._effectiveMime = effMime || EXT_TO_MIME[ext] || PREVIEW_ONLY_EXT_TO_MIME[ext] || '';
    file._serverAllowed = !!serverAllowed;
    file._previewOnlyWarn = !!previewOnly;
    state.pendingFiles.push(file);
    added++;
  }
  LOG.i('addPendingFiles()', { added, totalPending: state.pendingFiles.length });
  renderPendingPreviews();
  if (files.length > 0 && added === 0) toast('لم يتم قبول أي من الملفات المرفوعة.');
}

async function renderPendingPreviews(){
  if (!els.pendingAttachments) return;
  els.pendingAttachments.innerHTML = '';

  const removeBtnHtml = `
    <button type="button" class="remove-btn" title="إزالة"
      style="position:absolute;top:6px;inset-inline-end:6px;width:28px;height:28px;border-radius:50%;background:#ef4444;border:none;color:#fff;cursor:pointer;">
      <i class="fas fa-times"></i>
    </button>`;

  for (let idx = 0; idx < state.pendingFiles.length; idx++) {
    const file = state.pendingFiles[idx];
    const mime = (file._effectiveMime || file.type || '').toLowerCase();
    const isVoice = isVoiceRecordingLocal(file);
    const warn = !!file._previewOnlyWarn && !file._serverAllowed;
    const url = URL.createObjectURL(file);
    const cleanup = () => { try { URL.revokeObjectURL(url); } catch(_){} };

    let card = null;
    if (isVoice) {
      card = audioVoiceCard(url, file.name, file.size, warn);
    } else if (mime.startsWith('image/')) {
      card = imgCard(url, file.name, file.size);
    } else if (mime.startsWith('video/')) {
      const poster = await makeVideoPosterFromFile(file).catch(() => videoPlaceholderDataURL(file.name));
      card = videoCard(url, file.name, file.size, { poster });
    } else if (mime.startsWith('audio/')) {
      card = audioFileCard(url, file.name, file.size, warn);
    } else {
      card = documentCard(url, file.name, file.size, false, warn);
    }

    if (!card) { cleanup(); continue; }
    card.style.position = 'relative';
    card.insertAdjacentHTML('afterbegin', removeBtnHtml);
    const btn = card.querySelector('.remove-btn');
    if (btn) {
      on(btn, 'click', (evt) => {
        evt.preventDefault();
        evt.stopPropagation();
        cleanup();
        state.pendingFiles.splice(idx, 1);
        renderPendingPreviews();
        LOG.i('pending file removed',{name:file.name});
      });
    }
    if (warn) card.classList.add('has-warning');
    els.pendingAttachments.appendChild(card);
  }
}

async function sendMessage(){
  if(!state.currentReqId) return toast('اختر طلبًا أولاً');
  const body = els.messageInput?.value?.trim() || '';
  if(!body && state.pendingFiles.length===0){ toast('اكتب رسالة أو أرفق ملفًا'); return; }

  const fd = new FormData();
  if (body) fd.append('body', body);
  state.pendingFiles.forEach(f=> fd.append('files[]', f, f.name));
  addCsrf(fd);

  const startedAt = Date.now();
  const hasFiles = state.pendingFiles.length>0;
  const placeholder = addSendingPlaceholder({ bodyText: body, hasFiles });

  showUploadBar();
  els.btnSendMessage?.setAttribute('disabled','disabled');
  els.btnAttachFile?.setAttribute('disabled','disabled');
  els.voiceBtn?.setAttribute('disabled','disabled');

  LOG.g('sendMessage(XHR)', { body: !!body, files: state.pendingFiles.length, totalApproxMB: humanMB([...state.pendingFiles].reduce((a,f)=>a+f.size,0)) });

  try{
    const res = await postWithProgress(`/students/service-requests/${state.currentReqId}/messages`, fd, (e)=>{
      if (e.lengthComputable) updateUploadBar({ loaded: e.loaded, total: e.total, startedAt });
      else $('.up-stats', ensureUploadBar()).textContent = '... جارٍ الرفع';
    });
    const j = res.json || {};
    if (res.status<200 || res.status>=300 || !j?.success){
      toast((j?.message || 'فشل الإرسال') + (j?.ref ? ` [${j.ref}]` : ''));
      return;
    }
    if (els.messageInput) els.messageInput.value = '';
    state.pendingFiles = [];
    renderPendingPreviews();

    const tries = hasFiles ? 5 : 2;
    let addedTotal = 0;
    for (let i=0;i<tries;i++){
      const added = await refreshLatestMessagesOnce();
      addedTotal += added;
      if (added>0 && !hasFiles) break;
      await new Promise(r=> setTimeout(r, 700 + i*300));
    }
    if (addedTotal>0) removeNode(placeholder);

    await ensureScrollable();
    scrollToBottom();
  }catch(err){
    LOG.e('submit error', err);
    toast('❌ خطأ أثناء رفع الملف/الرسالة');
  }finally{
    hideUploadBar();
    els.btnSendMessage?.removeAttribute('disabled');
    els.btnAttachFile?.removeAttribute('disabled');
    if (state.canVoice) els.voiceBtn?.removeAttribute('disabled');
    LOG.ge();
  }
}

/* ===================== التسجيل الصوتي ===================== */
function pickBestAudioMime() {
  const prefs = [
    { mime: 'audio/ogg;codecs=opus',  ext: 'ogg'  },
    { mime: 'audio/webm;codecs=opus', ext: 'weba' },
    { mime: 'audio/webm',             ext: 'weba' },
  ];
  for (const p of prefs) {
    if (window.MediaRecorder?.isTypeSupported?.(p.mime)) return p;
  }
  return { mime: 'audio/webm', ext: 'weba' };
}
let REC_AUDIO_FMT = pickBestAudioMime();

async function startRecording(){
  if (Number(state.planLevel) === 1 || !state.canVoice) {
    showPlanUpgradeAlert();
    return;
  }
  try{
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    state.mediaRecorder = new MediaRecorder(stream, { mimeType: REC_AUDIO_FMT.mime });
    state.recordingChunks = [];
    state.mediaRecorder.ondataavailable = (e) => e.data.size && state.recordingChunks.push(e.data);
    state.mediaRecorder.onstop = () => {};
    state.mediaRecorder.start();
    state.recordingStart = Date.now();
    if (els.voiceRecording) els.voiceRecording.style.display = 'flex';
    animateTimer();
  }catch(e){
    LOG.e('startRecording error', e);
    toast('تعذر بدء التسجيل الصوتي');
  }
}
function animateTimer(){
  const tick=()=>{
    if(!els.voiceRecording || els.voiceRecording.style.display==='none') return;
    const s=Math.floor((Date.now()-state.recordingStart)/1000);
    const m=String(Math.floor(s/60)).padStart(2,'0');
    const ss=String(s%60).padStart(2,'0');
    if (els.recordingTime) els.recordingTime.textContent=`${m}:${ss}`;
    requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
}
function stopRecordingAndQueue(){
  if(!state.mediaRecorder) return;
  state.mediaRecorder.onstop = () => {
    const blob = new Blob(state.recordingChunks, { type: REC_AUDIO_FMT.mime });
    const fileName = `voice_${Date.now()}.${REC_AUDIO_FMT.ext}`;
    const file = new File([blob], fileName, { type: REC_AUDIO_FMT.mime });
    state.pendingFiles.push(file);
    renderPendingPreviews();
    if (els.voiceRecording) els.voiceRecording.style.display = 'none';
    try { state.mediaRecorder.stream.getTracks().forEach(t => t.stop()); } catch(_) {}
    state.mediaRecorder = null;
  };
  state.mediaRecorder.stop();
}
function cancelRecording(){
  if(state.mediaRecorder){
    try{ state.mediaRecorder.stream.getTracks().forEach(t=>t.stop()); }catch(_){ }
    state.mediaRecorder=null;
  }
  if (els.voiceRecording) els.voiceRecording.style.display='none';
}

/* ===================== Toggles متنوعة ===================== */
function bindToggles(){
  on(els.btnToggleRequestDetails,'click',()=>{
    const el=els.requestDetails;
    if (!el) return;
    el.style.display = (el.style.display==='none'||!el.style.display)?'block':'none';
  });

  on(els.cancelRequestBtn,'click',async()=>{
    if(!state.currentReqId) return;
    const doCancel = async () => {
      const fd=addCsrf(new FormData());
      const res=await fetch(`/students/service-requests/${state.currentReqId}/cancel`,{method:'POST',body:fd,credentials:'same-origin'});
      const j=await res.json();
      if(!j?.success){ toast(j?.message||'فشل الإلغاء'); return; }
      toast('تم إلغاء الطلب');
      state.reqOffset=0; state.hasMoreReq=true;
      await loadRequests(true);
      await openRequest(state.currentReqId);
    };
    if(window.Swal?.fire){
      const r=await Swal.fire({icon:'warning', text:'هل تريد إلغاء الطلب؟', showCancelButton:true, confirmButtonText:'نعم'});
      if (r.isConfirmed) await doCancel();
    } else {
      if (confirm('هل تريد إلغاء الطلب؟')) await doCancel();
    }
  });

  on(els.btnNewRequest,'click',()=>{
    try{
      if(window.ServiceRequestModal?.ensureMounted) window.ServiceRequestModal.ensureMounted();
      else if(window.ServiceRequestModal?.init)     window.ServiceRequestModal.init();
    }catch(_){ }
    window.ServiceRequestModal?.open?.();
  });
}

/* ===================== Mount / Unmount ===================== */
function mount(rootEl=document){
  LOG.i(`MOUNT ${JS_VERSION} at`, new Date().toISOString());
  els = {
    root: rootEl.querySelector('#serviceRoot') || rootEl,
    csrfName: rootEl.querySelector('#__csrf_name'),
    csrfHash: rootEl.querySelector('#__csrf_hash'),
    topMeta: rootEl.querySelector('#serviceTopMeta'),
    upgradeHint: rootEl.querySelector('#serviceUpgradeHint'),
    list: rootEl.querySelector('#serviceList'),
    pagination: rootEl.querySelector('#svcPagination'),
    search: rootEl.querySelector('#svcSearch'),
    chatPlaceholder: rootEl.querySelector('#chatPlaceholder'),
    chatContainer: rootEl.querySelector('#chatContainer'),
    messagesWrapper: rootEl.querySelector('#messagesWrapper'),
    messagesList: rootEl.querySelector('#messagesList'),
    requestDetails: rootEl.querySelector('#requestDetails'),
    btnToggleRequestDetails: rootEl.querySelector('#btnToggleRequestDetails'),
    cancelRequestBtn: rootEl.querySelector('#cancelRequestBtn'),
    btnAttachFile: rootEl.querySelector('#btnAttachFile'),
    chatFileInput: rootEl.querySelector('#chatFileInput'),
    pendingAttachments: rootEl.querySelector('#chatPendingAttachments'),
    messageInput: rootEl.querySelector('#messageInput'),
    btnSendMessage: rootEl.querySelector('#btnSendMessage'),
    voiceBtn: rootEl.querySelector('#voiceBtn'),
    voiceRecording: rootEl.querySelector('#voiceRecording'),
    btnStopRecording: rootEl.querySelector('#btnStopRecording'),
    btnCancelRecording: rootEl.querySelector('#btnCancelRecording'),
    recordingTime: rootEl.querySelector('#recordingTime'),
    btnNewRequest: rootEl.querySelector('#svcNewRequestBtn'),
    svcReqSelect: rootEl.querySelector('#svcReqSelect'),
    requestsDdlWrap: rootEl.querySelector('#requestsDdlWrap'),
    paginationMobile: rootEl.querySelector('#svcPaginationMobile'),
  };

  LOG.i('elements:', { hasRoot:!!els.root, hasWrapper:!!els.messagesWrapper, hasList:!!els.messagesList, hasBtnSend:!!els.btnSendMessage });

  state = {
    reqOffset:0, reqLimit:5, reqStatus:'', reqQuery:'', loadingList:false, hasMoreReq:true,
    currentReqId:null, msgLimit:5, beforeMsgId:null, messagesLoading:false, hasMoreMsgs:true,
    pendingFiles:[], mediaRecorder:null, recordingChunks:[], recordingStart:0,
    canVoice:false, planLevel:0, priority:'low',
    unreadByReq: new Map(),
  };
  state.renderedMsgIds = new Set();

  bindFilters(); bindComposer(); bindMessagesScroll(); bindToggles();
  loadTopMeta(); loadRequests(true);

  refreshPerRequestUnreadCounters();
  on(window, 'service:unread-total', () => { refreshPerRequestUnreadCounters(); });

  try{
    if(window.ServiceRequestModal?.ensureMounted){ window.ServiceRequestModal.ensureMounted(); }
    else if(window.ServiceRequestModal?.init){ window.ServiceRequestModal.init(); }
  }catch(e){ LOG.w('ServiceRequestModal init skipped:', e?.message); }
}

function unmount(){
  LOG.i('UNMOUNT');
  try{ listeners.forEach(off=>off()); }catch(_){ }
  listeners=[];
  try{ if(state?.mediaRecorder) state.mediaRecorder.stream.getTracks().forEach(t=>t.stop()); }catch(_){ }
  els={}; state={};
}

/* ===================== Router API ===================== */
const ServicePage = { mount, unmount };
export default ServicePage;
window.ServicePage = ServicePage;
window.reloadServiceRequests = async function(){ try{ state.reqOffset=0; state.hasMoreReq=true; await loadRequests(true); }catch(_){} };
window.loadRequest = function(id){ if(id) openRequest(id); };
