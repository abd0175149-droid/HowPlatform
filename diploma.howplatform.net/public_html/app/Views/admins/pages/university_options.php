<!-- =========================================================
     Universities & Specialties — Unified Admin Design
     ✳️ مطابق لتوكِن الألوان ومنطق صفحة "خيارات العام الدراسي"
     ✳️ نحافظ على نفس الـ IDs حتى لا ينكسر الـ JS
========================================================= -->


<section id="universitiesPage" class="upg-page">
  <!-- Header -->
  <header class="ap-head">
    <div class="ap-head__title">
      <div class="ap-head__icon"><i class="fas fa-university" aria-hidden="true"></i></div>
      <div class="ap-head__text">
        <h2>إدارة الجامعات والتخصّصات</h2>
        <div class="ap-head__sub">إضافة جامعات وتخصّصات وربطهما</div>
      </div>
    </div>
    <div class="ap-head__actions">
      <span class="ap-counter"><i class="fas fa-sliders-h"></i>&nbsp;لوحة خيارات الموقع</span>
    </div>
  </header>

  <!-- Stats -->
  <div class="mu-stats">
    <div class="mu-card">
      <div class="mu-icon-wrap"><i class="fas fa-university" aria-hidden="true"></i></div>
      <div class="mu-meta">
        <span class="mu-label">عدد الجامعات</span>
        <span class="mu-value"><?= esc($universitiesCount ?? 0) ?></span>
      </div>
    </div>
    <div class="mu-card">
      <div class="mu-icon-wrap"><i class="fas fa-graduation-cap" aria-hidden="true"></i></div>
      <div class="mu-meta">
        <span class="mu-label">عدد التخصّصات</span>
        <span class="mu-value"><?= esc($specialtiesCount ?? 0) ?></span>
      </div>
    </div>
  </div>

  <!-- Panels: Add University / Add Specialty / Link -->
  <div class="uni-panels">
    <!-- إضافة جامعة -->
    <div class="upg-card">
      <div class="upg-card__head">
        <h3><i class="fas fa-plus-circle"></i> إضافة جامعة</h3>
      </div>
      <div class="upg-card__body">
        <div class="ap-form__grid">
          <div class="ap-field">
            <label for="uniName" class="ap-label"><i class="fas fa-university"></i> اسم الجامعة</label>
            <div class="ap-input-wrap">
              <i class="fas fa-university"></i>
              <input id="uniName" type="text" class="form-control" placeholder="مثال: جامعة دمشق">
            </div>
          </div>
          <div class="ap-field">
            <label for="diplomaTypeSelect" class="ap-label"><i class="fas fa-id-badge"></i> نوع الدبلوم</label>
            <div class="ap-input-wrap">
              <i class="fas fa-id-badge"></i>
              <select id="diplomaTypeSelect" class="form-control">
                <option value="">اختر نوع الدبلوم</option>
                <?php foreach ($diplomaOptions as $d): ?>
                  <option value="<?= $d['id'] ?>"><?= esc($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <button class="btn btn-primary"><i class="fas fa-save"></i> حفظ الجامعة</button>

        <h3 class="section-subtitle"><i class="fas fa-list"></i> قائمة الجامعات</h3>
        <div class="card-scroll">
          <ul id="universitiesList" class="options-list simple-list"></ul>
        </div>
      </div>
    </div>

    <!-- إضافة تخصّص -->
    <div class="upg-card">
      <div class="upg-card__head">
        <h3><i class="fas fa-book"></i> إضافة تخصّص</h3>
      </div>
      <div class="upg-card__body">
        <div class="ap-form__grid">
          <div class="ap-field">
            <label for="specName" class="ap-label"><i class="fas fa-graduation-cap"></i> اسم التخصّص</label>
            <div class="ap-input-wrap">
              <i class="fas fa-graduation-cap"></i>
              <input id="specName" type="text" class="form-control" placeholder="مثال: هندسة الحواسيب">
            </div>
          </div>
        </div>

        <button class="btn btn-primary"><i class="fas fa-save"></i> حفظ التخصّص</button>

        <h3 class="section-subtitle"><i class="fas fa-list"></i> قائمة التخصّصات</h3>
        <div class="card-scroll">
          <ul id="specialtiesList" class="options-list simple-list"></ul>
        </div>
      </div>
    </div>

    <!-- ربط جامعة بتخصّصات -->
    <div class="upg-card">
      <div class="upg-card__head">
        <h3><i class="fas fa-link"></i> ربط جامعات بتخصّصات</h3>
      </div>
      <div class="upg-card__body">
        <div class="ap-form__grid">
          <div class="ap-field">
            <label for="uniSelect" class="ap-label"><i class="fas fa-university"></i> الجامعة</label>
            <div class="ap-input-wrap">
              <i class="fas fa-university"></i>
              <select id="uniSelect" class="form-control">
                <option value="">— اختر —</option>
                <?php foreach ($uniOptions as $u): ?>
                  <option value="<?= $u['id'] ?>"><?= esc($u['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- صندوق التخصّصات (يحافظ على ID ليعمل الـ JS الحالي) -->
        <div id="specCheckboxes" class="uni-grid">
          <?php foreach ($specOptions as $s): ?>
            <label class="uni-chip" for="spec<?= $s['id'] ?>">
              <input type="checkbox" id="spec<?= $s['id'] ?>" value="<?= $s['id'] ?>">
              <span><?= esc($s['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <button class="btn btn-primary"><i class="fas fa-link"></i> حفظ الربط</button>

        <h3 class="section-subtitle"><i class="fas fa-list"></i> التخصّصات المرتبطة بالجامعة</h3>
        <div class="card-scroll">
          <ul id="linkedSpecsList" class="options-list simple-list"></ul>
        </div>
      </div>
    </div>
  </div>

  <!-- University Cards (3 per row on wide) -->
  <div class="upg-card">
    <div class="upg-card__head">
      <h3><i class="fas fa-list"></i> الجامعات</h3>
    </div>
    <div class="upg-card__body">
      <div id="uniCards" class="uni-cards-grid"><!-- ← يعتمد عليه JS للحذف الديناميكي -->
        <?php foreach ($universities as $uni): ?>
          <div class="uni-card">
            <div class="uni-card__header">
              <div class="uni-card__title">
                <i class="fas fa-university" aria-hidden="true"></i>
                <span class="name"><?= esc($uni['name']) ?></span>
              </div>
              <div class="uni-card__subtitle">
                <?= esc($uni['diploma'] ?: 'غير محدد') ?>
              </div>
            </div>

            <div class="uni-card__body">
              <h4 class="section-subtitle" style="margin:0;"><i class="fas fa-graduation-cap"></i> التخصّصات</h4>
              <ul class="uni-card__list">
                <?php foreach ($uni['specialties'] as $sp): ?>
                  <li><i class="fas fa-book"></i> <?= esc($sp['name']) ?></li>
                <?php endforeach; ?>
                <?php if (! $uni['specialties']): ?>
                  <li style="color:var(--muted)">لا تخصّصات بعد</li>
                <?php endif; ?>
              </ul>
            </div>

            <div class="uni-card__footer">
              <button class="action-btn-uni delete-btn" data-id="<?= $uni['id'] ?>">
                <i class="fas fa-trash"></i> حذف
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
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
  (function(){ var y = document.getElementById("currentYear"); if (y) y.textContent = new Date().getFullYear(); })();
</script>
