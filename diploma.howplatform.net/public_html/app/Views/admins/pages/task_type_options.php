<!-- =========================================================
     Task Types Options — Unified Admin Design
     (Matches Academic Years & Universities design tokens)
========================================================= -->

<section id="taskTypesPage" class="upg-page">
  <!-- Header -->
  <header class="ap-head">
    <div class="ap-head__title">
      <div class="ap-head__icon"><i class="fas fa-tags" aria-hidden="true"></i></div>
      <div class="ap-head__text">
        <h2>إدارة أنواع المهام</h2>
        <div class="ap-head__sub">إضافة أنواع مهام وحذفها للتحكم بتصنيفات المهام والواجبات بشكل مرن</div>
      </div>
    </div>
    <div class="ap-head__actions">
      <span class="ap-counter"><i class="fas fa-sliders-h"></i>&nbsp;لوحة خيارات الموقع</span>
    </div>
  </header>

  <!-- Stats -->
  <div class="mu-stats">
    <div class="mu-card">
      <div class="mu-icon-wrap"><i class="fas fa-tags" aria-hidden="true"></i></div>
      <div class="mu-meta dashboard-card"> <!-- يحتفظ بالكلاس dashboard-card ليتمكن JS من تحديث العداد -->
        <span class="mu-label">إجمالي أنواع المهام المسجلة</span>
        <span class="mu-value count"><?= esc($typesCount) ?></span>
      </div>
    </div>
  </div>

  <div class="ay-panels">
    <!-- panel-section required for task_type_options.js querySelector -->
    <div class="upg-card panel-section">
      <div class="upg-card__head">
        <h3><i class="fas fa-plus-circle"></i> إضافة تصنيف جديد</h3>
      </div>
      <div class="upg-card__body">
        
        <div class="ap-form__grid" style="grid-template-columns: 1fr;">
          <div class="ap-field">
            <label for="typeName" class="ap-label"><i class="fas fa-pen"></i> اسم النوع</label>
            <div class="ap-input-wrap">
              <i class="fas fa-tag"></i>
              <input id="typeName" type="text" class="form-control" placeholder="مثال: مشروع تقييمي، واجب منزلي...">
            </div>
          </div>
        </div>

        <button type="button" class="btn btn-primary" style="align-self: flex-start;"><i class="fas fa-save"></i> حفظ التصنيف</button>

        <!-- قائمة الأنواع للحذف -->
        <h3 class="section-subtitle" style="margin-top:0.5rem"><i class="fas fa-list"></i> قائمة التصنيفات المسجلة</h3>
        
        <!-- simple-list will automatically render as Chips in modern UI -->
        <ul id="typesList" class="simple-list">
          <?php foreach ($types as $t): ?>
            <li>
              <span><?= esc($t['name']) ?></span>
              <button data-id="<?= $t['id'] ?>" class="link-danger" title="حذف">
                <i class="fas fa-trash"></i>
              </button>
            </li>
          <?php endforeach; ?>
        </ul>

      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="upg-card" style="text-align:center; padding:12px; margin-top:24px;">
    <div class="upg-card__body" style="padding:12px; display:block;">
      © <span id="currentYear"></span> منصة كيف التعليمية - جميع الحقوق محفوظة
    </div>
  </div>
</section>

<script>
  (function(){ var y = document.getElementById("currentYear"); if (y) y.textContent = new Date().getFullYear(); })();
</script>
