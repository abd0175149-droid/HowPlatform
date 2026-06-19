<div class="upg-page" id="usersPage">
  <!-- ===== Header ===== -->
  <header class="ap-head">
    <div class="ap-head__title">
      <div class="ap-head__icon"><i class="fas fa-user-cog" aria-hidden="true"></i></div>
      <div class="ap-head__text">
        <h2>إدارة حسابات المستخدمين</h2>
        <div class="ap-head__sub">مراجعة وإدارة معلومات واشتراكات المستخدمين على المنصة</div>
      </div>
    </div>
    <div class="ap-head__actions">
      <span class="ap-counter"><i class="fas fa-database"></i>&nbsp;عرض قائم على قواعد متعددة</span>
    </div>
  </header>

  <!-- ===== Stats ===== -->
  <div class="mu-stats" id="muStats">
    <div class="mu-card mu-students is-loading">
      <div class="mu-icon-wrap"><i class="fas fa-user-graduate" aria-hidden="true"></i></div>
      <div class="mu-meta">
        <span class="mu-label">الطلاب</span>
        <span class="mu-value" id="statStudents" data-target="0">0</span>
      </div>
    </div>
    <div class="mu-card mu-teachers is-loading">
      <div class="mu-icon-wrap"><i class="fas fa-chalkboard-teacher" aria-hidden="true"></i></div>
      <div class="mu-meta">
        <span class="mu-label">المعلمين</span>
        <span class="mu-value" id="statTeachers" data-target="0">0</span>
      </div>
    </div>
    <div class="mu-card mu-admins is-loading">
      <div class="mu-icon-wrap"><i class="fas fa-user-shield" aria-hidden="true"></i></div>
      <div class="mu-meta">
        <span class="mu-label">الأدمن</span>
        <span class="mu-value" id="statAdmins" data-target="0">0</span>
      </div>
    </div>
  </div>

  <!-- ===== Filters Card ===== -->
  <section class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-filter"></i> فلاتر البحث</h3>
    </div>
    <div class="upg-card__body">
      <div class="ap-filters__grid">
        <div class="ap-field">
          <label class="ap-label">تصفية حسب الدور</label>
          <div class="ap-input-wrap">
            <i class="fas fa-user-tag" aria-hidden="true"></i>
            <select id="role-filter" class="upg-input"></select>
          </div>
        </div>

        <div class="ap-field">
          <label class="ap-label">حالة إكمال الملف</label>
          <div class="ap-input-wrap">
            <i class="fas fa-clipboard-check" aria-hidden="true"></i>
            <select id="complete-filter" class="upg-input">
              <option value="">الكل</option>
              <option value="1">مكتمل</option>
              <option value="0">غير مكتمل</option>
            </select>
          </div>
        </div>

        <div class="ap-field">
          <label class="ap-label">البحث باسم المستخدم</label>
          <div class="ap-input-wrap">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input type="text" id="username-search" class="upg-input" placeholder="ابحث باسم المستخدم">
          </div>
        </div>

        <div class="ap-field">
          <label class="ap-label">البحث برقم الهاتف</label>
          <div class="ap-input-wrap">
            <i class="fas fa-phone" aria-hidden="true"></i>
            <input type="text" id="phone-search" class="upg-input" placeholder="ابحث برقم الهاتف">
          </div>
        </div>

        <div class="ap-field">
          <label class="ap-label">البحث بالبريد</label>
          <div class="ap-input-wrap">
            <i class="fas fa-envelope" aria-hidden="true"></i>
            <input type="text" id="email-search" class="upg-input" placeholder="ابحث بالبريد الإلكتروني">
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== Table Card ===== -->
  <section class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-users"></i> قائمة المستخدمين</h3>
    </div>
    <!-- ap-table__wrap مباشرةً على upg-card__body كما في صفحة المساقات -->
    <div class="upg-card__body ap-table__wrap">
      <table class="upg-table users-table">
        <thead>
          <tr>
            <th>الصورة</th>
            <th>الاسم</th>
            <th>الهاتف</th>
            <th>الدور</th>
            <th>الجامعة</th>
            <th>العام</th>
            <th>التخصص</th>
            <th>الإجراءات</th>
          </tr>
        </thead>
        <tbody id="users-table-body">
          <!-- يملؤه الـ JS -->
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="admin-pagination" style="padding: 12px 16px;">
      <button class="admin-pagination-btn" id="firstPageBtn" title="الأولى">
        <i class="fas fa-angle-double-right"></i>
      </button>
      <button class="admin-pagination-btn" id="prevPageBtn" title="السابق">
        <i class="fas fa-angle-right"></i>
      </button>
      <div class="admin-pagination-info" id="paginationInfo">صفحة 1 من 1</div>
      <button class="admin-pagination-btn" id="nextPageBtn" title="التالي">
        <i class="fas fa-angle-left"></i>
      </button>
      <button class="admin-pagination-btn" id="lastPageBtn" title="الأخيرة">
        <i class="fas fa-angle-double-left"></i>
      </button>
    </div>
  </section>

  <!-- ===== Footer ===== -->
  <p class="upg-muted" style="text-align:center; font-size:0.82rem; padding:8px 0;">
    © <span id="currentYear"></span> منصة كيف التعليمية — جميع الحقوق محفوظة
  </p>
</div>

<script>document.getElementById("currentYear").textContent = new Date().getFullYear();</script>
