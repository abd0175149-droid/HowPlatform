<?php
/**
 * Admin Dashboard
 * - التبويبات: الحسابات / الدبلوم / المكتبة / نقاط البيع / الأدوات
 * - قسم الدبلوم نشط افتراضيًا
 * - ألوان/ستايل متناغم مع صفحات الإعدادات (يعتمد على متغيرات CSS لديك)
 */
?>
<div class="admin-dashboard" data-admin-dashboard>
  <!-- Header -->
  <div class="header">
    <h1>لوحة تحكم الإدارة</h1>
    <div class="date-info">
      <i class="fas fa-calendar-alt"></i>
      <span id="current-date">--</span>
    </div>
  </div>

  <!-- Tabs -->
  <div class="db-tabs" role="tablist" aria-label="أقسام الإحصاءات">
    <button type="button" class="db-tab active" data-db="diploma" aria-selected="true">
      <i class="fas fa-user-graduate"></i> الدبلوم
    </button>
    <button type="button" class="db-tab" data-db="library" aria-selected="false">
      <i class="fas fa-book"></i> المكتبة
    </button>
    <button type="button" class="db-tab" data-db="tools" aria-selected="false">
      <i class="fas fa-tools"></i> الأدوات <span class="beta-chip">BETA</span>
    </button>
    <button type="button" class="db-tab" data-db="student-view" aria-selected="false">
      <i class="fas fa-user-graduate"></i> استعراض واجهة الطالب
    </button>
  </div>

  <!-- Sections -->
  <div class="sections-stack">

    <!-- Section: Diploma (DEFAULT ACTIVE) -->
    <section class="stats-section is-active" data-db-section="diploma" aria-label="إحصائيات الدبلوم">
      <!-- (A) النشطون: Pie نشِط/غير نشِط -->
      <div class="stat-card" data-drag-card>
        <div class="stat-header drag-target">
          <h3 class="stat-title">النشطون</h3>
          <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
        </div>
        <div class="segmented-control" role="tablist" aria-label="نطاق النشاط">
          <button type="button" class="seg-btn active" data-window="day" aria-selected="true">اليوم</button>
          <button type="button" class="seg-btn" data-window="week" aria-selected="false">الأسبوع</button>
          <button type="button" class="seg-btn" data-window="month" aria-selected="false">الشهر</button>
        </div>
        <div class="chart-container is-pie">
          <canvas id="dipActiveBreakdownChart"></canvas>
        </div>
      </div>

      <!-- (B) اكتمال التسجيل: Doughnut -->
      <div class="stat-card" data-drag-card>
        <div class="stat-header drag-target">
          <h3 class="stat-title">اكتمال التسجيل</h3>
          <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        </div>
        <div class="chart-container is-pie">
          <canvas id="dipRegCompletionChart"></canvas>
        </div>
      </div>

      <!-- (F) المستخدمون النشطون: جدول + باجينيشن (واسع) -->
      <div class="stat-card is-wide" id="dipActiveUsersCard" data-drag-card>
        <div class="stat-header drag-target">
          <h3 class="stat-title">المستخدمون النشطون</h3>
          <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
        </div>

        <!-- أزرار النطاق -->
        <div class="segmented-control" role="tablist" aria-label="نطاق المستخدمين النشطين">
          <button type="button" class="seg-btn active" data-window="day" aria-selected="true">اليوم</button>
          <button type="button" class="seg-btn" data-window="week" aria-selected="false">الأسبوع</button>
          <button type="button" class="seg-btn" data-window="month" aria-selected="false">الشهر</button>
        </div>

        <!-- الجدول -->
        <div class="table-wrap" style="margin-top:10px; overflow:auto;">
          <table class="user-table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th>الاسم</th>
                <th>الجامعة</th>
                <th>التخصص</th>
                <th>آخر نشاط</th>
              </tr>
            </thead>
            <!-- نفس الـ ID ليستمر الـ JS بالعمل -->
            <tbody id="dipActiveUsersList">
              <!-- تُملأ عبر JS -->
            </tbody>
          </table>
        </div>

        <!-- الباجينيشن -->
        <div class="pagination" style="display:flex; gap:8px; align-items:center; justify-content:flex-start; margin-top:10px;">
          <button type="button" class="seg-btn" id="dipAUPrev" disabled>
  <i class="fas fa-angle-right"></i> السابق
</button>
<button type="button" class="seg-btn" id="dipAUNext" disabled>
  التالي <i class="fas fa-angle-left"></i>
