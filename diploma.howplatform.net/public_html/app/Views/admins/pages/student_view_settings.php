<?php
/**
 * واجهة إعدادات استعراض واجهة الطالب للأدمن
 */
use App\Helpers\StudentViewHelper;
use Config\Database;

// جلب البيانات مباشرة من PHP
$db = Database::connect();

$diplomaTypes = $db->table('diploma_types')
    ->select('id, name')
    ->orderBy('name', 'ASC')
    ->get()
    ->getResultArray();

$universities = $db->table('universities')
    ->select('id, name')
    ->orderBy('name', 'ASC')
    ->get()
    ->getResultArray();

$specialties = $db->table('specialties')
    ->select('id, name')
    ->orderBy('name', 'ASC')
    ->get()
    ->getResultArray();

$academicYears = $db->table('academic_years')
    ->select('id, label as name')
    ->orderBy('id', 'ASC')
    ->get()
    ->getResultArray();

$universityDiplomaTypes = $db->table('university_diploma_types')
    ->select('diploma_type_id, university_id')
    ->get()
    ->getResultArray();

$universitySpecialties = $db->table('university_specialties')
    ->select('university_id, specialty_id')
    ->get()
    ->getResultArray();

$universityAcademicYears = $db->table('university_academic_years')
    ->select('university_id, academic_year_id')
    ->get()
    ->getResultArray();

