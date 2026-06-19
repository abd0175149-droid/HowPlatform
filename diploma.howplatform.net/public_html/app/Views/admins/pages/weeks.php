
<!-- ========== WEEKS PAGE (Admins) ========== -->
<div class="upg-page" id="weeksPage">
  <!-- ===== Header ===== -->
  <header class="ap-head">
    <div class="ap-head__title">
      <div class="ap-head__icon"><i class="fas fa-calendar-week"></i></div>
      <div class="ap-head__text">
        <h2>إدارة الأسابيع الدراسية</h2>
        <div class="ap-head__sub">أضف، عدّل، واحذف الأسابيع لمحتوى المساقات</div>
      </div>
    </div>

    <div class="ap-head__actions">
      <span class="ap-counter" id="weeksCount">—</span>
      <button class="btn btn-ghost" id="resetFiltersBtn" type="button" onclick="resetFiltersForWeeks?.()">
        <i class="fas fa-sync-alt"></i> إعادة تعيين الفلتر
      </button>
      <button class="btn btn-accent" id="addWeekBtn" type="button">
        <i class="fas fa-plus"></i> إضافة أسبوع جديد
      </button>
    </div>
  </header>

  <!-- ===== Filters Card ===== -->
  <section class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-sliders-h"></i> فلترة الأسابيع</h3>
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

        <!-- Course (اختياري) -->
        <div class="ap-field">
          <label class="ap-label" for="course">المساق (اختياري)</label>
          <div class="ap-input-wrap">
            <i class="fas fa-book"></i>
            <select id="course" class="upg-input" disabled>
              <option value="">كل المساقات</option>
            </select>
          </div>
        </div>
      </div>

      <div class="upg-muted ap-filters__hint">
        تلميح: يكفي اختيار العام والجامعة والفصل لعرض الأسابيع لكل المساقات. يمكنك تقييد النتائج باختيار مساق محدد.
      </div>
    </div>
  </section>

  <!-- ===== Table Card ===== -->
  <section class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-list"></i> قائمة الأسابيع</h3>
    </div>
    <div class="upg-card__body ap-table__wrap">
      <table class="upg-table weeks-table">
        <thead>
          <tr>
            <th>
              <button class="th-sort" data-sort="rownum" type="button" title="ترتيب">
                <span>#</span> <i class="fas fa-sort"></i>
              </button>
            </th>
            <th>
              <button class="th-sort" data-sort="course" type="button" title="ترتيب">
                <span>المساق</span> <i class="fas fa-sort"></i>
              </button>
            </th>
            <th>عنوان الأسبوع</th>
            <th>
              <button class="th-sort" data-sort="tasks" type="button" title="ترتيب">
                <span>عدد المهام</span> <i class="fas fa-sort"></i>
              </button>
            </th>
            <th>الإجراءات</th>
          </tr>
        </thead>
        <tbody id="weeksTableBody">
          <tr>
            <td class="upg-empty" colspan="5">
              يرجى اختيار السنة والجامعة والفصل (والمساق إن رغبت) لعرض الأسابيع
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div class="pagination">
        <button class="pagination-btn" id="firstPageBtn" title="الأولى">
          <i class="fas fa-angle-double-right"></i>
        </button>
        <button class="pagination-btn" id="prevPageBtn" title="السابق">
          <i class="fas fa-angle-right"></i>
        </button>

        <div class="pagination-info" id="paginationInfo">—</div>

        <button class="pagination-btn" id="nextPageBtn" title="التالي">
          <i class="fas fa-angle-left"></i>
        </button>
        <button class="pagination-btn" id="lastPageBtn" title="الأخيرة">
          <i class="fas fa-angle-double-left"></i>
        </button>
      </div>
    </div>
  </section>

  <div class="footer">
    <p>© <span id="currentYear"></span> منصة كيف التعليمية - جميع الحقوق محفوظة</p>
  </div>
</div>

