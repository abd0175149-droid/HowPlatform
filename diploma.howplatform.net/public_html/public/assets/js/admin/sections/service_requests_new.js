/* public/assets/js/admins/sections/service_requests_new.js */
/* نظام طلبات الخدمة الجديد - يجمع بين الجدول والدردشة */

const API_BASE = '/admins/service-requests-new';

// الحالة العامة
let currentRequestId = null;
let currentUserId = null;
let messagesCheckInterval = null;
let lastMessageId = 0;
let currentFilter = 'all';
let currentStatus = '';
let adminsList = [];
let requestsList = [];
const renderedMessageIds = new Set(); // Set لتتبع الرسائل المعروضة

// ======================= Helpers =======================

function buildJsonHeaders() {
  return {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  };
}

async function fetchJSON(url, options = {}) {
  try {
    const res = await fetch(url, {
      ...options,
      headers: { ...buildJsonHeaders(), ...(options.headers || {}) },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  } catch (err) {
    console.error('fetchJSON error:', err);
    throw err;
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  try {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr; // إذا كان التاريخ غير صالح، أرجع النص الأصلي
    const now = new Date();
    const diff = now - d;
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'الآن';
    if (mins < 60) return `منذ ${mins} دقيقة`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `منذ ${hours} ساعة`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `منذ ${days} يوم`;
    // استخدام تنسيق آمن للتاريخ
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  } catch (err) {
    return dateStr;
  }
}

function getStatusText(status) {
  const map = {
    pending: 'في الانتظار',
    in_progress: 'قيد المعالجة',
    resolved: 'تم الحل',
    closed: 'مغلق',
    cancelled: 'ملغي',
  };
  return map[status] || status;
}

function getStatusClass(status) {
  return `status-${status}`;
}

// ======================= تحميل قائمة الطلبات =======================

async function loadRequestsList() {
  const listEl = document.getElementById('requestsList');
  if (!listEl) return;

  try {
    listEl.innerHTML = '<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i><p>جاري تحميل الطلبات...</p></div>';

    const params = new URLSearchParams();
    if (currentFilter === 'me') params.append('assigned', 'me');
    else if (currentFilter === 'unassigned') params.append('assigned', 'unassigned');
    if (currentStatus) params.append('status', currentStatus);

    const data = await fetchJSON(`${API_BASE}/list?${params}`);
    
    if (!data.success) {
      throw new Error(data.message || 'فشل تحميل الطلبات');
    }

    requestsList = data.data || [];
    renderRequestsList(requestsList);
    updateStats(data.stats || {});
    renderTable(requestsList);
  } catch (err) {
    console.error('loadRequestsList error:', err);
    listEl.innerHTML = `<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>${err.message || 'حدث خطأ'}</p></div>`;
  }
}

function renderRequestsList(requests) {
  const listEl = document.getElementById('requestsList');
  if (!listEl) return;

  if (requests.length === 0) {
    listEl.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><p>لا توجد طلبات</p></div>';
    return;
  }

  listEl.innerHTML = requests.map(req => {
    const isActive = currentRequestId === req.id;
    const unread = req.unread_count || 0;
    const lastMsg = req.last_message;
    const preview = lastMsg ? lastMsg.preview : 'لا توجد رسائل';
    
    return `
      <div class="request-item ${isActive ? 'active' : ''}" data-request-id="${req.id}">
        <div class="request-title">
          <span class="status-indicator ${getStatusClass(req.status)}"></span>
          ${req.subject || 'بدون عنوان'}
          ${unread > 0 ? `<span class="unread-badge">${unread}</span>` : ''}
        </div>
        <div class="request-preview">${preview}</div>
        <div class="request-meta">
          <span class="requester-name">${req.requester_name || 'غير معروف'}</span>
          <span class="request-date">${formatDate(req.last_activity_at || req.created_at)}</span>
        </div>
      </div>
    `;
  }).join('');

  // ربط الأحداث
  listEl.querySelectorAll('.request-item').forEach(item => {
    item.addEventListener('click', () => {
      const requestId = parseInt(item.dataset.requestId);
      selectRequest(requestId);
    });
  });
}

function renderTable(requests) {
  const tbody = document.getElementById('requestsTableBody');
  if (!tbody) return;

  if (requests.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><i class="fas fa-inbox"></i><p>لا توجد طلبات لعرضها</p></td></tr>';
    return;
  }

  tbody.innerHTML = requests.map(req => {
    const statusText = getStatusText(req.status);
    const assignedName = req.assigned_admin_name || 'غير مكلف';
    
    return `
      <tr data-request-id="${req.id}">
        <td>#${req.id}</td>
        <td>${req.subject || 'بدون عنوان'}</td>
        <td>${req.requester_name || 'غير معروف'}</td>
        <td><span class="status-badge ${getStatusClass(req.status)}">${statusText}</span></td>
        <td>${assignedName}</td>
        <td>${formatDate(req.created_at)}</td>
        <td>
          <button class="btn-sm btn-primary" onclick="openChatForRequest(${req.id})">
            <i class="fas fa-comments"></i> دردشة
          </button>
        </td>
      </tr>
    `;
  }).join('');
}

function updateStats(stats) {
  const counts = {
    all: stats.all?.total || 0,
    me: stats.assigned_to_me?.total || 0,
    pending: stats.all?.pending || 0,
    in_progress: stats.all?.in_progress || 0,
    unassigned: stats.unassigned || 0,
  };

  const countAllEl = document.getElementById('countAll');
  if (countAllEl) countAllEl.textContent = counts.all;
  
  const countMeEl = document.getElementById('countMe');
  if (countMeEl) countMeEl.textContent = counts.me;
  
  const countPendingEl = document.getElementById('countPending');
  if (countPendingEl) countPendingEl.textContent = counts.pending;
  
  const countProgressEl = document.getElementById('countProgress');
  if (countProgressEl) countProgressEl.textContent = counts.in_progress;
  
  const countUnassignedEl = document.getElementById('countUnassigned');
  if (countUnassignedEl) countUnassignedEl.textContent = counts.unassigned;
}

// ======================= اختيار طلب =======================

async function selectRequest(requestId) {
  if (currentRequestId === requestId) return;

  currentRequestId = requestId;
  lastMessageId = 0;

  // تحديث الواجهة
  document.querySelectorAll('.request-item').forEach(item => {
    item.classList.toggle('active', parseInt(item.dataset.requestId) === requestId);
  });

  // فتح عرض الدردشة
  const tableView = document.getElementById('tableView');
  if (tableView) tableView.style.setProperty('display', 'none');
  
  const chatView = document.getElementById('chatView');
  if (chatView) chatView.style.setProperty('display', 'flex');
  
  document.querySelectorAll('.view-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.view === 'chat');
  });

  await loadRequestDetails(requestId);
  await loadMessages(requestId);
  startMessagesCheck();
}

async function loadRequestDetails(requestId) {
  try {
    const data = await fetchJSON(`${API_BASE}/details/${requestId}`);
    if (!data.success) throw new Error(data.message);

    const req = data.data.request;
    
    const chatRequestTitle = document.getElementById('chatRequestTitle');
    if (chatRequestTitle) chatRequestTitle.textContent = req.subject || 'بدون عنوان';
    
    const chatRequestStatus = document.getElementById('chatRequestStatus');
    if (chatRequestStatus) {
      chatRequestStatus.textContent = getStatusText(req.status);
      chatRequestStatus.className = `status-badge ${getStatusClass(req.status)}`;
    }
    
    const chatRequestDate = document.getElementById('chatRequestDate');
    if (chatRequestDate) chatRequestDate.textContent = formatDate(req.created_at);
    
    // تحديث معلومات طالب الخدمة
    const requesterName = document.getElementById('requesterName');
    if (requesterName) requesterName.textContent = req.requester_name || 'غير معروف';
    
    const requesterEmail = document.getElementById('requesterEmail');
    if (requesterEmail) {
      requesterEmail.textContent = req.requester_email ? `📧 ${req.requester_email}` : '';
      requesterEmail.style.display = req.requester_email ? 'block' : 'none';
    }
    
    const requesterPhone = document.getElementById('requesterPhone');
    if (requesterPhone) {
      requesterPhone.textContent = req.requester_phone ? `📱 ${req.requester_phone}` : '';
      requesterPhone.style.display = req.requester_phone ? 'block' : 'none';
    }
    
    // تحديث المعلومات الأكاديمية
    const requesterUniversity = document.getElementById('requesterUniversity');
    if (requesterUniversity) {
      requesterUniversity.textContent = req.requester_university ? `🏛️ الجامعة: ${req.requester_university}` : '';
      requesterUniversity.style.display = req.requester_university ? 'block' : 'none';
    }
    
    const requesterSpecialty = document.getElementById('requesterSpecialty');
    if (requesterSpecialty) {
      requesterSpecialty.textContent = req.requester_specialty ? `📚 التخصص: ${req.requester_specialty}` : '';
      requesterSpecialty.style.display = req.requester_specialty ? 'block' : 'none';
    }
    
    const requesterAcademicYear = document.getElementById('requesterAcademicYear');
    if (requesterAcademicYear) {
      requesterAcademicYear.textContent = req.requester_academic_year ? `📅 السنة الأكاديمية: ${req.requester_academic_year}` : '';
      requesterAcademicYear.style.display = req.requester_academic_year ? 'block' : 'none';
    }
    
    const requestDescription = document.getElementById('requestDescription');
    if (requestDescription) requestDescription.textContent = req.description || 'لا يوجد وصف';

    // تحديث select الحالة
    const statusSelect = document.getElementById('quickStatusChange');
    if (statusSelect) statusSelect.value = req.status;

    // تحديث select التكليف
    await updateAssignSelect(req);

    const chatPlaceholder = document.getElementById('chatPlaceholder');
    if (chatPlaceholder) chatPlaceholder.style.setProperty('display', 'none');
    
    const chatContainer = document.getElementById('chatContainer');
    if (chatContainer) chatContainer.style.setProperty('display', 'flex');
  } catch (err) {
    console.error('loadRequestDetails error:', err);
    showError(err.message || 'فشل تحميل التفاصيل');
  }
}

async function updateAssignSelect(request) {
  const assignSelect = document.getElementById('assignSelect');
  const assignBtn = document.getElementById('assignBtn');
  if (!assignSelect) return;

  if (adminsList.length === 0) {
    try {
      const data = await fetchJSON(`${API_BASE}/admins`);
      if (data.success) adminsList = data.data || [];
    } catch (err) {
      console.error('load admins error:', err);
    }
  }

  assignSelect.innerHTML = '<option value="">اختر أدمن...</option>' +
    adminsList.map(admin => {
      const isSelected = parseInt(request.assigned_to) === parseInt(admin.id);
      return `<option value="${admin.id}" ${isSelected ? 'selected' : ''}>${admin.name} (${admin.email})</option>`;
    }).join('');

  if (assignBtn) {
    const currentValue = parseInt(assignSelect.value) || 0;
    const assignedTo = parseInt(request.assigned_to) || 0;
    const hasValue = currentValue > 0;
    const isDifferent = hasValue && currentValue !== assignedTo;
    assignBtn.style.display = isDifferent ? 'inline-block' : 'none';
  }
}

// ======================= الرسائل =======================

async function loadMessages(requestId, isInitial = true) {
  const messagesArea = document.getElementById('messagesArea');
  if (!messagesArea) {
    console.error('loadMessages: messagesArea not found');
    return;
  }

  try {
    console.log('loadMessages: Fetching messages for request', requestId);
    const data = await fetchJSON(`${API_BASE}/details/${requestId}`);
    console.log('loadMessages: Response data', data);
    
    if (!data || !data.success) {
      const errorMsg = data?.message || 'فشل تحميل الرسائل';
      console.error('loadMessages: API error', errorMsg);
      throw new Error(errorMsg);
    }

    if (!data.data) {
      console.error('loadMessages: No data in response', data);
      throw new Error('لا توجد بيانات في الاستجابة');
    }

    const messages = Array.isArray(data.data.messages) ? data.data.messages : [];
    console.log('loadMessages: Found', messages.length, 'messages', messages);
    
    if (isInitial) {
      messagesArea.innerHTML = '';
      lastMessageId = 0;
      renderedMessageIds.clear(); // مسح Set الرسائل المعروضة
    }

    if (messages.length === 0) {
      if (isInitial) {
        messagesArea.innerHTML = '<div class="empty-messages"><i class="fas fa-comments"></i><p>لا توجد رسائل بعد</p></div>';
      }
      console.log('loadMessages: No messages found');
      return;
    }

    messages.forEach(msg => {
      if (!msg || !msg.id) {
        console.warn('loadMessages: Invalid message', msg);
        return;
      }
      if (renderedMessageIds.has(msg.id)) {
        console.log('loadMessages: Message already rendered', msg.id);
        return;
      }
      console.log('loadMessages: Rendering message', msg.id, msg);
      renderMessage(msg);
      renderedMessageIds.add(msg.id);
      if (msg.id > lastMessageId) lastMessageId = msg.id;
    });

    scrollToBottom();
    markMessagesAsRead(requestId);
    console.log('loadMessages: Completed successfully');
  } catch (err) {
    console.error('loadMessages error:', err);
    if (isInitial) {
      messagesArea.innerHTML = `<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>فشل تحميل الرسائل: ${err.message}</p></div>`;
    }
  }
}

function renderMessage(msg) {
  const messagesArea = document.getElementById('messagesArea');
  if (!messagesArea) {
    console.error('renderMessage: messagesArea not found');
    return;
  }

  if (!msg || !msg.id) {
    console.error('renderMessage: Invalid message', msg);
    return;
  }

  console.log('renderMessage: Rendering message', msg.id, msg);

  const isSent = parseInt(msg.sender_id) === parseInt(currentUserId);
  const senderName = msg.sender_name || 'مستخدم';
  const messageTime = formatDate(msg.created_at);

  let content = '';
  if (msg.message_type === 'voice') {
    const fileId = msg.file_id || (msg.files && msg.files.length > 0 ? msg.files[0].file_id : '');
    content = `<div class="voice-message">
      <audio controls>
        <source src="${API_BASE}/file/${fileId}" type="audio/webm">
      </audio>
    </div>`;
  } else if (msg.message_type === 'file' && msg.files && msg.files.length > 0) {
    content = `<div class="message-files">
      ${msg.files.map(file => `
        <a href="${API_BASE}/file/${file.file_id || file.id}" class="file-card" target="_blank">
          <div class="thumb icon-thumb">
            <i class="fas fa-file"></i>
          </div>
          <div class="meta">
            <div class="name">${file.file_name || 'ملف'}</div>
            <div class="sub">${file.file_size_bytes ? (file.file_size_bytes / 1024).toFixed(1) + ' KB' : ''}</div>
          </div>
        </a>
      `).join('')}
    </div>`;
  } else {
    content = `<div class="message-text">${escapeHtml(msg.message || '')}</div>`;
  }

  const messageEl = document.createElement('div');
  messageEl.className = `message ${isSent ? 'sent' : 'received'}`;
  messageEl.dataset.messageId = msg.id;
  messageEl.innerHTML = `
    <div class="message-content">
      <div class="message-header">
        <span class="sender-name">${escapeHtml(senderName)}</span>
        <span class="message-time">${messageTime}</span>
      </div>
      ${content}
    </div>
  `;

  messagesArea.appendChild(messageEl);
  console.log('renderMessage: Message rendered successfully', msg.id);
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

async function sendMessage() {
  const input = document.getElementById('messageInput');
  const message = input?.value.trim();
  const fileInput = document.getElementById('chatFileInput');

  if (!message && (!fileInput || !fileInput.files.length)) {
    showError('يرجى كتابة رسالة أو إرفاق ملف');
    return;
  }

  if (!currentRequestId) {
    showError('يرجى اختيار طلب أولاً');
    return;
  }

  try {
    const formData = new FormData();
    if (message) formData.append('message', message);
    if (fileInput && fileInput.files.length) {
      Array.from(fileInput.files).forEach(file => {
        formData.append('files[]', file);
      });
    }

    const res = await fetch(`${API_BASE}/message/${currentRequestId}`, {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });

    const data = await res.json();
    if (!data.success) throw new Error(data.message);

    if (input) input.value = '';
    if (fileInput) fileInput.value = '';

    if (data.data) {
      renderMessage(data.data);
      lastMessageId = Math.max(lastMessageId, data.data.id);
      scrollToBottom();
    }

    await loadRequestsList();
  } catch (err) {
    console.error('sendMessage error:', err);
    showError(err.message || 'فشل إرسال الرسالة');
  }
}

async function markMessagesAsRead(requestId) {
  try {
    await fetchJSON(`${API_BASE}/mark-read/${requestId}`, { method: 'POST' });
    await loadRequestsList();
  } catch (err) {
    console.error('markMessagesAsRead error:', err);
  }
}

// ======================= فحص الرسائل الجديدة (Polling) =======================

function startMessagesCheck() {
  stopMessagesCheck();
  if (!currentRequestId) return;

  messagesCheckInterval = setInterval(async () => {
    if (!currentRequestId || document.hidden) return;

    try {
      const params = new URLSearchParams();
      if (lastMessageId > 0) params.append('last_message_id', lastMessageId);

      const data = await fetchJSON(`${API_BASE}/new-messages/${currentRequestId}?${params}`);
      if (data.success && data.data && data.data.length > 0) {
        data.data.forEach(msg => {
          if (!renderedMessageIds.has(msg.id)) {
            renderMessage(msg);
            renderedMessageIds.add(msg.id);
            if (msg.id > lastMessageId) lastMessageId = msg.id;
          }
        });
        scrollToBottom();
        markMessagesAsRead(currentRequestId);
        await loadRequestsList();
      }
    } catch (err) {
      console.error('check new messages error:', err);
    }
  }, 3000); // كل 3 ثوان
}

function stopMessagesCheck() {
  if (messagesCheckInterval) {
    clearInterval(messagesCheckInterval);
    messagesCheckInterval = null;
  }
}

function scrollToBottom() {
  const messagesArea = document.getElementById('messagesArea');
  if (messagesArea) {
    setTimeout(() => {
      messagesArea.scrollTop = messagesArea.scrollHeight;
    }, 100);
  }
}

// ======================= تحديث الحالة والتكليف =======================

async function updateStatus(requestId, status) {
  try {
    const data = await fetchJSON(`${API_BASE}/status/${requestId}`, {
      method: 'POST',
      body: JSON.stringify({ status }),
    });

    if (!data.success) throw new Error(data.message);
    showSuccess('تم تحديث الحالة بنجاح');
    await loadRequestDetails(requestId);
    await loadRequestsList();
  } catch (err) {
    console.error('updateStatus error:', err);
    showError(err.message || 'فشل تحديث الحالة');
  }
}

async function assignRequest(requestId, adminId) {
  try {
    const data = await fetchJSON(`${API_BASE}/assign/${requestId}`, {
      method: 'POST',
      body: JSON.stringify({ admin_id: adminId }),
    });

    if (!data.success) throw new Error(data.message);
    showSuccess('تم تكليف الطلب بنجاح');
    await loadRequestDetails(requestId);
    await loadRequestsList();
  } catch (err) {
    console.error('assignRequest error:', err);
    showError(err.message || 'فشل التكليف');
  }
}

// ======================= الأحداث =======================

function setupEventListeners() {
  // الفلاتر
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentFilter = btn.dataset.filter || 'all';
      loadRequestsList();
    });
  });

  // تبديل العرض
  document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const view = btn.dataset.view;
      document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      if (view === 'table') {
        const tableView = document.getElementById('tableView');
        if (tableView) {
          tableView.style.setProperty('display', 'flex');
          tableView.style.setProperty('flex-direction', 'column');
        }
        
        const chatView = document.getElementById('chatView');
        if (chatView) chatView.style.setProperty('display', 'none');
        
        stopMessagesCheck();
      } else {
        const tableView = document.getElementById('tableView');
        if (tableView) tableView.style.setProperty('display', 'none');
        
        const chatView = document.getElementById('chatView');
        if (chatView) {
          chatView.style.setProperty('display', 'flex');
          chatView.style.setProperty('flex-direction', 'column');
        }
        
        if (currentRequestId) startMessagesCheck();
      }
    });
  });

  // إرسال رسالة
  const sendBtn = document.getElementById('sendBtn');
  const messageInput = document.getElementById('messageInput');
  if (sendBtn) sendBtn.addEventListener('click', sendMessage);
  if (messageInput) {
    messageInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
  }

  // تحديث الحالة
  const statusSelect = document.getElementById('quickStatusChange');
  if (statusSelect) {
    statusSelect.addEventListener('change', (e) => {
      if (e.target.value && currentRequestId) {
        updateStatus(currentRequestId, e.target.value);
      }
    });
  }

  // التكليف
  const assignSelect = document.getElementById('assignSelect');
  const assignBtn = document.getElementById('assignBtn');
  if (assignSelect) {
    assignSelect.addEventListener('change', () => {
      if (assignBtn) {
        assignBtn.style.display = assignSelect.value ? 'inline-block' : 'none';
      }
    });
  }
  if (assignBtn) {
    assignBtn.addEventListener('click', () => {
      if (assignSelect.value && currentRequestId) {
        assignRequest(currentRequestId, parseInt(assignSelect.value));
      }
    });
  }

  // إرفاق ملف
  const attachBtn = document.getElementById('attachBtn');
  const fileInput = document.getElementById('chatFileInput');
  if (attachBtn && fileInput) {
    attachBtn.addEventListener('click', () => fileInput.click());
  }

  // تحديث
  const refreshBtn = document.getElementById('refreshAllBtn');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadRequestsList();
      if (currentRequestId) {
        loadRequestDetails(currentRequestId);
        loadMessages(currentRequestId);
      }
    });
  }

  // إخفاء/إظهار التفاصيل
  const toggleDetailsBtn = document.getElementById('toggleDetailsBtn');
  if (toggleDetailsBtn) {
    toggleDetailsBtn.addEventListener('click', () => {
      const details = document.getElementById('requestDetails');
      if (details) {
        details.style.display = details.style.display === 'none' ? 'block' : 'none';
      }
    });
  }

  // فلتر الجدول - الحالة
  const tableStatusFilter = document.getElementById('tableStatusFilter');
  if (tableStatusFilter) {
    tableStatusFilter.addEventListener('change', (e) => {
      currentStatus = e.target.value;
      loadRequestsList();
    });
  }

  // فلتر الجدول - التكليف
  const tableAssignFilter = document.getElementById('tableAssignFilter');
  if (tableAssignFilter) {
    tableAssignFilter.addEventListener('change', (e) => {
      currentFilter = e.target.value;
      loadRequestsList();
    });
  }
}

