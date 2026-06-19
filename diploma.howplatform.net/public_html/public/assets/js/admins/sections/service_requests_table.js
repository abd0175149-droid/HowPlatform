// public/assets/js/admin/modules/service_requests_table.js
// =======================================================
// Service Requests Table (Admin) — v1.8.3
// - متوافق مع الكنترولر: AdminServiceRequestsTableController (المحدّث)
// - يعتمد API تحت: /admins/api/service-requests-table/*
// - عمود "الأدمن المسؤول" للعرض فقط لكل الأدوار (لا select داخل الجدول)
// - زر التكليف يظهر فقط للأدمن (role=1)
// - بعد التكليف يتم تحديث الجدول والإحصائيات لإظهار الحالة الجديدة
// - إخفاء فلتر التكليف لغير الأدمن (وتثبيت filters.assigned='me' واجهياً)
// - باقي المنطق كما هو (بحث/ترقيم/مودالات/صلاحيات)
// =======================================================

'use strict';

/* ================== Debug Logger ================== */
const DBG = {
  on: true,
  info:  (...a) => DBG.on && console.log('[SR] INFO', ...a),
  warn:  (...a) => DBG.on && console.warn('[SR] WARN', ...a),
  err:   (...a) => DBG.on && console.error('[SR] ✖', ...a),
  trace: (...a) => DBG.on && console.debug('[SR] TRACE', ...a),
};

/* ================== حالة عامة ================== */
let EVENTS_BOUND = false;
let INITIALIZED  = false;

let admins = [];                   // {id, name?, email?, account_type_name?, label?}
let currentPage = 1;
let perPage = 10;
let totalRequests = 0;

const filters = {
  status: '',
  assigned: '', // '', 'me', 'unassigned' أو رقم أدمن
  q: ''
};

// API Root ثابت حسب الراوتر
const API = {
  ROOT:   '/admins/api/service-requests-table',
  LIST:   '/admins/api/service-requests-table/list',
  STATS:  '/admins/api/service-requests-table/stats',
  ADMINS: '/admins/api/service-requests-table/admins',
  STATUS: (id) => `/admins/api/service-requests-table/status/${encodeURIComponent(id)}`,
  ASSIGN: (id) => `/admins/api/service-requests-table/assign/${encodeURIComponent(id)}`
};

/* ================== DOM Helpers ================== */
const $id  = (id) => document.getElementById(id);
const $qs  = (sel, root = document) => root.querySelector(sel);
const $qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

/* ينتظر عنصر (مفيد مع SPA) */
function waitForElement(selector, timeoutMs = 10000) {
  return new Promise((resolve, reject) => {
    const found = document.querySelector(selector);
    if (found) return resolve(found);

    const obs = new MutationObserver(() => {
      const el = document.querySelector(selector);
      if (el) {
        obs.disconnect();
        resolve(el);
      }
    });

    obs.observe(document.body, { childList: true, subtree: true });

    setTimeout(() => {
      obs.disconnect();
      reject(new Error(`Timeout waiting for: ${selector}`));
    }, timeoutMs);
  });
}

/* ================== CSRF & Fetch Helpers ================== */
function getCsrfHeader() {
  // إن كنت تستخدم حماية CSRF ضع ميتا بهذين الاسمين:
  // <meta name="csrf_token_name" content="X-CSRF-TOKEN">
  // <meta name="csrf_token_value" content="...">
  const name  = document.querySelector('meta[name="csrf_token_name"]')?.getAttribute('content');
  const value = document.querySelector('meta[name="csrf_token_value"]')?.getAttribute('content');
  if (name && value) return { [name]: value };
  return null;
}

function buildJsonHeaders() {
  const headers = {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  };
  const csrf = getCsrfHeader();
  if (csrf) Object.assign(headers, csrf);
  return headers;
}

async function fetchJSON(url, options = {}) {
  const startedAt = performance.now();
  const opts = {
    credentials: 'same-origin',
    redirect: 'follow',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      ...(options.headers || {})
    },
    ...options
  };
  DBG.trace('HTTP →', { url, method: opts.method || 'GET', headers: opts.headers });

  let res;
  try {
    res = await fetch(url, opts);
  } catch (networkErr) {
    DBG.err('Network error', networkErr);
    throw new Error('NETWORK_ERROR');
  }

  let data = null;
  try {
    data = await res.json();
  } catch (e) {
    DBG.warn('Non-JSON response', { url, status: res.status });
  }

  const elapsed = Math.round(performance.now() - startedAt);
  if (!res.ok) {
    const msg = data?.message || `HTTP ${res.status}`;
    DBG.err(`fetch failed in ${elapsed}ms`, msg);
    const err = new Error(msg);
    err.status = res.status;
    err.response = data;
    throw err;
  }

  DBG.trace(`HTTP ✓ in ${elapsed}ms`, { url, status: res.status });
  return data;
}

