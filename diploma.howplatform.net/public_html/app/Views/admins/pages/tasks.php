<!-- ========== TASKS PAGE (Admins) ========== -->
<div class="upg-page" id="tasksPage">
  <!-- ===== Header ===== -->
  <header class="ap-head">
    <div class="ap-head__title">
      <div class="ap-head__icon"><i class="fas fa-tasks"></i></div>
      <div class="ap-head__text">
        <h2>إدارة المهام الدراسية</h2>
        <div class="ap-head__sub">أضف، عدّل، واحذف المهام التعليمية داخل الأسابيع</div>
      </div>
    </div>

    <div class="ap-head__actions">
      <button class="btn btn-ghost" id="resetFiltersBtn" type="button">
        <i class="fas fa-sync-alt"></i> إعادة تعيين الفلاتر
      </button>
      <button class="btn btn-accent" id="addTaskBtn" type="button">
        <i class="fas fa-plus"></i> إضافة مهمة جديدة
      </button>
    </div>
  </header>

  <!-- ===== Filters Card ===== -->
  <section class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-sliders-h"></i> فلترة المهام</h3>
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

        <!-- Course -->
        <div class="ap-field">
          <label class="ap-label" for="course">المساق</label>
          <div class="ap-input-wrap">
            <i class="fas fa-book"></i>
            <select id="course" class="upg-input" disabled>
              <option value="">اختر المساق</option>
            </select>
          </div>
        </div>

        <!-- Week -->
        <div class="ap-field">
          <label class="ap-label" for="week">الأسبوع</label>
          <div class="ap-input-wrap">
            <i class="fas fa-calendar-week"></i>
            <select id="week" class="upg-input" disabled>
              <option value="">اختر الأسبوع</option>
            </select>
          </div>
        </div>

        <!-- Specialty (Filter) -->
        <div class="ap-field">
          <label class="ap-label" for="specialty">التخصص</label>
          <div class="ap-input-wrap">
            <i class="fas fa-graduation-cap"></i>
            <select id="specialty" class="upg-input">
              <option value="">جميع التخصصات</option>
            </select>
          </div>
        </div>
      </div>

      <div class="upg-muted ap-filters__hint">
        تلميح: اختر (السنة، الجامعة، الفصل، المساق، الأسبوع) لعرض المهام بدقة. يمكنك تضييق النتائج بتحديد التخصص.
      </div>
    </div>
  </section>

  <!-- ===== Search Bar ===== -->
  <section class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-search"></i> بحث داخل النتائج</h3>
    </div>
    <div class="upg-card__body">
      <div class="table-search">
        <div class="form-group-search">
          <label class="fas fa-search" title="بحث"></label>

          <input type="text" id="searchTitle" class="ap-input" placeholder="ابحث في عنوان المهمة…">
          <input type="text" id="searchSpecialty" class="ap-input" placeholder="ابحث في التخصص…">

          <input type="date" id="dateFrom" class="ap-input" title="من تاريخ">
          <input type="date" id="dateTo"   class="ap-input" title="إلى تاريخ">
        </div>
      </div>
    </div>
  </section>

  <!-- ===== Table Card ===== -->
  <section class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-list"></i> قائمة المهام</h3>
    </div>
    <div class="upg-card__body ap-table__wrap">
      <table class="upg-table tasks-table">
        <thead>
          <tr>
            <th><span>#</span></th>
            <th>الجامعة</th>
            <th>المساق</th>
            <th>الأسبوع</th>
            <th>عنوان المهمة</th>
            <th>نوع المهمة</th>
            <th>التخصصات</th>
            <th>عدد المرفقات</th>
            <th>الإجراءات</th>
          </tr>
        </thead>
        <tbody id="tasksTableBody">
          <tr>
            <td class="upg-empty" colspan="9">يرجى اختيار جميع الفلاتر</td>
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

        <div class="pagination-info" id="paginationInfo">صفحة 1 من 1</div>

        <button class="pagination-btn" id="nextPageBtn" title="التالي">
          <i class="fas fa-angle-left"></i>
        </button>
        <button class="pagination-btn" id="lastPageBtn" title="الأخيرة">
          <i class="fas fa-angle-double-left"></i>
        </button>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <div class="footer">
    <p>© <span id="currentYear"></span> منصة كيف التعليمية - جميع الحقوق محفوظة</p>
  </div>

  <!-- (اختياري للأمان) دعم مرجع قديم في JS -->
  <select id="academicYearSelect" hidden></select>
