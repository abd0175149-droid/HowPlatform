// public/studentassets/js/sections/service.js
// نظام إدارة طلبات الخدمة المتخصصة - نسخة مُحسّنة ومُصححة (نهائية)
// ملاحظة: يعتمد هذا الملف على دوال التنبيه القادمة من alerts.js (showSuccess, showError, showInfo, showConfirm, showWarning)

// ======================= الحالة العامة =======================
let currentRequestId = null;
let currentUserId = null;
let mediaRecorder = null;
let audioChunks = [];
let recordingTimer = null;
let recordingStartTime = null;
let messagesCheckInterval = null;
let lastMessageId = 0;
let unreadCounts = {};
const renderedMessageIds = new Set();
let pendingFiles = [];
let isUploading = false;

// حراسة إعادة الربط / المؤقتات
let handlersAttached = false;
let onDocClick = null;
let onDocChange = null;

// ✅ التحميل الجزئي (History)
let oldestMessageId = null;      // أقدم ID لدينا حالياً
let isLoadingHistory = false;    // لمنع الطلبات المكررة
let hasMoreHistory = true;       // يتوقف عند نفاد الرسائل
let historyScrollAttached = false; // منع تكرار ربط السكrol

// حارس استدعاءات متتابعة
let loadRequestInflightFor = null;

// AbortController موحّد لكل طلبات الصفحة
let svcAbortController = null;
function getAbortSignal() {
  if (!svcAbortController) svcAbortController = new AbortController();
  return svcAbortController.signal;
}

// mark-read (تجميع ومنع التكرار)
const markReadInflight = new Map(); // requestId -> Promise
let markReadDebounceTimer = null;
function scheduleMarkRead() {
  if (markReadDebounceTimer) return;
  markReadDebounceTimer = setTimeout(() => {
    markReadDebounceTimer = null;
    if (currentRequestId) markMessagesAsRead(currentRequestId);
  }, 300);
}

// ====== Lazy Media: إيقاف مؤقت للبولينغ وقت تحميل الميديا ======
let mediaLoadingInFlight = 0;
function bumpMedia(delta){ mediaLoadingInFlight = Math.max(0, mediaLoadingInFlight + delta); }
let ioMedia = null;
function mountMediaLazy() {
  if (ioMedia) { try{ ioMedia.disconnect(); }catch(_){} ioMedia = null; }

  const root = document.getElementById('messagesArea') || null;
  ioMedia = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const ds = el.getAttribute('data-src');
      if (!ds) { ioMedia.unobserve(el); return; }

      bumpMedia(+1);

      if (el.tagName === 'IMG') {
        const onDone = ()=>{ el.removeEventListener('load', onDone); el.removeEventListener('error', onDone); bumpMedia(-1); };
        el.addEventListener('load', onDone);
        el.addEventListener('error', onDone);
        el.src = ds;
        el.removeAttribute('data-src');
      } else if (el.tagName === 'VIDEO') {
        const onVidDone = ()=>{ el.removeEventListener('loadeddata', onVidDone); el.removeEventListener('error', onVidDone); bumpMedia(-1); };
        el.addEventListener('loadeddata', onVidDone);
        el.addEventListener('error', onVidDone);
        el.src = ds;
        el.removeAttribute('data-src');
      }
      ioMedia.unobserve(el);
    });
  }, { root, threshold: 0.1 });

  document.querySelectorAll('#messagesArea img[data-src], #messagesArea video[data-src]').forEach(el=> ioMedia.observe(el));
}

// ======================= نقطة الدخول =======================
export async function init() {
  try {
    console.log('Initializing service requests page...');

    await waitForElement('#requestsList');
    await waitForElement('#chatPlaceholder');

    // Session check (non-blocking)
    const sessionValid = await checkUserSession();
    if (!sessionValid) {
      console.warn('Session check failed, continue cautiously...');
    }

    await getUserInfo();

    await loadRequests();
    setupEventListeners();
    startMessagesCheck();

    // فرض خصائص التمرير
    enforceScrollableStyles();

    // استرجاع آخر طلب تم فتحه
    const lastRequestIdLS = localStorage.getItem("lastServiceRequestId");
    if (lastRequestIdLS) loadRequest(lastRequestIdLS);

    // التعامل مع تبدّل رؤية الصفحة (إيقاف/استئناف البولنغ)
    document.addEventListener('visibilitychange', handleVisibility);
    window.addEventListener('online', () => { showInfo('تم استعادة الاتصال'); startMessagesCheck(true); });
    window.addEventListener('offline', () => { showError('لا يوجد اتصال بالإنترنت'); stopMessagesCheck(); });

    // تنظيف تلقائي عند الخروج
    window.addEventListener('beforeunload', cleanup, { once: true });

    console.log('Service requests page initialized successfully');
  } catch (error) {
    console.error('Init error:', error);
    showError("حدث خطأ في تحميل الصفحة. يرجى تحديث الصفحة أو المحاولة مرة أخرى.");

    setTimeout(() => {
      showConfirm({
        title: 'إعادة تحميل الصفحة؟',
        text: 'حدث خطأ أثناء التهيئة. هل تريد إعادة تحميل الصفحة الآن؟',
        confirmText: 'إعادة التحميل',
        cancelText: 'إلغاء',
        onConfirm: () => window.location.reload()
      });
    }, 3000);
  }
}

function handleVisibility(){
  if (document.hidden) {
    stopMessagesCheck();
  } else {
    startMessagesCheck(true);
  }
}

// ======================= Helpers عامة =======================
// ✅ فرض خصائص التمرير على الحاويات لضمان ظهور شريط السكrol دائمًا
function enforceScrollableStyles() {
  const chat = document.getElementById('chatContainer');
  const msgs = document.getElementById('messagesArea');
  if (chat) {
    chat.style.display = 'flex';
    chat.style.flexDirection = 'column';
    chat.style.minHeight = '0';
    if (!chat.style.height) chat.style.height = '100%'; // عدّلها حسب تخطيطك العام
  }
  if (msgs) {
    msgs.style.flex = '1 1 auto';
    msgs.style.minHeight = '0';
    msgs.style.overflowY = 'scroll';   // يُظهر الشريط دائمًا
    msgs.style.overflowX = 'hidden';
    msgs.style.webkitOverflowScrolling = 'touch';
  }
}

// ✅ backfill: تحميل أقدم حتى يصبح الحيز قابل للتمرير
async function backfillUntilScrollable() {
  const area = document.getElementById('messagesArea');
  if (!area) return;
  let safety = 0;

  // حمّل رسائل أقدم لحد ما يصير في overflow أو يخلص التاريخ أو نوصل حد أمان
  while (hasMoreHistory && (area.scrollHeight <= area.clientHeight + 1) && safety < 10) {
    await loadOlderMessages();
    safety++;
  }

  // لو ما صار overflow نهائيًا (محادثة قصيرة جدًا)، أضف سبايسر 1px يسمح بحركة طفيفة للأعلى
  if (area.scrollHeight <= area.clientHeight + 1) {
    let spacer = document.getElementById('preTopSpacer');
    if (!spacer) {
      spacer = document.createElement('div');
      spacer.id = 'preTopSpacer';
      spacer.style.height = '1px';
      area.prepend(spacer);
    }
  }
}

function getExt(name){ if(!name) return ''; const n=String(name).toLowerCase(); return n.includes('.')? n.split('.').pop():''; }
function isImageByExt(ext){ return ['jpg','jpeg','png','gif','webp'].includes((ext||'').toLowerCase()); }
function isVideoByExt(ext){ return ['mp4','webm','mov','avi','mkv','mpeg'].includes((ext||'').toLowerCase()); }
function getIconByExt(ext){
  const e=(ext||'').toLowerCase();
  if(['pdf'].includes(e)) return 'fa-file-pdf';
  if(['doc','docx'].includes(e)) return 'fa-file-word';
  if(['xls','xlsx'].includes(e)) return 'fa-file-excel';
  if(['ppt','pptx'].includes(e)) return 'fa-file-powerpoint';
  if(['zip','rar'].includes(e)) return 'fa-file-archive';
  if(['jpg','jpeg','png','gif','webp'].includes(e)) return 'fa-file-image';
  if(['mp4','webm','mov','avi','mkv','mpeg'].includes(e)) return 'fa-file-video';
  return 'fa-file';
}