/* ================== Formatting ================== */
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text ?? '';
  return div.innerHTML;
}

function truncateText(text, max = 100) {
  const s = String(text ?? '');
  if (s.length <= max) return s;
  return s.slice(0, max).trim() + '…';
}

function getStatusText(status) {
  const map = {
    pending: 'في الانتظار',
    in_progress: 'قيد المعالجة',
    resolved: 'تم الحل',
    closed: 'مغلق',
    cancelled: 'ملغي'
  };
  return map[status] || status || '';
}

function getPriorityText(priority) {
  const map = { low: 'منخفض', medium: 'متوسط', high: 'مرتفع', urgent: 'عاجل' };
  return map[priority] || 'متوسط';
}

function formatDate(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return '';
  const now = new Date();
  const diff = now - date;
  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  if (days === 0) return 'اليوم';
  if (days === 1) return 'أمس';
  if (days < 7) return `منذ ${days} أيام`;
  return date.toLocaleDateString('ar-SA');
}

function canAssign() {
  return Boolean(window.isAdmin ?? (Number(window.userRole || 0) === 1));
}

// Admin دائمًا، أو المكلّف فقط
function canOpenChatFor(requestId) {
  if (Number(window.userRole) === 1) return true;
  const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
  const assignedId = row?.getAttribute('data-assigned-id') || '';
  return String(assignedId) === String(window.currentUserId);
}

// ✅ فتح المحادثة: يدعم (payload) + جلسة + حدث مباشر إن كانت صفحة المحادثة مفتوحة
function openChat(requestId, evt) {
  // 1) صلاحيات
  if (!canOpenChatFor(requestId)) {
    showError('لا تملك صلاحية فتح هذه المحادثة.');
    return;
  }

  // 2) امنع أي تنقّل افتراضي وتعارض مع مفوض [data-section]
  if (evt) {
    try { evt.preventDefault(); } catch (_) {}
    try { evt.stopPropagation(); } catch (_) {}
    try { evt.stopImmediatePropagation?.(); } catch (_) {}
  }

  const id = String(requestId || '').trim();
  if (!id) return;

  // 3) عيّن القسم النشط للسايدبار
  try { localStorage.setItem('activeSection', 'service_chat'); } catch (_) {}

  // 4) إن كانت صفحة المحادثة موجودة أصلًا → افتح الخيط مباشرة
  if (document.getElementById('serviceChatPage')) {
    window.dispatchEvent(new CustomEvent('service-chat:open', { detail: { requestId: id } }));
    return;
  }

  // 5) خزّن الـid كاحتياط (لو تأخر تحميل الموديول)
  try { sessionStorage.setItem('chat:request_id', id); } catch (_) {}

  // 6) حمّل سكشن المحادثة عبر الراوتر مع payload
  if (typeof window.loadSection === 'function') {
    // تمرير payload بطريقتين لضمان الالتقاط من سكربت السكشن
    window.__pendingSectionPayload = { request_id: id };
    window.loadSection('service_chat', { request_id: id });
    return;
  }

  // 7) Fallback داخل الـSPA: انقر رابط القائمة اللي عليه data-section
  const link = document.querySelector('[data-section="service_chat"]');
  if (link) {
    link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    return;
  }

  // 8) آخر حل: بلّغ المستخدم بدل عمل redirect خارجي
  showError('تعذّر فتح صفحة المحادثة داخل اللوحة. جرّب تحديث الصفحة.');
}

/* ====== Role-based UI (إخفاء فلتر التكليف لغير الأدمن) ====== */
function applyRoleUI() {
  const isAdmin = Boolean(window.isAdmin ?? (Number(window.userRole || 0) === 1));
  const el = document.getElementById('assignmentFilter');
  if (!el) return;

  const wrap = el.closest('.filter-group') || el.parentElement;

  if (!isAdmin) {
    // إخفاء الفلتر + تثبيت الفلترة على "me" (واجهة فقط، والخادم يقيّد أيضاً)
    if (wrap) wrap.style.display = 'none'; else el.style.display = 'none';
    filters.assigned = 'me';
    try { el.value = 'me'; } catch (_) {}
  } else {
    if (wrap) wrap.style.display = ''; else el.style.display = '';
  }
}

