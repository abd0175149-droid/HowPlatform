<!-- app/Views/student/pages/service.php -->
<div class="service-requests-container">
    <!-- Header -->
    <div class="service-header">
        <div class="header-title">
            <h1><i class="fas fa-headset"></i> طلبات الخدمة المتخصصة</h1>
            <p>تابع طلباتك وتواصل مع فريق الدعم</p>
        </div>
        <button class="btn-new-request" onclick="ServiceRequestModal.open()">
            <i class="fas fa-plus-circle"></i>
            <span>طلب جديد</span>
        </button>
    </div>

    <!-- Main Content -->
    <div class="service-content"> 
        <div class="requests-sidebar">
            <div class="sidebar-header">
                <h3>طلباتي</h3>
                <div class="filter-tabs">
                    <button class="tab-btn active" data-filter="all">
                        الكل <span class="count" id="countAll">0</span>
                    </button>
                    <button class="tab-btn" data-filter="pending">
                        قيد الانتظار <span class="count" id="countPending">0</span>
                    </button>
                    <button class="tab-btn" data-filter="in_progress">
                        قيد المعالجة <span class="count" id="countProgress">0</span>
                    </button>
                    <button class="tab-btn" data-filter="resolved">
                        تم الحل <span class="count" id="countResolved">0</span>
                    </button>
                    <button class="tab-btn" data-filter="cancelled">
                        ملغي <span class="count" id="countCancelled">0</span>
                    </button>
                </div>
            </div>
            
            <div class="requests-list" id="requestsList">
                <!-- سيتم تحميل الطلبات هنا -->
                <div class="loading-placeholder">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>جاري تحميل الطلبات...</p>
                </div>
            </div>
        </div>
        <div class="chat-area">
            <div class="chat-placeholder" id="chatPlaceholder">
                <i class="fas fa-comments"></i>
                <h3>اختر طلباً لعرض المحادثة</h3>
                <p>اختر أحد الطلبات من القائمة الجانبية لبدء المحادثة</p>
            </div>
            <div class="chat-container" id="chatContainer" style="display: none;">
                <div class="chat-header">
                    <div class="request-info">
                        <h3 id="chatRequestTitle">عنوان الطلب</h3>
                        <div class="request-meta">
                            <span class="status-badge" id="chatRequestStatus">pending</span>
                            <span class="date" id="chatRequestDate">التاريخ</span>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <button class="btn-icon" onclick="toggleRequestDetails()" title="التفاصيل">
                            <i class="fas fa-info-circle"></i>
                        </button>
                        <button class="btn-icon btn-cancel-request" onclick="cancelRequest()" title="إلغاء الطلب" id="cancelRequestBtn">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                </div>
                <div class="request-details" id="requestDetails" style="display: none;">
                    <div class="detail-item">
                        <strong><i class="fas fa-align-left"></i> الوصف:</strong>
                        <p id="requestDescription"></p>
                    </div>
                    <div class="detail-item">
                        <strong><i class="fas fa-book"></i> المادة المرتبطة:</strong>
                        <p id="requestCourse">غير محدد</p>
                    </div>
                    <div class="detail-item">
                        <strong><i class="fas fa-paperclip"></i> المرفقات الأولية:</strong>
                        <div id="requestAttachments" class="uploaded-files file-preview-container"></div>
                    </div>
                </div>
                
                <div class="messages-area-service" id="messagesArea"></div>
                <div id="messagesWrapper" class="chat-scroll">
                  <!-- اللودر سيتولد تلقائيًا -->
                  <div id="messagesList"></div>
                </div>
                <div class="input-area" id="inputArea">
                    
                    <div class="input-tools">
                        <button class="tool-btn" onclick="attachFile()" title="إرفاق ملف">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <button class="tool-btn voice-btn" id="voiceBtn" onclick="toggleVoiceRecording()" title="تسجيل صوتي">
                            <i class="fas fa-microphone"></i>
                        </button>
                        <input type="file" id="chatFileInput" style="display: none;" multiple/>
                        <div class="voice-recording" id="voiceRecording" style="display: none;">
                            <div class="recording-visual">
                                <span class="recording-dot"></span>
                                <div class="recording-wave">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                                <span class="recording-time" id="recordingTime">00:00</span>
                            </div>
                            <div class="recording-actions">
                                <button class="btn-stop-voice" onclick="stopRecording()" title="إيقاف وإرسال">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                                <button class="btn-cancel-voice" onclick="cancelRecording()" title="إلغاء">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="chatPendingAttachments" class="uploaded-files file-preview-container"></div>

                    <div class="message-input-wrapper">
                        <textarea id="messageInput" 
                                  placeholder="اكتب رسالتك..." 
                                  rows="1"></textarea>
                        <button class="send-btn" onclick="sendMessage()">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>

