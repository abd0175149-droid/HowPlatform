/* ==========================================================================
 * Admin Service Chat — إعادة بناء (محاكاة شات الطالب: NotebookLM + لحظي)
 * MySQL مصدر الحقيقة، Firebase إشارة + fallback إلى polling.
 * يُحمَّل كقسم admin SPA (default.mount/unmount).
 * ========================================================================== */

const SVC = '/admins/api/service-requests';
const FB_SDK = 'https://www.gstatic.com/firebasejs/12.15.0';
const RT_POLL_MS = 4000;       // تحديث شبه فوري عبر polling
const RT_FIREBASE = false;     // فعّله = true بعد نشر قواعد Firestore للحظي الفوري

const state = {
  uid: 0,
  status: '', q: '',
  requests: [],
  currentReqId: 0,
  currentReq: null,
  rendered: new Set(),
  oldestId: 0, latestId: 0,
  hasMoreOlder: false,
  files: [],
  pollTimer: null,
  fb: null,
  els: {},
  searchTimer: null,
};

const $ = (id) => document.getElementById(id);
function esc(s) { const d = document.createElement('span'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
function csrf() {
  const m = document.querySelector('meta[name="csrf-token"]');
  if (m) return m.getAttribute('content') || '';
  const c = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]+)/i);
  return c ? decodeURIComponent(c[1]) : '';
}
function timeShort(ts) {
  if (!ts) return '';
  const d = new Date(String(ts).replace(' ', 'T'));
  if (isNaN(d.getTime())) return esc(ts);
  const diff = (Date.now() - d.getTime()) / 1000;
  if (diff < 60) return 'الآن';
  if (diff < 3600) return Math.floor(diff / 60) + ' د';
  if (diff < 86400) return Math.floor(diff / 3600) + ' س';
  if (diff < 604800) return Math.floor(diff / 86400) + ' يوم';
  try { return new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'short' }).format(d); } catch (e) { return esc(ts); }
}
function timeFull(ts) {
  if (!ts) return '';
  const d = new Date(String(ts).replace(' ', 'T'));
  if (isNaN(d.getTime())) return esc(ts);
  try { return new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(d); }
  catch (e) { return esc(ts); }
}
function fileIcon(mime, name) {
  mime = String(mime || ''); const ext = String(name || '').split('.').pop().toLowerCase();
  if (mime.startsWith('image/')) return 'fa-file-image';
  if (mime.startsWith('audio/')) return 'fa-file-audio';
  if (mime.startsWith('video/')) return 'fa-file-video';
  if (ext === 'pdf') return 'fa-file-pdf';
  if (['doc', 'docx'].includes(ext)) return 'fa-file-word';
  if (['xls', 'xlsx'].includes(ext)) return 'fa-file-excel';
  if (['ppt', 'pptx'].includes(ext)) return 'fa-file-powerpoint';
  if (['zip', 'rar', '7z'].includes(ext)) return 'fa-file-archive';
  return 'fa-file';
}
function fileUrl(id, disp) { return `${SVC}/file/${id}?disposition=${disp || 'inline'}`; }
function statusLabel(s) {
  return ({ pending: 'قيد الانتظار', in_progress: 'قيد المعالجة', resolved: 'تم الحل', closed: 'مغلق', cancelled: 'ملغى' })[s] || s;
}