/* ================== API Calls ================== */
async function loadAdmins(force = false) {
  if (!force && !canAssign()) { admins = []; return; }
  try {
    const res = await fetchJSON(API.ADMINS, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const list = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
    // الشكل المتوقع الآن: {id, name?, email?, account_type_name?, label?}
    admins = list.map(a => ({
      id: String(a.id),
      name: (a.name ?? '').trim(),
      email: (a.email ?? '').trim(),
      account_type_name: (a.account_type_name ?? '').trim(),
      label: (a.label ?? '').trim()
    }));
    DBG.info('admins loaded', admins);
  } catch (err) {
    DBG.err('loadAdmins', err.message || err);
    admins = [];
  }
}

async function loadStats() {
  try {
    const result = await fetchJSON(API.STATS, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (result?.success) {
      updateStats(result.data);
    } else {
      DBG.warn('stats not success', result);
    }
  } catch (err) {
    DBG.err('loadStats', err.message || err);
  }
}

async function loadRequests() {
  const tableBody = $id('requestsTableBody');
  if (!tableBody) return;

  tableBody.innerHTML = `
    <tr>
      <td colspan="9" class="loading-row">
        <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i>
        <span>جاري تحميل الطلبات...</span></div>
      </td>
    </tr>
  `;

  try {
    const params = new URLSearchParams({
      status:   filters.status,
      assigned: filters.assigned,
      q:        filters.q,
      page:     String(currentPage),
      per_page: String(perPage)
    });

    const result = await fetchJSON(`${API.LIST}?${params.toString()}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    if (!result?.success) {
      throw new Error(result?.message || 'فشل في تحميل الطلبات');
    }

    // تعريف المستخدم الحالي للواجهة
    if (result.current_user) {
      const cu = result.current_user;
      window.currentUserId = Number(cu.id ?? window.currentUserId ?? 0);
      window.userRole      = Number(cu.role ?? window.userRole ?? 0);
      window.isAdmin       = typeof cu.is_admin === 'boolean' ? cu.is_admin : (window.userRole === 1);
    }

    // بعد معرفة الدور من الخادم، طبّق إخفاء فلتر التكليف إن لزم
    applyRoleUI();

    const rows       = Array.isArray(result.data) ? result.data : [];
    const pagination = result.pagination || { page: 1, per_page: perPage, total: 0, pages: 1 };

    renderRequests(rows);

    if (window.isAdmin) {
      await loadAdmins(true);
      // لا توجد الآن قوائم inline داخل الجدول، الإبقاء لإمكانية استخدام لاحق أو لمودال فقط.
    }

    renderPagination({
      page:     Number(pagination.page     ?? 1),
      per_page: Number(pagination.per_page ?? perPage),
      total:    Number(pagination.total    ?? 0),
      pages:    Number(pagination.pages    ?? Math.max(1, Math.ceil((Number(pagination.total||0))/(Number(pagination.per_page||10)))))
    });

    updateStats(result.stats);

    const totalCountEl = $id('totalCount');
    if (totalCountEl) totalCountEl.textContent = String(pagination.total || 0);

  } catch (err) {
    DBG.err('loadRequests', err.message || err);
    showError(err.message || 'حدث خطأ في تحميل البيانات');
    renderRequests([]);
    renderPagination({ page: 1, per_page: perPage, total: 0, pages: 1 });
  }
}

async function assignRequest(requestId, adminId) {
  try {
    const result = await fetchJSON(API.ASSIGN(requestId), {
      method: 'POST',
      headers: buildJsonHeaders(),
      body: JSON.stringify({ admin_id: adminId })
    });

    if (result?.success) {
      showSuccess('تم تكليف الطلب بنجاح');

      // تحديث محلي بسيط (مع أن العمود للقراءة فقط)
      const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
      if (row) {
        row.setAttribute('data-assigned-id', String(adminId));
      }

      // 🔄 أعِد التحميل ليظهر تغيّر الحالة إلى in_progress (الخادم يضبطها)
      await loadRequests();
      await loadStats();
    } else {
      throw new Error(result?.message || 'فشل في التكليف');
    }
  } catch (err) {
    DBG.err('assignRequest', err.message || err);
    showError(err.message || 'حدث خطأ في التكليف');
  }
}

async function updateRequestStatus(requestId, status) {
  try {
    const result = await fetchJSON(API.STATUS(requestId), {
      method: 'POST',
      headers: buildJsonHeaders(),
      body: JSON.stringify({ status })
    });

    if (result?.success) {
      showSuccess('تم تحديث حالة الطلب بنجاح');
      await loadRequests();
      await loadStats();
    } else {
      throw new Error(result?.message || 'فشل في تحديث الحالة');
    }
  } catch (err) {
    DBG.err('updateRequestStatus', err.message || err);
    showError(err.message || 'حدث خطأ في تحديث الحالة');
  }
}

/* ================== Rendering ================== */
function renderRequests(requests) {
  const tableBody = $id('requestsTableBody');
  if (!tableBody) return;

  if (!requests?.length) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="9" class="empty-state">
          <i class="fas fa-inbox"></i>
          <h3>لا توجد طلبات</h3>
          <p>لم يتم العثور على طلبات بالمعايير المحددة</p>
        </td>
      </tr>
    `;
    return;
  }

  tableBody.innerHTML = requests.map((req, index) => {
    const rowIndex   = ((currentPage - 1) * perPage) + index + 1;
    const assignedId = req.assigned_to ?? '';
    const unread     = Number(req.unread_count || 0);

    // بيانات المستخدم قد تكون غير متوفرة (API خارجي)
    const requesterName  = (req.requester?.name || req.requester_name || 'غير معروف');
    const requesterEmail = (req.requester?.email || req.requester_email || '');

    return `
      <tr data-request-id="${req.id}" data-assigned-id="${assignedId}">
        <td>${rowIndex}</td>

        <td>
          <div class="user-info">
            <div class="user-name">${escapeHtml(requesterName)}</div>
            <div class="user-email">${escapeHtml(requesterEmail)}</div>
          </div>
        </td>

        <td>
          <div class="request-subject">
            ${escapeHtml(req.subject || '')}
            ${unread > 0 ? `<span class="badge-unread" title="رسائل غير مقروءة">${unread}</span>` : ''}
          </div>
        </td>

        <td>
          <div class="request-description" title="${escapeHtml(req.description || '')}">
            ${escapeHtml(truncateText(req.description || '', 100))}
          </div>
        </td>

        <td>
          <span class="course-name">${escapeHtml(req.course_name || 'غير محدد')}</span>
        </td>

        <td>${renderAdminCell(req)}</td>

        <td>${renderStatusAndPriority(req.status, req.priority)}</td>

        <td>${formatDate(req.created_at)}</td>

        <td>${renderRowActions(req)}</td>
      </tr>
    `;
  }).join('');
}

/* عمود الأدمن المسؤول — للعرض فقط للجميع */
function renderAdminCell(req) {
  // نحاول استخدام label الجاهز إن وُجد، ثم الاسم، ثم رقم المعرّف، وأخيرًا "غير مكلف"
  const labelFromApi = (req.assigned_admin_label || '').trim();
  const nameOnly     = (req.assigned_admin_name || '').trim();
  const accountType  = (req.assigned_admin_account_type || '').trim(); // اختياري إن وُفِّر
  const composed     = labelFromApi
    ? labelFromApi
    : (nameOnly ? (accountType ? `${nameOnly} — ${accountType}` : nameOnly) : (req.assigned_to ? `#${req.assigned_to}` : 'غير مكلف'));

  return `<span class="admin-name">${escapeHtml(composed)}</span>`;
}

function renderStatusAndPriority(status, priority) {
  const sText = getStatusText(status);
  const pText = getPriorityText(priority || 'medium');

  const statusHtml = `<span class="status-badge ${status}">${escapeHtml(sText)}</span>`;
  const prClass    = (priority || 'medium').toLowerCase();
  const prHtml     = `<span class="priority-badge ${prClass}" title="أولوية">${escapeHtml(pText)}</span>`;

  return `<div class="status-priority">${statusHtml} ${prHtml}</div>`;
}

function renderRowActions(req) {
  const statusBtn = canChangeStatus(req) ? `
    <button class="action-btn status-btn" title="تغيير الحالة" data-request-id="${req.id}">
      <i class="fas fa-edit"></i>
    </button>` : '';

  const chatBtn = `
    <button class="action-btn chat-btn" title="فتح المحادثة" data-request-id="${req.id}" data-section="service_chat">
      <i class="fas fa-comments"></i>
    </button>`;

  const assignBtn = canAssign() ? `
    <button class="action-btn assign-btn" title="تكليف" data-request-id="${req.id}" data-assigned="${req.assigned_to || ''}">
      <i class="fas fa-user-plus"></i>
    </button>` : '';

  return `<div class="row-actions">${statusBtn}${chatBtn}${assignBtn}</div>`;
}

function renderPagination(pagination) {
  if (!pagination) return;

  totalRequests = Number(pagination.total || 0);
  const totalPages = Math.max(1, Number(pagination.pages || 1));

  let container = $id('requestsPagination');
  if (!container) {
    container = document.createElement('div');
    container.id = 'requestsPagination';
    const tableContainer = $qs('.requests-table-container') || document.body;
    tableContainer.appendChild(container);
  }

  container.innerHTML = `
    <div class="pagination">
      <button class="pagination-btn ${currentPage === 1 ? 'disabled' : ''}" data-action="first" ${currentPage === 1 ? 'disabled' : ''}>
        <i class="fas fa-angle-double-right"></i>
      </button>
      <button class="pagination-btn ${currentPage === 1 ? 'disabled' : ''}" data-action="prev" ${currentPage === 1 ? 'disabled' : ''}>
        <i class="fas fa-angle-right"></i>
      </button>
      <div class="pagination-info">صفحة ${currentPage} من ${totalPages}</div>
      <button class="pagination-btn ${currentPage === totalPages ? 'disabled' : ''}" data-action="next" ${currentPage === totalPages ? 'disabled' : ''}>
        <i class="fas fa-angle-left"></i>
      </button>
      <button class="pagination-btn ${currentPage === totalPages ? 'disabled' : ''}" data-action="last" ${currentPage === totalPages ? 'disabled' : ''}>
        <i class="fas fa-angle-double-left"></i>
      </button>
    </div>
  `;
}

function buildAdminOptions(selectedId = '') {
  let options = '<option value="">اختر أدمن...</option>';
  admins.forEach(admin => {
    const selected = String(admin.id) === String(selectedId) ? 'selected' : '';
    const label    = (admin.label || admin.name || admin.email || `#${admin.id}`).trim();
    options += `<option value="${admin.id}" ${selected}>${escapeHtml(label)}</option>`;
  });
  return options;
}

// لا حاجة الآن لاستدعائها للجدول، لكنها تُستخدم إذا احتجنا نفس القوائم داخل المودال مستقبلاً
function populateAdminSelects() {
  if (!canAssign()) return;
  $qsa('.assign-inline').forEach(select => {
    const row = select.closest('tr');
    const assignedId = row?.getAttribute('data-assigned-id') || '';
    select.innerHTML = buildAdminOptions(assignedId);
    select.value = assignedId;

    const wrapper = select.closest('.assign-wrapper');
    if (wrapper) {
      const admin = admins.find(a => String(a.id) === String(assignedId));
      wrapper.dataset.email = admin?.email || '';
    }
  });
}

function updateStats(stats) {
  if (!stats) return;
  const all = stats.all_requests || {};

  const get = (k) => Number(all[k] ?? 0);
  const set = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = String(val ?? 0);
  };

  set('totalPending',     get('pending'));
  set('totalInProgress',  get('in_progress'));
  set('totalResolved',    get('resolved'));
  set('totalCancelled',   get('cancelled'));
  set('totalUnassigned',  Number(stats.unassigned ?? 0));

  const totalCountEl = document.getElementById('totalCount');
  if (totalCountEl) {
    const total = Number(all.total ?? (get('pending') + get('in_progress') + get('resolved') + get('closed') + get('cancelled')));
    totalCountEl.textContent = total;
  }
}

/* ================== Events ================== */
function bindEvents() {
  if (EVENTS_BOUND) return;
  EVENTS_BOUND = true;

  let debounceTimer;
  let lastQuery = '';

  // زر التحديث
  document.addEventListener('click', (e) => {
    const refreshBtn = e.target.closest('#refreshBtn');
    if (refreshBtn) {
      loadRequests();
      loadStats();
    }
  });

  // فلاتر
  document.addEventListener('change', (e) => {
    const t = e.target;

    if (t && t.id === 'statusFilter') {
      filters.status = t.value || '';
      currentPage = 1;
      loadRequests();
      return;
    }

    if (t && t.id === 'assignmentFilter') {
      if (!canAssign()) return; // غير أدمن؟ تجاهل الحدث بالكامل
      filters.assigned = t.value || '';
      currentPage = 1;
      loadRequests();
      return;
    }

    // ⚠️ لا توجد الآن assign-inline داخل الجدول. نُبقي الحارس للاستخدام المستقبلي إن عاد.
    if (t && t.classList?.contains('assign-inline')) {
      const requestId = t.dataset.requestId;
      const adminId   = t.value || '';
      if (!requestId) return;

      if (!adminId) {
        const row = t.closest('tr');
        const old = row?.getAttribute('data-assigned-id') || '';
        t.value = old;
        return;
      }
      assignRequest(requestId, adminId);
    }
  });

  // البحث مع debounce
  document.addEventListener('input', (e) => {
    const t = e.target;
    if (t && t.id === 'searchInput') {
      clearTimeout(debounceTimer);
      const next = (t.value || '').trim();
      debounceTimer = setTimeout(() => {
        if (next === lastQuery) return;
        lastQuery   = next;
        filters.q   = next;
        currentPage = 1;
        loadRequests();
      }, 600);
    }
  });

  // الترقيم
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.pagination-btn');
    if (!btn) return;

    const totalPages = Math.max(1, Math.ceil(totalRequests / perPage));
    switch (btn.dataset.action) {
      case 'first': currentPage = 1; break;
      case 'prev':  if (currentPage > 1) currentPage--; break;
      case 'next':  if (currentPage < totalPages) currentPage++; break;
      case 'last':  currentPage = totalPages; break;
    }
    loadRequests();
  });

  // إجراءات الصف
  document.addEventListener('click', (e) => {
    const statusBtn = e.target.closest('.status-btn');
    const chatBtn   = e.target.closest('.chat-btn');
    const assignBtn = e.target.closest('.assign-btn');

    if (statusBtn) {
      openStatusModal(statusBtn.dataset.requestId);
      return;
    }
    if (chatBtn) {
      openChat(chatBtn.dataset.requestId);
      return;
    }
    if (assignBtn) {
      openAssignModal(assignBtn.dataset.requestId, assignBtn.dataset.assigned);
      return;
    }
  });
}

