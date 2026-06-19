/* =========================================================================
 *  Service Requests: Chat Page (Dynamic Mount)
 *  File: public/assets/js/students/service.js
 *  تحميل ديناميكي عبر student_router.js
 * =========================================================================
 */

window.__SERVICE_DEBUG = true;
const DBG = (...args)=> { try{ if (window.__SERVICE_DEBUG) console.log('[service]', ...args); }catch(_){} };

const ALLOWED_MIME = new Set([
  // صور
  'image/jpeg','image/png','image/gif','image/webp',
  // فيديو
  'video/mp4','video/webm','video/ogg',
  // مستندات
  'application/pdf',
  'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
  // أرشيفات
  'application/zip','application/x-rar-compressed',
  // صوتيات
  'audio/mpeg','audio/mp4','audio/ogg','audio/webm','audio/wav'
]);

const MAX_FILE_BYTES = 10 * 1024 * 1024; // 10MB

// مراجع / حالة الصفحة — تُعاد تهيئتها في كل Mount
let els = {};
let state = {};
let listeners = []; // لتجميع وفصل الأحداث في unmount

// ====== أدوات مساعدة عامة ======
const $  = (s, c=document)=> c.querySelector(s);
const $$ = (s, c=document)=> Array.from(c.querySelectorAll(s));
const on = (el, ev, fn, opts)=> { if (!el) return; el.addEventListener(ev, fn, opts); listeners.push(()=> el.removeEventListener(ev, fn, opts)); };
const once = (el, ev, fn, opts)=> { const wrap = (e)=>{ fn(e); el.removeEventListener(ev, wrap, opts); }; on(el, ev, wrap, opts); };
const toast = (m)=> (window?.Swal?.fire ? Swal.fire({icon:'info', text:String(m)}) : alert(m));

function addCsrf(fd) {
  const name = els.csrfName?.value;
  const hash = els.csrfHash?.value;
  if (name && hash) fd.append(name, hash);
  return fd;
}

function scrollToBottom() {
  if (!els.messagesWrapper) return;
  els.messagesWrapper.scrollTop = els.messagesWrapper.scrollHeight + 999;
}

function humanMB(bytes) {
  return (bytes/1024/1024).toFixed(2) + ' MB';
}

function renderFilesCards(container, files) {
  DBG('renderFilesCards()', {count: files?.length||0, files});
  container.innerHTML = '';
  files.forEach(f=>{
    const isImg  = (f.mime_type||'').startsWith('image/');
    const isVid  = (f.mime_type||'').startsWith('video/');
    const isAud  = (f.mime_type||'').startsWith('audio/');

    if (isAud) {
      const wrap = document.createElement('div');
      wrap.className = 'file-card';
      wrap.innerHTML = `
        <div class="meta">
          <div class="name">${f.file_name}</div>
          <div class="sub">${humanMB(f.file_size_bytes || 0)}</div>
        </div>
        <div class="voice-message" style="margin-top:8px;">
          <audio controls preload="metadata" src="/file-view?id=${encodeURIComponent(f.file_id)}"></audio>
        </div>
      `;
      container.appendChild(wrap);
      return;
    }

    const a = document.createElement('a');
    a.className = 'file-card has-actions';
    a.href = `/file-view?id=${encodeURIComponent(f.file_id)}${isImg? '&disposition=inline':''}`;
    a.target = '_blank';
    a.rel = 'noopener';
    a.innerHTML = `
      <div class="thumb ${isImg ? 'img-thumb' : 'icon-thumb'}">
        ${isImg ? `<img src="/file-view?id=${f.file_id}&disposition=inline" alt="${f.file_name}">`
                 : `<i class="fas ${isVid ? 'fa-video' : 'fa-file-alt'}"></i>`}
      </div>
      <div class="meta">
        <div class="name">${f.file_name}</div>
        <div class="sub">${humanMB(f.file_size_bytes||0)}</div>
      </div>
      <span class="fab-download"><i class="fas fa-download"></i></span>
    `;
    container.appendChild(a);
  });
}