async function apiGet(path) {
  const r = await fetch(SVC + path, { credentials: 'include', headers: { 'Accept': 'application/json', 'Cache-Control': 'no-store' }, cache: 'no-store' });
  return r.json().catch(() => null);
}
async function apiPostForm(path, fd) {
  fd.append('csrf_test_name', csrf());
  const r = await fetch(SVC + path, { method: 'POST', credentials: 'include',
    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
  return r.json().catch(() => null);
}

/* ===================== قائمة الطلبات ===================== */
function renderTabs(counts) {
  counts = counts || {};
  const tabs = [
    { k: '', label: 'الكل', c: counts.all },
    { k: 'pending', label: 'قيد الانتظار', c: counts.pending },
    { k: 'in_progress', label: 'قيد المعالجة', c: counts.in_progress },
    { k: 'resolved', label: 'تم الحل', c: counts.resolved },
    { k: 'cancelled', label: 'ملغى', c: counts.cancelled },
  ];
  state.els.tabs.innerHTML = tabs.map(t =>
    `<button class="svc-tab ${state.status === t.k ? 'active' : ''}" data-k="${t.k}">${esc(t.label)}` +
    (t.c != null ? ` <span class="cnt">${t.c}</span>` : '') + `</button>`).join('');
  state.els.tabs.querySelectorAll('.svc-tab').forEach(b =>
    b.addEventListener('click', () => { state.status = b.dataset.k; loadRequests(); }));
}

function renderRequests() {
  const list = state.els.list;
  if (!state.requests.length) {
    list.innerHTML = `<div class="svc-state"><i class="fas fa-inbox" style="font-size:2rem;color:var(--primary-300)"></i><p>لا توجد طلبات${state.q ? ' مطابقة' : ''}.</p></div>`;
    return;
  }
  list.innerHTML = state.requests.map(r => {
    const unread = Number(r.unread_count) || 0;
    const prev = String(r.description || '').replace(/\s+/g, ' ').trim();
    return `<div class="svc-item ${r.id == state.currentReqId ? 'active' : ''}" data-id="${r.id}">
      <div class="svc-item-top">
        <span class="svc-item-subj">${esc(r.subject)}</span>
        <span class="svc-item-time">${timeShort(r.created_at)}</span>
      </div>
      ${prev ? `<div class="svc-item-prev" style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:2px 0 4px"><i class="fas fa-quote-right" style="font-size:.62rem;opacity:.6"></i> ${esc(prev.slice(0, 90))}</div>` : ''}
      <div class="svc-item-sub">
        <span class="svc-item-course">${r.course_name ? '<i class="fas fa-book" style="font-size:.7rem"></i> ' + esc(r.course_name) : '<i class="fas fa-headset" style="font-size:.7rem"></i> خدمة عامة'}</span>
        ${unread ? `<span class="svc-unread">${unread}</span>` : ''}
        <span class="svc-status st-${esc(r.status)}">${esc(statusLabel(r.status))}</span>
      </div>
    </div>`;
  }).join('');
  list.querySelectorAll('.svc-item').forEach(it =>
    it.addEventListener('click', () => openRequest(Number(it.dataset.id))));
}

async function loadRequests(keepOpen) {
  const params = new URLSearchParams({ limit: '20', offset: '0' });
  if (state.status) params.set('status', state.status);
  if (state.q) params.set('q', state.q);
  const res = await apiGet('/list?' + params.toString());
  if (!res || !res.success) { state.els.list.innerHTML = `<div class="svc-state">تعذّر تحميل الطلبات.</div>`; return; }
  state.requests = res.data.items || [];
  renderTabs(res.data.counts);
  renderRequests();
  if (!keepOpen && !state.currentReqId && state.requests.length && window.innerWidth > 920) {
    openRequest(Number(state.requests[0].id));
  }
}

/* ===================== المحادثة ===================== */
function bubbleHtml(m) {
  const isAi = m.sender_role === 'ai';
  const cls = isAi ? 'ai' : (Number(m.is_me) === 1 ? 'me' : 'them');
  const originTag = Number(m.is_origin) === 1
    ? ` <span class="svc-origin-tag" style="font-size:.66rem;font-weight:700;color:var(--primary-700);background:var(--primary-50);border:1px solid var(--primary-300);border-radius:999px;padding:1px 7px;">📩 الطلب الأصلي</span>`
    : '';
  const sender = isAi
    ? `<span class="svc-sender"><i class="fas fa-robot"></i> فَطين</span>`
    : `<span class="svc-sender">${esc(m.sender_name || (Number(m.is_me) === 1 ? 'أنا' : 'الطالب'))}</span>${originTag}`;
  let files = '';
  if (Array.isArray(m.files) && m.files.length) {
    files = '<div class="svc-files">' + m.files.map(f => {
      const url = fileUrl(f.file_id, 'inline');
      if (String(f.mime_type || '').startsWith('image/')) {
        return `<a class="svc-file" href="${url}" target="_blank" rel="noopener"><img class="thumb" src="${url}" alt="${esc(f.file_name)}" loading="lazy"></a>`;
      }
      return `<a class="svc-file" href="${fileUrl(f.file_id, 'attachment')}" target="_blank" rel="noopener"><i class="fas ${fileIcon(f.mime_type, f.file_name)}"></i><span class="nm">${esc(f.file_name)}</span></a>`;
    }).join('') + '</div>';
  }
  return `<div class="svc-msg ${cls}" data-id="${m.id}">
    <div class="svc-bubble">
      <div class="svc-msg-head">${sender} <span class="svc-time">${timeFull(m.created_at)}</span></div>
      ${m.body ? `<p class="svc-text">${esc(m.body)}</p>` : ''}
      ${files}
    </div>
  </div>`;
}

function appendMessages(items, prepend) {
  const frag = document.createDocumentFragment();
  let added = 0;
  (items || []).forEach(m => {
    if (state.rendered.has(m.id)) return;
    state.rendered.add(m.id);
    const tmp = document.createElement('div'); tmp.innerHTML = bubbleHtml(m);
    frag.appendChild(tmp.firstElementChild);
    added++;
    if (!state.oldestId || m.id < state.oldestId) state.oldestId = m.id;
    if (m.id > state.latestId) state.latestId = m.id;
  });
  if (!added) return 0;
  const wrap = state.els.msgs;
  if (prepend) {
    const prevH = wrap.scrollHeight;
    wrap.insertBefore(frag, wrap.firstChild ? wrap.firstChild.nextSibling : null);
    wrap.scrollTop = wrap.scrollHeight - prevH;
  } else {
    const nearBottom = wrap.scrollHeight - wrap.scrollTop - wrap.clientHeight < 120;
    wrap.appendChild(frag);
    if (nearBottom) wrap.scrollTop = wrap.scrollHeight;
  }
  return added;
}

function renderChatHeader() {
  const r = state.currentReq || {};
  state.els.subj.textContent = r.subject || '—';
  const bits = [];
  if (r.student_name) bits.push(`<span><i class="fas fa-user" style="font-size:.7rem"></i> ${esc(r.student_name)}</span>`);
  if (r.course_name) bits.push(`<span><i class="fas fa-book" style="font-size:.7rem"></i> ${esc(r.course_name)}</span>`);
  if (r.created_at) bits.push(`<span><i class="far fa-calendar" style="font-size:.7rem"></i> ${timeFull(r.created_at)}</span>`);
  state.els.meta.innerHTML = bits.join('');
  if (state.els.statusSel) state.els.statusSel.value = r.status || 'pending';
  ensureDeleteBtn();
}

/* زر الحذف النهائي — للأدمن (role=1) فقط، لمسح التجارب أو المخالفات */
function ensureDeleteBtn() {
  if (Number(window.__ADMIN_ROLE__) !== 1) return;
  let btn = document.getElementById('svc-delete-btn');
  if (btn) return;
  const host = (state.els.statusSel && state.els.statusSel.parentElement)
    || (state.els.subj && state.els.subj.closest('.svc-chead'))
    || (state.els.subj && state.els.subj.parentElement);
  if (!host) return;
  btn = document.createElement('button');
  btn.id = 'svc-delete-btn'; btn.type = 'button'; btn.title = 'حذف الطلب نهائياً';
  btn.style.cssText = 'margin-inline-start:6px;border:1px solid var(--border-color);background:var(--surface-1);color:var(--text-muted);border-radius:8px;width:34px;height:34px;cursor:pointer;flex:0 0 auto;';
  btn.innerHTML = '<i class="fas fa-trash"></i>';
  btn.addEventListener('mouseenter', () => { btn.style.color = '#ef4444'; btn.style.borderColor = '#ef4444'; });
  btn.addEventListener('mouseleave', () => { btn.style.color = 'var(--text-muted)'; btn.style.borderColor = 'var(--border-color)'; });
  btn.addEventListener('click', deleteCurrentRequest);
  host.appendChild(btn);
}

async function deleteCurrentRequest() {
  const id = state.currentReqId; if (!id) return;
  let go = false;
  if (typeof Swal !== 'undefined') {
    const r = await Swal.fire({
      icon: 'warning', title: `حذف الطلب #${id} نهائياً؟`,
      text: 'سيُحذف الطلب وكل رسائله ومرفقاته بلا رجعة. (لمسح التجارب أو الطلبات المخالفة للسياسات)',
      showCancelButton: true, confirmButtonText: 'نعم، احذف نهائياً', cancelButtonText: 'إلغاء',
      confirmButtonColor: '#ef4444',
    });
    go = r.isConfirmed;
  } else {
    go = window.confirm('حذف الطلب نهائياً؟ لا يمكن التراجع.');
  }
  if (!go) return;

  const res = await apiPostForm('/' + id + '/delete', new FormData());
  if (res && res.success) {
    state.requests = state.requests.filter(r => r.id != id);
    state.currentReqId = 0; state.currentReq = null; stopRealtime();
    state.els.chat.classList.remove('show'); state.els.root.classList.remove('in-chat');
    if (state.els.empty) state.els.empty.style.display = '';
    renderRequests(); loadRequests(true);
    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: 'تم الحذف نهائياً', toast: true, position: 'top-end', timer: 1500, showConfirmButton: false });
  } else {
    const msg = (res && res.message) || 'تعذّر حذف الطلب';
    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'خطأ', text: msg }); else alert(msg);
  }
}