function buildFileCard({ url, name, sizeText='', ext='', withLink=true, floatingDownload=true, showRemove=false, removeId=null }){
  const isImg=isImageByExt(ext), isVid=isVideoByExt(ext);
  const metaSub=[sizeText, ext?('.'+ext):''].filter(Boolean).join(' • ');
  let thumb='';
  if (isImg) {
    thumb = `<div class="thumb img-thumb"><img data-src="${escapeHtml(url)}" alt="${escapeHtml(name)}" loading="lazy"></div>`;
  } else if (isVid) {
    thumb = `<div class="thumb video-thumb">
               <video data-src="${escapeHtml(url)}" preload="none" controls playsinline muted></video>
             </div>`;
  } else {
    thumb = `<div class="thumb icon-thumb"><i class="fas ${getIconByExt(ext)}"></i></div>`;
  }

  const removeBtn = showRemove ? `<button class="remove-btn" data-remove-id="${removeId}" title="حذف الملف"><i class="fas fa-times"></i></button>` : '';
  const downloadBtn = (withLink && floatingDownload)
    ? `<a class="fab-download" href="${escapeHtml(url)}" target="_blank" rel="noopener" title="تنزيل"><i class="fas fa-download"></i></a>` : '';
  const inner = `${thumb}
    <div class="meta">
      <div class="name" title="${escapeHtml(name)}">${escapeHtml(name)}</div>
      <div class="sub">${escapeHtml(metaSub)}</div>
    </div>
    ${removeBtn}
    ${downloadBtn}`;
  return withLink
    ? `<a class="file-card has-actions" href="${escapeHtml(url)}" target="_blank" rel="noopener">${inner}</a>`
    : `<div class="file-card ${showRemove?'has-remove':''}">${inner}</div>`;
}

// ✅ لودر علوي للـ history
function showTopLoader() {
  const messagesArea = document.getElementById('messagesArea');
  if (!messagesArea) return;

  let loader = document.getElementById('historyLoader');
  if (!loader) {
    loader = document.createElement('div');
    loader.id = 'historyLoader';
    loader.style.cssText = 'text-align:center;padding:8px;font-size:12px;color:#666;background:#f8f8f8;';
    loader.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري تحميل الرسائل الأقدم...';
    messagesArea.prepend(loader);
  }
  loader.style.display = 'block';
}
function hideTopLoader() {
  const loader = document.getElementById('historyLoader');
  if (loader) loader.style.display = 'none';
}

// ✅ تحديث أقدم ID
function updateOldestMessageId(messages) {
  if (!Array.isArray(messages) || messages.length === 0) return;
  const minId = Math.min(...messages.map((m) => Number(m.id) || 0).filter(Boolean));
  if (minId && (!oldestMessageId || minId < oldestMessageId)) {
    oldestMessageId = minId;
  }
}

// ✅ Scroll handler للـ history (يربط مرة واحدة)
function attachHistoryScrollHandler() {
  if (historyScrollAttached) return;
  const area = document.getElementById('messagesArea');
  if (!area) return;

  const THRESHOLD = 10;
  area.addEventListener('scroll', () => {
    if ((area.scrollTop <= THRESHOLD) && hasMoreHistory && !isLoadingHistory) {
      loadOlderMessages();
    }
  }, { passive: true });

  historyScrollAttached = true;
}

// ✅ تحميل الرسائل الأقدم (5 في كل مرة)
async function loadOlderMessages() {
  if (!currentRequestId || !oldestMessageId || isLoadingHistory || !hasMoreHistory) return;

  isLoadingHistory = true;
  showTopLoader();

  try {
    const url = `/student/service-requests/${currentRequestId}/messages?limit=5&before_id=${encodeURIComponent(oldestMessageId)}&order=desc`;
    const res = await fetch(url, {
      headers: { 
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      credentials: 'same-origin',
      signal: getAbortSignal(),
    });

    if (!res.ok) {
      if (res.status === 403) { showError('غير مصرح لك بالوصول لهذا الطلب'); return; }
      if (res.status === 401) { showError('انتهت الجلسة'); setTimeout(()=>location.href='/auth/login',2000); return; }
      throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    }

    const payload = await res.json();
    if (!payload?.success) throw new Error(payload?.message || 'فشل في تحميل الرسائل');

    // الحصول على المصفوفة من الحقل الصحيح
    const list = Array.isArray(payload.messages) ? payload.messages
             : Array.isArray(payload.data)     ? payload.data
             : [];

    // ✅ فرز تصاعدي دائمًا: الوقت ثم الـID
    const olderAsc = [...list].sort((a, b) => {
      const ta = Date.parse(normalizeDateInput(a?.created_at || "")) || 0;
      const tb = Date.parse(normalizeDateInput(b?.created_at || "")) || 0;
      if (ta !== tb) return ta - tb;
      const ia = Number(a?.id) || 0;
      const ib = Number(b?.id) || 0;
      return ia - ib;
    });

    if (olderAsc.length > 0) {
      const messagesArea = document.getElementById('messagesArea');
      const prevScrollTop = messagesArea ? messagesArea.scrollTop : 0;
      const prevScrollHeight = messagesArea ? messagesArea.scrollHeight : 0;

      // نضيف للأعلى مع الحفاظ على موضع التمرير
      displayMessages(olderAsc, false, 'prepend');
      updateOldestMessageId(olderAsc);

      // لو أقل من 5 → لا مزيد
      if (olderAsc.length < 5) hasMoreHistory = false;

      // الحفاظ على نفس الموضع بعد الإضافة
      if (messagesArea) {
        const newScrollHeight = messagesArea.scrollHeight;
        messagesArea.scrollTop = prevScrollTop + (newScrollHeight - prevScrollHeight);
      }
    } else {
      hasMoreHistory = false;
    }
  } catch (e) {
    if (e.name !== 'AbortError') showError('حدث خطأ في تحميل الرسائل الأقدم');
  } finally {
    isLoadingHistory = false;
    hideTopLoader();
  }
}

async function waitForElement(selector, timeout=60000){
  const start=performance.now();
  while(performance.now()-start < timeout){
    const el=document.querySelector(selector);
    if(el) return el;
    await new Promise(r=>setTimeout(r,100));
  }
  throw new Error(`Timeout waiting for ${selector}`);
}

// ======================= معلومات المستخدم =======================
async function checkUserSession() {
  try {
    const response = await fetch("/student/service-requests/list", {
      headers: { "X-Requested-With":"XMLHttpRequest", "Accept":"application/json" },
      credentials: 'same-origin',
      signal: getAbortSignal(),
    });
    if (response.status === 401) throw new Error('User not authenticated');
    if (response.status === 403) throw new Error('User access forbidden');
    if (!response.ok) throw new Error(`Session check failed: ${response.status}`);
    const data = await response.json();
    if (!data?.success) throw new Error('Session success=false');
    return true;
  } catch (error) {
    console.error('Session check error:', error);
    showError('مشكلة في التحقق من الجلسة. تحقق من تسجيل الدخول.');
    return false;
  }
}

async function getUserInfo() {
  if (window.currentUserId) { currentUserId = window.currentUserId; return; }
  const meta = document.querySelector('meta[name="current-user-id"]');
  if (meta?.content) { currentUserId = meta.content; return; }
  try {
    const res = await fetch("/student/profile/data", {
      headers: { "X-Requested-With":"XMLHttpRequest", "Accept":"application/json" },
      credentials: 'same-origin',
      signal: getAbortSignal(),
    });
    if (res.ok) {
      const data = await res.json();
      if (data?.success && data.user?.id) { currentUserId = data.user.id; return; }
    }
  } catch (e) {
    console.warn('Could not fetch user info:', e);
  }
  console.warn('Could not determine user ID, proceeding anyway');
}

// ======================= المستمعات =======================
function setupEventListeners() {
  if (handlersAttached) return;
  handlersAttached = true;

  document.querySelectorAll(".tab-btn").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      document.querySelectorAll(".tab-btn").forEach((b)=>b.classList.remove("active"));
      e.currentTarget.classList.add("active");
      filterRequests(e.currentTarget.dataset.filter);
    });
  });

  const requestsList = document.getElementById("requestsList");
  if (requestsList) {
    requestsList.addEventListener("click", (e) => {
      const item = e.target.closest(".request-item");
      if (item?.dataset?.id) loadRequest(item.dataset.id);
    });
  }

  onDocClick = async (e) => {
    const isSendButton = e.target.closest("#sendBtn")
      || e.target.closest(".send-btn")
      || e.target.closest("[data-action='send']")
      || e.target.id === "sendBtn"
      || e.target.classList?.contains("send-btn");
    if (isSendButton) {
      e.preventDefault(); e.stopPropagation();
      await handleSendAction();
      return;
    }

    const delBtn = e.target.closest(".message-delete-btn");
    if (delBtn) {
      e.preventDefault(); e.stopPropagation();
      const msgEl = e.target.closest(".message-service");
      if (msgEl?.dataset?.messageId) deleteMessage(msgEl.dataset.messageId, msgEl);
      return;
    }
  };

  onDocChange = (e) => {
    if (e.target && e.target.id === "chatFileInput") handleFileSelect(e);
  };

  document.addEventListener("click", onDocClick);
  document.addEventListener("change", onDocChange);

  const messageInput = document.getElementById("messageInput");
  if (messageInput) {
    messageInput.addEventListener("input", function(){
      this.style.height = "auto";
      this.style.height = this.scrollHeight + "px";
    });
    messageInput.addEventListener("keydown", function(e){
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        if (pendingFiles.length === 0) handleSendAction();
      }
    });
  }
}

