<!-- =========================================================
     Academic Years & Semesters — Unified Admin Design
     (Matches Manage Users page tokens/components)
     لا تغييرات على الـ IDs لضمان توافق الـ JS
========================================================= -->


<section id="yearsPage" class="upg-page">
  <!-- Header -->
  <header class="ap-head">
    <div class="ap-head__title">
      <div class="ap-head__icon"><i class="fas fa-calendar-alt" aria-hidden="true"></i></div>
      <div class="ap-head__text">
        <h2>إدارة العام الدراسي والفصول الدراسية</h2>
        <div class="ap-head__sub">إدارة كاملة للأعوام الدراسية والفصول والجامعات المرتبطة</div>
      </div>
    </div>
    <div class="ap-head__actions">
      <span class="ap-counter"><i class="fas fa-sliders-h"></i>&nbsp;لوحة خيارات الموقع</span>
    </div>
  </header>

  <!-- Stats -->
  <div class="mu-stats">
    <div class="mu-card">
      <div class="mu-icon-wrap"><i class="fas fa-calendar-check" aria-hidden="true"></i></div>
      <div class="mu-meta">
        <span class="mu-label">عدد الأعوام</span>
        <span class="mu-value"><?= esc($yearsCount ?? 0) ?></span>
      </div>
    </div>
    <div class="mu-card">
      <div class="mu-icon-wrap"><i class="fas fa-book-open" aria-hidden="true"></i></div>
      <div class="mu-meta">
        <span class="mu-label">عدد الفصول</span>
        <span class="mu-value"><?= esc($semestersCount ?? 0) ?></span>
      </div>
    </div>
    <div class="mu-card">
      <div class="mu-icon-wrap"><i class="fas fa-university" aria-hidden="true"></i></div>
      <div class="mu-meta">
        <span class="mu-label">عدد الجامعات</span>
        <span class="mu-value"><?= esc($universitiesCount ?? 0) ?></span>
      </div>
    </div>
  </div>

  <!-- Panels: Add Year / Add Semester / Link Universities -->
 <!-- غلّف البطاقات الثلاث داخل هذه الحاوية -->
