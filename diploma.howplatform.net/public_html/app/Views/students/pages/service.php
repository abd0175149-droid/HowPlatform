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
            <p>تابع طلباتك وتواصل مع فريق الدعم</p>
        </div>

        <!-- زر طلب جديد: بلا inline JS -->
        <button  id="svcNewRequestBtn" class="btn-service-request" onclick="ServiceRequestModal.open()">
                    <i class="fas fa-headset"></i>
                    <span>طلب خدمة متخصصة</span>
                </button>
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
                    <div class="request-info">
                        <h3 id="chatRequestTitle">عنوان الطلب</h3>
                        <div class="request-meta">
                            <span class="status-badge" id="chatRequestStatus">pending</span>
                            <span class="date" id="chatRequestDate">التاريخ</span>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <button class="btn-icon" id="btnToggleRequestDetails" title="التفاصيل">
                            <i class="fas fa-info-circle"></i>
                        </button>
                        <button class="btn-icon btn-cancel-request" id="cancelRequestBtn" title="إلغاء الطلب">
                            <i class="fas fa-times-circle"></i>
                        </button>
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
                        <button class="tool-btn" id="btnAttachFile" title="إرفاق ملف">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <button class="tool-btn voice-btn" id="voiceBtn" title="تسجيل صوتي">
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
                                <button class="btn-stop-voice" id="btnStopRecording" title="إيقاف وإرسال">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                                <button class="btn-cancel-voice" id="btnCancelRecording" title="إلغاء">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="chatPendingAttachments" class="uploaded-files file-preview-container"></div>

                    <div class="message-input-wrapper">
                        <textarea id="messageInput" placeholder="اكتب رسالتك..." rows="1"></textarea>
                        <button class="send-btn" id="btnSendMessage">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div><!-- /chat-area -->
    </div><!-- /service-content -->
</div>

