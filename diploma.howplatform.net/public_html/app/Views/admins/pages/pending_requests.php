 <div class="container2">
        <header>
            <h1><i class="fas fa-chart-line"></i> إدارة طلبات الترقية</h1>
            <p>عرض وإدارة طلبات ترقية الحسابات على منصة كيف التعليمية</p>
        </header>

        <div class="dashboard">
            <div class="dashboard-card">
                <i class="fas fa-hourglass-half"></i>
                <h3>الطلبات المعلقة</h3>
                <div class="count" id="count-pending">0</div>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-check-circle"></i>
                <h3>الطلبات المقبولة</h3>
                <div class="count" id="count-approved">0</div>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-times-circle"></i>
                <h3>الطلبات المرفوضة</h3>
                <div class="count" id="count-rejected">0</div>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-file-invoice-dollar"></i>
                <h3>إجمالي الإيرادات</h3>
                <div class="count" id="total-revenue">0 دينار</div>
            </div>
        </div>

        <div class="filters-container">
            <div class="filter-group">
                <label><i class="fas fa-filter"></i> تصفية حسب الحالة</label>
                <select class="filter-control" id="status-filter">
                    <option value="all">الكل</option>
                    <option value="pending">معلقة</option>
                    <option value="approved">مقبولة</option>
                    <option value="rejected">مرفوضة</option>
                </select>
            </div>

            <div class="filter-group">
                <label><i class="fas fa-search"></i> البحث باسم المستخدم</label>
                <input type="text" class="filter-control" id="username-search" placeholder="ابحث باسم المستخدم">
            </div>

            <div class="filter-group">
                <label><i class="fas fa-phone"></i> البحث برقم الهاتف</label>
                <input type="text" class="filter-control" id="phone-search" placeholder="ابحث برقم الهاتف">
            </div>
        </div>

        <div class="requests-table-container">
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>اسم المستخدم</th>
                        <th>رقم الهاتف</th>
                        <th>نوع الخطة</th>
                        <th>تاريخ الطلب</th>
                        <th>الحالة</th>
                        <th>ملاحظات</th>
                        <th>تواصل</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody id="requests-table-body">
                    <!-- يتم ملؤه عبر JS -->
                </tbody>
            </table>
            <div class="pagination">
      <button class="pagination-btn" id="firstPageBtn"><i class="fas fa-angle-double-right"></i></button>
      <button class="pagination-btn" id="prevPageBtn"><i class="fas fa-angle-right"></i></button>
      <div class="pagination-info" id="paginationInfo">صفحة 1 من 1</div>
      <button class="pagination-btn" id="nextPageBtn"><i class="fas fa-angle-left"></i></button>
      <button class="pagination-btn" id="lastPageBtn"><i class="fas fa-angle-double-left"></i></button>
    </div>
        </div>
          <div class="footer">
    <p>© <span id="currentYear"></span> منصة كيف التعليمية - جميع الحقوق محفوظة</p>
  </div>
    </div>
    <div id="payment-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-money-bill-wave"></i> تسجيل عملية الدفع</h2>
            <button class="close-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body grid-form">
            <!-- الصف الأول -->
            <div class="form-group">
                <label><i class="fas fa-user"></i> اسم المستخدم</label>
                <input type="text" class="form-control" id="input-username" disabled>
            </div>
            <div class="form-group">
                <label><i class="fas fa-list"></i> نوع الخطة</label>
                <input type="text" class="form-control" id="input-plan-name" disabled>
            </div>
            <div class="form-group">
                <label><i class="fas fa-money-bill"></i> المبلغ المطلوب (دينار)</label>
                <input type="number" class="form-control" id="input-amount">
            </div>

            <!-- الصف الثاني -->
            <div class="form-group">
                <label><i class="fas fa-credit-card"></i> طريقة الدفع</label>
                <select class="form-control" id="input-payment-method">
                    <option value="">اختر طريقة الدفع</option>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-file-invoice"></i> رقم الفاتورة</label>
                <input type="text" class="form-control" id="input-invoice">
            </div>
            <div class="form-group">
                <label><i class="fas fa-exchange-alt"></i> معرف العملية</label>
                <input type="text" class="form-control" id="input-gateway-id">
            </div>

            <!-- الصف الثالث -->
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> تاريخ الدفع</label>
                <input type="date" class="form-control" id="input-payment-date">
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> تاريخ نهاية الترقية</label>
                <input type="date" class="form-control" id="input-premium-until">
            </div>

            <!-- الصف الرابع -->
            <div class="form-group full-width">
                <label><i class="fas fa-sticky-note"></i> ملاحظات</label>
                <textarea class="form-control" id="input-notes" rows="3"></textarea>
            </div>
            <div class="modal-footer full-width">
            <button class="btn btn-secondary close-modal-btn">إلغاء</button>
            <button class="btn btn-primary">تسجيل الدفع</button>
        </div>
        </div>
        
    </div>