async function openRequest(reqId) {
  if (!reqId) return;
  state.currentReqId = reqId;
  state.rendered.clear(); state.oldestId = 0; state.latestId = 0; state.hasMoreOlder = false;
  document.querySelectorAll('.svc-item').forEach(it => it.classList.toggle('active', Number(it.dataset.id) === reqId));
  state.els.empty.style.display = 'none';
  state.els.chat.classList.add('show');
  state.els.root.classList.add('in-chat');
  state.els.msgs.innerHTML = `<div class="svc-skel"></div><div class="svc-skel"></div>`;

  const det = await apiGet('/' + reqId);
  if (!det || !det.success) { state.els.msgs.innerHTML = `<div class="svc-state">تعذّر فتح الطلب.</div>`; return; }
  state.currentReq = det.data.request;
  renderChatHeader();

  const msgs = await apiGet(`/${reqId}/messages?limit=25`);
  state.els.msgs.innerHTML = '';
  if (msgs && msgs.success) {
    state.hasMoreOlder = !!msgs.data.has_more;
    renderOlderButton();
    appendMessages(msgs.data.items, false);
    state.els.msgs.scrollTop = state.els.msgs.scrollHeight;
    markRead();
  }
  startRealtime(reqId);
  const row = state.requests.find(r => r.id == reqId); if (row) { row.unread_count = 0; renderRequests(); }
}

