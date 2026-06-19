<?php
// app/Views/students/pages/Service.php
// ✅ Fragment يُحمَّل ديناميكيًا بواسطة public/assets/js/students/student_router.js
// ✅ لا نعتمد على جدول users نهائيًا؛ الاسم يأتي من Session مباشرة (مرَّره الكنترولر).
// تمرير المتغيرات من الكنترولر:
// - $features     = ['plan_level' => int, 'canSendVoice' => bool]
// - $session_user = ['id' => int, 'name' => string, 'role' => int, 'avatar' => string]

$features     = $features     ?? ['plan_level' => 0, 'canSendVoice' => false];
$session_user = $session_user ?? [
    'id' => (int) (session('user_id') ?? 0),
    'name' => (string) (session('user_name') ?? ''),
    'role' => (int) (session('role') ?? 0),
    'avatar' => (string) (session('profile_image_url') ?? ''),
];

// ⚠️ في الـ Fragment لا نمتلك <head> لوضع الميتا CSRF، لذا نضع حقول خفية يقرأها JS.
$csrfTokenName = csrf_token();
$csrfHash      = csrf_hash();
?>
<div class="service-requests-container"
     id="serviceRoot"
     data-plan-level="<?= (int) $features['plan_level'] ?>"
     data-can-voice="<?= $features['canSendVoice'] ? '1' : '0' ?>"
     data-session-user-id="<?= (int) $session_user['id'] ?>"
     data-session-user-name="<?= esc($session_user['name']) ?>"
     data-session-user-role="<?= (int) $session_user['role'] ?>"
     data-session-user-avatar="<?= esc($session_user['avatar']) ?>">

    <!-- CSRF (يقرأه service.js عند كل POST) -->
    <input type="hidden" id="__csrf_name" value="<?= esc($csrfTokenName) ?>">
    <input type="hidden" id="__csrf_hash" value="<?= esc($csrfHash) ?>">

    <!-- ===== Header ===== -->
    <div class="service-header">
        <div class="header-title">
            <h1><i class="fas fa-headset"></i> طلبات الخدمة المتخصصة</h1>
            <p>تابع الطلبات الموكلة الك</p>
        </div>

        <!-- زر طلب جديد: بلا inline JS -->
       
    </div>

    <!-- شريط أعلى الصفحة: الخطة + أولوية المعالجة + SLA (يملؤه service.js) -->
    <div id="serviceTopMeta" class="svc-top-meta" style="margin:10px 0 6px;"></div>

    <!-- تلميح الترقية إن كانت ميزات الخطة غير متاحة (voice/video) -->
    <div id="serviceUpgradeHint" class="svc-upgrade-hint" style="display:none;margin-bottom:12px;"></div>

    <!-- ===== Main Content ===== -->
    <div class="service-content">

        <!-- ===== Sidebar (طلبات + فلاتر) ===== -->
        <div class="requests-sidebar">
            <div class="sidebar-header">
                
                
             
                <h3>طلباتي</h3>

                <!-- حقل بحث: يلتقطه service.js -->
                <div class="input-group" style="margin:8px 0 12px;">
                    <input id="svcSearch" class="form-control" type="text" placeholder="ابحث في الموضوع/الوصف...">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                </div>
   
               <div class="requests-ddl-mobile" id="requestsDdlWrap" >
                  <label for="svcReqSelect" class="ddl-label" style="display:block;font-weight:800;margin-bottom:6px;">طلباتي</label>
                  <select id="svcReqSelect" class="form-control" style="width:100%;border-radius:12px;padding:10px;border:1px solid var(--border-color);background:#fff;">
                    <option value="">اختر طلباً…</option>
                  </select>
                    <div id="svcPaginationMobile" class="svc-pagination-mobile" style="display:none;margin-top:10px;"></div>

                </div>
                           
                <!-- تبويبات الحالة -->
                <div class="filter-tabs">
                    <button class="tab-btn active" data-status-tab="">
                        الكل <span class="count" id="countAll">0</span>
                    </button>
                    <button class="tab-btn" data-status-tab="pending">
                        قيد الانتظار <span class="count" id="countPending">0</span>
                    </button>
                    <button class="tab-btn" data-status-tab="in_progress">
                        قيد المعالجة <span class="count" id="countProgress">0</span>
                    </button>
                    <button class="tab-btn" data-status-tab="resolved">
                        تم الحل <span class="count" id="countResolved">0</span>
                    </button>
                    <button class="tab-btn" data-status-tab="cancelled">
                        ملغي <span class="count" id="countCancelled">0</span>
                    </button>
                </div>
            </div>

            <!-- قائمة الطلبات -->
            <div class="requests-list" id="serviceList">
                <div class="loading-placeholder">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>جاري تحميل الطلبات...</p>
                </div>
            </div>

            <!-- ترقيم الصفحات (إن احتجناه لاحقًا) -->
            <div id="svcPagination" class="svc-pagination" style="margin-top:10px;"></div>
        </div>

        <!-- ===== Chat Area ===== -->
        <div class="chat-area">
            <div class="chat-placeholder" id="chatPlaceholder">
                <i class="fas fa-comments"></i>
                <h3>اختر طلباً لعرض المحادثة</h3>
                <p>اختر أحد الطلبات من القائمة الجانبية لبدء المحادثة</p>
            </div>

            <div class="chat-container" id="chatContainer" style="display:none;">
                <div class="chat-header">
                    <div class="header-left">
                        <div class="subject-row">
                            <h3 id="chatRequestTitle">Service request</h3>
                            <span class="status-badge" id="chatRequestStatus">pending</span>
                            <span class="badge unread" id="activeChatUnread" style="display:none;"></span>
                        </div>
                        <div class="meta-row">
                            <span class="date" id="chatRequestDate">?</span>
                        </div>
                    </div>
                    <div class="header-right">
                        <div class="status-control compact">
                          <label for="statusSelect" class="lbl">Status</label>
                          <select id="statusSelect" class="form-control" disabled></select>
                        </div>
                        <div class="chat-actions">
                            <button type="button" class="btn-icon" id="btnToggleRequestDetails" title="">
                                <i class="fas fa-info-circle"></i>
                            </button>
                            <button type="button" class="btn-icon btn-cancel-request" id="cancelRequestBtn" title="">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="request-details" id="requestDetails" style="display:none;">
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
                    <div id="messagesList"></div>
                </div>

                <div class="input-area" id="inputArea">
                    <div class="input-tools">
                        <!-- أزرار بدون inline JS؛ سيُربط من service.js -->
                        <button type="button" class="tool-btn" id="btnAttachFile" title="إرفاق ملف">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <button type="button" class="tool-btn voice-btn" id="voiceBtn" title="تسجيل صوتي">
                            <i class="fas fa-microphone"></i>
                        </button>
                        <input type="file" id="chatFileInput" style="display:none;" multiple />

                        <div class="voice-recording" id="voiceRecording" style="display:none;">
                            <div class="recording-visual">
                                <span class="recording-dot"></span>
                                <div class="recording-wave">
                                    <span></span><span></span><span></span><span></span><span></span>
                                </div>
                                <span class="recording-time" id="recordingTime">00:00</span>
                            </div>
                            <div class="recording-actions">
                                <button type="button" class="btn-stop-voice" id="btnStopRecording" title="إيقاف وإرسال">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                                <button type="button" class="btn-cancel-voice" id="btnCancelRecording" title="إلغاء">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="chatPendingAttachments" class="uploaded-files file-preview-container"></div>

                    <div class="message-input-wrapper">
                        <textarea id="messageInput" placeholder="اكتب رسالتك..." rows="1"></textarea>
                        <button type="button" class="send-btn" id="btnSendMessage">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div><!-- /chat-area -->
    </div><!-- /service-content -->