:root {
  --primary-rgb: 33,158,188;
  --success-rgb: 40,167,69;
  --warning-rgb: 255,193,7;
  --danger-rgb: 220,53,69;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: 'Almarai', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--background);
  color: var(--text);
  line-height: 1.6;
  overflow-x: hidden;
}
.unread-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  height: 22px;
  padding: 0 7px;
  background: linear-gradient(135deg, #ff4d6d, #e63946); /* تدرج أحمر عصري */
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  border-radius: 50px;
  box-shadow: 0 3px 8px rgba(230, 57, 70, 0.4);
  position: absolute;
  top: 6px;
  right: 6px;
  z-index: 10;
  user-select: none;
  animation: pulseBadge 1.5s infinite ease-in-out;
}

/* حركة نبضية لطيفة */
@keyframes pulseBadge {
  0% {
    transform: scale(1);
    box-shadow: 0 3px 8px rgba(230, 57, 70, 0.4);
  }
  50% {
    transform: scale(1.15);
    box-shadow: 0 4px 12px rgba(230, 57, 70, 0.6);
  }
  100% {
    transform: scale(1);
    box-shadow: 0 3px 8px rgba(230, 57, 70, 0.4);
  }
}
#messagesArea {
  position: relative;
  flex: 1 1 auto;            /* خليه يتمدّد */
  min-height: 0;             /* مفتاح التمرير داخل flex child */
  overflow-y: auto;          /* تفعيل السكrol */
  overflow-x: hidden;
  -webkit-overflow-scrolling: touch; 
  overscroll-behavior: contain;      /* منع سحب الصفحة كاملة */
}
/* =============== Unified Buttons (بدون تغيير HTML) =============== */
.btn,
.btn-new-request,
.btn-icon,
.send-btn,
.tool-btn,
.btn-stop-voice,
.btn-cancel-voice,
.tab-btn {
  font-family: inherit;
  font-weight: 600;
  border-radius: var(--border-radius);
  border: 1px solid transparent;
  cursor: pointer;
  transition: var(--transition);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn:focus-visible,
.btn-new-request:focus-visible,
.btn-icon:focus-visible,
.send-btn:focus-visible,
.tool-btn:focus-visible,
.btn-stop-voice:focus-visible,
.btn-cancel-voice:focus-visible,
.tab-btn:focus-visible {
  outline: 3px solid rgba(var(--primary-rgb), .25);
  outline-offset: 2px;
}

/* Primary action look (يُطبّق على الأزرار الأساسية) */
.btn-new-request,
.send-btn,
.btn-stop-voice {
  background: var(--primary);
  color: #fff;
  box-shadow: var(--shadow);
}
.btn-new-request:hover,
.send-btn:hover,
.btn-stop-voice:hover { background: var(--primary-dark); transform: translateY(-1px); }

/* Ghost / Secondary (أزرار رمادية خفيفة) */
.tool-btn,
.btn-cancel-voice,
.btn-icon {
  background: var(--border-color);
  color: var(--text-light);
  border-color: var(--border-color);
}
.tool-btn:hover,
.btn-cancel-voice:hover,
.btn-icon:hover { background: var(--border-color); color: var(--text); }

/* Tabs (pill) */
.tab-btn {
  background: var(--border-color);
  color: var(--text-light);
  padding: 6px 12px;
  border-radius: 16px;
  font-size: .85rem;
}
.tab-btn:hover,
.tab-btn.active { background: var(--primary); color: #fff; }
.tab-btn .count {
  background: rgba(255,255,255,.22);
  padding: 0 6px;
  border-radius: 10px;
}

/* Badges in sidebar menu (unchanged classes) */
/* 
.menu-link .badge,
.submenu-link .badge {
  background: #e74c3c; color: #fff;
  padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-left: auto;
  min-width: 18px; text-align: center; font-weight: 600;
}
.menu-item.collapsed .badge {
  position: absolute; top: 8px; right: 8px; font-size: 9px; padding: 1px 4px;
}*/

/* ================== Page Layout ================== */
.service-requests-container {
  max-width: 2000px;
  margin: 0 auto;
  display: flex; flex-direction: column;
}

/* Header */
.service-header {
  background: var(--surface-1);
  border-bottom: 1px solid var(--border-color);
  padding: 2px 18px;
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px;
}
.header-title h1 {
  font-size: 1.4rem; font-weight: 800; color: var(--primary-dark);
  display: flex; align-items: center; gap: 10px;
}
.header-title h1 i { color: var(--primary); font-size: 1.2rem; }
.header-title p { color: var(--text-light); font-size: .9rem; margin-top: 4px; }

/* Button sizes without altering HTML */
.btn-new-request { padding: 10px 18px; font-size: .95rem; }

/* Main area */
.service-content { flex: 1; display: flex; overflow: hidden; }

/* Chat Area */
.chat-area { flex: 1; display: flex; flex-direction: column; background: var(--surface-1); max-height:80vh}
.chat-container { flex: 1; display: none; flex-direction: column; }
.chat-placeholder {
  flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
  color: var(--text-light); text-align: center; padding: 20px;min-height:80vh;
}
.chat-placeholder i { font-size: 3rem; color: var(--primary-light); margin-bottom: 12px; }
.chat-placeholder h3 { font-size: 1.1rem; margin-bottom: 6px; color: var(--text); }

/* Chat Header */
.chat-header {
  padding: 12px 18px;
  border-bottom: 1px solid var(--border-color);
  display: flex; align-items: center; justify-content: space-between;
  background: var(--surface-1);
  position: sticky; top: 0; z-index: 2;
}
.request-info h3 { font-size: 1rem; font-weight: 700; margin-bottom: 4px; }
.request-meta { display: flex; align-items: center; gap: 10px; font-size: .8rem; color: var(--text-light); }
.request-info {display: flex; flex-direction: row; align-items: center;gap: 12px;}
.status-badge {
  padding: 3px 8px; border-radius: 12px; font-size: .72rem; font-weight: 700;
}
.status-badge.pending   { background: rgba(var(--warning-rgb), .12); color: var(--warning); }
.status-badge.in_progress { background: rgba(var(--primary-rgb), .12); color: var(--primary); }
.status-badge.resolved  { background: rgba(var(--success-rgb), .12); color: var(--success); }
.status-badge.cancelled { background: rgba(var(--danger-rgb), .12); color: var(--danger); }

.chat-actions { display: flex; gap: 8px; }
.btn-icon { width: 36px; height: 36px; border-radius: 50%; }
.btn-cancel-request { background: rgba(var(--danger-rgb), .1); color: var(--danger); border-color: transparent; }
.btn-cancel-request:hover { background: var(--danger); color: #fff; }

/* Request Details */
.request-details {
  background: var(--surface-1);
  border-bottom: 1px solid var(--border-color);
  padding: 10px 10px;
  overflow: auto; transition: var(--transition);
  min-height: fit-content;
}
.detail-item { margin-bottom: 10px; }
.detail-item:last-child { margin-bottom: 0; }
.detail-item strong {
  color: var(--text); margin-bottom: 4px;
  display: flex; align-items: center; gap: 6px;
}
.detail-item p { color: var(--text-light); margin: 0; padding-left: 22px; }

/* Messages */
.messages-area-service {
  flex: 1; overflow-y: auto;
  padding: 10px 10px;
  background: var(--surface-2);
  display: flex; flex-direction: column; gap: 14px;
}
.message-service { display: flex; align-items: flex-start; animation: fadeIn .25s ease; }
.sentmsg { justify-content: flex-end; }
.receivedmsg { justify-content: flex-start; }

.message-content {
  max-width: 72%;
  background: var(--surface-1);
  border-radius: var(--border-radius);
  padding: 10px 10px;
  box-shadow: var(--shadow);
  position: relative;
}
.sentmsg .message-content { background: var(--surface-1); color: var(--text); border-bottom-left-radius: 6px; }
.receivedmsg .message-content { border-bottom-right-radius: 6px; }

.message-header {
  display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;
}
.sender-name { font-weight: 700; font-size: .82rem; color: var(--text); }
.sentmsg .sender-name { color:var(--text); }
.sender-role { font-size: .72rem; color: var(--text); margin-right: 6px; }
.message-time { font-size: .72rem; color: var(--text); }
.sentmsg .message-time { color: var(--text); }
.message-text { margin: 0 0 8px 0; line-height: 1.6; font-size: .95rem; }

/* Attachments */
.message-files {
  display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  margin-top: 8px;
}
.file-card {
  position: relative; background: #fff; border: 1px solid var(--border-color);
  border-radius: 12px; padding: 10px; box-shadow: 0 4px 10px rgba(0,0,0,.05);
  height: 190px; display: flex; flex-direction: column; align-items: stretch;
  overflow: hidden; text-decoration: none; color: inherit;
}
.file-card .thumb {
  width: 100%; aspect-ratio: 16/10; background: #f3f6f9; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; overflow: hidden;
}
.file-card .img-thumb img { width: 100%; height: 100%; object-fit: contain; }
.file-card .icon-thumb i { font-size: 48px; color: var(--primary); opacity: .9; }
.file-card .meta { margin-top: 8px; display: flex; flex-direction: column; gap: 4px; }
.file-card .meta .name {
  font-size: 13px; font-weight: 800; color: var(--primary-darker); line-height: 1.2;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;
}
.file-card .meta .sub { font-size: 12px; color: #7d8790; }
.fab-download,
.file-card .fab-download {
  position: absolute; right: -40px; bottom: 0px; width: 34px; height: 34px; border-radius: 50%;
  background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center;
  transition: transform .15s ease;
}
.file-card .fab-download:hover { transform: translateY(-2px); }
.message-service.sentmsg .message-content {
    position: relative;
}

.message-delete-btn {
    position: absolute;
    top: 40%;
    right: -40px;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.8);
    border: none;
    color: white;
    font-size: 10px;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.message-service.sentmsg:hover .message-delete-btn {
    opacity: 1;
    visibility: visible;
}

.message-delete-btn:hover {
    background: rgba(220, 38, 38, 0.9);
}


/* الرسالة المحذوفة */
.message-deleted {
    opacity: 0.6;
    font-style: italic;
    color: #6b7280;
    background: #f3f4f6 !important;
    border: 1px dashed #d1d5db !important;
}

.message-deleted .message-text {
    color: #6b7280 !important;
}

.message-deleted .message-files,
.message-deleted .voice-message {
    display: none;
}

@media (max-width: 768px) {
    .message-delete-btn {
        opacity: 1;
        visibility: visible;
    }
}
/* Voice Message */
.voice-message { margin: 8px 0; }
.voice-recording {
    width: auto;
    min-width: 50%;
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    border: 2px solid var(--primary-light);
    border-radius: 16px;
    padding: 2px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    animation: fadeIn 0.3s ease;
}

.recording-visual {
    display: flex;
    align-items: center;
    gap: 12px;
}

.recording-dot {
    width: 14px;
    height: 14px;
    background: var(--rejected);
    border-radius: 50%;
    animation: pulse 1.5s infinite;
    box-shadow: 0 0 8px var(--rejected);
}

/* موجات التسجيل */
.recording-wave {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    height: 16px;
}

.recording-wave span {
    display: block;
    width: 3px;
    background: var(--background);
    border-radius: 2px;
    animation: wave 1s infinite ease-in-out;
}

.recording-wave span:nth-child(1) { animation-delay: 0s; }
.recording-wave span:nth-child(2) { animation-delay: 0.1s; }
.recording-wave span:nth-child(3) { animation-delay: 0.2s; }
.recording-wave span:nth-child(4) { animation-delay: 0.3s; }
.recording-wave span:nth-child(5) { animation-delay: 0.4s; }

@keyframes wave {
    0%, 100% { height: 4px; }
    50% { height: 16px; }
}

.recording-time {
    font-weight: 700;
    font-size: 1.05rem;
    color: var(--background);
}

.recording-actions {
    display: flex;
    gap: 8px;
}

.voice-recording button {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease, background 0.2s ease;
}

.voice-recording .btn-stop-voice {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 10px rgba(33, 158, 188, 0.3);
}

.voice-recording .btn-stop-voice:hover {
    transform: scale(1.1);
    background: var(--primary-dark);
}

.voice-recording .btn-cancel-voice {
    background: var(--rejected);
    color: white;
    box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
}

.voice-recording .btn-cancel-voice:hover {
    transform: scale(1.1);
    background: #b51d2a;
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.audio-player {
  display: flex; align-items: center; gap: 10px; padding: 8px 12px;
  background: rgba(0,0,0,.05); border-radius: var(--border-radius);
}
.sentmsg .audio-player { background: rgba(255,255,255,.22); }

/* Input Area */
.input-area {
  padding: 14px 18px;
  background: var(--surface-1);
  border-top: 1px solid var(--border-color);
  flex-shrink: 0;
}
.input-area.disabled { opacity: .6; pointer-events: none; }

.input-tools { display: flex; gap: 8px; margin-bottom: 12px; }
.tool-btn { width: 40px; height: 40px; border-radius: 50%; }

.uploaded-files.file-preview-container {
  display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  margin-bottom: 12px;
}

.message-input-wrapper { flex: 1; position: relative; }
#messageInput {
  width: 100%; border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  padding: 12px 16px 12px 50px;
  resize: none; font-family: inherit; font-size: .95rem; transition: var(--transition);
  max-height: 160px; min-height: 50px; background: var(--border-strong);
}
#messageInput:focus {
  border-color: var(--primary); outline: none;
  box-shadow: 0 0 0 3px rgba(var(--primary-rgb), .2);
}
.send-btn {
  position: absolute; left: 10px; bottom: 10px; width: 38px; height: 38px; border-radius: 50%;
}

/* Requests Sidebar */
.requests-sidebar {
  width: 320px; background: var(--surface-1);
  border-left: 1px solid var(--border-color);
  display: flex; flex-direction: column; flex-shrink: 0;
  max-height: 80vh;
}
.sidebar-header { padding: 16px 14px; border-bottom: 1px solid var(--border-color); }
.sidebar-header h3 { font-size: 1.05rem; font-weight: 800; color: var(--text); margin-bottom: 12px; }
.filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.requests-list { flex: 1; overflow-y: auto; padding: 8px 0; }

.request-item {
  padding: 12px 14px; cursor: pointer; transition: var(--transition);
  border-bottom: 1px solid var(--border-color); position: relative;
}
.request-item:hover { background: var(--border-color); }
.request-item.active {
  background: linear-gradient(90deg, rgba(33,158,188,.08), transparent);
  border-left: 3px solid var(--primary);
}
.request-title {
  font-weight: 700; font-size: .92rem; color: var(--text);
  margin-bottom: 4px; display: flex; align-items: center; gap: 8px;
}
.status-indicator { width: 8px; height: 8px; border-radius: 50%; }
.status-indicator.pending { background: var(--warning); }
.status-indicator.in_progress { background: var(--primary); }
.status-indicator.resolved { background: var(--success); }
.status-indicator.cancelled { background: var(--danger); }

.request-meta {
  display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
  font-size: .78rem; color: var(--text-light);
}
.course-name { display: flex; align-items: center; gap: 4px; }
.request-date { margin-right: auto; }
.unread-count {
  position: absolute; top: 8px; right: 8px; background: var(--primary); color: #fff;
  font-size: .72rem; font-weight: 700; padding: 2px 6px; border-radius: 10px; min-width: 18px; text-align: center;
}

/* Animations */
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px);} to { opacity: 1; transform: translateY(0);} }