</div>

    <!-- مودال تواصل -->
<div id="contact-modal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2><i class="fas fa-comment-dots"></i> تواصل مع المستخدم</h2>
      <button class="close-modal"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>اسم المستخدم:</label>
        <input type="text" class="form-control" id="contact-username" disabled>
      </div>
      <div class="form-group">
        <label>رقم الهاتف:</label>
        <input type="text" class="form-control" id="contact-phone" disabled>
      </div>
      <div class="form-group">
        <label>البريد الإلكتروني:</label>
        <input type="text" class="form-control" id="contact-email" disabled>
      </div>
      <div class="form-group">
        <label>نص الرسالة:</label>
        <textarea class="form-control" id="contact-message" rows="7"></textarea>
      </div>
      <div class="modal-footer">
      <button class="btn btn-secondary close-modal">إلغاء</button>
      <button class="btn btn-whatsapp" id="contact-whatsapp">
        <i class="fab fa-whatsapp"></i> إرسال عبر واتساب
      </button>
      <button class="btn btn-email" id="contact-email-btn">
        <i class="fas fa-envelope"></i> إرسال عبر البريد
      </button>
    </div>
    </div>
    
  </div>
</div>

<style>
/* ===== عناصر فريدة بصفحة pending_requests فقط ===== */
/* (عناصر .btn, .modal, .form-control, .pagination تعيش في admin_style.css) */

/* حاوية الصفحة */
.container2 {
  max-width: 1120px;
  margin: 0 auto;
}

/* header الصفحة */
header {
  background: var(--surface-0);
  padding: 16px 0 12.8px;
  margin-bottom: 16px;
  border-bottom: 0.8px solid var(--border-color);
}
header h1 {
  font-size: 1.5rem;
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-strong);
}
header p {
  font-size: .875rem;
  color: var(--text-muted);
  margin-top: 3.2px;
}

/* بطاقات الإحصاءات */
.dashboard {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(176px, 1fr));
  gap: 12.8px;
  margin-bottom: 16px;
}
.dashboard-card {
  background: var(--surface-0);
  padding: 14.4px;
  border-radius: 8px;
  border: 0.8px solid var(--border-color);
  border-top: 2.4px solid var(--primary-500);
  text-align: center;
  transition: box-shadow .18s ease;
}
.dashboard-card:hover {
  box-shadow: var(--shadow-sm);
  border-color: var(--primary-300);
}
.dashboard-card i {
  font-size: 1.8rem;
  color: var(--primary-500);
  display: block;
  margin-bottom: 6.4px;
}
.dashboard-card h3 {
  margin-bottom: 4.8px;
  font-size: 1rem;
  color: var(--text-muted);
  font-weight: 500;
}
.dashboard-card .count {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-strong);
}

/* فلاتر البحث */
.filters-container {
  background: var(--surface-0);
  padding: 12.8px;
  border-radius: 8px;
  border: 0.8px solid var(--border-color);
  margin-bottom: 16px;
  display: flex;
  flex-wrap: wrap;
  gap: 9.6px;
}
.filter-group {
  flex: 1 1 176px;
  display: flex;
  flex-direction: column;
  gap: 3.2px;
}
.filter-group label {
  font-weight: 600;
  font-size: .78rem;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 4.8px;
  text-transform: uppercase;
  letter-spacing: .02em;
}
.filter-control {
  padding: 7.2px 9.6px;
  border: 1.2px solid var(--border-color);
  background: var(--surface-0);
  font-size: .875rem;
  border-radius: 6.4px;
  transition: border-color .15s ease, box-shadow .15s ease;
  color: var(--text-strong);
  font-family: inherit;
}
.filter-control:focus {
  border-color: var(--primary-500);
  box-shadow: var(--focus-ring);
  outline: none;
}