/* ================== Modals (SweetAlert) ================== */
function openStatusModal(requestId) {
  showConfirm({
    title: 'تغيير حالة الطلب',
    input: 'select',
    inputOptions: {
      pending: 'في الانتظار',
      in_progress: 'قيد المعالجة',
      resolved: 'تم الحل',
      closed: 'مغلق',
      cancelled: 'ملغي'
    },
    inputPlaceholder: 'اختر الحالة الجديدة',
    confirmText: 'تحديث',
    cancelText: 'إلغاء',
    onConfirm: (status) => {
      if (status) updateRequestStatus(requestId, status);
    }
  });
}

async function openAssignModal(requestId, assignedId) {
  if (!canAssign()) return;
  if (!admins.length) await loadAdmins(true);

  const options = { '': 'اختر أدمن...' };
  admins.forEach(a => {
    const label = (a.label || a.name || a.email || (`#${a.id}`)).trim();
    options[a.id] = label;
  });

  showConfirm({
    title: 'تكليف الطلب',
    input: 'select',
    inputOptions: options,
    inputValue: assignedId || '',
    confirmText: 'تكليف',
    cancelText: 'إلغاء',
    onConfirm: (adminId) => {
      if (adminId) assignRequest(requestId, adminId);
    }
  });
}

/* ================== Alerts - استخدام الدوال الموحدة من alerts.js ================== */
// showError, showSuccess متاحة من alerts.js
function showConfirm({ title, text, input, inputOptions, inputValue, inputPlaceholder, confirmText, cancelText, onConfirm }) {
  if (typeof Swal !== 'undefined') {
    const config = {
      title,
      confirmButtonText: confirmText || 'تأكيد',
      cancelButtonText:  cancelText  || 'إلغاء',
      showCancelButton: true,
      preConfirm: (value) => { if (onConfirm) onConfirm(value); }
    };
    if (text) config.text = text;
    if (input) {
      config.input = input;
      if (inputOptions)       config.inputOptions = inputOptions;
      if (inputValue != null) config.inputValue = inputValue;
      if (inputPlaceholder)   config.inputPlaceholder = inputPlaceholder;
    }
    Swal.fire(config);
  } else {
    const confirmed = confirm(`${title}\n${text || ''}`);
    if (confirmed && onConfirm) onConfirm();
  }
}