</button>

          <span id="dipAUPaginationInfo" style="color:var(--text-muted); font-size:12px; margin-inline-start:6px;">لا سجلات</span>
        </div>
      </div>

      <!-- (C) توزيع المستخدمين حسب الجامعة -->
      <div class="stat-card" data-drag-card>
        <div class="stat-header drag-target">
          <h3 class="stat-title">توزيع المستخدمين حسب الجامعة</h3>
          <div class="stat-icon"><i class="fas fa-university"></i></div>
        </div>
        <div class="chart-container">
          <canvas id="dipUnivTotalsChart"></canvas>
        </div>
      </div>

      <!-- (D) توزيع الخطط الفعّالة -->
      <div class="stat-card" data-drag-card>
        <div class="stat-header drag-target">
          <h3 class="stat-title">توزيع الخطط الفعّالة</h3>
          <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
        </div>
        <div class="chart-container is-pie">
          <canvas id="dipPlanChart"></canvas>
        </div>
      </div>

      <!-- (E) النشطون حسب الجامعة -->
      <div class="stat-card" id="activeByUniversityCard" data-drag-card>
        <div class="stat-header drag-target">
          <h3 class="stat-title">النشطون حسب الجامعة</h3>
          <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
        </div>

        <div class="segmented-control" role="tablist" aria-label="نطاق النشطين حسب الجامعة">
          <button type="button" class="seg-btn active" data-window="day" aria-selected="true">اليوم</button>
          <button type="button" class="seg-btn" data-window="week" aria-selected="false">الأسبوع</button>
          <button type="button" class="seg-btn" data-window="month" aria-selected="false">الشهر</button>
        </div>

        <div class="chart-container">
          <canvas id="dipUnivChart"></canvas>
        </div>
      </div>

      <!-- (G) استخدام فَطين -->
      <div class="stat-card" data-drag-card>
        <div class="stat-header drag-target">
          <h3 class="stat-title">استخدام فَطين</h3>
          <div class="stat-icon"><i class="fas fa-robot"></i></div>
        </div>
        <div class="segmented-control" role="tablist" aria-label="نطاق الاستخدام">
          <button type="button" class="seg-btn active" data-bucket="day" aria-selected="true">يوم</button>
          <button type="button" class="seg-btn" data-bucket="month" aria-selected="false">شهر</button>
          <button type="button" class="seg-btn" data-bucket="year" aria-selected="false">سنة</button>
        </div>

        <div class="chart-container">
          <canvas id="dipFateenChart"></canvas>
        </div>
      </div>

      <!-- (H) إحصائيات مهام الذكاء الاصطناعي (ai_jobs) -->
      <div class="stat-card ai-jobs-kpi-card" data-drag-card>
        <div class="stat-header drag-target">
          <h3 class="stat-title">مهام الذكاء الاصطناعي</h3>
          <div class="stat-icon stat-icon--ai"><i class="fas fa-brain"></i></div>
        </div>

        <!-- KPIs رئيسية -->
        <div class="ai-kpi-grid">
          <div class="ai-kpi-item ai-kpi-primary">
            <div class="ai-kpi-icon"><i class="fas fa-bolt"></i></div>
            <div class="ai-kpi-body">
              <div class="ai-kpi-label">إجمالي الاستخدامات</div>
              <div class="ai-kpi-value" id="aiJobsTotalUses">--</div>
            </div>
          </div>
          <div class="ai-kpi-item ai-kpi-secondary">
            <div class="ai-kpi-icon"><i class="fas fa-users"></i></div>
            <div class="ai-kpi-body">
              <div class="ai-kpi-label">المستخدمون الفريدون</div>
              <div class="ai-kpi-value" id="aiJobsUniqueUsers">--</div>
            </div>
          </div>
          <div class="ai-kpi-item ai-kpi-success">
            <div class="ai-kpi-icon"><i class="fas fa-check-circle"></i></div>
            <div class="ai-kpi-body">
              <div class="ai-kpi-label">مكتملة</div>
              <div class="ai-kpi-value" id="aiJobsDoneCount">--</div>
            </div>
          </div>
          <div class="ai-kpi-item ai-kpi-danger">
            <div class="ai-kpi-icon"><i class="fas fa-times-circle"></i></div>
            <div class="ai-kpi-body">
              <div class="ai-kpi-label">فاشلة</div>
              <div class="ai-kpi-value" id="aiJobsFailedCount">--</div>
            </div>
          </div>
        </div>

        <!-- جدول توزيع حسب النوع -->
        <div class="ai-jobs-type-list" id="aiJobsByTypeList">
          <!-- تُملأ عبر JS -->
        </div>
      </div>
    </section>

    <!-- Library -->
    <section class="stats-section" data-db-section="library" aria-label="إحصائيات المكتبة" hidden>
      <div class="stat-card" id="libDownloadsCard">
        <div class="stat-header">
          <h3 class="stat-title">مرّات التحميل</h3>
          <div class="stat-icon"><i class="fas fa-download"></i></div>
        </div>
        <div class="segmented-control" role="tablist" aria-label="نطاق التحميل">
          <button type="button" class="seg-btn active" data-window="month" aria-selected="true">الشهر</button>
          <button type="button" class="seg-btn" data-window="week" aria-selected="false">الأسبوع</button>
          <button type="button" class="seg-btn" data-window="day" aria-selected="false">اليوم</button>
          <button type="button" class="seg-btn" data-window="year" aria-selected="false">السنة</button>
        </div>
        <div class="kpis" style="grid-template-columns: repeat(2, 1fr); margin-top: 8px;">
          <div class="kpi">
            <div class="kpi-label">الإجمالي</div>
            <div class="kpi-value" id="libKpiDownloadsOverall">--</div>
          </div>
          <div class="kpi">
            <div class="kpi-label">خلال الفترة</div>
            <div class="kpi-value" id="libKpiDownloadsWindow">--</div>
          </div>
        </div>
      </div>

      <div class="stat-card" id="libVisitsCard">
        <div class="stat-header">
          <h3 class="stat-title">زيارات الموقع</h3>
          <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        </div>
        <div class="segmented-control" role="tablist" aria-label="نطاق الزيارات">
          <button type="button" class="seg-btn active" data-window="month" aria-selected="true">الشهر</button>
          <button type="button" class="seg-btn" data-window="week" aria-selected="false">الأسبوع</button>
          <button type="button" class="seg-btn" data-window="day" aria-selected="false">اليوم</button>
          <button type="button" class="seg-btn" data-window="year" aria-selected="false">السنة</button>
        </div>
        <div class="kpis" style="grid-template-columns: 1fr; margin-top: 8px;">
          <div class="kpi">
            <div class="kpi-label">الخلاصة خلال الفترة</div>
            <div class="kpi-value" id="libKpiVisitsWindow">--</div>
          </div>
        </div>
      </div>

      <div class="stat-card" id="libTopGradesCard">
        <div class="stat-header">
          <h3 class="stat-title">الصفوف الأعلى تحميلًا</h3>
          <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
        </div>
        <div class="segmented-control" role="tablist" aria-label="نطاق الصفوف">
          <button type="button" class="seg-btn active" data-window="month" aria-selected="true">الشهر</button>
          <button type="button" class="seg-btn" data-window="week" aria-selected="false">الأسبوع</button>
          <button type="button" class="seg-btn" data-window="day" aria-selected="false">اليوم</button>
          <button type="button" class="seg-btn" data-window="year" aria-selected="false">السنة</button>
          <button type="button" class="seg-btn" data-mode="raw" aria-pressed="true" title="إجمالي">إجمالي</button>
          <button type="button" class="seg-btn" data-mode="normalized" aria-pressed="false" title="معدل/ملف">معدل/ملف</button>
        </div>
        <div class="chart-container">
          <canvas id="libTopGradesChart"></canvas>
        </div>
      </div>

      <div class="stat-card" id="libAuthAnonCard">
        <div class="stat-header">
          <h3 class="stat-title">تنزيلات: موثّق مقابل مجهول</h3>
          <div class="stat-icon"><i class="fas fa-user-lock"></i></div>
        </div>
        <div class="segmented-control" role="tablist" aria-label="نطاق المقارنة">
          <button type="button" class="seg-btn active" data-window="month" aria-selected="true">الشهر</button>
          <button type="button" class="seg-btn" data-window="week" aria-selected="false">الأسبوع</button>
          <button type="button" class="seg-btn" data-window="day" aria-selected="false">اليوم</button>
          <button type="button" class="seg-btn" data-window="year" aria-selected="false">السنة</button>
        </div>
        <div class="chart-container is-pie">
          <canvas id="libAuthAnonChart"></canvas>
        </div>
      </div>
    </section>

    <!-- Section: Student View -->
    <section class="stats-section" data-db-section="student-view" aria-label="استعراض واجهة الطالب" hidden>
      <?= view('admins/pages/student_view_settings') ?>
    </section>

    <!-- Tools (each tool is its own stat-card at section level) -->
    <section class="stats-section" data-db-section="tools" aria-label="إحصائيات الأدوات" hidden>
      <!-- بطاقة التحكم (ثابتة) -->
      <div class="stat-card" id="tools-controls-card">
        <div class="stat-header">
          <h3 class="stat-title">إحصائيات الأدوات</h3>
          <div class="stat-icon"><i class="fas fa-tools"></i></div>
        </div>

        <div class="segmented-control" role="group" aria-label="خيارات عرض الأدوات" style="gap:8px; margin-bottom:10px;">
          <label class="seg-btn" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
            <input type="checkbox" id="toolsOnlyActive" checked style="margin:0;" />
            <span>الأدوات المفعّلة فقط</span>
          </label>

          <label class="seg-btn" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
            <span>حدّ العرض</span>
            <select id="toolsLimit" style="border:none; background:transparent; outline:none;">
              <option value="">الكل</option>
              <option>8</option>
              <option selected>12</option>
              <option>16</option>
              <option>24</option>
            </select>
          </label>

          <button type="button" class="seg-btn" id="toolsRefreshBtn" title="تحديث">
            <i class="fas fa-rotate"></i> تحديث
          </button>
        </div>
        <div class="placeholder">سيتم تحميل بطاقات الأدوات أسفل هذا الكارد…</div>
      </div>
      <!-- 🧷 بطاقات الأدوات ستُدرج هنا كأشقاء بواسطة الـ JS -->
    </section>

  </div>

  <div class="footer">
    <p>© <span id="currentYear"></span> منصة كيف التعليمية - جميع الحقوق محفوظة</p>
  </div>