</div>




<style>
/* ================== Service Page — Modern, Clean, RTL (Upgrade-like) ================== */
/* نطاق الصفحة فقط: لا نغيّر أسماء الكلاسات، نكتفي بإعادة تصميمها وفق توكنز مشابهة لصفحة Upgrade */
.service-requests-container{
  /* Tokens (منسجمة مع Upgrade) */
  --radius:16px;
  --shadow-sm: 0 1px 3px rgba(0,0,0,.08);
  --shadow-md: 0 6px 18px rgba(0,0,0,.10);
  --shadow-lg: 0 16px 44px rgba(0,0,0,.16);
  --transition:.22s ease;

  /* ربط بألوان منصّتك إن وُجدت، مع فfallback مطابق تقريبًا لصفحة Upgrade */
  --background: var(--bg, #f7f8fb);
  --surface-0: var(--surface, #ffffff);
  --surface-1: var(--surface, #ffffff);
  --surface-2: #f7f8fb;
  --surface-3: #eef1f6;

  --border-color: var(--border, #e8e8ef);

  --text: var(--text-strong, #0f172a);
  --text-light: var(--muted, #6b7280);

  --primary-50:#e8f5fb;
  --primary-200:#d2ebf7;
  --primary-400:#8ecae6;
  --primary-500:#219ebc;
  --primary-600:#1b81a0;
  --primary-700:#166b83;

  --primary: var(--primary-500);
  --primary-dark: var(--primary-600);
  --primary-darker: var(--primary-700);

  --success: #10b981;
  --warning: #f59e0b;
  --danger:  #ef4444;

  --shadow: var(--shadow-sm);
  --border-radius: 12px;


  padding: 24px 16px;
}

/* ================== Header ================== */
.service-header{
  background: linear-gradient(135deg, var(--primary-500), var(--primary-700));
  color:#fff;
  border-radius: var(--radius);
  padding: 18px 18px;
  display:flex; align-items:center; justify-content:space-between; gap:12px;
  box-shadow: var(--shadow-lg);
  position:relative; overflow:hidden;
  border: 1px solid rgba(255,255,255,.18);
}
.service-header::before{
  content:""; position:absolute; inset:-60% auto auto -60%;
  width:200%; height:200%;
  background: radial-gradient(transparent 50%, rgba(255,255,255,.1)),
              repeating-linear-gradient(45deg, rgba(255,255,255,.06) 0 10px, transparent 10px 20px);
  animation: svcFloat 22s linear infinite;
}
@keyframes svcFloat{ from{transform:translate(0,0)} to{transform:translate(120px,120px)} }

.header-title h1{
  margin:0; font-size:1.35rem; font-weight:800; display:flex; align-items:center; gap:10px; color:#fff;
}
.header-title h1 i{ color:#fff; opacity:.92; }
.header-title p{ margin:4px 0 0; color: rgba(255,255,255,.9); }

.btn-service-request,
.btn-new-request{ /* نفس الزر لكن نحافظ على اسم الكلاس الحالي */
  --_bg:#fff; --_ink: var(--primary-700);
  background:var(--_bg); color:var(--_ink);
  border:1px solid rgba(255,255,255,.55);
  padding:10px 14px; border-radius:12px; font-weight:800;
  box-shadow: var(--shadow-md);
  transition: transform var(--transition), box-shadow var(--transition), background var(--transition);
  z-index: 49;
}
.btn-service-request:hover{ transform: translateY(-2px); box-shadow: var(--shadow-lg); }

/* ================== Top Meta / Hint ================== */
.svc-top-meta{
  background: var(--surface-0);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 10px 12px;
  box-shadow: var(--shadow-sm);
}
.svc-upgrade-hint{
  display:block;
}
.svc-upgrade-hint .alert{
  display:grid; grid-template-columns:auto 1fr; gap:10px;
  padding:10px; border-radius:12px;
  border:1px solid #ffe5b4; background:#fff7e6; color:#a05a00;
  box-shadow: var(--shadow-sm);
}

/* ================== Layout ================== */
.service-content{ display:grid; gap:12px; grid-template-columns: 320px 1fr; margin-top: 12px; }
@media (max-width: 992px){ .service-content{ grid-template-columns: 1fr; } }

.requests-sidebar{
  background: var(--surface-0);
  border:1px solid var(--border-color);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}
.sidebar-header{
  padding:14px; border-bottom:1px solid var(--border-color); background:var(--surface-0);
}
.sidebar-header h3{ margin:0 0 10px; font-size:1rem; font-weight:800; color:var(--text); }

/* Search input look */
.input-group{
  display:grid; grid-template-columns: 1fr auto; gap:8px; align-items:center;
}
.input-group .form-control{
  border:1px solid var(--border-color); border-radius: 10px;
  padding:10px 12px; background:#fff; color:var(--text);
}
.input-group .input-group-text{
  padding:10px 12px; border-radius:10px;
  background:#fff; border:1px solid var(--border-color); color: var(--text-light);
}

/* Tabs (filters) — تشبه chips */
.filter-tabs{ display:flex; gap:8px; flex-wrap:wrap; }
.filter-tabs .tab-btn{
  background: #fff;
  border:1px solid var(--border-color);
  color: var(--text-light);
  padding: 8px 12px; border-radius: 999px; font-size:.86rem; font-weight:800;
  transition: background var(--transition), transform var(--transition), border-color var(--transition);
}
.filter-tabs .tab-btn:hover{ transform: translateY(-1px); border-color: var(--primary-200); background: var(--primary-50); color: var(--primary-700); }
.filter-tabs .tab-btn.active{
  background: var(--primary-600); color:#fff; border-color: transparent;
  box-shadow: var(--shadow-sm);
}
.filter-tabs .tab-btn .count{
  margin-inline-start:6px; background: rgba(0,0,0,.08);
  padding:1px 6px; border-radius:10px; font-weight:800;
}
.filter-tabs .tab-btn.active .count{ background: rgba(255,255,255,.22); }

/* Requests list items */
.requests-list{ max-height: 62vh; overflow:auto; }
.request-item{
  padding:12px 14px; cursor:pointer; transition: background var(--transition), border-color var(--transition);
  border-bottom:1px solid var(--border-color);
}
.request-item:hover{ background: var(--surface-2); }
.request-item.active{
  background: linear-gradient(90deg, rgba(33,158,188,.08), transparent);
  border-inline-start:3px solid var(--primary);
}
.request-title{ font-weight:800; color:var(--text); font-size:.94rem; display:flex; align-items:center; gap:8px; }
.status-indicator{ width:8px; height:8px; border-radius:50%; background: var(--border-color); }
.status-indicator.pending{ background: var(--warning); }
.status-indicator.in_progress{ background: var(--primary); }
.status-indicator.resolved{ background: var(--success); }
.status-indicator.cancelled{ background: var(--danger); }
.request-meta{ display:flex; flex-wrap:wrap; align-items:center; gap:10px; font-size:.78rem; color:var(--text-light); }

/* Pagination placeholder */
.svc-pagination{ padding: 10px; display:flex; justify-content:center; }

/* ================== Chat Area ================== */
.chat-area{
  background: transparent;
  display:flex; flex-direction:column; gap:12px;
}
.chat-container{
  background: var(--surface-0);
  border:1px solid var(--border-color);
  border-radius: 12px;
  box-shadow: var(--shadow-sm);
  overflow:hidden;
  display: flex;
  flex-direction: column;
}

/* Chat header with sticky bar */
.chat-header{
  display:flex; align-items:center; justify-content:space-between; gap:10px;
  padding:12px 14px; border-bottom:1px solid var(--border-color);
  background: var(--surface-0); position: sticky; top:0; z-index:2;
}
.request-info h3{ margin:0 0 2px; font-weight:800; font-size:1rem; color:var(--text); }
.request-meta{ gap:8px; font-size:.8rem; color: var(--text-light); }
.status-badge{
  padding:3px 8px; border-radius:12px; font-size:.72rem; font-weight:800;
  background:#eef2f7; color:var(--text-light);
}
.status-badge.pending{ background: rgba(245,158,11,.12); color:#d97706; }
.status-badge.in_progress{ background: rgba(33,158,188,.12); color: var(--primary-600); }
.status-badge.resolved{ background: rgba(16,185,129,.12); color:#059669; }
.status-badge.cancelled{ background: rgba(239,68,68,.12); color:#dc2626; }

.chat-actions{ display:flex; gap:8px; }
.btn-icon{
  width:36px; height:36px; border-radius: 10px;
  background:#fff; border:1px solid var(--border-color); color: var(--text-light);
  transition: transform var(--transition), background var(--transition);
}
.btn-icon:hover{ transform: translateY(-1px); background: var(--surface-2); }
.btn-cancel-request{ background: rgba(239,68,68,.08); color:#dc2626; border-color: transparent; }
.btn-cancel-request:hover{ background:#dc2626; color:#fff; }

/* Request details tray */
.request-details{
  padding:12px 14px; border-bottom:1px solid var(--border-color);
  background: var(--surface-0);
}
.detail-item{ margin-bottom:10px; }
.detail-item strong{ display:flex; gap:6px; align-items:center; color:var(--text); }
.detail-item p{ color:var(--text-light); margin:0; }

/* Messages scroller */
#messagesWrapper{
  background: var(--surface-2);
  padding: 12px; min-height: 300px; max-height: 58vh; overflow:auto;
  display:flex; flex-direction:column; gap:14px;
}

/* Message bubbles */
.message-service{ display:flex; align-items:flex-start; }
.sentmsg{ justify-content:flex-end; }
.receivedmsg{ justify-content:flex-start; }
.message-content{
  max-width: 72%;
  background: var(--surface-0);
  border:1px solid var(--border-color);
  border-radius: 14px;
  box-shadow: var(--shadow-sm);
  padding: 10px 12px;
}
.sentmsg .message-content{ background: var(--primary-50); border-color: var(--primary-200); }
.message-header{ display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; }
.sender-name{ font-weight:800; font-size:.82rem; color:var(--text); }
.message-time{ font-size:.74rem; color:var(--text-light); }
.message-text{ margin:0 0 8px; color:var(--text); }

/* Files inside messages */
.message-files{
  display:grid; gap:12px;
  margin-top: 8px;
}
.file-card{
  position:relative;
  background:#fff; color:inherit; text-decoration:none;
  border:1px solid var(--border-color); border-radius:12px; padding:10px;
  display:flex; flex-direction:column; gap:8px; overflow:hidden;
  box-shadow: var(--shadow-sm);
  transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition);
}
.file-card:hover{ transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: var(--primary-200); }
.file-card .thumb{ width:100%; background:#f3f6f9; border-radius:10px; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.file-card .img-thumb{ width:100%; background:#f3f6f9; border-radius:10px; overflow:hidden; display:flex; align-items:center; justify-content:center; }
.file-card .img-thumb img{ width:100%; height:auto; display:block; }
.file-card .icon-thumb i{ font-size:44px; color: var(--primary-600); opacity:.95; }
.file-card .meta{ display:flex; flex-direction:column; gap:4px; }
.file-card .meta .name{
  font-size:13px; font-weight:800; color: var(--primary-darker);
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
}
.file-card .meta .sub{ font-size:12px; color:#7d8790; }

/* Remove button on preview cards (قبل الإرسال) */
.file-card .remove-btn{
  position:absolute; top:8px; left:8px; width:28px; height:28px;
  border-radius:50%; border:none; cursor:pointer;
  background:rgba(239,68,68,0.95); color:#fff;
  display:flex; align-items:center; justify-content:center; font-size:12px; z-index:5;
  box-shadow: 0 2px 8px rgba(239,68,68,0.35);
  transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
}
.file-card .remove-btn:hover{ transform: scale(1.06); box-shadow: 0 6px 16px rgba(239,68,68,0.35); }
.file-card .remove-btn:active{ transform: scale(.96); }


/* === Media viewer & playback enhancements === */
.zoomable-media .img-card-button{
  width:100%; border:none; background:transparent; padding:0; cursor:pointer;
}
.zoomable-media .img-card-button:focus-visible{
  outline:2px solid var(--primary-400); outline-offset:4px;
}
.zoomable-media .img-thumb{
  border-radius:12px; overflow:hidden; background:var(--surface-2); box-shadow:var(--shadow-sm);
  transition: transform var(--transition), box-shadow var(--transition);
}
.zoomable-media .img-card-button:hover .img-thumb{ box-shadow: var(--shadow-md); }
.zoomable-media .img-card-button:hover .img-thumb img{ transform: scale(1.03); }
.zoomable-media .fallback-note{
  min-height:120px; display:flex; align-items:center; justify-content:center;
  padding:16px; text-align:center; font-size:.85rem; color:var(--text-light);
}

.img-viewer-overlay{
  position:fixed; inset:0; background:rgba(4,17,27,.8); backdrop-filter: blur(18px);
  display:flex; align-items:center; justify-content:center; z-index:999;
}
.img-viewer-glass{
  position:relative; background:rgba(12,34,48,.55); border:1px solid rgba(255,255,255,.18);
  border-radius:32px; padding:26px; box-shadow:0 28px 64px rgba(2,48,71,.45);
}
.img-viewer-content img{
  max-width:min(92vw, 780px); max-height:80vh; border-radius:22px; box-shadow:0 18px 44px rgba(0,0,0,.45);
}
.img-viewer-caption{
  margin-top:14px; text-align:center; color:#fff; font-weight:600; letter-spacing:.3px;
}
.img-viewer-close{
  position:absolute; top:18px; inset-inline-end:18px; width:46px; height:46px; border-radius:50%;
  border:1px solid rgba(255,255,255,.4); background:rgba(255,255,255,.2); color:#fff;
  backdrop-filter: blur(14px); cursor:pointer; display:flex; align-items:center; justify-content:center;
  box-shadow:0 16px 38px rgba(15,76,129,.45); transition: transform var(--transition), box-shadow var(--transition);
}
.img-viewer-close:hover{ transform: translateY(-2px) scale(1.03); box-shadow:0 22px 48px rgba(15,76,129,.5); }

.video-card.rich-media-card{
  height:auto; padding:12px; gap:10px;
}
.video-card .thumb{
  position:relative; padding:0; min-height:0;
}
.video-preview{
  width:100%;
  border-radius:14px;
  background: var(--surface-2);
  overflow:hidden;
  position:relative;
  box-shadow: var(--shadow-sm);
  display:flex;
  align-items:center;
  justify-content:center;
}
.video-preview::after{
  content:''; position:absolute; inset:0; pointer-events:none; background:linear-gradient(135deg, rgba(2,48,71,.05), rgba(33,158,188,.22));
}
.video-preview.has-error::after{ display:none; }
.video-preview img{
  width:100%;
  height:100%;
  object-fit:contain;
  display:block;
}
.video-preview.is-hidden{ opacity:0; pointer-events:none; }
.video-glass-play{
  position:absolute; inset:50% auto auto 50%; transform:translate(-50%, -50%);
  width:66px; height:66px; border-radius:22px; border:1px solid rgba(255,255,255,.45);
  background:rgba(255,255,255,.22); backdrop-filter: blur(12px); color:#fff; cursor:pointer;
  display:flex; align-items:center; justify-content:center; box-shadow:0 14px 40px rgba(15,76,129,.45);
  transition: transform .25s ease, box-shadow .25s ease, background .25s ease;
}
.video-glass-play:hover{ transform:translate(-50%, -50%) scale(1.05); background:rgba(255,255,255,.32); box-shadow:0 18px 46px rgba(15,76,129,.5); }
.video-glass-play.is-hidden{ display:none; }
.video-player-shell{
  position:absolute; inset:0; border-radius:14px; overflow:hidden; background:#000;
  display:flex; align-items:center; justify-content:center;
}
.video-player-shell[hidden]{ display:none !important; }
.video-card video{ width:100%; height:auto; max-height:100%; background:#000; }
.video-card .video-error{
  position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
  background:rgba(0,0,0,.66); color:#fff; font-weight:700;
}

.audio-bubble{
  position:relative; display:flex; flex-wrap:wrap; align-items:center; gap:14px;
  padding:16px 18px 28px; border-radius:18px; border:1px solid var(--border-color);
  background: var(--surface-0); box-shadow: var(--shadow-sm);
}
.audio-bubble.voice-bubble{
  background: linear-gradient(135deg, rgba(33,158,188,.18), rgba(21,102,131,.08));
  border-color: rgba(33,158,188,.32);
}
.audio-bubble.file-bubble{ background: var(--surface-1); }
.audio-bubble.has-warning{ border-color: var(--warning-border); background: var(--warning-soft); }
.audio-bubble.has-error{ border-color: var(--error-border); background: var(--error-soft); }
.audio-play{
  width:46px; height:46px; border-radius:50%; border:none;
  background:linear-gradient(135deg, var(--primary-500), var(--primary-700));
  color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer;
  box-shadow: var(--shadow-md); transition: transform var(--transition), box-shadow var(--transition);
}
.audio-play:hover{ transform: translateY(-1px); box-shadow: var(--shadow-lg); }
.audio-play:disabled{ opacity:.5; cursor:not-allowed; box-shadow:none; transform:none; }
.audio-visual{ display:flex; align-items:flex-end; gap:3px; height:22px; pointer-events:none; }
.audio-visual span{
  width:4px; height:6px; border-radius:2px; background: var(--primary-500);
  opacity:.55; animation: audioPulse 1.2s infinite ease-in-out;
}
.audio-bubble:not(.is-playing) .audio-visual span{ animation-play-state: paused; opacity:.32; }
.audio-visual span:nth-child(2){ animation-delay:.12s; }
.audio-visual span:nth-child(3){ animation-delay:.24s; }
.audio-visual span:nth-child(4){ animation-delay:.36s; }
.audio-visual span:nth-child(5){ animation-delay:.48s; }
.audio-slider{
  position:absolute; inset-inline-start:74px; inset-inline-end:86px; bottom:18px;
  height:4px; border-radius:999px; background:linear-gradient(90deg, var(--primary-500) 0%, rgba(255,255,255,.25) 0%);
  appearance:none; cursor:pointer;
}
.audio-slider:focus-visible{ outline:none; box-shadow: var(--focus-ring); }
.audio-slider::-webkit-slider-thumb{
  appearance:none; width:14px; height:14px; border-radius:50%; background:var(--primary-600);
  box-shadow:0 0 0 4px rgba(33,158,188,.28);
}
.audio-slider::-webkit-slider-runnable-track{ height:4px; border-radius:999px; background:transparent; }
.audio-slider::-moz-range-thumb{
  width:14px; height:14px; border:none; border-radius:50%; background:var(--primary-600);
  box-shadow:0 0 0 4px rgba(33,158,188,.28);
}
.audio-slider::-moz-range-track{ height:4px; border-radius:999px; background:transparent; }
.audio-meta{ flex:1; display:flex; flex-direction:column; gap:4px; }
.audio-name{ font-weight:700; color:var(--text); font-size:.9rem; }
.audio-sub{ font-size:.75rem; color:var(--text-light); display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.audio-footer{ margin-inline-start:auto; display:flex; align-items:center; gap:10px; font-size:.82rem; color:var(--text-light); }
.audio-time{ font-variant-numeric: tabular-nums; font-weight:600; color:var(--text); }
.audio-speed{
  border:none; background:rgba(33,158,188,.12); color:var(--primary-600);
  border-radius:999px; padding:4px 12px; font-weight:700; cursor:pointer;
  transition: background var(--transition), transform var(--transition);
}
.audio-speed:hover{ background:rgba(33,158,188,.22); transform: translateY(-1px); }
.audio-bubble.has-error .audio-speed{ opacity:.45; pointer-events:none; }

@keyframes audioPulse{
  0%,100%{ transform:scaleY(.35); }
  50%{ transform:scaleY(1); }
}

.file-card.has-warning{ border-color: var(--warning-border); background: var(--warning-soft); }
.badge.warn{
  display:inline-flex; align-items:center; padding:2px 10px; border-radius:999px;
  background:rgba(245,158,11,.16); color:var(--warning-500); font-size:.72rem; font-weight:700;
}

.message-header .sender-email{
  margin-inline-start:8px; font-size:.72rem; color:var(--text-light);
  background: var(--surface-2); padding:2px 8px; border-radius:999px;
}

.status-control{
  position:relative; margin-top:12px;
}
.status-control select{
  appearance:none; width:100%; border:1px solid var(--border-color);
  border-radius:12px; padding:10px 16px; background:var(--surface-0);
  padding-inline-end:48px; color:var(--text); font-weight:600; text-align:right;
  transition: border-color var(--transition), box-shadow var(--transition);
}
.status-control select:focus{ outline:none; border-color: var(--primary-500); box-shadow: var(--focus-ring); }
.status-control select:disabled{ background: var(--disabled-bg); color: var(--disabled-text); }
.status-control::after{
  content:'\f0d7'; font-family:"Font Awesome 5 Free"; font-weight:900; color:var(--text-light);
  position:absolute; inset-inline-end:18px; top:50%; transform:translateY(-50%);
  pointer-events:none;
}


/* Download FAB (روابط الملفات المرسلة) */
.file-card.has-actions .fab-download{
  position:absolute; right:10px; bottom:10px; width:40px; height:40px; border-radius:50%;
  background:linear-gradient(135deg,#10b981,#059669); color:#fff;
  border:2px solid rgba(255,255,255,.9);
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 4px 12px rgba(16,185,129,0.3), inset 0 1px 0 rgba(255,255,255,0.2);
  transition: transform var(--transition), box-shadow var(--transition), background var(--transition);
  z-index:4;
}
.file-card.has-actions .fab-download:hover{
  transform: translateY(-2px) scale(1.05);
  background:linear-gradient(135deg,#059669,#047857);
  box-shadow:0 8px 20px rgba(16,185,129,0.4), inset 0 1px 0 rgba(255,255,255,0.2);
}

/* Placeholder loaders */
.loading-placeholder{
  padding: 20px; text-align:center; color: var(--text-light);
}

/* ================== Composer ================== */
.input-area{
  background: var(--surface-0); border-top:1px solid var(--border-color);
  padding: 14px; display:grid; gap:10px;
}
.input-tools{ display:flex; gap:8px; }
.tool-btn{
  width:40px; height:40px; border-radius:10px;
  background:#fff; border:1px solid var(--border-color); color: var(--text-light);
  transition: transform var(--transition), background var(--transition);
}
.tool-btn:hover{ transform: translateY(-1px); background: var(--surface-2); }
.voice-btn i{ color: var(--primary-600); }

.message-input-wrapper{ position:relative; }
#messageInput{
  width:100%; min-height:50px; max-height:160px; resize:none;
  border:1px solid var(--border-color); border-radius: 12px;
  padding: 12px 48px 12px 12px; font-size:.95rem; background:#fff; color:var(--text);
  transition: box-shadow var(--transition), border-color var(--transition);
}
#messageInput:focus{ border-color: var(--primary-400); box-shadow: 0 0 0 3px rgba(33,158,188,.22); outline:none; }
.send-btn{
  position:absolute; left:8px; bottom:8px; width:38px; height:38px; border-radius:10px;
  background: var(--primary-600); color:#fff; border:none; box-shadow: var(--shadow-sm);
  transition: transform var(--transition), background var(--transition);
}
.send-btn:hover{ background: var(--primary-700); transform: translateY(-1px); }

/* Pending attachments strip */
.uploaded-files.file-preview-container{
  display:grid; gap:12px; grid-template-columns: repeat(auto-fill, minmax(160px,1fr));
}

/* ================== Voice Recording Bar (inline) ================== */
.voice-recording{
  background: linear-gradient(135deg, var(--primary-600), var(--primary-400));
  color:#fff; border:1px solid rgba(255,255,255,.35); border-radius: 14px;
  padding: 8px 12px; display:flex; align-items:center; justify-content:space-between; gap:12px;
  box-shadow: var(--shadow-md);
}
.voice-recording .recording-visual{ display:flex; align-items:center; gap:10px; }
.voice-recording .recording-dot{
  width:10px; height:10px; background:#ff4d6d; border-radius:50%; box-shadow: 0 0 8px rgba(255,77,109,.6);
  animation: recPulse 1.4s infinite ease-in-out;
}
@keyframes recPulse{ 0%,100%{ transform:scale(1)} 50%{ transform:scale(1.22)} }
.recording-wave{ display:flex; align-items:flex-end; gap:2px; height:16px; }
.recording-wave span{ width:3px; background:rgba(255,255,255,.9); border-radius:2px; animation: wave 1s infinite ease-in-out; }
.recording-wave span:nth-child(1){animation-delay:0s} .recording-wave span:nth-child(2){animation-delay:.1s}
.recording-wave span:nth-child(3){animation-delay:.2s} .recording-wave span:nth-child(4){animation-delay:.3s} .recording-wave span:nth-child(5){animation-delay:.4s}
@keyframes wave{ 0%,100%{height:4px} 50%{height:16px} }
.recording-time{ font-weight:800; color:#fff; }

.btn-stop-voice, .btn-cancel-voice{
  width:40px; height:40px; border-radius:10px; border:none; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
}
.btn-stop-voice{ background:#fff; color: var(--primary-700); }
.btn-stop-voice:hover{ transform: translateY(-1px); }
.btn-cancel-voice{ background: rgba(239,68,68,.95); color:#fff; }
.btn-cancel-voice:hover{ transform: translateY(-1px); }

/* ================== Dark Mode ================== */
[data-theme="dark"] .service-header{
  border-color: rgba(255,255,255,.22);
}
[data-theme="dark"] .service-requests-container{
  --surface-0: #0b1220;
  --surface-2: #0e1418;
  --surface-3: #121a22;
  --border-color: #1f2a44;
  --text: #e5e7eb;
  --text-light:#9aa4b2;
}
[data-theme="dark"] .btn-icon,
[data-theme="dark"] .tool-btn{ background: #0b1220; border-color:#1f2a44; color:#9aa4b2; }
[data-theme="dark"] .file-card{ background:#0b1220; border-color:#1f2a44; }
[data-theme="dark"] #messageInput{ background:#0b1220; }

/* ================== Responsive ================== */
@media (max-width: 1200px){ .message-content{ max-width: 76%; } }
@media (max-width: 992px){ .message-content{ max-width: 86%; } }
@media (max-width: 768px){
  .message-content{ max-width: 92%; }
  .requests-list{ max-height: none; }
}

/* ================== Accessibility / Reduced Motion ================== */
@media (prefers-reduced-motion: reduce){
  .service-header::before, .recording-wave span{ animation: none !important; }
}


/* ===== Responsive Video Bubble ===== */
/* العرض الافتراضي على الشاشات الكبيرة */
.video-bubble{
  /* ✅ غيّر هذه القيمة لو بدك (مثلاً 600px) */
  --video-bubble-width: 500px; /* ← عدّل هنا */
  width: min(100%, var(--video-bubble-width));
  margin: 6px 0;
}

/* نحافظ على الأسبكت رشيو من داخل المعاينة */
.video-bubble .vbv-inner{
  width: 100%;
  /* في JS أصلاً فيها aspect-ratio:16/9؛ نكررها هنا فقط لو بدك تتحكم فيها من CSS */
  aspect-ratio: 16/9; /* تقدر تغيّرها مثلاً إلى 4/3 */
}

/* ✅ على الهواتف: صغّر العرض إلى 200px */
@media (max-width: 640px){
  .video-bubble{
    --video-bubble-width: 200px; /* ← عدّل هنا لحجم الموبايل */
  }
}

/* افتراضي: أخفِ الـDDL على الشاشات الكبيرة */
.requests-ddl-mobile { display: none; }

/* على الهواتف: أظهر الـDDL وأخفِ قائمة الطلبات التقليدية */
@media (max-width: 768px) {
  .requests-ddl-mobile { 
    display: block; 
    padding: 12px 14px; 
    border-bottom: 1px solid var(--border-color);
    background: var(--surface-1);
  }
  .requests-sidebar .sidebar-header .filter-tabs { display: none; }   /* تبويبات الحالة تُخفى (اختياري) */
  .requests-sidebar .requests-list { display: none; }                 /* قائمة البطاقات تُخفى */
  .requests-sidebar .svc-pagination { display: none; }                /* ترقيم الصفحات يُخفى */
}
/* افتراضي: مخفي */
.svc-pagination-mobile { display: none; }

/* على الهواتف: أظهر الـDDL وترقيمها وأخفِ قائمة البطاقات وترقيم الديسكتوب */
@media (max-width: 768px) {
  .requests-ddl-mobile { 
    display: block; 
    padding: 12px 14px; 
    border-bottom: 1px solid var(--border-color);
    background: var(--surface-1);
  }
  .requests-sidebar .requests-list { display: none; }
  .requests-sidebar .svc-pagination { display: none; }

  .svc-pagination-mobile { 
    display: flex; 
    justify-content: center; 
  }
}





</style>


<!-- (اختياري) مزامنة aria-selected للتبويبات مع نفس منطقك الحالي -->