// Teacher/Trial: لا يغيّروا الحالة لو الطلب ملغي. Admin دائمًا مسموح.
function canChangeStatus(request) {
  if (Number(window.userRole) === 1) return true;
  if (!request) return false;
  return request.status !== 'cancelled';
}

/* ================== Styles Injection ================== */
function injectStyles() {
  if ($id('service-table-styles')) return;

  const style = document.createElement('style');
  style.id = 'service-table-styles';
  style.textContent = `
    .requests-table-container { overflow: visible; }
    .requests-table { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; }
    .requests-table th, .requests-table td {
      white-space: normal; word-break: break-word; overflow-wrap: anywhere;
      vertical-align: top; line-height: 1.5; height: auto; padding: 10px 12px;
    }
    .row-actions { display: flex; gap: 8px; align-items: center; flex-wrap: nowrap; }
    .action-btn { width: 36px; height: 36px; border-radius: 8px; border: 1px solid #e5e7eb;
      background: #fff; display: inline-flex; align-items: center; justify-content: center;
      cursor: pointer; transition: .2s ease; color: #0f172a; }
    .action-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

    .pagination { display: flex; justify-content: center; align-items: center; margin-top: 30px; gap: 8px; }
    .pagination-btn { width: 40px; height: 40px; border-radius: 8px; display: flex;
      align-items: center; justify-content: center; background: #fff; color: var(--primary);
      border: 1px solid #ddd; cursor: pointer; transition: var(--transition); font-weight: 600; }
    .pagination-btn:hover:not(.disabled) { background: var(--primary); color: #fff; border-color: var(--primary); }
    .pagination-btn.disabled { opacity: .5; cursor: not-allowed; }
    .pagination-info { margin: 0 15px; color: var(--text-light); font-weight: 500; }

    .empty-state { text-align: center; padding: 40px; color: var(--text-light); }
    .empty-state i { font-size: 48px; margin-bottom: 15px; color: var(--primary-light); opacity: 0.5; }
    .empty-state h3 { margin: 0 0 10px; color: var(--primary-dark); }

    .status-badge { padding: 4px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
    .status-badge.pending     { background: #fef3c7; color: #92400e; }
    .status-badge.in_progress { background: #dbeafe; color: #1e40af; }
    .status-badge.resolved    { background: #d1fae5; color: #065f46; }
    .status-badge.closed      { background: #f3f4f6; color: #374151; }
    .status-badge.cancelled   { background: #fee2e2; color: #991b1b; }

    .priority-badge { padding: 3px 10px; border-radius: 999px; font-size: .75rem; font-weight: 700; margin-inline-start: 6px; }
    .priority-badge.low    { background: #e5e7eb; color: #374151; }
    .priority-badge.medium { background: #e0e7ff; color: #4338ca; }
    .priority-badge.high   { background: #fde68a; color: #92400e; }
    .priority-badge.urgent { background: #fecaca; color: #991b1b; }

    .status-priority { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }

    .badge-unread {
      display: inline-block; margin-right: 6px; padding: 2px 8px; border-radius: 999px;
      font-size: 12px; background: #ef4444; color: #fff; vertical-align: middle;
    }
  `;
  document.head.appendChild(style);
}

/* ================== Entry ================== */
export async function init() {
  if (INITIALIZED) {
    await loadAdmins();
    await loadRequests();
    await loadStats();
    return;
  }
  INITIALIZED = true;

  DBG.info('ServiceRequestsTable init', { version: '1.8.3', api: API.ROOT });

  try {
    await waitForElement('#requestsTable');
    await waitForElement('#requestsTableBody');
  } catch (err) {
    DBG.warn('init wait elements timeout', err?.message || err);
    return;
  }

  // إخفاء مبدئي لفلتر التكليف حتى نعرف الدور (منع وميض بصري)
  (() => {
    const el = document.getElementById('assignmentFilter');
    if (!el) return;
    const wrap = el.closest('.filter-group') || el.parentElement;
    if (wrap) wrap.style.display = 'none'; else el.style.display = 'none';
  })();

  injectStyles();
  bindEvents();

  await loadAdmins();
  await loadRequests();
  await loadStats();

  DBG.info('init done');
}
