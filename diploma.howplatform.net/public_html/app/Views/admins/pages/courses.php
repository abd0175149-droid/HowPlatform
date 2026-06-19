<div class="upg-page" id="coursesPage">
  <!-- ===== Header ===== -->
  <header class="ap-head">
    <div class="ap-head__title">
      <div class="ap-head__icon"><i class="fas fa-book"></i></div>
      <div class="ap-head__text">
        <h2>إدارة المساقات</h2>
        <div class="ap-head__sub">أضف، عدّل، واحذف مساقاتك بسهولة</div>
      </div>
    </div>

    <div class="ap-head__actions">
      <span class="ap-counter" id="coursesCount">—</span>
      <button class="btn btn-ghost" id="resetFiltersBtn" type="button" onclick="resetFilters()">
        <i class="fas fa-sync-alt"></i> إعادة تعيين الفلتر
      </button>
      <!-- زر برتقالي واضح حسب طلبك -->
      <button class="btn btn-accent" id="addCourseBtn" type="button">
        <i class="fas fa-plus"></i> إضافة مساق
      </button>
    </div>
  </header>

  <!-- ===== Filters Card ===== -->
  <section class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-sliders-h"></i> فلترة المساقات</h3>
    </div>
    <div class="upg-card__body">
      <div class="ap-filters__grid">
        <!-- Academic Year -->
        <div class="ap-field">
          <label class="ap-label" for="academicYear">العام الدراسي</label>
          <div class="ap-input-wrap">
            <i class="fas fa-calendar-alt"></i>
            <select id="academicYear" class="upg-input">
              <option value="">اختر العام الدراسي</option>
            </select>
          </div>
        </div>

        <!-- University -->
        <div class="ap-field">
          <label class="ap-label" for="university">الجامعة</label>
          <div class="ap-input-wrap">
            <i class="fas fa-university"></i>
            <select id="university" class="upg-input" disabled>
              <option value="">اختر الجامعة</option>
            </select>
          </div>
        </div>

        <!-- Semester -->
        <div class="ap-field">
          <label class="ap-label" for="semester">الفصل الدراسي</label>
          <div class="ap-input-wrap">
            <i class="fas fa-layer-group"></i>
            <select id="semester" class="upg-input" disabled>
              <option value="">اختر الفصل الدراسي</option>
            </select>
          </div>
        </div>
      </div>

      <div class="upg-muted ap-filters__hint">
        تلميح: اختر العام ثم الجامعة ثم الفصل لعرض المساقات، بعدها يمكنك الإضافة أو التعديل.
      </div>
    </div>
  </section>

  <!-- ===== Table Card ===== -->
  <section class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-list"></i> قائمة المساقات</h3>
    </div>
    <div class="upg-card__body ap-table__wrap">
      <table class="upg-table">
        <thead>
          <tr>
            <th>#</th>
            <th>اسم المساق</th>
            <th>رمز المساق</th>
            <th>الجامعة</th>
            <th>عدد الأسابيع</th>
            <th>الإجراءات</th>
          </tr>
        </thead>
        <tbody id="coursesTableBody">
          <tr>
            <td class="upg-empty" colspan="6">
              يرجى اختيار السنة والجامعة والفصل لعرض المساقات
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</div>

<!-- ===== Modal: Add / Edit Course ===== -->
<div class="upg-modal" id="courseModalOverlay" aria-hidden="true">
  <div class="upg-modal__dialog ap-modal" role="dialog" aria-modal="true" aria-labelledby="apModalTitle">
    <!-- Head -->
    <div class="ap-modal__head">
      <h4 class="ap-modal__title">
        <i class="fas fa-book"></i>
        <span id="apModalTitle">إضافة مساق جديد</span>
      </h4>
      <button class="upg-modal__close" id="closeModalBtn" aria-label="إغلاق">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Body -->
    <div class="ap-modal__body">
      <!-- ثابت: السنة والفصل (من الفلترة) -->
      <div class="ap-row">
        <div class="ap-field">
          <label class="ap-label" for="modalAcademicYear">السنة الدراسية</label>
          <input type="text" id="modalAcademicYear" class="ap-input" disabled>
        </div>
        <div class="ap-field">
          <label class="ap-label" for="modalSemester">الفصل الدراسي</label>
          <input type="text" id="modalSemester" class="ap-input" disabled>
        </div>
      </div>

      <!-- جامعات متعددة (chips) — تُخفى في وضع التعديل عبر JS بإضافة .edit-mode على .ap-modal -->
      <div class="ap-field" id="multiUniversitiesRow">
        <div class="ap-field__row">
          <label class="ap-label">الجامعات المتاحة</label>
          <span class="ap-tip">يمكن اختيار عدة جامعات</span>
          <span class="ap-counter" id="uniSelectedCount">لا شيء مختار</span>
        </div>
        <div class="ap-multi-grid" id="modalUniversitiesList">
          <!-- تُحقن ديناميكيًا من JS:
               <input type="checkbox" class="specialty-checkbox" id="uni-1" value="1" checked>
               <label class="specialty-label" for="uni-1"><i class="fas fa-university"></i> جامعة كذا</label>
               * ستتم إضافة class="is-default" على اللابل المطابقة لجامعة الفلتر لإبرازها افتراضيًا
           -->
        </div>
      </div>

      <!-- الاسم والرمز -->
      <div class="ap-row">
        <div class="ap-field">
          <label class="ap-label" for="courseName">اسم المساق *</label>
          <input type="text" id="courseName" class="ap-input" placeholder="مثال: مهارات الحاسوب 1">
        </div>
        <div class="ap-field">
          <label class="ap-label" for="courseCode">رمز المساق *</label>
          <input type="text" id="courseCode" class="ap-input" placeholder="مثال: CS101">
        </div>
      </div>

      <!-- الوصف -->
      <div class="ap-field">
        <label class="ap-label" for="courseDescription">وصف المساق</label>
        <textarea id="courseDescription" class="ap-input" rows="3" placeholder="نبذة مختصرة عن محتوى المساق..."></textarea>
      </div>
    </div>

    <!-- Foot -->
    <div class="ap-modal__foot">
      <div class="ap-actions">
        <button class="btn" id="cancelModalBtn" type="button">
          <i class="fas fa-times"></i> إلغاء
        </button>
        <button class="btn btn-primary" id="saveCourseBtn" type="button">
          <i class="fas fa-save"></i> حفظ المساق
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Page CSS (scoped) ===== -->

