<!-- ✅ Partial فقط — يتم حقنه عبر admin_router.js -->
<section class="upg-page" id="serviceRequestsPage">

  <!-- ===== Header (يحافظ على IDs ويضيف ستايل موحّد) ===== -->
  <div class="service-requests-header ap-head">
    <div class="ap-head__title">
      <div class="ap-head__icon"><i class="fas fa-headset"></i></div>
      <div class="ap-head__text">
        <h2>إدارة طلبات المراسلة</h2>
        <div class="ap-head__sub">لوحة موحّدة لإدارة الطلبات، التكليف، والحالات</div>
      </div>
    </div>

    <div class="ap-head__actions">
      <button class="btn btn-ghost" id="refreshBtn" type="button">
        <i class="fas fa-sync-alt"></i> تحديث
      </button>
      <a href="#" class="btn btn-primary" data-section="service_chat">
        <i class="fas fa-comments"></i> المحادثات
      </a>
    </div>
  </div>

  <!-- ===== كارد الإحصائيات (يحافظ على نفس IDs للأرقام) ===== -->
  <div class="upg-card stats-card">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
          <h3 id="totalPending">0</h3>
          <p>في الانتظار</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon progress"><i class="fas fa-spinner"></i></div>
        <div class="stat-content">
          <h3 id="totalInProgress">0</h3>
          <p>قيد المعالجة</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon resolved"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content">
          <h3 id="totalResolved">0</h3>
          <p>تم الحل</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon cancelled"><i class="fas fa-times-circle"></i></div>
        <div class="stat-content">
          <h3 id="totalCancelled">0</h3>
          <p>ملغي</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon unassigned"><i class="fas fa-user-times"></i></div>
        <div class="stat-content">
          <h3 id="totalUnassigned">0</h3>
          <p>غير مكلفة</p>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== الفلاتر (نفس IDs + ستايل موحّد) ===== -->
  <div class="upg-card filter-section">
    <div class="filter-grid">
      <div class="filter-group">
        <label for="statusFilter">الحالة</label>
        <select class="filter-select" id="statusFilter">
          <option value="">جميع الحالات</option>
          <option value="pending">في الانتظار</option>
          <option value="in_progress">قيد المعالجة</option>
          <option value="resolved">تم الحل</option>
          <option value="closed">مغلق</option>
          <option value="cancelled">ملغي</option>
        </select>
      </div>

      <div class="filter-group">
        <label for="assignmentFilter">التكليف</label>
        <select class="filter-select" id="assignmentFilter">
          <option value="">جميع الطلبات</option>
          <option value="me">المكلفة لي</option>
          <option value="unassigned">غير مكلفة</option>
        </select>
      </div>

      <div class="filter-group">
        <label for="searchInput">البحث</label>
        <div class="search-wrapper">
          <input type="text" id="searchInput" class="search-input" placeholder="البحث في الموضوع أو الوصف...">
        </div>
      </div>
    </div>
  </div>

  <!-- ===== الجدول داخل كارد موحّد (يحافظ على IDs) ===== -->
  <div class="upg-card requests-table-container">
    <div class="table-content">
      <table class="requests-table" id="requestsTable">
        <thead>
          <tr>
            <th>#</th>
            <th>المستخدم</th>
            <th>موضوع الطلب</th>
            <th>تفاصيل الطلب</th>
            <th>المساق</th>
            <th>الأدمن المسؤول</th>
            <th>الحالة</th>
            <th>التاريخ</th>
            <th>الإجراءات</th>
          </tr>
        </thead>
        <tbody id="requestsTableBody">
          <tr>
            <td colspan="9" class="loading-row">
              <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <span>جاري تحميل الطلبات...</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- موضع الترقيم (نفس الـ ID) -->
      <div class="pagination" id="requestsPagination"></div>
    </div>
  </div>

  <div class="footer">
    <p>© <?= date('Y') ?> منصة كيف التعليمية - جميع الحقوق محفوظة</p>
  </div>
</section>