<!-- ===== Modal: Add / Edit Week ===== -->
<div class="upg-modal" id="weekModalOverlay" aria-hidden="true">
  <div class="upg-modal__dialog ap-modal week-modal" role="dialog" aria-modal="true" aria-labelledby="weekModalTitle">
    <!-- Head -->
    <div class="ap-modal__head">
      <h4 class="ap-modal__title">
        <i class="fas fa-calendar-plus"></i>
        <span id="weekModalTitle">إضافة أسبوع جديد</span>
      </h4>
      <button class="upg-modal__close" id="closeModalBtn" aria-label="إغلاق">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Body -->
    <div class="ap-modal__body">
      <!-- ثابت: السنة والفصل من الفلاتر -->
      <div class="ap-row">
        <div class="ap-field readonly">
          <label class="ap-label" for="modalAcademicYear">السنة الدراسية</label>
          <input type="text" id="modalAcademicYear" class="ap-input" disabled>
        </div>
        <div class="ap-field readonly">
          <label class="ap-label" for="modalSemester">الفصل الدراسي</label>
          <input type="text" id="modalSemester" class="ap-input" disabled>
        </div>
      </div>

      <!-- منطقة تعيين (جامعة ← مساقات متعددة) قابلة للتكرار -->
      <div class="upg-card" id="assignmentsCard">
        <div class="upg-card__head">
          <h3><i class="fas fa-university"></i> تعيين الجامعات والمساقات</h3>
        </div>
        <div class="upg-card__body">
          <div id="assignmentsContainer">
            <!-- تُحقن الصفوف ديناميكيًا عبر JS -->
            <!-- شكل الصف:
            <div class="ap-row ap-assign-row" data-row-id="...">
              <div class="ap-field">
                <label class="ap-label">الجامعة</label>
                <div class="ap-input-wrap">
                  <i class="fas fa-university"></i>
                  <select class="ap-input uni-select">
                    <option value="">اختر الجامعة</option>
                  </select>
                </div>
              </div>
              <div class="ap-field">
                <label class="ap-label">المساقات (يمكن متعدد)</label>
                <div class="ap-input-wrap">
                  <i class="fas fa-book"></i>
                  <select class="ap-input course-multi" multiple size="5" disabled>
                    <option value="">—</option>
                  </select>
                </div>
              </div>
              <div class="ap-field" style="align-self:end;">
                <button type="button" class="btn btn-ghost remove-assign"><i class="fas fa-trash"></i> إزالة</button>
              </div>
            </div>
            -->
          </div>

          <div class="assign-actions">
            <button class="btn" id="addAssignRowBtn" type="button">
              <i class="fas fa-plus"></i> إضافة جامعة/مساقات أخرى
            </button>
          </div>
          <div class="upg-muted" style="margin-top:8px">
            ملاحظة: في وضع <strong>التعديل</strong> لا يمكن تغيير الجامعة/المساق—سنُظهر البيانات الأصلية فقط.
          </div>
        </div>
      </div>

      <!-- في وضع التعديل فقط: نعرض الجامعة/المساق الأصليين بدل منطقة التعيينات -->
      <div id="editFixedMeta" class="upg-msg" style="display:none;margin-top:8px">
        <div><strong>الجامعة:</strong> <span id="editUniName">—</span></div>
        <div><strong>المساق:</strong> <span id="editCourseName">—</span></div>
      </div>

      <!-- بيانات الأسبوع -->
      <div class="ap-row" style="margin-top:8px">
        <div class="ap-field">
          <label class="ap-label" for="weekNumber">رقم الأسبوع *</label>
          <input type="number" id="weekNumber" class="ap-input" min="1" placeholder="أدخل رقم الأسبوع">
        </div>
        <div class="ap-field">
          <label class="ap-label" for="weekTitle">عنوان الأسبوع *</label>
          <input type="text" id="weekTitle" class="ap-input" placeholder="أدخل عنوان الأسبوع">
        </div>
      </div>
      <div class="ap-field">
        <label class="ap-label" for="publishDate">تاريخ النشر *</label>
        <input type="date" id="publishDate" class="ap-input">
      </div>
    </div>

    <div class="ap-modal__foot">
      <div class="ap-actions">
        <button class="btn" id="cancelModalBtn" type="button">
          <i class="fas fa-times"></i> إلغاء
        </button>
        <button class="btn btn-primary" id="saveWeekBtn" type="button">
          <i class="fas fa-save"></i> حفظ الأسبوع
        </button>
      </div>
    </div>
  </div>
</div>