/* ================== Responsive ================== */

/* <= 1200px: قلّل عرض القائمة اليمنى ووسع الرسائل */
@media (max-width: 1200px) {
  .requests-sidebar { width: 300px; }
  .message-content { max-width: 76%; }
  .header-title h1 { font-size: 1.25rem; }
}

/* <= 992px: واجهة عمودية – تظهر قائمة الطلبات أولاً ثم المحادثة */
@media (max-width: 992px) {
  .service-requests-container { height: auto; }
  .service-content {
    flex-direction: column;
    overflow: visible;
  }
  .requests-sidebar {
    width: 100%; order: -1; border-left: 0; border-bottom: 1px solid var(--border-color);
    position: sticky; top: 0; z-index: 3; /* تبقى ظاهرة أعلى */
  }
  .sidebar-header { padding: 14px 12px; }
  .chat-header { padding: 12px; }
  .messages-area-service { padding: 12px; }
  .request-item.active { border-left: 0; border-inline-start: 0; }
  .message-content { max-width: 86%; }
}

/* <= 768px: تحسين اللمس والقراءة */
@media (max-width: 768px) {
  .service-header { padding: 12px; }
  .btn-new-request { padding: 9px 14px; font-size: .9rem; }
  .message-content { max-width: 92%; }
  .message-files { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
  .file-card { height: 170px; }
  .tool-btn { width: 38px; height: 38px; }
  #messageInput { padding: 12px 46px 12px 12px; font-size: .92rem; }
  .send-btn { width: 36px; height: 36px; right: 8px; bottom: 8px; }
  .tab-btn { padding: 6px 10px; font-size: .82rem; }
  .chat-actions .btn-icon { width: 34px; height: 34px; }
}

/* <= 576px: شاشة جوال صغيرة – رسائل بعرض شبه كامل وأحجام خط أكبر قليلًا */
@media (max-width: 576px) {
  .header-title h1 { font-size: 1.1rem; }
  .header-title p { font-size: .85rem; }
  .message-content { max-width: 100%; }
  .message-text { font-size: .98rem; }
  .messages-area-service { gap: 12px; }
  .file-card { height: 160px; }
  .chat-placeholder i { font-size: 2.4rem; }
}

/* <= 400px: ضغط المسافات */
@media (max-width: 400px) {
  .service-header { gap: 8px; }
  .btn-new-request { padding: 8px 12px; font-size: .86rem; }
  .tab-btn { padding: 5px 9px; font-size: .78rem; }
  .request-title { font-size: .88rem; }
  .request-meta { font-size: .74rem; }
}

/* تحسين التباين في الوضع الداكن الاختياري (إن طُبّق لاحقًا عبر body.dark) */
body.dark .messages-area-service { background: #0e1418; }
body.dark .service-header,
body.dark .chat-header,
body.dark .requests-sidebar,
body.dark .chat-area,
body.dark .request-details { background: #0b1114; border-color: #132028; }
/* =========================
   Audio Player – Unified with new buttons style
   ========================= */

/* الحاوية الأساسية */
.audio-player {
  width: 100%;
  border-radius: var(--border-radius);
  padding: 10px;
  display: grid;
  gap: 8px;
  /* خلي الخلفية منسجمة مع فقاعة الرسالة:
     عادية: رمادي فاتح، والمرسلة: شفاف أبيض */
  background: rgba(0, 0, 0, 0.05);
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow);
}
.sentmsg .audio-player {
  background: rgba(255, 255, 255, 0.22);
  border-color: transparent;
  box-shadow: none;
}

/* إخفاء تحكم المتصفح */
.audio-player audio { display: none; }

/* الصفوف */
.audio-player .ap-top,
.audio-player .ap-bottom {
  display: flex;
  align-items: center;
  gap: 10px;
}
.audio-player .ap-bottom { padding-inline: 4px; }

/* =============== الأزرار (موحّدة مع نظام الأزرار الجديد) =============== */
.ap-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid var(--border-color);
  background: var(--border-color);
  color: var(--text-light);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: var(--transition);
  flex: 0 0 auto;
  box-shadow: none;
}
.ap-btn:hover { background: var(--border-color); color: var(--text); }
.ap-btn:focus-visible {
  outline: 3px solid rgba(var(--primary-rgb), .25);
  outline-offset: 2px;
}

/* زر التشغيل يمكن إبرازُه قليلاً كبرايمري مصغّر */
.ap-btn.ap-play {
  background: var(--primary);
  color: #fff;
  border-color: transparent;
  box-shadow: var(--shadow);
}
.ap-btn.ap-play:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
}