</div>

<!-- ===== Modal: Add / Edit Task ===== -->
<div class="upg-modal" id="taskModalOverlay" aria-hidden="true">
  <div class="upg-modal__dialog ap-modal task-modal" role="dialog" aria-modal="true">
    <!-- Head -->
    <div class="ap-modal__head">
      <h4 class="ap-modal__title">
        <i class="fas fa-tasks"></i>
        <!-- مهم: يستخدمه tasks.js -->
        <span id="modalTitle">إضافة مهمة جديدة</span>
      </h4>
      <button class="upg-modal__close" id="closeModalBtn" aria-label="إغلاق">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Body -->
    <div class="ap-modal__body">
      <input type="hidden" id="taskId">

      <!-- Progress Steps -->
      <div class="task-modal-steps">
        <div class="step-indicator">
          <div class="step-item active" data-step="1">
            <div class="step-number">1</div>
            <div class="step-label">المعلومات الأساسية</div>
          </div>
          <div class="step-item" data-step="2">
            <div class="step-number">2</div>
            <div class="step-label">تفاصيل المهمة</div>
          </div>
          <div class="step-item" data-step="3">
            <div class="step-number">3</div>
            <div class="step-label">المرفقات</div>
          </div>
        </div>
      </div>

      <!-- Step 1: Basic Information -->
      <div class="modal-step active" data-step="1">
        <div class="ap-row">
          <div class="ap-field readonly">
            <label class="ap-label" for="modalAcademicYear">السنة الدراسية</label>
            <input type="text" id="modalAcademicYear" class="ap-input" disabled placeholder="العام الدراسي">
          </div>
          <div class="ap-field readonly">
            <label class="ap-label" for="modalSemester">الفصل الدراسي</label>
            <input type="text" id="modalSemester" class="ap-input" disabled placeholder="الفصل الدراسي">
          </div>
          <div class="ap-field readonly">
            <label class="ap-label" for="modalUniversity">الجامعة</label>
            <input type="text" id="modalUniversity" class="ap-input" disabled placeholder="الجامعة">
          </div>
        </div>

        <div class="ap-row">
          <div class="ap-field readonly">
            <label class="ap-label" for="modalCourse">المساق</label>
            <input type="text" id="modalCourse" class="ap-input" disabled placeholder="المساق">
          </div>
          <div class="ap-field readonly">
            <label class="ap-label" for="modalWeek">الأسبوع</label>
            <input type="text" id="modalWeek" class="ap-input" disabled placeholder="الأسبوع">
          </div>
          <div class="ap-field">
            <label class="ap-label" for="taskType">نوع المهمة *</label>
            <div class="ap-input-wrap">
              <i class="fas fa-tag"></i>
              <select id="taskType" class="upg-input" required>
                <option value="">-- اختر نوع المهمة --</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Step 2: Task Details -->
      <div class="modal-step" data-step="2">
        <div class="ap-row">
          <div class="ap-field">
            <label class="ap-label" for="taskTitle">عنوان المهمة *</label>
            <input type="text" id="taskTitle" class="ap-input" placeholder="عنوان المهمة" required>
          </div>
          <div class="ap-field">
            <label class="ap-label" for="taskDescription">وصف المهمة *</label>
            <textarea id="taskDescription" class="ap-input" rows="4" placeholder="وصف المهمة" required></textarea>
          </div>
        </div>

        <div class="ap-row">
          <div class="ap-field" style="grid-column: 1 / -1;">
            <label class="ap-label" for="taskPrompt">موجه المهمة (Task Prompt)</label>
            <textarea id="taskPrompt" class="ap-input" rows="3" placeholder="أدخل موجه المهمة الذي سيستخدم مع الذكاء الاصطناعي"></textarea>
            <small class="ap-tip" style="display:block; margin-top:4px; color:var(--muted);">هذا الحقل اختياري ويستخدم لتوجيه الذكاء الاصطناعي عند معالجة المهمة</small>
          </div>

          <!-- اختيار إصدار أداة الذكاء الاصطناعي -->
          <div class="ap-field" style="grid-column: 1 / -1;" id="aiToolVersionContainer">
            <label class="ap-label" for="aiToolVersion">اختيار إصدار أداة الذكاء الاصطناعي (اختياري)</label>
            <select id="aiToolVersion" class="upg-input">
              <option value="">-- الافتراضي لنوع المهمة --</option>
            </select>
            <small class="ap-tip" style="display:block; margin-top:4px; color:var(--muted);">تخصيص إصدار أو سلوك مختلف لأداة الذكاء الاصطناعي المتاحة للطلاب في هذه المهمة.</small>
          </div>
        </div>

        <!-- قسم دراسة الحالة (يظهر فقط عند اختيار نوع مهمة دراسة الحالة) -->
        <div id="caseStudySection" class="ap-row case-study-section" style="display: none;">
          <div class="ap-field full-width">
            <h4 class="section-title">
              <i class="fas fa-file-lines"></i> إدارة حالات دراسة الحالة
            </h4>
            <p class="ap-tip">أضف حالات دراسة الحالة ومعايير المعلمين المرتبطة بها</p>
          </div>

          <!-- معايير المعلمين -->
          <div class="ap-field full-width">
            <label class="ap-label">معايير المعلمين (ترقيم هرمي مثل 1.1.4)</label>
            <div id="criteriaContainer" class="dynamic-list-container">
              <div class="criterion-row flex-row">
                <input type="text" class="criterion-input ap-input flex-grow" placeholder="مثال: 1.1.4 - فهم طبيعة التعلم">
                <button type="button" class="btn-remove-criterion btn-icon-danger">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
            <button type="button" id="btnAddCriterion" class="ap-btn-secondary btn-success-sm">
              <i class="fas fa-plus"></i> إضافة معيار آخر
            </button>
          </div>

          <!-- حالات دراسة الحالة -->
          <div class="ap-field full-width">
            <label class="ap-label">حالات دراسة الحالة</label>
            <div id="casesContainer">
              <div class="case-item case-card">
                <div class="case-card-header">
                  <h5 class="case-title-text">الحالة #1</h5>
                  <button type="button" class="btn-remove-case btn-icon-danger-sm">
                    <i class="fas fa-trash"></i> حذف
                  </button>
                </div>
                <div class="ap-field mb-2">
                  <label class="ap-label">عنوان الحالة</label>
                  <input type="text" class="case-title ap-input" placeholder="عنوان الحالة">
                </div>
                <div class="ap-field">
                  <label class="ap-label">نص دراسة الحالة</label>
                  <textarea class="case-text ap-input" rows="5" placeholder="نص دراسة الحالة..."></textarea>
                </div>
              </div>
            </div>
            <button type="button" id="btnAddCase" class="ap-btn-secondary btn-primary-sm mt-2">
              <i class="fas fa-plus"></i> إضافة حالة أخرى
            </button>
          </div>
        </div>

        <!-- قسم دمج أصحاب العلاقة (يظهر فقط عند اختيار المهمة 48) -->
        <div id="stakeholderSection" class="ap-row stakeholder-section" style="display: none;">
          <div class="ap-field full-width">
            <h4 class="section-title">
              <i class="fas fa-users"></i> إدارة معايير المعلمين
            </h4>
            <p class="ap-tip">أضف معايير المعلمين المرتبطة بنشاط دمج أصحاب العلاقة</p>
          </div>

          <!-- معايير المعلمين لدمج أصحاب العلاقة -->
          <div class="ap-field full-width">
            <label class="ap-label">معايير المعلمين (ترقيم هرمي مثل 1.1.4)</label>
            <div id="stakeholderCriteriaContainer" class="dynamic-list-container">
              <div class="criterion-row flex-row">
                <input type="text" class="stakeholder-criterion-input ap-input flex-grow" placeholder="مثال: 1.1.4 - فهم طبيعة التعلم">
                <button type="button" class="btn-remove-stakeholder-criterion btn-icon-danger">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
            <button type="button" id="btnAddStakeholderCriterion" class="ap-btn-secondary btn-success-sm">
              <i class="fas fa-plus"></i> إضافة معيار آخر
            </button>
          </div>
        </div>


        <!-- التخصصات -->
        <div class="ap-field">
          <label class="ap-label" id="specialtyLabel">اختر التخصصات</label>
          <p class="ap-tip" id="specialtyHint">
            يمكنك اختيار أكثر من تخصص. عند اختيار أي تخصص سيتم إلغاء تفعيل خيار <strong>عام لكل التخصصات</strong>، والعكس صحيح.
          </p>
          <div
            id="specialtyOptions"
            class="specialty-options"
            role="group"
            aria-labelledby="specialtyLabel"
            aria-describedby="specialtyHint"
          ></div>
        </div>

        <!-- التواريخ -->
        <div class="ap-row">
          <div class="ap-field">
            <label class="ap-label" for="publishDate">تاريخ العرض *</label>
            <input type="date" id="publishDate" class="ap-input" data-weekday="6" required>
          </div>
          <div class="ap-field">
            <label class="ap-label" for="dueDate">تاريخ الاستحقاق *</label>
            <input type="date" id="dueDate" class="ap-input" readonly data-weekday="6" required>
          </div>
        </div>
      </div>

      <!-- Step 3: Attachments -->
      <div class="modal-step" data-step="3">
        <div class="file-upload-section">
          <div class="file-upload-header">
            <i class="fas fa-cloud-upload-alt"></i>
            <h3>رفع ملفات</h3>
            <p class="upload-hint">اسحب الملفات هنا أو اضغط على الزر لاختيارها</p>
          </div>

          <input type="file" id="fileInput" multiple hidden>
          <button class="file-upload-btn" type="button" id="fileUploadBtn">
            <i class="fas fa-upload"></i> اختيار ملفات
          </button>

          <div id="filePreviewContainer" class="file-preview-container"></div>
        </div>
      </div>
    </div>

    <div class="ap-modal__foot">
      <div class="ap-actions">
        <button class="btn" id="cancelModalBtn" type="button">
          <i class="fas fa-times"></i> إلغاء
        </button>
        <button class="btn btn-secondary" id="prevStepBtn" type="button" style="display:none;">
          <i class="fas fa-arrow-right"></i> السابق
        </button>
        <button class="btn btn-secondary" id="nextStepBtn" type="button">
          <i class="fas fa-arrow-left"></i> التالي
        </button>
        <button class="btn btn-primary" id="saveTaskBtn" type="button" style="display:none;">
          <i class="fas fa-save"></i> حفظ المهمة
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Modal: View Task ===== -->
<div class="upg-modal" id="viewModalOverlay" aria-hidden="true">
  <div class="upg-modal__dialog ap-modal view-modal" role="dialog" aria-modal="true">
    <!-- Head -->
    <div class="ap-modal__head">
      <h4 class="ap-modal__title">
        <i class="fas fa-project-diagram"></i>
        <span id="viewTaskTitle">-</span>
      </h4>

      <!-- شارة الحالة كما يتوقع tasks.js -->
      <span class="status-badge status-active" id="viewStatusBadge" style="margin-inline-start:auto;">
        <i class="fas fa-circle"></i> <span id="viewStatusText">نشطة</span>
      </span>

      <button class="upg-modal__close" id="viewCloseBtn" aria-label="إغلاق">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Body -->
    <div class="ap-modal__body">
      <!-- معلومات مختصرة أعلى -->
      <div class="secondary-info-grid-header">
        <div class="info-item-header">
          <i class="fas fa-university"></i>
          <div><span class="value" id="viewUniversity">-</span></div>
        </div>
        <div class="info-item-header">
          <i class="fas fa-book"></i>
          <div><span class="value" id="viewCourse">-</span></div>
        </div>
        <div class="info-item-header">
          <i class="fas fa-calendar-week"></i>
          <div><span class="value" id="viewWeek">-</span></div>
        </div>
      </div>

      <!-- Grid معلومات -->
      <div class="secondary-info-grid">
        <div class="info-item">
          <i class="fas fa-tag"></i>
          <div><span class="value" id="viewTaskType">-</span></div>
        </div>
        <div class="info-item">
          <i class="fas fa-calendar-check"></i>
          <div><span class="value" id="viewPublishDateValue">-</span></div>
        </div>
        <div class="info-item">
          <i class="fas fa-hourglass-end"></i>
          <div><span class="value" id="viewDueDateValue">-</span></div>
        </div>
      </div>

      <!-- الوصف -->
      <div class="description-section">
        <h3><i class="fas fa-align-left"></i> تفاصيل المهمة</h3>
        <div id="viewDescription">-</div>
      </div>

      <!-- التخصصات -->
      <div class="specialties-section">
        <h3 class="section-title"><i class="fas fa-graduation-cap"></i> التخصصات المستهدفة</h3>
        <div class="specialties-container" id="viewSpecialtiesContainer"></div>
      </div>

      <!-- الملفات -->
      <div class="files-section">
        <h3 class="section-title"><i class="fas fa-paperclip"></i> الملفات التعليمية المرفقة</h3>
        <div class="files-container" id="viewFilesContainer"></div>
      </div>
    </div>

    <div class="ap-modal__foot" style="justify-content: flex-start;">
      <button class="btn btn-ghost close-btn-task" id="viewModalCloseFooter" type="button">
        <i class="fas fa-times"></i> إغلاق التفاصيل
      </button>
    </div>
  </div>