function renderMessage(m) {
  const wrap = document.createElement('div');
  wrap.className = 'message-service ' + (m.is_me ? 'sentmsg' : 'receivedmsg');

  const content = document.createElement('div');
  content.className = 'message-content';

  const who = m.is_me ? 'أنت' : (m.sender_name || 'الدعم');
  const head = document.createElement('div');
  head.className = 'message-header';
  head.innerHTML = `<div><span class="sender-name">${who}</span></div><span class="message-time">${m.created_at}</span>`;

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
      const isImg=(f.mime_type||'').startsWith('image/');
      const isVid=(f.mime_type||'').startsWith('video/');
      const isAud=(f.mime_type||'').startsWith('audio/');

      if (isAud) {
        const v = document.createElement('div');
        v.className = 'voice-message';
        v.innerHTML = `
          <div class="meta" style="margin-bottom:6px;">
            <div class="name">${f.file_name}</div>
            <div class="sub">${humanMB(f.file_size_bytes||0)}</div>
          </div>
          <audio controls preload="metadata" src="/file-view?id=${encodeURIComponent(f.file_id)}"></audio>
        `;
        filesWrap.appendChild(v);
        return;
      }

      const a = document.createElement('a');
      a.className = 'file-card';
      a.href = `/file-view?id=${encodeURIComponent(f.file_id)}${isImg ? '&disposition=inline':''}`;
      a.target = '_blank';
      a.rel = 'noopener';
      a.innerHTML = `
        <div class="thumb ${isImg ? 'img-thumb':'icon-thumb'}">
          ${isImg ? `<img src="/file-view?id=${f.file_id}&disposition=inline" alt="${f.file_name}">`
                   : `<i class="fas ${isVid ? 'fa-video':'fa-file-alt'}"></i>`}
        </div>
        <div class="meta">
          <div class="name">${f.file_name}</div>
          <div class="sub">${humanMB(f.file_size_bytes||0)}</div>
        </div>
      `;
      filesWrap.appendChild(a);
    });
    content.appendChild(filesWrap);
  }

  wrap.appendChild(content);
  return wrap;
}

// ====== تحميل الميتا العليا (الخطة/الأولوية) ======
async function loadTopMeta() {
  try {
    DBG('loadTopMeta(): start');
    const res = await fetch('/students/service-requests/top-meta', {credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}});
    const j = await res.json();
    DBG('loadTopMeta(): response', j);
    if (!j?.success) return;

    state.planLevel = j.data.plan_level ?? 0;
    state.priority  = j.data.priority ?? 'low';
    state.canVoice  = !!j.data.canSendVoice;

    const badge = `<span class="status-badge ${state.priority}" data-status="${state.priority}">${state.priority}</span>`;
    const btnUpg = (state.priority === 'low' || state.priority === 'medium')
      ? `<button id="btnUpgrade" class="btn" style="margin-inline-start:8px;"><i class="fas fa-rocket"></i> رَقِّ الآن</button>`
      : '';

    els.topMeta.innerHTML = `
      <div style="display:flex;align-items:center;gap:10px;">
        <strong>مستوى خطتك:</strong> <span>${state.planLevel}</span>
        <strong style="margin-inline-start:12px;">أولوية الطلبات:</strong> ${badge}
        ${btnUpg}
      </div>
    `;

    if (btnUpg) {
      const upg = $('#btnUpgrade', els.root);
      on(upg, 'click', ()=> toast('سيتم ربط زر الترقية قريبًا.'));
    }

    if (!state.canVoice) {
      els.voiceBtn?.setAttribute('disabled','disabled');
      els.voiceBtn?.classList.add('disabled');
      els.upgradeHint.style.display = 'block';
      els.upgradeHint.innerHTML = `<div class="alert alert-warning">
        ${j.data.upgrade_suggest || 'بعض الميزات تتطلب خطة أعلى.'}
      </div>`;
    } else {
      els.upgradeHint.style.display = 'none';
      els.voiceBtn?.removeAttribute('disabled');
      els.voiceBtn?.classList.remove('disabled');
    }

  } catch (e) {
    console.error('[service] top-meta', e);
  }
}