</div>

<!-- CSS -->
<style>
/* ===== رأس الصفحة ===== */
.admin-dashboard .header{
  display:flex; justify-content:space-between; align-items:center;
  margin-bottom:24px; padding:0 12px;
}
.admin-dashboard .header h1{
  font-size:1.8rem; font-weight:800;
  color:var(--text-strong);
}
.admin-dashboard .date-info{
  background:var(--surface-0); padding:8px 14.4px; border-radius:40px;
  box-shadow:var(--shadow-sm); color:var(--text-muted);
  display:flex; gap:6.4px; align-items:center;
}

/* إصلاح تباين header و date-info في الوضع الداكن */
html[data-theme="dark"] .admin-dashboard .date-info {
  color: var(--text-muted) !important;
  background: var(--surface-0) !important;
}

/* ===== التبويبات ===== */
.db-tabs{ display:flex; gap:8px; margin:6.4px 0 16px; flex-wrap:wrap; }
.db-tab{
  background:var(--surface-0);
  border:0.8px solid var(--border-color);
  padding:6.4px 11.2px; border-radius:799.2px; cursor:pointer; font-weight:600;
  display:flex; align-items:center; gap:6.4px;
  transition:background .2s, border-color .2s, transform .15s, color .2s;
  color:var(--text);
}
.db-tab:hover{ transform:translateY(-0.8px); border-color:var(--primary-300); }
.db-tab.active{ background:var(--primary-50); border-color:var(--primary-300); color:var(--primary-800); }
.db-tab:focus-visible{ outline:none; box-shadow:var(--focus-ring); }
.beta-chip{
  margin-inline-start:4.8px; font-size:8.8px; padding:1.6px 6.4px; border-radius:799.2px;
  background:var(--primary-50); color:var(--primary-700);
  border:0.8px solid var(--primary-300); font-weight:700;
}
html[data-theme="dark"] .beta-chip{
  background:var(--primary-50) !important; color:var(--primary-500) !important;
  border-color:var(--primary-300) !important;
}