// ======================= الطلبات / القوائم =======================
async function loadRequests(){
  try{
    const response = await fetch("/student/service-requests/list", {
      headers:{ "X-Requested-With":"XMLHttpRequest", "Accept":"application/json" },
      credentials: 'same-origin',
      signal: getAbortSignal(),
    });
    if (response.status === 401) {
      showError('انتهت الجلسة. سيتم نقلك لتسجيل الدخول...');
      setTimeout(() => { window.location.href = '/auth/login'; }, 3000);
      return;
    }
    if (response.status === 403) { showError('غير مصرح لك بالوصول.'); return; }
    if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    const data = await response.json();

    if (data?.success){
      unreadCounts = {};
      if (Array.isArray(data.requests)) {
        data.requests.forEach((req)=> unreadCounts[req.id]=req.unread_count||0 );
      }
      displayRequests(data.requests||[]);
      updateCounts(data.counts||{});
    } else {
      showError(data?.message || "فشل في تحميل الطلبات");
    }
  } catch (e){
    if (e.name !== 'AbortError') showError("حدث خطأ أثناء تحميل الطلبات: " + e.message);
  }
}

function displayRequests(requests){
  const requestsList=document.getElementById("requestsList");
  if (!requestsList) return;

  if(!Array.isArray(requests) || requests.length===0){
    requestsList.innerHTML = `<div class="empty-state"><i class="fas fa-inbox"></i><p>لا توجد طلبات</p></div>`;
    return;
  }

  requestsList.innerHTML = requests.map((request)=>{
    const hasUnread = (unreadCounts[request.id]||0)>0;
    return `<div class="request-item ${request.id==currentRequestId?"active":""}"
                data-id="${request.id}"
                data-status="${escapeHtml(request.status||"")}"
                onclick="loadRequest(${request.id})">
      <div class="request-title">
        <span class="status-indicator ${escapeHtml(request.status||"")}"></span>
        ${escapeHtml(request.subject||"")}
      </div>
      <div class="request-meta">
        <span class="course-name">${request.course_name ? `<i class="fas fa-book"></i> ${escapeHtml(request.course_name)}` : ""}</span>
        <span class="request-date">${formatDate(request.created_at)}</span>
      </div>
      ${hasUnread ? `<span class="unread-badge" id="unread-${request.id}">${unreadCounts[request.id]}</span>` : ""}
    </div>`;
  }).join("");
}

function updateCounts(counts){
  const set=(id,val)=>{ const el=document.getElementById(id); if(el) el.textContent=(val??0); };
  set("countAll", counts.all);
  set("countPending", counts.pending);
  set("countProgress", counts.in_progress);
  set("countResolved", counts.resolved);
  set("countCancelled", counts.cancelled);
}

function filterRequests(filter){
  document.querySelectorAll(".request-item").forEach((item)=>{
    item.style.display = (filter==="all" || item.dataset.status===filter) ? "block":"none";
  });
}

// ======================= تفاصيل الطلب / المحادثة =======================
// ✅ تحميل أولي: 5 رسائل فقط (أحدث 5) ثم عرض تصاعدي، ثم تثبيت للأسفل
async function loadInitialMessages(requestId){
  const res = await fetch(`/student/service-requests/${requestId}/messages?limit=5&order=desc`, {
    headers:{ "X-Requested-With":"XMLHttpRequest", "Accept":"application/json" },
    credentials: 'same-origin',
    signal: getAbortSignal(),
  });

  if (!res.ok) {
    if (res.status === 403) { showError('غير مصرح لك بالوصول لهذا الطلب.'); return; }
    if (res.status === 401) { showError('انتهت الجلسة.'); setTimeout(()=> location.href='/auth/login',2000); return; }
    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
  }

  const data = await res.json();
  if (!data?.success) throw new Error(data?.message || 'فشل في تحميل الرسائل');

  // التقاط المصفوفة من الحقل المناسب
  const list = Array.isArray(data.messages) ? data.messages
            : Array.isArray(data.data)     ? data.data
            : [];

  // ✅ فرز تصاعدي دائمًا: أولاً بالوقت ثم بالـ ID
  const initialAsc = [...list].sort((a, b) => {
    const ta = Date.parse(normalizeDateInput(a?.created_at || "")) || 0;
    const tb = Date.parse(normalizeDateInput(b?.created_at || "")) || 0;
    if (ta !== tb) return ta - tb;
    const ia = Number(a?.id) || 0;
    const ib = Number(b?.id) || 0;
    return ia - ib;
  });

  // إعادة ضبط المعرّفات المعروضة وعرض الرسائل
  renderedMessageIds.clear();
  displayMessages(initialAsc, true, 'replace');

  // تحديث مؤشرات التتبّع
  if (initialAsc.length > 0) {
    lastMessageId   = Math.max(...initialAsc.map(m => Number(m.id) || 0));
    oldestMessageId = Number(initialAsc[0].id) || null; // لأنه الأقدم بعد الفرز التصاعدي
  } else {
    lastMessageId   = 0;
    oldestMessageId = null;
  }

  // تحديد ما إذا كان هناك المزيد من التاريخ
  hasMoreHistory = (typeof data.has_more !== 'undefined') ? !!data.has_more : initialAsc.length === 5;

  // التمرير لأسفل بعد التحميل الأولي
  const messagesArea = document.getElementById('messagesArea');
  if (messagesArea) messagesArea.scrollTop = messagesArea.scrollHeight;
}