function renderOlderButton() {
  let btn = state.els.msgs.querySelector('.svc-load-older');
  if (state.hasMoreOlder) {
    if (!btn) {
      btn = document.createElement('button'); btn.className = 'svc-load-older'; btn.textContent = 'تحميل رسائل أقدم';
      btn.addEventListener('click', loadOlder);
      state.els.msgs.insertBefore(btn, state.els.msgs.firstChild);
    }
  } else if (btn) { btn.remove(); }
}

async function loadOlder() {
  if (!state.oldestId) return;
  const res = await apiGet(`/${state.currentReqId}/messages?limit=25&before_id=${state.oldestId}`);
  if (res && res.success) { state.hasMoreOlder = !!res.data.has_more; appendMessages(res.data.items, true); renderOlderButton(); }
}

async function pollNewer() {
  if (!state.currentReqId) return;
  const res = await apiGet(`/${state.currentReqId}/messages?limit=25`);
  if (res && res.success) {
    const fresh = (res.data.items || []).filter(m => m.id > state.latestId);
    if (fresh.length) { appendMessages(fresh, false); markRead(); refreshListSilently(); }
  }
}

async function markRead() {
  if (!state.currentReqId || !state.latestId) return;
  const fd = new FormData(); fd.append('last_id', String(state.latestId));
  try { await apiPostForm(`/${state.currentReqId}/read-up-to`, fd); } catch (e) {}
  try { window.dispatchEvent(new Event('admins:svc-badge:refresh')); } catch (e) {}
}