/* إصلاح تباين التبويبات في الوضع الداكن */
html[data-theme="dark"] .db-tab {
  color: var(--text) !important;
  background: var(--surface-0) !important;
  border-color: var(--border-color) !important;
}

html[data-theme="dark"] .db-tab.active {
  color: var(--primary-500) !important;
  background: var(--primary-50) !important;
  border-color: var(--primary-300) !important;
}

/* ===== الأقسام (إظهار/إخفاء صحيح) ===== */
.sections-stack{ position:relative; }
.stats-section{
  display:none;
  grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
  gap:16px; margin-bottom:25.6px;
  align-items: start; /* البطاقات بارتفاعها الطبيعي → لا فراغ ميّت أسفل الرسوم */
}
.stats-section.is-active{ display:grid; }

/* ===== بطاقات الإحصائيات ===== */
.stat-card{
  background:var(--surface-0);
  border:0.8px solid var(--border-color);
  border-radius:var(--border-radius);
  padding:19.2px; box-shadow:var(--shadow-sm);
  display:flex; flex-direction:column;
  /* فلات: بدون تحوّل عند hover */
  min-height: 0; /* لا ارتفاع إجباري → البطاقة بحجم محتواها */
  box-sizing: border-box;
}
/* ❌ إلغاء hover transform على البطاقات (تصميم فلات) */
.stat-card:hover{ transform:none; box-shadow:var(--shadow-sm); }