/* جدول الطلبات */
.requests-table-container {
  background: var(--surface-0);
  border-radius: 8px;
  border: 0.8px solid var(--border-color);
  margin-bottom: 16px;
  overflow-x: auto;
}
.requests-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 720px;
  font-size: .875rem;
}
.requests-table th {
  background: var(--surface-1);
  color: var(--text-strong);
  font-weight: 700;
  padding: 8px 9.6px;
  text-align: right;
  border-bottom: 1.2px solid var(--border-color);
  font-size: .8rem;
}
.requests-table td {
  padding: 8px 9.6px;
  border-bottom: 0.8px solid var(--border-color);
  text-align: right;
  color: var(--text);
}
.requests-table tbody tr:last-child td { border-bottom: none; }
.requests-table tbody tr:hover td { background: var(--surface-1); }

/* شارات الحالة */
.status-badge {
  padding: 2.4px 8px;
  border-radius: 16px;
  font-weight: 600;
  font-size: .8rem;
  white-space: nowrap;
}
.status-pending  { background: var(--warning-100); color: var(--warning-600); }
.status-approved { background: var(--success-100); color: var(--success-600); }
.status-rejected { background: var(--error-100);   color: var(--error-600);   }

/* خلية الملاحظات */
.notes-cell {
  min-width: 96px;
  max-width: 144px;
  font-size: .85rem;
  position: relative;
}
.notes-cell.pending {
  border: 0.8px dashed var(--border-color);
  border-radius: 4.8px;
  padding: 3.2px 4.8px;
  cursor: text;
}
.notes-cell.pending:focus {
  outline: none;
  border-color: var(--primary-500);
  box-shadow: var(--focus-ring);
}
.notes-cell.locked {
  color: var(--text-muted);
  cursor: not-allowed;
}

/* أزرار الإجراءات — فريدة لهذه الصفحة */
.action-btn {
  border: none;
  padding: 4.8px 9.6px;
  border-radius: 4.8px;
  cursor: pointer;
  font-size: .8rem;
  transition: background .15s ease, transform .12s ease;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
  margin: 1.6px;
  font-family: inherit;
}
.btn-reject  { background: var(--error-100);   color: var(--error-600); }
.btn-reject:hover  { background: var(--error-500);  color: #fff; }
.btn-approve { background: var(--success-100); color: var(--success-600); }
.btn-approve:hover { background: var(--success-500); color: #fff; }
.btn-contact { background: var(--info-100);    color: var(--info-600); }
.btn-contact:hover { background: var(--info-500);    color: #fff; }
.btn-review  { background: var(--primary-50);  color: var(--primary-700); }
.btn-review:hover  { background: var(--primary-500); color: #fff; }

/* مودال التواصل — عرض خاص */
#contact-modal .modal-content { max-width: 464px; }
.btn-whatsapp {
  background: #25D366;
  color: #fff;
  border: none;
}
.btn-whatsapp:hover { background: #1da851; }
.btn-email {
  background: var(--primary-50);
  color: var(--primary-700);
  border: none;
}
.btn-email:hover { background: var(--primary-500); color: #fff; }

/* نموذج الشبكة داخل المودال */
.grid-form {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(192px, 1fr));
  gap: 12.8px;
}
.full-width { grid-column: 1 / -1; }

/* scrollbar للـ modal-body */
.modal-content .modal-body {
  max-height: calc(90vh - 112px);
  overflow-y: auto;
  overscroll-behavior: contain;
}
.modal-content .modal-body::-webkit-scrollbar { width: 4.8px; }
.modal-content .modal-body::-webkit-scrollbar-thumb {
  background: var(--primary-200);
  border-radius: 2.4px;
}
</style>