<!-- مودال إنشاء طلب جديد -->
<?= view('students/partials/modals/service_request_modal') ?>


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

  background: var(--background);
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
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  margin-top: 8px;
}
.file-card{
  position:relative; height:190px;
  background:#fff; color:inherit; text-decoration:none;
  border:1px solid var(--border-color); border-radius:12px; padding:10px;
  display:flex; flex-direction:column; gap:8px; overflow:hidden;
  box-shadow: var(--shadow-sm);
  transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition);
}
.file-card:hover{ transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: var(--primary-200); }
.file-card .thumb{ width:100%; aspect-ratio:16/10; background:#f3f6f9; border-radius:10px; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.file-card .img-thumb img{ width:100%; height:100%; object-fit:contain; }
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


/* Cards */
.file-card{display:inline-flex;flex-direction:column;gap:6px;background:#fff;border:1px solid #e8e8ef;border-radius:12px;padding:8px;box-shadow:0 1px 3px rgba(0,0,0,.06);max-width:100%;}
.file-card .thumb{position:relative;border-radius:10px;overflow:hidden;background:#f3f4f6;}
.file-card .meta{font-size:13px;color:#374151;}
.file-card .meta .name{font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.file-card .meta .sub{color:#6b7280;font-weight:600;}
.img-card-button{border:0;background:transparent;padding:0;cursor:pointer;width:100%;text-align:inherit;}
.video-preview{background-size:cover;background-position:center;}
.video-glass-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.15);border:0;color:#fff;cursor:pointer;}
.video-player-shell[hidden]{display:none !important;}
.has-warning .badge.warn{background:#f59e0b;color:#111;padding:2px 6px;border-radius:8px;font-size:11px;font-weight:800;}
/* Audio bubble */
.audio-bubble{display:grid;grid-template-columns:40px minmax(120px,1fr) auto;gap:10px;align-items:center;padding:8px;border:1px solid #e8e8ef;border-radius:12px;background:#fff;}
.audio-bubble.voice-bubble{grid-template-columns:36px 1fr auto;}
.audio-play{width:36px;height:36px;border:1px solid #e8e8ef;border-radius:10px;background:#fff;color:#1b81a0;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;}
.audio-visual{display:inline-flex;gap:3px;align-items:end;height:16px;}
.audio-visual span{width:3px;height:8px;background:currentColor;opacity:.6;animation:vu 1s infinite ease-in-out;}
@keyframes vu{0%,100%{height:8px}50%{height:16px}}
.audio-slider{appearance:none;width:100%;height:6px;border-radius:6px;background:#eef1f6;border:0;outline:0;}
.audio-name{font-size:13px;font-weight:700;}
.audio-sub{font-size:12px;color:#6b7280;}
.audio-footer{display:flex;gap:10px;align-items:center;justify-content:flex-end;font-size:12px;color:#6b7280;}
.audio-footer .audio-speed{border:1px solid #e8e8ef;background:#fff;border-radius:8px;padding:2px 8px;font-weight:800;cursor:pointer;}

/* ================== Dark Mode — Colors Only ================== */
/* ملاحظة: لا تغييرات هيكلية — ألوان/توكنز فقط مع الحفاظ على الهوية (أزرق/برتقالي) */
[data-theme="dark"] .service-requests-container{
  /* سطوح وخلفيات متناغمة مع الثيم الداكن العام */
  --background: #0e141a;
  --surface-0: #0f1720;
  --surface-1: #121c25;
  --surface-2: #18232d;
  --surface-3: #0f1720;

  /* نص وحدود وظلال */
  --text: #cfe0e7;
  --text-light:#a6c4d2;
  --border-color: rgba(255,255,255,.08);

  /* سلّم الأزرق (Primary) على الدارك */
  --primary-50:  #0a2631;
  --primary-200: #11465a;
  --primary-400: #1e8eab;
  --primary-500: #2bb3d3; /* اللون الرئيسي */
  --primary-600: #21a1be;
  --primary-700: #1a8aa6;

  --primary: var(--primary-500);
  --primary-dark: var(--primary-600);
  --primary-darker: var(--primary-700);

  /* حالات */
  --shadow: 0 1px 3px rgba(0,0,0,.55);
}

/* الهيدر: يبقى الجradient لكن بقيم primary الداكنة الجديدة */
[data-theme="dark"] .service-header{
  border: 1px solid rgba(255,255,255,.18);
  color:#fff;
}

/* تلميح الترقية على الدارك */
[data-theme="dark"] .svc-upgrade-hint .alert{
  border-color: rgba(255,255,255,.12);
  background: rgba(253,158,2,.08); /* amber لطيف */
  color: #ffd08a;
}

/* الحقول والأزرار والأيقونات */
[data-theme="dark"] .btn-service-request,
[data-theme="dark"] .btn-new-request{
  --_bg: var(--surface-0);
  --_ink: var(--text);
  background: var(--surface-0);
  color: var(--text);
  border-color: var(--border-color);
}

[data-theme="dark"] .btn-icon,
[data-theme="dark"] .tool-btn{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text-light);
}
[data-theme="dark"] .btn-icon:hover,
[data-theme="dark"] .tool-btn:hover{
  background: var(--surface-2);
}

/* الشريط الجانبي + البحث */
[data-theme="dark"] .requests-sidebar,
[data-theme="dark"] .svc-top-meta,
[data-theme="dark"] .chat-container,
[data-theme="dark"] .request-details,
[data-theme="dark"] .chat-header{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}

[data-theme="dark"] .input-group .form-control,
[data-theme="dark"] .input-group .input-group-text{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}

/* التبويبات (Chips) */
[data-theme="dark"] .filter-tabs .tab-btn{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text-light);
}
[data-theme="dark"] .filter-tabs .tab-btn:hover{
  background: var(--primary-50);
  border-color: var(--primary-200);
  color: var(--primary-400);
}
[data-theme="dark"] .filter-tabs .tab-btn.active{
  background: var(--primary-600);
  color:#fff;
  border-color: transparent;
}
[data-theme="dark"] .filter-tabs .tab-btn .count{
  background: rgba(255,255,255,.08);
}
[data-theme="dark"] .filter-tabs .tab-btn.active .count{
  background: rgba(255,255,255,.22);
}

/* قائمة الطلبات */
[data-theme="dark"] .request-item{ border-color: var(--border-color); }
[data-theme="dark"] .request-item:hover{ background: var(--surface-2); }
[data-theme="dark"] .request-item.active{
  background: linear-gradient(90deg, rgba(43,179,211,.10), transparent);
}

/* شارات الحالة */
[data-theme="dark"] .status-badge{
  background: var(--surface-2);
  color: var(--text-light);
}
[data-theme="dark"] .status-badge.pending{
  background: #2a2204;  /* ~ warning-100 */
  color: #f1c40f;       /* warning-500 */
}
[data-theme="dark"] .status-badge.in_progress{
  background: var(--primary-50);
  color: var(--primary-600);
}
[data-theme="dark"] .status-badge.resolved{
  background: #0f2d1f;  /* success-100 */
  color: #2ecc71;       /* success-500 */
}
[data-theme="dark"] .status-badge.cancelled{
  background: #2e1512;  /* error-100 */
  color: #e74c3c;       /* error-500 */
}

/* منطقة الرسائل */
[data-theme="dark"] #messagesWrapper{
  background: var(--surface-2);
  color: var(--text);
}

/* الفقاعات */
[data-theme="dark"] .message-content{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}
/* المرسلة تستخدم درجات الأزرق على الدارك */
[data-theme="dark"] .sentmsg .message-content{
  background: var(--primary-50);
  border-color: var(--primary-200);
}

/* النصوص الثانوية */
[data-theme="dark"] .sender-name{ color: var(--text); }
[data-theme="dark"] .message-time{ color: var(--text-light); }
[data-theme="dark"] .message-text{ color: var(--text); }

/* بطاقات الملفات */
[data-theme="dark"] .file-card{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}
[data-theme="dark"] .file-card:hover{
  border-color: var(--primary-200);
  box-shadow: 0 6px 18px rgba(0,0,0,.45);
}
[data-theme="dark"] .file-card .thumb{ background:#12212a; }
[data-theme="dark"] .file-card .icon-thumb i{ color: var(--primary-600); }
[data-theme="dark"] .file-card .meta .name{ color: var(--primary-darker); }
[data-theme="dark"] .file-card .meta .sub{ color: var(--text-light); }

/* زر التحميل العائم يبقى كما هو (تباين ممتاز على الدارك) */

/* المحرر/الإرسال */
[data-theme="dark"] .input-area{
  background: var(--surface-0);
  border-color: var(--border-color);
}
[data-theme="dark"] #messageInput{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}
[data-theme="dark"] #messageInput:focus{
  border-color: var(--primary-400);
  box-shadow: var(--focus-ring);  /* يستخدم حلقة التركيز العامة */
}
[data-theme="dark"] .send-btn{
  background: var(--primary-600);
  color:#fff;
}
[data-theme="dark"] .send-btn:hover{ background: var(--primary-700); }

/* التسجيل الصوتي */
[data-theme="dark"] .voice-recording{
  background: linear-gradient(135deg, var(--primary-600), var(--primary-400));
  border-color: rgba(255,255,255,.22);
  color:#fff;
}
[data-theme="dark"] .btn-stop-voice{
  background: var(--surface-0);
  color: var(--primary-700);
}
[data-theme="dark"] .btn-cancel-voice{
  background: rgba(239,68,68,.95);
  color:#fff;
}

/* الفقاعة الصوتية/العادية */
[data-theme="dark"] .audio-bubble{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}
[data-theme="dark"] .audio-play{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--primary-500);
}
[data-theme="dark"] .audio-footer{ color: var(--text-light); }
[data-theme="dark"] .audio-footer .audio-speed{
  background: var(--surface-0);
  border-color: var(--border-color);
  color: var(--text);
}

/* حقول DDL/الموبايل على الدارك */
@media (max-width: 768px) {
  [data-theme="dark"] .requests-ddl-mobile{
    background: var(--surface-1);
    border-bottom: 1px solid var(--border-color);
  }
}

/* فيديو بابل: لا تغيير هيكلي — فقط تأكد من تباين الأزرار الزجاجية */
[data-theme="dark"] .video-glass-play{
  background: rgba(0,0,0,.25);
  color:#fff;
}

/* تحسين بسيط للظل على الدارك لبعض البطاقات */
[data-theme="dark"] .card,
[data-theme="dark"] .chat-container,
[data-theme="dark"] .requests-sidebar{
  box-shadow: 0 8px 24px rgba(0,0,0,.45);
}

</style>


<!-- (اختياري) مزامنة aria-selected للتبويبات مع نفس منطقك الحالي -->

