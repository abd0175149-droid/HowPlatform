<!-- app/Views/admins/pages/service_requests_new.php -->
<div class="service-requests-new-container"
     data-current-role="<?= (int) (
       session('ROLE')
       ?? session('role')
       ?? session('account_type_id')
       ?? session('account_type')
       ?? session('role_id')
       ?? 0
     ) ?>"
     data-current-user-id="<?= (int) (session('user_id') ?? 0) ?>">
  
  <!-- رأس الصفحة -->
  <div class="sr-new-header">
    <div class="header-title">
      <h1><i class="fas fa-comments"></i> طلبات الخدمة - جديد</h1>
      <p>إدارة شاملة لطلبات الخدمة مع نظام دردشة متكامل</p>
    </div>
    <div class="header-actions">
      <button class="btn btn-secondary" id="refreshAllBtn">
        <i class="fas fa-sync-alt"></i> تحديث
      </button>
    </div>
  </div>

  <!-- المحتوى الرئيسي -->
  <div class="sr-new-content">
    <!-- القائمة الجانبية للطلبات -->
    <div class="sr-new-sidebar">
      <div class="sidebar-header">
        <h3><i class="fas fa-list"></i> قائمة الطلبات</h3>
        <div class="filter-tabs">
          <button class="tab-btn active" data-filter="all">
            الكل <span class="count" id="countAll">0</span>
          </button>
          <button class="tab-btn" data-filter="me">
            طلباتي <span class="count" id="countMe">0</span>
          </button>
          <button class="tab-btn" data-filter="pending">
            انتظار <span class="count" id="countPending">0</span>
          </button>
          <button class="tab-btn" data-filter="in_progress">
            معالجة <span class="count" id="countProgress">0</span>
          </button>
          <button class="tab-btn" data-filter="unassigned">
            غير مكلفة <span class="count" id="countUnassigned">0</span>
          </button>
        </div>
      </div>

      <div class="requests-list" id="requestsList">
        <div class="loading-placeholder">
          <i class="fas fa-spinner fa-spin"></i>
          <p>جاري تحميل الطلبات...</p>
        </div>
      </div>
    </div>

    <!-- المنطقة الرئيسية: الجدول والدردشة -->
    <div class="sr-new-main">
      <!-- عرض الجدول -->
      <div class="table-view" id="tableView">
        <div class="table-header">
          <h3><i class="fas fa-table"></i> جدول الطلبات</h3>
          <div class="header-controls">
            <div class="table-filters">
              <select id="tableStatusFilter" class="filter-select">
                <option value="">جميع الحالات</option>
                <option value="pending">في الانتظار</option>
                <option value="in_progress">قيد المعالجة</option>
                <option value="resolved">تم الحل</option>
                <option value="closed">مغلق</option>
                <option value="cancelled">ملغي</option>
              </select>
              <select id="tableAssignFilter" class="filter-select">
                <option value="all">جميع الطلبات</option>
                <option value="me">طلباتي</option>
                <option value="unassigned">غير مكلفة</option>
              </select>
            </div>
            <div class="view-toggle">
              <button class="view-btn active" data-view="table" title="عرض الجدول">
                <i class="fas fa-table"></i>
              </button>
              <button class="view-btn" data-view="chat" title="عرض الدردشة">
                <i class="fas fa-comments"></i>
              </button>
            </div>
          </div>
        </div>
        
        <div class="table-content-wrapper">
          <table class="requests-table" id="requestsTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>الموضوع</th>
                <th>الطالب</th>
                <th>الحالة</th>
                <th>مكلف إلى</th>
                <th>التاريخ</th>
                <th>الإجراءات</th>
              </tr>
            </thead>
            <tbody id="requestsTableBody">
              <tr>
                <td colspan="7" class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>لا توجد طلبات لعرضها</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- عرض الدردشة -->
      <div class="chat-view" id="chatView" style="display: none;">
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
              <button class="btn-icon" id="refreshChatBtn" title="تحديث">
                <i class="fas fa-sync-alt"></i>
              </button>
              <button class="btn-icon" id="toggleDetailsBtn" title="التفاصيل">
                <i class="fas fa-info-circle"></i>
              </button>
            </div>
          </div>

          <div class="request-details" id="requestDetails" style="display:none">
            <div class="detail-item">
              <strong><i class="fas fa-user"></i> طالب الخدمة:</strong>
              <div id="requestRequester">
                <p id="requesterName"></p>
                <p id="requesterEmail" class="detail-sub"></p>
                <p id="requesterPhone" class="detail-sub"></p>
              </div>
            </div>
            <div class="detail-item">
              <strong><i class="fas fa-graduation-cap"></i> معلومات أكاديمية:</strong>
              <div id="requesterAcademic">
                <p id="requesterUniversity" class="detail-sub"></p>
                <p id="requesterSpecialty" class="detail-sub"></p>
                <p id="requesterAcademicYear" class="detail-sub"></p>
              </div>
            </div>
            <div class="detail-item">
              <strong><i class="fas fa-align-left"></i> الوصف:</strong>
              <p id="requestDescription"></p>
            </div>
            <div class="detail-item">
              <strong><i class="fas fa-user-tag"></i> التكليف:</strong>
              <div id="assignSection">
                <select id="assignSelect" class="assign-select">
                  <option value="">اختر أدمن...</option>
                </select>
                <button class="btn-assign" id="assignBtn" style="display:none">تكليف</button>
              </div>
            </div>
            <div class="detail-item">
              <strong><i class="fas fa-flag"></i> تحديث الحالة:</strong>
              <select id="quickStatusChange" class="status-select">
                <option value="">اختر حالة جديدة...</option>
                <option value="pending">في الانتظار</option>
                <option value="in_progress">قيد المعالجة</option>
                <option value="resolved">تم الحل</option>
                <option value="closed">مغلق</option>
                <option value="cancelled">ملغي</option>
              </select>
            </div>
          </div>

          <!-- منطقة الرسائل -->
          <div class="messages-area" id="messagesArea">
            <!-- يتم الحقن عبر JS -->
          </div>

          <!-- منطقة الإدخال -->
          <div class="input-area">
            <div class="input-tools">
              <button class="tool-btn" id="attachBtn" title="إرفاق ملف">
                <i class="fas fa-paperclip"></i>
              </button>
              <button class="tool-btn voice-btn" id="voiceBtn" title="تسجيل صوتي">
                <i class="fas fa-microphone"></i>
              </button>
              <input type="file" id="chatFileInput" style="display: none;" multiple>
            </div>
            
            <div class="voice-recording" id="recordingIndicator" style="display: none;">
              <div class="recording-visual">
                <span class="recording-dot"></span>
                <div class="recording-wave">
                  <span></span>
                  <span></span>
                  <span></span>
                  <span></span>
                  <span></span>
                </div>
                <span class="recording-time" id="recordingTimer">00:00</span>
              </div>
              <div class="recording-actions">
                <button class="btn-stop-voice" id="stopRecordingBtn" title="إيقاف وإرسال">
                  <i class="fas fa-paper-plane"></i>
                </button>
                <button class="btn-cancel-voice" id="cancelRecordingBtn" title="إلغاء">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>

            <div class="message-input-wrapper">
              <textarea id="messageInput" placeholder="اكتب رسالتك..." rows="1"></textarea>
              <button class="send-btn" id="sendBtn">
                <i class="fas fa-paper-plane"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