<!-- ===== مودال تغيير الحالة (بدون أي تغيير على IDs أو منطق الإغلاق) ===== -->
<div class="modal-overlay" id="statusModalOverlay">
  <div class="sr-modal" role="dialog" aria-modal="true" aria-labelledby="statusModalTitle">
    <div class="modal-header">
      <h2 id="statusModalTitle">
        <i class="fas fa-edit"></i>
        <span>تغيير حالة الطلب</span>
      </h2>
      <button class="close-modal" id="closeStatusModal" type="button" aria-label="إغلاق">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="modal-body">
      <div class="form-group">
        <label for="newStatus">الحالة الجديدة</label>
        <select class="form-control" id="newStatus">
          <option value="pending">في الانتظار</option>
          <option value="in_progress">قيد المعالجة</option>
          <option value="resolved">تم الحل</option>
          <option value="closed">مغلق</option>
          <option value="cancelled">ملغي</option>
        </select>
      </div>

      <div class="current-request-info" id="currentRequestInfo"></div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-neutral" id="cancelStatusChange" type="button">
        <i class="fas fa-times"></i> إلغاء
      </button>
      <button class="btn btn-primary" id="confirmStatusChange" type="button">
        <i class="fas fa-save"></i> تأكيد التحديث
      </button>
    </div>
  </div>
</div>

<!-- ===== مودال التكليف (نفس IDs/منطق) ===== -->
<div class="modal-overlay" id="assignModalOverlay">
  <div class="sr-modal" role="dialog" aria-modal="true" aria-labelledby="assignModalTitle">
    <div class="modal-header">
      <h2 id="assignModalTitle">
        <i class="fas fa-user-plus"></i>
        <span>تكليف الطلب</span>
      </h2>
      <button class="close-modal" id="closeAssignModal" type="button" aria-label="إغلاق">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="modal-body">
      <div class="form-group">
        <label for="assignToAdmin">اختيار الأدمن</label>
        <select class="form-control" id="assignToAdmin">
          <option value="">اختر أدمن...</option>
        </select>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-neutral" id="cancelAssign" type="button">
        <i class="fas fa-times"></i> إلغاء
      </button>
      <button class="btn btn-primary" id="confirmAssign" type="button">
        <i class="fas fa-check"></i> تكليف
      </button>
    </div>
  </div>
</div>

<style>
    /* =========================================================
   Admin — Service Requests (Unified • Tokens-based)
   يحافظ على نفس الـ IDs والمنطق، ويطبّق روح التصميم الجديد
   ========================================================= */

/* ===== صفحة عامة ===== */
.upg-page{
  /* احترام التوكنز العامة */
  background: var(--background);
  padding: 24px 16px;
  display: grid;
  gap: 18px;
}