async function loadRequest(requestId){
  if (!requestId) return;
  // حارس: منع استدعاء مكرر لنفس الطلب أثناء التحميل
  if (loadRequestInflightFor && String(loadRequestInflightFor) === String(requestId)) return;
  loadRequestInflightFor = String(requestId);

  clearPendingFiles();

  // إعادة تعيين متغيرات History
  oldestMessageId = null;
  hasMoreHistory = true;
  isLoadingHistory = false;
  renderedMessageIds.clear();

  currentRequestId = requestId;
  localStorage.setItem("lastServiceRequestId", requestId);

  document.querySelectorAll(".request-item").forEach((item)=>{
    item.classList.toggle("active", item.dataset.id==requestId);
  });

  // إزالة بادج الطلب الحالي وتصفير عدّاده محلياً
  const badge = document.getElementById(`unread-${requestId}`);
  if (badge) { badge.remove(); }
  unreadCounts[requestId] = 0;

  // ✅ تحديث بادج السايدبار مباشرةً بعد فتح الطلب
  (function updateSidebarBadge(){
    const sidebarBadge = document.getElementById("serviceRequestsBadge");
    if (!sidebarBadge) return;
    const totalUnreadNow = Object.values(unreadCounts).reduce((sum, c) => sum + (Number(c) || 0), 0);
    if (totalUnreadNow > 0){
      sidebarBadge.textContent = totalUnreadNow;
      sidebarBadge.style.display = "inline-block";
    } else {
      sidebarBadge.style.display = "none";
    }
  })();

  // ✅ علِّم كمقروء فورًا وتخلّص من أي debounce حتى لا تعود البادج
  if (markReadDebounceTimer){ clearTimeout(markReadDebounceTimer); markReadDebounceTimer = null; }
  markMessagesAsRead(requestId); // غير محجوب (non-blocking) حتى لا يؤخر رسم الصفحة

  const chatPlaceholder = document.getElementById("chatPlaceholder");
  const chatContainer   = document.getElementById("chatContainer");
  if (chatPlaceholder) chatPlaceholder.style.display = "none";
  if (chatContainer)   chatContainer.style.display   = "flex";

  // فرض خصائص التمرير حال عرض المحادثة
  enforceScrollableStyles();

  try{
    // معلومات رأس الطلب والمرفقات (خفيف)
    const detailsRes = await fetch(`/student/service-requests/${requestId}`, {
      headers:{ "X-Requested-With":"XMLHttpRequest", "Accept":"application/json" },
      credentials: 'same-origin',
      signal: getAbortSignal(),
    });
    if (detailsRes.ok){
      const details = await detailsRes.json();
      if (details?.success){
        displayRequestDetails(details.request||{}, details.attachments||[]);
      }
    }

    // ✅ الرسائل: أول دفعة = 5 فقط
    await loadInitialMessages(requestId);

    // ✅ backfill حتى يصبح الحيز قابلًا للتمرير
    await backfillUntilScrollable();

    // ربط سكrol للأعلى مرة واحدة
    attachHistoryScrollHandler();

    // يظل موجود، لكن لدينا استدعاء فوري بالأعلى
    scheduleMarkRead();
  } catch(e){
    if (e.name !== 'AbortError') showError("حدث خطأ أثناء تحميل تفاصيل الطلب: " + e.message);
  } finally {
    loadRequestInflightFor = null;
  }
}

function displayRequestDetails(request, attachments = []){
  const titleEl=document.getElementById("chatRequestTitle");
  const dateEl=document.getElementById("chatRequestDate");
  const statusBadge=document.getElementById("chatRequestStatus");
  const descEl=document.getElementById("requestDescription");
  const courseEl=document.getElementById("requestCourse");
  const cancelBtn=document.getElementById("cancelRequestBtn");
  const inputArea=document.getElementById("inputArea");
  const attachmentsEl=document.getElementById("requestAttachments");

  if (titleEl) titleEl.textContent = request?.subject || "";
  if (dateEl)  dateEl.textContent  = formatDate(request?.created_at);

  if (statusBadge){
    const st=request?.status||"";
    statusBadge.textContent=getStatusText(st);
    statusBadge.className=`status-badge ${st}`;
  }

  if (descEl)   descEl.textContent   = request?.description || "";
  if (courseEl) courseEl.textContent = request?.course_name || "غير محدد";

  if (cancelBtn && inputArea){
    if (request?.status==="pending" || request?.status==="in_progress"){
      cancelBtn.style.display="flex";
      inputArea.classList.remove("disabled");
    } else {
      cancelBtn.style.display="none";
      inputArea.classList.add("disabled");
    }
  }

  if (attachmentsEl){
    const files = Array.isArray(attachments) ? attachments : [];
    if (files.length>0){
      attachmentsEl.classList.add('uploaded-files','file-preview-container');
      attachmentsEl.innerHTML = files.map((file)=>{
        const name=file.name||`file-${file.id}`;
        const ext=getExt(name);
        const url=`/student/service-requests/file/${file.id}`;
        const sizeText=file.size? formatBytes(file.size):'';
        return buildFileCard({ url, name, sizeText, ext, withLink:true, floatingDownload:true });
      }).join('');
    } else {
      attachmentsEl.classList.remove('uploaded-files','file-preview-container');
      attachmentsEl.innerHTML = "<p>لا توجد مرفقات</p>";
    }
  }

  ensurePreviewStrip();

  requestAnimationFrame(()=> mountMediaLazy());
}

function formatBytes(bytes){
  if (!bytes) return '';
  const k=1024, sizes=['Bytes','KB','MB','GB'], i=Math.floor(Math.log(bytes)/Math.log(k));
  return (Math.round(bytes/Math.pow(k,i)*100)/100)+' '+sizes[i];
}

// ======================= عرض الرسائل =======================
function displayMessages(messages, isInitialLoad = false, mode = 'append') {
  const messagesArea = document.getElementById('messagesArea');
  if (!messagesArea) return;

  if (isInitialLoad) {
    renderedMessageIds.clear();
    mode = 'replace';
  }

  if (!Array.isArray(messages) || messages.length === 0) {
    if (isInitialLoad) {
      messagesArea.innerHTML = `<div class="empty-state"><p>لا توجد رسائل بعد</p></div>`;
      messagesArea.scrollTop = messagesArea.scrollHeight;
    }
    hideTopLoader();
    requestAnimationFrame(() => {
      mountMediaLazy?.();
      window.AudioPlayer?.mountAll?.(messagesArea || document);
    });
    return;
  }

  const fresh = messages.filter((m) => {
    const id = String(m?.id ?? '');
    if (!id) return true;
    if (renderedMessageIds.has(id)) return false;
    renderedMessageIds.add(id);
    return true;
  });

  if (fresh.length === 0 && mode !== 'replace') {
    hideTopLoader();
    return;
  }

  const frag = document.createDocumentFragment();
  fresh.forEach((msg) => {
    const wrap = document.createElement('div');
    wrap.innerHTML = createMessageHTML(msg);
    if (wrap.firstElementChild) frag.appendChild(wrap.firstElementChild);
  });

  if (mode === 'replace') {
    messagesArea.innerHTML = '';
    messagesArea.appendChild(frag);
    messagesArea.scrollTop = messagesArea.scrollHeight;
  } else if (mode === 'append') {
    messagesArea.appendChild(frag);
    messagesArea.scrollTop = messagesArea.scrollHeight;
  } else if (mode === 'prepend') {
    const prevScrollHeight = messagesArea.scrollHeight;
    const prevScrollTop = messagesArea.scrollTop;
    messagesArea.prepend(frag);
    const newScrollHeight = messagesArea.scrollHeight;
    const heightDifference = newScrollHeight - prevScrollHeight;
    messagesArea.scrollTop = prevScrollTop + heightDifference;
    hideTopLoader();
  }

  requestAnimationFrame(() => {
    mountMediaLazy?.();
    window.AudioPlayer?.mountAll?.(messagesArea || document);
  });
}