/* حالة ميوت – تبديل الأيقونة فقط */
.audio-player .ap-mute.is-muted i::before { content: "\f6a9"; } /* fa-volume-mute */

/* زر السرعة بنفس نمط pill (مثل التبويبات) */
.audio-player .ap-speed {
  background: var(--border-color);
  color: var(--text);
  border: 1px solid var(--border-color);
  box-shadow: none;
  height: 34px; padding: 0 10px; border-radius: 16px;
  font-weight: 700;
  transition: var(--transition);
}
.audio-player .ap-speed:hover {
  background: var(--primary);
  border-color: transparent;
  color: #fff;
}
.audio-player .ap-speed:focus-visible {
  outline: 3px solid rgba(var(--primary-rgb), .25);
  outline-offset: 2px;
}

/* الوقت */
.ap-time {
  font-weight: 700;
  color: var(--text);
  min-width: 96px;
  text-align: center;
  direction: ltr;
  flex: 0 0 auto;
}
.sentmsg .ap-time { color: var(--text); }

/* السلايدرز */
.ap-volume,
.ap-seek {
  accent-color: var(--primary);
}
.ap-volume { width: 110px; }
.ap-seek   { flex: 1; height: 6px; }

/* تحسين مظهر السلايدر عبر المتصفحات الداعمة (اختياري وخفيف) */
.ap-seek::-webkit-slider-runnable-track,
.ap-volume::-webkit-slider-runnable-track {
  height: 6px;
  background: var(--border-color);
  border-radius: 999px;
}
.ap-seek::-webkit-slider-thumb,
.ap-volume::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 14px; height: 14px;
  border-radius: 50%;
  background: var(--primary);
  border: 2px solid #fff;
  margin-top: -4px;
  box-shadow: 0 1px 3px rgba(0,0,0,.15);
}
.ap-seek::-moz-range-track,
.ap-volume::-moz-range-track {
  height: 6px;
  background: var(--border-color);
  border-radius: 999px;
}
.ap-seek::-moz-range-thumb,
.ap-volume::-moz-range-thumb {
  width: 14px; height: 14px; border-radius: 50%;
  background: var(--primary);
  border: 2px solid #fff;
  box-shadow: 0 1px 3px rgba(0,0,0,.15);
}