/* ===== Header (خفيف ومتطابق روح المساقات) ===== */
.ap-head{
  background: linear-gradient(135deg, var(--primary-500), var(--primary-700));
  color:#fff;
  border-radius: var(--border-radius);
  box-shadow: var(--shadow-lg);
  padding: 18px;
  display:flex; align-items:center; justify-content:space-between; gap:12px;
  position:relative; overflow:hidden;
}
.ap-head__title{ display:flex; align-items:center; gap:12px; position:relative; z-index:1; }
.ap-head__icon{
  width:42px; height:42px; display:grid; place-items:center; border-radius:12px;
  background: rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.28);
}
.ap-head__icon i{ font-size:1.1rem; color:#fff; }
.ap-head__text h2{ margin:0; font-size:1.28rem; font-weight:800; }
.ap-head__sub{ opacity:.9; font-size:.9rem; }
.ap-head__actions{ display:flex; gap:10px; position:relative; z-index:1; }

/* ===== Buttons (موحّدة) ===== */
.btn{
  --_bg:#fff; --_bg-h:#f7f8fb; --_ink:var(--text);
  background:var(--_bg); color:var(--_ink); border:1px solid var(--border-color);
  padding:10px 14px; border-radius:12px; cursor:pointer; font-weight:800;
  transition: background var(--transition), transform var(--transition), box-shadow var(--transition), border-color var(--transition);
  display:inline-flex; align-items:center; gap:8px; text-decoration:none;
}
.btn:hover{ background:var(--_bg-h); transform: translateY(-1px); }
.btn:disabled{ opacity:.6; cursor:not-allowed; transform:none; }
.btn-ghost{ background:#fff; color:var(--text); border:1px solid var(--border-color); }
.btn-primary{
  --_bg: var(--primary-500);
  --_bg-h: var(--primary-600);
  --_ink: #fff;
  background: var(--_bg); color: var(--_ink);
  border: 1px solid transparent;
  box-shadow: 0 6px 18px rgba(33,158,188,.20);
}
.btn-primary:hover{ background: var(--_bg-h); box-shadow: 0 10px 28px rgba(33,158,188,.28); }
.btn-neutral{ background: var(--surface-0); color: var(--text-strong); border:1px solid var(--border-color); }

/* ===== Card عام ===== */
.upg-card{
  background: var(--surface-0);
  border:1px solid var(--border-color);
  border-radius: var(--border-radius);
  box-shadow: var(--shadow-sm);
}

/* ===== إحصائيات — كارد نظيف ===== */
.stats-card{ padding: 12px; }
.stats-grid{
  display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr));
  gap:12px;
}
.stat-card{
  display:flex; align-items:center; gap:12px;
  background: var(--surface-0); border:1px solid var(--border-color);
  border-radius: 14px; padding: 14px; box-shadow: var(--shadow-sm);
  transition: var(--transition);
}
.stat-card:hover{ transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: var(--primary-200); }
.stat-icon{
  width:48px; height:48px; border-radius:50%; display:grid; place-items:center; color:#fff; flex-shrink:0;
}
.stat-icon.pending{ background: var(--warning-500); }
.stat-icon.progress{ background: var(--primary-600); }
.stat-icon.resolved{ background: var(--success-500); }
.stat-icon.cancelled{ background: var(--error-500); }
.stat-icon.unassigned{ background: var(--neutral-500); }
.stat-content h3{ margin:0; font-size:1.4rem; font-weight:800; color:var(--text-strong); }
.stat-content p{ margin:4px 0 0; color: var(--text-muted); font-weight:700; }

/* ===== الفلاتر داخل كارد ===== */
.filter-section{ padding: 16px; }
.filter-grid{
  display:grid; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap:12px;
}
.filter-group{ display:flex; flex-direction:column; gap:6px; }
.filter-group label{ font-weight:800; color: var(--text-strong); }
.filter-select,.search-input{
  padding:10px 12px; border-radius:12px; border:1px solid var(--border-color);
  background: var(--surface-0); color: var(--text-strong); min-height:42px; transition: var(--transition);
}
.filter-select:focus,.search-input:focus{
  outline:none; border-color: var(--primary-400); box-shadow: 0 0 0 3px rgba(33,158,188,.18);
}

/* ===== الجدول داخل كارد ===== */
.requests-table-container{ padding: 12px; }
.table-content{ overflow:auto; }
.requests-table{ width:100%; border-collapse:collapse; }
.requests-table thead th{
  background: var(--primary-500); color:#fff; padding:8px 6px; text-align:center; font-weight:800;
  border:1px solid var(--primary-600); position: sticky; top: 0; z-index: 1;
}
.requests-table td{
  padding:12px; border-bottom:1px solid var(--border-color); color:var(--text-strong); text-align:center;
}
.requests-table tbody tr:nth-child(even){ background: var(--primary-50); }
.requests-table tbody tr:hover{ background: rgba(33,158,188,.10); }
.loading-row .loading-spinner{ display:flex; align-items:center; justify-content:center; gap:10px; color: var(--text-muted); padding: 24px; }

/* شارات الحالة */
.status-badge{
  display:inline-flex; align-items:center; gap:8px;
  padding:6px 12px; border-radius:999px; font-weight:800; font-size:.85rem; border:1px solid transparent;
}
.status-badge.pending{ background: var(--warning-soft); color: var(--warning-600); border-color: var(--warning-border); }
.status-badge.in_progress{ background: rgba(33,158,188,.10); color: var(--primary-700); border-color: var(--primary-200); }
.status-badge.resolved{ background: var(--success-soft); color: var(--success-600); border-color: var(--success-border); }
.status-badge.closed{ background: var(--neutral-100); color: var(--neutral-800); border-color: var(--neutral-300); }
.status-badge.cancelled{ background: var(--error-soft); color: var(--error-600); border-color: var(--error-border); }