function createMessageHTML(msg){
  const isSent = isFromCurrentUser(msg);
  const senderName = msg.sender_display_name ?? (isSent ? "أنت" : (msg.sender_name || "مستخدم"));
  const roleText = (!isSent && msg.account_type_name) ? `(${msg.account_type_name})` : "";
  const isDeletedFlag = msg.is_deleted;
  const showDelBtn = isSent && !(isDeletedFlag===1 || isDeletedFlag===true || String(isDeletedFlag)==="1");
  const deletedClass = (isDeletedFlag===1 || isDeletedFlag===true || String(isDeletedFlag)==="1") ? ' message-deleted' : '';

  const deleteBtn = showDelBtn ? `<button class="message-delete-btn" title="حذف الرسالة"><i class="fas fa-trash"></i></button>` : '';

  return `<div class="message-service ${isSent?"sentmsg":"receivedmsg"}${deletedClass}" data-message-id="${escapeHtml(String(msg.id))}">
    <div class="message-content">
      <div class="message-header">
        <span class="sender-name">
          ${roleText ? `<span class="sender-role">${escapeHtml(roleText)}</span>` : ""}
          ${escapeHtml(senderName)}
        </span>
        <span class="message-time">${formatTime(msg.created_at)}</span>
      </div>
      ${renderMessageContent(msg)}
      ${deleteBtn}
    </div>
  </div>`;
}

function safeFileUrlForMessage(message) {
  // لو السيرفر يعيد download_url استخدمه
  if (message.download_url) return message.download_url;
  // لو فيه temp_url (رسائل مؤقتة قبل الحفظ)
  if (message.temp_url) return message.temp_url;
  // لو فيه file_id صالح
  if (message.file_id) return `/student/service-requests/file/${message.file_id}`;
  // لا يوجد رابط صالح
  return null;
}

function renderMessageContent(message){
  const isDeletedFlag = message.is_deleted;
  if (isDeletedFlag===1 || isDeletedFlag===true || String(isDeletedFlag)==="1"){
    return `<p class="message-text">تم حذف هذه الرسالة</p>`;
  }

  const type = message?.message_type || "text";
  switch(type){
    case "text":
      return `<p class="message-text">${escapeHtml(message.message||"")}</p>`;

    case "voice": {
      const url = safeFileUrlForMessage(message);
      if (!url) {
        return `<p class="message-text">ملف الصوت غير متاح.</p>`;
      }
      return `
        <div class="voice-message">
          <div class="audio-player apjs" data-src="${escapeHtml(url)}" dir="rtl">
            <audio preload="metadata" controlslist="nodownload noplaybackrate noremoteplayback" oncontextmenu="return false;"></audio>
            <div class="ap-top">
              <button class="ap-btn ap-play" aria-label="تشغيل/إيقاف"><i class="fas fa-play"></i></button>
              <div class="ap-time"><span class="ap-cur">0:00</span> / <span class="ap-dur">0:00</span></div>
              <button class="ap-btn ap-rewind" title="ترجيع 10 ثوانٍ" aria-label="ترجيع 10 ث"><i class="fas fa-forward"></i></button>
              <button class="ap-btn ap-forward" title="تقديم 10 ثوانٍ" aria-label="تقديم 10 ث"><i class="fas fa-backward"></i></button>
              <button class="ap-btn ap-speed" aria-label="السرعة">1x</button>
              <button class="ap-btn ap-mute" aria-label="كتم/إلغاء الكتم"><i class="fas fa-volume-up"></i></button>
              <input class="ap-volume" type="range" min="0" max="1" step="0.01" value="1" aria-label="مستوى الصوت">
            </div>
            <div class="ap-bottom"><input class="ap-seek" type="range" min="0" max="100" step="0.1" value="0" aria-label="التقدّم"></div>
          </div>
        </div>`;
    }

    case "file": {
      const name = message.file_name || 'ملف';
      const ext  = getExt(name);
      const url  = safeFileUrlForMessage(message);
      if (!url) {
        return `<div class="message-files"><p class="message-text">الملف غير متاح.</p></div>`;
      }
      // للمعاينة المؤقتة لا نضع رابط تحميل عائم
      const isTemp = !!message.temp_url;
      return `<div class="message-files">${buildFileCard({
        url, name, ext, withLink: !isTemp, floatingDownload: !isTemp
      })}</div>`;
    }

    default:
      return `<p class="message-text">${escapeHtml(message.message||"")}</p>`;
  }
}

// ======================= حذف الرسائل =======================
async function deleteMessage(messageId, messageElement){
  showConfirm({
    title:"حذف الرسالة",
    text:"هل أنت متأكد من حذف هذه الرسالة؟ لن يمكنك التراجع عن هذا الإجراء.",
    confirmText:"نعم، احذف",
    cancelText:"إلغاء",
    onConfirm: async ()=>{
      try{
        const response = await fetch(`/student/service-requests/message/${messageId}/delete`, {
          method:"POST",
          headers:{ "X-Requested-With":"XMLHttpRequest" },
          signal: getAbortSignal(),
        });
        const data = await response.json();
        if (data?.success){
          messageElement.classList.add('message-deleted');
          const delBtn = messageElement.querySelector('.message-delete-btn');
          if (delBtn) delBtn.remove();
          const content = messageElement.querySelector('.message-content');
          if (content){
            const header = content.querySelector('.message-header');
            content.innerHTML = '';
            if (header) content.appendChild(header);
            const p=document.createElement('p');
            p.className='message-text';
            p.textContent='تم حذف هذه الرسالة';
            content.appendChild(p);
          }
          showSuccess("تم حذف الرسالة بنجاح","تم",true);
        } else {
          showError(data?.message || "فشل في حذف الرسالة");
        }
      } catch(e){
        if (e.name !== 'AbortError') showError("حدث خطأ أثناء حذف الرسالة");
      }
    }
  });
}

// ======================= شريط معاينة الملفات =======================
function ensurePreviewStrip(){
  const inputArea=document.getElementById("inputArea");
  if (!inputArea){
    const chatContainer=document.getElementById("chatContainer");
    const messageForm=document.querySelector(".message-form, .chat-input, .input-container");
    const fallback=chatContainer || messageForm || null;
    if (fallback) createPreviewStripInContainer(fallback);
    return;
  }
  if (!document.getElementById("selectedFilesPreview")){
    const wrap=document.createElement("div");
    wrap.className="preview-strip";
    wrap.innerHTML=`<div class="preview-title" id="previewTitle" style="display:none;">ملفات جاهزة للإرسال</div>
                    <div id="selectedFilesPreview" class="preview-grid"></div>`;
    inputArea.appendChild(wrap);
  }
  renderPendingFilesPreview();
}

function createPreviewStripInContainer(container){
  if (document.getElementById("selectedFilesPreview")) return;
  const wrap=document.createElement("div");
  wrap.className="preview-strip";
  wrap.style.cssText="margin-top:10px; padding:10px; border-top:1px solid #e0e0e0;";
  wrap.innerHTML=`<div class="preview-title" id="previewTitle" style="display:none; font-size:12px; color:#666; margin-bottom:8px;">ملفات جاهزة للإرسال</div>
                  <div id="selectedFilesPreview" class="preview-grid" style="display:grid; gap:10px; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr));"></div>`;
  container.appendChild(wrap);
}