// ====== قائمة الطلبات ======
function bindFilters() {
  DBG('bindFilters(): attach handlers');
  // تبويبات الحالة
  $$('.filter-tabs .tab-btn', els.root).forEach(btn=>{
    on(btn, 'click', ()=>{
      DBG('filter-tab clicked', btn.getAttribute('data-status-tab'));
      $$('.filter-tabs .tab-btn', els.root).forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      state.reqStatus = btn.getAttribute('data-status-tab') || '';
      state.reqOffset = 0;
      state.hasMoreReq= true;
      loadRequests(true);
    });
  });
  // البحث — 700ms
  let t = null;
  on(els.search, 'input', ()=>{
    clearTimeout(t);
    t = setTimeout(()=>{
      state.reqQuery = els.search.value.trim();
      DBG('search debounced', state.reqQuery);
      state.reqOffset = 0;
      state.hasMoreReq= true;
      loadRequests(true);
    }, 700);
  });
}

async function loadRequests(reset=false) {
  if (state.loadingList || (!state.hasMoreReq && !reset)) return;
  state.loadingList = true;

  DBG('loadRequests(): start', {reset, status: state.reqStatus, q: state.reqQuery, offset: state.reqOffset, limit: state.reqLimit});

  if (reset) {
    els.list.innerHTML = `<div class="loading-placeholder">
      <i class="fas fa-spinner fa-spin"></i><p>جاري تحميل الطلبات...</p></div>`;
  }

  const p = new URLSearchParams({
    status: state.reqStatus || '',
    q: state.reqQuery || '',
    offset: String(state.reqOffset),
    limit: String(state.reqLimit),
  });

  const res = await fetch('/students/service-requests/list?' + p.toString(), {credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}});
  const j = await res.json();
  DBG('loadRequests(): response', j);

  if (!j?.success) {
    els.list.innerHTML = `<p style="padding:10px;">تعذّر تحميل الطلبات</p>`;
    state.loadingList = false; return;
  }

  // العدادات
  $('#countAll', els.root).textContent       = j.data.counts?.all ?? 0;
  $('#countPending', els.root).textContent   = j.data.counts?.pending ?? 0;
  $('#countProgress', els.root).textContent  = j.data.counts?.in_progress ?? 0;
  $('#countResolved', els.root).textContent  = j.data.counts?.resolved ?? 0;
  $('#countCancelled', els.root).textContent = j.data.counts?.cancelled ?? 0;

  if (reset) els.list.innerHTML = '';

  for (const it of (j.data.items||[])) {
    const el = document.createElement('div');
    el.className = 'request-item';
    el.dataset.reqId = it.id;
    el.innerHTML = `
      <div class="request-title">
        <span class="status-indicator ${it.status}"></span>
        <span>${it.subject}</span>
      </div>
      <div class="request-meta">
        <span class="course-name"><i class="fas fa-book"></i> ${it.course_name || 'غير محدد'}</span>
        <span class="request-date"><i class="far fa-clock"></i> ${it.created_at}</span>
      </div>
    `;
    on(el, 'click', ()=> { DBG('request clicked', it.id); openRequest(it.id); });
    els.list.appendChild(el);
  }

  state.reqOffset += (j.data.items||[]).length;
  state.hasMoreReq = !!j.data.has_more;

  // زر المزيد
  els.pagination.innerHTML = '';
  if (state.hasMoreReq) {
    const btn = document.createElement('button');
    btn.className = 'btn';
    btn.textContent = 'أعرض المزيد';
    on(btn, 'click', ()=> { DBG('load more clicked'); loadRequests(false); });
    els.pagination.appendChild(btn);
  }

  state.loadingList = false;
  DBG('loadRequests(): done', {offset: state.reqOffset, hasMore: state.hasMoreReq});
}