// دالة مساعدة للفتح من الجدول
window.openChatForRequest = function(requestId) {
  selectRequest(requestId);
  document.querySelectorAll('.view-btn').forEach(btn => {
    if (btn.dataset.view === 'chat') btn.click();
  });
};

// ======================= تحميل CSS =======================

function ensureStylesheet() {
  const linkId = 'service-requests-new-css';
  if (document.getElementById(linkId)) return;

  const link = document.createElement('link');
  link.id = linkId;
  link.rel = 'stylesheet';
  link.href = '/assets/css/admin/service_requests_new.css';
  document.head.appendChild(link);
}

// ======================= Helpers للتنبيهات =======================

// استخدام الدوال الموحدة من alerts.js (showSuccess, showError)

// ======================= التهيئة =======================

export async function init() {
  console.log('Service Requests New - Initializing');

  try {
    // تحميل CSS
    ensureStylesheet();

    // الحصول على معرف المستخدم
    const container = document.querySelector('.service-requests-new-container');
    if (container) {
      currentUserId = parseInt(container.dataset.currentUserId || '0');
      if (!currentUserId) {
        console.warn('Could not get user ID from dataset, trying to get from session...');
        // محاولة الحصول من session عبر API بسيط
        try {
          const res = await fetch('/admins/api/user-info', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          });
          if (res.ok) {
            const data = await res.json();
            currentUserId = parseInt(data.user_id || data.id || '0');
          }
        } catch (err) {
          console.warn('Could not fetch user info:', err);
        }
      }
    }

    await loadRequestsList();
    setupEventListeners();

    // مراقبة رؤية الصفحة
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        stopMessagesCheck();
      } else if (currentRequestId) {
        startMessagesCheck();
      }
    });

    // تنظيف عند الخروج
    window.addEventListener('beforeunload', () => {
      stopMessagesCheck();
    });

    console.log('Service Requests New - Initialized');
  } catch (err) {
    console.error('Init error:', err);
    showError('حدث خطأ في تحميل الصفحة');
  }
}