function renderPendingFilesPreview(){
  let grid=document.getElementById("selectedFilesPreview");
  let title=document.getElementById("previewTitle");
  if (!grid || !title){
    ensurePreviewStrip();
    grid=document.getElementById("selectedFilesPreview");
    title=document.getElementById("previewTitle");
    if (!grid || !title) return;
  }
  if (pendingFiles.length===0){ title.style.display="none"; grid.innerHTML=""; return; }
  title.style.display="block";
  grid.innerHTML = pendingFiles.map((item)=>{
    const ext=getExt(item.file.name);
    return buildFileCard({ url:item.objUrl, name:item.file.name, ext, withLink:false, floatingDownload:false, showRemove:true, removeId:item.id });
  }).join("");
  grid.querySelectorAll('.remove-btn').forEach((btn)=>{
    btn.addEventListener('click', (e)=>{
      e.preventDefault(); e.stopPropagation();
      const id=e.currentTarget.getAttribute('data-remove-id');
      removePendingFile(id);
    });
  });
}

function clearPendingFiles(){
  try{ pendingFiles.forEach(({objUrl})=>{ try{ URL.revokeObjectURL(objUrl);}catch(_){}}); }
  finally { pendingFiles=[]; renderPendingFilesPreview(); }
}

function removePendingFile(id){
  const idx=pendingFiles.findIndex(f=>f.id===id);
  if (idx>-1){
    const [item]=pendingFiles.splice(idx,1);
    try{ URL.revokeObjectURL(item.objUrl);}catch(_){}
  }
  renderPendingFilesPreview();
}

// ======================= إرسال الرسائل/الملفات =======================
async function handleSendAction(){
  if (isUploading){ showInfo("جاري رفع الملفات، يرجى الانتظار..."); return; }
  if (pendingFiles.length>0){
    try{ await sendPendingFiles(); } catch(e){ showError("حدث خطأ في إرسال الملفات: "+e.message); }
  } else {
    try{ await sendMessage(); } catch(e){ showError("حدث خطأ في إرسال الرسالة: "+e.message); }
  }
}

async function sendMessage(){
  const messageInput=document.getElementById("messageInput");
  if (!messageInput) return;
  const message=(messageInput.value||"").trim();
  if (!message || !currentRequestId) return;

  const tempId = `temp-${Date.now()}`;
  const tempMessage = {
    id: tempId, sender_id: currentUserId, is_self:1,
    sender_display_name:"أنت", sender_name:"أنت",
    message, message_type:"text", created_at:new Date().toISOString()
  };
  displayMessages([tempMessage], false);

  messageInput.value=""; messageInput.style.height="auto";

  try{
    const formData=new FormData();
    formData.append("message", message);
    formData.append("message_type","text");
    // ✅ مهم: مرّر الـ ID صراحةً
    formData.append("service_request_id", String(currentRequestId));

    const response = await fetch(`/student/service-requests/${currentRequestId}/message`, {
      method:"POST", body:formData,
      headers:{ "X-Requested-With":"XMLHttpRequest", "Accept":"application/json" },
      signal: getAbortSignal(),
    });

    // لوج تشخيصي قوي
    let data = null;
    try { data = await response.json(); }
    catch { console.warn('[sendMessage] Non-JSON response', response); }

    // فشل صريح
    if (!response.ok || !data?.success || !data?.message?.id){
      document.querySelector(`[data-message-id="${tempId}"]`)?.remove();
      showError(data?.message || `فشل في إرسال الرسالة (HTTP ${response.status}).`);
      return;
    }

    // نجاح: ثبّت الـ ID الحقيقي ثم اسحب نسخة السيرفر للتأكد أنها محفوظة
    const el=document.querySelector(`[data-message-id="${tempId}"]`);
    if (el) el.dataset.messageId=String(data.message.id);
    lastMessageId = Number(data.message.id)||lastMessageId;

    // ✅ خطوة تأكيد: اجلب أي رسائل جديدة من السيرفر الآن
    await checkNewMessages();
    scheduleMarkRead();
  } catch(e){
    if (e.name !== 'AbortError'){
      document.querySelector(`[data-message-id="${tempId}"]`)?.remove();
      showError("حدث خطأ أثناء إرسال الرسالة");
    }
  }
}

async function handleFileSelect(event){
  const input=event.target, files=input.files;
  if (!files?.length) return;
  ensurePreviewStrip();
  let added=0;
  for (let i=0; i<files.length; i++){
    try{
      const file=files[i];
      const id=`pf-${Date.now()}-${i}-${Math.random().toString(36).slice(2)}`;
      const objUrl=URL.createObjectURL(file);
      pendingFiles.push({ file, id, objUrl });
      added++;
    }catch(_){}
  }
  input.value="";
  renderPendingFilesPreview();
  showInfo(`تمت إضافة ${added} ملف للمعاينة. اضغط إرسال لرفعها.`);
}

async function sendPendingFiles(){
  if (!currentRequestId){ showError("يجب اختيار طلب أولاً"); return; }
  if (pendingFiles.length===0){ showInfo("لا توجد ملفات لإرسالها"); return; }
  if (isUploading){ showInfo("جاري رفع الملفات، يرجى الانتظار..."); return; }

  const sendBtn=document.getElementById("sendBtn");
  const originalBtnHtml=sendBtn?.innerHTML;
  isUploading=true;
  if (sendBtn){ sendBtn.setAttribute('disabled','disabled'); sendBtn.innerHTML='<i class="fas fa-spinner fa-spin"></i> جاري الرفع...'; }

  const filesToUpload=[...pendingFiles];
  const tempIds=[];

  try{
    const temps=filesToUpload.map((item,idx)=>({
      id:`temp-file-${Date.now()}-${idx}-${Math.random().toString(36).slice(2)}`,
      is_self:1, sender_display_name:"أنت",
      message_type:"file", file_name:item.file.name,
      temp_url:item.objUrl, created_at:new Date().toISOString()
    }));
    temps.forEach(t=>tempIds.push(t.id));
    displayMessages(temps,false);

    const formData=new FormData();
    formData.append("message_type","file");
    // ✅ مهم
    formData.append("service_request_id", String(currentRequestId));

    let valid=0;
    filesToUpload.forEach(({file})=>{
      if (file && file instanceof File){ formData.append("files[]", file); valid++; }
    });
    if (!valid) throw new Error("لا توجد ملفات صالحة للرفع");

    showInfo(`جاري رفع ${valid} ملف...`);
    const response = await fetch(`/student/service-requests/${currentRequestId}/message`, {
      method:"POST", body:formData,
      headers:{ "X-Requested-With":"XMLHttpRequest", "Accept":"application/json" },
      signal: getAbortSignal(),
    });

    let data = null;
    try { data = await response.json(); }
    catch { console.warn('[sendPendingFiles] Non-JSON response', response); }

    // أزل المؤقتة أيًا كان
    tempIds.forEach((id)=> document.querySelector(`[data-message-id="${id}"]`)?.remove());

    if (!response.ok || !data?.success){
      showError(data?.message || `فشل في رفع الملفات (HTTP ${response.status}).`);
      return;
    }

    clearPendingFiles();

    // إذا رجّع السيرفر الرسائل الجديدة اعرضها، وإلا اجلبها بنفسك
    const serverMessages = Array.isArray(data.messages)? data.messages : (data.message?[data.message]:[]);
    if (serverMessages.length){
      displayMessages(serverMessages,false);
      const maxId=Math.max(...serverMessages.map(m=>Number(m.id||0)));
      if (isFinite(maxId)) lastMessageId=maxId;
    } else {
      await checkNewMessages(); // ✅ تأكيد الحفظ
    }

    showSuccess("تم رفع الملفات بنجاح","تم",true);
    scheduleMarkRead();
  } catch(e){
    tempIds.forEach((id)=> document.querySelector(`[data-message-id="${id}"]`)?.remove());
    if (e.name !== 'AbortError') showError("حدث خطأ أثناء رفع الملفات: "+e.message);
  } finally {
    isUploading=false;
    if (sendBtn){ sendBtn.removeAttribute('disabled'); sendBtn.innerHTML=originalBtnHtml || '<i class="fas fa-paper-plane"></i>'; }
  }
}