// ====== تفاصيل الطلب + الرسائل ======
async function openRequest(reqId) {
  state.currentReqId = reqId;
  state.beforeMsgId  = null;

  DBG('openRequest(): start', {reqId});

  els.chatPlaceholder.style.display = 'none';
  els.chatContainer.style.display   = 'flex';

  // تمييز
  $$('.request-item', els.root).forEach(it=> it.classList.toggle('active', String(it.dataset.reqId)===String(reqId)));

  // تفاصيل
  const res = await fetch(`/students/service-requests/${reqId}`, {credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}});
  const j = await res.json();
  DBG('openRequest(): details response', j);
  if (!j?.success) { toast('تعذر تحميل تفاصيل الطلب'); return; }

  const r = j.data.request;
  $('#chatRequestTitle', els.root).textContent = r.subject;
  const badge = $('#chatRequestStatus', els.root);
  badge.textContent = r.status;
  badge.dataset.status = r.status;
  $('#chatRequestDate', els.root).textContent = r.created_at;
  $('#requestDescription', els.root).textContent = r.description || '';
  $('#requestCourse', els.root).textContent = r.course_name ? `${r.course_name} — ${r.semester_name || ''}` : 'غير محدد';

  // المرفقات الأولية
  renderFilesCards($('#requestAttachments', els.root), j.data.attachments || []);

  // الرسائل الأخيرة
  els.messagesList.innerHTML = '';
  await loadMessages(); // آخر 10
  scrollToBottom();
}

async function loadMessages() {
  if (!state.currentReqId || state.messagesLoading) return;
  state.messagesLoading = true;

  const qs = new URLSearchParams({
    limit: String(state.msgLimit),
    ...(state.beforeMsgId ? { before_id: String(state.beforeMsgId) } : {})
  });

  DBG('loadMessages(): fetch', {reqId: state.currentReqId, before: state.beforeMsgId, limit: state.msgLimit});

  const res = await fetch(`/students/service-requests/${state.currentReqId}/messages?`+qs.toString(),
    {credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}});
  const j = await res.json();
  DBG('loadMessages(): response', j);
  if (!j?.success) { state.messagesLoading=false; return; }

  const prevHeight = els.messagesList.scrollHeight;
  const frag = document.createDocumentFragment();
  (j.data.items||[]).forEach(m=> frag.appendChild(renderMessage(m)));

  if (state.beforeMsgId) {
    els.messagesList.prepend(frag);
    const newHeight = els.messagesList.scrollHeight;
    els.messagesWrapper.scrollTop = newHeight - prevHeight;
  } else {
    els.messagesList.appendChild(frag);
  }

  state.beforeMsgId = j.data.next_before_id;
  state.messagesLoading = false;
  DBG('loadMessages(): updated', {next_before_id: state.beforeMsgId});
}

function bindMessagesScroll() {
  on(els.messagesWrapper, 'scroll', async ()=>{
    if (els.messagesWrapper.scrollTop <= 10 && state.beforeMsgId && !state.messagesLoading) {
      DBG('scroll top reached → load older');
      await loadMessages();
    }
  });
}