async function refreshListSilently() {
  const params = new URLSearchParams({ limit: '20', offset: '0' });
  if (state.status) params.set('status', state.status);
  if (state.q) params.set('q', state.q);
  const res = await apiGet('/list?' + params.toString());
  if (res && res.success) { state.requests = res.data.items || []; renderTabs(res.data.counts); renderRequests(); }
}

/* ===================== الإرسال + الحالة ===================== */
function renderAttached() {
  state.els.attached.innerHTML = state.files.map((f, i) =>
    `<span class="svc-chip"><i class="fas ${fileIcon(f.type, f.name)}" style="color:var(--primary-500)"></i> ${esc(f.name)} <button data-i="${i}" title="إزالة">&times;</button></span>`).join('');
  state.els.attached.querySelectorAll('button').forEach(b =>
    b.addEventListener('click', () => { state.files.splice(Number(b.dataset.i), 1); renderAttached(); }));
}

async function sendMessage() {
  const body = state.els.input.value.trim();
  if (!body && !state.files.length) return;
  if (!state.currentReqId) return;
  state.els.sendBtn.disabled = true;
  const fd = new FormData();
  fd.append('body', body);
  state.files.forEach(f => fd.append('files[]', f, f.name));
  const res = await apiPostForm(`/${state.currentReqId}/messages`, fd);
  state.els.sendBtn.disabled = false;
  if (res && res.success) {
    state.els.input.value = ''; autosize(); state.files = []; renderAttached();
    await pollNewer();
    state.els.msgs.scrollTop = state.els.msgs.scrollHeight;
  } else { alert((res && res.message) || 'تعذّر إرسال الرسالة.'); }
}

function autosize() { const t = state.els.input; if (!t) return; t.style.height = 'auto'; t.style.height = Math.min(140, t.scrollHeight) + 'px'; }

async function changeStatus(value) {
  if (!state.currentReqId || !value) return;
  const fd = new FormData(); fd.append('status', value);
  const res = await apiPostForm(`/${state.currentReqId}/status`, fd);
  if (res && res.success) {
    if (state.currentReq) state.currentReq.status = value;
    await loadRequests(true);
  } else { alert((res && res.message) || 'تعذّر تحديث الحالة.'); }
}