/* ✔️ دراج هاندل */
.drag-handle {
  cursor: grab;
  color: var(--text-muted);
  padding: 3.2px 4.8px;
  border-radius: 4.8px;
  font-size: 11.2px;
  opacity: 0.45;
  transition: opacity .15s, background .15s;
  line-height: 1;
  user-select: none;
}
.drag-handle:hover { opacity: 1; background: var(--surface-1); }

/* ✔️ هيدر البطاقة = منطقة السحب */
.drag-target {
  cursor: grab;
  user-select: none;
  -webkit-user-select: none;
  touch-action: none;          /* منع تدخل التمرير اللمسي */
}
.drag-target:active { cursor: grabbing; }
/* لا تغيّر cursor على العناصر التفاعلية داخل الهيدر */
.drag-target button,
.drag-target .stat-icon { cursor: default; }

/* ✔️ أيقونة السحب (Grip) */
.dnd-grip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  color: var(--text-muted);
  opacity: 0.45;
  font-size: 11.2px;
  cursor: grab;
  flex-shrink: 0;
  transition: opacity .15s;
  user-select: none;
  -webkit-user-select: none;
}
.drag-target:hover .dnd-grip { opacity: 0.9; }
.dnd-grip:active { cursor: grabbing; }

/* ✔️ Ghost: نسخة شبح تتبع المؤشر */
.dnd-ghost {
  position: fixed !important;
  z-index: 9999;
  pointer-events: none;
  opacity: 0.88;
  transform: rotate(1.5deg) scale(1.03);
  box-shadow: 0 12.8px 32px rgba(0,0,0,0.22);
  border-radius: var(--border-radius);
  background: var(--surface-0);
  border: 1.2px solid var(--primary-300);
  transition: none;
  will-change: top, left;
}

/* ✔️ Placeholder: مساحة فارغة مكان البطاقة المسحوبة */
.dnd-placeholder {
  background: transparent;
  border: 1.6px dashed var(--primary-300);
  border-radius: var(--border-radius);
  opacity: 0.5;
  transition: none;
}

/* ✔️ Drop Indicator: خط أزرق بين البطاقات يوضح مكان الإدراج */
.dnd-indicator {
  height: 3.2px;
  background: var(--primary-500);
  border-radius: 3.2px;
  margin: -1.6px 0;
  pointer-events: none;
  z-index: 100;
  grid-column: 1 / -1;   /* يمتد عبر كل الأعمدة */
  position: relative;
  animation: indPulse 0.6s ease infinite alternate;
}
@keyframes indPulse {
  from { opacity: 1; }
  to   { opacity: 0.5; }
}

/* تغييم داكن */
html[data-theme="dark"] .dnd-ghost {
  background: var(--surface-1) !important;
  border-color: var(--primary-400) !important;
  box-shadow: 0 12.8px 32px rgba(0,0,0,0.45);
}

/* حاوية الرسم البياني — ارتفاع موحّد */
.chart-container {
  position: relative;
  width: 100%;
  height: 176px;       /* ← ارتفاع موحّد لكل Bar/Line charts */
  flex: 1 1 auto;
  min-height: 0;       /* أداة سحرية لمنع Flexbox من تمديد العنصر بشكل لا نهائي */
}
.chart-container > canvas {
  width: 100% !important;
  max-height: 100%;    /* استبدال height:100% بـ max-height لكي لا يتضارب مع Chart.js */
  display: block;
}
.stat-header{
  display:flex; justify-content:space-between; align-items:center; margin-bottom:11.2px;
}
.stat-title{
  font-size:1.05rem; font-weight:700; color:var(--text-strong);
}

/* إصلاح تباين العنوان في الوضع الداكن والفاتح */
html[data-theme="dark"] .stat-title {
  color: var(--text-strong) !important;
}

html[data-theme="light"] .stat-title,
html:not([data-theme]) .stat-title {
  color: var(--text-strong) !important;
}

.stat-icon{
  width:36.8px; height:36.8px; border-radius:11.2px;
  background:var(--primary-50);
  display:flex; align-items:center; justify-content:center;
  color:var(--primary); font-size:1.3rem;
}

/* إصلاح تباين بطاقات الإحصائيات في الوضع الداكن */
html[data-theme="dark"] .stat-card {
  background: var(--surface-0) !important;
  border-color: var(--border-color) !important;
}

html[data-theme="dark"] .stat-card .stat-title,
html[data-theme="dark"] .stat-card .stat-header {
  color: var(--text-strong) !important;
}