// ====== المرسل (نص/ملفات/صوت) ======
function bindComposer() {
  DBG('bindComposer(): attach handlers');
  on(els.btnAttachFile, 'click', ()=> { DBG('attach click'); els.chatFileInput?.click(); });
  on(els.chatFileInput, 'change', (e)=>{ DBG('file input change', e.target?.files?.length||0); addPendingFiles(e.target.files); e.target.value=''; });
  on(els.btnSendMessage, 'click', ()=> { DBG('send click'); sendMessage(); });

  // Enter لإرسال الرسالة (Shift+Enter = سطر جديد)
  on(els.messageInput, 'keydown', (ev)=>{
    if (ev.key === 'Enter' && !ev.shiftKey) {
      ev.preventDefault();
      DBG('Enter pressed → send');
      sendMessage();
    }
  });

  // الصوت
  on(els.voiceBtn, 'click', ()=> { DBG('voiceBtn click'); startRecording(); });
  on(els.btnStopRecording, 'click', ()=> { DBG('stopRecording click'); stopRecordingAndQueue(); });
  on(els.btnCancelRecording, 'click', ()=> { DBG('cancelRecording click'); cancelRecording(); });
}

function addPendingFiles(fileList) {
  let added = 0;
  for (const f of fileList) {
    if (!ALLOWED_MIME.has(f.type)) { toast('نوع ملف غير مسموح: '+f.name); continue; }
    if (f.size > MAX_FILE_BYTES)   { toast('ملف أكبر من 10MB تم تجاهله: '+f.name); continue; }
    state.pendingFiles.push(f);
    added++;
  }
  DBG('addPendingFiles(): added', added, 'total pending', state.pendingFiles.length);
  renderPendingPreviews();
  if (added === 0) toast('تمت إضافة 0 ملف للمعاينة');
}

function renderPendingPreviews() {
  els.pendingAttachments.innerHTML = '';
  state.pendingFiles.forEach((f, idx)=>{
    const isImg = f.type.startsWith('image/');
    const isVid = f.type.startsWith('video/');
    const isAud = f.type.startsWith('audio/');

    if (!isAud) {
      const card = document.createElement('div');
      card.className = 'file-card';
      card.innerHTML = `
        <button class="remove-btn" title="إزالة"><i class="fas fa-times"></i></button>
        <div class="thumb ${isImg?'img-thumb':'icon-thumb'}">
          ${isImg ? `<img src="${URL.createObjectURL(f)}" alt="${f.name}">`
                   : `<i class="fas ${isVid?'fa-video':'fa-file-alt'}"></i>`}
        </div>
        <div class="meta">
          <div class="name">${f.name}</div>
          <div class="sub">${humanMB(f.size)}</div>
        </div>
      `;
      const rm = card.querySelector('.remove-btn');
      on(rm, 'click', ()=>{
        DBG('remove pending file idx', idx, f.name);
        state.pendingFiles.splice(idx,1);
        renderPendingPreviews();
      });
      els.pendingAttachments.appendChild(card);
    } else {
      const box = document.createElement('div');
      box.className = 'voice-message';
      box.style.marginBottom = '8px';
      box.innerHTML = `
        <div class="meta" style="margin-bottom:6px;">
          <div class="name">${f.name}</div>
          <div class="sub">${humanMB(f.size)}</div>
        </div>
        <audio controls preload="metadata" src="${URL.createObjectURL(f)}"></audio>
        <div style="margin-top:6px;">
          <button class="remove-btn" title="إزالة" style="width:28px;height:28px;border-radius:50%;background:#ef4444;border:none;color:#fff;cursor:pointer;">
            <i class="fas fa-times"></i>
          </button>
        </div>
      `;
      const rm = box.querySelector('.remove-btn');
      on(rm, 'click', ()=>{
        DBG('remove pending voice idx', idx, f.name);
        state.pendingFiles.splice(idx,1);
        renderPendingPreviews();
      });
      els.pendingAttachments.appendChild(box);
    }
  });
}