// جلب الإعدادات الحالية إن وجدت
$currentSettings = StudentViewHelper::getSavedPreferences();
?>
<div class="student-view-settings" data-student-view-settings>
  <div class="settings-header">
    <h2><i class="fas fa-user-graduate"></i> استعراض واجهة الطالب</h2>
    <p class="settings-description">
      اختر إعدادات الطالب الافتراضي لاستعراض واجهة الطالب كما يراها الطالب تماماً
    </p>
  </div>

  <form id="studentViewSettingsForm" class="settings-form">
    <div class="form-grid">
      <!-- نوع الدبلوم -->
      <div class="form-group">
        <label for="diploma_type_id" class="form-label">
          <i class="fas fa-clipboard-list"></i> نوع الدبلوم:
        </label>
        <select name="diploma_type_id" id="diploma_type_id" class="form-select" required>
          <option value="">اختر نوع الدبلوم</option>
          <?php foreach ($diplomaTypes as $dt): ?>
            <option value="<?= $dt['id'] ?>" <?= ($currentSettings['diploma_type_id'] ?? null) == $dt['id'] ? 'selected' : '' ?>>
              <?= esc($dt['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small class="form-hint">اختر نوع الدبلوم أولاً</small>
      </div>

      <!-- الجامعة -->
      <div class="form-group">
        <label for="university_id" class="form-label">
          <i class="fas fa-university"></i> الجامعة:
        </label>
        <select name="university_id" id="university_id" class="form-select" required>
          <option value="">اختر الجامعة</option>
          <?php 
          // عرض الجامعات المرتبطة بنوع الدبلوم المختار (سيتم تحديثها ديناميكياً)
          $selectedDiplomaId = $currentSettings['diploma_type_id'] ?? null;
          if ($selectedDiplomaId) {
            $allowedUniIds = array_column(
              array_filter($universityDiplomaTypes, fn($udt) => (int)$udt['diploma_type_id'] == (int)$selectedDiplomaId),
              'university_id'
            );
            foreach ($universities as $uni) {
              if (in_array((int)$uni['id'], array_map('intval', $allowedUniIds))) {
                $selected = (($currentSettings['university_id'] ?? null) == $uni['id']) ? 'selected' : '';
                echo '<option value="' . $uni['id'] . '" ' . $selected . '>' . esc($uni['name']) . '</option>';
              }
            }
          }
          ?>
        </select>
        <small class="form-hint">يتم تعبئتها بناءً على نوع الدبلوم</small>
      </div>

      <!-- التخصص -->
      <div class="form-group">
        <label for="specialty_id" class="form-label">
          <i class="fas fa-graduation-cap"></i> التخصص:
        </label>
        <select name="specialty_id" id="specialty_id" class="form-select">
          <option value="">اختر التخصص (اختياري)</option>
          <?php 
          // عرض التخصصات المرتبطة بالجامعة المختارة (سيتم تحديثها ديناميكياً)
          $selectedUniId = $currentSettings['university_id'] ?? null;
          if ($selectedUniId) {
            $allowedSpecIds = array_column(
              array_filter($universitySpecialties, fn($us) => (int)$us['university_id'] == (int)$selectedUniId),
              'specialty_id'
            );
            foreach ($specialties as $spec) {
              if (in_array((int)$spec['id'], array_map('intval', $allowedSpecIds))) {
                $selected = (($currentSettings['specialty_id'] ?? null) == $spec['id']) ? 'selected' : '';
                echo '<option value="' . $spec['id'] . '" ' . $selected . '>' . esc($spec['name']) . '</option>';
              }
            }
          }
          ?>
        </select>
        <small class="form-hint">يتم تعبئتها بناءً على الجامعة</small>
      </div>

      <!-- السنة الأكاديمية -->
      <div class="form-group">
        <label for="academic_year_id" class="form-label">
          <i class="fas fa-calendar-alt"></i> السنة الأكاديمية:
        </label>
        <select name="academic_year_id" id="academic_year_id" class="form-select" required>
          <option value="">اختر السنة الأكاديمية</option>
          <?php 
          // عرض السنوات المرتبطة بالجامعة المختارة (سيتم تحديثها ديناميكياً)
          $selectedUniId = $currentSettings['university_id'] ?? null;
          if ($selectedUniId) {
            $allowedYearIds = array_column(
              array_filter($universityAcademicYears, fn($uay) => (int)$uay['university_id'] == (int)$selectedUniId),
              'academic_year_id'
            );
            foreach ($academicYears as $year) {
              if (in_array((int)$year['id'], array_map('intval', $allowedYearIds))) {
                $selected = (($currentSettings['academic_year_id'] ?? null) == $year['id']) ? 'selected' : '';
                echo '<option value="' . $year['id'] . '" ' . $selected . '>' . esc($year['name']) . '</option>';
              }
            }
          }
          ?>
        </select>
        <small class="form-hint">يتم تعبئتها بناءً على الجامعة</small>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" id="startViewBtn" class="btn btn-primary">
        <i class="fas fa-eye"></i> بدء الاستعراض
      </button>
    </div>

    <div id="settingsMessage" class="message-box" style="display: none;"></div>
  </form>

  <!-- حالة الاستعراض الحالية -->
  <div id="currentViewStatus" class="current-status" style="display: none;">
    <div class="status-header">
      <h3><i class="fas fa-info-circle"></i> حالة الاستعراض الحالية</h3>
    </div>
    <div class="status-content">
      <div class="status-item">
        <span class="status-label">الجامعة:</span>
        <span id="currentUniversity" class="status-value">--</span>
      </div>
      <div class="status-item">
        <span class="status-label">التخصص:</span>
        <span id="currentSpecialty" class="status-value">--</span>
      </div>
      <div class="status-item">
        <span class="status-label">السنة الأكاديمية:</span>
        <span id="currentAcademicYear" class="status-value">--</span>
      </div>
      <div class="status-item">
        <span class="status-label">نوع الدبلوم:</span>
        <span id="currentDiplomaType" class="status-value">--</span>
      </div>
      <div class="status-actions">
        <button type="button" id="stopViewBtn" class="btn btn-danger">
          <i class="fas fa-stop"></i> إنهاء الاستعراض
        </button>
        <a href="<?= site_url('students/') ?>" class="btn btn-primary" target="_blank">
          <i class="fas fa-external-link-alt"></i> فتح واجهة الطالب
        </a>
      </div>
    </div>
  </div>
</div>

<script>
// تمرير البيانات للـ JavaScript
window.STUDENT_VIEW_DATA = <?= json_encode([
  'diploma_types' => $diplomaTypes,
  'universities' => $universities,
  'specialties' => $specialties,
  'academic_years' => $academicYears,
  'university_diploma_types' => $universityDiplomaTypes,
  'university_specialties' => $universitySpecialties,
  'university_academic_years' => $universityAcademicYears,
], JSON_UNESCAPED_UNICODE) ?>;

// تمرير الإعدادات المحفوظة على الـ server (من session) إلى JS
// هذا هو المرجع الحقيقي وله الأولوية على localStorage
window.STUDENT_VIEW_SERVER_SETTINGS = <?= json_encode($currentSettings, JSON_UNESCAPED_UNICODE) ?>;
</script>

<style>
.student-view-settings {
  padding: 24px;
  background: var(--surface-0);
  border-radius: 12px;
  box-shadow: var(--shadow-md);
}

.settings-header {
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 2px solid var(--border-strong);
}

.settings-header h2 {
  margin: 0 0 8px 0;
  font-size: 24px;
  color: var(--text-strong);
  display: flex;
  align-items: center;
  gap: 12px;
}

.settings-description {
  margin: 0;
  color: var(--text-muted);
  font-size: 14px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 24px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--text-strong);
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.form-select {
  padding: 10px 12px;
  border: 1px solid var(--border-strong);
  border-radius: 8px;
  font-size: 14px;
  background: var(--surface-0);
  color: var(--text-strong); /* أضفنا هذا */
  transition: border-color 0.2s;
}

.form-select:focus {
  outline: none;
  border-color: var(--primary-500);
  box-shadow: var(--focus-ring);
}

.form-select option { /* أضفنا هذا لتحديد لون نص الخيارات في الدارك مود */
  background: var(--surface-0);
  color: var(--text-strong);
}

.form-hint {
  margin-top: 4px;
  font-size: 12px;
  color: var(--text-muted);
}

.form-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding-top: 16px;
  border-top: 1px solid var(--border-strong);
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
}

.btn-secondary {
  background: var(--neutral-600); /* تعديل من لون ثابت */
  color: var(--text-inverse);
}

.btn-secondary:hover {
  background: var(--neutral-700); /* تعديل من لون ثابت */
}

.btn-danger {
  background: var(--error-500); /* تعديل من لون ثابت */
  color: var(--text-inverse);
}

.btn-danger:hover {
  background: var(--error-600); /* تعديل من لون ثابت */
}

.message-box {
  margin-top: 16px;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
}

.message-box.success {
  background: var(--success-soft);
  color: var(--success-600);
  border: 1px solid var(--success-border);
}

.message-box.error {
  background: var(--error-soft);
  color: var(--error-600);
  border: 1px solid var(--error-border);
}

.current-status {
  margin-top: 24px;
  padding: 20px;
  background: var(--surface-1);
  border-radius: 8px;
  border: 1px solid var(--border-color);
}

.status-header h3 {
  margin: 0 0 16px 0;
  font-size: 18px;
  color: var(--text-strong);
  display: flex;
  align-items: center;
  gap: 8px;
}

.status-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.status-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid var(--border-color);
}

.status-item:last-child {
  border-bottom: none;
}

.status-label {
  font-weight: 600;
  color: var(--text-muted);
}

.status-value {
  color: var(--text-strong);
  font-weight: 500;
}

.status-actions {
  margin-top: 16px;
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}
</style>