</div>

<!-- ===== Modal: Copy / Move Task ===== -->
<div class="upg-modal" id="copyMoveModalOverlay" aria-hidden="true">
  <div class="upg-modal__dialog ap-modal task-modal" role="dialog" aria-modal="true">
    <div class="ap-modal__head">
      <h4 class="ap-modal__title">
        <i class="fas fa-exchange-alt"></i>
        <span id="cmModalTitle">نسخ/نقل المهمة</span>
      </h4>
      <button class="upg-modal__close" id="cmCloseBtn" aria-label="إغلاق">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="ap-modal__body">
      <!-- Hidden fields -->
      <input type="hidden" id="cmSourceTaskId">
      <input type="hidden" id="cmActionMode" value="clone">

      <!-- Section: Destination -->
      <div class="ap-field full-width">
        <h4 class="section-title">
          <i class="fas fa-route"></i> الوجهة المطلوبة
        </h4>
        <p class="ap-tip">حدد العام والفصل الدراسي لاختيار الوجهة</p>
      </div>

      <div class="ap-row">
        <div class="ap-field">
          <label class="ap-label" for="cmYear">العام الدراسي *</label>
          <div class="ap-input-wrap">
            <i class="fas fa-calendar-alt"></i>
            <select id="cmYear" class="upg-input">
              <option value="">اختر العام</option>
            </select>
          </div>
        </div>
        <div class="ap-field">
          <label class="ap-label" for="cmSemester">الفصل الدراسي *</label>
          <div class="ap-input-wrap">
            <i class="fas fa-layer-group"></i>
            <select id="cmSemester" class="upg-input" disabled>
              <option value="">اختر الفصل</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Section: Specific Targets -->
      <div class="ap-field full-width" style="margin-top: 16px;">
        <h4 class="section-title">
          <i class="fas fa-bullseye"></i> الأهداف (الجامعة - المساق - الأسبوع)
        </h4>
        
        <div class="upg-card" id="cmTargetsCard">
          <div class="upg-card__body">
            <div id="cmTargetRows">
              <!-- JS injected targets -->
            </div>
            
            <div class="assign-actions" style="margin-top: 12px;">
              <button id="cmAddTargetRowBtn" class="btn" type="button" disabled>
                <i class="fas fa-plus"></i> إضافة وجهة
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Link Options -->
      <div class="ap-field full-width" style="margin-top: 16px;">
        <label class="switch">
          <input type="checkbox" id="cmLinkExistingSwitch" checked>
          <span>اربط المرفقات الحالية بالمهمات الجديدة بشكل مباشر (توفير مساحة وتزامن أسرع)</span>
        </label>
      </div>

    </div>

    <div class="ap-modal__foot" style="justify-content: flex-end;">
      <button class="btn btn-ghost" id="cmCancelBtn" type="button">
        <i class="fas fa-times"></i> إلغاء
      </button>
      <button class="btn btn-primary" id="cmExecuteBtn" type="button">
        <i class="fas fa-check"></i> تأكيد التنفيذ
      </button>
    </div>
  </div>
</div>

<!-- ========== Page CSS (scoped) ========== -->