async function sendMessage() {
  if (!state.currentReqId) return toast('اختر طلبًا أولاً');
  const body = els.messageInput?.value?.trim() || '';

  if (!body && state.pendingFiles.length === 0) {
    toast('اكتب رسالة أو أرفق ملفًا'); return;
  }

  const fd = new FormData();
  if (body) fd.append('body', body);
  state.pendingFiles.forEach(f => fd.append('files[]', f, f.name));
  addCsrf(fd);

  DBG('sendMessage(): POST', {reqId: state.currentReqId, bodyLen: body.length, files: state.pendingFiles.map(f=>({name:f.name,type:f.type,size:f.size}))});

  const res = await fetch(`/students/service-requests/${state.currentReqId}/messages`, {
    method: 'POST',
    body: fd,
    credentials:'same-origin',
    headers: {'X-Requested-With':'XMLHttpRequest'}
  });

  let j = null;
  try { j = await res.json(); } catch (_) {}
  DBG('sendMessage(): response', {status: res.status, json: j});

  if (!j?.success) { toast((j && j.message) || `فشل الإرسال (HTTP ${res.status})`); return; }

  // امسح الإدخال والمعاينات
  els.messageInput.value = '';
  state.pendingFiles = [];
  renderPendingPreviews();

  // إعادة تحميل آخر رسائل
  state.beforeMsgId = null;
  els.messagesList.innerHTML = '';
  await loadMessages();
  scrollToBottom();
}

// ====== تسجيل الصوت ======
async function startRecording() {
  if (!state.canVoice) return toast('هذه الميزة تتطلب خطة أعلى.');
  try {
    DBG('startRecording(): requesting mic');
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    state.mediaRecorder = new MediaRecorder(stream);
    state.recordingChunks = [];
    state.mediaRecorder.ondataavailable = (e)=> { if (e.data.size) state.recordingChunks.push(e.data); };
    state.mediaRecorder.onstop = ()=> { DBG('mediaRecorder stopped; chunks', state.recordingChunks.length); };
    state.mediaRecorder.start();
    state.recordingStart = Date.now();
    els.voiceRecording.style.display = 'flex';
    animateTimer();
    DBG('startRecording(): started');
  } catch (e) {
    console.error(e);
    toast('تعذر بدء التسجيل الصوتي');
  }
}
function animateTimer() {
  const tick = ()=>{
    if (!state.mediaRecorder || els.voiceRecording.style.display === 'none') return;
    const s = Math.floor((Date.now()-state.recordingStart)/1000);
    const m = String(Math.floor(s/60)).padStart(2,'0');
    const ss= String(s%60).padStart(2,'0');
    els.recordingTime.textContent = `${m}:${ss}`;
    requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
}
function stopRecordingAndQueue() {
  if (!state.mediaRecorder) return;
  DBG('stopRecordingAndQueue(): stopping...');
  state.mediaRecorder.onstop = ()=>{
    const blob = new Blob(state.recordingChunks, { type: 'audio/webm' });
    const file = new File([blob], `voice_${Date.now()}.webm`, { type: 'audio/webm' });
    state.pendingFiles.push(file);
    DBG('stopRecordingAndQueue(): queued voice file', {name:file.name, size:file.size, type:file.type});
    renderPendingPreviews();
    els.voiceRecording.style.display = 'none';
    try { state.mediaRecorder.stream.getTracks().forEach(t=>t.stop()); } catch {}
    state.mediaRecorder = null;
  };
  state.mediaRecorder.stop();
}
function cancelRecording() {
  DBG('cancelRecording()');
  if (state.mediaRecorder) {
    try { state.mediaRecorder.stream.getTracks().forEach(t=>t.stop()); } catch {}
    state.mediaRecorder = null;
  }
  els.voiceRecording.style.display = 'none';
}

// ====== أزرار متفرقة ======
function bindToggles() {
  DBG('bindToggles(): attach');
  on(els.btnToggleRequestDetails, 'click', ()=>{
    const el = els.requestDetails;
    el.style.display = (el.style.display==='none' || !el.style.display) ? 'block' : 'none';
    DBG('toggle details:', el.style.display);
  });

  on(els.cancelRequestBtn, 'click', async ()=>{
    if (!state.currentReqId) return;
    const result = await Swal.fire({
      title: 'تأكيد الإلغاء',
      text: 'هل تريد إلغاء الطلب؟',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم، إلغاء',
      cancelButtonText: 'تراجع',
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6'
    });
    if (!result.isConfirmed) return;
    const fd = addCsrf(new FormData());
    DBG('cancelRequest(): POST', state.currentReqId);
    const res = await fetch(`/students/service-requests/${state.currentReqId}/cancel`, {
      method:'POST', body: fd, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}
    });
    const j = await res.json();
    DBG('cancelRequest(): response', j);
    if (!j?.success) { toast(j?.message || 'فشل الإلغاء'); return; }
    toast('تم إلغاء الطلب');
    // إعادة التحميل
    state.reqOffset = 0; state.hasMoreReq = true;
    await loadRequests(true);
    await openRequest(state.currentReqId);
  });

  // زر فتح مودال "طلب جديد" — لو الاسكربت غير محمل، نحمله ديناميكيًا ثم نفتحه
  on(els.btnNewRequest, 'click', async ()=>{
    DBG('new request button clicked → ensure modal script');
    await ensureServiceRequestModalLoaded();
    DBG('modal script ready, opening modal');
    window.ServiceRequestModal?.open?.();
  });
}