<div class="ay-panels">
  <!-- بطاقة: إضافة عام دراسي -->
  <div class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-plus-circle"></i> إضافة عام دراسي جديد</h3>
    </div>
    <div class="upg-card__body">
      <div class="ap-form__grid">
        <div class="ap-field">
          <label for="yearName" class="ap-label"><i class="fas fa-signature"></i> اسم العام الدراسي</label>
          <div class="ap-input-wrap">
            <i class="fas fa-signature"></i>
            <input type="text" id="yearName" class="form-control" placeholder="مثال: 2024-2025">
          </div>
        </div>
        <div class="ap-field">
          <label for="startDate" class="ap-label"><i class="fas fa-calendar-alt"></i> تاريخ البدء</label>
          <div class="ap-input-wrap">
            <i class="fas fa-calendar-alt"></i>
            <input type="date" id="startDate" class="form-control">
          </div>
        </div>
        <div class="ap-field">
          <label for="endDate" class="ap-label"><i class="fas fa-calendar-alt"></i> تاريخ الانتهاء</label>
          <div class="ap-input-wrap">
            <i class="fas fa-calendar-alt"></i>
            <input type="date" id="endDate" class="form-control">
          </div>
        </div>
      </div>

      <button type="button" class="btn btn-primary"><i class="fas fa-save"></i> حفظ العام الدراسي</button>

      <h3 class="section-subtitle"><i class="fas fa-list"></i> الأعوام المسجَّلة</h3>
      <ul id="yearsList" class="simple-list"></ul>
    </div>
  </div>

  <!-- بطاقة: إضافة فصل -->
  <div class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-book"></i> إضافة فصل دراسي</h3>
    </div>
    <div class="upg-card__body">
      <div class="ap-form__grid">
        <div class="ap-field">
          <label for="academicYear" class="ap-label"><i class="fas fa-calendar"></i> العام الدراسي</label>
          <div class="ap-input-wrap">
            <i class="fas fa-calendar"></i>
            <select id="academicYear" class="form-control">
              <option value="">اختر العام الدراسي</option>
              <?php foreach ($yearOptions as $y): ?>
                <option value="<?= $y['id'] ?>"><?= esc($y['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="ap-field">
          <label for="semesterName" class="ap-label"><i class="fas fa-book-open"></i> اسم الفصل</label>
          <div class="ap-input-wrap">
            <i class="fas fa-book-open"></i>
            <input type="text" id="semesterName" class="form-control" placeholder="مثال: الفصل الأول">
          </div>
        </div>
      </div>

      <button type="button" class="btn btn-primary"><i class="fas fa-plus-circle"></i> إضافة الفصل</button>

      <h3 class="section-subtitle"><i class="fas fa-list"></i> فصول العام المختار</h3>
      <ul id="semestersList" class="simple-list"></ul>
    </div>
  </div>

  <!-- بطاقة: ربط الجامعات -->
  <div class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-university"></i> ربط الجامعات بالعام الدراسي</h3>
    </div>
    <div class="upg-card__body">
      <div class="ap-form__grid">
        <div class="ap-field">
          <label for="academicYearLink" class="ap-label"><i class="fas fa-calendar"></i> العام الدراسي</label>
          <div class="ap-input-wrap">
            <i class="fas fa-calendar"></i>
            <select id="academicYearLink" class="form-control">
              <option value="">اختر العام الدراسي</option>
              <?php foreach ($yearOptions as $y): ?>
                <option value="<?= $y['id'] ?>"><?= esc($y['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- يحافظ على نفس الـID ليستمر JS بالعمل -->
      <div id="universities" class="uni-grid" aria-label="الجامعات">
        <?php foreach ($universityOptions as $u): ?>
          <label class="uni-chip" for="uni<?= $u['id'] ?>">
            <input type="checkbox" id="uni<?= $u['id'] ?>">
            <span><?= esc($u['name']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <button type="button" class="btn btn-primary">
        <i class="fas fa-link"></i> حفظ الربط
      </button>

      <h3 class="section-subtitle"><i class="fas fa-list"></i> الجامعات المرتبطة بالعام</h3>
      <ul id="universitiesList" class="simple-list"></ul>
    </div>
  </div>
</div>


  <!-- Academic Years Cards -->
  <div class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-list"></i> الأعوام الدراسية</h3>
    </div>
    <div class="upg-card__body">
      <?php foreach ($years as $year): ?>
        <?php
          $statusClass = [
            'upcoming' => 'status-upcoming',
            'active'   => 'status-active',
            'ended'    => 'status-ended',
          ][$year['status']];

          $progressClass = [
            'upcoming' => 'upcoming-progress',
            'active'   => 'active-progress',
            'ended'    => 'ended-progress',
          ][$year['status']];
        ?>
        <div class="year-card">
          <div>
            <div class="year-header">
              <div>
                <div class="year-title">
                  <i class="fas fa-calendar"></i> <?= esc($year['label']) ?>
                </div>
                <div class="year-period">
                  <i class="fas fa-clock"></i>
                  <?= date('d/m/Y', strtotime($year['start_date'])) ?> -
                  <?= date('d/m/Y', strtotime($year['end_date'])) ?>
                </div>
                <div class="progress-container">
                  <div class="progress-bar <?= $progressClass ?>" style="width: <?= $year['progress'] ?>%"></div>
                </div>
              </div>
              <div class="year-status <?= $statusClass ?>">
                <?= $year['status'] === 'upcoming' ? 'قادم' : ($year['status'] === 'active' ? 'نشط' : 'منتهي') ?>
              </div>
            </div>

            <div class="year-content">
              <div class="semesters-section">
                <h4 class="section-subtitle"><i class="fas fa-book"></i> الفصول الدراسية</h4>
                <ul class="semester-list">
                  <?php foreach ($year['semesters'] as $s): ?>
                    <li class="semester-item">
                      <i class="fas fa-book"></i>
                      <div><strong><?= esc($s['name']) ?></strong></div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>

              <div class="universities-section">
                <h4 class="section-subtitle"><i class="fas fa-university"></i> الجامعات المرتبطة</h4>
                <ul class="university-list">
                  <?php foreach ($year['universities'] as $u): ?>
                    <li class="university-item">
                      <i class="fas fa-university"></i>
                      <div><strong><?= esc($u['name']) ?></strong></div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </div>

          <div class="actions">
            <button class="action-btn-years edit-btn" data-id="<?= $year['id'] ?>">
              <i class="fas fa-edit"></i> تعديل
            </button>
            <button class="action-btn-years delete-btn" data-id="<?= $year['id'] ?>">
              <i class="fas fa-trash"></i> حذف
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Footer -->
  <div class="upg-card" style="text-align:center; padding:12px;">
    <div class="upg-card__body" style="padding:12px;">
      © <span id="currentYear"></span> منصة كيف التعليمية - جميع الحقوق محفوظة
    </div>
  </div>
</section>

<script>
  // سنة الفوتر
  document.getElementById("currentYear").textContent = new Date().getFullYear();
</script>