/* ===================== الزمن الحقيقي (Firebase + fallback) ===================== */
async function startRealtime(reqId) {
  stopRealtime();
  state.pollTimer = setInterval(() => { if (state.currentReqId === reqId) pollNewer(); }, RT_POLL_MS);
  if (!RT_FIREBASE) return;
  try {
    if (!window.__SVC_ADMIN_FB_CFG) {
      const tk = await apiGet('/firebase-token');
      if (tk && tk.success) window.__SVC_ADMIN_FB_CFG = tk.data;
    }
    const cfg = window.__SVC_ADMIN_FB_CFG;
    if (!cfg || !cfg.config || !cfg.config.apiKey) return;
    const [{ initializeApp, getApps }, { getAuth, signInWithCustomToken }, { getFirestore, doc, onSnapshot }] = await Promise.all([
      import(`${FB_SDK}/firebase-app.js`),
      import(`${FB_SDK}/firebase-auth.js`),
      import(`${FB_SDK}/firebase-firestore.js`),
    ]);
    const app = getApps().length ? getApps()[0] : initializeApp(cfg.config);
    const auth = getAuth(app);
    if (!auth.currentUser) { try { await signInWithCustomToken(auth, cfg.token); } catch (e) { return; } }
    const db = getFirestore(app);
    const unsub = onSnapshot(doc(db, 'conversations', String(reqId)),
      (snap) => { if (state.currentReqId === reqId && snap.exists()) pollNewer(); },
      (err) => {});
    state.fb = { app, unsub };
  } catch (e) {}
}
function stopRealtime() {
  if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null; }
  if (state.fb && typeof state.fb.unsub === 'function') { try { state.fb.unsub(); } catch (e) {} state.fb = null; }
}

/* ===================== الربط والتهيئة ===================== */
function bind() {
  const e = state.els;
  // ملاحظة: نستخدم ?. على كل عنصر حتى لا يُسقط أيّ عنصر مفقود تحميلَ القسم بالكامل
  // (Cannot read properties of null (reading 'addEventListener')).
  e.refresh?.addEventListener('click', () => loadRequests(true));
  e.back?.addEventListener('click', () => { e.root?.classList.remove('in-chat'); e.chat?.classList.remove('show'); if (e.empty) e.empty.style.display = ''; state.currentReqId = 0; stopRealtime(); });
  e.sendBtn?.addEventListener('click', sendMessage);
  e.input?.addEventListener('input', autosize);
  e.input?.addEventListener('keydown', (ev) => { if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); sendMessage(); } });
  e.attachBtn?.addEventListener('click', () => e.fileInput?.click());
  e.fileInput?.addEventListener('change', () => { Array.from(e.fileInput.files || []).forEach(f => state.files.push(f)); e.fileInput.value = ''; renderAttached(); });
  e.statusSel?.addEventListener('change', () => changeStatus(e.statusSel.value));
  e.search?.addEventListener('input', () => {
    clearTimeout(state.searchTimer);
    state.searchTimer = setTimeout(() => { state.q = (e.search.value || '').trim(); loadRequests(true); }, 350);
  });
}

function mount() {
  const root = $('svc-root'); if (!root) return;
  stopRealtime();
  state.status = ''; state.q = ''; state.currentReqId = 0; state.currentReq = null;
  state.requests = []; state.rendered = new Set(); state.oldestId = 0; state.latestId = 0; state.files = [];
  state.uid = Number(root.dataset.uid) || 0;
  state.els = {
    root, list: $('svc-list'), tabs: $('svc-tabs'), search: $('svc-search'), refresh: $('svc-refresh'),
    empty: $('svc-empty'), chat: $('svc-chat'), back: $('svc-back'),
    subj: $('svc-chead-subj'), meta: $('svc-chead-meta'), statusSel: $('svc-status-select'),
    msgs: $('svc-msgs'), composer: $('svc-composer'), attached: $('svc-attached'),
    attachBtn: $('svc-attach-btn'), fileInput: $('svc-file-input'), input: $('svc-input'), sendBtn: $('svc-send-btn'),
  };
  bind();

  // فتح طلب محدد إن مُرّر عبر الراوتر (مثلاً من الجدول)
  const payload = window.__pendingSectionPayload || null;
  const wantId = payload && (payload.request_id || payload.req_id);
  loadRequests(!!wantId).then(() => { if (wantId) openRequest(Number(wantId)); });
}

function unmount() { stopRealtime(); state.currentReqId = 0; }

export default { mount, unmount };