/* Loading State for cards */
.stat-card[aria-busy="true"] {
  position: relative;
  pointer-events: none;
}
.stat-card[aria-busy="true"]::after {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--surface-0);
  opacity: 0.55;
  border-radius: var(--border-radius);
  z-index: 10;
}
/* تأثير شمر (skeleton) لطيف يمرّ فوق البطاقة بدل السبنر الصاخب */
.stat-card[aria-busy="true"]::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: var(--border-radius);
  z-index: 11;
  background: linear-gradient(
    100deg,
    transparent 20%,
    color-mix(in oklab, var(--primary-300) 22%, transparent) 50%,
    transparent 80%
  );
  background-size: 200% 100%;
  animation: skeletonSweep 1.2s ease-in-out infinite;
  pointer-events: none;
}
@keyframes skeletonSweep {
  from { background-position: 180% 0; }
  to   { background-position: -80% 0; }
}
html[data-theme="dark"] .stat-card[aria-busy="true"]::after { opacity: 0.45; }

/* Placeholder */
.placeholder{
  padding:12.8px; border:0.8px dashed var(--primary-200);
  border-radius:9.6px; background:var(--surface-1);
  color:var(--text-muted); line-height:1.8;
}
.placeholder strong{ color:var(--primary-darker); }

/* إصلاح تباين placeholder في الوضع الداكن */
html[data-theme="dark"] .placeholder {
  color: var(--text-muted) !important;
  background: var(--surface-1) !important;
}

html[data-theme="dark"] .placeholder strong {
  color: var(--primary-500) !important;
}

/* KPIs */
.kpis{ display:grid; grid-template-columns:repeat(3, 1fr); gap:11.2px; margin:6.4px 0 11.2px; }
.kpi{
  background:var(--surface-0); border:0.8px solid var(--border-color);
  border-radius:9.6px; padding:9.6px; box-shadow:var(--shadow-sm);
}
.kpi-label{ 
  color:var(--text-muted); 
  font-size:10.4px; 
  margin-bottom:4.8px; 
}
.kpi-value{ 
  font-size:1.3rem; 
  font-weight:800; 
  color:var(--text-strong); 
}

/* إصلاح تباين الألوان في الوضع الداكن والفاتح */
html[data-theme="dark"] .kpi-label,
html[data-theme="dark"] .kpi-value {
  color: var(--text-strong) !important;
}

html[data-theme="dark"] .kpi {
  background: var(--surface-1) !important;
  border-color: var(--border-strong) !important;
}

html[data-theme="light"] .kpi-label,
html:not([data-theme]) .kpi-label {
  color: var(--text-muted) !important;
}

html[data-theme="light"] .kpi-value,
html:not([data-theme]) .kpi-value {
  color: var(--text-strong) !important;
}

/* Segmented control */
.segmented-control{
  display:flex; gap:6.4px; margin:9.6px 0 0; flex-wrap:wrap;
}
.segmented-control .seg-btn{
  border:0.8px solid var(--border-color); background:var(--surface-0); color:var(--text);
  padding:4.8px 9.6px; border-radius:799.2px; font-size:10.4px; cursor:pointer;
  transition:background .2s, border-color .2s, color .2s, transform .15s;
}
.segmented-control .seg-btn:hover{ border-color:var(--primary-300); }
.segmented-control .seg-btn.active{
  background:var(--primary-50); border-color:var(--primary-300); color:var(--primary-800);
}
.segmented-control .seg-btn:focus-visible{ outline:none; box-shadow:var(--focus-ring); }

/* إصلاح تباين أزرار segmented control في الوضع الداكن */
html[data-theme="dark"] .segmented-control .seg-btn {
  color: var(--text) !important;
  background: var(--surface-0) !important;
  border-color: var(--border-color) !important;
}

html[data-theme="dark"] .segmented-control .seg-btn.active {
  color: var(--primary-500) !important;
  background: var(--primary-50) !important;
  border-color: var(--primary-300) !important;
}

/* حجم موحّد للرسوم الدائرية */
:root{ --chart-pie-size:192px; }
.chart-container.is-pie{
  height:var(--chart-pie-size); display:flex; align-items:center; justify-content:center;
}
.chart-container.is-pie > canvas{
  width:var(--chart-pie-size) !important; height:var(--chart-pie-size) !important;
  max-width:var(--chart-pie-size); max-height:var(--chart-pie-size);
  margin:0 auto; display:block;
}
@media (max-width:768px){ :root{ --chart-pie-size:200px; } }

/* أدوات: شبكة ثابتة 4 بطاقات (وتتحول حسب العرض) */
.stats-section[data-db-section="tools"]{ grid-template-columns:repeat(4, minmax(0, 1fr)); }
@media (max-width:1280px){
  .stats-section[data-db-section="tools"]{ grid-template-columns:repeat(3, minmax(0, 1fr)); }
}
@media (max-width:992px){
  .stats-section[data-db-section="tools"]{ grid-template-columns:repeat(2, minmax(0, 1fr)); }
}
@media (max-width:640px){
  .stats-section[data-db-section="tools"]{ grid-template-columns:1fr; }
}