// ======================= التسجيل الصوتي =======================
async function toggleVoiceRecording(){ (mediaRecorder && mediaRecorder.state==="recording") ? stopRecording() : startRecording(); }

async function startRecording(){
  try{
    const stream=await navigator.mediaDevices.getUserMedia({ audio:true });
    mediaRecorder=new MediaRecorder(stream); audioChunks=[];
    mediaRecorder.ondataavailable=(e)=>{ audioChunks.push(e.data); };
    mediaRecorder.onstop=async ()=>{
      try{ stream.getTracks().forEach(t=>t.stop()); }catch(_){}
      if (audioChunks.length>0 && mediaRecorder.stopReason!=="cancel"){
        const audioBlob=new Blob(audioChunks,{ type:"audio/webm" });
        await sendVoiceMessage(audioBlob);
      }
      audioChunks=[]; mediaRecorder=null;
    };
    mediaRecorder.start(); recordingStartTime=Date.now();
    document.getElementById("voiceBtn")?.classList.add("recording");
    const vr=document.getElementById("voiceRecording"); if (vr) vr.style.display="flex";
    updateRecordingTime(); recordingTimer=setInterval(updateRecordingTime,1000);
    setTimeout(()=>{ if (mediaRecorder && mediaRecorder.state==="recording"){ stopRecording(); showInfo("تم إيقاف التسجيل - الحد الأقصى 3 دقائق"); } }, 180000);
  } catch(_){ showError("لا يمكن الوصول إلى الميكروفون"); }
}

function stopRecording(){
  if (mediaRecorder && mediaRecorder.state==="recording"){
    mediaRecorder.stopReason="stop"; mediaRecorder.stop();
    if (recordingTimer) clearInterval(recordingTimer);
    document.getElementById("voiceBtn")?.classList.remove("recording");
    const vr=document.getElementById("voiceRecording"); if (vr) vr.style.display="none";
  }
}

function cancelRecording(){
  if (mediaRecorder && mediaRecorder.state==="recording"){
    mediaRecorder.stopReason="cancel"; mediaRecorder.stop();
    if (recordingTimer) clearInterval(recordingTimer);
    document.getElementById("voiceBtn")?.classList.remove("recording");
    const vr=document.getElementById("voiceRecording"); if (vr) vr.style.display="none";
    audioChunks=[];
  }
}

function updateRecordingTime(){
  if (!recordingStartTime) return;
  const el=document.getElementById("recordingTime"); if (!el) return;
  const elapsed=Math.floor((Date.now()-recordingStartTime)/1000);
  const minutes=Math.floor(elapsed/60), seconds=elapsed%60;
  el.textContent = `${minutes.toString().padStart(2,"0")}:${seconds.toString().padStart(2,"0")}`;
}

async function sendVoiceMessage(audioBlob){
  if (!currentRequestId) return;
  const formData=new FormData();
  formData.append("message_type","voice");
  formData.append("voice", audioBlob, "recording.webm");
  // ✅ مهم
  formData.append("service_request_id", String(currentRequestId));

  try{
    showInfo("جاري إرسال التسجيل الصوتي...");
    const response = await fetch(`/student/service-requests/${currentRequestId}/message`, {
      method:"POST", body:formData,
      headers:{ "X-Requested-With":"XMLHttpRequest", "Accept":"application/json" },
      signal: getAbortSignal(),
    });

    let data = null;
    try { data = await response.json(); }
    catch { console.warn('[sendVoiceMessage] Non-JSON response', response); }

    if (!response.ok || !data?.success){
      showError(data?.message || `فشل في إرسال التسجيل الصوتي (HTTP ${response.status}).`);
      return;
    }

    if (data.message){
      displayMessages([data.message],false);
      requestAnimationFrame(()=> {
        const container=document.getElementById('chatContainer')||document.getElementById('messagesList')||document;
        if (window.AudioPlayer?.mountAll) AudioPlayer.mountAll(container);
      });
      if (data.message.id) lastMessageId=Number(data.message.id);
    } else {
      // ✅ في حال السيرفر لم يرجع الرسالة، اجلبها بنفسك
      await checkNewMessages();
    }

    showSuccess("تم إرسال التسجيل الصوتي","تم",true);
    scheduleMarkRead();
  } catch(e){
    if (e.name !== 'AbortError') showError("حدث خطأ أثناء إرسال التسجيل الصوتي");
  }
}

// ======================= إدارة الطلب =======================
async function cancelRequest(){
  if (!currentRequestId) return;
  showConfirm({
    title:"إلغاء الطلب",
    text:"هل أنت متأكد من إلغاء هذا الطلب؟ لن يمكنك التراجع عن هذا الإجراء.",
    confirmText:"نعم، ألغِ الطلب",
    cancelText:"تراجع",
    onConfirm: async ()=>{
      try{
        const response = await fetch(`/student/service-requests/${currentRequestId}/cancel`, {
          method:"POST", headers:{ "X-Requested-With":"XMLHttpRequest" }, signal: getAbortSignal(),
        });
        const data = await response.json();
        if (data?.success){
          showSuccess("تم إلغاء الطلب بنجاح");
          await loadRequests();
          await loadRequest(currentRequestId);
        } else {
          showError(data?.message || "فشل في إلغاء الطلب");
        }
      } catch(e){
        if (e.name !== 'AbortError') showError("حدث خطأ أثناء إلغاء الطلب");
      }
    }
  });
}

function toggleRequestDetails(){
  const details=document.getElementById("requestDetails");
  if (!details) return;
  details.style.display = (details.style.display==="none" || getComputedStyle(details).display==="none") ? "block":"none";
}

function downloadFile(fileId){ if (!fileId) return; window.open(`/student/service-requests/file/${fileId}`,"_blank"); }

// ======================= mark-read (محمي) =======================
async function markMessagesAsRead(requestId){
  if (!requestId) return;
  if (markReadInflight.has(requestId)) return markReadInflight.get(requestId);

  const p = (async ()=>{
    try{
      await fetch(`/student/service-requests/${requestId}/mark-read`, {
        method:"POST",
        headers:{ "X-Requested-With":"XMLHttpRequest" },
        signal: getAbortSignal(),
      });
    } catch(_) {
      // تجاهل
    } finally {
      markReadInflight.delete(requestId);
    }
  })();

  markReadInflight.set(requestId, p);
  return p;
}

// ======================= التحديثات الدورية =======================
// تسلسل + احترام تحميل الميديا + backoff بسيط
let pollingBusy = false;
let pollingBackoffMs = 0;
const POLLING_BASE_MS = 5000;
const POLLING_MAX_BACKOFF = 30000;

async function pollingTick(){
  if (pollingBusy) return;
  if (document.hidden) return;
  if (mediaLoadingInFlight > 0) return;
  pollingBusy = true;
  try {
    await checkNewMessages();
    await checkUnreadCounts();
    pollingBackoffMs = 0; // نجاح
  } catch {
    // في حال خطأ غير متوقع
    pollingBackoffMs = Math.min(POLLING_MAX_BACKOFF, (pollingBackoffMs||0) ? pollingBackoffMs*2 : 5000);
  } finally {
    pollingBusy = false;
  }
}