/* أزرار الإجراءات */
.row-actions{ display:flex; gap:8px; align-items:center; justify-content:center; flex-wrap:nowrap; }
.action-btn{
  width:36px; height:36px; border-radius:10px; border:1px solid var(--border-color);
  background: var(--surface-0); display:inline-flex; align-items:center; justify-content:center; cursor:pointer;
  transition: .18s ease; color: var(--text-strong);
}
.action-btn:hover{ background: var(--primary-500); color:#fff; border-color: var(--primary-500); }

/* ترقيم */
.pagination{ display:flex; justify-content:center; align-items:center; gap:8px; margin-top: 12px; }
.pagination-btn{
  width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center;
  background:#fff; color: var(--primary-600); border:1px solid var(--border-color); cursor:pointer; font-weight:800;
  transition: background var(--transition), color var(--transition), border-color var(--transition), transform var(--transition);
}
.pagination-btn:hover:not(.disabled){ background: var(--primary-500); color:#fff; border-color: var(--primary-500); transform: translateY(-1px); }
.pagination-btn.active{ background: var(--primary-500); color:#fff; border-color: var(--primary-500); }
.pagination-btn.disabled{ opacity:.5; cursor:not-allowed; }
.pagination-info{ margin: 0 10px; color: var(--text-muted); font-weight:700; }

/* ===== مودالات (منطق الإغلاق كما هو — تبقى مخفية حتى إضافة .active) ===== */
.modal-overlay{
  position: fixed; inset:0;
  display:flex; align-items:center; justify-content:center;
  z-index: 9999;
  background: rgba(0,0,0,.5); backdrop-filter: blur(2px);
  opacity:0; visibility:hidden; transition: var(--transition);
}
.modal-overlay.active{ opacity:1; visibility:visible; }
.sr-modal{
  width: min(720px, 96vw); max-height: 92vh; overflow:auto;
  background: var(--surface-0); border:1px solid var(--border-color);
  border-radius:16px; box-shadow: var(--shadow-lg);
  transform: translateY(18px); opacity:.0; transition: var(--transition);
}
.modal-overlay.active .sr-modal{ transform: translateY(0); opacity:1; }

/* رأس/جسم/فوتر المودال */
.modal-header{
  position:sticky; top:0; z-index:1;
  background: linear-gradient(135deg, var(--primary-500), var(--primary-700));
  color:#fff; display:flex; align-items:center; justify-content:space-between; gap:10px;
  padding:12px 14px; border-radius:16px 16px 0 0;
}
.modal-header h2{ display:inline-flex; gap:8px; align-items:center; margin:0; font-weight:800; }
.modal-header h2 i{ color: var(--accent-500); }
.close-modal{
  background:#fff; border:1px solid var(--border-color); border-radius:10px; color: var(--text-muted);
  width:36px; height:36px; display:grid; place-items:center; cursor:pointer;
  transition: transform .18s ease, background var(--transition);
}
.close-modal:hover{ background:#f5f7fb; transform: rotate(90deg); }
.modal-body{ padding:14px; display:grid; gap:12px; }
.modal-footer{ border-top:1px solid var(--border-color); padding:12px 14px; display:flex; justify-content:flex-end; gap:8px; }

.form-group{ display:flex; flex-direction:column; gap:6px; }
.form-control{
  padding:10px 12px; border-radius:12px; border:1px solid var(--border-color);
  background: var(--surface-0); color: var(--text-strong); min-height:42px; transition: var(--transition);
}
.form-control:focus{ outline:none; border-color: var(--primary-400); box-shadow: 0 0 0 3px rgba(33,158,188,.18); }
.current-request-info{
  margin-top: 8px; padding: 12px;
  background: var(--surface-1); border:1px solid var(--border-color);
  border-radius:12px; color: var(--text-strong);
}

/* ===== Responsive تحسينات ===== */
@media (max-width: 992px){
  .ap-head{ padding: 16px 14px; gap: 12px; }
  .ap-head__text h2{ font-size: 1.2rem; }
  .ap-head__sub{ font-size: .9rem; }
  .ap-head__actions{ gap: 8px; flex-wrap: wrap; }
}

@media (max-width: 720px){
  .ap-head{ flex-direction: column; align-items: stretch; }
  .filter-grid{ grid-template-columns: 1fr; }
  .table-content{ overflow: visible; }

  /* جدول ككروت للموبايل */
  .requests-table{ border-collapse: separate; border-spacing: 0 10px; }
  .requests-table thead{ display:none; }
  .requests-table tbody tr{
    display:grid; grid-template-columns: 1fr;
    background: var(--surface-0); border: 1px solid var(--border-color);
    border-radius: 12px; box-shadow: var(--shadow-sm); padding: 8px 10px;
  }
  .requests-table tbody tr:hover{ background: var(--surface-0); box-shadow: var(--shadow-md); }
  .requests-table td{
    display:grid; grid-template-columns: 140px 1fr;
    align-items: center; text-align: start; gap: 8px;
    padding: 10px 6px; border: none; color: var(--text-strong);
  }
  .requests-table td:nth-child(1)::before{ content: "#"; font-weight:800; color: var(--text-muted); }
  .requests-table td:nth-child(2)::before{ content: "المستخدم"; font-weight:800; color: var(--text-muted); }
  .requests-table td:nth-child(3)::before{ content: "موضوع الطلب"; font-weight:800; color: var(--text-muted); }
  .requests-table td:nth-child(4)::before{ content: "تفاصيل الطلب"; font-weight:800; color: var(--text-muted); }
  .requests-table td:nth-child(5)::before{ content: "المساق"; font-weight:800; color: var(--text-muted); }
  .requests-table td:nth-child(6)::before{ content: "الأدمن المسؤول"; font-weight:800; color: var(--text-muted); }
  .requests-table td:nth-child(7)::before{ content: "الحالة"; font-weight:800; color: var(--text-muted); }
  .requests-table td:nth-child(8)::before{ content: "التاريخ"; font-weight:800; color: var(--text-muted); }
  .requests-table td:nth-child(9)::before{ content: "الإجراءات"; font-weight:800; color: var(--text-muted); }
}

@media (max-width: 420px){
  .btn{ font-size: .92rem; }
  .form-control{ min-height: 40px; }
}
/* شارة الحالة */
.st-badge { padding:2px 8px; border-radius:12px; font-size:12px; }
.st-pending   { background:#fff3cd; color:#856404; }
.st-inprogress{ background:#d1ecf1; color:#0c5460; }
.st-resolved  { background:#d4edda; color:#155724; }
.st-closed    { background:#ececec; color:#444; }
.st-cancelled { background:#f8d7da; color:#721c24; }

/* شارة الأولوية */
.prio { margin-inline-end:6px; padding:2px 6px; border-radius:10px; font-size:11px; font-weight:600; }
.prio-low    { background:#e8f5e9; color:#2e7d32; }
.prio-med    { background:#e3f2fd; color:#1565c0; }
.prio-high   { background:#fff3e0; color:#ef6c00; }
.prio-urgent { background:#ffebee; color:#c62828; }

/* نقطة غير المقروء */
.unread-dot { display:inline-block; width:8px; height:8px; background:#d00; border-radius:50%; margin-inline-start:6px; vertical-align:middle; }

/* “حبة” المساق */
.course-pill { background:#f1f3f5; color:#333; padding:2px 6px; border-radius:10px; font-size:12px; }

/* ===== Dark Mode Tweaks ===== */
html[data-theme="dark"] .upg-card,
html[data-theme="dark"] .sr-modal{ background:#0b1220; border-color:#1f2a44; color:#e5e7eb; }
html[data-theme="dark"] .ap-head{ box-shadow: 0 16px 40px rgba(0,0,0,.6); }
html[data-theme="dark"] .filter-select,
html[data-theme="dark"] .search-input,
html[data-theme="dark"] .form-control{ background:#0b1220; border-color:#1f2a44; color:#e5e7eb; }
html[data-theme="dark"] .requests-table tbody tr:nth-child(even){ background: rgba(33,158,188,.08); }
html[data-theme="dark"] .close-modal{ background:#0b1220; border-color:#1f2a44; color:#9aa3b2; }
html[data-theme="dark"] .close-modal:hover{ background:#111a2e; }

</style>