/* ===== بطاقة أداة (تصميم مدمج فلات) ===== */
.tool-card{ min-height:auto; gap:11.2px; }

.tool-kpis{ display:grid; grid-template-columns:repeat(3, 1fr); gap:6.4px; margin-top:3.2px; }
.tool-kpi{
  background:var(--surface-1); border:0.8px solid var(--border-color);
  border-radius:8px; padding:8px 4.8px; text-align:center;
  display:flex; flex-direction:column; gap:2.4px; min-width:0;
}
.tool-kpi .tk-val{
  font-size:1.15rem; font-weight:800; color:var(--text-strong); line-height:1;
  display:inline-flex; align-items:center; justify-content:center; gap:2.4px;
}
.tool-kpi .tk-val i{ font-size:.7em; color:var(--accent); }
.tool-kpi .tk-lbl{ font-size:8.8px; color:var(--text-muted); white-space:nowrap; }

.tool-share{ margin-top:auto; }
.tool-share-head{
  display:flex; justify-content:space-between; align-items:center;
  font-size:9.6px; color:var(--text-muted); margin-bottom:4.8px;
}
.tool-share-head b{ color:var(--primary-700); font-size:10.4px; }
.tool-share-bar{ height:6.4px; border-radius:799.2px; background:var(--surface-2); overflow:hidden; }
.tool-share-bar > span{ display:block; height:100%; border-radius:799.2px; background:var(--primary-500); transition:width .45s ease; }

.tool-foot{ font-size:9.6px; color:var(--text-muted); display:flex; align-items:center; gap:4.8px; }

html[data-theme="dark"] .tool-kpi{ background:var(--surface-1) !important; border-color:var(--border-color) !important; }
html[data-theme="dark"] .tool-kpi .tk-val{ color:var(--text-strong) !important; }

/* جدول المستخدمين النشطين */
.user-table th, .user-table td{
  text-align:start; padding:8px 9.6px; border-bottom:0.8px solid var(--border-color); font-size:11.2px;
}
.user-table thead th{ background:var(--surface-1); color:var(--text-strong); font-weight:700; }
.user-table tbody tr:hover{ background:var(--primary-50); }

/* إصلاح تباين الجدول في الوضع الداكن */
html[data-theme="dark"] .user-table th,
html[data-theme="dark"] .user-table td {
  color: var(--text-strong) !important;
  border-bottom-color: var(--border-color) !important;
}

html[data-theme="dark"] .user-table thead th {
  background: var(--surface-1) !important;
  color: var(--text-strong) !important;
}

/* كارد المستخدمين النشطين أعرض */
.stat-card.is-wide{ grid-column:span 2; }
@media (max-width:768px){ .stat-card.is-wide{ grid-column:auto; } }

/* ===== Pagination (Active Users) ===== */
#dipActiveUsersCard .pagination{
  display:flex; gap:8px; align-items:center; justify-content:flex-start;
  margin-top:8px;
}

/* أزرار السابق/التالي — نمط كبسولة أنيق */
#dipActiveUsersCard .pagination .seg-btn{
  display:inline-flex; align-items:center; gap:6.4px;
  padding:6.4px 11.2px; border-radius:799.2px;
  border:0.8px solid var(--border-color);
  background:var(--surface-0); color:var(--text);
  font-weight:600; font-size:10.4px;
  box-shadow: var(--shadow-xs, 0 0.8px 1.6px rgba(0,0,0,.04));
  transition: transform .12s ease, background .2s, border-color .2s, color .2s, box-shadow .2s;
}

/* أيقونات داخل الزر (اختياري إن أضفت <i> بفونت أويسم) */
#dipActiveUsersCard .pagination .seg-btn i{
  font-size: 9.6px; opacity: .8;
}

/* Hover */
#dipActiveUsersCard .pagination .seg-btn:hover{
  transform: translateY(-0.8px);
  border-color: var(--primary-300);
  background: var(--primary-50);
  color: var(--primary-900);
  box-shadow: var(--shadow-sm, 0 1.6px 4.8px rgba(0,0,0,.06));
}

/* Focus */
#dipActiveUsersCard .pagination .seg-btn:focus-visible{
  outline: none;
  box-shadow: 0 0 0 2.4px color-mix(in oklab, var(--primary) 20%, transparent);
  border-color: var(--primary-400);
}

/* Active (press) */
#dipActiveUsersCard .pagination .seg-btn:active{
  transform: translateY(0);
}

/* Disabled */
#dipActiveUsersCard .pagination .seg-btn:disabled{
  cursor: not-allowed;
  opacity: .55;
  background: var(--surface-1);
  color: var(--text-muted);
  border-color: var(--border-color);
  box-shadow: none;
}