/* ===================== Responsive ===================== */
/* شاشات متوسطة: لفّ أدوات الأعلى كسطرين عند الحاجة */
@media (max-width: 768px) {
  .audio-player .ap-top { flex-wrap: wrap; row-gap: 8px; }
  .ap-volume { width: 90px; }
  .ap-time { min-width: 84px; }
}

/* شاشات صغيرة جدًا: تصغير الحجم وترتيب عمودي أنظف */
@media (max-width: 480px) {
  .ap-btn { width: 34px; height: 34px; }
  .ap-time { font-size: .9rem; min-width: 72px; }
  .ap-volume { width: 100%; order: 4; } /* انزلها آخر الصف */
  .audio-player .ap-top { gap: 8px; }
  .audio-player .ap-bottom { padding-inline: 0; }
  .audio-player { gap: 6px; }
}

/* منع خروج المحتوى أفقياً على الشاشات الأضيق */
.voice-message, .audio-player { overflow: hidden; }

/* وضع داكن اختياري إن فُعّل body.dark لاحقًا */
body.dark .audio-player {
  background: rgba(255,255,255,.06);
  border-color: #1f2a33;
}
body.dark .sentmsg .audio-player {
  background: rgba(255,255,255,.15);
}
body.dark .ap-btn { background: #0f1a20; border-color: #1f2a33; color: #b8c2cc; }
body.dark .ap-btn:hover { background: #16222a; color: #e7edf2; }
body.dark .audio-player .ap-speed { background: #0f1a20; border-color: #1f2a33; color: #e7edf2; }
body.dark .audio-player .ap-speed:hover { background: var(--primary); border-color: transparent; color: #fff; }
.uploaded-files.file-preview-container{display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-top:14px}
.file-card{position:relative;background:#fff;border:1px solid var(--border,#e6e8ec);border-radius:12px;padding:10px;box-shadow:0 4px 10px rgba(0,0,0,.05);height:190px;display:flex;flex-direction:column;align-items:stretch;overflow:hidden;text-decoration:none;color:inherit}
.file-card .thumb{width:100%;aspect-ratio:16/10;background:#f3f6f9;border-radius:10px;display:flex;align-items:center;justify-content:center;overflow:hidden}
.file-card .img-thumb img{width:100%;height:100%;object-fit:contain}
.file-card .video-thumb video{width:100%;height:100%;object-fit:contain;border:0;outline:none;border-radius:10px}
.file-card .icon-thumb i{font-size:64px;color:var(--primary,#219ebc);opacity:.9}
.file-card .meta{margin-top:8px;display:flex;flex-direction:column;gap:4px}
.file-card .meta .name{font-size:13px;font-weight:700;color:var(--primary-darker,#023047);line-height:1.2;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-overflow:ellipsis}
.file-card .meta .sub{font-size:12px;color:#7d8790}

/* زر الحذف في المعاينة */
.file-card .remove-btn{
    position:absolute;
    top:8px;
    left:8px;
    width:28px;
    height:28px;
    border-radius:50%;
    background:rgba(239,68,68,0.9);
    border:none;
    color:white;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:12px;
    cursor:pointer;
    transition:all 0.2s ease;
    box-shadow:0 2px 8px rgba(239,68,68,0.3);
    z-index:10;
}
.file-card .remove-btn:hover{
    background:rgba(220,38,38,0.95);
    transform:scale(1.1);
    box-shadow:0 4px 12px rgba(239,68,68,0.4);
}
.file-card .remove-btn:active{
    transform:scale(0.95);
}

@media (max-width:480px){
    .uploaded-files.file-preview-container{grid-template-columns:repeat(auto-fill,minmax(140px,1fr))}
    .file-card{height:180px}
    .file-card .icon-thumb i{font-size:56px}
}

.preview-strip{margin-top:10px}
.preview-strip .preview-title{font-size:12px;color:#6b7280;margin-bottom:8px;font-weight:600}
.preview-strip .preview-grid{display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(150px,1fr))}
.preview-strip .file-card{height:170px}
.file-card.has-actions .fab-download{
    position:absolute;
    right:10px;
    bottom:10px;
    width:40px;
    height:40px;
    border-radius:50%;
    background:linear-gradient(135deg, #10b981, #059669);
    border:2px solid rgba(255,255,255,0.9);
    display:flex;
    align-items:center;
    justify-content:center;
    color:white;
    text-decoration:none;
    box-shadow:0 4px 12px rgba(16,185,129,0.3), inset 0 1px 0 rgba(255,255,255,0.2);
    transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index:10;
}
.file-card.has-actions .fab-download i{
    font-size:16px;
    filter:drop-shadow(0 1px 2px rgba(0,0,0,0.1));
}
.file-card.has-actions .fab-download:hover{
    background:linear-gradient(135deg, #059669, #047857);
    transform:translateY(-2px) scale(1.05);
    box-shadow:0 8px 20px rgba(16,185,129,0.4), inset 0 1px 0 rgba(255,255,255,0.2);
}
.file-card.has-actions .fab-download:active{
    transform:translateY(0) scale(1);
    box-shadow:0 4px 12px rgba(16,185,129,0.3), inset 0 1px 0 rgba(255,255,255,0.2);
}
</style>