// تحميل سكربت مودال الطلب إن لم يكن موجودًا
let __modalScriptLoading = null;
async function ensureServiceRequestModalLoaded() {
  if (window.ServiceRequestModal && typeof window.ServiceRequestModal.open === 'function') {
    DBG('modal already loaded');
    return;
  }
  if (__modalScriptLoading) { DBG('modal loading promise awaited'); await __modalScriptLoading; return; }
  __modalScriptLoading = new Promise((resolve)=>{
    const s = document.createElement('script');
    s.src = '/assets/js/students/service_request_modal.js?v=log1';
    s.async = true;
    s.onload = ()=> { DBG('modal script loaded'); resolve(); };
    s.onerror = ()=> { console.error('[service] failed to load service_request_modal.js'); resolve(); };
    document.head.appendChild(s);
  });
  await __modalScriptLoading;
}

// ======================================================================
//                        MOUNT / UNMOUNT (ديناميكي)
// ======================================================================
function mount(rootEl=document) {
  DBG('mount(): start', {rootEl});
  // 1) اجمع العناصر
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

    // New Request
    btnNewRequest: rootEl.querySelector('#svcNewRequestBtn'),
  };
  DBG('mount(): elements collected', els);

  // 2) حالة افتراضية
  state = {
    reqOffset: 0,
    reqLimit: 5,
    reqStatus: '',
    reqQuery: '',
    loadingList: false,
    hasMoreReq: true,

    currentReqId: null,
    msgLimit: 10,
    beforeMsgId: null,
    messagesLoading: false,

    pendingFiles: [],
    mediaRecorder: null,
    recordingChunks: [],
    recordingStart: 0,
    canVoice: false,
    planLevel: 0,
    priority: 'low',
  };
  DBG('mount(): state initialized', state);

  // 3) ربط الأحداث
  bindFilters();
  bindComposer();
  bindMessagesScroll();
  bindToggles();

  // 4) تحميل البيانات الأولية
  loadTopMeta();
  loadRequests(true);
}

function unmount() {
  DBG('unmount(): start');
  // إزالة كل الـ listeners التي سُجّلت عبر on()
  try { listeners.forEach(off => off()); } catch {}
  listeners = [];

  // إيقاف أي تسجيل صوتي نشِط
  try {
    if (state?.mediaRecorder) {
      state.mediaRecorder.stream.getTracks().forEach(t=>t.stop());
    }
  } catch {}

  // تفريغ الحالتين
  els = {};
  state = {};
  DBG('unmount(): done');
}

// ======================================================================
//                       واجهة استخدام للوارد (router)
// ======================================================================
const ServicePage = { mount, unmount };
export default ServicePage;
// window.ServicePage = ServicePage;