async function checkNewMessages(){
  if (!currentRequestId) return;
  try{
    const response = await fetch(
      `/student/service-requests/${currentRequestId}/new-messages?last_id=${encodeURIComponent(lastMessageId||0)}`,
      { headers:{ "X-Requested-With":"XMLHttpRequest", "Accept":"application/json" }, signal: getAbortSignal() }
    );

    if (!response.ok) return; // بصمت: لا نكسر الحلقة في حال خطأ عابر

    const data = await response.json();
    if (data?.success && Array.isArray(data.messages) && data.messages.length > 0){
      // ✅ فرز تصاعدي دائمًا: الوقت ثم الـ ID
      const incoming = [...data.messages].sort((a, b) => {
        const ta = Date.parse(normalizeDateInput(a?.created_at || "")) || 0;
        const tb = Date.parse(normalizeDateInput(b?.created_at || "")) || 0;
        if (ta !== tb) return ta - tb;
        const ia = Number(a?.id) || 0;
        const ib = Number(b?.id) || 0;
        return ia - ib;
      });

      displayMessages(incoming, false);

      const maxId = Math.max(...incoming.map(m => Number(m.id) || 0));
      if (isFinite(maxId)) lastMessageId = maxId;

      scheduleMarkRead();
    }
  } catch(e){
    // تجاهل أخطاء الإلغاء/الشبكة المؤقتة
  }
}

async function checkUnreadCounts(){
  try{
    const response = await fetch("/student/service-requests/unread-counts", {
      headers:{ "X-Requested-With":"XMLHttpRequest" },
      signal: getAbortSignal(),
    });
    const data = await response.json();

    if (data?.success && data.counts){
      Object.keys(data.counts).forEach((requestId) => {
        let newCount = data.counts[requestId] || 0;
        const oldCount = unreadCounts[requestId] || 0;

        // لا تُظهر أي بادج للطلب المفتوح حالياً
        if (String(requestId) === String(currentRequestId)) {
          newCount = 0;
        }

        // حدّث الحالة الداخلية والـ DOM فقط عند اختلاف القيمة
        if (newCount !== oldCount){
          unreadCounts[requestId] = newCount;

          const item = document.querySelector(`.request-item[data-id="${requestId}"]`);
          if (item){
            let badge = item.querySelector(".unread-badge");
            if (newCount > 0){
              if (!badge){
                badge = document.createElement("span");
                badge.className = "unread-badge";
                badge.id = `unread-${requestId}`;
                item.appendChild(badge);
              }
              badge.textContent = newCount;
            } else if (badge){
              badge.remove();
            }
          }
        }
      });

      // ✅ تحديث بادج السايدبار فوراً
      const sidebarBadge = document.getElementById("serviceRequestsBadge");
      if (sidebarBadge){
        const totalUnread = Object.values(unreadCounts).reduce((sum, c) => sum + (Number(c) || 0), 0);
        if (totalUnread > 0){
          sidebarBadge.textContent = totalUnread;
          sidebarBadge.style.display = "inline-block";
        } else {
          sidebarBadge.style.display = "none";
        }
      }
    }
  } catch(e){
    // تجاهل
  }
}

function startMessagesCheck(forceRestart=false){
  if (messagesCheckInterval && !forceRestart) return;
  if (messagesCheckInterval) { clearInterval(messagesCheckInterval); messagesCheckInterval=null; }

  const tick = async () => {
    await pollingTick();
  };
  // أول تشغيل سريع
  tick();
  messagesCheckInterval = setInterval(tick, POLLING_BASE_MS);
}

function stopMessagesCheck(){
  if (messagesCheckInterval){
    clearInterval(messagesCheckInterval);
    messagesCheckInterval=null;
  }
}

// ======================= أدوات عامة =======================
window.openNewServiceModal = function(){
  if (typeof window.openServiceRequestModal === "function") {
    window.openServiceRequestModal();
  } else {
    window.location.href="/student/dashboard#new-request";
  }
};

function sortAscByTimeOrId(arr){
  return [...(arr || [])].sort((a, b) => {
    const ta = Date.parse(normalizeDateInput(a?.created_at || "")) || 0;
    const tb = Date.parse(normalizeDateInput(b?.created_at || "")) || 0;
    if (ta !== tb) return ta - tb;           // فرز زمني تصاعدي
    const ia = Number(a?.id) || 0;
    const ib = Number(b?.id) || 0;
    return ia - ib;                           // حسم التعادل بالـ id
  });
}

function isFromCurrentUser(msg){ return !!(msg && (msg.is_self===1 || msg.is_self==="1" || msg.is_self===true)); }

function escapeHtml(text){
  if (text===null || text===undefined) return "";
  const map={ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" };
  return text.toString().replace(/[&<>"']/g,(m)=>map[m]);
}

// ⚠️ التعامل مع تواريخ Safari (استبدال المسافة بـ 'T' عند التحويل)
function normalizeDateInput(dateString){
  if (!dateString) return '';
  if (typeof dateString === 'string' && dateString.includes(' ') && !dateString.includes('T')) {
    return dateString.replace(' ', 'T');
  }
  return dateString;
}

function formatDate(dateString){
  if (!dateString) return "";
  const dStr = normalizeDateInput(dateString);
  const date=new Date(dStr); if (isNaN(date.getTime())) return "";
  const now=new Date();
  const sameDay = date.getFullYear()===now.getFullYear() && date.getMonth()===now.getMonth() && date.getDate()===now.getDate();
  const yesterday=new Date(now); yesterday.setDate(now.getDate()-1);
  const isYesterday = date.getFullYear()===yesterday.getFullYear() && date.getMonth()===yesterday.getMonth() && date.getDate()===yesterday.getDate();
  if (sameDay) return "اليوم";
  if (isYesterday) return "أمس";
  return date.toLocaleDateString("ar-EG",{ year:"numeric", month:"long", day:"numeric", calendar:"gregory" });
}

function formatTime(dateString){
  if (!dateString) return "";
  const dStr = normalizeDateInput(dateString);
  const date=new Date(dStr); if (isNaN(date.getTime())) return "";
  return date.toLocaleTimeString("ar-SA",{ hour:"2-digit", minute:"2-digit" });
}

function getStatusText(status){
  const map={ pending:"قيد الانتظار", in_progress:"قيد المعالجة", resolved:"تم الحل", closed:"مغلق", cancelled:"ملغي" };
  return map[status] || status || "";
}

// ======================= تنظيف عند مغادرة الصفحة =======================
export function cleanup(){
  stopMessagesCheck();

  if (svcAbortController){
    try { svcAbortController.abort(); } catch(_) {}
    svcAbortController = null;
  }

  if (onDocClick){ document.removeEventListener("click", onDocClick); onDocClick=null; }
  if (onDocChange){ document.removeEventListener("change", onDocChange); onDocChange=null; }
  handlersAttached = false;

  if (mediaRecorder && mediaRecorder.state==="recording") { cancelRecording(); }

  currentRequestId = null;
  lastMessageId = 0;
  pendingFiles = [];
  renderedMessageIds.clear();

  if (markReadDebounceTimer){ clearTimeout(markReadDebounceTimer); markReadDebounceTimer=null; }
  markReadInflight.clear();

  if (ioMedia){ try{ ioMedia.disconnect(); }catch(_){ } ioMedia = null; }

  oldestMessageId = null;
  hasMoreHistory = true;
  isLoadingHistory = false;
  historyScrollAttached = false;

  document.removeEventListener('visibilitychange', handleVisibility);
  window.removeEventListener('online', startMessagesCheck);
  window.removeEventListener('offline', stopMessagesCheck);
}

// ======================= Export لواجهة Window =======================
window.loadRequest = loadRequest;
window.sendMessage = sendMessage;
window.attachFile = () => {
  const fileInput=document.getElementById("chatFileInput");
  if (fileInput) fileInput.click();
  else showError("لا يمكن فتح مربع اختيار الملفات");
};
window.toggleVoiceRecording = toggleVoiceRecording;
window.stopRecording = stopRecording;
window.cancelRecording = cancelRecording;
window.cancelRequest = cancelRequest;
window.toggleRequestDetails = toggleRequestDetails;
window.downloadFile = downloadFile;
window.handleSendAction = handleSendAction;
window.sendPendingFiles = sendPendingFiles;