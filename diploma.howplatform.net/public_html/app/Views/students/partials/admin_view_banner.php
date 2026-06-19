<?php
/**
 * Banner للاستعراض كأدمن
 * يظهر فقط عندما يكون الأدمن في وضع استعراض واجهة الطالب
 */
use App\Helpers\StudentViewHelper;

if (!StudentViewHelper::isAdminViewingAsStudent()) {
    return;
}

$prefs = StudentViewHelper::getStudentViewPreferences();
$adminInfo = StudentViewHelper::getOriginalAdminInfo();

// جلب أسماء القيم من قاعدة البيانات
$db = \Config\Database::connect();
$universityName = '--';
$specialtyName = '--';
$academicYearName = '--';
$diplomaTypeName = '--';

if (!empty($prefs['university_id'])) {
    $uni = $db->table('universities')->select('name')->where('id', $prefs['university_id'])->get()->getRow();
    $universityName = $uni ? $uni->name : '--';
}

if (!empty($prefs['specialty_id'])) {
    $spec = $db->table('specialties')->select('name')->where('id', $prefs['specialty_id'])->get()->getRow();
    $specialtyName = $spec ? $spec->name : '--';
}

if (!empty($prefs['academic_year_id'])) {
    $year = $db->table('academic_years')->select('label')->where('id', $prefs['academic_year_id'])->get()->getRow();
    $academicYearName = $year ? $year->label : '--';
}

if (!empty($prefs['diploma_type_id'])) {
    $diploma = $db->table('diploma_types')->select('name')->where('id', $prefs['diploma_type_id'])->get()->getRow();
    $diplomaTypeName = $diploma ? $diploma->name : '--';
}
?>
<div id="admin-view-banner" class="admin-view-banner">
  <div class="banner-content">
    <div class="banner-header">
      <div class="banner-icon">
        <i class="fas fa-user-shield"></i>
      </div>
      <div class="banner-title">
        <h3>أنت تستعرض واجهة الطالب كأدمن</h3>
        <p class="banner-subtitle">الإعدادات الحالية:</p>
      </div>
      <button type="button" id="toggle-banner-btn" class="banner-toggle-btn" aria-label="إخفاء/إظهار البانر">
        <i class="fas fa-chevron-up" id="toggle-banner-icon"></i>
      </button>
    </div>
    
    <div class="banner-body" id="banner-body">
      <div class="banner-settings">
        <div class="setting-item">
          <span class="setting-label">الجامعة:</span>
          <span class="setting-value" id="current-university"><?= esc($universityName) ?></span>
        </div>
        <div class="setting-item">
          <span class="setting-label">التخصص:</span>
          <span class="setting-value" id="current-specialty"><?= esc($specialtyName) ?></span>
        </div>
        <div class="setting-item">
          <span class="setting-label">السنة الأكاديمية:</span>
          <span class="setting-value" id="current-academic-year"><?= esc($academicYearName) ?></span>
        </div>
        <div class="setting-item">
          <span class="setting-label">نوع الدبلوم:</span>
          <span class="setting-value" id="current-diploma-type"><?= esc($diplomaTypeName) ?></span>
        </div>
      </div>

      <div class="banner-actions">
        <button type="button" id="edit-settings-btn" class="btn-edit">
          <i class="fas fa-edit"></i> تعديل الإعدادات
        </button>
        <button type="button" id="stop-view-btn" class="btn-stop">
          <i class="fas fa-stop"></i> إنهاء الاستعراض والعودة للأدمن
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.admin-view-banner {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 10000;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  padding: 2px 20px;
}

.banner-content {
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.banner-header {
  display: flex;
  align-items: center;
  gap: 12px;
  justify-content: space-between;
  cursor: pointer;
  user-select: none;
}

.banner-toggle-btn {
  background: rgba(255,255,255,0.2);
  border: 1px solid rgba(255,255,255,0.3);
  color: #fff;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
  z-index: 10;
  position: relative;
}

.banner-toggle-btn:focus {
  outline: 2px solid rgba(255,255,255,0.5);
  outline-offset: 2px;
}

.banner-toggle-btn:hover {
  background: rgba(255,255,255,0.3);
  transform: scale(1.05);
}

.banner-toggle-btn i {
  transition: transform 0.3s ease;
}

.admin-view-banner.collapsed .banner-toggle-btn i {
  transform: rotate(180deg);
}

.banner-body {
  transition: max-height 0.3s ease, opacity 0.3s ease;
  overflow: hidden;
  max-height: 500px;
  opacity: 1;
}

.admin-view-banner.collapsed .banner-body {
  max-height: 0;
  opacity: 0;
  padding-top: 0;
  padding-bottom: 0;
}

.banner-icon {
  font-size: 24px;
  opacity: 0.9;
}

.banner-title h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
}

.banner-subtitle {
  margin: 4px 0 0 0;
  font-size: 13px;
  opacity: 0.9;
}

.banner-settings {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  padding: 12px;
  background: rgba(255,255,255,0.1);
  border-radius: 8px;
  backdrop-filter: blur(10px);
}

.setting-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.setting-label {
  font-size: 12px;
  opacity: 0.8;
  font-weight: 500;
}

.setting-value {
  font-size: 14px;
  font-weight: 600;
}

.banner-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

.btn-edit,
.btn-stop {
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

.btn-edit {
  background: rgba(255,255,255,0.2);
  color: #fff;
  border: 1px solid rgba(255,255,255,0.3);
}

.btn-edit:hover {
  background: rgba(255,255,255,0.3);
}

.btn-stop {
  background: #dc2626;
  color: #fff;
}

.btn-stop:hover {
  background: #b91c1c;
}

@media (max-width: 768px) {
  .banner-settings {
    grid-template-columns: 1fr;
  }
  
  .banner-actions {
    flex-direction: column;
  }
  
  .btn-edit,
  .btn-stop {
    width: 100%;
    justify-content: center;
  }
}
</style>