/* إصلاح تباين pagination في الوضع الداكن */
html[data-theme="dark"] #dipActiveUsersCard .pagination .seg-btn {
  color: var(--text) !important;
  background: var(--surface-0) !important;
  border-color: var(--border-color) !important;
}

html[data-theme="dark"] #dipActiveUsersCard .pagination .seg-btn:hover:not(:disabled) {
  color: var(--primary-500) !important;
  background: var(--primary-50) !important;
  border-color: var(--primary-300) !important;
}

/* شارة معلومات الصفحات */
#dipAUPaginationInfo{
  display:inline-flex; align-items:center; gap:4.8px;
  font-size:9.6px; color:var(--text-muted);
  background: var(--surface-1);
  border:0.8px dashed var(--border-color);
  border-radius: 799.2px;
  padding:4.8px 8px;
}

/* إصلاح تباين معلومات الصفحات في الوضع الداكن */
html[data-theme="dark"] #dipAUPaginationInfo {
  color: var(--text-muted) !important;
  background: var(--surface-1) !important;
  border-color: var(--border-color) !important;
}

/* مظهر مدمج (اختياري) */
@media (max-width: 640px){
  #dipActiveUsersCard .pagination{
    gap:6.4px; flex-wrap:wrap;
  }
  #dipActiveUsersCard .pagination .seg-btn{
    padding:5.6px 9.6px; font-size:9.6px;
  }
  #dipAUPaginationInfo{
    margin-top:4.8px;
  }
}

/* ===== بطاقة AI Jobs KPI (فلات احترافية) ===== */
.ai-jobs-kpi-card .stat-icon--ai {
  background: var(--primary-50);
  color: var(--primary);
  border-radius: 11.2px;
}

/* شبكة KPI الرئيسية: 2×2 */
.ai-kpi-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin: 9.6px 0;
}

/* بند KPI واحد */
.ai-kpi-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9.6px 11.2px;
  border-radius: 8px;
  border: 0.8px solid var(--border-color);
  background: var(--surface-1);
  min-width: 0;
}

/* أيقونة البند */
.ai-kpi-icon {
  width: 28.8px;
  height: 28.8px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}

/* تخصيص اللون لكل نوع */
.ai-kpi-primary .ai-kpi-icon  { background: var(--primary-50); color: var(--primary); }
.ai-kpi-secondary .ai-kpi-icon{ background: var(--accent-50, rgba(253,158,2,.12)); color: var(--accent); }
.ai-kpi-success .ai-kpi-icon  { background: rgba(34,197,94,.12);   color: #22c55e; }
.ai-kpi-danger .ai-kpi-icon   { background: rgba(239,68,68,.12);   color: #ef4444; }

.ai-kpi-body  { min-width: 0; }
.ai-kpi-label { font-size: 8.8px; color: var(--text-muted); margin-bottom: 1.6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ai-kpi-value { font-size: 1.35rem; font-weight: 800; color: var(--text-strong); line-height: 1; }

/* وضع داكن */
html[data-theme="dark"] .ai-kpi-item { background: var(--surface-1) !important; border-color: var(--border-color) !important; }
html[data-theme="dark"] .ai-kpi-label { color: var(--text-muted) !important; }
html[data-theme="dark"] .ai-kpi-value { color: var(--text-strong) !important; }

/* قائمة التوزيع حسب النوع */
.ai-jobs-type-list {
  margin-top: 6.4px;
  border-top: 0.8px solid var(--border-color);
  padding-top: 8px;
}
.ai-job-type-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 5.6px 0;
  border-bottom: 0.8px dashed var(--border-color);
  font-size: 10.4px;
  gap: 6.4px;
}
.ai-job-type-row:last-child { border-bottom: none; }
.ai-job-type-name { color: var(--text); font-weight: 600; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ai-job-type-uses { color: var(--primary); font-weight: 800; font-size: 10.4px; white-space: nowrap; }
.ai-job-type-uniq { color: var(--text-muted); font-size: 8.8px; white-space: nowrap; }

html[data-theme="dark"] .ai-job-type-name  { color: var(--text) !important; }
html[data-theme="dark"] .ai-job-type-uses  { color: var(--primary-500) !important; }
html[data-theme="dark"] .ai-job-type-uniq  { color: var(--text-muted) !important; }
html[data-theme="dark"] .ai-jobs-type-list { border-color: var(--border-color) !important; }
html[data-theme="dark"] .ai-job-type-row   { border-color: var(--border-color) !important; }

/* تكيّف موبايل */
@media (max-width: 480px) {
  .ai-kpi-grid { grid-template-columns: 1fr; }
}

</style>
